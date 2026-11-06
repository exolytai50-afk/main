<?php
/**
 * GigaChef — планировщик праздника, этапы 2 и 3.
 *   /ai-holiday/table?id=N — сервировка и напитки   ($holidayStage = 2)
 *   /ai-holiday/decor?id=N — оформление помещения   ($holidayStage = 3)
 * $holidayStage задаёт роутер (index.php).
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/holiday_tips_helpers.php';
require_once __DIR__ . '/../includes/ads.php';
if (!function_exists('is_logged_in') || !is_logged_in()) {
    header('Location: ' . url('login'));
    exit;
}
$aiAdBundle = ad_bundle_json('ai_action');

$stage  = ((int)($holidayStage ?? 2) === 3) ? 3 : 2;
$user   = current_user();
$userId = (int)$user['id'];
$id     = (int)($_GET['id'] ?? 0);

$h = $id > 0 ? holiday_find_owned($id, $userId) : null;
if (!$h) {
    header('Location: ' . url('ai-holiday'));
    exit;
}

// Сервировка и напитки строятся по меню — без меню возвращаем на этап 1
$menu = ht_menu_by_course((int)$h['id']);
if ($stage === 2 && !$menu) {
    header('Location: ' . url('ai-holiday?id=' . $id));
    exit;
}

$tips = ht_load_tips((int)$h['id']);

$sectionsOut = [];
foreach (HOLIDAY_TIP_SECTIONS as $key => $s) {
    if ($s['stage'] === $stage) $sectionsOut[] = ['key' => $key, 'label' => $s['label']];
}

$holidayOut = holiday_public($h) + [
    'budget_label' => HOLIDAY_BUDGETS[$h['budget']]['label'] ?? '',
    'age_label'    => HOLIDAY_AGES[$h['age_group']] ?? '',
];

$linkMenu  = url('ai-holiday?id=' . $id);
$linkTable = url('ai-holiday/table?id=' . $id);
$linkDecor = url('ai-holiday/decor?id=' . $id);

$jf = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

$scheme          = (($_SERVER['HTTPS'] ?? '') === 'on') ? 'https' : 'http';
$pageUrl         = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'gigachef.ru') . ($stage === 2 ? '/ai-holiday/table' : '/ai-holiday/decor');
$pageH1          = $stage === 2 ? 'Стол и напитки' : 'Оформление помещения';
$pageTitle       = $pageH1 . ' — ' . $h['title'] . ' | GigaChef';
$pageDescription = 'Советы по сервировке, напиткам и декору для праздника.';

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.hw-root{--hw-card:#fff;--hw-line:#e5e7eb;--hw-title:#1f2937;--hw-text:#4b5563;--hw-sub:#6b7280;--hw-accent:#e11d48;--hw-soft:#ffe4e6;--hw-pill:#f3f4f6;--hw-hero:linear-gradient(135deg,#ffe4e6,#fef3c7);}
.theme-dark .hw-root{--hw-card:#1e293b;--hw-line:#334155;--hw-title:#f1f5f9;--hw-text:#cbd5e1;--hw-sub:#94a3b8;--hw-accent:#fb7185;--hw-soft:rgba(244,63,94,.18);--hw-pill:#334155;--hw-hero:linear-gradient(135deg,rgba(244,63,94,.22),rgba(245,158,11,.16));}
.hw-root .hw-card{background:var(--hw-card);border:1px solid var(--hw-line);border-radius:1.25rem;padding:1.25rem}
.hw-root .hw-hero{background:var(--hw-hero);border-radius:1.5rem;padding:1.25rem 1.5rem;color:var(--hw-title)}
.hw-root .hw-title{color:var(--hw-title)}
.hw-root .hw-text{color:var(--hw-text)}
.hw-root .hw-sub{color:var(--hw-sub)}
.hw-root .hw-accent{color:var(--hw-accent)}
.hw-root .hw-pill{display:inline-block;background:var(--hw-pill);color:var(--hw-text);padding:.2rem .65rem;border-radius:999px;font-size:.75rem;font-weight:600}
.hw-root .hw-badge{display:inline-block;background:var(--hw-soft);color:var(--hw-accent);padding:.1rem .5rem;border-radius:.5rem;font-size:.7rem;font-weight:700}
.hw-root .hw-row{border-top:1px solid var(--hw-line);padding:.75rem 0}
.hw-root .hw-row:first-child{border-top:0;padding-top:0}
.hw-root .hw-cta{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;width:100%;padding:.9rem 1rem;border-radius:.75rem;font-weight:800;color:#4c0519;background:linear-gradient(90deg,#fb7185,#fbbf24);border:0;cursor:pointer;transition:filter .15s,box-shadow .15s}
.hw-root .hw-cta:hover:not(:disabled){filter:brightness(1.05);box-shadow:0 6px 18px rgba(244,63,94,.25)}
.hw-root .hw-cta:disabled{opacity:.55;cursor:wait}
.hw-root .hw-btn{display:inline-flex;align-items:center;justify-content:center;gap:.4rem;padding:.5rem .9rem;border-radius:.75rem;font-size:.8rem;font-weight:700;color:var(--hw-text);background:var(--hw-card);border:1px solid var(--hw-line);cursor:pointer;transition:border-color .15s}
.hw-root .hw-btn:hover:not(:disabled){border-color:var(--hw-accent)}
.hw-root .hw-btn:disabled{opacity:.5;cursor:not-allowed}
.hw-root .hw-error{border:1px solid #fda4af;background:#fff1f2;color:#9f1239;border-radius:.75rem;padding:.75rem 1rem;font-size:.875rem;font-weight:600}
.theme-dark .hw-root .hw-error{border-color:rgba(251,113,133,.5);background:rgba(244,63,94,.14);color:#fecdd3}
.hw-root .hw-step{display:flex;align-items:center;gap:.5rem;font-weight:700;color:var(--hw-sub)}
.hw-root .hw-step-n{width:1.5rem;height:1.5rem;border-radius:999px;display:flex;align-items:center;justify-content:center;background:var(--hw-pill);color:var(--hw-sub)}
.hw-root .hw-step-on{color:var(--hw-accent)}
.hw-root .hw-step-on .hw-step-n{background:#f43f5e;color:#fff}
.hw-root .hw-step-done .hw-step-n{background:var(--hw-soft);color:var(--hw-accent)}
</style>

<section class="hw-root max-w-4xl mx-auto px-4 sm:px-6 py-8" x-data="holidayTips()">

    <div class="text-center mb-6">
        <h1 class="hw-title text-3xl sm:text-4xl font-extrabold inline-flex items-center gap-2">
            <?= icon('balloons', '', 1.6) ?>
            <span><?= e($pageH1) ?></span>
        </h1>
        <p class="hw-sub mt-2">Каждый раздел — отдельный короткий AI-запрос с учётом вашего меню и бюджета</p>
    </div>

    <!-- Этапы -->
    <ol class="flex items-center justify-center gap-3 sm:gap-6 mb-8 text-xs sm:text-sm">
        <?php
        $steps = [
            1 => ['Меню', $linkMenu],
            2 => ['Стол и напитки', $linkTable],
            3 => ['Декор', $linkDecor],
            4 => ['Мои праздники', null],
        ];
        foreach ($steps as $n => [$label, $href]):
            $cls = $n === $stage ? 'hw-step hw-step-on' : ($n < $stage ? 'hw-step hw-step-done' : 'hw-step');
        ?>
            <li>
                <?php if ($href && $n !== $stage): ?>
                    <a href="<?= e($href) ?>" class="<?= $cls ?>">
                        <span class="hw-step-n"><?= $n ?></span>
                        <span class="hidden sm:inline"><?= e($label) ?></span>
                    </a>
                <?php else: ?>
                    <span class="<?= $cls ?>">
                        <span class="hw-step-n"><?= $n ?></span>
                        <span class="hidden sm:inline"><?= e($label) ?></span>
                    </span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>

    <!-- Шапка праздника -->
    <div class="hw-hero mb-6">
        <h2 class="text-xl sm:text-2xl font-extrabold break-words" x-text="holiday.title"></h2>
        <div class="flex flex-wrap gap-2 mt-3">
            <span class="hw-pill" x-text="holiday.guests + ' ' + guestsWord(holiday.guests)"></span>
            <span class="hw-pill" x-text="'Бюджет: ' + holiday.budget_label"></span>
            <span class="hw-pill" x-text="holiday.age_label"></span>
        </div>
    </div>

    <?php if ($menu): ?>
        <details class="hw-card mb-6">
            <summary class="hw-title font-bold cursor-pointer">
                Учтено блюд из меню: <?= array_sum(array_map('count', $menu)) ?>
            </summary>
            <div class="mt-3 space-y-1.5 text-sm">
                <?php foreach ($menu as $label => $titles): ?>
                    <p class="hw-text"><span class="hw-title font-bold"><?= e($label) ?>:</span> <?= e(implode(', ', $titles)) ?></p>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endif; ?>

    <!-- Прогресс и ошибки (видны всегда) -->
    <div x-show="adVisible" style="display:none" class="mb-6 ai-ad-pause-container">
        <div class="ai-ad-pause-header flex justify-between items-center gap-3">
            <span class="inline-flex items-center gap-1.5 min-w-0">
                <?= icon('hourglass', 'gc-icon-xs', 1) ?>
                <span class="truncate" x-text="stepText || 'Подбираем…'"></span>
            </span>
            <span class="font-bold text-xs shrink-0"><span x-text="adTimer"></span> сек</span>
        </div>
        <div class="px-4 pt-2 text-xs text-gray-400" x-show="adProvider">
            <span x-text="'Партнёр: ' + adProvider"></span>
        </div>
        <div x-html="adHtml" class="p-4"></div>
        <div x-show="!adHtml" class="p-4 text-sm text-gray-400 text-center">Реклама загружается…</div>
    </div>

    <p x-show="error" style="display:none" class="hw-error mb-4" x-text="error"></p>

    <!-- Главная кнопка -->
    <div x-show="missing.length > 0" style="display:none" class="mb-6">
        <button type="button" class="hw-cta" :disabled="loading" @click="generate(missing)">
            <?= icon('ai') ?>
            <span x-text="loading ? 'Подбираем…' : (missing.length === sections.length ? mainLabel : 'Подобрать недостающее')"></span>
        </button>
    </div>

<?php if ($stage === 2): ?>

    <!-- ===== Сервировка ===== -->
    <div class="hw-card mb-5">
        <div class="flex items-center justify-between gap-3 mb-3">
            <h3 class="hw-title font-extrabold text-lg inline-flex items-center gap-2">
                <?= icon('dinner') ?><span>Сервировка стола</span>
            </h3>
            <button type="button" class="hw-btn" x-show="serving" style="display:none"
                    :disabled="loading" @click="generate(['serving'])">Обновить</button>
        </div>

        <p x-show="current === 'serving'" style="display:none" class="hw-sub text-sm animate-pulse">Подбираем сервировку…</p>
        <p x-show="!serving && current !== 'serving'" style="display:none" class="hw-sub text-sm">Пока пусто.</p>

        <template x-if="serving">
            <div>
                <p class="hw-text" x-text="serving.style"></p>
                <div class="flex flex-wrap gap-1.5 my-3">
                    <template x-for="(c, i) in serving.palette" :key="'sp' + i">
                        <span class="hw-pill" x-text="c"></span>
                    </template>
                </div>
                <div class="mt-2">
                    <template x-for="(it, i) in serving.items" :key="'si' + i">
                        <div class="hw-row">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="hw-title font-bold" x-text="it.name"></span>
                                <span class="hw-badge" x-show="it.qty" x-text="it.qty"></span>
                            </div>
                            <p class="hw-text text-sm mt-1" x-text="it.advice"></p>
                        </div>
                    </template>
                </div>
                <p class="hw-text text-sm mt-3" x-show="serving.layout">
                    <span class="hw-title font-bold">Расстановка. </span><span x-text="serving.layout"></span>
                </p>
                <ul class="mt-3 space-y-1.5" x-show="serving.tips && serving.tips.length">
                    <template x-for="(t, i) in serving.tips" :key="'st' + i">
                        <li class="hw-text text-sm flex gap-2"><span class="hw-accent">•</span><span x-text="t"></span></li>
                    </template>
                </ul>
            </div>
        </template>
    </div>

    <!-- ===== Напитки ===== -->
    <div class="hw-card mb-5">
        <div class="flex items-center justify-between gap-3 mb-3">
            <h3 class="hw-title font-extrabold text-lg inline-flex items-center gap-2">
                <?= icon('drink') ?><span>Напитки</span>
            </h3>
            <button type="button" class="hw-btn" x-show="drinks" style="display:none"
                    :disabled="loading" @click="generate(['drinks'])">Обновить</button>
        </div>

        <p x-show="current === 'drinks'" style="display:none" class="hw-sub text-sm animate-pulse">Подбираем напитки…</p>
        <p x-show="!drinks && current !== 'drinks'" style="display:none" class="hw-sub text-sm">Пока пусто.</p>

        <template x-if="drinks">
            <div>
                <p class="hw-text" x-show="drinks.intro" x-text="drinks.intro"></p>
                <template x-for="g in drinks.groups" :key="'dg' + g.key">
                    <div class="mt-4">
                        <h4 class="hw-title font-bold mb-2" x-text="g.label"></h4>
                        <template x-for="(it, i) in g.items" :key="'di' + g.key + i">
                            <div class="hw-row">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="hw-title font-bold" x-text="it.name"></span>
                                    <span class="hw-badge" x-show="it.total" x-text="'на всех: ' + it.total"></span>
                                    <span class="hw-sub text-xs" x-text="it.per_guest_ml + ' мл на гостя'"></span>
                                </div>
                                <p class="hw-text text-sm mt-1" x-show="it.note" x-text="it.note"></p>
                            </div>
                        </template>
                    </div>
                </template>
                <p class="hw-sub text-xs mt-3" x-show="drinks.note" x-text="drinks.note"></p>
                <ul class="mt-3 space-y-1.5" x-show="drinks.tips && drinks.tips.length">
                    <template x-for="(t, i) in drinks.tips" :key="'dt' + i">
                        <li class="hw-text text-sm flex gap-2"><span class="hw-accent">•</span><span x-text="t"></span></li>
                    </template>
                </ul>
            </div>
        </template>
    </div>

<?php else: ?>

    <!-- ===== Декор ===== -->
    <div class="hw-card mb-5">
        <div class="flex items-center justify-between gap-3 mb-3">
            <h3 class="hw-title font-extrabold text-lg inline-flex items-center gap-2">
                <?= icon('palette') ?><span>Оформление помещения</span>
            </h3>
            <button type="button" class="hw-btn" x-show="decor" style="display:none"
                    :disabled="loading" @click="generate(['decor'])">Обновить</button>
        </div>

        <p x-show="current === 'decor'" style="display:none" class="hw-sub text-sm animate-pulse">Придумываем оформление…</p>
        <p x-show="!decor && current !== 'decor'" style="display:none" class="hw-sub text-sm">Пока пусто.</p>

        <template x-if="decor">
            <div>
                <p class="hw-text" x-text="decor.concept"></p>
                <div class="flex flex-wrap gap-1.5 my-3">
                    <template x-for="(c, i) in decor.palette" :key="'dp' + i">
                        <span class="hw-pill" x-text="c"></span>
                    </template>
                </div>

                <template x-for="(z, zi) in decor.zones" :key="'dz' + zi">
                    <div class="hw-row">
                        <h4 class="hw-title font-bold" x-text="z.zone"></h4>
                        <ul class="mt-1.5 space-y-1">
                            <template x-for="(idea, ii) in z.ideas" :key="'di' + zi + '-' + ii">
                                <li class="hw-text text-sm flex gap-2"><span class="hw-accent">•</span><span x-text="idea"></span></li>
                            </template>
                        </ul>
                    </div>
                </template>

                <div class="mt-4" x-show="decor.diy && decor.diy.length">
                    <h4 class="hw-title font-bold mb-2">Сделать своими руками</h4>
                    <template x-for="(d, i) in decor.diy" :key="'dd' + i">
                        <div class="hw-row">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="hw-title font-bold" x-text="d.title"></span>
                                <span class="hw-badge" x-show="d.time_min > 0" x-text="d.time_min + ' мин'"></span>
                            </div>
                            <p class="hw-text text-sm mt-1" x-text="d.how"></p>
                        </div>
                    </template>
                </div>

                <div class="mt-4" x-show="decor.shopping && decor.shopping.length">
                    <h4 class="hw-title font-bold mb-2">Что докупить</h4>
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="(s, i) in decor.shopping" :key="'ds' + i">
                            <span class="hw-pill" x-text="s"></span>
                        </template>
                    </div>
                </div>

                <ul class="mt-4 space-y-1.5" x-show="decor.tips && decor.tips.length">
                    <template x-for="(t, i) in decor.tips" :key="'dtp' + i">
                        <li class="hw-text text-sm flex gap-2"><span class="hw-accent">•</span><span x-text="t"></span></li>
                    </template>
                </ul>
            </div>
        </template>
    </div>

<?php endif; ?>

    <!-- Навигация -->
    <div x-show="!loading" style="display:none" class="flex flex-col sm:flex-row gap-3 mt-2">
        <?php if ($stage === 2): ?>
            <a href="<?= e($linkMenu) ?>" class="hw-btn sm:w-auto">← К меню</a>
            <a href="<?= e($linkDecor) ?>" class="hw-cta sm:flex-1">Далее: декор →</a>
        <?php else: ?>
            <a href="<?= e($linkTable) ?>" class="hw-btn sm:w-auto">← Стол и напитки</a>
            <a href="<?= e(url('holiday/' . $h['slug'])) ?>" class="hw-cta sm:flex-1">Далее: мой праздник →</a>
        <?php endif; ?>
    </div>
</section>

<script src="<?= e(url('assets/js/ad-pause.js')) ?>?v=6"></script>
<script>
window.__AI_AD__ = <?= json_encode($aiAdBundle, $jf) ?>;

function holidayTips() {
    return {
        holiday:  <?= json_encode($holidayOut, $jf) ?>,
        sections: <?= json_encode($sectionsOut, $jf) ?>,
        tips:     <?= json_encode($tips, $jf) ?>,
        stage:    <?= (int)$stage ?>,
        mainLabel: <?= json_encode($stage === 2 ? 'Подобрать сервировку и напитки' : 'Придумать оформление', $jf) ?>,

        ...gcAdPause(10000),   // adVisible, adTimer, adHtml, adProvider, adShow, adRelease, adHold

        loading: false,
        current: '',
        stepText: '',
        error: '',

        get serving() { return this.tips.serving; },
        get drinks()  { return this.tips.drinks; },
        get decor()   { return this.tips.decor; },
        get missing() { return this.sections.filter(s => !this.tips[s.key]).map(s => s.key); },

        guestsWord(n) {
            n = parseInt(n) || 0;
            const m10 = n % 10, m100 = n % 100;
            if (m10 === 1 && m100 !== 11) return 'гость';
            if (m10 >= 2 && m10 <= 4 && (m100 < 10 || m100 >= 20)) return 'гостя';
            return 'гостей';
        },

        sectionLabel(key) {
            const s = this.sections.find(x => x.key === key);
            return s ? s.label : key;
        },

        async api(action, payload = {}) {
            const res = await fetch('/api/holiday_tips.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.__CSRF_TOKEN__ || '' },
                body: JSON.stringify({ action, ...payload })
            });
            let data;
            try { data = await res.json(); } catch (e) { throw new Error('Сервер вернул неверный ответ (' + res.status + ')'); }
            if (!data.ok) {
                const err = new Error(data.error || 'Ошибка запроса');
                err.status = res.status;
                throw err;
            }
            return data;
        },

        // Каждый раздел — отдельный запрос; идём по очереди
        async generate(keys) {
            if (this.loading || !keys || !keys.length) return;
            this.error = '';
            this.loading = true;
            try {
                await this.adHold((async () => {
                    for (const key of keys) {
                        this.current = key;
                        this.stepText = 'Готовим: ' + this.sectionLabel(key).toLowerCase() + '…';
                        const r = await this.api('generate', { holiday_id: this.holiday.id, section: key });
                        this.tips = { ...this.tips, [key]: r.content };
                    }
                })());
            } catch (e) {
                this.error = e.message;
            } finally {
                this.loading = false;
                this.current = '';
                this.stepText = '';
            }
        }
    };
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
