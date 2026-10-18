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
if(is_file(__DIR__.'/agent-tools.php')) require_once __DIR__.'/agent-tools.php';
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
    if($method==='HEAD') $opt[CURLOPT_NOBODY]=true; elseif($method!=='GET') $opt[CURLOPT_CUSTOMREQUEST]=$method;
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
        foreach(['orders','menu_items','drivers','order_settings','wa_sessions','appt_bookings','appt_services','appt_settings','appt_blocks','phone_calls','agent_configs','api_keys','token_ledger','api_usage','token_plans','acct_secrets','rides','taxi_drivers','ride_settings','hotel_bookings','hotel_rooms','hotel_requests','hotel_settings','callbacks','callback_settings'] as $t){
            try{ $s=$p->prepare("DELETE FROM $t WHERE account_id=?"); $s->execute([$aid]); $n+=$s->rowCount(); }catch(Throwable $e){ echo "  cleanup $t: ".$e->getMessage()."\n"; }
        }
        /* alle übrigen Tabellen mit account_id (neue Module: Rückruf, Training, Assistent, Push …) */
        try{ foreach($p->query("SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='account_id' AND TABLE_NAME<>'token_accounts'")->fetchAll(PDO::FETCH_COLUMN) as $t){ $s=$p->prepare("DELETE FROM `$t` WHERE account_id=?"); $s->execute([$aid]); $n+=$s->rowCount(); } }catch(Throwable $e){ echo "  cleanup (generisch): ".$e->getMessage()."\n"; }
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
$payload=['order_on'=>1,'name'=>'Selftest Pizzeria','address'=>'Friesenstraße 50, 50670 Köln','phone'=>'0221 000000','notify_email'=>'','tz'=>'Europe/Berlin','delivery_on'=>1,'pickup_on'=>1,'pickup_discount_pct'=>10,
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
    $r=http('GET',"$BASE/chat/board.php?t=$boardT&_=".rnd(),null,[],'json',true); ok($r['code']===200 && str_contains($r['body'],'Selftest Pizzeria'),'board.php (Tablet-Ansicht) lädt',"HTTP {$r['code']} (versionierte URL)");
    $r=$api('POST','order/status',['id'=>$o['id'],'status'=>'preparing']); ok(($r['json']['order']['status'] ?? '')==='preparing','Studio: „In Zubereitung“');
    $r=$api('POST','order/status',['id'=>$o['id'],'status'=>'ready']); ok(($r['json']['order']['status'] ?? '')==='ready','Studio: „Fertig“');
    $r=$api('POST','order/status',['id'=>$o['id'],'status'=>'out_for_delivery','driver_id'=>$drv['id'] ?? null]); ok(($r['json']['order']['driver']['name'] ?? '')==='Test-Fahrer','„Unterwegs“ mit zugewiesenem Fahrer');
    $r=http('GET',"$BASE/chat/driver.php?t=$drvT",null,[],'json',true); ok($r['code']===200 && str_contains($r['body'],$o['code']),'Fahrerseite zeigt die Fahrt (frische URL)',preg_replace('/t=[a-f0-9]+/','t=…',$r['url']));
    $r=http('GET',"$BASE/chat/order.php?c={$o['code']}",null,['Accept-Language: de-DE,de;q=0.9'],'json',true); ok($r['code']===200 && str_contains($r['body'],'Unterwegs') && str_contains($r['body'],'Test-Fahrer'),'Kunden-Statusseite: „Unterwegs · mit Test-Fahrer“');
    $r=http('POST',"$BASE/chat/driver.php?t=$drvT&_=".rnd(),['done'=>$o['id']],[],'form'); ok(in_array($r['code'],[302,303],true) && (ord_by_code($o['code'])['status'] ?? '')==='delivered','Fahrer meldet „Zugestellt“');
    $r=http('GET',"$BASE/chat/order.php?c={$o['code']}",null,['Accept-Language: de-DE,de;q=0.9'],'json',true); ok(str_contains($r['body'],'guten Appetit'),'Statusseite: zugestellt',"HTTP {$r['code']} · ".(preg_match('#<h1[^>]*>(.*?)</h1>#s',$r['body'],$hm)?short(strip_tags($hm[1]),80):'kein h1')." · Status in DB: ".(ord_by_code($o['code'])['status'] ?? '?')." · cf=".$r['cache']);
}
$r=$api('POST','order/create',['type'=>'pickup','items'=>[['name'=>'Margherita','qty'=>1]],'name'=>'Abholer','phone'=>'+49 170 1111111','payment'=>'bar']); $o2=$r['json']['order'] ?? null;
ok($o2 && (int)$o2['fee_cents']===0 && (int)$o2['discount_cents']===85 && (int)$o2['total_cents']===765,'Abholbestellung: keine Liefergebühr, 10 % Abholrabatt verbucht (8,50 → 7,65 €)',$o2 ? $o2['code'].' · '.$o2['total'] : short($r['body']));
if($o2){ $r=http('POST',"$BASE/chat/order.php?c={$o2['code']}",['do'=>'cancel'],['Accept-Language: de-DE,de;q=0.9'],'form',true); ok((ord_by_code($o2['code'])['status'] ?? '')==='cancelled' && str_contains($r['body'],'storniert'),'Kunde storniert über die Statusseite'); }

/* Kartensuche: „Pizza Salami“ muss die Salami finden; Unsinn liefert die ganze Karte statt „gibt es nicht“ */
require_once __DIR__.'/order-lib.php';
$m1=ord_exec($aid,'get_menu',['query'=>'Pizza Salami'],'selftest'); ok(count($m1['items'] ?? [])===1 && ($m1['items'][0]['name'] ?? '')==='Salami','Kartensuche „Pizza Salami“ findet die Salami');
$m2=ord_exec($aid,'get_menu',['category'=>'Pizzen'],'selftest'); ok(count($m2['items'] ?? [])===2,'Kategorie „Pizzen“ findet beide Pizzen');
$m3=ord_exec($aid,'get_menu',['query'=>'Sushi'],'selftest'); ok(count($m3['items'] ?? [])===3 && !empty($m3['note']),'Unbekannter Artikel: komplette Karte + Hinweis');

/* Seitenversionen: Studio/Buchungsseite/Board liefern immer den aktuellen Stand (302 → ?v=…) */
$r=http('GET',"$BASE/chat/studio.php",null,[],'json',true); ok($r['code']===200 && str_contains($r['body'],'pBestellungen') && str_contains($r['body'],'Eigene Nummer mit Twilio verbinden') && str_contains($r['url'],'v='),'Studio: aktuelle Version (Bestellungen-Tab + Anleitungen)',preg_replace('/^https:\/\/chat-help\.com/','',$r['url']).' · cf='.$r['cache']);

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

    /* Verbindungen: eigener Twilio-Auth-Token (nur Token, ohne SID → Antworten weiter als TwiML) – ab jetzt Signaturpflicht für Telefon + WhatsApp */
    $twTok=rnd(16); $r=$api('POST','connections',['twilio_token'=>$twTok]);
    ok($r['code']===200 && ($r['json']['connections']['twilio_token'] ?? '')==='••••'.substr($twTok,-4) && !str_contains($r['body'],$twTok),'Studio-Verbindungen: Auth-Token gespeichert, nur „••••“ zurück');
    $r=$api('POST','connections',['twilio_sid'=>'XYZ']); ok($r['code']===422,'Verbindungen: ungültige SID abgelehnt');
    $twSign=function(string $url, array $post) use($twTok): array { $d=$url; ksort($post); foreach($post as $k=>$v) $d.=$k.$v; return ['X-Twilio-Signature: '.base64_encode(hash_hmac('sha1',$d,$twTok,true))]; };
    th_db()->prepare("UPDATE order_settings SET wa_provider='twilio', wa_number='' WHERE account_id=?")->execute([$aid]);
    $waUrl="$BASE/chat/whatsapp-webhook.php?key=$cfgKey";
    $waPost=function(array $p) use($waUrl,$twSign){ return http('POST',$waUrl,$p,$twSign($waUrl,$p),'form'); };
    $r=http('POST',$waUrl,['Body'=>'Hallo','From'=>'whatsapp:+491700009999','MessageSid'=>'SM'.rnd(16)],[],'form');
    ok($r['code']===403,'WhatsApp (Twilio): ohne gültige Signatur → 403');
    /* WhatsApp über Twilio (synchron, TwiML) – zwei Nachrichten, Gesprächsgedächtnis */
    $wa='491700000'.random_int(100,999); $sidA='SM'.rnd(16);
    $r=$waPost(['Body'=>'Hallo! Was kostet bei euch eine große Margherita?','From'=>"whatsapp:+$wa",'ProfileName'=>'WA Test','MessageSid'=>$sidA]);
    ok($r['code']===200 && str_contains($r['body'],'<Message>'),'WhatsApp (Twilio): Antwort als TwiML',sprintf('%.1fs · ',$r['t']).short(html_entity_decode(strip_tags($r['body'])),200));
    $r=$waPost(['Body'=>'Hallo! Was kostet bei euch eine große Margherita?','From'=>"whatsapp:+$wa",'ProfileName'=>'WA Test','MessageSid'=>$sidA]);
    ok(!str_contains($r['body'],'<Message>'),'WhatsApp: doppelt zugestellte Nachricht wird nicht erneut beantwortet');
    $r=$waPost(['Body'=>'Super, dann bitte eine große Margherita zum Abholen, Name WA Test, bar. Ja, genau so aufgeben.','From'=>"whatsapp:+$wa",'ProfileName'=>'WA Test','MessageSid'=>'SM'.rnd(16)]);
    $s=th_db()->prepare("SELECT code,total_cents,discount_cents FROM orders WHERE account_id=? AND channel='whatsapp' AND wa_from=?"); $s->execute([$aid,$wa]); $wo=$s->fetch(PDO::FETCH_ASSOC);
    if(!$wo){ $r=$waPost(['Body'=>'Ja, bitte so aufgeben.','From'=>"whatsapp:+$wa",'ProfileName'=>'WA Test','MessageSid'=>'SM'.rnd(16)]); $s->execute([$aid,$wa]); $wo=$s->fetch(PDO::FETCH_ASSOC); }
    ok((bool)$wo,'WhatsApp: Bestellung im Gesprächsverlauf angelegt (Gedächtnis)',($wo?$wo['code'].' · '.number_format($wo['total_cents']/100,2,',','.').' € · ':'').sprintf('%.1fs · ',$r['t']).short(html_entity_decode(strip_tags($r['body'])),220));

    /* Datenschutz: Status zu einer FREMDEN Nummer darf nicht herauskommen (Werkzeug direkt geprüft) */
    if(function_exists('ch_toolkit') && $wo){ $tkw=ch_toolkit($aid,'widget',''); $x=($tkw['exec'])('get_order_status',['phone'=>'+'.$wa]);
        ok(empty($x['orders']) && ($x['error'] ?? '')==='code_required','Datenschutz: Website-Chat bekommt zu fremder Nummer keine Bestellung',json_encode($x,JSON_UNESCAPED_UNICODE));
        $tkw=ch_toolkit($aid,'whatsapp',$wa); $x=($tkw['exec'])('get_order_status',['phone'=>'+491709999999']);
        ok(!empty($x['orders']) && ($x['orders'][0]['code'] ?? '')===$wo['code'],'Datenschutz: WhatsApp-Absender sieht nur eigene Bestellung (fremde Nummer ignoriert)'); }
    /* WhatsApp über Meta (Webhook sofort bestätigt, Antwort danach über Graph-API – ohne Zugangsdaten übersprungen) */
    $wm='491700001'.random_int(100,999);
    $meta=['object'=>'whatsapp_business_account','entry'=>[['id'=>'1','changes'=>[['field'=>'messages','value'=>['messaging_product'=>'whatsapp','contacts'=>[['wa_id'=>$wm,'profile'=>['name'=>'Meta Test']]],
          'messages'=>[['from'=>$wm,'id'=>'wamid.'.rnd(12),'timestamp'=>(string)time(),'type'=>'text','text'=>['body'=>'Hallo, bis wann kann man heute bestellen?']]]]]]]]];
    th_db()->prepare("UPDATE order_settings SET wa_provider='meta' WHERE account_id=?")->execute([$aid]);
    $mSec=rnd(16); $r=$api('POST','connections',['meta_app_secret'=>$mSec]); ok($r['code']===200,'Verbindungen: Meta App-Secret gespeichert');
    $rawMeta=json_encode($meta,JSON_UNESCAPED_UNICODE);
    $r=http('POST',"$BASE/chat/whatsapp-webhook.php?key=$cfgKey",$rawMeta,['X-Hub-Signature-256: sha256='.hash_hmac('sha256',$rawMeta,'falsch')]);
    ok($r['code']===403,'WhatsApp (Meta): falsche Signatur → 403');
    $r=http('POST',"$BASE/chat/whatsapp-webhook.php?key=$cfgKey",$rawMeta,['X-Hub-Signature-256: sha256='.hash_hmac('sha256',$rawMeta,$mSec)]);
    ok($r['code']===200,'WhatsApp (Meta): Webhook bestätigt',sprintf('%.2fs (sofortige Bestätigung = %s)',$r['t'],$r['t']<3?'ja':'nein, synchron'));
    $hist=[]; for($i=0;$i<40;$i++){ $hist=ord_wa_session($aid,$wm); if(count($hist)>=2) break; usleep(500000); }
    ok(count($hist)>=2,'WhatsApp (Meta): Antwort im Hintergrund erzeugt',short(end($hist)['content'] ?? '',200));

    /* Telefon: Twilio-Simulation inkl. Redirect-Abholung */
    sec('Telefon-Webhook (Twilio-Simulation)');
    $self="$BASE/chat/phone-webhook.php?key=$cfgKey";
    $sign=$twSign;   // Konto-Auth-Token aus den Verbindungen
    $r=http('POST',$self,['CallSid'=>'CA'.rnd(16),'From'=>'+491700001111','To'=>'+4922100000','CallStatus'=>'ringing','Direction'=>'inbound'],[],'form');
    ok($r['code']===403,'Telefon: Anfrage ohne gültige Twilio-Signatur → 403');
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
    $du=$self.'&dial=1'; $dp=array_merge($base,['DialCallStatus'=>'completed']); $r=http('POST',$du,$dp,$sign($du,$dp),'form');
    ok(str_contains($r['body'],'<Hangup') && !str_contains($r['body'],'<Say'),'Weiterleitung beendet (Dial-Action): kein „niemand frei“ nach erfolgreichem Gespräch');
    $dp=array_merge($base,['DialCallStatus'=>'no-answer']); $r=http('POST',$du,$dp,$sign($du,$dp),'form');
    ok(str_contains($r['body'],'<Say') && str_contains($r['body'],'<Hangup'),'Weiterleitung nicht angenommen → Rückruf-Zusage');
    $end=array_merge($base,['CallStatus'=>'completed']); http('POST',$self,$end,$sign($self,$end),'form'); ok((th_call($sid)['status'] ?? '')==='completed','Anrufende (Status-Callback)');
    /* männliche Stimme */
    th_db()->prepare("UPDATE agent_configs SET phone_voice='de-DE-m' WHERE account_id=? AND plan='agent-bestellung'")->execute([$aid]);
    $g2=$turn(['CallSid'=>'CA'.rnd(16)]+$base); ok($g2['voice']!=='' && $g2['voice']!==$g['voice'],'Stimmwahl männlich wirkt',$g2['voice']);
    th_db()->prepare("UPDATE agent_configs SET phone_voice='tr-TR' WHERE account_id=? AND plan='agent-bestellung'")->execute([$aid]);
    $g3=$turn(['CallSid'=>'CA'.rnd(16)]+$base); ok(str_contains(implode(' ',$g3['said']),'asistan') && str_contains($g3['twiml'],'language="tr-TR"'),'Türkische Stimme: Begrüßung auf Türkisch',$g3['voice'].' · „'.short(implode(' ',$g3['said']),120).'“');
}
} catch(Throwable $e){ ok(false,'Unerwarteter Fehler: '.$e->getMessage(),basename($e->getFile()).':'.$e->getLine()); }

/* ---------- Taxi-Zentrale ---------- */
if(function_exists('ride_api')) try {
    sec('Taxi-Zentrale: Studio-API, Preis, Buchung, Disposition, Seiten');
    $dem=RIDE_DEMO; $r=$api('POST','ride/settings',['ride_on'=>1,'name'=>'Selftest Taxi Köln','phone'=>'0221 1234567','address'=>$dem['address'],'tz'=>'Europe/Berlin','area_km'=>25,
        'tariff'=>$dem['tariff'],'vehicles'=>$dem['vehicles'],'fixed_routes'=>$dem['fixed_routes'],'stands'=>$dem['stands'],'payment_methods'=>'bar,karte',
        'drivers'=>[['name'=>'Selftest Fahrer','phone'=>'+491700000101','vehicle'=>'Mercedes E-Klasse, schwarz','plate'=>'K-ST 100','seats'=>4,'stand'=>'Hauptbahnhof']]]);
    $rs=$r['json'] ?? []; $drv=$rs['drivers'][0] ?? null; $board=$rs['links']['board'] ?? ''; $drvUrl=$rs['links']['drivers'][0]['url'] ?? '';
    ok($r['code']===200 && (int)($rs['settings']['ride_on'] ?? 0)===1 && $drv && $board && $drvUrl,'Taxi-Einstellungen gespeichert (Tarif, Stände, Fahrer mit Link)',count($rs['settings']['stands'] ?? []).' Stände');
    $r=$api('GET','ride/estimate?pickup='.rawurlencode('Trankgasse 11, 50667 Köln').'&destination='.rawurlencode('Neumarkt 1, 50667 Köln').'&time=sofort');
    ok($r['code']===200 && str_contains(json_encode($r['json'],JSON_UNESCAPED_UNICODE),'€'),'Preisrechner: Richtpreis Hbf → Neumarkt',short($r['json']['price_text'] ?? ($r['json']['price'] ?? $r['body']),120));
    $r=$api('POST','ride/create',['name'=>'Selftest Fahrgast','phone'=>'0170 5555555','pickup'=>'Trankgasse 11, 50667 Köln','destination'=>'Neumarkt 1, 50667 Köln','time'=>'sofort','passengers'=>2]);
    $ride=$r['json']['ride'] ?? null; $rc=$ride['code'] ?? '';
    ok($r['code']===200 && $rc!=='','Fahrt manuell angelegt',$rc.' · '.short($ride['price_text'] ?? '',60));
    $r=$api('GET','ride/rides?scope=open'); $rid=0; foreach(($r['json']['rides'] ?? []) as $x) if(($x['code'] ?? '')===$rc) $rid=(int)$x['id'];
    ok($rid>0,'Fahrt in der Live-Liste der Zentrale');
    $r=$api('POST','ride/status',['id'=>$rid,'status'=>'assigned','driver_id'=>(int)$drv['id']]); ok($r['code']===200,'Fahrer zugeteilt',short($r['json']['ride']['status_label'] ?? $r['body'],80));
    $r=http('GET',"$BASE/chat/ride.php?c=$rc",null,[],'json',true); ok($r['code']===200 && str_contains($r['body'],$rc) && str_contains($r['body'],'K-ST 100'),'Kunden-Statusseite zeigt Fahrer + Kennzeichen');
    $r=http('GET',$board,null,[],'json',true); ok($r['code']===200 && str_contains($r['body'],'Selftest Taxi'),'Dispositions-Board erreichbar');
    $bt=preg_match('/t=([a-f0-9]{24})/',$board,$mm)?$mm[1]:''; $r=http('GET',"$BASE/chat/ride-api.php?a=board&t=$bt&_=".rnd());
    ok($r['code']===200 && str_contains($r['body'],$rc),'Board-API liefert die Fahrt');
    $r=http('GET',$drvUrl,null,[],'json',true); ok($r['code']===200 && str_contains($r['body'],$rc),'Fahrer-App zeigt den Auftrag');
    if(function_exists('ch_toolkit')){ $tkw=ch_toolkit($aid,'widget','',['ride']); $x=($tkw['exec'])('get_ride_status',['phone'=>'0170 5555555']);
        ok(empty($x['rides']),'Datenschutz: Website-Chat bekommt zu fremder Nummer keine Fahrt',short(json_encode($x,JSON_UNESCAPED_UNICODE),140)); }
    $r=$api('POST','ride/status',['id'=>$rid,'status'=>'cancelled']); ok($r['code']===200,'Fahrt storniert (Fahrer wieder frei)');
    if($AI){
        th_plan_upsert($aid,'agent-taxi','','active',date('Y-m-d H:i:s',time()+86400));
        $r=$api('POST','agent',['plan'=>'agent-taxi','messages'=>[['role'=>'user','content'=>'Bitte ein Taxi sofort von Trankgasse 11, 50667 Köln zum Neumarkt 1, 50667 Köln, 1 Person. Name KI Test, Telefon 0170 4444444, bar. Ich bestätige – bitte direkt bestellen.']],'lang'=>'de']);
        $tools=array_map(fn($t)=>$t['name'].($t['ok']?'✓':'✗'),$r['json']['tools'] ?? []);
        ok($r['code']===200 && in_array('book_ride✓',$tools,true),'Taxi-Agent bucht über Werkzeuge',implode(', ',$tools).' · „'.short($r['json']['result'] ?? $r['body'],200).'“');
    }
} catch(Throwable $e){ ok(false,'Taxi: unerwarteter Fehler '.$e->getMessage(),basename($e->getFile()).':'.$e->getLine()); }

/* ---------- Hotel ---------- */
if(function_exists('hotel_api')) try {
    sec('Hotel: Studio-API, Verfügbarkeit, Buchung, Gastseite, Rezeption');
    $rooms=array_map(function($x){ unset($x['id']); return $x; },HOTEL_DEMO['rooms']);
    $r=$api('POST','hotel/settings',['hotel_on'=>1,'name'=>'Selftest Hotel Hamburg','address'=>'Alsterufer 1, 20354 Hamburg','phone'=>'040 1234567','tz'=>'Europe/Berlin','auto_confirm'=>1,'breakfast_mode'=>'optional','breakfast_cents'=>1800,'city_tax_pct'=>5,'cancel_hours'=>48,'rooms'=>$rooms]);
    $hs=$r['json'] ?? []; $hb=$hs['links']['board'] ?? '';
    ok($r['code']===200 && count($hs['rooms'] ?? [])>=2 && $hb,'Hotel-Einstellungen + Zimmerkategorien gespeichert',count($hs['rooms'] ?? []).' Kategorien');
    $ci=(new DateTimeImmutable('+10 days'))->format('Y-m-d'); $co=(new DateTimeImmutable('+12 days'))->format('Y-m-d');
    $r=$api('GET',"hotel/availability?checkin=$ci&checkout=$co&guests=2"); $opt=$r['json']['options'][0] ?? null;
    ok($r['code']===200 && $opt,'Verfügbarkeit mit Gesamtpreis',short(($opt['room'] ?? '').' · '.($opt['total'] ?? ''),100));
    $r=$api('POST','hotel/book',['room_id'=>(int)($opt['room_id'] ?? 0),'checkin'=>$ci,'checkout'=>$co,'guests'=>2,'name'=>'Selftest Gast','email'=>'gast@selftest.invalid','phone'=>'0170 6666666','breakfast'=>true]);
    $bk=$r['json']['booking'] ?? null; $hc=$bk['code'] ?? '';
    ok($r['code']===200 && $hc!=='','Zimmer gebucht (Studio)',$hc.' · '.short($bk['total'] ?? '',40));
    $r=http('GET',"$BASE/chat/stay.php?c=$hc",null,[],'json',true); ok($r['code']===200 && str_contains($r['body'],$hc),'Gast-Seite zur Buchung');
    $r=http('GET',$hb,null,[],'json',true); ok($r['code']===200,'Rezeptions-Board erreichbar');
    $r=$api('POST','hotel/request/new',['room_no'=>'214','details'=>'Zwei zusätzliche Handtücher bitte','kind'=>'housekeeping']); ok($r['code']===200,'Gästewunsch angelegt',short($r['body'],100));
    $r=$api('GET','hotel/requests?scope=open'); ok($r['code']===200 && count($r['json']['requests'] ?? [])>=1,'Gästewunsch in der offenen Liste');
    if(function_exists('ch_toolkit')){ $tkw=ch_toolkit($aid,'widget','',['hotel']); $x=($tkw['exec'])('get_booking',['code'=>$hc]);
        ok(empty($x['booking']) || isset($x['error']),'Datenschutz: Buchung nur mit Code UND Name/Kontakt',short(json_encode($x,JSON_UNESCAPED_UNICODE),120)); }
    if($AI){
        th_plan_upsert($aid,'agent-hotel','','active',date('Y-m-d H:i:s',time()+86400));
        $r=$api('POST','agent',['plan'=>'agent-hotel','messages'=>[['role'=>'user','content'=>"Habt ihr vom $ci bis $co ein Doppelzimmer für 2 Personen frei? Wenn ja, bitte direkt buchen auf KI Gast, gast2@selftest.invalid, ohne Frühstück. Ich bestätige die Buchung."]],'lang'=>'de']);
        $tools=array_map(fn($t)=>$t['name'].($t['ok']?'✓':'✗'),$r['json']['tools'] ?? []);
        ok($r['code']===200 && in_array('check_room_availability✓',$tools,true),'Hotel-Agent prüft Verfügbarkeit'.(in_array('book_room✓',$tools,true)?' und bucht':''),implode(', ',$tools).' · „'.short($r['json']['result'] ?? $r['body'],200).'“');
    }
} catch(Throwable $e){ ok(false,'Hotel: unerwarteter Fehler '.$e->getMessage(),basename($e->getFile()).':'.$e->getLine()); }

/* ---------- Neue Bausteine: Impressum, Rückruf, Live-Demos, Assistent, Training ---------- */
try {
    sec('Neu: Impressum, Rückruf, Live-Demos, Studio-Assistent, Training');
    $r=http('GET',"$BASE/impressum.html?_=".rnd(4)); $nm=base64_decode('U2VyZGFy');
    ok($r['code']===200 && str_contains($r['body'],'Acerasoft LLC') && str_contains($r['body'],'Management') && stripos($r['body'],$nm)===false,'Impressum-Seite (Acerasoft LLC, Management, kein Personenname)',"HTTP {$r['code']}");
    $r=http('GET',"$BASE/?_=".rnd(4)); ok($r['code']===200 && str_contains($r['body'],'href="/impressum.html"') && str_contains($r['body'],"key\":\"hausverwaltung\""),'Startseite: Impressum-Link + eigene Hausverwaltungs-Demo',"HTTP {$r['code']}");
    $r=http('GET',"$BASE/chat/callback.php?k=demo&_=".rnd(4),null,[],'json',true); ok($r['code']===200 && stripos($r['body'],'Rückruf')!==false,'Rückruf-Seite (Demo)',"HTTP {$r['code']}");
    $r=http('GET',"$BASE/chat/lead-api.php?k=demo&_=".rnd(4)); ok($r['code']===200 && is_array($r['json']),'Lead-API beschreibt Felder (Demo)',"HTTP {$r['code']}");
    $r=$api('GET','callbacks/settings'); ok($r['code']===200 && isset($r['json']['settings']),'Studio-API Rückruf-Einstellungen',"HTTP {$r['code']}");
    $r=http('GET',"$BASE/chat/concierge.php?phone_sectors=1&_=".rnd(4)); $ns=count($r['json']['sectors'] ?? $r['json'] ?? []);
    ok($r['code']===200 && $ns>=18,'Telefon-Demo: Branchenliste',"$ns Branchen");
    foreach(['agent-hausverwaltung'=>'Rheinland Hausverwaltung','praxis-agent'=>'Hausarztpraxis','agent-autohaus'=>'Autohaus Brandt','agent-versicherung'=>'Hansen'] as $pl=>$co){
        $r=http('GET',"$BASE/chat/concierge.php?agent=$pl&_=".rnd(4)); ok($r['code']===200 && str_contains((string)json_encode($r['json'],JSON_UNESCAPED_UNICODE),$co),"Live-Demo $pl ($co)","HTTP {$r['code']}");
    }
    $r=http('GET',"$BASE/chat/tts.php?_=".rnd(4)); ok(in_array($r['code'],[400,403,405,501],true),'tts.php antwortet sauber ohne Parameter',"HTTP {$r['code']}");
    $r=$api('GET','assistant/profile'); ok($r['code']===200 && isset($r['json']['profile']),'Studio-Assistent: Profil abrufbar',"HTTP {$r['code']} · Sprachen ".count($r['json']['langs'] ?? []));
    $r=$api('GET','train/overview?plan=agent-support'); ok(in_array($r['code'],[200,402,404],true),'Agent-Training: Übersicht erreichbar',"HTTP {$r['code']}");
    $r=http('GET',"$BASE/chat/studio.php?_=".rnd(4),null,[],'json',true); ok($r['code']===200 && str_contains($r['body'],'id="lPaste"') && str_contains($r['body'],'/impressum.html'),'Studio: Einfügen-Knopf für den Login-Code + Impressum-Link',"HTTP {$r['code']}");
    sec('Phase 2: Kategorien, Preise, Kanäle, Kunden, Sprachen');
    $r=http('GET',"$BASE/chat/tokens-api.php/v1/categories?_=".rnd(4)); $cats=$r['json']['categories'] ?? [];
    ok($r['code']===200 && count($cats)>=15,'Kategorien-API',count($cats).' Kategorien: '.implode(', ',array_slice(array_map(fn($c)=>$c['id'] ?? '?',$cats),0,6)).' …');
    $r=http('GET',"$BASE/chat/tokens-api.php/v1/pricing?_=".rnd(4)); ok($r['code']===200 && is_array($r['json']),'Preis-API (Lizenz → Betrieb / eigener Schlüssel)',"HTTP {$r['code']}");
    foreach(['agent-beauty'=>'Glow','agent-aesthetic'=>'Aesthetica','agent-hair-tr'=>'Istanbul','agent-dental-tr'=>'Antalya','agent-bank'=>'Bank','agent-behoerde'=>''] as $pl=>$co){
        $r=http('GET',"$BASE/chat/concierge.php?agent=$pl&_=".rnd(4)); ok($r['code']===200 && ($co==='' || str_contains((string)json_encode($r['json'],JSON_UNESCAPED_UNICODE),$co)) && !str_contains((string)$r['body'],'Muster GmbH'),"Live-Demo $pl","HTTP {$r['code']}");
    }
    $r=$api('GET','contacts/list'); ok($r['code']===200,'Kunden-Verlauf: Liste erreichbar',"HTTP {$r['code']}");
    $r=http('GET',"$BASE/chat/book.php?k=demo&lang=tr&_=".rnd(4),null,[],'json',true); ok($r['code']===200 && preg_match('/<html[^>]+lang="tr(-TR)?"/i',$r['body']),'Buchungsseite auf Türkisch (lang=tr)',"HTTP {$r['code']}");
    $r=http('GET',"$BASE/chat/concierge.php?agent=agent-hair-tr&lang=en&_=".rnd(4)); ok($r['code']===200,'Türkiye-Demo auf Englisch',"HTTP {$r['code']}");
    $r=http('GET',"$BASE/chat/studio.php?_=".rnd(4),null,[],'json',true); ok($r['code']===200 && str_contains($r['body'],'data-v="whatsapp"') && str_contains($r['body'],'id="cLangs"') && str_contains($r['body'],'data-v="kunden"'),'Studio: WhatsApp-Tab, Kunden-Tab, 13-Sprachen-Auswahl',"HTTP {$r['code']}");
    $r=http('GET',"$BASE/?_=".rnd(4)); ok($r['code']===200 && str_contains($r['body'],'id="beauty"') && str_contains($r['body'],'id="tuerkei"') && !preg_match('/id="recht"/',$r['body']),'Startseite: Beauty, Türkiye, ohne Recht-Abschnitt',"HTTP {$r['code']}");
    sec('Phase 3: echte Browser-Stimmen, App, Länder/Währung, Rechtstexte');
    foreach(['chat/voice/voice.js'=>'CHVoice','chat/voice/piper-worker.js'=>'createPiperPhonemize','chat/vendor/voice/lib/ort.wasm.min.js'=>'ONNX Runtime','chat/vendor/voice/lib/piper_phonemize.js'=>'createPiperPhonemize'] as $f=>$needle){
        $r=http('GET',"$BASE/$f?_=".rnd(4),null,[],'json',true); ok($r['code']===200 && str_contains($r['body'],$needle),"Stimmen: $f",'HTTP '.$r['code'].' · '.strlen($r['body']).' Bytes');
    }
    foreach(['de_DE-kerstin-low','de_DE-mls-medium','fr_FR-upmc-medium','es_ES-sharvard-medium','tr_TR-fettah-medium','tr_TR-fahrettin-medium'] as $k){
        $r=http('GET',"$BASE/chat/vendor/voice/models/$k.json?_=".rnd(4)); $h=http('HEAD',"$BASE/chat/vendor/voice/models/$k.bin",null,[],'raw',true);
        ok($r['code']===200 && isset($r['json']['phoneme_id_map']) && $h['code']===200,"Stimme $k (Modell + Konfiguration)",'json '.$r['code'].' · bin '.$h['code']);
    }
    $r=http('GET',"$BASE/chat/vendor/voice/LICENSES.txt?_=".rnd(4),null,[],'json',true); ok($r['code']===200 && str_contains($r['body'],'espeak-ng') && str_contains($r['body'],'== Stimmen =='),'Stimmen-Lizenzen veröffentlicht',"HTTP {$r['code']}");
    $r=http('GET',"$BASE/?_=".rnd(4),null,[],'json',true); ok($r['code']===200 && str_contains($r['body'],'/chat/voice/voice.js') && str_contains($r['body'],'href="/datenschutz.html"'),'Startseite: echte Stimmen eingebunden + Datenschutz-Link',"HTTP {$r['code']}");
    $r=http('GET',"$BASE/chat/locale-api.php?cc=TR&_=".rnd(4),null,['CF-IPCountry: TR']); ok($r['code']===200 && ($r['json']['currency'] ?? '')==='TRY' && ($r['json']['lang'] ?? '')==='tr','Länder-Erkennung: Türkei → Türkisch + TRY',short($r['json'] ?? $r['body'],120));
    $r=http('GET',"$BASE/chat/locale-api.php?cc=RU&_=".rnd(4)); ok($r['code']===200 && ($r['json']['currency'] ?? '')==='EUR','Länder-Erkennung: Russland → keine Rubel (EUR)',short($r['json']['currency'] ?? '',20));
    $r=http('GET',"$BASE/chat/tokens-api.php/v1/pricing?cur=USD&lang=en&_=".rnd(4)); ok($r['code']===200 && isset($r['json']['display_currency']),'Preis-API mit Landeswährung (USD)',"HTTP {$r['code']}");
    foreach(['chat/app.php'=>'ChatHelp','chat/app-download.php'=>'ChatHelp','datenschutz.html'=>'Stimmen im Browser','agb.html'=>'AGB','impressum.html'=>'Acerasoft'] as $f=>$needle){
        $r=http('GET',"$BASE/$f?_=".rnd(4),null,[],'json',true); ok($r['code']===200 && str_contains($r['body'],$needle),"Seite /$f","HTTP {$r['code']}");
    }
} catch(Throwable $e){ ok(false,'Neue Bausteine: unerwarteter Fehler '.$e->getMessage(),basename($e->getFile()).':'.$e->getLine()); }

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
        foreach(['Vicki-Generative','Daniel-Generative','Joanna-Generative','Matthew-Generative','Amy-Generative','Lea-Generative','Remi-Generative','Lucia-Generative','Sergio-Generative','Bianca-Generative','Adriano-Generative','de-DE-Chirp3-HD-Aoede','de-DE-Chirp3-HD-Charon','tr-TR-Chirp3-HD-Aoede'] as $v) echo "  enthält „{$v}“: ".(stripos($doc,$v)!==false?'ja':'nein')."\n";
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
