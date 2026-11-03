<?php
/**
 * GigaChefAI — YandexART (асинхронная генерация изображений)
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/yandex_ai.php';

/**
 * Запускает генерацию и опрашивает операцию до готовности.
 * @return string base64 (JPEG) без префикса data:.
 */
function yandex_generate_image(string $prompt, int $maxWaitSec = 45): string
{
    $body = [
        'modelUri' => 'art://' . YANDEX_FOLDER_ID . '/yandex-art/latest',
        'generationOptions' => ['seed' => (string) random_int(1, 999999), 'aspectRatio' => ['widthRatio' => '4', 'heightRatio' => '3']],
        'messages' => [['weight' => '1', 'text' => mb_substr($prompt, 0, 500)]],
    ];

    $ch = curl_init(YANDEX_ART_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Api-Key ' . YANDEX_API_KEY, 'x-folder-id: ' . YANDEX_FOLDER_ID],
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $code >= 400) {
        throw new RuntimeException('YandexART: ошибка запуска генерации (' . $code . ')');
    }
    $opId = json_decode($resp, true)['id'] ?? null;
    if (!$opId) throw new RuntimeException('YandexART: не получен id операции');

    $deadline = time() + $maxWaitSec;
    while (time() < $deadline) {
        sleep(3);
        $ch = curl_init(YANDEX_ART_OP_URL . $opId);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Authorization: Api-Key ' . YANDEX_API_KEY],
        ]);
        $op = json_decode(curl_exec($ch) ?: '{}', true);
        curl_close($ch);

        if (!empty($op['done'])) {
            $img = $op['response']['image'] ?? null;
            if ($img) return $img;
            throw new RuntimeException('YandexART: генерация завершилась без изображения');
        }
    }
    throw new RuntimeException('YandexART: превышено время ожидания');
}

/** Сохраняет base64-изображение в uploads/ и возвращает публичный URL */
function save_base64_image(string $base64, string $prefix = 'ai'): string
{
    if (!is_dir(UPLOADS_DIR)) @mkdir(UPLOADS_DIR, 0755, true);
    $name = $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.jpg';
    $path = UPLOADS_DIR . '/' . $name;
    file_put_contents($path, base64_decode($base64));
    return UPLOADS_URL . '/' . $name;
}