<?php
/**
 * POST /api/ai_substitute.php
 * body: { ingredient: string, reason?: string, dish?: string }
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/yandex.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Только POST'], 405);
}

$in         = json_input();
$ingredient = trim((string)($in['ingredient'] ?? ''));
$reason     = trim((string)($in['reason'] ?? ''));
$dish       = trim((string)($in['dish'] ?? ''));

if ($ingredient === '') {
    json_response(['error' => 'Укажите ингредиент'], 400);
}

$schema = 'Структура JSON: {
  "substitutes": [
    {"name":"string","ratio":"string, например 1:1","note":"чем отличается вкус/текстура","best_for":"string"}
  ]
}';

$prompt = "Подбери 3-4 замены для ингредиента «{$ingredient}»."
        . ($dish   ? " Блюдо: {$dish}." : '')
        . ($reason ? " Причина замены: {$reason}." : '')
        . ' Укажи пропорции и на что влияет замена.';

try {
    $data = yandex_gpt_json($prompt, $schema);
    $subs = array_values(array_filter((array)($data['substitutes'] ?? [])));
    json_response(['ok' => true, 'substitutes' => $subs]);
} catch (Throwable $ex) {
    json_response(['error' => APP_DEBUG ? $ex->getMessage() : 'AI временно недоступен'], 500);
}