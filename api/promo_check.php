<?php
/**
 * POST /api/promo_check.php  body: { code, plan }
 * → { discount, amount, original, label }
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/promo.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'Только POST'], 405);
if (!is_logged_in()) json_response(['error' => 'Требуется вход (401)'], 401);

$in     = json_input();
$code   = (string)($in['code'] ?? '');
$plan   = ($in['plan'] ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly';
$amount = $plan === 'yearly' ? PLAN_YEARLY_PRICE : PLAN_MONTHLY_PRICE;

try {
    $r = promo_apply($code, $amount, current_user()['email']);
    $p = $r['promo'];
    $label = $p['discount_type'] === 'percent'
        ? '−' . rtrim(rtrim(number_format((float)$p['discount_val'], 2, '.', ''), '0'), '.') . '%'
        : '−' . number_format((float)$p['discount_val'], 0) . '₽';

    json_response([
        'ok'       => true,
        'original' => $r['original'],
        'discount' => $r['discount'],
        'amount'   => $r['amount'],
        'label'    => $label,
    ]);
} catch (Throwable $ex) {
    json_response(['error' => $ex->getMessage()], 400);
}