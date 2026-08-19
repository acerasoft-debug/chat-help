<?php
/**
 * ChatHelp — apply-themen4 (CH_THEMEN4) — ust menudeki "Meine Anträge" ikonunu SIL.
 *  Kullanici: Meine Themen kalsin, Meine Anträge tamamen silinsin (ust bardan da).
 *  EKLEMELI script: ust bardaki .tb-btn (onclick gP('ant') veya title Anträge/Themen)
 *  DOM'dan kaldirilir. Sol menudeki 'Meine Themen' (.ch-themen-nav) ve panel
 *  DOKUNULMAZ.
 * KULLANIM: pull2.php?key=...&files=apply-themen4.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-themen4 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_THEMEN4')!==false) exit("Zaten ekli (CH_THEMEN4).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-themen4">
/* CH_THEMEN4 — ust bardaki Meine Anträge/Themen ikonunu tamamen kaldir */
try{(function(){
  function kill(){
    try{
      document.querySelectorAll('.tb-btn').forEach(function(b){
        var oc=(b.getAttribute('onclick')||'');
        var ti=(b.getAttribute('title')||'');
        if(/gP\((['"])ant\1\)/.test(oc) || /Anträge|Meine Themen|My Topics|Konular/i.test(ti)){
          try{ b.remove(); }catch(e){ try{ b.style.display='none'; }catch(_){}}
        }
      });
      /* ek guvenlik: ust bardaki title=Meine Anträge olan tb-btn */
      document.querySelectorAll('[title="Meine Anträge"],[title="Meine Themen"]').forEach(function(e){
        if(e.classList && e.classList.contains('tb-btn')){ try{ e.remove(); }catch(x){ try{ e.style.display='none'; }catch(_){}} }
      });
    }catch(e){}
  }
  var n=0; (function loop(){ kill(); if(n++<120) setTimeout(loop,600); })();
  try{ if(window.MutationObserver){ new MutationObserver(kill).observe(document.body,{childList:true,subtree:true}); } }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'t4').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-themen4-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_THEMEN4')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_THEMEN4 diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Ust menudeki Meine Anträge ikonu kaldirildi; Meine Themen (sol menu) kalir.\n";
