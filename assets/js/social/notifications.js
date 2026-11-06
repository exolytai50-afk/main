/**
 * GigaChefAI — Уведомления
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('notificationsPage', () => ({
        notifications: [],
        unread: 0,
        loading: false,
        
        async init() {
            await this.loadNotifications();
            this.startPolling();
        },
        
        async loadNotifications() {
            this.loading = true;
            try {
                const res = await fetch('/api/notifications.php');
                const data = await res.json();
                this.notifications = data.notifications || [];
                this.unread = data.unread || 0;
            } catch (e) {
                console.error('Load notifications error:', e);
            } finally {
                this.loading = false;
            }
        },
        
        async markAllRead() {
            try {
                await fetch('/api/notifications.php', { method: 'POST' });
                this.unread = 0;
                this.notifications = this.notifications.map(n => ({ ...n, is_read: 1 }));
            } catch (e) {
                console.error('Mark all read error:', e);
            }
        },
        
        async deleteNotification(id) {
            try {
                await fetch('/api/notifications.php?id=' + id, { method: 'DELETE' });
                this.notifications = this.notifications.filter(n => n.id !== id);
            } catch (e) {
                console.error('Delete error:', e);
            }
        },
        
        startPolling() {
            setInterval(() => this.loadNotifications(), 30000);
        },
        
        getIcon(type) {
            const icons = {
                like: '❤️',
                comment: '💬',
                friend_request: '👥',
                friend_accepted: '🤝',
                achievement: '🏅',
                challenge: '🏆',
                system: '⚙️',
                message: '💌'
            };
            return icons[type] || '📌';
        },
        
        formatTimeAgo(d) {
            const diff = Math.floor((Date.now() - new Date(d).getTime()) / 1000);
            if (diff < 60) return 'только что';
            if (diff < 3600) return Math.floor(diff / 60) + ' мин назад';
            if (diff < 86400) return Math.floor(diff / 3600) + ' ч назад';
            return new Date(d).toLocaleDateString('ru-RU');
        }
    }));
    
    // Глобальный компонент для колокольчика в шапке
    Alpine.data('notifBell', () => ({
        open: false,
        items: [],
        unread: 0,
        
        init() {
            this.load();
            this.poll();
        },
        
        async load() {
            try {
                const res = await fetch('/api/notifications.php?unread=1');
                const data = await res.json();
                this.items = (data.notifications || []).slice(0, 10);
                this.unread = data.unread || 0;
            } catch (e) {}
        },
        
        poll() {
            setInterval(() => this.load(), 30000);
        },
        
        toggle() {
            this.open = !this.open;
            if (this.open && this.unread > 0) {
                fetch('/api/notifications.php', { method: 'POST' });
                this.unread = 0;
            }
        }
    }));
});