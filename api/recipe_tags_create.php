<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$raw     = file_get_contents('php://input');
$payload = json_decode($raw, true) ?: $_POST;
$name    = trim((string)($payload['name'] ?? ''));

if ($name === '') {
    echo json_encode(['ok' => false, 'error' => 'Пустое имя']); exit;
}

// Транслит для slug
if (!function_exists('transliterate')) {
    function transliterate($s) {
        $t = ['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
              'А'=>'A','Б'=>'B','В'=>'V','Г'=>'G','Д'=>'D','Е'=>'E','Ё'=>'Yo','Ж'=>'Zh','З'=>'Z','И'=>'I','Й'=>'Y','К'=>'K','Л'=>'L','М'=>'M','Н'=>'N','О'=>'O','П'=>'P','Р'=>'R','С'=>'S','Т'=>'T','У'=>'U','Ф'=>'F','Х'=>'Kh','Ц'=>'Ts','Ч'=>'Ch','Ш'=>'Sh','Щ'=>'Sch','Ъ'=>'','Ы'=>'Y','Ь'=>'','Э'=>'E','Ю'=>'Yu','Я'=>'Ya'];
        return strtr($s, $t);
    }
}

$slug = transliterate($name);
$slug = mb_strtolower($slug);
$slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug);
$slug = trim(preg_replace('/-+/', '-', $slug), '-');

if ($slug === '') {
    echo json_encode(['ok' => false, 'error' => 'Неверное имя']); exit;
}

// Проверка на существующий
$existing = db_one('SELECT * FROM recipe_tags WHERE slug = ?', [$slug]);
if ($existing) {
    echo json_encode(['ok' => true, 'tag' => [
        'id'        => (int)$existing['id'],
        'slug'      => $existing['slug'],
        'name'      => $existing['name'],
        'icon_svg'  => $existing['icon_svg'] ?? null,
        'tag_group' => $existing['tag_group'] ?? 'other',
    ]]); exit;
}

db_exec(
    'INSERT INTO recipe_tags (slug, name, tag_group, is_active, sort_order) VALUES (?, ?, ?, 1, 100)',
    [$slug, $name, 'other']
);
$id = (int)db()->lastInsertId();

echo json_encode(['ok' => true, 'tag' => [
    'id'        => $id,
    'slug'      => $slug,
    'name'      => $name,
    'icon_svg'  => null,
    'tag_group' => 'other',
]]);