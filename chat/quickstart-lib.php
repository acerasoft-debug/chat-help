<?php
/**
 * ChatHelp — Schnellstart: „in 5 Minuten ein laufender Agent“ (für Betriebe ohne Technikwissen, Handy zuerst)
 * ==========================================================================================================
 * Ablauf (einrichtung.js, Modus „qs“): Link einfügen (Website, Google Maps, Instagram) → KI-Entwurf (Name, Adresse, Telefon, Öffnungszeiten,
 * Leistungen/Preise bzw. Karte, häufige Fragen) → „Stimmt so“ · ohne Link: Branche + 3–4 kurze Fragen → Stimme (3 je Sprache, Hörprobe) +
 * Begrüßung (Vorschläge) → im Browser mit dem eigenen Agenten sprechen (vor der Zahlung) → Paket (mit Testzeitraum) → Nummer + Weiterleitung
 * → Testanruf → „Dein Agent ist live“. Alles landet in denselben Schritten wie die ausführliche Einrichtung (onboarding-lib) und bleibt im Studio änderbar.
 *
 * Tabelle onboarding_events: je Konto/Einrichtung Schritt + Ereignis (view, done, skip, help, abandon) → qs_funnel() für die Abbruch-Analyse.
 * Erinnerungen: qs_reminders_lazy() (Heartbeat) mailt offene Einrichtungen nach 1 Stunde und nach 1 Tag (je einmal).
 * REST (qs_api, CH_MODULE_LIBS['quickstart'] – vor onboarding, da onb_api fremde onboarding/*-Routen mit 404 beantwortet):
 *   POST onboarding/import {id,url} · POST onboarding/draft {id,draft} · POST onboarding/sector {sector,company,address,phone,hours}
 *   · GET onboarding/qs?id= · POST onboarding/qs {id,step} · POST onboarding/greeting {id,text,voice_id?} · POST onboarding/speak {id,text,t}
 *   · POST onboarding/event {id,step,ev} · POST onboarding/number {id,area,cc} · GET onboarding/funnel (Betreiber)
 * Google Maps: mit GOOGLE_PLACES_KEY (Places API, Text Search) kommen Öffnungszeiten, Telefon und Website direkt von Google; ohne Schlüssel nur Name/Adresse
 * aus dem Link und der Seite. Instagram: Name + Bio + Link in der Bio (wenn Instagram die Seite ausliefert).
 */
require_once __DIR__.'/onboarding-lib.php';
if(is_file(__DIR__.'/train-lib.php')) require_once __DIR__.'/train-lib.php';

const QS_STEPS = ['link','draft','sector','voice','try','plan','number','live'];
const QS_HOURS_PRESETS = [
  'mo-fr'=>['1'=>[['09:00','18:00']],'2'=>[['09:00','18:00']],'3'=>[['09:00','18:00']],'4'=>[['09:00','18:00']],'5'=>[['09:00','18:00']],'6'=>[],'0'=>[]],
  'mo-sa'=>['1'=>[['09:00','19:00']],'2'=>[['09:00','19:00']],'3'=>[['09:00','19:00']],'4'=>[['09:00','19:00']],'5'=>[['09:00','19:00']],'6'=>[['09:00','16:00']],'0'=>[]],
  'gastro'=>['1'=>[],'2'=>[['11:30','22:00']],'3'=>[['11:30','22:00']],'4'=>[['11:30','22:00']],'5'=>[['11:30','23:00']],'6'=>[['11:30','23:00']],'0'=>[['12:00','22:00']]],
  '24-7'=>['1'=>[['00:00','23:59']],'2'=>[['00:00','23:59']],'3'=>[['00:00','23:59']],'4'=>[['00:00','23:59']],'5'=>[['00:00','23:59']],'6'=>[['00:00','23:59']],'0'=>[['00:00','23:59']]],
];

/* ======================================================================= Analyse */
function qs_bootstrap(): void {
    static $d=false; if($d) return; $d=true;
    th_db()->exec("CREATE TABLE IF NOT EXISTS onboarding_events (id BIGINT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, onb_id INT NOT NULL DEFAULT 0, step VARCHAR(24) NOT NULL,
        ev VARCHAR(12) NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX(onb_id, step), INDEX(created_at)) ENGINE=InnoDB");
}
function qs_event(int $aid, int $onbId, string $step, string $ev): bool {
    if(!in_array($step,array_merge(QS_STEPS,['help','start']),true) || !in_array($ev,['view','done','skip','help','back','error'],true)) return false;
    qs_bootstrap(); th_db()->prepare("INSERT INTO onboarding_events(account_id,onb_id,step,ev) VALUES(?,?,?,?)")->execute([$aid,$onbId,$step,$ev]); return true;
}
/** Trichter der letzten $days Tage: je Schritt erreicht (view) / abgeschlossen (done) / zuletzt gesehen (= hier aufgehört, solange nicht live). */
function qs_funnel(int $days=30): array {
    qs_bootstrap(); $p=th_db(); $since=date('Y-m-d H:i:s',time()-$days*86400);
    $s=$p->prepare("SELECT step, ev, COUNT(DISTINCT onb_id) n FROM onboarding_events WHERE created_at>=? AND onb_id>0 GROUP BY step, ev"); $s->execute([$since]);
    $o=[]; foreach(QS_STEPS as $st) $o[$st]=['view'=>0,'done'=>0,'last'=>0,'help'=>0];
    foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r) if(isset($o[$r['step']][$r['ev']])) $o[$r['step']][$r['ev']]=(int)$r['n'];
    /* letzter gesehener Schritt je Einrichtung, die nicht live ist */
    $l=$p->prepare("SELECT e.onb_id, e.step FROM onboarding_events e JOIN (SELECT onb_id, MAX(id) mx FROM onboarding_events WHERE created_at>=? AND onb_id>0 AND ev='view' GROUP BY onb_id) t ON t.mx=e.id
        WHERE e.onb_id NOT IN (SELECT onb_id FROM onboarding_events WHERE step='live' AND ev='view')"); $l->execute([$since]);
    foreach($l->fetchAll(PDO::FETCH_ASSOC) as $r) if(isset($o[$r['step']])) $o[$r['step']]['last']++;
    return $o;
}

/* ======================================================================= Link → Quelle */
function qs_kind(string $url): string {
    $h=strtolower((string)parse_url(qs_norm_url($url),PHP_URL_HOST)); $path=strtolower((string)parse_url(qs_norm_url($url),PHP_URL_PATH));
    if(preg_match('/(^|\.)maps\.app\.goo\.gl$|(^|\.)goo\.gl$/',$h) || (preg_match('/(^|\.)google\.[a-z.]+$/',$h) && str_starts_with($path,'/maps')) || preg_match('/^maps\.google\./',$h)) return 'maps';
    if(preg_match('/(^|\.)instagram\.com$/',$h)) return 'instagram';
    return 'web';
}
function qs_norm_url(string $url): string { $u=trim($url); if($u!=='' && !preg_match('#^https?://#i',$u)) $u='https://'.ltrim($u,'/'); return mb_substr($u,0,600); }
function qs_meta(string $html, string $prop): string {
    if(preg_match('/<meta[^>]+(?:property|name|itemprop)=["\']'.preg_quote($prop,'/').'["\'][^>]*content=["\']([^"\']*)["\']/i',$html,$m) || preg_match('/<meta[^>]+content=["\']([^"\']*)["\'][^>]*(?:property|name|itemprop)=["\']'.preg_quote($prop,'/').'["\']/i',$html,$m))
        return trim(html_entity_decode($m[1],ENT_QUOTES|ENT_HTML5,'UTF-8'));
    return '';
}
/** Google Places (optional): Name, Adresse, Telefon, Website, Öffnungszeiten (strukturiert). */
function qs_places(string $query): ?array {
    $k=defined('GOOGLE_PLACES_KEY') ? trim((string)GOOGLE_PLACES_KEY) : ''; if($k==='' || trim($query)==='') return null;
    $ch=curl_init('https://places.googleapis.com/v1/places:searchText');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>12,CURLOPT_POSTFIELDS=>json_encode(['textQuery'=>$query,'languageCode'=>'de']),
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-Goog-Api-Key: '.$k,'X-Goog-FieldMask: places.displayName,places.formattedAddress,places.internationalPhoneNumber,places.websiteUri,places.regularOpeningHours,places.types']]);
    $r=(string)curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    $j=json_decode($r,true); $p=$j['places'][0] ?? null; if($code!==200 || !is_array($p)) return null;
    $hours=[]; foreach(($p['regularOpeningHours']['periods'] ?? []) as $per){ $d=(string)($per['open']['day'] ?? ''); if($d==='' || !isset($per['close'])) continue;
        $hours[$d][]=[sprintf('%02d:%02d',(int)$per['open']['hour'],(int)($per['open']['minute'] ?? 0)),sprintf('%02d:%02d',(int)$per['close']['hour'],(int)($per['close']['minute'] ?? 0))]; }
    return ['company'=>(string)($p['displayName']['text'] ?? ''),'address'=>(string)($p['formattedAddress'] ?? ''),'phone'=>(string)($p['internationalPhoneNumber'] ?? ''),'website'=>(string)($p['websiteUri'] ?? ''),'hours'=>$hours];
}
/** Text zur KI-Auswertung je Linkart. Rückgabe ['ok','kind','url','title','text','facts'=>[vorab sichere Angaben]] oder ['ok'=>false,'message']. */
function qs_source(string $url): array {
    if(!function_exists('train_http_get')) return ['ok'=>false,'message'=>'Der Website-Import ist auf diesem Server nicht verfügbar.'];
    $url=qs_norm_url($url); if(!filter_var($url,FILTER_VALIDATE_URL)) return ['ok'=>false,'message'=>'Das sieht nicht wie ein Link aus – bitte die Adresse aus dem Browser kopieren.'];
    $kind=qs_kind($url); $facts=[]; $text=''; $title='';
    if($kind==='maps'){
        $r=train_http_get($url,'html',800000,10,6); $final=(string)($r['url'] ?? $url); $html=(string)($r['body'] ?? '');
        $name=''; if(preg_match('#/maps/place/([^/@?]+)#',$final,$m)) $name=trim(str_replace('+',' ',rawurldecode($m[1])));
        if($name==='' && preg_match('/[?&]q=([^&]+)/',$final,$m)) $name=trim(str_replace('+',' ',rawurldecode($m[1])));
        $og=qs_meta($html,'og:title'); $desc=qs_meta($html,'og:description') ?: qs_meta($html,'description');
        if($og!=='' && !preg_match('/^Google Maps$/i',$og)){ $parts=array_map('trim',explode('·',$og)); if($name==='') $name=$parts[0]; if(isset($parts[1])) $facts['address']=$parts[1]; }
        if($name!=='') $facts['company']=$name;
        $pl=qs_places(trim($name.' '.($facts['address'] ?? '')));
        if($pl){ foreach(['company','address','phone','website'] as $k) if($pl[$k]!=='') $facts[$k]=$pl[$k]; if($pl['hours']) $facts['hours']=$pl['hours']; }
        $title=$name; $text="Google-Maps-Eintrag: $name\n".($facts['address'] ?? '')."\n$desc";
        if(!empty($facts['website'])){ $w=qs_site_text($facts['website']); if($w['text']!=='') $text.="\n\n".$w['text']; }
        if($name==='' && empty($facts)) return ['ok'=>false,'message'=>'Aus diesem Google-Maps-Link konnten wir nichts lesen – bitte den Link „Teilen“ → „Link kopieren“ nehmen oder die Website einfügen.'];
    } elseif($kind==='instagram'){
        $user=''; if(preg_match('#instagram\.com/([A-Za-z0-9._]{1,30})#i',$url,$m) && !in_array(strtolower($m[1]),['p','reel','explore','stories'],true)) $user=$m[1];
        if($user==='') return ['ok'=>false,'message'=>'Bitte den Link zu deinem Instagram-Profil einfügen (instagram.com/deinname).'];
        $r=train_http_get('https://www.instagram.com/'.$user.'/','html',900000,10,3); $html=(string)($r['body'] ?? '');
        $og=qs_meta($html,'og:title'); $desc=qs_meta($html,'og:description') ?: qs_meta($html,'description');
        if($og!=='' && preg_match('/^(.+?)\s*\(@/u',$og,$m)) $facts['company']=trim($m[1]);
        $bio=''; if(preg_match('/"biography":"((?:[^"\\\\]|\\\\.)*)"/',$html,$m)) $bio=(string)json_decode('"'.$m[1].'"');
        $ext=''; if(preg_match('/"external_url":"((?:[^"\\\\]|\\\\.)*)"/',$html,$m)) $ext=(string)json_decode('"'.$m[1].'"');
        $title=$facts['company'] ?? '@'.$user; $text="Instagram-Profil @$user\n$og\n$desc\n$bio";
        if($ext!==''){ $facts['website']=$ext; $w=qs_site_text($ext); if($w['text']!=='') $text.="\n\n".$w['text']; }
        if(trim($desc.$bio)==='' && $ext==='' ) return ['ok'=>true,'kind'=>$kind,'url'=>$url,'title'=>$title,'text'=>$text,'facts'=>$facts,'thin'=>true];
    } else {
        $w=qs_site_text($url); if($w['text']==='') return ['ok'=>false,'message'=>$w['message'] ?: 'Die Website konnten wir gerade nicht lesen – bitte später erneut oder Branche wählen.'];
        $text=$w['text']; $title=$w['title']; $facts['website']=$url;
    }
    return ['ok'=>true,'kind'=>$kind,'url'=>$url,'title'=>$title,'text'=>mb_substr($text,0,24000),'facts'=>$facts];
}
function qs_site_text(string $url): array {
    if(!function_exists('train_fetch_site')) return ['text'=>'','title'=>'','message'=>''];
    try{ $s=train_fetch_site($url,6); }catch(Throwable $e){ return ['text'=>'','title'=>'','message'=>'']; }
    if(!$s['pages']) return ['text'=>'','title'=>'','message'=>(string)(($s['errors'][0]['hint'] ?? '') ?: '')];
    return ['text'=>train_pages_raw($s['pages'],(string)$s['footer']),'title'=>(string)($s['pages'][0]['title'] ?? ''),'message'=>''];
}

/* ======================================================================= KI-Entwurf */
function qs_extract(array $src, array $P, string $lang='de'): array {
    $menu=in_array('order',$P['mods'],true); $f=$src['facts'] ?? [];
    $sys='Du liest Angaben eines Betriebs (Website, Google-Maps-Eintrag oder Instagram) und gibst NUR JSON zurück – ohne Erklärung, ohne Markdown. '
        .'Erfinde nichts: Was nicht im Text steht, bleibt leer ("" bzw. []). Preise als Zahl in Euro (ohne Währungszeichen), Dauer in Minuten. Antworte in der Sprache des Textes. '
        .'Schema: {"company":"","address":"Straße Nr, PLZ Ort","phone":"","email":"","hours":{"1":[["09:00","18:00"]],"2":[],"3":[],"4":[],"5":[],"6":[],"0":[]},'
        .($menu ? '"menu":[{"category":"","name":"","price":0,"description":""}],' : '"services":[{"name":"","price":0,"duration_min":0}],')
        .'"faq":[{"q":"","a":""}],"about":"2–3 Sätze, was der Betrieb anbietet"} – hours: 0=Sonntag … 6=Samstag, geschlossen = []; höchstens 25 '.($menu ? 'Gerichte' : 'Leistungen').', höchstens 8 Fragen (nur mit Antwort aus dem Text).';
    $msg=[['role'=>'user','content'=>'Quelle: '.$src['kind'].' '.$src['url']."\nTitel: ".$src['title']."\n\n".mb_substr((string)$src['text'],0,18000)]];
    $raw=function_exists('train_ai') ? train_ai($sys,$msg,1800,45) : th_ai($sys,$msg,1800,45);
    $j=null; if(is_string($raw) && preg_match('/\{.*\}/s',$raw,$m)) $j=json_decode($m[0],true);
    $d=is_array($j) ? $j : qs_guess((string)$src['text'],(string)$src['title']);
    foreach($f as $k=>$v) if($v!=='' && $v!==[] && (empty($d[$k]) || in_array($k,['company','address','phone','hours'],true))) $d[$k]=$v;   // Google/Instagram-Fakten haben Vorrang
    return qs_clean($d,$P)+['ai'=>is_array($j)];
}
/** Ohne KI: Name aus dem Titel, Telefon und Adresse per Muster. */
function qs_guess(string $text, string $title): array {
    $d=['company'=>trim((string)preg_split('/\s+[|\-–·]\s+/u',$title)[0])];
    if(preg_match('/(\+\d{2}[\d\s\/()-]{7,18}\d|0\d{2,5}[\s\/-]?\d[\d\s-]{4,12}\d)/u',$text,$m)) $d['phone']=trim($m[1]);
    if(preg_match('/([A-ZÄÖÜ][\p{L}.\- ]{2,40}(?:straße|str\.|weg|platz|allee|gasse|ring|damm|ufer|cad\.|caddesi|sokak|sk\.)\s*\d+[a-z]?\s*,?\s*\d{4,5}\s+[\p{L}\- ]{2,30})/iu',$text,$m)) $d['address']=trim($m[1]);
    return $d;
}
/** Entwurf säubern (Typen, Längen, Zeiten prüfen). */
function qs_clean(array $d, array $P): array {
    $s=fn($v,$n)=>mb_substr(trim(strip_tags(is_scalar($v) ? (string)$v : '')),0,$n);
    $o=['company'=>$s($d['company'] ?? '',120),'address'=>$s($d['address'] ?? '',200),'phone'=>$s($d['phone'] ?? '',40),'email'=>$s($d['email'] ?? '',120),'website'=>$s($d['website'] ?? '',300),'about'=>$s($d['about'] ?? '',600)];
    $h=null; if(is_array($d['hours'] ?? null)){ $raw=[]; for($w=0;$w<=6;$w++){ $rows=$d['hours'][(string)$w] ?? ($d['hours'][$w] ?? []); $raw[(string)$w]=is_array($rows) ? array_slice(array_values(array_filter($rows,'is_array')),0,3) : []; }
        $err=null; $p=function_exists('appt_hours_parse') ? appt_hours_parse($raw,$err) : null; if($err===null && is_array($p) && array_filter($p)) $h=$p; }
    $o['hours']=$h;
    /* Preis in Euro (KI, Formular) oder schon in Cent (Entwurf, der vom Studio unverändert zurückkommt) */
    $money=fn($r)=>isset($r['price_cents']) && is_numeric($r['price_cents']) ? max(0,(int)$r['price_cents']) : (is_numeric($r['price'] ?? null) && (float)$r['price']>0 ? (int)round((float)$r['price']*100) : 0);
    if(in_array('order',$P['mods'],true)){ $o['menu']=[]; foreach(array_slice((array)($d['menu'] ?? []),0,40) as $r){ if(!is_array($r)) continue; $n=$s($r['name'] ?? '',120); if($n==='') continue; $o['menu'][]=['category'=>$s($r['category'] ?? '',60),'name'=>$n,'price_cents'=>$money($r),'description'=>$s($r['description'] ?? '',300)]; } }
    else { $o['services']=[]; foreach(array_slice((array)($d['services'] ?? []),0,40) as $r){ if(!is_array($r)) continue; $n=$s($r['name'] ?? '',120); if($n==='') continue; $dur=(int)($r['duration_min'] ?? 0); $o['services'][]=['name'=>$n,'duration_min'=>$dur>=5 && $dur<=600 ? $dur : 30,'price_cents'=>$money($r)]; } }
    $o['faq']=[]; foreach(array_slice((array)($d['faq'] ?? []),0,12) as $r){ if(!is_array($r)) continue; $q=$s($r['q'] ?? ($r['question'] ?? ''),300); $a=$s($r['a'] ?? ($r['answer'] ?? ''),1200); if($q!=='' && $a!=='') $o['faq'][]=['question'=>$q,'answer'=>$a]; }
    return $o;
}
/** Entwurf übernehmen: dieselben Schritte wie die Einrichtung (geprüft, gespeichert) + Wissensbasis. Rückgabe ['ok','saved'=>[schritte],'errors'=>[schritt=>meldung]]. */
function qs_apply(int $aid, array $onb, array $draft): array {
    [$P]=onb_ctx($aid,$onb); $d=qs_clean($draft,$P); $saved=[]; $errors=[];
    $put=function(string $step,$val) use($aid,&$onb,&$saved,&$errors){ $r=onb_answer($aid,$onb,$step,$val); if(($r[0] ?? 200)===200){ $saved[]=$step; $o=onb_get($aid,$onb['id']); if($o) $onb=$o; } else $errors[$step]=(string)($r[1]['message'] ?? 'ungültig'); };
    $steps=array_column(onb_catalog($P),'id');
    if($d['company']!=='' && in_array('company',$steps,true)) $put('company',$d['company']);
    if($d['address']!=='' && in_array('address',$steps,true)) $put('address',$d['address']);
    if($d['phone']!=='' && in_array('phone_public',$steps,true)) $put('phone_public',$d['phone']);
    if($d['hours'] && in_array('hours',$steps,true)) $put('hours',$d['hours']);
    if(!empty($d['services']) && in_array('services',$steps,true)) $put('services',$d['services']);
    if(!empty($d['menu']) && in_array('menu',$steps,true)) $put('menu',$d['menu']);
    if($d['faq'] && in_array('faq',$steps,true)) $put('faq',$d['faq']);
    if($d['about']!=='' && in_array('focus',$steps,true)) $put('focus',$d['about']);
    /* Wissensbasis: Kurzfassung aller Angaben (der Agent antwortet daraus, auch auf Fragen außerhalb der Schritte) */
    if(function_exists('train_apply_kb')){
        $kb=[]; if($d['company']!=='') $kb[]='Betrieb: '.$d['company']; if($d['about']!=='') $kb[]=$d['about']; if($d['address']!=='') $kb[]='Adresse: '.$d['address'];
        if($d['phone']!=='') $kb[]='Telefon: '.$d['phone']; if($d['email']!=='') $kb[]='E-Mail: '.$d['email']; if($d['website']!=='') $kb[]='Website: '.$d['website'];
        if($d['hours']) $kb[]='Öffnungszeiten: '.onb_hours_text($d['hours']);
        foreach($d['services'] ?? [] as $r) $kb[]='Leistung: '.$r['name'].($r['price_cents'] ? ' – '.number_format($r['price_cents']/100,2,',','.').' €' : '').($r['duration_min'] ? ' ('.$r['duration_min'].' Min.)' : '');
        foreach($d['menu'] ?? [] as $r) $kb[]=($r['category']!=='' ? $r['category'].': ' : '').$r['name'].($r['price_cents'] ? ' – '.number_format($r['price_cents']/100,2,',','.').' €' : '').($r['description']!=='' ? ' ('.$r['description'].')' : '');
        try{ train_apply_kb($aid,$onb['base'],implode("\n",$kb),'append',['kind'=>'url','ref'=>$d['website'] ?: 'schnellstart','title'=>'Schnellstart']); }catch(Throwable $e){ error_log('CH qs kb: '.$e->getMessage()); }
    }
    $onb=onb_get($aid,$onb['id']) ?? $onb; $onb['meta']['qs']=array_merge((array)($onb['meta']['qs'] ?? []),['draft_at'=>gmdate('c')]); onb_save($onb,['meta']);
    return ['ok'=>!$errors || count($saved)>0,'saved'=>$saved,'errors'=>$errors];
}

/* ======================================================================= Ohne Link: Branche + 3–4 Fragen */
/** Branchen für die Auswahl (Reihenfolge wie im Profil); plan = passender Agent. */
function qs_sectors(): array {
    if(is_file(__DIR__.'/profile-lib.php')) require_once __DIR__.'/profile-lib.php';
    $o=[]; foreach((function_exists('profile_sectors') ? profile_sectors() : []) as $id=>$s){ $plan=$s['plans'][0] ?? ''; $pl=$plan!=='' ? ch_plan($plan) : null; if(!$pl || !onb_trial_ok($pl)) continue; $o[]=['id'=>$id,'label'=>$s['label'],'icon'=>$s['icon'],'plan'=>$plan]; }
    return $o;
}
/** $id > 0: Branche für eine bestehende Einrichtung (Agent schon gewählt) – sonst Einrichtung für den Agenten der Branche anlegen/finden. */
function qs_sector_start(int $aid, string $sector, array $in, int $id=0): array {
    $S=null; foreach(qs_sectors() as $s) if($s['id']===$sector) $S=$s; if(!$S) return [400,['error'=>'sector','message'=>'Bitte eine Branche wählen.']];
    [$c,$onb]=$id>0 ? onb_resolve($aid,$id,'') : onb_resolve($aid,0,$S['plan']); if($c!==200) return [$c,$onb];
    [$P]=onb_ctx($aid,$onb); $steps=array_column(onb_catalog($P),'id');
    $d=['company'=>(string)($in['company'] ?? ''),'address'=>(string)($in['address'] ?? ''),'phone'=>(string)($in['phone'] ?? '')];
    $hp=(string)($in['hours'] ?? ''); $d['hours']=QS_HOURS_PRESETS[$hp] ?? null;
    /* Vorlage der Branche: typische Leistungen/Karte (Preise bitte prüfen) – sonst Vorschläge des Agenten */
    $base=$onb['base']; if(in_array('services',$steps,true) && !empty(ONB_PRESETS[$base])) $d['services']=array_map(fn($r)=>['name'=>$r['name'],'price'=>$r['price_cents']/100,'duration_min'=>$r['duration_min']],ONB_PRESETS[$base]);
    if(in_array('menu',$steps,true)) $d['menu']=array_map(fn($r)=>['category'=>$r['category'],'name'=>$r['name'],'price'=>$r['price_cents']/100,'description'=>$r['description']],ONB_PRESETS['menu']);
    $r=qs_apply($aid,$onb,$d); $onb=onb_get($aid,$onb['id']) ?? $onb; $onb['meta']['qs']=array_merge((array)($onb['meta']['qs'] ?? []),['sector'=>$sector]); onb_save($onb,['meta']);
    return [200,['ok'=>true,'onboarding'=>onb_public($onb),'saved'=>$r['saved'],'errors'=>$r['errors']]];
}

/* ======================================================================= Stimme + Begrüßung */
/** Drei Stimmen der Sprache zur Auswahl (zwei Frauen, ein Mann – Server-Stimmen zuerst, sonst Browser-Stimmen des Katalogs). */
function qs_voices(string $lang): array {
    if(!function_exists('vc_list') && is_file(__DIR__.'/voice-catalog.php')) require_once __DIR__.'/voice-catalog.php';
    if(!function_exists('vc_list')) return [];
    $L=vc_list($lang); $pick=function(array $list,int $n){ $cloud=array_values(array_filter($list,fn($e)=>$e['available'] && $e['source']['type']==='cloud')); $any=array_values(array_filter($list,fn($e)=>$e['available']));
        $src=count($cloud)>=$n ? $cloud : $any; return array_slice($src,0,$n); };
    $o=array_merge($pick($L['f'] ?? [],2),$pick($L['m'] ?? [],1));
    return array_map(fn($e)=>['id'=>$e['id'],'label'=>$e['label'],'gender'=>$e['gender'],'desc'=>$e['desc'],'tier'=>$e['tier'],'server'=>$e['source']['type']==='cloud'],$o);
}
/** Begrüßungsvorschläge in der Sprache des Agenten (Name des Betriebs, Persona optional). */
function qs_greetings(string $company, string $persona, string $lang): array {
    $co=$company!=='' ? $company : '…';
    $T=['de'=>['Guten Tag, {c}, hier ist {p}, die digitale Assistenz. Wie kann ich Ihnen helfen?','Hallo und willkommen bei {c}! Was kann ich für Sie tun?','{c}, guten Tag. Möchten Sie einen Termin, haben Sie eine Frage – oder soll ich etwas notieren?'],
        'tr'=>['Merhaba, {c}\'ya hoş geldiniz, ben dijital asistan {p}. Size nasıl yardımcı olabilirim?','{c}, iyi günler! Size nasıl yardımcı olabilirim?','Merhaba, {c}. Randevu mu almak istersiniz, bir sorunuz mu var?'],
        'en'=>['Hello, thanks for calling {c}, this is {p}, the digital assistant. How can I help?','Hi and welcome to {c}! What can I do for you?','{c}, good day. Would you like to book, ask a question – or shall I take a message?'],
        'fr'=>['Bonjour, {c}, ici {p}, l\'assistant numérique. Comment puis-je vous aider ?','Bonjour et bienvenue chez {c} ! Que puis-je faire pour vous ?','{c}, bonjour. Souhaitez-vous un rendez-vous ou avez-vous une question ?'],
        'es'=>['Hola, {c}, le atiende {p}, el asistente digital. ¿En qué puedo ayudarle?','¡Hola y bienvenido a {c}! ¿Qué puedo hacer por usted?','{c}, buenos días. ¿Quiere una cita o tiene alguna pregunta?'],
        'az'=>['Salam, {c}-a xoş gəlmisiniz, mən rəqəmsal köməkçi {p}. Sizə necə kömək edə bilərəm?','{c}, xoş gördük! Sizə necə kömək edə bilərəm?','Salam, {c}. Görüş təyin etmək istəyirsiniz, yoxsa sualınız var?']];
    $p=$persona!=='' ? $persona : ($lang==='tr' ? 'Elif' : 'Lea');
    $list=$T[$lang] ?? [t('demo.greet',$lang,['persona'=>$p,'company'=>$co]),t('demo.greet0',$lang,['company'=>$co])];
    return array_values(array_unique(array_map(fn($x)=>strtr($x,['{c}'=>$co,'{p}'=>$p]),$list)));
}

/* ======================================================================= Im Browser mit dem eigenen Agenten sprechen: Sprachausgabe */
function qs_speak_sig(int $aid, string $text): string { return substr(hash_hmac('sha256','qs|'.$aid.'|'.$text,function_exists('ch_tts_key') ? ch_tts_key() : 'qs'),0,32); }
/** Antwort des Agenten mit seiner Stimme (Server-Stimme) – nur für Texte, die der Server signiert hat; null = Browserstimme. */
function qs_speak(int $aid, array $onb, string $text, string $sig): ?string {
    if(!hash_equals(qs_speak_sig($aid,$text),$sig) || !function_exists('ch_tts_synth') || mb_strlen($text)>CH_TTS_MAXLEN) return null;
    if(!th_rate('qs:tts:'.$aid,120,86400)) return null;
    $vid=function_exists('vc_agent_get') ? (string)(vc_agent_get($aid,$onb['base'])['voice_id'] ?? '') : '';
    $cfg=th_agent_cfg($aid,$onb['base']); $lang=ch_lang_norm((string)($cfg['lang'] ?? 'de')) ?: 'de';
    $voice=ch_tts_voice_for(['gender'=>str_ends_with((string)$cfg['phone_voice'],'-m') ? 'm' : 'f'],null,$lang);
    if($vid!=='' && function_exists('vc_apply_tts')) $voice=vc_apply_tts($voice,$vid);
    $voice['channel']='studio'; $voice['lang_auto']=false;
    try{ return ch_tts_synth($text,$voice); }catch(Throwable $e){ return null; }
}

/* ======================================================================= Erinnerungen (offene Einrichtung) */
/** Heartbeat: offene Einrichtungen nach 1 Stunde und nach 24 Stunden erinnern (je einmal, nie nach „live“). */
function qs_reminders_lazy(): int {
    if(!th_lock('qs_reminders',0)) return 0; $n=0;
    try{
        $f=sys_get_temp_dir().'/ch_qs_rem.ts'; if((int)@file_get_contents($f)>time()-900) return 0; @file_put_contents($f,(string)time());
        onb_bootstrap(); $p=th_db();
        $s=$p->query("SELECT * FROM onboardings WHERE status='open' AND created_at < (NOW() - INTERVAL 1 HOUR) AND created_at > (NOW() - INTERVAL 8 DAY) ORDER BY id DESC LIMIT 40");
        foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r){ $o=onb_decode($r); $m=(array)($o['meta']['rem'] ?? []); $age=time()-strtotime((string)$o['created_at']);
            $stage=$age>86400 ? 'd1' : 'h1'; if(!empty($m[$stage])) continue;   // nach einem Tag nur noch die zweite Erinnerung (keine zwei Mails kurz hintereinander)
            if(qs_send_reminder((int)$o['account_id'],$o,$stage)){ $o['meta']['rem'][$stage]=gmdate('c'); if($stage==='d1') $o['meta']['rem']['h1']=$o['meta']['rem']['h1'] ?? gmdate('c'); onb_save($o,['meta']); $n++; } }
    } finally { th_unlock('qs_reminders'); }
    return $n;
}
function qs_send_reminder(int $aid, array $onb, string $stage): bool {
    $to=onb_email($aid); if($to==='') return false;   // Testkonten (*.invalid) sortiert th_mail selbst aus
    $P=onb_profile($onb['plan']);
    $url=ONB_START_URL; $h=fn($x)=>htmlspecialchars((string)$x,ENT_QUOTES,'UTF-8');
    $subj=$stage==='h1' ? 'Dein '.$P['base_name'].' ist fast fertig – noch 2 Minuten' : 'Weitermachen, wo du aufgehört hast: '.$P['base_name'];
    $html='<div style="font-family:Inter,Arial,sans-serif;max-width:560px;margin:auto;color:#111"><h2 style="color:#b0801f">'.$h($subj).'</h2>'
        .'<p>Deine Angaben sind gespeichert – du machst genau dort weiter, wo du aufgehört hast. In wenigen Minuten nimmt dein Agent Anrufe und Nachrichten an.</p>'
        .'<p><a href="'.$h($url).'" style="display:inline-block;background:#111;color:#fff;padding:12px 18px;border-radius:12px;text-decoration:none;font-weight:600">Einrichtung fortsetzen →</a></p>'
        .'<p style="color:#666;font-size:13px">Fragen? Einfach auf diese E-Mail antworten – wir helfen gern.</p></div>';
    return function_exists('onb_mail_send') ? onb_mail_send($to,$subj,$html,strip_tags(str_replace(['<br>','</p>'],"\n",$html))) : th_mail($to,$subj,$html);
}

/* ======================================================================= Studio-REST */
/** Schnellstart-Zustand je Einrichtung: Schritt, Stimme, Begrüßung, Nummer, Anrufe seit der Nummer, letzte Gespräche (für „live“). */
function qs_state(int $aid, array $onb): array {
    $m=(array)($onb['meta']['qs'] ?? []); $base=$onb['base']; $cfg=th_agent_cfg($aid,$base); $lang=ch_lang_norm((string)($cfg['lang'] ?? 'de')) ?: 'de';
    $voice=function_exists('vc_agent_get') ? vc_agent_get($aid,$base) : ['voice_id'=>'','gender'=>'f'];
    $num=(string)($cfg['phone_number'] ?? ''); $numAt=null;
    if($num!==''){ $r=th_db()->prepare("SELECT created_at FROM phone_numbers WHERE account_id=? AND base=? AND status='active' ORDER BY id DESC LIMIT 1"); $r->execute([$aid,$base]); $numAt=$r->fetchColumn() ?: null; }
    $calls=0; if($num!==''){ $c=th_db()->prepare("SELECT COUNT(*) FROM phone_calls WHERE account_id=? AND started_at >= ?"); $c->execute([$aid,$numAt ?: (string)($onb['created_at'] ?? '2000-01-01')]); $calls=(int)$c->fetchColumn(); }
    $recent=[]; if(function_exists('dash_feed')){ try{ foreach(dash_feed($aid,7) as $it){ if(!in_array($it['kind'],['call','whatsapp','web','order','appt','ride','hotel','callback'],true)) continue; unset($it['hay']); $recent[]=$it; if(count($recent)>=5) break; } }catch(Throwable $e){} }
    $active=th_plan_active($aid,$base); $P=onb_profile($onb['plan']); $email=onb_email($aid);
    return ['step'=>(string)($m['step'] ?? 'link'),'started'=>isset($m['step']),'status'=>(string)$onb['status'],'sector'=>(string)($m['sector'] ?? ''),'draft_at'=>$m['draft_at'] ?? null,
        'company'=>(string)($onb['answers']['company'] ?? ($cfg['company'] ?? '')),'phone'=>in_array('phone',(array)($P['channels'] ?? []),true),
        'lang'=>$lang,'voice_id'=>(string)($voice['voice_id'] ?? ''),'gender'=>(string)($voice['gender'] ?? 'f'),'voices'=>qs_voices($lang),'greeting'=>(string)($cfg['phone_greeting'] ?? ''),
        'greetings'=>qs_greetings((string)($onb['answers']['company'] ?? ($cfg['company'] ?? '')),'',$lang),'active'=>$active,'trial'=>onb_is_trial($onb),
        'number'=>$num!=='' ? ['e164'=>$num,'display'=>onb_num_display($num),'codes'=>onb_forward_codes($num)] : null,'number_pending'=>(array)($onb['meta']['num_pending'] ?? []),
        'numbers_ready'=>function_exists('num_ready') ? num_ready() : false,'country'=>onb_country($aid),'calls'=>$calls,'live'=>$calls>0,'recent'=>$recent,
        /* Kasse mit der Konto-E-Mail vorbelegt: so landet das Paket sicher auf diesem Konto (und die Einrichtung bleibt erhalten) */
        'checkout'=>onb_is_trial($onb) ? '/chat/tokens-checkout.php?plan='.rawurlencode($onb['plan']).($email!=='' ? '&email='.rawurlencode($email) : '') : null,'widget_key'=>(string)($cfg['widget_key'] ?? '')];
}
function qs_set(array &$onb, array $kv): void { $onb['meta']['qs']=array_merge((array)($onb['meta']['qs'] ?? []),$kv); onb_save($onb,['meta']); }
function qs_api(string $route, string $method, int $aid, array $body, array $account): ?array {
    $mine=['onboarding/import'=>'POST','onboarding/draft'=>'POST','onboarding/sector'=>'POST','onboarding/qs'=>'*','onboarding/greeting'=>'POST','onboarding/speak'=>'POST','onboarding/event'=>'POST','onboarding/number'=>'POST','onboarding/funnel'=>'GET'];
    if(!isset($mine[$route])) return null;
    if($mine[$route]!=='*' && $mine[$route]!==$method) return [405,['error'=>'method_not_allowed']];
    if($aid<=0) return [403,['error'=>'demo_readonly','message'=>'In der Demo gibt es keine Einrichtung.']];
    $in=$body+$_GET; $sc=fn($v)=>is_scalar($v) ? trim((string)$v) : '';
    try{
        onb_bootstrap(); qs_bootstrap();
        if($route==='onboarding/funnel'){ if(!function_exists('bl_is_admin') || !bl_is_admin($aid)) return [403,['error'=>'forbidden']]; $d=(int)($in['days'] ?? 30); return [200,['days'=>$d,'funnel'=>qs_funnel(in_array($d,[7,30,90],true) ? $d : 30)]]; }
        if($route==='onboarding/sector'){
            if(!th_rate('qs:sector:'.$aid,20,3600)) return [429,['error'=>'rate_limited','message'=>'Bitte kurz warten.']];
            [$c,$r]=qs_sector_start($aid,preg_replace('/[^a-z0-9_-]/','',strtolower($sc($in['sector'] ?? ''))),['company'=>$sc($in['company'] ?? ''),'address'=>$sc($in['address'] ?? ''),'phone'=>$sc($in['phone'] ?? ''),'hours'=>$sc($in['hours'] ?? '')],(int)($in['id'] ?? 0));
            if($c===200){ $onb=onb_get($aid,(int)$r['onboarding']['id']); if($onb){ qs_set($onb,['step'=>'voice']); qs_event($aid,$onb['id'],'sector','done'); $r['qs']=qs_state($aid,$onb); } }
            return [$c,$r];
        }
        $id=(int)($in['id'] ?? 0); $onb=$id>0 ? onb_get($aid,$id) : null;
        if(!$onb && $route==='onboarding/qs' && $method==='GET'){ [$c,$o]=onb_resolve($aid,0,preg_replace('/[^a-z0-9-]/','',strtolower($sc($in['plan'] ?? '')))); if($c!==200) return [$c,$o+['sectors'=>qs_sectors()]]; $onb=$o; }
        if(!$onb) return [404,['error'=>'not_found','message'=>'Diese Einrichtung gibt es nicht.']];
        switch($route){
            case 'onboarding/qs':
                if($method==='GET') return [200,['onboarding'=>onb_public($onb),'qs'=>qs_state($aid,$onb),'sectors'=>qs_sectors()]];
                $step=$sc($in['step'] ?? ''); if(!in_array($step,QS_STEPS,true)) return [400,['error'=>'step','message'=>'Unbekannter Schritt.']];
                qs_set($onb,['step'=>$step]); return [200,['ok'=>true,'qs'=>qs_state($aid,$onb)]];
            case 'onboarding/event':
                $ok=qs_event($aid,$onb['id'],$sc($in['step'] ?? ''),$sc($in['ev'] ?? '')); return [$ok ? 200 : 400,['ok'=>$ok]];
            case 'onboarding/import':
                if(!th_rate('qs:import:'.$aid,12,3600)) return [429,['error'=>'rate_limited','message'=>'Zu viele Importe – bitte kurz warten.']];
                $src=qs_source($sc($in['url'] ?? '')); if(empty($src['ok'])) return [422,['error'=>'import_failed','message'=>(string)$src['message']]];
                [$P]=onb_ctx($aid,$onb); $d=qs_extract($src,$P,ch_lang_norm((string)($in['lang'] ?? '')) ?: 'de');
                qs_set($onb,['step'=>'draft','source'=>['kind'=>$src['kind'],'url'=>$src['url']]]); qs_event($aid,$onb['id'],'link','done');
                return [200,['ok'=>true,'kind'=>$src['kind'],'url'=>$src['url'],'thin'=>!empty($src['thin']),'draft'=>$d,'qs'=>qs_state($aid,$onb)]];
            case 'onboarding/draft':
                $draft=is_array($in['draft'] ?? null) ? $in['draft'] : []; if(!$draft) return [400,['error'=>'draft_required']];
                $r=qs_apply($aid,$onb,$draft); $onb=onb_get($aid,$onb['id']) ?? $onb; qs_set($onb,['step'=>'voice']); qs_event($aid,$onb['id'],'draft','done');
                return [200,['ok'=>$r['ok'],'saved'=>$r['saved'],'errors'=>$r['errors'],'qs'=>qs_state($aid,$onb)]];
            case 'onboarding/greeting':
                $text=mb_substr($sc($in['text'] ?? ''),0,400); $vid=preg_replace('/[^a-z0-9_-]/','',$sc($in['voice_id'] ?? ''));
                $steps=array_column(onb_catalog(onb_ctx($aid,$onb)[0]),'id');
                if($vid!=='' && function_exists('vc_agent_set')){ $r=vc_agent_set($aid,$onb['base'],$vid); if(isset($r['error'])) return [422,$r];
                    if(in_array('phone_persona',$steps,true)){ onb_answer($aid,$onb,'phone_persona',(string)($r['gender'] ?? 'f')); $onb=onb_get($aid,$onb['id']) ?? $onb; } }
                if($text!==''){ $cur=th_agent_cfg($aid,$onb['base']); if(empty($cur['id'])) th_agent_cfg_save($aid,$onb['base'],[],th_plan_active($aid,$onb['base']));
                    th_db()->prepare("UPDATE agent_configs SET phone_greeting=?, updated_at=NOW() WHERE account_id=? AND plan=?")->execute([$text,$aid,$onb['base']]);
                    if(in_array('phone_greeting',$steps,true)){ onb_answer($aid,$onb,'phone_greeting',$text); $onb=onb_get($aid,$onb['id']) ?? $onb; } }
                $onb=onb_get($aid,$onb['id']) ?? $onb; qs_set($onb,['step'=>'try']); qs_event($aid,$onb['id'],'voice','done');
                return [200,['ok'=>true,'qs'=>qs_state($aid,$onb)]];
            case 'onboarding/speak':
                $text=mb_substr($sc($in['text'] ?? ''),0,1200); $sig=$sc($in['t'] ?? '');
                if($sig===''){ /* Vorschau der Begrüßung: signierter Text der eigenen Begrüßung ist immer erlaubt */ $sig=qs_speak_sig($aid,$text); if(mb_strlen($text)>400) return [400,['error'=>'text']]; }
                $mp3=qs_speak($aid,$onb,$text,$sig); if($mp3===null) return [501,['error'=>'tts_unavailable','message'=>'Gerade keine Server-Stimme – der Browser liest vor.']];
                if(PHP_SAPI==='cli') return [200,['audio'=>true,'bytes'=>strlen($mp3)]];
                if(function_exists('th_nocache')) th_nocache(); http_response_code(200); header('Content-Type: audio/mpeg'); header('Content-Length: '.strlen($mp3)); echo $mp3; exit;
            case 'onboarding/number':
                $area=preg_replace('/\D/','',$sc($in['area'] ?? '')); $cc=strtoupper(preg_replace('/[^a-zA-Z]/','',$sc($in['cc'] ?? ''))) ?: onb_country($aid);
                if(!function_exists('num_buy') && is_file(__DIR__.'/number-lib.php')) require_once __DIR__.'/number-lib.php';
                if(!isset(NUM_CC[$cc])) return [422,['error'=>'country','message'=>'In diesem Land vergeben wir noch keine Nummern.']];
                $active=th_plan_active($aid,$onb['base']);
                if($active && function_exists('num_ready') && num_ready()){ $r=num_buy($aid,$onb['base'],'',$area,$cc); if(isset($r['error'])) return [422,$r]; unset($onb['meta']['num_pending']); $onb['meta']['number']=$r['number']; onb_save($onb,['meta']); }
                else { $onb['meta']['num_pending']=['area'=>$area,'cc'=>$cc,'at'=>gmdate('c')]; onb_save($onb,['meta']); }
                $onb=onb_get($aid,$onb['id']) ?? $onb; qs_set($onb,['step'=>'number']); qs_event($aid,$onb['id'],'number','done');
                return [200,['ok'=>true,'qs'=>qs_state($aid,$onb)]];
        }
    }catch(Throwable $e){ error_log('CH qs_api '.$route.': '.$e->getMessage().' @'.$e->getLine()); return [500,['error'=>'server_error','message'=>'Interner Fehler – bitte erneut versuchen.']]; }
    return [404,['error'=>'not_found']];
}
