<?php
/**
 * ChatHelp — apply-cardwatch2 (CH_CARDWATCH2) — kart hareketinin GERCEK sebebi
 *  cardwatch (childList) = 0/0/0 cikti: kartlar eklenip cikarilmiyor. Demek ki
 *  gorunen hareket ya CSS order churn (oznitelik) ya da ceviri->reflow.
 *  Bu surum 3 seyi ayni anda olcer, 14 sn:
 *   [A] STYLE/order oznitelik degisimi (attributes observer) — kac kez, hangi kart
 *   [B] METIN degisimi (characterData/subtree) — ceviri katmani kart metnini
 *       kac kez yeniden yaziyor
 *   [C] GERCEK KONUM: her .wcat'in getBoundingClientRect().top/left'ini 500ms'de
 *       bir orneklER; kac kart GERCEKTEN yer degistirdi (gozle gorunen hareket)
 *  Rozet (data-noi18n — ceviri dokunmaz) sag altta ozetler. SADECE TESHIS.
 * KULLANIM: pull2.php?key=...&files=apply-cardwatch2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-cardwatch2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_CARDWATCH2')!==false) exit("Zaten ekli (CH_CARDWATCH2).\n");

$bundle = <<<'HTML'
<style id="ch-cardwatch2-css">
#ch-cw2{position:fixed;right:8px;bottom:8px;z-index:2147483647;max-width:76vw;max-height:56vh;overflow:auto;
 background:#101b0d;border:2px solid #7ad06a;border-radius:12px;padding:9px 30px 9px 11px;color:#d7ffcf;
 font:11.5px/1.5 ui-monospace,Menlo,monospace;white-space:pre-wrap;word-break:break-word;pointer-events:auto}
#ch-cw2 b{color:#ffd166}
#ch-cw2-x{position:absolute;right:8px;top:5px;color:#7ad06a;font-size:18px;cursor:pointer}
</style>
<script id="ch-cardwatch2-js">
/* CH_CARDWATCH2 — order/metin/konum uc yonlu olcum (SADECE TESHIS) */
try{(function(){
  var t0=Date.now();
  function ts(){ return ((Date.now()-t0)/1000).toFixed(1)+"s"; }
  var c={styl:0,txt:0,posMove:0}, LOG=[], last={};
  function idtag(el){
    if(!el||el.nodeType!==1) return "?";
    var a=["data-ccat","data-univcat","data-chcat","data-frcat","data-trcat"];
    for(var i=0;i<a.length;i++){ if(el.hasAttribute&&el.hasAttribute(a[i])) return el.getAttribute(a[i]); }
    return "etiketSIZ";
  }
  function cards(){ return [].slice.call(document.querySelectorAll(".wcat")); }
  function render(){
    var b=document.getElementById("ch-cw2");
    if(!b){ b=document.createElement("div"); b.id="ch-cw2"; b.setAttribute("data-noi18n","1");
      var x=document.createElement("span"); x.id="ch-cw2-x"; x.setAttribute("data-noi18n","1"); x.textContent="X";
      x.onclick=function(){ b.remove(); }; b.appendChild(x);
      var p=document.createElement("div"); p.id="ch-cw2-p"; p.setAttribute("data-noi18n","1"); b.appendChild(p);
      document.body.appendChild(b); }
    var p=document.getElementById("ch-cw2-p");
    if(p) p.innerHTML="<b>KART OLCUM</b> hukuk:"+((typeof CC!=="undefined")?CC:"?")+" kart:"+cards().length+"\n"
      +"[A] order/style degisimi: "+c.styl+"\n[B] metin degisimi: "+c.txt+"\n[C] GERCEK konum kaymasi: "+c.posMove+"\n"
      +LOG.slice(-12).join("\n");
  }
  function attach(el){
    if(el._cw2) return; el._cw2=1;
    try{ new MutationObserver(function(muts){
      muts.forEach(function(m){
        if(m.type==="attributes"){ c.styl++; if(c.styl<=40) LOG.push("["+ts()+"] A style/"+m.attributeName+" "+idtag(el)+" order="+(el.style&&el.style.order)); }
        else if(m.type==="characterData"||m.type==="childList"){ c.txt++; if(c.txt<=40) LOG.push("["+ts()+"] B metin "+idtag(el)); }
      }); render();
    }).observe(el,{attributes:true,attributeFilter:["style","class"],characterData:true,subtree:true,childList:true}); }catch(e){}
  }
  function sample(){
    cards().forEach(function(el){
      var k=idtag(el); var r; try{ r=el.getBoundingClientRect(); }catch(e){ return; }
      var key=k+"#"+(el._cw2i||(el._cw2i=Math.random().toString(36).slice(2,6)));
      var cur=Math.round(r.top)+","+Math.round(r.left);
      if(last[key]!==undefined && last[key]!==cur){ c.posMove++; if(c.posMove<=40) LOG.push("["+ts()+"] C KAYDI "+k+" "+last[key]+" -> "+cur); }
      last[key]=cur;
    });
    render();
  }
  function boot(){
    if(!cards().length){ setTimeout(boot,200); return; }
    cards().forEach(attach); LOG.push("["+ts()+"] olcum basladi ("+cards().length+" kart)"); render();
    var iv=setInterval(function(){ cards().forEach(attach); sample(); }, 500);
    setTimeout(function(){ clearInterval(iv); LOG.push("["+ts()+"] --- 14sn OZET: A(order)="+c.styl+" B(metin)="+c.txt+" C(gorsel kaydi)="+c.posMove+" ---"); render(); },14000);
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",boot); else boot();
})();}catch(e){}
</script>
HTML;

$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok — index degistirilmedi.\n");
$src = substr($src,0,$pos).$bundle."\n".substr($src,$pos);
echo "  ✓ uc-yonlu olcum izleyicisi eklendi (order + metin + gercek konum)\n";

$tmp = tempnam(sys_get_temp_dir(),'cw2').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-cardwatch2-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_CARDWATCH2 uygulandi. /chat/?z=31 ile ac, ES/TR sec, 14 sn bekle,\n";
echo "   yesil rozetin EKRAN GORUNTUSUNU gonder. OZET satiri hangi sebep oldugunu soyleyecek.\n";
