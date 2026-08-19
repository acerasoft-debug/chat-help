<?php
/**
 * ChatHelp — apply-sbprobe3 (CH_SBPROBE3) — GECICI: GENIS + KACIRILMAZ teshis
 *  sbprobe2 ekran goruntusu COK ERKEN alinmis (0.0s'de hicbir DEGISTI yok) —
 *  oynama henuz yakalanmamis. Bu surum:
 *   [1] KIRMIZI SAYAC ROZETI: degisiklik oldugu AN dugmede "N" rozeti belirir
 *       (titresen kirmizi) — kullanici ne zaman bakacagini TAHMIN ETMEK
 *       ZORUNDA KALMAZ, rozeti gorunce hemen ekran goruntusu alir.
 *   [2] DAHA GENIS AG: sidebar + kategori izgaralari YANI SIRA ust bar (.tb-r),
 *       ulke izgarasi (.co-grid / [data-cc] konteyneri), dil menusu
 *       (#ch-lang-menu), ana sayfa modul/kesif kartlari (#pnl-chat icindeki
 *       ust seviye kart gruplari) da izlenir.
 *   [3] EYLEM GUNLUGU: gP(), openFall(), setCountry/applyCC gibi bilinen
 *       navigasyon fonksiyonlari sarmalanip cagrildiginda "EYLEM:" olarak
 *       zaman damgali loglanir — hangi tiklamanin hangi DEGISTI'yi tetikledigi
 *       ANINDA gorulur.
 *   [4] Ekran boyutu degisimi (responsive) ile GERCEK bug ayirt edilsin diye
 *       pencere genisligi de her degisiklikte loglanir.
 *  Bagimsizdir — sbprobe2 kaldirilmasa da calisir uzerine eklenir.
 * KULLANIM: pull-updates.php?files=apply-sbprobe3.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-sbprobe3 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_SBPROBE3')!==false) exit("Zaten ekli (CH_SBPROBE3).\n");

$bundle = <<<'HTML'
<style id="ch-sbprobe3-css">
#ch-sbp3-btn{position:fixed;right:12px;bottom:116px;z-index:2147483647;width:48px;height:48px;
  border-radius:14px;background:#12122a;border:1px solid #ff8a3d;color:#ff8a3d;font-size:16px;
  display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 4px 16px rgba(0,0,0,.4);font-weight:800}
#ch-sbp3-badge{position:absolute;top:-6px;right:-6px;min-width:20px;height:20px;border-radius:10px;
  background:#ff3d3d;color:#fff;font:800 11px/20px system-ui;text-align:center;display:none;
  box-shadow:0 0 0 2px #12122a;animation:chP3 1s infinite}
@keyframes chP3{0%,100%{transform:scale(1)}50%{transform:scale(1.25)}}
#ch-sbp3-panel{position:fixed;inset:16px;z-index:2147483646;background:#0b0b18;color:#d8d8ea;
  border:1px solid #ff8a3d;border-radius:14px;padding:14px;display:none;flex-direction:column;
  font:11px/1.5 ui-monospace,Menlo,Consolas,monospace;overflow:hidden}
#ch-sbp3-panel.on{display:flex}
#ch-sbp3-panel h4{margin:0 0 8px;color:#ff8a3d;font-size:13px}
#ch-sbp3-pre{flex:1;overflow:auto;white-space:pre-wrap;word-break:break-word;background:#12122a;
  padding:10px;border-radius:8px;border:1px solid rgba(255,138,61,.25)}
#ch-sbp3-x{position:absolute;right:12px;top:10px;color:#ff8a3d;cursor:pointer;font-size:20px}
#ch-sbp3-panel .r{color:#ff8a8a;font-weight:800}
#ch-sbp3-panel .g{color:#46be78}
#ch-sbp3-panel .y{color:#ffd166}
</style>
<script id="ch-sbprobe3-js">
/* CH_SBPROBE3 — genis ag + kirmizi sayac rozeti + eylem gunlugu */
try{(function(){
  var LOG=[]; var t0=null; var changeCount=0;
  function ts(){ if(!t0) t0=Date.now(); return ((Date.now()-t0)/1000).toFixed(1)+"s"; }
  function pushLog(s){ LOG.push("["+ts()+"] "+s); if(LOG.length>500) LOG.shift(); render(); }
  function bumpBadge(){
    changeCount++;
    var b=document.getElementById("ch-sbp3-badge");
    if(b){ b.textContent=String(changeCount); b.style.display="block"; }
  }

  function findMenuBox(){
    var nj=document.getElementById("nav-jobs")||document.getElementById("nav-cv")||document.getElementById("nav-hub");
    return (nj&&nj.parentElement)?nj.parentElement:(document.getElementById("sb")||null);
  }
  function extraContainers(){
    var sel=[".sb-cat",".wcat-grid",".vsf-grid",".co-grid","[class*='cat-grid']",
             ".tb-r",".co","#ch-lang-menu",".vd-grid","#pnl-chat .qa-grid","[class*='qa-grid']",
             "[class*='discover']","[class*='module-grid']"];
    var out=[];
    sel.forEach(function(s){ try{ document.querySelectorAll(s).forEach(function(e){
      if(out.indexOf(e)<0 && e.children && e.children.length>1) out.push(e);
    }); }catch(e){} });
    return out;
  }
  function sig(el){
    if(!el||!el.children) return "";
    return [].slice.call(el.children).map(function(c){
      var id=c.id||"";
      var cls=(typeof c.className==="string")?c.className.split(" ")[0]:"";
      var tx=(c.textContent||"").trim().slice(0,12).replace(/\s+/g," ");
      var ord=""; try{ ord=getComputedStyle(c).order; }catch(e){}
      var r=""; try{ var rc=c.getBoundingClientRect(); r=Math.round(rc.top)+","+Math.round(rc.left); }catch(e){}
      return (id||cls||tx||"?")+"@o"+ord+"@"+r;
    }).join(" | ");
  }
  var watchers=[];
  function refreshWatchers(){
    var list=[];
    var box=findMenuBox();
    if(box) list.push({label:"SIDEBAR("+(box.id||box.className)+")", el:box});
    extraContainers().forEach(function(el,i){
      var lbl="W["+i+"]"+(el.id?"#"+el.id:(typeof el.className==="string"?"."+el.className.split(" ")[0]:""));
      list.push({label:lbl, el:el});
    });
    list.forEach(function(w){
      var ex=watchers.filter(function(o){ return o.el===w.el; })[0];
      if(!ex){ w.lastSig=sig(w.el); watchers.push(w); pushLog("<span class='y'>izleniyor</span> + "+w.label+" ("+w.el.children.length+" cocuk)"); }
    });
    watchers=watchers.filter(function(w){
      if(!document.body.contains(w.el)){ pushLog("kayboldu: "+w.label); return false; }
      return true;
    });
  }
  var lastW=null;
  function tick(){
    refreshWatchers();
    var w2=window.innerWidth;
    if(lastW!==null && lastW!==w2){ pushLog("<span class='y'>pencere genisligi degisti</span> "+lastW+"px -> "+w2+"px (responsive olabilir)"); }
    lastW=w2;
    watchers.forEach(function(w){
      var s=sig(w.el);
      if(s!==w.lastSig){
        pushLog("<span class='r'>DEGISTI</span> "+w.label+"\n  ONCE: "+w.lastSig+"\n  SONRA: "+s);
        w.lastSig=s;
        bumpBadge();
      }
    });
  }
  function render(){
    var pre=document.getElementById("ch-sbp3-pre");
    if(pre) pre.innerHTML=LOG.slice(-160).join("\n");
  }
  function wrapFn(name){
    try{
      if(typeof window[name]!=="function"||window[name].__chWrapped) return;
      var orig=window[name];
      window[name]=function(){
        pushLog("<span class='g'>EYLEM</span>: "+name+"("+[].slice.call(arguments).map(function(a){return JSON.stringify(a);}).join(",")+")");
        return orig.apply(this,arguments);
      };
      window[name].__chWrapped=true;
    }catch(e){}
  }
  function boot(){
    if(document.getElementById("ch-sbp3-btn")) return;
    var b=document.createElement("div"); b.id="ch-sbp3-btn"; b.title="Genis izleme + rozet";
    b.innerHTML='👁️<span id="ch-sbp3-badge"></span>';
    b.onclick=function(){ var p=document.getElementById("ch-sbp3-panel"); if(p) p.classList.toggle("on"); var bd=document.getElementById("ch-sbp3-badge"); if(bd){bd.style.display="none";} render(); };
    document.body.appendChild(b);
    var pnl=document.createElement("div"); pnl.id="ch-sbp3-panel";
    pnl.innerHTML='<span id="ch-sbp3-x">×</span><h4>👁️ Genis izleme + eylem gunlugu — rozet gorunce ekran goruntusu al</h4><div id="ch-sbp3-pre"></div>';
    document.body.appendChild(pnl);
    pnl.querySelector("#ch-sbp3-x").onclick=function(){ pnl.classList.remove("on"); };
    pushLog("<span class='g'>izleme baslatildi</span> (genis ag + eylem gunlugu)");
    ["gP","openFall","setCountry","applyCC","NC","oSB"].forEach(wrapFn);
    tick();
    setInterval(function(){
      ["gP","openFall","setCountry","applyCC","NC","oSB"].forEach(wrapFn); /* gec tanimlananlari da sar */
      tick();
    }, 250);
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",boot); else boot();
})();}catch(e){}
</script>
HTML;
$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$src = substr($src,0,$pos).$bundle."\n".substr($src,$pos);
echo "  ✓ genis ag + kirmizi sayac rozeti + eylem gunlugu eklendi (sag alt, turuncu 👁️)\n";

$tmp = tempnam(sys_get_temp_dir(),'sp3').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-sbprobe3-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_SBPROBE3 uygulandi. Turuncu 👁️ dugmesi sag altta biraz daha yukarida.\n";
echo "   Siteyi normal kullan — degisiklik oldugu AN dugmede KIRMIZI SAYAC belirir.\n";
echo "   O ani gorunce hemen 👁️'ya bas ve ekran goruntusu al. Kaldirmak icin: apply-sbprobe3-remove.php\n";
