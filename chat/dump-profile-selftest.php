<?php
/**
 * ChatHelp — dump-profile-selftest — ACIL: "kullanici isimleri kaydedilmiyor".
 *  Tahmin YOK — profil kalicilik akisini GERCEK HTTP ile UCTAN UCA test eder
 *  (tarayicinin yaptigi TAM AYNI yol, Authorization header dahil):
 *   [1] Test kullanicisi olustur + DB'de dogrudan email_verified=1 yap (e-posta
 *       adimini atla, sadece profil akisini test ediyoruz)
 *   [2] GERCEK HTTP login -> JWT token al
 *   [3] GERCEK HTTP save_profile -> test ad/soyad verisi gonder (Authorization: Bearer)
 *   [4] GERCEK HTTP get_profile -> verinin GERCEKTEN geri geldigini dogrula
 *   [5] Authorization header'in sunucuda GERCEKTEN goruldugunu kanitla
 *   [6] Test kullanicisini SIL — kalici iz birakmaz
 *  KULLANIM: pull2.php?key=...&files=dump-profile-selftest.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
echo "=== dump-profile-selftest — GERCEK PROFIL AKISI TESTI (test kullanicisi sonda silinir) ===\n\n";

@require_once "$D/config.php";
@require_once "$D/db.php";
if (!function_exists('db')) exit("HATA: db() yuklenemedi — devam edilemiyor.\n");

$host = $_SERVER['HTTP_HOST'] ?? 'chat-help.com';
$base = 'https://'.$host.'/chat/auth.php';
$testEmail = 'chathelp.selftest2+'.time().'@example.com';
$testPass  = 'SelfTest'.rand(1000,9999).'!X';
$uid = null;

function post($url,$body,$token=null){
  $headers=['Content-Type: application/json'];
  if($token) $headers[]='Authorization: Bearer '.$token;
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body),CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>20]);
  $raw=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
  return [$code,$raw,$err];
}
function get($url,$token=null){
  $headers=[]; if($token) $headers[]='Authorization: Bearer '.$token;
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>20]);
  $raw=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
  return [$code,$raw,$err];
}

/* [1] test kullanicisi + dogrudan verified */
echo "[1] Test kullanicisi olusturuluyor + email_verified=1 (DB'den, e-posta adimi atlaniyor)\n";
list($c1,$r1,$e1) = post($base.'?action=register', ['email'=>$testEmail,'password'=>$testPass,'name'=>'Selftest Kisi']);
echo "    register HTTP $c1: ".substr((string)$r1,0,200)."\n";
try {
  $pdo=db();
  $st=$pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1'); $st->execute([$testEmail]); $row=$st->fetch();
  if(!$row){ echo "    ✗ Kullanici DB'de yok — test durduruluyor.\n"; goto cleanup; }
  $uid=$row['id'];
  $pdo->prepare('UPDATE users SET email_verified=1 WHERE id=?')->execute([$uid]);
  echo "    ✓ id=$uid, email_verified=1 yapildi\n\n";
} catch (Throwable $e) { echo "    ✗ DB HATASI: ".$e->getMessage()."\n"; goto cleanup; }

/* [2] GERCEK HTTP login */
echo "[2] GERCEK HTTP login -> JWT\n";
list($c2,$r2,$e2) = post($base.'?action=login', ['email'=>$testEmail,'password'=>$testPass]);
echo "    login HTTP $c2: ".substr((string)$r2,0,300)."\n";
$loginResp = json_decode((string)$r2, true);
$token = $loginResp['token'] ?? null;
if (!$token) { echo "    ✗ TOKEN ALINAMADI — profil testleri atlaniyor.\n\n"; goto cleanup; }
echo "    ✓ token alindi (uzunluk ".strlen($token).")\n\n";

/* [3] GERCEK HTTP save_profile — Authorization header ile */
echo "[3] GERCEK HTTP save_profile (Authorization: Bearer ile) — test ad/soyad gonderiliyor\n";
$testProfiles = [['name'=>'Profil 1','d'=>['f1'=>'AdSelftest','f2'=>'SoyadSelftest','f3'=>'Test Sok. 1','f4'=>'Test Sehir','f5'=>'','f6'=>'','f7'=>$testEmail]]];
list($c3,$r3,$e3) = post($base.'?action=save_profile', ['profiles'=>$testProfiles,'active'=>0], $token);
echo "    save_profile HTTP $c3: ".substr((string)$r3,0,300)."\n";
$saveResp = json_decode((string)$r3, true);
if (isset($saveResp['success']) && $saveResp['success']) echo "    ✓ save_profile BASARILI\n\n";
else echo "    ✗ save_profile BASARISIZ — Authorization header sunucuya ULASMIYOR OLABILIR (yaygin Apache/PHP-FPM sorunu)\n\n";

/* [4] GERCEK HTTP get_profile — verinin gercekten geri geldigini dogrula */
echo "[4] GERCEK HTTP get_profile — az once kaydedilen veri GERI GELIYOR MU?\n";
list($c4,$r4,$e4) = get($base.'?action=get_profile', $token);
echo "    get_profile HTTP $c4: ".substr((string)$r4,0,500)."\n";
$getResp = json_decode((string)$r4, true);
$gotName = $getResp['profiles'][0]['d']['f1'] ?? null;
if ($gotName === 'AdSelftest') echo "    ✓✓✓ UCTAN UCA DOGRULANDI: kaydedilen 'AdSelftest' GERI GELDI. Backend TAM CALISIYOR.\n\n";
elseif ($gotName === null) echo "    ✗ profiles/d/f1 alani BOS/YOK dondu — save_profile veriyi gercekten YAZMAMIS olabilir.\n\n";
else echo "    ✗ FARKLI DEGER dondu ('$gotName') — beklenmeyen durum.\n\n";

/* [5] Authorization header sunucuda gerceten goruluyor mu (dogrudan kanit) */
echo "[5] Authorization header sunucu tarafinda GORULUYOR MU (dogrudan PHP kaniti):\n";
$hdrSeen = isset($_SERVER['HTTP_AUTHORIZATION']) || isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) || (function_exists('apache_request_headers') && isset(apache_request_headers()['Authorization']));
echo "    (Bu betiğin KENDI isteğinde) HTTP_AUTHORIZATION mevcut mu: ".($hdrSeen ? "EVET" : "bu istekte header gonderilmedi (normal, test amacli degil)")."\n";
echo "    Not: [3]/[4] adimlarindaki GERCEK sonuc, Authorization header'in save_profile/get_profile'a ULASIP ULASMADIGININ kesin kanitidir.\n\n";

cleanup:
echo "[6] Temizlik:\n";
if ($uid) {
  try {
    db()->prepare('DELETE FROM user_profiles WHERE user_id=?')->execute([$uid]);
    db()->prepare('DELETE FROM users WHERE id=?')->execute([$uid]);
    echo "    ✓ Test kullanicisi + profili silindi (id=$uid)\n";
  } catch (Throwable $e) { echo "    ⚠ Silinemedi (id=$uid): ".$e->getMessage()."\n"; }
} else echo "    (silinecek kullanici olusmadi)\n";

echo "\n=== BITTI. Ciktinin TAMAMINI gonder. ===\n";
