<?php
// includes/rate_limit.php — простой лимит по сессии
declare(strict_types=1);

function rate_limit(string $key, int $maxPerHour): void
{
    $now  = time();
    $slot = 'rl_' . $key;
    $data = $_SESSION[$slot] ?? ['count' => 0, 'reset' => $now + 3600];

    if ($now > $data['reset']) {
        $data = ['count' => 0, 'reset' => $now + 3600];
    }
    if ($data['count'] >= $maxPerHour) {
        $wait = max(1, (int) ceil(($data['reset'] - $now) / 60));
        json_response(['error' => "Достигнут лимит запросов. Попробуйте через {$wait} мин."], 429);
    }
    $data['count']++;
    $_SESSION[$slot] = $data;
}