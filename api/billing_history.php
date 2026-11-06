<?php
/**
 * GET /api/billing_history.php — история платежей текущего пользователя.
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

if (!is_logged_in()) json_response(['error' => 'Требуется вход (401)'], 401);
$email = current_user()['email'];

$rows = db_all(
    'SELECT id, status, plan, months, amount, original_amount, promo_code, refunded_amount,
            receipt_number, auto_renew, subscription_end_date, next_charge_date, paid_at, created_at
     FROM subscriptions WHERE user_email=? ORDER BY created_at DESC LIMIT 100',
    [$email]
);

$active = db_one(
    "SELECT subscription_end_date, auto_renew, next_charge_date FROM subscriptions
     WHERE user_email=? AND status='active' AND subscription_end_date >= CURDATE()
     ORDER BY subscription_end_date DESC LIMIT 1",
    [$email]
);

json_response([
    'history'     => $rows,
    'active'      => $active ?: null,
    'refund_days' => REFUND_WINDOW_DAYS,
]);