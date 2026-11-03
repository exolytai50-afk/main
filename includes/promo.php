<?php
/**
 * GigaChefAI — валидация и применение промокодов.
 */
declare(strict_types=1);

/**
 * Проверяет промокод для пользователя и возвращает расчёт скидки.
 * @throws RuntimeException при невалидном коде
 * @return array{promo:array, amount:float, discount:float, original:float}
 */
function promo_apply(string $code, float $amount, string $userEmail): array
{
    $code = mb_strtoupper(trim($code));
    if ($code === '') throw new RuntimeException('Введите промокод');

    $promo = db_one('SELECT * FROM promo_codes WHERE code=? AND is_active=1', [$code]);
    if (!$promo) throw new RuntimeException('Промокод не найден');

    if (!empty($promo['valid_until']) && strtotime($promo['valid_until']) < strtotime(date('Y-m-d'))) {
        throw new RuntimeException('Срок действия промокода истёк');
    }
    if ($promo['max_uses'] !== null && (int)$promo['used_count'] >= (int)$promo['max_uses']) {
        throw new RuntimeException('Лимит использований промокода исчерпан');
    }
    if ((int)$promo['first_only'] === 1) {
        $hasPaid = db_one(
            "SELECT id FROM subscriptions WHERE user_email=? AND status IN ('active','expired','refunded') LIMIT 1",
            [$userEmail]
        );
        if ($hasPaid) throw new RuntimeException('Промокод действует только на первую подписку');
    }

    $discount = $promo['discount_type'] === 'percent'
        ? round($amount * (float)$promo['discount_val'] / 100, 2)
        : min($amount, (float)$promo['discount_val']);

    $final = max(1.0, round($amount - $discount, 2)); // минимум 1₽ для платёжного шлюза

    return [
        'promo'    => $promo,
        'original' => $amount,
        'discount' => round($amount - $final, 2),
        'amount'   => $final,
    ];
}

/** Увеличивает счётчик использований */
function promo_mark_used(string $code): void
{
    db_exec('UPDATE promo_codes SET used_count = used_count + 1 WHERE code=?', [mb_strtoupper(trim($code))]);
}