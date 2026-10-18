<?php
/**
 * ChatHelp — Konto-Profil aus der Registrierung („Für wen? Betrieb? Bedarf?“)
 * ============================================================================
 * Nach der ersten Anmeldung fragt das Studio in vier kurzen Schritten (jeder überspringbar):
 *   a) Für wen: Betrieb (business) oder privat (private = Kategorie „privat“)
 *   b) Betrieb: Firmenname, Branchen (Mehrfachauswahl), Größe, Ort/Land · Privat: Was möchtest du erledigen?
 *   c) Bedarf (Anrufe, WhatsApp, Website-Chat, Termine …) und Hauptsprachen der Kunden (13 Sprachen)
 *   d) Zusammenfassung mit empfohlenen Agenten + Studio-Bereichen (ch_profile_recommend)
 * Das Studio richtet danach Navigation, Agenten-Reihenfolge und „Für dich empfohlen“ nach dem Profil aus;
 * der Studio-Assistent bekommt eine Kurzfassung (ch_profile_brief, max. 300 Zeichen) als Kontext.
 *
 * Tabelle: account_profile (eine Zeile je Konto). kind=NULL bedeutet „Assistent übersprungen“ (das Studio fragt dann nicht erneut).
 *          sectors/needs/langs sind JSON-Listen (TEXT-Spalten, damit es auch auf älteren MySQL/MariaDB-Versionen läuft).
 * REST (Studio, Bearer-Key, siehe tokens-api.php):
 *   GET  /v1/profile  → {profile|null, exists, complete, rec:{plans[],areas[],primary}, options:{sectors[],needs[],private_tasks[],sizes[],langs[],countries[]}}
 *   POST /v1/profile  {kind, company, sectors[], size, city, country, needs[], langs[]}  oder  {dismissed:true}
 * Alle Werte werden geprüft und begrenzt: nur bekannte IDs für Listen, Freitext ohne HTML/Steuerzeichen, feste Längen.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';

const PROFILE_KINDS = ['business','private'];
const PROFILE_SIZES = ['solo'=>'Nur ich','2-10'=>'2–10','11-50'=>'11–50','50+'=>'Über 50'];
/** Hauptsprachen der Kunden: zuerst die fünf Schwerpunkt-Sprachen (hervorgehoben), dann die übrigen. */
const PROFILE_LANGS = ['de'=>'Deutsch','fr'=>'Français','tr'=>'Türkçe','az'=>'Azərbaycanca','es'=>'Español','en'=>'English','ru'=>'Русский','it'=>'Italiano','nl'=>'Nederlands','sv'=>'Svenska','no'=>'Norsk','fi'=>'Suomi','ja'=>'日本語'];
const PROFILE_LANGS_TOP = ['de','fr','tr','az','es'];
const PROFILE_COUNTRIES = ['DE'=>'Deutschland','AT'=>'Österreich','CH'=>'Schweiz','TR'=>'Türkei','AZ'=>'Aserbaidschan','FR'=>'Frankreich','ES'=>'Spanien','IT'=>'Italien','NL'=>'Niederlande',
  'BE'=>'Belgien','LU'=>'Luxemburg','GB'=>'Vereinigtes Königreich','SE'=>'Schweden','NO'=>'Norwegen','FI'=>'Finnland','PL'=>'Polen','JP'=>'Japan','US'=>'USA'];
/** Bedarf (Betrieb): label, icon, Studio-Bereich (Tab) oder '', passende Pläne. */
const PROFILE_NEEDS = [
  'phone'     =>['Anrufe annehmen','☎','phone',['agent-phone']],
  'whatsapp'  =>['WhatsApp','💬','whatsapp',[]],
  'webchat'   =>['Website-Chat','🌐','website',['agent-support']],
  'termine'   =>['Termine','📅','termine',['agent-termine']],
  'bestellung'=>['Bestellungen & Lieferung','🍕','bestellungen',['agent-bestellung']],
  'taxi'      =>['Taxi & Fahrten','🚕','taxi',['agent-taxi']],
  'hotel'     =>['Hotel & Zimmer','🛎','hotel',['agent-hotel']],
  'rueckruf'  =>['Rückrufe','📞','rueckruf',[]],
  'kunden'    =>['Kundenverlauf','👥','kunden',[]],
  'marketing' =>['Marketing & Content','✍️','',['agent-content','creator-social']],
  'team'      =>['Personal & Schulung','🎓','training',['hr-onboarding']],
];
/** Privat: Was möchtest du erledigen? label, icon, Plan. */
const PROFILE_PRIVATE_TASKS = [
  'p_behoerde'=>['Behördengänge & Formulare','🏛','privat-behoerde'],
  'p_bank'    =>['Bank & Geld','🏦','privat-bank'],
  'p_reise'   =>['Hotel & Reise','✈️','privat-reise'],
  'p_briefe'  =>['Briefe, E-Mails & Kündigungen','✉️','privat-assistent'],
  'p_anrufe'  =>['Anrufe für mich erledigen','📞','privat-anruf'],
];
/** Studio-Bereiche je Plan (wie MOD_FOR in studio.php). */
const PROFILE_MOD_FOR = [
  'termine'=>['agent-termine','agent-beauty','praxis-agent','agent-autohaus','praxis-termine','gastro-agent'],
  'bestellungen'=>['agent-bestellung','gastro-agent'],'taxi'=>['agent-taxi'],'hotel'=>['agent-hotel'],
  'rueckruf'=>['agent-phone','immo-agent','agent-hausverwaltung','agent-versicherung','agent-kanzlei','agent-steuer','agent-inkasso','agent-bank','agent-behoerde','agent-aesthetic','agent-hair-tr','agent-dental-tr','praxis-agent','agent-autohaus','agent-hotel','agent-sales','privat-anruf'],
];
/** Branchen ohne Telefon-Demo (CH_PHONE_SECTORS) – ergänzen die Auswahl: label, icon, Kategorie, Pläne, Suchwörter. */
const PROFILE_SECTOR_EXTRA = [
  'inkasso'  =>['Inkasso & Forderungen','💶','recht',['agent-inkasso'],'mahnung forderung'],
  'shop'     =>['Onlineshop & Handel','🛍','handel',['agent-support','ec-texte'],'ecommerce shopify woocommerce einzelhandel laden'],
  'agentur'  =>['Agentur, Marketing & Creator','✍️','marketing',['agent-content','creator-social','agent-website'],'social media youtube seo werbung'],
  'behoerde' =>['Behörde & Verwaltung','🏛','oeffentlich',['agent-behoerde'],'rathaus bürgeramt gemeinde stadt'],
  'logistik' =>['Logistik & Spedition','📦','bestellung',['log-support','log-liefer'],'transport kurier lager'],
  'bildung'  =>['Personal, Bildung & Coaching','🎓','team',['hr-screening','edu-kurs'],'hr recruiting schule kurs training'],
];
/** Plan-Zuordnung, wo die Telefon-Demo-Basis nicht der beste Agent ist. */
const PROFILE_SECTOR_PLANS = [
  'zahnarzt'=>['praxis-agent','agent-phone'],'arztpraxis'=>['praxis-agent'],'tierarzt'=>['agent-termine','praxis-agent'],'apotheke'=>['agent-phone'],
  'handwerk'=>['agent-phone','hw-angebot'],'stadtwerke'=>['agent-behoerde'],'bank'=>['agent-bank'],'cafe'=>['gastro-agent'],'fitness'=>['agent-termine'],
  'friseur'=>['agent-termine'],'autowerkstatt'=>['agent-autohaus','agent-termine'],'restaurant'=>['agent-bestellung','gastro-agent'],
];
/** Zusätzliche Suchwörter (auch türkisch/englisch), damit die Suche Saç/Diş, Kuaför … findet. */
const PROFILE_SECTOR_KW = [
  'hair-tr'=>'saç ekimi haartransplantation hair transplant türkiye fue dhi','dental-tr'=>'diş kliniği zahn veneers implantat dental türkiye',
  'beauty'=>'kosmetik nagel wimpern spa massage güzellik','aesthetic'=>'ästhetik botox hyaluron klinik estetik','friseur'=>'kuaför barber haare',
  'restaurant'=>'pizza lieferdienst imbiss','zahnarzt'=>'zahn dental','arztpraxis'=>'arzt hausarzt praxis','kanzlei'=>'anwalt recht avukat','steuerberater'=>'steuer buchhaltung',
  'hotel'=>'pension apartment otel','taxi'=>'mietwagen shuttle transfer','immobilien'=>'makler emlak','autowerkstatt'=>'auto kfz werkstatt autohaus',
];
const PROFILE_MAX_SECTORS = 6;

/* ======================================================================= Tabelle */
function profile_bootstrap(): void {
    static $done=false; if($done) return; $done=true;
    th_db()->exec("CREATE TABLE IF NOT EXISTS account_profile (
        account_id INT NOT NULL PRIMARY KEY, kind VARCHAR(10) NULL, company VARCHAR(120) NOT NULL DEFAULT '', sectors TEXT NULL, size VARCHAR(8) NOT NULL DEFAULT '',
        city VARCHAR(80) NOT NULL DEFAULT '', country CHAR(2) NOT NULL DEFAULT '', needs TEXT NULL, langs TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/* ======================================================================= Auswahl-Optionen */
/** Branchen für die Chip-Auswahl: Telefon-Branchen der Startseite + Ergänzungen, sortiert nach Kategorie. id → [label, icon, category, plans[], kw]. */
function profile_sectors(): array {
    static $o=null; if($o!==null) return $o; $o=[];
    foreach(CH_PHONE_SECTORS as $k=>$s){
        $cat=CH_PHONE_SECTOR_CATEGORY[$k] ?? null; if(!$cat || !isset(CH_CATEGORIES[$cat])) continue;
        $plans=PROFILE_SECTOR_PLANS[$k] ?? [];
        if(!$plans){ $fd=$s['from_demo'] ?? ''; $rf=$s['rules_from'] ?? ''; $rf=is_array($rf) ? ($rf[0] ?? '') : $rf;
            foreach([$fd,$rf] as $c) if($c!=='' && isset(CH_PLANS[$c]) && CH_PLANS[$c][4]==='agent'){ $plans=[$c]; break; } }
        if(!$plans) foreach(CH_CATEGORIES[$cat]['plans'] as $pid) if(isset(CH_PLANS[$pid]) && CH_PLANS[$pid][4]==='agent'){ $plans=[$pid]; break; }
        $o[$k]=['label'=>(string)$s['label'],'icon'=>(string)($s['icon'] ?? '✦'),'category'=>$cat,'plans'=>array_values(array_filter($plans,fn($p)=>isset(CH_PLANS[$p]))),'kw'=>PROFILE_SECTOR_KW[$k] ?? ''];
    }
    foreach(PROFILE_SECTOR_EXTRA as $k=>[$l,$i,$c,$p,$kw]) if(!isset($o[$k]) && isset(CH_CATEGORIES[$c])) $o[$k]=['label'=>$l,'icon'=>$i,'category'=>$c,'plans'=>array_values(array_filter($p,fn($x)=>isset(CH_PLANS[$x]))),'kw'=>$kw];
    uasort($o,fn($a,$b)=>(CH_CATEGORIES[$a['category']]['order'] <=> CH_CATEGORIES[$b['category']]['order']));
    return $o;
}
function profile_options(): array {
    $sec=[]; foreach(profile_sectors() as $k=>$s) $sec[]=['id'=>$k,'label'=>$s['label'],'icon'=>$s['icon'],'category'=>$s['category'],'group'=>CH_CATEGORIES[$s['category']]['label'],'kw'=>$s['kw']];
    return ['sectors'=>$sec,
      'needs'=>array_map(fn($k,$n)=>['id'=>$k,'label'=>$n[0],'icon'=>$n[1]],array_keys(PROFILE_NEEDS),PROFILE_NEEDS),
      'private_tasks'=>array_map(fn($k,$n)=>['id'=>$k,'label'=>$n[0],'icon'=>$n[1]],array_keys(PROFILE_PRIVATE_TASKS),PROFILE_PRIVATE_TASKS),
      'sizes'=>array_map(fn($k,$l)=>['id'=>$k,'label'=>$l],array_keys(PROFILE_SIZES),PROFILE_SIZES),
      'langs'=>array_map(fn($k,$l)=>['id'=>$k,'label'=>$l,'top'=>in_array($k,PROFILE_LANGS_TOP,true)],array_keys(PROFILE_LANGS),PROFILE_LANGS),
      'countries'=>array_map(fn($k,$l)=>['id'=>$k,'label'=>$l],array_keys(PROFILE_COUNTRIES),PROFILE_COUNTRIES),
      'max_sectors'=>PROFILE_MAX_SECTORS];
}

/* ======================================================================= Prüfen & Speichern */
/** Freitext: ohne HTML, ohne Steuerzeichen, Leerraum zusammengefasst, gekürzt. */
function profile_text($v, int $max): string {
    if(!is_scalar($v)) return '';
    $s=strip_tags((string)$v); $s=str_replace(['<','>','"','`'],'',$s);
    $s=preg_replace('/[\x00-\x1F\x7F]/u',' ',$s) ?? ''; $s=trim(preg_replace('/\s+/u',' ',$s) ?? '');
    return mb_substr($s,0,$max);
}
/** Liste nur aus erlaubten IDs, eindeutig, begrenzt. */
function profile_ids($v, array $allowed, int $max): array {
    if(!is_array($v)) return []; $o=[];
    foreach($v as $x){ if(!is_string($x)) continue; $x=strtolower(trim($x)); if(in_array($x,$allowed,true) && !in_array($x,$o,true)) $o[]=$x; if(count($o)>=$max) break; }
    return $o;
}
/** Eingabe → geprüftes Profil (unbekannte Werte fallen still weg). */
function profile_clean(array $d): array {
    $kind=is_string($d['kind'] ?? null) && in_array($d['kind'],PROFILE_KINDS,true) ? $d['kind'] : null;
    $country=strtoupper(is_string($d['country'] ?? null) ? trim($d['country']) : '');
    $p=['kind'=>$kind,'company'=>profile_text($d['company'] ?? '',120),'sectors'=>profile_ids($d['sectors'] ?? [],array_keys(profile_sectors()),PROFILE_MAX_SECTORS),
        'size'=>is_string($d['size'] ?? null) && isset(PROFILE_SIZES[$d['size']]) ? $d['size'] : '','city'=>profile_text($d['city'] ?? '',80),
        'country'=>isset(PROFILE_COUNTRIES[$country]) ? $country : '',
        'needs'=>profile_ids($d['needs'] ?? [],array_merge(array_keys(PROFILE_NEEDS),array_keys(PROFILE_PRIVATE_TASKS)),20),
        'langs'=>profile_ids($d['langs'] ?? [],array_keys(PROFILE_LANGS),count(PROFILE_LANGS))];
    if($kind===null){   // „Für wen?“ übersprungen: aus den übrigen Angaben ableiten
        if($p['company']!=='' || $p['sectors'] || $p['size']!=='' || array_intersect($p['needs'],array_keys(PROFILE_NEEDS))) $kind='business';
        elseif(array_intersect($p['needs'],array_keys(PROFILE_PRIVATE_TASKS))) $kind='private';
        $p['kind']=$kind;
    }
    if($kind==='private'){ $p['company']=''; $p['sectors']=[]; $p['size']=''; $p['needs']=array_values(array_filter($p['needs'],fn($n)=>isset(PROFILE_PRIVATE_TASKS[$n]))); }
    elseif($kind==='business') $p['needs']=array_values(array_filter($p['needs'],fn($n)=>isset(PROFILE_NEEDS[$n])));
    return $p;
}
function profile_get(int $aid): ?array {
    if($aid<=0) return null; profile_bootstrap();
    $s=th_db()->prepare("SELECT * FROM account_profile WHERE account_id=?"); $s->execute([$aid]); $r=$s->fetch(PDO::FETCH_ASSOC); if(!$r) return null;
    $j=function($v){ $a=json_decode((string)$v,true); return is_array($a) ? array_values(array_filter($a,'is_string')) : []; };
    return ['kind'=>in_array($r['kind'],PROFILE_KINDS,true) ? $r['kind'] : null,'company'=>(string)$r['company'],'sectors'=>$j($r['sectors']),'size'=>(string)$r['size'],
      'city'=>(string)$r['city'],'country'=>(string)$r['country'],'needs'=>$j($r['needs']),'langs'=>$j($r['langs']),
      'created_at'=>th_dbiso($r['created_at']),'updated_at'=>th_dbiso($r['updated_at'] ?? null)];
}
/** Speichern (Upsert). $dismissed=true und noch kein Profil → leere Zeile (kind NULL) = „später“. Ein vorhandenes Profil bleibt dann unverändert. */
function profile_save(int $aid, array $d, bool $dismissed=false): array {
    profile_bootstrap(); $cur=profile_get($aid);
    if($dismissed){ if($cur) return $cur; $p=profile_clean([]); }
    else $p=profile_clean($d);
    $enc=fn($a)=>json_encode(array_values($a),JSON_UNESCAPED_UNICODE);
    th_db()->prepare("INSERT INTO account_profile(account_id,kind,company,sectors,size,city,country,needs,langs,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,NOW(),NOW())
        ON DUPLICATE KEY UPDATE kind=VALUES(kind),company=VALUES(company),sectors=VALUES(sectors),size=VALUES(size),city=VALUES(city),country=VALUES(country),needs=VALUES(needs),langs=VALUES(langs),updated_at=NOW()")
      ->execute([$aid,$p['kind'],$p['company'],$enc($p['sectors']),$p['size'],$p['city'],$p['country'],$enc($p['needs']),$enc($p['langs'])]);
    return profile_get($aid) ?? $p;
}
function profile_delete(int $aid): void { profile_bootstrap(); th_db()->prepare("DELETE FROM account_profile WHERE account_id=?")->execute([$aid]); }

/* ======================================================================= Empfehlung */
/**
 * Profil → empfohlene Pläne (max. 4) + Studio-Bereiche.
 * Punkte: Hauptagent der Branche 5, weitere Branchen-Agenten 3, Bedarf 2, Telefon/WhatsApp gewünscht und Agent kann es +1.
 * Bereiche: Tabs aus dem Bedarf + Fachmodule (PROFILE_MOD_FOR) und Kanäle der empfohlenen Agenten.
 * @return array{plans:list<array>,areas:list<string>,primary:?string}
 */
function ch_profile_recommend(?array $profile): array {
    $p=profile_clean($profile ?? []); $score=[]; $why=[]; $order=array_flip(array_keys(CH_PLANS));
    $add=function(string $id,int $pts,string $reason) use(&$score,&$why){ if(!isset(CH_PLANS[$id])) return; $score[$id]=($score[$id] ?? 0)+$pts; $why[$id] ??= $reason; };
    $areas=[];
    if($p['kind']==='private'){
        foreach($p['needs'] as $n) if(isset(PROFILE_PRIVATE_TASKS[$n])) $add(PROFILE_PRIVATE_TASKS[$n][2],4,'Für „'.PROFILE_PRIVATE_TASKS[$n][0].'“');
        if(!$score) $add('privat-assistent',1,'Dein Alltags-Assistent für Briefe, Termine und Fragen');
        $areas=['assistant','agents'];
    } elseif($p['kind']==='business' || $p['sectors'] || $p['needs']){
        $S=profile_sectors();
        foreach($p['sectors'] as $sid){ $s=$S[$sid] ?? null; if(!$s) continue;
            foreach($s['plans'] as $i=>$pid) $add($pid,$i===0 ? 5 : 3,'Passt zu deiner Branche „'.$s['label'].'“'); }
        foreach($p['needs'] as $n){ $nd=PROFILE_NEEDS[$n] ?? null; if(!$nd) continue; if($nd[2]!=='') $areas[]=$nd[2];
            foreach($nd[3] as $pid) $add($pid,2,'Für „'.$nd[0].'“'); }
        $wantVoice=array_intersect($p['needs'],['phone','whatsapp']);
        if($wantVoice) foreach(array_keys($score) as $pid) if(in_array('phone',ch_plan_channels($pid),true)) $score[$pid]++;
        if(!$score){ $add('agent-phone',1,'Nimmt Anrufe an und beantwortet Fragen rund um die Uhr'); $add('agent-support',1,'Beantwortet Fragen auf deiner Website'); }
    }
    uksort($score,fn($a,$b)=>($score[$b] <=> $score[$a]) ?: ($order[$a] <=> $order[$b]));
    $plans=[];
    foreach(array_slice(array_keys($score),0,4) as $pid){ $pl=ch_plan($pid); if(!$pl) continue;
        $ch=$pl['channels'];
        foreach(PROFILE_MOD_FOR as $tab=>$ids) if(in_array($pid,$ids,true)) $areas[]=$tab;
        if($pl['kind']==='agent'){ if(in_array('phone',$ch,true)) $areas[]='phone'; if(in_array('whatsapp',$ch,true)) $areas[]='whatsapp'; if(in_array('web',$ch,true)) $areas[]='website'; }
        $plans[]=['id'=>$pid,'name'=>$pl['name'],'desc'=>mb_substr($pl['desc'],0,170).(mb_strlen($pl['desc'])>170 ? '…' : ''),'eur_month'=>$pl['cents']/100,'kind'=>$pl['kind'],
          'sector'=>$pl['sector'],'channels'=>$ch,'reason'=>$why[$pid] ?? '','checkout'=>'/chat/tokens-checkout.php?plan='.rawurlencode($pid)];
    }
    if($plans && $p['kind']!=='private') $areas[]='kunden';
    return ['plans'=>$plans,'areas'=>array_values(array_unique($areas)),'primary'=>$plans[0]['id'] ?? null];
}

/** Kurzfassung für Systemprompts (Studio-Assistent): höchstens 300 Zeichen, '' ohne Profil. */
function ch_profile_brief(int $aid): string {
    try{ $p=profile_get($aid); }catch(Throwable $e){ return ''; }
    if(!$p || !$p['kind']) return '';
    $parts=[];
    if($p['kind']==='private'){
        $parts[]='Privatperson';
        if($p['needs']) $parts[]='möchte erledigen: '.implode(', ',array_map(fn($n)=>PROFILE_PRIVATE_TASKS[$n][0] ?? $n,$p['needs']));
    } else {
        $S=profile_sectors();
        $parts[]='Betrieb'.($p['company']!=='' ? ' „'.$p['company'].'“' : '');
        if($p['sectors']) $parts[]='Branche: '.implode(', ',array_map(fn($s)=>$S[$s]['label'] ?? $s,$p['sectors']));
        if($p['size']!=='') $parts[]='Größe: '.(PROFILE_SIZES[$p['size']] ?? $p['size']).($p['size']==='solo' ? '' : ' Personen');
        if($p['needs']) $parts[]='Bedarf: '.implode(', ',array_map(fn($n)=>PROFILE_NEEDS[$n][0] ?? $n,$p['needs']));
    }
    $loc=trim($p['city'].($p['country']!=='' ? ($p['city']!=='' ? ', ' : '').$p['country'] : ''));
    if($loc!=='') $parts[]='Ort: '.$loc;
    if($p['langs']) $parts[]='Kundensprachen: '.strtoupper(implode(', ',$p['langs']));
    $s=implode(' · ',$parts);
    return mb_strlen($s)>300 ? mb_substr($s,0,299).'…' : $s;
}

/** Antwort für GET/POST /v1/profile. */
function profile_response(int $aid, ?array $p=null): array {
    $p ??= profile_get($aid);
    return ['profile'=>$p,'exists'=>$p!==null,'complete'=>$p!==null && $p['kind']!==null,'rec'=>ch_profile_recommend($p),'brief'=>ch_profile_brief($aid),'options'=>profile_options()];
}
