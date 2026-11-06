<?php
require_once __DIR__ . '/../config/config.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
if (!$user) {
    echo json_encode(['success' => false, 'error' => 'Требуется авторизация']);
    exit;
}
$userEmail = $user['email'];

// Различаем JSON и multipart (для загрузки изображений)
if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false) {
    $data = $_POST;
} else {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }
}

$action = $data['action'] ?? '';
$groupId = (int)($data['group_id'] ?? 0);

// Проверка участия в группе для всех действий кроме leave/delete_group
if ($groupId > 0 && !in_array($action, ['leave', 'delete_group'])) {
    $membership = db_one(
        "SELECT group_id FROM chat_group_members
         WHERE group_id = ? AND user_email COLLATE utf8mb4_unicode_ci = ?",
        [$groupId, $userEmail]
    );
    if (!$membership) {
        echo json_encode(['success' => false, 'error' => 'Вы не участник этой группы']);
        exit;
    }
}

switch ($action) {

    // Получить сообщения
    case 'fetch':
        $lastId = (int)($data['last_id'] ?? 0);
        if ($lastId > 0) {
            $messages = db_all(
    "SELECT m.*, u.display_name, u.avatar_emoji, u.avatar_url, u.gender,
            q.message AS quoted_text, q.sender_email AS quoted_sender_email,
            qu.display_name AS quoted_author,
            q.image_url AS quoted_image_url,
            q.sticker_url AS quoted_sticker_url
     FROM chat_group_messages m
     JOIN users u ON u.email COLLATE utf8mb4_unicode_ci = m.sender_email
     LEFT JOIN chat_group_messages q ON q.id = m.quoted_id
     LEFT JOIN users qu ON qu.email COLLATE utf8mb4_unicode_ci = q.sender_email
     WHERE m.group_id = ? AND m.id > ?
     ORDER BY m.created_at ASC",
    [$groupId, $lastId]
);
        } else {
            $messages = db_all(
    "SELECT m.*, u.display_name, u.avatar_emoji, u.avatar_url, u.gender,
            q.message AS quoted_text, q.sender_email AS quoted_sender_email,
            qu.display_name AS quoted_author,
            q.image_url AS quoted_image_url,
            q.sticker_url AS quoted_sticker_url
     FROM chat_group_messages m
     JOIN users u ON u.email COLLATE utf8mb4_unicode_ci = m.sender_email
     LEFT JOIN chat_group_messages q ON q.id = m.quoted_id
     LEFT JOIN users qu ON qu.email COLLATE utf8mb4_unicode_ci = q.sender_email
     WHERE m.group_id = ?
     ORDER BY m.created_at ASC",
    [$groupId]
);
        }
        echo json_encode(['success' => true, 'messages' => $messages]);
        break;

    // Отправить текстовое сообщение
        case 'send':
        $message = trim($data['message'] ?? '');
        $quotedId = (int)($data['quoted_id'] ?? 0);

        if (empty($message) || $message === '<br>') {
            echo json_encode(['success' => false, 'error' => 'Пустое сообщение']);
            exit;
        }

        db_query(
            "INSERT INTO chat_group_messages (group_id, sender_email, message, quoted_id, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$groupId, $userEmail, $message, $quotedId > 0 ? $quotedId : null]
        );
        echo json_encode(['success' => true]);
        break;

    // Отправить стикер
    case 'send_sticker':
        $stickerUrl = trim($data['sticker_url'] ?? '');
        if (empty($stickerUrl)) {
            echo json_encode(['success' => false, 'error' => 'Не указан стикер']);
            exit;
        }

        db_query(
            "INSERT INTO chat_group_messages (group_id, sender_email, sticker_url, created_at)
             VALUES (?, ?, ?, NOW())",
            [$groupId, $userEmail, $stickerUrl]
        );
        echo json_encode(['success' => true]);
        break;

    // Загрузить изображение
            case 'upload_image':
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Нет файла']);
            exit;
        }

        $file = $_FILES['image'];
        $uploadDir = __DIR__ . '/../uploads/chat/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowed)) {
            echo json_encode(['success' => false, 'error' => 'Недопустимый формат']);
            exit;
        }

        $filename = 'group_' . $groupId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $filepath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            echo json_encode(['success' => false, 'error' => 'Ошибка загрузки']);
            exit;
        }

        $imageUrl = '/uploads/chat/' . $filename;
        $messageText = trim($data['message'] ?? '');
        $quotedId = (int)($data['quoted_id'] ?? 0);

        db_query(
            "INSERT INTO chat_group_messages (group_id, sender_email, message, image_url, quoted_id, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [$groupId, $userEmail, $messageText, $imageUrl, $quotedId > 0 ? $quotedId : null]
        );
        echo json_encode(['success' => true, 'image_url' => $imageUrl]);
        break;

    // Удалить сообщение
    case 'delete':
        $msgId = (int)($data['message_id'] ?? 0);
        if ($msgId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Некорректный ID']);
            exit;
        }

        $msg = db_one(
            "SELECT sender_email FROM chat_group_messages WHERE id = ? AND group_id = ?",
            [$msgId, $groupId]
        );
        if (!$msg) {
            echo json_encode(['success' => false, 'error' => 'Сообщение не найдено']);
            exit;
        }

        if ($msg['sender_email'] !== $userEmail) {
            echo json_encode(['success' => false, 'error' => 'Нельзя удалить чужое сообщение']);
            exit;
        }

        // Сбрасываем quoted_id у сообщений, которые ссылаются на удаляемое
        db_query("UPDATE chat_group_messages SET quoted_id = NULL WHERE quoted_id = ?", [$msgId]);
        db_query("DELETE FROM chat_group_messages WHERE id = ?", [$msgId]);
        echo json_encode(['success' => true]);
        break;

    // Редактировать сообщение
    case 'edit':
        $msgId = (int)($data['message_id'] ?? 0);
        $newText = trim($data['message'] ?? '');

        if ($msgId <= 0 || empty($newText)) {
            echo json_encode(['success' => false, 'error' => 'Некорректные данные']);
            exit;
        }

        $msg = db_one(
            "SELECT sender_email FROM chat_group_messages WHERE id = ? AND group_id = ?",
            [$msgId, $groupId]
        );
        if (!$msg || $msg['sender_email'] !== $userEmail) {
            echo json_encode(['success' => false, 'error' => 'Нельзя редактировать']);
            exit;
        }

        db_query(
            "UPDATE chat_group_messages SET message = ?, is_edited = 1 WHERE id = ?",
            [$newText, $msgId]
        );
        echo json_encode(['success' => true]);
        break;

    // Покинуть группу
    case 'leave':
        db_query(
            "DELETE FROM chat_group_members WHERE group_id = ? AND user_email COLLATE utf8mb4_unicode_ci = ?",
            [$groupId, $userEmail]
        );
        echo json_encode(['success' => true]);
        break;

    // Удалить группу (только создатель)
    case 'delete_group':
        $group = db_one("SELECT creator_email FROM chat_groups WHERE id = ?", [$groupId]);
        if (!$group) {
            echo json_encode(['success' => false, 'error' => 'Группа не найдена']);
            exit;
        }
        if ($group['creator_email'] !== $userEmail) {
            echo json_encode(['success' => false, 'error' => 'Только создатель может удалить группу']);
            exit;
        }
        db_query("DELETE FROM chat_group_messages WHERE group_id = ?", [$groupId]);
        db_query("DELETE FROM chat_group_members WHERE group_id = ?", [$groupId]);
        db_query("DELETE FROM chat_groups WHERE id = ?", [$groupId]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
}
