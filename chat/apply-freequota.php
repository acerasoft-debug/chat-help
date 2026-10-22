<?php
/**
 * ChatHelp — apply-freequota (CH_FREEQUOTA) — SUNUCU ucu 'freequota.php' yazar:
 *  IP-hash ile anahtar (data/freeip/{sha256(salt|ip)}.json), 1 free/IP, KALICI,
 *  EMAIL'e bakmaz -> email degistirilse de ayni IP ikinci free ALAMAZ.
 *  action=claim : atomik (fopen 'x') -> free varsa isaretle+ver, yoksa ver=false.
 *  action=peek  : sadece durum (tuketmez).
 * KULLANIM: pull2.php?key=...&files=apply-freequota.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-freequota BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$endpoint = <<<'PHPEOF'
<?php
/* ChatHelp — freequota.php (CH_FREEQUOTA) — IP-bazli 1 gratis Dokument. Email-unabhängig. */
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');
error_reporting(E_ERROR | E_PARSE);
$SALT='chfree_v1_9f3ac71b';
function ch_client_ip(){
  $c=$_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
  $c=trim(explode(',',$c)[0]);
  return filter_var($c,FILTER_VALIDATE_IP) ? $c : '0.0.0.0';
}
$dir=__DIR__.'/data/freeip';
if(!is_dir($dir)) @mkdir($dir,0755,true);
$ip=ch_client_ip();
$key=hash('sha256',$SALT.'|'.$ip);
$file=$dir.'/'.$key.'.json';
$action=$_GET['action'] ?? 'peek';

if($action==='claim'){
  /* atomik: dosya YOKSA olustur (free ver); VARSA zaten kullanilmis */
  $fp=@fopen($file,'x');
  if($fp===false){ echo json_encode(['free'=>false,'used'=>true]); exit; }
  @fwrite($fp,json_encode(['used'=>1,'ts'=>time()]));
  @fclose($fp);
  echo json_encode(['free'=>true,'granted'=>true]); exit;
}
/* peek / status: sadece durum */
echo json_encode(['free'=>!is_file($file),'used'=>is_file($file)]);
PHPEOF;

$ep=__DIR__.'/freequota.php';
$w=@file_put_contents($ep,$endpoint);
echo ($w!==false ? "  ✓ freequota.php yazildi (".strlen($endpoint)." B)\n" : "  ✗ freequota.php YAZILAMADI\n");
$lo=[];$rc=0; exec('php -l '.escapeshellarg($ep).' 2>&1',$lo,$rc);
echo ($rc===0 ? "  ✓ lint temiz\n" : "  ✗ LINT HATASI:\n     ".implode("\n     ",$lo)."\n");

/* data/freeip dizini + koruma */
$fd=__DIR__.'/data/freeip';
if(!is_dir($fd)) @mkdir($fd,0755,true);
@file_put_contents(__DIR__.'/data/freeip/.htaccess',"Require all denied\nDeny from all\n");
echo (is_dir($fd) ? "  ✓ data/freeip/ hazir (korumali)\n" : "  ✗ data/freeip olusturulamadi\n");

/* opcache */
if(function_exists('opcache_reset')) @opcache_reset();

echo "\n  Test: freequota.php?action=peek -> {\"free\":true} (ilk kez), claim sonrasi {\"free\":false}\n";
echo "  Sirada: apply-free1ip.php (istemci baglama).\n";
