<?php
/**
 * API: Логирование согласия на cookie
 * POST /api/cookie_consent.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

// Только POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// CSRF-проверка (если функция доступна)
if (function_exists('csrf_verify')) {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!csrf_verify($token)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

// Получаем данные
$input = json_input();
$policyVersion = trim((string)($input['policy_version'] ?? ''));
$accepted = (int)($input['accepted'] ?? 0);

if ($policyVersion === '' || !in_array($accepted, [0, 1], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid data']);
    exit;
}

// Собираем метаданные
$userEmail = null;
if (function_exists('current_user')) {
    $u = current_user();
    if ($u && !empty($u['email'])) {
        $userEmail = $u['email'];
    }
}

$sessionId = session_id() ?: null;
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
// Берём первый IP, если их несколько
if (strpos($ip, ',') !== false) {
    $ip = trim(explode(',', $ip)[0]);
}
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

// Записываем в БД
try {
    db_exec(
        "INSERT INTO cookie_consents 
            (user_email, session_id, ip_address, user_agent, policy_version, accepted, accepted_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())",
        [$userEmail, $sessionId, $ip, $userAgent, $policyVersion, $accepted]
    );

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('cookie_consent.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Internal error']);
}