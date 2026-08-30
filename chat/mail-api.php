<?php
/**
 * ChatHelp — E-POSTAYI HTTPS API İLE GÖNDER (Brevo) — SMTP portları kapalıysa ÇÖZÜM
 * ==============================================================================
 * Neden: Paylaşımlı hosting genelde giden SMTP portlarını (25/465/587) engeller,
 * bu yüzden onay mailleri gitmiyor. Bu script sendMail()'i Brevo HTTPS API'sine
 * (api.brevo.com, port 443 — engellenmez) çevirir. SMTP + mail() yedek kalır.
 *
 * ÖN HAZIRLIK (2 dk, ücretsiz):
 *   1) brevo.com'a ücretsiz üye ol.
 *   2) Settings -> Senders: gönderen adresini (ör. noreply@chat-help.com VEYA
 *      kendi mailin) ekle ve gelen doğrulama mailindeki linke tıkla (VERIFIED olsun).
 *   3) Settings -> SMTP & API -> API Keys -> "Generate a new API key" -> kopyala
 *      (xkeysib-... ile başlar).
 *
 * KULLANIM (tek sefer):
 *   https://chat-help.com/chat/mail-api.php?key=xkeysib-ANAHTARIN&to=SENIN_MAILIN
 *   -> API'yi canlı test eder, sendMail()'i API'ye çevirir, anahtarı db.php'ye yazar.
 * Sonra opcache-reset.php aç, YENİ kayıt dene. Bitince BU DOSYAYI SİL (anahtar içerir).
 */
header('Content-Type: text/plain; charset=UTF-8');
@set_time_limit(60);
require_once __DIR__ . '/db.php';

$from = defined('SMTP_FROM') ? SMTP_FROM : 'noreply@chat-help.com';
$name = defined('SMTP_NAME') ? SMTP_NAME : 'ChatHelp';
$to   = $_GET['to']  ?? 'acerasoft@gmail.com';
$key  = $_GET['key'] ?? (defined('BREVO_KEY') ? BREVO_KEY : '');

echo "ChatHelp — API Mail Kurulum  (".date('c').")\n=====================================\n\n";
echo "FROM=$from  NAME=$name  TEST-> $to\n";
echo "Brevo anahtarı: ".($key ? ('verildi ('.substr($key,0,10).'…, '.strlen($key).' krktr)') : 'YOK — ?key=... ekle')."\n\n";

if(!$key){ echo "!! Önce Brevo API anahtarını al ve ?key=xkeysib-... ile aç. Yukarıdaki ÖN HAZIRLIK'a bak.\n"; exit; }

/* ---- 1) CANLI API TESTİ ---- */
echo "=== 1) Brevo API canlı test ===\n";
$payload = json_encode([
    'sender'      => ['name'=>$name, 'email'=>$from],
    'to'          => [['email'=>$to]],
    'subject'     => 'ChatHelp API test ✅',
    'htmlContent' => '<h2 style="color:#d4a84a">ChatHelp e-posta (API) çalışıyor ✅</h2><p>Bu mail Brevo HTTPS API üzerinden geldi.</p>',
]);
$ch=curl_init('https://api.brevo.com/v3/smtp/email');
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_TIMEOUT=>20,
    CURLOPT_HTTPHEADER=>['accept: application/json','api-key: '.$key,'content-type: application/json']]);
$res=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); $cerr=curl_error($ch); curl_close($ch);
echo "  HTTP $code\n  yanıt: ".($res?:$cerr)."\n";
$apiOk = ($code>=200 && $code<300);
echo "  >> ".($apiOk ? "MAIL API'DEN GİTTİ ✅  (gelen kutunu + SPAM kontrol et)" : "API HATASI ✗ (sık sebep: gönderen adres VERIFIED değil, veya anahtar yanlış)")."\n\n";

/* ---- 2) db.php'ye BREVO_KEY yaz (kalıcı) ---- */
echo "=== 2) db.php'ye anahtar yaz ===\n";
$dbf=__DIR__.'/db.php'; $db=file_get_contents($dbf);
if(strpos($db,'BREVO_KEY')!==false){ echo "  BREVO_KEY zaten db.php'de var — dokunulmadı.\n"; }
else {
    file_put_contents($dbf.'.bak-brevo-'.date('Ymd-His'),$db);
    $ins="\ndefine('BREVO_KEY', ".var_export($key,true).");  // ChatHelp: HTTPS mail API\n";
    if(preg_match('/^<\?php/',$db)) $db=preg_replace('/^<\?php/', "<?php".$ins, $db, 1);
    else $db=$ins.$db;
    file_put_contents($dbf,$db);
    echo "  ✓ BREVO_KEY db.php'ye eklendi (yedek alındı).\n";
}

/* ---- 3) auth.php sendMail() -> API öncelikli (SMTP yedek) ---- */
echo "\n=== 3) auth.php sendMail() -> API öncelikli ===\n";
$af=__DIR__.'/auth.php';
if(!file_exists($af)){ echo "  auth.php YOK — dur, haber ver.\n"; exit; }
$src=file_get_contents($af); $orig=$src;
file_put_contents($af.'.bak-mailapi-'.date('Ymd-His'),$src);

$NEW = <<<'PHP'
function sendMail(string $to, string $subject, string $html): void {
    $from = defined('SMTP_FROM') ? SMTP_FROM : 'noreply@chat-help.com';
    $name = defined('SMTP_NAME') ? SMTP_NAME : 'ChatHelp';
    // 1) HTTPS API (Brevo) — port 443, SMTP portları kapalı olsa da çalışır
    if (defined('BREVO_KEY') && BREVO_KEY) {
        $payload = json_encode(['sender'=>['name'=>$name,'email'=>$from],'to'=>[['email'=>$to]],'subject'=>$subject,'htmlContent'=>$html]);
        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payload, CURLOPT_TIMEOUT=>20,
            CURLOPT_HTTPHEADER=>['accept: application/json','api-key: '.BREVO_KEY,'content-type: application/json']]);
        $res = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($code>=200 && $code<300) return;
        error_log('CH Brevo fail '.$code.' '.$res);
    }
    // 2) SMTP yedek (relay -> HostEurope STARTTLS -> SSL)
    $send = function($host,$port,$mode,$auth) use ($to,$subject,$html,$from,$name){
        $t=($mode==='ssl'?'ssl://':'').$host;
        $fp=@fsockopen($t,$port,$e,$s,15); if(!$fp) return false;
        stream_set_timeout($fp,15);
        $R=function()use($fp){$d='';while($l=fgets($fp,515)){$d.=$l;if(isset($l[3])&&$l[3]===' ')break;}return $d;};
        $C=function($c)use($fp,$R){fwrite($fp,$c."\r\n");return $R();};
        $R(); $C('EHLO chat-help.com');
        if($mode==='starttls'){$C('STARTTLS'); if(!@stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)){fclose($fp);return false;} $C('EHLO chat-help.com');}
        if($auth){$C('AUTH LOGIN');$C(base64_encode(SMTP_USER));$r=$C(base64_encode(SMTP_PASS)); if(strpos($r,'235')===false){fclose($fp);return false;}}
        $C('MAIL FROM:<'.$from.'>'); $r=$C('RCPT TO:<'.$to.'>'); if(strpos($r,'250')===false&&strpos($r,'251')===false){fclose($fp);return false;}
        $C('DATA');
        $msg='From: '.$name.' <'.$from.">\r\nTo: <".$to.">\r\nSubject: =?UTF-8?B?".base64_encode($subject)."?=\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($html));
        fwrite($fp,$msg."\r\n.\r\n"); $r=$R(); $C('QUIT'); fclose($fp);
        return strpos($r,'250')!==false;
    };
    $H = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.hosteurope.de';
    if ($send('relay-hosting.secureserver.net',25,'plain',false)) return;
    if ($send($H,587,'starttls',true)) return;
    if ($send($H,465,'ssl',true)) return;
    @mail($to,$subject,$html,"MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: ".$name." <".$from.">");
}
if (!function_exists('sendEmail')) { function sendEmail($to,$n,$subject='',$html=''){ sendMail($to,$subject!==''?$subject:$n,$html); } }
PHP;

$replaced=false;
if(preg_match('/function\s+sendMail\s*\([^{]*\{/',$src,$m,PREG_OFFSET_CAPTURE)){
    $s0=$m[0][1]; $i=strpos($src,'{',$s0); $d=0;$j=$i;
    do{ if($src[$j]==='{')$d++; elseif($src[$j]==='}')$d--; $j++; }while($d>0 && $j<strlen($src));
    $src=substr($src,0,$s0).$NEW.substr($src,$j); $replaced=true;
} else { echo "  !! sendMail() bulunamadı.\n"; }
$src=str_replace('https://chat-help.de/chat/','https://chat-help.com/chat/',$src);
if($src!==$orig) file_put_contents($af,$src);
echo "  ".($replaced?"✓ sendMail() API öncelikli yapıldı (SMTP yedek).":"✗ sendMail() değişmedi")."\n";

/* ---- 4) register/verify — onay maili gerçekten çağrılıyor mu? ---- */
echo "\n=== 4) Kayıtta onay maili çağrılıyor mu? (inceleme) ===\n";
foreach(['register','verify','sendMail(','sendEmail('] as $kw){
    $p=stripos($orig,$kw);
    echo "  '$kw': ".($p!==false?"var (index ~$p)":"YOK")."\n";
}
$p=stripos($orig,'register');
if($p!==false) echo "\n  --- register bağlamı ---\n".preg_replace('/^/m','  | ',substr($orig,max(0,$p-120),560))."\n";

echo "\n=== SONUÇ ===\n";
echo "  • API testi: ".($apiOk?"BAŞARILI ✅ mail gitti":"BAŞARISIZ ✗ (yukarıdaki 'yanıt'a bak — gönderen VERIFIED mi?)")."\n";
echo "  • Şimdi: opcache-reset.php aç -> YENİ kayıt dene -> onay maili gelmeli.\n";
echo "  • GÜVENLİK: mail-api.php'yi SİL (API anahtarı içerir). Anahtar db.php'de kalıcı.\n";
