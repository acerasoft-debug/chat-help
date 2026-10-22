<?php
/**
 * ChatHelp — apply-zen-fix (CH_ZEN_CARDSONLY) — Zen dugmesi ARTIK SADECE KARTLARI gizler
 *  HATA: CH_ZEN dugmesi sidebar + hero + ornek sorular + kartlar = HER SEYI
 *  gizliyordu. Kullanici sadece KARTLARIN kaldirilmasini istemisti.
 *  DUZELTME: gizleme kapsami yalniz kart izgarasi + kategori cubugu + sozlesme
 *  vitrinleri ile sinirlanir. Sidebar/hero/ornek sorular/bayrak/sohbet KALIR.
 * KULLANIM: pull2.php?key=...&files=apply-zen-fix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-zen-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_ZEN_CARDSONLY')!==false) exit("Zaten ekli (CH_ZEN_CARDSONLY).\n");
if (strpos($src,'ch-zen-css')===false) exit("HATA: CH_ZEN katmani yok.\n");

$fail=[];

/* [1] gizleme kapsamini SADECE kartlar/vitrin ile sinirla (sidebar+hero+ornekler kalir) */
$o1 = "body.ch-zen #sb{display:none !important}\n"
    . "body.ch-zen #wlc .wlc-hero,body.ch-zen #wlc .wcat-grid,body.ch-zen #wlc .ch-cat-tg,\n"
    . "body.ch-zen #wlc .ch-vtr-sec,body.ch-zen #wlc .ch-ex-sec,body.ch-zen #wlc #ch-vst-home,\n"
    . "body.ch-zen #wlc .co-grid,body.ch-zen #wlc [class*=\"staat\"]{display:none !important}";
$n1 = "body.ch-zen #wlc .wcat-grid,body.ch-zen #wlc .ch-cat-tg,body.ch-zen #wlc .ch-vtr-sec,body.ch-zen #wlc #ch-vst-home{display:none !important} /*CH_ZEN_CARDSONLY — yalniz kartlar/vitrin; sidebar+hero+ornekler+chat kalir*/";
$c=0; $src=str_replace($o1,$n1,$src,$c);
echo ($c===1?"  ✓ [1] gizleme kapsami sadece kartlar/vitrin ile sinirlandi\n":"  ✗ [1] anchor ($c)\n"); if($c!==1)$fail[]=1;

/* [2] dugme baligini guncelle (2 gecis) */
$c=0; $src=str_replace('"Sadece sohbet"','"Kartlari gizle/goster"',$src,$c);
echo ($c>=1?"  ✓ [2] dugme baligi 'Kartlari gizle/goster' ($c gecis)\n":"  ⚠ [2] balik metni bulunamadi (onemsiz)\n");

if ($fail) { echo "\nHATA: eslesmeyen adimlar: ".implode(', ',$fail)." — index DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'zf').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-zenfix-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_ZEN_CARDSONLY uygulandi. 🧘 dugmesi artik yalniz kartlari/vitrini gizler;\n";
echo "   sidebar, ornek sorular, sohbet ve diger her sey yerinde kalir.\n";
echo "   (Once 'her sey gitti' gorenler: dugmeye bir kez basip kapatabilir ya da oyle birakabilir.)\n";
