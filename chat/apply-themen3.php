<?php
/**
 * ChatHelp — apply-themen3 (CH_THEMEN3) — kalan "Meine Anträge"leri TUM sayfada
 *  "Meine Themen"e cevir (baslik, menu, bos durum, chip vb. nerede kaldiysa).
 *  EKLEMELI script: document.body genelinde yaprak metin taramasi; dil bazli.
 *  ("Behalten -> Meine Themen -> ⚖️ Fall -> Meine Fälle" akisi zaten kurulu.)
 * KULLANIM: pull2.php?key=...&files=apply-themen3.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-themen3 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_THEMEN3')!==false) exit("Zaten ekli (CH_THEMEN3).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-themen3">
/* CH_THEMEN3 — tum sayfada "Meine Anträge" -> "Meine Themen" (kalan etiketler) */
try{(function(){
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  var TH={de:'Meine Themen',tr:'Konularım',en:'My Topics',fr:'Mes sujets',es:'Mis temas',it:'I miei temi',nl:'Mijn onderwerpen',pl:'Moje tematy',pt:'Os meus temas'};
  var EM={de:'Noch keine Themen',tr:'Henüz konu yok',en:'No topics yet',fr:'Aucun sujet',es:'Sin temas',it:'Nessun tema',nl:'Nog geen onderwerpen'};
  function L(o){ return o[lang()]||o.de; }
  var OLD=['Meine Anträge'];
  var CUR=['Meine Themen','My Topics','Konularım','Mes sujets','Mis temas','I miei temi','Mijn onderwerpen','Moje tematy','Os meus temas'];
  var OLDE=['Noch keine Anträge'];
  function walk(){
    try{
      var els=document.body.querySelectorAll('div,span,h1,h2,h3,button,a,li');
      for(var i=0;i<els.length;i++){
        var el=els[i]; if(el.children.length) continue;
        var t=(el.textContent||'').trim();
        if(OLD.indexOf(t)>-1 || (CUR.indexOf(t)>-1 && t!==L(TH))){ el.textContent=L(TH); }
        else if(OLDE.indexOf(t)>-1){ el.textContent=L(EM); }
      }
      /* tooltip: ust bar ikonu (gizli olsa da) */
      try{ document.querySelectorAll('[title="Meine Anträge"]').forEach(function(e){ e.setAttribute('title',L(TH)); }); }catch(e){}
    }catch(e){}
  }
  var n=0; (function loop(){ walk(); if(n++<200) setTimeout(loop,800); })();
  try{ document.addEventListener('click',function(){ setTimeout(walk,120); },true); }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'t3').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-themen3-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_THEMEN3')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_THEMEN3 diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Tum 'Meine Anträge' etiketleri 'Meine Themen'e cevrildi.\n";
