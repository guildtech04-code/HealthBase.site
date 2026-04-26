<?php
require_once '../../config/db_connect.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=followup_compliance.csv');

$since = isset($_GET['since']) ? $_GET['since'] : date('Y-m-01');

$query = "
SELECT 
  f.created_at as created_at,
  f.patient_id,
  f.visit_id,
  f.priority_level,
  f.suggested_date,
  f.status,
  f.model_version,
  v.provider_specialty,
  v.diagnosis_group
FROM followup_queue f
LEFT JOIN opd_visits v ON v.visit_id = f.visit_id
WHERE f.created_at >= ?
ORDER BY f.created_at DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param('s', $since);
$stmt->execute();
$result = $stmt->get_result();

$output = fopen('php://output', 'w');
fputcsv($output, ['created_at','patient_id','visit_id','priority_level','suggested_date','status','model_version','provider_specialty','diagnosis_group']);
while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}
fclose($output);
exit;

