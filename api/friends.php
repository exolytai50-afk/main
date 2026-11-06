<?php
/**
 * api/friends.php
 *
 * API для управления заявками в друзья:
 * GET  — получить список связей;
 * POST — add, accept, reject, remove, block, unblock.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/notifications.php';

if (!is_logged_in()) {
    json_response(['error' => 'Требуется вход'], 401);
}

$me = current_user();

$myEmail = mb_strtolower(trim((string)($me['email'] ?? '')));
$myId    = (int)($me['id'] ?? 0);
$myName  = (string)($me['display_name'] ?? $myEmail);

if ($myEmail === '' || $myId <= 0) {
    json_response(['error' => 'Не удалось определить пользователя'], 401);
}

/**
 * Универсальный парсинг входных данных:
 * поддерживает JSON и обычные POST-формы.
 */
if (!function_exists('api_input')) {
    function api_input(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (stripos($contentType, 'application/json') !== false) {
            return json_input() ?: [];
        }

        return $_POST;
    }
}

/**
 * Параметры для SQL-проверки связи в обе стороны.
 */
if (!function_exists('friend_pair_params')) {
    function friend_pair_params(string $a, string $b): array
    {
        return [$a, $b, $b, $a];
    }
}

/**
 * Очистка устаревших уведомлений между двумя пользователями.
 */
if (!function_exists('clear_friend_notifications')) {
    function clear_friend_notifications(string $ownerEmail, string $relatedEmail): void
    {
        db_exec(
            "DELETE FROM notifications
             WHERE user_email = ?
               AND related_email = ?
               AND type IN (
                   'friend_request',
                   'friend_accepted',
                   'friend_removed'
               )",
            [$ownerEmail, $relatedEmail]
        );
    }
}

// ============================================================
// GET — список связей текущего пользователя
// ============================================================

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    try {
        $rows = db_all(
            "SELECT
                f.id,
                f.status,
                CASE
                    WHEN f.user_email = ? THEN f.friend_email
                    ELSE f.user_email
                END AS peer_email,
                u.display_name,
                u.avatar_url,
                u.avatar_emoji
             FROM friendships f
             LEFT JOIN users u
                ON u.email = CASE
                    WHEN f.user_email = ? THEN f.friend_email
                    ELSE f.user_email
                END
             WHERE f.user_email = ?
                OR f.friend_email = ?
             ORDER BY f.id DESC",
            [$myEmail, $myEmail, $myEmail, $myEmail]
        );

        $out = [];

        foreach ($rows as $row) {
            $peerEmail = (string)$row['peer_email'];

            $out[] = [
                'id'           => (int)$row['id'],
                'email'        => $peerEmail,
                'name'         => $row['display_name'] ?: $peerEmail,
                'status'       => (string)$row['status'],
                'avatar_url'   => $row['avatar_url'] ?? null,
                'avatar_emoji' => $row['avatar_emoji'] ?? null,
            ];
        }

        json_response(['friends' => $out]);
    } catch (Throwable $e) {
        error_log('api/friends.php [GET]: ' . $e->getMessage());
        json_response(['error' => 'Не удалось загрузить список друзей'], 500);
    }
}

// ============================================================
// Только POST дальше
// ============================================================

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_response(['error' => 'Метод не поддерживается'], 405);
}

$in = api_input();

$action = trim((string)($in['action'] ?? ''));

$email = mb_strtolower(
    trim((string)($in['friend_email'] ?? $in['email'] ?? ''))
);

// Совместимость со старыми вызовами, где action не передавался.
if ($action === '' && $email !== '') {
    $action = 'add';
}

if ($action === '') {
    json_response(['error' => 'Не указано действие'], 400);
}

$allowedActions = [
    'add',
    'accept',
    'reject',
    'remove',
    'block',
    'unblock',
];

if (!in_array($action, $allowedActions, true)) {
    json_response(['error' => 'Неизвестное действие: ' . $action], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['error' => 'Некорректный email'], 400);
}

if ($email === $myEmail) {
    json_response(['error' => 'Нельзя выполнить действие с самим собой'], 400);
}

try {
    // Проверяем существование второго пользователя.
    $peer = db_one(
        'SELECT id, display_name FROM users WHERE email = ? LIMIT 1',
        [$email]
    );

    if (!$peer) {
        json_response(['error' => 'Пользователь не найден'], 404);
    }

    $peerId   = (int)$peer['id'];
    $peerName = (string)($peer['display_name'] ?? $email);

    switch ($action) {

        // ====================================================
        // ADD — отправить заявку
        // ====================================================

        case 'add': {

            // Проверяем блокировку в обе стороны.
            $blocked = db_one(
                "SELECT id
                 FROM blacklist
                 WHERE (user_email = ? AND blocked_email = ?)
                    OR (user_email = ? AND blocked_email = ?)
                 LIMIT 1",
                friend_pair_params($myEmail, $email)
            );

            if ($blocked) {
                json_response([
                    'error' => 'Нельзя отправить заявку этому пользователю'
                ], 403);
            }

            // Проверяем, не существует ли уже связь.
            $existing = db_one(
                "SELECT id, user_email, friend_email, status
                 FROM friendships
                 WHERE (user_email = ? AND friend_email = ?)
                    OR (user_email = ? AND friend_email = ?)
                 ORDER BY id DESC
                 LIMIT 1",
                friend_pair_params($myEmail, $email)
            );

            if ($existing) {
                $direction = 'outgoing';

                if (
                    $existing['user_email'] === $email
                    && $existing['friend_email'] === $myEmail
                ) {
                    $direction = 'incoming';
                }

                if ($existing['status'] === 'accepted') {
                    $direction = 'accepted';
                }

                flush_friends_cache($myEmail, $email);

                json_response([
                    'ok'        => true,
                    'id'        => (int)$existing['id'],
                    'status'    => (string)$existing['status'],
                    'direction' => $direction,
                    'exists'    => true,
                ]);
            }

            // Создаём новую заявку.
            try {
                $id = db_insert(
                    "INSERT INTO friendships
                        (user_email, friend_email, status)
                     VALUES (?, ?, 'pending')",
                    [$myEmail, $email]
                );
            } catch (PDOException $e) {
                // Защита от повторной вставки при одновременных запросах.
                if ($e->getCode() === '23000') {
                    flush_friends_cache($myEmail, $email);

                    json_response([
                        'ok'     => true,
                        'status' => 'exists',
                        'exists' => true,
                    ]);
                }

                throw $e;
            }

            // Совместимость со старой таблицей friends.
            // Ошибка старой таблицы не должна отменять основную операцию.
            try {
                $legacyExists = db_one(
                    'SELECT id
                     FROM friends
                     WHERE user_id = ? AND friend_email = ?
                     LIMIT 1',
                    [$myId, $email]
                );

                if (!$legacyExists) {
                    db_insert(
                        "INSERT INTO friends
                            (user_id, friend_email, friend_name, status)
                         VALUES (?, ?, ?, 'pending')",
                        [$myId, $email, $peerName]
                    );
                }
            } catch (Throwable $e) {
                error_log('legacy mirror add failed: ' . $e->getMessage());
            }
            
            clear_friend_notifications($email, $myEmail);
            notify_user($email, 'friend_request', 'Новая заявка в друзья',
                "{$myName} хочет добавить вас в друзья", $myEmail,
                url('profile?user=' . urlencode($myEmail)));

            flush_friends_cache($myEmail, $email);

            json_response([
                'ok'     => true,
                'id'     => (int)$id,
                'status' => 'pending',
            ]);
        }

        // ====================================================
        // ACCEPT — принять входящую заявку
        // ====================================================

        case 'accept': {

            // Принимать можно только входящую pending-заявку.
            $incoming = db_one(
                "SELECT id
                 FROM friendships
                 WHERE user_email = ?
                   AND friend_email = ?
                   AND status = 'pending'
                 ORDER BY id DESC
                 LIMIT 1",
                [$email, $myEmail]
            );

            if (!$incoming) {
                // Возможно, заявка уже была принята ранее.
                $accepted = db_one(
                    "SELECT id
                     FROM friendships
                     WHERE (
                            (user_email = ? AND friend_email = ?)
                         OR (user_email = ? AND friend_email = ?)
                     )
                     AND status = 'accepted'
                     LIMIT 1",
                    friend_pair_params($myEmail, $email)
                );

                if ($accepted) {
                    flush_friends_cache($myEmail, $email);

                    json_response([
                        'ok'     => true,
                        'status' => 'accepted',
                        'already_accepted' => true,
                    ]);
                }

                json_response([
                    'error' => 'Входящая заявка не найдена или уже обработана'
                ], 409);
            }

            // Обновляем именно входящую заявку.
            db_exec(
                "UPDATE friendships
                 SET status = 'accepted'
                 WHERE user_email = ?
                   AND friend_email = ?
                   AND status = 'pending'",
                [$email, $myEmail]
            );

            // Проверяем, что дружба действительно установлена.
            $accepted = db_one(
                "SELECT id
                 FROM friendships
                 WHERE user_email = ?
                   AND friend_email = ?
                   AND status = 'accepted'
                 LIMIT 1",
                [$email, $myEmail]
            );

            if (!$accepted) {
                json_response([
                    'error' => 'Не удалось принять заявку'
                ], 409);
            }

            // Обновляем старую таблицу, если она используется.
            try {
                db_exec(
                    "UPDATE friends
                     SET status = 'accepted'
                     WHERE user_id = ?
                       AND friend_email = ?
                       AND status = 'pending'",
                    [$peerId, $myEmail]
                );
            } catch (Throwable $e) {
                error_log('legacy mirror accept failed: ' . $e->getMessage());
            }

            clear_friend_notifications($myEmail, $email);
            clear_friend_notifications($email, $myEmail);
            
            notify_user(
                $email,
                'friend_accepted',
                'Заявка принята',
                "{$myName} принял(а) вашу заявку в друзья",
                $myEmail,
                url('profile?user=' . urlencode($myEmail))
            );
            
            flush_friends_cache($myEmail, $email);

            json_response([
                'ok'     => true,
                'status' => 'accepted',
            ]);
        }

        // ====================================================
        // REJECT — отклонить входящую заявку
        // ====================================================

        case 'reject': {

            // Отклоняем только входящую заявку.
            // Принятую дружбу или исходящую заявку не удаляем.
            $incoming = db_one(
                "SELECT id
                 FROM friendships
                 WHERE user_email = ?
                   AND friend_email = ?
                   AND status = 'pending'
                 LIMIT 1",
                [$email, $myEmail]
            );

            if (!$incoming) {
                flush_friends_cache($myEmail, $email);

                json_response([
                    'ok'     => true,
                    'status' => 'no_pending_request',
                ]);
            }

            db_exec(
                "DELETE FROM friendships
                 WHERE user_email = ?
                   AND friend_email = ?
                   AND status = 'pending'",
                [$email, $myEmail]
            );

            // Удаляем старую запись, если она существует.
            try {
                db_exec(
                    "DELETE FROM friends
                     WHERE user_id = ?
                       AND friend_email = ?
                       AND status = 'pending'",
                    [$peerId, $myEmail]
                );
            } catch (Throwable $e) {
                error_log('legacy mirror reject failed: ' . $e->getMessage());
            }

            clear_friend_notifications($myEmail, $email);

            flush_friends_cache($myEmail, $email);

            json_response([
                'ok'     => true,
                'status' => 'rejected',
            ]);
        }

        // ====================================================
        // REMOVE — удалить друга
        // ====================================================

        case 'remove': {

            // Удаляем связь в обе стороны.
            db_exec(
                "DELETE FROM friendships
                 WHERE (user_email = ? AND friend_email = ?)
                    OR (user_email = ? AND friend_email = ?)",
                friend_pair_params($myEmail, $email)
            );

            // Удаляем старые записи, если используется legacy-таблица.
            // Ошибка здесь не должна прерывать основной сценарий.
            try {
                db_exec(
                    "DELETE FROM friends
                     WHERE (user_id = ? AND friend_email = ?)
                        OR (user_id = ? AND friend_email = ?)",
                    [$myId, $email, $peerId, $myEmail]
                );
            } catch (Throwable $e) {
                error_log('legacy mirror remove failed: ' . $e->getMessage());
            }

            // Уведомляем второго пользователя.
            notify_user(
                $email,
                'friend_removed',
                'Друг удалён',
                "{$myName} удалил(а) вас из друзей",
                $myEmail,
                null
            );

            clear_friend_notifications($myEmail, $email);
            clear_friend_notifications($email, $myEmail);
            
            notify_user($email, 'friend_removed', 'Друг удалён',
                "{$myName} удалил(а) вас из друзей", $myEmail, null);
            
            flush_friends_cache($myEmail, $email);

            json_response([
                'ok'     => true,
                'status' => 'removed',
            ]);
        }

        // ====================================================
        // BLOCK — заблокировать пользователя
        // ====================================================

        case 'block': {

            $alreadyBlocked = db_one(
                "SELECT id
                 FROM blacklist
                 WHERE user_email = ?
                   AND blocked_email = ?
                 LIMIT 1",
                [$myEmail, $email]
            );

            if (!$alreadyBlocked) {
                db_insert(
                    "INSERT INTO blacklist (user_email, blocked_email)
                     VALUES (?, ?)",
                    [$myEmail, $email]
                );
            }

            // Удаляем дружбу и заявки в обе стороны.
            db_exec(
                "DELETE FROM friendships
                 WHERE (user_email = ? AND friend_email = ?)
                    OR (user_email = ? AND friend_email = ?)",
                friend_pair_params($myEmail, $email)
            );

            try {
                db_exec(
                    "DELETE FROM friends
                     WHERE (user_id = ? AND friend_email = ?)
                        OR (user_id = ? AND friend_email = ?)",
                    [$myId, $email, $peerId, $myEmail]
                );
            } catch (Throwable $e) {
                error_log('legacy mirror block failed: ' . $e->getMessage());
            }

            clear_friend_notifications($myEmail, $email);
            clear_friend_notifications($email, $myEmail);

            flush_friends_cache($myEmail, $email);

            json_response([
                'ok'     => true,
                'status' => 'blocked',
            ]);
        }

        // ====================================================
        // UNBLOCK — разблокировать пользователя
        // ====================================================

        case 'unblock': {

            db_exec(
                "DELETE FROM blacklist
                 WHERE user_email = ?
                   AND blocked_email = ?",
                [$myEmail, $email]
            );

            flush_friends_cache($myEmail, $email);

            json_response([
                'ok'     => true,
                'status' => 'unblocked',
            ]);
        }
    }

} catch (Throwable $e) {
    error_log(
        'api/friends.php [' . $action . ']: ' . $e->getMessage()
    );

    json_response([
        'error' => 'Внутренняя ошибка при выполнении операции'
    ], 500);
}