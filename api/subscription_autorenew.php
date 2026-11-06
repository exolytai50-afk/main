<?php
/**
 * POST /api/subscription_autorenew.php  body: { enabled: bool }
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'Только POST'], 405);
if (!is_logged_in()) json_response(['error' => 'Требуется вход (401)'], 401);

$enabled = !empty(json_input()['enabled']);
$email   = current_user()['email'];

$sub = db_one(
    "SELECT id, payment_method_id, subscription_end_date FROM subscriptions
     WHERE user_email=? AND status='active' AND subscription_end_date >= CURDATE()
     ORDER BY subscription_end_date DESC LIMIT 1",
    [$email]
);
if (!$sub) json_response(['error' => 'Активная подписка не найдена'], 404);
if ($enabled && empty($sub['payment_method_id'])) {
    json_response(['error' => 'Нет сохранённого способа оплаты. Оформите подписку заново с автопродлением.'], 400);
}

db_exec(
    'UPDATE subscriptions SET auto_renew=?, next_charge_date=?, renewal_notified=0 WHERE id=?',
    [$enabled ? 1 : 0, $enabled ? $sub['subscription_end_date'] : null, (int)$sub['id']]
);
json_response(['ok' => true, 'auto_renew' => $enabled]);