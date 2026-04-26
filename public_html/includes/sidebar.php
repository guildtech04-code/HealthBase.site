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
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="/assistant_view/index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/assistant_view/user_management.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'user_management.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users-cog"></i>
                    <span class="nav-text">User Management</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/assistant_view/audit_logs.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'audit_logs.php' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list"></i>
                    <span class="nav-text">Audit Logs</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/assistant_view/appointments_management.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'appointments_management.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-check"></i>
                    <span class="nav-text">Appointments</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/assistant_view/system_settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'system_settings.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    <span class="nav-text">System Settings</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/support/support.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'support.php' ? 'active' : ''; ?>">
                    <i class="fas fa-headset"></i>
                    <span class="nav-text">Support Tickets</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($username, 0, 1)); ?>
            </div>
            <div class="user-details">
                <span class="user-name"><?php echo $username; ?></span>
                <span class="user-role">Assistant</span>
            </div>
        </div>
        <a href="/auth/logout.php" class="logout-btn" onclick="return confirm('Are you sure you want to logout?');">
            <i class="fas fa-sign-out-alt"></i>
            <span class="nav-text">Logout</span>
        </a>
    </div>
</div>

<?php
// Determine the correct path based on where this file is being included from
$js_path = (basename(dirname($_SERVER['PHP_SELF'])) == 'support') ? '../assistant_view/js/assistant_sidebar.js' : 'js/assistant_sidebar.js';
?>
<script src="<?php echo $js_path; ?>"></script>
<script>
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
})();
</script>

