function categoryPage(categoryId, slug) {
    return {
        results: [],
        total: 0,
        page: window.__CATEGORY_INITIAL_PAGE__ || 1,
        totalPages: 1,
        loading: true,
        loadingMore: false,
        hasMore: false,
        pageNumbers: [],

        async init() {
            await this.loadPage(this.page);
        },

        async loadPage(p) {
            this.loading = true;
            try {
                const res = await fetch(`/api/category_recipes.php?type=category&slug=${encodeURIComponent(slug)}&page=${p}`);
                const data = await res.json();
                this.results    = data.items || [];
                this.total      = data.total || 0;
                this.page       = data.page || 1;
                this.totalPages = data.pages || 1;
                this.hasMore    = this.page < this.totalPages;
                this.pageNumbers = this.buildPageNumbers();
                this.updateUrl();
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
                const res = await fetch(`/api/category_recipes.php?type=category&slug=${encodeURIComponent(slug)}&page=${next}`);
                const data = await res.json();
                this.results = this.results.concat(data.items || []);
                this.page    = data.page || next;
                this.hasMore = this.page < (data.pages || 1);
            } catch (e) { console.error(e); }
            finally { this.loadingMore = false; }
        },

        goToPage(p) {
            if (p < 1 || p > this.totalPages || this.loading) return;
            this.loadPage(p);
        },

        buildPageNumbers() {
            const total = this.totalPages, current = this.page, out = [];
            if (total <= 7) { for (let i = 1; i <= total; i++) out.push(i); return out; }
            out.push(1);
            if (current > 3) out.push('…');
            for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) out.push(i);
            if (current < total - 2) out.push('…');
            out.push(total);
            return out;
        },

        updateUrl() {
            const url = new URL(window.location);
            url.searchParams.set('page', this.page);
            window.history.replaceState({}, '', url);
        }
    };
}