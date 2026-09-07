<?php
/**
 * VESTRA — buyer claims ("Open dispute").
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
 * TEK KARAR NOKTASI: vestra_claim_state(). Kart, POST isleyicisi ve escrow
 * supurucusu ucu de ONU cagirir. Bu depoda ayni kararin ikinci kopyasi alti kez
 * yanlis yere bakti; "talep acik mi" sorusunun ikinci bir cevabi olsaydi, para
 * bir yerde tutulup baska yerde birakilirdi.
 *
 * Dosya kurallari KYC yuklemesiyle BIREBIR ayni (auth_doc_file_check /
 * auth_doc_allowed_ext / auth_doc_max_bytes) — inc/receipts.php'nin de yaptigi
 * gibi, ikinci bir limit kopyasi ilk duzenlemede ayrisirdi.
 */
/* Kendi bagimliliklarini kendisi getirir. Bu dosya cron baglamindan da
   cagriliyor (escrow supurucusu) ve orada hicbir sayfa yigini yuklu degil:
   vestra_read_json products.php'de, vestra_order_history_entry orders.php'de.
   Ilk yazimda products.php eksikti ve test bunu ilk kosuda fatal ile yakaladi --
   canlida gorunusu "supurucu sessizce durdu" olurdu.
   orders.php TEMBEL yukleniyor (asagida, function_exists ile): orders.php de
   bu dosyayi cagiriyor, ustte require etmek dairesel bir zincir kurardi. */
require_once __DIR__.'/products.php';  // vestra_read_json / vestra_write_json
require_once __DIR__.'/auth.php';
require_once __DIR__.'/escrow.php';   // VESTRA_CLAIM_DAYS, escrow_get, escrow_update

/** orders.php'deki gecmis satiri kurucusu — tembel, dairesel require olmasin. */
function vestra_claim_history_entry(string $status, string $by, string $note): array {
    if (!function_exists('vestra_order_history_entry')) require_once __DIR__.'/orders.php';
    return vestra_order_history_entry($status, $by, $note);
}

define('VESTRA_CLAIMS_DIR', __DIR__.'/../data/claims');
/* Kanit dosyasi tavani. SSS dort fotograf istiyor (mal, kargo etiketi, dis koli,
   ceki listesi); 6 onu rahat karsiliyor ve tek gonderimde biten bir talep
   "eksik kanit" yuzunden beklemiyor. */
const VESTRA_CLAIM_MAX_FILES = 6;

/** Talep sebepleri — SSS disputes/1'in saydigi DORT sebep, birebir aynilari.
 *  Anahtar kayda yazilir (dile bagimsiz), etiket ekranda cevrilir. */
function vestra_claim_reasons(): array {
    return [
        'non_delivery'     => 'Non-delivery',
        'not_as_described' => 'Not as described',
        'quality'          => 'Quality issue',
        'counterfeit'      => 'Counterfeit',
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

/** Bu siparişte SU AN acik bir talep var mi. Escrow supurucusu bunu sorar. */
function vestra_claim_is_open(string $ref): bool {
    $c = vestra_order_claim($ref);
    return $c !== null && ($c['status'] ?? 'open') === 'open';
}

/**
 * Talebin hangi asamada oldugu — TEK karar noktasi.
 *
 *  'filed'  : talep zaten acilmis (durumu $claim['status'])
 *  'na'     : bu siparişte talep anlamsiz (henuz odenmemis, ya da iptal)
 *  'open'   : acilabilir. 'deadline' teslim edilmisse dolu, degilse null
 *             (teslim edilmemis siparişte "mal gelmedi" talebinin suresi
 *             teslimattan sayilamaz — SSS: "teslim edilmemede, kararlastirilan
 *             teslim tarihine kadar")
 *  'late'   : teslim edilmis ve VESTRA_CLAIM_DAYS gecmis
 *
 * Gun sayisi VESTRA_CLAIM_DAYS'ten gelir, metne GOMULMEZ: KURAL 11 ayni sabiti
 * SSS metniyle birlikte kilitliyor, ikinci bir "3" ilk degisiklikte ayrisirdi.
 */
function vestra_claim_state(string $ref, array $statusEntry, ?int $now = null): array {
    $now = $now ?? time();
    $c = vestra_order_claim($ref);
    if ($c) return ['phase'=>'filed', 'claim'=>$c, 'deadline'=>null, 'days_left'=>null];

    $st = (string)($statusEntry['status'] ?? 'pending');
    /* Para hareket etmeden ya da mal yola cikmadan talep edilecek bir sey yok;
       iptal edilmis siparişte de yok. 'completed' BILEREK iceride: alici teslim
       aldigini onayladiktan sonra kutuyu acip sorunu gorebilir, ve o an hakki
       hala surmektedir. */
    if (!in_array($st, ['paid', 'shipped', 'delivered', 'completed'], true)) {
        return ['phase'=>'na', 'claim'=>null, 'deadline'=>null, 'days_left'=>null];
    }

    $dts = strtotime((string)($statusEntry['delivered_at'] ?? ''));
    if (!$dts) return ['phase'=>'open', 'claim'=>null, 'deadline'=>null, 'days_left'=>null];

    $deadline = $dts + VESTRA_CLAIM_DAYS * 86400;
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
 */
function vestra_claim_open_new(string $ref, string $reason, string $detail, array $files, string $by = 'buyer'): array {
    $ref    = trim($ref);
    $detail = trim($detail);
    if ($ref === '')                                   return ['ok'=>false, 'error'=>'window',  'claim_ref'=>''];
    if (!isset(vestra_claim_reasons()[$reason]))       return ['ok'=>false, 'error'=>'reason',  'claim_ref'=>''];
    if ($detail === '')                                return ['ok'=>false, 'error'=>'detail',  'claim_ref'=>''];

    $all   = vestra_read_json('order_statuses.json');
    $entry = $all[$ref] ?? null;
    if ($entry === null)                               return ['ok'=>false, 'error'=>'window',  'claim_ref'=>''];
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
 */
function vestra_claim_resolve(string $ref, string $outcome, string $by = 'operator'): array {
    $all = vestra_read_json('order_statuses.json');
    $entry = $all[$ref] ?? null;
    if ($entry === null || empty($entry['claim'])) return ['ok'=>false, 'error'=>'nofile'];
    if (($entry['claim']['status'] ?? 'open') !== 'open') return ['ok'=>false, 'error'=>'exists'];
    $entry['claim']['status']      = 'resolved';
    $entry['claim']['resolved_at'] = date('c');
    $entry['claim']['resolved_by'] = $by;
    $entry['claim']['outcome']     = mb_substr(trim($outcome), 0, 500);
    $entry['history'][] = vestra_claim_history_entry((string)($entry['status'] ?? 'pending'), $by,
        'Claim '.($entry['claim']['claim_ref'] ?? '').' resolved'.($outcome !== '' ? ': '.mb_substr(trim($outcome), 0, 120) : ''));
    $all[$ref] = $entry;
    vestra_write_json('order_statuses.json', $all);
    $esc = function_exists('escrow_get') ? escrow_get($ref) : null;
    if ($esc) escrow_update($ref, ['disputed'=>false, 'dispute_resolved_at'=>date('c')]);
    return ['ok'=>true, 'error'=>''];
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
        default:       return function_exists('auth_doc_error_text') ? auth_doc_error_text($code) : $tr('Upload failed.');
    }
}

/** Alicinin gordugu form. Dosya girdisi receipts.php / docs.php ile ayni accept
 *  listesini, ayni boyut sinirini ve ayni tarayici-ici kucultme scriptini kullanir. */
function vestra_claim_form(string $action, string $ref): string {
    $tr     = fn(string $s) => function_exists('t') ? t($s) : $s;
    $max    = auth_doc_max_bytes();
    $accept = '.'.implode(',.', auth_doc_allowed_ext()).',image/*,application/pdf';
    $h = '<form method="post" action="'.htmlspecialchars($action).'" enctype="multipart/form-data">'
       . '<input type="hidden" name="_action" value="open_claim">'
       . '<input type="hidden" name="MAX_FILE_SIZE" value="'.$max.'">'
       . '<input type="hidden" name="ref" value="'.htmlspecialchars($ref).'">'
       . '<label class="hint">'.htmlspecialchars($tr('What went wrong?')).'</label>'
       . '<select name="reason" required style="width:100%;margin-bottom:10px">'
       . '<option value="">—</option>';
    foreach (vestra_claim_reasons() as $k => $label) {
        $h .= '<option value="'.htmlspecialchars($k).'">'.htmlspecialchars($tr($label)).'</option>';
    }
    $h .= '</select>'
       . '<label class="hint">'.htmlspecialchars($tr('Describe the problem')).'</label>'
       . '<textarea name="detail" rows="3" required style="width:100%;margin-bottom:10px" '
       . 'placeholder="'.htmlspecialchars($tr('How many pieces are affected, and which articles?')).'"></textarea>'
       . '<label class="hint">'.htmlspecialchars($tr('Photographs: the goods, the shipping label and the outer carton')).'</label>'
       . '<input type="file" name="evidence[]" multiple accept="'.htmlspecialchars($accept).'" '
       . 'data-shrink="1" data-max="'.$max.'" style="font-size:12px;width:100%;margin-bottom:6px">'
       . '<span class="hint shrinkhint" style="font-size:11px"></span>'
       . '<button class="btn btn-p btn-sm" type="submit" style="margin-top:8px">'.htmlspecialchars($tr('Open dispute')).'</button>'
       . '</form>';
    return $h;
}
