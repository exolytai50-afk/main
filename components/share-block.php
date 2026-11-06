<?php
/**
 * Модальное окно «Поделиться» — GigaChef
 */
if (!isset($shareData) || !is_array($shareData)) {
    $shareData = [
        'title'       => $GLOBALS['pageTitle']       ?? 'GigaChef',
        'url'         => $GLOBALS['pageUrl']         ?? (($_SERVER['HTTPS'] ?? '') === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'gigachef.ru'),
        'description' => $GLOBALS['pageDescription'] ?? '',
        'image'       => $GLOBALS['pageImage']       ?? '',
    ];
}

// Гарантируем UTF-8
$shareData['title']       = mb_convert_encoding((string)($shareData['title']       ?? ''), 'UTF-8', 'UTF-8');
$shareData['description'] = mb_convert_encoding((string)($shareData['description'] ?? ''), 'UTF-8', 'UTF-8');
$shareData['url']         = (string)($shareData['url']   ?? '');
$shareData['image']       = (string)($shareData['image'] ?? '');

// Абсолютный URL — обязательно
if (!preg_match('#^https?://#', $shareData['url'])) {
    $scheme = (($_SERVER['HTTPS'] ?? '') === 'on') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'gigachef.ru';
    $shareData['url'] = $scheme . '://' . $host . '/' . ltrim($shareData['url'], '/');
}

if (!empty($shareData['image']) && !preg_match('#^https?://#', $shareData['image'])) {
    $scheme = (($_SERVER['HTTPS'] ?? '') === 'on') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'gigachef.ru';
    $shareData['image'] = $scheme . '://' . $host . '/' . ltrim($shareData['image'], '/');
}

// JSON с полным экранированием
$shareJson = json_encode(
    $shareData,
    JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP
);

// Финальная страховка
$shareJson = str_replace('"', '&#34;', (string)$shareJson);
?>

<div x-data="shareBlock(<?= $shareJson ?>)" x-cloak
     @open-share-modal.window="open = true"
     @keydown.escape.window="open = false">

    <!-- Оверлей + модалка -->
    <div x-show="open" x-transition.opacity
         style="display:none"
         class="fixed inset-0 z-[200] flex items-end sm:items-center justify-center bg-black/60 p-0 sm:p-4"
         @click.self="open = false"
         role="dialog" aria-modal="true" aria-label="Поделиться">

        <div class="bg-white w-full sm:max-w-2xl sm:rounded-3xl rounded-t-3xl shadow-2xl flex flex-col max-h-[90vh]"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0">

            <!-- Заголовок -->
            <div class="flex items-center justify-between p-4 sm:p-5 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <span class="share-block__icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" width="20" height="20" fill="currentColor">
                            <path d="M229.66,109.66l-48,48a8,8,0,0,1-11.32-11.32L204.69,112H165a88,88,0,0,0-85.23,66,8,8,0,0,1-15.5-4A103.94,103.94,0,0,1,165,96h39.71L170.34,61.66a8,8,0,0,1,11.32-11.32l48,48A8,8,0,0,1,229.66,109.66ZM192,208H40V88a8,8,0,0,0-16,0V216a8,8,0,0,0,8,8H192a8,8,0,0,0,0-16Z"/>
                        </svg>
                    </span>
                    <h3 class="font-extrabold text-gray-800 text-lg">Поделиться</h3>
                </div>
                <button type="button" @click="open = false"
                        class="w-8 h-8 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600 text-2xl flex items-center justify-center transition"
                        aria-label="Закрыть">×</button>
            </div>

            <!-- Превью -->
            <div class="flex items-center gap-3 px-4 sm:px-5 py-3 border-b border-gray-50">
                <template x-if="image">
                    <img :src="image" class="w-12 h-12 rounded-lg object-cover shrink-0" alt="">
                </template>
                <div class="min-w-0 flex-1">
                    <div class="font-bold text-gray-800 text-sm truncate" x-text="title"></div>
                    <div class="text-xs text-gray-400 truncate" x-text="url.replace(/^https?:\/\//,'')"></div>
                </div>
            </div>

            <!-- Сетка иконок -->
            <div class="flex-1 p-4 sm:p-5">
                <div class="share-block__grid">

    <!-- MAX -->
    <button type="button" class="share-btn share-btn--max"
            @click="share('max')" :title="'Поделиться в MAX'" aria-label="Поделиться в MAX">
        <span class="share-btn__icon">
            <svg width="2em" height="2em" viewBox="0 0 500 500" xmlns="http://www.w3.org/2000/svg" fill="none" style="flex-shrink:0" aria-hidden="true"><path fill="url(#a)" d="M0 0h500v500H0z"/><path fill="url(#b)" d="M0 0h500v500H0z"/><path fill="#fff" fill-rule="evenodd" d="M253.223 398.85c-29.441 0-43.123-4.298-66.905-21.49-15.043 19.341-62.679 34.456-64.756 8.596 0-19.412-4.298-35.816-9.17-53.725C106.59 310.168 100 285.598 100 249.997 100 164.968 169.771 101 252.435 101c82.736 0 147.564 67.12 147.564 149.784.278 81.386-65.391 147.632-146.776 148.066m1.218-224.355c-40.258-2.077-71.633 25.788-78.582 69.484-5.73 36.175 4.442 80.229 13.109 82.522 4.155 1.002 14.613-7.45 21.132-13.969a74.5 74.5 0 0 0 36.39 12.966c41.713 2.006 77.356-29.75 80.157-71.418 1.63-41.756-30.487-77.123-72.206-79.513z" clip-rule="evenodd"/><defs><radialGradient id="b" cx="0" cy="0" r="1" gradientTransform="matrix(378.9086 473.90374 -195.26028 156.1199 -11.895 -219.593)" gradientUnits="userSpaceOnUse"><stop stop-color="#00F"/><stop offset="1" stop-opacity="0"/></radialGradient><linearGradient id="a" x1="58.923" x2="500" y1="380.268" y2="250" gradientUnits="userSpaceOnUse"><stop stop-color="#4CF"/><stop offset=".662" stop-color="#53E"/><stop offset="1" stop-color="#93D"/></linearGradient></defs></svg>
        </span>
        <span class="share-btn__label">MAX</span>
    </button>

    <!-- VK -->
    <button type="button" class="share-btn share-btn--vk"
            @click="share('vk')" :title="'Поделиться ВКонтакте'" aria-label="Поделиться ВКонтакте">
        <span class="share-btn__icon">
            <svg width="2em" height="2em" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" style="flex-shrink:0" aria-hidden="true"><rect height="64" rx="11.2" ry="11.2" width="64" fill="#4489c8"/><path fill="#fff" d="M9.62,19.77H17.3a.79.79,0,0,1,.74.51c.85,2.19,4.38,10.76,7.61,11.46,2.4,0,1.55-11.4-1.5-11.4-.8,0,1.42-1.42,1.57-1.48a16.38,16.38,0,0,1,8.66,0c1.53.64,1.79,2.43,1.83,3.95s-1.7,8,.59,8.7c3,.92,6.86-8.49,7.76-10.8a.79.79,0,0,1,.74-.5h8.55a.8.8,0,0,1,.74,1.11,74.74,74.74,0,0,1-6.31,11.52,1.59,1.59,0,0,0,.16,2C51,37.58,58,45.34,54,45.34H45.37a1.57,1.57,0,0,1-1.18-.53c-1.34-1.48-5.24-5.6-7-5.27-1.46.27-1.6,2.75-1.53,4.37a1.6,1.6,0,0,1-1.48,1.68c-1.53.09-3.62.13-3.81.12-6.1-.39-9.71-4.46-13.16-9A56.33,56.33,0,0,1,8.86,20.83.81.81,0,0,1,9.62,19.77Z"/></svg>
        </span>
        <span class="share-btn__label">ВКонтакте</span>
    </button>

    <!-- OK -->
    <button type="button" class="share-btn share-btn--ok"
            @click="share('ok')" :title="'Поделиться в Одноклассниках'" aria-label="Поделиться в Одноклассниках">
        <span class="share-btn__icon">
            <svg width="2em" height="2em" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 44 44" style="flex-shrink:0" aria-hidden="true"><path fill="#F70" d="M0 0h44v44H0z"/><path fill="#fff" d="M26.868 19.87a6.85 6.85 0 0 1-2.229 1.492 6.88 6.88 0 0 1-7.5-1.492 6.87 6.87 0 0 1-1.875-3.51 6.84 6.84 0 0 1 .392-3.955 6.9 6.9 0 0 1 2.527-3.08 6.96 6.96 0 0 1 3.82-1.18 6.96 6.96 0 0 1 3.83 1.177 6.9 6.9 0 0 1 2.532 3.086 6.84 6.84 0 0 1 .389 3.962 6.87 6.87 0 0 1-1.886 3.512zm-4.877-7.978a3.15 3.15 0 0 0-2.21.917 3.105 3.105 0 0 0 0 4.397c.586.584 1.38.914 2.21.918a3.15 3.15 0 0 0 2.21-.918 3.1 3.1 0 0 0 .913-2.198c0-.824-.328-1.615-.914-2.199a3.15 3.15 0 0 0-2.209-.917m7.239 10.017 2.014 2.753a.315.315 0 0 1-.05.434 14.2 14.2 0 0 1-5.725 2.79l3.889 7.495a.316.316 0 0 1-.123.43.3.3 0 0 1-.16.041h-4.17a.32.32 0 0 1-.296-.19l-2.605-6.182-2.618 6.181a.32.32 0 0 1-.295.191H14.92c-.244 0-.386-.255-.283-.471l3.902-7.494c-2.054-.484-4.03-1.402-5.724-2.791a.32.32 0 0 1-.064-.434l2.027-2.74a.334.334 0 0 1 .475-.064 10.5 10.5 0 0 0 6.75 2.677c2.503 0 4.84-1.045 6.752-2.677.128-.127.359-.102.475.051"/></svg>
        </span>
        <span class="share-btn__label">Одноклассники</span>
    </button>

    <!-- Telegram -->
    <button type="button" class="share-btn share-btn--tg"
            @click="share('telegram')" :title="'Поделиться в Telegram'" aria-label="Поделиться в Telegram">
        <span class="share-btn__icon">
            <svg width="2em" height="2em" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="flex-shrink:0" aria-hidden="true"><rect width="512" height="512" rx="15%" fill="#37aee2"/><path fill="#c8daea" d="M199 404c-11 0-10-4-13-14l-32-105 245-144"/><path fill="#a9c9dd" d="M199 404c7 0 11-4 16-8l45-43-56-34"/><path fill="#f6fbfe" d="M204 319l135 99c14 9 26 4 30-14l55-258c5-22-9-32-24-25L79 245c-21 8-21 21-4 26l83 26 190-121c9-5 17-3 11 4"/></svg>
        </span>
        <span class="share-btn__label">Telegram</span>
    </button>

    <!-- WhatsApp -->
    <button type="button" class="share-btn share-btn--wa"
            @click="share('whatsapp')" :title="'Поделиться в WhatsApp'" aria-label="Поделиться в WhatsApp">
        <span class="share-btn__icon">
            <svg width="2em" height="2em" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="flex-shrink:0" aria-hidden="true"><rect width="512" height="512" rx="15%" fill="#45d354"/><path fill="#fff" d="M308 273c-3-2-6-3-9 1l-12 16c-3 2-5 3-9 1-15-8-36-17-54-47-1-4 1-6 3-8l9-14c2-2 1-4 0-6l-12-29c-3-8-6-7-9-7h-8c-2 0-6 1-10 5-22 22-13 53 3 73 3 4 23 40 66 59 32 14 39 12 48 10 11-1 22-10 27-19 1-3 6-16 2-18"/><path fill="#fff" d="M264 384c-41 0-72-22-72-22l-49 13 12-48s-20-31-20-70c0-72 59-132 132-132 68 0 126 53 126 127 0 72-58 131-129 132m-159 29l83-23a158 158 0 0 0 230-140c0-86-68-155-154-155a158 158 0 0 0-137 236"/></svg>
        </span>
        <span class="share-btn__label">WhatsApp</span>
    </button>

    <!-- Viber -->
    <button type="button" class="share-btn share-btn--viber"
            @click="share('viber')" :title="'Поделиться в Viber'" aria-label="Поделиться в Viber">
        <span class="share-btn__icon">
            <svg width="2em" height="2em" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="flex-shrink:0" aria-hidden="true"><rect width="512" height="512" rx="15%" fill="#665ca7"/><path fill="none" stroke="#fff" stroke-linecap="round" stroke-width="10" d="M269 186a30 30 0 0 1 31 31m-38-58a64 64 0 0 1 64 67m-73-93a97 97 0 0 1 99 104"/><path fill="#fff" fill-rule="evenodd" d="M95 232c0-91 17-147 161-147s161 56 161 147-17 147-161 147l-26-1-53 63c-4 4-8 1-8-3v-69c-6 0-31-12-38-19-22-23-36-40-36-118zm-30 0c0-126 55-177 191-177s191 51 191 177-55 177-191 177c-10 0-18 0-32-2l-38 43c-7 8-28 11-28-13v-42c-6 0-20-6-39-18-19-13-54-44-54-145zm223 42q10-13 24-4l36 27q8 10-7 28t-28 15q-53-12-102-60t-61-104q0-20 25-34 13-9 22 5l25 35q6 12-7 22c-39 15 51 112 73 70z"/></svg>
        </span>
        <span class="share-btn__label">Viber</span>
    </button>

    <!-- Email -->
    <button type="button" class="share-btn share-btn--email"
            @click="share('email')" :title="'Поделиться по почте'" aria-label="Поделиться по почте">
        <span class="share-btn__icon">
            <svg width="2em" height="2em" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg" style="flex-shrink:0" aria-hidden="true"><path fill="#FFCA38" d="M0 0h40v40H0z"/><path fill="#FFDC7C" d="M40 32.994V40H0v-7.463L17.78 17.72a3.04 3.04 0 0 1 3.891 0z"/><path fill="url(#ea)" d="M40 8.565 22.018 24.578a3.04 3.04 0 0 1-4.038.005L0 8.638V0h40z"/><path fill="url(#eb)" fill-opacity=".5" d="M40 8.565 22.018 24.578a3.04 3.04 0 0 1-4.038.005L0 8.638V0h40z"/><defs><linearGradient id="ea" x1="49.479" x2="16.304" y1="-25.224" y2="19.19" gradientUnits="userSpaceOnUse"><stop stop-color="#FFDA3E"/><stop offset="1" stop-color="#FC4714"/></linearGradient><linearGradient id="eb" x1="47.794" x2="20.067" y1="-3.132" y2="21.921" gradientUnits="userSpaceOnUse"><stop stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></linearGradient></defs></svg>
        </span>
        <span class="share-btn__label">Email</span>
    </button>

    <!-- Копировать -->
    <button type="button" class="share-btn share-btn--copy"
            @click="copyLink()" :title="'Копировать ссылку'" aria-label="Копировать ссылку">
        <span class="share-btn__icon">
            <svg width="2em" height="2em" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" preserveAspectRatio="xMidYMid meet" style="flex-shrink:0" aria-hidden="true" x-show="!copied"><path fill="#00C3FF" d="M31.467 8.267l-7.733-7.733c-0.711-0.711-1.866-0.711-2.577 0l-7.733 7.733c-0.713 0.711-0.713 1.866 0 2.579l1.288 1.288 7.733-7.733 5.156 5.156-7.733 7.733 1.29 1.29c0.711 0.711 1.866 0.711 2.577 0l7.733-7.733c0.711-0.713 0.711-1.868 0-2.579zM17.29 19.867l-7.733 7.733-5.156-5.156 7.733-7.733-1.29-1.29c-0.711-0.711-1.866-0.711-2.577 0l-7.733 7.733c-0.711 0.713-0.711 1.868 0 2.579l7.733 7.733c0.711 0.711 1.866 0.711 2.577 0l7.733-7.733c0.713-0.711 0.713-1.866 0-2.579l-1.288-1.288zM10.844 21.154c0.713 0.713 1.866 0.713 2.579 0l7.733-7.733c0.711-0.711 0.711-1.864 0-2.575-0.713-0.713-1.866-0.713-2.579 0l-7.733 7.733c-0.711 0.711-0.711 1.864 0 2.575z"/></svg>
            <svg width="2em" height="2em" viewBox="0 0 24 24" fill="#00C3FF" style="flex-shrink:0;display:none" aria-hidden="true" x-show="copied"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2Z"/></svg>
        </span>
        <span class="share-btn__label" x-text="copied ? 'Готово' : 'Ссылка'"></span>
    </button>

    <!-- Ещё (только мобильные) -->
    <button type="button" class="share-btn share-btn--native"
            x-show="showNativeShare"
            @click="shareNative()"
            :title="'Поделиться через…'"
            aria-label="Поделиться через системное меню">
        <span class="share-btn__icon">
            <svg width="2em" height="2em" viewBox="0 0 24 24" fill="#f59e0b" style="flex-shrink:0" aria-hidden="true">
                <path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z"/>
            </svg>
        </span>
        <span class="share-btn__label">Ещё</span>
    </button>
</div>
            </div>

            <!-- Тост -->
            <div x-show="toast" x-transition.opacity.duration.200ms x-cloak
                 class="absolute bottom-4 left-1/2 -translate-x-1/2 bg-gray-800 text-white text-sm font-semibold px-4 py-2 rounded-lg shadow-lg whitespace-nowrap"
                 x-text="toast"></div>
        </div>
    </div>
</div>