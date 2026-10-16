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