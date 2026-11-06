<?php
require_once __DIR__ . '/../config/config.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
$userEmail = $user['email'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = db_all(
        'SELECT pack_id FROM user_chat_sticker_packs
         WHERE user_email = ? AND group_id IS NULL AND peer_email IS NULL
         ORDER BY sort_order ASC, id ASC',
        [$userEmail]
    );
    $ids = array_map(fn($r) => (int)$r['pack_id'], $rows);
    echo json_encode(['ok' => true, 'ids' => $ids]);
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $ids = $data['ids'] ?? [];
    if (!is_array($ids)) {
        echo json_encode(['ok' => false, 'error' => 'Invalid data']);
        exit;
    }
    $ids = array_values(array_unique(array_map('intval', $ids)));

    try {
        db_exec(
            'DELETE FROM user_chat_sticker_packs
             WHERE user_email = ? AND group_id IS NULL AND peer_email IS NULL',
            [$userEmail]
        );
        foreach ($ids as $idx => $packId) {
            db_insert(
                'INSERT INTO user_chat_sticker_packs (user_email, group_id, peer_email, pack_id, sort_order)
                 VALUES (?, NULL, NULL, ?, ?)',
                [$userEmail, $packId, $idx]
            );
        }
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'DB error: ' . $e->getMessage()]);
        exit;
    }

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);