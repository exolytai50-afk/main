<?php
/**
 * GET /api/achievements.php — список достижений пользователя
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

if (!is_logged_in()) {
    json_response(['error' => 'Требуется вход (401)'], 401);
}

$userEmail = current_user()['email'];
$userId = current_user()['id'];

try {
    // Все достижения
    $allAchievements = db_all('SELECT * FROM achievements ORDER BY points_reward ASC');
    
    // Полученные пользователем
    $unlocked = db_all(
        'SELECT achievement_code, unlocked_at FROM user_achievements WHERE user_email = ?',
        [$userEmail]
    );
    $unlockedMap = [];
    foreach ($unlocked as $u) {
        $unlockedMap[$u['achievement_code']] = $u['unlocked_at'];
    }
    
    // Статистика
    $stats = db_one('SELECT * FROM user_stats WHERE user_id = ?', [$userId]) ?? [];
    
    // Формируем результат
    $achievements = [];
    foreach ($allAchievements as $ach) {
        $achievements[] = [
            'code' => $ach['code'],
            'title' => $ach['title'],
            'description' => $ach['description'],
            'icon' => $ach['icon'],
            'points_reward' => (int)$ach['points_reward'],
            'unlocked' => isset($unlockedMap[$ach['code']]),
            'unlocked_at' => $unlockedMap[$ach['code']] ?? null
        ];
    }
    
    json_response([
        'achievements' => $achievements,
        'total_unlocked' => count($unlocked),
        'total_count' => count($allAchievements),
        'stats' => [
            'points' => (int)($stats['total_points'] ?? 0),
            'level' => (int)($stats['level'] ?? 1),
            'recipes' => (int)($stats['recipes_created'] ?? 0)
        ]
    ]);
    
} catch (Throwable $ex) {
    json_response(['error' => APP_DEBUG ? $ex->getMessage() : 'Ошибка сервера'], 500);
}