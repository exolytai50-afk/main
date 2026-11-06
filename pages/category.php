<?php
require_once __DIR__ . '/../config/config.php';

// Получаем HTML-код рекламы
$inlineAdHtml = get_ad_code('feed_inline');

// Функция транслитерации
if (!function_exists('transliterate')) {
    function transliterate($string) {
        $translit = ['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya'];
        return strtr($string, $translit);
    }
}

if (!function_exists('generateSlug')) {
    function generateSlug($string) {
        $string = transliterate($string);
        $string = mb_strtolower($string);
        $string = str_replace([' ', '_', '/', '\\', ',', '.', '!', '?', '(', ')', '[', ']', '{', '}', '+', '=', '&', '#', '@', '$', '%', '^', '*', ':', ';', '"', "'", '<', '>', '|', '~', '`'], '-', $string);
        $string = preg_replace('/[^a-z0-9\-]/u', '', $string);
        $string = preg_replace('/-+/', '-', $string);
        return trim($string, '-');
    }
}

$slug = trim((string)($_GET['slug'] ?? ''));

if (empty($slug)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

if (preg_match('/[а-яА-ЯёЁ]/u', $slug)) {
    $slug = generateSlug($slug);
}

$category = db_one('SELECT * FROM recipe_categories WHERE slug = ? AND is_active = 1', [$slug]);

if (!$category) {
    $category = db_one('SELECT * FROM recipe_categories WHERE LOWER(name) = ? AND is_active = 1', [mb_strtolower($slug)]);
}

if (!$category && is_numeric($slug)) {
    $category = db_one('SELECT * FROM recipe_categories WHERE id = ? AND is_active = 1', [(int)$slug]);
}

if (!$category) {
    $existingCuisine = db_one('SELECT DISTINCT cuisine FROM recipes WHERE LOWER(cuisine) = ? LIMIT 1', [mb_strtolower($slug)]);
    if (!$existingCuisine) {
        $allCuisines = db_all('SELECT DISTINCT cuisine FROM recipes');
        if ($allCuisines) {
            foreach ($allCuisines as $cuisine) {
                if (generateSlug($cuisine['cuisine']) === $slug) {
                    $existingCuisine = ['cuisine' => $cuisine['cuisine']];
                    break;
                }
            }
        }
    }
    if ($existingCuisine) {
        $category = [
            'name' => $existingCuisine['cuisine'], 'slug' => $slug,
            'description' => 'Рецепты в категории ' . $existingCuisine['cuisine'],
            'icon_svg' => 'cookbook',
            'meta_title' => $existingCuisine['cuisine'] . ' — рецепты на GigaChef',
            'meta_description' => 'Лучшие рецепты в категории ' . $existingCuisine['cuisine'] . '.',
            'difficulty_level' => null,
        ];
    } else {
        http_response_code(404);
        require __DIR__ . '/404.php';
        exit;
    }
}

$pageTitle = $category['meta_title'] ?: $category['name'] . ' — рецепты на GigaChef';
$pageDescription = $category['meta_description'] ?: 'Лучшие рецепты в категории ' . $category['name'] . '.';
$pageUrl = url('category/' . $category['slug']);

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset  = ($page - 1) * $perPage;

$categoryId = !empty($category['id']) ? (int)$category['id'] : 0;

if ($categoryId > 0) {
    $where  = 'r.category_id = ?';
    $params = [$categoryId];
} else {
    $where  = 'r.cuisine = ?';
    $params = [$category['name']];
}

$recipes = db_all(
    "SELECT r.*, u.display_name AS author_name, u.avatar_url AS author_avatar_url,
            u.gender AS author_gender, c.slug AS category_slug, c.name AS category_name, c.icon_svg AS category_icon
     FROM recipes r
     LEFT JOIN users u ON u.email = r.author_email
     LEFT JOIN recipe_categories c ON c.id = r.category_id
     WHERE {$where} ORDER BY r.created_at DESC LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $offset])
);

if (!empty($recipes)) {
    $ids = array_column($recipes, 'id');
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $tagRows = db_all(
        "SELECT l.recipe_id, t.id, t.slug, t.name, t.icon_svg, t.tag_group
         FROM recipe_tag_links l JOIN recipe_tags t ON t.id = l.tag_id
         WHERE l.recipe_id IN ({$ph}) AND t.is_active = 1 ORDER BY t.tag_group, t.sort_order, t.slug",
        $ids
    );
    $byRecipe = [];
    foreach ($tagRows as $row) {
        $byRecipe[$row['recipe_id']][] = [
            'id' => (int)$row['id'], 'slug' => $row['slug'], 'name' => $row['name'],
            'icon_svg' => $row['icon_svg'], 'tag_group' => $row['tag_group'],
        ];
    }
    foreach ($recipes as &$r) {
        $r['tags_list'] = $byRecipe[$r['id']] ?? [];
    }
    unset($r);
}

$totalRow = db_one("SELECT COUNT(*) AS cnt FROM recipes r WHERE {$where}", $params);
$totalCount = (int)($totalRow['cnt'] ?? 0);
$totalPages = max(1, (int)ceil($totalCount / $perPage));

require_once __DIR__ . '/../includes/header.php';
?>

<nav class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-4 text-sm" aria-label="Хлебные крошки">
    <ol class="flex items-center gap-2 text-gray-400">
        <li><a href="<?= url('') ?>" class="hover:text-amber-600 transition">Главная</a></li>
        <li><span class="text-gray-300">/</span></li>
        <li><a href="<?= url('search') ?>" class="hover:text-amber-600 transition">Рецепты</a></li>
        <li><span class="text-gray-300">/</span></li>
        <li class="text-gray-600 font-semibold"><?= e($category['name']) ?></li>
    </ol>
</nav>

<section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-gradient-to-r from-amber-50 to-orange-50 rounded-2xl shadow-md p-8 mb-8">
        <div class="flex items-center gap-4 mb-4">
            <?php if (!empty($category['icon_svg'])): ?>
                <span class="gc-icon-xl" style="display:inline-flex;width:3em;height:3em;vertical-align:-0.2em"><?= icon($category['icon_svg'], 'gc-icon-xl', 3) ?></span>
            <?php else: ?>
                <span class="gc-icon-xl" style="display:inline-flex;width:3em;height:3em;vertical-align:-0.2em"><?= icon('cookbook', 'gc-icon-xl', 3) ?></span>
            <?php endif; ?>
            <div>
                <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-800"><?= e($category['h1'] ?? $category['name']) ?></h1>
                <p class="text-gray-500 mt-2"><?= e($category['description'] ?: 'Рецепты в категории ' . $category['name']) ?></p>
            </div>
        </div>
        <div class="flex flex-wrap gap-4 text-sm text-gray-500">
            <span class="inline-flex items-center gap-1"><?= icon('openbook') ?> <span><?= $totalCount ?> рецептов</span></span>
        </div>
    </div>
</section>

<!-- СЕТКА КАРТОЧЕК -->
<section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pb-8" x-data="categoryPage('<?= e($category['slug']) ?>')" x-init="init()">

    <div x-show="!loading && total > 0" x-cloak class="mb-4 text-sm text-gray-500">
        Найдено рецептов: <span class="font-bold text-gray-700" x-text="total"></span>
    </div>

    <div x-show="loading" x-cloak class="text-center py-10 text-gray-400">
        <div class="animate-spin inline-block w-8 h-8 border-4 border-amber-200 border-t-amber-500 rounded-full"></div>
        <p class="mt-2">Загрузка...</p>
    </div>

    <div x-show="!loading && results.length > 0" x-cloak class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <template x-for="(rec, index) in results" :key="rec.id">
            <div style="display: contents;">
        
            <!-- Карточка рецепта -->
            <article class="recipe-card bg-white rounded-2xl shadow-md hover:shadow-lg overflow-hidden transition hover:-translate-y-1 flex flex-col">
                <a :href="rec.slug ? '/recipe/' + rec.slug : '/recipe?id=' + rec.id" class="block flex-1 flex flex-col">
                    <div class="aspect-[4/3] overflow-hidden relative bg-gradient-to-br from-amber-100 to-orange-100 shrink-0">
                        <img :src="rec.image_url || '/uploads/static/static.jpg'" onerror="this.src='/uploads/static/static.jpg'" class="w-full h-full object-cover" loading="lazy" :alt="rec.title">
                        <span x-show="rec.is_ai_generated" class="absolute top-3 left-3 px-2 py-1 rounded-lg text-xs font-bold bg-violet-100/90 text-violet-700 inline-flex items-center gap-1"><span x-html="icons.ai"></span><span>AI</span></span>
                        <span class="absolute top-3 right-3 px-2 py-1 rounded-lg text-xs font-bold" :class="difficultyColor(rec.difficulty)" x-text="difficultyLabel(rec.difficulty)"></span>
                    </div>
                    <div class="p-4 flex flex-col flex-1">
                        <template x-if="rec.category_slug && rec.category_name">
                            <a :href="'/category/' + rec.category_slug" @click.stop class="inline-flex items-center gap-1.5 text-xs text-gray-500 hover:text-amber-600 transition mb-2 self-start">
                                <template x-if="rec.category_icon_svg_html"><span x-html="rec.category_icon_svg_html"></span></template>
                                <span x-text="rec.category_name"></span>
                            </a>
                        </template>
                        <h2 class="font-bold text-gray-800 clamp-2 leading-snug text-base mb-1" x-text="rec.title"></h2>
                        <p class="text-sm text-gray-500 clamp-2 mb-3" x-text="rec.description || ''"></p>
                        <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500 mb-3">
                            <span class="inline-flex items-center gap-1"><span x-html="icons.time"></span><span x-text="((rec.prep_time || 0) + (rec.cook_time || 0)) + ' мин'"></span></span>
                            <span class="inline-flex items-center gap-1"><span x-html="icons.rest"></span><span x-text="(rec.servings || 1) + ' порц.'"></span></span>
                            <span class="inline-flex items-center gap-1"><span x-html="icons.lightning"></span><span x-text="(rec.calories || 0) + ' ккал'"></span></span>
                        </div>
                        <div class="flex flex-wrap gap-1 mt-auto pt-2 border-t border-gray-100">
                            <template x-for="t in (rec.tags_list || []).slice(0, 4)" :key="t.id">
                                <a :href="'/tag/' + t.slug" @click.stop class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] bg-amber-50 text-amber-600 hover:bg-amber-100 transition shrink-0" :title="t.tag_group">
                                    <template x-if="t.icon_svg_html"><span x-html="t.icon_svg_html"></span></template>
                                    <span x-text="t.name"></span>
                                </a>
                            </template>
                        </div>
                    </div>
                </a>
            </article>

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

    <div x-show="!loading && results.length > 0 && hasMore" x-cloak class="flex justify-center mt-8">
        <button @click="loadMore()" :disabled="loadingMore" class="px-6 py-3 rounded-xl bg-amber-100 text-amber-700 font-bold hover:bg-amber-200 transition disabled:opacity-60 inline-flex items-center gap-2">
            <span x-show="!loadingMore">Показать ещё</span>
            <span x-show="loadingMore" x-cloak class="inline-flex items-center gap-1"><?= icon('hourglass') ?><span>Загрузка…</span></span>
        </button>
    </div>

    <nav x-show="!loading && results.length > 0 && totalPages > 1" x-cloak class="hidden sm:flex justify-center items-center gap-2 mt-8" aria-label="Пагинация">
        <button type="button" :disabled="page <= 1 || loading" @click="goToPage(page - 1)" class="px-3 py-2 rounded-xl bg-gray-100 text-gray-600 text-sm hover:bg-gray-200 disabled:opacity-40 transition inline-flex items-center gap-1"><?= icon('left') ?><span>Назад</span></button>
        <template x-for="(p, idx) in pageNumbers" :key="idx + '-' + p">
            <span class="inline-flex">
                <span x-show="p === '…'" class="w-10 h-10 flex items-center justify-center text-gray-400 select-none">…</span>
                <button type="button" x-show="p !== '…'" @click="goToPage(p)" :disabled="loading" :class="p === page ? 'bg-amber-400 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'" class="w-10 h-10 rounded-xl text-sm font-semibold transition disabled:opacity-50" x-text="p"></button>
            </span>
        </template>
        <button type="button" :disabled="page >= totalPages || loading" @click="goToPage(page + 1)" class="px-3 py-2 rounded-xl bg-gray-100 text-gray-600 text-sm hover:bg-gray-200 disabled:opacity-40 transition inline-flex items-center gap-1"><span>Вперёд</span><?= icon('right') ?></button>
    </nav>

    <div x-show="!loading && results.length > 0" x-cloak class="text-center text-xs text-gray-400 mt-3" x-text="'Показано ' + results.length + ' из ' + total"></div>

    <div x-show="!loading && results.length === 0" x-cloak class="text-center py-16">
        <div class="flex justify-center mb-4"><span class="gc-icon-xl" style="display:inline-flex;width:4em;height:4em"><?= icon('cookbook', 'gc-icon-xl', 4) ?></span></div>
        <h2 class="text-xl font-bold text-gray-700 mb-2">Рецепты скоро появятся</h2>
        <p class="text-gray-500 mb-6">В этой категории пока нет рецептов</p>
        <a href="<?= url('create-recipe') ?>" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-amber-100 text-amber-700 font-bold hover:bg-amber-200 transition"><?= icon('plus') ?><span>Добавить первый рецепт</span></a>
    </div>
</section>

<script>
window.DIFFICULTY_LABELS = { easy: 'Легко', medium: 'Средне', hard: 'Сложно', master: 'Мастер', unknown: '—' };
window.DIFFICULTY_COLORS = { easy: 'bg-emerald-100 text-emerald-700', medium: 'bg-amber-100 text-amber-700', hard: 'bg-rose-100 text-rose-700', master: 'bg-violet-100 text-violet-700', unknown: 'bg-gray-100 text-gray-500' };
window.difficultyLabel = v => window.DIFFICULTY_LABELS[String(v || '').toLowerCase().trim()] || '—';
window.difficultyColor = v => window.DIFFICULTY_COLORS[String(v || '').toLowerCase().trim()] || window.DIFFICULTY_COLORS.unknown;

window.__CATEGORY_ICONS__ = {
    time:      <?= json_encode(icon('time',      'gc-icon-sm', 1), JSON_UNESCAPED_UNICODE) ?>,
    rest:      <?= json_encode(icon('rest',      'gc-icon-sm', 1), JSON_UNESCAPED_UNICODE) ?>,
    lightning: <?= json_encode(icon('lightning', 'gc-icon-sm', 1), JSON_UNESCAPED_UNICODE) ?>,
    ai:        <?= json_encode(icon('ai',        'gc-icon-sm', 1), JSON_UNESCAPED_UNICODE) ?>,
    hourglass: <?= json_encode(icon('hourglass', 'gc-icon-sm', 1), JSON_UNESCAPED_UNICODE) ?>,
};
window.__INLINE_AD_HTML__ = <?= json_encode($inlineAdHtml ?: '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script>
function categoryPage(slug) {
    return {
        results: [],
        total: 0,
        page: 1,
        totalPages: 1,
        loading: true,
        loadingMore: false,
        hasMore: false,
        icons: window.__CATEGORY_ICONS__ || {},
        inlineAdHtml: window.__INLINE_AD_HTML__ || '',

        _getApiUrl(pageNum) {
            const url = new URL('/api/category_recipes.php', window.location.origin);
            url.searchParams.set('slug', slug);
            url.searchParams.set('page', pageNum);
            return url.toString();
        },

        async init() {
            const params = new URLSearchParams(window.location.search);
            this.page = Math.max(1, parseInt(params.get('page') || '1', 10));
            await this.loadPage(this.page);
        },

        async loadPage(p) {
            this.loading = true;
            try {
                const url = this._getApiUrl(p);
                const res  = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const data = await res.json();
                
                if (data.error) throw new Error(data.error);

                const items = Array.isArray(data.items) ? data.items : [];

                // Убираем дубликаты
                const uniqueItems = [];
                const seenIds = new Set();
                items.forEach(item => {
                    if (item && item.id != null && !seenIds.has(String(item.id))) {
                        seenIds.add(String(item.id));
                        uniqueItems.push(item);
                    }
                });

                this.results    = uniqueItems;
                this.total      = parseInt(data.total || 0, 10);
                this.page       = parseInt(data.page || 1, 10);
                this.totalPages = parseInt(data.total_pages || 1, 10);
                this.hasMore    = typeof data.hasMore === 'boolean' ? data.hasMore : this.page < this.totalPages;
                
                this.syncUrl();
            } catch (e) { 
                console.error('Load page error:', e); 
            } finally { 
                this.loading = false; 
            }
        },

        async loadMore() {
            if (this.loadingMore || this.loading || !this.hasMore) return;

            this.loadingMore = true;
            const nextPage = this.page + 1;

            try {
                const url = this._getApiUrl(nextPage);
                const res = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const data = await res.json();
                if (data.error) throw new Error(data.error);

                const items = Array.isArray(data.items) ? data.items : [];
                
                if (items.length === 0) {
                    this.hasMore = false;
                    return;
                }

                // Убираем дубликаты
                const existingIds = new Set(this.results.map(item => String(item.id)));
                const newItems = items.filter(item => 
                    item.id != null && !existingIds.has(String(item.id))
                );

                this.results = [...this.results, ...newItems];
                this.page = nextPage;
                this.total = parseInt(data.total, 10) || this.total;
                this.totalPages = Math.max(1, parseInt(data.total_pages, 10) || this.totalPages);
                this.hasMore = typeof data.hasMore === 'boolean' ? data.hasMore : this.page < this.totalPages;

                this.syncUrl();
            } catch (error) {
                console.error('Ошибка загрузки рецептов:', error);
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