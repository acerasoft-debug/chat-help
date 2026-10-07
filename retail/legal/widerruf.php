<?php
/**
 * Widerrufsbelehrung + Muster-Widerrufsformular
 * ---------------------------------------------
 * Metin, BGB Anlage 1/2'deki resmi örneğe sadık kalıyor — bu metinlerde
 * "yaratıcı" olmak doğrudan hukuki risk demek. Pazaryerine özgü tek fark:
 * cayma hakkının yalnızca GEWERBLICH satıcıya karşı geçerli olduğunun ayrıca
 * anlatılması.
 */

declare(strict_types=1);

require_once __DIR__ . '/_doc.php';

$c     = vr_config('company');
$co    = trim((string)($c['legal_name'] ?? ''));
$email = trim((string)($c['email'] ?? ''));
$wd    = (int)vr_config('withdrawal_days', 14);
$addr  = trim(implode(', ', array_filter([
    trim((string)($c['street'] ?? '')),
    trim(trim((string)($c['zip'] ?? '')) . ' ' . trim((string)($c['city'] ?? ''))),
    trim((string)($c['country'] ?? '')),
])));
$addrShown = $addr !== '' ? $addr : '[Anschrift]';

vr_doc_page('widerruf', 'legal_withdrawal', '2026-08-01', compact('c', 'co', 'email', 'wd', 'addr', 'addrShown'));
