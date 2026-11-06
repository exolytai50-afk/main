<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/notifications.php';
require_login();

$user      = current_user();
$userEmail = $user['email'];
$userName  = $user['display_name'] ?? $userEmail;
$success   = '';
$error     = '';

// Добавляем недостающие колонки (один раз)
try {
    $columns     = db_all("SHOW COLUMNS FROM notifications");
    $columnNames = array_column($columns, 'Field');

    if (!in_array('is_pinned', $columnNames)) {
        db_exec("ALTER TABLE notifications ADD COLUMN is_pinned tinyint(1) DEFAULT 0 AFTER is_read");
    }
    if (!in_array('related_email', $columnNames)) {
        db_exec("ALTER TABLE notifications ADD COLUMN related_email varchar(255) DEFAULT NULL AFTER type");
    }
} catch (Throwable $e) {
    error_log('notifications schema check: ' . $e->getMessage());
}

// ─────────────────────────────────────────────────────────────
// Обработка POST
// ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Массовое удаление прочитанных
    if ($action === 'delete_read') {
        db_exec(
            "DELETE FROM notifications
             WHERE user_email = ?
               AND is_read = 1
               AND (is_pinned = 0 OR is_pinned IS NULL)
               AND type != 'friend_request'",
            [$userEmail]
        );
        $success = 'Прочитанные уведомления удалены (закреплённые и требующие действия сохранены)';
    }

    // Удаление всех (кроме закреплённых и заявок)
    elseif ($action === 'delete_all') {
        db_exec(
            "DELETE FROM notifications
             WHERE user_email = ?
               AND (is_pinned = 0 OR is_pinned IS NULL)
               AND type != 'friend_request'",
            [$userEmail]
        );
        $success = 'Уведомления удалены (закреплённые и требующие действия сохранены)';
    }

    // Индивидуальное удаление
    elseif ($action === 'delete') {
        $notificationId = (int)($_POST['notification_id'] ?? 0);
        if ($notificationId <= 0) {
            $error = 'Не указано уведомление';
        } else {
            $notification = db_one(
                'SELECT * FROM notifications WHERE id = ? AND user_email = ?',
                [$notificationId, $userEmail]
            );
            if (!$notification) {
                $error = 'Уведомление не найдено';
            } else {
                $isPinned        = (int)($notification['is_pinned'] ?? 0);
                $isFriendRequest = ($notification['type'] === 'friend_request');
                if ($isPinned) {
                    $error = 'Сначала открепите уведомление, чтобы удалить его';
                } elseif ($isFriendRequest) {
                    $error = 'Сначала примите или отклоните заявку в друзья';
                } else {
                    db_exec('DELETE FROM notifications WHERE id = ?', [$notificationId]);
                    $success = 'Уведомление удалено';
                }
            }
        }
    }

    // Закрепление/открепление
    elseif ($action === 'pin') {
        $notificationId = (int)($_POST['notification_id'] ?? 0);
        if ($notificationId > 0) {
            $notification = db_one(
                'SELECT * FROM notifications WHERE id = ? AND user_email = ?',
                [$notificationId, $userEmail]
            );
            if ($notification) {
                $newPinned = $notification['is_pinned'] ? 0 : 1;
                db_exec('UPDATE notifications SET is_pinned = ? WHERE id = ?', [$newPinned, $notificationId]);
                $success = $newPinned ? 'Уведомление закреплено' : 'Уведомление откреплено';
            }
        }
    }

    // Принятие заявки
    elseif ($action === 'accept_friend') {
        $notificationId = (int)($_POST['notification_id'] ?? 0);
        if ($notificationId > 0) {
            $notification = db_one(
                'SELECT * FROM notifications WHERE id = ? AND user_email = ?',
                [$notificationId, $userEmail]
            );
            if ($notification) {
                $friendEmail = $notification['related_email'] ?? '';
                if ($friendEmail) {
                    // 1. Принимаем только входящую заявку
                    db_exec(
                        "UPDATE friendships SET status = 'accepted'
                         WHERE user_email = ? AND friend_email = ? AND status = 'pending'",
                        [$friendEmail, $userEmail]
                    );

                    // 2. Подчищаем pending-дубли — только pending! (иначе удалим и только что принятую)
                    db_exec(
                        "DELETE FROM friendships
                         WHERE status = 'pending'
                           AND ((user_email = ? AND friend_email = ?)
                             OR (user_email = ? AND friend_email = ?))",
                        [$friendEmail, $userEmail, $userEmail, $friendEmail]
                    );

                    // 3. Уведомляем автора заявки
                    notify_user(
                        $friendEmail,
                        'friend_accepted',
                        'Заявка принята',
                        "{$userName} принял(а) вашу заявку в друзья",
                        $userEmail,
                        url('profile?user=' . urlencode($userEmail))
                    );

                    // 4. Удаляем само уведомление у себя
                    db_exec("DELETE FROM notifications WHERE user_email = ? AND related_email = ? AND type = 'friend_request'",
                    [$userEmail, $friendEmail]);

                    // 5. Сбрасываем кэш
                    flush_friends_cache($userEmail, $friendEmail);

                    $success = 'Заявка принята!';
                }
            }
        }
    }

    // Отклонение заявки
    elseif ($action === 'reject_friend') {
        $notificationId = (int)($_POST['notification_id'] ?? 0);
        if ($notificationId > 0) {
            $notification = db_one(
                'SELECT * FROM notifications WHERE id = ? AND user_email = ?',
                [$notificationId, $userEmail]
            );
            if ($notification) {
                $friendEmail = $notification['related_email'] ?? '';
                if ($friendEmail) {
                    // 1. Удаляем в обе стороны (симметрично)
                    db_exec(
                        "DELETE FROM friendships
                         WHERE (user_email = ? AND friend_email = ?)
                            OR (user_email = ? AND friend_email = ?)",
                        [$friendEmail, $userEmail, $userEmail, $friendEmail]
                    );

                    // 2. Удаляем уведомление
                    db_exec("DELETE FROM notifications WHERE user_email = ? AND related_email = ? AND type = 'friend_request'",
                    [$userEmail, $friendEmail]);

                    // 3. Сбрасываем кэш
                    flush_friends_cache($userEmail, $friendEmail);

                    $success = 'Заявка отклонена';
                }
            }
        }
    }

    if ($action) {
        header('Location: ' . url('notifications'));
        exit;
    }
}

// ─────────────────────────────────────────────────────────────
// Помечаем все уведомления прочитанными (после обработки POST)
// ─────────────────────────────────────────────────────────────
db_exec(
    "UPDATE notifications SET is_read = 1 WHERE user_email = ? AND is_read = 0",
    [$userEmail]
);
if (function_exists('cache_delete')) {
    cache_delete('notifications_unread_' . md5($userEmail)); // старый ключ, оставляем
    cache_delete('api_counters_'        . md5($userEmail)); // кеш /api/notifications.php (15 с)
    cache_delete('user_counters_'       . md5($userEmail)); // кеш счётчиков в шапке
}

// ─────────────────────────────────────────────────────────────
// Получаем уведомления
// ─────────────────────────────────────────────────────────────
$notifications = db_all(
    'SELECT * FROM notifications WHERE user_email = ? ORDER BY is_pinned DESC, created_at DESC LIMIT 100',
    [$userEmail]
);

$readCount         = 0;
$pinnedCount       = 0;
$friendRequestCount = 0;
foreach ($notifications as $n) {
    if ((int)$n['is_read'] === 1 && !(int)($n['is_pinned'] ?? 0) && $n['type'] !== 'friend_request') {
        $readCount++;
    }
    if ((int)($n['is_pinned'] ?? 0) === 1) {
        $pinnedCount++;
    }
    if ($n['type'] === 'friend_request') {
        $friendRequestCount++;
    }
}

$pageTitle = 'Уведомления';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="max-w-2xl mx-auto px-4 sm:px-6 py-8">
    <!-- Заголовок -->
    <div class="mb-4" data-aos="fade-up">
        <h1 class="text-3xl font-extrabold text-gray-800 inline-flex items-center gap-2">
            <?= icon('notifications', 'gc-icon-md') ?> Уведомления
        </h1>
    </div>

    <!-- Массовые действия -->
    <div class="flex flex-wrap gap-2 mb-6" data-aos="fade-up">
        <?php if ($readCount > 0): ?>
        <form method="POST" onsubmit="return confirm('Удалить все прочитанные уведомления?')">
            <input type="hidden" name="action" value="delete_read">
            <button type="submit" class="px-4 py-2 rounded-xl bg-rose-50 text-rose-600 text-xs font-bold hover:bg-rose-100 transition inline-flex items-center gap-1.5">
                <?= icon('trash', 'gc-icon-sm') ?> Удалить прочитанные (<?= $readCount ?>)
            </button>
        </form>
        <?php endif; ?>

        <form method="POST" onsubmit="return confirm('Удалить все уведомления (кроме закреплённых и заявок)?')">
            <input type="hidden" name="action" value="delete_all">
            <button type="submit" class="px-4 py-2 rounded-xl bg-rose-100 text-rose-700 text-xs font-bold hover:bg-rose-200 transition inline-flex items-center gap-1.5">
                <?= icon('trash', 'gc-icon-sm') ?> Удалить все
            </button>
        </form>
    </div>

    <?php if ($success): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl mb-4 inline-flex items-center gap-2 w-full">
            <?= icon('check', 'gc-icon-sm') ?>
            <span><?= e($success) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl mb-4 inline-flex items-center gap-2 w-full">
            <?= icon('warning', 'gc-icon-sm') ?>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if (empty($notifications)): ?>
        <div class="text-center py-20" data-aos="fade-up">
            <div class="mb-4 flex justify-center text-gray-300"><?= icon('notifications', 'gc-icon-3xl') ?></div>
            <p class="text-gray-400">У вас пока нет уведомлений</p>
        </div>
    <?php else: ?>
        <div class="space-y-2" data-aos="fade-up">
            <?php foreach ($notifications as $notif):
                $iconName = match($notif['type']) {
                    'like'             => 'heart',
                    'comment'          => 'chats',
                    'friend_request'   => 'friends',
                    'friend_accepted'  => 'friends',
                    'friend_removed'   => 'friends',
                    'achievement'      => 'medal',
                    'challenge'        => 'challenges',
                    'system'           => 'technical',
                    'payment'          => 'payment',
                    default            => 'notifications',
                };
                $isPinned        = (int)($notif['is_pinned'] ?? 0);
                $isFriendRequest = ($notif['type'] === 'friend_request');
                $relatedEmail    = $notif['related_email'] ?? '';
                $canRespond      = $isFriendRequest && $relatedEmail !== '';
            ?>
                <div class="bg-white rounded-2xl shadow-md p-4 flex items-start gap-3 transition
                            <?= $notif['is_read'] ? '' : 'border-l-4 border-amber-400' ?>
                            <?= $isPinned ? 'ring-2 ring-amber-300' : '' ?>">

                    <span class="shrink-0 mt-0.5"><?= icon($iconName, 'gc-icon-xl') ?></span>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <div class="font-bold text-gray-800 text-sm"><?= e($notif['title']) ?></div>
                            <?php if ($isPinned): ?>
                                <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full inline-flex items-center gap-1">
                                    <?= icon('pin', 'gc-icon-xs') ?> Закреплено
                                </span>
                            <?php endif; ?>
                            <?php if ($canRespond): ?>
                                <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full inline-flex items-center gap-1">
                                    <?= icon('warning', 'gc-icon-xs') ?> Требует действия
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="text-gray-500 text-sm mt-0.5"><?= e($notif['message']) ?></div>
                        <div class="text-xs text-gray-400 mt-1"><?= date('d.m.Y H:i', strtotime($notif['created_at'])) ?></div>

                        <?php if ($canRespond): ?>
                            <div class="flex gap-2 mt-2">
                                <form method="POST">
                                    <input type="hidden" name="action" value="accept_friend">
                                    <input type="hidden" name="notification_id" value="<?= (int)$notif['id'] ?>">
                                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-emerald-500 text-white text-xs font-bold hover:bg-emerald-600 transition inline-flex items-center gap-1">
                                        <?= icon('check', 'gc-icon-xs') ?> Принять
                                    </button>
                                </form>
                                <form method="POST">
                                    <input type="hidden" name="action" value="reject_friend">
                                    <input type="hidden" name="notification_id" value="<?= (int)$notif['id'] ?>">
                                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-rose-100 text-rose-600 text-xs font-bold hover:bg-rose-200 transition inline-flex items-center gap-1">
                                        <?= icon('close', 'gc-icon-xs') ?> Отклонить
                                    </button>
                                </form>
                            </div>
                        <?php elseif ($isFriendRequest): ?>
                            <!-- Старые заявки без related_email: ссылка на страницу заявок -->
                            <a href="<?= url('friends') ?>#requests" class="text-xs text-amber-600 hover:underline mt-1 inline-block">
                                Перейти к заявкам →
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="flex flex-col gap-1 shrink-0">
                        <!-- Закрепить/открепить -->
                        <form method="POST">
                            <input type="hidden" name="action" value="pin">
                            <input type="hidden" name="notification_id" value="<?= (int)$notif['id'] ?>">
                            <button type="submit"
                                    class="w-8 h-8 rounded-lg <?= $isPinned ? 'bg-amber-100 text-amber-700' : 'bg-gray-50 text-gray-400 hover:bg-gray-100' ?> flex items-center justify-center transition"
                                    title="<?= $isPinned ? 'Открепить' : 'Закрепить' ?>">
                                <?= icon('pin', 'gc-icon-sm') ?>
                            </button>
                        </form>

                        <!-- Удалить -->
                        <?php if (!$isPinned && !$isFriendRequest): ?>
                            <form method="POST" onsubmit="return confirm('Удалить уведомление?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="notification_id" value="<?= (int)$notif['id'] ?>">
                                <button type="submit"
                                        class="w-8 h-8 rounded-lg bg-gray-50 text-gray-400 hover:bg-rose-50 hover:text-rose-500 flex items-center justify-center transition"
                                        title="Удалить">
                                    <?= icon('trash', 'gc-icon-sm') ?>
                                </button>
                            </form>
                        <?php elseif ($isPinned): ?>
                            <span class="text-[10px] text-gray-400 text-center leading-tight">Открепите<br>для удаления</span>
                        <?php elseif ($isFriendRequest): ?>
                            <span class="text-[10px] text-gray-400 text-center leading-tight">Сначала<br>ответьте</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<script>
window.addEventListener('load', function () {
    if (window.gigachefPoll && typeof window.gigachefPoll.run === 'function') {
        window.gigachefPoll.run();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>