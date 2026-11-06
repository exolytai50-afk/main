<?php
/**
 * GigaChefAI / GigaChef — работа с YandexGPT.
 *
 * Содержит:
 *  - низкоуровневые обёртки (yandex_gpt_request, yandex_gpt_json, yandex_gpt_chat, yandex_gpt_text)
 *  - высокоуровневые для классификации рецептов (classifyRecipeWithAI)
 *
 * Требует: config/config.php уже подключён (константы YANDEX_*, AI_CLASSIFY_*, AI_RECIPE_CLASSIFY_PROMPT).
 */
declare(strict_types=1);

if (!defined('YANDEX_COMPLETION_URL')) {
    // На случай, если файл подключат раньше config.php
    require_once __DIR__ . '/../config/config.php';
}

// ============================================================
//  НИЗКОУРОВНЕВЫЙ ЗАПРОС К YANDEXGPT
// ============================================================

if (!function_exists('yandex_gpt_request')) {
    /**
     * Отправляет готовый payload в YandexGPT и возвращает текст ответа.
     * Бросает RuntimeException при исчерпании retries.
     */
    function yandex_gpt_request(array $body, int $retries = 2): string
    {
        $apiKey   = defined('YANDEX_API_KEY')   ? YANDEX_API_KEY   : getenv('YANDEX_API_KEY');
        $folderId = defined('YANDEX_FOLDER_ID') ? YANDEX_FOLDER_ID : getenv('YANDEX_FOLDER_ID');

        if (empty($apiKey) || empty($folderId)) {
            throw new RuntimeException('YANDEX_API_KEY или YANDEX_FOLDER_ID не настроены');
        }

        $url = defined('YANDEX_COMPLETION_URL')
            ? YANDEX_COMPLETION_URL
            : 'https://llm.api.cloud.yandex.net/foundationModels/v1/completion';

        $lastErr = '';

        for ($attempt = 0; $attempt <= $retries; $attempt++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_TIMEOUT        => 90,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Api-Key ' . $apiKey,
                    'x-folder-id: ' . $folderId,
                ],
                CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
            ]);

            $resp     = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            error_log("[YandexGPT] HTTP {$httpCode}" . ($curlErr ? " curl={$curlErr}" : ''));

            if ($resp !== false && $httpCode === 200) {
                $data = json_decode($resp, true);

                // Foundation Models формат
                if (isset($data['result']['alternatives'][0]['message']['text'])) {
                    return (string)$data['result']['alternatives'][0]['message']['text'];
                }
                // OpenAI-совместимый
                if (isset($data['choices'][0]['message']['content'])) {
                    return (string)$data['choices'][0]['message']['content'];
                }
                $lastErr = 'Неизвестный формат ответа API';

yandex_vision_log(
    'API вернул HTTP 200, но формат ответа не распознан',
    [
        'top_level_keys' => array_keys($data),
        'response' => $data
    ]
);
            } else {
                $errorData = json_decode((string)$resp, true);
                $errorMsg  = $errorData['error']['message']
                          ?? $errorData['message']
                          ?? "HTTP {$httpCode}";
                $lastErr = $errorMsg;

                // 4xx (кроме 429) — не ретраим
                if ($httpCode >= 400 && $httpCode < 500 && $httpCode !== 429) {
                    break;
                }
            }

            if ($attempt < $retries) {
                usleep(500000 * ($attempt + 1));
            }
        }

        throw new RuntimeException('YandexGPT API: ' . $lastErr);
    }
}

// ============================================================
//  ИЗВЛЕЧЕНИЕ JSON ИЗ ОТВЕТА
// ============================================================

if (!function_exists('yandex_fix_json')) {
    /**
     * Чинит типичные огрехи модели в JSON:
     *  - висячие запятые
     *  - // комментарии
     *  - BOM
     */
    function yandex_fix_json(string $json): string
    {
        $json = preg_replace('/^\xEF\xBB\xBF/', '', $json);
        $json = preg_replace('/\/\/.*$/m', '', $json);
        $json = preg_replace('/,\s*([}\]])/', '$1', $json);
        return $json;
    }
}

if (!function_exists('yandex_extract_json')) {
    /**
     * Пытается вытащить JSON-объект (или массив) из произвольного текста.
     * Возвращает массив или null.
     */
    function yandex_extract_json(string $text): ?array
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*\n?/m', '', $text);
        $text = preg_replace('/\n?```$/m', '', $text);
        $text = trim($text);

        // 1. Весь текст как JSON
        $data = json_decode($text, true);
        if (is_array($data) && !empty($data)) {
            return $data;
        }

        // 2. Первый {...}
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $json = substr($text, $start, $end - $start + 1);
            $json = yandex_fix_json($json);
            $data = json_decode($json, true);
            if (is_array($data) && !empty($data)) {
                return $data;
            }
        }

        // 3. Первый [...]
        $start = strpos($text, '[');
        $end   = strrpos($text, ']');
        if ($start !== false && $end !== false && $end > $start) {
            $json = substr($text, $start, $end - $start + 1);
            $data = json_decode($json, true);
            if (is_array($data)) {
                return ['recipes' => $data];
            }
        }

        return null;
    }
}

// ============================================================
//  ВЫСОКОУРОВНЕВЫЕ ФУНКЦИИ (используются в проекте)
// ============================================================

if (!function_exists('ai_text_request')) {
    /**
     * Универсальный текстовый запрос через Yandex Cloud AI Studio:
     *  - DeepSeek V4.1 Flash (modelUri = gpt://folder/deepseek-v4.1-flash/latest)
     *  - YandexGPT (fallback)
     *
     * @param array $messages [{role, content}, ...]
     */
    function ai_text_request(array $messages, float $temperature = 0.7, int $maxTokens = 4000, int $retries = 2): string
    {
        $provider = defined('AI_TEXT_PROVIDER') ? AI_TEXT_PROVIDER : 'yandex';

        // Выбор модели: DeepSeek или YandexGPT
        if ($provider === 'deepseek' && defined('DEEPSEEK_MODEL') && DEEPSEEK_MODEL !== '') {
            $model = DEEPSEEK_MODEL;
        } else {
            $model = defined('YANDEX_GPT_4_LITE_MODEL') ? YANDEX_GPT_4_LITE_MODEL : 'yandexgpt/latest';
        }

        // Приводим messages к формату Yandex: {role, text}
        $yandexMessages = array_map(function ($m) {
            return [
                'role' => $m['role'] ?? 'user',
                'text' => $m['content'] ?? $m['text'] ?? '',
            ];
        }, $messages);

        $folderId = defined('YANDEX_FOLDER_ID') ? YANDEX_FOLDER_ID : getenv('YANDEX_FOLDER_ID');

        $body = [
            'modelUri' => 'gpt://' . $folderId . '/' . $model,
            'completionOptions' => [
                'stream'      => false,
                'temperature' => $temperature,
                'maxTokens'   => (string)$maxTokens,
            ],
            'messages' => $yandexMessages,
        ];

        return yandex_gpt_request($body, $retries);
    }
}

if (!function_exists('yandex_gpt_chat')) {
    /**
     * Чат с историей (последние 8 сообщений).
     */
    function yandex_gpt_chat(string $system, string $message, array $history = [],
                             float $temperature = 0.7, int $maxTokens = 4000): string
    {
        $messages = [['role' => 'system', 'content' => $system]];

        foreach (array_slice($history, -8) as $msg) {
            $text = $msg['content'] ?? $msg['text'] ?? '';
            if (!empty($msg['role']) && $text !== '') {
                $messages[] = ['role' => $msg['role'], 'content' => $text];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        return ai_text_request($messages, $temperature, $maxTokens, 2);
    }
}

if (!function_exists('yandex_gpt_text')) {
    /**
     * Простой запрос «промпт → текст».
     */
    function yandex_gpt_text(string $prompt, int $maxTokens = 4000): string
    {
        $messages = [
            ['role' => 'system', 'content' => 'Ты — полезный ассистент. Отвечай на русском языке.'],
            ['role' => 'user',   'content' => $prompt],
        ];

        return ai_text_request($messages, 0.7, $maxTokens, 2);
    }
}

if (!function_exists('yandex_gpt_json')) {
    /**
     * Отправляет промпт + схему, ждёт JSON-ответ.
     * Использует ai_text_request() (DeepSeek через Yandex Cloud или YandexGPT-fallback).
     */
    function yandex_gpt_json(
        string $prompt,
        string $schema,
        int $maxRetries = 1,
        ?int $maxTokens = null
    ): array
    {
        $system = 'Ты — помощник, который отвечает ТОЛЬКО валидным JSON. '
            . 'Не используй markdown-разметку, не добавляй пояснения до или после JSON. '
            . 'Отвечай ТОЛЬКО JSON-объектом.';

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => "СТРУКТУРА ОТВЕТА:\n" . $schema . "\n\n" . $prompt],
        ];

        $lastError = '';

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            try {
                $text = ai_text_request(
                    $messages,
                    0.1,
                    defined('AI_CLASSIFY_MAX_TOKENS') ? (int)AI_CLASSIFY_MAX_TOKENS : 4000,
                    2
                );
                
                // --- ВРЕМЕННОЕ СОХРАНЕНИЕ СЫРОГО ОТВЕТА ---
                @file_put_contents(__DIR__ . '/../logs/ai_raw.log', date('Y-m-d H:i:s') . "\n" . $text . "\n\n---\n\n", FILE_APPEND);
                // ------------------------------------------

                $clean = preg_replace('/^```(?:json)?\s*\n?/m', '', $text);
                $clean = preg_replace('/\n?```\s*$/m', '', $clean);
                $clean = trim($clean);

                $json = yandex_extract_json($clean);
                if ($json !== null) {
                    return $json;
                }
                
                // --- ВРЕМЕННАЯ ОТЛАДКА ---
                file_put_contents(__DIR__ . '/../logs/ai_raw.log', date('Y-m-d H:i:s') . "\n" . $text . "\n\n", FILE_APPEND);
                // --------------------------                

                // --- ВРЕМЕННАЯ ОТЛАДКА ---
                $lastError = 'Модель не вернула валидный JSON (попытка ' . ($attempt + 1) . ')';
                // --------------------------

                if ($attempt < $maxRetries - 1) {
                    $messages[] = ['role' => 'assistant', 'content' => $text];
                    $messages[] = [
                        'role' => 'user',
                        'content' => 'Это не валидный JSON. Верни ответ СТРОГО в формате JSON без markdown и без текста до или после. Следуй СТРУКТУРЕ, которую я указал в первом сообщении.',
                    ];
                }
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
                if ($attempt === $maxRetries - 1) {
                    throw $e;
                }
            }

            if ($attempt < $maxRetries - 1) {
                usleep(500000 * ($attempt + 1));
            }
        }

        throw new RuntimeException($lastError ?: 'Не удалось получить валидный JSON');
    }
}

// ============================================================
//  КЛАССИФИКАЦИЯ РЕЦЕПТА (категория + теги)
// ============================================================

if (!function_exists('classifyRecipeWithAI')) {
    function classifyRecipeWithAI(array $recipe): ?array
    {
        require_once __DIR__ . '/recipe_classifier.php';

        try {
            $r = classify_recipe($recipe);
            if (empty($r['category_slug'])) return null;

            return [
                'category_slug'       => $r['category_slug'],
                'category_confidence' => $r['confidence'],
                'tags'                => $r['tag_slugs'],
            ];
        } catch (Throwable $e) {
            error_log('[classifyRecipeWithAI] ' . $e->getMessage());
            return null;
        }
    }
}
/**
 * Отдельный лог для Yandex Vision API.
 */
function yandex_vision_log(string $message, array $context = []): void
{
    $logDir = dirname(__DIR__) . '/logs';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/yandex_vision.log';

    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message;

    if (!empty($context)) {
        $entry .= "\n" . json_encode(
            $context,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_PRETTY_PRINT
        );
    }

    file_put_contents(
        $logFile,
        $entry . "\n\n",
        FILE_APPEND | LOCK_EX
    );
}