<?php
/**
 * VESTRA — acik talep (dispute) hatirlatmasi (cron / CLI).
 *
 * NEDEN VAR. SSS disputes/1 ve returns/9: "ekibimiz 2 is gunu icinde inceler".
 * Talep acildiginda operatore tek bir mektup gider (vestra_claim_notify); o
 * mektup kacirilirsa talep sessizce bekler, alicinin parasi escrow'da tutulur
 * ve kimse haberdar olmaz -- cron_pending_accounts.php'nin bekleyen hesaplar
 * icin oldugu KURAL 2c dersinin aynisi: bekleyen sey SESSIZ kalmaz.
 *
 * Karari bu betik YENIDEN TANIMLAMAZ: acik talep listesi vestra_claims_open()'dan,
 * inceleme suresi VESTRA_CLAIM_REVIEW_BDAYS'ten, is gunu hesabi
 * vestra_business_days_after()'dan gelir.
 *
 * SESSIZ OLDUGUNDA HIC YAZMAZ. Suresi dolmus talep yoksa mektup gitmez --
 * her sabah "0 bekleyen" yazan bir uyari, okunmamayi ogretir. Acik ama henuz
 * suresi dolmamis talepler yalnizca kutuge yazilir.
 *
 * Zamanlama: SUNUCU crontab'i (07:10 UTC gunluk), deploy-vestra.yml her push'ta
 * idempotent kurar (VESTRA-SWEEP etiketli satir). GitHub Actions DEGIL:
 * schedule yalnizca varsayilan daldaki workflow'lar icin atesleniyor.
 *
 * Kullanim:  php cron_claims.php [--dry-run]
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/inc/claims.php';
require_once __DIR__ . '/inc/notify.php';

$DRY = in_array('--dry-run', $argv ?? [], true);
$now = time();

$open = vestra_claims_open();
$due  = [];   // inceleme suresini gecmis
$rows = [];
foreach ($open as $ref => $c) {
    $opened = strtotime((string)($c['opened_at'] ?? '')) ?: $now;
    $reviewBy = vestra_business_days_after($opened, VESTRA_CLAIM_REVIEW_BDAYS);
    $row = [
        'ref'      => $ref,
        'claim'    => (string)($c['claim_ref'] ?? ''),
        'reason'   => vestra_claim_reasons()[$c['reason'] ?? ''] ?? '?',
        'files'    => count((array)($c['files'] ?? [])),
        'days'     => max(0, (int)floor(($now - $opened) / 86400)),
        'overdue'  => $now > $reviewBy,
        'review_by'=> $reviewBy,
    ];
    $rows[] = $row;
    if ($row['overdue']) $due[] = $row;
}
usort($rows, fn($a, $b) => $b['days'] <=> $a['days']);

/* Kutuk: ref/talep no/sebep/gun -- kisiye ait hicbir alan yok (halka acik
   Actions kanaryasi bu ciktiyi basiyor). */
printf("acik talep: %d | inceleme suresi dolmus: %d\n", count($rows), count($due));
foreach ($rows as $r) {
    printf("  %s %-10s %-12s %-18s %d dosya  %d gun%s\n",
        $r['overdue'] ? 'GECIKMIS' : 'acik    ', $r['ref'], $r['claim'], $r['reason'], $r['files'], $r['days'],
        $r['overdue'] ? '' : '  (inceleme: '.date('D d M', $r['review_by']).')');
}

if (!$due) { echo "suresi dolmus talep yok — mektup gonderilmedi.\n"; exit(0); }

$subject = 'VESTRA — '.count($due).' claim(s) past the '.VESTRA_CLAIM_REVIEW_BDAYS.'-business-day review promise';
$body = count($due)." open claim(s) have passed the review window the FAQ promises to buyers\n"
      . "(".VESTRA_CLAIM_REVIEW_BDAYS." business days). Escrow funds on these orders, if any, are still held.\n\n";
foreach ($due as $r) {
    $body .= sprintf("  %s — order %s · %s · %d file(s) · open %d day(s)\n     https://vestrasales.com/admin?tab=orders&view=%s\n",
        $r['claim'], $r['ref'], $r['reason'], $r['files'], $r['days'], rawurlencode($r['ref']));
}
$body .= "\nResolve each one in Admin > Orders (the outcome you type is e-mailed to the buyer and posted\n"
       . "into the order thread). This message is only sent on days when a claim is actually overdue.\n";

$to = (string)vestra_cfg('ops_email', 'acerasoft@gmail.com');
if ($DRY) { echo "\n— DRY RUN — mektup gonderilmedi\n--- konu ---\n$subject\n"; exit(0); }

$ok = vestra_send_mail($to, $subject, $body, '', 'VESTRA');
echo $ok ? "hatirlatma gonderildi\n" : "HATIRLATMA GONDERILEMEDI\n";
exit($ok ? 0 : 1);
