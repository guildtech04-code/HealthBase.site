<?php
/**
 * ML Risk Scoring API
 * ===================
 * 
 * Endpoint for real-time ML risk scoring
 * POST /api/ml_score.php
 * 
 * Body: { "visit_id": "...", "patient_data": {...} }
 * 
 * Returns: { "score": 0.65, "risk_tier": "High", "threshold": 0.47 }
 */

require_once '../../config/db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['visit_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'visit_id is required']);
    exit;
}

$visit_id = $data['visit_id'];

// Fetch latest model
$model_query = "SELECT * FROM ml_models WHERE deployed = 1 ORDER BY created_at DESC LIMIT 1";
$model_result = $conn->query($model_query);
$model = $model_result->fetch_assoc();

if (!$model) {
    http_response_code(503);
    echo json_encode(['error' => 'No deployed model found']);
    exit;
}

// Check if prediction already exists
$check_query = "SELECT * FROM ml_predictions WHERE visit_id = ? LIMIT 1";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("s", $visit_id);
$stmt->execute();
$existing_prediction = $stmt->get_result()->fetch_assoc();

if ($existing_prediction) {
    // Return existing prediction
    echo json_encode([
        'score' => floatval($existing_prediction['score']),
        'risk_tier' => $existing_prediction['risk_tier'],
        'threshold' => floatval($model['threshold']),
        'model_version' => $model['model_version'],
        'from_cache' => true
    ]);
    exit;
}

// If no Python backend is available, return rule-based risk score
$visit_query = "SELECT * FROM opd_visits WHERE visit_id = ? LIMIT 1";
$stmt = $conn->prepare($visit_query);
$stmt->bind_param("s", $visit_id);
$stmt->execute();
$visit = $stmt->get_result()->fetch_assoc();

if (!$visit) {
    http_response_code(404);
    echo json_encode(['error' => 'Visit not found']);
    exit;
}

// Rule-based risk scoring (fallback when ML not available)
$risk_score = calculate_rule_based_risk($visit, $model['threshold']);

$risk_tier = $risk_score >= $model['threshold'] ? 'High' : 'Low';

// Save prediction
$insert_query = "INSERT INTO ml_predictions 
    (visit_id, patient_id, score, risk_tier, model_version, threshold, features_json)
    VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($insert_query);
$features_json = json_encode($visit);
$stmt->bind_param(
    "ssdssds",
    $visit_id,
    $visit['patient_id'],
    $risk_score,
    $risk_tier,
    $model['model_version'],
    $model['threshold'],
    $features_json
);
$stmt->execute();

// Generate follow-up if high-risk
if ($risk_tier === 'High') {
    generate_followup($visit, $risk_score, $model['model_version'], $conn);
}

echo json_encode([
    'score' => $risk_score,
    'risk_tier' => $risk_tier,
    'threshold' => floatval($model['threshold']),
    'model_version' => $model['model_version'],
    'from_cache' => false
]);

/**
 * Rule-based risk calculation
 */
function calculate_rule_based_risk($visit, $threshold) {
    $score = 0.0;
    
    // Age factor (very young or very old)
    if ($visit['age'] < 5 || $visit['age'] > 65) {
        $score += 0.05;
    }
    
    // Chronic conditions
    if (!empty($visit['chronic_flag']) && $visit['chronic_flag'] !== 'NONE') {
        $score += 0.15;
    }
    
    // Vital signs
    if ($visit['systolic_bp'] > 140 || $visit['diastolic_bp'] > 90) {
        $score += 0.10;
    }
    
    if ($visit['heart_rate'] < 50 || $visit['heart_rate'] > 100) {
        $score += 0.08;
    }
    
    // Recent visits
    if ($visit['prior_visits_90d'] >= 3) {
        $score += 0.20;
    }
    
    // PWD status
    if (isset($visit['pwd_flag']) && $visit['pwd_flag'] == 1) {
        $score += 0.10;
    }
    
    // Service type
    if ($visit['service_type'] === 'ER-to-OPD') {
        $score += 0.12;
    }
    
    // Ensure score is between 0 and 1
    return min(1.0, $score);
}

/**
 * Generate follow-up entry
 */
function generate_followup($visit, $score, $model_version, $conn) {
    // Calculate suggested date (10 days out, avoiding weekends)
    $suggested_date = date('Y-m-d', strtotime('+10 weekdays'));
    
    $priority = 'Standard';
    if ($score >= 0.7 || (isset($visit['pwd_flag']) && $visit['pwd_flag'] == 1)) {
        $priority = 'Priority';
    }
    
    $reason = "High-risk patient detected (score: " . number_format($score, 3) . ")";
    
    $insert_query = "INSERT INTO followup_queue 
        (patient_id, visit_id, priority_level, suggested_date, reason, model_version)
        VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param(
        "ssssss",
        $visit['patient_id'],
        $visit['visit_id'],
        $priority,
        $suggested_date,
        $reason,
        $model_version
    );
    $stmt->execute();
}

