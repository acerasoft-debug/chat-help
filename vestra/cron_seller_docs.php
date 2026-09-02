<?php
/**
 * VESTRA — satici belge suresi (cron / CLI).
 *
 * Operator karari, 2 Eylul 2026: "Satici bolumunde ilk urun eklensin,
 * sonrasinda saticiya belgeleri eklemesi icin sure verilsin -- 3 gun gibi;
 * eger yuklemezse suspend olsun."
 *
 * Akis (karar auth_seller_doc_grace(), inc/auth.php -- burada YENIDEN
 * yazilmaz):
 *   unstamped -> ilani var, saati yok: damgala + "3 gun icinde" mektubu
 *                (ilk ilan seller-add.php'de zaten damgalar; burasi kural
 *                yururluge girdiginde ZATEN ilani olan saticilar icin)
 *   due_soon  -> son 24 saat: hatirlatma (bir kez)
 *   expired   -> aski: status=suspended, suspend_reason=docs, ilanlar
 *                katalogdan cekilir (vestra_live_listings), saticiya mektup
 *   suspended + belge tam -> operatore "acmaniz bekleniyor" satiri
 *
 * ILK MEKTUP GITMEDEN ASKI YOK: uyari mektubu hic gonderilememisse (posta
 * hatasi) hesap askiya alinmaz, mektup yeniden denenir. Uyarilmamis bir
 * saticiyi askiya almak kural degil tuzak olurdu.
 *
 * SESSIZ OLDUGUNDA OPERATORE YAZMAZ: yalnizca aski ya da onay bekleyen
 * varsa mektup gider (bkz. cron_pending_accounts.php, ayni ilke).
 *
 * Zamanlama: SUNUCU crontab'i (06:50 UTC), deploy-vestra.yml idempotent kurar.
 * Kullanim: php cron_seller_docs.php [--dry-run]
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/products.php';
require_once __DIR__ . '/inc/seller_docs.php';

$DRY = in_array('--dry-run', $argv ?? [], true);
$now = time();

$mask = function (string $e): string {
    $at = strrpos($e, '@'); if ($at === false) return $e;
    $lo = substr($e, 0, $at);
    return mb_substr($lo, 0, 2) . str_repeat('*', max(0, mb_strlen($lo) - 2)) . substr($e, $at);
};
$label = fn(array $a) => (trim((string)($a['company'] ?? '')) ?: trim((string)($a['name'] ?? '')) ?: '(no name)');
$reload = fn(string $uid) => vestra_seller_docs_account($uid);

$acts = ['stamped'=>[], 'reminded'=>[], 'suspended'=>[], 'awaiting'=>[], 'running'=>0, 'clear'=>0, 'nolisting'=>0];

foreach (auth_accounts() as $a) {
    if (($a['type'] ?? '') !== 'seller') continue;
    $st = (string)($a['status'] ?? '');
    if ($st === 'deleted' || $st === 'pending_email' || empty($a['email_verified'])) continue;
    $uid = (string)($a['id'] ?? '');
    if ($uid === '') continue;

    $listings = vestra_seller_listings($uid);
    $g = auth_seller_doc_grace($a, $listings, $now);

    if ($st === 'suspended') {
        /* Belge askisindaki satici belgesini yukledi: kapiyi OPERATOR acar
           (KURAL 2 -- yukleme onay degil). Buradan yalnizca haber verilir. */
        if (auth_suspended_for_docs($a) && !$g['missing']) $acts['awaiting'][] = $a;
        continue;
    }

    switch ($g['phase']) {
        case 'clear':     $acts['clear']++;     break;
        case 'none':      $acts['nolisting']++; break;

        case 'unstamped':
            printf("  BASLAT   %-30s %-12s ilan=%d eksik=%s\n", $mask((string)$a['email']), mb_substr((string)($a['country'] ?? ''), 0, 12), count($listings), implode('+', $g['missing']));
            if (!$DRY) {
                auth_seller_doc_grace_start($uid);
                $a2 = $reload($uid);
                if ($a2) vestra_seller_docs_notify($a2, auth_seller_doc_grace($a2, $listings, $now), 'notice');
            }
            $acts['stamped'][] = $a;
            break;

        case 'running':
            $acts['running']++;
            /* Ilk mektup gitmemis (posta hatasi): yeniden dene. */
            if (empty($a['doc_grace_notice_at'])) {
                printf("  MEKTUP   %-30s ilk uyari eksikti, yeniden gonderiliyor (%d gun kaldi)\n", $mask((string)$a['email']), (int)$g['days_left']);
                if (!$DRY) vestra_seller_docs_notify($a, $g, 'notice');
            }
            break;

        case 'due_soon':
            if (empty($a['doc_grace_notice_at'])) {
                printf("  MEKTUP   %-30s ilk uyari eksikti, gonderiliyor (son gun)\n", $mask((string)$a['email']));
                if (!$DRY) vestra_seller_docs_notify($a, $g, 'notice');
            } elseif (empty($a['doc_grace_reminder_at'])) {
                printf("  HATIRLAT %-30s son 24 saat, eksik=%s\n", $mask((string)$a['email']), implode('+', $g['missing']));
                if (!$DRY) vestra_seller_docs_notify($a, $g, 'reminder');
                $acts['reminded'][] = $a;
            }
            break;

        case 'expired':
            if (empty($a['doc_grace_notice_at'])) {
                /* Uyarilmadan aski yok. */
                printf("  MEKTUP   %-30s sure doldu ama ilk uyari HIC gitmemis -> once mektup, aski yok\n", $mask((string)$a['email']));
                if (!$DRY) vestra_seller_docs_notify($a, $g, 'notice');
                break;
            }
            printf("  ASKI     %-30s %-12s sure %s doldu, eksik=%s\n", $mask((string)$a['email']), mb_substr((string)($a['country'] ?? ''), 0, 12), gmdate('Y-m-d', (int)$g['deadline']), implode('+', $g['missing']));
            if (!$DRY) {
                auth_update($uid, ['status'=>'suspended', 'suspend_reason'=>'docs', 'doc_grace_suspended_at'=>date('c')]);
                $a2 = $reload($uid);
                if ($a2) vestra_seller_docs_notify($a2, $g, 'suspended');
            }
            $acts['suspended'][] = $a;
            break;
    }
}

printf("satici: belgesi tam=%d | ilani yok=%d | sure isliyor=%d | baslatildi=%d | hatirlatildi=%d | ASKIYA ALINDI=%d | askida+belge geldi=%d%s\n",
       $acts['clear'], $acts['nolisting'], $acts['running'], count($acts['stamped']), count($acts['reminded']),
       count($acts['suspended']), count($acts['awaiting']), $DRY ? '   (KURU KOSU: hicbir sey yazilmadi, gonderilmedi)' : '');
foreach ($acts['awaiting'] as $a) printf("  BEKLIYOR %-30s askida, belgesini yukledi -> Admin > Users > Activate\n", $mask((string)$a['email']));

if (!$acts['suspended'] && !$acts['awaiting']) { echo "operatore mektup yok (aski ve bekleyen yok).\n"; exit(0); }

$subj = [];
if ($acts['suspended']) $subj[] = count($acts['suspended']).' seller(s) paused — documents missing';
if ($acts['awaiting'])  $subj[] = count($acts['awaiting']).' paused seller(s) sent documents — activate?';
$subject = 'VESTRA — '.implode(' · ', $subj);

$body = '';
if ($acts['suspended']) {
    $body .= "These sellers did not supply their documents within ".VESTRA_SELLER_DOC_GRACE_DAYS." days of their first listing.\n"
           . "Their accounts are now PAUSED and their listings are hidden from the catalogue. Each has been e-mailed.\n\n";
    foreach ($acts['suspended'] as $a) {
        $body .= sprintf("  %s — %s (%s) · missing: %s\n", (string)$a['email'], $label($a), (string)($a['country'] ?? 'country not set'),
                         implode(' + ', auth_missing_doc_types($a)));
    }
    $body .= "\nWhen their documents arrive (upload or e-mail), attach/review them and press Activate — the account gets a fresh ".VESTRA_SELLER_DOC_GRACE_DAYS."-day window if anything is still open.\n\n";
}
if ($acts['awaiting']) {
    $body .= "These paused sellers have now supplied their documents and are WAITING FOR YOU:\n\n";
    foreach ($acts['awaiting'] as $a) {
        $body .= sprintf("  %s — %s (%s)\n", (string)$a['email'], $label($a), (string)($a['country'] ?? 'country not set'));
    }
    $body .= "\nReview the files under Admin ▸ Documents, then Admin ▸ Users ▸ Activate to put their listings back on.\n\n";
}
$body .= "Users: https://vestrasales.com/admin?tab=users\nDocuments: https://vestrasales.com/admin?tab=documents\n\n"
       . "This message is only sent on days when something happened.\n";

$to = (string)vestra_cfg('ops_email', 'acerasoft@gmail.com');
if ($DRY) { echo "\n— DRY RUN — gonderilecek adres: ".$mask($to)."\n--- konu ---\n$subject\n"; exit(0); }
$ok = vestra_send_mail($to, $subject, $body, '', 'VESTRA');
echo $ok ? "operator uyarisi gonderildi -> ".$mask($to)."\n" : "OPERATOR UYARISI GONDERILEMEDI\n";
exit($ok ? 0 : 1);
