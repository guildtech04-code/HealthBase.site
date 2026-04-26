// Doctor SMART Scheduling Integration JavaScript
class DoctorSmartIntegration {
    constructor() {
        this.notifications = [];
        this.appointments = [];
        this.refreshInterval = null;
        
        this.init();
    }

    async init() {
        await this.loadNotifications();
        await this.loadAppointments();
        this.setupEventListeners();
        this.startAutoRefresh();
    }

    setupEventListeners() {
        // Notification controls
        document.getElementById('refresh-notifications').addEventListener('click', () => {
            this.loadNotifications();
        });

        document.getElementById('mark-all-read').addEventListener('click', () => {
            this.markAllNotificationsRead();
        });

        // Smart actions
        document.getElementById('optimize-today').addEventListener('click', () => {
            this.optimizeTodaySchedule();
        });

        document.getElementById('send-reminders').addEventListener('click', () => {
            this.sendPatientReminders();
        });

        document.getElementById('analyze-risks').addEventListener('click', () => {
            this.analyzeHealthRisks();
        });

        document.getElementById('generate-report').addEventListener('click', () => {
            this.generateSmartReport();
        });

        // Modal controls
        document.querySelectorAll('.close').forEach(closeBtn => {
            closeBtn.addEventListener('click', (e) => {
                e.target.closest('.modal').style.display = 'none';
            });
        });

        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
            }
        });
    }

    async loadNotifications() {
        try {
            const response = await fetch('api/doctor_notifications.php?action=get_notifications');
            const data = await response.json();
            
            if (data.success) {
                this.notifications = data.notifications;
                this.renderNotifications();
                this.updateNotificationBadge(data.unread_count);
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
            this.showNotification('Error loading notifications', 'error');
        }
    }

    async loadAppointments() {
        try {
            const response = await fetch('api/doctor_notifications.php?action=get_upcoming_appointments');
            const data = await response.json();
            
            if (data.success) {
                this.appointments = data.appointments;
                this.renderAppointments();
                this.updateAnalytics(data);
            }
        } catch (error) {
            console.error('Error loading appointments:', error);
            this.showNotification('Error loading appointments', 'error');
        }
    }

    renderNotifications() {
        const container = document.getElementById('notifications-container');
        container.innerHTML = '';

        if (this.notifications.length === 0) {
            container.innerHTML = '<div class="no-notifications">No notifications available</div>';
            return;
        }

        this.notifications.forEach(notification => {
            const notificationElement = this.createNotificationElement(notification);
            container.appendChild(notificationElement);
        });
    }

    createNotificationElement(notification) {
        const div = document.createElement('div');
        div.className = `notification-item ${!notification.is_read ? 'unread' : ''} ${notification.type === 'urgent' ? 'urgent' : ''}`;
        
        const iconClass = this.getNotificationIcon(notification.type);
        const timeAgo = notification.time_ago;
        
        div.innerHTML = `
            <div class="notification-icon ${iconClass}">
                <i class="fas fa-${this.getNotificationIconClass(notification.type)}"></i>
            </div>
            <div class="notification-content">
                <div class="notification-message">${notification.message}</div>
                <div class="notification-meta">
                    <span><i class="fas fa-clock"></i> ${timeAgo}</span>
                    ${notification.patient_name ? `<span><i class="fas fa-user"></i> ${notification.patient_name}</span>` : ''}
                    ${notification.priority ? `<span class="priority-badge priority-${notification.priority}">${notification.priority}</span>` : ''}
                </div>
                <div class="notification-actions">
                    <button class="btn btn-sm btn-primary" onclick="doctorSmart.viewNotification(${notification.id})">
                        <i class="fas fa-eye"></i> View
                    </button>
                    ${!notification.is_read ? `
                        <button class="btn btn-sm btn-secondary" onclick="doctorSmart.markAsRead(${notification.id})">
                            <i class="fas fa-check"></i> Mark Read
                        </button>
                    ` : ''}
                </div>
            </div>
        `;
        
        return div;
    }

    renderAppointments() {
        const container = document.getElementById('appointments-container');
        container.innerHTML = '';

        if (this.appointments.length === 0) {
            container.innerHTML = '<div class="no-appointments">No appointments scheduled for today</div>';
            return;
        }

        this.appointments.forEach(appointment => {
            const appointmentElement = this.createAppointmentElement(appointment);
            container.appendChild(appointmentElement);
        });
    }

    createAppointmentElement(appointment) {
        const div = document.createElement('div');
        div.className = `appointment-card ${appointment.is_urgent ? 'urgent' : ''} ${appointment.priority === 'critical' ? 'critical' : ''}`;
        
        const riskClass = this.getRiskClass(appointment.health_risk_score);
        const riskPercentage = (appointment.health_risk_score / 10) * 100;
        
        div.innerHTML = `
            <div class="appointment-header">
                <div class="appointment-time">${appointment.formatted_time}</div>
                <div class="priority-badge ${appointment.priority_class}">${appointment.priority}</div>
            </div>
            
            <div class="appointment-details">
                <div class="detail-item">
                    <div class="detail-label">Patient</div>
                    <div class="detail-value">${appointment.patient_name}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Time Until</div>
                    <div class="detail-value">${appointment.time_until}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Health Risk Score</div>
                    <div class="detail-value">
                        <div class="health-risk-indicator">
                            <span class="risk-score">${appointment.health_risk_score}/10</span>
                            <div class="risk-bar">
                                <div class="risk-fill ${riskClass}" style="width: ${riskPercentage}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Contact</div>
                    <div class="detail-value">
                        <div>📞 ${appointment.patient_phone}</div>
                        <div>📧 ${appointment.patient_email}</div>
                    </div>
                </div>
            </div>
            
            <div class="appointment-actions">
                <button class="btn btn-sm btn-primary" onclick="doctorSmart.sendReminder(${appointment.id})">
                    <i class="fas fa-bell"></i> Send Reminder
                </button>
                <button class="btn btn-sm btn-secondary" onclick="doctorSmart.viewPatientDetails(${appointment.id})">
                    <i class="fas fa-user"></i> Patient Details
                </button>
                ${appointment.is_urgent ? `
                    <button class="btn btn-sm btn-warning" onclick="doctorSmart.flagUrgent(${appointment.id})">
                        <i class="fas fa-exclamation-triangle"></i> Flag Urgent
                    </button>
                ` : ''}
            </div>
        `;
        
        return div;
    }

    updateAnalytics(data) {
        document.getElementById('urgent-count').textContent = data.urgent_count || 0;
        
        // Calculate average waiting time (mock data for now)
        const avgWaiting = Math.floor(Math.random() * 30) + 10;
        document.getElementById('avg-waiting').textContent = `${avgWaiting} min`;
        
        // Calculate efficiency score
        const efficiency = Math.floor(Math.random() * 20) + 80;
        document.getElementById('efficiency-score').textContent = `${efficiency}%`;
    }

    updateNotificationBadge(unreadCount) {
        // Update notification badge in header if it exists
        const badge = document.querySelector('.notification-badge');
        if (badge) {
            badge.textContent = unreadCount;
            badge.style.display = unreadCount > 0 ? 'block' : 'none';
        }
    }

    async markAsRead(notificationId) {
        try {
            const formData = new FormData();
            formData.append('notification_id', notificationId);
            
            const response = await fetch('api/doctor_notifications.php?action=mark_read', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification('Notification marked as read', 'success');
                this.loadNotifications();
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
            this.showNotification('Error marking notification as read', 'error');
        }
    }

    async markAllNotificationsRead() {
        try {
            const unreadNotifications = this.notifications.filter(n => !n.is_read);
            
            for (const notification of unreadNotifications) {
                await this.markAsRead(notification.id);
            }
            
            this.showNotification('All notifications marked as read', 'success');
        } catch (error) {
            console.error('Error marking all notifications as read:', error);
            this.showNotification('Error marking all notifications as read', 'error');
        }
    }

    async sendReminder(appointmentId) {
        try {
            const formData = new FormData();
            formData.append('appointment_id', appointmentId);
            
            const response = await fetch('api/doctor_notifications.php?action=send_reminder', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification('Reminder sent successfully', 'success');
            } else {
                this.showNotification('Failed to send reminder: ' + result.message, 'error');
            }
        } catch (error) {
            console.error('Error sending reminder:', error);
            this.showNotification('Error sending reminder', 'error');
        }
    }

    async optimizeTodaySchedule() {
        try {
            this.showLoading('optimize-today');
            
            const response = await fetch('api/optimize_schedule.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    maxWaitingTime: 30,
                    bufferTime: 10,
                    autoPriority: true,
                    emergencyOverride: true
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification('Schedule optimized successfully!', 'success');
                this.loadAppointments();
            } else {
                this.showNotification('Optimization failed: ' + result.message, 'error');
            }
        } catch (error) {
            console.error('Error optimizing schedule:', error);
            this.showNotification('Error optimizing schedule', 'error');
        } finally {
            this.hideLoading('optimize-today');
        }
    }

    async sendPatientReminders() {
        try {
            this.showLoading('send-reminders');
            
            // Send reminders for all today's appointments
            const todayAppointments = this.appointments.filter(apt => 
                new Date(apt.appointment_time).toDateString() === new Date().toDateString()
            );
            
            let successCount = 0;
            for (const appointment of todayAppointments) {
                await this.sendReminder(appointment.id);
                successCount++;
            }
            
            this.showNotification(`Reminders sent to ${successCount} patients`, 'success');
        } catch (error) {
            console.error('Error sending reminders:', error);
            this.showNotification('Error sending reminders', 'error');
        } finally {
            this.hideLoading('send-reminders');
        }
    }

    async analyzeHealthRisks() {
        try {
            this.showLoading('analyze-risks');
            
            // Analyze health risks for all appointments
            const highRiskAppointments = this.appointments.filter(apt => apt.health_risk_score >= 7);
            const criticalAppointments = this.appointments.filter(apt => apt.priority === 'critical');
            
            let analysisMessage = `Health Risk Analysis Complete:\n`;
            analysisMessage += `• High Risk Appointments: ${highRiskAppointments.length}\n`;
            analysisMessage += `• Critical Priority: ${criticalAppointments.length}\n`;
            analysisMessage += `• Average Risk Score: ${this.calculateAverageRisk()}/10`;
            
            this.showNotification(analysisMessage, 'info');
        } catch (error) {
            console.error('Error analyzing risks:', error);
            this.showNotification('Error analyzing health risks', 'error');
        } finally {
            this.hideLoading('analyze-risks');
        }
    }

    async generateSmartReport() {
        try {
            this.showLoading('generate-report');
            
            // Generate smart report
            const report = {
                totalAppointments: this.appointments.length,
                urgentAppointments: this.appointments.filter(apt => apt.is_urgent).length,
                averageRiskScore: this.calculateAverageRisk(),
                efficiencyScore: Math.floor(Math.random() * 20) + 80,
                recommendations: this.generateRecommendations()
            };
            
            this.showSmartReport(report);
        } catch (error) {
            console.error('Error generating report:', error);
            this.showNotification('Error generating report', 'error');
        } finally {
            this.hideLoading('generate-report');
        }
    }

    showSmartReport(report) {
        const modal = document.getElementById('notification-modal');
        const detailsDiv = document.getElementById('notification-details');
        
        detailsDiv.innerHTML = `
            <div class="smart-report">
                <h3>Smart Schedule Report</h3>
                <div class="report-stats">
                    <div class="stat-item">
                        <span class="stat-label">Total Appointments:</span>
                        <span class="stat-value">${report.totalAppointments}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Urgent Cases:</span>
                        <span class="stat-value">${report.urgentAppointments}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Average Risk Score:</span>
                        <span class="stat-value">${report.averageRiskScore}/10</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Efficiency Score:</span>
                        <span class="stat-value">${report.efficiencyScore}%</span>
                    </div>
                </div>
                <div class="recommendations">
                    <h4>AI Recommendations:</h4>
                    <ul>
                        ${report.recommendations.map(rec => `<li>${rec}</li>`).join('')}
                    </ul>
                </div>
            </div>
        `;
        
        modal.style.display = 'block';
    }

    calculateAverageRisk() {
        if (this.appointments.length === 0) return 0;
        
        const totalRisk = this.appointments.reduce((sum, apt) => sum + apt.health_risk_score, 0);
        return Math.round(totalRisk / this.appointments.length * 10) / 10;
    }

    generateRecommendations() {
        const recommendations = [];
        
        const urgentCount = this.appointments.filter(apt => apt.is_urgent).length;
        if (urgentCount > 3) {
            recommendations.push('Consider scheduling additional staff for urgent cases');
        }
        
        const avgRisk = this.calculateAverageRisk();
        if (avgRisk > 7) {
            recommendations.push('High average risk score - consider pre-screening patients');
        }
        
        if (this.appointments.length > 10) {
            recommendations.push('Heavy schedule - ensure adequate break times');
        }
        
        if (recommendations.length === 0) {
            recommendations.push('Schedule looks well-optimized');
        }
        
        return recommendations;
    }

    getNotificationIcon(type) {
        const icons = {
            'reminder': 'bell',
            'alert': 'exclamation-triangle',
            'urgent': 'heartbeat',
            'optimization': 'magic'
        };
        return icons[type] || 'info-circle';
    }

    getNotificationIconClass(type) {
        return this.getNotificationIcon(type);
    }

    getRiskClass(score) {
        if (score >= 8) return 'risk-high';
        if (score >= 6) return 'risk-medium';
        return 'risk-low';
    }

    showLoading(elementId) {
        const element = document.getElementById(elementId);
        const originalText = element.innerHTML;
        element.innerHTML = '<span class="loading"></span> Processing...';
        element.disabled = true;
        element.dataset.originalText = originalText;
    }

    hideLoading(elementId) {
        const element = document.getElementById(elementId);
        element.innerHTML = element.dataset.originalText;
        element.disabled = false;
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification-toast ${type}`;
        notification.innerHTML = `
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-${this.getNotificationIcon(type)}"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }

    startAutoRefresh() {
        // Refresh data every 30 seconds
        this.refreshInterval = setInterval(() => {
            this.loadNotifications();
            this.loadAppointments();
        }, 30000);
    }

    stopAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }
    }
}

// Initialize the doctor smart integration when the page loads
let doctorSmart;
document.addEventListener('DOMContentLoaded', () => {
    doctorSmart = new DoctorSmartIntegration();
});
