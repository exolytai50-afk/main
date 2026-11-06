<?php
/**
 * Универсальный рекламный баннер для вкладок.
 * Ожидает: $adPlacement (строка) — ключ размещения из БД.
 */
$placement = $adPlacement ?? 'feed_inline';

// ✅ Премиум не видит рекламу вообще
if (function_exists('is_premium') && is_premium()) {
    return;
}

$adCode = get_ad_code($placement);
?>
<div class="mt-8 mb-4">
    <div class="ai-ad-pause-container">
        <div class="ai-ad-pause-header flex justify-between items-center">
            <span class="inline-flex items-center gap-1.5">
                <?= icon('info', 'gc-icon-xs', 1) ?>
                <span>Реклама</span>
            </span>
        </div>
        <div class="p-4">
            <?= $adCode ?>
        </div>
    </div>
</div>