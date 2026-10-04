<?php
/**
 * ChatHelp — apply-konto-loeschen: GDPR Art.17 hesap+veri silme.
 *   1) auth.php'ye guvenli delete_account endpoint'i ekler (sifre dogrulamali).
 *   2) konto.html sayfasini web kokune + /konto/ altina yazar.
 * KULLANIM: pull-updates.php?files=apply-konto-loeschen.php  -> sonra SIL.
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-konto-loeschen BASLADI OK (PHP ".PHP_VERSION.")\n\n";

/* ===== 1) auth.php: delete_account endpoint ===== */
$auth = __DIR__ . '/auth.php';
if (!file_exists($auth)) {
    echo "[auth.php] BULUNAMADI — endpoint eklenemedi.\n";
} else {
    $src = file_get_contents($auth);
    if (strpos($src, 'CH_DELACCT') !== false) {
        echo "[auth.php] Zaten ekli (CH_DELACCT).\n";
    } else {
        $bak = $auth . '.bak-delacct-' . date('Ymd-His');
        file_put_contents($bak, $src);

        $disp = "'delete_account' => doDeleteAccount(\$body),";
        if (preg_match('/\'google\'\s*=>\s*doGoogleLogin\(\$body\),/', $src)) {
            $src = preg_replace('/(\'google\'\s*=>\s*doGoogleLogin\(\$body\),)/', "$1\n        " . $disp, $src, 1);
        } else {
            $src = preg_replace('/(\n\s*default\s*=>\s*respond\()/', "\n        " . $disp . "$1", $src, 1);
        }

        $fn = "\n\n/* CH_DELACCT — GDPR Art.17 hesap + veri silme */\n"
            . "function doDeleteAccount(array \$b): void {\n"
            . "    \$email = strtolower(trim(\$b['email'] ?? ''));\n"
            . "    \$pass  = \$b['password'] ?? '';\n"
            . "    if (!filter_var(\$email, FILTER_VALIDATE_EMAIL) || \$pass === '') respond(['error' => 'E-Mail und Passwort erforderlich'], 400);\n"
            . "    \$pdo = db();\n"
            . "    \$st = \$pdo->prepare('SELECT id, password_hash FROM users WHERE email = ?'); \$st->execute([\$email]);\n"
            . "    \$u = \$st->fetch(PDO::FETCH_ASSOC);\n"
            . "    if (!\$u || empty(\$u['password_hash']) || !password_verify(\$pass, \$u['password_hash'])) respond(['error' => 'Zugangsdaten ungültig'], 401);\n"
            . "    \$uid = (int)\$u['id'];\n"
            . "    try {\n"
            . "        \$pdo->beginTransaction();\n"
            . "        \$dbn = \$pdo->query('SELECT DATABASE()')->fetchColumn();\n"
            . "        \$q = \$pdo->prepare(\"SELECT DISTINCT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND COLUMN_NAME = 'user_id'\");\n"
            . "        \$q->execute([\$dbn]);\n"
            . "        foreach (\$q->fetchAll(PDO::FETCH_COLUMN) as \$t) {\n"
            . "            if (!preg_match('/^[A-Za-z0-9_]+\$/', \$t)) continue;\n"
            . "            \$pdo->prepare(\"DELETE FROM `\$t` WHERE user_id = ?\")->execute([\$uid]);\n"
            . "        }\n"
            . "        \$pdo->prepare('DELETE FROM user_plans WHERE user_id = ?')->execute([\$uid]);\n"
            . "        \$pdo->prepare('DELETE FROM users WHERE id = ?')->execute([\$uid]);\n"
            . "        \$pdo->commit();\n"
            . "    } catch (Throwable \$e) {\n"
            . "        if (\$pdo->inTransaction()) \$pdo->rollBack();\n"
            . "        respond(['error' => 'Löschung fehlgeschlagen'], 500);\n"
            . "    }\n"
            . "    respond(['ok' => true]);\n"
            . "}\n";

        $src = preg_replace('/\?>\s*$/', '', $src);
        $src = rtrim($src) . "\n" . $fn;

        $tmp = tempnam(sys_get_temp_dir(), 'auth') . '.php';
        file_put_contents($tmp, $src);
        $lo = []; $rc = 0;
        exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
        if ($rc !== 0) {
            echo "[auth.php] LINT HATASI — YAZILMADI:\n  " . implode("\n  ", $lo) . "\n";
        } else {
            file_put_contents($auth, $src);
            echo "[auth.php] delete_account endpoint eklendi. Yedek: " . basename($bak) . "\n";
        }
        @unlink($tmp);
    }
}

/* ===== 2) konto.html ===== */
$root = dirname(__DIR__);
$html = <<<'KONTOHTML'
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Konto & Daten löschen — ChatHelp</title>
<style>
  :root{
    --navy1:#0a1a3f; --navy2:#0d2452; --anth1:#14141b; --anth2:#1b1b23;
    --gold:#e7c66b; --gold2:#cf9f42; --t1:#ffffff; --t2:#aeb7d6; --t3:#7f89ad;
    --line:rgba(255,255,255,.09); --danger:#ff6a6a; --ok:#42df94;
  }
  *{margin:0;box-sizing:border-box}
  html,body{min-height:100%}
  body{
    font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Inter,Roboto,Arial,sans-serif;
    color:var(--t1);background:linear-gradient(165deg,var(--navy1) 0%,var(--navy2) 34%,var(--anth1) 100%);
    background-attachment:fixed;padding:32px 18px 60px;line-height:1.55;-webkit-font-smoothing:antialiased;
  }
  .wrap{max-width:560px;margin:0 auto}
  .brand{display:flex;align-items:center;gap:12px;justify-content:center;margin-bottom:26px}
  .brand svg{flex-shrink:0}
  .brand .nm{font-size:26px;font-weight:800;letter-spacing:-.5px}
  .brand .nm .x{color:var(--gold)}
  .card{background:linear-gradient(160deg,rgba(255,255,255,.045),rgba(255,255,255,.015));
    border:1px solid var(--line);border-radius:22px;padding:30px 26px;margin-bottom:18px;
    box-shadow:0 30px 70px rgba(0,0,0,.35)}
  h1{font-size:27px;font-weight:800;letter-spacing:-.5px;margin-bottom:10px}
  h1 .g{background:linear-gradient(120deg,var(--gold),var(--gold2));-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
  .lead{color:var(--t2);font-size:15.5px;margin-bottom:4px}
  .note{font-size:13.5px;color:var(--t3);margin-top:14px}
  h2{font-size:16px;font-weight:700;margin:22px 0 10px;color:#fff}
  ul{margin:0 0 4px 18px;color:var(--t2);font-size:14.5px}
  ul li{margin-bottom:5px}
  label{display:block;font-size:13px;font-weight:600;color:var(--t2);margin:16px 0 6px}
  input[type=email],input[type=password]{
    width:100%;padding:14px 15px;font-size:15px;color:#fff;background:rgba(0,0,0,.28);
    border:1px solid var(--line);border-radius:13px;outline:none;font-family:inherit}
  input:focus{border-color:var(--gold2);background:rgba(0,0,0,.36)}
  .chk{display:flex;gap:10px;align-items:flex-start;margin:18px 0 6px;font-size:14px;color:var(--t2)}
  .chk input{margin-top:3px;width:17px;height:17px;flex-shrink:0;accent-color:var(--gold2)}
  button{width:100%;margin-top:20px;padding:15px;font-size:15.5px;font-weight:800;font-family:inherit;
    border:none;border-radius:13px;cursor:pointer;color:#fff;
    background:linear-gradient(135deg,#c0392b,var(--danger));box-shadow:0 12px 30px rgba(255,80,80,.22);
    transition:transform .08s,opacity .2s}
  button:hover{transform:translateY(-1px)}
  button:disabled{opacity:.5;cursor:not-allowed;transform:none}
  .msg{margin-top:18px;padding:14px 15px;border-radius:12px;font-size:14.5px;display:none}
  .msg.show{display:block}
  .msg.err{background:rgba(255,106,106,.1);border:1px solid rgba(255,106,106,.3);color:#ffc2c2}
  .msg.ok{background:rgba(66,223,148,.1);border:1px solid rgba(66,223,148,.3);color:#b8f4d4}
  .msg.info{background:rgba(231,198,107,.08);border:1px solid rgba(231,198,107,.28);color:#f0dca0}
  .foot{text-align:center;font-size:13px;color:var(--t3);margin-top:22px}
  .foot a{color:var(--t2);text-decoration:none;border-bottom:1px solid var(--line)}
  .en{font-size:12.5px;color:var(--t3);font-style:italic}
  .badge{display:inline-flex;align-items:center;gap:7px;font-size:12px;font-weight:700;color:var(--gold2);
    background:rgba(231,198,107,.09);border:1px solid rgba(231,198,107,.3);padding:6px 13px;border-radius:100px;margin-bottom:16px}
</style>
</head>
<body>
<div class="wrap">

  <div class="brand">
    <svg width="40" height="40" viewBox="0 0 512 512"><path fill="#fff" d="M156 96 h200 a56 56 0 0 1 56 56 v150 a56 56 0 0 1 -56 56 h-92 l-70 62 v-62 h-38 a56 56 0 0 1 -56 -56 v-150 a56 56 0 0 1 56 -56 z"/><text x="256" y="196" text-anchor="middle" dominant-baseline="central" font-family="Inter,Arial" font-weight="800" font-size="150" letter-spacing="-8"><tspan fill="#0e2758">C</tspan><tspan fill="#cf9f42">H</tspan></text></svg>
    <div class="nm">Chat<span class="x">Help</span></div>
  </div>

  <div class="card">
    <div class="badge">🔒 DSGVO Art. 17</div>
    <h1>Konto & <span class="g">Daten löschen</span></h1>
    <p class="lead">Hier löschen Sie Ihr ChatHelp-Konto und alle damit verbundenen Daten – endgültig und unwiderruflich.</p>
    <p class="en">Delete your ChatHelp account and all associated data — permanent and irreversible.</p>

    <h2>Was wird gelöscht?</h2>
    <ul>
      <li>Ihr Konto (Name, E-Mail, Adresse, Telefonnummer)</li>
      <li>Alle erstellten Dokumente und Anträge</li>
      <li>Ihr Abonnement / Plan sowie hochgeladene Dateien und Fotos</li>
    </ul>
    <p class="note">Die Löschung erfolgt sofort. Es gibt keine Aufbewahrungsfrist und keine Wiederherstellung.
      <span class="en">Deletion is immediate; no retention period, no recovery.</span></p>
  </div>

  <div class="card">
    <h2 style="margin-top:0">Zur Bestätigung anmelden</h2>
    <p class="lead" style="font-size:14px">Geben Sie die Zugangsdaten des zu löschenden Kontos ein.
      <span class="en">Enter the credentials of the account to be deleted.</span></p>

    <form id="f" autocomplete="off">
      <label for="em">E-Mail-Adresse / Email</label>
      <input id="em" type="email" required placeholder="name@example.com" autocomplete="username">

      <label for="pw">Passwort / Password</label>
      <input id="pw" type="password" required placeholder="••••••••" autocomplete="current-password">

      <label class="chk">
        <input id="cf" type="checkbox" required>
        <span>Ich verstehe, dass mein Konto und alle Daten unwiderruflich gelöscht werden.
          <span class="en">I understand my account and all data will be permanently deleted.</span></span>
      </label>

      <button id="btn" type="submit">Konto endgültig löschen</button>
    </form>

    <div id="msg" class="msg"></div>
  </div>

  <div class="card">
    <h2 style="margin-top:0">Alternative</h2>
    <p class="lead" style="font-size:14px">Kein Zugriff mehr auf Ihr Konto? Schreiben Sie an
      <a href="mailto:support@chat-help.de?subject=Kontolöschung" style="color:var(--gold2)">support@chat-help.de</a>
      – wir löschen Ihr Konto innerhalb von 30 Tagen.
      <span class="en">No account access? Email us and we delete your account within 30 days.</span></p>
  </div>

  <div class="foot">
    <a href="/chat/">ChatHelp öffnen</a> &nbsp;·&nbsp;
    <a href="mailto:support@chat-help.de">support@chat-help.de</a><br>
    Acerasoft LLC
  </div>
</div>

<script>
(function(){
  var f=document.getElementById('f'), btn=document.getElementById('btn'), msg=document.getElementById('msg');
  function show(type,text){ msg.className='msg show '+type; msg.innerHTML=text; }
  f.addEventListener('submit', function(e){
    e.preventDefault();
    var email=document.getElementById('em').value.trim(), pass=document.getElementById('pw').value;
    if(!email||!pass){ show('err','Bitte E-Mail und Passwort eingeben.'); return; }
    if(!document.getElementById('cf').checked){ show('err','Bitte bestätigen Sie die Löschung.'); return; }
    if(!confirm('Konto und alle Daten endgültig löschen? Dies kann nicht rückgängig gemacht werden.')) return;
    btn.disabled=true; show('info','Wird gelöscht… / Deleting…');
    fetch('/chat/auth.php?action=delete_account',{
      method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include',
      body:JSON.stringify({email:email, password:pass})
    }).then(function(r){ return r.json().catch(function(){return {error:'Serverfehler ('+r.status+')'};}); })
      .then(function(d){
        if(d && d.ok){
          f.style.display='none';
          show('ok','✓ Ihr Konto und alle Daten wurden endgültig gelöscht.<br><span class="en">Your account and all data have been permanently deleted.</span>');
        } else {
          btn.disabled=false;
          show('err', (d && d.error) ? d.error : 'Löschung fehlgeschlagen. Bitte Zugangsdaten prüfen.');
        }
      }).catch(function(){ btn.disabled=false; show('err','Netzwerkfehler. Bitte später erneut versuchen.'); });
  });
})();
</script>
</body>
</html>
KONTOHTML;

$written = 0;
if (@file_put_contents($root . '/konto.html', $html) !== false) { echo "[sayfa] yazildi: " . $root . "/konto.html\n"; $written++; }
if (!is_dir($root . '/konto')) @mkdir($root . '/konto', 0755);
if (is_dir($root . '/konto') && @file_put_contents($root . '/konto/index.html', $html) !== false) { echo "[sayfa] yazildi: " . $root . "/konto/index.html\n"; $written++; }

echo "\n$written sayfa dosyasi yazildi.\n";
echo "\nURL'ler (gizli pencerede test et):\n";
echo "  https://chat-help.com/konto.html\n";
echo "  https://chat-help.com/konto/\n";
echo "\nBITTI. SIL: rm apply-konto-loeschen.php\n";
