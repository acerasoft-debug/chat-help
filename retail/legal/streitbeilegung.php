<?php
/**
 * Streitbeilegung + DSA iletişim noktası + bildirim usulü (Art. 16 DSA)
 * Pazaryeri işletmecisinin bildirim/şikayet mekanizmasını YAYINLAMASI gerekiyor;
 * bu sayfa onu somut bir akış olarak veriyor.
 */

declare(strict_types=1);

require_once __DIR__ . '/_doc.php';

$email = trim((string)(vr_config('company')['email'] ?? ''));
$mail  = $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[E-Mail]</em>';

vr_doc_page('streitbeilegung', 'legal_disputes', '2026-08-01', compact('email', 'mail'));
