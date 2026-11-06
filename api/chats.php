<?php
require_once __DIR__ . '/../config/config.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
if (!$user) {
    echo json_encode(['success' => false, 'error' => 'Требуется авторизация'], JSON_UNESCAPED_UNICODE);
    exit;
}
$userEmail = $user['email'];

// ============================================================
// Чтение тела запроса (JSON или form-data)
// ============================================================
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    $data = $_POST;
}

/**
 * Универсальный доступ к параметру: JSON → POST → GET.
 */
function req(string $key, $default = null)
{
    global $data;
    return $data[$key] ?? $_POST[$key] ?? $_GET[$key] ?? $default;
}

$action = (string)req('action', '');

// ============================================================
// Хелперы
// ============================================================

/**
 * Проверить, что пользователь — участник группы.
 */
function isGroupMember(int $groupId, string $userEmail): bool
{
    $row = db_one(
        'SELECT 1 FROM chat_group_members WHERE group_id = ? AND user_email = ?',
        [$groupId, $userEmail]
    );
    return (bool)$row;
}

/**
 * Разобрать pin_key. Возвращает [type, value] или null.
 *   "foo@bar.com" => ['user', 'foo@bar.com']
 *   "group:5"     => ['group', 5]
 *
 * Личные чаты БЕЗ префикса — совместимо со старыми данными в chat_favorites.
 */
function parsePinKey(string $pinKey): ?array
{
    if (str_starts_with($pinKey, 'group:')) {
        $id = (int)substr($pinKey, 6);
        return $id > 0 ? ['group', $id] : null;
    }
    if (filter_var($pinKey, FILTER_VALIDATE_EMAIL)) {
        return ['user', $pinKey];
    }
    return null;
}

// ============================================================
// Роутинг
// ============================================================
try {
    switch ($action) {

        // ----------------------------------------------------
        // Удалить личный чат (всю переписку с собеседником)
        // ----------------------------------------------------
        case 'delete':
            $email = trim((string)req('email', ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'error' => 'Некорректный email'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            db_query(
                "DELETE FROM chat_messages
                 WHERE (sender_email = ? AND receiver_email = ?)
                    OR (sender_email = ? AND receiver_email = ?)",
                [$userEmail, $email, $email, $userEmail]
            );
            // Ключ без префикса
            db_query(
                "DELETE FROM chat_favorites WHERE user_email = ? AND counterpart_email = ?",
                [$userEmail, $email]
            );
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            break;

        // ----------------------------------------------------
        // Очистить личный чат (сообщения, но не удалять диалог)
        // ----------------------------------------------------
        case 'clear':
            $email = trim((string)req('email', ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'error' => 'Некорректный email'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            db_query(
                "DELETE FROM chat_messages
                 WHERE (sender_email = ? AND receiver_email = ?)
                    OR (sender_email = ? AND receiver_email = ?)",
                [$userEmail, $email, $email, $userEmail]
            );
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            break;

        // ----------------------------------------------------
        // Закрепить/открепить чат (личный или групповой)
        // ----------------------------------------------------
        case 'pin':
            $pinKey = trim((string)req('pin_key', ''));

            if ($pinKey === '') {
                echo json_encode([
                    'success' => false,
                    'error'   => 'Не указан ключ закрепления',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $parsed = parsePinKey($pinKey);
            if ($parsed === null) {
                echo json_encode([
                    'success' => false,
                    'error'   => 'Некорректный ключ закрепления',
                    'pin_key' => $pinKey,
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            [$type, $value] = $parsed;

            // Проверка прав
            if ($type === 'group') {
                if (!isGroupMember((int)$value, $userEmail)) {
                    echo json_encode(['success' => false, 'error' => 'Нет доступа к группе'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            } else {
                $exists = db_one(
                    'SELECT 1 FROM users WHERE email COLLATE utf8mb4_unicode_ci = ?',
                    [$value]
                );
                if (!$exists) {
                    echo json_encode(['success' => false, 'error' => 'Пользователь не найден'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }

            // Текущее состояние
            $existing = db_one(
                'SELECT 1 FROM chat_favorites WHERE user_email = ? AND counterpart_email = ?',
                [$userEmail, $pinKey]
            );

            if ($existing) {
                db_query(
                    'DELETE FROM chat_favorites WHERE user_email = ? AND counterpart_email = ?',
                    [$userEmail, $pinKey]
                );
                echo json_encode(['success' => true, 'pinned' => false], JSON_UNESCAPED_UNICODE);
            } else {
                db_query(
                    'INSERT INTO chat_favorites (user_email, counterpart_email) VALUES (?, ?)',
                    [$userEmail, $pinKey]
                );
                echo json_encode(['success' => true, 'pinned' => true], JSON_UNESCAPED_UNICODE);
            }
            break;

        // ----------------------------------------------------
        // Создать групповой чат
        // ----------------------------------------------------
        case 'create_group':
            $usersList = req('users', []);

            if (!is_array($usersList) || count($usersList) < 2) {
                echo json_encode(['success' => false, 'error' => 'Нужно минимум 2 участника'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            foreach ($usersList as $ue) {
                if (!filter_var($ue, FILTER_VALIDATE_EMAIL)) {
                    echo json_encode(['success' => false, 'error' => 'Некорректный email: ' . $ue], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }

            if (!in_array($userEmail, $usersList)) {
                $usersList[] = $userEmail;
            }
            $usersList = array_values(array_unique($usersList));

            // Автоназвание группы
            $names = [];
            foreach ($usersList as $ue) {
                $u = db_one(
                    'SELECT display_name FROM users WHERE email COLLATE utf8mb4_unicode_ci = ?',
                    [$ue]
                );
                if ($u) {
                    $names[] = $u['display_name'] ?? $ue;
                }
            }
            $groupName = mb_substr(implode(', ', $names), 0, 100);

            // Создаём группу
            db_query(
                "INSERT INTO chat_groups (name, creator_email, created_at) VALUES (?, ?, NOW())",
                [$groupName, $userEmail]
            );

            $row = db_one("SELECT LAST_INSERT_ID() AS id");
            $groupId = (int)$row['id'];

            // Добавляем участников
            foreach ($usersList as $ue) {
                db_query(
                    "INSERT INTO chat_group_members (group_id, user_email) VALUES (?, ?)",
                    [$groupId, $ue]
                );
            }

            echo json_encode(['success' => true, 'group_id' => $groupId], JSON_UNESCAPED_UNICODE);
            break;

        // ----------------------------------------------------
        // Неизвестное действие
        // ----------------------------------------------------
        default:
            echo json_encode([
                'success' => false,
                'error'   => 'Неизвестное действие: ' . $action,
            ], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Ошибка сервера: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}