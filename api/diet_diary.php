<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'error' => 'Требуется авторизация']);
    exit;
}

$userEmail = current_user()['email'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $entries = db_all(
        'SELECT * FROM diet_diary WHERE user_email = ? AND entry_date = CURDATE() ORDER BY created_at DESC',
        [$userEmail]
    );
    echo json_encode(['ok' => true, 'entries' => $entries]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $mealType = $input['meal_type'] ?? 'breakfast';
    $foodName = trim($input['food_name'] ?? '');
    $portion = trim($input['portion'] ?? '');
    
    if (empty($foodName)) {
        echo json_encode(['ok' => false, 'error' => 'Введите название блюда']);
        exit;
    }
    
    // AI расчет КБЖУ (демо-данные)
    $calories = rand(150, 500);
    $proteins = round($calories * 0.15 / 4, 1);
    $fats = round($calories * 0.25 / 9, 1);
    $carbs = round($calories * 0.55 / 4, 1);
    
    db_exec(
        'INSERT INTO diet_diary (user_email, entry_date, meal_type, food_name, portion, calories, proteins, fats, carbs) VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, ?)',
        [$userEmail, $mealType, $foodName, $portion, $calories, $proteins, $fats, $carbs]
    );
    
    echo json_encode(['ok' => true, 'calories' => $calories, 'proteins' => $proteins, 'fats' => $fats, 'carbs' => $carbs]);
    exit;
}