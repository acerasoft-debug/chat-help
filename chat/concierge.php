<?php
/**
 * ChatHelp — Ana sayfa asistanı (herkese açık, konuşabilir yapay zekâ)
 * ====================================================================
 * POST /chat/concierge.php {messages:[{role,content}], lang}  → {reply}
 * GET  /chat/concierge.php?lang=de                             → {greeting, chips[]}
 * Live-Demo eines Agenten (Startseite):
 *   GET  ?agent=<planId>  → {demo, agent, company, persona, greeting, chips, voice:{gender,rate,pitch}, speech:[{text,t}], fillers:[{text,t}], tts, lang}
 *   POST {agent, channel:'phone'|'chat', messages, lang} → {reply, tools, forward, end, …, speech:[{text,t}], tts}
 *   speech/fillers = sprechbare Sätze mit Token für /chat/tts.php (speech-lib.php); tts=true, wenn eine Server-Stimme eingerichtet ist.
 * Platform anahtarıyla (CLAUDE_KEY / DEEPSEEK_KEY, db.php) çalışır; ziyaretçiden token istemez.
 * Koruma: IP başına 20 mesaj/saat, toplam 400 mesaj/saat (tablo: concierge_log). Yalnız ChatHelp konuları.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';
require_once __DIR__.'/speech-lib.php';

/** Persona + Sprechstil des Demo-Betriebs + allgemeine Realismus-Regeln (nach ch_agent_system, vor dem Werkzeug-Hinweis). */
function concierge_persona_prompt(array $d, bool $phone): string {
    $name=trim((string)($d['persona'] ?? '')); $fem=(($d['voice']['gender'] ?? 'f')!=='m'); $co=(string)($d['company'] ?? '');
    $o='';
    if($name!==''){
        $o.="\n\nPERSONA: Du bist {$name}, ".($fem?'die digitale Mitarbeiterin':'der digitale Mitarbeiter')." von „{$co}“, und klingst wie ein sehr guter, erfahrener Mensch aus genau diesem Betrieb."
           ." Die Begrüßung ist bereits erfolgt („".trim((string)($d['greeting'] ?? ''))."“) – stell dich nicht noch einmal vor und begrüße nicht erneut."
           ." Fragt jemand, ob du ein Mensch bist: freundlich und ehrlich – du bist {$name}, ".($fem?'die digitale Assistentin':'der digitale Assistent')." von {$co}, und hilfst trotzdem direkt weiter.";
        $o.=!empty($d['du']) ? " Du duzt dein Gegenüber (so ist es in diesem Betrieb üblich)." : " Du siezt dein Gegenüber, außer es bietet dir ausdrücklich das Du an.";
    }
    if(trim((string)($d['style'] ?? ''))!=='') $o.="\n\nSTIL UND HALTUNG".($name!==''?" ({$name})":'').":\n".trim((string)$d['style']);
    $o.="\n\nREALISMUS – so klingst du wie ein echter Mensch, nicht wie ein Bot:"
       ."\n• Reagiere zuerst kurz auf das Gesagte („Oh, das klingt unangenehm.“, „Alles klar.“, „Verstehe.“), dann geht es weiter – aber nicht jedes Mal mit derselben Einleitung."
       ."\n• Immer nur EINE Frage pro Antwort. Kurze, natürliche Sätze in der Alltagssprache dieser Branche – keine Behördensprache."
       ."\n• Wichtige Angaben (Name, Datum, Uhrzeit, Adresse, Betrag, Code, Telefonnummer) kurz wiederholen und bestätigen lassen, bevor du etwas festmachst."
       ."\n• Gefühle wahrnehmen: Ärger, Sorge, Schmerz oder Eile kurz und ehrlich anerkennen, dann lösungsorientiert weiter. Wer es eilig hat, bekommt noch knappere Antworten."
       ."\n• Keine Roboter-Floskeln: kein „Als KI …“, kein „Gerne helfe ich Ihnen weiter!“, kein „Ich hoffe, das hilft“, kein „Zögern Sie nicht …“; keine Formulierung im Gespräch zweimal verwenden."
       ."\n• ".($phone ? 'Am Telefon: keine Aufzählungen, keine Listen, keine Emojis, keine Links – alles in gesprochenen Sätzen.' : 'Im Chat höchstens eine kurze Liste, wenn sie wirklich hilft; Emojis höchstens sparsam und nur, wenn es zum Betrieb passt.')
       ."\n• Ehrlich bleiben: Was du nicht weißt, sagst du offen und bietest den nächsten Schritt an (Rückruf, Weiterleitung, E-Mail)."
       ."\n• Zum Schluss freundlich und persönlich verabschieden – ein Satz genügt.";
    return $o;
}
header('Access-Control-Allow-Origin: *'); header('Access-Control-Allow-Headers: Content-Type'); header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){ http_response_code(204); exit; }

$in=json_decode(file_get_contents('php://input'),true); if(!is_array($in)) $in=[];
$lang=substr(preg_replace('/[^a-z]/','',strtolower($in['lang'] ?? ($_GET['lang'] ?? 'de'))),0,2) ?: 'de';
$G=[
 'de'=>['hi'=>'Hallo! Ich bin der ChatHelp-Assistent. Frag mich, was du erledigen willst – ein Rechtsschreiben, einen KI-Agenten für dein Unternehmen, Social-Media-Content oder die API.',
        'chips'=>['Ich will einen Widerspruch schreiben','Welcher Agent passt zu meinem Shop?','Was kosten Tokens?','Ich brauche Instagram-Posts jede Woche','Wie binde ich den Support-Agenten auf meiner Website ein?']],
 'tr'=>['hi'=>'Merhaba! Ben ChatHelp asistanıyım. Ne yapmak istediğini söyle: resmi bir dilekçe, işletmen için bir yapay zekâ agent, sosyal medya içeriği veya API.',
        'chips'=>['Vize başvurusu için belge istiyorum','Mağazam için hangi agent uygun?','Token fiyatları ne?','Her hafta Instagram paylaşımı istiyorum']],
 'en'=>['hi'=>"Hi! I'm the ChatHelp assistant. Tell me what you need – a legal letter, an AI agent for your business, social media content or the API.",
        'chips'=>['I need to write an objection letter','Which agent fits my online shop?','How much do tokens cost?','I want weekly Instagram posts']],
 'fr'=>['hi'=>"Bonjour ! Je suis l'assistant ChatHelp. Dites-moi ce dont vous avez besoin : un courrier juridique, un agent IA, du contenu social ou l'API.",
        'chips'=>['Je veux rédiger une contestation','Quel agent pour ma boutique ?','Combien coûtent les tokens ?']],
 'ru'=>['hi'=>'Здравствуйте! Я ассистент ChatHelp. Скажите, что нужно: юридическое письмо, ИИ-агент для бизнеса, контент для соцсетей или API.',
        'chips'=>['Нужно написать возражение','Какой агент подходит моему магазину?','Сколько стоят токены?']],
 'es'=>['hi'=>'¡Hola! Soy el asistente de ChatHelp. Dime qué necesitas: una carta legal, un agente de IA, contenido para redes o la API.',
        'chips'=>['Quiero redactar una reclamación','¿Qué agente encaja con mi tienda?','¿Cuánto cuestan los tokens?']],
];
$g=$G[$lang] ?? $G['de'];
/* Live-Demo eines Agenten (Startseite): ?agent=agent-phone → Beispiel-Firma + Wissensbasis, echter Agent */
$demo=preg_replace('/[^a-z0-9-]/','',(string)($in['agent'] ?? ($_GET['agent'] ?? '')));
if($demo!=='' && !ch_plan($demo)) $demo='';
/* Server-Stimme nur für echte Demo-Einträge (tts.php prüft CH_DEMO) */
$ttsOn = $demo!=='' && isset(CH_DEMO[$demo]) && ch_tts_provider()!==null;
if($_SERVER['REQUEST_METHOD']==='GET'){
    if($demo!==''){
        $d=ch_demo_cfg($demo); $pl=ch_plan($demo);
        $fill=[]; foreach($d['fillers'] as $f){ $x=implode(' ',ch_speech_sentences($f,'de',!empty($d['du']))); if($x!=='') $fill[]=['text'=>$x,'t'=>ch_tts_sign($demo,$x)]; }
        th_json(200,['demo'=>$demo,'agent'=>$pl['name'],'company'=>$d['company'],'persona'=>$d['persona'],'greeting'=>$d['greeting'],'chips'=>$d['chips'],
            'voice'=>['gender'=>$d['voice']['gender'],'rate'=>$d['voice']['rate'],'pitch'=>$d['voice']['pitch']],
            'speech'=>ch_speech_payload($demo,$d['greeting']),'fillers'=>$fill,'tts'=>$ttsOn,'lang'=>'de']);
    }
    th_json(200,['greeting'=>$g['hi'],'chips'=>$g['chips'],'lang'=>$lang]);
}

/* ---- hız sınırı ---- */
th_bootstrap();
$p=th_db();
$p->exec("CREATE TABLE IF NOT EXISTS concierge_log (id INT AUTO_INCREMENT PRIMARY KEY, ip_hash CHAR(32) NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX(ip_hash,created_at), INDEX(created_at)) ENGINE=InnoDB");
$ip=$_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '0')); $ip=trim(explode(',',$ip)[0]); $h=md5('ch|'.$ip);
$s=$p->prepare("SELECT COUNT(*) FROM concierge_log WHERE ip_hash=? AND created_at > (NOW() - INTERVAL 1 HOUR)"); $s->execute([$h]);
if((int)$s->fetchColumn() >= 30) th_json(429,['error'=>'rate_limited','reply'=>['de'=>'Das war viel auf einmal 🙂 Bitte in einer Stunde erneut – oder melde dich im Studio an (300 Tokens gratis): https://chat-help.com/chat/studio.php','tr'=>'Bir saat sonra tekrar dene ya da Studio\'ya giriş yap (300 token hediye): https://chat-help.com/chat/studio.php 🙂','en'=>'That was a lot at once 🙂 Please try again in an hour or sign in to the Studio (300 free tokens): https://chat-help.com/chat/studio.php'][$lang] ?? 'Bitte später erneut versuchen.']);
$s=$p->query("SELECT COUNT(*) FROM concierge_log WHERE created_at > (NOW() - INTERVAL 1 HOUR)");
if((int)$s->fetchColumn() >= 600) th_json(429,['error'=>'busy','reply'=>'Gerade sehr viel los – bitte gleich noch einmal versuchen.']);
if(random_int(1,50)===1) $p->exec("DELETE FROM concierge_log WHERE created_at < (NOW() - INTERVAL 2 DAY)");

/* ---- mesajlar ---- */
$msgs=[]; foreach((array)($in['messages']??[]) as $m){ $role=($m['role']??'user')==='assistant'?'assistant':'user'; $t=trim((string)($m['content']??'')); if($t!=='') $msgs[]=['role'=>$role,'content'=>mb_substr($t,0,1500)]; }
$msgs=array_slice($msgs,-10);
if(!$msgs || end($msgs)['role']!=='user') th_json(400,['error'=>'messages_required']);

/* ---- Agent-Live-Demo: echter Agent mit Beispiel-Wissensbasis (Plattform-Schlüssel, kein Token-Verbrauch des Besuchers) ---- */
if($demo!==''){
    $pl=ch_plan($demo); $d=ch_demo_cfg($demo); $phone=(($in['channel']??'')==='phone') || $demo==='agent-phone';
    $sys=ch_agent_system($pl,$d,'de').concierge_persona_prompt($d,$phone).($phone?CH_PHONE_RULES:'')
        ."\n\nDEMO-HINWEIS (nur intern): Dies ist eine öffentliche Live-Demo auf chat-help.com mit der Beispiel-Firma „".$d['company']."“. Bleibe vollständig in der Rolle. Wenn jemand fragt, wie er so einen Agenten bekommt oder etwas über ChatHelp wissen will: Ein Satz – Studio auf chat-help.com, eigene Wissensbasis einfügen, in 10 Minuten startklar – dann zurück in die Rolle.";
    /* Termin- und Bestell-Demo arbeiten mit ECHTEN Werkzeugen auf Konto 0 (Salon Luna / Pizzeria Napoli): echte Slot- und Adressprüfung, echte Buchung/Bestellung – keine Benachrichtigungen */
    $tk=null;
    require_once __DIR__.'/agent-tools.php'; ch_heartbeat();
    $DEMO_MODS=['agent-termine'=>['appt'],'agent-bestellung'=>['order'],'agent-taxi'=>['ride'],'agent-hotel'=>['hotel','callback'],'immo-agent'=>['callback'],'agent-phone'=>['callback']];   // Demo-Konto 0: passende echte Werkzeuge je Demo
    if(isset($DEMO_MODS[$demo])){ $only=array_values(array_filter($DEMO_MODS[$demo],fn($k)=>isset(ch_modules()[$k]))); if($only) $tk=ch_toolkit(0,'demo','',$only); }
    if($tk){
        $sys.=$tk['hint']."\nDEMO-MODUS: Kundendaten dürfen Platzhalter sein (z. B. Name „Demo“, Telefon „0170 0000000“), Bestellungen auch außerhalb der Öffnungszeiten annehmen; es wird keine echte Nachricht verschickt – sage, dass im echten Betrieb Bestätigung per E-Mail/WhatsApp/SMS kommt.";
        $answer=th_ai_tools($sys,$msgs,$tk['tools'],$tk['exec'],$phone?320:800,60);
    } else $answer=th_ai($sys,$msgs,$phone?300:700,45);
    if($answer===null){ $r='Die Demo ist gerade nicht erreichbar – bitte gleich noch einmal versuchen.';
        th_json(200,['error'=>'ai_unavailable','reply'=>$r,'demo'=>$demo,'detail'=>th_ai_info()['errors'] ?? [],'speech'=>ch_speech_payload($demo,$r),'tts'=>$ttsOn]); }   // 200: Cloudflare würde 502 durch eigene Fehlerseite ersetzen
    $forward=str_contains($answer,'[WEITERLEITEN]'); $end=str_contains($answer,'[ENDE]');
    $answer=trim(str_replace(['[WEITERLEITEN]','[ENDE]'],'',$answer));
    $p->prepare("INSERT INTO concierge_log(ip_hash) VALUES(?)")->execute([$h]);
    th_json(200,['reply'=>$answer,'source'=>th_ai_info()['provider'] ?? 'ai','demo'=>$demo,'forward'=>$forward,'end'=>$end,'tools'=>array_map(fn($c)=>['name'=>$c['name'],'ok'=>$c['ok'] ?? true],th_ai_info()['tool_calls'] ?? []),
        'speech'=>ch_speech_payload($demo,$answer),'tts'=>$ttsOn]);
}

/* ---- sistem promptu: ürün bilgisi ---- */
$cat=''; foreach(CH_PLANS as $id=>$v){ $cat.="- {$v[0]} ({$v[4]}, {$v[3]}): ".number_format($v[1]/100,2,',','.')." € ".($v[4]==='buy'?'einmalig':'/ Monat').", inkl. ".number_format($v[2],0,',','.')." Tokens; Kauf: https://chat-help.com/chat/tokens-checkout.php?plan=$id\n"; }
$system = <<<SYS
Du bist der freundliche Assistent von ChatHelp (chat-help.com), einer europäischen KI-Plattform. Antworte in der Sprache des Nutzers (Standard: $lang). Kurz (max. 6 Sätze oder eine kompakte Liste), konkret, ohne Marketing-Floskeln. Nenne passende Links.

PRODUKTE
1) ChatHelp Recht (https://chat-help.com/chat/): geprüfte Rechtsschreiben für DE/AT/CH/FR/TR – Widerspruch, Kündigung, Mieterhöhung, Mahnung, Behördenanträge, Visum-Unterlagen (TR). Besonderheit: jedes Dokument wird von zwei unabhängigen KI-Prüfungen gegengelesen, zitiert nur echte Rechtsgrundlagen (§§ mit Quelle), keine erfundenen Paragrafen, aktuelle Fristen, Versand per Post/Einschreiben/Fax direkt aus der App. Einzeldokumente ab 1,99 €, Abos Basic 13,99 €/Monat, Pro 29,99 €/Monat. Keine Rechtsberatung im Einzelfall – bei komplexen Fällen Anwalt empfehlen.
2) KI-Agenten zum Mieten (monatlich kündbar) oder Kaufen (einmalig): fertige Agenten mit eigener Wissensbasis des Kunden, Website-Chat-Widget, Studio-Arbeitsbereich (https://chat-help.com/chat/studio.php). Einrichtung: Kauf → API-Key per E-Mail → Studio → Wissensbasis einfügen → Widget-Code auf die Website.
3) Creator-Pakete: Social-Media-Automatisierung 39 €/Monat, YouTube-Skript & SEO 59 €/Monat, Untertitel/Übersetzung per Tokens (ab 19 € / 200 Min).
4) Tokens: Guthaben für alle Dienste, verfallen nicht: 10.000 = 49 €, 50.000 = 199 €, 200.000 = 690 €, beliebige Menge über den Rechner (https://chat-help.com/#tokens). Richtwerte: Post 5 Tokens, YouTube-Skript 50, Agent-Antwort 6.
5) API (https://chat-help.com/#api): REST, Bearer-Key, alle Dienste; API Pro 249 €/Monat, Enterprise ab 990 €/Monat.

KATALOG (Pakete & Agenten)
$cat

REGELN: Bleibe bei ChatHelp-Themen; bei fremden Themen freundlich zurücklenken. Erfinde keine Preise oder Funktionen, die oben nicht stehen. Wenn jemand ein Rechtsproblem schildert: kurz einordnen (welches Schreiben passt) und zu ChatHelp Recht führen. Wenn jemand ein Unternehmen hat: den passenden Agenten vorschlagen (Name, Preis, was er tut) und Mieten vs. Kaufen erklären. Kontakt: support@chat-help.com.
SYS;

$answer = th_ai($system, $msgs, 550, 45);   // Claude model zinciri → DeepSeek (tokens-lib)
$source = th_ai_info()['provider'] ?? 'rules';
if($answer===null){
    // KI anahtarı yoksa yine de yardımcı ol: kural tabanlı kısa yönlendirme
    $q=mb_strtolower(end($msgs)['content']);
    $answer = (preg_match('/widerspruch|kündig|miet|mahnung|visum|vize|dilekçe|antrag|behörde|recht|anwalt|letter|objection/u',$q))
      ? 'Dafür ist ChatHelp Recht da: geprüfte, versandfertige Schreiben mit echten Rechtsgrundlagen → https://chat-help.com/chat/'
      : (preg_match('/agent|support|shop|kunden|bot|widget|website/u',$q)
        ? 'Passend wäre ein KI-Agent zum Mieten (ab 79 €/Monat, inkl. Tokens & Website-Widget). Agenten ansehen → https://chat-help.com/#agenten'
        : 'Gern! Tokens kaufst du hier: https://chat-help.com/#tokens · Rechtsschreiben: https://chat-help.com/chat/ · Agenten: https://chat-help.com/#agenten · Fragen: support@chat-help.com');
}
$p->prepare("INSERT INTO concierge_log(ip_hash) VALUES(?)")->execute([$h]);
th_json(200,['reply'=>$answer,'source'=>$source]);
