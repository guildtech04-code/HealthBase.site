<?php
/**
 * Generate Test Data for ML Prediction Testing
 * 
 * This script creates sample appointments, consultations, and patient records
 * so you can test the ML prediction system without waiting for real data.
 */

require_once __DIR__ . '/../config/db_connect.php';

// Check if running from command line or web
$is_cli = php_sapi_name() === 'cli';

if (!$is_cli && !isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

echo "Generating test data for ML prediction testing...\n\n";

// Get existing patients and doctors
$patients_result = $conn->query("SELECT id, first_name, last_name, age, gender FROM patients ORDER BY id LIMIT 20");
$doctors_result = $conn->query("SELECT id FROM users WHERE role = 'doctor' ORDER BY id LIMIT 5");

$patients = [];
$doctors = [];

if ($patients_result) {
    while ($row = $patients_result->fetch_assoc()) {
        $patients[] = $row;
    }
}

if ($doctors_result) {
    while ($row = $doctors_result->fetch_assoc()) {
        $doctors[] = $row['id'];
    }
}

if (empty($patients)) {
    die("ERROR: No patients found. Please create at least one patient first.\n");
}

if (empty($doctors)) {
    die("ERROR: No doctors found. Please create at least one doctor first.\n");
}

echo "Found " . count($patients) . " patients and " . count($doctors) . " doctors.\n\n";

// Sample diagnoses for variety
$diagnoses = [
    'Hypertension',
    'Diabetes Mellitus',
    'Upper Respiratory Tract Infection',
    'Asthma',
    'UTI',
    'Headache',
    'Chest Pain',
    'Fever',
    'Back Pain',
    'Common Cold'
];

// Generate appointments over the last 60 days
$base_date = new DateTime();
$days_back = 60;
$appointments_created = 0;
$consultations_created = 0;

echo "Creating appointments and consultations...\n";

for ($i = 0; $i < $days_back; $i++) {
    $date = clone $base_date;
    $date->modify("-$i days");
    
    // Skip weekends (you can adjust this)
    if ($date->format('N') >= 6) {
        continue;
    }
    
    // Create 2-4 appointments per day
    $appts_per_day = rand(2, 4);
    
    for ($j = 0; $j < $appts_per_day; $j++) {
        $patient = $patients[array_rand($patients)];
        $doctor_id = $doctors[array_rand($doctors)];
        
        // Random time between 9 AM and 4 PM
        $hour = rand(9, 16);
        $minute = rand(0, 1) * 30; // 0 or 30
        
        $appointment_date = $date->format('Y-m-d') . ' ' . sprintf('%02d:%02d:00', $hour, $minute);
        
        // Status: mostly Completed, some Confirmed
        $status = rand(1, 10) <= 8 ? 'Completed' : 'Confirmed';
        
        // Insert appointment
        $stmt = $conn->prepare("
            INSERT INTO appointments (patient_id, doctor_id, appointment_date, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->bind_param("iiss", $patient['id'], $doctor_id, $appointment_date, $status);
        
        if ($stmt->execute()) {
            $appointment_id = $stmt->insert_id;
            $appointments_created++;
            
            // Create consultation record for Completed appointments (80% of them)
            if ($status === 'Completed' && rand(1, 10) <= 8) {
                $diagnosis = $diagnoses[array_rand($diagnoses)];
                $chief_complaint = "Patient complaint on " . $date->format('M d, Y');
                $consultation_notes = "Regular consultation. Patient presents with symptoms related to " . strtolower($diagnosis);
                
                // Random treatment plan
                $treatment_plan = "Follow-up scheduled. Medications prescribed as needed.";
                
                // Follow-up date: 7-30 days from appointment
                $followup_days = rand(7, 30);
                $followup_date = clone $date;
                $followup_date->modify("+$followup_days days");
                
                $consult_stmt = $conn->prepare("
                    INSERT INTO consultations 
                    (appointment_id, patient_id, doctor_id, visit_date, chief_complaint, consultation_notes, diagnosis, treatment_plan, follow_up_date, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $consult_stmt->bind_param("iiiisssss", 
                    $appointment_id,
                    $patient['id'],
                    $doctor_id,
                    $appointment_date,
                    $chief_complaint,
                    $consultation_notes,
                    $diagnosis,
                    $treatment_plan,
                    $followup_date->format('Y-m-d')
                );
                
                if ($consult_stmt->execute()) {
                    $consultations_created++;
                }
                $consult_stmt->close();
            }
        }
        $stmt->close();
    }
}

echo "\n✅ Generated test data:\n";
echo "   - Appointments created: $appointments_created\n";
echo "   - Consultations created: $consultations_created\n\n";

// Check if we need to create opd_visits records for the batch scorer
// The batch scorer reads from opd_visits table
echo "Checking for opd_visits table...\n";

$table_check = $conn->query("SHOW TABLES LIKE 'opd_visits'");
if ($table_check->num_rows === 0) {
    echo "⚠️  'opd_visits' table not found. Creating it...\n";
    
    $create_table = "
        CREATE TABLE IF NOT EXISTS opd_visits (
            visit_id VARCHAR(100) PRIMARY KEY,
            patient_id VARCHAR(100) NOT NULL,
            visit_date DATETIME NOT NULL,
            age INT,
            sex VARCHAR(10),
            diagnosis_group VARCHAR(100),
            service_type VARCHAR(50),
            provider_specialty VARCHAR(50),
            systolic_bp INT,
            diastolic_bp INT,
            heart_rate INT,
            bmi DECIMAL(5,2),
            chronic_flag INT DEFAULT 0,
            prior_visits_90d INT DEFAULT 0,
            wait_time_minutes INT DEFAULT 0,
            pwd_flag INT DEFAULT 0,
            visit_session VARCHAR(20),
            INDEX idx_patient_date (patient_id, visit_date),
            INDEX idx_visit_date (visit_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    
    if ($conn->query($create_table)) {
        echo "✅ opd_visits table created.\n";
    } else {
        die("❌ Failed to create opd_visits table: " . $conn->error . "\n");
    }
}

// Populate opd_visits from appointments and consultations
echo "\nPopulating opd_visits table from appointments...\n";

$opd_query = "
    INSERT INTO opd_visits (
        visit_id, patient_id, visit_date, age, sex, diagnosis_group, 
        service_type, provider_specialty, systolic_bp, diastolic_bp, 
        heart_rate, bmi, chronic_flag, prior_visits_90d, wait_time_minutes, pwd_flag, visit_session
    )
    SELECT 
        CONCAT('APP', a.id) as visit_id,
        CAST(a.patient_id AS CHAR) as patient_id,
        a.appointment_date as visit_date,
        p.age,
        p.gender as sex,
        COALESCE(c.diagnosis, 'Not Specified') as diagnosis_group,
        'Consultation' as service_type,
        u.specialization as provider_specialty,
        -- Random vital signs
        FLOOR(100 + RAND() * 60) as systolic_bp,  -- 100-160
        FLOOR(60 + RAND() * 40) as diastolic_bp,   -- 60-100
        FLOOR(60 + RAND() * 60) as heart_rate,     -- 60-120
        ROUND(18 + RAND() * 18, 1) as bmi,         -- 18-36
        CASE WHEN c.diagnosis IN ('Hypertension', 'Diabetes Mellitus', 'Asthma') THEN 1 ELSE 0 END as chronic_flag,
        -- Prior visits count (simplified - could be calculated properly)
        FLOOR(RAND() * 5) as prior_visits_90d,
        FLOOR(10 + RAND() * 40) as wait_time_minutes,  -- 10-50 minutes
        0 as pwd_flag,
        CASE WHEN HOUR(a.appointment_date) < 12 THEN 'Morning' ELSE 'Afternoon' END as visit_session
    FROM appointments a
    INNER JOIN patients p ON a.patient_id = p.id
    LEFT JOIN consultations c ON a.id = c.appointment_id
    LEFT JOIN users u ON a.doctor_id = u.id
    WHERE a.status IN ('Completed', 'Confirmed')
        AND a.appointment_date >= DATE_SUB(NOW(), INTERVAL 60 DAY)
    ON DUPLICATE KEY UPDATE
        diagnosis_group = VALUES(diagnosis_group),
        systolic_bp = VALUES(systolic_bp),
        diastolic_bp = VALUES(diastolic_bp),
        heart_rate = VALUES(heart_rate),
        bmi = VALUES(bmi)
";

if ($conn->query($opd_query)) {
    $affected = $conn->affected_rows;
    echo "✅ Populated opd_visits with $affected records.\n\n";
} else {
    echo "⚠️  Note: " . $conn->error . "\n";
    echo "   (This is okay if records already exist)\n\n";
}

// Show summary
echo "📊 Test Data Summary:\n";
echo "═══════════════════════════════════════════════════════════\n";

$stats = $conn->query("
    SELECT 
        COUNT(DISTINCT a.patient_id) as unique_patients,
        COUNT(DISTINCT a.id) as total_appointments,
        COUNT(DISTINCT c.id) as total_consultations,
        COUNT(DISTINCT ov.visit_id) as opd_visits_count,
        MIN(a.appointment_date) as earliest_date,
        MAX(a.appointment_date) as latest_date
    FROM appointments a
    LEFT JOIN consultations c ON a.id = c.appointment_id
    LEFT JOIN opd_visits ov ON CONCAT('APP', a.id) = ov.visit_id
    WHERE a.appointment_date >= DATE_SUB(NOW(), INTERVAL 60 DAY)
");

if ($stats && $row = $stats->fetch_assoc()) {
    echo "   Unique Patients: " . $row['unique_patients'] . "\n";
    echo "   Total Appointments: " . $row['total_appointments'] . "\n";
    echo "   Total Consultations: " . ($row['total_consultations'] ?? 0) . "\n";
    echo "   OPD Visits Records: " . ($row['opd_visits_count'] ?? 0) . "\n";
    echo "   Date Range: " . date('M d, Y', strtotime($row['earliest_date'])) . " to " . date('M d, Y', strtotime($row['latest_date'])) . "\n";
}

echo "\n✅ Test data generation complete!\n";
echo "\n📝 Next Steps:\n";
echo "   1. Run the batch scorer: cd ml_module && python batch_scorer.py --since " . date('Y-m-d', strtotime('-60 days')) . "\n";
echo "   2. Or run with --all to score all visits\n";
echo "   3. Check the dashboard: /assistant_view/ml_dashboard.php\n";
echo "\n";

$conn->close();
?>

