<?php
/**
 * ChatHelp — apply-verlauf-fix (CH_VERLAUF_FIX)
 *  BUG: Sol menude "Verlauf"a ILK tiklama bos kaliyordu (ikincisi aciyordu).
 *  Neden: gP sarmalayicisi once orijinal gP'yi calistiriyor (panel henuz
 *  yokken .on eklenemiyor), paneli SONRA olusturuyordu.
 *  Duzeltme: sira ters cevrildi — once renderVerlauf() (panel olusur),
 *  sonra orijinal gP (.on ekler, panel gorunur).
 * KULLANIM: pull-updates.php?files=apply-verlauf-fix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-verlauf-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_VERLAUF_FIX') !== false) exit("Zaten ekli (CH_VERLAUF_FIX).\n");
file_put_contents($file . '.bak-verlauffix-' . date('Ymd-His'), $src);

$old = 'var w=function(k){ var r=_g.apply(this,arguments); try{ if(k==="verlauf") renderVerlauf(); }catch(e){} return r; };';
$new = 'var w=function(k){ /*CH_VERLAUF_FIX*/ try{ if(k==="verlauf") renderVerlauf(); }catch(e){} return _g.apply(this,arguments); };';
$c = 0; $src = str_replace($old, $new, $src, $c);
if (!$c) exit("HATA: verlauf sarmalayici anchor bulunamadi — degistirilmedi.\n");

$tmp = tempnam(sys_get_temp_dir(), 'idx') . '.php';
file_put_contents($tmp, $src);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "LINT HATASI — index.php degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
file_put_contents($file, $src);
echo "✓ CH_VERLAUF_FIX uygulandi ($c nokta). Verlauf artik ilk tiklamada acilir.\n";
