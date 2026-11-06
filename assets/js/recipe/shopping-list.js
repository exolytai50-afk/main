document.addEventListener('alpine:init', () => {
    Alpine.data('shoppingList', () => ({
        items: [],
        newItem: '',
        email: '',
        sending: false,
        openShoppingList: false,
        nextId: 1,
        
        init() {
            if (window.__RECIPE_INGREDIENTS__ && Array.isArray(window.__RECIPE_INGREDIENTS__)) {
                this.items = window.__RECIPE_INGREDIENTS__.map((ing, index) => {
                    const name = ing.name || ing.title || ing.ingredient || '';
                    let amount = ing.amount || ing.quantity || ing.count || '';
                    const unit = ing.unit || ing.measure || ing.measurement || '';
                    
                    // Конвертируем единицы измерения в тексте
                    amount = this.convertUnitsInText(amount);
                    
                    // Формируем полный текст с количеством
                    let text = name;
                    const quantity = [amount, unit].filter(Boolean).join(' ').trim();
                    if (quantity) {
                        text += ' — ' + quantity;
                    }
                    
                    return {
                        id: index + 1,
                        text: text,
                        checked: false,
                        editing: false
                    };
                });
            }
        },
        
        // Функция конвертации единиц измерения в тексте
        convertUnitsInText(text) {
            if (!text) return text;
            text = String(text);
            
            // Конвертация граммов
            text = text.replace(/(\d+)\s*(g|gr|gram|grams)\b/gi, '$1 г');
            text = text.replace(/(\d+)\s*(kg|kilogram|kilograms)\b/gi, '$1 кг');
            
            // Конвертация миллилитров и литров
            text = text.replace(/(\d+)\s*(ml|milliliter|milliliters)\b/gi, '$1 мл');
            text = text.replace(/(\d+)\s*(l|liter|liters)\b/gi, '$1 л');
            
            // Конвертация столовых ложек
            text = text.replace(/(\d+)\s*(tbsp|tablespoon|tablespoons)\b/gi, '$1 ст.л.');
            
            // Конвертация чайных ложек
            text = text.replace(/(\d+)\s*(tsp|teaspoon|teaspoons)\b/gi, '$1 ч.л.');
            
            return text;
        },
        
        get remaining() { 
            return this.items.filter(i => !i.checked).length; 
        },
        
        add() {
            const text = this.convertUnitsInText(this.newItem.trim());
            if (!text) return;
            this.items.push({
                id: this.nextId++, 
                text: text, 
                checked: false, 
                editing: false
            });
            this.newItem = '';
        },
        
        remove(id) { 
            this.items = this.items.filter(i => i.id !== id); 
        },
        
        startEdit(item) { 
            item.editing = true; 
        },
        
        saveEdit(item) { 
            item.editing = false; 
            item.text = this.convertUnitsInText(item.text);
            if (!item.text.trim()) this.remove(item.id); 
        },
        
        async copyList() {
            const text = this.items.map(i => `${i.text} ${i.checked ? '(куплено)' : ''}`).join('\n');
            try { 
                await navigator.clipboard.writeText(text); 
                if (typeof toast === 'function') toast('Список скопирован!', 'success'); 
            } catch (e) {}
        },
        
        async sendEmail() {
            if (!this.email || !this.email.includes('@')) {
                if (typeof toast === 'function') toast('Введите корректный email', 'error');
                return;
            }
            this.sending = true;
            try {
                const res = await fetch('/api/send_shopping_list.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': window.__CSRF_TOKEN__ || ''
                    },
                    body: JSON.stringify({email: this.email, items: this.items, recipe_id: window.__RECIPE_ID__ || 0, recipe_title: window.__RECIPE_TITLE__ || ''})
                });
                const data = await res.json();
                if (data.ok) {
                    if (typeof toast === 'function') toast('Список отправлен на почту!', 'success');
                } else {
                    if (typeof toast === 'function') toast(data.error || 'Ошибка отправки', 'error');
                }
            } catch (e) {
                if (typeof toast === 'function') toast('Не удалось отправить список', 'error');
            } finally {
                this.sending = false;
            }
        }
    }));
});