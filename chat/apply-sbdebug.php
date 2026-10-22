<?php
/**
 * ChatHelp — apply-sbdebug (CH_SBDEBUG) — GECICI TESHIS
 *  Sol menunun (#sb) gercek boyutlarini ekrana yazar: genislik/yukseklik,
 *  tasma var mi (scrollWidth/Height vs clientWidth/Height), ekran boyutu,
 *  #sb'nin position/overflow degerleri, ve ust container bilgisi.
 *  IS BITINCE: pull-updates.php?files=apply-debug-remove2.php
 * KULLANIM: pull-updates.php?files=apply-sbdebug.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-sbdebug BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_SBDEBUG') !== false) exit("Zaten ekli (CH_SBDEBUG).\n");
file_put_contents($file . '.bak-sbdebug-' . date('Ymd-His'), $src);

$bundle = <<<'SBHTML'
<script id="ch-sbdebug">
/* CH_SBDEBUG — GECICI: #sb boyut/tasma teshisi */
try{(function(){
  function box(){
    var b=document.getElementById("ch-sbdebug-box");
    if(!b){
      b=document.createElement("div"); b.id="ch-sbdebug-box";
      b.style.cssText="position:fixed;left:8px;top:8px;z-index:99999;max-width:360px;max-height:80vh;overflow:auto;background:#000;color:#ff0;font:10.5px monospace;padding:10px 12px;border-radius:8px;border:1px solid #ff0;white-space:pre-wrap";
      document.body.appendChild(b);
    }
    return b;
  }
  function build(){
    var sb=document.getElementById("sb");
    var out="CH_SBDEBUG\n";
    out+="window: "+window.innerWidth+"x"+window.innerHeight+" dpr="+(window.devicePixelRatio||1)+"\n";
    out+="UA: "+navigator.userAgent.slice(0,70)+"\n\n";
    if(!sb){ out+="#sb BULUNAMADI\n"; box().textContent=out; return; }
    var cs=window.getComputedStyle(sb);
    var r=sb.getBoundingClientRect();
    out+="#sb rect: left="+Math.round(r.left)+" top="+Math.round(r.top)+" w="+Math.round(r.width)+" h="+Math.round(r.height)+"\n";
    out+="#sb client: "+sb.clientWidth+"x"+sb.clientHeight+"\n";
    out+="#sb scroll: "+sb.scrollWidth+"x"+sb.scrollHeight+"\n";
    out+="#sb tasma-X: "+(sb.scrollWidth>sb.clientWidth?"VAR ("+(sb.scrollWidth-sb.clientWidth)+"px)":"yok")+"\n";
    out+="#sb tasma-Y: "+(sb.scrollHeight>sb.clientHeight?"VAR ("+(sb.scrollHeight-sb.clientHeight)+"px)":"yok")+"\n";
    out+="#sb position="+cs.position+" width="+cs.width+" maxWidth="+cs.maxWidth+" height="+cs.height+"\n";
    out+="#sb overflowX="+cs.overflowX+" overflowY="+cs.overflowY+" display="+cs.display+"\n";
    out+="#sb sagKenar (left+width): "+Math.round(r.left+r.width)+"  (window.innerWidth="+window.innerWidth+")\n";
    out+="EKRANDAN TASMA: "+((r.left+r.width>window.innerWidth||r.left<0)?"EVET, sag/sol kenar disarida":"hayir, kenarlar icinde")+"\n";
    var parent=sb.parentElement;
    if(parent){
      var pr=parent.getBoundingClientRect();
      out+="\nust eleman <"+parent.tagName.toLowerCase()+(parent.id?"#"+parent.id:"")+"> rect: w="+Math.round(pr.width)+" h="+Math.round(pr.height)+"\n";
    }
    out+="\ndocument.documentElement.scrollWidth="+document.documentElement.scrollWidth+" (window.innerWidth="+window.innerWidth+")\n";
    out+="sayfada yatay tasma: "+(document.documentElement.scrollWidth>window.innerWidth?"EVET":"hayir")+"\n";
    box().textContent=out;
  }
  function tick(){ build(); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",function(){ tick(); setInterval(tick,1000); window.addEventListener("resize",tick); });
  else { tick(); setInterval(tick,1000); window.addEventListener("resize",tick); }
})();}catch(e){}
</script>
SBHTML;

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
echo "✓ CH_SBDEBUG uygulandi. Sol ust kosede sari kutucuk cikacak — tableti kullandigin ekranda ac, kutunun TAMAMINI (kaydirarak) bana yaz veya ekran goruntusu at.\n";
