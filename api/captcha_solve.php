<?php
/**
 * POST /api/captcha/solve
 * Body (JSON): { "nonce": "...", "tiles": [0,1,2,3] }
 * Ответ:      { "ok": true, "token": "..." }  или  { "ok": false }
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    $body = $_POST;
}

$nonce = (string)($body['nonce'] ?? '');
$tiles = $body['tiles'] ?? [];

if (!preg_match('/^[a-f0-9]{32}$/', $nonce)) {
    echo json_encode(['ok' => false, 'error' => 'bad_nonce']);
    exit;
}
if (!is_array($tiles)) {
    echo json_encode(['ok' => false, 'error' => 'bad_tiles']);
    exit;
}

if (!captcha_mark_solved($nonce, $tiles)) {
    echo json_encode(['ok' => false, 'error' => 'not_solved']);
    exit;
}

$token = captcha_issue_token($nonce);
if (!$token) {
    echo json_encode(['ok' => false, 'error' => 'token_failed']);
    exit;
}

echo json_encode(['ok' => true, 'token' => $token]);