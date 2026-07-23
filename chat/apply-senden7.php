<?php
/**
 * ChatHelp — apply-senden7 (CH_SENDEN7) — sari Senden'i DOM'dan KESIN siler
 *  (CSS gizleme yetmediyse): periyodik JS supurge, .ch-senden-btn + .ch-send-row
 *  icindeki (yesil #ch-sd2 DISINDAKI) Senden dugmelerini kaldirir. Yesil korunur.
 * KULLANIM: pull2.php?key=...&files=apply-senden7.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-senden7 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_SENDEN7')!==false) exit("Zaten ekli (CH_SENDEN7).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-senden7-js">
/* CH_SENDEN7 — sari/eski Senden dugmelerini DOM'dan kaldir (yesil #ch-sd2 korunur) */
try{(function(){
  function sweep(){
    try{
      /* enjektorun eski butonlari */
      var a=document.querySelectorAll('.ch-senden-btn');
      for(var i=0;i<a.length;i++){ try{ a[i].parentNode&&a[i].parentNode.removeChild(a[i]); }catch(e){} }
      /* ch-send-row sarmalayicilarini duzlestir */
      var rows=document.querySelectorAll('.ch-send-row');
      for(var r=0;r<rows.length;r++){ try{ var row=rows[r]; while(row.firstChild){ row.parentNode.insertBefore(row.firstChild,row); } row.parentNode.removeChild(row); }catch(e){} }
      /* guvenlik: 'Senden' + 'Gratis-Fax' iceren, YESIL olmayan, #ch-sd2 olmayan butonlari kaldir */
      var btns=document.querySelectorAll('button');
      for(var j=0;j<btns.length;j++){
        var b=btns[j]; if(b.id==='ch-sd2') continue;
        var t=(b.textContent||''); if(!/Senden/.test(t)||!/Gratis-Fax/i.test(t)) continue;
        var bg=''; try{ bg=(b.style.background||'')+ (getComputedStyle(b).backgroundImage||''); }catch(e){}
        /* yesil (#3fe08a/#37d17d/#1fa25c) degilse -> sari, kaldir */
        if(!/3fe08a|37d17d|1fa25c|47,\s*209|47,\s*191/i.test(bg)){ try{ b.parentNode&&b.parentNode.removeChild(b); }catch(e){} }
      }
    }catch(e){}
  }
  sweep(); try{ setInterval(sweep,500); }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;

$pos=strripos($src,'</body>');
if($pos===false) exit("</body> yok\n");
$src=substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp=tempnam(sys_get_temp_dir(),'s7').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-senden7-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_SENDEN7')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_SENDEN7 diskte (".strlen($chk)." B)\n";
echo "  Sari Senden DOM'dan kaldirilir; tek YESIL Senden kalir.\n";
echo "  NOT: App'te hala goruyorsan -> Ayarlar>ChatHelp>Depolama>Verileri Temizle (onbellek).\n";
