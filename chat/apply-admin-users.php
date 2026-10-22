<?php
/**
 * ChatHelp — apply-admin-users (CH_ADMIN_USERS) — musteri + paket yonetimi paneli
 *  Mevcut admin.php'ye DOKUNMADAN yeni bir admin-users.php olusturur (izole,
 *  guvenli). Ozellikler: musteri listesi (email/ad/plan/dogrulama/tarih),
 *  arama, PLAN degistirme (free/basic/pro/elite), E-POSTA duzeltme (yanlis
 *  Stripe-email vakasi buradan cozulur), plan dagilimi ozeti.
 *  Guvenlik: session admin sifresi (config ADMIN_PW ya da varsayilan),
 *  prepared statements, htmlspecialchars (XSS), e-posta dogrulama.
 * KULLANIM: pull2.php?key=...&files=apply-admin-users.php
 *  Sonra: chat-help.com/chat/admin-users.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-admin-users BASLADI OK (PHP ".PHP_VERSION.")\n\n";

if (!is_file(__DIR__.'/db.php'))   exit("HATA: db.php yok — DB katmani bulunamadi.\n");
if (!is_file(__DIR__.'/auth.php')) exit("HATA: auth.php yok.\n");

$target = __DIR__.'/admin-users.php';

$page = <<<'ADMINPHP'
<?php
/* ChatHelp — Musteri & Paket Yonetimi (admin-users.php) — CH_ADMIN_USERS */
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
                /* 'free' ist kein enum-Wert (basic|pro|elite) → Plan-Zeile entfernen = Free */
                $pdo->prepare('DELETE FROM user_plans WHERE user_id=?')->execute([$uid]);
            } else {
                /* user_plans.user_id ist UNIQUE (Webhook nutzt ON DUPLICATE KEY) → upsert */
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
    } catch (Throwable $e) { $msg=$e->getMessage(); $msgType='err'; }
}

/* ── daten ── */
$rows=[]; $counts=['free'=>0,'basic'=>0,'pro'=>0,'elite'=>0]; $total=0; $q='';
if ($authed) {
    try {
        $pdo=db(); $q=trim($_GET['q'] ?? '');
        $sql='SELECT u.id,u.email,u.name,u.email_verified,u.created_at,
                (SELECT p.plan FROM user_plans p WHERE p.user_id=u.id AND p.status=\'active\' ORDER BY p.id DESC LIMIT 1) AS plan
              FROM users u';
        $args=[];
        if ($q!==''){ $sql.=' WHERE u.email LIKE ? OR u.name LIKE ?'; $args=["%$q%","%$q%"]; }
        $sql.=' ORDER BY u.id DESC LIMIT 300';
        $st=$pdo->prepare($sql); $st->execute($args); $rows=$st->fetchAll();
        $ct=$pdo->query('SELECT (SELECT p.plan FROM user_plans p WHERE p.user_id=u.id AND p.status=\'active\' ORDER BY p.id DESC LIMIT 1) AS plan FROM users u');
        foreach ($ct as $r){ $pl=$r['plan']?:'free'; if(isset($counts[$pl])) $counts[$pl]++; $total++; }
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
.tbl{background:#0d0d1c;border:1px solid rgba(255,255,255,.08);border-radius:14px;overflow:hidden}
.urow{border-bottom:1px solid rgba(255,255,255,.06);padding:14px 16px}
.urow:last-child{border-bottom:none}
.utop{display:flex;justify-content:space-between;align-items:baseline;gap:10px;flex-wrap:wrap}
.uid{font-size:11px;color:#505078}
.uname{font-weight:700;font-size:14px}
.uplan{font-size:10px;font-weight:800;letter-spacing:.6px;padding:3px 9px;border-radius:100px;text-transform:uppercase}
.uplan.free{color:#9090b8;border:1px solid rgba(255,255,255,.15)}
.uplan.basic{color:#d4a84a;border:1px solid rgba(212,168,74,.4)}
.uplan.pro{color:#6094ff;border:1px solid rgba(96,148,255,.4)}
.uplan.elite{color:#c090ff;border:1px solid rgba(192,144,255,.4)}
.uforms{display:flex;gap:14px;flex-wrap:wrap;margin-top:10px}
.uf{display:flex;gap:6px;align-items:center;background:#0a0a16;border:1px solid rgba(255,255,255,.06);border-radius:10px;padding:7px 9px}
.uf input,.uf select{background:#131325;border:1px solid rgba(255,255,255,.12);border-radius:8px;padding:6px 9px;color:#fff;font-size:12.5px;outline:none}
.uf input[type=email]{min-width:200px}
.uf label{font-size:10px;color:#707098;text-transform:uppercase;letter-spacing:.5px}
.uv{font-size:11px}.uv.y{color:#42df94}.uv.n{color:#e0a050}
.hint{font-size:11.5px;color:#707098;margin-top:14px;line-height:1.6}
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
    <?php else: foreach($rows as $u): $pl=$u['plan']?:'free'; ?>
      <div class="urow">
        <div class="utop">
          <div>
            <span class="uid">#<?=h($u['id'])?></span>
            <span class="uname"><?=h($u['name']?:'—')?></span>
            &nbsp;·&nbsp; <span style="color:#c0c0e0;font-size:13px"><?=h($u['email'])?></span>
          </div>
          <div style="display:flex;gap:8px;align-items:center">
            <span class="uplan <?=h($pl)?>"><?=h($pl)?></span>
            <span class="uv <?=$u['email_verified']?'y':'n'?>"><?=$u['email_verified']?'✓ verifiziert':'✗ unbestätigt'?></span>
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
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <div class="hint">
    • <b>Plan setzen</b> weist dem Kunden sofort das gewählte Paket zu (jüngster Eintrag zählt).<br>
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
echo "  ✓ admin-users.php olusturuldu (".strlen($page)." bayt)\n";
echo "  ✓ musteri listesi + plan degistirme + e-posta duzeltme + verifizierme\n";
echo "\n✓ CH_ADMIN_USERS hazir. Adres: https://chat-help.com/chat/admin-users.php\n";
echo "   Sifre: config.php'deki ADMIN_PW (yoksa varsayilan 'chathelp2026').\n";
echo "   NOT: admin.php (API-Keys) ile ayni sifre; guvenlik icin ADMIN_PW'yi config'de degistirin.\n";
