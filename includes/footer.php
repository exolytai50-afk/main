</main>
<!-- ===== ПОДВАЛ (только десктоп) ===== -->
<footer class="hidden lg:block bg-white border-t border-gray-200 py-6 mt-auto">
    <div class="max-w-7xl mx-auto px-4 flex flex-row items-center justify-between gap-2 text-xs text-gray-400">
        <span>© <?= date('Y') ?> GigaChef. Все права защищены.</span>
        <div class="flex gap-4">
            <a href="<?= url('terms') ?>" class="hover:text-amber-600">Соглашение</a>
            <a href="<?= url('privacy') ?>" class="hover:text-amber-600">Конфиденциальность</a>
            <a href="<?= url('legal') ?>" class="hover:text-amber-600">Право</a>
            <a href="<?= url('about') ?>" class="hover:text-amber-600">О нас</a>
        </div>
    </div>
</footer>

<!-- ===== МОБИЛЬНОЕ НИЖНЕЕ МЕНЮ ===== -->
<?php if (!empty($__showMobileNav)): ?>
<nav class="lg:hidden fixed bottom-0 inset-x-0 z-50 mobile-nav border-t"
     style="padding-bottom: env(safe-area-inset-bottom, 0px);"
     aria-label="Основная навигация">
    <div class="flex items-stretch h-16 w-full">

        <!-- 1. Моя лента -->
        <a href="<?= url('') ?>"
           class="flex-1 flex flex-col items-center justify-center gap-0.5 h-full transition min-w-0 <?= $__currentPath === '' ? 'text-amber-600' : 'text-gray-400 hover:text-amber-600' ?>"
           aria-label="Моя лента">
            <span class="w-6 h-6 inline-flex items-center justify-center"><?= icon('home', '', 1.5) ?></span>
            <span class="text-[10px] font-semibold leading-none truncate">Лента</span>
        </a>

        <!-- 2. Все активности -->
        <a href="<?= url('feed') ?>"
           class="flex-1 flex flex-col items-center justify-center gap-0.5 h-full transition min-w-0 <?= $__currentPath === 'feed' ? 'text-amber-600' : 'text-gray-400 hover:text-amber-600' ?>"
           aria-label="Все активности">
            <span class="w-6 h-6 inline-flex items-center justify-center"><?= icon('feed', '', 1.5) ?></span>
            <span class="text-[10px] font-semibold leading-none truncate">Активности</span>
        </a>

        <!-- 3. Поиск -->
        <a href="<?= url('search') ?>"
           class="flex-1 flex flex-col items-center justify-center gap-0.5 h-full transition min-w-0 <?= $__currentPath === 'search' ? 'text-amber-600' : 'text-gray-400 hover:text-amber-600' ?>"
           aria-label="Поиск">
            <span class="w-6 h-6 inline-flex items-center justify-center"><?= icon('search', '', 1.5) ?></span>
            <span class="text-[10px] font-semibold leading-none truncate">Поиск</span>
        </a>

        <!-- 4. Уведомления -->
        <a href="<?= url('notifications') ?>"
           class="relative flex-1 flex flex-col items-center justify-center gap-0.5 h-full transition min-w-0 <?= $__currentPath === 'notifications' ? 'text-amber-600' : 'text-gray-400 hover:text-amber-600' ?>"
           aria-label="Уведомления">
            <span class="w-6 h-6 inline-flex items-center justify-center"><?= icon('notifications', '', 1.5) ?></span>
            <span class="text-[10px] font-semibold leading-none truncate">Уведомл.</span>

            <?php
            $__notifTotal = (int)($unreadNotifications ?? 0);
            ?>
            <span data-badge="notifications-total"
                  class="absolute top-2 right-1/4 min-w-[16px] h-[16px] px-1 rounded-full bg-rose-500 text-white text-[9px] font-bold flex items-center justify-center shadow-sm"
                  style="<?= $__notifTotal > 0 ? '' : 'display:none' ?>">
                <?= $__notifTotal > 99 ? '99+' : $__notifTotal ?>
            </span>
        </a>

        <!-- 4.5. Переключатель темы -->
        <button type="button"
        id="gc-theme-toggle-btn-mobile"
        class="flex-1 flex flex-col items-center justify-center gap-0.5 h-full transition min-w-0 text-gray-400 hover:text-amber-600"
        aria-label="Переключить тему">
    <span class="w-6 h-6 inline-flex items-center justify-center">
        <span class="gc-theme-icon-light w-full h-full flex items-center justify-center"><?= icon('day', '', 1.5) ?></span>
        <span class="gc-theme-icon-dark w-full h-full flex items-center justify-center" style="display:none"><?= icon('night', '', 1.5) ?></span>
    </span>
    <span class="text-[10px] font-semibold leading-none truncate">Тема</span>
</button>

        <!-- 5. Профиль -->
        <?php if (!empty($__user)): ?>
            <a href="<?= url('profile') ?>"
               class="flex-1 flex flex-col items-center justify-center gap-0.5 h-full transition min-w-0 <?= $__currentPath === 'profile' ? 'text-amber-600' : 'text-gray-400 hover:text-amber-600' ?>"
               aria-label="Профиль">
                <span class="w-6 h-6 inline-flex items-center justify-center">
                    <?= render_avatar(
                        $__user,
                        'w-6 h-6 rounded-full object-cover ring-2 ring-amber-200',
                        'w-6 h-6 rounded-full bg-amber-100 ring-2 ring-amber-200 flex items-center justify-center text-amber-600',
                        'text-xs'
                    ) ?>
                </span>
                <span class="text-[10px] font-semibold leading-none truncate">Профиль</span>
            </a>
        <?php else: ?>
            <a href="<?= url('login') ?>"
               class="flex-1 flex flex-col items-center justify-center gap-0.5 h-full text-gray-400 hover:text-amber-600 transition min-w-0"
               aria-label="Войти">
                <span class="w-6 h-6 inline-flex items-center justify-center"><?= icon('login', '', 1.5) ?></span>
                <span class="text-[10px] font-semibold leading-none truncate">Войти</span>
            </a>
        <?php endif; ?>
    </div>
</nav>
<?php endif; ?>
<!-- ===== BURGER MENU (fullscreen) ===== -->
<div x-data="burgerMenu()" x-init="init()" class="lg:hidden">

    <template x-teleport="body">
        <div x-show="open"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             style="display:none"
             class="fixed inset-0 z-[200] bg-black/50"
             @click.self="close()">

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-x-full"
                 x-transition:enter-end="translate-x-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-x-0"
                 x-transition:leave-end="translate-x-full"
                 class="absolute right-0 top-0 bottom-0 w-full max-w-sm bg-white shadow-2xl flex flex-col"
                 @click.stop>

                <!-- Header -->
                <div class="flex items-center gap-3 px-4 py-3 border-b border-gray-100 shrink-0">
                    <img src="<?= url('assets/icon/chef_32.png') ?>" alt="GigaChef" class="w-8 h-8 rounded-lg">
                    <span class="font-extrabold text-amber-700 flex-1">GigaChef</span>
                    <button type="button"
                            @click="close()"
                            class="w-10 h-10 rounded-xl hover:bg-gray-100 flex items-center justify-center text-gray-500"
                            aria-label="Закрыть">
                        <?= icon('close', '', 1.5) ?>
                    </button>
                </div>

                <!-- Search -->
                <div class="px-4 py-3 border-b border-gray-100 shrink-0">
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none inline-flex">
                            <?= icon('search', '', 1.25) ?>
                        </span>
                        <input type="text"
                               x-model="search"
                               placeholder="Поиск по разделам…"
                               class="w-full pl-10 pr-3 py-2 rounded-xl border border-gray-200 outline-none text-sm focus:border-amber-300">
                    </div>
                </div>

                <!-- Menu items -->
                <nav class="flex-1 overflow-y-auto py-2" @click="onNavClick()">

                    <?php
                    $__menuItems = [
                        ['section' => 'Основные', 'items' => [
                            ['icon' => icon('home',         '', 1.5), 'label' => 'Моя лента',          'url' => url('')],
                            ['icon' => icon('feed',         '', 1.5), 'label' => 'Все активности',     'url' => url('feed')],
                            ['icon' => icon('stories',      '', 1.5), 'label' => 'Истории',            'url' => url('stories')],
                        ]],
                        ['section' => 'AI-инструменты', 'items' => [
                            ['icon' => icon('aigenerator',  '', 1.5), 'label' => 'Генератор рецептов', 'url' => url('ai-generator')],
                            ['icon' => icon('whattocook',   '', 1.5), 'label' => 'Что приготовить?',   'url' => url('what-to-cook')],
                            ['icon' => icon('fusion',       '', 1.5), 'label' => 'Фьюжн-кухня',        'url' => url('fusion')],
                            ['icon' => icon('advisor',      '', 1.5), 'label' => 'Советник',           'url' => url('advisor')],
                            ['icon' => icon('scaner',       '', 1.5), 'label' => 'Сканер калорий',     'url' => url('calorie-scanner')],
                            ['icon' => icon('beer',         '', 1.5), 'label' => 'Бирпейринг',         'url' => url('beer-pairing')],
                            ['icon' => icon('diet',         '', 1.5), 'label' => 'Диетолог',           'url' => url('dietolog')],
                            ['icon' => icon('smoking',      '', 1.5), 'label' => 'Бросаем курить',     'url' => url('quit-smoking')],
                            ['icon' => icon('cookbook',     '', 1.5), 'label' => 'AI-книга',           'url' => url('ai-cookbook')],
                        ]],
                        ['section' => 'Сообщества', 'items' => [
                            ['icon' => icon('team',         '', 1.5), 'label' => 'Сообщества',         'url' => url('communities')],
                            ['icon' => icon('challenges',   '', 1.5), 'label' => 'Челленджи',          'url' => url('challenges')],
                            ['icon' => icon('leader',       '', 1.5), 'label' => 'Лидеры',             'url' => url('leaderboard')],
                            ['icon' => icon('friends',      '', 1.5), 'label' => 'Друзья',             'url' => url('friends')],
                            ['icon' => icon('video',        '', 1.5), 'label' => 'Видео',              'url' => url('videos')],
                        ]],
                        ['section' => 'Прочее', 'items' => [
                            ['icon' => icon('achievements', '', 1.5), 'label' => 'Достижения',         'url' => url('achievements')],
                            ['icon' => icon('games',        '', 1.5), 'label' => 'Игры',               'url' => url('games')],
                            ['icon' => icon('search',       '', 1.5), 'label' => 'Поиск',              'url' => url('search')],
                            ['icon' => icon('payment',      '', 1.5), 'label' => 'Тарифы',             'url' => url('payment')],
                        ]],
                        ['section' => 'Аккаунт', 'items' => [
                            ['icon' => icon('profile',      '', 1.5), 'label' => 'Профиль',            'url' => url('profile')],
                            ['icon' => icon('bookmark',     '', 1.5), 'label' => 'Сохранённые',        'url' => url('saved-recipes')],
                            ['icon' => icon('openbook',     '', 1.5), 'label' => 'Мои книги',          'url' => url('my-cookbooks')],
                        ]],
                    ];

                    if (function_exists('is_admin') && is_admin()) {
                        $__menuItems[] = ['section' => 'Админ', 'items' => [
                            ['icon' => icon('admin',     '', 1.5), 'label' => 'Админ-панель', 'url' => url('admin')],
                            ['icon' => icon('technical', '', 1.5), 'label' => 'Техработы',    'url' => url('admin/maintenance')],
                        ]];
                    }
                    ?>

                    <?php foreach ($__menuItems as $__group): ?>
                        <div class="px-4 pt-4 pb-1 text-xs font-bold text-gray-400 uppercase tracking-wider">
                            <?= e($__group['section']) ?>
                        </div>
                        <?php foreach ($__group['items'] as $__item): ?>
                            <a href="<?= e($__item['url']) ?>"
                               x-show="!search || '<?= e(mb_strtolower($__item['label'])) ?>'.includes(search.toLowerCase())"
                               class="flex items-center gap-3 px-4 py-1 hover:bg-amber-50 transition">
                                <span class="text-2xl shrink-0 inline-flex items-center justify-center" aria-hidden="true"><?= $__item['icon'] ?></span>
                                <span class="font-semibold text-gray-600 text-sm"><?= e($__item['label']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    <?php endforeach; ?>

                    <div class="border-t border-gray-100 my-2"></div>

    <?php if (!empty($__user)): ?>
        <a href="<?= url('logout') ?>"
           class="flex items-center gap-3 px-4 py-3 text-rose-600 hover:bg-rose-50 transition">
            <span class="text-2xl shrink-0 inline-flex items-center justify-center" aria-hidden="true"><?= icon('exit', '', 1.5) ?></span>
            <span class="font-semibold text-sm">Выйти</span>
        </a>
    <?php else: ?>
        <a href="<?= url('login') ?>"
           class="flex items-center gap-3 px-4 py-3 text-amber-700 hover:bg-amber-50 transition">
            <span class="text-2xl shrink-0 inline-flex items-center justify-center" aria-hidden="true"><?= icon('login', '', 1.5) ?></span>
            <span class="font-semibold text-sm">Войти</span>
        </a>
    <?php endif; ?>

    <!-- ⬇⬇⬇ Копирайт и юридические ссылки ⬇⬇⬇ -->
    <div class="px-4 pt-6 pb-8 border-t border-gray-100 mt-4">
        <div class="text-xs text-gray-400 space-y-2">
            <div class="flex flex-wrap gap-x-4 gap-y-1">
                <a href="<?= url('terms') ?>" class="hover:text-amber-600 transition">Соглашение</a>
                <a href="<?= url('privacy') ?>" class="hover:text-amber-600 transition">Конфиденциальность</a>
                <a href="<?= url('legal') ?>" class="hover:text-amber-600 transition">Право</a>
                <a href="<?= url('about') ?>" class="hover:text-amber-600 transition">О нас</a>
            </div>
            <div class="pt-2">
                © <?= date('Y') ?> GigaChef. Все права защищены.
            </div>
        </div>
    </div>
</nav>
            </div>
        </div>
    </template>
</div>

<!-- ===== ЧАТ С АДМИНОМ (адаптивно) ===== -->
<?php
// Определяем, находимся ли мы на странице чата/мессенджера
$__isChatPage = preg_match('~^(chat|admin/messages)(/|$)~', $__currentPath);

// Показываем кнопку, если:
// 1. Пользователь авторизован
// 2. Кнопка не скрыта вручную ($__hideChatButton)
// 3. Мы НЕ на странице чата (чтобы не мешать вводу)
?>
<?php if (is_logged_in() && empty($__hideChatButton) && !$__isChatPage): ?>
<div class="fixed z-[80] right-4 lg:right-5"
     role="complementary"
     data-chat-btn
     style="bottom: <?= !empty($__showMobileNav)
         ? 'calc(5rem + env(safe-area-inset-bottom, 0px))'
         : 'calc(1rem + env(safe-area-inset-bottom, 0px))' ?>;">
    <?php if (is_admin()): ?>
        <a href="<?= url('admin/messages') ?>"
           class="relative w-12 h-12 rounded-full bg-gradient-to-br from-gray-400 to-gray-500 shadow-lg flex items-center justify-center text-2xl text-white hover:scale-105 transition"
           aria-label="Открыть сообщения пользователей"
           title="Сообщения пользователей">
            <?= icon('chats', '', 1.5) ?>
            <?php
            $__unreadAdmin = 0;
            try {
                $__row = db_one(
                    "SELECT COUNT(*) AS cnt FROM admin_chat_messages
                     WHERE to_email = ? AND is_read = 0",
                    [$__user['email']]
                );
                $__unreadAdmin = (int)($__row['cnt'] ?? 0);
            } catch (Throwable $e) {}
            ?>
            <?php if ($__unreadAdmin > 0): ?>
                <span class="absolute -top-1 -right-1 min-w-[20px] h-[20px] px-1 rounded-full bg-rose-500 text-white text-[10px] font-bold flex items-center justify-center">
                    <?= $__unreadAdmin ?>
                </span>
            <?php endif; ?>
        </a>
    <?php else: ?>
        <?php
        // Иконки (SVG из icon()) для умного чата: подставляются в шаблоны через x-html
        $__scIcons = [];
        foreach (['admin', 'payment', 'profile', 'ai', 'openbook', 'friends', 'technical', 'sparkle', 'chats', 'check', 'left', 'right', 'refresh', 'send', 'close'] as $__n) {
            $__scIcons[$__n] = icon($__n, '', 1.5);
        }
        $__scUrls = [
            'payment'       => url('payment'),
            'profile'       => url('profile'),
            'friends'       => url('friends'),
            'notifications' => url('notifications'),
            'search'        => url('search'),
            'what-to-cook'  => url('what-to-cook'),
        ];
        ?>
        <script>
        window.GC_SC = <?= json_encode(['icons' => $__scIcons, 'urls' => $__scUrls, 'delay' => 320],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        </script>

        <div x-data="adminChat()" x-init="$store.sc && $store.sc.bind($data)"
             @keydown.escape.window="open = false"
             aria-label="Чат с администратором">

            <button x-show="!open"
                    @click="open = true"
                    class="relative w-12 h-12 rounded-full bg-gradient-to-br from-gray-400 to-gray-500 shadow-lg flex items-center justify-center text-2xl text-white hover:scale-105 transition"
                    aria-label="Открыть чат с админом"
                    :aria-expanded="open ? 'true' : 'false'">
                <?= icon('chats', '', 1.5) ?>
                <span x-show="unread > 0"
                      class="absolute -top-1 -right-1 min-w-[20px] h-[20px] px-1 rounded-full bg-rose-500 text-white text-[10px] font-bold flex items-center justify-center"
                      x-text="unread"></span>
            </button>

            <div x-show="open" x-transition style="display:none"
                 class="w-[90vw] max-w-sm bg-white rounded-3xl shadow-2xl flex flex-col h-[70vh] absolute bottom-0 right-0"
                 role="dialog" aria-modal="true" aria-labelledby="admin-chat-title">

                <div class="flex items-center justify-between p-4 border-b border-gray-100">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600"><?= icon('admin', '', 1.25) ?></span>
                        <div>
                            <span id="admin-chat-title" class="font-extrabold text-indigo-800 text-sm block">Чат с админом</span>
                            <span class="text-[10px] text-gray-400" x-text="statusText"></span>
                        </div>
                    </div>
                    <button @click="open = false"
                            class="text-gray-400 hover:text-gray-600 w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 transition"
                            aria-label="Закрыть">
                        <?= icon('close', '', 1.25) ?>
                    </button>
                </div>

                <!-- Лента: сообщения с сервера + сценарий умного помощника -->
                <div id="sc-scroll" class="flex-1 overflow-y-auto p-4 space-y-3 bg-gray-50" x-ref="scroll"
                     aria-live="polite" aria-label="История сообщений"
                     x-effect="$store.sc && $store.sc.sync(open, loading, messages.length)">

                    <template x-if="loading && messages.length === 0">
                        <div class="text-center text-gray-400 text-sm py-8">Загрузка…</div>
                    </template>
                    <template x-if="!loading && messages.length === 0 && !$store.sc.active && !$store.sc.log.length">
                        <div class="text-center text-gray-400 text-sm py-8">
                            Напишите админу — он ответит при первой возможности
                        </div>
                    </template>

                    <template x-for="(m, i) in messages" :key="i">
                        <div :class="m.is_admin ? 'flex justify-start' : 'flex justify-end'">
                            <div class="max-w-[85%] px-3 py-2 rounded-2xl text-sm whitespace-pre-wrap"
                                 :class="m.is_admin ? 'bg-white text-gray-600 border border-gray-100' : 'bg-indigo-500 text-white'"
                                 x-text="m.text"></div>
                        </div>
                    </template>

                    <!-- Сценарий помощника -->
                    <template x-for="(b, i) in $store.sc.log" :key="'sc' + i">
                        <div>
                            <template x-if="b.w === 'bot'">
                                <div class="flex items-end gap-2">
                                    <span class="sc-ic w-7 h-7 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center shrink-0"
                                          x-html="$store.sc.ic('admin')"></span>
                                    <div class="max-w-[85%] px-3 py-2 rounded-2xl rounded-bl-md bg-white text-gray-700 border border-gray-100 text-sm">
                                        <div class="whitespace-pre-wrap" x-text="b.t"></div>
                                        <template x-if="b.links && b.links.length">
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                <template x-for="l in b.links" :key="l.href">
                                                    <a :href="l.href"
                                                       class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-indigo-50 text-indigo-700 text-xs font-bold hover:bg-indigo-100 transition">
                                                        <span x-text="l.label"></span>
                                                        <span class="sc-ic" x-html="$store.sc.ic('right')"></span>
                                                    </a>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <template x-if="b.w === 'me'">
                                <div class="flex justify-end">
                                    <div class="max-w-[85%] px-3 py-2 rounded-2xl rounded-br-md bg-indigo-500 text-white text-sm whitespace-pre-wrap" x-text="b.t"></div>
                                </div>
                            </template>

                            <template x-if="b.w === 'sum'">
                                <div class="ml-9 bg-white border border-indigo-100 rounded-2xl overflow-hidden text-sm">
                                    <div class="px-3 py-2 bg-indigo-50 font-bold text-indigo-800 text-xs" x-text="b.title"></div>
                                    <div class="px-3 py-2 space-y-1">
                                        <template x-for="(r, ri) in b.rows" :key="ri">
                                            <div class="flex gap-2">
                                                <span class="text-gray-400 shrink-0 w-20 text-xs pt-0.5" x-text="r[0]"></span>
                                                <span class="text-gray-700 min-w-0 break-words" x-text="r[1]"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <!-- Помощник «печатает» -->
                    <div x-show="$store.sc.busy" style="display:none" class="flex items-end gap-2" aria-hidden="true">
                        <span class="sc-ic w-7 h-7 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center shrink-0"
                              x-html="$store.sc.ic('admin')"></span>
                        <div class="px-3 py-3 rounded-2xl rounded-bl-md bg-white border border-gray-100 flex gap-1">
                            <span class="sc-dot"></span><span class="sc-dot"></span><span class="sc-dot"></span>
                        </div>
                    </div>

                    <!-- Варианты ответа -->
                    <div x-show="$store.sc.active && !$store.sc.busy && $store.sc.cur" style="display:none"
                         class="flex flex-wrap gap-2 pl-9">
                        <template x-for="o in $store.sc.options()" :key="o.label">
                            <button type="button" @click="$store.sc.pick(o)"
                                    :disabled="$store.sc.sending"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-xs font-semibold transition disabled:opacity-50"
                                    :class="o.action === 'send'
                                        ? 'bg-indigo-500 border-indigo-500 text-white hover:bg-indigo-600'
                                        : (o.kind
                                            ? 'border-gray-200 text-gray-500 hover:bg-gray-100'
                                            : 'border-indigo-200 bg-white text-indigo-700 hover:bg-indigo-50')">
                                <span class="sc-ic" x-show="o.icon" x-html="$store.sc.ic(o.icon)"></span>
                                <span x-text="o.label"></span>
                            </button>
                        </template>
                    </div>

                    <div x-show="$store.sc.notice" style="display:none"
                         class="text-center text-xs text-gray-400 py-1" x-text="$store.sc.notice"></div>
                </div>

                <div class="border-t border-gray-100">
                    <div x-show="!$store.sc.active" style="display:none" class="px-3 pt-2">
                        <button type="button" @click="$store.sc.start()"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-indigo-50 text-indigo-700 text-xs font-bold hover:bg-indigo-100 transition">
                            <span class="sc-ic" x-html="$store.sc.ic('sparkle')"></span>
                            Новое обращение
                        </button>
                    </div>

                    <form @submit.prevent="$store.sc ? $store.sc.onSubmit() : send()" class="p-3 flex gap-2">
                        <input type="text" x-model="input" data-sc-input maxlength="500"
                               :placeholder="$store.sc ? $store.sc.placeholder() : 'Ваше сообщение…'"
                               class="flex-1 min-w-0 px-3 py-2 rounded-xl border border-gray-200 outline-none text-sm focus:border-indigo-300"
                               :disabled="sending"
                               aria-label="Сообщение для админа">
                        <button type="submit"
                                :disabled="sending || !input.trim()"
                                class="px-4 py-2 rounded-xl bg-indigo-500 text-white font-bold disabled:opacity-50"
                                aria-label="Отправить">
                            <span x-show="!sending"><?= icon('send', '', 1.25) ?></span>
                            <span x-show="sending" style="display:none"><?= icon('hourglass', '', 1.25) ?></span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<style>
@media (min-width: 1024px) {
    [data-chat-btn] { bottom: 1.25rem !important; }
}
.sc-ic { display: inline-flex; align-items: center; justify-content: center; }
.sc-ic svg { width: 1.05rem; height: 1.05rem; }
.sc-dot { width: 6px; height: 6px; border-radius: 50%; background: #9ca3af; animation: sc-b 1s infinite ease-in-out; }
.sc-dot:nth-child(2) { animation-delay: .15s; }
.sc-dot:nth-child(3) { animation-delay: .3s; }
@keyframes sc-b { 0%, 80%, 100% { transform: translateY(0); opacity: .4; } 40% { transform: translateY(-3px); opacity: 1; } }
@media (prefers-reduced-motion: reduce) { .sc-dot { animation: none; } }
</style>
<?php endif; ?>

<!-- ===== JS для бургер-меню ===== -->
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('burgerMenu', () => ({
        open: false,
        search: '',

        init() {
            window.addEventListener('open-burger', () => { this.open = true; });
        },

        close() {
            this.open = false;
            this.search = '';
        },

        onNavClick() {
            // Даём ссылке сработать, потом закрываем
            setTimeout(() => this.close(), 100);
        }
    }));
});
</script>

<!-- ===== Скрипты ===== -->
<script src="<?= url('assets/js/pagination.js') ?>?v=<?= time() ?>"></script>
<script src="<?= url('assets/js/infinite-feed.js') ?>?v=<?= time() ?>"></script>
<script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
<script>
AOS.init({
    duration: 600,
    once: true,          // анимация срабатывает один раз
    disable: 'mobile',   // на мобильных AOS не инициализируется (там анимации не нужны)
    startEvent: 'load',  // старт после загрузки всех ресурсов
    throttleDelay: 99,   // throttle для scroll-события
    offset: 120
});
</script>
<script src="<?= url('assets/js/api-client.js') ?>"></script>
<script src="<?= url('assets/js/app.js') ?>"></script>
<script src="<?= url('assets/js/theme.js') ?>"></script>
<script src="<?= url('assets/js/smart-chat.js') ?>?v=1"></script>
<script src="<?= url('assets/js/admin-chat.js') ?>?v=2"></script>
<script src="<?= url('assets/js/share.js') ?>?v=4"></script>
<!-- ===== Опрос счётчиков (единый) ===== -->
<script>
(function() {
    'use strict';

    const POLL_INTERVAL = 10000;
    const POLL_URL      = '/api/notifications.php?counters=1';

    let lastData = null;
    let timer    = null;

    function setBadge(name, count) {
        document.querySelectorAll('[data-badge="' + name + '"]').forEach(function(el) {
            if (count > 0) {
                el.textContent = count > 99 ? '99+' : String(count);
                el.style.display = '';
                el.removeAttribute('hidden');
            } else {
                el.textContent = '';
                el.style.display = 'none';
                el.setAttribute('hidden', '');
            }
        });
    }

    function updateBadges(data) {
        var n = parseInt(data.notifications || 0, 10);
        var f = parseInt(data.friends || 0, 10);
        // заявка в друзья уже входит в notifications — не складываем
        setBadge('notifications-total', n);
        setBadge('notifications', n);
        setBadge('chats',   parseInt(data.chats || 0, 10));
        setBadge('friends', f);
        setBadge('admin',   parseInt(data.admin || 0, 10));
    }

    function showToast(text, onClick) {
        var t = document.createElement('div');
        t.style.cssText = 'position:fixed;bottom:20px;right:20px;background:#f59e0b;color:#fff;'
                        + 'padding:12px 18px;border-radius:12px;font-weight:600;font-size:14px;'
                        + 'z-index:9999;cursor:pointer;box-shadow:0 10px 25px rgba(0,0,0,.15);'
                        + 'opacity:0;transition:opacity .3s;max-width:300px;';
        t.textContent = text;
        t.onclick = function() { t.remove(); if (onClick) onClick(); };
        document.body.appendChild(t);
        requestAnimationFrame(function() { t.style.opacity = '1'; });
        setTimeout(function() {
            t.style.opacity = '0';
            setTimeout(function() { t.remove(); }, 300);
        }, 7000);
    }

    function handleChanges(oldData, newData) {
        if (!oldData) return;
    
        var dF = (newData.friends       || 0) - (oldData.friends       || 0);
        var dN = (newData.notifications || 0) - (oldData.notifications || 0);
        var dC = (newData.chats         || 0) - (oldData.chats         || 0);
        var dA = (newData.admin         || 0) - (oldData.admin         || 0);
    
        if (dF > 0) {
            showToast('🔔 Новая заявка в друзья' + (dF > 1 ? ' (' + dF + ')' : ''),
                      function() { window.location.href = '/friends'; });
            document.dispatchEvent(new CustomEvent('gigachef:friends-changed', { detail: newData }));
        }
    
        // заявка уже показана тостом выше — вычитаем её из общего прироста
        var dNotif = dN - (dF > 0 ? dF : 0);
        if (dNotif > 0) {
            showToast('🔔 Новое уведомление' + (dNotif > 1 ? ' (' + dNotif + ')' : ''),
                      function() { window.location.href = '/notifications'; });
        }
    
        if (dC > 0) {
            showToast('💬 Новое сообщение' + (dC > 1 ? ' (' + dC + ')' : ''),
                      function() { window.location.href = '/chats'; });
        }
        if (dA > 0) {
            showToast('📩 Новое сообщение от пользователя (админ)',
                      function() { window.location.href = '/admin/messages'; });
        }
    
        // любое изменение заявок/уведомлений — для живого обновления списков
        if (dF !== 0 || dN !== 0) {
            document.dispatchEvent(new CustomEvent('gigachef:counters', { detail: newData }));
        }
    }

    async function poll() {
        try {
            var res = await fetch(POLL_URL, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store'
            });
            if (!res.ok) return;
            var data = await res.json();
            if (!data.ok) return;
            updateBadges(data);
            handleChanges(lastData, data);
            lastData = data;
        } catch (e) {}
    }

    function schedule() {
        if (timer) clearTimeout(timer);
        if (document.hidden) return;
        timer = setTimeout(async function() { await poll(); schedule(); }, POLL_INTERVAL);
    }

    poll().then(schedule);

    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) { poll(); schedule(); }
    });

    window.gigachefPoll  = { run: poll };
    window.refreshBadges = function() { poll(); };
})();
</script>
<!-- ===== COOKIE BANNER JS ===== -->
<script>
(function() {
    'use strict';

    const banner = document.getElementById('cookie-banner');
    if (!banner) return;

    const POLICY_VERSION = document.querySelector('meta[name="cookie-policy-version"]')?.content || '1.0';
    const COOKIE_NAME = 'cookie_consent';
    const COOKIE_DAYS = 180; // 6 месяцев
    const LS_KEY = 'cookie_consent_dismissed';

    // Если уже скрыт в localStorage — скрываем мгновенно (без мигания)
    if (localStorage.getItem(LS_KEY) === POLICY_VERSION) {
        banner.remove();
        return;
    }

    // Устанавливаем cookie
    function setCookie(value, days) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        const expires = 'expires=' + date.toUTCString();
        const secure = location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = COOKIE_NAME + '=' + encodeURIComponent(value)
            + '; ' + expires
            + '; path=/'
            + '; SameSite=Lax'
            + secure;
    }

    // Отправка на сервер для логирования
    function logConsent(accepted) {
        const csrf = window.__CSRF_TOKEN__
            || document.querySelector('meta[name="csrf-token"]')?.content
            || '';

        fetch('/api/cookie_consent.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrf
            },
            body: JSON.stringify({
                policy_version: POLICY_VERSION,
                accepted: accepted ? 1 : 0
            })
        }).catch(() => {
            // Тихо игнорируем — согласие уже сохранено в cookie
        });
    }

    // Скрытие баннера
    function hideBanner(accepted) {
        banner.classList.add('cookie-banner--hiding');
        setTimeout(() => banner.remove(), 400);

        // Сохраняем в localStorage для мгновенного скрытия при следующих загрузках
        localStorage.setItem(LS_KEY, POLICY_VERSION);

        // Сохраняем cookie (на 6 месяцев)
        const value = POLICY_VERSION + '|' + Math.floor(Date.now() / 1000);
        setCookie(value, COOKIE_DAYS);

        // Логируем на сервер
        logConsent(accepted);
    }

    // Кнопка "Принять"
    document.getElementById('cookie-accept-btn')?.addEventListener('click', () => {
        hideBanner(true);
    });

    // Кнопка "Закрыть" (крестик) — считаем как принятие (стандартная практика)
    document.getElementById('cookie-close-btn')?.addEventListener('click', () => {
        hideBanner(true);
    });
})();
</script>
<script>
/**
 * Глобальная функция для отправки форм дружбы через AJAX.
 * Вызывается через onsubmit="return gcFriendSubmit(event, this)".
 */
async function gcFriendSubmit(e, form) {
    e.preventDefault();
    e.stopPropagation();

    const btn = form.querySelector('button[type="submit"]');
    const oldHTML = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '...'; }

    const actionUrl = form.getAttribute('action') || '/api/friends.php';
    const formData  = new FormData(form);
    const action    = formData.get('action');

    try {
        const res = await fetch(actionUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        });
        const data = await res.json();

        if (data.ok) {
            const isProfile = window.location.pathname.includes('/profile');

            // Новая заявка не на странице профиля: просто подменяем кнопку
            if (action === 'add' && !data.exists && !isProfile) {
                form.style.display = 'none';
                const msg = document.createElement('span');
                msg.className = 'text-sm text-emerald-600 font-bold';
                msg.textContent = 'Заявка отправлена';
                form.parentNode.insertBefore(msg, form.nextSibling);
            } else {
                // accept / reject / уже существующая связь / профиль:
                // перезагрузка покажет актуальный статус и списки
                window.location.reload();
            }
            if (window.gigachefPoll) window.gigachefPoll.run();
        } else {
            alert(data.error || 'Ошибка');
            if (btn) { btn.disabled = false; btn.innerHTML = oldHTML; }
        }
    } catch (err) {
        alert('Ошибка сети');
        if (btn) { btn.disabled = false; btn.innerHTML = oldHTML; }
    }
    return false;
}
</script>
</body>
</html>