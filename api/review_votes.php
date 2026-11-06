<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'error' => 'Требуется вход'], JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$userEmail = current_user()['email'];

/* POST: проголосовать за отзыв или комментарий */
if ($method === 'POST') {
    $type = trim((string)($_POST['type'] ?? ''));  // 'review' | 'comment'
    $id   = (int)($_POST['id'] ?? 0);
    $vote = (int)($_POST['vote'] ?? 0);            // 1 | -1

    if (!in_array($type, ['review', 'comment'], true) || $id <= 0 || !in_array($vote, [1, -1], true)) {
        echo json_encode(['ok' => false, 'error' => 'Неверные параметры'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $targetTable = $type === 'review' ? 'recipe_reviews' : 'recipe_comments';

    /* Проверяем существование записи */
    $row = db_one("SELECT id FROM {$targetTable} WHERE id = ?", [$id]);
    if (!$row) {
        echo json_encode(['ok' => false, 'error' => 'Запись не найдена'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* Ищем существующий голос */
    $existing = db_one(
        "SELECT * FROM chat_votes WHERE target_type = ? AND target_id = ? AND user_email = CONVERT(? USING utf8mb4)",
        [$type, $id, $userEmail]
    );

    $myVote = 0;

    if ($existing && (int)$existing['vote'] === $vote) {
        /* Снять голос (повторный клик) */
        db_exec("DELETE FROM chat_votes WHERE id = ?", [(int)$existing['id']]);
        $myVote = 0;
    } elseif ($existing) {
        /* Переключить голос */
        db_exec("UPDATE chat_votes SET vote = ? WHERE id = ?", [$vote, (int)$existing['id']]);
        $myVote = $vote;
    } else {
        /* Новый голос */
        db_insert(
            "INSERT INTO chat_votes (target_type, target_id, user_email, vote) VALUES (?, ?, CONVERT(? USING utf8mb4), ?)",
            [$type, $id, $userEmail, $vote]
        );
        $myVote = $vote;
    }

    /* Пересчитываем счётчики */
    $likes = db_one(
        "SELECT COUNT(*) AS cnt FROM chat_votes WHERE target_type = ? AND target_id = ? AND vote = 1",
        [$type, $id]
    );
    $dislikes = db_one(
        "SELECT COUNT(*) AS cnt FROM chat_votes WHERE target_type = ? AND target_id = ? AND vote = -1",
        [$type, $id]
    );

    $likesCount    = (int)($likes['cnt'] ?? 0);
    $dislikesCount = (int)($dislikes['cnt'] ?? 0);

    /* Обновляем счётчики в целевой таблице */
    try {
        db_exec(
            "UPDATE {$targetTable} SET likes_count = ?, dislikes_count = ? WHERE id = ?",
            [$likesCount, $dislikesCount, $id]
        );
    } catch (Throwable $e) {
        /* Поля могут отсутствовать — не критично */
    }

    echo json_encode([
        'ok'             => true,
        'my_vote'        => $myVote,
        'likes_count'    => $likesCount,
        'dislikes_count' => $dislikesCount,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Метод не поддерживается'], JSON_UNESCAPED_UNICODE);
exit;
