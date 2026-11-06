<?php
/**
 * GigaChefAI — Система достижений
 * Включает достижения за фьюжн-кухню.
 * 
 * ВАЖНО: Иконки теперь берутся из общей системы (uploads/icon/*.svg)
 * через функцию icon('имя_иконки').
 */
declare(strict_types=1);

/**
 * Безопасный вывод SVG-иконки достижения.
 * Теперь функция просто возвращает результат вызова icon().
 *
 * @param array|string $achievement Массив достижения (с ключом 'icon') либо имя иконки.
 */
if (!function_exists('achievement_icon')) {
    function achievement_icon(array|string $achievement): string
    {
        if (is_array($achievement)) {
            $iconName = $achievement['icon'] ?? 'medal';
        } else {
            $iconName = $achievement;
        }

        // Проверяем, есть ли такая иконка в нашей системе, если нет — ставим заглушку
        return icon($iconName, 'gc-icon-md');
    }
}

/**
 * Определения всех достижений.
 * Вместо инлайновых SVG теперь просто указываем имя иконки.
 */
function achievements_definitions(): array
{
    return [
        // === Рецепты ===
        'first_recipe'      => ['code' => 'first_recipe',      'title' => 'Первый рецепт',   'description' => 'Создайте свой первый рецепт',       'icon' => 'chef', 'points_reward' => 10],
        'ten_recipes'       => ['code' => 'ten_recipes',       'title' => 'Десятка',         'description' => 'Создайте 10 рецептов',              'icon' => 'openbook', 'points_reward' => 50],
        'fifty_recipes'     => ['code' => 'fifty_recipes',     'title' => 'Полтинник',       'description' => 'Создайте 50 рецептов',              'icon' => 'cookbook', 'points_reward' => 200],
        'hundred_recipes'   => ['code' => 'hundred_recipes',   'title' => 'Центурион',       'description' => 'Создайте 100 рецептов',             'icon' => 'crown', 'points_reward' => 500],
        
        // === Очки ===
        'hundred_points'    => ['code' => 'hundred_points',    'title' => 'Сотка',           'description' => 'Наберите 100 очков',                'icon' => 'star', 'points_reward' => 20],
        'thousand_points'   => ['code' => 'thousand_points',   'title' => 'Тысячник',        'description' => 'Наберите 1000 очков',               'icon' => 'diamond', 'points_reward' => 100],
        
        // === Уровни ===
        'level_5'           => ['code' => 'level_5',           'title' => 'Пятый уровень',   'description' => 'Достигните 5 уровня',               'icon' => 'rocket', 'points_reward' => 30],
        'level_10'          => ['code' => 'level_10',          'title' => 'Десятый уровень', 'description' => 'Достигните 10 уровня',              'icon' => 'medal', 'points_reward' => 100],
        
        // === Фьюжн ===
        'fusion_first'      => ['code' => 'fusion_first',      'title' => 'Первый фьюжн',       'description' => 'Создайте свой первый фьюжн-рецепт',           'icon' => 'flask', 'points_reward' => 15],
        'fusion_explorer'   => ['code' => 'fusion_explorer',   'title' => 'Кулинарный исследователь', 'description' => 'Создайте 10 фьюжн-рецептов',           'icon' => 'globe', 'points_reward' => 75],
        'fusion_master'     => ['code' => 'fusion_master',     'title' => 'Мастер фьюжн',       'description' => 'Создайте 50 фьюжн-рецептов',                  'icon' => 'spell', 'points_reward' => 300],
        'fusion_curator'    => ['code' => 'fusion_curator',    'title' => 'Куратор',            'description' => 'Сохраните 10 фьюжн-рецептов в кулинарную книгу', 'icon' => 'bookmark', 'points_reward' => 150],
        'fusion_world_tour' => ['code' => 'fusion_world_tour', 'title' => 'Вокруг света',       'description' => 'Смешайте 10 разных кухонь',                   'icon' => 'globe', 'points_reward' => 100],
        'fusion_perfect'    => ['code' => 'fusion_perfect',    'title' => 'Идеальное сочетание','description' => 'Получите 10 оценок 5 звёзд на фьюжн-рецепты', 'icon' => 'star', 'points_reward' => 200],
        
        // === Кулинарные книги ===
        'first_cookbook'    => ['code' => 'first_cookbook',    'title' => 'Первая книга',       'description' => 'Создайте свою первую кулинарную книгу',        'icon' => 'openbook', 'points_reward' => 25],
        'five_cookbooks'    => ['code' => 'five_cookbooks',    'title' => 'Библиофил',          'description' => 'Создайте 5 кулинарных книг',                  'icon' => 'cookbook', 'points_reward' => 100],
    ];
}

/**
 * Проверяет и разблокирует все доступные достижения
 */
function check_achievements(string $userEmail): void
{
    $user = db_one('SELECT id, email FROM users WHERE email = ?', [$userEmail]);
    if (!$user) return;
    
    $userId = (int)$user['id'];
    $stats = db_one('SELECT * FROM user_stats WHERE user_id = ?', [$userId]) ?? [];
    
    $recipesCount = (int)($stats['recipes_created'] ?? 0);
    $points       = (int)($stats['total_points'] ?? 0);
    $level        = (int)($stats['level'] ?? 1);
    
    $toUnlock = [];
    
    // === Рецепты ===
    if ($recipesCount >= 1)   $toUnlock[] = 'first_recipe';
    if ($recipesCount >= 10)  $toUnlock[] = 'ten_recipes';
    if ($recipesCount >= 50)  $toUnlock[] = 'fifty_recipes';
    if ($recipesCount >= 100) $toUnlock[] = 'hundred_recipes';
    
    // === Очки ===
    if ($points >= 100)  $toUnlock[] = 'hundred_points';
    if ($points >= 1000) $toUnlock[] = 'thousand_points';
    
    // === Уровни ===
    if ($level >= 5)  $toUnlock[] = 'level_5';
    if ($level >= 10) $toUnlock[] = 'level_10';
    
    // === Фьюжн ===
    $fusionStats = db_one(
        'SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_saved = 1 THEN 1 ELSE 0 END) as saved,
            SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as fives
         FROM fusion_recipes WHERE user_id = ?',
        [$userId]
    ) ?? [];
    
    $fusionTotal = (int)($fusionStats['total'] ?? 0);
    $fusionSaved = (int)($fusionStats['saved'] ?? 0);
    $fusionFives = (int)($fusionStats['fives'] ?? 0);
    
    if ($fusionTotal >= 1)   $toUnlock[] = 'fusion_first';
    if ($fusionTotal >= 10)  $toUnlock[] = 'fusion_explorer';
    if ($fusionTotal >= 50)  $toUnlock[] = 'fusion_master';
    if ($fusionSaved >= 10)  $toUnlock[] = 'fusion_curator';
    if ($fusionFives >= 10)  $toUnlock[] = 'fusion_perfect';
    
    // Уникальные кухни
    $uniqueCuisines = db_one(
        'SELECT COUNT(DISTINCT cuisine) FROM (
            SELECT cuisine_a as cuisine FROM fusion_recipes WHERE user_id = ?
            UNION
            SELECT cuisine_b as cuisine FROM fusion_recipes WHERE user_id = ?
        ) as c',
        [$userId, $userId]
    );
    if ((int)($uniqueCuisines['COUNT(DISTINCT cuisine)'] ?? 0) >= 10) {
        $toUnlock[] = 'fusion_world_tour';
    }
    
    // === Кулинарные книги ===
    try {
        $cookbooksCount = (int)db_one(
            'SELECT COUNT(*) as cnt FROM cookbooks WHERE user_id = ?',
            [$userId]
        )['cnt'] ?? 0;
        
        if ($cookbooksCount >= 1) $toUnlock[] = 'first_cookbook';
        if ($cookbooksCount >= 5) $toUnlock[] = 'five_cookbooks';
    } catch (Throwable $e) {
        // Таблица может отсутствовать
    }
    
    // Разблокируем
    foreach (array_unique($toUnlock) as $code) {
        unlock_achievement($userEmail, $code);
    }
}

/**
 * Разблокирует конкретное достижение
 */
function unlock_achievement(string $userEmail, string $code): void
{
    // Проверяем, есть ли уже
    $exists = db_one(
        'SELECT id FROM user_achievements WHERE user_email = ? AND achievement_code = ?',
        [$userEmail, $code]
    );
    
    if ($exists) return;
    
    // Берём определение
    $defs = achievements_definitions();
    $achievement = $defs[$code] ?? null;
    
    if (!$achievement) {
        // Fallback на БД, если есть таблица achievements
        $achievement = db_one('SELECT * FROM achievements WHERE code = ?', [$code]);
        if (!$achievement) return;
    }
    
    try {
        // Записываем достижение
        db_insert(
            'INSERT INTO user_achievements (user_email, achievement_code) VALUES (?, ?)',
            [$userEmail, $code]
        );
        
        // Начисляем очки
        $user = db_one('SELECT id FROM users WHERE email = ?', [$userEmail]);
        if ($user) {
            $userId = (int)$user['id'];
            $points = (int)($achievement['points_reward'] ?? 0);
            
            if ($points > 0) {
                db_exec(
                    'UPDATE user_stats SET total_points = total_points + ? WHERE user_id = ?',
                    [$points, $userId]
                );
                db_exec(
                    'UPDATE users SET points = points + ? WHERE id = ?',
                    [$points, $userId]
                );
                
                // Логируем
                try {
                    db_insert(
                        'INSERT INTO points_log (user_id, points, reason, description, created_at)
                         VALUES (?, ?, "achievement", ?, NOW())',
                        [$userId, $points, 'Достижение: ' . ($achievement['title'] ?? $code)]
                    );
                } catch (Throwable $e) {
                    // Таблица может отсутствовать
                }
            }
        }
        
        // Уведомление — выводим иконку через общую функцию icon()
        $medalSvg = icon('medal', 'gc-icon-sm');
        
        send_notification(
            $userEmail,
            $medalSvg . ' Достижение получено!',
            ($achievement['title'] ?? $code) . ' — ' . ($achievement['description'] ?? ''),
            'achievement'
        );
        
        // Активность
        add_activity(
            $userEmail,
            'achievement',
            null,
            'achievement',
            'Получил достижение: ' . ($achievement['title'] ?? $code)
        );
        
    } catch (Throwable $e) {
        error_log('unlock_achievement error: ' . $e->getMessage());
    }
}

/**
 * Возвращает список достижений пользователя
 */
function get_user_achievements(string $userEmail): array
{
    $unlocked = db_all(
        'SELECT achievement_code, unlocked_at FROM user_achievements 
         WHERE user_email = ? ORDER BY unlocked_at DESC',
        [$userEmail]
    );
    
    $unlockedMap = [];
    foreach ($unlocked as $u) {
        $unlockedMap[$u['achievement_code']] = $u['unlocked_at'];
    }
    
    $defs = achievements_definitions();
    $result = [];
    
    foreach ($defs as $code => $def) {
        $result[] = [
            'code'        => $code,
            'title'       => $def['title'],
            'description' => $def['description'],
            // Возвращаем готовый HTML с иконкой
            'icon'        => $def['icon'], // например 'chef' 
            'points'      => $def['points_reward'] ?? 0,
            'unlocked'    => isset($unlockedMap[$code]),
            'unlocked_at' => $unlockedMap[$code] ?? null,
        ];
    }
    
    return $result;
}

/**
 * Возвращает прогресс по фьюжн-достижениям
 */
function get_fusion_achievement_progress(string $userEmail): array
{
    $user = db_one('SELECT id FROM users WHERE email = ?', [$userEmail]);
    if (!$user) return [];
    
    $userId = (int)$user['id'];
    
    $stats = db_one(
        'SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_saved = 1 THEN 1 ELSE 0 END) as saved,
            SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as fives
         FROM fusion_recipes WHERE user_id = ?',
        [$userId]
    ) ?? [];
    
    return [
        'total_generated' => (int)($stats['total'] ?? 0),
        'total_saved'     => (int)($stats['saved'] ?? 0),
        'total_fives'     => (int)($stats['fives'] ?? 0),
        'goals' => [
            'fusion_first'    => ['current' => min(1, (int)($stats['total'] ?? 0)),   'target' => 1],
            'fusion_explorer' => ['current' => min(10, (int)($stats['total'] ?? 0)),  'target' => 10],
            'fusion_master'   => ['current' => min(50, (int)($stats['total'] ?? 0)),  'target' => 50],
            'fusion_curator'  => ['current' => min(10, (int)($stats['saved'] ?? 0)),  'target' => 10],
            'fusion_perfect'  => ['current' => min(10, (int)($stats['fives'] ?? 0)),  'target' => 10],
        ]
    ];
}

/**
 * Начисляет очки пользователю и пишет в points_log.
 * Используется везде: за рецепты, отзывы, лайки, фьюжн и т.д.
 */
if (!function_exists('award_points')) {
    function award_points(
        string $userEmail,
        int $points,
        string $reason,
        ?string $description = null,
        ?int $referenceId = null,
        ?string $referenceType = null
    ): void {
        if ($points === 0) return;

        try {
            $user = db_one('SELECT id FROM users WHERE email = ?', [$userEmail]);
            if (!$user) return;

            $userId = (int)$user['id'];

            // Обновляем total_points
            db_exec(
                'UPDATE user_stats SET total_points = GREATEST(0, total_points + ?) WHERE user_id = ?',
                [$points, $userId]
            );

            // users.points (если используется)
            db_exec(
                'UPDATE users SET points = GREATEST(0, points + ?) WHERE id = ?',
                [$points, $userId]
            );

            // Пишем в points_log
            db_insert(
                'INSERT INTO points_log (user_id, points, reason, description, reference_id, reference_type, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())',
                [$userId, $points, $reason, $description, $referenceId, $referenceType]
            );

            // Пересчитываем уровень (если есть функция)
            if (function_exists('recalc_user_level')) {
                recalc_user_level($userId);
            }
        } catch (Throwable $e) {
            error_log('award_points error: ' . $e->getMessage());
        }
    }
}