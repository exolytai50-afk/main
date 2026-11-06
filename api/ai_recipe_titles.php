<?php
/**
 * POST /api/ai_recipe_titles.php
 * Возвращает список уникальных названий рецептов по теме.
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/yandex.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Только POST'], 405);
}

$in = json_input();
$theme = trim((string)($in['theme'] ?? ''));
$count = max(1, min(10, (int)($in['count'] ?? 5)));

if ($theme === '') {
    json_response(['error' => 'Укажите тему'], 400);
}

$schema = '{"titles": ["название 1", "название 2", "..."]}';

$prompt = "Ты — шеф-повар. Придумай РОВНО {$count} РАЗНЫХ названий блюд на русском языке по теме: «{$theme}».\n"
        . "Все названия должны быть уникальными и не повторяться. Не пиши описания, только названия.\n"
        . "Ответь СТРОГО JSON в формате: " . $schema;

try {
    $data = yandex_gpt_json($prompt, $schema);
    $titles = $data['titles'] ?? [];
    
    // Убираем дубликаты и пустые
    $titles = array_values(array_unique(array_filter(array_map('trim', $titles))));
    
    // Если AI вернул меньше, добиваем вариантами
    while (count($titles) < $count) {
        $titles[] = $theme . ' (вариант ' . (count($titles) + 1) . ')';
    }

    json_response(['ok' => true, 'titles' => array_slice($titles, 0, $count)]);
} catch (Throwable $ex) {
    json_response(['error' => APP_DEBUG ? $ex->getMessage() : 'Ошибка генерации названий'], 500);
}