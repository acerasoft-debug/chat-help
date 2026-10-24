<?php
/**
 * ChatHelp — Website-Baukasten („Website erstellen“): Datenmodell, KI-Entwurf, Übersetzung, Bilder, Anfragen, Veröffentlichung, ZIP-Export
 * ======================================================================================================================================
 * Jeder Betrieb (und Privatpersonen: Portfolio, Lebenslauf, Event) bekommt in wenigen Minuten eine eigene Website mit eingebautem ChatHelp-Agenten
 * (Website-Chat, WhatsApp, Telefon, Termin-/Bestell-Knöpfe zu den vorhandenen Kundenseiten).
 *
 * Tabellen (idempotent, site_bootstrap):
 *   sites        id, account_id, slug (eindeutig), status draft|published, template, lang_default, langs JSON, theme JSON, data JSON (Abschnitte + Texte je Sprache),
 *                seo JSON, custom_domain, pub (JSON-Schnappschuss der veröffentlichten Fassung), created_at, updated_at, published_at, exported_at
 *   site_assets  Bilder (JPEG/PNG/WebP ≤ 2 MB, mit GD neu kodiert, Metadaten entfernt) unter chat/uploads/sites/<site-id>/<zufall>.<webp|jpg|png>
 *   site_leads   Kontaktformular (Name, Kontakt, Nachricht, Sprache, IP nur als Prüfsumme, Status new|done) → Postfach/Push/E-Mail des Betriebs (ch_push lead_new)
 *
 * Grundsätze: Drafts kostenlos, KI kostet Tokens (Entwurf SITE_PRICE_GENERATE, Übersetzung SITE_PRICE_TRANSLATE je Sprache – gleiche Größenordnung wie CH_PRICING
 * agent_run 10 / seo 8), Veröffentlichen und ZIP-Export brauchen ein aktives Paket (jeder bezahlte Agent/Plan) oder den kleinen Plan „website-pro“ (plans.php).
 * Die KI liefert ausschließlich JSON (wird geprüft, nur Text, nie HTML); unbekannte Fakten erscheinen als Platzhalter [[…]] („Bitte ergänzen“), die das Studio markiert
 * und die das Veröffentlichen blockieren. Öffentliche Ausgabe: chat/site.php (site-render.php), alles escaped.
 *
 * REST (Studio, Bearer-Key; tokens-api.php → site_api):
 *   GET  /v1/sites                        → {sites[], templates[], presets, fonts, langs, can_publish, plan, prices, limits}
 *   POST /v1/sites {template, lang, name?, slug?}            → neuer Entwurf (vorbelegt aus Profil + aktiven Modulen)
 *   GET  /v1/sites/slug-check?slug=&id=   → {ok, slug, reason}
 *   GET  /v1/sites/<id>                   → Website inkl. Bilder, Hinweise (issues), Vorschau-URL, Module/Knöpfe, Widget-Status
 *   POST /v1/sites/<id> {template?, theme?, data?, seo?, langs?, lang_default?, slug?, custom_domain?}   → speichern (Teil-Update)
 *   POST /v1/sites/<id>/generate {lang?, answers:{tone,audience,offer,usp,extra}}   → KI-Entwurf (Tokens)
 *   POST /v1/sites/<id>/translate {langs:[…]}                → KI-Übersetzung je Sprache (Tokens)
 *   POST /v1/sites/<id>/upload {data:"data:image/…;base64,…", kind}                → Bild
 *   POST /v1/sites/<id>/asset-delete {asset_id}
 *   POST /v1/sites/<id>/publish · /unpublish · /delete
 *   GET  /v1/sites/<id>/leads · POST /v1/sites/<id>/lead-status {lead_id, status:new|done|delete}
 *   GET  /v1/sites/<id>/export            → ZIP (statische Seiten + Bilder) für eigenes Hosting
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';

const SITE_BASE = 'https://chat-help.com';
/** Sprachen in Prioritätsreihenfolge (DE, FR, TR, AZ, ES, dann IT, EN, dann RU, NL, SV, NO, FI, JA). */
const SITE_LANGS = ['de','fr','tr','az','es','it','en','ru','nl','sv','no','fi','ja'];
const SITE_LANG_NAMES = ['de'=>'Deutsch','fr'=>'Français','tr'=>'Türkçe','az'=>'Azərbaycanca','es'=>'Español','it'=>'Italiano','en'=>'English','ru'=>'Русский','nl'=>'Nederlands','sv'=>'Svenska','no'=>'Norsk','fi'=>'Suomi','ja'=>'日本語'];
const SITE_LANG_EN = ['de'=>'German','fr'=>'French','tr'=>'Turkish','az'=>'Azerbaijani','es'=>'Spanish','it'=>'Italian','en'=>'English','ru'=>'Russian','nl'=>'Dutch','sv'=>'Swedish','no'=>'Norwegian (Bokmål)','fi'=>'Finnish','ja'=>'Japanese'];
const SITE_LANG_TAGS = ['de'=>'de-DE','fr'=>'fr-FR','tr'=>'tr-TR','az'=>'az-AZ','es'=>'es-ES','it'=>'it-IT','en'=>'en-GB','ru'=>'ru-RU','nl'=>'nl-NL','sv'=>'sv-SE','no'=>'nb-NO','fi'=>'fi-FI','ja'=>'ja-JP'];
/** Plan „Website“ (plans.php): Veröffentlichen ohne Agenten-Paket. */
const SITE_PLAN = 'website-pro';
/** Tokens je KI-Vorgang (vgl. CH_PRICING: agent_run 10, seo 8, generic 10): kompletter Entwurf mit allen Abschnitten, SEO und FAQ = 20; je Zielsprache = 8. */
const SITE_PRICE_GENERATE = 20;
const SITE_PRICE_TRANSLATE = 8;
const SITE_MAX_SITES = 5;
const SITE_MAX_ASSETS = 40;
const SITE_IMG_MAX = 2097152;            // 2 MB je Bild (Eingang)
const SITE_IMG_EDGE = 2000;              // größte Kantenlänge nach dem Neu-Kodieren
const SITE_QUOTA = 83886080;             // 80 MB Bilder je Konto
const SITE_DATA_MAX = 300000;            // Byte JSON je Website (Inhalte)
const SITE_SECTIONS = ['hero','services','about','team','gallery','hours','location','reviews','faq','contact'];
/** Texte je Sprache: Schlüssel → Höchstlänge. */
const SITE_TEXT_KEYS = ['hero_kicker'=>80,'hero_title'=>120,'hero_text'=>420,'cta_label'=>40,'about_title'=>100,'about_text'=>2400,'services_title'=>100,'services_text'=>600,
  'team_title'=>100,'team_text'=>600,'gallery_title'=>100,'hours_title'=>100,'hours_note'=>300,'location_title'=>100,'location_text'=>600,'reviews_title'=>100,'faq_title'=>100,
  'contact_title'=>100,'contact_text'=>600,'footer_text'=>300];
const SITE_CTAS = ['auto','book','order','room','ride','call','whatsapp','chat','contact','callback'];
const SITE_RESERVED = ['admin','api','app','chat','studio','www','mail','demo','test','help','support','site','sites','static','assets','login','account','billing','preview',
  'null','undefined','chathelp','chat-help','impressum','datenschutz','privacy','imprint','agb','new','neu'];

/**
 * Vorlagen: name, tag (Kurzbeschreibung DE), sectors (Profil-Branchen), schema (schema.org-Typ), hero (split|full|center|card), font, preset, services (Darstellung),
 * sections (Standard-Reihenfolge; nicht genannte Abschnitte sind aus), cta (Standard-Aktion).
 */
const SITE_TEMPLATES = [
  'gastro'   => ['name'=>'Trattoria','tag'=>'Restaurant, Café, Bar & Lieferdienst','sectors'=>['restaurant','cafe'],'schema'=>'Restaurant','hero'=>'full','font'=>'classic','preset'=>'terracotta','services'=>'menu',
                 'sections'=>['hero','services','about','gallery','hours','location','reviews','faq','contact'],'cta'=>'order'],
  'beauty'   => ['name'=>'Atelier','tag'=>'Salon, Kosmetik, Nails, Spa & Ästhetik','sectors'=>['friseur','beauty','aesthetic','fitness'],'schema'=>'BeautySalon','hero'=>'split','font'=>'elegant','preset'=>'rose','services'=>'list',
                 'sections'=>['hero','services','about','team','gallery','reviews','hours','location','faq','contact'],'cta'=>'book'],
  'praxis'   => ['name'=>'Klinik','tag'=>'Praxis, Gesundheit, Kanzlei & Beratung','sectors'=>['zahnarzt','arztpraxis','tierarzt','apotheke','hair-tr','dental-tr','kanzlei','steuerberater','versicherung','bank','inkasso','behoerde'],
                 'schema'=>'MedicalBusiness','hero'=>'split','font'=>'humanist','preset'=>'teal','services'=>'cards','sections'=>['hero','services','about','team','hours','location','faq','contact'],'cta'=>'book'],
  'handwerk' => ['name'=>'Werkstatt','tag'=>'Handwerk, Werkstatt, Service & Immobilien','sectors'=>['handwerk','autowerkstatt','hausverwaltung','immobilien','stadtwerke'],'schema'=>'HomeAndConstructionBusiness','hero'=>'card','font'=>'grotesk','preset'=>'ember','services'=>'cards',
                 'sections'=>['hero','services','about','gallery','reviews','faq','location','hours','contact'],'cta'=>'call'],
  'hotel'    => ['name'=>'Riviera','tag'=>'Hotel, Pension & Ferienwohnung','sectors'=>['hotel'],'schema'=>'LodgingBusiness','hero'=>'full','font'=>'elegant','preset'=>'navy','services'=>'rooms',
                 'sections'=>['hero','about','services','gallery','reviews','location','faq','contact'],'cta'=>'room'],
  'taxi'     => ['name'=>'Mobil','tag'=>'Taxi, Shuttle, Transfer & Kurier','sectors'=>['taxi','logistik'],'schema'=>'TaxiService','hero'=>'center','font'=>'grotesk','preset'=>'night','services'=>'tariff',
                 'sections'=>['hero','services','about','hours','location','reviews','faq','contact'],'cta'=>'ride'],
  'shop'     => ['name'=>'Boutique','tag'=>'Laden, Onlineshop & Einzelhandel','sectors'=>['shop','agentur'],'schema'=>'Store','hero'=>'split','font'=>'modern','preset'=>'mono','services'=>'cards',
                 'sections'=>['hero','services','gallery','about','reviews','hours','location','faq','contact'],'cta'=>'contact'],
  'personal' => ['name'=>'Portfolio','tag'=>'Portfolio, Lebenslauf, Event & Vereine','sectors'=>['bildung'],'schema'=>'Person','hero'=>'center','font'=>'editorial','preset'=>'gold','services'=>'cards',
                 'sections'=>['hero','about','services','gallery','reviews','faq','contact'],'cta'=>'contact'],
];
/** Branchen mit genauerem schema.org-Typ als die Vorlage. */
const SITE_SECTOR_SCHEMA = ['restaurant'=>'Restaurant','cafe'=>'CafeOrCoffeeShop','friseur'=>'HairSalon','beauty'=>'BeautySalon','aesthetic'=>'MedicalClinic','fitness'=>'HealthClub',
  'zahnarzt'=>'Dentist','arztpraxis'=>'Physician','tierarzt'=>'VeterinaryCare','apotheke'=>'Pharmacy','dental-tr'=>'Dentist','hair-tr'=>'MedicalClinic','kanzlei'=>'Attorney','steuerberater'=>'AccountingService',
  'versicherung'=>'InsuranceAgency','bank'=>'BankOrCreditUnion','handwerk'=>'HomeAndConstructionBusiness','autowerkstatt'=>'AutoRepair','immobilien'=>'RealEstateAgent','hausverwaltung'=>'RealEstateAgent',
  'hotel'=>'Hotel','taxi'=>'TaxiService','shop'=>'Store','behoerde'=>'GovernmentOffice'];
/** Farb-Vorlagen: bg, sur (Flächen), ink (Text), accent, accent2. dark = dunkles Thema. */
const SITE_PRESETS = [
  'gold'       => ['label'=>'Gold','bg'=>'#faf8f3','sur'=>'#ffffff','ink'=>'#0f1222','accent'=>'#b8862b','accent2'=>'#ecd08a'],
  'terracotta' => ['label'=>'Terracotta','bg'=>'#fbf6f0','sur'=>'#ffffff','ink'=>'#2a1c14','accent'=>'#b5532f','accent2'=>'#e9b872'],
  'rose'       => ['label'=>'Rosé','bg'=>'#fbf5f3','sur'=>'#ffffff','ink'=>'#2b1f24','accent'=>'#a85a6a','accent2'=>'#ebc7bf'],
  'teal'       => ['label'=>'Petrol','bg'=>'#f4f9fa','sur'=>'#ffffff','ink'=>'#0d2430','accent'=>'#0f7482','accent2'=>'#a7dbe0'],
  'navy'       => ['label'=>'Navy','bg'=>'#f6f4ef','sur'=>'#ffffff','ink'=>'#13233a','accent'=>'#1f4e79','accent2'=>'#c8a96a'],
  'forest'     => ['label'=>'Wald','bg'=>'#f5f7f3','sur'=>'#ffffff','ink'=>'#14211b','accent'=>'#2f6b4f','accent2'=>'#c9dccf'],
  'ember'      => ['label'=>'Glut','bg'=>'#f3f2ef','sur'=>'#ffffff','ink'=>'#16181d','accent'=>'#c4520a','accent2'=>'#ffc08a'],
  'plum'       => ['label'=>'Pflaume','bg'=>'#f8f5fa','sur'=>'#ffffff','ink'=>'#221a2b','accent'=>'#6d4c8f','accent2'=>'#d9c9ea'],
  'mono'       => ['label'=>'Mono','bg'=>'#ffffff','sur'=>'#f6f6f4','ink'=>'#111111','accent'=>'#2f5d50','accent2'=>'#dfe8e3'],
  'night'      => ['label'=>'Nacht','bg'=>'#0f1012','sur'=>'#1a1c20','ink'=>'#f3f1ea','accent'=>'#f5c518','accent2'=>'#ffe48a','dark'=>true],
];
/** Schrift-Paare nur aus Systemschriften (keine externen Schriftdienste → DSGVO, schnell). */
const SITE_FONTS = [
  'classic'   => ['label'=>'Klassisch','h'=>'"Iowan Old Style","Palatino Linotype",Palatino,"Book Antiqua","URW Palladio L",Georgia,serif','b'=>'system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Noto Sans",sans-serif','w'=>600],
  'elegant'   => ['label'=>'Elegant','h'=>'Didot,"Bodoni 72","Bodoni MT","Noto Serif Display","Iowan Old Style",Georgia,serif','b'=>'"Avenir Next",Avenir,"Segoe UI",system-ui,-apple-system,Roboto,Arial,sans-serif','w'=>500],
  'modern'    => ['label'=>'Modern','h'=>'"Avenir Next","Segoe UI Variable Display","Segoe UI",system-ui,-apple-system,Roboto,Arial,sans-serif','b'=>'system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif','w'=>700],
  'humanist'  => ['label'=>'Freundlich','h'=>'Optima,Candara,"Gill Sans","Gill Sans MT","Noto Sans",system-ui,sans-serif','b'=>'system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif','w'=>600],
  'editorial' => ['label'=>'Editorial','h'=>'"New York",Charter,"Bitstream Charter","Sitka Text",Cambria,Georgia,serif','b'=>'system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif','w'=>500],
  'grotesk'   => ['label'=>'Kräftig','h'=>'"Helvetica Neue",Helvetica,Arial,"Nimbus Sans","Liberation Sans",sans-serif','b'=>'system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif','w'=>800],
];
/** Ländervorwahlen für tel:/wa.me (nationale Schreibweise → E.164). */
const SITE_CC = ['DE'=>'49','AT'=>'43','CH'=>'41','TR'=>'90','AZ'=>'994','FR'=>'33','ES'=>'34','IT'=>'39','NL'=>'31','BE'=>'32','LU'=>'352','GB'=>'44','SE'=>'46','NO'=>'47','FI'=>'358','PL'=>'48','JP'=>'81','US'=>'1'];
const SITE_COUNTRY_LANG = ['DE'=>'de','AT'=>'de','CH'=>'de','TR'=>'tr','AZ'=>'az','FR'=>'fr','BE'=>'fr','LU'=>'fr','ES'=>'es','IT'=>'it','NL'=>'nl','GB'=>'en','US'=>'en','SE'=>'sv','NO'=>'no','FI'=>'fi','JP'=>'ja','PL'=>'en'];

/* ======================================================================= Tabellen */
function site_bootstrap(): void {
    static $done=false; if($done) return; $done=true; $p=th_db();
    $p->exec("CREATE TABLE IF NOT EXISTS sites (
        id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, slug VARCHAR(48) NOT NULL, status VARCHAR(12) NOT NULL DEFAULT 'draft', template VARCHAR(20) NOT NULL DEFAULT 'gastro',
        lang_default CHAR(2) NOT NULL DEFAULT 'de', langs TEXT NULL, theme TEXT NULL, data MEDIUMTEXT NULL, seo TEXT NULL, custom_domain VARCHAR(190) NULL, pub MEDIUMTEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL, published_at DATETIME NULL, exported_at DATETIME NULL,
        UNIQUE KEY uq_slug (slug), INDEX(account_id), INDEX(custom_domain)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS site_assets (
        id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL, account_id INT NOT NULL, kind VARCHAR(12) NOT NULL DEFAULT 'image', file VARCHAR(80) NOT NULL, mime VARCHAR(20) NOT NULL,
        w INT NOT NULL DEFAULT 0, h INT NOT NULL DEFAULT 0, bytes INT NOT NULL DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX(site_id), INDEX(account_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS site_leads (
        id INT AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL, account_id INT NOT NULL, name VARCHAR(120) NOT NULL DEFAULT '', contact VARCHAR(190) NOT NULL DEFAULT '', message TEXT NULL,
        lang CHAR(2) NOT NULL DEFAULT 'de', ip_hash CHAR(32) NOT NULL DEFAULT '', status VARCHAR(10) NOT NULL DEFAULT 'new', created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX(site_id, created_at), INDEX(account_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/* ======================================================================= Prüfen & Säubern */
/** Einzeiliger/mehrzeiliger Freitext ohne HTML und Steuerzeichen (Zeilenumbrüche nur mit $multi), gekürzt. */
function site_txt($v, int $max, bool $multi=false): string {
    if(!is_scalar($v)) return ''; $s=(string)$v;
    if(!mb_check_encoding($s,'UTF-8')) $s=mb_convert_encoding($s,'UTF-8','UTF-8');
    $s=strip_tags(str_replace(["\r\n","\r"],"\n",$s)); $s=str_replace(['<','>'],'',$s);
    $s=preg_replace('/[\x00-\x09\x0B-\x1F\x7F\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u',' ',$s) ?? '';
    if($multi){ $s=preg_replace('/[ \t]+/u',' ',$s) ?? ''; $s=preg_replace("/ *\n */u","\n",$s) ?? ''; $s=preg_replace("/\n{3,}/u","\n\n",$s) ?? ''; }
    else $s=preg_replace('/\s+/u',' ',$s) ?? '';
    return mb_substr(trim($s),0,$max);
}
function site_lang($l): string { $l=is_string($l) ? strtolower(trim($l)) : ''; if($l==='nb'||$l==='nn') $l='no'; return in_array($l,SITE_LANGS,true) ? $l : ''; }
function site_hex($v, string $def): string { return is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/',trim($v)) ? strtolower(trim($v)) : $def; }
function site_phone_clean($v): string { $s=preg_replace('/[^0-9+ ()\/.-]/','',is_scalar($v) ? (string)$v : '') ?? ''; $s=trim(preg_replace('/\s+/',' ',$s) ?? ''); $d=preg_replace('/\D/','',$s); return (strlen($d)>=6 && strlen($d)<=17) ? mb_substr($s,0,32) : ''; }
function site_email_clean($v): string { $s=trim(is_scalar($v) ? (string)$v : ''); return ($s!=='' && strlen($s)<=190 && filter_var($s,FILTER_VALIDATE_EMAIL)) ? strtolower($s) : ''; }
/** Telefon → E.164 (für tel: und wa.me). */
function site_e164(string $phone, string $country): string { if($phone==='') return ''; $cc=SITE_CC[strtoupper($country)] ?? '49'; return th_e164($phone,$cc); }
/** Sprach-Map {de:"…",fr:"…"} säubern. */
function site_lmap($v, int $max, bool $multi=false): array {
    $o=[]; if(is_string($v)) $v=['de'=>$v]; if(!is_array($v)) return $o;
    foreach($v as $l=>$t){ $l=site_lang($l); if($l==='') continue; $t=site_txt($t,$max,$multi); if($t!=='') $o[$l]=$t; }
    return $o;
}
/** Öffnungszeiten: {"0".."6": [["HH:MM","HH:MM"],…]} (0 = Sonntag) oder null (= unbekannt). */
function site_hours_clean($v): ?array {
    if(!is_array($v)) return null; $o=[]; $any=false; $hm='/^(?:[01]\d|2[0-3]):[0-5]\d$|^24:00$/';
    for($d=0;$d<=6;$d++){ $rows=[]; foreach(array_slice((array)($v[(string)$d] ?? $v[$d] ?? []),0,3) as $iv){ if(!is_array($iv)) continue; $iv=array_values($iv); if(count($iv)<2) continue;
            $a=trim((string)$iv[0]); $b=trim((string)$iv[1]); if(preg_match('/^\d:\d\d$/',$a)) $a='0'.$a; if(preg_match('/^\d:\d\d$/',$b)) $b='0'.$b;
            if(preg_match($hm,$a) && preg_match($hm,$b) && $a!=='24:00'){ $rows[]=[$a,$b]; $any=true; } }
        $o[(string)$d]=$rows; }
    return $any ? $o : null;
}
function site_domain_clean($v): string {
    $s=strtolower(trim(is_scalar($v) ? (string)$v : '')); $s=preg_replace('#^[a-z]+://#','',$s) ?? ''; $s=explode('/',$s)[0]; $s=rtrim(explode(':',$s)[0],'.');
    if(function_exists('idn_to_ascii') && preg_match('/[^\x00-\x7F]/',$s)){ $x=@idn_to_ascii($s,IDNA_DEFAULT,INTL_IDNA_VARIANT_UTS46); if(is_string($x)) $s=$x; }
    if($s==='' || strlen($s)>190 || !preg_match('/^(?=.{4,190}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/',$s)) return '';
    if($s==='chat-help.com' || str_ends_with($s,'.chat-help.com') || in_array($s,['localhost'],true)) return '';
    return $s;
}
/** Slug aus Text: Umlaute/türkische/aserbaidschanische Zeichen umschreiben, nur a-z 0-9 -. */
function site_slugify(string $s): string {
    $s=mb_strtolower(trim($s));
    $s=strtr($s,['ä'=>'ae','ö'=>'oe','ü'=>'ue','ß'=>'ss','ç'=>'c','ğ'=>'g','ı'=>'i','i̇'=>'i','ş'=>'s','ə'=>'e','é'=>'e','è'=>'e','ê'=>'e','à'=>'a','á'=>'a','â'=>'a','ñ'=>'n','ó'=>'o','ò'=>'o','ô'=>'o','ú'=>'u','ù'=>'u','í'=>'i','å'=>'a','ø'=>'o','æ'=>'ae','&'=>'-und-']);
    if(function_exists('iconv')){ $x=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s); if(is_string($x) && $x!=='') $s=$x; }
    $s=preg_replace('/[^a-z0-9]+/','-',strtolower($s)) ?? ''; $s=trim(preg_replace('/-+/','-',$s) ?? '','-');
    return substr($s,0,40);
}
/** @return array{0:bool,1:string,2:string} ok, normalisierter Slug, Grund (slug_short|slug_reserved|slug_taken|slug_invalid) */
function site_slug_check(string $raw, int $exceptId=0): array {
    site_bootstrap(); $s=site_slugify($raw);
    if($s==='' || !preg_match('/^[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])$/',$s)) return [false,$s,strlen($s)<3 ? 'slug_short' : 'slug_invalid'];
    if(strlen($s)<3) return [false,$s,'slug_short'];
    if(in_array($s,SITE_RESERVED,true)) return [false,$s,'slug_reserved'];
    $q=th_db()->prepare("SELECT id FROM sites WHERE slug=? AND id<>?"); $q->execute([$s,$exceptId]);
    if($q->fetchColumn()) return [false,$s,'slug_taken'];
    return [true,$s,''];
}
/** Freien Slug finden (Basis, Basis-2, …). */
function site_slug_free(string $base, int $exceptId=0): string {
    $b=site_slugify($base); if(strlen($b)<3) $b=($b!=='' ? $b.'-' : '').'site'; if(in_array($b,SITE_RESERVED,true)) $b.='-site'; $b=substr($b,0,34);
    for($i=0;$i<60;$i++){ $c=$i===0 ? $b : $b.'-'.($i<10 ? $i+1 : bin2hex(random_bytes(2))); [$ok,$s]=site_slug_check($c,$exceptId); if($ok) return $s; }
    return substr($b,0,30).'-'.bin2hex(random_bytes(3));
}

/* ======================================================================= Lesen */
function site_decode(array $r): array {
    $j=fn($v,$d)=>is_array($x=json_decode((string)$v,true)) ? $x : $d;
    $langs=array_values(array_filter($j($r['langs'],[]),fn($l)=>in_array($l,SITE_LANGS,true)));
    $def=site_lang($r['lang_default']) ?: 'de'; if(!in_array($def,$langs,true)) array_unshift($langs,$def);
    return ['id'=>(int)$r['id'],'account_id'=>(int)$r['account_id'],'slug'=>(string)$r['slug'],'status'=>$r['status']==='published' ? 'published' : 'draft','template'=>isset(SITE_TEMPLATES[$r['template']]) ? $r['template'] : 'gastro',
      'lang_default'=>$def,'langs'=>$langs,'theme'=>$j($r['theme'],[]),'data'=>$j($r['data'],[]),'seo'=>$j($r['seo'],[]),'custom_domain'=>$r['custom_domain'] ? (string)$r['custom_domain'] : '',
      'pub'=>$r['pub'] ? $j($r['pub'],null) : null,'created_at'=>$r['created_at'],'updated_at'=>$r['updated_at'],'published_at'=>$r['published_at'],'exported_at'=>$r['exported_at'] ?? null];
}
/** Website des Kontos (Besitzprüfung) oder null. */
function site_get(int $id, int $aid): ?array {
    site_bootstrap(); if($id<=0 || $aid<=0) return null;
    $s=th_db()->prepare("SELECT * FROM sites WHERE id=? AND account_id=?"); $s->execute([$id,$aid]); $r=$s->fetch(PDO::FETCH_ASSOC); return $r ? site_decode($r) : null;
}
function site_by_slug(string $slug): ?array {
    site_bootstrap(); $slug=strtolower(trim($slug)); if(!preg_match('/^[a-z0-9-]{3,48}$/',$slug)) return null;
    $s=th_db()->prepare("SELECT * FROM sites WHERE slug=?"); $s->execute([$slug]); $r=$s->fetch(PDO::FETCH_ASSOC); return $r ? site_decode($r) : null;
}
function site_by_domain(string $host): ?array {
    site_bootstrap(); $h=site_domain_clean($host); if($h==='') return null;
    $alt=str_starts_with($h,'www.') ? substr($h,4) : 'www.'.$h;
    $s=th_db()->prepare("SELECT * FROM sites WHERE custom_domain IN (?,?) AND status='published' ORDER BY (custom_domain=?) DESC LIMIT 1"); $s->execute([$h,$alt,$h]); $r=$s->fetch(PDO::FETCH_ASSOC); return $r ? site_decode($r) : null;
}
function site_list(int $aid): array {
    site_bootstrap(); $s=th_db()->prepare("SELECT * FROM sites WHERE account_id=? ORDER BY id DESC"); $s->execute([$aid]); return array_map('site_decode',$s->fetchAll(PDO::FETCH_ASSOC));
}
function site_count(int $aid): int { site_bootstrap(); $s=th_db()->prepare("SELECT COUNT(*) FROM sites WHERE account_id=?"); $s->execute([$aid]); return (int)$s->fetchColumn(); }

/* ======================================================================= Module, Knöpfe, Widget */
function site_safe(callable $f, $def=null){ try{ return $f(); }catch(Throwable $e){ return $def; } }
/** Aktive Fachmodule eines Kontos + Ziele für die Knöpfe (Buchungsseite, Rückrufseite). Nur Module, die wirklich eingeschaltet sind. */
function site_modules(int $aid): array {
    $m=['appt'=>false,'order'=>false,'hotel'=>false,'ride'=>false,'callback'=>false,'book_url'=>'','callback_url'=>''];
    if($aid<=0) return $m;
    if(!function_exists('appt_settings') && is_file(__DIR__.'/appt-lib.php')) require_once __DIR__.'/appt-lib.php';
    if(!function_exists('ord_settings') && is_file(__DIR__.'/order-lib.php')) require_once __DIR__.'/order-lib.php';
    foreach(['hotel'=>'hotel-lib.php','ride'=>'ride-lib.php','callback'=>'callback-lib.php'] as $k=>$f) if(is_file(__DIR__.'/'.$f)) require_once __DIR__.'/'.$f;
    $m['appt']=(bool)site_safe(fn()=>function_exists('appt_settings') && appt_settings($aid)['appt_on']===1,false);
    $m['order']=(bool)site_safe(fn()=>function_exists('ord_settings') && ord_settings($aid)['order_on']===1,false);
    $m['hotel']=(bool)site_safe(fn()=>function_exists('hotel_settings') && hotel_settings($aid)['hotel_on']===1,false);
    $m['ride']=(bool)site_safe(fn()=>function_exists('ride_settings') && ride_settings($aid)['ride_on']===1,false);
    $m['callback']=(bool)site_safe(function() use($aid){ if(!function_exists('cb_settings')) return false; $s=th_db()->prepare("SELECT cb_on FROM callback_settings WHERE account_id=?"); $s->execute([$aid]); return (int)$s->fetchColumn()===1; },false);
    if($m['appt']) $m['book_url']=(string)site_safe(fn()=>appt_booking_url($aid),'');
    if($m['callback']) $m['callback_url']=(string)site_safe(fn()=>'https://chat-help.com/chat/callback.php?k='.rawurlencode(cb_form_key($aid)),'');
    return $m;
}
/** Widget-Schlüssel, der auf der Website laufen darf (widget_on, Plan aktiv, Domainliste leer oder enthält chat-help.com / die eigene Domain). */
function site_widget(int $aid, string $customDomain=''): array {
    if($aid<=0) return ['key'=>null,'state'=>'none'];
    $s=th_db()->prepare("SELECT plan,widget_key,widget_domain,widget_on FROM agent_configs WHERE account_id=? AND widget_key IS NOT NULL ORDER BY widget_on DESC, id"); $s->execute([$aid]);
    $state='none';
    foreach($s->fetchAll(PDO::FETCH_ASSOC) as $c){
        if((int)$c['widget_on']!==1){ continue; }
        if(!site_safe(fn()=>th_plan_active($aid,(string)$c['plan']),false)){ $state='plan_inactive'; continue; }
        $dom=array_filter(array_map('trim',preg_split('/[\s,]+/',strtolower((string)$c['widget_domain'])) ?: []));
        if($dom){ $ok=false; foreach($dom as $d){ $d=rtrim(preg_replace('#^https?://#','',$d) ?? '','/'); foreach(array_filter(['chat-help.com',$customDomain]) as $h) if($h===$d || str_ends_with($h,'.'.$d)) $ok=true; }
            if(!$ok){ $state='domain'; continue; } }
        return ['key'=>(string)$c['widget_key'],'state'=>'ok','plan'=>(string)$c['plan']];
    }
    return ['key'=>null,'state'=>$state];
}
/** Leistungen aus den Modulen (Speisekarte, Termin-Leistungen, Zimmer) – echte Daten, keine KI. */
function site_module_services(int $aid, array $mods): array {
    $o=['menu'=>[],'services'=>[],'rooms'=>[]];
    if($aid<=0) return $o;
    if($mods['order'] ?? false) $o['menu']=array_slice(array_map(fn($r)=>['category'=>(string)$r['category'],'name'=>(string)$r['name'],'desc'=>(string)$r['description'],'price_cents'=>(int)$r['price_cents']],(array)site_safe(fn()=>ord_menu($aid,true),[])),0,80);
    if($mods['appt'] ?? false) $o['services']=array_slice(array_map(fn($r)=>['name'=>(string)$r['name'],'duration'=>(int)$r['duration_min'],'price_cents'=>(int)$r['price_cents']],(array)site_safe(fn()=>appt_services($aid,true),[])),0,40);
    if($mods['hotel'] ?? false) $o['rooms']=array_slice(array_map(fn($r)=>['name'=>(string)$r['name'],'desc'=>(string)$r['description'],'capacity'=>(int)$r['capacity'],'price_cents'=>(int)$r['price_cents'],'amenities'=>(string)$r['amenities']],(array)site_safe(fn()=>hotel_rooms($aid,true),[])),0,20);
    return $o;
}

/* ======================================================================= Neu anlegen (Vorbelegung) */
/** Vorlage passend zum Profil (Branche → Vorlage, privat → Portfolio). */
function site_template_for(?array $profile): string {
    if(($profile['kind'] ?? '')==='private') return 'personal';
    foreach((array)($profile['sectors'] ?? []) as $sec) foreach(SITE_TEMPLATES as $k=>$t) if(in_array($sec,$t['sectors'],true)) return $k;
    return 'shop';
}
function site_profile(int $aid): ?array {
    if(!function_exists('profile_get') && is_file(__DIR__.'/profile-lib.php')) require_once __DIR__.'/profile-lib.php';
    return function_exists('profile_get') ? site_safe(fn()=>profile_get($aid),null) : null;
}
/** Fakten aus Profil und eingeschalteten Modulen (nur gespeicherte Werte – nichts geraten). */
function site_facts(int $aid): array {
    $p=site_profile($aid) ?? []; $f=['name'=>(string)($p['company'] ?? ''),'city'=>(string)($p['city'] ?? ''),'country'=>(string)($p['country'] ?? ''),'street'=>'','zip'=>'','phone'=>'','email'=>'','hours'=>null,'lat'=>null,'lon'=>null];
    $mods=site_modules($aid);
    $take=function(array $s, array $map) use(&$f){ foreach($map as $from=>$to){ $v=$s[$from] ?? null; if(is_string($v) && trim($v)!=='' && ($f[$to]==='' || $f[$to]===null)) $f[$to]=trim($v); } };
    if($mods['order']){ $s=site_safe(fn()=>ord_settings($aid),[]); $take($s,['name'=>'name','phone'=>'phone']); if(!empty($s['address'])) site_addr_into($f,(string)$s['address']);
        $q=th_db()->prepare("SELECT hours,lat,lon FROM order_settings WHERE account_id=?"); $q->execute([$aid]); $raw=$q->fetch(PDO::FETCH_ASSOC);
        if($raw && $raw['hours']){ $f['hours']=site_hours_clean(json_decode((string)$raw['hours'],true)); } if($raw && $raw['lat']!==null){ $f['lat']=(float)$raw['lat']; $f['lon']=(float)$raw['lon']; } }
    if($mods['appt']){ $s=site_safe(fn()=>appt_settings($aid),[]); $take($s,['company'=>'name']); if(!empty($s['address']) && $f['street']==='') site_addr_into($f,(string)$s['address']);
        if($f['hours']===null){ $q=th_db()->prepare("SELECT hours FROM appt_settings WHERE account_id=?"); $q->execute([$aid]); $h=$q->fetchColumn(); if($h) $f['hours']=site_hours_clean(json_decode((string)$h,true)); } }
    if($mods['hotel']){ $s=site_safe(fn()=>hotel_settings($aid),[]); $take($s,['name'=>'name','phone'=>'phone']); if(!empty($s['address']) && $f['street']==='') site_addr_into($f,(string)$s['address']); }
    if($mods['ride']){ $s=site_safe(fn()=>ride_settings($aid),[]); $take($s,['name'=>'name','phone'=>'phone']); if(!empty($s['address']) && $f['street']==='') site_addr_into($f,(string)$s['address']); }
    if($f['phone']===''){ $q=th_db()->prepare("SELECT phone_number FROM agent_configs WHERE account_id=? AND phone_on=1 AND phone_number<>'' LIMIT 1"); $q->execute([$aid]); $f['phone']=(string)($q->fetchColumn() ?: ''); }
    if($f['name']===''){ $q=th_db()->prepare("SELECT company FROM agent_configs WHERE account_id=? AND company<>'' ORDER BY updated_at DESC LIMIT 1"); $q->execute([$aid]); $f['name']=(string)($q->fetchColumn() ?: ''); }
    return $f+['profile'=>$p,'mods'=>$mods];
}
/** „Musterstraße 1, 10115 Berlin“ → street/zip/city (ohne Gewähr; nur wenn das Muster eindeutig ist). */
function site_addr_into(array &$f, string $a): void {
    $a=trim($a); if($a==='') return;
    if(preg_match('/^(.+?),\s*(\d{4,6})\s+([^,]+)(?:,.*)?$/u',$a,$m)){ $f['street']=$f['street'] ?: trim($m[1]); $f['zip']=$f['zip'] ?: $m[2]; $f['city']=$f['city'] ?: trim($m[3]); }
    elseif($f['street']==='') $f['street']=mb_substr($a,0,160);
}
/** Neue Website (Entwurf). */
function site_create(int $aid, array $in): array {
    site_bootstrap();
    if(site_count($aid)>=SITE_MAX_SITES) return ['error'=>'site_limit','message'=>'Höchstens '.SITE_MAX_SITES.' Websites je Konto.'];
    $F=site_facts($aid); $p=$F['profile'];
    $tpl=is_string($in['template'] ?? null) && isset(SITE_TEMPLATES[$in['template']]) ? $in['template'] : site_template_for($p);
    $lang=site_lang($in['lang'] ?? '') ?: (site_lang($p['langs'][0] ?? '') ?: (SITE_COUNTRY_LANG[$F['country']] ?? 'de'));
    $name=site_txt($in['name'] ?? '',120) ?: $F['name'];
    if(($p['kind'] ?? '')==='private' && $name==='') $name='';
    $slug=isset($in['slug']) && is_string($in['slug']) && $in['slug']!=='' ? site_slug_check($in['slug']) : null;
    if($slug && !$slug[0]) return ['error'=>$slug[2],'message'=>site_slug_msg($slug[2]),'slug'=>$slug[1]];
    $slug=$slug ? $slug[1] : site_slug_free($name!=='' ? $name.($F['city']!=='' ? ' '.$F['city'] : '') : $tpl.'-'.substr(bin2hex(random_bytes(3)),0,4));
    $T=SITE_TEMPLATES[$tpl]; $P=SITE_PRESETS[$T['preset']];
    $sections=[]; foreach($T['sections'] as $s) $sections[]=['id'=>$s,'on'=>true]; foreach(SITE_SECTIONS as $s) if(!in_array($s,$T['sections'],true)) $sections[]=['id'=>$s,'on'=>false];
    $email=''; $langs=array_values(array_unique(array_merge([$lang],array_values(array_filter(array_map('site_lang',(array)($p['langs'] ?? [])))))));
    $data=['biz'=>['name'=>$name,'phone'=>site_phone_clean($F['phone']),'whatsapp'=>'','email'=>$email,'street'=>$F['street'],'zip'=>$F['zip'],'city'=>$F['city'],'country'=>$F['country'] ?: 'DE','lat'=>$F['lat'],'lon'=>$F['lon']],
      'hours'=>$F['hours'],'sections'=>$sections,'services'=>[],'services_auto'=>true,'faq'=>[],'team'=>[],'reviews'=>[],'gallery'=>[],'hero_img'=>null,'about_img'=>null,'logo'=>null,
      'cta'=>['primary'=>'auto'],'widget'=>true,
      'legal'=>['owner'=>$name,'form'=>'','rep'=>'','street'=>$F['street'],'zip'=>$F['zip'],'city'=>$F['city'],'country'=>$F['country'] ?: 'DE','email'=>'','phone'=>site_phone_clean($F['phone']),'register'=>'','vat'=>'','authority'=>'','responsible'=>'','extra'=>'','privacy_extra'=>''],
      'texts'=>[$lang=>site_default_texts($tpl,$lang,$name)]];
    $theme=['preset'=>$T['preset'],'bg'=>$P['bg'],'sur'=>$P['sur'],'ink'=>$P['ink'],'accent'=>$P['accent'],'accent2'=>$P['accent2'],'font'=>$T['font']];
    $seo=[$lang=>['title'=>mb_substr($name!=='' ? $name.($F['city']!=='' ? ' · '.$F['city'] : '') : '',0,65),'desc'=>'']];
    th_db()->prepare("INSERT INTO sites(account_id,slug,status,template,lang_default,langs,theme,data,seo,updated_at) VALUES(?,?,?,?,?,?,?,?,?,NOW())")
      ->execute([$aid,$slug,'draft',$tpl,$lang,json_encode(array_slice($langs,0,13)),json_encode($theme),json_encode($data,JSON_UNESCAPED_UNICODE),json_encode($seo,JSON_UNESCAPED_UNICODE)]);
    return site_get((int)th_db()->lastInsertId(),$aid) ?? ['error'=>'create_failed'];
}
/** Startwerte der Texte (ehrliche Platzhalter statt erfundener Inhalte). */
function site_default_texts(string $tpl, string $lang, string $name): array {
    $ph=fn(string $k)=>'[['.site_ph_label($k,$lang).']]';
    return ['hero_kicker'=>'','hero_title'=>$name!=='' ? $name : $ph('name'),'hero_text'=>$ph('hero'),'about_title'=>'','about_text'=>$ph('about'),'services_title'=>'','services_text'=>'',
      'contact_title'=>'','contact_text'=>'','footer_text'=>''];
}
/** Kurze Platzhalter-Bezeichnungen in der Inhaltssprache (Studio übersetzt die Markierung „Bitte ergänzen“ selbst). */
function site_ph_label(string $k, string $lang): string {
    $L=['de'=>['name'=>'Name deines Betriebs','hero'=>'Ein Satz, was du anbietest und für wen','about'=>'Kurz über dich bzw. deinen Betrieb'],
        'en'=>['name'=>'Your business name','hero'=>'One sentence: what you offer and for whom','about'=>'A few words about you or your business'],
        'fr'=>['name'=>'Nom de votre entreprise','hero'=>'Une phrase : ce que vous proposez et pour qui','about'=>'Quelques mots sur vous ou votre entreprise'],
        'tr'=>['name'=>'İşletmenizin adı','hero'=>'Tek cümle: ne sunuyorsunuz, kime','about'=>'Kendiniz veya işletmeniz hakkında kısa bilgi'],
        'az'=>['name'=>'Müəssisənizin adı','hero'=>'Bir cümlə: nə təklif edirsiniz və kimə','about'=>'Özünüz və ya müəssisəniz haqqında qısa məlumat'],
        'es'=>['name'=>'Nombre de tu negocio','hero'=>'Una frase: qué ofreces y para quién','about'=>'Unas palabras sobre ti o tu negocio']];
    return ($L[$lang] ?? $L['en'])[$k] ?? $k;
}

/* ======================================================================= Speichern */
/** Daten-Teilupdate prüfen und mit dem Bestand zusammenführen. $assets = gültige Bild-IDs dieser Website. */
function site_clean_data(array $in, array $cur, array $assets): array {
    $d=$cur; $aok=fn($v)=>(is_int($v) || (is_string($v) && ctype_digit($v))) && in_array((int)$v,$assets,true) ? (int)$v : null;
    if(isset($in['biz']) && is_array($in['biz'])){ $b=$in['biz']+($cur['biz'] ?? []);
        $d['biz']=['name'=>site_txt($b['name'] ?? '',120),'phone'=>site_phone_clean($b['phone'] ?? ''),'whatsapp'=>site_phone_clean($b['whatsapp'] ?? ''),'email'=>site_email_clean($b['email'] ?? ''),
          'street'=>site_txt($b['street'] ?? '',160),'zip'=>site_txt($b['zip'] ?? '',12),'city'=>site_txt($b['city'] ?? '',80),'country'=>isset(SITE_CC[strtoupper((string)($b['country'] ?? ''))]) ? strtoupper((string)$b['country']) : ((string)($cur['biz']['country'] ?? '') ?: 'DE'),
          'lat'=>is_numeric($b['lat'] ?? null) && abs((float)$b['lat'])<=90 ? round((float)$b['lat'],6) : null,'lon'=>is_numeric($b['lon'] ?? null) && abs((float)$b['lon'])<=180 ? round((float)$b['lon'],6) : null]; }
    if(array_key_exists('hours',$in)) $d['hours']=site_hours_clean($in['hours']);
    if(isset($in['sections']) && is_array($in['sections'])){ $seen=[]; $o=[];
        foreach($in['sections'] as $s){ $id=is_array($s) ? (string)($s['id'] ?? '') : (string)$s; if(!in_array($id,SITE_SECTIONS,true) || isset($seen[$id])) continue; $seen[$id]=1; $o[]=['id'=>$id,'on'=>is_array($s) ? !empty($s['on']) : true]; }
        foreach(SITE_SECTIONS as $id) if(!isset($seen[$id])) $o[]=['id'=>$id,'on'=>false];
        $d['sections']=$o; }
    if(isset($in['services']) && is_array($in['services'])) $d['services']=array_values(array_filter(array_map(fn($x)=>is_array($x) ? ['name'=>site_lmap($x['name'] ?? '',100),'desc'=>site_lmap($x['desc'] ?? '',400,true),'price'=>site_txt($x['price'] ?? '',40)] : null,array_slice($in['services'],0,30)),fn($x)=>$x && $x['name']));
    if(array_key_exists('services_auto',$in)) $d['services_auto']=!empty($in['services_auto']);
    if(isset($in['faq']) && is_array($in['faq'])) $d['faq']=array_values(array_filter(array_map(fn($x)=>is_array($x) ? ['q'=>site_lmap($x['q'] ?? '',200),'a'=>site_lmap($x['a'] ?? '',900,true)] : null,array_slice($in['faq'],0,15)),fn($x)=>$x && $x['q']));
    if(isset($in['team']) && is_array($in['team'])) $d['team']=array_values(array_filter(array_map(fn($x)=>is_array($x) ? ['name'=>site_txt($x['name'] ?? '',80),'role'=>site_lmap($x['role'] ?? '',100),'img'=>$aok($x['img'] ?? null)] : null,array_slice($in['team'],0,12)),fn($x)=>$x && $x['name']!==''));
    if(isset($in['reviews']) && is_array($in['reviews'])) $d['reviews']=array_values(array_filter(array_map(fn($x)=>is_array($x) ? ['author'=>site_txt($x['author'] ?? '',80),'text'=>site_txt($x['text'] ?? '',600,true),'stars'=>max(1,min(5,(int)($x['stars'] ?? 5)))] : null,array_slice($in['reviews'],0,12)),fn($x)=>$x && $x['text']!==''));
    if(isset($in['gallery']) && is_array($in['gallery'])){ $o=[]; foreach(array_slice($in['gallery'],0,24) as $x){ $img=$aok(is_array($x) ? ($x['img'] ?? null) : $x); if($img && !in_array($img,array_column($o,'img'),true)) $o[]=['img'=>$img,'alt'=>site_lmap(is_array($x) ? ($x['alt'] ?? '') : '',160)]; } $d['gallery']=$o; }
    foreach(['hero_img','about_img','logo'] as $k) if(array_key_exists($k,$in)) $d[$k]=$aok($in[$k]);
    if(isset($in['cta']) && is_array($in['cta'])) $d['cta']=['primary'=>in_array($in['cta']['primary'] ?? '',SITE_CTAS,true) ? $in['cta']['primary'] : 'auto'];
    if(array_key_exists('widget',$in)) $d['widget']=!empty($in['widget']);
    if(isset($in['legal']) && is_array($in['legal'])){ $l=$in['legal']+($cur['legal'] ?? []);
        $d['legal']=['owner'=>site_txt($l['owner'] ?? '',160),'form'=>site_txt($l['form'] ?? '',80),'rep'=>site_txt($l['rep'] ?? '',200),'street'=>site_txt($l['street'] ?? '',160),'zip'=>site_txt($l['zip'] ?? '',12),
          'city'=>site_txt($l['city'] ?? '',80),'country'=>isset(SITE_CC[strtoupper((string)($l['country'] ?? ''))]) ? strtoupper((string)$l['country']) : ((string)($cur['legal']['country'] ?? '') ?: 'DE'),'email'=>site_email_clean($l['email'] ?? ''),
          'phone'=>site_phone_clean($l['phone'] ?? ''),'register'=>site_txt($l['register'] ?? '',200),'vat'=>site_txt($l['vat'] ?? '',40),'authority'=>site_txt($l['authority'] ?? '',300,true),
          'responsible'=>site_txt($l['responsible'] ?? '',200),'extra'=>site_txt($l['extra'] ?? '',3000,true),'privacy_extra'=>site_txt($l['privacy_extra'] ?? '',4000,true)]; }
    if(isset($in['texts']) && is_array($in['texts'])) foreach($in['texts'] as $l=>$tx){ $l=site_lang($l); if($l==='' || !is_array($tx)) continue; $o=[];
        foreach(SITE_TEXT_KEYS as $k=>$max) if(isset($tx[$k])){ $v=site_txt($tx[$k],$max,in_array($k,['about_text','hero_text','services_text','team_text','location_text','contact_text','hours_note','footer_text'],true)); if($v!=='') $o[$k]=$v; }
        $d['texts'][$l]=$o; }
    return $d;
}
function site_clean_theme($in, array $cur): array {
    if(!is_array($in)) return $cur; $preset=is_string($in['preset'] ?? null) && isset(SITE_PRESETS[$in['preset']]) ? $in['preset'] : ($cur['preset'] ?? 'gold');
    $P=SITE_PRESETS[$preset]; $pick=isset($in['preset']) && ($in['preset']!==($cur['preset'] ?? '')) && !isset($in['accent']);   // Vorlage gewechselt → deren Farben
    $o=['preset'=>$preset]; foreach(['bg','sur','ink','accent','accent2'] as $k) $o[$k]=$pick ? $P[$k] : site_hex($in[$k] ?? ($cur[$k] ?? $P[$k]),$P[$k]);
    $o['font']=is_string($in['font'] ?? null) && isset(SITE_FONTS[$in['font']]) ? $in['font'] : ($cur['font'] ?? 'classic');
    return $o;
}
function site_clean_seo($in, array $cur): array {
    if(!is_array($in)) return $cur; $o=$cur;
    foreach($in as $l=>$v){ $l=site_lang($l); if($l==='' || !is_array($v)) continue; $o[$l]=['title'=>site_txt($v['title'] ?? '',70),'desc'=>site_txt($v['desc'] ?? '',170)]; }
    return $o;
}
/** Speichern (Teil-Update). Rückgabe: aktualisierte Website oder ['error'=>…]. */
function site_update(array $S, array $in): array {
    $p=th_db(); $aid=$S['account_id']; $assets=array_map(fn($a)=>(int)$a['id'],site_assets($S['id']));
    $tpl=is_string($in['template'] ?? null) && isset(SITE_TEMPLATES[$in['template']]) ? $in['template'] : $S['template'];
    $theme=array_key_exists('theme',$in) ? site_clean_theme($in['theme'],$S['theme']) : $S['theme'];
    if($tpl!==$S['template'] && !isset($in['theme'])){ $T=SITE_TEMPLATES[$tpl]; $theme=site_clean_theme(['preset'=>$T['preset'],'font'=>$T['font']],$S['theme']); }
    $data=isset($in['data']) && is_array($in['data']) ? site_clean_data($in['data'],$S['data'],$assets) : $S['data'];
    $seo=array_key_exists('seo',$in) ? site_clean_seo($in['seo'],$S['seo']) : $S['seo'];
    $def=site_lang($in['lang_default'] ?? '') ?: $S['lang_default'];
    $langs=isset($in['langs']) && is_array($in['langs']) ? array_values(array_unique(array_filter(array_map('site_lang',$in['langs'])))) : $S['langs'];
    if(!in_array($def,$langs,true)) array_unshift($langs,$def);
    usort($langs,fn($a,$b)=>($a===$def ? -1 : ($b===$def ? 1 : array_search($a,SITE_LANGS,true) <=> array_search($b,SITE_LANGS,true))));
    $slug=$S['slug'];
    if(isset($in['slug']) && is_string($in['slug']) && $in['slug']!==$S['slug']){ [$ok,$s,$why]=site_slug_check($in['slug'],$S['id']); if(!$ok) return ['error'=>$why,'message'=>site_slug_msg($why),'slug'=>$s]; $slug=$s; }
    $dom=$S['custom_domain'];
    if(array_key_exists('custom_domain',$in)){ $raw=trim((string)($in['custom_domain'] ?? '')); $dom=$raw==='' ? '' : site_domain_clean($raw);
        if($raw!=='' && $dom==='') return ['error'=>'domain_invalid','message'=>'Bitte eine gültige eigene Domain angeben, z. B. www.mein-salon.de.'];
        if($dom!==''){ $q=$p->prepare("SELECT id FROM sites WHERE custom_domain=? AND id<>?"); $q->execute([$dom,$S['id']]); if($q->fetchColumn()) return ['error'=>'domain_taken','message'=>'Diese Domain ist bereits mit einer anderen Website verbunden.']; } }
    $dj=json_encode($data,JSON_UNESCAPED_UNICODE); if(strlen((string)$dj)>SITE_DATA_MAX) return ['error'=>'too_large','message'=>'Zu viele Inhalte für eine Website – bitte kürzen.'];
    $p->prepare("UPDATE sites SET template=?,theme=?,data=?,seo=?,lang_default=?,langs=?,slug=?,custom_domain=?,updated_at=NOW() WHERE id=? AND account_id=?")
      ->execute([$tpl,json_encode($theme),$dj,json_encode($seo,JSON_UNESCAPED_UNICODE),$def,json_encode(array_slice($langs,0,13)),$slug,$dom!=='' ? $dom : null,$S['id'],$aid]);
    return site_get($S['id'],$aid);
}
function site_slug_msg(string $why): string {
    return ['slug_short'=>'Die Adresse braucht mindestens 3 Zeichen (a–z, 0–9, Bindestrich).','slug_reserved'=>'Diese Adresse ist reserviert – bitte eine andere wählen.',
      'slug_taken'=>'Diese Adresse ist schon vergeben – bitte eine andere wählen.','slug_invalid'=>'Nur a–z, 0–9 und Bindestriche, 3–40 Zeichen.'][$why] ?? 'Adresse ungültig.';
}

/* ======================================================================= Hinweise (Platzhalter, Pflichtangaben) */
/** Alle Platzhalter [[…]] in einem Text. */
function site_placeholders(string $t): array { return preg_match_all('/\[\[(.{1,80}?)\]\]/u',$t,$m) ? $m[1] : []; }
/** Hinweise für das Studio und die Freigabe. block=true verhindert das Veröffentlichen. */
function site_issues(array $S): array {
    $d=$S['data']; $o=[]; $on=[]; foreach((array)($d['sections'] ?? []) as $s) if(!empty($s['on'])) $on[$s['id']]=1;
    $add=function(string $code, string $field, ?string $lang=null, bool $block=true, string $hint='') use(&$o){ $o[]=['code'=>$code,'field'=>$field,'lang'=>$lang,'block'=>$block]+($hint!=='' ? ['hint'=>$hint] : []); };
    if(trim((string)($d['biz']['name'] ?? ''))==='') $add('biz_name','biz.name');
    $L=$d['legal'] ?? []; foreach(['owner','street','zip','city','email'] as $k) if(trim((string)($L[$k] ?? ''))==='') $add('legal_missing','legal.'.$k);
    $secOf=['hero_kicker'=>'hero','hero_title'=>'hero','hero_text'=>'hero','cta_label'=>'hero','about_title'=>'about','about_text'=>'about','services_title'=>'services','services_text'=>'services','team_title'=>'team','team_text'=>'team',
      'gallery_title'=>'gallery','hours_title'=>'hours','hours_note'=>'hours','location_title'=>'location','location_text'=>'location','reviews_title'=>'reviews','faq_title'=>'faq','contact_title'=>'contact','contact_text'=>'contact','footer_text'=>'footer'];
    foreach($S['langs'] as $l){
        foreach((array)($d['texts'][$l] ?? []) as $k=>$v){ $sec=$secOf[$k] ?? 'footer'; if($sec!=='footer' && !isset($on[$sec])) continue; foreach(site_placeholders((string)$v) as $ph) $add('placeholder','texts.'.$k,$l,true,$ph); }
        if(isset($on['services'])) foreach((array)($d['services'] ?? []) as $i=>$x) foreach(['name','desc'] as $k) foreach(site_placeholders((string)($x[$k][$l] ?? '')) as $ph) $add('placeholder','services.'.$i.'.'.$k,$l,true,$ph);
        if(isset($on['services'])) foreach((array)($d['services'] ?? []) as $i=>$x) if($l===$S['lang_default']) foreach(site_placeholders((string)($x['price'] ?? '')) as $ph) $add('placeholder','services.'.$i.'.price',$l,true,$ph);
        if(isset($on['faq'])) foreach((array)($d['faq'] ?? []) as $i=>$x) foreach(['q','a'] as $k) foreach(site_placeholders((string)($x[$k][$l] ?? '')) as $ph) $add('placeholder','faq.'.$i.'.'.$k,$l,true,$ph);
        foreach(['title','desc'] as $k) foreach(site_placeholders((string)($S['seo'][$l][$k] ?? '')) as $ph) $add('placeholder','seo.'.$k,$l,true,$ph);
    }
    $b=$d['biz'] ?? []; if(($b['phone'] ?? '')==='' && ($b['email'] ?? '')==='' && ($b['whatsapp'] ?? '')==='' && !isset($on['contact'])) $add('no_contact','biz.phone',null,false);
    if(trim((string)($S['seo'][$S['lang_default']]['desc'] ?? ''))==='') $add('seo_desc','seo.desc',$S['lang_default'],false);
    return $o;
}

/* ======================================================================= Veröffentlichen */
/** Darf das Konto veröffentlichen? Jeder aktive bezahlte Plan (Agent, Abo, Einmalkauf im Betrieb, Betrieb/BYOK) oder der Plan „website-pro“. */
function site_can_publish(int $aid): array {
    foreach(th_plans($aid) as $p){ if(!in_array($p['status'],['active','trialing','past_due'],true)) continue; if(!ch_plan((string)$p['plan'])) continue;
        if(site_safe(fn()=>th_plan_active($aid,(string)$p['plan']),false)) return ['ok'=>true,'plan'=>(string)$p['plan']]; }
    return ['ok'=>false,'plan'=>null];
}
function site_checkout_url(string $email=''): string { return '/chat/tokens-checkout.php?plan='.SITE_PLAN.($email!=='' ? '&email='.rawurlencode($email) : ''); }
/** Schnappschuss der veröffentlichten Fassung. */
function site_snapshot(array $S): array { return ['template'=>$S['template'],'theme'=>$S['theme'],'data'=>$S['data'],'seo'=>$S['seo'],'langs'=>$S['langs'],'lang_default'=>$S['lang_default']]; }
/** Website mit der veröffentlichten Fassung (für die öffentliche Seite). */
function site_published_view(array $S): ?array {
    if($S['status']!=='published' || !is_array($S['pub'])) return null;
    $P=$S['pub']; foreach(['template','theme','data','seo','langs','lang_default'] as $k) if(isset($P[$k])) $S[$k]=$P[$k];
    return $S;
}
function site_publish(array $S, string $email=''): array {
    $cp=site_can_publish($S['account_id']);
    if(!$cp['ok']) return ['error'=>'plan_required','status'=>402,'message'=>'Zum Veröffentlichen brauchst du ein aktives Paket – z. B. den Plan „Website“ für 9 €/Monat oder einen beliebigen Agenten.','checkout'=>site_checkout_url($email)];
    $blk=array_values(array_filter(site_issues($S),fn($i)=>$i['block']));
    if($blk) return ['error'=>'issues','status'=>422,'message'=>'Bitte zuerst die markierten Angaben ergänzen (Platzhalter und Pflichtangaben für Impressum/Datenschutz).','issues'=>$blk];
    th_db()->prepare("UPDATE sites SET status='published',pub=?,published_at=NOW() WHERE id=? AND account_id=?")->execute([json_encode(site_snapshot($S),JSON_UNESCAPED_UNICODE),$S['id'],$S['account_id']]);
    return ['site'=>site_get($S['id'],$S['account_id'])];
}
function site_unpublish(array $S): array { th_db()->prepare("UPDATE sites SET status='draft' WHERE id=? AND account_id=?")->execute([$S['id'],$S['account_id']]); return site_get($S['id'],$S['account_id']) ?? $S; }
/** Ungespeicherte Änderungen gegenüber der veröffentlichten Fassung? */
function site_dirty(array $S): bool { return $S['status']==='published' && is_array($S['pub']) && json_encode(site_snapshot($S))!==json_encode($S['pub']); }
/** Cache-Version der öffentlichen Seite (ändert sich mit jeder Veröffentlichung und jedem Stand der Darstellung). */
function site_pub_version(array $S): string {
    return substr(md5(($S['published_at'] ?? '').'|'.md5(json_encode($S['pub'] ?? null)).'|'.@filemtime(__DIR__.'/site-render.php').'|'.@filemtime(__FILE__).'|'.@filemtime(__DIR__.'/site-page.js').'|'.$S['custom_domain']),0,10);
}
/** Vorschau-Schlüssel (Entwurf ansehen ohne Anmeldung, z. B. im Studio-iframe). */
function site_preview_token(array $S): string {
    $k=th_secret_master(); if($k==='') $k=hash('sha256',__FILE__.'|'.php_uname());
    return substr(hash_hmac('sha256','site-preview|'.$S['id'].'|'.$S['account_id'].'|'.$S['created_at'],$k),0,24);
}
function site_url(array $S, ?string $lang=null, string $page=''): string {
    return SITE_BASE.'/chat/site.php?s='.rawurlencode($S['slug']).($lang!==null && $lang!==$S['lang_default'] ? '&lang='.$lang : '').($page!=='' ? '&page='.$page : '');
}

/* ======================================================================= Bilder */
function site_upload_dir(int $siteId): string {
    $base=__DIR__.'/uploads'; $dir=$base.'/sites/'.$siteId;
    if(!is_dir($dir)) @mkdir($dir,0755,true);
    /* kein Verzeichnis-Listing, keine Skripte (Apache/LiteSpeed; nginx führt in uploads ohnehin kein PHP aus, Dateinamen sind zufällig und enden auf .webp/.jpg/.png) */
    foreach([$base,$base.'/sites',$dir] as $x) if(!is_file($x.'/index.html')) @file_put_contents($x.'/index.html','');
    if(!is_file($base.'/.htaccess')) @file_put_contents($base.'/.htaccess',"<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi|sh|html?|svg|js)$\">\n  Require all denied\n</FilesMatch>\n<FilesMatch \"^index\\.html$\">\n  Require all granted\n</FilesMatch>\n");
    return $dir;
}
function site_assets(int $siteId): array {
    site_bootstrap(); $s=th_db()->prepare("SELECT * FROM site_assets WHERE site_id=? ORDER BY id"); $s->execute([$siteId]);
    return array_map(fn($r)=>['id'=>(int)$r['id'],'kind'=>(string)$r['kind'],'file'=>(string)$r['file'],'mime'=>(string)$r['mime'],'w'=>(int)$r['w'],'h'=>(int)$r['h'],'bytes'=>(int)$r['bytes'],
      'url'=>'/chat/uploads/sites/'.(int)$r['site_id'].'/'.$r['file']],$s->fetchAll(PDO::FETCH_ASSOC));
}
/**
 * Bild prüfen und speichern: data-URL (JPEG/PNG/WebP), ≤ 2 MB, echtes Rasterbild (getimagesizefromstring), 64–8000 px, ≤ 40 MP;
 * mit GD neu kodiert (WebP, sonst JPEG/PNG), auf SITE_IMG_EDGE verkleinert – entfernt Metadaten und eingebettete Fremdinhalte.
 */
function site_upload(array $S, $raw, string $kind='image'): array {
    $kind=in_array($kind,['hero','about','logo','gallery','team','image'],true) ? $kind : 'image';
    $s=is_string($raw) ? trim($raw) : '';
    if(!preg_match('#^data:image/(jpeg|jpg|png|webp);base64,([A-Za-z0-9+/=\s]+)$#',$s,$m)) return ['error'=>'image_type','status'=>415,'message'=>'Bitte ein Bild als JPEG, PNG oder WebP hochladen.'];
    if(strlen($m[2])>(int)ceil(SITE_IMG_MAX*4/3)+256) return ['error'=>'image_too_large','status'=>413,'message'=>'Das Bild ist zu groß (höchstens 2 MB).'];
    $bin=base64_decode(preg_replace('/\s+/','',$m[2]),true);
    if($bin===false || $bin==='') return ['error'=>'image_broken','status'=>422,'message'=>'Das Bild ist beschädigt – bitte erneut auswählen.'];
    if(strlen($bin)>SITE_IMG_MAX) return ['error'=>'image_too_large','status'=>413,'message'=>'Das Bild ist zu groß (höchstens 2 MB).'];
    $info=@getimagesizefromstring($bin);
    if(!$info || !in_array($info['mime'] ?? '',['image/jpeg','image/png','image/webp'],true)) return ['error'=>'image_type','status'=>415,'message'=>'Das ist kein gültiges JPEG-, PNG- oder WebP-Bild.'];
    [$w,$h]=[(int)$info[0],(int)$info[1]];
    if($w<64 || $h<64) return ['error'=>'image_small','status'=>422,'message'=>'Das Bild ist zu klein (mindestens 64 × 64 Pixel).'];
    if($w>8000 || $h>8000 || $w*$h>40000000) return ['error'=>'image_dims','status'=>422,'message'=>'Das Bild hat zu viele Pixel (höchstens 8000 px Kantenlänge).'];
    if(count(site_assets($S['id']))>=SITE_MAX_ASSETS) return ['error'=>'asset_limit','status'=>409,'message'=>'Höchstens '.SITE_MAX_ASSETS.' Bilder je Website – bitte zuerst ungenutzte löschen.'];
    $q=th_db()->prepare("SELECT COALESCE(SUM(bytes),0) FROM site_assets WHERE account_id=?"); $q->execute([$S['account_id']]); if((int)$q->fetchColumn()>SITE_QUOTA) return ['error'=>'quota','status'=>409,'message'=>'Speicherplatz für Bilder ausgeschöpft (80 MB).'];
    if(!function_exists('imagecreatefromstring')) return ['error'=>'gd_missing','status'=>501,'message'=>'Bildverarbeitung auf dem Server nicht verfügbar.'];
    @ini_set('memory_limit','256M');
    $im=@imagecreatefromstring($bin); if($im===false) return ['error'=>'image_broken','status'=>422,'message'=>'Das Bild konnte nicht gelesen werden.'];
    $edge=$kind==='logo' ? 600 : SITE_IMG_EDGE; $sc=min(1,$edge/max($w,$h)); $nw=max(1,(int)round($w*$sc)); $nh=max(1,(int)round($h*$sc));
    $alpha=$info['mime']!=='image/jpeg';
    $out=imagecreatetruecolor($nw,$nh);
    if($alpha){ imagealphablending($out,false); imagesavealpha($out,true); imagefill($out,0,0,imagecolorallocatealpha($out,0,0,0,127)); } else imagefill($out,0,0,imagecolorallocate($out,255,255,255));
    imagecopyresampled($out,$im,0,0,0,0,$nw,$nh,$w,$h); imagedestroy($im);
    ob_start(); $ext='webp'; $mime='image/webp';
    if(function_exists('imagewebp') && @imagewebp($out,null,82)){ } else { ob_clean(); if($alpha){ imagepng($out,null,8); $ext='png'; $mime='image/png'; } else { imagejpeg($out,null,85); $ext='jpg'; $mime='image/jpeg'; } }
    $enc=(string)ob_get_clean(); imagedestroy($out);
    if($enc==='') return ['error'=>'image_broken','status'=>422,'message'=>'Das Bild konnte nicht verarbeitet werden.'];
    $file=bin2hex(random_bytes(12)).'.'.$ext; $dir=site_upload_dir($S['id']);
    if(@file_put_contents($dir.'/'.$file,$enc,LOCK_EX)===false) return ['error'=>'write_failed','status'=>500,'message'=>'Das Bild konnte nicht gespeichert werden.'];
    @chmod($dir.'/'.$file,0644);
    th_db()->prepare("INSERT INTO site_assets(site_id,account_id,kind,file,mime,w,h,bytes) VALUES(?,?,?,?,?,?,?,?)")->execute([$S['id'],$S['account_id'],$kind,$file,$mime,$nw,$nh,strlen($enc)]);
    $id=(int)th_db()->lastInsertId();
    foreach(site_assets($S['id']) as $a) if($a['id']===$id) return ['asset'=>$a];
    return ['error'=>'write_failed','status'=>500];
}
/** Bild löschen (nicht, solange die veröffentlichte Fassung es nutzt). Verweise im Entwurf werden entfernt. */
function site_asset_delete(array $S, int $assetId): array {
    $a=null; foreach(site_assets($S['id']) as $x) if($x['id']===$assetId) $a=$x;
    if(!$a) return ['error'=>'not_found','status'=>404];
    if($S['status']==='published' && is_array($S['pub']) && in_array($assetId,site_asset_refs($S['pub']['data'] ?? []),true)) return ['error'=>'asset_in_use','status'=>409,'message'=>'Das Bild wird auf der veröffentlichten Website verwendet – erst entfernen und neu veröffentlichen.'];
    $d=$S['data']; foreach(['hero_img','about_img','logo'] as $k) if(($d[$k] ?? null)===$assetId) $d[$k]=null;
    $d['gallery']=array_values(array_filter((array)($d['gallery'] ?? []),fn($g)=>(int)($g['img'] ?? 0)!==$assetId));
    foreach((array)($d['team'] ?? []) as $i=>$t) if((int)($t['img'] ?? 0)===$assetId) $d['team'][$i]['img']=null;
    th_db()->prepare("UPDATE sites SET data=?,updated_at=NOW() WHERE id=?")->execute([json_encode($d,JSON_UNESCAPED_UNICODE),$S['id']]);
    th_db()->prepare("DELETE FROM site_assets WHERE id=? AND site_id=?")->execute([$assetId,$S['id']]);
    $f=__DIR__.'/uploads/sites/'.$S['id'].'/'.basename($a['file']); if(is_file($f)) @unlink($f);
    return ['ok'=>true];
}
function site_asset_refs(array $d): array {
    $o=[]; foreach(['hero_img','about_img','logo'] as $k) if(!empty($d[$k])) $o[]=(int)$d[$k];
    foreach((array)($d['gallery'] ?? []) as $g) if(!empty($g['img'])) $o[]=(int)$g['img']; foreach((array)($d['team'] ?? []) as $t) if(!empty($t['img'])) $o[]=(int)$t['img'];
    return array_values(array_unique($o));
}
function site_delete(array $S): void {
    foreach(site_assets($S['id']) as $a){ $f=__DIR__.'/uploads/sites/'.$S['id'].'/'.basename($a['file']); if(is_file($f)) @unlink($f); }
    $dir=__DIR__.'/uploads/sites/'.$S['id']; if(is_dir($dir)){ @unlink($dir.'/index.html'); @rmdir($dir); }
    foreach(['site_assets','site_leads'] as $t) th_db()->prepare("DELETE FROM $t WHERE site_id=?")->execute([$S['id']]);
    th_db()->prepare("DELETE FROM sites WHERE id=? AND account_id=?")->execute([$S['id'],$S['account_id']]);
}

/* ======================================================================= Anfragen (Kontaktformular) */
function site_form_token(array $S, ?int $ts=null): string { $ts=$ts ?? time(); return $ts.'.'.substr(hash_hmac('sha256','site-form|'.$S['id'].'|'.$ts,th_secret_master() ?: 'ch-site-form'),0,16); }
/** Anfrage annehmen. $post = Formularfelder. Rückgabe ['ok'=>true] oder ['error'=>code]. */
function site_lead_submit(array $S, array $post, string $ip): array {
    if($S['status']!=='published' && empty($S['exported_at'])) return ['error'=>'f_off'];
    if(trim((string)($post['website'] ?? ''))!=='') return ['ok'=>true,'spam'=>true];   // Honigtopf: still „ok“, nichts speichern
    $tok=(string)($post['t'] ?? ''); if(!preg_match('/^(\d{9,11})\.([a-f0-9]{16})$/',$tok,$m) || !hash_equals(site_form_token($S,(int)$m[1]),$tok)) return ['error'=>'f_err'];
    $age=time()-(int)$m[1]; if($age<2 || $age>86400*2) return ['error'=>'f_err'];
    $name=site_txt($post['name'] ?? '',120); $contact=site_txt($post['contact'] ?? '',190); $msg=site_txt($post['message'] ?? '',3000,true); $lang=site_lang($post['lang'] ?? '') ?: $S['lang_default'];
    if($name==='' || mb_strlen($contact)<5 || $msg==='') return ['error'=>'f_err'];
    if(!filter_var($contact,FILTER_VALIDATE_EMAIL) && strlen(preg_replace('/\D/','',$contact))<6) return ['error'=>'f_err'];
    $ih=md5('site|'.$ip);
    if(!th_rate('sitelead:ip:'.$ih,5,600) || !th_rate('sitelead:ipday:'.$ih,20,86400) || !th_rate('sitelead:site:'.$S['id'],100,86400)) return ['error'=>'f_rate'];
    th_db()->prepare("INSERT INTO site_leads(site_id,account_id,name,contact,message,lang,ip_hash) VALUES(?,?,?,?,?,?,?)")->execute([$S['id'],$S['account_id'],$name,$contact,$msg,$lang,$ih]);
    $id=(int)th_db()->lastInsertId();
    if(!function_exists('ch_push') && is_file(__DIR__.'/push-lib.php')){ require_once __DIR__.'/agent-tools.php'; }
    if(function_exists('ch_push')) site_safe(fn()=>ch_push($S['account_id'],'lead_new','Website-Anfrage: '.$name,mb_substr($contact.' · '.$msg,0,300),'/chat/studio.php#website-builder',['tag'=>'site-lead-'.$id]));
    return ['ok'=>true,'id'=>$id];
}
function site_leads(int $siteId, int $limit=100): array {
    $s=th_db()->prepare("SELECT id,name,contact,message,lang,status,created_at FROM site_leads WHERE site_id=? ORDER BY id DESC LIMIT ".max(1,min(500,$limit))); $s->execute([$siteId]);
    return array_map(fn($r)=>['id'=>(int)$r['id'],'name'=>$r['name'],'contact'=>$r['contact'],'message'=>(string)$r['message'],'lang'=>$r['lang'],'status'=>$r['status'],'created_at'=>th_dbiso($r['created_at'])],$s->fetchAll(PDO::FETCH_ASSOC));
}

/* ======================================================================= KI: Entwurf & Übersetzung */
/** KI-Antwort → JSON-Objekt (Codeblöcke/Vorrede werden entfernt). */
function site_ai_json(?string $txt): ?array {
    if($txt===null) return null; $t=trim($txt);
    $t=preg_replace('/^```(?:json)?\s*|\s*```$/i','',$t) ?? $t;
    $a=strpos($t,'{'); $b=strrpos($t,'}'); if($a===false || $b===false || $b<$a) return null;
    $j=json_decode(substr($t,$a,$b-$a+1),true); return is_array($j) ? $j : null;
}
/** Fakten-Block für die KI (nur gespeicherte Angaben). */
function site_ai_facts(array $S, array $answers): string {
    $d=$S['data']; $b=$d['biz'] ?? []; $aid=$S['account_id']; $p=site_profile($aid) ?? []; $mods=site_modules($aid); $ms=site_module_services($aid,$mods);
    $L=[];
    $L[]='Name: '.(($b['name'] ?? '')!=='' ? $b['name'] : 'UNBEKANNT');
    $T=SITE_TEMPLATES[$S['template']]; $L[]='Art: '.$T['tag'].(($p['kind'] ?? '')==='private' ? ' (Privatperson)' : '');
    if(!empty($p['sectors']) && function_exists('profile_sectors')){ $ps=site_safe('profile_sectors',[]); $L[]='Branche: '.implode(', ',array_map(fn($x)=>$ps[$x]['label'] ?? $x,(array)$p['sectors'])); }
    if(($b['city'] ?? '')!=='') $L[]='Ort: '.$b['city'].(($b['country'] ?? '')!=='' ? ' ('.$b['country'].')' : '');
    $L[]='Kontaktwege vorhanden: '.implode(', ',array_filter([($b['phone'] ?? '')!=='' ? 'Telefon' : '',($b['whatsapp'] ?? '')!=='' ? 'WhatsApp' : '',($b['email'] ?? '')!=='' ? 'E-Mail' : '','Kontaktformular'])).'.';
    $act=array_keys(array_filter(['Online-Terminbuchung'=>$mods['appt'],'Bestellung per Chat/WhatsApp/Telefon'=>$mods['order'],'Zimmeranfrage'=>$mods['hotel'],'Taxi-Bestellung'=>$mods['ride'],'Rückruf-Service'=>$mods['callback']]));
    if($act) $L[]='Digitale Services: '.implode(', ',$act).' (ein KI-Assistent beantwortet Fragen rund um die Uhr).';
    if($ms['menu']) $L[]='Speisekarte (Auszug): '.implode('; ',array_slice(array_map(fn($x)=>$x['name'],$ms['menu']),0,25));
    if($ms['services']) $L[]='Leistungen: '.implode('; ',array_map(fn($x)=>$x['name'].' ('.$x['duration'].' Min)',array_slice($ms['services'],0,25)));
    if($ms['rooms']) $L[]='Zimmer: '.implode('; ',array_map(fn($x)=>$x['name'].' (bis '.$x['capacity'].' Pers.)',$ms['rooms']));
    $man=array_filter(array_map(fn($x)=>$x['name'][$S['lang_default']] ?? (reset($x['name']) ?: ''),(array)($d['services'] ?? []))); if($man) $L[]='Eigene Leistungsliste: '.implode('; ',array_slice($man,0,20));
    if(!empty($d['hours'])) $L[]='Öffnungszeiten sind hinterlegt (werden separat angezeigt – nicht im Text wiederholen).';
    $A=['tone'=>'Tonalität','audience'=>'Zielgruppe','offer'=>'Angebot laut Inhaber','usp'=>'Besonderheiten laut Inhaber','extra'=>'Weitere Angaben des Inhabers'];
    foreach($A as $k=>$lab){ $v=site_txt($answers[$k] ?? '',600,true); if($v!=='') $L[]=$lab.': '.$v; }
    return implode("\n",$L);
}
function site_ai_schema_text(): string {
    return '{"texts":{"hero_kicker":"","hero_title":"","hero_text":"","about_title":"","about_text":"","services_title":"","services_text":"","team_title":"","gallery_title":"","hours_title":"","location_title":"","location_text":"","reviews_title":"","faq_title":"","contact_title":"","contact_text":"","footer_text":""},"services":[{"name":"","desc":""}],"faq":[{"q":"","a":""}],"seo":{"title":"","desc":""}}';
}
/** KI-Entwurf in der Standardsprache. Rückgabe ['site'=>…,'tokens_used'=>n] oder Fehler mit status. */
function site_generate(array $S, array $b): array {
    $aid=$S['account_id']; $lang=site_lang($b['lang'] ?? '') ?: $S['lang_default']; $cost=SITE_PRICE_GENERATE;
    if(!th_rate('sitegen:'.$aid,12,3600)) return ['error'=>'rate_limited','status'=>429,'message'=>'Bitte kurz warten – höchstens 12 KI-Entwürfe pro Stunde.'];
    if(th_balance($aid)<$cost) return ['error'=>'insufficient_tokens','status'=>402,'need'=>$cost,'balance'=>th_balance($aid),'message'=>'Nicht genug Tokens für den KI-Entwurf ('.$cost.' Tokens).'];
    $answers=is_array($b['answers'] ?? null) ? $b['answers'] : [];
    $hasModuleServices=(bool)array_filter(site_module_services($aid,site_modules($aid)));
    $sys="Du bist ein erfahrener Website-Texter für kleine Betriebe und Selbstständige in Europa. Du schreibst klare, warme, verkaufsstarke, aber ehrliche Texte.\n"
        ."AUSGABE: ausschließlich EIN JSON-Objekt, exakt nach diesem Schema (keine weiteren Schlüssel, kein Markdown, kein HTML, keine Emojis, keine Links):\n".site_ai_schema_text()."\n"
        ."SPRACHE aller Texte: ".SITE_LANG_EN[$lang]." (".$lang.").\n"
        ."STRENGE REGELN:\n- Verwende nur Fakten aus FAKTEN. Erfinde NIEMALS Zahlen, Jahreszahlen, Preise, Auszeichnungen, Zertifikate, Garantien, Personennamen, Kundenbewertungen, Adressen, Öffnungszeiten oder Versprechen.\n"
        ."- Fehlt ein konkretes Detail, das ein guter Text bräuchte, setze einen kurzen Platzhalter in doppelten eckigen Klammern in der Zielsprache, z. B. [[Gründungsjahr]] oder [[Ihre Spezialität]]. Lieber allgemein formulieren als raten.\n"
        ."- Längen: hero_kicker ≤ 6 Wörter, hero_title ≤ 9 Wörter, hero_text 1–2 Sätze, about_text 2 kurze Absätze (durch Leerzeile getrennt), services_text 1 Satz, location_text 1 Satz, contact_text 1–2 Sätze, footer_text 1 Satz.\n"
        ."- *_title: kurze, natürliche Abschnittsüberschriften (2–5 Wörter).\n"
        .($hasModuleServices ? "- services: leere Liste [] (die Leistungen kommen aus dem System).\n" : "- services: 3–6 Einträge NUR aus den Angaben des Inhabers bzw. der Leistungsliste; ohne solche Angaben 3 Einträge mit Platzhalter-Namen wie [[Leistung 1]]. desc 1 Satz, keine Preise.\n")
        ."- faq: 4–6 typische Fragen von Kunden; Antworten nur mit Fakten, sonst Platzhalter. Keine Fragen zu Preisen, wenn keine Preise bekannt sind.\n"
        ."- seo.title ≤ 60 Zeichen (Name + Ort/Branche), seo.desc 120–155 Zeichen.\n- Keine Rechts-, Medizin- oder Heilversprechen. Kein Superlativ ohne Beleg (\"die besten\").";
    $usr="FAKTEN:\n".site_ai_facts($S,$answers)."\n\nSchreibe jetzt das JSON.";
    th_ai_context($aid,'studio'); $t0=microtime(true);
    $out=th_ai($sys,[['role'=>'user','content'=>$usr]],2600,90);
    $j=site_ai_json($out);
    if(!$j || !is_array($j['texts'] ?? null)){ $inf=th_ai_info(); return ['error'=>!empty($inf['byok_failed']) ? 'byok_failed' : 'ai_unavailable','status'=>503,'message'=>$out===null ? 'KI gerade nicht erreichbar – bitte gleich noch einmal versuchen.' : 'Die KI-Antwort war unvollständig – bitte erneut versuchen (es wurden keine Tokens berechnet).']; }
    $d=$S['data']; $tx=$d['texts'][$lang] ?? [];
    foreach(SITE_TEXT_KEYS as $k=>$max){ if($k==='cta_label' || $k==='hours_note') continue; $v=site_txt($j['texts'][$k] ?? '',$max,in_array($k,['about_text','hero_text','services_text','location_text','contact_text','footer_text'],true)); if($v!=='') $tx[$k]=$v; }
    if(($tx['hero_title'] ?? '')==='' && ($d['biz']['name'] ?? '')!=='') $tx['hero_title']=$d['biz']['name'];
    $d['texts'][$lang]=$tx;
    if(!$hasModuleServices && is_array($j['services'] ?? null)){ $sv=[]; foreach(array_slice($j['services'],0,8) as $x){ if(!is_array($x)) continue; $n=site_txt($x['name'] ?? '',100); if($n==='') continue; $sv[]=['name'=>[$lang=>$n],'desc'=>array_filter([$lang=>site_txt($x['desc'] ?? '',400,true)]),'price'=>''];} if($sv && empty($d['services'])) $d['services']=$sv; }
    if(is_array($j['faq'] ?? null)){ $fq=[]; foreach(array_slice($j['faq'],0,8) as $x){ if(!is_array($x)) continue; $q=site_txt($x['q'] ?? '',200); $a=site_txt($x['a'] ?? '',900,true); if($q!=='' && $a!=='') $fq[]=['q'=>[$lang=>$q],'a'=>[$lang=>$a]]; } if($fq) $d['faq']=$fq; }
    $seo=$S['seo']; $st=site_txt($j['seo']['title'] ?? '',70); $sd=site_txt($j['seo']['desc'] ?? '',170); if($st!=='' || $sd!=='') $seo[$lang]=['title'=>$st ?: ($seo[$lang]['title'] ?? ''),'desc'=>$sd ?: ($seo[$lang]['desc'] ?? '')];
    $d['ai_at']=gmdate('c');
    th_db()->prepare("UPDATE sites SET data=?,seo=?,updated_at=NOW() WHERE id=?")->execute([json_encode($d,JSON_UNESCAPED_UNICODE),json_encode($seo,JSON_UNESCAPED_UNICODE),$S['id']]);
    $used=th_charge($aid,$cost,'site:generate','site:'.$S['id'].':'.bin2hex(random_bytes(4)));
    th_log_usage($aid,'site_generate','marketing',$used,(int)((microtime(true)-$t0)*1000));
    return ['site'=>site_get($S['id'],$aid),'tokens_used'=>$used,'balance'=>th_balance($aid)];
}
/** Übersetzbare Texte einer Sprache (Struktur für die KI). */
function site_tr_source(array $S, string $l): array {
    $d=$S['data']; $src=['texts'=>(object)array_filter((array)($d['texts'][$l] ?? []),fn($v)=>is_string($v) && $v!=='')];
    $src['services']=array_map(fn($x)=>['name'=>$x['name'][$l] ?? '','desc'=>$x['desc'][$l] ?? ''],(array)($d['services'] ?? []));
    $src['faq']=array_map(fn($x)=>['q'=>$x['q'][$l] ?? '','a'=>$x['a'][$l] ?? ''],(array)($d['faq'] ?? []));
    $src['team']=array_map(fn($x)=>['role'=>$x['role'][$l] ?? ''],(array)($d['team'] ?? []));
    $src['gallery']=array_map(fn($x)=>['alt'=>$x['alt'][$l] ?? ''],(array)($d['gallery'] ?? []));
    $src['seo']=['title'=>$S['seo'][$l]['title'] ?? '','desc'=>$S['seo'][$l]['desc'] ?? ''];
    return $src;
}
/** KI-Übersetzung in weitere Sprachen (je Sprache SITE_PRICE_TRANSLATE Tokens, nur erfolgreiche werden berechnet). */
function site_translate(array $S, array $b): array {
    $aid=$S['account_id']; $from=$S['lang_default'];
    $targets=array_values(array_unique(array_filter(array_map('site_lang',(array)($b['langs'] ?? [])),fn($l)=>$l!=='' && $l!==$from)));
    if(!$targets) return ['error'=>'langs_required','status'=>400,'message'=>'Bitte mindestens eine Zielsprache wählen.'];
    $targets=array_slice($targets,0,12); $need=SITE_PRICE_TRANSLATE*count($targets);
    if(!th_rate('sitetr:'.$aid,40,3600)) return ['error'=>'rate_limited','status'=>429,'message'=>'Bitte kurz warten – zu viele Übersetzungen in kurzer Zeit.'];
    if(th_balance($aid)<$need) return ['error'=>'insufficient_tokens','status'=>402,'need'=>$need,'balance'=>th_balance($aid),'message'=>'Nicht genug Tokens: '.$need.' Tokens für '.count($targets).' Sprache(n).'];
    $src=site_tr_source($S,$from); $srcJson=json_encode($src,JSON_UNESCAPED_UNICODE);
    $done=[]; $failed=[]; $used=0; $t0=microtime(true); th_ai_context($aid,'studio');
    $d=$S['data']; $seo=$S['seo']; $langs=$S['langs'];
    foreach($targets as $l){
        $sys="Du bist professioneller Übersetzer für Websites. Übersetze alle Textwerte des JSON von ".SITE_LANG_EN[$from]." nach ".SITE_LANG_EN[$l]." – idiomatisch, im gleichen Ton, ohne Zusätze.\n"
            ."Gib AUSSCHLIESSLICH das JSON mit identischer Struktur zurück (gleiche Schlüssel, gleiche Listenlängen und Reihenfolge, leere Werte bleiben leer). Kein Markdown, kein HTML.\n"
            ."Platzhalter in doppelten eckigen Klammern [[…]] bleiben Platzhalter: nur den Inhalt in den Klammern übersetzen. Eigennamen, Telefonnummern und Preise unverändert lassen.";
        $out=th_ai($sys,[['role'=>'user','content'=>$srcJson]],3000,90); $j=site_ai_json($out);
        if(!$j || !isset($j['texts'])){ $failed[]=$l; continue; }
        $tx=[]; foreach(SITE_TEXT_KEYS as $k=>$max){ if(!isset($src['texts']->$k)) continue; $v=site_txt($j['texts'][$k] ?? '',$max,in_array($k,['about_text','hero_text','services_text','team_text','location_text','contact_text','hours_note','footer_text'],true)); if($v!=='') $tx[$k]=$v; }
        $d['texts'][$l]=$tx;
        foreach((array)($d['services'] ?? []) as $i=>$x){ $n=site_txt($j['services'][$i]['name'] ?? '',100); $ds=site_txt($j['services'][$i]['desc'] ?? '',400,true); if($n!=='') $d['services'][$i]['name'][$l]=$n; if($ds!=='') $d['services'][$i]['desc'][$l]=$ds; }
        foreach((array)($d['faq'] ?? []) as $i=>$x){ $q=site_txt($j['faq'][$i]['q'] ?? '',200); $a=site_txt($j['faq'][$i]['a'] ?? '',900,true); if($q!=='') $d['faq'][$i]['q'][$l]=$q; if($a!=='') $d['faq'][$i]['a'][$l]=$a; }
        foreach((array)($d['team'] ?? []) as $i=>$x){ $r=site_txt($j['team'][$i]['role'] ?? '',100); if($r!=='') $d['team'][$i]['role'][$l]=$r; }
        foreach((array)($d['gallery'] ?? []) as $i=>$x){ $a=site_txt($j['gallery'][$i]['alt'] ?? '',160); if($a!=='') $d['gallery'][$i]['alt'][$l]=$a; }
        $seo[$l]=['title'=>site_txt($j['seo']['title'] ?? '',70),'desc'=>site_txt($j['seo']['desc'] ?? '',170)];
        if(!in_array($l,$langs,true)) $langs[]=$l;
        $used+=th_charge($aid,SITE_PRICE_TRANSLATE,'site:translate','site:'.$S['id'].':'.$l.':'.bin2hex(random_bytes(4)));
        $done[]=$l;
    }
    if($done){
        usort($langs,fn($a,$b)=>($a===$from ? -1 : ($b===$from ? 1 : array_search($a,SITE_LANGS,true) <=> array_search($b,SITE_LANGS,true))));
        th_db()->prepare("UPDATE sites SET data=?,seo=?,langs=?,updated_at=NOW() WHERE id=?")->execute([json_encode($d,JSON_UNESCAPED_UNICODE),json_encode($seo,JSON_UNESCAPED_UNICODE),json_encode($langs),$S['id']]);
        th_log_usage($aid,'site_translate','marketing',$used,(int)((microtime(true)-$t0)*1000));
    }
    if(!$done) return ['error'=>'ai_unavailable','status'=>503,'message'=>'Übersetzung gerade nicht möglich – bitte gleich noch einmal versuchen (keine Tokens berechnet).','failed'=>$failed];
    return ['site'=>site_get($S['id'],$aid),'done'=>$done,'failed'=>$failed,'tokens_used'=>$used,'balance'=>th_balance($aid)];
}

/* ======================================================================= API-Antwort */
function site_templates_public(?array $profile=null): array {
    $rec=site_template_for($profile); $o=[];
    foreach(SITE_TEMPLATES as $k=>$t) $o[]=['id'=>$k,'name'=>$t['name'],'tag'=>$t['tag'],'preset'=>$t['preset'],'font'=>$t['font'],'hero'=>$t['hero'],'recommended'=>$k===$rec,
      'colors'=>array_intersect_key(SITE_PRESETS[$t['preset']],array_flip(['bg','ink','accent','accent2'])),'demo_url'=>'/chat/site.php?demo='.$k];
    return $o;
}
function site_out(array $S, bool $full=true): array {
    $o=['id'=>$S['id'],'slug'=>$S['slug'],'status'=>$S['status'],'template'=>$S['template'],'lang_default'=>$S['lang_default'],'langs'=>$S['langs'],'custom_domain'=>$S['custom_domain'],
      'name'=>(string)($S['data']['biz']['name'] ?? ''),'url'=>site_url($S),'path'=>'/chat/site.php?s='.rawurlencode($S['slug']),
      'created_at'=>th_dbiso($S['created_at']),'updated_at'=>th_dbiso($S['updated_at']),'published_at'=>th_dbiso($S['published_at']),'dirty'=>site_dirty($S)];
    if(!$full) return $o;
    $mods=site_modules($S['account_id']);
    return $o+['theme'=>$S['theme'],'data'=>$S['data'],'seo'=>(object)$S['seo'],'assets'=>site_assets($S['id']),'issues'=>site_issues($S),
      'preview_url'=>'/chat/site.php?s='.rawurlencode($S['slug']).'&preview='.site_preview_token($S),'modules'=>$mods,'module_services'=>site_module_services($S['account_id'],$mods),
      'widget'=>site_widget($S['account_id'],$S['custom_domain']),'sitemap'=>site_url($S).'&sitemap=xml','exported_at'=>th_dbiso($S['exported_at'])];
}
function site_meta(int $aid): array {
    $cp=site_can_publish($aid);
    return ['templates'=>site_templates_public(site_profile($aid)),'presets'=>array_map(fn($p)=>array_diff_key($p,['dark'=>1])+['dark'=>!empty($p['dark'])],SITE_PRESETS),
      'fonts'=>array_map(fn($f)=>['label'=>$f['label'],'h'=>$f['h']],SITE_FONTS),'langs'=>array_map(fn($l)=>['id'=>$l,'name'=>SITE_LANG_NAMES[$l]],SITE_LANGS),
      'sections'=>SITE_SECTIONS,'ctas'=>SITE_CTAS,'can_publish'=>$cp['ok'],'publish_plan'=>$cp['plan'],
      'plan'=>['id'=>SITE_PLAN,'eur_month'=>(CH_PLANS[SITE_PLAN][1] ?? 900)/100,'checkout'=>site_checkout_url()],
      'prices'=>['generate'=>SITE_PRICE_GENERATE,'translate'=>SITE_PRICE_TRANSLATE],'limits'=>['sites'=>SITE_MAX_SITES,'assets'=>SITE_MAX_ASSETS,'image_bytes'=>SITE_IMG_MAX],'balance'=>th_balance_ledger($aid)];
}

/* ======================================================================= ZIP-Export */
function site_export_zip(array $S): array {
    if(!class_exists('ZipArchive')) return ['error'=>'zip_missing','status'=>501,'message'=>'ZIP-Export auf diesem Server nicht verfügbar.'];
    require_once __DIR__.'/site-render.php';
    $tmp=tempnam(sys_get_temp_dir(),'chsite'); $z=new ZipArchive(); if($z->open($tmp,ZipArchive::OVERWRITE)!==true) return ['error'=>'zip_failed','status'=>500];
    $assets=site_assets($S['id']); $aurl=[]; foreach($assets as $a) $aurl[$a['id']]='assets/'.$a['file'];
    $wid=site_widget($S['account_id'],$S['custom_domain']);
    foreach($S['langs'] as $l){ $sfx=$l===$S['lang_default'] ? '' : '-'.$l;
        foreach(['home'=>'index','impressum'=>'impressum','datenschutz'=>'datenschutz'] as $page=>$fn)
            $z->addFromString($fn.$sfx.'.html',site_render($S,$l,['mode'=>'export','page'=>$page,'assets'=>$aurl,'widget_key'=>!empty($S['data']['widget']) ? $wid['key'] : null])); }
    foreach($assets as $a){ $f=__DIR__.'/uploads/sites/'.$S['id'].'/'.basename($a['file']); if(is_file($f)) $z->addFile($f,'assets/'.$a['file']); }
    if(is_file(__DIR__.'/site-page.js')) $z->addFile(__DIR__.'/site-page.js','assets/site.js');
    $z->addFromString('LIESMICH.txt',"ChatHelp – statischer Export deiner Website\n============================================\n\n1. Den Inhalt dieses Ordners per FTP/SFTP in das Web-Verzeichnis deiner Domain laden (index.html ist die Startseite).\n2. SSL/HTTPS richtest du bei deinem Hosting-Anbieter ein.\n3. Das Kontaktformular sendet an ChatHelp (Anfragen erscheinen im Studio unter „Website erstellen → Anfragen“).\n4. Der Chat-Assistent wird von chat-help.com geladen (nur wenn ein Widget aktiv ist). Trage deine Domain im Studio unter Website → Widget-Domain ein, falls du die Domainliste nutzt.\n5. Änderungen machst du im Studio und exportierst dann erneut.\n\nDu bist für die Inhalte (inkl. Impressum und Datenschutzerklärung) selbst verantwortlich.\n\n---\nChatHelp – static export of your website: upload the folder contents to your web space (index.html = home page), set up HTTPS with your host. The contact form posts to ChatHelp; enquiries appear in the Studio.\n");
    $z->close();
    th_db()->prepare("UPDATE sites SET exported_at=NOW() WHERE id=?")->execute([$S['id']]);
    return ['file'=>$tmp,'name'=>$S['slug'].'-website.zip'];
}

/* ======================================================================= REST-Router */
/** /v1/sites… → [HTTP-Code, Antwort] oder null (nicht zuständig). Export schreibt die ZIP-Datei selbst und beendet. */
function site_api(string $r, string $m, int $aid, array $b, array $acc): ?array {
    if($r!=='sites' && !str_starts_with($r,'sites/')) return null;
    site_bootstrap();
    if($r==='sites' && $m==='GET') return [200,['sites'=>array_map(fn($s)=>site_out($s,false),site_list($aid))]+site_meta($aid)];
    if($r==='sites' && $m==='POST'){
        if(!th_rate('sitecreate:'.$aid,20,3600)) return [429,['error'=>'rate_limited','message'=>'Bitte kurz warten.']];
        $S=site_create($aid,$b); if(isset($S['error'])) return [$S['error']==='site_limit' ? 409 : 422,$S];
        return [200,['ok'=>true,'site'=>site_out($S)]+site_meta($aid)];
    }
    if($r==='sites/slug-check' && $m==='GET'){ [$ok,$s,$why]=site_slug_check((string)($_GET['slug'] ?? ''),(int)($_GET['id'] ?? 0));
        if($ok===false && $why==='slug_taken' && ($own=site_get((int)($_GET['id'] ?? 0),$aid)) && $own['slug']===$s) $ok=true;
        return [200,['ok'=>$ok,'slug'=>$s,'reason'=>$why ?: null,'message'=>$ok ? null : site_slug_msg($why)]]; }
    if(!preg_match('#^sites/(\d{1,10})(?:/([a-z-]+))?$#',$r,$mm)) return [404,['error'=>'not_found']];
    $S=site_get((int)$mm[1],$aid); if(!$S) return [404,['error'=>'site_not_found','message'=>'Website nicht gefunden.']];   // fremde IDs sehen aus wie nicht vorhandene
    $act=$mm[2] ?? '';
    switch(true){
      case $act==='' && $m==='GET': return [200,['site'=>site_out($S)]+site_meta($aid)];
      case $act==='' && $m==='POST':
        if(!th_rate('sitesave:'.$aid,600,3600)) return [429,['error'=>'rate_limited','message'=>'Zu viele Speichervorgänge – bitte kurz warten.']];
        $U=site_update($S,$b); if(isset($U['error'])) return [422,$U]; return [200,['ok'=>true,'site'=>site_out($U)]];
      case $act==='generate' && $m==='POST': $x=site_generate($S,$b); if(isset($x['error'])) return [(int)$x['status'],$x]; $x['site']=site_out($x['site']); return [200,['ok'=>true]+$x];
      case $act==='translate' && $m==='POST': $x=site_translate($S,$b); if(isset($x['error'])) return [(int)$x['status'],$x]; $x['site']=site_out($x['site']); return [200,['ok'=>true]+$x];
      case $act==='upload' && $m==='POST':
        if(!th_rate('siteup:'.$aid,80,3600)) return [429,['error'=>'rate_limited','message'=>'Zu viele Uploads – bitte kurz warten.']];
        $x=site_upload($S,$b['data'] ?? null,(string)($b['kind'] ?? 'image')); if(isset($x['error'])) return [(int)$x['status'],$x];
        return [200,['ok'=>true,'asset'=>$x['asset'],'assets'=>site_assets($S['id'])]];
      case $act==='asset-delete' && $m==='POST': $x=site_asset_delete($S,(int)($b['asset_id'] ?? 0)); if(isset($x['error'])) return [(int)$x['status'],$x];
        return [200,['ok'=>true,'site'=>site_out(site_get($S['id'],$aid))]];
      case $act==='publish' && $m==='POST': $x=site_publish($S,(string)$acc['email']); if(isset($x['error'])) return [(int)$x['status'],$x]; return [200,['ok'=>true,'site'=>site_out($x['site'])]];
      case $act==='unpublish' && $m==='POST': return [200,['ok'=>true,'site'=>site_out(site_unpublish($S))]];
      case $act==='delete' && $m==='POST': site_delete($S); return [200,['ok'=>true]];
      case $act==='leads' && $m==='GET': return [200,['leads'=>site_leads($S['id'])]];
      case $act==='lead-status' && $m==='POST':
        $lid=(int)($b['lead_id'] ?? 0); $st=(string)($b['status'] ?? '');
        if($st==='delete') th_db()->prepare("DELETE FROM site_leads WHERE id=? AND site_id=?")->execute([$lid,$S['id']]);
        elseif(in_array($st,['new','done'],true)) th_db()->prepare("UPDATE site_leads SET status=? WHERE id=? AND site_id=?")->execute([$st,$lid,$S['id']]);
        else return [400,['error'=>'invalid_status']];
        return [200,['ok'=>true,'leads'=>site_leads($S['id'])]];
      case $act==='export' && $m==='GET':
        $cp=site_can_publish($aid); if(!$cp['ok']) return [402,['error'=>'plan_required','message'=>'Der ZIP-Export ist mit aktivem Paket verfügbar – z. B. dem Plan „Website“.','checkout'=>site_checkout_url((string)$acc['email'])]];
        $blk=array_values(array_filter(site_issues($S),fn($i)=>$i['block'])); if($blk) return [422,['error'=>'issues','message'=>'Bitte zuerst die markierten Angaben ergänzen.','issues'=>$blk]];
        if(!th_rate('siteexp:'.$aid,30,3600)) return [429,['error'=>'rate_limited','message'=>'Bitte kurz warten.']];
        $x=site_export_zip($S); if(isset($x['error'])) return [(int)$x['status'],$x];
        th_nocache(); header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="'.$x['name'].'"'); header('Content-Length: '.filesize($x['file']));
        readfile($x['file']); @unlink($x['file']); exit;
    }
    return [404,['error'=>'not_found']];
}
