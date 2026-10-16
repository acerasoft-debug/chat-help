<?php
/**
 * ChatHelp ops — openreg-msg: "e-posta onayı olmadan giriş serbest" kararını kayıt mesajına yansıt
 * ================================================================================================
 * Durum: users.email_verified DEFAULT 1 (open-signup) → yeni kayıtlar anında giriş yapabiliyor; ancak doRegister
 * hâlâ "Bitte bestätigen Sie Ihre E-Mail." diyor ve onay maili "Konto zu aktivieren" yazıyor → kullanıcı bekliyor.
 * UYGULA (apply=1): auth.php içinde
 *   1) doRegister yanıt mesajı → "Registrierung erfolgreich! Sie können sich jetzt anmelden."
 *   2) onay e-postası metni → "Willkommen bei ChatHelp" / bilgilendirme (hesap zaten aktif)
 *   3) e-postadaki support@chat-help.de → support@chat-help.com
 * Yedek auth.php.bak-openreg-<ts>, php -l; hata → geri alınır. ÖNİZLEME (varsayılan) yalnız anchor'ları sayar.
 */
header('Content-Type: text/plain; charset=UTF-8');
$apply = isset($_GET['apply']);
$af = __DIR__.'/auth.php';
echo "ChatHelp — openreg-msg  (".date('c').")   MOD: ".($apply ? 'UYGULA' : 'ÖNİZLEME')."\n\n";
if (!is_file($af)) exit("auth.php yok\n");
$src = (string)file_get_contents($af); $orig = $src;

$R = [
  ['kayıt mesajı',
   "respond(['success' => true, 'message' => 'Registrierung erfolgreich! Bitte bestätigen Sie Ihre E-Mail.']);",
   "respond(['success' => true, 'message' => 'Registrierung erfolgreich! Sie können sich jetzt anmelden.', 'open' => true]); /*CH_OPENREG*/"],
  ['e-posta başlığı',
   "<h2 style=\\\"color:#d4a84a;margin-bottom:20px\\\">ChatHelp — E-Mail bestätigen</h2>",
   "<h2 style=\\\"color:#d4a84a;margin-bottom:20px\\\">Willkommen bei ChatHelp</h2>"],
  ['e-posta metni',
   "<p>Bitte bestätigen Sie Ihre E-Mail-Adresse, um Ihr ChatHelp-Konto zu aktivieren.</p>",
   "<p>Ihr ChatHelp-Konto ist bereits aktiv – Sie können sich jetzt anmelden. Mit einem Klick bestätigen Sie zusätzlich Ihre E-Mail-Adresse (optional).</p>"],
  ['e-posta konu',
   "sendMail(\$email, 'ChatHelp — E-Mail bestätigen', \$html);",
   "sendMail(\$email, 'Willkommen bei ChatHelp', \$html);"],
  ['destek adresi .de→.com',
   "support@chat-help.de",
   "support@chat-help.com"],
];
$total = 0;
foreach ($R as [$label, $old, $new]) {
    $n = substr_count($src, $old); $total += $n;
    echo sprintf("  %-26s anchor: %d\n", $label, $n);
    if ($n && $apply) $src = str_replace($old, $new, $src);
}
if (!$apply) { echo "\nÖNİZLEME: auth.php değişmedi. (apply=1 ile uygulanır)\n"; exit; }
if ($src === $orig) exit("\n  değişiklik yok (anchor'lar bulunamadı ya da zaten uygulanmış)\n");
$bak = $af.'.bak-openreg-'.date('Ymd-His'); copy($af, $bak); echo "\n  yedek: ".basename($bak)."\n";
file_put_contents($af, $src);
$lint = (string)shell_exec('php -l '.escapeshellarg($af).' 2>&1');
if (stripos($lint, 'No syntax errors') === false) { file_put_contents($af, $orig); exit("  ✗ SÖZDİZİMİ HATASI → geri alındı: ".trim($lint)."\n"); }
echo "  ✓ auth.php güncellendi  php -l: ".trim($lint)."\n";
echo "  Geri alma: cp ".basename($bak)." auth.php\n";
