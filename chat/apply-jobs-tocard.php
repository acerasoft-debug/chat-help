<?php
/**
 * ChatHelp — apply-jobs-tocard (CH_JOBS_TOCARD)
 *  "Job finden & bewerben" sol menuden kaldirilir, ana sayfada bir KART olur.
 *  Alttaki sistem (pnl-jobs, arama, basvuru mektubu vb.) TAMAMEN AYNI kalir —
 *  sadece erisim yolu degisir (nav yerine kart). Modul-Zentrale'deki
 *  Jobs ac/kapa anahtarina da (ch_mods.jobs) uyumlu calisir.
 * KULLANIM: pull-updates.php?files=apply-jobs-tocard.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-jobs-tocard BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_JOBS_TOCARD') !== false) exit("Zaten ekli (CH_JOBS_TOCARD).\n");
file_put_contents($file . '.bak-jobstocard-' . date('Ymd-His'), $src);

$bundle = <<<'JCHTML'
<script id="ch-jobs-tocard">
/* CH_JOBS_TOCARD — Jobsuche: nav yerine ana sayfa karti (ayni pnl-jobs sistemi) */
try{(function(){
  function UIL(){ try{ return (window.chUIL?window.chUIL():(localStorage.getItem("ch_uilang")||"de")); }catch(e){ return "de"; } }
  function esc(s){ return String(s==null?"":s).replace(/[&<>"]/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];}); }
  var T={de:{t:"Job finden & bewerben",s:"Echte Arbeitgeber in Ihrer Region"},
    tr:{t:"İş Bul & Başvur",s:"Bölgenizdeki gerçek işverenler"},
    en:{t:"Find a job & apply",s:"Real employers in your area"},
    fr:{t:"Trouver un emploi",s:"De vrais employeurs dans votre région"},
    es:{t:"Buscar empleo",s:"Empresas reales de su zona"},
    it:{t:"Trova lavoro",s:"Datori di lavoro reali nella tua zona"}};
  function L(){ return T[UIL()]||T.en; }
  function mods(){ try{ return JSON.parse(localStorage.getItem("ch_mods")||"{}"); }catch(e){ return {}; } }
  function modOn(k){ var m=mods(); return m[k]===undefined?true:!!m[k]; }

  function hideNav(){
    try{ var n=document.getElementById("nav-jobs"); if(n) n.style.display="none"; }catch(e){}
  }
  function addCard(){
    try{
      var on=modOn("jobs");
      var ex=document.getElementById("ch-jobs-card");
      if(!on){ if(ex) ex.style.display="none"; return; }
      if(ex){ ex.style.display=""; return; }
      var anchor=document.querySelector(".wlc-quick")||document.querySelector(".wlc-quick-label");
      if(!anchor) return;
      var l=L();
      var c=document.createElement("div"); c.id="ch-jobs-card";
      c.style.cssText="display:flex;align-items:center;gap:12px;margin:12px 0;padding:13px 15px;border:1px solid rgba(212,168,74,.3);border-radius:14px;cursor:pointer;background:linear-gradient(135deg,rgba(212,168,74,.08),rgba(212,168,74,.02))";
      c.innerHTML='<div style="font-size:24px">💼</div><div style="flex:1"><div style="font-size:13.5px;font-weight:800;color:#fff">'+esc(l.t)+'</div>'
        +'<div style="font-size:11.5px;color:var(--t3,#a8a8d0);margin-top:2px">'+esc(l.s)+'</div></div><div style="font-size:16px;color:var(--gold,#d4a84a)">›</div>';
      c.addEventListener("click",function(){ try{ gP("jobs"); }catch(e){} });
      anchor.parentNode.insertBefore(c, anchor);
    }catch(e){}
  }
  function tick(){ hideNav(); addCard(); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",function(){ tick(); setInterval(tick,1200); });
  else { tick(); setInterval(tick,1200); }
})();}catch(e){}
</script>
JCHTML;

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
echo "✓ CH_JOBS_TOCARD uygulandi: nav-jobs gizlendi, ana sayfada 💼 kart eklendi (ayni pnl-jobs sistemi).\n";
