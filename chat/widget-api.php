<?php
/**
 * ChatHelp — Website-Widget Backend (herkese açık, widget anahtarı ile)
 * =====================================================================
 * POST /chat/widget-api.php  {key:"wk_…", messages:[{role,content}], lang}
 *  - widget_key → agent_configs (widget_on=1) → hesap + plan; plan aktif olmalı
 *  - İzinli alan adı tanımlıysa Origin/Referer eşleşmeli
 *  - Hesap başına dakikada 60 mesaj sınırı; tokenlar agent SAHİBİNİN defterinden düşer (widget_turn)
 *  → {reply, lang, chips:[{id,label,text}] (≤ 2 Folge-Vorschläge)} – wechselt die Gesprächssprache gegenüber `lang`, zusätzlich ui/human/start in der neuen Sprache.
 * GET  /chat/widget-api.php?key=wk_…[&lang=xx]  → widget için başlık/karşılama (JSON): greeting in der Sprache des Besuchers (?lang=, Accept-Language,
 *      CF-IPCountry – nur erlaubte Sprachen, sonst Standardsprache), greetings{code:text} für alle erlaubten Sprachen, langs[{code,name}],
 *      ui{…} (alle Oberflächentexte, i18n wg.*), chips[{id,label,text|url}] (3–4 Schnellwahl-Knöpfe nach aktiven Modulen),
 *      human{label,text,url?,phone?} („Mit einem Menschen sprechen“: Rückrufseite, wenn das Modul aktiv ist, sonst Nachricht an den Agenten).
 *      url ist relativ zu widget-api.php (z. B. callback.php?k=…), das Widget löst sie selbst auf.
 * POST: Antwortsprache je Nachricht erkannt (i18n.php ch_reply_lang): letzte Kundennachricht → frühere Kundennachrichten → lang des Widgets (Browser) → Standard.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';
header('Access-Control-Allow-Origin: *'); header('Access-Control-Allow-Headers: Content-Type'); header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){ http_response_code(204); exit; }
th_bootstrap(); ch_i18n_bootstrap();
require_once __DIR__.'/agent-tools.php';   // Werkzeuge + Modulliste (Schnellwahl im GET, Werkzeuge im POST)

/* ---------- Widget-Oberfläche: Schnellwahl, Mensch-Knopf, Texte ---------- */
/** Aktive Kundenmodule des Kontos (appt, order, ride, hotel, callback); Fehler eines Moduls → gilt als aus. */
function wg_modules(int $aid): array {
    $o=[]; foreach(ch_modules() as $k=>$m){ if(!in_array($k,['appt','order','ride','hotel','callback'],true)) continue; try{ if(($m['enabled'])($aid)) $o[]=$k; }catch(Throwable $e){} }
    return $o;
}
/** Rückrufseite des Betriebs (relativ zu widget-api.php) oder '' – nur wenn das Modul aktiv ist und die Seite öffentlich erreichbar wäre (wie callback.php). */
function wg_cb_url(int $aid, array $w, array $mods, string $l): string {
    if(!in_array('callback',$mods,true) || !function_exists('cb_public_ok')) return '';
    try{ if(!cb_public_ok($aid,(string)$w['plan'])) return ''; }catch(Throwable $e){ return ''; }
    return 'callback.php?'.http_build_query(['k'=>(string)$w['widget_key'],'lang'=>$l,'source'=>'Website-Chat']);
}
/** Öffentliche Telefonnummer aus den Einstellungen der aktiven Module (dieselbe, die auf den Kundenseiten steht) oder ''. */
function wg_phone(int $aid, array $mods): string {
    $src=['order'=>'ord_settings','ride'=>'ride_settings','hotel'=>'hotel_settings'];
    foreach($src as $m=>$fn){ if(!in_array($m,$mods,true) || !function_exists($fn)) continue;
        try{ $p=trim((string)preg_replace('/[^0-9+ ()\/-]/','',(string)($fn($aid)['phone'] ?? ''))); }catch(Throwable $e){ $p=''; }
        if(strlen(preg_replace('/\D/','',$p))>=6) return mb_substr($p,0,30); }
    return '';
}
/** 3–4 Schnellwahl-Knöpfe: Fachmodule zuerst, dann Öffnungszeiten & Preise, Rückruf (Seite), Speisekarte, Kontakt, Angebot. */
function wg_chips(array $mods, string $l, string $cbUrl): array {
    $c=fn(string $k)=>['id'=>$k,'label'=>t('wg.c.'.$k,$l),'text'=>t('wg.c.'.$k.'_t',$l)];
    $o=[]; foreach(['appt'=>'book','order'=>'order','ride'=>'taxi','hotel'=>'room'] as $m=>$k) if(in_array($m,$mods,true)) $o[]=$c($k);
    $o[]=$c('info');
    if($cbUrl!=='' && count($o)<4) $o[]=['id'=>'cb','label'=>t('wg.c.cb',$l),'url'=>$cbUrl];
    if(in_array('order',$mods,true) && count($o)<4) $o[]=$c('menu');
    if(count($o)<3) $o[]=$c('contact');
    if(count($o)<3) $o[]=$c('offer');
    return array_slice($o,0,4);
}
/** Folge-Vorschläge nach einer Antwort (≤ 2): Bitte um Bestätigung → „Ja, passt so“ / „Etwas ändern“; offene Frage oder gerade erledigte Buchung → keine;
 *  sonst noch nicht genutzte Schnellwahl-Knöpfe (ohne Seiten-Links), aufgefüllt mit „Kontakt“ / „Angebot“. */
function wg_followups(array $start, array $clean, string $answer, bool $confirm, bool $bound, string $l): array {
    if($confirm) return [['id'=>'yes','label'=>t('wg.c.yes',$l),'text'=>t('wg.c.yes_t',$l)],['id'=>'change','label'=>t('wg.c.change',$l),'text'=>t('wg.c.change_t',$l)]];
    if($bound || preg_match('/[?？]\s*$/u',$answer)) return [];
    $used=[]; foreach($clean as $m) if($m['role']==='user') $used[$m['content']]=1;
    $ids=array_column($start,'id'); foreach(['contact','offer'] as $k) if(!in_array($k,$ids,true)) $start[]=['id'=>$k,'label'=>t('wg.c.'.$k,$l),'text'=>t('wg.c.'.$k.'_t',$l)];
    $o=[]; foreach($start as $c){ if(isset($c['url']) || isset($used[$c['text'] ?? ''])) continue; $o[]=$c; if(count($o)>=2) break; }
    return $o;
}
/** Oberflächentexte des Widgets (wg.* ohne Knopf-Texte) + Name der Sprachauswahl. */
function wg_ui(string $l): array {
    $o=[]; foreach(ch_t_prefix('wg.',$l) as $k=>$v) if(!str_starts_with((string)$k,'c.')) $o[$k]=$v;
    return $o+['lang'=>t('lang',$l)];
}
function wg_human(string $cbUrl, string $phone, string $l): array {
    return ['label'=>t('wg.human',$l),'text'=>t('wg.human_t',$l),'url'=>$cbUrl!=='' ? $cbUrl : null,'url_label'=>t('wg.c.cb',$l),'phone'=>$phone!=='' ? $phone : null];
}

$in = json_decode(file_get_contents('php://input'),true); if(!is_array($in)) $in=[];
$key = (string)($in['key'] ?? ($_GET['key'] ?? ''));
$w = th_widget($key); if(!$w) th_json(403,['error'=>'widget_disabled','message'=>'Widget nicht aktiv oder Schlüssel ungültig.']);
$pl = ch_plan($w['plan']); if(!$pl) th_json(404,['error'=>'unknown_plan']);
$aid=(int)$w['account_id'];
if(!th_plan_active($aid,$pl['id'])) th_json(402,['error'=>'plan_inactive','message'=>t('ch.inactive',ch_page_lang(ch_cfg_langs($w),ch_cfg_default($w)))]);

/* Alan adı kontrolü (isteğe bağlı) */
if(!empty($w['widget_domain'])){
    $allowed = array_filter(array_map('trim',preg_split('/[\s,]+/',strtolower($w['widget_domain']))));
    $origin = strtolower(parse_url($_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? ''),PHP_URL_HOST) ?? '');
    $ok=false; foreach($allowed as $d){ $d=preg_replace('#^https?://#','',$d); $d=rtrim($d,'/'); if($origin===$d || ($d!=='' && str_ends_with($origin,'.'.$d))) $ok=true; }
    if(!$ok) th_json(403,['error'=>'domain_not_allowed','origin'=>$origin]);
}

$allowed=ch_cfg_langs($w); $defLang=ch_cfg_default($w);
$company = $w['company'] ?: t('ch.team',$defLang);
if($_SERVER['REQUEST_METHOD']==='GET'){
    $vl=ch_page_lang($allowed,$defLang); $gr=[]; foreach($allowed as $l) $gr[$l]=t('ch.greet',$l,['company'=>$w['company'] ?: t('ch.team',$l)]);
    $mods=wg_modules($aid); $cbUrl=wg_cb_url($aid,$w,$mods,$vl);
    th_json(200,['ok'=>true,'title'=>$company,'agent'=>$pl['name'],'lang'=>$vl,'default_lang'=>$defLang,'greeting'=>$gr[$vl] ?? $gr[$defLang],'greetings'=>$gr,
      'langs'=>array_map(fn($l)=>['code'=>$l,'name'=>CH_LANGS[$l]['native'],'tag'=>CH_LANGS[$l]['tag']],$allowed),
      'ui'=>wg_ui($vl),'chips'=>wg_chips($mods,$vl,$cbUrl),'human'=>wg_human($cbUrl,wg_phone($aid,$mods),$vl)]);
}

/* Mengenlimits (öffentlicher Schlüssel → Schutz des Token-Guthabens): je Besucher-IP 30 Nachrichten / 10 Min, je Konto 60 / Min und 1.500 / Tag */
$hintLang=ch_lang_norm(is_string($in['lang'] ?? null) ? $in['lang'] : '');
if(!th_rate('w:'.$aid.':'.th_client_ip(),30,600) || !th_rate('wa:'.$aid,60,60) || !th_rate('wd:'.$aid,1500,86400)) th_json(429,['error'=>'rate_limited','message'=>t('ch.wait',$hintLang ?: $defLang)]);

$msgs=is_array($in['messages']??null)?array_slice($in['messages'],-12):[];
$clean=[]; foreach($msgs as $m){ $role=($m['role']??'user')==='assistant'?'assistant':'user'; $t=trim((string)($m['content']??'')); if($t!=='') $clean[]=['role'=>$role,'content'=>mb_substr($t,0,3000)]; }
if(!$clean || end($clean)['role']!=='user') th_json(400,['error'=>'messages_required']);

/* Gesprächssprache: frühere Kundennachrichten (sicher erkannt) → Browser-Sprache des Widgets → Standard; dann die letzte Nachricht */
$prevLang=''; foreach(array_slice($clean,0,-1) as $m){ if($m['role']!=='user') continue; $ps=false; $pd=ch_detect_lang($m['content'],'',$ps); if($ps && in_array($pd,$allowed,true)) $prevLang=$pd; }
$replyLang=ch_reply_lang($w,end($clean)['content'],$prevLang,$hintLang);
$cost=CH_PRICING['widget_turn'];
if(th_balance($aid) < $cost) th_json(402,['error'=>'owner_out_of_tokens','message'=>t('ch.na',$replyLang)]);

/* KI çağrısı (api.php ile aynı mantık; bağımsız tutuldu) */
$system = ch_agent_system($pl,$w,$replyLang,true)."\n\nDu sprichst mit einem Website-Besucher (nicht mit dem Betreiber). Kurz, hilfreich, max. 6 Sätze. Wenn du etwas nicht weißt: ehrlich sagen und Kontaktaufnahme anbieten."
  ." Pro Nachricht nur ein Schritt bzw. eine Frage, kurze einfache Sätze. Vor jeder verbindlichen Buchung, Bestellung oder Anfrage alle Angaben kurz zusammenfassen und ausdrücklich bestätigen lassen; endet deine Nachricht mit dieser Bitte um Bestätigung, schreib ganz am Ende genau [CONFIRM]."
  ." Möchte der Besucher mit einem Menschen sprechen, nenne freundlich die Kontaktmöglichkeiten aus der Wissensbasis bzw. biete einen Rückruf an.";
$t0=microtime(true);
ch_heartbeat();
$tk=ch_toolkit($aid,'widget');
if($tk['tools']){ $system.=$tk['hint']; $answer=th_ai_tools($system,$clean,$tk['tools'],$tk['exec'],900,75); $cost+=2*count(th_ai_info()['tool_calls'] ?? []); }
else $answer = th_ai($system, $clean, 700, 60);   // Claude model zinciri → DeepSeek (tokens-lib)
if($answer===null) th_json(503,['error'=>'ai_unavailable']);
$cost=th_charge($aid,$cost,'widget:'.$pl['id']);
th_log_usage($aid,'widget',$pl['sector'],$cost,(int)((microtime(true)-$t0)*1000));

/* Folge-Vorschläge: Marker [CONFIRM] → Bestätigungs-Knöpfe (Marker wird entfernt); verbindliche Aktion gerade erledigt → keine */
$confirm=(bool)preg_match('/\[\s*(CONFIRM|BEST(Ä|AE)TIGEN)\s*\]/iu',$answer);
$answer=trim((string)preg_replace('/[ \t]*\[\s*(CONFIRM|BEST(Ä|AE)TIGEN)\s*\][ \t]*/iu',' ',$answer));
$bound=false; foreach((array)(th_ai_info()['tool_calls'] ?? []) as $tc) if(!empty($tc['ok']) && preg_match('/^(book_|create_|request_|order_|cancel_)/',(string)($tc['name'] ?? ''))) $bound=true;
$mods=wg_modules($aid); $cbUrl=wg_cb_url($aid,$w,$mods,$replyLang); $start=wg_chips($mods,$replyLang,$cbUrl);
$out=['reply'=>$answer,'lang'=>$replyLang,'chips'=>wg_followups($start,$clean,$answer,$confirm,$bound,$replyLang)];
if($replyLang!==$hintLang) $out+=['ui'=>wg_ui($replyLang),'human'=>wg_human($cbUrl,wg_phone($aid,$mods),$replyLang),'start'=>$start];   // Besucher schreibt in einer anderen Sprache → Oberfläche kann folgen
th_json(200,$out);
