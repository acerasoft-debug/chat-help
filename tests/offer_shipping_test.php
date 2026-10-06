<?php
/* KABUL EDILMIS TEKLIFE NAVLUN, FATURA KESMEDEN (29 Eyl 2026, O34FE5;
 * operator: "O34FE5 bu siparise 120 eur shipp cost yaz").
 *
 * Teklif faturasi navlunu teklifin KENDI kaydindan okuyor
 * (offer_responses.json -> invoice_shipping). O alana yalniz belgeyi
 * KESEN / YENIDEN CIZEN yollar yaziyordu; is akisinin admin_mode=shipping'i
 * ise yalniz orders.csv'ye bakiyordu (faturasiz teklifte "siparis
 * bulunamadi", faturali teklifte belgenin OKUMADIGI kopyayi yazma).
 *
 * KUM HAVUZUNDA GERCEKTEN YAZIYOR ve is akisi adimini bir SITE KOPYASINDA
 * KOSTURUYOR. IKI YON: hedef kayit ve fatura yuku degisir; kaydin diger
 * alanlari, BASKA teklif, kabul edilmemis / faturali / uye / odenmis teklif ve
 * siparis dali DEGISMEZ. */

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  HATA ").$n."\n"; };

$root = dirname(__DIR__);
$sand = sys_get_temp_dir().'/vestra_offship_'.bin2hex(random_bytes(4));
mkdir($sand.'/data', 0777, true);
if (!defined('VESTRA_DATA_DIR')) define('VESTRA_DATA_DIR', $sand.'/data');
if (!defined('VESTRA_ACCOUNTS')) define('VESTRA_ACCOUNTS', $sand.'/data/accounts.json');
require_once $root.'/vestra/inc/products.php';
require_once $root.'/vestra/inc/offers.php';
require_once $root.'/vestra/inc/orders.php';
require_once $root.'/vestra/inc/invoice.php';
if (vestra_data_dir() !== $sand.'/data' || vestra_invoice_dir() !== $sand.'/data/invoices') {
    fwrite(STDERR, "kum havuzu kurulamadi\n"); exit(1);
}

/* SENTETIK veri: gercek bir musterinin adi/adresi buraya YAZILMAZ (depo herkese
   acik). SKU'lar katalogda YOK -- ilan cozulemez, kesen taraf platforma duser;
   olculen sey navlun, satici degil. */
$seed = function (string $dir): void {
    @mkdir($dir.'/invoices', 0777, true);
    $h = fopen($dir.'/offers.csv', 'w');
    fputcsv($h, ['timestamp','ref','sku','product','qty','offer_unit','offer_total','company','email','message'], ',', '"', '\\');
    foreach ([
        ['O34FE5', 320, 30], ['O2ACC0', 10, 50], ['O3PEND', 5, 40], ['O4INVD', 10, 10],
        ['O5MEMB', 4, 25], ['O6PAID', 2, 60], ['O7ORDR', 20, 30],
    ] as [$ref, $qty, $unit]) {
        fputcsv($h, ['2026-09-29T15:09:43+00:00', $ref, 'ZZ-TEST-'.$ref, 'Test Sweatpants', $qty, $unit, $qty * $unit,
                     'Test SAS', 'buyer@example.com', 'test'], ',', '"', '\\');
    }
    fclose($h);
    file_put_contents($dir.'/offer_responses.json', json_encode([
        'O34FE5' => ['status' => 'accept', 'responded_at' => '2026-09-29T15:30:00+00:00',
                     'invoice_vat_note' => 'Intra-Community supply', 'counters' => []],
        'O2ACC0' => ['status' => 'accept', 'invoice_shipping' => 15],
        'O3PEND' => ['status' => 'counter', 'counter_price' => 45],
        'O4INVD' => ['status' => 'accept', 'invoice_shipping' => 0, 'invoice_members' => ['O4INVD', 'O5MEMB']],
        'O5MEMB' => ['status' => 'accept', 'invoice_group_ref' => 'O4INVD'],
        'O6PAID' => ['status' => 'accept', 'invoice_paid_at' => '2026-09-20T10:00:00+00:00'],
        'O7ORDR' => ['status' => 'accept'],
    ], JSON_PRETTY_PRINT));
    file_put_contents($dir.'/invoices/O4INVD__vestra.json', json_encode(
        ['no' => 'INV-2026-9001', 'seller_key' => 'vestra', 'total' => 100, 'currency' => 'EUR', 'issued_at' => '2026-09-20']));
    file_put_contents($dir.'/accounts.json', json_encode([
        ['id' => 'b1', 'type' => 'buyer', 'email' => 'buyer@example.com', 'company' => 'Test SAS', 'country' => 'France'],
    ]));
    $h = fopen($dir.'/orders.csv', 'w');
    fputcsv($h, ['ref','company','email','country','items','subtotal','commission','payout','total','notes','discount','shipping','shipping_label'], ',', '"', '\\');
    fputcsv($h, ['O7ORDR', 'Test SAS', 'buyer@example.com', 'France', '20x ZZ-TEST-O7ORDR @30.00', '600.00', '0.00', '600.00', '600.00',
                 'Payment: Bank transfer. Created from accepted offer(s) O7ORDR — invoiced together.', '', '', ''], ',', '"', '\\');
    /* Faturali teklifin siparis KOPYASI: eski adim bunu yaziyordu (belgenin
       okumadigi yer). Yeni adim ona DOKUNMAMALI. */
    fputcsv($h, ['O4INVD', 'Test SAS', 'buyer@example.com', 'France', '10x ZZ-TEST-O4INVD @10.00', '100.00', '0.00', '100.00', '100.00',
                 'Payment: Bank transfer.', '', '', ''], ',', '"', '\\');
    fputcsv($h, ['VES-TEST1', 'Other GmbH', 'other@example.com', 'Germany', '5x ZZ-OTHER @10.00', '50.00', '0.00', '50.00', '50.00',
                 'Payment: Bank transfer.', '', '', ''], ',', '"', '\\');
    fclose($h);
};
$seed($sand.'/data');
$rec = fn(string $ref, string $dir = '') => (array)(json_decode((string)file_get_contents(($dir ?: $sand.'/data').'/offer_responses.json'), true)[$ref] ?? []);
$ordRow = function (string $ref, string $dir = '') use ($sand) {
    $f = ($dir ?: $sand.'/data').'/orders.csv';
    $h = fopen($f, 'r'); $head = fgetcsv($h, null, ',', '"', '\\');
    while (($r = fgetcsv($h, null, ',', '"', '\\')) !== false) {
        $r = array_combine($head, array_slice(array_pad($r, count($head), ''), 0, count($head)));
        if (($r['ref'] ?? '') === $ref) { fclose($h); return $r; }
    }
    fclose($h); return null;
};
$backups = fn() => glob($sand.'/data/offer_backups/*-ship-*.json') ?: [];

echo "== 1. KURU KOSU: rakamlar doner, HICBIR SEY yazilmaz ==\n";
$before = (string)file_get_contents($sand.'/data/offer_responses.json');
$r = vestra_offer_set_invoice_shipping('O34FE5', 120.0, true);
$t('kuru kosu ok', !empty($r['ok']) && !empty($r['dry']));
$t('onceki navlun: kayitta YOK (null)', array_key_exists('prev', $r) && $r['prev'] === null);
$t('mal toplami 320 x 30 = 9600 (fatura yukunden)', abs((float)($r['goods'] ?? 0) - 9600.0) < 0.005);
$t('fatura toplami 9720', abs((float)($r['total'] ?? 0) - 9720.0) < 0.005);
$t('belge birimi EUR', ($r['doc_currency'] ?? '') === 'EUR');
$t('kayit dosyasi BAYT BAYT ayni', (string)file_get_contents($sand.'/data/offer_responses.json') === $before);
$t('yedek YAZILMADI', $backups() === []);

echo "\n== 2. UYGULAMA: kayit + fatura yuku + yedek ==\n";
$r = vestra_offer_set_invoice_shipping('O34FE5', 120.0);
$t('yazildi', !empty($r['ok']) && empty($r['dry']) && empty($r['error']));
$b = $rec('O34FE5');
$t('invoice_shipping = 120', array_key_exists('invoice_shipping', $b) && abs((float)$b['invoice_shipping'] - 120.0) < 0.005);
$t('iz: by=operator + tarih', ($b['invoice_shipping_by'] ?? '') === 'operator' && strtotime((string)($b['invoice_shipping_at'] ?? '')) > 0);
$t('kaydin DIGER alanlari korundu (durum, KDV satiri, yanit tarihi)',
   ($b['status'] ?? '') === 'accept' && ($b['invoice_vat_note'] ?? '') === 'Intra-Community supply'
   && ($b['responded_at'] ?? '') === '2026-09-29T15:30:00+00:00');
$p = vestra_offer_invoice_payload('O34FE5');
$t('FATURA YUKU navlunu 120 goruyor', abs((float)($p['meta']['shipping'] ?? -1) - 120.0) < 0.005);
$t('fatura yuku kalemi degismedi (320 x 30)', (int)($p['items'][0]['qty'] ?? 0) === 320 && abs((float)($p['items'][0]['unit'] ?? 0) - 30.0) < 0.005);
$t('okuyucu (vestra_offer_invoice_shipping) 120 donuyor', abs(vestra_offer_invoice_shipping($b, [], []) - 120.0) < 0.005);
$bk = $backups();
$t('yedek dosyasi VAR (offer_backups/<ref>-ship-*)', count($bk) === 1 && str_contains($bk[0], '/offer_backups/O34FE5-ship-'));
$bj = json_decode((string)@file_get_contents($bk[0] ?? ''), true);
$t('yedek DEGISIKLIKTEN ONCEKI kaydi tasiyor (navlun yok)', is_array($bj) && isset($bj['O34FE5']) && !array_key_exists('invoice_shipping', $bj['O34FE5']));
$t('yedek YALNIZ o kaydi tasiyor', is_array($bj) && count($bj) === 1);
$t('BASKA teklif degismedi (O2ACC0 navlun 15)', abs((float)($rec('O2ACC0')['invoice_shipping'] ?? -1) - 15.0) < 0.005);
$t('KESIM YOK: O34FE5 icin fatura dosyasi olusmadi', (glob($sand.'/data/invoices/O34FE5__*') ?: []) === []);
$t('KESIM YOK: O34FE5 icin siparis satiri olusmadi', $ordRow('O34FE5') === null);
$t('siparis satiri yoktu -> order_row=false', ($r['order_row'] ?? null) === false);

echo "\n== 3. ACIK 0 bir karardir: alan SILINMEZ, 0 yazilir ==\n";
$r = vestra_offer_set_invoice_shipping('O2ACC0', 0.0);
$b = $rec('O2ACC0');
$t('0 yazildi', !empty($r['ok']));
$t('alan DURUYOR ve 0 (array_key_exists -- "navlun yok" karari)', array_key_exists('invoice_shipping', $b) && (float)$b['invoice_shipping'] === 0.0);
$t('onceki deger 15 raporlandi', abs((float)($r['prev'] ?? -1) - 15.0) < 0.005);

echo "\n== 4. RED -- ve reddedilen kayit DEGISMEZ ==\n";
$snap = (string)file_get_contents($sand.'/data/offer_responses.json');
$r = vestra_offer_set_invoice_shipping('O3PEND', 50.0);
$t('kabul edilmemis teklif REDDEDILIR', ($r['code'] ?? '') === 'not_accepted');
$r = vestra_offer_set_invoice_shipping('O4INVD', 50.0);
$t('faturali teklif REDDEDILIR (invoiced)', ($r['code'] ?? '') === 'invoiced');
$t('ret numarayi soyluyor', in_array('INV-2026-9001', (array)($r['invoices'] ?? []), true));
$t('ret KURAL 5f yolunu soyluyor', str_contains((string)($r['error'] ?? ''), 'invoice_draft'));
$r = vestra_offer_set_invoice_shipping('O5MEMB', 50.0);
$t('birlesik fatura UYESI reddedilir, birincili soyler', ($r['code'] ?? '') === 'member' && ($r['primary'] ?? '') === 'O4INVD');
$r = vestra_offer_set_invoice_shipping('O6PAID', 50.0);
$t('parasi gelmis satis REDDEDILIR', ($r['code'] ?? '') === 'paid');
$t('olmayan ref REDDEDILIR', (vestra_offer_set_invoice_shipping('OFFFFF', 50.0)['code'] ?? '') === 'missing');
$t('negatif tutar REDDEDILIR', (vestra_offer_set_invoice_shipping('O34FE5', -5.0)['code'] ?? '') === 'amount');
$t('NAN REDDEDILIR', (vestra_offer_set_invoice_shipping('O34FE5', NAN)['code'] ?? '') === 'amount');
$t('butun retlerden sonra kayit dosyasi BAYT BAYT ayni', (string)file_get_contents($sand.'/data/offer_responses.json') === $snap);

echo "\n== 5. siparis satiri VARSA o da ayni rakama cekilir ==\n";
$r = vestra_offer_set_invoice_shipping('O7ORDR', 120.0);
$o = $ordRow('O7ORDR');
$t('ok + order_row=true', !empty($r['ok']) && ($r['order_row'] ?? null) === true);
$t('siparis satiri navlunu 120', abs((float)($o['shipping'] ?? -1) - 120.0) < 0.005);
$t('siparis toplami 600 + 120 = 720', abs((float)($o['total'] ?? -1) - 720.0) < 0.005);
$t('siparis toplami fatura toplamiyla ayni', abs((float)($o['total'] ?? -1) - (float)($r['total'] ?? -2)) < 0.005);

echo "\n== 6. IS AKISI ADIMI -- site kopyasinda KOSTURULUYOR ==\n";
$wf = (string)file_get_contents($root.'/.github/workflows/seller-products.yml');
$a  = strpos($wf, "cat > /tmp/vestra_ship.php <<'PHPEOF'");
$e  = $a === false ? false : strpos($wf, "\n            PHPEOF", $a);
$php = '';
if ($a !== false && $e !== false) {
    $blk = substr($wf, strpos($wf, "\n", $a) + 1, $e - strpos($wf, "\n", $a) - 1);
    $php = implode("\n", array_map(fn($l) => preg_replace('/^ {12}/', '', $l), explode("\n", $blk)))."\n";
}
$t('adim betigi cikarildi', str_starts_with($php, '<?php'));

$home = $sand.'/home';
$site = $home.'/public_html';
@mkdir($site, 0777, true);
exec('cp -r '.escapeshellarg($root.'/vestra/inc').' '.escapeshellarg($site.'/inc'));
$t('site kopyasi kuruldu (inc)', is_file($site.'/inc/offers.php'));
file_put_contents($sand.'/ship.php', $php);
$run = function (string $ref, string $val) use ($home, $site, $sand, $seed): array {
    exec('rm -rf '.escapeshellarg($site.'/data'));
    @mkdir($site.'/data', 0777, true);
    $seed($site.'/data');
    $cmd = 'HOME='.escapeshellarg($home).' SHIP_REF='.escapeshellarg($ref).' SHIP_VAL='.escapeshellarg($val)
         .' php -d display_errors=stderr '.escapeshellarg($sand.'/ship.php').' 2>&1';
    $out = []; $rc = 0; exec($cmd, $out, $rc);
    return [$rc, implode("\n", $out)];
};
$sd = $site.'/data';

[$rc, $out] = $run('O34FE5', '120');
$t('O34FE5 120 -> rc 0', $rc === 0);
$t('teklif dalina girdi', str_contains($out, 'teklif           : O34FE5'));
$t('FATURA TOPLAMI 9,720.00 EUR basiliyor', str_contains($out, 'FATURA TOPLAMI   : 9,720.00 EUR'));
$t('KAYDEDILDI + kesim YAPILMADI', str_contains($out, 'KAYDEDILDI') && str_contains($out, 'kesim            : YAPILMADI'));
$t('kayit 120', abs((float)($rec('O34FE5', $sd)['invoice_shipping'] ?? -1) - 120.0) < 0.005);
$t('"siparis bulunamadi" DEMEDI', !str_contains($out, 'siparis bulunamadi'));
$t('PHP uyarisi yok', !preg_match('/\b(Warning|Notice|Deprecated|Fatal)\b/', $out));

[$rc, $out] = $run('O34FE5', '120|dry=1');
$t('dry=1 -> rc 0 + KURU KOSU', $rc === 0 && str_contains($out, 'KURU KOSU'));
$t('dry=1 -> kayit YAZILMADI', !array_key_exists('invoice_shipping', $rec('O34FE5', $sd)));

[$rc, $out] = $run('O34FE5', '100 USD');
$t('USD -> REDDEDILIR (teklif kaydi EUR)', $rc !== 0 && str_contains($out, 'EUR yazin'));
$t('USD -> kayit yazilmadi', !array_key_exists('invoice_shipping', $rec('O34FE5', $sd)));

[$rc, $out] = $run('O34FE5', 'auto');
$t("'auto' -> REDDEDILIR", $rc !== 0 && !array_key_exists('invoice_shipping', $rec('O34FE5', $sd)));

/* Probe'lar para birimi dalina TAKILMAMALI (sonu uc harf degil): 'abc' ilk
   yazimda orada reddediliyordu ve tutar dogrulamasi silindiginde bu iddia
   YESIL kaldi -- olctugu sey dogrulama degildi. */
[$rc, $out] = $run('O34FE5', 'x');
$t("okunamayan tutar 'x' REDDEDILIR (0 diye YAZILMAZ)", $rc !== 0 && str_contains($out, 'tutar okunamadi')
   && !array_key_exists('invoice_shipping', $rec('O34FE5', $sd)));
[$rc, $out] = $run('O34FE5', '12O');
$t("yazim hatasi '12O' REDDEDILIR (12 diye YAZILMAZ)", $rc !== 0 && !array_key_exists('invoice_shipping', $rec('O34FE5', $sd)));

[$rc, $out] = $run('O34FE5', '120 EUR|Air freight');
$t("'120 EUR' kabul, etiket yok sayildi ve SOYLENDI", $rc === 0 && str_contains($out, 'ETIKETI yok')
   && abs((float)($rec('O34FE5', $sd)['invoice_shipping'] ?? -1) - 120.0) < 0.005);

/* ESKI HATANIN YONU: faturali teklifte adim orders.csv KOPYASINI yaziyordu. */
[$rc, $out] = $run('O4INVD', '50||allow_invoiced=1');
$o = $ordRow('O4INVD', $sd);
$t('faturali teklif -> rc != 0', $rc !== 0);
$t('ret KURAL 5f yolunu soyluyor', str_contains($out, 'invoice_draft'));
$t('siparis KOPYASINA dokunulmadi (navlun bos, toplam 100)', ($o['shipping'] ?? 'x') === '' && abs((float)($o['total'] ?? -1) - 100.0) < 0.005);
$t('teklif kaydi degismedi (navlun 0)', (float)($rec('O4INVD', $sd)['invoice_shipping'] ?? -1) === 0.0);

/* KONTROL GRUBU: teklif OLMAYAN siparis ref'i eski yoldan yurur. */
[$rc, $out] = $run('VES-TEST1', '20');
$o = $ordRow('VES-TEST1', $sd);
$t('siparis ref -> rc 0 (siparis dali)', $rc === 0 && !str_contains($out, 'teklif           :'));
$t('siparis satiri navlun 20, toplam 70', abs((float)($o['shipping'] ?? -1) - 20.0) < 0.005 && abs((float)($o['total'] ?? -1) - 70.0) < 0.005);

echo "\n== 7. kablolama ==\n";
$code = preg_replace('~/\*.*?\*/~s', '', $php);
$pOffer = strpos($code, 'if (vestra_offer_row($ref))');
$pOrder = strpos($code, "foreach (vestra_read_csv('orders.csv')");
$t('teklif dali siparis aramasindan ONCE', $pOffer !== false && $pOrder !== false && $pOffer < $pOrder);
$t('teklif dali TEK yaziciyi cagiriyor', str_contains($code, 'vestra_offer_set_invoice_shipping($ref'));
$offerBlk = $pOffer !== false && $pOrder !== false ? substr($code, $pOffer, $pOrder - $pOffer) : '';
$t('teklif dali mektup GONDERMIYOR', $offerBlk !== '' && !str_contains($offerBlk, 'vestra_send_mail'));
$t('teklif dali FATURA KESMIYOR', $offerBlk !== '' && !preg_match('/vestra_(ensure_invoice|offer_issue_invoice|offers_combined_invoice_issue)\(/', $offerBlk));
$t('teklif dali orders.csv yazicisini DOGRUDAN cagirmiyor', $offerBlk !== '' && !str_contains($offerBlk, 'vestra_order_set_shipping('));

exec('rm -rf '.escapeshellarg($sand));
echo "\n{$ok} ok, {$bad} hata\n";
exit($bad ? 1 : 0);
