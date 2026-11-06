<?php
/**
 * POST /api/ai_what_to_cook.php
 * body: { ingredients: [string], diet?, time? }
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/yandex.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Только POST'], 405);
}

$in    = json_input();
$ings  = array_values(array_filter(array_map('trim', (array)($in['ingredients'] ?? []))));
$diet  = trim((string)($in['diet'] ?? ''));
$time  = (int)($in['time'] ?? 0);

if (!$ings) {
    json_response(['error' => 'Добавьте хотя бы один продукт'], 400);
}

$schema = 'Структура JSON: {
  "dishes": [
    {"title":"название блюда","description":"краткое описание",
     "time":число_минут,
     "have":["продукты из списка пользователя, что есть"],
     "need":["продукты, которых не хватает, максимум 3"]}
  ]
}';

$prompt = 'Подбери 4-5 блюд, которые можно приготовить в основном из этих продуктов: '
        . implode(', ', $ings) . '.'
        . ($diet ? " Тип питания: {$diet}." : '')
        . ($time ? " Время приготовления не более {$time} минут." : '')
        . ' Отдавай приоритет блюдам, где почти все ингредиенты уже есть у пользователя.';

try {
    $data   = yandex_gpt_json($prompt, $schema);
    $dishes = array_values(array_filter((array)($data['dishes'] ?? [])));

    // Санитайзинг
    $clean = array_map(function ($d) {
        return [
            'title'       => mb_substr(trim((string)($d['title'] ?? 'Блюдо')), 0, 255),
            'description' => trim((string)($d['description'] ?? '')),
            'time'        => max(0, (int)($d['time'] ?? 0)),
            'have'        => array_values(array_slice((array)($d['have'] ?? []), 0, 10)),
            'need'        => array_values(array_slice((array)($d['need'] ?? []), 0, 3)),
        ];
    }, $dishes);

    json_response(['ok' => true, 'dishes' => $clean]);
} catch (Throwable $ex) {
    json_response(['error' => APP_DEBUG ? $ex->getMessage() : 'AI временно недоступен'], 500);
}