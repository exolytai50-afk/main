<?php
/**
 * GigaChef — Просмотр праздника (/holiday/{slug})
 * Меню, сервировка, напитки, декор и общий список покупок.
 * Доступ только владельцу.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/holiday_shopping.php';
require_once __DIR__ . '/../includes/ads.php';

$aiAdBundle = ad_bundle_json('ai_action');

if (!function_exists('is_logged_in') || !is_logged_in()) {
    header('Location: ' . url('login'));
    exit;
}

$user   = current_user();
$userId = (int)$user['id'];

$slug = preg_replace('/[^a-z0-9\-]/i', '', (string)($_GET['slug'] ?? ''));
$h    = $slug !== '' ? db_one('SELECT * FROM holidays WHERE slug = ? AND user_id = ?', [$slug, $userId]) : null;
if (!$h) {
    header('Location: ' . url('my-holidays'));
    exit;
}

$id   = (int)$h['id'];
$rows = db_all('SELECT * FROM holiday_recipes WHERE holiday_id = ? ORDER BY id', [$id]);

$byCourse = [];
foreach ($rows as $r) $byCourse[$r['course']][] = $r;

$tips   = ht_load_tips($id);
$groups = hs_groups($id);
$next   = hv_next_step($h, count($rows), $tips);
$when   = hv_when($h['event_date']);

$shopTotal = 0;
foreach ($groups as $g) $shopTotal += count($g['items']);

// какие блюда уже имеют рецепт на сайте (id блюда => slug) и какие блюда AI ещё без рецепта
$createdMap = [];
$aiIds      = [];
foreach ($rows as $r) {
    $rid = (int)($r['recipe_id'] ?? 0);
    $slug = (string)($r['recipe_slug'] ?? '');
    if ($rid > 0 && $slug !== '') {
        $createdMap[(int)$r['id']] = $slug;         // и db, и ai с recipe_id
    } elseif ($r['source'] === 'ai') {
        $aiIds[] = (int)$r['id'];
    }
}

$linkMenu  = url('ai-holiday?id=' . $id);
$linkTable = url('ai-holiday/table?id=' . $id);
$linkDecor = url('ai-holiday/decor?id=' . $id);

$jf = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

$extraCss        = url('assets/css/holiday.css') . '?v=' . (@filemtime(ROOT_DIR . '/assets/css/holiday.css') ?: time());
$scheme          = (($_SERVER['HTTPS'] ?? '') === 'on') ? 'https' : 'http';
$pageUrl         = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'gigachef.ru') . '/holiday/' . $h['slug'];
$pageTitle       = $h['title'] . ' — GigaChef';
$pageDescription = 'Меню, сервировка, напитки, декор и список покупок для праздника.';

require_once __DIR__ . '/../includes/header.php';
?>

<section class="hw-root max-w-4xl mx-auto px-4 sm:px-6 py-8" x-data="holidayDetail()">

    <!-- Шапка -->
    <div class="hw-hero mb-4 hw-print-hide">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <h1 x-show="!editing" class="text-2xl sm:text-3xl font-extrabold break-words" x-text="title"></h1>
                <div x-show="editing" style="display:none" class="flex flex-wrap gap-2">
                    <input type="text" class="hw-input flex-1 min-w-[12rem]" x-model="newTitle" maxlength="120" @keydown.enter="saveTitle()">
                    <button type="button" class="hw-btn" @click="saveTitle()">Сохранить</button>
                    <button type="button" class="hw-btn" @click="editing = false">Отмена</button>
                </div>
            </div>
            <span class="hw-badge" :class="status === 'ready' ? 'hw-badge-ok' : ''" x-text="status === 'ready' ? 'Готов' : 'Черновик'"></span>
        </div>

        <div class="flex flex-wrap gap-2 mt-3">
            <?php if ($h['event_date']): ?>
                <span class="hw-pill"><?= e(date('d.m.Y', strtotime($h['event_date']))) ?><?= $when ? ' · ' . e($when) : '' ?></span>
            <?php endif; ?>
            <span class="hw-pill"><?= (int)$h['guests'] ?> <?= e(hv_guests_word((int)$h['guests'])) ?></span>
            <span class="hw-pill">Бюджет: <?= e(HOLIDAY_BUDGETS[$h['budget']]['label'] ?? '') ?></span>
            <span class="hw-pill"><?= e(HOLIDAY_AGES[$h['age_group']] ?? '') ?></span>
        </div>
    </div>

    <!-- Действия -->
    <div class="flex flex-wrap items-center gap-2 mb-4 hw-print-hide">
        <?php if ($next): ?>
            <a href="<?= e($next[1]) ?>" class="hw-cta">Продолжить: <?= e($next[0]) ?></a>
        <?php endif; ?>
        <button type="button" class="hw-btn" @click="startEdit()">Переименовать</button>
        <button type="button" class="hw-btn" @click="toggleStatus()" x-text="status === 'ready' ? 'Вернуть в черновики' : 'Отметить готовым'"></button>
        <a href="<?= e(url('my-holidays')) ?>" class="hw-btn">Мои праздники</a>
        <button type="button" class="hw-btn hw-btn-danger ml-auto" @click="remove()">Удалить</button>
    </div>

    <p x-show="error" style="display:none" class="hw-error mb-4 hw-print-hide" x-text="error"></p>
    
    <div x-show="adVisible" style="display:none" class="hw-card mb-4 ai-ad-pause-container hw-print-hide">
        <div class="ai-ad-pause-header flex justify-between items-center gap-3">
            <span class="inline-flex items-center gap-1.5 min-w-0">
                <?= icon('hourglass', 'gc-icon-xs', 1) ?>
                <span class="truncate" x-text="stepText || 'Подождите…'"></span>
            </span>
            <span class="font-bold text-xs shrink-0"><span x-text="adTimer"></span> сек</span>
        </div>
        <div class="px-4 pt-2 text-xs text-gray-400" x-show="adProvider">
            <span x-text="'Партнёр: ' + adProvider"></span>
        </div>
        <div x-html="adHtml" class="p-4"></div>
        <div x-show="!adHtml" class="p-4 text-sm text-gray-400 text-center">Реклама загружается…</div>
    </div>

    <!-- Якоря -->
    <div class="hw-anchors mb-6 hw-print-hide">
        <a class="hw-btn" href="#menu">Меню</a>
        <a class="hw-btn" href="#table">Стол и напитки</a>
        <a class="hw-btn" href="#decor">Декор</a>
        <a class="hw-btn" href="#shopping">Покупки<?= $shopTotal ? ' (' . $shopTotal . ')' : '' ?></a>
    </div>

    <!-- ===== Меню ===== -->
    <div id="menu" class="hw-card mb-6 hw-print-hide">
        <div class="flex items-center justify-between gap-3 mb-3">
            <h2 class="hw-title text-xl font-extrabold">Меню</h2>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="hw-btn" x-show="pending.length > 0" style="display:none"
                        :disabled="busy" @click="createAll()"
                        x-text="busy ? 'Создаём…' : 'Создать все рецепты (' + pending.length + ')'"></button>
                <a href="<?= e($linkMenu) ?>" class="hw-btn">Изменить</a>
            </div>
        </div>

        <p x-show="recipeMsg" style="display:none" class="hw-okmsg mb-3" x-text="recipeMsg"></p>

        <?php if (!$rows): ?>
            <p class="hw-sub text-sm">Блюд пока нет. <a href="<?= e($linkMenu) ?>" class="hw-accent font-bold">Составить меню</a></p>
        <?php else: ?>
            <?php foreach (HOLIDAY_COURSES as $key => $course): ?>
                <?php if (empty($byCourse[$key])) continue; ?>
                <h3 class="hw-sub text-sm font-bold uppercase tracking-wide mt-4 mb-1 inline-flex items-center gap-2">
                    <?= icon($course['icon'], '', 1.1) ?><span><?= e($course['label']) ?></span>
                </h3>
                <?php foreach ($byCourse[$key] as $row) hv_render_dish($row); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ===== Стол и напитки ===== -->
    <div id="table" class="hw-card mb-6 hw-print-hide">
        <div class="flex items-center justify-between gap-3 mb-3">
            <h2 class="hw-title text-xl font-extrabold">Сервировка и напитки</h2>
            <a href="<?= e($linkTable) ?>" class="hw-btn"><?= ($tips['serving'] || $tips['drinks']) ? 'Обновить' : 'Подобрать' ?></a>
        </div>

        <?php if (!$tips['serving'] && !$tips['drinks']): ?>
            <p class="hw-sub text-sm">Советов пока нет.
                <?php if ($rows): ?><a href="<?= e($linkTable) ?>" class="hw-accent font-bold">Подобрать сервировку и напитки</a>
                <?php else: ?>Сначала составьте меню.<?php endif; ?></p>
        <?php endif; ?>

        <?php if ($tips['serving']): ?>
            <h3 class="hw-title font-bold mb-2 inline-flex items-center gap-2"><?= icon('dinner', '', 1.2) ?><span>Сервировка стола</span></h3>
            <?php hv_render_serving($tips['serving']); ?>
        <?php endif; ?>

        <?php if ($tips['drinks']): ?>
            <h3 class="hw-title font-bold mt-6 mb-2 inline-flex items-center gap-2"><?= icon('drink', '', 1.2) ?><span>Напитки</span></h3>
            <?php hv_render_drinks($tips['drinks']); ?>
        <?php endif; ?>
    </div>

    <!-- ===== Декор ===== -->
    <div id="decor" class="hw-card mb-6 hw-print-hide">
        <div class="flex items-center justify-between gap-3 mb-3">
            <h2 class="hw-title text-xl font-extrabold">Оформление помещения</h2>
            <a href="<?= e($linkDecor) ?>" class="hw-btn"><?= $tips['decor'] ? 'Обновить' : 'Подобрать' ?></a>
        </div>
        <?php if ($tips['decor']): ?>
            <?php hv_render_decor($tips['decor']); ?>
        <?php else: ?>
            <p class="hw-sub text-sm">Идей пока нет. <a href="<?= e($linkDecor) ?>" class="hw-accent font-bold">Придумать оформление</a></p>
        <?php endif; ?>
    </div>

    <!-- ===== Список покупок ===== -->
    <div id="shopping" class="hw-card">

        <div class="hw-print-only">
            <h2 style="font-size:20px;font-weight:800">Список покупок — <?= e((string)$h['title']) ?></h2>
            <p style="margin:4px 0 12px"><?= (int)$h['guests'] ?> <?= e(hv_guests_word((int)$h['guests'])) ?></p>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 hw-print-hide">
            <h2 class="hw-title text-xl font-extrabold">Список покупок</h2>
            <span class="hw-sub text-sm" x-show="total > 0" style="display:none" x-text="'Осталось купить: ' + left + ' из ' + total"></span>
        </div>

        <?php if (!$groups): ?>
            <p class="hw-sub text-sm mt-2 hw-print-hide">Список появится, когда в меню будут блюда.</p>
        <?php else: ?>

            <div class="flex flex-wrap gap-2 mt-3 hw-print-hide">
                <button type="button" class="hw-btn" @click="copyList()">Копировать</button>
                <button type="button" class="hw-btn" @click="printList()">Печать / PDF</button>
                <a class="hw-btn" href="<?= e(url('api/holiday_shopping.php?id=' . $id)) ?>">Скачать CSV</a>
                <button type="button" class="hw-btn" @click="emailOpen = !emailOpen">Отправить на почту</button>
                <button type="button" class="hw-btn ml-auto" @click="resetChecks()" x-show="left < total" style="display:none">Сбросить отметки</button>
            </div>

            <div x-show="emailOpen" style="display:none" class="mt-3 flex flex-wrap gap-2 hw-print-hide">
                <input type="email" class="hw-input flex-1 min-w-[12rem]" x-model="email" placeholder="email получателя" @keydown.enter="sendEmail()">
                <button type="button" class="hw-cta" :disabled="sending" @click="sendEmail()" x-text="sending ? 'Отправляем…' : 'Отправить'"></button>
            </div>

            <p x-show="msg" style="display:none" class="hw-okmsg mt-3 hw-print-hide" x-text="msg"></p>
            <p x-show="mailError" style="display:none" class="hw-error mt-3 hw-print-hide" x-text="mailError"></p>

            <template x-for="g in groups" :key="g.key">
                <div class="mt-5">
                    <h3 class="hw-sub text-sm font-bold uppercase tracking-wide mb-1" x-text="g.label"></h3>
                    <template x-for="it in g.items" :key="g.key + '|' + it.name + '|' + it.text">
                        <label class="hw-check">
                            <input type="checkbox" :checked="isChecked(it)" @change="toggle(it)">
                            <span class="min-w-0">
                                <span :class="isChecked(it) ? 'hw-done' : ''">
                                    <span class="hw-title font-bold" x-text="it.name"></span>
                                    <span class="hw-text"> — </span>
                                    <span class="hw-text" x-text="it.text"></span>
                                </span>
                                <span class="hw-sub text-xs block" x-text="usedLine(it)"></span>
                            </span>
                        </label>
                    </template>
                </div>
            </template>

        <?php endif; ?>
    </div>
</section>
<script src="<?= e(url('assets/js/ad-pause.js')) ?>?v=6"></script>
<script>
window.__AI_AD__ = <?= json_encode($aiAdBundle, $jf) ?>;
</script>

<script>
function holidayDetail() {
    return {
        id: <?= $id ?>,
        title: <?= json_encode((string)$h['title'], $jf) ?>,
        status: <?= json_encode((string)$h['status'], $jf) ?>,
        groups: <?= json_encode($groups, $jf) ?>,
        email: <?= json_encode((string)$user['email'], $jf) ?>,

        ...gcAdPause(10000),   // adVisible, adTimer, adHtml, adProvider, adShow, adRelease, adHold
        stepText: '',

        created: <?= json_encode((object)$createdMap, $jf) ?>,
        aiIds: <?= json_encode($aiIds, $jf) ?>,
        busy: false,
        recipeMsg: '',

        checked: {},
        editing: false,
        newTitle: '',
        emailOpen: false,
        sending: false,
        msg: '',
        error: '',
        mailError: '',

        init() {
            try {
                this.checked = JSON.parse(localStorage.getItem('hw_shop_' + this.id) || '{}') || {};
            } catch (e) { this.checked = {}; }
        },

        // ---------- список покупок ----------
        keyOf(it) { return (it.name + '|' + it.text).toLowerCase(); },
        isChecked(it) { return !!this.checked[this.keyOf(it)]; },
        toggle(it) {
            this.checked[this.keyOf(it)] = !this.checked[this.keyOf(it)];
            this.persist();
        },
        persist() {
            try { localStorage.setItem('hw_shop_' + this.id, JSON.stringify(this.checked)); } catch (e) {}
        },
        resetChecks() { this.checked = {}; this.persist(); },

        get total() { return this.groups.reduce((s, g) => s + g.items.length, 0); },
        get left()  {
            return this.groups.reduce((s, g) => s + g.items.filter(it => !this.isChecked(it)).length, 0);
        },
        usedLine(it) {
            const u = it.used || [];
            if (!u.length) return '';
            return 'для: ' + u.slice(0, 2).join(', ') + (u.length > 2 ? ' и ещё ' + (u.length - 2) : '');
        },

        async copyList() {
            const lines = [this.title, ''];
            this.groups.forEach(g => {
                lines.push(g.label.toUpperCase());
                g.items.forEach(it => lines.push((this.isChecked(it) ? '☑ ' : '☐ ') + it.name + ' — ' + it.text));
                lines.push('');
            });
            try {
                await navigator.clipboard.writeText(lines.join('\n').trim());
                this.flash('Список скопирован');
            } catch (e) {
                this.mailError = 'Не удалось скопировать: разрешите доступ к буферу обмена';
            }
        },

        printList() {
            document.body.classList.add('hw-print-list');
            const done = () => {
                document.body.classList.remove('hw-print-list');
                window.removeEventListener('afterprint', done);
            };
            window.addEventListener('afterprint', done);
            window.print();
        },

        flash(text) {
            this.msg = text;
            this.mailError = '';
            setTimeout(() => { this.msg = ''; }, 4000);
        },

        // ---------- запросы ----------
        async api(action, payload = {}) {
            const res = await fetch('/api/holiday_manage.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.__CSRF_TOKEN__ || '' },
                body: JSON.stringify({ action, holiday_id: this.id, ...payload })
            });
            let data;
            try { data = await res.json(); } catch (e) { throw new Error('Сервер вернул неверный ответ (' + res.status + ')'); }
            if (!data.ok) throw new Error(data.error || 'Ошибка запроса');
            return data;
        },

        async sendEmail() {
            if (this.sending) return;
            this.sending = true;
            this.mailError = '';
            this.msg = '';
            try {
                const r = await this.api('email', { email: this.email });
                this.flash(r.message || 'Отправлено');
            } catch (e) {
                this.mailError = e.message;
            } finally {
                this.sending = false;
            }
        },

        // ---------- рецепты на сайте ----------
        get pending() { return this.aiIds.filter(id => !this.created[id]); },

        // «сырая» версия без паузы — для вложенных вызовов
        async _createRecipeRaw(itemId, force = false) {
            const r = await this.api('create_recipe', { item_id: itemId, force });
            if (r.duplicate) return r;               // caller решает, что делать
            this.created = { ...this.created, [itemId]: r.slug };
            this.recipeMsg = 'Рецепт создан. Откройте его по кнопке «Открыть рецепт на сайте».';
            return r;
        },

        // публичная версия с рекламой
        async createRecipeWithAd(itemId) {
            if (this.busy) return;
            if (!confirm('Создать на сайте полноценный рецепт из этого блюда? Он будет опубликован под вашим именем.')) return;
        
            this.error = '';
            this.recipeMsg = '';
            this.busy = true;
            this.stepText = 'Создаём рецепт…';
            try {
                const r = await this.adHold(this._createRecipeRaw(itemId, false));
                if (r && r.duplicate) {
                    const d = r.duplicate;
                    if (confirm('Найден похожий ваш рецепт «' + d.title + '» (совпадение ' + d.similarity + '%).\n\n'
                              + 'ОК — использовать его для этого блюда.\nОтмена — создать новый.')) {
                        await this.adHold((async () => {
                            await this.api('link_recipe', { item_id: itemId, recipe_id: d.id });
                            this.created = { ...this.created, [itemId]: d.slug };
                        })());
                    } else if (confirm('Создать новый рецепт всё равно?')) {
                        await this.adHold(this._createRecipeRaw(itemId, true));
                    }
                }
            } catch (e) {
                this.error = e.message;
            } finally {
                this.busy = false;
                this.stepText = '';
            }
        },

        async createAll() {
            if (this.busy || !this.pending.length) return;
            if (!confirm('Создать рецепты на сайте для всех блюд, придуманных AI (' + this.pending.length + ')? '
                       + 'Они будут опубликованы под вашим именем.')) return;
        
            this.error = '';
            this.recipeMsg = '';
            this.busy = true;
            let ok = 0, dup = 0;
            try {
                await this.adHold((async () => {
                    for (const id of this.pending.slice()) {
                        this.stepText = `Создаём ${ok + dup + 1} из ${this.pending.length}…`;
                        const r = await this._createRecipeRaw(id, false);
                        if (r && r.duplicate) { dup++; continue; }
                        ok++;
                    }
                })());
            } catch (e) {
                this.error = e.message;
            } finally {
                this.busy = false;
                this.stepText = '';
                this.recipeMsg = 'Создано рецептов: ' + ok
                    + (dup ? '. Пропущено как похожие на уже имеющиеся: ' + dup + ' — создайте их кнопкой у блюда.' : '.');
            }
        },

        startEdit() { this.newTitle = this.title; this.editing = true; },
        async saveTitle() {
            this.error = '';
            try {
                const r = await this.api('rename', { title: this.newTitle });
                this.title = r.title;
                document.title = r.title + ' — GigaChef';
                this.editing = false;
            } catch (e) { this.error = e.message; }
        },

        async toggleStatus() {
            this.error = '';
            try {
                const r = await this.api('status', { status: this.status === 'ready' ? 'draft' : 'ready' });
                this.status = r.status;
            } catch (e) { this.error = e.message; }
        },

        async remove() {
            if (!confirm('Удалить праздник «' + this.title + '» вместе с меню и советами?')) return;
            this.error = '';
            try {
                await this.api('delete');
                location.href = '/my-holidays';
            } catch (e) { this.error = e.message; }
        }
    };
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
