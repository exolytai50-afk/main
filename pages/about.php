<?php
/**
 * pages/about.php — «О нас» (интерактивный лендинг GigaChef)
 * Самодостаточная страница: свои стили (префикс ab-), свой JS, без зависимостей кроме icon(), url(), e().
 */
require_once __DIR__ . '/../config/config.php';

$meta = function_exists('get_page_meta') ? get_page_meta('/about') : null;
if ($meta && function_exists('apply_meta')) {
    apply_meta($meta);
}
$pageTitle       = $GLOBALS['pageTitle']       ?? 'О нас — GigaChef | кулинарная соцсеть с ИИ';
$pageH1          = $GLOBALS['pageH1']          ?? 'О GigaChef';
$pageDescription = $GLOBALS['pageDescription'] ?? 'GigaChef — кулинарная соцсеть: рецепты, ИИ-помощники, диетолог, челленджи, сообщества и многое другое в одном месте.';
$GLOBALS['pageTitle']       = $pageTitle;
$GLOBALS['pageH1']          = $pageH1;
$GLOBALS['pageDescription'] = $pageDescription;

$isGuest = !function_exists('is_logged_in') || !is_logged_in();

/** Стоковое фото (Unsplash). Позже замените на свои файлы. */
$img = fn(string $id): string => "https://images.unsplash.com/{$id}?auto=format&fit=crop&w=1100&q=70";

$P = [
    'platter' => 'photo-1504674900247-0877df9cc836',
    'cooking' => 'photo-1556910103-1c02745aae4d',
    'food'    => 'photo-1498837167922-ddd27525d352',
    'salad'   => 'photo-1512621776951-a57141f2eefd',
    'kitchen' => 'photo-1495521821757-a1efb6729352',
    'chef'    => 'photo-1466637574441-749b8f19452f',
    'colors'  => 'photo-1490645935967-10de6ba17061',
    'table'   => 'photo-1476224203421-9ac39bcb3327',
    'restaur' => 'photo-1414235077428-338989a2e8c0',
    'pizza'   => 'photo-1565299624946-b28f40a0ae38',
    'spread'  => 'photo-1540189549336-e6e99c3679fe',
    'soup'    => 'photo-1547592180-85f173990554',
    'grill'   => 'photo-1555939594-58d7cb561ad1',
    'friends' => 'photo-1528605248644-14dd04022da1',
    'party'   => 'photo-1511690743698-d9d85f2fbf38',
    'cake'    => 'photo-1486427944299-d1955d23e34d',
    'steak'   => 'photo-1544025162-d76694265947',
    'beer'    => 'photo-1535958636474-b021ee887b13',
    'home'    => 'photo-1556909114-f6e7ad7d3136',
    'bake'    => 'photo-1506368249639-73a05d6f6488',
    'brunch'  => 'photo-1493770348161-369560ae357d',
    'dining'  => 'photo-1517248135467-4c7edcad34c4',
];

/** Блоки лендинга. slides: [фото, подпись, иконка] */
$blocks = [
  [
    'id' => 'feed', 'icon' => 'feed', 'kicker' => 'Лента и общение',
    'title' => 'Кулинарная соцсеть, а не просто каталог',
    'text'  => 'Здесь вы видите, что готовят друзья, делитесь историями о своих блюдах и переписываетесь в личных и групповых чатах. Лента показывает рецепты, достижения и участие в челленджах, а фильтры помогают оставить только друзей, только ИИ или только соревнования.',
    'points' => [['stories', 'Истории живут 7 дней: фото до 5 МБ или видео до 50 МБ'], ['chats', 'Личные и групповые чаты со стикерами, цитатами и лайками'], ['friends', 'Заявки в друзья, чёрный список и поиск людей по городу']],
    'href' => 'feed', 'cta' => 'Открыть ленту',
    'slides' => [[$P['friends'], 'Лента друзей', 'feed'], [$P['party'], 'Истории дня', 'stories'], [$P['dining'], 'Чаты и группы', 'chats']],
  ],
  [
    'id' => 'recipes', 'icon' => 'openbook', 'kicker' => 'Рецепты',
    'title' => 'Рецепт, который хочется приготовить прямо сейчас',
    'text'  => 'У каждого рецепта есть ингредиенты, шаги с таймерами, видео, КБЖУ и отзывы. Нужно найти что-то конкретное? Ищите по названию или продукту, а фильтры по кухне, диете, сезону, поводу и времени сузят выбор за секунды. Понравилось — сохраните в избранное или соберите список покупок.',
    'points' => [['search', 'Поиск с автоподсказкой и дополнительными фильтрами'], ['time', 'Шаги приготовления со встроенными таймерами'], ['video', 'Видеорецепты с Rutube, YouTube и Vimeo']],
    'href' => 'search', 'cta' => 'Найти рецепт',
    'slides' => [[$P['platter'], 'Подробные рецепты', 'openbook'], [$P['soup'], 'Шаги с таймерами', 'time'], [$P['salad'], 'Теги и категории', 'tags'], [$P['spread'], 'Видеоуроки', 'video']],
  ],
  [
    'id' => 'ai', 'icon' => 'ai', 'kicker' => 'ИИ на вашей кухне',
    'title' => 'Нейросети, которые действительно помогают готовить',
    'text'  => 'Опишите блюдо, и ИИ предложит четыре варианта рецепта с расчётом КБЖУ. Сфотографируйте холодильник, и он подберёт блюда из того, что есть. Спросите совета у кулинарного помощника, смешайте две кухни мира в фьюжн-блюдо или подберите пиво к стейку. Всё это в одном аккаунте.',
    'points' => [['aigenerator', 'Генератор рецептов: 4 варианта с КБЖУ за один запрос'], ['whattocook', '«Что приготовить?» — по фото холодильника'], ['fusion', 'Фьюжн-кухня и вариатор: веган, кето, быстро, празднично']],
    'href' => 'ai-generator', 'cta' => 'Попробовать ИИ',
    'slides' => [[$P['cooking'], 'Генератор рецептов', 'aigenerator'], [$P['kitchen'], 'Что приготовить?', 'whattocook'], [$P['colors'], 'Фьюжн-кухня', 'fusion'], [$P['chef'], 'Кулинарный советник', 'advisor']],
  ],
  [
    'id' => 'scan', 'icon' => 'scaner', 'kicker' => 'Сканер и бирпейринг',
    'title' => 'Сфотографировали — и всё понятно',
    'text'  => 'Сканер калорий распознаёт блюдо по фото или описанию и оценивает калории, белки, жиры и углеводы, а ещё может превратить увиденное в полноценный рецепт. Бирпейринг подскажет, какое пиво подчеркнёт вкус блюда, и наоборот. С альтернативами, реальными брендами и советами по подаче.',
    'points' => [['camera', 'Фото или текст: JPEG, PNG, WebP до 5 МБ'], ['beer', 'Режимы «К блюду» и «К пиву»'], ['chart', 'Уровень точности для каждого результата']],
    'href' => 'calorie-scanner', 'cta' => 'Сканировать блюдо',
    'slides' => [[$P['steak'], 'Сканер калорий', 'scaner'], [$P['beer'], 'Бирпейринг', 'beer'], [$P['pizza'], 'КБЖУ по фото', 'chart']],
  ],
  [
    'id' => 'books', 'icon' => 'cookbook', 'kicker' => 'Кулинарные книги',
    'title' => 'Личная библиотека, которую пишет ИИ вместе с вами',
    'text'  => 'Задайте тему, например «быстрые ужины за 30 минут», и получите готовую книгу на 3, 5 или 10 рецептов. Дополняйте её вручную, проверяйте на дубликаты, а любой рецепт превращайте в полноценную публикацию на сайте. Книги можно оставить приватными или открыть для всех.',
    'points' => [['sparkle', 'Генерация по теме, кухне и тегам'], ['copy', 'Поиск похожих рецептов с процентом совпадения'], ['share', 'Публичные и приватные книги, поделиться в один клик']],
    'href' => 'my-cookbooks', 'cta' => 'Мои книги',
    'slides' => [[$P['bake'], 'AI-книга', 'cookbook'], [$P['brunch'], 'Семейные рецепты', 'heart'], [$P['table'], 'Публикация в один клик', 'share']],
  ],
  [
    'id' => 'diet', 'icon' => 'diet', 'kicker' => 'Диетолог',
    'title' => 'Персональный план питания без скучных таблиц',
    'text'  => 'Короткая анкета, и AI-диетолог считает вашу норму КБЖУ, ИМТ и воды, составляет план по приёмам пищи и ведёт дневник. Графики веса, диаграмма БЖУ, шагомер на телефоне и распознавание продуктов с фото чека показывают прогресс наглядно. Рекомендации носят справочный характер и не заменяют консультацию врача.',
    'points' => [['water', 'Контроль воды и серии дней подряд'], ['steps', 'Шагомер с целью 10 000 шагов'], ['receipt', 'Сканер чеков: продукты сразу в дневник']],
    'href' => 'dietolog', 'cta' => 'Рассчитать план',
    'slides' => [[$P['salad'], 'План питания', 'diet'], [$P['colors'], 'Дневник и КБЖУ', 'chart'], [$P['home'], 'Здоровые привычки', 'muscle']],
  ],
  [
    'id' => 'quit', 'icon' => 'smoking', 'kicker' => 'Бросаем курить',
    'title' => 'Бесплатный помощник на пути к жизни без никотина',
    'text'  => 'Девять методик на выбор, от резкого отказа до авторского «Метода Переключателя», оформлены как пошаговые курсы. Есть дневник состояния, SOS-кнопка с дыхательной практикой на момент тяги, счётчик сэкономленных денег и календарь восстановления организма. Без осуждения, без обещаний чудес и с опорой на клинические исследования. Раздел не заменяет врача.',
    'points' => [['switch', '9 методов и персональная анкета из 4 шагов'], ['lungs', 'Календарь восстановления: от часа до 10 лет'], ['pulse', 'SOS-кнопка с дыханием 4-4-6-2']],
    'href' => 'quit-smoking', 'cta' => 'Начать бесплатно',
    'slides' => [[$P['home'], 'Один шаг', 'quit'], [$P['kitchen'], 'Дневник состояния', 'pen'], [$P['dining'], 'Достижения за 1–365 дней', 'medal']],
  ],
  [
    'id' => 'play', 'icon' => 'challenges', 'kicker' => 'Азарт и награды',
    'title' => 'Челленджи, очки, уровни и достижения',
    'text'  => 'Участвуйте в кулинарных соревнованиях: загрузите фото блюда, соберите голоса сообщества и получите очки. Каждое действие, будь то рецепт, отзыв, ИИ-генерация или книга, копится в уровне и открывает награды. Таблица лидеров покажет, как вы смотритесь на фоне остальных кулинаров.',
    'points' => [['leader', 'Топ-50 лидеров и подробная история очков'], ['achievements', 'Награды навсегда — их нельзя потерять'], ['games', 'Бесплатные игры про еду, 24 категории']],
    'href' => 'challenges', 'cta' => 'Выбрать челлендж',
    'slides' => [[$P['grill'], 'Челленджи', 'challenges'], [$P['cake'], 'Достижения', 'achievements'], [$P['party'], 'Таблица лидеров', 'leader'], [$P['pizza'], 'Игры про еду', 'games']],
  ],
  [
    'id' => 'community', 'icon' => 'team', 'kicker' => 'Сообщества и новости',
    'title' => 'Найдите своих: выпечка, ПП, вегетарианство и не только',
    'text'  => 'Создайте тематическое сообщество или вступите в существующее: публикуйте посты, обменивайтесь рецептами, голосуйте и обсуждайте. А раздел новостей расскажет, что нового на платформе, с рейтингом и комментариями под каждой публикацией.',
    'points' => [['communities', 'Свои обложки, категории и приглашения'], ['news', 'Новости с закреплением, рейтингом и комментариями'], ['share', 'Поделиться любой страницей через системное меню']],
    'href' => 'communities', 'cta' => 'К сообществам',
    'slides' => [[$P['friends'], 'Тематические клубы', 'team'], [$P['table'], 'Общие столы', 'communities'], [$P['spread'], 'Новости платформы', 'news']],
  ],
  [
    'id' => 'pro', 'icon' => 'crown', 'kicker' => 'GigaChef Pro',
    'title' => 'Больше возможностей, когда они нужны',
    'text'  => 'Подписка Pro открывает безлимитную генерацию рецептов, ИИ-фото блюд, сканер калорий и персональную AI-книгу. Выбирайте месяц или год (в нём выгода 17%), применяйте промокод, управляйте автопродлением и скачивайте квитанции. Возврат возможен в течение 14 дней, а после отмены доступ сохраняется до конца оплаченного периода.',
    'points' => [['payment', 'Безопасная оплата через ЮKassa'], ['invoice', 'История платежей и электронные квитанции'], ['refresh', 'Автопродление отключается в любой момент']],
    'href' => 'payment', 'cta' => 'Посмотреть тарифы',
    'slides' => [[$P['chef'], 'Pro-инструменты', 'crown'], [$P['platter'], 'Безлимитный ИИ', 'lightning'], [$P['bake'], 'Прозрачные платежи', 'receipt']],
  ],
];

$faq = [
  ['Сколько стоит пользоваться GigaChef?', 'Регистрация и основные функции бесплатны. Часть ИИ-инструментов имеет дневные лимиты, а подписка Pro снимает ограничения.'],
  ['ИИ-рецепты и советы безопасны?', 'Это справочная информация, а не медицинская консультация. Если у вас диабет, аллергии или особая диета, проверяйте рекомендации у врача.'],
  ['Мои данные в безопасности?', 'Мы не передаём данные третьим лицам и не используем их для рекламы. Согласие на cookie запрашивается раз в полгода, и вы можете управлять им в браузере.'],
  ['Как быстро получить очки и уровень?', 'Участвуйте в челленджах, публикуйте рецепты, оставляйте отзывы и ведите дневник питания. Регулярность ценится больше разовых всплесков.'],
  ['Можно ли пользоваться с телефона?', 'Да, сайт адаптивный. Фото можно снять камерой прямо из браузера, а шагомер использует датчики смартфона.'],
];
$stats = [['4', 'варианта рецепта за один запрос'], ['9', 'методик отказа от курения'], ['7', 'дней живёт история'], ['24', 'категории игр']];

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.ab{--ink:rgb(55, 65, 81);;--paper:#fafaf9;--line:rgba(0,0,0,.12);--acc:#f59e0b;color:var(--ink);overflow-x:clip}
.ab *{box-sizing:border-box}
.ab h1,.ab h2,.ab h3,.ab p{margin:0}
.ab-wrap{max-width:1180px;margin:0 auto;padding:0 20px}
.ab-ic{display:inline-flex;color:inherit}
.ab-ic .gc-icon{color:inherit}

/* reveal */
.ab-rv{opacity:0;transform:translateY(28px);transition:opacity .8s cubic-bezier(.2,.7,.2,1),transform .8s cubic-bezier(.2,.7,.2,1)}
.ab-rv.in{opacity:1;transform:none}
@media (prefers-reduced-motion:reduce){.ab-rv{opacity:1;transform:none;transition:none}.ab *{animation:none!important}}

/* HERO */
.ab-hero{position:relative;background:rgb(55, 65, 81);;color:#fff;padding:110px 0 70px;overflow:hidden;isolation:isolate}
.ab-hero:before,.ab-hero:after{content:"";position:absolute;border-radius:50%;filter:blur(90px);z-index:-1;opacity:.5}
.ab-hero:before{width:520px;height:520px;background:#f59e0b;top:-200px;right:-120px;animation:abf 14s ease-in-out infinite alternate}
.ab-hero:after{width:420px;height:420px;background:#fff;opacity:.12;bottom:-180px;left:-120px;animation:abf 18s ease-in-out infinite alternate-reverse}
@keyframes abf{to{transform:translate(-60px,50px) scale(1.15)}}
.ab-badge{display:inline-flex;align-items:center;gap:8px;border:1px solid rgba(255,255,255,.25);border-radius:99px;padding:7px 16px;font-size:13px;font-weight:600;letter-spacing:.04em;backdrop-filter:blur(6px)}
.ab-hero h1{font-size:clamp(40px,8vw,96px);line-height:1;font-weight:900;letter-spacing:-.03em;margin:26px 0 22px;max-width:14ch}
.ab-hero h1 em{font-style:normal;background:linear-gradient(90deg,#fff,#f59e0b);-webkit-background-clip:text;background-clip:text;color:transparent}
.ab-hero p.lead{max-width:56ch;font-size:clamp(16px,2vw,20px);line-height:1.6;color:rgba(255,255,255,.75)}
.ab-btns{display:flex;flex-wrap:wrap;gap:12px;margin-top:34px}
.ab-btn{display:inline-flex;align-items:center;gap:8px;padding:14px 26px;border-radius:99px;font-weight:700;font-size:15px;text-decoration:none;transition:transform .25s,box-shadow .25s,background .25s}
.ab-btn:hover{transform:translateY(-3px)}
.ab-btn.w{background:#fff;color:rgb(55, 65, 81);}.ab-btn.w:hover{box-shadow:0 12px 30px rgba(255,255,255,.25)}
.ab-btn.g{border:1px solid rgba(255,255,255,.35);color:#fff}.ab-btn.g:hover{background:rgba(255,255,255,.1)}
.ab-btn.k{background:rgb(55, 65, 81);;color:#fff}.ab-btn.k:hover{box-shadow:0 12px 30px rgba(0,0,0,.3)}
.ab-hero-grid{display:grid;grid-template-columns:1.2fr .8fr;gap:40px;align-items:center}
.ab-hero-car{display:block}
@media(max-width:900px){.ab-hero{padding:80px 0 50px}.ab-hero-grid{grid-template-columns:1fr}}

/* hero: фото на фоне */
.ab-hero{padding:120px 0 150px}
.ab-hero:before,.ab-hero:after{display:none!important}
.ab-hero .ab-car{position:absolute;inset:0;aspect-ratio:auto;border-radius:0;box-shadow:none;transform:none!important;z-index:0;background:rgb(55, 65, 81);}
.ab-hero .ab-sl img{filter:blur(4px) brightness(.55) grayscale(.1);transform:scale(1.15)}
.ab-hero .ab-sl.on img{transform:scale(1.05)}
.ab-hero .ab-sl:after{background:linear-gradient(to top,rgba(0,0,0,.75),rgba(0,0,0,.35) 55%,rgba(0,0,0,.55))}
.ab-hero .ab-cap{left:max(20px,calc((50vw - 1180px)/2 + 20px));bottom:34px;font-size:17px}
.ab-hero .ab-nav{justify-content:flex-end;padding:0 max(20px,calc((50vw - 1180px)/2 + 20px)) 10px}
.ab-hero .ab-dots{flex:0 1 190px;margin:0}
.ab-hero-in{position:relative;z-index:1}
.ab-hero h1{max-width:16ch}
.ab-hero h1 em{background:none;-webkit-background-clip:initial;background-clip:initial;color:#fff;font-style:normal;text-decoration:underline;text-decoration-color:#f59e0b;text-decoration-thickness:.07em;text-underline-offset:.12em}
@media(max-width:700px){
  .ab-hero{padding:44px 0 176px}
  .ab-hero h1{font-size:clamp(34px,10.5vw,46px);margin:18px 0 14px}
  .ab-hero p.lead{font-size:15px;line-height:1.55}
  .ab-hero .ab-btns{margin-top:22px;gap:10px;flex-wrap:nowrap}
  .ab-hero .ab-btn{flex:1 1 0;justify-content:center;padding:12px 10px;font-size:13px;white-space:nowrap}
  .ab-hero .ab-cap{left:20px;right:20px;bottom:62px;font-size:15px;gap:10px}
  .ab-hero .ab-cap i{width:38px;height:38px}
  .ab-hero .ab-nav{padding:0 20px 26px;justify-content:flex-start}
  .ab-hero .ab-dots{flex:1;max-width:none}
}
}

/* слайдер возможностей */
.ab-fs{background:#fff;border-block:1px solid var(--line)}
.ab-fs-box{position:relative;height:98px;overflow:hidden}
.ab-fi{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;gap:16px;padding:0 8px;opacity:0;transform:translateY(22px);transition:opacity .6s,transform .6s;pointer-events:none}
.ab-fi.on{opacity:1;transform:none}
.ab-fi.out{transform:translateY(-22px)}
.ab-fi i{flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;width:54px;height:54px;border-radius:50%;background:#0e0e0e2e;color:#fff}
.ab-fi i .gc-icon{width:1.6em;height:1.6em}
.ab-fi b{display:block;font-size:20px;font-weight:800;color:rgb(55, 65, 81);;line-height:1.2}
.ab-fi span{display:block;margin-top:3px;font-size:13px;color:#555;line-height:1.35}
@media(max-width:480px){.ab-fi{gap:12px}.ab-fi i{width:46px;height:46px}.ab-fi b{font-size:17px}.ab-fi span{font-size:12px}}

/* stats */
.ab-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:0;background:#fff;border-bottom:1px solid var(--line)}
.ab-st{padding:38px 20px;text-align:center;border-right:1px solid var(--line)}
.ab-st:last-child{border:0}
.ab-st b{display:block;font-size:clamp(38px,6vw,64px);font-weight:900;line-height:1;letter-spacing:-.03em}
.ab-st span{display:block;margin-top:8px;font-size:13px;color:#555}
@media(max-width:700px){.ab-stats{grid-template-columns:1fr 1fr}.ab-st:nth-child(2){border-right:0}.ab-st:nth-child(-n+2){border-bottom:1px solid var(--line)}}

/* intro */
.ab-intro{background:var(--paper);padding:90px 0 40px;text-align:center}
.ab-intro h2{font-size:clamp(28px,4.6vw,52px);font-weight:900;letter-spacing:-.02em;line-height:1.1;max-width:20ch;margin:0 auto 18px}
.ab-intro p{max-width:62ch;margin:0 auto;font-size:18px;line-height:1.7;color:#444}

/* BLOCK */
.ab-blk{padding:90px 0;position:relative}
.ab-blk.l{background:var(--paper);color:rgb(55, 65, 81);}
.ab-blk.d{background:rgb(55, 65, 81);;color:#fff}
.ab-row{display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:center}
.ab-blk.r .ab-row>.ab-tx{order:2}
@media(max-width:900px){.ab-row{grid-template-columns:1fr;gap:34px}.ab-blk.r .ab-row>.ab-tx{order:0}.ab-blk{padding:64px 0}}
.ab-kick{display:inline-flex;align-items:center;gap:10px;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.12em;opacity:.7}
.ab-kick .gc-icon{width:1.7em;height:1.7em}
.ab-blk h2{font-size:clamp(28px,4vw,46px);font-weight:900;line-height:1.08;letter-spacing:-.02em;margin:16px 0 18px}
.ab-blk .ab-p{font-size:17px;line-height:1.75;opacity:.8}
.ab-pts{list-style:none;margin:28px 0 30px;padding:0;display:grid;gap:12px}
.ab-pts li{display:flex;align-items:center;gap:14px;padding:12px 16px;border:1px solid currentColor;border-color:rgba(127,127,127,.3);border-radius:16px;font-size:15px;font-weight:500;transition:transform .3s,background .3s,border-color .3s}
.ab-pts li:hover{transform:translateX(8px);border-color:currentColor}
.ab-pts .gc-icon{width:1.7em;height:1.7em;flex-shrink:0}
.ab-blk.l .ab-btn{background:rgb(55, 65, 81);;color:#fff}
.ab-blk.d .ab-btn{background:#fff;color:rgb(55, 65, 81);}
.ab-num{position:absolute;top:30px;right:20px;font-size:clamp(70px,14vw,180px);font-weight:900;line-height:1;opacity:.05;pointer-events:none;letter-spacing:-.05em}

/* CAROUSEL */
.ab-car{position:relative;border-radius:28px;overflow:hidden;aspect-ratio:4/4.6;background:#222;box-shadow:0 30px 70px -20px rgba(0,0,0,.45);transform:perspective(1200px) rotateY(var(--ry,0deg)) rotateX(var(--rx,0deg));transition:transform .25s ease-out;touch-action:pan-y}
.ab-trk{display:flex;height:100%;transition:transform .8s cubic-bezier(.65,0,.2,1)}
.ab-sl{flex:0 0 100%;position:relative;height:100%;background:linear-gradient(135deg,#333,#111)}
.ab-sl img{width:100%;height:100%;object-fit:cover;display:block;filter:grayscale(.15) contrast(1.05);transform:scale(1.12);transition:transform 6s ease-out}
.ab-sl.on img{transform:scale(1)}
.ab-sl:after{content:"";position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.75),transparent 55%)}
.ab-cap{position:absolute;left:18px;bottom:54px;z-index:2;display:flex;align-items:center;gap:12px;color:#fff;font-weight:800;font-size:18px;opacity:0;transform:translateY(14px);transition:.6s .3s}
.ab-sl.on .ab-cap{opacity:1;transform:none}
.ab-cap i{display:inline-flex;width:46px;height:46px;border-radius:50%;background:#fff;color:rgb(55, 65, 81);;align-items:center;justify-content:center}
.ab-cap i .gc-icon{width:1.4em;height:1.4em}
.ab-nav{position:absolute;inset:auto 0 0 0;z-index:3;display:flex;align-items:center;justify-content:space-between;padding:12px 16px}
.ab-dots{display:flex;gap:6px;flex:1;margin-right:14px}
.ab-dot{height:4px;flex:1;max-width:46px;border-radius:4px;background:rgba(255,255,255,.35);border:0;padding:0;cursor:pointer;position:relative;overflow:hidden}
.ab-dot:after{content:"";position:absolute;inset:0;background:#fff;transform:scaleX(0);transform-origin:left}
.ab-dot.on:after{animation:abp var(--t,5s) linear forwards}
.ab-dot.done:after{transform:scaleX(1)}
.ab-car.paused .ab-dot.on:after{animation-play-state:paused}
@keyframes abp{to{transform:scaleX(1)}}
.ab-ar{display:flex;gap:8px}
.ab-ar button{width:38px;height:38px;border-radius:50%;border:1px solid rgba(255,255,255,.5);background:rgba(0,0,0,.35);color:#fff;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;backdrop-filter:blur(6px);transition:.25s}
.ab-ar button:hover{background:#fff;color:rgb(55, 65, 81);}

/* FAQ */
.ab-faq{background:#fff;padding:90px 0}
.ab-faq h2{font-size:clamp(28px,4vw,44px);font-weight:900;text-align:center;margin-bottom:40px;letter-spacing:-.02em}
.ab-q{max-width:780px;margin:0 auto;border-bottom:1px solid var(--line)}
.ab-q button{all:unset;box-sizing:border-box;width:100%;display:flex;justify-content:space-between;align-items:center;gap:16px;padding:22px 4px;cursor:pointer;font-weight:700;font-size:18px}
.ab-q button .gc-icon{transition:transform .35s}
.ab-q.open button .gc-icon{transform:rotate(45deg)}
.ab-a{display:grid;grid-template-rows:0fr;transition:grid-template-rows .4s}
.ab-q.open .ab-a{grid-template-rows:1fr}
.ab-a div{overflow:hidden}
.ab-a p{padding:0 4px 22px;color:#555;line-height:1.7}

/* CTA */
.ab-cta{background:rgb(55, 65, 81);;color:#fff;text-align:center;padding:110px 0;position:relative;overflow:hidden}
.ab-cta:before{content:"";position:absolute;width:600px;height:600px;border-radius:50%;background:#f59e0b;filter:blur(120px);opacity:.3;left:50%;top:50%;transform:translate(-50%,-50%)}
.ab-cta .ab-wrap{position:relative}
.ab-cta h2{font-size:clamp(32px,6vw,68px);font-weight:900;letter-spacing:-.03em;line-height:1.05;max-width:16ch;margin:0 auto 24px}
.ab-cta p{max-width:50ch;margin:0 auto;color:rgba(255,255,255,.7);font-size:18px;line-height:1.6}
.ab-cta .ab-btns{justify-content:center}
.ab-foot{display:flex;flex-wrap:wrap;gap:8px 22px;justify-content:center;margin-top:44px;font-size:13px}
.ab-foot a{color:rgba(255,255,255,.55);text-decoration:none}.ab-foot a:hover{color:#fff}
/* hero-фон: растянуть на всю шапку (после общих стилей карусели) */
.ab .ab-hero>.ab-car{position:absolute;top:0;right:0;bottom:0;left:0;width:100%;height:100%;aspect-ratio:auto!important}
.ab .ab-hero>.ab-car .ab-trk,.ab .ab-hero>.ab-car .ab-sl{height:100%}
</style>

<main class="ab">

<!-- HERO -->
<section class="ab-hero">
  <div class="ab-car" data-car data-t="5000" aria-hidden="true">
    <div class="ab-trk">
      <?php foreach ([[$P['platter'], 'Рецепты', 'openbook'], [$P['cooking'], 'ИИ-помощники', 'ai'], [$P['friends'], 'Сообщество', 'friends'], [$P['grill'], 'Челленджи', 'challenges']] as $s): ?>
        <div class="ab-sl"><img src="<?= e($img($s[0])) ?>" alt="" loading="eager" onerror="this.style.display='none'"><div class="ab-cap"><i><?= icon($s[2], 'gc-icon-md') ?></i><?= e($s[1]) ?></div></div>
      <?php endforeach; ?>
    </div>
    <div class="ab-nav"><div class="ab-dots"></div></div>
  </div>
  <div class="ab-wrap ab-hero-in">
    <span class="ab-badge ab-rv"><span class="ab-ic"><?= icon('chef', 'gc-icon-sm') ?></span> Кулинарная соцсеть нового поколения</span>
    <h1 class="ab-rv">Готовьте, делитесь и <em>вдохновляйтесь</em></h1>
    <p class="lead ab-rv">GigaChef объединяет рецепты, ИИ-помощников, диетолога, соревнования и живое сообщество. Всё, что нужно, чтобы готовить интереснее и питаться осознаннее.</p>
    <div class="ab-btns ab-rv">
      <a class="ab-btn w" href="<?= $isGuest ? url('register') : url('') ?>"><?= icon('rocket', 'gc-icon-md') ?> <?= $isGuest ? 'Присоединиться' : 'Перейти в ленту' ?></a>
      <a class="ab-btn g" href="#ab-feed"><?= icon('chevron_down', 'gc-icon-md') ?> Узнать больше</a>
    </div>
  </div>
</section>

<!-- Слайдер возможностей (без навигации) -->
<section class="ab-fs" aria-label="Возможности GigaChef">
  <div class="ab-wrap">
    <div class="ab-fs-box" data-fs>
      <?php foreach ([
        ['openbook','Рецепты','Подробные шаги, таймеры и видео'],
        ['aigenerator','ИИ-генератор','Четыре варианта рецепта с КБЖУ'],
        ['whattocook','Что приготовить?','Блюда из того, что в холодильнике'],
        ['fusion','Фьюжн-кухня','Смешайте две кухни мира'],
        ['scaner','Сканер калорий','КБЖУ по фото блюда'],
        ['beer','Бирпейринг','Пиво к блюду и блюдо к пиву'],
        ['diet','Диетолог','Персональный план питания'],
        ['challenges','Челленджи','Соревнуйтесь и получайте очки'],
        ['team','Сообщества','Клубы по интересам'],
        ['cookbook','Кулинарные книги','Сборники, созданные ИИ'],
        ['stories','Истории','Фото и видео на 7 дней'],
        ['games','Игры','Бесплатные игры про еду'],
        ['smoking','Бросаем курить','Девять методик и поддержка'],
      ] as $m): ?>
        <div class="ab-fi"><i><?= icon($m[0], 'gc-icon-lg') ?></i><div><b><?= e($m[1]) ?></b><span><?= e($m[2]) ?></span></div></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Цифры -->
<div class="ab-stats">
  <?php foreach ($stats as $s): ?>
    <div class="ab-st ab-rv"><b data-count="<?= (int)$s[0] ?>">0</b><span><?= e($s[1]) ?></span></div>
  <?php endforeach; ?>
</div>

<!-- Вступление -->
<section class="ab-intro">
  <div class="ab-wrap">
    <h2 class="ab-rv">Одна платформа вместо десятка вкладок</h2>
    <p class="ab-rv">Мы собрали в GigaChef то, что обычно разбросано по разным сайтам: рецепты и видео, подсчёт калорий, общение с единомышленниками и умные подсказки. Листайте блоки ниже: у каждого есть своя галерея.</p>
  </div>
</section>

<!-- Блоки -->
<?php foreach ($blocks as $i => $b):
    $theme = $i % 2 === 0 ? 'l' : 'd';
    $rev   = $i % 2 === 1 ? ' r' : '';
    $anchor = $i === 0 ? 'ab-feed' : 'ab-' . $b['id'];
?>
<section class="ab-blk <?= $theme . $rev ?>" id="<?= e($anchor) ?>">
  <span class="ab-num"><?= sprintf('%02d', $i + 1) ?></span>
  <div class="ab-wrap ab-row">
    <div class="ab-tx ab-rv">
      <span class="ab-kick"><?= icon($b['icon'], 'gc-icon-md') ?> <?= e($b['kicker']) ?></span>
      <h2><?= e($b['title']) ?></h2>
      <p class="ab-p"><?= e($b['text']) ?></p>
      <ul class="ab-pts">
        <?php foreach ($b['points'] as $p): ?>
          <li><?= icon($p[0], 'gc-icon-md') ?><span><?= e($p[1]) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <a class="ab-btn" href="<?= url($b['href']) ?>"><?= e($b['cta']) ?> <?= icon('right', 'gc-icon-sm') ?></a>
    </div>
    <div class="ab-rv">
      <div class="ab-car" data-car data-t="<?= 4200 + $i * 250 ?>">
        <div class="ab-trk">
          <?php foreach ($b['slides'] as $s): ?>
            <div class="ab-sl"><img src="<?= e($img($s[0])) ?>" alt="<?= e($s[1]) ?>" loading="lazy" onerror="this.style.display='none'"><div class="ab-cap"><i><?= icon($s[2], 'gc-icon-md') ?></i><?= e($s[1]) ?></div></div>
          <?php endforeach; ?>
        </div>
        <div class="ab-nav"><div class="ab-dots"></div><div class="ab-ar"><button type="button" data-prev aria-label="Назад"><?= icon('left', 'gc-icon-sm') ?></button><button type="button" data-next aria-label="Вперёд"><?= icon('right', 'gc-icon-sm') ?></button></div></div>
      </div>
    </div>
  </div>
</section>
<?php endforeach; ?>

<!-- Безопасность -->
<section class="ab-blk l" id="ab-trust" style="padding-top:20px">
  <div class="ab-wrap">
    <div class="ab-tx ab-rv" style="max-width:760px;margin:0 auto;text-align:center">
      <span class="ab-kick"><?= icon('shield', 'gc-icon-md') ?> Прозрачность</span>
      <h2>Честно о данных, правах и ИИ</h2>
      <p class="ab-p">Рекомендации нейросетей носят информационный характер и не заменяют врача. Ваши данные не передаются третьим лицам, а правила платформы, политика cookie и юридическая информация всегда под рукой. Во время плановых работ мы показываем понятную страницу с таймером, чтобы вы знали, когда вернёмся.</p>
      <div class="ab-btns" style="justify-content:center">
        <a class="ab-btn k" href="<?= url('terms') ?>"><?= icon('document', 'gc-icon-sm') ?> Соглашение</a>
        <a class="ab-btn k" href="<?= url('privacy') ?>"><?= icon('cookie', 'gc-icon-sm') ?> Конфиденциальность</a>
        <a class="ab-btn k" href="<?= url('legal') ?>"><?= icon('legal', 'gc-icon-sm') ?> Право</a>
      </div>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="ab-faq">
  <div class="ab-wrap">
    <h2 class="ab-rv">Частые вопросы</h2>
    <?php foreach ($faq as $f): ?>
      <div class="ab-q ab-rv">
        <button type="button" aria-expanded="false"><span><?= e($f[0]) ?></span><?= icon('plus', 'gc-icon-md') ?></button>
        <div class="ab-a"><div><p><?= e($f[1]) ?></p></div></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- CTA -->
<section class="ab-cta">
  <div class="ab-wrap">
    <h2 class="ab-rv">Ваш следующий шедевр начинается здесь</h2>
    <p class="ab-rv">Присоединяйтесь к GigaChef: готовьте, пробуйте новое и делитесь результатом с теми, кто это оценит.</p>
    <div class="ab-btns ab-rv">
      <a class="ab-btn w" href="<?= $isGuest ? url('register') : url('create-recipe') ?>"><?= icon($isGuest ? 'rocket' : 'pen', 'gc-icon-md') ?> <?= $isGuest ? 'Создать аккаунт' : 'Создать рецепт' ?></a>
      <a class="ab-btn g" href="<?= url('search') ?>"><?= icon('search', 'gc-icon-md') ?> Смотреть рецепты</a>
    </div>
    <div class="ab-foot">
      <a href="<?= url('terms') ?>">Соглашение</a><a href="<?= url('privacy') ?>">Конфиденциальность</a><a href="<?= url('legal') ?>">Право</a>
    </div>
  </div>
</section>

</main>

<script>
(function () {
  'use strict';

  /* Появление блоков при скролле */
  var io = 'IntersectionObserver' in window ? new IntersectionObserver(function (es) {
    es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
  }, { threshold: .12 }) : null;
  document.querySelectorAll('.ab-rv').forEach(function (el) { io ? io.observe(el) : el.classList.add('in'); });

  /* Счётчики */
  var cio = 'IntersectionObserver' in window ? new IntersectionObserver(function (es) {
    es.forEach(function (e) {
      if (!e.isIntersecting) return;
      var el = e.target, end = +el.dataset.count, t0 = null;
      (function step(t) {
        t0 = t0 || t;
        var p = Math.min((t - t0) / 1200, 1);
        el.textContent = Math.round(end * (1 - Math.pow(1 - p, 3)));
        if (p < 1) requestAnimationFrame(step);
      })(performance.now());
      cio.unobserve(el);
    });
  }, { threshold: .5 }) : null;
  document.querySelectorAll('[data-count]').forEach(function (el) { cio ? cio.observe(el) : (el.textContent = el.dataset.count); });

  /* Карусели */
  document.querySelectorAll('[data-car]').forEach(function (car) {
    var trk = car.querySelector('.ab-trk'),
        slides = car.querySelectorAll('.ab-sl'),
        dotsBox = car.querySelector('.ab-dots'),
        dur = +car.dataset.t || 5000,
        cur = 0, timer = null, visible = false, dots = [];
    car.style.setProperty('--t', dur + 'ms');

    slides.forEach(function (_, i) {
      var d = document.createElement('button');
      d.type = 'button'; d.className = 'ab-dot'; d.setAttribute('aria-label', 'Слайд ' + (i + 1));
      d.addEventListener('click', function () { go(i, true); });
      if (dotsBox) { dotsBox.appendChild(d); dots.push(d); }
    });

    function paint() {
      trk.style.transform = 'translateX(' + (-100 * cur) + '%)';
      slides.forEach(function (s, i) { s.classList.toggle('on', i === cur); });
      dots.forEach(function (d, i) {
        d.classList.remove('on', 'done');
        void d.offsetWidth;
        if (i < cur) d.classList.add('done');
        if (i === cur) d.classList.add('on');
      });
    }
    function arm() {
      clearTimeout(timer);
      if (!visible || car.classList.contains('paused')) return;
      timer = setTimeout(function () { go(cur + 1); }, dur);
    }
    function go(i, manual) {
      cur = (i + slides.length) % slides.length;
      paint(); arm();
    }

    var nx = car.querySelector('[data-next]'); if (nx) nx.addEventListener('click', function () { go(cur + 1, true); });
    var pv = car.querySelector('[data-prev]'); if (pv) pv.addEventListener('click', function () { go(cur - 1, true); });

    /* пауза при наведении */
    car.addEventListener('mouseenter', function () { car.classList.add('paused'); clearTimeout(timer); });
    car.addEventListener('mouseleave', function () {
      car.classList.remove('paused');
      car.style.setProperty('--rx', '0deg'); car.style.setProperty('--ry', '0deg');
      go(cur);
    });

    /* лёгкий 3D-наклон на десктопе */
    if (window.matchMedia('(hover:hover)').matches) {
      car.addEventListener('mousemove', function (e) {
        var r = car.getBoundingClientRect();
        car.style.setProperty('--ry', (((e.clientX - r.left) / r.width - .5) * 8).toFixed(2) + 'deg');
        car.style.setProperty('--rx', (-((e.clientY - r.top) / r.height - .5) * 8).toFixed(2) + 'deg');
      });
    }

    /* свайп */
    var sx = null;
    car.addEventListener('touchstart', function (e) { sx = e.touches[0].clientX; car.classList.add('paused'); clearTimeout(timer); }, { passive: true });
    car.addEventListener('touchend', function (e) {
      if (sx !== null) {
        var dx = e.changedTouches[0].clientX - sx;
        if (Math.abs(dx) > 40) go(cur + (dx < 0 ? 1 : -1), true);
      }
      sx = null; car.classList.remove('paused'); go(cur);
    });

    /* автоплей только в зоне видимости */
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (es) {
        visible = es[0].isIntersecting; arm();
        if (visible) paint();
      }, { threshold: .35 }).observe(car);
    } else { visible = true; }

    paint();
  });

  /* Слайдер возможностей: автопрокрутка без навигации */
  var fs = document.querySelector('[data-fs]');
  if (fs) {
    var items = fs.querySelectorAll('.ab-fi'), fi = 0;
    items[0].classList.add('on');
    setInterval(function () {
      if (document.hidden) return;
      var prev = items[fi];
      prev.classList.remove('on'); prev.classList.add('out');
      setTimeout(function () { prev.classList.remove('out'); }, 650);
      fi = (fi + 1) % items.length;
      items[fi].classList.add('on');
    }, 2600);
  }

  /* FAQ */
  document.querySelectorAll('.ab-q button').forEach(function (b) {
    b.addEventListener('click', function () {
      var q = b.parentNode, open = q.classList.toggle('open');
      b.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  });

  /* Плавный скролл к якорям */
  document.querySelectorAll('.ab a[href^="#"]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      var t = document.querySelector(a.getAttribute('href'));
      if (t) { e.preventDefault(); t.scrollIntoView({ behavior: 'smooth' }); }
    });
  });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
