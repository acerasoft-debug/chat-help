<?php
/**
 * ChatHelp — apply-cleanline2 (CH_CLEANLINE_DOC) — cleanline'in atlanan chDocClean
 *  bolumu: canlida chDocClean 2 KOPYA -> ikisine de alt-cizgi temizligi eklenir.
 * KULLANIM: pull2.php?key=...&files=apply-cleanline2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-cleanline2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_CLEANLINE_DOC')!==false) exit("Zaten ekli (CH_CLEANLINE_DOC).\n");

$o2="function chDocClean(text){ try{ text=String(text||''); var nm=chSignerName(); if(nm){ text=text.replace(/Acerasoft(\\s+LLC)?/gi,nm); } return text; }catch(e){ return String(text||''); } }";
$n2="function chDocClean(text){ try{ text=String(text||''); var nm=chSignerName(); if(nm){ text=text.replace(/Acerasoft(\\s+LLC)?/gi,nm); } text=text.replace(/^[ \\t]*[_\\-\\u2013\\u2014]{3,}[ \\t]*$/gm,'').replace(/\\n{3,}/g,'\\n\\n');/*CH_CLEANLINE_DOC*/ return text; }catch(e){ return String(text||''); } }";
$c=substr_count($src,$o2);
if($c<1||$c>3){ echo "  ✗ anchor ($c, 1-3 beklenir) — HICBIR sey degistirilmedi.\n"; exit; }
$src=str_replace($o2,$n2,$src);
echo "  ✓ chDocClean $c kopyada alt-cizgi temizligi eklendi\n";

$tmp=tempnam(sys_get_temp_dir(),'cl2').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-cleanline2-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_CLEANLINE_DOC')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_CLEANLINE_DOC diskte (".strlen($chk)." B)\n";
echo "  Artik hem PDF hem gonderim yolunda etiketsiz '____' cizgileri temizlenir.\n";
