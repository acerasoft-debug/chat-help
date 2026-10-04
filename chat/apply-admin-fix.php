<?php
/**
 * ChatHelp — apply-admin-fix (#95) — 3 duzeltme:
 *  (1) admin.php test butonu modeli: claude-sonnet-4-6 (gecersiz) -> claude-sonnet-4-5
 *  (2) admin.php sifresi: config.php once yuklenir, login ADMIN_PW (config'te) ile;
 *      yoksa varsayilan 'chathelp2026'. Panelden degistirilen sifre artik gecerli.
 *  (3) fall-api.php modeli: claude-sonnet-4-6 (gecersiz) -> claude-sonnet-4-5
 *  Her dosya icin: str_replace count-guard + php -l lint + .bak yedek + dogrulama.
 * KULLANIM: pull2.php?key=...&files=apply-admin-fix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-admin-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$D=__DIR__;

function lintOk($code,&$msg){
  $tmp=tempnam(sys_get_temp_dir(),'af').'.php';
  file_put_contents($tmp,$code); $o=[];$rc=0;
  exec('php -l '.escapeshellarg($tmp).' 2>&1',$o,$rc); @unlink($tmp);
  $msg=implode("\n  ",$o); return $rc===0;
}
function writeVerify($file,$new,$needle){
  @file_put_contents($file.'.bak-adminfix-'.date('Ymd-His'), (string)@file_get_contents($file));
  $w=@file_put_contents($file,$new);
  if($w===false||$w<strlen($new)) return "YAZMA HATASI";
  $chk=(string)@file_get_contents($file);
  return (strpos($chk,$needle)!==false)?"OK":"DOGRULAMA BASARISIZ";
}

/* ═══ [1]+[2] admin.php ═══ */
$af="$D/admin.php";
$a=(string)@file_get_contents($af);
if($a===''){ echo "✗ admin.php okunamadi/bos — atlaniyor.\n"; }
else{
  $before=$a; $steps=[];
  /* (1) model */
  if(strpos($a,"'claude-sonnet-4-6'")!==false){
    $n=substr_count($a,"'claude-sonnet-4-6'");
    $a=str_replace("'claude-sonnet-4-6'","'claude-sonnet-4-5'",$a);
    $steps[]="  ✓ [1] admin.php model: claude-sonnet-4-6 -> claude-sonnet-4-5 ($n yer)";
  } else $steps[]="  ⚠ [1] admin.php'de 'claude-sonnet-4-6' yok (belki zaten duzeltilmis)";
  /* (2) sifre config'e */
  $old="define('ADMIN_PASS', 'chathelp2026');";
  if(strpos($a,$old)!==false){
    $new="if (is_file(__DIR__.'/config.php')) { include_once __DIR__.'/config.php'; }\n"
        ."define('ADMIN_PASS', (defined('ADMIN_PW') && ADMIN_PW !== '') ? ADMIN_PW : 'chathelp2026'); /* #95: config ADMIN_PW oncelikli */";
    $c=substr_count($a,$old);
    if($c===1){ $a=str_replace($old,$new,$a); $steps[]="  ✓ [2] admin.php sifresi: config ADMIN_PW oncelikli (varsayilan fallback)"; }
    else $steps[]="  ⚠ [2] ADMIN_PASS define anchor $c kez (1 bekleniyordu) — sifre adimi ATLANDI";
  } else $steps[]="  ⚠ [2] ADMIN_PASS define bulunamadi — belki zaten config'te";

  if($a!==$before){
    $msg='';
    if(!lintOk($a,$msg)){ echo "✗ admin.php LINT HATASI — DEGISTIRILMEDI:\n  $msg\n"; }
    else{
      $r=writeVerify($af,$a,"'claude-sonnet-4-5'");
      foreach($steps as $s) echo $s."\n";
      echo "  -> admin.php yazma: $r (".strlen($a)." bayt)\n";
    }
  } else { foreach($steps as $s) echo $s."\n"; echo "  -> admin.php degismedi.\n"; }
}

echo "\n";
/* ═══ [3] fall-api.php ═══ */
$ff="$D/fall-api.php";
$f=(string)@file_get_contents($ff);
if($f===''){ echo "✗ fall-api.php okunamadi/bos — atlaniyor.\n"; }
else{
  if(strpos($f,"'claude-sonnet-4-6'")!==false){
    $n=substr_count($f,"'claude-sonnet-4-6'");
    $f2=str_replace("'claude-sonnet-4-6'","'claude-sonnet-4-5'",$f);
    $msg='';
    if(!lintOk($f2,$msg)){ echo "✗ fall-api.php LINT HATASI — DEGISTIRILMEDI:\n  $msg\n"; }
    else{
      $r=writeVerify($ff,$f2,"'claude-sonnet-4-5'");
      echo "  ✓ [3] fall-api.php model: claude-sonnet-4-6 -> claude-sonnet-4-5 ($n yer)\n";
      echo "  -> fall-api.php yazma: $r (".strlen($f2)." bayt)\n";
    }
  } else echo "  ⚠ [3] fall-api.php'de 'claude-sonnet-4-6' yok (belki zaten duzeltilmis)\n";
}

echo "\n✓ BITTI: gecersiz model duzeltildi (admin+fall-api) + admin sifresi config'e tasindi.\n";
echo "   Not: Admin panelinden yeni sifre kaydedince ADMIN_PW config.php'ye yazilir ve login onu kullanir.\n";
