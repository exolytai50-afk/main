<?php
/**
 * GigaChefAI — настройки Yandex AI Studio
 */
declare(strict_types=1);

// --- LLM URLs ---
defined('YANDEX_COMPLETION_URL') || define('YANDEX_COMPLETION_URL', 'https://llm.api.cloud.yandex.net/foundationModels/v1/completion');

// --- LLM Models ---
// YandexGPT 4 Lite для текстовых запросов
defined('YANDEX_GPT_4_LITE_MODEL') || define('YANDEX_GPT_4_LITE_MODEL', 'yandexgpt-4-lite/latest');

// --- VLM Models ---
// Qwen3.6-35B для анализа изображений (Vision)
defined('QWEN_3_6_MODEL') || define('QWEN_3_6_MODEL', 'qwen3.6-35b-a3b/latest');

// Лимиты загрузки изображений
defined('MAX_IMAGE_SIZE')      || define('MAX_IMAGE_SIZE', 5 * 1024 * 1024);   // 5 МБ
defined('ALLOWED_IMAGE_TYPES') || define('ALLOWED_IMAGE_TYPES', 'image/jpeg,image/png,image/webp');

// --- Настройки для диабетической адаптации ---
defined('DIABETES_ADAPTATION_TEMPERATURE') || define('DIABETES_ADAPTATION_TEMPERATURE', 0.7);
defined('DIABETES_ADAPTATION_MAX_TOKENS') || define('DIABETES_ADAPTATION_MAX_TOKENS', 2000);

// --- SMTP (опционально) ---
defined('SMTP_HOST')      || define('SMTP_HOST', '');
defined('SMTP_PORT')      || define('SMTP_PORT', 465);
defined('SMTP_USER')      || define('SMTP_USER', '');
defined('SMTP_PASS')      || define('SMTP_PASS', '');
defined('SMTP_FROM')      || define('SMTP_FROM', 'noreply@cookai.local');
defined('SMTP_FROM_NAME') || define('SMTP_FROM_NAME', 'GigaChefAI');

// --- Промпты для AI ---
defined('AI_DIABETES_ADAPT_PROMPT') || define('AI_DIABETES_ADAPT_PROMPT', 
    'Ты — эксперт по питанию при диабете 2 типа. ' .
    'Адаптируй следующий рецепт для людей с диабетом. ' .
    'Замени продукты с высоким гликемическим индексом (ГИ > 70) на альтернативы с низким ГИ (< 55). ' .
    'Уменьши количество сахара и быстрых углеводов. ' .
    'Сохрани вкус и текстуру блюда. ' .
    'Верни ответ в формате JSON с полями: ingredients (массив с name, amount, unit, replacement_note), ' .
    'steps (массив с instruction), notes (строка с рекомендациями).'
);

defined('AI_XE_CALCULATION_PROMPT') || define('AI_XE_CALCULATION_PROMPT',
    'Рассчитай хлебные единицы (ХЕ) и гликемическую нагрузку (ГН) для следующих ингредиентов. ' .
    '1 ХЕ = 10-12 г углеводов. ГН = (ГИ × углеводы в граммах) / 100. ' .
    'Верни JSON с массивом ingredients: name, carbs_per_100g, glycemic_index, xe, gl.'
);