<?php
/**
 * Продвинутый просмотр историй.
 * Использование: <?php require __DIR__ . '/../components/story-viewer.php'; ?>
 * Затем вызвать из JS: window.StoryViewer.openAt(storiesArray, startIndex);
 */
?>
<div x-data="storyViewer()" x-show="open" x-cloak
     @keydown.escape.window="close()"
     @keydown.arrow-left.window="prev()"
     @keydown.arrow-right.window="next()"
     class="fixed inset-0 z-[200] bg-black/95 flex items-center justify-center"
     style="display:none">

    <!-- Кнопка закрытия -->
    <button @click="close()"
            class="absolute top-4 right-4 z-30 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white text-2xl flex items-center justify-center transition"
            aria-label="Закрыть">×</button>

    <!-- Контейнер: три карточки -->
    <div class="relative w-full h-full flex items-center justify-center gap-3 md:gap-6 md:px-6 select-none overflow-hidden">

        <!-- ═══ ПРЕДЫДУЩАЯ ═══ -->
        <!-- Добавлена проверка hasPrev() и optional chaining ?. -->
        <div x-show="hasPrev() && stories[currentIndex - 1]"
             @click="prev()"
             class="hidden md:flex items-center justify-center cursor-pointer opacity-40 hover:opacity-70 transition flex-shrink-0"
             style="height: min(80vh, 500px); max-height: calc(85vh - 1rem); aspect-ratio: 9 / 16;">
            <div class="relative w-full h-full rounded-2xl overflow-hidden bg-gray-900 shadow-2xl">
                <template x-if="stories[currentIndex - 1]">
                    <div class="w-full h-full">
                        <template x-if="stories[currentIndex - 1]?.media_type !== 'video'">
                            <img :src="stories[currentIndex - 1]?.image_url || '/uploads/static/static.jpg'"
                                 class="w-full h-full object-cover"
                                 @error="$el.src='/uploads/static/static.jpg'"
                                 alt="">
                        </template>
                        <template x-if="stories[currentIndex - 1]?.media_type === 'video' && stories[currentIndex - 1]?.rutube_id">
                            <img :src="'https://pic.rutube.ru/video/' + stories[currentIndex - 1]?.rutube_id + '.jpg'"
                                 class="w-full h-full object-cover"
                                 alt="">
                        </template>
                        <template x-if="stories[currentIndex - 1]?.media_type === 'video' && !stories[currentIndex - 1]?.rutube_id">
                            <video :src="stories[currentIndex - 1]?.video_url"
                                   class="w-full h-full object-cover"
                                   preload="metadata"
                                   muted
                                   playsinline></video>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        <!-- ═══ ТЕКУЩАЯ ═══ -->
        <div class="relative flex-shrink-0 w-full h-full md:h-[85vh] md:w-auto md:aspect-[9/16] md:max-w-[90vw]"
             @touchstart.passive="onTouchStart($event)"
             @touchmove.passive="onTouchMove($event)"
             @touchend="onTouchEnd($event)">

            <div class="relative w-full h-full md:rounded-2xl overflow-hidden bg-gray-900 md:shadow-2xl">

                <template x-if="current">
                    <div class="relative w-full h-full">

                        <!-- Медиа -->
                        <template x-if="current.media_type !== 'video'">
                            <img :src="current.image_url || '/uploads/static/static.jpg'"
                                 class="absolute inset-0 w-full h-full object-cover bg-black z-0"
                                 @error="$el.src='/uploads/static/static.jpg'"
                                 :alt="current.user_name || ''">
                        </template>

                        <template x-if="current.media_type === 'video' && current.rutube_id">
                            <iframe :src="'https://rutube.ru/play/embed/' + current.rutube_id + '/?autoplay=1'"
                                    class="absolute inset-0 w-full h-full border-0 z-0"
                                    allow="autoplay; fullscreen"
                                    allowfullscreen
                                    frameborder="0"></iframe>
                        </template>

                        <template x-if="current.media_type === 'video' && !current.rutube_id">
                            <video x-ref="currentVideo"
                                   :src="current.video_url"
                                   class="absolute inset-0 w-full h-full object-contain bg-black z-0"
                                   playsinline
                                   @loadedmetadata="onVideoLoaded()"
                                   @ended="onVideoEnded()"
                                   @timeupdate="onVideoTimeUpdate()"></video>
                        </template>

                        <!-- Градиенты -->
                        <div class="absolute top-0 left-0 right-0 h-32 bg-gradient-to-b from-black/70 to-transparent pointer-events-none z-10"></div>
                        <div class="absolute bottom-0 left-0 right-0 h-24 bg-gradient-to-t from-black/70 to-transparent pointer-events-none z-10"></div>

                        <!-- Прогресс-бар -->
                        <div class="absolute top-3 left-3 right-3 flex gap-1 z-20">
                            <template x-for="(s, i) in stories" :key="i">
                                <div class="flex-1 h-[3px] bg-white/30 rounded-full overflow-hidden">
                                    <div class="h-full bg-white rounded-full transition-[width] duration-100 ease-linear"
                                         :style="'width:' + segmentWidth(i) + '%'"></div>
                                </div>
                            </template>
                        </div>

                        <!-- Шапка -->
                        <div class="absolute top-6 left-0 right-0 px-4 flex items-center gap-3 z-20">
                            <template x-if="current.avatar_url">
                                <img :src="current.avatar_url"
                                     class="w-10 h-10 rounded-full object-cover ring-2 ring-white/50 shrink-0"
                                     alt="">
                            </template>
                            <template x-if="!current.avatar_url">
                                <span class="w-10 h-10 rounded-full bg-amber-100 ring-2 ring-white/50 flex items-center justify-center overflow-hidden shrink-0"
                                      x-html="window.getAvatarSvg(current.user_gender)"></span>
                            </template>
                            <div class="flex-1 min-w-0">
                                <div class="text-white font-bold text-sm truncate" x-text="current.user_name"></div>
                                <div class="text-white/60 text-xs" x-text="formatTimeAgo(current.created_at)"></div>
                            </div>
                        </div>

                        <!-- Клик-зоны -->
                        <button @click="prev()" class="absolute left-0 top-0 bottom-0 w-1/3 z-[15] opacity-0 focus:outline-none" aria-label="Предыдущая"></button>
                        <button @click="next()" class="absolute right-0 top-0 bottom-0 w-1/3 z-[15] opacity-0 focus:outline-none" aria-label="Следующая"></button>
                    </div>
                </template>
            </div>
        </div>

        <!-- ═══ СЛЕДУЮЩАЯ ═══ -->
        <!-- Добавлена проверка hasNext() и optional chaining ?. -->
        <div x-show="hasNext() && stories[currentIndex + 1]"
             @click="next()"
             class="hidden md:flex items-center justify-center cursor-pointer opacity-40 hover:opacity-70 transition flex-shrink-0"
             style="height: min(80vh, 500px); max-height: calc(85vh - 1rem); aspect-ratio: 9 / 16;">
            <div class="relative w-full h-full rounded-2xl overflow-hidden bg-gray-900 shadow-2xl">
                <template x-if="stories[currentIndex + 1]">
                    <div class="w-full h-full">
                        <template x-if="stories[currentIndex + 1]?.media_type !== 'video'">
                            <img :src="stories[currentIndex + 1]?.image_url || '/uploads/static/static.jpg'"
                                 class="w-full h-full object-cover"
                                 @error="$el.src='/uploads/static/static.jpg'"
                                 alt="">
                        </template>
                        <template x-if="stories[currentIndex + 1]?.media_type === 'video' && stories[currentIndex + 1]?.rutube_id">
                            <img :src="'https://pic.rutube.ru/video/' + stories[currentIndex + 1]?.rutube_id + '.jpg'"
                                 class="w-full h-full object-cover"
                                 alt="">
                        </template>
                        <template x-if="stories[currentIndex + 1]?.media_type === 'video' && !stories[currentIndex + 1]?.rutube_id">
                            <video :src="stories[currentIndex + 1]?.video_url"
                                   class="w-full h-full object-cover"
                                   preload="metadata"
                                   muted
                                   playsinline></video>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<script>
window.StoryViewer = window.StoryViewer || {};

document.addEventListener('alpine:init', () => {
    Alpine.data('storyViewer', () => ({
        open: false,
        stories: [],
        currentIndex: 0,
        progress: 0,
        timer: null,
        photoDuration: 15000,
        // Состояние свайпа
        touchStartX: 0,
        touchStartY: 0,
        touchDeltaX: 0,
        touchDeltaY: 0,
        isSwiping: false,

        get current() {
            return this.stories[this.currentIndex] || null;
        },

        hasPrev() {
            return this.currentIndex > 0;
        },

        hasNext() {
            return this.currentIndex < this.stories.length - 1;
        },

        /**
         * Ширина сегмента прогресс-бара:
         *   - истории до currentIndex — 100% (просмотрены)
         *   - текущая — progress
         *   - после currentIndex — 0%
         */
        segmentWidth(i) {
            if (i < this.currentIndex) return 100;
            if (i > this.currentIndex) return 0;
            return this.progress;
        },

        openAt(stories, index) {
            this.stories = Array.isArray(stories) ? stories : [];
            this.currentIndex = Math.max(0, Math.min(index, this.stories.length - 1));
            this.open = true;
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => this.startCurrent());
        },

        close() {
            this.open = false;
            this.stopTimer();
            this.pauseVideo();
            document.body.style.overflow = '';
        },

        prev() {
            if (this.currentIndex <= 0) return;
            this.currentIndex--;
            this.startCurrent();
        },

        next() {
            if (this.currentIndex >= this.stories.length - 1) {
                this.close();
                return;
            }
            this.currentIndex++;
            this.startCurrent();
        },

        startCurrent() {
            this.stopTimer();
            this.pauseVideo();
            this.progress = 0;

            const s = this.current;
            if (!s) return;

            if (s.media_type === 'video' && !s.rutube_id) {
                this.$nextTick(() => {
                    const v = this.$refs.currentVideo;
                    if (v) {
                        v.currentTime = 0;
                        v.play().catch(() => {});
                    }
                });
                return;
            }

            if (s.media_type === 'video' && s.rutube_id) {
                this.runProgress(60000);
                return;
            }

            this.runProgress(this.photoDuration);
        },

        runProgress(duration) {
            const start = Date.now();
            this.timer = setInterval(() => {
                const elapsed = Date.now() - start;
                this.progress = Math.min(100, (elapsed / duration) * 100);
                if (elapsed >= duration) {
                    this.stopTimer();
                    this.next();
                }
            }, 50);
        },

        stopTimer() {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
        },

        pauseVideo() {
            const v = this.$refs.currentVideo;
            if (v && !v.paused) {
                try { v.pause(); } catch (e) {}
            }
        },

        onVideoLoaded() {
            const v = this.$refs.currentVideo;
            if (!v) return;
            v.play().catch(() => {});
        },

        onVideoTimeUpdate() {
            const v = this.$refs.currentVideo;
            if (!v || !v.duration) return;
            this.progress = Math.min(100, (v.currentTime / v.duration) * 100);
        },

        onVideoEnded() {
            this.next();
        },

        formatTimeAgo(d) {
            if (!d) return '';
            const diff = Math.floor((Date.now() - new Date(d.replace(' ', 'T')).getTime()) / 1000);
            if (diff < 60) return 'только что';
            if (diff < 3600) return Math.floor(diff / 60) + ' мин назад';
            if (diff < 86400) return Math.floor(diff / 3600) + ' ч назад';
            return Math.floor(diff / 86400) + ' дн назад';
        },

        // ═══ Свайпы ═══
        onTouchStart(e) {
            const t = e.changedTouches[0];
            this.touchStartX = t.screenX;
            this.touchStartY = t.screenY;
            this.touchDeltaX = 0;
            this.touchDeltaY = 0;
            this.isSwiping = false;
        },

        onTouchMove(e) {
            const t = e.changedTouches[0];
            this.touchDeltaX = t.screenX - this.touchStartX;
            this.touchDeltaY = t.screenY - this.touchStartY;
            // Горизонтальный свайп больше вертикального — считаем это свайпом
            if (Math.abs(this.touchDeltaX) > Math.abs(this.touchDeltaY) && Math.abs(this.touchDeltaX) > 10) {
                this.isSwiping = true;
            }
        },

        onTouchEnd(e) {
            if (!this.isSwiping) return;
            const threshold = 50;
            if (this.touchDeltaX > threshold) this.prev();
            else if (this.touchDeltaX < -threshold) this.next();
            this.isSwiping = false;
            this.touchDeltaX = 0;
            this.touchDeltaY = 0;
        }
    }));
});
</script>