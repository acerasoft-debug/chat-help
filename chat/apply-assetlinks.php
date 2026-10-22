<?php
/**
 * ChatHelp — apply-assetlinks — APP MIKROFON COZUMU (TWA) sunucu ayagi:
 *  Google Play TWA paketinin adres dogrulamasi icin /.well-known/
 *  assetlinks.json dosyasini yazar. TWA kurulunca App, Chrome motoruyla
 *  calisir -> MIKROFON SITEDEKI GIBI DOGRUDAN CALISIR.
 * KULLANIM (parametreli!):
 *  pull2.php?key=...&files=apply-assetlinks.php&pkg=com.ornek.chathelp&sha=AA:BB:...:FF
 *  pkg = Android paket adi | sha = Play Console'daki SHA-256 imza parmak izi
 *  (Play Console -> Setup -> App integrity -> App signing key certificate)
 *  Birden fazla parmak izi: sha=XX:..,YY:.. (virgulle)
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-assetlinks BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$pkg = trim((string)($_GET['pkg'] ?? ''));
$sha = trim((string)($_GET['sha'] ?? ''));
if ($pkg==='' || $sha===''){
  echo "KULLANIM: URL'ye iki parametre ekleyin:\n";
  echo "  ...&files=apply-assetlinks.php&pkg=com.ornek.chathelp&sha=AA:BB:CC:...:FF\n\n";
  echo "  pkg -> App'in Android paket adi (Play Console'da gorunur)\n";
  echo "  sha -> Play Console > Setup > App integrity > App signing key\n";
  echo "         certificate altindaki SHA-256 fingerprint\n";
  echo "  Birden fazla parmak izi virgulle: sha=XX:...,YY:...\n";
  exit;
}
if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*(\.[a-zA-Z][a-zA-Z0-9_]*)+$/',$pkg))
  exit("HATA: pkg gecersiz gorunuyor: $pkg\n");
$fps=[];
foreach (explode(',',$sha) as $f){
  $f=strtoupper(trim($f));
  if (!preg_match('/^([A-F0-9]{2}:){31}[A-F0-9]{2}$/',$f))
    exit("HATA: SHA-256 parmak izi gecersiz: $f\n(32 cift hex, aralarinda ':' olmali)\n");
  $fps[]=$f;
}
$json = json_encode([[
  'relation'=>['delegate_permission/common.handle_all_urls'],
  'target'=>['namespace'=>'android_app','package_name'=>$pkg,'sha256_cert_fingerprints'=>$fps]
]], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

$dir = dirname(__DIR__).'/.well-known';
if (!is_dir($dir) && !@mkdir($dir,0755,true)) exit("HATA: $dir olusturulamadi\n");
$dest = $dir.'/assetlinks.json';
if (is_file($dest)) @copy($dest,$dest.'.bak-'.date('Ymd-His'));
$w=@file_put_contents($dest,$json);
$chk=(string)@file_get_contents($dest);
if ($w===false || strpos($chk,$pkg)===false) exit("HATA: yazilamadi/dogrulanamadi\n");
echo "  ✓ yazildi: /.well-known/assetlinks.json (".strlen($chk)." B)\n";
echo "  paket: $pkg\n  parmak izi: ".count($fps)." adet\n\n";
echo "KONTROL: https://chat-help.com/.well-known/assetlinks.json\n";
echo "  tarayicida acilmali ve paket adinizi gostermeli.\n";
echo "SONRAKI ADIM: .aab'yi Play Console'a yukleyin; yayimlandiktan sonra\n";
echo "  App'te adres cubugu GORUNMEZ ve mikrofon dogrudan calisir.\n";
