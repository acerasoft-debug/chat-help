<?php
/**
 * ChatHelp — apply-home-not-konto (CH_HOME_NOT_KONTO) — acilista ANA SAYFA
 *  KANIT (dump-adminkonto): CH_ONBOARD katmani ilk giriste hosgeldin/gizlilik
 *  mesajindan sonra OTOMATIK gP("konto") cagirip kullaniciyi Konto'ya atiyor.
 *  Kullanici "login-when-needed" modeline gecti — acilista ana sayfa kalmali.
 *  Bu yama iki otomatik yonlendirmeyi no-op yapar (hosgeldin/gizlilik mesaji
 *  KALIR, sadece zorla Konto'ya atma kalkar). Diger gP('konto') cagrilari
 *  (buton/menu tiklamalari) DOKUNULMAZ. Her adim count-guard'li.
 * KULLANIM: pull2.php?key=...&files=apply-home-not-konto.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-home-not-konto BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_HOME_NOT_KONTO')!==false) exit("Zaten ekli (CH_HOME_NOT_KONTO).\n");

$done=0;

/* [1] onboarding hosgeldin sonrasi otomatik Konto (setTimeout ... 600) */
$o1 = 'setTimeout(function(){ try{ gP("konto"); }catch(e){} }, 600);';
$n1 = '/*CH_HOME_NOT_KONTO — acilista Konto\'ya atma kaldirildi; ana sayfa kalir*/';
$c=substr_count($src,$o1);
if($c===1){ $src=str_replace($o1,$n1,$src,$c); echo "  ✓ [1] hosgeldin sonrasi otomatik Konto yonlendirmesi kaldirildi\n"; $done++; }
else echo "  ⚠ [1] anchor ($c) — atlandi\n";

/* [2] onboarding profil-zorunlu otomatik Konto */
$o2 = 'try{ if(typeof gP==="function") gP("konto"); }catch(e){}';
$n2 = '/*CH_HOME_NOT_KONTO — profil-zorunlu otomatik Konto kaldirildi*/';
$c=substr_count($src,$o2);
if($c===1){ $src=str_replace($o2,$n2,$src,$c); echo "  ✓ [2] profil-zorunlu otomatik Konto yonlendirmesi kaldirildi\n"; $done++; }
else echo "  ⚠ [2] anchor ($c) — atlandi (guvenli)\n";

if($done===0) exit("\nHATA: hicbir otomatik yonlendirme eslesmedi — index DEGISTIRILMEDI.\n");

/* iz birak (HTML yorumu, kod disi) */
$pos=strrpos($src,'</body>');
if($pos!==false) $src=substr($src,0,$pos)."<!-- CH_HOME_NOT_KONTO -->\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'hk').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
$bk=@file_put_contents($file.'.bak-homenotkonto-'.date('Ymd-His'), @file_get_contents($file));
if ($bk===false) echo "  ⚠ yedek yazilamadi — devam\n";
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_HOME_NOT_KONTO')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_HOME_NOT_KONTO diskte ($done otomatik yonlendirme kaldirildi, ".strlen($chk)." bayt)\n";
echo "\n✓ CH_HOME_NOT_KONTO uygulandi. Site acilinca artik ANA SAYFA gelir;\n";
echo "   Konto yalniz kullanici tikladiginda acilir.\n";
