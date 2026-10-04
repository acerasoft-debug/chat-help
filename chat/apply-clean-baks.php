<?php
/**
 * ChatHelp — apply-clean-baks (CH_CLEAN_BAKS) — index.php yedek temizligi.
 *  Bugunku denetimde 52 adet index.php.bak-* (~100 MB) bulundu. Bu betik
 *  EN YENI 8 yedegi SAKLAR, daha eskilerini siler. index.php'ye ve baska
 *  hicbir dosyaya DOKUNMAZ. Ayrica auth.php.bak-* ve admin*.bak-* icin de
 *  en yeni 4'u saklar. Silinenler tek tek raporlanir.
 * KULLANIM: pull2.php?key=...&files=apply-clean-baks.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-clean-baks BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$D=__DIR__;

function cleanSet($pattern,$keep,$label){
  global $D;
  $files=glob("$D/$pattern");
  if(!$files){ echo "  ($label: yedek yok)\n"; return; }
  /* dosya adindaki zaman damgasina gore sirala (yeniden eskiye) */
  rsort($files);
  $total=count($files);
  $del=array_slice($files,$keep);
  $freed=0;$n=0;
  foreach($del as $f){
    $sz=filesize($f);
    if(@unlink($f)){ $freed+=$sz; $n++; echo "  🗑 ".basename($f)." (".number_format($sz)." B)\n"; }
    else echo "  ✗ silinemedi: ".basename($f)."\n";
  }
  echo "  ✓ $label: $total yedekten en yeni ".min($keep,$total)." saklandi, $n silindi, ".number_format($freed)." B bosaldi\n\n";
}

echo "[1] index.php yedekleri (en yeni 8 kalir):\n";
cleanSet('index.php.bak-*',8,'index.php');

echo "[2] auth.php yedekleri (en yeni 4 kalir):\n";
cleanSet('auth.php.bak-*',4,'auth.php');

echo "[3] admin.php / admin-users.php yedekleri (en yeni 4'er kalir):\n";
cleanSet('admin.php.bak-*',4,'admin.php');
cleanSet('admin-users.php.bak-*',4,'admin-users.php');

echo "[4] Dogrulama: index.php saglam mi?\n";
$lo=[];$rc=0; exec('php -l '.escapeshellarg("$D/index.php").' 2>&1',$lo,$rc);
echo "  ".($rc===0?"✓ index.php lint TEMIZ (dokunulmadi)":"✗ ".implode(' ',$lo))."\n";

echo "\n✓ Temizlik bitti. Kalan yedekler acil geri-donus icin yeterli.\n";
