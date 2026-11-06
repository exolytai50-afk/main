/**
 * GigaChefAI — универсальный HTTP-клиент для фронтенда.
 * Обёртка над fetch с обработкой ошибок, кэшем GET, CSRF и статусами 401/403/429/503.
 *
 * Использование:
 *   const data = await CookAPI.get('/api/food_diary.php?days=14');
 *   const res  = await CookAPI.post('/api/ai_calorie_scan.php', { image, save: true });
 *   const up   = await CookAPI.upload('/api/upload.php', formData, p => console.log(p));
 *
 * Требует (необязательно) глобальную функцию toast(message, type).
 *
 * ВАЖНО: CSRF-токен и base-path читаются ДИНАМИЧЕСКИ в момент запроса,
 * поэтому порядок подключения скрипта относительно <meta> не критичен.
 */
(function (window) {
    'use strict';

    // ==========================================================
    //  Конфигурация
    // ==========================================================
    const CACHE_TTL       = 30 * 1000;   // TTL кэша GET, мс
    const DEFAULT_TIMEOUT = 90000;       // таймаут по умолчанию, мс (vision-анализ долгий)

    // Кэш GET-запросов (в памяти)
    const cache = new Map();

    // ==========================================================
    //  Динамическое чтение meta-тегов
    // ==========================================================

    /** CSRF-токен из <meta name="csrf-token"> — читаем КАЖДЫЙ раз */
    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? (meta.getAttribute('content') || '') : '';
    }

    /** Базовый путь из <meta name="base-path"> — читаем КАЖДЫЙ раз */
    function getBasePath() {
        const meta = document.querySelector('meta[name="base-path"]');
        return meta ? (meta.getAttribute('content') || '').replace(/\/+$/, '') : '';
    }

    // ==========================================================
    //  Вспомогательные функции
    // ==========================================================

    /** Безопасный вызов toast, если он определён */
    function notify(message, type) {
        if (typeof window.toast === 'function') {
            window.toast(message, type || 'info');
        }
    }

    /** Собирает полный URL с учётом BASE_PATH */
    function buildUrl(path) {
        if (/^https?:\/\//i.test(path)) return path;           // абсолютный URL — как есть
        const clean = path.startsWith('/') ? path : '/' + path;
        return getBasePath() + clean;
    }

    /** Ключ кэша */
    function cacheKey(url) {
        return 'GET:' + url;
    }

    /** Дефолтные сообщения по HTTP-статусу */
    function defaultMessage(status) {
        const map = {
            400: 'Некорректный запрос',
            401: 'Требуется вход в аккаунт',
            403: 'Доступ запрещён',
            404: 'Ресурс не найден',
            413: 'Файл слишком большой',
            422: 'Проверьте правильность заполнения полей',
            429: 'Слишком много запросов. Попробуйте позже.',
            500: 'Внутренняя ошибка сервера',
            502: 'Сервер временно недоступен',
            503: 'Идут технические работы',
        };
        return map[status] || ('Ошибка запроса (' + status + ')');
    }

    /** Общие заголовки (CSRF читается динамически) */
    function baseHeaders(extra) {
        const h = {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        };
        const token = getCsrfToken();
        if (token) h['X-CSRF-Token'] = token;
        return Object.assign(h, extra || {});
    }

    /** Мягкий редирект на страницу входа при 401 */
    function redirectToLogin() {
        if (!/\/login/.test(location.pathname)) {
            const back = encodeURIComponent(location.pathname + location.search);
            setTimeout(() => { location.href = buildUrl('/login?back=' + back); }, 800);
        }
    }

    // ==========================================================
    //  Обработка ответа
    // ==========================================================
    async function handleResponse(res) {
        const contentType = res.headers.get('content-type') || '';
        const isJson = contentType.includes('application/json');
        let data = null;

        if (isJson) {
            data = await res.json().catch(() => null);
        } else {
            data = await res.text().catch(() => '');
        }

        // --- Успех ---
        if (res.ok) {
            return isJson ? data : { ok: true, raw: data };
        }

        // --- Ошибки ---
        const message = (isJson && data && data.error) ? data.error : defaultMessage(res.status);
        const err = new Error(message);
        err.code = res.status;
        err.data = data;

        switch (res.status) {
            case 401: // не авторизован
                notify(message, 'error');
                redirectToLogin();
                break;

            case 403: // нет прав / CSRF
                notify(message, 'error');
                break;

            case 429: // лимит AI-запросов
                notify(message, 'error');
                err.retryAfter  = (isJson && data) ? data.retry_after : null;
                err.isPro       = (isJson && data) ? data.is_pro : false;
                err.limitReset  = (isJson && data) ? data.reset : null;
                break;

            case 503: // техработы
                notify(message, 'error');
                err.maintenance = true;
                err.retryAfter  = (isJson && data) ? data.retry_after : null;
                break;

            default:
                notify(message, 'error');
                break;
        }

        throw err;
    }

    // ==========================================================
    //  Ядро запроса (fetch + таймаут)
    // ==========================================================
    async function request(method, path, body, options) {
        options = options || {};
        const url = buildUrl(path);

        const controller = new AbortController();
        const timeout = options.timeout || DEFAULT_TIMEOUT;
        const timer = setTimeout(() => controller.abort(), timeout);

        const init = {
            method,
            headers: baseHeaders(options.headers),
            credentials: 'same-origin',   // отправляем cookie сессии
            signal: controller.signal,
        };

        // --- Формируем тело ---
        if (body !== undefined && body !== null) {
            const token = getCsrfToken();

            if (body instanceof FormData) {
                // не задаём Content-Type — браузер сам добавит boundary
                if (token && !body.has('_csrf')) body.append('_csrf', token);
                init.body = body;
            } else if (typeof body === 'object' && !Array.isArray(body)) {
                init.headers['Content-Type'] = 'application/json';
                // дублируем _csrf в тело как fallback (если сервер читает из JSON)
                init.body = JSON.stringify(Object.assign({ _csrf: token }, body));
            } else {
                init.headers['Content-Type'] = 'application/json';
                init.body = JSON.stringify(body);
            }
        }

        try {
            const res = await fetch(url, init);
            clearTimeout(timer);
            return await handleResponse(res);
        } catch (e) {
            clearTimeout(timer);

            if (e.name === 'AbortError') {
                const err = new Error('Превышено время ожидания ответа. Попробуйте ещё раз.');
                err.code = 'timeout';
                notify(err.message, 'error');
                throw err;
            }
            // Сетевые ошибки (нет соединения)
            if (e instanceof TypeError) {
                const err = new Error('Нет соединения с сервером. Проверьте интернет.');
                err.code = 'network';
                notify(err.message, 'error');
                throw err;
            }
            throw e; // уже обработанные ошибки статусов пробрасываем дальше
        }
    }

    // ==========================================================
    //  Публичный API
    // ==========================================================
    const CookAPI = {
        /** GET с кэшированием (TTL 30 c). options.noCache — отключить кэш. */
        async get(path, options) {
            options = options || {};
            const url = buildUrl(path);
            const key = cacheKey(url);

            if (!options.noCache) {
                const cached = cache.get(key);
                if (cached && (Date.now() - cached.time) < CACHE_TTL) {
                    return cached.data;
                }
            }

            const data = await request('GET', path, null, options);
            if (!options.noCache) {
                cache.set(key, { data, time: Date.now() });
            }
            return data;
        },

        /** POST (JSON или FormData). Сбрасывает кэш GET. */
        async post(path, body, options) {
            const data = await request('POST', path, body, options);
            this.clearCache();
            return data;
        },

        /** PUT. Сбрасывает кэш GET. */
        async put(path, body, options) {
            const data = await request('PUT', path, body, options);
            this.clearCache();
            return data;
        },

        /** DELETE. Сбрасывает кэш GET. */
        async del(path, body, options) {
            const data = await request('DELETE', path, body, options);
            this.clearCache();
            return data;
        },

        /** Загрузка файла (FormData) с прогрессом через XHR */
        upload(path, formData, onProgress, options) {
            options = options || {};
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                const url = buildUrl(path);
                xhr.open('POST', url);
                xhr.withCredentials = true;
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.setRequestHeader('Accept', 'application/json');

                // CSRF: заголовок + дублирование в FormData
                const token = getCsrfToken();
                if (token) {
                    xhr.setRequestHeader('X-CSRF-Token', token);
                    if (formData instanceof FormData && !formData.has('_csrf')) {
                        formData.append('_csrf', token);
                    }
                }

                // Таймаут
                xhr.timeout = options.timeout || DEFAULT_TIMEOUT;
                xhr.ontimeout = () => {
                    const err = new Error('Превышено время ожидания загрузки. Попробуйте ещё раз.');
                    err.code = 'timeout';
                    notify(err.message, 'error');
                    reject(err);
                };

                // Прогресс
                if (typeof onProgress === 'function' && xhr.upload) {
                    xhr.upload.onprogress = (e) => {
                        if (e.lengthComputable) {
                            onProgress(Math.round((e.loaded / e.total) * 100));
                        }
                    };
                }

                xhr.onload = () => {
                    let data = null;
                    try { data = JSON.parse(xhr.responseText); } catch (_) {}

                    if (xhr.status >= 200 && xhr.status < 300) {
                        this.clearCache();
                        resolve(data || { ok: true });
                        return;
                    }

                    const err = new Error((data && data.error) || defaultMessage(xhr.status));
                    err.code = xhr.status;
                    err.data = data;
                    notify(err.message, 'error');

                    if (xhr.status === 401) redirectToLogin();
                    reject(err);
                };

                xhr.onerror = () => {
                    const err = new Error('Ошибка загрузки файла. Проверьте соединение.');
                    err.code = 'network';
                    notify(err.message, 'error');
                    reject(err);
                };

                xhr.send(formData);
            });
        },

        /** Очистка кэша GET (полностью или по префиксу пути) */
        clearCache(pathPrefix) {
            if (!pathPrefix) { cache.clear(); return; }
            const full = cacheKey(buildUrl(pathPrefix));
            for (const key of cache.keys()) {
                if (key.startsWith(full)) cache.delete(key);
            }
        },

        /** Утилита: полный URL с учётом BASE_PATH (для ссылок в JS) */
        url: buildUrl,

        /** Текущий CSRF-токен (динамически) — для ручных запросов */
        get csrf() { return getCsrfToken(); },

        /** Текущий базовый путь (динамически) */
        get basePath() { return getBasePath(); },
    };

    window.CookAPI = CookAPI;

    // ==========================================================
    //  Резервная реализация toast (если не подключён свой)
    // ==========================================================
    if (typeof window.toast !== 'function') {
        window.toast = function (message, type) {
            const colors = {
                success: 'bg-emerald-500',
                error:   'bg-rose-500',
                info:    'bg-gray-800',
            };
            const el = document.createElement('div');
            el.className =
                'fixed bottom-6 right-6 z-[9999] px-5 py-3 rounded-xl text-white font-semibold shadow-lg '
                + (colors[type] || colors.info)
                + ' transition-all duration-300 opacity-0 translate-y-2';
            el.textContent = message;
            document.body.appendChild(el);

            requestAnimationFrame(() => {
                el.classList.remove('opacity-0', 'translate-y-2');
            });
            setTimeout(() => {
                el.classList.add('opacity-0', 'translate-y-2');
                setTimeout(() => el.remove(), 300);
            }, 3500);
        };
    }
})(window);