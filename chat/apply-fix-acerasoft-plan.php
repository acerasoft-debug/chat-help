<?php
/**
 * ChatHelp — apply-fix-acerasoft-plan — ACIL: acerasoft@gmail.com DB'de hala 'basic'
 *  (webhook bug'indan kalma, CH_PLANSAFE gelecegi korudu ama gecmis kaydi duzeltmedi).
 *  Bu TEK SEFERLIK script: acerasoft@gmail.com kullanicisinin EN SON user_plans
 *  satirini 'elite' yapar. Baska HICBIR seye dokunmaz (stripe_customer_id/status aynen).
 * KULLANIM: pull2.php?key=...&files=apply-fix-acerasoft-plan.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fix-acerasoft-plan BASLADI OK (PHP ".PHP_VERSION.")\n\n";
if(!is_file(__DIR__.'/db.php')) exit("HATA: db.php yok.\n");
require_once __DIR__.'/db.php';

try{
  $pdo=db();
  $email='acerasoft@gmail.com';

  $stmt=$pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
  $stmt->execute([$email]);
  $user=$stmt->fetch();
  if(!$user){ echo "✗ Kullanici bulunamadi: $email\n"; exit; }
  $uid=$user['id'];
  echo "  ✓ Kullanici bulundu: id=$uid ($email)\n";

  $stmt=$pdo->prepare('SELECT id, plan, stripe_customer_id FROM user_plans WHERE user_id=? ORDER BY id DESC LIMIT 1');
  $stmt->execute([$uid]);
  $row=$stmt->fetch();
  if(!$row){ echo "  ✗ user_plans satiri yok — INSERT gerekir, elle mudahale et.\n"; exit; }
  echo "  ONCESI: plan='".$row['plan']."'  (satir id=".$row['id']."  stripe_customer_id=".($row['stripe_customer_id']?:'—').")\n";

  if($row['plan']==='elite'){ echo "\n  = Zaten 'elite'. Degisiklik yapilmadi.\n"; exit; }

  $upd=$pdo->prepare('UPDATE user_plans SET plan=? WHERE id=?');
  $upd->execute(['elite',$row['id']]);
  echo "  ✓ GUNCELLENDI: plan -> 'elite' (satir id=".$row['id'].")\n";

  /* dogrula: auth.php'nin okudugu SORGU ile ayni sekilde kontrol */
  $chk=$pdo->prepare('SELECT COALESCE(p.plan,"free") as plan FROM users u LEFT JOIN user_plans p ON p.user_id=u.id WHERE u.id=? ORDER BY p.id DESC LIMIT 1');
  $chk->execute([$uid]);
  $after=$chk->fetch();
  echo "\n  SONRASI (auth.php'nin okuyacagi deger): plan='".$after['plan']."'\n";
  if($after['plan']==='elite') echo "\n✓ BASARILI — acerasoft@gmail.com artik Elite. Uygulamada CIKIS YAP + tekrar GIRIS yap (server-truth taze cekilir).\n";
  else echo "\n✗ BEKLENMEDIK: dogrulama 'elite' donmedi, kontrol et.\n";

}catch(Exception $e){
  echo "✗ HATA: ".$e->getMessage()."\n";
}
