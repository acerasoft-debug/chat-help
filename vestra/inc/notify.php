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

/* low-level: send one UTF-8 plain-text email */
function vestra_send_mail($to,$subject,$body,$replyTo=''){
  if(!vestra_cfg('mail_enabled',false)) return false;
  if(!filter_var($to,FILTER_VALIDATE_EMAIL)) return false;
  $from=vestra_cfg('mail_from','support@vestrasales.com');
  $h ="From: VESTRA <{$from}>\r\n";
  $h.="MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n";
  if($replyTo && filter_var($replyTo,FILTER_VALIDATE_EMAIL)) $h.="Reply-To: {$replyTo}\r\n";
  $subj='=?UTF-8?B?'.base64_encode($subject).'?=';
  return @mail($to,$subj,$body,$h);
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
