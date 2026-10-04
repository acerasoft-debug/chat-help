<?php
/**
 * ChatHelp — apply-de-vertrag (CH_DE_VERTRAG) — DE duz-kart sozlesmelerini module tasi.
 *  DE 'vertraege' kategorisi (V objesi ~20 sozlesme: Kaufvertrag, Mietvertrag,
 *  Arbeitsvertrag, Darlehen, NDA, Werkvertrag…) tiklaninca duz-kart liste yerine
 *  Vertrag Studio modulu acilir. DE modulu bu sozlesmelerin zengin/opsiyonlu
 *  halini zaten iceriyor. EKLEMELI (showCat/showCatDocs wrap). Gomulu JS node ✓.
 *  TR icin ayni is CH_TR_VERTRAG2'de yapildi.
 * KULLANIM: pull2.php?key=...&files=apply-de-vertrag.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-de-vertrag BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_DE_VERTRAG')!==false) exit("Zaten ekli (CH_DE_VERTRAG).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-de-vertrag">
/* CH_DE_VERTRAG — DE 'vertraege' kategorisi -> Vertrag Studio modulu */
try{(function(){
  /* module iceriginin hazir oldugu sozlesme kategorileri (duz-kart yerine modul) */
  var CONTRACT_CATS={ vertraege:1 };
  function openVst(){ try{ if(typeof gP==="function"){ gP("vst"); return true; } }catch(e){} return false; }
  function catKey(arg){ try{ return (typeof arg==="string")?arg:((arg&&arg.cat)||""); }catch(e){ return ""; } }
  function wrapNav(){
    ["showCat","showCatDocs"].forEach(function(fn){
      try{
        if(typeof window[fn]!=="function" || window[fn].__chdev) return;
        var _o=window[fn];
        var w=function(arg){
          try{ if(CONTRACT_CATS[catKey(arg)] && openVst()) return; }catch(e){}
          return _o.apply(this,arguments);
        };
        w.__chdev=1; window[fn]=w;
      }catch(e){}
    });
  }
  var n=0;(function loop(){ wrapNav(); if(n++<80) setTimeout(loop,600); })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'dv').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-devertrag-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_DE_VERTRAG')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_DE_VERTRAG diskte (".strlen($chk)." bayt)\n";
echo "\n✓ DE 'vertraege' kategorisi artik Vertrag Studio modulunu aciyor (duz-kart yok).\n";
