    <?php
    // Patient-specific sidebar — footer shows patient record name (first + last), not login username
    if (!isset($conn) && !empty($_SESSION['user_id'])) {
        $_hb_db_connect = dirname(__DIR__, 2) . '/config/db_connect.php';
        if (is_file($_hb_db_connect)) {
            require_once $_hb_db_connect;
        }
    }

    $session_role = $_SESSION['role'] ?? '';
    $sidebar_display_name = '';
    if (isset($sidebar_user_data) && isset($sidebar_user_data['display_name']) && trim((string) $sidebar_user_data['display_name']) !== '') {
        $sidebar_display_name = trim((string) $sidebar_user_data['display_name']);
    } elseif (isset($conn) && isset($_SESSION['user_id']) && in_array($session_role, ['user', 'patient'], true)) {
        $uid = (int) $_SESSION['user_id'];
        $pn_stmt = $conn->prepare('SELECT TRIM(CONCAT(COALESCE(first_name, ""), " ", COALESCE(last_name, ""))) AS full_name FROM patients WHERE user_id = ? LIMIT 1');
        if ($pn_stmt) {
            $pn_stmt->bind_param('i', $uid);
            if ($pn_stmt->execute()) {
                $pr = $pn_stmt->get_result()->fetch_assoc();
                if ($pr && trim((string) $pr['full_name']) !== '') {
                    $sidebar_display_name = trim((string) $pr['full_name']);
                    $_SESSION['patient_display_name'] = $sidebar_display_name;
                }
            }
            $pn_stmt->close();
        }
    }
    if ($sidebar_display_name === '' && !empty($_SESSION['patient_display_name'])) {
        $sidebar_display_name = trim((string) $_SESSION['patient_display_name']);
    }
    if ($sidebar_display_name === '') {
        if (isset($sidebar_user_data) && isset($sidebar_user_data['username']) && trim((string) $sidebar_user_data['username']) !== '') {
            $sidebar_display_name = trim((string) $sidebar_user_data['username']);
        } else {
            $sidebar_display_name = trim((string) ($_SESSION['username'] ?? 'Patient'));
        }
    }
    $sidebar_display_name_esc = htmlspecialchars($sidebar_display_name, ENT_QUOTES, 'UTF-8');
    $parts = preg_split('/\s+/', $sidebar_display_name, -1, PREG_SPLIT_NO_EMPTY);
    if (count($parts) >= 2) {
        $sidebar_avatar_initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts) - 1], 0, 1));
    } else {
        $sidebar_avatar_initials = strtoupper(substr($sidebar_display_name, 0, 1));
    }
    if ($sidebar_avatar_initials === '') {
        $sidebar_avatar_initials = '?';
    }

    $sidebar_profile_photo_url = null;
    $sidebar_profile_modal_sections = [];
    $sidebar_profile_modal_ready = false;

    if (isset($conn) && isset($_SESSION['user_id'])) {
        require_once __DIR__ . '/../../includes/patient_profile_extra.php';
        require_once __DIR__ . '/../../includes/patient_profile_photo.php';
        hb_ensure_patient_profile_extra_table($conn);
        hb_ensure_user_profile_photo_column($conn);
        $has_user_profile_photo_col = hb_user_profile_photo_column_exists($conn);

        $hb_spv = static function ($v): string {
            $t = trim((string) $v);
            if ($t === '') {
                return '<span class="patient-profile-modal__empty">—</span>';
            }

            return htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
        };
        $hb_spv_ml = static function ($v) use ($hb_spv): string {
            $t = trim((string) $v);
            if ($t === '') {
                return '<span class="patient-profile-modal__empty">—</span>';
            }

            return nl2br(htmlspecialchars($t, ENT_QUOTES, 'UTF-8'));
        };
        $hb_yesno = static function ($v): string {
            return !empty($v) ? 'Yes' : 'No';
        };

        $uid_modal = (int) $_SESSION['user_id'];
        $u_row = null;
        $u_sql = $has_user_profile_photo_col
            ? 'SELECT email, username, profile_photo FROM users WHERE id = ? LIMIT 1'
            : 'SELECT email, username FROM users WHERE id = ? LIMIT 1';
        $u_stmt = $conn->prepare($u_sql);
        if ($u_stmt) {
            $u_stmt->bind_param('i', $uid_modal);
            if ($u_stmt->execute()) {
                $u_row = $u_stmt->get_result()->fetch_assoc();
            }
            $u_stmt->close();
        }
        if ($u_row && $has_user_profile_photo_col) {
            $sidebar_profile_photo_url = hb_user_profile_photo_public_url($u_row['profile_photo'] ?? null);
        }

        $col_dob_sidebar = $conn->query("
            SELECT COUNT(*) AS c FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'patients' AND COLUMN_NAME = 'date_of_birth'
        ");
        $has_patient_dob_sidebar = $col_dob_sidebar && (int) ($col_dob_sidebar->fetch_assoc()['c'] ?? 0) > 0;

        $p_row = null;
        if ($has_patient_dob_sidebar) {
            $p_stmt = $conn->prepare('SELECT id, first_name, last_name, age, date_of_birth, gender, health_concern FROM patients WHERE user_id = ? LIMIT 1');
        } else {
            $p_stmt = $conn->prepare('SELECT id, first_name, last_name, age, gender, health_concern FROM patients WHERE user_id = ? LIMIT 1');
        }
        if ($p_stmt) {
            $p_stmt->bind_param('i', $uid_modal);
            if ($p_stmt->execute()) {
                $p_row = $p_stmt->get_result()->fetch_assoc();
            }
            $p_stmt->close();
        }

        $ppe_row = null;
        if ($p_row && hb_patient_profile_extra_table_exists($conn)) {
            $ppe_row = hb_get_patient_profile_extra($conn, (int) $p_row['id']);
        }

        $acct_rows = [];
        if ($u_row) {
            if ($has_user_profile_photo_col) {
                if ($sidebar_profile_photo_url) {
                    $acct_rows[] = [
                        'label' => 'Profile photo',
                        'value' => '<span class="patient-profile-modal__photo-wrap"><img src="' . htmlspecialchars($sidebar_profile_photo_url, ENT_QUOTES, 'UTF-8') . '" width="80" height="80" alt=""></span>',
                    ];
                } else {
                    $acct_rows[] = ['label' => 'Profile photo', 'value' => $hb_spv('')];
                }
            }
            $acct_rows[] = ['label' => 'Email', 'value' => $hb_spv($u_row['email'] ?? '')];
            $acct_rows[] = ['label' => 'Username', 'value' => $hb_spv($u_row['username'] ?? '')];
        }
        if ($acct_rows !== []) {
            $sidebar_profile_modal_sections[] = ['title' => 'Account', 'rows' => $acct_rows];
        }

        if ($p_row) {
            $pr = [];
            $pr[] = ['label' => 'First name', 'value' => $hb_spv($p_row['first_name'] ?? '')];
            $pr[] = ['label' => 'Last name', 'value' => $hb_spv($p_row['last_name'] ?? '')];
            $pr[] = ['label' => 'Age', 'value' => $hb_spv(isset($p_row['age']) && $p_row['age'] !== null && $p_row['age'] !== '' ? (string) $p_row['age'] : '')];
            if ($has_patient_dob_sidebar) {
                $dob_raw = $p_row['date_of_birth'] ?? '';
                $dob_disp = '';
                if ($dob_raw !== null && trim((string) $dob_raw) !== '') {
                    $ts = strtotime((string) $dob_raw);
                    $dob_disp = $ts ? date('M j, Y', $ts) : trim((string) $dob_raw);
                }
                $pr[] = ['label' => 'Date of birth', 'value' => $hb_spv($dob_disp)];
            }
            $pr[] = ['label' => 'Gender', 'value' => $hb_spv($p_row['gender'] ?? '')];
            $pr[] = ['label' => 'Reason for visit / health concern', 'value' => $hb_spv_ml($p_row['health_concern'] ?? '')];
            $sidebar_profile_modal_sections[] = ['title' => 'Patient record', 'rows' => $pr];
        } else {
            $sidebar_profile_modal_sections[] = [
                'title' => 'Patient record',
                'rows' => [['label' => 'Status', 'value' => '<span class="patient-profile-modal__hint">No patient record found. Complete setup from Edit Profile if prompted.</span>']],
            ];
        }

        if ($ppe_row) {
            $sidebar_profile_modal_sections[] = [
                'title' => 'Emergency contact',
                'rows' => [
                    ['label' => 'Name', 'value' => $hb_spv($ppe_row['emergency_contact_name'] ?? '')],
                    ['label' => 'Contact number', 'value' => $hb_spv($ppe_row['emergency_contact_phone'] ?? '')],
                    ['label' => 'Relationship', 'value' => $hb_spv($ppe_row['emergency_relationship'] ?? '')],
                ],
            ];
            $sidebar_profile_modal_sections[] = [
                'title' => 'Physician information',
                'rows' => [
                    ['label' => 'Referring physician', 'value' => $hb_spv($ppe_row['referring_physician'] ?? '')],
                    ['label' => 'Primary care physician', 'value' => $hb_spv($ppe_row['primary_care_physician'] ?? '')],
                    ['label' => 'Other physician 1', 'value' => $hb_spv($ppe_row['other_physician_1'] ?? '')],
                    ['label' => 'Other physician 2', 'value' => $hb_spv($ppe_row['other_physician_2'] ?? '')],
                    ['label' => 'Other physician 3', 'value' => $hb_spv($ppe_row['other_physician_3'] ?? '')],
                ],
            ];
            $sidebar_profile_modal_sections[] = [
                'title' => 'Contact information',
                'rows' => [
                    ['label' => 'Address', 'value' => $hb_spv_ml($ppe_row['address_line'] ?? '')],
                    ['label' => 'Other mobile', 'value' => $hb_spv($ppe_row['other_mobile'] ?? '')],
                    ['label' => 'Nickname', 'value' => $hb_spv($ppe_row['nickname'] ?? '')],
                ],
            ];
            $sidebar_profile_modal_sections[] = [
                'title' => 'Parents / guardians',
                'rows' => [
                    ['label' => 'Parent or guardian 1', 'value' => $hb_spv($ppe_row['parent_guardian_1'] ?? '')],
                    ['label' => 'Parent or guardian 2', 'value' => $hb_spv($ppe_row['parent_guardian_2'] ?? '')],
                    ['label' => 'Show guardian names on records', 'value' => $hb_yesno($ppe_row['show_guardian_names'] ?? 0)],
                ],
            ];
            $sidebar_profile_modal_sections[] = [
                'title' => 'Employment',
                'rows' => [
                    ['label' => 'Occupation', 'value' => $hb_spv($ppe_row['occupation'] ?? '')],
                    ['label' => 'Employer name', 'value' => $hb_spv($ppe_row['employer_name'] ?? '')],
                    ['label' => 'Employer address', 'value' => $hb_spv_ml($ppe_row['employer_address'] ?? '')],
                    ['label' => 'Employer phone', 'value' => $hb_spv($ppe_row['employer_phone'] ?? '')],
                ],
            ];
            $sidebar_profile_modal_sections[] = [
                'title' => 'Insurance & identifiers',
                'rows' => [
                    ['label' => 'HMO name', 'value' => $hb_spv($ppe_row['hmo_name'] ?? '')],
                    ['label' => 'PhilHealth number', 'value' => $hb_spv($ppe_row['philhealth_no'] ?? '')],
                    ['label' => 'Patient tags', 'value' => $hb_spv($ppe_row['patient_tags'] ?? '')],
                ],
            ];
            $sidebar_profile_modal_sections[] = [
                'title' => 'Demographics',
                'rows' => [
                    ['label' => 'Nationality', 'value' => $hb_spv($ppe_row['nationality'] ?? '')],
                    ['label' => 'Race', 'value' => $hb_spv($ppe_row['race'] ?? '')],
                    ['label' => 'Religion', 'value' => $hb_spv($ppe_row['religion'] ?? '')],
                    ['label' => 'Blood type', 'value' => $hb_spv($ppe_row['blood_type'] ?? '')],
                    ['label' => 'Civil status', 'value' => $hb_spv($ppe_row['civil_status'] ?? '')],
                ],
            ];
            $sidebar_profile_modal_sections[] = [
                'title' => 'Preferences',
                'rows' => [
                    ['label' => 'Invite to patient app', 'value' => $hb_yesno($ppe_row['invite_patient_app'] ?? 0)],
                    ['label' => 'Consent acknowledged', 'value' => $hb_yesno($ppe_row['consent_acknowledged'] ?? 0)],
                ],
            ];
        } elseif ($p_row && !hb_patient_profile_extra_table_exists($conn)) {
            $sidebar_profile_modal_sections[] = [
                'title' => 'Additional information',
                'rows' => [['label' => 'Extended profile', 'value' => '<span class="patient-profile-modal__hint">Extended fields are unavailable until the patient_profile_extra table exists (see sql/patient_profile_extra.sql).</span>']],
            ];
        }

        if ($sidebar_profile_modal_sections === []) {
            $sidebar_profile_modal_sections[] = [
                'title' => 'Profile',
                'rows' => [['label' => 'Status', 'value' => '<span class="patient-profile-modal__hint">Profile details could not be loaded.</span>']],
            ];
        }

        $sidebar_profile_modal_ready = true;
    } elseif (isset($_SESSION['user_id'])) {
        $sidebar_profile_modal_sections = [
            [
                'title' => 'Your information',
                'rows' => [
                    ['label' => 'Name', 'value' => htmlspecialchars($sidebar_display_name, ENT_QUOTES, 'UTF-8')],
                    ['label' => 'Username', 'value' => htmlspecialchars((string) ($_SESSION['username'] ?? ''), ENT_QUOTES, 'UTF-8')],
                ],
            ],
            [
                'title' => 'Notice',
                'rows' => [
                    ['label' => 'Details', 'value' => '<span class="patient-profile-modal__hint">Full profile details could not be loaded from the database. Try again later or use Edit Profile.</span>'],
                ],
            ],
        ];
        $sidebar_profile_modal_ready = true;
    }
    ?>

    <div id="patientSidebar" class="patient-sidebar">
        <div class="sidebar-header">
            <div class="sidebar-brand">
                <img src="/assets/images/Logo.png" alt="HealthBase" class="sidebar-logo">
                <span class="brand-text">HealthBase</span>
            </div>
            <button class="sidebar-toggle" id="patientSidebarToggle" title="Toggle Sidebar">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <div class="sidebar-pin-toggle">
            <button id="patientPinToggle" class="pin-btn" title="Pin/Unpin Sidebar">
                <i class="fas fa-thumbtack"></i>
            </button>
        </div>
        
        <nav class="sidebar-nav">
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="/patient/patient_dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'patient_dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/patient/patient_appointments.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'patient_appointments.php' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-check"></i>
                        <span class="nav-text">My Appointments</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/patient/patient_appointment_calendar.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'patient_appointment_calendar.php' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-days"></i>
                        <span class="nav-text">Calendar · Appointment Reminders</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/patient/medical_records.php" class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['medical_records.php', 'patient_history.php'], true) ? 'active' : ''; ?>">
                        <i class="fas fa-file-medical"></i>
                        <span class="nav-text">Medical Records</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/appointments/scheduling.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'scheduling.php' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-plus"></i>
                        <span class="nav-text">Book Appointment</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/patient/edit_profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'edit_profile.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user-edit"></i>
                        <span class="nav-text">Edit Profile</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/patient/patient_tickets.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'patient_tickets.php' ? 'active' : ''; ?>">
                        <i class="fas fa-ticket-alt"></i>
                        <span class="nav-text">My Tickets</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/patient/about_us.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'about_us.php' ? 'active' : ''; ?>">
                        <i class="fas fa-info-circle"></i>
                        <span class="nav-text">About Us</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <div class="sidebar-footer">
            <?php if (!empty($sidebar_profile_modal_ready)): ?>
        <button type="button" class="user-info patient-profile-trigger" id="patientProfileTrigger" aria-haspopup="dialog" aria-controls="patientProfileModal" title="View your profile information">
            <span class="user-avatar<?php echo $sidebar_profile_photo_url ? ' user-avatar--photo' : ''; ?>" aria-hidden="true">
                <?php if ($sidebar_profile_photo_url): ?>
                    <img src="<?php echo htmlspecialchars($sidebar_profile_photo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="40" height="40" loading="lazy" decoding="async" draggable="false">
                <?php else: ?>
                    <?php echo htmlspecialchars($sidebar_avatar_initials, ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
            </span>
                <span class="user-details">
                    <span class="user-name"><?php echo $sidebar_display_name_esc; ?></span>
                    <span class="user-role">Patient</span>
                </span>
            </button>
            <?php else: ?>
        <div class="user-info">
            <div class="user-avatar<?php echo !empty($sidebar_profile_photo_url) ? ' user-avatar--photo' : ''; ?>">
                <?php if (!empty($sidebar_profile_photo_url)): ?>
                    <img src="<?php echo htmlspecialchars($sidebar_profile_photo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="40" height="40" loading="lazy" decoding="async" draggable="false">
                <?php else: ?>
                    <?php echo htmlspecialchars($sidebar_avatar_initials, ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
            </div>
                <div class="user-details">
                    <span class="user-name"><?php echo $sidebar_display_name_esc; ?></span>
                    <span class="user-role">Patient</span>
                </div>
            </div>
            <?php endif; ?>
            <a href="/auth/logout.php" class="logout-btn" id="logoutBtn" onclick="return handleLogout(event);">
                <i class="fas fa-sign-out-alt"></i>
                <span class="nav-text">Logout</span>
            </a>
        </div>
    </div>

    <script src="/patient/js/patient_sidebar.js"></script>
    <script>
    // Ensure main content margin is updated immediately
    (function() {
        const sidebar = document.getElementById('patientSidebar');
        const mainContent = document.querySelector('.patient-main-content');
        
        if (sidebar && mainContent) {
            function updateContentMargin() {
                const isPinned = sidebar.classList.contains('pinned');
                const isCollapsed = sidebar.classList.contains('collapsed');
                
                if (isPinned) {
                    mainContent.style.marginLeft = '280px';
                } else if (isCollapsed) {
                    mainContent.style.marginLeft = '70px';
                } else {
                    mainContent.style.marginLeft = '280px';
                }
            }
            
            // Update on load
            setTimeout(updateContentMargin, 50);
            
            // Also listen for class changes
            const observer = new MutationObserver(updateContentMargin);
            observer.observe(sidebar, { 
                attributes: true, 
                attributeFilter: ['class'] 
            });
        }
    })();
    </script>

    <style>
    /* Logout Modal Styles */
    .logout-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
        z-index: 10000;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .logout-modal.active {
        display: flex;
        opacity: 1;
    }

    .logout-modal-content {
        background: white;
        border-radius: 16px;
        padding: 0;
        max-width: 400px;
        width: 90%;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        transform: scale(0.9);
        transition: transform 0.3s ease;
        overflow: hidden;
    }

    .logout-modal.active .logout-modal-content {
        transform: scale(1);
    }

    .logout-modal-title {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
        padding: 20px 25px;
        font-size: 18px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .logout-modal-message {
        padding: 25px;
        color: #1e293b;
    }

    .logout-modal-message p {
        margin: 0 0 8px 0;
        font-size: 15px;
        line-height: 1.6;
    }

    .logout-modal-actions {
        display: flex;
        gap: 10px;
        padding: 20px 25px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
    }

    .logout-modal-btn {
        flex: 1;
        padding: 12px 20px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .logout-modal-btn-cancel {
        background: white;
        color: #64748b;
        border: 2px solid #e2e8f0;
    }

    .logout-modal-btn-cancel:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: #475569;
    }

    .logout-modal-btn-confirm {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
    }

    .logout-modal-btn-confirm:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
    }

    /* Sidebar: profile trigger (button reset) */
    .patient-sidebar .patient-profile-trigger {
        cursor: pointer;
    }
    .patient-sidebar button.user-info {
        display: flex;
        align-items: center;
        gap: 12px;
        width: 100%;
        border: none;
        background: transparent;
        cursor: pointer;
        font: inherit;
        text-align: left;
        padding: 0;
        margin-bottom: 15px;
        border-radius: 8px;
    }
    .patient-sidebar button.user-info:hover .user-avatar:not(.user-avatar--photo),
    .patient-sidebar button.user-info:focus-visible .user-avatar:not(.user-avatar--photo) {
        background: rgba(255, 255, 255, 0.35);
        outline: 2px solid rgba(255, 255, 255, 0.5);
        outline-offset: 2px;
    }
    .patient-sidebar .user-avatar--photo {
        padding: 0;
        overflow: hidden;
    }
    .patient-sidebar .user-avatar--photo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .patient-sidebar button.user-info:hover .user-avatar--photo,
    .patient-sidebar button.user-info:focus-visible .user-avatar--photo {
        outline: 2px solid rgba(255, 255, 255, 0.55);
        outline-offset: 2px;
    }

    /* Profile details modal — patient information sheet */
    .patient-profile-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 10001;
        align-items: center;
        justify-content: center;
        padding: 20px 16px;
        box-sizing: border-box;
        opacity: 0;
        transition: opacity 0.28s ease;
    }
    .patient-profile-modal.active {
        display: flex;
        opacity: 1;
    }
    .patient-profile-modal__backdrop {
        position: absolute;
        inset: 0;
        background: radial-gradient(ellipse 120% 80% at 50% 0%, rgba(14, 165, 233, 0.12) 0%, transparent 55%),
            rgba(15, 23, 42, 0.62);
        backdrop-filter: blur(6px);
    }
    .patient-profile-modal__dialog {
        position: relative;
        background: #f1f5f9;
        border-radius: 20px;
        max-width: 480px;
        width: 100%;
        max-height: min(88vh, 760px);
        display: flex;
        flex-direction: column;
        box-shadow:
            0 0 0 1px rgba(255, 255, 255, 0.12) inset,
            0 25px 50px -12px rgba(15, 23, 42, 0.35);
        transform: scale(0.94) translateY(8px);
        transition: transform 0.28s cubic-bezier(0.34, 1.2, 0.64, 1), opacity 0.28s ease;
        overflow: hidden;
    }
    .patient-profile-modal.active .patient-profile-modal__dialog {
        transform: scale(1) translateY(0);
    }
    .patient-profile-modal__head {
        flex-shrink: 0;
        background: linear-gradient(145deg, #0284c7 0%, #0ea5e9 48%, #38bdf8 100%);
        color: #fff;
        padding: 20px 20px 18px;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px;
    }
    .patient-profile-modal__head-main {
        display: flex;
        align-items: center;
        gap: 14px;
        min-width: 0;
    }
    .patient-profile-modal__head-icon {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.22);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
    }
    .patient-profile-modal__head-text h3 {
        margin: 0 0 4px 0;
        font-size: 1.15rem;
        font-weight: 700;
        letter-spacing: -0.02em;
        font-family: 'Poppins', 'Inter', sans-serif;
    }
    .patient-profile-modal__head-text p {
        margin: 0;
        font-size: 13px;
        opacity: 0.92;
        font-weight: 400;
        line-height: 1.35;
    }
    .patient-profile-modal__close {
        border: none;
        background: rgba(255, 255, 255, 0.18);
        color: #fff;
        width: 40px;
        height: 40px;
        border-radius: 12px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        line-height: 1;
        flex-shrink: 0;
        transition: background 0.2s ease, transform 0.15s ease;
    }
    .patient-profile-modal__close:hover {
        background: rgba(255, 255, 255, 0.3);
    }
    .patient-profile-modal__close:active {
        transform: scale(0.96);
    }
    .patient-profile-modal__hero {
        flex-shrink: 0;
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 18px 20px;
        background: #fff;
        border-bottom: 1px solid #e2e8f0;
    }
    .patient-profile-modal__hero-avatar {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: linear-gradient(145deg, #e0f2fe, #bae6fd);
        color: #0369a1;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.35rem;
        flex-shrink: 0;
        overflow: hidden;
        border: 3px solid #fff;
        box-shadow: 0 4px 16px rgba(14, 165, 233, 0.25);
    }
    .patient-profile-modal__hero-avatar--photo {
        padding: 0;
        background: #e0f2fe;
    }
    .patient-profile-modal__hero-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .patient-profile-modal__hero-text {
        min-width: 0;
    }
    .patient-profile-modal__hero-name {
        display: block;
        font-size: 1.05rem;
        font-weight: 700;
        color: #0f172a;
        letter-spacing: -0.02em;
        font-family: 'Poppins', 'Inter', sans-serif;
    }
    .patient-profile-modal__hero-badge {
        display: inline-block;
        margin-top: 8px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        background: linear-gradient(135deg, #e0f2fe, #f0f9ff);
        color: #0369a1;
        border: 1px solid #bae6fd;
    }
    .patient-profile-modal__body {
        padding: 14px 16px 18px;
        overflow-y: auto;
        flex: 1;
        color: #1e293b;
        font-size: 14px;
        line-height: 1.5;
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 #f1f5f9;
    }
    .patient-profile-modal__body::-webkit-scrollbar {
        width: 8px;
    }
    .patient-profile-modal__body::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 8px;
    }
    .patient-profile-modal__card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 0;
        margin-bottom: 12px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
        overflow: hidden;
    }
    .patient-profile-modal__card:last-child {
        margin-bottom: 0;
    }
    .patient-profile-modal__section-title {
        margin: 0;
        padding: 12px 16px 10px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #64748b;
        background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        border-bottom: 1px solid #e2e8f0;
    }
    .patient-profile-modal__row {
        display: grid;
        grid-template-columns: minmax(112px, 36%) 1fr;
        gap: 10px 14px;
        padding: 11px 16px;
        border-bottom: 1px solid #f1f5f9;
        align-items: start;
    }
    .patient-profile-modal__row:last-child {
        border-bottom: none;
    }
    .patient-profile-modal__label {
        color: #64748b;
        font-weight: 600;
        font-size: 12px;
    }
    .patient-profile-modal__value {
        color: #0f172a;
        word-break: break-word;
        font-size: 14px;
    }
    .patient-profile-modal__photo-wrap {
        display: inline-block;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        vertical-align: middle;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
    }
    .patient-profile-modal__photo-wrap img {
        display: block;
        width: 80px;
        height: 80px;
        object-fit: cover;
    }
    .patient-profile-modal__empty {
        color: #94a3b8;
    }
    .patient-profile-modal__hint {
        color: #64748b;
        font-size: 13px;
        line-height: 1.45;
    }
    .patient-profile-modal__foot {
        flex-shrink: 0;
        padding: 16px 20px;
        background: #fff;
        border-top: 1px solid #e2e8f0;
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        align-items: center;
        gap: 10px;
    }
    .patient-profile-modal__btn-ghost {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        border: 2px solid #e2e8f0;
        background: #fff;
        color: #475569;
        cursor: pointer;
        transition: border-color 0.2s, background 0.2s;
    }
    .patient-profile-modal__btn-ghost:hover {
        border-color: #cbd5e1;
        background: #f8fafc;
        color: #334155;
    }
    .patient-profile-modal__btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        border: none;
        cursor: pointer;
        background: linear-gradient(135deg, #0ea5e9, #0284c7);
        color: #fff;
        box-shadow: 0 4px 14px rgba(14, 165, 233, 0.35);
        transition: filter 0.2s, transform 0.15s;
    }
    .patient-profile-modal__btn:hover {
        filter: brightness(1.06);
    }
    .patient-profile-modal__btn:active {
        transform: translateY(1px);
    }
    @media (max-width: 480px) {
        .patient-profile-modal__row {
            grid-template-columns: 1fr;
            gap: 4px;
        }
        .patient-profile-modal__head {
            padding: 16px 16px 14px;
        }
        .patient-profile-modal__hero {
            padding: 14px 16px;
        }
    }
    </style>

    <script>
    function handleLogout(event) {
        event.preventDefault();
        document.getElementById('logoutModal').classList.add('active');
        return false;
    }

    function closeLogoutModal() {
        document.getElementById('logoutModal').classList.remove('active');
    }

    function confirmLogout() {
        window.location.href = '/auth/logout.php';
    }
    </script>

    <?php if (!empty($sidebar_profile_modal_ready)): ?>
    <!-- Patient profile details (opened from sidebar avatar / name) -->
    <div id="patientProfileModal" class="patient-profile-modal" role="dialog" aria-modal="true" aria-labelledby="patientProfileModalTitle" aria-hidden="true">
        <div class="patient-profile-modal__backdrop" data-close-profile-modal tabindex="-1"></div>
        <div class="patient-profile-modal__dialog">
            <div class="patient-profile-modal__head">
                <div class="patient-profile-modal__head-main">
                    <div class="patient-profile-modal__head-icon" aria-hidden="true">
                        <i class="fas fa-heart-pulse"></i>
                    </div>
                    <div class="patient-profile-modal__head-text">
                        <h3 id="patientProfileModalTitle">Patient information</h3>
                        <p>Your HealthBase profile and clinical details</p>
                    </div>
                </div>
                <button type="button" class="patient-profile-modal__close" id="patientProfileModalClose" data-close-profile-modal aria-label="Close">&times;</button>
            </div>
            <div class="patient-profile-modal__hero">
                <div class="patient-profile-modal__hero-avatar<?php echo $sidebar_profile_photo_url ? ' patient-profile-modal__hero-avatar--photo' : ''; ?>">
                    <?php if ($sidebar_profile_photo_url): ?>
                        <img src="<?php echo htmlspecialchars($sidebar_profile_photo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="64" height="64" loading="lazy" decoding="async" draggable="false">
                    <?php else: ?>
                        <?php echo htmlspecialchars($sidebar_avatar_initials, ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                </div>
                <div class="patient-profile-modal__hero-text">
                    <span class="patient-profile-modal__hero-name"><?php echo $sidebar_display_name_esc; ?></span>
                    <span class="patient-profile-modal__hero-badge">Patient</span>
                </div>
            </div>
            <div class="patient-profile-modal__body">
                <?php foreach ($sidebar_profile_modal_sections as $sec): ?>
                <section class="patient-profile-modal__card">
                    <h4 class="patient-profile-modal__section-title"><?php echo htmlspecialchars($sec['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                    <?php foreach ($sec['rows'] as $row): ?>
                    <div class="patient-profile-modal__row">
                        <span class="patient-profile-modal__label"><?php echo htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="patient-profile-modal__value"><?php echo $row['value']; ?></span>
                    </div>
                    <?php endforeach; ?>
                </section>
                <?php endforeach; ?>
            </div>
            <div class="patient-profile-modal__foot">
                <button type="button" class="patient-profile-modal__btn-ghost" data-close-profile-modal><i class="fas fa-times"></i> Close</button>
                <a href="/patient/edit_profile.php" class="patient-profile-modal__btn"><i class="fas fa-user-edit"></i> Edit profile</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Logout Confirmation Modal -->
    <div id="logoutModal" class="logout-modal">
        <div class="logout-modal-content">
            <div class="logout-modal-title">
                <i class="fas fa-exclamation-triangle"></i>
                Confirm Logout
            </div>
            <div class="logout-modal-message">
                <p>Are you sure you want to logout?</p>
                <p style="color: #64748b; font-size: 14px;">This will end your current session.</p>
            </div>
            <div class="logout-modal-actions">
                <button class="logout-modal-btn logout-modal-btn-cancel" onclick="closeLogoutModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button class="logout-modal-btn logout-modal-btn-confirm" onclick="confirmLogout()">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>
        </div>
    </div>
