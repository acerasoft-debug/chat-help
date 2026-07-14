<?php
/**
 * ChatHelp — apply-themen2 (CH_THEMEN2) — "Meine Anträge -> Meine Themen" tamamla.
 *  apply-clean1'in rename'i yanlis seciciydi (.pnl-topbar-title yok; baslik duz div).
 *  EKLEMELI script:
 *   (1) pnl-ant panel basligini (duz flex:1 div) + bos durumu "Meine Themen"e cevir
 *   (2) sol menudeki Meine Themen girisini gercek nav listesine (.sb-nav) tasi
 *  Dil bazli. Mevcut kod degismez.
 * KULLANIM: pull2.php?key=...&files=apply-themen2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-themen2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_THEMEN2')!==false) exit("Zaten ekli (CH_THEMEN2).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-themen2">
/* CH_THEMEN2 — panel basligi + bos durum "Meine Themen"; nav'i .sb-nav'a tasi */
try{(function(){
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  var TH={de:'Meine Themen',tr:'Konularım',en:'My Topics',fr:'Mes sujets',es:'Mis temas',it:'I miei temi',nl:'Mijn onderwerpen',pl:'Moje tematy',pt:'Os meus temas'};
  var EM={de:'Noch keine Themen',tr:'Henüz konu yok',en:'No topics yet',fr:'Aucun sujet',es:'Sin temas',it:'Nessun tema',nl:'Nog geen onderwerpen'};
  function L(o){ return o[lang()]||o.de; }
  var OLD=['Meine Anträge','Meine Themen','My Topics','Konularım','Mes sujets','Mis temas','I miei temi','Mijn onderwerpen','Moje tematy','Os meus temas'];
  var OLDE=['Noch keine Anträge','Noch keine Themen'];
  function renameIn(root){
    try{
      if(!root) return;
      var els=root.querySelectorAll('div,span,h1,h2,h3');
      for(var i=0;i<els.length;i++){
        var el=els[i]; if(el.children.length) continue; /* sadece yaprak metin */
        var t=(el.textContent||'').trim();
        if(OLD.indexOf(t)>-1){ if(t!==L(TH)) el.textContent=L(TH); }
        else if(OLDE.indexOf(t)>-1){ if(t!==L(EM)) el.textContent=L(EM); }
      }
    }catch(e){}
  }
  function placeNav(){
    try{
      var nav=document.getElementById('ch-themen-nav');
      var list=document.querySelector('.sb-nav');
      if(nav && list && nav.parentNode!==list){ list.appendChild(nav); }
    }catch(e){}
  }
  function tick(){ renameIn(document.getElementById('pnl-ant')); placeNav(); }
  var n=0; (function loop(){ tick(); if(n++<300) setTimeout(loop,700); })();
  /* pnl-ant acilinca da hemen tazele */
  try{ document.addEventListener('click',function(){ setTimeout(tick,120); },true); }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'t2').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-themen2-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_THEMEN2')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_THEMEN2 diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Panel basligi + bos durum artik 'Meine Themen'; menu girisi .sb-nav icinde.\n";
