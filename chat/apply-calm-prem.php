<?php
/**
 * ChatHelp — apply-calm-prem (CH_CALM_PREM) — "daha sakin ve Premium" gecisi.
 *  Site surekli(infinite) DEKORATIF animasyonlarla "oynak" hissi veriyordu.
 *  Bu yama SADECE surekli dekoratif hareketi durdurur; ISLEVSEL geri bildirim
 *  (AI 'yaziyor' noktalari, caret blink, kayit sirasi mic halkasi, hata nabzi)
 *  ve premium hover-kalkislari (translateY) AYNEN korunur.
 *  Durdurulanlar (dump-anim2 ile dogrulanan selector'ler):
 *   .wlc-h1::after (chHeroLaser/2 lazer taramasi), .wlc-h1 em (shimmer),
 *   .wlc-scan::before (scanshine), .wlc-badge-dot (bdot), .tb-dot (3'lu nabiz),
 *   .vst/.cvs/.trv-card:hover (chBreathe nefes).
 *  Ayrica prefers-reduced-motion icin tam-sakin blok eklenir.
 *  Stil bloku </body> ONCESINE eklenir (kaynak sirasi -> !important ile ustun).
 * KULLANIM: pull2.php?key=...&files=apply-calm-prem.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-calm-prem BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_CALM_PREM')!==false) exit("Zaten ekli (CH_CALM_PREM).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-calm-prem">
/* CH_CALM_PREM — sakin premium: surekli dekoratif hareket kapali, islev korunur */
.wlc-h1::after{ animation:none !important; opacity:0 !important; }
.wlc-h1 em{ animation:none !important; background-position:0 0 !important; }
.wlc-scan::before{ animation:none !important; opacity:0 !important; }
.wlc-badge-dot{ animation:none !important; }
.tb-dot{ animation:none !important; }
.vst-card:hover,.cvs-card:hover,.trv-card:hover{ animation:none !important; }
/* gecisler biraz daha yumusak/sakin (yalniz donusum/renk, reflow yok) */
.pc,.wcat,.ccard,.wc,.acard,.vst-card,.cvs-card,.trv-card,.ch-vtr-card,.cvs-card,.btn-g{ transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease, background-color .18s ease !important; }
@media (prefers-reduced-motion: reduce){
  *,*::before,*::after{ animation-duration:.001ms !important; animation-iteration-count:1 !important; transition-duration:.001ms !important; scroll-behavior:auto !important; }
}
</style>
HTMLBLOCK;

$pos=strripos($src,'</body>');
if($pos===false) exit("</body> bulunamadi\n");
$new=substr($src,0,$pos).$block."\n".substr($src,$pos);

/* lint */
$tmp=tempnam(sys_get_temp_dir(),'cp').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }

@file_put_contents($file.'.bak-calm-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_CALM_PREM')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_CALM_PREM eklendi (".strlen($chk)." B)\n\n";
echo "✓ Hero lazer taramasi + shimmer + scanshine + rozet/canli-nokta nabzi + hover 'breathe' KAPALI\n";
echo "✓ AI 'yaziyor' noktalari, caret, kayit mic halkasi, hata nabzi KORUNDU\n";
echo "✓ Hover kalkislari premium kaldi; gecisler .18s'e yumusatildi\n";
echo "✓ prefers-reduced-motion: tam sakin\n";
echo "\nSayfayi yenile (SW cache reset zaten var) — his: sakin, premium, oynamasiz.\n";
