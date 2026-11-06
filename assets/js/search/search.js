document.addEventListener('alpine:init', () => {
    Alpine.data('searchPage', () => ({
        // ─── Основные ───
        q: '',
        sort: 'popular',
        category: '',
        difficulty: '',
        page: window.__SEARCH_INITIAL_PAGE__ || 1,
        perPage: 24,

        // ─── Данные ───
        results: [],
        total: 0,
        totalPages: 1,
        hasMore: false,
        loading: false,
        loadingMore: false,
        searched: false,

        // ─── Фильтры ───
        filterCuisine: '',
        filterDiet: '',
        filterHoliday: '',
        filterSeason: '',
        filterTime: '',
        filterRestriction: '',

        tagGroups: [],
        categories: [],
        tagFilters: {},
        openDropdown: null,
        icons: window.__SEARCH_ICONS__ || {},

        get difficultyIcons() {
            return window.__SEARCH_DIFFICULTY_ICONS__ || {};
        },

        get activeExtraCount() {
            let n = 0;
            if (this.filterHoliday) n++;
            if (this.filterSeason) n++;
            if (this.filterTime) n++;
            if (this.filterRestriction) n++;
            return n;
        },

        getGroupTags(group) {
            const grp = (this.tagGroups || []).find(g => g.group === group);
            return grp ? (grp.tags || []) : [];
        },

        groupIconHtml(group) {
            const g = (this.tagGroups || []).find(x => x.group === group);
            return g && g.icon_svg_html ? g.icon_svg_html : '';
        },

        selectedTagIconHtml(group) {
            const slugs = this.tagFilters[group];
            const slug  = Array.isArray(slugs) ? slugs[0] : '';
            if (!slug) return '';
            const g = (this.tagGroups || []).find(x => x.group === group);
            if (!g) return '';
            const t = (g.tags || []).find(x => x.slug === slug);
            return t && t.icon_svg_html ? t.icon_svg_html : '';
        },

        currentGroupIcon(group) {
            return this.selectedTagIconHtml(group) || this.groupIconHtml(group);
        },

        getTagName(group, slug) {
            const g = (this.tagGroups || []).find(x => x.group === group);
            if (!g) return '';
            const t = (g.tags || []).find(x => x.slug === slug);
            return t ? t.name : '';
        },

        currentCategoryIcon() {
            if (!this.category) {
                return (this.icons && this.icons.search) || '';
            }
            const c = (this.categories || []).find(x => x.slug === this.category);
            return c && c.icon_svg_html ? c.icon_svg_html : '';
        },

        currentDifficultyIcon() {
            const icons = this.difficultyIcons;
            return icons[this.difficulty] || icons[''] || '';
        },

        onGroupSelect(group, slug) {
            this.tagFilters[group] = slug ? [slug] : [];
            const key = 'filter' + group.charAt(0).toUpperCase() + group.slice(1);
            if (key in this) this[key] = slug || '';
            this.search();
        },

        async init() {
            const params = new URLSearchParams(window.location.search);
            this.q          = params.get('q') || '';
            this.category   = params.get('category') || '';
            this.difficulty = params.get('difficulty') || '';
            this.sort       = params.get('sort') || 'popular';
            this.page       = parseInt(params.get('page') || window.__SEARCH_INITIAL_PAGE__ || '1', 10) || 1;

            ['cuisine', 'diet', 'holiday', 'season', 'time', 'restriction'].forEach(group => {
                const raw = params.get('tags[' + group + ']');
                if (raw) {
                    const slugs = raw.split(',').filter(Boolean);
                    this.tagFilters[group] = slugs;
                    const key = 'filter' + group.charAt(0).toUpperCase() + group.slice(1);
                    if (key in this) this[key] = slugs[0] || '';
                }
            });

            await this.loadTagGroups();
            await this.loadCategories();
            this.search(true);
        },

        async loadTagGroups() {
            try {
                const res  = await fetch('/api/tag_groups.php');
                const data = await res.json();
                this.tagGroups = data.groups || [];
            } catch (e) {
                console.error('Tag groups load error:', e);
                this.tagGroups = [];
            }
        },

        async loadCategories() {
            try {
                const res  = await fetch('/api/categories.php');
                const data = await res.json();
                this.categories = data.items || [];
            } catch (e) {
                this.categories = [];
            }
        },

        _getApiUrl(pageToLoad) {
            let url = '/api/search.php?';
            const params = [];
            
            if (this.q) params.push('q=' + encodeURIComponent(this.q));
            if (this.difficulty) params.push('difficulty=' + encodeURIComponent(this.difficulty));
            if (this.category) params.push('category=' + encodeURIComponent(this.category));
            if (this.sort) params.push('sort=' + encodeURIComponent(this.sort));
            
            params.push('page=' + pageToLoad);
            params.push('per_page=' + this.perPage);

            Object.entries(this.tagFilters).forEach(([group, slugs]) => {
                if (slugs && slugs.length) {
                    params.push('tags[' + group + ']=' + encodeURIComponent(slugs.join(',')));
                }
            });
            
            return url + params.join('&');
        },

        _syncBrowserUrl() {
            const params = new URLSearchParams();
            if (this.q) params.set('q', this.q);
            if (this.category) params.set('category', this.category);
            if (this.difficulty) params.set('difficulty', this.difficulty);
            if (this.sort !== 'popular') params.set('sort', this.sort);
            if (this.page > 1) params.set('page', this.page);

            Object.entries(this.tagFilters).forEach(([group, slugs]) => {
                if (slugs && slugs.length) params.set('tags[' + group + ']', slugs.join(','));
            });

            const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
            window.history.replaceState({}, '', newUrl);
        },

        async search(resetPage = true) {
            if (resetPage instanceof Event) resetPage = true;
            this.loading = true;
            this.searched = true;
            const pageToLoad = resetPage ? 1 : this.page;

            try {
                const url = this._getApiUrl(pageToLoad);
                const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                
                const data = await res.json();
                const items = data.items || data.recipes || [];
                
                if (resetPage) {
                    // Очищаем дубли даже при первой загрузке
                    const seenIds = new Set();
                    this.results = items.filter(item => {
                        if (item.id != null && !seenIds.has(String(item.id))) {
                            seenIds.add(String(item.id));
                            return true;
                        }
                        return false;
                    });
                } else {
                    const existingIds = new Set(this.results.map(item => String(item.id)));
                    const newItems = items.filter(item => 
                        item.id != null && !existingIds.has(String(item.id))
                    );
                    this.results = [...this.results, ...newItems];
                }
                
                this.total      = parseInt(data.total || 0, 10);
                this.hasMore    = !!data.hasMore;
                this.totalPages = Math.max(1, parseInt(data.total_pages || 1, 10));
                
                if (resetPage) this.page = 1;
                this._syncBrowserUrl();
            } catch (e) {
                console.error('Search error:', e);
                if (resetPage) this.results = [];
                this.total = 0;
                this.hasMore = false;
                this.totalPages = 1;
            } finally {
                this.loading = false;
            }
        },

async loadMore() {
    if (this.loadingMore || this.loading || !this.hasMore) {
        return;
    }

    this.loadingMore = true;

    const nextPage = this.page + 1;

    try {
        const url = this._getApiUrl(nextPage);

        const res = await fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!res.ok) {
            throw new Error(`HTTP ${res.status}`);
        }

        const data = await res.json();

        if (data.error) {
            throw new Error(data.error);
        }

        const items = Array.isArray(data.items)
            ? data.items
            : Array.isArray(data.recipes)
                ? data.recipes
                : [];

        if (items.length === 0) {
            this.hasMore = false;
            return;
        }

        // Не добавляем уже отображённые рецепты повторно.
        const existingIds = new Set(
            this.results.map(item => String(item.id))
        );

        const newItems = items.filter(
            item => item.id != null &&
                    !existingIds.has(String(item.id))
        );

        // Обновляем список одним присваиванием для Alpine.
        this.results = [...this.results, ...newItems];

        this.page = nextPage;
        this.total = Number.parseInt(data.total, 10) || this.total;
        this.totalPages = Math.max(
            1,
            Number.parseInt(data.total_pages, 10) || this.totalPages
        );

        this.hasMore = typeof data.hasMore === 'boolean'
            ? data.hasMore
            : this.page < this.totalPages;

        this._syncBrowserUrl();

    } catch (error) {
        console.error('Ошибка загрузки рецептов:', error);
    } finally {
        this.loadingMore = false;
    }
},

        async goToPage(p) {
            if (p === '…' || p < 1 || p > this.totalPages || p === this.page) return;
            this.loading = true;
            
            try {
                const url = this._getApiUrl(p);
                const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                
                this.results = data.items || data.recipes || [];
                this.total = parseInt(data.total || 0, 10);
                this.hasMore = !!data.hasMore;
                this.totalPages = Math.max(1, parseInt(data.total_pages || 1, 10));
                
                this.page = p;
                this._syncBrowserUrl();
                
                this.$nextTick(() => {
                    document.querySelector('.max-w-7xl')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            } catch (e) {
                console.error('Go to page error:', e);
            } finally {
                this.loading = false;
            }
        },

        clearFilters() {
            this.q = '';
            this.category = '';
            this.difficulty = '';
            this.sort = 'popular';
            this.filterCuisine = '';
            this.filterDiet = '';
            this.filterHoliday = '';
            this.filterSeason = '';
            this.filterTime = '';
            this.filterRestriction = '';
            this.tagFilters = {};
            this.search(true);
        },

        get pageNumbers() {
            const current = this.page;
            const total = this.totalPages;
            if (total <= 1) return [1];
            const delta = 1, edge = 1, pages = [];
            const left = Math.max(2, current - delta);
            const right = Math.min(total - 1, current + delta);
            for (let i = 1; i <= Math.min(edge, total); i++) pages.push(i);
            if (left > edge + 1) pages.push('…');
            for (let i = left; i <= right; i++) pages.push(i);
            if (right < total - edge) pages.push('…');
            for (let i = Math.max(total - edge + 1, right + 1); i <= total; i++) pages.push(i);
            return [...new Set(pages)];
        },
    }));
});