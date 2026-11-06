<?php
/**
 * Сохранение анкеты диетолога
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'error' => 'Требуется авторизация']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Только POST']);
    exit;
}

$userEmail = current_user()['email'];

$goal = trim($_POST['goal'] ?? '');
$gender = trim($_POST['gender'] ?? '');
$age = (int)($_POST['age'] ?? 0);
$height = (int)($_POST['height'] ?? 0);
$weight = (float)($_POST['weight'] ?? 0);
$activityLevel = trim($_POST['activity_level'] ?? '');
$medicalConditions = trim($_POST['medical_conditions'] ?? '');
$budget = trim($_POST['budget'] ?? 'medium');

// Аллергии (checkbox)
$allergies = $_POST['allergies'] ?? [];
$allergiesJson = json_encode($allergies, JSON_UNESCAPED_UNICODE);

if (empty($goal) || empty($gender) || $age <= 0 || $height <= 0 || $weight <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Заполните все обязательные поля']);
    exit;
}

// Сохраняем анкету
db_exec(
    'INSERT INTO diet_questionnaires (user_email, goal, gender, age, height, weight, activity_level, medical_conditions, allergies, budget) 
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
    [$userEmail, $goal, $gender, $age, $height, $weight, $activityLevel, $medicalConditions, $allergiesJson, $budget]
);

// Перенаправляем на страницу диетолога
header('Location: ' . url('dietolog'));
exit;