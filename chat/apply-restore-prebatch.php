<?php
/**
 * ChatHelp — apply-restore-prebatch (ACIL GERI ALMA) — "acilinca tum arayuz
 *  gizleniyor". index.php'yi bu oturumdaki ILK sozlesme degisikliginden ONCEKI
 *  yedege dondurur (.bak-deminijob-* = calistigi bilinen durum). Boylece servis
 *  aninda geri gelir; sonra degisiklikleri tek tek geri ekleyip suclu bulunur.
 *  Mevcut (bozuk) index once .bak-beforerevert-* olarak saklanir (kayip yok).
 * KULLANIM: pull2.php?key=...&files=apply-restore-prebatch.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-restore-prebatch BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$cur = @file_get_contents($file);
if ($cur===false) exit("index.php okunamadi\n");

/* pre-batch yedegini bul: oncelik sirasi */
$prefs = ['index.php.bak-deminijob-*','index.php.bak-vorl300-*','index.php.bak-esfr-*','index.php.bak-itgb-*','index.php.bak-atch2-*','index.php.bak-vizeux-*'];
$pick=null;$pickPat=null;
foreach($prefs as $pat){
  $g=glob(__DIR__.'/'.$pat);
  if($g){ sort($g); $pick=end($g); $pickPat=$pat; break; }
}
if(!$pick){
  echo "✗ Hicbir .bak-* yedegi bulunamadi. Mevcut yedekler:\n";
  foreach(glob(__DIR__.'/index.php.bak-*') as $b) echo "   ".basename($b)." (".filesize($b)." bayt)\n";
  exit("\nGeri alinamadi — yedek yok.\n");
}
echo "  Secilen yedek: ".basename($pick)." (".filesize($pick)." bayt)\n";
echo "  (oncelik: $pickPat)\n";

$good = @file_get_contents($pick);
if($good===false || strlen($good)<500000) exit("✗ Yedek okunamadi/kucuk — iptal.\n");

/* saglik kontrolu: html iskeleti + core script var, batch markerlari YOK (pre-batch) */
if(strpos($good,'</body>')===false) exit("✗ Yedekte </body> yok — iptal.\n");
if(strpos($good,'CH_VST_X')===false) exit("✗ Yedekte CH_VST_X yok (beklenmez) — iptal.\n");
$hasBatch = (strpos($good,'CH_DE_MINIJOB')!==false);
echo "  Yedek durumu: CH_DE_MINIJOB ".($hasBatch?"VAR (dikkat: pre-batch degil!)":"yok (pre-batch ✓)")."\n";

/* lint */
$tmp = tempnam(sys_get_temp_dir(),'rb').'.php';
file_put_contents($tmp,$good);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\n✗ Yedek LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
echo "  ✓ Yedek php -l temiz\n";

/* mevcut (bozuk) index'i sakla */
$bk=@file_put_contents($file.'.bak-beforerevert-'.date('Ymd-His'), $cur);
echo "  ✓ Mevcut index .bak-beforerevert-* olarak saklandi (kayip yok)\n";

/* geri yukle */
$w=@file_put_contents($file,$good);
if ($w===false || $w<strlen($good)) exit("\n✗ YAZMA HATASI.\n");
$chk=(string)@file_get_contents($file);
echo "  ✓ index.php geri yuklendi (".strlen($chk)." bayt)\n";
echo "\n✓ GERI ALINDI. App'i tekrar ac — arayuz donmeli.\n";
echo "  DONDUYSE: sebep bu oturumdaki degisikliklerde -> tek tek geri ekleyip sucluyu buluruz.\n";
echo "  DONMEDIYSE: sebep baska (zen/mod/CSS/onbellek) -> oraya bakariz. Cikti tamamini gonder.\n";
