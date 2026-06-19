<?php
/**
 * ChatHelp — Ekleme v2: dilekçe başına TEK yükleme + Fall premium (additive)
 * -------------------------------------------------------------------------
 *  • Dilekçe formunda TEK premium yükleme alanı (her kutu altında DEĞİL).
 *    Yüklenen belge: genDoc Vision ile dilekçede kullanılır + (autofill ile) kutular dolar.
 *  • Fall / "Anliegen frei beschreiben" / ana sohbet: girişin yanında küçük premium "+".
 *  • Fall sayfaları premium: butonlar altın/ghost, premium giriş, premium topbar.
 *  • Eski dağınık ekleme arayüzleri (.ch-q-photo, #ch-fall-attach, .chp-wrap) gizlenir.
 *
 * KULLANIM: chat-help.com/chat/apply-attach-plus.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src   = file_get_contents($file);
$start = $src;
file_put_contents($file . '.bak-attach2-' . date('Ymd-His'), $src);

if (strpos($src, 'ch-attach-v2') !== false) { exit("Zaten ekli (ch-attach-v2).\n"); }

$bundle = <<<'HTML'
<style id="ch-attach-v2-css">
/* eski dağınık ekleme arayüzlerini gizle */
.ch-q-photo,#ch-fall-attach,.chp-wrap,.chp{display:none!important}
/* sonradan eklenen genel geri çipini kaldır (her sayfanın kendi geri butonu var) */
#ch-back-chip{display:none!important}
/* ── Dilekçe: TEK premium yükleme alanı ── */
.cha2-doc{margin:2px 0 16px;padding:14px 16px;border:1.5px dashed rgba(212,168,74,.45);border-radius:16px;background:linear-gradient(135deg,rgba(212,168,74,.09),rgba(212,168,74,.02))}
.cha2-doc-btn{display:flex;align-items:center;gap:12px;cursor:pointer;color:var(--gold,#d4a84a);font-weight:800;font-size:14.5px}
.cha2-doc-btn input{display:none}
.cha2-doc-btn .tx .sub{display:block;font-weight:500;font-size:11.5px;color:var(--t4,#8e8e9c);margin-top:2px}
.cha2-ic{width:40px;height:40px;border-radius:12px;background:rgba(212,168,74,.15);display:flex;align-items:center;justify-content:center;font-size:19px;flex:0 0 auto}
.cha2-chips{display:flex;gap:6px;flex-wrap:wrap;margin-top:11px}
.cha2-chip{display:inline-flex;align-items:center;gap:6px;background:var(--s3,#313137);border:1px solid var(--bdr,rgba(255,255,255,.1));border-radius:18px;padding:4px 6px 4px 11px;font-size:11.5px;color:var(--t3,#cfcfdd);max-width:180px}
.cha2-chip .nm{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cha2-chip .x{cursor:pointer;color:#d05a4e;font-weight:800;padding:0 3px;line-height:1}
.cha2-chip .x:hover{color:#e8675a}
/* ── küçük "+" (fall/intake/main) ── */
.cha2-plus{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:50%;border:1.5px solid rgba(212,168,74,.55);background:rgba(212,168,74,.09);color:var(--gold,#d4a84a);cursor:pointer;font-size:20px;font-weight:700;line-height:1;flex:0 0 auto;transition:.15s;vertical-align:middle}
.cha2-plus:hover{background:var(--gold,#d4a84a);color:#1b1b1e;box-shadow:0 0 0 4px rgba(212,168,74,.15)}
.cha2-plus input{display:none}
.cha2-mini{display:inline-flex;gap:5px;flex-wrap:wrap;vertical-align:middle;margin-left:6px}
.cha2-mchip{display:inline-flex;align-items:center;gap:4px;background:var(--s3,#313137);border:1px solid var(--bdr,rgba(255,255,255,.1));border-radius:14px;padding:2px 5px 2px 8px;font-size:10.5px;color:var(--t3,#cfcfdd);max-width:120px}
.cha2-mchip .nm{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cha2-mchip .x{cursor:pointer;color:#d05a4e;font-weight:800}
/* ── Fall premium ── */
#fall-body{padding:14px 14px 18px}
.cha2-prim{background:linear-gradient(135deg,#d4a84a,#b8862f)!important;color:#1b1b1e!important;border:none!important;border-radius:13px!important;font-weight:800!important;box-shadow:0 6px 18px rgba(212,168,74,.22)!important;transition:.15s!important}
.cha2-prim:hover{filter:brightness(1.07)}
.cha2-ghost{background:var(--s2,#26262c)!important;color:#e7e7ef!important;border:1px solid var(--bdr,rgba(255,255,255,.12))!important;border-radius:12px!important;font-weight:700!important;transition:.15s!important}
.cha2-ghost:hover{border-color:var(--gold,#d4a84a)!important;color:var(--gold,#d4a84a)!important}
#fall-input{border-radius:14px!important;border:1px solid var(--bdr,rgba(255,255,255,.12))!important;transition:.15s}
#fall-input:focus{border-color:var(--gold,#d4a84a)!important;box-shadow:0 0 0 3px rgba(212,168,74,.13)!important;outline:none}
</style>
<script id="ch-attach-v2">
(function(){
  'use strict';
  window._chDocFiles  = window._chDocFiles  || [];
  window._chFallFiles = window._chFallFiles || [];
  window._chIntakeFiles = window._chIntakeFiles || [];

  function readFile(file, cb){
    if(file.size>6*1024*1024){ alert('„'+file.name+'" ist zu groß (max. 6 MB).'); return; }
    var ok=/^image\//.test(file.type)||file.type==='application/pdf';
    if(!ok){ alert('Nur Bilder oder PDF erlaubt.'); return; }
    var fr=new FileReader();
    fr.onload=function(e){ cb({data:(''+e.target.result).split(',')[1],type:file.type,name:file.name}); };
    fr.readAsDataURL(file);
  }
  function syncDocPhotos(){
    window._chPhotos={}; var i=0;
    window._chDocFiles.forEach(function(f){ window._chPhotos['p'+(i++)]={data:f.data,type:f.type,name:f.name}; });
  }

  /* ── 1) Dilekçe: TEK yükleme alanı ── */
  function decorateDocForm(){
    var first=document.querySelector('[data-k]'); if(!first) return;
    if(document.querySelector('.cha2-doc')) { drawDocChips(); return; }
    var fi=first.closest('.fi')||first; var parent=fi.parentNode; if(!parent) return;
    var box=document.createElement('div'); box.className='cha2-doc';
    box.innerHTML='<label class="cha2-doc-btn"><span class="cha2-ic">📎</span>'
      +'<span class="tx">Dokument oder Foto hochladen'
      +'<span class="sub">Bescheid, Brief, Beleg … Daten werden automatisch übernommen & im Schreiben verwendet</span></span>'
      +'<input type="file" accept="image/*,application/pdf" multiple></label>'
      +'<div class="cha2-chips"></div>';
    box.querySelector('input').addEventListener('change',function(){
      Array.prototype.slice.call(this.files||[]).forEach(function(f){ readFile(f,function(o){ window._chDocFiles.push(o); syncDocPhotos(); drawDocChips(); }); });
      this.value='';
    });
    parent.insertBefore(box, fi);
    drawDocChips();
  }
  function drawDocChips(){
    var box=document.querySelector('.cha2-doc .cha2-chips'); if(!box) return;
    box.innerHTML='';
    window._chDocFiles.forEach(function(f,i){
      var c=document.createElement('span'); c.className='cha2-chip';
      c.innerHTML='<span>'+(f.type==='application/pdf'?'📄':'🖼️')+'</span><span class="nm"></span><span class="x">✕</span>';
      c.querySelector('.nm').textContent=f.name||'Datei';
      c.querySelector('.x').onclick=function(){ window._chDocFiles.splice(i,1); syncDocPhotos(); drawDocChips(); };
      box.appendChild(c);
    });
  }

  /* ── 2) küçük "+" (store dizisi) ── */
  function smallPlus(host, ref, getList){
    if(!host || host.querySelector('.cha2-plus[data-ref="'+ref+'"]')) return;
    var lab=document.createElement('label'); lab.className='cha2-plus'; lab.setAttribute('data-ref',ref); lab.title='Foto / Datei';
    lab.innerHTML='+<input type="file" accept="image/*,application/pdf" multiple>';
    var mini=document.createElement('span'); mini.className='cha2-mini'; mini.setAttribute('data-ref',ref);
    function draw(){ mini.innerHTML=''; getList().forEach(function(f,i){ var c=document.createElement('span'); c.className='cha2-mchip';
      c.innerHTML='<span>'+(f.type==='application/pdf'?'📄':'🖼️')+'</span><span class="nm"></span><span class="x">✕</span>';
      c.querySelector('.nm').textContent=f.name||'Datei'; c.querySelector('.x').onclick=function(){ getList().splice(i,1); draw(); }; mini.appendChild(c); }); }
    lab.querySelector('input').addEventListener('change',function(){
      Array.prototype.slice.call(this.files||[]).forEach(function(f){ readFile(f,function(o){ getList().push(o); draw(); }); }); this.value='';
    });
    host.insertBefore(mini, host.firstChild); host.insertBefore(lab, host.firstChild); draw();
  }
  function decorateFall(){ var ip=document.getElementById('fall-input'); if(ip&&ip.parentNode) smallPlus(ip.parentNode,'fall',function(){return window._chFallFiles;}); }
  function decorateIntake(){ var ip=document.getElementById('ch-iv-input'); if(ip&&ip.parentNode) smallPlus(ip.parentNode,'intake',function(){return window._chIntakeFiles;}); }
  function decorateMain(){
    var ip=document.getElementById('inp'); if(!ip) return;
    var host=document.querySelector('.ia-btns')||ip.parentNode; if(!host) return;
    if(host.querySelector('.cha2-plus[data-ref="main"]')) return;
    var lab=document.createElement('label'); lab.className='cha2-plus'; lab.setAttribute('data-ref','main'); lab.title='Foto / Datei'; lab.textContent='+';
    lab.onclick=function(){ var ins=document.querySelectorAll('input[type=file]'),ex=null;
      for(var i=0;i<ins.length;i++){ if(!ins[i].closest('.cha2-plus')&&!ins[i].closest('.cha2-doc')&&/image/.test(ins[i].accept||'')){ ex=ins[i]; break; } }
      if(ex) ex.click(); };
    host.insertBefore(lab, host.firstChild);
  }

  /* ── 3) Fall premium: butonları sınıflandır ── */
  function fallPremium(){
    var body=document.getElementById('fall-body'); if(!body) return;
    body.querySelectorAll('button').forEach(function(b){
      if(b._cha2) return; b._cha2=true;
      var t=(b.textContent||'').toLowerCase();
      if(/(lösen|losen|erstellen|eröffnen|eroffnen|analy|neuer|\bneu\b|anmelden|upgrade|jetzt)/.test(t)) b.classList.add('cha2-prim');
      else b.classList.add('cha2-ghost');
    });
  }

  /* ── form açılınca yükleme alanını (kalıcı dosyalarla) yeniden çiz ── */
  function wrapReset(){
    if(typeof window.showQuestions==='function' && !window.showQuestions._cha2){
      var _sq=window.showQuestions;
      // _chDocFiles SIFIRLANMAZ: ana sayfada çekilen foto forma taşınır
      window.showQuestions=function(){ var r=_sq.apply(this,arguments);
        setTimeout(decorateDocForm,200); setTimeout(decorateDocForm,700); return r; };
      window.showQuestions._cha2=true;
    }
    if(typeof window.chOpenIntake==='function' && !window.chOpenIntake._cha2){
      var _oi=window.chOpenIntake; window.chOpenIntake=function(){ window._chIntakeFiles=[]; return _oi.apply(this,arguments); }; window.chOpenIntake._cha2=true;
    }
  }

  /* ── Ana sayfa "fotografieren" foto'sunu da belgeye taşı (capture) ── */
  document.addEventListener('change', function(ev){
    var inp=ev.target;
    if(!inp || inp.type!=='file' || !inp.files || !inp.files.length) return;
    if(inp.closest('.cha2-doc') || inp.closest('.cha2-plus')) return;   // benim inputlarım zaten ekleniyor
    if(!/image\//.test(inp.accept||'') && !/pdf/.test(inp.accept||'')) return;
    Array.prototype.slice.call(inp.files).forEach(function(f){
      readFile(f, function(o){ window._chDocFiles.push(o); syncDocPhotos(); drawDocChips(); });
    });
  }, true);

  /* ── intake isteklerine photos ── */
  var of=window.fetch.bind(window);
  window.fetch=function(input,init){
    init=init||{};
    var url=(typeof input==='string')?input:((input&&input.url)||'');
    if(/intake-api\.php/.test(url) && init.body && typeof init.body==='string'){
      try{ var b=JSON.parse(init.body);
        if(b&&(b.action==='intake_solve'||b.action==='intake_chat')&&window._chIntakeFiles.length){
          b.photos=window._chIntakeFiles.map(function(f){return {data:f.data,type:f.type,name:f.name};}); init.body=JSON.stringify(b);
        } }catch(e){}
    }
    return of(input,init);
  };

  function tick(){ wrapReset(); decorateDocForm(); decorateFall(); decorateIntake(); decorateMain(); fallPremium(); }
  function boot(){ tick(); setInterval(tick,1000); }
  if(document.body) boot(); else document.addEventListener('DOMContentLoaded',boot);
})();
</script>
HTML;

$n = 0;
$src = preg_replace('/<\/body>/', $bundle . "\n</body>", $src, 1, $n);
$changed = ($src !== $start);
if ($changed) file_put_contents($file, $src);

echo "ChatHelp — Ekleme v2 + Fall premium raporu\n==========================================\n\n";
echo ($n > 0 ? "  ✓ Ekleme v2 katmanı eklendi  →  $n\n" : "  ✗ </body> bulunamadı! HABER VER.\n");
echo "\n" . ($changed ? "DURUM: index.php güncellendi. ✅\n" : "DURUM: Değişiklik yok.\n");
echo "\nGEREKLİ: apply-autofill.php de yüklü olmalı (yüklenen belge kutuları doldursun).\n";
echo "SONRA: opcache-reset.php. SİL: rm apply-attach-plus.php\n";
echo "Test: dilekçe -> formda ÜSTTE tek 'Dokument hochladen' -> Bescheid yükle -> kutular dolar + dilekçede kullanılır.\n";
echo "      Fall -> butonlar altın/premium; input yanında küçük '+'.\n";
