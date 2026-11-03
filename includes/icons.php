<?php
/**
 * GigaChef — минимальные хелперы для иконок.
 *
 * Иконки: /uploads/icon/<name>.svg  (плоская структура, без подпапок)
 * Использование: <?= icon('home') ?>
 *
 * Никаких паков, пресетов, цветовых переменных.
 * Цвет — через currentColor (унаследуется от родителя).
 */

if (!function_exists('resolve_icon_path')) {
    /**
     * Полный путь к SVG по имени. null, если файла нет.
     */
    function resolve_icon_path(string $name): ?string
    {
        $safe = preg_replace('/[^a-z0-9_\-]/i', '', $name);
        if ($safe === '') return null;

        $path = ROOT_DIR . '/uploads/icon/' . $safe . '.svg';
        return is_file($path) ? $path : null;
    }
}

if (!function_exists('icon')) {
    /**
     * Возвращает inline SVG в <span class="gc-icon ...">.
     *
     * @param string $name   Имя иконки (без .svg)
     * @param string $class  Доп. CSS-классы
     * @param float  $size   Размер в em (по умолчанию 1.5)
     * @param string $color  Явный CSS-цвет (пусто — currentColor от родителя)
     */
    function icon(string $name, string $class = '', float $size = 1.5, string $color = ''): string
    {
        static $cache = [];

        $path = resolve_icon_path($name);

        if (!$path) {
            // Серая заглушка — кружок
            return '<span class="gc-icon ' . e($class) . '" data-icon="' . e($name) . '" '
                 . 'style="display:inline-flex;width:' . $size . 'em;height:' . $size . 'em;'
                 . 'vertical-align:-0.15em;opacity:.35;'
                 . ($color !== '' ? 'color:' . $color . ';' : '')
                 . '">'
                 . '<svg viewBox="0 0 24 24" style="width:100%;height:100%">'
                 . '<circle cx="12" cy="12" r="10" fill="currentColor"/></svg></span>';
        }

        if (!isset($cache[$path])) {
            $svg = @file_get_contents($path);
            if ($svg === false) return '';

            // 1. Убрать XML/DOCTYPE/комментарии
            $svg = preg_replace('/<\?xml[^>]*\?>\s*/i', '', $svg);
            $svg = preg_replace('/<!DOCTYPE[^>]*>\s*/i', '', $svg);
            $svg = preg_replace('/<!--[\s\S]*?-->/', '', $svg);

            // 2. Убрать width/height/style с корневого <svg>
            $svg = preg_replace_callback(
                '/<svg\b([^>]*)>/i',
                function ($m) {
                    $a = preg_replace('/\s(width|height|style)\s*=\s*"[^"]*"/i', '', $m[1]);
                    return '<svg' . $a . '>';
                },
                $svg,
                1
            );

            // 3. Все заливки/обводки → currentColor (кроме none/url())
            $svg = preg_replace_callback(
                '/\b(fill|stroke)\s*=\s*"([^"]*)"/i',
                function ($m) {
                    $v = strtolower(trim($m[2]));
                    if ($v === 'none' || $v === 'currentcolor'
                        || $v === 'transparent' || str_starts_with($v, 'url(')) {
                        return $m[0];
                    }
                    return $m[1] . '="currentColor"';
                },
                $svg
            );

            // 4. Инлайновые style="fill:#..." → fill:currentColor
            $svg = preg_replace(
                '/(fill|stroke)\s*:\s*(#[0-9a-f]{3,8}|rgba?\([^)]+\)|hsla?\([^)]+\))/i',
                '$1:currentColor',
                $svg
            );

            // 5. Растянуть на контейнер
            $svg = preg_replace(
                '/<svg\b/i',
                '<svg style="width:100%;height:100%;display:block;fill:currentColor"',
                $svg,
                1
            );

            // 6. Схлопнуть пробелы
            $svg = preg_replace('/\s{2,}/', ' ', $svg);
            $svg = str_replace('> <', '><', $svg);

            $cache[$path] = trim($svg);
        }

        $style = 'display:inline-flex;width:' . $size . 'em;height:' . $size . 'em;'
               . 'vertical-align:-0.15em;line-height:0;flex-shrink:0;';
        if ($color !== '') {
            $style .= 'color:' . $color . ';';
        }

        return '<span class="gc-icon ' . e($class) . '" '
             . 'data-icon="' . e($name) . '" '
             . 'style="' . $style . '">'
             . $cache[$path]
             . '</span>';
    }
}