<?php
/**
 * POST /api/community_remove_member.php
 *
 * Удаление участника владельцем сообщества.
 *
 * body: community_id, user_email
 */
declare(strict_types=1);

error_reporting(0);
ini_set('display_errors', '0');

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(array $d, int $code = 200): void {
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(['ok' => false, 'error' => 'Только POST'], 405);
}

if (!is_logged_in()) {
    out(['ok' => false, 'error' => 'Требуется вход'], 401);
}

$user = current_user();
if (!is_array($user) || empty($user['email'])) {
    out(['ok' => false, 'error' => 'Пользователь не найден'], 401);
}

$myEmail = (string)$user['email'];

// Читаем POST и JSON
$communityId = (int)($_POST['community_id'] ?? 0);
$targetEmail = trim((string)($_POST['user_email'] ?? ''));

if ($communityId <= 0 || $targetEmail === '') {
    $raw  = file_get_contents('php://input');
    $json = json_decode($raw ?: '[]', true);
    if (is_array($json)) {
        if ($communityId <= 0 && !empty($json['community_id'])) {
            $communityId = (int)$json['community_id'];
        }
        if ($targetEmail === '' && !empty($json['user_email'])) {
            $targetEmail = trim((string)$json['user_email']);
        }
    }
}

if ($communityId <= 0) {
    out(['ok' => false, 'error' => 'Не указано сообщество'], 400);
}

if ($targetEmail === '') {
    out(['ok' => false, 'error' => 'Не указан участник'], 400);
}

$community = db_one('SELECT id, author_email, name FROM communities WHERE id = ?', [$communityId]);
if (!$community) {
    out(['ok' => false, 'error' => 'Сообщество не найдено'], 404);
}

// Проверяем, что вызвавший — владелец
if ((string)$community['author_email'] !== $myEmail) {
    out(['ok' => false, 'error' => 'Удалять участников может только владелец'], 403);
}

// Владелец не может удалить сам себя
if ($targetEmail === $myEmail) {
    out(['ok' => false, 'error' => 'Владелец не может удалить себя'], 403);
}

try {
    $deleted = db_exec(
        'DELETE FROM community_members 
         WHERE community_id = ? 
           AND CONVERT(user_email USING utf8mb4) = CONVERT(? USING utf8mb4)',
        [$communityId, $targetEmail]
    );

    if ($deleted === 0) {
        out(['ok' => false, 'error' => 'Участник не найден в сообществе'], 404);
    }

    // Уменьшаем счётчик
    db_exec(
        'UPDATE communities SET members_count = GREATEST(0, COALESCE(members_count, 1) - 1) WHERE id = ?',
        [$communityId]
    );

    // Уведомление удалённому — не критично
    try {
        if (function_exists('send_notification')) {
            send_notification(
                $targetEmail,
                'Вы исключены из сообщества',
                'Вас исключили из сообщества: ' . ($community['name'] ?? ''),
                'system'
            );
        }
    } catch (Throwable $e) {
        error_log('send_notification (community_remove): ' . $e->getMessage());
    }

    // Активность — не критично
    try {
        if (function_exists('add_activity')) {
            add_activity(
                $myEmail,
                'community_remove_member',
                $communityId,
                'community',
                'Исключил участника ' . $targetEmail . ' из ' . ($community['name'] ?? '')
            );
        }
    } catch (Throwable $e) {
        error_log('add_activity (community_remove): ' . $e->getMessage());
    }

    out(['ok' => true, 'message' => 'Участник удалён']);

} catch (Throwable $e) {
    error_log('community_remove_member error: ' . $e->getMessage());
    out([
        'ok'    => false,
        'error' => defined('APP_DEBUG') && APP_DEBUG ? $e->getMessage() : 'Ошибка удаления',
    ], 500);
}