<?php
/* SATICI AYAK IZI (seller-products.yml → admin_mode=seller_footprint) — 3 Eki 2026,
 * operator: "Tyrex Internatioal BV yi saticilardan sil".
 *
 * Panelin kalici 'Delete' eylemi (admin.php: delete_account) hesabi siliyor ve
 * saticinin ILANLARINI yedeksiz cikariyor; kapisi yalniz orders.csv'ye bakiyor.
 * Bu adim silmeden ONCE neye dokunacagini olcer: SALT OKUNUR.
 *
 * Adim IS AKISININ ICINDEKI GERCEK PHP'den cikarilip kum havuzunda KOSTURULUYOR
 * (kaynak taramasi degil). Olculenler, iki yon:
 *   - hesaba bagli her sey sayiliyor (ilan / siparis / teklif / konusma / numune /
 *     diskteki fatura / kesen-secimi) ve KONTROL SATICISININ kayitlari HIC girmiyor
 *   - panel kapisinin cevabi ayni mantikla basiliyor (faturali -> engel, acik -> engel,
 *     hicbiri -> GECER)
 *   - e-posta maskeli, kisi adi yok, PHP uyarisi yok
 *   - veri dizini kosudan once ve sonra bayt bayt ayni (SALT OKUNUR)
 *   - belirsiz / olmayan / bos secim DURUR (cikis 1) */

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  FAIL ").$n."\n"; };
$root = dirname(__DIR__);

echo "== 1. adim is akisinda, salt okunur, panel kapisiyla ayni olcut ==\n";
$wf = (string)file_get_contents($root.'/.github/workflows/seller-products.yml');
$a  = strpos($wf, "admin_mode == 'seller_footprint'");
$t('adim var', $a !== false);
$blk = $a === false ? '' : substr($wf, $a, strpos($wf, "\n      - name:", $a) - $a);
$t('issue_ref envs ile sunucuya geciyor (SF_UID)', str_contains($blk, 'SF_UID: ${{ github.event.inputs.issue_ref }}') && str_contains($blk, 'envs: SF_UID'));
if (!preg_match("~<<'PHPEOF'\n(.*?)\n\s*PHPEOF~s", $blk, $m)) { echo "  FAIL php govdesi bulunamadi\n"; exit(1); }
$php  = preg_replace('/^            /m', '', $m[1]);
$code = preg_replace('~/\*.*?\*/~s', '', $php);
$t('hicbir yazici / silici / posta cagirmiyor',
   !preg_match('/vestra_write_json|vestra_write_csv|vestra_send_mail|file_put_contents|vestra_save_listings|vestra_msg_save_threads|auth_save_accounts|auth_update|sample_save|unlink\(|rename\(|copy\(|fopen\([^)]*[\'"][wax]/', $code));
$t('admin_mode aciklamasinda yeni mod anlatiliyor', (bool)preg_match('/admin_mode:\s*\n\s*description:\s*"[^\n]*seller_footprint/', $wf));

/* 3 Eki 2026: panelin satir ici kapisi inc/account_delete.php'ye tasindi (panel ve seller_delete
   AYNI fonksiyonu cagirir). Probe'un kopyasi artik O dosyayla esleniyor; admin.php'de ikinci kopya
   KALMADI (account_delete_test.php bunu ayrica tutuyor). */
$guard = (string)file_get_contents($root.'/vestra/inc/account_delete.php');
$t('ortak fonksiyonun kapisi: kapali durum listesi probe ile ayni',
   str_contains($guard, "['completed','cancelled','refunded']") && str_contains($code, "['completed', 'cancelled', 'refunded']"));
$t('ortak fonksiyonun kapisi: faturali siparis saymasi probe ile ayni mantik',
   str_contains($guard, 'count(vestra_invoices_for_ref($ref))>0') && str_contains($code, 'count($invs) > 0'));
$t('ortak fonksiyonun kapisi: durumu orders.csv satirindan okuyor (probe de AYNEN)',
   str_contains($guard, "(\$o['status']??'')") && str_contains($code, "(\$o['status'] ?? '')"));
$t('ortak fonksiyon: ilanlari silen satir hala orada, ama ONCE yedek aliniyor (probe bunu anlatiyor)',
   str_contains($guard, '$ls = array_values(array_filter($ls') && str_contains($guard, 'listings.json') && str_contains($guard, '.bak-del-')
   && str_contains($php, 'silmeden once listings.json yedeklenir'));

echo "\n== 2. kum havuzunda kostur ==\n";
$sand = sys_get_temp_dir().'/vestra_sfoot_'.bin2hex(random_bytes(4));
mkdir($sand.'/data/invoices', 0777, true);
$P = 'aaaa1111aaaa1111'; $C = 'cccc3333cccc3333'; $B1 = 'bbbb2222bbbb2222'; $B2 = 'dddd4444dddd4444';
$accounts = [
  ['id'=>$P, 'type'=>'seller', 'company'=>'Probe Seller BV', 'name'=>'Pat Probe', 'email'=>'probe.owner@probe-seller.example',
   'country'=>'Netherlands', 'status'=>'active', 'kyb_status'=>'approved', 'created_at'=>'2026-08-01T10:00:00+00:00',
   'stripe_account_id'=>'acct_TEST', 'bank_iban'=>'TESTBANK123', 'membership_status'=>'',
   'doc_requests'=>[['type'=>'trade_licence','status'=>'uploaded','file'=>'x.pdf'],['type'=>'id_document','status'=>'requested']]],
  ['id'=>$C, 'type'=>'seller', 'company'=>'Control Seller SL', 'name'=>'Cora Control', 'email'=>'cora@control-seller.example',
   'country'=>'Spain', 'status'=>'active', 'kyb_status'=>'approved'],
  ['id'=>$B1, 'type'=>'buyer', 'company'=>'Buyer One Ltd', 'name'=>'Bo One', 'email'=>'buyer.one@buyer1.example', 'country'=>'France', 'status'=>'active'],
  ['id'=>$B2, 'type'=>'buyer', 'company'=>'Buyer Two GmbH', 'name'=>'Bea Two', 'email'=>'buyer.two@buyer2.example', 'country'=>'Germany', 'status'=>'active'],
];
file_put_contents($sand.'/data/accounts.json', json_encode($accounts));
$L = fn($id, $sku, $st, $uid, $x = []) => $x + ['id'=>$id, 'sku'=>$sku, 'brand'=>'BrandX', 'name'=>'Name of '.$id, 'status'=>$st, 'seller_uid'=>$uid,
      'moq'=>10, 'tiers'=>[['min'=>10,'price'=>10.0]], 'images'=>[]];
file_put_contents($sand.'/data/listings.json', json_encode([
  $L('p-approved', 'SKU-P1', 'approved', $P),
  $L('p-pending',  'SKU-P2', 'pending',  $P),
  $L('p-rej',      'SKU-P3', 'rejected', $P, ['redirect_to'=>'p-approved', 'sold_out'=>true]),
  $L('c-approved', 'SKU-C1', 'approved', $C),
]));
$head = ['timestamp','ref','company','vat','name','email','country','phone','items','subtotal','commission','payout','total','notes','consent','terms_reply_unused','voucher_code','discount','shipping','shipping_label'];
$ord = fn($ref, $co, $email, $items) => ['2026-09-20T10:00:00+00:00', $ref, $co, '', 'Person Name', $email, 'France', '', $items, '10', '0', '10', '10', 'Payment: Bank transfer.', '', '', '', '', '0', ''];
$h = fopen($sand.'/data/orders.csv', 'w'); fputcsv($h, $head, ',', '"', '\\');
foreach ([
  $ord('VES-A1', 'Buyer One Ltd', 'buyer.one@buyer1.example', '1x SKU-P1 @10.00'),                 // SATICI + faturali
  $ord('VES-A2', 'Buyer Two GmbH', 'buyer.two@buyer2.example', '1x SKU-C1 @10.00'),                // kontrol: girmemeli
  $ord('VES-A3', 'Buyer One Ltd', 'buyer.one@buyer1.example', '1x SKU-C1 @10.00'),                 // yalniz KESEN-SECIMI
  $ord('VES-A4', 'Probe Seller BV', 'probe.owner@probe-seller.example', '1x SKU-C1 @10.00'),       // ALICI (iptal ama panel ACIK sayar)
] as $r) fputcsv($h, $r, ',', '"', '\\');
fclose($h);
file_put_contents($sand.'/data/invoices/VES-A1__vestra.json', json_encode(['no'=>'INV-T-1','ref'=>'VES-A1','seller_key'=>'vestra','currency'=>'EUR','total'=>10,'issued_at'=>'2026-09-21T09:00:00+00:00']));
file_put_contents($sand.'/data/invoices/VES-A5__'.$P.'.json',  json_encode(['no'=>'INV-T-9','ref'=>'VES-A5','seller_key'=>$P,'currency'=>'EUR','total'=>55,'issued_at'=>'2026-09-22T09:00:00+00:00']));
file_put_contents($sand.'/data/order_statuses.json', json_encode([
  'VES-A1' => ['status'=>'pending'],
  'VES-A3' => ['status'=>'pending', 'invoice_seller_uid'=>$P],
  'VES-A4' => ['status'=>'cancelled'],
]));
$oh = ['timestamp','ref','sku','product','qty','offer_unit','offer_total','company','email','message'];
$off = fn($ref, $sku, $co) => ['2026-09-25T10:00:00+00:00', $ref, $sku, 'P', '10', '9', '90', $co, 'x@y.example', ''];
$h = fopen($sand.'/data/offers.csv', 'w'); fputcsv($h, $oh, ',', '"', '\\');
foreach ([$off('OFR-P1', 'SKU-P1', 'Buyer One Ltd'), $off('OFR-C1', 'SKU-C1', 'Buyer Two GmbH'), $off('OFR-C2', 'SKU-C1', 'Buyer One Ltd')] as $r) fputcsv($h, $r, ',', '"', '\\');
fclose($h);
file_put_contents($sand.'/data/offer_responses.json', json_encode([
  'OFR-P1' => ['status'=>'accept'],
  'OFR-C1' => ['status'=>'accept'],
  'OFR-C2' => ['status'=>'counter', 'invoice_seller_uid'=>$P],
]));
$thr = fn($id, $buyer, $seller, $lid, $msgs) => ['id'=>$id, 'buyer_uid'=>$buyer, 'seller_uid'=>$seller, 'listing_id'=>$lid, 'messages'=>$msgs, 'last_at'=>'2026-09-30T10:00:00+00:00'];
file_put_contents($sand.'/data/messages.json', json_encode([
  $thr('thr0000000000001', $B1, $P, 'p-approved', [['from'=>$B1,'text'=>'hello','at'=>'2026-09-30T09:00:00+00:00'], ['from'=>$P,'text'=>'hi','at'=>'2026-09-30T10:00:00+00:00']]),
  $thr('thr0000000000002', $B2, $P, 'p-pending',  [['from'=>$B2,'text'=>'question','at'=>'2026-09-29T09:00:00+00:00']]),
  $thr('thr0000000000003', $B1, $C, 'c-approved', [['from'=>$B1,'text'=>'control thread','at'=>'2026-09-28T09:00:00+00:00']]),
]));
file_put_contents($sand.'/data/blocked_messages.json', '[]');
file_put_contents($sand.'/data/samples.json', json_encode([
  'SPL-T1' => ['ref'=>'SPL-T1', 'seller_uid'=>$P, 'buyer_id'=>$B1, 'status'=>'pending', 'sku'=>'SKU-P1', 'created'=>'2026-09-26T10:00:00+00:00'],
  'SPL-T2' => ['ref'=>'SPL-T2', 'seller_uid'=>$C, 'status'=>'paid',    'sku'=>'SKU-C1', 'created'=>'2026-09-26T10:00:00+00:00'],
]));
$h = fopen($sand.'/data/request_offers.csv', 'w'); fputcsv($h, ['timestamp','ref','request_ref','company','email','unit_price'], ',', '"', '\\');
fputcsv($h, ['2026-09-27T10:00:00+00:00', 'RO1', 'RQ1', 'Probe Seller BV', 'x@y.example', '5'], ',', '"', '\\');
fputcsv($h, ['2026-09-27T10:00:00+00:00', 'RO2', 'RQ1', 'Control Seller SL', 'x@y.example', '5'], ',', '"', '\\');
fclose($h);

/* .htaccess HARIC: vestra_invoice_dir() koruma dosyasini ilk erisimde yaziyor --
   sunucuda zaten var, is verisi degil. Olculen sey kayitlar. */
$snap = function () use ($sand) {
  $o = [];
  foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sand.'/data', FilesystemIterator::SKIP_DOTS)) as $f) {
    if (basename((string)$f) === '.htaccess') continue;
    $o[substr((string)$f, strlen($sand))] = sha1_file((string)$f);
  }
  ksort($o); return $o;
};
$run = str_replace("\$doc = getenv('HOME').'/public_html';", "\$doc = ".var_export($root.'/vestra', true).';', $php);
$t('belge koku satiri bulundu (kum havuzuna yonlendirilebildi)', $run !== $php);
$prelude = "<?php\ndefine('VESTRA_DATA_DIR', ".var_export($sand.'/data', true).");\n"
         . "define('VESTRA_ACCOUNTS', ".var_export($sand.'/data/accounts.json', true).");\n"
         . "define('VESTRA_MESSAGES', ".var_export($sand.'/data/messages.json', true).");\n"
         . "define('VESTRA_BLOCKED_MESSAGES', ".var_export($sand.'/data/blocked_messages.json', true).");\n"
         . "define('VESTRA_SAMPLES', ".var_export($sand.'/data/samples.json', true).");\n";
file_put_contents($sand.'/run.php', $prelude.preg_replace('/^<\?php\s*/', '', $run));
$exec = function (string $sel) use ($sand): array {
  $cmd = 'SF_UID='.escapeshellarg($sel).' '.escapeshellarg(PHP_BINARY).' '.escapeshellarg($sand.'/run.php').' 2>&1';
  exec($cmd, $lines, $rc);
  return [implode("\n", $lines), $rc];
};
$before = $snap();
[$out, $rc] = $exec($P);
$t('kostu, cikis 0', $rc === 0);
$t('PHP uyarisi / hatasi yok', !preg_match('/(Warning|Notice|Deprecated|Fatal error|Uncaught)/', $out));
$t('salt okunur: veri dizini kosudan sonra bayt bayt ayni', $snap() === $before);

echo "\n== 3. hesap ==\n";
$t('uid ve firma', str_contains($out, 'id        : '.$P) && str_contains($out, 'firma     : Probe Seller BV'));
$t('tip/durum/kyb', str_contains($out, 'seller / active') && str_contains($out, 'kyb=approved'));
$t('Stripe ve IBAN yalniz VAR (deger yok)', str_contains($out, 'Stripe    : VAR | IBAN: VAR') && !str_contains($out, 'acct_TEST') && !str_contains($out, 'TESTBANK123'));
$t('belge durumlari', str_contains($out, 'trade_licence:uploaded') && str_contains($out, 'id_document:requested') && str_contains($out, 'dosyali 1'));
$t('e-posta MASKELI, ham adres ve kisi adi YOK',
   str_contains($out, 'p***@probe-seller.example') && !str_contains($out, 'probe.owner@') && !str_contains($out, 'Pat Probe') && !str_contains($out, 'buyer.one@'));

echo "\n== 4. ilanlar (ham liste, her durum) ==\n";
$t('toplam 3, durum dagilimi', str_contains($out, 'toplam 3 |') && str_contains($out, 'approved=1') && str_contains($out, 'pending=1') && str_contains($out, 'rejected=1'));
$t('uc ilan da listede, SATILDI ve yonlendirme isaretli',
   str_contains($out, 'p-approved') && str_contains($out, 'p-pending') && str_contains($out, 'p-rej') && str_contains($out, '[SATILDI]') && str_contains($out, '[->p-approved]'));
$t('KONTROL: baska saticinin ilani yok', !str_contains($out, 'c-approved'));

echo "\n== 5. siparisler ve panel kapisi ==\n";
$sec = function (string $title) use ($out): string {
  $p = strpos($out, '===== '.$title); if ($p === false) return '';
  $q = strpos($out, "\n=====", $p + 5);
  return substr($out, $p, ($q === false ? strlen($out) : $q) - $p);
};
$ords = $sec('SIPARISLER');
$t('SATICI + faturali siparis: fatura numarasi ve kesen', preg_match('/VES-A1 .*SATICI\s+INV-T-1\(VESTRA\)/', $ords) === 1);
$t('yalniz KESEN-SECIMI olan siparis', preg_match('/VES-A3 .*KESEN-SECIMI\s+faturasiz/', $ords) === 1);
$t('ALICI siparisi, GERCEK durum (cancelled) yaninda', preg_match('/VES-A4 .*cancelled\s+ALICI\s+faturasiz/', $ords) === 1);
$t('KONTROL: baska alicinin / baska saticinin siparisi yok', !str_contains($ords, 'VES-A2'));
$t('panel kapisi: ENGELLER (faturali siparis 1)', str_contains($out, "panel 'Delete' kapisi    : ENGELLER (faturali siparis 1)"));
$t('panel ilanlari hesapla birlikte siler (yedekli): 3 ilan, 1 approved', str_contains($out, 'panel \'Delete\' ilanlari  : 3 ilan hesapla birlikte silinir (1 approved)'));

echo "\n== 6. diskteki fatura / teklif / konusma / numune ==\n";
$t('bu hesabin kestigi fatura (siparis listesinde olmayan ref)', str_contains($sec('BU HESABIN KESTIGI'), 'INV-T-9') && str_contains($sec('BU HESABIN KESTIGI'), 'VES-A5'));
$of = $sec('TEKLIFLER');
$t('teklif: SKU ile gelen + kesen-secimi olan, kontrol YOK', str_contains($of, 'OFR-P1') && str_contains($of, 'OFR-C2') && str_contains($of, 'kesen-secimi') && !str_contains($of, 'OFR-C1'));
$th = $sec('KONUSMALAR');
$t('konusma: iki konusma, satici yazan sayisi dogru, kontrol YOK',
   str_contains($th, 'thr0000000000001') && str_contains($th, 'thr0000000000002') && !str_contains($th, 'thr0000000000003')
   && preg_match('/thr0000000000001.*mesaj=2 \(satici yazan 1\)/', $th) === 1 && preg_match('/thr0000000000002.*mesaj=1 \(satici yazan 0\)/', $th) === 1);
$t('konusmada alici FIRMA adi (e-posta degil)', str_contains($th, 'Buyer One Ltd') && str_contains($th, 'Buyer Two GmbH'));
$t('konusma satirinda thread id KARAKTER ARASI BOSLUKLU da var (Actions maskesine takilmasin)',
   str_contains($th, 'id='.implode(' ', str_split('thr0000000000001'))) && str_contains($th, 'id='.implode(' ', str_split('thr0000000000002'))));
$sm = $sec('NUMUNE');
$t('numune: yalniz bu satici, acik; istek teklifi yalniz bu firma', str_contains($sm, 'SPL-T1') && !str_contains($sm, 'SPL-T2') && str_contains($sm, 'numune: 1 (acik 1)') && str_contains($sm, 'istek teklifi (uid ya da firma adi gecen satir): 1'));
$t('numune satirinda ALICI firma adi + hesap ID (karakter arasi bosluklu: Actions maskesi), e-posta YOK',
   str_contains($sm, 'alici=Buyer One Ltd') && str_contains($sm, 'id='.implode(' ', str_split($B1)))
   && !str_contains($sm, $B1) && !str_contains($sm, '@'));
$t('ozet satiri: kapinin GORMEDIGI baglar tam sayilarla',
   str_contains($out, 'teklif 2 (faturali 0, yalniz kesen-secimi 1)') && str_contains($out, 'konusma 2 (3 mesaj, satici yazan 1)')
   && str_contains($out, 'numune 1 (acik 1)') && str_contains($out, 'siparis kesen-secimi 1') && str_contains($out, 'diskte fatura 1') && str_contains($out, 'istek teklifi 1'));
$t('son satir: salt okunur beyani', str_contains($out, '(salt okunur: hicbir sey yazilmadi, kimseye gonderilmedi)'));

echo "\n== 7. secim: firma parcasi, belirsiz, yok, bos ==\n";
[$o2, $r2] = $exec('Probe Seller');
$t('firma parcasi TAM 1 hesap -> ayni sonuc', $r2 === 0 && str_contains($o2, 'id        : '.$P));
[$o3, $r3] = $exec('Seller');
$t('belirsiz (2 hesap) -> DURUR, adaylar listelenir, hicbir ayak izi yok', $r3 === 1 && str_contains($o3, '2 hesap') && str_contains($o3, $P) && str_contains($o3, $C) && !str_contains($o3, '===== ILANLAR'));
[$o4, $r4] = $exec('yok-boyle-bir-hesap');
$t('olmayan -> DURUR', $r4 === 1 && str_contains($o4, '0 hesap'));
[$o5, $r5] = $exec('');
$t('bos -> DURUR', $r5 === 1 && str_contains($o5, 'issue_ref bos'));

echo "\n== 8. kapi cevabi: faturali yok -> acik siparis (panel iptali de ACIK sayar), hicbiri yok -> GECER ==\n";
@unlink($sand.'/data/invoices/VES-A1__vestra.json');
[$o6, $r6] = $exec($P);
$t('faturali siparis kalmayinca: ENGELLER (acik siparis 2) -- SATICI siparisi + iptal ama panelce acik sayilan ALICI siparisi',
   $r6 === 0 && str_contains($o6, "panel 'Delete' kapisi    : ENGELLER (acik siparis 2)"));
file_put_contents($sand.'/data/orders.csv', implode(',', $head)."\n");
[$o7, $r7] = $exec($P);
$t('siparis kalmayinca: GECER (ilanlar yine yedeksiz silinir uyarisi duruyor)',
   $r7 === 0 && str_contains($o7, "panel 'Delete' kapisi    : GECER") && str_contains($o7, '3 ilan hesapla birlikte silinir')
   && str_contains($o7, '(bu hesaba bagli siparis yok)'));
[$o8, $r8] = $exec($C);
$t('KONTROL SATICISI: yalniz kendi ilani, bu satici hesabinin kayitlari girmiyor',
   $r8 === 0 && str_contains($o8, 'toplam 1 |') && str_contains($o8, 'c-approved') && !str_contains($o8, 'p-approved') && !str_contains($o8, 'thr0000000000001')
   && str_contains($o8, 'konusma 1 (1 mesaj, satici yazan 0)') && str_contains($o8, 'numune 1 (acik 1)'));

$rm = function (string $d) use (&$rm) { foreach (glob($d.'/{,.}*', GLOB_BRACE) ?: [] as $f) { if (in_array(basename($f), ['.', '..'], true)) continue; is_dir($f) ? $rm($f) : @unlink($f); } @rmdir($d); };
$rm($sand);
echo "\n".($bad ? "FAIL: {$bad}" : "hepsi yesil").", {$ok} ok\n";
exit($bad ? 1 : 0);
