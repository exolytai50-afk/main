<?php
/**
 * Универсальная пагинация.
 *
 * Использование:
 *   <?= render_pagination([
 *       'page'       => $page,
 *       'totalPages' => $totalPages,
 *       'baseUrl'    => url('search') . '?' . http_build_query(array_diff_key($_GET, ['page' => 1])),
 *       'mode'       => 'numbers',      // 'numbers' | 'loadmore' | 'both'
 *       'loadMoreTarget' => '#feed-list', // для режима loadmore
 *       'loadMorePage'   => $page + 1,
 *   ]) ?>
 */

if (!function_exists('render_pagination')) {
    function render_pagination(array $opts): string
    {
        $page       = max(1, (int)($opts['page'] ?? 1));
        $totalPages = max(1, (int)($opts['totalPages'] ?? 1));
        $baseUrl    = $opts['baseUrl'] ?? '';
        $mode       = $opts['mode'] ?? 'numbers';       // numbers | loadmore | both
        $target     = $opts['loadMoreTarget'] ?? '#list';
        $nextPage   = $opts['loadMorePage'] ?? ($page + 1);
        $labelMore  = $opts['loadMoreLabel'] ?? 'Показать ещё';

        if ($totalPages <= 1) return '';

        $html = '';

        /* ─── Load More ─── */
        if ($mode === 'loadmore' || $mode === 'both') {
            $disabled = $nextPage > $totalPages ? 'hidden' : '';
            $html .= '<div class="flex justify-center mt-6 ' . $disabled . '" data-pagination-more>'
                  .   '<button type="button" '
                  .   'data-load-more '
                  .   'data-target="' . e($target) . '" '
                  .   'data-next-page="' . (int)$nextPage . '" '
                  .   'data-total-pages="' . (int)$totalPages . '" '
                  .   'data-base-url="' . e($baseUrl) . '" '
                  .   'class="px-6 py-3 rounded-xl bg-amber-100 text-amber-700 font-bold hover:bg-amber-200 transition disabled:opacity-60">'
                  .   e($labelMore)
                  .   '</button>'
                  . '</div>';
        }

        /* ─── Numbers (только десктоп) ─── */
        if ($mode === 'numbers' || $mode === 'both') {
            $html .= '<nav class="hidden sm:flex justify-center items-center gap-2 mt-8" aria-label="Пагинация">';
            $html .= '<button type="button" '
                  .   ($page <= 1 ? 'disabled' : '')
                  .   ' data-page-link="' . ($page - 1) . '" '
                  .   'class="px-3 py-2 rounded-xl bg-gray-100 text-gray-600 text-sm hover:bg-gray-200 disabled:opacity-40 disabled:cursor-not-allowed transition">← Назад</button>';

            foreach (pagination_pages($page, $totalPages) as $p) {
                if ($p === '…') {
                    $html .= '<span class="w-10 text-center text-gray-400 select-none">…</span>';
                } else {
                    $cls = $p === $page
                        ? 'bg-amber-400 text-white'
                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200';
                    $html .= '<button type="button" data-page-link="' . (int)$p . '" '
                          . 'class="w-10 h-10 rounded-xl text-sm font-semibold transition ' . $cls . '">'
                          . (int)$p . '</button>';
                }
            }

            $html .= '<button type="button" '
                  .   ($page >= $totalPages ? 'disabled' : '')
                  .   ' data-page-link="' . ($page + 1) . '" '
                  .   'class="px-3 py-2 rounded-xl bg-gray-100 text-gray-600 text-sm hover:bg-gray-200 disabled:opacity-40 disabled:cursor-not-allowed transition">Вперёд →</button>';
            $html .= '</nav>';
        }

        return $html;
    }
}

/**
 * Возвращает массив страниц для отображения: [1, 2, 3, '…', 48, 49, 50]
 */
if (!function_exists('pagination_pages')) {
    function pagination_pages(int $current, int $total, int $delta = 1, int $edge = 1): array
    {
        if ($total <= 1) return [1];

        $pages = [];
        $left  = max(2, $current - $delta);
        $right = min($total - 1, $current + $delta);

        for ($i = 1; $i <= min($edge, $total); $i++) $pages[] = $i;
        if ($left > $edge + 1) $pages[] = '…';
        for ($i = $left; $i <= $right; $i++) $pages[] = $i;
        if ($right < $total - $edge) $pages[] = '…';
        for ($i = max($total - $edge + 1, $right + 1); $i <= $total; $i++) $pages[] = $i;

        // Убираем дубли, если total маленький
        return array_values(array_unique($pages, SORT_REGULAR));
    }
}