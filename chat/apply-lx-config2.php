<?php
/**
 * ChatHelp — apply-lx-config2 — LetterXpress kimligi GOMULU kurulum.
 *  API anahtari kodda gomulu; kullanici adi varsayilan acerasoft@gmail.com
 *  (farkliysa &lxuser=DOGRUSU ekleyin). mode=test ile baslar.
 *  Kurulumdan sonra kimlik CANLI dogrulanir (balance cagrisi) ve sonuc basilir.
 * KULLANIM: pull2.php?key=...&files=apply-lx-config2.php
 *           (istege bagli: &lxuser=... &lxmode=live)
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-lx-config2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$k = '7895a0a0aff0a8dabf14ac1a0920722bdfd4c752d2ccfce851bc7bbe6a865639';
$u = trim((string)($_GET['lxuser'] ?? ''));
if ($u==='') $u = 'LXPApi73638';
$m = trim((string)($_GET['lxmode'] ?? 'test'));
if ($m!=='live') $m='test';

echo "  kullanici : $u\n  anahtar   : ".substr($k,0,6)."*** (gomulu)\n  mod       : $m\n\n";

$f=__DIR__.'/config.php';
$s=(string)@file_get_contents($f);
if($s===''){ exit("HATA: config.php okunamadi\n"); }
$s2=preg_replace("/\n?define\('LETTERXPRESS_(USER|APIKEY|MODE)'.*?;[ \t]*/","",$s);
$ins="\ndefine('LETTERXPRESS_USER', ".var_export($u,true).");\n"
    ."define('LETTERXPRESS_APIKEY', ".var_export($k,true).");\n"
    ."define('LETTERXPRESS_MODE', ".var_export($m,true).");\n";
$pos=strpos($s2,'<?php'); $pos=($pos===false)?0:$pos+5;
$new=substr($s2,0,$pos).$ins.substr($s2,$pos);
$tmp=tempnam(sys_get_temp_dir(),'lx2').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — config DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($f.'.bak-lx2-'.date('Ymd-His'),$s);
$w=@file_put_contents($f,$new);
$chk=(string)@file_get_contents($f);
if($w===false || strpos($chk,'LETTERXPRESS_APIKEY')===false){ exit("  ✗ config yazilamadi\n"); }
echo "  ✓ LetterXpress kimligi config.php'ye yazildi\n\n";

/* ── kimligi CANLI dogrula (balance) ── */
echo "──── Kimlik dogrulama testi ────\n";
$tried=false;
foreach(['https://api.letterxpress.de/v1/balance','https://api.letterxpress.de/v1/getBalance'] as $url){
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25,
    CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>json_encode(['auth'=>['username'=>$u,'apikey'=>$k,'mode'=>$m]]),
    CURLOPT_HTTPHEADER=>['Content-Type: application/json']]);
  $res=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  if($res===false) continue;
  $tried=true;
  echo "  ".basename($url).": HTTP $code — ".preg_replace('/\s+/',' ',substr((string)$res,0,260))."\n";
  if($code>=200 && $code<300){ echo "\n  ✓ KIMLIK GECERLI gorunuyor.\n"; break; }
  if($code===401||$code===403){ echo "\n  ✗ Kimlik REDDEDILDI — kullanici adi buyuk olasilikla farkli.\n     URL'ye &lxuser=DOGRU_KULLANICI ekleyip tekrar calistirin.\n"; break; }
}
if(!$tried) echo "  (API'ye ulasilamadi — kimlik daha sonra status ile kontrol edilir)\n";
echo "\nKONTROL: https://chat-help.com/chat/postversand.php?action=status\n";
