<?php
/**
 * VESTRA — bekleyen hesap uyarisi (cron / CLI).
 *
 * NEDEN VAR. Fiyat kapisini operator onayi aciyor (KURAL 2:
 * auth_prices_unlocked -> auth_user_approved). Ama kayit oldugunda operatore
 * HICBIR sey haber vermiyordu: check-registrations.yml yalnizca elle
 * tetikleniyor. Sonuc, 1 Eylul 2026'da olculdu -- 7 hesap onay bekliyordu ve
 * BIRI belgesini yukleyip onay da almisken hala kilitliydi. O alici sitede
 * fiyat goremiyor, sepeti onaylayamiyor ve bunu kimse bilmiyor.
 *
 * Kapiyi bu betik YENIDEN TANIMLAMAZ, auth_prices_unlocked()'i cagirir. Bu
 * depoda alti kez kontrolun kendisi yanlis yere bakti; kapinin ikinci bir
 * kopyasini yazmak yedincisi olurdu.
 *
 * SESSIZ OLDUGUNDA HIC YAZMAZ. Bekleyen yoksa mektup gitmez -- her sabah
 * "0 bekleyen" yazan bir uyari, okunmamayi ogretir.
 *
 * Zamanlama: SUNUCU crontab'i (06:40 UTC gunluk), deploy-vestra.yml her push'ta
 * idempotent kurar (VESTRA-SWEEP etiketli satirlar). GitHub Actions DEGIL:
 * schedule yalnizca varsayilan daldaki workflow'lar icin atesleniyor.
 *
 * Kullanim:  php cron_pending_accounts.php [--dry-run]
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/notify.php';

$DRY = in_array('--dry-run', $argv ?? [], true);

/* Log public_html DISINDA tutuluyor ama yine de MASKELI yaziyoruz; tam adres
   yalnizca operatorun kendi kutusuna giden mektupta. */
$mask = function (string $e): string {
    $at = strrpos($e, '@'); if ($at === false) return $e;
    $lo = substr($e, 0, $at);
    return mb_substr($lo, 0, 2) . str_repeat('*', max(0, mb_strlen($lo) - 2)) . substr($e, $at);
};

$waiting = [];      // onay bekleyen: e-posta dogrulanmis ama kapi kapali
$unverified = 0;    // e-postasini hic dogrulamamis: operatorun yapacagi bir sey yok

foreach (auth_accounts() as $a) {
    $st = (string)($a['status'] ?? '');
    if ($st === 'suspended' || $st === 'deleted') continue;
    if (auth_prices_unlocked($a)) continue;                 // kapi ACIK -> beklemiyor

    $email = trim((string)($a['email'] ?? ''));
    if ($email === '') continue;
    if ($st === 'pending_email' || empty($a['email_verified'])) { $unverified++; continue; }

    $since = strtotime((string)($a['created'] ?? '')) ?: time();
    $days  = max(0, (int)floor((time() - $since) / 86400));

    /* En keskin vaka: belgesini YUKLEMIS (hatta onaylanmis) ama hesap hala
       kapali. O musteri istenen her seyi yapti ve hala fiyat goremiyor --
       listede en uste cikmali. */
    $doc = function_exists('auth_trade_doc_status') ? auth_trade_doc_status($a) : '';

    $waiting[] = [
        'email'   => $email,
        'company' => trim((string)($a['company'] ?? '')) ?: trim((string)($a['name'] ?? '')),
        'type'    => (string)($a['type'] ?? 'buyer'),
        'country' => (string)($a['country'] ?? ''),
        'doc'     => $doc,
        'days'    => $days,
        'urgent'  => in_array($doc, ['uploaded', 'approved'], true),
    ];
}

/* Once belgesini vermis olanlar, sonra en uzun bekleyen. */
usort($waiting, function ($x, $y) {
    if ($x['urgent'] !== $y['urgent']) return $x['urgent'] ? -1 : 1;
    return $y['days'] <=> $x['days'];
});

printf("bekleyen: %d | e-postasi dogrulanmamis (islem yok): %d\n", count($waiting), $unverified);
foreach ($waiting as $w) {
    printf("  %-28s %-6s %-12s belge=%-9s %d gun%s\n",
        $mask($w['email']), $w['type'], mb_substr($w['country'], 0, 12),
        $w['doc'] !== '' ? $w['doc'] : '(istek yok)', $w['days'],
        $w['urgent'] ? '  <-- BELGESINI VERDI, HALA KAPALI' : '');
}

if (!$waiting) { echo "bekleyen yok — mektup gonderilmedi.\n"; exit(0); }

$urgent = array_filter($waiting, fn($w) => $w['urgent']);
$subject = 'VESTRA — ' . count($waiting) . ' hesap onay bekliyor'
         . ($urgent ? ' (' . count($urgent) . ' tanesi belgesini vermis)' : '');

$body = count($waiting) . " account(s) are waiting for your approval. Until you approve them they\n"
      . "cannot see trade prices, download the line sheet or place an order.\n\n";
if ($urgent) {
    $body .= "These have already supplied their trade document and are still locked:\n\n";
    foreach ($urgent as $w) {
        $body .= sprintf("  %s — %s (%s, %s) · document %s · waiting %d day(s)\n",
            $w['email'], $w['company'] !== '' ? $w['company'] : '(no company name)',
            $w['type'], $w['country'] !== '' ? $w['country'] : 'country not set',
            $w['doc'], $w['days']);
    }
    $body .= "\n";
}
$rest = array_filter($waiting, fn($w) => !$w['urgent']);
if ($rest) {
    $body .= "Waiting, document not uploaded yet:\n\n";
    foreach ($rest as $w) {
        $body .= sprintf("  %s — %s (%s, %s) · document %s · waiting %d day(s)\n",
            $w['email'], $w['company'] !== '' ? $w['company'] : '(no company name)',
            $w['type'], $w['country'] !== '' ? $w['country'] : 'country not set',
            $w['doc'] !== '' ? $w['doc'] : 'no request', $w['days']);
    }
    $body .= "\n";
}
$body .= "Approve them here: https://vestrasales.com/admin?tab=users\n\n"
       . "A document is not required to open an account — your approval is what opens it.\n"
       . "This message is only sent on days when somebody is actually waiting.\n";

$to = (string)vestra_cfg('ops_email', 'acerasoft@gmail.com');
if ($DRY) { echo "\n— DRY RUN — gonderilecek adres: " . $mask($to) . "\n--- konu ---\n$subject\n"; exit(0); }

$ok = vestra_send_mail($to, $subject, $body, '', 'VESTRA');
echo $ok ? "uyari gonderildi -> " . $mask($to) . "\n" : "UYARI GONDERILEMEDI\n";
exit($ok ? 0 : 1);
