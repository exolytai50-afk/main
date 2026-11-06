<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

// Время жизни истории: 7 дней
const STORY_LIFETIME_HOURS = 168;

// Чтение учётных данных Rutube из .env / environment
function rutubeClientId(): string {
    if (defined('RUTUBE_CLIENT_ID')) return RUTUBE_CLIENT_ID;
    return getenv('RUTUBE_CLIENT_ID') ?: '';
}
function rutubeClientSecret(): string {
    if (defined('RUTUBE_CLIENT_SECRET')) return RUTUBE_CLIENT_SECRET;
    return getenv('RUTUBE_CLIENT_SECRET') ?: '';
}
function rutubeApiKey(): string {
    if (defined('RUTUBE_API_KEY')) return RUTUBE_API_KEY;
    return getenv('RUTUBE_API_KEY') ?: '';
}
function siteUrl(): string {
    if (defined('SITE_URL')) return rtrim(SITE_URL, '/');
    return rtrim(getenv('SITE_URL') ?: '', '/');
}

/**
 * Получить access_token через OAuth2 client_credentials.
 * Кэшируется в файл на 23 часа.
 */
function getRutubeAccessToken(): string {
    $clientId = rutubeClientId();
    $clientSecret = rutubeClientSecret();
    if ($clientId === '' || $clientSecret === '') return '';

    $cacheFile = sys_get_temp_dir() . '/rutube_token_' . md5($clientId) . '.json';

    // Проверяем кэш
    if (is_file($cacheFile)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached && isset($cached['token'], $cached['expires_at']) && time() < $cached['expires_at']) {
            return $cached['token'];
        }
    }

    // Запрашиваем новый токен
    $ch = curl_init('https://api.rutube.ru/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]),
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log('Rutube OAuth failed: HTTP ' . $httpCode . ' — ' . $response);
        return '';
    }

    $data = json_decode($response, true);
    if (!$data || !isset($data['access_token'])) {
        error_log('Rutube OAuth: no access_token in response — ' . $response);
        return '';
    }

    $expiresIn = ($data['expires_in'] ?? 86400) - 3600; // запас 1 час
    $cache = [
        'token' => $data['access_token'],
        'expires_at' => time() + $expiresIn,
    ];
    @file_put_contents($cacheFile, json_encode($cache));

    return $data['access_token'];
}

/**
 * Загрузить видео в Rutube по публичному URL.
 * Возвращает video_id (строка) или null при ошибке.
 */
function uploadToRutube(string $videoUrl, string $title): ?string {
    $token = getRutubeAccessToken();
    if ($token === '') return null;

    $ch = curl_init('https://rutube.ru/api/video/');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_POSTFIELDS => http_build_query([
            'url' => $videoUrl,
            'title' => mb_substr($title, 0, 200),
            'description' => '',
            'is_hidden' => 0,
            'category_id' => 13,
        ]),
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log('Rutube upload failed: HTTP ' . $httpCode . ' — ' . $response);
        return null;
    }

    $data = json_decode($response, true);
    if (!$data || !isset($data['video_id'])) {
        error_log('Rutube upload: no video_id — ' . $response);
        return null;
    }

    return (string)$data['video_id'];
}

// ===== Создание/обновление таблиц =====
try {
    db_exec("CREATE TABLE IF NOT EXISTS `stories` (
        `id` int NOT NULL AUTO_INCREMENT,
        `user_email` varchar(255) NOT NULL,
        `image_url` varchar(500) DEFAULT NULL,
        `media_type` varchar(10) DEFAULT 'image',
        `video_url` varchar(500) DEFAULT NULL,
        `rutube_id` varchar(64) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Добавляем недостающие колонки при апгрейде старой таблицы
    $cols = db_all("SHOW COLUMNS FROM stories");
    $colNames = array_column($cols, 'Field');
    if (!in_array('media_type', $colNames)) {
        db_exec("ALTER TABLE stories ADD COLUMN media_type varchar(10) DEFAULT 'image' AFTER image_url");
    }
    if (!in_array('video_url', $colNames)) {
        db_exec("ALTER TABLE stories ADD COLUMN video_url varchar(500) DEFAULT NULL AFTER media_type");
    }
    if (!in_array('rutube_id', $colNames)) {
        db_exec("ALTER TABLE stories ADD COLUMN rutube_id varchar(64) DEFAULT NULL AFTER video_url");
    }

    db_exec("CREATE TABLE IF NOT EXISTS `story_views` (
        `id` int NOT NULL AUTO_INCREMENT,
        `story_id` int NOT NULL,
        `user_email` varchar(255) NOT NULL,
        `viewed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `story_user` (`story_id`, `user_email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Удаляем истёкшие истории (7 дней)
    db_exec("DELETE FROM stories WHERE created_at < DATE_SUB(NOW(), INTERVAL " . STORY_LIFETIME_HOURS . " HOUR)");
    db_exec("DELETE FROM story_views WHERE story_id NOT IN (SELECT id FROM stories)");
} catch (Throwable $e) {
    error_log('Stories table error: ' . $e->getMessage());
}

$method = $_SERVER['REQUEST_METHOD'];

// ===== GET: список историй =====
if ($method === 'GET') {
    $myEmail = is_logged_in() ? current_user()['email'] : '';

$stories = db_all(
    "SELECT s.id, s.user_email, s.image_url, s.media_type, s.video_url, s.rutube_id, s.created_at,
            u.display_name, u.avatar_url, u.gender
     FROM stories s
     JOIN users u ON u.email COLLATE utf8mb4_unicode_ci = s.user_email COLLATE utf8mb4_unicode_ci
     WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL " . STORY_LIFETIME_HOURS . " HOUR)
     ORDER BY s.created_at DESC"
);

    $viewedIds = [];
    if (!empty($myEmail)) {
        $viewedRows = db_all(
            'SELECT story_id FROM story_views WHERE user_email COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci',
            [$myEmail]
        );
        $viewedIds = array_column($viewedRows, 'story_id');
    }

    $result = [];
    foreach ($stories as $s) {
        $result[] = [
    'id' => (int)$s['id'],
    'image_url' => $s['image_url'],
    'media_type' => $s['media_type'] ?? 'image',
    'video_url' => $s['video_url'],
    'rutube_id' => $s['rutube_id'],
    'user_name' => $s['display_name'] ?? 'Пользователь',
    'avatar_url' => $s['avatar_url'] ?? '',
    'user_gender' => $s['gender'] ?? null,   // ← добавили
    'created_at' => $s['created_at'],
    'is_unread' => $myEmail ? !in_array((int)$s['id'], $viewedIds) : false,
    'is_owner' => $myEmail ? ($s['user_email'] === $myEmail) : false,
];
    }

    echo json_encode(['ok' => true, 'stories' => $result], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== POST =====
if ($method === 'POST') {
    // --- Отметка просмотра ---
    if (isset($_GET['id']) && isset($_GET['view'])) {
        if (!is_logged_in()) {
            echo json_encode(['ok' => false, 'error' => 'Требуется вход'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $storyId = (int)$_GET['id'];
        $myEmail = current_user()['email'];
        if ($storyId > 0) {
            try {
                db_exec(
                    'INSERT IGNORE INTO story_views (story_id, user_email) VALUES (?, ? COLLATE utf8mb4_unicode_ci)',
                    [$storyId, $myEmail]
                );
            } catch (Throwable $e) {}
        }
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- Загрузка истории ---
    if (!is_logged_in()) {
        echo json_encode(['ok' => false, 'error' => 'Требуется вход'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $user = current_user();
    $myEmail = $user['email'];

    $file = $_FILES['image'] ?? $_FILES['video'] ?? $_FILES['file'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok' => false, 'error' => 'Файл не получен'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $isImage = strpos($mimeType, 'image/') === 0;
    $isVideo = strpos($mimeType, 'video/') === 0;

    if (!$isImage && !$isVideo) {
        echo json_encode(['ok' => false, 'error' => 'Допускаются только изображения и видео'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $maxSize = $isImage ? (5 * 1024 * 1024) : (50 * 1024 * 1024);
    if ($file['size'] > $maxSize) {
        $limit = $isImage ? '5 МБ' : '50 МБ';
        echo json_encode(['ok' => false, 'error' => "Файл больше {$limit}"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $uploadDir = __DIR__ . '/../uploads/stories/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $ext = $isImage ? 'jpg' : 'mp4';
    $fileName = 'story_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $filePath = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        echo json_encode(['ok' => false, 'error' => 'Не удалось сохранить файл'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $publicPath = '/uploads/stories/' . $fileName;

    if ($isImage) {
        $imageId = db_insert(
            'INSERT INTO stories (user_email, image_url, media_type) VALUES (? COLLATE utf8mb4_unicode_ci, ?, ?)',
            [$myEmail, $publicPath, 'image']
        );
        echo json_encode(['ok' => true, 'id' => $imageId], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ===== Видео =====
    // 1. Сначала сохраняем видео локально в БД (БЕЗ ожидания Rutube)
    $storyId = db_insert(
        'INSERT INTO stories (user_email, image_url, media_type, video_url, rutube_id)
        VALUES (? COLLATE utf8mb4_unicode_ci, ?, ?, ?, ?)',
        [$myEmail, '', 'video', $publicPath, null]
    );

    // 2. Отдаём успешный ответ клиенту сразу
    echo json_encode([
        'ok' => true,
        'id' => $storyId,
        'rutube_id' => null,
        'video_url' => $publicPath,
        'media_type' => 'video'
    ], JSON_UNESCAPED_UNICODE);

    // 3. Закрываем соединение с браузером, чтобы Rutube-загрузка не тормозила ответ
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        // Для Apache / mod_php
        ignore_user_abort(true);
        ob_end_flush();
        flush();
    }

    // 4. Пытаемся загрузить в Rutube ПОСЛЕ отправки ответа (в фоне)
    if (rutubeClientId() !== '' && rutubeClientSecret() !== '' && $baseUrl !== '') {
        try {
            $videoPublicUrl = $baseUrl . $publicPath;
            $title = 'Story от ' . ($user['display_name'] ?? $myEmail);
            
            // Обернули в try-catch, чтобы даже фатальная ошибка не уронила скрипт
            $rutubeId = uploadToRutube($videoPublicUrl, $title);
            
            if ($rutubeId) {
                db_exec('UPDATE stories SET rutube_id = ? WHERE id = ?', [$rutubeId, $storyId]);
            }
        } catch (Throwable $e) {
            error_log('Rutube async upload error: ' . $e->getMessage());
        }
    }
    exit;
}

// ===== DELETE =====
if ($method === 'DELETE') {
    if (!is_logged_in()) {
        echo json_encode(['ok' => false, 'error' => 'Требуется вход'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $storyId = (int)($_GET['id'] ?? 0);
    $myEmail = current_user()['email'];
    if ($storyId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Не указан ID'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $story = db_one('SELECT * FROM stories WHERE id = ?', [$storyId]);
    if (!$story) {
        echo json_encode(['ok' => false, 'error' => 'История не найдена'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($story['user_email'] !== $myEmail) {
        echo json_encode(['ok' => false, 'error' => 'Нет прав'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // Удаление физических файлов
    if (!empty($story['image_url'])) {
        $filePath = realpath(__DIR__ . '/..' . $story['image_url']);
        if ($filePath && is_file($filePath)) {
            @unlink($filePath);
        }
    }
    if (!empty($story['video_url'])) {
        $filePath = realpath(__DIR__ . '/..' . $story['video_url']);
        if ($filePath && is_file($filePath)) {
            @unlink($filePath);
        }
    }
    db_exec('DELETE FROM stories WHERE id = ?', [$storyId]);
    db_exec('DELETE FROM story_views WHERE story_id = ?', [$storyId]);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Метод не поддерживается'], JSON_UNESCAPED_UNICODE);
exit;
