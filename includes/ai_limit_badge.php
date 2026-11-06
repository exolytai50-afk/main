<?php
/**
 * Переиспользуемый бейдж остатка AI-лимита.
 * Использование:
 *   <?php $aiFeature = 'generate'; require __DIR__ . '/../includes/ai_limit_badge.php'; ?>
 */
$feature = $aiFeature ?? 'generate';
?>
<div x-data="{ ai: {} }"
     x-init="try { const r = await CookAPI.get('/api/ai_status.php?feature=<?= e($feature) ?>'); ai = r.ai || {}; } catch(e){}"
     x-show="ai.limit" x-cloak class="inline-flex items-center gap-2">
    <span class="px-3 py-1 rounded-full text-xs font-bold"
          :class="ai.remaining > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-600'">
        <template x-if="!ai.is_pro">
            <span>Осталось <span x-text="ai.remaining"></span>/<span x-text="ai.limit"></span> сегодня</span>
        </template>
        <template x-if="ai.is_pro">
            <span>Pro · <span x-text="ai.remaining"></span>/<span x-text="ai.limit"></span></span>
        </template>
    </span>
</div>