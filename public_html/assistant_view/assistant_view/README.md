# SMART Scheduling System

A comprehensive AI-powered appointment management system that automates scheduling based on patient priority, doctor availability, and predicted health risk factors.

## 🚀 Features

### 2.1 Resource Allocation Optimization
- **Adaptive Scheduling Algorithms**: Automatically reschedules appointments based on urgency and priority
- **Waiting Time Minimization**: Reduces patient waiting time through intelligent slot allocation
- **Conflict Resolution**: Automatically detects and resolves scheduling conflicts
- **Priority-Based Scheduling**: Critical cases are automatically prioritized

### 2.2 Automated Notifications & Reminders
- **Patient Reminders**: Automated SMS/email reminders for upcoming appointments
- **Doctor Alerts**: Real-time notifications for doctors about upcoming appointments
- **Staff Notifications**: Alerts for urgent cases requiring immediate attention
- **Smart Notifications**: Context-aware notifications based on appointment priority and health risk

## 🏗️ System Architecture

### Core Components
1. **Assistant Dashboard** (`index.php`) - Main control panel for scheduling management
2. **Doctor Integration** (`doctor_integration.php`) - Doctor-specific view with notifications
3. **API Endpoints** - RESTful APIs for data management and optimization
4. **Notification System** - Automated reminder and alert system
5. **Analytics Dashboard** - Real-time insights and performance metrics

### Database Schema
- `notifications` - Stores all system notifications
- `notification_logs` - Tracks notification history
- `doctor_availability` - Doctor schedule management
- `appointment_optimization_logs` - Optimization history

## 🔧 Setup Instructions

### 1. Database Setup
Run the setup script to initialize the database:
```bash
php setup.php
```

### 2. Assistant Account
The setup script creates an assistant account:
- **Username**: `smart_assistant`
- **Password**: `SmartAssistant2024!`
- **Role**: `assistant`

### 3. Access Points
- **Assistant Dashboard**: `assistant_view/index.php`
- **Doctor Integration**: `assistant_view/doctor_integration.php`
- **Setup Page**: `assistant_view/setup.php`

## 📊 SMART Features

### Health Risk Assessment
- **Risk Scoring**: 1-10 scale based on priority, appointment history, and medical factors
- **Predictive Analytics**: Identifies high-risk patients requiring immediate attention
- **Dynamic Prioritization**: Automatically adjusts appointment priority based on health risk

### Adaptive Scheduling
- **Conflict Detection**: Identifies overlapping appointments and scheduling conflicts
- **Slot Optimization**: Finds optimal time slots based on doctor availability
- **Buffer Management**: Maintains appropriate gaps between appointments
- **Emergency Override**: Automatically handles critical cases

### Notification Intelligence
- **Context-Aware Alerts**: Notifications tailored to appointment type and urgency
- **Multi-Channel Delivery**: Email, SMS, and in-app notifications
- **Escalation Management**: Automatic escalation for unacknowledged urgent cases
- **Reminder Scheduling**: Smart timing for patient reminders

## 🎯 Usage Guide

### For Assistants
1. **Login** with assistant credentials
2. **Monitor Dashboard** for real-time appointment status
3. **Optimize Schedule** using the AI optimization tool
4. **Manage Notifications** for patients and doctors
5. **Analyze Performance** through the analytics dashboard

### For Doctors
1. **Access Integration View** to see SMART notifications
2. **View Upcoming Appointments** with health risk indicators
3. **Send Patient Reminders** with one-click functionality
4. **Monitor Urgent Cases** through priority alerts

## 🔌 API Endpoints

### Core APIs
- `GET /api/get_appointments.php` - Retrieve appointments with filters
- `POST /api/optimize_schedule.php` - Run schedule optimization
- `POST /api/notification_system.php` - Send notifications
- `GET /api/get_dashboard_data.php` - Dashboard analytics

### Doctor APIs
- `GET /api/doctor_notifications.php` - Doctor-specific notifications
- `POST /api/reschedule_appointment.php` - Reschedule appointments
- `GET /api/check_notifications.php` - Real-time notification updates

## 🎨 User Interface

### Assistant Dashboard
- **Modern Design**: Clean, intuitive interface with real-time updates
- **Responsive Layout**: Works on desktop, tablet, and mobile devices
- **Interactive Charts**: Visual analytics for appointment distribution and trends
- **Smart Controls**: Easy-to-use optimization and notification controls

### Doctor Integration
- **Notification Panel**: Real-time alerts and reminders
- **Appointment Cards**: Visual appointment management with health risk indicators
- **Smart Actions**: One-click optimization and reminder sending
- **Analytics Overview**: Performance metrics and efficiency scores

## 🔒 Security Features

- **Role-Based Access**: Different permissions for assistants, doctors, and admins
- **Session Management**: Secure authentication and authorization
- **Data Validation**: Input sanitization and validation
- **Audit Logging**: Complete activity tracking for compliance

## 📈 Performance Metrics

### Key Performance Indicators
- **Average Waiting Time**: Reduced through intelligent scheduling
- **Appointment Efficiency**: Optimized resource utilization
- **Patient Satisfaction**: Improved through better scheduling
- **Doctor Productivity**: Enhanced through smart notifications

### Analytics Dashboard
- **Real-Time Metrics**: Live updates on system performance
- **Trend Analysis**: Historical data and pattern recognition
- **Predictive Insights**: AI-powered recommendations
- **Custom Reports**: Detailed analytics for different stakeholders

## 🚀 Future Enhancements

### Planned Features
- **Machine Learning Integration**: Advanced AI for predictive scheduling
- **Mobile App**: Native mobile application for doctors and assistants
- **Integration APIs**: Connect with external healthcare systems
- **Advanced Analytics**: Deep learning for health risk prediction

## 🛠️ Technical Requirements

### Server Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- SSL certificate (recommended)

### Dependencies
- PDO MySQL extension
- JSON support
- Session management
- Email functionality (PHPMailer recommended)

## 📞 Support

For technical support or feature requests, please contact the development team or refer to the system documentation.

---

**SMART Scheduling System** - Revolutionizing healthcare appointment management through intelligent automation and AI-powered optimization.
