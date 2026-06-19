<?php
/**
 * ChatHelp — Evrensel küçük premium "+" foto/dosya ekleme (additive)
 * ------------------------------------------------------------------
 * Her girişin yanında küçük, premium bir "+" (◯+). Tıkla -> foto/PDF seç -> chip.
 * Kapsam:
 *   • Dilekçe soru formları (her alanın yanında)   -> window._chPhotos (genDoc gönderir)
 *   • Fall sohbeti (#fall-input)                    -> window._chFallFiles
 *   • "Anliegen frei beschreiben" (#ch-iv-input)    -> window._chIntakeFiles
 *   • Ana sohbet (#inp)                             -> mevcut analyze_photo akışını tetikler
 * Eski büyük barlar (.ch-q-photo, #ch-fall-attach) gizlenir.
 *
 * KULLANIM: chat-help.com/chat/apply-attach-plus.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src   = file_get_contents($file);
$start = $src;
file_put_contents($file . '.bak-plus-' . date('Ymd-His'), $src);

if (strpos($src, 'ch-attach-plus') !== false) { exit("Zaten ekli (ch-attach-plus).\n"); }

$bundle = <<<'HTML'
<style id="ch-attach-plus-css">
.chp-wrap{display:inline-flex;align-items:center;gap:7px;flex-wrap:wrap;vertical-align:middle}
.chp{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:50%;border:1.5px solid rgba(212,168,74,.55);background:rgba(212,168,74,.09);color:var(--gold,#d4a84a);cursor:pointer;font-size:19px;font-weight:700;line-height:1;transition:.15s;flex:0 0 auto;user-select:none}
.chp:hover{background:var(--gold,#d4a84a);color:#1b1b1e;box-shadow:0 0 0 4px rgba(212,168,74,.15)}
.chp input{display:none}
.chp-chips{display:inline-flex;gap:5px;flex-wrap:wrap}
.chp-chip{display:inline-flex;align-items:center;gap:5px;background:var(--s3,#313137);border:1px solid var(--bdr,rgba(255,255,255,.1));border-radius:16px;padding:3px 5px 3px 9px;font-size:11px;color:var(--t3,#cfcfdd);max-width:160px}
.chp-chip .nm{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.chp-chip .x{cursor:pointer;color:#d05a4e;font-weight:800;padding:0 3px;line-height:1}
.chp-chip .x:hover{color:#e8675a}
/* form alanlarında "+" sağ altta, küçük */
.fi .chp-wrap{margin-top:6px;width:100%;justify-content:flex-end}
/* eski büyük barları gizle (yerini "+" aldı) */
.ch-q-photo{display:none!important}
#ch-fall-attach{display:none!important}
</style>
<script id="ch-attach-plus">
(function(){
  'use strict';
  window._chFieldFiles = window._chFieldFiles || {};
  window._chFallFiles  = window._chFallFiles  || [];
  window._chIntakeFiles= window._chIntakeFiles|| [];

  function readFile(file, cb){
    if(file.size>6*1024*1024){ alert('„'+file.name+'" ist zu groß (max. 6 MB).'); return; }
    var ok=/^image\//.test(file.type)||file.type==='application/pdf';
    if(!ok){ alert('Nur Bilder oder PDF erlaubt.'); return; }
    var fr=new FileReader();
    fr.onload=function(e){ cb({data:(''+e.target.result).split(',')[1],type:file.type,name:file.name}); };
    fr.readAsDataURL(file);
  }
  function buildPlus(getList, addFile, removeAt){
    var wrap=document.createElement('span'); wrap.className='chp-wrap';
    var lab=document.createElement('label'); lab.className='chp'; lab.title='Foto / Datei hinzufügen';
    lab.innerHTML='+<input type="file" accept="image/*,application/pdf" multiple>';
    var chips=document.createElement('span'); chips.className='chp-chips';
    function draw(){
      chips.innerHTML='';
      getList().forEach(function(f,i){
        var c=document.createElement('span'); c.className='chp-chip';
        c.innerHTML='<span>'+(f.type==='application/pdf'?'📄':'🖼️')+'</span><span class="nm"></span><span class="x">✕</span>';
        c.querySelector('.nm').textContent=f.name||'Datei';
        c.querySelector('.x').onclick=function(){ removeAt(i); draw(); };
        chips.appendChild(c);
      });
    }
    lab.querySelector('input').addEventListener('change',function(){
      Array.prototype.slice.call(this.files||[]).forEach(function(f){ readFile(f,function(o){ addFile(o); draw(); }); });
      this.value='';
    });
    wrap.appendChild(lab); wrap.appendChild(chips); wrap._draw=draw; draw();
    return wrap;
  }
  function cssK(s){ return (''+s).replace(/["\\\]]/g,'\\$&'); }

  /* ── 1) Dilekçe soru alanları ── */
  function syncDocPhotos(){
    window._chPhotos={}; var i=0;
    Object.keys(window._chFieldFiles).forEach(function(k){
      (window._chFieldFiles[k]||[]).forEach(function(f){ window._chPhotos['p'+(i++)]={data:f.data,type:f.type,name:f.name}; });
    });
  }
  function decorateFields(){
    document.querySelectorAll('[data-k]').forEach(function(el){
      if(el.tagName==='OPTION') return;
      var key=el.getAttribute('data-k'); if(!key) return;
      var host=el.closest('.fi')||el.parentNode; if(!host) return;
      if(host.querySelector('.chp-wrap[data-k="'+cssK(key)+'"]')) return;
      window._chFieldFiles[key]=window._chFieldFiles[key]||[];
      var w=buildPlus(
        function(){ return window._chFieldFiles[key]; },
        function(o){ window._chFieldFiles[key].push(o); syncDocPhotos(); },
        function(i){ window._chFieldFiles[key].splice(i,1); syncDocPhotos(); }
      );
      w.setAttribute('data-k',key);
      el.insertAdjacentElement('afterend', w);
    });
  }

  /* ── 2) Fall ── */
  function decorateFall(){
    var ip=document.getElementById('fall-input'); if(!ip) return;
    var host=ip.parentNode; if(!host) return;
    if(host.querySelector('.chp-wrap[data-ctx="fall"]')) return;
    var w=buildPlus(
      function(){ return window._chFallFiles; },
      function(o){ window._chFallFiles.push(o); },
      function(i){ window._chFallFiles.splice(i,1); }
    );
    w.setAttribute('data-ctx','fall');
    host.insertBefore(w, ip);
  }

  /* ── 3) Intake overlay ── */
  function decorateIntake(){
    var ip=document.getElementById('ch-iv-input'); if(!ip) return;
    var host=ip.parentNode; if(!host) return;
    if(host.querySelector('.chp-wrap[data-ctx="intake"]')) return;
    var w=buildPlus(
      function(){ return window._chIntakeFiles; },
      function(o){ window._chIntakeFiles.push(o); },
      function(i){ window._chIntakeFiles.splice(i,1); }
    );
    w.setAttribute('data-ctx','intake');
    host.insertBefore(w, ip);
  }

  /* ── 4) Ana sohbet: mevcut analyze_photo akışını tetikle ── */
  function decorateMain(){
    var ip=document.getElementById('inp'); if(!ip) return;
    var host=document.querySelector('.ia-btns')||ip.parentNode; if(!host) return;
    if(host.querySelector('.chp[data-ctx="main"]')) return;
    var lab=document.createElement('label'); lab.className='chp'; lab.setAttribute('data-ctx','main'); lab.title='Foto / Datei';
    lab.textContent='+';
    lab.onclick=function(){
      // mevcut (benim olmayan) foto input'unu bul ve tetikle
      var ins=document.querySelectorAll('input[type=file]'), ex=null;
      for(var i=0;i<ins.length;i++){ if(!ins[i].closest('.chp') && /image/.test(ins[i].accept||'')){ ex=ins[i]; break; } }
      if(ex){ ex.click(); }
    };
    host.insertBefore(lab, host.firstChild);
  }

  /* ── formlar değişince ekleri sıfırla ── */
  function wrapReset(){
    if(typeof window.showQuestions==='function' && !window.showQuestions._chp){
      var _sq=window.showQuestions;
      window.showQuestions=function(){ window._chFieldFiles={}; window._chPhotos={}; var r=_sq.apply(this,arguments);
        setTimeout(decorateFields,200); setTimeout(decorateFields,700); return r; };
      window.showQuestions._chp=true;
    }
    if(typeof window.chOpenIntake==='function' && !window.chOpenIntake._chp){
      var _oi=window.chOpenIntake;
      window.chOpenIntake=function(){ window._chIntakeFiles=[]; return _oi.apply(this,arguments); };
      window.chOpenIntake._chp=true;
    }
  }

  /* ── intake isteklerine photos ekle ── */
  var of=window.fetch.bind(window);
  window.fetch=function(input,init){
    init=init||{};
    var url=(typeof input==='string')?input:((input&&input.url)||'');
    if(/intake-api\.php/.test(url) && init.body && typeof init.body==='string'){
      try{
        var b=JSON.parse(init.body);
        if(b && (b.action==='intake_solve'||b.action==='intake_chat') && window._chIntakeFiles.length){
          b.photos=window._chIntakeFiles.map(function(f){return {data:f.data,type:f.type,name:f.name};});
          init.body=JSON.stringify(b);
        }
      }catch(e){}
    }
    return of(input,init);
  };

  function tick(){ wrapReset(); decorateFields(); decorateFall(); decorateIntake(); decorateMain(); }
  function boot(){ tick(); setInterval(tick,1000); }
  if(document.body) boot(); else document.addEventListener('DOMContentLoaded',boot);
})();
</script>
HTML;

$n = 0;
$src = preg_replace('/<\/body>/', $bundle . "\n</body>", $src, 1, $n);
$changed = ($src !== $start);
if ($changed) file_put_contents($file, $src);

echo "ChatHelp — Evrensel '+' ekleme raporu\n=====================================\n\n";
echo ($n > 0 ? "  ✓ '+' katmanı eklendi  →  $n\n" : "  ✗ </body> bulunamadı! HABER VER.\n");
echo "\n" . ($changed ? "DURUM: index.php güncellendi. ✅\n" : "DURUM: Değişiklik yok.\n");
echo "\nGEREKLİ: güncel intake-api.php yüklü olmalı (intake foto desteği).\n";
echo "SONRA: opcache-reset.php. SİL: rm apply-attach-plus.php\n";
echo "Test: dilekçe sorularında / Fall'da / 'Anliegen frei beschreiben'de / ana sohbette küçük altın '+' görünür.\n";
