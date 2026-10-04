<?php
/**
 * ChatHelp — apply-histpdf (CH_HISTPDF) — geriye-donuste PDF + "bazen hata veriyor" temizligi.
 *  KOK NEDEN (apply-themen-fix deployed):
 *   1) openTopic gecmisten belgeyi acarken sadece kisaltilmis d.preview aliyor
 *      -> th-pdf butonu eksik/bos metinle makePDF cagiriyor.
 *   2) th-pdf onclick basarisizsa 'alert(tx)' ham metni pop-up ediyor = cirkin "hata".
 *  COZUM (2 sayac-korumali str_replace, sadece Themen goruntuleyici; canli showResult'a DOKUNMAZ):
 *   [A] openTopic: sunucudan TAM metin alanlarini dene (doc/body/content/text/full), yoksa preview.
 *   [B] th-pdf: alert(tx) KALDIR; metin kisa/bos ise nazik toast, patlama yok.
 * KULLANIM: pull2.php?key=...&files=apply-histpdf.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-histpdf BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_HISTPDF')!==false) exit("Zaten ekli (CH_HISTPDF).\n");

$fail=[];

/* [A] openTopic: tam metin alanlarini dene */
$oA = "var tx=String(d.preview||'').replace(";
$nA = "/*CH_HISTPDF*/var tx=String(d.doc||d.body||d.content||d.text||d.full||d.fulltext||d.preview||'').replace(";
$cA=substr_count($src,$oA);
if($cA===1){ $src=str_replace($oA,$nA,$src); echo "  ✓ [A] openTopic tam-metin alanlari (doc/body/content/text/full) eklendi\n"; }
else { echo "  ✗ [A] anchor ($cA, 1 beklenir)\n"; $fail[]='A'; }

/* [B] th-pdf: alert(tx) kaldir + bos/kisa guard */
$oB = "try{ if(typeof window.makePDF==='function') window.makePDF(tx,CUR.title,CCX()); else alert(tx); }catch(e){ alert(tx); }";
$nB = "try{ if(!tx||String(tx).replace(/\\s/g,'').length<60){ if(typeof toast==='function') toast(T('nodoc')); return; } if(typeof window.makePDF==='function'){ window.makePDF(tx,CUR.title,CCX()); } else if(typeof toast==='function'){ toast('PDF n/v'); } }catch(e){}/*CH_HISTPDF_B*/";
$cB=substr_count($src,$oB);
if($cB===1){ $src=str_replace($oB,$nB,$src); echo "  ✓ [B] th-pdf: alert(tx) kaldirildi, bos/kisa-metin guard eklendi\n"; }
else { echo "  ✗ [B] anchor ($cB, 1 beklenir)\n"; $fail[]='B'; }

if($fail){ echo "\nHATA: eslesmeyen adim(lar): ".implode(', ',$fail)." — HICBIR sey degistirilmedi.\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'hp').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-histpdf-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_HISTPDF')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_HISTPDF diskte (".strlen($chk)." B)\n";
echo "  Test: Meine Themen -> eski belge ac -> 'Als PDF speichern' -> tam belge PDF, alert-hata YOK.\n";
