<?php
/**
 * ChatHelp — i18n katmanı (8 dil, additive, kendi kendine yeten)
 *  • Dil seçici (DE/TR/RU/AR/EN/FR/ES/IT) — sağ üstte bayrak
 *  • Telefon dili Almanca değilse "fortfahren auf X?" sorar
 *  • Soru etiketleri ÇİFT: Almanca (asıl) + ana dil (translate.php, önbellekli)
 *  • Arapça için RTL — sadece çeviri metnine
 *  • Her dilekçe altına "In eigenen Worten" serbest metin alanı (-> belgeye)
 *  • Açılır menülere "Sonstiges …" -> kendi yazabileceği alan
 *  • generate isteğine lang enjekte (belge Almanca + ana dil açıklama için)
 *  Konto'da/elle seçim her şeyi ezer; hukuk + belge HER ZAMAN Almanca kalır.
 * KULLANIM: chat-help.com/chat/apply-i18n.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src=file_get_contents($file); $start=$src;
file_put_contents($file . '.bak-i18n-' . date('Ymd-His'), $src);
if (strpos($src, 'ch-i18n-layer') !== false) { exit("Zaten ekli.\n"); }

$bundle = <<<'HTML'
<style id="ch-i18n-css">
/* yumuşak genel geçiş (dil değişiminde içerik premium akar) */
.fi label,.fi input,.fi select,.fi textarea,.ch-i18n-tr{transition:color .25s ease,background-color .25s ease,border-color .25s ease,opacity .25s ease}
#ch-lang-btn{position:fixed;top:12px;right:12px;z-index:99990;width:42px;height:42px;border-radius:50%;background:linear-gradient(145deg,var(--s2,#26262c),var(--s1,#1b1b22));border:1.5px solid rgba(212,168,74,.4);font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 18px rgba(0,0,0,.4),inset 0 1px 0 rgba(255,255,255,.06);transition:transform .18s cubic-bezier(.2,.8,.2,1),box-shadow .18s,border-color .18s}
#ch-lang-btn:hover{transform:translateY(-2px) scale(1.06);border-color:var(--gold,#d4a84a);box-shadow:0 10px 26px rgba(212,168,74,.25)}
#ch-lang-btn:active{transform:scale(.95)}
#ch-lang-menu{position:fixed;top:60px;right:12px;z-index:99991;background:linear-gradient(180deg,var(--s1,#1c1c22),var(--bg,#15151b));border:1px solid rgba(212,168,74,.22);border-radius:16px;padding:7px;box-shadow:0 18px 44px rgba(0,0,0,.6),inset 0 1px 0 rgba(255,255,255,.05);min-width:180px;opacity:0;transform:translateY(-8px) scale(.96);transform-origin:top right;pointer-events:none;transition:opacity .2s ease,transform .22s cubic-bezier(.2,.85,.25,1)}
#ch-lang-menu.on{opacity:1;transform:translateY(0) scale(1);pointer-events:auto}
#ch-lang-menu button{display:flex;align-items:center;gap:11px;width:100%;padding:10px 13px;background:none;border:none;color:#e7e7ef;font-size:13.5px;font-weight:600;cursor:pointer;border-radius:11px;font-family:inherit;text-align:left;transition:background .15s,color .15s,transform .12s}
#ch-lang-menu button:hover{background:rgba(212,168,74,.1);transform:translateX(2px)}
#ch-lang-menu button.on{color:var(--gold,#d4a84a);background:rgba(212,168,74,.12)}
#ch-lang-menu button .fl{font-size:19px;filter:drop-shadow(0 1px 2px rgba(0,0,0,.4))}
#ch-lang-ask{position:fixed;top:0;left:0;right:0;z-index:99989;background:linear-gradient(135deg,#1d1d30,#15151f);border-bottom:1px solid rgba(212,168,74,.3);padding:12px 14px;display:flex;align-items:center;gap:11px;justify-content:center;flex-wrap:wrap;font-size:13px;color:#e7e7ef;transform:translateY(-100%);transition:transform .35s cubic-bezier(.2,.85,.25,1);box-shadow:0 8px 24px rgba(0,0,0,.35)}
#ch-lang-ask.on{transform:translateY(0)}
#ch-lang-ask b{color:var(--gold,#d4a84a)}
#ch-lang-ask button{padding:8px 16px;border-radius:10px;border:none;font-weight:800;font-size:12.5px;cursor:pointer;font-family:inherit;transition:transform .15s,filter .15s}
#ch-lang-ask button:hover{transform:translateY(-1px)}
#ch-lang-ask .yes{background:linear-gradient(135deg,#d4a84a,#b8862f);color:#1b1b1e}
#ch-lang-ask .yes:hover{filter:brightness(1.08)}
#ch-lang-ask .no{background:rgba(255,255,255,.08);color:#c0c0d0;border:1px solid rgba(255,255,255,.12)}
.ch-i18n-tr{display:block;font-size:11.5px;color:var(--gold,#d4a84a);opacity:.82;font-weight:500;margin-top:3px;animation:chTrIn .3s ease}
@keyframes chTrIn{from{opacity:0;transform:translateY(-3px)}to{opacity:.82;transform:none}}
.ch-i18n-rtl{direction:rtl;text-align:right;unicode-bidi:isolate}
.ch-freitext textarea{min-height:64px}
.ch-sonst-inp{margin-top:7px;width:100%;background:var(--bg,#1b1b1e);border:1px solid var(--bdr,rgba(255,255,255,.13));border-radius:10px;color:#f0f0f6;padding:10px 12px;font-family:inherit;font-size:14px;animation:chTrIn .25s ease}
</style>
<script id="ch-i18n-layer">
(function(){
  'use strict';
  var L={de:['🇩🇪','Deutsch'],tr:['🇹🇷','Türkçe'],ru:['🇷🇺','Русский'],ar:['🇸🇦','العربية'],en:['🇬🇧','English'],fr:['🇫🇷','Français'],es:['🇪🇸','Español'],it:['🇮🇹','Italiano']};
  function sup(l){ return L.hasOwnProperty(l); }

  function getLang(){
    var s=null; try{ s=localStorage.getItem('ch_uilang'); }catch(e){}
    if(s && sup(s)) return s;
    try{ if(window.UL && sup(window.UL)) return window.UL; }catch(e){}
    return 'de';
  }
  function setLang(l){
    if(!sup(l)) return;
    try{ localStorage.setItem('ch_uilang', l); }catch(e){}
    document.documentElement.classList.toggle('ch-has-rtl', l==='ar');
    clearTr(); updateBtn(); hideAsk();
    // sitenin kendi dilini de den, varsa (UI metinleri için)
    try{ if(typeof window.setFlag==='function' && l!=='ar') window.setFlag(l); }catch(e){}
  }
  function clearTr(){
    document.querySelectorAll('.ch-i18n-tr').forEach(function(e){ e.remove(); });
    document.querySelectorAll('[data-i18n-done]').forEach(function(e){ e.removeAttribute('data-i18n-done'); });
  }

  /* ── çeviri (translate.php + bellek önbellek) ── */
  var TC={};
  function trBatch(texts, lang, cb){
    TC[lang]=TC[lang]||{};
    var miss=texts.filter(function(t){ return !(t in TC[lang]); });
    if(!miss.length){ cb(map(texts,lang)); return; }
    fetch('translate.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({texts:miss,lang:lang})})
      .then(function(r){return r.json();}).then(function(d){ var t=d.translations||{}; for(var k in t) TC[lang][k]=t[k]; cb(map(texts,lang)); })
      .catch(function(){ miss.forEach(function(m){TC[lang][m]=m;}); cb(map(texts,lang)); });
  }
  function map(texts,lang){ var m={}; texts.forEach(function(t){ m[t]=(TC[lang]&&TC[lang][t])||t; }); return m; }

  function deText(l){
    var d=l.getAttribute('data-de'); if(d!==null) return d;
    var t=''; for(var i=0;i<l.childNodes.length;i++){ if(l.childNodes[i].nodeType===3) t+=l.childNodes[i].nodeValue; }
    t=t.replace(/\*/g,'').trim(); l.setAttribute('data-de', t); return t;
  }

  /* ── soru etiketlerini çift dile çevir ── */
  function decorateLabels(){
    var lang=getLang(); if(lang==='de') return;
    var labels=[].slice.call(document.querySelectorAll('.fi label:not([data-i18n-done])'));
    if(!labels.length) return;
    var texts=[], els=[];
    labels.forEach(function(l){ var t=deText(l); l.setAttribute('data-i18n-done',lang); if(t){ texts.push(t); els.push(l); } });
    if(!texts.length) return;
    trBatch(texts, lang, function(mm){
      els.forEach(function(l){ var t=l.getAttribute('data-de'); var tr=mm[t];
        if(tr && tr!==t && !l.querySelector('.ch-i18n-tr')){
          var s=document.createElement('span'); s.className='ch-i18n-tr'+(lang==='ar'?' ch-i18n-rtl':''); s.textContent=tr; l.appendChild(s);
        }
      });
    });
  }

  /* ── serbest metin alanı (her dilekçe altına) ── */
  function decorateFreitext(){
    var fis=[].slice.call(document.querySelectorAll('.fi:not(.ch-freitext)'));
    if(!fis.length) return;
    if(document.querySelector('.ch-freitext')) return;
    var last=fis[fis.length-1]; if(!last||!last.parentNode) return;
    var lang=getLang();
    var w=document.createElement('div'); w.className='fi ch-freitext';
    w.innerHTML='<label data-de="Weitere Angaben in eigenen Worten (optional)">Weitere Angaben in eigenen Worten <span style="color:var(--t4);font-size:9px">(optional)</span></label>'
      +'<textarea data-k="_freitext" rows="3" placeholder="Beschreiben Sie Ihre Situation — auch in Ihrer Sprache."></textarea>';
    last.parentNode.insertBefore(w, last.nextSibling);
    if(lang!=='de') w.querySelector('label').removeAttribute('data-i18n-done');
  }

  /* ── açılır menülere "Sonstiges" + serbest giriş ── */
  function decorateSonstiges(){
    document.querySelectorAll('select[data-k]:not([data-sonst])').forEach(function(sel){
      sel.setAttribute('data-sonst','1');
      var has=[].some.call(sel.options,function(o){ return /sonstig|anderes|andere|other/i.test(o.text); });
      if(!has){ var o=document.createElement('option'); o.value='__other__'; o.textContent='Sonstiges …'; sel.appendChild(o); }
      var inp=document.createElement('input'); inp.type='text'; inp.className='ch-sonst-inp'; inp.placeholder='Bitte angeben (z.B. „2 Tage")'; inp.style.display='none';
      sel.parentNode.insertBefore(inp, sel.nextSibling);
      function isOther(){ var o=sel.options[sel.selectedIndex]; return sel.value==='__other__' || (o && /sonstig|anderes|andere|other/i.test(o.text)); }
      sel.addEventListener('change',function(){ inp.style.display=isOther()?'block':'none'; if(isOther()) setTimeout(function(){inp.focus();},30); });
      inp.addEventListener('input',function(){
        var v=inp.value; var co=sel.querySelector('option[data-custom]');
        if(!co){ co=document.createElement('option'); co.setAttribute('data-custom','1'); sel.appendChild(co); }
        co.value=v; co.textContent=v||'Sonstiges …'; sel.value=v;
        try{ sel.dispatchEvent(new Event('change',{bubbles:true})); }catch(e){}
      });
    });
  }

  /* ── generate isteğine lang enjekte ── */
  var of=window.fetch.bind(window);
  window.fetch=function(input,init){
    var url=(typeof input==='string')?input:((input&&input.url)||'');
    if(/action=generate/.test(url) && init && init.body && typeof init.body==='string'){
      var l=getLang();
      if(l!=='de'){ try{ var b=JSON.parse(init.body); b.lang=l; b.ui_lang=l; init.body=JSON.stringify(b); }catch(e){} }
    }
    return of(input,init);
  };

  /* ── dil seçici UI ── */
  function buildPicker(){
    if(document.getElementById('ch-lang-btn')) return;
    var btn=document.createElement('button'); btn.id='ch-lang-btn'; document.body.appendChild(btn);
    var menu=document.createElement('div'); menu.id='ch-lang-menu';
    menu.innerHTML=Object.keys(L).map(function(k){ return '<button data-l="'+k+'"><span class="fl">'+L[k][0]+'</span>'+L[k][1]+'</button>'; }).join('');
    document.body.appendChild(menu);
    btn.onclick=function(e){ e.stopPropagation(); menu.classList.toggle('on'); };
    document.addEventListener('click',function(){ menu.classList.remove('on'); });
    menu.querySelectorAll('button').forEach(function(b){ b.onclick=function(){ setLang(b.getAttribute('data-l')); menu.classList.remove('on'); }; });
    updateBtn();
  }
  function updateBtn(){ var b=document.getElementById('ch-lang-btn'); if(b) b.textContent=L[getLang()][0];
    var m=document.getElementById('ch-lang-menu'); if(m) m.querySelectorAll('button').forEach(function(x){ x.classList.toggle('on', x.getAttribute('data-l')===getLang()); }); }

  /* ── telefon dili != Almanca -> sor ── */
  function maybeAsk(){
    var set=null; try{ set=localStorage.getItem('ch_uilang'); }catch(e){}
    if(set) return;                       // kullanıcı zaten seçmiş
    var nav=(navigator.language||'de').slice(0,2).toLowerCase();
    if(nav==='de' || !sup(nav)) return;   // zaten Almanca veya desteklenmiyor
    if(document.getElementById('ch-lang-ask')) return;
    var bar=document.createElement('div'); bar.id='ch-lang-ask';
    bar.innerHTML='<span>🌐 Möchten Sie auf <b>'+L[nav][1]+'</b> fortfahren?</span>'
      +'<button class="yes">'+L[nav][0]+' Ja</button><button class="no">🇩🇪 Deutsch</button>';
    document.body.appendChild(bar); bar.classList.add('on');
    bar.querySelector('.yes').onclick=function(){ setLang(nav); };
    bar.querySelector('.no').onclick=function(){ setLang('de'); };
  }
  function hideAsk(){ var a=document.getElementById('ch-lang-ask'); if(a) a.classList.remove('on'); }

  function tick(){ decorateLabels(); decorateFreitext(); decorateSonstiges(); }
  function boot(){
    document.documentElement.classList.toggle('ch-has-rtl', getLang()==='ar');
    buildPicker(); maybeAsk(); tick(); setInterval(tick, 1000);
  }
  if(document.body) boot(); else document.addEventListener('DOMContentLoaded',boot);
})();
</script>
HTML;

$n=0;
$src=preg_replace('/<\/body>/', $bundle . "\n</body>", $src, 1, $n);
$changed=($src!==$start);
if($changed) file_put_contents($file,$src);
echo "ChatHelp — i18n katmanı raporu\n==============================\n\n";
echo ($n>0?"  ✓ i18n katmanı eklendi  →  $n\n":"  ✗ </body> yok!\n");
echo "\n".($changed?"DURUM: güncellendi. ✅\n":"Değişiklik yok.\n");
echo "GEREKLİ: translate.php yüklü olmalı.\n";
echo "SONRA: opcache-reset.php. SİL: rm apply-i18n.php\n";
echo "Test: sağ üstte bayrak -> dil seç -> soru etiketleri çift dilli; en altta serbest metin; menülerde Sonstiges.\n";
