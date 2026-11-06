function culinaryAdvisor() {
    return {
        messages: [
            // emoji убираем — SVG вставим через renderMessage по маркеру
            { role: 'assistant', text: 'Привет! Я ваш кулинарный AI-ассистент {{chef}} Спросите меня о рецептах, заменах продуктов или советах по готовке.' }
        ],
        suggestions: [ /* ... без изменений ... */ ],
        input: '',
        loading: false,

        // === SVG-иконки, встраиваемые в текст ===
        
        svgMap: {
            chef: window.__SVG_CHEF__ || '',
            warn: window.__SVG_WARN__ || '',
            spark: window.__SVG_SPARK__ || ''
        },

        escapeHtml(s) {
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        },

        // {{chef}}, {{warn}}, {{spark}} → SVG; остальное экранируем
        renderMessage(text) {
            let safe = this.escapeHtml(text);
            // важно: маркеры {{...}} не содержат < >, поэтому escapeHtml их не тронет
            return safe.replace(/\{\{(\w+)\}\}/g, (m, key) =>
                this.svgMap[key] || m
            );
        },

        async send() {
            const text = this.input.trim();
            if (!text || this.loading) return;
            this.messages.push({ role: 'user', text });
            this.input = '';
            this.loading = true;
            this.scrollDown();

            try {
                const history = this.messages
                    .filter(m => m.role !== 'assistant' || m !== this.messages[0])
                    .slice(-8)
                    .map(m => ({ role: m.role, text: m.text }));

                const res = await CookAPI.post('/api/ai_chat.php', {
                    message: text,
                    history: history.slice(0, -1)
                });
                this.messages.push({ role: 'assistant', text: res.reply });
            } catch (e) {
                this.messages.push({ role: 'assistant', text: '{{warn}} ' + (e.message || 'Не удалось получить ответ. Попробуйте ещё раз.') });
            } finally {
                this.loading = false;
                this.scrollDown();
            }
        },

        scrollDown() {
            this.$nextTick(() => {
                const el = this.$refs.scroll;
                if (el) el.scrollTop = el.scrollHeight;
            });
        }
    };
}