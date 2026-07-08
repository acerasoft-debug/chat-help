<?php
/**
 * ChatHelp — apply-menufix3 (CH_MENUFIX3) — sol menu KESIN + NIHAI cozum
 *  Onceki iki deneme DOM tasima (insertBefore) yapiyordu; modul navlari
 *  beklenen kapsayicida olmayinca calismiyordu -> hala oynuyordu.
 *  YENI YAKLASIM: DOM'a HIC dokunma — CSS flexbox 'order'.
 *   • Navlarin GERCEK kapsayicisini bir nav'in kendi parentNode'undan bulur
 *     (tahmin/scope yok).
 *   • O kapsayiciyi flex-column yapar; her cocuga 'order' atar: modul navlari
 *     openFall dugmesinin ardina SABIT sirayla, diger cocuklar mevcut
 *     konumlarini korur.
 *   • order CSS oldugundan DOM nasil degisirse degissin gorsel konum SABIT —
 *     hicbir sey oynatamaz. MutationObserver ile yeni navlar aninda yerine
 *     oturur (ziplama yok). Idempotent (ayni order = reflow yok).
 * KULLANIM: pull-updates.php?files=apply-menufix3.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-menufix3 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_MENUFIX3')!==false) exit("Zaten ekli (CH_MENUFIX3).\n");

$bundle = <<<'HTML'
<script id="ch-menufix3-js">
/* CH_MENUFIX3 — sol menu NIHAI sabit sira (CSS flex order, DOM tasimasiz) */
try{(function(){
  var SEQ=["nav-verlauf","nav-jobs","nav-cv","nav-vst","nav-trvisa","nav-fris","nav-rech","nav-tresor","nav-briefe","nav-hub"];
  var ORD={}; SEQ.forEach(function(id,i){ ORD[id]=i+1; });
  function apply(){
    try{
      /* navlarin GERCEK kapsayicisi (bir modul navinin parent'i) */
      var parent=null;
      for(var i=0;i<SEQ.length && !parent;i++){ var e=document.getElementById(SEQ[i]); if(e&&e.parentNode) parent=e.parentNode; }
      if(!parent) return;
      /* kapsayiciyi flex-column yap (degilse) */
      var disp="";
      try{ disp=(window.getComputedStyle?getComputedStyle(parent).display:parent.style.display)||""; }catch(e){}
      if(disp!=="flex"){ parent.style.display="flex"; parent.style.flexDirection="column"; }
      /* openFall dugmesi (ayni kapsayicida) — navlar bunun ardina */
      var kids=[].slice.call(parent.children);
      var fallIdx=-1;
      for(var j=0;j<kids.length;j++){
        var oc=(kids[j].getAttribute&&kids[j].getAttribute("onclick"))||"";
        if(/openFall/.test(oc) && !/^nav-/.test(kids[j].id||"")){ fallIdx=j; break; }
      }
      var base=(fallIdx>=0?fallIdx:0)*100;
      /* order ata: navlar sabit, digerleri mevcut konum */
      kids.forEach(function(el,k){
        var id=el.id||"";
        var want = (ORD[id]!=null) ? (base+ORD[id]) : (k*100);
        var cur=el.style.order;
        if(cur!==String(want)) el.style.order=String(want); /* idempotent */
      });
    }catch(e){}
  }
  function boot(){
    apply();
    try{
      var p=null; for(var i=0;i<SEQ.length&&!p;i++){ var e=document.getElementById(SEQ[i]); if(e&&e.parentNode) p=e.parentNode; }
      var target=p||document.getElementById("sb")||document.body;
      if(target && window.MutationObserver){ new MutationObserver(function(){ apply(); }).observe(target,{childList:true,subtree:true}); }
    }catch(e){}
    setInterval(apply, 1200);
    /* ilk saniyelerde moduller gec yuklenebilir — birkac erken tetik */
    setTimeout(apply,300); setTimeout(apply,800); setTimeout(apply,1600); setTimeout(apply,3000);
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",boot); else boot();
})();}catch(e){}
</script>
HTML;
$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$src = substr($src,0,$pos).$bundle."\n".substr($src,$pos);
echo "  ✓ CSS flex-order tabanli nihai menu sabitleyici eklendi\n";

$tmp = tempnam(sys_get_temp_dir(),'m3').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-menufix3-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_MENUFIX3 uygulandi. Menu artik CSS order ile sabit — DOM nasil degisirse degissin oynamaz.\n";
