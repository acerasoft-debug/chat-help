<?php
/**
 * ChatHelp — Fall premium tasarım + her an foto/dosya ekleme (additive)
 * --------------------------------------------------------------------
 *  • #fall-body içinde, sohbet ederken HER AN 📎 foto/PDF eklenebilir
 *  • Eklenenler chip olarak görünür, kaldırılabilir, fall switch'te temizlenir
 *  • fall_chat + fall_solve isteklerine 'photos' otomatik eklenir (fetch sarmalayıcı)
 *  • Fall paneline premium görünüm (altın aksan, yumuşak kartlar, şık input)
 * render() her çizimde input'u yeniden kuruyor -> gözlemci ile tekrar enjekte edilir.
 *
 * KULLANIM: chat-help.com/chat/apply-fall-premium.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src   = file_get_contents($file);
$start = $src;
file_put_contents($file . '.bak-fallprem-' . date('Ymd-His'), $src);

if (strpos($src, 'ch-fall-premium') !== false) { exit("Zaten ekli (ch-fall-premium).\n"); }

$bundle = <<<'HTML'
<style id="ch-fall-premium">
/* ── Fall premium görünüm ── */
#pnl-fall .pnl-topbar-title{font-weight:800;letter-spacing:.2px}
#fall-body{padding-bottom:10px}
#fall-body .fall-card,#fall-body .card,#fall-body [class*="card"]{border-radius:16px!important}
#fall-input{border-radius:14px!important;transition:border-color .15s,box-shadow .15s}
#fall-input:focus{border-color:var(--gold,#d4a84a)!important;box-shadow:0 0 0 3px rgba(212,168,74,.13)!important;outline:none}
#fall-body button{border-radius:12px!important}
/* ── Ek dosya barı ── */
#ch-fall-attach{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:8px 0 4px;padding:9px 11px;background:var(--s2,#26262c);border:1px solid var(--bdr,rgba(255,255,255,.09));border-radius:13px}
.cfa-btn{display:inline-flex;align-items:center;gap:7px;cursor:pointer;font-size:13px;font-weight:700;color:var(--gold,#d4a84a);padding:8px 13px;border:1.5px dashed rgba(212,168,74,.5);border-radius:11px;background:rgba(212,168,74,.07);transition:.15s;white-space:nowrap}
.cfa-btn:hover{background:rgba(212,168,74,.15)}
.cfa-btn input{display:none}
.cfa-hint{font-size:11px;color:var(--t4,#8e8e9c)}
#ch-fall-chips{display:flex;gap:6px;flex-wrap:wrap}
.cfa-chip{display:inline-flex;align-items:center;gap:7px;background:var(--s3,#313137);border:1px solid var(--bdr,rgba(255,255,255,.1));border-radius:20px;padding:5px 6px 5px 11px;font-size:12px;color:var(--t3,#cfcfdd);max-width:190px}
.cfa-chip .ic{font-size:13px}
.cfa-chip .nm{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cfa-chip .x{cursor:pointer;color:#d05a4e;font-weight:800;padding:0 4px;line-height:1}
.cfa-chip .x:hover{color:#e8675a}
</style>
<script id="ch-fall-premium-js">
(function(){
  'use strict';
  window._chFallFiles = window._chFallFiles || [];
  function clean(arr){ return arr.map(function(f){return {data:f.data,type:f.type,name:f.name};}); }

  function renderChips(){
    var box=document.getElementById('ch-fall-chips'); if(!box) return;
    box.innerHTML='';
    window._chFallFiles.forEach(function(f,i){
      var c=document.createElement('span'); c.className='cfa-chip';
      var ic=(f.type==='application/pdf')?'📄':'🖼️';
      c.innerHTML='<span class="ic">'+ic+'</span><span class="nm"></span><span class="x">✕</span>';
      c.querySelector('.nm').textContent=f.name||'Datei';
      c.querySelector('.x').onclick=function(){ window._chFallFiles.splice(i,1); renderChips(); };
      box.appendChild(c);
    });
  }
  function onPick(inp){
    var files=Array.prototype.slice.call(inp.files||[]);
    files.forEach(function(file){
      if(file.size>6*1024*1024){ alert('„'+file.name+'" ist zu groß (max. 6 MB).'); return; }
      var ok=/^image\//.test(file.type)||file.type==='application/pdf';
      if(!ok){ alert('Nur Bilder oder PDF.'); return; }
      var fr=new FileReader();
      fr.onload=function(e){
        window._chFallFiles.push({data:(''+e.target.result).split(',')[1],type:file.type,name:file.name});
        renderChips();
      };
      fr.readAsDataURL(file);
    });
    inp.value='';
  }
  function injectAttach(){
    var ip=document.getElementById('fall-input'); if(!ip) return;
    // input'un kapsayıcısını bul
    var host=ip.parentNode; if(!host) return;
    if(host.querySelector('#ch-fall-attach')||document.getElementById('ch-fall-attach')) { renderChips(); return; }
    var bar=document.createElement('div'); bar.id='ch-fall-attach';
    bar.innerHTML='<label class="cfa-btn"><input type="file" accept="image/*,application/pdf" multiple> 📎 Foto / Datei</label>'
      +'<span class="cfa-hint">Bescheid, Brief, Beleg … wird von der KI gelesen</span>'
      +'<div id="ch-fall-chips"></div>';
    bar.querySelector('input').addEventListener('change',function(){ onPick(this); });
    host.insertBefore(bar, ip);   // input'un hemen üstüne
    renderChips();
  }

  /* fall switch / yeni fall -> ekleri temizle */
  ['openFall','fallNew'].forEach(function(fn){
    var t=setInterval(function(){
      if(typeof window[fn]==='function' && !window[fn]._chWrapped){
        var orig=window[fn];
        window[fn]=function(){ window._chFallFiles=[]; var r=orig.apply(this,arguments); setTimeout(injectAttach,150); return r; };
        window[fn]._chWrapped=true; clearInterval(t);
      }
    },500);
  });

  /* fetch sarmalayıcı: fall_chat (yeni dosyalar) + fall_solve (tüm dosyalar) */
  var of=window.fetch.bind(window);
  window.fetch=function(input,init){
    init=init||{};
    var url=(typeof input==='string')?input:((input&&input.url)||'');
    if(/fall-api\.php/.test(url) && init.body && typeof init.body==='string'){
      try{
        var b=JSON.parse(init.body);
        if(b && b.action==='fall_solve'){ b.photos=clean(window._chFallFiles); init.body=JSON.stringify(b); }
        else if(b && b.action==='fall_chat'){
          var nu=window._chFallFiles.filter(function(f){return !f._sent;});
          if(nu.length){ b.photos=clean(nu); nu.forEach(function(f){f._sent=true;}); init.body=JSON.stringify(b); }
        }
      }catch(e){}
    }
    return of(input,init);
  };

  function boot(){ injectAttach(); setInterval(injectAttach,1000); }
  if(document.body) boot(); else document.addEventListener('DOMContentLoaded',boot);
})();
</script>
HTML;

$n = 0;
$src = preg_replace('/<\/body>/', $bundle . "\n</body>", $src, 1, $n);
$changed = ($src !== $start);
if ($changed) file_put_contents($file, $src);

echo "ChatHelp — Fall premium + dosya ekleme raporu\n=============================================\n\n";
echo ($n > 0 ? "  ✓ Fall premium katmanı eklendi  →  $n\n" : "  ✗ </body> bulunamadı! HABER VER.\n");
echo "\n" . ($changed ? "DURUM: index.php güncellendi. ✅\n" : "DURUM: Değişiklik yok.\n");
echo "\nGEREKLİ: güncel fall-api.php yüklü olmalı (PDF + sohbette foto okuma).\n";
echo "SONRA: opcache-reset.php. SİL: rm apply-fall-premium.php\n";
echo "Test: Fall eröffnen -> sohbet input'unun üstünde '📎 Foto / Datei' -> ekle -> chip görünür -> Fall lösen.\n";
