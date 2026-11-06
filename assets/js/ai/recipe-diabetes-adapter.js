document.addEventListener('alpine:init', () => {
    Alpine.data('diabetesAdapter', (recipeId) => ({
        loading: false,
        error: '',
        success: '',

        /**
         * Создать или пересоздать адаптацию рецепта
         * @param {boolean} force - принудительно пересоздать, если уже существует
         */
        async createAdaptation(force = false) {
            this.loading = true;
            this.error = '';
            this.success = '';

            try {
                const res = await fetch('/api/ai_diabetes_adapt.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': window.__CSRF_TOKEN__ || ''
                    },
                    body: JSON.stringify({
                        recipe_id: Number(recipeId),
                        diabetes_type: 'type2',
                        force: !!force
                    })
                });

                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.ok) {
                    throw new Error(data.error || 'Ошибка создания адаптации');
                }

                this.success = 'Адаптация создана!';
                if (typeof toast === 'function') {
                    toast(this.success, 'success');
                }
                // Даём время увидеть сообщение и перезагружаем страницу
                setTimeout(() => window.location.reload(), 1000);
            } catch (e) {
                this.error = e.message || 'Ошибка соединения';
                if (typeof toast === 'function') {
                    toast(this.error, 'error');
                }
            } finally {
                this.loading = false;
            }
        },

        /**
         * Удалить все адаптации рецепта
         */
        async resetAllAdaptations() {
            if (!confirm('Удалить адаптацию для этого рецепта?')) return;

            this.loading = true;
            this.error = '';
            this.success = '';

            try {
                const res = await fetch('/api/reset_diabetes_adaptations.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': window.__CSRF_TOKEN__ || ''
                    },
                    body: JSON.stringify({ recipe_id: Number(recipeId) })
                });

                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.ok) {
                    throw new Error(data.error || 'Ошибка удаления адаптации');
                }

                this.success = 'Адаптация удалена';
                if (typeof toast === 'function') {
                    toast(this.success, 'success');
                }
                setTimeout(() => window.location.reload(), 1000);
            } catch (e) {
                this.error = e.message || 'Ошибка';
                if (typeof toast === 'function') {
                    toast(this.error, 'error');
                }
            } finally {
                this.loading = false;
            }
        }
    }));
});