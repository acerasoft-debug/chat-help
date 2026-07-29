<?php
require_once __DIR__.'/inc/i18n.php';
require_once __DIR__.'/inc/auth.php';

$token = trim($_GET['token'] ?? '');
$ok    = false;
$lang  = function_exists('vlang') ? vlang() : 'en';

if ($token !== '') {
    $list = auth_accounts();
    $verified = null;
    foreach ($list as &$a) {
        if (($a['email_token'] ?? '') === $token && ($a['status'] ?? '') === 'pending_email') {
            $a['email_verified'] = true;
            $a['email_token']    = '';
            $a['status']         = 'pending'; // awaiting KYB review
            $lang = $a['lang'] ?? $lang;
            $ok   = true;
            $verified = $a;
            break;
        }
    }
    unset($a);
    if ($ok) {
        auth_save_accounts($list);
        // With require_email_verify on, auth_register()'s "please upload your KYC
        // documents" ack email never fires (that's the else-branch of a check this
        // account already failed). Send it now, right after the address is confirmed
        // real — otherwise a verified user is never told what to do next.
        require_once __DIR__.'/inc/notify.php';
        [$asub, $abody, $aOpts] = vestra_ack_text($lang, $verified['name'] ?: $verified['company'], $verified['type'] ?? 'buyer');
        $sent = vestra_send_mail($verified['email'], $asub, $abody, '', '', null, '', $aOpts);
        auth_update($verified['id'], ['ack_sent_at' => date('c'), 'ack_sent_ok' => $sent]);
    }
}

/* Localized copy for the confirmation / error screen. */
$C = [
  'en' => ['Email verified','Thank you — your email address is confirmed. You can now sign in to VESTRA.','Sign in',
           'Verification link invalid','This link may have expired (valid 72 hours) or has already been used. Sign in and request a new link if you still need one.','Go to sign in'],
  'de' => ['E-Mail bestätigt','Vielen Dank — Ihre E-Mail-Adresse ist bestätigt. Sie können sich jetzt bei VESTRA anmelden.','Anmelden',
           'Bestätigungslink ungültig','Dieser Link ist möglicherweise abgelaufen (72 Stunden gültig) oder wurde bereits verwendet. Melden Sie sich an und fordern Sie bei Bedarf einen neuen Link an.','Zur Anmeldung'],
  'fr' => ['E-mail vérifié','Merci — votre adresse e-mail est confirmée. Vous pouvez maintenant vous connecter à VESTRA.','Se connecter',
           'Lien de vérification invalide','Ce lien a peut-être expiré (valable 72 heures) ou a déjà été utilisé. Connectez-vous et demandez un nouveau lien si nécessaire.','Aller à la connexion'],
  'it' => ['E-mail verificata','Grazie — il tuo indirizzo e-mail è confermato. Ora puoi accedere a VESTRA.','Accedi',
           'Link di verifica non valido','Questo link potrebbe essere scaduto (valido 72 ore) o già utilizzato. Accedi e richiedi un nuovo link se necessario.','Vai all\'accesso'],
  'es' => ['Correo verificado','Gracias — tu dirección de correo está confirmada. Ya puedes iniciar sesión en VESTRA.','Iniciar sesión',
           'Enlace de verificación no válido','Este enlace puede haber caducado (válido 72 horas) o ya se ha usado. Inicia sesión y solicita un nuevo enlace si aún lo necesitas.','Ir a iniciar sesión'],
];
$t = $C[$lang] ?? $C['en'];
[$okTitle,$okSub,$okBtn,$errTitle,$errSub,$errBtn] = $t;

$title = $ok ? $okTitle : $errTitle;
$sub   = $ok ? $okSub   : $errSub;
$btn   = $ok ? $okBtn   : $errBtn;
$accent = $ok ? '#c9a86a' : '#c9a86a';
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

// Success mark (gold check in a ring) vs neutral mark (envelope-ish) — inline SVG, no assets.
$mark = $ok
  ? '<svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#c9a86a" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>'
  : '<svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#c9a86a" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>';

http_response_code($ok ? 200 : 400);
header('Content-Type: text/html; charset=UTF-8');
?><!doctype html>
<html lang="<?= $h($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= $h($title) ?> — VESTRA</title>
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<style>
  :root{--bg:#0e0e11;--card:#15151a;--ink:#f4f1ea;--mut:#9a988f;--acc:<?= $accent ?>;--line:rgba(255,255,255,.08)}
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;
    background:radial-gradient(1200px 600px at 50% -10%,#191821 0%,var(--bg) 60%);color:var(--ink);
    font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,sans-serif;line-height:1.6;-webkit-font-smoothing:antialiased}
  .card{width:100%;max-width:440px;background:var(--card);border:1px solid var(--line);border-radius:18px;
    padding:44px 36px 40px;text-align:center;box-shadow:0 30px 80px rgba(0,0,0,.45)}
  .brand{font-weight:700;letter-spacing:.24em;font-size:13px;color:var(--acc);text-transform:uppercase;margin:0 0 26px}
  .ring{width:74px;height:74px;border-radius:50%;margin:0 auto 22px;display:flex;align-items:center;justify-content:center;
    background:rgba(201,168,106,.10);border:1px solid rgba(201,168,106,.35)}
  h1{font-family:'Playfair Display',Georgia,serif;font-weight:700;font-size:26px;line-height:1.2;margin:0 0 12px;color:var(--ink)}
  p{margin:0 auto 26px;max-width:340px;color:var(--mut);font-size:15px}
  .btn{display:inline-block;background:var(--acc);color:#141109;padding:13px 34px;border-radius:10px;
    text-decoration:none;font-weight:600;font-size:14px;letter-spacing:.01em;transition:transform .12s ease,filter .12s ease}
  .btn:hover{filter:brightness(1.06);transform:translateY(-1px)}
  .foot{margin:24px 0 0;font-size:11px;color:#6f6d66;letter-spacing:.03em}
  .rule{width:40px;height:2px;background:var(--acc);opacity:.7;margin:0 auto 24px;border-radius:2px}
</style>
</head>
<body>
  <div class="card">
    <div class="brand">VESTRA</div>
    <div class="ring"><?= $mark ?></div>
    <h1><?= $h($title) ?></h1>
    <div class="rule"></div>
    <p><?= $h($sub) ?></p>
    <a class="btn" href="/login<?= $ok ? '?verified=1' : '' ?>"><?= $h($btn) ?> &rarr;</a>
    <div class="foot">vestrasales.com · verified B2B wholesale</div>
  </div>
</body>
</html>
