<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    db_exec("CREATE TABLE IF NOT EXISTS `chat_votes` (
        `id` int NOT NULL AUTO_INCREMENT,
        `target_type` varchar(20) NOT NULL,
        `target_id` int NOT NULL,
        `user_email` varchar(255) NOT NULL,
        `vote` tinyint NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `vote_unique` (`target_type`, `target_id`, `user_email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {
    error_log('Chat votes table error: ' . $e->getMessage());
}

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'error' => 'Требуется вход'], JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$myEmail = current_user()['email'];

// ===== POST: поставить / снять голос =====
if ($method === 'POST') {
    $targetType = trim((string)($_POST['target_type'] ?? ''));
    $targetId = (int)($_POST['target_id'] ?? 0);
    $vote = (int)($_POST['vote'] ?? 0);

    if (!in_array($targetType, ['chat_message', 'group_message'], true)) {
        echo json_encode(['ok' => false, 'error' => 'Неверный тип цели'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($targetId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Неверный ID'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!in_array($vote, [1, -1], true)) {
        echo json_encode(['ok' => false, 'error' => 'Голос должен быть 1 или -1'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $existing = db_one(
        'SELECT * FROM chat_votes WHERE target_type = ? AND target_id = ? AND user_email = CONVERT(? USING utf8mb4)',
        [$targetType, $targetId, $myEmail]
    );

    if ($existing && (int)$existing['vote'] === $vote) {
        db_exec('DELETE FROM chat_votes WHERE id = ?', [(int)$existing['id']]);
        $myVote = 0;
    } elseif ($existing) {
        db_exec('UPDATE chat_votes SET vote = ? WHERE id = ?', [$vote, (int)$existing['id']]);
        $myVote = $vote;
    } else {
        db_insert(
            'INSERT INTO chat_votes (target_type, target_id, user_email, vote) VALUES (?, ?, CONVERT(? USING utf8mb4), ?)',
            [$targetType, $targetId, $myEmail, $vote]
        );
        $myVote = $vote;
    }

    $likes = db_one(
        'SELECT COUNT(*) as cnt FROM chat_votes WHERE target_type = ? AND target_id = ? AND vote = 1',
        [$targetType, $targetId]
    );
    $dislikes = db_one(
        'SELECT COUNT(*) as cnt FROM chat_votes WHERE target_type = ? AND target_id = ? AND vote = -1',
        [$targetType, $targetId]
    );

    echo json_encode([
        'ok' => true,
        'my_vote' => $myVote,
        'like_count' => (int)($likes['cnt'] ?? 0),
        'dislike_count' => (int)($dislikes['cnt'] ?? 0),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== GET: получить голоса для списка =====
if ($method === 'GET') {
    $targetType = trim((string)($_GET['target_type'] ?? ''));
    $targetIds = trim((string)($_GET['target_ids'] ?? ''));

    if (!in_array($targetType, ['chat_message', 'group_message'], true) || $targetIds === '') {
        echo json_encode(['ok' => false, 'error' => 'Неверные параметры'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $ids = array_filter(array_map('intval', explode(',', $targetIds)));
    if (empty($ids)) {
        echo json_encode(['ok' => true, 'votes' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $rows = db_all(
        "SELECT target_id, vote, user_email FROM chat_votes WHERE target_type = ? AND target_id IN ({$placeholders})",
        array_merge([$targetType], $ids)
    );

    $result = [];
    foreach ($ids as $tid) {
        $result[$tid] = ['like_count' => 0, 'dislike_count' => 0, 'my_vote' => 0];
    }
    foreach ($rows as $r) {
        $tid = (int)$r['target_id'];
        if (!isset($result[$tid])) continue;
        if ((int)$r['vote'] === 1) $result[$tid]['like_count']++;
        if ((int)$r['vote'] === -1) $result[$tid]['dislike_count']++;
        if ($r['user_email'] === $myEmail) $result[$tid]['my_vote'] = (int)$r['vote'];
    }

    echo json_encode(['ok' => true, 'votes' => $result], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Метод не поддерживается'], JSON_UNESCAPED_UNICODE);
exit;
