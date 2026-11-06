<?php
/**
 * POST /api/payment_create.php
 * body: { plan, auto_renew?, promo? }
 * → { confirmation_url }
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/yookassa.php';
require_once __DIR__ . '/../includes/promo.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'Только POST'], 405);
if (!is_logged_in()) json_response(['error' => 'Требуется вход (401)'], 401);

$in        = json_input();
$plan      = ($in['plan'] ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly';
$autoRenew = !empty($in['auto_renew']);
$promoCode = trim((string)($in['promo'] ?? ''));

$baseAmount = $plan === 'yearly' ? PLAN_YEARLY_PRICE : PLAN_MONTHLY_PRICE;
$months     = $plan === 'yearly' ? 12 : 1;
$user       = current_user();

$amount   = $baseAmount;
$original = null;
$appliedCode = null;

// Применяем промокод (если указан)
if ($promoCode !== '') {
    try {
        $r = promo_apply($promoCode, $baseAmount, $user['email']);
        $amount      = $r['amount'];
        $original    = $r['original'];
        $appliedCode = mb_strtoupper($promoCode);
    } catch (Throwable $ex) {
        json_response(['error' => $ex->getMessage()], 400);
    }
}

try {
    $localId = db_insert(
        'INSERT INTO subscriptions
         (user_email, status, plan, months, amount, promo_code, original_amount, auto_renew, created_at)
         VALUES (?,?,?,?,?,?,?,?, NOW())',
        [$user['email'], 'pending', $plan, $months, $amount, $appliedCode, $original, $autoRenew ? 1 : 0]
    );

    $returnUrl = SITE_URL . '/payment-return?local_id=' . $localId;
    $desc = 'GigaChefAI Pro — подписка (' . ($plan === 'yearly' ? '1 год' : '1 месяц') . ')';

    $payment = yookassa_create_initial_payment(
        $amount, $desc,
        [
            'local_id'   => (string) $localId,
            'email'      => $user['email'],
            'plan'       => $plan,
            'months'     => (string) $months,
            'auto_renew' => $autoRenew ? '1' : '0',
            'promo'      => $appliedCode ?? '',
        ],
        $returnUrl
    );

    db_exec('UPDATE subscriptions SET payment_id=? WHERE id=?', [$payment['id'], $localId]);

    $confirmationUrl = $payment['confirmation']['confirmation_url'] ?? null;
    if (!$confirmationUrl) throw new RuntimeException('Не получен URL для оплаты');

    json_response(['ok' => true, 'confirmation_url' => $confirmationUrl]);
} catch (Throwable $ex) {
    json_response(['error' => APP_DEBUG ? $ex->getMessage() : 'Ошибка создания платежа'], 500);
}