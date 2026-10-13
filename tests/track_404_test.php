<?php
/* Ziyaretci sayacinda 404 (13 Eki 2026, operator: "/404.php neden cikiyor aktif
   ziyaretcilere baktigim sik sik karsima geliyor"). Iki yon:
   - tarayici/OS yoklamalari, statik dosyalar ve saldiri taramalari ZIYARET sayilmaz
   - gercek sayfa adresleri (uzantisiz, .php) sayilir ve panelde ISTENEN yol gorunur */
$src = file_get_contents(__DIR__.'/../vestra/inc/security.php');
if (!preg_match('/^function vestra_track_is_probe\(.*?^}/ms', $src, $m)) { echo "HATA: fonksiyon yok\n"; exit(1); }
eval($m[0]);
$ok=0;$bad=0;$t=function($n,$c)use(&$ok,&$bad){ if($c){$ok++;echo "  ok   $n\n";}else{$bad++;echo "  HATA $n\n";} };
foreach (['/.well-known/passkey-endpoints','/.well-known/traffic-advice','/.env','/apple-touch-icon-precomposed.png',
          '/apple-touch-icon-120x120.png','/favicon.ico','/wp-admin/install.php','/ads.txt','/security.txt',
          '/assets/img.php/x.jpg','/ipfs/bafkrei','/uploads/x/y.jpg','/robots.txt.bak'] as $p)
  $t("yoklama, sayilmaz: $p", vestra_track_is_probe($p));
foreach (['/null','/product','/wholesale/gucci','/wholesale/levi-s/jeans','/b2b/women-s-jeans','/group','/old-page.php','/404','/shop.html'] as $p)
  $t("sayfa, sayilir: $p", !vestra_track_is_probe($p));
$t('404 icin istenen yol yaziliyor', str_contains($src, "\$path = '404 '.mb_substr(\$req, 0, 80);"));
$t('yoklama sayilmadan cikiliyor', str_contains($src, 'if (vestra_track_is_probe($req)) return;'));
$t('kosul yalniz /404.php', str_contains($src, "if (\$path === '/404.php') {"));
$ht = file_get_contents(__DIR__.'/../vestra/.htaccess');
$t('apple-touch-icon varyantlari tek dosyaya', preg_match('/RewriteRule \^apple-touch-icon\(-\[0-9\]\+x\[0-9\]\+\)\?\(-precomposed\)\?\\\\\.png\$ apple-touch-icon\.png \[L\]/', $ht) === 1);
$t('o kural 404 kuralindan ONCE', strpos($ht, 'apple-touch-icon(-') < strpos($ht, 'RewriteRule . 404.php'));
printf("\n%d gecti, %d KALDI\n", $ok, $bad); exit($bad ? 1 : 0);
