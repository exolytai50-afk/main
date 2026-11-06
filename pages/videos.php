<?php
require_once __DIR__ . '/../config/config.php';

// Загружаем мета-теги для страницы
$meta = get_page_meta('/videos');
if ($meta) {
    apply_meta($meta);
}

// Устанавливаем заголовок страницы
$pageTitle = $GLOBALS['pageTitle'] ?? 'Видео рецепты — смотрите и готовьте | GigaChef';
$pageH1 = $GLOBALS['pageH1'] ?? 'Видео рецептов';
$pageDescription = $GLOBALS['pageDescription'] ?? 'Смотрите видеоуроки по приготовлению блюд от пользователей GigaChef. Пошаговые видео-рецепты.';

$GLOBALS['pageH1'] = $pageH1;
$GLOBALS['pageTitle'] = $pageTitle;
$GLOBALS['pageDescription'] = $pageDescription;

require_once __DIR__ . '/../includes/header.php';

// Проверяем существование полей в БД
$videoFieldExists = false;
try {
    $columns = db_all("SHOW COLUMNS FROM recipes LIKE 'video_url'");
    $videoFieldExists = !empty($columns);
} catch (Exception $e) {
    $videoFieldExists = false;
}

// Получаем все рецепты с видео (гибридный подход)
$videos = [];
if ($videoFieldExists) {
    $videos = db_all(
        "SELECT id, title, slug, video_url, video_owner_id, video_id, image_url, description, cuisine, 
                prep_time, cook_time, servings, calories, likes_count, views_count,
                created_at
         FROM recipes 
         WHERE (video_url IS NOT NULL AND video_url != '') 
            OR (video_owner_id IS NOT NULL AND video_id IS NOT NULL)
         ORDER BY created_at DESC"
    );
}
?>

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-8" 
         x-data="{
            search: '',
            platform: 'all',
            sortBy: 'newest',
            videos: <?= json_encode($videos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            
            get filteredVideos() {
                let filtered = [...this.videos];
                
                if (this.search.trim()) {
                    const query = this.search.toLowerCase();
                    filtered = filtered.filter(v => 
                        (v.title && v.title.toLowerCase().includes(query)) ||
                        (v.description && v.description.toLowerCase().includes(query)) ||
                        (v.cuisine && v.cuisine.toLowerCase().includes(query))
                    );
                }
                
                if (this.platform !== 'all') {
                    filtered = filtered.filter(v => {
                        if (this.platform === 'vk') {
                            return !!(v.video_owner_id && v.video_id);
                        }
                        const videoUrl = v.video_url || '';
                        if (this.platform === 'rutube') return videoUrl.includes('rutube.ru');
                        if (this.platform === 'youtube') return videoUrl.includes('youtube.com') || videoUrl.includes('youtu.be');
                        if (this.platform === 'vimeo') return videoUrl.includes('vimeo.com');
                        return true;
                    });
                }
                
                if (this.sortBy === 'newest') {
                    filtered.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
                } else if (this.sortBy === 'popular') {
                    filtered.sort((a, b) => (b.views_count || 0) - (a.views_count || 0));
                } else if (this.sortBy === 'liked') {
                    filtered.sort((a, b) => (b.likes_count || 0) - (a.likes_count || 0));
                }
                
                return filtered;
            },
            
            getEmbedUrl(video) {
                if (!video) return '';
                
                if (video.video_owner_id && video.video_id) {
                    return 'https://vk.com/video_ext.php?oid=' + video.video_owner_id + '&id=' + video.video_id;
                }
                
                const url = video.video_url || '';
                if (!url) return '';
                
                const rutubeMatch = url.match(/rutube\.ru\/(?:video|shorts|live)\/([a-zA-Z0-9_-]+)/i);
                if (rutubeMatch) {
                    return 'https://rutube.ru/play/embed/' + rutubeMatch[1] + '/';
                }
                
                const youtubeMatch = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/i);
                if (youtubeMatch) {
                    return 'https://www.youtube.com/embed/' + youtubeMatch[1];
                }
                
                const vimeoMatch = url.match(/vimeo\.com\/(\d+)/i);
                if (vimeoMatch) {
                    return 'https://player.vimeo.com/video/' + vimeoMatch[1];
                }
                
                if (url.includes('embed') || url.includes('play/embed')) {
                    return url;
                }
                
                return url;
            },
            
            getPlatform(video) {
                if (video.video_owner_id) return 'VK Видео';
                
                const url = video.video_url || '';
                if (!url) return 'Видео';
                if (url.includes('rutube.ru')) return 'Rutube';
                if (url.includes('youtube.com') || url.includes('youtu.be')) return 'YouTube';
                if (url.includes('vimeo.com')) return 'Vimeo';
                return 'Видео';
            }
         }">
    
    <div class="text-center mb-8" data-aos="fade-up">
        <span class="inline-block px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold mb-3"><?= icon('video', '', 1.5) ?> Видео</span>
        <h1 class="text-3xl font-extrabold text-gray-800"><?= icon('video', '', 1.5) ?> <?= e($pageH1) ?></h1>
        <p class="mt-2 text-gray-500">Смотрите видеоуроки по приготовлению блюд от наших пользователей</p>
    </div>

    <?php if (!$videoFieldExists): ?>
        <div class="bg-yellow-50 border border-yellow-200 rounded-2xl p-6 mb-6 text-center">
            <div class="text-4xl mb-3"><?= icon('warning', '', 1.5) ?></div>
            <h3 class="font-bold text-yellow-800 mb-2">Поле video_url не найдено</h3>
            <p class="text-yellow-600 text-sm mb-4">Для работы с видео необходимо добавить поле в базу данных</p>
            <pre class="bg-white rounded-lg p-4 text-left text-xs text-gray-600 overflow-x-auto mb-4">ALTER TABLE recipes ADD COLUMN video_url VARCHAR(500) DEFAULT NULL AFTER image_url;</pre>
            <button onclick="navigator.clipboard.writeText('ALTER TABLE recipes ADD COLUMN video_url VARCHAR(500) DEFAULT NULL AFTER image_url;')" 
                    class="px-4 py-2 rounded-xl bg-yellow-100 text-yellow-700 font-bold text-sm hover:bg-yellow-200 transition">
                <?= icon('copy', '', 1.5) ?> Копировать SQL
            </button>
        </div>
    <?php elseif (empty($videos)): ?>
        <div class="text-center py-16" data-aos="fade-up">
            <div class="text-6xl mb-4"><?= icon('stories', '', 1.5) ?></div>
            <h3 class="text-xl font-bold text-gray-700 mb-2">Видео пока нет</h3>
            <p class="text-gray-500 mb-6">Станьте первым, кто добавит видео к рецепту!</p>
            <a href="<?= url('create-recipe') ?>" class="px-5 py-2.5 rounded-xl bg-amber-100 text-amber-700 font-bold hover:bg-amber-200 transition">
                <?= icon('document', '', 1.5) ?> Создать рецепт с видео
            </a>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-2xl shadow-md p-4 mb-6" data-aos="fade-up" data-aos-delay="100">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Поиск</label>
                    <input type="text" x-model="search" 
                           placeholder="Название рецепта, кухня..."
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 outline-none focus:border-emerald-300 text-sm">
                </div>
                
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Платформа</label>
                    <select x-model="platform" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 outline-none text-sm">
                        <option value="all">Все платформы</option>
                        <option value="vk">VK Видео</option>
                        <option value="rutube">Rutube</option>
                        <option value="youtube">YouTube</option>
                        <option value="vimeo">Vimeo</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Сортировка</label>
                    <select x-model="sortBy" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 outline-none text-sm">
                        <option value="newest">Сначала новые</option>
                        <option value="popular">По просмотрам</option>
                        <option value="liked">По лайкам</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="mb-4 text-sm text-gray-500" data-aos="fade-up">
            Найдено видео: <span class="font-bold text-gray-700" x-text="filteredVideos.length"></span>
        </div>

        <div x-show="filteredVideos.length > 0" 
             class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <template x-for="video in filteredVideos" :key="video.id">
                <div class="bg-white rounded-2xl shadow-md overflow-hidden hover:shadow-lg transition">
                    <div class="aspect-video bg-black relative">
                        <!-- ИСПРАВЛЕНО: передаем весь объект video -->
                        <iframe x-show="getEmbedUrl(video)" 
                                :src="getEmbedUrl(video)" 
                                class="w-full h-full" 
                                frameborder="0" 
                                allow="clipboard-write; autoplay; fullscreen" 
                                allowfullscreen></iframe>
                        
                        <!-- ИСПРАВЛЕНО: передаем весь объект video -->
                        <span class="absolute top-3 left-3 px-2.5 py-1 rounded-lg text-xs font-bold bg-black/70 text-white"
                              x-text="getPlatform(video)"></span>
                        
                        <span class="absolute top-3 right-3 px-2.5 py-1 rounded-lg text-xs font-bold bg-black/70 text-white">
                            <?= icon('time', '', 1.5) ?> <span x-text="(video.prep_time || 0) + (video.cook_time || 0)"></span> мин
                        </span>
                    </div>
                    
                    <div class="p-4">
                        <h3 class="font-bold text-gray-800 clamp-2 leading-snug mb-2" x-text="video.title"></h3>
                        
                        <p class="text-sm text-gray-500 clamp-2 mb-3" x-text="video.description || ''"></p>
                        
                        <div class="flex items-center justify-between text-xs text-gray-400 mb-3">
                            <span x-text="video.cuisine || 'Другая кухня'"></span>
                            <span class="flex items-center gap-3">
                                <span><?= icon('eye', '', 1.5) ?> <span x-text="video.views_count || 0"></span></span>
                                <span><?= icon('heart', '', 1.5) ?> <span x-text="video.likes_count || 0"></span></span>
                            </span>
                        </div>
                        
                        <div class="flex gap-2">
                            <a :href="video.slug ? '/recipe/' + video.slug : '/recipe?id=' + video.id" 
                               class="flex-1 py-2 rounded-xl bg-amber-50 text-amber-700 text-xs font-bold hover:bg-amber-100 transition text-center">
                                <?= icon('openbook', '', 1.5) ?> Открыть рецепт
                            </a>
                            <a :href="video.video_owner_id ? 'https://vk.com/video' + video.video_owner_id + '_' + video.video_id : video.video_url" 
                               target="_blank" rel="noopener"
                               class="flex-1 py-2 rounded-xl bg-emerald-50 text-emerald-700 text-xs font-bold hover:bg-emerald-100 transition text-center">
                                <?= icon('play-circle', '', 1.5) ?> Смотреть
                            </a>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div x-show="filteredVideos.length === 0" class="text-center py-16" data-aos="fade-up">
            <div class="text-6xl mb-4"><?= icon('search', '', 1.5) ?></div>
            <h3 class="text-xl font-bold text-gray-700 mb-2">Видео не найдены</h3>
            <p class="text-gray-500 mb-6">Попробуйте изменить параметры поиска</p>
            <button @click="search = ''; platform = 'all'; sortBy = 'newest'" 
                    class="px-5 py-2.5 rounded-xl bg-amber-100 text-amber-700 font-bold hover:bg-amber-200 transition">
                Сбросить фильтры
            </button>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>