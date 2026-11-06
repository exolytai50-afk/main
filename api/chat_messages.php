<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Требуется вход'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userEmail = current_user()['email'];
$userName = current_user()['display_name'] ?? $userEmail;

// Проверяем и добавляем колонки
try {
    $columns = db_all("SHOW COLUMNS FROM chat_messages");
    $columnNames = array_column($columns, 'Field');

    if (!in_array('sticker_url', $columnNames)) {
        db_exec("ALTER TABLE chat_messages ADD COLUMN sticker_url varchar(500) DEFAULT NULL AFTER message");
    }
    if (!in_array('image_url', $columnNames)) {
        db_exec("ALTER TABLE chat_messages ADD COLUMN image_url varchar(500) DEFAULT NULL AFTER sticker_url");
    }
    if (!in_array('is_edited', $columnNames)) {
        db_exec("ALTER TABLE chat_messages ADD COLUMN is_edited tinyint(1) DEFAULT 0 AFTER is_read");
    }
    if (!in_array('is_deleted', $columnNames)) {
        db_exec("ALTER TABLE chat_messages ADD COLUMN is_deleted tinyint(1) DEFAULT 0 AFTER is_edited");
    }
    if (!in_array('quote_id', $columnNames)) {
        db_exec("ALTER TABLE chat_messages ADD COLUMN quote_id int(11) DEFAULT NULL AFTER is_deleted");
    }
} catch (Throwable $e) {
    error_log('Error adding chat columns: ' . $e->getMessage());
}

// Получаем JSON input (для PUT и POST без файла)
$input = json_input();

// ====== DELETE ======
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $action = trim($_GET['action'] ?? '');
    $counterpartEmail = trim($_GET['email'] ?? '');
    $messageId = (int)($_GET['id'] ?? 0);

    // Удаление одного сообщения
    if ($messageId > 0 && empty($action)) {
        try {
            $msg = db_one('SELECT * FROM chat_messages WHERE id = ?', [$messageId]);
            if (!$msg) {
                echo json_encode(['ok' => false, 'error' => 'Сообщение не найдено'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if ($msg['sender_email'] !== $userEmail && !is_admin()) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'Нет прав на удаление'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            db_exec('UPDATE chat_messages SET is_deleted = 1 WHERE id = ?', [$messageId]);
            echo json_encode(['ok' => true, 'message' => 'Сообщение удалено'], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log('Delete message error: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'error' => 'Ошибка при удалении: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    if (empty($counterpartEmail)) {
        echo json_encode(['ok' => false, 'error' => 'Не указан email'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Очистка чата — мягкое удаление
    if ($action === 'clear') {
        try {
            db_exec(
                "UPDATE chat_messages SET is_deleted = 1
                 WHERE (sender_email = ? AND receiver_email = ?)
                    OR (sender_email = ? AND receiver_email = ?)",
                [$userEmail, $counterpartEmail, $counterpartEmail, $userEmail]
            );
            echo json_encode(['ok' => true, 'message' => 'Чат очищен'], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log('Clear chat error: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'error' => 'Ошибка при очистке чата: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    // Полное удаление чата
    if ($action === 'delete') {
        try {
            db_exec(
                "DELETE FROM chat_messages
                 WHERE (sender_email = ? AND receiver_email = ?)
                    OR (sender_email = ? AND receiver_email = ?)",
                [$userEmail, $counterpartEmail, $counterpartEmail, $userEmail]
            );
            echo json_encode(['ok' => true, 'message' => 'Чат удалён'], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log('Delete chat error: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'error' => 'Ошибка при удалении чата: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Неизвестное действие'], JSON_UNESCAPED_UNICODE);
    exit;
}

// GET — получение сообщений
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $counterpartEmail = trim($_GET['email'] ?? '');

    if (empty($counterpartEmail)) {
        echo json_encode(['ok' => false, 'error' => 'Не указан email'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        // Собираем только существующие колонки
        $columns = db_all("SHOW COLUMNS FROM chat_messages");
        $columnNames = array_column($columns, 'Field');

        $hasImage   = in_array('image_url', $columnNames);
        $hasQuote   = in_array('quote_id', $columnNames);
        $hasEdited  = in_array('is_edited', $columnNames);
        $hasDeleted = in_array('is_deleted', $columnNames);
        $hasSticker = in_array('sticker_url', $columnNames);

        $selectParts = [
    'm.id',
    'm.sender_email',
    'm.receiver_email',
    'm.message',
];
if ($hasSticker) $selectParts[] = 'm.sticker_url';
if ($hasImage)   $selectParts[] = 'm.image_url';
$selectParts[] = 'm.is_read';
if ($hasEdited)  $selectParts[] = 'm.is_edited';
if ($hasDeleted) $selectParts[] = 'm.is_deleted';
$selectParts[] = 'm.created_at';

// Данные автора самого сообщения
$selectParts[] = 'u.display_name AS display_name';
$selectParts[] = 'u.avatar_url   AS avatar_url';
$selectParts[] = 'u.gender       AS gender';

if ($hasQuote) {
    $selectParts[] = 'm.quote_id';
    $selectParts[] = 'q.message       AS quoted_text';
    $selectParts[] = 'q.sender_email  AS quoted_sender_email';
    $selectParts[] = 'q.image_url     AS quoted_image_url';
    $selectParts[] = 'q.sticker_url   AS quoted_sticker_url';
    // Данные автора цитируемого сообщения
    $selectParts[] = 'qu.display_name AS quoted_author';
    $selectParts[] = 'qu.avatar_url   AS quoted_avatar_url';
    $selectParts[] = 'qu.gender       AS quoted_gender';
}

$selectSql = implode(', ', $selectParts);

// Автор сообщения
$joinSql = "LEFT JOIN users u ON u.email = m.sender_email ";

// Цитата и её автор
if ($hasQuote) {
    $joinSql .= "LEFT JOIN chat_messages q ON m.quote_id = q.id" .
                ($hasDeleted ? " AND q.is_deleted = 0" : "") . " " .
                "LEFT JOIN users qu ON qu.email = q.sender_email";
}

        $whereSql = "((m.sender_email = ? AND m.receiver_email = ?) OR (m.sender_email = ? AND m.receiver_email = ?))";
        if ($hasDeleted) {
            $whereSql .= " AND (m.is_deleted = 0 OR m.is_deleted IS NULL)";
        }

        $messages = db_all(
            "SELECT {$selectSql}
             FROM chat_messages m
             {$joinSql}
             WHERE {$whereSql}
             ORDER BY m.created_at ASC",
            [$userEmail, $counterpartEmail, $counterpartEmail, $userEmail]
        );

        // Отмечаем как прочитанные
        db_exec(
            'UPDATE chat_messages SET is_read = 1
             WHERE receiver_email = ? AND sender_email = ? AND is_read = 0',
            [$userEmail, $counterpartEmail]
        );

        echo json_encode([
            'ok' => true,
            'messages' => $messages
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('Get messages error: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Ошибка при получении сообщений: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ====== POST — отправка сообщения, стикера или изображения ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Поддержка как JSON, так и FormData (для загрузки изображений)
    $isFormData = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false;

    if ($isFormData) {
        $receiverEmail = trim($_POST['email'] ?? $_POST['receiver_email'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $stickerUrl = trim($_POST['sticker_url'] ?? '');
        $quoteId = !empty($_POST['quote_id']) ? (int)$_POST['quote_id'] : null;
    } else {
        $receiverEmail = trim($input['email'] ?? $input['receiver_email'] ?? '');
        $message = trim($input['message'] ?? '');
        $stickerUrl = trim($input['sticker_url'] ?? '');
        $quoteId = !empty($input['quote_id']) ? (int)$input['quote_id'] : null;
    }

    if (empty($receiverEmail)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Не указан получатель'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Обработка загрузки изображения
    $imageUrl = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = ROOT_DIR . '/uploads/chat/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowed)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Недопустимый формат изображения'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $filename = uniqid('chat_') . '.' . $ext;
        $destination = $uploadDir . $filename;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
            $imageUrl = '/uploads/chat/' . $filename;
        } else {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Ошибка загрузки изображения'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    if (empty($message) && empty($stickerUrl) && empty($imageUrl)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Заполните сообщение, выберите стикер или загрузите изображение'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $receiver = db_one('SELECT id FROM users WHERE email = ?', [$receiverEmail]);
        if (!$receiver) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Получатель не найден'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Проверяем цитируемое сообщение
        if ($quoteId) {
            $quotedMsg = db_one(
                'SELECT id FROM chat_messages WHERE id = ? AND ((sender_email = ? AND receiver_email = ?) OR (sender_email = ? AND receiver_email = ?))',
                [$quoteId, $userEmail, $receiverEmail, $receiverEmail, $userEmail]
            );
            if (!$quotedMsg) {
                $quoteId = null;
            }
        }

        $messageId = db_insert(
            'INSERT INTO chat_messages (sender_email, receiver_email, message, sticker_url, image_url, is_read, is_deleted, quote_id)
             VALUES (?, ?, ?, ?, ?, 0, 0, ?)',
            [$userEmail, $receiverEmail, $message ?: '', $stickerUrl ?: null, $imageUrl, $quoteId]
        );

        $sender = db_one(
    'SELECT display_name, avatar_url, gender FROM users WHERE email = ?',
    [$userEmail]
);

echo json_encode([
    'ok'            => true,
    'message_id'    => $messageId,
    'message'       => $message,
    'sticker_url'   => $stickerUrl ?: null,
    'image_url'     => $imageUrl,
    'quote_id'      => $quoteId,
    'created_at'    => date('Y-m-d H:i:s'),
    'sender_email'  => $userEmail,
    'display_name'  => $sender['display_name'] ?? null,
    'avatar_url'    => $sender['avatar_url']   ?? null,
    'gender'        => $sender['gender']       ?? null,
], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('Send message error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Ошибка при отправке: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ====== PUT — редактирование сообщения ======
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $messageId = (int)($input['message_id'] ?? 0);
    // Фронтенд отправляет `text`, старый код — `message`. Поддерживаем оба.
    $newMessage = trim($input['text'] ?? $input['message'] ?? '');

    if ($messageId <= 0 || empty($newMessage)) {
        echo json_encode(['ok' => false, 'error' => 'Заполните сообщение'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $msg = db_one('SELECT * FROM chat_messages WHERE id = ?', [$messageId]);
        if (!$msg) {
            echo json_encode(['ok' => false, 'error' => 'Сообщение не найдено'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($msg['sender_email'] !== $userEmail) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Вы не автор сообщения'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (!empty($msg['sticker_url']) || !empty($msg['image_url'])) {
            echo json_encode(['ok' => false, 'error' => 'Стикеры и изображения нельзя редактировать'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        db_exec(
            'UPDATE chat_messages SET message = ?, is_edited = 1 WHERE id = ?',
            [$newMessage, $messageId]
        );

        echo json_encode(['ok' => true, 'message' => 'Сообщение обновлено'], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('Edit message error: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Ошибка при редактировании: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Метод не поддерживается'], JSON_UNESCAPED_UNICODE);
exit;
