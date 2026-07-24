<?php
/**
 * ChatHelp — apply-micapp (CH_MIC_APPFINAL) — App/WebView'de kompozer mikrofon
 *  butonu (#pb, onclick=startVoice) SpeechRecognition deniyor -> WebView'de calismaz.
 *  Onceki WVDIRECT sadece chOpenMic'i sariyordu, #pb'yi degil -> devreye girmiyordu.
 *  DUZELTME: capture-fazinda #pb tiklamasi yakalanir; App ise startVoice'a HIC
 *  ulasilmadan yazi kutusu (#inp) odaklanip klavye acilir + 'klavye mikrofonuna bas'
 *  ipucu gosterilir. Tarayici/masaustunde normal mikrofon aynen kalir.
 * KULLANIM: pull2.php?key=...&files=apply-micapp.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-micapp BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_MIC_APPFINAL')!==false) exit("Zaten ekli (CH_MIC_APPFINAL).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-mic-appfinal-js">
/* CH_MIC_APPFINAL — App/WebView: mikrofon butonu (#pb) -> klavye diktesi (en son, kazanan katman) */
try{(function(){
  function isWV(){
    try{
      var ua=navigator.userAgent||'';
      if(ua.indexOf('; wv)')!==-1) return true;
      if(/Android/.test(ua)&&/Version\/\d/.test(ua)&&/Chrome\//.test(ua)) return true;
      if(window.chIsApp===true) return true;
      return false;
    }catch(e){ return false; }
  }
  function hint(){
    try{
      var ex=document.getElementById('ch-appmic-hint'); if(ex) return;
      var t=document.createElement('div'); t.id='ch-appmic-hint';
      t.setAttribute('style','position:fixed;left:50%;bottom:92px;transform:translateX(-50%);z-index:2147483000;'
        +'background:rgba(15,22,52,.97);color:#f0e6c8;border:1px solid rgba(212,168,74,.5);border-radius:12px;'
        +'padding:11px 17px;font-size:13px;font-weight:600;max-width:88vw;text-align:center;box-shadow:0 10px 34px rgba(0,0,0,.5)');
      t.textContent='🎤 Tippen Sie auf das Mikrofon Ihrer Tastatur · Klavyedeki mikrofon tuşuna basın';
      document.body.appendChild(t);
      setTimeout(function(){ try{ t.remove(); }catch(e){} },4800);
    }catch(e){}
  }
  function kbFocus(){
    try{
      var i=document.getElementById('inp');
      if(i){ try{ i.focus(); }catch(e){} try{ i.click(); }catch(e){}
        try{ var v=i.value; i.value=''; i.value=v; }catch(e){} }
      hint();
    }catch(e){}
  }
  /* capture-fazi: baska hicbir katman araya giremeden #pb'yi App'te yakala */
  document.addEventListener('click',function(e){
    try{
      if(!isWV()) return;
      var t=e.target;
      while(t && t!==document){ if(t.id==='pb'){ e.stopImmediatePropagation(); if(e.preventDefault)e.preventDefault(); kbFocus(); return; } t=t.parentNode; }
    }catch(_){}
  },true);
  /* ek guvence: App'te #pb inline onclick'ini da temizle */
  var n=0;(function loop(){
    try{
      if(!isWV()) return;                 /* tarayici: dokunma */
      var pb=document.getElementById('pb');
      if(pb && !pb.__appmic){ pb.__appmic=1; pb.onclick=function(ev){ try{ if(ev&&ev.preventDefault)ev.preventDefault(); }catch(_){} kbFocus(); return false; }; pb.setAttribute('title','Tastatur-Mikrofon'); }
    }catch(e){}
    if(n++<200) setTimeout(loop,300);
  })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos=strripos($src,'</body>');
if($pos===false) exit("</body> yok\n");
$src=substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp=tempnam(sys_get_temp_dir(),'ma').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-micapp-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_MIC_APPFINAL')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_MIC_APPFINAL diskte (".strlen($chk)." B)\n";
echo "  Test: App'te mikrofon -> yazi kutusu odaklanir + klavye acilir + ipucu cikar.\n";
echo "  Klavyedeki mikrofona basinca dikte dogrudan kutuya yazar (WebView izni gerekmez).\n";
echo "  Tarayici/masaustunde normal mikrofon aynen calisir.\n";
