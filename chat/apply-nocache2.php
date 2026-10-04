<?php
/**
 * ChatHelp — apply-nocache2 (CH_NOCACHE2) — KOKTEN COZUM: saatlerdir yasanan "degisiklik
 *  gorunmuyor" sorunu icin sayfaya asla-onbellekleme talimati ekler. Boylece BUNDAN
 *  SONRAKI her pull2 degisikligi ANINDA gorunur — App-restart/Force-Stop gerekmez.
 *  [1] Meta http-equiv cache-control (HTML <head>, PHP header() zamanlama riski yok,
 *      her yerde guvenli calisir).
 *  [2] PHP header() (mumkunse gercek HTTP header — proxy/CDN'de de etkili).
 *  Additive, 2 sayac-korumali ekleme. Mevcut isleve DOKUNMAZ.
 * KULLANIM: pull2.php?key=...&files=apply-nocache2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-nocache2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_NOCACHE2')!==false) exit("Zaten ekli (CH_NOCACHE2).\n");

$fail=[];

/* [1] meta http-equiv cache-control — icon-16 link'inden hemen once (guvenilir tekil anchor) */
$o1='<link rel="icon" type="image/png" sizes="16x16" href="/chat/icon-16.png?a7">';
$n1='<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0"><!--CH_NOCACHE2-->'."\n".
    '<meta http-equiv="Pragma" content="no-cache">'."\n".
    '<meta http-equiv="Expires" content="0">'."\n".
    $o1;
$c1=substr_count($src,$o1);
if($c1===1){ $src=str_replace($o1,$n1,$src); echo "  ✓ [1] meta no-cache etiketleri eklendi\n"; }
else { echo "  ✗ [1] anchor ($c1, 1 beklenir)\n"; $fail[]=1; }

/* [2] gercek PHP header() — dosyanin EN BASINDA <?php varsa hemen sonrasina ekle */
if(substr($src,0,5)==='<?php'){
  $o2='<?php';
  $n2="<?php\n/*CH_NOCACHE2_HDR*/ if(!headers_sent()){ header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0'); header('Pragma: no-cache'); header('Expires: 0'); }";
  $pos=strpos($src,$o2);
  if($pos===0){
    $src=$n2.substr($src,strlen($o2));
    echo "  ✓ [2] gercek PHP Cache-Control header() eklendi (dosya basi)\n";
  } else echo "  = [2] dosya '<?php' ile baslamiyor gibi gorundu — atlandi (guvenli)\n";
} else {
  echo "  = [2] dosya PHP ile baslamiyor (HTML-once olabilir) — sadece meta-tag kullanilacak\n";
}

if(in_array(1,$fail)){ echo "\nHATA: kritik adim [1] eslesmedi — HICBIR sey degistirilmedi.\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'nch').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-nocache2-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_NOCACHE2')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_NOCACHE2 diskte (".strlen($chk)." B)\n";
echo "  ETKI: BUNDAN SONRA hicbir degisiklik icin App-restart/Force-Stop GEREKMEZ.\n";
echo "  SIMDI icin: bu yamanin KENDISININ gorunmesi icin YINE DE son bir kez App'i\n";
echo "  Force Stop et (bu, gecmisteki eski sayfayi temizleyecek SON adim).\n";
echo "  Ondan SONRA: Konto -> plan 'ELITE' gorunmeli, dilekce uretimi calismali.\n";
