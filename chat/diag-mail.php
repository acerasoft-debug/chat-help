<?php
/**
 * ChatHelp — KAYIT / E-POSTA TEŞHİS (salt-okunur, güvenli)
 * =======================================================
 * Hiçbir dosyayı DEĞİŞTİRMEZ. Sadece okur + canlı SMTP testi yapar.
 * Kayıt onay maili neden gitmiyor / müşteri neden üye olamıyor — hepsini gösterir.
 *
 * KULLANIM:  html/chat/ içine yükle -> tarayıcıda aç:
 *            https://chat-help.com/chat/diag-mail.php?to=SENIN@MAIL.com
 *   (to verilmezse SMTP_FROM adresine test atar)
 * Çıktının TAMAMINI bana yapıştır. Sonra bu dosyayı SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
@set_time_limit(90);
echo "ChatHelp — Kayıt/E-posta Teşhis  (" . date('c') . ")\n";
echo "======================================================\n\n";

/* ---- db.php + SMTP sabitleri ---- */
$db = __DIR__ . '/db.php';
echo "[db.php] " . (file_exists($db) ? "var" : "YOK") . "\n";
if (file_exists($db)) { require_once $db; }

echo "\n=== 1) SMTP yapılandırması ===\n";
foreach (['SMTP_HOST','SMTP_PORT','SMTP_USER','SMTP_FROM','SMTP_NAME'] as $c) {
    echo str_pad($c, 11) . " = " . (defined($c) ? constant($c) : '(TANIMSIZ)') . "\n";
}
echo "SMTP_PASS   = " . (defined('SMTP_PASS') ? ('tanımlı, ' . strlen(SMTP_PASS) . ' karakter') : 'TANIMSIZ') . "\n";
$from = defined('SMTP_FROM') ? SMTP_FROM : 'noreply@chat-help.com';
$name = defined('SMTP_NAME') ? SMTP_NAME : 'ChatHelp';
$to   = $_GET['to'] ?? $from;
echo "Test hedefi = $to\n";

/* ---- port erişimi ---- */
echo "\n=== 2) Giden port erişimi (fsockopen) ===\n";
$probes = [['relay-hosting.secureserver.net',25]];
if (defined('SMTP_HOST')) { $probes[] = [SMTP_HOST, defined('SMTP_PORT')?SMTP_PORT:587]; $probes[] = [SMTP_HOST,465]; $probes[] = [SMTP_HOST,25]; }
foreach ($probes as $hp) {
    $t=microtime(true); $fp=@fsockopen($hp[0],$hp[1],$e,$s,8);
    echo sprintf("  %-38s :%-4d  %s  (%.1fs)\n",$hp[0],$hp[1], $fp?'AÇIK ✓':"KAPALI ✗  [$s]", microtime(true)-$t);
    if($fp) fclose($fp);
}

/* ---- verbose SMTP gönderici ---- */
function smtp_try($host,$port,$tls,$auth,$user,$pass,$from,$name,$to,&$log){
    $log=''; $ok=false;
    $fp=@fsockopen($host,$port,$e,$s,15);
    if(!$fp){ $log.="  BAĞLANTI YOK: $s ($e)\n"; return false; }
    stream_set_timeout($fp,15);
    $R=function()use($fp,&$log){ $d=''; while($l=fgets($fp,515)){ $d.=$l; if(isset($l[3])&&$l[3]===' ')break; } $log.="  S: ".trim($d)."\n"; return $d; };
    $C=function($c,$hide=false)use($fp,$R,&$log){ $log.="  C: ".($hide?'***':$c)."\n"; fwrite($fp,$c."\r\n"); return $R(); };
    $R();
    $C("EHLO chat-help.com");
    if($tls){
        $C("STARTTLS");
        if(!@stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)){ $log.="  !! TLS BAŞARISIZ\n"; fclose($fp); return false; }
        $C("EHLO chat-help.com");
    }
    if($auth){
        $C("AUTH LOGIN");
        $C(base64_encode($user),true);
        $r=$C(base64_encode($pass),true);
        if(strpos($r,'235')===false){ $log.="  !! AUTH BAŞARISIZ (kullanıcı/şifre yanlış olabilir)\n"; }
    }
    $r=$C("MAIL FROM:<$from>");
    $r=$C("RCPT TO:<$to>");
    if(strpos($r,'250')===false && strpos($r,'251')===false){ $log.="  !! RCPT reddedildi\n"; }
    $C("DATA");
    $msg ="From: $name <$from>\r\nTo: <$to>\r\n";
    $msg.="Subject: =?UTF-8?B?".base64_encode('ChatHelp Teşhis Testi ✅')."?=\r\n";
    $msg.="MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $msg.=chunk_split(base64_encode('<h2>ChatHelp e-posta testi ✅</h2><p>Bu teşhis maili geldiyse bu yol çalışıyor.</p>'));
    fwrite($fp,$msg."\r\n.\r\n");
    $r=$R();
    if(strpos($r,'250')!==false){ $ok=true; }
    $C("QUIT"); fclose($fp);
    return $ok;
}

$U=defined('SMTP_USER')?SMTP_USER:''; $P=defined('SMTP_PASS')?SMTP_PASS:'';
echo "\n=== 3) TEST A — GoDaddy relay :25 (auth/TLS yok) ===\n";
$logA=''; $okA=smtp_try('relay-hosting.secureserver.net',25,false,false,'','',$from,$name,$to,$logA); echo $logA."  >> ".($okA?"KUYRUĞA GİRDİ ✓":"BAŞARISIZ ✗")."\n";

echo "\n=== 4) TEST B — HostEurope ".($host=defined('SMTP_HOST')?SMTP_HOST:'smtp.hosteurope.de')." :587 STARTTLS+AUTH ===\n";
$logB=''; $okB=smtp_try($host,587,true,true,$U,$P,$from,$name,$to,$logB); echo $logB."  >> ".($okB?"KUYRUĞA GİRDİ ✓":"BAŞARISIZ ✗")."\n";

echo "\n=== 5) PHP mail() ===\n";
echo "  mail() fonksiyonu: ".(function_exists('mail')?'var':'YOK')."\n";
echo "  sendmail_path: ".(ini_get('sendmail_path')?:'(boş)')."\n";

/* ---- auth.php: sendMail + kayıt/verify akışı (SALT OKUNUR) ---- */
echo "\n=== 6) auth.php içeriği ===\n";
$af=__DIR__.'/auth.php';
if(!file_exists($af)){ echo "  auth.php YOK\n"; }
else {
    $a=file_get_contents($af);
    echo "  boyut: ".strlen($a)." bayt\n";
    // sendMail / sendEmail tanımlı mı?
    echo "  'function sendMail' geçişi: ".substr_count($a,'function sendMail')."\n";
    echo "  'sendEmail(' çağrısı (TANIMSIZ olabilir): ".substr_count($a,'sendEmail(')."\n";
    echo "  'function sendEmail' tanımı: ".substr_count($a,'function sendEmail')."\n";
    echo "  '.de/chat/' hatalı link: ".substr_count($a,'chat-help.de/chat/')."\n";
    // sendMail gövdesi
    if(preg_match('/function\s+sendMail\s*\([^{]*\{/',$a,$m,PREG_OFFSET_CAPTURE)){
        $s0=$m[0][1]; $d=0; $i=strpos($a,'{',$s0); $j=$i;
        do{ if($a[$j]==='{')$d++; elseif($a[$j]==='}')$d--; $j++; }while($d>0 && $j<strlen($a));
        echo "\n  --- sendMail() gövdesi ---\n";
        echo preg_replace('/^/m','  | ', substr($a,$s0,$j-$s0))."\n";
    } else { echo "\n  sendMail() bulunamadı!\n"; }
    // register + verify handler bağlamı
    foreach(['register','verify','sendMail('] as $kw){
        $p=stripos($a,$kw);
        if($p!==false){
            $ctx=substr($a,max(0,$p-200),500);
            echo "\n  --- '$kw' bağlamı ---\n".preg_replace('/^/m','  | ',$ctx)."\n";
        }
    }
}
echo "\n=== BİTTİ — çıktının tamamını yapıştır, sonra diag-mail.php'yi SİL ===\n";
