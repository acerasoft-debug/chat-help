<?php
/**
 * ChatHelp — apply-fix-dupacc — ACIL: cikis-giris sonrasi hala 'basic' gorunuyor,
 *  DB'de id=1 'elite' oldugu halde. Suphe: 'acerasoft@gmail.com' icin BIRDEN FAZLA
 *  users satiri var (gecmis "yanlis hesap atanma" bug'indan kalma) ve giris FARKLI
 *  bir id'yi kullaniyor. Bu script: (1) TUM eslesen users satirlarini + planlarini
 *  listeler, (2) HEPSININ en son user_plans satirini 'elite' yapar (hangi id
 *  kullanilirsa kullanilsin dogru sonuc alinsin diye), (3) auth.php'nin TAM giris
 *  sorgusuyla NE DONDUGUNU dogrudan gosterir.
 * KULLANIM: pull2.php?key=...&files=apply-fix-dupacc.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fix-dupacc BASLADI OK (PHP ".PHP_VERSION.")\n\n";
if(!is_file(__DIR__.'/db.php')) exit("HATA: db.php yok.\n");
require_once __DIR__.'/db.php';

try{
  $pdo=db();
  $email='acerasoft@gmail.com';

  echo "=== [1] '$email' ile ESLESEN TUM users satirlari ===\n";
  $stmt=$pdo->prepare('SELECT id, email, name, created_at FROM users WHERE email=? ORDER BY id ASC');
  $stmt->execute([$email]);
  $rows=$stmt->fetchAll();
  if(!$rows){ echo "  ✗ HIC kullanici bulunamadi!\n"; exit; }
  echo "  Toplam ".count($rows)." satir bulundu:\n";
  foreach($rows as $r){ echo "    id=".$r['id']."  name=".($r['name']?:'—')."  created=".($r['created_at']?:'—')."\n"; }

  echo "\n=== [2] Her id'nin TUM user_plans satirlari ===\n";
  foreach($rows as $r){
    $uid=$r['id'];
    $st2=$pdo->prepare('SELECT id, plan, status, stripe_customer_id FROM user_plans WHERE user_id=? ORDER BY id ASC');
    $st2->execute([$uid]);
    $plans=$st2->fetchAll();
    echo "  users.id=$uid:\n";
    if(!$plans){ echo "    (user_plans satiri YOK)\n"; }
    foreach($plans as $p){ echo "    plan_row_id=".$p['id']."  plan='".$p['plan']."'  status=".($p['status']?:'—')."  stripe_cust=".($p['stripe_customer_id']?:'—')."\n"; }
  }

  echo "\n=== [3] auth.php'nin TAM GIRIS SORGUSU ile ne donuyor? ===\n";
  $stL=$pdo->prepare('SELECT u.*, p.plan FROM users u LEFT JOIN user_plans p ON u.id = p.user_id WHERE u.email = ? ORDER BY p.id DESC LIMIT 1');
  $stL->execute([$email]);
  $loginRow=$stL->fetch();
  echo "  Giris sorgusu -> id=".($loginRow['id']??'?')."  plan='".($loginRow['plan']??'NULL/free')."'\n";

  echo "\n=== [4] DUZELTME: TUM id'lerin EN SON user_plans satiri 'elite' yapiliyor ===\n";
  $fixed=0;
  foreach($rows as $r){
    $uid=$r['id'];
    $st3=$pdo->prepare('SELECT id, plan FROM user_plans WHERE user_id=? ORDER BY id DESC LIMIT 1');
    $st3->execute([$uid]);
    $latest=$st3->fetch();
    if($latest){
      if($latest['plan']!=='elite'){
        $pdo->prepare('UPDATE user_plans SET plan=? WHERE id=?')->execute(['elite',$latest['id']]);
        echo "  ✓ users.id=$uid: plan_row_id=".$latest['id']." '".$latest['plan']."' -> 'elite'\n";
        $fixed++;
      } else echo "  = users.id=$uid: zaten 'elite'\n";
    } else {
      $ins=$pdo->prepare('INSERT INTO user_plans (user_id,plan) VALUES (?,?)');
      $ins->execute([$uid,'elite']);
      echo "  ✓ users.id=$uid: user_plans satiri YOKTU -> yeni 'elite' satiri eklendi\n";
      $fixed++;
    }
  }

  echo "\n=== [5] Dogrulama: giris sorgusu SIMDI ne donuyor? ===\n";
  $stL2=$pdo->prepare('SELECT u.id, p.plan FROM users u LEFT JOIN user_plans p ON u.id = p.user_id WHERE u.email = ? ORDER BY p.id DESC LIMIT 1');
  $stL2->execute([$email]);
  $after=$stL2->fetch();
  echo "  -> id=".($after['id']??'?')."  plan='".($after['plan']??'NULL/free')."'\n";

  if(($after['plan']??'')==='elite') echo "\n✓ TUM kayitlar 'elite'. Uygulamada CIKIS + GIRIS yap (veya App'i Force Stop et).\n";
  else echo "\n✗ HALA sorun var — plan hala elite degil, daha derin inceleme gerekir.\n";

}catch(Exception $e){
  echo "✗ HATA: ".$e->getMessage()."\n";
}
