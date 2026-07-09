<?php
/**
 * ChatHelp — apply-sbprobe2 (CH_SBPROBE2) — GECICI: SUREKLI izleyen canli teshis
 *  Onceki probe (sbprobe) tek-seferlik 1.5 sn snapshot aliyordu ve sidebar
 *  nav'larda hareket YOKTU (menufix4/5 basarili). Kullanici HALA oynama
 *  gördüğünü bildiriyor -> ya (a) hareket benim kisa pencereden daha seyrek
 *  oluyor, ya (b) oynayan sey nav-* degil BASKA bir liste (kategori kartlari,
 *  katalog listesi — ozellikle yeni 10 ulke enjeksiyonundan sonra), ya da
 *  (c) sayfa GECISLERINDE (modul acilis/kapanis) oluyor.
 *  Bu surum: OTOMATIK baslar (dugmeye basmaya gerek yok), 20 saniye boyunca
 *  HER 300ms'de sidebar + tum '.co,.wcat,.sb-cat,[class*=cat]' konteynerlerinin
 *  cocuk SIRASINI (id/text listesi) kaydeder, degisiklik oldugu ANI (zaman
 *  damgali) panelde gosterir. Ayrica sayfa/modul degisimlerini de izler.
 * KULLANIM: pull-updates.php?files=apply-sbprobe2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-sbprobe2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_SBPROBE2')!==false) exit("Zaten ekli (CH_SBPROBE2).\n");

$bundle = <<<'HTML'
<style id="ch-sbprobe2-css">
#ch-sbp2-btn{position:fixed;right:12px;bottom:64px;z-index:2147483647;width:48px;height:48px;
  border-radius:14px;background:#12122a;border:1px solid #46be78;color:#46be78;font-size:16px;
  display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 4px 16px rgba(0,0,0,.4);font-weight:800}
#ch-sbp2-panel{position:fixed;inset:16px;z-index:2147483646;background:#0b0b18;color:#d8d8ea;
  border:1px solid #46be78;border-radius:14px;padding:14px;display:none;flex-direction:column;
  font:11px/1.5 ui-monospace,Menlo,Consolas,monospace;overflow:hidden}
#ch-sbp2-panel.on{display:flex}
#ch-sbp2-panel h4{margin:0 0 8px;color:#46be78;font-size:13px}
#ch-sbp2-pre{flex:1;overflow:auto;white-space:pre-wrap;word-break:break-word;background:#12122a;
  padding:10px;border-radius:8px;border:1px solid rgba(70,190,120,.25)}
#ch-sbp2-x{position:absolute;right:12px;top:10px;color:#46be78;cursor:pointer;font-size:20px}
#ch-sbp2-panel .r{color:#ff8a8a;font-weight:800}
#ch-sbp2-panel .g{color:#46be78}
</style>
<script id="ch-sbprobe2-js">
/* CH_SBPROBE2 — surekli izleyen canli DOM/order teshis paneli */
try{(function(){
  var LOG=[]; var t0=null;
  function ts(){ if(!t0) t0=Date.now(); return ((Date.now()-t0)/1000).toFixed(1)+"s"; }
  function pushLog(s){ LOG.push("["+ts()+"] "+s); if(LOG.length>400) LOG.shift(); render(); }

  function findMenuBox(){
    var nj=document.getElementById("nav-jobs")||document.getElementById("nav-cv")||document.getElementById("nav-hub");
    return (nj&&nj.parentElement)?nj.parentElement:(document.getElementById("sb")||null);
  }
  function catContainers(){
    var sel=[".sb-cat",".wcat-grid",".vsf-grid",".co-grid","[class*='cat-grid']","#pnl-hub .wcat-grid"];
    var out=[];
    sel.forEach(function(s){ try{ document.querySelectorAll(s).forEach(function(e){ if(out.indexOf(e)<0) out.push(e); }); }catch(e){} });
    return out;
  }
  function sig(el){ /* cocuklarin gorsel sira imzasi: id||class||text-kisa, computed order ile */
    if(!el||!el.children) return "";
    return [].slice.call(el.children).map(function(c){
      var id=c.id||"";
      var cls=(typeof c.className==="string")?c.className.split(" ")[0]:"";
      var tx=(c.textContent||"").trim().slice(0,14).replace(/\s+/g," ");
      var ord="";
      try{ ord=getComputedStyle(c).order; }catch(e){}
      return (id||cls||tx||"?")+"@o"+ord;
    }).join(" | ");
  }
  var watchers=[]; // {label, el, lastSig}
  function refreshWatchers(){
    var list=[];
    var box=findMenuBox();
    if(box) list.push({label:"SIDEBAR("+(box.id||box.className)+")", el:box});
    catContainers().forEach(function(el,i){
      var lbl="CAT["+i+"]"+(el.id?"#"+el.id:(typeof el.className==="string"?"."+el.className.split(" ")[0]:""));
      list.push({label:lbl, el:el});
    });
    /* mevcut watcher'lari koru (lastSig), yenilerini ekle */
    list.forEach(function(w){
      var ex=watchers.filter(function(o){ return o.el===w.el; })[0];
      if(!ex){ w.lastSig=sig(w.el); watchers.push(w); pushLog("IZLENIYOR + "+w.label+" ("+w.el.children.length+" cocuk)"); }
    });
    /* kaybolanlari isaretle */
    watchers=watchers.filter(function(w){
      if(!document.body.contains(w.el)){ pushLog("KAYBOLDU: "+w.label); return false; }
      return true;
    });
  }
  function tick(){
    refreshWatchers();
    watchers.forEach(function(w){
      var s=sig(w.el);
      if(s!==w.lastSig){
        pushLog("<span class='r'>DEGISTI</span> "+w.label+"\n  ONCE: "+w.lastSig+"\n  SONRA: "+s);
        w.lastSig=s;
      }
    });
  }
  function render(){
    var pre=document.getElementById("ch-sbp2-pre");
    if(pre) pre.innerHTML=LOG.slice(-120).join("\n");
  }
  function boot(){
    if(document.getElementById("ch-sbp2-btn")) return;
    var b=document.createElement("div"); b.id="ch-sbp2-btn"; b.textContent="👁️"; b.title="Surekli izleme (otomatik baslar)";
    b.onclick=function(){ var p=document.getElementById("ch-sbp2-panel"); if(p) p.classList.toggle("on"); render(); };
    document.body.appendChild(b);
    var pnl=document.createElement("div"); pnl.id="ch-sbp2-panel";
    pnl.innerHTML='<span id="ch-sbp2-x">×</span><h4>👁️ Surekli DOM izleme — degisiklik oldugu AN kaydedilir (ekran goruntusu al)</h4><div id="ch-sbp2-pre"></div>';
    document.body.appendChild(pnl);
    pnl.querySelector("#ch-sbp2-x").onclick=function(){ pnl.classList.remove("on"); };
    pushLog("<span class='g'>izleme baslatildi</span> — otomatik, dugmeye basmadan calisiyor");
    tick();
    setInterval(tick, 300);
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",boot); else boot();
})();}catch(e){}
</script>
HTML;
$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$src = substr($src,0,$pos).$bundle."\n".substr($src,$pos);
echo "  ✓ surekli izleyen teshis paneli eklendi (sag alt '👁️' — otomatik baslar)\n";

$tmp = tempnam(sys_get_temp_dir(),'sp2').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-sbprobe2-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_SBPROBE2 uygulandi. Siteyi ac (dugmeye basmana bile gerek yok, otomatik izler),\n";
echo "   normal kullan (modul ac/kapa, ulke degistir), sonra sag alttaki 👁️'ya bas ve\n";
echo "   ekran goruntusu al. 'DEGISTI' satirlari TAM olarak neyin ne zaman oynadigini gosterir.\n";
echo "   Kaldirmak icin: apply-sbprobe2-remove.php\n";
