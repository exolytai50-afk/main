/**
 * GigaChefAI — Лента активностей
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('feedPage', () => ({
        feed: [],
        filter: 'all',
        page: 1,
        pages: 1,
        loading: false,
        
        init() {
            this.loadFeed();
        },
        
        async loadFeed() {
            this.loading = true;
            try {
                const params = new URLSearchParams({
                    filter: this.filter,
                    page: this.page
                });
                
                const res = await fetch('/api/activity.php?' + params.toString());
                const data = await res.json();
                
                if (this.page === 1) {
                    this.feed = data.feed || [];
                } else {
                    this.feed = [...this.feed, ...(data.feed || [])];
                }
                
                this.pages = data.pages || 1;
            } catch (e) {
                console.error('Load feed error:', e);
            } finally {
                this.loading = false;
            }
        },
        
        async changeFilter(filter) {
            this.filter = filter;
            this.page = 1;
            await this.loadFeed();
        },
        
        async loadMore() {
            if (this.page < this.pages) {
                this.page++;
                await this.loadFeed();
            }
        },
        
        async likePost(postId) {
            try {
                await CookAPI.post('/api/likes.php', { type: 'activity', id: postId });
                // Обновляем UI
                const post = this.feed.find(p => p.id === postId);
                if (post) {
                    post.is_liked = !post.is_liked;
                    post.likes_count = (post.likes_count || 0) + (post.is_liked ? 1 : -1);
                }
            } catch (e) {
                console.error('Like error:', e);
            }
        },
        
        formatTimeAgo(d) {
            const diff = Math.floor((Date.now() - new Date(d).getTime()) / 1000);
            if (diff < 60) return 'только что';
            if (diff < 3600) return Math.floor(diff / 60) + ' мин назад';
            if (diff < 86400) return Math.floor(diff / 3600) + ' ч назад';
            return new Date(d).toLocaleDateString('ru-RU');
        }
    }));
});