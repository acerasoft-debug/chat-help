<?php
/**
 * ChatHelp — apply-mic-wv-direct (CH_MIC_WVDIRECT) — App'te mikrofon KESIN cozum
 *  (sunucudan yapilabilecek en iyi): App WebView'inde getUserMedia native izin
 *  koprusu (WebChromeClient.onPermissionRequest) olmadigi icin GUVENILMEZ —
 *  CH_MIC_APPTO 2.2sn bekleyip klavyeye dusuyordu; kullanici icin bu hala
 *  "calismiyor" hissi. YENI: Android WebView tespit edilirse ("; wv)" UA
 *  isareti / eski WebView kalibi / window.chIsApp) mikrofon butonu HIC
 *  getUserMedia denemeden ANINDA klavye-dikteye gecer + ilk seferde kisa
 *  ipucu gosterir ("klavyedeki mikrofon tusuna basin"). Tarayicida (sitede)
 *  HICBIR SEY DEGISMEZ — normal premium mikrofon calismaya devam eder.
 *  Sadece EKLEMELI (wrap), mevcut kod DEGISMEZ. node ✓ (3 senaryo).
 * KULLANIM: pull2.php?key=...&files=apply-mic-wv-direct.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-mic-wv-direct BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_MIC_WVDIRECT')!==false) exit("Zaten ekli (CH_MIC_WVDIRECT).\n");
if (strpos($src,'window.chOpenMic')===false) exit("HATA: chOpenMic yok — once mikrofon modulleri gerekli.\n");

$block = <<<'HTMLBLOCK'
<script id="ch-mic-wvdirect-js">
/* CH_MIC_WVDIRECT — App WebView: mikrofon dogrudan klavye-dikteye (getUserMedia hic denenmez) */
try{(function(){
  function isWV(){
    try{
      var ua=navigator.userAgent||'';
      if(ua.indexOf('; wv)')!==-1) return true;                                        /* standart Android WebView */
      if(/Android/.test(ua) && /Version\/\d/.test(ua) && /Chrome\//.test(ua)) return true; /* eski WebView kalibi */
      if(window.chIsApp===true) return true;                                            /* app kendini tanitirsa */
      return false;
    }catch(e){ return false; }
  }
  function hint(){
    try{
      if(sessionStorage.getItem('ch_wvmic_hint')) return;
      sessionStorage.setItem('ch_wvmic_hint','1');
      var t=document.createElement('div');
      t.setAttribute('style','position:fixed;left:50%;bottom:86px;transform:translateX(-50%);z-index:99999;'
        +'background:rgba(15,22,52,.96);color:#f0e6c8;border:1px solid rgba(212,168,74,.45);border-radius:12px;'
        +'padding:10px 16px;font-size:13px;font-weight:600;max-width:86vw;text-align:center;box-shadow:0 8px 30px rgba(0,0,0,.45)');
      t.textContent='🎤 Tastatur-Mikrofon nutzen · Klavyedeki mikrofon tuşuna basın';
      document.body.appendChild(t);
      setTimeout(function(){ try{ t.remove(); }catch(e){} },4500);
    }catch(e){}
  }
  function kb(){
    try{ if(typeof window.chKbFocus==='function'){ window.chKbFocus(); hint(); return; } }catch(e){}
    try{ var i=document.getElementById('inp'); if(i){ i.focus(); i.click(); hint(); } }catch(e){}
  }
  function wrap(){
    try{
      if(typeof window.chOpenMic!=='function' || window.chOpenMic.__wvdirect) return false;
      var _o=window.chOpenMic;
      var w=function(){
        if(isWV()){ kb(); return; }        /* App: hic bekletmeden klavye mikrofonu */
        return _o.apply(this,arguments);   /* Site/tarayici: normal premium mikrofon */
      };
      w.__wvdirect=1; window.chOpenMic=w;
      return true;
    }catch(e){ return false; }
  }
  var n=0;(function loop(){ if(!wrap() && n++<200) setTimeout(loop,300); })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'wv').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-wvdirect-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_MIC_WVDIRECT')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_MIC_WVDIRECT diskte (".strlen($chk)." bayt)\n";
echo "\n✓ App'te mikrofon butonu artik ANINDA klavye-dikteye gecer (bekleme yok),\n";
echo "   ilk kullanimda '🎤 klavyedeki mikrofon tusuna basin' ipucu gosterilir.\n";
echo "   Sitede/tarayicida premium mikrofon aynen calismaya devam eder.\n";
echo "   NOT: App icinde GERCEK dahili mikrofon istenirse native kod sarttir\n";
echo "   (WebChromeClient.onPermissionRequest + RECORD_AUDIO) — sunucudan yapilamaz.\n";
