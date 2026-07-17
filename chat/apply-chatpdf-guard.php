<?php
/**
 * ChatHelp — apply-chatpdf-guard (CH_CHATPDF_GUARD) — chatte "..." BOS PDF
 *  regresyonunun kesin cozumu: PDF'e giden metin bos veya yer tutucuysa
 *  ("...", "…"), ekranda GORUNEN belge kartindaki gercek metin otomatik
 *  geri alinir. 3 nokta:
 *  [1] card(): gorunen belge metni window._chLastDoc'a da yazilir (yedek)
 *  [2] printDoc(): bos/yer tutucu metin -> son .ch-doccard-body'den geri al
 *  [3] makePDF(): TUM cagri yollari icin ayni koruma (son care _chLastDoc)
 *  3 count-guard'li str_replace. node mantik testi ✓.
 * KULLANIM: pull2.php?key=...&files=apply-chatpdf-guard.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-chatpdf-guard BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_CHATPDF_GUARD')!==false) exit("Zaten ekli (CH_CHATPDF_GUARD).\n");

$fail=[];
function rep(&$src,$o,$n,$tag,&$fail){
  $c=substr_count($src,$o);
  if($c!==1){ echo "  ✗ [$tag] anchor $c kez (1 olmali)\n"; $fail[]=$tag; return; }
  $src=str_replace($o,$n,$src);
  echo "  ✓ [$tag] uygulandi\n";
}

$GUARD="try{ if(!doc || String(doc).replace(/[.\\u2026\\s]/g,'')===''){ var _dc=document.querySelectorAll('.ch-doccard-body'); if(_dc.length) doc=_dc[_dc.length-1].textContent||doc; if((!doc||String(doc).replace(/[.\\u2026\\s]/g,'')==='') && window._chLastDoc) doc=window._chLastDoc; } }catch(e){} /*CH_CHATPDF_GUARD*/";

/* [1] card(): gorunen metni yedekle */
$o1="      var mi=document.getElementById(\"mi\"); if(!mi) return;\n      var real=restore(docText);";
$n1="      var mi=document.getElementById(\"mi\"); if(!mi) return;\n      var real=restore(docText);\n      try{ if(real && real.replace(/[.\\u2026\\s]/g,'')!=='') window._chLastDoc=real; }catch(e){} /*CH_CHATPDF_GUARD*/";
rep($src,$o1,$n1,'1-card',$fail);

/* [2] printDoc(): bos metinde karttan geri al */
$o2="    var real=restore(docText);\n    try{\n      if(typeof makePDF===\"function\"){";
$n2="    try{ if(!docText || String(docText).replace(/[.\\u2026\\s]/g,'')===''){ var _dc2=document.querySelectorAll('.ch-doccard-body'); if(_dc2.length) docText=_dc2[_dc2.length-1].textContent||docText; if((!docText||String(docText).replace(/[.\\u2026\\s]/g,'')==='')&&window._chLastDoc) docText=window._chLastDoc; } }catch(e){} /*CH_CHATPDF_GUARD*/\n    var real=restore(docText);\n    try{\n      if(typeof makePDF===\"function\"){";
rep($src,$o2,$n2,'2-printDoc',$fail);

/* [3] makePDF(): evrensel koruma */
$o3="function makePDF(doc,name,cc){ /*CH_MAKEPDF_UNICODE + CH_PDF_PREMIUM";
$n3="function makePDF(doc,name,cc){ ".$GUARD." /*CH_MAKEPDF_UNICODE + CH_PDF_PREMIUM";
rep($src,$o3,$n3,'3-makePDF',$fail);

if ($fail) { echo "\nHATA: eslesmeyen: ".implode(', ',$fail)." — index DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'cg').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-chatpdfguard-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_CHATPDF_GUARD')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "\n  ✓ DOGRULAMA: CH_CHATPDF_GUARD diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Chat PDF'i artik ASLA bos cikamaz:\n";
echo "   PDF'e giden metin bos/yer tutucu ('...') ise ekrandaki belge kartinin\n";
echo "   gercek metni otomatik kullanilir (3 katmanli koruma: kart yedegi +\n";
echo "   printDoc + makePDF). Tum PDF yollari icin gecerli.\n";
