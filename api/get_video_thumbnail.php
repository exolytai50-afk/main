<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

$videoUrl = $_GET['url'] ?? '';

if (empty($videoUrl)) {
    json_response(['ok' => false, 'error' => 'URL не указан'], 400);
}

// Извлекаем ID видео из Rutube URL
if (preg_match('/rutube\.ru\/(?:video|shorts|live)\/([a-zA-Z0-9_-]+)/i', $videoUrl, $matches)) {
    $videoId = $matches[1];
    
    // Формируем URL превью
    $thumbnailUrl = "https://rutube.ru/api/video/{$videoId}/thumbnail/";
    
    json_response([
        'ok' => true,
        'thumbnail_url' => $thumbnailUrl,
        'video_id' => $videoId
    ]);
} else {
    json_response(['ok' => false, 'error' => 'Неверный формат URL Rutube'], 400);
}