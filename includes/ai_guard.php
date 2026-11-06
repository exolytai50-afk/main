<?php
/**
 * GigaChefAI — защита AI-эндпоинтов от перегрузки (rate limit).
 */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

/**
 * Проверяет дневной лимит для конкретной AI-фичи.
 * Если лимит исчерпан — выбрасывает исключение.
 *
 * @param string $feature  имя фичи (generate, calorie, cookbook)
 * @throws RuntimeException при превышении лимита
 */
function ai_guard(string $feature): void
{
    // ✅ Администраторы без ограничений
    if (is_admin()) {
        return;
    }

    $defaults = ['guest' => 50, 'pro' => 999999];
    $limits = defined('AI_LIMITS') ? AI_LIMITS : [];
    [$guestLimit, $proLimit] = $limits[$feature] ?? [$defaults['guest'], $defaults['pro']];

    [$guestLimit, $proLimit] = $limits[$feature];

    // Определяем лимит в зависимости от подписки
    $isPro = is_logged_in() && is_premium();
    $limit = $isPro ? $proLimit : $guestLimit;

    // Гостям без подписки — минимальный лимит
    if (!is_logged_in()) {
        $identifier = $_SERVER['REMOTE_ADDR'] ?? 'guest';
        $limit = $guestLimit;
    } else {
        $identifier = current_user()['email'];
    }

    // Считаем использованные запросы сегодня
    $today = date('Y-m-d');
    $used = (int)(db_one(
        'SELECT SUM(used_count) as total FROM ai_usage 
         WHERE identifier = ? AND feature = ? AND used_date = ?',
        [$identifier, $feature, $today]
    )['total'] ?? 0);

    if ($used >= $limit) {
        $resetTime = date('Y-m-d 00:00:00', strtotime('+1 day'));
        throw new RuntimeException(
            "Дневной лимит исчерпан ({$used}/{$limit}). Лимит обновится {$resetTime}."
        );
    }

    // Инкрементируем счётчик
    try {
        db_exec(
            'INSERT INTO ai_usage (identifier, feature, used_date, used_count) 
             VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE used_count = used_count + 1',
            [$identifier, $feature, $today]
        );
    } catch (Throwable $e) {
        error_log('ai_guard increment error: ' . $e->getMessage());
        // Если таблица не существует — продолжаем без ограничений
    }
}

/**
 * Возвращает текущий статус использования AI-фичи.
 *
 * @param string $feature
 * @return array{limit: int, remaining: int, reset: string, is_pro: bool}
 */
function ai_usage_status(string $feature): array
{
    // ✅ Администраторы — безлимит
    if (is_admin()) {
        return [
            'limit'     => 999,
            'remaining' => 999,
            'reset'     => 'никогда',
            'is_pro'    => true,
        ];
    }

    $limits = defined('AI_LIMITS') ? AI_LIMITS : [];
    [$guestLimit, $proLimit] = $limits[$feature] ?? [50, 999999];

    $isPro = is_logged_in() && is_premium();
    $limit = $isPro ? $proLimit : $guestLimit;

    if (!is_logged_in()) {
        $identifier = $_SERVER['REMOTE_ADDR'] ?? 'guest';
        $limit = $guestLimit;
    } else {
        $identifier = current_user()['email'];
    }

    $today = date('Y-m-d');
    $used = (int)(db_one(
        'SELECT SUM(used_count) as total FROM ai_usage 
         WHERE identifier = ? AND feature = ? AND used_date = ?',
        [$identifier, $feature, $today]
    )['total'] ?? 0);

    return [
        'limit'     => $limit,
        'remaining' => max(0, $limit - $used),
        'reset'     => date('Y-m-d 00:00:00', strtotime('+1 day')),
        'is_pro'    => $isPro,
    ];
}