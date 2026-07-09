<?php
/**
 * ChatHelp — apply-aichat-pdf-polish (CH_AICHAT_PDF_POLISH)
 *  Sohbetten uretilen PDF'te iki sorun:
 *   [1] TASMA: .a4 kutusu icin overflow-wrap/word-break YOKTU — AI metninde
 *       bosluksuz uzun bir dizi (uzun rakam/referans/alt-cizgi bloklari vb.)
 *       gecerse sayfa genisligini (794px) asip disariya tasabiliyordu.
 *   [2] KARAKTER/YAZI TIPI: font-family sadece 'Inter','Segoe UI',Arial idi;
 *       Arapca icin dogru YON (RTL) HIC yoktu -> Arapca metin soldan saga ve
 *       sola hizali basiliyordu (bozuk gorunum). Turkce/Ispanyolca/Fransizca/
 *       Lehce/Portekizce/Isvecce gibi Latin-genisletilmis diller zaten
 *       Segoe UI/Arial ile calisir, ama font zincirine Tahoma/Noto Sans da
 *       eklenerek Kiril + Arapca yedeklemesi guclendirilir.
 *  COZUM: printDoc() icindeki HTML/CSS sablonu guncellenir — tasma imkansiz
 *  hale gelir (overflow-wrap:anywhere), Arapca icin dir="rtl" + saga hizali
 *  govde otomatik uygulanir, font zinciri genisler, tipografi biraz daha
 *  kurumsal hale getirilir (satir araligi, kenar boslugu).
 * KULLANIM: pull-updates.php?files=apply-aichat-pdf-polish.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-aichat-pdf-polish BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_AICHAT_PDF_POLISH')!==false) exit("Zaten ekli (CH_AICHAT_PDF_POLISH).\n");
if (strpos($src,'CH_AICHAT_DOCPDF')===false) exit("HATA: once apply-aichat-docpdf.php gerekli (CH_AICHAT_DOCPDF yok).\n");

$old = <<<'OLD'
  function printDoc(docText){
    var real=restore(docText);
    var html='<!doctype html><html><head><meta charset="utf-8"><title>ChatHelp</title>'
      +'<style>@page{size:A4;margin:0}html,body{margin:0;padding:0}'
      +'.a4{width:794px;min-height:1123px;box-sizing:border-box;padding:80px 84px;background:#fff;color:#1e1e26;'
      +'font-family:\'Inter\',\'Segoe UI\',Arial,sans-serif;font-size:12.5px;line-height:1.75;white-space:pre-wrap}'
      +'</style></head><body><div class="a4">'+esc(real)+'</div></body></html>';
OLD;

$new = <<<'NEW'
  function printDoc(docText){ /*CH_AICHAT_PDF_POLISH*/
    var real=restore(docText);
    var isRtl=(uil()==="ar");
    var html='<!doctype html><html'+(isRtl?' dir="rtl" lang="ar"':'')+'><head><meta charset="utf-8"><title>ChatHelp</title>'
      +'<style>@page{size:A4;margin:0}html,body{margin:0;padding:0}*{box-sizing:border-box}'
      +'.a4{width:794px;min-height:1123px;padding:78px 76px 90px;background:#fff;color:#1e1e26;'
      +'font-family:\'Inter\',\'Segoe UI\',\'Noto Sans\',\'Noto Naskh Arabic\',\'Noto Sans Arabic\',Tahoma,Arial,sans-serif;'
      +'font-size:12.5px;line-height:1.85;white-space:pre-wrap;overflow-wrap:anywhere;word-break:break-word;'
      +'hyphens:auto;'+(isRtl?'text-align:right;direction:rtl':'text-align:left')+'}'
      +'</style></head><body><div class="a4">'+esc(real)+'</div></body></html>';
NEW;

$c=0; $src=str_replace($old,$new,$src,$c);
if ($c!==1) { echo "HATA: printDoc anchor bulunamadi (x$c) — index.php DEGISTIRILMEDI.\n"; exit; }
echo "  ✓ [1] tasma imkansiz (overflow-wrap:anywhere + word-break)\n";
echo "  ✓ [2] genis font zinciri + Arapca icin otomatik RTL yon\n";

$tmp = tempnam(sys_get_temp_dir(),'pp').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-pdfpolish-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_AICHAT_PDF_POLISH uygulandi. Sohbet PDF'i artik tasmiyor, tum diller\n";
echo "   (Turkce/Ispanyolca/Fransizca/Lehce/Rusca/Arapca vb.) dogru gorunuyor.\n";
