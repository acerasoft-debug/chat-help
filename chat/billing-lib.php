<?php
/**
 * ChatHelp — Abrechnung nach dem Einmalkauf + eigener KI-Schlüssel (BYOK) + KI-Verbrauch/Kostenübersicht (Präfix bl_)
 * ==================================================================================================================
 * • Einmalkauf (buy-…) = Einrichtung + Lizenz + Startguthaben + CH_BUY_INCLUDED_DAYS Tage Betrieb. Danach (Stripe-Abo, monatlich kündbar):
 *     betrieb-…  Betriebs-Abo inkl. Tokens (CH_RUN_TIERS)   ·   byok-…  Plattformgebühr, KI läuft über den Schlüssel des Kunden.
 *   token_plans: buy-… mit period_end = Ende des enthaltenen Betriebs (NULL = Bestandskauf, unbefristet). Abgelaufen → status 'license'
 *   (Lizenz bleibt Eigentum, Agent ruht bis Betrieb/BYOK aktiv ist). Folge-Abos zählen nur zusammen mit der Lizenz.
 * • BYOK: Schlüssel in acct_secrets (claude_key, deepseek_key – verschlüsselt mit CH_SECRET_KEY), Zustand in ai_byok.
 *   th_ai()/th_ai_tools() nutzen ihn, sobald das Konto ein aktives Paket hat; dann bucht th_charge() keine Tokens ab.
 *   Abgelehnter Schlüssel → Status „invalid“, Hinweis im Studio + Push; Ersatz über die Plattform nur mit eigenem Guthaben.
 * • ai_usage: echte Modell-Tokens je Konto/Monat/Kanal/Anbieter (aus der API-Antwort) → bl_cost_overview() schätzt € nach Listenpreisen.
 * Geladen von tokens-lib.php (th_db, th_secret, th_plans …). Plan-Konstanten aus plans.php werden erst zur Laufzeit gelesen.
 */

/** Ab so viel Guthaben gilt ein BYOK-Konto für die Guthaben-Prüfungen der Kanäle als „bereit“ (th_balance), gebucht wird nichts. */
const BL_BYOK_GATE = 1000;
/** Anbieter für eigene Schlüssel: Anthropic-Messages-Format (DeepSeek über den Anthropic-kompatiblen Endpunkt, Thinking aus). */
const BL_BYOK_PROVIDERS = [
  'claude'   => ['label'=>'Claude (Anthropic)','secret'=>'claude_key','url'=>'https://api.anthropic.com/v1/messages','model'=>'claude-sonnet-5-5','extra'=>[],
                 'pattern'=>'/^sk-ant-[A-Za-z0-9_\-]{20,200}$/','format'=>'beginnt mit sk-ant-','console'=>'https://console.anthropic.com/settings/keys'],
  'deepseek' => ['label'=>'DeepSeek','secret'=>'deepseek_key','url'=>'https://api.deepseek.com/anthropic/v1/messages','model'=>'deepseek-flash','extra'=>['thinking'=>['type'=>'disabled']],
                 'pattern'=>'/^sk-[A-Za-z0-9]{20,120}$/','format'=>'beginnt mit sk-','console'=>'https://platform.deepseek.com/api_keys'],
];
/**
 * Listenpreise in US-Dollar je 1 Mio. Tokens (für die Schätzung in der Kostenübersicht).
 * DeepSeek: off-peak/peak (Rabattfenster BL_DEEPSEEK_OFFPEAK_UTC); cache_read = Cache-Treffer, in = Cache-Fehlgriff.
 * Ältere Plattform-Modelle der Fallback-Kette sind mit ihren Listenpreisen enthalten; unbekannte Modelle werden wie Sonnet 5.5 geschätzt.
 * Claude: geprüft am 25.10.2026 mit claude.com/pricing (Cache-Schreiben = 5-Minuten-Cache).
 */
const BL_AI_PRICES = [
  'claude-sonnet-5-5' => ['label'=>'Claude Sonnet 5.5','in'=>2.00,'out'=>10.00,'cache_read'=>0.10,'cache_write'=>2.50],
  'claude-sonnet-5'   => ['label'=>'Claude Sonnet 5','in'=>2.00,'out'=>10.00,'cache_read'=>0.20,'cache_write'=>2.50],
  'claude-haiku-5-5'  => ['label'=>'Claude Haiku 5.5','in'=>0.10,'out'=>0.50,'cache_read'=>0.01,'cache_write'=>0.125],
  'claude-opus-5-5'   => ['label'=>'Claude Opus 5.5','in'=>4.00,'out'=>20.00,'cache_read'=>0.20,'cache_write'=>5.00],
  'claude-sonnet-4-6' => ['label'=>'Claude Sonnet 4.6','in'=>3.00,'out'=>15.00,'cache_read'=>0.30,'cache_write'=>3.75],
  'claude-sonnet-4-5' => ['label'=>'Claude Sonnet 4.5','in'=>3.00,'out'=>15.00,'cache_read'=>0.30,'cache_write'=>3.75],
  'deepseek-flash'    => ['label'=>'DeepSeek Flash','offpeak'=>['in'=>0.15,'out'=>0.60,'cache_read'=>0.003],'peak'=>['in'=>0.30,'out'=>1.20,'cache_read'=>0.006]],
  'deepseek-eu'       => ['label'=>'DeepSeek (EU-Anbieter)','in'=>0.50,'out'=>1.50,'cache_read'=>0.0,'cache_write'=>0.50],   // Schätzung, Preis des EU-Anbieters in db.php: CH_DEEPSEEK_EU_PRICE
  'deepseek-chat'     => ['label'=>'DeepSeek (Plattform-Ersatz)','offpeak'=>['in'=>0.15,'out'=>0.60,'cache_read'=>0.003],'peak'=>['in'=>0.30,'out'=>1.20,'cache_read'=>0.006]],
];
/** DeepSeek-Rabattfenster (UTC, über Mitternacht). */
const BL_DEEPSEEK_OFFPEAK_UTC = ['16:30','00:30'];
/** Listenpreis eines Plattform-Tokens in € (10.000er-Paket 49 €) – nur für die Schätzung „Wert der verbrauchten Tokens“. */
const BL_TOKEN_EUR = 0.0049;
const BL_CHANNEL_LABELS = ['phone'=>'Telefon','whatsapp'=>'WhatsApp','widget'=>'Website-Widget','studio'=>'Studio-Chat','phone-test'=>'Studio (Telefon-Test)',
  'api'=>'API','assistant'=>'Studio-Assistent','train'=>'Training','booking'=>'Buchungsseite','demo'=>'Demo','test'=>'Schlüssel-Test','other'=>'Sonstiges'];

/** Wechselkurs USD → EUR für Schätzungen (db.php: CH_FX_USD_EUR überschreibt). */
function bl_usd_eur(): float { return (defined('CH_FX_USD_EUR') && (float)CH_FX_USD_EUR>0) ? (float)CH_FX_USD_EUR : 0.86; }

function bl_bootstrap(): void {
    static $done=false; if($done) return; $done=true; $p=th_db();
    $p->exec("CREATE TABLE IF NOT EXISTS ai_byok (account_id INT NOT NULL PRIMARY KEY, pref VARCHAR(12) DEFAULT 'auto',
        claude_status VARCHAR(12) DEFAULT '', claude_error VARCHAR(255) DEFAULT '', claude_checked DATETIME NULL,
        deepseek_status VARCHAR(12) DEFAULT '', deepseek_error VARCHAR(255) DEFAULT '', deepseek_checked DATETIME NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS ai_usage (id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, ym CHAR(7) NOT NULL, channel VARCHAR(16) NOT NULL,
        provider VARCHAR(12) NOT NULL, model VARCHAR(48) NOT NULL, byok TINYINT(1) NOT NULL DEFAULT 0, peak TINYINT(1) NOT NULL DEFAULT 0,
        calls INT NOT NULL DEFAULT 0, in_tok BIGINT NOT NULL DEFAULT 0, out_tok BIGINT NOT NULL DEFAULT 0, cache_read BIGINT NOT NULL DEFAULT 0, cache_write BIGINT NOT NULL DEFAULT 0,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq (account_id, ym, channel, provider, model, byok, peak)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    /* Plattform je Tag (alle Konten + Demo): echte Kosten je Antwort vorher/nachher messen (Prompt-Cache), ohne Kontobezug */
    $p->exec("CREATE TABLE IF NOT EXISTS ai_usage_day (d DATE NOT NULL, channel VARCHAR(16) NOT NULL, provider VARCHAR(12) NOT NULL, model VARCHAR(48) NOT NULL,
        byok TINYINT(1) NOT NULL DEFAULT 0, peak TINYINT(1) NOT NULL DEFAULT 0, calls INT NOT NULL DEFAULT 0, in_tok BIGINT NOT NULL DEFAULT 0, out_tok BIGINT NOT NULL DEFAULT 0,
        cache_read BIGINT NOT NULL DEFAULT 0, cache_write BIGINT NOT NULL DEFAULT 0, PRIMARY KEY (d, channel, provider, model, byok, peak)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/* ============================================================== Lizenz, Betrieb, eigener Schlüssel */
/** Abgelaufenen enthaltenen Betrieb von Einmalkäufen auf 'license' setzen (einmal je Konto und Request). */
function bl_expire_licenses(int $acc): void {
    static $done=[]; if($acc<=0 || isset($done[$acc])) return; $done[$acc]=1;
    try{ th_db()->prepare("UPDATE token_plans SET status='license', updated_at=NOW() WHERE account_id=? AND stripe_sub IS NULL AND status='active' AND plan LIKE 'buy-%' AND period_end IS NOT NULL AND period_end < NOW()")->execute([$acc]); }catch(Throwable $e){}
}
/** Lizenz besitzt das Konto (gekauft, enthaltener Betrieb aktiv oder abgelaufen). */
function bl_license_owned(int $acc, string $buyId): bool {
    if($acc<=0 || !str_starts_with($buyId,'buy-')) return false;
    $s=th_db()->prepare("SELECT COUNT(*) FROM token_plans WHERE account_id=? AND plan=? AND status IN ('active','trialing','past_due','license')"); $s->execute([$acc,$buyId]); return (int)$s->fetchColumn()>0;
}
/** Lizenz-Zeile eines Einmalkaufs (oder null). */
function bl_license_row(int $acc, string $buyId): ?array {
    bl_expire_licenses($acc);
    $s=th_db()->prepare("SELECT * FROM token_plans WHERE account_id=? AND plan=? AND stripe_sub IS NULL ORDER BY id DESC LIMIT 1"); $s->execute([$acc,$buyId]); return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}
/**
 * Einmalkauf verbuchen: Lizenz + enthaltener Betrieb bis jetzt + CH_BUY_INCLUDED_DAYS (verlängert, nie verkürzt).
 * Bestandskäufe ohne Enddatum (unbefristet) bleiben unbefristet. Gibt das Ende des enthaltenen Betriebs zurück (null = unbefristet).
 */
function bl_license_activate(int $acc, string $buyId, int $days=0): ?string {
    if($days<=0) $days=function_exists('ch_buy_included_days') ? ch_buy_included_days($buyId) : (defined('CH_BUY_INCLUDED_DAYS') ? (int)CH_BUY_INCLUDED_DAYS : 90);
    $row=bl_license_row($acc,$buyId);
    if($row && $row['period_end']===null && $row['status']==='active') return null;   // Bestandskauf: unbefristet
    $end=(new DateTimeImmutable('now'))->modify('+'.$days.' days');
    if($row && $row['period_end'] && strtotime((string)$row['period_end'])>$end->getTimestamp()) $end=new DateTimeImmutable((string)$row['period_end']);
    $pe=th_db()->query("SELECT NOW() + INTERVAL ".max(0,(int)ceil(($end->getTimestamp()-time())/86400))." DAY")->fetchColumn();   // DB-Zeit (period_end wird mit NOW() verglichen)
    th_plan_upsert($acc,$buyId,'','active',(string)$pe);
    return (string)$pe;
}
/** Aktives Folge-Abo (betrieb-/byok-) zu einer Lizenz: ['plan','status','period_end','stripe_sub'] oder null. */
function bl_run_plan(int $acc, string $buyId): ?array {
    $x=substr($buyId,4); $s=th_db()->prepare("SELECT plan,status,period_end,stripe_sub FROM token_plans WHERE account_id=? AND plan IN (?,?) AND status IN ('active','trialing','past_due') ORDER BY id DESC LIMIT 1");
    $s->execute([$acc,'betrieb-'.$x,'byok-'.$x]); return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}
/** Hat das Konto irgendein laufendes Paket (Voraussetzung, damit der eigene KI-Schlüssel genutzt wird)? */
function bl_has_active_plan(int $acc): bool {
    if($acc<=0) return false; bl_expire_licenses($acc);
    foreach(th_plans($acc) as $p){
        if(!in_array($p['status'],['active','trialing','past_due'],true)) continue;
        if(preg_match('/^(?:betrieb|byok)-([a-z0-9-]+)$/',(string)$p['plan'],$m) && !bl_license_owned($acc,'buy-'.$m[1])) continue;   // Folge-Abo ohne Lizenz zählt nicht
        return true;
    }
    return false;
}
/**
 * Lizenzen + Betrieb für das Studio (GET /v1/billing): je Einmalkauf Status (unbefristet | inklusive bis … | Betrieb nötig),
 * aktives Folge-Abo, Preise und Checkout-Links für Betrieb bzw. eigenen Schlüssel.
 */
function bl_billing_overview(int $acc, string $email=''): array {
    bl_expire_licenses($acc); $out=[];
    $s=th_db()->prepare("SELECT plan,status,period_end FROM token_plans WHERE account_id=? AND plan LIKE 'buy-%' AND stripe_sub IS NULL ORDER BY id"); $s->execute([$acc]);
    foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r){
        $pl=ch_plan((string)$r['plan']); if(!$pl) continue; $run=bl_run_plan($acc,$pl['id']); $fu=ch_followups($pl['id']);
        $state=$r['status']==='active' ? ($r['period_end']===null ? 'lifetime' : 'included') : ($r['status']==='license' ? 'expired' : (string)$r['status']);
        $q=$email!=='' ? '&email='.rawurlencode($email) : '';
        $out[]=['license'=>$pl['id'],'name'=>$pl['name'],'base'=>$pl['base'],'state'=>$state,'included_until'=>$r['period_end'] ? th_dbiso((string)$r['period_end']) : null,
          'run'=>$run ? ['plan'=>$run['plan'],'kind'=>str_starts_with((string)$run['plan'],'byok-') ? 'byok' : 'betrieb','status'=>$run['status'],'period_end'=>$run['period_end'] ? th_dbiso((string)$run['period_end']) : null] : null,
          'active'=>$state==='lifetime' || $state==='included' || $run!==null,
          'options'=>array_values(array_map(fn($f)=>['id'=>$f['id'],'kind'=>$f['kind'],'name'=>$f['name'],'eur_month'=>$f['cents']/100,'tokens_month'=>$f['tokens'],
              'checkout'=>'/chat/tokens-checkout.php?plan='.rawurlencode($f['id']).$q],$fu))];
    }
    return $out;
}

/* ---------- eigener KI-Schlüssel ---------- */
function bl_byok_state(int $acc): array {
    $d=['pref'=>'auto','claude'=>['status'=>'','error'=>'','checked'=>null],'deepseek'=>['status'=>'','error'=>'','checked'=>null]];
    if($acc<=0) return $d;
    try{ bl_bootstrap(); $s=th_db()->prepare("SELECT * FROM ai_byok WHERE account_id=?"); $s->execute([$acc]); $r=$s->fetch(PDO::FETCH_ASSOC); }catch(Throwable $e){ $r=false; }
    if(!$r) return $d;
    $d['pref']=in_array($r['pref'],['auto','claude','deepseek'],true) ? (string)$r['pref'] : 'auto';
    foreach(['claude','deepseek'] as $p) $d[$p]=['status'=>(string)$r[$p.'_status'],'error'=>(string)$r[$p.'_error'],'checked'=>$r[$p.'_checked']];
    return $d;
}
function bl_byok_set(int $acc, string $provider, string $status, string $error=''): void {
    if($acc<=0 || !isset(BL_BYOK_PROVIDERS[$provider])) return; bl_bootstrap();
    th_db()->prepare("INSERT INTO ai_byok(account_id,{$provider}_status,{$provider}_error,{$provider}_checked) VALUES(?,?,?,NOW())
        ON DUPLICATE KEY UPDATE {$provider}_status=VALUES({$provider}_status),{$provider}_error=VALUES({$provider}_error),{$provider}_checked=NOW(),updated_at=NOW()")
      ->execute([$acc,$status,mb_substr($error,0,255)]);
    unset($GLOBALS['__bl_routes'][$acc]);
}
/**
 * Reihenfolge der eigenen Schlüssel für th_ai(): bevorzugter Anbieter zuerst, abgelehnte Schlüssel ausgelassen.
 * Leer, wenn kein Schlüssel hinterlegt ist oder das Konto kein laufendes Paket hat (eigener Schlüssel ersetzt nur Tokens, nicht das Paket).
 */
function bl_byok_routes(int $acc): array {
    if($acc<=0) return [];
    if(isset($GLOBALS['__bl_routes'][$acc])) return $GLOBALS['__bl_routes'][$acc];
    $routes=[];
    try{
        $keys=[]; foreach(BL_BYOK_PROVIDERS as $p=>$c){ $k=th_secret($acc,$c['secret']); if($k!=='') $keys[$p]=$k; }
        if($keys && bl_has_active_plan($acc)){
            $st=bl_byok_state($acc); $order=$st['pref']==='deepseek' ? ['deepseek','claude'] : ['claude','deepseek'];
            foreach($order as $p){ if(!isset($keys[$p]) || $st[$p]['status']==='invalid') continue; $c=BL_BYOK_PROVIDERS[$p];
                $routes[]=['provider'=>$p,'url'=>$c['url'],'key'=>$keys[$p],'models'=>[$c['model']],'byok'=>true,'extra'=>$c['extra']]; }
        }
    }catch(Throwable $e){ error_log('CH bl_byok_routes: '.$e->getMessage()); }
    return $GLOBALS['__bl_routes'][$acc]=$routes;
}
function bl_byok_usable(int $acc): bool { return (bool)bl_byok_routes($acc); }
/** Ersatz über die Plattform-KI, wenn der eigene Schlüssel scheitert: nur mit echtem (gekauftem/enthaltenem) Guthaben. */
function bl_platform_fallback_ok(int $acc): bool { return th_balance_ledger($acc)>0; }
/** Schlüssel abgelehnt (401/403/402): Status setzen, Hinweis im Studio + Push (höchstens alle 6 Stunden je Anbieter). */
function bl_byok_invalid(int $acc, string $provider, string $msg): void {
    $prev=bl_byok_state($acc)[$provider]['status'] ?? '';
    bl_byok_set($acc,$provider,'invalid',$msg!=='' ? $msg : 'Schlüssel abgelehnt');
    if(!function_exists('ch_push')) return;
    try{ if(function_exists('push_slot') && !push_slot('byok|'.$acc.'|'.$provider,21600) && $prev==='invalid') return; }catch(Throwable $e){}
    $lab=BL_BYOK_PROVIDERS[$provider]['label'] ?? $provider; $fb=bl_platform_fallback_ok($acc);
    ch_push($acc,'system','Eigener KI-Schlüssel abgelehnt: '.$lab,
        $lab.' hat deinen Schlüssel abgelehnt ('.mb_substr($msg,0,120).'). '.($fb ? 'Bis du ihn erneuerst, antworten deine Agenten über dein ChatHelp-Guthaben.' : 'Ohne ChatHelp-Guthaben antworten deine Agenten gerade nicht – bitte Schlüssel im Studio unter Telefon → Verbindungen erneuern oder Tokens nachladen.'),
        '/chat/studio.php#phone',['tag'=>'byok-'.$provider,'urgency'=>'high']);
}
function bl_byok_ok(int $acc, string $provider): void {
    if((bl_byok_state($acc)[$provider]['status'] ?? '')!=='ok') bl_byok_set($acc,$provider,'ok','');
}
/** Format eines Schlüssels prüfen (ohne Netz). '' = ok, sonst Fehlermeldung. */
function bl_byok_format_error(string $provider, string $key): string {
    $c=BL_BYOK_PROVIDERS[$provider] ?? null; if(!$c) return 'Unbekannter Anbieter.';
    return preg_match($c['pattern'],$key) ? '' : $c['label'].'-Schlüssel ungültig – er '.$c['format'].' (zu finden unter '.$c['console'].').';
}
/**
 * Test mit einem 1-Token-Aufruf. $key = neuer Schlüssel (sonst der gespeicherte). Rückgabe ['ok'=>bool,'status'=>'ok'|'invalid'|'error','message'=>…,'model'=>…].
 * Mit $save=true wird der Zustand gespeichert (nur für den gespeicherten Schlüssel sinnvoll).
 */
function bl_byok_test(int $acc, string $provider, ?string $key=null, bool $save=true): array {
    $c=BL_BYOK_PROVIDERS[$provider] ?? null; if(!$c) return ['ok'=>false,'status'=>'error','message'=>'Unbekannter Anbieter.'];
    $key=$key ?? th_secret($acc,$c['secret']); if($key==='') return ['ok'=>false,'status'=>'missing','message'=>'Kein '.$c['label'].'-Schlüssel hinterlegt.'];
    $pl=['model'=>$c['model'],'max_tokens'=>1,'messages'=>[['role'=>'user','content'=>'ping']]]+$c['extra'];
    [$code,$r,$cerr]=th_ai_http($c['url'],['x-api-key: '.$key,'anthropic-version: 2023-06-01','content-type: application/json'],$pl,15);
    $et=(string)($r['error']['type'] ?? ''); $em=trim((string)($r['error']['message'] ?? $cerr));
    if($code===200 && is_array($r)){
        if(is_array($r['usage'] ?? null)) bl_usage_log($acc,'test',$provider,(string)($r['model'] ?? $c['model']),true,bl_usage_norm($r['usage']));
        if($save) bl_byok_set($acc,$provider,'ok','');
        return ['ok'=>true,'status'=>'ok','message'=>$c['label'].'-Schlüssel funktioniert ✓ (Modell '.$c['model'].').','model'=>$c['model']];
    }
    if(in_array($code,[401,402,403],true) || in_array($et,['authentication_error','permission_error'],true)){
        $msg=$code===402 ? 'Guthaben beim Anbieter aufgebraucht' : ($em!=='' ? mb_substr($em,0,160) : 'Schlüssel abgelehnt');
        if($save) bl_byok_set($acc,$provider,'invalid',$msg);
        return ['ok'=>false,'status'=>'invalid','message'=>$c['label'].' hat den Schlüssel abgelehnt: '.$msg.'.','http'=>$code];
    }
    return ['ok'=>false,'status'=>'error','message'=>$c['label'].' gerade nicht erreichbar ('.($code ?: 'Netzwerk').($em!=='' ? ': '.mb_substr($em,0,120) : '').') – bitte später erneut testen.','http'=>$code];
}
/**
 * Schlüssel speichern/löschen (Studio → Verbindungen). $in: claude_key, deepseek_key ('' = löschen, '••••…' = unverändert), ai_pref.
 * Neue Schlüssel werden vor dem Speichern getestet: abgelehnt → Fehler, nicht gespeichert; Anbieter nicht erreichbar → gespeichert, Status offen.
 */
function bl_byok_save(int $acc, array $in): array {
    $tests=[];
    foreach(BL_BYOK_PROVIDERS as $p=>$c){
        if(!array_key_exists($c['secret'],$in)) continue; $v=trim((string)$in[$c['secret']]);
        if(str_starts_with($v,'••••')) continue;
        if($v===''){ th_secret_set($acc,$c['secret'],''); bl_byok_set($acc,$p,'',''); continue; }
        if(($fe=bl_byok_format_error($p,$v))!=='') return ['error'=>'bad_value','field'=>$c['secret'],'message'=>$fe];
        $t=bl_byok_test($acc,$p,$v,false);
        if($t['status']==='invalid') return ['error'=>'byok_auth_failed','field'=>$c['secret'],'message'=>$t['message']];
        th_secret_set($acc,$c['secret'],$v); bl_byok_set($acc,$p,$t['ok'] ? 'ok' : '',$t['ok'] ? '' : '');
        $tests[$p]=$t;
    }
    if(isset($in['ai_pref']) && in_array($in['ai_pref'],['auto','claude','deepseek'],true)){
        bl_bootstrap(); th_db()->prepare("INSERT INTO ai_byok(account_id,pref) VALUES(?,?) ON DUPLICATE KEY UPDATE pref=VALUES(pref),updated_at=NOW()")->execute([$acc,$in['ai_pref']]);
        unset($GLOBALS['__bl_routes'][$acc]);
    }
    return ['ok'=>true,'tests'=>$tests];
}
/** Für das Studio: nie den Schlüssel, nur „••••1234“, Status, Fehlermeldung, ob er genutzt wird, und ob ein Ersatz über Guthaben möglich ist. */
function bl_byok_public(int $acc): array {
    $st=bl_byok_state($acc); $hints=th_secret_hints($acc); $o=['pref'=>$st['pref'],'entitled'=>bl_has_active_plan($acc),'active'=>bl_byok_usable($acc),
      'fallback'=>bl_platform_fallback_ok($acc),'providers'=>[]];
    foreach(BL_BYOK_PROVIDERS as $p=>$c) $o['providers'][$p]=['label'=>$c['label'],'model'=>$c['model'],'hint'=>$hints[$c['secret']] ?? '','status'=>$st[$p]['status'],
        'error'=>$st[$p]['error'],'checked'=>$st[$p]['checked'] ? th_dbiso((string)$st[$p]['checked']) : null,'console'=>$c['console']];
    $o['note']=!$o['entitled'] ? 'Der eigene Schlüssel wird genutzt, sobald ein Paket aktiv ist (Abo, Einmalkauf im Inklusiv-Zeitraum, Betrieb oder „Eigener KI-Schlüssel“).'
        : ($o['active'] ? 'Aktiv: Deine Agenten nutzen deinen Schlüssel – ChatHelp bucht dafür keine Tokens ab.' : '');
    foreach(['claude','deepseek'] as $p) if($st[$p]['status']==='invalid') $o['alert']=BL_BYOK_PROVIDERS[$p]['label'].'-Schlüssel abgelehnt: '.$st[$p]['error'].'. '.($o['fallback'] ? 'Bis zur Erneuerung läuft der Betrieb über dein Guthaben.' : 'Ohne Guthaben antworten deine Agenten nicht – bitte Schlüssel erneuern.');
    return $o;
}

/* ============================================================== Verbrauch & Kosten */
/** usage-Block einer Anbieter-Antwort (Anthropic- oder OpenAI-Format) → in/out/cache_read/cache_write. */
function bl_usage_norm(array $u): array {
    $cr=(int)($u['cache_read_input_tokens'] ?? ($u['prompt_cache_hit_tokens'] ?? 0)); $cw=(int)($u['cache_creation_input_tokens'] ?? 0);
    $in=isset($u['input_tokens']) ? (int)$u['input_tokens'] : max(0,(int)($u['prompt_tokens'] ?? 0)-$cr);
    $out=(int)($u['output_tokens'] ?? ($u['completion_tokens'] ?? 0));
    return ['in'=>max(0,$in),'out'=>max(0,$out),'cache_read'=>max(0,$cr),'cache_write'=>max(0,$cw)];
}
/** Liegt der Zeitpunkt im DeepSeek-Rabattfenster? (false = Normalpreis) */
function bl_deepseek_offpeak(?int $ts=null): bool {
    $t=(new DateTimeImmutable('@'.($ts ?? time())))->setTimezone(new DateTimeZone('UTC'))->format('H:i'); [$a,$b]=BL_DEEPSEEK_OFFPEAK_UTC;
    return $a<=$b ? ($t>=$a && $t<$b) : ($t>=$a || $t<$b);
}
/** Verbrauch eines KI-Aufrufs (= einer Antwort, Werkzeug-Runden eingerechnet) verbuchen: je Konto/Monat (Konto 0 = Demo der Plattform) und plattformweit je Tag. */
function bl_usage_log(int $acc, string $channel, string $provider, string $model, bool $byok, array $u): void {
    if($acc<0 || ($u['in']+$u['out']+$u['cache_read']+$u['cache_write'])<=0) return;
    try{
        bl_bootstrap(); $peak=($provider==='deepseek' && !bl_deepseek_offpeak()) ? 1 : 0;
        $ch=substr(preg_replace('/[^a-z0-9-]/','',strtolower($channel)) ?: 'other',0,16); $pv=substr($provider,0,12); $md=substr($model,0,48);
        th_db()->prepare("INSERT INTO ai_usage(account_id,ym,channel,provider,model,byok,peak,calls,in_tok,out_tok,cache_read,cache_write) VALUES(?,?,?,?,?,?,?,1,?,?,?,?)
            ON DUPLICATE KEY UPDATE calls=calls+1,in_tok=in_tok+VALUES(in_tok),out_tok=out_tok+VALUES(out_tok),cache_read=cache_read+VALUES(cache_read),cache_write=cache_write+VALUES(cache_write),updated_at=NOW()")
          ->execute([$acc,date('Y-m'),$ch,$pv,$md,$byok?1:0,$peak,$u['in'],$u['out'],$u['cache_read'],$u['cache_write']]);
        th_db()->prepare("INSERT INTO ai_usage_day(d,channel,provider,model,byok,peak,calls,in_tok,out_tok,cache_read,cache_write) VALUES(?,?,?,?,?,?,1,?,?,?,?)
            ON DUPLICATE KEY UPDATE calls=calls+1,in_tok=in_tok+VALUES(in_tok),out_tok=out_tok+VALUES(out_tok),cache_read=cache_read+VALUES(cache_read),cache_write=cache_write+VALUES(cache_write)")
          ->execute([date('Y-m-d'),$ch,$pv,$md,$byok?1:0,$peak,$u['in'],$u['out'],$u['cache_read'],$u['cache_write']]);
    }catch(Throwable $e){ error_log('CH bl_usage_log: '.$e->getMessage()); }
}
/** Anteil der Eingabe, die aus dem Prompt-Cache kam (0–1). */
function bl_cache_share(int $in, int $cr, int $cw): float { $t=$in+$cr+$cw; return $t>0 ? round($cr/$t,3) : 0.0; }
/** Betreiber der Plattform (db.php bzw. keybox: CH_ADMIN_EMAILS, Komma-getrennt; E-Mail oder „sha256:<hex>“ der kleingeschriebenen E-Mail) – sieht die Plattform-Kosten aller Konten. */
function bl_is_admin(int $acc): bool {
    $l=defined('CH_ADMIN_EMAILS') ? (is_array(CH_ADMIN_EMAILS) ? CH_ADMIN_EMAILS : explode(',',(string)CH_ADMIN_EMAILS)) : []; $l=array_filter(array_map(fn($x)=>strtolower(trim((string)$x)),$l));
    if($acc<=0 || !$l) return false; $s=th_db()->prepare("SELECT email FROM token_accounts WHERE id=?"); $s->execute([$acc]); $e=strtolower(trim((string)$s->fetchColumn()));
    return $e!=='' && (in_array($e,$l,true) || in_array('sha256:'.hash('sha256',$e),$l,true));
}
/**
 * Echte Kosten je Antwort der Plattform (Claude über ChatHelp, ohne eigene Schlüssel) aus ai_usage_day: Zeitraum [from, to), je Kanal und gesamt.
 * Rückgabe: ['from','to','channels'=>[{channel,label,answers,eur,cent_per_answer,cache_share,in,out,cache_read,cache_write}],'total'=>{…}]
 */
function bl_answer_costs(string $from, string $to): array {
    bl_bootstrap(); $fx=bl_usd_eur(); $ch=[]; $tot=['answers'=>0,'eur'=>0.0,'in'=>0,'out'=>0,'cache_read'=>0,'cache_write'=>0];
    $s=th_db()->prepare("SELECT channel,model,peak,SUM(calls) c,SUM(in_tok) i,SUM(out_tok) o,SUM(cache_read) cr,SUM(cache_write) cw FROM ai_usage_day WHERE d>=? AND d<? AND byok=0 GROUP BY channel,model,peak");
    $s->execute([$from,$to]);
    foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r){ $c=(string)$r['channel'];
        $ch[$c]??=['channel'=>$c,'label'=>BL_CHANNEL_LABELS[$c] ?? $c,'answers'=>0,'eur'=>0.0,'in'=>0,'out'=>0,'cache_read'=>0,'cache_write'=>0];
        $eur=bl_cost_usd((string)$r['model'],(bool)(int)$r['peak'],(int)$r['i'],(int)$r['o'],(int)$r['cr'],(int)$r['cw'])*$fx;
        foreach([&$ch[$c],&$tot] as &$x){ $x['answers']+=(int)$r['c']; $x['eur']+=$eur; $x['in']+=(int)$r['i']; $x['out']+=(int)$r['o']; $x['cache_read']+=(int)$r['cr']; $x['cache_write']+=(int)$r['cw']; } unset($x); }
    $fin=function(array $x){ $x['cent_per_answer']=$x['answers'] ? round(100*$x['eur']/$x['answers'],3) : null; $x['cache_share']=bl_cache_share($x['in'],$x['cache_read'],$x['cache_write']); $x['eur']=round($x['eur'],4); return $x; };
    $pos=array_flip(array_keys(BL_CHANNEL_LABELS)); $list=array_map($fin,array_values($ch)); usort($list,fn($a,$b)=>($pos[$a['channel']] ?? 99) <=> ($pos[$b['channel']] ?? 99));
    return ['from'=>$from,'to'=>$to,'channels'=>$list,'total'=>$fin($tot),'fx_usd_eur'=>$fx];
}
/** Geschätzte Kosten in USD nach Listenpreis. */
function bl_cost_usd(string $model, bool $peak, int $in, int $out, int $cr=0, int $cw=0): float {
    $p=BL_AI_PRICES[$model] ?? (str_starts_with($model,'deepseek') ? BL_AI_PRICES['deepseek-flash'] : BL_AI_PRICES['claude-sonnet-5-5']);
    if(isset($p['peak'])) $p=$p[$peak ? 'peak' : 'offpeak'];
    return ($in*$p['in'] + $out*$p['out'] + $cr*($p['cache_read'] ?? 0) + $cw*($p['cache_write'] ?? $p['in']))/1e6;
}
/** Kanal aus einer api_usage-Aufgabe (phone, whatsapp, widget, agent:…, booking, assistant, train:…, api:…) */
function bl_task_channel(string $task): string {
    $t=strtolower($task);
    foreach(['phone-test'=>'phone-test','phone'=>'phone','whatsapp'=>'whatsapp','widget'=>'widget','booking'=>'booking','assistant'=>'assistant','train'=>'train','agent'=>'studio'] as $k=>$c) if(str_starts_with($t,$k)) return $c;
    return 'api';
}
/**
 * Monatsübersicht (GET /v1/usage?month=YYYY-MM): je Kanal Modell-Tokens (eigener Schlüssel und Plattform getrennt) mit geschätzten Kosten in €,
 * dazu die abgebuchten ChatHelp-Tokens (api_usage) und ihr Listenwert. Preise und Wechselkurs werden mitgeliefert.
 */
function bl_cost_overview(int $acc, string $ym=''): array {
    if(!preg_match('/^\d{4}-\d{2}$/',$ym)) $ym=date('Y-m'); bl_bootstrap(); $fx=bl_usd_eur(); $ch=[];
    $row=function(string $c) use(&$ch){ if(!isset($ch[$c])) $ch[$c]=['channel'=>$c,'label'=>BL_CHANNEL_LABELS[$c] ?? $c,'calls'=>0,'byok'=>['in'=>0,'out'=>0,'cache_read'=>0,'cache_write'=>0,'eur'=>0.0],
        'platform_ai'=>['in'=>0,'out'=>0,'cache_read'=>0,'cache_write'=>0,'eur'=>0.0],'platform_tokens'=>0,'platform_tokens_eur'=>0.0,'models'=>[]]; };
    $s=th_db()->prepare("SELECT channel,provider,model,byok,peak,SUM(calls) calls,SUM(in_tok) i,SUM(out_tok) o,SUM(cache_read) cr,SUM(cache_write) cw FROM ai_usage WHERE account_id=? AND ym=? GROUP BY channel,provider,model,byok,peak");
    $s->execute([$acc,$ym]);
    foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r){
        $c=(string)$r['channel']; $row($c); $k=(int)$r['byok'] ? 'byok' : 'platform_ai';
        $eur=bl_cost_usd((string)$r['model'],(bool)(int)$r['peak'],(int)$r['i'],(int)$r['o'],(int)$r['cr'],(int)$r['cw'])*$fx;
        $ch[$c]['calls']+=(int)$r['calls']; foreach(['in'=>'i','out'=>'o','cache_read'=>'cr','cache_write'=>'cw'] as $a=>$b) $ch[$c][$k][$a]+=(int)$r[$b]; $ch[$c][$k]['eur']+=$eur;
        $mk=(string)$r['model'].((int)$r['byok'] ? ' · eigener Schlüssel' : ' · Plattform'); $ch[$c]['models'][$mk]=($ch[$c]['models'][$mk] ?? 0)+(int)$r['i']+(int)$r['o']+(int)$r['cr'];
    }
    [$y,$m]=array_map('intval',explode('-',$ym)); $from=sprintf('%04d-%02d-01 00:00:00',$y,$m); $to=date('Y-m-d H:i:s',mktime(0,0,0,$m+1,1,$y));
    $s=th_db()->prepare("SELECT task, SUM(tokens) t FROM api_usage WHERE account_id=? AND created_at>=? AND created_at<? GROUP BY task"); $s->execute([$acc,$from,$to]);
    foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r){ $c=bl_task_channel((string)$r['task']); $row($c); $ch[$c]['platform_tokens']+=(int)$r['t']; }
    $tot=['calls'=>0,'byok_eur'=>0.0,'platform_ai_eur'=>0.0,'platform_tokens'=>0,'platform_tokens_eur'=>0.0,'model_tokens'=>0];
    foreach($ch as &$c){
        $c['platform_tokens_eur']=round($c['platform_tokens']*BL_TOKEN_EUR,2);
        $ai=$c['byok']['eur']+$c['platform_ai']['eur']; $c['cent_per_answer']=$c['calls'] ? round(100*$ai/$c['calls'],3) : null;   // echte KI-Kosten je Antwort (mit Prompt-Cache)
        $c['cache_share']=bl_cache_share($c['byok']['in']+$c['platform_ai']['in'],$c['byok']['cache_read']+$c['platform_ai']['cache_read'],$c['byok']['cache_write']+$c['platform_ai']['cache_write']);
        $c['byok']['eur']=round($c['byok']['eur'],4); $c['platform_ai']['eur']=round($c['platform_ai']['eur'],4);
        $tot['calls']+=$c['calls']; $tot['byok_eur']+=$c['byok']['eur']; $tot['platform_ai_eur']+=$c['platform_ai']['eur']; $tot['platform_tokens']+=$c['platform_tokens']; $tot['platform_tokens_eur']+=$c['platform_tokens_eur'];
        foreach(['byok','platform_ai'] as $k) $tot['model_tokens']+=$c[$k]['in']+$c[$k]['out']+$c[$k]['cache_read']+$c[$k]['cache_write'];
    } unset($c);
    $tot['cent_per_answer']=$tot['calls'] ? round(100*($tot['byok_eur']+$tot['platform_ai_eur'])/$tot['calls'],3) : null;
    foreach(['byok_eur','platform_ai_eur','platform_tokens_eur'] as $k) $tot[$k]=round($tot[$k],2);
    $pos=array_flip(array_keys(BL_CHANNEL_LABELS)); usort($ch,fn($a,$b)=>($pos[$a['channel']] ?? 99) <=> ($pos[$b['channel']] ?? 99));
    return ['month'=>$ym,'channels'=>array_values($ch),'totals'=>$tot,'fx_usd_eur'=>$fx,'token_eur'=>BL_TOKEN_EUR,
      'prices_usd_per_mtok'=>BL_AI_PRICES,'deepseek_offpeak_utc'=>BL_DEEPSEEK_OFFPEAK_UTC,
      'note'=>'Schätzung nach Listenpreisen der Anbieter (eigener Schlüssel: rechnet der Anbieter direkt mit dir ab). ChatHelp-Tokens: tatsächlich abgebucht, Wert zum Listenpreis '.number_format(BL_TOKEN_EUR,4,',','.').' €/Token.'];
}

/* ============================================================== Kontingent (Monats-Tokens der Abos) + Upgrade auf die nächste Stufe */
/** Annahmen für „~Stunden / ~Gespräche“ (Studio → Einstellungen des Betreibers, ch_settings 'assumptions'); nach 2–4 Wochen mit echten Werten nachziehen (ops/ai-costs). */
function bl_assumptions(): array {
    $a=function_exists('ch_setting') ? (array)ch_setting('assumptions',[]) : [];
    return ['answers_per_hour'=>max(20,min(600,(int)($a['answers_per_hour'] ?? 160))),'minutes_per_call'=>max(0.5,min(30,(float)($a['minutes_per_call'] ?? 2.5)))];
}
/** Tokens → geschätzte Antworten, Stunden und Gespräche bei $perAnswer Tokens je Antwort. */
function bl_tokens_time(int $tokens, int $perAnswer): array {
    $A=bl_assumptions(); $ans=$perAnswer>0 ? max(0,$tokens)/$perAnswer : 0; $h=$ans/$A['answers_per_hour'];
    return ['answers'=>(int)floor($ans),'hours'=>round($h,1),'calls'=>(int)round($h*60/$A['minutes_per_call'])];
}
/**
 * Kontingent des laufenden Abrechnungsmonats: vereinbarte Monats-Tokens der aktiven Abos (token_plans.tokens_month, sonst Katalog) gegen die seit
 * Periodenbeginn verbrauchten Tokens. null = kein Abo mit Monats-Tokens. Für Stufen-Agenten: nächste Stufe (Upgrade) und Zeit-Schätzung.
 */
function bl_quota_status(int $acc): ?array {
    if($acc<=0) return null;
    $s=th_db()->prepare("SELECT * FROM token_plans WHERE account_id=? AND stripe_sub IS NOT NULL AND status IN ('active','trialing','past_due') ORDER BY created_at"); $s->execute([$acc]);
    $inc=0; $end=null; $main=null;
    foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r){ $pl=ch_plan((string)$r['plan']); if(!$pl) continue;
        $t=isset($r['tokens_month']) && $r['tokens_month']!==null ? (int)$r['tokens_month'] : (int)$pl['tokens']; if($t<=0) continue;
        $inc+=$t; if($r['period_end'] && ($end===null || $r['period_end']<$end)) $end=(string)$r['period_end'];
        if(ch_plan_tier($pl['id']) && ($main===null || $t>$main['t'])) $main=['id'=>$pl['id'],'t'=>$t]; }
    if($inc<=0) return null;
    $tz=new DateTimeZone(date_default_timezone_get());
    $start=$end ? (new DateTimeImmutable($end,$tz))->modify('-1 month')->format('Y-m-d H:i:s') : date('Y-m-01 00:00:00');
    $u=th_db()->prepare("SELECT COALESCE(-SUM(delta),0) FROM token_ledger WHERE account_id=? AND delta<0 AND created_at>=?"); $u->execute([$acc,$start]); $used=(int)$u->fetchColumn();
    $plan=$main['id'] ?? null; $rate=$plan ? ch_phone_turn_tokens($acc,$plan) : (int)CH_PRICING['agent_turn'];
    $tier=$plan ? ch_plan_tier($plan) : null; $next=$tier['next'] ?? null; $np=$next ? ch_plan($next) : null;
    return ['included'=>$inc,'used'=>$used,'pct'=>(int)floor(100*$used/$inc),'period_start'=>$start,'period_end'=>$end,'plan'=>$plan,'tier'=>$tier['key'] ?? null,'rate'=>$rate,
        'time'=>bl_tokens_time($inc,$rate),'time_used'=>bl_tokens_time($used,$rate),'assumptions'=>bl_assumptions(),
        'next'=>$np ? ['id'=>$np['id'],'name'=>$np['name'],'eur_month'=>$np['cents']/100,'tokens'=>$np['tokens'],'time'=>bl_tokens_time((int)$np['tokens'],$rate)] : null];
}
/** Nach jeder Abbuchung (th_charge): bei 80 % und 100 % des Kontingents einmal je Abrechnungsmonat E-Mail + Push, bei 80 % mit Upgrade-Vorschlag. */
function bl_quota_check(int $acc): void {
    static $seen=[]; if($acc<=0 || isset($seen[$acc])) return; $seen[$acc]=1;
    $q=bl_quota_status($acc); if(!$q || $q['pct']<80) return;
    $lvl=$q['pct']>=100 ? 100 : 80; $per=substr((string)$q['period_start'],0,10);
    th_db()->exec("CREATE TABLE IF NOT EXISTS quota_alerts (account_id INT NOT NULL, period CHAR(10) NOT NULL, level SMALLINT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(account_id,period,level)) ENGINE=InnoDB");
    $ins=th_db()->prepare("INSERT IGNORE INTO quota_alerts(account_id,period,level) VALUES(?,?,?)"); $ins->execute([$acc,$per,$lvl]); if($ins->rowCount()!==1) return;
    $h=fn($x)=>htmlspecialchars((string)$x,ENT_QUOTES,'UTF-8'); $nx=$q['next']; $url='https://chat-help.com/chat/studio.php#tokens';
    $title=$lvl>=100 ? 'Gesprächszeit dieses Monats aufgebraucht' : 'Schon 80 % deiner Gesprächszeit genutzt';
    $txt=$lvl>=100 ? 'Dein Monatskontingent ist verbraucht. Weitere Antworten gehen von zusätzlichem Guthaben ab – ohne Guthaben nimmt der Agent keine neuen Gespräche an.'
        : 'Du hast rund '.number_format($q['time_used']['hours'],1,',','.').' von ~'.number_format($q['time']['hours'],1,',','.').' Stunden genutzt.';
    if($nx) $txt.=' Mit „'.$nx['name'].'“ ('.number_format($nx['eur_month'],0,',','.').' €/Monat) hast du ~'.number_format($nx['time']['hours'],0,',','.').' Stunden im Monat – ein Klick im Studio, der Unterschied wird anteilig berechnet.';
    if(function_exists('ch_push')){ try{ ch_push($acc,'tokens_low',$title,$txt,'/chat/studio.php#tokens',['tag'=>'quota-'.$lvl,'urgency'=>$lvl>=100 ? 'high' : 'normal']); }catch(Throwable $e){} }
    $e=th_db()->prepare("SELECT email FROM token_accounts WHERE id=?"); $e->execute([$acc]); $to=(string)$e->fetchColumn();
    if($to!=='') th_mail($to,'ChatHelp: '.$title,'<div style="font-family:Inter,Arial,sans-serif;max-width:560px;margin:auto;color:#111"><h2 style="color:#b0801f">'.$h($title).'</h2><p>'.$h($txt).'</p>'
        .'<p><a href="'.$h($url).'" style="display:inline-block;background:#111;color:#fff;padding:12px 18px;border-radius:12px;text-decoration:none;font-weight:600">'.($nx ? 'Paket upgraden' : 'Zum Studio').' →</a></p>'
        .'<p style="color:#666;font-size:13px">Stunden sind Schätzwerte ('.$q['assumptions']['answers_per_hour'].' Antworten je Stunde).</p></div>',$title."\n\n".$txt."\n\n".$url);
}
/**
 * Upgrade auf eine größere Stufe desselben Agenten (Stripe-Abo ändern, Unterschied anteilig sofort berechnet): Preis der Stufe für neue Käufe,
 * vereinbarte Tokens in den Abo-Metadaten, anteilige Mehr-Tokens für den Rest der Periode sofort gutgeschrieben. Rückgabe ['ok',…] oder ['error','message'].
 */
function bl_plan_upgrade(int $acc, string $to): array {
    $tp=ch_plan($to); $tt=ch_plan_tier($to); if(!$tp || !$tt) return ['error'=>'plan','message'=>'Diese Stufe gibt es nicht.'];
    $ids=$tt['ids']; $s=th_db()->prepare("SELECT * FROM token_plans WHERE account_id=? AND stripe_sub IS NOT NULL AND status IN ('active','trialing','past_due') AND plan IN (".implode(',',array_fill(0,count($ids),'?')).") ORDER BY created_at DESC LIMIT 1");
    $s->execute(array_merge([$acc],$ids)); $row=$s->fetch(PDO::FETCH_ASSOC); if(!$row) return ['error'=>'no_subscription','message'=>'Für diesen Agenten gibt es kein laufendes Abo – bitte zuerst buchen.'];
    $from=ch_plan((string)$row['plan']); if(array_search($to,$ids,true)<=array_search($from['id'],$ids,true)) return ['error'=>'not_upgrade','message'=>'Das ist keine größere Stufe.'];
    $sk=defined('STRIPE_SK') ? STRIPE_SK : (defined('STRIPE_SECRET') ? STRIPE_SECRET : (defined('STRIPE_SECRET_KEY') ? STRIPE_SECRET_KEY : ''));
    if($sk==='') return ['error'=>'stripe','message'=>'Zahlungsanbieter nicht eingerichtet.'];
    $sub=bl_stripe_call('GET','subscriptions/'.rawurlencode((string)$row['stripe_sub']),$sk); $item=$sub['items']['data'][0] ?? null;
    if(($sub['_http'] ?? 0)>=300 || !$item) return ['error'=>'stripe','message'=>'Abo nicht gefunden – bitte Support kontaktieren.'];
    $cur=strtolower((string)($item['price']['currency'] ?? 'eur')); $amount=$cur==='eur' ? (int)$tp['cents'] : ch_price_local((int)$tp['cents'],strtoupper($cur));
    $prod=bl_stripe_call('POST','products',$sk,['name'=>'ChatHelp Agent: '.$tp['name']]); if(empty($prod['id'])) return ['error'=>'stripe','message'=>'Upgrade gerade nicht möglich – bitte später erneut.'];
    $oldTok=isset($row['tokens_month']) && $row['tokens_month']!==null ? (int)$row['tokens_month'] : (int)$from['tokens'];
    $up=bl_stripe_call('POST','subscriptions/'.rawurlencode((string)$row['stripe_sub']),$sk,['items[0][id]'=>$item['id'],'items[0][price_data][currency]'=>$cur,'items[0][price_data][product]'=>$prod['id'],
        'items[0][price_data][unit_amount]'=>$amount,'items[0][price_data][recurring][interval]'=>'month','items[0][price_data][tax_behavior]'=>'exclusive','proration_behavior'=>'always_invoice',
        'metadata[plan]'=>$to,'metadata[tokens]'=>(int)$tp['tokens'],'metadata[upgraded_from]'=>$from['id'],'metadata[type]'=>'plan','metadata[kind]'=>'agent']);
    if(($up['_http'] ?? 0)>=300) return ['error'=>'stripe','message'=>'Upgrade abgelehnt: '.mb_substr((string)($up['error']['message'] ?? 'unbekannt'),0,160)];
    $pe=!empty($up['current_period_end']) ? date('Y-m-d H:i:s',(int)$up['current_period_end']) : $row['period_end'];
    th_plan_upsert($acc,$to,(string)$row['stripe_sub'],(string)($up['status'] ?? $row['status']),$pe); th_plan_tokens((string)$row['stripe_sub'],(int)$tp['tokens']);
    $ps=(int)($up['current_period_start'] ?? 0); $pend=(int)($up['current_period_end'] ?? 0); $frac=($pend>$ps && $pend>time()) ? ($pend-time())/($pend-$ps) : 1.0;
    $extra=(int)max(0,round(((int)$tp['tokens']-$oldTok)*$frac)); $cr=$extra>0 ? th_credit($acc,$extra,'upgrade',$row['stripe_sub'].':'.$to) : false;
    return ['ok'=>true,'from'=>$from['id'],'plan'=>$to,'name'=>$tp['name'],'tokens_month'=>(int)$tp['tokens'],'credited'=>$cr ? $extra : 0,'amount_minor'=>$amount,'currency'=>$cur];
}

/* ============================================================== Stripe (Checkout + Webhook) */
function bl_stripe_base(): string { return (defined('CH_STRIPE_API') && CH_STRIPE_API) ? rtrim((string)CH_STRIPE_API,'/') : 'https://api.stripe.com/v1'; }
function bl_stripe_call(string $method, string $path, string $sk, array $params=[]): array {
    $ch=curl_init(bl_stripe_base().'/'.ltrim($path,'/')); $o=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_USERPWD=>$sk.':',CURLOPT_TIMEOUT=>20];
    if($method!=='GET'){ $o[CURLOPT_CUSTOMREQUEST]=$method; $o[CURLOPT_POSTFIELDS]=http_build_query($params); }
    curl_setopt_array($ch,$o); $raw=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    $j=json_decode((string)$raw,true); return (is_array($j) ? $j : [])+['_http'=>$code];
}
/**
 * Stripe-Checkout-Parameter für einen Plan (Abo, Einmalkauf, Betrieb, eigener Schlüssel). $email optional (für Betrieb/BYOK Pflicht:
 * die Lizenz muss zu diesem Konto gehören). Rückgabe ['params'=>[…]] oder ['error'=>…,'status'=>HTTP,'message'=>…].
 * Betrieb/BYOK während des enthaltenen Betriebs: Abo startet mit trial_end = Ende des Inklusiv-Zeitraums (keine doppelte Zahlung).
 */
function bl_checkout_plan_params(array $pl, string $email, string $site): array {
    $kind=$pl['kind']; $buy=$kind==='buy'; $run=in_array($kind,['betrieb','byok'],true);
    $p=['cancel_url'=>$site.'/#preise','allow_promotion_codes'=>'true','automatic_tax[enabled]'=>'true','tax_id_collection[enabled]'=>'true','line_items[0][quantity]'=>1];
    if($email!=='') $p['customer_email']=$email;
    $trialEnd=null;
    if($run){
        if($email==='') return ['error'=>'email_required','status'=>400,'message'=>'Betrieb und eigener KI-Schlüssel gehören zu einem Einmalkauf – bitte über das Studio buchen (Tokens & Abrechnung), damit die Lizenz deinem Konto zugeordnet wird.'];
        $s=th_db()->prepare("SELECT id FROM token_accounts WHERE email=?"); $s->execute([strtolower(trim($email))]); $aid=(int)($s->fetchColumn() ?: 0);
        if($aid<=0 || !bl_license_owned($aid,(string)$pl['license'])) return ['error'=>'license_required','status'=>409,'message'=>'Für „'.$pl['name'].'“ wird die Lizenz „'.(ch_plan((string)$pl['license'])['name'] ?? $pl['license']).'“ benötigt. Bitte zuerst den Einmalkauf abschließen oder den Monatsplan wählen.'];
        $lic=bl_license_row($aid,(string)$pl['license']);
        if($lic && $lic['status']==='active' && $lic['period_end']===null) return ['error'=>'lifetime_license','status'=>409,'message'=>'Deine Lizenz ist ein unbefristeter Bestandskauf – Betrieb ist bereits enthalten.'];
        if($lic && $lic['status']==='active' && $lic['period_end']){ $t=th_dbtime((string)$lic['period_end'],new DateTimeZone('UTC')); if($t && $t->getTimestamp()>time()+2*86400) $trialEnd=$t->getTimestamp(); }
    }
    $p['mode']=$buy ? 'payment' : 'subscription';
    $p['success_url']=$site.'/chat/studio.php?welcome='.$pl['id'].'&session_id={CHECKOUT_SESSION_ID}';
    $p['line_items[0][price_data][currency]']='eur';
    $term=($buy && isset($pl['term'])) ? $pl['term'] : null;   // Laufzeitwahl (CH_BUY_TERMS): Preis und Tage der gewählten Laufzeit
    $p['line_items[0][price_data][unit_amount]']=$term ? (int)$term['cents'] : $pl['cents'];
    if(!$buy) $p['line_items[0][price_data][recurring][interval]']='month'; else $p['invoice_creation[enabled]']='true';
    $p['line_items[0][price_data][tax_behavior]']='exclusive';
    $label=$buy ? 'Einmalkauf' : (['agent'=>'Agent','betrieb'=>'Betrieb','byok'=>'Plattform (eigener KI-Schlüssel)'][$kind] ?? 'Paket');
    $p['line_items[0][price_data][product_data][name]']='ChatHelp '.$label.': '.$pl['name'];
    $days=$term ? (int)$term['days'] : (function_exists('ch_buy_included_days') ? ch_buy_included_days((string)$pl['id']) : (defined('CH_BUY_INCLUDED_DAYS') ? (int)CH_BUY_INCLUDED_DAYS : 90));
    if($term) $p['line_items[0][price_data][product_data][name]'].=' – '.$term['label'];
    $desc=$buy ? $pl['desc'].' · Lizenz + '.number_format($pl['tokens'],0,',','.').' Tokens Startguthaben (verfallen nicht) · '.$days.' Tage Betrieb inklusive'
        : ($kind==='byok' ? $pl['desc'].' · monatlich kündbar' : $pl['desc'].' · inkl. '.number_format($pl['tokens'],0,',','.').' Tokens / Monat · monatlich kündbar');
    $p['line_items[0][price_data][product_data][description]']=mb_substr($desc,0,500);
    if($trialEnd) $p['subscription_data[trial_end]']=$trialEnd;
    foreach($buy ? ['metadata'] : ['metadata','subscription_data[metadata]'] as $m){
        $p[$m.'[type]']='plan'; $p[$m.'[plan]']=$pl['id']; $p[$m.'[tokens]']=$pl['tokens']; $p[$m.'[kind]']=$kind;
        if($run) $p[$m.'[license]']=(string)$pl['license'];
        if($term){ $p[$m.'[term]']=(int)$term['term']; $p[$m.'[days]']=$days; }
    }
    return ['params'=>$p];
}
