<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Требуется вход'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user      = current_user();
$userEmail = $user['email'];
$isAdmin   = ($user['role'] ?? 'user') === 'admin';
$action    = $_GET['action'] ?? '';

try {
    /* ════════════════════════════════════════════════
       LIST — сообщения диалога
       ════════════════════════════════════════════════ */
    if ($action === 'list') {
        if ($isAdmin && !empty($_GET['user'])) {
            $otherEmail = (string)$_GET['user'];
            $meEmail    = $userEmail;
        } else {
            $adminRow   = db_one("SELECT email FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
            $otherEmail = $adminRow['email'] ?? '';
            $meEmail    = $userEmail;
        }

        if ($otherEmail === '' || $otherEmail === null) {
            echo json_encode(['ok' => true, 'messages' => []], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $messages = db_all(
            "SELECT id, from_email, to_email, message, is_read, created_at
             FROM admin_chat_messages
             WHERE (from_email = ? AND to_email = ?)
                OR (from_email = ? AND to_email = ?)
             ORDER BY created_at ASC
             LIMIT 500",
            [$meEmail, $otherEmail, $otherEmail, $meEmail]
        );

        db_exec(
            "UPDATE admin_chat_messages
             SET is_read = 1
             WHERE to_email = ? AND from_email = ? AND is_read = 0",
            [$meEmail, $otherEmail]
        );

        $out = [];
        foreach ($messages as $m) {
            $out[] = [
                'id'         => (int)$m['id'],
                'text'       => (string)$m['message'],
                'is_admin'   => ($m['from_email'] === $otherEmail),
                'created_at' => $m['created_at'],
            ];
        }

        echo json_encode(['ok' => true, 'messages' => $out], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ════════════════════════════════════════════════
       SEND — отправка
       ════════════════════════════════════════════════ */
    if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $message = trim((string)($_POST['message'] ?? ''));

        if ($message === '' || mb_strlen($message) > 5000) {
            echo json_encode(['ok' => false, 'error' => 'Сообщение пустое или слишком длинное'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($isAdmin) {
            // ═══ АДМИН ═══
            $toEmail = trim((string)($_POST['to'] ?? ''));

            if ($toEmail === '') {
                echo json_encode(['ok' => false, 'error' => 'Не выбран получатель. Откройте /admin/messages'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if ($toEmail === $userEmail) {
                echo json_encode(['ok' => false, 'error' => 'Нельзя писать самому себе'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $exists = db_one('SELECT id FROM users WHERE email = ?', [$toEmail]);
            if (!$exists) {
                echo json_encode(['ok' => false, 'error' => 'Получатель не найден'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $fromEmail = $userEmail;
        } else {
            // ═══ ПОЛЬЗОВАТЕЛЬ ═══
            $admin = db_one("SELECT email FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
            if (!$admin) {
                echo json_encode(['ok' => false, 'error' => 'Админ не найден'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $toEmail   = $admin['email'];
            $fromEmail = $userEmail;
        }

        db_exec(
            "INSERT INTO admin_chat_messages (from_email, to_email, message, is_read, created_at)
             VALUES (?, ?, ?, 0, NOW())",
            [$fromEmail, $toEmail, $message]
        );
        
        /* ═══ Автоответ для первого сообщения пользователя ═══ */
if (!$isAdmin) {
    // Проверяем: это ПЕРВОЕ сообщение пользователя админу?
    $count = db_one(
        "SELECT COUNT(*) AS cnt FROM admin_chat_messages
         WHERE from_email = ? AND to_email = ?",
        [$fromEmail, $toEmail]
    );
    $isFirst = ((int)($count['cnt'] ?? 0) === 1);

    if ($isFirst) {
        $autoReply = 'Привет! 👋 Я получил ваше сообщение. Отвечу в течение дня. Если срочно — напишите на email админа из раздела «О нас».';

        db_exec(
            "INSERT INTO admin_chat_messages (from_email, to_email, message, is_read, created_at)
             VALUES (?, ?, ?, 0, NOW())",
            [$toEmail, $fromEmail, $autoReply]   // ← от админа к пользователю
        );
    }
}

        if (function_exists('send_notification')) {
            try {
                send_notification(
                    $toEmail,
                    $isAdmin ? '💬 Ответ администратора' : '📩 Новое сообщение админу',
                    mb_substr($message, 0, 100),
                    'message'
                );
            } catch (Throwable $e) {
                error_log('send_notification error: ' . $e->getMessage());
            }
        }

        echo json_encode([
            'ok'   => true,
            'from' => $fromEmail,
            'to'   => $toEmail,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ════════════════════════════════════════════════
       UNREAD — количество непрочитанных
       ════════════════════════════════════════════════ */
    if ($action === 'unread') {
        if ($isAdmin) {
            $row = db_one(
                "SELECT COUNT(*) AS cnt FROM admin_chat_messages
                 WHERE to_email = ? AND is_read = 0",
                [$userEmail]
            );
        } else {
            $row = db_one(
                "SELECT COUNT(*) AS cnt FROM admin_chat_messages
                 WHERE to_email = ? AND from_email != ? AND is_read = 0",
                [$userEmail, $userEmail]
            );
        }
        echo json_encode(['ok' => true, 'unread' => (int)($row['cnt'] ?? 0)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ════════════════════════════════════════════════
       CONVERSATIONS — список диалогов (только админ)
       ════════════════════════════════════════════════ */
    if ($action === 'conversations') {
        if (!$isAdmin) {
            echo json_encode(['ok' => false, 'error' => 'Доступ только для админа'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $adminEmail = $userEmail;

        $conversations = db_all(
            "SELECT
                other.email AS user_email,
                u.display_name,
                u.avatar_url,
                (SELECT message FROM admin_chat_messages m2
                 WHERE (m2.from_email = other.email AND m2.to_email = ?)
                    OR (m2.from_email = ? AND m2.to_email = other.email)
                 ORDER BY m2.created_at DESC LIMIT 1) AS last_message,
                (SELECT created_at FROM admin_chat_messages m3
                 WHERE (m3.from_email = other.email AND m3.to_email = ?)
                    OR (m3.from_email = ? AND m3.to_email = other.email)
                 ORDER BY m3.created_at DESC LIMIT 1) AS last_at,
                (SELECT COUNT(*) FROM admin_chat_messages m4
                 WHERE m4.from_email = other.email AND m4.to_email = ? AND m4.is_read = 0) AS unread
             FROM (
                SELECT DISTINCT from_email AS email FROM admin_chat_messages WHERE to_email = ?
                UNION
                SELECT DISTINCT to_email   AS email FROM admin_chat_messages WHERE from_email = ?
             ) AS other
             LEFT JOIN users u ON u.email = other.email
             WHERE other.email != ?
             ORDER BY last_at DESC",
            [
                $adminEmail, $adminEmail,
                $adminEmail, $adminEmail,
                $adminEmail,
                $adminEmail, $adminEmail,
                $adminEmail,
            ]
        );

        echo json_encode(['ok' => true, 'conversations' => $conversations], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Неизвестный action'], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('admin_chat error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Ошибка сервера: ' . ($e->getMessage())], JSON_UNESCAPED_UNICODE);
}