<?php
// appointment_reports.php - Appointment Reports for Objective 2.5
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/auth_guard.php';
ensure_role(['doctor', 'assistant', 'admin']);
include("../config/db_connect.php");
require_once __DIR__ . '/../includes/security.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get report parameters
$report_type = $_GET['type'] ?? 'daily';
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$doctor_id = $_GET['doctor_id'] ?? null;

// Fetch appointments data
$query = "
    SELECT a.id, a.appointment_date, a.status, a.urgency,
           CONCAT(p.first_name, ' ', p.last_name) as patient_name,
           p.age, p.gender, p.health_concern,
           CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
           d.specialization,
           (
               SELECT mp.score
               FROM ml_predictions mp
               WHERE mp.visit_id IN (CONCAT('APP', a.id), CAST(a.id AS CHAR))
               ORDER BY mp.scored_at DESC
               LIMIT 1
           ) AS risk_score,
           (
               SELECT mp.risk_tier
               FROM ml_predictions mp
               WHERE mp.visit_id IN (CONCAT('APP', a.id), CAST(a.id AS CHAR))
               ORDER BY mp.scored_at DESC
               LIMIT 1
           ) AS risk_tier
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users d ON a.doctor_id = d.id
    WHERE DATE(a.appointment_date) BETWEEN ? AND ?
      AND NOT EXISTS (
          SELECT 1
          FROM appointments a_dup
          WHERE a_dup.doctor_id = a.doctor_id
            AND a_dup.patient_id = a.patient_id
            AND a_dup.appointment_date = a.appointment_date
            AND a_dup.id > a.id
      )
";

$params = [$start_date, $end_date];
$types = "ss";

if ($doctor_id && $role !== 'user') {
    $query .= " AND a.doctor_id = ?";
    $params[] = $doctor_id;
    $types .= "i";
}

if ($role === 'doctor') {
    $query .= " AND a.doctor_id = ?";
    $params[] = $user_id;
    $types .= "i";
}

$query .= " ORDER BY a.appointment_date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$appointments = $stmt->get_result();

// Calculate statistics
$stats = [
    'total' => 0,
    'confirmed' => 0,
    'completed' => 0,
    'declined' => 0,
    'pending' => 0,
    'by_doctor' => []
];

while ($row = $appointments->fetch_assoc()) {
    $stats['total']++;
    $status_key = strtolower(trim((string) ($row['status'] ?? '')));
    if (isset($stats[$status_key])) {
        $stats[$status_key]++;
    }
    
    if (!isset($stats['by_doctor'][$row['doctor_name']])) {
        $stats['by_doctor'][$row['doctor_name']] = 0;
    }
    $stats['by_doctor'][$row['doctor_name']]++;
}

// Get user info for sidebar
$query = $conn->prepare("SELECT username, email, specialization FROM users WHERE id = ?");
$query->bind_param("i", $user_id);
$query->execute();
$user_result = $query->get_result();
$user = $user_result->fetch_assoc();

$sidebar_user_data = [
    'username' => htmlspecialchars($user['username']),
    'email' => htmlspecialchars($user['email']),
    'role' => $role,
    'specialization' => htmlspecialchars($user['specialization'] ?? 'General')
];

/**
 * Compute urgency from ML risk score/tier.
 * Thresholds:
 * - High: >= 70%
 * - Medium: 40% to < 70%
 * - Low: < 40%
 */
function hb_report_urgency_from_risk($risk_score, $risk_tier, $fallback = 'Normal'): string
{
    $tier = strtolower(trim((string) $risk_tier));
    if ($tier === 'high') {
        return 'High';
    }
    if ($tier === 'medium') {
        return 'Medium';
    }
    if ($tier === 'low') {
        return 'Low';
    }

    if ($risk_score !== null && $risk_score !== '') {
        $score = (float) $risk_score;
        // Support either 0..1 or 0..100 scale
        if ($score > 1.0) {
            $score /= 100.0;
        }
        if ($score >= 0.70) {
            return 'High';
        }
        if ($score >= 0.40) {
            return 'Medium';
        }
        return 'Low';
    }

    return (string) $fallback;
}

// Store data for export
$appointments_data = [];
$appointments->data_seek(0);
while ($row = $appointments->fetch_assoc()) {
    $row['urgency'] = hb_report_urgency_from_risk($row['risk_score'] ?? null, $row['risk_tier'] ?? null, $row['urgency'] ?? 'Normal');
    $appointments_data[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Reports - HealthBase</title>
    <link rel="icon" type="image/x-icon" href="/assets/icons/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="appointments.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <?php if ($role === 'assistant'): ?>
    <link rel="stylesheet" href="../assistant_view/css/assistant.css">
    <?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        .reports-container {
            max-width: 1400px;
            margin: 20px auto;
        }
        
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .filter-group label {
            display: block;
            color: #475569;
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 13px;
        }
        
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 10px;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .btn-generate {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
        }
        
        .export-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn-export {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-export-csv {
            background: #10b981;
            color: white;
        }
        
        .btn-export-pdf {
            background: #ef4444;
            color: white;
        }
        
        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border-left: 4px solid #3b82f6;
        }
        
        .stat-card.total { border-left-color: #8b5cf6; }
        .stat-card.confirmed { border-left-color: #10b981; }
        .stat-card.completed { border-left-color: #3b82f6; }
        .stat-card.declined { border-left-color: #ef4444; }
        .stat-card.pending { border-left-color: #f59e0b; }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #1e293b;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .appointments-table {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-confirmed { background: #d1fae5; color: #065f46; }
        .status-completed { background: #dbeafe; color: #1e40af; }
        .status-declined { background: #fee2e2; color: #991b1b; }
        .status-pending { background: #fef3c7; color: #92400e; }

        .urgency-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .urgency-high { background: #fee2e2; color: #991b1b; }
        .urgency-medium { background: #fef3c7; color: #92400e; }
        .urgency-low { background: #d1fae5; color: #065f46; }
        .urgency-normal { background: #e2e8f0; color: #334155; }
        
    .chart-container {
        background: white;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        min-height: 420px;
        display: flex;
        flex-direction: column;
    }
    
    #statusChart {
        max-height: 320px !important;
        height: 320px !important;
        width: 100% !important;
    }

    .status-legend {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 16px;
        margin-top: 14px;
        padding-top: 10px;
        border-top: 1px solid #e2e8f0;
    }

    .status-legend-item {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: #475569;
        font-weight: 600;
    }

    .status-legend-dot {
        width: 10px;
        height: 10px;
        border-radius: 999px;
        display: inline-block;
    }

    .status-legend-dot-confirmed { background: #10b981; }
    .status-legend-dot-completed { background: #3b82f6; }
    .status-legend-dot-declined { background: #ef4444; }
    .status-legend-dot-pending { background: #f59e0b; }
    </style>
</head>
<body class="dashboard-page">
    <?php 
    if ($role === 'doctor') {
        include '../includes/doctor_sidebar.php'; 
    } elseif ($role === 'assistant') {
        include '../assistant_view/includes/assistant_sidebar.php';
    } else {
        include '../includes/sidebar.php';
    }
    ?>
    
    <?php if ($role === 'assistant'): ?>
    <div class="assistant-main-content">
        <!-- Header -->
        <header class="assistant-header">
            <div class="assistant-header-left">
                <h1 class="assistant-welcome">Appointment Reports</h1>
                <p class="assistant-subtitle">Generate and analyze appointment data</p>
            </div>
            <div class="assistant-header-right">
                <div class="current-time" style="display: flex; align-items: center; gap: 8px; padding: 8px 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <i class="fas fa-clock" style="color: #3b82f6;"></i>
                    <span id="currentDateTime" style="color: #1e293b; font-weight: 600; font-size: 14px;"></span>
                </div>
            </div>
        </header>

        <div class="assistant-dashboard-content">
    <?php else: ?>
    <div class="main-content">
    <?php endif; ?>
        <div class="reports-container">
            <h2 style="margin-bottom: 20px;">
                <i class="fas fa-chart-line"></i> Appointment Reports
            </h2>
            
            <div class="filter-section">
                <form method="GET">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label>Report Type</label>
                            <select name="type">
                                <option value="daily" <?= $report_type === 'daily' ? 'selected' : '' ?>>Daily</option>
                                <option value="weekly" <?= $report_type === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                                <option value="monthly" <?= $report_type === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Start Date</label>
                            <input type="date" name="start_date" value="<?= $start_date ?>">
                        </div>
                        <div class="filter-group">
                            <label>End Date</label>
                            <input type="date" name="end_date" value="<?= $end_date ?>">
                        </div>
                        <?php if ($role !== 'doctor'): ?>
                        <div class="filter-group">
                            <label>Doctor (optional)</label>
                            <select name="doctor_id">
                                <option value="">All Doctors</option>
                                <?php
                                $doctors = $conn->query("SELECT id, first_name, last_name, specialization FROM users WHERE role='doctor'");
                                while ($doc = $doctors->fetch_assoc()):
                                    $selected = ($doctor_id == $doc['id']) ? 'selected' : '';
                                    echo "<option value='{$doc['id']}' $selected>{$doc['first_name']} {$doc['last_name']} - {$doc['specialization']}</option>";
                                endwhile;
                                ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn-generate">
                        <i class="fas fa-search"></i> Generate Report
                    </button>
                </form>
                <div class="export-buttons">
                    <button class="btn-export btn-export-csv" onclick="exportToCSV()">
                        <i class="fas fa-file-csv"></i> Export to CSV
                    </button>
                    <button class="btn-export btn-export-pdf" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf"></i> Export to PDF
                    </button>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <i class="fas fa-calendar-alt" style="font-size: 24px; color: #8b5cf6;"></i>
                    <div class="stat-value"><?= $stats['total'] ?></div>
                    <div class="stat-label">Total Appointments</div>
                </div>
                <div class="stat-card confirmed">
                    <i class="fas fa-check-circle" style="font-size: 24px; color: #10b981;"></i>
                    <div class="stat-value"><?= $stats['confirmed'] ?></div>
                    <div class="stat-label">Confirmed</div>
                </div>
                <div class="stat-card completed">
                    <i class="fas fa-check-square" style="font-size: 24px; color: #3b82f6;"></i>
                    <div class="stat-value"><?= $stats['completed'] ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card declined">
                    <i class="fas fa-times-circle" style="font-size: 24px; color: #ef4444;"></i>
                    <div class="stat-value"><?= $stats['declined'] ?></div>
                    <div class="stat-label">Declined</div>
                </div>
                <div class="stat-card pending">
                    <i class="fas fa-clock" style="font-size: 24px; color: #f59e0b;"></i>
                    <div class="stat-value"><?= $stats['pending'] ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
            
            <!-- Chart -->
            <div class="chart-container">
                <h3 style="margin-bottom: 15px;">Status Distribution</h3>
                <canvas id="statusChart"></canvas>
                <div class="status-legend" aria-label="Status legend">
                    <span class="status-legend-item"><span class="status-legend-dot status-legend-dot-confirmed"></span>Confirmed</span>
                    <span class="status-legend-item"><span class="status-legend-dot status-legend-dot-completed"></span>Completed</span>
                    <span class="status-legend-item"><span class="status-legend-dot status-legend-dot-declined"></span>Declined</span>
                    <span class="status-legend-item"><span class="status-legend-dot status-legend-dot-pending"></span>Pending</span>
                </div>
            </div>
            
            <!-- Appointments Table -->
            <div class="appointments-table">
                <h3 style="margin-bottom: 15px;">Appointment Details</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e5e7eb;">
                            <th style="padding: 12px; text-align: left; color: #475569;">Date & Time</th>
                            <th style="padding: 12px; text-align: left; color: #475569;">Patient</th>
                            <th style="padding: 12px; text-align: left; color: #475569;">Doctor</th>
                            <th style="padding: 12px; text-align: left; color: #475569;">Status</th>
                            <th style="padding: 12px; text-align: left; color: #475569;">Urgency</th>
                        </tr>
                    </thead>
                    <tbody id="appointmentsTable">
                        <?php foreach ($appointments_data as $row): ?>
                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                <td style="padding: 12px;">
                                    <?= date('M j, Y g:i A', strtotime($row['appointment_date'])) ?>
                                </td>
                                <td style="padding: 12px;">
                                    <strong><?= htmlspecialchars($row['patient_name']) ?></strong><br>
                                    <small style="color: #94a3b8;">
                                        <?= $row['age'] ?> years, <?= $row['gender'] ?>
                                    </small>
                                </td>
                                <td style="padding: 12px;">
                                    <?= htmlspecialchars($row['doctor_name']) ?><br>
                                    <small style="color: #94a3b8;"><?= htmlspecialchars($row['specialization']) ?></small>
                                </td>
                                <td style="padding: 12px;">
                                    <span class="status-badge status-<?= strtolower($row['status']) ?>">
                                        <?= $row['status'] ?>
                                    </span>
                                </td>
                                <td style="padding: 12px;">
                                    <?php
                                    $urgencyText = (string) ($row['urgency'] ?? 'Normal');
                                    $urgencyKey = strtolower(trim($urgencyText));
                                    if (!in_array($urgencyKey, ['high', 'medium', 'low'], true)) {
                                        $urgencyKey = 'normal';
                                    }
                                    ?>
                                    <span class="urgency-badge urgency-<?= htmlspecialchars($urgencyKey) ?>">
                                        <?= htmlspecialchars($urgencyText) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        // Current Date/Time for Assistant Header
        function updateDateTime() {
            const element = document.getElementById('currentDateTime');
            if (!element) return; // Element doesn't exist (not assistant view)
            
            const now = new Date();
            const options = { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit', 
                minute: '2-digit',
                second: '2-digit'
            };
            element.textContent = now.toLocaleDateString('en-US', options);
        }
        updateDateTime();
        setInterval(updateDateTime, 1000);
        
        // Status Distribution Chart
        (function() {
            function initChart() {
                const ctx = document.getElementById('statusChart');
                if (ctx && typeof Chart !== 'undefined') {
                    new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: ['Confirmed', 'Completed', 'Declined', 'Pending'],
                            datasets: [{
                                data: [
                                    <?= $stats['confirmed'] ?>,
                                    <?= $stats['completed'] ?>,
                                    <?= $stats['declined'] ?>,
                                    <?= $stats['pending'] ?>
                                ],
                                backgroundColor: [
                                    '#10b981',
                                    '#3b82f6',
                                    '#ef4444',
                                    '#f59e0b'
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    enabled: true,
                                    backgroundColor: 'rgba(0,0,0,0.8)',
                                    titleFont: { size: 14 },
                                    bodyFont: { size: 13 },
                                    padding: 10
                                }
                            }
                        }
                    });
                } else if (typeof Chart === 'undefined') {
                    setTimeout(initChart, 100);
                }
            }
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initChart);
            } else {
                initChart();
            }
        })();
        
        // Export to CSV
        function exportToCSV() {
            const url = 'export_reports.php?type=csv&start_date=<?= $start_date ?>&end_date=<?= $end_date ?><?= $doctor_id ? "&doctor_id=" . $doctor_id : "" ?>';
            window.open(url, '_blank');
        }
        
        // Export to PDF
        function exportToPDF() {
            const url = 'export_reports.php?type=pdf&start_date=<?= $start_date ?>&end_date=<?= $end_date ?><?= $doctor_id ? "&doctor_id=" . $doctor_id : "" ?>';
            window.open(url, '_blank');
        }
    </script>
</body>
</html>
