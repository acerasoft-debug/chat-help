<?php
/**
 * ChatHelp — apply-applock (CH_APPLOCK) — CALISAN App-yazdirma halini KILITLER.
 *
 * Ne yapar:
 *  1) index.php'nin su anki halini kalici anlik goruntu olarak saklar:
 *       index.php.GOOD-appprint      (uzerine yazilmaz; ilk kilit korunur)
 *       index.php.GOOD-appprint-<ts> (her kilitte tarihli kopya)
 *  2) Ilgili yardimci dosyalari da yedekler: pv.php, pvsave.php
 *  3) Beklenen isaret envanterini ch-applock.json'a yazar.
 * Sonra:
 *  · Durum kontrolu : chk-applock.php
 *  · Geri yukleme   : apply-applock-restore.php
 *
 * ONEMLI: Bunu App'te 'Kostenlos selbst ausdrucken' CALISTIGINI dogruladiktan
 * SONRA calistir — kilit, o andaki hali "dogru hal" kabul eder.
 *
 * KULLANIM: /chat/pull2.php?key=...&files=apply-applock.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
echo "apply-applock BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$dir=__DIR__;
$file=$dir.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");

/* ── beklenen isaretler ── */
$marks=['CH_ISWV_GLOBAL'=>1,'CH_APPVIEW3'=>1,'CH_APPVIEW2C'=>4];
$inv=[];$eksik=[];
echo "=== Isaret envanteri ===\n";
foreach($marks as $m=>$min){
  $c=substr_count($src,$m); $inv[$m]=$c;
  $ok=($c>=$min);
  printf("  %-16s %2d  (en az %d)  %s\n",$m,$c,$min,$ok?'OK':'EKSIK');
  if(!$ok) $eksik[]=$m;
}
$files=['pv.php','pvsave.php'];
echo "\n=== Yardimci dosyalar ===\n";
foreach($files as $f){
  $ok=is_file($dir.'/'.$f);
  printf("  %-12s %s\n",$f,$ok?('OK ('.filesize($dir.'/'.$f).' B)'):'YOK');
  if(!$ok) $eksik[]=$f;
}

if($eksik){
  echo "\n✗ KILITLENMEDI — once eksikleri tamamla: ".implode(', ',$eksik)."\n";
  echo "  (apply-iswvfix.php ve apply-appview3.php calistirilmis olmali)\n";
  exit;
}

/* ── anlik goruntu ── */
$ts=date('Ymd-His');
$snapTs=$file.'.GOOD-appprint-'.$ts;
@file_put_contents($snapTs,$src);
echo "\n✓ tarihli anlik goruntu: ".basename($snapTs)." (".strlen($src)." B)\n";

$snap=$file.'.GOOD-appprint';
if(!is_file($snap)){
  @file_put_contents($snap,$src);
  echo "✓ ANA kilit olusturuldu: ".basename($snap)."\n";
}else{
  echo "· ANA kilit zaten var (".basename($snap).", ".filesize($snap)." B) — korundu.\n";
  echo "  (ana kilidi tazelemek istersen once onu sil, sonra bu betigi tekrar calistir)\n";
}
foreach($files as $f){ @copy($dir.'/'.$f, $dir.'/'.$f.'.GOOD'); }
echo "✓ yardimci dosyalar yedeklendi: pv.php.GOOD, pvsave.php.GOOD\n";

/* ── kilit kaydi ── */
$rec=['locked_at'=>date('c'),'size'=>strlen($src),'sha1'=>sha1($src),
      'snapshot'=>basename($snap),'snapshot_ts'=>basename($snapTs),
      'markers'=>$inv,'files'=>$files];
@file_put_contents($dir.'/ch-applock.json',json_encode($rec,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo "✓ kilit kaydi: ch-applock.json (sha1 ".substr($rec['sha1'],0,12)."…)\n";

echo "\n════════════════════════════════════════\n";
echo "KILITLENDI. Bundan sonra:\n";
echo "  · Durum kontrolu : pull2.php?...&files=chk-applock.php\n";
echo "  · Bozulursa geri : pull2.php?...&files=apply-applock-restore.php\n";
echo "════════════════════════════════════════\n";
