<?php
/**
 * GigaChef — общий каркас админ-панели.
 *
 * Подключение на странице админки (после config.php):
 *     require_once __DIR__ . '/../includes/admin_ui.php';
 *
 * Использование внутри <section> страницы (после header.php):
 *     <?php admin_top('users', 'user', 'Пользователи', 'Роли, премиум, удаление'); ?>
 *     <?php admin_flash($success, $error); ?>
 *
 * Что даёт:
 *   - admin_top()     вкладки разделов (горизонтальная прокрутка) + заголовок страницы;
 *   - admin_flash()   одинаковые сообщения об успехе/ошибке;
 *   - admin_subtabs() вкладки внутри страницы (Alpine), тот же вид;
 *   - admin_cls()     общие наборы Tailwind-классов (кнопки, поля, таблицы, модалки).
 *
 * Иконки — только SVG через icon('имя', 'gc-icon-xs').
 * Размеры задаются классом: gc-icon-xs / sm / md / lg / xl (см. icons.css).
 */

if (!function_exists('admin_nav_items')) {
    /**
     * Разделы админки: ключ => [маршрут, иконка, название, описание для страницы «Обзор»].
     */
    function admin_nav_items(): array
    {
        return [
            'panel'       => ['admin',             'home',          'Обзор',          'Статистика и последние события'],
            'users'       => ['admin/users',       'user',          'Пользователи',   'Блокировка, роли, подписки'],
            'recipes'     => ['admin/recipes',     'cookbook',      'Рецепты',        'Редактирование, удаление, модерация'],
            'comments'    => ['admin/comments',    'quote',         'Комментарии',    'Модерация комментариев'],
            'reviews'     => ['admin/reviews',     'star',          'Отзывы',         'Модерация отзывов'],
            'messages'    => ['admin/messages',    'mail',          'Сообщения',      'Чат с пользователями и обращения'],
            'communities' => ['admin/communities', 'communities',   'Сообщества',     'Управление группами'],
            'challenges'  => ['admin/challenges',  'challenges',    'Челленджи',      'Создание и управление'],
            'news'        => ['admin/news',        'news',          'Новости',        'Публикации и закрепление'],
            'games'       => ['admin/games',       'games',         'Игры',           'Каталог игр'],
            'stickers'    => ['admin/stickers',    'palette',       'Стикеры',        'Стикерпаки для чата'],
            'emoji'       => ['admin/emoji',       'happy',         'Смайлы',         'PNG-смайлы и пакеты'],
            'ads'         => ['admin/ads',         'notifications', 'Реклама',        'Рекламные блоки'],
            'meta'        => ['admin/meta',        'tags',          'Мета-теги',      'SEO и Sitemap'],
            'backup'      => ['admin/backup',      'diskette',      'Бэкап',          'Экспорт и импорт базы, sitemap'],
            'cdn'         => ['admin/cdn',         'globe',         'CDN',            'Доставка файлов через CDN'],
            'maintenance' => ['admin/maintenance', 'technical',     'Техработы',      'Режим технического обслуживания'],
        ];
    }
}

if (!function_exists('admin_cls')) {
    /**
     * Общие наборы классов. Можно передавать несколько ключей через пробел:
     *   class="<?= admin_cls('btn btn-primary') ?>"
     */
    function admin_cls(string $keys): string
    {
        static $map = [
            'card'        => 'bg-white rounded-2xl shadow-md',
            'input'       => 'w-full px-4 py-2.5 rounded-xl border border-gray-200 outline-none focus:border-amber-300 text-sm',
            'label'       => 'block text-xs font-semibold text-gray-600 mb-1',
            'th'          => 'px-4 py-3 text-left text-xs font-semibold text-gray-400 whitespace-nowrap',
            'btn'         => 'inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-bold transition disabled:opacity-60',
            'btn-primary' => 'bg-amber-400 text-amber-900 hover:bg-amber-500',
            'btn-soft'    => 'bg-amber-100 text-amber-700 hover:bg-amber-200',
            'btn-success' => 'bg-emerald-500 text-white hover:bg-emerald-600',
            'btn-danger'  => 'bg-rose-500 text-white hover:bg-rose-600',
            'btn-ghost'   => 'bg-gray-100 text-gray-600 hover:bg-gray-200',
            'ibtn'        => 'w-9 h-9 inline-flex items-center justify-center rounded-lg transition',
            'ibtn-edit'   => 'text-amber-600 hover:bg-amber-50',
            'ibtn-del'    => 'text-rose-500 hover:bg-rose-50',
            'ibtn-ok'     => 'text-emerald-600 hover:bg-emerald-50',
            'ibtn-info'   => 'text-blue-600 hover:bg-blue-50',
            'overlay'     => 'fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4',
            'modal'       => 'bg-white rounded-3xl shadow-xl w-full p-6',
        ];
        $out = [];
        foreach (preg_split('/\s+/', trim($keys)) as $k) {
            if (isset($map[$k])) $out[] = $map[$k];
        }
        return implode(' ', $out);
    }
}

if (!function_exists('admin_tabs')) {
    /**
     * Вкладки разделов с горизонтальной прокруткой.
     * Активная вкладка автоматически прокручивается в видимую область.
     */
    function admin_tabs(string $active): void
    {
        static $assets = false;
        if (!$assets) {
            $assets = true;
            ?>
<style>
.adm-tabs-wrap{position:relative}
.adm-tabs{position:relative;display:flex;gap:8px;overflow-x:auto;overflow-y:hidden;scroll-behavior:smooth;-webkit-overflow-scrolling:touch;
  scrollbar-width:none;padding:4px 2px 8px;
  -webkit-mask-image:linear-gradient(to right,transparent 0,#000 var(--fl,0px),#000 calc(100% - var(--fr,0px)),transparent 100%);
  mask-image:linear-gradient(to right,transparent 0,#000 var(--fl,0px),#000 calc(100% - var(--fr,0px)),transparent 100%)}
.adm-tabs::-webkit-scrollbar{display:none}
.adm-tab{flex:0 0 auto;display:inline-flex;align-items:center;gap:8px;padding:9px 14px;border-radius:14px;font-size:14px;font-weight:700;
  line-height:1.2;white-space:nowrap;text-decoration:none;transition:background-color .15s,color .15s,box-shadow .15s;cursor:pointer;border:0}
.adm-tab:focus-visible{outline:2px solid #f59e0b;outline-offset:2px}
.adm-tabs-arrow{position:absolute;top:calc(50% - 2px);transform:translateY(-50%);width:32px;height:32px;border-radius:50%;display:none;
  align-items:center;justify-content:center;z-index:2;border:0;cursor:pointer;padding:0}
.adm-tabs-prev{left:-6px}.adm-tabs-next{right:-6px}
.adm-tabs-wrap[data-prev] .adm-tabs-prev,.adm-tabs-wrap[data-next] .adm-tabs-next{display:flex}
@media (hover:none){.adm-tabs-arrow{display:none!important}}
.adm-tabs-wrap.is-sub .adm-tab{padding:8px 12px;font-size:13px}
</style>
<script>
(function(){
  function init(){
    var wraps=document.querySelectorAll('[data-adm-tabs]');
    Array.prototype.forEach.call(wraps,function(w){
      if(w.__admTabs) return; w.__admTabs=true;
      var t=w.querySelector('.adm-tabs'), p=w.querySelector('.adm-tabs-prev'), n=w.querySelector('.adm-tabs-next');
      if(!t) return;
      function upd(){
        var max=t.scrollWidth-t.clientWidth, l=t.scrollLeft;
        if(l>2) w.setAttribute('data-prev',''); else w.removeAttribute('data-prev');
        if(l<max-2) w.setAttribute('data-next',''); else w.removeAttribute('data-next');
        t.style.setProperty('--fl', l>2 ? '28px' : '0px');
        t.style.setProperty('--fr', l<max-2 ? '28px' : '0px');
      }
      t.addEventListener('scroll',upd,{passive:true});
      window.addEventListener('resize',upd);
      if(p) p.addEventListener('click',function(){t.scrollBy({left:-Math.max(200,t.clientWidth*0.6),behavior:'smooth'});});
      if(n) n.addEventListener('click',function(){t.scrollBy({left:Math.max(200,t.clientWidth*0.6),behavior:'smooth'});});
      var a=t.querySelector('[aria-current]');
      if(a){ t.style.scrollBehavior='auto'; t.scrollLeft=a.offsetLeft-(t.clientWidth-a.offsetWidth)/2; t.style.scrollBehavior=''; }
      upd();
    });
  }
  window.admTabsInit=init;
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init); else init();
})();
</script>
            <?php
        }

        echo '<nav class="adm-tabs-wrap mb-5" data-adm-tabs aria-label="Разделы админ-панели">';
        echo '<button type="button" class="adm-tabs-arrow adm-tabs-prev bg-white text-gray-500 shadow-md" aria-label="Прокрутить влево">'
           . icon('left', 'gc-icon-sm') . '</button>';
        echo '<div class="adm-tabs">';
        foreach (admin_nav_items() as $key => $item) {
            $isActive = ($key === $active);
            $cls = $isActive
                ? 'bg-amber-400 text-amber-900 shadow-sm'
                : 'bg-white text-gray-600 hover:bg-amber-50 hover:text-amber-700';
            echo '<a href="' . e(url($item[0])) . '" class="adm-tab ' . $cls . '"'
               . ($isActive ? ' aria-current="page"' : '') . '>'
               . icon($item[1], 'gc-icon-sm') . '<span>' . e($item[2]) . '</span></a>';
        }
        echo '</div>';
        echo '<button type="button" class="adm-tabs-arrow adm-tabs-next bg-white text-gray-500 shadow-md" aria-label="Прокрутить вправо">'
           . icon('right', 'gc-icon-sm') . '</button>';
        echo '</nav>';
    }
}

if (!function_exists('admin_header')) {
    /**
     * Заголовок страницы: иконка, название, подзаголовок и (необязательно) блок кнопок справа.
     *
     * @param string $actionsHtml готовый HTML кнопок (Alpine-атрибуты работают, если вызов внутри x-data)
     */
    function admin_header(string $iconName, string $title, string $subtitle = '', string $actionsHtml = ''): void
    {
        echo '<div class="flex items-center justify-between gap-3 flex-wrap mb-6">';
        echo '<div class="flex items-center gap-3 min-w-0">';
        echo '<span class="w-11 h-11 rounded-2xl bg-amber-100 text-amber-600 inline-flex items-center justify-center shrink-0">'
           . icon($iconName, 'gc-icon-heading') . '</span>';
        echo '<div class="min-w-0"><h1 class="text-2xl sm:text-3xl font-extrabold text-gray-800 leading-tight">' . e($title) . '</h1>';
        if ($subtitle !== '') {
            echo '<p class="text-sm text-gray-400 mt-0.5">' . e($subtitle) . '</p>';
        }
        echo '</div></div>';
        if ($actionsHtml !== '') {
            echo '<div class="flex items-center gap-2 flex-wrap">' . $actionsHtml . '</div>';
        }
        echo '</div>';
    }
}

if (!function_exists('admin_top')) {
    /**
     * Вкладки + заголовок одним вызовом.
     */
    function admin_top(string $active, string $iconName, string $title, string $subtitle = '', string $actionsHtml = ''): void
    {
        admin_tabs($active);
        admin_header($iconName, $title, $subtitle, $actionsHtml);
    }
}

if (!function_exists('admin_flash')) {
    /**
     * Сообщения об успехе и ошибке (текст экранируется).
     */
    function admin_flash(string $success = '', string $error = ''): void
    {
        if ($success !== '') {
            echo '<div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl mb-4 flex items-center gap-2 text-sm font-semibold">'
               . icon('check', 'gc-icon-sm') . '<span>' . e($success) . '</span></div>';
        }
        if ($error !== '') {
            echo '<div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl mb-4 flex items-center gap-2 text-sm font-semibold">'
               . icon('warning', 'gc-icon-sm') . '<span>' . e($error) . '</span></div>';
        }
    }
}

if (!function_exists('admin_subtabs')) {
    /**
     * Вкладки внутри страницы (переключают Alpine-переменную $var, по умолчанию «tab»).
     * Тот же вид и та же горизонтальная прокрутка, что у основных вкладок.
     *
     * @param array<int,array{0:string,1:string,2:string,3?:int|string}> $tabs [ключ, иконка, название, счётчик]
     */
    function admin_subtabs(array $tabs, string $var = 'tab'): void
    {
        echo '<nav class="adm-tabs-wrap is-sub mb-5" data-adm-tabs>';
        echo '<button type="button" class="adm-tabs-arrow adm-tabs-prev bg-white text-gray-500 shadow-md" aria-label="Прокрутить влево">'
           . icon('left', 'gc-icon-sm') . '</button>';
        echo '<div class="adm-tabs">';
        foreach ($tabs as $t) {
            $k = e($t[0]);
            echo '<button type="button" class="adm-tab" @click="' . $var . " = '" . $k . "'\""
               . ' :class="' . $var . " === '" . $k . "' ? 'bg-amber-400 text-amber-900 shadow-sm' : 'bg-white text-gray-600 hover:bg-amber-50 hover:text-amber-700'\">"
               . icon($t[1], 'gc-icon-sm') . '<span>' . e($t[2]) . '</span>'
               . (isset($t[3]) ? '<span class="text-xs opacity-70">' . e((string)$t[3]) . '</span>' : '')
               . '</button>';
        }
        echo '</div>';
        echo '<button type="button" class="adm-tabs-arrow adm-tabs-next bg-white text-gray-500 shadow-md" aria-label="Прокрутить вправо">'
           . icon('right', 'gc-icon-sm') . '</button>';
        echo '</nav>';
        echo '<script>window.admTabsInit && window.admTabsInit();</script>';
    }
}
