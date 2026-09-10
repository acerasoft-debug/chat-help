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

echo "\n== 4. Site içinde de her yol 'liste' değildir ==\n";
$t('/cart -> /shop',                          $back('https://vestrasales.com/cart')['url'] === '/shop');
$t('/buyer paneli -> /shop',                  $back('https://vestrasales.com/buyer')['url'] === '/shop');
$t('/product urun sayfasi -> /shop',          $back('https://vestrasales.com/product?id=uw-1')['url'] === '/shop');
$t("'/shopping-cart' /shop SANILMIYOR",       $back('https://vestrasales.com/shopping-cart')['url'] === '/shop');
$t("'/b2bx' /b2b SANILMIYOR",                 $back('https://vestrasales.com/b2bx/y')['url'] === '/shop');
$t('kok / -> /shop',                          $back('https://vestrasales.com/')['url'] === '/shop');

echo "\n== 5. Ürün sayfası gerçekten çağırıyor ==\n";
$src = (string)file_get_contents($root.'/vestra/product.php');
$t('product.php vestra_back_link() cagiriyor', str_contains($src, 'vestra_back_link('));
$t('bagi ciziyor (crumb-back)',                str_contains($src, 'crumb-back'));
$t('url htmlspecialchars ile basiliyor',       str_contains($src, 'htmlspecialchars($__back['));
$css = (string)file_get_contents($root.'/vestra/inc/style.css');
$t('.crumb-back stili var',                    str_contains($css, '.crumb-back'));

echo "\n";
echo ($fail ? "BASARISIZ" : "GECTI").": {$ok} iddia gecti, {$fail} dustu\n";
exit($fail ? 1 : 0);
