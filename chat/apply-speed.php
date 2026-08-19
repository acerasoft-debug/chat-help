<?php
/**
 * ChatHelp — apply-speed (CH_SPEED) — kartlar/yazilar ~5sn yerine ~1.2sn gelsin.
 *  CH_STAB_FINAL grid'leri .ch-settling ile gizleyip mutasyon durunca 2000ms
 *  (+10000ms sert tavan) sonra reveal ediyor; 60+ enjektor yuzunden ~5sn.
 *  (1) reveal gecikmesi 2000 -> 500
 *  (2) sert tavan 10000 -> 1300 (2 yer)
 *  (3) EKLEMELI garantili force-reveal @1100/2200ms (mutasyon surse bile goster)
 * KULLANIM: pull2.php?key=...&files=apply-speed.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-speed BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_SPEED')!==false) exit("Zaten ekli (CH_SPEED).\n");

$done=0;

/* (1) reveal gecikmesi */
$o1='t=setTimeout(reveal, 2000)'; $n1='t=setTimeout(reveal, 500)';
$c=substr_count($src,$o1);
if($c===1){ $src=str_replace($o1,$n1,$src); echo "  ✓ (1) reveal gecikmesi 2000 -> 500ms\n"; $done++; }
else echo "  ⚠ (1) anchor $c kez — atlandi\n";

/* (2) sert tavan */
$o2='if(!shown) reveal(); }, 10000)'; $n2='if(!shown) reveal(); }, 1300)';
$c=substr_count($src,$o2);
if($c>=1){ $src=str_replace($o2,$n2,$src); echo "  ✓ (2) sert tavan 10000 -> 1300ms ($c yer)\n"; $done++; }
else echo "  ⚠ (2) anchor $c kez — atlandi\n";

/* (3) additive garantili force-reveal */
$block = <<<'HTMLBLOCK'
<script id="ch-speed">
/* CH_SPEED — icerigi en gec ~1.1sn'de goster (stabilizer settling'i cok gec reveal ediyordu) */
try{(function(){
  function reveal(){
    try{
      document.querySelectorAll('.ch-settling').forEach(function(el){ el.classList.remove('ch-settling'); try{ el.style.minHeight=''; }catch(e){} });
      document.querySelectorAll('.cat-grid,#wlc,.wcat-grid,.cgrid').forEach(function(el){ try{ if(el.style.opacity==='0') el.style.opacity=''; }catch(e){} });
    }catch(e){}
  }
  setTimeout(reveal,1100); setTimeout(reveal,2200);
  if(document.readyState==='complete') setTimeout(reveal,300);
})();}catch(e){}
</script>
HTMLBLOCK;
$pos = strripos($src,'</body>');
if ($pos!==false){ $src = substr($src,0,$pos).$block."\n".substr($src,$pos); echo "  ✓ (3) garantili force-reveal @1100/2200ms eklendi\n"; $done++; }
else echo "  ⚠ (3) </body> yok — atlandi\n";

if($done===0) exit("\nHATA: hicbir adim uygulanmadi — index DEGISTIRILMEDI.\n");

$tmp = tempnam(sys_get_temp_dir(),'sp').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-speed-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_SPEED')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_SPEED diskte ($done adim, ".strlen($chk)." bayt)\n";
echo "\n✓ Icerik artik ~1.1-1.3sn'de gorunur (onceden ~5sn).\n";
