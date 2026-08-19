<?php
/**
 * ChatHelp — apply-fotoread-fix (CH_FOTOREAD_FIX) — ana sayfa foto okuyucu.
 *  Sorun: composer-premium eski "+" foto tetikleyicisini gizledi; premium 📎 ataç
 *  fotoyu topluyor ama OKUMUYOR (analyze_photo tetiklenmiyor).
 *  Cozum (EKLEMELI): 📎 input'una CAPTURE-fazi change dinleyici ekle -> secilen
 *  her RESIM icin analyze_photo cagir (OCR/ozet), sohbete ozet yaz. Odeme duvari
 *  mevcut fetch yakalayicisiyla ayni (ucretsiz kullanicida paywall). Gomulu JS node ✓.
 * KULLANIM: pull2.php?key=...&files=apply-fotoread-fix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fotoread-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_FOTOREAD_FIX')!==false) exit("Zaten ekli (CH_FOTOREAD_FIX).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-fotoread-fix">
/* CH_FOTOREAD_FIX — premium 📎 ataca resim eklenince otomatik foto-okuma (analyze_photo) */
try{(function(){
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de'); }catch(e){ return 'de'; } }
  var RD={de:'🔍 <b>Dokument wird gelesen…</b>',tr:'🔍 <b>Belge okunuyor…</b>',en:'🔍 <b>Reading document…</b>',fr:'🔍 <b>Lecture du document…</b>',es:'🔍 <b>Leyendo el documento…</b>',it:'🔍 <b>Lettura del documento…</b>'};
  function rdMsg(){ return RD[lang().slice(0,2)]||RD.de; }
  function say(html){
    try{ if(typeof addMsg==='function'){ addMsg('ai', html); return; } }catch(e){}
    try{ if(typeof toast==='function'){ toast(html); } }catch(e){}
  }
  function progress(){ try{ if(typeof toast==='function'){ toast(rdMsg()); } }catch(e){} }
  function analyzeImg(file){
    try{
      if(!file || !/^image\//.test(file.type||'')) return;
      var fr=new FileReader();
      fr.onload=function(e){
        try{
          var b64=(''+e.target.result).split(',')[1];
          progress();
          var url=((typeof API!=='undefined'&&API)?API:'api.php')+'?action=analyze_photo';
          fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},
            body:JSON.stringify({b64:b64,mime:file.type,lang:lang()})})
          .then(function(r){ return r.json(); })
          .then(function(d){
            try{
              if(!d || d.error) return; /* paywall/hata mevcut yakalayicida */
              var r=(d.result||d.ocr||{});
              if(r && r.error) return;
              var sum=r.zusammenfassung||r.summary||r.text||r.ocr||'';
              if(sum){ say('🖼️ '+String(sum)); }
            }catch(_e){}
          }).catch(function(){});
        }catch(_e){}
      };
      fr.readAsDataURL(file);
    }catch(e){}
  }
  function onPick(ev){
    try{
      var inp=ev.target; if(!inp || !inp.files) return;
      Array.prototype.slice.call(inp.files).forEach(function(f){ analyzeImg(f); });
    }catch(e){}
  }
  function bind(){
    try{
      document.querySelectorAll('.ch-att-btn input[type=file]').forEach(function(inp){
        if(inp.__fread) return; inp.__fread=1;
        /* CAPTURE fazi: composer'in onPick'i input.value''yi silmeden ONCE dosyalari oku */
        inp.addEventListener('change', onPick, true);
      });
    }catch(e){}
  }
  var n=0;(function loop(){ bind(); if(n++<160) setTimeout(loop,600); })();
  try{ if(window.MutationObserver){ new MutationObserver(bind).observe(document.body,{childList:true,subtree:true}); } }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'fr').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-fotoread-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_FOTOREAD_FIX')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_FOTOREAD_FIX diskte (".strlen($chk)." bayt)\n";
echo "\n✓ 📎 ataca eklenen resimler artik otomatik okunuyor (analyze_photo -> sohbete ozet).\n";
