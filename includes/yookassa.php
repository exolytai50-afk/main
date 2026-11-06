<?php
/**
 * GigaChefAI — клиент ЮKassa (REST API v3, без SDK).
 * Поддержка: разовые платежи, рекуррентные списания, возвраты.
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/payment.php';

/**
 * ПЕРВЫЙ платёж с сохранением способа оплаты для будущих автосписаний.
 */
function yookassa_create_initial_payment(float $amount, string $description, array $metadata, string $returnUrl): array
{
    $body = [
        'amount'              => ['value' => yk_money($amount), 'currency' => 'RUB'],
        'capture'             => true,
        'save_payment_method' => true,
        'confirmation'        => ['type' => 'redirect', 'return_url' => $returnUrl],
        'description'         => mb_substr($description, 0, 128),
        'metadata'            => $metadata,
        'receipt'             => yk_receipt($amount, $description, $metadata['email'] ?? 'user@cookai.ru'),
    ];
    return yk_request('POST', YOOKASSA_API_URL, $body, true);
}

/**
 * Автосписание по сохранённому payment_method_id (без участия пользователя).
 */
function yookassa_charge_recurring(string $paymentMethodId, float $amount, string $description, array $metadata): array
{
    $body = [
        'amount'            => ['value' => yk_money($amount), 'currency' => 'RUB'],
        'capture'           => true,
        'payment_method_id' => $paymentMethodId,
        'description'       => mb_substr($description, 0, 128),
        'metadata'          => $metadata,
        'receipt'           => yk_receipt($amount, $description, $metadata['email'] ?? 'user@cookai.ru'),
    ];
    return yk_request('POST', YOOKASSA_API_URL, $body, true);
}

/** Получить статус платежа */
function yookassa_get_payment(string $paymentId): array
{
    return yk_request('GET', YOOKASSA_API_URL . '/' . rawurlencode($paymentId), null, false);
}

/**
 * Возврат платежа (полный или частичный).
 */
function yookassa_refund(string $paymentId, float $amount, string $reason = ''): array
{
    $body = [
        'payment_id' => $paymentId,
        'amount'     => ['value' => yk_money($amount), 'currency' => 'RUB'],
    ];
    if ($reason !== '') $body['description'] = mb_substr($reason, 0, 128);
    return yk_request('POST', YOOKASSA_REFUND_URL, $body, true);
}

// ---------- helpers ----------

function yk_money(float $amount): string
{
    return number_format($amount, 2, '.', '');
}

function yk_receipt(float $amount, string $description, string $email): array
{
    return [
        'customer' => ['email' => $email],
        'items' => [[
            'description'     => mb_substr($description, 0, 128),
            'quantity'        => '1.00',
            'amount'          => ['value' => yk_money($amount), 'currency' => 'RUB'],
            'vat_code'        => 1,
            'payment_mode'    => 'full_payment',
            'payment_subject' => 'service',
        ]],
    ];
}

function yk_request(string $method, string $url, ?array $body, bool $withIdempotence): array
{
    $headers = [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode(YOOKASSA_SHOP_ID . ':' . YOOKASSA_SECRET_KEY),
    ];
    if ($withIdempotence) {
        $headers[] = 'Idempotence-Key: ' . bin2hex(random_bytes(16));
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }

    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        throw new RuntimeException('ЮKassa: ошибка соединения: ' . $err);
    }
    $data = json_decode($resp, true) ?: [];
    if ($code >= 400) {
        $msg = $data['description'] ?? ($data['type'] ?? 'Неизвестная ошибка');
        throw new RuntimeException('ЮKassa (' . $code . '): ' . $msg);
    }
    return $data;
}