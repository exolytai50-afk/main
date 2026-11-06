/**
 * GigaChef — «Бросаем курить»
 * Все иконки берутся из window.__QUIT_ICONS__ (прокинуто из PHP).
 * Своих эмодзи здесь нет — только HTML-строки и фолбэки.
 */

// ─── Утилиты ───
window.showToast = (message, type = 'info') => {
    window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));
};

window.isMobile = /Android|iPhone|iPad|iPod|Windows Phone/i.test(navigator.userAgent) ||
    (window.matchMedia && window.matchMedia('(max-width: 768px)').matches);

window.formatRuDate = (dateStr) => {
    const months = ['янв','фев','мар','апр','мая','июн','июл','авг','сен','окт','ноя','дек'];
    const d = new Date(dateStr + 'T00:00:00');
    const today = new Date(); today.setHours(0,0,0,0);
    const diff = Math.round((today - d) / 86400000);
    if (diff === 0)  return 'Сегодня';
    if (diff === 1)  return 'Вчера';
    if (diff === -1) return 'Завтра';
    return `${d.getDate()} ${months[d.getMonth()]}`;
};

// ─── Безопасный доступ к иконкам ───
// Если window.__QUIT_ICONS__ ещё не проставлен — вернём пустую строку,
// чтобы не сломать верстку ни при каких обстоятельствах.
function quitIcon(name) {
    const pack = window.__QUIT_ICONS__ || {};
    return pack[name] || '';
}
function quitIconInline(name) {
    // Иконка в строке — с классом размера по умолчанию
    const svg = quitIcon(name);
    return svg ? `<span class="inline-flex items-center">${svg}</span>` : '';
}

// ─── Alpine-компоненты ───
document.addEventListener('alpine:init', () => {

    // ═══════════════════════════════════════════════════════════
    //  Единая компонента страницы «Бросаем курить»
    // ═══════════════════════════════════════════════════════════
    Alpine.data('quitApp', () => ({
        tab: 'overview',

        // SOS / тяга
        showCravingPopup: false,
        breathingActive: false,
        breathingPhase: '',
        breathingCount: 0,
        _breathingTimer: null,

        // Анкета / редактирование
        showQuestionnaire: false,
        isEditing: false,
        submitting: false,
        step: 1,

        formData: {
            cigarettes_per_day: '',
            years_smoking: '',
            cigarettes_per_pack: 20,
            pack_price: '',
            wake_up_smoke: '',
            first_cigarette_time: '',
            trigger_coffee: false,
            trigger_stress: false,
            trigger_alcohol: false,
            trigger_after_meal: false,
            trigger_social: false,
            trigger_driving: false,
            trigger_boredom: false,
            previous_attempts: '',
            previous_methods: '',
            motivation_health: false,
            motivation_money: false,
            motivation_family: false,
            motivation_pregnancy: false,
            motivation_fitness: false,
            quit_method: '',
            quit_date: '',
        },

        methodNames: {
            cold_turkey: 'Резкий отказ',
            gradual: 'Постепенное снижение',
            nrt: 'Никотинзаместительная терапия',
            car: 'Метод Аллена Карра',
            cbt: 'Когнитивно-поведенческая терапия',
            mindfulness: 'Осознанность и медитация',
            meds: 'Медикаментозная поддержка',
            combined: 'Комбинированный подход',
            switch: 'Метод Переключателя',
        },

        // ─── Иконки для использования в шаблоне ───
        icons: {
            get check()     { return quitIcon('check'); },
            get close()     { return quitIcon('close'); },
            get warning()   { return quitIcon('warning'); },
            get hourglass() { return quitIcon('hourglass'); },
        },

        init() {
            if (window.location.hash === '#diary')    this.tab = 'diary';
            if (window.location.hash === '#progress') this.tab = 'progress';
        },

        // ─── Табы ───
        switchTab(name) { this.tab = name; },

        // ─── SOS / дыхание ───
        openCravingSOS() {
            this.showCravingPopup = true;
            this.startBreathing();
        },

        closeCravingSOS() {
            this.showCravingPopup = false;
            this.stopBreathing();
        },

        startBreathing() {
            this.breathingActive = true;
            this.breathingCount  = 0;
            this.breathingPhase  = 'Вдох';

            let phaseIndex = 0;
            const phases    = ['Вдох', 'Задержка', 'Выдох', 'Задержка'];
            const durations = [4000, 4000, 6000, 2000];

            const run = () => {
                if (!this.breathingActive || this.breathingCount >= 5) {
                    this.stopBreathing();
                    return;
                }
                this.breathingPhase = phases[phaseIndex];
                this._breathingTimer = setTimeout(() => {
                    phaseIndex = (phaseIndex + 1) % 4;
                    if (phaseIndex === 0) this.breathingCount++;
                    run();
                }, durations[phaseIndex]);
            };
            run();
        },

        stopBreathing() {
            this.breathingActive = false;
            if (this._breathingTimer) {
                clearTimeout(this._breathingTimer);
                this._breathingTimer = null;
            }
        },

        async logCraving(coped) {
            try {
                await fetch('/api/quit_craving.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ coped, intensity: 5 })
                });
                window.showToast(
                    coped
                        ? 'Сила на вашей стороне! Тяга зафиксирована.'
                        : 'Тяга зафиксирована. Не сдавайтесь!',
                    'success'
                );
            } catch (e) {
                window.showToast('Тяга отмечена', 'info');
            }
            this.closeCravingSOS();
        },

        // ─── Анкета ───
        get methodTitle() {
            return this.methodNames[this.formData.quit_method]
                || this.formData.quit_method
                || 'Комбинированный подход';
        },

        get stepValid() {
            if (this.step === 1) {
                return this.formData.cigarettes_per_day >= 1 &&
                       this.formData.cigarettes_per_day <= 100 &&
                       this.formData.years_smoking >= 1 &&
                       this.formData.years_smoking <= 80;
            }
            if (this.step === 3) {
                return !!this.formData.quit_method && !!this.formData.quit_date;
            }
            return true;
        },

        openQuestionnaire(detail) {
            const D = window.__QUIT__ || {};
            if (detail && detail.edit && D.profile) {
                const p = D.profile;
                this.formData = {
                    cigarettes_per_day:   p.cigarettes_per_day   || '',
                    years_smoking:        p.years_smoking        || '',
                    cigarettes_per_pack:  p.cigarettes_per_pack  || 20,
                    pack_price:           p.pack_price           || '',
                    wake_up_smoke:        p.wake_up_smoke        || '',
                    first_cigarette_time: p.first_cigarette_time || '',
                    trigger_coffee:       !!p.trigger_coffee,
                    trigger_stress:       !!p.trigger_stress,
                    trigger_alcohol:      !!p.trigger_alcohol,
                    trigger_after_meal:   !!p.trigger_after_meal,
                    trigger_social:       !!p.trigger_social,
                    trigger_driving:      !!p.trigger_driving,
                    trigger_boredom:      !!p.trigger_boredom,
                    previous_attempts:    p.previous_attempts    || '',
                    previous_methods:     p.previous_methods     || '',
                    motivation_health:    !!p.motivation_health,
                    motivation_money:     !!p.motivation_money,
                    motivation_family:    !!p.motivation_family,
                    motivation_pregnancy: !!p.motivation_pregnancy,
                    motivation_fitness:   !!p.motivation_fitness,
                    quit_method:          p.quit_method          || '',
                    quit_date:            p.quit_date            || '',
                };
                this.isEditing = true;
            } else {
                this.formData = {
                    cigarettes_per_day: '', years_smoking: '', cigarettes_per_pack: 20,
                    pack_price: '', wake_up_smoke: '', first_cigarette_time: '',
                    trigger_coffee: false, trigger_stress: false, trigger_alcohol: false,
                    trigger_after_meal: false, trigger_social: false, trigger_driving: false,
                    trigger_boredom: false, previous_attempts: '', previous_methods: '',
                    motivation_health: false, motivation_money: false, motivation_family: false,
                    motivation_pregnancy: false, motivation_fitness: false,
                    quit_method: '', quit_date: '',
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
                fd.append('csrf_token', window.__QUIT__?.csrfToken || '');
                if (this.isEditing) fd.append('edit', '1');
                Object.entries(this.formData).forEach(([k, v]) => {
                    if (v !== '' && v !== null && v !== false) fd.append(k, v);
                });

                const res = await fetch('/api/save_quit_profile.php', {
                    method: 'POST',
                    body: fd
                });

                if (res.ok) {
                    window.showToast(
                        this.isEditing ? 'Профиль обновлён!' : 'Профиль сохранён!',
                        'success'
                    );
                    setTimeout(() => {
                        window.location.href = window.__QUIT__?.pageUrl || location.href;
                    }, 800);
                } else {
                    window.showToast('Ошибка сервера', 'error');
                }
            } catch (e) {
                window.showToast('Ошибка соединения', 'error');
            } finally {
                this.submitting = false;
            }
        }
    }));

    // ─── Алиас: quitForm → quitApp ───
    Alpine.data('quitForm', () => {
        return Alpine.$data(document.querySelector('[x-data*="quitApp"]')) || (() => {
            return {
                showQuestionnaire: false,
                isEditing: false,
                submitting: false,
                step: 1,
                formData: {},
                methodNames: {},
                get methodTitle() { return ''; },
                get stepValid()   { return true; },
                openQuestionnaire() {},
                closeQuestionnaire() {},
                nextStep() {},
                prevStep() {},
                submitQuestionnaire() {},
            };
        })();
    });

    // ─── Дневник ───
    Alpine.data('quitDiary', () => ({
        entries: [],
        loading: true,
        mood: '',
        note: '',
        cigarettes_today: 0,
        saving: false,

        async init() { await this.loadEntries(); },

        async loadEntries() {
            this.loading = true;
            try {
                const res  = await fetch('/api/quit_diary.php?action=list');
                const data = await res.json();
                this.entries = data.entries || [];
            } catch (e) {
                this.entries = [];
            } finally {
                this.loading = false;
            }
        },

        async addEntry() {
            if (!this.mood) {
                window.showToast('Выберите настроение', 'info');
                return;
            }
            this.saving = true;
            try {
                const res = await fetch('/api/quit_diary.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        mood: this.mood,
                        note: this.note,
                        cigarettes_today: this.cigarettes_today
                    })
                });
                const data = await res.json();
                if (data.ok) {
                    this.mood = '';
                    this.note = '';
                    this.cigarettes_today = 0;
                    await this.loadEntries();
                    window.showToast('Запись добавлена', 'success');
                } else {
                    window.showToast(data.error || 'Ошибка', 'error');
                }
            } catch (e) {
                window.showToast('Ошибка соединения', 'error');
            } finally {
                this.saving = false;
            }
        },

        async deleteEntry(id) {
            try {
                await fetch('/api/quit_diary.php?id=' + id, { method: 'DELETE' });
                await this.loadEntries();
            } catch (e) {
                window.showToast('Ошибка удаления', 'error');
            }
        },

        // Возвращает HTML-строку иконки, а не эмодзи.
        // Ключи mood-1…mood-5 должны быть в window.__QUIT_ICONS__.
        // Если их нет — фолбэк на 'happy'.
        get moodIcon() {
            const map = {
                great:   quitIcon('mood-1') || quitIcon('happy'),
                good:    quitIcon('mood-2') || quitIcon('happy'),
                neutral: quitIcon('mood-3') || quitIcon('happy'),
                bad:     quitIcon('mood-4') || quitIcon('happy'),
                awful:   quitIcon('mood-5') || quitIcon('happy'),
            };
            return map;
        }
    }));

    // ─── Прогресс ───
    Alpine.data('quitProgress', () => ({
        cravesData: [],
        loading: true,

        async init() { await this.loadCraves(); },

        async loadCraves() {
            this.loading = true;
            try {
                const res  = await fetch('/api/quit_craving.php?action=list');
                const data = await res.json();
                this.cravesData = data.cravings || [];
            } catch (e) {
                this.cravesData = [];
            } finally {
                this.loading = false;
            }
        },

        get totalCravings() { return this.cravesData.length; },
        get copedCravings() { return this.cravesData.filter(c => c.coped).length; },
        get copeRate()      { return this.totalCravings ? Math.round(this.copedCravings / this.totalCravings * 100) : 0; }
    }));

    // ─── Курс ───
    Alpine.data('quitCourse', (steps, method) => ({
        steps: steps || [],
        method: method,
        completed: [],
        expanded: [],

        init() {
            try {
                const saved = JSON.parse(localStorage.getItem('quit_course_' + this.method) || '[]');
                this.completed = Array.isArray(saved) ? saved : [];
            } catch (e) {
                this.completed = [];
            }

            // Открываем первый незавершённый этап
            const firstUnfinished = this.steps.findIndex((_, i) => !this.completed.includes(i));
            if (firstUnfinished >= 0) this.expanded[firstUnfinished] = true;
        },

        isCompleted(idx)  { return this.completed.includes(idx); },
        toggleExpand(idx) { this.expanded[idx] = !this.expanded[idx]; },

        toggleStep(idx) {
            if (this.isCompleted(idx)) {
                this.completed = this.completed.filter(i => i !== idx);
            } else {
                this.completed.push(idx);
                // Иконку рисует шаблон тоста — здесь только тип
                if (this.completed.length === this.steps.length) {
                    window.showToast('Курс пройден!', 'success');
                } else {
                    window.showToast('Этап отмечен', 'success');
                }
            }
            try {
                localStorage.setItem('quit_course_' + this.method, JSON.stringify(this.completed));
            } catch (e) {}
        },

        // Иконка ресурса — SVG-строка вместо эмодзи.
        // Ключи должны быть в window.__QUIT_ICONS__: book, article, audio, video, check.
        resourceIcon(type) {
            const map = {
                book:      'book',
                article:   'openbook',
                audio:     'play',
                video:     'video',
                checklist: 'check',
            };
            const name = map[type] || 'dot';
            return quitIcon(name);
        },

        resourceClass(type) {
            return {
                'bg-blue-50 text-blue-600 hover:bg-blue-100':       type === 'book' || type === 'article',
                'bg-purple-50 text-purple-600 hover:bg-purple-100': type === 'audio',
                'bg-rose-50 text-rose-600 hover:bg-rose-100':       type === 'video',
                'bg-gray-100 text-gray-600 hover:bg-gray-200':      type === 'checklist',
            };
        },

        renderMarkdown(text) {
            if (!text) return '';
            return text
                .replace(/^### (.+)$/gm, '<h3 class="text-base font-bold text-gray-800 mt-4 mb-2">$1</h3>')
                .replace(/^## (.+)$/gm,  '<h2 class="text-lg font-bold text-gray-800 mt-4 mb-2">$1</h2>')
                .replace(/^# (.+)$/gm,   '<h1 class="text-xl font-bold text-gray-800 mt-4 mb-2">$1</h1>')
                .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                .replace(/^- (.+)$/gm,     '<li class="ml-4 list-disc">$1</li>')
                .replace(/^\d+\. (.+)$/gm, '<li class="ml-4 list-decimal">$1</li>')
                .replace(/\n{2,}/g, '</p><p class="my-2">')
                .replace(/^/, '<p class="my-2">')
                .replace(/$/, '</p>');
        },
    }));
});