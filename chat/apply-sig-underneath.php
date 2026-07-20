<?php
/**
 * ChatHelp — apply-sig-underneath (CH_SIG_UNDER) — imza artik sayfa DIBINE degil,
 *  imza atanin ADININ HEMEN ALTINA konur.
 *  Kok neden (imza5 finalHtml): sig <img> '</body>' oncesine margin-top:24px ile
 *  ekleniyordu -> belgenin en sonuna dusuyor.
 *  Cozum: kapanis ifadesinden (Mit freundlichen Grüßen vb.) sonraki imza-sahibi
 *  adini bul, sig'i onun HEMEN ARDINA yerlestir. Bulamazsa eski davranis (fallback).
 *  1 count-guard'li str_replace + php -l. Sadece imza konumunu degistirir.
 * KULLANIM: pull2.php?key=...&files=apply-sig-underneath.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-sig-underneath BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_SIG_UNDER')!==false) exit("Zaten ekli (CH_SIG_UNDER).\n");

$old = <<<'OLD'
if(withSigPng){ var sig=sigPng(); if(sig){ var img='<div style="margin-top:24px"><img src="'+sig+'" style="height:62px"></div>'; if(/<\/body>/i.test(html)) html=html.replace(/<\/body>/i,img+'</body>'); else html=html+img; } }
OLD;
$old=rtrim($old,"\n");

$new = <<<'NEW'
if(withSigPng){ var sig=sigPng(); if(sig){ /*CH_SIG_UNDER*/ var img='<img src="'+sig+'" style="height:56px;display:block;margin:3px 0 8px">'; var _pl=false; try{ var _nm=chSignerName(); var _ci=html.search(/Mit freundlichen Grüßen|Hochachtungsvoll|Mit freundlichem Gru|freundlichen Grüßen|Sincerely|Best regards|Kind regards|Cordialement|Cordiali saluti|Distinti saluti|Atentamente|Saludos|Un saludo|Saygılar|İyi çalışmalar/i); if(_ci>=0 && _nm){ var _ni=html.indexOf(_nm, _ci); if(_ni>=0){ var _af=_ni+_nm.length; html=html.slice(0,_af)+img+html.slice(_af); _pl=true; } } }catch(e){} if(!_pl){ if(/<\/body>/i.test(html)) html=html.replace(/<\/body>/i,'<div style="margin-top:14px">'+img+'</div></body>'); else html=html+img; } } }
NEW;
$new=rtrim($new,"\n");

$c=substr_count($src,$old);
if($c!==1){ echo "  ✗ imza-insert anchor ($c, 1 beklenir) — DEGISTIRILMEDI.\n   (imza5 surumu farkli olabilir; dokunmadim.)\n"; exit; }
$src=str_replace($old,$new,$src);

$tmp=tempnam(sys_get_temp_dir(),'su').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-sigunder-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_SIG_UNDER')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_SIG_UNDER: imza artik imza-sahibi adinin hemen altina konuyor (".strlen($chk)." B)\n";
echo "  Test: belge uret -> imza at -> PDF -> imza 'Melissa Bilgic' isminin altinda, dipte degil.\n";
