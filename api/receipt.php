<?php
/**
 * GET /api/receipt.php?id=123 — скачать PDF-квитанцию своей оплаты.
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/pdf_receipt.php';

require_login();
$id    = (int)($_GET['id'] ?? 0);
$email = current_user()['email'];

$sub = db_one('SELECT * FROM subscriptions WHERE id=? AND user_email=?', [$id, $email]);
if (!$sub) { http_response_code(404); die('Квитанция не найдена'); }
if (empty($sub['paid_at']) || !in_array($sub['status'], ['active', 'expired', 'refunded'], true)) {
    http_response_code(400); die('Квитанция доступна только для оплаченных подписок');
}

$pdf  = generate_receipt_pdf($sub);
$name = 'cookai-receipt-' . ($sub['receipt_number'] ?: $id) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, no-cache');
echo $pdf;