<?php
/**
 * ChatHelp — Einrichtung nach Kauf/Miete (Onboarding)
 * ===================================================
 * Nach jeder Zahlung (Stripe checkout.session.completed, type=plan) legt tokens-webhook.php über onb_on_payment() einen Einrichtungs-Datensatz an
 * (genau einer je Checkout-Session, Webhook-Wiederholungen erzeugen weder Datensatz noch Mail doppelt) und schickt sofort die Einrichtungs-Anleitung
 * (E-Mail mit „Einrichtung starten“ + App-Download) und eine Push-Meldung.
 *
 * Im Studio (Panel „einrichtung“) beantwortet der Kunde einen Fragenkatalog, der aus dem gekauften Plan abgeleitet wird (Basis-Agent, Kanäle, Module):
 *   Betrieb (Name, Website-Import, Adresse, Telefon, Öffnungszeiten) · Kanäle (Widget, Telefon: eigene Twilio-Nummer oder ChatHelp-Nummer, WhatsApp: Meta/Twilio)
 *   · Module (Termine: Leistungen; Bestellungen: Karte, Liefergebiete, Mindestbestellwert; Taxi: Tarif, Stände, Fahrer; Hotel: Zimmer; Rückruf: Zeiten)
 *   · Sprachen, Anrede, Tonalität, „Was soll dein Agent nie tun?“, FAQ.   Private Pläne: kurzer Privat-Katalog.
 * Bereits vorhandene Einstellungen werden vorbelegt (known=true → der Assistent überspringt die Frage).
 * Jede Antwort wird serverseitig geprüft und sofort gespeichert (Geheimnisse wie Twilio-Token/WhatsApp-Token gehen direkt in die verschlüsselten
 * Verbindungen bzw. die WhatsApp-Einstellungen, im Antwortspeicher steht nur ••••1234). onb_finish() überträgt alles in die echten Modul-Einstellungen
 * (appt_*, ord_*, ride_*, hotel_*, cb_*, th_agent_cfg_save, ch_agent_langs_save, train_rules_save, train_faq_save) und sendet die persönliche Anleitung
 * (erledigt / offen, Meta-Verifizierung, Twilio-Nummer, Widget-Code mit Schlüssel, App-Installation Android/iOS/PWA; HTML + Klartext).
 *
 * Studio-REST (über ch_module_api, CH_MODULE_LIBS['onboarding']):
 *   GET  /v1/onboarding/state[?id=|?plan=]   → Einrichtung (legt sie für ein aktives Paket bei Bedarf an), Fragen mit Vorbelegung, Fortschritt, Checkliste, Links
 *   POST /v1/onboarding/answer  {id, step, value}  → prüfen + speichern (422 mit Hinweis bei ungültigen Angaben)
 *   POST /v1/onboarding/finish  {id}               → in die Module übernehmen, abschließen, Anleitung (einmalig) senden
 *   POST /v1/onboarding/resend-guide {id}          → Anleitung erneut senden (max. 5 je Stunde)
 * Test-Naht: $GLOBALS['CH_MAIL_TAP'] = callable(to, subject, html, text) bekommt jede Einrichtungs-Mail (zusätzlich zu th_mail).
 */
require_once __DIR__.'/agent-tools.php';

const ONB_SITE = 'https://chat-help.com';
const ONB_APP_URL = 'https://chat-help.com/chat/app.php';
const ONB_START_URL = 'https://chat-help.com/chat/studio.php#einrichtung';
/** Basis-Agent → Fachmodule (wie die Studio-Navigation MOD_FOR). */
const ONB_MODULES = [
  'appt'=>['agent-termine','agent-beauty','agent-aesthetic','praxis-agent','praxis-termine','agent-autohaus'],
  'order'=>['agent-bestellung','gastro-agent'],
  'ride'=>['agent-taxi'],
  'hotel'=>['agent-hotel'],
  'callback'=>['agent-phone','immo-agent','agent-hausverwaltung','agent-versicherung','agent-kanzlei','agent-steuer','agent-inkasso','agent-bank','agent-behoerde',
    'agent-aesthetic','agent-hair-tr','agent-dental-tr','praxis-agent','agent-autohaus','agent-sales'],
];
/** Fachfrage „Angebot & Zuständigkeiten“ je Basis-Agent: [Titel, Erklärung, Beispiel]. */
const ONB_FOCUS = [
  'agent-hausverwaltung'=>['Notdienst & Zuständigkeiten','Wen soll dein Agent bei Heizungsausfall, Wasserschaden oder Schlüsselverlust nennen? Und wer ist wofür zuständig?','Heizungsausfall: Wärme Service GmbH, 0221 123456 (24 h) · Wasserschaden: Rohrblitz, 0221 654321 · Nebenkostenabrechnung: Frau Weber, Di + Do 9–12 Uhr'],
  'agent-kanzlei'=>['Rechtsgebiete & Erstberatung','Welche Rechtsgebiete betreust du, was kostet die Erstberatung und welche Unterlagen sollen Mandanten mitbringen?','Arbeitsrecht, Mietrecht, Familienrecht · Erstberatung 30 Min. 190 € zzgl. USt. · bitte Kündigung/Vertrag mitbringen'],
  'agent-steuer'=>['Leistungen & Unterlagen','Welche Leistungen bietest du an und welche Unterlagen brauchst du von neuen Mandanten?','Einkommensteuer, Lohnbuchhaltung, Jahresabschluss · Unterlagen bis 31.05. über das Mandantenportal'],
  'agent-inkasso'=>['Ablauf & Zahlungswege','Wie läuft eine Forderung bei dir ab und wie können Schuldner zahlen oder Raten vereinbaren?','Ratenzahlung ab 25 €/Monat möglich · Überweisung auf Treuhandkonto DE12 … · Rückfragen Mo–Fr 9–16 Uhr'],
  'agent-versicherung'=>['Sparten & Schadenmeldung','Welche Versicherungen betreust du und wie meldet man einen Schaden?','Kfz, Hausrat, Haftpflicht, BU · Schadenmeldung per Foto an schaden@… oder telefonisch'],
  'agent-bank'=>['Produkte & Servicezeiten','Welche Anliegen soll der Agent beantworten (Konto, Karte sperren, Kredit) und wohin verweist er bei Notfällen?','Karte sperren: Sperr-Notruf 116 116 · Kontoeröffnung online · Baufinanzierung nur mit Termin'],
  'agent-behoerde'=>['Leistungen & Unterlagen','Welche Dienstleistungen bietet deine Stelle an und welche Unterlagen braucht man?','Personalausweis: Termin + biometrisches Foto + alter Ausweis · Meldebescheinigung online'],
  'agent-autohaus'=>['Werkstatt & Verkauf','Was sollen Kunden über Werkstatt, Probefahrten und Ersatzwagen wissen?','Inspektion nach Herstellervorgabe · Ersatzwagen 19 €/Tag · Probefahrt mit Führerschein'],
  'praxis-agent'=>['Praxis-Hinweise','Was sollen Patientinnen und Patienten wissen (Rezepte, Überweisungen, Notfälle)?','Rezeptbestellung per Telefon bis 11 Uhr · Notfall: 112 bzw. ärztlicher Bereitschaftsdienst 116 117'],
  'agent-hair-tr'=>['Behandlung & Ablauf','Was sollen Interessenten über Methode, Paketpreis, Transfer und Hotel wissen?','FUE/DHI · Paket inkl. Hotel 2 Nächte + VIP-Transfer · kostenlose Haaranalyse per Foto'],
  'agent-dental-tr'=>['Behandlung & Ablauf','Was sollen Interessenten über Behandlungen, Preise, Aufenthaltsdauer und Garantie wissen?','Veneers ab 190 €/Zahn · Implantat inkl. Krone ab 650 € · 5–7 Tage Aufenthalt · 10 Jahre Garantie'],
  'agent-aesthetic'=>['Behandlungen & Beratung','Welche Behandlungen bietest du an und was gilt vorab (Beratung, Kontraindikationen)?','Hyaluron ab 290 € · Botox ab 250 € · immer erst ärztliche Beratung'],
];
const ONB_FOCUS_DEFAULT = ['Was sollen Kunden über dein Angebot wissen?','Leistungen, Preise, Abläufe, Zuständigkeiten – in Stichworten genügt. Das ist das Wissen, mit dem dein Agent antwortet.','Was wir anbieten, was es kostet, wie es abläuft und an wen man sich wendet'];
const ONB_PRIVATE_FOCUS = [
  'privat-behoerde'=>'Briefe vom Amt verstehen, Anträge ausfüllen, Fristen im Blick behalten',
  'privat-bank'=>'Kontoauszüge verstehen, Verträge vergleichen, Kündigungen schreiben',
  'privat-reise'=>'Reisen planen, Visa und Einreise prüfen, Unterlagen vorbereiten',
  'privat-anruf'=>'Termine beim Arzt, Friseur oder Handwerker für mich anfragen',
  'privat-assistent'=>'Briefe und E-Mails formulieren, Termine organisieren, Fragen im Alltag',
];
/** Vorschläge (Ein-Tipp-Zeilen) für Leistungen / Karte / Zimmer. */
const ONB_PRESETS = [
  'agent-termine'=>[['name'=>'Damenhaarschnitt','duration_min'=>60,'price_cents'=>5900],['name'=>'Herrenhaarschnitt','duration_min'=>30,'price_cents'=>3200],['name'=>'Färben','duration_min'=>90,'price_cents'=>7900]],
  'agent-beauty'=>[['name'=>'Maniküre','duration_min'=>45,'price_cents'=>3500],['name'=>'Gesichtsbehandlung','duration_min'=>60,'price_cents'=>6900],['name'=>'Wimpernlifting','duration_min'=>60,'price_cents'=>5500]],
  'agent-aesthetic'=>[['name'=>'Beratungsgespräch','duration_min'=>30,'price_cents'=>0],['name'=>'Hyaluron Lippen','duration_min'=>45,'price_cents'=>29000]],
  'praxis-agent'=>[['name'=>'Erstuntersuchung','duration_min'=>30,'price_cents'=>0],['name'=>'Kontrolltermin','duration_min'=>15,'price_cents'=>0],['name'=>'Impfung','duration_min'=>10,'price_cents'=>0]],
  'praxis-termine'=>[['name'=>'Erstuntersuchung','duration_min'=>30,'price_cents'=>0],['name'=>'Kontrolltermin','duration_min'=>15,'price_cents'=>0]],
  'agent-autohaus'=>[['name'=>'Inspektion','duration_min'=>120,'price_cents'=>0],['name'=>'Reifenwechsel','duration_min'=>30,'price_cents'=>3900],['name'=>'Probefahrt','duration_min'=>45,'price_cents'=>0]],
  'menu'=>[['category'=>'Pizza','name'=>'Margherita','price_cents'=>850,'description'=>'Tomate, Mozzarella, Basilikum'],['category'=>'Pizza','name'=>'Salami','price_cents'=>990,'description'=>''],['category'=>'Getränke','name'=>'Cola 0,33 l','price_cents'=>250,'description'=>'']],
  'rooms'=>[['name'=>'Einzelzimmer','capacity'=>1,'units'=>4,'price_cents'=>8900],['name'=>'Doppelzimmer','capacity'=>2,'units'=>8,'price_cents'=>12900],['name'=>'Suite','capacity'=>4,'units'=>1,'price_cents'=>22900]],
];
const ONB_NEVER = ['Preise nennen, die nicht in der Wissensbasis stehen','Rechtliche, medizinische oder steuerliche Beratung geben','Zahlungs- oder Kartendaten abfragen',
  'Termine oder Lieferungen außerhalb der Öffnungszeiten zusagen','Über Mitbewerber sprechen','Rabatte oder Gutscheine versprechen'];
const ONB_TONES = ['freundlich-professionell'=>'Freundlich & professionell','seriös-zugewandt'=>'Seriös & zugewandt','sachlich-respektvoll'=>'Sachlich & respektvoll',
  'locker-modern'=>'Locker & modern','einfühlsam-präzise'=>'Einfühlsam & präzise','kompetent-ehrlich'=>'Kompetent & ehrlich'];
const ONB_WD = ['So','Mo','Di','Mi','Do','Fr','Sa'];

/* ======================================================================= Tabelle */
function onb_bootstrap(): void {
    static $done=false; if($done) return; $done=true;
    th_db()->exec("CREATE TABLE IF NOT EXISTS onboardings (
        id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, plan VARCHAR(40) NOT NULL, base VARCHAR(40) NOT NULL, checkout_id VARCHAR(120) NOT NULL,
        status VARCHAR(12) NOT NULL DEFAULT 'open', answers MEDIUMTEXT NULL, result MEDIUMTEXT NULL, meta TEXT NULL,
        guide_sent_at DATETIME NULL, guide_count INT NOT NULL DEFAULT 0, done_mail_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL, finished_at DATETIME NULL,
        UNIQUE KEY uq_checkout (checkout_id), INDEX(account_id, status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function onb_decode(array $r): array {
    foreach(['answers','result','meta'] as $k){ $v=json_decode((string)($r[$k] ?? ''),true); $r[$k]=is_array($v) ? $v : []; }
    foreach(['id','account_id','guide_count'] as $k) $r[$k]=(int)$r[$k];
    return $r;
}
function onb_get(int $aid, int $id): ?array { onb_bootstrap(); $s=th_db()->prepare("SELECT * FROM onboardings WHERE id=? AND account_id=?"); $s->execute([$id,$aid]); $r=$s->fetch(PDO::FETCH_ASSOC); return $r ? onb_decode($r) : null; }
function onb_by_checkout(string $cid): ?array { onb_bootstrap(); $s=th_db()->prepare("SELECT * FROM onboardings WHERE checkout_id=?"); $s->execute([$cid]); $r=$s->fetch(PDO::FETCH_ASSOC); return $r ? onb_decode($r) : null; }
function onb_list(int $aid): array { onb_bootstrap(); $s=th_db()->prepare("SELECT * FROM onboardings WHERE account_id=? ORDER BY (status='open') DESC, id DESC LIMIT 20"); $s->execute([$aid]); return array_map('onb_decode',$s->fetchAll(PDO::FETCH_ASSOC)); }
function onb_find(int $aid, string $base): ?array { onb_bootstrap(); $s=th_db()->prepare("SELECT * FROM onboardings WHERE account_id=? AND base=? ORDER BY id DESC LIMIT 1"); $s->execute([$aid,$base]); $r=$s->fetch(PDO::FETCH_ASSOC); return $r ? onb_decode($r) : null; }
function onb_save(array $o, array $fields): void {
    $set=[]; $vals=[]; foreach($fields as $k){ $set[]="$k=?"; $vals[]=in_array($k,['answers','result','meta'],true) ? json_encode($o[$k],JSON_UNESCAPED_UNICODE) : $o[$k]; }
    $vals[]=$o['id']; th_db()->prepare("UPDATE onboardings SET ".implode(',',$set).", updated_at=NOW() WHERE id=?")->execute($vals);
}
/** Neue Einrichtung für (Konto, Plan, Checkout); vorhandene bleibt unverändert. */
function onb_create(int $aid, string $planId, string $checkoutId): ?array {
    $pl=ch_plan($planId); if(!$pl || $aid<=0 || $checkoutId==='') return null; onb_bootstrap();
    th_db()->prepare("INSERT IGNORE INTO onboardings(account_id,plan,base,checkout_id,status,answers,meta) VALUES(?,?,?,?, 'open','{}','{}')")->execute([$aid,$pl['id'],(string)$pl['base'],mb_substr($checkoutId,0,120)]);
    $o=onb_by_checkout(mb_substr($checkoutId,0,120)); return ($o && $o['account_id']===$aid) ? $o : null;
}

/* ======================================================================= Profil + Fragenkatalog */
function onb_profile(string $planId): array {
    $pl=ch_plan($planId) ?? ['id'=>$planId,'base'=>$planId,'kind'=>'abo','sector'=>'','name'=>$planId]; $base=(string)$pl['base'];
    $bp=ch_plan($base) ?? $pl; $mods=[];
    foreach(ONB_MODULES as $m=>$ids) if(in_array($base,$ids,true)) $mods[]=$m;
    $agent=(CH_PLANS[$base][4] ?? '')==='agent';
    return ['plan'=>$pl['id'],'base'=>$base,'kind'=>(string)$pl['kind'],'name'=>(string)$pl['name'],'base_name'=>trim((string)preg_replace('/\s*\(.*\)\s*$/u','',(string)$bp['name'])),
        'private'=>($bp['sector'] ?? '')==='privat','agent'=>$agent,'mods'=>$mods,'channels'=>array_values(array_intersect(ch_plan_channels($base),['web','phone','whatsapp']))];
}
function onb_step(string $id, string $type, string $group, string $title, array $o=[]): array { return ['id'=>$id,'type'=>$type,'group'=>$group,'title'=>$title]+$o; }
const ONB_COLS_SERVICES = [['k'=>'name','l'=>'Leistung','t'=>'text','req'=>true,'max'=>120,'ph'=>'z. B. Damenhaarschnitt','w'=>3],['k'=>'duration_min','l'=>'Dauer','t'=>'int','min'=>5,'max'=>600,'def'=>30,'suffix'=>'Min.','w'=>1],['k'=>'price_cents','l'=>'Preis','t'=>'money','suffix'=>'€','w'=>1]];
const ONB_COLS_MENU = [['k'=>'name','l'=>'Gericht','t'=>'text','req'=>true,'max'=>120,'ph'=>'z. B. Margherita','w'=>2],['k'=>'category','l'=>'Kategorie','t'=>'text','max'=>60,'ph'=>'Pizza','w'=>1],['k'=>'price_cents','l'=>'Preis','t'=>'money','suffix'=>'€','w'=>1],['k'=>'description','l'=>'Beschreibung','t'=>'text','max'=>300,'ph'=>'Zutaten, Allergene','w'=>5]];
const ONB_COLS_ZONES = [['k'=>'plz','l'=>'Postleitzahlen','t'=>'plz','req'=>true,'ph'=>'50667, 50668','w'=>2],['k'=>'name','l'=>'Name','t'=>'text','max'=>60,'ph'=>'Innenstadt','w'=>1],['k'=>'fee_cents','l'=>'Liefergebühr','t'=>'money','suffix'=>'€','w'=>1],['k'=>'min_cents','l'=>'Mindestwert','t'=>'money','suffix'=>'€','w'=>1]];
const ONB_COLS_STANDS = [['k'=>'name','l'=>'Taxistand','t'=>'text','req'=>true,'max'=>80,'ph'=>'Hauptbahnhof','w'=>2],['k'=>'address','l'=>'Adresse','t'=>'text','max'=>200,'ph'=>'Trankgasse 11, 50667 Köln','w'=>3]];
const ONB_COLS_DRIVERS = [['k'=>'name','l'=>'Fahrer/in','t'=>'text','req'=>true,'max'=>80,'ph'=>'Max Mustermann','w'=>2],['k'=>'phone','l'=>'Handy','t'=>'tel','ph'=>'0170 1234567','w'=>2],['k'=>'plate','l'=>'Kennzeichen','t'=>'text','max'=>20,'ph'=>'K-TX 123','w'=>1]];
const ONB_COLS_ROOMS = [['k'=>'name','l'=>'Zimmerkategorie','t'=>'text','req'=>true,'max'=>120,'ph'=>'Doppelzimmer','w'=>2],['k'=>'capacity','l'=>'Personen','t'=>'int','min'=>1,'max'=>20,'def'=>2,'w'=>1],['k'=>'units','l'=>'Anzahl','t'=>'int','min'=>1,'max'=>999,'def'=>1,'w'=>1],['k'=>'price_cents','l'=>'Preis/Nacht','t'=>'money','suffix'=>'€','w'=>1]];
const ONB_COLS_FAQ = [['k'=>'question','l'=>'Frage','t'=>'text','req'=>true,'max'=>500,'ph'=>'Gibt es Parkplätze?','w'=>5],['k'=>'answer','l'=>'Antwort','t'=>'textarea','req'=>true,'max'=>2000,'ph'=>'Ja, direkt hinter dem Haus – kostenlos.','w'=>5]];

function onb_catalog(array $P): array {
    $S=[]; $b=$P['base']; $m=$P['mods']; $ch=$P['channels'];
    $langs=onb_step('langs','langs','Agent','In welchen Sprachen soll dein '.($P['private'] ? 'Assistent' : 'Agent').' antworten?',['sub'=>'Die Standardsprache nutzt er zuerst. Schreibt oder spricht jemand in einer der erlaubten Sprachen, antwortet er automatisch in dieser Sprache.']);
    $anrede=onb_step('anrede','choice','Agent',$P['private'] ? 'Wie soll dein Assistent mit dir sprechen?' : 'Duzt oder siezt dein Agent die Kunden?',['options'=>[['v'=>'sie','l'=>'Sie','d'=>'„Wie kann ich Ihnen helfen?“'],['v'=>'du','l'=>'Du','d'=>'„Wie kann ich dir helfen?“']],'auto'=>true]);
    $never=onb_step('never','multi','Agent','Was soll dein '.($P['private'] ? 'Assistent' : 'Agent').' nie tun?',['sub'=>'Tippe an, was gilt – oder schreib eigene Regeln dazu. Der Agent hält sich in jedem Kanal daran.','options'=>array_map(fn($x)=>['v'=>$x,'l'=>$x],$P['private'] ? ['Ohne meine Zustimmung etwas zusagen oder bestellen','Meine Daten an Dritte weitergeben','Rechtliche oder medizinische Beratung geben'] : ONB_NEVER),'free'=>true,'free_ph'=>'Eigene Regel, z. B. „Keine Preise am Telefon“']);
    if($P['private']){
        $S[]=onb_step('p_name','text','Über dich','Wie dürfen wir dich nennen?',['sub'=>'Dein Assistent spricht dich so an – und nennt den Namen, wenn er für dich schreibt oder anruft.','ph'=>'z. B. Anna Schmidt','req'=>true,'max'=>120,'auto_complete'=>'name']);
        $S[]=onb_step('p_focus','textarea','Über dich','Wobei soll dein Assistent dir vor allem helfen?',['sub'=>'Ein, zwei Sätze genügen – so weiß er, worauf er achten soll.','ph'=>ONB_PRIVATE_FOCUS[$b] ?? ONB_PRIVATE_FOCUS['privat-assistent'],'max'=>2000]);
        $S[]=onb_step('p_context','textarea','Über dich','Was sollte dein Assistent über dich wissen?',['sub'=>'Zum Beispiel Wohnort, Familie, wichtige Fristen oder Verträge. Nur, was dir hilft – änderbar jederzeit unter Training.','ph'=>'Ich wohne in Köln, bin selbstständig, meine Krankenkasse ist die TK …','max'=>3000]);
        $S[]=$langs; $S[]=$anrede; $S[]=$never;
        return $S;
    }
    $S[]=onb_step('company','text','Betrieb','Wie heißt dein Betrieb?',['sub'=>'So stellt sich dein Agent am Telefon, im Chat und bei WhatsApp vor.','ph'=>'z. B. Salon Luna','req'=>true,'max'=>120,'auto_complete'=>'organization']);
    $S[]=onb_step('website','url_import','Betrieb','Hast du eine Website?',['sub'=>'Website-Adresse eingeben → wir lesen sie ein. Leistungen, Preise, Öffnungszeiten und FAQ landen automatisch in der Wissensbasis deines Agenten.','ph'=>'www.dein-betrieb.de']);
    if($P['agent']){
        $S[]=onb_step('address','text','Betrieb','Wo findet man dich?',['sub'=>in_array('order',$m,true) || in_array('ride',$m,true) ? 'Von dieser Adresse aus berechnen wir Liefer- bzw. Anfahrtswege.' : 'Straße, Hausnummer, PLZ und Ort – dein Agent nennt sie bei Wegbeschreibungen.','ph'=>'Schanzenstraße 18, 20357 Hamburg','max'=>200,'auto_complete'=>'street-address']);
        $S[]=onb_step('phone_public','text','Betrieb','Unter welcher Nummer erreichen Kunden dich?',['sub'=>'Dein Agent nennt sie, wenn ein Mensch übernehmen soll – und leitet Anrufe bei Bedarf dorthin weiter.','input'=>'tel','ph'=>'0221 1234567','max'=>40,'auto_complete'=>'tel']);
        $ht=in_array('order',$m,true) ? 'Wann nimmst du Bestellungen an?' : (in_array('appt',$m,true) ? 'Wann kann man bei dir Termine buchen?' : (in_array('ride',$m,true) ? 'Wann ist deine Zentrale erreichbar?' : (in_array('hotel',$m,true) ? 'Wann ist deine Rezeption besetzt?' : 'Wann hast du geöffnet?')));
        $S[]=onb_step('hours','hours','Betrieb',$ht,['sub'=>'Tippe einen Tag an, um ihn zu öffnen oder zu schließen. Ende vor Beginn = über Mitternacht.']);
        $opts=[]; foreach($ch as $c) $opts[]=['v'=>$c,'l'=>['web'=>'Website-Chat','phone'=>'Telefon','whatsapp'=>'WhatsApp'][$c],'d'=>['web'=>'Widget mit einer Zeile Code auf deiner Seite','phone'=>'Agent nimmt Anrufe an – rund um die Uhr','whatsapp'=>'Kunden schreiben deinem Agenten per WhatsApp'][$c]];
        $S[]=onb_step('channels','multi','Kanäle','Wo soll dein Agent für Kunden da sein?',['sub'=>'Du kannst alles später im Studio ein- und ausschalten.','options'=>$opts,'min'=>1]);
        if(in_array('phone',$ch,true)){
            $S[]=onb_step('phone_persona','choice','Telefon','Welche Stimme passt zu deinem Betrieb?',['when'=>['channels'=>['phone']],'sub'=>'Die Stimme spricht jede Sprache deines Agenten. Probehören: Studio → Telefon.','auto'=>true,'options'=>[
                ['v'=>'f','l'=>'Weiblich · warm','d'=>'Unsere natürlichste Stimme – Standard'],['v'=>'m','l'=>'Männlich · ruhig','d'=>'Sachlich und vertrauenswürdig'],
                ['v'=>'g','l'=>'Weiblich · HD','d'=>'HD-Stimme, besonders klar'],['v'=>'gm','l'=>'Männlich · HD','d'=>'HD-Stimme, besonders klar']]]);
            $S[]=onb_step('phone_greeting','textarea','Telefon','Wie soll dein Agent Anrufer begrüßen?',['when'=>['channels'=>['phone']],'sub'=>'Leer lassen für die Standardbegrüßung. Der Agent sagt immer dazu, dass er ein digitaler Assistent ist.','ph'=>'Guten Tag, hier ist der digitale Assistent von Salon Luna – wie kann ich helfen?','max'=>400]);
        }
        if(in_array('whatsapp',$ch,true)){
            $S[]=onb_step('wa_provider','choice','WhatsApp','Wie verbindest du WhatsApp?',['when'=>['channels'=>['whatsapp']],'auto'=>true,'options'=>[
                ['v'=>'meta','l'=>'WhatsApp Business (Meta)','d'=>'Direkt über die WhatsApp-Cloud-API von Meta – kostenlos bis 1.000 Gespräche/Monat.'],
                ['v'=>'later','l'=>'Später verbinden','d'=>'Wir schicken dir die Schritt-für-Schritt-Anleitung per E-Mail.']]]);
            $S[]=onb_step('wa_meta','fields','WhatsApp','Deine WhatsApp-Cloud-API-Daten',['when'=>['channels'=>['whatsapp'],'wa_provider'=>['meta']],'sub'=>'Meta for Developers → deine App → WhatsApp → API-Setup. Kein Zugang? Einfach überspringen – die Anleitung kommt per E-Mail.','fields'=>[
                ['k'=>'wa_phone_id','l'=>'Telefonnummer-ID','t'=>'text','ph'=>'z. B. 109876543210987','req'=>true],['k'=>'wa_token','l'=>'Dauerhafter Zugriffstoken','t'=>'password','ph'=>'EAAG…','req'=>true],['k'=>'wa_verify','l'=>'Verify-Token (optional)','t'=>'text','ph'=>'frei wählbar']]]);
            $S[]=onb_step('wa_twilio','fields','WhatsApp','Deine WhatsApp-Nummer bei Twilio',['when'=>['channels'=>['whatsapp'],'wa_provider'=>['twilio']],'sub'=>'Die Nummer, die in Twilio als WhatsApp-Sender freigeschaltet ist.','fields'=>[
                ['k'=>'wa_number','l'=>'WhatsApp-Nummer','t'=>'tel','ph'=>'+49 151 …','req'=>true],['k'=>'twilio_sid','l'=>'Account SID (falls noch nicht hinterlegt)','t'=>'text','ph'=>'AC…'],['k'=>'twilio_token','l'=>'Auth Token (falls noch nicht hinterlegt)','t'=>'password','ph'=>'32 Zeichen']]]);
        }
    }
    if(in_array('appt',$m,true)){
        $S[]=onb_step('services','rows','Termine','Welche Leistungen kann man buchen?',['sub'=>'Mit Dauer und Preis – daraus berechnet dein Agent freie Termine. Preis 0 = „auf Anfrage“.','cols'=>ONB_COLS_SERVICES,'min'=>1,'max'=>40,'presets'=>ONB_PRESETS[$b] ?? ONB_PRESETS['agent-termine'],'add'=>'Leistung hinzufügen']);
        $S[]=onb_step('appt_buffer','choice','Termine','Wie viel Puffer brauchst du zwischen zwei Terminen?',['auto'=>true,'options'=>[['v'=>'0','l'=>'Keinen'],['v'=>'5','l'=>'5 Minuten'],['v'=>'10','l'=>'10 Minuten'],['v'=>'15','l'=>'15 Minuten'],['v'=>'30','l'=>'30 Minuten']]]);
    }
    if(in_array('order',$m,true)){
        $S[]=onb_step('menu','rows','Bestellungen','Was steht auf deiner Karte?',['sub'=>'Die wichtigsten Gerichte genügen für den Start – Extras und Größen ergänzt du im Studio unter Bestellungen.','cols'=>ONB_COLS_MENU,'min'=>1,'max'=>200,'presets'=>ONB_PRESETS['menu'],'add'=>'Gericht hinzufügen']);
        $S[]=onb_step('order_modes','multi','Bestellungen','Lieferst du, oder holen Kunden ab?',['options'=>[['v'=>'delivery','l'=>'Lieferung','d'=>'Wir bringen das Essen'],['v'=>'pickup','l'=>'Abholung','d'=>'Kunden holen selbst ab']],'min'=>1]);
        $S[]=onb_step('zones','rows','Bestellungen','Wohin lieferst du?',['when'=>['order_modes'=>['delivery']],'sub'=>'Je Liefergebiet die Postleitzahlen, Gebühr und Mindestbestellwert. Ohne Gebiete lieferst du überallhin im Umkreis.','cols'=>ONB_COLS_ZONES,'max'=>40,'add'=>'Liefergebiet hinzufügen']);
        $S[]=onb_step('order_fees','fields','Bestellungen','Mindestbestellwert & Wartezeiten',['sub'=>'Gilt überall, wo kein Liefergebiet etwas anderes festlegt.','fields'=>[
            ['k'=>'min_order_cents','l'=>'Mindestbestellwert','t'=>'money','suffix'=>'€','ph'=>'15,00'],['k'=>'delivery_fee_cents','l'=>'Liefergebühr','t'=>'money','suffix'=>'€','ph'=>'2,50'],
            ['k'=>'eta_delivery_min','l'=>'Lieferzeit','t'=>'int','min'=>5,'max'=>240,'suffix'=>'Min.','ph'=>'40'],['k'=>'eta_pickup_min','l'=>'Abholbereit nach','t'=>'int','min'=>5,'max'=>240,'suffix'=>'Min.','ph'=>'20']]]);
        $S[]=onb_step('payments','multi','Bestellungen','Wie können Kunden bezahlen?',['options'=>[['v'=>'bar','l'=>'Bar'],['v'=>'karte','l'=>'EC-/Kreditkarte'],['v'=>'paypal','l'=>'PayPal'],['v'=>'online','l'=>'Online'],['v'=>'rechnung','l'=>'Rechnung']],'min'=>1]);
    }
    if(in_array('ride',$m,true) && function_exists('ride_settings_save')){
        $S[]=onb_step('tariff','fields','Taxi','Dein Taxitarif',['sub'=>'Daraus nennt dein Agent Richtpreise. Die Werte deines örtlichen Tarifs stehen in der Taxiordnung.','fields'=>[
            ['k'=>'base_cents','l'=>'Grundpreis','t'=>'money','suffix'=>'€','ph'=>'4,90'],['k'=>'km1_cents','l'=>'Preis je km','t'=>'money','suffix'=>'€','ph'=>'2,40'],['k'=>'km1_upto','l'=>'gilt bis km','t'=>'int','min'=>0,'max'=>500,'suffix'=>'km','ph'=>'7'],
            ['k'=>'km2_cents','l'=>'danach je km','t'=>'money','suffix'=>'€','ph'=>'2,10'],['k'=>'wait_min_cents','l'=>'Wartezeit je Min.','t'=>'money','suffix'=>'€','ph'=>'0,63'],['k'=>'night_pct','l'=>'Nachtzuschlag','t'=>'int','min'=>0,'max'=>100,'suffix'=>'%','ph'=>'0']]]);
        $S[]=onb_step('ride_area','choice','Taxi','Wie groß ist dein Einsatzgebiet?',['sub'=>'Umkreis um deine Adresse. Fahrten von weiter weg nimmt der Agent als Anfrage auf.','auto'=>true,'options'=>[['v'=>'10','l'=>'10 km'],['v'=>'15','l'=>'15 km'],['v'=>'25','l'=>'25 km'],['v'=>'40','l'=>'40 km'],['v'=>'80','l'=>'80 km']]]);
        $S[]=onb_step('stands','rows','Taxi','An welchen Taxiständen stehen deine Wagen?',['sub'=>'Der Agent erkennt „am Bahnhof“ oder „am Dom“ als Abholort.','cols'=>ONB_COLS_STANDS,'max'=>30,'add'=>'Taxistand hinzufügen']);
        $S[]=onb_step('drivers','rows','Taxi','Wer fährt bei dir?',['sub'=>'Jede Fahrerin, jeder Fahrer bekommt einen eigenen Link zur Fahrer-App (Fahrten, Status, Navigation).','cols'=>ONB_COLS_DRIVERS,'max'=>200,'add'=>'Fahrer/in hinzufügen']);
        $S[]=onb_step('ride_payments','multi','Taxi','Wie können Fahrgäste bezahlen?',['options'=>array_map(fn($k,$l)=>['v'=>$k,'l'=>$l],array_keys(RIDE_PAYMENTS),RIDE_PAYMENTS),'min'=>1]);
    }
    if(in_array('hotel',$m,true) && function_exists('hotel_settings_save')){
        $S[]=onb_step('rooms','rows','Hotel','Welche Zimmer bietest du an?',['sub'=>'Je Kategorie: Personen, Anzahl der Zimmer und Preis pro Nacht. Wochenendpreise ergänzt du im Studio unter Hotel.','cols'=>ONB_COLS_ROOMS,'min'=>1,'max'=>40,'presets'=>ONB_PRESETS['rooms'],'add'=>'Zimmerkategorie hinzufügen']);
        $S[]=onb_step('hotel_times','fields','Hotel','Check-in, Check-out & Frühstück',['fields'=>[
            ['k'=>'checkin_from','l'=>'Check-in ab','t'=>'time','ph'=>'15:00'],['k'=>'checkout_until','l'=>'Check-out bis','t'=>'time','ph'=>'11:00'],
            ['k'=>'breakfast_mode','l'=>'Frühstück','t'=>'select','opts'=>[['v'=>'none','l'=>'Kein Frühstück'],['v'=>'included','l'=>'Inklusive'],['v'=>'optional','l'=>'Gegen Aufpreis']]],
            ['k'=>'breakfast_cents','l'=>'Frühstück pro Person','t'=>'money','suffix'=>'€','ph'=>'15,00'],['k'=>'city_tax_pct','l'=>'City-Tax','t'=>'pct','suffix'=>'%','ph'=>'0']]]);
    }
    if(in_array('callback',$m,true) && function_exists('cb_settings_save')){
        $S[]=onb_step('call_window','hours','Rückrufe','Wann dürfen Kunden zurückgerufen werden?',['sub'=>'Rückrufwünsche aus Chat, Telefon und WhatsApp landen in deiner Rückrufliste – zu diesen Zeiten.','same_day'=>true]);
        $S[]=onb_step('cb_mode','choice','Rückrufe','Wer ruft zurück?',['auto'=>true,'options'=>[['v'=>'human','l'=>'Mein Team','d'=>'Rückrufliste im Studio und als Push aufs Handy'],['v'=>'ai','l'=>'Der KI-Agent','d'=>'Ruft zur gewünschten Zeit selbst zurück (nur mit Zustimmung des Kunden)']]]);
    }
    if(!$m || in_array('callback',$m,true)){
        [$ft,$fs,$fp]=ONB_FOCUS[$b] ?? ONB_FOCUS_DEFAULT;
        $S[]=onb_step('focus','textarea','Agent',$ft,['sub'=>$fs,'ph'=>$fp,'max'=>4000]);
    }
    $S[]=$langs; $S[]=$anrede;
    $S[]=onb_step('tone','choice','Agent','Welcher Ton passt zu dir?',['auto'=>true,'options'=>array_map(fn($k,$l)=>['v'=>$k,'l'=>$l],array_keys(ONB_TONES),ONB_TONES)]);
    $S[]=$never;
    if($P['agent']) $S[]=onb_step('faq','rows','Agent','Welche Fragen hörst du am häufigsten?',['sub'=>'Frage und Antwort – genau so antwortet dein Agent. Optional, du kannst später im Training ergänzen.','cols'=>ONB_COLS_FAQ,'max'=>30,'add'=>'Frage hinzufügen']);
    return $S;
}

/* ======================================================================= Vorbelegung aus bestehenden Einstellungen */
function onb_row(string $table, int $aid): ?array {
    try{ $s=th_db()->prepare("SELECT * FROM `$table` WHERE account_id=?"); $s->execute([$aid]); $r=$s->fetch(PDO::FETCH_ASSOC); return $r ?: null; }catch(Throwable $e){ return null; }
}
function onb_cfg_row(int $aid, string $plan): ?array { $s=th_db()->prepare("SELECT * FROM agent_configs WHERE account_id=? AND plan=?"); $s->execute([$aid,$plan]); $r=$s->fetch(PDO::FETCH_ASSOC); return $r ?: null; }
const ONB_SEC_START = '— Aus der Einrichtung —'; const ONB_SEC_END = '— Ende Einrichtung —';
const ONB_KB_START = '=== Stammdaten (aus der Einrichtung) ==='; const ONB_KB_END = '=== Ende Stammdaten ===';
function onb_section_get(string $text, string $a, string $z): ?string { $i=mb_strpos($text,$a); if($i===false) return null; $j=mb_strpos($text,$z,$i); $s=mb_substr($text,$i+mb_strlen($a),$j===false ? null : $j-$i-mb_strlen($a)); return trim($s); }
function onb_section_put(string $text, string $a, string $z, string $body, bool $top=false): string {
    $block=$body==='' ? '' : $a."\n".trim($body)."\n".$z;
    $i=mb_strpos($text,$a);
    if($i!==false){ $j=mb_strpos($text,$z,$i); $end=$j===false ? mb_strlen($text) : $j+mb_strlen($z); return trim(mb_substr($text,0,$i).$block.mb_substr($text,$end)); }
    if($block==='') return $text;
    return trim($top ? $block."\n\n".$text : $text."\n\n".$block);
}
function onb_wa_ready(array $os): bool { return ($os['wa_provider']==='meta' && $os['wa_phone_id']!=='' && $os['wa_token']!=='') || ($os['wa_provider']==='twilio' && $os['wa_number']!==''); }
/** [stepId => ['value'=>…, 'known'=>bool]] aus den echten Einstellungen. */
function onb_prefill(int $aid, array $P): array {
    $o=[]; $b=$P['base']; $cfg=onb_cfg_row($aid,$b) ?? th_agent_cfg($aid,$b);
    $as=in_array('appt',$P['mods'],true) ? onb_row('appt_settings',$aid) : null; $os=onb_row('order_settings',$aid);
    $rs=function_exists('ride_settings') && in_array('ride',$P['mods'],true) ? onb_row('ride_settings',$aid) : null;
    $hs=function_exists('hotel_settings') && in_array('hotel',$P['mods'],true) ? onb_row('hotel_settings',$aid) : null;
    $cs=function_exists('cb_settings') && in_array('callback',$P['mods'],true) ? onb_row('callback_settings',$aid) : null;
    $put=function(string $k,$v,bool $known) use(&$o){ $o[$k]=['value'=>$v,'known'=>$known]; };
    $first=function(array $vals){ foreach($vals as $v) if(is_string($v) && trim($v)!=='') return trim($v); return ''; };
    $company=$first([$cfg['company'] ?? '',$as['company'] ?? '',in_array('order',$P['mods'],true) ? ($os['name'] ?? '') : '',$rs['name'] ?? '',$hs['name'] ?? '']);
    $put('company',$company,$company!=='');
    $put('p_name',(string)($cfg['company'] ?? ''),trim((string)($cfg['company'] ?? ''))!=='');
    $kbBlock=onb_section_get((string)($cfg['kb'] ?? ''),ONB_KB_START,ONB_KB_END) ?? '';
    $kbLine=function(string $label) use($kbBlock): string { return preg_match('/^'.preg_quote($label,'/').':\s*(.+)$/mu',$kbBlock,$mm) ? trim($mm[1]) : ''; };
    $site=''; if(!empty($cfg['widget_domain'])) $site='https://'.preg_replace('#^https?://#','',(string)$cfg['widget_domain']);
    try{ $s=th_db()->prepare("SELECT ref FROM agent_sources WHERE account_id=? AND plan=? AND kind='url' ORDER BY id DESC LIMIT 1"); $s->execute([$aid,$b]); $ref=(string)($s->fetchColumn() ?: ''); if($ref!=='') $site=$ref; }catch(Throwable $e){}
    $put('website',$site,$site!=='');
    $addr=$first([$as['address'] ?? '',in_array('order',$P['mods'],true) ? ($os['address'] ?? '') : '',$rs['address'] ?? '',$hs['address'] ?? '',$kbLine('Adresse')]); $put('address',$addr,$addr!=='');
    $tel=$first([in_array('order',$P['mods'],true) ? ($os['phone'] ?? '') : '',$rs['phone'] ?? '',$hs['phone'] ?? '',$cfg['phone_forward'] ?? '',$kbLine('Telefon')]); $put('phone_public',$tel,$tel!=='');
    $hours=null;
    if($as && !empty($as['hours'])) $hours=appt_settings($aid)['hours'];
    elseif(in_array('order',$P['mods'],true) && $os && !empty($os['hours'])) $hours=ord_settings($aid)['hours'];
    $hk=$hours!==null; if($hours===null){ $hk=$kbLine('Öffnungszeiten')!==''; $hours=APPT_DEFAULT_HOURS; }
    $put('hours',$hours,$hk);
    $chan=[]; $wa=$os ? onb_wa_ready(ord_settings($aid)) : false;
    if(!empty($cfg['widget_on'])) $chan[]='web'; if(!empty($cfg['phone_on'])) $chan[]='phone'; if($wa) $chan[]='whatsapp';
    $put('channels',$chan ?: $P['channels'],(bool)$chan);
    $pv=(string)($cfg['phone_voice'] ?? 'de-DE'); $put('phone_persona',preg_match('/-(m|g|gm)$/',$pv,$mm) ? $mm[1] : 'f',trim((string)($cfg['phone_greeting'] ?? ''))!=='');
    $put('phone_greeting',(string)($cfg['phone_greeting'] ?? ''),trim((string)($cfg['phone_greeting'] ?? ''))!=='');
    $osx=$os ? ord_settings($aid) : null;
    $put('wa_provider',$wa ? $osx['wa_provider'] : '',$wa);
    $put('wa_meta',['wa_phone_id'=>(string)($osx['wa_phone_id'] ?? ''),'wa_token'=>!empty($osx['wa_token']) ? '••••'.substr((string)$osx['wa_token'],-4) : '','wa_verify'=>(string)($osx['wa_verify'] ?? '')],$wa && ($osx['wa_provider'] ?? '')==='meta');
    $put('wa_twilio',['wa_number'=>(string)($osx['wa_number'] ?? ''),'twilio_sid'=>'','twilio_token'=>''],$wa && ($osx['wa_provider'] ?? '')==='twilio');
    if(in_array('appt',$P['mods'],true)){
        $sv=array_map(fn($x)=>['name'=>$x['name'],'duration_min'=>$x['duration_min'],'price_cents'=>$x['price_cents']],appt_services($aid,true)); $put('services',$sv,(bool)$sv);
        $put('appt_buffer',(string)($as ? (int)$as['buffer_min'] : 0),(bool)$as);
    }
    if(in_array('order',$P['mods'],true)){
        $os2=ord_settings($aid); $mn=array_map(fn($x)=>['name'=>$x['name'],'category'=>$x['category'],'price_cents'=>$x['price_cents'],'description'=>$x['description']],ord_menu($aid,true));
        $put('menu',$mn,(bool)$mn);
        $put('order_modes',array_values(array_filter([$os2['delivery_on'] ? 'delivery' : null,$os2['pickup_on'] ? 'pickup' : null])),(bool)$os);
        $put('zones',array_map(fn($z)=>['plz'=>$z['plz'],'name'=>$z['name'],'fee_cents'=>(int)$z['fee_cents'],'min_cents'=>(int)$z['min_cents']],$os2['zones']),(bool)$os2['zones']);
        $put('order_fees',['min_order_cents'=>$os2['min_order_cents'],'delivery_fee_cents'=>$os2['delivery_fee_cents'],'eta_delivery_min'=>$os2['eta_delivery_min'],'eta_pickup_min'=>$os2['eta_pickup_min']],(bool)$os);
        $put('payments',array_values(array_filter(explode(',',(string)$os2['payment_methods']))),(bool)$os);
    }
    if($rs!==null || (in_array('ride',$P['mods'],true) && function_exists('ride_settings'))){
        $r2=ride_settings($aid,true); $t=$r2['tariff'];
        $put('tariff',['base_cents'=>$t['base_cents'],'km1_cents'=>$t['km1_cents'],'km1_upto'=>(int)$t['km1_upto'],'km2_cents'=>$t['km2_cents'],'wait_min_cents'=>$t['wait_min_cents'],'night_pct'=>$t['night_pct']],(bool)$rs);
        $put('ride_area',(string)$r2['area_km'],(bool)$rs);
        $put('stands',array_map(fn($x)=>['name'=>$x['name'],'address'=>$x['address']],$r2['stands']),(bool)$r2['stands']);
        $dr=array_map(fn($x)=>['name'=>$x['name'],'phone'=>$x['phone'],'plate'=>$x['plate']],ride_drivers($aid,true)); $put('drivers',$dr,(bool)$dr);
        $put('ride_payments',array_values(array_filter(explode(',',(string)$r2['payment_methods']))),(bool)$rs);
    }
    if($hs!==null || (in_array('hotel',$P['mods'],true) && function_exists('hotel_settings'))){
        $h2=hotel_settings($aid); $rm=array_map(fn($x)=>['name'=>$x['name'],'capacity'=>$x['capacity'],'units'=>$x['units'],'price_cents'=>$x['price_cents']],hotel_rooms($aid,true));
        $put('rooms',$rm,(bool)$rm);
        $put('hotel_times',['checkin_from'=>$h2['checkin_from'],'checkout_until'=>$h2['checkout_until'],'breakfast_mode'=>$h2['breakfast_mode'],'breakfast_cents'=>$h2['breakfast_cents'],'city_tax_pct'=>$h2['city_tax_pct']],(bool)$hs);
    }
    if($cs!==null || (in_array('callback',$P['mods'],true) && function_exists('cb_settings'))){
        $c2=cb_settings($aid); $put('call_window',$c2['call_window'],(bool)$cs); $put('cb_mode',$c2['ai_callback'] ? 'ai' : 'human',(bool)$cs);
    }
    $put('focus',$kbLine('Angebot'),$kbLine('Angebot')!=='');
    $put('p_focus',$kbLine('Wobei ich Hilfe brauche'),$kbLine('Wobei ich Hilfe brauche')!=='');
    $put('p_context',$kbLine('Über mich'),$kbLine('Über mich')!=='');
    $al=array_values(array_filter(explode(',',(string)($cfg['langs'] ?? ''))));
    $put('langs',['default'=>ch_lang_norm((string)($cfg['lang'] ?? 'de')) ?: 'de','allowed'=>$al ?: CH_LANG_CODES],$al!==[]);
    $rules=function_exists('train_rules') ? train_rules($aid,$b) : ''; $sec=onb_section_get($rules,ONB_SEC_START,ONB_SEC_END) ?? '';
    $an=preg_match('/Anrede: (Sie|du)/u',$sec,$mm) ? ($mm[1]==='Sie' ? 'sie' : 'du') : ''; $put('anrede',$an,$an!=='');
    $tone=(string)($cfg['tone'] ?? ''); $put('tone',isset(ONB_TONES[$tone]) ? $tone : 'freundlich-professionell',$sec!=='' && isset(ONB_TONES[$tone]));
    $nv=[]; if(preg_match_all('/^• (?:Niemals|Regel): (.+)$/mu',$sec,$mm)) $nv=$mm[1]; $put('never',$nv,(bool)$nv);
    $fq=function_exists('train_faq') ? array_map(fn($x)=>['question'=>$x['question'],'answer'=>$x['answer']],array_slice(train_faq($aid,$b,true),0,30)) : [];
    $put('faq',[],(bool)$fq);
    return $o;
}

/* ======================================================================= Bedingungen, Normalisierung */
/** when = [stepId => [erlaubte Werte]] (UND-verknüpft); Wert = Antwort, sonst Vorbelegung. */
function onb_visible(array $step, array $vals): bool {
    foreach((array)($step['when'] ?? []) as $k=>$need){ $v=$vals[$k] ?? null; $have=is_array($v) ? $v : ($v===null || $v==='' ? [] : [(string)$v]); if(!array_intersect(array_map('strval',$have),$need)) return false; }
    return true;
}
function onb_money($v): ?int {
    if($v===null || $v==='') return null; if(is_int($v)) return max(0,min(100000000,$v));
    $s=trim(str_replace(['€','EUR',' ',"\u{00A0}"],'',(string)$v)); if($s==='') return null;
    if(preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/',$s)) $s=str_replace(['.',','],['','.'],$s); else $s=str_replace(',','.',$s);
    if(!is_numeric($s)) return -1; return max(0,min(100000000,(int)round((float)$s*100)));
}
/** Ein Feld (Spalte/Formularfeld) prüfen → [Wert, Fehler|null]. */
function onb_norm_field(array $f, $v): array {
    $t=$f['t'] ?? 'text'; $l=$f['l'] ?? $f['k'];
    if(is_array($v) || is_object($v)) $v='';
    $s=trim((string)($v ?? ''));
    switch($t){
        case 'int': if($s==='') return [null,null]; if(!preg_match('/^-?\d+$/',$s)) return [null,"„{$l}“: bitte eine ganze Zahl."]; return [max((int)($f['min'] ?? 0),min((int)($f['max'] ?? 100000),(int)$s)),null];
        case 'pct': if($s==='') return [null,null]; $s=str_replace(',','.',$s); if(!is_numeric($s)) return [null,"„{$l}“: bitte eine Zahl."]; return [max(0,min(100,round((float)$s,2))),null];
        case 'money': $c=onb_money($v); if($c===-1) return [null,"„{$l}“: bitte einen Betrag wie 12,50."]; return [$c,null];
        case 'time': if($s==='') return [null,null]; if(!preg_match('/^([01]?\d|2[0-3])[:.]([0-5]\d)$/',$s,$m)) return [null,"„{$l}“: bitte eine Uhrzeit wie 15:00."]; return [sprintf('%02d:%02d',(int)$m[1],(int)$m[2]),null];
        case 'tel': if($s==='') return [null,null]; $d=preg_replace('/\D/','',$s); if(strlen($d)<6 || strlen($d)>16 || preg_match('/[^0-9+ ()\/.-]/',$s)) return [null,"„{$l}“: bitte eine gültige Telefonnummer."]; return [mb_substr($s,0,40),null];
        case 'select': if($s==='') return [null,null]; $ok=array_column($f['opts'] ?? [],'v'); return in_array($s,$ok,true) ? [$s,null] : [null,"„{$l}“: bitte auswählen."];
        case 'plz': $l2=array_values(array_unique(array_filter(preg_split('/[\s,;]+/',$s) ?: [],fn($x)=>$x!==''))); foreach($l2 as $x) if(!preg_match('/^\d{4,5}$/',$x)) return [null,"„{$x}“ ist keine Postleitzahl."]; return [$l2 ? implode(', ',array_slice($l2,0,100)) : null,null];
        case 'password': case 'text': case 'textarea': default:
            $s=str_replace("\r",'',strip_tags($s)); if($s==='') return [null,null]; return [mb_substr($s,0,(int)($f['max'] ?? ($t==='textarea' ? 2000 : 300))),null];
    }
}
function onb_url(string $s): ?string {
    $s=trim($s); if($s==='') return null; if(!preg_match('#^[a-z][a-z0-9+.-]*://#i',$s)) $s='https://'.ltrim($s,'/');
    $p=parse_url($s); $h=strtolower((string)($p['host'] ?? '')); if(!in_array(strtolower((string)($p['scheme'] ?? '')),['http','https'],true) || !preg_match('/^([a-z0-9-]+\.)+[a-z]{2,}$/',$h)) return null;
    return mb_substr($s,0,500);
}
/** Antwort prüfen → ['value'=>…] oder ['error'=>…,'message'=>…]. Geheimnisse werden hier direkt gespeichert (siehe onb_answer). */
function onb_norm(array $st, $v): array {
    $bad=fn(string $m)=>['error'=>'invalid','message'=>$m];
    if($v===null) return ['value'=>null];
    switch($st['type']){
        case 'text': case 'textarea':
            if(!is_scalar($v)) return $bad('Bitte als Text eingeben.');
            $s=trim(str_replace("\r",'',strip_tags((string)$v)));
            if(($st['input'] ?? '')==='tel' && $s!==''){ [$x,$e]=onb_norm_field(['t'=>'tel','l'=>'Telefon'],$s); if($e) return $bad($e); $s=(string)$x; }
            if($s==='' && !empty($st['req'])) return $bad('Bitte ausfüllen – oder „Überspringen“ wählen.');
            return ['value'=>mb_substr($s,0,(int)($st['max'] ?? ($st['type']==='textarea' ? 2000 : 300)))];
        case 'url_import':
            if(!is_scalar($v)) return $bad('Bitte eine Website-Adresse eingeben.'); if(trim((string)$v)==='') return ['value'=>''];
            $u=onb_url((string)$v); return $u===null ? $bad('Das sieht nicht nach einer Website-Adresse aus – z. B. www.dein-betrieb.de') : ['value'=>$u];
        case 'choice':
            $s=is_scalar($v) ? (string)$v : ''; return in_array($s,array_column($st['options'],'v'),true) ? ['value'=>$s] : $bad('Bitte eine der Möglichkeiten wählen.');
        case 'multi':
            if(!is_array($v)) return $bad('Bitte auswählen.'); $ok=array_column($st['options'],'v'); $out=[];
            foreach($v as $x){ if(!is_scalar($x)) continue; $x=trim(strip_tags((string)$x)); if($x==='') continue;
                if(in_array($x,$ok,true)) $out[]=$x; elseif(!empty($st['free'])) $out[]=mb_substr($x,0,160); }
            $out=array_slice(array_values(array_unique($out)),0,20);
            if(count($out)<(int)($st['min'] ?? 0)) return $bad('Bitte mindestens eine Möglichkeit wählen.');
            return ['value'=>$out];
        case 'hours':
            if(!is_array($v)) return $bad('Bitte Zeiten angeben.'); $raw=[];
            for($w=0;$w<=6;$w++){ $rows=$v[(string)$w] ?? ($v[$w] ?? []); $raw[(string)$w]=is_array($rows) ? array_slice(array_values(array_filter($rows,'is_array')),0,3) : []; }
            $h=appt_hours_parse($raw,$err); if($err!==null) return $bad($err);
            foreach($h as $w=>$rows) foreach($rows as $r){ if($r[0]===$r[1]) return $bad(ONB_WD[(int)$w].': Beginn und Ende sind gleich – bitte prüfen.');
                if(!empty($st['same_day']) && $r[1]<$r[0]) return $bad(ONB_WD[(int)$w].': Rückrufzeiten enden am selben Tag – bitte Ende nach dem Beginn wählen.'); }
            return ['value'=>$h];
        case 'rows':
            if(!is_array($v)) return $bad('Bitte als Liste angeben.'); $out=[];
            foreach(array_values($v) as $i=>$row){ if(!is_array($row)) continue; $r=[]; $empty=true;
                foreach($st['cols'] as $c){ [$x,$e]=onb_norm_field($c,$row[$c['k']] ?? null); if($e) return $bad('Zeile '.($i+1).': '.$e); if($x===null && isset($c['def'])) $x=$c['def']; $r[$c['k']]=$x; if($x!==null && $x!=='' && empty($c['def'])) $empty=false; }
                if($empty) continue;
                foreach($st['cols'] as $c) if(!empty($c['req']) && ($r[$c['k']]===null || $r[$c['k']]==='')) return $bad('Zeile '.($i+1).': „'.$c['l'].'“ fehlt.');
                $out[]=$r; }
            if(count($out)>(int)($st['max'] ?? 50)) return $bad('Höchstens '.(int)$st['max'].' Einträge.');
            if(count($out)<(int)($st['min'] ?? 0)) return $bad('Bitte mindestens einen Eintrag – oder „Überspringen“.');
            if($st['id']==='faq'){ $seen=[]; foreach($out as $r){ $k=mb_strtolower(trim((string)$r['question'])); if(isset($seen[$k])) return $bad('Die Frage „'.mb_substr((string)$r['question'],0,60).'“ steht doppelt.'); $seen[$k]=1; } }
            return ['value'=>$out];
        case 'fields':
            if(!is_array($v)) return $bad('Bitte ausfüllen.'); $out=[];
            foreach($st['fields'] as $f){ [$x,$e]=onb_norm_field($f,$v[$f['k']] ?? null); if($e) return $bad($e); $out[$f['k']]=$x; }
            return ['value'=>$out];
        case 'langs':
            if(!is_array($v)) return $bad('Bitte Sprachen wählen.'); $def=ch_lang_norm((string)($v['default'] ?? '')); if($def==='') return $bad('Bitte eine Standardsprache wählen.');
            $al=array_values(array_unique(array_filter(array_map(fn($x)=>is_scalar($x) ? ch_lang_norm((string)$x) : '',(array)($v['allowed'] ?? []))))); if(!in_array($def,$al,true)) array_unshift($al,$def);
            return ['value'=>['default'=>$def,'allowed'=>$al]];
    }
    return $bad('Unbekannter Fragetyp.');
}

/* ======================================================================= Zustand */
function onb_ctx(int $aid, array $onb): array {
    $P=onb_profile($onb['plan']); $steps=onb_catalog($P); $pre=onb_prefill($aid,$P); $ans=$onb['answers'];
    $vals=[]; foreach($steps as $s){ $vals[$s['id']]=array_key_exists($s['id'],$ans) && $ans[$s['id']]!==null ? $ans[$s['id']] : ($pre[$s['id']]['value'] ?? null); }
    return [$P,$steps,$pre,$vals];
}
function onb_progress(array $steps, array $pre, array $ans, array $vals): array {
    $tot=0; $done=0; foreach($steps as $s){ if(!onb_visible($s,$vals)) continue; $tot++; if(array_key_exists($s['id'],$ans) || !empty($pre[$s['id']]['known'])) $done++; }
    return ['answered'=>$done,'total'=>$tot,'pct'=>$tot ? (int)round(100*$done/$tot) : 100];
}
function onb_public(array $onb): array {
    $P=onb_profile($onb['plan']);
    return ['id'=>$onb['id'],'plan'=>$onb['plan'],'plan_name'=>$P['name'],'base'=>$onb['base'],'base_name'=>$P['base_name'],'kind'=>$P['kind'],'private'=>$P['private'],
        'status'=>$onb['status'],'created_at'=>th_dbiso($onb['created_at'] ?? null),'finished_at'=>th_dbiso($onb['finished_at'] ?? null),'guide_sent_at'=>th_dbiso($onb['guide_sent_at'] ?? null),'guide_count'=>$onb['guide_count'],
        'trial'=>onb_is_trial($onb),'checkout'=>onb_is_trial($onb) ? '/chat/tokens-checkout.php?plan='.rawurlencode($onb['plan']) : null];   // Testphase: eingerichtet, Live-Schaltung mit dem Paket
}
function onb_state(int $aid, array $onb): array {
    [$P,$steps,$pre,$vals]=onb_ctx($aid,$onb); $ans=$onb['answers']; $out=[];
    foreach($steps as $s){ $id=$s['id']; $s['value']=$vals[$id]; $s['answered']=array_key_exists($id,$ans); $s['skipped']=array_key_exists($id,$ans) && $ans[$id]===null; $s['known']=!empty($pre[$id]['known']); $s['visible']=onb_visible($s,$vals); $out[]=$s; }
    $list=array_map('onb_public',onb_list($aid));
    return ['onboarding'=>onb_public($onb),'list'=>$list,'steps'=>$out,'progress'=>onb_progress($steps,$pre,$ans,$vals),'checklist'=>onb_checklist($aid,$onb),'links'=>onb_links($aid,$onb),
        'langs'=>array_map(fn($c)=>['v'=>$c,'l'=>CH_LANGS[$c]['native']],CH_LANG_CODES)];
}
/** Einrichtung für das Studio: ?id, sonst ?plan (bei aktivem Paket ggf. neu anlegen), sonst die neueste offene bzw. die neueste. */
function onb_resolve(int $aid, int $id, string $plan): array {
    if($id>0){ $o=onb_get($aid,$id); return $o ? [200,$o] : [404,['error'=>'not_found','message'=>'Diese Einrichtung gibt es nicht.']]; }
    $mine=onb_list($aid);
    if($plan!==''){ $pl=ch_plan($plan); if(!$pl) return [400,['error'=>'plan_unknown','message'=>'Unbekannter Plan.']];
        foreach($mine as $o) if($o['plan']===$pl['id'] || $o['base']===$pl['base']) return [200,$o];
        if(!th_plan_active($aid,$pl['id'])){
            /* Noch kein Paket (Testphase): Einrichtung trotzdem sofort – die Antworten bleiben beim Buchen erhalten (onb_on_payment übernimmt sie) */
            if(!onb_trial_ok($pl)) return [403,['error'=>'plan_inactive','message'=>'Für diesen Agenten gibt es noch kein aktives Paket.']];
            $o=onb_create($aid,$pl['id'],'trial:'.$aid.':'.$pl['base']); return $o ? [200,$o] : [500,['error'=>'create_failed']]; }
        $o=onb_create($aid,$pl['id'],'studio:'.$aid.':'.$pl['base']); return $o ? [200,$o] : [500,['error'=>'create_failed']]; }
    if($mine) return [200,$mine[0]];
    foreach(th_plans($aid) as $p){ $pl=ch_plan((string)$p['plan']); if(!$pl || !in_array($p['status'],['active','trialing','past_due'],true) || !th_plan_active($aid,$pl['id'])) continue;
        $o=onb_create($aid,$pl['id'],'studio:'.$aid.':'.$pl['base']); if($o) return [200,$o]; }
    return [404,['error'=>'no_plan','message'=>'Welchen Agenten richten wir ein? Wähle einen – die Einrichtung startet sofort.','choices'=>onb_choices($aid)]];
}
/** Land für Ortsnummern: Profil (country), sonst Deutschland. */
function onb_country(int $aid): string {
    if(is_file(__DIR__.'/profile-lib.php')) require_once __DIR__.'/profile-lib.php';
    $c=function_exists('profile_get') ? strtoupper((string)((profile_get($aid) ?? [])['country'] ?? '')) : ''; return preg_match('/^[A-Z]{2}$/',$c) ? $c : 'DE';
}
/** Einrichtung ohne gebuchtes Paket erlaubt? Agenten und Privat-Assistenten (keine Token-/Content-Pakete). */
function onb_trial_ok(array $pl): bool { $b=(string)$pl['base']; return (CH_PLANS[$b][4] ?? '')==='agent' || str_starts_with($b,'privat-'); }
function onb_is_trial(array $onb): bool { return str_starts_with((string)$onb['checkout_id'],'trial:') && !th_plan_active((int)$onb['account_id'],(string)$onb['base']); }
/** Vorschläge, wenn noch nichts gebucht ist: Empfehlungen aus dem Profil (beste zuerst), sonst die gefragtesten Agenten. */
function onb_choices(int $aid): array {
    $ids=[]; $why=[]; if(is_file(__DIR__.'/profile-lib.php')) require_once __DIR__.'/profile-lib.php';
    if(function_exists('ch_profile_recommend') && function_exists('profile_get')) foreach(ch_profile_recommend(profile_get($aid))['plans'] ?? [] as $r){ if(onb_trial_ok(ch_plan($r['id']) ?? ['base'=>''])){ $ids[]=$r['id']; $why[$r['id']]=(string)($r['reason'] ?? ''); } }
    foreach(['agent-phone','agent-termine','agent-bestellung','agent-support'] as $d) if(count($ids)<4 && !in_array($d,$ids,true) && ch_plan($d)) $ids[]=$d;
    return array_map(function($id) use($why){ $pl=ch_plan($id); return ['id'=>$id,'name'=>(string)$pl['name'],'desc'=>mb_substr((string)$pl['desc'],0,140).(mb_strlen((string)$pl['desc'])>140 ? '…' : ''),'reason'=>$why[$id] ?? '','channels'=>$pl['channels']]; },array_slice($ids,0,4));
}

/* ======================================================================= Antwort speichern (inkl. Geheimnisse) */
function onb_twilio_check(string $sid, string $tok): ?bool {
    $base=defined('CB_TWILIO_API') && CB_TWILIO_API ? rtrim((string)CB_TWILIO_API,'/') : 'https://api.twilio.com';
    $ch=curl_init($base.'/2010-04-01/Accounts/'.rawurlencode($sid).'.json'); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_USERPWD=>$sid.':'.$tok]);
    curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($code===401 || $code===403 || $code===404) return false; return ($code>=200 && $code<300) ? true : null;   // null = nicht erreichbar → gespeichert, beim ersten Anruf geprüft
}
function onb_mask(string $v): string { return $v==='' ? '' : '••••'.substr($v,-4); }
/** Geheimnisse sofort ablegen, im Antwortspeicher nur maskiert. Rückgabe: ['value'=>maskiert] oder Fehler. */
function onb_secrets(int $aid, array $onb, string $step, array $v): array {
    $bad=fn(string $m)=>['error'=>'invalid','message'=>$m]; $masked=fn($x)=>is_string($x) && str_starts_with($x,'••••');
    if($step==='twilio' || $step==='wa_twilio'){
        $sid=(string)($v['twilio_sid'] ?? ''); $tok=(string)($v['twilio_token'] ?? '');
        if($masked($sid)) $sid=''; if($masked($tok)) $tok='';
        $sidC=$sid!=='' ? $sid : th_secret($aid,'twilio_sid'); $tokC=$tok!=='' ? $tok : th_secret($aid,'twilio_token');
        if($sid!=='' && !preg_match('/^AC[a-f0-9]{32}$/',$sid)) return $bad('Account SID beginnt mit AC und hat 34 Zeichen (0–9, a–f).');
        if($tok!=='' && !preg_match('/^[a-f0-9]{32}$/',$tok)) return $bad('Auth Token: 32 Zeichen (0–9, a–f).');
        if($step==='twilio'){
            if($sidC==='' || $tokC==='') return $bad('Bitte Account SID und Auth Token eingeben – oder „Überspringen“.');
            $from=th_e164((string)($v['twilio_from'] ?? '')); if($from==='') return $bad('Twilio-Nummer bitte im Format +49…');
        } else {
            $from=null; $num=th_e164((string)($v['wa_number'] ?? '')); if($num==='') return $bad('WhatsApp-Nummer bitte im Format +49…');
            if($sidC==='' || $tokC==='') return $bad('Für WhatsApp über Twilio brauchen wir einmalig Account SID und Auth Token.');
        }
        $verified=null; if($sid!=='' || $tok!==''){ $verified=onb_twilio_check($sidC,$tokC); if($verified===false) return $bad('Twilio hat SID/Token abgelehnt – bitte in der Twilio-Console (Account Info) prüfen.'); }
        if($sid!=='') th_secret_set($aid,'twilio_sid',$sid); if($tok!=='') th_secret_set($aid,'twilio_token',$tok);
        if($step==='twilio'){ th_secret_set($aid,'twilio_from',(string)$from); return ['value'=>['twilio_sid'=>onb_mask($sidC),'twilio_token'=>onb_mask($tokC),'twilio_from'=>$from],'verified'=>$verified]; }
        ord_settings_save($aid,['wa_provider'=>'twilio','wa_number'=>$num]);
        return ['value'=>['wa_number'=>$num,'twilio_sid'=>onb_mask($sidC),'twilio_token'=>onb_mask($tokC)],'verified'=>$verified];
    }
    if($step==='wa_meta'){
        $pid=preg_replace('/[^0-9]/','',(string)($v['wa_phone_id'] ?? '')); $tok=trim((string)($v['wa_token'] ?? '')); $ver=preg_replace('/[^A-Za-z0-9_-]/','',(string)($v['wa_verify'] ?? ''));
        if(strlen($pid)<8) return $bad('Die Telefonnummer-ID besteht aus Ziffern (mind. 8) – zu finden unter WhatsApp → API-Setup.');
        $cur=ord_settings($aid);
        if($masked($tok) || $tok===''){ if((string)$cur['wa_token']==='') return $bad('Bitte den dauerhaften Zugriffstoken eingeben.'); $tok=(string)$cur['wa_token']; }
        elseif(strlen($tok)<20 || preg_match('/\s/',$tok)) return $bad('Der Zugriffstoken sieht unvollständig aus (beginnt meist mit EAA…).');
        ord_settings_save($aid,['wa_provider'=>'meta','wa_phone_id'=>$pid,'wa_token'=>$tok,'wa_verify'=>mb_substr($ver,0,64)]);
        return ['value'=>['wa_phone_id'=>$pid,'wa_token'=>onb_mask($tok),'wa_verify'=>$ver]];
    }
    return ['value'=>$v];
}
function onb_answer(int $aid, array $onb, string $stepId, $value): array {
    [$P,$steps]=onb_ctx($aid,$onb); $st=null; foreach($steps as $s) if($s['id']===$stepId){ $st=$s; break; }
    if(!$st) return [404,['error'=>'unknown_step','message'=>'Diese Frage gibt es in deiner Einrichtung nicht.']];
    if(is_string($value) && $value==='__skip') $value=null;
    $n=onb_norm($st,$value); if(isset($n['error'])) return [422,$n+['step'=>$stepId]];
    $extra=[];
    if($n['value']!==null && in_array($stepId,['twilio','wa_twilio','wa_meta'],true)){ $sx=onb_secrets($aid,$onb,$stepId,$n['value']); if(isset($sx['error'])) return [422,$sx+['step'=>$stepId]]; $n['value']=$sx['value']; if(array_key_exists('verified',$sx)) $extra['verified']=$sx['verified']; }
    $onb['answers'][$stepId]=$n['value'];
    onb_save($onb,['answers']);
    $onb=onb_get($aid,$onb['id']); [$P,$steps,$pre,$vals]=onb_ctx($aid,$onb);
    return [200,['ok'=>true,'step'=>$stepId,'value'=>$n['value'],'skipped'=>$n['value']===null,'progress'=>onb_progress($steps,$pre,$onb['answers'],$vals)]+$extra];
}

/* ======================================================================= Übernehmen in die Module */
function onb_hours_text(array $h): string {
    $order=[1,2,3,4,5,6,0]; $fmt=fn($rows)=>$rows ? implode(', ',array_map(fn($r)=>$r[0].'–'.$r[1],$rows)) : 'geschlossen'; $parts=[]; $i=0;
    while($i<7){ $d=$order[$i]; $f=$fmt($h[(string)$d] ?? []); $j=$i; while($j+1<7 && $fmt($h[(string)$order[$j+1]] ?? [])===$f) $j++;
        $parts[]=($i===$j ? ONB_WD[$d] : ONB_WD[$d].'–'.ONB_WD[$order[$j]]).' '.$f; $i=$j+1; }
    return implode(' · ',$parts);
}
function onb_eur(int $c): string { return number_format($c/100,2,',','.').' €'; }
/** Alle Antworten in die echten Einstellungen übernehmen. Rückgabe: Liste [key,label,ok,detail]. */
function onb_apply(int $aid, array $onb): array {
    [$P,$steps,$pre,$vals]=onb_ctx($aid,$onb); $A=$onb['answers']; $b=$P['base']; $res=[];
    $has=fn(string $k)=>array_key_exists($k,$A) && $A[$k]!==null && $A[$k]!=='' && $A[$k]!==[];
    $vis=[]; foreach($steps as $s) $vis[$s['id']]=onb_visible($s,$vals);
    $use=fn(string $k)=>$has($k) && ($vis[$k] ?? false);
    $v=fn(string $k)=>$vals[$k] ?? null;
    $run=function(string $key,string $label,callable $fn) use(&$res){ try{ $d=$fn(); if($d!==false) $res[]=['key'=>$key,'label'=>$label,'ok'=>true,'detail'=>is_string($d) ? $d : '']; }
        catch(Throwable $e){ error_log('CH onb_apply '.$key.': '.$e->getMessage().' @'.$e->getLine()); $res[]=['key'=>$key,'label'=>$label,'ok'=>false,'detail'=>'Konnte nicht gespeichert werden – bitte im Studio prüfen.']; } };
    $active=th_plan_active($aid,$onb['plan']) || th_plan_active($aid,$b);
    $company=$P['private'] ? (string)($v('p_name') ?? '') : (string)($v('company') ?? '');
    $address=(string)($v('address') ?? ''); $phone=(string)($v('phone_public') ?? ''); $hours=is_array($v('hours')) ? $v('hours') : null; $hoursAns=$use('hours');
    $site=(string)($v('website') ?? ''); $host=$site!=='' ? preg_replace('/^www\./','',strtolower((string)parse_url($site,PHP_URL_HOST))) : '';
    $chan=(array)($v('channels') ?? []);
    /* 1) Agent-Konfiguration */
    $run('agent','Agent eingerichtet',function() use($aid,$b,$P,$A,$use,$v,$company,$host,$chan,$hours,$hoursAns,$active,$address,$phone,$site){
        $cur=th_agent_cfg($aid,$b); $d=[];
        if($company!=='') $d['company']=$company;
        if($use('tone')) $d['tone']=(string)$v('tone');
        if($host!=='') $d['widget_domain']=$host;
        if($P['agent'] && $use('channels')){ $d['widget_on']=in_array('web',$chan,true) ? 1 : 0; $d['phone_on']=in_array('phone',$chan,true) && in_array('phone',$P['channels'],true) ? 1 : 0; }
        if($use('phone_persona') || $use('langs')){ $lang=(string)(($v('langs') ?? [])['default'] ?? (ch_lang_norm((string)$cur['lang']) ?: 'de')); $sx=(string)$v('phone_persona'); $d['phone_voice']=CH_LANGS[$lang]['phone'].(in_array($sx,['m','g','gm'],true) ? '-'.$sx : ''); }
        if($use('phone_greeting')) $d['phone_greeting']=(string)$v('phone_greeting');
        $fwd=$use('managed_phone') ? (string)(($v('managed_phone') ?? [])['forward'] ?? '') : ''; if($fwd==='' && $phone!=='' && trim((string)$cur['phone_forward'])==='') $fwd=$phone; if($fwd!=='') $d['phone_forward']=$fwd;
        if($hours && $hoursAns) $d['phone_hours']=mb_substr(onb_hours_text($hours),0,160);
        /* Stammdaten-Block in der Wissensbasis (ersetzt sich bei erneutem Abschließen) */
        $L=[];
        if($P['private']){ if($company!=='') $L[]='Name: '.$company; if($use('p_focus')) $L[]='Wobei ich Hilfe brauche: '.preg_replace('/\s*\n\s*/u',' ',(string)$v('p_focus')); if($use('p_context')) $L[]='Über mich: '.preg_replace('/\s*\n\s*/u',' ',(string)$v('p_context')); }
        else { if($company!=='') $L[]='Unternehmen: '.$company; if($address!=='') $L[]='Adresse: '.$address; if($phone!=='') $L[]='Telefon: '.$phone; if($site!=='') $L[]='Website: '.$site;
            if($hours && ($hoursAns || !empty($cur['kb']))) $L[]='Öffnungszeiten: '.onb_hours_text($hours);
            if($use('focus')) $L[]='Angebot: '.preg_replace('/\s*\n\s*/u',' ',(string)$v('focus')); }
        if($L) $d['kb']=mb_substr(onb_section_put((string)$cur['kb'],ONB_KB_START,ONB_KB_END,implode("\n",$L),true),0,60000);
        th_agent_cfg_save($aid,$b,$d,$active);
        $parts=[]; if($company!=='') $parts[]=$company; if(isset($d['widget_on'])) $parts[]=$d['widget_on'] ? 'Website-Chat an' : 'Website-Chat aus'; if(!empty($d['phone_on'])) $parts[]='Telefon an';
        return implode(' · ',$parts);
    });
    if($use('langs')) $run('langs','Sprachen',function() use($aid,$b,$v){ $l=$v('langs'); $r=ch_agent_langs_save($aid,$b,(string)$l['default'],(array)$l['allowed']); return $r ? implode(', ',array_map(fn($c)=>CH_LANGS[$c]['native'],array_slice($r,0,6))).(count($r)>6 ? ' …' : '') : false; });
    if(($use('anrede') || $use('never') || $use('tone')) && function_exists('train_rules_save')) $run('rules','Regeln für den Agenten',function() use($aid,$b,$v,$use,$P){
        $L=[]; if($use('anrede')) $L[]=$v('anrede')==='du' ? '• Anrede: du – wir duzen '.($P['private'] ? 'die Person, für die du arbeitest' : 'Kundinnen und Kunden').'.' : '• Anrede: Sie – wir siezen '.($P['private'] ? 'die Person, für die du arbeitest' : 'Kundinnen und Kunden').'.';
        if($use('tone')) $L[]='• Ton: '.(ONB_TONES[(string)$v('tone')] ?? (string)$v('tone')).'.';
        foreach((array)($use('never') ? $v('never') : []) as $n){ $n=rtrim((string)$n,'.'); $L[]=preg_match('/^(nie|niemals|kein|keine|keinen|keine[rsm]|nicht|ohne)\b/iu',$n) ? '• Regel: '.$n : '• Niemals: '.$n; }
        $r=train_rules_save($aid,$b,onb_section_put(train_rules($aid,$b),ONB_SEC_START,ONB_SEC_END,implode("\n",$L)));
        if(isset($r['error'])) throw new RuntimeException((string)$r['message']); return count($L)===1 ? '1 Regel' : count($L).' Regeln';
    });
    if($use('faq') && function_exists('train_faq_save')) $run('faq','Häufige Fragen',function() use($aid,$b,$v){
        $cur=train_faq($aid,$b); $seen=[]; $items=[]; foreach($cur as $x){ $items[]=['id'=>$x['id'],'question'=>$x['question'],'answer'=>$x['answer'],'active'=>$x['active'],'source'=>$x['source'] ?? 'manual']; $seen[train_norm_q($x['question'])]=count($items)-1; }
        $n=0; foreach((array)$v('faq') as $r){ $k=train_norm_q((string)$r['question']); if(isset($seen[$k])){ $items[$seen[$k]]['answer']=(string)$r['answer']; $items[$seen[$k]]['active']=1; } else { $items[]=['question'=>(string)$r['question'],'answer'=>(string)$r['answer'],'source'=>'manual']; $seen[$k]=count($items)-1; } $n++; }
        $x=train_faq_save($aid,$b,$items,[]); if(isset($x['error'])) throw new RuntimeException((string)$x['message']); return $n===1 ? '1 Frage' : $n.' Fragen';
    });
    /* 2) Telefon */
    $numNow=is_file(__DIR__.'/number-lib.php') && (require_once __DIR__.'/number-lib.php') && num_ready();
    $ownTw=th_secret($aid,'twilio_sid')!=='' && th_secret($aid,'twilio_token')!=='';   // Bestand: eigenes Konto bleibt im Hintergrund
    $wantNum=$P['agent'] && in_array('phone',$chan,true) && in_array('phone',$P['channels'],true) && !$ownTw;
    if($wantNum && $numNow) $run('phone_number','Telefonnummer',function() use($aid,&$onb,$b,$phone,$active){
        $cc=onb_country($aid);   // gleiche Vorwahl wie die Geschäftsnummer (Kunden sehen sie nicht – sie wählen weiter die bekannte Nummer)
        if(!$active){ $onb['meta']['num_pending']=['area'=>$phone,'cc'=>$cc]; onb_save($onb,['meta']); return 'kommt sofort nach der Buchung'; }   // Testphase: Kauf löst die Zahlung aus
        $r=num_buy($aid,$b,'',$phone,$cc); if(isset($r['error'])) throw new RuntimeException((string)$r['message']);
        unset($onb['meta']['num_pending']); onb_save($onb,['meta']); return 'Nummer '.$r['display'];
    });
    if($wantNum && !$numNow) $run('phone_request','Telefonnummer wird eingerichtet',function() use($aid,$onb,$P,$company,$phone){
        $meta=$onb['meta']; if(!empty($meta['phone_req_at'])) return 'bereits in Arbeit';
        $to=defined('SMTP_FROM') ? SMTP_FROM : 'support@chat-help.com'; $h=fn($x)=>htmlspecialchars((string)$x,ENT_QUOTES,'UTF-8');
        th_mail($to,'[ChatHelp] Nummer einrichten: '.$P['name'],'<p>Konto: '.$h(onb_email($aid)).' (#'.$aid.')<br>Betrieb: '.$h($company).'<br>Agent: '.$h($P['base']).'<br>Geschäftsnummer (wird umgeleitet): '.$h($phone).'</p>');
        $meta['phone_req_at']=gmdate('c'); $onb['meta']=$meta; onb_save($onb,['meta']); return 'wir melden uns mit der Nummer';
    });
    /* 3) Module */
    if(in_array('appt',$P['mods'],true) && ($use('services') || $use('hours') || $use('appt_buffer') || $use('company'))) $run('appt','Terminbuchung',function() use($aid,$v,$use,$company,$address,$hours,$hoursAns){
        $d=['appt_on'=>1]; if($company!=='') $d['company']=$company; if($address!=='') $d['address']=$address; if($hours && $hoursAns) $d['hours']=$hours; if($use('appt_buffer')) $d['buffer_min']=(int)$v('appt_buffer');
        if($use('services')) $d['services']=array_map(fn($r)=>['name'=>$r['name'],'duration_min'=>(int)$r['duration_min'],'price_cents'=>(int)($r['price_cents'] ?? 0)],(array)$v('services'));
        appt_settings_save($aid,$d,$err); if($err) throw new RuntimeException((string)$err['message']);
        $n=count(appt_services($aid,true)); return ($n===1 ? '1 Leistung' : $n.' Leistungen').' · Buchung an';
    });
    if(in_array('order',$P['mods'],true) && ($use('menu') || $use('order_modes') || $use('zones') || $use('order_fees') || $use('payments') || $use('hours'))) $run('order','Bestellannahme',function() use($aid,$v,$use,$company,$address,$phone,$hours,$hoursAns,$vis){
        $d=['order_on'=>1]; if($company!=='') $d['name']=$company; if($address!=='') $d['address']=$address; if($phone!=='') $d['phone']=$phone; if($hours && $hoursAns) $d['hours']=$hours;
        if($use('order_modes')){ $m=(array)$v('order_modes'); $d['delivery_on']=in_array('delivery',$m,true) ? 1 : 0; $d['pickup_on']=in_array('pickup',$m,true) ? 1 : 0; }
        if($use('zones') && ($vis['zones'] ?? false)) $d['zones']=array_map(fn($z)=>['plz'=>(string)$z['plz'],'name'=>(string)($z['name'] ?? ''),'fee_cents'=>(int)($z['fee_cents'] ?? 0),'min_cents'=>(int)($z['min_cents'] ?? 0)],(array)$v('zones'));
        if($use('order_fees')) foreach((array)$v('order_fees') as $k=>$x) if($x!==null) $d[$k]=(int)$x;
        if($use('payments')) $d['payment_methods']=implode(',',(array)$v('payments'));
        if($use('menu')) $d['menu']=array_map(fn($r)=>['name'=>$r['name'],'category'=>(string)($r['category'] ?? ''),'price_cents'=>(int)($r['price_cents'] ?? 0),'description'=>(string)($r['description'] ?? '')],(array)$v('menu'));
        $s=ord_settings_save($aid,$d);
        return count(ord_menu($aid,true)).' Gerichte · '.($s['delivery_on'] ? 'Lieferung' : '').($s['delivery_on'] && $s['pickup_on'] ? ' + ' : '').($s['pickup_on'] ? 'Abholung' : '').($s['lat']===null && $s['address']!=='' ? ' · Adresse noch nicht auf der Karte gefunden' : '');
    });
    if(in_array('ride',$P['mods'],true) && function_exists('ride_settings_save') && ($use('tariff') || $use('stands') || $use('drivers') || $use('ride_area') || $use('ride_payments') || $use('company'))) $run('ride','Taxi-Zentrale',function() use($aid,$v,$use,$company,$address,$phone){
        $d=['ride_on'=>1]; if($company!=='') $d['name']=$company; if($address!=='') $d['address']=$address; if($phone!=='') $d['phone']=$phone;
        if($use('tariff')){ $t=array_filter((array)$v('tariff'),fn($x)=>$x!==null); if($t) $d['tariff']=$t; }
        if($use('ride_area')) $d['area_km']=(int)$v('ride_area'); if($use('ride_payments')) $d['payment_methods']=implode(',',(array)$v('ride_payments'));
        if($use('stands')) $d['stands']=array_map(fn($r)=>['name'=>$r['name'],'address'=>(string)($r['address'] ?? '')],(array)$v('stands'));
        if($use('drivers')) $d['drivers']=array_map(fn($r)=>['name'=>$r['name'],'phone'=>(string)($r['phone'] ?? ''),'plate'=>(string)($r['plate'] ?? '')],(array)$v('drivers'));
        $s=ride_settings_save($aid,$d); return count($s['stands']).' Taxistände · '.count(ride_drivers($aid,true)).' Fahrer · Grundpreis '.onb_eur((int)$s['tariff']['base_cents']);
    });
    if(in_array('hotel',$P['mods'],true) && function_exists('hotel_settings_save') && ($use('rooms') || $use('hotel_times') || $use('company'))) $run('hotel','Hotel-Rezeption',function() use($aid,$v,$use,$company,$address,$phone){
        $d=['hotel_on'=>1]; if($company!=='') $d['name']=$company; if($address!=='') $d['address']=$address; if($phone!=='') $d['phone']=$phone;
        if($use('hotel_times')) foreach((array)$v('hotel_times') as $k=>$x) if($x!==null) $d[$k]=$x;
        if($use('rooms')) $d['rooms']=array_map(fn($r)=>['name'=>$r['name'],'capacity'=>(int)$r['capacity'],'units'=>(int)$r['units'],'price_cents'=>(int)($r['price_cents'] ?? 0)],(array)$v('rooms'));
        $s=hotel_settings_save($aid,$d); return count(hotel_rooms($aid,true)).' Zimmerkategorien · Check-in ab '.$s['checkin_from'];
    });
    if(in_array('callback',$P['mods'],true) && function_exists('cb_settings_save') && ($use('call_window') || $use('cb_mode'))) $run('callback','Rückrufe',function() use($aid,$v,$use,$b){
        $d=['cb_on'=>1,'plan'=>$b]; if($use('call_window')) $d['call_window']=$v('call_window'); if($use('cb_mode')) $d['ai_callback']=$v('cb_mode')==='ai' ? 1 : 0;
        $s=cb_settings_save($aid,$d); return cb_window_text($s);
    });
    return $res;
}
function onb_finish(int $aid, array $onb): array {
    $lk='onb:fin:'.$onb['id']; $got=th_lock($lk,10);
    try{
        $onb=onb_get($aid,$onb['id']) ?? $onb;
        $applied=onb_apply($aid,$onb); $first=$onb['status']!=='done';
        $onb['status']='done'; $onb['result']=['applied'=>$applied,'at'=>gmdate('c')];
        th_db()->prepare("UPDATE onboardings SET status='done', result=?, finished_at=COALESCE(finished_at,NOW()), updated_at=NOW() WHERE id=?")->execute([json_encode($onb['result'],JSON_UNESCAPED_UNICODE),$onb['id']]);
        $onb=onb_get($aid,$onb['id']);
        $mailed=false;
        $cl=th_db()->prepare("UPDATE onboardings SET done_mail_at=NOW() WHERE id=? AND done_mail_at IS NULL"); $cl->execute([$onb['id']]);
        if($cl->rowCount()===1){   // genau ein Abschluss verschickt die Mail (Doppelklick, zweiter Tab)
            $mailed=onb_send_guide($aid,$onb,'done');
            if(!$mailed) th_db()->prepare("UPDATE onboardings SET done_mail_at=NULL WHERE id=?")->execute([$onb['id']]);
        }
        return ['ok'=>true,'first'=>$first,'mailed'=>$mailed,'applied'=>$applied]+onb_state($aid,onb_get($aid,$onb['id']));
    } finally { if($got) th_unlock($lk); }
}

/* ======================================================================= Checkliste + Links */
function onb_email(int $aid): string { $s=th_db()->prepare("SELECT email FROM token_accounts WHERE id=?"); $s->execute([$aid]); return (string)($s->fetchColumn() ?: ''); }
/** Rufumleitungs-Codes auf die ChatHelp-Nummer (number-lib). */
function onb_forward_codes(string $e164): array { if(!function_exists('num_forward_codes') && is_file(__DIR__.'/number-lib.php')) require_once __DIR__.'/number-lib.php'; return function_exists('num_forward_codes') ? num_forward_codes($e164) : []; }
function onb_num_display(string $e164): string { if(!function_exists('num_display') && is_file(__DIR__.'/number-lib.php')) require_once __DIR__.'/number-lib.php'; return function_exists('num_display') ? num_display($e164) : $e164; }
function onb_links(int $aid, array $onb): array {
    $b=$onb['base']; $cfg=onb_cfg_row($aid,$b); $wk=(string)($cfg['widget_key'] ?? ''); $P=onb_profile($onb['plan']); $o=['app'=>ONB_APP_URL,'app_android'=>ONB_APP_URL.'#android','app_ios'=>ONB_APP_URL.'#ios',
        'start'=>ONB_START_URL,'studio_test'=>ONB_SITE.'/chat/studio.php?welcome='.$b,'widget_key'=>$wk,'widget_snippet'=>$wk!=='' ? '<script src="'.ONB_SITE.'/chat/widget.js" data-key="'.$wk.'" async></script>' : null,
        'phone_webhook'=>$wk!=='' ? ONB_SITE.'/chat/phone-webhook.php?key='.$wk : null,'test_plan'=>$b];
    if(in_array('whatsapp',$P['channels'],true) && $wk!==''){ $os=ord_settings($aid); $o['whatsapp_webhook']=ONB_SITE.'/chat/whatsapp-webhook.php?key='.$wk; $o['verify_token']=$os['wa_verify'] ?: $wk; }
    try{
        if(in_array('appt',$P['mods'],true) && $wk!=='') $o['booking']=appt_booking_url($aid);
        if(in_array('order',$P['mods'],true) && onb_row('order_settings',$aid)){ $l=ord_links($aid); $o['board']=$l['board']; }
        if(in_array('ride',$P['mods'],true) && function_exists('ride_links') && onb_row('ride_settings',$aid)){ $l=ride_links($aid); $o['taxi_board']=$l['board']; $o['drivers']=$l['drivers']; }
        if(in_array('hotel',$P['mods'],true) && function_exists('hotel_links') && onb_row('hotel_settings',$aid)) $o['hotel_board']=hotel_links($aid)['board'];
        if(in_array('callback',$P['mods'],true) && function_exists('cb_links') && $wk!=='') $o['callback_form']=cb_links($aid)['form'];
    }catch(Throwable $e){ error_log('CH onb_links: '.$e->getMessage()); }
    return $o;
}
/** Persönliche Schritte: status done|todo|info, mit Anleitung (steps) und Studio-Ziel (go). */
function onb_checklist(int $aid, array $onb): array {
    $P=onb_profile($onb['plan']); $b=$P['base']; $cfg=onb_cfg_row($aid,$b) ?? th_agent_cfg($aid,$b); $L=onb_links($aid,$onb); $A=$onb['answers']; $C=[];
    $item=function(string $key,string $label,string $status,string $detail='',array $steps=[],array $x=[]) use(&$C){ $C[]=['key'=>$key,'label'=>$label,'status'=>$status,'detail'=>$detail,'steps'=>$steps]+$x; };
    $item('questions','Fragen beantwortet',$onb['status']==='done' ? 'done' : 'todo',$onb['status']==='done' ? 'Deine Angaben sind übernommen.' : 'Ein paar Fragen – dann ist dein '.($P['private'] ? 'Assistent' : 'Agent').' startklar (ca. 5 Minuten).',[],['go'=>'einrichtung','url'=>ONB_START_URL]);
    $kb=mb_strlen(trim((string)($cfg['kb'] ?? '')));
    $item('knowledge','Wissensbasis',$kb>=200 ? 'done' : 'todo',$kb>=200 ? number_format($kb,0,',','.').' Zeichen Wissen hinterlegt.' : 'Website einlesen oder Dokumente hochladen (Studio → Training).',[],['go'=>'training']);
    $chan=(array)($A['channels'] ?? $P['channels']); $byok=$P['kind']==='byok';
    if($P['agent'] && in_array('web',$P['channels'],true) && in_array('web',$chan,true)){
        $on=!empty($cfg['widget_on']);
        $item('widget','Website-Chat einbinden',$on ? 'done' : 'todo',$on ? 'Widget ist freigeschaltet – Code einmal vor </body> deiner Website einfügen.' : 'Mit aktivem Paket freischalten und den Code vor </body> einfügen.',
            ['Code kopieren (unten bzw. Studio → Website)','In deiner Website vor </body> einfügen (WordPress: Design → Theme-Datei-Editor oder Plugin „Header & Footer“)','Seite neu laden – unten rechts erscheint dein Agent'],['code'=>$L['widget_snippet'],'go'=>'website']);
    }
    if($P['agent'] && in_array('phone',$P['channels'],true) && in_array('phone',$chan,true)){
        $own=th_secret($aid,'twilio_sid')!=='' && th_secret($aid,'twilio_token')!=='';
        if($own) $item('phone','Telefonnummer verbinden',!empty($cfg['phone_on']) ? 'done' : 'todo','Eigene Nummer '.(th_secret($aid,'twilio_from') ?: '').' – jetzt nur noch den Webhook eintragen.',
            ['Console → Phone Numbers → Manage → Active numbers → deine Nummer','Bei „A call comes in“: Webhook, HTTP POST, URL: '.$L['phone_webhook'],'Speichern und deine Nummer anrufen – dein Agent meldet sich'],['url'=>$L['phone_webhook'],'go'=>'phone']);
        elseif(!empty($cfg['phone_number'])){
            $item('phone','Deine ChatHelp-Nummer','done','Dein Agent nimmt unter '.onb_num_display((string)$cfg['phone_number']).' ab. Kunden wählen weiter deine bekannte Nummer – du leitest sie nur um.',[],['go'=>'phone']);
            $biz=trim((string)($A['phone_public'] ?? ''));
            $item('forward','Anrufe auf deinen Agenten umleiten','todo',($biz!=='' ? 'Auf dem Telefon mit '.$biz.' einen Code antippen' : 'Auf dem Telefon mit deiner Geschäftsnummer einen Code antippen').' (oder in der Telefon-App eintippen und anrufen). Empfohlen: „Wenn ich nicht rangehe“ und „Wenn besetzt“ – dann klingelt zuerst bei dir.',
                ['iPhone: Code in der Telefon-App eintippen und auf Anrufen drücken (Links mit * und # öffnet das iPhone nicht)','Festnetz: dieselben Codes mit einem Stern am Anfang (z. B. *61*…#) oder im Router unter Telefonie → Rufumleitung (Fritzbox: Rufbehandlung)','Bei „Immer umleiten“ unter Studio → Telefon „Weiterleitung an (Mensch)“ eine andere Nummer eintragen (z. B. dein Handy) – sonst landet die Weiterleitung wieder beim Agenten'],
                ['codes'=>onb_forward_codes((string)$cfg['phone_number']),'go'=>'phone']);
        }
        else $item('phone','Deine ChatHelp-Nummer','info','Wir richten deine Nummer ein und schicken sie dir per E-Mail und Push – danach leitest du deine bekannte Nummer mit einem Code darauf um. Bis dahin kannst du den Telefon-Agenten im Browser testen.',[],['go'=>'phone']);
    }
    if($P['agent'] && in_array('whatsapp',$P['channels'],true) && in_array('whatsapp',$chan,true)){
        $os=ord_settings($aid); $ready=onb_wa_ready($os); $prov=(string)($A['wa_provider'] ?? ($ready ? $os['wa_provider'] : 'meta'));
        if($prov==='twilio') $item('whatsapp','WhatsApp verbinden',$ready ? 'done' : 'todo',$ready ? 'Über Twilio: '.$os['wa_number'].' – Webhook eintragen, fertig.' : 'WhatsApp-Sender in Twilio freischalten und hier eintragen.',
            ['Twilio-Console → Messaging → Senders → WhatsApp senders → Nummer registrieren (Meta-Freigabe dauert 1–3 Tage)','Beim Sender „A message comes in“: '.($L['whatsapp_webhook'] ?? 'Studio → WhatsApp'),'Studio → WhatsApp: Anbieter Twilio und Nummer speichern'],['url'=>$L['whatsapp_webhook'] ?? null,'go'=>'whatsapp']);
        else $item('whatsapp','WhatsApp verbinden',$ready ? 'done' : 'todo',$ready ? 'WhatsApp-Cloud-API verbunden – jetzt den Webhook bei Meta bestätigen.' : 'In 5 Schritten mit der WhatsApp-Cloud-API von Meta verbinden.',
            ['business.facebook.com → Einstellungen → Sicherheitscenter → Unternehmensverifizierung starten (Gewerbeanmeldung oder Handelsregisterauszug bereithalten, Prüfung 1–5 Tage)','developers.facebook.com → App erstellen (Typ „Business“) → Produkt „WhatsApp“ hinzufügen → eigene Telefonnummer hinzufügen und per SMS bestätigen',
             'Business-Einstellungen → Systemnutzer anlegen → Token mit „whatsapp_business_messaging“ erzeugen (dauerhaft); Telefonnummer-ID unter WhatsApp → API-Setup kopieren',
             'WhatsApp → Konfiguration → Webhook: Rückruf-URL '.($L['whatsapp_webhook'] ?? '(Studio → WhatsApp)').' · Verify-Token '.($L['verify_token'] ?? '').' → Feld „messages“ abonnieren',
             'Studio → WhatsApp: Telefonnummer-ID und Token eintragen, speichern – Testnachricht an deine Nummer schicken'],['url'=>$L['whatsapp_webhook'] ?? null,'verify'=>$L['verify_token'] ?? null,'go'=>'whatsapp']);
    }
    if(in_array('appt',$P['mods'],true)){ $n=count(appt_services($aid,true)); $on=appt_settings($aid)['appt_on']===1; $item('appt','Terminbuchung',$n && $on ? 'done' : 'todo',$n && $on ? ($n===1 ? '1 Leistung' : $n.' Leistungen').' buchbar – deine Buchungsseite zum Teilen (Instagram, Google-Profil, Website):' : 'Leistungen mit Dauer und Preis anlegen.',[],['url'=>$L['booking'] ?? null,'go'=>'termine']); }
    if(in_array('order',$P['mods'],true)){ $n=count(ord_menu($aid,true)); $on=ord_settings($aid)['order_on']===1; $item('order','Bestellannahme',$n && $on ? 'done' : 'todo',$n && $on ? $n.' Gerichte auf der Karte – das Küchen-Board fürs Tablet zeigt jede Bestellung live:' : 'Speisekarte anlegen.',[],['url'=>$L['board'] ?? null,'go'=>'bestellungen']); }
    if(in_array('ride',$P['mods'],true) && function_exists('ride_settings')){ $s=ride_settings($aid,true); $nd=count(ride_drivers($aid,true)); $item('ride','Taxi-Zentrale',$s['ride_on'] && $s['saved'] ? 'done' : 'todo',$s['ride_on'] && $s['saved'] ? 'Tarif, '.count($s['stands']).' Taxistände und '.$nd.' Fahrer – Fahrer-Links verschicken.' : 'Tarif, Taxistände und Fahrer anlegen.',array_map(fn($d)=>$d['name'].': '.$d['url'],array_slice($L['drivers'] ?? [],0,10)),['url'=>$L['taxi_board'] ?? null,'go'=>'taxi']); }
    if(in_array('hotel',$P['mods'],true) && function_exists('hotel_settings')){ $n=count(hotel_rooms($aid,true)); $on=hotel_settings($aid)['hotel_on']===1; $item('hotel','Hotel-Rezeption',$n && $on ? 'done' : 'todo',$n && $on ? $n.' Zimmerkategorien buchbar – das Rezeptions-Board zeigt Anfragen und Gästewünsche live:' : 'Zimmer mit Preisen anlegen.',[],['url'=>$L['hotel_board'] ?? null,'go'=>'hotel']); }
    if(in_array('callback',$P['mods'],true) && function_exists('cb_settings')){ $saved=(bool)onb_row('callback_settings',$aid); $item('callback','Rückrufe',$saved ? 'done' : 'todo',$saved ? 'Rückrufzeiten: '.cb_window_text(cb_settings($aid)) : 'Zeiten festlegen, zu denen zurückgerufen wird.',[],['url'=>$L['callback_form'] ?? null,'go'=>'rueckruf']); }
    if($byok){ $bk=bl_byok_public($aid); $item('byok','Eigenen KI-Schlüssel hinterlegen',!empty($bk['active']) ? 'done' : 'todo',!empty($bk['active']) ? 'Dein Schlüssel ist aktiv – ChatHelp bucht keine Tokens ab.' : 'Claude- oder DeepSeek-Schlüssel eintragen: Studio → Telefon → Verbindungen.',[],['go'=>'phone']); }
    $item('app','App installieren','info','ChatHelp aufs Handy: Aufträge, Rückrufe und Nachrichten sofort per Push.',['Android: '.$L['app_android'].' öffnen → „App installieren“','iPhone/iPad: '.$L['app_ios'].' in Safari öffnen → Teilen → „Zum Home-Bildschirm“','Computer: '.$L['app'].' in Chrome oder Edge öffnen → Installieren-Symbol in der Adressleiste'],['url'=>ONB_APP_URL,'go'=>'benachrichtigungen']);
    $item('test','Test-Chat starten','info','Stell deinem '.($P['private'] ? 'Assistenten' : 'Agenten').' eine Frage wie ein '.($P['private'] ? 'echtes Anliegen' : 'Kunde').' – im Studio, kostenlos im Rahmen deines Pakets.',[],['url'=>$L['studio_test'],'go'=>'test']);
    return $C;
}

/* ======================================================================= E-Mail + Push */
function onb_mail_send(string $to, string $subject, string $html, string $text): bool {
    if(isset($GLOBALS['CH_MAIL_TAP']) && is_callable($GLOBALS['CH_MAIL_TAP'])){ try{ ($GLOBALS['CH_MAIL_TAP'])($to,$subject,$html,$text); }catch(Throwable $e){} }
    return th_mail($to,$subject,$html,$text);
}
/** Anleitung bauen: phase start (direkt nach der Zahlung) | done (nach der Einrichtung) | resend. → [Betreff, HTML, Text] */
function onb_guide(int $aid, array $onb, string $phase): array {
    $P=onb_profile($onb['plan']); $C=onb_checklist($aid,$onb); $h=fn($x)=>htmlspecialchars((string)$x,ENT_QUOTES,'UTF-8');
    $done=$onb['status']==='done'; $nd=count(array_filter($C,fn($c)=>$c['status']==='done')); $nt=count(array_filter($C,fn($c)=>$c['status']!=='info'));
    $who=$P['private'] ? 'Assistent' : 'Agent';
    $subject=$done ? 'Alles bereit: '.$P['base_name'].' ist eingerichtet ✅' : 'So richtest du '.$P['base_name'].' ein – in 5 Minuten startklar';
    $lead=$done ? 'Deine Angaben sind übernommen. Hier ist deine persönliche Übersicht: was schon läuft und was noch zu tun ist.' : 'Danke für deinen Kauf! Beantworte ein paar kurze Fragen – dann übernehmen wir alles in deinen '.$who.'en und du bist in wenigen Minuten live.';
    $btn=$done ? ['Zum Studio',ONB_SITE.'/chat/studio.php#einrichtung'] : ['Einrichtung starten',ONB_START_URL];
    $H='<div style="font-family:Inter,Arial,sans-serif;max-width:560px;margin:0 auto;padding:32px;border:1px solid #e6e8ef;border-radius:20px;color:#0f1222;background:#ffffff">'
      .'<div style="font-size:22px;font-weight:700;margin-bottom:6px">ChatHelp <span style="color:#b8862b">Studio</span></div>'
      .'<div style="margin-top:18px"><span style="display:inline-block;font:600 11px Inter,Arial,sans-serif;letter-spacing:.08em;text-transform:uppercase;padding:6px 10px;border-radius:999px;background:#f6ecd3;color:#b8862b">'.$h($done ? 'Einrichtung abgeschlossen' : 'Einrichtung').' · '.$h($P['name']).'</span></div>'
      .'<h2 style="font-family:Georgia,serif;font-size:22px;line-height:1.3;margin:14px 0 8px">'.$h($done ? 'Alles bereit.' : 'Willkommen – los geht’s.').'</h2>'
      .'<p style="color:#3b4055;margin:0 0 18px;line-height:1.55">'.$h($lead).'</p>'
      .'<div style="text-align:center;margin:22px 0"><a href="'.$h($btn[1]).'" style="display:inline-block;background:#0f1222;color:#ffffff;text-decoration:none;font-weight:600;font-size:16px;padding:15px 28px;border-radius:999px">'.$h($btn[0]).' →</a></div>'
      .'<p style="color:#6b7083;font-size:13px;margin:0 0 6px;text-align:center">'.$h($nd.' von '.$nt.' Schritten erledigt').'</p>'
      .'<div style="height:6px;border-radius:99px;background:#f1f2f7;overflow:hidden;margin:0 0 22px"><div style="height:6px;width:'.($nt ? (int)round(100*$nd/$nt) : 0).'%;background:#b8862b"></div></div>';
    $T=($done ? 'Alles bereit.' : 'Willkommen – los geht’s.')."\n\n".$lead."\n\n".$btn[0].': '.$btn[1]."\n\n".$nd.' von '.$nt." Schritten erledigt\n";
    $i=0; foreach($C as $c){ if($c['status']!=='done') $i++;
        $mark=$c['status']==='done' ? '<span style="display:inline-block;width:26px;height:26px;border-radius:50%;background:#e4f5ea;color:#1a9e5a;text-align:center;line-height:26px;font-weight:700">✓</span>'
            : '<span style="display:inline-block;width:26px;height:26px;border-radius:50%;background:'.($c['status']==='info' ? '#eceafe;color:#4f46e5' : '#f6ecd3;color:#b8862b').';text-align:center;line-height:26px;font-weight:700;font-size:13px">'.$i.'</span>';
        $H.='<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-top:1px solid #e6e8ef;padding:14px 0"><tr><td style="width:38px;vertical-align:top;padding-top:12px">'.$mark.'</td><td style="vertical-align:top;padding-top:12px">'
           .'<div style="font-weight:700;font-size:15px">'.$h($c['label']).($c['status']==='done' ? ' <span style="color:#1a9e5a;font-weight:600;font-size:12px">erledigt</span>' : ($c['status']==='todo' ? ' <span style="color:#b8862b;font-weight:600;font-size:12px">offen</span>' : '')).'</div>'
           .($c['detail']!=='' ? '<div style="color:#3b4055;font-size:13.5px;line-height:1.5;margin-top:3px">'.$h($c['detail']).'</div>' : '');
        $T.="\n".($c['status']==='done' ? '[✓] ' : '['.$i.'] ').$c['label'].($c['status']==='todo' ? ' (offen)' : '')."\n".($c['detail']!=='' ? '    '.$c['detail']."\n" : '');
        if($c['steps'] && ($c['status']!=='done' || in_array($c['key'],['app','ride','phone'],true))){
            $H.='<ol style="margin:8px 0 0;padding-left:18px;color:#3b4055;font-size:13px;line-height:1.6">'.implode('',array_map(fn($s)=>'<li style="margin:3px 0">'.$h($s).'</li>',$c['steps'])).'</ol>';
            foreach($c['steps'] as $k=>$s) $T.='    '.($k+1).'. '.$s."\n"; }
        if(!empty($c['codes'])){ $H.='<div style="margin-top:8px">'.implode('',array_map(fn($x)=>'<div style="margin:5px 0;font-size:13.5px"><a href="'.$h($x['tel']).'" style="display:inline-block;min-width:150px;font:600 13px ui-monospace,Menlo,Consolas,monospace;color:#0f1222;background:#f6ecd3;border-radius:10px;padding:7px 10px;text-decoration:none">'.$h($x['code']).'</a> <span style="color:#3b4055">'.$h($x['label']).'</span></div>',$c['codes'])).'</div>';
            foreach($c['codes'] as $x) $T.='    '.$x['code'].'  '.$x['label']."\n"; }
        if(!empty($c['code'])){ $H.='<div style="font:12.5px ui-monospace,Menlo,Consolas,monospace;background:#0f1222;color:#e6e8f5;border-radius:12px;padding:12px;word-break:break-all;margin-top:8px;-webkit-user-select:all;user-select:all">'.$h($c['code']).'</div>'; $T.='    Code: '.$c['code']."\n"; }
        if(!empty($c['url']) && $c['key']!=='questions'){ $H.='<div style="margin-top:6px;font-size:13px"><a href="'.$h($c['url']).'" style="color:#b8862b;text-decoration:none;font-weight:600">'.$h(['app'=>'App öffnen','test'=>'Test-Chat öffnen','appt'=>'Buchungsseite öffnen','order'=>'Küchen-Board öffnen','hotel'=>'Rezeptions-Board öffnen','ride'=>'Taxi-Board öffnen','callback'=>'Rückruf-Formular öffnen','whatsapp'=>'Webhook-URL'][$c['key']] ?? 'Link öffnen').' →</a></div>'; $T.='    '.$c['url']."\n"; }
        $H.='</td></tr></table>'; }
    $H.='<div style="margin-top:18px;padding:16px;border-radius:14px;background:#f6f7fb;border:1px solid #e6e8ef"><div style="font-weight:700;font-size:14px;margin-bottom:6px">📱 ChatHelp-App herunterladen</div>'
       .'<div style="font-size:13px;color:#3b4055;line-height:1.6"><a href="'.$h(ONB_APP_URL.'#android').'" style="color:#0f1222;font-weight:600">Android</a> · <a href="'.$h(ONB_APP_URL.'#ios').'" style="color:#0f1222;font-weight:600">iPhone &amp; iPad</a> · <a href="'.$h(ONB_APP_URL).'" style="color:#0f1222;font-weight:600">Computer (Web-App)</a></div></div>'
       .'<p style="color:#6b7083;font-size:12.5px;margin-top:22px;line-height:1.5">Anmelden im Studio mit dieser E-Mail-Adresse – du bekommst einen 6-stelligen Code. Fragen? Einfach auf diese Mail antworten.<br>ChatHelp · chat-help.com · support@chat-help.com</p></div>';
    $T.="\nChatHelp-App: Android ".ONB_APP_URL."#android · iPhone ".ONB_APP_URL."#ios · Computer ".ONB_APP_URL."\n\nAnmelden im Studio mit dieser E-Mail-Adresse.\nChatHelp · chat-help.com · support@chat-help.com\n";
    return [$subject,$H,$T];
}
function onb_send_guide(int $aid, array $onb, string $phase): bool {
    $to=onb_email($aid); if($to==='') return false; [$s,$hm,$t]=onb_guide($aid,$onb,$phase); return onb_mail_send($to,$s,$hm,$t);
}
/**
 * Stripe-Webhook nach erfolgreicher Zahlung (type=plan): Einrichtung anlegen (einmal je Checkout-Session), Agent-Konfiguration mit Widget-Schlüssel
 * sicherstellen, Anleitung + Push einmalig senden. Folge-Abos (Betrieb/eigener Schlüssel) nutzen eine vorhandene Einrichtung desselben Agenten.
 */
function onb_on_payment(int $aid, string $email, array $pl, string $checkoutId): array {
    if($aid<=0 || $checkoutId==='' || empty($pl['id'])) return ['ok'=>false,'skip'=>'no_checkout'];
    onb_bootstrap(); $base=(string)$pl['base'];
    /* In der Testphase schon eingerichtet → diese Einrichtung übernimmt die Zahlung (Antworten bleiben, keine zweite leere Einrichtung) */
    if(!onb_by_checkout($checkoutId) && ($ex=onb_find($aid,$base)) && str_starts_with($ex['checkout_id'],'trial:')){
        th_db()->prepare("UPDATE onboardings SET plan=?, checkout_id=?, updated_at=NOW() WHERE id=? AND checkout_id=?")->execute([$pl['id'],mb_substr($checkoutId,0,120),$ex['id'],$ex['checkout_id']]);
        if($ex['status']==='done') th_db()->prepare("UPDATE onboardings SET guide_sent_at=COALESCE(guide_sent_at,NOW()) WHERE id=?")->execute([$ex['id']]);   // fertig eingerichtet: keine „Jetzt einrichten“-Mail
    }
    if(in_array($pl['kind'],['betrieb','byok'],true) && ($ex=onb_find($aid,$base)) && $ex['checkout_id']!==$checkoutId) return ['ok'=>true,'id'=>$ex['id'],'created'=>false,'mailed'=>false,'reused'=>true];
    $before=onb_by_checkout($checkoutId); $onb=$before ?? onb_create($aid,$pl['id'],$checkoutId);
    if(!$onb || $onb['account_id']!==$aid) return ['ok'=>false,'skip'=>'checkout_mismatch'];
    /* In der Testphase gewünschte ChatHelp-Nummer: jetzt kaufen – der Agent nimmt sofort Anrufe an */
    if(!empty($onb['meta']['num_pending']) && is_file(__DIR__.'/number-lib.php')){ require_once __DIR__.'/number-lib.php';
        try{ $np=$onb['meta']['num_pending']; $nr=num_buy($aid,$base,'',(string)($np['area'] ?? ''),(string)($np['cc'] ?? 'DE'));
            if(!isset($nr['error'])){ unset($onb['meta']['num_pending']); $onb['meta']['number']=$nr['number']; onb_save($onb,['meta']); } else error_log('CH onb number: '.$nr['message']); }
        catch(Throwable $e){ error_log('CH onb number: '.$e->getMessage()); } }
    try{ if(!onb_cfg_row($aid,$base)) th_agent_cfg_save($aid,$base,[],th_plan_active($aid,$base)); }catch(Throwable $e){ error_log('CH onb cfg: '.$e->getMessage()); }
    $mailed=false; $pushed=false;
    $cl=th_db()->prepare("UPDATE onboardings SET guide_sent_at=NOW(), guide_count=guide_count+1 WHERE id=? AND guide_sent_at IS NULL"); $cl->execute([$onb['id']]);
    if($cl->rowCount()===1){   // Webhook-Wiederholung / parallele Zustellung: nur der erste Aufruf sendet
        $mailed=onb_send_guide($aid,onb_get($aid,$onb['id']),'start');
        if(!$mailed) th_db()->prepare("UPDATE onboardings SET guide_sent_at=NULL, guide_count=GREATEST(guide_count-1,0) WHERE id=?")->execute([$onb['id']]);   // nächster Webhook-Versuch sendet erneut
        $P=onb_profile($pl['id']);
        if(function_exists('ch_push')) $pushed=ch_push($aid,'system',$P['base_name'].': jetzt einrichten','In 5 Minuten startklar – ein paar Fragen beantworten, den Rest übernehmen wir. Die Anleitung liegt auch in deinem Postfach.','/chat/studio.php#einrichtung',['email'=>false,'tag'=>'onb-'.$onb['id']]);
    }
    return ['ok'=>true,'id'=>$onb['id'],'created'=>$before===null,'mailed'=>$mailed,'pushed'=>$pushed];
}

/* ======================================================================= Studio-REST */
function onb_api(string $route, string $method, int $aid, array $body, array $account): ?array {
    if(strpos($route,'onboarding/')!==0) return null;
    $known=['onboarding/state'=>'GET','onboarding/answer'=>'POST','onboarding/finish'=>'POST','onboarding/resend-guide'=>'POST'];
    $routes=['GET onboarding/state','POST onboarding/answer','POST onboarding/finish','POST onboarding/resend-guide'];
    if(!isset($known[$route])) return [404,['error'=>'not_found','routes'=>$routes]];
    if($known[$route]!==$method) return [405,['error'=>'method_not_allowed','routes'=>$routes]];
    if($aid<=0) return [403,['error'=>'demo_readonly','message'=>'In der Demo gibt es keine Einrichtung.']];
    try{
        onb_bootstrap();
        $id=(int)($body['id'] ?? ($_GET['id'] ?? 0));
        if($route==='onboarding/state'){
            $plan=preg_replace('/[^a-z0-9-]/','',strtolower((string)($_GET['plan'] ?? ''))) ?? '';
            [$c,$o]=onb_resolve($aid,$id,$plan); if($c!==200) return [$c,$o+['list'=>array_map('onb_public',onb_list($aid))]];
            return [200,onb_state($aid,$o)];
        }
        $onb=$id>0 ? onb_get($aid,$id) : null; if(!$onb) return [404,['error'=>'not_found','message'=>'Diese Einrichtung gibt es nicht.']];
        switch($route){
            case 'onboarding/answer':
                $step=preg_replace('/[^a-z_]/','',(string)($body['step'] ?? '')) ?? ''; if($step==='') return [400,['error'=>'step_required']];
                if(!array_key_exists('value',$body)) return [400,['error'=>'value_required','message'=>'value fehlt (null = überspringen).']];
                if(!th_rate('onb:ans:'.$aid,600,3600)) return [429,['error'=>'rate_limited','message'=>'Bitte kurz warten.']];
                return onb_answer($aid,$onb,$step,$body['value']);
            case 'onboarding/finish':
                return [200,onb_finish($aid,$onb)];
            case 'onboarding/resend-guide':
                if(!th_rate('onb:resend:'.$aid,5,3600)) return [429,['error'=>'rate_limited','message'=>'Die Anleitung wurde gerade mehrfach gesendet – bitte in einer Stunde erneut.']];
                $ok=onb_send_guide($aid,$onb,'resend');
                if($ok) th_db()->prepare("UPDATE onboardings SET guide_count=guide_count+1, guide_sent_at=COALESCE(guide_sent_at,NOW()), updated_at=NOW() WHERE id=?")->execute([$onb['id']]);
                return [$ok ? 200 : 502,['ok'=>$ok,'sent_to'=>onb_email($aid),'message'=>$ok ? 'Anleitung gesendet an '.onb_email($aid).'.' : 'Versand fehlgeschlagen – bitte später erneut.']];
        }
    }catch(Throwable $e){ error_log('CH onb_api '.$route.': '.$e->getMessage().' @'.$e->getLine()); return [500,['error'=>'server_error','message'=>'Interner Fehler – bitte erneut versuchen.']]; }
    return [404,['error'=>'not_found','routes'=>$routes]];
}
