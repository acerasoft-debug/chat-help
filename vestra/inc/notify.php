<?php
/**
 * VESTRA — local config loader + outgoing email (plain PHP mail()).
 * Real settings live in inc/config.php (NOT in git). Falls back to safe defaults.
 */
function vestra_cfg($k,$def=null){
  static $c=null;
  if($c===null){ $f=__DIR__.'/config.php'; $c=is_readable($f)?(require $f):[]; if(!is_array($c)) $c=[]; }
  return array_key_exists($k,$c) ? $c[$k] : $def;
}

/* low-level: send one UTF-8 plain-text email. Transport priority:
 *   1) HTTP API (mail_api_key set) — sends over HTTPS/443, which shared hosts
 *      leave open even when they block outbound SMTP ports (25/465/587). Best
 *      deliverability: the provider signs with its own SPF/DKIM. RECOMMENDED.
 *   2) Authenticated SMTP (smtp_host set) — needs an outbound SMTP port open.
 *   3) Local mail() — only lands in inboxes if the domain's SPF/DKIM authorize
 *      this server's IP.
 */
function vestra_send_mail($to,$subject,$body,$replyTo=''){
  if(!vestra_cfg('mail_enabled',false)) return false;
  if(!filter_var($to,FILTER_VALIDATE_EMAIL)) return false;
  if(vestra_cfg('mail_api_key','')!==''){
    $ok = vestra_api_send($to,$subject,$body,$replyTo);
    if(!$ok) error_log("[VESTRA Mail] API send failed to {$to} — subject: {$subject}");
    return $ok;
  }
  if(vestra_cfg('smtp_host','')!==''){
    $ok = vestra_smtp_send($to,$subject,$body,$replyTo);
    if(!$ok) error_log("[VESTRA Mail] SMTP send failed to {$to} — subject: {$subject}");
    return $ok;
  }
  $from=vestra_cfg('mail_from','support@vestrasales.com');
  $h ="From: VESTRA <{$from}>\r\n";
  $h.="MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n";
  if($replyTo && filter_var($replyTo,FILTER_VALIDATE_EMAIL)) $h.="Reply-To: {$replyTo}\r\n";
  $subj='=?UTF-8?B?'.base64_encode($subject).'?=';
  $ok = mail($to,$subj,$body,$h);
  if(!$ok) error_log("[VESTRA Mail] mail() returned false sending to {$to} — subject: {$subject}");
  return $ok;
}

/* Dependency-free authenticated SMTP (STARTTLS + AUTH LOGIN) — no PHPMailer/composer.
 * Config: smtp_host, smtp_port (default 587), smtp_user, smtp_pass, smtp_from, smtp_name. */
function vestra_smtp_send($to,$subject,$body,$replyTo=''){
  $host=vestra_cfg('smtp_host',''); $port=(int)vestra_cfg('smtp_port',587);
  $user=vestra_cfg('smtp_user',''); $pass=vestra_cfg('smtp_pass','');
  $from=vestra_cfg('smtp_from',$user); $name=vestra_cfg('smtp_name','VESTRA');
  if($host===''||$user===''||$pass===''||$from===''){ error_log('[VESTRA SMTP] smtp_host set but user/pass/from missing'); return false; }

  $fp=@fsockopen($host,$port,$errno,$errstr,15);
  if(!$fp){ error_log("[VESTRA SMTP] connect failed: {$errstr} ({$errno})"); return false; }
  stream_set_timeout($fp,15);

  $read=function() use ($fp){
    $data=''; while(($line=fgets($fp,515))!==false){ $data.=$line; if(isset($line[3])&&$line[3]===' ') break; } return $data;
  };
  $cmd=function($c) use ($fp,$read){ fwrite($fp,$c."\r\n"); return $read(); };

  $read(); // greeting
  $cmd('EHLO vestrasales.com');
  $r=$cmd('STARTTLS');
  if(strpos($r,'220')===false || !@stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)){
    fclose($fp); error_log('[VESTRA SMTP] STARTTLS failed'); return false;
  }
  $cmd('EHLO vestrasales.com');
  $cmd('AUTH LOGIN');
  $cmd(base64_encode($user));
  $r=$cmd(base64_encode($pass));
  if(strpos($r,'235')===false){ fclose($fp); error_log('[VESTRA SMTP] AUTH LOGIN rejected — check smtp_user/smtp_pass'); return false; }

  $cmd('MAIL FROM:<'.$from.'>');
  $r=$cmd('RCPT TO:<'.$to.'>');
  if(strpos($r,'250')===false){ fclose($fp); error_log("[VESTRA SMTP] RCPT TO rejected: {$to}"); return false; }
  $cmd('DATA');

  $h ="From: {$name} <{$from}>\r\nTo: <{$to}>\r\n";
  $h.='Subject: =?UTF-8?B?'.base64_encode($subject)."?=\r\n";
  $h.="MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n";
  if($replyTo && filter_var($replyTo,FILTER_VALIDATE_EMAIL)) $h.="Reply-To: {$replyTo}\r\n";
  $escapedBody=preg_replace('/^\./m','..',$body); // SMTP dot-stuffing
  $r=$cmd($h."\r\n".$escapedBody."\r\n.");
  $cmd('QUIT');
  fclose($fp);
  return strpos($r,'250')!==false;
}

/* Send via a transactional-email HTTP API (over HTTPS/443, no SMTP port needed).
 * Config: mail_api_provider ('brevo' default | 'resend'), mail_api_key,
 *         mail_from (verified sender address), smtp_name (display name).
 * Returns true on a 2xx from the provider. */
function vestra_api_send($to,$subject,$body,$replyTo=''){
  $provider=strtolower((string)vestra_cfg('mail_api_provider','brevo'));
  $key=(string)vestra_cfg('mail_api_key','');
  $from=(string)vestra_cfg('mail_from','support@vestrasales.com');
  $name=(string)vestra_cfg('smtp_name','VESTRA');
  if($key===''||$from===''){ error_log('[VESTRA API] mail_api_key or mail_from missing'); return false; }

  if($provider==='resend'){
    $url='https://api.resend.com/emails';
    $headers=['Authorization: Bearer '.$key,'Content-Type: application/json'];
    $payload=['from'=>"{$name} <{$from}>",'to'=>[$to],'subject'=>$subject,'text'=>$body];
    if($replyTo && filter_var($replyTo,FILTER_VALIDATE_EMAIL)) $payload['reply_to']=$replyTo;
  } else { // brevo (default)
    $url='https://api.brevo.com/v3/smtp/email';
    $headers=['api-key: '.$key,'Content-Type: application/json','Accept: application/json'];
    $payload=[
      'sender'=>['name'=>$name,'email'=>$from],
      'to'=>[['email'=>$to]],
      'subject'=>$subject,
      'textContent'=>$body,
    ];
    if($replyTo && filter_var($replyTo,FILTER_VALIDATE_EMAIL)) $payload['replyTo']=['email'=>$replyTo];
  }

  $ch=curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_POST=>true,
    CURLOPT_HTTPHEADER=>$headers,
    CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT=>20,
  ]);
  $resp=curl_exec($ch);
  if($resp===false){ error_log('[VESTRA API] curl error: '.curl_error($ch)); curl_close($ch); return false; }
  $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  if($code>=200 && $code<300) return true;
  error_log("[VESTRA API] {$provider} HTTP {$code}: ".substr((string)$resp,0,300));
  return false;
}

/* notify the operator address(es) configured in inc/config.php */
function vestra_notify($subject,$body,$replyTo=''){
  $list=vestra_cfg('notify',[]); if(is_string($list)) $list=[$list];
  $ok=false; foreach((array)$list as $to){ if(vestra_send_mail($to,$subject,$body,$replyTo)) $ok=true; }
  return $ok;
}

/* localized email-verification email → [subject, body] */
function vestra_verify_text($lang, $name, $token) {
  $url = 'https://vestrasales.com/verify?token='.$token;
  $T = [
   'en' => [
     'VESTRA — please verify your email address',
     "Hello %s,\n\nThank you for registering on VESTRA. Please verify your email address by clicking the link below:\n\n%s\n\nThis link is valid for 72 hours. If you did not register, you can safely ignore this email.\n\n— VESTRA · vestrasales.com",
   ],
   'de' => [
     'VESTRA — bitte bestätigen Sie Ihre E-Mail-Adresse',
     "Hallo %s,\n\nVielen Dank für Ihre Registrierung bei VESTRA. Bitte bestätigen Sie Ihre E-Mail-Adresse über den folgenden Link:\n\n%s\n\nDieser Link ist 72 Stunden gültig. Falls Sie sich nicht registriert haben, können Sie diese E-Mail ignorieren.\n\n— VESTRA · vestrasales.com",
   ],
   'fr' => [
     'VESTRA — veuillez vérifier votre adresse e-mail',
     "Bonjour %s,\n\nMerci de vous être inscrit sur VESTRA. Veuillez vérifier votre adresse e-mail en cliquant sur le lien ci-dessous :\n\n%s\n\nCe lien est valide 72 heures. Si vous n'êtes pas à l'origine de cette inscription, ignorez simplement cet e-mail.\n\n— VESTRA · vestrasales.com",
   ],
   'it' => [
     'VESTRA — verifica il tuo indirizzo e-mail',
     "Ciao %s,\n\nGrazie per esserti registrato su VESTRA. Verifica il tuo indirizzo e-mail cliccando sul link qui sotto:\n\n%s\n\nQuesto link è valido per 72 ore. Se non ti sei registrato, puoi ignorare questa e-mail.\n\n— VESTRA · vestrasales.com",
   ],
   'es' => [
     'VESTRA — por favor verifica tu dirección de correo',
     "Hola %s,\n\nGracias por registrarte en VESTRA. Por favor verifica tu dirección de correo electrónico haciendo clic en el enlace de abajo:\n\n%s\n\nEste enlace caduca en 72 horas. Si no te registraste, puedes ignorar este correo.\n\n— VESTRA · vestrasales.com",
   ],
  ];
  $t = $T[$lang] ?? $T['en'];
  return [$t[0], sprintf($t[1], $name, $url)];
}

/* localized welcome email after registration → [subject, body] */
function vestra_ack_text($lang,$name,$type){
  $role_en = $type==='seller' ? 'seller' : 'buyer';
  $url_en  = $type==='seller' ? 'https://vestrasales.com/seller?tab=kyc' : 'https://vestrasales.com/buyer?tab=kyc';
  $T=[
   'en'=>[
     'Welcome to VESTRA — account created',
     "Hello %s,\n\nYour VESTRA account has been created as a verified %s.\n\nNext step: please upload your verification documents at:\n%s\n\nOur team will review them and activate your account. Thank you for joining!\n\n— VESTRA · vestrasales.com",
   ],
   'de'=>[
     'Willkommen bei VESTRA — Konto erstellt',
     "Hallo %s,\n\nIhr VESTRA-Konto als %s wurde erfolgreich erstellt.\n\nNächster Schritt: Bitte laden Sie Ihre Verifizierungsdokumente hoch:\n%s\n\nUnser Team prüft diese und aktiviert Ihr Konto. Vielen Dank!\n\n— VESTRA · vestrasales.com",
   ],
   'fr'=>[
     'Bienvenue sur VESTRA — compte créé',
     "Bonjour %s,\n\nVotre compte VESTRA en tant que %s a été créé avec succès.\n\nProchaine étape : veuillez télécharger vos documents de vérification :\n%s\n\nNotre équipe les examinera et activera votre compte. Merci de nous rejoindre !\n\n— VESTRA · vestrasales.com",
   ],
   'it'=>[
     'Benvenuto su VESTRA — account creato',
     "Ciao %s,\n\nIl tuo account VESTRA come %s è stato creato con successo.\n\nProssimo passo: carica i tuoi documenti di verifica:\n%s\n\nIl nostro team li esaminerà e attiverà il tuo account. Grazie!\n\n— VESTRA · vestrasales.com",
   ],
   'es'=>[
     'Bienvenido a VESTRA — cuenta creada',
     "Hola %s,\n\nTu cuenta VESTRA como %s ha sido creada con éxito.\n\nSiguiente paso: sube tus documentos de verificación:\n%s\n\nNuestro equipo los revisará y activará tu cuenta. ¡Gracias!\n\n— VESTRA · vestrasales.com",
   ],
  ];
  $t = $T[$lang] ?? $T['en'];
  return [$t[0], sprintf($t[1], $name, $role_en, $url_en)];
}
