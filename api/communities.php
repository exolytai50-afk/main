<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

// Функция транслитерации (если не определена)
if (!function_exists('transliterate')) {
    function transliterate($string) {
        $translit = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
            'А'=>'a','Б'=>'b','В'=>'v','Г'=>'g','Д'=>'d','Е'=>'e','Ё'=>'yo','Ж'=>'zh','З'=>'z','И'=>'i','Й'=>'y','К'=>'k','Л'=>'l','М'=>'m','Н'=>'n','О'=>'o','П'=>'p','Р'=>'r','С'=>'s','Т'=>'t','У'=>'u','Ф'=>'f','Х'=>'kh','Ц'=>'ts','Ч'=>'ch','Ш'=>'sh','Щ'=>'sch','Ъ'=>'','Ы'=>'y','Ь'=>'','Э'=>'e','Ю'=>'yu','Я'=>'ya',
        ];
        return strtr($string, $translit);
    }
}

if (!function_exists('generateSlug')) {
    function generateSlug($string) {
        $string = transliterate($string);
        $string = mb_strtolower($string);
        $string = str_replace([' ', '_', '/', '\\', ',', '.', '!', '?', '(', ')', '[', ']', '{', '}', '+', '=', '&', '#', '@', '$', '%', '^', '*', ':', ';', '"', "'", '<', '>', '|', '~', '`'], '-', $string);
        $string = preg_replace('/[^a-z0-9\-]/u', '', $string);
        $string = preg_replace('/-+/', '-', $string);
        return trim($string, '-');
    }
}

// ============================================================
// GET: список сообществ
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $communities = db_all('SELECT * FROM communities ORDER BY members_count DESC LIMIT 50');
    echo json_encode(['communities' => $communities], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// POST: создать, обновить сообщество или обновить обложку
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Требуется вход'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $action = trim((string)($_POST['action'] ?? ''));

    // ------------------------------------------------------------
    // Действие: обновление обложки сообщества
    // ------------------------------------------------------------
    if ($action === 'update_cover') {
        $communityId = (int)($_POST['community_id'] ?? 0);
        if ($communityId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Некорректный ID сообщества'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $community = db_one('SELECT * FROM communities WHERE id = ?', [$communityId]);
        if (!$community) {
            echo json_encode(['ok' => false, 'error' => 'Сообщество не найдено'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($community['author_email'] !== current_user()['email'] && !is_admin()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Вы не владелец'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $coverRemoved = ($_POST['cover_removed'] ?? '0') === '1';

        // --- Удаление обложки: используем стоковую ---
        if ($coverRemoved) {
            if (!empty($community['cover_image'])) {
                $oldPath = ROOT_DIR . $community['cover_image'];
                if (file_exists($oldPath)) @unlink($oldPath);
            }
            db_exec('UPDATE communities SET cover_image = NULL WHERE id = ?', [$communityId]);
            echo json_encode([
                'ok'        => true,
                'cover_url' => null,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // --- Загрузка новой обложки ---
        if (!isset($_FILES['cover_image']) || $_FILES['cover_image']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['ok' => false, 'error' => 'Файл не передан или ошибка загрузки'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $file = $_FILES['cover_image'];

        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['ok' => false, 'error' => 'Файл больше 5 МБ'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Проверка mime-типа
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $allowedMime, true)) {
            echo json_encode(['ok' => false, 'error' => 'Недопустимый формат (только JPG, PNG, WebP, GIF)'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $uploadDir = ROOT_DIR . '/uploads/communities';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!$extension) $extension = 'jpg';

        $fileName = 'cover_' . $communityId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $fullPath = $uploadDir . '/' . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            echo json_encode(['ok' => false, 'error' => 'Не удалось сохранить файл'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Удаляем старую обложку, если она была
        if (!empty($community['cover_image'])) {
            $oldPath = ROOT_DIR . $community['cover_image'];
            if (file_exists($oldPath) && strpos($community['cover_image'], '/uploads/static/') !== 0) {
                @unlink($oldPath);
            }
        }

        $urlPath = '/uploads/communities/' . $fileName;
        db_exec('UPDATE communities SET cover_image = ? WHERE id = ?', [$urlPath, $communityId]);

        echo json_encode([
            'ok'        => true,
            'cover_url' => $urlPath,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ------------------------------------------------------------
    // Основное действие: создание или редактирование сообщества
    // ------------------------------------------------------------
    $communityId = (int)($_POST['community_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $coverRemoved = ($_POST['cover_removed'] ?? '0') === '1';

    if (mb_strlen($name) < 2) {
        echo json_encode(['ok' => false, 'error' => 'Название слишком короткое'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Генерация slug
    $slug = generateSlug($name);

    // Загрузка cover-изображения
    $coverImageUrl = null;
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['cover_image'];
        if ($file['size'] <= 5 * 1024 * 1024) {
            $uploadDir = ROOT_DIR . '/uploads/communities';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            if (!$extension) $extension = 'jpg';

            $fileName = 'cover_' . time() . '_' . uniqid() . '.' . $extension;

            if (move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $fileName)) {
                $coverImageUrl = '/uploads/communities/' . $fileName;
            }
        }
    }

    if ($communityId > 0) {
        // Редактирование
        $community = db_one('SELECT * FROM communities WHERE id = ?', [$communityId]);
        if (!$community) {
            echo json_encode(['ok' => false, 'error' => 'Сообщество не найдено'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Проверяем права
        if ($community['author_email'] !== current_user()['email'] && !is_admin()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Вы не владелец'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $coverImage = $community['cover_image'];

        // Удаляем старое фото
        if ($coverRemoved && $coverImage) {
            $oldPath = ROOT_DIR . $coverImage;
            if (file_exists($oldPath) && strpos($coverImage, '/uploads/static/') !== 0) {
                @unlink($oldPath);
            }
            $coverImage = null;
        }

        // Загружаем новое
        if ($coverImageUrl) {
            if ($coverImage && file_exists(ROOT_DIR . $coverImage) && strpos($coverImage, '/uploads/static/') !== 0) {
                @unlink(ROOT_DIR . $coverImage);
            }
            $coverImage = $coverImageUrl;
        }

        db_exec(
            'UPDATE communities SET name = ?, description = ?, category = ?, cover_image = ? WHERE id = ?',
            [$name, $description, $category, $coverImage, $communityId]
        );

        echo json_encode(['ok' => true, 'slug' => $community['slug']], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        // Создание
        $existing = db_one('SELECT id FROM communities WHERE slug = ?', [$slug]);
        if ($existing) {
            $slug = $slug . '-' . time();
        }

        $id = db_insert(
            'INSERT INTO communities (name, slug, description, category, cover_image, author_email, members_count, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, 1, NOW())',
            [$name, $slug, $description, $category, $coverImageUrl, current_user()['email']]
        );

        echo json_encode(['ok' => true, 'slug' => $slug], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

echo json_encode(['ok' => false, 'error' => 'Метод не поддерживается'], JSON_UNESCAPED_UNICODE);
exit;