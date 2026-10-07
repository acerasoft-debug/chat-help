<?php
/**
 * Rückgabe (freiwilliges Rückgaberecht + praktische Abwicklung)
 * Bilinçli olarak Widerrufsbelehrung'dan AYRI sayfa: birinde yasal metin,
 * burada "nasıl yapılır". İkisi karışınca yasal metin okunmaz hale geliyor.
 */

declare(strict_types=1);

require_once __DIR__ . '/_doc.php';

$days  = (int)vr_config('return_days', 30);
$wd    = (int)vr_config('withdrawal_days', 14);
$email = trim((string)(vr_config('company')['email'] ?? ''));

vr_doc_page('rueckgabe', 'legal_returns', '2026-08-01', compact('days', 'wd', 'email'));
