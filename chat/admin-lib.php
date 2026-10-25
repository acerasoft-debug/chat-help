<?php
/**
 * ChatHelp — Betreiber-Bereich (nur CH_ADMIN_EMAILS, bl_is_admin)
 * =================================================================
 *   GET  admin/overview?days=7|30|90   Konten (neu, aktiv, zahlend), Umsatz (Zahlungseingänge je Währung, MRR nach Katalogpreis), Kosten (KI, Stimme, Spracherkennung),
 *                                      Nutzung der Plattform (Antworten je Kanal, Anrufe, Vorgänge), Verlauf je Tag, Systemzustand (Schlüssel, Auffälligkeiten)
 *   GET  admin/accounts?q=&filter=&sort=&limit=&offset=   Kontenliste mit Paketen, Guthaben, Nutzung, letzter Aktivität
 *   GET  admin/account?id=             ein Konto: Pakete, Einstellungen, Nummern, Einrichtung, Nutzung, KI-Kosten, Token-Buch, Zahlungen, Notiz, Protokoll (keine Gesprächsinhalte)
 *   POST admin/action {id, action, …}  credit|debit {amount, note} · extend {plan_row, days} · grant {plan, days} · cancel {plan_row} · note {note}
 *   GET  admin/log                     letzte Betreiber-Aktionen
 * admin/settings (Annahmen, Telefon-Grenzen, Preise) bleibt in tokens-api.php.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';

const ADM_DAYS = [7,30,90];
const ADM_FILTERS = ['all','paying','trial','active','inactive','low','onboarding','admin_credit'];
const ADM_SORTS = ['created','last','balance','usage','email','revenue'];
const ADM_LOW_BALANCE = 200;

function adm_bootstrap(): void {
    static $done=false; if($done) return; $done=true; $p=th_db();
    $p->exec("CREATE TABLE IF NOT EXISTS admin_log (id INT AUTO_INCREMENT PRIMARY KEY, admin_id INT NOT NULL, account_id INT NOT NULL DEFAULT 0, action VARCHAR(20) NOT NULL,
        detail VARCHAR(500) NOT NULL DEFAULT '', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX(account_id), INDEX(created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS admin_notes (account_id INT NOT NULL PRIMARY KEY, note TEXT NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS payments (id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, kind VARCHAR(12) NOT NULL, plan VARCHAR(40) NOT NULL DEFAULT '',
        amount_cents INT NOT NULL DEFAULT 0, currency CHAR(3) NOT NULL DEFAULT 'EUR', ref VARCHAR(120) NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_ref (ref), INDEX(account_id), INDEX(created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function adm_q(string $sql, array $a=[]): array { try{ $s=th_db()->prepare($sql); $s->execute($a); return $s->fetchAll(PDO::FETCH_ASSOC); }catch(Throwable $e){ return []; } }
function adm_one(string $sql, array $a=[]): ?array { $r=adm_q($sql,$a); return $r[0] ?? null; }
function adm_val(string $sql, array $a=[]): int { $r=adm_one($sql,$a); return $r ? (int)reset($r) : 0; }
function adm_iso(?string $db): ?string { return $db ? th_dbiso($db) : null; }
/** Betreiber-E-Mail? (gleiche Liste wie bl_is_admin, ohne Datenbankabfrage je Konto) */
function adm_is_admin_email(string $email): bool {
    static $l=null; if($l===null){ $l=defined('CH_ADMIN_EMAILS') ? (is_array(CH_ADMIN_EMAILS) ? CH_ADMIN_EMAILS : explode(',',(string)CH_ADMIN_EMAILS)) : []; $l=array_filter(array_map(fn($x)=>strtolower(trim((string)$x)),$l)); }
    $e=strtolower(trim($email)); return $e!=='' && (in_array($e,$l,true) || in_array('sha256:'.hash('sha256',$e),$l,true));
}
function adm_log(int $admin, int $acc, string $action, string $detail): void {
    try{ adm_bootstrap(); th_db()->prepare("INSERT INTO admin_log(admin_id,account_id,action,detail) VALUES(?,?,?,?)")->execute([$admin,$acc,substr($action,0,20),mb_substr($detail,0,500)]); }catch(Throwable $e){ error_log('CH adm_log: '.$e->getMessage()); }
}
/** Monatlicher Katalogpreis eines laufenden Abos (Cent) – Einmalkäufe 0. */
function adm_month_cents(string $plan): int {
    $pl=ch_plan($plan); if(!$pl || $pl['kind']==='buy') return 0; return (int)$pl['cents'];
}
/** Zahlende Abos (Stripe, aktiv/überfällig) und Probezeiten. */
function adm_subs(): array {
    $o=['paying'=>[],'trialing'=>[]];
    foreach(adm_q("SELECT id,account_id,plan,status,period_end FROM token_plans WHERE stripe_sub IS NOT NULL AND status IN ('active','past_due','trialing')") as $r) $o[$r['status']==='trialing' ? 'trialing' : 'paying'][]=$r;
    return $o;
}
/** Kurzfassung der API-Schlüssel (nur vorhanden ja/nein – nie Werte). */
function adm_keys(): array {
    $d=fn(string $c)=>defined($c) && (string)constant($c)!=='';
    if(!function_exists('ch_tts_creds') && is_file(__DIR__.'/speech-lib.php')) @require_once __DIR__.'/speech-lib.php';
    if(!function_exists('stt_key') && is_file(__DIR__.'/stt-lib.php')) @require_once __DIR__.'/stt-lib.php';
    $tts=fn(string $p)=>function_exists('ch_tts_creds') ? (bool)ch_tts_creds($p) : false;
    return ['ai'=>$d('CLAUDE_KEY'),'ai_backup'=>$d('DEEPSEEK_KEY') || $d('DEEPSEEK_EU_KEY'),'voice_premium'=>$tts('elevenlabs'),'voice_standard'=>$tts('azure') || $d('AZURE_SPEECH_KEY'),
        'stt'=>function_exists('stt_key') ? stt_key('deepgram')!=='' : $d('DEEPGRAM_KEY'),'phone'=>$d('TWILIO_ACCOUNT_SID') && $d('TWILIO_AUTH_TOKEN'),
        'payments'=>$d('STRIPE_SK') || $d('STRIPE_SECRET') || $d('STRIPE_SECRET_KEY'),'payments_webhook'=>$d('STRIPE_TOKENS_WEBHOOK_SECRET') || $d('STRIPE_WEBHOOK_SECRET'),
        'mail'=>$d('SMTP_HOST') || $d('SMTP_FROM'),'push'=>$d('VAPID_PRIVATE_PEM') || $d('FCM_SERVICE_ACCOUNT_JSON') || $d('FCM_SERVICE_ACCOUNT_FILE'),'maps'=>$d('GOOGLE_PLACES_KEY')];
}
function adm_win(string $col, int $from, int $to=0): string { return "$col > (NOW() - INTERVAL $from DAY)".($to>0 ? " AND $col <= (NOW() - INTERVAL $to DAY)" : ''); }

/* ======================================================================= Übersicht */
function adm_overview(int $days): array {
    adm_bootstrap(); $W=fn(string $c)=>adm_win($c,$days); $P=fn(string $c)=>adm_win($c,2*$days,$days);
    $subs=adm_subs(); $payAcc=array_unique(array_map(fn($r)=>(int)$r['account_id'],$subs['paying']));
    $mrr=0; $byPlan=[]; $byKind=[];
    foreach($subs['paying'] as $r){ $c=adm_month_cents((string)$r['plan']); $mrr+=$c; $pl=ch_plan((string)$r['plan']); $k=(string)($pl['kind'] ?? '?');
        $byPlan[$r['plan']]??=['plan'=>(string)$r['plan'],'name'=>(string)($pl['name'] ?? $r['plan']),'n'=>0,'mrr_cents'=>0]; $byPlan[$r['plan']]['n']++; $byPlan[$r['plan']]['mrr_cents']+=$c;
        $byKind[$k]??=['kind'=>$k,'n'=>0,'mrr_cents'=>0]; $byKind[$k]['n']++; $byKind[$k]['mrr_cents']+=$c; }
    usort($byPlan,fn($a,$b)=>[$b['mrr_cents'],$b['n']]<=>[$a['mrr_cents'],$a['n']]);
    $licenses=adm_val("SELECT COUNT(*) FROM token_plans WHERE plan LIKE 'buy-%' AND status IN ('active','trialing','past_due')");
    $pay=function(string $w): array { $o=[]; foreach(adm_q("SELECT currency, kind, COUNT(*) n, SUM(amount_cents) c FROM payments WHERE $w GROUP BY currency, kind") as $r){ $cu=(string)$r['currency'];
        $o[$cu]??=['currency'=>$cu,'cents'=>0,'n'=>0,'by'=>[]]; $o[$cu]['cents']+=(int)$r['c']; $o[$cu]['n']+=(int)$r['n']; $o[$cu]['by'][$r['kind']]=(int)$r['c']; } return array_values($o); };
    $acc=['total'=>adm_val("SELECT COUNT(*) FROM token_accounts"),'new'=>adm_val("SELECT COUNT(*) FROM token_accounts WHERE ".$W('created_at')),'new_prev'=>adm_val("SELECT COUNT(*) FROM token_accounts WHERE ".$P('created_at')),
        'active'=>adm_val("SELECT COUNT(DISTINCT account_id) FROM api_usage WHERE account_id>0 AND ".$W('created_at')),'active_prev'=>adm_val("SELECT COUNT(DISTINCT account_id) FROM api_usage WHERE account_id>0 AND ".$P('created_at')),
        'paying'=>count($payAcc),'trialing'=>count(array_unique(array_map(fn($r)=>(int)$r['account_id'],$subs['trialing']))),'licenses'=>$licenses,
        'churned'=>adm_val("SELECT COUNT(*) FROM token_plans WHERE stripe_sub IS NOT NULL AND status IN ('canceled','unpaid','incomplete_expired') AND ".$W('updated_at'))];

    /* Nutzung der Plattform */
    $chan=function(string $w): array { $o=['phone'=>0,'whatsapp'=>0,'web'=>0,'studio'=>0,'intern'=>0]; foreach(adm_q("SELECT task, COUNT(*) n FROM api_usage WHERE $w GROUP BY task") as $r){
        $t=(string)$r['task']; $c=function_exists('dash_chan') ? dash_chan($t) : null; $o[$c ?? 'intern']+=(int)$r['n']; } return $o; };
    $calls=adm_one("SELECT COUNT(*) n, COALESCE(SUM(GREATEST(0,TIMESTAMPDIFF(SECOND,started_at,COALESCE(ended_at,started_at)))),0) s FROM phone_calls WHERE ".$W('started_at'));
    $callsP=adm_one("SELECT COUNT(*) n, COALESCE(SUM(GREATEST(0,TIMESTAMPDIFF(SECOND,started_at,COALESCE(ended_at,started_at)))),0) s FROM phone_calls WHERE ".$P('started_at'));
    $mods=[]; foreach(['order'=>'orders','appt'=>'appt_bookings','ride'=>'rides','hotel'=>'hotel_bookings','hotel_request'=>'hotel_requests','callback'=>'callbacks'] as $k=>$t)
        $mods[]=['kind'=>$k,'n'=>adm_val("SELECT COUNT(*) FROM $t WHERE ".$W('created_at')),'prev'=>adm_val("SELECT COUNT(*) FROM $t WHERE ".$P('created_at'))];
    $tok=adm_one("SELECT COALESCE(SUM(CASE WHEN delta>0 AND reason LIKE 'stripe:%' THEN delta END),0) sold, COALESCE(SUM(CASE WHEN delta>0 AND reason NOT LIKE 'stripe:%' THEN delta END),0) free, COALESCE(-SUM(CASE WHEN delta<0 THEN delta END),0) used FROM token_ledger WHERE ".$W('created_at'));

    /* Kosten: KI (echte Nutzung, Listenpreise), Stimme (Zeichen diesen Monat), Spracherkennung (Sekunden im Zeitraum) */
    $from=date('Y-m-d',strtotime('-'.($days-1).' day')); $to=date('Y-m-d',strtotime('+1 day')); $pfrom=date('Y-m-d',strtotime('-'.(2*$days-1).' day'));
    $ai=function_exists('bl_answer_costs') ? bl_answer_costs($from,$to) : null; $aiP=function_exists('bl_answer_costs') ? bl_answer_costs($pfrom,$from) : null;
    $tts=[]; foreach(adm_q("SELECT provider, channel, chars FROM tts_usage WHERE ym=? AND channel<>'blocked'",[gmdate('Y-m')]) as $r){ $tts[$r['provider']]??=['provider'=>(string)$r['provider'],'chars'=>0,'by'=>[]]; $tts[$r['provider']]['chars']+=(int)$r['chars']; $tts[$r['provider']]['by'][$r['channel']]=(int)$r['chars']; }
    foreach($tts as $p=>&$x){ $x['budget']=function_exists('ch_tts_budget') ? ch_tts_budget($p) : null; } unset($x);
    $stt=[]; foreach(adm_q("SELECT provider, SUM(calls) c, SUM(seconds) s FROM stt_usage_day WHERE d>=? GROUP BY provider",[$from]) as $r){ $sec=(float)$r['s'];
        $stt[]=['provider'=>(string)$r['provider'],'calls'=>(int)$r['c'],'minutes'=>round($sec/60,1),'usd'=>function_exists('stt_cost_usd') ? round(stt_cost_usd((string)$r['provider'],$sec),2) : null]; }

    /* Verlauf je Tag: Anmeldungen, Antworten an Kunden, Anrufe, KI-Kosten */
    $series=[]; for($i=$days-1;$i>=0;$i--){ $d=date('Y-m-d',strtotime("-$i day")); $series[$d]=['d'=>$d,'signups'=>0,'answers'=>0,'calls'=>0,'ai_eur'=>0.0,'payments_cents'=>0]; }
    foreach(adm_q("SELECT DATE(created_at) d, COUNT(*) n FROM token_accounts WHERE ".$W('created_at')." GROUP BY d") as $r) if(isset($series[$r['d']])) $series[$r['d']]['signups']=(int)$r['n'];
    foreach(adm_q("SELECT DATE(created_at) d, task, COUNT(*) n FROM api_usage WHERE ".$W('created_at')." GROUP BY d, task") as $r){ $c=function_exists('dash_chan') ? dash_chan((string)$r['task']) : null; if(in_array($c,['phone','whatsapp','web'],true) && isset($series[$r['d']])) $series[$r['d']]['answers']+=(int)$r['n']; }
    foreach(adm_q("SELECT DATE(started_at) d, COUNT(*) n FROM phone_calls WHERE ".$W('started_at')." GROUP BY d") as $r) if(isset($series[$r['d']])) $series[$r['d']]['calls']=(int)$r['n'];
    foreach(adm_q("SELECT DATE(created_at) d, SUM(amount_cents) c FROM payments WHERE currency='EUR' AND ".$W('created_at')." GROUP BY d") as $r) if(isset($series[$r['d']])) $series[$r['d']]['payments_cents']=(int)$r['c'];
    if(function_exists('bl_cost_usd')){ $fx=bl_usd_eur(); foreach(adm_q("SELECT d, model, peak, SUM(in_tok) i, SUM(out_tok) o, SUM(cache_read) cr, SUM(cache_write) cw FROM ai_usage_day WHERE d>=? AND byok=0 GROUP BY d, model, peak",[$from]) as $r)
        if(isset($series[$r['d']])) $series[$r['d']]['ai_eur']+=round(bl_cost_usd((string)$r['model'],(bool)(int)$r['peak'],(int)$r['i'],(int)$r['o'],(int)$r['cr'],(int)$r['cw'])*$fx,4); }

    /* Auffälligkeiten */
    $low=[]; foreach(adm_q("SELECT a.id, a.email, (SELECT COALESCE(SUM(l.delta),0) FROM token_ledger l WHERE l.account_id=a.id) bal FROM token_accounts a WHERE a.id IN (SELECT DISTINCT account_id FROM token_plans WHERE status IN ('active','trialing','past_due')) HAVING bal < ".ADM_LOW_BALANCE." ORDER BY bal LIMIT 20") as $r) $low[]=['id'=>(int)$r['id'],'email'=>(string)$r['email'],'balance'=>(int)$r['bal']];
    $issues=['low_balance'=>$low,
        'onboarding_stale'=>adm_val("SELECT COUNT(*) FROM onboardings WHERE status='open' AND created_at < (NOW() - INTERVAL 2 DAY)"),
        'callbacks_failed'=>adm_val("SELECT COUNT(*) FROM callbacks WHERE status='failed' AND updated_at > (NOW() - INTERVAL 7 DAY)"),
        'calls_stuck'=>adm_val("SELECT COUNT(*) FROM phone_calls WHERE ended_at IS NULL AND started_at < (NOW() - INTERVAL 2 HOUR) AND started_at > (NOW() - INTERVAL 7 DAY)"),
        'past_due'=>adm_val("SELECT COUNT(*) FROM token_plans WHERE status='past_due'"),
        'quota_alerts'=>adm_val("SELECT COUNT(*) FROM quota_alerts WHERE created_at > (NOW() - INTERVAL 7 DAY)"),
        'numbers_active'=>adm_val("SELECT COUNT(*) FROM phone_numbers WHERE status='active'")];
    $aiEur=$ai ? (float)$ai['total']['eur'] : 0.0;
    return ['days'=>$days,'generated_at'=>gmdate('Y-m-d\TH:i:s\Z'),'accounts'=>$acc,
        'revenue'=>['mrr_cents'=>$mrr,'arr_cents'=>$mrr*12,'by_kind'=>array_values($byKind),'top_plans'=>array_slice($byPlan,0,10),'payments'=>$pay($W('created_at')),'payments_prev'=>$pay($P('created_at')),
            'arpa_cents'=>count($payAcc) ? (int)round($mrr/count($payAcc)) : 0],
        'usage'=>['answers'=>$chan($W('created_at')),'answers_prev'=>$chan($P('created_at')),'calls'=>['n'=>(int)($calls['n'] ?? 0),'min'=>(int)round(((int)($calls['s'] ?? 0))/60),'prev'=>(int)($callsP['n'] ?? 0),'prev_min'=>(int)round(((int)($callsP['s'] ?? 0))/60)],
            'actions'=>$mods,'tokens'=>['sold'=>(int)($tok['sold'] ?? 0),'free'=>(int)($tok['free'] ?? 0),'used'=>(int)($tok['used'] ?? 0)],
            'web_chats'=>adm_val("SELECT COUNT(*) FROM widget_chats WHERE ".$W('updated_at'))],
        'costs'=>['ai'=>$ai ? ['eur'=>$aiEur,'answers'=>(int)$ai['total']['answers'],'cent_per_answer'=>$ai['total']['cent_per_answer'],'cache_share'=>$ai['total']['cache_share'],'channels'=>$ai['channels']] : null,
            'ai_prev_eur'=>$aiP ? (float)$aiP['total']['eur'] : null,'tts_month'=>array_values($tts),'stt'=>$stt,'margin_hint'=>$mrr>0 ? round(1-($aiEur*30/$days*100)/$mrr,3) : null],
        'series'=>array_values($series),'issues'=>$issues,'keys'=>adm_keys(),
        'system'=>['php'=>PHP_VERSION,'db'=>(string)(adm_one("SELECT VERSION() v")['v'] ?? ''),'time_utc'=>gmdate('Y-m-d H:i'),'version'=>is_file(__DIR__.'/pagever.php') ? substr(md5((string)@filemtime(__DIR__.'/studio.php')),0,8) : '']];
}

/* ======================================================================= Konten */
function adm_accounts(array $f): array {
    adm_bootstrap();
    $q=trim(mb_substr((string)($f['q'] ?? ''),0,120)); $filter=in_array($f['filter'] ?? '',ADM_FILTERS,true) ? $f['filter'] : 'all'; $sort=in_array($f['sort'] ?? '',ADM_SORTS,true) ? $f['sort'] : 'created';
    $lim=max(1,min(200,(int)($f['limit'] ?? 50) ?: 50)); $off=max(0,(int)($f['offset'] ?? 0));
    $w=''; $a=[]; if($q!==''){ if(ctype_digit($q)){ $w=' WHERE (a.id=? OR a.email LIKE ?)'; $a=[(int)$q,'%'.$q.'%']; } else { $w=' WHERE a.email LIKE ?'; $a=['%'.$q.'%']; } }
    $rows=adm_q("SELECT a.id, a.email, a.created_at,
        (SELECT COALESCE(SUM(l.delta),0) FROM token_ledger l WHERE l.account_id=a.id) bal,
        (SELECT MAX(u.created_at) FROM api_usage u WHERE u.account_id=a.id) last_use,
        (SELECT COUNT(*) FROM api_usage u WHERE u.account_id=a.id AND u.created_at > (NOW() - INTERVAL 30 DAY)) use30,
        (SELECT COUNT(*) FROM phone_calls c WHERE c.account_id=a.id AND c.started_at > (NOW() - INTERVAL 30 DAY)) calls30
        FROM token_accounts a$w ORDER BY a.id DESC LIMIT 5000",$a);
    if(!$rows) return ['total'=>0,'items'=>[],'counts'=>array_fill_keys(ADM_FILTERS,0),'offset'=>$off,'more'=>false];
    $ids=array_map(fn($r)=>(int)$r['id'],$rows); $in=implode(',',$ids);
    $plans=[]; foreach(adm_q("SELECT account_id, plan, status, stripe_sub IS NOT NULL paid, period_end FROM token_plans WHERE account_id IN ($in) ORDER BY id") as $r) $plans[(int)$r['account_id']][]=$r;
    $company=[]; foreach(adm_q("SELECT account_id, company, country FROM account_profile WHERE account_id IN ($in)") as $r) $company[(int)$r['account_id']]=['company'=>(string)$r['company'],'country'=>(string)$r['country']];
    foreach(adm_q("SELECT account_id, MAX(company) company FROM agent_configs WHERE account_id IN ($in) AND company<>'' GROUP BY account_id") as $r){ $x=$company[(int)$r['account_id']] ?? ['company'=>'','country'=>'']; if($x['company']==='') $x['company']=(string)$r['company']; $company[(int)$r['account_id']]=$x; }
    $onb=[]; foreach(adm_q("SELECT account_id, SUM(status='open') o, SUM(status='done') d FROM onboardings WHERE account_id IN ($in) GROUP BY account_id") as $r) $onb[(int)$r['account_id']]=['open'=>(int)$r['o'],'done'=>(int)$r['d']];
    $rev=[]; foreach(adm_q("SELECT account_id, SUM(amount_cents) c FROM payments WHERE account_id IN ($in) AND currency='EUR' GROUP BY account_id") as $r) $rev[(int)$r['account_id']]=(int)$r['c'];
    $adminCr=[]; foreach(adm_q("SELECT DISTINCT account_id FROM token_ledger WHERE account_id IN ($in) AND reason LIKE 'admin:%'") as $r) $adminCr[(int)$r['account_id']]=true;
    $items=[]; $cnt=array_fill_keys(ADM_FILTERS,0);
    foreach($rows as $r){ $id=(int)$r['id']; $pl=$plans[$id] ?? [];
        $act=array_values(array_filter($pl,fn($p)=>in_array($p['status'],['active','trialing','past_due'],true)));
        $paying=(bool)array_filter($act,fn($p)=>(int)$p['paid']===1 && $p['status']!=='trialing'); $trial=!$paying;
        $mrr=array_sum(array_map(fn($p)=>(int)$p['paid']===1 && $p['status']!=='trialing' ? adm_month_cents((string)$p['plan']) : 0,$act));
        $last=$r['last_use'] ? th_dbiso((string)$r['last_use']) : null; $use=(int)$r['use30'];
        $it=['id'=>$id,'email'=>(string)$r['email'],'created_at'=>adm_iso((string)$r['created_at']),'balance'=>(int)$r['bal'],'last_active'=>$last,'use30'=>$use,'calls30'=>(int)$r['calls30'],
            'plans'=>array_map(fn($p)=>['id'=>(string)$p['plan'],'name'=>(string)(ch_plan((string)$p['plan'])['name'] ?? $p['plan']),'status'=>(string)$p['status'],'paid'=>(int)$p['paid']===1],$act),
            'paying'=>$paying,'mrr_cents'=>$mrr,'revenue_cents'=>$rev[$id] ?? 0,'company'=>$company[$id]['company'] ?? '','country'=>$company[$id]['country'] ?? '','onboarding'=>$onb[$id] ?? null,'admin'=>adm_is_admin_email((string)$r['email'])];
        $tags=['all'=>true,'paying'=>$paying,'trial'=>$trial,'active'=>$use>0,'inactive'=>$use===0,'low'=>$act && (int)$r['bal']<ADM_LOW_BALANCE,'onboarding'=>!empty($onb[$id]['open']),'admin_credit'=>!empty($adminCr[$id])];
        foreach($tags as $k=>$v) if($v) $cnt[$k]++;
        if(!$tags[$filter]) continue; $items[]=$it; }
    $key=['created'=>fn($x)=>$x['id'],'last'=>fn($x)=>(string)$x['last_active'],'balance'=>fn($x)=>$x['balance'],'usage'=>fn($x)=>$x['use30'],'email'=>fn($x)=>$x['email'],'revenue'=>fn($x)=>[$x['mrr_cents'],$x['revenue_cents']]][$sort];
    usort($items,fn($x,$y)=>$sort==='email' ? strcmp($key($x),$key($y)) : ($key($y) <=> $key($x)));
    return ['total'=>count($items),'counts'=>$cnt,'offset'=>$off,'items'=>array_slice($items,$off,$lim),'more'=>$off+$lim<count($items)];
}
function adm_account(int $id): ?array {
    adm_bootstrap(); $a=adm_one("SELECT id,email,created_at FROM token_accounts WHERE id=?",[$id]); if(!$a) return null;
    $prof=adm_one("SELECT kind,company,sectors,size,city,country,needs,langs,created_at,updated_at FROM account_profile WHERE account_id=?",[$id]);
    $plans=array_map(function($r){ $pl=ch_plan((string)$r['plan']);
        return ['row'=>(int)$r['id'],'id'=>(string)$r['plan'],'name'=>(string)($pl['name'] ?? $r['plan']),'kind'=>(string)($pl['kind'] ?? ''),'status'=>(string)$r['status'],'paid'=>$r['stripe_sub']!==null,
            'period_end'=>adm_iso($r['period_end']),'created_at'=>adm_iso($r['created_at']),'tokens_month'=>$r['tokens_month']!==null ? (int)$r['tokens_month'] : ($pl['tokens'] ?? null),'month_cents'=>$r['stripe_sub']!==null ? adm_month_cents((string)$r['plan']) : 0]; },
        adm_q("SELECT id,plan,status,stripe_sub,period_end,created_at,tokens_month FROM token_plans WHERE account_id=? ORDER BY id DESC",[$id]));
    $cfg=array_map(fn($r)=>['plan'=>(string)$r['plan'],'name'=>(string)(ch_plan((string)$r['plan'])['name'] ?? $r['plan']),'company'=>(string)$r['company'],'lang'=>(string)$r['lang'],'langs'=>(string)$r['langs'],'widget_on'=>(int)$r['widget_on']===1,
        'widget_domain'=>(string)$r['widget_domain'],'phone_on'=>(int)$r['phone_on']===1,'phone_number'=>(string)$r['phone_number'],'kb_chars'=>(int)$r['kb_len'],'updated_at'=>adm_iso($r['updated_at'])],
        adm_q("SELECT plan,company,lang,langs,widget_on,widget_domain,phone_on,phone_number,CHAR_LENGTH(COALESCE(kb,'')) kb_len,updated_at FROM agent_configs WHERE account_id=? ORDER BY updated_at DESC",[$id]));
    $nums=array_map(fn($r)=>['e164'=>(string)$r['e164'],'country'=>(string)$r['country'],'status'=>(string)$r['status'],'created_at'=>adm_iso($r['created_at'])],adm_q("SELECT e164,country,status,created_at FROM phone_numbers WHERE account_id=? ORDER BY id DESC",[$id]));
    $onb=array_map(fn($r)=>['id'=>(int)$r['id'],'plan'=>(string)$r['plan'],'status'=>(string)$r['status'],'created_at'=>adm_iso($r['created_at']),'finished_at'=>adm_iso($r['finished_at'])],adm_q("SELECT id,plan,status,created_at,finished_at FROM onboardings WHERE account_id=? ORDER BY id DESC LIMIT 10",[$id]));
    $use=['phone'=>0,'whatsapp'=>0,'web'=>0,'studio'=>0,'intern'=>0]; $tok30=0;
    foreach(adm_q("SELECT task, COUNT(*) n, COALESCE(SUM(tokens),0) t FROM api_usage WHERE account_id=? AND created_at > (NOW() - INTERVAL 30 DAY) GROUP BY task",[$id]) as $r){ $c=function_exists('dash_chan') ? dash_chan((string)$r['task']) : null; $use[$c ?? 'intern']+=(int)$r['n']; $tok30+=(int)$r['t']; }
    $calls=adm_one("SELECT COUNT(*) n, COALESCE(SUM(GREATEST(0,TIMESTAMPDIFF(SECOND,started_at,COALESCE(ended_at,started_at)))),0) s FROM phone_calls WHERE account_id=? AND started_at > (NOW() - INTERVAL 30 DAY)",[$id]);
    $acts=function_exists('dash_actions') ? dash_actions($id,30) : [];
    $ai=[]; if(function_exists('bl_cost_usd')){ $fx=bl_usd_eur(); foreach(adm_q("SELECT ym, byok, SUM(calls) c, model, peak, SUM(in_tok) i, SUM(out_tok) o, SUM(cache_read) cr, SUM(cache_write) cw FROM ai_usage WHERE account_id=? GROUP BY ym, byok, model, peak ORDER BY ym DESC",[$id]) as $r){
        $k=$r['ym'].((int)$r['byok'] ? ':byok' : ''); $ai[$k]??=['ym'=>(string)$r['ym'],'byok'=>(int)$r['byok']===1,'calls'=>0,'eur'=>0.0];
        $ai[$k]['calls']+=(int)$r['c']; $ai[$k]['eur']+=bl_cost_usd((string)$r['model'],(bool)(int)$r['peak'],(int)$r['i'],(int)$r['o'],(int)$r['cr'],(int)$r['cw'])*$fx; }
        $ai=array_slice(array_values(array_map(function($x){ $x['eur']=round($x['eur'],2); return $x; },$ai)),0,6); }
    $ledger=array_map(fn($r)=>['id'=>(int)$r['id'],'delta'=>(int)$r['delta'],'reason'=>(string)$r['reason'],'created_at'=>adm_iso($r['created_at'])],
        adm_q("SELECT id,delta,reason,created_at FROM token_ledger WHERE account_id=? AND (delta>0 OR reason NOT REGEXP '^(widget|phone|whatsapp|agent|phone-test|booking|assistant):') ORDER BY id DESC LIMIT 40",[$id]));
    $byReason=array_map(fn($r)=>['reason'=>(string)$r['r'],'n'=>(int)$r['n'],'tokens'=>(int)$r['t']],adm_q("SELECT SUBSTRING_INDEX(reason,':',1) r, COUNT(*) n, SUM(delta) t FROM token_ledger WHERE account_id=? AND created_at > (NOW() - INTERVAL 30 DAY) GROUP BY r ORDER BY t",[$id]));
    $pays=array_map(fn($r)=>['kind'=>(string)$r['kind'],'plan'=>(string)$r['plan'],'cents'=>(int)$r['amount_cents'],'currency'=>(string)$r['currency'],'created_at'=>adm_iso($r['created_at'])],adm_q("SELECT kind,plan,amount_cents,currency,created_at FROM payments WHERE account_id=? ORDER BY id DESC LIMIT 30",[$id]));
    $log=array_map(fn($r)=>['action'=>(string)$r['action'],'detail'=>(string)$r['detail'],'by'=>(string)($r['email'] ?? ''),'created_at'=>adm_iso($r['created_at'])],adm_q("SELECT l.action,l.detail,l.created_at,a.email FROM admin_log l LEFT JOIN token_accounts a ON a.id=l.admin_id WHERE l.account_id=? ORDER BY l.id DESC LIMIT 30",[$id]));
    $last=adm_one("SELECT MAX(created_at) m FROM api_usage WHERE account_id=?",[$id]);
    return ['account'=>['id'=>(int)$a['id'],'email'=>(string)$a['email'],'created_at'=>adm_iso($a['created_at']),'last_active'=>adm_iso($last['m'] ?? null),'balance'=>th_balance_ledger($id),'admin'=>bl_is_admin($id),
            'keys'=>adm_val("SELECT COUNT(*) FROM api_keys WHERE account_id=? AND active=1",[$id]),'byok'=>function_exists('bl_byok_usable') ? (bool)bl_byok_usable($id) : false],
        'profile'=>$prof,'plans'=>$plans,'configs'=>$cfg,'numbers'=>$nums,'onboardings'=>$onb,
        'usage30'=>['answers'=>$use,'tokens'=>$tok30,'calls'=>(int)($calls['n'] ?? 0),'call_min'=>(int)round(((int)($calls['s'] ?? 0))/60),'actions'=>$acts],
        'ai_cost'=>$ai,'ledger'=>$ledger,'ledger_30d'=>$byReason,'payments'=>$pays,'note'=>(string)(adm_one("SELECT note FROM admin_notes WHERE account_id=?",[$id])['note'] ?? ''),'log'=>$log,
        'grantable'=>array_values(array_map(fn($k)=>['id'=>$k,'name'=>(string)(ch_plan($k)['name'] ?? $k)],array_keys(array_filter(CH_PLANS,fn($p)=>in_array($p[4],['agent','abo'],true)))))];
}
function adm_action(int $admin, array $b): array {
    adm_bootstrap(); $id=(int)($b['id'] ?? 0); if(!adm_one("SELECT id FROM token_accounts WHERE id=?",[$id])) return [404,['error'=>'not_found','message'=>'Konto nicht gefunden.']];
    $act=(string)($b['action'] ?? ''); $note=mb_substr(trim((string)($b['note'] ?? '')),0,300);
    switch($act){
        case 'credit': case 'debit':
            $n=(int)($b['amount'] ?? 0); if($n<1 || $n>1000000) return [422,['error'=>'invalid_amount','message'=>'Menge 1–1.000.000 Tokens.']];
            if($note==='') return [422,['error'=>'note_required','message'=>'Bitte einen Grund angeben.']];
            $ref='adm:'.$admin.':'.bin2hex(random_bytes(6));
            $ok=$act==='credit' ? th_credit($id,$n,'admin:credit',$ref) : th_debit($id,$n,'admin:debit',$ref);
            if(!$ok) return [409,['error'=>'insufficient_balance','message'=>'Guthaben reicht für diese Abbuchung nicht.']];
            adm_log($admin,$id,$act,($act==='credit' ? '+' : '−').$n.' Tokens · '.$note); return [200,['ok'=>true,'balance'=>th_balance_ledger($id)]];
        case 'extend':
            $row=(int)($b['plan_row'] ?? 0); $d=(int)($b['days'] ?? 0); if($d<1 || $d>366) return [422,['error'=>'invalid_days','message'=>'1–366 Tage.']];
            $r=adm_one("SELECT id,plan,stripe_sub,period_end FROM token_plans WHERE id=? AND account_id=?",[$row,$id]); if(!$r) return [404,['error'=>'not_found','message'=>'Paket nicht gefunden.']];
            if($r['stripe_sub']!==null) return [409,['error'=>'stripe_managed','message'=>'Bezahlte Abos verlängert der Zahlungsanbieter – bitte dort ändern.']];
            th_db()->prepare("UPDATE token_plans SET period_end=(GREATEST(COALESCE(period_end,NOW()),NOW()) + INTERVAL $d DAY), status='active', updated_at=NOW() WHERE id=?")->execute([$row]);
            adm_log($admin,$id,'extend',$r['plan'].' +'.$d.' Tage'.($note!=='' ? ' · '.$note : '')); return [200,['ok'=>true]];
        case 'grant':
            $plan=preg_replace('/[^a-z0-9-]/','',(string)($b['plan'] ?? '')); $d=(int)($b['days'] ?? 14); $pl=ch_plan($plan);
            if(!$pl || !in_array($pl['kind'],['agent','abo'],true)) return [422,['error'=>'invalid_plan','message'=>'Nur Monats-Agenten können freigeschaltet werden.']];
            if($d<1 || $d>366) return [422,['error'=>'invalid_days','message'=>'1–366 Tage.']];
            th_plan_upsert($id,$plan,'','active',date('Y-m-d H:i:s',strtotime("+$d day")));
            adm_log($admin,$id,'grant',$plan.' für '.$d.' Tage'.($note!=='' ? ' · '.$note : '')); return [200,['ok'=>true]];
        case 'cancel':
            $row=(int)($b['plan_row'] ?? 0); $r=adm_one("SELECT id,plan,stripe_sub FROM token_plans WHERE id=? AND account_id=?",[$row,$id]); if(!$r) return [404,['error'=>'not_found','message'=>'Paket nicht gefunden.']];
            if($r['stripe_sub']!==null) return [409,['error'=>'stripe_managed','message'=>'Bezahlte Abos kündigt der Zahlungsanbieter – bitte dort ändern.']];
            th_db()->prepare("UPDATE token_plans SET status='canceled', updated_at=NOW() WHERE id=?")->execute([$row]);
            adm_log($admin,$id,'cancel',(string)$r['plan'].($note!=='' ? ' · '.$note : '')); return [200,['ok'=>true]];
        case 'note':
            $t=mb_substr(trim((string)($b['note'] ?? '')),0,4000);
            th_db()->prepare("INSERT INTO admin_notes(account_id,note,updated_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE note=VALUES(note), updated_at=NOW()")->execute([$id,$t]);
            adm_log($admin,$id,'note',mb_substr($t,0,120)); return [200,['ok'=>true]];
    }
    return [422,['error'=>'invalid_action','message'=>'Unbekannte Aktion.']];
}

/* ======================================================================= REST */
function adm_api(string $route, string $method, int $aid, array $body, array $account): ?array {
    if(strpos($route,'admin/')!==0 || $route==='admin/settings') return null;
    if(!bl_is_admin($aid)) return [403,['error'=>'forbidden']];
    $in=$body+$_GET;
    try{
        switch($route){
            case 'admin/overview': if($method!=='GET') break; $d=(int)($in['days'] ?? 30); return [200,adm_overview(in_array($d,ADM_DAYS,true) ? $d : 30)];
            case 'admin/accounts': if($method!=='GET') break; return [200,adm_accounts($in)];
            case 'admin/account': if($method!=='GET') break; $r=adm_account((int)($in['id'] ?? 0)); return $r ? [200,$r] : [404,['error'=>'not_found','message'=>'Konto nicht gefunden.']];
            case 'admin/action': if($method!=='POST') break; if(!th_rate('adm:'.$aid,120,3600)) return [429,['error'=>'rate_limited']]; return adm_action($aid,$body);
            case 'admin/log': if($method!=='GET') break; adm_bootstrap();
                return [200,['items'=>array_map(fn($r)=>['account_id'=>(int)$r['account_id'],'account'=>(string)($r['acc'] ?? ''),'action'=>(string)$r['action'],'detail'=>(string)$r['detail'],'by'=>(string)($r['by_email'] ?? ''),'created_at'=>adm_iso($r['created_at'])],
                    adm_q("SELECT l.*, a.email acc, b.email by_email FROM admin_log l LEFT JOIN token_accounts a ON a.id=l.account_id LEFT JOIN token_accounts b ON b.id=l.admin_id ORDER BY l.id DESC LIMIT 100"))]];
            default: return [404,['error'=>'not_found']];
        }
        return [405,['error'=>'method_not_allowed']];
    }catch(Throwable $e){ error_log('CH adm_api '.$route.': '.$e->getMessage()); return [500,['error'=>'server_error','message'=>'Interner Fehler.']]; }
}
