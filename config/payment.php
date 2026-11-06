<?php
/**
 * GigaChefAI — конфигурация платёжного шлюза ЮKassa.
 */
declare(strict_types=1);

// --- Учётные данные ЮKassa (ЛК → Настройки → API) ---
define('YOOKASSA_SHOP_ID', 'ВАШ_SHOP_ID');
define('YOOKASSA_SECRET_KEY', 'ВАШ_СЕКРЕТНЫЙ_КЛЮЧ');   // test_... или live_...
define('YOOKASSA_API_URL', 'https://api.yookassa.ru/v3/payments');
define('YOOKASSA_REFUND_URL', 'https://api.yookassa.ru/v3/refunds');

define('PAYMENT_LIVE_MODE', false);

// --- Тарифы (рубли) ---
define('PLAN_MONTHLY_PRICE', 299.00);
define('PLAN_YEARLY_PRICE', 2990.00);

// --- Базовый URL сайта ---
define('SITE_URL', 'https://ваш-домен.ру');

// --- Автопродление ---
define('MAX_RENEW_ATTEMPTS', 3);        // попыток списания
define('RENEW_NOTIFY_DAYS', 3);         // за сколько дней предупреждать письмом
define('REFUND_WINDOW_DAYS', 14);       // окно возврата после оплаты

// --- Секрет для запуска кронов по HTTP ---
define('CRON_SECRET', 'СГЕНЕРИРУЙТЕ_ДЛИННУЮ_СЛУЧАЙНУЮ_СТРОКУ');