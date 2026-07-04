<?php
/**
 * ChatHelp — dump-mkrev: Google Play inceleme test hesabını oluşturur/onarır.
 *   • email_verified=1 doğrulanmış kullanıcı (password_hash ile)
 *   • user_plans: elite / active, uzak vadeli expires_at
 *   • Idempotent: tekrar çalıştırınca şifreyi sabit değere geri getirir.
 * KORUMA: ?key=<token> gerekli. Çalıştırdıktan sonra SİL: rm dump-mkrev.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);            // config redefine uyarılarını gizle
require_once __DIR__ . '/db.php';
if (file_exists(__DIR__ . '/config.php')) require_once __DIR__ . '/config.php';

$TOKEN = 'c2dcbac726e0cec30a3368ea';
if (($_GET['key'] ?? '') !== $TOKEN) { http_response_code(403); exit("forbidden\n"); }

/* ── Sabit inceleme kimlik bilgileri ── */
$EMAIL = 'googleplay.review@chat-help.de';
$PASS  = 'ChatHelpReview2026!';
$NAME  = 'Google Play Review';

try { $pdo = db(); } catch (Exception $e) { exit("DB baglanamadi: ".$e->getMessage()."\n"); }

echo "ChatHelp — inceleme test hesabi kurulumu\n========================================\n\n";

$hash = password_hash($PASS, PASSWORD_DEFAULT);

/* ── 1) users: oluştur ya da güncelle ── */
$st = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$st->execute([$EMAIL]);
$uid = $st->fetchColumn();

if ($uid) {
    $pdo->prepare("UPDATE users SET password_hash=?, name=?, email_verified=1, verify_token=NULL WHERE id=?")
        ->execute([$hash, $NAME, $uid]);
    echo "[users] mevcut kullanici guncellendi (id=$uid) — sifre sabitlendi, email_verified=1\n";
} else {
    $pdo->prepare("INSERT INTO users (email, password_hash, name, email_verified, created_at) VALUES (?,?,?,1,NOW())")
        ->execute([$EMAIL, $hash, $NAME]);
    $uid = (int)$pdo->lastInsertId();
    echo "[users] yeni kullanici olusturuldu (id=$uid), email_verified=1\n";
}

/* ── 2) user_plans: elite / active ── */
$st = $pdo->prepare("SELECT id FROM user_plans WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$st->execute([$uid]);
$pid = $st->fetchColumn();

if ($pid) {
    $pdo->prepare("UPDATE user_plans SET plan='elite', status='active', starts_at=CURDATE(), expires_at='2035-12-31' WHERE id=?")
        ->execute([$pid]);
    echo "[user_plans] mevcut plan Elite/active yapildi (plan_id=$pid), expires_at=2035-12-31\n";
} else {
    $pdo->prepare("INSERT INTO user_plans (user_id, plan, status, starts_at, expires_at) VALUES (?, 'elite','active', CURDATE(), '2035-12-31')")
        ->execute([$uid]);
    echo "[user_plans] yeni Elite plan eklendi (user_id=$uid), expires_at=2035-12-31\n";
}

/* ── 3) Doğrulama: password_verify + plan okuması ── */
$row = $pdo->query("SELECT u.id,u.email,u.email_verified,u.password_hash,p.plan,p.status,p.expires_at
  FROM users u LEFT JOIN user_plans p ON p.user_id=u.id
  WHERE u.email=".$pdo->quote($EMAIL)." ORDER BY p.id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$ok = $row && password_verify($PASS, $row['password_hash']);
echo "\n[dogrulama] password_verify: ".($ok ? "GECTI ✓" : "BASARISIZ ✗")."\n";
echo "[dogrulama] email_verified={$row['email_verified']} plan={$row['plan']} status={$row['status']} expires={$row['expires_at']}\n";

echo "\n=================== PLAY CONSOLE İÇİN ===================\n";
echo "  Kullanıcı adı / e-posta : $EMAIL\n";
echo "  Şifre                   : $PASS\n";
echo "========================================================\n";
echo "\nBu hesap Elite'tir; tüm premium özellikler açık.\n";
echo "GÜVENLİK: Bu dosyayi hemen SİL -> rm dump-mkrev.php\n";
