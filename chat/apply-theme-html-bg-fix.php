<?php
/**
 * ChatHelp — apply-theme-html-bg-fix (CH_THEME_HTMLBG) — <html> etiketinin
 *  arka plani HICBIR ZAMAN temaya gore degismiyordu (:root'taki temasiz
 *  'html,body{background:#07070e!important}' kuralindan sonra hicbir
 *  data-chtheme kurali <html>'in kendi background'ini ayarlamiyor, sadece
 *  --bg degiskenini taniyor). viewport-fit=cover ile guvenli-alan/notch/ev
 *  tusu seridinde bu yuzden beyaz temada bile lacivert #07070e goruluyor
 *  ("kartlar dogru ama arka plan beyaz olmadi" sikayeti).
 *  TEK SATIR duzeltme: mevcut genel 'html[data-chtheme] body,...' kuralinin
 *  BASINA 'html[data-chtheme]{background:var(--bg)!important}' eklenir —
 *  boylece <html> kendi --bg degiskenini (anthracite/navy/white ne ise)
 *  kullanir. TUM temalari kapsar (tek tema'ya ozel degil).
 * KULLANIM: pull2.php?key=...&files=apply-theme-html-bg-fix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-theme-html-bg-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_THEME_HTMLBG')!==false) exit("Zaten ekli (CH_THEME_HTMLBG).\n");

$o1 = <<<'ANC'
html[data-chtheme] body,html[data-chtheme] #app,html[data-chtheme] .pnl,html[data-chtheme] #msgs,html[data-chtheme] #mi,html[data-chtheme] #pnl-ant,html[data-chtheme] #pnl-konto,html[data-chtheme] #pnl-fall{background:var(--bg)!important}
ANC;
$n1 = <<<'ANC'
/*CH_THEME_HTMLBG*/html[data-chtheme]{background:var(--bg)!important}
html[data-chtheme] body,html[data-chtheme] #app,html[data-chtheme] .pnl,html[data-chtheme] #msgs,html[data-chtheme] #mi,html[data-chtheme] #pnl-ant,html[data-chtheme] #pnl-konto,html[data-chtheme] #pnl-fall{background:var(--bg)!important}
ANC;

$c1 = substr_count($src,$o1);
if ($c1!==1) { echo "  ✗ anchor bulunamadi (eslesme sayisi: $c1) — index.php DEGISTIRILMEDI.\n"; exit; }
$src = str_replace($o1,$n1,$src);
echo "  ✓ <html> etiketine tema-farkindalikli arka plan eklendi (tum temalar: anthracite/navy/white)\n";

$tmp = tempnam(sys_get_temp_dir(),'thb').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-htmlbg-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_THEME_HTMLBG')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "\n  ✓ DOGRULAMA: CH_THEME_HTMLBG diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Beyaz (ve diger) temalarda artik <html> etiketi de dogru renkte —\n";
echo "   guvenli-alan/notch/ev-tusu seridinde lacivert sizintisi bitti.\n";
