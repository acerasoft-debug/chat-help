<?php
/**
 * Datenschutzerklärung (DSGVO)
 * ----------------------------
 * Bu metin sitenin GERÇEKTE yaptığı işleme göre yazıldı — kopyala-yapıştır bir
 * şablon değil. Bu yüzden burada olmayan şeyler de açıkça yazılı: analitik yok,
 * pazarlama pikseli yok, dış CDN yok, Google Fonts yok. Site bu nedenle rıza
 * bandı gerektirmiyor (TDDDG §25 Abs. 2).
 */

declare(strict_types=1);

require_once __DIR__ . '/_doc.php';

$c     = vr_config('company');
$co    = trim((string)($c['legal_name'] ?? ''));
$email = trim((string)($c['email'] ?? ''));
$mail  = vr_mail_settings();

vr_doc_page('datenschutz', 'legal_privacy', '2026-08-01', compact('c', 'co', 'email', 'mail'));
