<?php
require_once __DIR__ . '/../config/config.php';

// Загружаем мета-теги для страницы
$meta = get_page_meta('/games');
if ($meta) {
    apply_meta($meta);
}

// Устанавливаем мета-теги
$pageTitle = $GLOBALS['pageTitle'] ?? 'Игры про еду — GigaChef';
$pageH1 = $GLOBALS['pageH1'] ?? 'Игры про еду';
$pageDescription = $GLOBALS['pageDescription'] ?? 'Играйте в бесплатные онлайн-игры про еду и кулинарию. Готовьте, управляйте рестораном и решайте головоломки на GigaChef!';
$pageImage = $GLOBALS['pageImage'] ?? url('assets/icon/chef_256.png');
$pageUrl = $GLOBALS['pageUrl'] ?? url('games');

$GLOBALS['pageH1'] = $pageH1;
$GLOBALS['pageTitle'] = $pageTitle;
$GLOBALS['pageDescription'] = $pageDescription;

// Загружаем игры из базы данных
$gamesFromDb = db_all('SELECT * FROM games WHERE is_active = 1 ORDER BY sort_order, id DESC');

// ============================================================
//  КАТЕГОРИИ ИГР
//
//  Ключ — slug категории из колонки games.category.
//  Значение — русская подпись. Иконка берётся через icon($slug).
//  Все эти slug-и присутствуют в icon-names.php.
// ============================================================
$gameCategories = [
    'cooking'         => 'Готовка',
    'puzzle'          => 'Головоломки',
    'arcade'          => 'Аркады',
    'strategy'        => 'Стратегии',
    'simulation'      => 'Симуляторы',
    'management'      => 'Управление',
    'time_management' => 'Тайм-менеджмент',
    'match3'          => 'Три в ряд',
    'platformer'      => 'Платформеры',
    'runner'          => 'Раннеры',
    'shooter'         => 'Шутеры',
    'racing'          => 'Гонки',
    'sports'          => 'Спортивные',
    'board'           => 'Настольные',
    'card'            => 'Карточные',
    'quiz'            => 'Викторины',
    'educational'     => 'Обучающие',
    'adventure'       => 'Приключения',
    'rpg'             => 'RPG',
    'action'          => 'Экшн',
    'idle'            => 'Кликеры',
    'multiplayer'     => 'Мультиплеер',
    'kids'            => 'Для детей',
    'casual'          => 'Казуальные',
];

// ============================================================
//  КАТЕГОРИИ ДЛЯ JS (slug → { label, icon })
//  icon — готовый HTML от icon(), чтобы Alpine мог вставлять
//  его через x-html без обращения к PHP в рантайме.
// ============================================================
$gameCategoriesForJs = [];
foreach ($gameCategories as $slug => $label) {
    $gameCategoriesForJs[$slug] = [
        'label' => $label,
        'icon'  => icon($slug, 'gc-icon-sm', 1),
    ];
}

// ============================================================
//  ИКОНКИ ДЛЯ JS-ШАБЛОНОВ
//
//  $jsIcons — карта name → готовый HTML.
//  Используется в x-html="icons.star", x-html="icons.game" и т.д.
//  Все имена — из includes/icon-names.php.
// ============================================================
$jsIcons = [
    // Базовые
    'game'         => icon('games',        'gc-icon-sm', 1),
    'search'       => icon('search',       'gc-icon-sm', 1),
    'chevron_down' => icon('chevron_down', 'gc-icon-sm', 1),
    'star'         => icon('star',         'gc-icon-sm', 1),
    'close'        => icon('close',        'gc-icon-sm', 1),
    'fullscreen'   => icon('fullscreen',   'gc-icon-sm', 1),
    'multiplayer'  => icon('multiplayer',  'gc-icon-sm', 1),
    'user'         => icon('user',         'gc-icon-sm', 1),

    // Сортировка
    'sort_rating'  => icon('sort_rating',  'gc-icon-sm', 1),
    'sort_popular' => icon('sort_popular', 'gc-icon-sm', 1),
    'sort_newest'  => icon('sort_newest',  'gc-icon-sm', 1),
    'sort_alpha'   => icon('sort_alpha',   'gc-icon-sm', 1),
];

require_once __DIR__ . '/../includes/header.php';
?>

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-8" x-data="foodGames()">

    <!-- HERO -->
    <div class="text-center mb-8" data-aos="fade-up">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-violet-100 text-violet-700 text-xs font-bold mb-3">
            <?= icon('games', 'gc-icon-sm', 1) ?>
            <span>Игры</span>
        </span>
        <h1 class="text-3xl font-extrabold text-gray-800"><?= e($pageH1) ?></h1>
        <p class="mt-2 text-gray-500">Играйте в увлекательные бесплатные игры на кулинарную тематику</p>
    </div>

    <!-- Поиск и сортировка -->
    <div class="bg-white rounded-2xl shadow-md p-4 mb-6 relative z-30" data-aos="fade-up" data-aos-delay="100"
         x-data="{ catOpen: false, sortOpen: false }" @click.outside="catOpen = false; sortOpen = false">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">

            <!-- Поиск -->
            <div class="relative">
                <input type="text" x-model="searchQuery" placeholder="Поиск игр..."
                       class="w-full px-4 py-3 pl-12 rounded-xl border border-gray-200 outline-none focus:border-violet-300">
                <span class="absolute left-4 top-3.5 text-gray-400 inline-flex items-center">
                    <?= icon('search', 'gc-icon-sm', 1) ?>
                </span>
            </div>

            <!-- Категории -->
            <div class="relative z-40">
                <button type="button" @click="catOpen = !catOpen; sortOpen = false"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-violet-300 bg-white text-left flex items-center justify-between gap-2">
                    <span class="flex items-center gap-2 truncate">
                        <span class="inline-flex items-center text-violet-500"
                              x-html="selectedCategory === 'all' ? icons.game : getCategoryIcon(selectedCategory)"></span>
                        <span class="truncate"
                              x-text="selectedCategory === 'all' ? 'Все категории' : getCategoryLabel(selectedCategory)"></span>
                    </span>
                    <span class="inline-flex items-center text-gray-400 shrink-0"
                          x-html="icons.chevron_down"></span>
                </button>

                <ul x-show="catOpen" x-transition style="display:none"
                    class="absolute z-[999] w-full mt-1 bg-white border border-gray-200 rounded-xl shadow-2xl max-h-80 overflow-y-auto">
                    <li @click="selectedCategory = 'all'; catOpen = false"
                        class="px-4 py-2 hover:bg-violet-50 cursor-pointer flex items-center gap-2">
                        <span class="inline-flex items-center text-violet-500" x-html="icons.game"></span>
                        <span>Все категории</span>
                    </li>
                    <template x-for="(cat, key) in categories" :key="key">
                        <li @click="selectedCategory = key; catOpen = false"
                            class="px-4 py-2 hover:bg-violet-50 cursor-pointer flex items-center gap-2">
                            <span class="inline-flex items-center text-violet-500" x-html="cat.icon"></span>
                            <span x-text="cat.label"></span>
                        </li>
                    </template>
                </ul>
            </div>

            <!-- Сортировка -->
            <div class="relative z-40">
                <button type="button" @click="sortOpen = !sortOpen; catOpen = false"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-violet-300 bg-white text-left flex items-center justify-between gap-2">
                    <span class="flex items-center gap-2 truncate">
                        <span class="inline-flex items-center text-violet-500" x-html="icons[sortIconKey()]"></span>
                        <span class="truncate" x-text="sortLabel()"></span>
                    </span>
                    <span class="inline-flex items-center text-gray-400 shrink-0"
                          x-html="icons.chevron_down"></span>
                </button>

                <ul x-show="sortOpen" x-transition style="display:none"
                    class="absolute z-[999] w-full mt-1 bg-white border border-gray-200 rounded-xl shadow-2xl">
                    <li @click="sortBy = 'rating';  sortOpen = false"
                        class="px-4 py-2 hover:bg-violet-50 cursor-pointer flex items-center gap-2">
                        <span class="inline-flex items-center text-violet-500" x-html="icons.sort_rating"></span>
                        <span>По рейтингу</span>
                    </li>
                    <li @click="sortBy = 'popular'; sortOpen = false"
                        class="px-4 py-2 hover:bg-violet-50 cursor-pointer flex items-center gap-2">
                        <span class="inline-flex items-center text-violet-500" x-html="icons.sort_popular"></span>
                        <span>По популярности</span>
                    </li>
                    <li @click="sortBy = 'newest';  sortOpen = false"
                        class="px-4 py-2 hover:bg-violet-50 cursor-pointer flex items-center gap-2">
                        <span class="inline-flex items-center text-violet-500" x-html="icons.sort_newest"></span>
                        <span>По дате добавления</span>
                    </li>
                    <li @click="sortBy = 'title';   sortOpen = false"
                        class="px-4 py-2 hover:bg-violet-50 cursor-pointer flex items-center gap-2">
                        <span class="inline-flex items-center text-violet-500" x-html="icons.sort_alpha"></span>
                        <span>По алфавиту</span>
                    </li>
                </ul>
            </div>

        </div>
    </div>

    <!-- Сетка игр -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <template x-for="game in filteredGames" :key="game.id">
            <div class="bg-white rounded-2xl shadow-md overflow-hidden hover:shadow-lg transition group cursor-pointer"
                 @click="openGame(game)">

                <div class="aspect-[4/3] bg-gradient-to-br from-violet-100 to-purple-100 relative overflow-hidden">
                    <template x-if="game.image_url">
                        <img :src="game.image_url"
                             class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                             :alt="game.title"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    </template>
                    <div class="w-full h-full items-center justify-center text-violet-500"
                         :style="game.image_url ? 'display:none' : 'display:flex'">
                        <span x-html="game.icon_svg || defaultGameIcon()"></span>
                    </div>

                    <span class="absolute top-3 left-3 px-2.5 py-1 rounded-lg text-xs font-bold bg-white/90 inline-flex items-center gap-1"
                          :class="getCategoryColor(game.category)">
                        <span x-html="getCategoryIcon(game.category)"></span>
                        <span x-text="getCategoryLabel(game.category)"></span>
                    </span>

                    <span class="absolute top-3 right-3 px-2.5 py-1 rounded-lg text-xs font-bold bg-white/90 text-amber-600 inline-flex items-center gap-1">
                        <span x-html="icons.star"></span>
                        <span x-text="(game.rating || 0).toFixed(1)"></span>
                        <span class="text-gray-400 text-[10px]">(<span x-text="game.rating_count || 0"></span>)</span>
                    </span>
                </div>

                <div class="p-4">
                    <h2 class="font-bold text-gray-800 clamp-1 leading-snug mb-1" x-text="game.title"></h2>
                    <p class="text-sm text-gray-500 clamp-2 mb-3" x-text="game.description || ''"></p>

                    <!-- Звёзды рейтинга -->
                    <div class="flex items-center gap-1 mb-3" @click.stop>
                        <template x-for="star in 5" :key="star">
                            <button @click="rateGame(game, star)"
                                    class="transition hover:scale-110"
                                    :class="star <= (userRatings[game.id] || 0) ? 'text-amber-400' : 'text-gray-200'"
                                    x-html="icons.star">
                            </button>
                        </template>
                        <span class="text-xs text-gray-400 ml-2">
                            <span x-text="(game.rating || 0).toFixed(1)"></span>
                            (<span x-text="game.rating_count || 0"></span>)
                        </span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-400 inline-flex items-center gap-1">
                            <span x-html="icons.user"></span>
                            <span x-text="game.players_count || '1000+'"></span>
                            <span>игроков</span>
                        </span>
                        <span class="text-xs font-bold text-violet-600">Играть →</span>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Модальное окно с игрой -->
    <div x-show="showGameModal" x-transition style="display:none"
         class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70"
         @click.self="closeGame()">
        <div class="bg-white shadow-xl w-full flex flex-col transition-all duration-300"
             :class="isFullscreen ? 'fixed inset-0 max-w-none max-h-none rounded-none' : 'rounded-3xl max-w-4xl max-h-[90vh] m-4'">

            <div class="flex items-center justify-between p-4 border-b border-gray-100 shrink-0">
                <h3 class="font-extrabold text-gray-800 text-lg truncate" x-text="selectedGame?.title"></h3>
                <div class="flex items-center gap-2 shrink-0">
                    <button @click="toggleFullscreen()"
                            class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition"
                            x-html="icons.fullscreen"></button>
                    <button @click="closeGame()"
                            class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition"
                            x-html="icons.close"></button>
                </div>
            </div>

            <div class="flex-1 bg-gray-900 relative"
                 :style="isFullscreen ? 'min-height: calc(100vh - 130px)' : 'min-height: 500px'">
                <div x-show="gameLoading" class="absolute inset-0 flex items-center justify-center z-10">
                    <div class="text-center">
                        <div class="animate-spin inline-block w-10 h-10 border-4 border-violet-200 border-t-violet-500 rounded-full mb-3"></div>
                        <p class="text-gray-300 text-sm">Загрузка игры...</p>
                    </div>
                </div>

                <iframe x-show="selectedGame" :src="selectedGame?.iframe_url"
                        class="w-full h-full absolute inset-0 border-0"
                        frameborder="0" allow="autoplay; fullscreen; gamepad; encrypted-media"
                        allowfullscreen @load="gameLoading = false" scrolling="no"></iframe>
            </div>
        </div>
    </div>
</section>

<script>
// ============================================================
//  ДАННЫЕ ИЗ PHP
// ============================================================
window.__GAMES__           = <?= json_encode($gamesFromDb, JSON_UNESCAPED_UNICODE) ?: '[]' ?>;
window.__GAME_CATEGORIES__ = <?= json_encode($gameCategoriesForJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.__GAME_ICONS__      = <?= json_encode($jsIcons, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

// ============================================================
//  ALPINE-КОМПОНЕНТ
// ============================================================
document.addEventListener('alpine:init', () => {
    Alpine.data('foodGames', () => ({
        searchQuery: '',
        selectedCategory: 'all',
        sortBy: 'rating',
        selectedGame: null,
        showGameModal: false,
        gameLoading: true,
        isFullscreen: false,
        userRatings: {},

        games: (window.__GAMES__ || []).map(g => ({
            ...g,
            rating:       Number(g.rating) || 0,
            rating_count: Number(g.rating_count) || 0,
        })),
        categories: window.__GAME_CATEGORIES__ || {},
        icons:      window.__GAME_ICONS__ || {},

        get filteredGames() {
            let filtered = [...this.games];

            if (this.searchQuery.trim()) {
                const query = this.searchQuery.toLowerCase();
                filtered = filtered.filter(g =>
                    g.title.toLowerCase().includes(query) ||
                    (g.description || '').toLowerCase().includes(query)
                );
            }

            if (this.selectedCategory !== 'all') {
                filtered = filtered.filter(g => {
                    const cats = this.getGameCategories(g);
                    return cats.includes(this.selectedCategory);
                });
            }

            switch (this.sortBy) {
                case 'rating':
                    filtered.sort((a, b) => (b.rating || 0) - (a.rating || 0));
                    break;
                case 'popular':
                    filtered.sort((a, b) => {
                        const parsePlayers = (str) => {
                            const num = parseInt(str);
                            if (isNaN(num)) return 0;
                            if (str.includes('K')) return num * 1000;
                            if (str.includes('M')) return num * 1000000;
                            return num;
                        };
                        return parsePlayers(b.players_count || '0') - parsePlayers(a.players_count || '0');
                    });
                    break;
                case 'newest':
                    filtered.sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));
                    break;
                case 'title':
                    filtered.sort((a, b) => (a.title || '').localeCompare(b.title || ''));
                    break;
            }

            return filtered;
        },

        getGameCategories(game) {
            if (game.categories) {
                try {
                    const cats = typeof game.categories === 'string'
                        ? JSON.parse(game.categories)
                        : game.categories;
                    if (Array.isArray(cats) && cats.length > 0) return cats;
                } catch (e) {}
            }
            return [game.category];
        },

        getCategoryLabel(category) {
            return (this.categories[category] && this.categories[category].label) || 'Игра';
        },

        getCategoryIcon(category) {
            return (this.categories[category] && this.categories[category].icon) || this.icons.game;
        },

        defaultGameIcon() {
            return this.icons.game;
        },

        getCategoryColor(category) {
            const colors = {
                'cooking':         'text-emerald-700',
                'puzzle':          'text-blue-700',
                'arcade':          'text-orange-700',
                'strategy':        'text-purple-700',
                'simulation':      'text-cyan-700',
                'management':      'text-indigo-700',
                'match3':          'text-pink-700',
                'platformer':      'text-green-700',
                'runner':          'text-lime-700',
                'shooter':         'text-red-700',
                'racing':          'text-amber-700',
                'sports':          'text-teal-700',
                'board':           'text-slate-700',
                'card':            'text-rose-700',
                'quiz':            'text-fuchsia-700',
                'educational':     'text-sky-700',
                'adventure':       'text-emerald-600',
                'rpg':             'text-violet-600',
                'action':          'text-orange-600',
                'idle':            'text-gray-600',
                'multiplayer':     'text-blue-600',
                'kids':            'text-yellow-600',
                'casual':          'text-gray-500',
            };
            return colors[category] || 'text-gray-700';
        },

        sortLabel() {
            return {
                'rating':  'По рейтингу',
                'popular': 'По популярности',
                'newest':  'По дате добавления',
                'title':   'По алфавиту',
            }[this.sortBy] || 'По рейтингу';
        },

        sortIconKey() {
            return {
                'rating':  'sort_rating',
                'popular': 'sort_popular',
                'newest':  'sort_newest',
                'title':   'sort_alpha',
            }[this.sortBy] || 'sort_rating';
        },

        async rateGame(game, rating) {
            this.userRatings[game.id] = rating;

            try {
                const res = await fetch('/api/rate_game.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ game_id: game.id, rating: rating })
                });

                const data = await res.json();

                if (!data.ok && !data.success) {
                    alert(data.error || 'Ошибка оценки');
                    return;
                }

                const newRating = Number(data.new_rating ?? data.rating ?? 0);
                const newCount  = Number(data.rating_count ?? data.count ?? 0);

                const idx = this.games.findIndex(g => g.id === game.id);
                if (idx !== -1) {
                    this.games[idx] = {
                        ...this.games[idx],
                        rating: newRating,
                        rating_count: newCount,
                    };
                }

                if (typeof toast === 'function') toast('Спасибо за оценку!', 'success');
            } catch (e) {
                console.error('Rating error:', e);
            }
        },

        openGame(game) {
            this.selectedGame = game;
            this.showGameModal = true;
            this.gameLoading = true;
            this.isFullscreen = false;
            document.body.style.overflow = 'hidden';
        },

        closeGame() {
            this.showGameModal = false;
            this.selectedGame = null;
            this.gameLoading = false;
            this.isFullscreen = false;
            document.body.style.overflow = '';
        },

        toggleFullscreen() {
            this.isFullscreen = !this.isFullscreen;
        },

        init() {
            if (!this._reactive) {
                this.games = Alpine.reactive(this.games);
                this._reactive = true;
            }
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.showGameModal) this.closeGame();
            });
        },
    }));
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>