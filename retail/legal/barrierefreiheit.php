<?php
/**
 * Barrierefreiheitserklärung (BFSG)
 * ---------------------------------
 * Barrierefreiheitsstärkungsgesetz 28.06.2025'ten beri B2C e-ticareti kapsıyor.
 * Bu sayfa şablon değil: sitede GERÇEKTEN yapılmış olan şeyleri (klavye
 * erişimi, kontrast, JS'siz çalışma) ve bilinen eksikleri sayıyor.
 */

declare(strict_types=1);

require_once __DIR__ . '/_doc.php';

$email = trim((string)(vr_config('company')['email'] ?? ''));
$mail  = $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[E-Mail]</em>';

vr_doc_page('barrierefreiheit', 'legal_accessibility', '2026-08-01', compact('email', 'mail'));
