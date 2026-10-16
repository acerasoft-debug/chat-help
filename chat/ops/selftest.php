<?php
/**
 * ChatHelp ops — selftest: kompletter Kundenablauf mit einem TEMPORÄREN Testkonto (…@selftest.invalid), danach restlos gelöscht.
 * =====================================================================================================================
 * Prüft über die echten öffentlichen URLs (https://chat-help.com, über Cloudflare):
 *   • Edge-Cache-Probe: zwei Konten, identische URL → darf keine fremden Daten liefern
 *   • Studio-API Bestellannahme: Einstellungen, Karte (Optionen-Kurzschreibweise), Zonen, Fahrer, Adressprüfung, Bestellung, Mindestbestellwert
 *   • Küchen-Board (Token), Status-Workflow, Fahrerseite (frische URL), „Zugestellt“, Kunden-Statusseite, Storno durch Kunden
 *   • Studio-API Termine: Einstellungen, Leistungen, Slots, Buchung, Doppelbuchung, Liste, Buchungsseite, Verwaltungsseite, Absage
 *   • Agent aktiv (Testplan): Studio-Chat mit Werkzeugen, Website-Widget, WhatsApp (Twilio-Format + Meta-Format, Verify, Doppelzustellung),
 *     Telefon-Webhook (Twilio-Simulation inkl. Redirect-Abholung und Signatur, falls TWILIO_AUTH_TOKEN gesetzt)
 *   • Twilio-Doku vom Server geladen: gültige Stimmen (Polly Generative/Neural, Google Chirp3-HD) + Gather-Hinweise
 * args: {"ai":"0"} KI-Schritte aus · {"keep":"1"} Testdaten behalten · {"docs":"0"} Twilio-Doku nicht laden
 */
header('Content-Type: text/plain; charset=UTF-8');
@set_time_limit(1200);
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';
require_once __DIR__.'/appt-lib.php';
require_once __DIR__.'/order-lib.php';
th_bootstrap(); appt_bootstrap(); ord_bootstrap();
$BASE='https://chat-help.com'; $AI=($_GET['ai'] ?? '1')!=='0'; $KEEP=($_GET['keep'] ?? '0')==='1'; $DOCS=($_GET['docs'] ?? '1')!=='0';
$PASS=0; $FAIL=0; $FAILS=[]; $T0=microtime(true);
echo "ChatHelp — selftest  (".date('c').")  KI: ".($AI?'an':'aus')."\n";

function ok($c, string $label, string $d=''): bool { global $PASS,$FAIL,$FAILS; $c=(bool)$c; if($c) $PASS++; else { $FAIL++; $FAILS[]=$label; } echo ($c?'  ✓ ':'  ✗ ').$label.($d!==''?'  — '.$d:'')."\n"; return $c; }
function sec(string $t): void { echo "\n=== $t ===\n"; }
function short($s, int $n=220): string { return mb_substr(trim(preg_replace('/\s+/',' ',is_string($s)?$s:(string)json_encode($s,JSON_UNESCAPED_UNICODE))),0,$n); }
function hdrs(string $h, string $re='/^(cache-control|set-cookie|cf-cache-status|age|x-cache[\w-]*|x-proxy-cache|vary|expires|last-modified|server|x-powered-by|location):/i'): string {
    $out=[]; foreach(preg_split('/\r?\n/',$h) as $l){ if(preg_match($re,$l)) $out[]=preg_replace('/(set-cookie:\s*[^=]+=)[^;]{12,}/i','$1…',trim($l)); } return implode(' | ',$out);
}
function http(string $method, string $url, $body=null, array $hdr=[], string $kind='json', bool $follow=false): array {
    $ch=curl_init($url); $h=$hdr;
    $opt=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>150,CURLOPT_HEADER=>true,CURLOPT_USERAGENT=>'ChatHelp-selftest',CURLOPT_FOLLOWLOCATION=>$follow,CURLOPT_MAXREDIRS=>4];
    if($method!=='GET') $opt[CURLOPT_CUSTOMREQUEST]=$method;
    if($body!==null){ if($kind==='form'){ $opt[CURLOPT_POSTFIELDS]=http_build_query($body); $h[]='Content-Type: application/x-www-form-urlencoded'; } else { $opt[CURLOPT_POSTFIELDS]=is_string($body)?$body:json_encode($body,JSON_UNESCAPED_UNICODE); $h[]='Content-Type: application/json'; } }
    $opt[CURLOPT_HTTPHEADER]=$h; curl_setopt_array($ch,$opt);
    $t=microtime(true); $raw=(string)curl_exec($ch); $dt=microtime(true)-$t;
    $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $hs=(int)curl_getinfo($ch,CURLINFO_HEADER_SIZE); $err=curl_error($ch); $eff=(string)curl_getinfo($ch,CURLINFO_EFFECTIVE_URL); curl_close($ch);
    $hd=substr($raw,0,$hs); $b=substr($raw,$hs);
    return ['code'=>$code,'body'=>$b,'json'=>json_decode($b,true),'hdr'=>$hd,'t'=>$dt,'err'=>$err,'url'=>$eff,'cache'=>preg_match_all('/^cf-cache-status:\s*(\S+)/mi',$hd,$m)?end($m[1]):'-'];
}
function rnd(int $n=5): string { return bin2hex(random_bytes($n)); }

/* ---------- Testkonten + Aufräumen ---------- */
$ACCS=[];
$cleanup=function() use(&$ACCS,$KEEP){
    if(!$ACCS) return;
    if($KEEP){ echo "\n(keep=1: Testdaten bleiben: Konten ".implode(',',$ACCS).")\n"; return; }
    $p=th_db(); $n=0;
    foreach($ACCS as $aid){
        foreach(['orders','menu_items','drivers','order_settings','wa_sessions','appt_bookings','appt_services','appt_settings','appt_blocks','phone_calls','agent_configs','api_keys','token_ledger','api_usage','token_plans'] as $t){
            try{ $s=$p->prepare("DELETE FROM $t WHERE account_id=?"); $s->execute([$aid]); $n+=$s->rowCount(); }catch(Throwable $e){ echo "  cleanup $t: ".$e->getMessage()."\n"; }
        }
        $p->prepare("DELETE FROM token_accounts WHERE id=?")->execute([$aid]); $n++;
    }
    echo "\nAufgeräumt: ".count($ACCS)." Testkonten, $n Datensätze gelöscht.\n"; $ACCS=[];
};
register_shutdown_function(function() use($cleanup){ $cleanup(); });

$A=th_account('selftest-'.rnd(4).'@selftest.invalid'); $aid=(int)$A['id']; $ACCS[]=$aid;
th_credit($aid,3000,'selftest','st:'.$aid.':'.rnd(3)); $key=th_issue_key($aid,'selftest');
$api=function(string $m, string $path, $body=null) use($BASE,$key){ $u="$BASE/chat/tokens-api.php/v1/$path".(str_contains($path,'?')?'&':'?').'_='.rnd(); return http($m,$u,$body,['Authorization: Bearer '.$key]); };

try {
sec('Testkonto');
ok($aid>0 && th_balance($aid)===3000,'Testkonto mit 3.000 Tokens + API-Key',"id=$aid");

/* ---------- Edge-Cache-Probe ---------- */
sec('Edge-Cache-Probe');
$B=th_account('selftest-b-'.rnd(4).'@selftest.invalid'); $aidB=(int)$B['id']; $ACCS[]=$aidB; $keyB=th_issue_key($aidB,'selftest');
$probe="$BASE/chat/tokens-api.php/v1/me?probe=".rnd(6);
$r1=http('GET',$probe,null,['Authorization: Bearer '.$key]); $r2=http('GET',$probe,null,['Authorization: Bearer '.$keyB]); $r3=http('GET',$probe,null,['Authorization: Bearer '.$key]);
echo "  A: {$r1['code']} cf={$r1['cache']} email=".($r1['json']['email'] ?? '?')."\n  B: {$r2['code']} cf={$r2['cache']} email=".($r2['json']['email'] ?? '?')."\n  A: {$r3['code']} cf={$r3['cache']}\n";
echo "  Header B: ".hdrs($r2['hdr'])."\n";
ok(($r2['json']['email'] ?? '')===$B['email'] && ($r1['json']['email'] ?? '')===$A['email'],'Identische URL, zwei Konten: jedes sieht nur eigene Daten');
$pub="$BASE/chat/order-api.php?a=demo_menu&probe=".rnd(6); $p1=http('GET',$pub); usleep(800000); $p2=http('GET',$pub);
echo "  öffentliches JSON, 2× gleiche URL: cf {$p1['cache']} → {$p2['cache']}\n  Header 1: ".hdrs($p1['hdr'])."\n  Header 2: ".hdrs($p2['hdr'])."\n";
$h1=http('GET',"$BASE/chat/book.php?k=demo&probe=".rnd(4)); echo "  book.php Header: ".hdrs($h1['hdr'])."\n";
echo "  Laufzeit-Umgebung: PHP ".PHP_VERSION.", fastcgi_finish_request ".(function_exists('fastcgi_finish_request')?'vorhanden':'—')." (CLI-Sicht; Web siehe Telefon-Test)\n";

/* ---------- Studio-API: Bestellannahme ---------- */
sec('Studio-API: Bestellannahme');
$r=$api('GET','order/settings'); ok($r['code']===200 && isset($r['json']['settings']),'GET /v1/order/settings','order_on='.($r['json']['settings']['order_on'] ?? '?'));
$allDay=[]; for($w=0;$w<=6;$w++) $allDay[(string)$w]=[['00:00','23:59']];
$payload=['order_on'=>1,'name'=>'Selftest Pizzeria','address'=>'Friesenstraße 50, 50670 Köln','phone'=>'0221 000000','notify_email'=>'','tz'=>'Europe/Berlin','delivery_on'=>1,'pickup_on'=>1,
  'min_order_cents'=>1500,'delivery_fee_cents'=>250,'eta_delivery_min'=>35,'eta_pickup_min'=>15,'payment_methods'=>'bar,karte','hours'=>$allDay,
  'zones'=>[['name'=>'Innenstadt','plz'=>'50667,50670,50672','fee_cents'=>0,'min_cents'=>1500],['name'=>'Südstadt','plz'=>'50677,50678','fee_cents'=>250,'min_cents'=>2000]],
  'menu'=>[['category'=>'Pizza','name'=>'Margherita','description'=>'Tomate, Mozzarella','price_cents'=>850,'options'=>'Größe: Normal 0 | Groß +3,00','tags'=>'vegetarisch'],
           ['category'=>'Pizza','name'=>'Salami','description'=>'Tomate, Mozzarella, Salami','price_cents'=>990,'options'=>'Größe: Normal 0 | Groß +3,00; Extra: Käse +1,50','tags'=>''],
           ['category'=>'Getränke','name'=>'Cola 0,33 l','description'=>'','price_cents'=>250,'options'=>'','tags'=>'']],
  'drivers'=>[['name'=>'Test-Fahrer','phone'=>'']],'wa_provider'=>'meta','wa_verify'=>'selftest-verify','wa_token'=>'EAAGselftestGEHEIM'.rnd(6)];
$r=$api('POST','order/settings',$payload); $j=$r['json'] ?? [];
ok($r['code']===200 && (int)($j['settings']['order_on'] ?? 0)===1,'POST /v1/order/settings (Restaurant, Zeiten, Zonen, Karte, Fahrer)','Karte='.count($j['menu'] ?? []).' Fahrer='.count($j['drivers'] ?? []).' Geo='.(($j['settings']['lat'] ?? null)!==null?'✓':'—').($r['code']!==200?' · '.short($r['body']):''));
$sal=null; foreach($j['menu'] ?? [] as $m) if($m['name']==='Salami') $sal=$m;
ok($sal && count($sal['options'])===2 && (int)$sal['options'][0]['choices'][1]['delta']===300 && (int)$sal['options'][1]['choices'][0]['delta']===150,'Optionen-Kurzschreibweise geparst',short($sal['options'] ?? ''));
$links=$j['links'] ?? []; $drv=$j['drivers'][0] ?? null;
ok(!empty($links['board']) && !empty($links['whatsapp_webhook']) && $drv && !empty($drv['url']),'Links: Küchen-Board, WhatsApp-Webhook, Fahrer-Link');
$cfgKey=preg_match('/key=(wk_[a-f0-9]{36})/',(string)($links['whatsapp_webhook'] ?? ''),$mm)?$mm[1]:'';
$boardT=preg_match('/t=([a-f0-9]{24})/',(string)($links['board'] ?? ''),$mm)?$mm[1]:'';
$drvT=preg_match('/t=([a-f0-9]{24})/',(string)($drv['url'] ?? ''),$mm)?$mm[1]:'';
ok(($j['settings']['wa_token'] ?? '')==='••••••••' && !str_contains($r['body'],'GEHEIM'),'WhatsApp-Zugangstoken wird nie im Klartext ausgegeben');
$r=$api('GET','order/check?address='.rawurlencode('Severinstraße 12, 50678 Köln'));
ok(($r['json']['ok'] ?? false)===true && ($r['json']['zone'] ?? '')==='Südstadt','Adressprüfung: Zone + Gebühr + Entfernung','Zone='.($r['json']['zone'] ?? '-').' Gebühr='.($r['json']['fee_cents'] ?? '-').' km='.($r['json']['distance_km'] ?? '-'));
$r=$api('GET','order/check?address='.rawurlencode('Marienplatz 1, 80331 München'));
ok(($r['json']['ok'] ?? true)===false,'Adresse außerhalb des Liefergebiets abgelehnt','reason='.($r['json']['reason'] ?? '-'));
$r=$api('POST','order/create',['type'=>'delivery','items'=>[['name'=>'Cola','qty'=>1]],'name'=>'Mini','phone'=>'+49 170 0','address'=>'Severinstraße 12, 50678 Köln']);
ok(($r['json']['error'] ?? '')==='below_minimum','Mindestbestellwert je Zone durchgesetzt');
$r=$api('POST','order/create',['type'=>'delivery','items'=>[['name'=>'Salami','qty'=>2,'options'=>['Groß']],['name'=>'Cola','qty'=>1]],'name'=>'Selftest Kunde','phone'=>'+49 170 0000000','address'=>'Severinstraße 12, 50678 Köln','payment'=>'bar','note'=>'Klingel: Test']);
$o=$r['json']['order'] ?? null;
ok($r['code']===200 && $o && (int)$o['total_cents']===2*1290+250+250,'Bestellung: 2× Salami groß + Cola, Lieferung Südstadt = 30,80 €',$o?($o['code'].' · '.$o['total'].' · ETA '.$o['eta'].' · '.($o['distance_km'] ?? '-').' km'):short($r['body']));
if($o){
    $r=$api('GET','order/orders'); ok(in_array($o['code'],array_column($r['json']['orders'] ?? [],'code'),true),'Studio: Live-Bestellliste enthält die Bestellung');
    $r=http('GET',"$BASE/chat/order-api.php?a=board&t=$boardT&_=".rnd()); ok(in_array($o['code'],array_column($r['json']['orders'] ?? [],'code'),true),'Küchen-Board (Token) zeigt die Bestellung');
    $r=http('POST',"$BASE/chat/order-api.php?a=status&t=$boardT",['id'=>$o['id'],'status'=>'confirmed']); ok(($r['json']['order']['status'] ?? '')==='confirmed','Board: „Bestätigen“');
    $r=http('GET',"$BASE/chat/board.php?t=$boardT&_=".rnd()); ok($r['code']===200 && str_contains($r['body'],'Selftest Pizzeria'),'board.php (Tablet-Ansicht) lädt');
    $r=$api('POST','order/status',['id'=>$o['id'],'status'=>'preparing']); ok(($r['json']['order']['status'] ?? '')==='preparing','Studio: „In Zubereitung“');
    $r=$api('POST','order/status',['id'=>$o['id'],'status'=>'ready']); ok(($r['json']['order']['status'] ?? '')==='ready','Studio: „Fertig“');
    $r=$api('POST','order/status',['id'=>$o['id'],'status'=>'out_for_delivery','driver_id'=>$drv['id'] ?? null]); ok(($r['json']['order']['driver']['name'] ?? '')==='Test-Fahrer','„Unterwegs“ mit zugewiesenem Fahrer');
    $r=http('GET',"$BASE/chat/driver.php?t=$drvT",null,[],'json',true); ok($r['code']===200 && str_contains($r['body'],$o['code']),'Fahrerseite zeigt die Fahrt (frische URL)',preg_replace('/t=[a-f0-9]+/','t=…',$r['url']));
    $r=http('GET',"$BASE/chat/order.php?c={$o['code']}",null,[],'json',true); ok($r['code']===200 && str_contains($r['body'],'Unterwegs') && str_contains($r['body'],'Test-Fahrer'),'Kunden-Statusseite: „Unterwegs · mit Test-Fahrer“');
    $r=http('POST',"$BASE/chat/driver.php?t=$drvT&_=".rnd(),['done'=>$o['id']],[],'form'); ok(in_array($r['code'],[302,303],true) && (ord_by_code($o['code'])['status'] ?? '')==='delivered','Fahrer meldet „Zugestellt“');
    $r=http('GET',"$BASE/chat/order.php?c={$o['code']}",null,[],'json',true); ok(str_contains($r['body'],'guten Appetit'),'Statusseite: zugestellt');
}
$r=$api('POST','order/create',['type'=>'pickup','items'=>[['name'=>'Margherita','qty'=>1]],'name'=>'Abholer','phone'=>'+49 170 1111111','payment'=>'bar']); $o2=$r['json']['order'] ?? null;
ok($o2 && (int)$o2['fee_cents']===0 && (int)$o2['total_cents']===850,'Abholbestellung ohne Liefergebühr',$o2['code'] ?? short($r['body']));
if($o2){ $r=http('POST',"$BASE/chat/order.php?c={$o2['code']}",['do'=>'cancel'],[],'form',true); ok((ord_by_code($o2['code'])['status'] ?? '')==='cancelled' && str_contains($r['body'],'storniert'),'Kunde storniert über die Statusseite'); }

/* ---------- Studio-API: Termine ---------- */
sec('Studio-API: Terminbuchung');
$hrs=[]; for($w=0;$w<=6;$w++) $hrs[(string)$w]=[['08:00','20:00']];
$r=$api('POST','appt/settings',['appt_on'=>1,'company'=>'Selftest Praxis','address'=>'Teststraße 1, 50667 Köln','tz'=>'Europe/Berlin','slot_min'=>30,'buffer_min'=>0,'lead_min'=>60,'max_days'=>30,'hours'=>$hrs,
  'services'=>[['name'=>'Beratung','duration_min'=>30,'price'=>'50'],['name'=>'Behandlung','duration_min'=>60,'price'=>'90,00']]]);
$svc=$r['json']['services'] ?? []; $bookUrl=(string)($r['json']['booking_url'] ?? '');
ok($r['code']===200 && count($svc)===2 && (int)($svc[1]['price_cents'] ?? 0)===9000 && (int)($r['json']['settings']['appt_on'] ?? 0)===1,'Termine: Einstellungen + Leistungen',short($svc,160));
ok(str_contains($bookUrl,'book.php?k=wk_'),'Eigene Buchungsseite',preg_replace('/k=wk_\w+/','k=wk_…',$bookUrl));
$tomorrow=(new DateTimeImmutable('tomorrow',new DateTimeZone('Europe/Berlin')))->format('Y-m-d');
$r=$api('GET',"appt/slots?date=$tomorrow&service=".($svc[0]['id'] ?? 0)); $slots=$r['json']['slots'] ?? []; ok(count($slots)>=20,'Freie Slots morgen','n='.count($slots));
$bk=null;
if($slots){
    $r=$api('POST','appt/book',['service_id'=>$svc[0]['id'],'start'=>"$tomorrow ".$slots[0],'name'=>'Selftest Patient','phone'=>'+49 170 2222222','email'=>'patient@selftest.invalid','note'=>'Test']); $bk=$r['json']['booking'] ?? null;
    ok($bk && ($bk['status'] ?? '')==='booked','Termin buchen',$bk ? $bk['code'].' · '.$bk['human'] : short($r['body']));
    $r=$api('POST','appt/book',['service_id'=>$svc[0]['id'],'start'=>"$tomorrow ".$slots[0],'name'=>'Doppelt','phone'=>'+49 170 3']); ok(($r['json']['error'] ?? '')==='slot_unavailable','Doppelbuchung verhindert');
}
if($bk){
    $r=$api('GET',"appt/bookings?from=$tomorrow&to=$tomorrow"); ok(in_array($bk['code'],array_column($r['json']['bookings'] ?? [],'code'),true),'Terminliste im Studio');
    $k=preg_match('/k=(wk_[a-f0-9]{36})/',$bookUrl,$mm)?$mm[1]:'';
    $r=http('GET',"$BASE/chat/appt-api.php?k=$k&a=info&_=".rnd()); ok(($r['json']['company'] ?? '')==='Selftest Praxis','Buchungsseiten-API (öffentlich) liefert den Betrieb');
    $r=http('GET',"$BASE/chat/appt.php?c={$bk['code']}",null,[],'json',true); ok($r['code']===200 && str_contains($r['body'],'Selftest Praxis'),'Termin-Verwaltungsseite für Kunden');
    $r=$api('POST','appt/cancel',['code'=>$bk['code']]); ok(($r['json']['booking']['status'] ?? '')==='cancelled','Absage im Studio');
}

/* ---------- Agent aktivieren + Kanäle ---------- */
sec('Agent aktiv (Testplan agent-bestellung) · Kanäle');
th_plan_upsert($aid,'agent-bestellung','','active',date('Y-m-d H:i:s',time()+86400));
$r=$api('POST','agent/config?plan=agent-bestellung',['company'=>'Selftest Pizzeria','tone'=>'herzlich-schnell','lang'=>'de','kb'=>"Selftest Pizzeria, Friesenstraße 50, 50670 Köln. Holzofen-Pizza. Abholung 10 % Rabatt. Allergene: Weizen in allen Pizzen.",'widget_on'=>1,'phone_on'=>1,'phone_voice'=>'de-DE','phone_greeting'=>'','phone_forward'=>'']);
$c=$r['json']['config'] ?? [];
ok((int)($c['widget_on'] ?? 0)===1 && (int)($c['phone_on'] ?? 0)===1 && ($c['widget_key'] ?? '')===$cfgKey,'Widget + Telefon aktiv, gleicher Schlüssel wie WhatsApp');
$r=http('GET',"$BASE/chat/whatsapp-webhook.php?key=$cfgKey&hub.mode=subscribe&hub.verify_token=selftest-verify&hub.challenge=CH".($ch=rnd(4)));
ok(trim($r['body'])==='CH'.$ch,'WhatsApp (Meta): Webhook-Verifizierung mit Verify-Token');
$r=http('GET',"$BASE/chat/whatsapp-webhook.php?key=$cfgKey&hub.mode=subscribe&hub.verify_token=falsch&hub.challenge=".rnd(3)); ok($r['code']===403,'Meta: falscher Verify-Token → 403');

if($AI){
    $r=$api('POST','agent',['plan'=>'agent-bestellung','messages'=>[['role'=>'user','content'=>'Eine große Margherita zum Abholen bitte. Name Studio-Test, Telefon 0170 3333333, bar. Ich bestätige hiermit – bitte direkt aufgeben.']],'lang'=>'de']);
    $tools=array_map(fn($t)=>$t['name'].($t['ok']?'✓':'✗'),$r['json']['tools'] ?? []);
    ok($r['code']===200 && in_array('create_order✓',$tools,true),'Studio-Chat: Agent bestellt über Werkzeuge',implode(', ',$tools).' · '.($r['json']['tokens_used'] ?? '?').' Tokens');
    echo "    „".short($r['json']['result'] ?? $r['body'],320)."“\n";

    $r=http('POST',"$BASE/chat/widget-api.php",['key'=>$cfgKey,'messages'=>[['role'=>'user','content'=>'Was kostet eine große Salami und habt ihr gerade offen?']],'lang'=>'de']);
    ok($r['code']===200 && !empty($r['json']['reply']),'Website-Widget antwortet aus Karte/Werkzeugen',short($r['json']['reply'] ?? $r['body'],200));

    /* WhatsApp über Twilio (synchron, TwiML) – zwei Nachrichten, Gesprächsgedächtnis */
    $wa='491700000'.random_int(100,999); $sidA='SM'.rnd(16);
    $r=http('POST',"$BASE/chat/whatsapp-webhook.php?key=$cfgKey",['Body'=>'Hallo! Was kostet bei euch eine große Margherita?','From'=>"whatsapp:+$wa",'ProfileName'=>'WA Test','MessageSid'=>$sidA],[],'form');
    ok($r['code']===200 && str_contains($r['body'],'<Message>'),'WhatsApp (Twilio): Antwort als TwiML',sprintf('%.1fs · ',$r['t']).short(html_entity_decode(strip_tags($r['body'])),200));
    $r=http('POST',"$BASE/chat/whatsapp-webhook.php?key=$cfgKey",['Body'=>'Hallo! Was kostet bei euch eine große Margherita?','From'=>"whatsapp:+$wa",'ProfileName'=>'WA Test','MessageSid'=>$sidA],[],'form');
    ok(!str_contains($r['body'],'<Message>'),'WhatsApp: doppelt zugestellte Nachricht wird nicht erneut beantwortet');
    $r=http('POST',"$BASE/chat/whatsapp-webhook.php?key=$cfgKey",['Body'=>'Super, dann bitte eine große Margherita zum Abholen, Name WA Test, bar. Ja, genau so aufgeben.','From'=>"whatsapp:+$wa",'ProfileName'=>'WA Test','MessageSid'=>'SM'.rnd(16)],[],'form');
    $s=th_db()->prepare("SELECT code,total_cents FROM orders WHERE account_id=? AND channel='whatsapp' AND wa_from=?"); $s->execute([$aid,$wa]); $wo=$s->fetch(PDO::FETCH_ASSOC);
    ok((bool)$wo,'WhatsApp: Bestellung über 2 Nachrichten angelegt (Gedächtnis)',($wo?$wo['code'].' · '.number_format($wo['total_cents']/100,2,',','.').' € · ':'').sprintf('%.1fs · ',$r['t']).short(html_entity_decode(strip_tags($r['body'])),220));

    /* WhatsApp über Meta (Webhook sofort bestätigt, Antwort danach über Graph-API – ohne Zugangsdaten übersprungen) */
    $wm='491700001'.random_int(100,999);
    $meta=['object'=>'whatsapp_business_account','entry'=>[['id'=>'1','changes'=>[['field'=>'messages','value'=>['messaging_product'=>'whatsapp','contacts'=>[['wa_id'=>$wm,'profile'=>['name'=>'Meta Test']]],
          'messages'=>[['from'=>$wm,'id'=>'wamid.'.rnd(12),'timestamp'=>(string)time(),'type'=>'text','text'=>['body'=>'Hallo, bis wann kann man heute bestellen?']]]]]]]]];
    $r=http('POST',"$BASE/chat/whatsapp-webhook.php?key=$cfgKey",$meta);
    ok($r['code']===200,'WhatsApp (Meta): Webhook bestätigt',sprintf('%.2fs (sofortige Bestätigung = %s)',$r['t'],$r['t']<3?'ja':'nein, synchron'));
    $hist=[]; for($i=0;$i<40;$i++){ $hist=ord_wa_session($aid,$wm); if(count($hist)>=2) break; usleep(500000); }
    ok(count($hist)>=2,'WhatsApp (Meta): Antwort im Hintergrund erzeugt',short(end($hist)['content'] ?? '',200));

    /* Telefon: Twilio-Simulation inkl. Redirect-Abholung */
    sec('Telefon-Webhook (Twilio-Simulation)');
    $self="$BASE/chat/phone-webhook.php?key=$cfgKey";
    $sign=function(string $url, array $post): array { if(!(defined('TWILIO_AUTH_TOKEN') && TWILIO_AUTH_TOKEN)) return []; $d=$url; ksort($post); foreach($post as $k=>$v) $d.=$k.$v; return ['X-Twilio-Signature: '.base64_encode(hash_hmac('sha1',$d,TWILIO_AUTH_TOKEN,true))]; };
    $turn=function(array $post, ?string $speech=null) use($self,$sign): array {
        $url=$self; $p=$post; if($speech!==null){ $p['SpeechResult']=$speech; $p['Confidence']='0.93'; }
        $said=[]; $t=microtime(true); $hops=0; $twiml='';
        while($hops<8){ $r=http('POST',$url,$p,$sign($url,$p),'form'); $twiml=$r['body']; $hops++;
            preg_match_all('#<Say[^>]*>(.*?)</Say>#s',$twiml,$m); foreach($m[1] as $x) $said[]=trim(html_entity_decode(strip_tags($x)));
            if(preg_match('#<Redirect[^>]*>(.*?)</Redirect>#s',$twiml,$rm) && !str_contains($twiml,'<Gather') && !str_contains($twiml,'<Hangup')){ $url=html_entity_decode($rm[1]); $p=$post; continue; }
            break; }
        preg_match('/voice="([^"]+)"/',$twiml,$vm);
        return ['twiml'=>$twiml,'said'=>$said,'hops'=>$hops,'t'=>microtime(true)-$t,'voice'=>$vm[1] ?? '','code'=>$r['code'] ?? 0];
    };
    $sid='CA'.rnd(16); $base=['CallSid'=>$sid,'AccountSid'=>'AC'.rnd(16),'From'=>'+4917000'.random_int(10000,99999),'To'=>'+4922100000','CallStatus'=>'in-progress','Direction'=>'inbound'];
    $g=$turn($base); ok($g['code']===200 && str_contains($g['twiml'],'<Gather') && $g['said'],'Anruf: Begrüßung + Zuhören',"Stimme {$g['voice']} · „".short(implode(' ',$g['said']),160)."“");
    echo "    TwiML: ".short($g['twiml'],420)."\n";
    $t1=$turn($base,'Hallo, ich hätte gern zwei große Pizza Salami zum Abholen. Mein Name ist Anruf Test.');
    ok($t1['said'] && str_contains($t1['twiml'],'<Gather'),'Anruf: Antwort gesprochen, Agent hört weiter',sprintf('%.1fs, %d Abrufe · ',$t1['t'],$t1['hops'])."„".short(implode(' / ',$t1['said']),260)."“");
    $t2=$turn($base,'Ja genau, bar bezahlen. Bitte so aufgeben.');
    ok((bool)$t2['said'],'Anruf: zweite Antwort',sprintf('%.1fs, %d Abrufe · ',$t2['t'],$t2['hops'])."„".short(implode(' / ',$t2['said']),260)."“");
    $call=th_call($sid); $s=th_db()->prepare("SELECT code,total_cents FROM orders WHERE account_id=? AND channel='phone'"); $s->execute([$aid]); $po=$s->fetch(PDO::FETCH_ASSOC);
    ok($call && (int)$call['turns']>=2 && (int)$call['tokens']>0,'Anruf protokolliert (Transkript + Tokens)','Antworten='.($call['turns'] ?? 0).' Tokens='.($call['tokens'] ?? 0).($po?' · Bestellung '.$po['code'].' '.number_format($po['total_cents']/100,2,',','.').' €':' · (noch keine Bestellung)'));
    ok(empty($call['pending']),'Keine hängende Hintergrund-Antwort');
    http('POST',$self,$base+['CallStatus'=>'completed'],$sign($self,$base+['CallStatus'=>'completed']),'form'); ok((th_call($sid)['status'] ?? '')==='completed','Anrufende (Status-Callback)');
    /* männliche Stimme */
    th_db()->prepare("UPDATE agent_configs SET phone_voice='de-DE-m' WHERE account_id=? AND plan='agent-bestellung'")->execute([$aid]);
    $g2=$turn(['CallSid'=>'CA'.rnd(16)]+$base); ok($g2['voice']!=='' && $g2['voice']!==$g['voice'],'Stimmwahl männlich wirkt',$g2['voice']);
}
} catch(Throwable $e){ ok(false,'Unerwarteter Fehler: '.$e->getMessage(),basename($e->getFile()).':'.$e->getLine()); }

/* ---------- Twilio-Doku (vom Server geladen) ---------- */
if($DOCS){
    sec('Twilio: gültige Stimmen laut offizieller Doku');
    $doc=null; foreach(['https://www.twilio.com/docs/voice/twiml/say/text-speech.md','https://www.twilio.com/docs/voice/twiml/say/text-speech'] as $u){ $r=http('GET',$u,null,['Accept: text/markdown, text/plain, text/html'],'json',true); echo "  $u → {$r['code']} (".strlen($r['body'])." Bytes)\n"; if($r['code']===200 && strlen($r['body'])>15000){ $doc=$r['body']; break; } }
    if($doc){
        $lines=stripos($doc,'<tr')!==false ? array_map(fn($row)=>implode(' | ',array_map(fn($c)=>trim(html_entity_decode(strip_tags($c))),preg_split('#</t[dh]>#i',$row))),(preg_match_all('#<tr[^>]*>(.*?)</tr>#is',$doc,$rows)?$rows[1]:[])) : preg_split('/\R/',$doc);
        foreach(['de-DE','en-US','en-GB','tr-TR','fr-FR','es-ES','it-IT','nl-NL','pl-PL','ru-RU'] as $loc){
            $hits=array_values(array_filter($lines,fn($l)=>str_contains($l,$loc)));
            $pick=function(string $re,int $max) use($hits){ $o=[]; foreach($hits as $l){ if(preg_match($re,$l)){ $o[]=trim(preg_replace('/\s*\|\s*/',' | ',trim($l,'| '))); if(count($o)>=$max) break; } } return $o; };
            echo "  [$loc] Polly Generative: ".(implode(' ;; ',$pick('/Polly.*Generative|Generative.*Polly/i',6)) ?: '—')."\n";
            echo "  [$loc] Polly Neural: ".(implode(' ;; ',$pick('/Polly.*Neural|Neural.*Polly/i',6)) ?: '—')."\n";
            echo "  [$loc] Google Chirp3-HD: ".(implode(' ;; ',$pick('/Chirp3-HD/i',4)) ?: '—')."\n";
        }
        foreach(['Vicki-Generative','Daniel-Generative','Joanna-Generative','Matthew-Generative','Amy-Generative','Lea-Generative','Remi-Generative','Lucia-Generative','Sergio-Generative','Bianca-Generative','Adriano-Generative','de-DE-Chirp3-HD-Aoede','de-DE-Chirp3-HD-Charon','tr-TR-Chirp3-HD-Aoede'] as $v) echo "  enthält „$v“: ".(stripos($doc,$v)!==false?'ja':'nein')."\n";
        if(preg_match_all('/[a-z]{2}-[A-Z]{2}-Chirp3-HD-[A-Za-z]+/',$doc,$cm)){ $byLoc=[]; foreach(array_unique($cm[0]) as $v){ $byLoc[substr($v,0,5)][]=substr($v,16); } foreach(['de-DE','tr-TR','en-US','fr-FR'] as $l) if(isset($byLoc[$l])) echo "  Chirp3-HD $l: ".implode(', ',array_slice($byLoc[$l],0,30))."\n"; }
        foreach(preg_split('/\R/',strip_tags($doc)) as $l){ if(preg_match('/beta|not covered|SLA|13520|13512|invalid voice|SSML/i',$l) && mb_strlen(trim($l))>20){ echo "  Hinweis: ".short($l,260)."\n"; } }
    }
    sec('Twilio <Gather>: speechModel / speechTimeout laut Doku');
    $r=http('GET','https://www.twilio.com/docs/voice/twiml/gather.md',null,['Accept: text/markdown, text/plain'],'json',true);
    if($r['code']!==200 || strlen($r['body'])<5000) $r=http('GET','https://www.twilio.com/docs/voice/twiml/gather',null,[],'json',true);
    echo "  → {$r['code']} (".strlen($r['body'])." Bytes)\n"; $n=0;
    foreach(preg_split('/\R/',strip_tags($r['body'])) as $l){ if(preg_match('/experimental_conversations|phone_call|googlev2|deepgram|speechTimeout.*auto|auto.*speechTimeout|enhanced/i',$l) && mb_strlen(trim($l))>25){ echo "  · ".short($l,300)."\n"; if(++$n>=24) break; } }
}

$cleanup();
printf("\nERGEBNIS: %d bestanden, %d fehlgeschlagen  (%.0f s)\n",$PASS,$FAIL,microtime(true)-$T0);
if($FAILS) echo "Fehlgeschlagen:\n  - ".implode("\n  - ",$FAILS)."\n";
echo "BİTTİ\n";
