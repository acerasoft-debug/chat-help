<?php
/**
 * ChatHelp — apply-antfinal (CH_ANTFINAL) — "Meine Anträge" nerede kaldiysa TEMIZLE.
 *  EKLEMELI (hepsi birden, MutationObserver + 500ms):
 *   (1) ust bardaki Anträge ikonunu kaldir (onclick attr gP('ant'))
 *   (2) tum yaprak "Meine Anträge" metnini "Meine Themen"e cevir (dil bazli)
 *   (3) title="Meine Anträge" -> "Meine Themen"
 *  Sol menudeki Meine Themen (JS-onclick, attr yok) DOKUNULMAZ.
 * KULLANIM: pull2.php?key=...&files=apply-antfinal.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-antfinal BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_ANTFINAL')!==false) exit("Zaten ekli (CH_ANTFINAL).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-antfinal">
/* CH_ANTFINAL — Meine Anträge kalintilarini temizle (ikon + metin + title) */
try{(function(){
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  var TH={de:'Meine Themen',tr:'Konularım',en:'My Topics',fr:'Mes sujets',es:'Mis temas',it:'I miei temi',nl:'Mijn onderwerpen',pl:'Moje tematy',pt:'Os meus temas'};
  function L(){ return TH[lang()]||TH.de; }
  function work(){
    try{
      /* 1) onclick attr gP('ant') olan ogeleri kaldir (ust bar ikonu) */
      document.querySelectorAll('[onclick]').forEach(function(b){
        var oc=b.getAttribute('onclick')||'';
        if(/gP\((['"])ant\1\)/.test(oc)){ try{ b.remove(); }catch(e){ try{ b.style.display='none'; }catch(_){}} }
      });
      /* 2) yaprak metin "Meine Anträge" -> localized */
      var els=document.body.querySelectorAll('div,span,button,a,li,h1,h2,h3,strong,b,p');
      for(var i=0;i<els.length;i++){ var el=els[i]; if(el.children.length) continue; var t=(el.textContent||'').trim(); if(t==='Meine Anträge'){ el.textContent=L(); } }
      /* 3) title attr */
      document.querySelectorAll('[title="Meine Anträge"]').forEach(function(e){ try{ e.setAttribute('title',L()); }catch(_){}});
    }catch(e){}
  }
  var n=0; (function loop(){ work(); if(n++<180) setTimeout(loop,500); })();
  try{ if(window.MutationObserver){ new MutationObserver(work).observe(document.body,{childList:true,subtree:true,characterData:true}); } }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'af').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-antfinal-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_ANTFINAL')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_ANTFINAL diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Meine Anträge (ikon+metin+title) her yerde temizlendi -> Meine Themen.\n";
