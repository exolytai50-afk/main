<?php
/**
 * POST /api/community_leave.php
 *
 * Выход из сообщества.
 * Владелец выйти не может (нужно сначала передать права или удалить сообщество).
 *
 * body: community_id
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

$userEmail = (string)$user['email'];

// Читаем community_id и из POST, и из JSON
$communityId = (int)($_POST['community_id'] ?? 0);
if ($communityId <= 0) {
    $raw  = file_get_contents('php://input');
    $json = json_decode($raw ?: '[]', true);
    if (is_array($json) && !empty($json['community_id'])) {
        $communityId = (int)$json['community_id'];
    }
}

if ($communityId <= 0) {
    out(['ok' => false, 'error' => 'Не указано сообщество'], 400);
}

$community = db_one('SELECT id, author_email, name FROM communities WHERE id = ?', [$communityId]);
if (!$community) {
    out(['ok' => false, 'error' => 'Сообщество не найдено'], 404);
}

// Владелец не может выйти
if ((string)$community['author_email'] === $userEmail) {
    out(['ok' => false, 'error' => 'Владелец не может покинуть сообщество. Передайте права или удалите сообщество.'], 403);
}

try {
    // Удаляем запись об участнике
    $deleted = db_exec(
        'DELETE FROM community_members 
         WHERE community_id = ? 
           AND CONVERT(user_email USING utf8mb4) = CONVERT(? USING utf8mb4)',
        [$communityId, $userEmail]
    );

    if ($deleted === 0) {
        out(['ok' => false, 'error' => 'Вы не являетесь участником сообщества'], 404);
    }

    // Уменьшаем счётчик
    db_exec(
        'UPDATE communities SET members_count = GREATEST(0, COALESCE(members_count, 1) - 1) WHERE id = ?',
        [$communityId]
    );

    // Активность — не критично
    try {
        if (function_exists('add_activity')) {
            add_activity(
                $userEmail,
                'community_leave',
                $communityId,
                'community',
                'Покинул сообщество: ' . ($community['name'] ?? '')
            );
        }
    } catch (Throwable $e) {
        error_log('add_activity (community_leave): ' . $e->getMessage());
    }

    out(['ok' => true, 'message' => 'Вы вышли из сообщества']);

} catch (Throwable $e) {
    error_log('community_leave error: ' . $e->getMessage());
    out([
        'ok'    => false,
        'error' => defined('APP_DEBUG') && APP_DEBUG ? $e->getMessage() : 'Ошибка выхода',
    ], 500);
}