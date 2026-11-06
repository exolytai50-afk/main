<?php
/**
 * GET  /api/food_diary.php?days=14         — сводка КБЖУ по дням + записи
 * DELETE-эмуляция: POST { action:'delete', id } — удалить запись
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

if (!is_logged_in()) json_response(['error' => 'Требуется вход (401)'], 401);
$email = current_user()['email'];

// --- Удаление записи ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_input();
    if (($in['action'] ?? '') === 'delete') {
        $id = (int)($in['id'] ?? 0);
        db_exec('DELETE FROM calorie_scans WHERE id=? AND user_email=?', [$id, $email]);
        json_response(['ok' => true]);
    }
    json_response(['error' => 'Неизвестное действие'], 400);
}

// --- Чтение ---
$days = max(1, min(90, (int)($_GET['days'] ?? 14)));
$from = date('Y-m-d', strtotime("-{$days} days"));

// Записи за период
$scans = db_all(
    'SELECT id, dish, portion, calories, proteins, fats, carbs, source, confidence, created_at
     FROM calorie_scans
     WHERE user_email=? AND created_at >= ?
     ORDER BY created_at DESC',
    [$email, $from . ' 00:00:00']
);

// Агрегация по дням (сумма КБЖУ)
$byDay = db_all(
    'SELECT DATE(created_at) d,
            COUNT(*) items,
            SUM(calories) calories,
            SUM(proteins) proteins,
            SUM(fats) fats,
            SUM(carbs) carbs
     FROM calorie_scans
     WHERE user_email=? AND created_at >= ?
     GROUP BY DATE(created_at)
     ORDER BY d DESC',
    [$email, $from . ' 00:00:00']
);

// Сводка за сегодня
$today = date('Y-m-d');
$todayRow = db_one(
    'SELECT COUNT(*) items, COALESCE(SUM(calories),0) calories,
            COALESCE(SUM(proteins),0) proteins, COALESCE(SUM(fats),0) fats, COALESCE(SUM(carbs),0) carbs
     FROM calorie_scans WHERE user_email=? AND DATE(created_at)=?',
    [$email, $today]
);

json_response([
    'scans'  => $scans,
    'by_day' => $byDay,
    'today'  => $todayRow,
    'days'   => $days,
]);