<?php
/**
 * Страница технического обслуживания (GigaChef).
 * Показывается всем, кроме админа. Подключается из cookai_maintenance_gate().
 *
 * Встроенные офлайн-игры лежат в pages/maintenance_games/*.html (одиночные HTML-файлы
 * с русским интерфейсом и лицензиями открытого ПО). Страница подставляет их в iframe через srcdoc,
 * поэтому дополнительных запросов к серверу (и к шлюзу техработ) не требуется.
 */
declare(strict_types=1);
 
require_once __DIR__ . '/../config/config.php';
 
$settings = maintenance_get_settings();
$until    = $settings['maintenance_until'] ?: '';
$started  = $settings['maintenance_started_at'] ?: '';
$message  = $settings['maintenance_message'] ?: 'Мы обновляем кухню и скоро вернёмся с новыми возможностями!';
 
$now         = time();
$untilTs     = ($until !== '' && strtotime($until) !== false) ? (int)strtotime($until) : 0;
$startedTs   = ($started !== '' && strtotime($started) !== false) ? (int)strtotime($started) : 0;
$secondsLeft = $untilTs > 0 ? max(0, $untilTs - $now) : 0;
$totalSec    = ($untilTs > 0 && $startedTs > 0 && $untilTs > $startedTs) ? ($untilTs - $startedTs) : 0;
 
if (!headers_sent()) {
    http_response_code(503);
    header('Retry-After: ' . max(60, $secondsLeft ?: 3600));
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
}
 
/* Игра в iframe srcdoc берёт относительные пути от адреса страницы, а шлюз техработ отдаёт на них 503.
   Поэтому звуки/картинки из maintenance_games/ (например 'sounds/step.mp3') подставляем прямо в код игры как data: URI. */
function mg_inline_assets(string $html, string $dir): string
{
    $mime = ['mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav', 'm4a' => 'audio/mp4',
             'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
             'webp' => 'image/webp', 'svg' => 'image/svg+xml'];
    $base = realpath($dir);
    return preg_replace_callback(
        '~(["\'])((?:\./)?(?:sounds|img|images|assets)/[A-Za-z0-9_\-./]+\.(mp3|ogg|wav|m4a|png|jpe?g|gif|webp|svg))\1~i',
        function ($m) use ($dir, $base, $mime) {
            $path = realpath($dir . $m[2]);
            if ($path === false || $base === false || strpos($path, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
                return $m[0];
            }
            $ext = strtolower($m[3]);
            return $m[1] . 'data:' . $mime[$ext] . ';base64,' . base64_encode((string)file_get_contents($path)) . $m[1];
        },
        $html
    ) ?? $html;
}
 
/* ---------- Игры ---------- */
$gamesDir = __DIR__ . '/maintenance_games/';
$gamesMeta = [
    '2048' => [
        'title' => '2048', 'desc' => 'Соединяйте одинаковые плитки и соберите 2048.',
        'ctl' => 'Стрелки или свайп', 'author' => 'Габриэле Чирулли', 'license' => 'MIT',
        'src' => 'https://github.com/gabrielecirulli/2048',
        'icon' => '<rect x="3" y="3" width="8" height="8" rx="2"/><rect x="13" y="3" width="8" height="8" rx="2"/><rect x="3" y="13" width="8" height="8" rx="2"/><rect x="13" y="13" width="8" height="8" rx="2" fill="currentColor" stroke="none"/>',
    ],
    'tetris' => [
        'title' => 'Тетрис', 'desc' => 'Классические падающие фигуры. Собирайте линии.',
        'ctl' => 'Стрелки, пробел или кнопки на экране', 'author' => 'Джейк Гордон', 'license' => 'MIT',
        'src' => 'https://github.com/jakesgordon/javascript-tetris',
        'icon' => '<rect x="3" y="4" width="6" height="6" rx="1.4"/><rect x="9" y="4" width="6" height="6" rx="1.4"/><rect x="15" y="4" width="6" height="6" rx="1.4"/><rect x="9" y="10" width="6" height="6" rx="1.4" fill="currentColor" stroke="none"/><rect x="3" y="16" width="6" height="5" rx="1.4"/>',
    ],
    'breakout' => [
        'title' => 'Арканоид', 'desc' => 'Отбивайте мяч и разбейте все кирпичи.',
        'ctl' => 'Мышь, касание или стрелки', 'author' => 'Андрей Мазур и участники MDN', 'license' => 'CC0 (общественное достояние)',
        'src' => 'https://github.com/end3r/Gamedev-Canvas-workshop',
        'icon' => '<rect x="3" y="4" width="8" height="4" rx="1.4"/><rect x="13" y="4" width="8" height="4" rx="1.4"/><rect x="3" y="10" width="8" height="4" rx="1.4"/><circle cx="15" cy="16" r="2" fill="currentColor" stroke="none"/><path d="M6 21h12"/>',
    ],
    'invaders' => [
        'title' => 'Космические захватчики', 'desc' => 'Защитите Землю от волн пришельцев.',
        'ctl' => 'Стрелки и пробел или кнопки', 'author' => 'Дейв Керр', 'license' => 'MIT',
        'src' => 'https://github.com/dwmkerr/spaceinvaders',
        'icon' => '<path d="M7 4v2M17 4v2M6 6h12v3h2v6h-2v3h-3v-3H9v3H6v-3H4V9h2z"/><path d="M9 11h.01M15 11h.01" stroke-width="3"/>',
    ],
    'dino' => [
        'title' => 'Динозаврик', 'desc' => 'Игра из браузера, когда пропадает интернет.',
        'ctl' => 'Пробел или касание — прыжок, стрелка вниз — пригнуться', 'author' => 'The Chromium Authors', 'license' => 'BSD 3-Clause',
        'src' => 'https://github.com/wayou/t-rex-runner',
        'icon' => '<path d="M9 3h8v5h-3v2h3v4h-3v3h-2v4M9 3v6l-3 1v3H4M12 21H8v-4"/><path d="M15 6h.01" stroke-width="3"/>',
    ],
    'treasure' => [
        'title' => 'Клад', 'desc' => 'Восстановленная игра "Клад" "Вектор 06-ц"',
        'ctl' => 'стрелки / WASD / экранные кнопки или свайпы по полю', 'author' => 'gigachef.ru', 'license' => 'BSD 3-Clause',
        'src' => 'https://gigachef.ru/',
        'icon' => '<path d="M53,7H16c-0.552,0-1,0.448-1,1v5h-3c-0.315,0-0.611,0.148-0.8,0.4l-6,8c-0.274,0.365-0.266,0.869,0.019,1.225l5.154,6.442  L2.39,35.208l0.01,0.013C2.163,35.403,2,35.677,2,36v25c0,0.552,0.448,1,1,1h38c0.124,0,0.247-0.028,0.363-0.073  c0.044-0.017,0.08-0.046,0.121-0.069c0.052-0.029,0.109-0.05,0.156-0.089l12-10C53.868,51.578,54,51.297,54,51V26.949  c5.046-0.503,9-4.773,9-9.949C63,11.486,58.514,7,53,7z M52,45.168c-2.314,0.781-3.962,4.251-3.996,8.694l-2.471,2.059  c-0.657-1.426-1.956-2.498-3.533-2.82V36.468l2-1.667v5.37c0,1.237,0.709,2.299,1.852,2.772c0.383,0.159,0.778,0.236,1.167,0.236  c0.772,0,1.521-0.305,2.102-0.886C49.688,41.727,50,40.973,50,40.171v-10.37l2-1.667V45.168z M16,15h4.196L17,20.113L13.804,15H16z   M26.319,35h-1.539l3-12h8.139L26.319,35z M21.281,35l-3-12h7.438l-3,12H21.281z M32.075,15.767L36,21h-7.196L32.075,15.767z   M18.804,21L22,15.887L25.196,21H18.804z M27,20.113L23.804,15h6.391L27,20.113z M15.196,21H8l3.925-5.233L15.196,21z M16.219,23  l3,12h-1.539l-9.6-12H16.219z M17.2,37h3.3h3h1.431c-0.495,3.94-3.859,7-7.931,7s-7.436-3.06-7.931-7H17.2z M7.051,37  c0.503,5.046,4.773,9,9.949,9s9.446-3.954,9.949-9H40v16.069c-3.612,0.453-6.477,3.319-6.931,6.931H10.931  C10.477,56.388,7.612,53.523,4,53.069V37H7.051z M40,55.09V60h-4.91C35.514,57.493,37.493,55.514,40,55.09z M48,31.468v8.703  c0,0.267-0.104,0.519-0.293,0.708c-0.439,0.439-0.945,0.276-1.09,0.217C46.473,41.036,46,40.792,46,40.171v-7.036L48,31.468z   M44.238,32H44v0.198L40.638,35H28.88l6.4-8h14.958L44.238,32z M17,9h35v16H36.88l1.9-2.375c0.285-0.356,0.293-0.86,0.019-1.225  l-6-8C32.611,13.148,32.315,13,32,13H17V9z M15.12,35H5.94l5.683-4.371L15.12,35z M4,55.09c2.507,0.423,4.486,2.403,4.91,4.91H4  V55.09z M42,55.184c0.939,0.335,1.659,1.117,1.903,2.095L42,58.865V55.184z M50.128,52.092c0.287-2.164,1.035-3.844,1.872-4.619  v3.059L50.128,52.092z M54,24.931V9.069c3.94,0.495,7,3.859,7,7.931S57.94,24.436,54,24.931z"/><path d="M3,9c2.206,0,4,1.794,4,4c0,0.552,0.448,1,1,1s1-0.448,1-1c0-2.206,1.794-4,4-4c0.552,0,1-0.448,1-1s-0.448-1-1-1  c-2.206,0-4-1.794-4-4c0-0.552-0.448-1-1-1S7,2.448,7,3c0,2.206-1.794,4-4,4C2.448,7,2,7.448,2,8S2.448,9,3,9z M8,6.313  C8.444,6.981,9.019,7.556,9.687,8C9.019,8.444,8.444,9.019,8,9.687C7.556,9.019,6.981,8.444,6.313,8  C6.981,7.556,7.556,6.981,8,6.313z"/>',
    ],
];
$gamesData = [];
foreach ($gamesMeta as $id => $m) {
    $f = $gamesDir . (string)$id . '.html';
    if (is_file($f) && is_readable($f)) {
        $gamesData[$id] = mg_inline_assets((string)file_get_contents($f), $gamesDir);
    } else {
        unset($gamesMeta[$id]);
    }
}
$gamesJson = json_encode($gamesData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="light dark">
<title>Технические работы — GigaChef</title>
<link rel="icon" type="image/png" href="/assets/icon/chef_32.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root{
  --bg1:#fff7ed;--bg2:#ffedd5;--bg3:#fef3c7;
  --ink:#1c1917;--mut:#78716c;--card:rgba(255,255,255,.74);--card2:rgba(255,255,255,.9);
  --line:rgba(120,53,15,.12);--acc:#f59e0b;--acc2:#ea580c;--accink:#431407;
  --chip:rgba(245,158,11,.14);--shadow:0 20px 50px -20px rgba(154,52,18,.35);
  --stage:#0c0a09;
}
@media (prefers-color-scheme:dark){:root{
  --bg1:#14110f;--bg2:#1c1917;--bg3:#231a10;
  --ink:#fafaf9;--mut:#a8a29e;--card:rgba(41,37,36,.7);--card2:rgba(41,37,36,.92);
  --line:rgba(255,255,255,.09);--chip:rgba(245,158,11,.16);--shadow:0 20px 50px -20px rgba(0,0,0,.7);
}}
*{box-sizing:border-box}
html{-webkit-text-size-adjust:100%}
body{margin:0;min-height:100vh;font-family:'Nunito',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;color:var(--ink);
  background:linear-gradient(135deg,var(--bg1),var(--bg2) 55%,var(--bg3));background-attachment:fixed;overflow-x:hidden;line-height:1.5}
body.noscroll{overflow:hidden}
.blob{position:fixed;border-radius:50%;filter:blur(70px);opacity:.38;pointer-events:none;z-index:0;animation:drift 18s ease-in-out infinite alternate}
.b1{width:420px;height:420px;background:#f59e0b;top:-140px;left:-120px}
.b2{width:340px;height:340px;background:#fb7185;bottom:-120px;right:-100px;animation-delay:-6s}
.b3{width:260px;height:260px;background:#fdba74;top:45%;left:62%;animation-delay:-11s}
@keyframes drift{to{transform:translate(40px,-30px) scale(1.12)}}
.shell{position:relative;z-index:1;max-width:980px;margin:0 auto;padding:22px 16px 40px;display:flex;flex-direction:column;gap:20px}
.brand{display:flex;align-items:center;gap:10px;font-weight:900;font-size:18px;letter-spacing:-.01em}
.brand img{width:34px;height:34px;border-radius:10px}
.card{background:var(--card);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);border:1px solid var(--line);border-radius:28px;box-shadow:var(--shadow)}
svg{display:block}
.ic{display:inline-flex}.ic svg{width:100%;height:100%}
 
/* hero */
.hero{padding:40px 28px 30px;text-align:center}
.hero-ic{position:relative;width:116px;height:116px;margin:0 auto 18px}
.hero-ic img{width:100%;height:100%;object-fit:contain;animation:float 3.4s ease-in-out infinite;filter:drop-shadow(0 14px 18px rgba(154,52,18,.28))}
.gear{position:absolute;right:-12px;top:-8px;width:38px;height:38px;color:var(--acc2);animation:spin 9s linear infinite}
@keyframes float{50%{transform:translateY(-10px)}}
@keyframes spin{to{transform:rotate(360deg)}}
.pill{display:inline-flex;align-items:center;gap:8px;padding:6px 14px;border-radius:99px;background:var(--chip);color:var(--acc2);font-weight:800;font-size:13px;letter-spacing:.02em}
.dot{width:8px;height:8px;border-radius:50%;background:var(--acc2);animation:pulse 1.6s ease-out infinite}
@keyframes pulse{0%{box-shadow:0 0 0 0 rgba(234,88,12,.55)}100%{box-shadow:0 0 0 10px rgba(234,88,12,0)}}
h1{margin:14px 0 10px;font-size:clamp(30px,6vw,50px);line-height:1.05;font-weight:900;letter-spacing:-.025em}
.msg{margin:0 auto;max-width:540px;color:var(--mut);font-size:clamp(16px,2.2vw,18px)}
 
/* таймер */
.timer{margin:26px auto 0;max-width:520px;padding:18px;border-radius:22px;background:var(--card2);border:1px solid var(--line)}
.timer-t{font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:var(--acc2);margin-bottom:12px}
.tgrid{display:flex;justify-content:center;gap:10px}
.tbox{flex:1;max-width:120px;padding:12px 6px;border-radius:16px;background:var(--chip)}
.tbox b{display:block;font-size:clamp(28px,7vw,40px);line-height:1;font-weight:900;font-variant-numeric:tabular-nums;color:var(--ink)}
.tbox span{display:block;margin-top:6px;font-size:12px;font-weight:700;color:var(--mut)}
.bar{margin-top:14px;height:8px;border-radius:99px;background:var(--chip);overflow:hidden}
.bar i{display:block;height:100%;width:0;border-radius:99px;background:linear-gradient(90deg,var(--acc),var(--acc2));transition:width 1s linear}
.bar-l{margin-top:7px;font-size:12px;color:var(--mut);font-weight:600}
.soon{margin:26px auto 0;max-width:520px;padding:18px;border-radius:22px;background:var(--card2);border:1px solid var(--line);font-weight:800;color:var(--acc2)}
.soon i{display:inline-block;animation:blink 1.4s infinite both}.soon i:nth-child(2){animation-delay:.2s}.soon i:nth-child(3){animation-delay:.4s}
@keyframes blink{0%,80%,100%{opacity:.2}40%{opacity:1}}
 
.feats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:22px}
.feat{padding:14px 8px;border-radius:18px;background:var(--card2);border:1px solid var(--line);font-weight:700;font-size:13px}
.feat .ic{width:26px;height:26px;margin:0 auto 6px;color:var(--acc2)}
 
/* игры */
.play{padding:26px 22px}
.play h2{margin:0;font-size:clamp(22px,4vw,30px);font-weight:900;letter-spacing:-.02em}
.play-sub{margin:4px 0 18px;color:var(--mut);font-size:15px}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:12px}
.gcard{all:unset;box-sizing:border-box;cursor:pointer;display:flex;flex-direction:column;gap:6px;padding:16px;border-radius:20px;background:var(--card2);border:1px solid var(--line);transition:transform .2s,box-shadow .2s,border-color .2s}
.gcard:hover,.gcard:focus-visible{transform:translateY(-3px);border-color:var(--acc);box-shadow:0 14px 28px -14px rgba(234,88,12,.5)}
.gcard:focus-visible{outline:2px solid var(--acc);outline-offset:2px}
.gi{width:46px;height:46px;border-radius:14px;background:linear-gradient(135deg,var(--acc),var(--acc2));color:#fff;display:flex;align-items:center;justify-content:center;margin-bottom:4px}
.gi svg{width:26px;height:26px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.gt{font-weight:900;font-size:17px;line-height:1.15}
.gd{font-size:13.5px;color:var(--mut)}
.gc{margin-top:auto;padding-top:6px;font-size:12px;font-weight:700;color:var(--acc2)}
 
.stage{margin-top:4px;border-radius:22px;overflow:hidden;background:var(--stage);border:1px solid var(--line);display:none;flex-direction:column}
.stage.on{display:flex}
.stage-h{display:flex;align-items:center;gap:8px;padding:10px 12px;background:rgba(255,255,255,.06);color:#fafaf9}
.stage-h strong{flex:1;font-weight:900;font-size:15px;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sbtn{all:unset;box-sizing:border-box;cursor:pointer;display:inline-flex;align-items:center;gap:6px;height:36px;padding:0 12px;border-radius:12px;background:rgba(255,255,255,.1);color:#fafaf9;font-weight:800;font-size:13px}
.sbtn:hover{background:rgba(255,255,255,.2)}.sbtn:focus-visible{outline:2px solid var(--acc)}
.sbtn svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
.sbtn.ico{width:36px;padding:0;justify-content:center}
.frame{width:100%;height:clamp(470px,74vh,700px);border:0;display:block;background:var(--stage)}
.stage-f{padding:7px 12px;font-size:12px;color:#a8a29e;background:rgba(255,255,255,.04)}
.stage.full{position:fixed;inset:0;z-index:100;border-radius:0;border:0;margin:0}
.stage.full .frame{flex:1;height:auto}
.play.hide-grid .grid,.play.hide-grid .play-sub{display:none}
 
details.lic{margin-top:16px;border-top:1px solid var(--line);padding-top:12px}
details.lic summary{cursor:pointer;font-weight:800;font-size:14px;color:var(--mut);list-style:none;display:flex;align-items:center;gap:8px}
details.lic summary::-webkit-details-marker{display:none}
details.lic summary .ic{width:18px;height:18px;transition:transform .25s}
details.lic[open] summary .ic{transform:rotate(90deg)}
.lic ul{list-style:none;margin:12px 0 0;padding:0;display:grid;gap:8px}
.lic li{font-size:13px;color:var(--mut);padding:10px 12px;border-radius:14px;background:var(--card2);border:1px solid var(--line)}
.lic li b{color:var(--ink)}
.lic a{color:var(--acc2);font-weight:700;text-decoration:none;word-break:break-all}.lic a:hover{text-decoration:underline}
.lic p{font-size:12.5px;color:var(--mut);margin:12px 0 0}
 
.foot{text-align:center;font-size:13px;color:var(--mut)}
.foot p{margin:2px 0}
.foot .ic{display:inline-flex;width:16px;height:16px;vertical-align:-3px;color:#e11d48}
 
@media (max-width:560px){
  .shell{padding:14px 12px 28px;gap:14px}
  .hero{padding:30px 16px 22px;border-radius:24px}.play{padding:20px 14px;border-radius:24px}
  .hero-ic{width:96px;height:96px}
  .feats{gap:8px}.feat{font-size:12px;padding:12px 4px}
  .grid{grid-template-columns:1fr 1fr;gap:10px}.gcard{padding:13px}.gt{font-size:15px}.gd{font-size:12.5px}
  .tgrid{gap:8px}
  .frame{height:min(78vh,640px)}
}
@media (max-width:360px){.grid{grid-template-columns:1fr}}
@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
</style>
</head>
<body>
<div class="blob b1"></div><div class="blob b2"></div><div class="blob b3"></div>
 
<main class="shell">
  <div class="brand"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAMAAAADACAMAAABlApw1AAABIFBMVEUgICCrpZ1mZWUVFRWYcWNdRDvUqpEsKyujoqNQUFDBeV54Y1epjHbQ0dN7lZzZoH7WxrdXMi8uIiFcXFyduMGqwL++1+DT4N8+QUFMMzNBPUF/h39+gH9+gICKf4oAAAD6+/sDAgLwu5CxxMnDfGTFhW2rvcNWdnwVFRazeWbM1tg2SlQsKCg6VWGVpqurt7oyNTfTlnhQVli3hHGMlZnxwZo2UV1ceoJVZWsnGBXl6utONjBISUplaWt0hYrb5OX////217flqohtdXjvyKbUpYmPZlkJCQlJWmO60tj44shlfYXEysyKm6J0VUwcIiUYFxdDKyaFiYoYHCEnJidrS0M4ODj13MITExMWFhYyIR2EW1C7eF3S3eA1PUFJSUrxpwOUAAAAYHRSTlNj/SSg/f3+KRhO/v7+Ef7+/v7eov7+/v5OCj4g/pkYAP39/v3+/v7+/f39/f3+/v39/v3+/f7+/v39/f39/f39BP7+/f7+/tH9/v7+/f79/Y79/f5w/U/+zbH9/f79/TE0hNC+AAAblklEQVR42s1dCWOi2LLGuMQs3T1z17e3RLEBaUDExF3jFhO3aPb9//+LV3UOKCAoROyeundmEkWs79RyajuE+b4LSu4zT48XrJUen24Pkq3wv4oJ93Z7hPvrR9aVHqMHyb84gO/fP26fKLe8qtXnOqFOXVN5E8Ne668M4OP2gjJfj4gpG430ukoxhAshVAB714R9dT6qpFyoMprXQofAhKf8SQbZ5zUltYYUjehSNPmXk0BrD3Wfr4upDSTWEcLjQeuvBaBFll8bpXzQSCNC+PJXApCMAktSJOWTFLTnp72/BIAW/i+J6rNZeyz2PEc1AgSt1t7vl0DrADYuXk8FIkVCQ/gNEmgl9+5vmWsg5pY52Eu2WoR/SUkFpBG61INWK3lwwNzi/a7xfl9aOwXwZY+5toU4/GP07/8LXkUVU4Gpgrb897/xjmjjPljEFABAaxElrFCtkvoM1T0ipvvkDgC09o21X8Q48/+p12iI80n+KQJeqmkdvOG8M6hJBgYmGTIAYJ8wX+tERCu3FVHbgn+CoG79tKjMa3wgCIzPIIewX1/dp0SJVT/PPyKQnDcV50QOPvdqPwC+kBBTmrsZqvYp+7WIsMbWVl/UVd8BE+Nz+fm56zrrLD9KbUWiykbcNrop62+f2AxgH6MEzX2ZKxIbSW1Jf7iIAChLXBSDG/12AO4v1uyyOjtPbU1zVnFNHmSVItgOAPKvemqJWkuFQDXNVbfSSs0HAsYH/6J3UCmGAWAUd7tNRUlnca9mvq/VovUA9tbyn6orqVBId1XRf6cpgvvPSwCjZMmb/0okFRK562gaEIAWXex9FkAL/Kc0Sv02EgFBGiz56csnAdyD/CKp30gIQIbI4vZzAPYgzO/8Tv5TI9ChdGe9EnkDaN2y24U5a1nz5b7eUATpHMtetz4BAAWg7Ij/ppL2dRkBoIMm7wUH0IpuFSdvUO5stukn0iMAsAoTbQUGsEsBiNl0VvRrxcSOva3AEwADFrAj/ivpYADS2hpHxKzRoCCFklEzwLVZ9C7+AVzO1+QGXgD2IAYNEuj4Y8hUIKQAElBAhz4CAgANqgXTCr8ioK4liATSmHgzwQCgBjWC+MVs2qcIiG/P6r4AVEwADe+twAvAUzAfBAAU0f/6zwfZrO+LgcbeRuABIPnISkE2gWZaltOiX/2/1EppbwDNxX3Ef5pGMPV0pB4ADgI60Wa6UU9vRmDYryIN12jc8q30giAm3Q8EAAJRLRgAWZIBQWX9RZT/dJ2VvW1gad/N7AKAFhQAOKF6KpgNqBoECOuEIJr8KzeqtxfCm9jlZQBgQgBQWQegw+okcmmuyU6oAx2ynUsPk7du0s2lBoHMggPouMXnTW+Hp/DvlMHRqiI1RQsz0NrQPXwWUbLsm9MCwgOgZD20vELEPDS/TxSbi+sqyP1SGbLATM4jGqUwjbcsCvQ5AHX3KDItenlsmeXH1lUDGKJIebaSzrMNeKnpssUp6WWQIdo+VAoKwN0LGW5hRQoV0bA0VUk7KOt8ARqUKnlRbDpukcXlMU2gaf9UYCM+cA+FTKZsX14xFwvidi29iYCV+UJEzWYFyTARQKDLVIPeHJ+qBXWjUNFyy4fhi2RjXUcifnnTZp6gqZsQQIpeW301a/DfydGYSnS+Dx2Dj6ChBImmHck32Gpp4M1dFjbMmuL9NuFf8rgA+ed11KDmyntrcjJmfT4D7q7iqLhKOW8WZajqq7I3/yCiqez56TpbQr1yeQfy+qdW4HxAI37H7nZABBFe0r0RgKz5kgfCsYqVeq+PKhr7vkbxboNnZBiOouPMWt0OvACxbV3Bl13WWMRCGjutuyyzTAZtSqO0y0ezBN004gXAO5bzzomfSFnRjKcWO9Mb9eTq3I2PdLZjTGXxubpukYPSGBgzZ3zNXQQwg3PjqVz6muKcZ1XiljjSiqs/H4OiaDq1PEr0e0qkvVijY1mspNY0baBptZzB/LRE4Kl1PZs2P2xwCG+o4zXGwTLB60LQGhiR8qS7vgKjDcsayw3KHYsrrGvSSv+df++A3jXoRdKgIS+WRCGDaGu8V1ry1qA1lTmorU8cEYmFCCcSbbE3OqV3usYSygU/oegdrabekFd5EEWpQ/XjMj3XDF1Sa/VOYz7vELBSwxYv2b60Off2Qetqox8spsVNT6dRd64yjCoqVNnM71fksa6PI441kOsq7/ik3SnAT8rQNJV/itBL99agddVphhRHDbesdHT7pkM008J8rSMrtvXLWi+3Y8iCwuWkJQoetMtiEWDzi80Gwu762hYHs75BNl+IYMjnrH4lO1eJT6nlajCrodNVxljsDYILJWtlPLsMFei/jcRY1jslbdDooCSnHdliThJGhUbgmIqsb5Ot69AwRIlMK4A9StLmaHuXaZm6S022xwLmpkcCpGxWMX1UVlFI9FShkZ9DIAoZY+Q1XB9F7uRuYJ8xdamZGsGb15/tkQECPp2yuR5eete0HG/36HShFeuGhyGmOFrmBU3LW2+jtMX5AjIKgZ1K0pT4Wd3kv5JqqhumA9d3KWmXb7mh1JZqi6u/YIEs8VuQIoDoNOwb885qw7wrrAdMUniXRf30iaMYPVYseyL1ICpR2exS1Udi4F6Iw78pOorBsslhdQBjiG36xOBLoxh/NR0pIa9cLoJ4WPtRs/LZHqTV3acgb2wY+TOVJ3wVe3G/3awEmbXRIuJS2yM8Ky2WXkl/Yu3tlVvzzmKqg9ZL2RfNAd+NEzcbp1WS18ZOY3p2GQRtBjJiM5XaqgvsBFC6JIvUNEesN0/3Mj6noqWSbqatNCnckvtVACMY3oGMlPoypYPsX9x+CWXkzDgW8N7AeF2ck6B+W+5pEdSWEuCWVXuDTDttHDO43gtr6M8cueTVUkcHU+s0KyENElgppYByRuZGVPt4/dEKc+zyg4kuIx89lQpnkMAOYGQZfr31e8jD/+Tu3v1i6jiyGwCiEd493X4kdzM7nfy4J4JQwpumsQKAEUI+evsfe8mdTq9/eSSZWvgAoOekso9fdj5+DyMI/E4AQORWWzfVERoAPlgHfGPDzBJLaJ4FxDABsMH6l0EA1Nck76EBCNK/rCzJB4A3jCWYXwFgff8SK9ai6FoIoJmNCebfDgAizPD+GgAlj+VuEr4hwFayWdfKI8kt8R0E4sxpAID+KwC4NZ+A9ZG1nIOMKrKMRRVK8HNEyWbXtw5I/r57AFHWPvCNvBvVBywDQZFLq72rkN3ySEb+CXQjSWpO06CWNcb6i7hawSIArncNwDYIZTaXgPMGnKeZ8qwv4uHYTL2BMC4vbQCUdSW4EAEoVu6VcUdTJQfrZaBnSZrNqpRm0jO8Yr+In0LFsWEt22E09/Rl1wCeSCRBuFcUfaDeWPl+rsYn/X5MEISCgzj4RxBi/f6kOrMi4aG+atZ5MZp73NsxgC9PEEm8oclCCWpRHS3P4pMjYBwYpZRHSiQSeUqclQBI/2VyV31e1iVpuofR3M4BYPcvks6O6zmT9+rdS18omFx/+9Zut09cCF7+RqEQ8QCOQuzlrlo2q0H1cRqjuYtdA4CCqaQ0coYS1CYxYCWfaHtw7Y6k10MgGaJaQmxSM26mzuFI084B7F+AxE3Z3yVg1RPtk0Svl4hEEr2Toh8E3W4iUei/9PsgCkHIZLg7837oC36BBKjmgP6XC/l8G5g5qlKzfL6LRTZxX+z2YhPDaZWl+IuQAYsHazANe+cSwJl2ln+u4qnHI9CbyNGzzTlWY5Fu0SmJYnHB/XG8bLteuotluD6akkTeuN31PoCNGz4ej1fhuxMnvYnb0VZQJiTQqiUl8LXIUXn18vKES+DdZjN0arvPB2COpQwAZiwb6xWq7lttmectgcQimuA9dupqJIYAJNSsX5IPIABYSkliQyIe1VCaVflfEMy1QALSpFq2xzYYqkkqUA0aTppWX1Cn01n+UoK3aia9qxDxSVaplFX+F+UDz8/0aLEOug1x82g0wkzlreKg5iq9iW9ITfJv/JA4GmWViN6hewG/qRmwPYDWf9ECnaSLJGM0GBNNGo0s2QtJYIz0xtIuU7LKIoKj2IGUOd1dnu6TuwTwcU2lDaew38wMYN4YmopSWtJgAEMG3gRvw0WgYY0GNJIBVPpN7BhVxfvdAfjAk608ZjQiRtE5FVIAfnsTlnIaNpkrY+OF+10BwJOJNZgUGKZGcmm6NGHTkqdoxlMLSeq7mpNuFsRbyY5C6ihKU0c0/Iau3hYA8FgNdBO1lEiO+t5MpfdBpzEeDqjWYLv9Uv7xo/MDCV8hnUDUJiudDkr4do7SO/FGuBo1WXmD0n0tFyyvDPJcCTCAUg3SgSYe9L3JnQ5Ix+Mfw+EZYTkiQob4Y0ml8SX0xH+c/nRQaXh2djYgL1NIpWFnAHtKLgJT1dBMnwYKiJhAqcCUCGAE5jYdDH7QAcZGifL/lQyxWPhvAJ7Lhp3/09PT0hColDvN5eAXguJseNb4ATLtKKM6NKDVQPsxE6ioqEL1MlJR3lke1i1CZiO+/kD+z340YLVRgZYEL/xLPl0RALA7PPuZ+3mKAE4NRGcDmDCAAX5swL0H2s6YQFvYuwYahHNxaunnmDS2TJaHyH/Wyr+MClRy8k8FMMidEmmY/DdKpwNY+AbOV6q5HQLI1eCAdBqO5OR+npGN6B+mzkTeQGEsCkQ16myF/wGsNiiQoU6nP09BIvAS/AJ8l7I4UVLbFYB74iJUEYYg+dOfDTq4ZSjQ10ugsYV/olHjFf2h7CLjp9QIFiZxegqDy1lwbwjgdocA3kcwSs5rRIPS46UBXL5FLPyfKahRJWASnNXAXH7qgRolqv0IIDcAjzT8AfbwU7thc4r8vmMA79ilRwADdEER4n+AhaxNgc7ODAOg9cUb2GlNBHDt2Y8l/6c/8dPgU+EncJ/v8q4lkIMFwjlaAwBlGdgF/i+/LpcfNCp9ScaLDJpSpafs/jT4RwEQiVBEACC3WwDohWrsjZKGMn4OARAPekYN4F+y3QBEHEJmIeTWG7hJ8SooyWmpcXY2tCoQkQhF9BNilFpEn7JqLVBmHAwAVlR09HUIQCbrSQ3gMn1mARAB/qc4Cko6BmKaTAKBB8LL6RaWIwIgEkGfhADQC8ng4FT4juguJNAyQmmYx8ixU22MHojQigcdU/6H4kiR56SaroMQ3nHHheXOmQQKRBbglEACOd3ocp1kNRhL7IUOYM/IgmH+H9ZJ+jo2BCCjB5VLVgUSI9Cy1t8UnO+C6m1DRtVgNapAC/aJB0KfRH9l2YEs54ySU7QVugSgqsvOH47LGLJgMJcbUv6/ogJZPegwe5klmgbTn0bAXZLlxhT+0zgrLdcftgBDALj+UKyUdNQgtlqI8QHKK0yAgpb68HB+DIsvKxhOqySqJAZwaVUgWcQTLx0YoKSdAqJ2wBvPTn8MLQpE+SevnObgftPGeAwCuIOCaTxAo4PxX89iI1fn3S7cXFMiiIBHHUZvaovhvl6KeJRGJvxXoVXQj5MJU1RviexsBpkWjEnBDY6+EguYQbWUE8r+PSnj/2GQ2sP5+UkxUsbzD7JcoxPpsF+lIzYPivznxrjibBwqn5kC14cf6/JYI0nEAgAKYHgGP5AeyTvkxjil1ecyUO6d+C+S+gQAClT+AwQAteUYJvXQdiTD31BckbPojsw9LIv71/tYRr9TFQRsB7wKUPvkG2M9h5BhSyN+dDBsNIbDnEQaa1Kp8VVH/u+Q/0ymMPOtRIzvmrRO+AcER+SUADRR62TonpcgVDCySq2EuTJsqOhOZgL3DT+Q5woTHMfVydzsVH0nAhiUtNz71GjQNOBN3LjjBWSfy3Ax37k943cLqIEC0SIzkcG0PgY29VJuail+LnxOBCfb+5zZ0shjETUHTJZu7Nfi4teG5MHIKuGfCgAQgOE8JkMDgHtw4oEKABEUyrQpJGMzuzPILTt9NyrMh1MDnuSX9XViltp4PG5oy7MDEOW9Q99Yh1c776RMXRAyJgCB92nHjE8BdK5MAWCdvxc3DpIQDCAJ6G9joWoIrhAYKhF3bmkQ5Asv5MwKQPiKrXAsbZVIUQuuHw4k2lngMggAmk/4f2jbXPx3SABAANIf6IIsRCvrvDoYzg0QhIAfepimLFibZu0CuX5aQghjnED4inpDGvtUJLM+UR8wekMIfl0p4681bBMAoUTB6LWQwzDgUgh1zONWfVvTr80RhlDFIAMjVw6HIAmV2kK52ufyXL6HncJ227CDO9ZXz5Xx15NJOARAqBdb9osgb7GaZrUH759fLT7DHXKTpe7TIp3562xSwD6nReHACKgI7sMAgMMFGgigu2jTwc8J+uNJr9CvrraN1OME4f/qaslTptB3aYhI8b5AV95KHEHgL6Bg/NSzMIgwF7OIfF2ZcIrFYg/67vHZc5k07KsT8PVHf5B3H66uuhYAGbodTKrkUhxLuHuJCVwPmYc1OV8uEWpchu4FHyEAYGgUtxDA1ZUVAfk6AQknJKDx+8zedam1WDiiWoGLWhboyESMNIkp+yfnDw9XD5bvQLzQypf81FcYPxp0ZDVhIoGrh67VRvPc6ytBgVFDwaU9bBgmLOqLQK98hTAjb97xgZJVBByJiJ6SWwNwaBCImwJw2HSbzEHkezGWxwAob9fqvGD4Rh72N4N67eWSIPe2W+bNeOJjawAfGMZZpAsIHhBB170RX4D2K+jGK6xucQnuUKAbFEqgtzp7cG43AQoAdMiPH2J8xKE1xybQtXxbsZewcXTMs3eg4zi+YkgB9esQFh+1Hm3Aejl0w7vUF3Rtu0zxhOhQ1UergPFhArErl03A4GACHct4zzLIAV9aRn/0QhYd+XgF5RHunsHvwLW1hPXDZV46SrhOiKAnJWnBl60AtOjBaE8APZLll++WsxBHi73szwJ3CJMch4fI/2IL4OMRc7GLRyTfrLoi8G0EmwDggFPi6txd409gr+GPT0wl6vYm1s7XrM/R8KbQt+11AIFe/62XwNwinnCxpbxh8xuNgPFRTPnDAwBaLHvUXQyjxJwdy2qfeNaViQpDbeBzOObBxlZFUPxGYury9gDu7duY/VswqI51zeV3nfxwzioaEYQphCJGSHcuAHoCRzXvdksAsA9XAYD7KBZq9oS6ke5xOVBzuE+9bBezzOqJBwBMjZkQAJyfuwIoJsisUyzR7Z4U4kHb29UCfC7RZ9cBQD8aBoDuOgng8ErtU4M3kmY8O6DqqkKgQWEBSHgAKMbDGReadN3caIgAeh7bAPFCIVCh6A4gNBVKnHuo0Mlm1anWNl4S73qFr1wIRrwPG1LhxAPAZhFAqXayYZ6lHPECwKEXut0egFToeUmgeLyWu+d+AWLr2Fo5lWNu920TARSet5cAFBXLQr7rNZJbLHhzV56AK8yTyuKzt4oJPbf75gW/eT3jo68hcG3vmeLjifsOJiH7XJvURjnhZeaO8aWQd70rR0pcwtbBHC0KQVDmDaCdB+6cilTWYgWYq24XkbonMOydgIFj50XVF4Fz55/WhnCi9yK5bULDkJr3GhG08UQADtKTuoRUJSER/Kca7xeOjyM9GG0s9O/uqiotZc8kPN4xq971SerpPi9OU1CsbW2d0CCAGajyurn0PJeh5xoEcgICejKuhsFXJ7HjBJhEgR43AA6/edyRaFCm4GMb8JETX6ARcL21RwLypC5rHN4gmI77k3gVT85APIoHU+CgQQ/j53aeM+qfzsTfmgzQGgDa8PZJPVrxBJZ47Uw9YOjRWkN7Ub/r4jkBMgXeIwWw4uIABDnpse7AhFGE6ftpNPkqbFU59IfBqHhu1BqAHAn7JsobBeqqn34x4+fMRhm9cjsY/8T/GNxj7bPo+6M9S4H9PqTiLvbeuCDsC/Fjmja26fGaxPFLzCeEttkhgJ7I4/+FUV4HHXom+0oABJBfSkcF4yBEHg6psOWETwAG/5mC5KvDwfhrUWL7VghgBsXEhFSgYShfIlnxrOCX/9fM0oQ/wmkx4VaA/c9ACLrHywioHO/7/ahhwDQfvm6FAwCfMtTHvSUIAsgKC7H+ywsct+p7RQxeW7AhAF8TH4zPoeky3lkIguBb5hA/QfboQ1+fKy75JxbgRwD+AOxRR/QqZF59I8i/vsKu/A3aR71vec4P8qIRQWSMaQl/Z+L8jRrsY/mEmpdfX5S3RQq9fNuH/1zyj3tANMSHw+BeUDYCFK59EpyKmy/omf7TCON8nixmfB+hNCYxMArzyTVpXJA4ohjAfI04mj0Id+AJH5r3Yq6RLyHQdqZXK2dFfV4t/KMHiob8eB58aN7fYgspuyUijlcM/q/ON2tU27r81ACekmEP/SWJGSwQrOYi+V6xaAfwsNLOXBQdXntFa0JkWX6wsucgh1oDjF1GjZiI0qvTFPL9hA1B19lPtvikl5dljprnrMtPa+oXB7uY3MU/jygJ3KEXBOHVpljFrmnCK7J6+VMwr3SsPvA/C3YQiwl2mtuGACG0LSUimDmxJf/Foru99svVvJmK2lcf9H8W8FBxoINw+0SLuMND61cuMBTK8F5vY6DNgY7E8jSRdlJw/gMeRUQE5b79i18NDEU4HT3bGLblBaHKxuETwP7hKv9S4EPdAQ+Dkj8T+uJcOuhjA0s9iIBhbpVbt23lX4W41ZnZ+SfdzPsdP9UgapkutFsfh33dauEV1ajooT6vwoQUOdzYL+D+e3G/6/PESdyTZzEX/aWTesgd12u7PE4ClQY3Wangyr+AJb1Axyg/+VfSycMjYUTSbRlRh8FEYPaGPi1j8SgMfBDG6+shFysb08VOKryU/T5dcfs/807UqOqmyMggj1MSiMEs1ZnXHQocbrKrCnRoLH/wZzJ89u/U73sKAWshMK4CynL4SnAcGj730FzleMFF+0mJ/unj+/dfBMAQAliCcyMSiCXOIAvOCMu3yANICrEqaQ+vst+f+f570KEBoEJAPeKcCMh45Qyq5wIdYzUeyTPBhy5Az8xxeSZDRymeDlrffykAQwh8fAUCF6NDE8+zP++wJEGeJTQrGw0Zh+elYoFHuyY/y8bnAYAQoqTPgi719dCmE1Xeq+mUsUX+xoWfcD5hADAhsNgqsjsV1BjJ0jvjy9CvsbstbIRUjScxtL7/JgCmKcCDeeyahOpfEOCBWpO7u7sJeZIQ52T/jhburrdif2sACylA/6jvNIflY7Usod8hwfZCdediW/ZDAACpGhO9MJ4V1BeWIATThZJnIJlOFTVnYvRcL273tv/2EADgU5HNRwqXQRAxwVh0yrbBOkceD9ZfPFcGFn8vjO8OBQCKYX/5WGRoANNBakN/cEpaiMUmtBNLuI/6fqrxrwKA1rBv6hIdloMHBlXj+DCoKvaGF29cRK+Z/VZo3xoiAKJLACJ6ceExGHERjTLM/l6oXxkuAALiP8EzAUUJXRDCn66B9xBX3qT/B1x5FfCw/qoYAAAAAElFTkSuQmCC" alt="" width="34" height="34">GigaChef</div>
 
  <section class="card hero" aria-labelledby="ttl">
    <div class="hero-ic">
      <img src="data:image/png;base64,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" alt="Шеф GigaChef">
      <span class="gear ic" aria-hidden="true"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M230.1,108.76,198.25,90.62c-.64-1.16-1.31-2.29-2-3.41l-.12-36A104.61,104.61,0,0,0,162,32L130,49.89c-1.34,0-2.69,0-4,0L94,32A104.58,104.58,0,0,0,59.89,51.25l-.16,36c-.7,1.12-1.37,2.26-2,3.41l-31.84,18.1a99.15,99.15,0,0,0,0,38.46l31.85,18.14c.64,1.16,1.31,2.29,2,3.41l.12,36A104.61,104.61,0,0,0,94,224l32-17.87c1.34,0,2.69,0,4,0L162,224a104.58,104.58,0,0,0,34.08-19.25l.16-36c.7-1.12,1.37-2.26,2-3.41l31.84-18.1A99.15,99.15,0,0,0,230.1,108.76ZM128,168a40,40,0,1,1,40-40A40,40,0,0,1,128,168Z" opacity="0.2"/><path d="M128,80a48,48,0,1,0,48,48A48.05,48.05,0,0,0,128,80Zm0,80a32,32,0,1,1,32-32A32,32,0,0,1,128,160Zm109.94-52.79a8,8,0,0,0-3.89-5.4l-29.83-17-.12-33.62a8,8,0,0,0-2.83-6.08,111.91,111.91,0,0,0-36.72-20.67,8,8,0,0,0-6.46.59L128,41.85,97.88,25a8,8,0,0,0-6.47-.6A111.92,111.92,0,0,0,54.73,45.15a8,8,0,0,0-2.83,6.07l-.15,33.65-29.83,17a8,8,0,0,0-3.89,5.4,106.47,106.47,0,0,0,0,41.56,8,8,0,0,0,3.89,5.4l29.83,17,.12,33.63a8,8,0,0,0,2.83,6.08,111.91,111.91,0,0,0,36.72,20.67,8,8,0,0,0,6.46-.59L128,214.15,158.12,231a7.91,7.91,0,0,0,3.9,1,8.09,8.09,0,0,0,2.57-.42,112.1,112.1,0,0,0,36.68-20.73,8,8,0,0,0,2.83-6.07l.15-33.65,29.83-17a8,8,0,0,0,3.89-5.4A106.47,106.47,0,0,0,237.94,107.21Zm-15,34.91-28.57,16.25a8,8,0,0,0-3,3c-.58,1-1.19,2.06-1.81,3.06a7.94,7.94,0,0,0-1.22,4.21l-.15,32.25a95.89,95.89,0,0,1-25.37,14.3L134,199.13a8,8,0,0,0-3.91-1h-.19c-1.21,0-2.43,0-3.64,0a8.1,8.1,0,0,0-4.1,1l-28.84,16.1A96,96,0,0,1,67.88,201l-.11-32.2a8,8,0,0,0-1.22-4.22c-.62-1-1.23-2-1.8-3.06a8.09,8.09,0,0,0-3-3.06l-28.6-16.29a90.49,90.49,0,0,1,0-28.26L61.67,97.63a8,8,0,0,0,3-3c.58-1,1.19-2.06,1.81-3.06a7.94,7.94,0,0,0,1.22-4.21l.15-32.25a95.89,95.89,0,0,1,25.37-14.3L122,56.87a8,8,0,0,0,4.1,1c1.21,0,2.43,0,3.64,0a8,8,0,0,0,4.1-1l28.84-16.1A96,96,0,0,1,188.12,55l.11,32.2a8,8,0,0,0,1.22,4.22c.62,1,1.23,2,1.8,3.06a8.09,8.09,0,0,0,3,3.06l28.6,16.29A90.49,90.49,0,0,1,222.9,142.12Z"/></svg></span>
    </div>
    <span class="pill"><span class="dot"></span>Идут технические работы</span>
    <h1 id="ttl">Скоро вернёмся</h1>
    <p class="msg"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
 
    <?php if ($secondsLeft > 0): ?>
    <div class="timer" role="timer" aria-live="off">
      <div class="timer-t">До окончания работ</div>
      <div class="tgrid" id="tgrid"></div>
      <?php if ($totalSec > 0): ?>
      <div class="bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="bar"><i id="barfill"></i></div>
      <div class="bar-l" id="barl"></div>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="soon">Работы идут, скоро вернёмся<i>.</i><i>.</i><i>.</i></div>
    <?php endif; ?>
 
    <div class="feats">
      <div class="feat"><span class="ic"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor">
                <path d="M184,120v61.65a8,8,0,0,1-2.34,5.65l-34.35,34.35a8,8,0,0,1-13.57-4.53L128,176ZM136,72H74.35a8,8,0,0,0-5.65,2.34L34.35,108.69a8,8,0,0,0,4.53,13.57L80,128ZM40,216c37.65,0,50.69-19.69,54.56-28.18L68.18,161.44C59.69,165.31,40,178.35,40,216Z" opacity="0.2"/>
                <path d="M223.85,47.12a16,16,0,0,0-15-15c-12.58-.75-44.73.4-71.41,27.07L132.69,64H74.36A15.91,15.91,0,0,0,63,68.68L28.7,103a16,16,0,0,0,9.07,27.16l38.47,5.37,44.21,44.21,5.37,38.49a15.94,15.94,0,0,0,10.78,12.92,16.11,16.11,0,0,0,5.1.83A15.91,15.91,0,0,0,153,227.3L187.32,193A15.91,15.91,0,0,0,192,181.64V123.31l4.77-4.77C223.45,91.86,224.6,59.71,223.85,47.12ZM74.36,80h42.33L77.16,119.52,40,114.34Zm74.41-9.45a76.65,76.65,0,0,1,59.11-22.47,76.46,76.46,0,0,1-22.42,59.16L128,164.68,91.32,128ZM176,181.64,141.67,216l-5.19-37.17L176,139.31Zm-74.16,9.5C97.34,201,82.29,224,40,224a8,8,0,0,1-8-8c0-42.29,23-57.34,32.86-61.85a8,8,0,0,1,6.64,14.56c-6.43,2.93-20.62,12.36-23.12,38.91,26.55-2.5,36-16.69,38.91-23.12a8,8,0,1,1,14.56,6.64Z"/>
            </svg></span>Ускоряем сервис</div>
      <div class="feat"><span class="ic"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor">
                <path d="M194.82,151.43l-55.09,20.3-20.3,55.09a7.92,7.92,0,0,1-14.86,0l-20.3-55.09-55.09-20.3a7.92,7.92,0,0,1,0-14.86l55.09-20.3,20.3-55.09a7.92,7.92,0,0,1,14.86,0l20.3,55.09,55.09,20.3A7.92,7.92,0,0,1,194.82,151.43Z" opacity="0.2"/>
                <path d="M197.58,129.06,146,110l-19-51.62a15.92,15.92,0,0,0-29.88,0L78,110l-51.62,19a15.92,15.92,0,0,0,0,29.88L78,178l19,51.62a15.92,15.92,0,0,0,29.88,0L146,178l51.62-19a15.92,15.92,0,0,0,0-29.88ZM137,164.22a8,8,0,0,0-4.74,4.74L112,223.85,91.78,169A8,8,0,0,0,87,164.22L32.15,144,87,123.78A8,8,0,0,0,91.78,119L112,64.15,132.22,119a8,8,0,0,0,4.74,4.74L191.85,144ZM144,40a8,8,0,0,1,8-8h16V16a8,8,0,0,1,16,0V32h16a8,8,0,0,1,0,16H184V64a8,8,0,0,1-16,0V48H152A8,8,0,0,1,144,40ZM248,88a8,8,0,0,1-8,8h-8v8a8,8,0,0,1-16,0V96h-8a8,8,0,0,1,0-16h8V72a8,8,0,0,1,16,0v8h8A8,8,0,0,1,248,88Z"/>
            </svg></span>Новые функции</div>
      <div class="feat"><span class="ic"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor">
                <path d="M224,96a64,64,0,0,1-94.94,56L73,217A24,24,0,0,1,39,183L104,126.94a64,64,0,0,1,80-90.29L144,80l5.66,26.34L176,112l43.35-40A63.8,63.8,0,0,1,224,96Z" opacity="0.2"/>
                <path d="M226.76,69a8,8,0,0,0-12.84-2.88l-40.3,37.19-17.23-3.7-3.7-17.23,37.19-40.3A8,8,0,0,0,187,29.24,72,72,0,0,0,88,96,72.34,72.34,0,0,0,94,124.94L33.79,177c-.15.12-.29.26-.43.39a32,32,0,0,0,45.26,45.26c.13-.13.27-.28.39-.42L131.06,162A72,72,0,0,0,232,96,71.56,71.56,0,0,0,226.76,69ZM160,152a56.14,56.14,0,0,1-27.07-7,8,8,0,0,0-9.92,1.77L67.11,211.51a16,16,0,0,1-22.62-22.62L109.18,133a8,8,0,0,0,1.77-9.93,56,56,0,0,1,58.36-82.31l-31.2,33.81a8,8,0,0,0-1.94,7.1L141.83,108a8,8,0,0,0,6.14,6.14l26.35,5.66a8,8,0,0,0,7.1-1.94l33.81-31.2A56.06,56.06,0,0,1,160,152Z"/>
            </svg></span>Чиним баги</div>
    </div>
  </section>
 
<?php if ($gamesMeta): ?>
  <section class="card play" id="play" aria-labelledby="ptl">
    <h2 id="ptl">Пока ждёте — поиграйте</h2>
    <p class="play-sub">Игры уже загружены и работают без интернета. Выберите любую.</p>
 
    <div class="grid" id="grid">
<?php foreach ($gamesMeta as $id => $m): ?>
      <button type="button" class="gcard" data-game="<?= htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8') ?>">
        <span class="gi"><svg viewBox="0 0 24 24" aria-hidden="true"><?= $m['icon'] ?></svg></span>
        <span class="gt"><?= htmlspecialchars($m['title'], ENT_QUOTES, 'UTF-8') ?></span>
        <span class="gd"><?= htmlspecialchars($m['desc'], ENT_QUOTES, 'UTF-8') ?></span>
        <span class="gc"><?= htmlspecialchars($m['ctl'], ENT_QUOTES, 'UTF-8') ?></span>
      </button>
<?php endforeach; ?>
    </div>
 
    <div class="stage" id="stage">
      <div class="stage-h">
        <button type="button" class="sbtn" id="back" aria-label="Назад к списку игр"><svg viewBox="0 0 24 24"><path d="M15 5l-7 7 7 7"/></svg>Игры</button>
        <strong id="stitle"></strong>
        <button type="button" class="sbtn ico" id="restart" aria-label="Начать заново" title="Начать заново"><svg viewBox="0 0 24 24"><path d="M20 11a8 8 0 1 0-2.3 5.7"/><path d="M20 4v7h-7"/></svg></button>
        <button type="button" class="sbtn ico" id="full" aria-label="На весь экран" title="На весь экран"><svg viewBox="0 0 24 24"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg></button>
      </div>
      <iframe class="frame" id="frame" title="Игра" sandbox="allow-scripts allow-same-origin" allow="autoplay"></iframe>
      <div class="stage-f" id="sctl"></div>
    </div>
 
    <details class="lic">
      <summary><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"/></svg></span>Об играх и лицензиях</summary>
      <ul>
<?php foreach ($gamesMeta as $id => $m): ?>
        <li><b><?= htmlspecialchars($m['title'], ENT_QUOTES, 'UTF-8') ?></b> — <?= htmlspecialchars($m['author'], ENT_QUOTES, 'UTF-8') ?>, лицензия <?= htmlspecialchars($m['license'], ENT_QUOTES, 'UTF-8') ?>.<br><a href="<?= htmlspecialchars($m['src'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($m['src'], ENT_QUOTES, 'UTF-8') ?></a></li>
<?php endforeach; ?>
      </ul>
      <p>Все игры — открытое программное обеспечение. Интерфейс переведён на русский, добавлено управление касанием; уведомления об авторских правах и лицензии сохранены в исходном коде каждой игры.</p>
    </details>
  </section>
<?php endif; ?>
 
  <footer class="foot">
    <p>GigaChef — умная кулинарная социальная сеть</p>
    <p>Спасибо за ваше терпение <span class="ic" aria-hidden="true"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true">
            <path d="M217.36,133.36,128,224,38.64,133.36a50,50,0,0,1,70.72-70.72L128,80l18.64-17.36a50,50,0,1,1,70.72,70.72Z" opacity="0.2"/>
            <path d="M223,57a58.07,58.07,0,0,0-81.92-.1L128,69.05,114.91,56.86A58,58,0,0,0,33,139l89.35,90.66a8,8,0,0,0,11.4,0L223,139a58,58,0,0,0,0-82Zm-11.35,70.76L128,212.6,44.3,127.68a42,42,0,0,1,59.4-59.4l.2.2,18.65,17.35a8,8,0,0,0,10.9,0L152.1,68.48l.2-.2a42,42,0,1,1,59.36,59.44Z"/>
        </svg></span></p>
  </footer>
</main>
 
<script>
(function () {
  'use strict';
 
  /* ---------- Таймер ---------- */
  var secondsLeft = <?= (int)$secondsLeft ?>;
  var totalSec    = <?= (int)$totalSec ?>;
  var grid = document.getElementById('tgrid');
 
  function plural(n, a, b, c) { n = Math.abs(n) % 100; var d = n % 10; return (n > 10 && n < 20) ? c : d > 1 && d < 5 ? b : d === 1 ? a : c; }
  function two(n) { return n < 10 ? '0' + n : String(n); }
 
  if (grid && secondsLeft > 0) {
    var end = Date.now() + secondsLeft * 1000;
    var fill = document.getElementById('barfill'), bar = document.getElementById('bar'), barl = document.getElementById('barl');
    var done = false;
    var render = function () {
      var rem = Math.max(0, Math.round((end - Date.now()) / 1000));
      var d = Math.floor(rem / 86400), h = Math.floor(rem % 86400 / 3600), m = Math.floor(rem % 3600 / 60), s = rem % 60, units;
      if (rem >= 86400)     units = [[d, plural(d, 'день', 'дня', 'дней')], [h, plural(h, 'час', 'часа', 'часов')], [m, plural(m, 'минута', 'минуты', 'минут')]];
      else if (rem >= 3600) units = [[h, plural(h, 'час', 'часа', 'часов')], [m, plural(m, 'минута', 'минуты', 'минут')], [s, plural(s, 'секунда', 'секунды', 'секунд')]];
      else                  units = [[m, plural(m, 'минута', 'минуты', 'минут')], [s, plural(s, 'секунда', 'секунды', 'секунд')]];
      grid.innerHTML = units.map(function (u) { return '<div class="tbox"><b>' + two(u[0]) + '</b><span>' + u[1] + '</span></div>'; }).join('');
      if (totalSec > 0 && fill) {
        var pct = Math.max(0, Math.min(100, Math.round((1 - rem / totalSec) * 100)));
        fill.style.width = pct + '%'; bar.setAttribute('aria-valuenow', pct); barl.textContent = 'Работы выполнены примерно на ' + pct + '%';
      }
      if (rem <= 0 && !done) { done = true; setTimeout(function () { location.reload(); }, 3000); }
    };
    render(); setInterval(render, 1000);
  }
 
  /* ---------- Автовозврат: как только сайт откроется, перезагружаем страницу ---------- */
  setInterval(function () {
    if (document.hidden || !navigator.onLine) return;
    fetch(location.href, { method: 'HEAD', cache: 'no-store', credentials: 'same-origin' })
      .then(function (r) { if (r.status !== 503) location.reload(); })
      .catch(function () {});
  }, 60000);
 
  /* ---------- Игры ---------- */
  var GAMES = <?= $gamesJson ?>;
  var META = <?= json_encode(array_map(function ($m) { return ['title' => $m['title'], 'ctl' => $m['ctl']]; }, $gamesMeta), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var play = document.getElementById('play'), stage = document.getElementById('stage'), frame = document.getElementById('frame');
  if (!play || !stage) return;
  var current = null;
 
  function open(id) {
    if (!GAMES[id]) return;
    current = id;
    document.getElementById('stitle').textContent = META[id].title;
    document.getElementById('sctl').textContent = 'Управление: ' + META[id].ctl;
    frame.srcdoc = GAMES[id];
    play.classList.add('hide-grid'); stage.classList.add('on');
    stage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    frame.addEventListener('load', function f() { frame.removeEventListener('load', f); try { frame.contentWindow.focus(); } catch (e) {} });
  }
  function close() {
    setFull(false); frame.srcdoc = ''; stage.classList.remove('on'); play.classList.remove('hide-grid'); current = null;
  }
  var holder = document.createComment('stage');
  var FS_ICON = '<svg viewBox="0 0 24 24"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>';
  var FS_EXIT = '<svg viewBox="0 0 24 24"><path d="M9 4v5H4M15 4v5h5M9 20v-5H4M15 20v-5h5"/></svg>';
  function nativeFs() { return document.fullscreenElement || document.webkitFullscreenElement; }
  function setFull(on) {
    var isFull = stage.classList.contains('full');
    if (on === isFull) return;
    if (on) {
      /* position:fixed внутри блока с backdrop-filter считается от блока, а не от окна, поэтому выносим сцену в body */
      stage.parentNode.insertBefore(holder, stage);
      document.body.appendChild(stage);
      stage.classList.add('full');
      var rq = stage.requestFullscreen || stage.webkitRequestFullscreen;
      if (rq) { try { var r = rq.call(stage); if (r && r.catch) r.catch(function () {}); } catch (e) {} }
    } else {
      stage.classList.remove('full');
      if (holder.parentNode) holder.parentNode.insertBefore(stage, holder), holder.parentNode.removeChild(holder);
      if (nativeFs()) { var ex = document.exitFullscreen || document.webkitExitFullscreen; try { var r2 = ex.call(document); if (r2 && r2.catch) r2.catch(function () {}); } catch (e) {} }
    }
    document.body.classList.toggle('noscroll', on);
    var b = document.getElementById('full');
    b.setAttribute('aria-label', on ? 'Выйти из полноэкранного режима' : 'На весь экран');
    b.innerHTML = on ? FS_EXIT : FS_ICON;
    try { frame.contentWindow.focus(); } catch (e) {}
  }
  function onFsChange() { if (!nativeFs() && stage.classList.contains('full') && stage.dataset.nat === '1') setFull(false); }
  document.addEventListener('fullscreenchange', function () { if (nativeFs()) stage.dataset.nat = '1'; else onFsChange(), stage.dataset.nat = ''; });
  document.addEventListener('webkitfullscreenchange', function () { if (nativeFs()) stage.dataset.nat = '1'; else onFsChange(), stage.dataset.nat = ''; });
 
  Array.prototype.forEach.call(document.querySelectorAll('.gcard'), function (b) { b.addEventListener('click', function () { open(b.getAttribute('data-game')); }); });
  document.getElementById('back').addEventListener('click', close);
  document.getElementById('restart').addEventListener('click', function () { if (current) { frame.srcdoc = ''; setTimeout(function () { frame.srcdoc = GAMES[current]; }, 30); } });
  document.getElementById('full').addEventListener('click', function () { setFull(!stage.classList.contains('full')); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && stage.classList.contains('full')) setFull(false); });
})();
</script>
</body>
</html>