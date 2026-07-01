<?php
/**
 * ChatHelp — Parola sıfırlama KESİN düzeltmesi (iki paralel sistemi uyumlu hale getirir)
 * ---------------------------------------------------------------------------------------
 *  Kök neden (dump-auth/auth2/auth3 ile doğrulandı): auth.php'de İKİ ayrı "parola sıfırlama"
 *  akışı var — biri password_resets tablosunu + doğru '?reset=' linkini kullanıyor
 *  (doForgotPassword), diğeri users.reset_token sütununu + YANLIŞ '?reset_token=' linkini
 *  kullanıyor (doResetRequest -> sendResetEmail). Frontend SADECE '?reset=' okuyor.
 *  Hangisi arayüzden tetikleniyor kesin bilinmese de, bu yama HER İKİSİNİ de çalışır hale
 *  getiriyor: (1) linki düzeltir, (2) doğrulama fonksiyonu her iki tabloyu da kontrol eder.
 *
 * KULLANIM: chat-help.com/chat/apply-reset-fix.php -> opcache-reset.php. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-reset-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . "/auth.php";
if (!file_exists($file)) exit("auth.php yok\n");
$src = file_get_contents($file);
$start = $src;

if (strpos($src, "CH_RESET_LINK_FIX") !== false && strpos($src, "CH_RESET_FALLBACK") !== false) {
  exit("Zaten ekli. Değişiklik yok.\n");
}

$changed = 0;

/* 1) sendResetEmail() linkini düzelt: ?reset_token= -> ?reset= */
$linkAnchor = '$link = "https://chat-help.com/chat/?reset_token=$token";';
if (strpos($src, "CH_RESET_LINK_FIX") === false) {
  if (strpos($src, $linkAnchor) !== false) {
    $newLink = '$link = "https://chat-help.com/chat/?reset=$token"; /*CH_RESET_LINK_FIX*/';
    $src = str_replace($linkAnchor, $newLink, $src);
    $changed++;
    echo "✓ sendResetEmail() linki düzeltildi: ?reset_token= -> ?reset=\n";
  } else {
    echo "✗ sendResetEmail link anchor bulunamadı — bu kısım atlandı.\n";
  }
} else { echo "• Link zaten düzeltilmiş.\n"; }

/* 2) doResetPassword() doğrulamasını genişlet: password_resets bulamazsa users.reset_token'a da bak
      (boş satırlardaki görünmez boşluk farklarına karşı esnek regex kullanılıyor, tam metin eşleşmesi değil) */
$verifyPattern = '/(\$stmt = \$pdo->prepare\(\'SELECT user_id FROM password_resets WHERE token = \? AND expires_at > NOW\(\)\'\);\s*'
  . '\$stmt->execute\(\[\$token\]\);\s*'
  . '\$row = \$stmt->fetch\(PDO::FETCH_ASSOC\);\s*)'
  . '(if\(!\$row\)\{\s*respond\(\[\'error\' => \'Link ungültig oder abgelaufen\'\]\);\s*\})/';
if (strpos($src, "CH_RESET_FALLBACK") === false) {
  if (preg_match($verifyPattern, $src)) {
    $fallbackInsert = "if(!\$row){\n"
      . "        /* CH_RESET_FALLBACK: paralel sistem (users.reset_token) ile de uyumlu olsun */\n"
      . "        \$stmt2 = \$pdo->prepare('SELECT id AS user_id FROM users WHERE reset_token = ? AND reset_expires > NOW()');\n"
      . "        \$stmt2->execute([\$token]);\n"
      . "        \$row = \$stmt2->fetch(PDO::FETCH_ASSOC);\n"
      . "    }\n"
      . "    if(!\$row){ respond(['error' => 'Link ungültig oder abgelaufen']); }";
    /* preg_replace_callback kullanılıyor -> $fallbackInsert İÇİNDEKİ $ işaretleri asla
       backreference olarak yorumlanmaz (str olarak doğrudan eklenir, güvenli). */
    $src = preg_replace_callback($verifyPattern, function($m) use ($fallbackInsert) {
      return $m[1] . $fallbackInsert;
    }, $src, 1);
    $changed++;
    echo "✓ doResetPassword() artık her iki token deposunu da kontrol ediyor (fallback).\n";
  } else {
    echo "✗ doResetPassword doğrulama anchor bulunamadı (regex) — bu kısım atlandı.\n";
  }
} else { echo "• Fallback zaten ekli.\n"; }

/* 3) Başarılı sıfırlamada users.reset_token'ı da temizle (varsa) */
$cleanupAnchor = "\$pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([\$row['user_id']]);\n    \n    respond(['success' => true, 'message' => 'Passwort erfolgreich geändert. Sie können sich jetzt anmelden.']);";
if (strpos($src, $cleanupAnchor) !== false && strpos($src, 'reset_token=NULL') === false) {
  $newCleanup = "\$pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([\$row['user_id']]);\n    \$pdo->prepare('UPDATE users SET reset_token=NULL, reset_expires=NULL WHERE id=?')->execute([\$row['user_id']]); /*CH_RESET_CLEANUP*/\n    \n    respond(['success' => true, 'message' => 'Passwort erfolgreich geändert. Sie können sich jetzt anmelden.']);";
  $src = str_replace($cleanupAnchor, $newCleanup, $src);
  echo "✓ Başarılı sıfırlamada users.reset_token da temizleniyor.\n";
}

if ($changed === 0) {
  echo "\nHiçbir değişiklik uygulanamadı (anchor'lar bulunamadı). auth.php dokunulmadan bırakıldı.\n";
  exit;
}

/* Güvenlik: geçici dosyada php -l denetimi */
$tmp = $file . ".tmp-resetfix";
file_put_contents($tmp, $src);
$out = []; $code = 0;
exec("php -l " . escapeshellarg($tmp) . " 2>&1", $out, $code);
@unlink($tmp);

if ($code !== 0) {
  echo "\n✗ LINT HATASI — auth.php DEĞİŞTİRİLMEDİ (güvenli):\n" . implode("\n", $out) . "\n";
  exit;
}

$bak = $file . ".bak-resetfix-" . date("Ymd-His");
file_put_contents($bak, $start);
file_put_contents($file, $src);

echo "\nLINT: auth.php sözdizimi temiz ✓\n";
echo "Yedek: " . basename($bak) . "\n";
echo "\nSONRA: opcache-reset.php. SİL: rm apply-reset-fix.php\n";
echo "Test: 'Passwort vergessen' ile gerçek bir sıfırlama iste -> gelen e-postadaki linke tıkla -> 'Neues Passwort' formu açılmalı.\n";
