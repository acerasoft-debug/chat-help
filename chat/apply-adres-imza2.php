<?php
/**
 * ChatHelp — apply-adres-imza2 (CH_ADRES_IMZA2) — adres onayini DOGRU noktaya
 *  bagla: makePDF IIFE-yerel oldugu icin cipiak makePDF() cagrilari eski
 *  window.makePDF sarmasini atlıyordu. TUM PDF'ler window.chPrintDoc'tan
 *  gectigi icin onay ekrani ARTIK chPrintDoc seviyesinde:
 *   • Yalniz DE (hukuk ulkesi) + mektup/dilekce goruntusu (Alman kapanis
 *     selami veya kullanici adresi iceren HTML) icin onay ekrani cikar.
 *   • TR vize (vfA4) ve DE-disi belgeler dokunulmadan gecer.
 *   • Adres duzenlenir -> HTML'de guncellenir; imza gerekmiyorsa ekran
 *     imzasi cizilip belgeye gomulur; sonra orijinal chPrintDoc cagrilir.
 *   • Eski CH_ADRES_IMZA makePDF sarmasi PASS-THROUGH yapilir (cift modal yok).
 *  Mevcut #ai-* CSS'i yeniden kullanilir. node ✓ + harness ✓.
 * KULLANIM: pull2.php?key=...&files=apply-adres-imza2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-adres-imza2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_ADRES_IMZA2')!==false) exit("Zaten ekli (CH_ADRES_IMZA2).\n");
if (strpos($src,'CH_ADRES_IMZA')===false) exit("HATA: once CH_ADRES_IMZA gerekli.\n");

$block = <<<'HTMLBLOCK'
<script id="ch-adres-imza2-js">
/* CH_ADRES_IMZA2 — adres onayi chPrintDoc seviyesinde (dogru ortak cikis) */
try{(function(){
  function UIL(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  function CCX(){ try{ var m=localStorage.getItem('ch_cc_manual'); var c=localStorage.getItem('ch_cc'); var w=(typeof window.CC!=='undefined'&&window.CC)?window.CC:''; return String((m&&m.length===2?m:(c||w||'DE'))).toUpperCase(); }catch(e){ return 'DE'; } }
  function T(k){
    var L={
      ttl:{de:'Adresse bestätigen',tr:'Adresinizi onaylayın',en:'Confirm your address'},
      sub:{de:'Diese Angaben erscheinen im Dokument. Bei Bedarf korrigieren.',tr:'Bu bilgiler belgede görünecek. Gerekirse düzeltin.',en:'These appear in the document. Correct if needed.'},
      name:{de:'Name',tr:'Ad Soyad',en:'Name'},adr:{de:'Adresse',tr:'Adres',en:'Address'},plz:{de:'PLZ / Ort',tr:'Posta kodu / Şehir',en:'Postal code / City'},
      sigsec:{de:'✍️ Unterschrift',tr:'✍️ İmza',en:'✍️ Signature'},
      sigok:{de:'Für dieses Dokument genügt eine einfache Unterschrift. Unten unterschreiben (optional):',tr:'Bu belge için basit imza yeterli. Aşağıya imzalayın (opsiyonel):',en:'A simple signature suffices. Sign below (optional):'},
      sigwet:{de:'⚠️ Dieses Dokument braucht eine ORIGINAL-Unterschrift (BGB §126). Bitte ausdrucken und handschriftlich unterschreiben.',tr:'⚠️ Bu belge ISLAK (orijinal) imza gerektirir (BGB §126). Lütfen çıktı alıp elle imzalayın.',en:'⚠️ This document needs an ORIGINAL signature (BGB §126). Print and sign by hand.'},
      clr:{de:'Löschen',tr:'Temizle',en:'Clear'},
      free:{de:'📄 Kostenlos selbst ausdrucken',tr:'📄 Ücretsiz kendim yazdırırım',en:'📄 Print it myself (free)'},
      mail:{de:'✉️ Original nach Hause senden — 2,99 €',tr:'✉️ Orijinali evime postala — 2,99 €',en:'✉️ Mail the original to me — €2.99'},
      cancel:{de:'Abbrechen',tr:'Vazgeç',en:'Cancel'},
      mailok:{de:'Bestellung gespeichert. Sie wird bearbeitet, sobald der Postversand aktiv ist.',tr:'Sipariş kaydedildi. Posta gönderimi aktifleşince işlenecek.',en:'Order saved.'}
    };
    var l=UIL(); return (L[k]&&(L[k][l]||L[k].en))||'';
  }
  function prof(){ try{ var P=window.P; if(P&&(P.f1||P.f7)) return P; }catch(e){} try{ return JSON.parse(localStorage.getItem('ch_prof_v3')||'{}')||{}; }catch(e){ return {}; } }
  function needsWet(html){ var h=(html||'').toLowerCase(); return /k(ü|ue)ndigung[^<]{0,60}(arbeit|anstellung)|arbeitsvertrag|aufhebungsvertrag|b(ü|ue)rgschaft|testament|erbausschlagung/.test(h); }
  /* DE mektup/dilekce mi? */
  function isLetter(html){
    if(CCX()!=='DE') return false;
    if(/vfA4/.test(html)) return false; /* TR vize -> dokunma */
    var P=prof();
    return /Mit freundlichen Grüßen|Hochachtungsvoll|Mit freundlichem Gruß|freundlichen Grüßen/i.test(html)
        || (P.f3 && html.indexOf(P.f3)!==-1);
  }

  var CTX=null; var SIG={drawing:false,has:false,ctx:null,canvas:null};
  function ovEl(){
    var o=document.getElementById('ai-ov');
    if(!o){ o=document.createElement('div'); o.id='ai-ov'; o.innerHTML='<div id="ai-box"></div>'; document.body.appendChild(o);
      o.addEventListener('click',function(e){ if(e.target===o) close(); }); }
    return o;
  }
  function close(){ var o=document.getElementById('ai-ov'); if(o) o.classList.remove('on'); }
  function setupSig(){
    var c=document.getElementById('ai-sigpad'); if(!c) return;
    try{ c.width=c.clientWidth*2; c.height=c.clientHeight*2; }catch(e){}
    var ctx=c.getContext('2d'); try{ ctx.scale(2,2); }catch(e){} ctx.lineWidth=2.2; ctx.lineCap='round'; ctx.strokeStyle='#0b1a3a';
    SIG.canvas=c; SIG.ctx=ctx; SIG.has=false;
    function pos(ev){ var r=c.getBoundingClientRect(); var t=(ev.touches&&ev.touches[0])||ev; return {x:t.clientX-r.left,y:t.clientY-r.top}; }
    function down(ev){ ev.preventDefault(); SIG.drawing=true; var p=pos(ev); ctx.beginPath(); ctx.moveTo(p.x,p.y); }
    function move(ev){ if(!SIG.drawing) return; ev.preventDefault(); var p=pos(ev); ctx.lineTo(p.x,p.y); ctx.stroke(); SIG.has=true; }
    function up(){ SIG.drawing=false; }
    c.addEventListener('pointerdown',down); c.addEventListener('pointermove',move); window.addEventListener('pointerup',up);
    c.addEventListener('touchstart',down,{passive:false}); c.addEventListener('touchmove',move,{passive:false}); c.addEventListener('touchend',up);
  }
  function clearSig(){ try{ if(SIG.ctx&&SIG.canvas){ SIG.ctx.clearRect(0,0,SIG.canvas.width,SIG.canvas.height); SIG.has=false; } }catch(e){} }
  function sigData(){ try{ return SIG.has?SIG.canvas.toDataURL('image/png'):''; }catch(e){ return ''; } }

  function proceed(html){ window.__addrOK=true; try{ CTX.orig.call(window,html); }catch(e){ try{ CTX.orig(html); }catch(e2){} } }
  function doFree(){
    var P=prof();
    var f3=(document.getElementById('ai-f3')||{}).value, f4=(document.getElementById('ai-f4')||{}).value;
    var html=CTX.html;
    try{
      if(f3!==undefined && P.f3 && f3 && f3!==P.f3) html=html.split(P.f3).join(f3);
      if(f4!==undefined && P.f4 && f4 && f4!==P.f4) html=html.split(P.f4).join(f4);
      if(window.P){ if(f3!==undefined) window.P.f3=f3; if(f4!==undefined) window.P.f4=f4; }
      var pv=JSON.parse(localStorage.getItem('ch_prof_v3')||'{}')||{}; if(f3!==undefined)pv.f3=f3; if(f4!==undefined)pv.f4=f4; localStorage.setItem('ch_prof_v3',JSON.stringify(pv));
    }catch(e){}
    var sig=sigData();
    if(sig){
      var img='<div style="margin-top:24px"><img src="'+sig+'" style="height:62px" alt="Unterschrift"></div>';
      if(/<\/body>/i.test(html)) html=html.replace(/<\/body>/i,img+'</body>'); else html=html+img;
    }
    close(); proceed(html);
  }
  function doMail(){
    try{
      var P=prof();
      var req={ ts:new Date().getTime(), adr:(document.getElementById('ai-f3')||{}).value||P.f3||'', plz:(document.getElementById('ai-f4')||{}).value||P.f4||'', html:String(CTX.html||'').slice(0,20000), sig:sigData()?1:0, paid:0 };
      var a=JSON.parse(localStorage.getItem('ch_mailorig_req')||'[]'); if(!Array.isArray(a)) a=[]; a.push(req); localStorage.setItem('ch_mailorig_req',JSON.stringify(a.slice(-50)));
    }catch(e){}
    close(); try{ alert(T('mailok')); }catch(e){}
  }
  function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
  function render(){
    var P=prof(), box=document.getElementById('ai-box'); if(!box) return;
    var wet=needsWet(CTX.html);
    var h='<h3>📮 '+T('ttl')+'</h3><div class="sub">'+T('sub')+'</div>';
    h+='<div class="ai-f"><label>'+T('name')+'</label><input class="ai-in" id="ai-f1" value="'+esc(((P.f1||'')+' '+(P.f2||'')).trim())+'"></div>';
    h+='<div class="ai-f"><label>'+T('adr')+'</label><input class="ai-in" id="ai-f3" value="'+esc(P.f3||'')+'"></div>';
    h+='<div class="ai-f"><label>'+T('plz')+'</label><input class="ai-in" id="ai-f4" value="'+esc(P.f4||'')+'"></div>';
    h+='<div class="ai-sec">'+T('sigsec')+'</div>';
    if(wet){ h+='<div class="ai-warn">'+T('sigwet')+'</div>'; }
    else{ h+='<div class="sub" style="margin-bottom:6px">'+T('sigok')+'</div><canvas id="ai-sigpad"></canvas><div class="ai-sigrow"><button id="ai-sigclr">🗑 '+T('clr')+'</button></div>'; }
    h+='<div class="ai-acts"><button id="ai-free">'+T('free')+'</button><button id="ai-mail">'+T('mail')+'</button><button id="ai-cancel">'+T('cancel')+'</button></div>';
    box.innerHTML=h;
    if(!wet){ setupSig(); var cb=document.getElementById('ai-sigclr'); if(cb) cb.onclick=clearSig; }
    document.getElementById('ai-free').onclick=doFree;
    document.getElementById('ai-mail').onclick=doMail;
    document.getElementById('ai-cancel').onclick=function(){ close(); proceed(CTX.html); };
  }

  /* eski makePDF sarmasini PASS-THROUGH yap (cift modal onleme) */
  try{ if(typeof window.makePDF==='function' && window.makePDF.__adr && !window.makePDF.__pt){
    var _old=window.makePDF;
    var pt=function(doc,name,cc){ window.__pdfConfirmed=true; return _old.call(this,doc,name,cc); };
    pt.__adr=1; pt.__pt=1; window.makePDF=pt;
  } }catch(e){}

  /* chPrintDoc'u sar (outermost) */
  var g=0;(function wrap(){
    try{
      if(typeof window.chPrintDoc==='function' && !window.chPrintDoc.__addr){
        var _cpd=window.chPrintDoc;
        var w=function(html){
          try{
            if(window.__addrOK){ window.__addrOK=false; return _cpd.apply(this,arguments); }
            if(typeof html==='string' && isLetter(html)){
              CTX={html:html,orig:_cpd};
              ovEl().classList.add('on'); render();
              return;
            }
          }catch(e){}
          return _cpd.apply(this,arguments);
        };
        w.__addr=1;
        try{ if(_cpd.__ai2)w.__ai2=1; if(_cpd.__fit)w.__fit=1; if(_cpd.__vsfprn)w.__vsfprn=1; }catch(e){}
        window.chPrintDoc=w;
      }
    }catch(e){}
    if(g++<500) setTimeout(wrap,500);
  })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'a2').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-adresimza2-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_ADRES_IMZA2')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_ADRES_IMZA2 diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Adres onayi artik DOGRU noktada (chPrintDoc):\n";
echo "   • DE hukuku + Alman mektup/dilekce -> onay ekrani (kategori/chat/hepsi)\n";
echo "   • TR vize (vfA4) ve DE-disi belgeler dokunulmadan gecer\n";
echo "   • Eski makePDF sarmasi pass-through (cift modal yok)\n";
echo "   TEST: DE hukuku hesabiyla bir Almanca dilekce uret -> PDF -> onay cikar.\n";
