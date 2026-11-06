document.addEventListener('alpine:init', () => {
    Alpine.data('aiGenerator', () => ({
        // ─── Форма ───
        form: {
            dish: '',
            difficulty: '',
            servings: 2,
            exclude: '',
        },

        // ─── Данные ───
        tagGroups: [],
        selectedTags: {},
        recipes: [],
        selectedCount: 0,
        loading: false,
        saving: false,
        error: '',

        // ─── UI-состояние ───
        openDropdown: null,
        icons: window.__AI_ICONS__ || {},
        
        // ─── Реклама ───
        aiAdHtml: window.__AI_AD_HTML__ || '',
        adTimer: 0,

        // ─── Инициализация ───
        async init() {
            await this.loadTagGroups();
        },

        async loadTagGroups() {
            try {
                const res  = await fetch('/api/tag_groups.php');
                const data = await res.json();
                this.tagGroups = data.groups || [];
            } catch (e) {
                console.error('Tag groups load error:', e);
                this.tagGroups = [];
            }
        },

        // ─── Хелперы для dropdown тегов ───
        groupIconHtml(group) {
            const g = (this.tagGroups || []).find(x => x.group === group);
            return g && g.icon_svg_html ? g.icon_svg_html : '';
        },

        selectedTagIconHtml(group) {
            const slug = this.selectedTags[group];
            if (!slug) return '';
            const g = (this.tagGroups || []).find(x => x.group === group);
            if (!g) return '';
            const t = (g.tags || []).find(x => x.slug === slug);
            return t && t.icon_svg_html ? t.icon_svg_html : '';
        },

        currentGroupIcon(group) {
            return this.selectedTagIconHtml(group) || this.groupIconHtml(group);
        },

        getTagName(group, slug) {
            const g = (this.tagGroups || []).find(x => x.group === group);
            if (!g) return '';
            const t = (g.tags || []).find(x => x.slug === slug);
            return t ? t.name : '';
        },

        toggleGroupDropdown(group) {
            this.openDropdown = (this.openDropdown === group) ? null : group;
        },

        currentDifficultyIcon() {
            const map = window.__AI_DIFFICULTY_ICONS__ || {};
            return map[this.form.difficulty] || map[''] || '';
        },

        onGroupSelect(group, slug) {
            if (!slug) delete this.selectedTags[group];
            else this.selectedTags[group] = slug;
            this.openDropdown = null;
        },

        // ─── Выбор рецептов ───
        updateSelection() {
            this.selectedCount = this.recipes.filter(r => r.selected).length;
        },

        // ─── Генерация (с рекламной паузой) ───
        async generate() {
            if (!this.form.dish.trim()) {
                this.error = 'Введите название блюда';
                return;
            }

            this.loading = true;
            this.error = '';
            this.recipes = [];
            this.selectedCount = 0;
            this.adTimer = 0;

            const interval = setInterval(() => { this.adTimer++; }, 1000);
            const startTime = Date.now();

            const totalRecipes = 4;
            const allRecipes = [];
            const variations = [
                'классический рецепт',
                'необычный авторский вариант',
                'лёгкий диетический вариант',
                'сытный домашний вариант'
            ];

            for (let i = 0; i < totalRecipes; i++) {
                try {
                    const res = await fetch('/api/ai_generate_recipe.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            dish: this.form.dish.trim() + ' (' + variations[i] + ')',
                            difficulty: this.form.difficulty,
                            servings: parseInt(this.form.servings) || 2,
                            exclude: this.form.exclude.trim(),
                            tags: this.selectedTags,
                            count: 1,
                        }),
                    });

                    if (!res.ok) {
                        const errData = await res.json().catch(() => ({}));
                        throw new Error(errData.error || 'HTTP ' + res.status);
                    }

                    const data = await res.json();
                    const list = data.recipes || data.items || [];
                    if (list.length > 0) allRecipes.push(list[0]);
                } catch (e) {
                    console.error(`Ошибка генерации рецепта ${i + 1}:`, e);
                }
            }

            const elapsed = (Date.now() - startTime) / 1000;
            if (elapsed < 10) {
                await new Promise(r => setTimeout(r, (10 - elapsed) * 1000));
            }
            clearInterval(interval);

            if (allRecipes.length === 0) {
                this.error = 'Не удалось сгенерировать ни одного рецепта. Попробуйте еще раз.';
            } else {
                this.recipes = allRecipes.map(r => ({
                    ...r,
                    selected: false,
                    expanded: false,
                }));
                this.updateSelection();
            }

            this.loading = false;
            this.adTimer = 0;
        },

        // ─── Сохранение (с рекламной паузой) ───
        async saveSelected() {
            const selected = this.recipes.filter(r => r.selected);
            if (!selected.length) return;

            this.saving = true;
            this.error = '';
            this.adTimer = 0;

            const interval = setInterval(() => { this.adTimer++; }, 1000);
            const startTime = Date.now();

            let savedCount = 0;
            let duplicates = [];
            let errors = [];

            for (const recipe of selected) {
                try {
                    const payload = {
                        title:           recipe.title,
                        description:     recipe.description || '',
                        image_url:       recipe.image_url || '',
                        video_url:       recipe.video_url || '',
                        difficulty:      recipe.difficulty || 'easy',
                        prep_time:       parseInt(recipe.prep_time) || 0,
                        cook_time:       parseInt(recipe.cook_time) || 0,
                        servings:        parseInt(recipe.servings) || 2,
                        calories:        parseInt(recipe.calories) || 0,
                        proteins:        parseInt(recipe.proteins) || 0,
                        fats:            parseInt(recipe.fats) || 0,
                        carbs:           parseInt(recipe.carbs) || 0,
                        ingredients:     recipe.ingredients || [],
                        steps:           recipe.steps || [],
                        category_slug:   recipe.category_slug || '',
                        tag_slugs:       recipe.tag_slugs || [],
                        is_ai_generated: 1,
                    };

                    const res = await fetch('/api/save_recipe.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });

                    const data = await res.json();

                    if (res.status === 409 && data.duplicate) {
                        duplicates.push({
                            title: data.duplicate.title,
                            similarity: data.duplicate.similarity,
                            redirect_url: data.redirect_url,
                        });
                        continue;
                    }

                    if (data.ok) savedCount++;
                    else errors.push({ title: recipe.title, error: data.error });
                } catch (e) {
                    errors.push({ title: recipe.title, error: 'Сетевая ошибка' });
                }
            }

            const elapsed = (Date.now() - startTime) / 1000;
            if (elapsed < 10) {
                await new Promise(r => setTimeout(r, (10 - elapsed) * 1000));
            }
            clearInterval(interval);

            this.saving = false;
            this.adTimer = 0;

            let message = '';
            if (savedCount > 0) message += `Сохранено: ${savedCount} рецепт(ов).\n`;
            
            if (duplicates.length > 0) {
                message += `\nВнимание: найдены дубликаты (${duplicates.length}):\n`;
                duplicates.forEach(d => {
                    message += `- «${d.title}» (совпадение ${d.similarity}%)\n`;
                });
                const openExisting = confirm(message + '\n\nОткрыть существующий рецепт «' + duplicates[0].title + '»?');
                if (openExisting && duplicates[0].redirect_url) {
                    window.location.href = duplicates[0].redirect_url;
                    return;
                }
            }
            
            if (errors.length > 0) {
                message += `\nОшибки (${errors.length}):\n`;
                errors.forEach(e => { message += `- «${e.title}»: ${e.error}\n`; });
            }

            if (message) {
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { 
                        message: savedCount > 0 ? `Сохранено: ${savedCount} из ${selected.length}` : 'Не удалось сохранить',
                        type: savedCount > 0 ? 'success' : 'error'
                    }
                }));
                if (duplicates.length === 0 && errors.length > 0) alert(message);
            }

            if (savedCount > 0) {
                this.recipes = this.recipes.filter(r => !r.selected);
                this.updateSelection();
            }
        },
    }));
});