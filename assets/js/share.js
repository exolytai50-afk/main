document.addEventListener('alpine:init', () => {
    Alpine.data('shareBlock', (data = {}) => ({
        title:       data.title       || document.title,
        url:         data.url         || window.location.href,
        description: data.description || '',
        image:       data.image       || '',
        open: false,

        copied: false,
        toast: '',
        _toastTimer: null,

        // Показывать ли кнопку «Ещё» (native share)
        showNativeShare: false,

        init() {
            const isMobile = /Android|iPhone|iPad|iPod|Windows Phone/i.test(navigator.userAgent);
            this.showNativeShare = isMobile && typeof navigator.share === 'function';
        },

        isMobile() {
            return /Android|iPhone|iPad|iPod|Windows Phone/i.test(navigator.userAgent);
        },

        enc(v) { return encodeURIComponent(v || ''); },

        openExternal(link, newTab = false) {
            if (newTab || !this.isMobile()) {
                window.open(link, '_blank', 'noopener,noreferrer');
            } else {
                window.location.href = link;
            }
        },

        showToast(msg) {
            this.toast = msg;
            clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(() => { this.toast = ''; }, 2500);
        },

        /* Тексты шеринга */
        shareTextMax()      { return `Смотри, какой рецепт в GigaChef: ${this.url}`; },
        shareTextTelegram() { return `${this.title} — ${this.url}`; },
        shareTextWhatsApp() { return `Зашарил рецепт: ${this.title}. ${this.url}`; },
        shareTextGeneric()  { return `${this.title}\n${this.url}`; },

        /* Главный метод */
        share(platform) {
            let link = '';

            switch (platform) {
                case 'max':
                    link = `https://max.ru/:share?text=${this.enc(this.shareTextMax())}`;
                    this.openExternal(link, !this.isMobile());
                    break;

                case 'vk':
                    link = 'https://vk.com/share.php'
                         + `?url=${this.enc(this.url)}`
                         + `&title=${this.enc(this.title)}`
                         + (this.description ? `&description=${this.enc(this.description)}` : '')
                         + (this.image       ? `&image=${this.enc(this.image)}`             : '');
                    this.openExternal(link, true);
                    break;

                case 'ok':
                    link = 'https://connect.ok.ru/offer'
                         + `?url=${this.enc(this.url)}`
                         + `&title=${this.enc(this.title)}`
                         + (this.image ? `&imageUrl=${this.enc(this.image)}` : '');
                    this.openExternal(link, true);
                    break;

                case 'telegram':
                    link = 'https://t.me/share/url'
                         + `?url=${this.enc(this.url)}`
                         + `&text=${this.enc(this.title)}`;
                    this.openExternal(link, true);
                    break;

                case 'whatsapp':
                    link = `https://wa.me/?text=${this.enc(this.shareTextWhatsApp())}`;
                    this.openExternal(link, true);
                    break;

                case 'viber':
                    link = `viber://forward?text=${this.enc(this.shareTextGeneric())}`;
                    this.openExternal(link, false);
                    break;

                case 'email':
                    link = `mailto:?subject=${this.enc(this.title)}&body=${this.enc(`${this.description}\n\n${this.url}`)}`;
                    window.location.href = link;
                    break;

                default:
                    console.warn('Share: неизвестная платформа', platform);
            }
        },

        /* Системный шер-шит (только мобильные) */
        async shareNative() {
            if (typeof navigator.share !== 'function') return;
            try {
                await navigator.share({
                    title: this.title,
                    text:  this.description || this.title,
                    url:   this.url,
                });
            } catch (e) {
                if (e.name !== 'AbortError') console.error('Native share error:', e);
            }
        },

        /* Копирование */
        async copyLink() {
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(this.url);
                } else {
                    const ta = document.createElement('textarea');
                    ta.value = this.url;
                    ta.style.position = 'fixed';
                    ta.style.opacity = '0';
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                }
                this.copied = true;
                this.showToast('✅ Ссылка скопирована');
                setTimeout(() => { this.copied = false; }, 2500);
            } catch (e) {
                console.error(e);
                this.showToast('⚠️ Не удалось скопировать');
            }
        }
    }));
});