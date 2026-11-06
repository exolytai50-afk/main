<?php
/**
 * GET /api/tag_groups.php
 * Возвращает все активные группы тегов (кроме difficulty) с их тегами
 * и предрендеренными SVG-иконками.
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

$groups  = get_filter_tag_groups();
$allTags = get_all_tags_by_group();
$result  = [];

foreach ($groups as $g) {
    $tags = $allTags[$g] ?? [];
    if (empty($tags)) continue;

    /* ─── SVG-иконка группы ─── */
    $groupIconName = get_tag_group_icon_svg($g);
    $groupIconHtml = $groupIconName
        ? icon($groupIconName, 'gc-icon-xs', 1)
        : '';

    /* ─── Теги группы ─── */
    $tagsOut = [];
    foreach ($tags as $t) {
        $iconHtml = !empty($t['icon_svg'])
            ? icon($t['icon_svg'], 'gc-icon-xs', 1)
            : '';

        $tagsOut[] = [
            'id'            => (int)$t['id'],
            'slug'          => $t['slug'],
            'name'          => $t['name'],
            'icon_svg'      => $t['icon_svg'],
            'icon_svg_html' => $iconHtml,
        ];
    }

    /* ─── Группа ─── */
    $result[] = [
        'group'         => $g,
        'label'         => get_tag_group_label($g),
        'icon_svg_html' => $groupIconHtml,                 // SVG-иконка группы
        'tags'          => $tagsOut,
    ];
}

json_response(['groups' => $result]);