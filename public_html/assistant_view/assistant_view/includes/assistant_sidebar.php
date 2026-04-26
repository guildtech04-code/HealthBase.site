<?php
// Assistant-specific sidebar component
if (isset($sidebar_user_data)) {
    $username = $sidebar_user_data['username'];
    $email = $sidebar_user_data['email'];
    $role = $sidebar_user_data['role'];
} else {
    // Fallback to session data
    $username = $_SESSION['username'] ?? 'Assistant';
    $email = $_SESSION['email'] ?? 'assistant@healthbase.com';
    $role = $_SESSION['role'] ?? 'assistant';
}
?>

<div id="assistantSidebar" class="assistant-sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <img src="/assets/images/Logo.png" alt="HealthBase" class="sidebar-logo">
            <span class="brand-text">HealthBase</span>
        </div>
        <button class="sidebar-toggle" id="assistantSidebarToggle" title="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    
    <div class="sidebar-pin-toggle">
        <button id="assistantPinToggle" class="pin-btn" title="Pin/Unpin Sidebar">
            <i class="fas fa-thumbtack"></i>
        </button>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav-menu" id="assistant-nav-menu">
            <li class="nav-item">
                <a href="/assistant_view/index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/assistant_view/assistant_appointments.php" class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['assistant_appointments.php', 'appointments_management.php']) ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-check"></i>
                    <span class="nav-text">Manage Appointments</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/assistant_view/patient_management.php" class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['patient_management.php', 'patient_history.php'], true) ? 'active' : ''; ?>">
                    <i class="fas fa-user-injured"></i>
                    <span class="nav-text">Patient Management</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/appointments/ehr_module.php" class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['ehr_module.php', 'ehr_records.php'], true) ? 'active' : ''; ?>">
                    <i class="fas fa-file-medical"></i>
                    <span class="nav-text">EHR Records</span>
                </a>
            </li>
            <li class="nav-item" id="doctor-availability-menu">
                <a href="/assistant_view/doctor_availability.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'doctor_availability.php' ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i>
                    <span class="nav-text">Doctor Availability</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/appointments/appointment_reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'appointment_reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i>
                    <span class="nav-text">Reports & Analytics</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/assistant_view/ml_dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'ml_dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-brain"></i>
                    <span class="nav-text">ML Risk Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/assistant_view/system_settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'system_settings.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    <span class="nav-text">System Settings</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="sidebar-footer">
        <div class="footer-nav">
            <a href="/assistant_view/user_management.php" class="footer-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'user_management.php' ? 'active' : ''; ?>">
                <i class="fas fa-users-cog"></i>
                <span class="nav-text">User Management</span>
            </a>
            <a href="/assistant_view/audit_logs.php" class="footer-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'audit_logs.php' ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-list"></i>
                <span class="nav-text">Audit Logs</span>
            </a>
        </div>
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($username, 0, 1)); ?>
            </div>
            <div class="user-details">
                <span class="user-name"><?php echo $username; ?></span>
                <span class="user-role">Assistant</span>
            </div>
        </div>
        <a href="/auth/logout.php" class="logout-btn" id="logoutBtn" onclick="return handleLogout(event);">
            <i class="fas fa-sign-out-alt"></i>
            <span class="nav-text">Logout</span>
        </a>
    </div>
</div>

<?php
// Determine the correct path based on where this file is being included from
if (!isset($js_path)) {
    $current_dir = basename(dirname($_SERVER['PHP_SELF']));
    if ($current_dir == 'support') {
        $js_path = '../assistant_view/js/assistant_sidebar.js';
    } elseif ($current_dir == 'appointments') {
        $js_path = '../assistant_view/js/assistant_sidebar.js';
    } else {
        $js_path = 'js/assistant_sidebar.js';
    }
}
?>
<script src="<?php echo $js_path; ?>"></script>

<style>
/* Logout Confirmation Modal */
#logoutModal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}

#logoutModal.active {
    display: flex;
}

.logout-modal-content {
    background: #1e293b;
    border-radius: 16px;
    padding: 30px;
    min-width: 400px;
    max-width: 500px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.logout-modal-title {
    font-size: 18px;
    font-weight: 600;
    color: #ffffff;
    margin-bottom: 15px;
}

.logout-modal-message {
    font-size: 14px;
    color: #cbd5e1;
    margin-bottom: 20px;
    line-height: 1.6;
}

.logout-modal-message p {
    margin: 0 0 8px 0;
}

.logout-modal-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

.logout-modal-btn {
    padding: 10px 20px;
    border-radius: 8px;
    border: none;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.logout-modal-btn-cancel {
    background: #334155;
    color: white;
}

.logout-modal-btn-cancel:hover {
    background: #475569;
}

.logout-modal-btn-confirm {
    background: #8b5cf6;
    color: white;
}

.logout-modal-btn-confirm:hover {
    background: #7c3aed;
}
</style>

<script>
// Enhanced logout confirmation with custom modal
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

// Close modal when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    var logoutModal = document.getElementById('logoutModal');
    if (logoutModal) {
        logoutModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeLogoutModal();
            }
        });
    }
});

// Ensure main content margin is updated immediately
(function() {
    const sidebar = document.getElementById('assistantSidebar');
    const mainContent = document.querySelector('.assistant-main-content');
    
    if (sidebar && mainContent) {
        function updateContentMargin() {
            const isPinned = sidebar.classList.contains('pinned');
            const isCollapsed = sidebar.classList.contains('collapsed');
            const isHoverExpanded = sidebar.classList.contains('hover-expanded');
            
            if (isPinned) {
                // When pinned, always show full sidebar width
                mainContent.style.marginLeft = '280px';
            } else if (isCollapsed && !isHoverExpanded) {
                // When collapsed and not hovered, show minimal margin
                mainContent.style.marginLeft = '70px';
            } else {
                // When expanded (hover or normal), show full margin
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
        
        // Also update on mouse events for hover
        sidebar.addEventListener('mouseenter', updateContentMargin);
        sidebar.addEventListener('mouseleave', updateContentMargin);
    }
    
    // Debug: Check if Doctor Availability menu is present
    const docAvailMenu = document.getElementById('doctor-availability-menu');
    console.log('Doctor Availability menu element:', docAvailMenu);
    if (!docAvailMenu) {
        console.error('Doctor Availability menu item is missing from DOM!');
    }
})();
</script>

<div id="logoutModal" class="logout-modal">
    <div class="logout-modal-content">
        <div class="logout-modal-title">Confirm Logout</div>
        <div class="logout-modal-message">
            <p>Are you sure you want to logout?</p>
            <p style="color: #94a3b8;">This will end your current session.</p>
        </div>
        <div class="logout-modal-actions">
            <button class="logout-modal-btn logout-modal-btn-cancel" onclick="closeLogoutModal()">Cancel</button>
            <button class="logout-modal-btn logout-modal-btn-confirm" onclick="confirmLogout()">Logout</button>
        </div>
    </div>
</div>

