/**
 * Бесконечная прокрутка ленты с интеграцией рекламных блоков.
 */
(function () {
    'use strict';

    const container = document.querySelector('[data-infinite-feed]');
    const sentinel  = document.querySelector('[data-infinite-sentinel]');
    if (!container || !sentinel) return;

    let loading = false;

    // Функция для получения HTML рекламного блока
    // Если у вас есть реальный код из РСЯ, его можно передать через скрытый шаблон в HTML
function getAdHtml() {
    // ✅ Премиум не видит рекламу
    if (window.__IS_PREMIUM_USER__ === true) {
        return '';
    }
    
    // (Если у вас будет реальный код РСЯ, его можно передать через скрытый шаблон в HTML)
    return `
        <div class="bg-white rounded-2xl shadow-md p-5 border border-gray-100">
            <div class="flex items-center justify-between mb-3">
                <span class="text-[10px] uppercase tracking-widest text-gray-400 font-bold">Реклама</span>
            </div>
            <div class="flex items-center gap-4 py-2">
                <div class="w-12 h-12 rounded-xl bg-gray-50 flex items-center justify-center text-gray-300">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:24px;height:24px;">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                        <polyline points="21 15 16 10 5 21"></polyline>
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-bold text-gray-700">Здесь могла быть ваша реклама</div>
                    <div class="text-xs text-gray-400 mt-0.5">Станьте партнером GigaChef</div>
                </div>
            </div>
        </div>
    `;
}

    // Функция для вставки рекламы в контейнер после определенной карточки
    function insertAdIfNeeded() {
        // Находим все карточки активности, исключая рекламные блоки
        const cards = Array.from(container.querySelectorAll('.activity-card-item'));
        
        // Проверяем, не вставлена ли уже реклама (по классу)
        // Нам нужно вставить рекламу после каждой 10-й карточки.
        // Считаем общее количество карточек на странице.
        
        // Проходим с конца, чтобы не сбить индексы
        for (let i = cards.length - 1; i >= 0; i--) {
            const cardIndex = i + 1; // 1, 2, 3...
            
            if (cardIndex % 10 === 0) {
                const card = cards[i];
                
                // Проверяем, нет ли уже рекламы сразу после этой карточки
                const nextEl = card.nextElementSibling;
                if (nextEl && nextEl.classList.contains('ad-in-feed-block')) {
                    continue; // Реклама уже есть
                }

                const adHtml = getAdHtml();
                    if (adHtml && adHtml.trim() !== '') {
                        const adDiv = document.createElement('div');
                        adDiv.className = 'ad-in-feed-block';
                        adDiv.innerHTML = adHtml;
                        card.after(adDiv);
                    }
            }
        }
    }

    // Наблюдатель за добавлением новых элементов (чтобы вставить рекламу после подгрузки)
    const mutationObserver = new MutationObserver((mutations) => {
        let shouldUpdate = false;
        mutations.forEach((mutation) => {
            if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                shouldUpdate = true;
            }
        });
        if (shouldUpdate) {
            // Используем setTimeout, чтобы дождаться, пока другие скрипты (AOS) закончат рендер
            setTimeout(insertAdIfNeeded, 100);
        }
    });

    mutationObserver.observe(container, { childList: true });

    // Первоначальная вставка рекламы после загрузки страницы
    setTimeout(insertAdIfNeeded, 500);

    // Функция подгрузки следующей страницы
    async function loadNext() {
        if (loading) return;
        if (container.dataset.infiniteHasmore !== '1') return;

        loading = true;
        const page  = parseInt(container.dataset.infinitePage || '1', 10) + 1;
        const scope = container.dataset.infiniteScope || 'all';
        const base  = container.dataset.infiniteUrl || '/api/feed_page.php';

        const url = new URL(base, window.location.origin);
        url.searchParams.set('scope', scope);
        url.searchParams.set('page', page);

        try {
            const res  = await fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();

            if (data.ok && data.html) {
                container.insertAdjacentHTML('beforeend', data.html);
                container.dataset.infinitePage    = page;
                container.dataset.infiniteHasmore = data.hasMore ? '1' : '0';

                // После вставки HTML вызываем проверку на рекламу
                insertAdIfNeeded();

                if (window.AOS && typeof AOS.refresh === 'function') AOS.refresh();
            } else {
                container.dataset.infiniteHasmore = '0';
            }
        } catch (err) {
            console.error('Infinite feed error:', err);
        } finally {
            loading = false;
        }
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) loadNext();
        });
    }, { rootMargin: '600px 0px' });

    observer.observe(sentinel);
})();