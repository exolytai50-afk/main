<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/image_storage.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Требуется вход']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'error' => 'Файл не загружен']);
    exit;
}

$file = $_FILES['file'];

/* Лимит исходника: 10 МБ */
if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'error' => 'Файл больше 10 МБ']);
    exit;
}

$mime = mime_content_type($file['tmp_name']);
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'];
if (!in_array($mime, $allowed, true)) {
    echo json_encode(['ok' => false, 'error' => 'Недопустимый формат']);
    exit;
}

$folder = preg_replace('/[^a-z0-9_\-]/i', '', $_POST['folder'] ?? 'misc');
$uploadDir = ROOT_DIR . '/uploads/' . $folder . '/' . date('Y/m');
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$ext = match ($mime) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
    'image/avif' => 'avif',
    default      => 'bin',
};

$basename = bin2hex(random_bytes(8));
$originalPath = $uploadDir . '/' . $basename . '.' . $ext;

if (!move_uploaded_file($file['tmp_name'], $originalPath)) {
    echo json_encode(['ok' => false, 'error' => 'Не удалось сохранить файл']);
    exit;
}

/* ─── Сжатие и ресайз ─── */
$sizes = [
    'thumb'    => 300,   // карточки
    'medium'   => 800,   // детальные страницы
    'large'    => 1600,  // hero
];

$variants = [];
foreach ($sizes as $name => $maxWidth) {
    $out = $uploadDir . '/' . $basename . '-' . $name . '.webp';
    if (resize_to_webp($originalPath, $out, $maxWidth, 82)) {
        $variants[$name] = '/uploads/' . $folder . '/' . date('Y/m') . '/' . $basename . '-' . $name . '.webp';
    }
}

/* Оригинал можно удалить, если не нужен */
// @unlink($originalPath);

echo json_encode([
    'ok'       => true,
    'original' => '/uploads/' . $folder . '/' . date('Y/m') . '/' . $basename . '.' . $ext,
    'variants' => $variants,
], JSON_UNESCAPED_UNICODE);