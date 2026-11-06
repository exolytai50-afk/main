<?php
require_once __DIR__ . '/../config/config.php';

// Получаем HTML-код рекламы
$inlineAdHtml = get_ad_code('feed_inline');

/* ============================================================
   ХЕЛПЕРЫ
   ============================================================ */
if (!function_exists('transliterate')) {
    function transliterate($string) {
        $translit = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
            'А'=>'a','Б'=>'b','В'=>'v','Г'=>'g','Д'=>'d','Е'=>'e','Ё'=>'yo','Ж'=>'zh','З'=>'z','И'=>'i','Й'=>'y','К'=>'k','Л'=>'l','М'=>'m','Н'=>'n','О'=>'o','П'=>'p','Р'=>'r','С'=>'s','Т'=>'t','У'=>'u','Ф'=>'f','Х'=>'kh','Ц'=>'ts','Ч'=>'ch','Ш'=>'sh','Щ'=>'sch','Ъ'=>'','Ы'=>'y','Ь'=>'','Э'=>'e','Ю'=>'yu','Я'=>'ya',
        ];
        return strtr($string, $translit);
    }
}

if (!function_exists('generateSlug')) {
    function generateSlug($string) {
        $string = transliterate($string);
        $string = mb_strtolower($string);
        $string = str_replace([' ','_','/','\\',',','.','!','?','(',')','[',']','{','}','+','=','&','#','@','$','%','^','*',':',';','"',"'",'<','>','|','~','`'], '-', $string);
        $string = preg_replace('/[^a-z0-9\-]/u', '', $string);
        $string = preg_replace('/-+/', '-', $string);
        return trim($string, '-');
    }
}

/* ============================================================
   SLUG ТЕГА
   ============================================================ */
$slug = trim((string)($_GET['slug'] ?? ''));

if ($slug === '') {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

if (preg_match('/[а-яА-ЯёЁ]/u', $slug)) {
    $slug = generateSlug($slug);
}

/* ============================================================
   ПОИСК ТЕГА
   ============================================================ */
$tag = db_one('SELECT * FROM recipe_tags WHERE slug = ? AND is_active = 1', [$slug]);

if (!$tag) {
    $tag = db_one('SELECT * FROM recipe_tags WHERE LOWER(name) = ? AND is_active = 1', [mb_strtolower($slug)]);
}

if (!$tag && is_numeric($slug)) {
    $tag = db_one('SELECT * FROM recipe_tags WHERE id = ? AND is_active = 1', [(int)$slug]);
}

if (!$tag) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

/* ============================================================
   МЕТА
   ============================================================ */
$pageTitle       = ($tag['name'] ?? 'Тег') . ' — рецепты на GigaChef';
$pageDescription = $tag['description'] ?: 'Лучшие рецепты с тегом «' . $tag['name'] . '». Готовьте с удовольствием вместе с GigaChef!';
$pageKeywords    = $tag['name'] . ', рецепты, кулинария, GigaChef';
$pageImage       = !empty($tag['image_url']) ? $tag['image_url'] : url('assets/icon/chef_256.png');
$pageUrl         = url('tag/' . $tag['slug']);
$pageType        = 'website';
$GLOBALS['pageH1']    = $tag['name'];
$GLOBALS['pageTitle'] = $pageTitle;

/* ============================================================
   ПАГИНАЦИЯ
   ============================================================ */
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset  = ($page - 1) * $perPage;

/* ============================================================
   РЕЦЕПТЫ С ЭТИМ ТЕГОМ
   ============================================================ */
$recipes = db_all(
    "SELECT r.*,
            u.display_name AS author_name,
            u.avatar_url   AS author_avatar_url,
            u.gender       AS author_gender,
            c.slug         AS category_slug,
            c.name         AS category_name,
            c.icon_svg     AS category_icon
     FROM recipes r
     INNER JOIN recipe_tag_links l ON l.recipe_id = r.id
     LEFT JOIN users u ON u.email = r.author_email
     LEFT JOIN recipe_categories c ON c.id = r.category_id
     WHERE l.tag_id = ?
     ORDER BY r.created_at DESC
     LIMIT ? OFFSET ?",
    [(int)$tag['id'], $perPage, $offset]
);

/* ============================================================
   ТЕГИ ДЛЯ КАРТОЧЕК + готовый HTML иконок
   ============================================================ */
if (!empty($recipes)) {
    $ids = array_column($recipes, 'id');
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $tagRows = db_all(
        "SELECT l.recipe_id, t.id, t.slug, t.name, t.icon_svg, t.tag_group
         FROM recipe_tag_links l
         JOIN recipe_tags t ON t.id = l.tag_id
         WHERE l.recipe_id IN ({$ph}) AND t.is_active = 1
         ORDER BY t.tag_group, t.sort_order, t.slug",
        $ids
    );
    $byRecipe = [];
        foreach ($tagRows as $row) {
        $byRecipe[$row['recipe_id']][] = [
            'id'            => (int)$row['id'],
            'slug'          => $row['slug'],
            'name'          => $row['name'],
            'icon_svg'      => $row['icon_svg'],
            'icon_svg_html' => !empty($row['icon_svg'])
                ? icon($row['icon_svg'], 'gc-icon-xs', 0.9)
                : '',
            'tag_group'     => $row['tag_group'],
        ];
    }
    foreach ($recipes as &$r) {
        $r['tags_list'] = $byRecipe[$r['id']] ?? [];
    }
    unset($r);
}

/* ============================================================
   ОБЩЕЕ КОЛИЧЕСТВО
   ============================================================ */
$totalRow = db_one(
    "SELECT COUNT(*) AS cnt FROM recipe_tag_links WHERE tag_id = ?",
    [(int)$tag['id']]
);
$totalCount = (int)($totalRow['cnt'] ?? 0);
$totalPages = max(1, (int)ceil($totalCount / $perPage));

/* ============================================================
   ИКОНКИ ДЛЯ JS-ШАБЛОНОВ
   ============================================================ */
$jsIcons = [
    'ai'        => icon('ai',        'gc-icon-sm', 1),
    'clock'     => icon('clock',     'gc-icon-sm', 1),
    'dinner'    => icon('dinner',    'gc-icon-sm', 1),
    'flame'     => icon('flame',     'gc-icon-sm', 1),
    'hourglass' => icon('hourglass', 'gc-icon-sm', 1),
    'left'      => icon('left',      'gc-icon-sm', 1),
    'right'     => icon('right',     'gc-icon-sm', 1),
    'search'    => icon('search',    'gc-icon-sm', 1),
    'chef_big'  => icon('chef',      'gc-icon-3xl', 1),
    'search_big'=> icon('search',    'gc-icon-3xl', 1),
];

require_once __DIR__ . '/../includes/header.php';
?>

<script>
window.__TAG_ICONS__ = <?= json_encode($jsIcons, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.__INLINE_AD_HTML__ = <?= json_encode($inlineAdHtml ?: '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>

<!-- Хлебные крошки -->
<nav class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-4 text-sm" aria-label="Хлебные крошки">
    <ol class="flex items-center gap-2 text-gray-400">
        <li><a href="<?= url('') ?>" class="hover:text-amber-600 transition">Главная</a></li>
        <li><span class="text-gray-300">/</span></li>
        <li><a href="<?= url('search') ?>" class="hover:text-amber-600 transition">Рецепты</a></li>
        <li><span class="text-gray-300">/</span></li>
        <li class="text-gray-600 font-semibold"><?= e($tag['name']) ?></li>
    </ol>
</nav>

<!-- Заголовок тега -->
<section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-gradient-to-r from-amber-50 to-orange-50 rounded-2xl shadow-md p-8 mb-8">
        <div class="flex items-center gap-4 mb-4 flex-wrap">
            <?php if (!empty($tag['icon_svg'])): ?>
                <span class="text-amber-500 shrink-0">
                    <?= icon($tag['icon_svg'], 'gc-icon-3xl', 3) ?>
                </span>
            <?php endif; ?>
            <div>
                <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-800">
                    <?= e($tag['name']) ?>
                </h1>
                <p class="text-gray-500 mt-2">
                    <?= e($tag['description'] ?: 'Рецепты с тегом «' . $tag['name'] . '»') ?>
                </p>
            </div>
        </div>
        <div class="flex gap-4 text-sm text-gray-500 items-center">
            <span class="inline-flex items-center gap-1.5">
                <?= icon('openbook', 'gc-icon-sm', 1) ?>
                <span><?= $totalCount ?> рецептов</span>
            </span>
            <span class="inline-flex items-center gap-1.5 text-amber-600 font-semibold">
                <?= icon('tags', 'gc-icon-sm', 1) ?>
                <span><?= e(tag_group_label($tag['tag_group'])) ?></span>
            </span>
        </div>
    </div>

    <!-- Сетка рецептов -->
    <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pb-8"
             x-data="tagPage('<?= e($tag['slug']) ?>')"
             x-init="init()">

        <!-- Счётчик -->
        <div x-show="!loading && total > 0" class="mb-4 text-sm text-gray-500">
            Найдено рецептов: <span class="font-bold text-gray-700" x-text="total"></span>
        </div>

        <!-- Загрузка -->
        <div x-show="loading" class="text-center py-10 text-gray-400">
            <div class="animate-spin inline-block w-8 h-8 border-4 border-amber-200 border-t-amber-500 rounded-full"></div>
            <p class="mt-2">Загрузка...</p>
        </div>

        <!-- Карточки -->
        <div x-show="!loading && results.length > 0"
             class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <template x-for="(rec, index) in results" :key="rec.id">
                
                <!-- ОБЕРТКА display: contents (чтобы Alpine не ломал сетку) -->
                <div style="display: contents;">
                    
                    <!-- Карточка рецепта -->
                    <a :href="rec.slug ? '/recipe/' + rec.slug : '/recipe?id=' + rec.id"
                       class="recipe-card bg-white rounded-2xl shadow-md hover:shadow-lg overflow-hidden block transition hover:-translate-y-1">

                        <!-- Обложка -->
                        <div class="aspect-[4/3] overflow-hidden relative bg-gradient-to-br from-amber-100 to-orange-100">
                            <img :src="rec.image_url || '/uploads/static/static.jpg'"
                                 onerror="this.src='/uploads/static/static.jpg'"
                                 class="w-full h-full object-cover"
                                 :alt="rec.title">
                            <span x-show="rec.is_ai_generated"
                                  class="absolute top-3 left-3 px-2 py-1 rounded-lg text-xs font-bold bg-violet-100/90 text-violet-700 inline-flex items-center gap-1">
                                <span x-html="icons.ai"></span>
                                <span>AI</span>
                            </span>
                            <span class="absolute top-3 right-3 px-2 py-1 rounded-lg text-xs font-bold"
                                  :class="difficultyColor(rec.difficulty)"
                                  x-text="difficultyLabel(rec.difficulty)"></span>
                        </div>

                        <div class="p-4 flex flex-col">

                            <!-- Категория + иконка -->
                            <template x-if="rec.category_slug && rec.category_name">
                                <a :href="'/category/' + rec.category_slug" @click.stop
                                   class="inline-flex items-center gap-1.5 text-xs text-gray-500 hover:text-amber-600 transition mb-2 self-start">
                                    <template x-if="rec.category_icon">
                                        <span x-html="categoryIconHtml(rec.category_icon)"></span>
                                    </template>
                                    <span x-text="rec.category_name"></span>
                                </a>
                            </template>

                            <!-- Заголовок -->
                            <h2 class="font-bold text-gray-800 clamp-2 leading-snug text-base mb-1"
                                x-text="rec.title"></h2>

                            <!-- Описание -->
                            <p class="text-sm text-gray-500 clamp-2 mb-3"
                               x-text="rec.description || ''"></p>

                            <!-- Метаданные -->
                            <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500 mb-3">
                                <span class="inline-flex items-center gap-1">
                                    <span x-html="icons.clock"></span>
                                    <span x-text="((rec.prep_time || 0) + (rec.cook_time || 0)) + ' мин'"></span>
                                </span>
                                <span class="inline-flex items-center gap-1">
                                    <span x-html="icons.dinner"></span>
                                    <span x-text="(rec.servings || 1) + ' порц.'"></span>
                                </span>
                                <span class="inline-flex items-center gap-1">
                                    <span x-html="icons.flame"></span>
                                    <span x-text="(rec.calories || 0) + ' ккал'"></span>
                                </span>
                            </div>

                            <!-- Теги -->
                            <div class="flex flex-wrap gap-1 mt-auto pt-2 border-t border-gray-100">
                                <template x-for="t in (rec.tags_list || []).slice(0, 4)" :key="t.id">
                                    <a :href="'/tag/' + t.slug" @click.stop
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] bg-amber-50 text-amber-600 hover:bg-amber-100 transition shrink-0"
                                    :title="t.tag_group">
                                    <template x-if="t.icon_svg_html">
                                        <span x-html="t.icon_svg_html"></span>
                                    </template>
                                    <span x-text="t.name"></span>
                                    </a>
                                </template>
                            </div>
                        </div>
                    </a>

                    <!-- РЕКЛАМА (Вставляется после 6-й карточки) -->
                    <template x-if="(index + 1) % 6 === 0 && window.__INLINE_AD_HTML__">
                        <article class="ad-card-wrapper bg-white rounded-2xl shadow-md overflow-hidden h-full flex flex-col relative border border-amber-100">
                            <span class="ad-label absolute top-3 right-3 bg-white/90 text-gray-500 text-[10px] font-bold px-2 py-1 rounded-md uppercase tracking-wider z-10 border border-gray-200">Реклама</span>
                            <div class="p-4 flex flex-col justify-center h-full w-full">
                                <div x-html="window.__INLINE_AD_HTML__" class="w-full"></div>
                            </div>
                        </article>
                    </template>

                </div>
            </template>
        </div>

        <!-- Показать ещё -->
        <div x-show="!loading && results.length > 0 && hasMore" class="flex justify-center mt-8">
            <button @click="loadMore()" :disabled="loadingMore"
                    class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-amber-100 text-amber-700 font-bold hover:bg-amber-200 transition disabled:opacity-60">
                <span x-show="!loadingMore">Показать ещё</span>
                <span x-show="loadingMore" style="display:none" class="inline-flex items-center gap-1.5">
                    <span x-html="icons.hourglass"></span>
                    <span>Загрузка…</span>
                </span>
            </button>
        </div>

        <!-- Пагинация -->
        <nav x-show="!loading && results.length > 0 && totalPages > 1"
             class="hidden sm:flex justify-center items-center gap-2 mt-8" aria-label="Пагинация">

            <button type="button" :disabled="page <= 1 || loading" @click="goToPage(page - 1)"
                    class="inline-flex items-center gap-1 px-3 py-2 rounded-xl bg-gray-100 text-gray-600 text-sm hover:bg-gray-200 disabled:opacity-40 transition">
                <span x-html="icons.left"></span>
                <span>Назад</span>
            </button>

            <template x-for="(p, idx) in pageNumbers" :key="idx + '-' + p">
                <span class="inline-flex">
                    <span x-show="p === '…'" class="w-10 h-10 flex items-center justify-center text-gray-400 select-none">…</span>
                    <button type="button" x-show="p !== '…'" @click="goToPage(p)" :disabled="loading"
                            :class="p === page ? 'bg-amber-400 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="w-10 h-10 rounded-xl text-sm font-semibold transition disabled:opacity-50"
                            x-text="p"></button>
                </span>
            </template>

            <button type="button" :disabled="page >= totalPages || loading" @click="goToPage(page + 1)"
                    class="inline-flex items-center gap-1 px-3 py-2 rounded-xl bg-gray-100 text-gray-600 text-sm hover:bg-gray-200 disabled:opacity-40 transition">
                <span>Вперёд</span>
                <span x-html="icons.right"></span>
            </button>
        </nav>

        <div x-show="!loading && results.length > 0" class="text-center text-xs text-gray-400 mt-3"
             x-text="'Показано ' + results.length + ' из ' + total"></div>

        <!-- Нет результатов -->
        <div x-show="!loading && results.length === 0" class="text-center py-16">
            <div class="mb-4 text-gray-300 flex justify-center" x-html="icons.chef_big"></div>
            <h2 class="text-xl font-bold text-gray-700 mb-2">Рецепты скоро появятся</h2>
            <p class="text-gray-500 mb-6">В этом теге пока нет рецептов</p>
            <a href="<?= url('create-recipe') ?>"
               class="inline-block px-6 py-3 rounded-xl bg-amber-100 text-amber-700 font-bold hover:bg-amber-200 transition">
                Добавить первый рецепт
            </a>
        </div>
    </section>
</section>

<!-- Хелперы для Alpine -->
<script>
    window.DIFFICULTY_LABELS = {
        easy:    'Легко',
        medium:  'Средне',
        hard:    'Сложно',
        master:  'Мастер',
        unknown: '—'
    };
    window.DIFFICULTY_COLORS = {
        easy:    'bg-emerald-100 text-emerald-700',
        medium:  'bg-amber-100 text-amber-700',
        hard:    'bg-rose-100 text-rose-700',
        master:  'bg-violet-100 text-violet-700',
        unknown: 'bg-gray-100 text-gray-500'
    };
    window.difficultyLabel = v => window.DIFFICULTY_LABELS[String(v || '').toLowerCase().trim()] || '—';
    window.difficultyColor = v => window.DIFFICULTY_COLORS[String(v || '').toLowerCase().trim()] || window.DIFFICULTY_COLORS.unknown;
</script>

<script>
function tagPage(slug) {
    return {
        results: [],
        total: 0,
        page: 1,
        totalPages: 1,
        loading: true,
        loadingMore: false,
        hasMore: false,

        // Иконки для x-html в шаблонах
        icons: window.__TAG_ICONS__ || {},

        // HTML иконки категории: получаем имя иконки из API,
        categoryIconCache: {},

        async init() {
            const params = new URLSearchParams(window.location.search);
            this.page = Math.max(1, parseInt(params.get('page') || '1', 10));
            await this.loadPage(this.page);
        },

        async ensureCategoryIcon(iconName) {
            if (!iconName) return '';
            if (this.categoryIconCache[iconName]) {
                return this.categoryIconCache[iconName];
            }
            try {
                const res = await fetch('/api/categories.php');
                const data = await res.json();
                const list = data.items || [];
                for (const c of list) {
                    if (c.icon_svg) {
                        this.categoryIconCache[c.icon_svg] = c.icon_svg_html || '';
                    }
                }
            } catch (e) {}
            return this.categoryIconCache[iconName] || '';
        },

        categoryIconHtml(iconName) {
            if (!iconName) return '';
            if (this.categoryIconCache[iconName]) {
                return this.categoryIconCache[iconName];
            }
            this.ensureCategoryIcon(iconName);
            return '';
        },

        async loadPage(p) {
            this.loading = true;
            try {
                const url = new URL('/api/tag_recipes.php', window.location.origin);
                url.searchParams.set('slug', slug);
                url.searchParams.set('page', p);

                const res  = await fetch(url.toString());
                const data = await res.json();

                this.results    = data.items || [];
                this.total      = parseInt(data.total || 0, 10);
                this.page       = parseInt(data.page || 1, 10);
                this.totalPages = parseInt(data.total_pages || 1, 10);
                this.hasMore    = this.page < this.totalPages;

                const names = Array.from(new Set(
                    this.results.map(r => r.category_icon).filter(Boolean)
                ));
                if (names.length) {
                    await this.ensureCategoryIcon(names[0]);
                }

                this.syncUrl();
            } catch (e) {
                console.error(e);
            } finally {
                this.loading = false;
            }
        },

        async loadMore() {
            if (this.loadingMore || !this.hasMore) return;
            this.loadingMore = true;
            try {
                const next = this.page + 1;
                const url = new URL('/api/tag_recipes.php', window.location.origin);
                url.searchParams.set('slug', slug);
                url.searchParams.set('page', next);

                const res  = await fetch(url.toString());
                const data = await res.json();

                // Защита от дублей (как в search/category)
                const items = data.items || [];
                const existingIds = new Set(this.results.map(item => String(item.id)));
                const newItems = items.filter(item => 
                    item.id != null && !existingIds.has(String(item.id))
                );

                this.results    = this.results.concat(newItems);
                this.page       = parseInt(data.page || next, 10);
                this.totalPages = parseInt(data.total_pages || 1, 10);
                this.hasMore    = this.page < this.totalPages;

                this.syncUrl();
            } catch (e) {
                console.error(e);
            } finally {
                this.loadingMore = false;
            }
        },

        goToPage(p) {
            if (p === '…' || p < 1 || p > this.totalPages || p === this.page) return;
            this.loadPage(p);
            this.$nextTick(() => {
                document.querySelector('section[x-data]')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        },

        syncUrl() {
            const url = new URL(window.location);
            if (this.page > 1) url.searchParams.set('page', this.page);
            else url.searchParams.delete('page');
            window.history.replaceState({}, '', url);
        },

        get pageNumbers() {
            const current = this.page, total = this.totalPages;
            if (total <= 1) return [1];
            const delta = 1, edge = 1, pages = [];
            const left  = Math.max(2, current - delta);
            const right = Math.min(total - 1, current + delta);
            for (let i = 1; i <= Math.min(edge, total); i++) pages.push(i);
            if (left > edge + 1) pages.push('…');
            for (let i = left; i <= right; i++) pages.push(i);
            if (right < total - edge) pages.push('…');
            for (let i = Math.max(total - edge + 1, right + 1); i <= total; i++) pages.push(i);
            return [...new Set(pages)];
        }
    };
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>