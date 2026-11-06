<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'error' => 'Требуется авторизация']);
    exit;
}

$userEmail = current_user()['email'];

// GET - получить шаги за сегодня
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $entry = db_one(
        'SELECT * FROM diet_steps WHERE user_email = ? AND entry_date = CURDATE()',
        [$userEmail]
    );
    
    echo json_encode([
        'ok' => true,
        'steps' => $entry ? (int)$entry['steps_count'] : 0,
        'distance' => $entry ? (float)$entry['distance_km'] : 0,
        'calories' => $entry ? (int)$entry['calories_burned'] : 0,
        'active_minutes' => $entry ? (int)$entry['active_minutes'] : 0
    ]);
    exit;
}

// POST - обновить шаги
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $steps = (int)($input['steps'] ?? 0);
    $distance = (float)($input['distance'] ?? 0);
    $calories = (int)($input['calories'] ?? 0);
    $activeMinutes = (int)($input['active_minutes'] ?? 0);
    
    db_exec(
        'INSERT INTO diet_steps (user_email, entry_date, steps_count, distance_km, calories_burned, active_minutes) 
         VALUES (?, CURDATE(), ?, ?, ?, ?) 
         ON DUPLICATE KEY UPDATE 
         steps_count = VALUES(steps_count), 
         distance_km = VALUES(distance_km), 
         calories_burned = VALUES(calories_burned), 
         active_minutes = VALUES(active_minutes)',
        [$userEmail, $steps, $distance, $calories, $activeMinutes]
    );
    
    echo json_encode(['ok' => true]);
    exit;
}