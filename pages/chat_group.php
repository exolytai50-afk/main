<?php
$__showMobileNav = false;
require_once __DIR__ . '/../config/config.php';
require_login();

$user = current_user();
$userEmail = $user['email'];

$groupId = (int)($_GET['group_id'] ?? 0);

if ($groupId <= 0) {
    http_response_code(400);
    $pageTitle = 'Ошибка';
    require_once __DIR__ . '/../includes/header.php';
    echo '<link rel="stylesheet" href="/assets/css/reviews.css">';
    echo '<div class="text-center py-20"><h1>Некорректный ID группы</h1><a href="/chats">Вернуться к чатам</a></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$membership = db_one(
    "SELECT group_id FROM chat_group_members
     WHERE group_id = ? AND user_email COLLATE utf8mb4_unicode_ci = ?",
    [$groupId, $userEmail]
);

if (!$membership) {
    http_response_code(403);
    $pageTitle = 'Ошибка';
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="text-center py-20"><h1>Вы не участник этой группы</h1><a href="/chats">Вернуться к чатам</a></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$group = db_one("SELECT * FROM chat_groups WHERE id = ?", [$groupId]);
if (!$group) {
    http_response_code(404);
    $pageTitle = 'Ошибка';
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="text-center py-20"><h1>Группа не найдена</h1><a href="/chats">Вернуться к чатам</a></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$members = db_all(
    "SELECT m.user_email, u.display_name, u.avatar_emoji, u.avatar_url, u.gender
     FROM chat_group_members m
     JOIN users u ON u.email COLLATE utf8mb4_unicode_ci = m.user_email
     WHERE m.group_id = ?",
    [$groupId]
);

$isCreator = ($group['creator_email'] === $userEmail);

try {
    db_exec("CREATE TABLE IF NOT EXISTS `chat_votes` (
        `id` int NOT NULL AUTO_INCREMENT,
        `target_type` varchar(20) NOT NULL,
        `target_id` int NOT NULL,
        `user_email` varchar(255) NOT NULL,
        `vote` tinyint NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `vote_unique` (`target_type`, `target_id`, `user_email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {
    error_log('Chat votes table error: ' . $e->getMessage());
}

$stickerPacks = [];
try {
    $stickerPacks = db_all("SELECT sp.*, (SELECT image_url FROM stickers s WHERE s.pack_id = sp.id ORDER BY s.sort_order ASC LIMIT 1) as first_sticker FROM sticker_packs sp WHERE sp.is_active = 1 ORDER BY sp.sort_order ASC");
    error_log('chat_group: stickerPacks count = ' . count($stickerPacks));
} catch (Throwable $e) {
    error_log('chat_group: stickerPacks ERROR = ' . $e->getMessage());
    $stickerPacks = [];
}

$allStickers = [];
try {
    $allStickers = db_all("SELECT s.* FROM stickers s JOIN sticker_packs sp ON sp.id = s.pack_id WHERE sp.is_active = 1 ORDER BY s.pack_id, s.sort_order ASC");
} catch (Throwable $e) { $allStickers = []; }

$stickersByPack = [];
foreach ($allStickers as $s) { $stickersByPack[$s['pack_id']][] = $s; }

$emojiPacks = [];
try {
    $emojiPacks = db_all("SELECT ep.*, (SELECT image_url FROM emoji_images ei WHERE ei.pack_id = ep.id ORDER BY ei.sort_order ASC LIMIT 1) as first_emoji FROM emoji_packs ep WHERE ep.is_active = 1 ORDER BY ep.sort_order ASC");
} catch (Throwable $e) { $emojiPacks = []; }

$allEmojis = [];
try {
    $allEmojis = db_all("SELECT ei.* FROM emoji_images ei JOIN emoji_packs ep ON ep.id = ei.pack_id WHERE ep.is_active = 1 ORDER BY ei.pack_id, ei.sort_order ASC");
} catch (Throwable $e) { $allEmojis = []; }

$emojisByPack = [];
foreach ($allEmojis as $em) { $emojisByPack[$em['pack_id']][] = $em; }

$pageTitle = e($group['name']) . ' — Групповой чат';
$extraCss = '/assets/css/chats.css';
$__bootVars = [
    '__USER_EMAIL__'        => $userEmail,
    '__GROUP_ID__'          => (int)$groupId,
    '__STICKER_PACKS__'     => $stickerPacks,
    '__STICKERS_BY_PACK__'  => $stickersByPack,
    '__EMOJI_PACKS__'       => $emojiPacks,
    '__EMOJIS_BY_PACK__'    => $emojisByPack,
    '__ICON_PALETTE__'      => icon('palette'),
    '__ICON_IMAGE__'        => icon('image'),
];
require_once __DIR__ . '/../includes/header.php';
?>
<style>
body, html { overflow: hidden !important; height: 100%; margin: 0; padding: 0; }
.chat-page-wrapper {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: var(--bottom-h, 0px);
    display: flex;
    flex-direction: column;
    max-width: 48rem;
    margin: 0 auto;
    overflow: hidden;
    z-index: 40;
}

@media (min-width: 1024px) {
    .chat-page-wrapper {
        left: max(0px, calc(50vw - 700px + 16rem));
        right: max(0px, calc(50vw - 700px));
        max-width: none;
        margin: 0;
        padding-left: 1rem;
        padding-right: 1rem;
        padding-top: 0.5rem;
        padding-bottom: 0.5rem;
    }
}

footer, .footer, #footer {
    position: fixed !important;
    bottom: 0 !important;
    left: 0 !important;
    right: 0 !important;
    z-index: 55 !important;
}

@media (min-width: 1024px) {
    footer, .footer, #footer {
        left:  max(0px, calc(50vw - 700px + 16rem)) !important;
        right: max(0px, calc(50vw - 700px)) !important;
    }
}

nav[x-data] { z-index: 60 !important; }

.chat-header-fixed { flex: 0 0 auto; padding: 0.75rem 1rem; margin-top: 0.5rem; border-radius: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.08); display: flex; align-items: center; gap: 0.75rem; z-index: 50; }
.chat-messages-scroll { flex: 1 1 auto; overflow-y: auto; overflow-x: hidden; padding: 1rem; margin: 0.5rem 0; border-radius: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.08); min-height: 0; }
.chat-input-fixed { flex: 0 0 auto; padding: 0.75rem 1rem; margin-bottom: 0.5rem; border-radius: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.08); position: relative; }

@media (max-width: 1023.98px) {
    .chat-input-fixed {
        margin-bottom: <?= !empty($__showMobileNav) ? '5rem' : '0.5rem' ?>;
    }
}

.chat-messages-scroll::-webkit-scrollbar { width: 6px; }
.chat-messages-scroll::-webkit-scrollbar-track { background: transparent; }
.chat-messages-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 3px; }
.chat-messages-scroll::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
[contenteditable][data-placeholder]:empty::before { content: attr(data-placeholder); color: #9ca3af; pointer-events: none; }
.chat-message-content img { display: inline-block; max-width: 100%; height: auto; vertical-align: middle; }
.chat-message-content p { margin: 0; line-height: 1.4; }
.chat-message-content br { display: none; }
.inline-emoji { display: inline-block; width: 2em; height: 2em; vertical-align: middle; margin: 0 2px; object-fit: contain; }
.chat-image { max-width: 100%; max-height: 300px; border-radius: 12px; cursor: pointer; transition: transform 0.2s; }
.chat-image:hover { transform: scale(1.02); }
.image-preview { max-width: 100%; max-height: 200px; border-radius: 8px; object-fit: contain; }

.vote-btn {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 2px 6px; border-radius: 8px;
    font-size: 11px; transition: all 0.15s ease;
    cursor: pointer; border: none; background: transparent;
}
.vote-btn:hover { background: #f3f4f6; }
.vote-icon-wrapper {
    display: flex; align-items: center; justify-content: center;
    transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.vote-btn.anim-click .vote-icon-wrapper {
    animation: thumb-pop 0.35s ease;
}
@keyframes thumb-pop {
    0%   { transform: scale(1); }
    40%  { transform: scale(1.3); }
    70%  { transform: scale(0.95); }
    100% { transform: scale(1); }
}
.vote-btn-active-like { color: #16a34a; background: #dcfce7; }
.vote-btn-active-like:hover { background: #bbf7d0; }
.vote-btn-active-dislike { color: #dc2626; background: #fee2e2; }
.vote-btn-active-dislike:hover { background: #fecaca; }

/* Выравнивание SVG-иконок */
.gc-icon, .gc-icon-xs, .gc-icon-sm, .gc-icon-xl {
    display: inline-block;
    vertical-align: -0.125em;
    flex-shrink: 0;
}
/* Фикс прокрутки для flex-контейнеров */
.flex-1.min-h-0 { min-height: 0; }
/* Анимация появления бейджа */
@keyframes bounce-short {
    0%, 100% { transform: translate(-50%, 0); }
    50% { transform: translate(-50%, -8px); }
}
.animate-bounce-short {
    animation: bounce-short 1.5s ease-in-out infinite;
}
</style>
<script>
window.__USER_EMAIL__       = <?= json_encode($userEmail, JSON_UNESCAPED_UNICODE) ?>;
window.__GROUP_ID__         = <?= json_encode((int)$groupId) ?>;
window.__STICKER_PACKS__    = <?= json_encode($stickerPacks, JSON_UNESCAPED_UNICODE) ?: '[]' ?>;
window.__STICKERS_BY_PACK__ = <?= json_encode($stickersByPack, JSON_UNESCAPED_UNICODE) ?: '{}' ?>;
window.__EMOJI_PACKS__      = <?= json_encode($emojiPacks, JSON_UNESCAPED_UNICODE) ?: '[]' ?>;
window.__EMOJIS_BY_PACK__   = <?= json_encode($emojisByPack, JSON_UNESCAPED_UNICODE) ?: '{}' ?>;
window.__ICON_PALETTE__     = <?= json_encode(icon('palette'), JSON_UNESCAPED_UNICODE) ?>;
window.__ICON_IMAGE__       = <?= json_encode(icon('image'), JSON_UNESCAPED_UNICODE) ?>;
</script>
<div class="chat-page-wrapper">
    <section class="flex flex-col h-full overflow-hidden"
         x-data="chatEngine({
             isGroup: true,
             groupId: <?= (int)$groupId ?>,
             myEmail: window.__USER_EMAIL__
        })"
         x-init="init()">

        <!-- Шапка -->
        <div class="chat-header-fixed shrink-0">
            <a href="/chats" class="text-gray-400 hover:text-gray-600 shrink-0 inline-flex items-center justify-center w-8 h-8" title="Назад" aria-label="Назад">
                <?= icon('back') ?>
            </a>

            <div class="w-10 h-10 rounded-full bg-amber-200 ring-2 ring-amber-200 flex items-center justify-center shrink-0">
                <?= icon('friends') ?>
            </div>

            <div class="flex-1 min-w-0">
                <span class="font-bold text-gray-800 truncate block"><?= e($group['name']) ?></span>
                <span class="text-xs text-gray-400"><?= count($members) ?> участников</span>
            </div>

            <div class="relative shrink-0" x-data="{ showMembers: false }">
                <button @click="showMembers = !showMembers" type="button"
                        class="w-10 h-10 rounded-xl hover:bg-gray-100 transition flex items-center justify-center text-sm font-bold text-gray-500"
                        title="Участники" aria-label="Участники">
                    <?= count($members) ?>
                </button>

                <div x-show="showMembers" @click.outside="showMembers = false"
                     style="display:none"
                     class="absolute right-0 mt-2 w-56 bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-50 max-h-72 overflow-y-auto">
                    <div class="px-4 py-1 text-xs font-bold text-gray-400 uppercase">Участники</div>
                    <?php foreach ($members as $m): ?>
                        <div class="flex items-center gap-2 px-4 py-2">
                            <?= render_avatar(
                                $m,
                                'w-6 h-6 rounded-full object-cover',
                                'w-6 h-6 rounded-full bg-amber-100 flex items-center justify-center text-amber-600',
                                'text-xs'
                            ) ?>
                            <span class="text-sm text-gray-700 truncate"><?= e($m['display_name']) ?></span>
                            <?php if ($m['user_email'] === $group['creator_email']): ?>
                                <span class="text-amber-500 shrink-0 inline-flex items-center justify-center" title="Создатель группы">
                                    <?= icon('crown') ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="relative shrink-0" x-data="{ showMenu: false }">
                <button @click="showMenu = !showMenu" type="button"
                        class="w-10 h-10 rounded-xl hover:bg-gray-100 transition flex items-center justify-center"
                        title="Меню" aria-label="Меню группы">
                    <?= icon('dots') ?>
                </button>

                <div x-show="showMenu" @click.outside="showMenu = false"
                     style="display:none"
                     class="absolute right-0 mt-2 w-52 bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-50">
                    <button @click="leaveGroup(); showMenu = false" 
                            class="w-full px-4 py-2.5 text-left text-sm text-rose-600 hover:bg-rose-50 transition inline-flex items-center gap-2">
                        <?= icon('exit') ?>
                        <span>Покинуть группу</span>
                    </button>
                    <?php if ($isCreator): ?>
                        <button @click="deleteGroup(); showMenu = false" 
                                class="w-full px-4 py-2.5 text-left text-sm text-rose-600 hover:bg-rose-50 transition inline-flex items-center gap-2">
                            <?= icon('trash') ?>
                            <span>Удалить группу</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Сообщения -->
        <div class="chat-messages-scroll space-y-3 relative" 
            x-ref="messagesContainer" 
            @scroll="handleScroll($event)">
            <template x-for="msg in messages" :key="msg.id">
                <div :class="msg.sender_email === myEmail ? 'flex justify-end' : 'flex justify-start'">
                    <div class="group relative flex flex-col max-w-[80%]"
                         :class="msg.sender_email === myEmail ? 'items-end' : 'items-start'">

                        <div x-show="msg.sender_email !== myEmail" class="flex items-center gap-1.5 mb-1 ml-1">
                            <template x-if="msg.avatar_url">
                                <img :src="msg.avatar_url" class="w-5 h-5 rounded-full object-cover" alt="" loading="lazy" decoding="async">
                            </template>
                            <template x-if="!msg.avatar_url">
                                <span class="w-5 h-5 rounded-full bg-amber-100 flex items-center justify-center overflow-hidden"
                                      x-html="window.getAvatarSvg(msg.gender)"></span>
                            </template>
                            <span class="text-xs font-semibold text-gray-500" x-text="msg.display_name"></span>
                        </div>

                        <template x-if="msg.quoted_text || msg.quoted_image_url || msg.quoted_sticker_url">
                            <div class="mb-1 max-w-full px-3 py-1.5 bg-gray-200/60 rounded-lg border-l-2 border-gray-400 text-xs break-words">
                                <span class="font-semibold text-gray-600 block" 
                                      x-text="msg.quoted_sender_email === myEmail ? 'Вы' : (msg.quoted_author || 'Собеседник')"></span>
                                <template x-if="msg.quoted_sticker_url">
                                    <span class="inline-flex items-center gap-1 text-gray-500">
                                        <?= icon('palette') ?> <span>Стикер</span>
                                    </span>
                                </template>
                                <template x-if="msg.quoted_image_url">
                                    <span class="inline-flex items-center gap-1 text-gray-500">
                                        <?= icon('image') ?> <span>Фото</span>
                                    </span>
                                </template>
                                <template x-if="msg.quoted_text">
                                    <div class="text-gray-500 chat-message-content" x-html="renderMessage(msg.quoted_text)"></div>
                                </template>
                            </div>
                        </template>

                        <div x-show="msg.image_url && msg.message && !msg.sticker_url" class="flex flex-col max-w-full">
                            <img :src="msg.image_url" @click="openImageModal(msg.image_url)" class="chat-image block cursor-pointer" alt="Изображение" loading="lazy" decoding="async">
                            <div :class="msg.sender_email === myEmail ? 'bg-amber-50 text-amber-800' : 'bg-gray-50 text-gray-600'"
                                 class="mt-1 px-3 py-1.5 rounded-lg text-sm break-words italic">
                                <div class="chat-message-content" x-html="renderMessage(msg.message)"></div>
                            </div>
                            <div class="text-[10px] opacity-60 flex items-center gap-1 justify-end mt-1">
                                <span x-show="msg.is_edited" class="italic">ред.</span>
                                <span x-text="formatTime(msg.created_at)"></span>
                            </div>
                        </div>

                        <div x-show="msg.message && !msg.sticker_url && !msg.image_url"
                             :class="msg.sender_email === myEmail ? 'bg-amber-100 text-amber-900' : 'bg-gray-100 text-gray-700'"
                             class="px-4 py-2.5 rounded-2xl text-sm break-words w-fit max-w-full">
                            <div class="w-full inline-block chat-message-content">
                                <div x-html="renderMessage(msg.message)"></div>
                            </div>
                            <div class="text-[10px] mt-1 opacity-60 flex items-center gap-1 justify-end">
                                <span x-show="msg.is_edited" class="italic">ред.</span>
                                <span x-text="formatTime(msg.created_at)"></span>
                            </div>
                        </div>

                        <div x-show="msg.sticker_url" class="max-w-full">
                            <img :src="msg.sticker_url" class="block w-48 h-48 object-contain" alt="Стикер" loading="lazy" decoding="async">
                            <div class="text-[10px] text-gray-400 mt-1 text-center" x-text="formatTime(msg.created_at)"></div>
                        </div>

                        <div x-show="msg.image_url && !msg.message && !msg.sticker_url" class="max-w-full">
                            <img :src="msg.image_url" @click="openImageModal(msg.image_url)" class="chat-image block cursor-pointer" alt="Изображение" loading="lazy" decoding="async">
                            <div class="text-[10px] text-gray-400 mt-1 text-center" x-text="formatTime(msg.created_at)"></div>
                        </div>

                        <div class="flex items-center gap-0.5 mt-1"
                             :class="msg.sender_email === myEmail ? 'justify-end' : 'justify-start'">
                            <button @click="vote(msg, 'group_message', msg.id, 1, $event)" type="button"
                                    :class="msg.my_vote === 1 ? 'vote-btn-active-like' : ''"
                                    class="vote-btn text-gray-400" style="font-size:11px; padding:2px 6px;">
                                <span class="vote-icon-wrapper"><?= icon('like') ?></span>
                                <span x-text="msg.like_count || ''"></span>
                            </button>
                            <button @click="vote(msg, 'group_message', msg.id, -1, $event)" type="button"
                                    :class="msg.my_vote === -1 ? 'vote-btn-active-dislike' : ''"
                                    class="vote-btn text-gray-400" style="font-size:11px; padding:2px 6px;">
                                <span class="vote-icon-wrapper"><?= icon('dislike') ?></span>
                                <span x-text="msg.dislike_count || ''"></span>
                            </button>
                        </div>

                        <div class="absolute -top-3 opacity-0 group-hover:opacity-100 transition flex gap-1 z-10"
                             :class="msg.sender_email === myEmail ? 'right-0' : 'left-0'">
                            <button @click="startQuote(msg)" type="button" 
                                    class="w-7 h-7 rounded-full bg-white shadow-md flex items-center justify-center hover:bg-gray-50" 
                                    title="Цитировать" aria-label="Цитировать">
                                <?= icon('quote') ?>
                            </button>
                            <button x-show="msg.sender_email === myEmail && msg.message && !msg.sticker_url && !msg.image_url" 
                                    @click="startEdit(msg)" type="button" 
                                    class="w-7 h-7 rounded-full bg-white shadow-md flex items-center justify-center hover:bg-gray-50" 
                                    title="Редактировать" aria-label="Редактировать">
                                <?= icon('pen') ?>
                            </button>
                            <button x-show="msg.sender_email === myEmail" 
                                    @click="deleteMessage(msg.id)" type="button" 
                                    class="w-7 h-7 rounded-full bg-white shadow-md flex items-center justify-center hover:bg-rose-50" 
                                    title="Удалить" aria-label="Удалить">
                                <?= icon('trash') ?>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
            <div x-show="!messages.length" class="text-center text-gray-400 py-10">Нет сообщений. Начните общение!</div>
        </div>
        
        <!-- Бейдж новых сообщений -->
        <div x-show="hasNewMessages" x-cloak
            class="absolute bottom-24 left-1/2 -translate-x-1/2 z-40 transition-all duration-300">
            <button @click="scrollToBottom(true)" 
                    class="px-4 py-2 rounded-full bg-amber-500 text-white font-bold text-sm shadow-lg hover:bg-amber-600 flex items-center gap-2 animate-bounce-short">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                <span x-text="newMessagesCount"></span>
                <span x-text="newMessagesCount === 1 ? 'новое' : 'новых'"></span>
            </button>
        </div>

        <!-- Область ввода -->
        <div class="chat-input-fixed shrink-0">

            <div x-show="imagePreview" style="display:none" class="px-4 py-2 bg-gray-50 border-t border-gray-100 mb-2 rounded-xl">
                <div class="flex items-center gap-2">
                    <img :src="imagePreview" class="image-preview" alt="Предпросмотр" loading="lazy" decoding="async">
                    <button @click="cancelImageUpload()" 
                            class="text-gray-400 hover:text-gray-600 inline-flex items-center justify-center w-6 h-6"
                            aria-label="Отменить загрузку">
                        <?= icon('close') ?>
                    </button>
                </div>
            </div>

            <div x-show="quotedMessage" style="display:none" class="px-4 py-2 bg-blue-50 border-t border-blue-100 mb-2 rounded-xl">
                <div class="flex items-start gap-2">
                    <div class="flex-1 px-3 py-1.5 bg-white/60 rounded-lg border-l-2 border-blue-300 text-xs">
                        <div class="font-semibold text-blue-700" x-text="quotedMessage ? getQuoteAuthorName(quotedMessage) : ''"></div>
                        <div class="text-gray-600 line-clamp-2" x-html="quotedMessage ? getQuotePreview(quotedMessage) : ''"></div>
                    </div>
                    <button @click="cancelQuote()" 
                            class="text-gray-400 hover:text-gray-600 shrink-0 inline-flex items-center justify-center w-6 h-6"
                            aria-label="Отменить цитату">
                        <?= icon('close') ?>
                    </button>
                </div>
            </div>

            <div x-show="editingMessageId" style="display:none" class="px-4 py-2 bg-amber-50 border-t border-amber-100 mb-2 rounded-xl flex items-center justify-between">
                <span class="text-xs text-amber-700 font-semibold">Редактирование сообщения</span>
                <button @click="cancelEdit()" class="text-xs text-gray-500 hover:text-gray-700">Отмена</button>
            </div>

            <!-- ===== ДЕСКТОП ввод ===== -->
            <div class="hidden sm:flex items-center gap-2">
                <button @click="showPanel = !showPanel; showPanelMobile = false;" type="button"
                        title="Смайлы и стикеры"
                        class="w-10 h-10 rounded-xl bg-amber-50 hover:bg-amber-100 transition flex items-center justify-center shrink-0"
                        aria-label="Смайлы и стикеры">
                    <?= icon('happy') ?>
                </button>
                <button x-show="!editingMessageId" @click="triggerImageUpload()" type="button" 
                        title="Загрузить изображение" 
                        class="w-10 h-10 rounded-xl bg-amber-50 hover:bg-amber-100 transition flex items-center justify-center shrink-0"
                        aria-label="Загрузить изображение">
                    <?= icon('image') ?>
                </button>
                <input type="file" x-ref="imageInput" accept="image/*" class="hidden" @change="handleImageUpload($event)">
                <div x-ref="chatInput" contenteditable="true" 
                     @input="onChatInput(); saveDraft();" 
                     @click="saveRange()"
                     @keyup="saveRange()"
                     @keydown.enter.prevent="onChatEnter($event)" 
                     @paste="onChatPaste($event)" 
                     class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 outline-none focus:border-amber-300 text-sm overflow-y-auto max-h-32" 
                     data-placeholder="Введите сообщение..."></div>
                <button x-show="!editingMessageId" @click="sendMessage()" :disabled="inputEmpty && !imageFile" 
                        class="px-4 py-2.5 rounded-xl bg-amber-300 text-white font-bold hover:bg-amber-400 transition disabled:opacity-50 shrink-0 inline-flex items-center justify-center"
                        aria-label="Отправить">
                    <?= icon('send') ?>
                </button>
                <button x-show="editingMessageId" @click="saveEdit()" :disabled="inputEmpty" 
                        class="px-4 py-2.5 rounded-xl bg-blue-500 text-white font-bold text-sm hover:bg-blue-600 transition disabled:opacity-50 shrink-0 inline-flex items-center justify-center"
                        aria-label="Сохранить">
                    <?= icon('check') ?>
                </button>
            </div>

            <!-- ===== МОБИЛЬНЫЙ ввод ===== -->
            <div class="flex flex-col gap-2 sm:hidden">
                <div x-ref="chatInputMobile" contenteditable="true" 
     @input="onChatInput(); saveDraft();"
     @click="saveRange(); showPanelMobile = false;"
     @keyup="saveRange()"
     @keydown.enter.prevent="onChatEnter($event)" 
     @paste="onChatPaste($event)"
     class="w-full px-4 py-2.5 rounded-xl border border-gray-200 outline-none focus:border-amber-300 text-sm overflow-y-auto max-h-32" 
     data-placeholder="Введите сообщение..."></div>
                <div class="flex items-center justify-between">
    <div class="flex gap-2">
        <!-- Кнопка переключения Эмодзи / Клавиатура -->
        
<button
    type="button"
    @mousedown.prevent
    @click="toggleEmojiPanelMobile()"
    class="w-10 h-10 rounded-xl bg-amber-50 hover:bg-amber-100 flex items-center justify-center shrink-0"
    :aria-label="showPanelMobile ? 'Открыть клавиатуру' : 'Открыть панель эмодзи'"
    :aria-expanded="showPanelMobile ? 'true' : 'false'"
>
    <template x-if="!showPanelMobile">
        <?= icon('happy') ?>
    </template>

    <template x-if="showPanelMobile">
        <?= icon('keyboard', 'gc-icon-sm') ?>
    </template>
</button>

        <!-- Кнопка загрузки изображения -->
        <button x-show="!editingMessageId" @click="triggerImageUpload()" type="button" 
                title="Загрузить изображение" 
                class="w-10 h-10 rounded-xl bg-amber-50 hover:bg-amber-100 transition flex items-center justify-center shrink-0"
                aria-label="Загрузить изображение">
            <?= icon('image') ?>
        </button>
        <input type="file" x-ref="imageInputMobile" accept="image/*" class="hidden" @change="handleImageUpload($event)">
    </div>

    <!-- Кнопка отправки -->
    <button x-show="!editingMessageId" @click="sendMessage()" :disabled="inputEmpty && !imageFile" 
            class="px-4 py-2.5 rounded-xl bg-amber-300 text-white font-bold hover:bg-amber-400 transition disabled:opacity-50 shrink-0 inline-flex items-center justify-center"
            aria-label="Отправить">
        <?= icon('send') ?>
    </button>

    <!-- Кнопка сохранения -->
    <button x-show="editingMessageId" @click="saveEdit()" :disabled="inputEmpty" 
            class="px-4 py-2.5 rounded-xl bg-blue-500 text-white font-bold text-sm hover:bg-blue-600 transition disabled:opacity-50 shrink-0 inline-flex items-center justify-center"
            aria-label="Сохранить">
        <?= icon('check') ?>
    </button>
</div>
            </div>

            <!-- ===== ПАНЕЛЬ СМАЙЛОВ И СТИКЕРОВ (общая для десктопа и мобилки) ===== -->
            <div x-show="showPanel || showPanelMobile" 
                 x-transition
                 @mousedown="isClickingPanel = true"
                 @mouseup="isClickingPanel = false"
                 @touchstart="isClickingPanel = true"
                 @touchend="isClickingPanel = false"
                 style="display:none"
                 class="mt-2 bg-gray-50 rounded-2xl border border-gray-100 flex flex-col overflow-hidden"
                 :style="(showPanel || showPanelMobile) ? 'height: 45vh; max-height: 380px;' : ''">
                 
                <!-- Табы -->
<div class="flex border-b border-gray-200 bg-white shrink-0">
    <button @click="panelMode = 'emojis'"
            :class="panelMode === 'emojis' ? 'text-amber-600' : 'text-gray-500 hover:text-amber-600'"
            :style="panelMode === 'emojis' ? 'background-color: #fffbeb !important; border-bottom: 2px solid #f59e0b !important;' : 'background-color: transparent !important; border-bottom: 2px solid transparent !important;'"
            class="flex-1 py-3 text-sm font-semibold inline-flex items-center justify-center gap-1 transition">
        <?= icon('happy') ?> Эмодзи
    </button>
    
    <button @click="panelMode = 'stickers'"
            :class="panelMode === 'stickers' ? 'text-amber-600' : 'text-gray-500 hover:text-amber-600'"
            :style="panelMode === 'stickers' ? 'background-color: #fffbeb !important; border-bottom: 2px solid #f59e0b !important;' : 'background-color: transparent !important; border-bottom: 2px solid transparent !important;'"
            class="flex-1 py-3 text-sm font-semibold inline-flex items-center justify-center gap-1 transition">
        <?= icon('palette') ?> Стикеры
    </button>
</div>

                <!-- Смайлы -->
<div x-show="panelMode === 'emojis'" class="flex-1 flex flex-col overflow-hidden min-h-0">
    <!-- Верхняя строка паков (фиксирована) -->
    <div class="flex gap-1 overflow-x-auto p-2 border-b border-gray-200 bg-white shrink-0">
        <template x-for="pack in emojiPacks" :key="pack.id">
            <button @click="activeEmojiPack = pack.id; $nextTick(() => document.activeElement.blur());" 
                    :class="activeEmojiPack === pack.id ? 'bg-amber-100 ring-2 ring-amber-400' : 'bg-gray-100'" 
                    class="shrink-0 w-10 h-10 rounded-lg overflow-hidden flex items-center justify-center border border-gray-200">
                <img x-show="pack.first_emoji" :src="pack.first_emoji" class="w-full h-full object-contain" alt="" loading="lazy" decoding="async">
                <span x-show="!pack.first_emoji" class="inline-flex items-center justify-center w-5 h-5 text-gray-400"><?= icon('happy') ?></span>
            </button>
        </template>
    </div>
    
    <!-- Сетка эмодзи (прокручивается) -->
    <div class="flex-1 overflow-y-auto p-2 min-h-0">
        <div class="grid grid-cols-7 sm:grid-cols-10 gap-1.5">
            <template x-for="emoji in getActiveEmojis()" :key="emoji.id">
                
<button
    type="button"
    @mousedown.prevent
    @click="insertEmojiToChat(emoji)"
    class="aspect-square rounded-lg hover:bg-amber-50 transition flex items-center justify-center overflow-hidden p-0.5"
    :aria-label="'Вставить эмодзи ' + (emoji.code || '')"
>
    <img
        :src="emoji.image_url"
        class="w-full h-full object-contain pointer-events-none"
        :alt="emoji.code || 'Эмодзи'"
        loading="lazy"
        decoding="async"
        draggable="false"
    >
</button>

            </template>
        </div>
    </div>
</div>

                <!-- Стикеры -->
                <div x-show="panelMode === 'stickers'" class="flex-1 flex flex-col overflow-hidden min-h-0">
                    <!-- Верхняя строка паков -->
                    <div class="flex gap-1.5 overflow-x-auto p-2 border-b border-gray-200 bg-white shrink-0">
                        <template x-for="pack in sortedStickerPacks" :key="pack.id">
                            <div class="shrink-0 relative">
                                <button @click="
                                            $nextTick(() => {
                                                const el = document.getElementById('sticker-preview-' + pack.id);
                                                if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                            });
                                        "
                                        :class="isStickerPackActive(pack.id) ? 'ring-2 ring-amber-400 bg-amber-50' : 'bg-gray-50'"
                                        class="w-12 h-12 rounded-xl overflow-hidden border border-gray-200 flex items-center justify-center">
                                    <img x-show="pack.first_sticker" :src="pack.first_sticker" class="w-full h-full object-contain" alt="" loading="lazy" decoding="async">
                                    <span x-show="!pack.first_sticker" class="inline-flex items-center justify-center w-6 h-6 text-gray-400"><?= icon('palette') ?></span>
                                </button>
                                <span x-show="!isStickerPackActive(pack.id)"
                                      class="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-amber-500 text-white text-[11px] font-bold flex items-center justify-center shadow-md z-10 pointer-events-none">
                                    +
                                </span>
                            </div>
                        </template>
                    </div>

                    <div class="flex-1 overflow-y-auto p-2 min-h-0">
                        <!-- Активные паки -->
                        <template x-if="activeStickerPacksList.length > 0">
                            <div>
                                <template x-for="pack in activeStickerPacksList" :key="'active-' + pack.id">
                                    <div :id="'sticker-preview-' + pack.id" class="mb-4">
                                        <div class="flex items-center justify-between mb-1">
                                            <div class="font-bold text-sm text-gray-800" x-text="pack.name"></div>
                                            <button @click="removeStickerPack(pack.id);"
                                                    class="px-3 py-1 rounded-lg bg-rose-500 text-white text-xs font-bold hover:bg-rose-600 transition">
                                                Удалить
                                            </button>
                                        </div>
                                        <div class="grid grid-cols-4 sm:grid-cols-8 gap-1.5">
                                            <template x-for="sticker in (stickersByPack[pack.id] || [])" :key="sticker.id">
                                                <button @click="sendSticker(sticker.image_url); showPanel = false; showPanelMobile = false;" type="button"
                                                        class="aspect-square rounded-xl overflow-hidden border border-gray-100 bg-white hover:bg-amber-50 transition">
                                                    <img :src="sticker.image_url" class="w-full h-full object-contain" alt="" loading="lazy" decoding="async">
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <!-- Неактивные паки -->
                        <template x-for="pack in sortedStickerPacks.filter(p => !isStickerPackActive(p.id))" :key="'inactive-' + pack.id">
                            <div :id="'sticker-preview-' + pack.id" class="mb-4">
                                <div class="flex items-center justify-between mb-1">
                                    <div>
                                        <div class="font-bold text-sm text-gray-800" x-text="pack.name"></div>
                                        <div class="text-xs text-gray-400" x-text="((stickersByPack[pack.id] || []).length) + ' стикеров'"></div>
                                    </div>
                                    <button @click="addStickerPack(pack.id); $nextTick(() => document.activeElement.blur());"
                                            class="px-3 py-1 rounded-lg bg-blue-500 text-white text-xs font-bold hover:bg-blue-600 transition">
                                        Добавить
                                    </button>
                                </div>
                                <div class="flex gap-1 overflow-hidden">
                                    <template x-for="sticker in (stickersByPack[pack.id] || []).slice(0, 6)" :key="'preview-' + sticker.id">
                                        <div class="w-1/6 aspect-square rounded-lg overflow-hidden border border-gray-100 bg-white">
                                            <img :src="sticker.image_url" class="w-full h-full object-contain" alt="" loading="lazy" decoding="async">
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

        </div>

        <!-- Модалка изображения -->
        <div x-show="showImageModal" x-transition.opacity style="display:none" 
             class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 p-4" 
             @click.self="closeImageModal()" @keydown.escape.window="closeImageModal()">
            <div class="relative max-w-5xl max-h-[90vh] w-full">
                <button @click="closeImageModal()" 
                        class="absolute -top-10 right-0 text-white hover:text-gray-300 transition inline-flex items-center justify-center w-8 h-8"
                        aria-label="Закрыть">
                    <?= icon('close') ?>
                </button>
                <img :src="modalImageUrl" class="w-full h-auto max-h-[85vh] object-contain rounded-xl shadow-2xl" alt="Просмотр">
                <a :href="modalImageUrl" target="_blank" 
                   class="absolute bottom-4 left-1/2 -translate-x-1/2 px-4 py-2 bg-white/90 text-gray-700 rounded-lg text-sm font-semibold hover:bg-white">
                    Открыть в новой вкладке
                </a>
            </div>
        </div>
    </section>
</div>
<script>
function measureLayout() {
    const footer = document.querySelector('footer') || document.querySelector('.footer') || document.querySelector('#footer');
    const mobileNav = document.querySelector('nav[x-data]');
    const isMobileNavVisible = mobileNav && mobileNav.offsetWidth > 0 && mobileNav.offsetHeight > 0;
    if (isMobileNavVisible) {
        document.documentElement.style.setProperty('--bottom-h', mobileNav.offsetHeight + 'px');
    } else if (footer) {
        document.documentElement.style.setProperty('--bottom-h', footer.offsetHeight + 'px');
    } else {
        document.documentElement.style.setProperty('--bottom-h', '0px');
    }
}
window.addEventListener('load', measureLayout);
window.addEventListener('resize', measureLayout);
measureLayout();
setTimeout(measureLayout, 100);
setTimeout(measureLayout, 500);
</script>
<script>
  window.DEFAULT_AVATAR_SVG_MALE    = `<?= str_replace('`', '\\`', default_avatar_svg('male')) ?>`;
  window.DEFAULT_AVATAR_SVG_FEMALE  = `<?= str_replace('`', '\\`', default_avatar_svg('female')) ?>`;
  window.DEFAULT_AVATAR_SVG_NEUTRAL = `<?= str_replace('`', '\\`', default_avatar_svg()) ?>`;

  window.getAvatarSvg = function(gender) {
    if (gender === 'male')   return window.DEFAULT_AVATAR_SVG_MALE;
    if (gender === 'female') return window.DEFAULT_AVATAR_SVG_FEMALE;
    return window.DEFAULT_AVATAR_SVG_NEUTRAL;
  };
</script>
<script src="<?= url('assets/js/chat/chat.js') ?>?v=<?= @filemtime(ROOT_DIR . '/assets/js/chat/chat.js') ?: time() ?>"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>