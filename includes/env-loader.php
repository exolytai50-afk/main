<?php
/**
 * GigaChefAI — загрузчик окружения из .env файла
 * Должен быть подключён ПЕРВЫМ в config.php
 */

function load_env_file(string $path = null): void
{
    if ($path === null) {
        $path = dirname(__DIR__) . '/.env';
    }

    if (!file_exists($path)) {
        return; // .env не найден — используются системные переменные окружения
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Пропускаем комментарии
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Парсим KEY=VALUE
        if (strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Удаляем кавычки если есть
        if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1)
            || (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
            $value = substr($value, 1, -1);
        }

        // Устанавливаем как переменную окружения (если ещё не установлена)
        if (!getenv($key, true)) {
            putenv("{$key}={$value}");
        }
    }
}

// Загружаем .env файл
load_env_file();
