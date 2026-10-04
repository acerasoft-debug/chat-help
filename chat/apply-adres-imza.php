<?php
/**
 * ChatHelp — apply-adres-imza (CH_ADRES_IMZA) — dilekce cikmadan adres onayi
 *  + akilli imza + postala secenegi:
 *  makePDF sarilir; PDF uretilmeden ONCE onay ekrani cikar:
 *   [1] ADRES: Konto/profil adresi (f1..f4) gosterilir, DUZENLENEBILIR.
 *       Duzeltilirse profile kaydedilir ve belgedeki eski adres guncellenir.
 *   [2] IMZA MANTIGI: belge turu imza gerektiriyor mu otomatik belirlenir.
 *       • Islak imza SART (is feshi/istifa/kefalet/vasiyet...): uyari verilir
 *         ("cikti alip elle imzalayin"), ekran imzasi YOK.
 *       • Digerleri (Textform): basit EKRAN IMZASI (canvas) sunulur; cizilirse
 *         belgeye gomulur.
 *   [3] SECENEK: "📄 Ucretsiz kendim yazdiririm" -> PDF (imzali ise gomulu).
 *       "✉️ Orijinali evime postala — 2,99 €" -> siparis kaydedilir
 *       (ch_mailorig_req) + onay. (Gercek odeme+gonderim: Stripe 2,99 fiyati
 *       ve PixelLetter/Deutsche Post hesabi baglaninca aktiflesir.)
 *  Tek eklemeli </body> blogu; makePDF sarma recursion-korumali. node ✓ + harness ✓.
 * KULLANIM: pull2.php?key=...&files=apply-adres-imza.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-adres-imza BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_ADRES_IMZA')!==false) exit("Zaten ekli (CH_ADRES_IMZA).\n");
if (strpos($src,'function makePDF')===false) exit("HATA: makePDF yok.\n");

$block = <<<'HTMLBLOCK'
<style id="ch-adres-imza-css">
#ai-ov{position:fixed;inset:0;z-index:2147483360;background:rgba(4,4,12,.86);backdrop-filter:blur(4px);display:none;align-items:center;justify-content:center;padding:16px;font-family:'Inter',system-ui,sans-serif}
#ai-ov.on{display:flex}
#ai-box{width:min(500px,96vw);max-height:92vh;overflow-y:auto;background:linear-gradient(180deg,#12122a,#0b0b19);border:1px solid rgba(212,168,74,.4);border-radius:16px;color:#f2f2fb;padding:18px}
#ai-box h3{font-size:16px;font-weight:800;margin-bottom:4px}
#ai-box .sub{font-size:11.5px;color:#9a9ab0;margin-bottom:14px;line-height:1.5}
.ai-f{margin-bottom:10px}
.ai-f label{display:block;font-size:10px;font-weight:800;color:#8e8e9c;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.ai-in{width:100%;background:#0d0d1e;border:1.5px solid rgba(255,255,255,.14);border-radius:10px;padding:11px;color:#fff;font-size:14px;font-family:inherit;outline:none;box-sizing:border-box}
.ai-in:focus{border-color:rgba(212,168,74,.6)}
.ai-2{display:flex;gap:10px}.ai-2>div{flex:1}
.ai-sec{font-size:12px;font-weight:800;color:#e8c874;margin:16px 0 7px}
.ai-warn{background:rgba(255,170,60,.1);border:1px solid rgba(255,170,60,.4);border-radius:10px;padding:10px 12px;font-size:11.5px;color:#ffd9a0;line-height:1.5;margin-bottom:8px}
#ai-sigpad{width:100%;height:130px;background:#fff;border:1.5px dashed rgba(212,168,74,.5);border-radius:10px;touch-action:none;cursor:crosshair}
.ai-sigrow{display:flex;gap:8px;margin-top:6px}
.ai-sigrow button{flex:1;background:#1a1a30;border:1px solid rgba(255,255,255,.14);color:#c8c8e0;border-radius:8px;padding:8px;font-size:11.5px;font-weight:700;cursor:pointer;font-family:inherit}
.ai-acts{display:flex;flex-direction:column;gap:9px;margin-top:16px}
.ai-acts button{padding:13px;border-radius:11px;border:none;cursor:pointer;font-family:inherit;font-size:13.5px;font-weight:800}
#ai-free{background:linear-gradient(135deg,#d4a84a,#ecc060);color:#07070e}
#ai-mail{background:#1a1a30;color:#e8c874;border:1.5px solid rgba(212,168,74,.5)!important}
#ai-cancel{background:transparent;color:#9a9ab0;border:1px solid rgba(255,255,255,.12)!important;font-weight:700}
</style>
<script id="ch-adres-imza-js">
/* CH_ADRES_IMZA — makePDF oncesi adres onayi + imza + postala */
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
      sigwet:{de:'⚠️ Dieses Dokument braucht eine ORIGINAL-Unterschrift (BGB §126). Bitte ausdrucken und mit blauer/schwarzer Tinte handschriftlich unterschreiben — eine Bildschirm-Unterschrift ist hier ungültig.',tr:'⚠️ Bu belge ISLAK (orijinal) imza gerektirir (BGB §126). Lütfen çıktı alıp mavi/siyah kalemle elle imzalayın — ekran imzası burada geçersizdir.',en:'⚠️ This document needs an ORIGINAL signature (BGB §126). Print and sign by hand — a screen signature is invalid here.'},
      clr:{de:'Löschen',tr:'Temizle',en:'Clear'},
      free:{de:'📄 Kostenlos selbst ausdrucken',tr:'📄 Ücretsiz kendim yazdırırım',en:'📄 Print it myself (free)'},
      mail:{de:'✉️ Original nach Hause senden — 2,99 €',tr:'✉️ Orijinali evime postala — 2,99 €',en:'✉️ Mail the original to me — €2.99'},
      cancel:{de:'Abbrechen',tr:'Vazgeç',en:'Cancel'},
      mailok:{de:'Bestellung gespeichert. Sie wird bearbeitet, sobald der Postversand aktiv ist.',tr:'Sipariş kaydedildi. Posta gönderimi aktifleşince işlenecek.',en:'Order saved. It will be processed once mailing is enabled.'}
    };
    var l=UIL(); return (L[k]&&(L[k][l]||L[k].en))||'';
  }
  function prof(){
    try{ var P=window.P; if(P&&(P.f1||P.f7)) return P; }catch(e){}
    try{ return JSON.parse(localStorage.getItem('ch_prof_v3')||'{}')||{}; }catch(e){ return {}; }
  }
  /* islak imza gerektiren belge mi? */
  function needsWet(name,doc){
    var h=((name||'')+' '+(doc||'')).toLowerCase();
    return /k(ü|ue|u)ndigung[^.]{0,40}(arbeit|arbeitsvertrag|anstellung)|arbeitsvertrag|aufhebungsvertrag|b(ü|ue|u)rgschaft|testament|erbausschlagung|istifa|is akdi fesh|iş akdi|iş sözleşmesi fesh/i.test(h);
  }
  var CTX=null; /* {doc,name,cc,orig} */
  var SIG={drawing:false,has:false,ctx:null,canvas:null};

  function ov(){
    var o=document.getElementById('ai-ov'); if(o) return o;
    o=document.createElement('div'); o.id='ai-ov';
    o.innerHTML='<div id="ai-box"></div>';
    document.body.appendChild(o);
    o.addEventListener('click',function(e){ if(e.target===o) close(); });
    return o;
  }
  function close(){ var o=document.getElementById('ai-ov'); if(o) o.classList.remove('on'); }

  function setupSig(){
    var c=document.getElementById('ai-sigpad'); if(!c) return;
    try{ c.width=c.clientWidth*2; c.height=c.clientHeight*2; }catch(e){}
    var ctx=c.getContext('2d'); ctx.scale(2,2); ctx.lineWidth=2.2; ctx.lineCap='round'; ctx.strokeStyle='#0b1a3a';
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

  function esc(s){ return String(s==null?'':s).replace(/[&<>]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;'}[c];}); }
  /* imzali belgeyi kendi A4 html'i ile garantili yazdir */
  function printSigned(doc,name,cc,sig){
    var html='<!doctype html><html><head><meta charset="utf-8"><title>'+esc(name||'ChatHelp')+'</title>'
      +'<style>@page{size:A4;margin:0}html,body{margin:0;padding:0}'
      +'.a4{width:794px;min-height:1123px;padding:78px 76px 90px;background:#fff;color:#1e1e26;'
      +"font-family:'Inter','Segoe UI','Noto Sans',Arial,sans-serif;font-size:12.5px;line-height:1.85;"
      +'white-space:pre-wrap;overflow-wrap:anywhere;word-break:break-word;box-sizing:border-box}'
      +'.sig{margin-top:26px}.sig img{height:64px}</style></head><body><div class="a4">'
      +esc(doc)+'<div class="sig"><img src="'+sig+'" alt="Unterschrift"></div></div></body></html>';
    try{ if(typeof window.chVsfExportPDF==='function'){ window.chVsfExportPDF(html); return true; } }catch(e){}
    try{ var w=window.open('','_blank'); if(w){ w.document.open(); w.document.write(html+'<scr'+'ipt>window.onload=function(){setTimeout(function(){window.print();},400);}<\/scr'+'ipt>'); w.document.close(); return true; } }catch(e){}
    return false;
  }
  function doFree(){
    var P=prof();
    var f1=(document.getElementById('ai-f1')||{}).value, f3=(document.getElementById('ai-f3')||{}).value, f4=(document.getElementById('ai-f4')||{}).value;
    /* profile kaydet + belgede eski adresi guncelle */
    var doc=CTX.doc;
    try{
      if(f3!==undefined && P.f3 && f3 && f3!==P.f3) doc=doc.split(P.f3).join(f3);
      if(f4!==undefined && P.f4 && f4 && f4!==P.f4) doc=doc.split(P.f4).join(f4);
      if(window.P){ if(f3!==undefined) window.P.f3=f3; if(f4!==undefined) window.P.f4=f4; }
      var pv=JSON.parse(localStorage.getItem('ch_prof_v3')||'{}')||{}; if(f3!==undefined)pv.f3=f3; if(f4!==undefined)pv.f4=f4; localStorage.setItem('ch_prof_v3',JSON.stringify(pv));
    }catch(e){}
    close();
    var sig=sigData();
    if(sig){ printSigned(doc,CTX.name,CTX.cc,sig); return; }
    window.__pdfConfirmed=true;
    try{ CTX.orig(doc,CTX.name,CTX.cc); }catch(e){ try{ CTX.orig(CTX.doc,CTX.name,CTX.cc); }catch(e2){} }
  }
  function doMail(){
    try{
      var P=prof();
      var req={ ts:new Date().getTime(), name:(document.getElementById('ai-f1')||{}).value||'', adr:(document.getElementById('ai-f3')||{}).value||P.f3||'', plz:(document.getElementById('ai-f4')||{}).value||P.f4||'', doc:String(CTX.doc||'').slice(0,4000), title:CTX.name||'', cc:CTX.cc||'DE', sig:sigData()?1:0, paid:0 };
      var a=JSON.parse(localStorage.getItem('ch_mailorig_req')||'[]'); if(!Array.isArray(a)) a=[]; a.push(req); localStorage.setItem('ch_mailorig_req',JSON.stringify(a.slice(-50)));
    }catch(e){}
    close();
    /* mevcut odeme boru hatti hazir oldugunda: window.stripeCheckout(null,'mailorig','standard') */
    try{ alert(T('mailok')); }catch(e){}
  }

  function render(){
    var P=prof();
    var box=document.getElementById('ai-box'); if(!box) return;
    var wet=needsWet(CTX.name,CTX.doc);
    var h='<h3>📮 '+T('ttl')+'</h3><div class="sub">'+T('sub')+'</div>';
    h+='<div class="ai-f"><label>'+T('name')+'</label><input class="ai-in" id="ai-f1" value="'+esc(((P.f1||'')+' '+(P.f2||'')).trim())+'"></div>';
    h+='<div class="ai-f"><label>'+T('adr')+'</label><input class="ai-in" id="ai-f3" value="'+esc(P.f3||'')+'"></div>';
    h+='<div class="ai-f"><label>'+T('plz')+'</label><input class="ai-in" id="ai-f4" value="'+esc(P.f4||'')+'"></div>';
    h+='<div class="ai-sec">'+T('sigsec')+'</div>';
    if(wet){ h+='<div class="ai-warn">'+T('sigwet')+'</div>'; }
    else{
      h+='<div class="sub" style="margin-bottom:6px">'+T('sigok')+'</div>';
      h+='<canvas id="ai-sigpad"></canvas><div class="ai-sigrow"><button id="ai-sigclr">🗑 '+T('clr')+'</button></div>';
    }
    h+='<div class="ai-acts"><button id="ai-free">'+T('free')+'</button><button id="ai-mail">'+T('mail')+'</button><button id="ai-cancel">'+T('cancel')+'</button></div>';
    box.innerHTML=h;
    if(!wet){ setupSig(); var cb=document.getElementById('ai-sigclr'); if(cb) cb.onclick=clearSig; }
    document.getElementById('ai-free').onclick=doFree;
    document.getElementById('ai-mail').onclick=doMail;
    document.getElementById('ai-cancel').onclick=close;
  }

  /* makePDF sarma */
  var g=0;(function wrap(){
    try{
      if(typeof window.makePDF==='function' && !window.makePDF.__adr){
        var _m=window.makePDF;
        var w=function(doc,name,cc){
          try{
            if(window.__pdfConfirmed){ window.__pdfConfirmed=false; return _m.apply(this,arguments); }
            /* SADECE ALMANYA: DE disi belgeler dogrudan PDF olur */
            var dcc=String(cc||CCX()||'DE').toUpperCase();
            if(dcc!=='DE'){ return _m.apply(this,arguments); }
            CTX={doc:String(doc||''),name:name||'Dokument',cc:'DE',orig:_m};
            ov().classList.add('on'); render();
            return;
          }catch(e){ return _m.apply(this,arguments); }
        };
        w.__adr=1; window.makePDF=w;
        try{ if(typeof makePDF==='function'){ /* global ref may exist */ } }catch(e){}
      }
    }catch(e){}
    if(g++<400) setTimeout(wrap,500);
  })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'ai').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-adresimza-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_ADRES_IMZA')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_ADRES_IMZA diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Dilekce cikmadan once ADRES ONAYI ekrani:\n";
echo "   • Konto/profil adresi gosterilir, duzenlenebilir (belgeye + profile islenir)\n";
echo "   • Islak imza gerektiren belgede uyari; digerlerinde EKRAN IMZASI (canvas)\n";
echo "   • '📄 Ucretsiz kendim yazdir' / '✉️ Orijinali evime postala 2,99 €' (siparis kaydi)\n";
echo "   Not: 2,99 gercek odeme+gonderim, Stripe fiyati + PixelLetter hesabi baglaninca aktiflesir.\n";
