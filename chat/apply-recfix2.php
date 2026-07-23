<?php
/**
 * ChatHelp — apply-recfix2 — CH_RECFIX'in kalan IKINCI kopyasi: CH_FAX_UX
 *  onizleme satirindaki recName() COK-SATIRLI formatta oldugu icin ilk yamada
 *  atlanmisti (kritik KI-bul kopyasi DUZELDI). Bu, fax onizlemesindeki
 *  "Wird gefaxt an: <isim>" satirinin da alici adini gostermesini saglar.
 * KULLANIM: pull2.php?key=...&files=apply-recfix2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-recfix2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");

$oldRN2="function recName(){\n    try{ var r=document.getElementById('ai-recipient'); if(r&&r.value) return (r.value.split('\\n')[0]||'').trim(); }catch(e){}\n    return '';\n  }";
$newRN='function recName(){/*CH_RECFIX*/ var _v=\'\'; try{ var r=document.getElementById(\'ai-recipient\'); if(r&&r.value&&r.value.trim()) _v=r.value; }catch(e){} try{ if(!_v&&typeof recVal===\'function\'){ var q=recVal(); if(q&&String(q).trim()) _v=String(q); } }catch(e){} try{ if(!_v){ var bx=document.getElementById(\'ai-box\'); if(bx){ var els=bx.querySelectorAll(\'textarea,input[type=text],input:not([type])\'); for(var i=0;i<els.length;i++){ var idp=(els[i].id||\'\')+\' \'+(els[i].placeholder||\'\'); if(/rec|empf|adressat|alici/i.test(idp)&&els[i].value&&els[i].value.trim()){ _v=els[i].value; break; } } } } }catch(e){} try{ if(!_v&&window._lastGenAns&&window._lastGenAns.ans){ var a=window._lastGenAns.ans,ks=Object.keys(a); for(var j=0;j<ks.length;j++){ if(/empf|recip|adressat|behoerd|alici|an_/i.test(ks[j])&&a[ks[j]]&&String(a[ks[j]]).trim()){ _v=String(a[ks[j]]); break; } } } }catch(e){} return _v?(_v.split(\'\n\')[0]||\'\').trim():\'\'; }';

$c=substr_count($src,$oldRN2);
if($c<1){ echo "  = cok-satirli recName bulunamadi ($c) — zaten duzeltilmis olabilir. HICBIR sey degistirilmedi.\n"; exit; }
if($c>2){ echo "  ✗ beklenmedik kopya sayisi ($c) — HICBIR sey degistirilmedi.\n"; exit; }
$src=str_replace($oldRN2,$newRN,$src);
echo "  ✓ onizleme recName() kopyasi da 4-kaynakli yapildi ($c)\n";

$tmp=tempnam(sys_get_temp_dir(),'rf2').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-recfix2-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(substr_count($chk,$oldRN2)>0) exit("\n✗ DOGRULAMA BASARISIZ (eski kopya hala var).\n");
echo "\n  ✓ tamam (".strlen($chk)." B)\n";
echo "  Test: Fax sec -> onizleme satiri 'Wird gefaxt an: <alici> · <numara>' seklinde\n";
echo "  alici adini da gostermeli.\n";
