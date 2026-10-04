<?php
/**
 * ChatHelp — apply-plandbg3 (CH_PLANDBG3) — SON teshis: Konto sayfasinda, plan
 *  rozetinin HEMEN ALTINA, KALICI (alert degil, sayfada duran) bir bilgi satiri
 *  ekler: token var mi (Y/N), ch_user.plan, P.plan, ch_token'in ilk 12 karakteri.
 *  Boylece "giris yapilmamis mi" yoksa "giris yapilmis ama deger yanlis mi"
 *  kesin ayirt edilir. Sadece GORUNURLUK — davranis degismez.
 * KULLANIM: pull2.php?key=...&files=apply-plandbg3.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-plandbg3 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_PLANDBG3')!==false) exit("Zaten ekli (CH_PLANDBG3).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-plandbg3-js">
/* CH_PLANDBG3 — Konto sayfasinda kalici teshis satiri (alert DEGIL) */
try{(function(){
  function render(){
    try{
      var bd=document.getElementById('konto-plan-badge');
      if(!bd) return;
      var wrap=bd.closest('div')||bd.parentNode;
      if(!wrap) return;
      var tok=''; try{ tok=localStorage.getItem('ch_token')||''; }catch(e){}
      var cu=''; try{ cu=(JSON.parse(localStorage.getItem('ch_user')||'{}').plan)||'(yok)'; }catch(e){ cu='(hata)'; }
      var pp=''; try{ pp=(window.P&&window.P.plan)||'(yok)'; }catch(e){ pp='(hata)'; }
      var tokShow = tok ? (tok.slice(0,10)+'…'+' ('+tok.length+' kr)') : '(TOKEN YOK — giris yapilmamis)';
      var el=document.getElementById('ch-plandbg3');
      if(!el){
        el=document.createElement('div'); el.id='ch-plandbg3';
        el.style.cssText='margin-top:8px;padding:8px 10px;background:rgba(255,80,80,.08);border:1px solid rgba(255,80,80,.3);border-radius:8px;font-size:10.5px;font-family:monospace;color:#ffb0b0;line-height:1.6;white-space:pre-wrap;word-break:break-all';
        wrap.appendChild(el);
      }
      el.textContent = 'DEBUG token: '+tokShow+'\nDEBUG ch_user.plan: '+cu+'\nDEBUG P.plan: '+pp;
    }catch(e){}
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',render); else render();
  try{ setInterval(render,1200); }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;
$pos=strripos($src,'</body>');
if($pos===false) exit("</body> yok\n");
$src=substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp=tempnam(sys_get_temp_dir(),'pd3').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-plandbg3-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_PLANDBG3')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_PLANDBG3 diskte (".strlen($chk)." B)\n";
echo "  Test: Konto sayfasina git -> plan rozetinin ALTINDA kirmizi 3 satir DEBUG\n";
echo "  yazisi cikacak (token/ch_user.plan/P.plan). O 3 satiri AYNEN bana gonder.\n";
