// assets/js/social/meal-planner.js
function mealPlanner(recipes) {
    return {
        recipes,
        days: ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'].map(name => ({
            name,
            meals: [
                { label: 'Завтрак', recipeId: '' },
                { label: 'Обед', recipeId: '' },
                { label: 'Ужин', recipeId: '' }
            ]
        })),
        assign(di, mi, id) {
            this.days[di].meals[mi].recipeId = id;
            localStorage.setItem('mealPlan', JSON.stringify(this.days));
        },
        dayCalories(di) {
            return this.days[di].meals.reduce((sum, m) => {
                const r = this.recipes.find(x => String(x.id) === String(m.recipeId));
                return sum + (r ? Number(r.calories) || 0 : 0);
            }, 0);
        },
        init() {
            const saved = localStorage.getItem('mealPlan');
            if (saved) try { this.days = JSON.parse(saved); } catch (_) {}
        }
    };
}