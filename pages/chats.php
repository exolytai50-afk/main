<?php
$__showMobileNav = false;
require_once __DIR__ . '/../config/config.php';
require_login();

$user = current_user();
$userEmail = $user['email'];

// ============================================================
// Получаем список диалогов (личные + группы)
// ============================================================
$chats = db_all(
    "SELECT 
        t.counterpart_email,
        t.last_msg_id,
        t.last_msg_time,
        CASE WHEN f.user_email IS NOT NULL THEN 1 ELSE 0 END AS is_pinned,
        NULL AS group_id,
        NULL AS group_name,
        NULL AS group_creator_email,
        t.counterpart_email AS pin_key
     FROM (
        SELECT counterpart_email, MAX(id) AS last_msg_id, MAX(created_at) AS last_msg_time
        FROM (
            SELECT 
                CASE WHEN sender_email = ? THEN receiver_email ELSE sender_email END AS counterpart_email,
                id, created_at
            FROM chat_messages
            WHERE sender_email = ? OR receiver_email = ?
        ) sub
        GROUP BY counterpart_email
     ) t
     LEFT JOIN chat_favorites f 
        ON f.user_email = ? 
       AND f.counterpart_email = t.counterpart_email

     UNION ALL

     SELECT 
        NULL AS counterpart_email,
        COALESCE(gm.last_id, 0) AS last_msg_id,
        COALESCE(gm.last_time, g.created_at) AS last_msg_time,
        CASE WHEN f.user_email IS NOT NULL THEN 1 ELSE 0 END AS is_pinned,
        g.id AS group_id,
        g.name AS group_name,
        g.creator_email AS group_creator_email,
        CONCAT('group:', g.id) AS pin_key
     FROM chat_groups g
     JOIN chat_group_members cm ON cm.group_id = g.id AND cm.user_email = ?
     LEFT JOIN (
        SELECT group_id, MAX(id) AS last_id, MAX(created_at) AS last_time
        FROM chat_group_messages
        GROUP BY group_id
     ) gm ON gm.group_id = g.id
     LEFT JOIN chat_favorites f 
        ON f.user_email = ? 
       AND f.counterpart_email = CONCAT('group:', g.id)

     ORDER BY is_pinned DESC, last_msg_time DESC",
    [$userEmail, $userEmail, $userEmail, $userEmail, $userEmail, $userEmail]
);

// ============================================================
// Обогащаем данные чатов
// ============================================================
foreach ($chats as &$chat) {
    if (!empty($chat['group_id'])) {
        $chat['user'] = [
            'display_name' => $chat['group_name'],
            'avatar_emoji' => null,
            'avatar_url' => null,
            'email' => null
        ];

        if ($chat['last_msg_id'] > 0) {
            $chat['last_message'] = db_one(
                'SELECT message, sticker_url, image_url, sender_email, created_at FROM chat_group_messages WHERE id = ?',
                [$chat['last_msg_id']]
            );
        } else {
            $chat['last_message'] = null;
        }
        $chat['unread_count'] = 0;
    } else {
        $chat['user'] = db_one(
            'SELECT id, email, display_name, gender, avatar_emoji, avatar_url FROM users WHERE email = ?',
            [$chat['counterpart_email']]
        );

        $chat['last_message'] = db_one(
            'SELECT message, sticker_url, image_url, is_read, sender_email, created_at FROM chat_messages WHERE id = ?',
            [$chat['last_msg_id']]
        );

        $unread = db_one(
            'SELECT COUNT(*) as cnt FROM chat_messages WHERE sender_email = ? AND receiver_email = ? AND is_read = 0',
            [$chat['counterpart_email'], $userEmail]
        );
        $chat['unread_count'] = (int)$unread['cnt'];
    }
}
unset($chat);

$users = db_all('SELECT id, email, display_name, gender, avatar_emoji, avatar_url FROM users WHERE email != ?', [$userEmail]);

// ============================================================
// Превью последнего сообщения — теперь с icon()
// ============================================================
function lastMessagePreview($msg): array
{
    if (empty($msg)) return ['text' => '', 'prefix' => ''];

    if (!empty($msg['sticker_url'])) return [
        'text' => 'Стикер',
        'prefix' => icon('palette'),
    ];

    if (!empty($msg['image_url'])) return [
        'text' => 'Фото',
        'prefix' => icon('image'),
    ];

    $text = $msg['message'] ?? '';
    $hasEmoji = false;

    if (mb_stripos($text, '<img') !== false) {
        $hasEmoji = true;
        
        // Ищем <img ... src="..." ...> и заменяем на уменьшенную версию
        $text = preg_replace_callback(
            '/<img[^>]+src="([^"]+)"[^>]*>/i',
            function ($matches) {
                return '<img src="' . $matches[1] . '" style="width: 1.3em; height: 1.3em; vertical-align: middle; margin: 0 2px; object-fit: contain;" alt="">';
            },
            $text
        );
        
        $text = str_replace(['&nbsp;', "\xc2\xa0"], ' ', $text);
        $text = trim(preg_replace('/\s+/', ' ', $text));
    } else {
        $text = mb_substr($text, 0, 60);
        if (mb_strlen($msg['message'] ?? '') > 60) $text .= '...';
    }

    return ['text' => $text];
}

// ============================================================
// Список закреплённых ключей для Alpine store
// ============================================================
$pinnedKeys = array_values(array_map(
    fn($c) => $c['pin_key'],
    array_filter($chats, fn($c) => !empty($c['is_pinned']))
));

$pageTitle = 'Сообщения';
$extraCss = '/assets/css/chats.css';
require_once __DIR__ . '/../includes/header.php';

echo '<link rel="stylesheet" href="/assets/css/reviews.css">';
?>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.store('chatPins', {
        pins: <?= json_encode($pinnedKeys, JSON_UNESCAPED_UNICODE) ?>,
        has(key) { return this.pins.indexOf(key) !== -1; },
        add(key) { if (!this.has(key)) this.pins.push(key); },
        remove(key) {
            const i = this.pins.indexOf(key);
            if (i !== -1) this.pins.splice(i, 1);
        }
    });
});
</script>

<script>
window.__ICON_PEN__ = <?= json_encode(icon('pen'), JSON_UNESCAPED_UNICODE) ?>;
</script>

<section class="mx-auto px-4 sm:px-6 py-8"
         x-data="{ newChatModal: false, searchQuery: '' }">
<div class="lg:max-w-6xl lg:mx-auto">
    <h1 class="text-3xl font-extrabold text-gray-800 mb-6 text-center inline-flex items-center justify-center gap-2 w-full" data-aos="fade-up">
        <?= icon('chats') ?>
        <span>Сообщения</span>
    </h1>

    <div class="flex justify-between items-center mb-4">
        <button type="button" @click="newChatModal = true"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-500 text-white font-bold hover:bg-amber-600 transition shadow-md">
            <?= icon('plus') ?>
            <span>Новый чат</span>
        </button>
        <div class="text-sm text-gray-500">Закреплённые вверху</div>
    </div>

    <?php if (empty($chats)): ?>
        <div class="text-center py-20" data-aos="fade-up">
            <div class="mb-4 flex justify-center">
                <span class="gc-icon-xl" style="display:inline-flex;width:4em;height:4em">
                    <?= icon('chats', 'gc-icon-xl', 4) ?>
                </span>
            </div>
            <p class="text-gray-400 text-lg">У вас пока нет сообщений</p>
            <a href="<?= url('friends') ?>" class="inline-block mt-4 px-6 py-3 rounded-xl bg-amber-100 text-amber-700 font-bold hover:bg-amber-200 transition">Найти собеседника</a>
        </div>
    <?php else: ?>
        <div class="space-y-2" data-aos="fade-up">
            <?php foreach ($chats as $chat):
                if (!$chat['user']) continue;
                $isGroup = !empty($chat['group_id']);
                $chatUrl = $isGroup
                    ? '/chat/group/' . (int)$chat['group_id']
                    : '/chat/' . rawurlencode($chat['counterpart_email']);
                $pinKey = $chat['pin_key'];
                $preview = lastMessagePreview($chat['last_message']);
                $isUnread = !$isGroup && $chat['last_message'] && $chat['last_message']['is_read'] == 0 && $chat['last_message']['sender_email'] != $userEmail;
            ?>
<div class="group relative flex items-center gap-4 p-4 rounded-2xl bg-white shadow-md hover:shadow-lg transition"
     x-data="{ showMenu: false }"
     :class="$store.chatPins.has('<?= e($pinKey) ?>') ? 'border-amber-200 ring-1 ring-amber-300' : ''">

    <!-- Бейдж закрепления -->
    <div x-show="$store.chatPins.has('<?= e($pinKey) ?>')"
         x-cloak
         class="absolute -top-2 left-4 z-10">
        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-400 text-amber-900 shadow-sm">
            <?= icon('pin') ?>
        </span>
    </div>

    <!-- Аватар -->
    <a href="<?= $chatUrl ?>" class="shrink-0">
        <?php if ($isGroup): ?>
            <span class="w-14 h-14 rounded-full bg-amber-100 ring-2 ring-amber-200 flex items-center justify-center overflow-hidden" aria-hidden="true">
                <?= icon('friends') ?>
            </span>
        <?php else: ?>
            <?= render_avatar(
                $chat['user'],
                'w-14 h-14 rounded-full object-cover ring-2 ring-amber-200',
                'w-14 h-14 rounded-full bg-amber-100 ring-2 ring-amber-200 flex items-center justify-center text-amber-600',
                'text-2xl'
            ) ?>
        <?php endif; ?>
    </a>

    <!-- Основная зона -->
    <a href="<?= $chatUrl ?>" class="flex-1 min-w-0">
        <div class="flex items-center justify-between">
            <span class="font-bold text-gray-800 truncate">
                <?= e($chat['user']['display_name'] ?? $chat['counterpart_email']) ?>
            </span>
            <span class="text-xs text-gray-400 shrink-0 ml-2">
                <?= !empty($chat['last_msg_time']) ? date('d.m H:i', strtotime($chat['last_msg_time'])) : '' ?>
            </span>
        </div>

        <div class="flex items-center justify-between mt-1">
            <span class="text-sm truncate inline-flex items-center gap-1 inline-emoji-preview <?= $isUnread ? 'font-bold text-gray-700' : 'text-gray-500' ?>">
                <?php if (!empty($preview['prefix'])): ?>
                    <span class="shrink-0 inline-flex items-center"><?= $preview['prefix'] ?></span>
                <?php endif; ?>
                <span><?= $preview['text'] ?></span>
            </span>
            <?php if ($chat['unread_count'] > 0): ?>
                <span class="inline-flex items-center justify-center min-w-[22px] h-[22px] px-1.5 rounded-full bg-rose-500 text-white text-xs font-bold shrink-0 ml-2">
                    <?= $chat['unread_count'] ?>
                </span>
            <?php endif; ?>
        </div>
    </a>

    <!-- Меню «⋮» -->
    <div class="relative shrink-0">
        <button @click="showMenu = !showMenu" type="button"
                class="w-10 h-10 rounded-xl hover:bg-gray-100 transition flex items-center justify-center"
                title="Меню" aria-label="Меню чата">
            <?= icon('dots') ?>
        </button>

        <div x-show="showMenu" @click.outside="showMenu = false"
             x-cloak
             class="absolute right-0 mt-2 w-52 bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-50">

            <!-- Закрепить/Открепить -->
            <button type="button" 
                    @click="togglePin('<?= e($pinKey) ?>', $el.closest('.group')); showMenu = false" 
                    class="w-full px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-amber-50 transition inline-flex items-center gap-2">
                <?= icon('pin') ?>
                <span x-text="$store.chatPins.has('<?= e($pinKey) ?>') ? 'Открепить' : 'Закрепить'">Закрепить</span>
            </button>

            <?php if (!$isGroup): ?>
                <button type="button" 
                        @click="clearChat('<?= e($chat['counterpart_email']) ?>'); showMenu = false" 
                        class="w-full px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-amber-50 transition inline-flex items-center gap-2">
                    <?= icon('clear') ?>
                    <span>Очистить чат</span>
                </button>

                <button type="button" 
                        @click="deleteChat('<?= e($chat['counterpart_email']) ?>', $el.closest('.group')); showMenu = false" 
                        class="w-full px-4 py-2.5 text-left text-sm text-rose-600 hover:bg-rose-50 transition inline-flex items-center gap-2">
                    <?= icon('trash') ?>
                    <span>Удалить чат</span>
                </button>
            <?php else: ?>
                <a href="<?= $chatUrl ?>" 
                   class="w-full px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-amber-50 transition inline-flex items-center gap-2">
                    <?= icon('chats') ?>
                    <span>Открыть чат</span>
                </a>
                <button type="button" 
                        onclick="leaveGroupFromList(<?= (int)$chat['group_id'] ?>)" 
                        class="w-full px-4 py-2.5 text-left text-sm text-rose-600 hover:bg-rose-50 transition inline-flex items-center gap-2">
                    <?= icon('exit') ?>
                    <span>Покинуть группу</span>
                </button>
                <?php if (!empty($chat['group_creator_email']) && $chat['group_creator_email'] === $userEmail): ?>
                    <button type="button" 
                            onclick="deleteGroupFromList(<?= (int)$chat['group_id'] ?>)" 
                            class="w-full px-4 py-2.5 text-left text-sm text-rose-600 hover:bg-rose-50 transition inline-flex items-center gap-2">
                        <?= icon('trash') ?>
                        <span>Удалить группу</span>
                    </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
</div>

    <!-- Модальное окно: создание чата -->
    <div x-show="newChatModal"
         x-transition.opacity
         @keydown.escape.window="newChatModal = false"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">

        <div @click.outside="newChatModal = false"
             class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden max-h-[90vh] flex flex-col"
             x-data="{
                 isGroup: false,
                 selected: [],
                 toggle(email) {
                     if (this.isGroup) {
                         let i = this.selected.indexOf(email);
                         if (i > -1) { this.selected.splice(i, 1); }
                         else { this.selected.push(email); }
                     } else {
                         this.selected = [email];
                     }
                 },
                 isSelected(email) { return this.selected.includes(email); },
                 get canCreate() {
                     return this.isGroup ? this.selected.length >= 2 : this.selected.length === 1;
                 },
                 createChat() {
                     if (!this.canCreate) return;
                     if (this.isGroup) {
                         createGroupChat(this.selected);
                     } else {
                         window.location.href = '/chat/' + encodeURIComponent(this.selected[0]);
                     }
                 }
             }">

            <div class="flex items-center justify-between p-4 border-b bg-gray-50 shrink-0">
                <h2 class="text-xl font-bold text-gray-800">Новый чат</h2>
                <button type="button" @click="newChatModal = false" 
                        class="p-2 rounded-full hover:bg-gray-200 inline-flex items-center justify-center"
                        aria-label="Закрыть">
                    <?= icon('close') ?>
                </button>
            </div>

            <div class="p-4 border-b shrink-0">
                <div class="flex gap-2">
                    <button type="button" @click="isGroup = false; selected = []"
                            :class="!isGroup ? 'bg-amber-100 border-amber-400 text-amber-700' : 'bg-white border-gray-300'"
                            class="flex-1 px-3 py-2 rounded-lg text-sm font-medium border transition">
                        Личный чат
                    </button>
                    <button type="button" @click="isGroup = true; selected = []"
                            :class="isGroup ? 'bg-amber-100 border-amber-400 text-amber-700' : 'bg-white border-gray-300'"
                            class="flex-1 px-3 py-2 rounded-lg text-sm font-medium border transition">
                        Групповой чат
                    </button>
                </div>
            </div>

            <div class="p-4 border-b shrink-0">
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none inline-flex items-center">
                        <?= icon('search') ?>
                    </span>
                    <input type="text" x-model="searchQuery" placeholder="Поиск пользователей..."
                           class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 outline-none focus:border-amber-300 text-sm">
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-4 space-y-2">
                <?php foreach ($users as $u): ?>
                <div x-show="!searchQuery || '<?= e($u['display_name'] ?? '') ?>'.toLowerCase().includes(searchQuery.toLowerCase()) || '<?= e($u['email']) ?>'.toLowerCase().includes(searchQuery.toLowerCase())"
                     @click="toggle('<?= e($u['email']) ?>')"
                     :class="isSelected('<?= e($u['email']) ?>') ? 'ring-2 ring-amber-400 bg-amber-50' : 'bg-gray-50 hover:bg-white border border-transparent hover:border-gray-200'"
                     class="flex items-center gap-3 p-3 rounded-xl cursor-pointer transition">

                    <div class="shrink-0">
                        <?= render_avatar(
                            $u,
                            'w-10 h-10 rounded-full object-cover border border-gray-200',
                            'w-10 h-10 rounded-full bg-amber-100 border border-gray-200 flex items-center justify-center text-amber-600',
                            'text-lg'
                        ) ?>
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-gray-800 truncate"><?= e($u['display_name']) ?></div>
                        <div class="text-xs text-gray-500 truncate"><?= e($u['email']) ?></div>
                    </div>

                    <div x-show="isSelected('<?= e($u['email']) ?>')" x-cloak
                         class="w-6 h-6 rounded-full bg-amber-500 flex items-center justify-center text-white shrink-0">
                        <?= icon('check') ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div x-show="isGroup" x-cloak class="p-4 bg-amber-50 border-t shrink-0">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 rounded-full bg-amber-200 flex items-center justify-center">
                        <?= icon('friends') ?>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-800 text-sm">Новая группа</h3>
                        <p class="text-xs text-gray-500">Участников: <span x-text="selected.length">0</span></p>
                    </div>
                </div>
                <p class="text-xs text-gray-500" x-show="selected.length < 2">Добавьте минимум 2 участников.</p>
            </div>

            <div class="p-4 border-t flex justify-end gap-3 shrink-0">
                <button type="button" @click="newChatModal = false"
                        class="px-4 py-2 rounded-lg text-sm text-gray-600 border border-gray-300 hover:bg-gray-50">
                    Отмена
                </button>
                <button type="button" @click="createChat()" :disabled="!canCreate"
                        class="px-5 py-2 rounded-lg text-sm font-bold bg-amber-500 text-white hover:bg-amber-600 disabled:opacity-50 disabled:cursor-not-allowed transition">
                    Создать
                </button>
            </div>
        </div>
    </div>
</section>

<style>
    [x-cloak] { display: none !important; }

    /* Выравнивание SVG-иконок */
    .gc-icon, .gc-icon-xs, .gc-icon-sm, .gc-icon-xl {
        display: inline-block;
        vertical-align: -0.125em;
        flex-shrink: 0;
    }

    /* На десктопе секция растягивается на всю правую область */
    @media (min-width: 1024px) {
        html body > section.max-w-4xl {
            max-width: none !important;
            margin-left: 16rem !important;
            margin-right: 0 !important;
            padding-left: 0.5rem !important;
            padding-right: 0.5rem !important;
        }
    }
    /* Выравнивание эмодзи в списке чатов */
.inline-emoji-preview img {
    display: inline-block;
    width: 1.2em !important;
    height: 1.2em !important;
    vertical-align: -0.2em; /* Чуть опускаем, чтобы было ровно с текстом */
    margin: 0 2px;
    object-fit: contain;
}
</style>

<script>
window.__USER_EMAIL__ = <?= json_encode($userEmail, JSON_UNESCAPED_UNICODE) ?>;

function apiCall(body) {
    return fetch('/api/chats.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    }).then(function(r) { return r.json(); });
}

/**
 * Универсальное закрепление/открепление.
 * pinKey: "email@example.com" или "group:5"
 */
function togglePin(pinKey, cardEl) {
    apiCall({ action: 'pin', pin_key: pinKey }).then(function(data) {
        if (!data.success) {
            console.error('Ошибка API pin:', data.error);
            return;
        }

        var isNowPinned = Boolean(data.pinned);

        if (window.Alpine && Alpine.store('chatPins')) {
            var store = Alpine.store('chatPins');
            if (isNowPinned) store.add(pinKey);
            else store.remove(pinKey);
        }

        if (isNowPinned && cardEl) {
            var container = cardEl.parentElement;
            if (container) container.insertBefore(cardEl, container.firstChild);
        }
    }).catch(function(err) {
        console.error(err);
        alert('Ошибка при закреплении чата');
    });
}

function clearChat(email) {
    if (!confirm('Очистить чат с ' + email + '? Все сообщения будут удалены.')) return;
    apiCall({ action: 'clear', email: email }).then(function(data) {
        if (data.success) location.reload();
        else alert(data.error || 'Ошибка очистки чата');
    });
}

function deleteChat(email, cardEl) {
    if (!confirm('Удалить чат с ' + email + '? Все сообщения будут потеряны безвозвратно.')) return;
    apiCall({ action: 'delete', email: email }).then(function(data) {
        if (data.success) {
            cardEl.style.transition = 'opacity 0.3s, transform 0.3s';
            cardEl.style.opacity = '0';
            cardEl.style.transform = 'translateX(-20px)';
            setTimeout(function() { cardEl.remove(); }, 300);
        } else {
            alert(data.error || 'Ошибка удаления чата');
        }
    });
}

function createGroupChat(users) {
    apiCall({ action: 'create_group', users: users }).then(function(data) {
        if (data.success && data.group_id) {
            window.location.href = '/chat/group/' + data.group_id;
        } else {
            alert(data.error || 'Ошибка при создании группы');
        }
    }).catch(function() {
        alert('Ошибка при создании группы');
    });
}
</script>

<script>
function leaveGroupFromList(groupId) {
    if (!confirm('Покинуть группу?')) return;
    fetch('/api/chat_group.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'leave', group_id: groupId })
    }).then(r => r.json()).then(d => {
        if (d.success) window.location.reload();
    });
}

function deleteGroupFromList(groupId) {
    if (!confirm('Удалить группу? Все сообщения будут потеряны.')) return;
    fetch('/api/chat_group.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete_group', group_id: groupId })
    }).then(r => r.json()).then(d => {
        if (d.success) window.location.reload();
    });
}
document.addEventListener('DOMContentLoaded', function() {
    // Проходим по всем карточкам чатов на странице
    document.querySelectorAll('.group.relative.flex.items-center.gap-4').forEach(function(card) {
        var link = card.querySelector('a[href^="/chat/"]');
        if (!link) return;

        var href = link.getAttribute('href');
        var draftKey = null;

        // Определяем ключ черновика (совпадает с тем, что пишет chat.js)
        if (href.includes('/chat/group/')) {
            var groupId = href.split('/').pop();
            draftKey = 'gc_chat_draft_group_' + groupId;
        } else if (href.startsWith('/chat/')) {
            // Извлекаем email из URL (декодируем на случай спецсимволов)
            var email = decodeURIComponent(href.replace('/chat/', ''));
            draftKey = 'gc_chat_draft_' + email;
        }

        if (!draftKey) return;

        // Проверяем наличие черновика в localStorage
        var draft = localStorage.getItem(draftKey);

        if (draft && draft.trim() !== '') {
            // Ищем блок с текстом последнего сообщения
            var previewSpan = card.querySelector('a.flex-1 .text-sm');
            
            if (previewSpan) {
                // 1. Извлекаем чистый текст (чтобы понять, есть ли там что-то кроме эмодзи)
                var tempDiv = document.createElement('div');
                tempDiv.innerHTML = draft;
                var textPreview = tempDiv.textContent || tempDiv.innerText || '';
                
                // 2. Проверяем, есть ли в черновике картинки (эмодзи)
                var hasEmoji = draft.includes('<img');
                var isOnlyEmoji = textPreview.trim() === '' && hasEmoji;
                
                // Обрезаем текст, если он слишком длинный
                if (textPreview.length > 40) textPreview = textPreview.substring(0, 40) + '...';
                
                // 3. Создаем бейдж "Черновик"
                var draftLabel = document.createElement('span');
                draftLabel.className = 'inline-flex items-center gap-1 text-xs font-bold text-rose-500 bg-rose-50 px-1.5 py-0.5 rounded ml-2 shrink-0';
                draftLabel.innerHTML = (window.__ICON_PEN__ || '') + ' Черновик';

                // Очищаем контейнер
                previewSpan.innerHTML = '';
                previewSpan.appendChild(draftLabel);
                
                // 4. Выводим сам текст черновика
                var textNode = document.createElement('span');
                textNode.className = 'truncate text-gray-500 inline-flex items-center gap-1';
                
                if (hasEmoji) {
                    // Если есть эмодзи, нам нужно отрендерить HTML черновика, 
                    // но заменить оригинальные <img> на маленькие, чтобы они влезли в строку.
                    var formattedHtml = draft;
                    
                    // Используем DOMParser для безопасного изменения HTML
                    var parser = new DOMParser();
                    var doc = parser.parseFromString(draft, 'text/html');
                    var imgs = doc.querySelectorAll('img');
                    
                    imgs.forEach(function(img) {
                        img.style.width = '1.3em';
                        img.style.height = '1.3em';
                        img.style.verticalAlign = 'middle';
                        img.style.margin = '0 2px';
                        img.style.objectFit = 'contain';
                        img.style.display = 'inline-block';
                    });
                    
                    // Если в черновике ТОЛЬКО эмодзи (нет текста), просто выводим их
                    if (isOnlyEmoji) {
                        textNode.innerHTML = doc.body.innerHTML;
                    } else {
                        // Если есть и текст, и эмодзи, выводим HTML
                        textNode.innerHTML = doc.body.innerHTML;
                    }
                } else {
                    // Если эмодзи нет, просто выводим текст
                    textNode.textContent = ' ' + textPreview;
                }
                
                previewSpan.appendChild(textNode);
                
                // Красим карточку
                card.classList.add('bg-amber-50');
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>