<?php
/**
 * ChatHelp — apply-phaxio-setup — Phaxio (Sinch Fax) anahtarlarini config.php'ye
 *  yazar + FAX_PROVIDER=phaxio yapar + Phaxio'ya baglanip DOGRULAR.
 *  Anahtarlar URL'den gelir (git'te/koda GOMULU DEGIL):
 *    ...&files=apply-phaxio-setup.php&k=KEY&s=SECRET
 *  Iki sirali (k:s ve s:k) otomatik denenir, calisan kullanilir. Degerler
 *  ekrana ASLA tam basilmaz (maskeli).
 * KULLANIM: pull2.php?key=...&files=apply-phaxio-setup.php&k=...&s=...
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "== apply-phaxio-setup ==\n\n";

$k = trim((string)($_GET['k'] ?? ''));
$s = trim((string)($_GET['s'] ?? ''));
function mask($v){ $v=(string)$v; $n=strlen($v); return $n<=6?str_repeat('*',$n):(substr($v,0,3).str_repeat('*',max(0,$n-6)).substr($v,-3)); }
if($k===''||$s===''){
  echo "ANAHTAR EKSIK.\n";
  echo "Su sekilde cagir (Phaxio Key + Secret):\n";
  echo "  ...&files=apply-phaxio-setup.php&k=KEY_BURAYA&s=SECRET_BURAYA\n";
  exit;
}
echo "Alinan: k=".mask($k)." · s=".mask($s)."\n\n";

/* Phaxio account/status ile dogrula (iki sirayi da dene) */
function phaxio_status($user,$pass){
  $ch=curl_init('https://api.phaxio.com/v2.1/account/status');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25,CURLOPT_USERPWD=>$user.':'.$pass]);
  $r=curl_exec($ch); $c=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $e=curl_error($ch); curl_close($ch);
  $j=json_decode((string)$r,true);
  $ok=($c>=200&&$c<300 && is_array($j) && !empty($j['success']));
  return [$ok,$c,$j,$e,$r];
}
echo "=== Phaxio dogrulama ===\n";
list($ok1,$c1,$j1)=phaxio_status($k,$s);
$KEY=$k; $SEC=$s; $ok=$ok1; $used='k:s';
if(!$ok1){
  list($ok2,$c2,$j2)=phaxio_status($s,$k);
  if($ok2){ $KEY=$s; $SEC=$k; $ok=true; $used='s:k (ters)'; $c1=$c2; $j1=$j2; }
}
if(!$ok){
  echo "  ✗ Phaxio dogrulanamadi (her iki sira da). HTTP $c1\n";
  echo "  yanit: ".substr(json_encode($j1),0,250)."\n";
  echo "  -> Anahtarlar yanlis olabilir ya da bunlar Phaxio degil baska servis anahtari.\n";
  echo "  Hicbir sey config'e YAZILMADI.\n";
  exit;
}
echo "  ✓ PHAXIO BAGLANDI ($used) · HTTP $c1\n";
$bal = isset($j1['data']['balance']) ? $j1['data']['balance'] : null;
if($bal!==null) echo "  bakiye: ".($bal/100)." ".($j1['data']['balance_currency']??'')."\n";

/* config.php'ye yaz */
$cfg=__DIR__.'/config.php';
$src=@file_get_contents($cfg);
if($src===false){ echo "\n✗ config.php okunamadi.\n"; exit; }

/* mevcut tanimlari temizle */
$src=preg_replace("/^\s*define\(\s*'(PHAXIO_KEY|PHAXIO_SECRET|FAX_PROVIDER)'.*?\);\s*$/mi",'',$src);
$ins="\n/* CH_PHAXIO — Sinch/Phaxio Fax */\n"
    ."define('FAX_PROVIDER','phaxio');\n"
    ."define('PHAXIO_KEY', ".var_export($KEY,true).");\n"
    ."define('PHAXIO_SECRET', ".var_export($SEC,true).");\n";
/* <?php'den hemen sonra ekle */
if(preg_match('/^\s*<\?php/', $src)){
  $src=preg_replace('/^(\s*<\?php)/', "$1\n".$ins, $src, 1);
} else {
  $src="<?php\n".$ins.$src;
}

$tmp=tempnam(sys_get_temp_dir(),'ph').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "\n✗ config.php LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@copy($cfg,$cfg.'.bak-phaxio-'.date('Ymd-His'));
$w=@file_put_contents($cfg,$src);
if($w===false){ echo "\n✗ config.php yazilamadi.\n"; exit; }
if(function_exists('opcache_reset')) @opcache_reset();

/* dogrula: yeniden yukle */
$chk=(string)@file_get_contents($cfg);
$hasP = (strpos($chk,'CH_PHAXIO')!==false && strpos($chk,"FAX_PROVIDER','phaxio")!==false);
echo "\n  ".($hasP?"✓ config.php guncellendi — FAX_PROVIDER=phaxio, anahtarlar yazildi":"✗ dogrulama basarisiz")."\n";
echo "\n  SONRAKI:\n";
echo "  1) postversand.php?action=status  -> fax_provider: phaxio gorunmeli\n";
echo "  2) apply-faxtest.php&to=+49...     -> gercek test faksi\n";
echo "  3) ⚠️ SOHBETE yapistirdigin anahtarlari Phaxio panelinden YENILE (guvenlik)\n";
echo "\n== BITTI ==\n";
