/* Excursio — small vanilla JS helpers. No framework, no build step. */
(function () {
    'use strict';

    /* ---- Hero search: switch between Stays / Dining / Activities ---------- */
    document.querySelectorAll('[data-ex-search]').forEach(function (root) {
        var tabs = root.querySelectorAll('[role="tab"]');
        var panes = root.querySelectorAll('.ex-search-pane');

        function select(tab) {
            tabs.forEach(function (t) {
                var active = t === tab;
                t.setAttribute('aria-selected', active ? 'true' : 'false');
                t.tabIndex = active ? 0 : -1;
            });
            panes.forEach(function (pane) {
                var active = pane.id === tab.getAttribute('aria-controls');
                pane.hidden = !active;
                // Disabled fields are not submitted, so each form only sends its own category's fields.
                pane.querySelectorAll('input, select, button').forEach(function (el) { el.disabled = !active; });
            });
        }

        tabs.forEach(function (tab, i) {
            tab.addEventListener('click', function () { select(tab); });
            tab.addEventListener('keydown', function (e) {
                if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
                var next = tabs[(i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
                next.focus();
                select(next);
            });
        });

        var initial = root.querySelector('[role="tab"][aria-selected="true"]') || tabs[0];
        if (initial) select(initial);
    });

    /* ---- Date pairs: check-out must be after check-in --------------------- */
    document.querySelectorAll('[data-ex-date-start]').forEach(function (start) {
        var end = document.getElementById(start.getAttribute('data-ex-date-start'));
        if (!end) return;
        function sync() {
            if (!start.value) return;
            var p = start.value.split('-').map(Number);
            var d = new Date(Date.UTC(p[0], p[1] - 1, p[2] + 1));   // UTC avoids timezone day-shifts
            var min = d.toISOString().slice(0, 10);
            end.min = min;
            if (!end.value || end.value < min) end.value = min;
        }
        start.addEventListener('change', sync);
    });

    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content;

    function esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function getJson(url) {
        return fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) {
                return r.json().catch(function () { return {}; }).then(function (body) { return { ok: r.ok, status: r.status, body: body }; });
            });
    }

    function errorText(res) {
        if (res.status === 429) return 'Too many requests — please wait a moment and try again.';
        var b = res.body || {};
        if (b.errors) { var k = Object.keys(b.errors)[0]; if (k) return b.errors[k][0] === 'Invalid length of stay.' || b.errors[k][0] === 'Party too large.' ? b.message : b.errors[k][0]; }
        return b.message || 'Something went wrong. Please try again.';
    }

    /* Booking buttons under each room / time. Only IDs, dates and counts are sent: the server prices everything. */
    function actionHtml(form, fields, opts) {
        var auth = form.dataset.auth;
        if (auth === 'guest') return '<a class="btn btn-sm btn-ex-primary w-100 mt-2" href="' + esc(form.dataset.login) + '">Sign in to book</a>';
        if (auth !== 'customer') return '<div class="small text-muted mt-2">Booking is available from guest accounts.</div>';
        function hidden(extra) {
            var h = '<input type="hidden" name="_token" value="' + esc(csrf) + '">';
            Object.keys(fields).concat(Object.keys(extra || {})).forEach(function (k) {
                var v = k in fields ? fields[k] : extra[k];
                h += '<input type="hidden" name="' + esc(k) + '" value="' + esc(v) + '">';
            });
            return h;
        }
        if (opts && opts.waitlist) {
            return '<form method="POST" action="' + esc(form.dataset.waitlist.replace('__ID__', opts.waitlist)) + '" class="mt-2">' + hidden() +
                '<button class="btn btn-sm btn-ex-outline w-100"><i class="bi bi-list-ol me-1"></i>Join waitlist</button></form>';
        }
        return '<div class="d-flex gap-2 mt-2">' +
            '<form method="POST" action="' + esc(form.dataset.book) + '" class="flex-grow-1">' + hidden({ idempotency_key: form.dataset.key + '-' + (fields.stay_unit_id || fields.time_slot_id) }) +
            '<button class="btn btn-sm btn-ex-primary w-100">' + esc(opts && opts.label || 'Reserve') + '</button></form>' +
            '<form method="POST" action="' + esc(form.dataset.trip) + '">' + hidden() +
            '<button class="btn btn-sm btn-ex-outline" title="Add to trip cart"><i class="bi bi-bag-plus"></i><span class="visually-hidden">Add to trip</span></button></form></div>';
    }

    /* ---- Sort dropdown submits itself ------------------------------------ */
    document.querySelectorAll('[data-ex-autosubmit] select').forEach(function (sel) {
        sel.addEventListener('change', function () { sel.form.submit(); });
    });

    /* ---- Wishlist hearts: instant toggle, form fallback without JS ------- */
    document.querySelectorAll('[data-ex-wishlist]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = form.querySelector('button');
            btn.disabled = true;
            fetch(form.action, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { if (!r.ok) throw r; return r.json(); })
                .then(function (data) {
                    btn.classList.toggle('is-saved', data.saved);
                    btn.setAttribute('aria-pressed', data.saved ? 'true' : 'false');
                    btn.querySelector('i').className = 'bi ' + (data.saved ? 'bi-heart-fill' : 'bi-heart');
                    btn.setAttribute('aria-label', (data.saved ? 'Remove from saved' : 'Save'));
                })
                .catch(function () { form.submit(); })
                .finally(function () { btn.disabled = false; });
        });
    });

    /* ---- Stay availability ---------------------------------------------- */
    document.querySelectorAll('[data-ex-stay]').forEach(function (form) {
        var out = form.parentElement.querySelector('.ex-avail-result');
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!form.check_in.value || !form.check_out.value) { out.innerHTML = '<div class="text-danger small">Please choose check-in and check-out dates.</div>'; return; }
            out.innerHTML = '<div class="small text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Checking rooms…</div>';
            getJson(form.dataset.exStay + '?' + new URLSearchParams(new FormData(form))).then(function (res) {
                if (!res.ok) { out.innerHTML = '<div class="text-danger small">' + esc(errorText(res)) + '</div>'; return; }
                var d = res.body, html = '<div class="small text-muted mb-2">' + d.nights + ' night' + (d.nights > 1 ? 's' : '') + '</div><div class="d-grid gap-2">';
                d.units.forEach(function (u) {
                    html += '<div class="ex-unit ' + (u.available ? '' : 'is-off') + '"><div class="d-flex justify-content-between gap-2"><div><div class="fw-semibold">' + esc(u.name) + '</div><div class="small text-muted">' + esc(u.beds || '') + ' · sleeps ' + u.max_guests + '</div></div>';
                    if (u.available) {
                        html += '<div class="text-end"><div class="fw-semibold">' + esc(u.quote.total) + '</div><div class="small text-muted">total</div></div></div>';
                        html += '<div class="small mt-1 ' + (u.rooms_left <= 2 ? 'ex-scarce' : 'text-muted') + '">' + (u.rooms_left <= 2 ? 'Only ' + u.rooms_left + ' left' : u.rooms_left + ' available') + '</div>';
                        html += '<details class="small mt-1"><summary>Price breakdown</summary><ul class="list-unstyled mb-0 mt-1">';
                        u.quote.nights.forEach(function (n) { html += '<li class="d-flex justify-content-between"><span>' + esc(n.date) + (n.label ? ' · ' + esc(n.label) : '') + '</span><span>' + esc(n.price) + '</span></li>'; });
                        if (u.quote.fees) html += '<li class="d-flex justify-content-between"><span>Service fee</span><span>' + esc(u.quote.fees) + '</span></li>';
                        html += '<li class="d-flex justify-content-between fw-semibold border-top mt-1 pt-1"><span>Total</span><span>' + esc(u.quote.total) + '</span></li></ul></details>';
                        html += actionHtml(form, { stay_unit_id: u.id, check_in: form.check_in.value, check_out: form.check_out.value, rooms: form.rooms.value || 1, guests: form.guests.value }, { label: 'Reserve' });
                    } else {
                        html += '</div><div class="small text-muted mt-1">' + esc(u.reason) + '</div>';
                    }
                    html += '</div>';
                });
                out.innerHTML = html + '</div>';
            }).catch(function () { out.innerHTML = '<div class="text-danger small">Could not check availability. Please try again.</div>'; });
        });
    });

    /* ---- Dining / activity time slots ----------------------------------- */
    document.querySelectorAll('[data-ex-slots]').forEach(function (form) {
        var out = form.parentElement.querySelector('.ex-avail-result');
        var unit = form.dataset.unit || 'places';
        function load() {
            out.innerHTML = '<div class="small text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Finding times…</div>';
            getJson(form.dataset.exSlots + '?' + new URLSearchParams(new FormData(form))).then(function (res) {
                if (!res.ok) { out.innerHTML = '<div class="text-danger small">' + esc(errorText(res)) + '</div>'; return; }
                var d = res.body, html = '';
                if (d.weather) html += '<div class="ex-weather-note"><i class="bi ' + esc(d.weather.icon) + ' me-1"></i>Forecast: ' + esc(d.weather.label) + ', ' + d.weather.max + '° / ' + d.weather.min + '°' + (d.weather.rain !== null ? ' · ' + d.weather.rain + '% rain' : '') + '</div>';
                if (!d.slots.length) { out.innerHTML = html + '<div class="small text-muted">No times left on this date. Try another day.</div>'; return; }
                html += '<div class="ex-slots" role="list">';
                d.slots.forEach(function (s) {
                    html += '<button type="button" role="listitem" class="ex-slot" ' + (s.available ? '' : 'disabled') + ' data-total="' + esc(s.quote.total) + '" data-unit="' + esc(s.quote.unit_price) + '" data-id="' + s.id + '" data-time="' + esc(s.time) + '" data-free="' + (s.quote.total_paise === 0 ? '1' : '0') + '">'
                        + '<span class="fw-semibold">' + esc(s.time) + '</span><small>' + (s.remaining === 0 ? 'Full' : (s.available ? s.remaining + ' ' + unit + ' left' : 'Only ' + s.remaining + ' left')) + '</small></button>';
                });
                out.innerHTML = html + '</div><div class="ex-slot-quote small mt-2"></div><div class="ex-slot-actions"></div>';
                var full = d.slots.filter(function (s) { return !s.available; });
                if (full.length && form.dataset.auth === 'customer') {
                    var wl = '<details class="small mt-2"><summary>Full? Join a waitlist</summary><div class="mt-2">';
                    full.forEach(function (s) { wl += '<div class="d-flex justify-content-between align-items-center gap-2"><span>' + esc(s.time) + '</span>' + actionHtml(form, { guests: form.guests.value }, { waitlist: s.id }) + '</div>'; });
                    out.insertAdjacentHTML('beforeend', wl + '</div></details>');
                }
                out.querySelectorAll('.ex-slot:not([disabled])').forEach(function (b) {
                    b.addEventListener('click', function () {
                        out.querySelectorAll('.ex-slot').forEach(function (x) { x.classList.remove('is-active'); });
                        b.classList.add('is-active');
                        out.querySelector('.ex-slot-actions').innerHTML = actionHtml(form, { time_slot_id: b.dataset.id, guests: form.guests.value }, { label: b.dataset.free === '1' ? 'Reserve table' : 'Book this time' });
                        out.querySelector('.ex-slot-quote').innerHTML = b.dataset.free === '1'
                            ? '<div><strong>' + esc(b.dataset.time) + '</strong> · no deposit — pay at the restaurant</div>'
                            : '<div class="d-flex justify-content-between"><span>' + esc(b.dataset.time) + ' · ' + esc(b.dataset.unit) + ' × ' + esc(form.guests.value) + '</span><strong>' + esc(b.dataset.total) + '</strong></div>';
                    });
                });
            }).catch(function () { out.innerHTML = '<div class="text-danger small">Could not load times. Please try again.</div>'; });
        }
        form.addEventListener('submit', function (e) { e.preventDefault(); load(); });
        if (form.date.value) load();
    });

    /* ---- Checkout hold countdown ------------------------------------------ */
    document.querySelectorAll('[data-ex-countdown]').forEach(function (el) {
        var end = new Date(el.dataset.exCountdown).getTime(), text = el.querySelector('[data-ex-countdown-text]');
        (function tick() {
            var left = Math.max(0, Math.round((end - Date.now()) / 1000));
            text.textContent = Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0');
            if (left <= 60) el.classList.add('is-ending');
            if (left === 0) { el.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i>Your hold expired — the place has been released.'; return; }
            setTimeout(tick, 1000);
        })();
    });

    /* ---- Map (Leaflet + OpenStreetMap) ---------------------------------- */
    window.addEventListener('load', function () {
        var el = document.getElementById('ex-map');
        if (!el || typeof L === 'undefined') return;
        var pos = [parseFloat(el.dataset.lat), parseFloat(el.dataset.lng)];
        var map = L.map(el, { scrollWheelZoom: false }).setView(pos, 13);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        L.circle(pos, { radius: 600, color: '#243D35', fillColor: '#87988A', fillOpacity: .25, weight: 1.5 }).addTo(map);
    });

    /* ---- QR check-in code (F3) ------------------------------------------- */
    window.addEventListener('load', function () {
        if (typeof qrcode === 'undefined') return;
        document.querySelectorAll('[data-qr]').forEach(function (el) {
            var qr = qrcode(0, 'M');
            qr.addData(el.dataset.qr);
            qr.make();
            el.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
        });
    });

    /* ---- Host QR scanner -------------------------------------------------- */
    window.addEventListener('load', function () {
        var box = document.getElementById('ex-scanner'), start = document.getElementById('ex-scan-start');
        if (!box || !start) return;
        var msg = document.getElementById('ex-scan-msg'), form = document.getElementById('ex-checkin-form');
        if (typeof Html5Qrcode === 'undefined') { start.disabled = true; msg.textContent = 'The scanner could not load. Type the reference instead.'; return; }
        var scanner = new Html5Qrcode('ex-scanner'), busy = false;
        start.addEventListener('click', function () {
            start.disabled = true;
            scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: 220 }, function (text) {
                if (busy) return;
                busy = true;
                msg.textContent = 'Code found — opening booking…';
                scanner.stop().finally(function () {
                    // Submit through the same server-side lookup as typed codes (no client-side trust).
                    form.code.value = text;
                    form.submit();
                });
            }).then(function () { msg.textContent = 'Point the camera at the guest’s QR code.'; })
              .catch(function () { start.disabled = false; msg.textContent = 'Camera unavailable or permission denied. Type the reference instead.'; });
        });
    });

    /* ---- Host weekly schedule rows ---------------------------------------- */
    document.querySelectorAll('#ex-schedule').forEach(function (form) {
        var body = form.querySelector('[data-rows]');
        function renumber() {
            body.querySelectorAll('[data-row]').forEach(function (row, i) {
                row.querySelectorAll('[name]').forEach(function (el) { el.name = el.name.replace(/rules\[\d+\]/, 'rules[' + i + ']'); });
            });
        }
        form.addEventListener('click', function (e) {
            var remove = e.target.closest('[data-remove]');
            if (remove && body.querySelectorAll('[data-row]').length > 1) { remove.closest('[data-row]').remove(); renumber(); }
            if (e.target.closest('[data-add]')) {
                var last = body.querySelector('[data-row]:last-child'), copy = last.cloneNode(true);
                copy.querySelector('select').value = (parseInt(last.querySelector('select').value, 10) + 1) % 7;
                body.appendChild(copy);
                renumber();
            }
        });
    });

    /* ---- Small behaviours that used to be inline handlers (CSP: no inline JS) ---- */
    document.addEventListener('change', function (e) {
        if (e.target.matches('[data-autosubmit]')) e.target.form.submit();
    });
    document.addEventListener('submit', function (e) {
        var msg = e.target.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) e.preventDefault();
    });
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-print]')) window.print();
    });
})();
