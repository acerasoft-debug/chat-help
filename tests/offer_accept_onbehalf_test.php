<?php
/* ALICININ KABULUNU OPERATOR ADINA KAYDETME (operatör, 7 Eyl 2026: "alici kabul
 * edecegini yazdi sen kabul et sistemde faturayi yapabilelim").
 *
 * Alici kabulunu mesajla bildirdiginde satici tarafi bunu sisteme YAZAMIYORDU:
 * vestra_offer_respond() sira alicidayken satici tarafini reddediyor (KURAL 4)
 * ve elimizde alicinin kabul linki yok. Yani anlasma kapanmis ama kayit
 * acik kaliyor ve fatura kesilemiyor.
 *
 * Cozum, alicinin KENDI kabul yolunu operator adina calistirmak. Tutulanlar:
 *   - sira/fiyat kontrolleri AYNEN duruyor (KURAL 4 delinmiyor),
 *   - fiyat BIZIM karsi teklifimiz -- alicinin ilk teklifi degil,
 *   - kayit KIMIN kaydettigini soyluyor: 'operator' + dayanak metni,
 *     cunku 'buyer' yazmak bir anlasmazlikta kaydin soyleyemeyecegi bir sey,
 *   - dayanaksiz kabul islenmez.
 */
$src = file_get_contents(__DIR__.'/../vestra/inc/offers.php');
if (!preg_match('/^function vestra_offer_accept_counter\(.*?^}/ms', $src, $m)) {
    echo "HATA: vestra_offer_accept_counter bulunamadi\n"; exit(1);
}
$fn = $m[0];

$ok=0; $fail=0;
$t = function (string $n, bool $c) use (&$ok,&$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};

echo "== 1. Imza ve kablolama (kaynak duzeyinde) ==\n";
$t('ucuncu parametre var',        str_contains($fn, '?string $onBehalfBasis = null'));
$t('token kontrolu atlanabiliyor', str_contains($fn, 'if (!$onBehalf) {'));
$t('sira kontrolu KORUNUYOR',      str_contains($fn, "vestra_offer_turn(\$resp) !== 'buyer'"));
$t('fiyat karsi tekliften',        str_contains($fn, "\$unit = (float)(\$resp['counter_price'] ?? 0)"));
$t('kayit imzasi ayrisiyor',       str_contains($fn, "\$onBehalf ? 'operator' : 'buyer'"));
$t('dayanak kayda giriyor',        str_contains($fn, "'accept_basis'"));
$t('operator bildirimi ayri',      str_contains($fn, 'recorded by operator'));
/* Bos dayanak "adina kayit" saymamali: o zaman token de aranmaz ve kayit
   kimin neye dayanarak kapattigini soyleyemez. */
$t('bos dayanak adina-kayit degil', str_contains($fn, "trim(\$onBehalfBasis) !== ''"));

echo "\n== 2. Davranis ==\n";
/* Gercek fonksiyonu kosmak icin bagimliliklari yerine koyuyoruz: kayit
   okuma/yazma bellekte, gonderim/bildirim yolları sessiz. */
$GLOBALS['__rs'] = [];
$GLOBALS['__notified'] = [];
function vestra_read_json(string $f): array { return $GLOBALS['__rs']; }
function vestra_write_json(string $f, array $d): void { $GLOBALS['__rs'] = $d; }
function vestra_offer_turn(?array $r): string {
    $st = (string)($r['status'] ?? '');
    if ($st === 'accept' || $st === 'decline') return '';
    return $st === 'counter' ? 'buyer' : 'seller';
}
function vestra_offer_row(string $ref): ?array {
    return ['ref'=>$ref,'sku'=>'8045006','qty'=>10,'offer_unit'=>100.0,
            'email'=>'stocketchic@example.test','company'=>'Stock&chic','product'=>'Burberry Hoodie'];
}
function vestra_listing_by_sku(string $s): ?array { return ['id'=>'bur-8045006','brand'=>'Burberry','name'=>'Hoodie','seller_uid'=>'tyrex']; }
function auth_find(string $e) { return null; }
function vestra_offer_issue_invoice(string $r, bool $pdf) { return ['no'=>'']; }
function vestra_notify(string $s, string $b): void { $GLOBALS['__notified'][] = $s."\n".$b; }
function vestra_msg_post_system(...$a): void {}
function vestra_push_send(...$a): void {}
function vestra_user_lang($a): string { return 'en'; }
function vestra_tpl_offer_counter_accepted(...$a): array { return ['s','b',[]]; }
function vestra_send_mail(...$a): bool { return true; }
eval(preg_replace("#require_once __DIR__\.'/[a-z_]+\.php';#", '', $fn));

$reset = function () { $GLOBALS['__rs'] = ['O795BA' => [
    'status'=>'counter','counter_price'=>110.0,'accept_token'=>'tok-abc',
    'responded_at'=>'2026-09-04T10:00:00+00:00','responded_by'=>'operator']]; };

$reset();
$r = vestra_offer_accept_counter('O795BA', '', 'buyer confirmed by message on 7 Sep');
$t('adina kayit basarili',   !empty($r['ok']));
$t('fiyat karsi teklif 110', abs(((float)$r['unit']) - 110.0) < 0.001);
$rec = $GLOBALS['__rs']['O795BA'];
$t('durum accept',           ($rec['status'] ?? '') === 'accept');
$t('imza operator',          ($rec['accepted_by'] ?? '') === 'operator');
$t('adina bayragi',          !empty($rec['accepted_on_behalf']));
$t('dayanak saklandi',       str_contains((string)($rec['accept_basis'] ?? ''), 'confirmed by message'));
$t('anlasilan birim 110',    abs(((float)($rec['agreed_unit'] ?? 0)) - 110.0) < 0.001);
$t('operatore haber gitti',  count($GLOBALS['__notified']) === 1 && str_contains($GLOBALS['__notified'][0], 'recorded by operator'));

echo "\n== 3. Token yolu DEGISMEDI ==\n";
$reset(); $GLOBALS['__notified'] = [];
$t('yanlis token reddedilir', empty(vestra_offer_accept_counter('O795BA', 'yanlis')['ok']));
$reset();
$r2 = vestra_offer_accept_counter('O795BA', 'tok-abc');
$t('dogru token gecer',      !empty($r2['ok']));
$t('imza buyer kaliyor',     ($GLOBALS['__rs']['O795BA']['accepted_by'] ?? '') === 'buyer');
$t('adina bayragi YOK',      !isset($GLOBALS['__rs']['O795BA']['accepted_on_behalf']));

echo "\n== 4. KURAL 4 delinmiyor ==\n";
/* Sira BIZDEYSE (alici karsi teklif verdi) operator de kapatamaz: fiyati
   baglayan taraf biz olurduk. */
$GLOBALS['__rs'] = ['O795BA' => ['status'=>'buyer_counter','counter_price'=>105.0]];
$t('sira bizdeyken kaydedilmez', empty(vestra_offer_accept_counter('O795BA', '', 'dayanak')['ok']));
$GLOBALS['__rs'] = ['O795BA' => ['status'=>'accept','counter_price'=>110.0]];
$t('kapanmis teklif tekrar kapanmaz', empty(vestra_offer_accept_counter('O795BA', '', 'dayanak')['ok']));

echo "\n== 5. Is akisi kablolamasi ==\n";
$wf = (string)@file_get_contents(__DIR__.'/../.github/workflows/send-campaign-preview.yml');
$t('offer_accept kipi var',       str_contains($wf, "\$letter === 'offer_accept'"));
$t('dayanak zorunlu',             str_contains($wf, 'basis= gerekli'));
$t('send=false varsayilan guvenli',str_contains($wf, 'KAYDEDILMEDI'));
$t('karisik alici reddediliyor',  str_contains($wf, 'FARKLI alicilara ait'));
$t('sira/durum onden dogrulaniyor',str_contains($wf, "yalniz bizim karsi teklifimiz beklerken"));

echo "\nTOPLAM: {$ok} gecti, {$fail} kaldi\n";
exit($fail === 0 ? 0 : 1);
