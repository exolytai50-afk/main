/**
 * GigaChef — пауза с динамическими рекламными блоками.
 */
function gcAdPause(defaultMinMs = 10000) {
    const bundle = window.__AI_AD__ || { min_duration_ms: defaultMinMs, items: [] };
    const minMs  = Math.max(defaultMinMs, bundle.min_duration_ms || 0);
    const items  = Array.isArray(bundle.items) ? bundle.items : [];
    const ROTATE_MS = 4000;

    return {
        adVisible: false,
        adTimer: 0,
        adHtml: '',
        adProvider: '',
        _adItems: items,
        _adIdx: 0,
        _adStart: 0,
        _adTimerId: null,
        _adRotateId: null,
        _adMinMs: minMs,

        adShow() {
    this.adVisible = true;
    this.adTimer = 0;
    this._adStart = Date.now();
    this.adHtml = '';
    this.adProvider = '';

    if (this._adItems.length) {
        this._renderAd(0); // Всегда показываем первый баннер
        // Ротация каждые 8 секунд, если баннеров больше одного
        if (this._adItems.length > 1) {
            this._adRotateId = setInterval(() => {
                this._adIdx = (this._adIdx + 1) % this._adItems.length;
                this._renderAd(this._adIdx);
            }, 10000);
        }
    }

    this._adTimerId = setInterval(() => {
        this.adTimer = Math.floor((Date.now() - this._adStart) / 1000);
    }, 1000);
},

        _renderAd(idx) {
            const it = this._adItems[idx];
            if (!it) return;
            this.adHtml = it.html || '';
            this.adProvider = it.provider || '';

            if (it.init_js) {
                try { (0, eval)(it.init_js); } catch (e) { console.warn('ad init', e); }
            }
            if (it.refresh_js) {
                try { (0, eval)(it.refresh_js); } catch (e) { console.warn('ad refresh', e); }
            }
        },

        // Возвращает Promise: операция ждёт минимум _adMinMs,
        // а если операция дольше — ждёт её завершения.
        async adHold(operation) {
            this.adShow();
            await new Promise(r => setTimeout(r, 50)); // Даём браузеру отрисовать баннер
            
            const start = Date.now();
            let result, error;
            try {
                const opPromise = typeof operation === 'function' ? operation() : operation;
                result = await opPromise;
            } catch (e) {
                error = e;
            }
            
            const elapsed = Date.now() - start;
            const left = Math.max(0, this._adMinMs - elapsed);
            if (left > 0) await new Promise(r => setTimeout(r, left));
            
            this.adRelease();
            if (error) throw error;
            return result;
        },

        adRelease() {
            this.adVisible = false;
            document.body.style.overflow = '';
            clearInterval(this._adTimerId);
            clearInterval(this._adRotateId);
            this._adTimerId = null;
            this._adRotateId = null;
            this.adTimer = 0;
            this.adHtml = '';
            this.adProvider = '';
            this._adIdx = 0;
        },
    };
}