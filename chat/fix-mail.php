<?php
/**
 * ChatHelp — KAYIT ONAY MAILİ DÜZELTME (kendi kendini test eder + düzeltir)
 * =======================================================================
 * Yaptıkları:
 *   1) sendMail()'i DAYANIKLI sürümle değiştirir: çalışırken sırayla
 *      GoDaddy relay(:25) -> HostEurope STARTTLS(:587) -> SSL(:465) -> mail()
 *      dener; hangisi çalışıyorsa mail ONDAN gider. (auth.php yedeklenir)
 *   2) Tanımsız sendEmail() çağrısı kayıt akışını 500'e düşürüyorsa
 *      uyumluluk shim'i ekler (üyelik açılmasını engelleyen hata biterse).
 *   3) chat-help.de/chat/ -> chat-help.com/chat/ (bozuk doğrulama linki) düzeltir.
 *   4) Her yolu CANLI test eder, hangisi mail attı gösterir + sana gerçek
 *      test maili yollar.
 *
 * KULLANIM:  html/chat/ içine yükle -> aç:
 *   https://chat-help.com/chat/fix-mail.php?to=SENIN_MAILIN
 * Çıktının tamamını bana yapıştır. Sonra opcache-reset.php aç, fix-mail.php'yi SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
@set_time_limit(120);
require_once __DIR__ . '/db.php';

$from = defined('SMTP_FROM') ? SMTP_FROM : 'noreply@chat-help.com';
$name = defined('SMTP_NAME') ? SMTP_NAME : 'ChatHelp';
$to   = $_GET['to'] ?? 'acerasoft@gmail.com';

echo "ChatHelp — Mail Düzeltme  (" . date('c') . ")\n=====================================\n\n";
echo "FROM=$from  NAME=$name  TEST-> $to\n\n";

/* ---------- canlı SMTP test (loglu) ---------- */
function try_send($host,$port,$mode,$auth,$to,$subject,$html,$from,$name,&$log){
    $log=''; $target = ($mode==='ssl'?'ssl://':'').$host;
    $fp=@fsockopen($target,$port,$e,$s,15);
    if(!$fp){ $log.="  bağlanamadı: $s\n"; return false; }
    stream_set_timeout($fp,15);
    $R=function()use($fp,&$log){$d='';while($l=fgets($fp,515)){$d.=$l;if(isset($l[3])&&$l[3]===' ')break;}$log.="  S: ".trim($d)."\n";return $d;};
    $C=function($c,$h=false)use($fp,$R,&$log){$log.="  C: ".($h?'***':$c)."\n";fwrite($fp,$c."\r\n");return $R();};
    $R(); $C('EHLO chat-help.com');
    if($mode==='starttls'){
        $C('STARTTLS');
        if(!@stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)){$log.="  TLS başarısız\n";fclose($fp);return false;}
        $C('EHLO chat-help.com');
    }
    if($auth){
        $C('AUTH LOGIN'); $C(base64_encode(defined('SMTP_USER')?SMTP_USER:''),true);
        $r=$C(base64_encode(defined('SMTP_PASS')?SMTP_PASS:''),true);
        if(strpos($r,'235')===false){$log.="  !! AUTH başarısız (şifre?)\n";fclose($fp);return false;}
    }
    $C('MAIL FROM:<'.$from.'>'); $r=$C('RCPT TO:<'.$to.'>');
    if(strpos($r,'250')===false && strpos($r,'251')===false){$log.="  !! RCPT reddedildi\n";fclose($fp);return false;}
    $C('DATA');
    $msg='From: '.$name.' <'.$from.">\r\nTo: <".$to.">\r\nSubject: =?UTF-8?B?".base64_encode($subject)."?=\r\n".
         "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".
         chunk_split(base64_encode($html));
    fwrite($fp,$msg."\r\n.\r\n"); $r=$R(); $C('QUIT'); fclose($fp);
    return strpos($r,'250')!==false;
}

$H = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.hosteurope.de';
$html='<h2 style="color:#d4a84a">ChatHelp mail testi ✅</h2><p>Bu mail geldiyse bu yol çalışıyor.</p>';
$paths = [
    ['GoDaddy relay :25',              'relay-hosting.secureserver.net',25,'plain',   false],
    ['HostEurope :587 STARTTLS+AUTH',  $H,587,'starttls',true],
    ['HostEurope :465 SSL+AUTH',       $H,465,'ssl',     true],
];
$works=[];
echo "=== Canlı test (hangi yol mail atıyor?) ===\n";
foreach($paths as $p){
    echo "-- {$p[0]} --\n"; $log='';
    $ok=try_send($p[1],$p[2],$p[3],$p[4],$to,'ChatHelp Test — '.$p[0],$html,$from,$name,$log);
    echo $log."  >> ".($ok?"MAIL GİTTİ ✓":"olmadı ✗")."\n\n";
    if($ok) $works[]=$p[0];
}
echo "mail() fonksiyonu: ".(function_exists('mail')?'var':'YOK')."\n\n";

/* ---------- auth.php: sendMail dayanıklı sürüm + shim + link fix ---------- */
$af=__DIR__.'/auth.php';
echo "=== auth.php düzeltme ===\n";
if(!file_exists($af)){ echo "auth.php YOK — dur, bana haber ver.\n"; exit; }
$src=file_get_contents($af); $orig=$src;
$bak=$af.'.bak-fixmail-'.date('Ymd-His'); file_put_contents($bak,$src);
echo "yedek: ".basename($bak)."\n";
echo "'function sendMail' sayısı: ".substr_count($src,'function sendMail')."\n";
echo "'sendEmail(' çağrısı: ".substr_count($src,'sendEmail(')."   'function sendEmail': ".substr_count($src,'function sendEmail')."\n";
echo "'chat-help.de/chat/' : ".substr_count($src,'chat-help.de/chat/')."\n";

$NEW = <<<'PHP'
function sendMail(string $to, string $subject, string $html): void {
    $from = defined('SMTP_FROM') ? SMTP_FROM : 'noreply@chat-help.com';
    $name = defined('SMTP_NAME') ? SMTP_NAME : 'ChatHelp';
    $send = function($host,$port,$mode,$auth) use ($to,$subject,$html,$from,$name){
        $t=($mode==='ssl'?'ssl://':'').$host;
        $fp=@fsockopen($t,$port,$e,$s,15); if(!$fp){error_log("CH mail connect $host:$port $s");return false;}
        stream_set_timeout($fp,15);
        $R=function()use($fp){$d='';while($l=fgets($fp,515)){$d.=$l;if(isset($l[3])&&$l[3]===' ')break;}return $d;};
        $C=function($c)use($fp,$R){fwrite($fp,$c."\r\n");return $R();};
        $R(); $C('EHLO chat-help.com');
        if($mode==='starttls'){$C('STARTTLS'); if(!@stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)){fclose($fp);return false;} $C('EHLO chat-help.com');}
        if($auth){$C('AUTH LOGIN');$C(base64_encode(SMTP_USER));$r=$C(base64_encode(SMTP_PASS)); if(strpos($r,'235')===false){fclose($fp);error_log('CH mail auth fail');return false;}}
        $C('MAIL FROM:<'.$from.'>'); $r=$C('RCPT TO:<'.$to.'>'); if(strpos($r,'250')===false&&strpos($r,'251')===false){fclose($fp);return false;}
        $C('DATA');
        $msg='From: '.$name.' <'.$from.">\r\nTo: <".$to.">\r\nSubject: =?UTF-8?B?".base64_encode($subject)."?=\r\n".
             "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".
             chunk_split(base64_encode($html));
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

// sendMail() gövdesini brace-match ile bul + değiştir
$replaced=false;
if(preg_match('/function\s+sendMail\s*\([^{]*\{/',$src,$m,PREG_OFFSET_CAPTURE)){
    $s0=$m[0][1]; $i=strpos($src,'{',$s0); $d=0; $j=$i;
    do{ if($src[$j]==='{')$d++; elseif($src[$j]==='}')$d--; $j++; }while($d>0 && $j<strlen($src));
    $src = substr($src,0,$s0) . $NEW . substr($src,$j);
    $replaced=true;
} else {
    echo "!! sendMail() bulunamadı — değiştirilmedi. Bana haber ver.\n";
}
// domain link fix
$src=str_replace('https://chat-help.de/chat/','https://chat-help.com/chat/',$src);
$src=str_replace('chat-help.de/chat/','chat-help.com/chat/',$src);

$changed = ($src!==$orig);
if($changed) file_put_contents($af,$src);
echo ($replaced?"  ✓ sendMail() dayanıklı sürümle değiştirildi\n":"  ✗ sendMail() değişmedi\n");
echo ($changed?"DURUM: auth.php güncellendi ✅  (yedek: ".basename($bak).")\n":"DURUM: değişiklik yok\n");

/* ---------- kayıt/verify akışı (senin için döküm) ---------- */
echo "\n=== Kayıt/verify akışı (inceleme için) ===\n";
foreach(['register','verify','verified','sendMail('] as $kw){
    $p=stripos($orig,$kw);
    if($p!==false){ echo "--- '$kw' ---\n".preg_replace('/^/m','  | ',substr($orig,max(0,$p-160),420))."\n\n"; }
}

echo "=== SONUÇ ===\n";
echo "Çalışan yol(lar): ".($works?implode(', ',$works):"HİÇBİRİ — aşağıdaki loglara bak (şifre/port/hosting)")."\n";
echo "Şimdi: opcache-reset.php aç, sonra YENİ bir hesap kaydı dene (SPAM'i de kontrol et).\n";
echo "Bittiğinde: fix-mail.php ve diag-mail.php dosyalarını SİL.\n";
