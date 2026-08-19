<?php
/**
 * ChatHelp — apply-emsal-hub (CH_EMSAL_HUB) — Emsal (Präzedenzfälle & Urteile)
 *  segmentini SUREKLI menuden cikarir, Modul-Zentrale (hub) opt-in sistemine
 *  baglar:
 *   [1] Sol menudeki '⚖️ Präzedenzfälle' (nav-emsal) gorunurlugu artik
 *       ch_mods.emsal'e bagli. Varsayilan KAPALI -> kalici menuden kalkar.
 *   [2] Modul-Zentrale panelinde (#pnl-hub) yerel bir '⚖️ Präzedenzfälle &
 *       Urteile' karti: ＋ Arayüze taşı / − Kaldır + Aç →. Hub yeniden
 *       cizildiginde kart otomatik geri eklenir (re-inject).
 *  Tamamen EKLEMELI (hub ic koduna dokunmaz). node ✓ + harness ✓.
 * KULLANIM: pull2.php?key=...&files=apply-emsal-hub.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-emsal-hub BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_EMSAL_HUB')!==false) exit("Zaten ekli (CH_EMSAL_HUB).\n");
if (strpos($src,'CH_EMSAL')===false) exit("HATA: once CH_EMSAL gerekli.\n");

$block = <<<'HTMLBLOCK'
<script id="ch-emsal-hub-js">
/* CH_EMSAL_HUB — Emsal segmenti kalici menuden cikar, hub opt-in'e baglanir */
try{(function(){
  function UIL(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  function mods(){ try{ var m=JSON.parse(localStorage.getItem('ch_mods')||'{}'); return m&&typeof m==='object'?m:{}; }catch(e){ return {}; } }
  function setMod(k,v){ try{ var m=mods(); m[k]=v?1:0; localStorage.setItem('ch_mods',JSON.stringify(m)); }catch(e){} }
  function isOn(){ return mods().emsal===1; }
  /* varsayilan KAPALI: tanimsizsa bir kez 0 yaz (kalici menuden cikar) */
  try{ var m0=mods(); if(m0.emsal===undefined){ m0.emsal=0; localStorage.setItem('ch_mods',JSON.stringify(m0)); } }catch(e){}
  function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
  var L={
    n:{de:'Präzedenzfälle & Urteile',tr:'Emsal Kararlar',en:'Case-Law Research'},
    d:{de:'Echte Gerichtsurteile zu Ihrem Anliegen recherchieren (Pro/Elite) — ohne Halluzination, mit Quelle.',tr:'Konunuza dair gerçek mahkeme kararlarını araştır (Pro/Elite) — halüsinasyonsuz, kaynaklı.',en:'Research real court rulings for your case (Pro/Elite) — no hallucination, with sources.'},
    on:{de:'AKTIV',tr:'AKTİF',en:'ACTIVE'},off:{de:'INAKTIV',tr:'KAPALI',en:'OFF'},
    add:{de:'＋ Zur Oberfläche',tr:'＋ Arayüze taşı',en:'＋ Add to interface'},rem:{de:'− Entfernen',tr:'− Kaldır',en:'− Remove'},
    open:{de:'Öffnen →',tr:'Aç →',en:'Open →'}
  };
  function TT(o){ return o[UIL()]||o.en; }
  /* [1] sidebar nav-emsal gorunurlugu ch_mods.emsal'e bagli */
  function enforceNav(){ try{ var nv=document.getElementById('nav-emsal'); if(nv) nv.style.display=isOn()?'':'none'; }catch(e){} }
  /* [2] hub paneline Emsal karti (re-render sonrasi tekrar eklenir) */
  function renderCard(d){
    var act=isOn();
    d.innerHTML='<div style="display:flex;align-items:center;gap:11px;margin-bottom:10px"><div class="vst-ic" style="margin:0;width:46px;height:46px;font-size:23px">⚖️</div>'
      +'<div style="flex:1"><div class="vst-n" style="font-size:14px">'+esc(TT(L.n))+'</div></div>'
      +'<span style="font-size:9px;font-weight:800;letter-spacing:.6px;padding:3px 9px;border-radius:100px;'+(act?'color:#42df94;border:1px solid rgba(66,223,148,.45)':'color:var(--t4,#6d6d92);border:1px solid var(--bdr,rgba(255,255,255,.15))')+'">'+esc(act?TT(L.on):TT(L.off))+'</span></div>'
      +'<div class="vst-sn" style="flex:1;line-height:1.55;margin-bottom:12px">'+esc(TT(L.d))+'</div>'
      +'<div style="display:flex;gap:8px">'
      +'<button class="jb-mbtn'+(act?'':' p')+'" data-emsal-tg="1" style="flex:1">'+esc(act?TT(L.rem):TT(L.add))+'</button>'
      +(act?'<button class="jb-mbtn p" data-emsal-open="1">'+esc(TT(L.open))+'</button>':'')
      +'</div>';
    var tg=d.querySelector('[data-emsal-tg]'); if(tg) tg.onclick=function(){ setMod('emsal',!isOn()); enforceNav(); renderCard(d); };
    var op=d.querySelector('[data-emsal-open]'); if(op) op.onclick=function(){ try{ if(typeof window.chEmsal==='function') window.chEmsal(); }catch(e){} };
  }
  function injectCard(){
    try{
      var grid=document.querySelector('#pnl-hub .vst-grid'); if(!grid) return;
      if(document.getElementById('emsal-hub-card')) return; /* var: dokunma (state kart butonundan degisir) */
      var d=document.createElement('div'); d.className='vst-card'; d.id='emsal-hub-card';
      d.style.cssText='cursor:default;display:flex;flex-direction:column';
      grid.appendChild(d); renderCard(d);
    }catch(e){}
  }
  enforceNav();
  setInterval(function(){ enforceNav(); injectCard(); },1200);
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'eh').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-emsalhub-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_EMSAL_HUB')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_EMSAL_HUB diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Emsal (⚖️ Präzedenzfälle & Urteile) artik KALICI menuden kalkti\n";
echo "   (varsayilan kapali). Modul-Zentrale (🧩) panelinden acilinca sol menude\n";
echo "   gorunur; 'Aç →' ile dogrudan calisir. Kullanici istedigi zaman ac/kapa yapar.\n";
