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
 *   • Tokens: ch_phone_turn_tokens() je Antwort (ChatHelp-Nummer 12, eigenes Twilio 9, Bestand 8) vom Konto des Agent-Besitzers; Transkript in phone_calls.
 * Sicherheit: X-Twilio-Signature wird mit dem Auth-Token des eigenen Twilio-Kontos (Studio → Verbindungen) bzw. TWILIO_AUTH_TOKEN geprüft.
 * Einrichtung: Twilio-Nummer → Voice → "A call comes in": Webhook (POST) auf obige URL; Status-Callback dieselbe URL.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';
require_once __DIR__.'/agent-tools.php';   // Fachmodule (Termine, Bestellungen, Taxi, Hotel, Rückruf) + Werkzeug-Register
if(is_file(__DIR__.'/voice-catalog.php')) require_once __DIR__.'/voice-catalog.php';   // Stimmenkatalog: agent_configs.voice_id → Twilio-Stimme (vc_twilio)
require_once __DIR__.'/stt-lib.php';   // günstigere Spracherkennung (Aufnahme + Deepgram/Azure) statt Gather, wenn eingeschaltet
th_nocache();
header('Content-Type: text/xml; charset=UTF-8');

function twiml(string $inner): void { echo '<?xml version="1.0" encoding="UTF-8"?><Response>'.$inner.'</Response>'; exit; }
function x(string $s): string { return htmlspecialchars($s, ENT_XML1|ENT_QUOTES, 'UTF-8'); }
/**
 * Natürliche Stimme je Sprache (gültige Twilio-Werte laut offizieller Voice-Tabelle, geprüft vom Server am 16.10.2026; ops/selftest.php lädt die Tabelle neu).
 * phone_voice: "de-DE" (weiblich, beste Stimme) · "de-DE-m" (männlich) · "de-DE-g" / "de-DE-gm" (Google Chirp3-HD weiblich/männlich) – das Geschlecht gilt für jede Sprache des Anrufs.
 *   Amazon Polly „Generative“ (mit SSML-Pausen): DE, EN (US/GB), FR, ES, IT.  Amazon Polly „Neural“: SV (Elin), NO (Ida), FI (Suvi), JA (Kazuha/Takumi).
 *   Google Chirp3-HD (Aoede/Charon): TR, NL, PL, RU sowie männliche Stimmen ohne Polly-Gegenstück und als Alternative ("-g"/"-gm").
 *   AZ: Twilio hat keine aserbaidschanische Stimme → mit Azure-Schlüssel oder ElevenLabs-Profisprecher echte az-Stimme über <Play> (ph_play),
 *   sonst tr-TR-Stimme (Aserbaidschanisch ist für türkische Hörer gut verständlich; ə/x/q werden angenähert), Spracherkennung (Gather) aber az-AZ.  db.php: TWILIO_GATHER_AZ (z. B. 'tr-TR'), falls az-AZ im Konto nicht freigegeben ist.
 * db.php: TWILIO_VOICE (fester Wert für die eingestellte Telefonsprache) · TWILIO_VOICE_TIER='neural' (Polly Neural statt Generative, falls im Twilio-Konto nicht freigeschaltet).
 */
const PH_VOICES=[ // [weiblich, männlich] – null = Google Chirp3-HD
  'de-DE'=>['Polly.Vicki-Generative','Polly.Daniel-Generative'], 'en-US'=>['Polly.Joanna-Generative','Polly.Matthew-Generative'],
  'en-GB'=>['Polly.Amy-Generative',null], 'fr-FR'=>['Polly.Lea-Generative',null], 'es-ES'=>['Polly.Lucia-Generative','Polly.Sergio-Generative'],
  'it-IT'=>['Polly.Bianca-Generative',null], 'tr-TR'=>[null,null], 'nl-NL'=>[null,null], 'pl-PL'=>[null,null], 'ru-RU'=>[null,null],
  'sv-SE'=>['Polly.Elin-Neural',null], 'nb-NO'=>['Polly.Ida-Neural',null], 'fi-FI'=>['Polly.Suvi-Neural',null], 'ja-JP'=>['Polly.Kazuha-Neural','Polly.Takumi-Neural']];
/** Stimme fürs Vorlesen (az-AZ → tr-TR, siehe oben). */
function ph_say_locale(string $loc): string { return $loc==='az-AZ' ? 'tr-TR' : (isset(PH_VOICES[$loc]) ? $loc : 'de-DE'); }
/** Weitere natürliche Google-Chirp3-HD-Stimmen je Geschlecht (gleiche Namen in allen Sprachen; nur Namen, die in der Twilio-Stimmentabelle stehen –
 *  ops/selftest.php listet sie je Sprache). Jeder Agent bekommt daraus fest „seine“ Stimme, damit verschiedene Betriebe verschieden klingen. */
const PH_CHIRP_POOL=['f'=>['Aoede','Kore','Leda','Zephyr'],'m'=>['Charon','Fenrir','Orus','Puck']];   // laut Twilio-Stimmentabelle (Selbsttest 18.10.2026) für de, fr, tr, es, it, en, nl, ru vorhanden
function voice_for(string $lang): string {
    $lang=ph_say_locale($lang);
    if(defined('TWILIO_VOICE') && TWILIO_VOICE && $lang===($GLOBALS['ph_def_locale'] ?? 'de-DE')) return TWILIO_VOICE;
    /* Vom Betrieb im Studio gewählte Katalogstimme (agent_configs.voice_id): Chirp3-HD-Stimme desselben Geschlechts, in jeder Sprache des Anrufs; sonst wie bisher */
    $vid=(string)($GLOBALS['ph_voice_id'] ?? ''); if($vid!=='' && function_exists('vc_twilio') && ($tv=vc_twilio($vid,$lang))!==null) return $tv;
    $g=$GLOBALS['ph_gender'] ?? 'f'; $chirp=fn(string $l,bool $m,string $n='')=>'Google.'.$l.'-Chirp3-HD-'.($n!=='' ? $n : ($m?'Charon':'Aoede'));
    if($g==='g' || $g==='gm') return $chirp($lang,$g==='gm');
    $m=$g==='m'; $v=PH_VOICES[$lang][$m?1:0] ?? null;
    if($v!==null && defined('TWILIO_VOICE_TIER') && TWILIO_VOICE_TIER==='neural') $v=str_replace('-Generative','-Neural',$v);
    /* Stimme je Agent: Polly (falls vorhanden) + Chirp3-HD-Vorrat, fest über Konto + Plan gewählt; db.php CH_PHONE_VOICE_POOL=false schaltet das ab */
    $seed=(string)($GLOBALS['ph_vseed'] ?? '');
    if($seed!=='' && !(defined('CH_PHONE_VOICE_POOL') && CH_PHONE_VOICE_POOL===false)){
        $opts=$v!==null ? [$v] : [];
        foreach(PH_CHIRP_POOL[$m?'m':'f'] as $n) $opts[]=$chirp($lang,$m,$n);
        return $opts[crc32($seed) % count($opts)];
    }
    return $v ?? $chirp($lang,$m);
}
/** Aserbaidschanisch mit türkischer Stimme: ə→e, x→h, q→g (nächstliegende türkische Laute). */
function ph_az_for_tr(string $t): string { return strtr($t,['ə'=>'e','Ə'=>'E','x'=>'h','X'=>'H','q'=>'g','Q'=>'G']); }
/**
 * Muttersprachliche Server-Stimme am Telefon (Twilio <Play> statt <Say>) – db.php TWILIO_PLAY_LANGS:
 *   'auto' (Standard): nur Sprachen ohne eigene Twilio-Stimme (Aserbaidschanisch), sobald dafür eine echte muttersprachliche Stimme da ist
 *                      (Azure Banu/Babek oder ein ElevenLabs-Profisprecher aus voice-scout) · 'all': jede Sprache mit Server-Stimme
 *                      (z. B. die ElevenLabs-Sprecher auch am Telefon) · 'az,tr,…': diese Sprachen · 'off'.
 * Stimme wie im Studio gewählt (Katalog) bzw. Geschlecht des Agenten. null = <Say> wie bisher.
 */
function ph_play_voice(string $lang): ?array {
    static $memo=[]; if(array_key_exists($lang,$memo)) return $memo[$lang];
    if(!function_exists('ch_tts_provider_for')) return $memo[$lang]=null;
    $l2=ph_l2($lang); $set=defined('TWILIO_PLAY_LANGS') ? strtolower(trim((string)TWILIO_PLAY_LANGS)) : 'auto'; $auto=$set==='' || $set==='auto';
    $want=$set==='all' || ($auto && $l2==='az') || (!$auto && in_array($l2,array_map('trim',explode(',',$set)),true));
    $prem=!empty($GLOBALS['ph_premium']) && ch_tts_creds('elevenlabs')!==null;   // Premium-Stimme: ElevenLabs in jeder Sprache mit muttersprachlichem Sprecher
    if((!$want && !$prem) || ch_lang_norm($l2)!==$l2) return $memo[$lang]=null;
    $g=(string)($GLOBALS['ph_gender'] ?? 'f'); $vid=(string)($GLOBALS['ph_voice_id'] ?? '');
    $v=ch_tts_voice_for(['gender'=>in_array($g,['m','gm'],true) ? 'm' : 'f'],$vid!=='' && ch_tts_catalog_entry($vid) ? $vid : null,$l2); $v['channel']='phone';
    if($prem) $v['premium']=true; elseif(empty($GLOBALS['ph_voice_legacy'])) $v['no_premium']=true;   // neue Kunden ohne Premium: Standardstimme (Azure/Twilio), kein ElevenLabs
    $p=ch_tts_provider_for($v['lang'],$v);
    if($prem && !$want && $p!=='elevenlabs') return $memo[$lang]=null;   // kein Premium-Sprecher für die Sprache → Twilio-Stimme wie ohne Premium
    $native=$p==='azure' || ($p==='google' && $l2!=='az') || ($p==='elevenlabs' && ($l2==='en' || ch_tts_native($l2,'elevenlabs')!==[]));
    return $memo[$lang]=(!$p || ($auto && !$native)) ? null : $v;
}
/** Aufpreis (Tokens) je Antwort, wenn die Premium-Stimme (ElevenLabs) diese Antwort spricht – sonst 0. */
function ph_premium_extra(string $lang): int {
    if(empty($GLOBALS['ph_premium']) || !($v=ph_play_voice($lang))) return 0;
    return ch_tts_provider_for($v['lang'],$v)==='elevenlabs' ? ch_phone_rates()['premium'] : 0;
}
/** Text → <Play>-Folge (vertont + im Cache, signierte Adressen) oder null, wenn etwas fehlt (dann <Say>). Auch zum Vorwärmen im Hintergrund. */
function ph_play(string $text, string $lang): ?string {
    $v=ph_play_voice($lang); if(!$v) return null;
    $parts=ch_tts_chunks($text); if(!$parts || count($parts)>6) return null;
    $xml='';
    foreach($parts as $c){ $file=ch_tts_cache_path($c,$v); $info=null;
        if(!$file || ch_tts_synth($c,$v,$info)===null || !is_file($file)) return null;
        $xml.='<Play>'.x(ch_tts_play_url($file)).'</Play>'; }
    return $xml;
}
/** Gesprochener Text → SSML: Sätze mit kurzen Atempausen, damit es nach Mensch klingt statt nach Durchlesen. $lang = Locale des Anrufs (z. B. ja-JP, az-AZ). */
function say(string $text, string $lang): string {
    $text=trim(preg_replace('/\s+/u',' ',$text));
    if($text!=='' && ($pl=ph_play($text,$lang))!==null) return $pl;   // muttersprachliche Server-Stimme (z. B. Aserbaidschanisch)
    $v=voice_for($lang); $sl=ph_say_locale($lang);
    if($lang==='az-AZ') $text=ph_az_for_tr($text);
    if(!str_starts_with($v,'Polly.')) return '<Say voice="'.x($v).'" language="'.x($sl).'">'.x($text).'</Say>';
    $parts=$sl==='ja-JP' ? (preg_split('/(?<=[。！？])/u',$text,-1,PREG_SPLIT_NO_EMPTY) ?: [$text]) : (preg_split('/(?<=[.!?…])\s+(?=[\p{Lu}0-9„"(¿¡])/u',$text) ?: [$text]); $ssml='';
    foreach($parts as $i=>$p){ $p=trim($p); if($p==='') continue; $ssml.=($ssml!==''?'<break time="'.(preg_match('/[?？]$/u',$p)?'280':'360').'ms"/>':'').'<s>'.x($p).'</s>'; }
    return '<Say voice="'.x($v).'" language="'.x($sl).'">'.$ssml.'</Say>';
}
/** Erkennungssprache für <Gather> (az-AZ, außer TWILIO_GATHER_AZ ist gesetzt). */
function ph_gather_locale(string $loc): string { return $loc==='az-AZ' && defined('TWILIO_GATHER_AZ') && TWILIO_GATHER_AZ ? (string)TWILIO_GATHER_AZ : $loc; }
/** Zuhören: Standard-Spracherkennung, Ende bei natürlicher Sprechpause, Anrufer darf jederzeit unterbrechen (Twilio bargeIn = Standard).
 *  db.php: TWILIO_SPEECH_MODEL (z. B. 'phone_call') nur setzen, wenn das Modell für die Sprache freigegeben ist.
 *  $multi=true (erste Frage, nur mit db.php TWILIO_SPEECH_MULTI=true): mehrsprachige Erkennung (Deepgram nova-3, language="multi") statt einer festen Sprache. */
function gather(string $inner, string $lang, string $action, string $hints='', bool $multi=false): string {
    if(($GLOBALS['ph_stt'] ?? '')!=='') return stt_record_twiml($inner,$action);   // Aufnahme-Modus (stt-lib): Twilio nimmt auf, Deepgram/Azure schreibt ab
    if($multi && defined('TWILIO_SPEECH_MULTI') && TWILIO_SPEECH_MULTI){ $model=' speechModel="deepgram_nova-3"'; $gl='multi'; }
    else { $model=(defined('TWILIO_SPEECH_MODEL') && TWILIO_SPEECH_MODEL) ? ' speechModel="'.x(TWILIO_SPEECH_MODEL).'"' : ''; $gl=ph_gather_locale($lang); }
    return '<Gather input="speech" language="'.x($gl).'"'.$model.' speechTimeout="auto" actionOnEmptyResult="true" profanityFilter="false"'.($hints!==''?' hints="'.x(mb_substr($hints,0,480)).'"':'').' action="'.x($action).'" method="POST">'.$inner.'</Gather>';
}
/** Feste Sätze des Agenten je Sprache des Anrufs (13 Plattformsprachen + Polnisch). KI-Antworten folgen der erkannten Sprache. */
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
 'sv'=>['tag'=>['God morgon','God dag','God kväll'],'greet'=>'du pratar med den digitala assistenten. Vad kan jag hjälpa dig med?','inactive'=>'Den här telefonassistenten är inte aktiv just nu. Hej då.','slow'=>'Förlåt, det tog lite för lång tid. Kan du kort säga vad du behöver en gång till?','nohear'=>'Jag hör dig tyvärr inte. Ring gärna igen när som helst. Hej då!','still'=>'Hallå? Är du kvar? Jag lyssnar.','connect'=>'Ett ögonblick, jag kopplar dig till en kollega.','nobody'=>'Tyvärr är ingen ledig just nu. Jag har antecknat allt och vi ringer upp dig så snart som möjligt. Hej då!','unreach'=>'Tyvärr når vi ingen just nu. Försök igen senare. Hej då.','noted'=>'Jag har antecknat ditt ärende, teamet ringer upp dig så snart som möjligt. Hej då!','glitch'=>'Förlåt, det blev en kort störning på linjen. Kan du säga det igen?','bye'=>'Tack för att du ringde. Hej då.','max'=>'Tack för samtalet och ha en fin dag. Hej då!','fill'=>['Ett ögonblick, jag kollar det.','Vänta lite, jag tittar efter.']],
 'no'=>['tag'=>['God morgen','God dag','God kveld'],'greet'=>'du snakker med den digitale assistenten. Hva kan jeg hjelpe deg med?','inactive'=>'Denne telefonassistenten er ikke aktiv akkurat nå. Ha det bra.','slow'=>'Beklager, det tok litt for lang tid. Kan du si kort hva du trenger én gang til?','nohear'=>'Jeg hører deg dessverre ikke. Ring gjerne igjen når som helst. Ha det bra!','still'=>'Hallo? Er du der fortsatt? Jeg lytter.','connect'=>'Et øyeblikk, jeg setter deg over til en kollega.','nobody'=>'Dessverre er ingen ledige akkurat nå. Jeg har notert alt, og vi ringer deg tilbake så snart som mulig. Ha det bra!','unreach'=>'Dessverre er ingen tilgjengelige akkurat nå. Prøv igjen senere. Ha det bra.','noted'=>'Jeg har notert henvendelsen din, teamet ringer deg tilbake så snart som mulig. Ha det bra!','glitch'=>'Beklager, det var en kort forstyrrelse på linjen. Kan du si det igjen?','bye'=>'Takk for at du ringte. Ha det bra.','max'=>'Takk for samtalen, ha en fin dag. Ha det bra!','fill'=>['Et øyeblikk, jeg sjekker det.','Bare et sekund, jeg ser etter.']],
 'fi'=>['tag'=>['Hyvää huomenta','Hyvää päivää','Hyvää iltaa'],'greet'=>'puhut digitaalisen avustajan kanssa. Miten voin auttaa?','inactive'=>'Tämä puhelinavustaja ei ole juuri nyt käytössä. Näkemiin.','slow'=>'Anteeksi, siinä kesti hetki liian kauan. Voisitko kertoa asiasi vielä lyhyesti uudelleen?','nohear'=>'En valitettavasti kuule sinua. Soita uudelleen milloin tahansa. Näkemiin!','still'=>'Haloo? Oletko vielä linjalla? Kuuntelen.','connect'=>'Hetkinen, yhdistän sinut kollegalleni.','nobody'=>'Valitettavasti kukaan ei ole juuri nyt vapaana. Kirjasin kaiken ylös, ja soitamme sinulle takaisin mahdollisimman pian. Näkemiin!','unreach'=>'Valitettavasti ketään ei tavoiteta juuri nyt. Yritä myöhemmin uudelleen. Näkemiin.','noted'=>'Kirjasin asiasi ylös, tiimimme soittaa sinulle takaisin mahdollisimman pian. Näkemiin!','glitch'=>'Anteeksi, linjalla oli pieni häiriö. Voisitko sanoa sen uudelleen?','bye'=>'Kiitos soitostasi. Näkemiin.','max'=>'Kiitos keskustelusta ja oikein mukavaa päivää. Näkemiin!','fill'=>['Hetkinen, tarkistan asian.','Pieni hetki, katson sitä.']],
 'ja'=>['tag'=>['おはようございます','こんにちは','こんばんは'],'greet'=>'デジタルアシスタントでございます。ご用件をお伺いいたします。','inactive'=>'この電話アシスタントは現在ご利用いただけません。失礼いたします。','slow'=>'申し訳ございません、少しお時間がかかってしまいました。ご用件をもう一度簡単にお聞かせいただけますか？','nohear'=>'申し訳ございません、お声が聞き取れません。またいつでもお電話ください。失礼いたします。','still'=>'もしもし、お聞きになれますか？お話をお伺いしております。','connect'=>'少々お待ちください。担当者におつなぎいたします。','nobody'=>'申し訳ございません、ただいま担当者が対応できません。ご用件は承りましたので、できるだけ早く折り返しお電話いたします。失礼いたします。','unreach'=>'申し訳ございません、ただいまどなたにもおつなぎできません。後ほどおかけ直しください。失礼いたします。','noted'=>'ご用件を承りました。担当者よりできるだけ早く折り返しお電話いたします。失礼いたします。','glitch'=>'申し訳ございません、回線が一瞬途切れたようです。もう一度おっしゃっていただけますか？','bye'=>'お電話ありがとうございました。失礼いたします。','max'=>'本日はありがとうございました。よい一日をお過ごしください。失礼いたします。','fill'=>['少々お待ちください。確認いたします。','ただいまお調べしております。']],
 'az'=>['tag'=>['Sabahınız xeyir','Günortanız xeyir','Axşamınız xeyir'],'greet'=>'rəqəmsal assistentlə danışırsınız. Sizə necə kömək edə bilərəm?','inactive'=>'Bu telefon assistenti hazırda aktiv deyil. Sağ olun.','slow'=>'Bağışlayın, bir az uzun çəkdi. İstəyinizi qısaca bir daha deyə bilərsinizmi?','nohear'=>'Təəssüf ki, sizi eşitmirəm. İstənilən vaxt yenidən zəng edə bilərsiniz. Sağ olun!','still'=>'Alo? Xəttdəsiniz? Sizi dinləyirəm.','connect'=>'Bir dəqiqə, sizi əməkdaşımıza qoşuram.','nobody'=>'Təəssüf ki, hazırda boş əməkdaş yoxdur. Hər şeyi qeyd etdim, ən qısa zamanda sizə geri zəng edəcəyik. Sağ olun!','unreach'=>'Təəssüf ki, hazırda heç kimlə əlaqə saxlamaq mümkün deyil. Zəhmət olmasa sonra yenidən cəhd edin. Sağ olun.','noted'=>'Sorğunuzu qeyd etdim, komandamız ən qısa zamanda sizə geri zəng edəcək. Sağ olun!','glitch'=>'Bağışlayın, xətdə qısa bir problem oldu. Bir daha deyə bilərsinizmi?','bye'=>'Zəng etdiyiniz üçün təşəkkür edirik. Sağ olun.','max'=>'Söhbət üçün təşəkkürlər, gününüz xoş keçsin. Sağ olun!','fill'=>['Bir saniyə, indi baxıram.','Bir az gözləyin, yoxlayıram.']],
];
/** Zusatzsätze je Sprache: Sprachangebot in der Begrüßung (offer), Bestätigung nach dem Wechsel (switch), „nicht verstanden“ (repeat),
 *  Anrufbeantworter beim KI-Rückruf (vm, {company}), Erkennungshilfen für Twilio (hints). */
const PH_TX=[
 'de'=>['offer'=>'Für Deutsch sprechen Sie einfach Deutsch.','switch'=>'Gern, dann sprechen wir Deutsch. Was kann ich für Sie tun?','repeat'=>'Entschuldigung, das habe ich nicht verstanden.','vm'=>'Guten Tag, hier ist der digitale Assistent von {company}. Wir rufen wegen Ihrer Rückrufbitte an und versuchen es später noch einmal. Auf Wiederhören.','hints'=>'Termin, Rückruf, Bestellung, Lieferung, Abholung, Mitarbeiter, Öffnungszeiten, Reservierung'],
 'en'=>['offer'=>'For English, just speak English.','switch'=>"Of course, let's continue in English. How can I help you?",'repeat'=>"Sorry, I didn't quite catch that.",'vm'=>"Hello, this is the digital assistant of {company}. We're calling about your callback request and will try again later. Goodbye.",'hints'=>'appointment, callback, order, delivery, pickup, opening hours, booking, reservation'],
 'fr'=>['offer'=>'Pour le français, parlez simplement français.','switch'=>'Bien sûr, continuons en français. Que puis-je faire pour vous ?','repeat'=>"Pardon, je n'ai pas bien compris.",'vm'=>"Bonjour, ici l'assistant numérique de {company}. Nous vous appelons au sujet de votre demande de rappel et réessaierons plus tard. Au revoir.",'hints'=>'rendez-vous, rappel, commande, livraison, à emporter, horaires, réservation'],
 'es'=>['offer'=>'Para español, hable en español.','switch'=>'Por supuesto, sigamos en español. ¿En qué puedo ayudarle?','repeat'=>'Perdone, no le he entendido bien.','vm'=>'Hola, le habla el asistente digital de {company}. Le llamamos por su solicitud de llamada y lo intentaremos más tarde. Adiós.','hints'=>'cita, llamada, pedido, entrega, recogida, horario, reserva'],
 'tr'=>['offer'=>'Türkçe için Türkçe konuşmanız yeterli.','switch'=>'Tabii, Türkçe devam edelim. Size nasıl yardımcı olabilirim?','repeat'=>'Kusura bakmayın, tam anlayamadım.','vm'=>'Merhaba, {company} dijital asistanı arıyor. Geri arama talebiniz için aradık, daha sonra tekrar deneyeceğiz. Hoşça kalın.','hints'=>'randevu, geri arama, sipariş, teslimat, gel al, çalışma saatleri, rezervasyon'],
 'ru'=>['offer'=>'Чтобы говорить по-русски, просто говорите по-русски.','switch'=>'Конечно, давайте продолжим по-русски. Чем могу помочь?','repeat'=>'Извините, я не расслышал.','vm'=>'Здравствуйте, это цифровой помощник компании {company}. Мы звоним по Вашей заявке на обратный звонок и перезвоним позже. До свидания.','hints'=>'запись, перезвонить, заказ, доставка, самовывоз, часы работы, бронирование'],
 'it'=>['offer'=>"Per l'italiano, parli pure in italiano.",'switch'=>'Certo, continuiamo in italiano. Come posso aiutarla?','repeat'=>'Mi scusi, non ho capito bene.','vm'=>"Buongiorno, sono l'assistente digitale di {company}. La chiamiamo per la sua richiesta di richiamata e riproveremo più tardi. Arrivederci.",'hints'=>'appuntamento, richiamata, ordine, consegna, ritiro, orari, prenotazione'],
 'nl'=>['offer'=>'Voor Nederlands, spreek gewoon Nederlands.','switch'=>'Natuurlijk, we gaan verder in het Nederlands. Waarmee kan ik u helpen?','repeat'=>'Sorry, dat heb ik niet goed verstaan.','vm'=>'Goedendag, u spreekt met de digitale assistent van {company}. We bellen over uw terugbelverzoek en proberen het later nog eens. Tot ziens.','hints'=>'afspraak, terugbellen, bestelling, bezorging, afhalen, openingstijden, reservering'],
 'pl'=>['offer'=>'Po polsku? Proszę po prostu mówić po polsku.','switch'=>'Oczywiście, rozmawiajmy po polsku. W czym mogę pomóc?','repeat'=>'Przepraszam, nie zrozumiałem.','vm'=>'Dzień dobry, tu cyfrowy asystent firmy {company}. Dzwonimy w sprawie prośby o kontakt i spróbujemy ponownie później. Do widzenia.','hints'=>'wizyta, oddzwonienie, zamówienie, dostawa, odbiór, godziny otwarcia, rezerwacja'],
 'sv'=>['offer'=>'För svenska, prata bara svenska.','switch'=>'Självklart, vi fortsätter på svenska. Hur kan jag hjälpa dig?','repeat'=>'Förlåt, det uppfattade jag inte.','vm'=>'Hej, det här är den digitala assistenten hos {company}. Vi ringer angående din förfrågan och försöker igen senare. Hej då.','hints'=>'tid, ring tillbaka, beställning, leverans, avhämtning, öppettider, bokning'],
 'no'=>['offer'=>'For norsk, bare snakk norsk.','switch'=>'Selvfølgelig, vi fortsetter på norsk. Hvordan kan jeg hjelpe deg?','repeat'=>'Beklager, det fikk jeg ikke med meg.','vm'=>'Hei, dette er den digitale assistenten til {company}. Vi ringer angående forespørselen din og prøver igjen senere. Ha det bra.','hints'=>'time, ring tilbake, bestilling, levering, henting, åpningstider, booking'],
 'fi'=>['offer'=>'Suomeksi? Puhukaa vain suomea.','switch'=>'Totta kai, jatketaan suomeksi. Miten voin auttaa?','repeat'=>'Anteeksi, en saanut selvää.','vm'=>'Hei, täällä yrityksen {company} digitaalinen avustaja. Soitamme soittopyyntösi vuoksi ja yritämme myöhemmin uudelleen. Näkemiin.','hints'=>'ajanvaraus, takaisinsoitto, tilaus, toimitus, nouto, aukioloajat, varaus'],
 'ja'=>['offer'=>'日本語をご希望の方は、そのまま日本語でお話しください。','switch'=>'かしこまりました。日本語でご案内いたします。ご用件をお聞かせください。','repeat'=>'申し訳ございません、うまく聞き取れませんでした。','vm'=>'こんにちは。{company}のAIアシスタントです。折り返しのご依頼につきお電話いたしました。後ほど改めてお掛けします。失礼いたします。','hints'=>'予約, 折り返し, 注文, 配達, 持ち帰り, 営業時間'],
 'az'=>['offer'=>'Azərbaycan dilində danışmaq üçün sadəcə azərbaycanca danışın.','switch'=>'Əlbəttə, Azərbaycan dilində davam edək. Sizə necə kömək edə bilərəm?','repeat'=>'Bağışlayın, yaxşı başa düşmədim.','vm'=>'Salam, {company} şirkətinin rəqəmsal assistentidir. Geri zəng sorğunuzla bağlı zəng edirik, bir az sonra yenidən cəhd edəcəyik. Sağ olun.','hints'=>'görüş, geri zəng, sifariş, çatdırılma, götürmə, iş saatları, rezervasiya'],
];
/** Grenzen je Paket: alle Leitungen belegt (lines), belegt + Weiterleitung (lines_fw), Höchstdauer erreicht (maxtime). */
const PH_TL=[
 'de'=>['lines'=>'Leider sind gerade alle Leitungen belegt. Bitte rufen Sie in ein paar Minuten noch einmal an. Vielen Dank und auf Wiederhören.','lines_fw'=>'Gerade sind alle Leitungen belegt – ich verbinde Sie mit dem Team.','maxtime'=>'Wir sind leider am Ende der Gesprächszeit. Wir melden uns bei Bedarf gern zurück. Vielen Dank für Ihren Anruf und auf Wiederhören.'],
 'en'=>['lines'=>'Sorry, all lines are busy right now. Please call again in a few minutes. Thank you and goodbye.','lines_fw'=>'All lines are busy right now – let me connect you to the team.','maxtime'=>"We've reached the end of the call time. We'll be happy to get back to you if needed. Thank you for calling, goodbye."],
 'tr'=>['lines'=>'Şu anda tüm hatlarımız dolu. Lütfen birkaç dakika sonra tekrar arayın. Teşekkürler, iyi günler.','lines_fw'=>'Şu anda tüm hatlar dolu, sizi ekibe bağlıyorum.','maxtime'=>'Görüşme süremizin sonuna geldik. Gerekirse sizi memnuniyetle geri ararız. Aradığınız için teşekkürler, iyi günler.'],
 'fr'=>['lines'=>'Désolé, toutes les lignes sont occupées. Merci de rappeler dans quelques minutes. Au revoir.','lines_fw'=>'Toutes les lignes sont occupées – je vous mets en relation avec l’équipe.','maxtime'=>'Nous arrivons à la fin du temps d’appel. Nous vous recontacterons volontiers si besoin. Merci de votre appel, au revoir.'],
 'es'=>['lines'=>'Lo sentimos, todas las líneas están ocupadas. Por favor, vuelva a llamar en unos minutos. Gracias y adiós.','lines_fw'=>'Ahora mismo todas las líneas están ocupadas; le paso con el equipo.','maxtime'=>'Hemos llegado al final del tiempo de llamada. Si hace falta, le devolvemos la llamada con gusto. Gracias por llamar, adiós.'],
 'it'=>['lines'=>'Ci dispiace, tutte le linee sono occupate. La preghiamo di richiamare tra qualche minuto. Grazie e arrivederci.','lines_fw'=>'Al momento tutte le linee sono occupate: la metto in contatto con il team.','maxtime'=>'Siamo arrivati al termine del tempo di chiamata. Se serve, la richiameremo volentieri. Grazie per la chiamata, arrivederci.'],
 'nl'=>['lines'=>'Helaas zijn alle lijnen op dit moment bezet. Belt u over een paar minuten nog eens. Dank u wel en tot ziens.','lines_fw'=>'Alle lijnen zijn bezet – ik verbind u door met het team.','maxtime'=>'We zijn aan het einde van de gesprekstijd. We bellen u graag terug als dat nodig is. Bedankt voor uw telefoontje, tot ziens.'],
 'pl'=>['lines'=>'Niestety wszystkie linie są zajęte. Proszę zadzwonić ponownie za kilka minut. Dziękujemy, do widzenia.','lines_fw'=>'Wszystkie linie są zajęte – łączę z zespołem.','maxtime'=>'Dobiegł koniec czasu rozmowy. W razie potrzeby chętnie oddzwonimy. Dziękujemy za telefon, do widzenia.'],
 'ru'=>['lines'=>'К сожалению, сейчас все линии заняты. Пожалуйста, перезвоните через несколько минут. Спасибо, до свидания.','lines_fw'=>'Сейчас все линии заняты – соединяю вас с командой.','maxtime'=>'Время разговора подошло к концу. При необходимости мы с удовольствием перезвоним. Спасибо за звонок, до свидания.'],
 'sv'=>['lines'=>'Tyvärr är alla linjer upptagna just nu. Ring gärna igen om några minuter. Tack och hej då.','lines_fw'=>'Alla linjer är upptagna just nu – jag kopplar dig till teamet.','maxtime'=>'Samtalstiden är slut. Vi ringer gärna tillbaka vid behov. Tack för samtalet, hej då.'],
 'no'=>['lines'=>'Beklager, alle linjer er opptatt akkurat nå. Ring gjerne igjen om noen minutter. Takk og ha det.','lines_fw'=>'Alle linjer er opptatt – jeg setter deg over til teamet.','maxtime'=>'Samtaletiden er over. Vi ringer gjerne tilbake ved behov. Takk for at du ringte, ha det.'],
 'fi'=>['lines'=>'Valitettavasti kaikki linjat ovat varattuina. Soitathan uudelleen muutaman minuutin kuluttua. Kiitos ja näkemiin.','lines_fw'=>'Kaikki linjat ovat varattuina – yhdistän sinut tiimille.','maxtime'=>'Puheaika on päättymässä. Soitamme mielellämme takaisin tarvittaessa. Kiitos soitostasi, näkemiin.'],
 'ja'=>['lines'=>'申し訳ございません。ただいま回線が大変混み合っております。数分後におかけ直しください。お電話ありがとうございました。','lines_fw'=>'ただいま回線が混み合っておりますので、担当者におつなぎします。','maxtime'=>'通話時間の上限となりました。必要に応じて折り返しご連絡いたします。お電話ありがとうございました。'],
 'az'=>['lines'=>'Təəssüf ki, hazırda bütün xətlər məşğuldur. Zəhmət olmasa bir neçə dəqiqədən sonra yenidən zəng edin. Təşəkkürlər, sağ olun.','lines_fw'=>'Hazırda bütün xətlər məşğuldur, sizi komandaya qoşuram.','maxtime'=>'Danışıq vaxtımızın sonuna çatdıq. Lazım olsa, sizə məmnuniyyətlə geri zəng edərik. Zəng etdiyiniz üçün təşəkkürlər, sağ olun.'],
];
/** Sprachcode (2 Buchstaben) aus Locale oder Code: 'nb-NO' → 'no'. */
function ph_l2(string $loc): string { $l=strtolower(substr($loc,0,2)); return ($l==='nb' || $l==='nn') ? 'no' : $l; }
/** Fester Satz in der Sprache des Anrufs (Locale oder Code); fehlt er, Englisch, dann Deutsch. */
function ph_t(string $k, string $lang){ $l=ph_l2($lang); return PH_T[$l][$k] ?? PH_TX[$l][$k] ?? PH_TL[$l][$k] ?? PH_T['en'][$k] ?? PH_TX['en'][$k] ?? PH_TL['en'][$k] ?? PH_T['de'][$k] ?? PH_TX['de'][$k] ?? ''; }
/** Twilio-Locale einer Anrufsprache: Standardsprache → eingestellte Locale (z. B. en-GB), sonst Tabelle CH_LANGS (i18n.php). */
function ph_locale(string $l): string { $def=$GLOBALS['ph_def_locale'] ?? 'de-DE'; if($l===ph_l2($def)) return $def; return CH_LANGS[$l]['phone'] ?? ($l==='pl' ? 'pl-PL' : 'de-DE'); }
/** Kurze menschliche Überbrückung, wenn das Nachschlagen (Kalender, Karte, Adresse) etwas dauert. */
function filler(string $lang): string { $l=ph_t('fill',$lang); return $l[array_rand($l)]; }
/** Begrüßung nach Tageszeit des Betriebs (Europe/Berlin). */
function greeting(string $company, string $lang): string {
    $h=(int)(new DateTimeImmutable('now',new DateTimeZone('Europe/Berlin')))->format('G'); $t=ph_t('tag',$lang); $tag=$h<5?$t[2]:($h<11?$t[0]:($h<18?$t[1]:$t[2]));
    if(ph_l2($lang)==='ja') return $tag.'。'.($company!==''?$company.'の':'').ph_t('greet',$lang);
    return $tag.($company!==''?', '.$company:'').', '.ph_t('greet',$lang);
}
/** Sprachangebot nach der Begrüßung: je Sprache ein kurzer Satz in dieser Sprache und mit ihrer Stimme („For English, just speak English.“).
 *  Erlaubte Sprachen ausdrücklich eingestellt → bis zu 4 davon; sonst (alle 13 erlaubt) nur Englisch – erkannt werden trotzdem alle erlaubten Sprachen. */
function ph_offers(array $cfg, array $allowed, string $cur): array {
    $explicit=trim((string)($cfg['langs'] ?? ''))!=='';
    $o=$explicit ? array_values(array_filter($allowed,fn($l)=>$l!==$cur)) : array_values(array_filter([$cur==='en' ? 'de' : 'en'],fn($l)=>in_array($l,$allowed,true)));
    return array_slice($o,0,4);
}
function ph_offer_xml(array $offers): string { $o=''; foreach($offers as $l) $o.=say(ph_t('offer',$l),ph_locale($l)); return $o; }
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
if(!$cfg) twiml(say(ph_t('inactive','de-DE'),'de-DE').'<Hangup/>');
$aid=(int)$cfg['account_id'];
/* Echtheit: X-Twilio-Signature mit dem Auth-Token des eigenen Twilio-Kontos (Studio → Verbindungen) oder dem Plattform-Konto */
$sigTokens=array_values(array_filter([th_secret($aid,'twilio_token'),(defined('TWILIO_AUTH_TOKEN') ? (string)TWILIO_AUTH_TOKEN : '')]));
if($sigTokens && !th_twilio_sig_valid('https://chat-help.com'.(string)($_SERVER['REQUEST_URI'] ?? ''),$_POST,$sigTokens)){ error_log('CH phone-webhook: bad signature acc '.$aid); http_response_code(403); twiml('<Hangup/>'); }
/* Rufumleitung vom eigenen Anschluss des Betriebs: nie auf genau diese Nummer zurückstellen (sonst Schleife) – dann Rückruf-Zusage statt Weiterleitung */
$fwdFrom=th_e164((string)($_POST['ForwardedFrom'] ?? '')); if($fwdFrom!=='' && trim((string)$cfg['phone_forward'])!=='' && th_same_phone($fwdFrom,(string)$cfg['phone_forward'])) $cfg['phone_forward']='';
$lang='de-DE'; $GLOBALS['ph_gender']='f';
if(preg_match('/^([a-z]{2}-[A-Z]{2})(?:-(m|f|g|gm))?$/',(string)$cfg['phone_voice'],$mv)){ $lang=$mv[1]; $GLOBALS['ph_gender']=$mv[2] ?? 'f'; }
/* Sprachen: Standard = Sprache der eingestellten Stimme (phone_voice), erlaubt = agent_configs.langs (leer = alle 13, i18n.php) */
$GLOBALS['ph_def_locale']=$lang; ch_i18n_bootstrap(); $defL=ph_l2($lang);
$GLOBALS['ph_vseed']=$aid.'|'.(string)$cfg['plan'];   // eigene Telefonstimme je Agent (voice_for)
$GLOBALS['ph_voice_id']=(string)($cfg['voice_id'] ?? '');   // im Studio gewählte Katalogstimme (leer = Vorrat)
$GLOBALS['ph_premium']=!empty($cfg['voice_premium']);   // Premium-Stimme (ElevenLabs, Aufpreis je Antwort) im Studio eingeschaltet
$GLOBALS['ph_voice_legacy']=ch_phone_legacy($aid,(string)$cfg['plan']);   // Bestandskunden: Stimmen wie bisher
$allowed=ch_cfg_langs(['lang'=>ch_lang_norm($defL) ?: ch_cfg_default($cfg),'langs'=>(string)($cfg['langs'] ?? '')]); if(!in_array($defL,$allowed,true)) array_unshift($allowed,$defL);
$planId=ch_plan_active_any($aid,(string)$cfg['plan']) ? (string)$cfg['plan'] : null;
$pl=$planId ? ch_plan($planId) : null;
if(!$pl) twiml(say(ph_t('inactive',$lang),$lang).'<Hangup/>');

$sid=preg_replace('/[^A-Za-z0-9]/','',(string)($_POST['CallSid'] ?? '')); if($sid==='') twiml('<Hangup/>');
$from=mb_substr((string)($_POST['From'] ?? ''),0,32); $to=mb_substr((string)($_POST['To'] ?? ''),0,32);
$status=(string)($_POST['CallStatus'] ?? ''); $speech=trim((string)($_POST['SpeechResult'] ?? ''));
$company=(string)($cfg['company'] ?: '');
$outbound=str_starts_with((string)($_POST['Direction'] ?? ''),'outbound');
$customer=$outbound ? $to : $from;   // bei KI-Rückrufen ist der Kunde der Angerufene
$ourNumber=$outbound ? $from : $to;  // unsere Nummer (für Weiterleitung als Absender)
$cbx=($cbId && function_exists('cb_call_context')) ? cb_call_context($cbId,$key) : null;
if($cbx && (int)($cbx['account_id'] ?? -1)!==$aid) $cbx=null;
/* Sprache dieses Anrufs (ch_conv_lang, Kanal call): bleibt nach dem Wechsel für den Rest des Gesprächs; probes = Versuche mit angebotenen Sprachen */
$peer=preg_replace('/\D/','',(string)$customer);
$cst=ch_conv_lang_get($aid,'call',$sid); $L=$defL; $probes=0;
if($cst && in_array($cst['lang'],$allowed,true)){ $L=$cst['lang']; $probes=$cst['probes']; }
$lang=ph_locale($L);
/* Spracherkennung: Twilio Gather (Standard) oder Aufnahme + Deepgram/Azure (ChatHelp-Nummern, wenn im Studio eingeschaltet und Schlüssel vorhanden) */
$ownTw=th_secret($aid,'twilio_token')!=='';
$GLOBALS['ph_stt']=stt_mode_for(!$ownTw,$L);
if(isset($_GET['rec'])){
    $ru=(string)($_POST['RecordingUrl'] ?? ''); $speech='';
    if($ru!=='' && !isset($_GET['empty']) && $GLOBALS['ph_stt']!=='' && (int)($_POST['RecordingDuration'] ?? 1)>0){
        $tsid=$ownTw ? th_secret($aid,'twilio_sid') : (defined('TWILIO_ACCOUNT_SID') ? (string)TWILIO_ACCOUNT_SID : ''); $ttok=$ownTw ? th_secret($aid,'twilio_token') : (defined('TWILIO_AUTH_TOKEN') ? (string)TWILIO_AUTH_TOKEN : '');
        $tx=stt_from_twilio($ru,$tsid,$ttok,$GLOBALS['ph_stt'],$L,(int)(th_call($sid)['turns'] ?? 0)===0);
        $speech=trim((string)$tx['text']); $_POST['Confidence']=$tx['text']!=='' ? max(0.4,(float)$tx['conf']) : 0;
        if($tx['error']!=='') error_log('CH phone stt: '.$tx['error']);
    }
}
$hintsFor=fn(string $l): string => implode(', ',array_filter(array_merge([(string)$cfg['company']],explode(', ',(string)ph_t('hints',$l)),array_map(fn($x)=>CH_LANGS[$x]['native'] ?? '',$allowed),['English'])));   // Erkennungshilfe für Twilio
$hints=$hintsFor($L);
$offers0=ph_offers($cfg,$allowed,$defL);

/* Anruf beendet (Status-Callback) */
if(in_array($status,['completed','busy','failed','no-answer','canceled'],true) && !isset($_GET['dial'])){
    th_call_update($sid,['status'=>$status,'ended_at'=>'NOW()','pending'=>null]); ch_conv_lang_del($aid,'call',$sid);
    if(function_exists('ch_push') && !$cbId){ $c=th_call($sid); if($c && (int)$c['turns']>0){ $trx=json_decode((string)$c['transcript'],true) ?: []; $first=''; foreach($trx as $m){ if(($m['role']??'')==='user'){ $first=(string)$m['content']; break; } } ch_push($aid,'call_done','Anruf von '.($customer ?: 'unbekannt').' · '.(int)$c['turns'].' Antworten',mb_substr($first,0,160).(($c['outcome'] ?? '')==='callback'?' · Rückruf gewünscht':''),'/chat/studio.php#phone',['tag'=>'call-'.$sid,'urgency'=>($c['outcome'] ?? '')==='callback'?'high':'normal']); } }
    if($cbId && function_exists('cb_on_call_status')){ $c=th_call($sid); cb_on_call_status($cbId,$status,(string)($_POST['AnsweredBy'] ?? ''),(int)($c['turns'] ?? 0)); }
    ch_heartbeat(); twiml('');
}
/* Ende der Weiterleitung (<Dial action>): Gespräch mit dem Team fand statt → auflegen; sonst Rückruf zusagen */
if(isset($_GET['dial'])){
    $ds=(string)($_POST['DialCallStatus'] ?? '');
    if(in_array($ds,['completed','answered'],true)){ th_call_update($sid,['outcome'=>'forwarded']); twiml('<Hangup/>'); }
    th_call_update($sid,['outcome'=>'callback']);
    twiml(say(ph_t('nobody',$lang),$lang).'<Hangup/>');
}

$call=th_call($sid) ?: th_call_start($aid,$pl['id'],$sid,$from,$to);
$tr=json_decode((string)($call['transcript'] ?? '[]'),true) ?: [];
$turns=(int)($call['turns'] ?? 0); $silent=(int)($call['silent'] ?? 0);
/* Grenzen je Paket (Studio → Einstellungen): Höchstdauer je Gespräch, gleichzeitige Gespräche */
$lim=ch_phone_limits($pl['id'],(int)($cfg['phone_max_min'] ?? 0)); $elapsed=th_call_elapsed($sid);

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
        twiml(gather(say(ph_t('slow',$lang),$lang),$lang,$self,$hints));
    }
    twiml(($wait===0 ? say(filler($lang),$lang) : '<Pause length="1"/>').'<Redirect method="POST">'.x($self.'&w='.($wait+1).'&t='.$pid).'</Redirect>');
}

/* Begrüßung: Standardsprache + kurzes Sprachangebot („For English, just speak English.“); bekannter Anrufer → gleich in seiner Sprache */
if($speech==='' && $turns===0 && !$tr){
    /* Alle Leitungen des Pakets belegt → an das Team weiterleiten (falls hinterlegt), sonst freundlich später anrufen lassen */
    if(!$cbx && !$outbound && th_calls_active($aid,ch_plan_variants($pl['id']),$sid,$lim['max_call_sec'])>=$lim['concurrent']){
        th_call_update($sid,['outcome'=>'lines_busy']);
        $fw=(string)$cfg['phone_forward'];
        if($fw!=='') twiml(say(ph_t('lines_fw',$lang),$lang).'<Dial timeout="25"'.($ourNumber!==''?' callerId="'.x($ourNumber).'"':'').' action="'.x($self.'&dial=1').'" method="POST">'.x($fw).'</Dial>');
        th_call_update($sid,['status'=>'completed','ended_at'=>'NOW()']); twiml(say(ph_t('lines',$lang),$lang).'<Hangup/>');
    }
    $rem=$peer!=='' ? ch_conv_lang_get($aid,'phone',$peer) : null;
    if($rem && $rem['lang']!==$defL && in_array($rem['lang'],$allowed,true)){ $L=$rem['lang']; $lang=ph_locale($L); $hints=$hintsFor($L); }
    ch_conv_lang_set($aid,'call',$sid,$L,0);
    /* KI-Rückruf: Anrufbeantworter erkannt → kurze Nachricht hinterlassen und auflegen (Ergebnis kommt per Status-Callback) */
    $ab=(string)($_POST['AnsweredBy'] ?? '');
    if($cbx && (str_starts_with($ab,'machine') || $ab==='fax')){ $vm=$L===$defL ? (string)$cbx['voicemail'] : str_replace('{company}',$company ?: ph_t('greet',$L),(string)ph_t('vm',$L));
        th_call_update($sid,['outcome'=>'voicemail','transcript'=>json_encode([['role'=>'assistant','content'=>$vm]],JSON_UNESCAPED_UNICODE)]); twiml(($ab==='fax'?'':say($vm,$lang)).'<Hangup/>'); }
    $greet=$cbx ? ($L===$defL ? (string)$cbx['greeting'] : greeting($company,$lang)) : (($cfg['phone_greeting'] && $L===$defL) ? (string)$cfg['phone_greeting'] : greeting($company,$lang));
    $offers=($cbx || $L!==$defL) ? [] : $offers0;
    $tr[]=['role'=>'assistant','content'=>$greet]; th_call_update($sid,['transcript'=>json_encode($tr,JSON_UNESCAPED_UNICODE)]);
    twiml(gather(say($greet,$lang).ph_offer_xml($offers),$lang,$self,$hints,!$cbx));
}
/* Stille */
if($speech===''){
    $silent++;
    if($silent>=2){ th_call_update($sid,['silent'=>$silent,'outcome'=>'silent','status'=>'completed','ended_at'=>'NOW()']); twiml(say(ph_t('nohear',$lang),$lang).'<Hangup/>'); }
    th_call_update($sid,['silent'=>$silent]);
    twiml(gather(say(ph_t('still',$lang),$lang).($turns===0 && !$cbx && $L===$defL ? ph_offer_xml($offers0) : ''),$lang,$self,$hints));   // erste Frage: Sprachangebot wiederholen
}

/* Sprache des Anrufers: erste Äußerung (Erkennung) oder ausdrücklicher Wunsch („English, please“, „auf Türkisch“) → Stimme und Erkennung wechseln
   für den Rest des Anrufs. Später nur bei eindeutigem Wunsch oder klar erkannter längerer Äußerung (die Erkennung läuft ja in der bisherigen Sprache). */
$named=ch_lang_named($speech); $sure=false; $det=ch_detect_lang($speech,'',$sure);
$nw=count(preg_split('/\s+/u',trim($speech)) ?: []);
$strong=$sure && ($turns===0 || ($nw>=4 && (ch_detect_lang_scores($speech)[$det] ?? 0)>=3));
$want=$named!=='' ? $named : ($strong ? $det : '');
$conf=is_numeric($_POST['Confidence'] ?? null) ? (float)$_POST['Confidence'] : 1.0;
if($want!=='' && in_array($want,$allowed,true) && $want!==$L){
    $L=$want; $lang=ph_locale($L); $hints=$hintsFor($L); ch_conv_lang_set($aid,'call',$sid,$L,$probes);
    if($peer!=='' && !$cbx){ if($L===$defL) ch_conv_lang_del($aid,'phone',$peer); else ch_conv_lang_set($aid,'phone',$peer,$L); }   // Anrufer beim nächsten Mal gleich in seiner Sprache begrüßen
    if($named!=='' && $nw<=4){   // reine Sprachwahl → kurz bestätigen und weiter zuhören (kein KI-Schritt, keine Kosten)
        $sw=(string)ph_t('switch',$L); $tr[]=['role'=>'user','content'=>mb_substr($speech,0,200)]; $tr[]=['role'=>'assistant','content'=>$sw];
        th_call_update($sid,['transcript'=>json_encode($tr,JSON_UNESCAPED_UNICODE),'silent'=>0]); twiml(gather(say($sw,$lang),$lang,$self,$hints));
    }
} elseif($want==='' && $turns===0 && !$cbx && $conf<0.35 && $probes<count($offers0)){
    /* Kaum verstanden und Sprache unklar: mit der nächsten angebotenen Sprache zuhören (mehrere Versuche, je einer pro angebotener Sprache) */
    $try=$offers0[$probes]; ch_conv_lang_set($aid,'call',$sid,$try,$probes+1);
    twiml(gather(say((string)ph_t('repeat',$lang),$lang).say((string)ph_t('offer',$try),ph_locale($try)),ph_locale($try),$self,$hintsFor($try)));
} elseif($want==='' && $turns===0 && $probes>0 && $L!==$defL && $conf<0.35){
    $L=$defL; $lang=ph_locale($L); $hints=$hintsFor($L); ch_conv_lang_set($aid,'call',$sid,$L,$probes);   // alle Versuche erfolglos → Standardsprache
}

/* Antwort der KI (Schutz: höchstens 40 KI-Schritte je Anruf, 2.000 je Konto und Tag) */
if($turns>=40 || !th_rate('pd:'.$aid,2000,86400)) twiml(say(ph_t('max',$lang),$lang).'<Hangup/>');
/* Höchstdauer erreicht → freundlich verabschieden und auflegen (kein KI-Schritt, keine Kosten) */
if($elapsed>=$lim['max_call_sec']){ $tr[]=['role'=>'assistant','content'=>ph_t('maxtime',$lang)]; th_call_update($sid,['transcript'=>json_encode($tr,JSON_UNESCAPED_UNICODE),'outcome'=>'maxtime','status'=>'completed','ended_at'=>'NOW()']); twiml(say(ph_t('maxtime',$lang),$lang).'<Hangup/>'); }
$closing=$elapsed>=$lim['max_call_sec']-75;   // die nächste Antwort ist die letzte (endet spätestens ~30 s vor der Höchstdauer)
$tr[]=['role'=>'user','content'=>mb_substr($speech,0,1500)];
$cost=ch_phone_turn_tokens($aid,$pl['id']);   // 12 ChatHelp-Nummer · 9 eigenes Twilio · 8 Bestand
if(th_balance($aid) < $cost){
    th_call_update($sid,['transcript'=>json_encode($tr,JSON_UNESCAPED_UNICODE),'outcome'=>'no_tokens']);
    $fw=$cfg['phone_forward'];
    if($fw) twiml(say(ph_t('connect',$lang),$lang).'<Dial timeout="25" callerId="'.x($ourNumber).'" action="'.x($self.'&dial=1').'" method="POST">'.x($fw).'</Dial>');
    twiml(say(ph_t('unreach',$lang),$lang).'<Hangup/>');
}
$cfgL=$cfg; $cfgL['lang']=ch_lang_norm($defL) ?: (string)$cfg['lang'];
$sys=ch_agent_system($pl,$cfgL,ch_lang_norm($L) ?: $L,true).CH_PHONE_RULES;
if($L!=='de') $sys.="\nTELEFON-SPRACHE: Der Anrufer spricht ".(CH_LANGS[$L]['en'] ?? ($L==='pl' ? 'Polish (polski)' : $L)).". Sprich ausschließlich ".(CH_LANGS[$L]['en'] ?? 'Polish').": Zahlen, Preise, Uhrzeiten und Daten so, wie man sie in dieser Sprache ausspricht – die deutschen Beispiele oben nur sinngemäß übertragen.".($L==='az' ? ' Aserbaidschanisch, nicht Türkisch.' : '').($L==='ja' ? ' Höfliche Telefonsprache (です・ます, Keigo).' : '');
if(!empty($cfg['phone_hours'])) $sys.="\nTelefonische Erreichbarkeit des Teams (für Rückrufe): ".$cfg['phone_hours'];
if($cbx) $sys.=TH_AI_SPLIT."\n\n".(string)$cbx['system'];   // Rückruf-Kontext: wen wir anrufen und warum
$sys.=TH_AI_SPLIT."\n".($outbound?'Nummer des Gesprächspartners':'Anrufer-Nummer')." (für Rückrufe, nicht vorlesen): ".($customer ?: 'unbekannt').". Aktuelle Zeit (Deutschland): ".(new DateTimeImmutable('now',new DateTimeZone('Europe/Berlin')))->format('l, d.m.Y H:i').".";
$sys.="\nZeilen „[intern …]“ im Verlauf sind Systemnotizen (z. B. bereits angelegte Bestellung/Buchung mit Code) – beachten, nie vorlesen, nichts doppelt anlegen.";
if($closing) $sys.="\nGESPRÄCHSENDE: Die Höchstdauer dieses Anrufs ist gleich erreicht. Beantworte das Anliegen in einem Satz, fasse Vereinbartes kurz zusammen, biete bei Offenem einen Rückruf an, verabschiede dich herzlich und schreibe am Ende [ENDE].";
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
    if($reply===null && !$done) return ['spoken'=>ph_t('glitch',$lang),'forward'=>false,'end'=>false,'turns'=>$turns,'failed'=>true];   // KI-Ausfall: kein Abzug, kein Schritt
    $reply=$reply ?? '';
    $forward=str_contains($reply,'[WEITERLEITEN]'); $end=str_contains($reply,'[ENDE]');
    $spoken=trim(preg_replace('/\s+/',' ',preg_replace('/\[intern[^\]]*\]/u','',str_replace(['[WEITERLEITEN]','[ENDE]','*','#','`'],'',$reply))));
    if($spoken==='') $spoken=$forward ? ph_t('connect',$lang) : ($done ? ph_t('bye',$lang) : ph_t('glitch',$lang));
    $cost+=2*count($calls)+ph_premium_extra($lang); $turns++;
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
    try{ $res=$work(); }catch(Throwable $e){ error_log('CH phone work: '.$e->getMessage()); $res=['spoken'=>ph_t('glitch',$lang),'forward'=>false,'end'=>false,'turns'=>$turns,'failed'=>true]; }
    try{ if(($res['spoken'] ?? '')!=='') ph_play((string)$res['spoken'],$lang); }catch(Throwable $e){}   // Server-Stimme vorab vertonen, solange der Anrufer noch die Überbrückung hört
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
        twiml(say($spoken.' '.ph_t('noted',$lang),$lang).'<Hangup/>');
    }
    if(!empty($r['end'])) twiml(say($spoken,$lang).'<Pause length="1"/><Hangup/>');
    if((int)($r['turns'] ?? 0)>=25) twiml(say($spoken.' '.ph_t('max',$lang),$lang).'<Hangup/>');
    twiml(gather(say($spoken,$lang),$lang,$self,$hints));
}
