<?php
/**
 * ChatHelp — dump-plan-values (SADECE OKUR) — "Abo verwalten" butonu gorunmeme
 *  kok nedeni: frontend planOf() SADECE tam-esit 'basic'/'pro'/'elite' kabul
 *  ediyor. user_plans tablosundaki GERCEK deger(ler) bunlardan FARKLI olabilir
 *  (buyuk harf, farkli etiket, bosluk). Sadece DEGER CESITLERINI + kac kullanicida
 *  gectigini gosterir — HICBIR KISISEL VERI (email/isim) YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-plan-values.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
@require_once "$D/config.php";
@require_once "$D/db.php";
echo "=== dump-plan-values — SADECE OKUR (kisisel veri YOK) ===\n\n";
if (!function_exists('db')) exit("db() yuklenemedi\n");

try {
  $pdo = db();

  echo "[1] user_plans tablosundaki BENZERSIZ 'plan' degerleri + adet:\n";
  $st = $pdo->query("SELECT plan, COUNT(*) c FROM user_plans GROUP BY plan ORDER BY c DESC");
  $rows = $st->fetchAll();
  if (!$rows) echo "  (tablo bos)\n";
  foreach ($rows as $r) echo "  '".$r['plan']."'  (uzunluk ".strlen($r['plan']).")  -> ".$r['c']." satir\n";

  echo "\n[2] users.stripe_customer_id DOLU olan kullanici sayisi (toplam / bos-olmayan):\n";
  $st2 = $pdo->query("SELECT COUNT(*) t, SUM(CASE WHEN stripe_customer_id IS NOT NULL AND stripe_customer_id<>'' THEN 1 ELSE 0 END) f FROM users");
  $r2 = $st2->fetch();
  echo "  toplam kullanici: ".$r2['t'].", stripe_customer_id dolu: ".$r2['f']."\n";

  echo "\n[3] user_plans satir sayisi vs benzersiz user_id sayisi (birden fazla plan satiri olan var mi):\n";
  $st3 = $pdo->query("SELECT COUNT(*) rows_, COUNT(DISTINCT user_id) users_ FROM user_plans");
  $r3 = $st3->fetch();
  echo "  toplam satir: ".$r3['rows_'].", benzersiz user_id: ".$r3['users_']."\n";
  if ($r3['rows_'] > $r3['users_']) echo "  ⚠ Bazi kullanicilarin BIRDEN FAZLA plan satiri var (doMe 'ORDER BY id DESC LIMIT 1' kullaniyor -> EN SON eklenen satir gecerli olur, eskisi degil).\n";

  echo "\n[4] doMe()'nin okudugu sorguyla AYNI mantik — orn. bir kac satir (deger+created varsa) ilk 5:\n";
  $st4 = $pdo->query("SELECT user_id, plan FROM user_plans ORDER BY id DESC LIMIT 5");
  foreach ($st4->fetchAll() as $r) echo "  user_id=".$r['user_id']." plan='".$r['plan']."'\n";

} catch (Throwable $e) {
  echo "DB HATASI: ".$e->getMessage()."\n";
}

echo "\n=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
