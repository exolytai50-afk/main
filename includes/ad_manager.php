<?php
// includes/ad_manager.php

if (!function_exists('get_ad_code')) {
    /**
     * Возвращает HTML-код рекламы для указанного placement.
     * Если реального кода нет — возвращает заглушку «Место для рекламы».
     * Проверку премиума делает вызывающий код (ad-banner.php, category.php, feed.php).
     */
    function get_ad_code(string $placement): string
    {
        if (function_exists('ad_bundle')) {
            $items = ad_bundle($placement)['items'];
            return $items ? $items[0]['html'] : '';
        }
    // ✅ Единая проверка премиума для ВСЕХ страниц
    if (function_exists('is_premium') && is_premium()) {
        return '';
    }
        static $cache = [];
        if (array_key_exists($placement, $cache)) {
            return $cache[$placement];
        }

        $ad = null;
        try {
            $ad = db_one(
                "SELECT code, is_active FROM ad_blocks WHERE placement = ? AND is_active = 1",
                [$placement]
            );
        } catch (Throwable $e) {
            error_log('AdManager error: ' . $e->getMessage());
        }

        $code = $ad ? trim((string)$ad['code']) : '';

        // Если реального кода нет — возвращаем заглушку (для вёрстки)
        if ($code === '' || str_starts_with($code, '<!--')) {
            $fallback  = '<div class="ad-placeholder" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px;border:2px dashed #e2e8f0;border-radius:16px;background:#f8fafc;color:#94a3b8;text-align:center;height:100%;min-height:200px;">';
            $fallback .= '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-bottom:8px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>';
            $fallback .= '<div style="font-weight:bold;font-size:14px;color:#64748b;">Место для рекламы</div>';
            $fallback .= '<div style="font-size:12px;margin-top:4px;">' . htmlspecialchars($placement) . '</div>';
            $fallback .= '</div>';
            return $cache[$placement] = $fallback;
        }

        return $cache[$placement] = '<div class="ad-content-inner">' . $ad['code'] . '</div>';
    }
}