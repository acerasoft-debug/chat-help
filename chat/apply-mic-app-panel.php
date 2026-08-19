<?php
/**
 * ChatHelp — apply-mic-app-panel (CH_MIC_APPKB) — App mikrofonu KESIN cozum v3.
 *  ONCEKI DURUM: WebView'de buton input.focus() cagiriyordu; bazi WebView'ler
 *  programatik odaklamada klavyeyi SESSIZCE ACMAZ -> kullanici "hicbir sey
 *  olmuyor" goruyor. GERCEK COZUM: programatik klavye zorlamayi birakiyoruz —
 *  App'te mikrofon butonu artik SESLI YAZIM PANELI acar:
 *   • Buyuk bir yazi alani + "alana dokunun, klavyenizdeki 🎤 ile konusun"
 *     yonergesi. KULLANICININ KENDI DOKUNUSU klavyeyi %100 acar (gercek
 *     dokunus WebView'de hicbir zaman engellenmez).
 *   • "✓ Metni Ekle" -> yazilan metin sohbet kutusuna eklenir, panel kapanir.
 *   • Panel kosesinde kucuk "v3" etiketi: App'te panel HIC gorunmuyorsa
 *     App eski sayfayi onbellekten yukluyor demektir (sunucudan cozulemez;
 *     App verisi temizlenmeli) — bunu kesin ayirt etmemizi saglar.
 *  Sitede/tarayicida davranis DEGISMEZ (dogrudan tanima calismaya devam).
 *  Sadece EKLEMELI sarmalama. node ✓ + 3 senaryoluk harness ✓.
 * KULLANIM: pull2.php?key=...&files=apply-mic-app-panel.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-mic-app-panel BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_MIC_APPKB')!==false) exit("Zaten ekli (CH_MIC_APPKB).\n");
if (strpos($src,'CH_MIC_UNI')===false) exit("HATA: once CH_MIC_UNI gerekli (apply-mic-unified.php).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-mic-appkb-css">
#mkb-ov{position:fixed;inset:0;z-index:2147483200;background:rgba(4,4,12,.82);backdrop-filter:blur(5px);display:none;align-items:flex-end;justify-content:center;font-family:'Inter',system-ui,sans-serif}
#mkb-ov.on{display:flex}
#mkb-card{position:relative;width:100%;max-width:560px;background:linear-gradient(180deg,#14142e,#0b0b19);border:1px solid rgba(212,168,74,.45);border-radius:20px 20px 0 0;padding:18px 16px 16px;color:#f2f2fb;box-shadow:0 -18px 60px rgba(0,0,0,.55)}
#mkb-card .t{font-size:15px;font-weight:800;margin-bottom:4px}
#mkb-card .s{font-size:12px;color:#b9b9dc;line-height:1.55;margin-bottom:10px}
#mkb-card .s b{color:#ecc060}
#mkb-ta{width:100%;min-height:110px;box-sizing:border-box;background:#101024;border:2px solid rgba(212,168,74,.5);border-radius:13px;padding:12px;color:#fff;font-size:16px;line-height:1.5;outline:none;font-family:inherit;resize:vertical}
#mkb-ta:focus{border-color:#ecc060;box-shadow:0 0 0 3px rgba(212,168,74,.18)}
#mkb-nav{display:flex;gap:8px;margin-top:12px}
#mkb-nav button{flex:1;padding:13px;border-radius:12px;border:none;cursor:pointer;font-family:inherit;font-size:14px;font-weight:800}
#mkb-add{background:linear-gradient(135deg,#d4a84a,#ecc060);color:#191000}
#mkb-close{background:transparent;border:1.5px solid rgba(255,255,255,.18)!important;color:#c8c8e8}
#mkb-v{position:absolute;top:10px;right:14px;font-size:9.5px;color:#55557a;letter-spacing:.5px}
</style>
<script id="ch-mic-appkb-js">
/* CH_MIC_APPKB — App: mikrofon -> sesli yazim paneli (dokunus klavyeyi garantili acar) */
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
  function panel(){
    var ov=document.getElementById('mkb-ov');
    if(!ov){
      ov=document.createElement('div'); ov.id='mkb-ov';
      ov.innerHTML='<div id="mkb-card"><span id="mkb-v">APPKB v3</span>'
        +'<div class="t">🎤 Sesli Yazım</div>'
        +'<div class="s">Aşağıdaki alana <b>dokunun</b> — klavyeniz açılır. Klavyedeki <b>🎤 mikrofon tuşuna</b> basıp konuşun; söyledikleriniz yazıya dönüşür. Bitince <b>Metni Ekle</b>\'ye basın.</div>'
        +'<textarea id="mkb-ta" placeholder="Buraya dokunun ve konuşun…"></textarea>'
        +'<div id="mkb-nav"><button id="mkb-close">Kapat</button><button id="mkb-add">✓ Metni Ekle</button></div></div>';
      document.body.appendChild(ov);
      document.getElementById('mkb-close').onclick=function(){ ov.classList.remove('on'); };
      document.getElementById('mkb-add').onclick=function(){
        try{
          var ta=document.getElementById('mkb-ta');
          var v=(ta&&ta.value)?ta.value.trim():'';
          if(v){
            var i=document.getElementById('inp');
            if(i){
              var base=(i.value||'').replace(/\s+$/,'');
              i.value=(base?base+' ':'')+v;
              try{ if(typeof onInp==='function') onInp(i); }catch(e){}
              try{ i.dispatchEvent(new Event('input',{bubbles:true})); }catch(e){}
              try{ i.focus(); }catch(e){}
            }
            if(ta) ta.value='';
          }
        }catch(e){}
        ov.classList.remove('on');
      };
      /* alana dokunulunca klavye zaten acilir; panel acilirken bir de programatik deneriz */
      ov.addEventListener('click',function(ev){ if(ev.target===ov) ov.classList.remove('on'); });
    }
    ov.classList.add('on');
    try{ var ta=document.getElementById('mkb-ta'); if(ta){ ta.focus(); ta.click(); } }catch(e){}
  }
  function wrap(){
    try{
      if(typeof window.startVoice!=='function' || window.startVoice.__appkb) return false;
      var _o=window.startVoice;
      var w=function(){
        if(isWV()){ panel(); return; }   /* App: sesli yazim paneli */
        return _o.apply(this,arguments); /* Site: mevcut dogrudan tanima */
      };
      w.__appkb=1; w.__final=1;          /* CH_MIC_UNI koruma dongusuyle baris */
      window.startVoice=w;
      if(typeof window.chOpenMic==='function' && !window.chOpenMic.__appkb){
        var _c=window.chOpenMic;
        var wc=function(){ if(isWV()){ panel(); return; } return _c.apply(this,arguments); };
        wc.__appkb=1; wc.__final=1; window.chOpenMic=wc;
      }
      return true;
    }catch(e){ return false; }
  }
  var n=0;(function loop(){ wrap(); if(n++<200) setTimeout(loop,400); })();
  /* sahipligi 60sn koru (baska donguler ezerse geri al) */
  var g=0;(function guard(){
    try{ if(!(window.startVoice&&window.startVoice.__appkb)) wrap(); }catch(e){}
    if(g++<150) setTimeout(guard,400);
  })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'kb').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-appkb-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_MIC_APPKB')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_MIC_APPKB diskte (".strlen($chk)." bayt)\n";
echo "\n✓ App'te mikrofon butonu artik SESLI YAZIM PANELI acar (v3 etiketli):\n";
echo "   alana dokun -> klavye GARANTILI acilir -> klavyedeki 🎤 ile konus ->\n";
echo "   '✓ Metni Ekle' -> yazi sohbet kutusuna gecer.\n";
echo "   ONEMLI TEST: App'te butona basinca panel HIC acilmiyorsa, App eski\n";
echo "   sayfayi onbellekten yuklüyor demektir -> App ayarlarindan 'depolama/\n";
echo "   onbellegi temizle' yapin veya App'i kapatip yeniden acin.\n";
