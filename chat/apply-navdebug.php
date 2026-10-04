<?php
/**
 * ChatHelp — apply-navdebug (CH_NAVDEBUG) — GECICI TESHIS
 *  Sag alt kosede kucuk bir panel gosterir: nav-fris/rech/tresor/briefe
 *  DOM'da var mi, gorunur mu (computed display), ch_mods icerigi ne.
 *  Ekrana bakip bana yazman yeterli — devtools gerekmiyor.
 *  IS BITINCE: pull-updates.php?files=apply-navdebug-remove.php ile kaldirilacak.
 * KULLANIM: pull-updates.php?files=apply-navdebug.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-navdebug BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_NAVDEBUG') !== false) exit("Zaten ekli (CH_NAVDEBUG).\n");
file_put_contents($file . '.bak-navdebug-' . date('Ymd-His'), $src);

$bundle = <<<'NDHTML'
<script id="ch-navdebug">
/* CH_NAVDEBUG — GECICI teshis paneli */
try{(function(){
  function esc(s){ return String(s==null?"":s).replace(/[&<>"]/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];}); }
  function row(id){
    var el=document.getElementById(id);
    if(!el) return id+": YOK";
    var cs=window.getComputedStyle(el);
    return id+": VAR (display="+cs.display+", visibility="+cs.visibility+")";
  }
  function build(){
    var box=document.getElementById("ch-navdebug-box");
    if(!box){
      box=document.createElement("div"); box.id="ch-navdebug-box";
      box.style.cssText="position:fixed;left:8px;bottom:8px;z-index:99999;max-width:340px;background:#000;color:#0f0;font:11px monospace;padding:10px 12px;border-radius:8px;border:1px solid #0f0;white-space:pre-wrap;max-height:60vh;overflow:auto";
      document.body.appendChild(box);
    }
    var mods="?"; try{ mods=localStorage.getItem("ch_mods")||"(bos/yok)"; }catch(e){}
    var sbCount=0; try{ sbCount=document.querySelectorAll(".sb-nav-item, .sb-photo-btn").length; }catch(e){}
    var txt = "CH_NAVDEBUG\n"
      + row("nav-fris")+"\n"
      + row("nav-rech")+"\n"
      + row("nav-tresor")+"\n"
      + row("nav-briefe")+"\n"
      + row("nav-jobs")+"\n"
      + row("nav-cv")+"\n"
      + row("nav-vst")+"\n"
      + row("nav-hub")+"\n"
      + row("ch-hub-fab")+"\n"
      + "sb menu item sayisi: "+sbCount+"\n"
      + "ch_mods: "+esc(mods)+"\n"
      + "CH_MODPACK var mi: "+(typeof window.__CH_MP_TEST!=="undefined"?"?":"(kontrol JS'te)")+"\n";
    box.textContent = txt;
  }
  function tick(){ build(); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",function(){ tick(); setInterval(tick,1500); });
  else { tick(); setInterval(tick,1500); }
})();}catch(e){}
</script>
NDHTML;

$nn = 0;
$src = preg_replace('/<\/body>/', $bundle . "\n</body>", $src, 1, $nn);
if (!$nn) exit("HATA: </body> bulunamadi — yazilmadi.\n");

$tmp = tempnam(sys_get_temp_dir(), 'idx') . '.php';
file_put_contents($tmp, $src);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "LINT HATASI — index.php degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
file_put_contents($file, $src);
echo "✓ CH_NAVDEBUG uygulandi. Sag alt kosede yesil kutucuga bak, icerigini bana yaz.\n";
