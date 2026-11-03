<?php
/**
 * GigaChef — режим технического обслуживания.
 *
 * Что делает:
 *   1. Проверяет site_settings.maintenance_enabled.
 *   2. Если включено:
 *      - админ видит сайт + баннер «Техработы идут».
 *      - все остальные видят красивую заглушку.
 *   3. Если время истекло:
 *      - автоматически выключает режим.
 *      - показывает «Мы вернулись» тем, кто зайдёт после.
 */
declare(strict_types=1);

if (!function_exists('maintenance_get_settings')) {
    /**
     * Читает все настройки обслуживания.
     */
    function maintenance_get_settings(): array
    {
        $defaults = [
            'maintenance_enabled'                 => '0',
            'maintenance_until'                   => '',
            'maintenance_message'                 => '',
            'maintenance_started_at'              => '',
            'maintenance_finished_notice_until'   => '',
        ];

        try {
            $rows = db_all(
                "SELECT `key`, `value` FROM site_settings 
                 WHERE `key` LIKE 'maintenance_%'"
            );
            foreach ($rows as $r) {
                if (array_key_exists($r['key'], $defaults)) {
                    $defaults[$r['key']] = (string)$r['value'];
                }
            }
        } catch (Throwable $e) {
            error_log('maintenance_get_settings: ' . $e->getMessage());
        }

        return $defaults;
    }
}

if (!function_exists('maintenance_set')) {
    /**
     * Сохраняет значение настройки.
     */
    function maintenance_set(string $key, string $value): void
    {
        try {
            db_exec(
                'INSERT INTO site_settings (`key`, `value`) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
                [$key, $value]
            );
        } catch (Throwable $e) {
            error_log('maintenance_set: ' . $e->getMessage());
        }
    }
}

if (!function_exists('maintenance_check_and_autofinish')) {
    /**
     * Проверяет, не истёк ли режим. Если истёк — выключает 
     * и ставит флаг «недавно завершилось» на 24 часа.
     */
    function maintenance_check_and_autofinish(): array
    {
        $s = maintenance_get_settings();

        if ($s['maintenance_enabled'] !== '1') {
            return $s;
        }

        $until = $s['maintenance_until'];
        if ($until !== '' && strtotime($until) !== false && strtotime($until) <= time()) {
            // Истёк — выключаем
            maintenance_set('maintenance_enabled', '0');
            maintenance_set('maintenance_finished_notice_until', date('Y-m-d H:i:s', time() + 86400));
            $s['maintenance_enabled'] = '0';
        }

        return $s;
    }
}

if (!function_exists('maintenance_is_admin')) {
    /**
     * Текущий пользователь — админ?
     */
    function maintenance_is_admin(): bool
    {
        if (!function_exists('is_admin')) return false;
        try {
            return is_admin();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('cookai_maintenance_gate')) {
    /**
     * Вызывается в config.php. Если режим включён и это не админ 
     * и это не /api/ — рендерим заглушку и exit.
     */
    function cookai_maintenance_gate(): void
    {
        // Не применяем для API
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (str_contains($uri, '/api/') || str_contains($uri, '/cron/')) {
            return;
        }

        // Не применяем для страниц логина/логаута — чтобы админ мог войти
        if (preg_match('~/(login|logout|register)~', $uri)) {
            return;
        }

        $s = maintenance_check_and_autofinish();

        if ($s['maintenance_enabled'] === '1' && !maintenance_is_admin()) {
            // Показываем заглушку
            http_response_code(503);
            header('Retry-After: ' . max(60, strtotime($s['maintenance_until'] ?: '+1 hour') - time()));
            require ROOT_DIR . '/pages/maintenance.php';
            exit;
        }
    }
}

if (!function_exists('cookai_maintenance_notice')) {
    function cookai_maintenance_notice(): ?array
    {
        $s = maintenance_check_and_autofinish();
        if ($s['maintenance_enabled'] !== '1') return null;

        $until = $s['maintenance_until'] ?: '';
        $untilTs = 0;
        $minutesLeft = 0;

        if ($until !== '' && strtotime($until) !== false) {
            $untilTs = (int)strtotime($until);
            $minutesLeft = max(0, (int)floor(($untilTs - time()) / 60));
        }

        return [
            'enabled'      => true,
            'until'        => $until,
            'until_ts'     => $untilTs,      // ← добавили
            'minutes_left' => $minutesLeft,
            'message'      => $s['maintenance_message'],
        ];
    }
}

if (!function_exists('maintenance_should_show_finished')) {
    /**
     * Нужно ли показать баннер «Мы вернулись»?
     */
    function maintenance_should_show_finished(): bool
    {
        $s = maintenance_get_settings();
        if ($s['maintenance_enabled'] === '1') return false;

        $noticeUntil = $s['maintenance_finished_notice_until'];
        if ($noticeUntil === '') return false;

        return strtotime($noticeUntil) > time();
    }
}