<?php
/**
 * ChatHelp — apply-freedoc-be (CH_FREEDOC) — 1 ucretsiz belge kotasi BACKEND
 *  1) free_grants tablosunu olusturur (user_id UNIQUE + ip): kullanici basina 1
 *     VE IP basina 1 ucretsiz belge. 2) auth.php'ye EKLEMELI (mevcut mantik
 *     degismez): 'free_status' (bak) + 'free_use' (tuket) router case'leri +
 *     doFreeStatus/doFreeUse/ch_ip fonksiyonlari. JWT zorunlu (requireAuth).
 *  Ucretli plan (basic/pro/elite) bu kotadan etkilenmez.
 * KULLANIM: pull2.php?key=...&files=apply-freedoc-be.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-freedoc-be BASLADI OK (PHP ".PHP_VERSION.")\n\n";

/* ── [0] free_grants tablosu ── */
@require_once __DIR__.'/config.php';
@require_once __DIR__.'/db.php';
if (!function_exists('db')) exit("HATA: db() yuklenemedi (config/db.php)\n");
try {
    db()->exec("CREATE TABLE IF NOT EXISTS free_grants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        ip VARCHAR(45) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user (user_id),
        KEY idx_ip (ip)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "  ✓ [0] free_grants tablosu hazir (CREATE TABLE IF NOT EXISTS)\n";
} catch (Throwable $e) { exit("HATA [0]: tablo olusturulamadi: ".$e->getMessage()."\n"); }

/* ── auth.php ── */
$authFile = __DIR__.'/auth.php';
$src = @file_get_contents($authFile);
if ($src===false) exit("HATA: auth.php okunamadi\n");
if (strpos($src,'CH_FREEDOC')!==false) exit("Zaten ekli (CH_FREEDOC).\n");

$done=0;

/* [1] router case'leri — billing_portal satirindan sonra (yoksa doMe'den sonra) */
$anchorBP = "'billing_portal' => doBillingPortal(),";
$anchorInsert = "\n    'free_status'    => doFreeStatus(),\n    'free_use'       => doFreeUse(),";
if (strpos($src,$anchorBP)!==false) {
    $src = str_replace($anchorBP, $anchorBP.$anchorInsert, $src);
    echo "  ✓ [1] router'a free_status + free_use eklendi (billing_portal sonrasi)\n"; $done++;
} else {
    $cnt=0;
    $src = preg_replace("/('me'\\s*=>\\s*doMe\\(\\)\\s*,)/", "$1".addcslashes($anchorInsert,'\\$'), $src, 1, $cnt);
    if ($cnt===1) { echo "  ✓ [1] router'a free_status + free_use eklendi (doMe sonrasi)\n"; $done++; }
    else exit("HATA: [1] router anchor bulunamadi — iptal\n");
}

/* [2] fonksiyonlar — dosya sonuna */
$fn = <<<'PHPFN'

/* CH_FREEDOC — 1 kostenloses Dokument pro Nutzer & pro IP (server-seitig) */
function ch_ip(): string {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');
    $ip = trim(explode(',', (string)$ip)[0]);
    if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    $r = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var($r, FILTER_VALIDATE_IP) ? $r : '0.0.0.0';
}
function doFreeStatus(): void {
    $u = requireAuth();
    if (in_array($u['plan'], ['basic','pro','elite'], true)) respond(['available'=>false,'reason'=>'plan']);
    try {
        $pdo = db(); $ip = ch_ip();
        $st = $pdo->prepare('SELECT 1 FROM free_grants WHERE user_id=? OR ip=? LIMIT 1');
        $st->execute([$u['id'], $ip]);
        respond(['available'=> !$st->fetch()]);
    } catch (Throwable $e) { respond(['available'=>false,'error'=>'db']); }
}
function doFreeUse(): void {
    $u = requireAuth();
    if (in_array($u['plan'], ['basic','pro','elite'], true)) respond(['ok'=>false,'reason'=>'plan']);
    try {
        $pdo = db(); $ip = ch_ip();
        $st = $pdo->prepare('SELECT 1 FROM free_grants WHERE user_id=? LIMIT 1');
        $st->execute([$u['id']]);
        if ($st->fetch()) respond(['ok'=>false,'reason'=>'user']);
        $st = $pdo->prepare('SELECT COUNT(*) c FROM free_grants WHERE ip=?');
        $st->execute([$ip]);
        if ((int)($st->fetch()['c'] ?? 0) >= 1) respond(['ok'=>false,'reason'=>'ip']);
        try { $pdo->prepare('INSERT INTO free_grants (user_id, ip) VALUES (?, ?)')->execute([$u['id'], $ip]); }
        catch (Throwable $e) { respond(['ok'=>false,'reason'=>'race']); }
        respond(['ok'=>true]);
    } catch (Throwable $e) { respond(['ok'=>false,'error'=>'db']); }
}
PHPFN;

$closeTag = strrpos($src, '?>');
if ($closeTag !== false && trim(substr($src,$closeTag+2))==='') {
    $src = substr($src,0,$closeTag).$fn."\n?>".substr($src,$closeTag+2);
} else {
    $src = rtrim($src)."\n".$fn."\n";
}
echo "  ✓ [2] doFreeStatus/doFreeUse/ch_ip eklendi\n"; $done++;

$src .= "\n/* CH_FREEDOC applied */\n";

/* lint + yaz */
$tmp = tempnam(sys_get_temp_dir(),'fd').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — auth.php DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
$bk=@file_put_contents($authFile.'.bak-freedoc-'.date('Ymd-His'), @file_get_contents($authFile));
if ($bk===false) echo "  ⚠ yedek yazilamadi — devam\n";
$w=@file_put_contents($authFile,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($authFile);
if (strpos($chk,'CH_FREEDOC')===false || strpos($chk,'function doFreeUse')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_FREEDOC + doFreeUse() diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Backend hazir. Endpointler: auth.php?action=free_status | free_use (JWT).\n";
echo "   Kural: kullanici basina 1 + IP basina 1 ucretsiz belge. Frontend ayri yamada.\n";
