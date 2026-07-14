<?php
/**
 * ChatHelp — apply-authlabel (CH_AUTHLABEL) — hesap butonu "Konto" yerine ADI gostersin.
 *  Sorun: ch_user.name bos oldugunda etiket "Konto"ya dusuyor; oysa profilde
 *  (ch_prof_v3.f1/f2 / P.f1/f2) ad var. topbar-merge'deki nameOrKonto'yu yamalar:
 *  ch_user.name -> profil adi -> "Konto" sirasiyla. Byte-exact str_replace.
 * KULLANIM: pull2.php?key=...&files=apply-authlabel.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-authlabel BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_AUTHLABEL')!==false) exit("Zaten ekli (CH_AUTHLABEL).\n");

$old = "var nm=(u&&u.name)?String(u.name).trim():''; if(nm) return nm.split(' ')[0];";
$new = "var nm=(u&&u.name)?String(u.name).trim():'';"
     . " if(!nm){try{var pr=JSON.parse(localStorage.getItem('ch_prof_v3')||'{}');if(pr&&(pr.f1||pr.f2))nm=((pr.f1||'')+' '+(pr.f2||'')).trim();}catch(_e2){}}"
     . " if(!nm){try{if(window.P&&(P.f1||P.f2))nm=((P.f1||'')+' '+(P.f2||'')).trim();}catch(_e3){}}"
     . " if(nm) return nm.split(' ')[0]; /*CH_AUTHLABEL*/";

$c = substr_count($src,$old);
if ($c!==1) exit("HATA: nameOrKonto anchor $c kez (1 bekleniyordu) — once topbar-merge uygulanmali. index DEGISTIRILMEDI.\n");
$src = str_replace($old,$new,$src);

$tmp = tempnam(sys_get_temp_dir(),'al').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-authlabel-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_AUTHLABEL')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_AUTHLABEL diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Hesap butonu: ch_user.name -> profil adi -> 'Konto'. Profilin varsa adin gorunur.\n";
