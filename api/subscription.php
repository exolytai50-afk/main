<?php
// api/subscription.php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
if (!is_logged_in()) json_response(['error' => 'Требуется вход (401)'], 401);
$email = current_user()['email'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sub = db_one('SELECT * FROM subscriptions WHERE user_email=? ORDER BY id DESC LIMIT 1', [$email]);
    json_response(['subscription' => $sub, 'active' => check_subscription($email)]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Демо-эмуляция успешной оплаты (замените на реальный платёжный шлюз)
    $in     = json_input();
    $plan   = $in['plan'] ?? 'monthly';
    $months = $plan === 'yearly' ? 12 : 1;
    $amount = $plan === 'yearly' ? 2990.00 : 299.00;

    db_insert(
        'INSERT INTO subscriptions (user_email, status, subscription_end_date, amount) VALUES (?,?,?,?)',
        [$email, 'active', date('Y-m-d', strtotime("+{$months} months")), $amount]
    );
    json_response(['ok' => true, 'active' => true]);
}
json_response(['error' => 'Метод не поддерживается'], 405);