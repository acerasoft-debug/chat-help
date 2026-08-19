<?php
/**
 * ChatHelp — apply-flicker-kill (CH_FLICKER_KILL) — titremenin KESIN kok nedeni:
 *  bir DOM savasi. apply-attach-plus her saniye .ia-btns'e ".cha2-plus" (+ butonu)
 *  EKLIYOR; apply-plusicon-remove'un MutationObserver'i o eklemeyi aninda SILIYOR
 *  -> attach-plus tekrar ekliyor -> sonsuz ekle/sil = titreme (flickdbg: 6x ADD +
 *  6x DEL .cha2-plus @.ia-btns).
 *  COZUM: plusicon-remove'un JS blogunu (kill dongusu + MutationObserver) KALDIR,
 *  CSS blogunu BIRAK. Boylece eski "+" DOM'da kalir ama CSS ile GIZLI; attach-plus
 *  "zaten var" deyip tekrar EKLEMEZ; hicbir JS SILMEZ -> savas biter, tam sabit.
 *  Gorunumde hicbir sey degismez (+ zaten CSS ile gizli), sadece titreme durur.
 * KULLANIM: pull2.php?key=...&files=apply-flicker-kill.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-flicker-kill BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");

/* [1] plusicon-remove JS savas blogunu kaldir (CSS kalir) */
$n=0;
$src=preg_replace('#<script id="ch-plusicon-remove-js">.*?</script>#s','<!-- ch-plusicon-remove-js: savas kaldirildi (CH_FLICKER_KILL) -->',$src,-1,$n);
echo "  ".($n>0?"✓":"✗")." plusicon-remove JS savas blogu: $n adet kaldirildi\n";
if($n===0){ echo "  (Belki zaten kaldirilmis; devam.)\n"; }

/* CSS hala duruyor mu dogrula (eski + gizli kalmali) */
$cssvar = strpos($src,'ch-plusicon-remove-css')!==false;
echo "  ".($cssvar?"✓":"!")." plusicon-remove CSS ".($cssvar?"duruyor (eski + gizli kalir)":"YOK — eski + gorunebilir")."\n";

/* [2] guvence: attach-plus tick'i main '+' eklemesin (savas kaynagini da kes).
   decorateMain cagrisini tick icinde etkisizlestir (idempotent, count-guard). */
$a2='function tick(){ wrapReset(); decorateDocForm(); decorateFall(); decorateIntake(); decorateMain(); fallPremium(); syncDocPhotos(); }';
$n2 = ($a2!=='' && strpos($src,$a2)!==false) ? substr_count($src,$a2) : 0;
if($n2===1){
  $b2='function tick(){ wrapReset(); decorateDocForm(); decorateFall(); decorateIntake(); /*CH_FLICKER_KILL: decorateMain kapatildi*/ fallPremium(); syncDocPhotos(); }';
  $src=str_replace($a2,$b2,$src);
  echo "  ✓ attach-plus tick: decorateMain (ana '+') artik cagrilmiyor -> ekleme kaynagi da kesildi\n";
} else {
  echo "  = attach-plus tick anchor'i ($n2) — atlandi (JS savas kaldirmasi zaten yeterli)\n";
}

$tmp=tempnam(sys_get_temp_dir(),'fk').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
if($n===0 && $n2!==1){ echo "\n! Degisiklik yapilmadi (belki zaten uygulanmis).\n"; }
@file_put_contents($file.'.bak-flickerkill-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
echo "\n  ✓ index.php guncellendi (".strlen((string)@file_get_contents($file))." B)\n";
echo "✓ .cha2-plus ekle/sil savasi bitti -> titreme durur. Hard-refresh (app'i kapat-ac).\n";
