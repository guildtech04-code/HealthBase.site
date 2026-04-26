<?php

/**
 * Patient cannot have two overlapping active visits (pending/confirmed).
 * Overlap uses fixed-length intervals from each appointment_datetime (default 30 minutes).
 *
 * - Overlaps an existing **confirmed** visit → returns error (book elsewhere or cancel/reschedule first).
 * - Overlaps only **pending** visits → those rows are set to **cancelled**, then caller may insert.
 *
 * @param bool $you_form true = message uses "You" (patient self-booking); false = "This patient" (staff)
 * @return array{ok: bool, error?: string}
 */
function hb_resolve_patient_appointment_overlap(
    mysqli $conn,
    int $patient_id,
    string $appointment_date_sql,
    int $overlap_slot_min = 30,
    bool $you_form = false
): array {
    if ($patient_id <= 0) {
        return ['ok' => true];
    }

    $ov_stmt = $conn->prepare('
        SELECT id, LOWER(TRIM(status)) AS st
        FROM appointments
        WHERE patient_id = ?
        AND LOWER(TRIM(status)) IN (\'pending\', \'confirmed\')
        AND appointment_date < DATE_ADD(?, INTERVAL ? MINUTE)
        AND DATE_ADD(appointment_date, INTERVAL ? MINUTE) > ?
    ');
    $ov_stmt->bind_param('isiis', $patient_id, $appointment_date_sql, $overlap_slot_min, $overlap_slot_min, $appointment_date_sql);
    $ov_stmt->execute();
    $ov_rs = $ov_stmt->get_result();
    $pending_overlap_ids = [];
    $has_confirmed_overlap = false;
    while ($ov_row = $ov_rs->fetch_assoc()) {
        if ($ov_row['st'] === 'confirmed') {
            $has_confirmed_overlap = true;
        } elseif ($ov_row['st'] === 'pending') {
            $pending_overlap_ids[] = (int) $ov_row['id'];
        }
    }
    $ov_stmt->close();

    if ($has_confirmed_overlap) {
        $msg = $you_form
            ? 'You already have a confirmed appointment that overlaps this time. Cancel or reschedule that visit first, or pick a different time.'
            : 'This patient already has a confirmed appointment that overlaps this time. Cancel or reschedule that visit first, or pick a different time.';

        return ['ok' => false, 'error' => $msg];
    }

    foreach ($pending_overlap_ids as $cancel_id) {
        $cancel_appt = $conn->prepare('UPDATE appointments SET status = \'cancelled\' WHERE id = ? AND patient_id = ? AND LOWER(TRIM(status)) = \'pending\'');
        $cancel_appt->bind_param('ii', $cancel_id, $patient_id);
        $cancel_appt->execute();
        $cancel_appt->close();
    }

    return ['ok' => true];
}
