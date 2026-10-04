<?php
/**
 * ChatHelp — apply-kuend-iosbadge (CH_KUEND_FIX + CH_QR_IOS) — 2 additive is:
 *  [A] "Verträge kündigen" karti CONTRACT_CATS('vertraege') yuzunden Vertrag
 *      Studio'ya (sozlesme olusturma) yonleniyordu. Yeni 'kuendigen_de'
 *      kategorisi olusturulur (ayni 11 GERCEK fesih belgesi) ve kart ona
 *      yeniden baglanir -> yonlendirme dodge, dogru fesih sablonlari acilir.
 *  [B] Sol-alt CH_QR widget paneline (Google Play) premium "iPhone (Safari)"
 *      rozeti + 'Ana Ekrana Ekle' adimlari eklenir; pill etiketi genellenir.
 *  Ikisi de </body> oncesi additive script (kirilgan anchor YOK). Lint+opcache.
 * KULLANIM: pull2.php?key=...&files=apply-kuend-iosbadge.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-kuend-iosbadge BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_KUEND_FIX')!==false) exit("Zaten ekli (CH_KUEND_FIX).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-kuend-fix-js">
/* CH_KUEND_FIX — 'Verträge kündigen' -> gercek fesih kategorisi (Studio dodge) */
try{(function(){
  var FB=[
    {k:"kuendigung_handy",ic:"📱",t:"Kündigung Handyvertrag",tier:"einfach"},
    {k:"kuendigung_internet",ic:"🌐",t:"Kündigung Internetvertrag",tier:"einfach"},
    {k:"kuendigung_strom",ic:"⚡",t:"Kündigung Stromvertrag",tier:"einfach"},
    {k:"kuendigung_fitness",ic:"🏋️",t:"Kündigung Fitnessstudio",tier:"einfach"},
    {k:"kuendigung_streaming",ic:"🎬",t:"Kündigung Streaming-Abo",tier:"einfach"},
    {k:"kuendigung_versicherung",ic:"🛡️",t:"Kündigung Versicherung",tier:"einfach"},
    {k:"kuendigung_kfz",ic:"🚗",t:"Kündigung KFZ-Versicherung",tier:"einfach"},
    {k:"kuendigung_zeitschrift",ic:"📰",t:"Kündigung Zeitschrift/Abo",tier:"einfach"},
    {k:"kuendigung_festnetz",ic:"☎️",t:"Kündigung Festnetzvertrag",tier:"einfach"},
    {k:"kuendigung_mitgliedschaft",ic:"🎭",t:"Kündigung Mitgliedschaft/Verein",tier:"einfach"},
    {k:"kuendigung_bausparkasse",ic:"🏗️",t:"Kündigung Bausparvertrag",tier:"einfach"}
  ];
  function ensureCat(){
    try{
      if(typeof CAT_DOCS==='undefined') return false;
      var base=(CAT_DOCS['vertraege']||[]).filter(function(d){ return d && typeof d.k==='string' && d.k.indexOf('kuendigung')===0; });
      CAT_DOCS['kuendigen_de'] = base.length ? base.slice() : FB;
      try{ if(typeof CAT_LABELS!=='undefined') CAT_LABELS['kuendigen_de']='Verträge kündigen'; }catch(e){}
      return CAT_DOCS['kuendigen_de'].length>0;
    }catch(e){ return false; }
  }
  function repoint(){
    try{
      var cs=document.querySelectorAll('.wcat[onclick]'); var n=0;
      cs.forEach(function(c){
        var oc=c.getAttribute('onclick')||'';
        if(oc.indexOf("showCatDocs('vertraege')")!==-1 || oc.indexOf('showCatDocs("vertraege")')!==-1){
          c.setAttribute('onclick',"showCatDocs('kuendigen_de')");
          c.onclick=function(){ try{ if(typeof showCatDocs==='function') showCatDocs('kuendigen_de'); }catch(e){} return false; };
          n++;
        }
      });
      return n>0;
    }catch(e){ return false; }
  }
  /* guvenlik: repointlenmis kart tiklamasini garanti et (Studio redirect'i ez) */
  window.addEventListener('click',function(ev){
    try{
      var t=ev.target; if(!t||!t.closest) return;
      var card=t.closest('.wcat[onclick]'); if(!card) return;
      if((card.getAttribute('onclick')||'').indexOf('kuendigen_de')!==-1){
        ev.stopImmediatePropagation(); ev.preventDefault();
        if(typeof showCatDocs==='function') showCatDocs('kuendigen_de');
      }
    }catch(e){}
  },true);
  var n=0;(function loop(){ ensureCat(); repoint(); if(n++<80) setTimeout(loop,450); })();
})();}catch(e){}
</script>
<script id="ch-qr-ios-js">
/* CH_QR_IOS — Google Play widget'ina premium iPhone (Safari) rozeti */
try{(function(){
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2);}catch(e){return 'de';} }
  var APPLE='<svg viewBox="0 0 24 24" width="15" height="15" fill="#fff"><path d="M17.05 12.04c-.03-2.6 2.12-3.85 2.22-3.91-1.21-1.77-3.09-2.02-3.76-2.05-1.6-.16-3.12.94-3.93.94-.81 0-2.06-.92-3.39-.9-1.74.03-3.35 1.01-4.25 2.57-1.81 3.15-.46 7.81 1.3 10.37.86 1.25 1.89 2.66 3.24 2.61 1.3-.05 1.79-.84 3.36-.84 1.57 0 2.01.84 3.39.81 1.4-.03 2.28-1.28 3.14-2.54.99-1.46 1.4-2.87 1.42-2.94-.03-.01-2.72-1.05-2.75-4.16zM14.6 4.6c.71-.87 1.19-2.07 1.06-3.27-1.02.04-2.26.68-3 1.54-.66.76-1.24 1.98-1.08 3.15 1.14.09 2.31-.58 3.02-1.42z"/></svg>';
  var L={
   de:{b:"iPhone (Safari)", s:"Safari öffnen → <b>Teilen</b> ⬆️ → <b>„Zum Home‑Bildschirm"</b>", p:"App laden"},
   tr:{b:"iPhone (Safari)", s:"Safari'yi aç → <b>Paylaş</b> ⬆️ → <b>„Ana Ekrana Ekle"</b>", p:"Uygulamayı al"},
   en:{b:"iPhone (Safari)", s:"Open Safari → <b>Share</b> ⬆️ → <b>“Add to Home Screen”</b>", p:"Get the App"},
   fr:{b:"iPhone (Safari)", s:"Safari → <b>Partager</b> ⬆️ → <b>« Sur l'écran d'accueil »</b>", p:"Obtenir l'app"},
   es:{b:"iPhone (Safari)", s:"Safari → <b>Compartir</b> ⬆️ → <b>«Añadir a inicio»</b>", p:"Obtener app"},
   it:{b:"iPhone (Safari)", s:"Safari → <b>Condividi</b> ⬆️ → <b>«Aggiungi a Home»</b>", p:"Scarica app"}
  };
  var n=0;(function loop(){
    try{
      var l=L[lang()]||L.de;
      var pl=document.getElementById('ch-qr-pl'); if(pl && l.p) pl.textContent=l.p;
      var panel=document.getElementById('ch-qr-panel');
      if(panel && !document.getElementById('ch-qr-ios-wrap')){
        var wrap=document.createElement('div'); wrap.id='ch-qr-ios-wrap';
        wrap.style.cssText='margin-top:9px;border-top:1px solid rgba(212,168,74,.16);padding-top:9px';
        wrap.innerHTML='<button id="ch-qr-ios" style="display:flex;align-items:center;justify-content:center;gap:7px;width:100%;background:linear-gradient(135deg,#1c1c28,#0e0e18);color:#fff;border:1px solid rgba(255,255,255,.2);border-radius:9px;padding:9px;font-size:12.5px;font-weight:800;cursor:pointer">'+APPLE+'<span>'+l.b+'</span></button>'+
          '<div id="ch-qr-ios-steps" style="display:none;font-size:11.5px;color:#c8c8e0;line-height:1.55;margin-top:8px;text-align:center">'+l.s+'</div>';
        panel.appendChild(wrap);
        var steps=wrap.querySelector('#ch-qr-ios-steps');
        wrap.querySelector('#ch-qr-ios').addEventListener('click',function(ev){ ev.stopPropagation(); steps.style.display=(steps.style.display==='none'?'block':'none'); });
      }
    }catch(e){}
    if(n++<80) setTimeout(loop,500);
  })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos=strripos($src,'</body>');
if($pos===false) exit("</body> bulunamadi\n");
$new=substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp=tempnam(sys_get_temp_dir(),'ki').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-kuendios-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_KUEND_FIX')===false||strpos($chk,'CH_QR_IOS')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_KUEND_FIX + CH_QR_IOS eklendi (".strlen($chk)." B)\n";
echo "\n✓ [A] 'Verträge kündigen' artik gercek 11 fesih sablonu gosterir (Studio yonlendirmesi yok)\n";
echo "✓ [B] Sol-alt app widget'ina premium 'iPhone (Safari)' rozeti + Ana Ekrana Ekle adimlari\n";
