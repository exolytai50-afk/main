/**
 * GigaChefAI — замена ингредиентов (используется в recipe_detail и как standalone)
 */
function ingredientSubstitute(dishName = '') {
    return {
        dish: dishName,
        ingredient: '',
        reason: '',
        results: [],
        loading: false,
        error: '',
        open: false,

        async find() {
            if (!this.ingredient.trim()) { this.error = 'Укажите ингредиент.'; return; }
            this.error = '';
            this.loading = true;
            this.results = [];
            try {
                const res = await CookAPI.post('/api/ai_substitute.php', {
                    ingredient: this.ingredient,
                    reason: this.reason,
                    dish: this.dish
                });
                this.results = res.substitutes || [];
                if (!this.results.length) this.error = 'Замены не найдены.';
            } catch (e) {
                this.error = e.message || 'Ошибка запроса.';
            } finally {
                this.loading = false;
            }
        }
    };
}