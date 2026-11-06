document.addEventListener('alpine:init', () => {
    Alpine.data('diabetesCalculator', (recipeId) => ({
        ingredients: [],
        totalXE: 0,
        totalGL: 0,
        recommendation: '',
        loading: true,
        
        async init() {
            await this.loadIngredients();
        },
        
        async loadIngredients() {
            try {
                const res = await fetch(`/api/get_product_gi.php?recipe_id=${recipeId}`);
                const data = await res.json();
                
                if (data.ok) {
                    this.ingredients = data.ingredients;
                    this.calculate();
                }
            } catch (e) {
                console.error('Error loading GI data:', e);
                this.ingredients = [];
            } finally {
                this.loading = false;
            }
        },
        
        calculate() {
            this.totalXE = this.ingredients.reduce((sum, ing) => sum + (ing.xe || 0), 0);
            this.totalGL = this.ingredients.reduce((sum, ing) => sum + (ing.gl || 0), 0);
            this.setRecommendation();
        },
        
        setRecommendation() {
            if (this.totalGL < 10) {
                this.recommendation = '✅ Низкая гликемическая нагрузка. Блюдо хорошо подходит для людей с диабетом.';
            } else if (this.totalGL < 20) {
                this.recommendation = '⚠️ Средняя гликемическая нагрузка. Рекомендуется умеренное употребление и контроль порции.';
            } else {
                this.recommendation = '❌ Высокая гликемическая нагрузка. Рекомендуется адаптировать рецепт, заменив продукты с высоким ГИ.';
            }
        },
        
        get glClassification() {
            if (this.totalGL < 10) return 'Низкая ГН';
            if (this.totalGL < 20) return 'Средняя ГН';
            return 'Высокая ГН';
        },
        
        get ingredientsWithXE() {
            return this.ingredients.filter(ing => ing.xe > 0);
        }
    }));
});