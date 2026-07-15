<?php
/**
 * ChatHelp — apply-mic-keyboard (CH_MICKB) — kolay sesli giris: klavye diktesi.
 *  Android WebView web-mikrofonu vermiyor ("Zugriff verweigert"). En kolay/guvenilir
 *  yontem telefon klavyesinin mikrofonu (OS diktesi). Mic ekranina her zaman gorunur
 *  "⌨️ Klavye ile dikte et" butonu eklenir: tiklayinca overlay kapanir, yazi alanina
 *  odaklanir (klavye acilir) — kullanici klavye mikrofonuyla konusarak yazar.
 *  Izin reddedilince bu buton one cikar. Saf EKLEMELI JS. node --check ✓.
 * KULLANIM: pull2.php?key=...&files=apply-mic-keyboard.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-mic-keyboard BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_MICKB')!==false) exit("Zaten ekli (CH_MICKB).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-mickb-css">
/* CH_MICKB — klavye diktesi butonu */
.cm2-kb{width:100%;margin-top:10px;padding:13px;border-radius:14px;border:1px dashed rgba(212,168,74,.5);
  background:rgba(212,168,74,.09);color:var(--gold,#d4a84a);font-size:14px;font-weight:800;cursor:pointer;font-family:inherit;transition:.15s}
.cm2-kb:hover{background:rgba(212,168,74,.18)}
.cm2-card.kb-emph .cm2-kb{background:linear-gradient(135deg,#c49038,#e8b840);color:#1a0e00;border-style:solid;box-shadow:0 6px 20px rgba(212,168,74,.3)}
</style>
<script id="ch-mickb-js">
/* CH_MICKB — klavye ile dikte (WebView icin kolay yontem) */
try{(function(){
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  var KB={de:'⌨️ Über Tastatur diktieren',tr:'⌨️ Klavye ile konuşarak yaz',en:'⌨️ Dictate with keyboard',fr:'⌨️ Dicter avec le clavier',es:'⌨️ Dictar con el teclado',it:'⌨️ Detta con la tastiera'};
  var HINT={de:'Tippen Sie auf das 🎤 Ihrer Tastatur und sprechen Sie.',tr:'Klavyenizdeki 🎤 simgesine dokunup konuşun.',en:'Tap the 🎤 on your keyboard and speak.',fr:'Touchez le 🎤 de votre clavier et parlez.',es:'Toque el 🎤 de su teclado y hable.',it:'Tocca il 🎤 della tastiera e parla.'};
  function txt(m){ return m[lang()]||m.de; }
  function toast(msg){
    try{ if(typeof window.toast==='function'){ window.toast(msg); return; } }catch(e){}
    try{ if(typeof addMsg==='function'){ /* sessiz */ } }catch(e){}
  }
  function keyboardDictate(){
    try{
      var h=document.getElementById('ch-mic2'); if(h) h.classList.remove('on');
      var m1=document.getElementById('ch-mic'); if(m1) m1.classList.remove('on');
      var inp=document.getElementById('inp');
      if(inp){ try{ inp.focus(); }catch(e){} try{ inp.click(); }catch(e){} setTimeout(function(){ try{ inp.focus(); }catch(e){} },120); }
      toast(txt(HINT));
    }catch(e){}
  }
  window.chKeyboardDictate=keyboardDictate;

  function inject(){
    try{
      var card=document.querySelector('#ch-mic2 .cm2-card'); if(!card) return;
      if(!card.__kb){
        card.__kb=1;
        var b=document.createElement('button'); b.type='button'; b.className='cm2-kb';
        b.textContent=txt(KB);
        b.addEventListener('click',function(ev){ ev.stopPropagation(); keyboardDictate(); });
        card.appendChild(b);
      } else {
        var bb=card.querySelector('.cm2-kb'); if(bb) bb.textContent=txt(KB);
      }
      /* izin reddedilince butonu one cikar */
      try{
        var st=card.querySelector('.cm2-status'); var txt2=(st&&st.textContent)||'';
        var denied=/verweigert|reddedildi|denied|refusé|denegado|negato|desteklenmiyor|not supported|nicht unterstützt/i.test(txt2);
        card.classList.toggle('kb-emph', denied);
      }catch(e){}
    }catch(e){}
  }
  var n=0;(function loop(){ inject(); if(n++<400) setTimeout(loop,600); })();
  try{ if(window.MutationObserver){ new MutationObserver(inject).observe(document.body,{childList:true,subtree:true}); } }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'kb').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-mickb-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_MICKB')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_MICKB diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Klavye diktesi butonu eklendi; izin reddedilince one cikar.\n";
