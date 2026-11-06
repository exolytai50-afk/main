<?php
/**
 * GigaChefAI — анализ изображений через YandexCloud OpenAI-совместимый API (Qwen Vision)
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
/**
 * Отправляет изображение + промпт на Qwen Vision через OpenAI-совместимый API
 */
function yandex_vision_json(string $imageBase64, string $prompt, string $schema): array
{
    $folderId = defined('YANDEX_FOLDER_ID') ? YANDEX_FOLDER_ID : getenv('YANDEX_FOLDER_ID');
    $apiKey = defined('YANDEX_API_KEY') ? YANDEX_API_KEY : getenv('YANDEX_API_KEY');
    
    if (empty($folderId) || empty($apiKey)) {
        throw new RuntimeException('YANDEX_FOLDER_ID или YANDEX_API_KEY не настроены');
    }

    $system = 'Ты — профессиональный эксперт по питанию и кулинарии из России. '
            . 'Отвечай ИСКЛЮЧИТЕЛЬНО НА РУССКОМ ЯЗЫКЕ. '
            . 'Отвечай СТРОГО валидным JSON без markdown-разметки и пояснений. ' . $schema;

    $messages = [];
    
    if (!empty($system)) {
        $messages[] = ['role' => 'system', 'content' => $system];
    }
    
    $userContent = [];
    
    if (!empty($imageBase64)) {
        $mimeType = 'image/jpeg';
        $decoded = @base64_decode($imageBase64, true);
        if ($decoded !== false) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detectedMime = @finfo_buffer($finfo, $decoded);
                if ($detectedMime && str_starts_with($detectedMime, 'image/')) {
                    $mimeType = $detectedMime;
                }
                @finfo_close($finfo);
            }
        }
        
        $userContent[] = [
            'type' => 'image_url',
            'image_url' => ['url' => 'data:' . $mimeType . ';base64,' . $imageBase64]
        ];
    }
    
    $userContent[] = ['type' => 'text', 'text' => $prompt];
    
    $messages[] = ['role' => 'user', 'content' => $userContent];

    $body = [
        'model' => 'gpt://' . $folderId . '/' .
    (defined('QWEN_3_6_MODEL') ? QWEN_3_6_MODEL : 'qwen3.6-35b-a3b/latest'),
        'messages' => $messages,
        'temperature' => 0.3,
        'max_tokens' => 4000,
    ];

    $result = yandex_vision_request($body);
    return $result;
}

/**
 * Низкоуровневый запрос к YandexCloud OpenAI-совместимому API
 */

/**
 * Низкоуровневый запрос к Yandex Cloud OpenAI-совместимому API.
 */
function yandex_vision_request(array $body, int $retries = 2): array
{
    $apiKey = defined('YANDEX_API_KEY')
        ? YANDEX_API_KEY
        : getenv('YANDEX_API_KEY');

    $folderId = defined('YANDEX_FOLDER_ID')
        ? YANDEX_FOLDER_ID
        : getenv('YANDEX_FOLDER_ID');

    if (empty($apiKey) || empty($folderId)) {
        yandex_vision_log('Ошибка конфигурации: отсутствуют ключ или Folder ID');

        throw new RuntimeException('API ключи не настроены');
    }

    $url = 'https://ai.api.cloud.yandex.net/v1/chat/completions';
    $lastErr = '';

    for ($attempt = 0; $attempt <= $retries; $attempt++) {
        $attemptNumber = $attempt + 1;

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 90,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
                'x-folder-id: ' . $folderId,
            ],
            CURLOPT_POSTFIELDS => json_encode(
                $body,
                JSON_UNESCAPED_UNICODE
            ),
        ]);

        
$resp = curl_exec($ch);

$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErrorNumber = curl_errno($ch);
$curlError = curl_error($ch);
$curlInfo = curl_getinfo($ch);

curl_close($ch);

yandex_vision_log('Диагностика cURL', [
    'attempt' => $attempt + 1,
    'http_code' => $httpCode,
    'curl_errno' => $curlErrorNumber,
    'curl_error' => $curlError,
    'response_is_false' => ($resp === false),
    'response_length' => is_string($resp) ? strlen($resp) : null,
    'primary_ip' => $curlInfo['primary_ip'] ?? null,
    'total_time' => $curlInfo['total_time'] ?? null,
    'content_type' => $curlInfo['content_type'] ?? null,
]);


        // Не записываем ключи, заголовки авторизации или изображение.
        yandex_vision_log('Получен ответ API', [
            'attempt' => $attemptNumber,
            'http_code' => $httpCode,
            'curl_error' => $curlError !== '' ? $curlError : null,
            'response_received' => $resp !== false,
            'response_length' => is_string($resp) ? strlen($resp) : 0,
        ]);

        if ($resp !== false && $httpCode === 200) {
            $data = json_decode($resp, true);

            if (!is_array($data)) {
                $lastErr = 'API вернул некорректный JSON';

                yandex_vision_log('Не удалось разобрать JSON ответа', [
                    'attempt' => $attemptNumber,
                    'json_error' => json_last_error_msg(),
                    'response' => mb_substr($resp, 0, 12000),
                ]);
            } else {
                // OpenAI-совместимый формат.
                $text = $data['choices'][0]['message']['content'] ?? null;

                // Запасной формат Foundation Models.
                if (!is_string($text) || trim($text) === '') {
                    $text = $data['result']['alternatives'][0]['message']['text']
                        ?? null;
                }

                // Некоторые API возвращают content в виде массива блоков.
                if (is_array($text)) {
                    $parts = [];

                    foreach ($text as $part) {
                        if (is_string($part)) {
                            $parts[] = $part;
                        } elseif (is_array($part) && isset($part['text'])) {
                            $parts[] = (string) $part['text'];
                        }
                    }

                    $text = implode("\n", $parts);
                }

                if (is_string($text) && trim($text) !== '') {
                    yandex_vision_log('Текст ответа успешно извлечён', [
                        'attempt' => $attemptNumber,
                        'text_length' => mb_strlen($text),
                        'top_level_keys' => array_keys($data),
                    ]);

                    try {
                        return yandex_extract_json($text);
                    } catch (Throwable $e) {
                        yandex_vision_log('Ошибка извлечения JSON из текста модели', [
                            'attempt' => $attemptNumber,
                            'exception' => get_class($e),
                            'message' => $e->getMessage(),
                            'model_text' => mb_substr($text, 0, 6000),
                        ]);

                        throw $e;
                    }
                }

                $lastErr = 'Неизвестный формат ответа API';

                yandex_vision_log('Формат ответа API не распознан', [
                    'attempt' => $attemptNumber,
                    'top_level_keys' => array_keys($data),
                    'response' => mb_substr($resp, 0, 12000),
                ]);
            }
        } else {
            $errorData = is_string($resp)
                ? json_decode($resp, true)
                : null;

            $errorMsg = is_array($errorData)
                ? (
                    $errorData['error']['message']
                    ?? $errorData['message']
                    ?? null
                )
                : null;

            $lastErr = $errorMsg
                ?? ($curlError !== ''
                    ? $curlError
                    : 'HTTP ' . $httpCode);

            yandex_vision_log('Ошибка запроса к API', [
                'attempt' => $attemptNumber,
                'http_code' => $httpCode,
                'curl_error' => $curlError,
                'error' => $lastErr,
                'response' => is_string($resp)
                    ? mb_substr($resp, 0, 6000)
                    : null,
            ]);

            // Не повторяем запрос для 4xx, кроме 429.
            if ($httpCode >= 400 && $httpCode < 500 && $httpCode !== 429) {
                break;
            }
        }

        if ($attempt < $retries) {
            usleep(500000 * ($attempt + 1));
        }
    }

    yandex_vision_log('Запрос завершился окончательной ошибкой', [
        'error' => $lastErr,
        'attempts' => $attempt + 1,
    ]);

    throw new RuntimeException('Vision API: ' . $lastErr);
}


/**
 * Валидирует и нормализует data-URL изображения, возвращает чистый base64
 */
function normalize_image_data_url(string $dataUrl): string
{
    if (!preg_match('#^data:(image/[a-z+]+);base64,(.+)$#i', $dataUrl, $m)) {
        throw new RuntimeException('Некорректный формат изображения. Ожидается data:image/...;base64,...');
    }
    
    $mime   = strtolower($m[1]);
    $base64 = $m[2];

    $allowedTypes = defined('ALLOWED_IMAGE_TYPES') 
        ? explode(',', ALLOWED_IMAGE_TYPES) 
        : ['image/jpeg', 'image/png', 'image/webp'];
    
    if (!in_array($mime, $allowedTypes, true)) {
        throw new RuntimeException('Поддерживаются только: ' . implode(', ', $allowedTypes));
    }
    
    $bin = base64_decode($base64, true);
    if ($bin === false) {
        throw new RuntimeException('Не удалось декодировать изображение');
    }
    
    $maxSize = defined('MAX_IMAGE_SIZE') ? MAX_IMAGE_SIZE : 5 * 1024 * 1024;
    if (strlen($bin) > $maxSize) {
        throw new RuntimeException('Изображение слишком большое (макс. ' . ($maxSize / 1048576) . ' МБ)');
    }
    
    if (@getimagesizefromstring($bin) === false) {
        throw new RuntimeException('Файл не является изображением');
    }
    
    return base64_encode($bin);
}