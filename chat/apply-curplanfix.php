<?php
/**
 * ChatHelp — apply-curplanfix (CH_CURPLANFIX) — KESIN KOK NEDEN: 'Dokument freischalten'
 *  odeme penceresinde Basic 'Aktiv' gorunuyordu (Elite hesapta bile) — curPlan()
 *  fonksiyonu SADECE eski/hic-senkronize-edilmeyen 'ch_profile' (TEKIL) anahtarindan
 *  okuyordu, dogru kaynaktan (P.plan / ch_user.plan) DEGIL. Bu eski anahtar aylar
 *  once 'basic' olarak kalmis ve hicbir login/guncelleme onu degistirmiyordu.
 *  COZUM: curPlan() artik ONCE P.plan, SONRA ch_user.plan, EN SON (yedek) eski
 *  ch_profile okur. Bu, paywall/Aktiv-etiketi dahil TUM curPlan() cagiranlari
 *  kalici olarak duzeltir.
 *  1 sayac-korumali str_replace. Eslesmezse HICBIR sey degistirilmez.
 * KULLANIM: pull2.php?key=...&files=apply-curplanfix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-curplanfix BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_CURPLANFIX')!==false) exit("Zaten ekli (CH_CURPLANFIX).\n");

$old = 'function curPlan(){ try{ var p=JSON.parse(localStorage.getItem("ch_profile")||"{}"); return p.plan||""; }catch(e){ return ""; } }';
$new = 'function curPlan(){/*CH_CURPLANFIX*/ try{ if(window.P&&P.plan) return P.plan; }catch(e0){} try{ var lu=JSON.parse(localStorage.getItem("ch_user")||"{}"); if(lu.plan) return lu.plan; }catch(e1){} try{ var p=JSON.parse(localStorage.getItem("ch_profile")||"{}"); return p.plan||""; }catch(e){ return ""; } }';

$c=substr_count($src,$old);
if($c!==1){ echo "  ✗ anchor ($c, 1 beklenir) — HICBIR sey degistirilmedi.\n"; exit; }
$src=str_replace($old,$new,$src);
echo "  ✓ curPlan(): artik ONCE P.plan, SONRA ch_user.plan, EN SON eski ch_profile okur\n";

$tmp=tempnam(sys_get_temp_dir(),'cpf').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-curplanfix-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_CURPLANFIX')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_CURPLANFIX diskte (".strlen($chk)." B)\n";
echo "  Test: bir belge uret -> odeme penceresi acilirsa (ELITE'te acilmamali ama\n";
echo "  test icin bak) -> ELITE karti 'Aktiv' gostermeli, Basic degil.\n";
