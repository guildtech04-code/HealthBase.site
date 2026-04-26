/* ===============================
   MY TICKETS PAGE JAVASCRIPT
   Modern Clinic Theme
   (Appointments Directory Version)
=============================== */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize notifications and profile dropdowns
    initializeDropdowns();
    
    // Initialize notifications
    initializeNotifications();
});

function initializeDropdowns() {
    // Notifications dropdown
    const notifIcon = document.querySelector('.notification-icon');
    const notifDropdown = document.querySelector('.notif-dropdown');
    const notifications = document.querySelector('.notifications');
    
    // Profile dropdown
    const profileIcon = document.querySelector('.profile-icon');
    const profileDropdown = document.querySelector('.profile-dropdown');
    const profile = document.querySelector('.profile');
    
    // Notifications toggle
    if (notifIcon && notifDropdown) {
        notifIcon.addEventListener('click', function(e) {
            e.stopPropagation();
            notifDropdown.classList.toggle('show');
            
            // Close profile dropdown if open
            if (profileDropdown) {
                profileDropdown.classList.remove('show');
            }
        });
    }
    
    // Profile toggle
    if (profileIcon && profileDropdown) {
        profileIcon.addEventListener('click', function(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
            
            // Close notifications dropdown if open
            if (notifDropdown) {
                notifDropdown.classList.remove('show');
            }
        });
    }
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (notifDropdown && !notifications.contains(e.target)) {
            notifDropdown.classList.remove('show');
        }
        
        if (profileDropdown && !profile.contains(e.target)) {
            profileDropdown.classList.remove('show');
        }
    });
}

function initializeNotifications() {
    const notifIcon = document.getElementById('notificationIcon');
    const notifBadge = document.getElementById('notificationBadge');
    const notifList = document.getElementById('notifList');
    const markAllRead = document.getElementById('markAllRead');

    // Fetch notifications
    function fetchNotifications() {
        fetch('../notifications/fetch_notifications.php')
            .then(response => response.json())
            .then(data => {
                updateNotificationBadge(data);
                updateNotificationList(data);
            })
            .catch(error => {
                console.error('Error fetching notifications:', error);
            });
    }

    // Update notification badge
    function updateNotificationBadge(notifications) {
        const unreadCount = notifications.filter(notif => notif.is_read == 0).length;
        notifBadge.textContent = unreadCount;
        notifBadge.style.display = unreadCount > 0 ? 'flex' : 'none';
    }

    // Update notification list
    function updateNotificationList(notifications) {
        if (notifications.length === 0) {
            notifList.innerHTML = '<div class="notif-item"><div class="notif-content">No new notifications</div></div>';
            return;
        }

        notifList.innerHTML = notifications.map(notif => {
            const messageHtml = notif.ticket_id
                ? `<a href="my_ticket.php?id=${notif.ticket_id}" style="color: inherit; text-decoration: none;">${notif.message}</a>`
                : notif.message;

            return `
                <div class="notif-item">
                    <div class="notif-content" style="${notif.is_read == 0 ? 'font-weight: 600;' : 'color: #64748b;'}">
                        ${messageHtml}
                        <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">
                            ${formatDate(notif.created_at)}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    // Mark all notifications as read
    if (markAllRead) {
        markAllRead.addEventListener('click', function() {
            fetch('../notifications/mark_notifications_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'mark_all=1'
            })
            .then(() => {
                fetchNotifications();
            })
            .catch(error => {
                console.error('Error marking notifications as read:', error);
            });
        });
    }

    // Fetch notifications every 30 seconds
    fetchNotifications();
    setInterval(fetchNotifications, 30000);
}

// Utility function to format dates
function formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);

    if (diffInSeconds < 60) {
        return 'Just now';
    } else if (diffInSeconds < 3600) {
        const minutes = Math.floor(diffInSeconds / 60);
        return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
    } else if (diffInSeconds < 86400) {
        const hours = Math.floor(diffInSeconds / 3600);
        return `${hours} hour${hours > 1 ? 's' : ''} ago`;
    } else {
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
}

// Add smooth scrolling for better UX
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Add loading states for ticket cards
function addLoadingStates() {
    const ticketCards = document.querySelectorAll('.ticket-card');
    ticketCards.forEach(card => {
        const viewBtn = card.querySelector('.view-ticket-btn');
        if (viewBtn) {
            viewBtn.addEventListener('click', function(e) {
                // Add loading state
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                this.style.pointerEvents = 'none';
                
                // Remove loading state after a short delay (since page will navigate)
                setTimeout(() => {
                    this.innerHTML = originalText;
                    this.style.pointerEvents = 'auto';
                }, 2000);
            });
        }
    });
}

// Initialize loading states
addLoadingStates();

// Export functions for potential external use
window.MyTicketsJS = {
    initializeDropdowns,
    initializeNotifications,
    formatDate,
    addLoadingStates
};