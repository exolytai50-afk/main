/* ═══════════════════════════════════════════════════════════
   recipe-detail.js — все Alpine-компоненты страницы рецепта,
   кроме отзывов (reviews.js).
   ═══════════════════════════════════════════════════════════ */

/* ─── Глобальные функции для onclick в шаблоне ─── */
window.copyShareLink = function() {
    navigator.clipboard.writeText(window.location.href).then(function() {
        if (typeof toast === 'function') toast('Ссылка скопирована', 'success');
    });
};

window.saveRecipe = function(recipeId, btn) {
    if (!window.__IS_LOGGED_IN__) { window.location.href = '/login'; return; }
    fetch('/api/saved_recipes.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ recipe_id: recipeId })
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.ok) {
            if (data.saved) {
                btn.classList.add('text-amber-500');
                btn.classList.remove('text-gray-400');
                btn.querySelector('svg').setAttribute('fill', 'currentColor');
                btn.title = 'Убрать из избранного';
            } else {
                btn.classList.remove('text-amber-500');
                btn.classList.add('text-gray-400');
                btn.querySelector('svg').setAttribute('fill', 'none');
                btn.title = 'Сохранить в избранное';
            }
            if (typeof toast === 'function') toast(data.saved ? 'Сохранено!' : 'Убрано', 'success');
        } else {
            if (typeof toast === 'function') toast(data.error || 'Ошибка', 'error');
        }
    }).catch(function() {
        if (typeof toast === 'function') toast('Ошибка соединения', 'error');
    });
};

window.toggleLikeGlobal = function(recipeId, btn) {
    if (!window.__IS_LOGGED_IN__) { window.location.href = '/login'; return; }
    fetch('/api/recipe-likes.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ recipe_id: recipeId })
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.ok) {
            var span = btn.querySelector('span');
            if (span) span.textContent = data.likes_count;
            if (typeof toast === 'function') toast(data.liked ? '❤️' : 'Лайк снят', 'success');
        } else {
            if (typeof toast === 'function') toast(data.error || 'Ошибка', 'error');
        }
    }).catch(function() {
        if (typeof toast === 'function') toast('Ошибка соединения', 'error');
    });
};

/* ─── Регистрация компонентов ─── */
document.addEventListener('alpine:init', () => {

    /* ─── recipePage: табы + график ─── */
    Alpine.data('recipePage', () => ({
        tab: 'ingredients',
        _chartInited: false,

        switchTab(name) {
            this.tab = name;
            if (name === 'nutrition') {
                this.$nextTick(() => this.initChart());
            }
        },

        initChart() {
            if (this._chartInited) return;
            var canvas = document.getElementById('nutritionChart');
            if (!canvas) return;
            if (typeof initNutritionChart === 'function') {
                initNutritionChart('nutritionChart', {
                    proteins: window.__RECIPE_PROTEINS__,
                    fats: window.__RECIPE_FATS__,
                    carbs: window.__RECIPE_CARBS__
                });
                this._chartInited = true;
            }
        }
    }));

    /* ─── stepTimer ─── */
    Alpine.data('stepTimer', (minutes) => ({
        total: minutes * 60,
        remaining: minutes * 60,
        running: false,
        _timer: null,

        get display() {
            var m = Math.floor(this.remaining / 60);
            var s = this.remaining % 60;
            return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        },
        get progress() {
            return ((this.total - this.remaining) / this.total) * 100;
        },
        toggle() {
            if (this.running) {
                clearInterval(this._timer);
                this.running = false;
            } else {
                this.running = true;
                this._timer = setInterval(() => {
                    if (this.remaining > 0) {
                        this.remaining--;
                    } else {
                        clearInterval(this._timer);
                        this.running = false;
                    }
                }, 1000);
            }
        },
        reset() {
            clearInterval(this._timer);
            this.running = false;
            this.remaining = this.total;
        }
    }));

    /* ─── shoppingList ─── */
    Alpine.data('shoppingList', () => ({
        openShoppingList: false,
        items: [],
        newItem: '',
        email: '',
        sending: false,

        init() {
            if (window.__RECIPE_INGREDIENTS__ && Array.isArray(window.__RECIPE_INGREDIENTS__)) {
                this.items = window.__RECIPE_INGREDIENTS__.map(function(ing, i) {
                    var name = ing.name || ing.title || ing.ingredient || '';
                    var amount = ing.amount || ing.quantity || ing.count || '';
                    var unit = ing.unit || ing.measure || ing.measurement || '';
                    var text = name;
                    var quantity = [amount, unit].filter(Boolean).join(' ').trim();
                    if (quantity) text += ' — ' + quantity;

                    return {
                        id: i + 1,
                        text: text,
                        checked: false,
                        editing: false
                    };
                });
            }
        },

        get remaining() {
            return this.items.filter(function(i) { return !i.checked; }).length;
        },

        add() {
            if (!this.newItem.trim()) return;
            this.items.push({
                id: Date.now(),
                text: this.newItem.trim(),
                checked: false,
                editing: false
            });
            this.newItem = '';
        },

        remove(id) {
            this.items = this.items.filter(function(i) { return i.id !== id; });
        },

        startEdit(item) { item.editing = true; },
        saveEdit(item)  { item.editing = false; },

        copyList() {
            var text = this.items.map(function(i) {
                return (i.checked ? '☑' : '☐') + ' ' + i.text;
            }).join('\n');
            navigator.clipboard.writeText(text).then(function() {
                if (typeof toast === 'function') toast('Список скопирован', 'success');
            });
        },

        async sendEmail() {
            if (!this.email || !this.email.includes('@')) {
                if (typeof toast === 'function') toast('Введите корректный email', 'error');
                return;
            }
            this.sending = true;
            try {
                var res = await fetch('/api/shopping-list.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: this.email, items: this.items, recipe_id: window.__RECIPE_ID__ || 0, recipe_title: window.__RECIPE_TITLE__ })
                    
                    
                });
                var data = await res.json();
                if (data.ok) {
                    if (typeof toast === 'function') toast('Письмо отправлено!', 'success');
                    this.email = '';
                } else {
                    if (typeof toast === 'function') toast(data.error || 'Ошибка', 'error');
                }
            } catch (e) {
                if (typeof toast === 'function') toast('Ошибка соединения', 'error');
            }
            this.sending = false;
        }
    }));

    /* ─── videoUpload ─── */
    Alpine.data('videoUpload', (recipeId) => ({
        recipeId: recipeId,
        videoTitle: '',
        videoDescription: '',
        videoFile: null,
        videoFileName: '',
        uploading: false,
        uploadProgress: 0,
        error: '',
        success: '',
        
        // Поля для вставки по ссылке
        videoUrlInput: '',
        savingUrl: false,
        urlError: '',
        urlSuccess: '',

        handleVideoFile(event) {
            var file = event.target.files[0];
            if (!file) return;
            this.videoFile = file;
            this.videoFileName = file.name;
        },

        async uploadVideo() {
            if (!this.videoFile) return;
            this.uploading = true;
            this.error = '';
            this.success = '';
            this.uploadProgress = 0;

            var formData = new FormData();
            formData.append('video', this.videoFile);
            formData.append('title', this.videoTitle);
            formData.append('description', this.videoDescription);
            formData.append('recipe_id', this.recipeId);

            try {
                var res = await fetch('/api/video-upload.php', {
                    method: 'POST',
                    body: formData
                });
                var data = await res.json();
                if (data.ok) {
                    this.success = 'Видео загружено!';
                    this.uploadProgress = 100;
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    this.error = data.error || 'Ошибка загрузки';
                }
            } catch (e) {
                this.error = 'Ошибка соединения';
            }
            this.uploading = false;
        },

        async saveVideoUrl() {
            if (!this.videoUrlInput.trim()) return;
            this.savingUrl = true;
            this.urlError = '';
            this.urlSuccess = '';

            try {
                var res = await fetch('/api/update_recipe_video.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        recipe_id: this.recipeId,
                        video_url: this.videoUrlInput.trim()
                    })
                });
                var data = await res.json();
                if (data.ok) {
                    this.urlSuccess = 'Ссылка сохранена! Видео появится после перезагрузки.';
                    setTimeout(function() { location.reload(); }, 1200);
                } else {
                    this.urlError = data.error || 'Ошибка сохранения';
                }
            } catch (e) {
                this.urlError = 'Ошибка соединения';
            }
            this.savingUrl = false;
        }
    }));

    /* ─── diabetesCalculator ─── */
    Alpine.data('diabetesCalculator', () => ({
        get totalXE() {
            var carbs = window.__RECIPE_CARBS__ || 0;
            var servings = window.__RECIPE_SERVINGS__ || 1;
            return (carbs / servings) / 12;
        },
        get totalGL() {
            var carbs = window.__RECIPE_CARBS__ || 0;
            var servings = window.__RECIPE_SERVINGS__ || 1;
            var gi = 55;
            return ((carbs / servings) * gi) / 100;
        },
        get glClassification() {
            var gl = this.totalGL;
            if (gl < 10) return 'Низкая';
            if (gl < 20) return 'Средняя';
            return 'Высокая';
        },
        get recommendation() {
            var gl = this.totalGL;
            var xe = this.totalXE;
            if (gl < 10) return 'Блюдо подходит для регулярного употребления. ХЕ на порцию: ' + xe.toFixed(1);
            if (gl < 20) return 'Употребляйте в умеренном количестве. ХЕ на порцию: ' + xe.toFixed(1);
            return 'Ограничьте порцию или замените часть углеводов. ХЕ на порцию: ' + xe.toFixed(1);
        }
    }));

    /* ─── diabetesAdapter ─── */
    Alpine.data('diabetesAdapter', (recipeId) => ({
        recipeId: recipeId,
        loading: false,
        error: '',
        success: '',

        async createAdaptation(recreate) {
            this.loading = true;
            this.error = '';
            this.success = '';
            try {
                var res = await fetch('/api/ai_diabetes_adapt.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ recipe_id: this.recipeId, recreate: !!recreate })
                });
                var data = await res.json();
                if (data.ok) {
                    this.success = 'Адаптация создана!';
                    setTimeout(function() { location.reload(); }, 1200);
                } else {
                    this.error = data.error || 'Ошибка';
                }
            } catch (e) {
                this.error = 'Ошибка соединения';
            }
            this.loading = false;
        },

        async resetAllAdaptations() {
            if (!confirm('Скрыть все адаптации для этого рецепта?')) return;
            this.loading = true;
            this.error = '';
            this.success = '';

            try {
                // Отправляем на правильный URL
                var res = await fetch('/api/reset_diabetes_adaptations.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ recipe_id: this.recipeId })
                });
                var data = await res.json();
                
                if (data.ok) {
                    this.success = 'Адаптации скрыты';
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    this.error = data.error || 'Ошибка при скрытии';
                }
            } catch (e) {
                this.error = 'Ошибка соединения';
            }
            this.loading = false;
        }
    }));

    /* ─── recipeAssistant ─── */
    Alpine.data('recipeAssistant', (recipeId) => ({
        open: false,
        messages: [],
        input: '',
        loading: false,
        recipeId: recipeId,

        init() {
            this.messages.push({
                role: 'assistant',
                text: 'Привет! Спросите меня об этом рецепте — ингредиенты, замену, время приготовления.'
            });
        },

        async send() {
            if (!this.input.trim() || this.loading) return;
            var userText = this.input.trim();
            this.messages.push({ role: 'user', text: userText });
            this.input = '';
            this.loading = true;

            this.$nextTick(() => {
                if (this.$refs.scroll) this.$refs.scroll.scrollTop = this.$refs.scroll.scrollHeight;
            });

            try {
                var res = await fetch('/api/recipe-assistant.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ recipe_id: this.recipeId, message: userText })
                });
                var data = await res.json();
                if (data.ok) {
                    this.messages.push({ role: 'assistant', text: data.reply });
                } else {
                    this.messages.push({ role: 'assistant', text: 'Извините, не удалось ответить. Попробуйте позже.' });
                }
            } catch (e) {
                this.messages.push({ role: 'assistant', text: 'Ошибка соединения.' });
            }
            this.loading = false;

            this.$nextTick(() => {
                if (this.$refs.scroll) this.$refs.scroll.scrollTop = this.$refs.scroll.scrollHeight;
            });
        }
    }));

}); /* end alpine:init */