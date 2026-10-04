<?php
/**
 * ChatHelp — apply-gendbg (CH_GENDBG) — GECICI teshis: "Dokument generieren"
 *  neden calismiyor? Gercek hatayi EKRANDA gosterir.
 *   • window error + unhandledrejection -> alert (gizli JS hatasi/reddi yakalar)
 *   • genDoc / showPaywall / showToneSelector / showProc sarilir -> hangisi
 *     cagrildi + icinde hata varsa gosterir.
 *  Kullanimdan sonra apply-gendbg-off.php ile kaldirilir.
 * KULLANIM: pull2.php?key=...&files=apply-gendbg.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-gendbg BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("okunamadi\n");
if(strpos($src,'CH_GENDBG')!==false) exit("Zaten ekli.\n");

$block = <<<'HTMLBLOCK'
<script id="ch-gendbg">
/* CH_GENDBG — gecici uretim teshisi */
try{(function(){
  var last='';
  function show(m){ try{ var d=document.getElementById('gendbg'); if(!d){ d=document.createElement('div'); d.id='gendbg'; d.style.cssText='position:fixed;left:6px;right:6px;bottom:6px;z-index:2147483647;background:#1a0d0d;color:#ffd0d0;border:1px solid #a44;border-radius:10px;padding:10px 12px;font:12px/1.5 monospace;max-height:45vh;overflow:auto;white-space:pre-wrap'; d.onclick=function(){ d.remove(); }; document.body.appendChild(d); } d.textContent='🔎 GENDBG (dokun=kapat)\n'+m+'\n\n'+last; last=m+'\n'+last; }catch(e){} }
  window.addEventListener('error',function(e){ show('❌ JS-Fehler: '+(e.message||'')+'\n  @ '+(e.filename||'').split('/').pop()+':'+e.lineno+':'+e.colno); });
  window.addEventListener('unhandledrejection',function(e){ var r=e.reason; show('❌ Promise-Reject: '+(r&&(r.message||r)||'?')+'\n'+((r&&r.stack)||'').split('\n').slice(0,4).join('\n')); });
  function wrap(name){
    var g=0;(function w(){
      try{
        if(typeof window[name]==='function' && !window[name].__dbg){
          var _o=window[name];
          var nf=function(){ show('▶ '+name+'() cagrildi (arg0='+(arguments[0]!=null?String(arguments[0]).slice(0,40):'')+')');
            try{ var r=_o.apply(this,arguments); if(r&&r.then){ r.then(function(){ show('✓ '+name+' bitti'); },function(err){ show('❌ '+name+' HATA: '+(err&&(err.message||err))+'\n'+((err&&err.stack)||'').split('\n').slice(0,4).join('\n')); }); } return r; }
            catch(err){ show('❌ '+name+' THROW: '+(err&&(err.message||err))+'\n'+((err&&err.stack)||'').split('\n').slice(0,4).join('\n')); throw err; } };
          nf.__dbg=1; window[name]=nf;
        }
      }catch(e){}
      if(g++<400) setTimeout(w,600);
    })();
  }
  ['genDoc','showPaywall','showToneSelector','showProc','makePDF','chPrintDoc'].forEach(wrap);
  show('hazir — butona bas, ne oldugunu burada gosterir.');
})();}catch(e){}
</script>
HTMLBLOCK;

$pos=strripos($src,'</body>');
if($pos===false) exit("</body> yok\n");
$src=substr($src,0,$pos).$block."\n".substr($src,$pos);
$tmp=tempnam(sys_get_temp_dir(),'gd').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-gendbg-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false) exit("YAZMA HATASI\n");
if(function_exists('opcache_reset')) @opcache_reset();
echo "  ✓ CH_GENDBG eklendi. Sayfayi yenile, 'Dokument generieren'e bas.\n";
echo "  Alt kisimda kirmizi kutuda ne oldugunu gorursun -> bana yapistir.\n";
echo "  Kaldirmak: apply-gendbg-off.php\n";
