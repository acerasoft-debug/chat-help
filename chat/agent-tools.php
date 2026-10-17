<?php
/**
 * ChatHelp — Werkzeug-Register aller Fachmodule
 * ==============================================
 * Bündelt die echten Werkzeuge der Module (Termine, Bestellungen, Taxi, Hotel, Rückruf) für einen Agenten.
 * Jeder Kanal (Studio-Chat, Website-Widget, Telefon, WhatsApp, Live-Demo) ruft ch_toolkit() auf und erhält:
 *   hint    – System-Zusatz mit Fakten + Regeln aller aktiven Module
 *   tools   – Werkzeugdefinitionen für th_ai_tools() (Namen eindeutig)
 *   exec    – callable(name, input) → führt das Werkzeug im richtigen Modul aus
 *   modules – Liste der aktiven Module
 * Neue Module: Lib mit P_enabled/P_tools/P_exec/P_system_hint/P_api anlegen und unten in CH_MODULE_LIBS eintragen.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';
require_once __DIR__.'/appt-lib.php';
require_once __DIR__.'/order-lib.php';
const CH_MODULE_LIBS = ['ride'=>['ride-lib.php','ride'],'hotel'=>['hotel-lib.php','hotel'],'callback'=>['callback-lib.php','cb'],'train'=>['train-lib.php','train'],'assistant'=>['assistant-lib.php','assistant'],'push'=>['push-lib.php','push']];
foreach(CH_MODULE_LIBS as [$f,$p]) if(is_file(__DIR__.'/'.$f)) require_once __DIR__.'/'.$f;
if(is_file(__DIR__.'/push-lib.php')) require_once __DIR__.'/push-lib.php';   // Benachrichtigungen (ch_push) für alle Module

/** Module: enabled(acc) · tools() · exec(acc,name,input,channel,from) · hint(acc,channel,lang) */
function ch_modules(): array {
    static $m=null; if($m!==null) return $m; $m=[];
    $m['appt']=['enabled'=>fn(int $a)=>appt_settings($a)['appt_on']===1,'tools'=>fn()=>appt_tools(),
        'exec'=>fn(int $a,string $n,array $i,string $c,string $f)=>appt_exec($a,$n,$i,$c,$f),'hint'=>fn(int $a,string $c,string $l)=>appt_system_hint($a,$l)];
    $m['order']=['enabled'=>fn(int $a)=>ord_settings($a)['order_on']===1,'tools'=>fn()=>ord_tools(),
        'exec'=>fn(int $a,string $n,array $i,string $c,string $f)=>ord_exec($a,$n,$i,$c,$f),'hint'=>fn(int $a,string $c,string $l)=>ord_system_hint($a)];
    foreach(['ride','hotel','callback'] as $key){ $p=CH_MODULE_LIBS[$key][1]; if(!function_exists($p.'_tools') || !function_exists($p.'_exec')) continue;
        $m[$key]=['enabled'=>fn(int $a)=>function_exists($p.'_enabled') ? (bool)($p.'_enabled')($a) : true,'tools'=>fn()=>($p.'_tools')(),
            'exec'=>fn(int $a,string $n,array $i,string $c,string $f)=>($p.'_exec')($a,$n,$i,$c,$f),
            'hint'=>fn(int $a,string $c,string $l)=>function_exists($p.'_system_hint') ? (string)($p.'_system_hint')($a,$c) : ''];
    }
    return $m;
}
/**
 * Werkzeugkasten eines Kontos für einen Kanal.
 * $only = Liste von Modulschlüsseln (z. B. ['ride'] für die Taxi-Demo) – dann ohne Aktiv-Prüfung; sonst alle aktiven Module.
 */
function ch_toolkit(int $acc, string $channel, string $from='', ?array $only=null, string $lang='de'): array {
    $mods=ch_modules(); $hint=''; $tools=[]; $owner=[]; $active=[];
    foreach($mods as $key=>$mod){
        if($only!==null && !in_array($key,$only,true)) continue;
        try{ if($only===null && !($mod['enabled'])($acc)) continue; $ts=($mod['tools'])(); }catch(Throwable $e){ continue; }
        foreach((array)$ts as $t){ if(!isset($t['name']) || isset($owner[$t['name']])) continue; $owner[$t['name']]=$key; $tools[]=$t; }
        try{ $hint.=($mod['hint'])($acc,$channel,$lang); }catch(Throwable $e){}
        $active[]=$key;
    }
    $exec=function(string $n,array $i) use($acc,$channel,$from,$owner,$mods): array {
        $k=$owner[$n] ?? null; if($k===null) return ['error'=>'unknown_tool','hint'=>'Dieses Werkzeug gibt es nicht.'];
        if($acc!==0) foreach(array_keys($i) as $ik){ if($ik==='force' || $ik==='account_id' || str_starts_with((string)$ik,'_')) unset($i[$ik]); }   // interne Schalter nie aus Modell-Eingaben übernehmen
        $r=($mods[$k]['exec'])($acc,$n,$i,$channel,$from); return is_array($r) ? $r : ['result'=>$r];
    };
    return ['hint'=>$hint,'tools'=>$tools,'exec'=>$exec,'modules'=>$active,'order'=>in_array('order',$active,true),'appt'=>in_array('appt',$active,true)];
}
/**
 * Telefonnummer, nach der ein Werkzeug Kundendaten suchen darf (Datenschutz):
 * nur die vom Kanal verifizierte Absender-/Anrufernummer (WhatsApp, Telefon); im Studio (Betreiber selbst) und in der Demo frei.
 * null = Suche nur per Code aus der Bestätigung.
 */
function ch_verified_phone(int $acc, string $channel, string $from, string $asked=''): ?string {
    if($acc===0 || in_array($channel,['studio','phone-test'],true)){ $x=$asked!=='' ? $asked : $from; return strlen(preg_replace('/\D/','',$x))>=6 ? $x : null; }
    if(in_array($channel,['whatsapp','phone'],true) && strlen(preg_replace('/\D/','',$from))>=8) return $from;
    return null;
}
const CH_CODE_ONLY_HINT='Aus Datenschutzgründen nur mit dem Code aus der Bestätigung möglich (oder per WhatsApp/Anruf von der Nummer, mit der gebucht bzw. bestellt wurde). Bitte den Kunden freundlich nach dem Code fragen – niemals Daten zu einer anderen Telefonnummer herausgeben.';
/** Plan aktiv – auch als Einmalkauf (buy-…) bzw. Abo-Gegenstück desselben Agenten. */
function ch_plan_active_any(int $acc, string $plan): bool {
    if($acc===0) return true; $base=CH_PLAN_BASE[$plan] ?? $plan; $ids=[$plan,$base];
    foreach(CH_PLAN_BASE as $buy=>$agent) if($agent===$base) $ids[]=$buy;
    foreach(array_unique($ids) as $id) if(th_plan_active($acc,$id)) return true; return false;
}
/**
 * Welcher aktive Plan bedient einen öffentlichen Kanal (WhatsApp, Buchungsseite)? Zuerst der Plan der Konfiguration,
 * sonst der passende Fach-Agent eines eingeschalteten Moduls (z. B. Bestellungen → agent-bestellung/buy-bestellung). null = keiner aktiv.
 */
const CH_MODULE_PLANS=['order'=>'agent-bestellung','appt'=>'agent-termine','ride'=>'agent-taxi','hotel'=>'agent-hotel','callback'=>'immo-agent'];
function ch_channel_plan(int $acc, string $cfgPlan, array $prefer=[]): ?string {
    if(ch_plan_active_any($acc,$cfgPlan)) return $cfgPlan;
    $mods=ch_modules();
    foreach(array_unique(array_merge($prefer,array_keys(CH_MODULE_PLANS))) as $k){
        if(!isset(CH_MODULE_PLANS[$k],$mods[$k])) continue;
        try{ if(!($mods[$k]['enabled'])($acc)) continue; }catch(Throwable $e){ continue; }
        if(ch_plan_active_any($acc,CH_MODULE_PLANS[$k])) return CH_MODULE_PLANS[$k];
    }
    return null;
}
/** Studio-REST der Module: /v1/<route> → P_api(); null = nicht zuständig. */
function ch_module_api(string $route, string $method, int $aid, array $body, array $account): ?array {
    foreach(CH_MODULE_LIBS as [$f,$p]){ $fn=$p.'_api'; if(!function_exists($fn)) continue; $r=$fn($route,$method,$aid,$body,$account); if(is_array($r)) return $r; }
    return null;
}
/** Arbeit nach der Antwort an den Client erledigen (z. B. fällige KI-Rückrufe starten), ohne den Aufrufer warten zu lassen. */
function ch_after_response(callable $fn): void {
    register_shutdown_function(function() use($fn){ if(function_exists('fastcgi_finish_request')) @fastcgi_finish_request(); elseif(function_exists('litespeed_finish_request')) @litespeed_finish_request(); try{ $fn(); }catch(Throwable $e){} });
}
/** Nach der Antwort: fällige KI-Rückrufe anstoßen (siehe callback-lib) und Termin-Erinnerungen versenden – jeweils mit eigener Sperre/Taktung. */
function ch_heartbeat(): void {
    if(PHP_SAPI==='cli' || !empty($GLOBALS['__ch_hb'])) return; $GLOBALS['__ch_hb']=1;
    ch_after_response(function(){
        if(function_exists('cb_scheduler_lazy')) try{ cb_scheduler_lazy(); }catch(Throwable $e){ error_log('CH heartbeat cb: '.$e->getMessage()); }
        if(function_exists('appt_reminders_lazy')) try{ appt_reminders_lazy(); }catch(Throwable $e){ error_log('CH heartbeat appt: '.$e->getMessage()); }   // Termin-Erinnerungen auch ohne Studio-Aufruf
    });
}
