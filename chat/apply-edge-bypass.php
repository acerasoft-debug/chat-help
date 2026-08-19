<?php
/**
 * ChatHelp — apply-edge-bypass (CH_EDGE_BYPASS) — 31-GUN EDGE CACHE'INE KESIN COZUM
 *
 *  KANIT (dump-headers): cf-cache-status: HIT · age: 549 ·
 *  cache-control: max-age=2678400 (31 GUN!) · vary: Cookie
 *  GoDaddy/Cloudflare gateway HTML'i 31 gun onbellekliyor; deploy sirasinda
 *  alinan bozuk kopya edge'de VE kullanicilarin tarayicisinda gunlerce
 *  kalabiliyor ("site bos", "eski site cikiyor" sikayetlerinin koku).
 *
 *  COZUM (iki katman):
 *   [1] GERCEK HTTP basliklari: index.php artik ciktidan ONCE
 *       Cache-Control: no-store gonderiyor (meta etiketleri CDN'i etkilemez,
 *       HTTP basligi etkiler).
 *   [2] VARY: COOKIE kaldiraci: yanit "vary: Cookie" tasidigi icin cache
 *       anahtari cerezlere baglidir. Her cihaza kalici, rastgele bir
 *       'ch_edge' cerezi verilir -> her cihazin cache anahtari BENZERSIZ
 *       olur -> baska zamanda/baska cihaz icin alinmis bayat/bozuk kopya
 *       hicbir kullaniciya BIR DAHA isabet edemez. Guncellemeler icin
 *       mevcut ver.php cache-buster (?_ch=... ile yeniden yukleme) zaten
 *       devrede.
 * KULLANIM: pull-updates.php?files=apply-edge-bypass.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-edge-bypass BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_EDGE_BYPASS')!==false) exit("Zaten ekli (CH_EDGE_BYPASS).\n");

$prepend = "<?php /*CH_EDGE_BYPASS — edge/tarayici HTML cache'ini kapat + cihaz-basi benzersiz cache anahtari*/\n"
    . "header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');\n"
    . "header('Pragma: no-cache');\n"
    . "header('Expires: 0');\n"
    . "if (empty(\$_COOKIE['ch_edge'])) {\n"
    . "    @setcookie('ch_edge', substr(bin2hex(random_bytes(6)),0,12), ['expires'=>time()+31536000,'path'=>'/','secure'=>true,'samesite'=>'Lax']);\n"
    . "}\n"
    . "?>";

$src = $prepend . $src;
echo "  ✓ [1] HTTP no-store basliklari en basa eklendi (cikti oncesi)\n";
echo "  ✓ [2] cihaz-basi 'ch_edge' cerezi (vary:Cookie -> benzersiz cache anahtari)\n";

$tmp = tempnam(sys_get_temp_dir(),'eb').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-edgebypass-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_EDGE_BYPASS uygulandi. Bayat/bozuk edge kopyalari artik kullanicilara\n";
echo "   isabet edemez; 'eski site cikiyor' sorunu kokten kapandi.\n";
echo "\nNOT: Halihazirda etkilenen cihazlar icin bir kez ?v=yeni1 ile acmak yeterli —\n";
echo "     cerez yerlesir ve o cihaz bir daha bayat kopya gormez.\n";
