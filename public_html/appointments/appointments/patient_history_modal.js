/**
 * Patient consultation history modal (table UI). Requires window.HB_PATIENT_HISTORY_API (relative URL to patient_history_data.php).
 */
(function () {
    function truncate(str, max) {
        if (!str) return '—';
        var s = String(str).replace(/\s+/g, ' ').trim();
        if (s.length <= max) return s;
        return s.slice(0, max - 1) + '…';
    }

    function bindModal(apiUrl) {
        var modal = document.getElementById('patientHistoryModal');
        var btnClose = document.getElementById('patientHistoryModalClose');
        var titleEl = document.getElementById('patientHistoryModalTitle');
        var subEl = document.getElementById('patientHistoryModalSubtitle');
        var loading = document.getElementById('patientHistoryLoading');
        var errBox = document.getElementById('patientHistoryError');
        var emptyBox = document.getElementById('patientHistoryEmpty');
        var tableWrap = document.getElementById('patientHistoryTableWrap');
        var tbody = document.getElementById('patientHistoryTableBody');

        if (!modal || !tbody || !apiUrl) return;

        function makeTd(htmlCls, text, titleFull) {
            var cell = document.createElement('td');
            if (htmlCls) cell.className = htmlCls;
            if (titleFull) cell.setAttribute('title', titleFull);
            cell.textContent = text === undefined || text === '' ? '—' : String(text);
            return cell;
        }

        function makeFollowUpTd(value) {
            var cell = document.createElement('td');
            cell.className = 'ph-nowrap';
            var raw = value === undefined || value === null ? '' : String(value).trim();
            if (raw === '' || raw === '—') {
                cell.textContent = '—';
                return cell;
            }

            var pill = document.createElement('span');
            pill.className = 'ph-followup-pill';
            pill.textContent = raw;
            cell.appendChild(pill);
            return cell;
        }

        function showState(state) {
            if (loading) loading.hidden = state !== 'loading';
            if (errBox) errBox.hidden = state !== 'error';
            if (emptyBox) emptyBox.hidden = state !== 'empty';
            if (tableWrap) tableWrap.hidden = state !== 'table';
        }

        function closeModal() {
            modal.classList.remove('is-open');
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            tbody.innerHTML = '';
            if (errBox) errBox.textContent = '';
        }

        function openModal(patientId) {
            var id = parseInt(patientId, 10);
            if (!id) return;

            modal.classList.add('is-open');
            modal.style.display = '';
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            if (titleEl) titleEl.textContent = 'Consultation history';
            if (subEl) subEl.textContent = '';
            tbody.innerHTML = '';
            showState('loading');

            var url = apiUrl + (apiUrl.indexOf('?') >= 0 ? '&' : '?') + 'patient_id=' + encodeURIComponent(id);
            fetch(url, { credentials: 'same-origin' })
                .then(function (r) {
                    return r.json().then(function (data) {
                        return { ok: r.ok, data: data };
                    });
                })
                .then(function (result) {
                    if (!result.ok || !result.data || !result.data.ok) {
                        var msg = (result.data && result.data.error) ? result.data.error : 'Could not load history.';
                        showState('error');
                        if (errBox) errBox.textContent = msg;
                        return;
                    }
                    var d = result.data;
                    if (subEl) subEl.textContent = d.patient_name ? d.patient_name : '';

                    var list = d.consultations || [];
                    if (list.length === 0) {
                        showState('empty');
                        return;
                    }

                    showState('table');
                    list.forEach(function (c) {
                        var tr = document.createElement('tr');
                        var cc = truncate(c.chief_complaint, 100);
                        var dg = truncate(c.diagnosis, 100);
                        var tp = truncate(c.treatment_plan, 80);
                        var rxRaw = c.rx_details ? String(c.rx_details).trim() : '';
                        var rxText = rxRaw !== '' ? truncate(rxRaw, 120) : (c.rx_count > 0 ? (String(c.rx_count) + ' item(s)') : '—');

                        tr.appendChild(makeTd('ph-nowrap', c.visit_display));
                        tr.appendChild(makeTd('', c.doctor_name));
                        tr.appendChild(makeTd('', c.specialization || '—'));
                        tr.appendChild(makeTd('ph-cell-long', cc, c.chief_complaint || ''));
                        tr.appendChild(makeTd('ph-cell-long', dg, c.diagnosis || ''));
                        tr.appendChild(makeTd('ph-cell-long', tp, c.treatment_plan || ''));
                        tr.appendChild(makeTd('ph-cell-long', rxText, rxRaw));
                        tr.appendChild(makeFollowUpTd(c.follow_up_display));
                        tbody.appendChild(tr);
                    });
                })
                .catch(function () {
                    showState('error');
                    if (errBox) errBox.textContent = 'Network error loading history.';
                });

            if (btnClose) btnClose.focus();
        }

        document.querySelectorAll('.js-open-patient-history').forEach(function (el) {
            el.addEventListener('click', function () {
                openModal(el.getAttribute('data-patient-id'));
            });
        });

        window.HB_openPatientHistory = function (patientId) {
            openModal(patientId);
        };

        if (btnClose) btnClose.addEventListener('click', closeModal);
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
        });
    }

    var api = window.HB_PATIENT_HISTORY_API;
    if (!api) {
        console.warn('HB_PATIENT_HISTORY_API not set; patient history modal disabled.');
        return;
    }
    bindModal(api);
})();
