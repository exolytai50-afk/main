<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Здесь будет интеграция с API GamePush
// Для примера возвращаем демо-данные

$games = [
    [
        'id' => 'cooking-fever',
        'title' => 'Cooking Fever',
        'description' => 'Готовьте вкусные блюда и управляйте собственным рестораном!',
        'category' => 'cooking',
        'rating' => 4.7,
        'players' => '50000+',
        'embed_url' => 'https://gamepush.com/embed/cooking-fever'
    ],
    // ... другие игры
];

json_response([
    'ok' => true,
    'games' => $games,
    'total' => count($games)
]);