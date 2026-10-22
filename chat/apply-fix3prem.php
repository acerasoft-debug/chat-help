<?php
/**
 * ChatHelp — apply-fix3prem (CH_FIX3PREM) — 3 sikayet tek pakette:
 *  [A] Verlauf altinda son sohbetler: ch-verlauf-recent-js DAHA SAGLAM surumle
 *      DEGISTIRILIR (tolerant session tarama + saglam anchor: #nav-verlauf yoksa
 *      onclick'inde 'verlauf' gecen menu ogesi). Tek renderer -> savas yok.
 *  [B] Apple/iPhone butonu Google Play (#ch-qr-btn) TAM ALTINA eklenir (gumus
 *      stil, tikla -> "Safari > Teilen > Zum Home-Bildschirm" ipucu, 6 dil).
 *  [C] Meine Themen premium: #th-sec/.th-item VE #ch-topics-sec/.tt-item premium
 *      altin-gradyan kart + altin sol-cizgi + hover kalkma.
 *  1 preg_replace (A) + 1 eklemeli </body> blok (B+C CSS/JS). Lint + opcache.
 * KULLANIM: pull2.php?key=...&files=apply-fix3prem.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fix3prem BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_FIX3PREM')!==false) exit("Zaten ekli (CH_FIX3PREM).\n");

/* ── [A] Verlauf renderer'i saglam surumle degistir ── */
$newjs = <<<'JSBLOCK'
<script id="ch-verlauf-recent-js">
/* CH_VERLAUF_RECENT3 (v5, CH_FIX3PREM) — son 3 sohbet, tolerant + saglam anchor */
try{(function(){
  function findSessions(){
    var best=[];
    try{ var k=window.chSessKey; if(k){ var a=JSON.parse(localStorage.getItem(k)||'[]'); if(a&&a.length) return a; } }catch(e){}
    try{ var a2=JSON.parse(localStorage.getItem('ch_chat_sessions')||'[]'); if(a2&&a2.length) return a2; }catch(e){}
    try{ for(var i=0;i<localStorage.length;i++){ var kk=localStorage.key(i); var v=localStorage.getItem(kk);
      if(!v||v.charAt(0)!=='[') continue;
      try{ var arr=JSON.parse(v);
        if(Array.isArray(arr)&&arr.length&&arr[0]&&typeof arr[0]==='object'&&(('messages'in arr[0])||('msgs'in arr[0])||('title'in arr[0])||('updated'in arr[0]))){ if(arr.length>=best.length) best=arr; }
      }catch(e){}
    } }catch(e){}
    return best;
  }
  function anchor(){
    var n=document.getElementById('nav-verlauf'); if(n&&n.parentNode) return n;
    try{ var items=document.querySelectorAll('.sb-nav-item,[onclick]');
      for(var i=0;i<items.length;i++){ var oc=(items[i].getAttribute&&items[i].getAttribute('onclick'))||''; if(/verlauf/i.test(oc)&&items[i].parentNode) return items[i]; }
    }catch(e){}
    return null;
  }
  function loadOne(s){ try{ if(typeof window.chLoadSession==='function'){ window.chLoadSession(s); return; } }catch(e){} try{ if(typeof gP==='function') gP('verlauf'); }catch(e){} }
  function ago(t){ try{ var d=Math.floor((Date.now()-new Date(t).getTime())/60000); if(isNaN(d)||d<0) return ''; if(d<1)return 'jetzt'; if(d<60)return d+' Min'; var h=Math.floor(d/60); if(h<24)return h+' Std'; return Math.floor(h/24)+' T'; }catch(e){ return ''; } }
  function render(){
    try{
      var nav=anchor(); if(!nav) return;
      var ss=findSessions().slice().sort(function(a,b){ return (new Date(b.updated||b.t||0))-(new Date(a.updated||a.t||0)); }).slice(0,3);
      var sig=ss.map(function(s){ return (s.id||s.title||'')+':'+(s.updated||s.t||''); }).join('|');
      var old=document.getElementById('ch-verlauf-recent');
      if(old && old.getAttribute('data-sig')===sig && old.parentNode===nav.parentNode) return;
      if(old) old.remove();
      if(!ss.length) return;
      var box=document.createElement('div'); box.id='ch-verlauf-recent'; box.className='ch-vr-recent'; box.setAttribute('data-sig',sig);
      ss.forEach(function(s){
        var it=document.createElement('div'); it.className='ch-vr-item';
        var tt=(s.title||(s.messages&&s.messages[0]&&String(s.messages[0].content||'').slice(0,44))||(s.msgs&&s.msgs[0]&&String(s.msgs[0].content||s.msgs[0].text||'').slice(0,44))||'Chat');
        var t=ago(s.updated||s.t);
        it.innerHTML='<span class="ch-vr-dot"></span><span class="ch-vr-t"></span>'+(t?'<span class="ch-vr-a"></span>':'');
        it.querySelector('.ch-vr-t').textContent=tt; if(t) it.querySelector('.ch-vr-a').textContent=t;
        it.title=tt;
        it.addEventListener('click',function(ev){ ev.stopPropagation(); loadOne(s); });
        box.appendChild(it);
      });
      nav.parentNode.insertBefore(box, nav.nextSibling);
    }catch(e){}
  }
  render(); try{ setInterval(render,1500); }catch(e){}
})();}catch(e){}
</script>
JSBLOCK;
$pat='~<script id="ch-verlauf-recent-js">.*?</script>~s';
$rn=preg_match($pat,$src);
if($rn===1){ $src=preg_replace($pat,$newjs,$src,1); echo "  ✓ [A] Verlauf renderer saglam surumle degistirildi\n"; }
else { echo "  ! [A] ch-verlauf-recent-js ($rn) bulunamadi — eklenerek devam\n"; $src=str_ireplace('</body>',$newjs."\n</body>",$src); }

/* ── [B]+[C] CSS/JS blok ── */
$block = <<<'HTMLBLOCK'
<style id="ch-fix3prem-css">
/* [C] Meine Themen premium — iki sistem de */
#th-sec,#ch-topics-sec{margin:10px 14px 16px!important;padding:16px!important;border:1px solid rgba(212,168,74,.34)!important;border-radius:18px!important;background:linear-gradient(160deg,rgba(212,168,74,.10),rgba(212,168,74,.02))!important;box-shadow:0 10px 30px rgba(0,0,0,.28)!important}
#th-sec .th-h,#ch-topics-sec .tt-h{font-size:14px!important;font-weight:800!important;color:var(--gold,#e9c46a)!important;letter-spacing:.2px;margin-bottom:12px!important}
.th-item,.tt-item{position:relative!important;overflow:hidden!important;padding:13px 14px 13px 17px!important;border:1px solid rgba(212,168,74,.18)!important;border-radius:14px!important;margin-bottom:10px!important;background:linear-gradient(160deg,rgba(255,255,255,.05),rgba(255,255,255,.015))!important;box-shadow:0 4px 14px rgba(0,0,0,.22)!important;transition:transform .15s ease,border-color .15s ease,box-shadow .15s ease!important}
.th-item::before,.tt-item::before{content:"";position:absolute;left:0;top:0;bottom:0;width:3px;background:linear-gradient(180deg,#e8c877,#d4a84a)}
.th-item:hover,.tt-item:hover{transform:translateY(-2px);border-color:rgba(212,168,74,.5)!important;box-shadow:0 10px 26px rgba(0,0,0,.34)!important}
.th-item .th-t,.tt-item .tt-t{font-weight:800!important;color:#f4f4fa!important}
.tt-item .tt-m{color:#a9a9c0!important}
#th-sec .th-empty,#ch-topics-sec .tt-empty{font-size:12.5px!important;color:#a9a9c0!important;line-height:1.5}
/* [A] Verlauf recent premium */
.ch-vr-recent{margin:3px 0 10px;display:flex;flex-direction:column;gap:2px}
.ch-vr-item{display:flex;align-items:center;gap:8px;font-size:12.5px;color:#cfd4e8;padding:8px 10px 8px 12px;margin-left:8px;border-left:2px solid rgba(212,168,74,.3);border-radius:0 9px 9px 0;cursor:pointer;transition:background-color .15s,color .15s,border-color .15s}
.ch-vr-item .ch-vr-dot{width:6px;height:6px;border-radius:50%;background:var(--gold,#d4a84a);flex:0 0 auto;opacity:.75}
.ch-vr-item .ch-vr-t{flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ch-vr-item .ch-vr-a{font-size:10.5px;color:#8a90ab;flex-shrink:0}
.ch-vr-item:hover{background:rgba(212,168,74,.1);color:#fff;border-left-color:var(--gold,#d4a84a)}
/* [B] Apple button */
#ch-apple-btn2{display:block;text-align:center;background:linear-gradient(135deg,#33333c,#45454f);color:#fff;border:1px solid rgba(255,255,255,.18);border-radius:9px;padding:9px;font-size:12.5px;font-weight:800;text-decoration:none;margin-top:8px;cursor:pointer}
#ch-apple-btn2:hover{filter:brightness(1.1)}
#ch-apple-hint{margin-top:8px;font-size:11px;color:#c8c8e0;line-height:1.5;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:9px;padding:9px;display:none}
#ch-apple-hint.on{display:block}
</style>
<script id="ch-fix3prem-js">
/* [B] Apple/iPhone butonu Google Play'in altina */
try{(function(){
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2);}catch(e){return 'de';} }
  var APPLE='<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" style="vertical-align:-2px;margin-right:6px"><path d="M17.05 12.04c-.03-2.6 2.12-3.85 2.22-3.91-1.21-1.77-3.09-2.02-3.76-2.05-1.6-.16-3.12.94-3.93.94-.81 0-2.06-.92-3.39-.9-1.74.03-3.35 1.01-4.25 2.57-1.81 3.15-.46 7.81 1.3 10.37.86 1.25 1.89 2.66 3.24 2.61 1.3-.05 1.79-.84 3.36-.84 1.57 0 2.01.84 3.39.81 1.4-.03 2.28-1.28 3.14-2.54.99-1.46 1.4-2.87 1.42-2.94-.03-.01-2.72-1.05-2.75-4.16zM14.6 4.6c.71-.87 1.19-2.07 1.06-3.27-1.02.04-2.26.68-3 1.54-.66.76-1.24 1.98-1.08 3.15 1.14.09 2.31-.58 3.02-1.42z"/></svg>';
  var L={
    de:{b:'iPhone — Zum Home‑Bildschirm', h:'In <b>Safari</b> öffnen → unten <b>Teilen</b> ⬆️ → <b>„Zum Home‑Bildschirm"</b> tippen. Fertig — die App liegt dann auf dem iPhone.'},
    tr:{b:'iPhone — Ana Ekrana Ekle', h:'<b>Safari</b>\'de aç → altta <b>Paylaş</b> ⬆️ → <b>„Ana Ekrana Ekle"</b>. Uygulama iPhone\'a gelir.'},
    en:{b:'iPhone — Add to Home Screen', h:'Open in <b>Safari</b> → <b>Share</b> ⬆️ → <b>“Add to Home Screen”</b>. Done — the app lands on your iPhone.'},
    fr:{b:'iPhone — Écran d\'accueil', h:'Dans <b>Safari</b> → <b>Partager</b> ⬆️ → <b>« Sur l\'écran d\'accueil »</b>.'},
    es:{b:'iPhone — Pantalla de inicio', h:'En <b>Safari</b> → <b>Compartir</b> ⬆️ → <b>«Añadir a inicio»</b>.'},
    it:{b:'iPhone — Schermata Home', h:'In <b>Safari</b> → <b>Condividi</b> ⬆️ → <b>«Aggiungi a Home»</b>.'}
  };
  function inject(){
    try{
      var t=L[lang()]||L.de;
      var gp=document.getElementById('ch-qr-btn');
      if(gp && gp.parentNode && !document.getElementById('ch-apple-btn2')){
        var a=document.createElement('a'); a.id='ch-apple-btn2'; a.href='javascript:void(0)'; a.innerHTML=APPLE+t.b;
        var hint=document.createElement('div'); hint.id='ch-apple-hint'; hint.innerHTML=t.h;
        a.addEventListener('click',function(ev){ ev.preventDefault(); hint.classList.toggle('on'); });
        gp.parentNode.insertBefore(a, gp.nextSibling);
        a.parentNode.insertBefore(hint, a.nextSibling);
      }
    }catch(e){}
  }
  inject(); try{ setInterval(inject,900); }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;
$pos=strripos($src,'</body>');
if($pos===false) exit("</body> bulunamadi\n");
$src=substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp=tempnam(sys_get_temp_dir(),'f3').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-fix3prem-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_FIX3PREM')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ [B] Apple butonu Google Play altina (6 dil ipucu)\n";
echo "  ✓ [C] Meine Themen premium (#th-sec/.th-item + #ch-topics-sec/.tt-item)\n";
echo "\n  ✓ CH_FIX3PREM diskte (".strlen($chk)." B). Hard-refresh (app'i kapat-ac).\n";
echo "NOT: Verlauf son 3 sohbet SADECE kayitli sohbet varsa gorunur (bir kez sohbet et).\n";
