<?php
/**
 * Trigger ML Prediction Scoring (Web Interface)
 * 
 * Allows assistants/admins to manually trigger batch scoring from the web dashboard
 * instead of using SSH command line.
 */

session_start();
require_once '../../config/db_connect.php';
require_once '../../includes/auth_guard.php';
require_once '../../includes/security.php';

// Always return JSON from this endpoint.
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

if (!function_exists('tp_json_fail')) {
    function tp_json_fail(string $message, int $status = 500, array $extra = []): void
    {
        if (ob_get_length() > 0) {
            ob_clean();
        }
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(array_merge([
            'success' => false,
            'message' => $message
        ], $extra));
        exit();
    }
}

set_exception_handler(static function (Throwable $e): void {
    tp_json_fail('Server exception while running predictions.', 500, [
        'error' => $e->getMessage()
    ]);
});

register_shutdown_function(static function (): void {
    $err = error_get_last();
    if (!$err) {
        return;
    }
    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int) $err['type'], $fatal, true)) {
        return;
    }
    tp_json_fail('Prediction service crashed before completing response.', 500, [
        'error' => (string) ($err['message'] ?? 'Fatal error')
    ]);
});

function tp_next_business_date(string $baseDate, int $businessDays = 10): string
{
    $dt = new DateTime($baseDate);
    $added = 0;
    while ($added < $businessDays) {
        $dt->modify('+1 day');
        $dow = (int) $dt->format('N');
        if ($dow <= 5) {
            $added++;
        }
    }
    return $dt->format('Y-m-d');
}

function tp_compute_rule_score(array $row): float
{
    $score = 0.0;
    $age = (int) ($row['age'] ?? 0);
    $systolic = (float) ($row['systolic_bp'] ?? 0);
    $diastolic = (float) ($row['diastolic_bp'] ?? 0);
    $heart = (float) ($row['heart_rate'] ?? 0);
    $prior90 = (int) ($row['prior_visits_90d'] ?? 0);
    $pwd = (int) ($row['pwd_flag'] ?? 0);

    if ($age > 0 && ($age < 5 || $age > 65)) {
        $score += 0.06;
    }
    if ($systolic >= 140 || $diastolic >= 90) {
        $score += 0.12;
    }
    if ($heart > 0 && ($heart < 60 || $heart > 100)) {
        $score += 0.10;
    }
    if ($prior90 >= 3) {
        $score += 0.20;
    }
    if ($pwd === 1) {
        $score += 0.10;
    }

    // If no diagnosis yet, leave room for uncertainty.
    $diagnosis = trim((string) ($row['diagnosis'] ?? ''));
    if ($diagnosis === '' || strcasecmp($diagnosis, 'Not recorded') === 0) {
        $score += 0.04;
    }

    return max(0.0, min(1.0, $score));
}

function tp_get_table_columns(mysqli $conn, string $table): array
{
    $cols = [];
    $stmt = $conn->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    if (!$stmt) {
        return $cols;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $rs = $stmt->get_result();
    while ($row = $rs->fetch_assoc()) {
        $k = strtolower((string) ($row['COLUMN_NAME'] ?? ''));
        if ($k !== '') {
            $cols[$k] = true;
        }
    }
    $stmt->close();
    return $cols;
}

function tp_run_php_fallback_scoring(mysqli $conn, string $since_date): array
{
    $model_version = 'php-fallback-v1';
    $threshold = 0.59;
    $model_rs = $conn->query("SELECT model_version, threshold FROM ml_models WHERE deployed = 1 ORDER BY created_at DESC LIMIT 1");
    if ($model_rs && ($model_row = $model_rs->fetch_assoc())) {
        $model_version = (string) ($model_row['model_version'] ?? $model_version);
        $threshold = (float) ($model_row['threshold'] ?? $threshold);
    }

    $patientsCols = tp_get_table_columns($conn, 'patients');
    $consultCols = tp_get_table_columns($conn, 'consultations');
    $predCols = tp_get_table_columns($conn, 'ml_predictions');
    $fqCols = tp_get_table_columns($conn, 'followup_queue');

    $pAge = isset($patientsCols['age']) ? 'p.age' : 'NULL';
    $pPwd = isset($patientsCols['pwd_flag']) ? 'p.pwd_flag' : '0';
    $cSys = isset($consultCols['systolic_bp']) ? 'c.systolic_bp' : 'NULL';
    $cDia = isset($consultCols['diastolic_bp']) ? 'c.diastolic_bp' : 'NULL';
    $cHr = isset($consultCols['heart_rate']) ? 'c.heart_rate' : 'NULL';
    $cDx = isset($consultCols['diagnosis']) ? 'c.diagnosis' : "''";

    $query = "
        SELECT
            a.id AS appointment_id,
            a.patient_id,
            DATE(a.appointment_date) AS visit_date,
            {$pAge} AS age,
            {$pPwd} AS pwd_flag,
            {$cSys} AS systolic_bp,
            {$cDia} AS diastolic_bp,
            {$cHr} AS heart_rate,
            {$cDx} AS diagnosis,
            (
                SELECT COUNT(*)
                FROM appointments a2
                WHERE a2.patient_id = a.patient_id
                  AND a2.appointment_date >= DATE_SUB(a.appointment_date, INTERVAL 90 DAY)
                  AND a2.appointment_date < a.appointment_date
                  AND a2.status IN ('Completed', 'Confirmed')
            ) AS prior_visits_90d
        FROM appointments a
        LEFT JOIN patients p ON p.id = a.patient_id
        LEFT JOIN consultations c ON c.appointment_id = a.id
        WHERE a.appointment_date >= ?
          AND a.status IN ('Completed', 'Confirmed')
        ORDER BY a.appointment_date DESC
    ";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new RuntimeException('Failed preparing fallback scoring query.');
    }
    $stmt->bind_param('s', $since_date);
    $stmt->execute();
    $rs = $stmt->get_result();

    $processed = 0;
    $high_risk = 0;

    while ($row = $rs->fetch_assoc()) {
        $processed++;
        $visit_id = 'APP' . (int) $row['appointment_id'];
        $patient_id = (string) ($row['patient_id'] ?? '');
        if ($patient_id === '') {
            continue;
        }

        $score = tp_compute_rule_score($row);
        $risk_tier = ($score >= $threshold) ? 'High' : 'Low';
        if ($risk_tier === 'High') {
            $high_risk++;
        }

        $features_json = json_encode([
            'source' => 'php_fallback',
            'appointment_id' => (int) $row['appointment_id'],
            'visit_date' => (string) ($row['visit_date'] ?? ''),
            'age' => (int) ($row['age'] ?? 0),
            'pwd_flag' => (int) ($row['pwd_flag'] ?? 0),
            'systolic_bp' => (float) ($row['systolic_bp'] ?? 0),
            'diastolic_bp' => (float) ($row['diastolic_bp'] ?? 0),
            'heart_rate' => (float) ($row['heart_rate'] ?? 0),
            'prior_visits_90d' => (int) ($row['prior_visits_90d'] ?? 0),
            'diagnosis' => (string) ($row['diagnosis'] ?? ''),
        ]);

        // Upsert-style behavior: update latest prediction row if visit_id exists, otherwise insert.
        $chk = $conn->prepare("SELECT 1 FROM ml_predictions WHERE visit_id = ? LIMIT 1");
        if ($chk) {
            $chk->bind_param('s', $visit_id);
            $chk->execute();
            $existing = $chk->get_result()->fetch_assoc();
            $chk->close();

            if ($existing) {
                $setParts = [];
                $types = '';
                $bind = [];
                if (isset($predCols['patient_id'])) {
                    $setParts[] = 'patient_id = ?';
                    $types .= 's';
                    $bind[] = $patient_id;
                }
                if (isset($predCols['score'])) {
                    $setParts[] = 'score = ?';
                    $types .= 'd';
                    $bind[] = $score;
                }
                if (isset($predCols['risk_tier'])) {
                    $setParts[] = 'risk_tier = ?';
                    $types .= 's';
                    $bind[] = $risk_tier;
                }
                if (isset($predCols['model_version'])) {
                    $setParts[] = 'model_version = ?';
                    $types .= 's';
                    $bind[] = $model_version;
                }
                if (isset($predCols['threshold'])) {
                    $setParts[] = 'threshold = ?';
                    $types .= 'd';
                    $bind[] = $threshold;
                }
                if (isset($predCols['features_json'])) {
                    $setParts[] = 'features_json = ?';
                    $types .= 's';
                    $bind[] = $features_json;
                }
                if (isset($predCols['scored_at'])) {
                    $setParts[] = 'scored_at = NOW()';
                }
                if (!empty($setParts)) {
                    $sql = "UPDATE ml_predictions SET " . implode(', ', $setParts) . " WHERE visit_id = ?";
                    $types .= 's';
                    $bind[] = $visit_id;
                    $upd = $conn->prepare($sql);
                    if ($upd) {
                        $upd->bind_param($types, ...$bind);
                        $upd->execute();
                        $upd->close();
                    }
                }
            } else {
                $insCols = [];
                $insVals = [];
                $types = '';
                $bind = [];
                if (isset($predCols['visit_id'])) {
                    $insCols[] = 'visit_id';
                    $insVals[] = '?';
                    $types .= 's';
                    $bind[] = $visit_id;
                }
                if (isset($predCols['patient_id'])) {
                    $insCols[] = 'patient_id';
                    $insVals[] = '?';
                    $types .= 's';
                    $bind[] = $patient_id;
                }
                if (isset($predCols['score'])) {
                    $insCols[] = 'score';
                    $insVals[] = '?';
                    $types .= 'd';
                    $bind[] = $score;
                }
                if (isset($predCols['risk_tier'])) {
                    $insCols[] = 'risk_tier';
                    $insVals[] = '?';
                    $types .= 's';
                    $bind[] = $risk_tier;
                }
                if (isset($predCols['model_version'])) {
                    $insCols[] = 'model_version';
                    $insVals[] = '?';
                    $types .= 's';
                    $bind[] = $model_version;
                }
                if (isset($predCols['threshold'])) {
                    $insCols[] = 'threshold';
                    $insVals[] = '?';
                    $types .= 'd';
                    $bind[] = $threshold;
                }
                if (isset($predCols['features_json'])) {
                    $insCols[] = 'features_json';
                    $insVals[] = '?';
                    $types .= 's';
                    $bind[] = $features_json;
                }
                if (isset($predCols['scored_at'])) {
                    $insCols[] = 'scored_at';
                    $insVals[] = 'NOW()';
                }
                if (!empty($insCols)) {
                    $sql = "INSERT INTO ml_predictions (" . implode(', ', $insCols) . ") VALUES (" . implode(', ', $insVals) . ")";
                    $ins = $conn->prepare($sql);
                    if ($ins) {
                        if ($types !== '') {
                            $ins->bind_param($types, ...$bind);
                        }
                        $ins->execute();
                        $ins->close();
                    }
                }
            }
        }

        if ($risk_tier === 'High') {
            $reason = 'High-risk patient detected (score: ' . number_format($score, 3) . ')';
            $priority = 'Priority';
            $suggested_date = tp_next_business_date((string) ($row['visit_date'] ?? date('Y-m-d')), 10);

            $hasPatientId = isset($fqCols['patient_id']);
            $hasVisitId = isset($fqCols['visit_id']);
            $hasStatus = isset($fqCols['status']);
            if ($hasPatientId && $hasVisitId && $hasStatus) {
                $fq_chk = $conn->prepare("SELECT 1 FROM followup_queue WHERE patient_id = ? AND visit_id = ? AND status = 'Pending' LIMIT 1");
                if ($fq_chk) {
                    $fq_chk->bind_param('ss', $patient_id, $visit_id);
                    $fq_chk->execute();
                    $existing_fq = $fq_chk->get_result()->fetch_assoc();
                    $fq_chk->close();

                    if (!$existing_fq) {
                        $fqInsCols = [];
                        $fqInsVals = [];
                        $fqTypes = '';
                        $fqBind = [];

                        if ($hasPatientId) {
                            $fqInsCols[] = 'patient_id';
                            $fqInsVals[] = '?';
                            $fqTypes .= 's';
                            $fqBind[] = $patient_id;
                        }
                        if ($hasVisitId) {
                            $fqInsCols[] = 'visit_id';
                            $fqInsVals[] = '?';
                            $fqTypes .= 's';
                            $fqBind[] = $visit_id;
                        }
                        if (isset($fqCols['priority_level'])) {
                            $fqInsCols[] = 'priority_level';
                            $fqInsVals[] = '?';
                            $fqTypes .= 's';
                            $fqBind[] = $priority;
                        }
                        if (isset($fqCols['suggested_date'])) {
                            $fqInsCols[] = 'suggested_date';
                            $fqInsVals[] = '?';
                            $fqTypes .= 's';
                            $fqBind[] = $suggested_date;
                        }
                        if (isset($fqCols['reason'])) {
                            $fqInsCols[] = 'reason';
                            $fqInsVals[] = '?';
                            $fqTypes .= 's';
                            $fqBind[] = $reason;
                        }
                        if (isset($fqCols['model_version'])) {
                            $fqInsCols[] = 'model_version';
                            $fqInsVals[] = '?';
                            $fqTypes .= 's';
                            $fqBind[] = $model_version;
                        }
                        if ($hasStatus) {
                            $fqInsCols[] = 'status';
                            $fqInsVals[] = "'Pending'";
                        }
                        if (isset($fqCols['created_at'])) {
                            $fqInsCols[] = 'created_at';
                            $fqInsVals[] = 'NOW()';
                        }

                        if (!empty($fqInsCols)) {
                            $sql = "INSERT INTO followup_queue (" . implode(', ', $fqInsCols) . ") VALUES (" . implode(', ', $fqInsVals) . ")";
                            $fq_ins = $conn->prepare($sql);
                            if ($fq_ins) {
                                if ($fqTypes !== '') {
                                    $fq_ins->bind_param($fqTypes, ...$fqBind);
                                }
                                $fq_ins->execute();
                                $fq_ins->close();
                            }
                        }
                    }
                }
            }
        }
    }
    $stmt->close();

    return [
        'processed' => $processed,
        'high_risk' => $high_risk,
        'model_version' => $model_version,
        'threshold' => $threshold
    ];
}

// Only assistants and admins can trigger predictions
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_role = $_SESSION['role'] ?? '';
if (!in_array($user_role, ['assistant', 'admin', 'doctor'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Only assistants, admins, and doctors can trigger predictions.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}
require_post_csrf();

// Get parameters
$since_date = $_POST['since'] ?? date('Y-m-d', strtotime('-7 days'));
$force_all = isset($_POST['all']) && $_POST['all'] === '1';
// Optional: force venv python
$use_venv = isset($_POST['venv']) && $_POST['venv'] === '1';
// Optional: diagnostic mode
$debug = isset($_POST['debug']) && $_POST['debug'] === '1';

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $since_date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD']);
    exit();
}

// Paths
$ml_module_path = realpath(__DIR__ . '/../../ml_module');
$batch_scorer = $ml_module_path . '/batch_scorer.py';
$python_sys = '/opt/alt/python311/bin/python3'; // Hostinger Python 3.11
$python_venv = $ml_module_path . '/venv/bin/python3';
$python_bin = ($use_venv && file_exists($python_venv)) ? $python_venv : $python_sys;

// Verify batch scorer exists
if (!file_exists($batch_scorer)) {
    echo json_encode([
        'success' => false,
        'message' => 'Batch scorer script not found.',
        'hint' => 'Expected at ' . $batch_scorer
    ]);
    exit();
}

if (!file_exists($python_bin)) {
    echo json_encode([
        'success' => false,
        'message' => 'Python executable not found.',
        'hint' => 'Checked: ' . $python_bin . ' (try adding venv=1)'
    ]);
    exit();
}

// If debug, return environment diagnostics
if ($debug) {
    $disabled = array_map('trim', explode(',', ini_get('disable_functions') ?: ''));
    $can_exec = function_exists('exec') && !in_array('exec', $disabled, true);
    $can_shell = function_exists('shell_exec') && !in_array('shell_exec', $disabled, true);
    $paths = [
        'ml_module_path' => $ml_module_path,
        'batch_scorer' => $batch_scorer,
        'python_bin' => $python_bin,
        'python_sys' => $python_sys,
        'python_venv' => $python_venv,
    ];
    $python_version = null;
    if ($can_exec && file_exists($python_bin)) {
        @exec(escapeshellarg($python_bin) . ' --version 2>&1', $pvOut, $pvCode);
        $python_version = implode("\n", (array)$pvOut);
    }
    echo json_encode([
        'success' => true,
        'message' => 'Debug info',
        'since' => $since_date,
        'force_all' => $force_all,
        'use_venv' => $use_venv,
        'paths' => $paths,
        'file_exists' => [
            'batch_scorer' => file_exists($batch_scorer),
            'python_bin' => file_exists($python_bin),
        ],
        'exec' => [
            'exec_enabled' => $can_exec,
            'shell_exec_enabled' => $can_shell,
            'disable_functions' => $disabled,
            'python_version' => $python_version,
        ],
    ], JSON_PRETTY_PRINT);
    exit();
}

// Build command
if ($force_all) {
    $cmd = "cd " . escapeshellarg($ml_module_path) . " && OPENBLAS_NUM_THREADS=1 OMP_NUM_THREADS=1 " . escapeshellarg($python_bin) . " batch_scorer.py --all 2>&1";
    $description = "scoring all visits";
} else {
    $cmd = "cd " . escapeshellarg($ml_module_path) . " && OPENBLAS_NUM_THREADS=1 OMP_NUM_THREADS=1 " . escapeshellarg($python_bin) . " batch_scorer.py --since " . escapeshellarg($since_date) . " 2>&1";
    $description = "scoring visits since $since_date";
}

// Log the trigger attempt (best-effort; skip if table missing)
$user_id = $_SESSION['user_id'];
$has_audit = false;
$tbl_res = $conn->query("SHOW TABLES LIKE 'audit_ml'");
if ($tbl_res && $tbl_res->num_rows > 0) {
    $has_audit = true;
}
if ($has_audit) {
    $audit_columns = [];
    $col_stmt = $conn->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'audit_ml'
    ");
    if ($col_stmt) {
        $col_stmt->execute();
        $col_rs = $col_stmt->get_result();
        while ($col_row = $col_rs->fetch_assoc()) {
            $audit_columns[strtolower((string) ($col_row['COLUMN_NAME'] ?? ''))] = true;
        }
        $col_stmt->close();
    }

    $event_data = json_encode([
        'action' => 'manual_trigger',
        'since_date' => $since_date,
        'all' => $force_all,
        'triggered_by' => $user_id,
        'command' => $description
    ]);

    $cols = [];
    $vals = [];
    $types = '';
    $bind = [];

    if (isset($audit_columns['event_type'])) {
        $cols[] = 'event_type';
        $vals[] = '?';
        $types .= 's';
        $bind[] = 'manual_scoring_trigger';
    } elseif (isset($audit_columns['action'])) {
        $cols[] = 'action';
        $vals[] = '?';
        $types .= 's';
        $bind[] = 'manual_scoring_trigger';
    } elseif (isset($audit_columns['type'])) {
        $cols[] = 'type';
        $vals[] = '?';
        $types .= 's';
        $bind[] = 'manual_scoring_trigger';
    }

    if (isset($audit_columns['event_data'])) {
        $cols[] = 'event_data';
        $vals[] = '?';
        $types .= 's';
        $bind[] = $event_data;
    } elseif (isset($audit_columns['data'])) {
        $cols[] = 'data';
        $vals[] = '?';
        $types .= 's';
        $bind[] = $event_data;
    } elseif (isset($audit_columns['payload'])) {
        $cols[] = 'payload';
        $vals[] = '?';
        $types .= 's';
        $bind[] = $event_data;
    }

    if (isset($audit_columns['created_by'])) {
        $cols[] = 'created_by';
        $vals[] = '?';
        $types .= 'i';
        $bind[] = (int) $user_id;
    } elseif (isset($audit_columns['user_id'])) {
        $cols[] = 'user_id';
        $vals[] = '?';
        $types .= 'i';
        $bind[] = (int) $user_id;
    }

    if (isset($audit_columns['created_at'])) {
        $cols[] = 'created_at';
        $vals[] = 'NOW()';
    } elseif (isset($audit_columns['timestamp'])) {
        $cols[] = 'timestamp';
        $vals[] = 'NOW()';
    }

    if (!empty($cols)) {
        $log_query = "INSERT INTO audit_ml (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
        $log_stmt = $conn->prepare($log_query);
        if ($log_stmt) {
            if ($types !== '' && !empty($bind)) {
                $log_stmt->bind_param($types, ...$bind);
            }
            $log_stmt->execute();
            $log_stmt->close();
        }
    }
}

// Execute command (no external timeout to avoid 500s on missing binary)
$output = [];
$return_var = 0;
$output_text = '';

$disabled = array_map('trim', explode(',', ini_get('disable_functions') ?: ''));
$can_exec = function_exists('exec') && !in_array('exec', $disabled, true);
$can_shell = function_exists('shell_exec') && !in_array('shell_exec', $disabled, true);

if ($can_exec) {
    exec($cmd, $output, $return_var);
    $output_text = implode("\n", (array)$output);
} elseif ($can_shell) {
    $output_text = shell_exec($cmd);
    $return_var = (strpos($output_text, 'Traceback') !== false || strpos($output_text, 'Error') !== false) ? 1 : 0;
} else {
    // Fallback path for shared hosting: run lightweight PHP-based scoring.
    try {
        $fallback = tp_run_php_fallback_scoring($conn, $since_date);
        echo json_encode([
            'success' => true,
            'message' => 'Predictions completed using PHP fallback scorer.',
            'processed' => (int) ($fallback['processed'] ?? 0),
            'high_risk' => (int) ($fallback['high_risk'] ?? 0),
            'timestamp' => date('Y-m-d H:i:s'),
            'fallback_used' => true,
            'hint' => 'Python execution is disabled on this server; used built-in scoring fallback.'
        ], JSON_PRETTY_PRINT);
        exit();
    } catch (Throwable $fallbackError) {
        echo json_encode([
            'success' => false,
            'message' => 'Server does not permit running external commands.',
            'hint' => 'Enable exec/shell_exec or run via SSH.',
            'command' => $cmd,
            'fallback_error' => $fallbackError->getMessage()
        ], JSON_PRETTY_PRINT);
        exit();
    }
}

// Check if command succeeded
$success = ($return_var === 0);

// Parse output for summary
$processed = 0;
$high_risk = 0;
if (preg_match('/Processed (\d+) visits/', $output_text, $matches)) {
    $processed = intval($matches[1]);
}
if (preg_match('/High-risk: (\d+)/', $output_text, $matches)) {
    $high_risk = intval($matches[1]);
}

// Prepare response
$response = [
    'success' => $success,
    'message' => $success 
        ? "Successfully completed $description" 
        : "Error occurred while $description",
    'processed' => $processed,
    'high_risk' => $high_risk,
    'output' => $output_text,
    'timestamp' => date('Y-m-d H:i:s')
];

// If command failed, include more details
if (!$success) {
    $response['error'] = "Command returned exit code: $return_var";
    $response['hint'] = "Try adding &venv=1 to use virtualenv Python. Or run via SSH.";
    $response['command'] = $cmd;
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>

