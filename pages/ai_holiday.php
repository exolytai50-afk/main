<?php
/**
 * GigaChef — AI-планировщик праздника. Этап 1: меню.
 *   /ai-holiday          — новая форма
 *   /ai-holiday?id=123   — продолжить черновик
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/holiday_helpers.php';

if (!function_exists('is_logged_in') || !is_logged_in()) {
    header('Location: ' . url('login'));
    exit;
}

$user   = current_user();
$userId = (int)$user['id'];

// ---------- данные для формы ----------
$occasionsOut = [];
foreach (holiday_occasions() as $o) {
    $occasionsOut[] = ['slug' => $o['slug'], 'name' => $o['name'], 'icon_html' => icon($o['icon'], '', 1.5)];
}
$occasionsOut[] = ['slug' => 'custom', 'name' => 'Другой повод', 'icon_html' => icon('balloons', '', 1.5)];

$coursesOut = [];
foreach (HOLIDAY_COURSES as $key => $c) {
    $coursesOut[] = ['key' => $key, 'label' => $c['label'], 'icon_html' => icon($c['icon'], '', 1.25)];
}

$budgetsOut = [];
foreach (HOLIDAY_BUDGETS as $key => $b) {
    $budgetsOut[] = ['key' => $key, 'label' => $b['label'], 'hint' => $b['short']];
}

$agesOut = [];
foreach (HOLIDAY_AGES as $key => $label) {
    $agesOut[] = ['key' => $key, 'label' => $label];
}

$dietOut = holiday_diet_options();

// ---------- продолжение черновика ----------
$resume = null;
$rid    = (int)($_GET['id'] ?? 0);
if ($rid > 0 && ($h = holiday_find_owned($rid, $userId))) {
    $resume = ['holiday' => holiday_public($h), 'items' => holiday_items((int)$h['id'])];
}

$jf = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
require_once __DIR__ . '/../includes/ads.php';
$aiAdBundle = ad_bundle_json('ai_action');

$scheme          = (($_SERVER['HTTPS'] ?? '') === 'on') ? 'https' : 'http';
$pageUrl         = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'gigachef.ru') . '/ai-holiday';
$pageTitle       = 'Планировщик праздника — меню, сервировка и декор | GigaChef';
$pageDescription = 'Составьте меню праздника: готовые рецепты из базы и AI-подбор блюд под число гостей, бюджет и диету.';
$pageH1          = 'Планировщик праздника';

require_once __DIR__ . '/../includes/header.php';
?>

<section class="max-w-4xl mx-auto px-4 sm:px-6 py-8" x-data="holidayWizard()">

    <!-- Заголовок -->
    <div class="text-center mb-6">
        <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-800 inline-flex items-center gap-2">
            <?= icon('balloons', '', 1.6) ?>
            <span><?= e($pageH1) ?></span>
        </h1>
        <p class="mt-2 text-gray-500">Меню, сервировка стола, напитки и декор: шаг за шагом</p>
    </div>

    <!-- Этапы -->
    <ol class="flex items-center justify-center gap-3 sm:gap-6 mb-8 text-xs sm:text-sm font-bold">
        <?php foreach ([1 => 'Меню', 2 => 'Стол и напитки', 3 => 'Декор', 4 => 'Мои праздники'] as $n => $label): ?>
            <li class="flex items-center gap-2 <?= $n === 1 ? 'text-rose-600' : 'text-gray-400' ?>">
                <span class="w-6 h-6 rounded-full flex items-center justify-center <?= $n === 1 ? 'bg-rose-500 text-white' : 'bg-gray-200 text-gray-500' ?>"><?= $n ?></span>
                <span class="hidden sm:inline"><?= e($label) ?></span>
            </li>
        <?php endforeach; ?>
    </ol>

    <!-- ===================== ФОРМА ===================== -->
    <div x-show="!holiday" style="display:none" class="bg-white rounded-3xl shadow-md p-5 sm:p-8 space-y-6">

        <!-- Повод -->
        <div>
            <label class="block text-sm font-bold text-gray-600 mb-2">Повод</label>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                <template x-for="o in occasions" :key="o.slug">
                    <button type="button" @click="form.occasion = o.slug"
                        :class="form.occasion === o.slug ? 'holiday-btn-active' : 'holiday-btn'"
                        class="flex items-center gap-2 px-3 py-2.5 rounded-xl border-2 text-left text-sm font-bold transition">
                        <span x-html="o.icon_html" class="shrink-0"></span>
                        <span class="truncate" x-text="o.name"></span>
                    </button>
                </template>
            </div>
            <input type="text" x-show="form.occasion === 'custom'" style="display:none"
                   x-model="form.custom_name" maxlength="60" placeholder="Например: проводы друга"
                   class="mt-3 w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-rose-300">
        </div>

        <!-- Дата и гости -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-bold text-gray-600 mb-2">Дата <span class="font-normal text-gray-400">(необязательно)</span></label>
                <input type="date" x-model="form.event_date"
                       class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-rose-300">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-600 mb-2">Сколько гостей</label>
                <div class="flex items-center gap-3">
                    <button type="button" @click="form.guests = Math.max(1, (parseInt(form.guests) || 1) - 1)"
                            class="w-11 h-11 rounded-xl bg-gray-100 hover:bg-gray-200 text-xl font-bold text-gray-600">−</button>
                    <input type="number" min="1" max="30" x-model.number="form.guests"
                           class="w-20 text-center px-2 py-3 rounded-xl border border-gray-200 outline-none focus:border-rose-300 font-bold">
                    <button type="button" @click="form.guests = Math.min(30, (parseInt(form.guests) || 0) + 1)"
                            class="w-11 h-11 rounded-xl bg-gray-100 hover:bg-gray-200 text-xl font-bold text-gray-600">+</button>
                    <span class="text-sm text-gray-500" x-text="guestsWord"></span>
                </div>
            </div>
        </div>

        <!-- Бюджет -->
        <div>
            <label class="block text-sm font-bold text-gray-600 mb-2">Бюджет</label>
            <div class="grid grid-cols-3 gap-2">
                <template x-for="b in budgets" :key="b.key">
                    <button type="button" @click="form.budget = b.key"
                        :class="form.budget === b.key ? 'budget-btn-active' : 'budget-btn'"
                        class="px-2 py-3 rounded-xl border-2 text-center transition">
                        <div class="font-bold text-sm" x-text="b.label"></div>
                        <div class="text-[11px] opacity-70 mt-0.5" x-text="b.hint"></div>
                    </button>
                </template>
            </div>
        </div>

        <!-- Кто придёт -->
        <div>
            <label class="block text-sm font-bold text-gray-600 mb-2">Кто придёт</label>
            <div class="grid grid-cols-3 gap-2">
                <template x-for="a in ages" :key="a.key">
                    <button type="button" @click="form.age_group = a.key"
                        :class="form.age_group === a.key ? 'age-btn-active' : 'age-btn'"
                        class="px-2 py-2.5 rounded-xl border-2 text-center text-sm font-bold transition"
                        x-text="a.label">
                    </button>
                </template>
            </div>
        </div>

        <!-- Диета -->
        <div>
            <button type="button" @click="showDiet = !showDiet"
                    class="text-sm font-bold text-gray-600 inline-flex items-center gap-2">
                <span>Диета и ограничения гостей</span>
                <span class="text-gray-400 font-normal" x-text="form.diet.length ? '(выбрано: ' + form.diet.length + ')' : '(необязательно)'"></span>
            </button>
            <div x-show="showDiet" style="display:none" class="mt-3 space-y-3">
                <template x-for="g in dietGroups" :key="g.key">
                    <div>
                        <div class="text-xs font-semibold text-gray-500 mb-1.5" x-text="g.label"></div>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="t in g.tags" :key="t.slug">
                                <button type="button" @click="toggleDiet(t.slug)"
                                    :class="form.diet.includes(t.slug) ? 'diet-btn-active' : 'diet-btn'"
                                    class="px-3 py-1.5 rounded-lg border text-xs font-semibold transition"
                                    x-text="t.name">
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Пожелания -->
        <div>
            <label class="block text-sm font-bold text-gray-600 mb-2">Пожелания <span class="font-normal text-gray-400">(необязательно)</span></label>
            <textarea x-model="form.notes" rows="2" maxlength="300"
                      placeholder="Например: без рыбы, хочется что-то запечённое"
                      class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-rose-300 resize-none"></textarea>
        </div>

        <button type="button" @click="buildMenu()" :disabled="loading"
                class="w-full py-3.5 rounded-xl bg-gradient-to-r from-rose-300 to-amber-300 text-rose-900 font-bold hover:shadow-md transition disabled:opacity-60 inline-flex items-center justify-center gap-2">
            <?= icon('ai') ?>
            <span>Составить меню</span>
        </button>
    </div>

    <!-- ===================== РЕЗУЛЬТАТ ===================== -->
    <div x-show="holiday" style="display:none">

        <div class="bg-gradient-to-br from-rose-100 to-amber-100 rounded-3xl p-5 sm:p-6 mb-6">
            <h2 class="text-xl sm:text-2xl font-extrabold text-gray-800 break-words" x-text="holiday?.title"></h2>
            <div class="flex flex-wrap gap-2 mt-3 text-xs sm:text-sm font-semibold text-gray-600">
                <span class="px-2.5 py-1 rounded-full bg-white/70" x-text="holiday?.guests + ' ' + guestsWordFor(holiday?.guests)"></span>
                <span class="px-2.5 py-1 rounded-full bg-white/70" x-text="'Бюджет: ' + budgetLabel(holiday?.budget)"></span>
                <span class="px-2.5 py-1 rounded-full bg-white/70" x-text="ageLabel(holiday?.age_group)"></span>
            </div>
        </div>

        <!-- Прогресс -->
        <div x-show="adVisible" style="display:none" class="mb-6 ai-ad-pause-container">
            <div class="ai-ad-pause-header flex justify-between items-center gap-3">
                    <span class="inline-flex items-center gap-1.5 min-w-0">
                        <?= icon('hourglass', 'gc-icon-xs', 1) ?>
                        <span class="truncate" x-text="stepText || 'Работаем…'"></span>
                    </span>
                    <span class="text-rose-600 font-bold bg-rose-50 px-2 py-0.5 rounded-md text-xs shrink-0">
                        <span x-text="adTimer"></span> сек
                    </span>
                </div>
                <div class="px-4 pt-2 text-xs text-gray-400" x-show="adProvider">
                    <span x-text="'Партнёр: ' + adProvider"></span>
                </div>
                <div x-html="adHtml" class="p-4"></div>
                <div x-show="!adHtml" class="p-4 text-sm text-gray-400 text-center">Реклама загружается…</div>
            </div>

        <p x-show="error" style="display:none" class="mb-4 text-rose-600 text-sm font-semibold" x-text="error"></p>

        <!-- Разделы меню -->
        <template x-for="c in courses" :key="c.key">
            <div x-show="(plan[c.key] || 0) > 0" style="display:none" class="mb-6">
                <h3 class="flex items-center gap-2 font-extrabold text-gray-700 mb-2">
                    <span x-html="c.icon_html"></span>
                    <span x-text="c.label"></span>
                    <span class="text-xs font-bold text-gray-400" x-text="itemsOf(c.key).length + ' из ' + (plan[c.key] || 0)"></span>
                </h3>

                <div class="space-y-2">
                    <template x-for="it in itemsOf(c.key)" :key="it.id">
                        <div class="bg-white rounded-2xl shadow-sm p-4 flex items-start gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5 mb-1">
                                    <span x-show="it.source === 'db'" style="display:none"
                                          class="px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-100 text-emerald-700">Из базы</span>
                                    <span x-show="it.source === 'ai'" style="display:none"
                                          class="px-2 py-0.5 rounded-md text-[11px] font-bold bg-violet-100 text-violet-700">Придумал AI</span>
                                    <span class="text-[11px] text-gray-400" x-text="'на ' + it.servings + ' порц.'"></span>
                                </div>

                                <a x-show="it.source === 'db' && it.slug" style="display:none"
                                   :href="'/recipe/' + encodeURIComponent(it.slug || '')" target="_blank"
                                   class="font-bold text-gray-800 hover:text-rose-600 break-words" x-text="it.title"></a>
                                <span x-show="!(it.source === 'db' && it.slug)" style="display:none"
                                      class="font-bold text-gray-800 break-words" x-text="it.title"></span>

                                <p class="text-sm text-gray-500 mt-1 line-clamp-2" x-show="it.description" x-text="it.description"></p>
                                <div class="flex flex-wrap gap-3 mt-2 text-xs text-gray-400">
                                    <span x-show="it.total_time > 0" x-text="it.total_time + ' мин'"></span>
                                    <span x-show="it.calories > 0" x-text="it.calories + ' ккал / порц.'"></span>
                                    <span x-text="it.ingredients_count + ' ингр.'"></span>
                                    <span x-text="it.difficulty"></span>
                                </div>
                            </div>
                            <button type="button" @click="removeItem(it)" :disabled="loading" title="Убрать из меню"
                                    class="w-8 h-8 shrink-0 flex items-center justify-center rounded-lg bg-rose-50 text-rose-500 hover:bg-rose-100 transition disabled:opacity-40">
                                <?= icon('trash', 'gc-icon-xs') ?>
                            </button>
                        </div>
                    </template>

                    <template x-for="n in (loading ? slotsLeft(c.key) : 0)" :key="'sk-' + c.key + n">
                        <div class="rounded-2xl border-2 border-dashed border-rose-200 bg-rose-50/50 p-4 text-sm text-rose-400 animate-pulse">
                            Подбираем блюдо…
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <!-- Не хватает блюд -->
        <div x-show="!loading && missingTotal > 0" style="display:none"
             class="mb-6 p-4 rounded-2xl bg-amber-50 border border-amber-200">
            <p class="text-sm text-amber-800 font-semibold mb-3"
               x-text="'В меню не хватает блюд: ' + missingTotal + '. AI предложит названия и составит рецепты.'"></p>
            <button type="button" @click="fillMissing()"
                    class="px-4 py-2 rounded-xl bg-amber-400 text-white text-sm font-bold hover:bg-amber-500 transition">
                Добавить недостающие блюда
            </button>
        </div>

        <!-- Действия -->
        <div x-show="!loading" style="display:none" class="flex flex-col sm:flex-row gap-3">
            <a x-show="holiday && items.length > 0" style="display:none"
                :href="holiday ? '/ai-holiday/table?id=' + holiday.id : '#'"
                class="flex-1 py-3 rounded-xl text-center font-bold bg-gradient-to-r from-rose-300 to-amber-300 text-rose-900 hover:shadow-md transition">
                Далее: стол и напитки →
            </a>
            <button type="button" @click="reset()"
                    class="px-5 py-3 rounded-xl bg-white border border-gray-200 text-gray-600 font-bold hover:bg-gray-50 transition">
                Начать заново
            </button>
        </div>
        <p x-show="!loading" style="display:none" class="mt-3 text-xs text-gray-400">
            Меню сохранено как черновик. К нему можно вернуться по ссылке из адресной строки.
        </p>
    </div>
</section>

<script src="<?= e(url('assets/js/ad-pause.js')) ?>?v=6"></script>

<script>
window.__AI_AD__ = <?= json_encode($aiAdBundle, $jf) ?>;
</script>
<script>

function holidayWizard() {
    return {
        occasions:  <?= json_encode($occasionsOut, $jf) ?>,
        dietGroups: <?= json_encode($dietOut, $jf) ?>,
        courses:    <?= json_encode($coursesOut, $jf) ?>,
        budgets:    <?= json_encode($budgetsOut, $jf) ?>,
        ages:       <?= json_encode($agesOut, $jf) ?>,
        resume:     <?= json_encode($resume, $jf) ?>,

        form: { occasion: '', custom_name: '', event_date: '', guests: 4, budget: 'mid', age_group: 'adults', diet: [], notes: '' },
        showDiet: false,

        holiday: null,
        items: [],
        plan: {},

        loading: false,
        stepText: '',
        error: '',
        
        ...gcAdPause(10000),   // adVisible, adTimer, adHtml, adProvider, adShow, adRelease, adHold

        init() {
            if (this.resume && this.resume.holiday) {
                this.holiday = this.resume.holiday;
                this.plan    = this.resume.holiday.plan || {};
                this.items   = this.resume.items || [];
            }
        },

        // ---------- вычисляемое ----------
        guestsWordFor(n) {
            n = parseInt(n) || 0;
            const m10 = n % 10, m100 = n % 100;
            if (m10 === 1 && m100 !== 11) return 'гость';
            if (m10 >= 2 && m10 <= 4 && (m100 < 10 || m100 >= 20)) return 'гостя';
            return 'гостей';
        },
        get guestsWord() { return this.guestsWordFor(this.form.guests); },
        budgetLabel(k) { const b = this.budgets.find(x => x.key === k); return b ? b.label : ''; },
        ageLabel(k)    { const a = this.ages.find(x => x.key === k);    return a ? a.label : ''; },

        itemsOf(course)   { return this.items.filter(i => i.course === course); },
        slotsLeft(course) { return Math.max(0, (this.plan[course] || 0) - this.itemsOf(course).length); },
        get missingTotal() { return this.courses.reduce((s, c) => s + this.slotsLeft(c.key), 0); },

        toggleDiet(slug) {
            const i = this.form.diet.indexOf(slug);
            if (i >= 0) this.form.diet.splice(i, 1);
            else if (this.form.diet.length < 8) this.form.diet.push(slug);
        },

        // ---------- запросы ----------
        async api(action, payload = {}) {
            const res = await fetch('/api/holiday.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.__CSRF_TOKEN__ || '' },
                body: JSON.stringify({ action, ...payload })
            });
            let data;
            try { data = await res.json(); } catch (e) { throw new Error('Сервер вернул неверный ответ'); }
            if (!data.ok) {
                const err = new Error(data.error || 'Ошибка запроса');
                err.status = res.status;
                throw err;
            }
            return data;
        },

        // ---------- сценарий ----------
        async buildMenu() {
            if (this.loading) return;
            this.error = '';
            if (!this.form.occasion) { this.error = 'Выберите повод'; return; }
            if (this.form.occasion === 'custom' && this.form.custom_name.trim().length < 3) {
                this.error = 'Опишите повод (минимум 3 символа)';
                return;
            }
            this.form.guests = Math.max(1, Math.min(30, parseInt(this.form.guests) || 4));
        
            this.loading = true;
            try {
                await this.adHold((async () => {
                    this.stepText = 'Создаём праздник…';
                    const c = await this.api('create', this.form);
                    this.holiday = c.holiday;
                    this.plan    = c.holiday.plan || {};
                    this.items   = [];
                    history.replaceState(null, '', '/ai-holiday?id=' + c.holiday.id);
        
                    this.stepText = 'Ищем готовые рецепты в базе…';
                    const p = await this.api('plan', { holiday_id: this.holiday.id });
                    this.items = p.items || [];
        
                    if (this.missingTotal > 0) await this.generateMissing();
                })());
            } catch (e) {
                this.error = e.message;
            } finally {
                this.loading = false;
                this.stepText = '';
            }
        },

        async fillMissing() {
            if (this.loading || !this.holiday) return;
            this.error = '';
            this.loading = true;
            try {
                await this.adHold(this.generateMissing());
            } catch (e) {
                this.error = e.message;
            } finally {
                this.loading = false;
                this.stepText = '';
            }
        },

        // Один запрос на названия + по одному запросу на каждый рецепт
        async generateMissing() {
            this.stepText = 'AI подбирает недостающие блюда…';
            const s = await this.api('suggest', { holiday_id: this.holiday.id });
            const queue = s.queue || [];

            let failed = 0;
            for (let i = 0; i < queue.length; i++) {
                const q = queue[i];
                this.stepText = 'Готовим рецепт ' + (i + 1) + ' из ' + queue.length + ': ' + q.title;
                try {
                    const r = await this.api('recipe', { holiday_id: this.holiday.id, course: q.course, title: q.title });
                    this.items.push(r.item);
                } catch (e) {
                    failed++;
                    if (e.status === 429) { this.error = e.message; break; }
                }
            }
            if (failed > 0 && !this.error) {
                this.error = 'Не удалось подготовить блюд: ' + failed + '. Нажмите «Добавить недостающие блюда».';
            }
        },

        async removeItem(it) {
            if (this.loading || !confirm('Убрать «' + it.title + '» из меню?')) return;
            this.error = '';
            this.loading = true;
            try {
                await this.adHold((async () => {
                    await this.api('remove', { item_id: it.id });
                    this.items = this.items.filter(x => x.id !== it.id);
                })());
            } catch (e) {
                this.error = e.message;
            } finally {
                this.loading = false;
            }
        },

        reset() {
            if (this.loading) return;
            this.holiday = null;
            this.items = [];
            this.plan = {};
            this.error = '';
            history.replaceState(null, '', '/ai-holiday');
        }
    };
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
