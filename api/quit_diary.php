<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Требуется авторизация']);
    exit;
}

$user = current_user();
$email = $user['email'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ─── GET: список записей ───
if ($method === 'GET' && $action === 'list') {
    $entries = db_all(
        'SELECT id, entry_date, mood, note, cigarettes_today, created_at
         FROM quit_smoking_diary
         WHERE user_email = ?
           AND entry_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         ORDER BY entry_date DESC, created_at DESC',
        [$email]
    );
    echo json_encode(['entries' => $entries]);
    exit;
}

// ─── POST: добавить запись ───
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) $input = $_POST;

    $mood = trim($input['mood'] ?? '');
    $note = trim($input['note'] ?? '');
    $cigsToday = (int)($input['cigarettes_today'] ?? 0);

    $validMoods = ['great', 'good', 'neutral', 'bad', 'awful'];
    if (!in_array($mood, $validMoods)) {
        http_response_code(400);
        echo json_encode(['error' => 'Выберите настроение']);
        exit;
    }
    if ($cigsToday < 0 || $cigsToday > 100) {
        $cigsToday = 0;
    }
    if (mb_strlen($note) > 1000) {
        $note = mb_substr($note, 0, 1000);
    }

    $today = date('Y-m-d');

    try {
        // Проверяем, есть ли уже запись за сегодня — обновляем
        $existing = db_one(
            'SELECT id FROM quit_smoking_diary WHERE user_email = ? AND entry_date = ? LIMIT 1',
            [$email, $today]
        );

        if ($existing) {
            db_query(
                'UPDATE quit_smoking_diary SET mood = ?, note = ?, cigarettes_today = ? WHERE id = ?',
                [$mood, $note, $cigsToday, $existing['id']]
            );
        } else {
            db_query(
                'INSERT INTO quit_smoking_diary (user_email, entry_date, mood, note, cigarettes_today) VALUES (?, ?, ?, ?, ?)',
                [$email, $today, $mood, $note, $cigsToday]
            );
        }

        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка сохранения']);
    }
    exit;
}

// ─── DELETE: удалить запись ───
if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Не указан ID']);
        exit;
    }

    // Проверяем, что запись принадлежит пользователю
    $entry = db_one(
        'SELECT user_email FROM quit_smoking_diary WHERE id = ?',
        [$id]
    );
    if (!$entry || $entry['user_email'] !== $email) {
        http_response_code(403);
        echo json_encode(['error' => 'Доступ запрещён']);
        exit;
    }

    try {
        db_query('DELETE FROM quit_smoking_diary WHERE id = ?', [$id]);
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка удаления']);
    }
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Неизвестное действие']);
