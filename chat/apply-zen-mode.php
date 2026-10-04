<?php
/**
 * ChatHelp — apply-zen-mode (CH_ZEN) — tek tikla tertemiz sayfa: SADECE CHAT
 *  Sol ustte kucuk bir dugme; tiklaninca sol menu + karsilama bolumleri
 *  gizlenir, yalniz sohbet (mesajlar + yazma alani) kalir. Tekrar tiklaninca
 *  her sey geri gelir. Tercih kalicidir (localStorage ch_zen).
 *  Tamamen EK (CSS + kucuk JS) — mevcut hicbir davranisa dokunmaz.
 * KULLANIM: pull2.php?key=...&files=apply-zen-mode.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-zen-mode BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_ZEN')!==false) exit("Zaten ekli (CH_ZEN).\n");

$bundle = <<<'HTML'
<style id="ch-zen-css">
/* CH_ZEN — tek tikla sadece-chat gorunumu */
#ch-zen-btn{position:fixed;left:10px;top:64px;z-index:99950;width:38px;height:38px;border-radius:50%;
  background:linear-gradient(145deg,var(--s2,#26262c),var(--s1,#1b1b22));border:1.5px solid rgba(212,168,74,.4);
  color:var(--gold,#d4a84a);font-size:17px;cursor:pointer;display:flex;align-items:center;justify-content:center;
  box-shadow:0 4px 14px rgba(0,0,0,.35);transition:transform .15s,border-color .15s;-webkit-tap-highlight-color:transparent}
#ch-zen-btn:hover{transform:scale(1.08);border-color:var(--gold,#d4a84a)}
body.ch-zen #sb{display:none !important}
body.ch-zen #wlc .wlc-hero,body.ch-zen #wlc .wcat-grid,body.ch-zen #wlc .ch-cat-tg,
body.ch-zen #wlc .ch-vtr-sec,body.ch-zen #wlc .ch-ex-sec,body.ch-zen #wlc #ch-vst-home,
body.ch-zen #wlc .co-grid,body.ch-zen #wlc [class*="staat"]{display:none !important}
body.ch-zen #ch-zen-btn{border-color:var(--gold,#d4a84a);background:rgba(212,168,74,.18)}
</style>
<script id="ch-zen-js">
/* CH_ZEN — dugme + kalici tercih */
try{(function(){
  function apply(on){
    try{ document.body.classList.toggle("ch-zen", !!on); }catch(e){}
    try{ var b=document.getElementById("ch-zen-btn"); if(b) b.title=on?"Normal görünüm":"Sadece sohbet"; }catch(e){}
  }
  function saved(){ try{ return localStorage.getItem("ch_zen")==="1"; }catch(e){ return false; } }
  function make(){
    if(document.getElementById("ch-zen-btn")) return;
    if(!document.body) return;
    var b=document.createElement("button");
    b.id="ch-zen-btn"; b.type="button"; b.setAttribute("data-noi18n","1");
    b.textContent="🧘"; b.title="Sadece sohbet";
    b.addEventListener("click",function(){
      var on=!document.body.classList.contains("ch-zen");
      try{ localStorage.setItem("ch_zen", on?"1":"0"); }catch(e){}
      apply(on);
    });
    document.body.appendChild(b);
    apply(saved());
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",make); else make();
  var n=0, iv=setInterval(function(){ make(); if(++n>10) clearInterval(iv); },800);
})();}catch(e){}
</script>
HTML;

$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok — index degistirilmedi.\n");
$src = substr($src,0,$pos).$bundle."\n".substr($src,$pos);
echo "  ✓ zen dugmesi (sol ust) + sadece-chat gorunumu eklendi\n";

$tmp = tempnam(sys_get_temp_dir(),'zn').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-zen-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_ZEN uygulandi. Sol ustteki 🧘 dugmesi: tek tikla sadece chat,\n";
echo "   tekrar tikla her sey geri. Tercih kalici.\n";
