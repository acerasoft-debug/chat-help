<?php
/*
 * Search Console, 12 Eki 2026: "Robots.txt tarafindan engelleniyor olsa da dizine eklendi".
 * Hesap/sepet/giris sayfalari ve uretilen indirmeler robots.txt'te ENGELLI DEGIL ve
 * kendileri X-Robots-Tag: noindex soyluyor -- Google okuyup dizinden cikarabilsin.
 * Engel geri gelirse ya da bir sayfanin basligi duserse bu test kirmizi olur.
 */
$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; } };
$root = dirname(__DIR__).'/vestra';
$robots = (string)file_get_contents($root.'/robots.txt');
$pages = ['login','register','forgot','reset','buyer','seller','seller-add','cart','linesheet','catalog','catalog-pdf','catalog-csv','admin'];
foreach ($pages as $p) {
    $t("robots.txt /$p ENGELLEMIYOR", !preg_match('#^\s*Disallow:\s*/'.preg_quote($p, '#').'\s*$#mi', $robots));
    $lines = file($root."/$p.php");
    $t("$p.php ILK satirdan sonra X-Robots-Tag noindex", isset($lines[1]) && preg_match("#^header\('X-Robots-Tag: noindex, (follow|nofollow)'\);#", $lines[1]) === 1);
}
$t('robots.txt: gizli klasorler hala kapali', str_contains($robots, 'Disallow: /data/') && str_contains($robots, 'Disallow: /uploads/'));
$t('robots.txt: sitemap satiri duruyor', str_contains($robots, 'Sitemap: https://vestrasales.com/sitemap.xml'));
$t('seller-invite (acik sayfa) noindex DEGIL', !str_contains((string)file_get_contents($root.'/seller-invite.php'), "X-Robots-Tag: noindex"));
$h = (string)file_get_contents($root.'/inc/head.php');
$t('head.php meta, basliktaki noindex ile ayni', str_contains($h, "stripos(\$__h, 'X-Robots-Tag:') === 0 && stripos(\$__h, 'noindex') !== false) { \$NOINDEX = true;"));
echo "\n$ok ok, $fail hata\n";
exit($fail ? 1 : 0);
