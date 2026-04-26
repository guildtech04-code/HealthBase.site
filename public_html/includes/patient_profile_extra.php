<?php
/**
 * Extended patient profile (patient_profile_extra table).
 * Call hb_ensure_patient_profile_extra_table() once after DB connect to auto-create the table when missing.
 */

function hb_patient_profile_extra_table_exists(mysqli $conn): bool
{
    $r = $conn->query("SHOW TABLES LIKE 'patient_profile_extra'");
    return $r && $r->num_rows > 0;
}

/**
 * Creates patient_profile_extra if it does not exist (CREATE TABLE IF NOT EXISTS).
 * Returns true if the table exists afterward. On failure, logs error and returns false.
 */
function hb_ensure_patient_profile_extra_table(mysqli $conn): bool
{
    if (hb_patient_profile_extra_table_exists($conn)) {
        return true;
    }

    $ddl = <<<'SQL'
CREATE TABLE IF NOT EXISTS `patient_profile_extra` (
  `patient_id` int(11) NOT NULL,
  `nickname` varchar(100) DEFAULT NULL,
  `referring_physician` varchar(255) DEFAULT NULL,
  `primary_care_physician` varchar(255) DEFAULT NULL,
  `other_physician_1` varchar(255) DEFAULT NULL,
  `other_physician_2` varchar(255) DEFAULT NULL,
  `other_physician_3` varchar(255) DEFAULT NULL,
  `emergency_contact_name` varchar(255) DEFAULT NULL,
  `emergency_contact_phone` varchar(80) DEFAULT NULL,
  `emergency_relationship` varchar(100) DEFAULT NULL,
  `address_line` text,
  `other_mobile` varchar(80) DEFAULT NULL,
  `parent_guardian_1` varchar(255) DEFAULT NULL,
  `parent_guardian_2` varchar(255) DEFAULT NULL,
  `show_guardian_names` tinyint(1) NOT NULL DEFAULT 1,
  `occupation` varchar(255) DEFAULT NULL,
  `employer_name` varchar(255) DEFAULT NULL,
  `employer_address` text,
  `employer_phone` varchar(80) DEFAULT NULL,
  `hmo_name` varchar(255) DEFAULT NULL,
  `patient_tags` varchar(255) DEFAULT NULL,
  `nationality` varchar(120) DEFAULT NULL,
  `race` varchar(120) DEFAULT NULL,
  `religion` varchar(120) DEFAULT NULL,
  `blood_type` varchar(20) DEFAULT NULL,
  `civil_status` varchar(50) DEFAULT NULL,
  `philhealth_no` varchar(120) DEFAULT NULL,
  `invite_patient_app` tinyint(1) NOT NULL DEFAULT 0,
  `consent_acknowledged` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`patient_id`),
  CONSTRAINT `fk_patient_profile_extra_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

    if ($conn->query($ddl)) {
        return hb_patient_profile_extra_table_exists($conn);
    }

    error_log('hb_ensure_patient_profile_extra_table (with FK): ' . $conn->error);

    $ddlNoFk = <<<'SQL'
CREATE TABLE IF NOT EXISTS `patient_profile_extra` (
  `patient_id` int(11) NOT NULL,
  `nickname` varchar(100) DEFAULT NULL,
  `referring_physician` varchar(255) DEFAULT NULL,
  `primary_care_physician` varchar(255) DEFAULT NULL,
  `other_physician_1` varchar(255) DEFAULT NULL,
  `other_physician_2` varchar(255) DEFAULT NULL,
  `other_physician_3` varchar(255) DEFAULT NULL,
  `emergency_contact_name` varchar(255) DEFAULT NULL,
  `emergency_contact_phone` varchar(80) DEFAULT NULL,
  `emergency_relationship` varchar(100) DEFAULT NULL,
  `address_line` text,
  `other_mobile` varchar(80) DEFAULT NULL,
  `parent_guardian_1` varchar(255) DEFAULT NULL,
  `parent_guardian_2` varchar(255) DEFAULT NULL,
  `show_guardian_names` tinyint(1) NOT NULL DEFAULT 1,
  `occupation` varchar(255) DEFAULT NULL,
  `employer_name` varchar(255) DEFAULT NULL,
  `employer_address` text,
  `employer_phone` varchar(80) DEFAULT NULL,
  `hmo_name` varchar(255) DEFAULT NULL,
  `patient_tags` varchar(255) DEFAULT NULL,
  `nationality` varchar(120) DEFAULT NULL,
  `race` varchar(120) DEFAULT NULL,
  `religion` varchar(120) DEFAULT NULL,
  `blood_type` varchar(20) DEFAULT NULL,
  `civil_status` varchar(50) DEFAULT NULL,
  `philhealth_no` varchar(120) DEFAULT NULL,
  `invite_patient_app` tinyint(1) NOT NULL DEFAULT 0,
  `consent_acknowledged` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

    if ($conn->query($ddlNoFk)) {
        return hb_patient_profile_extra_table_exists($conn);
    }

    error_log('hb_ensure_patient_profile_extra_table (no FK): ' . $conn->error);
    return false;
}

function hb_patient_profile_extra_defaults(): array
{
    return [
        'nickname' => '',
        'referring_physician' => '',
        'primary_care_physician' => '',
        'other_physician_1' => '',
        'other_physician_2' => '',
        'other_physician_3' => '',
        'emergency_contact_name' => '',
        'emergency_contact_phone' => '',
        'emergency_relationship' => '',
        'address_line' => '',
        'other_mobile' => '',
        'parent_guardian_1' => '',
        'parent_guardian_2' => '',
        'show_guardian_names' => 1,
        'occupation' => '',
        'employer_name' => '',
        'employer_address' => '',
        'employer_phone' => '',
        'hmo_name' => '',
        'patient_tags' => '',
        'nationality' => '',
        'race' => '',
        'religion' => '',
        'blood_type' => '',
        'civil_status' => '',
        'philhealth_no' => '',
        'invite_patient_app' => 0,
        'consent_acknowledged' => 0,
    ];
}

function hb_get_patient_profile_extra(mysqli $conn, int $patient_id): array
{
    $defaults = hb_patient_profile_extra_defaults();
    if (!hb_patient_profile_extra_table_exists($conn)) {
        return $defaults;
    }
    $st = $conn->prepare('SELECT * FROM patient_profile_extra WHERE patient_id = ? LIMIT 1');
    if (!$st) {
        return $defaults;
    }
    $st->bind_param('i', $patient_id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    if (!$row) {
        return $defaults;
    }
    unset($row['patient_id'], $row['updated_at']);
    foreach ($defaults as $k => $_) {
        if (!array_key_exists($k, $row)) {
            $row[$k] = $defaults[$k];
        }
    }
    return array_merge($defaults, $row);
}

function hb_save_patient_profile_extra(mysqli $conn, int $patient_id, array $post, callable $sanitize_string): bool
{
    if (!hb_patient_profile_extra_table_exists($conn) || $patient_id < 1) {
        return false;
    }

    $s = function ($key, $max = 500) use ($post, $sanitize_string) {
        return $sanitize_string(trim((string) ($post['ppe_' . $key] ?? '')), $max);
    };

    $tiny = function ($key) use ($post): int {
        return !empty($post['ppe_' . $key]) ? 1 : 0;
    };

    $data = [
        'nickname' => $s('nickname', 100),
        'referring_physician' => $s('referring_physician', 255),
        'primary_care_physician' => $s('primary_care_physician', 255),
        'other_physician_1' => $s('other_physician_1', 255),
        'other_physician_2' => $s('other_physician_2', 255),
        'other_physician_3' => $s('other_physician_3', 255),
        'emergency_contact_name' => $s('emergency_contact_name', 255),
        'emergency_contact_phone' => $s('emergency_contact_phone', 80),
        'emergency_relationship' => $s('emergency_relationship', 100),
        'address_line' => $s('address_line', 2000),
        'other_mobile' => $s('other_mobile', 80),
        'parent_guardian_1' => $s('parent_guardian_1', 255),
        'parent_guardian_2' => $s('parent_guardian_2', 255),
        'show_guardian_names' => $tiny('show_guardian_names'),
        'occupation' => $s('occupation', 255),
        'employer_name' => $s('employer_name', 255),
        'employer_address' => $s('employer_address', 2000),
        'employer_phone' => $s('employer_phone', 80),
        'hmo_name' => $s('hmo_name', 255),
        'patient_tags' => $s('patient_tags', 255),
        'nationality' => $s('nationality', 120),
        'race' => $s('race', 120),
        'religion' => $s('religion', 120),
        'blood_type' => $s('blood_type', 20),
        'civil_status' => $s('civil_status', 50),
        'philhealth_no' => $s('philhealth_no', 120),
        'invite_patient_app' => $tiny('invite_patient_app'),
        'consent_acknowledged' => $tiny('consent_acknowledged'),
    ];
    $data['show_guardian_names'] = (int) $data['show_guardian_names'];
    $data['invite_patient_app'] = (int) $data['invite_patient_app'];
    $data['consent_acknowledged'] = (int) $data['consent_acknowledged'];

    $sql = 'INSERT INTO patient_profile_extra (
        patient_id, nickname, referring_physician, primary_care_physician,
        other_physician_1, other_physician_2, other_physician_3,
        emergency_contact_name, emergency_contact_phone, emergency_relationship,
        address_line, other_mobile, parent_guardian_1, parent_guardian_2, show_guardian_names,
        occupation, employer_name, employer_address, employer_phone, hmo_name, patient_tags,
        nationality, race, religion, blood_type, civil_status, philhealth_no,
        invite_patient_app, consent_acknowledged
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE
        nickname = VALUES(nickname),
        referring_physician = VALUES(referring_physician),
        primary_care_physician = VALUES(primary_care_physician),
        other_physician_1 = VALUES(other_physician_1),
        other_physician_2 = VALUES(other_physician_2),
        other_physician_3 = VALUES(other_physician_3),
        emergency_contact_name = VALUES(emergency_contact_name),
        emergency_contact_phone = VALUES(emergency_contact_phone),
        emergency_relationship = VALUES(emergency_relationship),
        address_line = VALUES(address_line),
        other_mobile = VALUES(other_mobile),
        parent_guardian_1 = VALUES(parent_guardian_1),
        parent_guardian_2 = VALUES(parent_guardian_2),
        show_guardian_names = VALUES(show_guardian_names),
        occupation = VALUES(occupation),
        employer_name = VALUES(employer_name),
        employer_address = VALUES(employer_address),
        employer_phone = VALUES(employer_phone),
        hmo_name = VALUES(hmo_name),
        patient_tags = VALUES(patient_tags),
        nationality = VALUES(nationality),
        race = VALUES(race),
        religion = VALUES(religion),
        blood_type = VALUES(blood_type),
        civil_status = VALUES(civil_status),
        philhealth_no = VALUES(philhealth_no),
        invite_patient_app = VALUES(invite_patient_app),
        consent_acknowledged = VALUES(consent_acknowledged)';

    $st = $conn->prepare($sql);
    if (!$st) {
        return false;
    }

    $types = 'i' . str_repeat('s', 13) . 'i' . str_repeat('s', 12) . 'ii';
    $st->bind_param(
        $types,
        $patient_id,
        $data['nickname'],
        $data['referring_physician'],
        $data['primary_care_physician'],
        $data['other_physician_1'],
        $data['other_physician_2'],
        $data['other_physician_3'],
        $data['emergency_contact_name'],
        $data['emergency_contact_phone'],
        $data['emergency_relationship'],
        $data['address_line'],
        $data['other_mobile'],
        $data['parent_guardian_1'],
        $data['parent_guardian_2'],
        $data['show_guardian_names'],
        $data['occupation'],
        $data['employer_name'],
        $data['employer_address'],
        $data['employer_phone'],
        $data['hmo_name'],
        $data['patient_tags'],
        $data['nationality'],
        $data['race'],
        $data['religion'],
        $data['blood_type'],
        $data['civil_status'],
        $data['philhealth_no'],
        $data['invite_patient_app'],
        $data['consent_acknowledged']
    );

    return $st->execute();
}
