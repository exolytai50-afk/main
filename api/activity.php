<?php
/**
 * GET  /api/activity.php?filter=all&page=1 — лента активностей
 * POST /api/activity.php — создать активность (внутреннее)
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

if (!is_logged_in()) {
    json_response(['error' => 'Требуется вход (401)'], 401);
}

$userEmail = current_user()['email'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $filter = trim($_GET['filter'] ?? 'all');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        
        // Получаем ленту
        $feed = get_feed($userEmail, $filter, $page, $perPage);
        
        // Общее количество для пагинации
        $total = db_one("SELECT COUNT(*) as cnt FROM activity_feed WHERE visibility = 'public'");
        
        json_response([
            'feed' => $feed,
            'page' => $page,
            'total' => (int)($total['cnt'] ?? 0),
            'pages' => ceil(($total['cnt'] ?? 0) / $perPage)
        ]);
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Только для внутреннего использования
        if (!is_admin()) {
            json_response(['error' => 'Доступ запрещён'], 403);
        }
        
        $in = json_input();
        $type = trim((string)($in['type'] ?? ''));
        $message = trim((string)($in['message'] ?? ''));
        
        if (empty($type)) {
            json_response(['error' => 'Не указан тип'], 400);
        }
        
        add_activity(
            $userEmail,
            $type,
            (int)($in['reference_id'] ?? null),
            $in['reference_type'] ?? null,
            $message ?: null,
            $in['visibility'] ?? 'public'
        );
        
        json_response(['ok' => true]);
    }
    
    json_response(['error' => 'Метод не поддерживается'], 405);
    
} catch (Throwable $ex) {
    json_response(['error' => APP_DEBUG ? $ex->getMessage() : 'Ошибка сервера'], 500);
}