<?php
/**
 * GET /api/community_check.php?community_id=123
 * Проверяет, состоит ли пользователь в сообществе
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

$communityId = (int)($_GET['community_id'] ?? 0);

if ($communityId <= 0) {
    json_response(['joined' => false, 'members_count' => 0]);
}

$community = db_one('SELECT members_count FROM communities WHERE id = ?', [$communityId]);
$membersCount = (int)($community['members_count'] ?? 0);

if (!is_logged_in()) {
    json_response(['joined' => false, 'members_count' => $membersCount]);
}

$member = db_one(
    'SELECT id FROM community_members WHERE community_id = ? AND user_email = ?',
    [$communityId, current_user()['email']]
);

json_response([
    'joined' => $member !== null,
    'members_count' => $membersCount
]);