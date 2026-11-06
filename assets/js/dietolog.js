window.showToast = (message, type = 'info') => {
    window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));
};

window.isMobile = /Android|iPhone|iPad|iPod|Windows Phone/i.test(navigator.userAgent) || (window.matchMedia && window.matchMedia('(max-width: 768px)').matches);

window.formatRuDate = (dateStr) => {
    const months = ['янв','фев','мар','апр','мая','июн','июл','авг','сен','окт','ноя','дек'];
    const d = new Date(dateStr + 'T00:00:00');
    const today = new Date(); today.setHours(0,0,0,0);
    const diff = Math.round((today - d) / 86400000);
    if (diff === 0) return 'Сегодня';
    if (diff === 1) return 'Вчера';
    if (diff === -1) return 'Завтра';
    return `${d.getDate()} ${months[d.getMonth()]}`;
};

document.addEventListener('alpine:init', () => {

    Alpine.data('dietForm', () => ({
        showQuestionnaire: false,
        selectedGoal: '',
        submitting: false,
        step: 1,
        isEditing: false,
        formData: {
            gender: '', age: '', height: '', weight: '',
            target_weight: '', activity_level: 'moderate',
            allergies: [], medical_conditions: '', budget: 'medium',
            blood_sugar: '', cholesterol: '', hemoglobin: '',
            systolic_pressure: '', diastolic_pressure: '',
        },
        goalTitles: {
            weight_loss: 'Похудение', muscle_gain: 'Набор массы',
            maintenance: 'Поддержка веса', diabetes: 'Диабет/низкий сахар',
            low_carb: 'Низкоуглеводное', vegetarian: 'Вегетарианство',
            gluten_free: 'Без глютена', lactose_free: 'Без лактозы',
        },
        get goalTitle() { return this.goalTitles[this.selectedGoal] || this.selectedGoal; },
        get stepValid() {
            if (this.step === 1) {
                return this.formData.gender &&
                       this.formData.age >= 14 && this.formData.age <= 100 &&
                       this.formData.height >= 100 && this.formData.height <= 250 &&
                       this.formData.weight >= 30 && this.formData.weight <= 300;
            }
            return true;
        },
        openQuestionnaire(detail) {
            if (detail && detail.edit && window.__DIETOLOG__.questionnaire) {
                const q = window.__DIETOLOG__.questionnaire;
                this.selectedGoal = q.goal || (detail && detail.goal) || '';
                this.formData = {
                    gender: q.gender || '',
                    age: q.age || '',
                    height: q.height || '',
                    weight: q.weight || '',
                    target_weight: q.target_weight || '',
                    activity_level: q.activity_level || 'moderate',
                    allergies: q.allergies
                        ? (Array.isArray(q.allergies) ? q.allergies : String(q.allergies).split(',').map(s => s.trim()).filter(Boolean))
                        : [],
                    medical_conditions: q.medical_conditions || '',
                    budget: q.budget || 'medium',
                    blood_sugar: q.blood_sugar || '',
                    cholesterol: q.cholesterol || '',
                    hemoglobin: q.hemoglobin || '',
                    systolic_pressure: q.systolic_pressure || '',
                    diastolic_pressure: q.diastolic_pressure || '',
                };
                this.isEditing = true;
            } else {
                this.selectedGoal = (detail && detail.goal) || '';
                this.formData = {
                    gender: '', age: '', height: '', weight: '',
                    target_weight: '', activity_level: 'moderate',
                    allergies: [], medical_conditions: '', budget: 'medium',
                    blood_sugar: '', cholesterol: '', hemoglobin: '',
                    systolic_pressure: '', diastolic_pressure: '',
                };
                this.isEditing = false;
            }
            this.step = 1;
            this.showQuestionnaire = true;
        },
        closeQuestionnaire() { this.showQuestionnaire = false; },
        nextStep() { if (this.stepValid && this.step < 4) this.step++; },
        prevStep() { if (this.step > 1) this.step--; },
        async submitQuestionnaire() {
            if (!this.stepValid) return;
            this.submitting = true;
            try {
                const fd = new FormData();
                fd.append('goal', this.selectedGoal);
                fd.append('csrf_token', window.__DIETOLOG__.csrfToken);
                if (this.isEditing) fd.append('edit', '1');
                Object.entries(this.formData).forEach(([k, v]) => {
                    if (Array.isArray(v)) v.forEach(i => fd.append(k + '[]', i));
                    else if (v !== '' && v !== null) fd.append(k, v);
                });
                const res = await fetch('/api/save_questionnaire.php', { method: 'POST', body: fd });
                if (res.ok) {
                    window.showToast(this.isEditing ? 'Анкета обновлена!' : 'Анкета сохранена!', 'success');
                    setTimeout(() => { window.location.href = window.__DIETOLOG__.dietologUrl; }, 800);
                } else {
                    window.showToast('Ошибка сервера', 'error');
                }
            } catch (e) { window.showToast('Ошибка соединения', 'error'); }
            finally { this.submitting = false; }
        }
    }));

    Alpine.data('dietologApp', () => ({
        tab: 'plan',
        streak: 0,

        // ── Иконки для мобильного dropdown ──
        tabItems: [
            { v: 'plan',     t: 'План',    i: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor" width="1.15em" height="1.15em" style="display:inline-block;vertical-align:-0.2em;"><path d="M208,40V168H152S152,64,208,40Z" opacity="0.2"/><path d="M72,88V40a8,8,0,0,1,16,0V88a8,8,0,0,1-16,0ZM216,40V224a8,8,0,0,1-16,0V176H152a8,8,0,0,1-8-8,268.75,268.75,0,0,1,7.22-56.88c9.78-40.49,28.32-67.63,53.63-78.47A8,8,0,0,1,216,40ZM200,53.9c-32.17,24.57-38.47,84.42-39.7,106.1H200ZM119.89,38.69a8,8,0,1,0-15.78,2.63L112,88.63a32,32,0,0,1-64,0l7.88-47.31a8,8,0,1,0-15.78-2.63l-8,48A8.17,8.17,0,0,0,32,88a48.07,48.07,0,0,0,40,47.32V224a8,8,0,0,0,16,0V135.32A48.07,48.07,0,0,0,128,88a8.17,8.17,0,0,0-.11-1.31Z"/></svg>' },
            { v: 'diary',    t: 'Дневник', i: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor" width="1.15em" height="1.15em" style="display:inline-block;vertical-align:-0.2em;"><path d="M208,48V216a8,8,0,0,1-8,8H56a8,8,0,0,1-8-8V48a8,8,0,0,1,8-8H96a39.83,39.83,0,0,0-8,24v8h80V64a39.83,39.83,0,0,0-8-24h40A8,8,0,0,1,208,48Z" opacity="0.2"/><path d="M168,152a8,8,0,0,1-8,8H96a8,8,0,0,1,0-16h64A8,8,0,0,1,168,152Zm-8-40H96a8,8,0,0,0,0,16h64a8,8,0,0,0,0-16Zm56-64V216a16,16,0,0,1-16,16H56a16,16,0,0,1-16-16V48A16,16,0,0,1,56,32H92.26a47.92,47.92,0,0,1,71.48,0H200A16,16,0,0,1,216,48ZM96,64h64a32,32,0,0,0-64,0ZM200,48H173.25A47.93,47.93,0,0,1,176,64v8a8,8,0,0,1-8,8H88a8,8,0,0,1-8-8V64a47.93,47.93,0,0,1,2.75-16H56V216H200Z"/></svg>' },
            { v: 'analysis', t: 'Анализ',  i: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor" width="1.15em" height="1.15em" style="display:inline-block;vertical-align:-0.2em;"><path d="M224,64V208H32V48H208A16,16,0,0,1,224,64Z" opacity="0.2"/><path d="M232,208a8,8,0,0,1-8,8H32a8,8,0,0,1-8-8V48a8,8,0,0,1,16,0V156.69l50.34-50.35a8,8,0,0,1,11.32,0L128,132.69,180.69,80H160a8,8,0,0,1,0-16h40a8,8,0,0,1,8,8v40a8,8,0,0,1-16,0V91.31l-58.34,58.35a8,8,0,0,1-11.32,0L96,123.31l-56,56V200H224A8,8,0,0,1,232,208Z"/></svg>' },
            { v: 'checks',   t: 'Чеки',    i: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor" width="1.15em" height="1.15em" style="display:inline-block;vertical-align:-0.2em;"><path d="M224,56V208l-32-16-32,16-32-16L96,208,64,192,32,208V56a8,8,0,0,1,8-8H216A8,8,0,0,1,224,56Z" opacity="0.2"/><path d="M72,104a8,8,0,0,1,8-8h96a8,8,0,0,1,0,16H80A8,8,0,0,1,72,104Zm8,40h96a8,8,0,0,0,0-16H80a8,8,0,0,0,0,16ZM232,56V208a8,8,0,0,1-11.58,7.15L192,200.94l-28.42,14.21a8,8,0,0,1-7.16,0L128,200.94,99.58,215.15a8,8,0,0,1-7.16,0L64,200.94,35.58,215.15A8,8,0,0,1,24,208V56A16,16,0,0,1,40,40H216A16,16,0,0,1,232,56Zm-16,0H40V195.06l20.42-10.22a8,8,0,0,1,7.16,0L96,199.06l28.42-14.22a8,8,0,0,1,7.16,0L160,199.06l28.42-14.22a8,8,0,0,1,7.16,0L216,195.06Z"/></svg>' },
            { v: 'steps',    t: 'Шагомер', i: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor" width="1.15em" height="1.15em" style="display:inline-block;vertical-align:-0.2em;"><path d="M176,48a24,24,0,1,1-24-24A24,24,0,0,1,176,48Z" opacity="0.2"/><path d="M152,80a32,32,0,1,0-32-32A32,32,0,0,0,152,80Zm0-48a16,16,0,1,1-16,16A16,16,0,0,1,152,32Zm64,112a8,8,0,0,1-8,8c-35.31,0-52.95-17.81-67.12-32.12-2.74-2.77-5.36-5.4-8-7.84l-13.43,30.88,37.2,26.57A8,8,0,0,1,160,176v56a8,8,0,0,1-16,0V180.12l-31.07-22.2L79.34,235.19A8,8,0,0,1,72,240a7.84,7.84,0,0,1-3.19-.67,8,8,0,0,1-4.15-10.52l54.08-124.37c-9.31-1.65-20.92,1.2-34.7,8.58a163.88,163.88,0,0,0-30.57,21.77,8,8,0,0,1-10.95-11.66c2.5-2.35,61.69-57.22,98.72-25.08,3.83,3.32,7.48,7,11,10.58C166.19,122.7,179.36,136,208,136A8,8,0,0,1,216,144Z"/></svg>' }
        ],

        tabLabels: {
            plan: 'План', diary: 'Дневник', analysis: 'Анализ', checks: 'Чеки', steps: 'Шагомер'
        },

        tabIcon(v) {
            const item = this.tabItems.find(i => i.v === v);
            return item ? item.i : '';
        },

        tabLabel(v) {
            return this.tabLabels[v] || '';
        },

        init() {
            this.calculateStreak();
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }
            if (window.location.hash === '#analysis') this.tab = 'analysis';

            // Слушаем переключение вкладки из других компонентов (например, из дневника)
            window.addEventListener('switch-tab', (e) => {
                const name = e.detail;
                if (typeof name === 'string') this.switchTab(name);
            });
        },

        calculateStreak() {
            const diaryData = window.__DIETOLOG__.diaryEntries;
            const dates = [...new Set(diaryData.map(e => (e.entry_date || '').slice(0, 10)))].sort().reverse();
            if (dates.length === 0) return;
            const today = new Date(); today.setHours(0, 0, 0, 0);
            let streak = 0;
            let checkDate = new Date(today);
            if (!dates.includes(checkDate.toISOString().split('T')[0])) {
                checkDate.setDate(checkDate.getDate() - 1);
            }
            while (dates.includes(checkDate.toISOString().split('T')[0])) {
                streak++;
                checkDate.setDate(checkDate.getDate() - 1);
            }
            this.streak = streak;
        },

        switchTab(name) {
            if (name === 'steps' && !window.isMobile) {
                window.showToast('Шагомер доступен только на мобильных устройствах', 'info');
                return;
            }
            this.tab = name;
            if (name === 'analysis') {
                this.$nextTick(() => window.dispatchEvent(new CustomEvent('draw-weight-chart')));
            }
        }
    }));

    Alpine.data('dietDiary', () => ({
        mealType: 'breakfast',
        foodName: '',
        portion: '',
        entries: [],
        entriesLoading: true,
        aiCalculating: false,
        selectedDate: new Date().toISOString().split('T')[0],
        recentFoods: [],
        waterCount: parseInt(localStorage.getItem('waterCount_' + new Date().toISOString().split('T')[0]) || '0'),
        waterGoalCount: Math.ceil(window.__DIETOLOG__.waterGoalMl / 250),

        get isToday() { return this.selectedDate === new Date().toISOString().split('T')[0]; },
        get dayLabel() { return window.formatRuDate(this.selectedDate); },
        get dayDateStr() {
            const d = new Date(this.selectedDate + 'T00:00:00');
            const weekdays = ['воскресенье','понедельник','вторник','среда','четверг','пятница','суббота'];
            return weekdays[d.getDay()];
        },
        get dayEntries() {
            return this.entries.filter(e => (e.entry_date || '').slice(0, 10) === this.selectedDate);
        },
        get dayTotals() {
            return this.dayEntries.reduce((acc, e) => ({
                calories: acc.calories + (parseInt(e.calories) || 0),
                proteins: acc.proteins + (parseFloat(e.proteins) || 0),
                fats: acc.fats + (parseFloat(e.fats) || 0),
                carbs: acc.carbs + (parseFloat(e.carbs) || 0),
            }), { calories: 0, proteins: 0, fats: 0, carbs: 0 });
        },

        setWater(count) {
            this.waterCount = count;
            localStorage.setItem('waterCount_' + this.selectedDate, String(count));
        },

        changeDay(dir) {
            const d = new Date(this.selectedDate + 'T00:00:00');
            d.setDate(d.getDate() + dir);
            this.selectedDate = d.toISOString().split('T')[0];
            this.waterCount = parseInt(localStorage.getItem('waterCount_' + this.selectedDate) || '0');
        },

        async init() { await this.loadEntries(); this.loadRecentFoods(); },

        async loadEntries() {
            this.entriesLoading = true;
            try {
                const res = await fetch('/api/diet_diary.php?action=list');
                const data = await res.json();
                this.entries = data.entries || [];
            } catch (e) { window.showToast('Не удалось загрузить дневник', 'error'); this.entries = []; }
            finally { this.entriesLoading = false; }
        },

        loadRecentFoods() {
            const seen = new Set();
            this.recentFoods = [];
            this.entries.forEach(e => {
                if (!seen.has(e.food_name)) {
                    seen.add(e.food_name);
                    this.recentFoods.unshift({ name: e.food_name, portion: e.portion });
                }
            });
            this.recentFoods = this.recentFoods.slice(0, 6);
        },

        quickAdd(food) {
            this.foodName = food.name;
            this.portion = food.portion || '';
        },

        handleQuickAdd(detail) {
            this.foodName = detail.name;
            this.portion = '';
            this.mealType = 'breakfast';
            window.showToast('Блюдо добавлено в форму. Нажмите «Добавить»', 'info');
        },

        async addEntry() {
            if (!this.foodName.trim() || !this.isToday) return;
            this.aiCalculating = true;
            try {
                const res = await fetch('/api/diet_diary.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ meal_type: this.mealType, food_name: this.foodName, portion: this.portion })
                });
                const data = await res.json();
                if (data.ok) { this.foodName = ''; this.portion = ''; await this.loadEntries(); this.loadRecentFoods(); window.showToast('Блюдо добавлено', 'success'); }
                else { window.showToast(data.error || 'Ошибка', 'error'); }
            } catch (e) { window.showToast('Ошибка соединения', 'error'); }
            finally { this.aiCalculating = false; }
        },

        async removeEntry(id) {
            try { await fetch('/api/diet_diary.php?id=' + id, { method: 'DELETE' }); await this.loadEntries(); }
            catch (e) { window.showToast('Ошибка удаления', 'error'); }
        },

        finishDay() {
            window.showToast('День завершён! Смотрите анализ.', 'success');
            window.dispatchEvent(new CustomEvent('switch-tab', { detail: 'analysis' }));
        }
    }));

    Alpine.data('dietAnalysis', () => ({
        weightDate: new Date().toISOString().split('T')[0],
        weightValue: '',
        weightEntries: window.__DIETOLOG__.weightEntries,
        analysisDays: [],
        today: new Date().toISOString().split('T')[0],
        _chart: null,
        _macroChart: null,

        init() {
            this.calculateAnalysis();
            window.addEventListener('draw-weight-chart', () => {
                this.$nextTick(() => { this.drawWeightChart(); this.drawMacroChart(); });
            });
            // Первая отрисовка, если вкладка «Анализ» открыта сразу
            this.$nextTick(() => { this.drawWeightChart(); this.drawMacroChart(); });
        },

        get macroAvg() {
            if (!this.analysisDays.length) return { proteins: 0, fats: 0, carbs: 0 };
            const sum = this.analysisDays.reduce((acc, d) => ({
                proteins: acc.proteins + d.proteins,
                fats: acc.fats + d.fats,
                carbs: acc.carbs + d.carbs,
            }), { proteins: 0, fats: 0, carbs: 0 });
            const n = this.analysisDays.length;
            return { proteins: Math.round(sum.proteins / n), fats: Math.round(sum.fats / n), carbs: Math.round(sum.carbs / n) };
        },

        get macroPercent() {
            const total = this.macroAvg.proteins + this.macroAvg.fats + this.macroAvg.carbs;
            if (!total) return { proteins: 0, fats: 0, carbs: 0 };
            return {
                proteins: Math.round(this.macroAvg.proteins / total * 100),
                fats: Math.round(this.macroAvg.fats / total * 100),
                carbs: Math.round(this.macroAvg.carbs / total * 100),
            };
        },

        get aiRecommendations() {
            const recs = [];
            const T = window.__DIETOLOG__.kbjuTargets;
            const avgCal = this.analysisDays.length
                ? Math.round(this.analysisDays.reduce((s, d) => s + d.calories, 0) / this.analysisDays.length)
                : 0;

            if (avgCal === 0) {
                recs.push('Начните вести дневник питания для получения рекомендаций');
            } else {
                if (avgCal > T.calories * 1.15) recs.push(`Среднее потребление (${avgCal} ккал) превышает норму на ${Math.round((avgCal/T.calories - 1) * 100)}% — уменьшите порции`);
                else if (avgCal < T.calories * 0.85) recs.push(`Среднее потребление (${avgCal} ккал) ниже нормы на ${Math.round((1 - avgCal/T.calories) * 100)}% — добавьте полезные перекусы`);
                else recs.push(`Среднее потребление (${avgCal} ккал) в пределах нормы — так держать!`);
            }

            if (this.macroAvg.proteins < T.proteins * 0.8) recs.push('Белка недостаточно — добавьте курицу, рыбу, яйца или творог');
            if (this.macroAvg.fats > T.fats * 1.2) recs.push('Жиров больше нормы — сократите масло и орехи');
            if (this.macroAvg.carbs > T.carbs * 1.2) recs.push('Углеводов больше нормы — уменьшите порции круп и сладкого');

            recs.push('Пейте больше воды (минимум ' + (window.__DIETOLOG__.waterGoalMl / 1000).toFixed(1) + ' л в день)');
            if (this.weightEntries.length > 3) recs.push('Отслеживайте динамику веса — график выше');

            return recs;
        },

        async addWeight() {
            if (!this.weightDate || !this.weightValue) return;
            try {
                const res = await fetch('/api/diet_weight.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ entry_date: this.weightDate, weight: parseFloat(this.weightValue) })
                });
                const data = await res.json();
                if (data.ok) {
                    this.weightEntries = data.entries || this.weightEntries;
                    this.weightValue = '';
                    this.$nextTick(() => this.drawWeightChart());
                    window.showToast('Вес сохранён', 'success');
                }
                else { window.showToast(data.error || 'Ошибка', 'error'); }
            } catch (e) { window.showToast('Ошибка соединения', 'error'); }
        },

        calculateAnalysis() {
            const diaryData = window.__DIETOLOG__.diaryEntries;
            const grouped = {};
            diaryData.forEach(entry => {
                const date = entry.entry_date;
                if (!grouped[date]) grouped[date] = { calories: 0, proteins: 0, fats: 0, carbs: 0 };
                grouped[date].calories += parseInt(entry.calories) || 0;
                grouped[date].proteins += parseFloat(entry.proteins) || 0;
                grouped[date].fats += parseFloat(entry.fats) || 0;
                grouped[date].carbs += parseFloat(entry.carbs) || 0;
            });
            this.analysisDays = Object.keys(grouped).map(date => ({ date, ...grouped[date] })).sort((a, b) => b.date.localeCompare(a.date));
        },

        formatDate(dateStr) { return window.formatRuDate(dateStr); },

        drawWeightChart()  {
            const canvas = document.getElementById('weightChart');
            if (!canvas) return;
    
            // Проверяем, что canvas виден (вкладка активна)
            if (canvas.offsetWidth === 0 || canvas.offsetHeight === 0) {
                // Если скрыт — откладываем отрисовку
                setTimeout(() => this.drawWeightChart(), 100);
                return;
            }
            if (!this.weightEntries.length || typeof Chart === 'undefined') return;
            const ctx = document.getElementById('weightChart');
            if (!ctx) return;
            if (this._chart) this._chart.destroy();
            const labels = this.weightEntries.map(e => e.entry_date).reverse();
            const data = this.weightEntries.map(e => parseFloat(e.weight)).reverse();
            this._chart = new Chart(ctx, {
                type: 'line',
                data: { labels, datasets: [{
                    label: 'Вес (кг)', data,
                    borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.1)',
                    tension: 0.3, fill: true, pointRadius: 4, pointHoverRadius: 7, pointBackgroundColor: '#10b981',
                }] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: (c) => c.parsed.y.toFixed(1) + ' кг' } }
                    },
                    scales: {
                        y: { beginAtZero: false, grid: { color: 'rgba(0,0,0,0.05)' } },
                        x: { grid: { display: false } }
                    },
                }
            });
        },

        drawMacroChart() {
            if (!this.analysisDays.length || typeof Chart === 'undefined') return;
            const ctx = document.getElementById('macroChart');
            if (!ctx) return;
            if (this._macroChart) this._macroChart.destroy();
            this._macroChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Белки', 'Жиры', 'Углеводы'],
                    datasets: [{
                        data: [this.macroAvg.proteins, this.macroAvg.fats, this.macroAvg.carbs],
                        backgroundColor: ['#3b82f6', '#f59e0b', '#8b5cf6'],
                        borderWidth: 0,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (c) => {
                                    const total = c.dataset.data.reduce((a, b) => a + b, 0);
                                    const pct = total > 0 ? Math.round(c.parsed / total * 100) : 0;
                                    return ` ${c.label}: ${c.parsed} г (${pct}%)`;
                                }
                            }
                        }
                    }
                }
            });
        },

        exportCSV() {
            let csv = 'Дата,Вес (кг)\n';
            this.weightEntries.forEach(e => { csv += `${e.entry_date},${e.weight}\n`; });
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url; a.download = 'weight_history.csv'; a.click();
            URL.revokeObjectURL(url);
            window.showToast('Файл скачан', 'success');
        }
    }));

    Alpine.data('checkUploader', () => ({
        fileSelected: false,
        fileName: '',
        previewUrl: '',
        processing: false,
        dragOver: false,
        recognizedProducts: [],

        handleFile(event) {
            const file = event.target.files[0];
            if (file) this.processFile(file);
        },
        handleDrop(event) {
            this.dragOver = false;
            const file = event.dataTransfer.files[0];
            if (file) this.processFile(file);
        },
        processFile(file) {
            if (file.size > 10 * 1024 * 1024) { window.showToast('Файл слишком большой (макс. 10 МБ)', 'error'); return; }
            this.fileSelected = true;
            this.fileName = file.name;
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (e) => { this.previewUrl = e.target.result; };
                reader.readAsDataURL(file);
            }
        },
        clearFile() {
            this.fileSelected = false;
            this.fileName = '';
            this.previewUrl = '';
        },
        async uploadCheck() {
            if (!this.fileSelected) return;
            this.processing = true;
            setTimeout(() => {
                this.recognizedProducts = [
                    { name: 'Молоко 3.2%',    quantity: '1 л',   calories: 600 },
                    { name: 'Хлеб ржаной',    quantity: '1 шт',  calories: 220 },
                    { name: 'Куриная грудка', quantity: '500 г', calories: 600 },
                    { name: 'Гречка',         quantity: '1 кг',  calories: 1100 },
                    { name: 'Творог 5%',      quantity: '250 г', calories: 310 },
                    { name: 'Огурцы',         quantity: '1 кг',  calories: 150 },
                    { name: 'Помидоры',       quantity: '1 кг',  calories: 180 },
                    { name: 'Оливковое масло', quantity: '500 мл', calories: 4000 },
                ];
                this.processing = false;
                this.clearFile();
                window.showToast('Чек распознан — проверьте список', 'success');
            }, 2500);
        },
        async addToDiary(product) {
            try {
                const res = await fetch('/api/diet_diary.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ meal_type: 'snack', food_name: product.name, portion: product.quantity })
                });
                const data = await res.json();
                if (data.ok) window.showToast('«' + product.name + '» добавлен в дневник', 'success');
                else window.showToast('Не удалось добавить', 'error');
            } catch (e) { window.showToast('Ошибка соединения', 'error'); }
        },
        async addAllToDiary() {
            let ok = 0;
            for (const p of this.recognizedProducts) {
                try {
                    const res = await fetch('/api/diet_diary.php', {
                        method: 'POST', headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ meal_type: 'snack', food_name: p.name, portion: p.quantity })
                    });
                    const data = await res.json();
                    if (data.ok) ok++;
                } catch (e) {}
            }
            window.showToast(`Добавлено ${ok} из ${this.recognizedProducts.length} продуктов`, 'success');
            this.recognizedProducts = [];
        }
    }));

    Alpine.data('stepCounter', () => ({
        steps: window.__DIETOLOG__.todaySteps,
        distance: 0,
        caloriesBurned: 0,
        activeMinutes: 0,
        isTracking: false,
        statusText: 'Шагомер не запущен',
        isStepDetected: false,
        trackingInterval: null,
        wakeLock: null,
        boundHandleMotion: null,

        async init() { await this.loadSteps(); },

        async loadSteps() {
            try {
                const res = await fetch('/api/diet_steps.php');
                const data = await res.json();
                if (data.ok) {
                    this.steps = data.steps || 0;
                    this.distance = data.distance || 0;
                    this.caloriesBurned = data.calories || 0;
                    this.activeMinutes = data.active_minutes || 0;
                }
            } catch (e) {}
        },

        async toggleTracking() { if (this.isTracking) this.stopTracking(); else await this.startTracking(); },

        async startTracking() {
            if (!window.isMobile) { window.showToast('Шагомер работает только на мобильных устройствах', 'info'); return; }
            if (typeof DeviceMotionEvent === 'undefined') { window.showToast('Датчик движения не поддерживается', 'error'); return; }
            if (typeof DeviceMotionEvent.requestPermission === 'function') {
                try {
                    const perm = await DeviceMotionEvent.requestPermission();
                    if (perm !== 'granted') { window.showToast('Доступ к датчику запрещён', 'error'); return; }
                } catch (e) {}
            }
            this.requestWakeLock();
            this.isTracking = true;
            this.statusText = 'Отслеживание активно';
            this.boundHandleMotion = this.handleMotion.bind(this);
            window.addEventListener('devicemotion', this.boundHandleMotion);
            this.trackingInterval = setInterval(() => this.saveSteps(), 30000);
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('Шагомер запущен', { body: 'Работает в фоновом режиме' });
            }
        },

        stopTracking() {
            this.isTracking = false;
            this.statusText = 'Шагомер остановлен';
            if (this.boundHandleMotion) { window.removeEventListener('devicemotion', this.boundHandleMotion); this.boundHandleMotion = null; }
            if (this.trackingInterval) { clearInterval(this.trackingInterval); this.trackingInterval = null; }
            this.releaseWakeLock();
            this.saveSteps();
        },

        handleMotion(event) {
            const acc = event.accelerationIncludingGravity;
            if (!acc) return;
            const mag = Math.sqrt((acc.x || 0) ** 2 + (acc.y || 0) ** 2 + (acc.z || 0) ** 2);
            if (mag > 12 && !this.isStepDetected) {
                this.isStepDetected = true;
                this.steps++;
                this.distance = (this.steps * 0.75) / 1000;
                this.caloriesBurned = Math.round(this.steps * 0.04);
                this.activeMinutes = Math.round(this.steps / 100);
                setTimeout(() => { this.isStepDetected = false; }, 300);
            }
        },

        async saveSteps() {
            try {
                await fetch('/api/diet_steps.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ steps: this.steps, distance: this.distance, calories: this.caloriesBurned, active_minutes: this.activeMinutes })
                });
            } catch (e) {}
        },

        async requestWakeLock() {
            try { if ('wakeLock' in navigator) this.wakeLock = await navigator.wakeLock.request('screen'); }
            catch (e) {}
        },
        releaseWakeLock() {
            if (this.wakeLock) { this.wakeLock.release(); this.wakeLock = null; }
        }
    }));
});