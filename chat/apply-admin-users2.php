<?php
/**
 * ChatHelp — apply-admin-users2 (CH_ADMIN_USERS2) — ADIM 2: kullanici SILME,
 *  hesap BLOKE/kaldir, IP BLOKE/kaldir arayuzu + admin.php<->admin-users.php
 *  capraz baglanti. ONCE apply-admin-block-ip.php calistirilmis olmali
 *  (blocked/reg_ip/last_ip sutunlari + blocked_ips tablosu).
 *  [A] admin.php'ye "👥 Kunden" baglantisi eklenir (mevcut EXACT metne anchor).
 *  [B] admin-users.php TAMAMEN YENIDEN URETILIR (CH_ADMIN_USERS'in ustune):
 *      - Her kullanicida reg_ip/last_ip gosterilir
 *      - 🗑 Löschen (onay istemli, cascade: user_plans/user_profiles/
 *        free_grants/password_resets/users)
 *      - 🔒 Sperren / 🔓 Entsperren (hesap bloke)
 *      - 🚫 IP sperren (kullanicinin son IP'sini blocked_ips'e ekler)
 *      - Ayri "Gesperrte IPs" bolumu: liste + entsperren + elle IP ekleme
 * KULLANIM: pull2.php?key=...&files=apply-admin-users2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-admin-users2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

if (!is_file(__DIR__.'/db.php'))   exit("HATA: db.php yok.\n");
if (!is_file(__DIR__.'/auth.php')) exit("HATA: auth.php yok.\n");

/* ── [A] admin.php: capraz baglanti ── */
$adminFile = __DIR__.'/admin.php';
$adminSrc = @file_get_contents($adminFile);
if ($adminSrc===false) exit("HATA: admin.php okunamadi\n");
if (strpos($adminSrc,'admin-users.php')===false) {
  $oA = <<<'ANC'
  <div class="top-bar">
    <div style="font-size:13px;color:var(--t3)">✅ Eingeloggt</div>
    <a href="?logout" class="btn-logout">
ANC;
  $nA = <<<'ANC'
  <div class="top-bar">
    <div style="font-size:13px;color:var(--t3)">✅ Eingeloggt</div>
    <a href="admin-users.php" class="btn-logout" style="background:rgba(212,168,74,.14);border-color:rgba(212,168,74,.4);color:#d4a84a">👥 Kunden</a>
    <a href="?logout" class="btn-logout">
ANC;
  $cA = substr_count($adminSrc,$oA);
  if ($cA!==1) { echo "  ✗ [A] admin.php top-bar anchor ($cA) — atlaniyor, admin.php DEGISMEDI.\n"; }
  else {
    $adminSrc2 = str_replace($oA,$nA,$adminSrc);
    $tmpA = tempnam(sys_get_temp_dir(),'aa').'.php';
    file_put_contents($tmpA,$adminSrc2);
    $loA=[];$rcA=0; exec('php -l '.escapeshellarg($tmpA).' 2>&1',$loA,$rcA); @unlink($tmpA);
    if ($rcA!==0) { echo "  ✗ [A] admin.php LINT HATASI — DEGISTIRILMEDI:\n    ".implode("\n    ",$loA)."\n"; }
    else {
      @copy($adminFile,$adminFile.'.bak-'.date('Ymd-His'));
      @file_put_contents($adminFile,$adminSrc2);
      echo "  ✓ [A] admin.php'ye '👥 Kunden' baglantisi eklendi\n";
    }
  }
} else echo "  ✓ [A] admin.php zaten baglantili (atlandi)\n";

/* ── [B] admin-users.php yeniden uret ── */
$target = __DIR__.'/admin-users.php';

$page = <<<'ADMINPHP'
<?php
/* ChatHelp — Musteri & Paket Yonetimi (admin-users.php) — CH_ADMIN_USERS2 */
session_start();
@require_once __DIR__.'/config.php';
require_once __DIR__.'/db.php';

$ADMIN_PW = (defined('ADMIN_PW') && ADMIN_PW) ? ADMIN_PW : 'chathelp2026';

/* ── logout ── */
if (isset($_GET['logout'])) { session_destroy(); header('Location: admin-users.php'); exit; }

/* ── login ── */
$loginError='';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['password']) && !isset($_SESSION['au_ok'])) {
    if (hash_equals($ADMIN_PW, (string)$_POST['password'])) { $_SESSION['au_ok']=true; header('Location: admin-users.php'); exit; }
    else $loginError='Falsches Passwort';
}
$authed = !empty($_SESSION['au_ok']);

/* ── actions (nur eingeloggt) ── */
$msg=''; $msgType='ok';
if ($authed && $_SERVER['REQUEST_METHOD']==='POST') {
    try {
        $pdo = db();
        if (isset($_POST['set_plan'])) {
            $uid  = (int)($_POST['user_id'] ?? 0);
            $plan = $_POST['plan'] ?? '';
            $allowed = ['free','basic','pro','elite'];
            if ($uid<=0 || !in_array($plan,$allowed,true)) throw new Exception('Ungültige Eingabe.');
            if ($plan==='free') {
                $pdo->prepare('DELETE FROM user_plans WHERE user_id=?')->execute([$uid]);
            } else {
                $pdo->prepare('INSERT INTO user_plans (user_id, plan, status, starts_at) VALUES (?, ?, \'active\', CURDATE()) ON DUPLICATE KEY UPDATE plan=VALUES(plan), status=\'active\'')->execute([$uid,$plan]);
            }
            $msg="Plan aktualisiert: Nutzer #$uid → ".strtoupper($plan);
        }
        elseif (isset($_POST['update_email'])) {
            $uid = (int)($_POST['user_id'] ?? 0);
            $em  = strtolower(trim($_POST['email'] ?? ''));
            if ($uid<=0 || !filter_var($em, FILTER_VALIDATE_EMAIL)) throw new Exception('Ungültige E-Mail.');
            $chk=$pdo->prepare('SELECT id FROM users WHERE email=? AND id<>?'); $chk->execute([$em,$uid]);
            if ($chk->fetch()) throw new Exception('Diese E-Mail ist bereits bei einem anderen Konto.');
            $pdo->prepare('UPDATE users SET email=? WHERE id=?')->execute([$em,$uid]);
            $msg="E-Mail aktualisiert: Nutzer #$uid → ".$em;
        }
        elseif (isset($_POST['verify_user'])) {
            $uid=(int)($_POST['user_id'] ?? 0);
            $pdo->prepare('UPDATE users SET email_verified=1, verify_token=NULL WHERE id=?')->execute([$uid]);
            $msg="Nutzer #$uid als verifiziert markiert.";
        }
        elseif (isset($_POST['block_user'])) {
            $uid=(int)($_POST['user_id'] ?? 0);
            $pdo->prepare('UPDATE users SET blocked=1 WHERE id=?')->execute([$uid]);
            $msg="Nutzer #$uid gesperrt.";
        }
        elseif (isset($_POST['unblock_user'])) {
            $uid=(int)($_POST['user_id'] ?? 0);
            $pdo->prepare('UPDATE users SET blocked=0 WHERE id=?')->execute([$uid]);
            $msg="Nutzer #$uid entsperrt.";
        }
        elseif (isset($_POST['delete_user'])) {
            $uid=(int)($_POST['user_id'] ?? 0);
            if ($uid<=0) throw new Exception('Ungültige Nutzer-ID.');
            foreach (['user_plans','user_profiles','free_grants'] as $t) {
                try { $pdo->prepare("DELETE FROM $t WHERE user_id=?")->execute([$uid]); } catch (Throwable $e) {}
            }
            try {
                $em=$pdo->prepare('SELECT email FROM users WHERE id=?'); $em->execute([$uid]); $emv=$em->fetch();
                if ($emv) $pdo->prepare('DELETE FROM password_resets WHERE user_id=?')->execute([$uid]);
            } catch (Throwable $e) {}
            $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$uid]);
            $msg="Nutzer #$uid endgültig gelöscht.";
        }
        elseif (isset($_POST['block_ip'])) {
            $ip = trim($_POST['ip'] ?? '');
            if (!filter_var($ip, FILTER_VALIDATE_IP)) throw new Exception('Ungültige IP-Adresse.');
            $reason = trim($_POST['reason'] ?? '');
            $pdo->prepare('INSERT INTO blocked_ips (ip, reason) VALUES (?, ?) ON DUPLICATE KEY UPDATE reason=VALUES(reason)')->execute([$ip, $reason ?: null]);
            $msg="IP gesperrt: $ip";
        }
        elseif (isset($_POST['unblock_ip'])) {
            $ip = trim($_POST['ip'] ?? '');
            $pdo->prepare('DELETE FROM blocked_ips WHERE ip=?')->execute([$ip]);
            $msg="IP entsperrt: $ip";
        }
    } catch (Throwable $e) { $msg=$e->getMessage(); $msgType='err'; }
}

/* ── daten ── */
$rows=[]; $counts=['free'=>0,'basic'=>0,'pro'=>0,'elite'=>0]; $total=0; $q=''; $blockedIps=[];
$hasBlockCols = false;
if ($authed) {
    try {
        $pdo=db(); $q=trim($_GET['q'] ?? '');
        $cols = array_map(function($c){ return $c['Field']; }, $pdo->query('SHOW COLUMNS FROM users')->fetchAll());
        $hasBlockCols = in_array('blocked',$cols) && in_array('reg_ip',$cols) && in_array('last_ip',$cols);
        $extraCols = $hasBlockCols ? ', u.blocked, u.reg_ip, u.last_ip' : '';
        $sql='SELECT u.id,u.email,u.name,u.email_verified,u.created_at'.$extraCols.',
                (SELECT p.plan FROM user_plans p WHERE p.user_id=u.id AND p.status=\'active\' ORDER BY p.id DESC LIMIT 1) AS plan
              FROM users u';
        $args=[];
        if ($q!==''){ $sql.=' WHERE u.email LIKE ? OR u.name LIKE ?'; $args=["%$q%","%$q%"]; }
        $sql.=' ORDER BY u.id DESC LIMIT 300';
        $st=$pdo->prepare($sql); $st->execute($args); $rows=$st->fetchAll();
        $ct=$pdo->query('SELECT (SELECT p.plan FROM user_plans p WHERE p.user_id=u.id AND p.status=\'active\' ORDER BY p.id DESC LIMIT 1) AS plan FROM users u');
        foreach ($ct as $r){ $pl=$r['plan']?:'free'; if(isset($counts[$pl])) $counts[$pl]++; $total++; }
        if ($hasBlockCols) {
            try { $blockedIps = $pdo->query('SELECT ip, reason, blocked_at FROM blocked_ips ORDER BY blocked_at DESC LIMIT 200')->fetchAll(); } catch (Throwable $e) {}
        }
    } catch (Throwable $e) { $msg='DB-Fehler: '.$e->getMessage(); $msgType='err'; }
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ChatHelp — Kunden & Pakete</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#07070e;color:#f5f5ff;font-family:'Inter',system-ui,sans-serif;padding:24px 14px;-webkit-font-smoothing:antialiased}
.wrap{max-width:1000px;margin:0 auto}
.hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:10px}
.hd h1{font-size:19px;font-weight:800}.hd h1 em{font-style:normal;color:#d4a84a}
.hd .r{display:flex;gap:8px;align-items:center}
a.lnk,button,input,select{font-family:inherit}
.btn{background:linear-gradient(135deg,#d4a84a,#ecc060);color:#07070e;border:none;border-radius:9px;padding:9px 14px;font-size:12.5px;font-weight:700;cursor:pointer}
.btn.sm{padding:6px 10px;font-size:11.5px}
.btn.gray{background:#1a1a30;color:#c0c0e0;border:1px solid rgba(255,255,255,.1)}
.btn.danger{background:#3a1414;color:#ff9090;border:1px solid rgba(224,80,80,.35)}
.btn.warn{background:#3a2e14;color:#ffc978;border:1px solid rgba(224,160,80,.35)}
.login{max-width:360px;margin:60px auto;background:#0d0d1c;border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:30px;text-align:center}
.login h1{font-size:19px;margin-bottom:6px}.login p{color:#9090b8;font-size:13px;margin-bottom:18px}
.login input{width:100%;background:#131325;border:1.5px solid rgba(255,255,255,.1);border-radius:10px;padding:12px;color:#fff;font-size:15px;text-align:center;letter-spacing:2px;outline:none;margin-bottom:10px}
.login .err{background:rgba(224,80,80,.1);border:1px solid rgba(224,80,80,.25);color:#ff8a8a;border-radius:8px;padding:9px;font-size:12.5px;margin-top:10px}
.sumrow{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px}
.chip{background:#0d0d1c;border:1px solid rgba(255,255,255,.08);border-radius:11px;padding:11px 15px;flex:1;min-width:110px}
.chip .n{font-size:22px;font-weight:800}.chip .l{font-size:11px;color:#9090b8;text-transform:uppercase;letter-spacing:.6px;margin-top:2px}
.chip.free .n{color:#9090b8}.chip.basic .n{color:#d4a84a}.chip.pro .n{color:#6094ff}.chip.elite .n{color:#c090ff}.chip.total .n{color:#42df94}
.searchbar{display:flex;gap:8px;margin-bottom:14px}
.searchbar input{flex:1;background:#131325;border:1.5px solid rgba(255,255,255,.1);border-radius:10px;padding:10px 14px;color:#fff;font-size:13.5px;outline:none}
.msg{border-radius:10px;padding:11px 15px;font-size:13px;margin-bottom:16px}
.msg.ok{background:rgba(66,223,148,.09);border:1px solid rgba(66,223,148,.28);color:#7fe9b6}
.msg.err{background:rgba(224,80,80,.09);border:1px solid rgba(224,80,80,.28);color:#ff8a8a}
.tbl{background:#0d0d1c;border:1px solid rgba(255,255,255,.08);border-radius:14px;overflow:hidden;margin-bottom:24px}
.urow{border-bottom:1px solid rgba(255,255,255,.06);padding:14px 16px}
.urow:last-child{border-bottom:none}
.urow.blocked{background:rgba(224,80,80,.05)}
.utop{display:flex;justify-content:space-between;align-items:baseline;gap:10px;flex-wrap:wrap}
.uid{font-size:11px;color:#505078}
.uname{font-weight:700;font-size:14px}
.uplan{font-size:10px;font-weight:800;letter-spacing:.6px;padding:3px 9px;border-radius:100px;text-transform:uppercase}
.uplan.free{color:#9090b8;border:1px solid rgba(255,255,255,.15)}
.uplan.basic{color:#d4a84a;border:1px solid rgba(212,168,74,.4)}
.uplan.pro{color:#6094ff;border:1px solid rgba(96,148,255,.4)}
.uplan.elite{color:#c090ff;border:1px solid rgba(192,144,255,.4)}
.ublocked{font-size:10px;font-weight:800;letter-spacing:.6px;padding:3px 9px;border-radius:100px;text-transform:uppercase;color:#ff9090;border:1px solid rgba(224,80,80,.4)}
.uip{font-size:10.5px;color:#707098;margin-top:4px;font-family:monospace}
.uforms{display:flex;gap:10px;flex-wrap:wrap;margin-top:10px}
.uf{display:flex;gap:6px;align-items:center;background:#0a0a16;border:1px solid rgba(255,255,255,.06);border-radius:10px;padding:7px 9px}
.uf input,.uf select{background:#131325;border:1px solid rgba(255,255,255,.12);border-radius:8px;padding:6px 9px;color:#fff;font-size:12.5px;outline:none}
.uf input[type=email]{min-width:200px}
.uf label{font-size:10px;color:#707098;text-transform:uppercase;letter-spacing:.5px}
.uv{font-size:11px}.uv.y{color:#42df94}.uv.n{color:#e0a050}
.hint{font-size:11.5px;color:#707098;margin-top:14px;line-height:1.6;margin-bottom:30px}
.sec-t{font-size:14px;font-weight:800;margin:0 0 12px;display:flex;align-items:center;gap:8px}
.iprow{display:flex;justify-content:space-between;align-items:center;padding:11px 16px;border-bottom:1px solid rgba(255,255,255,.06);gap:10px;flex-wrap:wrap}
.iprow:last-child{border-bottom:none}
.ipv{font-family:monospace;font-size:13px;font-weight:700}
.ipr{font-size:11.5px;color:#9090b8}
.addipf{display:flex;gap:8px;padding:14px 16px;flex-wrap:wrap}
.addipf input{background:#131325;border:1px solid rgba(255,255,255,.12);border-radius:8px;padding:8px 10px;color:#fff;font-size:12.5px;outline:none}
.addipf input[name=ip]{width:150px;font-family:monospace}
.addipf input[name=reason]{flex:1;min-width:150px}
</style></head><body><div class="wrap">
<?php if (!$authed): ?>
  <div class="login">
    <h1>🔐 Kunden-Verwaltung</h1>
    <p>Bitte Admin-Passwort eingeben</p>
    <form method="post">
      <input type="password" name="password" placeholder="Passwort" autofocus>
      <button class="btn" type="submit" style="width:100%">Anmelden</button>
      <?php if($loginError): ?><div class="err"><?=h($loginError)?></div><?php endif; ?>
    </form>
  </div>
<?php else: ?>
  <div class="hd">
    <h1>👥 Chat<em>Help</em> — Kunden &amp; Pakete</h1>
    <div class="r">
      <a class="lnk" href="admin.php"><button class="btn gray sm" type="button">🔑 API-Keys</button></a>
      <a class="lnk" href="?logout=1"><button class="btn gray sm" type="button">Abmelden</button></a>
    </div>
  </div>

  <?php if($msg): ?><div class="msg <?=$msgType?>"><?=h($msg)?></div><?php endif; ?>
  <?php if(!$hasBlockCols): ?><div class="msg err">⚠ Sperr-Funktionen benötigen apply-admin-block-ip.php (noch nicht ausgeführt).</div><?php endif; ?>

  <div class="sumrow">
    <div class="chip total"><div class="n"><?=$total?></div><div class="l">Kunden</div></div>
    <div class="chip free"><div class="n"><?=$counts['free']?></div><div class="l">Free</div></div>
    <div class="chip basic"><div class="n"><?=$counts['basic']?></div><div class="l">Basic</div></div>
    <div class="chip pro"><div class="n"><?=$counts['pro']?></div><div class="l">Pro</div></div>
    <div class="chip elite"><div class="n"><?=$counts['elite']?></div><div class="l">Elite</div></div>
  </div>

  <form method="get" class="searchbar">
    <input type="text" name="q" value="<?=h($q)?>" placeholder="Suche nach E-Mail oder Name…">
    <button class="btn" type="submit">Suchen</button>
    <?php if($q!==''): ?><a class="lnk" href="admin-users.php"><button class="btn gray" type="button">Reset</button></a><?php endif; ?>
  </form>

  <div class="tbl">
    <?php if(!$rows): ?>
      <div class="urow" style="color:#707098">Keine Kunden gefunden.</div>
    <?php else: foreach($rows as $u): $pl=$u['plan']?:'free'; $isBlocked=!empty($u['blocked']); $lastIp=$u['last_ip']??''; $regIp=$u['reg_ip']??''; ?>
      <div class="urow<?=$isBlocked?' blocked':''?>">
        <div class="utop">
          <div>
            <span class="uid">#<?=h($u['id'])?></span>
            <span class="uname"><?=h($u['name']?:'—')?></span>
            &nbsp;·&nbsp; <span style="color:#c0c0e0;font-size:13px"><?=h($u['email'])?></span>
            <?php if($hasBlockCols && ($lastIp||$regIp)): ?><div class="uip">IP: <?=h($lastIp?:$regIp)?><?=($regIp && $lastIp && $regIp!==$lastIp)?' (Registrierung: '.h($regIp).')':''?></div><?php endif; ?>
          </div>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <span class="uplan <?=h($pl)?>"><?=h($pl)?></span>
            <span class="uv <?=$u['email_verified']?'y':'n'?>"><?=$u['email_verified']?'✓ verifiziert':'✗ unbestätigt'?></span>
            <?php if($isBlocked): ?><span class="ublocked">🔒 gesperrt</span><?php endif; ?>
          </div>
        </div>
        <div class="uforms">
          <form method="post" class="uf">
            <input type="hidden" name="user_id" value="<?=h($u['id'])?>">
            <label>Plan</label>
            <select name="plan">
              <?php foreach(['free','basic','pro','elite'] as $p): ?>
                <option value="<?=$p?>" <?=$p===$pl?'selected':''?>><?=ucfirst($p)?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn sm" type="submit" name="set_plan" value="1">Setzen</button>
          </form>
          <form method="post" class="uf">
            <input type="hidden" name="user_id" value="<?=h($u['id'])?>">
            <label>E-Mail</label>
            <input type="email" name="email" value="<?=h($u['email'])?>">
            <button class="btn sm" type="submit" name="update_email" value="1">Ändern</button>
          </form>
          <?php if(!$u['email_verified']): ?>
          <form method="post" class="uf">
            <input type="hidden" name="user_id" value="<?=h($u['id'])?>">
            <button class="btn sm gray" type="submit" name="verify_user" value="1">✓ Verifizieren</button>
          </form>
          <?php endif; ?>
          <?php if($hasBlockCols): ?>
            <?php if($isBlocked): ?>
            <form method="post" class="uf">
              <input type="hidden" name="user_id" value="<?=h($u['id'])?>">
              <button class="btn sm gray" type="submit" name="unblock_user" value="1">🔓 Entsperren</button>
            </form>
            <?php else: ?>
            <form method="post" class="uf" onsubmit="return confirm('Konto #<?=h($u['id'])?> wirklich sperren?');">
              <input type="hidden" name="user_id" value="<?=h($u['id'])?>">
              <button class="btn sm warn" type="submit" name="block_user" value="1">🔒 Sperren</button>
            </form>
            <?php endif; ?>
            <?php if($lastIp||$regIp): ?>
            <form method="post" class="uf" onsubmit="return confirm('IP <?=h($lastIp?:$regIp)?> komplett sperren? Betrifft ALLE Konten von dieser IP.');">
              <input type="hidden" name="ip" value="<?=h($lastIp?:$regIp)?>">
              <input type="hidden" name="reason" value="Von Nutzer #<?=h($u['id'])?> (<?=h($u['email'])?>)">
              <button class="btn sm warn" type="submit" name="block_ip" value="1">🚫 IP sperren</button>
            </form>
            <?php endif; ?>
          <?php endif; ?>
          <form method="post" class="uf" onsubmit="return confirm('Konto #<?=h($u['id'])?> (<?=h($u['email'])?>) ENDGÜLTIG löschen? Das kann nicht rückgängig gemacht werden.');">
            <input type="hidden" name="user_id" value="<?=h($u['id'])?>">
            <button class="btn sm danger" type="submit" name="delete_user" value="1">🗑 Löschen</button>
          </form>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <?php if($hasBlockCols): ?>
  <div class="sec-t">🚫 Gesperrte IP-Adressen</div>
  <div class="tbl">
    <div class="addipf">
      <form method="post" style="display:flex;gap:8px;flex:1;flex-wrap:wrap">
        <input type="text" name="ip" placeholder="IP-Adresse (z.B. 1.2.3.4)" required>
        <input type="text" name="reason" placeholder="Grund (optional)">
        <button class="btn sm warn" type="submit" name="block_ip" value="1">+ IP sperren</button>
      </form>
    </div>
    <?php if(!$blockedIps): ?>
      <div class="iprow" style="color:#707098">Keine IPs gesperrt.</div>
    <?php else: foreach($blockedIps as $bi): ?>
      <div class="iprow">
        <div>
          <span class="ipv"><?=h($bi['ip'])?></span>
          <?php if($bi['reason']): ?><span class="ipr">— <?=h($bi['reason'])?></span><?php endif; ?>
          <span class="ipr">· <?=h($bi['blocked_at'])?></span>
        </div>
        <form method="post">
          <input type="hidden" name="ip" value="<?=h($bi['ip'])?>">
          <button class="btn sm gray" type="submit" name="unblock_ip" value="1">Entsperren</button>
        </form>
      </div>
    <?php endforeach; endif; ?>
  </div>
  <?php endif; ?>

  <div class="hint">
    • <b>Plan setzen</b> weist dem Kunden sofort das gewählte Paket zu (jüngster Eintrag zählt).<br>
    • <b>Sperren</b> verhindert die Anmeldung des Kontos sofort (Konto bleibt erhalten).<br>
    • <b>IP sperren</b> verhindert Registrierung/Anmeldung von dieser IP für JEDES Konto.<br>
    • <b>Löschen</b> entfernt Konto, Profile, Pläne und Freikontingent unwiderruflich.<br>
    • <b>E-Mail ändern</b> korrigiert eine falsch eingegebene Adresse (z.B. bei Zahlungen). Die Stripe-Kunden-E-Mail ändern Sie zusätzlich im Stripe-Dashboard.<br>
    • Es werden die 300 neuesten Kunden angezeigt; nutzen Sie die Suche für ältere.
  </div>
<?php endif; ?>
</div></body></html>
ADMINPHP;

/* ── PHP lint on temp before write ── */
$tmp = tempnam(sys_get_temp_dir(),'au').'.php';
file_put_contents($tmp,$page);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — admin-users.php YAZILMADI:\n  ".implode("\n  ",$lo)."\n"; exit; }

if (is_file($target)) @copy($target,$target.'.bak-'.date('Ymd-His'));
$w=@file_put_contents($target,$page);
if ($w===false || $w<strlen($page)) { echo "\n✗ YAZMA HATASI (admin-users.php).\n"; exit; }
echo "  ✓ [B] admin-users.php yeniden uretildi (".strlen($page)." bayt)\n";
echo "  ✓ Silme + Bloke/Entsperren + IP sperren/entsperren eklendi\n";

echo "\n✓ CH_ADMIN_USERS2 hazir. Adres: https://chat-help.com/chat/admin-users.php\n";
echo "   NOT: apply-admin-block-ip.php ONCE calistirilmadiysa, sperr-fonksiyonlari\n";
echo "   sayfada gizli kalir (uyari gosterilir) — DB veri kaybi olmaz.\n";
