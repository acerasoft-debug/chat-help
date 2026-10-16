<?php
/**
 * ChatHelp — E-POSTA KESİN ÇÖZÜM (teşhis sonucu: bu sunucuda giden SMTP soketleri KAPALI, PHP mail() AÇIK)
 * ====================================================================================================
 * diag-mail bulgusu: relay:25 "Permission denied", smtp.hosteurope.de çözümlenemiyor → mevcut sendMail() soket
 * açamayınca sessizce çıkıyor (return) → onay/şifre mailleri hiç gitmiyor.
 * Çözüm: sendMail() → 1) Brevo HTTPS API (BREVO_KEY varsa)  2) PHP mail() (platform sendmail, From: @chat-help.com)
 *        Gönderen .de yerine .com (alan adının MX/SPF'i GoDaddy'de) → MAIL_FROM ile değiştirilebilir.
 * KULLANIM (CLI): php -r '$_GET=["to"=>"x@y.z","apply"=>"1"]; include "mail-final.php";'
 *   apply yoksa yalnız test gönderir, auth.php'ye dokunmaz.
 */
header('Content-Type: text/plain; charset=UTF-8');
@set_time_limit(60);
require_once __DIR__ . '/db.php';

$to    = $_GET['to'] ?? 'acerasoft@gmail.com';
$from  = defined('MAIL_FROM') ? MAIL_FROM : 'support@chat-help.com';
$name  = defined('SMTP_NAME') ? SMTP_NAME : 'ChatHelp';
$apply = isset($_GET['apply']);
echo "ChatHelp — E-posta kesin çözüm  (".date('c').")   MOD: ".($apply?'UYGULA':'TEST')."\n";
echo "FROM=$from  NAME=$name  TEST-> $to  sendmail_path=".ini_get('sendmail_path')."\n\n";

/* ---- 1) canlı test: mail() ---- */
$hdr = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: $name <$from>\r\nReply-To: $from\r\nX-Mailer: ChatHelp";
$ok  = @mail($to, '=?UTF-8?B?'.base64_encode('ChatHelp Test ✅ (mail)').'?=', '<h2 style="color:#b0801f">ChatHelp E-Mail funktioniert ✅</h2><p>Weg: PHP mail() · '.date('c').'</p>', $hdr);
echo "=== 1) PHP mail() test === -> ".($ok ? "KABUL EDİLDİ (sendmail kuyruğa aldı) ✅" : "FALSE ✗")."\n";

/* ---- 2) Brevo API testi (varsa) ---- */
$brevoOk=null;
if(defined('BREVO_KEY') && BREVO_KEY){
    $ch=curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>20,
        CURLOPT_POSTFIELDS=>json_encode(['sender'=>['name'=>$name,'email'=>$from],'to'=>[['email'=>$to]],'subject'=>'ChatHelp Test ✅ (Brevo API)','htmlContent'=>'<p>Brevo HTTPS API '.date('c').'</p>']),
        CURLOPT_HTTPHEADER=>['accept: application/json','api-key: '.BREVO_KEY,'content-type: application/json']]);
    $res=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    $brevoOk=($code>=200&&$code<300); echo "=== 2) Brevo API test === HTTP $code -> ".($brevoOk?'GİTTİ ✅':'HATA ✗ '.substr((string)$res,0,160))."\n";
} else echo "=== 2) Brevo === BREVO_KEY yok (isteğe bağlı; mail() yeterliyse gerekmez)\n";

if(!$apply){ echo "\nÖNİZLEME: auth.php değişmedi. apply ile sendMail() yenilenir.\n"; exit; }

/* ---- 3) auth.php sendMail() değiştir ---- */
echo "\n=== 3) auth.php sendMail() -> Brevo (varsa) + mail() ===\n";
$af=__DIR__.'/auth.php';
if(!file_exists($af)){ echo "  auth.php YOK\n"; exit; }
$src=file_get_contents($af); $orig=$src;
$bak=$af.'.bak-mailfinal-'.date('Ymd-His'); file_put_contents($bak,$src); echo "  yedek: ".basename($bak)."\n";

$NEW = <<<'PHP'
function sendMail(string $to, string $subject, string $html): void {
    /* ChatHelp mail-final: bu sunucuda giden SMTP soketleri kapalı → Brevo API (varsa) + PHP mail() */
    $from = defined('MAIL_FROM') ? MAIL_FROM : 'support@chat-help.com';
    $name = defined('SMTP_NAME') ? SMTP_NAME : 'ChatHelp';
    if (strpos($html, '<') === false) { $html = nl2br(htmlspecialchars($html, ENT_QUOTES, 'UTF-8')); }
    if (defined('BREVO_KEY') && BREVO_KEY) {
        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_TIMEOUT=>20,
            CURLOPT_POSTFIELDS=>json_encode(['sender'=>['name'=>$name,'email'=>$from],'to'=>[['email'=>$to]],'subject'=>$subject,'htmlContent'=>$html]),
            CURLOPT_HTTPHEADER=>['accept: application/json','api-key: '.BREVO_KEY,'content-type: application/json']]);
        $res = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($code >= 200 && $code < 300) return;
        error_log('CH sendMail brevo '.$code.' '.$res);
    }
    $hdr = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: $name <$from>\r\nReply-To: $from\r\nX-Mailer: ChatHelp";
    if (@mail($to, '=?UTF-8?B?'.base64_encode($subject).'?=', $html, $hdr)) return;
    error_log('CH sendMail: mail() false for '.$to);
}
if (!function_exists('sendEmail')) { function sendEmail($to, $n, $subject = '', $html = '') { sendMail($to, $subject !== '' ? $subject : $n, $html); } }
PHP;

$replaced=false;
if(preg_match('/function\s+sendMail\s*\([^{]*\{/',$src,$m,PREG_OFFSET_CAPTURE)){
    $s0=$m[0][1]; $i=strpos($src,'{',$s0); $d=0; $j=$i;
    do{ if($src[$j]==='{')$d++; elseif($src[$j]==='}')$d--; $j++; }while($d>0 && $j<strlen($src));
    $src=substr($src,0,$s0).$NEW.substr($src,$j); $replaced=true;
} else echo "  !! sendMail() bulunamadı\n";
$src=str_replace('https://chat-help.de/chat/','https://chat-help.com/chat/',$src);
if($src!==$orig){
    file_put_contents($af,$src);
    /* sözdizimi kontrolü; hata varsa geri al */
    $lint=shell_exec('php -l '.escapeshellarg($af).' 2>&1');
    if(stripos((string)$lint,'No syntax errors')===false){ file_put_contents($af,$orig); echo "  !! SÖZDİZİMİ HATASI → geri alındı: ".trim((string)$lint)."\n"; exit; }
    echo "  ".($replaced?"✓ sendMail() yenilendi (Brevo→mail()), sendEmail() shim eklendi":"✗ sendMail değişmedi")."\n  php -l: ".trim((string)$lint)."\n";
} else echo "  değişiklik yok\n";

/* ---- 4) kayıt akışı onay maili çağrısı var mı ---- */
echo "\n=== 4) kayıt akışı ===\n";
foreach(['function doRegister','sendVerificationEmail(','function doResendVerification','email_verified'] as $kw){ echo "  '$kw': ".(stripos($orig,$kw)!==false?'var':'YOK')."\n"; }
echo "\n=== SONUÇ ===\n  mail(): ".($ok?'çalışıyor':'FALSE')."  · Brevo: ".($brevoOk===null?'tanımsız':($brevoOk?'çalışıyor':'hata'))."\n  Gelen kutunu (ve SPAM) kontrol et: $to — 'ChatHelp Test ✅'\n";
