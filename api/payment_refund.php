<?php
/**
 * POST /api/payment_refund.php  body: { subscription_id }
 * Возврат за последний платёж в пределах REFUND_WINDOW_DAYS.
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/yookassa.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'Только POST'], 405);
if (!is_logged_in()) json_response(['error' => 'Требуется вход (401)'], 401);

$sid   = (int)(json_input()['subscription_id'] ?? 0);
$email = current_user()['email'];

$sub = db_one('SELECT * FROM subscriptions WHERE id=? AND user_email=?', [$sid, $email]);
if (!$sub) json_response(['error' => 'Платёж не найден'], 404);
if (empty($sub['payment_id'])) json_response(['error' => 'Нет данных о платеже'], 400);
if ($sub['status'] === 'refunded') json_response(['error' => 'Возврат уже выполнен'], 400);
if (empty($sub['paid_at'])) json_response(['error' => 'Платёж не был завершён'], 400);

// Проверка окна возврата
$daysPassed = (time() - strtotime($sub['paid_at'])) / 86400;
if ($daysPassed > REFUND_WINDOW_DAYS) {
    json_response(['error' => 'Срок возврата истёк (' . REFUND_WINDOW_DAYS . ' дней)'], 400);
}

$refundable = (float)$sub['amount'] - (float)$sub['refunded_amount'];
if ($refundable <= 0) json_response(['error' => 'Нечего возвращать'], 400);

try {
    $refund = yookassa_refund($sub['payment_id'], $refundable, 'Возврат по запросу пользователя');

    // Немедленно фиксируем (webhook refund.succeeded продублирует идемпотентно)
    if (($refund['status'] ?? '') === 'succeeded') {
        db_exec(
            'UPDATE subscriptions
             SET status=?, auto_renew=0, next_charge_date=NULL,
                 refunded_amount=refunded_amount+?, refund_id=?
             WHERE id=?',
            ['refunded', $refundable, ($refund['id'] ?? null), $sid]
        );
        db_insert(
            'INSERT INTO notifications (user_email, title, message, type, created_at) VALUES (?,?,?,?, NOW())',
            [$email, 'Возврат выполнен 💸',
             'Возвращено ' . number_format($refundable, 0) . '₽. Подписка отменена.', 'payment']
        );
    }
    json_response(['ok' => true, 'status' => $refund['status'] ?? 'pending', 'amount' => $refundable]);
} catch (Throwable $ex) {
    json_response(['error' => APP_DEBUG ? $ex->getMessage() : 'Не удалось выполнить возврат'], 500);
}