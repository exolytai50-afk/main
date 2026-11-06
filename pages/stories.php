<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$user = current_user();

$pageTitle = 'Истории — GigaChef';
$pageDescription = 'Делитесь фото и видео блюд — истории живут неделю';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="max-w-4xl mx-auto px-4 sm:px-6 py-8" x-data="storiesPage()">
    <h1 class="text-3xl font-extrabold text-gray-800 mb-6 text-center inline-flex items-center justify-center gap-2 w-full" data-aos="fade-up">
        <?= icon('stories', 'gc-icon-md') ?> 
        <span><?= e($pageH1) ?></span>
    </h1>

    <!-- Создание истории -->
    <div class="bg-white rounded-2xl shadow-md p-4 mb-6" data-aos="fade-up">
        <div class="flex items-center gap-3">
            <?= render_avatar(
    $user,
    'w-12 h-12 rounded-full object-cover ring-2 ring-amber-300',
    'w-12 h-12 rounded-full bg-amber-100 ring-2 ring-amber-300 flex items-center justify-center text-amber-600',
    'text-2xl'
) ?>
            <div class="flex-1">
                <div class="font-bold text-gray-800 text-sm">Ваша история</div>
                <div class="text-xs text-gray-400">Фото или видео — живёт 7 дней</div>
            </div>
            <label class="px-4 py-2 rounded-xl bg-amber-100 text-amber-700 text-sm font-bold cursor-pointer hover:bg-amber-200 transition">
                <span x-show="!uploading"><?= icon('plus', 'gc-icon-xs') ?> Добавить</span>
                <span x-show="uploading" style="display:none">
                    <?= icon('hourglass') ?>
                    Загрузка...</span>
                <input type="file" accept="image/*,video/*" class="hidden" @change="uploadStory($event)" :disabled="uploading">
            </label>
        </div>

        <!-- Превью загружаемого файла -->
        <div x-show="previewUrl" style="display:none" class="mt-3 relative">
            <template x-if="previewIsVideo">
                <video :src="previewUrl" class="w-full max-h-48 object-cover rounded-xl" controls muted></video>
            </template>
            <template x-if="!previewIsVideo">
                <img :src="previewUrl" class="w-full max-h-48 object-cover rounded-xl" alt="Превью">
            </template>
            <button @click="previewUrl = ''" class="absolute top-2 right-2 bg-white/90 rounded-full p-1.5 shadow-md">
                <?= icon('close', 'gc-icon-xs') ?>
            </button>
        </div>
    </div>

    <!-- Список историй -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4" data-aos="fade-up">
    <template x-for="story in stories" :key="story.id">
        <div class="relative rounded-2xl overflow-hidden shadow-md hover:shadow-lg transition group cursor-pointer"
     @click="openViewer(stories.indexOf(story))">
            <!-- Обёртка с фиксированным соотношением сторон -->
            <div class="story-card-media-wrapper">
                <!-- Фото -->
                <template x-if="story.media_type !== 'video'">
                    <img :src="story.image_url || '/uploads/static/static.jpg'"
                         class="story-card-img"
                         :alt="story.user_name || ''"
                         loading="lazy"
                         onerror="this.onerror=null;this.src='/uploads/static/static.jpg'">
                </template>

                <!-- Видео: Rutube thumbnail или локальный video -->
                <template x-if="story.media_type === 'video'">
                    <div class="story-card-media-wrapper">
                        <!-- Рендер Rutube обложки (если есть) -->
                        <template x-if="story.rutube_id">
                            <img :src="'https://pic.rutube.ru/video/' + story.rutube_id + '.jpg'"
                                class="story-card-img"
                                loading="lazy"
                                onerror="this.onerror=null;this.style.display='none';this.parentElement.classList.add('bg-gray-800')">
                        </template>
        
                        <!-- Рендер локального видео (если rutube_id еще нет) -->
                        <template x-if="!story.rutube_id">
                            <video :src="story.video_url"
                                class="story-card-video"
                                preload="metadata"
                                muted
                                playsinline></video>
                        </template>

                        <!-- Кнопка Play поверх видео -->
                        <div class="absolute inset-0 flex items-center justify-center bg-black/30 pointer-events-none z-10">
                            <div class="w-12 h-12 rounded-full bg-white/80 flex items-center justify-center shadow-lg">
                                <?= icon('play', 'gc-icon-lg') ?>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Затемнение и подпись снизу -->
            <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent pointer-events-none"></div>
            <div class="absolute bottom-0 left-0 right-0 p-3 text-left pointer-events-none">
<div class="flex items-center gap-2">
    <template x-if="story.avatar_url">
        <img :src="story.avatar_url" class="w-6 h-6 rounded-full object-cover ring-1 ring-white shrink-0" alt="">
    </template>
    <template x-if="!story.avatar_url">
        <span class="w-6 h-6 rounded-full bg-amber-100 ring-1 ring-white flex items-center justify-center overflow-hidden shrink-0"
              x-html="window.getAvatarSvg(story.user_gender)"></span>
    </template>
    <span class="text-white text-sm font-bold truncate" x-text="story.user_name"></span>
</div>
                <div class="text-white/70 text-xs mt-0.5" x-text="formatTimeAgo(story.created_at)"></div>
            </div>

            <div x-show="story.is_unread" class="absolute top-2 right-2 w-3 h-3 rounded-full bg-amber-400 ring-2 ring-white"></div>

            <!-- Кнопка удаления (только владелец) -->
            <button x-show="story.is_owner"
                    @click.stop="deleteStory(story.id)"
                    class="absolute top-2 left-2 w-7 h-7 rounded-full bg-white/90 flex items-center justify-center text-rose-500 hover:bg-rose-50 transition shadow-md opacity-0 group-hover:opacity-100"
                    title="Удалить">
                <?= icon('close', 'gc-icon-xs') ?>
            </button>
        </div>
    </template>
</div>

    <p x-show="!stories.length && !loading" class="text-center text-gray-600 py-10 flex flex-col items-center justify-center gap-3">
        <?= icon('video', 'gc-icon-3xl') ?>
        <span>Историй пока нет. Будьте первым!</span>
    </p>

    <div x-show="loading" class="text-center py-10">
        <div class="animate-spin inline-block w-8 h-8 border-4 border-amber-200 border-t-amber-500 rounded-full"></div>
    </div>

    <!-- Просмотр истории -->
    
</section>
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
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('storiesPage', () => ({
        stories: [],
        uploading: false,
        previewUrl: '',
        previewIsVideo: false,
        loading: false,

        async init() {
            await this.loadStories();
        },

        async loadStories() {
            this.loading = true;
            try {
                const res = await fetch('/api/stories.php');
                const data = await res.json();
                this.stories = data.stories || [];
            } catch (e) {
                console.error('Load stories error:', e);
            } finally {
                this.loading = false;
            }
        },
        
        openViewer(index) {
    const viewer = Alpine.$data(document.querySelector('[x-data="storyViewer()"]'));
    if (viewer) {
        viewer.openAt(this.stories, index);

        // Пометить историю как просмотренную
        const story = this.stories[index];
        if (story && story.is_unread) {
            fetch('/api/stories.php?id=' + story.id + '&view=1', { method: 'POST' })
                .then(() => { story.is_unread = false; })
                .catch(() => {});
        }
    }
},

        async uploadStory(e) {
            const file = e.target.files[0];
            if (!file) return;

            const isImage = file.type.startsWith('image/');
            const isVideo = file.type.startsWith('video/');

            if (!isImage && !isVideo) {
                alert('Выберите изображение или видео');
                e.target.value = '';
                return;
            }

            const maxBytes = isImage ? (5 * 1024 * 1024) : (50 * 1024 * 1024);
            const limitText = isImage ? '5 МБ' : '50 МБ';
            if (file.size > maxBytes) {
                alert('Файл слишком большой (максимум ' + limitText + ')');
                e.target.value = '';
                return;
            }

            this.uploading = true;

            this.previewIsVideo = isVideo;
            const reader = new FileReader();
            reader.onload = (ev) => { this.previewUrl = ev.target.result; };
            reader.readAsDataURL(file);

            try {
                const formData = new FormData();
                formData.append('image', file);

                const res = await fetch('/api/stories.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.ok) {
                    this.previewUrl = '';
                    await this.loadStories();
                    if (typeof toast === 'function') {
                        toast('История опубликована!', 'success');
                    }
                } else {
                    alert(data.error || 'Ошибка загрузки');
                }
            } catch (err) {
                console.error('Upload error:', err);
                alert('Ошибка загрузки: ' + err.message);
            } finally {
                this.uploading = false;
                e.target.value = '';
            }
        },

        async deleteStory(storyId) {
    if (!confirm('Удалить историю?')) return;
    try {
        const res = await fetch('/api/stories.php?id=' + storyId, { method: 'DELETE' });
        const data = await res.json();
        if (data.ok) {
            this.stories = this.stories.filter(s => s.id !== storyId);
            if (typeof toast === 'function') toast('История удалена', 'info');
        }
    } catch (e) {
        console.error('Delete error:', e);
    }
},

        formatTimeAgo(d) {
            if (!d) return '';
            const diff = Math.floor((Date.now() - new Date(d).getTime()) / 1000);
            if (diff < 60) return 'только что';
            if (diff < 3600) return Math.floor(diff / 60) + ' мин назад';
            if (diff < 86400) return Math.floor(diff / 3600) + ' ч назад';
            return Math.floor(diff / 86400) + ' дн назад';
        }
    }));
});
</script>
<?php require __DIR__ . '/../components/story-viewer.php'; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>