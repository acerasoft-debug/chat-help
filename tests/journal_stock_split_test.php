<?php
/* Stok raporlari (markali "New in stock") yazilardan AYRI duruyor mu.
   journal.php kum havuzunda GERCEKTEN cizdiriliyor. */
$root = dirname(__DIR__);
$ok=0;$bad=0;$t=function($n,$c)use(&$ok,&$bad){ $c?($ok++.print("  ok   $n\n")):($bad++.print("  HATA $n\n")); };
$src = file_get_contents("$root/vestra/inc/journal.php");
$auto = file_get_contents("$root/vestra/inc/journal_auto.php");
preg_match("/VESTRA_JOURNAL_AUTO_FLAG = '([^']+)'/", $auto, $a);
preg_match("/VESTRA_JOURNAL_STOCK_SOURCE = '([^']+)'/", $src, $b);
$t('bayrak iki dosyada ayni', ($a[1] ?? 'x') === ($b[1] ?? 'y'));
$S = sys_get_temp_dir().'/jst'.getmypid(); @mkdir($S);
exec('cd '.escapeshellarg($root).' && git ls-files vestra | tar -cf - -T - | tar -xf - -C '.escapeshellarg($S));
@mkdir("$S/vestra/data");
$arts=[];
for($i=0;$i<4;$i++) $arts[]=['id'=>"e$i",'slug'=>"edit-$i",'title'=>"EDITORIAL-$i",'category'=>'Brand News','excerpt'=>'x','body'=>'b','published'=>1,'created'=>"2026-10-0".($i+1)."T10:00:00+00:00"];
for($i=0;$i<3;$i++) $arts[]=['id'=>"s$i",'slug'=>"stock-$i",'title'=>"STOCKREPORT-$i",'category'=>'Brand News','excerpt'=>'y','body'=>'b','published'=>1,'created'=>"2026-10-0".($i+5)."T10:00:00+00:00",'source'=>$a[1]];
file_put_contents("$S/vestra/data/journal.json", json_encode($arts));
$render = function($q) use ($S) {
  $code = '$_GET='.var_export($q,true).'; $_SERVER["REQUEST_URI"]="/journal"; $_SERVER["HTTP_HOST"]="t"; chdir('.var_export("$S/vestra",true).'); include "journal.php";';
  return (string)shell_exec('php -d display_errors=1 -r '.escapeshellarg($code).' 2>&1');
};
$h = $render([]);
$p = strpos($h, 'class="jr-stock"');
$grid = substr($h, 0, (int)$p); $band = substr($h, (int)$p);
$t('Tumu: bant var', $p !== false);
$t('Tumu: yazi alaninda rapor YOK', !str_contains($grid, 'STOCKREPORT'));
$t('Tumu: one cikan yazi bir YAZI', preg_match('/<h2>EDITORIAL-3/', $grid) === 1);
$t('Tumu: bantta 3 rapor', substr_count($band, 'STOCKREPORT') === 3);
$t('Tumu: bantta yazi yok', !str_contains($band, 'EDITORIAL'));
$h = $render(['cat'=>'stock']);
$t('?cat=stock: yalniz rapor', str_contains($h,'STOCKREPORT-2') && !str_contains($h,'EDITORIAL'));
$h = $render(['cat'=>'Brand News']);
$t('Brand News sekmesi: rapor yok', str_contains($h,'EDITORIAL-0') && !str_contains($h,'STOCKREPORT'));
$h = $render(['slug'=>'stock-0']);
$more = substr($h, (int)strpos($h,'jr-more-h'));
$t('rapor altinda yalniz rapor', str_contains($more,'STOCKREPORT-1') && !str_contains($more,'EDITORIAL'));
$h = $render(['slug'=>'edit-0']);
$more = substr($h, (int)strpos($h,'jr-more-h'));
$t('yazi altinda yalniz yazi', str_contains($more,'EDITORIAL-1') && !str_contains($more,'STOCKREPORT'));
$t('PHP uyarisi yok', !preg_match('/(Warning|Fatal|Notice|Deprecated)( error)?:/', $render([]).$render(['cat'=>'stock'])));
exec('rm -rf '.escapeshellarg($S));
echo "\n$ok ok, $bad hata\n"; exit($bad?1:0);
