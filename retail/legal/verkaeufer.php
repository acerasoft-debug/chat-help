<?php
/**
 * Verkäuferbedingungen
 * --------------------
 * Satıcı ile platform arasındaki ilişki. Komisyon, ödeme akışı, yasak ürünler,
 * privat/gewerblich ayrımı, DSA yükümlülükleri, fesih.
 * Ücret oranları YAPILANDIRMADAN okunuyor: sözleşme metni ile faturalanan oran
 * asla ayrışmasın.
 */

declare(strict_types=1);

require_once __DIR__ . '/_doc.php';

$brand = (string)vr_config('brand');
$c     = vr_config('company');
$co    = trim((string)($c['legal_name'] ?? ''));
$email = trim((string)($c['email'] ?? ''));
$feeB  = (int)vr_config('fee_bps_business', 1200) / 100;
$feeP  = (int)vr_config('fee_bps_private', 900) / 100;
$feeV  = (int)vr_config('fee_bps_outlet', 1500) / 100;
$fixed = vr_money((int)vr_config('fee_fixed_cents', 35));

vr_doc_page('verkaeufer', 'legal_seller_terms', '2026-08-01', compact('brand', 'c', 'co', 'email', 'feeB', 'feeP', 'feeV', 'fixed'));
