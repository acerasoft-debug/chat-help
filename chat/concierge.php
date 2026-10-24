<?php
/**
 * ChatHelp — Ana sayfa asistanı (herkese açık, konuşabilir yapay zekâ)
 * ====================================================================
 * POST /chat/concierge.php {messages:[{role,content}], lang}  → {reply}
 * GET  /chat/concierge.php?lang=de                             → {greeting, chips[]}
 * Live-Demo eines Agenten (Startseite):
 *   GET  ?agent=<planId>[&sector=<key>] → {demo, sector, agent, company, persona, greeting, chips, voice:{gender,rate,pitch,preset}, voices:[{id,label,gender,desc,…}],
 *                                          speech:[{text,t}], fillers:[{text,t}], script:[[role,text],…], tts, lang}   (sector nur bei agent-phone, sonst null)
 *   GET  ?phone_sectors=1               → {sectors:[{key,label,icon,company,persona,gender,group,preset}]}   (Branchen des Telefon-Agenten, CH_PHONE_SECTORS)
 *   POST {agent, sector?, channel:'phone'|'chat', messages, lang} → {reply, tools, forward, end, …, speech:[{text,t}], tts}
 *   script_speech = {Index der Agenten-Zeile in script: [{text,t}]} – Autoplay spricht damit jede Agenten-Zeile mit der Server-Stimme.
 *   speech/fillers = sprechbare Sätze mit Token (demo|sector|exp|text) für /chat/tts.php (speech-lib.php); tts=true, wenn eine Server-Stimme eingerichtet ist.
 *   Sprache (13, i18n.php): ?lang= bzw. JSON lang → Begrüßung, Schnellwahl, Überbrückungssätze und Stimme der Demo in dieser Sprache (eigene Übersetzung aus
 *   CH_DEMO greetings, sonst die allgemeine Vorlage demo.greet); die Antwort folgt der erkannten Sprache jeder Nachricht (unsicher → gewählte Sprache).
 *   GET ?phone_sectors=1 liefert zusätzlich langs:[{code,name,tag}] für die Sprachauswahl des Telefon-Demo-Agenten.
 *   Tests: CH_TEST_AI_HOOK (Funktionsname, nur in einer Test-Umgebung definiert und nur für Anfragen von 127.0.0.1/::1) ersetzt den KI-Aufruf.
 * Platform anahtarıyla (CLAUDE_KEY / DEEPSEEK_KEY, db.php) çalışır; ziyaretçiden token istemez.
 * Koruma: IP başına 20 mesaj/saat, toplam 400 mesaj/saat (tablo: concierge_log). Yalnız ChatHelp konuları.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';
require_once __DIR__.'/speech-lib.php';
require_once __DIR__.'/demo-l10n.php';   // lokale Demo-Betriebe je Besuchersprache (alle zwölf weiteren Sitesprachen); Deutsch bleibt CH_DEMO

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
/** Demo in einer anderen Sprache ohne eigene Übersetzung (CH_DEMO greetings): allgemeine Begrüßung, Schnellwahl und Überbrückung in $lang, Beispielgespräch nur mit der Begrüßung. */
function concierge_localize(array $d, string $lang): array {
    $req=ch_demo_req_lang(); if($req==='nb' || $req==='nn') $req='no';   // ?lang= → JSON lang → Accept-Language (wie ch_demo_cfg)
    if($req!=='' && $req===(string)($d['lang'] ?? '')) return $d;          // eigene Übersetzung (greetings) schon angewendet, auch außerhalb der 13 (z. B. ar)
    if(!in_array($req,CH_LANG_CODES,true)) return $d;                       // unbekannte Sprache: Original
    $lang=$req; if($lang==='de' || $lang===(string)($d['lang'] ?? 'de')) return $d;
    $p=trim((string)($d['persona'] ?? '')); $co=trim((string)($d['company'] ?? ''));
    $d['greeting']=$p!=='' ? t('demo.greet',$lang,['persona'=>$p,'company'=>$co]) : t('demo.greet0',$lang,['company'=>$co]);
    $d['chips']=(array)t('demo.chips',$lang); $d['fillers']=(array)t('demo.fill',$lang); $d['script']=[['a',$d['greeting']]]; $d['lang']=$lang;
    return $d;
}
/** Demo-Konfiguration in der Besuchersprache: zuerst eine lokale Fassung (demo-l10n.php: eigener Betrieb mit Stadt, Währung, Wissensbasis – setzt lang),
 *  sonst concierge_localize() (allgemeine Begrüßung in der Sprache, deutsche Wissensbasis). Deutsch (Standard) bleibt unverändert. */
function concierge_demo(string $demo, string $sector, string $lang): array {
    $req=ch_demo_req_lang(); if($req==='nb' || $req==='nn') $req='no';
    return concierge_localize(ch_demo_l10n(ch_demo_conf($demo,$sector),$demo,$sector,$req!=='' ? $req : $lang),$lang);
}
/** Demo-Werkzeuge (Konto 0): Taxi → ride, Hotel → hotel, … ; beim Telefon-Agenten je Branche (CH_PHONE_SECTORS tools). */
const CONCIERGE_DEMO_MODS=['agent-termine'=>['appt'],'agent-bestellung'=>['order'],'agent-taxi'=>['ride'],'agent-hotel'=>['hotel','callback'],'immo-agent'=>['callback'],'agent-phone'=>['callback'],'agent-beauty'=>['callback'],'agent-aesthetic'=>['callback'],'agent-hair-tr'=>['callback'],'agent-dental-tr'=>['callback'],'agent-bank'=>['callback'],'agent-behoerde'=>['callback'],'agent-hausverwaltung'=>['callback'],'agent-versicherung'=>['callback'],'agent-autohaus'=>['callback'],'praxis-agent'=>['callback'],'agent-marketing'=>['growth'],'agent-amazon'=>['growth','fba']];   // Amazon-Demo: Rechen-Werkzeuge + Keepa-Produktfinder (Konto 0, Tageslimit FBA_PLATFORM_DAILY_PRODUCTS)
/** Werkzeug-Register laden – in einer Funktion, damit die Schleifenvariablen von agent-tools.php ($f, $p) keine Variablen dieses Skripts (PDO $p) überschreiben. */
function concierge_load_tools(): void { require_once __DIR__.'/agent-tools.php'; }
function concierge_toolkit(string $demo, array $d): ?array {
    concierge_load_tools();
    $want=$demo==='agent-phone' ? (array)($d['tools'] ?? ['callback']) : (CONCIERGE_DEMO_MODS[$demo] ?? []);
    $GLOBALS['ch_current_plan']=$demo;   // growth-lib: Werkzeug-Satz des Demo-Agenten
    $have=ch_modules(); $only=array_values(array_filter($want,fn($k)=>is_string($k) && isset($have[$k])));
    if(!$only) return null;
    $tk=ch_toolkit(0,'demo','',$only);
    if(!$tk['tools']) return null;
    if($demo==='agent-phone' && in_array('appt',$only,true) && !empty($d['services'])) $tk=concierge_appt_wrap($tk,$d);
    return $tk;
}
/**
 * Termin-Kalender der Demo (Konto 0, Salon Luna) für eine andere Branche nutzen (Werkstatt, Tierarzt, Studio …):
 * Werkzeuge sehen nur die Leistungen der Branche; jede Leistung belegt einen Demo-Slot gleicher Dauer; Ergebnisse tragen Firma und Leistung der Branche.
 */
function concierge_appt_wrap(array $tk, array $d): array {
    $svc=[]; foreach(array_values($d['services']) as $i=>$x) $svc[$i+1]=['id'=>$i+1,'name'=>(string)$x['name'],'duration_min'=>(int)$x['duration_min'],'price'=>(string)($x['price'] ?? '')];
    $list=array_values($svc);
    $map=function(int $dur): ?int { $best=null; foreach(appt_services(0) as $x){ $df=abs((int)$x['duration_min']-$dur); if($best===null || $df<$best[0]) $best=[$df,(int)$x['id']]; } return $best[1] ?? null; };
    $co=(string)($d['company'] ?? ''); $addr=(string)($d['address'] ?? '');
    $relabel=function($r, ?array $s) use(&$relabel,$co,$addr,$list,$svc){
        if(!is_array($r)) return $r;
        foreach($r as $k=>$v){
            if($k==='services' && is_array($v)) { $r[$k]=$list; continue; }
            if($k==='company' && is_string($v)) { $r[$k]=$co; continue; }
            if($k==='address' && is_string($v) && $addr!=='') { $r[$k]=$addr; continue; }
            if($k==='service' && is_array($v) && $s) { $r[$k]=['id'=>$s['id'],'name'=>$s['name'],'duration_min'=>$s['duration_min']]; continue; }
            if($k==='service' && is_string($v)){ if($s){ $r[$k]=$s['name']; continue; }
                foreach($svc as $x) if(isset($r['note']) && is_string($r['note']) && str_starts_with($r['note'],$x['name'])){ $r[$k]=$x['name']; break; } continue; }
            if(is_array($v)) $r[$k]=$relabel($v,$s);
        }
        return $r;
    };
    $exec0=$tk['exec'];
    $tk['exec']=function(string $n, array $in) use($exec0,$svc,$list,$map,$relabel): array {
        if($n==='list_services') return ['services'=>array_map(fn($x)=>['id'=>$x['id'],'name'=>$x['name'],'duration_min'=>$x['duration_min'],'price'=>$x['price']],$list)];
        if($n==='get_available_slots' || $n==='book_appointment'){
            $sid=isset($in['service_id']) ? (int)$in['service_id'] : 0;
            if($sid>0 && !isset($svc[$sid])) return ['error'=>'unknown_service','service_id'=>$sid,'hint'=>'Diese Leistung gibt es nicht. Bitte eine der Leistungen (service_id aus services) verwenden.','services'=>$list];
            $s=$sid>0 ? $svc[$sid] : null;
            if($n==='book_appointment' && !$s) return ['error'=>'service_required','hint'=>'Bitte zuerst die Leistung klären und deren service_id angeben.','services'=>$list];
            if($s){ $m=$map($s['duration_min']); if($m) $in['service_id']=$m; else unset($in['service_id']); } else unset($in['service_id']);
            if($n==='book_appointment') $in['note']=trim($s['name'].'. '.(string)($in['note'] ?? ''),' .');
            return $relabel($exec0($n,$in),$s);
        }
        return $relabel($exec0($n,$in),null);
    };
    $txt=implode('; ',array_map(fn($x)=>'#'.$x['id'].' '.$x['name'].' ('.$x['duration_min'].' Min'.($x['price']!=='' ? ', '.$x['price'] : '').')',$list));
    $h=preg_replace('/Leistungen: .*?\. Vorlauf/su','Leistungen: '.str_replace(['\\','$'],['\\\\','\\$'],$txt).'. Vorlauf',$tk['hint'],1);
    $tk['hint']=is_string($h) && $h!==$tk['hint'] ? $h : $tk['hint']."\nLeistungen dieses Betriebs (service_id): ".$txt.'.';
    return $tk;
}
/** Autoplay-Gespräch: sprechbare Sätze mit Token für jede Agenten-Zeile ('a'), Schlüssel = Index in script → dieselbe Server-Stimme wie bei der Begrüßung. */
function concierge_script_speech(string $demo, array $script, string $sector, bool $du): array {
    $o=[]; foreach($script as $i=>$row) if(($row[0] ?? '')==='a' && is_string($row[1] ?? null)){ $sp=ch_speech_payload($demo,$row[1],$sector,$du); if($sp) $o[(string)$i]=$sp; }
    return $o;
}
/** KI-Aufruf der Live-Demo (Test-Haken CH_TEST_AI_HOOK nur lokal). */
function concierge_ai(string $sys, array $msgs, ?array $tk, bool $phone): ?string {
    if(defined('CH_TEST_AI_HOOK') && is_string(CH_TEST_AI_HOOK) && function_exists(CH_TEST_AI_HOOK) && in_array($_SERVER['REMOTE_ADDR'] ?? '',['127.0.0.1','::1'],true))
        return (CH_TEST_AI_HOOK)($sys,$msgs,$tk,$phone);
    if($tk) return th_ai_tools($sys,$msgs,$tk['tools'],$tk['exec'],$phone?320:800,60);
    return th_ai($sys,$msgs,$phone?300:700,45);
}
header('Access-Control-Allow-Origin: *'); header('Access-Control-Allow-Headers: Content-Type'); header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){ http_response_code(204); exit; }

$in=json_decode(file_get_contents('php://input'),true); if(!is_array($in)) $in=[];
$lr=$in['lang'] ?? ($_GET['lang'] ?? 'de'); $lr=is_string($lr) ? $lr : 'de';   // Array/Zahl aus JSON oder ?lang[]= → Standard
$lang=ch_lang_norm((string)preg_replace('/[^a-zA-Z_-]/','',$lr)) ?: 'de';   // 13 Sprachen (nb/nn → no)
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
        'chips'=>['Quiero redactar una reclamación','¿Qué agente encaja con mi tienda?','¿Cuánto cuestan los tokens?']], /* Startseite in 13 Sprachen: Begrüßung des schwebenden Assistenten auch für it/az/nl/sv/no/fi/ja (sonst Englisch) */
 'it'=>['hi'=>'Ciao! Sono l’assistente di ChatHelp. Dimmi cosa ti serve: una lettera formale, un agente IA per la tua attività, contenuti social o l’API.',
        'chips'=>['Quale agente fa per il mio ristorante?','Quanto costano i token?','Come funziona l’agente telefonico?']],
 'az'=>['hi'=>'Salam! Mən ChatHelp köməkçisiyəm. Nə lazım olduğunu de: rəsmi məktub, biznesin üçün süni intellekt agenti, sosial media məzmunu və ya API.',
        'chips'=>['Klinikam üçün hansı agent uyğundur?','Tokenlər neçəyədir?','WhatsApp agenti necə işləyir?']],
 'nl'=>['hi'=>'Hallo! Ik ben de ChatHelp-assistent. Vertel wat je nodig hebt: een formele brief, een AI-agent voor je bedrijf, social-mediacontent of de API.',
        'chips'=>['Welke agent past bij mijn webshop?','Wat kosten tokens?','Hoe werkt de telefoonagent?']],
 'sv'=>['hi'=>'Hej! Jag är ChatHelp-assistenten. Berätta vad du behöver: ett formellt brev, en AI-agent för ditt företag, innehåll för sociala medier eller API:t.',
        'chips'=>['Vilken agent passar min webbutik?','Vad kostar tokens?','Hur fungerar telefonagenten?']],
 'no'=>['hi'=>'Hei! Jeg er ChatHelp-assistenten. Si hva du trenger: et formelt brev, en AI-agent for bedriften din, innhold til sosiale medier eller API-et.',
        'chips'=>['Hvilken agent passer for nettbutikken min?','Hva koster tokens?','Hvordan fungerer telefonagenten?']],
 'fi'=>['hi'=>'Hei! Olen ChatHelp-avustaja. Kerro, mitä tarvitset: virallisen kirjeen, tekoälyagentin yrityksellesi, somesisältöä tai API:n.',
        'chips'=>['Mikä agentti sopii verkkokauppaani?','Mitä tokenit maksavat?','Miten puhelinagentti toimii?']],
 'ja'=>['hi'=>'こんにちは！ChatHelpアシスタントです。正式な文書、事業向けAIエージェント、SNSコンテンツ、APIなど、ご用件をお聞かせください。',
        'chips'=>['お店に合うエージェントは？','トークンの料金は？','電話エージェントの仕組みは？']],
];
$g=$G[$lang] ?? $G['en'];
/* Live-Demo eines Agenten (Startseite): ?agent=agent-phone[&sector=bank] → Beispiel-Firma + Wissensbasis, echter Agent */
$ar=$in['agent'] ?? ($_GET['agent'] ?? ''); $demo=is_string($ar) ? (string)preg_replace('/[^a-z0-9-]/','',$ar) : '';
if($demo!=='' && !ch_plan($demo)) $demo='';
$sector=$demo!=='' ? ch_demo_sector($demo,$in['sector'] ?? ($_GET['sector'] ?? '')) : '';   // nur agent-phone; unbekannt → zahnarzt
/* Server-Stimme nur für echte Demo-Einträge (tts.php prüft CH_DEMO) */
$ttsOn = $demo!=='' && isset(CH_DEMO[$demo]) && ch_tts_provider()!==null;
if($_SERVER['REQUEST_METHOD']==='GET'){
    if(isset($_GET['phone_sectors'])){
        $o=[]; foreach(array_keys(CH_PHONE_SECTORS) as $k){ $c=ch_phone_sector_cfg($k);
            $o[]=['key'=>$k,'label'=>$c['label'],'icon'=>$c['icon'],'company'=>$c['company'],'persona'=>$c['persona'],'gender'=>$c['voice']['gender'],'group'=>$c['group'],'preset'=>$c['voice']['preset']]; }
        th_json(200,['sectors'=>$o,'langs'=>array_map(fn($l)=>['code'=>$l,'name'=>CH_LANGS[$l]['native'],'tag'=>CH_LANGS[$l]['tag']],CH_LANG_CODES)]);
    }
    if($demo!==''){
        $d=concierge_demo($demo,$sector,$lang); $pl=ch_plan($demo); $du=!empty($d['du']); $dl=(string)($d['lang'] ?? 'de');
        $fill=[]; foreach($d['fillers'] as $f){ $x=implode(' ',ch_speech_sentences($f,$dl,$du)); if($x!=='') $fill[]=['text'=>$x,'t'=>ch_tts_sign($demo,$x,$sector)]; }
        $out=['demo'=>$demo,'sector'=>$sector!=='' ? $sector : null,'agent'=>$pl['name'],'company'=>$d['company'],'persona'=>$d['persona'],'greeting'=>$d['greeting'],'chips'=>$d['chips'],
            'voice'=>['gender'=>$d['voice']['gender'],'rate'=>$d['voice']['rate'],'pitch'=>$d['voice']['pitch'],'preset'=>$d['voice']['preset']],'voices'=>ch_tts_voices_public(),
            'speech'=>ch_speech_payload($demo,$d['greeting'],$sector,$du),'fillers'=>$fill,'script'=>$d['script'],'script_speech'=>(object)concierge_script_speech($demo,$d['script'],$sector,$du),'tts'=>$ttsOn,'lang'=>$dl,
            'langs'=>array_map(fn($l)=>['code'=>$l,'name'=>CH_LANGS[$l]['native']],CH_LANG_CODES)];
        if($sector!=='') $out+=['label'=>$d['label'],'icon'=>$d['icon'],'group'=>$d['group']];
        th_json(200,$out);
    }
    th_json(200,['greeting'=>$g['hi'],'chips'=>$g['chips'],'lang'=>$lang]);
}

/* ---- hız sınırı ---- */
th_bootstrap();
$p=th_db();
$p->exec("CREATE TABLE IF NOT EXISTS concierge_log (id INT AUTO_INCREMENT PRIMARY KEY, ip_hash CHAR(32) NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX(ip_hash,created_at), INDEX(created_at)) ENGINE=InnoDB");
$ip=ch_rate_ip(); $h=md5('ch|'.$ip);   // weitergeleitete Header nur von Cloudflare/lokalen Proxys (speech-lib)
$s=$p->prepare("SELECT COUNT(*) FROM concierge_log WHERE ip_hash=? AND created_at > (NOW() - INTERVAL 1 HOUR)"); $s->execute([$h]);
if((int)$s->fetchColumn() >= 30) th_json(429,['error'=>'rate_limited','reply'=>['de'=>'Das war viel auf einmal 🙂 Bitte in einer Stunde erneut – oder melde dich im Studio an (300 Tokens gratis): https://chat-help.com/chat/studio.php','tr'=>'Bir saat sonra tekrar dene ya da Studio\'ya giriş yap (300 token hediye): https://chat-help.com/chat/studio.php 🙂','en'=>'That was a lot at once 🙂 Please try again in an hour or sign in to the Studio (300 free tokens): https://chat-help.com/chat/studio.php'][$lang] ?? 'Bitte später erneut versuchen.']);
$s=$p->query("SELECT COUNT(*) FROM concierge_log WHERE created_at > (NOW() - INTERVAL 1 HOUR)");
if((int)$s->fetchColumn() >= 600) th_json(429,['error'=>'busy','reply'=>'Gerade sehr viel los – bitte gleich noch einmal versuchen.']);
if(random_int(1,50)===1) $p->exec("DELETE FROM concierge_log WHERE created_at < (NOW() - INTERVAL 2 DAY)");

/* ---- mesajlar ---- */
$msgs=[]; foreach((is_array($in['messages'] ?? null) ? $in['messages'] : []) as $m){ if(!is_array($m)) continue; $role=($m['role']??'user')==='assistant'?'assistant':'user';
    $c=$m['content'] ?? null; $t=is_string($c) ? trim($c) : ''; if($t!=='' && mb_check_encoding($t,'UTF-8')) $msgs[]=['role'=>$role,'content'=>mb_substr($t,0,1500)]; }
$msgs=array_slice($msgs,-10);
if(!$msgs || end($msgs)['role']!=='user') th_json(400,['error'=>'messages_required']);

/* ---- Agent-Live-Demo: echter Agent mit Beispiel-Wissensbasis (Plattform-Schlüssel, kein Token-Verbrauch des Besuchers) ---- */
if($demo!==''){
    $pl=ch_plan($demo); $d=concierge_demo($demo,$sector,$lang); $phone=(($in['channel']??'')==='phone') || $demo==='agent-phone'; $du=!empty($d['du']);
    if($sector!==''){   // Telefon-Agent einer Branche: Grundrolle + Fachablauf der Branche zusätzlich zu den Telefon-Regeln
        if(!empty($d['sector_sys']) && isset(CH_SECTORS[$d['sector_sys']])) $pl['sector']=$d['sector_sys'];
        $rf=array_values(array_filter((array)($d['rules_from'] ?? []),fn($k)=>is_string($k) && isset(CH_AGENT_RULES[$k])));   // ein Schlüssel oder mehrere (z. B. Werkstatt: agent-autohaus + agent-termine)
        if($rf) $pl['rules'].="\n\nFACHABLAUF ".mb_strtoupper((string)$d['label']).":\n".implode("\n",array_map(fn($k)=>CH_AGENT_RULES[$k],$rf))."\nStehen dafür Werkzeuge bereit, buchst bzw. trägst du verbindlich über die Werkzeuge ein, statt nur zu notieren.";
    }
    /* Antwortsprache: diese Nachricht (sicher erkannt) → frühere Nachrichten des Besuchers → gewählte Sprache (?lang=) → Deutsch */
    $prevL=''; foreach(array_slice($msgs,0,-1) as $m){ if($m['role']!=='user') continue; $ps=false; $pd=ch_detect_lang($m['content'],'',$ps); if($ps) $prevL=$pd; }
    $replyLang=ch_reply_lang(['lang'=>'de','langs'=>''],end($msgs)['content'],$prevL,(string)($d['lang'] ?? $lang));
    $sys=ch_agent_system($pl,array_merge($d,['lang'=>'de','langs'=>'']),$replyLang,true).concierge_persona_prompt($d,$phone).($phone?CH_PHONE_RULES:'')
        ."\n\nDEMO-HINWEIS (nur intern): Dies ist eine öffentliche Live-Demo auf chat-help.com mit der Beispiel-Firma „".$d['company']."“".($sector!=='' ? ' (Branche: '.$d['label'].')' : '').". Bleibe vollständig in der Rolle. Wenn jemand fragt, wie er so einen Agenten bekommt oder etwas über ChatHelp wissen will: Ein Satz – Studio auf chat-help.com, eigene Wissensbasis einfügen, in 10 Minuten startklar – dann zurück in die Rolle.";
    /* Demos arbeiten mit ECHTEN Werkzeugen auf Konto 0 (Termine, Bestellungen, Taxi, Hotel, Rückruf): echte Slot- und Adressprüfung, echte Buchung – keine Benachrichtigungen */
    concierge_load_tools(); ch_heartbeat();
    $tk=concierge_toolkit($demo,$d);
    if($tk) $sys.=$tk['hint']."\nDEMO-MODUS: Kundendaten dürfen Platzhalter sein (z. B. Name „Demo“, Telefon „0170 0000000“), Bestellungen auch außerhalb der Öffnungszeiten annehmen; es wird keine echte Nachricht verschickt – sage, dass im echten Betrieb Bestätigung per E-Mail/WhatsApp/SMS kommt.";
    $answer=concierge_ai($sys,$msgs,$tk,$phone);
    if($answer===null){ $r=t('demo.down',$replyLang);
        th_json(200,['error'=>'ai_unavailable','reply'=>$r,'demo'=>$demo,'sector'=>$sector!=='' ? $sector : null,'detail'=>in_array($_SERVER['REMOTE_ADDR'] ?? '',['127.0.0.1','::1'],true) ? (th_ai_info()['errors'] ?? []) : [],'speech'=>ch_speech_payload($demo,$r,$sector,$du),'tts'=>$ttsOn]); }   // 200: Cloudflare würde 502 durch eigene Fehlerseite ersetzen
    $forward=str_contains($answer,'[WEITERLEITEN]'); $end=str_contains($answer,'[ENDE]');
    $answer=trim(str_replace(['[WEITERLEITEN]','[ENDE]'],'',$answer));
    $p->prepare("INSERT INTO concierge_log(ip_hash) VALUES(?)")->execute([$h]);
    th_json(200,['reply'=>$answer,'source'=>th_ai_info()['provider'] ?? 'ai','demo'=>$demo,'sector'=>$sector!=='' ? $sector : null,'forward'=>$forward,'end'=>$end,'tools'=>array_map(fn($c)=>['name'=>$c['name'],'ok'=>$c['ok'] ?? true],th_ai_info()['tool_calls'] ?? []),
        'speech'=>ch_speech_payload($demo,$answer,$sector,$du),'tts'=>$ttsOn,'lang'=>$replyLang]);
}

/* ---- sistem promptu: ürün bilgisi ---- */
$G_LANGNAME=ch_lang_name($lang,'en').' ('.ch_lang_name($lang,'native').')';
$cat=''; foreach(CH_PLANS as $id=>$v){ $cat.="- {$v[0]} ({$v[4]}, {$v[3]}): ".number_format($v[1]/100,2,',','.')." € ".($v[4]==='buy'?'einmalig':'/ Monat').", inkl. ".number_format($v[2],0,',','.')." Tokens; Kauf: https://chat-help.com/chat/tokens-checkout.php?plan=$id\n"; }
$system = <<<SYS
Du bist der freundliche Assistent von ChatHelp (chat-help.com), einer europäischen KI-Plattform. Antworte in der Sprache des Nutzers (Standard: {$G_LANGNAME}). Kurz (max. 6 Sätze oder eine kompakte Liste), konkret, ohne Marketing-Floskeln. Nenne passende Links.

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
