<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_ui.php';
require_admin();
$pageTitle = 'Сообщения от пользователей';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.am-ic { display: inline-flex; align-items: center; justify-content: center; }
.am-ic svg { width: 100%; height: 100%; }
</style>

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8"
         x-data="adminMessages()">

    <?php admin_top('messages', 'mail', 'Сообщения', 'Чат с пользователями и обращения из умного чата'); ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        <!-- Список диалогов -->
        <div class="lg:col-span-1 bg-white rounded-2xl shadow-md overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100">
                <input type="text" x-model="search"
                       placeholder="Поиск по email…"
                       class="w-full px-3 py-2 rounded-xl border border-gray-200 outline-none text-sm focus:border-amber-300">
            </div>

            <div class="max-h-[70vh] overflow-y-auto">
                <template x-if="conversations.length === 0 && !loading">
                    <div class="text-center py-12 text-gray-400 text-sm">
                        Нет диалогов
                    </div>
                </template>

                <template x-for="conv in filteredConversations" :key="conv.user_email">
                    <button type="button"
                            @click="openConversation(conv.user_email)"
                            :class="activeEmail === conv.user_email
                                ? 'bg-amber-50 border-l-4 border-amber-400'
                                : 'hover:bg-gray-50 border-l-4 border-transparent'"
                            class="w-full text-left px-4 py-3 border-b border-gray-50 transition flex items-start gap-3">

                        <!-- Аватар -->
                        <span class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center text-lg shrink-0">
                            <template x-if="conv.avatar_url">
                                <img :src="conv.avatar_url" class="w-10 h-10 rounded-full object-cover" alt="">
                            </template>
                            <template x-if="!conv.avatar_url">
                                <span x-text="(conv.display_name || conv.user_email || '?').charAt(0).toUpperCase()"></span>
                            </template>
                        </span>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2 mb-0.5">
                                <span class="font-bold text-sm text-gray-800 truncate"
                                      x-text="conv.display_name || conv.user_email"></span>
                                <span x-show="conv.unread > 0"
                                      class="min-w-[18px] h-[18px] px-1 rounded-full bg-rose-500 text-white text-[10px] font-bold flex items-center justify-center"
                                      x-text="conv.unread"></span>
                            </div>

                            <!-- Для обращений из умного чата показываем тему, для обычных сообщений - текст -->
                            <div class="text-xs truncate"
                                 :class="gcTicket(conv.last_message) ? 'text-amber-700 font-semibold' : 'text-gray-500'"
                                 x-text="gcTicket(conv.last_message) ? gcTicket(conv.last_message).title : (conv.last_message || 'Нет сообщений')"></div>

                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-[10px] text-gray-400" x-text="formatTime(conv.last_at)"></span>
                                <span x-show="gcTicket(conv.last_message) && gcTicket(conv.last_message).high"
                                      style="display:none"
                                      class="text-[10px] font-bold px-1.5 rounded-full bg-rose-100 text-rose-600">срочно</span>
                            </div>
                        </div>
                    </button>
                </template>
            </div>
        </div>

        <!-- Окно чата -->
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-md flex flex-col h-[75vh]">

            <!-- Пусто -->
            <template x-if="!activeEmail">
                <div class="flex-1 flex flex-col items-center justify-center text-gray-400 p-8 text-center">
                    <span class="am-ic w-16 h-16 mb-3 text-gray-300"><?= icon('chats', '', 1.25) ?></span>
                    <p class="text-sm">Выберите диалог слева</p>
                </div>
            </template>

            <!-- Заголовок -->
            <template x-if="activeEmail">
                <div class="flex items-center gap-3 px-4 py-3 border-b border-gray-100">
                    <span class="w-9 h-9 rounded-full bg-amber-100 flex items-center justify-center shrink-0"
                          x-text="(activeName || '?').charAt(0).toUpperCase()"></span>
                    <div class="min-w-0 flex-1">
                        <div class="font-bold text-gray-800 text-sm truncate" x-text="activeName || activeEmail"></div>
                        <div class="text-xs text-gray-400 truncate" x-text="activeEmail"></div>
                    </div>
                    <button type="button"
                            @click="activeEmail = ''"
                            class="lg:hidden text-gray-400 hover:text-gray-600 w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center"
                            aria-label="Назад к списку">
                        <span class="am-ic w-5 h-5"><?= icon('left', '', 1.5) ?></span>
                    </button>
                </div>
            </template>

            <!-- Сообщения -->
            <template x-if="activeEmail">
                <div class="flex-1 overflow-y-auto p-4 space-y-3 bg-gray-50" x-ref="scroll">
                    <template x-if="loadingMessages">
                        <div class="text-center text-gray-400 text-sm py-8">Загрузка…</div>
                    </template>

                    <template x-if="!loadingMessages && messages.length === 0">
                        <div class="text-center text-gray-400 text-sm py-8">
                            Диалог пуст. Напишите первым.
                        </div>
                    </template>

                    <template x-for="(m, i) in messages" :key="i">
                        <div :class="m.is_admin ? 'flex justify-end' : 'flex justify-start'">

                            <!-- Обращение из умного чата: карточка -->
                            <template x-if="!m.is_admin && gcTicket(m.text)">
                                <div class="max-w-[90%] w-full sm:w-[440px] bg-white rounded-2xl border border-amber-200 shadow-sm overflow-hidden">
                                    <div class="flex items-center gap-2 px-3 py-2 bg-amber-50 border-b border-amber-100">
                                        <span class="am-ic w-4 h-4 text-amber-600 shrink-0"><?= icon('document', '', 1.5) ?></span>
                                        <span class="font-bold text-sm text-gray-800 flex-1 min-w-0 truncate" x-text="gcTicket(m.text).title"></span>
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0"
                                              :class="{
                                                  'bg-rose-100 text-rose-600': gcTicket(m.text).level === 'high',
                                                  'bg-gray-100 text-gray-500': gcTicket(m.text).level === 'normal',
                                                  'bg-sky-100 text-sky-600': gcTicket(m.text).level === 'low'
                                              }"
                                              x-text="gcTicket(m.text).label"></span>
                                    </div>
                                    <div class="px-3 py-2 text-sm space-y-1">
                                        <template x-for="(r, ri) in gcTicket(m.text).rows" :key="ri">
                                            <div class="flex gap-2">
                                                <span class="text-gray-400 shrink-0 w-24 text-xs pt-0.5" x-text="r.k"></span>
                                                <span class="text-gray-700 min-w-0 break-words whitespace-pre-wrap" x-text="r.v"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <!-- Обычное сообщение -->
                            <template x-if="m.is_admin || !gcTicket(m.text)">
                                <div class="max-w-[80%] px-3 py-2 rounded-2xl text-sm whitespace-pre-wrap"
                                     :class="m.is_admin
                                        ? 'bg-indigo-500 text-white'
                                        : 'bg-white text-gray-700 border border-gray-100'"
                                     x-text="m.text"></div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            <!-- Быстрые ответы + форма -->
            <template x-if="activeEmail">
                <div class="border-t border-gray-100">
                    <div class="px-3 pt-2 flex gap-2 overflow-x-auto" aria-label="Быстрые ответы">
                        <template x-for="t in gcCanned(messages)" :key="t">
                            <button type="button" @click="input = t"
                                    class="shrink-0 max-w-[260px] truncate px-3 py-1.5 rounded-full border border-indigo-200 bg-white text-indigo-700 text-xs font-semibold hover:bg-indigo-50 transition"
                                    :title="t" x-text="t"></button>
                        </template>
                    </div>

                    <form @submit.prevent="send()" class="p-3 flex gap-2">
                        <input type="text" x-model="input"
                               placeholder="Ответить пользователю…"
                               :disabled="sending"
                               class="flex-1 px-3 py-2 rounded-xl border border-gray-200 outline-none text-sm focus:border-amber-300"
                               aria-label="Ответ пользователю">
                        <button type="submit"
                                :disabled="sending || !input.trim()"
                                class="px-5 py-2 rounded-xl bg-indigo-500 text-white font-bold disabled:opacity-50 inline-flex items-center justify-center"
                                aria-label="Отправить">
                            <span class="am-ic w-5 h-5" x-show="!sending"><?= icon('send', '', 1.5) ?></span>
                            <span class="am-ic w-5 h-5" x-show="sending" style="display:none"><?= icon('hourglass', '', 1.5) ?></span>
                        </button>
                    </form>
                </div>
            </template>
        </div>
    </div>
</section>

<script src="<?= url('assets/js/admin-ticket.js') ?>?v=1"></script>
<script src="<?= url('assets/js/admin-messages.js') ?>?v=1"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
