<?php
/* Katalogtan ürün sayfasına girip GERİ dönmek (vestra_back_link).
 *
 * Iki yonu de tutuyor: gelinen liste sayfasi (sorgu parametreleriyle birlikte)
 * korunmali, ama Referer BASKASININ yazdigi bir baslik -- yabanci alan adi,
 * 'javascript:' semasi ya da site icinden gelse bile liste OLMAYAN bir yol
 * (sepet, panel) "geri" hedefi olamaz. Yanlis yon de olculuyor: '/shop' oneki
 * duz alt dize olarak arandiginda '/shopping-cart' de eslesiyordu -- bu depoda
 * ayni ders blocklist'te mango -> Mangobay olarak kayitli.
 */
$root = dirname(__DIR__);
require_once $root.'/vestra/inc/i18n.php';
require_once $root.'/vestra/inc/products.php';

$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$_SERVER['HTTP_HOST'] = 'vestrasales.com';
$back = function (?string $ref) {
    if ($ref === null) unset($_SERVER['HTTP_REFERER']); else $_SERVER['HTTP_REFERER'] = $ref;
    return vestra_back_link();
};

echo "\n== 1. Gelinen liste sayfasına döner ==\n";
$r = $back('https://vestrasales.com/shop');
$t("/shop -> /shop",                          $r['url'] === '/shop');
$t("etiket 'Back to catalog'",                $r['label'] === t('Back to catalog'));
$r = $back('https://vestrasales.com/shop?section=footwear&brand=NBB');
$t('sorgu parametreleri KORUNUYOR',           $r['url'] === '/shop?section=footwear&brand=NBB');
$r = $back('https://vestrasales.com/b2b/sneakers');
$t('/b2b/sneakers korunuyor',                 $r['url'] === '/b2b/sneakers');
$t("liste /shop degilse etiket 'Back'",       $r['label'] === t('Back'));
$t("'Back' ile 'Back to catalog' ayri metin", t('Back') !== t('Back to catalog'));
$r = $back('https://vestrasales.com/wholesale/lacoste/polos');
$t('/wholesale/<marka>/<kategori> korunuyor', $r['url'] === '/wholesale/lacoste/polos');
$t('/price-list kabul',                       $back('https://vestrasales.com/price-list')['url'] === '/price-list');
$t('/journal kabul',                          $back('https://vestrasales.com/journal?slug=x')['url'] === '/journal?slug=x');
$t('/search kabul',                           $back('https://vestrasales.com/search?q=polo')['url'] === '/search?q=polo');
$t('/groups kabul',                           $back('https://vestrasales.com/groups')['url'] === '/groups');

echo "\n== 2. www ve port farkı aynı sitedir ==\n";
$t('www. oneki kabul',                        $back('https://www.vestrasales.com/shop')['url'] === '/shop');
$t('port farki kabul',                        $back('http://vestrasales.com:8085/shop')['url'] === '/shop');
$_SERVER['HTTP_HOST'] = 'www.vestrasales.com';
$t('sunucu www iken cipsiz host kabul',       $back('https://vestrasales.com/shop')['url'] === '/shop');
$_SERVER['HTTP_HOST'] = 'vestrasales.com';

echo "\n== 3. Referer'a güvenilmez ==\n";
$t('Referer YOKSA /shop',                     $back(null)['url'] === '/shop');
$t('bos Referer -> /shop',                    $back('')['url'] === '/shop');
$t('yabanci alan adi -> /shop',               $back('https://evil.example/shop')['url'] === '/shop');
$t('protokolsuz //evil -> /shop',             $back('//evil.example/shop')['url'] === '/shop');
$t("'javascript:' -> /shop",                  $back('javascript:alert(1)')['url'] === '/shop');
$t("'data:' -> /shop",                        $back('data:text/html,/shop')['url'] === '/shop');
$t('cop metin -> /shop',                      $back('not a url at all')['url'] === '/shop');

echo "\n== 4. NEREDEN GELDIYSE ORAYA (operator, 11 Eyl 2026) ==\n";
/* Once yalniz liste sayfalari kabul ediliyordu ve ana sayfa / showroom / panel /
   urun sayfasindan gelen herkes KATALOGUN BASINA dusuyordu -- operatorun
   sikayeti tam buydu. Liste, urune baglanti veren sayfalar OLCULEREK dolduruldu
   (`grep 'product?id='`). */
$_SERVER['REQUEST_URI'] = '/product?id=uw-1';
$t('ana sayfa / -> /',                        $back('https://vestrasales.com/')['url'] === '/');
$t('/showroom sorgusuyla korunuyor',          $back('https://vestrasales.com/showroom?uid=abc')['url'] === '/showroom?uid=abc');
$t('/dropship kabul',                         $back('https://vestrasales.com/dropship')['url'] === '/dropship');
$t('/requests kabul',                         $back('https://vestrasales.com/requests')['url'] === '/requests');
$t('/group?id= korunuyor',                    $back('https://vestrasales.com/group?id=g1')['url'] === '/group?id=g1');
$t('/buyer paneli kabul',                     $back('https://vestrasales.com/buyer?view=VES-1')['url'] === '/buyer?view=VES-1');
$t('/seller paneli kabul',                    $back('https://vestrasales.com/seller')['url'] === '/seller');
$t('BASKA urun sayfasi kabul',                $back('https://vestrasales.com/product?id=uw-2')['url'] === '/product?id=uw-2');
$t("bunlarda etiket 'Back'",                  $back('https://vestrasales.com/showroom')['label'] === t('Back'));

echo "\n== 4b. Kendine dönen 'geri' bozuk düğmedir ==\n";
$t('AYNI urun -> /shop',                      $back('https://vestrasales.com/product?id=uw-1')['url'] === '/shop');
$t('birebir ayni adres -> /shop',             $back('https://vestrasales.com/product?id=uw-1')['url'] === '/shop');
$_SERVER['REQUEST_URI'] = '/shop?section=footwear';
$t('ayni liste adresi -> /shop (fallback)',   $back('https://vestrasales.com/shop?section=footwear')['url'] === '/shop');
$_SERVER['REQUEST_URI'] = '/product?id=uw-1';

echo "\n== 4c. GET ile İŞ YAPAN uçlar hâlâ DIŞARIDA ==\n";
/* Olculdu: bu dort uc GET ile is yapiyor -- `login?signout` oturumu kapatiyor,
   digerleri jeton harciyor. "Ayni alan adindaki her yol" kabul edilseydi "geri"
   dugmesi bunlari YENIDEN CAGIRIRDI. Izin listesinin var olma sebebi bu. */
$t('/login?signout=1 -> /shop',               $back('https://vestrasales.com/login?signout=1')['url'] === '/shop');
$t('/offer-accept?t= -> /shop',               $back('https://vestrasales.com/offer-accept?t=abc')['url'] === '/shop');
$t('/verify?t= -> /shop',                     $back('https://vestrasales.com/verify?t=abc')['url'] === '/shop');
$t('/lead-unsubscribe -> /shop',              $back('https://vestrasales.com/lead-unsubscribe?t=abc')['url'] === '/shop');
$t('/admin -> /shop',                         $back('https://vestrasales.com/admin?dl=orders')['url'] === '/shop');

echo "\n== 4d. Ters yön: benzeyen ad aynı ad değildir ==\n";
$t("'/shopping-cart' /shop SANILMIYOR",       $back('https://vestrasales.com/shopping-cart')['url'] === '/shop');
$t("'/b2bx' /b2b SANILMIYOR",                 $back('https://vestrasales.com/b2bx/y')['url'] === '/shop');
$t("'/groupshot' /group SANILMIYOR",          $back('https://vestrasales.com/groupshot')['url'] === '/shop');
$t("'/products-old' /product SANILMIYOR",     $back('https://vestrasales.com/products-old')['url'] === '/shop');
$t("'/sellerx' /seller SANILMIYOR",           $back('https://vestrasales.com/sellerx')['url'] === '/shop');
$t('/cart -> /shop (urune baglanmiyor)',      $back('https://vestrasales.com/cart')['url'] === '/shop');
/* Ters bolu bazi tarayicilarda '/' diye normallesir: '/\evil' -> '//evil' yani
   sema-goreli bir adres, site disina cikan bir "geri". */
$t('ters bolu /\\evil -> /shop',              $back('https://vestrasales.com/\\evil.example')['url'] === '/shop');

echo "\n== 5. Ürün sayfası gerçekten çağırıyor ==\n";
$src = (string)file_get_contents($root.'/vestra/product.php');
$t('product.php vestra_back_link() cagiriyor', str_contains($src, 'vestra_back_link('));
$t('bagi ciziyor (crumb-back)',                str_contains($src, 'crumb-back'));
$t('url htmlspecialchars ile basiliyor',       str_contains($src, 'htmlspecialchars($__back['));
$css = (string)file_get_contents($root.'/vestra/inc/style.css');
$t('.crumb-back stili var',                    str_contains($css, '.crumb-back'));

echo "\n== 6. Gerçek 'bir sayfa geri': history.back() ==\n";
/* Sunucudan gelen adres dogru yere goturuyor ama adresi YENIDEN CEKIYOR --
   200 urun asagida tiklayan ziyaretci listenin BASINA doner. Kaydirma konumunu
   yalniz tarayicinin kendi gecmisi koruyor. */
/* Kaliba DIKKAT: duz 'history.back()' aramak YETMEZ -- o dizge bloğun ustundeki
   ACIKLAMA satirinda da geciyor, yani blok tamamen silinse bile iddia yesil
   kalirdi. Falsifikasyon kosusu bunu boyle yakaladi; iddia artik kodun kendisine
   bagli. "Hic dusemeyen bir iddia, iddia degildir." */
$t('history.back() GERCEKTEN cagriliyor',      str_contains($src, 'e.preventDefault(); history.back();'));
$t('referrer ayni kokende mi diye bakiyor',    str_contains($src, 'new URL(r).origin!==location.origin'));
$t('gecmis yoksa href birakiliyor',            str_contains($src, 'history.length<2'));
$t('kendine donen href de atlaniyor',          str_contains($src, "r===location.href"));
/* href KALMALI: JS'siz tarayici, orta tik ve "yeni sekmede ac" bozulmasin. */
$t('href hala basiliyor',                      str_contains($src, 'href="<?= htmlspecialchars($__back[\'url\']) ?>"'));
$t('yeni sekme kisayollari korunuyor',         str_contains($src, 'e.metaKey||e.ctrlKey||e.shiftKey||e.altKey'));
$t('sadece sol tik ele geciriliyor',           str_contains($src, 'e.button!==0'));

echo "\n";
echo ($fail ? "BASARISIZ" : "GECTI").": {$ok} iddia gecti, {$fail} dustu\n";
exit($fail ? 1 : 0);
