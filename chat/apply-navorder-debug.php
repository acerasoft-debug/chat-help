<?php
/**
 * ChatHelp — apply-navorder-debug (CH_NAVORDER_DEBUG) — GECICI TESHIS
 *  Sol menudeki (#sb) DOM degisikliklerini MutationObserver ile izler,
 *  hangi eleman ne zaman eklendi/tasindi loglar. Sag ust kosede kucuk
 *  bir panelde son 25 olayi gosterir (zaman damgali).
 *  IS BITINCE: pull-updates.php?files=apply-navorder-debug-remove.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-navorder-debug BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_NAVORDER_DEBUG') !== false) exit("Zaten ekli (CH_NAVORDER_DEBUG).\n");
file_put_contents($file . '.bak-navorderdbg-' . date('Ymd-His'), $src);

$bundle = <<<'NOHTML'
<script id="ch-navorder-debug">
/* CH_NAVORDER_DEBUG — GECICI: sol menu siralamasi degisim izleyici */
try{(function(){
  var log=[];
  function ts(){ var d=new Date(); return d.toLocaleTimeString()+"."+String(d.getMilliseconds()).padStart(3,"0"); }
  function box(){
    var b=document.getElementById("ch-navorder-box");
    if(!b){
      b=document.createElement("div"); b.id="ch-navorder-box";
      b.style.cssText="position:fixed;right:8px;top:8px;z-index:99999;max-width:380px;max-height:70vh;overflow:auto;background:#000;color:#0ff;font:10px monospace;padding:8px 10px;border-radius:8px;border:1px solid #0ff;white-space:pre-wrap";
      document.body.appendChild(b);
    }
    return b;
  }
  function render(){ box().textContent="CH_NAVORDER_DEBUG (son "+log.length+" olay)\n"+log.join("\n"); }
  function idOf(n){ if(!n||n.nodeType!==1) return null; return n.id ? "#"+n.id : "."+(n.className||"?").toString().split(" ").join("."); }
  function snapshot(){
    var sb=document.getElementById("sb"); if(!sb) return "";
    return Array.prototype.slice.call(sb.children).map(function(c){ return idOf(c); }).join(" > ");
  }
  var lastSnap=snapshot();
  function onMut(muts){
    var sb=document.getElementById("sb"); if(!sb) return;
    var changed=false;
    muts.forEach(function(m){
      if(m.type!=="childList") return;
      m.addedNodes.forEach(function(n){ if(n.nodeType===1){ log.push(ts()+" ADD "+idOf(n)+" into "+idOf(m.target)+" (next="+idOf(n.nextSibling)+")"); changed=true; } });
      m.removedNodes.forEach(function(n){ if(n.nodeType===1){ log.push(ts()+" DEL "+idOf(n)+" from "+idOf(m.target)); changed=true; } });
    });
    if(changed){
      var snap=snapshot();
      if(snap!==lastSnap){ log.push(ts()+" ORDER: "+snap); lastSnap=snap; }
      if(log.length>200) log=log.slice(-200);
      render();
    }
  }
  function start(){
    var sb=document.getElementById("sb");
    if(!sb){ setTimeout(start,300); return; }
    log.push(ts()+" START order: "+snapshot());
    render();
    var mo=new MutationObserver(onMut);
    mo.observe(sb,{childList:true,subtree:true});
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",start);
  else start();
})();}catch(e){}
</script>
NOHTML;

$p = strrpos($src, '</body>');
if ($p === false) exit("HATA: </body> bulunamadi — yazilmadi.\n");
$src = substr($src, 0, $p) . $bundle . "\n" . substr($src, $p);

$tmp = tempnam(sys_get_temp_dir(), 'idx') . '.php';
file_put_contents($tmp, $src);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "LINT HATASI — index.php degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
file_put_contents($file, $src);
echo "✓ CH_NAVORDER_DEBUG uygulandi. Sag ust kosede mavi/camgobegi kutucuk cikacak.\n";
echo "Siteyi ac, 15-20 saniye bekle, menudeki oynamayi izle, sonra kutucugun TAMAMINI (scroll ederek) kopyala/ekran goruntusu al ve bana gonder.\n";
