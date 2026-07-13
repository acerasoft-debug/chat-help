<?php
/**
 * ChatHelp — apply-login-eye (CH_LOGIN_EYE) — Anmeldung/login.php sifre alanlarina
 *  goz (goster/gizle) butonu. login.php'de 2 password input var (#inp-pass,
 *  #inp-pass2), goz butonu yok. Bu yama her password alanina saga bir goz
 *  dugmesi ekler; tiklaninca type password<->text degisir, kullanici girdigini
 *  gorur. Tamamen additive (login.php'nin </body> oncesine CSS+JS).
 * KULLANIM: pull2.php?key=...&files=apply-login-eye.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-login-eye BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/login.php';
$src = @file_get_contents($file);
if ($src===false) exit("login.php okunamadi\n");
if (strpos($src,'CH_LOGIN_EYE')!==false) exit("Zaten ekli (CH_LOGIN_EYE).\n");
if (strpos($src,'id="inp-pass"')===false) exit("HATA: login.php beklenen sifre alanini icermiyor.\n");

$inject = <<<'HTML'
<style id="ch-login-eye-css">
/* CH_LOGIN_EYE — sifre goster/gizle */
.field{position:relative}
.pw-eye{position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;color:#60607a;font-size:17px;cursor:pointer;padding:6px;line-height:1;font-family:inherit}
.pw-eye:hover{color:#d4a84a}
.field input.pw-has{padding-right:40px}
</style>
<script id="ch-login-eye-js">
(function(){
  function addEye(inp){
    if(!inp || inp._chEye) return; inp._chEye=1;
    var host=inp.parentNode; if(!host) return;
    var b=document.createElement('button');
    b.type='button'; b.className='pw-eye'; b.setAttribute('aria-label','Passwort anzeigen'); b.tabIndex=-1;
    b.innerHTML='<i class="ti ti-eye"></i>';
    b.addEventListener('click',function(){
      var show=(inp.type==='password');
      inp.type=show?'text':'password';
      b.innerHTML='<i class="ti '+(show?'ti-eye-off':'ti-eye')+'"></i>';
    });
    inp.classList.add('pw-has');
    host.appendChild(b);
  }
  function init(){
    addEye(document.getElementById('inp-pass'));
    addEye(document.getElementById('inp-pass2'));
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init); else init();
  /* pass2 alani register sekmesinde gizli -> gorununce de ekle */
  setInterval(init, 800);
})();
</script>
HTML;

$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: login.php'de </body> yok.\n");
$src = substr($src,0,$pos).$inject."\n".substr($src,$pos);
echo "  ✓ sifre alanlarina goz (goster/gizle) butonu eklendi\n";

$tmp = tempnam(sys_get_temp_dir(),'le').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — login.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
$bk=@file_put_contents($file.'.bak-loginEye-'.date('Ymd-His'), @file_get_contents($file));
if ($bk===false) echo "  ⚠ yedek yazilamadi — devam\n";
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_LOGIN_EYE')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_LOGIN_EYE diskte (".strlen($chk)." bayt)\n";
echo "\n✓ CH_LOGIN_EYE uygulandi. Anmeldung/kayit sifre alanlarinda goz ile\n";
echo "   girdiginizi gorup gizleyebilirsiniz.\n";
