<?php
/**
 * GigaChefAI — CDN-модуль.
 *
 * Пока cdn_enabled = 0, ссылки строятся на основном домене. Когда включите — через CDN-домен.
 *
 * Использование в шаблонах:
 *     <img src="<?= cdn_url('/uploads/emoji/1.png') ?>">
 *
 * К адресу локального файла автоматически добавляется ?v=<время изменения файла>.
 * Благодаря этому при годовом сроке кеша в CDN и в браузере заменённый файл сразу получает новый адрес
 * и не «залипает» у пользователей. Отключить для одного вызова: cdn_url($path, false).
 */
declare(strict_types=1);

/**
 * Возвращает URL ресурса: через CDN, если он включён и настроен, иначе — через основной домен.
 *
 * @param string $path    Путь к ресурсу (например, '/uploads/emoji/1.png')
 * @param bool   $version Добавлять ли ?v=<mtime> для существующих локальных файлов
 */
function cdn_url(string $path, bool $version = true): string
{
    // Абсолютные адреса не трогаем
    if (preg_match('~^(?:https?:)?//~i', $path) || str_starts_with($path, 'data:')) {
        return $path;
    }

    $path = ltrim($path, '/');
    if ($version) {
        $path = cdn_add_version($path);
    }

    if (!cdn_is_enabled()) {
        return url($path);
    }

    $domain = cdn_domain();
    if ($domain === '') {
        return url($path);
    }

    // Всегда https: за балансировщиком/прокси $_SERVER['HTTPS'] обычно пустой,
    // и определение по нему давало http:// (блокировка смешанного содержимого на https-сайте).
    return 'https://' . $domain . '/' . $path;
}

/**
 * Добавляет ?v=mtime к пути существующего файла (если в адресе ещё нет query).
 */
function cdn_add_version(string $path): string
{
    if (str_contains($path, '?') || str_contains($path, '#') || !defined('ROOT_DIR')) {
        return $path;
    }
    $file = ROOT_DIR . '/' . $path;
    if (is_file($file)) {
        $mtime = @filemtime($file);
        if ($mtime) {
            return $path . '?v=' . $mtime;
        }
    }
    return $path;
}

/**
 * Домен CDN без протокола, пути и порта. Пустая строка, если не задан.
 */
function cdn_domain(): string
{
    $d = trim(cdn_get_setting('cdn_domain', ''));
    $d = preg_replace('~^[a-z][a-z0-9+.\-]*://~i', '', $d) ?? '';
    $d = explode('/', $d, 2)[0];
    return strtolower(trim($d));
}

/**
 * Включён ли CDN.
 */
function cdn_is_enabled(): bool
{
    return cdn_get_setting('cdn_enabled', '0') === '1';
}

/**
 * Читает настройки CDN из БД. Один запрос на страницу (кеш живёт до конца запроса).
 * Кеш сбрасывается при cdn_set_setting(), поэтому админка сразу видит сохранённые значения.
 */
function cdn_get_setting(string $key, string $default = ''): string
{
    $cache = &cdn_settings_store();

    if ($cache === null) {
        $cache = [];
        try {
            $rows = db_all('SELECT setting_key, setting_value FROM cdn_settings');
            foreach ($rows as $row) {
                $cache[$row['setting_key']] = (string)$row['setting_value'];
            }
        } catch (Throwable $e) {
            // Таблицы может не быть — работаем как без CDN
        }
    }

    return $cache[$key] ?? $default;
}

/**
 * Сохранить настройку CDN.
 */
function cdn_set_setting(string $key, string $value): bool
{
    try {
        db_exec(
            'INSERT INTO cdn_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            [$key, $value]
        );
        $cache = &cdn_settings_store();
        $cache = null; // перечитаем при следующем обращении
        return true;
    } catch (Throwable $e) {
        error_log('cdn_set_setting error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Хранилище кеша настроек (общее для чтения и сброса).
 */
function &cdn_settings_store(): ?array
{
    static $store = null;
    return $store;
}
