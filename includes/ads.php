<?php
/**
 * GigaChef — простая реклама: берём активные блоки из БД и выдаём их HTML.
 */
declare(strict_types=1);

function ad_bundle(string $placement, int $limit = 9): array
{
    static $cache = [];
    $key = $placement . ':' . $limit;
    if (isset($cache[$key])) return $cache[$key];

    // Просто берём все активные блоки для этого места
    $rows = db_all(
        'SELECT id, title, code, min_duration_ms 
         FROM ad_blocks 
         WHERE placement = ? AND is_active = 1 
         ORDER BY sort_order ASC, id ASC 
         LIMIT ' . (int)$limit,
        [$placement]
    );

    $items = [];
    $minDuration = 10000;
    
    foreach ($rows as $r) {
        $html = trim((string)($r['code'] ?? ''));
        if ($html === '') continue; // пропускаем пустые
        
        $minDuration = max($minDuration, (int)($r['min_duration_ms'] ?? 10000));
        
        $items[] = [
            'provider'  => 'custom',
            'slot'      => 'main',
            'weight'    => 1,
            'html'      => $html,
            'init_js'   => '',
            'refresh_js'=> '',
            'block_id'  => (int)$r['id'],
        ];
    }

    return $cache[$key] = ['min_duration_ms' => $minDuration, 'items' => $items];
}

function ad_bundle_json(string $placement): array
{
    return ad_bundle($placement);
}