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

/* Bir hesabin HALA borclu oldugu zorunlu belgeler: auth_missing_doc_types()
   (inc/auth.php) -- tek dogruluk kaynagi auth_required_doc_types() (KURAL 2).
   Ayni fonksiyonu cron_seller_docs.php ve satici paneli de okur; burada
   ikinci bir liste yazmak, iki listenin ayrisacagi gun demektir. */
$missingDocs = fn(array $a): array => auth_missing_doc_types($a);

$waiting = [];      // onay bekleyen: e-posta dogrulanmis ama kapi kapali
$docless = [];      // kapisi ACIK ama hala zorunlu belge borclu (cogunlukla SATICI)
$unverified = 0;    // e-postasini hic dogrulamamis: operatorun yapacagi bir sey yok

foreach (auth_accounts() as $a) {
    $st = (string)($a['status'] ?? '');
    if ($st === 'suspended' || $st === 'deleted') continue;

    /* KAPISI ACIK AMA BELGESIZ. Bu kume uc yerde birden gorunmuyordu:
       bu betik yalnizca kapali hesaba bakiyordu, verify_nudge yalnizca
       'pending' hesaba, ve ikisi de id_document'i hic sormuyordu. Sonuc:
       onaylanmis bir SATICI hicbir belge vermeden satisa devam ediyor ve
       kimse haberdar olmuyor. Satici icin bu bildirim olmazsa olmaz --
       platform onun adina fatura kesiyor. */
    if (auth_prices_unlocked($a)) {
        $email = trim((string)($a['email'] ?? ''));
        if ($email === '' || empty($a['email_verified'])) continue;
        $miss = $missingDocs($a);
        if ($miss) {
            $docless[] = [
                'email'   => $email,
                'company' => trim((string)($a['company'] ?? '')) ?: trim((string)($a['name'] ?? '')),
                'type'    => (string)($a['type'] ?? 'buyer'),
                'country' => (string)($a['country'] ?? ''),
                'missing' => $miss,
                'days'    => max(0, (int)floor((time() - (strtotime((string)($a['created'] ?? '')) ?: time())) / 86400)),
                /* Operatorun 3 gun kuralindan muaf tuttugu satici (GARAGE LE PARIS):
                   listede KALIR ama isaretli -- muafiyet bilincli bir tercih olarak
                   gorunur durmali, gorunmez olmamali. */
                'exempt'  => ($a['type'] ?? '') === 'seller' && function_exists('auth_doc_grace_exempt') && auth_doc_grace_exempt($a),
            ];
        }
        continue;
    }

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

/* Saticilar once: platform onlarin adina fatura kesiyor, belgesiz satici
   alici tarafindaki belgesiz hesaptan daha pahali bir bosluk. */
usort($docless, function ($x, $y) {
    $sx = $x['type'] === 'seller'; $sy = $y['type'] === 'seller';
    if ($sx !== $sy) return $sx ? -1 : 1;
    return $y['days'] <=> $x['days'];
});

printf("bekleyen: %d | belgesiz (kapi acik): %d | e-postasi dogrulanmamis (islem yok): %d\n",
       count($waiting), count($docless), $unverified);
foreach ($waiting as $w) {
    printf("  ONAY  %-28s %-6s %-12s belge=%-9s %d gun%s\n",
        $mask($w['email']), $w['type'], mb_substr($w['country'], 0, 12),
        $w['doc'] !== '' ? $w['doc'] : '(istek yok)', $w['days'],
        $w['urgent'] ? '  <-- BELGESINI VERDI, HALA KAPALI' : '');
}
foreach ($docless as $w) {
    printf("  BELGE %-28s %-6s %-12s eksik=%-26s %d gun%s\n",
        $mask($w['email']), $w['type'], mb_substr($w['country'], 0, 12),
        implode('+', $w['missing']), $w['days'], !empty($w['exempt']) ? '  (3 gun kuralindan MUAF)' : '');
}

if (!$waiting && !$docless) { echo "bekleyen ve belgesiz yok — mektup gonderilmedi.\n"; exit(0); }

$urgent   = array_filter($waiting, fn($w) => $w['urgent']);
$sellers  = array_filter($docless, fn($w) => $w['type'] === 'seller');
$subj = [];
if ($waiting) $subj[] = count($waiting).' waiting for approval'.($urgent ? ' ('.count($urgent).' already sent documents)' : '');
if ($docless) $subj[] = count($docless).' missing documents'.($sellers ? ' — '.count($sellers).' of them sellers' : '');
$subject = 'VESTRA — '.implode(' · ', $subj);

$body = '';
if ($waiting) {
$body .= count($waiting) . " account(s) are waiting for your approval. Until you approve them they\n"
      . "cannot see trade prices, download the line sheet or place an order.\n\n";
}
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
if ($waiting) {
    $body .= "Approve them here: https://vestrasales.com/admin?tab=users\n"
           . "A document is not required to open an account — your approval is what opens it.\n\n";
}

/* BELGESIZ AMA ACIK. Bunlar onay beklemiyor -- zaten satis/alim yapiyorlar,
   sadece dosyalari eksik. Saticiyi ayri yaziyoruz: platform onun adina fatura
   kesiyor, yani eksik olan yalnizca bir evrak degil, faturanin dayanagi. */
if ($docless) {
    $body .= str_repeat('-', 64)."\n\n";
    if ($sellers) {
        $body .= "SELLERS trading without the documents VESTRA requires (".count($sellers)."):\n\n";
        foreach ($sellers as $w) {
            $body .= sprintf("  %s — %s (%s) · missing: %s · account %d day(s) old%s\n",
                $w['email'], $w['company'] !== '' ? $w['company'] : '(no company name)',
                $w['country'] !== '' ? $w['country'] : 'country not set',
                implode(' + ', $w['missing']), $w['days'],
                !empty($w['exempt']) ? ' · EXEMPT from the 3-day rule (your decision)' : '');
        }
        $body .= "\nVESTRA issues invoices in a seller's name, so this is the paperwork behind\n"
               . "every document that goes out under it. Sellers owe two: trade licence and ID.\n"
               . "Chase them with: send-campaign-preview.yml -> reply_letter=seller_setup,\n"
               . "reply_spec: to=account:<name>|send=true  (it writes only the parts that are\n"
               . "actually missing, so nobody is asked twice for something already on file).\n\n";
    }
    $buyersNoDoc = array_filter($docless, fn($w) => $w['type'] !== 'seller');
    if ($buyersNoDoc) {
        $body .= "Buyers with an open account but no trade document on file (".count($buyersNoDoc)."):\n\n";
        foreach ($buyersNoDoc as $w) {
            $body .= sprintf("  %s — %s (%s) · missing: %s · account %d day(s) old\n",
                $w['email'], $w['company'] !== '' ? $w['company'] : '(no company name)',
                $w['country'] !== '' ? $w['country'] : 'country not set',
                implode(' + ', $w['missing']), $w['days']);
        }
        $body .= "\nTheir prices are already open — this is for the file, not a blocker.\n\n";
    }
    $body .= "Request or review documents: https://vestrasales.com/admin?tab=documents\n\n";
}

$body .= "This message is only sent on days when somebody is actually waiting.\n";

$to = (string)vestra_cfg('ops_email', 'acerasoft@gmail.com');
if ($DRY) { echo "\n— DRY RUN — gonderilecek adres: " . $mask($to) . "\n--- konu ---\n$subject\n"; exit(0); }

$ok = vestra_send_mail($to, $subject, $body, '', 'VESTRA');
echo $ok ? "uyari gonderildi -> " . $mask($to) . "\n" : "UYARI GONDERILEMEDI\n";
exit($ok ? 0 : 1);
