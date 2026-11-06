<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

$rows = get_all_categories();
$items = [];
foreach ($rows as $r) {
    $items[] = [
        'id'            => (int)$r['id'],
        'slug'          => $r['slug'],
        'name'          => $r['name'],
        'icon_svg'      => $r['icon_svg'],
        'icon_svg_html' => !empty($r['icon_svg'])
            ? icon($r['icon_svg'], 'gc-icon-md', 1.25)
            : '',
    ];
}

json_response(['items' => $items]);