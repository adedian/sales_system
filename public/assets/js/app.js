(function () {
    'use strict';

    /* ------------------------------------------------------------------
     * Toast notifications
     * ---------------------------------------------------------------- */
    const Toast = {
        icons: {
            success: 'bi-check-circle-fill',
            danger: 'bi-exclamation-triangle-fill',
            warning: 'bi-exclamation-circle-fill',
            info: 'bi-info-circle-fill',
        },
        show(message, type = 'info', duration = 4000) {
            const stack = document.getElementById('toastStack');
            if (!stack) return;

            const item = document.createElement('div');
            item.className = `toast-item ${type}`;
            item.innerHTML = `
                <i class="bi ${this.icons[type] || this.icons.info}"></i>
                <span>${this.escape(message)}</span>
                <button type="button" class="toast-close" aria-label="Tutup">&times;</button>
            `;
            stack.appendChild(item);

            const remove = () => {
                item.style.opacity = '0';
                setTimeout(() => item.remove(), 150);
            };

            item.querySelector('.toast-close').addEventListener('click', remove);
            if (duration > 0) setTimeout(remove, duration);
        },
        escape(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        },
    };
    window.Toast = Toast;

    /* ------------------------------------------------------------------
     * Confirm dialog (wraps the shared Bootstrap modal in #confirmModal)
     * ---------------------------------------------------------------- */
    function confirmDialog(message, options = {}) {
        return new Promise((resolve) => {
            const modalEl = document.getElementById('confirmModal');
            if (!modalEl || typeof bootstrap === 'undefined') {
                resolve(window.confirm(message));
                return;
            }

            modalEl.querySelector('#confirmModalTitle').textContent = options.title || 'Konfirmasi';
            modalEl.querySelector('#confirmModalBody').textContent = message;

            const okBtn = modalEl.querySelector('#confirmModalOk');
            okBtn.textContent = options.confirmText || 'Ya, Lanjutkan';
            okBtn.className = `btn ${options.confirmClass || 'btn-danger'}`;

            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            let settled = false;

            const onOk = () => {
                settled = true;
                modal.hide();
                resolve(true);
            };
            const onHidden = () => {
                okBtn.removeEventListener('click', onOk);
                modalEl.removeEventListener('hidden.bs.modal', onHidden);
                if (!settled) resolve(false);
            };

            okBtn.addEventListener('click', onOk);
            modalEl.addEventListener('hidden.bs.modal', onHidden);
            modal.show();
        });
    }
    window.confirmDialog = confirmDialog;

    /* ------------------------------------------------------------------
     * API fetch wrapper — attaches CSRF header and normalizes errors
     * ---------------------------------------------------------------- */
    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    const Api = {
        async request(url, opts = {}) {
            const headers = Object.assign(
                {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                opts.headers || {}
            );

            if (opts.body && !(opts.body instanceof FormData)) {
                headers['Content-Type'] = 'application/json';
            }

            let response;
            try {
                response = await fetch(url, Object.assign({}, opts, { headers }));
            } catch (err) {
                Toast.show('Tidak dapat terhubung ke server.', 'danger');
                throw err;
            }

            if (!response.ok) {
                Toast.show(`Permintaan gagal (${response.status}).`, 'danger');
                throw new Error(`Request failed with status ${response.status}`);
            }

            const contentType = response.headers.get('content-type') || '';
            return contentType.includes('application/json') ? response.json() : response.text();
        },
        get(url) {
            return this.request(url, { method: 'GET' });
        },
        post(url, data) {
            return this.request(url, { method: 'POST', body: JSON.stringify(data || {}) });
        },
    };
    window.Api = Api;

    /* ------------------------------------------------------------------
     * Sidebar toggle (mobile / tablet off-canvas)
     * ---------------------------------------------------------------- */
    const sidebar = document.getElementById('appSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const toggleBtn = document.getElementById('sidebarToggle');

    function closeSidebar() {
        sidebar?.classList.remove('open');
        backdrop?.classList.remove('show');
    }

    toggleBtn?.addEventListener('click', () => {
        sidebar?.classList.toggle('open');
        backdrop?.classList.toggle('show');
    });
    backdrop?.addEventListener('click', closeSidebar);

    /* ------------------------------------------------------------------
     * Password visibility toggle
     * ---------------------------------------------------------------- */
    document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.getAttribute('data-toggle-password'));
            if (!input) return;
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            btn.querySelector('i')?.classList.toggle('bi-eye');
            btn.querySelector('i')?.classList.toggle('bi-eye-slash');
        });
    });

    /* ------------------------------------------------------------------
     * Generic "confirm before submit" for destructive actions
     * (deactivate user, delete role, etc.) — add data-confirm="message"
     * to any <form>.
     * ---------------------------------------------------------------- */
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === 'true') return;
            event.preventDefault();

            confirmDialog(form.getAttribute('data-confirm')).then((ok) => {
                if (ok) {
                    form.dataset.confirmed = 'true';
                    form.submit();
                }
            });
        });
    });

    /* ------------------------------------------------------------------
     * Dashboard live polling demo (foundation for Phase 2+ realtime UI)
     * ---------------------------------------------------------------- */
    const liveEl = document.querySelector('[data-live="active_users"]');
    if (liveEl) {
        const pollInterval = parseInt(
            document.querySelector('meta[name="poll-interval"]')?.getAttribute('content') || '30000',
            10
        );

        const base = (window.APP_BASE_URL || '').replace(/\/+$/, '');

        const refresh = () => {
            Api.get(base + '/api/dashboard/summary')
                .then((data) => {
                    liveEl.classList.remove('skeleton');
                    liveEl.textContent = data.active_users;
                })
                .catch(() => {
                    /* keep last known value on transient failure */
                });
        };

        refresh();
        setInterval(refresh, Math.max(pollInterval, 10000));
    }

    /* ------------------------------------------------------------------
     * Notification bell (topbar) — polled, no websockets in this stack.
     * Backed by NotificationApiController; see app/Models/Notification.php.
     * ---------------------------------------------------------------- */
    (function () {
        var bell = document.getElementById('notifBell');
        var badge = document.getElementById('notifBadge');
        var list = document.getElementById('notifList');
        var markAllBtn = document.getElementById('notifMarkAllRead');
        if (!bell || !badge || !list) return;

        var base = (window.APP_BASE_URL || '').replace(/\/+$/, '');
        var notifPollInterval = parseInt(
            document.querySelector('meta[name="poll-interval"]')?.getAttribute('content') || '30000',
            10
        );

        function timeAgo(datetime) {
            var diff = Math.floor((Date.now() - new Date(datetime.replace(' ', 'T'))) / 1000);
            if (diff < 60) return 'baru saja';
            if (diff < 3600) return Math.floor(diff / 60) + ' menit lalu';
            if (diff < 86400) return Math.floor(diff / 3600) + ' jam lalu';
            return Math.floor(diff / 86400) + ' hari lalu';
        }

        function refreshBadge() {
            Api.get(base + '/api/notifications/summary').then(function (data) {
                badge.classList.toggle('d-none', !(data && data.unread_count > 0));
            }).catch(function () {});
        }

        function loadList() {
            Api.get(base + '/api/notifications').then(function (data) {
                var items = data.notifications || [];
                if (!items.length) {
                    list.innerHTML = '<div class="empty-state py-4"><i class="bi bi-bell-slash"></i><p class="mb-0">Belum ada notifikasi.</p></div>';
                    return;
                }
                list.innerHTML = items.map(function (n) {
                    var cls = 'notif-item' + (n.is_read ? '' : ' notif-item-unread');
                    var href = n.link ? (base + n.link) : '#';
                    return '<a href="' + href + '" class="' + cls + '" data-notif-id="' + n.id + '">' +
                        '<div class="notif-item-title">' + Toast.escape(n.title) + '</div>' +
                        (n.message ? '<div class="notif-item-message">' + Toast.escape(n.message) + '</div>' : '') +
                        '<div class="notif-item-time">' + timeAgo(n.created_at) + '</div>' +
                        '</a>';
                }).join('');
            }).catch(function () {});
        }

        bell.addEventListener('click', loadList);

        list.addEventListener('click', function (event) {
            var item = event.target.closest('[data-notif-id]');
            if (!item) return;

            Api.post(base + '/api/notifications/' + item.getAttribute('data-notif-id') + '/read', {}).then(function (data) {
                badge.classList.toggle('d-none', !(data && data.unread_count > 0));
            }).catch(function () {});
        });

        if (markAllBtn) {
            markAllBtn.addEventListener('click', function () {
                Api.post(base + '/api/notifications/read-all', {}).then(function () {
                    badge.classList.add('d-none');
                    list.querySelectorAll('.notif-item-unread').forEach(function (el) {
                        el.classList.remove('notif-item-unread');
                    });
                }).catch(function () {});
            });
        }

        refreshBadge();
        setInterval(refreshBadge, Math.max(notifPollInterval, 10000));
    })();
})();
