/* ===============================
   APPOINTMENTS PAGE JAVASCRIPT
   Modern Clinic Theme
=============================== */

document.addEventListener('DOMContentLoaded', function () {
    initializeCalendar();
    initializeDropdowns();
    initializeApptDetailModal();
});

function escHtml(s) {
    if (s == null || s === undefined) return '';
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

function statusLabel(st) {
    if (!st) return '—';
    return st.charAt(0).toUpperCase() + String(st).slice(1).toLowerCase();
}

/** Split created into date + time for spotlight (fallback parses created_label). */
function getCreatedParts(d) {
    if (
        d.created_date_label &&
        d.created_time_label &&
        (d.created_date_label !== '—' || d.created_time_label !== '—')
    ) {
        return { date: d.created_date_label, time: d.created_time_label };
    }
    var cl = d.created_label;
    if (!cl || cl === '—') return { date: '—', time: '—' };
    var m = String(cl).match(/^(.+?)\s+at\s+(.+)$/i);
    if (m) return { date: m[1].trim(), time: m[2].trim() };
    return { date: cl, time: '' };
}

function spotlightCard(modClass, iconClass, label, primaryHtml, secondaryHtml) {
    return (
        '<div class="appt-detail-spotlight ' +
        modClass +
        '">' +
        '<div class="appt-detail-spotlight-icon" aria-hidden="true"><i class="' +
        iconClass +
        '"></i></div>' +
        '<div class="appt-detail-spotlight-body">' +
        '<span class="appt-detail-spotlight-label">' +
        escHtml(label) +
        '</span>' +
        '<span class="appt-detail-spotlight-primary">' +
        primaryHtml +
        '</span>' +
        (secondaryHtml
            ? '<span class="appt-detail-spotlight-time"><i class="far fa-clock"></i> ' + secondaryHtml + '</span>'
            : '') +
        '</div></div>'
    );
}

function renderApptDetailBody(d, meta) {
    meta = meta || {};
    var sug = meta.suggestedStart;
    var isFu = !!meta.isFollowupCalendar;
    var cr = getCreatedParts(d);
    var heroClass = 'appt-detail-hero-grid' + (isFu && sug ? ' appt-detail-hero-grid--3' : ' appt-detail-hero-grid--2');
    var hero = '<div class="appt-detail-hero">';

    if (isFu && sug) {
        hero +=
            '<div class="appt-detail-banner"><i class="fas fa-sync-alt"></i> <strong>Follow-up</strong> — suggested from the consultation plan. Times below help you coordinate with the patient.</div>';
        hero += '<div class="' + heroClass + '">';
        hero += spotlightCard(
            'appt-detail-spotlight--suggested',
            'fas fa-calendar-plus',
            'Suggested follow-up slot',
            escHtml(
                sug.toLocaleDateString(undefined, {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                })
            ),
            escHtml(
                sug.toLocaleTimeString(undefined, {
                    hour: 'numeric',
                    minute: '2-digit',
                })
            )
        );
        hero += spotlightCard(
            'appt-detail-spotlight--visit',
            'fas fa-calendar-check',
            'Original visit',
            escHtml(d.date_label),
            escHtml(d.time_label)
        );
        hero += spotlightCard(
            'appt-detail-spotlight--created',
            'fas fa-clipboard-list',
            'Appointment created',
            escHtml(cr.date),
            cr.time && cr.time !== '—' ? escHtml(cr.time) : ''
        );
        hero += '</div></div>';
    } else {
        hero += '<div class="' + heroClass + '">';
        hero += spotlightCard(
            'appt-detail-spotlight--visit',
            'fas fa-calendar-day',
            'Scheduled visit',
            escHtml(d.date_label),
            escHtml(d.time_label)
        );
        hero += spotlightCard(
            'appt-detail-spotlight--created',
            'fas fa-history',
            'Appointment created',
            escHtml(cr.date),
            cr.time && cr.time !== '—' ? escHtml(cr.time) : ''
        );
        hero += '</div></div>';
    }

    var statusLower = String(d.status || '').toLowerCase();
    var canOpenConsultation = statusLower === 'confirmed' || statusLower === 'pending' || statusLower === 'completed';
    var consultationHref = 'consultation_form.php?appointment_id=' + encodeURIComponent(String(d.id || ''));
    var consultationAction =
        '<div class="appt-detail-section">' +
        '<h4 class="appt-detail-section-title"><i class="fas fa-stethoscope"></i> Consultation</h4>' +
        '<div class="appt-detail-action-wrap">' +
        (canOpenConsultation
            ? '<a href="' +
              consultationHref +
              '" class="appt-detail-action-btn">' +
              '<i class="fas fa-file-medical"></i> ' +
              (d.has_ehr ? 'Open consultation record' : 'Start consultation form') +
              '</a>'
            : '<span class="appt-detail-action-note"><i class="fas fa-info-circle"></i> Consultation is unavailable for ' +
              escHtml(statusLabel(d.status)) +
              ' appointments.</span>') +
        '</div></div>';

    var rows =
        hero +
        '<div class="appt-detail-section">' +
        '<h4 class="appt-detail-section-title"><i class="fas fa-hashtag"></i> Reference &amp; status</h4>' +
        '<div class="appt-detail-rows">' +
        '<div class="appt-detail-row"><span class="appt-detail-k">Reference</span><span class="appt-detail-v">#' +
        escHtml(d.id) +
        '</span></div>' +
        '<div class="appt-detail-row"><span class="appt-detail-k">Status</span><span class="appt-detail-v"><span class="appt-detail-status-pill">' +
        escHtml(statusLabel(d.status)) +
        '</span></span></div>' +
        '</div></div>' +
        '<div class="appt-detail-section">' +
        '<h4 class="appt-detail-section-title"><i class="fas fa-user-injured"></i> Patient</h4>' +
        '<div class="appt-detail-rows">' +
        '<div class="appt-detail-row"><span class="appt-detail-k">Patient to be seen</span><span class="appt-detail-v">' +
        escHtml(d.visit_patient) +
        '</span></div>' +
        '<div class="appt-detail-row"><span class="appt-detail-k">Gender</span><span class="appt-detail-v">' +
        escHtml(d.gender) +
        '</span></div>' +
        '<div class="appt-detail-row"><span class="appt-detail-k">Health concern</span><span class="appt-detail-v">' +
        escHtml(d.health_concern) +
        '</span></div>' +
        '</div></div>' +
        '<div class="appt-detail-section">' +
        '<h4 class="appt-detail-section-title"><i class="fas fa-file-medical"></i> Records</h4>' +
        '<div class="appt-detail-rows">' +
        '<div class="appt-detail-row"><span class="appt-detail-k">Booked by</span><span class="appt-detail-v">' +
        escHtml(d.booked_by) +
        '</span></div>' +
        '<div class="appt-detail-row"><span class="appt-detail-k">EHR on file</span><span class="appt-detail-v">' +
        (d.has_ehr
            ? '<span class="appt-detail-pill appt-detail-pill-ok">Yes</span>'
            : '<span class="appt-detail-pill appt-detail-pill-warn">No</span>') +
        '</span></div>' +
        '</div></div>' +
        consultationAction;

    return rows;
}

function openApptDetailModal(appointmentId, calendarEvent) {
    var modal = document.getElementById('apptDetailModal');
    var body = document.getElementById('apptDetailModalBody');
    var titleEl = document.getElementById('apptDetailModalTitle');
    var subEl = document.getElementById('apptDetailModalSubtitle');
    if (!modal || !body || !titleEl) return;

    if (!appointmentId || appointmentId <= 0) {
        titleEl.textContent = 'Calendar event';
        if (subEl) subEl.textContent = 'Quick preview — link this visit in the lists below for full details.';
        if (calendarEvent && calendarEvent.start) {
            var st = calendarEvent.start;
            body.innerHTML =
                '<div class="appt-detail-hero"><div class="appt-detail-hero-grid appt-detail-hero-grid--1">' +
                spotlightCard(
                    'appt-detail-spotlight--visit',
                    'fas fa-calendar-alt',
                    'Starts',
                    escHtml(
                        st.toLocaleDateString(undefined, {
                            weekday: 'long',
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric',
                        })
                    ),
                    escHtml(
                        st.toLocaleTimeString(undefined, {
                            hour: 'numeric',
                            minute: '2-digit',
                        })
                    )
                ) +
                '</div></div>' +
                '<div class="appt-detail-section"><h4 class="appt-detail-section-title"><i class="fas fa-tag"></i> Title</h4><div class="appt-detail-rows">' +
                '<div class="appt-detail-row"><span class="appt-detail-k">Event</span><span class="appt-detail-v">' +
                escHtml(calendarEvent.title || '') +
                '</span></div></div></div>';
        } else {
            body.innerHTML =
                '<p class="appt-detail-empty"><i class="fas fa-info-circle"></i> No appointment ID is attached to this calendar entry. Use the tables below or refresh the page.</p>';
        }
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        return;
    }

    var map = window.DOCTOR_APPT_DETAILS || {};
    var d = map[String(appointmentId)] || map[appointmentId];

    if (!d) {
        titleEl.textContent = 'Appointment details';
        if (subEl) subEl.textContent = 'Could not load record — try refreshing.';
        body.innerHTML =
            '<p class="appt-detail-empty"><i class="fas fa-exclamation-circle"></i> Details are not available for this calendar entry. Try refreshing the page.</p>';
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        return;
    }

    var ep = (calendarEvent && calendarEvent.extendedProps) || {};
    var isFollowupCal = ep.type === 'followup' && calendarEvent && calendarEvent.start;
    titleEl.textContent = 'Appointment details';
    if (subEl) {
        subEl.textContent = isFollowupCal
            ? 'Follow-up suggestion — original visit & booking info below.'
            : statusLabel(d.status) + ' · Ref #' + d.id;
    }

    body.innerHTML = renderApptDetailBody(d, {
        isFollowupCalendar: isFollowupCal,
        suggestedStart: isFollowupCal ? calendarEvent.start : null,
    });

    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}

function closeApptDetailModal() {
    var modal = document.getElementById('apptDetailModal');
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

function initializeApptDetailModal() {
    var closeBtn = document.getElementById('apptDetailModalClose');
    var backdrop = document.getElementById('apptDetailModalBackdrop');
    if (closeBtn) closeBtn.addEventListener('click', closeApptDetailModal);
    if (backdrop) backdrop.addEventListener('click', closeApptDetailModal);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var m = document.getElementById('apptDetailModal');
            if (m && m.classList.contains('is-open')) closeApptDetailModal();
        }
    });

    document.addEventListener('click', function (e) {
        var tr = e.target.closest('.js-appt-table-row');
        if (!tr) return;
        if (e.target.closest('button, a, form, input')) return;
        var id = parseInt(tr.getAttribute('data-appt-id'), 10);
        if (id) openApptDetailModal(id, null);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        var tr = e.target.closest('.js-appt-table-row');
        if (!tr || tr !== document.activeElement) return;
        e.preventDefault();
        var id = parseInt(tr.getAttribute('data-appt-id'), 10);
        if (id) openApptDetailModal(id, null);
    });
}

function initializeCalendar() {
    var calendarEl = document.getElementById('calendar');
    if (calendarEl) {
        var events = (window.calendarEvents || []).map(function (event, idx) {
            var aid =
                event.appointmentId != null && event.appointmentId !== undefined
                    ? parseInt(event.appointmentId, 10)
                    : NaN;
            var hasAid = !isNaN(aid) && aid > 0;
            return {
                id: hasAid ? 'hb-appt-' + aid : 'hb-cal-' + idx,
                title: event.title,
                start: event.start,
                color: event.color || '#3b82f6',
                textColor: event.textColor || '#ffffff',
                extendedProps: {
                    type: event.type || 'confirmed',
                    hasEhr: event.hasEhr === true || event.hasEhr === 1,
                    originalAppointmentDate: event.originalAppointmentDate || null,
                    appointmentId: hasAid ? aid : null,
                },
            };
        });

        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay',
            },
            events: events,
            eventDisplay: 'block',
            height: 'auto',
            dayMaxEvents: 4,
            moreLinkClick: 'popover',
            eventClick: function (info) {
                if (info.jsEvent) {
                    info.jsEvent.preventDefault();
                    info.jsEvent.stopPropagation();
                }
                var aid = null;
                if (typeof info.event.getExtendedProp === 'function') {
                    aid = info.event.getExtendedProp('appointmentId');
                }
                if (aid == null || aid === '') {
                    var ep = info.event.extendedProps || {};
                    aid = ep.appointmentId;
                }
                if (aid == null || aid === '' || isNaN(parseInt(aid, 10))) {
                    var eid = info.event.id;
                    if (eid && String(eid).indexOf('hb-appt-') === 0) {
                        aid = parseInt(String(eid).replace(/^hb-appt-/, ''), 10);
                    }
                }
                var num =
                    aid != null && aid !== '' && !isNaN(parseInt(aid, 10)) ? parseInt(aid, 10) : 0;
                openApptDetailModal(num, info.event);
            },
        });

        calendar.render();
    }
}

function initializeDropdowns() {
    var notifIcon = document.querySelector('.notification-icon');
    var notifDropdown = document.querySelector('.notif-dropdown');
    var notifications = document.querySelector('.notifications');

    var profileIcon = document.querySelector('.profile-icon');
    var profileDropdown = document.querySelector('.profile-dropdown');
    var profile = document.querySelector('.profile');

    if (notifIcon && notifDropdown) {
        notifIcon.addEventListener('click', function (e) {
            e.stopPropagation();
            notifDropdown.classList.toggle('show');
            if (profileDropdown) profileDropdown.classList.remove('show');
        });
    }

    if (profileIcon && profileDropdown) {
        profileIcon.addEventListener('click', function (e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
            if (notifDropdown) notifDropdown.classList.remove('show');
        });
    }

    document.addEventListener('click', function (e) {
        if (notifDropdown && notifications && !notifications.contains(e.target)) {
            notifDropdown.classList.remove('show');
        }
        if (profileDropdown && profile && !profile.contains(e.target)) {
            profileDropdown.classList.remove('show');
        }
    });
}

function formatDate(date) {
    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(date));
}

function showLoading(element) {
    element.innerHTML = '<div class="loading-spinner">Loading...</div>';
}

function hideLoading(element, originalContent) {
    element.innerHTML = originalContent;
}

window.AppointmentsJS = {
    initializeCalendar,
    initializeDropdowns,
    formatDate,
    showLoading,
    hideLoading,
    openApptDetailModal,
    closeApptDetailModal,
};
