<?php
/**
 * GigaChefAI — минималистичный генератор PDF-квитанций на чистом PHP.
 * Поддержка кириллицы через встроенный шрифт с кодировкой WinAnsi + escape.
 * Для простоты используем транслитерацию заголовков в латиницу там, где нужно,
 * а данные (суммы, даты, номера) — ASCII-safe.
 */
declare(strict_types=1);

class PdfReceipt
{
    private array $lines = [];   // [x, y, size, text, bold]
    private float $pageH = 842;  // A4 в pt
    private float $pageW = 595;

    public function text(float $x, float $y, string $s, int $size = 11, bool $bold = false): void
    {
        // y сверху вниз — переведём в PDF-координаты (снизу вверх)
        $this->lines[] = [$x, $this->pageH - $y, $size, $s, $bold];
    }

    public function line(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->lines[] = ['LINE', $x1, $this->pageH - $y1, $x2, $this->pageH - $y2];
    }

    private function esc(string $s): string
    {
        // Транслитерация кириллицы (built-in Helvetica не имеет кириллических глифов)
        $s = self::translit($s);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }

    public static function translit(string $s): string
    {
        $map = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z',
            'и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r',
            'с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch',
            'ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
            'А'=>'A','Б'=>'B','В'=>'V','Г'=>'G','Д'=>'D','Е'=>'E','Ё'=>'E','Ж'=>'Zh','З'=>'Z',
            'И'=>'I','Й'=>'Y','К'=>'K','Л'=>'L','М'=>'M','Н'=>'N','О'=>'O','П'=>'P','Р'=>'R',
            'С'=>'S','Т'=>'T','У'=>'U','Ф'=>'F','Х'=>'H','Ц'=>'Ts','Ч'=>'Ch','Ш'=>'Sh','Щ'=>'Sch',
            'Ъ'=>'','Ы'=>'Y','Ь'=>'','Э'=>'E','Ю'=>'Yu','Я'=>'Ya',
            '₽'=>' RUB', '№'=>'No.', '✓'=>'', '✨'=>'', '—'=>'-', '–'=>'-', '«'=>'"', '»'=>'"',
        ];
        return strtr($s, $map);
    }

    /** Возвращает бинарную строку PDF */
    public function build(): string
    {
        $content = "BT\n";
        $graphics = '';
        foreach ($this->lines as $l) {
            if ($l[0] === 'LINE') {
                $graphics .= sprintf("%.2f %.2f m %.2f %.2f l S\n", $l[1], $l[2], $l[3], $l[4]);
                continue;
            }
            [$x, $y, $size, $s, $bold] = $l;
            $font = $bold ? '/F2' : '/F1';
            $content .= sprintf("%s %d Tf\n1 0 0 1 %.2f %.2f Tm\n(%s) Tj\n", $font, $size, $x, $y, $this->esc($s));
        }
        $content .= "ET\n";
        $stream = "0.15 w\n" . $graphics . $content;

        $objects = [];
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objects[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$this->pageW} {$this->pageH}] "
                    . "/Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 4 0 R >>";
        $objects[4] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
        $objects[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objects[6] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj\n{$body}\nendobj\n";
        }
        $xrefPos = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";
        return $pdf;
    }
}

/**
 * Формирует PDF-квитанцию по записи подписки.
 */
function generate_receipt_pdf(array $sub): string
{
    $pdf = new PdfReceipt();

    $plan   = ($sub['plan'] ?? 'monthly') === 'yearly' ? 'Годовая подписка' : 'Месячная подписка';
    $no     = $sub['receipt_number'] ?: ('CK-' . str_pad((string)$sub['id'], 6, '0', STR_PAD_LEFT));
    $date   = $sub['paid_at'] ? date('d.m.Y H:i', strtotime($sub['paid_at'])) : date('d.m.Y');
    $amount = number_format((float)$sub['amount'], 2, '.', ' ');
    $orig   = $sub['original_amount'] ? number_format((float)$sub['original_amount'], 2, '.', ' ') : null;

    $y = 60;
    $pdf->text(60, $y, 'GigaChefAI Pro', 22, true); $y += 8;
    $pdf->text(60, $y + 14, 'Квитанция об оплате подписки', 12); $y += 40;

    $pdf->line(60, $y, 535, $y); $y += 24;

    $pdf->text(60, $y, 'Квитанция №:', 11, true);
    $pdf->text(220, $y, $no, 11); $y += 22;

    $pdf->text(60, $y, 'Дата оплаты:', 11, true);
    $pdf->text(220, $y, $date, 11); $y += 22;

    $pdf->text(60, $y, 'Плательщик:', 11, true);
    $pdf->text(220, $y, $sub['user_email'], 11); $y += 22;

    $pdf->text(60, $y, 'Тариф:', 11, true);
    $pdf->text(220, $y, $plan, 11); $y += 22;

    if (!empty($sub['promo_code'])) {
        $pdf->text(60, $y, 'Промокод:', 11, true);
        $pdf->text(220, $y, $sub['promo_code'], 11); $y += 22;
    }

    $y += 8;
    $pdf->line(60, $y, 535, $y); $y += 24;

    if ($orig) {
        $pdf->text(60, $y, 'Стоимость без скидки:', 11);
        $pdf->text(400, $y, $orig . ' RUB', 11); $y += 22;
        $disc = number_format((float)$sub['original_amount'] - (float)$sub['amount'], 2, '.', ' ');
        $pdf->text(60, $y, 'Скидка по промокоду:', 11);
        $pdf->text(400, $y, '-' . $disc . ' RUB', 11); $y += 22;
    }

    $pdf->text(60, $y, 'ИТОГО ОПЛАЧЕНО:', 13, true);
    $pdf->text(400, $y, $amount . ' RUB', 13, true); $y += 30;

    if ((float)($sub['refunded_amount'] ?? 0) > 0) {
        $ref = number_format((float)$sub['refunded_amount'], 2, '.', ' ');
        $pdf->text(60, $y, 'Возвращено:', 11, true);
        $pdf->text(400, $y, $ref . ' RUB', 11, true); $y += 22;
    }

    $y = 780;
    $pdf->line(60, $y, 535, $y); $y += 16;
    $pdf->text(60, $y, 'Документ сформирован автоматически сервисом GigaChefAI. Не требует печати.', 8);
    $pdf->text(60, $y + 12, 'Оплата произведена через платёжный сервис ЮKassa.', 8);

    return $pdf->build();
}