<?php
/**
 * GET /api/holiday_shopping.php?id=123
 * Список покупок праздника в CSV. Только владелец.
 * UTF-8 с BOM и разделитель «;» — так Excel открывает кириллицу без танцев.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/holiday_shopping.php';

if (!is_logged_in()) {
    http_response_code(401);
    exit('Требуется вход');
}

$h = holiday_find_owned((int)($_GET['id'] ?? 0), (int)current_user()['id']);
if (!$h) {
    http_response_code(404);
    exit('Праздник не найден');
}

/** Защита от формул в Excel: ячейка не должна начинаться с = + - @ */
function csv_cell(string $s): string
{
    return ($s !== '' && strpos('=+-@', $s[0]) !== false) ? "'" . $s : $s;
}

$groups = hs_groups((int)$h['id']);
$fname  = 'shopping-' . preg_replace('/[^a-z0-9\-]/i', '', (string)$h['slug']) . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: private, no-store');

echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');
fputcsv($out, ['Категория', 'Продукт', 'Количество', 'Нужно для', 'Куплено'], ';', '"', '\\');

foreach ($groups as $g) {
    foreach ($g['items'] as $it) {
        fputcsv($out, [
            csv_cell((string)$g['label']),
            csv_cell((string)$it['name']),
            csv_cell((string)$it['text']),
            csv_cell(implode(', ', $it['used'])),
            '',
        ], ';', '"', '\\');
    }
}
fclose($out);
