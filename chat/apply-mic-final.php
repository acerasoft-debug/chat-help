<?php
/**
 * ChatHelp — apply-mic-final (CH_MICFINAL) — mic butonu APK'da "hicbir sey olmuyor".
 *  WebView web-mikrofonu native izin olmadan acilmaz; programatik klavye de sessiz.
 *  Cozum: startVoice override -> (a) gercek getUserMedia dene (native izin varsa
 *  premium ses ekrani chOpenMic acilir), (b) 2.5s timeout/red -> PREMIUM REHBER
 *  modali (klavye mikrofonu ile sesli yazma + yazi alanina git). Sessizlik biter.
 *  Additive, gozlemci YOK (freeze-safe). node --check ✓.
 * KULLANIM: pull2.php?key=...&files=apply-mic-final.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-mic-final BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_MICFINAL')!==false) exit("Zaten ekli (CH_MICFINAL).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-micfinal-css">
/* CH_MICFINAL — sesli giris rehber modali */
#micf-ov{position:fixed;inset:0;z-index:2147483600;background:rgba(4,4,10,.72);backdrop-filter:blur(4px);
  display:none;align-items:center;justify-content:center;padding:22px;font-family:'Inter',system-ui,sans-serif}
#micf-ov.on{display:flex}
#micf-card{width:100%;max-width:400px;background:linear-gradient(180deg,#12122a,#0d0d1c);border:1px solid rgba(212,168,74,.4);
  border-radius:20px;padding:24px 20px;text-align:center;color:#f2f2fb;box-shadow:0 20px 60px rgba(0,0,0,.5)}
#micf-card .mic-ic{width:74px;height:74px;margin:0 auto 14px;border-radius:50%;
  background:linear-gradient(135deg,#d4a84a,#ecc060);display:flex;align-items:center;justify-content:center;font-size:36px;
  box-shadow:0 8px 24px rgba(212,168,74,.35)}
#micf-card h3{font-size:19px;font-weight:800;margin-bottom:6px}
#micf-card .sub{font-size:13px;color:#b8b8cc;margin-bottom:16px;line-height:1.5}
#micf-card .steps{text-align:left;background:#0b0b16;border:1px solid rgba(255,255,255,.07);border-radius:13px;padding:13px 14px;margin-bottom:16px}
#micf-card .steps div{font-size:13px;color:#e7e7ef;padding:5px 0;display:flex;gap:9px;align-items:flex-start}
#micf-card .steps b{color:#e8c874}
#micf-card .go{width:100%;background:linear-gradient(135deg,#d4a84a,#ecc060);color:#07070e;border:none;border-radius:13px;
  padding:14px;font-size:15px;font-weight:800;font-family:inherit;cursor:pointer;margin-bottom:8px}
#micf-card .cls{background:none;border:none;color:#9a9ab0;font-size:13px;cursor:pointer;font-family:inherit;padding:6px}
#micf-card .note{font-size:11px;color:#7e7e8e;margin-top:8px;line-height:1.5}
</style>
<script id="ch-micfinal-js">
try{(function(){
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  var T={
    de:{t:'Spracheingabe',s:'Nutzen Sie das Mikrofon Ihrer Tastatur, um zu diktieren:',
        s1:'Tippen Sie auf das Textfeld',s2:'Tippen Sie auf das <b>🎤</b> Ihrer Tastatur',s3:'Sprechen Sie — der Text erscheint',
        go:'✏️ Zum Textfeld',cl:'Schließen',nt:'Für ein vollautomatisches In-App-Mikrofon ist ein App-Update nötig.'},
    tr:{t:'Sesli Giriş',s:'Yazı yazdırmak için telefon klavyenizin mikrofonunu kullanın:',
        s1:'Yazı alanına dokunun',s2:'Klavyenizdeki <b>🎤</b> simgesine dokunun',s3:'Konuşun — yazıya dönüşür',
        go:'✏️ Yazı alanına git',cl:'Kapat',nt:'Uygulama içi tam otomatik mikrofon için uygulama güncellemesi gerekir.'},
    en:{t:'Voice input',s:'Use your keyboard microphone to dictate:',
        s1:'Tap the text field',s2:'Tap the <b>🎤</b> on your keyboard',s3:'Speak — the text appears',
        go:'✏️ Go to text field',cl:'Close',nt:'A fully automatic in-app microphone needs an app update.'}
  };
  function tr(){ return T[lang()]||T.de; }
  function ovEl(){ var o=document.getElementById('micf-ov'); if(!o){ o=document.createElement('div'); o.id='micf-ov'; document.body.appendChild(o); } return o; }
  function close(){ var o=document.getElementById('micf-ov'); if(o) o.classList.remove('on'); }
  function gotoInput(){ close(); try{ var i=document.getElementById('inp'); if(i){ i.removeAttribute('readonly'); i.scrollIntoView({block:'center'}); i.focus(); i.click(); setTimeout(function(){ try{ i.focus(); }catch(e){} },80); } }catch(e){} }
  function showHint(){
    var L=tr(), o=ovEl();
    o.innerHTML='<div id="micf-card"><div class="mic-ic">🎤</div><h3>'+L.t+'</h3><div class="sub">'+L.s+'</div>'
      +'<div class="steps"><div><b>1.</b><span>'+L.s1+'</span></div><div><b>2.</b><span>'+L.s2+'</span></div><div><b>3.</b><span>'+L.s3+'</span></div></div>'
      +'<button class="go" id="micf-go">'+L.go+'</button><br><button class="cls" id="micf-cls">'+L.cl+'</button>'
      +'<div class="note">'+L.nt+'</div></div>';
    o.classList.add('on');
    var g=document.getElementById('micf-go'); if(g) g.addEventListener('click',gotoInput);
    var c=document.getElementById('micf-cls'); if(c) c.addEventListener('click',close);
    o.addEventListener('click',function(ev){ if(ev.target===o) close(); });
  }
  window.chMicHint=showHint;
  window.startVoice=function(){
    try{
      var md=(navigator.mediaDevices&&navigator.mediaDevices.getUserMedia)?navigator.mediaDevices:null;
      if(!md){ showHint(); return; }
      var done=false;
      var timer=setTimeout(function(){ if(!done){ done=true; showHint(); } },2500);
      md.getUserMedia({audio:true}).then(function(s){
        if(done) { try{ s.getTracks().forEach(function(t){t.stop();}); }catch(e){} return; }
        done=true; clearTimeout(timer);
        try{ s.getTracks().forEach(function(t){ t.stop(); }); }catch(e){}
        if(typeof window.chOpenMic==='function') window.chOpenMic(); else showHint();
      }).catch(function(){ if(!done){ done=true; clearTimeout(timer); showHint(); } });
    }catch(e){ showHint(); }
  };
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'mf').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-micfinal-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_MICFINAL')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_MICFINAL diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Mic butonu: native izin varsa premium ekran; yoksa net rehber modali (sessizlik biter).\n";
