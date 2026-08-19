<?php
/**
 * ChatHelp — apply-clean1 (CH_CLEAN1) — ana sayfa temizligi + Meine Themen erisimi.
 *  EKLEMELI </body> (CSS+JS), mevcut kod degismez:
 *   (1) "Job finden & bewerben" (#ch-jobs-card) ana sayfa kartini gizler (modulde var).
 *   (2) pnl-ant panel basligini "Meine Themen"e cevirir (dil bazli).
 *   (3) Sol menuye (.sb-new yanina) "Meine Themen" girisi ekler -> gP('ant')
 *       (ust bar ikonu kaldirildi, erisim buradan devam eder).
 *  ("Fälle'ye aktar" butonu ayri yamada — FK anahtari netlestirilecek.)
 * KULLANIM: pull2.php?key=...&files=apply-clean1.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-clean1 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_CLEAN1')!==false) exit("Zaten ekli (CH_CLEAN1).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-clean1-css">
/* CH_CLEAN1 — Job/Bewerbung ana sayfa karti gizli (modulde mevcut) */
#ch-jobs-card{display:none !important}
.ch-themen-nav{display:flex;align-items:center;gap:9px;margin:2px 14px 8px;padding:10px 12px;background:var(--s2,#131325);border:1px solid var(--bdr,rgba(255,255,255,.08));border-radius:10px;cursor:pointer;font-size:13px;font-weight:600;color:var(--t2,#d0d0f0);-webkit-tap-highlight-color:transparent;transition:border-color .15s}
.ch-themen-nav:hover{border-color:rgba(212,168,74,.4)}
.ch-themen-nav svg{width:16px;height:16px;stroke:#d4a84a;fill:none;stroke-width:1.8}
</style>
<script id="ch-clean1-js">
try{(function(){
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  var TH={de:'Meine Themen',tr:'Konularım',en:'My Topics',fr:'Mes sujets',es:'Mis temas',it:'I miei temi',nl:'Mijn onderwerpen',pl:'Moje tematy',pt:'Os meus temas'};
  function label(){ return TH[lang()]||TH.de; }
  function renameAnt(){
    try{
      var t=document.querySelector('#pnl-ant .pnl-topbar-title');
      if(t && t.textContent!==label()) t.textContent=label();
    }catch(e){}
  }
  function injectNav(){
    try{
      if(document.getElementById('ch-themen-nav')) { var ex=document.getElementById('ch-themen-nav'); var sp=ex.querySelector('.lbl'); if(sp && sp.textContent!==label()) sp.textContent=label(); return; }
      var anchor=document.querySelector('.sb-new'); if(!anchor||!anchor.parentNode) return;
      var b=document.createElement('div');
      b.id='ch-themen-nav'; b.className='ch-themen-nav';
      b.innerHTML='<svg viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg><span class="lbl">'+label()+'</span>';
      b.onclick=function(){ try{ if(typeof gP==='function') gP('ant'); }catch(e){} };
      anchor.parentNode.insertBefore(b, anchor.nextSibling);
    }catch(e){}
  }
  var n=0; (function loop(){ renameAnt(); injectNav(); if(n++<300) setTimeout(loop,900); })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'c1').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
$bk=@file_put_contents($file.'.bak-clean1-'.date('Ymd-His'), $src);
if ($bk===false) echo "  ⚠ yedek yazilamadi — devam\n";
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_CLEAN1')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_CLEAN1 diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Job/Bewerbung karti gizlendi; pnl-ant -> 'Meine Themen'; sol menuye Meine Themen girisi.\n";
