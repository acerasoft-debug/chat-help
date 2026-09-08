<?php
/* TOPTAN ERISIM ABONELIGI (operator, 8 Eyl 2026):
 *   "tiklandiginda aylik odeme funktionu da olsun ... 199,90 eur olacak fiyati,
 *    eger bu fiyat odenirse toptan fiyatina satin alinabilir"
 *   "yoksa tekli dropshipping fiyati yuzde 20 eklenecek"
 *
 * Yani iki fiyat var ve IKISI DE tutulmali: abonesiz +%20, aboneli zamsiz.
 * Tek yonu test etmek yetmez -- zam kalkarsa abonelik hicbir sey satmiyor
 * olurdu, zam her zaman uygulanirsa abonelik parayi bosa aliyor olurdu.
 */
$root = __DIR__ . '/../vestra';
require_once $root . '/inc/products.php';
require_once $root . '/inc/dropship.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$src = fn(string $f) => (string)@file_get_contents($root . '/' . $f);

/* Turetilmis dropship blogu olan tipik bir ilan: en dusuk kademe 19,90. */
$prod = ['id'=>'t1','brand'=>'Dsquared2','cat'=>'T-Shirts','name'=>'Tee','status'=>'approved',
         'mode'=>'fixed','tiers'=>[['min'=>12,'price'=>19.90],['min'=>120,'price'=>17.50]]];

echo "== 1. Plan fiyati TEK KAYNAK ve dogru ==\n";
$t('fiyat 199,90',            abs(VESTRA_DROPSHIP_PLAN_PRICE - 199.90) < 0.001);
$t('Stripe kurusu 19990',     (int)round(VESTRA_DROPSHIP_PLAN_PRICE * 100) === 19990);
$t('aylik',                   VESTRA_DROPSHIP_PLAN_INTERVAL === 'month');
$t('euro',                    VESTRA_DROPSHIP_PLAN_CURRENCY === 'eur');
$t('zam HALA %20',            abs(VESTRA_DROPSHIP_MARKUP - 0.20) < 0.0001);

echo "\n== 2. Abonelik durumu: tek karar noktasi ==\n";
$t('active  -> acik',    vestra_dropship_plan_active(['dropship_plan_status'=>'active']));
$t('trialing-> acik',    vestra_dropship_plan_active(['dropship_plan_status'=>'trialing']));
$t('none    -> kapali', !vestra_dropship_plan_active(['dropship_plan_status'=>'none']));
$t('canceled-> kapali', !vestra_dropship_plan_active(['dropship_plan_status'=>'canceled']));
/* past_due BILEREK kapali: tahsil edemedigimiz bir ayricalik acik kalmaz. */
$t('past_due-> kapali', !vestra_dropship_plan_active(['dropship_plan_status'=>'past_due']));
$t('alan hic yoksa kapali', !vestra_dropship_plan_active(['id'=>'x']));
$t('null (misafir) kapali',  !vestra_dropship_plan_active(null));

echo "\n== 3. Iki fiyat da dogru ==\n";
$ds = vestra_dropship_of($prod);
$t('taban = en dusuk kademe',    abs((float)$ds['base'] - 19.90) < 0.001);
$t('liste = taban + %20',        abs((float)$ds['price'] - 23.88) < 0.001);
$t('toptan fiyat = taban',       abs(vestra_dropship_wholesale_price($prod) - 19.90) < 0.001);
$t('abonesiz oder ZAMLI',        abs(vestra_dropship_unit_price($prod, ['dropship_plan_status'=>'none']) - 23.88) < 0.001);
$t('aboneli oder ZAMSIZ',        abs(vestra_dropship_unit_price($prod, ['dropship_plan_status'=>'active']) - 19.90) < 0.001);
$t('misafir/ortak API zamli',    abs(vestra_dropship_unit_price($prod, null) - 23.88) < 0.001);
$t('iptal sonrasi yine zamli',   abs(vestra_dropship_unit_price($prod, ['dropship_plan_status'=>'canceled']) - 23.88) < 0.001);

echo "\n== 4. Kenar durumlar ==\n";
/* Elle yazilmis dropship blogu: taban yazmiyor, ilanin kademesinden gelmeli. */
$hand = $prod; $hand['dropship'] = ['enabled'=>true,'price'=>30.00];
$t('elle blok: taban kademeden',  abs(vestra_dropship_wholesale_price($hand) - 19.90) < 0.001);
$t('elle blok: abone 19,90 oder', abs(vestra_dropship_unit_price($hand, ['dropship_plan_status'=>'active']) - 19.90) < 0.001);
$t('elle blok: abonesiz 30,00',   abs(vestra_dropship_unit_price($hand, null) - 30.00) < 0.001);
/* Taban listeden BUYUKSE indirim yok -- aboneye daha pahali fiyat basilmaz. */
$weird = $prod; $weird['dropship'] = ['enabled'=>true,'price'=>10.00];
$t('taban > liste: indirim YOK',  abs(vestra_dropship_unit_price($weird, ['dropship_plan_status'=>'active']) - 10.00) < 0.001);
/* Dropship'e kapali ilan: fiyat yok, "0" degil. */
$off = $prod; $off['dropship_off'] = true;
$t('kapali ilan -> null',         vestra_dropship_unit_price($off, ['dropship_plan_status'=>'active']) === null);
$t('kapali ilan toptan -> null',  vestra_dropship_wholesale_price($off) === null);

echo "\n== 5. Fiyat MUSTERIDEN gelmiyor (siparis kurucusu) ==\n";
$dsSrc = $src('inc/dropship.php');
$t('kurucu alici hesabini aliyor', str_contains($dsSrc, '?array $buyer = null'));
$t('birim fiyat fonksiyondan',     str_contains($dsSrc, '$unit   = vestra_dropship_unit_price($p, $buyer);'));
$t('POST tutari OKUNMUYOR',        !preg_match('/\$unit\s*=\s*\(float\)\s*\(?\$_(POST|GET|REQUEST)/', $dsSrc));
$t('hangi fiyattan kesildigi kayitta', str_contains($dsSrc, "'wholesale_plan'"));
/* Odeme kapisi HALA en basta: plan eklemek onu one gecirmemeli. */
$posGate = strpos($dsSrc, 'payments_paused');
$posUnit = strpos($dsSrc, 'vestra_dropship_unit_price($p, $buyer)');
$t('odeme kapisi fiyattan ONCE',   $posGate !== false && $posUnit !== false && $posGate < $posUnit);

echo "\n== 6. Site formu hesabi geciriyor, ortak API'si GECIRMIYOR ==\n";
$co = $src('../vestra/dropship-checkout.php');
$t('site formu $dsUser geciriyor', str_contains($co, '$zone, $dsUser)'));
$api = $src('api/dropship.php');
$t('ortak API hesap gecirmiyor',   !str_contains($api, '$zone, $'));

echo "\n== 7. Webhook: alici planini SATICI uyeliginden ayiriyor ==\n";
$wh = $src('stripe/webhook.php');
$t('plan metadata ile ayirt ediliyor', substr_count($wh, "'dropship_wholesale'") >= 3);
$t('plani AYRI alana yaziyor',         str_contains($wh, "'dropship_plan_status'"));
$t('membership_status EZILMIYOR',      str_contains($wh, "\$u = ['dropship_plan_status' => \$obj->status ?? 'none'];"));
/* En onemlisi: iptal dali. Plan iptali ilan askiya ALMAMALI ve satici
   "uyeliginiz bitti" mektubu GITMEMELI. */
$delPos  = strpos($wh, 'customer.subscription.deleted');
$planPos = strpos($wh, "'dropship_plan_status' => 'canceled'");
$susPos  = strpos($wh, "\$listing['status'] = 'suspended'");
$t('iptal dalinda plan ONCE ele aliniyor', $delPos !== false && $planPos !== false && $susPos !== false
                                            && $delPos < $planPos && $planPos < $susPos);
$t('plan iptali erken cikiyor (break)',    (bool)preg_match(
      "/'dropship_plan_status' => 'canceled'\]\);\s*\n\s*break;/", $wh));

echo "\n== 8. Abonelik ucu ==\n";
$ep = $src('stripe/dropship-plan.php');
$t('dosya var',                     $ep !== '');
$t('abonelik kipi',                 str_contains($ep, "'mode'       => 'subscription'"));
$t('fiyat SABITTEN, gomulu degil',  str_contains($ep, 'VESTRA_DROPSHIP_PLAN_PRICE') && !str_contains($ep, '19990,'));
/* Rakam KODDA gomulu olmamali. Yorumdaki operator cumlesi ("199,90 eur olacak")
   bunu ihlal etmiyor ve silinmemeli -- karari kaydeden satir o. O yuzden
   olcum yorumlar AYIKLANDIKTAN sonra yapiliyor. */
$epCode = '';
foreach (@token_get_all($ep) as $tok) {
    if (is_array($tok)) { if (in_array($tok[0], [T_COMMENT, T_DOC_COMMENT], true)) continue; $epCode .= $tok[1]; }
    else $epCode .= $tok;
}
$t('199,90 KODA gomulmemis',        !str_contains($epCode, '199.90') && !str_contains($epCode, '19990'));
$t('olcum gercekten yorumsuz',      str_contains($ep, '199,90') && !str_contains($epCode, '199,90'));
$t('metadata iki tarafa da',        substr_count($ep, "'plan' => 'dropship_wholesale'") >= 1
                                    && str_contains($ep, "'subscription_data'    => ['metadata' => \$meta]"));
$t('giris sarti var',               str_contains($ep, 'auth_user()'));
$t('onayli hesap sarti var',        str_contains($ep, 'auth_prices_unlocked'));
$t('ikinci abonelik engelli',       str_contains($ep, 'vestra_dropship_plan_active($user)'));
$t('POST disi istek reddediliyor',  str_contains($ep, "REQUEST_METHOD'] !== 'POST'"));

echo "\n== 9. Iptal yolu GERCEKTEN acik ==\n";
$po = $src('stripe/portal.php');
$t('portal aliciya da acik',   str_contains($po, '$hasDsPlan'));
$t('geri adres alici icin',    str_contains($po, "'/dropship'"));
$t('bozuk &error adresi yok',  str_contains($po, '$backErr') && !str_contains($po, "\$backTo . '&error"));
$page = $src('dropship.php');
$t('sayfada iptal POST formu', str_contains($page, 'action="/stripe/portal"') && str_contains($page, 'method="post"'));

echo "\n== 10. Sayfa, odenecek fiyati gosteriyor ==\n";
$t('birim fiyat plana gore',   str_contains($page, 'vestra_dropship_unit_price($p, $dsUser)'));
$t('dugmede de ayni fiyat',    str_contains($page, "t('Buy now') ?> — <?= vestra_money((float)\$dsUnit)"));
$t('eski sabit fiyat kalmadi', !str_contains($page, "vestra_money((float)\$ds['price'])"));
$t('plan fiyati sabitten',     str_contains($page, 'vestra_money(VESTRA_DROPSHIP_PLAN_PRICE)'));
$t('zam orani sabitten',       str_contains($page, 'VESTRA_DROPSHIP_MARKUP * 100'));

echo "\n== 11. Gezilebilir dropship katalogu ==\n";
/* Operator, 8 Eyl 2026: "Dropshipping icin ayri bir sayfada acabiliriz".
   Sayfa eskiden urun SECTIRMIYORDU (12 ornek + /shop'a link), ve /shop
   dropship'e gore suzulemiyor -- yani tek adet alinabilecek seylerin listesi
   hicbir yerde yoktu. */
$t('suzgec cubugu var',            str_contains($page, "name=\"brand\"") && str_contains($page, "name=\"cat\"") && str_contains($page, "name=\"q\""));
$t('izgara ciziliyor',             str_contains($page, 'class="shopgrid"') && str_contains($page, 'class="scard"'));
$t('sayfalama var',                str_contains($page, '$dsPages > 1') && str_contains($page, '$dsUrl(['));
/* SUZGEC SECENEKLERI dropship'e ACIK kumeden turemeli: butun katalogdan
   turetilseydi Lacoste/Ralph Lauren ya da ayakkabi secilebilir ve sonuc hep
   bos cikardi -- kendi kurdugumuz bir cikmaz sokak. */
$t('secenekler dropship kumesinden', str_contains($page, 'foreach ($dsPool as $p)') || str_contains($page, '$dsPool as $p'));
$t('gecersiz suzgec yok sayiliyor',  str_contains($page, "if (\$fBrand !== '' && !isset(\$dsBrands[\$fBrand])) \$fBrand = '';"));
/* Iki gorunum AYRILMIS olmali: izgarada satin alma formu, tek urun
   gorunumunde izgara olmamali. */
$t('izgara yalniz ?id= YOKKEN',    str_contains($page, 'if (!$dsAll): ?>'));
$t('form yalniz ?id= VARKEN',      str_contains($page, 'if ($dsAll): foreach ($items as $p)'));
$t('izgara fiyati da plana gore',  str_contains($page, 'vestra_dropship_unit_price($gp, $dsUser)'));
/* Bos sonucun sebebi yazilmali: suzgec yuzunden bosalan bir sayfada
   "Nothing available right now" yanlis -- katalog dolu, secim dar. */
$t('bos sonucun sebebi ayriliyor', str_contains($page, "t('No article matches this filter.')"));
/* Suzgec degisince sayfa 1'e donmeli. */
$t('suzgec degisince sayfa sifirlanir',
   str_contains($page, "array_intersect_key(\$over, ['brand' => 1, 'cat' => 1, 'q' => 1])")
   || str_contains($src('dropship.php'), "array_intersect_key(\$over, ['brand' => 1, 'cat' => 1, 'q' => 1])"));

echo "\n--- $ok gecti, $fail kaldi ---\n";
exit($fail ? 1 : 0);
