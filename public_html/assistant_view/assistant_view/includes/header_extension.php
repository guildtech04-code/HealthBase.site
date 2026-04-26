<!-- Header Extension for Time/Date Display -->
<div class="assistant-header-right">
    <div class="current-time" style="display: flex; align-items: center; gap: 8px; padding: 8px 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
        <i class="fas fa-clock" style="color: #3b82f6;"></i>
        <span id="currentDateTime" style="color: #1e293b; font-weight: 600; font-size: 14px;"></span>
    </div>
</div>

<script>
// Update date and time every second
function updateDateTime() {
    const now = new Date();
    const options = { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit', 
        minute: '2-digit',
        second: '2-digit',
        hour12: true 
    };
    const datetimeElement = document.getElementById('currentDateTime');
    if (datetimeElement) {
        datetimeElement.textContent = now.toLocaleString('en-US', options);
    }
}

// Update immediately and then every second
updateDateTime();
setInterval(updateDateTime, 1000);
</script>

