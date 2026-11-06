/**
 * Универсальная пагинация.
 *  - [data-page-link] — переход по номеру страницы (с сохранением ?page=N)
 *  - [data-load-more] — подгрузка следующей порции в [data-target]
 */
(function () {
    'use strict';

    function buildUrl(base, page) {
        const url = new URL(base, window.location.origin);
        url.searchParams.set('page', page);
        return url.toString();
    }

    document.addEventListener('click', async (e) => {
        /* ─── Переход по номеру страницы ─── */
        const numBtn = e.target.closest('[data-page-link]');
        if (numBtn) {
            const page = numBtn.getAttribute('data-page-link');
            const base = numBtn.closest('nav')?.querySelector('[data-load-more]')?.dataset.baseUrl
                      || window.location.pathname + window.location.search;
            const url  = buildUrl(base, page);
            window.location.href = url;
            return;
        }

        /* ─── «Показать ещё» ─── */
        const moreBtn = e.target.closest('[data-load-more]');
        if (!moreBtn) return;

        const targetSel = moreBtn.dataset.target;
        const base      = moreBtn.dataset.baseUrl || window.location.pathname + window.location.search;
        const nextPage  = parseInt(moreBtn.dataset.nextPage, 10) || 2;
        const total     = parseInt(moreBtn.dataset.totalPages, 10) || 1;

        const target = document.querySelector(targetSel);
        if (!target) return;

        moreBtn.disabled = true;
        const originalText = moreBtn.textContent;
        moreBtn.textContent = 'Загрузка…';

        try {
            const url = new URL(base, window.location.origin);
            url.searchParams.set('page', nextPage);
            url.searchParams.set('partial', '1');

            const res = await fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!res.ok) throw new Error('HTTP ' + res.status);

            const html = await res.text();
            target.insertAdjacentHTML('beforeend', html);

            // Перерисовываем AOS для новых карточек
            if (window.AOS && typeof AOS.refresh === 'function') AOS.refresh();

            // Обновляем состояние кнопки
            const newNext = nextPage + 1;
            if (newNext > total) {
                moreBtn.closest('[data-pagination-more]').remove();
            } else {
                moreBtn.dataset.nextPage = newNext;
                moreBtn.disabled = false;
                moreBtn.textContent = originalText;
            }

            // Обновляем URL (для истории браузера)
            history.replaceState({}, '', buildUrl(base, nextPage));
        } catch (err) {
            console.error('Pagination error:', err);
            moreBtn.disabled = false;
            moreBtn.textContent = originalText;
        }
    });
})();