<?php
/**
 * ChatHelp — Ana sayfa: premium akordeon + arama (kategoriler başlık altında katlı)
 *  • Eski kategori grid'i gizlenir; yerine premium akordeon
 *  • Üstte arama (tüm belgelerde anında) + "Beliebt" hızlı erişim
 *  • Inline (getDocPrice'tan sonra) -> CATS/DOCS kesin erişim
 * KULLANIM: chat-help.com/chat/apply-home-accordion.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src=file_get_contents($file); $start=$src;
file_put_contents($file . '.bak-acc-' . date('Ymd-His'), $src);
$rep=[];

/* ── 1) Premium CSS (</body> öncesi) ── */
if (strpos($src,'ch-acc-css')===false){
$css = <<<'HTML'
<style id="ch-acc-css">
.wcat-grid,.ch-cat-tg,.ch-vtr-sec{display:none!important}
.ch-acc{margin:16px 0 8px;text-align:left}
.ch-acc-search{display:flex;align-items:center;gap:11px;background:var(--s2,#26262c);border:1px solid var(--bdr,rgba(255,255,255,.1));border-radius:15px;padding:13px 16px;margin-bottom:16px;transition:border-color .18s,box-shadow .18s}
.ch-acc-search:focus-within{border-color:var(--gold,#d4a84a);box-shadow:0 0 0 3px rgba(212,168,74,.12)}
.ch-acc-search svg{width:18px;height:18px;stroke:var(--t4,#8e8e9c);flex:0 0 auto}
.ch-acc-search input{flex:1;background:none;border:none;outline:none;color:#fff;font-size:14.5px;font-family:inherit}
.ch-acc-search input::placeholder{color:var(--t4,#8e8e9c)}
.ch-acc-pop{margin-bottom:18px}
.ch-acc-pop-h{font-size:11px;font-weight:800;color:var(--gold,#d4a84a);letter-spacing:1px;margin-bottom:9px;text-transform:uppercase}
.ch-acc-pop-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px}
.ch-acc-pop-row{display:flex;align-items:center;gap:9px;padding:12px;background:linear-gradient(135deg,rgba(212,168,74,.09),rgba(212,168,74,.02));border:1px solid rgba(212,168,74,.22);border-radius:13px;cursor:pointer;transition:all .16s}
.ch-acc-pop-row:hover{border-color:rgba(212,168,74,.5);transform:translateY(-2px);box-shadow:0 7px 18px rgba(0,0,0,.3)}
.ch-acc-pop-row .ic{font-size:18px}
.ch-acc-pop-row .nm{font-size:12.5px;font-weight:700;color:#fff;line-height:1.2}
.ch-acc-sec{border:1px solid var(--bdr,rgba(255,255,255,.09));border-radius:15px;margin-bottom:10px;overflow:hidden;background:var(--s1,#1c1c22);transition:border-color .18s}
.ch-acc-sec.open{border-color:rgba(212,168,74,.28)}
.ch-acc-head{display:flex;align-items:center;gap:13px;padding:16px;cursor:pointer;transition:background .15s;user-select:none}
.ch-acc-head:hover{background:var(--s2,#26262c)}
.ch-acc-head .ic{font-size:21px;width:26px;text-align:center}
.ch-acc-head .nm{flex:1;font-size:14.5px;font-weight:700;color:#fff;letter-spacing:-.2px}
.ch-acc-head .ct{font-size:11px;font-weight:700;color:var(--t3,#b4b4c0);background:rgba(255,255,255,.06);padding:3px 9px;border-radius:100px}
.ch-acc-head .chev{transition:transform .28s cubic-bezier(.2,.8,.2,1);color:var(--t4,#8e8e9c);font-size:13px}
.ch-acc-sec.open .chev{transform:rotate(90deg);color:var(--gold,#d4a84a)}
.ch-acc-body{max-height:0;overflow:hidden;transition:max-height .32s cubic-bezier(.2,.8,.2,1)}
.ch-acc-sec.open .ch-acc-body{max-height:1600px}
.ch-acc-row{display:flex;align-items:center;gap:12px;padding:12px 16px 12px 20px;cursor:pointer;border-top:1px solid var(--bdr,rgba(255,255,255,.07));transition:background .14s,padding-left .16s}
.ch-acc-row:hover{background:var(--s2,#26262c);padding-left:25px}
.ch-acc-row .ic{font-size:16px;width:22px;text-align:center;flex:0 0 auto}
.ch-acc-row .nm{font-size:13.5px;color:#e7e7ef;font-weight:600}
.ch-acc-row .ar{margin-left:auto;color:var(--gold,#d4a84a);font-weight:800;opacity:0;transform:translateX(-4px);transition:all .16s}
.ch-acc-row:hover .ar{opacity:1;transform:none}
.ch-acc-empty{padding:18px;text-align:center;color:var(--t4,#8e8e9c);font-size:13px}
</style>
HTML;
  $n1=0; $src=preg_replace('/<\/body>/', $css."\n</body>", $src, 1, $n1);
  $rep[]=['#1 premium CSS', $n1];
} else $rep[]=['#1 CSS (zaten)',0];

/* ── 2) Inline akordeon kurucu (getDocPrice'tan sonra) ── */
$anchor = "function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if (strpos($src,'CH_ACC_INLINE')!==false){ $rep[]=['#2 inline (zaten)',0]; }
elseif (strpos($src,$anchor)===false){ $rep[]=['#2 anchor BULUNAMADI',0]; }
else {
$js = <<<'JS'

/* CH_ACC_INLINE — premium akordeon + arama */
try{(function(){
  var POP=['widerspruch','arbeit_kuendigung','kuendigung_mieter','bussgeldbescheid','mahnschreiben','mahnung','bew_lebenslauf','bew_anschreiben','vtr_arbeitsvertrag','widerruf'];
  function esc(s){ return (''+s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
  function open(dk){ try{ if(typeof qS==='function') return qS(dk); }catch(e){} try{ if(typeof showQuestions==='function') return showQuestions(dk); }catch(e){} }
  function build(){
    var wlc=document.getElementById('wlc'); if(!wlc) return;
    if(typeof CATS==='undefined'||typeof DOCS==='undefined') return;
    if(wlc.querySelector('.ch-acc')) return;
    var box=document.createElement('div'); box.className='ch-acc';

    // arama
    var srch=document.createElement('div'); srch.className='ch-acc-search';
    srch.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.5" y2="16.5"/></svg><input type="text" placeholder="Dokument suchen … (z.B. Kündigung, Widerspruch, Lebenslauf)">';
    box.appendChild(srch);

    // Beliebt
    var pop=document.createElement('div'); pop.className='ch-acc-pop';
    var pr=POP.filter(function(k){return DOCS[k];}).slice(0,6);
    if(pr.length){
      pop.innerHTML='<div class="ch-acc-pop-h">★ Beliebt</div><div class="ch-acc-pop-grid">'
        +pr.map(function(k){var d=DOCS[k];return '<div class="ch-acc-pop-row" data-dk="'+k+'"><span class="ic">'+(d.ic||'📄')+'</span><span class="nm">'+esc(d.n)+'</span></div>';}).join('')+'</div>';
      box.appendChild(pop);
    }

    // kategoriler (akordeon)
    var secWrap=document.createElement('div'); secWrap.className='ch-acc-list';
    Object.keys(CATS).forEach(function(ck){
      var cat=CATS[ck]; var docs=(cat.docs||[]).filter(function(d){return DOCS[d];}); if(!docs.length) return;
      var sec=document.createElement('div'); sec.className='ch-acc-sec';
      var rows=docs.map(function(dk){var d=DOCS[dk];return '<div class="ch-acc-row" data-dk="'+dk+'"><span class="ic">'+(d.ic||'📄')+'</span><span class="nm">'+esc(d.n)+'</span><span class="ar">→</span></div>';}).join('');
      sec.innerHTML='<div class="ch-acc-head"><span class="ic">'+(cat.ic||'📁')+'</span><span class="nm">'+esc(cat.n)+'</span><span class="ct">'+docs.length+'</span><span class="chev">▶</span></div><div class="ch-acc-body">'+rows+'</div>';
      sec.querySelector('.ch-acc-head').addEventListener('click',function(){ sec.classList.toggle('open'); });
      secWrap.appendChild(sec);
    });
    box.appendChild(secWrap);

    // arama mantığı
    var inp=srch.querySelector('input');
    inp.addEventListener('input',function(){
      var q=inp.value.trim().toLowerCase();
      if(!q){ pop.style.display=''; secWrap.querySelectorAll('.ch-acc-sec').forEach(function(s){s.style.display='';s.classList.remove('open');}); secWrap.querySelectorAll('.ch-acc-row').forEach(function(r){r.style.display='';}); return; }
      pop.style.display='none';
      secWrap.querySelectorAll('.ch-acc-sec').forEach(function(s){
        var any=false;
        s.querySelectorAll('.ch-acc-row').forEach(function(r){
          var nm=(r.querySelector('.nm').textContent||'').toLowerCase();
          var hit=nm.indexOf(q)>-1; r.style.display=hit?'':'none'; if(hit)any=true;
        });
        s.style.display=any?'':'none'; if(any)s.classList.add('open'); else s.classList.remove('open');
      });
    });

    // tıklama (delege)
    box.addEventListener('click',function(e){ var el=e.target.closest('[data-dk]'); if(el){ open(el.getAttribute('data-dk')); } });

    var grid=wlc.querySelector('.wcat-grid');
    if(grid&&grid.parentNode){ grid.parentNode.insertBefore(box, grid); } else { wlc.appendChild(box); }
  }
  function boot(){ build(); setInterval(build, 1200); }
  if(document.body) boot(); else document.addEventListener('DOMContentLoaded',boot);
})();}catch(e){}
JS;
  $src=str_replace($anchor, $anchor.$js, $src);
  $rep[]=['#2 inline akordeon eklendi',1];
}

$changed=($src!==$start);
if($changed) file_put_contents($file,$src);
echo "ChatHelp — Ana sayfa akordeon raporu\n====================================\n\n";
foreach($rep as [$l,$n]) echo ($n>0?"  ✓ ":"  ✗ ").$l."  →  $n\n";
echo "\n".($changed?"DURUM: güncellendi. ✅\n":"Değişiklik yok.\n");
echo "SONRA: opcache-reset.php. SİL: rm apply-home-accordion.php\n";
echo "Test: ana sayfa -> üstte arama + 'Beliebt' + kategoriler katlı (tıkla-aç, premium).\n";
