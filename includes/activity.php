<?php
/**
 * GigaChefAI — Система активностей
 * Поддерживает все типы: рецепты, фьюжн, кулинарные книги, достижения
 */
declare(strict_types=1);

/**
 * Карта типов активности: иконка, цвет, название, баллы.
 *
 * Иконки рендерятся через icon() из includes/icons.php
 * (файлы: /uploads/icon/<name>.svg, все имена — из config/icon-names.php).
 */
function activity_types_map(): array
{
    return [
        // ─── Обычные рецепты ───
        'recipe'            => [
            'icon'   => icon('openbook', '', 1.5),
            'color'  => 'amber',
            'title'  => 'Рецепт',
            'points' => 0,
        ],
        'recipe_create'     => [
            'icon'   => icon('plus', '', 1.5),
            'color'  => 'orange',
            'title'  => 'Создал рецепт',
            'points' => 10,
        ],
        'recipe_created'    => [
            'icon'   => icon('plus', '', 1.5),
            'color'  => 'orange',
            'title'  => 'Создал рецепт',
            'points' => 10,
        ],
        'recipe_save'       => [
            'icon'   => icon('save', '', 1.5),
            'color'  => 'green',
            'title'  => 'Сохранил рецепт',
            'points' => 5,
        ],
        'recipe_photo'      => [
            'icon'   => icon('picture', '', 1.5),
            'color'  => 'blue',
            'title'  => 'Фото рецепта',
            'points' => 3,
        ],

        // ─── AI-рецепты ───
        'ai_recipe'         => [
            'icon'   => icon('sparkle', '', 1.5),
            'color'  => 'violet',
            'title'  => 'AI-рецепт',
            'points' => 10,
        ],

        // ─── Фьюжн ───
        'fusion_generate'   => [
            'icon'   => icon('fusion', '', 1.5),
            'color'  => 'purple',
            'title'  => 'Создал фьюжн',
            'points' => 5,
        ],
        'fusion_save'       => [
            'icon'   => icon('save', '', 1.5),
            'color'  => 'green',
            'title'  => 'Сохранил фьюжн',
            'points' => 10,
        ],
        'fusion_share'      => [
            'icon'   => icon('share', '', 1.5),
            'color'  => 'blue',
            'title'  => 'Поделился фьюжн',
            'points' => 3,
        ],
        'fusion_rated'      => [
            'icon'   => icon('star', '', 1.5),
            'color'  => 'yellow',
            'title'  => 'Оценил фьюжн',
            'points' => 0,
        ],

        // ─── Кулинарные книги ───
        'cookbook_created'  => [
            'icon'   => icon('cookbook', '', 1.5),
            'color'  => 'blue',
            'title'  => 'Создал книгу',
            'points' => 15,
        ],
        'cookbook_save'     => [
            'icon'   => icon('openbook', '', 1.5),
            'color'  => 'blue',
            'title'  => 'Сохранил книгу',
            'points' => 5,
        ],

        // ─── Достижения / уровни ───
        'achievement'       => [
            'icon'   => icon('achievements', '', 1.5),
            'color'  => 'amber',
            'title'  => 'Достижение',
            'points' => 0,
        ],
        'level_up'          => [
            'icon'   => icon('chart', '', 1.5),
            'color'  => 'violet',
            'title'  => 'Новый уровень',
            'points' => 0,
        ],

        // ─── Челленджи ───
        'challenge_joined'  => [
            'icon'   => icon('challenges', '', 1.5),
            'color'  => 'amber',
            'title'  => 'Присоединился к челленджу',
            'points' => 3,
        ],
        'challenge_completed' => [
            'icon'   => icon('medal', '', 1.5),
            'color'  => 'yellow',
            'title'  => 'Прошёл челлендж',
            'points' => 20,
        ],
        'challenge_submitted' => [
            'icon'   => icon('check', '', 1.5),
            'color'  => 'emerald',
            'title'  => 'Работа отправлена',
            'points' => 5,
        ],

        // ─── Сообщества ───
        'community_joined'  => [
            'icon'   => icon('communities', '', 1.5),
            'color'  => 'blue',
            'title'  => 'Вступил в сообщество',
            'points' => 2,
        ],
        'community_post'    => [
            'icon'   => icon('news', '', 1.5),
            'color'  => 'blue',
            'title'  => 'Публикация в сообществе',
            'points' => 3,
        ],

        // ─── Прочее ───
        'beer_pairing'      => [
            'icon'   => icon('beer', '', 1.5),
            'color'  => 'yellow',
            'title'  => 'Пивное сочетание',
            'points' => 3,
        ],
        'calorie_scan'      => [
            'icon'   => icon('scaner', '', 1.5),
            'color'  => 'emerald',
            'title'  => 'Сканирование',
            'points' => 2,
        ],
        'comment'           => [
            'icon'   => icon('chats', '', 1.5),
            'color'  => 'gray',
            'title'  => 'Комментарий',
            'points' => 0,
        ],
        'like'              => [
            'icon'   => icon('like', '', 1.5),
            'color'  => 'rose',
            'title'  => 'Лайк',
            'points' => 0,
        ],
        'friend'            => [
            'icon'   => icon('friends', '', 1.5),
            'color'  => 'blue',
            'title'  => 'Друг',
            'points' => 0,
        ],
        'story'             => [
            'icon'   => icon('stories', '', 1.5),
            'color'  => 'violet',
            'title'  => 'История',
            'points' => 1,
        ],
        'system'            => [
            'icon'   => icon('settings', '', 1.5),
            'color'  => 'gray',
            'title'  => 'Система',
            'points' => 0,
        ],
    ];
}

/**
 * Иконка для типа активности. Возвращает готовый HTML через icon().
 * Если типа нет — фолбэк на 'system'.
 */
function activity_icon(string $type): string
{
    $map = activity_types_map();
    if (isset($map[$type]['icon'])) {
        return $map[$type]['icon'];
    }
    // Фолбэк
    return icon('settings', '', 1.5);
}

/**
 * Цвет для типа активности (для CSS-классов).
 */
function activity_color(string $type): string
{
    $map = activity_types_map();
    return $map[$type]['color'] ?? 'gray';
}

/**
 * Название типа активности.
 */
function activity_title(string $type): string
{
    $map = activity_types_map();
    return $map[$type]['title'] ?? 'Активность';
}

/**
 * Баллы за тип активности.
 */
function activity_points(string $type): int
{
    $map = activity_types_map();
    return (int)($map[$type]['points'] ?? 0);
}

/**
 * Добавляет активность в ленту.
 */
function add_activity(
    string $userEmail,
    string $type,
    ?int $referenceId = null,
    ?string $referenceType = null,
    ?string $message = null,
    string $visibility = 'public'
): void {
    try {
        $types = activity_types_map();
        if (!isset($types[$type])) {
            $type = 'system';
        }

        db_insert(
            'INSERT INTO activity_feed (user_email, type, reference_id, reference_type, message, visibility) 
             VALUES (?, ?, ?, ?, ?, ?)',
            [$userEmail, $type, $referenceId, $referenceType, $message, $visibility]
        );
    } catch (Throwable $e) {
        error_log('add_activity error: ' . $e->getMessage());
    }
}

/**
 * Получает ленту активностей.
 */
function get_feed(string $userEmail, string $filter = 'all', int $page = 1, int $perPage = 20): array
{
    $offset = ($page - 1) * $perPage;

    if ($filter === 'friends') {
        return db_all(
            "SELECT af.*, u.display_name, u.avatar_emoji, u.avatar_url
             FROM activity_feed af
             JOIN users u ON u.email = af.user_email
             WHERE af.user_email IN (
                 SELECT friend_email FROM friendships WHERE user_email = ? AND status = 'accepted'
             )
             AND af.visibility IN ('public', 'friends')
             ORDER BY af.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            [$userEmail]
        );
    }

    if ($filter === 'ai') {
        return db_all(
            "SELECT af.*, u.display_name, u.avatar_emoji, u.avatar_url
             FROM activity_feed af
             JOIN users u ON u.email = af.user_email
             WHERE af.type IN (
                 'ai_recipe', 'beer_pairing', 'calorie_scan', 
                 'cookbook_created', 'fusion_generate', 'fusion_save'
             )
             AND af.visibility = 'public'
             ORDER BY af.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
    }

    if ($filter === 'fusion') {
        return db_all(
            "SELECT af.*, u.display_name, u.avatar_emoji, u.avatar_url
             FROM activity_feed af
             JOIN users u ON u.email = af.user_email
             WHERE af.type IN ('fusion_generate', 'fusion_save', 'fusion_rated', 'fusion_share')
             AND af.visibility = 'public'
             ORDER BY af.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
    }

    if ($filter === 'achievements') {
        return db_all(
            "SELECT af.*, u.display_name, u.avatar_emoji, u.avatar_url
             FROM activity_feed af
             JOIN users u ON u.email = af.user_email
             WHERE af.type = 'achievement'
             AND af.visibility = 'public'
             ORDER BY af.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
    }

    return db_all(
        "SELECT af.*, u.display_name, u.avatar_emoji, u.avatar_url
         FROM activity_feed af
         JOIN users u ON u.email = af.user_email
         WHERE af.visibility = 'public'
         ORDER BY af.created_at DESC
         LIMIT {$perPage} OFFSET {$offset}"
    );
}

/**
 * Отправляет уведомление пользователю.
 */
function send_notification(
    string $userEmail,
    string $title,
    string $message,
    string $type = 'system',
    ?string $linkUrl = null
): void {
    try {
        $settings = db_one('SELECT notification_settings FROM users WHERE email = ?', [$userEmail]);
        if ($settings && !empty($settings['notification_settings'])) {
            $prefs = json_decode($settings['notification_settings'], true);
            if (is_array($prefs)) {
                $typeMap = [
                    'achievement' => 'recipe_approved',
                    'comment'     => 'new_comments',
                    'like'        => 'new_likes',
                    'message'     => 'new_messages',
                    'friend'      => 'new_friend_requests',
                ];
                if (isset($typeMap[$type]) && empty($prefs[$typeMap[$type]])) {
                    return;
                }
            }
        }

        try {
            db_insert(
                'INSERT INTO notifications (user_email, title, message, type, link_url, created_at) 
                 VALUES (?, ?, ?, ?, ?, NOW())',
                [$userEmail, $title, $message, $type, $linkUrl]
            );
        } catch (Throwable $e) {
            db_insert(
                'INSERT INTO notifications (user_email, title, message, type) VALUES (?, ?, ?, ?)',
                [$userEmail, $title, $message, $type]
            );
        }
    } catch (Throwable $e) {
        error_log('send_notification error: ' . $e->getMessage());
    }
}

/**
 * Отправляет уведомления о разблокированных достижениях.
 */
function send_achievement_notification(string $userEmail, array $achievements): void
{
    foreach ($achievements as $achievement) {
        send_notification(
            $userEmail,
            '🏅 Достижение получено!',
            $achievement['title'] . ' — ' . $achievement['description'],
            'achievement'
        );
    }
}

/**
 * Форматирует время активности.
 */
function format_activity_time(string $dateStr): string
{
    $d = new DateTime($dateStr);
    $n = new DateTime();
    $diff = $n->diff($d);

    if ($diff->days === 0) {
        if ($diff->h === 0) {
            if ($diff->i < 1) return 'только что';
            return $diff->i . ' мин. назад';
        }
        return $diff->h . ' ч. назад';
    }

    if ($diff->days === 1) return 'вчера';
    if ($diff->days < 7) return $diff->days . ' дн. назад';
    if ($diff->days < 30) return floor($diff->days / 7) . ' нед. назад';

    return $d->format('d.m.Y');
}

/**
 * Логирует активность текущего пользователя.
 */
if (!function_exists('log_activity')) {
    function log_activity(
        string $type,
        ?int $referenceId = null,
        ?string $referenceType = null,
        ?string $message = null,
        string $visibility = 'public'
    ): void {
        if (!is_logged_in()) return;

        try {
            db_exec(
                "INSERT INTO activity_feed
                    (user_email, type, reference_id, reference_type, message, visibility, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [
                    current_user()['email'],
                    $type,
                    $referenceId,
                    $referenceType,
                    $message,
                    $visibility,
                ]
            );
        } catch (Throwable $e) {
            error_log('log_activity error: ' . $e->getMessage());
        }
    }
}