<?php
/**
 * ChatHelp — apply-mic-smart2 (CH_MICSMART2) — mic SENKRON klavye-acilis.
 *  Sorun: asenkron getUserMedia dokunma-gesture'ini kaybediyor -> input.focus()
 *  klavyeyi acmiyor -> "mic calismiyor".
 *  Cozum: WebView'i SENKRON algila (UA " wv)" veya SpeechRecognition yok) ->
 *  dokunma aninda inp.focus() (klavye acilir). Tarayicida premium ekran.
 *  window.startVoice override. Observer YOK. node ✓.
 * KULLANIM: pull2.php?key=...&files=apply-mic-smart2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-mic-smart2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_MICSMART2')!==false) exit("Zaten ekli (CH_MICSMART2).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-mic-smart2">
/* CH_MICSMART2 — WebView'de SENKRON klavye-acilis; tarayicida premium ekran */
try{(function(){
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  var HINT={de:'Tippen Sie auf das 🎤 Ihrer Tastatur und sprechen Sie.',tr:'Klavyenizdeki 🎤 simgesine dokunup konuşun.',en:'Tap the 🎤 on your keyboard and speak.',fr:'Touchez le 🎤 de votre clavier et parlez.',es:'Toque el 🎤 de su teclado y hable.',it:'Tocca il 🎤 della tastiera e parla.'};
  function hint(){ return HINT[lang()]||HINT.de; }
  function kbFocus(){
    var inp=document.getElementById('inp');
    if(inp){
      try{ inp.removeAttribute('readonly'); }catch(e){}
      try{ inp.scrollIntoView({block:'center'}); }catch(e){}
      try{ inp.focus(); }catch(e){}
      try{ inp.click(); }catch(e){}
      /* bazi WebView'lerde tekrar focus gerek */
      setTimeout(function(){ try{ inp.focus(); }catch(e){} },60);
    }
    try{ if(typeof window.toast==='function') window.toast(hint()); }catch(e){}
  }
  window.startVoice=function(){
    try{
      var ua=(navigator.userAgent||'');
      var hasSR=!!(window.SpeechRecognition||window.webkitSpeechRecognition);
      var isWV=(/ wv\)/i.test(ua)) || (/\bwv\b/i.test(ua)) || !hasSR;
      if(isWV){ kbFocus(); return; }             /* SENKRON — klavye acilir */
      if(typeof window.chOpenMic==='function'){ window.chOpenMic(); return; }
      kbFocus();
    }catch(e){ try{ kbFocus(); }catch(_){}}
  };
  window.chKbFocus=kbFocus;
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'s2').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-micsmart2-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_MICSMART2')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_MICSMART2 diskte (".strlen($chk)." bayt)\n";
echo "\n✓ WebView'de mic -> SENKRON klavye acilir; tarayicida premium ses ekrani.\n";
