<?php
/**
 * ChatHelp — apply-fallprof2 (CH_FALLPROF) — fallfix2'nin atlanan [1] bolumu, DAR
 *  anchor'larla: canli fall-api.php'de sadece 3 kucuk nokta degistirilir:
 *   [a] $prof satirindan sonra telefon-dogrulama yardimcilari eklenir
 *   [b] "E-Mail:" satiri bossa atlanir
 *   [c] "Telefon:" satirina tarih ASLA yazilmaz (f6 telefon degilse f5, o da
 *       degilse satir tamamen atlanir)
 *  Eslesmeyen nokta olursa canli metin EKRANA basilir (sonraki tur icin).
 * KULLANIM: pull2.php?key=...&files=apply-fallprof2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fallprof2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$fa=__DIR__.'/fall-api.php';
$src=@file_get_contents($fa);
if($src===false) exit("fall-api.php okunamadi\n");
if(strpos($src,'CH_FALLPROF')!==false) exit("Zaten ekli (CH_FALLPROF).\n");

$ok=0; $fail=false;

/* [a] yardimcilar */
$oa='$prof    = $body[\'profile\'] ?? [];';
$na='$prof    = $body[\'profile\'] ?? [];
    /* CH_FALLPROF */
    $chIsDate = function($s){ return (bool)preg_match(\'/^\s*(\d{4}-\d{2}-\d{2}|\d{1,2}\.\d{1,2}\.\d{2,4})\s*$/\', (string)$s); };
    $chIsTel  = function($s) use ($chIsDate){ $s=trim((string)$s); if($s===\'\'||$chIsDate($s)) return false; if(!preg_match(\'/^[+0-9 ()\/\-.]{5,}$/\',$s)) return false; return strlen(preg_replace(\'/\D/\',\'\',$s))>=6; };
    $chTel=\'\'; if($chIsTel($prof[\'f6\'] ?? \'\')) $chTel=trim((string)$prof[\'f6\']); elseif($chIsTel($prof[\'f5\'] ?? \'\')) $chTel=trim((string)$prof[\'f5\']);';
$c=substr_count($src,$oa);
if($c===1){ $src=str_replace($oa,$na,$src); echo "  ✓ [a] yardimcilar eklendi\n"; $ok++; }
else { echo "  ✗ [a] anchor ($c)\n"; $fail=true; }

/* [b] E-Mail satiri */
$ob='. "E-Mail: "  . ($prof[\'f7\'] ?? \'\') . "\n"';
$nb='. ((($prof[\'f7\'] ?? \'\') !== \'\') ? ("E-Mail: " . $prof[\'f7\'] . "\n") : "")';
$c=substr_count($src,$ob);
if($c===1){ $src=str_replace($ob,$nb,$src); echo "  ✓ [b] E-Mail satiri kosullu\n"; $ok++; }
else { echo "  ✗ [b] anchor ($c)\n"; $fail=true; }

/* [c] Telefon satiri */
$oc='. "Telefon: " . ($prof[\'f6\'] ?? \'\') . "\n"';
$nc='. (($chTel !== \'\') ? ("Telefon: " . $chTel . "\n") : "")';
$c=substr_count($src,$oc);
if($c===1){ $src=str_replace($oc,$nc,$src); echo "  ✓ [c] Telefon satiri dogrulamali\n"; $ok++; }
else { echo "  ✗ [c] anchor ($c)\n"; $fail=true; }

if($fail){
  echo "\n  Eslesmeyen nokta var — canli metin ('profile' civari 900 karakter):\n";
  $p=strpos($src,"body['profile']");
  if($p!==false){ echo "---8<---\n".substr($src,max(0,$p-100),1000)."\n--->8---\n"; }
  if($ok===0) exit("\nHICBIR sey degistirilmedi.\n");
  echo "\n  NOT: sadece eslesen kisimlar uygulanacak.\n";
}

$tmp=tempnam(sys_get_temp_dir(),'fp2').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@copy($fa,$fa.'.bak-fallprof2-'.date('Ymd-His'));
$w=@file_put_contents($fa,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($fa);
if(strpos($chk,'CH_FALLPROF')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_FALLPROF diskte ($ok/3)\n";
echo "  Test: Fall lösen -> 'Telefon:' satirinda tarih YOK (telefon yoksa satir da yok).\n";
