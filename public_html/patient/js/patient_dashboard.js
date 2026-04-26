// Enhanced Patient Dashboard JavaScript with Error Handling
document.addEventListener('DOMContentLoaded', function() {
    console.log('Patient Dashboard JavaScript Loading...');
    
    // Wait a bit to ensure DOM is fully ready
    setTimeout(function() {
        initializeSidebar();
    }, 100);
});

function initializeSidebar() {
    const sidebar = document.getElementById('patientSidebar');
    const sidebarToggle = document.getElementById('patientSidebarToggle');
    const pinToggle = document.getElementById('patientPinToggle');
    
    // Debug: Check if elements exist
    console.log('Sidebar element:', sidebar);
    console.log('Sidebar toggle:', sidebarToggle);
    console.log('Pin toggle:', pinToggle);
    
    // Check if sidebar exists
    if (!sidebar) {
        console.error('Sidebar element not found!');
        return;
    }
    
    // Initialize sidebar state from localStorage
    initializePatientSidebarState(sidebar, pinToggle);
    
    // Set up event listeners
    setupSidebarEventListeners(sidebar, sidebarToggle, pinToggle);
    
    // Set up other animations
    setupAnimations();
}

function initializePatientSidebarState(sidebar, pinToggle) {
    try {
        // Force sidebar to be expanded by default
        sidebar.classList.remove('collapsed');
        
        const isPinned = localStorage.getItem('patientSidebarPinned') === 'true';
        const isCollapsed = localStorage.getItem('patientSidebarCollapsed') === 'true';
        
        console.log('Initial state - isPinned:', isPinned, 'isCollapsed:', isCollapsed);
        
        // Apply saved states immediately
        if (isPinned) {
            sidebar.classList.add('pinned');
            if (pinToggle) pinToggle.classList.add('pinned');
            sidebar.classList.remove('collapsed');
            console.log('Sidebar pinned');
        } else {
            // Only collapse if explicitly saved as collapsed
            if (isCollapsed === true) {
                sidebar.classList.add('collapsed');
                console.log('Sidebar collapsed');
            } else {
                sidebar.classList.remove('collapsed');
                console.log('Sidebar expanded');
            }
        }
        
        // Update body classes for layout adjustments
        updatePatientBodyClasses(sidebar);
        
    } catch (error) {
        console.error('Error initializing sidebar state:', error);
    }
}

function updatePatientBodyClasses(sidebar) {
    try {
        const body = document.body;
        const isPinned = sidebar.classList.contains('pinned');
        const isCollapsed = sidebar.classList.contains('collapsed');
        
        // Remove existing classes
        body.classList.remove('patient-sidebar-pinned', 'patient-sidebar-collapsed', 'patient-sidebar-expanded');
        
        // Add appropriate classes
        if (isPinned) {
            body.classList.add('patient-sidebar-pinned');
        } else if (isCollapsed) {
            body.classList.add('patient-sidebar-collapsed');
        } else {
            body.classList.add('patient-sidebar-expanded');
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
            console.log('Sidebar toggle clicked');
            
            // Only allow collapse/expand when not pinned
            if (!sidebar.classList.contains('pinned')) {
                sidebar.classList.toggle('collapsed');
                localStorage.setItem('patientSidebarCollapsed', sidebar.classList.contains('collapsed'));
                updatePatientBodyClasses(sidebar);
                console.log('Sidebar toggled to:', sidebar.classList.contains('collapsed') ? 'collapsed' : 'expanded');
            }
        });
    } else {
        console.log('Sidebar toggle element not found');
    }
    
    // Toggle pin state
    if (pinToggle) {
        pinToggle.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Pin toggle clicked');
            
            const wasPinned = sidebar.classList.contains('pinned');
            sidebar.classList.toggle('pinned');
            pinToggle.classList.toggle('pinned');
            
            const isPinned = sidebar.classList.contains('pinned');
            console.log('Pin state changed to:', isPinned);
            
            if (isPinned) {
                // When pinning, expand the sidebar
                sidebar.classList.remove('collapsed');
                localStorage.setItem('patientSidebarCollapsed', 'false');
                console.log('Sidebar pinned and expanded');
            } else {
                // When unpinning, collapse the sidebar
                sidebar.classList.add('collapsed');
                localStorage.setItem('patientSidebarCollapsed', 'true');
                console.log('Sidebar unpinned and collapsed');
            }
            
            localStorage.setItem('patientSidebarPinned', isPinned.toString());
            updatePatientBodyClasses(sidebar);
        });
    } else {
        console.log('Pin toggle element not found');
    }
    
    // Auto-expand on hover when not pinned
    sidebar.addEventListener('mouseenter', function() {
        if (!sidebar.classList.contains('pinned')) {
            sidebar.classList.add('hover-expanded');
            console.log('Sidebar hover expanded');
        }
    });
    
    sidebar.addEventListener('mouseleave', function() {
        if (!sidebar.classList.contains('pinned')) {
            sidebar.classList.remove('hover-expanded');
            console.log('Sidebar hover collapsed');
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
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768 && !sidebar.contains(e.target) && !mobileMenuToggle?.contains(e.target)) {
            sidebar.classList.remove('mobile-open');
        }
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('mobile-open');
        }
    });
}

function setupAnimations() {
    // Add smooth animations to stat cards
    const statCards = document.querySelectorAll('.patient-stat-card');
    statCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.classList.add('animate-in');
    });
    
    // Add hover effects to quick action buttons
    const quickActionBtns = document.querySelectorAll('.quick-action-btn');
    quickActionBtns.forEach(btn => {
        btn.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-3px) scale(1.02)';
        });
        
        btn.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
    
    // Add loading animation to appointment items
    const appointmentItems = document.querySelectorAll('.patient-appointment-item');
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

// Add CSS for animations
const style = document.createElement('style');
style.textContent = `
    .patient-stat-card.animate-in {
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
    
    .patient-appointment-item {
        transition: all 0.2s ease;
    }
`;
document.head.appendChild(style);