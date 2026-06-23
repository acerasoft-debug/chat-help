<?php
/**
 * VESTRA waitlist handler — kayıtları data/signups.csv'ye yazar (+ opsiyonel e-posta).
 * Sade PHP; Hosteurope'ta ekstra kurulum gerekmez.
 */
$CONTACT = 'hello@vestra.example'; // bildirim e-postan
$NOTIFY  = false;                  // true yaparsan her kayıtta sana mail gider (SMTP/mail() aktifse)

// Dil (geri dönüşte korunur)
$lang = $_POST['lang'] ?? 'en';
if (!in_array($lang, ['en','fr','it','es','de'], true)) $lang = 'en';

// Spam tuzağı (honeypot)
if (!empty($_POST['website'])) { header("Location: index.php?lang={$lang}&joined=1#join"); exit; }

$type    = (($_POST['type'] ?? '') === 'buyer') ? 'buyer' : 'seller';
$name    = trim($_POST['name'] ?? '');
$company = trim($_POST['company'] ?? '');
$email   = trim($_POST['email'] ?? '');
$country = trim($_POST['country'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: index.php?lang={$lang}&error=1#join"); exit;
}

$oneLine = fn($s) => trim(preg_replace('/\s+/', ' ', str_replace(["\r","\n"], ' ', $s)));
$row = [date('c'), $type, $oneLine($name), $oneLine($company), $oneLine($email),
        $oneLine($country), $oneLine($message), $_SERVER['REMOTE_ADDR'] ?? ''];

$dir = __DIR__ . '/data';
if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
$file = $dir . '/signups.csv';
$isNew = !file_exists($file);

if ($fh = @fopen($file, 'a')) {
    if ($isNew) {
        fputcsv($fh, ['timestamp','type','name','company','email','country','message','ip']);
    }
    fputcsv($fh, $row);
    fclose($fh);
}

if ($NOTIFY && $CONTACT) {
    $body = "New {$type} waitlist signup\n\n"
          . "Name:    {$name}\nCompany: {$company}\nEmail:   {$email}\n"
          . "Country: {$country}\nMessage: {$message}\n";
    @mail($CONTACT, "VESTRA waitlist — {$type}: {$name}", $body, "From: {$CONTACT}\r\nReply-To: {$email}");
}

header("Location: index.php?lang={$lang}&joined=1#join");
exit;
