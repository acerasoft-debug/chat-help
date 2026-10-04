<?php
/**
 * ChatHelp — apply-nowelcome (CH_NOWELCOME) — acilista eklenen "Bitte geben Sie zuerst
 *  Ihre Daten ein" karsilama BALONU kaldirilir. Boylece ilk ekran = fonksiyonlarin oldugu
 *  ana sayfa (hero #wlc / wlc-quick). Bilgi girisi zaten dilekce baslarken (qS ->
 *  showProfileForm) isteniyor; o KORUNUR. Hero mevcut oldugu icin bos sohbet kalmaz.
 * KULLANIM: pull2.php?key=...&files=apply-nowelcome.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-nowelcome BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_NOWELCOME')!==false) exit("Zaten ekli (CH_NOWELCOME).\n");

$o='addMsg("ai",(WELCOME[UIL()]||WELCOME.en)+"\n\n"+(PRIV[UIL()]||PRIV.en))';
$n='void 0 /*CH_NOWELCOME — acilista veri-giris karsilamasi kaldirildi; fonksiyonlar gorunur, bilgi dilekcede istenir*/';

$c=substr_count($src,$o);
if($c<1){ echo "  ✗ anchor bulunamadi — degistirilmedi.\n"; exit; }
if($c>1){ echo "  ⚠ anchor $c kez — beklenen 1; yine de hepsi kaldirilir.\n"; }
$src=str_replace($o,$n,$src);
echo "  ✓ acilis karsilama balonu ($c yerde) kaldirildi\n";

$tmp=tempnam(sys_get_temp_dir(),'nw').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-nowelcome-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_NOWELCOME')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_NOWELCOME diskte (".strlen($chk)." B)\n";
echo "  Ilk acilis: fonksiyonlarin oldugu ana sayfa. Bilgi girisi dilekce baslarken istenir.\n";
