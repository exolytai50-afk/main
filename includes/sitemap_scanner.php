<?php
/**
 * GigaChef — Синхронизация списка страниц для управления метатегами
 * 
 * Источник: config/meta_pages.php (явный whitelist)
 * НЕ парсит sitemap, НЕ сканирует pages/ — только то, что указано вручную
 */
declare(strict_types=1);

/**
 * Возвращает whitelist страниц
 */
function get_meta_pages_list(): array
{
    $configFile = dirname(__DIR__) . '/config/meta_pages.php';
    
    if (!file_exists($configFile)) {
        error_log('meta_pages.php not found: ' . $configFile);
        return [];
    }
    
    $pages = require $configFile;
    
    return is_array($pages) ? $pages : [];
}

/**
 * Возвращает категории из БД (для отдельной вкладки)
 */
function get_categories_for_meta(): array
{
    try {
        return db_all("
            SELECT id, name, slug, description, icon 
            FROM recipe_categories 
            WHERE is_active = 1 
            ORDER BY name
        ");
    } catch (Throwable $e) {
        error_log('get_categories_for_meta error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Синхронизирует whitelist-страницы с БД
 * 
 * @param bool $addNew       Добавлять новые страницы из whitelist
 * @param bool $keepExisting Не удалять из БД те, которых нет в whitelist
 */
function sync_pages_with_db(bool $addNew = true, bool $keepExisting = true): array
{
    $whitelist = get_meta_pages_list();
    
    $result = [
        'whitelist_count' => count($whitelist),
        'added'           => 0,
        'updated'         => 0,
        'skipped'         => 0,
        'removed'         => 0,
        'errors'          => [],
        'paths'           => [],
    ];
    
    if (empty($whitelist)) {
        $result['errors'][] = 'Whitelist пуст. Проверьте config/meta_pages.php';
        return $result;
    }
    
    // Загружаем существующие записи
    $existingPaths = [];
    try {
        $rows = db_all('SELECT page_path FROM page_meta');
        foreach ($rows as $row) {
            $existingPaths[$row['page_path']] = true;
        }
    } catch (Throwable $e) {
        $result['errors'][] = 'Не удалось загрузить существующие страницы: ' . $e->getMessage();
        return $result;
    }
    
    // Проверяем структуру таблицы
    $tableColumns = [];
    try {
        $cols = db_all("SHOW COLUMNS FROM page_meta");
        foreach ($cols as $c) {
            $tableColumns[$c['Field']] = true;
        }
    } catch (Throwable $e) {}
    
    $hasCreatedAt = isset($tableColumns['created_at']);
    $hasUpdatedAt = isset($tableColumns['updated_at']);
    
    // Синхронизация
    foreach ($whitelist as $path => $name) {
        if (empty($path)) {
            $result['skipped']++;
            continue;
        }
        
        $path = mb_substr($path, 0, 250);
        $name = mb_substr($name ?: 'Страница', 0, 250);
        
        try {
            if (isset($existingPaths[$path])) {
                // UPDATE
                $sql = 'UPDATE page_meta SET page_name = ?';
                $params = [$name];
                
                if ($hasUpdatedAt) {
                    $sql .= ', updated_at = NOW()';
                }
                
                $sql .= ' WHERE page_path = ?';
                $params[] = $path;
                
                $ok = db_exec($sql, $params);
                
                if ($ok !== false) {
                    $result['updated']++;
                } else {
                    $result['errors'][] = "Не удалось обновить: {$path}";
                }
            } elseif ($addNew) {
                // INSERT
                $cols = ['page_path', 'page_name', 'meta_title', 'meta_description', 'h1', 'meta_keywords'];
                $vals = [$path, $name, null, null, null, null];
                
                if ($hasCreatedAt) {
                    $cols[] = 'created_at';
                    $vals[] = date('Y-m-d H:i:s');
                }
                if ($hasUpdatedAt) {
                    $cols[] = 'updated_at';
                    $vals[] = date('Y-m-d H:i:s');
                }
                
                $placeholders = implode(', ', array_fill(0, count($cols), '?'));
                $sql = 'INSERT INTO page_meta (`' . implode('`, `', $cols) . '`) VALUES (' . $placeholders . ')';
                
                $ok = db_exec($sql, $vals);
                
                if ($ok !== false) {
                    $result['added']++;
                    $existingPaths[$path] = true;
                } else {
                    $result['errors'][] = "Не удалось вставить: {$path}";
                }
            }
            
            $result['paths'][] = $path;
            
        } catch (Throwable $e) {
            $result['errors'][] = "{$path}: " . $e->getMessage();
        }
    }
    
    // Удаление лишних (тех, что в БД, но не в whitelist)
    if (!$keepExisting) {
        try {
            $allInDb = db_all('SELECT page_path FROM page_meta');
            foreach ($allInDb as $row) {
                if (!isset($whitelist[$row['page_path']])) {
                    db_exec('DELETE FROM page_meta WHERE page_path = ?', [$row['page_path']]);
                    $result['removed']++;
                }
            }
        } catch (Throwable $e) {
            $result['errors'][] = 'Ошибка удаления: ' . $e->getMessage();
        }
    }
    
    return $result;
}

/**
 * Возвращает объединённый список страниц (для админки)
 * Только whitelist + то, что уже в БД по whitelist
 */
function get_all_pages_for_meta(): array
{
    $whitelist = get_meta_pages_list();
    
    // Из БД
    $fromDb = [];
    try {
        $fromDb = db_all('SELECT page_path, page_name, meta_title, meta_description, h1, meta_keywords FROM page_meta');
    } catch (Throwable $e) {}
    
    $dbMap = [];
    foreach ($fromDb as $row) {
        $dbMap[$row['page_path']] = $row;
    }
    
    // Строим итоговый список
    $pages = [];
    foreach ($whitelist as $path => $defaultName) {
        if (isset($dbMap[$path])) {
            // Есть в БД — берём оттуда
            $pages[] = $dbMap[$path];
        } else {
            // Есть в whitelist, но не в БД — показываем с пустыми мета
            $pages[] = [
                'page_path'        => $path,
                'page_name'        => $defaultName,
                'meta_title'       => null,
                'meta_description' => null,
                'h1'               => null,
                'meta_keywords'    => null,
            ];
        }
    }
    
    return $pages;
}