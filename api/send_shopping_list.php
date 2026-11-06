<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Только POST'], 405);
}

$in = json_decode(file_get_contents('php://input'), true);

$email = trim((string)($in['email'] ?? ''));
$items = $in['items'] ?? [];
$title = trim((string)($in['recipe_title'] ?? 'Рецепт'));

// Собираем строки
$lines = [];
foreach ((array)$items as $item) {
    $text = is_array($item) ? trim((string)($item['text'] ?? '')) : trim((string)$item);
    if ($text !== '') {
        $lines[] = $text;
    }
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['error' => 'Некорректный email'], 400);
}

if (empty($lines)) {
    json_response(['error' => 'Список пуст', 'debug_items' => $items], 400);
}

// Письмо
$list = implode("\n", array_map(fn($i, $t) => ($i+1).'. '.$t, array_keys($lines), $lines));

$subject = '=?UTF-8?B?' . base64_encode("Список покупок: {$title}") . '?=';
$body = "Здравствуйте!\n\nСписок покупок для «{$title}»:\n\n{$list}\n\n---\nGigaChefAI\n";
$headers = "From: GigaChefAI <no-reply@cookai.ru>\r\nContent-Type: text/plain; charset=UTF-8\r\n";

$sent = @mail($email, $subject, $body, $headers);

error_log("Shopping list mail to {$email}: " . ($sent ? 'OK' : 'FAIL') . ', items: ' . count($lines));

json_response([
    'ok' => $sent,
    'message' => $sent ? 'Отправлено!' : 'Ошибка отправки письма',
    'items_count' => count($lines)
]);