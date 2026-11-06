<?php
/**
 * POST /api/payment_webhook.php — webhook ЮKassa.
 * События: payment.succeeded, payment.canceled, refund.succeeded.
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/yookassa.php';
require_once __DIR__ . '/../includes/promo.php';

/**
 * Валидирует формат payment_id (должен быть UUID или цифровой ID)
 */
function validate_payment_id(string $paymentId): bool
{
    // UUID формат (e.g., 550e8400-e29b-41d4-a716-446655440000)
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $paymentId)) {
        return true;
    }
    // Цифровой ID
    if (preg_match('/^\d+$/', $paymentId)) {
        return true;
    }
    return false;
}

$raw   = file_get_contents('php://input');
$event = json_decode($raw, true) ?: [];
$type  = $event['event'] ?? '';
$obj   = $event['object'] ?? [];

try {
    // ---------- ВОЗВРАТ ----------
    if ($type === 'refund.succeeded') {
        $paymentId = $obj['payment_id'] ?? '';
        $amount    = (float)($obj['amount']['value'] ?? 0);

        // Валидация payment_id
        if (!$paymentId || !validate_payment_id($paymentId)) {
            http_response_code(400);
            echo 'invalid payment_id';
            exit;
        }

        if ($paymentId) {
            $sub = db_one('SELECT id, user_email FROM subscriptions WHERE payment_id=?', [$paymentId]);
            if ($sub) {
                db_exec(
                    'UPDATE subscriptions
                     SET status=?, auto_renew=0, next_charge_date=NULL,
                         refunded_amount=refunded_amount+?, refund_id=?
                     WHERE id=?',
                    ['refunded', $amount, ($obj['id'] ?? null), (int)$sub['id']]
                );
                db_insert(
                    'INSERT INTO notifications (user_email, title, message, type, created_at) VALUES (?,?,?,?, NOW())',
                    [$sub['user_email'], 'Возврат выполнен 💸',
                     'Возвращено ' . number_format($amount, 0) . '₽. Подписка отменена.', 'payment']
                );
                
                $stillActive = db_one(
                    "SELECT id FROM subscriptions
                     WHERE user_email = ? AND status = 'active' AND subscription_end_date >= CURDATE()
                     LIMIT 1",
                    [$sub['user_email']]
                );
                if (!$stillActive) {
                    db_exec('UPDATE users SET is_premium = 0 WHERE email = ?', [$sub['user_email']]);
                }
            }
        }
        http_response_code(200);
        echo 'refund ok';
        exit;
    }

    // ---------- ПЛАТЁЖ ----------
    $payId = $obj['id'] ?? '';
    if ($payId === '' || !validate_payment_id($payId)) {
        http_response_code(400);
        echo 'invalid id';
        exit;
    }

    $payment = yookassa_get_payment($payId);
    $status  = $payment['status'] ?? '';
    $meta    = $payment['metadata'] ?? [];
    $localId = (int)($meta['local_id'] ?? 0);
    $months  = max(1, (int)($meta['months'] ?? 1));
    $email   = (string)($meta['email'] ?? '');

    if (($meta['recurring'] ?? '0') === '1') {
        http_response_code(200);
        echo 'recurring handled by cron';
        exit;
    }
    if ($localId <= 0) {
        http_response_code(200);
        echo 'ignored';
        exit;
    }

    $sub = db_one('SELECT id, status, promo_code FROM subscriptions WHERE id=?', [$localId]);
    if (!$sub) {
        http_response_code(200);
        echo 'unknown';
        exit;
    }
    if ($sub['status'] === 'active') {
        http_response_code(200);
        echo 'already active';
        exit;
    }

    if ($status === 'succeeded') {
        $pmId = null;
        if (!empty($payment['payment_method']['saved']) && !empty($payment['payment_method']['id'])) {
            $pmId = $payment['payment_method']['id'];
        }

        $current = db_one(
            'SELECT MAX(subscription_end_date) d FROM subscriptions WHERE user_email=? AND status=?',
            [$email, 'active']
        );
        $base    = (!empty($current['d']) && strtotime($current['d']) > time()) ? $current['d'] : date('Y-m-d');
        $endDate = date('Y-m-d', strtotime($base . " +{$months} months"));

        $autoRenew  = (($meta['auto_renew'] ?? '0') === '1') && $pmId;
        $nextCharge = $autoRenew ? $endDate : null;

        // Уникальный номер квитанции
        $receiptNo = 'CK-' . date('Ymd') . '-' . str_pad((string)$localId, 6, '0', STR_PAD_LEFT);

        db_exec(
            'UPDATE subscriptions
             SET status=?, subscription_end_date=?, next_charge_date=?, payment_method_id=?,
                 auto_renew=?, renew_attempts=0, renewal_notified=0, receipt_number=?, paid_at=NOW()
             WHERE id=?',
            ['active', $endDate, $nextCharge, $pmId, $autoRenew ? 1 : 0, $receiptNo, $localId]
        );

        // Отмечаем промокод использованным
        if (!empty($sub['promo_code'])) {
            promo_mark_used($sub['promo_code']);
        }
        
                // ── ✅ СИНХРОНИЗАЦИЯ ФЛАГА ПРЕМИУМА ──
        db_exec('UPDATE users SET is_premium = 1 WHERE email = ?', [$email]);
        // ─────────────────────────────────────

        db_insert(
            'INSERT INTO notifications (user_email, title, message, type, created_at) VALUES (?,?,?,?, NOW())',
            [$email, 'Подписка активирована ✨',
             'GigaChefAI Pro активен до ' . date('d.m.Y', strtotime($endDate))
             . ($autoRenew ? '. Автопродление включено.' : ''), 'payment']
        );
    } elseif ($status === 'canceled') {
        db_exec('UPDATE subscriptions SET status=? WHERE id=?', ['canceled', $localId]);
    }

    http_response_code(200);
    echo 'ok';
} catch (Throwable $ex) {
    error_log('[GigaChefAI webhook] ' . $ex->getMessage());
    http_response_code(200);
    echo 'error logged';
}
