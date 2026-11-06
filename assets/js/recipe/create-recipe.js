document.addEventListener('alpine:init', () => {
    Alpine.data('createRecipe', () => ({

        /* ─── Состояние формы ─── */
        saving: false,
        error: '',
        uploadingVideo: false,
        uploadProgress: 0,
        newTagName: '',

        /* ─── Данные фильтров ─── */
        tagGroups: [],        // [{ group, label, icon, icon_svg_html, tags: [...] }]
        allCategories: [],    // [{ id, slug, name, icon_svg, icon_svg_html }]

        /* ─── Реактивные данные рецепта ─── */
        r: {
            title: '',
            description: '',
            image_url: '',
            video_url: '',
            video_owner_id: null, // <-- добавить
            video_id: null,
            category_slug: '',
            difficulty: 'easy',
            prep_time: 0,
            cook_time: 0,
            servings: 2,
            calories: 0,
            proteins: 0,
            fats: 0,
            carbs: 0,
            tag_slugs: [],
            ingredients: [{ name: '', amount: '', unit: '', is_optional: false }],
            steps: [{ order: 1, instruction: '', timer_minutes: 0, tip: '' }],
        },

        /* ─── Инициализация ─── */
        async init() {
            await this.loadTagGroups();
            await this.loadCategories();
        },

        async loadTagGroups() {
            try {
                const res  = await fetch('/api/tag_groups.php');
                const data = await res.json();
                this.tagGroups = data.groups || [];
            } catch (e) {
                console.error('loadTagGroups error:', e);
                this.tagGroups = [];
            }
        },

        async loadCategories() {
            try {
                const res  = await fetch('/api/categories.php');
                const data = await res.json();
                this.allCategories = data.items || [];
            } catch (e) {
                console.error('loadCategories error:', e);
                this.allCategories = [];
            }
        },

        /* ─── Работа с тегами ─── */

        /**
         * Выбор одного тега из группы (через <select>).
         * Удаляет все теги этой группы и добавляет выбранный.
         */
        onGroupSelect(group, slug) {
            if (!Array.isArray(this.r.tag_slugs)) this.r.tag_slugs = [];

            const grp = this.tagGroups.find(g => g.group === group);
            if (grp) {
                const groupSlugs = grp.tags.map(t => t.slug);
                this.r.tag_slugs = this.r.tag_slugs.filter(s => !groupSlugs.includes(s));
            }

            if (slug) this.r.tag_slugs.push(slug);
        },

        /** Проверка: выбран ли тег группы */
        isGroupSelected(group, slug) {
            return (this.r.tag_slugs || []).includes(slug);
        },

        /** Утилита: массив тегов группы */
        getGroupTags(group) {
            const grp = this.tagGroups.find(g => g.group === group);
            return grp ? grp.tags : [];
        },

        /** Ручное переключение тега (для чипсов и чекбоксов) */
        toggleTagSlug(slug) {
            const i = this.r.tag_slugs.indexOf(slug);
            if (i >= 0) this.r.tag_slugs.splice(i, 1);
            else this.r.tag_slugs.push(slug);
        },

        /** Имя тега по slug */
        getTagNameBySlug(slug) {
            for (const grp of this.tagGroups) {
                const t = grp.tags.find(x => x.slug === slug);
                if (t) return t.name;
            }
            return slug;
        },

        /** HTML иконки тега по slug */
        getTagIconHtml(slug) {
            for (const grp of this.tagGroups) {
                const t = grp.tags.find(x => x.slug === slug);
                if (t) return t.icon_svg_html || '';
            }
            return '';
        },

        /** Создание нового тега через API */
        async createTag() {
            const name = this.newTagName.trim();
            if (!name) return;

            try {
                const res = await fetch('/api/recipe_tags_create.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name })
                });
                const data = await res.json();
                if (!data.ok) throw new Error(data.error || 'Ошибка');

                const t = data.tag;                       // { id, slug, name, icon_svg, tag_group }
                const group = t.tag_group || 'other';

                // Найдём/создадим группу в tagGroups
                let grp = this.tagGroups.find(g => g.group === group);
                if (!grp) {
                    grp = {
                        group: group,
                        label: group,
                        icon: '🏷️',
                        icon_svg_html: '',
                        tags: [],
                    };
                    this.tagGroups.push(grp);
                }

                grp.tags.push({
                    id: t.id,
                    slug: t.slug,
                    name: t.name,
                    icon_svg: t.icon_svg,
                    icon_svg_html: '',   // новый тег без иконки — заглушка через icon() на сервере появится позже
                });

                this.r.tag_slugs.push(t.slug);
                this.newTagName = '';
            } catch (e) {
                alert('Не удалось создать тег: ' + e.message);
            }
        },

        /* ─── Загрузка изображения ─── */
        async uploadImage(event) {
            const file = event.target.files[0];
            if (!file) return;

            const fd = new FormData();
            fd.append('image', file);

            try {
                const res = await fetch('/api/upload_image.php', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();
                if (data.ok) {
                    this.r.image_url = data.url;
                } else {
                    alert('Ошибка загрузки: ' + data.error);
                }
            } catch (e) {
                alert('Ошибка соединения');
            }
        },

/* ─── Загрузка видео ─── */
async uploadVideo(event) {
    const file = event.target.files[0];
    if (!file) return;

    this.uploadingVideo = true;
    this.uploadProgress = 0;

    const fd = new FormData();
    fd.append('video', file);

    try {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/api/upload_video_vk.php'); // <-- НОВЫЙ ЭНДПОИНТ

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                this.uploadProgress = Math.round((e.loaded / e.total) * 100);
            }
        };

        const response = await new Promise((resolve, reject) => {
            xhr.onload  = () => resolve(xhr.responseText);
            xhr.onerror = reject;
            xhr.send(fd);
        });

        const data = JSON.parse(response);
        if (data.ok) {
            // Сохраняем ID в реактивные данные
            this.r.video_owner_id = data.video_owner_id;
            this.r.video_id = data.video_id;
            // Создаем "виртуальный" URL для отображения превью
            this.r.video_url = `vk:${data.video_owner_id}:${data.video_id}`; 
        } else {
            alert('Ошибка загрузки видео: ' + data.error);
        }
    } catch (e) {
        alert('Ошибка соединения');
    } finally {
        this.uploadingVideo = false;
        this.uploadProgress = 0;
    }
},

        removeVideo() {
            this.r.video_url = '';
        },

        /* ─── Сохранение рецепта ─── */
        async save() {
            if (this.saving) return;

            if (!this.r.title.trim()) {
                this.error = 'Укажите название рецепта';
                return;
            }

            this.saving = true;
            this.error = '';

            try {
                const ingredients = this.r.ingredients.filter(i => i.name && i.name.trim());

                const payload = {
                    title:           this.r.title,
                    description:     this.r.description,
                    image_url:       this.r.image_url,
                    video_url:       this.r.video_url,
                    difficulty:      this.r.difficulty,
                    prep_time:       this.r.prep_time,
                    cook_time:       this.r.cook_time,
                    servings:        this.r.servings,
                    calories:        this.r.calories,
                    proteins:        this.r.proteins,
                    fats:            this.r.fats,
                    carbs:           this.r.carbs,
                    ingredients:     ingredients,
                    steps:           this.r.steps,
                    category_slug:   this.r.category_slug,
                    tag_slugs:       this.r.tag_slugs,
                    is_ai_generated: 0,
                    book_id:         window.__CURRENT_BOOK_ID__ || 0,
                };

                const response = await fetch('/api/save_recipe.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();

                // Обработка дубликата (409)
                if (response.status === 409 && data.duplicate) {
                    const openExisting = confirm(
                        'Рецепт уже существует: «' + data.duplicate.title + '»\n' +
                        'Совпадение: ' + data.duplicate.similarity + '%\n\n' +
                        'Открыть существующий рецепт?'
                    );
                    if (openExisting && data.redirect_url) {
                        location.href = data.redirect_url;
                    }
                    return;
                }

                if (data.ok) {
                    location.href = data.redirect_url;
                } else {
                    this.error = data.error || 'Ошибка сохранения';
                }
            } catch (e) {
                console.error('Save error:', e);
                this.error = 'Ошибка соединения';
            } finally {
                this.saving = false;
            }
        }
    }));
});