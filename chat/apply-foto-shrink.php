<?php
/**
 * ChatHelp — apply-foto-shrink (CH_FOTO_SHRINK) — foto analizi "sunucu bos yanit"
 *  KESIN cozum. Kok neden: buyuk telefon fotograflari -> base64 Anthropic ~5MB
 *  (ve PHP post_max_size) sinirini asiyor -> vision API 400 -> bos sonuc. Bazi
 *  telefonlarda calisip bazilarinda calismamasinin sebebi bu (boyuta bagli).
 *  COZUM (additive, mevcut kodu bozmaz): window.fetch sarmalanir; action=
 *  analyze_photo istegindeki resim GONDERILMEDEN ONCE canvas ile kucultulur
 *  (en uzun kenar 1568px, JPEG q0.82), 'data:...;base64,' oneki temizlenir,
 *  mime image/jpeg'e normalize edilir. PDF (application/pdf) DOKUNULMAZ, sadece
 *  varsa data-uri oneki temizlenir. Kucuk resim de yeniden kodlanir (tutarlilik).
 * KULLANIM: pull2.php?key=...&files=apply-foto-shrink.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-foto-shrink BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_FOTO_SHRINK')!==false) exit("Zaten ekli (CH_FOTO_SHRINK).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-foto-shrink-js">
/* CH_FOTO_SHRINK — analyze_photo resmini gonderMEDEN once kucult (boyut sinirini as) */
try{(function(){
  if(window.__chFotoShrink) return; window.__chFotoShrink=1;
  var MAXPX=1568, Q=0.82;
  function stripPrefix(s){ s=String(s||''); var i=s.indexOf('base64,'); return i>=0? s.slice(i+7): s; }
  function shrink(b64, mime){
    return new Promise(function(res){
      try{
        var img=new Image();
        img.onload=function(){
          try{
            var w=img.naturalWidth||img.width, h=img.naturalHeight||img.height;
            if(!w||!h){ res(null); return; }
            var scale=Math.min(1, MAXPX/Math.max(w,h));
            var nw=Math.max(1,Math.round(w*scale)), nh=Math.max(1,Math.round(h*scale));
            var cv=document.createElement('canvas'); cv.width=nw; cv.height=nh;
            var ctx=cv.getContext('2d');
            ctx.fillStyle='#fff'; ctx.fillRect(0,0,nw,nh);
            ctx.drawImage(img,0,0,nw,nh);
            var durl=cv.toDataURL('image/jpeg', Q);
            res(stripPrefix(durl)||null);
          }catch(e){ res(null); }
        };
        img.onerror=function(){ res(null); };
        img.src='data:'+(mime||'image/jpeg')+';base64,'+b64;
      }catch(e){ res(null); }
    });
  }
  var _f=window.fetch;
  window.fetch=function(input, init){
    try{
      var url=(input&&input.url)||input||'';
      if(String(url).indexOf('analyze_photo')>=0 && init && typeof init.body==='string'){
        var body=null; try{ body=JSON.parse(init.body); }catch(e){}
        if(body && body.b64){
          var raw=stripPrefix(body.b64);
          var mime=body.mime||'image/jpeg';
          var self=this, inp=input, ini=init;
          if(String(mime).indexOf('image/')===0){
            return shrink(raw, mime).then(function(nb){
              if(nb){ body.b64=nb; body.mime='image/jpeg'; }
              else   { body.b64=raw; } /* en azindan prefix temiz */
              ini.body=JSON.stringify(body);
              return _f.call(self, inp, ini);
            });
          } else {
            body.b64=raw; ini.body=JSON.stringify(body); /* PDF: sadece prefix temizle */
            return _f.call(self, inp, ini);
          }
        }
      }
    }catch(e){}
    return _f.apply(this, arguments);
  };
})();}catch(e){}
</script>
HTMLBLOCK;

$pos=strripos($src,'</body>');
if($pos===false) exit("</body> bulunamadi\n");
$new=substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp=tempnam(sys_get_temp_dir(),'fs').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-fotoshrink-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_FOTO_SHRINK')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_FOTO_SHRINK eklendi (".strlen($chk)." B)\n";
echo "✓ Foto gonderMEDEN once tarayicida 1568px'e kucultuluyor -> boyut sinirini asmaz.\n";
echo "✓ data:-oneki temizlenir, mime image/jpeg'e normalize edilir; PDF dokunulmaz.\n";
echo "  Test: buyuk foto ceken telefonda belge yukle -> artik analiz gelmeli.\n";
