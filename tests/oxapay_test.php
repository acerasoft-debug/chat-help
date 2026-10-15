<?php
/* OxaPay kripto odeme + yaptirim engeli + tedarikci kuyrugu (14 Eki 2026, inc/oxapay.php).
 *
 * KUM HAVUZUNDA GERCEKTEN YAZIYOR; OxaPay ve tedarikci API'si SAHTE http ile, mektuplar
 * SAHTE mailer ile enjekte ediliyor -- hicbir sey disari cikmaz. Yonler:
 *   - ayar (.env) + yaptirim listesi (varsayilan, .env ile degistirilebilir)
 *   - ulke/bolge engeli: fatura ulkesi, teslimat ulkesi, adres metni (Kirim/Donetsk/...)
 *   - fatura olusturma: baslik, govde, adresler, kayit; odenmis/yaptirimli/API hatasi reddi
 *   - HMAC dogrulama (sha512, hash_equals)
 *   - webhook: paying->confirming, paid->paid (+kuyruk, +mektup), 5 kez tekrar = TEK is,
 *     expired/failed, yanlis tip, bilinmeyen track, ileri asamayi geri almaz
 *   - tedarikci kuyrugu: e-posta (SUPPLIER_ORDER_EMAIL / satici), API (Bearer), yeniden deneme
 *     60/300/900/3600, 5. denemede vazgecme, iletilmis isi atlama
 *   - SANDBOX: gercek mektup/API YOK; test adresi varsa [TEST], yoksa yalniz log
 *   - uc nokta dumani (php -S): webhook HMAC 400/200, pay ucu GET->303, yetkisiz POST->403 */

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  FAIL ").$n."\n"; };

$root = dirname(__DIR__);
$sand = sys_get_temp_dir().'/vestra_oxa_'.bin2hex(random_bytes(4));
mkdir($sand.'/data', 0777, true);
define('VESTRA_DATA_DIR', $sand.'/data');
define('VESTRA_ACCOUNTS', $sand.'/data/accounts.json');
putenv('OXAPAY_MERCHANT_API_KEY=TESTKEY-000000-000000-TEST01');
putenv('OXAPAY_SANDBOX=false');
putenv('VESTRA_BASE_URL=https://vestrasales.com');
putenv('SUPPLIER_FORWARD_MODE=email'); putenv('SUPPLIER_ORDER_EMAIL='); putenv('SUPPLIER_API_URL='); putenv('SUPPLIER_API_KEY=');
putenv('SANCTIONED_COUNTRIES='); putenv('SANCTIONED_REGIONS='); putenv('OXAPAY_SANDBOX_TEST_EMAIL=');
require_once $root.'/vestra/inc/products.php';
require_once $root.'/vestra/inc/auth.php';
require_once $root.'/vestra/inc/orders.php';
require_once $root.'/vestra/inc/invoice.php';
require_once $root.'/vestra/inc/addresses.php';
require_once $root.'/vestra/inc/oxapay.php';
if (vestra_data_dir() !== $sand.'/data') { fwrite(STDERR, "kum havuzu kurulamadi\n"); exit(1); }

/* ── Tohum ─────────────────────────────────────────────────────────────────── */
file_put_contents($sand.'/data/accounts.json', json_encode([
    ['id' => 'S1', 'type' => 'seller', 'email' => 'seller1@example.test', 'company' => 'Seller One', 'name' => 'Sel'],
    ['id' => 'S2', 'type' => 'seller', 'email' => 'seller2@example.test', 'company' => 'Seller Two'],
    ['id' => 'B1', 'type' => 'buyer',  'email' => 'buyer@example.test', 'company' => 'Buyer BV', 'country' => 'Netherlands', 'address' => 'Keizersgracht 1', 'postcode' => '1015', 'city' => 'Amsterdam'],
]));
file_put_contents($sand.'/data/listings.json', json_encode([
    ['id' => 'L1', 'sku' => 'SKU-A', 'brand' => 'Lacoste', 'name' => 'Polo', 'seller_uid' => 'S1', 'supplier_sku' => 'LAC-POLO-01', 'status' => 'approved', 'price' => 50, 'moq' => 1],
    ['id' => 'L2', 'sku' => 'SKU-B', 'brand' => 'Balmain', 'name' => 'Jacket', 'seller_uid' => 'S2', 'status' => 'approved', 'price' => 400, 'moq' => 1],
]));
$head = ['timestamp','ref','company','vat','name','email','country','phone','items','subtotal','commission','payout','total','notes','consent','terms_version','voucher_code','discount','shipping','shipping_label'];
$h = fopen($sand.'/data/orders.csv', 'w'); fputcsv($h, $head, ',', '"', '\\');
$row = function (string $ref, string $country, string $notes, string $items = '10x SKU-A @50 | 2x SKU-B @400', string $total = '1300.00') use ($h) {
    fputcsv($h, ['2026-10-14T10:00:00+00:00', $ref, 'Buyer BV', '', 'Bea', 'buyer@example.test', $country, '+31 6 1', $items, '1300.00', '0', '1300.00', $total, $notes, 'yes', 'v1', '', '', '', ''], ',', '"', '\\');
};
$row('VES-OK1', 'Netherlands', 'Payment: Crypto (OxaPay). Colours — SKU-A: Navy. Sizes — SKU-A: M, L.');
$row('VES-OK2', 'Turkey', 'Payment: Crypto (OxaPay). Deliver to: Levent, 34330 Istanbul, Türkiye.');
$row('VES-RU',  'Russia', 'Payment: Crypto (OxaPay).');
$row('VES-CRIM', 'Ukraine', 'Payment: Crypto (OxaPay). Deliver to: Lenina 5, 295000 Simferopol, Crimea.');
$row('VES-PAID', 'Brazil', 'Payment: Bank transfer.');
$row('VES-SHIP', 'Brazil', 'Payment: Crypto (OxaPay).');
$row('VES-SB',  'Chile', 'Payment: Crypto (OxaPay).');
$row('VES-API', 'Mexico', 'Payment: Crypto (OxaPay).', '3x SKU-A @50', '150.00');
$row('VES-ZERO', 'Mexico', 'Payment: Crypto (OxaPay).', '1x SKU-A @0', '0');
fclose($h);
file_put_contents($sand.'/data/order_statuses.json', json_encode([
    'VES-PAID' => ['status' => 'paid', 'paid_at' => '2026-10-01T00:00:00+00:00'],
    'VES-SHIP' => ['status' => 'shipped'],
]));
$statuses = fn() => vestra_read_json('order_statuses.json');
$queue    = fn() => vestra_read_json(VESTRA_SUPPLIER_QUEUE);
$rowOf    = fn(string $ref) => oxapay_order_row($ref);

/* ── 1. Ayar ───────────────────────────────────────────────────────────────── */
echo "1. ayar\n";
$cfg = oxapay_cfg();
$t('anahtar .env\'den okunuyor',                 $cfg['key'] === 'TESTKEY-000000-000000-TEST01');
$t('varsayilanlar: lifetime 60, fee 1, coverage 1, USDT', $cfg['lifetime'] === 60 && $cfg['fee_paid_by_payer'] === 1 && $cfg['under_paid_coverage'] == 1.0 && $cfg['to_currency'] === 'USDT');
$t('sandbox=false okunuyor',                      $cfg['sandbox'] === false);
$t('oxapay_enabled anahtar doluyken TRUE',        oxapay_enabled());
putenv('OXAPAY_MERCHANT_API_KEY=');
$t('anahtar BOSKEN kapali (musteri hicbir sey gormez)', !oxapay_enabled());
putenv('OXAPAY_MERCHANT_API_KEY=TESTKEY-000000-000000-TEST01');
putenv('OXAPAY_LIFETIME=abc'); $t('bozuk lifetime -> 60', oxapay_cfg()['lifetime'] === 60); putenv('OXAPAY_LIFETIME=');
$t('yaptirim listesi varsayilan RU BY IR KP SY CU', vestra_sanctioned_countries() === ['RU','BY','IR','KP','SY','CU']);
putenv('SANCTIONED_COUNTRIES=ru, by;ve xx1');
$t('.env listesi: kucuk harf/ayirici toleransli, bozuk kod atilir', vestra_sanctioned_countries() === ['RU','BY','VE']);
putenv('SANCTIONED_COUNTRIES=');

/* ── 2. Yaptirim engeli ────────────────────────────────────────────────────── */
echo "2. yaptirim\n";
$t('cc: Russia -> RU',                     vestra_sanction_cc('Russia') === 'RU');
$t('cc: Russian Federation -> RU',         vestra_sanction_cc('Russian Federation') === 'RU');
$t('cc: Россия -> RU',                      vestra_sanction_cc('Россия') === 'RU');
$t('cc: ciplak kod ru -> RU',              vestra_sanction_cc('ru') === 'RU');
$t('cc: "Belarus (BY)" -> BY',             vestra_sanction_cc('Belarus (BY)') === 'BY');
$t('cc: Kuzey Kore -> KP',                 vestra_sanction_cc('Kuzey Kore') === 'KP');
$t('cc: Syrian Arab Republic -> SY',       vestra_sanction_cc('Syrian Arab Republic') === 'SY');
$t('cc: Küba -> CU',                       vestra_sanction_cc('Küba') === 'CU');
$t('cc: Iran -> IR',                       vestra_sanction_cc('Iran') === 'IR');
$t('cc: Türkiye -> TR (engel degil)',      vestra_sanction_cc('Türkiye') === 'TR' || vestra_sanction_cc('Türkiye') === '');
$t('cc: bilinmeyen -> ""',                 vestra_sanction_cc('Atlantis') === '');
$t('fatura ulkesi Russia -> engel',        vestra_crypto_block_reason('Russia') === 'country:RU');
$t('teslimat ulkesi Belarus -> engel',     vestra_crypto_block_reason('Germany', 'Belarus') === 'country:BY');
$t('adres metninde Iran -> engel',         vestra_crypto_block_reason('Germany', '', 'Valiasr St 12, Tehran, Iran') === 'country:IR');
$t('adres metninde Cuba -> engel',         vestra_crypto_block_reason('Spain', '', '', 'Calle 23, La Habana, Cuba') === 'country:CU');
$t('Kirim (Simferopol, Crimea) -> bolge engeli',   str_starts_with(vestra_crypto_block_reason('Ukraine', '', 'Lenina 5, Simferopol, Crimea'), 'region:'));
$t('Sevastopol (Kiril) -> bolge engeli',   str_starts_with(vestra_crypto_block_reason('Ukraine', '', 'ул. Ленина 1, Севастополь'), 'region:'));
$t('Donetsk -> bolge engeli',              vestra_crypto_block_reason('Ukraine', '', 'Artema 1, Donetsk') === 'region:donetsk');
$t('Luhansk/Lugansk -> bolge engeli',      vestra_crypto_block_reason('Ukraine', '', 'Sovetskaya 1, Lugansk') === 'region:lugansk');
$t('Zaporizhzhia -> bolge engeli',         vestra_crypto_block_reason('Ukraine', '', 'Sobornyi 1, Zaporizhzhia') === 'region:zaporizhzhia');
$t('Zaporijya (TR yazim) -> bolge engeli', vestra_crypto_block_reason('Ukraine', '', 'Zaporijya') === 'region:zaporijya');
$t('Kherson -> bolge engeli',              vestra_crypto_block_reason('Ukraine', '', 'Ushakova 1, Kherson') === 'region:kherson');
$t('Mariupol (Donetsk ili) -> bolge engeli', vestra_crypto_block_reason('Ukraine', '', 'Mariupol') === 'region:mariupol');
$t('Kyiv, Ukraine -> SERBEST',             vestra_crypto_block_reason('Ukraine', '', 'Khreshchatyk 1, Kyiv, Ukraine') === '');
$t('Lviv, Ukraine -> SERBEST',             vestra_crypto_block_reason('Ukraine', 'Ukraine', 'Lviv') === '');
$t('Turkey -> SERBEST',                    vestra_crypto_block_reason('Turkey', '', 'Levent, Istanbul, Türkiye') === '');
$t('Brazil / UAE / Chile -> SERBEST',      vestra_crypto_block_reason('Brazil') === '' && vestra_crypto_block_reason('United Arab Emirates') === '' && vestra_crypto_block_reason('Chile') === '');
$t('"Prussia" / "Belarusian" gibi parca -> SERBEST (kelime siniri)', vestra_crypto_block_reason('Germany', '', 'Prussian Str 1, Berlin') === '' && vestra_crypto_block_reason('Poland', '', 'ul. Białoruska 2, Warszawa') === '');
$t('"Syracuse, NY" -> SERBEST',            vestra_crypto_block_reason('United States', '', '1 Main St, Syracuse, NY') === '');
putenv('SANCTIONED_COUNTRIES=RU');
$t('.env yalniz RU: Cuba artik SERBEST',   vestra_crypto_block_reason('Cuba') === '');
$t('.env yalniz RU: Russia hala engel',    vestra_crypto_block_reason('Russia') === 'country:RU');
$t('.env yalniz RU: Kirim bolgesi YINE engel (bolge listesi ulkeden bagimsiz)', str_starts_with(vestra_crypto_block_reason('Ukraine', '', 'Yalta, Crimea'), 'region:'));
putenv('SANCTIONED_COUNTRIES=RU,VE');
$t('.env ek kod VE: ciplak kod "VE" engel', vestra_crypto_block_reason('VE') === 'country:VE');
putenv('SANCTIONED_COUNTRIES=');
putenv('SANCTIONED_REGIONS=Transnistria, Tiraspol');
$t('.env ek bolge: Tiraspol engel',        vestra_crypto_block_reason('Moldova', '', 'Tiraspol') === 'region:tiraspol');
putenv('SANCTIONED_REGIONS=');
$t('kayitli siparis: ulke Russia -> engel', vestra_order_crypto_block($rowOf('VES-RU')) === 'country:RU');
$t('kayitli siparis: Deliver to Kirim -> engel', str_starts_with(vestra_order_crypto_block($rowOf('VES-CRIM')), 'region:'));
$t('kayitli siparis: Turkiye -> serbest',   vestra_order_crypto_block($rowOf('VES-OK2')) === '');
$t('kayitli siparis: NL (hesap adresi Amsterdam) -> serbest', vestra_order_crypto_block($rowOf('VES-OK1')) === '');
$js = vestra_sanction_js_pattern();
$t('JS kalibi: crimea, donetsk, russia, (RU) iceriyor', str_contains($js, 'crimea') && str_contains($js, 'donetsk') && str_contains($js, 'russia') && str_contains($js, '\(RU\)'));
$t('JS kalibi gecerli bir PCRE',            @preg_match('/'.$js.'/iu', 'x') !== false);
$t('JS kalibi: "Simferopol, Crimea" yakalar, "Kyiv" yakalamaz', preg_match('/'.$js.'/iu', 'Lenina 5, Simferopol, Crimea') === 1 && preg_match('/'.$js.'/iu', 'Kyiv, Ukraine') === 0);

/* ── 3. Fatura olusturma ───────────────────────────────────────────────────── */
echo "3. fatura\n";
$calls = [];
$httpOk = function (string $url, array $body, array $headers) use (&$calls) {
    $calls[] = ['url' => $url, 'body' => $body, 'headers' => $headers];
    return [200, ['data' => ['track_id' => 'TRK-'.$body['order_id'], 'payment_url' => 'https://pay.oxapay.com/'.$body['order_id']], 'message' => 'ok', 'status' => 200]];
};
$r = oxapay_create_invoice($rowOf('VES-OK1'), ['http' => $httpOk]);
$t('fatura OK, track_id + payment_url',      $r['ok'] && $r['track_id'] === 'TRK-VES-OK1' && $r['payment_url'] === 'https://pay.oxapay.com/VES-OK1');
$c = $calls[0] ?? [];
$t('endpoint https://api.oxapay.com/v1/payment/invoice', ($c['url'] ?? '') === 'https://api.oxapay.com/v1/payment/invoice');
$t('baslik merchant_api_key + Content-Type json', in_array('merchant_api_key: TESTKEY-000000-000000-TEST01', $c['headers'] ?? [], true) && in_array('Content-Type: application/json', $c['headers'] ?? [], true));
$b = $c['body'] ?? [];
$t('govde: amount 1300 EUR, lifetime 60, fee 1, coverage 1, USDT', ($b['amount'] ?? 0) == 1300.0 && ($b['currency'] ?? '') === 'EUR' && ($b['lifetime'] ?? 0) === 60 && ($b['fee_paid_by_payer'] ?? -1) === 1 && ($b['under_paid_coverage'] ?? -1) == 1.0 && ($b['to_currency'] ?? '') === 'USDT');
$t('govde: callback_url /oxapay/webhook, return_url /orders/{ref}/payment-return', ($b['callback_url'] ?? '') === 'https://vestrasales.com/oxapay/webhook' && ($b['return_url'] ?? '') === 'https://vestrasales.com/orders/VES-OK1/payment-return');
$t('govde: email, order_id, description, thanks_message, sandbox=false', ($b['email'] ?? '') === 'buyer@example.test' && ($b['order_id'] ?? '') === 'VES-OK1' && str_contains((string)($b['description'] ?? ''), 'VES-OK1') && ($b['thanks_message'] ?? '') !== '' && ($b['sandbox'] ?? null) === false);
$e = $statuses()['VES-OK1'] ?? [];
$t('kayit: payment_method=oxapay, payment_status=pending, track_id', ($e['payment_method'] ?? '') === 'oxapay' && ($e['payment_status'] ?? '') === 'pending' && ($e['oxapay_track_id'] ?? '') === 'TRK-VES-OK1');
$t('kayit: siparis durumu pending KALDI (odeme gelmeden paid yazilmaz)', ($e['status'] ?? '') === 'pending');
$t('indeks: track -> ref',                  oxapay_ref_for_track('TRK-VES-OK1') === 'VES-OK1');
$t('odenmis siparis REDDEDILIR (already_paid), API cagrilmaz', !oxapay_create_invoice($rowOf('VES-PAID'), ['http' => $httpOk])['ok'] && count($calls) === 1);
$rr = oxapay_create_invoice($rowOf('VES-RU'), ['http' => $httpOk]);
$t('yaptirimli siparis REDDEDILIR (sanctioned), API cagrilmaz', !$rr['ok'] && $rr['error'] === 'sanctioned' && count($calls) === 1);
$t('sifir tutar reddedilir',                oxapay_create_invoice($rowOf('VES-ZERO'), ['http' => $httpOk])['error'] === 'amount');
$httpBad = fn() => [400, ['error' => ['message' => 'Invalid amount']]];
$rb = oxapay_create_invoice($rowOf('VES-OK2'), ['http' => $httpBad]);
$t('API hatasi: ok=false, error api:400:..., kayit YAZILMAZ', !$rb['ok'] && str_starts_with($rb['error'], 'api:400') && !isset($statuses()['VES-OK2']));
$httpNoUrl = fn() => [200, ['data' => ['track_id' => 'X']]];
$t('payment_url eksikse ok=false',          !oxapay_create_invoice($rowOf('VES-OK2'), ['http' => $httpNoUrl])['ok']);
putenv('OXAPAY_MERCHANT_API_KEY=');
$t('anahtar bosken not_configured',         oxapay_create_invoice($rowOf('VES-OK2'), ['http' => $httpOk])['error'] === 'not_configured');
putenv('OXAPAY_MERCHANT_API_KEY=TESTKEY-000000-000000-TEST01');
foreach (['VES-OK2', 'VES-SB', 'VES-API'] as $ref) oxapay_create_invoice($rowOf($ref), ['http' => $httpOk]);
$t('uc fatura daha acildi (OK2, SB, API)', count($calls) === 4 && oxapay_ref_for_track('TRK-VES-API') === 'VES-API');
$t('VES-SHIP (shipped = parasi gelmis sayilir): fatura ACILMAZ', oxapay_create_invoice($rowOf('VES-SHIP'), ['http' => $httpOk])['error'] === 'already_paid' && count($calls) === 4);
/* Fatura beklemedeyken operator siparisi ileri tasidi senaryosu: track elle damgalanir. */
$all = $statuses(); $all['VES-SHIP']['payment_method'] = 'oxapay'; $all['VES-SHIP']['payment_status'] = 'pending'; $all['VES-SHIP']['oxapay_track_id'] = 'TRK-VES-SHIP'; vestra_write_json('order_statuses.json', $all);

/* ── 4. HMAC ───────────────────────────────────────────────────────────────── */
echo "4. hmac\n";
$key = 'TESTKEY-000000-000000-TEST01';
$raw = json_encode(['type' => 'invoice', 'track_id' => 'TRK-VES-OK1', 'status' => 'Paid']);
$sig = hash_hmac('sha512', $raw, $key);
$t('dogru imza gecer',                      oxapay_verify_webhook($raw, $sig));
$t('BUYUK harf hex de gecer',               oxapay_verify_webhook($raw, strtoupper($sig)));
$t('bir bayt degisen govde REDDEDILIR',     !oxapay_verify_webhook($raw.' ', $sig));
$t('yanlis anahtar REDDEDILIR',             !oxapay_verify_webhook($raw, hash_hmac('sha512', $raw, 'other')));
$t('bos imza / bos govde REDDEDILIR',       !oxapay_verify_webhook($raw, '') && !oxapay_verify_webhook('', $sig));
putenv('OXAPAY_MERCHANT_API_KEY=');
$t('anahtar yokken HICBIR imza gecmez',     !oxapay_verify_webhook($raw, $sig));
putenv('OXAPAY_MERCHANT_API_KEY=TESTKEY-000000-000000-TEST01');

/* ── 5. Webhook ────────────────────────────────────────────────────────────── */
echo "5. webhook\n";
$mails = [];
$mailer = function (string $to, string $subj, string $body) use (&$mails): bool { $mails[] = ['to' => $to, 'subj' => $subj, 'body' => $body]; return true; };
$opt = ['mail' => $mailer, 'now' => 1760500000, 'quiet' => true];
$t('tip white_label/invoice disi -> ignored_type', oxapay_handle_webhook(['type' => 'payout', 'track_id' => 'TRK-VES-OK1', 'status' => 'Paid'], $opt)['action'] === 'ignored_type');
$t('bilinmeyen track -> unknown_track (200)', oxapay_handle_webhook(['type' => 'invoice', 'track_id' => 'NOPE', 'status' => 'Paid'], $opt)['action'] === 'unknown_track');
$t('Paying -> confirming',                  oxapay_handle_webhook(['type' => 'invoice', 'track_id' => 'TRK-VES-OK1', 'status' => 'Paying'], $opt)['action'] === 'confirming' && ($statuses()['VES-OK1']['payment_status'] ?? '') === 'confirming');
$t('Confirming -> confirming, siparis hala pending', oxapay_handle_webhook(['type' => 'invoice', 'track_id' => 'TRK-VES-OK1', 'status' => 'Confirming'], $opt)['action'] === 'confirming' && ($statuses()['VES-OK1']['status'] ?? '') === 'pending');
$t('tedarikci kuyrugu henuz BOS',           $queue() === []);
$paid = ['type' => 'invoice', 'track_id' => 'TRK-VES-OK1', 'status' => 'Paid', 'amount' => 1300, 'currency' => 'EUR', 'pay_currency' => 'USDT', 'pay_amount' => 1412.5, 'txs' => [['tx_hash' => 'abc']], 'secret_field' => 'x'];
$r1 = oxapay_handle_webhook($paid, $opt);
$e = $statuses()['VES-OK1'];
$t('Paid -> action paid',                   $r1['action'] === 'paid' && $r1['ref'] === 'VES-OK1');
$t('kayit: payment_status=paid, paid_at, siparis durumu paid', ($e['payment_status'] ?? '') === 'paid' && ($e['paid_at'] ?? '') === date('c', 1760500000) && ($e['status'] ?? '') === 'paid');
$t('kayit: gecmis satiri by oxapay',        (($e['history'] ?? [])[0]['by'] ?? '') === 'oxapay' && (($e['history'] ?? [])[0]['status'] ?? '') === 'paid');
$t('kayit: payload saklandi (pay_currency), bilinmeyen alan ATILDI', ($e['oxapay_payload']['pay_currency'] ?? '') === 'USDT' && !isset($e['oxapay_payload']['secret_field']));
$t('vestra_order_payment_settled: odendi (via status)', vestra_order_payment_settled('VES-OK1')['settled'] && vestra_order_payment_settled('VES-OK1')['via'] === 'status');
$q = $queue();
$t('tedarikci kuyrugunda TEK is (VES-OK1), attempts 0, hemen', count($q) === 1 && isset($q['VES-OK1']) && $q['VES-OK1']['attempts'] === 0 && $q['VES-OK1']['next_at'] === 1760500000);
$t('mektuplar: alici "payment received" + satici S1 ve S2 "please ship"', count($mails) === 3 && $mails[0]['to'] === 'buyer@example.test' && str_contains($mails[0]['subj'], 'payment received') && $mails[1]['to'] === 'seller1@example.test' && $mails[2]['to'] === 'seller2@example.test');
$n = count($mails);
for ($i = 0; $i < 5; $i++) $rx = oxapay_handle_webhook($paid, $opt + ['now' => 1760500100 + $i]);
$e2 = $statuses()['VES-OK1'];
$t('ayni Paid 5 kez daha -> already_paid, kayit degismedi, kuyrukta hala TEK is, mektup YOK', $rx['action'] === 'already_paid' && $e2['paid_at'] === $e['paid_at'] && count($queue()) === 1 && count($mails) === $n && count($e2['history']) === 1);
$t('odenmis siparise Expired gelirse DEGISMEZ', oxapay_handle_webhook(['type' => 'invoice', 'track_id' => 'TRK-VES-OK1', 'status' => 'Expired'], $opt)['action'] === 'noted' && ($statuses()['VES-OK1']['payment_status'] ?? '') === 'paid');
$t('Expired (odenmemis OK2) -> payment_status expired, siparis pending', oxapay_handle_webhook(['type' => 'invoice', 'track_id' => 'TRK-VES-OK2', 'status' => 'Expired'], $opt)['action'] === 'expired' && ($statuses()['VES-OK2']['payment_status'] ?? '') === 'expired' && ($statuses()['VES-OK2']['status'] ?? '') === 'pending');
$t('Failed -> failed',                      oxapay_handle_webhook(['type' => 'white_label', 'track_id' => 'TRK-VES-OK2', 'status' => 'Failed'], $opt)['action'] === 'failed');
$t('expired siparise sonra Paid gelirse yine paid (gec odeme kabul)', oxapay_handle_webhook(['type' => 'invoice', 'track_id' => 'TRK-VES-OK2', 'status' => 'Paid'], $opt)['action'] === 'paid' && count($queue()) === 2);
$rs = oxapay_handle_webhook(['type' => 'invoice', 'track_id' => 'TRK-VES-SHIP', 'status' => 'Paid'], $opt);
$t('ileri asamadaki (shipped) siparis Paid gelince GERI ALINMAZ, payment_status paid', $rs['action'] === 'paid' && ($statuses()['VES-SHIP']['status'] ?? '') === 'shipped' && ($statuses()['VES-SHIP']['payment_status'] ?? '') === 'paid');
$t('track_id baska siparise ait degilse islenmez (index tutarliligi)', oxapay_handle_webhook(['type' => 'invoice', 'track_id' => 'TRK-VES-PAID', 'status' => 'Paid'], $opt)['action'] === 'unknown_track');

/* ── 6. Tedarikci kuyrugu — e-posta kipi ───────────────────────────────────── */
echo "6. tedarikci e-posta\n";
file_put_contents($sand.'/data/'.VESTRA_SUPPLIER_QUEUE, json_encode(['VES-OK1' => $queue()['VES-OK1']]));   // yalniz OK1
$mails = []; $hcalls = [];
$httpSup = function (string $url, array $body, array $headers) use (&$hcalls) { $hcalls[] = ['url' => $url, 'body' => $body, 'headers' => $headers]; return [200, ['ok' => true]]; };
$pl = vestra_supplier_payload($rowOf('VES-OK1'));
$t('yuk: reference VESTRA-VES-OK1',         $pl['reference'] === 'VESTRA-VES-OK1');
$t('yuk: teslimat adresi hesabin fatura adresi (Amsterdam, Netherlands)', str_contains($pl['delivery_address'], 'Amsterdam') && str_contains($pl['delivery_address'], 'Netherlands'));
$t('yuk: kalemler supplier_sku (LAC-POLO-01) / sku (SKU-B), ad, beden, adet', count($pl['items']) === 2 && $pl['items'][0]['supplier_sku'] === 'LAC-POLO-01' && $pl['items'][0]['sku'] === 'SKU-A' && $pl['items'][0]['qty'] === 10 && $pl['items'][0]['size'] === 'M, L' && $pl['items'][1]['supplier_sku'] === 'SKU-B' && str_contains($pl['items'][1]['name'], 'Balmain'));
$pl2 = vestra_supplier_payload($rowOf('VES-OK2'));
$t('yuk: "Deliver to" notu varsa o adres (Istanbul)', str_contains($pl2['delivery_address'], 'Istanbul'));
$s = vestra_supplier_forward_run(['now' => 1760500000, 'mail' => $mailer, 'http' => $httpSup, 'quiet' => true]);
$t('e-posta kipi, SUPPLIER_ORDER_EMAIL bos: her saticiya KENDI satirlari', $s['sent'] === 1 && count($mails) === 2 && $mails[0]['to'] === 'seller1@example.test' && $mails[1]['to'] === 'seller2@example.test');
$t('satici 1 mektubunda SKU-A var, SKU-B YOK',  str_contains($mails[0]['body'], 'LAC-POLO-01') && !str_contains($mails[0]['body'], 'SKU-B'));
$t('satici 2 mektubunda SKU-B var, SKU-A YOK',  str_contains($mails[1]['body'], 'SKU-B') && !str_contains($mails[1]['body'], 'SKU-A'));
$t('mektup: referans + teslimat adresi + konu',  str_contains($mails[0]['body'], 'VESTRA-VES-OK1') && str_contains($mails[0]['body'], 'Amsterdam') && str_contains($mails[0]['subj'], 'VESTRA-VES-OK1'));
$t('API hic cagrilmadi',                     $hcalls === []);
$e = $statuses()['VES-OK1'];
$t('kayit: supplier_forwarded_at, fulfillment_status=sent_to_supplier, via email', ($e['supplier_forwarded_at'] ?? '') === date('c', 1760500000) && ($e['fulfillment_status'] ?? '') === 'sent_to_supplier' && ($e['supplier_forward_via'] ?? '') === 'email');
$t('is kapandi (done_at)',                   ($queue()['VES-OK1']['done_at'] ?? '') !== '');
$n = count($mails);
$s = vestra_supplier_forward_run(['now' => 1760500600, 'mail' => $mailer, 'http' => $httpSup, 'quiet' => true]);
$t('ikinci kosu: hicbir sey gonderilmez',    $s['sent'] === 0 && count($mails) === $n);
/* supplier_forwarded_at doluysa cik */
vestra_supplier_enqueue('VES-OK1', 1760500700);
$t('enqueue idempotent: kapanmis is yeniden ACILMAZ', ($queue()['VES-OK1']['done_at'] ?? '') !== '');
$all = $statuses(); unset($all['VES-OK2']['supplier_forwarded_at']); $all['VES-OK2']['supplier_forwarded_at'] = '2026-10-14T00:00:00+00:00'; vestra_write_json('order_statuses.json', $all);
vestra_supplier_enqueue('VES-OK2', 1760500700);
$s = vestra_supplier_forward_run(['now' => 1760500800, 'mail' => $mailer, 'http' => $httpSup, 'quiet' => true]);
$t('supplier_forwarded_at DOLU olan is: atlanir, mektup yok', $s['skipped'] === 1 && count($mails) === $n && ($queue()['VES-OK2']['done_at'] ?? '') !== '');
putenv('SUPPLIER_ORDER_EMAIL=orders@supplier.test');
$all = $statuses(); unset($all['VES-OK2']['supplier_forwarded_at']); vestra_write_json('order_statuses.json', $all);
$q = $queue(); $q['VES-OK2'] = ['ref' => 'VES-OK2', 'created_at' => 'x', 'attempts' => 0, 'next_at' => 0, 'done_at' => '', 'failed_at' => '', 'last_error' => '']; vestra_write_json(VESTRA_SUPPLIER_QUEUE, $q);
$s = vestra_supplier_forward_run(['now' => 1760500900, 'mail' => $mailer, 'http' => $httpSup, 'quiet' => true]);
$t('SUPPLIER_ORDER_EMAIL doluysa TEK mektup oraya, tum kalemler', $s['sent'] === 1 && count($mails) === $n + 1 && end($mails)['to'] === 'orders@supplier.test' && str_contains(end($mails)['body'], 'SKU-A') && str_contains(end($mails)['body'], 'SKU-B'));
putenv('SUPPLIER_ORDER_EMAIL=');

/* ── 7. Tedarikci kuyrugu — API kipi + yeniden deneme ──────────────────────── */
echo "7. tedarikci API\n";
putenv('SUPPLIER_FORWARD_MODE=api'); putenv('SUPPLIER_API_URL=https://supplier.test/orders'); putenv('SUPPLIER_API_KEY=sup-secret');
vestra_write_json(VESTRA_SUPPLIER_QUEUE, []);
oxapay_handle_webhook(['type' => 'invoice', 'track_id' => 'TRK-VES-API', 'status' => 'Paid'], $opt + ['now' => 1760600000]);
$mails = []; $hcalls = [];   // odeme mektuplari gitti; buradan sonra yalniz tedarikci yolu olculuyor
$fail = 0;
$httpFlaky = function (string $url, array $body, array $headers) use (&$hcalls, &$fail) { $hcalls[] = ['url' => $url, 'body' => $body, 'headers' => $headers]; return $fail-- > 0 ? [503, null] : [201, ['id' => 9]]; };
$fail = 2;
$s = vestra_supplier_forward_run(['now' => 1760600000, 'mail' => $mailer, 'http' => $httpFlaky, 'quiet' => true]);
$q = $queue()['VES-API'];
$t('API 503 -> yeniden denenecek: attempts 1, next_at +60',  $s['retried'] === 1 && $q['attempts'] === 1 && $q['next_at'] === 1760600060 && str_contains($q['last_error'], '503'));
$t('istek: URL, Bearer, JSON yuk (reference, items)', $hcalls[0]['url'] === 'https://supplier.test/orders' && in_array('Authorization: Bearer sup-secret', $hcalls[0]['headers'], true) && $hcalls[0]['body']['reference'] === 'VESTRA-VES-API' && $hcalls[0]['body']['items'][0]['supplier_sku'] === 'LAC-POLO-01');
$s = vestra_supplier_forward_run(['now' => 1760600030, 'mail' => $mailer, 'http' => $httpFlaky, 'quiet' => true]);
$t('zamani gelmeden (30 sn) DOKUNULMAZ',    $s['retried'] === 0 && $s['sent'] === 0 && count($hcalls) === 1);
$s = vestra_supplier_forward_run(['now' => 1760600060, 'mail' => $mailer, 'http' => $httpFlaky, 'quiet' => true]);
$q = $queue()['VES-API'];
$t('2. deneme basarisiz -> next_at +300',    $q['attempts'] === 2 && $q['next_at'] === 1760600060 + 300);
$s = vestra_supplier_forward_run(['now' => 1760600360, 'mail' => $mailer, 'http' => $httpFlaky, 'quiet' => true]);
$e = $statuses()['VES-API'];
$t('3. deneme BASARILI (201): iletildi, via api, fulfillment sent_to_supplier', $s['sent'] === 1 && ($e['supplier_forward_via'] ?? '') === 'api' && ($e['fulfillment_status'] ?? '') === 'sent_to_supplier' && ($queue()['VES-API']['done_at'] ?? '') !== '');
$t('hicbir mektup gitmedi (API kipi)',       $mails === []);
/* 5 deneme sonra vazgecme */
$q = $queue(); $q['VES-DEAD'] = ['ref' => 'VES-DEAD', 'created_at' => 'x', 'attempts' => 0, 'next_at' => 0, 'done_at' => '', 'failed_at' => '', 'last_error' => '']; vestra_write_json(VESTRA_SUPPLIER_QUEUE, $q);
$httpDown = fn() => [500, null];
$waits = [];
$now = 1760700000;
for ($i = 1; $i <= 5; $i++) {
    $before = $queue()['VES-DEAD']['next_at'];
    $now = max($now, (int)$before);
    vestra_supplier_forward_run(['now' => $now, 'mail' => $mailer, 'http' => $httpDown, 'quiet' => true]);
    $qq = $queue()['VES-DEAD'];
    if ($i < 5) $waits[] = $qq['next_at'] - $now;
}
$t('bekleme dizisi 60/300/900/3600',         $waits === [60, 300, 900, 3600]);
$t('5. denemede VAZGECILDI (failed_at), bir daha denenmez', ($qq['failed_at'] ?? '') !== '' && $qq['attempts'] === 5);
$k = count($hcalls);
vestra_supplier_forward_run(['now' => $now + 99999, 'mail' => $mailer, 'http' => $httpDown, 'quiet' => true]);
$t('vazgecilmis is sonraki kosuda ATLANIR',  count($hcalls) === $k);
putenv('SUPPLIER_FORWARD_MODE=email'); putenv('SUPPLIER_API_URL='); putenv('SUPPLIER_API_KEY=');

/* ── 8. SANDBOX: gercek mektup/API yok ─────────────────────────────────────── */
echo "8. sandbox\n";
$mails = []; $hcalls = [];
$all = $statuses(); $all['VES-SB']['oxapay_sandbox'] = true; vestra_write_json('order_statuses.json', $all);
$rsb = oxapay_handle_webhook(['type' => 'invoice', 'track_id' => 'TRK-VES-SB', 'status' => 'Paid'], ['mail' => $mailer, 'now' => 1760800000]);
$t('sandbox Paid: kayit paid, kuyrukta is var',   $rsb['action'] === 'paid' && ($statuses()['VES-SB']['payment_status'] ?? '') === 'paid' && isset($queue()['VES-SB']));
$t('sandbox Paid: aliciya/saticiya/operatore HICBIR mektup YOK (test adresi bos)', $mails === []);
putenv('SUPPLIER_FORWARD_MODE=api'); putenv('SUPPLIER_API_URL=https://supplier.test/orders'); putenv('SUPPLIER_API_KEY=k');
$s = vestra_supplier_forward_run(['now' => 1760800000, 'mail' => $mailer, 'http' => $httpSup]);
$t('sandbox iletim (API kipi, test adresi bos): API CAGRILMAZ, mektup YOK, yalniz log; is kapanir via sandbox-log', $hcalls === [] && $mails === [] && $s['sent'] === 1 && ($statuses()['VES-SB']['supplier_forward_via'] ?? '') === 'sandbox-log');
putenv('SUPPLIER_FORWARD_MODE=email'); putenv('SUPPLIER_API_URL='); putenv('SUPPLIER_API_KEY=');
/* test adresi doluyken: [TEST] mektup oraya */
putenv('OXAPAY_SANDBOX_TEST_EMAIL=tester@example.test');
$all = $statuses(); unset($all['VES-SB']['supplier_forwarded_at'], $all['VES-SB']['payment_status']); $all['VES-SB']['status'] = 'pending'; vestra_write_json('order_statuses.json', $all);
vestra_write_json(VESTRA_SUPPLIER_QUEUE, []);
$mails = [];
oxapay_handle_webhook(['type' => 'invoice', 'track_id' => 'TRK-VES-SB', 'status' => 'Paid'], ['mail' => $mailer, 'now' => 1760800100]);
$t('sandbox Paid + test adresi: TEK [TEST] ozet mektup test adresine, alici/satici YOK', count($mails) === 1 && $mails[0]['to'] === 'tester@example.test' && str_starts_with($mails[0]['subj'], '[TEST]'));
$mails = [];
$s = vestra_supplier_forward_run(['now' => 1760800100, 'mail' => $mailer, 'http' => $httpSup]);
$t('sandbox iletim + test adresi: [TEST] mektup test adresine, saticiya YOK, API YOK', count($mails) === 1 && $mails[0]['to'] === 'tester@example.test' && str_starts_with($mails[0]['subj'], '[TEST]') && str_contains($mails[0]['body'], 'VESTRA-VES-SB') && $hcalls === [] && ($statuses()['VES-SB']['supplier_forward_via'] ?? '') === 'sandbox-test-mail');
putenv('OXAPAY_SANDBOX_TEST_EMAIL=');
/* damgasiz kayitta .env'deki kip gecerli */
putenv('OXAPAY_SANDBOX=true');
$t('kayitta damga yoksa .env OXAPAY_SANDBOX=true -> sandbox sayilir', oxapay_is_sandbox_order([]) === true && oxapay_is_sandbox_order(['oxapay_sandbox' => false]) === false);
putenv('OXAPAY_SANDBOX=false');
$t('canli siparis (damga false) sandbox DEGIL', oxapay_is_sandbox_order(['oxapay_sandbox' => false]) === false && oxapay_is_sandbox_order([]) === false);

/* ── 9. Uc nokta dumani (php -S) ───────────────────────────────────────────── */
echo "9. uc noktalar\n";
$prepend = $sand.'/prepend.php';
file_put_contents($prepend, "<?php define('VESTRA_DATA_DIR', '".$sand."/data'); define('VESTRA_ACCOUNTS', '".$sand."/data/accounts.json'); putenv('OXAPAY_MERCHANT_API_KEY=".$key."'); putenv('OXAPAY_SANDBOX=false'); ini_set('display_errors','1');");
$port = 8098 + random_int(0, 50);
$log = $sand.'/server.log';
/* auto_prepend_file yonlendirici betigiyle islemiyor; kanca _router_local.php'de (VESTRA_TEST_PREPEND). */
$cmd = 'VESTRA_TEST_PREPEND='.escapeshellarg($prepend).' php -S 127.0.0.1:'.$port.' -t '.escapeshellarg($root.'/vestra').' '.escapeshellarg($root.'/vestra/_router_local.php').' > '.escapeshellarg($log).' 2>&1 & echo $!';
$pid = (int)trim((string)shell_exec($cmd));
usleep(600000);
$req = function (string $method, string $path, string $body = '', array $headers = []) use ($port): array {
    $ch = curl_init('http://127.0.0.1:'.$port.$path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 10, CURLOPT_HTTPHEADER => $headers]);
    if ($body !== '') curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $raw = (string)curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $hs = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE); curl_close($ch);
    return [$code, substr($raw, 0, $hs), substr($raw, $hs)];
};
if ($pid > 0) {
    /* sandbox damgali: webhook mektup gondermeye kalkmasin */
    $all = $statuses(); $all['VES-OK2']['oxapay_sandbox'] = true; $all['VES-OK2']['payment_status'] = 'pending'; $all['VES-OK2']['status'] = 'pending'; unset($all['VES-OK2']['paid_at']); vestra_write_json('order_statuses.json', $all);
    $body = json_encode(['type' => 'invoice', 'track_id' => 'TRK-VES-OK2', 'status' => 'Confirming']);
    [$c1] = $req('POST', '/oxapay/webhook', $body, ['Content-Type: application/json', 'HMAC: deadbeef']);
    $t('webhook: yanlis HMAC -> 400',                 $c1 === 400);
    [$c2, , $b2] = $req('POST', '/oxapay/webhook', $body, ['Content-Type: application/json', 'HMAC: '.hash_hmac('sha512', $body, $key)]);
    $t('webhook: dogru HMAC -> 200 "ok", kayit confirming', $c2 === 200 && trim($b2) === 'ok' && ($statuses()['VES-OK2']['payment_status'] ?? '') === 'confirming');
    [$c3] = $req('GET', '/oxapay/webhook');
    $t('webhook: GET -> 405',                         $c3 === 405);
    [$c4, $h4] = $req('GET', '/orders/VES-OK2/pay/crypto');
    $t('pay ucu: GET -> 303 siparis sayfasina',       $c4 === 303 && str_contains($h4, '/order-confirm?ref=VES-OK2'));
    [$c5] = $req('POST', '/orders/VES-OK2/pay/crypto', 'x=1');
    $t('pay ucu: oturumsuz POST -> 403 (yalniz sahibi)', $c5 === 403);
    [$c6] = $req('POST', '/orders/NOPE/pay/crypto', 'x=1');
    $t('pay ucu: olmayan siparis -> 404',             $c6 === 404);
    [$c7, $h7] = $req('GET', '/orders/VES-OK2/payment-return');
    $t('return ucu: oturumsuz -> login\'e yonlendirir', in_array($c7, [302, 303], true) && str_contains($h7, '/login'));
    /* Kasa (15 Eki 2026): posta kodu + sehir ayri alan; kripto secenegi + %2 indirim satiri + "VESTRA'ya odersiniz" metni + cuzdansiz musteri notu. */
    [$c8, , $b8] = $req('GET', '/cart');
    $t('kasa 200',                                     $c8 === 200);
    $t('kasa: posta kodu ve sehir alanlari AYRI',      str_contains($b8, 'name="postcode"') && str_contains($b8, 'name="city"') && str_contains($b8, 'name="address"'));
    $t('kasa: kripto secenegi + -2% etiketi',          str_contains($b8, 'id="payCrypto"') && str_contains($b8, '· −2%'));
    $t('kasa: kripto indirim satiri + oran JS\'de',    str_contains($b8, 'id="cryptoDiscLine"') && str_contains($b8, 'CRYPTO_DISC_RATE = 0.02'));
    $t('kasa: "VESTRA\'ya odersiniz, satici sevkten sonra" + cuzdansiz musteri notu', str_contains($b8, 'never the seller') && str_contains($b8, 'No crypto wallet?'));
    $t('kasa: yaptirim kalibi JS\'de',                  str_contains($b8, 'CRYPTO_BLOCK_RE = new RegExp('));
    $t('sabit: VESTRA_CRYPTO_DISCOUNT_PCT = 2',         VESTRA_CRYPTO_DISCOUNT_PCT == 2.0);
    $t('kasa: haric markalar JS\'de (polvich, pili perez)', str_contains($b8, 'CRYPTO_DISC_EXCL = ["polvich","pili perez"]'));
    $t('kasa: Connect kapali -> ESCROW_MAP bos',         str_contains($b8, 'var ESCROW_MAP = {}') || str_contains($b8, 'var ESCROW_MAP = []'));
    $t('kasa: Connect kapali -> escrow secenegi HIC CIZILMEZ, yalniz havale + kripto', !str_contains($b8, 'id="payEscrow"') && str_contains($b8, 'id="payBank"') && str_contains($b8, 'id="payCrypto"') && !str_contains($b8, 'secure card escrow') && str_contains($b8, 'paid to VESTRA'));
    $t('kasa: havale secenegi "VESTRA denetler" cumlesi',  str_contains($b8, 'VESTRA oversees your order and the delivery'));
    $t('kasa: havale secenegi "24 saat icinde fatura" cumlesi', str_contains($b8, 'Within 24 hours of your purchase, your invoice with the payment details is emailed'));
    shell_exec('kill '.$pid.' 2>/dev/null');
} else { $t('php -S baslatilamadi', false); }

/* ── 10. Kripto indirimi: haric markalar, otomatik geri alma / yeniden uygulama ─ */
echo "10. kripto indirimi\n";
$t('haric marka: Polvich / Pili Pérez (aksan, buyuk harf toleransli)', vestra_crypto_brand_excluded('Polvich') && vestra_crypto_brand_excluded('PILI PÉREZ') && vestra_crypto_brand_excluded('pili perez') && !vestra_crypto_brand_excluded('Lacoste'));
$L = [['brand' => 'Lacoste', 'line' => 500.0], ['brand' => 'Polvich', 'line' => 300.0], ['brand' => 'Pili Pérez', 'line' => 200.0]];
$t('uygun mal bedeli: yalniz Lacoste (500)',        vestra_crypto_eligible_goods($L) == 500.0);
$t('indirim: 2% x 500 = 10.00 (kuponsuz)',           vestra_crypto_discount_amount($L, 1000.0, 0.0) == 10.0);
$t('indirim: kupon %10 varsa 2% x 500 x 0.9 = 9.00',  vestra_crypto_discount_amount($L, 1000.0, 100.0) == 9.0);
$t('tum sepet haric markaysa indirim 0',             vestra_crypto_discount_amount([['brand' => 'Polvich', 'line' => 900.0]], 900.0, 0.0) == 0.0);
/* Siparis: 1300 mal, kripto indirimi 26 uygulanmis, kripto odenmemis */
$hh = fopen($sand.'/data/orders.csv', 'a');
fputcsv($hh, ['2026-10-15T10:00:00+00:00', 'VES-CD', 'Buyer BV', '', 'Bea', 'buyer@example.test', 'Chile', '', '10x SKU-A @50 | 2x SKU-B @400', '1274.00', '0', '1274.00', '1274.00', 'Payment: Crypto (OxaPay). Crypto payment discount (-2%) = -€26.00 (applies only when paid in crypto).', 'yes', 'v1', '', '26.00', '', ''], ',', '"', '\\');
fputcsv($hh, ['2026-10-15T10:00:00+00:00', 'VES-CDP', 'Buyer BV', '', 'Bea', 'buyer@example.test', 'Chile', '', '10x SKU-A @50', '490.00', '0', '490.00', '490.00', 'Payment: Crypto (OxaPay).', 'yes', 'v1', '', '10.00', '', ''], ',', '"', '\\');
fclose($hh);
$all = $statuses();
$all['VES-CD']  = ['status' => 'pending', 'payment_method' => 'oxapay', 'payment_status' => 'pending', 'oxapay_track_id' => 'TRK-VES-CD', 'oxapay_created_at' => date('c', 1760900000), 'crypto_discount' => 26.0, 'crypto_discount_state' => 'applied', 'discount_voucher_part' => 0.0];
$all['VES-CDP'] = ['status' => 'pending', 'payment_method' => 'oxapay', 'payment_status' => 'paid', 'paid_at' => 'x', 'oxapay_track_id' => 'TRK-VES-CDP', 'oxapay_created_at' => date('c', 1760900000), 'crypto_discount' => 10.0, 'crypto_discount_state' => 'applied', 'discount_voucher_part' => 0.0];
vestra_write_json('order_statuses.json', $all);
$idx = vestra_read_json(VESTRA_OXAPAY_INDEX); $idx['TRK-VES-CD'] = 'VES-CD'; $idx['TRK-VES-CDP'] = 'VES-CDP'; vestra_write_json(VESTRA_OXAPAY_INDEX, $idx);
$sw = oxapay_expiry_sweep(['now' => 1760900000 + 60 * 60, 'quiet' => true]);
$t('sweep 60 dk: henuz dokunmaz (lifetime 60 + 15 dk tolerans)', $sw === ['expired' => 0, 'reverted' => 0] && (float)$rowOf('VES-CD')['total'] == 1274.0);
$sw = oxapay_expiry_sweep(['now' => 1760900000 + 76 * 60, 'quiet' => true]);
$rcd = $rowOf('VES-CD'); $ecd = $statuses()['VES-CD'];
$t('sweep 76 dk: fatura expired + indirim GERI ALINDI (total 1300, discount bos)', $sw === ['expired' => 1, 'reverted' => 1] && (float)$rcd['total'] == 1300.0 && (string)$rcd['discount'] === '' && (float)$rcd['subtotal'] == 1300.0);
$t('kayit: payment_status expired, crypto_discount_state reverted, gecmis satiri', ($ecd['payment_status'] ?? '') === 'expired' && ($ecd['crypto_discount_state'] ?? '') === 'reverted' && str_contains(json_encode($ecd['history'] ?? []), 'removed'));
$t('ODENMIS kripto siparisine (VES-CDP) DOKUNULMADI',  (float)$rowOf('VES-CDP')['total'] == 490.0 && ($statuses()['VES-CDP']['crypto_discount_state'] ?? '') === 'applied');
$t('ikinci sweep: hicbir sey degismez',              oxapay_expiry_sweep(['now' => 1760900000 + 90 * 60, 'quiet' => true]) === ['expired' => 0, 'reverted' => 0]);
/* Yeniden kripto faturasi: indirim geri gelir, OxaPay tutari 1274 */
$calls = [];
$rc = oxapay_create_invoice($rowOf('VES-CD'), ['http' => $httpOk]);
$t('yeni kripto faturasi: indirim YENIDEN uygulandi, tutar 1274', $rc['ok'] && ($calls[0]['body']['amount'] ?? 0) == 1274.0 && (float)$rowOf('VES-CD')['total'] == 1274.0 && ($statuses()['VES-CD']['crypto_discount_state'] ?? '') === 'applied');
/* Webhook Failed -> geri al */
oxapay_handle_webhook(['type' => 'invoice', 'track_id' => 'TRK-VES-CD', 'status' => 'Failed'], ['mail' => $mailer, 'now' => 1760910000, 'quiet' => true]);
$t('webhook Failed: indirim yine geri alindi (total 1300)', (float)$rowOf('VES-CD')['total'] == 1300.0 && ($statuses()['VES-CD']['crypto_discount_state'] ?? '') === 'reverted');
/* Kuponlu siparis: kupon payi KALIR */
$hh = fopen($sand.'/data/orders.csv', 'a');
fputcsv($hh, ['2026-10-15T10:00:00+00:00', 'VES-CDV', 'Buyer BV', '', 'Bea', 'buyer@example.test', 'Chile', '', '10x SKU-A @50', '465.50', '0', '465.50', '465.50', 'Payment: Crypto (OxaPay). Welcome discount WELCOME5 (-5%) = -€25.00 (first order). Crypto payment discount (-2%) = -€9.50.', 'yes', 'v1', 'WELCOME5', '34.50', '', ''], ',', '"', '\\');
fclose($hh);
$all = $statuses(); $all['VES-CDV'] = ['status' => 'pending', 'payment_method' => 'oxapay', 'payment_status' => 'expired', 'oxapay_track_id' => 'TRK-VES-CDV', 'crypto_discount' => 9.5, 'crypto_discount_state' => 'applied', 'discount_voucher_part' => 25.0]; vestra_write_json('order_statuses.json', $all);
$rv = oxapay_crypto_discount_revert('VES-CDV', ['quiet' => true]);
$rcv = $rowOf('VES-CDV');
$t('kuponlu siparis: kripto payi gitti, kupon payi KALDI (discount 25, code WELCOME5, total 475)', $rv['changed'] && (float)$rcv['discount'] == 25.0 && $rcv['voucher_code'] === 'WELCOME5' && (float)$rcv['total'] == 475.0);
$t('revert idempotent',                               oxapay_crypto_discount_revert('VES-CDV', ['quiet' => true])['changed'] === false);

/* ── 11. Stripe Connect anahtari (kapali) ──────────────────────────────────── */
echo "11. connect\n";
require_once $root.'/vestra/inc/escrow.php';
putenv('STRIPE_CONNECT_ENABLED=');
$t('Connect varsayilan KAPALI',                       !vestra_connect_enabled());
$t('kapaliyken hazir satici bile escrow_seller_ready FALSE', !escrow_seller_ready(['id' => 'S1', 'escrow_ready' => true, 'stripe_account_id' => 'acct_1']));
$t('kapaliyken Stripe suresi bandi yok (phase none)',   auth_seller_stripe_deadline(['type' => 'seller', 'stripe_deadline' => '2026-01-01', 'stripe_account_id' => ''])['phase'] === 'none');
putenv('STRIPE_CONNECT_ENABLED=1');
$t('STRIPE_CONNECT_ENABLED=1 ile acilir',             vestra_connect_enabled() && escrow_seller_ready(['id' => 'S1', 'escrow_ready' => true, 'stripe_account_id' => 'acct_1']) && auth_seller_stripe_deadline(['type' => 'seller', 'stripe_deadline' => '2026-01-01', 'stripe_account_id' => ''])['phase'] === 'overdue');
putenv('STRIPE_CONNECT_ENABLED=');

/* ── Sizinti: kum havuzu disina yazilmadi ──────────────────────────────────── */
$t('gercek data/ dizinine oxapay dosyasi yazilmadi', !file_exists($root.'/vestra/data/'.VESTRA_OXAPAY_INDEX) && !file_exists($root.'/vestra/data/'.VESTRA_SUPPLIER_QUEUE));

shell_exec('rm -rf '.escapeshellarg($sand));
echo "\n{$ok} ok, {$bad} fail\n";
exit($bad ? 1 : 0);
