// SMART Scheduling Assistant JavaScript
class SmartSchedulingAssistant {
    constructor() {
        this.appointments = [];
        this.doctors = [];
        this.patients = [];
        this.notifications = [];
        this.charts = {};
        this.optimizationSettings = {
            maxWaitingTime: 30,
            bufferTime: 10,
            autoPriority: true,
            emergencyOverride: true
        };
        
        this.init();
    }

    async init() {
        await this.loadInitialData();
        this.setupEventListeners();
        this.initializeCharts();
        this.startRealTimeUpdates();
        this.loadAppointments();
    }

    async loadInitialData() {
        try {
            // Load doctors
            const doctorsResponse = await fetch('api/get_doctors.php');
            this.doctors = await doctorsResponse.json();
            this.populateDoctorFilter();

            // Load dashboard data
            await this.updateDashboardData();
        } catch (error) {
            console.error('Error loading initial data:', error);
            this.showNotification('Error loading data', 'error');
        }
    }

    setupEventListeners() {
        // Filter controls
        document.getElementById('date-filter').addEventListener('change', () => this.loadAppointments());
        document.getElementById('doctor-filter').addEventListener('change', () => this.loadAppointments());
        document.getElementById('priority-filter').addEventListener('change', () => this.loadAppointments());

        // Action buttons
        document.getElementById('optimize-schedule').addEventListener('click', () => this.optimizeSchedule());
        document.getElementById('refresh-data').addEventListener('click', () => this.refreshData());

        // Settings controls
        document.getElementById('max-waiting').addEventListener('change', (e) => {
            this.optimizationSettings.maxWaitingTime = parseInt(e.target.value);
        });

        document.getElementById('buffer-time').addEventListener('change', (e) => {
            this.optimizationSettings.bufferTime = parseInt(e.target.value);
        });

        document.getElementById('auto-priority').addEventListener('change', (e) => {
            this.optimizationSettings.autoPriority = e.target.checked;
        });

        document.getElementById('emergency-override').addEventListener('change', (e) => {
            this.optimizationSettings.emergencyOverride = e.target.checked;
        });

        // Modal controls
        document.querySelectorAll('.close').forEach(closeBtn => {
            closeBtn.addEventListener('click', (e) => {
                e.target.closest('.modal').style.display = 'none';
            });
        });

        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
            }
        });
    }

    populateDoctorFilter() {
        const doctorFilter = document.getElementById('doctor-filter');
        doctorFilter.innerHTML = '<option value="">All Doctors</option>';
        
        this.doctors.forEach(doctor => {
            const option = document.createElement('option');
            option.value = doctor.id;
            option.textContent = doctor.name;
            doctorFilter.appendChild(option);
        });
    }

    async loadAppointments() {
        try {
            const filters = {
                date: document.getElementById('date-filter').value,
                doctor: document.getElementById('doctor-filter').value,
                priority: document.getElementById('priority-filter').value
            };

            const response = await fetch('api/get_appointments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(filters)
            });

            this.appointments = await response.json();
            this.renderAppointments();
            this.updateCharts();
        } catch (error) {
            console.error('Error loading appointments:', error);
            this.showNotification('Error loading appointments', 'error');
        }
    }

    renderAppointments() {
        const tbody = document.getElementById('appointments-tbody');
        tbody.innerHTML = '';

        this.appointments.forEach(appointment => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${this.formatTime(appointment.appointment_time)}</td>
                <td>${appointment.patient_name}</td>
                <td>${appointment.doctor_name}</td>
                <td><span class="priority-badge priority-${appointment.priority}">${appointment.priority}</span></td>
                <td><span class="risk-score">${appointment.health_risk_score}/10</span></td>
                <td><span class="status-badge status-${appointment.status}">${appointment.status}</span></td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="assistant.viewAppointment(${appointment.id})">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-secondary" onclick="assistant.rescheduleAppointment(${appointment.id})">
                        <i class="fas fa-calendar-alt"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
    }

    async optimizeSchedule() {
        try {
            this.showLoading('optimize-schedule');
            
            const response = await fetch('api/optimize_schedule.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(this.optimizationSettings)
            });

            const result = await response.json();
            
            if (result.success) {
                this.showNotification('Schedule optimized successfully!', 'success');
                this.loadAppointments();
                this.showOptimizationResults(result.optimizations);
            } else {
                this.showNotification('Optimization failed: ' + result.message, 'error');
            }
        } catch (error) {
            console.error('Error optimizing schedule:', error);
            this.showNotification('Error optimizing schedule', 'error');
        } finally {
            this.hideLoading('optimize-schedule');
        }
    }

    showOptimizationResults(optimizations) {
        const modal = document.getElementById('optimization-modal');
        const resultsDiv = document.getElementById('optimization-results');
        
        resultsDiv.innerHTML = `
            <div class="optimization-summary">
                <h3>Optimization Results</h3>
                <div class="result-stats">
                    <div class="stat">
                        <span class="stat-label">Appointments Rescheduled:</span>
                        <span class="stat-value">${optimizations.rescheduled || 0}</span>
                    </div>
                    <div class="stat">
                        <span class="stat-label">Average Waiting Time Reduced:</span>
                        <span class="stat-value">${optimizations.waitingTimeReduction || 0} minutes</span>
                    </div>
                    <div class="stat">
                        <span class="stat-label">Priority Adjustments:</span>
                        <span class="stat-value">${optimizations.priorityAdjustments || 0}</span>
                    </div>
                </div>
            </div>
        `;
        
        modal.style.display = 'block';
    }

    async rescheduleAppointment(appointmentId) {
        try {
            const response = await fetch('api/reschedule_appointment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ appointmentId })
            });

            const result = await response.json();
            
            if (result.success) {
                this.showNotification('Appointment rescheduled successfully!', 'success');
                this.loadAppointments();
            } else {
                this.showNotification('Rescheduling failed: ' + result.message, 'error');
            }
        } catch (error) {
            console.error('Error rescheduling appointment:', error);
            this.showNotification('Error rescheduling appointment', 'error');
        }
    }

    async viewAppointment(appointmentId) {
        try {
            const response = await fetch(`api/get_appointment_details.php?id=${appointmentId}`);
            const appointment = await response.json();
            
            const modal = document.getElementById('appointment-modal');
            const detailsDiv = document.getElementById('appointment-details');
            
            detailsDiv.innerHTML = `
                <div class="appointment-detail">
                    <h3>Appointment Details</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <label>Patient:</label>
                            <span>${appointment.patient_name}</span>
                        </div>
                        <div class="detail-item">
                            <label>Doctor:</label>
                            <span>${appointment.doctor_name}</span>
                        </div>
                        <div class="detail-item">
                            <label>Date & Time:</label>
                            <span>${this.formatDateTime(appointment.appointment_time)}</span>
                        </div>
                        <div class="detail-item">
                            <label>Priority:</label>
                            <span class="priority-badge priority-${appointment.priority}">${appointment.priority}</span>
                        </div>
                        <div class="detail-item">
                            <label>Health Risk Score:</label>
                            <span>${appointment.health_risk_score}/10</span>
                        </div>
                        <div class="detail-item">
                            <label>Status:</label>
                            <span class="status-badge status-${appointment.status}">${appointment.status}</span>
                        </div>
                        <div class="detail-item">
                            <label>Notes:</label>
                            <span>${appointment.notes || 'No notes available'}</span>
                        </div>
                    </div>
                </div>
            `;
            
            modal.style.display = 'block';
        } catch (error) {
            console.error('Error loading appointment details:', error);
            this.showNotification('Error loading appointment details', 'error');
        }
    }

    async updateDashboardData() {
        try {
            const response = await fetch('api/get_dashboard_data.php');
            const data = await response.json();
            
            document.getElementById('today-appointments').textContent = data.todayAppointments || 0;
            document.getElementById('urgent-cases').textContent = data.urgentCases || 0;
            document.getElementById('avg-waiting').textContent = (data.avgWaitingTime || 0) + ' min';
            document.getElementById('notifications-count').textContent = data.notificationsCount || 0;
        } catch (error) {
            console.error('Error updating dashboard data:', error);
        }
    }

    initializeCharts() {
        // Appointment Distribution Chart
        const appointmentCtx = document.getElementById('appointment-chart').getContext('2d');
        this.charts.appointment = new Chart(appointmentCtx, {
            type: 'doughnut',
            data: {
                labels: ['Scheduled', 'In Progress', 'Completed', 'Cancelled'],
                datasets: [{
                    data: [0, 0, 0, 0],
                    backgroundColor: ['#667eea', '#f093fb', '#43e97b', '#e53e3e']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Waiting Time Chart
        const waitingCtx = document.getElementById('waiting-time-chart').getContext('2d');
        this.charts.waiting = new Chart(waitingCtx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Average Waiting Time (minutes)',
                    data: [],
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Priority Distribution Chart
        const priorityCtx = document.getElementById('priority-chart').getContext('2d');
        this.charts.priority = new Chart(priorityCtx, {
            type: 'bar',
            data: {
                labels: ['Critical', 'High', 'Medium', 'Low'],
                datasets: [{
                    label: 'Appointments',
                    data: [0, 0, 0, 0],
                    backgroundColor: ['#e53e3e', '#dd6b20', '#2b6cb0', '#38a169']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    updateCharts() {
        // Update appointment distribution
        const statusCounts = this.appointments.reduce((acc, apt) => {
            acc[apt.status] = (acc[apt.status] || 0) + 1;
            return acc;
        }, {});

        this.charts.appointment.data.datasets[0].data = [
            statusCounts.scheduled || 0,
            statusCounts['in-progress'] || 0,
            statusCounts.completed || 0,
            statusCounts.cancelled || 0
        ];
        this.charts.appointment.update();

        // Update priority distribution
        const priorityCounts = this.appointments.reduce((acc, apt) => {
            acc[apt.priority] = (acc[apt.priority] || 0) + 1;
            return acc;
        }, {});

        this.charts.priority.data.datasets[0].data = [
            priorityCounts.critical || 0,
            priorityCounts.high || 0,
            priorityCounts.medium || 0,
            priorityCounts.low || 0
        ];
        this.charts.priority.update();
    }

    startRealTimeUpdates() {
        // Update dashboard every 30 seconds
        setInterval(() => {
            this.updateDashboardData();
        }, 30000);

        // Check for notifications every 10 seconds
        setInterval(() => {
            this.checkNotifications();
        }, 10000);
    }

    async checkNotifications() {
        try {
            const response = await fetch('api/check_notifications.php');
            const notifications = await response.json();
            
            notifications.forEach(notification => {
                this.showNotification(notification.message, notification.type);
            });
        } catch (error) {
            console.error('Error checking notifications:', error);
        }
    }

    async refreshData() {
        this.showLoading('refresh-data');
        await this.loadAppointments();
        await this.updateDashboardData();
        this.hideLoading('refresh-data');
        this.showNotification('Data refreshed successfully!', 'success');
    }

    showLoading(elementId) {
        const element = document.getElementById(elementId);
        const originalText = element.innerHTML;
        element.innerHTML = '<span class="loading"></span> Loading...';
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
        notification.className = `notification ${type}`;
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

    getNotificationIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    }

    formatTime(timeString) {
        const date = new Date(timeString);
        return date.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit',
            hour12: true 
        });
    }

    formatDateTime(timeString) {
        const date = new Date(timeString);
        return date.toLocaleString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
    }
}

// Initialize the assistant when the page loads
let assistant;
document.addEventListener('DOMContentLoaded', () => {
    assistant = new SmartSchedulingAssistant();
});
