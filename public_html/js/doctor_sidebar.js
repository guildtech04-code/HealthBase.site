// Enhanced Doctor Sidebar JavaScript
document.addEventListener('DOMContentLoaded', function() {
    console.log('Doctor Sidebar JavaScript Loading...');
    
    const sidebar = document.getElementById('doctorSidebar');
    const sidebarToggle = document.getElementById('doctorSidebarToggle');
    const pinToggle = document.getElementById('doctorPinToggle');
    
    // Check if sidebar exists
    if (!sidebar) {
        console.error('Doctor sidebar element not found!');
        return;
    }
    
    console.log('Doctor sidebar element:', sidebar);
    console.log('Sidebar toggle:', sidebarToggle);
    console.log('Pin toggle:', pinToggle);
    
    // Initialize sidebar state from localStorage
    initializeDoctorSidebarState(sidebar, pinToggle);
    
    // Set up event listeners
    setupSidebarEventListeners(sidebar, sidebarToggle, pinToggle);
    
    // Set up other animations
    setupAnimations();
});

function initializeDoctorSidebarState(sidebar, pinToggle) {
    try {
        const isPinned = localStorage.getItem('doctorSidebarPinned') === 'true';
        const isCollapsed = localStorage.getItem('doctorSidebarCollapsed') === 'true';
        
        console.log('Initial state - isPinned:', isPinned, 'isCollapsed:', isCollapsed);
        
        // Apply saved states immediately
        if (isPinned) {
            sidebar.classList.add('pinned');
            if (pinToggle) pinToggle.classList.add('pinned');
            sidebar.classList.remove('collapsed');
            console.log('Doctor sidebar pinned');
        } else {
            // When not pinned, apply collapsed state if saved
            if (isCollapsed === true || isCollapsed === 'true') {
                sidebar.classList.add('collapsed');
                console.log('Doctor sidebar collapsed');
            } else {
                sidebar.classList.remove('collapsed');
                console.log('Doctor sidebar expanded by default');
            }
        }
        
        // Update body classes and main content margin for layout adjustments
        updateDoctorBodyClasses(sidebar);
        
    } catch (error) {
        console.error('Error initializing doctor sidebar state:', error);
    }
}

function updateDoctorBodyClasses(sidebar) {
    try {
        const body = document.body;
        const mainContent = document.querySelector('.main-content');
        const isPinned = sidebar.classList.contains('pinned');
        const isCollapsed = sidebar.classList.contains('collapsed');
        
        // Remove existing classes
        body.classList.remove('doctor-sidebar-pinned', 'doctor-sidebar-collapsed', 'doctor-sidebar-expanded');
        
        // Add appropriate classes
        if (isPinned) {
            body.classList.add('doctor-sidebar-pinned');
            if (mainContent) mainContent.style.marginLeft = '280px';
        } else if (isCollapsed) {
            body.classList.add('doctor-sidebar-collapsed');
            if (mainContent) mainContent.style.marginLeft = '280px';
        } else {
            body.classList.add('doctor-sidebar-expanded');
            if (mainContent) mainContent.style.marginLeft = '280px';
        }
        
        console.log('Body classes updated - pinned:', isPinned, 'collapsed:', isCollapsed);
    } catch (error) {
        console.error('Error updating body classes:', error);
    }
}

function setupSidebarEventListeners(sidebar, sidebarToggle, pinToggle) {
    // Toggle sidebar collapse
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Doctor sidebar toggle clicked');
            
            // Only allow collapse/expand when not pinned
            if (!sidebar.classList.contains('pinned')) {
                sidebar.classList.toggle('collapsed');
                localStorage.setItem('doctorSidebarCollapsed', sidebar.classList.contains('collapsed'));
                updateDoctorBodyClasses(sidebar);
                console.log('Doctor sidebar toggled to:', sidebar.classList.contains('collapsed') ? 'collapsed' : 'expanded');
            }
        });
    } else {
        console.log('Doctor sidebar toggle element not found');
    }
    
    // Toggle pin state - CRITICAL FIX
    if (pinToggle) {
        pinToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Doctor pin toggle clicked');
            
            const wasPinned = sidebar.classList.contains('pinned');
            sidebar.classList.toggle('pinned');
            pinToggle.classList.toggle('pinned');
            
            const isPinned = sidebar.classList.contains('pinned');
            console.log('Doctor pin state changed to:', isPinned);
            
            if (isPinned) {
                // When pinning, expand the sidebar and adjust layout
                sidebar.classList.remove('collapsed');
                localStorage.setItem('doctorSidebarCollapsed', 'false');
                console.log('Doctor sidebar pinned and expanded');
            } else {
                // When unpinning, collapse the sidebar
                sidebar.classList.add('collapsed');
                localStorage.setItem('doctorSidebarCollapsed', 'true');
                console.log('Doctor sidebar unpinned and collapsed');
            }
            
            localStorage.setItem('doctorSidebarPinned', isPinned.toString());
            updateDoctorBodyClasses(sidebar);
        });
    } else {
        console.log('Doctor pin toggle element not found');
    }
    
    // Auto-expand on hover when not pinned
    sidebar.addEventListener('mouseenter', function() {
        if (!sidebar.classList.contains('pinned')) {
            sidebar.classList.add('hover-expanded');
            console.log('Doctor sidebar hover expanded');
        }
    });
    
    sidebar.addEventListener('mouseleave', function() {
        if (!sidebar.classList.contains('pinned')) {
            sidebar.classList.remove('hover-expanded');
            console.log('Doctor sidebar hover collapsed');
        }
    });
    
    // Mobile menu toggle
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('mobile-open');
        });
    }
    
    // Close mobile sidebar when clicking outside
    if (mobileMenuToggle) {
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768 && !sidebar.contains(e.target) && !mobileMenuToggle.contains(e.target)) {
                sidebar.classList.remove('mobile-open');
            }
        });
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('mobile-open');
        }
        updateDoctorBodyClasses(sidebar);
    });
}

function setupAnimations() {
    // Add smooth animations to stat cards (if they exist)
    const statCards = document.querySelectorAll('.stat-card');
    if (statCards.length > 0) {
        statCards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
            card.classList.add('animate-in');
        });
    }
    
    // Add hover effects to quick action buttons (if they exist)
    const quickActionBtns = document.querySelectorAll('.quick-action-btn');
    if (quickActionBtns.length > 0) {
        quickActionBtns.forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-3px) scale(1.02)';
            });
            
            btn.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });
    }
    
    // Add loading animation to appointment items (if they exist)
    const appointmentItems = document.querySelectorAll('.appointment-item');
    if (appointmentItems.length > 0) {
        appointmentItems.forEach((item, index) => {
            item.style.opacity = '0';
            item.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                item.style.transition = 'all 0.3s ease';
                item.style.opacity = '1';
                item.style.transform = 'translateY(0)';
            }, index * 100);
        });
    }
}

// Add CSS for animations
if (!document.getElementById('doctor-sidebar-styles')) {
    const sidebarStyle = document.createElement('style');
    sidebarStyle.id = 'doctor-sidebar-styles';
    sidebarStyle.textContent = `
        .stat-card.animate-in {
            animation: slideInUp 0.6s ease forwards;
        }
        
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .quick-action-btn {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .appointment-item {
            transition: all 0.2s ease;
        }
    `;
    document.head.appendChild(sidebarStyle);
}
