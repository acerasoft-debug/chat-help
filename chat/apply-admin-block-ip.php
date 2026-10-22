<?php
/**
 * ChatHelp — apply-admin-block-ip (CH_ADMIN_BLOCK) — Kullanici SILME/BLOKE +
 *  IP BLOKE altyapisi, ADIM 1 (backend). Mevcut doRegister/doLogin'e DOKUNMADAN
 *  degil — icine ENGELLEME KONTROLU ve IP kaydi eklenir (count-guard'li,
 *  onceki turlarda dogrulanmis EXACT metne dayanir).
 *  [0] Sema: users.blocked/reg_ip/last_ip sutunlari (yoksa) + blocked_ips tablosu
 *  [1] ch_ip() yardimci fonksiyonu (varsa CH_FREEDOC'unkini kullanir, yoksa tanimlar)
 *  [2] doRegister: IP engelliyse kayit REDDEDILIR + basarili kayitta reg_ip/last_ip kaydedilir
 *  [3] doLogin: IP engelliyse VEYA hesap bloke ise giris REDDEDILIR + basarili
 *      giriste last_ip guncellenir
 * KULLANIM: pull2.php?key=...&files=apply-admin-block-ip.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-admin-block-ip BASLADI OK (PHP ".PHP_VERSION.")\n\n";

@require_once __DIR__.'/config.php';
@require_once __DIR__.'/db.php';
if (!function_exists('db')) exit("HATA: db() yuklenemedi\n");

/* ── [0] sema ── */
try {
    $pdo = db();
    $cols = array_map(function($c){ return $c['Field']; }, $pdo->query("SHOW COLUMNS FROM users")->fetchAll());
    if (!in_array('blocked',$cols)) { $pdo->exec("ALTER TABLE users ADD COLUMN blocked TINYINT(1) NOT NULL DEFAULT 0"); echo "  ✓ [0a] users.blocked eklendi\n"; }
    else echo "  ✓ [0a] users.blocked zaten var\n";
    if (!in_array('reg_ip',$cols)) { $pdo->exec("ALTER TABLE users ADD COLUMN reg_ip VARCHAR(45) NULL"); echo "  ✓ [0b] users.reg_ip eklendi\n"; }
    else echo "  ✓ [0b] users.reg_ip zaten var\n";
    if (!in_array('last_ip',$cols)) { $pdo->exec("ALTER TABLE users ADD COLUMN last_ip VARCHAR(45) NULL"); echo "  ✓ [0c] users.last_ip eklendi\n"; }
    else echo "  ✓ [0c] users.last_ip zaten var\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS blocked_ips (
        ip VARCHAR(45) NOT NULL PRIMARY KEY,
        reason VARCHAR(255) NULL,
        blocked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "  ✓ [0d] blocked_ips tablosu hazir\n";
} catch (Throwable $e) { exit("HATA [0]: ".$e->getMessage()."\n"); }

$authFile = __DIR__.'/auth.php';
$src = @file_get_contents($authFile);
if ($src===false) exit("HATA: auth.php okunamadi\n");
if (strpos($src,'CH_ADMIN_BLOCK')!==false) exit("Zaten ekli (CH_ADMIN_BLOCK).\n");

$fail=[];

/* ── [1] ch_ip() — varsa kullan, yoksa tanimla ── */
$ipFn = strpos($src,'function ch_ip(')!==false ? '' : <<<'PHPFN'

/* CH_ADMIN_BLOCK — istemci IP'si */
function ch_ip(): string {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');
    $ip = trim(explode(',', (string)$ip)[0]);
    if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    $r = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var($r, FILTER_VALIDATE_IP) ? $r : '0.0.0.0';
}
function ch_ip_blocked(string $ip): bool {
    try { $st = db()->prepare('SELECT 1 FROM blocked_ips WHERE ip=? LIMIT 1'); $st->execute([$ip]); return (bool)$st->fetch(); }
    catch (Throwable $e) { return false; }
}
PHPFN;
if ($ipFn==='') {
  /* ch_ip() zaten var ama ch_ip_blocked() olmayabilir */
  if (strpos($src,'function ch_ip_blocked(')===false) {
    $ipFn = <<<'PHPFN'

function ch_ip_blocked(string $ip): bool {
    try { $st = db()->prepare('SELECT 1 FROM blocked_ips WHERE ip=? LIMIT 1'); $st->execute([$ip]); return (bool)$st->fetch(); }
    catch (Throwable $e) { return false; }
}
PHPFN;
  }
}

/* ── [2] doRegister: IP engeli + reg_ip/last_ip ── */
$o2 = <<<'ANC'
function doRegister(array $b): void {
    $email = strtolower(trim($b['email'] ?? ''));
    $pass  = $b['password'] ?? '';
    $name  = trim($b['name'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) respond(['error' => 'Ungültige E-Mail-Adresse'], 400);
    if (strlen($pass) < 8) respond(['error' => 'Passwort muss mindestens 8 Zeichen haben'], 400);

    $pdo = db();
    $existing = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $existing->execute([$email]);
    if ($existing->fetch()) respond(['error' => 'Diese E-Mail-Adresse ist bereits registriert'], 409);

    $token = bin2hex(random_bytes(32));
    $hash  = password_hash($pass, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, name, verify_token) VALUES (?, ?, ?, ?)');
    $stmt->execute([$email, $hash, $name, $token]);
    $userId = $pdo->lastInsertId();
ANC;
$n2 = <<<'ANC'
function doRegister(array $b): void {
    $email = strtolower(trim($b['email'] ?? ''));
    $pass  = $b['password'] ?? '';
    $name  = trim($b['name'] ?? '');

    /*CH_ADMIN_BLOCK*/ $__ip = ch_ip();
    if (ch_ip_blocked($__ip)) respond(['error' => 'Registrierung von diesem Standort aus nicht möglich.'], 403);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) respond(['error' => 'Ungültige E-Mail-Adresse'], 400);
    if (strlen($pass) < 8) respond(['error' => 'Passwort muss mindestens 8 Zeichen haben'], 400);

    $pdo = db();
    $existing = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $existing->execute([$email]);
    if ($existing->fetch()) respond(['error' => 'Diese E-Mail-Adresse ist bereits registriert'], 409);

    $token = bin2hex(random_bytes(32));
    $hash  = password_hash($pass, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, name, verify_token, reg_ip, last_ip) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$email, $hash, $name, $token, $__ip, $__ip]);
    $userId = $pdo->lastInsertId();
ANC;
$c=substr_count($src,$o2);
if($c!==1){ echo "  ✗ [2] doRegister anchor ($c)\n"; $fail[]='2'; }
else { $src=str_replace($o2,$n2,$src); echo "  ✓ [2] doRegister: IP engeli kontrolu + reg_ip/last_ip kaydi\n"; }

/* ── [3] doLogin: IP engeli + hesap bloke + last_ip guncelle ── */
$o3 = <<<'ANC'
function doLogin(array $b): void {
    $email = strtolower(trim($b['email'] ?? ''));
    $pass  = $b['password'] ?? '';

    $pdo  = db();
    $stmt = $pdo->prepare('SELECT u.*, p.plan FROM users u LEFT JOIN user_plans p ON u.id = p.user_id WHERE u.email = ? ORDER BY p.id DESC LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($pass, $user['password_hash'])) {
        respond(['error' => 'Falsche E-Mail-Adresse oder Passwort'], 401);
    }
    if (!$user['email_verified']) {
        respond(['error' => 'Bitte bestätigen Sie zuerst Ihre E-Mail-Adresse'], 403);
    }

    $token   = generateJWT($user['id'], $user['email'], $user['plan'] ?? 'free', $user['name']);
ANC;
$n3 = <<<'ANC'
function doLogin(array $b): void {
    $email = strtolower(trim($b['email'] ?? ''));
    $pass  = $b['password'] ?? '';

    /*CH_ADMIN_BLOCK*/ $__ip = ch_ip();
    if (ch_ip_blocked($__ip)) respond(['error' => 'Anmeldung von diesem Standort aus nicht möglich.'], 403);

    $pdo  = db();
    $stmt = $pdo->prepare('SELECT u.*, p.plan FROM users u LEFT JOIN user_plans p ON u.id = p.user_id WHERE u.email = ? ORDER BY p.id DESC LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($pass, $user['password_hash'])) {
        respond(['error' => 'Falsche E-Mail-Adresse oder Passwort'], 401);
    }
    if (!empty($user['blocked'])) {
        respond(['error' => 'Ihr Konto wurde gesperrt. Bitte kontaktieren Sie den Support.'], 403);
    }
    if (!$user['email_verified']) {
        respond(['error' => 'Bitte bestätigen Sie zuerst Ihre E-Mail-Adresse'], 403);
    }

    try { $pdo->prepare('UPDATE users SET last_ip=? WHERE id=?')->execute([$__ip, $user['id']]); } catch (Throwable $e) {}

    $token   = generateJWT($user['id'], $user['email'], $user['plan'] ?? 'free', $user['name']);
ANC;
$c=substr_count($src,$o3);
if($c!==1){ echo "  ✗ [3] doLogin anchor ($c)\n"; $fail[]='3'; }
else { $src=str_replace($o3,$n3,$src); echo "  ✓ [3] doLogin: IP engeli + hesap-bloke kontrolu + last_ip guncelleme\n"; }

if ($fail) { echo "\nHATA: eslesmeyen adimlar: ".implode(', ',$fail)." — auth.php DEGISTIRILMEDI.\n"; exit; }

/* ── helper fonksiyonlarini dosya sonuna ekle ── */
if (trim($ipFn)!=='') {
  $closeTag = strrpos($src, '?>');
  if ($closeTag !== false && trim(substr($src,$closeTag+2))==='') {
      $src = substr($src,0,$closeTag).$ipFn."\n?>".substr($src,$closeTag+2);
  } else {
      $src = rtrim($src)."\n".$ipFn."\n";
  }
  echo "  ✓ [1] ch_ip()/ch_ip_blocked() yardimci fonksiyonlari eklendi\n";
}
$src .= "\n/* CH_ADMIN_BLOCK applied */\n";

/* lint + yaz */
$tmp = tempnam(sys_get_temp_dir(),'ab').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — auth.php DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($authFile.'.bak-adminblock-'.date('Ymd-His'), (string)@file_get_contents($authFile));
$w=@file_put_contents($authFile,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($authFile);
if (strpos($chk,'CH_ADMIN_BLOCK')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_ADMIN_BLOCK diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Backend hazir: hesap bloke + IP bloke artik giris/kayitta uygulaniyor.\n";
echo "   Sonraki: apply-admin-users2.php (silme/bloke/IP-bloke arayuzu + admin.php capraz baglanti)\n";
