<?php
/**
 * VESTRA — buyer claims ("Open dispute" / "I have a problem with this order").
 *
 * NEDEN VAR: metin vardi, dugme yoktu. SSS (returns/4, disputes/0, disputes/1),
 * order-confirm.php, help.php, membership.php, index.php ve buyer.php hepsi
 * "hesabinizdan Open dispute'a basin" diyordu; oysa `disputed` bayragi TUM
 * depoda tek bir yerde OKUNUYOR (admin.php, escrow satirindaki uyari) ve
 * HICBIR YERDE yazilmiyordu. Yani ne dugme vardi, ne de o uyari hic calisabilirdi.
 * Musteriye soylenen ile kodun yaptigi bes ayri sayfada ayrilmisti.
 *
 * Politika KURAL 11'de (inc/faq.php 'returns'), ve kendi gerekcesini soyluyor:
 * "telefonla ya da saticiyla ozel e-postada acilan bir talep KAYIT birakmaz,
 * kayit yoksa uygulatacak bir sey yoktur." O yuzden dogru duzeltme metni
 * zayiflatmak degil, kaydi olusturan yolu insa etmekti.
 *
 * OPERATOR KARARI (6 Eyl 2026): "sadece escrow'da degil, musteri HER siparisinde
 * bunu yapabilmeli, fakat cok belirgin olmasin -- 'siparisimle ilgili bir
 * sorunum var' gibi bir sey; secenekler cikar, sonra foto ve yazi." Bu yuzden:
 *   - kapi escrow'a ya da teslimata BAGLI DEGIL; iptal disinda her sipariste var
 *   - ekranda kocaman bir kart degil, sessiz bir baglanti (<details>); acilinca
 *     once sebepler, sebep secilince aciklama + fotograf (JS yoksa hepsi acik)
 *
 * TEK KARAR NOKTASI: vestra_claim_state(). Bilesen, POST isleyicisi, alicinin
 * "teslim aldim" dugmesi ve escrow supurucusu HEPSI onu cagirir. Bu depoda ayni
 * kararin ikinci kopyasi alti kez yanlis yere bakti; "talep acik mi" sorusunun
 * ikinci bir cevabi olsaydi, para bir yerde tutulup baska yerde birakilirdi.
 *
 * Dosya kurallari KYC yuklemesiyle BIREBIR ayni (auth_doc_file_check /
 * auth_doc_allowed_ext / auth_doc_max_bytes) — inc/receipts.php'nin de yaptigi
 * gibi, ikinci bir limit kopyasi ilk duzenlemede ayrisirdi.
 */
/* Kendi bagimliliklarini kendisi getirir. Bu dosya cron baglamindan da
   cagriliyor (escrow supurucusu, cron_claims.php) ve orada hicbir sayfa yigini
   yuklu degil: vestra_read_json products.php'de, history orders.php'de.
   Ilk yazimda products.php eksikti ve test bunu ilk kosuda fatal ile yakaladi --
   canlida gorunusu "supurucu sessizce durdu" olurdu.
   orders.php TEMBEL yukleniyor (function_exists ile): orders.php de bu dosyayi
   cagiriyor, ustte require etmek dairesel bir zincir kurardi. */
require_once __DIR__.'/products.php';  // vestra_read_json / vestra_write_json
require_once __DIR__.'/auth.php';
require_once __DIR__.'/escrow.php';   // VESTRA_CLAIM_DAYS, escrow_get, escrow_update

function vestra_claim_history_entry(string $status, string $by, string $note): array {
    if (!function_exists('vestra_order_history_entry')) require_once __DIR__.'/orders.php';
    return vestra_order_history_entry($status, $by, $note);
}

define('VESTRA_CLAIMS_DIR', __DIR__.'/../data/claims');
/* Kanit dosyasi tavani. SSS dort fotograf istiyor (mal, kargo etiketi, dis koli,
   ceki listesi); 6 onu rahat karsiliyor ve tek gonderimde biten bir talep
   "eksik kanit" yuzunden beklemiyor. */
const VESTRA_CLAIM_MAX_FILES = 6;
/* SSS disputes/1: "ekibimiz 2 is gunu icinde inceler". cron_claims.php bu
   sureyi gecen acik talebi operatore hatirlatir. */
const VESTRA_CLAIM_REVIEW_BDAYS = 2;

/** Talep sebepleri. Ilk dordu SSS disputes/1'in saydigi sebepler, birebir.
 *  'other' operator karari (6 Eyl 2026, "her sipariste"): henuz yola cikmamis
 *  bir sipariste de sorun olabilir (yanlis adet, adres, iptal istegi) ve o
 *  sorunun bir yeri olmali. Anahtar kayda yazilir, etiket ekranda cevrilir. */
function vestra_claim_reasons(): array {
    return [
        'non_delivery'     => 'Non-delivery',
        'not_as_described' => 'Not as described',
        'quality'          => 'Quality issue',
        'counterfeit'      => 'Counterfeit',
        'other'            => 'Something else',
    ];
}

function vestra_claim_dir(string $ref): string {
    $base = VESTRA_CLAIMS_DIR;
    if (!is_dir($base)) @mkdir($base, 0755, true);
    $ht = $base.'/.htaccess';
    if (!is_file($ht)) @file_put_contents($ht, "Deny from all\n");
    $safe = preg_replace('/[^A-Za-z0-9_-]/', '', $ref);
    $dir  = $base.'/'.$safe;
    if ($safe !== '' && !is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

function vestra_claim_file_path(string $ref, string $filename): string {
    $safe = preg_replace('/[^A-Za-z0-9_-]/', '', $ref);
    return VESTRA_CLAIMS_DIR.'/'.$safe.'/'.basename($filename);
}

/** Kayitli talep, yoksa null. Her kanit dosyasi icin diskte GERCEKTEN var mi
 *  bakilir — dosyasi olmayan bir kanit kaydi, hic kanit olmamasindan kotudur:
 *  ekranda "3 fotograf" yazip acilinca hicbiri cikmaz (receipts.php ayni kontrol). */
function vestra_order_claim(string $ref): ?array {
    $all = vestra_read_json('order_statuses.json');
    $c = $all[$ref]['claim'] ?? null;
    if (!is_array($c) || empty($c['opened_at'])) return null;
    $files = [];
    foreach ((array)($c['files'] ?? []) as $f) {
        if (is_file(vestra_claim_file_path($ref, (string)$f))) $files[] = (string)$f;
    }
    $c['files_on_disk'] = $files;
    return $c;
}

/** Bu siparişte SU AN acik bir talep var mi. Escrow supurucusu ve alicinin
 *  "teslim aldim" dugmesi bunu sorar. */
function vestra_claim_is_open(string $ref): bool {
    $c = vestra_order_claim($ref);
    return $c !== null && ($c['status'] ?? 'open') === 'open';
}

/** Tum acik talepler: [ref => claim]. cron_claims.php ve admin listesi icin. */
function vestra_claims_open(): array {
    $out = [];
    foreach (vestra_read_json('order_statuses.json') as $ref => $e) {
        $c = $e['claim'] ?? null;
        if (is_array($c) && !empty($c['opened_at']) && ($c['status'] ?? 'open') === 'open') $out[(string)$ref] = $c;
    }
    return $out;
}

/**
 * Talebin hangi asamada oldugu — TEK karar noktasi.
 *
 *  'filed'  : talep zaten acilmis (durumu $claim['status'])
 *  'na'     : iptal edilmis sipariş -- talep edilecek bir sey yok
 *  'open'   : acilabilir. 'deadline' teslim edilmisse dolu, degilse null
 *             (teslim edilmemis siparişte sure teslimattan sayilamaz — SSS:
 *             "teslim edilmemede, kararlastirilan teslim tarihine kadar")
 *  'late'   : teslim edilmis ve VESTRA_CLAIM_DAYS gecmis
 *
 * Operator karari (6 Eyl 2026): kapi HER sipariste acik, yalnizca iptal disinda.
 * Eskiden odenmemis siparişte kapaliydi; oysa yanlis girilmis adet ya da adres
 * de "siparisimle ilgili bir sorun"dur ve bir yeri olmali.
 *
 * Gun sayisi VESTRA_CLAIM_DAYS'ten gelir, metne GOMULMEZ: KURAL 11 ayni sabiti
 * SSS metniyle birlikte kilitliyor, ikinci bir "3" ilk degisiklikte ayrisirdi.
 */
function vestra_claim_state(string $ref, array $statusEntry, ?int $now = null): array {
    $now = $now ?? time();
    $c = vestra_order_claim($ref);
    if ($c) return ['phase'=>'filed', 'claim'=>$c, 'deadline'=>null, 'days_left'=>null];

    $st = (string)($statusEntry['status'] ?? 'pending');
    if ($st === 'cancelled') return ['phase'=>'na', 'claim'=>null, 'deadline'=>null, 'days_left'=>null];

    $dts = strtotime((string)($statusEntry['delivered_at'] ?? ''));
    if (!$dts) return ['phase'=>'open', 'claim'=>null, 'deadline'=>null, 'days_left'=>null];

    /* IS GUNU (operator, 6 Eyl 2026: "hafta sonlari sayilmasin"). Hesap
       escrow.php'deki vestra_claim_deadline()'da -- supurucunun serbest birakma
       tarihi de ayni fonksiyondan turer, ikisi ayrisamaz. */
    $deadline = vestra_claim_deadline($dts);
    if ($now > $deadline) return ['phase'=>'late', 'claim'=>null, 'deadline'=>$deadline, 'days_left'=>0];
    return ['phase'=>'open', 'claim'=>null, 'deadline'=>$deadline,
            'days_left'=>(int)ceil(($deadline - $now) / 86400)];
}

/**
 * Talebi acar: kanitlari saklar, kayda yazar, escrow'u isaretler.
 * [ok, error, claim_ref] doner. error kodlari auth_doc_error_text() ile ayni
 * sozlukten, arti: 'reason' (sebep secilmemis), 'detail' (aciklama bos),
 * 'window' (sure gecmis / siparişte talep anlamsiz), 'exists' (zaten acik).
 *
 * DOSYA ZORUNLU DEGIL. SSS fotograf istiyor ama bir talebi fotograf yok diye
 * REDDETMEK, 3 gunluk sureyi kaciran alici uretir; kayit acilir, eksik kanit
 * incelemede istenir (SSS: "eksik bir talep de gonderildigi gun acilmis sayilir").
 *
 * Bildirimler (alici mektubu, sohbet karti, operator) BURADA DEGIL,
 * vestra_claim_notify()'da: bu fonksiyon saf kalsin ki testte posta gitmesin.
 */
function vestra_claim_open_new(string $ref, string $reason, string $detail, array $files, string $by = 'buyer'): array {
    $ref    = trim($ref);
    $detail = trim($detail);
    if ($ref === '')                                   return ['ok'=>false, 'error'=>'window',  'claim_ref'=>''];
    if (!isset(vestra_claim_reasons()[$reason]))       return ['ok'=>false, 'error'=>'reason',  'claim_ref'=>''];
    if ($detail === '')                                return ['ok'=>false, 'error'=>'detail',  'claim_ref'=>''];

    $all   = vestra_read_json('order_statuses.json');
    /* Kaydi hic olmayan sipariş = daha durumu bile yazilmamis taze sipariş;
       'pending' sayilir. Operator "her sipariste" dedi, kayitsiz olan da sipariş. */
    $entry = $all[$ref] ?? ['status'=>'pending'];
    $state = vestra_claim_state($ref, $entry);
    if ($state['phase'] === 'filed')                   return ['ok'=>false, 'error'=>'exists',  'claim_ref'=>''];
    if ($state['phase'] !== 'open')                    return ['ok'=>false, 'error'=>'window',  'claim_ref'=>''];

    /* Kanitlar. Bir dosya reddedilirse TALEP DE reddedilir: yarim kabul edilen
       bir gonderimde alici fotograflarinin gittigini sanir. */
    $stored = [];
    $dir = vestra_claim_dir($ref);
    foreach (array_slice($files, 0, VESTRA_CLAIM_MAX_FILES) as $f) {
        if (!is_array($f) || (int)($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        $code = auth_doc_file_check($f);
        if ($code !== '') {
            foreach ($stored as $s) @unlink($dir.'/'.$s);
            error_log('[VESTRA claim] rejected ref='.$ref.' code='.$code
                     .' size='.(int)($f['size'] ?? 0)
                     .' ext='.strtolower(pathinfo((string)($f['name'] ?? ''), PATHINFO_EXTENSION)));
            return ['ok'=>false, 'error'=>$code, 'claim_ref'=>''];
        }
        $ext   = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
        $fname = 'claim_'.bin2hex(random_bytes(4)).'.'.$ext;
        if (!is_dir($dir) || !is_writable($dir) || !@move_uploaded_file((string)$f['tmp_name'], $dir.'/'.$fname)) {
            foreach ($stored as $s) @unlink($dir.'/'.$s);
            error_log('[VESTRA claim] store FAILED ref='.$ref.' dir='.$dir
                     .' dir_ok='.(is_dir($dir) ? 'yes' : 'NO').' writable='.(is_writable($dir) ? 'yes' : 'NO'));
            return ['ok'=>false, 'error'=>'server', 'claim_ref'=>''];
        }
        $stored[] = $fname;
    }

    /* SSS "bir referans numarasi alirsiniz" diyor -- o yuzden gercekten uretiliyor. */
    $claimRef = 'CLM-'.strtoupper(bin2hex(random_bytes(3)));
    $all = vestra_read_json('order_statuses.json');       // dosyalar diske yazilirken degismis olabilir
    $entry = $all[$ref] ?? $entry;
    $entry['claim'] = [
        'claim_ref' => $claimRef,
        'reason'    => $reason,
        'detail'    => mb_substr($detail, 0, 4000),
        'files'     => $stored,
        'opened_at' => date('c'),
        'opened_by' => $by,
        'status'    => 'open',
    ];
    $entry['history'][] = vestra_claim_history_entry((string)($entry['status'] ?? 'pending'), $by,
        'Claim opened '.$claimRef.' ('.vestra_claim_reasons()[$reason].', '.count($stored).' file(s))');
    $all[$ref] = $entry;
    vestra_write_json('order_statuses.json', $all);

    /* Escrow kaydini isaretle. admin.php'deki "⚠ dispute_reason" uyarisi bu
       bayragi okuyor ve bugune kadar hic yazilmadigi icin hic gorunmedi. */
    $esc = function_exists('escrow_get') ? escrow_get($ref) : null;
    if ($esc) {
        escrow_update($ref, ['disputed'=>true, 'dispute_reason'=>vestra_claim_reasons()[$reason],
                             'disputed_at'=>date('c'), 'claim_ref'=>$claimRef]);
    }
    return ['ok'=>true, 'error'=>'', 'claim_ref'=>$claimRef];
}

/**
 * Talebi kapatir (operator karari). Escrow bayragi da temizlenir, yoksa
 * supurucu parayi birakir ama admin satirinda "⚠ disputed" sonsuza kadar durur —
 * ayni kaydin iki yerde farkli sey soylemesi bu depoda zaten yasandi (KURAL 5f).
 * Talebin KENDISI silinmez: kayit, sonucun gerekcesiyle birlikte kalir.
 * Sonuc metni ZORUNLU: SSS returns/9 "sonuc size bildirilir", returns/6 "yazili
 * yetki alirsiniz" diyor -- bos bir sonuc, alicinin hic okumayacagi bir mektup.
 */
function vestra_claim_resolve(string $ref, string $outcome, string $by = 'operator'): array {
    $outcome = trim($outcome);
    if ($outcome === '') return ['ok'=>false, 'error'=>'detail'];
    $all = vestra_read_json('order_statuses.json');
    $entry = $all[$ref] ?? null;
    if ($entry === null || empty($entry['claim'])) return ['ok'=>false, 'error'=>'nofile'];
    if (($entry['claim']['status'] ?? 'open') !== 'open') return ['ok'=>false, 'error'=>'exists'];
    $entry['claim']['status']      = 'resolved';
    $entry['claim']['resolved_at'] = date('c');
    $entry['claim']['resolved_by'] = $by;
    $entry['claim']['outcome']     = mb_substr($outcome, 0, 500);
    $entry['history'][] = vestra_claim_history_entry((string)($entry['status'] ?? 'pending'), $by,
        'Claim '.($entry['claim']['claim_ref'] ?? '').' resolved: '.mb_substr($outcome, 0, 120));
    $all[$ref] = $entry;
    vestra_write_json('order_statuses.json', $all);
    $esc = function_exists('escrow_get') ? escrow_get($ref) : null;
    if ($esc) escrow_update($ref, ['disputed'=>false, 'dispute_resolved_at'=>date('c')]);
    return ['ok'=>true, 'error'=>''];
}

/**
 * Bildirimler — acilista ve kapanista AYNI yol, ki iki olay iki farkli sey
 * soylemesin. $event: 'opened' | 'resolved'.
 *   - aliciya mektup (ref numarasi / sonuc): SSS disputes/1 ve returns/6
 *   - siparişin sohbet ipligine sistem karti: SSS returns/9 "sonuc size sipariş
 *     ipliginde bildirilir, dosya tek yerde kalir"
 *   - aliciya push (hesabi varsa)
 *   - acilista operatore mektup (kanitlarla birlikte inceleme baglantisi)
 * Sohbet karti saticinin da gordugu sey: KURAL 8 saticiyi ident'le gizler,
 * aliciyi degil -- satici kime sattigini ve neyin sikayet edildigini bilmek
 * zorunda ("satıcıdan açıklama istenir", returns/9).
 */
function vestra_claim_notify(string $event, string $ref, array $orderRow, array $claim): array {
    require_once __DIR__.'/notify.php';
    require_once __DIR__.'/email_templates.php';
    $out = ['buyer_mail'=>false, 'ops_mail'=>false, 'threads'=>0];
    $buyerName  = (string)($orderRow['name'] ?: ($orderRow['company'] ?: 'Customer'));
    $buyerEmail = trim((string)($orderRow['email'] ?? ''));
    $buyerAcc   = $buyerEmail !== '' ? auth_find($buyerEmail) : null;
    $claimRef   = (string)($claim['claim_ref'] ?? '');
    $reasonLbl  = vestra_claim_reasons()[$claim['reason'] ?? ''] ?? 'Claim';

    if ($event === 'opened') {
        [$s, $b, $o] = vestra_tpl_claim_received($buyerName, $ref, $claimRef, $reasonLbl, (bool)$buyerAcc);
    } else {
        [$s, $b, $o] = vestra_tpl_claim_resolved($buyerName, $ref, $claimRef, (string)($claim['outcome'] ?? ''), (bool)$buyerAcc);
    }
    if ($buyerEmail !== '') $out['buyer_mail'] = (bool)vestra_send_mail($buyerEmail, $s, $b, '', '', null, '', $o);

    if ($buyerAcc) {
        require_once __DIR__.'/push.php';
        vestra_push_send($buyerAcc['id'],
            $event === 'opened' ? 'VESTRA — claim '.$claimRef.' received' : 'VESTRA — claim '.$claimRef.' resolved',
            'Order '.$ref.($event === 'opened' ? ' — we review within '.VESTRA_CLAIM_REVIEW_BDAYS.' business days.' : ' — '.mb_substr((string)($claim['outcome'] ?? ''), 0, 90)),
            '/buyer?tab=orders&view='.rawurlencode($ref));
        /* Siparişteki her saticinin ipligine bir kart. Satici uid'si ilanlardan
           (vestra_order_lines), sipariş satirindan degil -- orders.csv satici
           tasimiyor. */
        if (!function_exists('vestra_order_lines')) require_once __DIR__.'/orders.php';
        require_once __DIR__.'/messages.php';
        $seen = [];
        foreach (vestra_order_lines($orderRow)['lines'] as $l) {
            $sid = (string)($l['seller_uid'] ?? '');
            if ($sid === '' || isset($seen[$sid])) continue;
            $seen[$sid] = true;
            vestra_msg_post_system($buyerAcc['id'], $sid, '', [
                'kind'=>'claim', 'status'=>$event, 'ref'=>$ref, 'claim_ref'=>$claimRef,
                'reason'=>$reasonLbl, 'outcome'=>(string)($claim['outcome'] ?? ''),
            ]);
            $out['threads']++;
        }
    }

    if ($event === 'opened') {
        $opsTo = (string)vestra_cfg('ops_email', 'acerasoft@gmail.com');
        $out['ops_mail'] = (bool)vestra_send_mail($opsTo, 'VESTRA — claim '.$claimRef.' opened on order '.$ref,
            "A buyer opened a claim.\n\n"
          . "Claim:   {$claimRef}\nOrder:   {$ref}\n"
          . "Company: ".($orderRow['company'] ?? '?')."\n"
          . "Reason:  {$reasonLbl}\n"
          . "Files:   ".count((array)($claim['files'] ?? []))."\n\n"
          . "Review (with evidence): https://vestrasales.com/admin?tab=orders&view=".rawurlencode($ref)."\n\n"
          . "Escrow funds, if any, are held until this is resolved. The FAQ promises a review within "
          . VESTRA_CLAIM_REVIEW_BDAYS." business days.\n\n— VESTRA", '', 'VESTRA');
    }
    return $out;
}

/** Hata kodu -> kullaniciya gosterilecek cumle. Dosya kodlari auth'un kendi
 *  metnine devrediliyor ki yukleme hatalari her yerde ayni cumleyi versin. */
function vestra_claim_error_text(string $code): string {
    $tr = fn(string $s) => function_exists('t') ? t($s) : $s;
    switch ($code) {
        case 'reason': return $tr('Please choose what went wrong.');
        case 'detail': return $tr('Please describe the problem.');
        case 'exists': return $tr('A claim is already open for this order.');
        case 'window': return $tr('A claim can no longer be opened for this order.');
        case 'hold':   return $tr('A claim is open on this order — receipt cannot be confirmed until it is resolved.');
        default:       return function_exists('auth_doc_error_text') ? auth_doc_error_text($code) : $tr('Upload failed.');
    }
}

/**
 * Alicinin gordugu bilesen — sessiz. Operator: "cok belirgin olmasin".
 *   open  : <details> ile katlanmis tek satirlik baglanti "Siparişimle ilgili
 *           bir sorunum var"; acilinca sebepler (radyo), sebep secilince
 *           aciklama + fotograf + gonder. JS yoksa ikinci adim zaten aciktir
 *           (gizleyen JS'tir, gosteren degil) -- form JS'siz de calisir.
 *   filed : tek satir durum (acik: "inceleniyor", kapali: sonuc)
 *   late  : tek satir, destek adresi
 *   na    : hicbir sey
 * Dosya girdisi receipts.php / docs.php ile ayni accept listesini, ayni boyut
 * sinirini ve ayni tarayici-ici kucultme scriptini kullanir.
 */
function vestra_claim_widget(string $ref, array $statusEntry, string $formHref): string {
    $tr = fn(string $s) => function_exists('t') ? t($s) : $s;
    $cl = vestra_claim_state($ref, $statusEntry);
    if ($cl['phase'] === 'na') return '';

    if ($cl['phase'] === 'filed') {
        $c = $cl['claim'];
        $reasons = vestra_claim_reasons();
        $open = ($c['status'] ?? 'open') === 'open';
        $h = '<div class="claimline'.($open ? '' : ' ok').'">'
           . ($open ? '⚠️ ' : '✓ ')
           . '<b>'.htmlspecialchars($tr('Claim')).' <span class="mono">'.htmlspecialchars((string)($c['claim_ref'] ?? '')).'</span></b>'
           . ' · '.htmlspecialchars($tr((string)($reasons[$c['reason'] ?? ''] ?? '')))
           . ' · '.htmlspecialchars(date('j M Y', strtotime((string)($c['opened_at'] ?? '')) ?: time()));
        $n = count((array)($c['files_on_disk'] ?? []));
        if ($n) $h .= ' · 📎 '.$n;
        if ($open) {
            $h .= '<div class="hint">'.htmlspecialchars($tr('Under review — we reply within 2 business days, here in this order.')).'</div>';
        } else {
            $h .= '<div class="hint"><b>'.htmlspecialchars($tr('Outcome')).':</b> '.htmlspecialchars((string)($c['outcome'] ?? ''))
                . ' <span class="mut">· '.htmlspecialchars(date('j M Y', strtotime((string)($c['resolved_at'] ?? '')) ?: time())).'</span></div>';
        }
        return $h.'</div>';
    }

    if ($cl['phase'] === 'late') {
        /* Sureyi kacirmis aliciyi bos bir duvara birakma: politika 3 gunu kesin
           tutuyor ama "yanlis oldugunu dusunuyorsaniz yazin" yolu aciktir --
           karari operator verir, form vermez. */
        return '<p class="hint claimlate">'.htmlspecialchars($tr('The claim window for this delivery has closed.')).' '
             . htmlspecialchars($tr('If you believe this is wrong, write to'))
             . ' <a class="acc" href="mailto:support@vestrasales.com">support@vestrasales.com</a>.</p>';
    }

    $max    = auth_doc_max_bytes();
    $accept = '.'.implode(',.', auth_doc_allowed_ext()).',image/*,application/pdf';
    $h = '<details class="claimdis"><summary>'.htmlspecialchars($tr('I have a problem with this order')).'</summary>'
       . '<form method="post" action="'.htmlspecialchars($formHref).'" enctype="multipart/form-data" class="claimform">'
       . '<input type="hidden" name="_action" value="open_claim">'
       . '<input type="hidden" name="MAX_FILE_SIZE" value="'.$max.'">'
       . '<input type="hidden" name="ref" value="'.htmlspecialchars($ref).'">';
    if (!empty($cl['deadline'])) {
        $h .= '<p class="hint">'.htmlspecialchars($tr('Report by')).' <b>'.htmlspecialchars(date('j M Y', (int)$cl['deadline'])).'</b>'
            . ' · <a class="acc" href="/faq?cat=returns">'.htmlspecialchars($tr('Read the claim rules')).'</a></p>';
    } else {
        $h .= '<p class="hint"><a class="acc" href="/faq?cat=returns">'.htmlspecialchars($tr('Read the claim rules')).'</a></p>';
    }
    $h .= '<div class="claimreasons">';
    foreach (vestra_claim_reasons() as $k => $label) {
        $h .= '<label><input type="radio" name="reason" value="'.htmlspecialchars($k).'" required> '.htmlspecialchars($tr($label)).'</label>';
    }
    $h .= '</div>'
       . '<div class="claimstep2">'
       . '<label class="hint">'.htmlspecialchars($tr('Describe the problem')).'</label>'
       . '<textarea name="detail" rows="3" required placeholder="'.htmlspecialchars($tr('How many pieces are affected, and which articles?')).'"></textarea>'
       . '<label class="hint">'.htmlspecialchars($tr('Photographs: the goods, the shipping label and the outer carton')).'</label>'
       . '<input type="file" name="evidence[]" multiple accept="'.htmlspecialchars($accept).'" data-shrink="1" data-max="'.$max.'">'
       . '<span class="hint shrinkhint" style="font-size:11px"></span>'
       . '<button class="btn btn-o btn-sm" type="submit">'.htmlspecialchars($tr('Send')).'</button>'
       . '</div></form></details>';
    /* Ilerleyen acilis: sebep secilene kadar ikinci adim gizli. GIZLEYEN JS'tir;
       JS calismazsa her sey acik kalir ve form yine gonderilir. */
    $h .= '<script>(function(){if(window.__vclaim)return;window.__vclaim=1;'
        . 'document.querySelectorAll(".claimform").forEach(function(f){var s=f.querySelector(".claimstep2");if(!s)return;'
        . 's.hidden=true;f.querySelectorAll("input[name=reason]").forEach(function(r){r.addEventListener("change",function(){s.hidden=false;'
        . 'var t=s.querySelector("textarea");if(t)t.focus();});});});})();</script>';
    if (function_exists('vestra_doc_upload_js')) $h .= vestra_doc_upload_js();
    return $h;
}

/**
 * Saticinin gordugu blok — SALT OKUNUR. SSS returns/9: "VESTRA saticidan
 * aciklama ister". Satici kaniti gorebilmeli ki cevap verebilsin; indirme
 * seller.php?dl_claim uzerinden, sahiplik kontrolu orada.
 */
function vestra_claim_seller_block(string $ref): string {
    $tr = fn(string $s) => function_exists('t') ? t($s) : $s;
    $c = vestra_order_claim($ref);
    if (!$c) return '';
    $reasons = vestra_claim_reasons();
    $open = ($c['status'] ?? 'open') === 'open';
    $h = '<div class="claimline'.($open ? '' : ' ok').'">'.($open ? '⚠️ ' : '✓ ')
       . '<b>'.htmlspecialchars($tr('Buyer claim')).' <span class="mono">'.htmlspecialchars((string)($c['claim_ref'] ?? '')).'</span></b>'
       . ' · '.htmlspecialchars($tr((string)($reasons[$c['reason'] ?? ''] ?? '')))
       . ' · '.htmlspecialchars(date('j M Y', strtotime((string)($c['opened_at'] ?? '')) ?: time()))
       . '<div style="white-space:pre-wrap;margin:6px 0">'.htmlspecialchars((string)($c['detail'] ?? '')).'</div>';
    foreach ((array)($c['files_on_disk'] ?? []) as $f) {
        $h .= '<a class="acc" style="margin-right:10px;font-size:12.5px" target="_blank" href="/seller?dl_claim='.urlencode($f).'&ref='.urlencode($ref).'">📎 '.htmlspecialchars($f).'</a>';
    }
    $h .= $open
        ? '<div class="hint" style="margin-top:6px">'.htmlspecialchars($tr('VESTRA is reviewing this claim and may ask you for your account of it. Funds, if held, stay held until it is resolved.')).'</div>'
        : '<div class="hint" style="margin-top:6px"><b>'.htmlspecialchars($tr('Outcome')).':</b> '.htmlspecialchars((string)($c['outcome'] ?? '')).'</div>';
    return $h.'</div>';
}
