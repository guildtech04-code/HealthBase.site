# HealthBase - Modular Directory Structure

This document describes the organized modular directory structure for the HealthBase application.

## Directory Structure

```
hb/
├── auth/                   # Authentication Module
│   ├── login.php          # User login
│   ├── register.php       # User registration
│   ├── auth.php           # Authentication handler
│   ├── logout.php         # User logout
│   ├── forgot_password.php # Password reset request
│   ├── reset_password.php  # Password reset handler
│   ├── verify.php         # Email verification
│   └── verify_2fa.php     # Two-factor authentication
├── dashboard/              # Dashboard Module
│   ├── healthbase_dashboard.php # Main user dashboard
│   └── doctor_dashboard.php     # Doctor-specific dashboard
├── appointments/           # Appointments Module
│   ├── appointments.php   # Appointments management
│   ├── appointment_success.php # Success page
│   ├── scheduling.php     # Scheduling interface
│   └── fetch_appointments_count.php # AJAX handler
├── support/               # Support/Ticket Module
│   ├── support.php        # Support dashboard
│   ├── contact_support.php # Contact form
│   ├── new_ticket.php     # Create new ticket
│   ├── my_ticket.php      # User's tickets
│   ├── doctor_ticket.php  # Doctor's tickets
│   ├── view_ticket.php    # View ticket details
│   ├── reply_ticket.php   # Reply to ticket
│   └── delete_ticket.php  # Delete ticket
├── admin/                 # Admin Module
│   ├── manage_users.php   # User management
│   ├── toggle_user.php    # Toggle user status
│   ├── bulk_action.php    # Bulk actions handler
│   ├── bulk_delete.php    # Bulk delete
│   ├── bulk_mark_read.php # Bulk mark as read
│   └── bulk_mark_unread.php # Bulk mark as unread
├── notifications/         # Notifications Module
│   ├── fetch_notifications.php # AJAX fetch notifications
│   ├── mark_notification.php # Mark notification
│   ├── mark_notifications_read.php # Mark all as read
│   ├── mark_read.php      # Mark as read
│   ├── mark_ticket_read.php # Mark ticket as read
│   ├── reply_notification.php # Reply to notification
│   ├── update_notification.php # Update notification
│   ├── unread_count.php   # Get unread count
│   ├── toggle_star.php    # Toggle star status
│   └── notification_helper.php # Helper functions
├── assets/                # Static assets
│   ├── fonts/            # Font files
│   ├── icons/             # Favicon and app icons
│   └── images/            # Image assets
├── config/                # Configuration files
│   └── db_connect.php     # Database connection
├── css/                   # Stylesheets
│   ├── dashboard.css      # Dashboard-specific styles
│   └── style.css          # Main stylesheet
├── includes/              # PHP include files
│   └── sidebar.php        # Sidebar component
├── js/                    # JavaScript files
│   └── script.js          # Main JavaScript file
├── uploads/               # File uploads directory
├── docs/                  # Documentation
│   └── Guildtech-Final-Manuscript (1).pdf
├── PHPMailer/             # Email library (unchanged)
├── index.php              # Main entry point
├── test_paths.php         # Path testing utility
└── README.md              # This file
```

## Module Organization

### 1. Authentication Module (`auth/`)
Handles all user authentication and authorization:
- User login/logout
- User registration
- Password reset functionality
- Email verification
- Two-factor authentication

### 2. Dashboard Module (`dashboard/`)
Contains all dashboard interfaces:
- Main user dashboard
- Doctor-specific dashboard
- Analytics and reporting views

### 3. Appointments Module (`appointments/`)
Manages appointment scheduling and management:
- Appointment creation and management
- Scheduling interface
- Appointment success handling
- AJAX handlers for appointment data

### 4. Support Module (`support/`)
Handles support tickets and customer service:
- Ticket creation and management
- Support dashboard
- Contact forms
- Ticket replies and status updates

### 5. Admin Module (`admin/`)
Administrative functions and user management:
- User management interface
- Bulk operations
- User status toggles
- Administrative controls

### 6. Notifications Module (`notifications/`)
Manages system notifications:
- Notification fetching and display
- Mark as read/unread functionality
- Notification replies
- Star/unstar notifications

## Key Benefits

1. **Modular Architecture**: Each module is self-contained with related functionality
2. **Better Organization**: Files are logically grouped by feature/functionality
3. **Easier Maintenance**: Changes to one module don't affect others
4. **Scalability**: Easy to add new modules or extend existing ones
5. **Team Development**: Different developers can work on different modules
6. **Security**: Module-based access control possible
7. **Testing**: Each module can be tested independently

## Path Updates

All file references have been updated to reflect the new modular structure:

- Authentication files: `auth/login.php`, `auth/register.php`, etc.
- Dashboard files: `dashboard/healthbase_dashboard.php`, etc.
- Appointments: `appointments/appointments.php`, etc.
- Support: `support/support.php`, etc.
- Admin: `admin/manage_users.php`, etc.
- Notifications: `notifications/fetch_notifications.php`, etc.

## Testing

Run `test_paths.php` to verify all module paths are working correctly after the reorganization.

## Notes

- All PHP files have been updated to use the new modular paths
- CSS and asset paths have been updated with correct relative paths
- Database connections are centralized in `config/db_connect.php`
- Form actions and redirects have been updated to use module paths
- The PHPMailer library remains in its original location
