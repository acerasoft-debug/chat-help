<?php
/**
 * ChatHelp — apply-cardwatch3 (CH_CARDWATCH3) — DUZELTILMIS olcum (kaydirma-bagimsiz)
 *  cardwatch2 kusuru: getBoundingClientRect viewport'a gore -> KAYDIRMA hareket
 *  sayildi (tum kartlar ayni anda ayni delta = scroll artefakti). Bu surum:
 *   [C'] KAYDIRMA-BAGIMSIZ konum: her kartin offsetTop/offsetLeft'i (belgeye
 *        gore) 500ms'de bir orneklenir -> sadece GERCEK yerlesim kaymasi sayilir.
 *   [B'] METIN KIMLIGI: her metin degisiminde ELEMAN (tag.class) + eski->yeni
 *        ilk 26 karakter loglanir -> donen metin dongusu ISMEN yakalanir.
 *   [A'] order yazimlari (freeze sonrasi ~0 olmali — dogrulama).
 *  15 sn olcer, OZET verir. Rozet data-noi18n (ceviri dokunmaz). SADECE TESHIS.
 * KULLANIM: pull2.php?key=...&files=apply-cardlock.php,apply-cardwatch3.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-cardwatch3 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_CARDWATCH3')!==false) exit("Zaten ekli (CH_CARDWATCH3).\n");

$bundle = <<<'HTML'
<style id="ch-cardwatch3-css">
#ch-cw3{position:fixed;right:8px;bottom:8px;z-index:2147483647;max-width:78vw;max-height:58vh;overflow:auto;
 background:#1b1030;border:2px solid #c08aff;border-radius:12px;padding:9px 30px 9px 11px;color:#ead9ff;
 font:11px/1.5 ui-monospace,Menlo,monospace;white-space:pre-wrap;word-break:break-word;pointer-events:auto}
#ch-cw3 b{color:#ffd166}
#ch-cw3-x{position:absolute;right:8px;top:5px;color:#c08aff;font-size:18px;cursor:pointer}
</style>
<script id="ch-cardwatch3-js">
/* CH_CARDWATCH3 — kaydirma-bagimsiz konum + metin-kimlik kaydi (SADECE TESHIS) */
try{(function(){
  var t0=Date.now();
  function ts(){ return ((Date.now()-t0)/1000).toFixed(1)+"s"; }
  var c={ord:0,txt:0,mov:0}, LOG=[], last={};
  function idtag(el){
    if(!el||el.nodeType!==1) return "?";
    var a=["data-ccat","data-univcat","data-chcat","data-frcat","data-trcat"];
    for(var i=0;i<a.length;i++){ if(el.hasAttribute&&el.hasAttribute(a[i])) return el.getAttribute(a[i]); }
    return (el.tagName||"?").toLowerCase()+(el.className?("."+(""+el.className).split(" ")[0]):"");
  }
  function snip(s){ return String(s==null?"":s).replace(/\s+/g," ").trim().slice(0,26); }
  function cards(){ return [].slice.call(document.querySelectorAll(".wcat")); }
  function render(){
    var b=document.getElementById("ch-cw3");
    if(!b){ b=document.createElement("div"); b.id="ch-cw3"; b.setAttribute("data-noi18n","1");
      var x=document.createElement("span"); x.id="ch-cw3-x"; x.textContent="X";
      x.onclick=function(){ b.remove(); }; b.appendChild(x);
      var p=document.createElement("div"); p.id="ch-cw3-p"; b.appendChild(p);
      document.body.appendChild(b); }
    var p=document.getElementById("ch-cw3-p");
    if(p) p.innerHTML="<b>OLCUM v3 (kaydirma-bagimsiz)</b> hukuk:"+((typeof CC!=="undefined")?CC:"?")
      +" cardlock:"+(document.getElementById("ch-cardlock-css")?"VAR":"YOK!")+"\n"
      +"[A] order: "+c.ord+"  [B] metin: "+c.txt+"  [C] GERCEK kayma: "+c.mov+"\n"
      +LOG.slice(-14).join("\n");
  }
  /* B: metin degisimi KIMLIKLE — tum #wlc uzerinde */
  try{
    var host=document.getElementById("wlc")||document.body;
    new MutationObserver(function(muts){
      muts.forEach(function(m){
        if(m.type==="characterData"){
          c.txt++;
          if(c.txt<=30){ var pe=m.target.parentElement;
            LOG.push("["+ts()+"] B "+idtag(pe)+" '"+snip(m.oldValue)+"' -> '"+snip(m.target.nodeValue)+"'"); }
        } else if(m.type==="attributes" && m.attributeName==="style"){
          var el=m.target;
          if(el.classList&&el.classList.contains("wcat")){ c.ord++; if(c.ord<=15) LOG.push("["+ts()+"] A style "+idtag(el)); }
        }
      });
      render();
    }).observe(host,{characterData:true,characterDataOldValue:true,attributes:true,attributeFilter:["style"],subtree:true});
  }catch(e){}
  /* C: kaydirma-bagimsiz konum (offsetTop/Left) */
  function sample(){
    cards().forEach(function(el){
      var key=el._cw3i||(el._cw3i=Math.random().toString(36).slice(2,7));
      var cur=(el.offsetTop|0)+","+(el.offsetLeft|0);
      if(last[key]!==undefined && last[key]!==cur){ c.mov++; if(c.mov<=30) LOG.push("["+ts()+"] C KAYDI "+idtag(el)+" "+last[key]+" -> "+cur); }
      last[key]=cur;
    });
    render();
  }
  function boot(){
    if(!cards().length){ setTimeout(boot,200); return; }
    LOG.push("["+ts()+"] v3 basladi ("+cards().length+" kart)"); render();
    var iv=setInterval(sample,500);
    setTimeout(function(){ clearInterval(iv);
      LOG.push("["+ts()+"] === 15sn OZET: A(order)="+c.ord+" B(metin)="+c.txt+" C(GERCEK kayma)="+c.mov+" ===");
      render(); },15000);
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",boot); else boot();
})();}catch(e){}
</script>
HTML;

$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok — index degistirilmedi.\n");
$src = substr($src,0,$pos).$bundle."\n".substr($src,$pos);
echo "  ✓ v3 olcum: kaydirma-bagimsiz konum + metin-kimlik kaydi\n";

$tmp = tempnam(sys_get_temp_dir(),'cw3').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-cardwatch3-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_CARDWATCH3 uygulandi. /chat/?z=35 ac, ES sec, KAYDIRMADAN 15 sn bekle,\n";
echo "   mor rozetin goruntusunu gonder. Rozet cardlock'un kurulu olup olmadigini da soyler.\n";
