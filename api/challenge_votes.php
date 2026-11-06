<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    db_exec("CREATE TABLE IF NOT EXISTS `challenge_votes` (
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
    error_log('Challenge votes table error: ' . $e->getMessage());
}

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'error' => 'Требуется вход (401)'], JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$myEmail = current_user()['email'];

// ===== POST: поставить / снять голос =====
if ($method === 'POST') {
    $targetType = trim((string)($_POST['target_type'] ?? ''));
    $targetId = (int)($_POST['target_id'] ?? 0);
    $vote = (int)($_POST['vote'] ?? 0);

    if (!in_array($targetType, ['submission', 'comment'], true)) {
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

    // Проверяем существующий голос
    $existing = db_one(
        'SELECT * FROM challenge_votes WHERE target_type = ? AND target_id = ? AND user_email = CONVERT(? USING utf8mb4)',
        [$targetType, $targetId, $myEmail]
    );

    if ($existing && (int)$existing['vote'] === $vote) {
        // Тот же голос — снимаем (toggle off)
        db_exec(
            'DELETE FROM challenge_votes WHERE id = ?',
            [(int)$existing['id']]
        );
        $myVote = 0;
    } elseif ($existing) {
        // Другой голос — меняем
        db_exec(
            'UPDATE challenge_votes SET vote = ? WHERE id = ?',
            [$vote, (int)$existing['id']]
        );
        $myVote = $vote;
    } else {
        // Нет голоса — ставим
        db_insert(
            'INSERT INTO challenge_votes (target_type, target_id, user_email, vote) VALUES (?, ?, CONVERT(? USING utf8mb4), ?)',
            [$targetType, $targetId, $myEmail, $vote]
        );
        $myVote = $vote;
    }

    // Считаем итог
    $likes = db_one(
        'SELECT COUNT(*) as cnt FROM challenge_votes WHERE target_type = ? AND target_id = ? AND vote = 1',
        [$targetType, $targetId]
    );
    $dislikes = db_one(
        'SELECT COUNT(*) as cnt FROM challenge_votes WHERE target_type = ? AND target_id = ? AND vote = -1',
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

// ===== GET: получить голоса для цели =====
if ($method === 'GET') {
    $targetType = trim((string)($_GET['target_type'] ?? ''));
    $targetId = (int)($_GET['target_id'] ?? 0);

    if (!in_array($targetType, ['submission', 'comment'], true) || $targetId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Неверные параметры'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $likes = db_one(
        'SELECT COUNT(*) as cnt FROM challenge_votes WHERE target_type = ? AND target_id = ? AND vote = 1',
        [$targetType, $targetId]
    );
    $dislikes = db_one(
        'SELECT COUNT(*) as cnt FROM challenge_votes WHERE target_type = ? AND target_id = ? AND vote = -1',
        [$targetType, $targetId]
    );
    $myVoteRow = db_one(
        'SELECT vote FROM challenge_votes WHERE target_type = ? AND target_id = ? AND user_email = CONVERT(? USING utf8mb4)',
        [$targetType, $targetId, $myEmail]
    );

    echo json_encode([
        'ok' => true,
        'like_count' => (int)($likes['cnt'] ?? 0),
        'dislike_count' => (int)($dislikes['cnt'] ?? 0),
        'my_vote' => $myVoteRow ? (int)$myVoteRow['vote'] : 0,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Метод не поддерживается'], JSON_UNESCAPED_UNICODE);
exit;
