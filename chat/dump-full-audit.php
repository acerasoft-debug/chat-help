<?php
/**
 * ChatHelp — dump-full-audit (SADECE OKUR) — bugunku TUM degisikliklerin
 *  butunluk denetimi: lint'ler, marker'lar (tekil mi), ikon dosyalari,
 *  admin dosyalari, cakisma/kalinti kontrolu. HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-full-audit.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
echo "=== dump-full-audit — SADECE OKUR ===\n\n";

echo "[1] PHP lint (kritik dosyalar):\n";
foreach(['index.php','api.php','auth.php','admin.php','admin-users.php'] as $f){
  $p="$D/$f";
  if(!is_file($p)){ echo "  ✗ $f YOK!\n"; continue; }
  $lo=[];$rc=0; exec('php -l '.escapeshellarg($p).' 2>&1',$lo,$rc);
  echo "  ".($rc===0?"✓":"✗")." ".str_pad($f,16).number_format(filesize($p))." B  ".($rc===0?"TEMIZ":implode(' ',$lo))."\n";
}

$src=(string)@file_get_contents("$D/index.php");
echo "\n[2] index.php marker'lari (hepsi TAM 1 olmali; 0=eksik, 2+=cift kurulum):\n";
$M=['CH_NAVY_ICON','CH_THEME_HTMLBG','CH_THEME_BRIDGE','CH_THEME_AUTO','CH_CAT_TGFIX',
    'CH_MIC_UNI','CH_MIC_WVDIRECT','CH_MIC_APPTO','CH_VIZE_MERGE','CH_VIZE_PRUF',
    'CH_TR_EXPAND4','CH_VST_SEBE','CH_KONTO_RACEFIX','CH_VIZE_RDV','CH_VIZE_SELFEMP'];
foreach($M as $m){
  $c=substr_count($src,$m);
  $flag = ($m==='CH_VIZE_DATEFIX')?'':(($c===1)?'✓':(($c===0)?'⚠ EKSIK':'⚠ COKLU'));
  /* bazi markerlar yorum+kod diye 2 kez gecebilir — bilgi olarak goster */
  echo "  ".str_pad($m,20).": $c  ".($c===0?"⚠":"")."\n";
}
echo "  (CH_VIZE_DATEFIX: ".substr_count($src,'CH_VIZE_DATEFIX')." — 2 normal: css+js)\n";

echo "\n[3] Ikon dosyalari:\n";
foreach([16,32,64,180] as $s){
  $p="$D/icon-$s.png";
  echo "  ".(is_file($p)?"✓":"✗")." icon-$s.png ".(is_file($p)?filesize($p)." B":"YOK")."\n";
}

echo "\n[4] auth.php denetimi (kayit/giris + admin bloke):\n";
$a=(string)@file_get_contents("$D/auth.php");
foreach(['CH_ADMIN_BLOCK','ch_ip_blocked','CH_KONTO_BE','doSaveProfile','doGetProfile'] as $m)
  echo "  ".str_pad($m,16).": ".substr_count($a,$m)." kez\n";

echo "\n[5] admin-users.php denetimi:\n";
$au=(string)@file_get_contents("$D/admin-users.php");
foreach(['CH_ADMIN_USERS2','delete_user','block_user','block_ip','unblock_ip'] as $m)
  echo "  ".str_pad($m,16).": ".substr_count($au,$m)." kez\n";
echo "  admin.php 'Kunden' linki: ".(strpos((string)@file_get_contents("$D/admin.php"),'admin-users.php')!==false?"VAR ✓":"YOK ✗")."\n";

echo "\n[6] </html> sonrasi kalinti (zararsiz yorumlar haric):\n";
$ph=strripos($src,'</html>');
$tail=trim(substr($src,$ph+7));
$tailClean=trim(preg_replace('/<!--.*?-->/s','',$tail));
echo "  kuyruk uzunlugu: ".strlen($tail)." — yorum-disi icerik: ".($tailClean===''?"YOK ✓":"VAR ⚠: ".substr($tailClean,0,120))."\n";

echo "\n[7] .bak yedekleri (temizlik onerisi icin sayi):\n";
$baks=glob("$D/index.php.bak-*"); $tot=0; foreach($baks as $b) $tot+=filesize($b);
echo "  index.php.bak-* : ".count($baks)." adet, toplam ".number_format($tot)." B\n";

echo "\n[8] DB hizli kontrol (users sutunlari + blocked_ips + plan sayilari):\n";
@require_once "$D/config.php"; @require_once "$D/db.php";
if(function_exists('db')){
  try{
    $pdo=db();
    $cols=array_map(function($c){return $c['Field'];},$pdo->query("SHOW COLUMNS FROM users")->fetchAll());
    echo "  users sutunlari: ".implode(', ',$cols)."\n";
    echo "  stripe_customer_id sutunu: ".(in_array('stripe_customer_id',$cols)?"VAR":"YOK")."\n";
    $n=$pdo->query("SELECT COUNT(*) c FROM users")->fetch(); echo "  kullanici sayisi: ".$n['c']."\n";
    try{ $b=$pdo->query("SELECT COUNT(*) c FROM blocked_ips")->fetch(); echo "  blocked_ips kayit: ".$b['c']."\n"; }catch(Throwable $e){ echo "  blocked_ips: tablo yok\n"; }
    try{ $p=$pdo->query("SELECT plan,COUNT(*) c FROM user_plans WHERE status='active' GROUP BY plan")->fetchAll();
      foreach($p as $r) echo "  plan ".$r['plan'].": ".$r['c']."\n"; }catch(Throwable $e){}
  }catch(Throwable $e){ echo "  DB HATASI: ".$e->getMessage()."\n"; }
} else echo "  db() yuklenemedi\n";

echo "\n=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
