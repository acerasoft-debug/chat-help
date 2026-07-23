<?php
/**
 * ChatHelp — apply-senden4 — tek Senden, buyuk ve premium (2 edit):
 *  [1] CH_SENDEN1_OFF: eski enjekte edilen altin 'Senden ★ Gratis-Fax' butonu
 *      TAMAMEN kaldirilir (enjektor kapatilir) — kartta TEK Senden kalir.
 *  [2] CH_SENDEN3_BIG: yesil Senden buyutulur + premium (buyuk padding/font,
 *      guclu golge) + Gratis-Fax kota rozeti yesil butona tasinir.
 * KULLANIM: pull2.php?key=...&files=apply-senden4.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-senden4 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");

$ok=0;

/* ── [1] CH_SENDEN1_OFF — eski enjektoru kapat ── */
if(strpos($src,'CH_SENDEN1_OFF')===false){
  $o1="  function inject(){\n    try{\n      var all=document.querySelectorAll('button,a');";
  $n1="  function inject(){ return; /*CH_SENDEN1_OFF — eski cift Senden kaldirildi*/\n    try{\n      var all=document.querySelectorAll('button,a');";
  $c=substr_count($src,$o1);
  if($c===1){ $src=str_replace($o1,$n1,$src); echo "  ✓ [1] eski altin Senden (enjektor) kapatildi — tek Senden kaldi\n"; $ok++; }
  else echo "  ✗ [1] anchor ($c, 1 beklenir) — atlandi\n";
} else { echo "  = [1] zaten ekli\n"; $ok++; }

/* ── [2] CH_SENDEN3_BIG — yesil butonu buyut + rozet ── */
if(strpos($src,'CH_SENDEN3_BIG')===false){
  $o2="sd.style.cssText='background:linear-gradient(135deg,#37d17d,#1fa25c);color:#fff;font-weight:800;border:0;border-radius:12px;padding:11px 20px;font-size:14px;box-shadow:0 6px 18px rgba(47,209,125,.35);cursor:pointer';";
  $n2="sd.style.cssText='background:linear-gradient(135deg,#3fe08a,#1fa25c);color:#fff;font-weight:800;border:0;border-radius:14px;padding:14px 30px;font-size:16px;letter-spacing:.3px;box-shadow:0 8px 26px rgba(47,209,125,.45),0 0 0 1px rgba(255,255,255,.12) inset;cursor:pointer;display:inline-flex;align-items:center;gap:8px';/*CH_SENDEN3_BIG*/";
  $c=substr_count($src,$o2);
  if($c===1){ $src=str_replace($o2,$n2,$src); echo "  ✓ [2a] yesil Senden buyutuldu (premium golge/parlaklik)\n"; $ok++; }
  else echo "  ✗ [2a] anchor ($c, 1 beklenir) — atlandi\n";

  $o3='sd.innerHTML=\'\u{1F4E4} Senden\';';
  $n3='sd.innerHTML=\'\u{1F4E4} Senden\'; try{var _q4=window.chFaxQuota&&window.chFaxQuota(); if(_q4&&_q4.remaining>0){ sd.innerHTML+=\' <span style="background:rgba(255,255,255,.22);border-radius:100px;padding:2px 9px;font-size:11px;font-weight:800">★ \'+_q4.remaining+\' Gratis-Fax</span>\'; }}catch(e4){}';
  $c=substr_count($src,$o3);
  if($c===1){ $src=str_replace($o3,$n3,$src); echo "  ✓ [2b] Gratis-Fax rozeti yesil butona tasindi\n"; $ok++; }
  else echo "  = [2b] rozet anchoru ($c) — atlandi (kritik degil)\n";
} else { echo "  = [2] zaten ekli\n"; $ok++; }

if($ok===0){ echo "\nHICBIR bolum uygulanamadi — HICBIR sey degistirilmedi.\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'s4').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-senden4-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
echo "\n  Sonuc: $ok bolum tamam (".strlen($chk)." B)\n";
echo "  Test (app2.php'de): dilekce uret -> kartta TEK buyuk yesil 'Senden ★N Gratis-Fax'\n";
echo "  -> bas -> gonderim yontemleri acilir. Eski altin Senden YOK.\n";
