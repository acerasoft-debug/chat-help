<?php
/**
 * ChatHelp — Token sistemi çekirdeği (tek defter, tek para birimi)
 * ==================================================================
 * Tüm servisler (Web-App, Agenten, API) token'ı BURADAN düşer/yükler.
 * Kendi tabloları (token_*) → mevcut users şemasına bağımlı değil; e-posta ile eşleşir.
 * Gerekli: db.php (db() PDO). İsteğe bağlı: BREVO_KEY (mail), STRIPE_SK.
 */
require_once __DIR__ . '/db.php';

function th_db(): PDO { static $p=null; if(!$p){ $p = function_exists('db') ? db() : $GLOBALS['pdo']; $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } return $p; }

/** Tabloları bir kez oluşturur (idempotent). */
function th_bootstrap(): void {
    $p=th_db();
    $p->exec("CREATE TABLE IF NOT EXISTS token_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(190) NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS api_keys (
        id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, key_hash CHAR(64) NOT NULL UNIQUE,
        prefix VARCHAR(16) NOT NULL, label VARCHAR(80) DEFAULT 'default', active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP, last_used DATETIME NULL, INDEX(account_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS token_ledger (
        id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, delta INT NOT NULL,
        reason VARCHAR(60) NOT NULL, ref VARCHAR(190) NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX(account_id), UNIQUE KEY uq_ref (reason, ref)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS api_usage (
        id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, task VARCHAR(40), sector VARCHAR(40),
        tokens INT, ms INT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX(account_id, created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    /* Aylık paket / agent abonelikleri (Stripe subscription) */
    $p->exec("CREATE TABLE IF NOT EXISTS token_plans (
        id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, plan VARCHAR(40) NOT NULL,
        stripe_sub VARCHAR(80) NULL, status VARCHAR(20) DEFAULT 'active', period_end DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
        INDEX(account_id), UNIQUE KEY uq_sub (stripe_sub)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    /* Agent ayarları (müşterinin bilgi tabanı, ton, firma) + widget anahtarı */
    $p->exec("CREATE TABLE IF NOT EXISTS agent_configs (
        id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, plan VARCHAR(40) NOT NULL,
        company VARCHAR(120) DEFAULT '', tone VARCHAR(40) DEFAULT 'freundlich-professionell', lang VARCHAR(8) DEFAULT 'de',
        kb MEDIUMTEXT NULL, widget_key CHAR(40) NULL, widget_domain VARCHAR(190) DEFAULT '', widget_on TINYINT(1) DEFAULT 0,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_acc_plan (account_id, plan), UNIQUE KEY uq_widget (widget_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    /* Studio-Login per E-Mail-Code */
    $p->exec("CREATE TABLE IF NOT EXISTS auth_codes (
        id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(190) NOT NULL, code_hash CHAR(64) NOT NULL, ip_hash CHAR(32) NOT NULL,
        expires_at DATETIME NOT NULL, used TINYINT(1) DEFAULT 0, attempts TINYINT DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX(email, created_at), INDEX(ip_hash, created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    /* Telefon-Agent: Anrufe + Transkripte */
    $p->exec("CREATE TABLE IF NOT EXISTS phone_calls (
        id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, plan VARCHAR(40) NOT NULL, call_sid VARCHAR(64) NOT NULL UNIQUE,
        from_number VARCHAR(32) DEFAULT '', to_number VARCHAR(32) DEFAULT '', status VARCHAR(20) DEFAULT 'in-progress',
        turns INT DEFAULT 0, silent INT DEFAULT 0, tokens INT DEFAULT 0, transcript MEDIUMTEXT NULL, outcome VARCHAR(20) DEFAULT '',
        started_at DATETIME DEFAULT CURRENT_TIMESTAMP, ended_at DATETIME NULL, INDEX(account_id, started_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    /* agent_configs: Telefon-Spalten nachrüsten (einmalig) */
    $cols = $p->query("SHOW COLUMNS FROM agent_configs")->fetchAll(PDO::FETCH_COLUMN);
    foreach(['phone_on'=>"TINYINT(1) DEFAULT 0",'phone_number'=>"VARCHAR(32) DEFAULT ''",'phone_greeting'=>"VARCHAR(400) DEFAULT ''",
             'phone_voice'=>"VARCHAR(40) DEFAULT 'de-DE'",'phone_forward'=>"VARCHAR(40) DEFAULT ''",'phone_hours'=>"VARCHAR(160) DEFAULT ''"] as $c=>$def){
        if(!in_array($c,$cols,true)) $p->exec("ALTER TABLE agent_configs ADD COLUMN $c $def");
    }
    /* phone_calls: Hintergrund-Antwort (KI rechnet weiter, Twilio holt per Redirect ab) */
    $cols = $p->query("SHOW COLUMNS FROM phone_calls")->fetchAll(PDO::FETCH_COLUMN);
    if(!in_array('pending',$cols,true)) $p->exec("ALTER TABLE phone_calls ADD COLUMN pending MEDIUMTEXT NULL");
}

/* ---------- Studio-Login (E-Mail + 6-stelliger Code) ---------- */
/** Code anfordern. Limits: 5 je E-Mail/Stunde, 15 je IP/Stunde. Gibt ['ok'=>bool,'message'=>..] zurück. */
function th_login_request(string $email, string $ip, string $lang='de'): array {
    $email=strtolower(trim($email)); $p=th_db(); $ih=md5('ch|'.$ip);
    $s=$p->prepare("SELECT COUNT(*) FROM auth_codes WHERE email=? AND created_at > (NOW() - INTERVAL 1 HOUR)"); $s->execute([$email]);
    if((int)$s->fetchColumn() >= 5) return ['ok'=>false,'error'=>'rate_limited','message'=>'Zu viele Codes angefordert – bitte in einer Stunde erneut.'];
    $s=$p->prepare("SELECT COUNT(*) FROM auth_codes WHERE ip_hash=? AND created_at > (NOW() - INTERVAL 1 HOUR)"); $s->execute([$ih]);
    if((int)$s->fetchColumn() >= 15) return ['ok'=>false,'error'=>'rate_limited','message'=>'Zu viele Anfragen – bitte später erneut.'];
    $code=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
    $p->prepare("UPDATE auth_codes SET used=1 WHERE email=? AND used=0")->execute([$email]);
    $p->prepare("INSERT INTO auth_codes(email,code_hash,ip_hash,expires_at) VALUES(?,?,?,NOW() + INTERVAL 15 MINUTE)")->execute([$email,hash('sha256',$code.'|'.$email),$ih]);
    if(random_int(1,30)===1) $p->exec("DELETE FROM auth_codes WHERE created_at < (NOW() - INTERVAL 2 DAY)");
    $T=['de'=>['s'=>'Dein ChatHelp Studio-Code: '.$code,'h'=>'Anmeldung im ChatHelp Studio','p'=>'Gib diesen Code im Studio ein. Er ist 15 Minuten gültig.','f'=>'Falls du das nicht warst, ignoriere diese E-Mail einfach.'],
        'en'=>['s'=>'Your ChatHelp Studio code: '.$code,'h'=>'Sign in to ChatHelp Studio','p'=>'Enter this code in the Studio. It is valid for 15 minutes.','f'=>"If this wasn't you, just ignore this email."],
        'tr'=>['s'=>'ChatHelp Studio kodun: '.$code,'h'=>'ChatHelp Studio girişi','p'=>'Bu kodu Studio\'da gir. 15 dakika geçerlidir.','f'=>'Bu isteği sen yapmadıysan bu e-postayı yok sayabilirsin.']][$lang] ?? null;
    $T = $T ?: ['s'=>'Dein ChatHelp Studio-Code: '.$code,'h'=>'Anmeldung im ChatHelp Studio','p'=>'Gib diesen Code im Studio ein. Er ist 15 Minuten gültig.','f'=>'Falls du das nicht warst, ignoriere diese E-Mail einfach.'];
    $html='<div style="font-family:Inter,Arial,sans-serif;max-width:520px;margin:0 auto;padding:32px;border:1px solid #e6e8ef;border-radius:20px;color:#0f1222">'
         .'<div style="font-size:22px;font-weight:700;margin-bottom:6px">ChatHelp <span style="color:#b8862b">Studio</span></div>'
         .'<h2 style="font-size:18px;margin:18px 0 8px">'.$T['h'].'</h2><p style="color:#3b4055;margin:0 0 18px">'.$T['p'].'</p>'
         .'<div style="font:700 36px/1 ui-monospace,Menlo,Consolas,monospace;letter-spacing:.22em;background:#f6ecd3;color:#1a1407;padding:18px 22px;border-radius:14px;text-align:center">'.$code.'</div>'
         .'<p style="color:#6b7083;font-size:13px;margin-top:22px">'.$T['f'].'<br>ChatHelp · chat-help.com · support@chat-help.com</p></div>';
    $sent=th_mail($email,$T['s'],$html);
    return ['ok'=>true,'sent'=>$sent,'message'=>$sent?'Code gesendet.':'Code erzeugt, E-Mail-Versand fehlgeschlagen.'];
}
/** Code prüfen → Konto (oder null). Max. 6 Versuche je Code. */
function th_login_verify(string $email, string $code): ?array {
    $email=strtolower(trim($email)); $code=preg_replace('/\D/','',$code); if(strlen($code)!==6) return null;
    $p=th_db(); $s=$p->prepare("SELECT * FROM auth_codes WHERE email=? AND used=0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1"); $s->execute([$email]);
    $row=$s->fetch(PDO::FETCH_ASSOC); if(!$row) return null;
    if((int)$row['attempts'] >= 6){ $p->prepare("UPDATE auth_codes SET used=1 WHERE id=?")->execute([$row['id']]); return null; }
    if(!hash_equals($row['code_hash'],hash('sha256',$code.'|'.$email))){ $p->prepare("UPDATE auth_codes SET attempts=attempts+1 WHERE id=?")->execute([$row['id']]); return null; }
    $p->prepare("UPDATE auth_codes SET used=1 WHERE id=?")->execute([$row['id']]);
    return th_account($email);
}
/** Deneme tokenları: hesap başına bir kez. Yüklenen miktarı döner (0 = zaten alınmış). */
function th_trial_credit(int $acc, int $amount): int {
    return th_credit($acc,$amount,'trial','acc:'.$acc) ? $amount : 0;
}

/* ---------- Telefon-Agent ---------- */
/** Telefon-Schlüssel (= widget_key) → Konfiguration mit phone_on=1 + Konto. */
function th_phone_cfg(string $wk): ?array {
    if(!preg_match('/^wk_[a-f0-9]{36}$/',$wk)) return null;
    $s=th_db()->prepare("SELECT c.*, a.email FROM agent_configs c JOIN token_accounts a ON a.id=c.account_id WHERE c.widget_key=? AND c.phone_on=1"); $s->execute([$wk]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}
function th_call(string $sid): ?array { $s=th_db()->prepare("SELECT * FROM phone_calls WHERE call_sid=?"); $s->execute([$sid]); return $s->fetch(PDO::FETCH_ASSOC) ?: null; }
function th_call_start(int $acc, string $plan, string $sid, string $from, string $to): array {
    th_db()->prepare("INSERT IGNORE INTO phone_calls(account_id,plan,call_sid,from_number,to_number,transcript) VALUES(?,?,?,?,?,'[]')")->execute([$acc,$plan,$sid,$from,$to]);
    return th_call($sid);
}
function th_call_update(string $sid, array $f): void {
    $allowed=['status','turns','silent','tokens','transcript','outcome','ended_at','pending']; $set=[]; $vals=[];
    foreach($f as $k=>$v){ if(in_array($k,$allowed,true)){ $set[]="$k=?"; $vals[]=$v; } }
    if(!$set) return; $vals[]=$sid; th_db()->prepare("UPDATE phone_calls SET ".implode(',',$set)." WHERE call_sid=?")->execute($vals);
}
function th_calls(int $acc, string $plan='', int $limit=50): array {
    $q="SELECT id,plan,call_sid,from_number,to_number,status,turns,tokens,outcome,started_at,ended_at,transcript FROM phone_calls WHERE account_id=?".($plan!==''?" AND plan=?":"")." ORDER BY id DESC LIMIT ".(int)$limit;
    $s=th_db()->prepare($q); $s->execute($plan!==''?[$acc,$plan]:[$acc]); $rows=$s->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as &$r){ $r['transcript']=json_decode((string)$r['transcript'],true) ?: []; } return $rows;
}

/* ---------- Abonelikler ---------- */
function th_plan_upsert(int $acc, string $plan, string $sub, string $status, ?string $periodEnd): void {
    $p=th_db();
    if($sub){ $s=$p->prepare("SELECT id FROM token_plans WHERE stripe_sub=?"); $s->execute([$sub]); $id=$s->fetchColumn(); }
    else { $s=$p->prepare("SELECT id FROM token_plans WHERE account_id=? AND plan=? AND stripe_sub IS NULL"); $s->execute([$acc,$plan]); $id=$s->fetchColumn(); }
    if($id) $p->prepare("UPDATE token_plans SET account_id=?, plan=?, status=?, period_end=?, updated_at=NOW() WHERE id=?")->execute([$acc,$plan,$status,$periodEnd,$id]);
    else    $p->prepare("INSERT INTO token_plans(account_id,plan,stripe_sub,status,period_end) VALUES(?,?,?,?,?)")->execute([$acc,$plan,$sub?:null,$status,$periodEnd]);
}
function th_plan_status(string $sub, string $status, ?string $periodEnd=null): void {
    th_db()->prepare("UPDATE token_plans SET status=?, period_end=COALESCE(?,period_end), updated_at=NOW() WHERE stripe_sub=?")->execute([$status,$periodEnd,$sub]);
}
/** Hesabın aktif paketleri (active/trialing/past_due → kullanılabilir). */
function th_plans(int $acc): array {
    $s=th_db()->prepare("SELECT plan,status,period_end,stripe_sub FROM token_plans WHERE account_id=? ORDER BY created_at DESC"); $s->execute([$acc]); return $s->fetchAll(PDO::FETCH_ASSOC);
}
function th_plan_active(int $acc, string $plan): bool {
    $s=th_db()->prepare("SELECT COUNT(*) FROM token_plans WHERE account_id=? AND plan=? AND status IN ('active','trialing','past_due')"); $s->execute([$acc,$plan]); return (int)$s->fetchColumn()>0;
}
/* ---------- Agent ayarları / widget ---------- */
function th_agent_cfg(int $acc, string $plan): array {
    $s=th_db()->prepare("SELECT * FROM agent_configs WHERE account_id=? AND plan=?"); $s->execute([$acc,$plan]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: ['account_id'=>$acc,'plan'=>$plan,'company'=>'','tone'=>'freundlich-professionell','lang'=>'de','kb'=>'','widget_key'=>null,'widget_domain'=>'','widget_on'=>0,
        'phone_on'=>0,'phone_number'=>'','phone_greeting'=>'','phone_voice'=>'de-DE','phone_forward'=>'','phone_hours'=>''];
}
/** Kaydet. $canActivate=false → widget_on/phone_on 0'a zorlanır (plan aktif değil). phone_number yalnız yönetici (Twilio) tarafından atanır. */
function th_agent_cfg_save(int $acc, string $plan, array $d, bool $canActivate=true): void {
    $p=th_db(); $cur=th_agent_cfg($acc,$plan);
    $company=mb_substr(trim($d['company']??$cur['company']),0,120); $tone=mb_substr(trim($d['tone']??$cur['tone']),0,40); $lang=mb_substr(trim($d['lang']??$cur['lang']),0,8);
    $kb=mb_substr((string)($d['kb']??$cur['kb']),0,60000); $dom=mb_substr(trim($d['widget_domain']??$cur['widget_domain']),0,190);
    $on=$canActivate ? (int)!empty($d['widget_on'] ?? $cur['widget_on']) : 0;
    $pon=$canActivate ? (int)!empty($d['phone_on'] ?? $cur['phone_on']) : 0;
    $pg=mb_substr(trim((string)($d['phone_greeting']??$cur['phone_greeting'])),0,400);
    $pv=preg_replace('/[^a-zA-Z-]/','',(string)($d['phone_voice']??$cur['phone_voice'])) ?: 'de-DE';
    $pf=preg_replace('/[^0-9+ ]/','',(string)($d['phone_forward']??$cur['phone_forward'])); $pf=mb_substr(trim($pf),0,40);
    $ph=mb_substr(trim((string)($d['phone_hours']??$cur['phone_hours'])),0,160);
    $wk=$cur['widget_key'] ?: ('wk_'.bin2hex(random_bytes(18)));
    $p->prepare("INSERT INTO agent_configs(account_id,plan,company,tone,lang,kb,widget_key,widget_domain,widget_on,phone_on,phone_greeting,phone_voice,phone_forward,phone_hours) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE company=VALUES(company),tone=VALUES(tone),lang=VALUES(lang),kb=VALUES(kb),widget_domain=VALUES(widget_domain),widget_on=VALUES(widget_on),
        phone_on=VALUES(phone_on),phone_greeting=VALUES(phone_greeting),phone_voice=VALUES(phone_voice),phone_forward=VALUES(phone_forward),phone_hours=VALUES(phone_hours),updated_at=NOW()")
      ->execute([$acc,$plan,$company,$tone,$lang,$kb,$wk,$dom,$on,$pon,$pg,$pv,$pf,$ph]);
}
function th_widget(string $wk): ?array {
    if(!preg_match('/^wk_[a-f0-9]{36}$/',$wk)) return null;
    $s=th_db()->prepare("SELECT c.*, a.email FROM agent_configs c JOIN token_accounts a ON a.id=c.account_id WHERE c.widget_key=? AND c.widget_on=1"); $s->execute([$wk]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}

function th_account(string $email): array {
    $email=strtolower(trim($email)); $p=th_db();
    $s=$p->prepare("SELECT * FROM token_accounts WHERE email=?"); $s->execute([$email]);
    if($a=$s->fetch(PDO::FETCH_ASSOC)) return $a;
    $p->prepare("INSERT INTO token_accounts(email) VALUES(?)")->execute([$email]);
    return ['id'=>(int)$p->lastInsertId(),'email'=>$email];
}
function th_balance(int $acc): int {
    $s=th_db()->prepare("SELECT COALESCE(SUM(delta),0) FROM token_ledger WHERE account_id=?"); $s->execute([$acc]); return (int)$s->fetchColumn();
}
/** Yükleme; aynı (reason,ref) ikinci kez gelirse sessizce yok sayılır (idempotent). */
function th_credit(int $acc, int $amount, string $reason, ?string $ref=null): bool {
    try{ th_db()->prepare("INSERT INTO token_ledger(account_id,delta,reason,ref) VALUES(?,?,?,?)")->execute([$acc,abs($amount),$reason,$ref]); return true; }
    catch(PDOException $e){ if($e->getCode()==='23000') return false; throw $e; }
}
/** Düşme; bakiye yetmezse false. Yarış koşuluna karşı transaction + kilit. */
function th_debit(int $acc, int $amount, string $reason, ?string $ref=null): bool {
    $p=th_db(); $p->beginTransaction();
    try{
        $s=$p->prepare("SELECT COALESCE(SUM(delta),0) FROM token_ledger WHERE account_id=? FOR UPDATE"); $s->execute([$acc]);
        if((int)$s->fetchColumn() < $amount){ $p->rollBack(); return false; }
        $p->prepare("INSERT INTO token_ledger(account_id,delta,reason,ref) VALUES(?,?,?,?)")->execute([$acc,-abs($amount),$reason,$ref ?: bin2hex(random_bytes(8))]);
        $p->commit(); return true;
    }catch(Throwable $e){ $p->rollBack(); throw $e; }
}

/** API anahtarı üretir; düz metin SADECE bir kez döner. */
function th_issue_key(int $acc, string $label='default'): string {
    $plain='ch_live_'.bin2hex(random_bytes(24));
    th_db()->prepare("INSERT INTO api_keys(account_id,key_hash,prefix,label) VALUES(?,?,?,?)")
        ->execute([$acc, hash('sha256',$plain), substr($plain,0,16), $label]);
    return $plain;
}
function th_has_key(int $acc): bool { $s=th_db()->prepare("SELECT COUNT(*) FROM api_keys WHERE account_id=? AND active=1"); $s->execute([$acc]); return (int)$s->fetchColumn()>0; }
/** Bearer → hesap (yoksa null). */
function th_auth(?string $bearer): ?array {
    if(!$bearer || !preg_match('/^ch_(live|test)_[a-f0-9]{48}$/',$bearer)) return null;
    $s=th_db()->prepare("SELECT k.id kid, a.* FROM api_keys k JOIN token_accounts a ON a.id=k.account_id WHERE k.key_hash=? AND k.active=1");
    $s->execute([hash('sha256',$bearer)]); $r=$s->fetch(PDO::FETCH_ASSOC); if(!$r) return null;
    th_db()->prepare("UPDATE api_keys SET last_used=NOW() WHERE id=?")->execute([$r['kid']]); return $r;
}
function th_log_usage(int $acc, string $task, string $sector, int $tokens, int $ms): void {
    th_db()->prepare("INSERT INTO api_usage(account_id,task,sector,tokens,ms) VALUES(?,?,?,?,?)")->execute([$acc,$task,$sector,$tokens,$ms]);
}

/** KI model zinciri: db.php'de CLAUDE_MODEL varsa önce o; sonra en yeni → eski. Son çalışan model 6 saat önbelleklenir. */
function th_ai_models(): array {
    $m = (defined('CLAUDE_MODEL') && CLAUDE_MODEL) ? [CLAUDE_MODEL] : [];
    return array_values(array_unique(array_merge($m, ['claude-sonnet-5-5','claude-sonnet-4-6','claude-sonnet-4-5'])));
}
/** Son th_ai() çağrısının bilgisi: provider, model, errors[] */
function th_ai_info(): array { return $GLOBALS['__th_ai_info'] ?? []; }
/**
 * KI sohbet: Claude (model zinciri; model bulunamazsa sıradakine geçer), olmazsa DeepSeek. Metin ya da null döner.
 * messages = [{role:user|assistant, content}]  — son mesaj user olmalı.
 */
function th_ai(string $system, array $messages, int $maxTokens=1200, int $timeout=60): ?string {
    $msgs=[]; foreach($messages as $m){ $role=($m['role']??'user')==='assistant'?'assistant':'user'; $c=trim((string)($m['content']??'')); if($c!=='') $msgs[]=['role'=>$role,'content'=>$c]; }
    if(!$msgs || end($msgs)['role']!=='user') return null;
    $info=['provider'=>null,'model'=>null,'errors'=>[]];
    $cacheF = is_writable(__DIR__) ? __DIR__.'/.ai_model.json' : sys_get_temp_dir().'/ch_ai_model.json';
    $models = th_ai_models();
    $cached = @json_decode((string)@file_get_contents($cacheF), true);
    if(!empty($cached['model']) && (time()-(int)($cached['t']??0)) < 21600 && in_array($cached['model'],$models,true)){
        $models = array_values(array_unique(array_merge([$cached['model']], $models)));
    }
    if(defined('CLAUDE_KEY') && CLAUDE_KEY){
        foreach($models as $model){
            $ch=curl_init('https://api.anthropic.com/v1/messages');
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>$timeout,
                CURLOPT_POSTFIELDS=>json_encode(['model'=>$model,'max_tokens'=>$maxTokens,'system'=>$system,'messages'=>$msgs]),
                CURLOPT_HTTPHEADER=>['x-api-key: '.CLAUDE_KEY,'anthropic-version: 2023-06-01','content-type: application/json']]);
            $raw=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $cerr=curl_error($ch); curl_close($ch);
            $r=json_decode((string)$raw,true);
            /* Metin blokları birleştirilir: yeni modeller ilk blokta 'thinking' döndürebilir, metin sonraki blokta olur */
            $txt=''; foreach((array)($r['content'] ?? []) as $blk){ if(is_array($blk) && ($blk['type'] ?? 'text')==='text') $txt.=(string)($blk['text'] ?? ''); }
            if(trim($txt)!==''){
                $info['provider']='claude'; $info['model']=$model; $GLOBALS['__th_ai_info']=$info;
                if(($cached['model']??null)!==$model) @file_put_contents($cacheF, json_encode(['model'=>$model,'t'=>time()]));
                return $txt;
            }
            if($code===200){ $info['errors'][]=$model.': 200 leer (stop_reason '.($r['stop_reason']??'?').')'; continue; }
            $et=(string)($r['error']['type']??''); $em=(string)($r['error']['message']??$cerr);
            $info['errors'][]=$model.': '.($code?:'curl').' '.$et.' '.mb_substr($em,0,120);
            if($code===401 || $et==='authentication_error' || $et==='permission_error') break; // anahtar sorunu: başka model denemenin anlamı yok
            // bulunamayan model, aşırı yük (529), limit (429), geçici hata → sıradaki modeli dene
        }
    }
    if(defined('DEEPSEEK_KEY') && DEEPSEEK_KEY){
        $ch=curl_init('https://api.deepseek.com/chat/completions');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>$timeout,
            CURLOPT_POSTFIELDS=>json_encode(['model'=>'deepseek-chat','max_tokens'=>$maxTokens,'messages'=>array_merge([['role'=>'system','content'=>$system]],$msgs)]),
            CURLOPT_HTTPHEADER=>['Authorization: Bearer '.DEEPSEEK_KEY,'content-type: application/json']]);
        $r=json_decode((string)curl_exec($ch),true); curl_close($ch);
        if(!empty($r['choices'][0]['message']['content'])){ $info['provider']='deepseek'; $info['model']='deepseek-chat'; $GLOBALS['__th_ai_info']=$info; return $r['choices'][0]['message']['content']; }
        $info['errors'][]='deepseek-chat: '.mb_substr((string)($r['error']['message']??'yanıt yok'),0,120);
    }
    $GLOBALS['__th_ai_info']=$info; error_log('CH th_ai: '.json_encode($info['errors']));
    return null;
}

/**
 * KI mit Werkzeugen (Anthropic tool use): Modell darf Funktionen aufrufen (z. B. freie Termine lesen, Termin buchen).
 * $tools = [['name','description','input_schema'],…]  $exec(name, input) → array (wird als JSON zurückgegeben).
 * Gibt den finalen Text zurück (null bei Fehler). Infos: th_ai_info() → provider, model, tool_calls[].
 */
function th_ai_tools(string $system, array $messages, array $tools, callable $exec, int $maxTokens=900, int $timeout=60, int $maxRounds=5): ?string {
    if(!(defined('CLAUDE_KEY') && CLAUDE_KEY) || !$tools) return th_ai($system,$messages,$maxTokens,$timeout);
    $msgs=[]; foreach($messages as $m){ $role=($m['role']??'user')==='assistant'?'assistant':'user'; $c=$m['content']??''; if(is_string($c)){ $c=trim($c); if($c==='') continue; } $msgs[]=['role'=>$role,'content'=>$c]; }
    if(!$msgs || $msgs[0]['role']!=='user') return null;
    $info=['provider'=>null,'model'=>null,'errors'=>[],'tool_calls'=>[]];
    $cacheF = is_writable(__DIR__) ? __DIR__.'/.ai_model.json' : sys_get_temp_dir().'/ch_ai_model.json';
    $models = th_ai_models(); $cached=@json_decode((string)@file_get_contents($cacheF),true);
    if(!empty($cached['model']) && (time()-(int)($cached['t']??0))<21600 && in_array($cached['model'],$models,true)) $models=array_values(array_unique(array_merge([$cached['model']],$models)));
    $model=null; $text='';
    for($round=0;$round<$maxRounds;$round++){
        $resp=null;
        foreach($model ? [$model] : $models as $m){
            $ch=curl_init('https://api.anthropic.com/v1/messages');
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>$timeout,
                CURLOPT_POSTFIELDS=>json_encode(['model'=>$m,'max_tokens'=>$maxTokens,'system'=>$system,'messages'=>$msgs,'tools'=>$tools],JSON_UNESCAPED_UNICODE),
                CURLOPT_HTTPHEADER=>['x-api-key: '.CLAUDE_KEY,'anthropic-version: 2023-06-01','content-type: application/json']]);
            $raw=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $cerr=curl_error($ch); curl_close($ch);
            $r=json_decode((string)$raw,true);
            if($code===200 && !empty($r['content'])){ $resp=$r; if($model!==$m){ $model=$m; if(($cached['model']??null)!==$m) @file_put_contents($cacheF,json_encode(['model'=>$m,'t'=>time()])); } break; }
            $info['errors'][]=$m.': '.($code?:'curl').' '.($r['error']['type']??'').' '.mb_substr((string)($r['error']['message']??$cerr),0,120);
            if($code===401 || ($r['error']['type']??'')==='authentication_error') break 2;
            if($model) break; // bekannter Modell-Fehler mitten im Gespräch: nicht wechseln
        }
        if(!$resp) break;
        $info['provider']='claude'; $info['model']=$model;
        $toolUses=[]; $t='';
        foreach((array)$resp['content'] as $blk){ if(($blk['type']??'')==='text') $t.=(string)($blk['text']??''); elseif(($blk['type']??'')==='tool_use') $toolUses[]=$blk; }
        if(trim($t)!=='') $text=$t;
        if(($resp['stop_reason']??'')!=='tool_use' || !$toolUses){ $GLOBALS['__th_ai_info']=$info; return trim($text)!=='' ? $text : null; }
        $results=[];
        foreach($toolUses as $tu){
            $in=is_array($tu['input']??null)?$tu['input']:[];
            try{ $out=$exec((string)$tu['name'],$in); }catch(Throwable $e){ $out=['error'=>$e->getMessage()]; }
            $info['tool_calls'][]=['name'=>$tu['name'],'input'=>$in,'ok'=>empty($out['error'])];
            $results[]=['type'=>'tool_result','tool_use_id'=>$tu['id'],'content'=>json_encode($out,JSON_UNESCAPED_UNICODE)];
        }
        $msgs[]=['role'=>'assistant','content'=>$resp['content']]; $msgs[]=['role'=>'user','content'=>$results];
    }
    $GLOBALS['__th_ai_info']=$info;
    return trim($text)!=='' ? $text : null;
}

/** E-posta: Brevo HTTPS API varsa onunla, yoksa mail(). */
function th_mail(string $to, string $subject, string $html): bool {
    /* Absender: MAIL_FROM, sonst support@chat-help.com (Domain mit MX/SPF; SMTP_FROM zeigt ggf. auf .de) */
    $from = defined('MAIL_FROM') ? MAIL_FROM : 'support@chat-help.com'; $name = defined('SMTP_NAME') ? SMTP_NAME : 'ChatHelp';
    if(preg_match('/\.(invalid|test|example)$/i',trim($to)) || !filter_var(trim($to),FILTER_VALIDATE_EMAIL)) return true;   // Testkonten (RFC 2606) / ungültige Adressen: nichts versenden, keine Bounces
    if(defined('BREVO_KEY') && BREVO_KEY){
        $ch=curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>20,
            CURLOPT_POSTFIELDS=>json_encode(['sender'=>['name'=>$name,'email'=>$from],'to'=>[['email'=>$to]],'subject'=>$subject,'htmlContent'=>$html]),
            CURLOPT_HTTPHEADER=>['accept: application/json','api-key: '.BREVO_KEY,'content-type: application/json']]);
        curl_exec($ch); $c=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); if($c>=200&&$c<300) return true;
    }
    /* -f: Envelope-Absender = From (im Web-Kontext sonst www-data@host → Relay/Empfänger verwerfen die Mail) */
    $ok = @mail($to,'=?UTF-8?B?'.base64_encode($subject).'?=',$html,"MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: $name <$from>\r\nReply-To: $from\r\nX-Mailer: ChatHelp",'-f'.$from);
    if(!$ok) $ok = @mail($to,'=?UTF-8?B?'.base64_encode($subject).'?=',$html,"MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: $name <$from>\r\nReply-To: $from\r\nX-Mailer: ChatHelp");
    return $ok;
}

/** Dinamik yanıtlar Cloudflare/platform önbelleğine girmesin (platform varsayılanı: public, 31 gün). */
function th_nocache(): void {
    if(headers_sent()) return;
    header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0'); header('Cloudflare-CDN-Cache-Control: no-store'); header('CDN-Cache-Control: no-store'); header('Pragma: no-cache'); header('Expires: 0'); header('Vary: Authorization, Cookie');
    /* Host Europe/GoDaddy MWP: Set-Cookie içeren yanıtlar platform/edge önbelleğine alınmaz (Cache-Control tek başına yetmiyor) */
    header('Set-Cookie: chnc=1; Path=/; Max-Age=3600; Secure; HttpOnly; SameSite=Lax', false);
}
/** Dynamische HTML-Seiten (Fahrer, Termin-Verwaltung): ohne Cache-Buster per 302 auf dieselbe URL + _=<Zeit> umleiten – Redirects werden am Edge nicht gecacht, die Zielseite ist eindeutig. */
function th_fresh(): void {
    if($_SERVER['REQUEST_METHOD']!=='GET' || isset($_GET['_']) || headers_sent()) return;
    $u=strtok((string)$_SERVER['REQUEST_URI'],'?'); $q=$_GET; $q['_']=dechex(time()); th_nocache(); header('Location: '.$u.'?'.http_build_query($q),true,302); exit;
}
/**
 * Antwort sofort an den Aufrufer schicken und danach im Hintergrund weiterarbeiten (PHP-FPM / LiteSpeed).
 * Für Webhooks mit kurzer Geduld (Twilio 15 s, Meta wiederholt bei langsamer Antwort). false = nicht möglich, nichts gesendet → synchron weitermachen.
 */
function th_finish_response(string $body, string $ctype='application/json; charset=UTF-8', int $code=200): bool {
    if(PHP_SAPI==='cli' || headers_sent() || (!function_exists('fastcgi_finish_request') && !function_exists('litespeed_finish_request'))) return false;
    ignore_user_abort(true); @set_time_limit(180);
    http_response_code($code); header('Content-Type: '.$ctype); header('Content-Length: '.strlen($body)); header('Connection: close');
    echo $body; while(ob_get_level()>0) @ob_end_flush(); @flush();
    if(function_exists('fastcgi_finish_request')) fastcgi_finish_request(); else litespeed_finish_request();
    return true;
}
function th_json(int $code, array $data): void { th_nocache(); http_response_code($code); header('Content-Type: application/json; charset=UTF-8'); echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
