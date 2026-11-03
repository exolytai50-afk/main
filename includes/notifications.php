<?php
/**
 * includes/notifications.php
 *
 * Общие хелперы для уведомлений и сброса кэша, связанного с дружбой.
 * Подключается из:
 *   - api/friends.php
 *   - pages/notifications.php
 *
 * Требует, чтобы config.php уже был подключён (db_*, url(), error_log настроены).
 */

if (!function_exists('notify_user')) {
    /**
     * Создать уведомление. Тихо игнорирует дубли и ошибки.
     *
     * @param string      $toEmail       Кому (user_email)
     * @param string      $type          friend_request | friend_accepted | friend_removed | ...
     * @param string      $title         Заголовок
     * @param string      $message       Текст
     * @param string      $relatedEmail  Второй участник события
     * @param string|null $link          Ссылка (опционально, если колонки link нет — см. флаг ниже)
     */
    function notify_user(
        string $toEmail,
        string $type,
        string $title,
        string $message,
        string $relatedEmail = '',
        ?string $link = null
    ): void {
        if ($toEmail === '') {
            return;
        }
        try {
            // Антиспам: не дублируем одинаковое уведомление за последние 10 минут
            $exists = db_one(
                'SELECT id FROM notifications
                 WHERE user_email=? AND type=? AND related_email=?
                   AND created_at > (NOW() - INTERVAL 10 MINUTE)
                 LIMIT 1',
                [$toEmail, $type, $relatedEmail]
            );
            if ($exists) {
                return;
            }

            // Проверяем, есть ли колонка link — чтобы не падать на старых схемах
            static $hasLink = null;
            if ($hasLink === null) {
                try {
                    $cols = db_all("SHOW COLUMNS FROM notifications LIKE 'link'");
                    $hasLink = !empty($cols);
                } catch (Throwable $e) {
                    $hasLink = false;
                }
            }

            if ($hasLink) {
                db_insert(
                    'INSERT INTO notifications
                        (user_email, type, title, message, related_email, link, is_read, created_at)
                     VALUES (?,?,?,?,?,?,0,NOW())',
                    [$toEmail, $type, $title, $message, $relatedEmail, $link]
                );
            } else {
                db_insert(
                    'INSERT INTO notifications
                        (user_email, type, title, message, related_email, is_read, created_at)
                     VALUES (?,?,?,?,?,0,NOW())',
                    [$toEmail, $type, $title, $message, $relatedEmail]
                );
            }

            // Сбрасываем счётчик непрочитанных у получателя
            if (function_exists('cache_delete')) {
                cache_delete('notifications_unread_' . md5($toEmail));
                cache_delete('api_counters_' . md5($toEmail));
                cache_delete('user_counters_' . md5($toEmail));
            }
        } catch (Throwable $e) {
            error_log('notify_user failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('flush_friends_cache')) {
    /**
     * Сбросить все кэши, связанные с дружбой, для указанных email'ов.
     * Безопасно вызывать, даже если cache_delete не определён (просто no-op).
     */
    function flush_friends_cache(string ...$emails): void {
        if (!function_exists('cache_delete')) {
            return;
        }
        $prefixes = [
            'api_counters_',
            'user_counters_',
            'friends_list_',
            'friends_pending_',
            'friends_incoming_',
            'notifications_unread_',
        ];
        foreach ($emails as $email) {
            if ($email === '') {
                continue;
            }
            $h = md5($email);
            foreach ($prefixes as $p) {
                cache_delete($p . $h);
            }
        }
    }
}