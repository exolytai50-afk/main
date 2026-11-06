<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

$userId = (int)($_GET['user_id'] ?? 0);

if ($userId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Некорректный user_id'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $items = db_all(
        "SELECT id, points, reason, description, reference_id, reference_type, created_at
         FROM points_log
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 50",
        [$userId]
    );

    // Сводка: сколько всего, за что
    $summary = db_all(
        "SELECT reason, SUM(points) AS total, COUNT(*) AS cnt
         FROM points_log
         WHERE user_id = ?
         GROUP BY reason
         ORDER BY total DESC",
        [$userId]
    );

    echo json_encode([
        'ok'      => true,
        'items'   => $items ?: [],
        'summary' => $summary ?: [],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('points_log error: ' . $e->getMessage());
    echo json_encode([
        'ok'    => false,
        'error' => APP_DEBUG ? $e->getMessage() : 'Ошибка загрузки',
        'items' => [],
    ], 500);
}