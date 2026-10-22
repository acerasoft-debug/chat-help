<?php
/**
 * ChatHelp — apply-menufix2 (CH_MENUFIX2) — sol menu KESIN sabitleme
 *  KOK NEDEN: eski enforceOrder (a) capayi tum sayfadan (.sb-photo-btn) ariyor,
 *  welcome ekranindaki 'Fall' dugmesini yanlislikla secebiliyor; (b) 'chat'
 *  dugmesini farkli kapsayicidan tasimaya calisip salinima giriyor; (c) 900ms
 *  aralikla calistigi icin gorunur ziplama oluyor.
 *  COZUM:
 *   1) Eski enforceOrder devre disi (return).
 *   2) Yeni duzenleyici: SADECE #sb icinde, SADECE modul nav dugmelerini
 *      (nav-*) openFall dugmesinin ardina sabit sirayla dizer. chat/NC'ye
 *      DOKUNMAZ. MutationObserver ile ANINDA (ziplamasiz) + geri-besleme
 *      korumasi (busy) + 1sn yedek interval. Dil-bagimsiz.
 * KULLANIM: pull-updates.php?files=apply-menufix2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-menufix2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_MENUFIX2')!==false) exit("Zaten ekli (CH_MENUFIX2).\n");

/* ── 1) eski enforceOrder devre disi ── */
$o1 = "function enforceOrder(){\n    try{";
$n1 = "function enforceOrder(){ return; /*CH_MENUFIX2: eski enforcer devre disi, yeni #sb-scoped enforcer devrede*/\n    try{";
$c=0; $src=str_replace($o1,$n1,$src,$c);
echo ($c===1?"  ✓ [1] eski enforceOrder devre disi\n":"  ✗ [1] enforceOrder anchor ($c) — eski surum?\n");
if ($c!==1) { echo "\nHATA: enforceOrder bulunamadi — index.php DEGISTIRILMEDI.\n"; exit; }

/* ── 2) yeni #sb-scoped enforcer ── */
$bundle = <<<'HTML'
<script id="ch-menufix2-js">
/* CH_MENUFIX2 — sol menu kesin sira (yalniz #sb, yalniz nav-*, chat'e dokunmaz) */
try{(function(){
  var SEQ=["nav-verlauf","nav-jobs","nav-cv","nav-vst","nav-trvisa","nav-fris","nav-rech","nav-tresor","nav-briefe","nav-hub"];
  function sb(){ return document.getElementById("sb"); }
  function findFall(){
    var s=sb(); if(!s) return null;
    var fall=null;
    s.querySelectorAll(".sb-photo-btn").forEach(function(b){
      if(!fall && !/^nav-/.test(b.id||"") && /openFall/.test(b.getAttribute("onclick")||"")) fall=b;
    });
    if(!fall){ s.querySelectorAll(".sb-photo-btn").forEach(function(b){ if(!fall && !/^nav-/.test(b.id||"")) fall=b; }); }
    return fall;
  }
  var busy=false;
  function order(){
    if(busy) return;
    try{
      var fall=findFall(); if(!fall||!fall.parentNode) return;
      var parent=fall.parentNode;
      var anchor=fall;
      SEQ.forEach(function(id){
        var el=document.getElementById(id); if(!el) return;
        if(el.parentNode!==parent){ return; } /* baska kapsayicidaki navi tasima — guvenli */
        if(el.previousElementSibling!==anchor){
          busy=true;
          parent.insertBefore(el, anchor.nextSibling);
          busy=false;
        }
        anchor=el;
      });
    }catch(e){ busy=false; }
  }
  function boot(){
    order();
    try{
      var s=sb();
      if(s && window.MutationObserver){
        var mo=new MutationObserver(function(){ if(!busy) order(); });
        mo.observe(s,{childList:true,subtree:true});
      }
    }catch(e){}
    setInterval(order, 1000);
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",boot); else boot();
})();}catch(e){}
</script>
HTML;
$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$src = substr($src,0,$pos).$bundle."\n".substr($src,$pos);
echo "  ✓ [2] yeni #sb-scoped enforcer eklendi (MutationObserver + interval)\n";

$tmp = tempnam(sys_get_temp_dir(),'m2').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-menufix2-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_MENUFIX2 uygulandi. Sol menu artik #sb-scoped + MutationObserver ile ANINDA sabit — oynamaz.\n";
