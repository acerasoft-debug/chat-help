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
