<?php
session_start();
require_once '../../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['assistant', 'admin', 'doctor'], true)) {
    http_response_code(403);
    exit('Forbidden');
}

$format = strtolower((string) ($_GET['format'] ?? 'csv'));
$doctorFilter = '';
$types = '';
$params = [];
if ($role === 'doctor') {
    $doctorFilter = ' AND a.doctor_id = ? ';
    $types = 'i';
    $params[] = (int) $_SESSION['user_id'];
}

$displayRows = [];
try {
    $query = "
        SELECT
            a.id as appointment_id,
            a.patient_id,
            a.doctor_id,
            a.appointment_date as visit_date,
            p.first_name,
            p.last_name,
            p.age,
            p.gender,
            c.diagnosis,
            c.follow_up_date as recommended_followup,
            u.specialization as doctor_specialty,
            mp.score as risk_score,
            mp.risk_tier,
            fq.suggested_date as suggested_followup_date,
            fq.status as followup_status,
            u.first_name as doctor_first_name,
            u.last_name as doctor_last_name
        FROM appointments a
        INNER JOIN patients p ON a.patient_id = p.id
        LEFT JOIN consultations c ON a.id = c.appointment_id
        LEFT JOIN users u ON a.doctor_id = u.id
        LEFT JOIN ml_predictions mp ON CONCAT('APP', a.id) = mp.visit_id OR CAST(a.id AS CHAR) = mp.visit_id
        LEFT JOIN followup_queue fq ON a.patient_id = fq.patient_id AND fq.status = 'Pending'
        WHERE a.status IN ('Completed', 'Confirmed')
        {$doctorFilter}
        ORDER BY a.appointment_date DESC
        LIMIT 100
    ";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($result && ($r = $result->fetch_assoc())) {
            $rows[] = $r;
        }

        $byAppointment = [];
        foreach ($rows as $r) {
            $aid = (int) ($r['appointment_id'] ?? 0);
            if (!isset($byAppointment[$aid])) {
                $byAppointment[$aid] = $r;
            }
        }
        $rows = array_values($byAppointment);
        usort($rows, static function ($a, $b) {
            return strtotime((string) ($b['visit_date'] ?? '')) <=> strtotime((string) ($a['visit_date'] ?? ''));
        });

        $seenPatient = [];
        foreach ($rows as $r) {
            $pid = (int) ($r['patient_id'] ?? 0);
            if ($pid > 0 && isset($seenPatient[$pid])) {
                continue;
            }
            if ($pid > 0) {
                $seenPatient[$pid] = true;
            }
            $displayRows[] = $r;
        }
    }
} catch (Throwable $e) {
    $displayRows = [];
}

if ($format === 'pdf') {
    $totalPatients = count($displayRows);
    $highRisk = 0;
    $avgAcc = 0.0;
    $avgCnt = 0;
    foreach ($displayRows as $r) {
        if (($r['risk_tier'] ?? '') === 'High') $highRisk++;
        if (($r['risk_score'] ?? '') !== '' && $r['risk_score'] !== null) {
            $avgAcc += (float) $r['risk_score'];
            $avgCnt++;
        }
    }
    $avgRiskPct = $avgCnt > 0 ? round(($avgAcc / $avgCnt) * 100, 1) : 0;
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!doctype html><html><head><meta charset="utf-8"><title>Generating PDF...</title></head><body>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <script>
    (function () {
      const { jsPDF } = window.jspdf;
      const rows = <?php echo json_encode($displayRows, JSON_UNESCAPED_UNICODE); ?> || [];
      const doc = new jsPDF({ orientation: "landscape", unit: "mm", format: "a4" });
      doc.setFont("helvetica","bold"); doc.setFontSize(18); doc.text("Risk Prediction Summary Report",14,16);
      doc.setFont("helvetica","normal"); doc.setFontSize(10);
      doc.text("HealthBase Medical Clinic",14,23);
      doc.text("Total Patients: <?php echo (int) $totalPatients; ?>",14,29);
      doc.text("High-Risk Patients: <?php echo (int) $highRisk; ?>",14,34);
      doc.text("Average Risk Score: <?php echo (float) $avgRiskPct; ?>%",14,39);
      doc.text("Generated: <?php echo date('F j, Y g:i A'); ?>",205,39);
      doc.line(14,42,282,42);
      const cols={dt:14,patient:38,age:82,sex:92,dx:104,score:170,tier:188,doctor:204,spec:238}; let y=49;
      function h(){doc.setFont("helvetica","bold");doc.setFontSize(8);doc.text("Date & Time",cols.dt,y);doc.text("Patient",cols.patient,y);doc.text("Age",cols.age,y);doc.text("Sex",cols.sex,y);doc.text("Diagnosis",cols.dx,y);doc.text("Score",cols.score,y);doc.text("Tier",cols.tier,y);doc.text("Doctor",cols.doctor,y);doc.text("Specialization",cols.spec,y);y+=3;doc.line(14,y,282,y);y+=5;}
      h(); doc.setFont("helvetica","normal"); doc.setFontSize(7);
      rows.forEach(r=>{ if(y>190){doc.addPage("a4","landscape"); y=16; h();} const dt=(r.visit_date||"").replace("T"," ").substring(0,16); const p=((r.first_name||"")+" "+(r.last_name||"")).trim().slice(0,24); const age=String(r.age??""); const sex=String(r.gender??""); const dx=((r.diagnosis||"Not recorded")+"").slice(0,28); const score=(r.risk_score===null||r.risk_score==="")?"-":(Number(r.risk_score)*100).toFixed(1)+"%"; const tier=r.risk_tier||"Low"; const d=((r.doctor_first_name||"")+" "+(r.doctor_last_name||"")).trim().slice(0,18); const s=(r.doctor_specialty||"").slice(0,24); doc.text(dt,cols.dt,y); doc.text(p,cols.patient,y); doc.text(age,cols.age,y); doc.text(sex,cols.sex,y); doc.text(dx,cols.dx,y); doc.text(score,cols.score,y); doc.text(tier,cols.tier,y); doc.text(d,cols.doctor,y); doc.text(s,cols.spec,y); y+=6; });
      doc.save("risk_prediction_summary_<?php echo date('Ymd'); ?>.pdf");
      setTimeout(()=>window.close(),500);
    })();
    </script><p style="font-family:Arial,sans-serif;text-align:center;margin-top:60px;color:#334155;">Generating PDF... your download should start automatically.</p></body></html>
    <?php
    exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="risk_summary_' . date('Ymd_His') . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['patient_name','age','gender','visit_date','diagnosis','risk_tier','risk_score','health_trend','followup_date','followup_status','doctor','doctor_specialty']);
foreach ($displayRows as $r) {
    $patient = trim((string) (($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')));
    $dx = trim((string) ($r['diagnosis'] ?? ''));
    if ($dx === '') $dx = 'Not recorded';
    $tier = (string) ($r['risk_tier'] ?? '');
    $score = $r['risk_score'] ?? '';
    $trend = $tier === 'High' ? 'Needs Attention' : (($score === '' || $score === null) ? 'Pending Assessment' : 'Stable');
    $fdate = (string) (($r['suggested_followup_date'] ?? '') ?: ($r['recommended_followup'] ?? ''));
    $fstatus = (string) ($r['followup_status'] ?? ($fdate !== '' ? 'Suggested' : 'Not scheduled'));
    $doctor = trim((string) (($r['doctor_first_name'] ?? '') . ' ' . ($r['doctor_last_name'] ?? '')));
    fputcsv($out, [$patient, $r['age'] ?? '', $r['gender'] ?? '', $r['visit_date'] ?? '', $dx, $tier, $score, $trend, $fdate, $fstatus, $doctor, $r['doctor_specialty'] ?? '']);
}
fclose($out);
exit;
