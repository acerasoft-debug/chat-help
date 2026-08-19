<?php
/** ChatHelp — dump-forgot2 (SADECE OKUR) — kesin teshis:
 *  (1) frontend submitForgotPassword / submitResetPassword action= stringleri
 *  (2) DB: password_resets tablosu var mi? users.reset_token/reset_expires var mi?
 *  Hicbir sey YAZILMAZ. TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=(string)@file_get_contents(__DIR__.'/index.php');
function showfn($src,$name,$len=900){
  $p=strpos($src,'function '.$name);
  if($p===false){ echo "\n── $name: (YOK)\n"; return; }
  echo "\n──────── $name (@$p) ────────\n".str_replace("\n","⏎",substr($src,$p,$len))."\n[/end]\n";
}
echo "════ [1] frontend fonksiyonlari (action= stringleri) ════\n";
showfn($idx,'submitForgotPassword',900);
showfn($idx,'showForgot',500);
showfn($idx,'submitResetPassword',900);

echo "\n════ [2] DB kontrol ════\n";
try{
  require_once __DIR__.'/db.php';
  $pdo=db();
  $t=$pdo->query("SHOW TABLES LIKE 'password_resets'")->fetchAll();
  echo "password_resets tablosu: ".(count($t)?"VAR ✓":"YOK ✗ (forgot_password INSERT patlar)")."\n";
  if(count($t)){
    echo "  kolonlar: ";
    foreach($pdo->query("DESCRIBE password_resets") as $c){ echo $c['Field']."(".$c['Type'].") "; }
    echo "\n";
    $n=$pdo->query("SELECT COUNT(*) c FROM password_resets")->fetch();
    echo "  kayit sayisi: ".$n['c']."\n";
  }
  echo "users kolonlari (reset/verify/token): ";
  foreach($pdo->query("DESCRIBE users") as $c){
    if(stripos($c['Field'],'reset')!==false||stripos($c['Field'],'verify')!==false||stripos($c['Field'],'token')!==false)
      echo $c['Field']."(".$c['Type'].") ";
  }
  echo "\n";
}catch(Throwable $e){ echo "DB HATA: ".$e->getMessage()."\n"; }

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
