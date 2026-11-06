/**
 * GigaChefAI — AI-помощник по рецепту (плавающая кнопка)
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('recipeAssistant', (recipeId) => ({
        open: false,
        messages: [],
        input: '',
        loading: false,
        
        async send() {
            const text = this.input.trim();
            if (!text || this.loading) return;
            
            this.messages.push({ role: 'user', text: text });
            this.input = '';
            this.loading = true;
            
            try {
                // Формируем историю (последние 10 сообщений)
                const history = this.messages.slice(-10).map(m => ({
                    role: m.role,
                    text: m.text
                }));
                
                const response = await fetch('/api/ai_recipe_chat.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        recipe_id: recipeId,
                        message: text,
                        history: history
                    })
                });
                
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.error || `HTTP ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.ok && result.reply) {
                    this.messages.push({ role: 'assistant', text: result.reply });
                } else {
                    throw new Error(result.error || 'Пустой ответ');
                }
            } catch (e) {
                console.error('Recipe assistant error:', e);
                this.messages.push({ 
                    role: 'assistant', 
                    text: 'Извините, произошла ошибка. Попробуйте позже. 😔' 
                });
            } finally {
                this.loading = false;
                
                // Прокрутка вниз
                this.$nextTick(() => {
                    const scrollEl = this.$refs.scroll;
                    if (scrollEl) {
                        scrollEl.scrollTop = scrollEl.scrollHeight;
                    }
                });
            }
        }
    }));
});