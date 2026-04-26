// Real-time Doctor Availability Checker
class DoctorAvailabilityChecker {
    constructor() {
        this.refreshInterval = 30000; // 30 seconds
        this.isActive = false;
        this.currentDoctorId = null;
        this.currentDate = null;
    }

    // Start real-time checking for a specific doctor and date
    startChecking(doctorId, date) {
        this.currentDoctorId = doctorId;
        this.currentDate = date;
        this.isActive = true;
        
        // Initial check
        this.checkAvailability();
        
        // Set up interval
        this.intervalId = setInterval(() => {
            if (this.isActive) {
                this.checkAvailability();
            }
        }, this.refreshInterval);
    }

    // Stop real-time checking
    stopChecking() {
        this.isActive = false;
        if (this.intervalId) {
            clearInterval(this.intervalId);
        }
    }

    // Check availability via API
    async checkAvailability() {
        if (!this.currentDoctorId || !this.currentDate) {
            return;
        }

        try {
            const response = await fetch(`api/doctor_availability.php?doctor_id=${this.currentDoctorId}&date=${this.currentDate}`);
            const data = await response.json();

            if (data.success) {
                this.updateAvailabilityDisplay(data);
                this.updateStatistics(data.statistics);
            } else {
                console.error('Availability check failed:', data.error);
            }
        } catch (error) {
            console.error('Error checking availability:', error);
        }
    }

    // Update the availability display
    updateAvailabilityDisplay(data) {
        const timeSlotsContainer = document.querySelector('.time-slots-grid');
        if (!timeSlotsContainer) return;

        // Update each time slot
        data.time_slots.forEach(slot => {
            const slotElement = timeSlotsContainer.querySelector(`[data-hour="${slot.hour}"]`);
            if (slotElement) {
                slotElement.className = `time-slot ${slot.available ? 'available' : 'booked'}`;
                
                if (slot.available) {
                    slotElement.onclick = () => this.bookAppointment(data.doctor.id, data.date, slot.hour);
                } else {
                    slotElement.onclick = null;
                }
            }
        });

        // Update availability status
        const statusElement = document.querySelector('.availability-status');
        if (statusElement) {
            statusElement.className = `availability-status ${data.is_available ? 'status-available' : 'status-busy'}`;
            statusElement.innerHTML = `
                <span class="status-indicator ${data.is_available ? 'indicator-available' : 'indicator-busy'}"></span>
                ${data.is_available ? 'Available' : 'Busy'}
            `;
        }

        // Update book button
        const bookButton = document.querySelector('.book-appointment-btn');
        if (bookButton) {
            if (data.is_available) {
                bookButton.style.display = 'block';
                bookButton.onclick = () => this.bookAppointment(data.doctor.id, data.date);
            } else {
                bookButton.style.display = 'none';
            }
        }
    }

    // Update statistics display
    updateStatistics(stats) {
        const statElements = document.querySelectorAll('.stat-number');
        if (statElements.length >= 3) {
            statElements[0].textContent = stats.available_slots;
            statElements[1].textContent = stats.booked_slots;
            statElements[2].textContent = stats.total_slots;
        }
    }

    // Book appointment
    bookAppointment(doctorId, date, hour = null) {
        let url = '/appointments/scheduling.php?';
        url += `doctor_id=${doctorId}`;
        url += `&date=${date}`;
        if (hour) {
            url += `&hour=${hour}`;
        }
        window.location.href = url;
    }

    // Get next available appointment
    async getNextAvailable(doctorId) {
        try {
            const today = new Date().toISOString().split('T')[0];
            const response = await fetch(`api/doctor_availability.php?doctor_id=${doctorId}&date=${today}`);
            const data = await response.json();

            if (data.success && data.next_available) {
                return {
                    doctor: data.doctor,
                    next_slot: data.next_available,
                    date: data.date
                };
            }
            return null;
        } catch (error) {
            console.error('Error getting next available:', error);
            return null;
        }
    }

    // Check all doctors' availability for today
    async checkAllDoctorsAvailability(date = null) {
        if (!date) {
            date = new Date().toISOString().split('T')[0];
        }

        try {
            const response = await fetch(`api/all_doctors_availability.php?date=${date}`);
            const data = await response.json();

            if (data.success) {
                return data.doctors;
            }
            return [];
        } catch (error) {
            console.error('Error checking all doctors availability:', error);
            return [];
        }
    }
}

// Global instance
window.doctorAvailabilityChecker = new DoctorAvailabilityChecker();

// Auto-start checking when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on a doctor availability page
    if (document.querySelector('.doctor-card')) {
        // Start checking for each doctor card
        const doctorCards = document.querySelectorAll('.doctor-card');
        doctorCards.forEach(card => {
            const doctorId = card.dataset.doctorId;
            const date = document.getElementById('date')?.value || new Date().toISOString().split('T')[0];
            
            if (doctorId) {
                window.doctorAvailabilityChecker.startChecking(doctorId, date);
            }
        });
    }
});

// Utility functions
function formatTime(hour) {
    const displayHour = hour > 12 ? hour - 12 : hour;
    const ampm = hour >= 12 ? 'PM' : 'AM';
    return `${displayHour}:00 ${ampm}`;
}

function getAvailabilityStatus(availableSlots, totalSlots) {
    const percentage = (availableSlots / totalSlots) * 100;
    
    if (percentage >= 70) return 'excellent';
    if (percentage >= 40) return 'good';
    if (percentage >= 20) return 'limited';
    return 'busy';
}

function getStatusColor(status) {
    const colors = {
        'excellent': '#10b981',
        'good': '#3b82f6',
        'limited': '#f59e0b',
        'busy': '#ef4444'
    };
    return colors[status] || '#64748b';
}
