document.addEventListener('alpine:init', () => {
    Alpine.data('adminMessages', () => ({
        conversations: [],
        messages: [],
        activeEmail: '',
        activeName: '',
        input: '',
        sending: false,
        loading: false,
        loadingMessages: false,
        search: '',
        _pollTimer: null,

        get filteredConversations() {
            const q = this.search.toLowerCase().trim();
            if (!q) return this.conversations;
            return this.conversations.filter(c =>
                (c.user_email || '').toLowerCase().includes(q) ||
                (c.display_name || '').toLowerCase().includes(q)
            );
        },

        init() {
            this.loadConversations();
            // Каждые 15 сек обновляем список + активный диалог
            this._pollTimer = setInterval(() => {
                this.loadConversations(true);
                if (this.activeEmail) this.loadMessages(true);
            }, 15000);
        },

        async loadConversations(silent = false) {
            if (!silent) this.loading = true;
            try {
                const res = await fetch('/api/admin_chat.php?action=conversations', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                if (data.ok) this.conversations = data.conversations || [];
            } catch (e) {
                console.error('adminMessages loadConversations:', e);
            } finally {
                this.loading = false;
            }
        },

        async openConversation(email) {
            this.activeEmail = email;
            const conv = this.conversations.find(c => c.user_email === email);
            this.activeName = conv?.display_name || email;
            await this.loadMessages();

            // Помечаем прочитанным
            this.conversations = this.conversations.map(c =>
                c.user_email === email ? { ...c, unread: 0 } : c
            );
        },

        async loadMessages(silent = false) {
            if (!this.activeEmail) return;
            if (!silent) this.loadingMessages = true;
            try {
                const res = await fetch('/api/admin_chat.php?action=list&user=' + encodeURIComponent(this.activeEmail), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                if (data.ok) {
                    const oldLen = this.messages.length;
                    this.messages = data.messages || [];
                    if (this.messages.length !== oldLen) this.scrollDown();
                }
            } catch (e) {
                console.error('adminMessages loadMessages:', e);
            } finally {
                this.loadingMessages = false;
            }
        },

        async send() {
            const text = this.input.trim();
            if (!text || this.sending || !this.activeEmail) return;

            this.sending = true;
            this.messages.push({ is_admin: true, text });
            this.input = '';
            this.scrollDown();

            try {
                const fd = new FormData();
                fd.append('message', text);
                fd.append('to', this.activeEmail);
                fd.append('csrf_token', window.__CSRF_TOKEN__ || '');

                const res = await fetch('/api/admin_chat.php?action=send', {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                if (data.ok) {
                    await this.loadMessages(true);
                    await this.loadConversations(true);
                } else {
                    this.messages.push({ is_admin: false, error: true, text: 'Ошибка: ' + (data.error || 'не удалось отправить') });
                }
            } catch (e) {
                this.messages.push({ is_admin: false, error: true, text: 'Ошибка соединения' });
            } finally {
                this.sending = false;
            }
        },

        scrollDown() {
            this.$nextTick(() => {
                const el = this.$refs.scroll;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },

        formatTime(iso) {
            if (!iso) return '';
            try {
                const d = new Date(iso.replace(' ', 'T'));
                const now = new Date();
                const diff = (now - d) / 1000;
                if (diff < 60) return 'только что';
                if (diff < 3600) return Math.floor(diff / 60) + ' мин назад';
                if (diff < 86400) return Math.floor(diff / 3600) + ' ч назад';
                return d.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit' });
            } catch { return iso; }
        }
    }));
});