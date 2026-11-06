<?php
$__showMobileNav = false;
require_once __DIR__ . '/../config/config.php';
require_login();

$user = current_user();
$userEmail = $user['email'];

$counterpartEmail = urldecode($_GET['email'] ?? '');
$counterpartEmail = trim($counterpartEmail);

if (empty($counterpartEmail) || !filter_var($counterpartEmail, FILTER_VALIDATE_EMAIL) || $counterpartEmail === $userEmail) {
    http_response_code(400);
    $pageTitle = 'Ошибка';
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="text-center py-20"><h1>Некорректный собеседник</h1><a href="/chats">Вернуться к чатам</a></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$counterpart = db_one('SELECT id, email, display_name, avatar_emoji, avatar_url, gender FROM users WHERE email = ?', [$counterpartEmail]);

if (!$counterpart) {
    http_response_code(404);
    $pageTitle = 'Ошибка';
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="text-center py-20"><h1>Собеседник не найден</h1><a href="/chats">Вернуться к чатам</a></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Стикеры
$stickerPacks = db_all("SELECT sp.*, (SELECT image_url FROM stickers s WHERE s.pack_id = sp.id ORDER BY s.sort_order ASC LIMIT 1) as first_sticker FROM sticker_packs sp WHERE sp.is_active = 1 ORDER BY sp.sort_order ASC");
$allStickers = db_all("SELECT s.* FROM stickers s JOIN sticker_packs sp ON sp.id = s.pack_id WHERE sp.is_active = 1 ORDER BY s.pack_id, s.sort_order ASC");
$stickersByPack = [];
foreach ($allStickers as $s) { $stickersByPack[$s['pack_id']][] = $s; }

// Эмодзи
$emojiPacks = db_all("SELECT ep.*, (SELECT image_url FROM emoji_images ei WHERE ei.pack_id = ep.id ORDER BY ei.sort_order ASC LIMIT 1) as first_emoji FROM emoji_packs ep WHERE ep.is_active = 1 ORDER BY ep.sort_order ASC");
$allEmojis = db_all("SELECT ei.* FROM emoji_images ei JOIN emoji_packs ep ON ep.id = ei.pack_id WHERE ep.is_active = 1 ORDER BY ei.pack_id, ei.sort_order ASC");
$emojisByPack = [];
foreach ($allEmojis as $em) { $emojisByPack[$em['pack_id']][] = $em; }

$pageTitle = 'Чат с ' . ($counterpart['display_name'] ?? $counterpartEmail);
$extraCss = '/assets/css/chats.css';
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
window.__USER_EMAIL__ = <?= json_encode($userEmail, JSON_UNESCAPED_UNICODE) ?>;
window.__COUNTERPART_EMAIL__ = <?= json_encode($counterpartEmail, JSON_UNESCAPED_UNICODE) ?>;
window.__STICKER_PACKS__ = <?= json_encode($stickerPacks, JSON_UNESCAPED_UNICODE) ?: '[]' ?>;
window.__STICKERS_BY_PACK__ = <?= json_encode($stickersByPack, JSON_UNESCAPED_UNICODE) ?: '{}' ?>;
window.__EMOJI_PACKS__ = <?= json_encode($emojiPacks, JSON_UNESCAPED_UNICODE) ?: '[]' ?>;
window.__EMOJIS_BY_PACK__ = <?= json_encode($emojisByPack, JSON_UNESCAPED_UNICODE) ?: '{}' ?>;
</script>

<div class="chat-page-wrapper">
    <section class="flex flex-col h-full overflow-hidden"
             x-data="chatEngine({
                 isGroup: false,
                 counterpartEmail: window.__COUNTERPART_EMAIL__,
                 myEmail: window.__USER_EMAIL__
             })"
             x-init="init()">

        <!-- Шапка -->
        <div class="chat-header-fixed shrink-0">
            <a href="/chats" class="text-gray-400 hover:text-gray-600 shrink-0 inline-flex items-center justify-center w-8 h-8" title="Назад">
                <?= icon('back') ?>
            </a>
            <?= render_avatar(
                $counterpart,
                'w-10 h-10 rounded-full object-cover ring-2 ring-amber-200 shrink-0',
                'w-10 h-10 rounded-full bg-amber-100 ring-2 ring-amber-200 flex items-center justify-center text-amber-600 shrink-0',
                'text-xl'
            ) ?>
            <span class="font-bold text-gray-800 truncate flex-1"><?= e($counterpart['display_name'] ?? $counterpartEmail) ?></span>
            <div class="relative shrink-0" x-data="{ showMenu: false }">
                <button @click="showMenu = !showMenu" type="button" class="w-10 h-10 rounded-xl hover:bg-gray-100 transition flex items-center justify-center" title="Меню">
                    <?= icon('dots') ?>
                </button>
                <div x-show="showMenu" @click.outside="showMenu = false" style="display:none" class="absolute right-0 mt-2 w-48 bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-50">
                    <button class="w-full px-4 py-2.5 text-left text-sm text-rose-600 hover:bg-rose-50 transition inline-flex items-center gap-2">
                        <?= icon('trash') ?> Удалить чат
                    </button>
                </div>
            </div>
        </div>

        <!-- Сообщения -->
        <div class="chat-messages-scroll space-y-3 relative" 
            x-ref="messagesContainer" 
            @scroll="handleScroll($event)">
            <template x-for="msg in messages" :key="msg.id">
                <div :class="msg.sender_email === myEmail ? 'flex justify-end' : 'flex justify-start'">
                    <div class="group relative flex flex-col max-w-[80%]" :class="msg.sender_email === myEmail ? 'items-end' : 'items-start'">
                        <div x-show="msg.sender_email !== myEmail" class="flex items-center gap-1.5 mb-1 ml-1">
                            <span class="text-xs font-semibold text-gray-500" x-text="msg.display_name"></span>
                        </div>

                        <!-- Цитата -->
                        <template x-if="msg.quoted_text || msg.quoted_image_url || msg.quoted_sticker_url">
                            <div class="mb-1 max-w-full px-3 py-1.5 bg-gray-200/60 rounded-lg border-l-2 border-gray-400 text-xs break-words">
                                <span class="font-semibold text-gray-600 block" x-text="msg.quoted_sender_email === myEmail ? 'Вы' : (msg.quoted_author || 'Собеседник')"></span>
                                <div x-show="msg.quoted_text" class="text-gray-500 chat-message-content" x-html="renderMessage(msg.quoted_text)"></div>
                                <span x-show="msg.quoted_sticker_url" class="inline-flex items-center gap-1 text-gray-500"><?= icon('palette') ?> Стикер</span>
                                <span x-show="msg.quoted_image_url" class="inline-flex items-center gap-1 text-gray-500"><?= icon('image') ?> Фото</span>
                            </div>
                        </template>

                        <!-- Текст -->
                        <div x-show="msg.message && !msg.sticker_url && !msg.image_url" :class="msg.sender_email === myEmail ? 'bg-amber-100 text-amber-900' : 'bg-gray-100 text-gray-700'" class="px-4 py-2.5 rounded-2xl text-sm break-words w-fit max-w-full">
                            <div class="w-full inline-block chat-message-content"><div x-html="renderMessage(msg.message)"></div></div>
                            <div class="text-[10px] mt-1 opacity-60 flex items-center gap-1 justify-end">
                                <span x-show="msg.is_edited" class="italic">ред.</span>
                                <span x-text="formatTime(msg.created_at)"></span>
                            </div>
                        </div>

                        <!-- Стикер -->
                        <div x-show="msg.sticker_url" class="max-w-full">
                            <img :src="msg.sticker_url" class="block w-48 h-48 object-contain" alt="Стикер">
                            <div class="text-[10px] text-gray-400 mt-1 text-center" x-text="formatTime(msg.created_at)"></div>
                        </div>

                        <!-- Лайки -->
                        <div class="flex items-center gap-0.5 mt-1" :class="msg.sender_email === myEmail ? 'justify-end' : 'justify-start'">
                            <button @click="vote(msg, 'chat_message', msg.id, 1, $event)" type="button" :class="msg.my_vote === 1 ? 'vote-btn-active-like' : ''" class="vote-btn text-gray-400">
                                <span class="vote-icon-wrapper"><?= icon('like') ?></span>
                                <span x-text="msg.like_count || ''"></span>
                            </button>
                            <button @click="vote(msg, 'chat_message', msg.id, -1, $event)" type="button" :class="msg.my_vote === -1 ? 'vote-btn-active-dislike' : ''" class="vote-btn text-gray-400">
                                <span class="vote-icon-wrapper"><?= icon('dislike') ?></span>
                                <span x-text="msg.dislike_count || ''"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
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

        <!-- Ввод -->
        <div class="chat-input-fixed shrink-0">
            
            <div x-show="imagePreview" style="display:none" class="px-4 py-2 bg-gray-50 border-t border-gray-100 mb-2 rounded-xl">
                <div class="flex items-center gap-2">
                    <img :src="imagePreview" class="image-preview" alt="Предпросмотр">
                    <button @click="cancelImageUpload()" class="text-gray-400 hover:text-gray-600 inline-flex items-center justify-center w-6 h-6"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                </div>
            </div>

            <div x-show="quotedMessage" style="display:none" class="px-4 py-2 bg-blue-50 border-t border-blue-100 mb-2 rounded-xl">
                <div class="flex items-start gap-2">
                    <div class="flex-1 px-3 py-1.5 bg-white/60 rounded-lg border-l-2 border-blue-300 text-xs">
                        <div class="font-semibold text-blue-700" x-text="quotedMessage ? getQuoteAuthorName(quotedMessage) : ''"></div>
                        <div class="text-gray-600 line-clamp-2" x-html="quotedMessage ? getQuotePreview(quotedMessage) : ''"></div>
                    </div>
                    <button @click="cancelQuote()" class="text-gray-400 hover:text-gray-600 shrink-0 inline-flex items-center justify-center w-6 h-6"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                </div>
            </div>

            <!-- ДЕСКТОП -->
            <div class="hidden sm:flex items-center gap-2">
                <button @click="showPanel = !showPanel; showPanelMobile = false;" type="button" class="w-10 h-10 rounded-xl bg-amber-50 hover:bg-amber-100 flex items-center justify-center shrink-0">
                    <?= icon('happy') ?>
                </button>
                <button x-show="!editingMessageId" @click="triggerImageUpload()" type="button" class="w-10 h-10 rounded-xl bg-amber-50 hover:bg-amber-100 flex items-center justify-center shrink-0">
                    <?= icon('image') ?>
                </button>
                <input type="file" x-ref="imageInput" accept="image/*" class="hidden" @change="handleImageUpload($event)">
                <div x-ref="chatInput" contenteditable="true" @input="onChatInput(); saveDraft();" @click="saveRange()" @keyup="saveRange()" @keydown.enter.prevent="onChatEnter($event)" @paste="onChatPaste($event)" class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 outline-none focus:border-amber-300 text-sm overflow-y-auto max-h-32" data-placeholder="Введите сообщение..."></div>
                <button x-show="!editingMessageId" @click="sendMessage()" :disabled="inputEmpty && !imageFile" class="px-4 py-2.5 rounded-xl bg-amber-300 text-white font-bold hover:bg-amber-400 transition disabled:opacity-50 shrink-0 inline-flex items-center justify-center">
                    <?= icon('send') ?>
                </button>
                <button x-show="editingMessageId" @click="saveEdit()" :disabled="inputEmpty" class="px-4 py-2.5 rounded-xl bg-blue-500 text-white font-bold text-sm hover:bg-blue-600 transition disabled:opacity-50 shrink-0 inline-flex items-center justify-center">
                    <?= icon('check') ?>
                </button>
            </div>

            <!-- МОБИЛЬНЫЙ -->
            <div class="flex flex-col gap-2 sm:hidden">
                <div x-ref="chatInputMobile" contenteditable="true" @input="onChatInput(); saveDraft();" @click="saveRange(); showPanelMobile = false;" @keyup="saveRange()" @keydown.enter.prevent="onChatEnter($event)" @paste="onChatPaste($event)" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 outline-none focus:border-amber-300 text-sm overflow-y-auto max-h-32" data-placeholder="Введите сообщение..."></div>
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

                        <button x-show="!editingMessageId" @click="triggerImageUpload()" type="button" class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center" aria-label="Фото">
                            <?= icon('image') ?>
                        </button>
                        <input type="file" x-ref="imageInputMobile" accept="image/*" class="hidden" @change="handleImageUpload($event)">
                    </div>
                    <button x-show="!editingMessageId" @click="sendMessage()" :disabled="inputEmpty && !imageFile" class="px-4 py-2.5 rounded-xl bg-amber-300 text-white font-bold hover:bg-amber-400 transition disabled:opacity-50">
                        <?= icon('send') ?>
                    </button>
                    <button x-show="editingMessageId" @click="saveEdit()" :disabled="inputEmpty" class="px-4 py-2.5 rounded-xl bg-blue-500 text-white font-bold text-sm hover:bg-blue-600 transition disabled:opacity-50">
                        <?= icon('check') ?>
                    </button>
                </div>
            </div>

            <!-- ПАНЕЛЬ СМАЙЛОВ И СТИКЕРОВ -->
            <div x-show="showPanel || showPanelMobile" x-transition @mousedown="isClickingPanel = true" @mouseup="isClickingPanel = false" @touchstart="isClickingPanel = true" @touchend="isClickingPanel = false" style="display:none" class="mt-2 bg-gray-50 rounded-2xl border border-gray-100 flex flex-col overflow-hidden" :style="(showPanel || showPanelMobile) ? 'height: 45vh; max-height: 380px;' : ''">
                <div class="flex border-b border-gray-200 bg-white shrink-0">
                    <button @click="panelMode = 'emojis'" :class="panelMode === 'emojis' ? 'text-amber-600' : 'text-gray-500 hover:text-amber-600'" :style="panelMode === 'emojis' ? 'background-color: #fffbeb !important; border-bottom: 2px solid #f59e0b !important;' : 'background-color: transparent !important; border-bottom: 2px solid transparent !important;'" class="flex-1 py-3 text-sm font-semibold inline-flex items-center justify-center gap-1 transition">
                        <?= icon('happy') ?> Эмодзи
                    </button>
                    <button @click="panelMode = 'stickers'" :class="panelMode === 'stickers' ? 'text-amber-600' : 'text-gray-500 hover:text-amber-600'" :style="panelMode === 'stickers' ? 'background-color: #fffbeb !important; border-bottom: 2px solid #f59e0b !important;' : 'background-color: transparent !important; border-bottom: 2px solid transparent !important;'" class="flex-1 py-3 text-sm font-semibold inline-flex items-center justify-center gap-1 transition">
                        <?= icon('palette') ?> Стикеры
                    </button>
                </div>

                <!-- Смайлы -->
                <div x-show="panelMode === 'emojis'" class="flex-1 flex flex-col overflow-hidden min-h-0">
                    <div class="flex gap-1 overflow-x-auto p-2 border-b border-gray-200 bg-white shrink-0">
                        <template x-for="pack in emojiPacks" :key="pack.id">
                            <button @click="activeEmojiPack = pack.id; $nextTick(() => document.activeElement.blur());" :class="activeEmojiPack === pack.id ? 'bg-amber-100 ring-2 ring-amber-400' : 'bg-gray-100'" class="shrink-0 w-10 h-10 rounded-lg overflow-hidden flex items-center justify-center border border-gray-200">
                                <img x-show="pack.first_emoji" :src="pack.first_emoji" class="w-full h-full object-contain">
                                <span x-show="!pack.first_emoji" class="inline-flex items-center justify-center w-5 h-5 text-gray-400"><?= icon('happy') ?></span>
                            </button>
                        </template>
                    </div>
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
                    <div class="flex gap-1.5 overflow-x-auto p-2 border-b border-gray-200 bg-white shrink-0">
                        <template x-for="pack in sortedStickerPacks" :key="pack.id">
                            <div class="shrink-0 relative">
                                <button @click="$nextTick(() => { const el = document.getElementById('sticker-preview-' + pack.id); if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' }); });" :class="isStickerPackActive(pack.id) ? 'ring-2 ring-amber-400 bg-amber-50' : 'bg-gray-50'" class="w-12 h-12 rounded-xl overflow-hidden border border-gray-200 flex items-center justify-center">
                                    <img x-show="pack.first_sticker" :src="pack.first_sticker" class="w-full h-full object-contain">
                                    <span x-show="!pack.first_sticker" class="inline-flex items-center justify-center w-6 h-6 text-gray-400"><?= icon('palette') ?></span>
                                </button>
                                <span x-show="!isStickerPackActive(pack.id)" class="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-amber-500 text-white text-[11px] font-bold flex items-center justify-center shadow-md z-10 pointer-events-none">+</span>
                            </div>
                        </template>
                    </div>

                    <div class="flex-1 overflow-y-auto p-2 min-h-0">
                        <template x-if="activeStickerPacksList.length > 0">
                            <div>
                                <template x-for="pack in activeStickerPacksList" :key="'active-' + pack.id">
                                    <div :id="'sticker-preview-' + pack.id" class="mb-4">
                                        <div class="flex items-center justify-between mb-1 gap-2">
                                            <div class="font-bold text-sm text-gray-800 truncate min-w-0" x-text="pack.name"></div>
                                            <button @click="removeStickerPack(pack.id);"
                                                    class="shrink-0 whitespace-nowrap px-3 py-1.5 rounded-lg bg-rose-500 text-white text-xs font-bold hover:bg-rose-600 active:bg-rose-700 transition shadow-sm">
                                                Удалить
                                            </button>
                                        </div>
                                        <div class="grid grid-cols-4 sm:grid-cols-8 gap-1.5">
                                            <template x-for="sticker in (stickersByPack[pack.id] || [])" :key="sticker.id">
                                                <button @click="sendSticker(sticker.image_url); showPanel = false; showPanelMobile = false;" type="button" class="aspect-square rounded-xl overflow-hidden border border-gray-100 bg-white hover:bg-amber-50 transition">
                                                    <img :src="sticker.image_url" class="w-full h-full object-contain">
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <template x-for="pack in sortedStickerPacks.filter(p => !isStickerPackActive(p.id))" :key="'inactive-' + pack.id">
                            <div :id="'sticker-preview-' + pack.id" class="mb-4">
                                <div class="flex items-center justify-between mb-1 gap-2">
                                    <div class="min-w-0">
                                        <div class="font-bold text-sm text-gray-800 truncate" x-text="pack.name"></div>
                                        <div class="text-xs text-gray-400" x-text="((stickersByPack[pack.id] || []).length) + ' стикеров'"></div>
                                    </div>
                                    <button @click="addStickerPack(Number(pack.id)); $nextTick(() => document.activeElement.blur());"
                                            class="shrink-0 whitespace-nowrap px-3 py-1.5 rounded-lg bg-blue-500 text-white text-xs font-bold hover:bg-blue-600 active:bg-blue-700 transition shadow-sm">
                                        Добавить
                                    </button>
                                </div>
                                <div class="flex gap-1 overflow-hidden">
                                    <template x-for="sticker in (stickersByPack[pack.id] || []).slice(0, 6)" :key="'preview-' + sticker.id">
                                        <div class="w-1/6 aspect-square rounded-lg overflow-hidden border border-gray-100 bg-white">
                                            <img :src="sticker.image_url" class="w-full h-full object-contain">
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<!-- Подключаем единый скрипт -->
<script src="<?= url('assets/js/chat/chat.js') ?>?v=<?= time() ?>"></script>

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

window.DEFAULT_AVATAR_SVG_MALE    = `<?= str_replace('`', '\\`', default_avatar_svg('male')) ?>`;
window.DEFAULT_AVATAR_SVG_FEMALE  = `<?= str_replace('`', '\\`', default_avatar_svg('female')) ?>`;
window.DEFAULT_AVATAR_SVG_NEUTRAL = `<?= str_replace('`', '\\`', default_avatar_svg()) ?>`;
window.getAvatarSvg = function(gender) {
    if (gender === 'male')   return window.DEFAULT_AVATAR_SVG_MALE;
    if (gender === 'female') return window.DEFAULT_AVATAR_SVG_FEMALE;
    return window.DEFAULT_AVATAR_SVG_NEUTRAL;
};
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>