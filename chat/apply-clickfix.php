<?php
/**
 * ChatHelp — apply-clickfix (CH_CLICKFIX) — "hicbir seye tiklanamiyor" KALKAN AVCISI
 *  BULGU: taze sayfa (?z=1) aciliyor ama tum tiklamalar yutuluyor -> ekrani
 *  kaplayan GORUNMEZ bir sabit katman var (hangi panel/overlay oldugu belirsiz).
 *  BU YAMA (tamamen EK — hicbir onceki calismayi geri almaz):
 *   [1] Sayfa yuklendikten 2.5 sn sonra ekranin ortasindaki EN USTTEKI elemani
 *       bulur; ekranin >%85'ini kaplayan fixed/absolute katmanlari SIRAYLA
 *       pointer-events:none yapar (en fazla 6 katman) -> tiklamalar ALTTAKI
 *       gercek icerige gecer. Etkisizlestirilen her katmanin KIMLIGI kaydedilir.
 *   [2] Ekranda buyuk, kapatilabilir bir TESHIS SERIDI gosterir: hangi
 *       katman(lar) etkisizlestirildi + 4 noktadaki ust eleman + SON TIKLANAN
 *       hedef (canli) -> ekran goruntusuyle kesin kimlik bize ulasir.
 *   [3] 10. saniyede bir tarama daha yapar (gec olusan katmanlar icin).
 * KULLANIM: pull-updates.php?files=apply-clickfix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-clickfix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_CLICKFIX')!==false) exit("Zaten ekli (CH_CLICKFIX).\n");

$bundle = <<<'HTML'
<style id="ch-clickfix-css">
#ch-clickfix-banner{position:fixed;left:8px;right:8px;bottom:8px;z-index:2147483647;
  background:#101024;border:2px solid #ffd166;border-radius:12px;padding:10px 34px 10px 12px;
  color:#ffe9b3;font:12px/1.5 ui-monospace,Menlo,monospace;pointer-events:auto;
  max-height:42vh;overflow:auto;white-space:pre-wrap;word-break:break-word}
#ch-clickfix-x{position:absolute;right:8px;top:6px;color:#ffd166;font-size:20px;cursor:pointer;pointer-events:auto}
</style>
<script id="ch-clickfix-js">
/* CH_CLICKFIX — gorunmez tam-ekran kalkanlari bul, etkisizlestir, raporla */
try{(function(){
  function desc(el){
    if(!el) return "(yok)";
    var id=el.id?("#"+el.id):"";
    var cl=(el.className&&typeof el.className==="string")?("."+el.className.split(" ").slice(0,2).join(".")):"";
    var z="", p=""; try{ var cs=getComputedStyle(el); z=cs.zIndex; p=cs.position; }catch(e){}
    var r={width:0,height:0}; try{ r=el.getBoundingClientRect(); }catch(e){}
    return el.tagName.toLowerCase()+id+cl+" [pos:"+p+" z:"+z+" "+Math.round(r.width)+"x"+Math.round(r.height)+"]";
  }
  var LOG=[];
  function say(s){ LOG.push(s); render(); }
  function render(){
    var b=document.getElementById("ch-clickfix-banner");
    if(!b){
      b=document.createElement("div"); b.id="ch-clickfix-banner";
      var x=document.createElement("span"); x.id="ch-clickfix-x"; x.textContent="×";
      x.addEventListener("click",function(ev){ ev.stopPropagation(); b.remove(); });
      b.appendChild(x);
      var pre=document.createElement("div"); pre.id="ch-clickfix-pre"; b.appendChild(pre);
      document.body.appendChild(b);
    }
    var pre=document.getElementById("ch-clickfix-pre");
    if(pre) pre.textContent=LOG.slice(-14).join("\n");
  }
  function scan(tag){
    try{
      var W=innerWidth,H=innerHeight;
      /* [1] ortadaki kalkanlari soy */
      var neut=0;
      for(var i=0;i<6;i++){
        var el=document.elementFromPoint(W/2,H/2);
        if(!el||el===document.documentElement||el===document.body) break;
        if(el.id==="ch-clickfix-banner"||el.closest&&el.closest("#ch-clickfix-banner")) break;
        var cs,r; try{ cs=getComputedStyle(el); r=el.getBoundingClientRect(); }catch(e){ break; }
        var covers = r.width>=W*0.85 && r.height>=H*0.85;
        if((cs.position==="fixed"||cs.position==="absolute") && covers){
          say("KALKAN["+tag+"] etkisizlestirildi: "+desc(el));
          el.style.pointerEvents="none";
          neut++;
        } else { say("USTTE["+tag+"]: "+desc(el)); break; }
      }
      if(!neut) say("("+tag+") tam-ekran kalkan bulunamadi");
      /* [2] 4 nokta haritasi */
      [[W/2,64,"ustbar"],[24,H/2,"solkenar"],[W/2,H/2,"orta"],[W/2,H-90,"altgiris"]].forEach(function(p){
        var el=document.elementFromPoint(p[0],p[1]);
        say("nokta("+p[2]+"): "+desc(el));
      });
    }catch(e){ say("scan hata: "+e.message); }
  }
  document.addEventListener("click",function(e){ try{ say("SON TIK: "+desc(e.target)); }catch(x){} }, true);
  function boot(){ setTimeout(function(){ scan("2.5s"); }, 2500); setTimeout(function(){ scan("10s"); }, 10000); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",boot); else boot();
})();}catch(e){}
</script>
HTML;
$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$src = substr($src,0,$pos).$bundle."\n".substr($src,$pos);
echo "  ✓ kalkan avcisi + canli teshis seridi eklendi\n";

$tmp = tempnam(sys_get_temp_dir(),'cf').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-clickfix-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_CLICKFIX uygulandi. Cihazda YENI query ile ac (orn. /chat/?z=2):\n";
echo "   2.5 sn icinde kalkan(lar) otomatik etkisizlesir -> tiklamalar geri gelir.\n";
echo "   Alttaki sari seridin ekran goruntusunu gonder — kalkanin KIMLIGI orada yazacak.\n";
