<?php
/**
 * ChatHelp — apply-faxprime-fix (CH_FAXPRIME_FIX) — CH_FAX_PRIMARY'nin '__faxprimed'
 *  bayragi KALICI '#ai-box' konteynerine yaziliyordu. Gonderim modali her acilista
 *  (her belge/Senden) icerigini (radio dugmeleri dahil) YENIDEN OLUSTURUYOR, ama
 *  konteyner DOM node'u ayni kaliyor -> ilk primeden SONRA bayrak hep 'true' kalip
 *  Fax bir daha ASLA en-uste tasinmiyor/varsayilan secilmiyor (sadece ilk acilista
 *  calisiyordu). COZUM: bayragi kalici konteyner yerine, HER YENIDEN-OLUSTURMADA
 *  taze bir DOM node olan Fax radio'sunun KENDISINE ('faxR.__faxprimed') yazariz —
 *  boylece her modal acilisinda doğru şekilde yeniden calisir, ayni radio-instance
 *  icin tekrar tekrar (700ms) calismaz (kullanicinin sonradan Post secmesini hala
 *  zorlamaz).
 *  1 sayac-korumali str_replace. Eslesmezse HICBIR sey degistirilmez.
 * KULLANIM: pull2.php?key=...&files=apply-faxprime-fix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-faxprime-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_FAXPRIME_FIX')!==false) exit("Zaten ekli (CH_FAXPRIME_FIX).\n");
if(strpos($src,'CH_FAX_PRIMARY')===false) exit("CH_FAX_PRIMARY canlida yok — once o deploy edilmeli.\n");

$old = "      var scope=radios[0].closest('#ai-box')||radios[0].closest('form')||document.body;\n"
      ."      if(scope.__faxprimed) return;\n"
      ."      var faxR=null;\n"
      ."      radios.forEach(function(r){ var lbl=r.closest('label')||r.parentNode; var t=(lbl&&lbl.textContent)||''; if(/📠|\\bfax\\b/i.test(t)) faxR=r; });\n"
      ."      if(!faxR) return;\n"
      ."      scope.__faxprimed=true;\n";
$new = "      var faxR=null;\n"
      ."      radios.forEach(function(r){ var lbl=r.closest('label')||r.parentNode; var t=(lbl&&lbl.textContent)||''; if(/📠|\\bfax\\b/i.test(t)) faxR=r; });\n"
      ."      if(!faxR || faxR.__faxprimed) return;\n"
      ."      faxR.__faxprimed=true;/*CH_FAXPRIME_FIX*/\n";

$c=substr_count($src,$old);
if($c!==1){ echo "  ✗ anchor ($c, 1 beklenir) — HICBIR sey degistirilmedi.\n"; exit; }
$src=str_replace($old,$new,$src);
echo "  ✓ prime() guard: kalici konteyner yerine taze Fax-radio node'una tasindi\n";
echo "  -> artik HER gonderim modali acilisinda Fax en-uste tasinir + varsayilan secilir\n";

$tmp=tempnam(sys_get_temp_dir(),'fpf').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-faxprimefix-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_FAXPRIME_FIX')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_FAXPRIME_FIX diskte (".strlen($chk)." B)\n";
echo "  Test: 2 FARKLI belge icin 'Senden' modalini ac -> HER ikisinde de Fax\n";
echo "  en-ustte + varsayilan secili olmali (sadece ilkinde degil).\n";
