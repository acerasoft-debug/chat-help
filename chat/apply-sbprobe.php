<?php
/**
 * ChatHelp — apply-sbprobe (CH_SBPROBE) — GECICI canli DOM teshis paneli
 *  Statik CSS bile oynamayi durdurmadi => varsayimlarim (kapsayici/cocuk
 *  yapisi) yanlis. Bu yama, TARAYICIDA calisan gercek DOM'u ekranda gosterir
 *  (F12 gerekmez): sag altta "🔍 SB" dugmesi; tiklayinca panel acilir.
 *  Panel gosterir:
 *   • asil menu kabinin computed display/flex-direction/overflow'u
 *   • kabin DOGRUDAN cocuklari: id/class/computed order/onclick
 *   • nav-jobs'un GERCEK ata zinciri (arada gizli sarmalayici var mi?)
 *   • CANLI izleme: 2 sn boyunca cocuklarin ekran Y konumu degisiyor mu
 *     (yani gercekten "oynuyor" mu, neresi oynuyor?)
 *  KULLANIM: pull-updates.php?files=apply-sbprobe.php
 *  Sonra: ekran goruntusu al. Kaldirmak icin apply-sbprobe-remove.php.
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-sbprobe BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_SBPROBE')!==false) exit("Zaten ekli (CH_SBPROBE).\n");

$bundle = <<<'HTML'
<style id="ch-sbprobe-css">
#ch-sbp-btn{position:fixed;right:12px;bottom:12px;z-index:2147483647;width:48px;height:48px;
  border-radius:14px;background:#12122a;border:1px solid #d4a84a;color:#d4a84a;font-size:18px;
  display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 4px 16px rgba(0,0,0,.4)}
#ch-sbp-panel{position:fixed;inset:16px;z-index:2147483646;background:#0b0b18;color:#d8d8ea;
  border:1px solid #d4a84a;border-radius:14px;padding:14px;display:none;flex-direction:column;
  font:12px/1.45 ui-monospace,Menlo,Consolas,monospace;overflow:hidden}
#ch-sbp-panel.on{display:flex}
#ch-sbp-panel h4{margin:0 0 8px;color:#d4a84a;font-size:13px}
#ch-sbp-pre{flex:1;overflow:auto;white-space:pre-wrap;word-break:break-word;background:#12122a;
  padding:10px;border-radius:8px;border:1px solid rgba(212,168,74,.25)}
#ch-sbp-x{position:absolute;right:12px;top:10px;color:#d4a84a;cursor:pointer;font-size:20px}
#ch-sbp-panel .r{color:#ff8a8a}
</style>
<script id="ch-sbprobe-js">
/* CH_SBPROBE — gecici canli DOM teshis paneli */
try{(function(){
  function findMenuBox(){
    /* nav-jobs'un GERCEK kapsayicisi = asil menu kabi */
    var nj=document.getElementById("nav-jobs")||document.getElementById("nav-cv")||document.getElementById("nav-hub");
    if(nj&&nj.parentElement) return nj.parentElement;
    return document.getElementById("sb")||null;
  }
  function chain(el){
    var out=[],p=el,i=0;
    while(p&&p!==document.body&&i<12){
      out.push((p.tagName||"?").toLowerCase()+(p.id?"#"+p.id:"")+(p.className&&typeof p.className==="string"?"."+p.className.split(" ")[0]:""));
      p=p.parentElement;i++;
    }
    return out.join(" > ");
  }
  function snap(box){ /* her cocugun ekran Y konumu */
    var m={};
    if(box) [].slice.call(box.children).forEach(function(c){ var r=c.getBoundingClientRect(); m[(c.id||c.className||c.tagName)]=Math.round(r.top); });
    return m;
  }
  function report(){
    var lines=[];
    var sb=document.getElementById("sb");
    lines.push("== #sb ==");
    if(!sb){ lines.push("  <span class='r'>#sb YOK!</span>"); }
    else{ var cs=getComputedStyle(sb); lines.push("  display="+cs.display+"  flexDir="+cs.flexDirection+"  overflowY="+cs.overflowY+"  pos="+cs.position); lines.push("  dogrudan cocuk sayisi="+sb.children.length); }
    var box=findMenuBox();
    lines.push("");
    lines.push("== ASIL MENU KABI (nav-jobs.parentElement) ==");
    if(!box){ lines.push("  <span class='r'>bulunamadi</span>"); }
    else{
      var bc=getComputedStyle(box);
      lines.push("  = "+box.tagName.toLowerCase()+(box.id?"#"+box.id:"")+(typeof box.className==="string"?" ."+box.className:""));
      lines.push("  display="+bc.display+"  flexDir="+bc.flexDirection+"  overflowY="+bc.overflowY);
      lines.push("  #sb ile ayni mi? "+((box===sb)?"EVET (nav'lar #sb'nin dogrudan cocugu)":"HAYIR ⚠ (arada sarmalayici var — order buraya uygulanmali!)"));
      lines.push("  -- dogrudan cocuklar (id · order · onclick) --");
      [].slice.call(box.children).forEach(function(c,i){
        var o=getComputedStyle(c).order;
        var oc=((c.getAttribute&&c.getAttribute("onclick"))||"").replace(/\s+/g," ").slice(0,22);
        lines.push("   ["+i+"] "+(c.id||("."+(typeof c.className==="string"?c.className.split(" ")[0]:"?")))+"  order="+o+(oc?"  oc="+oc:""));
      });
    }
    lines.push("");
    lines.push("== nav-jobs ATA ZINCIRI ==");
    var nj=document.getElementById("nav-jobs");
    lines.push("  "+(nj?chain(nj):"nav-jobs DOM'da YOK"));
    /* CANLI: 1.5 sn'de kim kimliyor? */
    lines.push("");
    lines.push("== CANLI HAREKET (1.5 sn) ==");
    var b0=snap(box);
    setTimeout(function(){
      var b1=snap(box),moved=[];
      Object.keys(b1).forEach(function(k){ if(b0[k]!=null && b0[k]!==b1[k]) moved.push(k+": "+b0[k]+"→"+b1[k]+"px"); });
      var pre=document.getElementById("ch-sbp-pre");
      if(pre){ pre.innerHTML += "\n"+(moved.length?("  OYNAYAN: \n   "+moved.join("\n   ")):"  (1.5 sn'de hicbir cocuk oynamadi)"); }
    },1500);
    return lines.join("\n");
  }
  function openPanel(){
    var p=document.getElementById("ch-sbp-panel");
    var pre=document.getElementById("ch-sbp-pre");
    if(pre) pre.innerHTML=report();
    if(p) p.classList.add("on");
  }
  function boot(){
    if(document.getElementById("ch-sbp-btn")) return;
    var b=document.createElement("div"); b.id="ch-sbp-btn"; b.textContent="🔍"; b.title="Menu teshis";
    b.onclick=openPanel; document.body.appendChild(b);
    var pnl=document.createElement("div"); pnl.id="ch-sbp-panel";
    pnl.innerHTML='<span id="ch-sbp-x">×</span><h4>🔍 Sol menu — canli DOM teshisi (ekran goruntusu al)</h4><div id="ch-sbp-pre"></div>';
    document.body.appendChild(pnl);
    pnl.querySelector("#ch-sbp-x").onclick=function(){ pnl.classList.remove("on"); };
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",boot); else boot();
})();}catch(e){}
</script>
HTML;
$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$src = substr($src,0,$pos).$bundle."\n".substr($src,$pos);
echo "  ✓ canli DOM teshis paneli eklendi (sag alt '🔍' dugmesi)\n";

$tmp = tempnam(sys_get_temp_dir(),'sp').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-sbprobe-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_SBPROBE uygulandi. Siteyi ac, sag alttaki 🔍 dugmesine bas, ekran goruntusu al.\n";
echo "   Kaldirmak icin: apply-sbprobe-remove.php\n";
