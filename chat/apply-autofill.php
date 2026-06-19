<?php
/**
 * ChatHelp — Belgeden otomatik kutu doldurma (additive)
 * -----------------------------------------------------
 * Bir dilekçe alanındaki "+" ile foto/belge yüklenince:
 *   • analyze_photo ile okunur (Behörde, Datum, Aktenzeichen, Frist, Betrag, Zusammenfassung)
 *   • BOŞ olan ilgili form kutuları otomatik doldurulur
 *   • Kısa premium bilgi balonu: ne olduğu + ne yapılması gerektiği
 * Belgenin kendisi zaten backend'de (genDoc Vision) dilekçeye işleniyor.
 *
 * KULLANIM: chat-help.com/chat/apply-autofill.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src   = file_get_contents($file);
$start = $src;
file_put_contents($file . '.bak-autofill-' . date('Ymd-His'), $src);

if (strpos($src, 'ch-autofill') !== false) { exit("Zaten ekli (ch-autofill).\n"); }

$bundle = <<<'HTML'
<style id="ch-autofill-css">
#ch-af-toast{position:fixed;left:50%;bottom:96px;transform:translateX(-50%) translateY(20px);z-index:100001;max-width:90%;background:var(--s1,#222226);border:1px solid var(--gold,#d4a84a);color:#f0f0f6;padding:13px 18px;border-radius:14px;box-shadow:0 12px 34px rgba(0,0,0,.5);font-size:13.5px;line-height:1.45;opacity:0;pointer-events:none;transition:.3s}
#ch-af-toast.on{opacity:1;transform:translateX(-50%) translateY(0)}
#ch-af-toast b{color:var(--gold,#d4a84a)}
.ch-af-glow{animation:chAfGlow 1.6s ease}
@keyframes chAfGlow{0%{box-shadow:0 0 0 0 rgba(212,168,74,.55)}100%{box-shadow:0 0 0 7px rgba(212,168,74,0)}}
</style>
<script id="ch-autofill">
(function(){
  'use strict';
  function toast(html){
    var t=document.getElementById('ch-af-toast');
    if(!t){ t=document.createElement('div'); t.id='ch-af-toast'; document.body.appendChild(t); }
    t.innerHTML=html; t.classList.add('on');
    clearTimeout(t._tm); t._tm=setTimeout(function(){ t.classList.remove('on'); },7000);
  }

  var MAP=[
    {re:/empf|beh(ö|oe|o)rde|gegner|firma|adress|absender|\bamt\b|stelle|vermieter|arbeitgeb|gl(ä|ae)ubiger|inkasso/i, k:'behoerde'},
    {re:/datum|bescheid|schreiben.*dat|dat.*schreiben/i, k:'datum'},
    {re:/aktenz|gesch(ä|ae)ftsz|\baz\b|kundennr|kundennummer|vorgangs|rechnungsnr|zeichen/i, k:'az'},
    {re:/frist|deadline|widerspruchsfrist/i, k:'frist'},
    {re:/betrag|summe|forderung|\beuro\b|geldb|hoehe|höhe/i, k:'betrag'},
    {re:/sachverhalt|beschreib|situation|\bgrund\b|details|anliegen|begründ|begruend|schilder/i, k:'zusammenfassung'},
  ];

  function fillFields(r){
    var filled=0;
    var fields=document.querySelectorAll('[data-k]');
    fields.forEach(function(el){
      if(el.tagName==='OPTION'||el.tagName==='BUTTON') return;
      if((el.value||'').trim()!=='') return;                 // doluysa dokunma
      var key=(el.getAttribute('data-k')||'').toLowerCase();
      var lblEl=el.closest('.fi'); var lbl=lblEl?(lblEl.textContent||'').toLowerCase():'';
      var hay=key+' '+lbl;
      for(var i=0;i<MAP.length;i++){
        var m=MAP[i]; var val=r[m.k];
        if(val && m.re.test(hay)){
          el.value=val;
          try{ el.dispatchEvent(new Event('input',{bubbles:true})); el.dispatchEvent(new Event('change',{bubbles:true})); }catch(e){}
          el.classList.add('ch-af-glow'); setTimeout(function(x){x.classList.remove('ch-af-glow');},1700,el);
          filled++; break;
        }
      }
    });
    return filled;
  }

  async function analyze(file){
    var b64=await new Promise(function(res,rej){ var fr=new FileReader(); fr.onload=function(e){res((''+e.target.result).split(',')[1]);}; fr.onerror=rej; fr.readAsDataURL(file); });
    var resp=await fetch('api.php?action=analyze_photo',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({b64:b64,mime:file.type})});
    var d=await resp.json();
    var r=d.result||d.ocr||{};
    return {
      behoerde:r.behoerde||r.absender||r.firma||'',
      datum:r.datum||'',
      az:r.aktenzeichen||r.kundennummer||r.az||'',
      frist:r.frist||'',
      betrag:r.betrag||'',
      zusammenfassung:r.zusammenfassung||r.summary||'',
      doc:r.doc||''
    };
  }

  // capture: "+" alan input'una dosya seçilince (form alanı bağlamında) oku + doldur
  document.addEventListener('change', function(ev){
    var inp=ev.target;
    if(!inp || inp.type!=='file' || !inp.files || !inp.files.length) return;
    // sadece dilekçe form alanı "+"'ı (data-k'li chp) için otomatik doldur
    var inForm=inp.closest('.chp-wrap[data-k]') || document.querySelector('[data-k]');
    if(!inForm) return;
    var file=inp.files[0];
    if(!file || !/^image\//.test(file.type)) return;   // analyze_photo görsel okur (PDF backend'de kullanılır)
    toast('🔍 <b>Dokument wird gelesen…</b>');
    analyze(file).then(function(r){
      var n=fillFields(r);
      var what=r.zusammenfassung?('<br><span style="opacity:.85">'+r.zusammenfassung+'</span>'):'';
      if(n>0) toast('✅ <b>'+n+' Feld(er)</b> aus dem Dokument ausgefüllt.'+what);
      else if(r.zusammenfassung) toast('📄 <b>Erkannt.</b>'+what);
      else toast('📄 Dokument hinzugefügt – wird im Schreiben verwendet.');
    }).catch(function(){ /* sessiz: belge yine de backend'de kullanılır */ });
  }, true);
})();
</script>
HTML;

$n = 0;
$src = preg_replace('/<\/body>/', $bundle . "\n</body>", $src, 1, $n);
$changed = ($src !== $start);
if ($changed) file_put_contents($file, $src);

echo "ChatHelp — Otomatik doldurma raporu\n===================================\n\n";
echo ($n > 0 ? "  ✓ Autofill katmanı eklendi  →  $n\n" : "  ✗ </body> bulunamadı! HABER VER.\n");
echo "\n" . ($changed ? "DURUM: index.php güncellendi. ✅\n" : "DURUM: Değişiklik yok.\n");
echo "\nGEREKLİ: apply-attach-plus.php ('+' butonları) önce yüklü olmalı.\n";
echo "SONRA: opcache-reset.php. SİL: rm apply-autofill.php\n";
echo "Test: dilekçe alanında '+' ile bir Bescheid fotoğrafı yükle -> Behörde/Datum/Az/Frist kutuları otomatik dolsun.\n";
