<?php
/**
 * Impressum — §5 DDG (eski TMG §5), §18 MStV
 * Bir US LLC olarak Almanya'ya yönelik satış yapıldığında da Impressum
 * zorunluluğu geçerli: belirleyici olan hizmetin Almanya'ya yöneltilmiş
 * olması, şirketin nerede kurulduğu değil.
 */

declare(strict_types=1);

require_once __DIR__ . '/_doc.php';

$c     = vr_config('company');
$email = trim((string)($c['email'] ?? ''));

vr_doc_page('impressum', 'legal_imprint', '2026-08-01', compact('c', 'email'));
