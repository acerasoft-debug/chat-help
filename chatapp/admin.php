<?php
// ═══════════════════════════════════════════════════
// ChatHelp Admin Panel — API Key Manager
// URL: chat-help.de/chat/admin.php
// ═══════════════════════════════════════════════════

// ── ADMIN ŞIFRE — DEĞİŞTİR! ─────────────────────────
define('ADMIN_PASS', 'chathelp2026');

// ── CONFIG DOSYASI ────────────────────────────────────
define('CONFIG_FILE', __DIR__ . '/config.php');

// ── SESSION ──────────────────────────────────────────
session_start();

// ── LOGOUT ───────────────────────────────────────────
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// ── LOGIN ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASS) {
        $_SESSION['admin'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $loginError = 'Falsches Passwort';
    }
}

// ── SAVE CONFIG ───────────────────────────────────────
$saved = false;
$saveError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config']) && $_SESSION['admin']) {
    $claude  = trim($_POST['claude_key']  ?? '');
    $deepseek= trim($_POST['deepseek_key']?? '');
    $adminPw = trim($_POST['admin_pw']    ?? '');
    
    // Validate
    if ($claude && !str_starts_with($claude, 'sk-ant-')) {
        $saveError = 'Claude API Key muss mit "sk-ant-" beginnen';
    } elseif ($deepseek && !str_starts_with($deepseek, 'sk-')) {
        $saveError = 'DeepSeek API Key muss mit "sk-" beginnen';
    } else {
        $php = '<?php' . "\n";
        $php .= '// ChatHelp Config — automatisch generiert' . "\n";
        $php .= '// Erstellt: ' . date('d.m.Y H:i') . "\n\n";
        if ($claude)   $php .= "define('CLAUDE_KEY',   '" . addslashes($claude)   . "');\n";
        if ($deepseek) $php .= "define('DEEPSEEK_KEY', '" . addslashes($deepseek) . "');\n";
        if ($adminPw)  $php .= "\n// Admin Password\ndefine('ADMIN_PW', '" . addslashes($adminPw) . "');\n";
        
        if (file_put_contents(CONFIG_FILE, $php) !== false) {
            $saved = true;
        } else {
            $saveError = 'Fehler beim Speichern. Schreibrechte prüfen!';
        }
    }
}

// ── READ CURRENT CONFIG ───────────────────────────────
$currentClaude = '';
$currentDeepseek = '';
if (file_exists(CONFIG_FILE)) {
    $configContent = file_get_contents(CONFIG_FILE);
    if (preg_match("/define\('CLAUDE_KEY',\s*'([^']+)'\)/", $configContent, $m)) {
        $currentClaude = $m[1];
    }
    if (preg_match("/define\('DEEPSEEK_KEY',\s*'([^']+)'\)/", $configContent, $m)) {
        $currentDeepseek = $m[1];
    }
}

// ── TEST API ──────────────────────────────────────────
$testResult = '';
if (isset($_POST['test_claude']) && $_SESSION['admin'] && $currentClaude) {
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'claude-sonnet-4-6',
            'max_tokens' => 20,
            'messages' => [['role' => 'user', 'content' => 'Say OK']]
        ]),
        CURLOPT_HTTPHEADER => [
            'x-api-key: ' . $currentClaude,
            'anthropic-version: 2023-06-01',
            'content-type: application/json'
        ],
        CURLOPT_TIMEOUT => 15
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($res, true);
    if ($code === 200 && isset($data['content'])) {
        $testResult = 'claude_ok';
    } else {
        $testResult = 'claude_fail_' . $code;
    }
}

if (isset($_POST['test_deepseek']) && $_SESSION['admin'] && $currentDeepseek) {
    $ch = curl_init('https://api.deepseek.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'deepseek-chat',
            'max_tokens' => 10,
            'messages' => [['role' => 'user', 'content' => 'Say OK']]
        ]),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $currentDeepseek,
            'content-type: application/json'
        ],
        CURLOPT_TIMEOUT => 15
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($res, true);
    if ($code === 200 && isset($data['choices'])) {
        $testResult = 'deepseek_ok';
    } else {
        $testResult = 'deepseek_fail_' . $code;
    }
}

// ── MASK KEY ──────────────────────────────────────────
function maskKey($key) {
    if (!$key) return '';
    $len = strlen($key);
    if ($len <= 12) return str_repeat('*', $len);
    return substr($key, 0, 8) . str_repeat('*', $len - 12) . substr($key, -4);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ChatHelp Admin</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--bg:#07070e;--s1:#0d0d1c;--s2:#131325;--s3:#191930;--gold:#d4a84a;--gold2:#ecc060;--ga:rgba(212,168,74,.1);--gb:rgba(212,168,74,.25);--t1:#f5f5ff;--t2:#d0d0f0;--t3:#9090b8;--t4:#505078;--gr:#42df94;--rd:#e05050;--bdr:rgba(255,255,255,.08);--r:10px;--r2:14px}
html,body{min-height:100vh;background:var(--bg);color:var(--t1);font-family:'Inter',system-ui,sans-serif;-webkit-font-smoothing:antialiased}
.wrap{max-width:560px;margin:0 auto;padding:40px 20px}
.logo{display:flex;align-items:center;gap:10px;margin-bottom:32px}
.logo-mark{width:38px;height:38px;background:linear-gradient(135deg,var(--s2),var(--s3));border:1.5px solid var(--gb);border-radius:10px;display:flex;align-items:center;justify-content:center}
.logo-mark svg{width:20px;height:20px}
.logo-name{font-size:18px;font-weight:800;color:#fff;letter-spacing:-.4px}
.logo-name em{font-style:normal;color:var(--gold)}
.logo-badge{font-size:10px;font-weight:700;background:rgba(224,80,80,.1);border:1px solid rgba(224,80,80,.2);color:rgba(255,130,130,.9);padding:2px 9px;border-radius:100px;margin-left:6px;letter-spacing:.5px}

/* LOGIN */
.login-box{background:var(--s1);border:1px solid var(--bdr);border-radius:var(--r2);padding:32px;text-align:center}
.login-box h1{font-size:20px;font-weight:700;color:#fff;margin-bottom:6px}
.login-box p{font-size:13px;color:var(--t3);margin-bottom:24px}
.login-input{width:100%;background:var(--s2);border:1.5px solid var(--bdr);border-radius:var(--r);padding:12px 16px;font-size:15px;color:#fff;outline:none;font-family:inherit;transition:border .18s;text-align:center;letter-spacing:3px;-webkit-appearance:none}
.login-input:focus{border-color:var(--gb)}
.login-btn{width:100%;margin-top:12px;background:linear-gradient(135deg,var(--gold),var(--gold2));color:#07070e;border:none;border-radius:var(--r);padding:13px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit}
.login-btn:hover{opacity:.9}
.login-error{background:rgba(224,80,80,.08);border:1px solid rgba(224,80,80,.2);color:rgba(255,130,130,.9);border-radius:var(--r);padding:10px;font-size:13px;margin-top:10px}

/* CARDS */
.card{background:var(--s1);border:1px solid var(--bdr);border-radius:var(--r2);padding:24px;margin-bottom:16px}
.card.success{border-color:rgba(66,223,148,.3);background:rgba(66,223,148,.04)}
.card.error{border-color:rgba(224,80,80,.3);background:rgba(224,80,80,.04)}
.card-title{font-size:13px;font-weight:700;color:#fff;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.card-title svg{width:14px;height:14px;stroke:var(--gold);fill:none;stroke-width:2;flex-shrink:0}
label{display:block;font-size:10px;font-weight:700;color:var(--t4);text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px;margin-top:14px}
label:first-of-type{margin-top:0}
input[type=text],input[type=password]{width:100%;background:var(--s2);border:1.5px solid var(--bdr);border-radius:var(--r);padding:11px 14px;font-size:13.5px;color:#fff;outline:none;font-family:monospace;transition:border .18s;-webkit-appearance:none}
input[type=text]:focus,input[type=password]:focus{border-color:var(--gb)}
input[type=text]::placeholder,input[type=password]::placeholder{color:#404070;font-family:'Inter',sans-serif}
.hint{font-size:11px;color:var(--t4);margin-top:5px;line-height:1.5}
.hint a{color:var(--gold);text-decoration:none}
.current-key{display:flex;align-items:center;justify-content:space-between;background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r);padding:10px 14px;margin-bottom:10px}
.current-key-val{font-family:monospace;font-size:12px;color:var(--gr)}
.current-key-label{font-size:10px;color:var(--t4);margin-bottom:3px}
.status-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.status-dot.ok{background:var(--gr);box-shadow:0 0 8px rgba(66,223,148,.5)}
.status-dot.missing{background:var(--t4)}
.status-dot.error{background:var(--rd)}
.btn-row{display:flex;gap:8px;margin-top:16px;flex-wrap:wrap}
.btn-save{flex:1;background:linear-gradient(135deg,var(--gold),var(--gold2));color:#07070e;border:none;border-radius:var(--r);padding:12px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .15s}
.btn-save:hover{opacity:.9}
.btn-test{background:var(--s3);border:1px solid var(--bdr);color:var(--t2);border-radius:var(--r);padding:10px 16px;font-size:12.5px;font-weight:600;cursor:pointer;font-family:inherit;transition:all .15s}
.btn-test:hover{border-color:var(--gb);color:var(--gold)}
.btn-logout{background:var(--s2);border:1px solid var(--bdr);color:var(--t3);border-radius:var(--r);padding:8px 16px;font-size:12px;cursor:pointer;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:5px}
.btn-logout:hover{color:var(--rd);border-color:rgba(224,80,80,.3)}
.alert{border-radius:var(--r);padding:12px 16px;font-size:13px;margin-bottom:16px}
.alert-ok{background:rgba(66,223,148,.08);border:1px solid rgba(66,223,148,.2);color:rgba(66,223,148,.9)}
.alert-err{background:rgba(224,80,80,.08);border:1px solid rgba(224,80,80,.2);color:rgba(255,130,130,.9)}
.info-box{background:rgba(100,136,255,.06);border:1px solid rgba(100,136,255,.2);border-radius:var(--r);padding:14px;margin-top:16px}
.info-box p{font-size:12.5px;color:rgba(160,190,255,.9);line-height:1.65}
.info-box code{background:rgba(0,0,0,.3);padding:1px 6px;border-radius:4px;font-family:monospace;font-size:11px}
.step{display:flex;gap:10px;margin-bottom:10px}
.step-n{width:22px;height:22px;border-radius:50%;background:var(--ga);border:1px solid var(--gb);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:var(--gold);flex-shrink:0;margin-top:1px}
.step-t{font-size:12.5px;color:var(--t2);line-height:1.6}
.divider{height:1px;background:var(--bdr);margin:20px 0}
footer-bar{display:flex;align-items:center;justify-content:space-between;margin-top:20px}
.top-bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px}
</style>
</head>
<body>
<div class="wrap">
  <div class="logo">
    <div class="logo-mark">
      <svg viewBox="0 0 24 24" fill="none">
        <path d="M3 19L8 8L14 15L17 11L21 19" stroke="#d4a84a" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
        <circle cx="8" cy="8" r="1.8" fill="#d4a84a"/>
      </svg>
    </div>
    <span class="logo-name">Chat<em>Help</em></span>
    <span class="logo-badge">ADMIN</span>
  </div>

<?php if (!$_SESSION['admin']): ?>
  <!-- LOGIN -->
  <div class="login-box">
    <h1>🔐 Admin-Zugang</h1>
    <p>Geben Sie das Admin-Passwort ein</p>
    <form method="post">
      <input type="password" name="password" class="login-input" placeholder="••••••••••••" autofocus autocomplete="off">
      <?php if (isset($loginError)): ?>
        <div class="login-error">❌ <?= htmlspecialchars($loginError) ?></div>
      <?php endif; ?>
      <button type="submit" class="login-btn">Einloggen →</button>
    </form>
    <p style="font-size:11px;color:var(--t4);margin-top:16px">Standard-Passwort: <code style="color:var(--gold);font-family:monospace">chathelp2026</code><br>Nach dem ersten Login bitte ändern!</p>
  </div>

<?php else: ?>
  <!-- ADMIN PANEL -->
  <div class="top-bar">
    <div style="font-size:13px;color:var(--t3)">✅ Eingeloggt</div>
    <a href="?logout" class="btn-logout">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Logout
    </a>
  </div>

  <?php if ($saved): ?>
    <div class="alert alert-ok">✅ Konfiguration gespeichert! api.php verwendet ab sofort die neuen Keys.</div>
  <?php endif; ?>
  <?php if ($saveError): ?>
    <div class="alert alert-err">❌ <?= htmlspecialchars($saveError) ?></div>
  <?php endif; ?>
  <?php if (str_contains($testResult, 'ok')): ?>
    <div class="alert alert-ok">✅ API Test erfolgreich — Verbindung funktioniert!</div>
  <?php elseif ($testResult): ?>
    <div class="alert alert-err">❌ API Test fehlgeschlagen: <?= htmlspecialchars($testResult) ?></div>
  <?php endif; ?>

  <!-- STATUS -->
  <div class="card">
    <div class="card-title">
      <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      Aktueller Status
    </div>
    <div class="current-key">
      <div>
        <div class="current-key-label">Claude API Key</div>
        <div class="current-key-val"><?= $currentClaude ? maskKey($currentClaude) : '❌ Nicht gesetzt' ?></div>
      </div>
      <div class="status-dot <?= $currentClaude ? 'ok' : 'missing' ?>"></div>
    </div>
    <div class="current-key">
      <div>
        <div class="current-key-label">DeepSeek API Key</div>
        <div class="current-key-val"><?= $currentDeepseek ? maskKey($currentDeepseek) : '⚠️ Nicht gesetzt (optional)' ?></div>
      </div>
      <div class="status-dot <?= $currentDeepseek ? 'ok' : 'missing' ?>"></div>
    </div>
    <div class="current-key">
      <div>
        <div class="current-key-label">config.php</div>
        <div class="current-key-val"><?= file_exists(CONFIG_FILE) ? '✅ Vorhanden' : '❌ Noch nicht erstellt' ?></div>
      </div>
      <div class="status-dot <?= file_exists(CONFIG_FILE) ? 'ok' : 'missing' ?>"></div>
    </div>
    <div style="display:flex;gap:8px;margin-top:14px">
      <form method="post" style="flex:1">
        <input type="hidden" name="test_claude" value="1">
        <button type="submit" class="btn-test" style="width:100%" <?= !$currentClaude ? 'disabled' : '' ?>>
          🧪 Claude testen
        </button>
      </form>
      <form method="post" style="flex:1">
        <input type="hidden" name="test_deepseek" value="1">
        <button type="submit" class="btn-test" style="width:100%" <?= !$currentDeepseek ? 'disabled' : '' ?>>
          🧪 DeepSeek testen
        </button>
      </form>
    </div>
  </div>

  <!-- CONFIG FORM -->
  <div class="card">
    <div class="card-title">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93l-1.41 1.41M4.93 4.93l1.41 1.41M12 2v2M12 20v2M4.93 19.07l1.41-1.41M19.07 19.07l-1.41-1.41M2 12h2M20 12h2"/></svg>
      API Keys konfigurieren
    </div>
    <form method="post">
      <label>Claude API Key (Anthropic)</label>
      <input type="text" name="claude_key" placeholder="sk-ant-api03-..." autocomplete="off" value="<?= htmlspecialchars($currentClaude) ?>">
      <div class="hint">Holen Sie Ihren Key unter <a href="https://console.anthropic.com/" target="_blank">console.anthropic.com</a> → API Keys</div>

      <label style="margin-top:16px">DeepSeek API Key (optional — für Rechtsrecherche)</label>
      <input type="text" name="deepseek_key" placeholder="sk-..." autocomplete="off" value="<?= htmlspecialchars($currentDeepseek) ?>">
      <div class="hint">Holen Sie Ihren Key unter <a href="https://platform.deepseek.com/" target="_blank">platform.deepseek.com</a> → API Keys</div>

      <div class="divider"></div>

      <label>Neues Admin-Passwort (leer lassen = unverändertes Passwort)</label>
      <input type="password" name="admin_pw" placeholder="Neues Passwort eingeben..." autocomplete="new-password">
      <div class="hint">Passwort wird in config.php gespeichert</div>

      <input type="hidden" name="save_config" value="1">
      <div class="btn-row">
        <button type="submit" class="btn-save">💾 Konfiguration speichern</button>
      </div>
    </form>
  </div>

  <!-- SETUP GUIDE -->
  <div class="info-box">
    <p style="font-weight:700;color:#fff;margin-bottom:10px">📋 Einrichtungsanleitung</p>
    <div class="step"><div class="step-n">1</div><div class="step-t">Claude API Key bei <strong>console.anthropic.com</strong> erstellen → Guthaben aufladen (min. $5)</div></div>
    <div class="step"><div class="step-n">2</div><div class="step-t">Key hier eingeben und <strong>Konfiguration speichern</strong> klicken</div></div>
    <div class="step"><div class="step-n">3</div><div class="step-t"><strong>Claude testen</strong> → wenn ✅ erscheint, ist alles bereit</div></div>
    <div class="step"><div class="step-n">4</div><div class="step-t">App öffnen: <code>chat-help.de/chat/</code> → ersten Widerspruch erstellen</div></div>
    <div class="step"><div class="step-n">5</div><div class="step-t">Dieses Admin-Panel mit einem starken Passwort schützen!</div></div>
  </div>

  <!-- QUICK LINKS -->
  <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap">
    <a href="./" style="display:inline-flex;align-items:center;gap:5px;background:var(--s2);border:1px solid var(--bdr);color:var(--t2);padding:8px 14px;border-radius:var(--r);font-size:12.5px;font-weight:500;text-decoration:none">
      ← Zurück zur App
    </a>
    <?php if (file_exists(CONFIG_FILE)): ?>
    <span style="display:inline-flex;align-items:center;gap:5px;background:rgba(66,223,148,.08);border:1px solid rgba(66,223,148,.2);color:rgba(66,223,148,.9);padding:8px 14px;border-radius:var(--r);font-size:12.5px;font-weight:500">
      ✅ config.php aktiv
    </span>
    <?php endif; ?>
  </div>

<?php endif; ?>

</div>
</body>
</html>
