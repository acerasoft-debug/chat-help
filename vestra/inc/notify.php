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
  $from=vestra_cfg('mail_from','hello@vestrasales.com');
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

/* localized "we received your request" acknowledgement → [subject, body] */
function vestra_ack_text($lang,$name,$type){
  $role = $type==='buyer' ? 'buyer' : 'seller';
  $T=[
   'en'=>['Your VESTRA request was received',"Hello %s,\n\nThank you — we have received your VESTRA %s request. Our team will review it and contact you as we open the founding cohort.\n\n— VESTRA · acerasoft LLC"],
   'de'=>['Ihre VESTRA-Anfrage ist eingegangen',"Hallo %s,\n\nvielen Dank — Ihre VESTRA-Anfrage als %s ist bei uns eingegangen. Unser Team prüft sie und meldet sich, sobald wir die Gründungsgruppe öffnen.\n\n— VESTRA · acerasoft LLC"],
   'fr'=>['Votre demande VESTRA a bien été reçue',"Bonjour %s,\n\nMerci — nous avons bien reçu votre demande VESTRA en tant que %s. Notre équipe l\\'examinera et vous recontactera à l\\'ouverture du groupe fondateur.\n\n— VESTRA · acerasoft LLC"],
   'it'=>['La tua richiesta VESTRA è stata ricevuta',"Ciao %s,\n\ngrazie — abbiamo ricevuto la tua richiesta VESTRA come %s. Il nostro team la esaminerà e ti contatterà all\\'apertura del gruppo fondatore.\n\n— VESTRA · acerasoft LLC"],
   'es'=>['Hemos recibido tu solicitud de VESTRA',"Hola %s,\n\ngracias — hemos recibido tu solicitud de VESTRA como %s. Nuestro equipo la revisará y te contactará cuando abramos el grupo fundador.\n\n— VESTRA · acerasoft LLC"],
  ];
  $t=$T[$lang] ?? $T['en'];
  return [$t[0], sprintf($t[1],$name,$role)];
}
