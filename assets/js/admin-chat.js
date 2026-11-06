document.addEventListener('alpine:init', () => {
    Alpine.data('adminChat', () => ({
        open: false,
        messages: [],
        input: '',
        sending: false,
        loading: false,
        unread: 0,
        _pollTimer: null,
        _unreadTimer: null,

        get statusText() {
            return this.messages.length === 0
                ? 'Обычно отвечает в течение дня'
                : 'Диалог открыт';
        },

        init() {
            // НЕ вызывайте this.load() при init — только когда чат открыт
            this.$watch('open', (val) => {
                if (val) {
                    this.load();
                    this.startPolling();
                } else {
                    this.stopPolling();
                }
            });

            this.checkUnread();
            this._unreadTimer = setInterval(() => this.checkUnread(), 30000);
        },

        startPolling() {
            this.stopPolling();
            this._pollTimer = setInterval(() => this.load(true), 3000);
        },

        stopPolling() {
            if (this._pollTimer) {
                clearInterval(this._pollTimer);
                this._pollTimer = null;
            }
        },

        async load(silent = false) {
            if (!silent) this.loading = true;
            try {
                const res = await fetch('/api/admin_chat.php?action=list', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                if (data.ok) {
                    const oldLen = this.messages.length;
                    this.messages = data.messages || [];
                    if (this.messages.length !== oldLen) this.scrollDown();
                }
            } catch (e) {
                console.error('adminChat load:', e);
            } finally {
                this.loading = false;
            }
        },

        async checkUnread() {
            try {
                const res = await fetch('/api/admin_chat.php?action=unread', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                if (data.ok) this.unread = data.unread || 0;
            } catch (e) {}
        },

        async send() {
            const text = this.input.trim();
            if (!text || this.sending) return;

            this.sending = true;

            // Оптимистично показываем
            this.messages.push({ is_admin: false, text });
            this.input = '';
            this.scrollDown();

            try {
                const fd = new FormData();
                fd.append('message', text);

                const res = await fetch('/api/admin_chat.php?action=send', {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();

                if (data.ok) {
                    // Подгружаем актуальный список из БД
                    await this.load(true);
                } else {
                    this.messages.push({ is_admin: true, error: true, text: 'Ошибка: ' + (data.error || 'не удалось отправить') });
                }
            } catch (e) {
                console.error('adminChat send:', e);
                this.messages.push({ is_admin: true, error: true, text: 'Ошибка соединения' });
            } finally {
                this.sending = false;
            }
        },

        scrollDown() {
            this.$nextTick(() => {
                const el = this.$refs.scroll;
                if (el) el.scrollTop = el.scrollHeight;
            });
        }
    }));
});
