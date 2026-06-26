<?php
/**
 * VESTRA waitlist handler — kayıtları data/signups.csv'ye yazar (+ opsiyonel e-posta).
 * Sade PHP; Hosteurope'ta ekstra kurulum gerekmez.
 */
require_once __DIR__.'/inc/notify.php';

// Dil (geri dönüşte korunur)
$lang = $_POST['lang'] ?? 'en';
if (!in_array($lang, ['en','fr','it','es','de'], true)) $lang = 'en';

// Spam tuzağı (honeypot)
if (!empty($_POST['website'])) { header("Location: /?lang={$lang}&joined=1#join"); exit; }

$type    = (($_POST['type'] ?? '') === 'buyer') ? 'buyer' : 'seller';
$name    = trim($_POST['name'] ?? '');
$company = trim($_POST['company'] ?? '');
$email   = trim($_POST['email'] ?? '');
$country = trim($_POST['country'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: /?lang={$lang}&error=1#join"); exit;
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

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
vestra_notify("New {$type} signup — {$name}",
  "New VESTRA {$type} waitlist signup\n\nName:    {$name}\nCompany: {$company}\nEmail:   {$email}\nCountry: {$country}\nMessage: {$message}\nIP:      {$ip}\nWhen:    ".date('c')."\n",
  $email);
if (vestra_cfg('confirm_user', false)) {
  [$subjAck,$bodyAck] = vestra_ack_text($lang, $name, $type);
  vestra_send_mail($email, $subjAck, $bodyAck);
}

header("Location: /?lang={$lang}&joined=1#join");
exit;
