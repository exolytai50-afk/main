<?php
/**
 * GigaChef — Мои праздники (/my-holidays)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/holiday_view.php';

if (!function_exists('is_logged_in') || !is_logged_in()) {
    header('Location: ' . url('login'));
    exit;
}

$user   = current_user();
$userId = (int)$user['id'];
$today  = date('Y-m-d');

$rows = db_all(
    'SELECT h.*, (SELECT COUNT(*) FROM holiday_recipes r WHERE r.holiday_id = h.id) AS dishes
     FROM holidays h WHERE h.user_id = ? ORDER BY h.updated_at DESC',
    [$userId]
);

// какие советы уже есть у каждого праздника
$tipsMap = [];
if ($rows) {
    $ids = array_map(fn($r) => (int)$r['id'], $rows);
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    foreach (db_all("SELECT holiday_id, section FROM holiday_tips WHERE holiday_id IN ({$ph})", $ids) as $t) {
        $tipsMap[(int)$t['holiday_id']][$t['section']] = true;
    }
}

// ближайшие сверху, затем без даты, затем прошедшие
$upcoming = $undated = $past = [];
foreach ($rows as $r) {
    if ($r['event_date'] && $r['event_date'] >= $today) $upcoming[] = $r;
    elseif ($r['event_date'])                           $past[]     = $r;
    else                                                $undated[]  = $r;
}
usort($upcoming, fn($a, $b) => strcmp($a['event_date'], $b['event_date']));
usort($past,     fn($a, $b) => strcmp($b['event_date'], $a['event_date']));

$extraCss        = url('assets/css/holiday.css') . '?v=' . (@filemtime(ROOT_DIR . '/assets/css/holiday.css') ?: time());
$scheme          = (($_SERVER['HTTPS'] ?? '') === 'on') ? 'https' : 'http';
$pageUrl         = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'gigachef.ru') . '/my-holidays';
$pageTitle       = 'Мои праздники — GigaChef';
$pageDescription = 'Ваши праздники: меню, сервировка, напитки, декор и списки покупок.';

require_once __DIR__ . '/../includes/header.php';

/** Карточка праздника. */
$renderCard = function (array $r) use ($tipsMap): void {
    $id     = (int)$r['id'];
    $dishes = (int)$r['dishes'];
    $tips   = $tipsMap[$id] ?? [];
    $next   = hv_next_step($r, $dishes, $tips);
    $when   = hv_when($r['event_date']);
    $ready  = $r['status'] === 'ready';

    $dots = [
        'Меню'          => $dishes > 0,
        'Стол и напитки' => !empty($tips['serving']) && !empty($tips['drinks']),
        'Декор'         => !empty($tips['decor']),
    ];
    ?>
    <div class="hw-card">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <a href="<?= e(url('holiday/' . $r['slug'])) ?>" class="hw-title text-lg font-extrabold break-words hover:underline">
                    <?= e((string)$r['title']) ?>
                </a>
                <div class="flex flex-wrap gap-1.5 mt-2">
                    <?php if ($r['event_date']): ?>
                        <span class="hw-pill"><?= e(date('d.m.Y', strtotime($r['event_date']))) ?><?= $when ? ' · ' . e($when) : '' ?></span>
                    <?php endif; ?>
                    <span class="hw-pill"><?= (int)$r['guests'] ?> <?= e(hv_guests_word((int)$r['guests'])) ?></span>
                    <span class="hw-pill"><?= e(HOLIDAY_BUDGETS[$r['budget']]['label'] ?? '') ?></span>
                    <span class="hw-pill"><?= $dishes ?> <?= e(hv_plural($dishes, 'блюдо', 'блюда', 'блюд')) ?></span>
                </div>
            </div>
            <span class="hw-badge <?= $ready ? 'hw-badge-ok' : '' ?>"><?= $ready ? 'Готов' : 'Черновик' ?></span>
        </div>

        <div class="flex flex-wrap gap-4 mt-3">
            <?php foreach ($dots as $label => $on): ?>
                <span class="hw-dot <?= $on ? 'on' : '' ?>"><i></i><?= e($label) ?></span>
            <?php endforeach; ?>
        </div>

        <div class="flex flex-wrap gap-2 mt-4">
            <a href="<?= e(url('holiday/' . $r['slug'])) ?>" class="hw-btn">Открыть</a>
            <?php if ($next): ?>
                <a href="<?= e($next[1]) ?>" class="hw-btn">Продолжить: <?= e($next[0]) ?></a>
            <?php endif; ?>
            <button type="button" class="hw-btn hw-btn-danger ml-auto"
                    @click="remove(<?= $id ?>, <?= e(json_encode((string)$r['title'], JSON_UNESCAPED_UNICODE)) ?>)">
                Удалить
            </button>
        </div>
    </div>
    <?php
};
?>

<section class="hw-root max-w-4xl mx-auto px-4 sm:px-6 py-8" x-data="holidayList()">

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <h1 class="hw-title text-3xl font-extrabold inline-flex items-center gap-2">
            <?= icon('balloons', '', 1.6) ?>
            <span>Мои праздники</span>
        </h1>
        <a href="<?= e(url('ai-holiday')) ?>" class="hw-cta">Новый праздник</a>
    </div>

    <p x-show="error" style="display:none" class="hw-error mb-4" x-text="error"></p>

    <?php if (!$rows): ?>
        <div class="hw-card text-center py-12">
            <div class="flex justify-center mb-3"><?= icon('balloons', '', 3) ?></div>
            <p class="hw-title text-lg font-bold">Пока нет праздников</p>
            <p class="hw-sub text-sm mt-1 mb-5">Составьте меню, подберите сервировку, напитки и декор — всё сохранится здесь.</p>
            <a href="<?= e(url('ai-holiday')) ?>" class="hw-cta">Спланировать праздник</a>
        </div>
    <?php else: ?>

        <?php foreach ([['Ближайшие', $upcoming], ['Без даты', $undated], ['Прошедшие', $past]] as [$label, $list]): ?>
            <?php if (!$list) continue; ?>
            <h2 class="hw-sub text-sm font-bold uppercase tracking-wide mt-6 mb-3"><?= e($label) ?></h2>
            <div class="space-y-3">
                <?php foreach ($list as $r) $renderCard($r); ?>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>
</section>

<script>
function holidayList() {
    return {
        error: '',
        async remove(id, title) {
            if (!confirm('Удалить праздник «' + title + '» вместе с меню и советами?')) return;
            this.error = '';
            try {
                const res = await fetch('/api/holiday_manage.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.__CSRF_TOKEN__ || '' },
                    body: JSON.stringify({ action: 'delete', holiday_id: id })
                });
                const data = await res.json();
                if (!data.ok) throw new Error(data.error || 'Не удалось удалить');
                location.reload();
            } catch (e) {
                this.error = e.message;
            }
        }
    };
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
