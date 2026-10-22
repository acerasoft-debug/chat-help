<?php
/**
 * ChatHelp — apply-cleanline (CH_CLEANLINE) — belgedeki yalniz alt-cizgi/imza-cizgisi
 *  satirlarini (____ , ---- gibi, etiketsiz) TEMIZLER. Imza gorsel olarak ismin
 *  altina zaten ekleniyor; bu bos cizgiler belgeyi kirli gosteriyordu.
 *  Iki temizleyiciye de eklenir:
 *   [1] chSanitizePDF (PDF yolu) — 'Als PDF speichern' / kart PDF
 *   [2] chDocClean (gonderim yolu) — Senden/Fax/Brief
 *  SADECE tamamen alt-cizgi/tire olan satirlar silinir; 'IBAN: ____' gibi
 *  ETIKETLI bosluklar KORUNUR (kullanici doldursun diye).
 * KULLANIM: pull2.php?key=...&files=apply-cleanline.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-cleanline BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");

$ok=0;

/* ── [1] chSanitizePDF ── */
if(strpos($src,'CH_CLEANLINE_PDF')===false){
  $o1=".replace(/[ \\t]+\\n/g,'\\n')\n    .replace(/\\n{3,}/g,'\\n\\n').trim();";
  $n1=".replace(/[ \\t]+\\n/g,'\\n')\n    .replace(/^[ \\t]*[_\\-\\u2013\\u2014]{3,}[ \\t]*$/gm,'')/*CH_CLEANLINE_PDF — etiketsiz imza-cizgisi satirlari*/\n    .replace(/\\n{3,}/g,'\\n\\n').trim();";
  $c=substr_count($src,$o1);
  if($c>=1 && $c<=2){ $src=str_replace($o1,$n1,$src); echo "  ✓ [1] chSanitizePDF: alt-cizgi satirlari temizlenir ($c)\n"; $ok++; }
  else echo "  ✗ [1] anchor ($c, 1-2 beklenir) — atlandi\n";
} else { echo "  = [1] zaten ekli\n"; $ok++; }

/* ── [2] chDocClean ── */
if(strpos($src,'CH_CLEANLINE_DOC')===false){
  $o2="function chDocClean(text){ try{ text=String(text||''); var nm=chSignerName(); if(nm){ text=text.replace(/Acerasoft(\\s+LLC)?/gi,nm); } return text; }catch(e){ return String(text||''); } }";
  $n2="function chDocClean(text){ try{ text=String(text||''); var nm=chSignerName(); if(nm){ text=text.replace(/Acerasoft(\\s+LLC)?/gi,nm); } text=text.replace(/^[ \\t]*[_\\-\\u2013\\u2014]{3,}[ \\t]*$/gm,'').replace(/\\n{3,}/g,'\\n\\n');/*CH_CLEANLINE_DOC*/ return text; }catch(e){ return String(text||''); } }";
  $c=substr_count($src,$o2);
  if($c===1){ $src=str_replace($o2,$n2,$src); echo "  ✓ [2] chDocClean: alt-cizgi satirlari temizlenir\n"; $ok++; }
  else echo "  ✗ [2] anchor ($c, 1 beklenir) — atlandi\n";
} else { echo "  = [2] zaten ekli\n"; $ok++; }

if($ok===0){ echo "\nHICBIR bolum uygulanamadi.\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'cl').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-cleanline-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
echo "\n  Sonuc: $ok/2 bolum tamam (".strlen($chk)." B)\n";
echo "  Test: belge uret -> en altta ismin ustundeki '____' cizgisi GITMIS olmali,\n";
echo "  belge temiz. 'IBAN: ____' gibi etiketli bosluklar KORUNUR.\n";
