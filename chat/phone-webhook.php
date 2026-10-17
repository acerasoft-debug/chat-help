<?php
/**
 * ChatHelp — Telefon-Agent (Twilio Voice Webhook → TwiML)
 * =======================================================
 * Twilio ruft diese URL bei jedem Anruf auf:  https://chat-help.com/chat/phone-webhook.php?key=wk_…
 *   • key = widget_key des Agenten (agent_configs, phone_on=1); Plan muss aktiv sein.
 *   • 1. Aufruf (kein SpeechResult): Begrüßung (phone_greeting) + <Gather input="speech">.
 *   • Folgeaufrufe: SpeechResult → KI (ch_agent_system + Telefonregeln) → <Say> Antwort → erneut <Gather>.
 *   • Antwort enthält [WEITERLEITEN] → <Dial> phone_forward (wenn gesetzt), sonst Rückruf-Zusage; [ENDE] → <Hangup>.
 *   • Status-Callback (CallStatus completed/…): Anruf abschließen.
 *   • Tokens: CH_PRICING['phone_turn'] je Antwort vom Konto des Agent-Besitzers; Transkript in phone_calls.
 * Sicherheit: X-Twilio-Signature wird mit dem Auth-Token des eigenen Twilio-Kontos (Studio → Verbindungen) bzw. TWILIO_AUTH_TOKEN geprüft.
 * Einrichtung: Twilio-Nummer → Voice → "A call comes in": Webhook (POST) auf obige URL; Status-Callback dieselbe URL.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';
require_once __DIR__.'/agent-tools.php';   // Fachmodule (Termine, Bestellungen, Taxi, Hotel, Rückruf) + Werkzeug-Register
th_nocache();
header('Content-Type: text/xml; charset=UTF-8');

function twiml(string $inner): void { echo '<?xml version="1.0" encoding="UTF-8"?><Response>'.$inner.'</Response>'; exit; }
function x(string $s): string { return htmlspecialchars($s, ENT_XML1|ENT_QUOTES, 'UTF-8'); }
/**
 * Natürliche Stimme je Sprache (gültige Twilio-Werte laut offizieller Voice-Tabelle, geprüft vom Server am 16.10.2026).
 * phone_voice: "de-DE" (weiblich, beste Stimme) · "de-DE-m" (männlich) · "de-DE-g" / "de-DE-gm" (Google Chirp3-HD weiblich/männlich).
 * Amazon Polly „Generative“ (mit SSML-Pausen) für DE/EN/FR/ES/IT, Google Chirp3-HD für TR/NL/PL/RU und als Alternative.
 * db.php: TWILIO_VOICE (fester Wert für alles) · TWILIO_VOICE_TIER='neural' (Polly Neural statt Generative, falls im Twilio-Konto nicht freigeschaltet).
 */
function voice_for(string $lang): string {
    if(defined('TWILIO_VOICE') && TWILIO_VOICE) return TWILIO_VOICE;
    $g=$GLOBALS['ph_gender'] ?? 'f'; $chirp=fn(string $l,bool $m)=>'Google.'.$l.'-Chirp3-HD-'.($m?'Charon':'Aoede');
    $P=[ // [weiblich, männlich] – null = Google Chirp3-HD
      'de-DE'=>['Polly.Vicki-Generative','Polly.Daniel-Generative'], 'en-US'=>['Polly.Joanna-Generative','Polly.Matthew-Generative'],
      'en-GB'=>['Polly.Amy-Generative',null], 'fr-FR'=>['Polly.Lea-Generative',null], 'es-ES'=>['Polly.Lucia-Generative','Polly.Sergio-Generative'],
      'it-IT'=>['Polly.Bianca-Generative',null], 'tr-TR'=>[null,null], 'nl-NL'=>[null,null], 'pl-PL'=>[null,null], 'ru-RU'=>[null,null]];
    if(!isset($P[$lang])) $lang='de-DE';
    if($g==='g' || $g==='gm') return $chirp($lang,$g==='gm');
    $v=$P[$lang][$g==='m'?1:0] ?? null; if($v===null) return $chirp($lang,$g==='m');
    if(defined('TWILIO_VOICE_TIER') && TWILIO_VOICE_TIER==='neural') $v=str_replace('-Generative','-Neural',$v);
    return $v;
}
/** Gesprochener Text → SSML: Sätze mit kurzen Atempausen, damit es nach Mensch klingt statt nach Durchlesen. */
function say(string $text, string $lang): string {
    $v=voice_for($lang); $text=trim(preg_replace('/\s+/',' ',$text));
    if(!str_starts_with($v,'Polly.')) return '<Say voice="'.x($v).'" language="'.x($lang).'">'.x($text).'</Say>';
    $parts=preg_split('/(?<=[.!?…])\s+(?=[A-ZÄÖÜ0-9„"(])/u',$text) ?: [$text]; $ssml='';
    foreach($parts as $i=>$p){ $p=trim($p); if($p==='') continue; $ssml.=($ssml!==''?'<break time="'.(str_ends_with($p,'?')?'280':'360').'ms"/>':'').'<s>'.x($p).'</s>'; }
    return '<Say voice="'.x($v).'" language="'.x($lang).'">'.$ssml.'</Say>';
}
/** Zuhören: Standard-Spracherkennung (alle Sprachen), Ende bei natürlicher Sprechpause, Anrufer darf jederzeit unterbrechen (Twilio bargeIn = Standard).
 *  db.php: TWILIO_SPEECH_MODEL (z. B. 'phone_call') nur setzen, wenn das Modell für die Sprache freigegeben ist. */
function gather(string $inner, string $lang, string $action, string $hints=''): string {
    $model=(defined('TWILIO_SPEECH_MODEL') && TWILIO_SPEECH_MODEL) ? ' speechModel="'.x(TWILIO_SPEECH_MODEL).'"' : '';
    return '<Gather input="speech" language="'.x($lang).'"'.$model.' speechTimeout="auto" actionOnEmptyResult="true" profanityFilter="false"'.($hints!==''?' hints="'.x(mb_substr($hints,0,480)).'"':'').' action="'.x($action).'" method="POST">'.$inner.'</Gather>';
}
/** Feste Sätze des Agenten in der Sprache der eingestellten Stimme (KI-Antworten folgen ohnehin der Sprache des Anrufers). */
const PH_T=[
 'de'=>['tag'=>['Guten Morgen','Guten Tag','Guten Abend'],'greet'=>'Sie sprechen mit dem digitalen Assistenten. Was kann ich für Sie tun?','inactive'=>'Dieser Telefon-Assistent ist derzeit nicht aktiv. Auf Wiederhören.','slow'=>'Entschuldigung, das hat gerade zu lange gedauert. Könnten Sie mir Ihr Anliegen bitte noch einmal kurz sagen?','nohear'=>'Ich höre Sie leider nicht. Rufen Sie gern jederzeit noch einmal an. Auf Wiederhören!','still'=>'Hallo? Sind Sie noch dran? Ich höre zu.','connect'=>'Einen Moment bitte, ich verbinde Sie mit einem Mitarbeiter.','nobody'=>'Gerade ist leider niemand frei. Ich habe alles notiert – wir rufen Sie so schnell wie möglich zurück. Auf Wiederhören!','unreach'=>'Leider ist gerade niemand erreichbar. Bitte versuchen Sie es später noch einmal. Auf Wiederhören.','noted'=>'Ich habe Ihr Anliegen notiert, das Team ruft Sie so schnell wie möglich zurück. Auf Wiederhören!','glitch'=>'Entschuldigung, da war kurz eine Störung in der Leitung. Könnten Sie das bitte noch einmal sagen?','bye'=>'Vielen Dank für Ihren Anruf. Auf Wiederhören.','max'=>'Vielen Dank für das Gespräch, ich wünsche Ihnen noch einen schönen Tag. Auf Wiederhören!','fill'=>['Einen kleinen Moment, ich schaue kurz nach.','Mhm, einen Augenblick bitte.','Ich prüfe das gerade für Sie, einen Moment.','Gleich hab ich es, einen kleinen Moment.']],
 'en'=>['tag'=>['Good morning','Good afternoon','Good evening'],'greet'=>"you're speaking with the digital assistant. How can I help you?",'inactive'=>'This phone assistant is currently not active. Goodbye.','slow'=>'Sorry, that took a little too long. Could you briefly tell me again what you need?','nohear'=>"I'm afraid I can't hear you. Feel free to call again any time. Goodbye!",'still'=>"Hello? Are you still there? I'm listening.",'connect'=>'One moment please, I will connect you with a colleague.','nobody'=>"Unfortunately nobody is available right now. I've noted everything and we'll call you back as soon as possible. Goodbye!",'unreach'=>'Unfortunately nobody is available right now. Please try again later. Goodbye.','noted'=>"I've noted your request, the team will call you back as soon as possible. Goodbye!",'glitch'=>'Sorry, there was a short glitch on the line. Could you say that again, please?','bye'=>'Thank you for calling. Goodbye.','max'=>'Thank you for the conversation, have a lovely day. Goodbye!','fill'=>['One moment, let me check that for you.','Just a second, I am looking that up.','Bear with me a moment.']],
 'tr'=>['tag'=>['Günaydın','İyi günler','İyi akşamlar'],'greet'=>'dijital asistanla görüşüyorsunuz. Size nasıl yardımcı olabilirim?','inactive'=>'Bu telefon asistanı şu anda aktif değil. İyi günler.','slow'=>'Kusura bakmayın, biraz uzun sürdü. İsteğinizi kısaca tekrar söyler misiniz?','nohear'=>'Maalesef sizi duyamıyorum. Dilediğiniz zaman tekrar arayabilirsiniz. Hoşça kalın!','still'=>'Alo? Hâlâ hatta mısınız? Sizi dinliyorum.','connect'=>'Bir dakika lütfen, sizi bir çalışma arkadaşıma bağlıyorum.','nobody'=>'Şu anda maalesef müsait kimse yok. Her şeyi not aldım, en kısa sürede sizi geri arayacağız. Hoşça kalın!','unreach'=>'Şu anda maalesef kimseye ulaşılamıyor. Lütfen daha sonra tekrar deneyin. Hoşça kalın.','noted'=>'Talebinizi not aldım, ekibimiz en kısa sürede sizi geri arayacak. Hoşça kalın!','glitch'=>'Kusura bakmayın, hatta kısa bir parazit oldu. Tekrar söyler misiniz?','bye'=>'Aradığınız için teşekkürler. Hoşça kalın.','max'=>'Görüşme için teşekkürler, iyi günler dilerim. Hoşça kalın!','fill'=>['Bir saniye, hemen bakıyorum.','Kısa bir an lütfen, kontrol ediyorum.']],
 'fr'=>['tag'=>['Bonjour','Bonjour','Bonsoir'],'greet'=>"vous êtes en ligne avec l'assistant numérique. Que puis-je faire pour vous ?",'inactive'=>"Cet assistant téléphonique n'est pas actif pour le moment. Au revoir.",'slow'=>'Pardon, cela a pris un peu trop de temps. Pourriez-vous me redire brièvement votre demande ?','nohear'=>"Je ne vous entends malheureusement pas. N'hésitez pas à rappeler. Au revoir !",'still'=>"Allô ? Vous êtes toujours là ? Je vous écoute.",'connect'=>'Un instant, je vous mets en relation avec un collègue.','nobody'=>"Personne n'est disponible pour le moment. J'ai tout noté, nous vous rappelons au plus vite. Au revoir !",'unreach'=>"Personne n'est joignable pour le moment. Merci de réessayer plus tard. Au revoir.",'noted'=>"J'ai noté votre demande, l'équipe vous rappelle au plus vite. Au revoir !",'glitch'=>'Pardon, il y a eu une petite coupure. Pourriez-vous répéter ?','bye'=>'Merci de votre appel. Au revoir.','max'=>'Merci pour cet échange, je vous souhaite une belle journée. Au revoir !','fill'=>['Un petit instant, je vérifie.','Une seconde, je regarde.']],
 'es'=>['tag'=>['Buenos días','Buenas tardes','Buenas noches'],'greet'=>'habla con el asistente digital. ¿En qué puedo ayudarle?','inactive'=>'Este asistente telefónico no está activo en este momento. Adiós.','slow'=>'Perdone, ha tardado demasiado. ¿Podría repetirme brevemente lo que necesita?','nohear'=>'Lo siento, no le oigo. Puede volver a llamar cuando quiera. ¡Adiós!','still'=>'¿Hola? ¿Sigue ahí? Le escucho.','connect'=>'Un momento, por favor, le paso con un compañero.','nobody'=>'Ahora mismo no hay nadie disponible. Lo he anotado todo y le devolveremos la llamada lo antes posible. ¡Adiós!','unreach'=>'Ahora mismo no hay nadie disponible. Inténtelo más tarde, por favor. Adiós.','noted'=>'He anotado su consulta, el equipo le devolverá la llamada lo antes posible. ¡Adiós!','glitch'=>'Perdone, hubo una pequeña interrupción. ¿Podría repetirlo?','bye'=>'Gracias por su llamada. Adiós.','max'=>'Gracias por la conversación, que tenga un buen día. ¡Adiós!','fill'=>['Un momento, lo compruebo.','Un segundo, lo estoy mirando.']],
 'it'=>['tag'=>['Buongiorno','Buon pomeriggio','Buonasera'],'greet'=>"sta parlando con l'assistente digitale. Come posso aiutarla?",'inactive'=>'Questo assistente telefonico al momento non è attivo. Arrivederci.','slow'=>"Mi scusi, ci è voluto un po' troppo. Può ripetermi brevemente la sua richiesta?",'nohear'=>'Purtroppo non la sento. Può richiamare quando vuole. Arrivederci!','still'=>'Pronto? È ancora in linea? La ascolto.','connect'=>'Un attimo, la metto in contatto con un collega.','nobody'=>'Al momento non c’è nessuno disponibile. Ho annotato tutto, la richiameremo al più presto. Arrivederci!','unreach'=>'Al momento non è raggiungibile nessuno. Riprovi più tardi. Arrivederci.','noted'=>'Ho annotato la sua richiesta, il team la richiamerà al più presto. Arrivederci!','glitch'=>"Mi scusi, c'è stato un piccolo disturbo. Può ripetere?",'bye'=>'Grazie per la chiamata. Arrivederci.','max'=>'Grazie per la conversazione, le auguro una buona giornata. Arrivederci!','fill'=>['Un attimo, controllo subito.','Un secondo, verifico.']],
 'nl'=>['tag'=>['Goedemorgen','Goedemiddag','Goedenavond'],'greet'=>'u spreekt met de digitale assistent. Waarmee kan ik u helpen?','inactive'=>'Deze telefoonassistent is momenteel niet actief. Tot ziens.','slow'=>'Sorry, dat duurde even te lang. Kunt u uw vraag nog één keer kort herhalen?','nohear'=>'Ik kan u helaas niet horen. Bel gerust nog een keer. Tot ziens!','still'=>'Hallo? Bent u er nog? Ik luister.','connect'=>'Een ogenblik, ik verbind u door met een collega.','nobody'=>'Er is op dit moment helaas niemand beschikbaar. Ik heb alles genoteerd, we bellen u zo snel mogelijk terug. Tot ziens!','unreach'=>'Er is op dit moment helaas niemand bereikbaar. Probeer het later nog eens. Tot ziens.','noted'=>'Ik heb uw vraag genoteerd, het team belt u zo snel mogelijk terug. Tot ziens!','glitch'=>'Sorry, er was even een storing op de lijn. Kunt u dat herhalen?','bye'=>'Bedankt voor uw telefoontje. Tot ziens.','max'=>'Bedankt voor het gesprek, nog een fijne dag. Tot ziens!','fill'=>['Een ogenblikje, ik kijk het even na.','Momentje, ik zoek het op.']],
 'pl'=>['tag'=>['Dzień dobry','Dzień dobry','Dobry wieczór'],'greet'=>'tu cyfrowy asystent. W czym mogę pomóc?','inactive'=>'Ten asystent telefoniczny jest obecnie nieaktywny. Do widzenia.','slow'=>'Przepraszam, trwało to zbyt długo. Czy może Pan lub Pani krótko powtórzyć swoją sprawę?','nohear'=>'Niestety nie słyszę. Zapraszam do ponownego kontaktu. Do widzenia!','still'=>'Halo? Czy jest Pan lub Pani jeszcze na linii? Słucham.','connect'=>'Chwileczkę, łączę z pracownikiem.','nobody'=>'Niestety nikt nie jest teraz dostępny. Wszystko zanotowałem, oddzwonimy jak najszybciej. Do widzenia!','unreach'=>'Niestety nikt nie jest teraz osiągalny. Proszę spróbować później. Do widzenia.','noted'=>'Zanotowałem sprawę, zespół oddzwoni jak najszybciej. Do widzenia!','glitch'=>'Przepraszam, na linii były zakłócenia. Czy może Pan lub Pani powtórzyć?','bye'=>'Dziękuję za telefon. Do widzenia.','max'=>'Dziękuję za rozmowę, życzę miłego dnia. Do widzenia!','fill'=>['Chwileczkę, już sprawdzam.','Moment, sprawdzam to.']],
 'ru'=>['tag'=>['Доброе утро','Добрый день','Добрый вечер'],'greet'=>'с вами говорит цифровой помощник. Чем могу помочь?','inactive'=>'Этот телефонный помощник сейчас не активен. До свидания.','slow'=>'Извините, это заняло слишком много времени. Повторите, пожалуйста, коротко ваш вопрос.','nohear'=>'К сожалению, я вас не слышу. Позвоните, пожалуйста, ещё раз. До свидания!','still'=>'Алло? Вы ещё на линии? Я слушаю.','connect'=>'Минуточку, соединяю вас с сотрудником.','nobody'=>'Сейчас, к сожалению, никто не свободен. Я всё записал, мы перезвоним вам как можно скорее. До свидания!','unreach'=>'Сейчас, к сожалению, никто не доступен. Попробуйте позже. До свидания.','noted'=>'Я записал ваш вопрос, команда перезвонит вам как можно скорее. До свидания!','glitch'=>'Извините, на линии были помехи. Повторите, пожалуйста.','bye'=>'Спасибо за звонок. До свидания.','max'=>'Спасибо за разговор, хорошего дня. До свидания!','fill'=>['Одну секунду, сейчас посмотрю.','Минуточку, проверяю.']],
];
function T(string $k, string $lang){ $L=PH_T[substr($lang,0,2)] ?? PH_T['de']; return $L[$k] ?? PH_T['de'][$k]; }
/** Kurze menschliche Überbrückung, wenn das Nachschlagen (Kalender, Karte, Adresse) etwas dauert. */
function filler(string $lang): string { $l=T('fill',$lang); return $l[array_rand($l)]; }
/** Begrüßung nach Tageszeit des Betriebs (Europe/Berlin). */
function greeting(string $company, string $lang): string {
    $h=(int)(new DateTimeImmutable('now',new DateTimeZone('Europe/Berlin')))->format('G'); $t=T('tag',$lang); $tag=$h<5?$t[2]:($h<11?$t[0]:($h<18?$t[1]:$t[2]));
    return $tag.($company!==''?', '.$company:'').', '.T('greet',$lang);
}
/** Schreibvorgang nur, wenn der Hintergrund-Auftrag (pending.id) noch aktuell ist – sonst wurde der Anrufer schon weitergeführt. */
function ph_pending_owned(string $sid, string $pid): bool {
    $s=th_db()->prepare("SELECT COUNT(*) FROM phone_calls WHERE call_sid=? AND pending LIKE ?"); $s->execute([$sid,'%"id":"'.$pid.'"%']); return (int)$s->fetchColumn()>0;
}

$key=(string)($_GET['key'] ?? '');
$cbId=isset($_GET['cb']) ? max(0,(int)$_GET['cb']) : 0;   // KI-Rückruf (ausgehender Anruf aus der Rückrufliste)
$self='https://chat-help.com/chat/phone-webhook.php?key='.$key.($cbId ? '&cb='.$cbId : '');

if($_SERVER['REQUEST_METHOD']!=='POST'){ header('Content-Type: text/plain; charset=UTF-8'); exit("ChatHelp Telefon-Agent – Twilio Voice Webhook. Diese URL als Voice-Webhook (POST) deiner Twilio-Nummer eintragen.\n"); }

th_bootstrap();
$cfg=th_phone_cfg($key);
if(!$cfg) twiml(say(T('inactive','de-DE'),'de-DE').'<Hangup/>');
$aid=(int)$cfg['account_id'];
/* Echtheit: X-Twilio-Signature mit dem Auth-Token des eigenen Twilio-Kontos (Studio → Verbindungen) oder dem Plattform-Konto */
$sigTokens=array_values(array_filter([th_secret($aid,'twilio_token'),(defined('TWILIO_AUTH_TOKEN') ? (string)TWILIO_AUTH_TOKEN : '')]));
if($sigTokens && !th_twilio_sig_valid('https://chat-help.com'.(string)($_SERVER['REQUEST_URI'] ?? ''),$_POST,$sigTokens)){ error_log('CH phone-webhook: bad signature acc '.$aid); http_response_code(403); twiml('<Hangup/>'); }
$lang='de-DE'; $GLOBALS['ph_gender']='f';
if(preg_match('/^([a-z]{2}-[A-Z]{2})(?:-(m|f|g|gm))?$/',(string)$cfg['phone_voice'],$mv)){ $lang=$mv[1]; $GLOBALS['ph_gender']=$mv[2] ?? 'f'; }
$planId=ch_plan_active_any($aid,(string)$cfg['plan']) ? (string)$cfg['plan'] : null;
$pl=$planId ? ch_plan($planId) : null;
if(!$pl) twiml(say(T('inactive',$lang),$lang).'<Hangup/>');

$sid=preg_replace('/[^A-Za-z0-9]/','',(string)($_POST['CallSid'] ?? '')); if($sid==='') twiml('<Hangup/>');
$from=mb_substr((string)($_POST['From'] ?? ''),0,32); $to=mb_substr((string)($_POST['To'] ?? ''),0,32);
$status=(string)($_POST['CallStatus'] ?? ''); $speech=trim((string)($_POST['SpeechResult'] ?? ''));
$company=(string)($cfg['company'] ?: '');
$outbound=str_starts_with((string)($_POST['Direction'] ?? ''),'outbound');
$customer=$outbound ? $to : $from;   // bei KI-Rückrufen ist der Kunde der Angerufene
$ourNumber=$outbound ? $from : $to;  // unsere Nummer (für Weiterleitung als Absender)
$cbx=($cbId && function_exists('cb_call_context')) ? cb_call_context($cbId,$key) : null;
if($cbx && (int)($cbx['account_id'] ?? -1)!==$aid) $cbx=null;
$hints=implode(', ',array_filter([$cfg['company'],'Termin','Rückruf','Bestellung','Lieferung','Abholung','Mitarbeiter','Öffnungszeiten']));   // Erkennungshilfe für Twilio

/* Anruf beendet (Status-Callback) */
if(in_array($status,['completed','busy','failed','no-answer','canceled'],true) && !isset($_GET['dial'])){
    th_call_update($sid,['status'=>$status,'ended_at'=>'NOW()','pending'=>null]);
    if(function_exists('ch_push') && !$cbId){ $c=th_call($sid); if($c && (int)$c['turns']>0){ $trx=json_decode((string)$c['transcript'],true) ?: []; $first=''; foreach($trx as $m){ if(($m['role']??'')==='user'){ $first=(string)$m['content']; break; } } ch_push($aid,'call_done','Anruf von '.($customer ?: 'unbekannt').' · '.(int)$c['turns'].' Antworten',mb_substr($first,0,160).(($c['outcome'] ?? '')==='callback'?' · Rückruf gewünscht':''),'/chat/studio.php#phone',['tag'=>'call-'.$sid,'urgency'=>($c['outcome'] ?? '')==='callback'?'high':'normal']); } }
    if($cbId && function_exists('cb_on_call_status')){ $c=th_call($sid); cb_on_call_status($cbId,$status,(string)($_POST['AnsweredBy'] ?? ''),(int)($c['turns'] ?? 0)); }
    ch_heartbeat(); twiml('');
}
/* Ende der Weiterleitung (<Dial action>): Gespräch mit dem Team fand statt → auflegen; sonst Rückruf zusagen */
if(isset($_GET['dial'])){
    $ds=(string)($_POST['DialCallStatus'] ?? '');
    if(in_array($ds,['completed','answered'],true)){ th_call_update($sid,['outcome'=>'forwarded']); twiml('<Hangup/>'); }
    th_call_update($sid,['outcome'=>'callback']);
    twiml(say(T('nobody',$lang),$lang).'<Hangup/>');
}

$call=th_call($sid) ?: th_call_start($aid,$pl['id'],$sid,$from,$to);
$tr=json_decode((string)($call['transcript'] ?? '[]'),true) ?: [];
$turns=(int)($call['turns'] ?? 0); $silent=(int)($call['silent'] ?? 0);

/* Antwort der KI abholen (Warteschleife): Die KI rechnet nach dem Abschicken der Antwort an Twilio im Hintergrund weiter.
   Ist sie nach ~2,5 s fertig, antwortet der Agent sofort; sonst sagt er kurz „Einen Moment …“ und holt die Antwort danach ab. Kein 15-s-Timeout von Twilio.
   t = ID dieses Gesprächsschritts: eine verspätete Antwort eines älteren Schritts wird nie für eine neue Frage abgespielt. */
$wait=isset($_GET['w']) ? max(0,(int)$_GET['w']) : null; $pid=preg_replace('/[^a-f0-9]/','',(string)($_GET['t'] ?? ''));
if($wait!==null){
    $deadline=microtime(true)+($wait===0?2.6:8.0); $pend=null;
    do{ $pend=json_decode((string)(th_call($sid)['pending'] ?? ''),true); if(($pend['state'] ?? '')==='ready' && ($pend['id'] ?? '')===$pid) break; usleep(250000); } while(microtime(true)<$deadline);
    $mine=is_array($pend) && ($pend['id'] ?? '')===$pid && $pid!=='';
    if($mine && ($pend['state'] ?? '')==='ready'){ th_call_update($sid,['pending'=>null]); render_reply((array)$pend['reply'],$cfg,$lang,$self,$hints,$ourNumber); }
    if(!$mine || ($pend['state'] ?? '')!=='work' || $wait>=4 || time()-(int)($pend['at'] ?? 0)>45){
        if($mine) th_call_update($sid,['pending'=>null]);
        twiml(gather(say(T('slow',$lang),$lang),$lang,$self,$hints));
    }
    twiml(($wait===0 ? say(filler($lang),$lang) : '<Pause length="1"/>').'<Redirect method="POST">'.x($self.'&w='.($wait+1).'&t='.$pid).'</Redirect>');
}

/* Begrüßung */
if($speech==='' && $turns===0 && !$tr){
    /* KI-Rückruf: Anrufbeantworter erkannt → kurze Nachricht hinterlassen und auflegen (Ergebnis kommt per Status-Callback) */
    $ab=(string)($_POST['AnsweredBy'] ?? '');
    if($cbx && (str_starts_with($ab,'machine') || $ab==='fax')){ th_call_update($sid,['outcome'=>'voicemail','transcript'=>json_encode([['role'=>'assistant','content'=>(string)$cbx['voicemail']]],JSON_UNESCAPED_UNICODE)]); twiml(($ab==='fax'?'':say((string)$cbx['voicemail'],$lang)).'<Hangup/>'); }
    $greet=$cbx ? (string)$cbx['greeting'] : ($cfg['phone_greeting'] ?: greeting($company,$lang));
    $tr[]=['role'=>'assistant','content'=>$greet]; th_call_update($sid,['transcript'=>json_encode($tr,JSON_UNESCAPED_UNICODE)]);
    twiml(gather(say($greet,$lang),$lang,$self,$hints));
}
/* Stille */
if($speech===''){
    $silent++;
    if($silent>=2){ th_call_update($sid,['silent'=>$silent,'outcome'=>'silent','status'=>'completed','ended_at'=>'NOW()']); twiml(say(T('nohear',$lang),$lang).'<Hangup/>'); }
    th_call_update($sid,['silent'=>$silent]);
    twiml(gather(say(T('still',$lang),$lang),$lang,$self,$hints));
}

/* Antwort der KI (Schutz: höchstens 40 KI-Schritte je Anruf, 2.000 je Konto und Tag) */
if($turns>=40 || !th_rate('pd:'.$aid,2000,86400)) twiml(say(T('max',$lang),$lang).'<Hangup/>');
$tr[]=['role'=>'user','content'=>mb_substr($speech,0,1500)];
$cost=CH_PRICING['phone_turn'];
if(th_balance($aid) < $cost){
    th_call_update($sid,['transcript'=>json_encode($tr,JSON_UNESCAPED_UNICODE),'outcome'=>'no_tokens']);
    $fw=$cfg['phone_forward'];
    if($fw) twiml(say(T('connect',$lang),$lang).'<Dial timeout="25" callerId="'.x($ourNumber).'" action="'.x($self.'&dial=1').'" method="POST">'.x($fw).'</Dial>');
    twiml(say(T('unreach',$lang),$lang).'<Hangup/>');
}
$sys=ch_agent_system($pl,$cfg,substr($lang,0,2)).CH_PHONE_RULES;
if(!empty($cfg['phone_hours'])) $sys.="\nTelefonische Erreichbarkeit des Teams (für Rückrufe): ".$cfg['phone_hours'];
if($cbx) $sys.="\n\n".(string)$cbx['system'];   // Rückruf-Kontext: wen wir anrufen und warum
$sys.="\n".($outbound?'Nummer des Gesprächspartners':'Anrufer-Nummer')." (für Rückrufe, nicht vorlesen): ".($customer ?: 'unbekannt').". Aktuelle Zeit (Deutschland): ".(new DateTimeImmutable('now',new DateTimeZone('Europe/Berlin')))->format('l, d.m.Y H:i').".";
$sys.="\nZeilen „[intern …]“ im Verlauf sind Systemnotizen (z. B. bereits angelegte Bestellung/Buchung mit Code) – beachten, nie vorlesen, nichts doppelt anlegen.";
/* Verlauf für die KI: Werkzeug-Ergebnisse früherer Schritte als interne Notiz anhängen */
$msgs=array_slice(array_values(array_map(fn($m)=>['role'=>$m['role'],'content'=>(string)$m['content'].(!empty($m['done'])?"\n[intern erledigt: ".implode(', ',(array)$m['done']).']':'')],
    array_filter($tr,fn($m)=>in_array($m['role'] ?? '',['user','assistant'],true)))),-16);
if($msgs && $msgs[0]['role']!=='user') array_shift($msgs);   // Claude: erste Nachricht muss vom Nutzer sein
$tk=ch_toolkit($aid,'phone',preg_replace('/\D/','',(string)$customer));
if($tk['tools']){ $sys.=$tk['hint'];
    if($tk['appt'])  $sys.="\nAm Telefon: Datum und Uhrzeit immer ausgesprochen wiederholen (z. B. „Donnerstag, den dreiundzwanzigsten Oktober um fünfzehn Uhr“).";
    if($tk['order']) $sys.="\nAm Telefon: Positionen einzeln bestätigen, Adresse mit Hausnummer und Postleitzahl wiederholen, am Ende Gesamtsumme und Zeit nennen. Die Rufnummer des Anrufers gilt als Kontaktnummer, falls er keine andere nennt.";
}
$pid=bin2hex(random_bytes(6));
$bg=false;   // läuft der Schritt im Hintergrund?
/** Denkt, bucht/bestellt über Werkzeuge, schreibt Transkript + Tokens. Gibt die gesprochene Antwort zurück. */
$work=function() use($sys,$msgs,$tk,$tr,$turns,$cost,$aid,$pl,$sid,$cfg,$lang,$pid,&$bg): array {
    $sync=!$bg;
    if($tk['tools']){ $reply=th_ai_tools($sys,$msgs,$tk['tools'],$tk['exec'],320,$sync?11:40,$sync?2:5); }
    else $reply=th_ai($sys,$msgs,300,$sync?11:25);
    $calls=th_ai_info()['tool_calls'] ?? [];
    $done=array_values(array_filter(array_map(fn($c)=>$c['ok'] && preg_match('/^(create|book|cancel|request|order|update)_/',(string)$c['name']) ? $c['name'].(!empty($c['ref'])?' (Code '.$c['ref'].')':'') : null,$calls)));
    if($reply===null && !$done) return ['spoken'=>T('glitch',$lang),'forward'=>false,'end'=>false,'turns'=>$turns,'failed'=>true];   // KI-Ausfall: kein Abzug, kein Schritt
    $reply=$reply ?? '';
    $forward=str_contains($reply,'[WEITERLEITEN]'); $end=str_contains($reply,'[ENDE]');
    $spoken=trim(preg_replace('/\s+/',' ',preg_replace('/\[intern[^\]]*\]/u','',str_replace(['[WEITERLEITEN]','[ENDE]','*','#','`'],'',$reply))));
    if($spoken==='') $spoken=$forward ? T('connect',$lang) : ($done ? T('bye',$lang) : T('glitch',$lang));
    $cost+=2*count($calls); $turns++;
    $entry=['role'=>'assistant','content'=>$spoken]; if($done) $entry['done']=$done;
    $out=['spoken'=>$spoken,'forward'=>$forward,'end'=>$end,'turns'=>$turns];
    $outcome=$forward ? ($cfg['phone_forward']?'forwarded':'callback') : ($end ? 'done' : ($turns>=25 ? 'maxturns' : null));
    $charged=th_charge($aid,$cost,'phone:'.$pl['id'],$sid.':'.$pid);
    th_log_usage($aid,'phone',$pl['sector'],$charged,0);
    /* Schreiben nur, wenn dieser Schritt noch aktuell ist (Hintergrund) – sonst nur die erledigten Aktionen als Notiz an den aktuellen Verlauf hängen */
    $p=th_db();
    if(!$bg || ph_pending_owned($sid,$pid)){
        $trn=$tr; $trn[]=$entry;
        $q="UPDATE phone_calls SET transcript=?, turns=?, silent=0, tokens=tokens+?".($outcome?", outcome=?":"").($end?", status='completed', ended_at=NOW()":"")." WHERE call_sid=?".($bg?" AND pending LIKE ?":"");
        $v=[json_encode($trn,JSON_UNESCAPED_UNICODE),$turns,$charged]; if($outcome) $v[]=$outcome; $v[]=$sid; if($bg) $v[]='%"id":"'.$pid.'"%';
        $p->prepare($q)->execute($v);
    } elseif($done){
        $cur=json_decode((string)(th_call($sid)['transcript'] ?? '[]'),true) ?: [];
        $cur[]=['role'=>'assistant','content'=>'(Nachtrag: '.implode(', ',$done).' erledigt)','done'=>$done];
        $p->prepare("UPDATE phone_calls SET transcript=?, tokens=tokens+? WHERE call_sid=?")->execute([json_encode($cur,JSON_UNESCAPED_UNICODE),$charged,$sid]);
        $out['stale']=true;
    }
    return $out;
};
/* Antwort an Twilio sofort (Redirect zur Abholung), KI rechnet im Hintergrund – kein 15-s-Timeout, natürliche Überbrückung */
th_call_update($sid,['transcript'=>json_encode($tr,JSON_UNESCAPED_UNICODE),'pending'=>json_encode(['state'=>'work','id'=>$pid,'at'=>time()])]);
$twr='<?xml version="1.0" encoding="UTF-8"?><Response><Redirect method="POST">'.x($self.'&w=0&t='.$pid).'</Redirect></Response>';
if(th_finish_response($twr,'text/xml; charset=UTF-8')){
    $bg=true;
    try{ $res=$work(); }catch(Throwable $e){ error_log('CH phone work: '.$e->getMessage()); $res=['spoken'=>T('glitch',$lang),'forward'=>false,'end'=>false,'turns'=>$turns,'failed'=>true]; }
    th_db()->prepare("UPDATE phone_calls SET pending=? WHERE call_sid=? AND pending LIKE ?")->execute([json_encode(['state'=>'ready','id'=>$pid,'at'=>time(),'reply'=>$res],JSON_UNESCAPED_UNICODE),$sid,'%"id":"'.$pid.'"%']);
    exit;
}
th_call_update($sid,['pending'=>null]);   // Server kann nicht im Hintergrund weiterrechnen → synchron (kurze Zeitlimits)
render_reply($work(),$cfg,$lang,$self,$hints,$ourNumber);

/** TwiML für eine fertige Antwort: weiterleiten, auflegen oder weiter zuhören. */
function render_reply(array $r, array $cfg, string $lang, string $self, string $hints, string $ourNumber): void {
    $spoken=(string)($r['spoken'] ?? '');
    if(!empty($r['forward'])){
        $fw=$cfg['phone_forward'];
        if($fw) twiml(say($spoken,$lang).'<Dial timeout="25"'.($ourNumber!==''?' callerId="'.x($ourNumber).'"':'').' action="'.x($self.'&dial=1').'" method="POST">'.x($fw).'</Dial>');
        twiml(say($spoken.' '.T('noted',$lang),$lang).'<Hangup/>');
    }
    if(!empty($r['end'])) twiml(say($spoken,$lang).'<Pause length="1"/><Hangup/>');
    if((int)($r['turns'] ?? 0)>=25) twiml(say($spoken.' '.T('max',$lang),$lang).'<Hangup/>');
    twiml(gather(say($spoken,$lang),$lang,$self,$hints));
}
