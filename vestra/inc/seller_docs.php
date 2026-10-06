<?php
/**
 * VESTRA — satici belge suresi: MEKTUP ve DAMGA yardimcilari.
 *
 * Karar auth_seller_doc_grace()'te (inc/auth.php, saf fonksiyon, testli).
 * Burasi yalnizca "ne zaman hangi mektup" ve damgalar. Iki cagiran var ve
 * ikisi AYNI fonksiyonu kullanir: seller-add.php (ilk ilan -> saat baslar,
 * ilk mektup gider) ve cron_seller_docs.php (hatirlatma, aski). Ayri ayri
 * yazilsalar mektuplar ve damgalar ayrisirdi.
 */
require_once __DIR__.'/auth.php';
require_once __DIR__.'/products.php';
require_once __DIR__.'/notify.php';
require_once __DIR__.'/email_templates.php';

function vestra_seller_doc_labels(array $types): array {
    $names = auth_doc_types();
    return array_map(fn($t) => (string)($names[$t] ?? $t), $types);
}

/* $kind: notice | reminder | suspended. Mektup GITTIYSE damga yazilir;
   gitmediyse yazilmaz, ertesi kosuda yeniden denenir. $dry: ne yazar ne
   gonderir, yalnizca "gonderilirdi" der. */
function vestra_seller_docs_notify(array $acc, array $grace, string $kind, bool $dry = false): bool {
    $email = trim((string)($acc['email'] ?? ''));
    if ($email === '' || empty($acc['email_verified'])) return false;
    $name     = trim((string)($acc['name'] ?? '')) ?: (trim((string)($acc['company'] ?? '')) ?: 'there');
    $labels   = vestra_seller_doc_labels((array)($grace['missing'] ?? []));
    $deadline = !empty($grace['deadline']) ? gmdate('j F Y', (int)$grace['deadline']) : '';
    if ($kind === 'suspended') {
        [$s, $b, $o] = vestra_tpl_seller_docs_suspended($name, $labels);
    } else {
        [$s, $b, $o] = vestra_tpl_seller_docs_due($name, $labels, $deadline, (int)($grace['days_left'] ?? 0), $kind === 'reminder');
    }
    if ($dry) return true;
    $ok = vestra_send_mail($email, $s, $b, '', '', null, '', $o);
    if ($ok) {
        $stamp = match($kind) { 'notice' => 'doc_grace_notice_at', 'reminder' => 'doc_grace_reminder_at', default => 'doc_grace_suspend_mail_at' };
        auth_update((string)($acc['id'] ?? ''), [$stamp => date('c')]);
    }
    return $ok;
}

function vestra_seller_docs_account(string $uid): ?array {
    foreach (auth_accounts() as $a) { if (($a['id'] ?? '') === $uid) return $a; }
    return null;
}

/* ILK ILAN kaydedildi: saati baslat ve "3 gun icinde" mektubunu gonder.
   Belgeler tamamsa, satici degilse ya da saat zaten isliyorsa hicbir sey
   yapmaz -- ikinci, ucuncu ilan saati YENIDEN baslatmaz. */
function vestra_seller_docs_kickoff(string $uid): void {
    $acc = vestra_seller_docs_account($uid);
    if (!$acc || ($acc['type'] ?? '') !== 'seller') return;
    if (!auth_missing_doc_types($acc)) return;
    if (!empty($acc['doc_grace_start'])) return;
    auth_seller_doc_grace_start($uid);
    $acc = vestra_seller_docs_account($uid);
    if (!$acc) return;
    $grace = auth_seller_doc_grace($acc, vestra_seller_listings($uid));
    if (in_array($grace['phase'], ['running', 'due_soon'], true)) vestra_seller_docs_notify($acc, $grace, 'notice');
}
