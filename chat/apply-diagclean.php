<?php
/**
 * ChatHelp — apply-diagclean — teshis beacon'larini KALDIRIR, kilidi tazeler.
 *
 * Neden: CH_LOADLOG her sayfa acilisinda (web kullanicilari dahil) sunucuya
 * UA kaydi gonderiyordu; CH_APPLOG/CH_CPDLOG/CH_BRANCHLOG da teshis icindi.
 * Is bitti, uretimde durmamali.
 *
 * KORUNAN (dokunulmaz): CH_ISWV_GLOBAL, CH_APPVIEW3, CH_APPVIEW2C (4 cagri),
 * pv.php, pvsave.php  — App yazdirma bunlara bagli.
 * chlog9k1.php birakilir (anahtar korumali; ileride teshis gerekirse hazir),
 * ama artik kendiliginden hicbir sey yazilmaz.
 *
 * Temizlik sonrasi kilit ANLIK GORUNTUSU tazelenir (temiz hal kilitlenir).
 * KULLANIM: /chat/pull2.php?key=...&files=apply-diagclean.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
echo "apply-diagclean BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$dir=__DIR__; $file=$dir.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
$new=$src;

echo "=== Temizlik ===\n";
$before=['CH_LOADLOG'=>substr_count($new,'CH_LOADLOG'),'CH_APPLOG'=>substr_count($new,'CH_APPLOG'),
         'CH_CPDLOG'=>substr_count($new,'CH_CPDLOG'),'CH_BRANCHLOG'=>substr_count($new,'CH_BRANCHLOG')];
$new=preg_replace('/<script>\/\*CH_LOADLOG\*\/.*?<\/script>/s','',$new);
$new=preg_replace('/\s*\/\*CH_APPLOG\*\/ try\{.*?\}catch\(_e9\)\{\}/s','',$new);
$new=preg_replace('/\s*\/\*CH_CPDLOG\*\/try\{.*?\}catch\(_ec\)\{\}/s','',$new);
$new=preg_replace('/\s*\/\*CH_BRANCHLOG\*\/try\{.*?\}catch\(_eb\)\{\}/s','',$new);
if($new===null) exit("preg hatasi\n");
foreach($before as $m=>$c) printf("  %-14s %d -> %d\n",$m,$c,substr_count($new,$m));

/* korunanlari dogrula */
echo "\n=== Korunanlar (dokunulmadi) ===\n";
$keep=['CH_ISWV_GLOBAL'=>1,'CH_APPVIEW3'=>1,'CH_APPVIEW2C'=>4];
$bad=[];
foreach($keep as $m=>$min){
  $c=substr_count($new,$m); $ok=($c>=$min);
  printf("  %-16s %d / en az %d  %s\n",$m,$c,$min,$ok?'OK':'✗ KAYIP');
  if(!$ok)$bad[]=$m;
}
if($bad) exit("\n✗ Kritik isaret kaybolacakti — DEGISIKLIK YOK. (".implode(', ',$bad).")\n");

if($new===$src){ echo "\n· Temizlenecek bir sey yok — index degismedi.\n"; }
else{
  $tmp=tempnam(sys_get_temp_dir(),'dc').'.php'; file_put_contents($tmp,$new);
  $lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
  if($rc!==0) exit("\n✗ LINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n");
  @file_put_contents($file.'.bak-diagclean-'.date('Ymd-His'),$src);
  $w=@file_put_contents($file,$new);
  if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
  if(function_exists('opcache_reset')) @opcache_reset();
  echo "\n✓ index.php temizlendi (".strlen($src)." -> ".strlen($new)." bayt)\n";
}

/* eski logger + kayit dosyalari */
foreach(['chlog.php','chlog.txt','chlog9k1.txt'] as $f){
  echo (@unlink($dir.'/'.$f) ? "  🗑 silindi: $f\n" : "  · yok: $f\n");
}
echo "  · birakildi: chlog9k1.php (anahtar korumali, ileride teshis icin)\n";

/* kilidi tazele */
$cur=(string)@file_get_contents($file);
$snap=$file.'.GOOD-appprint';
@file_put_contents($snap,$cur);
$rec=['locked_at'=>date('c'),'size'=>strlen($cur),'sha1'=>sha1($cur),
      'snapshot'=>basename($snap),'note'=>'diagclean sonrasi temiz hal',
      'markers'=>['CH_ISWV_GLOBAL'=>substr_count($cur,'CH_ISWV_GLOBAL'),
                  'CH_APPVIEW3'=>substr_count($cur,'CH_APPVIEW3'),
                  'CH_APPVIEW2C'=>substr_count($cur,'CH_APPVIEW2C')],
      'files'=>['pv.php','pvsave.php']];
@file_put_contents($dir.'/ch-applock.json',json_encode($rec,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo "\n✓ KILIT TAZELENDI (temiz hal): sha1 ".substr($rec['sha1'],0,12)."…  (".$rec['size']." B)\n";
echo "\nSon kontrol icin: pull2.php?...&files=chk-applock.php\n";
