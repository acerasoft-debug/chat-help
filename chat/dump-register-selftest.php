<?php
/**
 * ChatHelp — dump-register-selftest — ACIL: "kullanicilar kaydolmuyor".
 *  Tahmin YOK — GERCEK bir kayit denemesini uctan uca sunucuda calistirir:
 *   [1] auth.php?action=register'a GERCEK HTTP istegi (tarayicinin yaptigi
 *       AYNI yol) — tek kullanimlik test e-postasiyla.
 *   [2] Donen JSON yaniti gosterir.
 *   [3] users tablosunda satir gercekten olustu mu DOGRUDAN DB'den kontrol eder.
 *   [4] E-POSTA GONDERIMI: sendVerificationEmail/mail() gercekten calisiyor mu
 *       (PHP mail() dönüş degeri + hata) — cogu shared hosting'de SESSIZCE
 *       basarisiz olur, bu EN OLASI kok neden.
 *   [5] TEST KULLANICISINI SONDA SILER — kalici iz birakmaz.
 *  Sifre/DB degeri YAZMAZ, sadece durum. KULLANIM: pull2.php?key=...&files=dump-register-selftest.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
echo "=== dump-register-selftest — GERCEK KAYIT DENEMESI (test kullanicisi sonda silinir) ===\n\n";

$testEmail = 'chathelp.selftest+'.time().'@example.com';
$testPass  = 'SelfTest'.rand(1000,9999).'!X';
$testName  = 'Self Test';

/* [1] GERCEK HTTP istegi — tarayicinin yaptigi ayni yol */
$host = $_SERVER['HTTP_HOST'] ?? 'chat-help.com';
$url  = 'https://'.$host.'/chat/auth.php?action=register';
echo "[1] POST $url\n    email=$testEmail\n\n";

$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER=>true,
  CURLOPT_POST=>true,
  CURLOPT_POSTFIELDS=>json_encode(['email'=>$testEmail,'password'=>$testPass,'name'=>$testName]),
  CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
  CURLOPT_TIMEOUT=>20,
  CURLOPT_SSL_VERIFYPEER=>true,
]);
$raw = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

echo "    HTTP status: $httpCode\n";
if ($curlErr) echo "    CURL HATASI: $curlErr\n";
echo "    Ham yanit: ".substr((string)$raw,0,500)."\n\n";
$resp = json_decode((string)$raw, true);

echo "[2] Yorumlanan yanit:\n";
if ($resp === null) echo "    ✗ JSON parse edilemedi — auth.php muhtemelen HTML hata/uyari basiyor (asagida DB kontrolu ile devam)\n";
else {
  foreach ($resp as $k=>$v) echo "    $k: ".(is_scalar($v)?$v:json_encode($v))."\n";
  if (isset($resp['success']) && $resp['success']) echo "    ✓ Router seviyesinde BASARILI yanit dondu\n";
  elseif (isset($resp['error'])) echo "    ✗ Router HATA dondurdu: ".$resp['error']."\n";
}
echo "\n";

/* [3] DB'de satir gercekten olustu mu */
echo "[3] DB dogrudan kontrol (users tablosu):\n";
@require_once "$D/config.php";
@require_once "$D/db.php";
$uid = null; $dbErr = null;
if (function_exists('db')) {
  try {
    $pdo = db();
    $st = $pdo->prepare('SELECT id, email, email_verified, verify_token, created_at FROM users WHERE email=? LIMIT 1');
    $st->execute([$testEmail]);
    $row = $st->fetch();
    if ($row) {
      $uid = $row['id'];
      echo "    ✓ Satir OLUSTU: id={$row['id']} email_verified=".($row['email_verified']?'1':'0')." verify_token=".($row['verify_token']?'VAR('.strlen($row['verify_token']).' char)':'YOK')." created_at={$row['created_at']}\n";
    } else {
      echo "    ✗ Satir YOK — INSERT hic calismadi veya farkli bir DB'ye yaziyor.\n";
    }
  } catch (Throwable $e) { $dbErr = $e->getMessage(); echo "    ✗ DB HATASI: ".$dbErr."\n"; }
} else { echo "    ✗ db() fonksiyonu yuklenemedi (config.php/db.php sorunlu olabilir)\n"; }
echo "\n";

/* [4] E-posta gonderimi — PHP mail() gercekten calisiyor mu */
echo "[4] E-posta gonderim testi (PHP mail() dogrudan):\n";
$mailOk = @mail($testEmail, 'ChatHelp Selftest', 'Bu bir otomatik testtir.', 'From: no-reply@chat-help.com');
echo "    mail() donus degeri: ".($mailOk ? "true (kabul edildi — teslim garantisi degil)" : "FALSE — sunucu e-postayi REDDETTI")."\n";
$sendmailPath = ini_get('sendmail_path');
echo "    sendmail_path: ".($sendmailPath ?: '(bos/tanimsiz — mail() calismayabilir)')."\n";
if (function_exists('sendVerificationEmail')) {
  echo "    sendVerificationEmail() fonksiyonu MEVCUT — register akisinda cagriliyor olmali.\n";
} else {
  echo "    ✗ sendVerificationEmail() fonksiyonu BULUNAMADI (auth.php icinde tanimli degil mi?)\n";
}
echo "\n";

/* [5] Test kullanicisini SIL — kalici iz birakma */
echo "[5] Temizlik:\n";
if ($uid && function_exists('db')) {
  try { db()->prepare('DELETE FROM users WHERE id=?')->execute([$uid]); echo "    ✓ Test kullanicisi silindi (id=$uid)\n"; }
  catch (Throwable $e) { echo "    ⚠ Test kullanicisi SILINEMEDI (id=$uid) — elle silinmesi gerekebilir: DELETE FROM users WHERE email='".$testEmail."'\n"; }
} else { echo "    (silinecek satir yoktu)\n"; }

echo "\n=== BITTI. Ciktinin TAMAMINI gonder. ===\n";
