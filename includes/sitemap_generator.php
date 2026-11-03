<?php
/**
 * Генерация sitemap.xml.
 *
 * Важно: страницы и категории всегда включаются полностью.
 * Лимит применяется ТОЛЬКО к рецептам — чтобы sitemap не разбухал.
 *
 * @param int $recipesLimit 100|500|1000|5000|10000|20000
 * @return array{recipes:int, pages:int, categories:int}
 */
if (!function_exists('generate_sitemap')) {
    function generate_sitemap(int $recipesLimit = 1000): array
    {
        $base    = rtrim(url(''), '/');
        $entries = [];      // [loc, lastmod, changefreq, priority]

        /* ─────────── 1. Статические страницы (ВСЕГДА) ─────────── */
        $staticPages = [
            ['path' => '',                    'priority' => '1.0', 'freq' => 'daily'],
            ['path' => 'feed',                'priority' => '0.9', 'freq' => 'hourly'],
            ['path' => 'search',              'priority' => '0.8', 'freq' => 'weekly'],
            ['path' => 'recipes',             'priority' => '0.9', 'freq' => 'daily'],
            ['path' => 'news',                'priority' => '0.7', 'freq' => 'weekly'],
            ['path' => 'challenges',          'priority' => '0.7', 'freq' => 'weekly'],
            ['path' => 'communities',         'priority' => '0.7', 'freq' => 'weekly'],
            ['path' => 'leaderboard',         'priority' => '0.6', 'freq' => 'daily'],
            ['path' => 'achievements',        'priority' => '0.6', 'freq' => 'weekly'],
            ['path' => 'stories',             'priority' => '0.6', 'freq' => 'daily'],
            ['path' => 'friends',             'priority' => '0.5', 'freq' => 'weekly'],
            ['path' => 'ai-generator',        'priority' => '0.8', 'freq' => 'weekly'],
            ['path' => 'what-to-cook',        'priority' => '0.8', 'freq' => 'weekly'],
            ['path' => 'advisor',             'priority' => '0.7', 'freq' => 'weekly'],
            ['path' => 'calorie-scanner',     'priority' => '0.7', 'freq' => 'weekly'],
            ['path' => 'beer-pairing',        'priority' => '0.7', 'freq' => 'weekly'],
            ['path' => 'dietolog',            'priority' => '0.7', 'freq' => 'weekly'],
            ['path' => 'ai-cookbook',         'priority' => '0.6', 'freq' => 'weekly'],
            ['path' => 'fusion',              'priority' => '0.7', 'freq' => 'weekly'],
            ['path' => 'create-recipe',       'priority' => '0.7', 'freq' => 'weekly'],
            ['path' => 'inspiration',         'priority' => '0.6', 'freq' => 'weekly'],
            ['path' => 'videos',              'priority' => '0.6', 'freq' => 'weekly'],
            ['path' => 'about',               'priority' => '0.4', 'freq' => 'monthly'],
            ['path' => 'terms',               'priority' => '0.3', 'freq' => 'yearly'],
            ['path' => 'privacy',             'priority' => '0.3', 'freq' => 'yearly'],
            ['path' => 'legal',               'priority' => '0.3', 'freq' => 'yearly'],
        ];

        foreach ($staticPages as $p) {
            $entries[] = [
                'loc'        => $base . '/' . $p['path'],
                'lastmod'    => date('Y-m-d'),
                'changefreq' => $p['freq'],
                'priority'   => $p['priority'],
            ];
        }
        $pagesCount = count($staticPages);

        /* ─────────── 2. Категории (ВСЕГДА) ─────────── */
        $categoriesCount = 0;
        try {
            $categories = db_all(
                "SELECT slug, updated_at, created_at
                 FROM recipe_categories
                 WHERE is_active = 1
                 ORDER BY updated_at DESC"
            );
            foreach ($categories as $c) {
                if (empty($c['slug'])) continue;
                $entries[] = [
                    'loc'        => $base . '/category/' . $c['slug'],
                    'lastmod'    => date('Y-m-d', strtotime($c['updated_at'] ?? $c['created_at'] ?? 'now')),
                    'changefreq' => 'weekly',
                    'priority'   => '0.7',
                ];
                $categoriesCount++;
            }
        } catch (Throwable $e) {
            // Таблицы может не быть — тогда берём уникальные cuisine из recipes
            try {
                $cuisines = db_all("SELECT DISTINCT cuisine FROM recipes WHERE cuisine IS NOT NULL AND cuisine != ''");
                foreach ($cuisines as $c) {
                    $slug = generateSlug($c['cuisine']);
                    if (!$slug) continue;
                    $entries[] = [
                        'loc'        => $base . '/category/' . $slug,
                        'lastmod'    => date('Y-m-d'),
                        'changefreq' => 'weekly',
                        'priority'   => '0.7',
                    ];
                    $categoriesCount++;
                }
            } catch (Throwable $e2) {
                error_log('sitemap categories error: ' . $e2->getMessage());
            }
        }

        /* ─────────── 3. Рецепты (С ЛИМИТОМ) ─────────── */
        $recipesCount = 0;
        try {
            $recipes = db_all(
                "SELECT slug, id, updated_at, created_at
                 FROM recipes
                 WHERE slug IS NOT NULL AND slug != ''
                 ORDER BY COALESCE(updated_at, created_at) DESC
                 LIMIT " . (int)$recipesLimit
            );
            foreach ($recipes as $r) {
                $entries[] = [
                    'loc'        => $base . '/recipe/' . $r['slug'],
                    'lastmod'    => date('Y-m-d', strtotime($r['updated_at'] ?? $r['created_at'] ?? 'now')),
                    'changefreq' => 'monthly',
                    'priority'   => '0.8',
                ];
                $recipesCount++;
            }
        } catch (Throwable $e) {
            error_log('sitemap recipes error: ' . $e->getMessage());
        }

        /* ─────────── 4. Новости (ВСЕГДА) ─────────── */
        try {
            $news = db_all(
                "SELECT id, updated_at, created_at
                 FROM news
                 WHERE is_published = 1
                 ORDER BY created_at DESC
                 LIMIT 1000"
            );
            foreach ($news as $n) {
                $entries[] = [
                    'loc'        => $base . '/news/' . $n['id'],
                    'lastmod'    => date('Y-m-d', strtotime($n['updated_at'] ?? $n['created_at'] ?? 'now')),
                    'changefreq' => 'monthly',
                    'priority'   => '0.6',
                ];
            }
        } catch (Throwable $e) {
            // Таблицы может не быть
        }

        /* ─────────── 5. Сборка XML ─────────── */
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($entries as $e) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($e['loc'], ENT_XML1, 'UTF-8') . "</loc>\n";
            $xml .= "    <lastmod>" . $e['lastmod'] . "</lastmod>\n";
            $xml .= "    <changefreq>" . $e['changefreq'] . "</changefreq>\n";
            $xml .= "    <priority>" . $e['priority'] . "</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>' . "\n";

        /* ─────────── 6. Запись ─────────── */
        $file = ROOT_DIR . '/sitemap.xml';
        if (!@file_put_contents($file, $xml)) {
            throw new RuntimeException('Не удалось записать sitemap.xml');
        }

        return [
            'recipes'    => $recipesCount,
            'pages'      => $pagesCount,
            'categories' => $categoriesCount,
        ];
    }
}