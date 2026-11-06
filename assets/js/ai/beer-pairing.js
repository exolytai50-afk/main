/**
 * GigaChefAI — Бирпейринг (два режима)
 */
function beerPairing(isLogged) {
    return {
        logged: isLogged,
        mode: 'beer-to-food', // 'food-to-beer' или 'beer-to-food'
        image: null,
        
        // Поля для режима "пиво к блюду"
        dishName: '',
        foodToBeerPref: '',
        cuisineStyle: '',
        
        // Поля для режима "блюда к пиву"
        beerName: '',
        beerToFoodType: '',
        servings: '1-2',
        
        // Общие
        result: null,
        loading: false,
        error: '',
        ai: {},
        showMobileMenu: false,
        
        // ─── Реклама ───
        aiAdHtml: window.__AI_AD_HTML__ || '',
        adTimer: 0,

        async init() {
            try {
                const r = await fetch('/api/ai_status.php?feature=calorie').then(res => res.json());
                this.ai = r.ai || {};
            } catch (_) {}
        },

        getInputText() {
            return this.mode === 'food-to-beer' ? this.dishName : this.beerName;
        },

        preview(e) {
            const file = e.target.files[0];
            if (!file) return;
            if (file.size > 5 * 1024 * 1024) { this.error = 'Файл больше 5 МБ'; return; }
            if (!file.type.startsWith('image/')) { this.error = 'Выберите изображение'; return; }
            
            this.error = '';
            const reader = new FileReader();
            reader.onload = (ev) => this.compressAndSet(ev.target.result, file.type);
            reader.onerror = () => { this.error = 'Ошибка чтения файла'; };
            reader.readAsDataURL(file);
            e.target.value = '';
        },

        compressAndSet(dataUrl, mimeType) {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                let w = img.width, h = img.height;
                const max = 800;
                if (w > max || h > max) {
                    if (w > h) { h = Math.round(h * max / w); w = max; }
                    else { w = Math.round(w * max / h); h = max; }
                }
                canvas.width = w; canvas.height = h;
                canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                this.image = canvas.toDataURL('image/jpeg', 0.75);
                this.result = null;
            };
            img.onerror = () => { this.image = dataUrl; this.result = null; };
            img.src = dataUrl;
        },

        clearImage() {
            this.image = null;
            this.result = null;
            this.error = '';
        },

        // ─── Анализ (с рекламной паузой) ───
        async analyze() {
            const inputText = this.getInputText().trim();
            if (!this.image && !inputText) {
                this.error = this.mode === 'food-to-beer' 
                    ? 'Загрузите фото блюда или введите его название'
                    : 'Загрузите фото пива или введите его название';
                return;
            }
            
            this.error = '';
            this.loading = true;
            this.result = null;
            this.adTimer = 0;

            const startTime = Date.now();
            const interval = setInterval(() => { this.adTimer++; }, 1000);

            let fetchedResult = null;

            try {
                const payload = { mode: this.mode };
                
                if (this.image) payload.image = this.image;
                
                if (this.mode === 'food-to-beer') {
                    if (this.dishName.trim()) payload.dish_name = this.dishName.trim();
                    if (this.foodToBeerPref) payload.preference = this.foodToBeerPref;
                    if (this.cuisineStyle) payload.cuisine = this.cuisineStyle;
                } else {
                    if (this.beerName.trim()) payload.beer_name = this.beerName.trim();
                    if (this.beerToFoodType) payload.meal_type = this.beerToFoodType;
                    payload.servings = this.servings;
                }
                
                // --- ВАЖНО: Используем fetch, как в рабочих страницах ---
                const res = await fetch('/api/ai_beer_pairing.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                if (!res.ok) {
                    const errData = await res.json().catch(() => ({}));
                    throw new Error(errData.error || 'HTTP ' + res.status);
                }

                const data = await res.json();
                if (data.ai) this.ai = data.ai;
                
                if (data.error) {
                    throw new Error(data.error);
                }

                fetchedResult = data.result || null;
            } catch (e) {
                console.error('Beer pairing error:', e);
                this.error = e.message || 'Не удалось выполнить подбор';
            }

            // Ждем минимум 10 секунд
            const elapsed = (Date.now() - startTime) / 1000;
            if (elapsed < 10) {
                await new Promise(r => setTimeout(r, (10 - elapsed) * 1000));
            }
            clearInterval(interval);

            this.loading = false;
            this.adTimer = 0;

            if (fetchedResult) {
                this.result = fetchedResult;
            }
        }
    };
}