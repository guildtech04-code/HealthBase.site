// Assistant Sidebar JavaScript
document.addEventListener('DOMContentLoaded', function() {
    console.log('Assistant Sidebar JavaScript Loading...');
    
    const sidebar = document.getElementById('assistantSidebar');
    const sidebarToggle = document.getElementById('assistantSidebarToggle');
    const pinToggle = document.getElementById('assistantPinToggle');
    
    // Check if sidebar exists
    if (!sidebar) {
        console.error('Assistant sidebar element not found!');
        return;
    }
    
    console.log('Assistant Sidebar element:', sidebar);
    console.log('Sidebar toggle:', sidebarToggle);
    console.log('Pin toggle:', pinToggle);
    
    // Initialize sidebar state from localStorage
    initializeAssistantSidebarState(sidebar, pinToggle);
    
    // Set up event listeners
    setupAssistantSidebarEventListeners(sidebar, sidebarToggle, pinToggle);
});

function initializeAssistantSidebarState(sidebar, pinToggle) {
    try {
        const isPinned = localStorage.getItem('assistantSidebarPinned') === 'true';
        const isCollapsed = localStorage.getItem('assistantSidebarCollapsed') === 'true';
        
        console.log('Initial state - isPinned:', isPinned, 'isCollapsed:', isCollapsed);
        
        // Apply saved states immediately
        if (isPinned) {
            sidebar.classList.add('pinned');
            if (pinToggle) pinToggle.classList.add('pinned');
            sidebar.classList.remove('collapsed');
            console.log('Sidebar pinned');
        } else {
            // When not pinned, apply collapsed state if saved
            if (isCollapsed === true || isCollapsed === 'true') {
                sidebar.classList.add('collapsed');
                console.log('Sidebar collapsed');
            } else {
                sidebar.classList.remove('collapsed');
                console.log('Sidebar expanded by default');
            }
        }
        
        // Update body classes and main content margin for layout adjustments
        updateAssistantBodyClasses(sidebar);
        
    } catch (error) {
        console.error('Error initializing sidebar state:', error);
    }
}

function updateAssistantBodyClasses(sidebar) {
    try {
        const body = document.body;
        const mainContent = document.querySelector('.assistant-main-content');
        const isPinned = sidebar.classList.contains('pinned');
        const isCollapsed = sidebar.classList.contains('collapsed');
        
        // Remove existing classes
        body.classList.remove('assistant-sidebar-pinned', 'assistant-sidebar-collapsed', 'assistant-sidebar-expanded');
        
        // Add appropriate classes
        if (isPinned) {
            body.classList.add('assistant-sidebar-pinned');
            if (mainContent) mainContent.style.marginLeft = '280px';
        } else if (isCollapsed) {
            body.classList.add('assistant-sidebar-collapsed');
            if (mainContent) mainContent.style.marginLeft = '70px';
        } else {
            body.classList.add('assistant-sidebar-expanded');
            if (mainContent) mainContent.style.marginLeft = '280px';
        }
        
        console.log('Body classes updated - pinned:', isPinned, 'collapsed:', isCollapsed);
    } catch (error) {
        console.error('Error updating body classes:', error);
    }
}

function setupAssistantSidebarEventListeners(sidebar, sidebarToggle, pinToggle) {
    // Toggle sidebar collapse
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Sidebar toggle clicked');
            
            // Only allow collapse/expand when not pinned
            if (!sidebar.classList.contains('pinned')) {
                sidebar.classList.toggle('collapsed');
                localStorage.setItem('assistantSidebarCollapsed', sidebar.classList.contains('collapsed'));
                updateAssistantBodyClasses(sidebar);
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
            e.stopPropagation();
            console.log('Pin toggle clicked');
            
            const wasPinned = sidebar.classList.contains('pinned');
            sidebar.classList.toggle('pinned');
            pinToggle.classList.toggle('pinned');
            
            const isPinned = sidebar.classList.contains('pinned');
            console.log('Pin state changed to:', isPinned);
            
            if (isPinned) {
                // When pinning, expand the sidebar and adjust layout
                sidebar.classList.remove('collapsed');
                localStorage.setItem('assistantSidebarCollapsed', 'false');
                console.log('Sidebar pinned and expanded');
            } else {
                // When unpinning, collapse the sidebar
                sidebar.classList.add('collapsed');
                localStorage.setItem('assistantSidebarCollapsed', 'true');
                console.log('Sidebar unpinned and collapsed');
            }
            
            localStorage.setItem('assistantSidebarPinned', isPinned.toString());
            updateAssistantBodyClasses(sidebar);
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
    
    // Handle window resize
    window.addEventListener('resize', function() {
        updateAssistantBodyClasses(sidebar);
    });
}

