<?php
/**
 * ChatHelp ops — signup-login: kayıt/giriş akışının e-posta onayı kapısını incele (ÖNİZLEME, yazmaz)
 * ==================================================================================================
 * Amaç: "şimdilik e-posta onayı olmadan giriş serbest" kararını kodda doğrulamak.
 *   DB tarafı (open-signup) yapıldı: users.email_verified DEFAULT 1, mevcutlar 1. Trigger SUPER yetkisi yüzünden kurulamadı →
 *   doRegister INSERT'i email_verified=0 yazıyorsa yeni kullanıcılar yine onaysız kalır. Bu betik ilgili kodu tam gösterir.
 * KULLANIM (CLI): php -r '$_GET=[]; include "signup-login.php";'
 */
header('Content-Type: text/plain; charset=UTF-8');
require_once __DIR__ . '/db.php';
echo "ChatHelp — signup-login inceleme  (".date('c').")\n\n";

$af = __DIR__.'/auth.php';
if (!is_file($af)) exit("auth.php yok\n");
$src = (string)file_get_contents($af);
echo "auth.php: ".strlen($src)." B  ".date('Y-m-d H:i', filemtime($af))."\n";

function fnbody(string $s, string $name): ?string {
    if (!preg_match('/function\s+'.preg_quote($name,'/').'\s*\([^{]*\{/', $s, $m, PREG_OFFSET_CAPTURE)) return null;
    $s0 = $m[0][1]; $i = strpos($s, '{', $s0); $d = 0; $j = $i;
    do { if ($s[$j] === '{') $d++; elseif ($s[$j] === '}') $d--; $j++; } while ($d > 0 && $j < strlen($s));
    return substr($s, $s0, $j - $s0);
}
foreach (['doRegister','doLogin','doVerify','doResendVerification','sendVerificationEmail','doMe','doGoogleLogin'] as $fn) {
    echo "\n──── $fn ────\n";
    $b = fnbody($src, $fn);
    echo $b === null ? "  (bulunamadı)\n" : $b."\n";
}

echo "\n──── 'email_verified' geçişleri (auth.php) ────\n";
$o = 0; while (($p = strpos($src, 'email_verified', $o)) !== false) { echo "  @$p: ".str_replace("\n", ' ', substr($src, max(0, $p-90), 200))."\n"; $o = $p + 1; }

echo "\n──── index.php'de onay akışı izleri ────\n";
$ix = @file_get_contents(__DIR__.'/index.php');
if ($ix !== false) {
    foreach (['unverified','email_verified','resend_verification','verify','bestätigen','Bestätigung'] as $kw) {
        $c = substr_count($ix, $kw); echo "  '$kw': $c\n";
        if ($c && $c <= 6) { $o = 0; while (($p = strpos($ix, $kw, $o)) !== false) { echo "      @$p: ".str_replace("\n", ' ', substr($ix, max(0, $p-80), 170))."\n"; $o = $p + 1; } }
    }
}

echo "\n──── DB durumu ────\n";
try {
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $r = $pdo->query("SELECT email_verified, COUNT(*) c FROM users GROUP BY email_verified")->fetchAll(PDO::FETCH_KEY_PAIR);
    echo "  users.email_verified dağılımı: ".json_encode($r)."\n";
    $c = $pdo->query("SHOW COLUMNS FROM users LIKE 'email_verified'")->fetch(PDO::FETCH_ASSOC);
    echo "  kolon: ".json_encode($c)."\n";
    $t = $pdo->query("SHOW TRIGGERS LIKE 'users'")->fetchAll(PDO::FETCH_ASSOC);
    echo "  trigger: ".($t ? json_encode(array_column($t, 'Trigger')) : 'yok')."\n";
    $last = $pdo->query("SELECT id, email_verified, created_at FROM users ORDER BY id DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    echo "  son kayıtlar (id, onay, tarih): ".json_encode($last)."\n";
} catch (Throwable $e) { echo "  DB hata: ".$e->getMessage()."\n"; }
echo "\nBİTTİ (yazılmadı)\n";
