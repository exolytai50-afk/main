// assets/js/social/friends.js
function friendsPage() {
    return {
        list: [], email: '',
        async load() { try { this.list = (await CookAPI.get('/api/friends.php')).friends || []; } catch (_) {} },
        async add() {
            if (!this.email.trim()) return;
            try { await CookAPI.post('/api/friends.php', { email: this.email }); this.email=''; CookAPI.clearCache(); await this.load(); toast('Заявка отправлена','success'); }
            catch (e) { toast(e.message, 'error'); }
        }
    };
}