<?php
/**
 * API уведомлений и счётчиков для polling.
 *
 * GET  /api/notifications.php                    — список уведомлений + счётчики
 * GET  /api/notifications.php?unread=1           — только непрочитанные + счётчики
 * GET  /api/notifications.php?counters=1         — только счётчики (для polling)
 * POST /api/notifications.php                    — отметить все как прочитанные
 * DELETE /api/notifications.php?id=5             — удалить уведомление
 *
 * Возвращаемые счётчики:
 *   chats         — непрочитанные сообщения в чатах
 *   notifications — непрочитанные уведомления
 *   friends       — входящие заявки в друзья
 *   admin         — непрочитанные сообщения админу (только для админов)
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

if (!is_logged_in()) {
    json_response(['error' => 'Требуется вход (401)'], 401);
}

$userEmail = current_user()['email'];

try {

    // ============================================================
    // GET — список уведомлений и/или счётчики
    // ============================================================
   if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $unreadOnly   = ($_GET['unread']   ?? '') === '1';
    $countersOnly = ($_GET['counters'] ?? '') === '1';

    // === Счётчики: сначала пробуем кеш (15 сек) ===
    $countersKey = 'api_counters_' . md5($userEmail);
    $counters = cache_get($countersKey);

    if (!is_array($counters) || !isset($counters['ts']) || (time() - $counters['ts']) > 15) {
        $counters = [
            'ok'            => true,
            'chats'         => 0,
            'notifications' => 0,
            'friends'       => 0,
            'admin'         => 0,
            'ts'            => time(),
        ];

        try {
            $row = db_one(
                "SELECT
                    (SELECT COUNT(*) FROM notifications WHERE user_email = ? AND is_read = 0) AS notifications,
                    (SELECT COUNT(*) FROM friendships WHERE friend_email = ? AND status = 'pending') AS friends,
                    (SELECT COUNT(*) FROM chat_messages WHERE receiver_email = ? AND is_read = 0) AS chats",
                [$userEmail, $userEmail, $userEmail]
            );
            $counters['notifications'] = (int)($row['notifications'] ?? 0);
            $counters['friends']       = (int)($row['friends'] ?? 0);
            $counters['chats']         = (int)($row['chats'] ?? 0);
        } catch (Throwable $e) {}

        if (function_exists('is_admin') && is_admin()) {
            try {
                $row = db_one(
                    "SELECT COUNT(*) AS cnt FROM admin_chat_messages
                     WHERE to_email = ? AND is_read = 0",
                    [$userEmail]
                );
                $counters['admin'] = (int)($row['cnt'] ?? 0);
            } catch (Throwable $e) {}
        }

        cache_set($countersKey, $counters, 15);
    }

    if ($countersOnly) {
        json_response($counters);
    }

    // === Список уведомлений ===
    $where  = 'user_email = ?';
    $params = [$userEmail];
    if ($unreadOnly) {
        $where .= ' AND is_read = 0';
    }

    $notifications = db_all(
        "SELECT * FROM notifications 
         WHERE {$where} 
         ORDER BY is_pinned DESC, created_at DESC 
         LIMIT 50",
        $params
    );

    json_response([
        'ok'                  => true,
        'notifications'       => $notifications,
        'chats'               => $counters['chats'],
        'notifications_count' => $counters['notifications'],
        'friends'             => $counters['friends'],
        'admin'               => $counters['admin'],
        'ts'                  => $counters['ts'],
    ]);
}

// ============================================================
// POST — отметить все как прочитанные
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    db_exec(
        'UPDATE notifications SET is_read = 1 WHERE user_email = ? AND is_read = 0',
        [$userEmail]
    );

    // Сброс кеша счётчиков
    cache_delete('api_counters_' . md5($userEmail));
    cache_delete('user_counters_' . md5($userEmail));

    json_response(['ok' => true]);
}

// ============================================================
// DELETE — удалить одно уведомление
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_response(['error' => 'Не указан id'], 400);
    }
    db_exec(
        'DELETE FROM notifications WHERE id = ? AND user_email = ?',
        [$id, $userEmail]
    );

    // Сброс кеша
    cache_delete('api_counters_' . md5($userEmail));
    cache_delete('user_counters_' . md5($userEmail));

    json_response(['ok' => true]);
}

    json_response(['error' => 'Метод не поддерживается'], 405);

} catch (Throwable $ex) {
    json_response(
        ['error' => APP_DEBUG ? $ex->getMessage() : 'Ошибка сервера'],
        500
    );
}