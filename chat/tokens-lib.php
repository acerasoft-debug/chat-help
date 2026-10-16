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
    return $s->fetch(PDO::FETCH_ASSOC) ?: ['account_id'=>$acc,'plan'=>$plan,'company'=>'','tone'=>'freundlich-professionell','lang'=>'de','kb'=>'','widget_key'=>null,'widget_domain'=>'','widget_on'=>0];
}
function th_agent_cfg_save(int $acc, string $plan, array $d): void {
    $p=th_db(); $cur=th_agent_cfg($acc,$plan);
    $company=mb_substr(trim($d['company']??$cur['company']),0,120); $tone=mb_substr(trim($d['tone']??$cur['tone']),0,40); $lang=mb_substr(trim($d['lang']??$cur['lang']),0,8);
    $kb=mb_substr((string)($d['kb']??$cur['kb']),0,60000); $dom=mb_substr(trim($d['widget_domain']??$cur['widget_domain']),0,190); $on=(int)!empty($d['widget_on']);
    $wk=$cur['widget_key'] ?: ('wk_'.bin2hex(random_bytes(18)));
    $p->prepare("INSERT INTO agent_configs(account_id,plan,company,tone,lang,kb,widget_key,widget_domain,widget_on) VALUES(?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE company=VALUES(company),tone=VALUES(tone),lang=VALUES(lang),kb=VALUES(kb),widget_domain=VALUES(widget_domain),widget_on=VALUES(widget_on),updated_at=NOW()")
      ->execute([$acc,$plan,$company,$tone,$lang,$kb,$wk,$dom,$on]);
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
    $cacheF = sys_get_temp_dir().'/ch_ai_model.json';
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
            if(!empty($r['content'][0]['text'])){
                $info['provider']='claude'; $info['model']=$model; $GLOBALS['__th_ai_info']=$info;
                if(($cached['model']??null)!==$model) @file_put_contents($cacheF, json_encode(['model'=>$model,'t'=>time()]));
                return $r['content'][0]['text'];
            }
            $et=(string)($r['error']['type']??''); $em=(string)($r['error']['message']??$cerr);
            $info['errors'][]=$model.': '.($code?:'curl').' '.$et.' '.mb_substr($em,0,120);
            $modelProblem = $code===404 || $et==='not_found_error' || ($et==='invalid_request_error' && stripos($em,'model')!==false);
            if(!$modelProblem) break; // anahtar / limit / sunucu hatası: başka model denemenin anlamı yok
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

/** E-posta: Brevo HTTPS API varsa onunla, yoksa mail(). */
function th_mail(string $to, string $subject, string $html): bool {
    $from = defined('SMTP_FROM') ? SMTP_FROM : 'noreply@chat-help.com'; $name = defined('SMTP_NAME') ? SMTP_NAME : 'ChatHelp';
    if(defined('BREVO_KEY') && BREVO_KEY){
        $ch=curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>20,
            CURLOPT_POSTFIELDS=>json_encode(['sender'=>['name'=>$name,'email'=>$from],'to'=>[['email'=>$to]],'subject'=>$subject,'htmlContent'=>$html]),
            CURLOPT_HTTPHEADER=>['accept: application/json','api-key: '.BREVO_KEY,'content-type: application/json']]);
        curl_exec($ch); $c=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); if($c>=200&&$c<300) return true;
    }
    return @mail($to,$subject,$html,"MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: $name <$from>");
}

function th_json(int $code, array $data): void { http_response_code($code); header('Content-Type: application/json; charset=UTF-8'); echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
