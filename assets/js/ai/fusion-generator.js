document.addEventListener('alpine:init', () => {
    Alpine.data('fusionGenerator', () => ({
        cuisines: [
            'Русская', 'Итальянская', 'Японская', 'Мексиканская', 'Индийская',
            'Тайская', 'Французская', 'Грузинская', 'Китайская', 'Корейская',
            'Американская', 'Средиземноморская', 'Вьетнамская', 'Турецкая'
        ],
        a: 'Итальянская',
        b: 'Японская',
        recipe: null,
        loading: false,
        saving: false,
        saved: false,
        savedUrl: '',
        error: '',
        
        async generate() {
            if (!this.a || !this.b) {
                this.error = 'Выберите обе кухни';
                return;
            }
            
            if (this.a === this.b) {
                this.error = 'Выберите разные кухни';
                return;
            }
            
            this.loading = true;
            this.error = '';
            this.recipe = null;
            this.saved = false;
            
            try {
                const response = await fetch('/api/ai_fusion.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        cuisine_a: this.a,
                        cuisine_b: this.b
                    })
                });
                
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.error || `HTTP ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.ok && result.recipe) {
                    this.recipe = result.recipe;
                } else if (result.recipe) {
                    this.recipe = result.recipe;
                } else {
                    throw new Error(result.error || 'Не удалось сгенерировать рецепт');
                }
            } catch (e) {
                console.error('Fusion error:', e);
                this.error = e.message || 'Ошибка при генерации';
            } finally {
                this.loading = false;
            }
        },
        
        async saveRecipe() {
            if (!this.recipe) return;
            
            this.saving = true;
            
            try {
                const response = await fetch('/api/save_recipe.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        title: this.recipe.title,
                        description: this.recipe.description,
                        cuisine: this.recipe.cuisine || this.a + ' + ' + this.b,
                        difficulty: this.recipe.difficulty || 'Средне',
                        prep_time: this.recipe.prep_time || 15,
                        cook_time: this.recipe.cook_time || 30,
                        servings: this.recipe.servings || 2,
                        calories: this.recipe.calories || 0,
                        proteins: this.recipe.proteins || 0,
                        fats: this.recipe.fats || 0,
                        carbs: this.recipe.carbs || 0,
                        ingredients: this.recipe.ingredients || [],
                        steps: this.recipe.steps || [],
                        tags: ['fusion', this.a.toLowerCase(), this.b.toLowerCase()],
                        is_ai_generated: 1
                    })
                });
                
                const result = await response.json();
                
                if (result.ok) {
                    this.saved = true;
                    this.savedUrl = '/recipe/' + (result.slug || result.id);
                } else {
                    throw new Error(result.error || 'Ошибка сохранения');
                }
            } catch (e) {
                console.error('Save error:', e);
                this.error = e.message || 'Ошибка при сохранении';
            } finally {
                this.saving = false;
            }
        }
    }));
});