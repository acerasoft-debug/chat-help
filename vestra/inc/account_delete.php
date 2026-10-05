<?php
/**
 * VESTRA — kalici HESAP SILME: TEK uygulayici.
 *
 * Iki yol AYNI fonksiyonu cagirir: panelin Users satirindaki Delete dugmesi
 * (admin.php: delete_account) ve seller-products.yml -> admin_mode=seller_delete.
 * Ayri yazilsalardi panelin kapisi ile is akisinin kapisi ayrisirdi ve ayrisma
 * ancak bir hesap yanlis silindiginde gorunurdu (bu depoda "kapinin ikinci
 * kopyasi yanlis yere baktı" birkac kez kayitli).
 *
 * KAPI (panelin eski satir ici kapisiyla BIREBIR AYNI -- davranis degismedi):
 *   - hesap alici ya da satici olarak bir siparise bagliysa ve o siparisin KESILMIS
 *     FATURASI varsa: reddet (fatura numarali bir belgedir, musteriye gitmistir;
 *     konusu silinirse numara hicbir seye isaret etmez);
 *   - faturasiz ama ACIK siparis varsa: reddet. "Acik" = durum completed/cancelled/
 *     refunded DEGIL; durum orders.csv satirindaki 'status' sutunundan okunur --
 *     o sutun siparis basliginda YOK, yani iptal ve tamamlanmis siparis de ACIK
 *     sayilir. Bu bilinen bir kusur, burada AYNEN korundu: panelin verdigi cevap
 *     degismesin diye. Gercek durum order_statuses.json'da; seller_footprint onu
 *     yaninda basiyor.
 *
 * STRICT (yalniz is akisi): hesap HICBIR SEYE sahip olmamali. Panelin kapisi
 * teklifleri, konusmalari, numuneleri, "faturayi bu satici kessin" secimlerini ve
 * diskteki faturalari GORMUYOR; panel ise ilanlari listings.json'dan siler. Strict
 * kipte bunlardan biri varsa hesap SILINMEZ ("once devredin / kapatin"), ilan da
 * silinmez -- ilanlar el degistirmis olmali. Panel strict=false ile cagirir ve
 * eskisi gibi ilanlari birlikte siler (yalniz artik silmeden once listings.json
 * yedeklenir).
 *
 * SILMEDEN ONCE UC YEDEK (hicbiri alinamazsa HICBIR SEY silinmez -- disk kotasi
 * dolmusken (16 Eyl 2026) sessizce yedeksiz silmek geri donulemez bir kayip olurdu):
 *   accounts.json.bak.<zaman>                  tum hesap dosyasi
 *   deleted-accounts/<uid>-<zaman>.json        silinen hesabin KENDI kaydi
 *   listings.json.bak-del-<zaman>              yalniz ilan silinecekse
 *
 * DOKUNULMAYANLAR (bilerek): Stripe Connect hesabi (Stripe'ta ayrica kapatilir),
 * data/docs/<uid>/ altindaki yuklenmis belgeler (yedek kaydi onlara isaret ediyor;
 * "yanlis hesabi sildim" kurtarmasi dosyayi da ister), request_offers.csv satirlari
 * (tarihsel metin, uid ile baglanti yok). Sayilir ve 'extras' ile bildirilir.
 *
 * DONUS: ok, code ('' | not_found | has_invoice | has_orders | has_links | backup |
 * integrity | readback), n (engelleyen sayi), applied, label/type/status/country
 * (E-POSTA VE BANKA YOK -- cagiran log'a basabilir), counts, links, extras, backups
 * (yalniz dosya adlari), readback.
 *
 * KURAL 15: bagimliliklarini KENDISI yukler -- kardes bir dosyanin require'ina
 * yaslanan bir yardimci, bakim betiginden cagrildiginda fatal verir.
 */
require_once __DIR__.'/products.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/orders.php';
require_once __DIR__.'/invoice.php';

function vestra_account_delete(string $uid, bool $apply = false, bool $strict = false): array {
    $res = [
        'ok' => false, 'code' => '', 'n' => 0, 'applied' => false,
        'label' => '', 'type' => '', 'status' => '', 'country' => '',
        'counts' => ['orders' => 0, 'invoiced' => 0, 'open' => 0],
        'links' => [], 'extras' => [], 'backups' => [], 'readback' => [],
    ];
    $uid = trim($uid);
    $safeUid = preg_replace('/[^A-Za-z0-9_-]/', '', $uid);

    $victim = null; $nBefore = 0;
    foreach (auth_accounts() as $a) {
        $nBefore++;
        if ($uid !== '' && (string)($a['id'] ?? '') === $uid && $victim === null) $victim = $a;
    }
    if (!$victim) { $res['code'] = 'not_found'; return $res; }
    $res['label']   = trim((string)(($victim['company'] ?? '') ?: ($victim['name'] ?? ''))) ?: $uid;
    $res['type']    = (string)($victim['type'] ?? '');
    $res['status']  = (string)($victim['status'] ?? '');
    $res['country'] = (string)($victim['country'] ?? '');

    /* ---- Silme kapisi: panelin eski satir ici kapisi, BIREBIR ---- */
    $openOrders = 0; $invoiced = 0; $nOrd = 0;
    $vEmail = strtolower(trim((string)($victim['email'] ?? '')));
    foreach (vestra_read_csv('orders.csv') as $o) {
        $ref = (string)($o['ref'] ?? '');
        /* Alici tarafi: siparis satirindaki e-posta. Kapali siparis de sayilir
           cunku fatura kontrolu ondan turuyor. */
        $isBuyer  = $vEmail !== '' && strtolower(trim((string)($o['email'] ?? ''))) === $vEmail;
        $isSeller = false;
        foreach (vestra_order_lines($o)['lines'] as $l) {
            if ((string)($l['seller_uid'] ?? '') === $uid) { $isSeller = true; break; }
        }
        if (!$isBuyer && !$isSeller) continue;
        $nOrd++;
        if ($ref !== '' && count(vestra_invoices_for_ref($ref))>0) { $invoiced++; continue; }
        if (in_array(strtolower((string)($o['status']??'')),['completed','cancelled','refunded'],true)) continue;
        $openOrders++;
    }
    $res['counts'] = ['orders' => $nOrd, 'invoiced' => $invoiced, 'open' => $openOrders];

    /* ---- Bilgi amacli (engellemez) ---- */
    $docsDir = (defined('VESTRA_DOCS_DIR') ? VESTRA_DOCS_DIR : vestra_data_dir().'/docs').'/'.$safeUid;
    $docFiles = 0;
    if ($safeUid !== '' && is_dir($docsDir)) foreach (glob($docsDir.'/*') ?: [] as $df) if (is_file($df)) $docFiles++;
    $res['extras'] = [
        'stripe'    => !empty($victim['stripe_account_id']),
        'iban'      => !empty($victim['bank_iban']),
        'doc_files' => $docFiles,
    ];

    /* ---- STRICT: hesap hicbir seye sahip olmamali ---- */
    $links = [];
    if ($strict) {
        $links = ['listings' => 0, 'threads' => 0, 'samples' => 0, 'offers' => 0, 'picks' => 0, 'disk_invoices' => 0];
        $skuSet = [];
        foreach (vestra_listings() as $p) {
            if ((string)($p['seller_uid'] ?? '') !== $uid) continue;
            $links['listings']++;
            if ((string)($p['sku'] ?? '') !== '') $skuSet[(string)$p['sku']] = true;
        }
        require_once __DIR__.'/messages.php';
        foreach (vestra_msg_threads() as $t) {
            if ((string)($t['seller_uid'] ?? '') === $uid || (string)($t['buyer_uid'] ?? '') === $uid) $links['threads']++;
        }
        require_once __DIR__.'/samples.php';
        foreach (samples_all() as $sr) {
            if (!is_array($sr)) continue;
            if ((string)($sr['seller_uid'] ?? '') === $uid || (string)($sr['buyer_id'] ?? '') === $uid) $links['samples']++;
        }
        /* "Faturayi bu hesap kessin" secimi: silinen hesabi gosteren bir secim, o
           satisin faturasini kesilemez yapar (kayitsiz hesapta fatura kesilmez). */
        foreach (['order_statuses.json', 'offer_responses.json'] as $pf) {
            foreach (vestra_read_json($pf) as $e) {
                if (is_array($e) && (string)($e['invoice_seller_uid'] ?? '') === $uid) $links['picks']++;
            }
        }
        /* Bu saticinin ilanlarina verilmis teklifler (satici paneli teklifleri SKU ile buluyor). */
        foreach (vestra_read_csv('offers.csv') as $r) {
            if (isset($skuSet[(string)($r['sku'] ?? '')])) $links['offers']++;
        }
        /* Hesabin KESTIGI fatura dosyalari: <ref>__<hesap id>.json. */
        $links['disk_invoices'] = $safeUid === '' ? 0 : count(glob(vestra_invoice_dir().'/*__'.$safeUid.'.json') ?: []);
    }
    $res['links'] = $links;

    if ($invoiced > 0)    { $res['code'] = 'has_invoice'; $res['n'] = $invoiced;    return $res; }
    if ($openOrders > 0)  { $res['code'] = 'has_orders';  $res['n'] = $openOrders;  return $res; }
    if ($strict && array_sum($links) > 0) { $res['code'] = 'has_links'; $res['n'] = (int)array_sum($links); return $res; }
    if (!$apply) { $res['ok'] = true; return $res; }

    /* ---- TEKILLIK: tam olarak BIR hesap gitmeli. Yedekten ONCE bakilir: tekil olmayan
       bir dosyada yedek dosyalari da birakmak istemiyoruz (ayni id iki kayitta olursa
       ikisi birden silinirdi). ---- */
    $kept = array_values(array_filter(auth_accounts(), fn($a) => ($a['id'] ?? '') !== $uid));
    if (count($kept) !== $nBefore - 1) { $res['code'] = 'integrity'; return $res; }

    /* ---- YEDEKLER: hicbiri alinamazsa hicbir sey silinmez ---- */
    $dataDir = vestra_data_dir();
    $af = defined('VESTRA_ACCOUNTS') ? VESTRA_ACCOUNTS : $dataDir.'/accounts.json';
    $bakAcc = $af.'.bak.'.date('Ymd_His');
    if (!is_readable($af) || !@copy($af, $bakAcc) || !is_file($bakAcc) || (int)@filesize($bakAcc) < 1) {
        $res['code'] = 'backup'; return $res;
    }
    /* Silinen hesabin KENDI JSON yedegi. accounts.json'in tam kopyasi zaten alindi
       ama onun icinden tek hesabi bulmak, dosya buyudukce is haline geliyor. GDPR
       silme talebi de gelse, "yanlis hesabi sildim" kazasi da olsa, aranan sey tek
       bir kayit. */
    $ddir = $dataDir.'/deleted-accounts';
    if (!is_dir($ddir)) @mkdir($ddir, 0775, true);
    $bakOne = $ddir.'/'.preg_replace('/[^a-z0-9_-]/i', '', $uid).'-'.gmdate('Ymd-His').'.json';
    $w = @file_put_contents($bakOne, json_encode($victim + ['deleted_at' => gmdate('c')], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    $chk = is_file($bakOne) ? json_decode((string)@file_get_contents($bakOne), true) : null;
    if ($w === false || !is_array($chk) || (string)($chk['id'] ?? '') !== $uid) { $res['code'] = 'backup'; return $res; }

    $ls = vestra_listings();
    $mineL = 0;
    foreach ($ls as $l) if ((string)($l['seller_uid'] ?? '') === $uid) $mineL++;
    $bakL = '';
    if ($mineL > 0) {
        /* vestra_save_listings yedek ALMAZ; panelin Delete'i ilanlari bu yuzden
           yedeksiz siliyordu. */
        $lf = $dataDir.'/listings.json';
        $bakL = $lf.'.bak-del-'.date('Ymd-His');
        if (!@copy($lf, $bakL) || !is_file($bakL) || (int)@filesize($bakL) < 1) { $res['code'] = 'backup'; return $res; }
    }
    $res['backups'] = array_values(array_filter([basename($bakAcc), basename($bakOne), $bakL !== '' ? basename($bakL) : '']));

    /* ---- SIL: kayit yedek sirasinda degismis olabilir -- yeniden oku, yeniden say ---- */
    $kept = array_values(array_filter(auth_accounts(), fn($a) => ($a['id'] ?? '') !== $uid));
    if (count($kept) !== $nBefore - 1) { $res['code'] = 'integrity'; return $res; }
    auth_save_accounts($kept);
    if ($mineL > 0) {
        $ls = array_values(array_filter($ls, fn($l) => (string)($l['seller_uid'] ?? '') !== $uid));
        vestra_save_listings($ls);
    }

    /* ---- GERI OKU: "kaydedildi" yazan bir satir tek basina kanit degil ---- */
    $after = auth_accounts(); $gone = true;
    foreach ($after as $a) if ((string)($a['id'] ?? '') === $uid) $gone = false;
    $leftL = 0;
    foreach (vestra_listings() as $l) if ((string)($l['seller_uid'] ?? '') === $uid) $leftL++;
    $res['applied']  = true;
    $res['readback'] = ['account_gone' => $gone, 'accounts_before' => $nBefore, 'accounts_after' => count($after), 'listings_left' => $leftL];
    if (!$gone || count($after) !== $nBefore - 1 || $leftL > 0) { $res['code'] = 'readback'; return $res; }
    $res['ok'] = true;
    return $res;
}
