<?php

/**
 * Optional columns on `appointments` (guest booking / notes). Cached per request.
 *
 * @return array Guest and notes column presence flags.
 */
function hb_appointments_column_flags(mysqli $conn): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $guest = false;
    $notes = false;
    $r = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME IN ('guest_first_name','guest_last_name','notes')");
    if ($r) {
        $have = [];
        while ($row = $r->fetch_assoc()) {
            $have[$row['COLUMN_NAME']] = true;
        }
        $guest = !empty($have['guest_first_name']) && !empty($have['guest_last_name']);
        $notes = !empty($have['notes']);
    }
    $cache = ['guest' => $guest, 'notes' => $notes];

    return $cache;
}

/** Separator used in scheduling.php when guest name + concern are stored in `notes` only. */
const HB_APPT_NOTES_VISIT_SEP = ' — ';

/**
 * Parse "Visit for: First Last — …" written by scheduling when guest_* columns are absent.
 */
function hb_appointments_notes_extract_visit_for_name(?string $notes): string
{
    $notes = trim((string) $notes);
    if ($notes === '' || stripos($notes, 'Visit for:') !== 0) {
        return '';
    }
    if (!preg_match('/^Visit for:\s*/i', $notes, $m)) {
        return '';
    }
    $rest = trim(substr($notes, strlen($m[0])));
    $sep = HB_APPT_NOTES_VISIT_SEP;
    $p = function_exists('mb_strpos') ? mb_strpos($rest, $sep) : strpos($rest, $sep);
    if ($p !== false) {
        $slice = function_exists('mb_substr') ? mb_substr($rest, 0, $p) : substr($rest, 0, $p);

        return trim($slice);
    }

    return trim($rest);
}

/**
 * Health text after "Visit for: … — " in notes-only guest bookings.
 */
function hb_appointments_notes_extract_health_after_visit(?string $notes): string
{
    $notes = trim((string) $notes);
    if ($notes === '' || stripos($notes, 'Visit for:') !== 0) {
        return '';
    }
    if (!preg_match('/^Visit for:\s*/i', $notes, $m)) {
        return '';
    }
    $rest = trim(substr($notes, strlen($m[0])));
    $sep = HB_APPT_NOTES_VISIT_SEP;
    $p = function_exists('mb_strpos') ? mb_strpos($rest, $sep) : strpos($rest, $sep);
    if ($p === false) {
        return '';
    }
    $len = function_exists('mb_strlen') ? mb_strlen($sep) : strlen($sep);
    $after = function_exists('mb_substr') ? mb_substr($rest, $p + $len) : substr($rest, $p + $len);

    return trim($after);
}

/**
 * Doctor/patient UI: guest columns, else notes "Visit for:", else patients row.
 */
function hb_appointments_display_patient_name(array $row): string
{
    $g = trim((string) ($row['guest_first_name'] ?? '') . ' ' . (string) ($row['guest_last_name'] ?? ''));
    if ($g !== '') {
        return trim($g);
    }

    $fromNotes = hb_appointments_notes_extract_visit_for_name($row['appointment_notes'] ?? null);
    if ($fromNotes !== '') {
        return $fromNotes;
    }

    return trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
}

/**
 * Guest: appointments.notes (concern) or parsed from notes-only row; else patients.health_concern.
 */
function hb_appointments_display_health_concern(array $row): string
{
    $g = trim((string) ($row['guest_first_name'] ?? '') . ' ' . (string) ($row['guest_last_name'] ?? ''));
    $notes = trim((string) ($row['appointment_notes'] ?? ''));

    if ($g !== '') {
        return $notes !== '' ? $notes : trim((string) ($row['health_concern'] ?? ''));
    }

    if (hb_appointments_notes_extract_visit_for_name($notes) !== '') {
        $h = hb_appointments_notes_extract_health_after_visit($notes);

        return $h !== '' ? $h : trim((string) ($row['health_concern'] ?? ''));
    }

    return trim((string) ($row['health_concern'] ?? ''));
}
