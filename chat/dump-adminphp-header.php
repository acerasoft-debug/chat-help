<?php
/**
 * ChatHelp — dump-adminphp-header (SADECE OKUR) — admin.php'nin ust/nav
 *  yapisini gormek (admin-users.php'ye link eklemek icin dogru anchor).
 *  Ayrica users tablosunda 'blocked'/'reg_ip'/'last_ip' sutunlari var mi
 *  ve blocked_ips tablosu var mi kontrol eder. HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-adminphp-header.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$adm=(string)@file_get_contents("$D/admin.php");
echo "=== dump-adminphp-header — SADECE OKUR ===\n\n";
echo "[0] admin.php boyutu: ".strlen($adm)." bayt\n\n";

echo "[1] <body> ile ilk 60 satir (ust/nav yapisi):\n";
$p=stripos($adm,'<body');
if($p!==false){ echo substr($adm,$p,2200)."\n"; } else echo "(body bulunamadi)\n";

echo "\n[2] 'admin-users' gecen yerler (zaten link var mi):\n";
$off=0;$n=0;
while(($p=strpos($adm,'admin-users',$off))!==false && $n<6){
  $seg=substr($adm,max(0,$p-80),160); $seg=preg_replace('/\s+/',' ',$seg);
  echo "  …".$seg."…\n"; $off=$p+11;$n++;
}
if($n===0) echo "  (yok)\n";

echo "\n[3] users tablosu sema kontrol (blocked/reg_ip/last_ip sutunlari + blocked_ips tablosu):\n";
@require_once "$D/config.php";
@require_once "$D/db.php";
if (function_exists('db')) {
  try {
    $pdo=db();
    $cols=$pdo->query("SHOW COLUMNS FROM users")->fetchAll();
    $names=array_map(function($c){return $c['Field'];},$cols);
    echo "  users sutunlari: ".implode(', ',$names)."\n";
    foreach(['blocked','reg_ip','last_ip'] as $c){
      echo "  '$c' sutunu: ".(in_array($c,$names)?"VAR":"YOK")."\n";
    }
    $t=$pdo->query("SHOW TABLES LIKE 'blocked_ips'")->fetchAll();
    echo "  blocked_ips tablosu: ".($t?"VAR":"YOK")."\n";
  } catch (Throwable $e) { echo "  DB HATASI: ".$e->getMessage()."\n"; }
} else echo "  db() yuklenemedi\n";

echo "\n=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
