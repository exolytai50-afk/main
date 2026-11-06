<?php
/**
 * GET /api/leaderboard.php — таблица лидеров
 * Список лидеров кешируется на 1 час (одинаков для всех).
 * Текущий пользователь считается без кеша (зависит от сессии).
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

try {
    $period = $_GET['period'] ?? 'all';
    $limit  = min(100, max(1, (int)($_GET['limit'] ?? 50)));

    // === Список лидеров (кеш 1 час) ===
    $lbCacheKey = "leaderboard_{$period}_{$limit}";
    $leaders = cache_get($lbCacheKey);

    if ($leaders === null) {
        $whereClause = "WHERE u.role = 'user'";

        if ($period === 'week') {
            $whereClause .= " AND u.last_login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($period === 'month') {
            $whereClause .= " AND u.last_login_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }

        $leaders = db_all(
            "SELECT u.id, u.email, u.display_name, u.avatar_emoji, u.avatar_url, u.points,
                    s.total_points, s.level, s.recipes_created
             FROM users u
             LEFT JOIN user_stats s ON s.user_id = u.id
             {$whereClause}
             ORDER BY s.total_points DESC, u.points DESC
             LIMIT {$limit}"
        );

        cache_set($lbCacheKey, $leaders, 3600);
    }

    // === Текущий пользователь ===
    $currentUser = null;
    if (is_logged_in()) {
        $user = current_user();

        $rank = db_one(
            'SELECT COUNT(*) + 1 as rank
             FROM users u
             LEFT JOIN user_stats s ON s.user_id = u.id
             WHERE s.total_points > (
                 SELECT COALESCE(total_points, 0)
                 FROM user_stats
                 WHERE user_id = ?
             )
             AND u.role = "user"',
            [$user['id']]
        );

        $userStats = db_one(
            'SELECT total_points, level, recipes_created
             FROM user_stats
             WHERE user_id = ?',
            [$user['id']]
        );

        $currentUser = [
            'rank'            => (int)($rank['rank'] ?? 0),
            'points'          => (int)($userStats['total_points'] ?? 0),
            'level'           => (int)($userStats['level'] ?? 1),
            'recipes_created' => (int)($userStats['recipes_created'] ?? 0),
            'display_name'    => $user['display_name'] ?? $user['name'] ?? 'Пользователь',
            'avatar_emoji'    => $user['avatar_emoji'] ?? '👨‍🍳',
            'avatar_url'      => $user['avatar_url'] ?? null,
        ];
    }

    json_response([
        'ok'           => true,
        'leaders'      => $leaders,
        'current_user' => $currentUser,
        'total_users'  => count($leaders),
    ]);

} catch (Throwable $ex) {
    json_response([
        'error' => APP_DEBUG ? $ex->getMessage() : 'Ошибка сервера',
    ], 500);
}