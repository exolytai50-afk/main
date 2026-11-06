<?php
/**
 * POST /api/diary_add.php
 * body: { dish, portion, calories, proteins, fats, carbs, source, confidence }
 * Сохраняет уже посчитанный результат сканера в дневник (без вызова AI).
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'Только POST'], 405);
if (!is_logged_in()) json_response(['error' => 'Требуется вход (401)'], 401);

$in = json_input();
$dish = trim((string)($in['dish'] ?? ''));
if ($dish === '') json_response(['error' => 'Пустое блюдо'], 400);

$source = in_array($in['source'] ?? '', ['photo', 'text'], true) ? $in['source'] : 'photo';

$id = db_insert(
    'INSERT INTO calorie_scans
     (user_email, dish, portion, calories, proteins, fats, carbs, source, confidence, created_at)
     VALUES (?,?,?,?,?,?,?,?,?, NOW())',
    [
        current_user()['email'],
        mb_substr($dish, 0, 200),
        mb_substr((string)($in['portion'] ?? '1 порция'), 0, 120),
        max(0, (int)($in['calories'] ?? 0)),
        max(0, (int)($in['proteins'] ?? 0)),
        max(0, (int)($in['fats'] ?? 0)),
        max(0, (int)($in['carbs'] ?? 0)),
        $source,
        mb_substr((string)($in['confidence'] ?? 'средняя'), 0, 20),
    ]
);

json_response(['ok' => true, 'scan_id' => $id]);