<?php
/**
 * Akış testleri — CLI
 * ===================
 *   php tools/test-flow.php
 *
 * Stripe'a GERÇEK istek atmadan doğrulanabilen her şeyi doğrular:
 *  • Vault kademe matematiği (fiyat asla tabanın altına inmez, referans fiyat
 *    yalnızca gerçek bir indirim varsa üretilir)
 *  • Komisyon hesabı ve ödeme akışı modeli seçimi (destination / separate / none)
 *  • Webhook imza doğrulaması (geçerli, bozuk, süresi geçmiş)
 *  • Sipariş yaşam döngüsü: pending → paid, stok düşümü, los kapanması,
 *    idempotency (aynı olay iki kez gelirse tek etki)
 *
 * Testler GEÇİCİ bir veri dizininde çalışır (VR_DATA_DIR) — canlı veriye
 * dokunmaz. Bu yüzden bu dosyayı üretimde de güvenle çalıştırabilirsin.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Nur über die Kommandozeile.\n";
    exit(1);
}

// ---- izole veri dizini (canlı JSON dosyalarına dokunmadan test)
$tmp = sys_get_temp_dir() . '/maxsales-test-' . bin2hex(random_bytes(4));
@mkdir($tmp, 0750, true);
putenv('VR_DATA_DIR=' . $tmp);
putenv('VR_ORIGIN=https://test.local');
putenv('VR_BASE_URL=');
// Testler e-posta GÖNDERMEZ: mail() yoksa stderr'e "sendmail not found" düşer
// ve çıktı okunmaz olur. Bu bayrak staging kurulumlarında da işe yarıyor.
putenv('VR_NO_MAIL=1');

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/orders.php';
require_once __DIR__ . '/../inc/listings.php';
require_once __DIR__ . '/../inc/alerts.php';
require_once __DIR__ . '/../inc/lists.php';

$pass = 0;
$fail = 0;

function ok(bool $cond, string $label, string $detail = ''): void
{
    global $pass, $fail;
    if ($cond) { $pass++; printf("  [ok  ] %-52s %s\n", $label, $detail); }
    else       { $fail++; printf("  [FAIL] %-52s %s\n", $label, $detail); }
}
function head(string $t): void { echo "\n== {$t} ==\n"; }

echo "MAXSALES Retail — Ablauftests\n" . str_repeat('=', 74) . "\n";
echo "Datenverzeichnis (temporär): {$tmp}\n";

// ===========================================================================
head('1. Vault-Preisplan');

$now  = time();
$open = 30000;   // 300,00 €
$floor = 15000;  // 150,00 €
$steps = 5;
$hours = 24;

$mk = fn(int $offsetHours) => [
    'lot_id' => 'test', 'product_id' => 'x',
    'open_cents' => $open, 'floor_cents' => $floor,
    'steps' => $steps, 'step_hours' => $hours,
    'start_at' => $now - $offsetHours * 3600, 'status' => 'open',
];

$s0 = vr_vault_state($mk(0), $now);
ok($s0['cents'] === $open, 'Stufe 1 = Eröffnungspreis', vr_money($s0['cents']));
ok($s0['reference'] === null, 'Stufe 1 hat KEINEN Referenzpreis',
   'PAngV: ohne Senkung keine Senkungsangabe');
ok($s0['step'] === 0 && $s0['steps'] === $steps, 'Stufenzählung', "{$s0['step']}/{$s0['steps']}");

$s1 = vr_vault_state($mk(25), $now);          // 1 kademe geçti
ok($s1['step'] === 1, 'Nach 25 h → Stufe 2', (string)($s1['step'] + 1));
ok($s1['cents'] === 27000, 'Stufe 2 Preis korrekt', vr_money($s1['cents']) . ' (erwartet 270,00 €)');
ok($s1['reference'] === $open, 'Referenzpreis = vorherige Stufe', vr_money((int)$s1['reference']));
ok($s1['next_cents'] === 24000, 'Nächste Stufe angekündigt', vr_money((int)$s1['next_cents']));
ok($s1['next_at'] > $now, 'Nächster Senkungszeitpunkt in der Zukunft', vr_date((int)$s1['next_at'], true));

$sEnd = vr_vault_state($mk(1000), $now);      // planın çok ötesi
ok($sEnd['cents'] === $floor, 'Weit nach Planende → Bodenpreis', vr_money($sEnd['cents']));
ok($sEnd['at_floor'] === true, 'at_floor gesetzt');
ok($sEnd['next_at'] === null, 'Keine weitere Senkung angekündigt');
ok($sEnd['reference'] === null, 'Alte Senkung wird nicht mehr als Referenz beworben',
   '> 30 Tage → keine Angabe');

// Monotonluk: hiçbir kademe bir öncekinden pahalı olamaz, taban altına inemez.
$prev = PHP_INT_MAX;
$mono = true;
for ($i = 0; $i <= $steps + 2; $i++) {
    $st = vr_vault_state($mk((int)($i * $hours) + 1), $now);
    if ($st['cents'] > $prev || $st['cents'] < $floor || $st['cents'] > $open) $mono = false;
    $prev = $st['cents'];
}
ok($mono, 'Preis fällt monoton und bleibt im Plan', "{$open} … {$floor}");

$bad = vr_vault_state(['lot_id' => 'b', 'product_id' => 'x', 'open_cents' => 100,
                       'floor_cents' => 900, 'steps' => 3, 'step_hours' => 24, 'start_at' => $now], $now);
ok($bad === null, 'Boden > Eröffnung wird abgelehnt', 'Los wird ausgeblendet');

$future = vr_vault_state($mk(-48), $now);
ok($future['phase'] === 'scheduled', 'Zukünftiges Los = geplant', 'öffnet in ' . vr_until((int)$future['next_at']));

// ===========================================================================
head('2. Provision');

$feeB = vr_fee_cents(10000, 'business');
$feeP = vr_fee_cents(10000, 'private');
$feeV = vr_fee_cents(10000, 'business', true);
ok($feeB === 1235, 'Händler 12 % + 0,35 € auf 100 €', vr_money($feeB));
ok($feeP === 935,  'Privat 9 % + 0,35 € auf 100 €',   vr_money($feeP));
ok($feeV === 1535, 'Vault 15 % + 0,35 € auf 100 €',   vr_money($feeV));
ok(vr_fee_cents(50, 'private') < 50, 'Provision kann Betrag nie übersteigen', vr_money(vr_fee_cents(50, 'private')));

// ===========================================================================
head('3. Bestellaufbau und Auszahlungsmodell');

// Test satıcıları: biri Connect'te hazır, biri değil.
$readyId  = 'rs-testready';
$notReady = 'rs-testpending';
vr_store_write(VR_SELLERS_FILE, [
    ['id' => $readyId, 'email' => 'ready@test.local', 'pw_hash' => '', 'name' => 'Ready Shop',
     'company' => 'Ready Shop GmbH', 'seller_type' => 'business', 'country' => 'DE', 'status' => 'active',
     'stripe_account_id' => 'acct_TESTREADY', 'charges_enabled' => true, 'payouts_enabled' => true],
    ['id' => $notReady, 'email' => 'pending@test.local', 'pw_hash' => '', 'name' => 'Pending Person',
     'company' => '', 'seller_type' => 'private', 'country' => 'DE', 'status' => 'active',
     'stripe_account_id' => '', 'charges_enabled' => false],
]);

// Test ürünleri (retail-listings.json → katalog bunları görür)
vr_store_write(VR_LISTINGS_FILE, [
    ['id' => 'tp-ready', 'seller_uid' => $readyId, 'seller_type' => 'business', 'brand' => 'TESTHOUSE',
     'name' => 'TESTHOUSE Coat', 'cat' => 'Coats', 'sku' => 'T-1', 'retail_price' => 200.00,
     'size_stock' => ['M' => 2], 'images' => ['/uploads/x.jpg'], 'status' => 'approved'],
    ['id' => 'tp-priv', 'seller_uid' => $notReady, 'seller_type' => 'private', 'brand' => 'TESTHOUSE',
     'name' => 'TESTHOUSE Bag', 'cat' => 'Bags', 'sku' => 'T-2', 'retail_price' => 100.00,
     'size_stock' => ['ONE' => 1], 'images' => ['/uploads/y.jpg'], 'status' => 'approved'],
    ['id' => 'tp-own', 'seller_uid' => '', 'seller_type' => 'vestra', 'brand' => 'TESTHOUSE',
     'name' => 'TESTHOUSE Scarf', 'cat' => 'Accessories', 'sku' => 'T-3', 'retail_price' => 50.00,
     'size_stock' => ['ONE' => 3], 'images' => ['/uploads/z.jpg'], 'status' => 'approved'],
]);

// Katalog önbelleğini boşaltmak için ayrı süreçlerde çalışmamız gerekirdi;
// bunun yerine sepeti doğrudan kurup sipariş kurucusunu çağırıyoruz.
$_SESSION = [];

$cat = vr_catalog();
ok(isset($cat['tp-ready'], $cat['tp-priv'], $cat['tp-own']), 'Testartikel im Katalog', count($cat) . ' Artikel');
ok((int)$cat['tp-ready']['price_cents'] === 20000, 'Preis aus retail_price übernommen', vr_money((int)$cat['tp-ready']['price_cents']));

// --- A) tek satıcı, hazır → destination
vr_cart_clear(false);
vr_cart_add('tp-ready', 'M', 1);
[$okA, $orderA] = vr_order_build('DE');
ok($okA, 'Bestellung A aufgebaut');
ok($orderA['payout_mode'] === 'destination', 'A: Modell = destination charge', $orderA['payout_mode']);
ok($orderA['destination'] === 'acct_TESTREADY', 'A: Ziel = Verkäuferkonto', $orderA['destination']);
$expFeeA = vr_fee_cents(20000, 'business');
ok($orderA['commission_cents'] === $expFeeA, 'A: Provision', vr_money((int)$orderA['commission_cents']));
ok($orderA['application_fee_cents'] === $expFeeA + (int)$orderA['ship_cents'],
   'A: application_fee = Provision + Versand',
   vr_money((int)$orderA['application_fee_cents']) . ' (Versand ' . vr_money((int)$orderA['ship_cents']) . ')');
ok($orderA['by_seller'][$readyId]['net'] === 20000 - $expFeeA, 'A: Verkäuferanteil',
   vr_money((int)$orderA['by_seller'][$readyId]['net']));
$sumA = (int)$orderA['by_seller'][$readyId]['net'] + (int)$orderA['application_fee_cents'];
ok($sumA === (int)$orderA['total_cents'], 'A: Verkäuferanteil + Gebühr = Gesamtbetrag',
   vr_money($sumA) . ' = ' . vr_money((int)$orderA['total_cents']));

// --- B) iki satıcı → separate charges & transfers
vr_cart_clear(false);
vr_cart_add('tp-ready', 'M', 1);
vr_cart_add('tp-priv', 'ONE', 1);
[$okB, $orderB] = vr_order_build('DE');
ok($okB && $orderB['payout_mode'] === 'separate', 'B: Zwei Verkäufer → separate transfers', $orderB['payout_mode']);
ok($orderB['application_fee_cents'] === 0, 'B: kein application_fee (Transfers separat)');
ok((int)$orderB['subtotal_cents'] === 30000, 'B: Zwischensumme', vr_money((int)$orderB['subtotal_cents']));
ok($orderB['has_private'] === true, 'B: Privatverkäufer erkannt', 'Hinweis im Checkout nötig');
$netReady = (int)$orderB['by_seller'][$readyId]['net'];
$netPriv  = (int)$orderB['by_seller'][$notReady]['net'];
ok($netReady === 20000 - vr_fee_cents(20000, 'business'), 'B: Anteil Händler', vr_money($netReady));
ok($netPriv === 10000 - vr_fee_cents(10000, 'private'), 'B: Anteil Privat', vr_money($netPriv));
$platform = (int)$orderB['total_cents'] - $netReady - $netPriv;
ok($platform === (int)$orderB['commission_cents'] + (int)$orderB['ship_cents'],
   'B: Plattform behält Provisionen + Versand', vr_money($platform));

// --- C) sadece kendi stoğumuz → dağıtım yok
vr_cart_clear(false);
vr_cart_add('tp-own', 'ONE', 2);
[$okC, $orderC] = vr_order_build('DE');
ok($okC && $orderC['payout_mode'] === 'none', 'C: Eigene Ware → kein Payout', $orderC['payout_mode']);
ok((int)$orderC['commission_cents'] === 0, 'C: keine Provision auf eigene Ware');

// --- kargo eşiği
vr_cart_clear(false);
vr_cart_add('tp-own', 'ONE', 1);                     // 50 € → eşiğin altında
[$_, $orderShip] = vr_order_build('DE');
ok((int)$orderShip['ship_cents'] === (int)vr_config('shipping')['de']['cents'],
   'Versandkosten unter Freigrenze', vr_money((int)$orderShip['ship_cents']));
vr_cart_clear(false);
vr_cart_add('tp-ready', 'M', 1);
vr_cart_add('tp-own', 'ONE', 1);                     // 250 € → eşik üstü
[$_, $orderFree] = vr_order_build('DE');
ok((int)$orderFree['ship_cents'] === 0, 'Versandfrei über Freigrenze',
   'ab ' . vr_money((int)vr_config('shipping')['de']['free_over']));

// KDV payı brütten doğru ayrışıyor mu?
$vat = vr_vat_part(11900);
ok($vat === 1900, 'MwSt-Anteil aus Bruttobetrag (119 € → 19 €)', vr_money($vat));

// ===========================================================================
head('4. Webhook-Signatur');

$secret  = 'whsec_test_' . bin2hex(random_bytes(8));
$payload = '{"id":"evt_test","type":"checkout.session.completed"}';
$ts      = time();
$sig     = hash_hmac('sha256', $ts . '.' . $payload, $secret);

ok(vr_stripe_verify_webhook($payload, "t={$ts},v1={$sig}", $secret), 'Gültige Signatur akzeptiert');
ok(!vr_stripe_verify_webhook($payload, "t={$ts},v1=" . str_repeat('0', 64), $secret), 'Falsche Signatur abgelehnt');
ok(!vr_stripe_verify_webhook($payload . 'x', "t={$ts},v1={$sig}", $secret), 'Manipulierter Body abgelehnt');
$oldTs  = $ts - 3600;
$oldSig = hash_hmac('sha256', $oldTs . '.' . $payload, $secret);
ok(!vr_stripe_verify_webhook($payload, "t={$oldTs},v1={$oldSig}", $secret), 'Zu alter Zeitstempel abgelehnt (Replay)');
ok(!vr_stripe_verify_webhook($payload, '', $secret), 'Fehlender Header abgelehnt');
ok(!vr_stripe_verify_webhook($payload, "t={$ts},v1={$sig}", ''), 'Fehlendes Secret abgelehnt');
// Stripe birden fazla v1 gönderebilir (anahtar döndürme sırasında).
ok(vr_stripe_verify_webhook($payload, "t={$ts},v1=" . str_repeat('a', 64) . ",v1={$sig}", $secret),
   'Mehrere v1-Signaturen: eine gültige genügt');

// ===========================================================================
head('5. Bestellung bezahlt: Bestand, Los, Idempotenz');

vr_cart_clear(false);
vr_cart_add('tp-ready', 'M', 1);
[$okD, $orderD] = vr_order_build('DE');
vr_order_save($orderD);

$before = vr_product('tp-ready')['stock'] ?? 0;
$paid1 = vr_order_mark_paid((string)$orderD['id'], [
    'session_id' => 'cs_test', 'payment_intent' => 'pi_test', 'charge_id' => 'ch_test',
    'customer' => ['email' => 'buyer@test.local', 'name' => 'Test Buyer',
                   'address' => ['line1' => 'Teststr. 1', 'postal_code' => '10115', 'city' => 'Berlin', 'country' => 'DE']],
]);
ok($paid1 !== null && (string)$paid1['status'] === 'paid', 'Bestellung als bezahlt markiert');
ok((string)$paid1['customer']['email'] === 'buyer@test.local', 'Kundendaten gespeichert');

$sold = vr_store_read(VR_STOCK_FILE, []);
ok((int)($sold['tp-ready|M'] ?? 0) === 1, 'Bestand im Verkaufsregister abgezogen', 'tp-ready|M = 1');

// Aynı olay ikinci kez: stok İKİNCİ kez düşmemeli.
$paid2 = vr_order_mark_paid((string)$orderD['id'], ['session_id' => 'cs_test']);
$sold2 = vr_store_read(VR_STOCK_FILE, []);
ok((int)($sold2['tp-ready|M'] ?? 0) === 1, 'Zweiter Webhook-Aufruf ändert nichts (idempotent)',
   'tp-ready|M = ' . (int)($sold2['tp-ready|M'] ?? 0));
ok($paid2 !== null && (string)$paid2['status'] === 'paid', 'Zweiter Aufruf liefert dieselbe Bestellung');

// Connect'e hazır olmayan satıcı: pay platformda beklemeye alınmalı.
vr_cart_clear(false);
vr_cart_add('tp-priv', 'ONE', 1);
[$okE, $orderE] = vr_order_build('DE');
vr_order_save($orderE);
$paidE = vr_order_mark_paid((string)$orderE['id'], ['charge_id' => 'ch_test2']);
$held  = false;
foreach ((array)($paidE['transfers'] ?? []) as $tr) {
    if ((string)($tr['status'] ?? '') === 'held' && (string)($tr['reason'] ?? '') === 'connect_not_ready') $held = true;
}
ok($held, 'Nicht verifizierter Verkäufer: Anteil wird zurückgehalten');
ok(!empty($paidE['payout_pending']), 'payout_pending markiert', 'manuelle Auszahlung nach Verifizierung');

// Los satışı
vr_store_write(VR_VAULT_FILE, ['lots' => [[
    'lot_id' => 'testlot', 'product_id' => 'tp-own', 'open_cents' => 9000, 'floor_cents' => 5000,
    'steps' => 4, 'step_hours' => 24, 'start_at' => $now - 3600, 'status' => 'open',
]]]);
$lotPrice = vr_vault_price_for('testlot', session_id());
ok($lotPrice === 9000, 'Los-Preis vor Reservierung = Stufe 1', vr_money((int)$lotPrice));
[$rOk, , $locked] = vr_vault_reserve('testlot', session_id());
ok($rOk && $locked === 9000, 'Reservierung friert den Preis ein', vr_money((int)$locked));
[$rOk2] = vr_vault_reserve('testlot', 'anderesession');
ok(!$rOk2, 'Fremde Sitzung kann reserviertes Los nicht nehmen');
ok(vr_vault_mark_sold('testlot', 'o-test'), 'Los als verkauft markiert');
ok(vr_vault_price_for('testlot', session_id()) === null, 'Verkauftes Los ist nicht mehr kaufbar');
ok(vr_vault_mark_sold('testlot', 'o-test'), 'Zweifaches Markieren ist idempotent');

// ===========================================================================
head('6. Preisberechnung ist nicht manipulierbar');

// Sepette fiyat TUTULMUYOR: oturuma tutar enjekte edilse bile satır fiyatı
// yine katalogdan geliyor.
vr_cart_clear(false);
vr_cart_add('tp-ready', 'M', 1);
$raw = vr_cart_raw();
$key = array_key_first($raw);
$_SESSION['vr_cart'][$key]['price_cents'] = 1;      // saldırgan denemesi
$_SESSION['vr_cart'][$key]['unit_cents']  = 1;
$lines = vr_cart_lines()['lines'];
ok((int)$lines[0]['unit_cents'] === 20000, 'Eingeschmuggelter Preis wird ignoriert',
   vr_money((int)$lines[0]['unit_cents']));

// Stok üstünde adet istemek kırpılmalı.
vr_cart_clear(false);
vr_cart_add('tp-ready', 'M', 99);
$lines = vr_cart_lines()['lines'];
ok((int)$lines[0]['qty'] <= 2, 'Menge auf verfügbaren Bestand begrenzt', 'qty=' . (int)$lines[0]['qty']);


// ===========================================================================
head('7. Vault-Preisalarm');

vr_store_write(VR_VAULT_FILE, ['lots' => [[
    'lot_id' => 'alertlot', 'product_id' => 'tp-own', 'open_cents' => 20000, 'floor_cents' => 10000,
    'steps' => 4, 'step_hours' => 24, 'start_at' => $now - 3600, 'status' => 'open',
]]]);

// Geçerli eşik: taban ile güncel fiyat arasında.
[$aOk, $aKey] = vr_alert_add('alertlot', 'watcher@test.local', 15000, 'de');
ok($aOk, 'Alarm gesetzt (Boden < Wunsch < aktuell)', $aKey);

// Güncel fiyatın üstü → reddedilmeli (zaten geçilmiş bir eşik).
[$hiOk] = vr_alert_add('alertlot', 'watcher@test.local', 25000);
ok(!$hiOk, 'Wunschpreis über dem aktuellen Preis wird abgelehnt');

// Tabanın altı → asla tetiklenmez, reddedilmeli.
[$loOk] = vr_alert_add('alertlot', 'watcher@test.local', 5000);
ok(!$loOk, 'Wunschpreis unter dem Bodenpreis wird abgelehnt');

// Aynı los + aynı adres → yeni satır değil, eşik güncellemesi.
vr_alert_add('alertlot', 'watcher@test.local', 14000);
ok(count(vr_alerts_all()) === 1, 'Zweiter Alarm desselben Nutzers ersetzt den ersten',
   count(vr_alerts_all()) . ' Datensatz');

[$badMail] = vr_alert_add('alertlot', 'kein-email', 14000);
ok(!$badMail, 'Ungültige E-Mail wird abgelehnt');

// Henüz tetiklenmemeli (güncel 200 > eşik 140).
$run = vr_alerts_run(true);
ok($run['sent'] === 0, 'Probelauf sendet nichts', 'geprüft ' . $run['checked']);

// Eşiği güncel fiyatın üstüne çekip gerçek gönderimi doğrula.
$rows = vr_alerts_all();
$rows[0]['threshold_cents'] = 30000;
vr_store_write(VR_ALERTS_FILE, $rows);

$run2 = vr_alerts_run(false);
ok($run2['sent'] === 1, 'Erreichter Wunschpreis löst genau eine Mail aus');
ok(count(vr_alerts_all()) === 0, 'Alarm ist danach verbraucht (kein zweiter Versand)');

$run3 = vr_alerts_run(false);
ok($run3['sent'] === 0, 'Zweiter Lauf sendet nichts mehr');

// Satılmış losun alarmı sessizce düşer — "verpasst" postası atmıyoruz.
vr_alert_add('alertlot', 'watcher2@test.local', 15000);
vr_vault_mark_sold('alertlot', 'o-x');
$run4 = vr_alerts_run(false);
ok($run4['sent'] === 0 && $run4['dropped'] === 1, 'Verkauftes Los: Alarm wird entfernt, keine Mail');

// İptal jetonu
vr_store_write(VR_VAULT_FILE, ['lots' => [[
    'lot_id' => 'alertlot2', 'product_id' => 'tp-own', 'open_cents' => 20000, 'floor_cents' => 10000,
    'steps' => 4, 'step_hours' => 24, 'start_at' => $now - 3600, 'status' => 'open',
]]]);
vr_alert_add('alertlot2', 'cancel@test.local', 15000);
$id = (string)vr_alerts_all()[0]['id'];
ok(!vr_alert_cancel('falscher-token'), 'Falscher Storno-Token wird abgelehnt');
ok(vr_alert_cancel(vr_alert_token($id)), 'Storno-Link löscht den Alarm');
ok(count(vr_alerts_all()) === 0, 'Alarm ist weg');

// ===========================================================================
head('8. Merkliste und zuletzt angesehen');

$_COOKIE = [];
ok(vr_wish_count() === 0, 'Leere Merkliste');
vr_wish_toggle('tp-ready');
ok(vr_wish_has('tp-ready'), 'Artikel gemerkt', 'Cookie: ' . (string)($_COOKIE[VR_WISH_COOKIE] ?? ''));
ok(vr_wish_count() === 1, 'Zähler stimmt');
vr_wish_toggle('tp-ready');
ok(!vr_wish_has('tp-ready'), 'Nochmal tippen entfernt wieder');

// Çerezden gelen çöp değer temizlenmeli (XSS/enjeksiyon yüzeyi bırakmayalım).
$_COOKIE[VR_WISH_COOKIE] = 'tp-ready,<script>alert(1)</script>,tp-own,../../etc/passwd';
$ids = vr_wish_ids();
ok($ids === ['tp-ready', 'tp-own'], 'Ungültige Cookie-Einträge werden verworfen', implode(',', $ids));

$_COOKIE = [];
vr_seen_push('tp-ready');
vr_seen_push('tp-priv');
vr_seen_push('tp-ready');                       // tekrar: başa taşınmalı, çift olmamalı
$seen = vr_list_read(VR_SEEN_COOKIE, VR_SEEN_MAX);
ok($seen === ['tp-ready', 'tp-priv'], 'Zuletzt angesehen: neueste zuerst, keine Duplikate', implode(',', $seen));
ok(count(vr_seen_products('tp-ready')) === 1, 'Aktueller Artikel wird aus der Liste gefiltert');

// ===========================================================================
head('9. Abmeldung (Newsletter)');

$tok = vr_unsub_token('someone@test.local');
ok(strlen($tok) === 32, 'Abmelde-Token hat feste Länge', $tok);
ok($tok === vr_unsub_token('SOMEONE@test.local '), 'Token ignoriert Groß-/Kleinschreibung und Leerzeichen');
ok($tok !== vr_unsub_token('other@test.local'), 'Andere Adresse → anderer Token');
ok(str_contains(vr_unsub_url('someone@test.local'), $tok), 'Abmelde-URL enthält den Token');

// ===========================================================================
echo "\n" . str_repeat('=', 74) . "\n";
printf("%d Tests bestanden, %d fehlgeschlagen.\n", $pass, $fail);

// Geçici dizini temizle.
foreach (glob($tmp . '/*') ?: [] as $f) @unlink($f);
@rmdir($tmp);

exit($fail > 0 ? 1 : 0);
