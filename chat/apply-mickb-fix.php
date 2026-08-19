<?php
/**
 * ChatHelp — apply-mickb-fix (CH_MICKB2) — ACIL: CH_MICKB sonsuz-dongu freeze fix.
 *  Sorun: CH_MICKB MutationObserver(body) -> inject() -> her cagrida textContent
 *  yeniden set -> mutasyon -> observer tekrar -> SONSUZ DONGU -> app dondu.
 *  Cozum: eski CH_MICKB blogunu (style+script) TAMAMEN cikar; observer'siz,
 *  inject-once, sadece-degisince-emphasis eden CH_MICKB2 ekle.
 * KULLANIM: pull2.php?key=...&files=apply-mickb-fix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-mickb-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");

/* ── ADIM 1: bozuk CH_MICKB blogunu cikar (style baslangici -> script kapanisi) ── */
$removed=0;
$sTag='<style id="ch-mickb-css">';
$sPos=strpos($src,$sTag);
if($sPos!==false){
  $jPos=strpos($src,'<script id="ch-mickb-js">',$sPos);
  if($jPos!==false){
    $end=strpos($src,'</script>',$jPos);
    if($end!==false){
      $end=$end+strlen('</script>');
      /* takip eden newline'i da al */
      if(isset($src[$end]) && $src[$end]==="\n") $end++;
      $src=substr($src,0,$sPos).substr($src,$end);
      $removed=1;
      echo "  ✓ ADIM 1: bozuk CH_MICKB blogu cikarildi ($sPos..$end)\n";
    }
  }
}
if(!$removed) echo "  ⚠ ADIM 1: CH_MICKB blogu bulunamadi (belki zaten temiz) — devam.\n";

if (strpos($src,'CH_MICKB2')!==false) exit("Zaten ekli (CH_MICKB2). ADIM1 sonrasi cikip yazilmadi.\n");

/* ── ADIM 2: dongusuz klavye-dikte butonu ── */
$block = <<<'HTMLBLOCK'
<style id="ch-mickb2-css">
/* CH_MICKB2 — klavye diktesi (observer'siz, freeze-safe) */
.cm2-kb{width:100%;margin-top:10px;padding:13px;border-radius:14px;border:1px dashed rgba(212,168,74,.5);
  background:rgba(212,168,74,.09);color:var(--gold,#d4a84a);font-size:14px;font-weight:800;cursor:pointer;font-family:inherit;transition:.15s}
.cm2-kb:hover{background:rgba(212,168,74,.18)}
.cm2-card.kb-emph .cm2-kb{background:linear-gradient(135deg,#c49038,#e8b840);color:#1a0e00;border-style:solid;box-shadow:0 6px 20px rgba(212,168,74,.3)}
</style>
<script id="ch-mickb2-js">
/* CH_MICKB2 — freeze-safe: MutationObserver YOK; inject-once; emphasis sadece-degisince */
try{(function(){
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  var KB={de:'⌨️ Über Tastatur diktieren',tr:'⌨️ Klavye ile konuşarak yaz',en:'⌨️ Dictate with keyboard',fr:'⌨️ Dicter avec le clavier',es:'⌨️ Dictar con el teclado',it:'⌨️ Detta con la tastiera'};
  var HINT={de:'Tippen Sie auf das 🎤 Ihrer Tastatur und sprechen Sie.',tr:'Klavyenizdeki 🎤 simgesine dokunup konuşun.',en:'Tap the 🎤 on your keyboard and speak.',fr:'Touchez le 🎤 de votre clavier et parlez.',es:'Toque el 🎤 de su teclado y hable.',it:'Tocca il 🎤 della tastiera e parla.'};
  function txt(m){ return m[lang()]||m.de; }
  function keyboardDictate(){
    try{
      var h=document.getElementById('ch-mic2'); if(h) h.classList.remove('on');
      var m1=document.getElementById('ch-mic'); if(m1) m1.classList.remove('on');
      var inp=document.getElementById('inp');
      if(inp){ try{ inp.focus(); }catch(e){} try{ inp.click(); }catch(e){} setTimeout(function(){ try{ inp.focus(); }catch(e){} },120); }
      try{ if(typeof window.toast==='function') window.toast(txt(HINT)); }catch(e){}
    }catch(e){}
  }
  window.chKeyboardDictate=keyboardDictate;
  var lastDenied=null;
  function work(){
    try{
      var card=document.querySelector('#ch-mic2 .cm2-card'); if(!card) return;
      if(!card.__kb2){
        card.__kb2=1;
        var b=document.createElement('button'); b.type='button'; b.className='cm2-kb';
        b.textContent=txt(KB);   /* SADECE bir kez yazilir */
        b.addEventListener('click',function(ev){ ev.stopPropagation(); keyboardDictate(); });
        card.appendChild(b);
      }
      /* emphasis: sadece durum DEGISTIGINDE (mutasyon dogurmaz) */
      var st=card.querySelector('.cm2-status'); var t=(st&&st.textContent)||'';
      var d=/verweigert|reddedildi|denied|refusé|denegado|negato|desteklenmiyor|not supported|nicht unterstützt/i.test(t);
      if(d!==lastDenied){ lastDenied=d; card.classList.toggle('kb-emph', d); }
    }catch(e){}
  }
  /* SADECE interval — MutationObserver YOK (freeze onlenir) */
  var n=0;(function loop(){ work(); if(n++<600) setTimeout(loop,700); })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'kf').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-mickbfix-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_MICKB2')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
$stillOld = (strpos($chk,'ch-mickb-js')!==false) ? "⚠ ESKI BLOK HALA VAR" : "eski blok temiz ✓";
echo "  ✓ DOGRULAMA: CH_MICKB2 diskte (".strlen($chk)." bayt) — $stillOld\n";
echo "\n✓ FREEZE FIX: sonsuz-dongulu observer kaldirildi; klavye butonu dongusuz calisiyor.\n";
