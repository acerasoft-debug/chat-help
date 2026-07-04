<?php
/** ChatHelp — dump-revacct: Google Play inceleme test hesabı için gerekli şema + auth mantığı. SADECE OKUR. */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
require_once __DIR__ . '/db.php';
if (file_exists(__DIR__ . '/config.php')) require_once __DIR__ . '/config.php';

$auth = @file_get_contents(__DIR__.'/auth.php');
if ($auth===false) { echo "auth.php okunamadi\n"; }

function FX($s,$name,$lbl,$cap=3500){
  echo "\n──────── $lbl ────────\n";
  if($s===false){ echo "(dosya yok)\n"; return; }
  $p=strpos($s,$name);
  if($p===false){ echo "(BULUNAMADI: $name)\n"; return; }
  $b=strpos($s,'{',$p); if($b===false){ echo substr($s,$p,300)."\n"; return; }
  $depth=0;$i=$b;$len=strlen($s);
  for(;$i<$len;$i++){ $c=$s[$i]; if($c==='{')$depth++; elseif($c==='}'){ $depth--; if($depth===0){ $i++; break; } } }
  echo substr($s,$p,min($i-$p,$cap)).(($i-$p)>$cap?"\n…(kirpildi)":"")."\n";
}

echo "###### 1) DB TABLO ŞEMALARI ######\n";
try { $pdo = db(); } catch (Exception $e) { exit("DB baglanamadi: ".$e->getMessage()."\n"); }
foreach (['users','user_plans'] as $t) {
  echo "\n-- $t --\n";
  try {
    $cols = $pdo->query("SHOW COLUMNS FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) echo "  {$c['Field']}  {$c['Type']}  ".($c['Null']==='NO'?'NOT NULL':'null').($c['Key']?" [{$c['Key']}]":'').($c['Default']!==null?" default={$c['Default']}":'')."\n";
  } catch (Exception $e) { echo "  (okunamadi: ".$e->getMessage().")\n"; }
}

echo "\n\n###### 2) auth.php: register mantigi (sifre hash + hangi sutunlar + verify sütunu) ######\n";
FX($auth,'function register','function register');
FX($auth,"'register'","case/branch 'register' civari",900);
// password hash yöntemi
echo "\n-- password_hash / md5 / sha kullanımı --\n";
foreach (['password_hash','password_verify','md5(','sha1(','hash(','crypt('] as $k){
  $n=substr_count($auth,$k); if($n) echo "  $k -> $n kez\n";
}

echo "\n\n###### 3) auth.php: login mantigi (sifre nasil dogrulaniyor + verified kontrolu) ######\n";
FX($auth,'function login','function login');

echo "\n\n###### 4) verify/aktivasyon sütunu ipuçları ######\n";
foreach (['verified','is_verified','email_verified','active','status','confirmed','verify_token'] as $k){
  if (stripos($auth,$k)!==false) echo "  auth.php içinde geçiyor: $k\n";
}

echo "\n=== BİTTİ. Çıktının TAMAMINI bana gönder. SİL: rm dump-revacct.php ===\n";
