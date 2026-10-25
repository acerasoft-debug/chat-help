<?php
/**
 * ChatHelp — Token sistemi çekirdeği (tek defter, tek para birimi)
 * ==================================================================
 * Tüm servisler (Web-App, Agenten, API) token'ı BURADAN düşer/yükler.
 * Kendi tabloları (token_*) → mevcut users şemasına bağımlı değil; e-posta ile eşleşir.
 * Gerekli: db.php (db() PDO). İsteğe bağlı: BREVO_KEY (mail), STRIPE_SK.
 */
require_once __DIR__ . '/db.php';
/* Zusätzliche Schlüssel aus chat/tts-keys.php (nur auf dem Server, ops/keybox.php: Sprachdienste, zentrales Twilio-Konto) – db.php hat Vorrang */
if(is_file(__DIR__.'/tts-keys.php')){ $__chk=@include __DIR__.'/tts-keys.php';
    if(is_array($__chk)) foreach($__chk as $__n=>$__v) if(is_string($__n) && preg_match('/^[A-Z][A-Z0-9_]{2,40}$/',$__n) && !defined($__n) && (is_string($__v) || is_int($__v))) define($__n,$__v);
    unset($__chk,$__n,$__v); }
if(is_file(__DIR__.'/billing-lib.php')) require_once __DIR__.'/billing-lib.php';   // Lizenz/Betrieb, eigener KI-Schlüssel, KI-Verbrauch (bl_…)

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
             'phone_voice'=>"VARCHAR(40) DEFAULT 'de-DE'",'phone_forward'=>"VARCHAR(40) DEFAULT ''",'phone_hours'=>"VARCHAR(160) DEFAULT ''",'ai_eco'=>"TINYINT(1) DEFAULT 0",'voice_premium'=>"TINYINT(1) DEFAULT 0",'phone_max_min'=>"TINYINT DEFAULT 0"] as $c=>$def){   // phone_max_min: Höchstdauer je Anruf vom Inhaber (0 = Standard des Pakets)
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
    $T=['de'=>['s'=>$code.' ist dein ChatHelp Studio-Code','h'=>'Anmeldung im ChatHelp Studio','p'=>'Dein Anmeldecode ist 15 Minuten gültig.','c'=>'Tippe auf den Code, um ihn zu markieren und zu kopieren.','b'=>'Code übernehmen & anmelden','o'=>'Oder im Studio eingeben:','f'=>'Falls du das nicht warst, ignoriere diese E-Mail einfach. Gib den Code niemals an andere weiter.'],
        'en'=>['s'=>$code.' is your ChatHelp Studio code','h'=>'Sign in to ChatHelp Studio','p'=>'Your sign-in code is valid for 15 minutes.','c'=>'Tap the code to select and copy it.','b'=>'Use code & sign in','o'=>'Or enter it in the Studio:','f'=>"If this wasn't you, just ignore this email. Never share this code with anyone."],
        'tr'=>['s'=>$code.' ChatHelp Studio kodun','h'=>'ChatHelp Studio girişi','p'=>'Giriş kodun 15 dakika geçerlidir.','c'=>'Kopyalamak için koda dokun ve seç.','b'=>'Kodu aktar ve giriş yap','o'=>'Ya da Studio\'da gir:','f'=>'Bu isteği sen yapmadıysan bu e-postayı yok sayabilirsin. Kodu kimseyle paylaşma.']][$lang] ?? null;
    $T = $T ?: ['s'=>$code.' ist dein ChatHelp Studio-Code','h'=>'Anmeldung im ChatHelp Studio','p'=>'Dein Anmeldecode ist 15 Minuten gültig.','c'=>'Tippe auf den Code, um ihn zu markieren und zu kopieren.','b'=>'Code übernehmen & anmelden','o'=>'Oder im Studio eingeben:','f'=>'Falls du das nicht warst, ignoriere diese E-Mail einfach. Gib den Code niemals an andere weiter.'];
    /* Ein-Tipp-Übernahme: Code + E-Mail stehen im Fragment (#…) – das wird nie an den Server/Logs/Mail-Scanner übertragen;
       das Studio füllt nur vor, angemeldet wird erst mit dem Klick auf „Anmelden“ (Linkprüfer verbrauchen den Code nicht). */
    $link='https://chat-help.com/chat/studio.php#code='.$code.'&e='.rtrim(strtr(base64_encode($email),'+/','-_'),'=');
    $h=fn($x)=>htmlspecialchars($x,ENT_QUOTES,'UTF-8');
    $html='<div style="font-family:Inter,Arial,sans-serif;max-width:520px;margin:0 auto;padding:32px;border:1px solid #e6e8ef;border-radius:20px;color:#0f1222">'
         .'<div style="font-size:22px;font-weight:700;margin-bottom:6px">ChatHelp <span style="color:#b8862b">Studio</span></div>'
         .'<h2 style="font-size:18px;margin:18px 0 8px">'.$h($T['h']).'</h2><p style="color:#3b4055;margin:0 0 18px">'.$h($T['p']).'</p>'
         .'<div style="font:700 36px/1 ui-monospace,Menlo,Consolas,monospace;letter-spacing:.22em;background:#f6ecd3;color:#1a1407;padding:18px 22px;border-radius:14px;text-align:center;-webkit-user-select:all;-moz-user-select:all;user-select:all;cursor:text">'.$code.'</div>'
         .'<p style="color:#6b7083;font-size:13px;margin:10px 0 0;text-align:center">'.$h($T['c']).'</p>'
         .'<div style="text-align:center;margin:22px 0 4px"><a href="'.$h($link).'" style="display:inline-block;background:#0f1222;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:13px 22px;border-radius:999px">'.$h($T['b']).' →</a></div>'
         .'<p style="color:#6b7083;font-size:13px;margin-top:22px">'.$h($T['f']).'<br>ChatHelp · chat-help.com · support@chat-help.com</p></div>';
    $text=$T['h']."\n\n".$T['p']."\n\n".$code."\n\n".$T['b'].":\n".$link."\n\n".$T['f']."\n\nChatHelp · chat-help.com · support@chat-help.com\n";
    $sent=th_mail($email,$T['s'],$html,$text);
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
/** Sekunden seit Gesprächsbeginn (Datenbankzeit wie started_at). */
function th_call_elapsed(string $sid): int { $s=th_db()->prepare("SELECT TIMESTAMPDIFF(SECOND,started_at,NOW()) FROM phone_calls WHERE call_sid=?"); $s->execute([$sid]); return max(0,(int)$s->fetchColumn()); }
/** Laufende Gespräche mit dem Agenten (ohne dieses und ohne weitergeleitete), höchstens so alt wie die Höchstdauer + 2 Minuten (hängengebliebene zählen nicht). */
function th_calls_active(int $acc, array $plans, string $exceptSid, int $maxSec): int {
    if(!$plans) return 0; $in=implode(',',array_fill(0,count($plans),'?'));
    $s=th_db()->prepare("SELECT COUNT(*) FROM phone_calls WHERE account_id=? AND plan IN ($in) AND call_sid<>? AND status='in-progress' AND ended_at IS NULL AND outcome NOT IN ('lines_busy','forwarded') AND started_at > (NOW() - INTERVAL ? SECOND)");   // weitergeleitete Gespräche belegen keinen Agenten
    $s->execute(array_merge([$acc],$plans,[$exceptSid,$maxSec+120])); return (int)$s->fetchColumn();
}
function th_call_update(string $sid, array $f): void {
    $allowed=['status','turns','silent','tokens','transcript','outcome','ended_at','pending']; $set=[]; $vals=[];
    foreach($f as $k=>$v){ if(in_array($k,$allowed,true)){ if($v==='NOW()'){ $set[]="$k=NOW()"; continue; } $set[]="$k=?"; $vals[]=$v; } }   // 'NOW()' = DB-Zeit (wie started_at)
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
/** Vereinbarte Monats-Tokens eines Abos (Bestandsschutz, Anzeige „~Stunden“ für den Kunden). */
function th_plan_tokens(string $sub, int $tokens): void {
    if($sub==='') return; static $col=false;
    if(!$col){ $col=true; try{ $c=th_db()->query("SHOW COLUMNS FROM token_plans LIKE 'tokens_month'")->fetch(); if(!$c) th_db()->exec("ALTER TABLE token_plans ADD COLUMN tokens_month INT NULL"); }catch(Throwable $e){ error_log('CH token_plans.tokens_month: '.$e->getMessage()); } }
    try{ th_db()->prepare("UPDATE token_plans SET tokens_month=? WHERE stripe_sub=?")->execute([$tokens,$sub]); }catch(Throwable $e){ error_log('CH th_plan_tokens: '.$e->getMessage()); }
}
/**
 * Einstellungen der Plattform, im Studio vom Betreiber änderbar (Tabelle ch_settings, JSON je Schlüssel): Annahmen (Antworten je Stunde,
 * Minuten je Gespräch), Telefon-Grenzen, Paket-Anpassungen für neue Käufe. Werte werden je Request einmal gelesen.
 */
function ch_setting(string $k, $default=null) {
    static $all=null;
    if($k==='__reset'){ $all=null; return null; }
    if($all===null){ $all=[]; try{ th_db()->exec("CREATE TABLE IF NOT EXISTS ch_settings (k VARCHAR(64) PRIMARY KEY, v MEDIUMTEXT NOT NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        foreach(th_db()->query("SELECT k,v FROM ch_settings")->fetchAll(PDO::FETCH_ASSOC) as $r){ $j=json_decode((string)$r['v'],true); $all[$r['k']]=$j; } }catch(Throwable $e){ error_log('CH ch_setting: '.$e->getMessage()); } }
    return array_key_exists($k,$all) && $all[$k]!==null ? $all[$k] : $default;
}
function ch_setting_set(string $k, $v): void {
    ch_setting('__probe'); th_db()->prepare("INSERT INTO ch_settings(k,v) VALUES(?,?) ON DUPLICATE KEY UPDATE v=VALUES(v), updated_at=NOW()")->execute([substr($k,0,64),json_encode($v,JSON_UNESCAPED_UNICODE)]);
    ch_setting('__reset');
}
function th_plan_status(string $sub, string $status, ?string $periodEnd=null): void {
    th_db()->prepare("UPDATE token_plans SET status=?, period_end=COALESCE(?,period_end), updated_at=NOW() WHERE stripe_sub=?")->execute([$status,$periodEnd,$sub]);
}
/** Pakete des Kontos (alle Zustände). Abgelaufener Inklusiv-Betrieb eines Einmalkaufs steht als status 'license' darin. */
function th_plans(int $acc): array {
    if(function_exists('bl_expire_licenses')) bl_expire_licenses($acc);
    $s=th_db()->prepare("SELECT plan,status,period_end,stripe_sub FROM token_plans WHERE account_id=? ORDER BY created_at DESC"); $s->execute([$acc]); return $s->fetchAll(PDO::FETCH_ASSOC);
}
/**
 * Ist der Agent bzw. das Paket nutzbar (active/trialing/past_due)? Zählt jede Variante desselben Agenten (Monatsplan, Einmalkauf im Inklusiv-Zeitraum
 * bzw. Bestandskauf, Betrieb, eigener Schlüssel – ch_plan_variants); Betrieb/BYOK nur zusammen mit der Lizenz.
 */
function th_plan_active(int $acc, string $plan): bool {
    if(function_exists('bl_expire_licenses')) bl_expire_licenses($acc);
    $ids=function_exists('ch_plan_variants') ? ch_plan_variants($plan) : [$plan];
    $s=th_db()->prepare("SELECT DISTINCT plan FROM token_plans WHERE account_id=? AND plan IN (".implode(',',array_fill(0,count($ids),'?')).") AND status IN ('active','trialing','past_due')");
    $s->execute(array_merge([$acc],$ids));
    foreach($s->fetchAll(PDO::FETCH_COLUMN) as $id){
        if(preg_match('/^(?:betrieb|byok)-([a-z0-9-]+)$/',(string)$id,$m) && function_exists('bl_license_owned') && !bl_license_owned($acc,'buy-'.$m[1])) continue;
        return true;
    }
    return false;
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
    $eco=array_key_exists('ai_eco',$d) ? (int)(!empty($d['ai_eco']) && ch_ai_eco_plan($plan)) : (int)($cur['ai_eco'] ?? 0);
    $p->prepare("INSERT INTO agent_configs(account_id,plan,company,tone,lang,kb,widget_key,widget_domain,widget_on,phone_on,phone_greeting,phone_voice,phone_forward,phone_hours) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE company=VALUES(company),tone=VALUES(tone),lang=VALUES(lang),kb=VALUES(kb),widget_domain=VALUES(widget_domain),widget_on=VALUES(widget_on),
        phone_on=VALUES(phone_on),phone_greeting=VALUES(phone_greeting),phone_voice=VALUES(phone_voice),phone_forward=VALUES(phone_forward),phone_hours=VALUES(phone_hours),updated_at=NOW()")
      ->execute([$acc,$plan,$company,$tone,$lang,$kb,$wk,$dom,$on,$pon,$pg,$pv,$pf,$ph]);
    $prem=array_key_exists('voice_premium',$d) ? (int)!empty($d['voice_premium']) : (int)($cur['voice_premium'] ?? 0);
    $pmax=array_key_exists('phone_max_min',$d) && is_numeric($d['phone_max_min']) ? max(0,min(60,(int)$d['phone_max_min'])) : (int)($cur['phone_max_min'] ?? 0);
    try{ $p->prepare("UPDATE agent_configs SET ai_eco=?, voice_premium=?, phone_max_min=? WHERE account_id=? AND plan=?")->execute([$eco,$prem,$pmax,$acc,$plan]); }catch(Throwable $e){}
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
/** Echtes Guthaben laut Token-Buch (für Anzeige und Abrechnung). */
function th_balance_ledger(int $acc): int {
    $s=th_db()->prepare("SELECT COALESCE(SUM(delta),0) FROM token_ledger WHERE account_id=?"); $s->execute([$acc]); return (int)$s->fetchColumn();
}
/**
 * Guthaben für die Bereitschafts-Prüfungen der Kanäle („reicht es für eine Antwort?“). Nutzt das Konto seinen eigenen KI-Schlüssel (BYOK, aktives Paket),
 * gilt es mit mindestens BL_BYOK_GATE als bereit – abgebucht wird dabei nichts (th_charge/th_debit lesen immer das echte Buch).
 */
function th_balance(int $acc): int {
    $b=th_balance_ledger($acc);
    if($acc>0 && defined('BL_BYOK_GATE') && $b<BL_BYOK_GATE && function_exists('bl_byok_usable') && bl_byok_usable($acc)) return BL_BYOK_GATE;
    return $b;
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
/**
 * Abrechnung NACH erbrachter Leistung (KI hat schon geantwortet): bucht den Betrag, höchstens aber das Restguthaben –
 * nie negativ, nie „gratis“. Gibt den tatsächlich gebuchten Betrag zurück (0 = nichts mehr da oder Referenz schon gebucht).
 */
function th_charge(int $acc, int $amount, string $reason, ?string $ref=null): int {
    if($amount<=0) return 0;
    /* Letzte KI-Antwort lief über den eigenen Schlüssel dieses Kontos → keine Tokens (nur die Plattformgebühr des Pakets). Einmal je KI-Aufruf. */
    $inf=$GLOBALS['__th_ai_info'] ?? null;
    if(is_array($inf) && !empty($inf['byok']) && (int)($inf['account'] ?? 0)===$acc && empty($inf['billed'])){ $GLOBALS['__th_ai_info']['billed']=$amount; return 0; }
    $p=th_db(); $p->beginTransaction();
    try{
        $s=$p->prepare("SELECT COALESCE(SUM(delta),0) FROM token_ledger WHERE account_id=? FOR UPDATE"); $s->execute([$acc]);
        $amt=min($amount,max(0,(int)$s->fetchColumn()));
        if($amt>0) $p->prepare("INSERT INTO token_ledger(account_id,delta,reason,ref) VALUES(?,?,?,?)")->execute([$acc,-$amt,$reason,$ref ?: bin2hex(random_bytes(8))]);
        $p->commit();
        if(function_exists('push_tokens_low')){ try{ push_tokens_low($acc,th_balance($acc)); }catch(Throwable $e){} }
        if($amt>0 && function_exists('bl_quota_check')){ try{ bl_quota_check($acc); }catch(Throwable $e){ error_log('CH quota: '.$e->getMessage()); } }   // 80/100 % des Monatskontingents
        return $amt;
    }catch(Throwable $e){ $p->rollBack(); if($e instanceof PDOException && $e->getCode()==='23000') return 0; throw $e; }
}

/* ---------- Zugangsdaten je Konto (Twilio, Meta) ---------- */
/**
 * Hauptschlüssel für Zugangsdaten: CH_SECRET_KEY aus db.php, sonst ein einmalig auf dem Server erzeugter Schlüssel in
 * chat/cache/.ch-master-key.php (PHP-Datei → im Browser leer; nicht im Deploy-Manifest → wird nie überschrieben;
 * chat/cache ist vom Server-Snapshot ausgeschlossen → landet nie im Repository). '' = keine Verschlüsselung möglich.
 */
function th_secret_master(): string {
    static $k=null; if($k!==null) return $k;
    if(defined('CH_SECRET_KEY') && CH_SECRET_KEY) return $k=(string)CH_SECRET_KEY;
    $dir=__DIR__.'/cache'; $f=$dir.'/.ch-master-key.php';
    $read=function() use($f){ if(!is_file($f)) return ''; $v=@include $f; return (is_string($v) && preg_match('/^[a-f0-9]{64}$/',$v)) ? $v : ''; };
    if(($v=$read())!=='') return $k=$v;
    if(!is_dir($dir)) @mkdir($dir,0750,true);
    $h=@fopen($f,'x');   // 'x': nie einen vorhandenen Schlüssel überschreiben (gleichzeitige Anfragen)
    if($h){ fwrite($h,"<?php return '".bin2hex(random_bytes(32))."';\n"); fclose($h); @chmod($f,0640); if(function_exists('opcache_invalidate')) @opcache_invalidate($f,true); }
    else usleep(50000);
    return $k=$read();
}
/** Verschlüsselt (AES-256-GCM) mit th_secret_master(); nur ohne jeden Schlüssel im Klartext ('p:'). */
function th_secret_box(string $v): string {
    $mk=th_secret_master();
    if($mk==='' || !function_exists('openssl_encrypt')) return 'p:'.$v;
    $k=hash('sha256','chs|'.$mk,true); $iv=random_bytes(12); $tag='';
    $c=openssl_encrypt($v,'aes-256-gcm',$k,OPENSSL_RAW_DATA,$iv,$tag); return 'e:'.base64_encode($iv.$tag.$c);
}
function th_secret_open(string $s): string {
    if(str_starts_with($s,'p:')) return substr($s,2);
    $mk=th_secret_master(); if(!str_starts_with($s,'e:') || $mk==='') return '';
    $b=base64_decode(substr($s,2),true); if($b===false || strlen($b)<29) return '';
    $k=hash('sha256','chs|'.$mk,true);
    $v=openssl_decrypt(substr($b,28),'aes-256-gcm',$k,OPENSSL_RAW_DATA,substr($b,0,12),substr($b,12,16)); return $v===false?'':$v;
}
function th_secrets_table(): void {
    static $done=false; if($done) return; $done=true;
    th_db()->exec("CREATE TABLE IF NOT EXISTS acct_secrets (account_id INT NOT NULL, k VARCHAR(40) NOT NULL, v TEXT NOT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(account_id,k)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
/** Zulässige Schlüssel: twilio_sid, twilio_token, twilio_from, meta_app_secret, claude_key, deepseek_key (eigene KI-Schlüssel, billing-lib) */
const TH_SECRET_KEYS=['twilio_sid','twilio_token','twilio_from','meta_app_secret','claude_key','deepseek_key','keepa_key'];
function th_secret(int $acc, string $k): string {
    if($acc<=0 || !in_array($k,TH_SECRET_KEYS,true)) return '';
    if(isset($GLOBALS['__th_sec'][$acc][$k])) return $GLOBALS['__th_sec'][$acc][$k];
    try{ th_secrets_table(); $s=th_db()->prepare("SELECT v FROM acct_secrets WHERE account_id=? AND k=?"); $s->execute([$acc,$k]); $v=$s->fetchColumn(); }catch(Throwable $e){ $v=false; }
    return $GLOBALS['__th_sec'][$acc][$k] = ($v===false ? '' : th_secret_open((string)$v));
}
/** Leerer Wert löscht. */
function th_secret_set(int $acc, string $k, string $v): void {
    if($acc<=0 || !in_array($k,TH_SECRET_KEYS,true)) return; th_secrets_table(); $v=trim($v); unset($GLOBALS['__th_sec'][$acc][$k]);
    if($v==='') th_db()->prepare("DELETE FROM acct_secrets WHERE account_id=? AND k=?")->execute([$acc,$k]);
    else th_db()->prepare("INSERT INTO acct_secrets(account_id,k,v) VALUES(?,?,?) ON DUPLICATE KEY UPDATE v=VALUES(v),updated_at=NOW()")->execute([$acc,$k,th_secret_box($v)]);
}
/** Für die Anzeige im Studio: nie den Wert selbst, nur „hinterlegt (…1234)“. */
function th_secret_hints(int $acc): array {
    $o=[]; foreach(TH_SECRET_KEYS as $k){ $v=th_secret($acc,$k); $o[$k]=$v==='' ? '' : (in_array($k,['twilio_sid','twilio_from'],true) ? $v : '••••'.substr($v,-4)); } return $o;
}
/**
 * Twilio-Zugang für ein Konto: eigener Account (Studio → Verbindungen) hat Vorrang, sonst der Plattform-Account aus db.php.
 * own=true heißt: Nummern/Absender gehören dem Kunden selbst.
 */
function th_twilio(int $acc): ?array {
    $sid=th_secret($acc,'twilio_sid'); $tok=th_secret($acc,'twilio_token');
    if($sid!=='' && $tok!=='') return ['sid'=>$sid,'token'=>$tok,'own'=>true,'from'=>th_secret($acc,'twilio_from')];
    if(defined('TWILIO_ACCOUNT_SID') && TWILIO_ACCOUNT_SID && defined('TWILIO_AUTH_TOKEN') && TWILIO_AUTH_TOKEN)
        return ['sid'=>TWILIO_ACCOUNT_SID,'token'=>TWILIO_AUTH_TOKEN,'own'=>false,'from'=>defined('TWILIO_SMS_FROM') ? (string)TWILIO_SMS_FROM : ''];
    return null;
}
/** Twilio-Webhook-Signatur (X-Twilio-Signature) gegen eine Liste möglicher Auth-Tokens prüfen. $url = exakt die bei Twilio hinterlegte URL. */
function th_twilio_sig_valid(string $url, array $post, array $tokens): bool {
    $sig=(string)($_SERVER['HTTP_X_TWILIO_SIGNATURE'] ?? ''); if($sig==='') return false;
    ksort($post); $data=$url; foreach($post as $k=>$v) $data.=$k.(is_array($v)?implode('',$v):$v);
    foreach(array_unique(array_filter($tokens)) as $t){ if(hash_equals(base64_encode(hash_hmac('sha1',$data,$t,true)),$sig)) return true; }
    return false;
}

/** Telefonnummer → E.164 (+491701234567). Nationale Schreibweise wird mit $cc (Standard 49) ergänzt. '' wenn unbrauchbar. */
function th_e164(string $raw, string $cc='49'): string {
    $raw=trim(preg_replace('/^(whatsapp|tel|sms):/i','',trim($raw)));
    $plus=str_starts_with($raw,'+'); $d=preg_replace('/\D/','',$raw);
    if($d==='') return '';
    if($plus) $n=$d; elseif(str_starts_with($d,'00')) $n=substr($d,2); elseif(str_starts_with($d,'0')) $n=$cc.substr($d,1); else $n=$d;
    return (strlen($n)>=8 && strlen($n)<=15 && $n[0]!=='0') ? '+'.$n : '';
}
/** Gleiche Nummer? (letzte 9 Ziffern, mind. 8) */
function th_same_phone(string $a, string $b): bool {
    $a=preg_replace('/\D/','',$a); $b=preg_replace('/\D/','',$b);
    if(strlen($a)<8 || strlen($b)<8) return false; return substr($a,-9)===substr($b,-9) || (strlen($a)===8 || strlen($b)===8 ? substr($a,-8)===substr($b,-8) : false);
}
/**
 * Einfaches Mengenlimit (z. B. öffentliche Widget-Schlüssel gegen Token-Verbrauch durch Fremde schützen).
 * Zählt UND bucht in einem Schritt; true = erlaubt. bucket z. B. "w:<acc>:<ip>".
 */
function th_rate(string $bucket, int $max, int $windowSec): bool {
    $p=th_db(); static $t=false;
    if(!$t){ $p->exec("CREATE TABLE IF NOT EXISTS rate_hits (id BIGINT AUTO_INCREMENT PRIMARY KEY, b CHAR(32) NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX(b,created_at), INDEX(created_at)) ENGINE=InnoDB"); $t=true; }
    $h=md5($bucket); if(random_int(1,200)===1) $p->exec("DELETE FROM rate_hits WHERE created_at < (NOW() - INTERVAL 1 DAY)");
    $p->prepare("INSERT INTO rate_hits(b) VALUES(?)")->execute([$h]);
    $s=$p->prepare("SELECT COUNT(*) FROM rate_hits WHERE b=? AND created_at > (NOW() - INTERVAL ? SECOND)"); $s->execute([$h,$windowSec]);
    return (int)$s->fetchColumn() <= $max;
}
/** Client-IP hinter Cloudflare */
function th_client_ip(): string { $ip=$_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '0')); return trim(explode(',',(string)$ip)[0]); }
/** Benannte DB-Sperre (z. B. eine Unterhaltung je Absender nacheinander abarbeiten). true = Sperre erhalten. */
function th_lock(string $name, int $wait=25): bool {
    try{ $s=th_db()->prepare("SELECT GET_LOCK(?,?)"); $s->execute([substr('ch:'.$name,0,64),$wait]); return (int)$s->fetchColumn()===1; }catch(Throwable $e){ return false; }
}
function th_unlock(string $name): void { try{ th_db()->prepare("SELECT RELEASE_LOCK(?)")->execute([substr('ch:'.$name,0,64)]); }catch(Throwable $e){} }

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
/**
 * Prompt-Cache (Anthropic): Grenze im Systemtext. Davor steht der feste Teil (Rolle, Wissensbasis, Regeln) – er bekommt cache_control und wird
 * mit den Werkzeugen als gemeinsamer Präfix aus dem Cache gelesen (ca. 0,1× Eingabepreis). Danach stehen wechselnde Angaben (Uhrzeit, freie Termine,
 * Anrufer, Kundenverlauf). ch_toolkit() setzt die Grenze vor seine Hinweise; Kanäle mit eigenen wechselnden Zeilen setzen sie selbst davor.
 */
const TH_AI_SPLIT = "\x1E";
function th_ai_sys_plain(string $s): string { return str_replace(TH_AI_SPLIT,'',$s); }
/** System als Blöcke: fester Teil mit cache_control, wechselnder Teil ohne (erste Grenze zählt). */
function th_ai_sys_blocks(string $s): array {
    $i=strpos($s,TH_AI_SPLIT); $fix=$i===false ? $s : substr($s,0,$i); $var=$i===false ? '' : th_ai_sys_plain(substr($s,$i+1));
    $o=[]; if(trim($fix)!=='') $o[]=['type'=>'text','text'=>$fix,'cache_control'=>['type'=>'ephemeral']];
    if(trim($var)!=='') $o[]=['type'=>'text','text'=>$var];
    return $o ?: [['type'=>'text','text'=>'-']];
}
/**
 * Datenschutz bei der Modellwahl: Claude für alles mit Personendaten (Telefon, WhatsApp, Buchungen, Kanzlei, Hotel …).
 * DeepSeek nur als Option für einfache Website-Chats ohne Personendaten (nur Info-Agenten, keine Werkzeuge, im Studio eingeschaltet)
 * und nur über einen Anbieter mit Verarbeitung in der EU (DEEPSEEK_EU_URL/KEY/MODEL, OpenAI-Format). Der frühere Ersatz über
 * api.deepseek.com (China) ist aus – nur mit CH_DEEPSEEK_CN_FALLBACK=true in db.php wieder an.
 */
const CH_AI_ECO_PLANS = ['agent-support','ec-support','log-support','agent-website','agent-content'];
function ch_ai_eco_plan(string $plan): bool { $b=function_exists('ch_plan') ? (string)((ch_plan($plan) ?? [])['base'] ?? $plan) : $plan; return in_array($b,CH_AI_ECO_PLANS,true); }
/** Dieser KI-Aufruf darf DeepSeek (EU) nutzen – setzt widget-api für berechtigte Website-Chats; sonst false. */
function th_ai_eco(?bool $on=null): bool { if($on!==null) $GLOBALS['__th_ai_eco']=$on; return !empty($GLOBALS['__th_ai_eco']); }
function th_ai_eu_cfg(): ?array {
    $u=defined('DEEPSEEK_EU_URL') ? trim((string)DEEPSEEK_EU_URL) : ''; $k=defined('DEEPSEEK_EU_KEY') ? trim((string)DEEPSEEK_EU_KEY) : '';
    if($u==='' || $k==='' || !preg_match('#^https://#',$u)) return null;
    return ['url'=>$u,'key'=>$k,'model'=>(defined('DEEPSEEK_EU_MODEL') && DEEPSEEK_EU_MODEL) ? (string)DEEPSEEK_EU_MODEL : 'deepseek-ai/DeepSeek-V3'];
}
/** Son th_ai() çağrısının bilgisi: provider, model, errors[], tool_calls[], byok (eigener Schlüssel benutzt), account, channel, usage */
function th_ai_info(): array { return $GLOBALS['__th_ai_info'] ?? []; }
/**
 * KI-Kontext des Requests: Konto (eigener KI-Schlüssel, Verbrauch) und Kanal (Kostenübersicht). Ohne Argumente nur lesen.
 * Wird von ch_agent_system() (Konto aus der Agent-Konfiguration) und ch_toolkit() (Konto + Kanal) gesetzt; Konto 0 = Plattform (Demo).
 */
function th_ai_context(?int $acc=null, ?string $channel=null): array {
    $c=$GLOBALS['__th_ai_ctx'] ?? ['acc'=>0,'channel'=>''];
    if($acc!==null){ $acc=max(0,$acc); if($acc!==(int)$c['acc']) $c['channel']=''; $c['acc']=$acc; }
    if($channel!==null) $c['channel']=substr(preg_replace('/[^a-z0-9-]/','',strtolower($channel)) ?? '',0,16);
    return $GLOBALS['__th_ai_ctx']=$c;
}
function th_ai_channel(): string {
    $c=(string)(th_ai_context()['channel'] ?? ''); if($c!=='') return $c;
    $r=function_exists('ch_req_channel') ? (string)ch_req_channel() : ''; return $r!=='' ? $r : 'other';
}
/**
 * HTTP-POST (JSON) an einen KI-Anbieter → [HTTP-Code, Antwort-Array|null, curl-Fehler].
 * Tests (nur Kommandozeile bzw. php -S): $GLOBALS['__th_ai_http_mock'] = function(string $url, array $headers, array $payload): [code, array, err].
 */
function th_ai_http(string $url, array $headers, array $payload, int $timeout): array {
    if(in_array(PHP_SAPI,['cli','cli-server'],true) && isset($GLOBALS['__th_ai_http_mock']) && is_callable($GLOBALS['__th_ai_http_mock'])){   // nur lokale Tests (nie unter FPM/LiteSpeed)
        $r=($GLOBALS['__th_ai_http_mock'])($url,$headers,$payload); return [(int)($r[0] ?? 0), is_array($r[1] ?? null) ? $r[1] : null, (string)($r[2] ?? '')];
    }
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>$timeout,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE),CURLOPT_HTTPHEADER=>$headers]);
    $raw=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $cerr=curl_error($ch); curl_close($ch);
    $r=json_decode((string)$raw,true); return [$code, is_array($r) ? $r : null, $cerr];
}
/**
 * Verlauf für die KI aufbereiten: leere Nachrichten raus, gleiche Rollen hintereinander zusammenfassen,
 * und beginnt der Verlauf mit der Begrüßung des Agenten, eine neutrale Nutzer-Zeile davor (Claude verlangt user zuerst).
 */
function th_ai_msgs(array $messages, bool $allowBlocks=false): array {
    $o=[]; foreach($messages as $m){ $role=($m['role']??'user')==='assistant'?'assistant':'user'; $c=$m['content']??'';
        if(is_array($c)){ if(!$allowBlocks) continue; if(!$c) continue; $o[]=['role'=>$role,'content'=>$c]; continue; }
        $c=trim((string)$c); if($c==='') continue;
        $n=count($o); if($n && $o[$n-1]['role']===$role && is_string($o[$n-1]['content'])){ $o[$n-1]['content'].="\n\n".$c; continue; }
        $o[]=['role'=>$role,'content'=>$c]; }
    if($o && $o[0]['role']==='assistant') array_unshift($o,['role'=>'user','content'=>'(Gespräch beginnt)']);
    return $o;
}
/**
 * Ein Endpunkt im Anthropic-Messages-Format (Plattform-Claude, eigener Claude- oder DeepSeek-Schlüssel).
 * $route = [provider, url, key, models[], byok, extra[] (z. B. thinking aus)]. Mit $tools: Werkzeug-Schleife (höchstens $maxRounds Runden).
 * $st sammelt model, errors[], auth (Schlüssel abgelehnt), auth_msg, ran (Werkzeuge wurden ausgeführt → nie auf einem anderen Weg wiederholen), usage, tool_calls[].
 */
function th_ai_route(array $route, string $system, array $msgs, ?array $tools, ?callable $exec, int $maxTokens, int $timeout, int $maxRounds, array &$st): ?string {
    $st+=['model'=>null,'errors'=>[],'auth'=>false,'auth_msg'=>'','ran'=>false,'usage'=>['in'=>0,'out'=>0,'cache_read'=>0,'cache_write'=>0],'tool_calls'=>[]];
    $hdr=['x-api-key: '.$route['key'],'anthropic-version: 2023-06-01','content-type: application/json'];
    /* Claude: fester Systemteil mit Cache-Marke + automatische Cache-Stelle am Ende des Verlaufs (Folgeschritte lesen das Gespräch aus dem Cache) */
    static $autoOff=false; $claude=($route['provider'] ?? '')==='claude';
    $sysOut=$claude ? th_ai_sys_blocks($system) : th_ai_sys_plain($system);
    $model=null; $text='';
    for($round=0;$round<max(1,$maxRounds);$round++){
        $resp=null;
        foreach($model ? [$model] : (array)$route['models'] as $m){
            $pl=['model'=>$m,'max_tokens'=>$maxTokens,'system'=>$sysOut,'messages'=>$msgs]+(array)($route['extra'] ?? []);
            if($tools) $pl['tools']=$tools;
            if($claude && !$autoOff) $pl['cache_control']=['type'=>'ephemeral'];
            [$code,$r,$cerr]=th_ai_http((string)$route['url'],$hdr,$pl,$timeout);
            if($code===400 && $claude && !$autoOff && stripos((string)($r['error']['message'] ?? ''),'cache_control')!==false){   // ohne automatische Cache-Stelle erneut (fester Teil bleibt im Cache)
                $autoOff=true; unset($pl['cache_control']); [$code,$r,$cerr]=th_ai_http((string)$route['url'],$hdr,$pl,$timeout); }
            if($code===200 && is_array($r) && is_array($r['usage'] ?? null) && function_exists('bl_usage_norm')){ foreach(bl_usage_norm($r['usage']) as $k=>$v) $st['usage'][$k]+=$v; }
            $useful=false;
            if($code===200 && is_array($r) && !empty($r['content'])) foreach((array)$r['content'] as $blk){ $ty=is_array($blk) ? ($blk['type'] ?? 'text') : ''; if(($ty==='text' && trim((string)($blk['text'] ?? ''))!=='') || $ty==='tool_use'){ $useful=true; break; } }
            if($useful){ $resp=$r; $model=$m; break; }
            if($code===200){ $st['errors'][]=$route['provider'].'/'.$m.': 200 leer (stop_reason '.($r['stop_reason'] ?? '?').')'; if($model) break; continue; }   // z. B. nur „thinking“ → nächstes Modell
            $et=(string)($r['error']['type'] ?? ''); $em=(string)($r['error']['message'] ?? $cerr);
            $st['errors'][]=$route['provider'].'/'.$m.': '.($code ?: 'curl').' '.$et.' '.mb_substr($em,0,120);
            if(in_array($code,[401,402,403],true) || in_array($et,['authentication_error','permission_error'],true)){   // Schlüsselproblem: kein weiteres Modell auf diesem Weg
                $st['auth']=true; $st['auth_msg']=$code===402 ? 'Guthaben beim Anbieter aufgebraucht' : mb_substr(trim($em) ?: 'HTTP '.$code,0,160); break 2; }
            if($model) break;   // bekanntes Modell mitten im Gespräch: nicht wechseln
            // Modell nicht gefunden, Überlast (529), Limit (429), vorübergehender Fehler → nächstes Modell
        }
        if(!$resp) break;
        $st['model']=$model;
        $toolUses=[]; $t='';
        foreach((array)$resp['content'] as $blk){ if(!is_array($blk)) continue; if(($blk['type'] ?? 'text')==='text') $t.=(string)($blk['text'] ?? ''); elseif(($blk['type'] ?? '')==='tool_use') $toolUses[]=$blk; }
        if(trim($t)!=='') $text=$t;
        if(!$tools || !$exec || ($resp['stop_reason'] ?? '')!=='tool_use' || !$toolUses) return trim($text)!=='' ? $text : null;
        $results=[]; $st['ran']=true;
        foreach($toolUses as $tu){
            $in=is_array($tu['input'] ?? null) ? $tu['input'] : [];
            try{ $out=$exec((string)$tu['name'],$in); }catch(Throwable $e){ $out=['error'=>$e->getMessage()]; }
            $ref=null; if(is_array($out)){ $ref=$out['code'] ?? null; if($ref===null) foreach($out as $v){ if(is_array($v) && isset($v['code']) && is_scalar($v['code'])){ $ref=$v['code']; break; } } }
            $st['tool_calls'][]=['name'=>$tu['name'],'input'=>$in,'ok'=>empty($out['error']),'ref'=>is_scalar($ref) ? mb_substr((string)$ref,0,40) : null];
            $results[]=['type'=>'tool_result','tool_use_id'=>$tu['id'],'content'=>json_encode($out,JSON_UNESCAPED_UNICODE)];
        }
        $msgs[]=['role'=>'assistant','content'=>$resp['content']]; $msgs[]=['role'=>'user','content'=>$results];
    }
    return trim($text)!=='' ? $text : null;
}
/**
 * Gemeinsamer Ablauf für th_ai()/th_ai_tools():
 *   1) eigener KI-Schlüssel des Kontos (billing-lib: bl_byok_routes – Claude Sonnet 5.5 bzw. DeepSeek Flash; Werkzeuge werden unterstützt),
 *      abgelehnter Schlüssel → Status „invalid“ + Push; Ersatz über die Plattform nur, wenn das Konto echtes Guthaben hat,
 *   2) Plattform-Claude (Modellkette, letzter Treffer 6 h gemerkt), 3) Plattform-DeepSeek (ohne Werkzeuge).
 * Haben Werkzeuge bereits etwas ausgeführt (Bestellung, Buchung …), wird nie auf einem anderen Weg wiederholt.
 */
function th_ai_run(string $system, array $msgs, ?array $tools, ?callable $exec, int $maxTokens, int $timeout, int $maxRounds): ?string {
    $acc=(int)(th_ai_context()['acc'] ?? 0); $channel=th_ai_channel();
    $info=['provider'=>null,'model'=>null,'errors'=>[],'tool_calls'=>[],'byok'=>false,'account'=>$acc,'channel'=>$channel,'usage'=>null];
    $done=function(?string $txt, array $st, array $rt) use(&$info,$acc,$channel){
        $info['tool_calls']=array_merge($info['tool_calls'],$st['tool_calls'] ?? []); $info['errors']=array_merge($info['errors'],$st['errors'] ?? []);
        $info['provider']=(string)$rt['provider']; $info['model']=$st['model']; $info['byok']=!empty($rt['byok']); $info['usage']=$st['usage'] ?? null;
        if(function_exists('bl_usage_log') && $st['model']) bl_usage_log($acc,$channel,(string)$rt['provider'],(string)$st['model'],!empty($rt['byok']),$st['usage']);
        $GLOBALS['__th_ai_info']=$info; return $txt;
    };
    /* 0) Einfacher Website-Chat ohne Personendaten, im Studio auf „sparsam“ gestellt: DeepSeek über EU-Anbieter, sonst weiter mit Claude */
    if(th_ai_eco() && !$tools && ($eu=th_ai_eu_cfg())){
        $plain=th_ai_msgs(array_values(array_filter($msgs,fn($m)=>is_string($m['content'] ?? null))));
        if($plain && end($plain)['role']==='user'){
            [$code,$r,$cerr]=th_ai_http($eu['url'],['Authorization: Bearer '.$eu['key'],'content-type: application/json'],['model'=>$eu['model'],'max_tokens'=>$maxTokens,'messages'=>array_merge([['role'=>'system','content'=>th_ai_sys_plain($system)]],$plain)],$timeout);
            $txt=(string)($r['choices'][0]['message']['content'] ?? '');
            if($code===200 && trim($txt)!==''){ $u=(function_exists('bl_usage_norm') && is_array($r['usage'] ?? null)) ? bl_usage_norm($r['usage']) : ['in'=>0,'out'=>0,'cache_read'=>0,'cache_write'=>0];
                return $done($txt,['model'=>'deepseek-eu','usage'=>$u],['provider'=>'deepseek-eu','byok'=>false]); }
            $info['errors'][]='deepseek-eu: '.($code ?: 'curl').' '.mb_substr((string)($r['error']['message'] ?? $cerr),0,120);
        }
    }
    /* 1) eigener Schlüssel (Vertrag des Kunden mit seinem Anbieter). Ein eigener DeepSeek-Schlüssel nur für einfache Chats ohne Personendaten
          (Website/Studio, ohne Werkzeuge, freigegebene Pakete) – Telefon, WhatsApp, Termine, Bestellungen, Kanzlei, Hotel immer über Claude */
    $routes=($acc>0 && function_exists('bl_byok_routes')) ? bl_byok_routes($acc) : [];
    $simple=!$tools && in_array($channel,['widget','studio'],true) && ch_ai_eco_plan((string)($GLOBALS['__th_ai_plan'] ?? ''));
    if(!$simple) $routes=array_values(array_filter($routes,fn($r)=>($r['provider'] ?? '')!=='deepseek'));
    foreach($routes as $rt){
        $st=[]; $txt=th_ai_route($rt,$system,$msgs,$tools,$exec,$maxTokens,$timeout,$maxRounds,$st);
        if($txt!==null || $st['ran']){ if(function_exists('bl_byok_ok')) bl_byok_ok($acc,(string)$rt['provider']); return $done($txt,$st,$rt); }
        $info['errors']=array_merge($info['errors'],$st['errors']);
        if($st['model'] && function_exists('bl_usage_log')) bl_usage_log($acc,$channel,(string)$rt['provider'],(string)$st['model'],true,$st['usage']);
        if($st['auth'] && function_exists('bl_byok_invalid')) bl_byok_invalid($acc,(string)$rt['provider'],(string)$st['auth_msg']);
    }
    if($routes){
        if(!(function_exists('bl_platform_fallback_ok') && bl_platform_fallback_ok($acc))){
            $info['errors'][]='byok: eigener KI-Schlüssel nicht nutzbar, kein ChatHelp-Guthaben für den Ersatzbetrieb'; $info['byok_failed']=true;
            $GLOBALS['__th_ai_info']=$info; error_log('CH th_ai: '.json_encode($info['errors'])); return null;
        }
        $info['byok_fallback']=true;
    }
    /* 2) Plattform-Claude */
    if(defined('CLAUDE_KEY') && CLAUDE_KEY){
        $cacheF = is_writable(__DIR__) ? __DIR__.'/.ai_model.json' : sys_get_temp_dir().'/ch_ai_model.json';
        $models=th_ai_models(); $cached=@json_decode((string)@file_get_contents($cacheF),true);
        if(!empty($cached['model']) && (time()-(int)($cached['t'] ?? 0))<21600 && in_array($cached['model'],$models,true)) $models=array_values(array_unique(array_merge([$cached['model']],$models)));
        $rt=['provider'=>'claude','url'=>'https://api.anthropic.com/v1/messages','key'=>(string)CLAUDE_KEY,'models'=>$models,'byok'=>false,'extra'=>[]];
        $st=[]; $txt=th_ai_route($rt,$system,$msgs,$tools,$exec,$maxTokens,$timeout,$maxRounds,$st);
        if($txt!==null || $st['ran']){ if($st['model'] && ($cached['model'] ?? null)!==$st['model']) @file_put_contents($cacheF,json_encode(['model'=>$st['model'],'t'=>time()])); return $done($txt,$st,$rt); }
        $info['errors']=array_merge($info['errors'],$st['errors']);
        if($tools){ $GLOBALS['__th_ai_info']=$info; return null; }   // wie bisher: Werkzeug-Gespräche nicht ohne Werkzeuge fortsetzen
    }
    /* 3) Plattform-DeepSeek über api.deepseek.com (China): nur mit ausdrücklicher Freigabe in db.php (CH_DEEPSEEK_CN_FALLBACK) – sonst kein Ersatz außerhalb der EU */
    if(defined('DEEPSEEK_KEY') && DEEPSEEK_KEY && defined('CH_DEEPSEEK_CN_FALLBACK') && CH_DEEPSEEK_CN_FALLBACK===true){
        $plain=th_ai_msgs(array_values(array_filter($msgs,fn($m)=>is_string($m['content'] ?? null))));
        if($plain && end($plain)['role']==='user'){
            [$code,$r,$cerr]=th_ai_http('https://api.deepseek.com/chat/completions',['Authorization: Bearer '.DEEPSEEK_KEY,'content-type: application/json'],
                ['model'=>'deepseek-chat','max_tokens'=>$maxTokens,'messages'=>array_merge([['role'=>'system','content'=>th_ai_sys_plain($system)]],$plain)],$timeout);
            $txt=(string)($r['choices'][0]['message']['content'] ?? '');
            if(trim($txt)!==''){ $u=(function_exists('bl_usage_norm') && is_array($r['usage'] ?? null)) ? bl_usage_norm($r['usage']) : ['in'=>0,'out'=>0,'cache_read'=>0,'cache_write'=>0];
                return $done($txt,['model'=>'deepseek-chat','usage'=>$u],['provider'=>'deepseek','byok'=>false]); }
            $info['errors'][]='deepseek-chat: '.mb_substr((string)($r['error']['message'] ?? ($cerr ?: 'yanıt yok')),0,120);
        }
    }
    $GLOBALS['__th_ai_info']=$info; error_log('CH th_ai: '.json_encode($info['errors']));
    return null;
}
/**
 * KI sohbet: eigener Schlüssel des Kontos → Claude (model zinciri) → DeepSeek. Metin ya da null döner.
 * messages = [{role:user|assistant, content}]  — son mesaj user olmalı.
 */
function th_ai(string $system, array $messages, int $maxTokens=1200, int $timeout=60): ?string {
    $msgs=th_ai_msgs($messages);
    if(!$msgs || end($msgs)['role']!=='user') return null;
    return th_ai_run($system,$msgs,null,null,$maxTokens,$timeout,1);
}

/**
 * KI mit Werkzeugen (Anthropic tool use): Modell darf Funktionen aufrufen (z. B. freie Termine lesen, Termin buchen).
 * $tools = [['name','description','input_schema'],…]  $exec(name, input) → array (wird als JSON zurückgegeben).
 * Gibt den finalen Text zurück (null bei Fehler). Infos: th_ai_info() → provider, model, tool_calls[], byok.
 * Ohne Anthropic-Weg (weder eigener Schlüssel noch CLAUDE_KEY) bzw. ohne Werkzeuge: th_ai() ohne Werkzeuge.
 */
function th_ai_tools(string $system, array $messages, array $tools, callable $exec, int $maxTokens=900, int $timeout=60, int $maxRounds=5): ?string {
    $acc=(int)(th_ai_context()['acc'] ?? 0);
    $anthropic=(defined('CLAUDE_KEY') && CLAUDE_KEY) || ($acc>0 && function_exists('bl_byok_routes') && bl_byok_routes($acc));
    if(!$anthropic || !$tools) return th_ai($system,$messages,$maxTokens,$timeout);
    $msgs=th_ai_msgs($messages,true);
    if(!$msgs || $msgs[0]['role']!=='user' || end($msgs)['role']!=='user') return null;
    return th_ai_run($system,$msgs,$tools,$exec,$maxTokens,$timeout,$maxRounds);
}

/** E-posta: Brevo HTTPS API varsa onunla, yoksa mail(). */
function th_mail(string $to, string $subject, string $html, string $text=''): bool {
    /* Absender: MAIL_FROM, sonst support@chat-help.com (Domain mit MX/SPF; SMTP_FROM zeigt ggf. auf .de) */
    $from = defined('MAIL_FROM') ? MAIL_FROM : 'support@chat-help.com'; $name = defined('SMTP_NAME') ? SMTP_NAME : 'ChatHelp';
    if(preg_match('/\.(invalid|test|example)$/i',trim($to)) || !filter_var(trim($to),FILTER_VALIDATE_EMAIL)) return true;   // Testkonten (RFC 2606) / ungültige Adressen: nichts versenden, keine Bounces
    if(defined('BREVO_KEY') && BREVO_KEY){
        $ch=curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>20,
            CURLOPT_POSTFIELDS=>json_encode(['sender'=>['name'=>$name,'email'=>$from],'to'=>[['email'=>$to]],'subject'=>$subject,'htmlContent'=>$html]+($text!==''?['textContent'=>$text]:[])),
            CURLOPT_HTTPHEADER=>['accept: application/json','api-key: '.BREVO_KEY,'content-type: application/json']]);
        curl_exec($ch); $c=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); if($c>=200&&$c<300) return true;
    }
    /* -f: Envelope-Absender = From (im Web-Kontext sonst www-data@host → Relay/Empfänger verwerfen die Mail) */
    $ctype="Content-Type: text/html; charset=UTF-8"; $body=$html;
    if($text!==''){   // multipart/alternative: Klartext (Code-Erkennung in Mail-Apps, Vorschau) + HTML
        $bd='=_ch'.bin2hex(random_bytes(8)); $ctype="Content-Type: multipart/alternative; boundary=\"$bd\"";
        $body="--$bd\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($text))
             ."--$bd\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($html))."--$bd--\r\n";
    }
    $hdr="MIME-Version: 1.0\r\n$ctype\r\nFrom: $name <$from>\r\nReply-To: $from\r\nX-Mailer: ChatHelp";
    $ok = @mail($to,'=?UTF-8?B?'.base64_encode($subject).'?=',$body,$hdr,'-f'.$from);
    if(!$ok) $ok = @mail($to,'=?UTF-8?B?'.base64_encode($subject).'?=',$body,$hdr);
    return $ok;
}

/** Dinamik yanıtlar Cloudflare/platform önbelleğine girmesin (platform varsayılanı: public, 31 gün). */
function th_nocache(): void {
    if(headers_sent()) return;
    header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0'); header('Cloudflare-CDN-Cache-Control: no-store'); header('CDN-Cache-Control: no-store'); header('Pragma: no-cache'); header('Expires: 0'); header('Vary: Authorization, Cookie');
    /* Host Europe/GoDaddy MWP: Set-Cookie içeren yanıtlar platform/edge önbelleğine alınmaz (Cache-Control tek başına yetmiyor) */
    header('Set-Cookie: chnc=1; Path=/; Max-Age=3600; Secure; HttpOnly; SameSite=Lax', false);
}
/**
 * DB-Zeitstempel (Spalten mit DEFAULT CURRENT_TIMESTAMP / NOW(), also in der Zeitzone des DB-Servers) → Zeitpunkt in $tz.
 * Der DB-Versatz zu UTC wird einmal je Request gemessen (funktioniert unabhängig davon, ob MySQL in UTC oder Europe/Berlin läuft).
 */
function th_dbtime(?string $s, DateTimeZone $tz): ?DateTimeImmutable {
    if($s===null || $s==='' || $s==='0000-00-00 00:00:00') return null; static $off=null;
    if($off===null){ try{ $off=(int)round(((int)th_db()->query("SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW())")->fetchColumn())/900)*900; }catch(Throwable $e){ $off=0; } }
    try{ return (new DateTimeImmutable($s,new DateTimeZone('UTC')))->modify((-$off).' seconds')->setTimezone($tz); }catch(Throwable $e){ return null; }
}
/** DB-Zeitstempel → ISO 8601 in UTC (für JavaScript: new Date(iso) zeigt dann die richtige Ortszeit). */
function th_dbiso(?string $s): ?string { $d=th_dbtime($s,new DateTimeZone('UTC')); return $d ? $d->format('Y-m-d\TH:i:s\Z') : null; }
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
