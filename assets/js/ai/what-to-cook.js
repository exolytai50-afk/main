/**
 * GigaChef — «Что приготовить?» по ингредиентам
 */
function whatToCook() {
    return {
        // ─── Основные поля ───
        ingredientInput: '',
        ingredients: [],
        popular: ['Яйца', 'Курица', 'Картофель', 'Помидоры', 'Сыр', 'Рис', 'Макароны', 'Лук', 'Морковь', 'Молоко'],
        diet: '',
        time: '',
        dishes: [],
        loading: false,
        error: '',

        // ─── Пакетная генерация ───
        generatedRecipes: [],
        generating: false,
        generatingProgress: { current: 0, total: 0 },

        // ─── Фото холодильника ───
        fridgeImage: null,
        scanningFridge: false,
        fridgeError: '',
        showMobileMenu: false,

        // ─── Лимиты AI ───
        ai: {},

        // ─── Реклама ───
        aiAdHtml: window.__AI_AD_HTML__ || '',
        adTimer: 0,

        init() {
            fetch('/api/ai_status.php?feature=calorie')
                .then(r => r.json())
                .then(r => { this.ai = r.ai || {}; })
                .catch(() => {});
        },

        // Добавление ингредиентов
        addIngredient() {
            const v = this.ingredientInput.trim();
            if (v && !this.ingredients.includes(v)) this.ingredients.push(v);
            this.ingredientInput = '';
        },
        quickAdd(p) {
            if (!this.ingredients.includes(p)) this.ingredients.push(p);
        },

        // Подбор блюд
        async find() {
            if (!this.ingredients.length) return;
            this.error = '';
            this.loading = true;
            this.dishes = [];
            this.generatedRecipes = [];
            this.adTimer = 0;

            const startTime = Date.now();
            const interval = setInterval(() => { this.adTimer++; }, 1000);

            let fetchedDishes = [];

            try {
                const res = await CookAPI.post('/api/ai_what_to_cook.php', {
                    ingredients: this.ingredients,
                    diet: this.diet,
                    time: this.time
                });
                
                fetchedDishes = (res.dishes || []).map(d => ({ ...d, selected: false }));
                
                if (!fetchedDishes.length) {
                    this.error = 'Не нашлось подходящих блюд. Добавьте больше продуктов.';
                }
            } catch (e) {
                this.error = e.message || 'Ошибка подбора.';
            }

            // Ждем минимум 10 секунд
            const elapsed = (Date.now() - startTime) / 1000;
            if (elapsed < 10) {
                await new Promise(r => setTimeout(r, (10 - elapsed) * 1000));
            }
            clearInterval(interval);

            this.loading = false;
            this.adTimer = 0;
            this.dishes = fetchedDishes;

            this.$nextTick(() => window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' }));
        },

        get selectedCount() {
            return this.dishes.filter(d => d.selected).length;
        },

        // Пакетная генерация и сохранение
        async generateSelected() {
            const selected = this.dishes.filter(d => d.selected);
            if (selected.length === 0) return;

            this.generating = true;
            this.generatingProgress = { current: 0, total: selected.length };
            this.generatedRecipes = [];
            this.error = '';
            this.adTimer = 0;

            const startTime = Date.now();
            const interval = setInterval(() => { this.adTimer++; }, 1000);

            let tempRecipes = [];

            for (let i = 0; i < selected.length; i++) {
                const dish = selected[i];
                this.generatingProgress.current = i + 1;

                try {
                    const genRes = await CookAPI.post('/api/ai_generate_recipe.php', {
                        dish: dish.title,
                        cuisine: '',
                        diet: this.diet,
                        difficulty: '',
                        servings: 2,
                        exclude: '',
                        tags: {},
                        count: 1
                    });

                    if (genRes.recipes && genRes.recipes.length > 0) {
                        const recipe = genRes.recipes[0];
                        
                        try {
                            const saveRes = await CookAPI.post('/api/save_recipe.php', {
                                ...recipe,
                                is_ai_generated: true
                            });
                            
                            if (saveRes.ok) {
                                recipe.saved = true;
                                recipe.slug = saveRes.slug;
                            } else if (saveRes.duplicate) {
                                recipe.saved = true;
                                recipe.slug = saveRes.redirect_url.split('/').pop();
                                recipe.title += ' (уже есть)';
                            } else {
                                recipe.saved = false;
                            }
                        } catch (saveErr) {
                            console.error('Save error:', saveErr);
                            recipe.saved = false;
                        }

                        tempRecipes.push(recipe);
                    }
                } catch (e) {
                    console.error('Generation error for', dish.title, e);
                    this.error = 'Некоторые блюда не удалось сгенерировать.';
                }
            }

            // Ждем минимум 10 секунд
            const elapsed = (Date.now() - startTime) / 1000;
            if (elapsed < 10) {
                await new Promise(r => setTimeout(r, (10 - elapsed) * 1000));
            }
            clearInterval(interval);

            this.generating = false;
            this.adTimer = 0;
            this.generatedRecipes = tempRecipes;

            this.$nextTick(() => {
                document.querySelector('h2[x-text*="Сгенерированные"]')?.scrollIntoView({ behavior: 'smooth' });
            });
        },

        // Ручное сохранение
        async saveGenerated(recipe) {
            if (recipe.saving || recipe.saved) return;
            recipe.saving = true;
            try {
                const res = await CookAPI.post('/api/save_recipe.php', {
                    ...recipe,
                    is_ai_generated: true
                });
                if (res.ok || res.duplicate) {
                    recipe.saved = true;
                    recipe.slug = res.slug || res.redirect_url.split('/').pop();
                } else {
                    alert('Ошибка: ' + res.error);
                }
            } catch (e) {
                alert('Ошибка соединения');
            } finally {
                recipe.saving = false;
            }
        },

        // ─── ФОТО ХОЛОДИЛЬНИКА ───
        previewFridge(e) {
            const file = e.target.files[0];
            if (!file) return;
            if (file.size > 5 * 1024 * 1024) { this.fridgeError = 'Файл больше 5 МБ'; return; }
            if (!file.type.startsWith('image/')) { this.fridgeError = 'Выберите изображение'; return; }

            this.fridgeError = '';
            const reader = new FileReader();
            reader.onload = (ev) => this.compressFridge(ev.target.result);
            reader.onerror = () => { this.fridgeError = 'Ошибка чтения файла'; };
            reader.readAsDataURL(file);
            e.target.value = '';
        },

        compressFridge(dataUrl) {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                let w = img.width, h = img.height;
                const max = 800;
                if (w > max || h > max) {
                    if (w > h) { h = Math.round(h * max / w); w = max; }
                    else { w = Math.round(w * max / h); h = max; }
                }
                canvas.width = w;
                canvas.height = h;
                canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                this.fridgeImage = canvas.toDataURL('image/jpeg', 0.75);
                this.fridgeError = '';
            };
            img.onerror = () => { this.fridgeImage = dataUrl; this.fridgeError = ''; };
            img.src = dataUrl;
        },

        clearFridge() {
            this.fridgeImage = null;
            this.fridgeError = '';
        },

        // ─── РАСПОЗНАВАНИЕ ФОТО ХОЛОДИЛЬНИКА ───
        async scanFridge() {
            if (!this.fridgeImage || this.scanningFridge) return;

            this.scanningFridge = true;
            this.fridgeError = '';
            this.adTimer = 0;

            const startTime = Date.now();
            const interval = setInterval(() => { this.adTimer++; }, 1000);

            let found = [];

            try {
                const res = await CookAPI.post('/api/ai_fridge_scan.php', { image: this.fridgeImage });
                
                if (!res.ok) throw new Error(res.error || 'Не удалось распознать');
                
                found = (res.ingredients || []).map(s => String(s).trim()).filter(Boolean);
                
                if (res.ai) this.ai = res.ai;
                
            } catch (e) {
                this.fridgeError = e.message || 'Ошибка распознавания';
            }

            // Ждем минимум 10 секунд
            const elapsed = (Date.now() - startTime) / 1000;
            if (elapsed < 10) {
                await new Promise(r => setTimeout(r, (10 - elapsed) * 1000));
            }
            clearInterval(interval);

            this.scanningFridge = false;
            this.adTimer = 0;

            if (found.length > 0) {
                found.forEach(name => {
                    if (!this.ingredients.includes(name)) this.ingredients.push(name);
                });
                this.fridgeImage = null;
            } else if (!this.fridgeError) {
                this.fridgeError = 'Не удалось распознать продукты';
            }
        },
    };
}