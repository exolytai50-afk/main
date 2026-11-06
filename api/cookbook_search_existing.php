<?php
/**
 * POST /api/cookbook_search_existing.php
 * Ищет опубликованные рецепты в базе по теме.
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Только POST'], 405);
}

$in = json_input();
$theme = trim((string)($in['theme'] ?? ''));
$count = max(1, min(20, (int)($in['count'] ?? 5)));

if ($theme === '') {
    json_response(['ok' => false, 'error' => 'Укажите тему'], 400);
}

$searchWords = explode(' ', mb_strtolower($theme));
$searchWords = array_filter($searchWords, fn($w) => mb_strlen($w) > 2);

if (empty($searchWords)) {
    json_response(['ok' => true, 'recipes' => []]);
}

$sql = "SELECT r.* FROM recipes r WHERE ";
$conditions = [];
$params = [];
foreach ($searchWords as $word) {
    $conditions[] = "(LOWER(r.title) LIKE ? OR LOWER(r.description) LIKE ?)";
    $params[] = "%{$word}%";
    $params[] = "%{$word}%";
}
$sql .= "(" . implode(' AND ', $conditions) . ") ";
$sql .= "ORDER BY r.created_at DESC LIMIT " . (int)$count;

try {
    $recipes = db_all($sql, $params);
    json_response(['ok' => true, 'recipes' => $recipes]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 500);
}