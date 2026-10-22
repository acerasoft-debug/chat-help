<?php
/**
 * ChatHelp — apply-konto-backend (CH_KONTO_BE) — Konto kesin cozum, ADIM 1 (backend).
 *  (a) user_profiles tablosu: kullanici profillerini (ad/adres/tel/... = ch_profiles)
 *      DB'de kalici saklar -> cihazlar arasi senkron.
 *  (b) save_profile / get_profile endpointleri (JWT).
 *  (c) resend_verification: dogrulama e-postasini tekrar gonderir (hesap sizdirmaz).
 *  Tamamen EKLEMELI: mevcut fonksiyonlar DEGISMEZ. Router'a 3 case eklenir
 *  (whitespace-toleransli preg_replace). php -l lint + .bak yedek + dogrulama.
 * KULLANIM: pull2.php?key=...&files=apply-konto-backend.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-konto-backend BASLADI OK (PHP ".PHP_VERSION.")\n\n";

/* ── [0] user_profiles tablosu ── */
@require_once __DIR__.'/config.php';
@require_once __DIR__.'/db.php';
if (!function_exists('db')) exit("HATA: db() yuklenemedi\n");
try {
    db()->exec("CREATE TABLE IF NOT EXISTS user_profiles (
        user_id INT NOT NULL PRIMARY KEY,
        profiles LONGTEXT NULL,
        active INT NOT NULL DEFAULT 0,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "  ✓ [0] user_profiles tablosu hazir\n";
} catch (Throwable $e) { exit("HATA [0]: ".$e->getMessage()."\n"); }

$authFile = __DIR__.'/auth.php';
$src = @file_get_contents($authFile);
if ($src===false) exit("HATA: auth.php okunamadi\n");
if (strpos($src,'CH_KONTO_BE')!==false) exit("Zaten ekli (CH_KONTO_BE).\n");

/* ── [1] router'a 3 case (whitespace-toleransli, me'den sonra) ── */
$cases = "'me' => doMe(),\n    'save_profile'   => doSaveProfile(\$body),\n    'get_profile'    => doGetProfile(),\n    'resend_verification' => doResendVerification(\$body),";
$cnt=0;
$src = preg_replace('/\'me\'\s*=>\s*doMe\(\)\s*,/', $cases, $src, 1, $cnt);
if ($cnt!==1) exit("HATA: [1] router 'me => doMe()' anchor bulunamadi ($cnt) — iptal.\n");
echo "  ✓ [1] router'a save_profile + get_profile + resend_verification eklendi\n";

/* ── [2] fonksiyonlar (dosya sonuna) ── */
$fn = <<<'PHPFN'

/* CH_KONTO_BE — profil kalici saklama + dogrulama tekrar gonder (EKLEMELI) */
function doSaveProfile(array $b): void {
    $u = requireAuth();
    $profiles = $b['profiles'] ?? null;
    $active   = (int)($b['active'] ?? 0);
    if (!is_array($profiles)) respond(['error'=>'bad_profiles','message'=>'Profil verisi gecersiz'], 400);
    $json = json_encode($profiles, JSON_UNESCAPED_UNICODE);
    if ($json === false) respond(['error'=>'encode'], 400);
    if (strlen($json) > 300000) respond(['error'=>'too_large','message'=>'Profil verisi cok buyuk'], 413);
    try {
        $pdo = db();
        $pdo->prepare('INSERT INTO user_profiles (user_id, profiles, active) VALUES (?,?,?)
                       ON DUPLICATE KEY UPDATE profiles=VALUES(profiles), active=VALUES(active)')
            ->execute([$u['id'], $json, $active]);
        respond(['success'=>true]);
    } catch (Throwable $e) { respond(['error'=>'db','message'=>'Speichern fehlgeschlagen'], 500); }
}
function doGetProfile(): void {
    $u = requireAuth();
    try {
        $pdo = db();
        $st = $pdo->prepare('SELECT profiles, active FROM user_profiles WHERE user_id=? LIMIT 1');
        $st->execute([$u['id']]);
        $row = $st->fetch();
        if (!$row) respond(['success'=>true,'profiles'=>null,'active'=>0]);
        $profiles = json_decode((string)($row['profiles'] ?? 'null'), true);
        respond(['success'=>true,'profiles'=>$profiles,'active'=>(int)($row['active'] ?? 0)]);
    } catch (Throwable $e) { respond(['success'=>true,'profiles'=>null,'active'=>0,'warn'=>'db']); }
}
function doResendVerification(array $b): void {
    $email = strtolower(trim($b['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) respond(['error'=>'Ungültige E-Mail-Adresse'], 400);
    try {
        $pdo = db();
        $st = $pdo->prepare('SELECT id, name, email_verified FROM users WHERE email=? LIMIT 1');
        $st->execute([$email]);
        $u = $st->fetch();
        if ($u && !$u['email_verified']) {
            $token = bin2hex(random_bytes(32));
            $pdo->prepare('UPDATE users SET verify_token=? WHERE id=?')->execute([$token, $u['id']]);
            if (function_exists('sendVerificationEmail')) {
                try { sendVerificationEmail($email, (string)($u['name'] ?? ''), $token); } catch (Throwable $e) {}
            }
        }
    } catch (Throwable $e) { /* hesap sizdirma yok */ }
    respond(['success'=>true, 'message'=>'Falls ein noch nicht bestätigtes Konto existiert, wurde eine neue Bestätigungs-E-Mail gesendet.']);
}
PHPFN;

$closeTag = strrpos($src, '?>');
if ($closeTag !== false && trim(substr($src,$closeTag+2))==='') {
    $src = substr($src,0,$closeTag).$fn."\n?>".substr($src,$closeTag+2);
} else {
    $src = rtrim($src)."\n".$fn."\n";
}
$src .= "\n/* CH_KONTO_BE applied */\n";
echo "  ✓ [2] doSaveProfile/doGetProfile/doResendVerification eklendi\n";

/* lint + yaz */
$tmp = tempnam(sys_get_temp_dir(),'kb').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — auth.php DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($authFile.'.bak-kontobe-'.date('Ymd-His'), (string)@file_get_contents($authFile));
$w=@file_put_contents($authFile,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($authFile);
if (strpos($chk,'CH_KONTO_BE')===false || strpos($chk,'function doSaveProfile')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_KONTO_BE + doSaveProfile diskte (".strlen($chk)." bayt)\n";
echo "\n✓ ADIM 1 tamam. Endpointler: auth.php?action=save_profile | get_profile | resend_verification (JWT).\n";
echo "   Sonraki: abonelik (stripe) + frontend senkron/dogrulama-tekrar-gonder.\n";
