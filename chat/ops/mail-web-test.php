<?php
/**
 * ChatHelp ops — mail-web-test: E-Mail-Versand aus dem WEB-Kontext testen (CLI-Mails kommen an, Web-Mails nicht?)
 * ============================================================================================================
 * Legt eine zufällig benannte, einmalige Web-Datei an, ruft sie per HTTPS auf (PHP-FPM-Kontext), zeigt Benutzer,
 * sendmail-Pfad, mail()-Rückgabe sowie einen rohen sendmail-Aufruf mit Exit-Code – und löscht die Datei wieder.
 * args: {"to":"empfaenger@…"}
 */
header('Content-Type: text/plain; charset=UTF-8');
$to = filter_var((string)($_GET['to'] ?? ''), FILTER_VALIDATE_EMAIL) ?: 'support@chat-help.com';
$name = '_mt_'.bin2hex(random_bytes(6)).'.php'; $path = __DIR__.'/'.$name;
$code = <<<'PHP'
<?php
header('Content-Type: text/plain; charset=UTF-8'); header('Cache-Control: no-store'); header('Set-Cookie: chnc=1; Path=/; Max-Age=60');
$to = filter_var((string)($_GET['to'] ?? ''), FILTER_VALIDATE_EMAIL) ?: 'support@chat-help.com';
echo "sapi=".PHP_SAPI." user=".get_current_user()." uid=".getmyuid()." euid=".(function_exists('posix_geteuid')?posix_geteuid():'?')." host=".gethostname()."\n";
echo "sendmail_path=".ini_get('sendmail_path')." mail.log=".ini_get('mail.log')." mail.force_extra_parameters=".ini_get('mail.force_extra_parameters')."\n";
echo "disable_functions=".ini_get('disable_functions')."\n";
$hdr = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nFrom: ChatHelp <support@chat-help.com>\r\nReply-To: support@chat-help.com";
$ok1 = @mail($to, 'ChatHelp WEB mail() -f '.date('H:i:s'), "Web-Kontext, mit -f\n".date('c'), $hdr, '-fsupport@chat-help.com');
echo "mail() mit -f: ".($ok1 ? 'true' : 'false')." ".json_encode(error_get_last())."\n";
$ok2 = @mail($to, 'ChatHelp WEB mail() ohne -f '.date('H:i:s'), "Web-Kontext, ohne -f\n".date('c'), $hdr);
echo "mail() ohne -f: ".($ok2 ? 'true' : 'false')." ".json_encode(error_get_last())."\n";
if (function_exists('shell_exec')) {
    $raw = "Subject: ChatHelp WEB raw sendmail ".date('H:i:s')."\nFrom: ChatHelp <support@chat-help.com>\nTo: $to\n\nroh via sendmail -t -i, Web-Kontext ".date('c')."\n";
    $tmp = tempnam(sys_get_temp_dir(), 'ml'); file_put_contents($tmp, $raw);
    echo "raw sendmail: ".trim((string)shell_exec('/usr/sbin/sendmail -t -i -f support@chat-help.com < '.escapeshellarg($tmp).' 2>&1; echo "exit=$?"'))."\n";
    echo "ls sendmail: ".trim((string)shell_exec('ls -la /usr/sbin/sendmail /usr/sbin/sendmail.postfix /usr/lib/sendmail 2>&1 | head -5'))."\n";
    echo "mailq: ".trim((string)shell_exec('mailq 2>&1 | head -8'))."\n";
    @unlink($tmp);
} else echo "shell_exec deaktiviert\n";
PHP;
file_put_contents($path, $code); @chmod($path, 0644);
echo "ChatHelp — mail-web-test  (".date('c').")  → $to\n\n";
$url = 'https://chat-help.com/chat/'.$name.'?to='.urlencode($to).'&_='.time();
$ch = curl_init($url); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>40]);
$body = (string)curl_exec($ch); $http = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
echo "=== WEB-Aufruf (HTTP $http) ===\n".($body !== '' ? $body : 'keine Antwort')."\n";
@unlink($path);
echo "\n=== CLI zum Vergleich ===\n";
echo "sapi=".PHP_SAPI." user=".get_current_user()." uid=".getmyuid()."\n";
$okc = @mail($to, 'ChatHelp CLI mail() Vergleich '.date('H:i:s'), "CLI-Kontext ".date('c'), "From: ChatHelp <support@chat-help.com>", '-fsupport@chat-help.com');
echo "mail() CLI mit -f: ".($okc ? 'true' : 'false')."\n";
echo "\nTemp-Datei gelöscht: ".(is_file($path) ? 'NEIN!' : 'ja')."\nBİTTİ\n";
