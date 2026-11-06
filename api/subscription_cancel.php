<?php
/**
 * POST /api/subscription_cancel.php
 * Отменяет автопродление. Доступ сохраняется до конца оплаченного периода.
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'Только POST'], 405);
if (!is_logged_in()) json_response(['error' => 'Требуется вход (401)'], 401);

$email = current_user()['email'];
$sub = db_one(
    "SELECT id, subscription_end_date FROM subscriptions
     WHERE user_email=? AND status='active' AND subscription_end_date >= CURDATE()
     ORDER BY subscription_end_date DESC LIMIT 1",
    [$email]
);
if (!$sub) json_response(['error' => 'Активная подписка не найдена'], 404);

db_exec('UPDATE subscriptions SET auto_renew=0, next_charge_date=NULL WHERE id=?', [(int)$sub['id']]);

db_insert(
    'INSERT INTO notifications (user_email, title, message, type, created_at) VALUES (?,?,?,?, NOW())',
    [$email, 'Подписка отменена',
     'Автопродление выключено. Доступ сохранится до ' . date('d.m.Y', strtotime($sub['subscription_end_date'])) . '.',
     'payment']
);
json_response(['ok' => true, 'active_until' => $sub['subscription_end_date']]);