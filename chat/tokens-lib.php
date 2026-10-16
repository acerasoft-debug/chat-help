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
