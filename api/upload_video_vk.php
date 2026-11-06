<?php
require_once __DIR__ . '/../config/config.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Метод не поддерживается'], 405);
}
if (!csrf_check()) {
    json_response(['ok' => false, 'error' => 'Неверный CSRF-токен'], 403);
}
if (!isset($_FILES['video'])) {
    json_response(['ok' => false, 'error' => 'Файл не загружен'], 400);
}

$file = $_FILES['video'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    json_response(['ok' => false, 'error' => 'Ошибка загрузки файла'], 400);
}

$maxSize = 500 * 1024 * 1024; // 500MB
if ($file['size'] > $maxSize) {
    json_response(['ok' => false, 'error' => 'Файл слишком большой. Максимум 500MB'], 400);
}

// 1. Получаем upload_url от VK
$vkToken = VK_COMMUNITY_TOKEN; // Константа из config.php
$groupId = VK_GROUP_ID;       // ID вашего сообщества (например, 12345678)

$params = [
    'access_token' => $vkToken,
    'group_id'     => $groupId,
    'name'         => pathinfo($file['name'], PATHINFO_FILENAME),
    'description'  => 'Видео рецепта с GigaChef',
    'v'            => '5.199'
];

$ch = curl_init('https://api.vk.com/method/video.save');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($params),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$saveResponse = json_decode(curl_exec($ch), true);
curl_close($ch);

if (isset($saveResponse['error'])) {
    error_log('VK video.save error: ' . print_r($saveResponse['error'], true));
    json_response(['ok' => false, 'error' => 'Ошибка VK API: ' . ($saveResponse['error']['error_msg'] ?? 'Неизвестно')], 500);
}

$uploadUrl = $saveResponse['response']['upload_url'];
$ownerId   = $saveResponse['response']['owner_id'];
$videoId   = $saveResponse['response']['video_id'];

// 2. Загружаем файл на сервер VK
$ch = curl_init($uploadUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => ['video_file' => new CURLFile($file['tmp_name'], mime_content_type($file['tmp_name']), $file['name'])],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 300,
]);
$uploadResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    error_log('VK upload error: HTTP ' . $httpCode . ' Response: ' . $uploadResponse);
    json_response(['ok' => false, 'error' => 'Ошибка загрузки на сервер VK'], 500);
}

// VK может вернуть JSON с размером или ошибкой
$uploadResult = json_decode($uploadResponse, true);
if (isset($uploadResult['error'])) {
    json_response(['ok' => false, 'error' => 'VK: ' . $uploadResult['error']], 500);
}

// 3. Возвращаем ID для сохранения в БД
json_response([
    'ok'         => true,
    'video_owner_id' => $ownerId,
    'video_id'       => $videoId,
    'message'        => 'Видео успешно загружено на VK. Оно появится в рецепте после обработки и модерации.'
]);