<?php
/* FATURA PARA BIRIMI — sipariş EUR, belge USD, kur SIPARIS TARIHININ kuru
 * (operatör, 7 Eyl 2026, VES-6B53D265 / 香港风徕贸易有限公司, €4.680,00:
 * *"usd ye cevir faturayi"*).
 *
 * Tutulan ilkeler:
 *   - Siparişin para birimi KAYITTIR, değişmez. Fatura hangi birimde kesilecek,
 *     operatörün ayrı kararı (`invoice_currency`).
 *   - Kur SIPARIS TARIHININ damgası (inc/fx_orders.php). Damga yoksa ÇEVİRİ YOK
 *     ve fatura KESİLMEZ — bugünün kuruyla doldurmak, Temmuz'da tahsil edilen
 *     tutarı Eylül kuruyla yazmak olurdu (KURAL 3'ün kur hâli).
 *   - Belge hangi kurla çevrildiğini SÖYLER; söylemezse alıcının muhasebecisi
 *     kendi kurunu uygular ve ödeme soru sorulurken bekler.
 *   - Birim fiyat çevrilip yuvarlanır, satır = birim × adet: belgenin kendi
 *     içinde toplaması tutmak zorunda.
 *   - Çevrilemeyen tek dilim varsa HİÇBİRİ kesilmez; yarısı USD yarısı EUR bir
 *     sipariş, operatörün istediği belge değil.
 */
error_reporting(E_ALL & ~E_DEPRECATED);
$root = __DIR__ . '/../vestra';
require_once $root . '/inc/pdf.php';
require_once $root . '/inc/money.php';
require_once $root . '/inc/products.php';
require_once $root . '/inc/invoice.php';
require_once $root . '/inc/fx_orders.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$src = fn(string $f) => (string)@file_get_contents($root . '/' . $f);

$meta  = ['ref'=>'CURT1','date'=>'2026-09-05T08:57:00+00:00','shipping'=>25.0,'discount'=>10.0,
          'buyer'=>['company'=>'香港风徕贸易有限公司','name'=>'LIN','country'=>'Hong Kong','address'=>'5 Canton Road','vat'=>'','reg'=>'']];
$items = [['sku'=>'AMI-PL-014','brand'=>'AMI Paris','name'=>'Core Logo Polo','colors'=>[],'qty'=>120,'unit'=>39.0,'line'=>4680.0]];
$fx    = ['usd'=>1.1622, 'date'=>'2026-09-04', 'source'=>'ecb'];

echo "== 1. Çevrim (saf fonksiyon) ==\n";
$c = vestra_invoice_convert_payload($meta, $items, 'EUR', 'USD', $fx);
$t('hata yok',                     !isset($c['error']));
$t('belge para birimi USD',        ($c['meta']['currency'] ?? '') === 'USD');
$t('birim 39.00 -> 45.33',         $c['items'][0]['unit'] === 45.33);
$t('satır = birim × adet',         $c['items'][0]['line'] === round(45.33 * 120, 2));
$t('navlun da çevrildi',           $c['meta']['shipping'] === round(25.0 * 1.1622, 2));
$t('indirim de çevrildi',          $c['meta']['discount'] === round(10.0 * 1.1622, 2));
/* Belge kendi içinde tutmalı: okuyan birim × adet yapıp satırı bulabilmeli. */
$t('belge kendi içinde tutuyor',   abs($c['items'][0]['unit'] * 120 - $c['items'][0]['line']) < 0.005);
$t('kur notu kuru yazıyor',        str_contains($c['meta']['fx_note'], '1 EUR = 1.1622 USD'));
$t('kur notu KAYNAĞI yazıyor',     str_contains($c['meta']['fx_note'], 'ECB'));
$t('kur notu TARİHİ yazıyor',      str_contains($c['meta']['fx_note'], '4 September 2026'));
$t('notu "sipariş tarihi" diyor',  str_contains($c['meta']['fx_note'], 'order date'));
/* ECB olmayan bir kura ECB denmez — money.php'nin kendi kuralı. */
$m = vestra_invoice_convert_payload($meta, $items, 'EUR', 'USD', ['usd'=>1.2,'date'=>'2026-09-04','source'=>'manual']);
$t('manuel kura ECB denmiyor',     !str_contains($m['meta']['fx_note'], 'ECB'));

echo "\n== 2. Reddetme: tahmin yok ==\n";
$t('kur damgası yoksa çeviri YOK', (vestra_invoice_convert_payload($meta, $items, 'EUR', 'USD', null)['error'] ?? '') !== '');
$t('sıfır kur da reddediliyor',    (vestra_invoice_convert_payload($meta, $items, 'EUR', 'USD', ['usd'=>0])['error'] ?? '') !== '');
$t('desteklenmeyen çift reddediliyor', (vestra_invoice_convert_payload($meta, $items, 'USD', 'GBP', $fx)['error'] ?? '') !== '');
$same = vestra_invoice_convert_payload($meta, $items, 'EUR', 'EUR', $fx);
$t('aynı birimde DOKUNULMUYOR',    !isset($same['error']) && $same['items'][0]['unit'] === 39.0 && !isset($same['meta']['fx_note']));
$t('boş hedefte dokunulmuyor',     vestra_invoice_convert_payload($meta, $items, 'EUR', '', $fx)['items'][0]['unit'] === 39.0);

echo "\n== 3. Seçim kaydı (izin listesi dar) ==\n";
$file = vestra_data_dir() . '/order_statuses.json';
$bak  = is_file($file) ? file_get_contents($file) : null;
try {
    $ref = 'CURTEST-' . strtoupper(bin2hex(random_bytes(3)));
    $t('varsayılan: seçim yok',      vestra_order_invoice_currency($ref) === '');
    $t('USD kaydediliyor',           vestra_order_set_invoice_currency($ref, 'usd') && vestra_order_invoice_currency($ref) === 'USD');
    $t('operatör damgası düşüyor',   (vestra_read_json('order_statuses.json')[$ref]['invoice_currency_by'] ?? '') === 'operator');
    $t('tanınmayan birim YAZILMIYOR', !vestra_order_set_invoice_currency($ref, 'GBP') && vestra_order_invoice_currency($ref) === 'USD');
    $t('boş seçim kaldırıyor',       vestra_order_set_invoice_currency($ref, '') && vestra_order_invoice_currency($ref) === '');
    $t('izin listesi EUR + USD',     vestra_invoice_currencies() === ['EUR', 'USD']);
} finally {
    if ($bak === null) @unlink($file); else file_put_contents($file, $bak);
}

echo "\n== 4. Belgeye basılıyor ==\n";
$seller = ['id'=>'garage','company'=>'GARAGE LE PARIS','country'=>'FR','address'=>'1 ALLEE DU CEDRE',
           'bank_holder'=>'Agaya','bank_iban'=>'FR1420041010050500013M02606','bank_bic'=>'PSSTFRPPSCE'];
$usd = vestra_render_invoice_pdf($c['meta'], $c['items'], $seller, 'INV-TEST-USD', false);
$t('tutarlar US$ ile basılıyor',   str_contains($usd, 'US$'));
$t('para birimi satırı USD',       str_contains($usd, 'USD - all amounts in US dollars') || str_contains($usd, 'all amounts in US dollars'));
$t('kur notu belgede',             str_contains($usd, 'converted from EUR'));
$eurPdf = vestra_render_invoice_pdf($meta, $items, $seller, 'INV-TEST-EUR', false);
$t('EUR belgede kur notu YOK',     !str_contains($eurPdf, 'converted from EUR'));

echo "\n== 5. Ödeme kutusu: USD yolu olmayan hesap TASLAKTA bildirilir ==\n";
/* GARAGE hesabında IBAN var, ABD hesabı yok: vestra_payment_rails USD için
   hesap no + ABA ister, yani belge ÖDEME KUTUSUZ çıkar. Alıcı bunu ancak fatura
   elindeyken görürdü. */
$t('IBAN hesabında USD yolu yok',  vestra_payment_rails($seller, 'USD') === []);
$draft = vestra_render_invoice_pdf($c['meta'], $c['items'], $seller, '', true);
$t('taslak ödeme kutusuzluğu yazıyor', str_contains($draft, 'no payment details for USD'));
$t('kesilmiş belgede iç not YOK',  !str_contains($usd, 'no payment details for'));
$usAcc = $seller + ['bank_account'=>'ACC1','bank_routing'=>'ABA1'];
$t('ABD yolu olan hesapta uyarı YOK',
   !str_contains(vestra_render_invoice_pdf($c['meta'], $c['items'], $usAcc, '', true), 'no payment details for'));
$t('EUR belgesinde uyarı YOK',     !str_contains(vestra_render_invoice_pdf($meta, $items, $seller, '', true), 'no payment details for'));
/* PLATFORM DILIMI: kutunun KESINLIKLE çıkmadığı tek hâl (bağlı hesap yok) uyarının
   DIŞINDA kalıyordu — koşul `$sellerAcc !== null` idi. En çok uyarı gereken hâl. */
$plat = vestra_render_invoice_pdf($meta, $items, null, '', true);
$t('platform taslağı kutusuzluğu yazıyor', str_contains($plat, 'VESTRA is issuing this invoice') && str_contains($plat, 'no payment box'));
/* Düzeltmenin YERİ yazılı olmalı: platformunki Admin ▸ Orders, satıcınınki
   Admin ▸ Users. Yanlış sayfaya yollayan uyarı iş görmez. */
$t('doğru sayfaya yolluyor',       str_contains($plat, 'Platform billing'));
$t('platform KESİLMİŞ belgesinde iç not YOK',
   !str_contains(vestra_render_invoice_pdf($meta, $items, null, 'INV-TEST-PLAT', false), 'VESTRA is issuing this invoice'));
/* ÖDENMİŞ (escrow) siparişte kutu zaten çizilmiyor: olmayan bir eksiği bildirmek
   uyarıyı gürültüye çevirirdi. */
$paidMeta = $meta; $paidMeta['paid'] = true;
$t('ödenmiş siparişte uyarı YOK',
   !str_contains(vestra_render_invoice_pdf($paidMeta, $items, null, '', true), 'no payment box'));

echo "\n== 5b. Platform kendi künyesinden kesiyor ==\n";
/* Panel platformun banka alanlarını TOPLUYOR ve boş bırakılınca "invoices will
   have no payment box" diye uyarıyordu; çizici ise o kaydı hiç okumuyor,
   `$sellerAcc ?? []` geçiyordu. Yani doldurulsa da hiçbir şey değişmiyordu. */
$pfile = vestra_data_dir() . '/platform_seller.json';
$pbak  = is_file($pfile) ? file_get_contents($pfile) : null;
try {
    $platEur = vestra_render_invoice_pdf($meta, $items, null, '', true);
    $t('platform künyesi belgede (adres)', str_contains($platEur, 'Dover'));
    $t('platform vergi kimliği belgede',   str_contains($platEur, '61-2070643'));
    $t('"katalog kalemi" satırı gitti',    !str_contains($platEur, 'Marketplace-catalog item'));
    /* Aynı belgede "Seller of record: Acerasoft LLC" + "Acerasoft ... is not the
       seller of record" yazıyordu: kendini yalanlayan iki beyan.
       ARANAN PARÇA TEK SATIRDA KALMALI: cümlenin tamamı sarılıp iki satıra
       bölünüyor ve PDF içinde bitişik geçmiyor — ilk yazımda "is not the seller
       of record" arandı, hiçbir belgede bulunamadı ve iddia HER İKİ yönde de
       "geçti". Hiç düşemeyen bir iddia, iddia değildir. */
    $t('platform faturası kendini yalanlamıyor', !str_contains($platEur, 'operates the marketplace'));
    $t('satıcı faturasında feragat DURUYOR',
       str_contains(vestra_render_invoice_pdf($meta, $items, $seller, '', true), 'operates the marketplace'));

    file_put_contents($pfile, json_encode([
        'bank_holder' => 'Acerasoft LLC', 'bank_name' => 'Test Bank',
        'bank_account' => '1234567890', 'bank_routing' => '021000021',
    ]));
    $platUsd = vestra_render_invoice_pdf($c['meta'], $c['items'], null, '', true);
    $t('banka alanları dolunca KUTU çıkıyor', str_contains($platUsd, 'Payment details'));
    $t('kutu çıkınca uyarı susuyor',          !str_contains($platUsd, 'no payment box'));
    /* Kutu TEK kaynaktan (`vestra_payment_rails`). Kutuya ayrıca 'Account
       holder' + 'Beneficiary bank' + 'Bank address' ekleniyordu; canlı USD
       taslağında lehdar ve banka İKİ KEZ, iki ayrı etiketle çıktı. Ödemeyi
       yapan tek bir lehdar bankası arar. */
    $t('lehdar TEK kez yazılı',  substr_count($platUsd, 'Acerasoft LLC)Tj') <= 1
                              || substr_count($platUsd, 'Beneficiary: Acerasoft LLC') === 1);
    $t('"Account holder" satırı yok', !str_contains($platUsd, 'Account holder:'));
    $t('banka adı TEK kez',      substr_count($platUsd, 'Beneficiary bank: Test Bank') === 1);
    $t('ödeme referansı kutuda', str_contains($platUsd, 'Payment reference: '));
    /* EUR yolu USD alanlarıyla açılmaz: IBAN yoksa EUR kutusu yine çıkmamalı. */
    $t('EUR yolu ayrı kalıyor',
       str_contains(vestra_render_invoice_pdf($meta, $items, null, '', true), 'no payment box'));
} finally {
    if ($pbak === null) @unlink($pfile); else file_put_contents($pfile, $pbak);
}

echo "\n== 6. Kesim yolu: çevrilemeyen belge KESİLMEZ ==\n";
$inv = $src('inc/invoice.php');
$t('yük çevrimi tek yerde (payloads)',   str_contains($inv, 'vestra_invoice_convert_payload($meta, $sellerItems, $orderCur, $wantCur, $fxStamp)'));
$t('kur sipariş damgasından',            str_contains($inv, '$fxStamp = vestra_order_fx($ref);'));
$t('hata varsa hiçbir numara yakılmıyor', str_contains($inv, "if (!empty(\$p['currency_error'])) return ['error'"));
/* Kesim 7 Eyl 2026'da TEK GÖVDEYE alındı (vestra_order_invoice_issue; panel ve
   iş akışı aynı fonksiyonu çağırıyor), kontrol de oraya taşındı. Davranış aynı:
   hata dizisi de "dolu" olduğu için düz bir if($issued) onu kesilmiş sanar ve
   alıcıya "faturanız hazır" yazardı. İddia kontrolün YENİ evine bakıyor. */
$t('gövde hata dizisini fatura sanmıyor', str_contains($inv, "if (isset(\$issued['error'])) return ['error'"));
$adm = $src('../vestra/admin.php');
$t('panel gövdeyi çağırıyor',             str_contains($adm, 'vestra_order_invoice_issue('));
$t('panel hatayı operatöre yazıyor',      str_contains($adm, "if(!empty(\$r['error']))"));
$t('admin para birimi seçicisi var',      str_contains($adm, "value=\"order_invoice_currency\"") && str_contains($adm, "if(\$act==='order_invoice_currency')"));

echo "\n== 6b. Tek tık düğmesi (sipariş ekranında) ==\n";
/* Operatör: "siparişi dolara çevirme buttonu yap". Karar "Approve & issue"in
   yanında veriliyor; bir ekranda görünmeyen seçenek olmayan seçenektir. */
$t('siparişte 💱 düğmesi var',        str_contains($adm, '💱 Invoice in '));
$t('geri dönüş düğmesi de var',       str_contains($adm, '↩ Back to '));
$t('aynı doğrulayıcıyı çağırıyor',    substr_count($adm, 'vestra_order_set_invoice_currency($ref') === 1);
$t('bastığı yere geri dönüyor',       str_contains($adm, "\$back=((\$_POST['from']??'')==='view')?'orders&view='.urlencode(\$ref):'invoices';"));
/* Kesilmiş faturada seçim belgeyi değiştirmez: düğme çizilmiyor VE sunucu
   ayrıca reddediyor — düğmeyi gizlemek yetki değil (KURAL 5g). */
$t('kesilmişse SUNUCU reddediyor',    str_contains($adm, "if(vestra_invoices_for_ref(\$ref)){")
                                   && str_contains($adm, 'msg=invoice_cur_late'));
$t('reddin gerekçesi ekrana yazılı',  str_contains($adm, "elseif(\$msg==='invoice_cur_late')"));
/* Damga yoksa kesim duracak; bunu düğmeye basan kişi ŞİMDİ görmeli. */
$t('kur damgası yoksa uyarı',         str_contains($adm, 'No rate stamp for this order — issuing will stop.'));
$sp = $src('../.github/workflows/seller-products.yml');
$t('iş akışında da yazma yolu var',   str_contains($sp, "admin_mode == 'currency'"));
$t('iş akışı yazmayı GERİ OKUYOR',    str_contains($sp, '$after  = vestra_order_invoice_currency($ref);')
                                   && str_contains($sp, 'if (!$ok || $after !== $expect)'));
$t('iş akışı numara YAKMIYOR',        !str_contains(explode("- name: Faturayı kes (issue)", $sp)[0], 'vestra_issue_order_invoices'));
$t('seçici kur damgasını gösteriyor',     str_contains($adm, 'kur damgası yok — kesim durur'));
$wf = (string)@file_get_contents(__DIR__.'/../.github/workflows/seller-products.yml');
$t('iş akışı da hatayı ayırt ediyor',     str_contains($wf, "isset(\$issued['error'])"));

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
