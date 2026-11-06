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

// ─── GET: список логов тяги ───
if ($method === 'GET' && $action === 'list') {
    $cravings = db_all(
        'SELECT id, intensity, coped, created_at
         FROM quit_smoking_cravings
         WHERE user_email = ?
           AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
         ORDER BY created_at DESC',
        [$email]
    );
    echo json_encode(['cravings' => $cravings]);
    exit;
}

// ─── POST: зафиксировать тягу ───
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) $input = $_POST;

    $intensity = (int)($input['intensity'] ?? 5);
    $coped = !empty($input['coped']) ? 1 : 0;

    if ($intensity < 1) $intensity = 1;
    if ($intensity > 10) $intensity = 10;

    try {
        db_query(
            'INSERT INTO quit_smoking_cravings (user_email, intensity, coped) VALUES (?, ?, ?)',
            [$email, $intensity, $coped]
        );
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка сохранения']);
    }
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Неизвестное действие']);
