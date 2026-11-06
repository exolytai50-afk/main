<?php
/**
 * Бейдж остатка AI-лимитов.
 * Ожидает: $aiFeature (строка) — имя фичи.
 * Если не передан — берётся 'generate'.
 */
if (!function_exists('ai_usage_status')) {
    require_once __DIR__ . '/../includes/ai_guard.php';
}

$feature = $aiFeature ?? 'generate';
$status  = ai_usage_status($feature);

// Гостям и премиум не показываем счётчик (у них либо безлимит, либо не залогинен)
if (!is_logged_in() || $status['is_pro']) {
    return;
}

$remaining = (int)$status['remaining'];
$limit     = (int)$status['limit'];
$percent   = $limit > 0 ? min(100, round($remaining / $limit * 100)) : 0;

// Цвет: > 50% — зелёный, 20–50% — жёлтый, < 20% — красный
$colorClass = $percent > 50
    ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
    : ($percent > 20
        ? 'bg-amber-50 text-amber-700 border-amber-200'
        : 'bg-rose-50 text-rose-700 border-rose-200');
?>
<div class="ai-limit-badge inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border text-xs font-semibold <?= $colorClass ?>"
     title="Осталось AI-запросов сегодня: <?= $remaining ?> из <?= $limit ?>">
    <?= icon('ai', 'gc-icon-xs', 1) ?>
    <span>AI: <?= $remaining ?>/<?= $limit ?></span>
</div>