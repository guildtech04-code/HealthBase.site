<?php
/**
 * Extended demographics form fields (ppe_* POST names).
 * @var array $ppe row merged with defaults
 * @var bool $table_exists if patient_profile_extra is available
 * @var string|null $ppe_css_href optional absolute or relative URL to patient_extended_form.css
 * @var bool $ppe_hide_outer_heading when true, omit the main "Additional information" title + hint (e.g. booking embed)
 */
if (!isset($ppe) || !is_array($ppe)) {
    $ppe = [];
}
$ppe_hide_outer_heading = !empty($ppe_hide_outer_heading);
$ppe_css = isset($ppe_css_href) ? (string) $ppe_css_href : 'css/patient_extended_form.css';
$f = static function (string $k) use ($ppe): string {
    return htmlspecialchars((string) ($ppe[$k] ?? ''), ENT_QUOTES, 'UTF-8');
};
$chk = static function (string $k) use ($ppe): string {
    return !empty($ppe[$k]) ? ' checked' : '';
};
$table_ok = !empty($table_exists);
?>
<link rel="stylesheet" href="<?= htmlspecialchars($ppe_css, ENT_QUOTES, 'UTF-8') ?>">

<?php if (!$table_ok): ?>
<div class="ppe-migration-note">
    <strong><i class="fas fa-database"></i> Extended sections unavailable.</strong>
    The system could not create the <code>patient_profile_extra</code> table (your DB user may need <code>CREATE</code> permission), or the manual script
    <code>sql/patient_profile_extra.sql</code> has not been applied. Core profile fields above still save as usual.
</div>
<?php else: ?>

<div class="ppe-sheet">
    <?php if (!$ppe_hide_outer_heading): ?>
    <h2 class="ppe-sheet__title">Additional information</h2>
    <p class="ppe-sheet__hint">Structured intake-style sections: contacts, physicians, employment, HMO, and consent. Fields are optional unless your clinic requires them.</p>
    <?php endif; ?>

    <div class="ppe-section">
        <h3 class="ppe-section__head">Emergency contact</h3>
        <div class="ppe-grid-2">
            <div class="ppe-field">
                <label for="ppe_emergency_contact_name">Name</label>
                <input type="text" name="ppe_emergency_contact_name" id="ppe_emergency_contact_name" value="<?= $f('emergency_contact_name') ?>" placeholder="Full name" autocomplete="off">
            </div>
            <div class="ppe-field">
                <label for="ppe_emergency_contact_phone">Contact number</label>
                <input type="tel" name="ppe_emergency_contact_phone" id="ppe_emergency_contact_phone" value="<?= $f('emergency_contact_phone') ?>" placeholder="+63 or local number" autocomplete="off">
            </div>
        </div>
        <div class="ppe-field">
            <label for="ppe_emergency_relationship">Relationship</label>
            <select name="ppe_emergency_relationship" id="ppe_emergency_relationship">
                <option value="">Select relationship</option>
                <?php
                $rels = ['Spouse', 'Parent', 'Child', 'Sibling', 'Friend', 'Partner', 'Guardian', 'Other'];
                $cur = (string) ($ppe['emergency_relationship'] ?? '');
                foreach ($rels as $rel) {
                    $sel = strcasecmp($cur, $rel) === 0 ? ' selected' : '';
                    echo '<option value="' . htmlspecialchars($rel, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>' . htmlspecialchars($rel) . '</option>';
                }
                ?>
            </select>
        </div>
    </div>

    <div class="ppe-section">
        <h3 class="ppe-section__head">Physician information</h3>
        <div class="ppe-field">
            <label for="ppe_referring_physician">Referring physician</label>
            <input type="text" name="ppe_referring_physician" id="ppe_referring_physician" value="<?= $f('referring_physician') ?>" placeholder="Name or clinic">
        </div>
        <div class="ppe-field">
            <label for="ppe_primary_care_physician">Primary care physician</label>
            <input type="text" name="ppe_primary_care_physician" id="ppe_primary_care_physician" value="<?= $f('primary_care_physician') ?>" placeholder="Name or clinic">
        </div>
        <div class="ppe-field">
            <label>Other physicians</label>
            <input type="text" name="ppe_other_physician_1" value="<?= $f('other_physician_1') ?>" placeholder="Physician 1" style="margin-bottom:8px;">
            <input type="text" name="ppe_other_physician_2" value="<?= $f('other_physician_2') ?>" placeholder="Physician 2" style="margin-bottom:8px;">
            <input type="text" name="ppe_other_physician_3" value="<?= $f('other_physician_3') ?>" placeholder="Physician 3">
        </div>
    </div>

    <div class="ppe-section">
        <h3 class="ppe-section__head">Contact information</h3>
        <div class="ppe-field">
            <label for="ppe_address_line">Address</label>
            <textarea name="ppe_address_line" id="ppe_address_line" rows="2" placeholder="Street, city, region"><?= $f('address_line') ?></textarea>
        </div>
        <div class="ppe-grid-2">
            <div class="ppe-field">
                <label for="ppe_other_mobile">Other mobile</label>
                <input type="tel" name="ppe_other_mobile" id="ppe_other_mobile" value="<?= $f('other_mobile') ?>" placeholder="+63##########">
            </div>
            <div class="ppe-field">
                <label for="ppe_nickname">Nickname</label>
                <input type="text" name="ppe_nickname" id="ppe_nickname" value="<?= $f('nickname') ?>" placeholder="Preferred name">
            </div>
        </div>
        <div class="ppe-grid-2">
            <div class="ppe-field">
                <label for="ppe_parent_guardian_1">Parent / guardian #1</label>
                <input type="text" name="ppe_parent_guardian_1" id="ppe_parent_guardian_1" value="<?= $f('parent_guardian_1') ?>">
            </div>
            <div class="ppe-field">
                <label for="ppe_parent_guardian_2">Parent / guardian #2</label>
                <input type="text" name="ppe_parent_guardian_2" id="ppe_parent_guardian_2" value="<?= $f('parent_guardian_2') ?>">
            </div>
        </div>
        <div class="ppe-toggle-row">
            <label for="ppe_show_guardian_names">Show parents/guardians&rsquo; names on shared documents</label>
            <input type="checkbox" name="ppe_show_guardian_names" id="ppe_show_guardian_names" value="1"<?= $chk('show_guardian_names') ?>>
        </div>
    </div>

    <div class="ppe-section">
        <h3 class="ppe-section__head">Employment information</h3>
        <div class="ppe-field">
            <label for="ppe_occupation">Occupation</label>
            <input type="text" name="ppe_occupation" id="ppe_occupation" value="<?= $f('occupation') ?>">
        </div>
        <div class="ppe-field">
            <label for="ppe_employer_name">Employer&rsquo;s name</label>
            <input type="text" name="ppe_employer_name" id="ppe_employer_name" value="<?= $f('employer_name') ?>">
        </div>
        <div class="ppe-field">
            <label for="ppe_employer_address">Employer&rsquo;s address</label>
            <textarea name="ppe_employer_address" id="ppe_employer_address" rows="2"><?= $f('employer_address') ?></textarea>
        </div>
        <div class="ppe-field">
            <label for="ppe_employer_phone">Employer&rsquo;s phone</label>
            <input type="tel" name="ppe_employer_phone" id="ppe_employer_phone" value="<?= $f('employer_phone') ?>">
        </div>
    </div>

    <div class="ppe-section">
        <h3 class="ppe-section__head">Health Maintenance Organization (HMO)</h3>
        <div class="ppe-field">
            <label for="ppe_hmo_name">HMO / member name</label>
            <input type="text" name="ppe_hmo_name" id="ppe_hmo_name" value="<?= $f('hmo_name') ?>" placeholder="Plan or insurer name">
        </div>
    </div>

    <div class="ppe-section">
        <h3 class="ppe-section__head">Clinical &amp; administrative tags</h3>
        <div class="ppe-grid-2">
            <div class="ppe-field">
                <label for="ppe_patient_tags">Patient tags</label>
                <input type="text" name="ppe_patient_tags" id="ppe_patient_tags" value="<?= $f('patient_tags') ?>" placeholder="e.g. Chronic, VIP">
            </div>
            <div class="ppe-field">
                <label for="ppe_philhealth_no">PhilHealth no.</label>
                <input type="text" name="ppe_philhealth_no" id="ppe_philhealth_no" value="<?= $f('philhealth_no') ?>">
            </div>
        </div>
        <div class="ppe-grid-2">
            <div class="ppe-field">
                <label for="ppe_blood_type">Blood type</label>
                <select name="ppe_blood_type" id="ppe_blood_type">
                    <option value="">Select</option>
                    <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown'] as $bt) {
                        $sel = ($ppe['blood_type'] ?? '') === $bt ? ' selected' : '';
                        echo '<option value="' . htmlspecialchars($bt, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>' . htmlspecialchars($bt) . '</option>';
                    } ?>
                </select>
            </div>
            <div class="ppe-field">
                <label for="ppe_civil_status">Civil status</label>
                <select name="ppe_civil_status" id="ppe_civil_status">
                    <option value="">Select</option>
                    <?php foreach (['Single','Married','Widowed','Separated','Divorced','Unknown'] as $cs) {
                        $sel = ($ppe['civil_status'] ?? '') === $cs ? ' selected' : '';
                        echo '<option value="' . htmlspecialchars($cs, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>' . htmlspecialchars($cs) . '</option>';
                    } ?>
                </select>
            </div>
        </div>
    </div>

    <details class="ppe-details" open>
        <summary><i class="fas fa-eye-slash" aria-hidden="true"></i> Additional demographics</summary>
        <div class="ppe-grid-2" style="margin-top:12px;">
            <div class="ppe-field">
                <label for="ppe_nationality">Nationality</label>
                <input type="text" name="ppe_nationality" id="ppe_nationality" value="<?= $f('nationality') ?>">
            </div>
            <div class="ppe-field">
                <label for="ppe_race">Race</label>
                <input type="text" name="ppe_race" id="ppe_race" value="<?= $f('race') ?>">
            </div>
        </div>
        <div class="ppe-field">
            <label for="ppe_religion">Religion</label>
            <input type="text" name="ppe_religion" id="ppe_religion" value="<?= $f('religion') ?>">
        </div>
    </details>

    <div class="ppe-section">
        <h3 class="ppe-section__head">Notifications &amp; app</h3>
        <div class="ppe-toggle-row">
            <label for="ppe_invite_patient_app">Invite patient to HealthBase app (when available)</label>
            <input type="checkbox" name="ppe_invite_patient_app" id="ppe_invite_patient_app" value="1"<?= $chk('invite_patient_app') ?>>
        </div>
    </div>

    <div class="ppe-section">
        <h3 class="ppe-section__head">Consent</h3>
        <div class="ppe-consent">
            <div class="ppe-consent__left">
                <input type="checkbox" name="ppe_consent_acknowledged" id="ppe_consent_acknowledged" value="1"<?= $chk('consent_acknowledged') ?> aria-labelledby="ppe-consent-lbl">
                <p class="ppe-consent__label" id="ppe-consent-lbl">I acknowledge HealthBase terms of use and privacy practices for this record.</p>
            </div>
            <a class="ppe-consent__link" href="/auth/register.php" target="_blank" rel="noopener">Read terms</a>
        </div>
    </div>
</div>

<?php endif; ?>
