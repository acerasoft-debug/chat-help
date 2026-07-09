<?php
/** VESTRA — request a password-reset link. Always answers the same way (no account enumeration). */
require_once __DIR__.'/inc/i18n.php';
require_once __DIR__.'/inc/auth.php';
require_once __DIR__.'/inc/notify.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $tkey  = 'reset|'.($_SERVER['REMOTE_ADDR'] ?? '');
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && !auth_throttled($tkey, 8, 900)) {
        auth_throttle_hit($tkey); // counts requests, not failures — caps reset-mail spam per IP
        if ($acc = auth_reset_begin($email)) {
            $host = (!empty($_SERVER['HTTPS']) ? 'https' : 'http').'://'.($_SERVER['HTTP_HOST'] ?? 'vestrasales.com');
            $link = $host.'/reset?token='.$acc['reset_token'];
            $name = $acc['name'] ?: ($acc['company'] ?: 'there');
            $texts = [
              'en' => ["VESTRA — reset your password",
                       "Hello {$name},\n\nSomeone (hopefully you) requested a password reset for your VESTRA account.\n\nSet a new password here (link valid for 1 hour):\n{$link}\n\nIf you didn't request this, you can ignore this email — your password stays unchanged.\n\n— VESTRA · vestrasales.com"],
              'de' => ["VESTRA — Passwort zurücksetzen",
                       "Hallo {$name},\n\njemand (hoffentlich Sie) hat das Zurücksetzen des Passworts für Ihr VESTRA-Konto angefordert.\n\nNeues Passwort hier festlegen (Link 1 Stunde gültig):\n{$link}\n\nFalls Sie das nicht angefordert haben, ignorieren Sie diese E-Mail — Ihr Passwort bleibt unverändert.\n\n— VESTRA · vestrasales.com"],
              'fr' => ["VESTRA — réinitialiser votre mot de passe",
                       "Bonjour {$name},\n\nQuelqu'un (vous, espérons-le) a demandé la réinitialisation du mot de passe de votre compte VESTRA.\n\nDéfinissez un nouveau mot de passe ici (lien valable 1 heure) :\n{$link}\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail — votre mot de passe reste inchangé.\n\n— VESTRA · vestrasales.com"],
              'it' => ["VESTRA — reimposta la password",
                       "Ciao {$name},\n\nqualcuno (speriamo tu) ha richiesto la reimpostazione della password del tuo account VESTRA.\n\nImposta una nuova password qui (link valido 1 ora):\n{$link}\n\nSe non sei stato tu, ignora questa e-mail — la tua password resta invariata.\n\n— VESTRA · vestrasales.com"],
              'es' => ["VESTRA — restablecer tu contraseña",
                       "Hola {$name},\n\nalguien (esperamos que tú) ha solicitado restablecer la contraseña de tu cuenta VESTRA.\n\nEstablece una nueva contraseña aquí (enlace válido 1 hora):\n{$link}\n\nSi no lo has solicitado, ignora este correo — tu contraseña no cambia.\n\n— VESTRA · vestrasales.com"],
            ];
            [$subj, $body] = $texts[vlang()] ?? $texts['en'];
            vestra_send_mail($acc['email'], $subj, $body);
        }
    }
    $sent = true; // same response whether or not the account exists
}

$PAGE = t('Reset password'); $NAV = ''; require __DIR__.'/inc/head.php';
?>
<div class="authwrap">
  <div class="authcard">
    <h2 class="authcard-title"><?= t('Reset password') ?></h2>
    <?php if ($sent): ?>
      <div class="banner" style="background:rgba(100,200,100,.08);border:1px solid rgba(100,200,100,.3);color:#6dbf7e;margin-bottom:16px">
        <?= t('If an account exists for that address, a reset link is on its way. Check your inbox (and spam folder).') ?>
      </div>
      <p class="authcard-foot"><a class="acc" href="/login"><?= t('Back to sign in') ?></a></p>
    <?php else: ?>
      <p class="authcard-sub"><?= t("Enter your account email — we'll send you a link to set a new password.") ?></p>
      <form method="post" action="/forgot" class="authform">
        <div class="authfield">
          <label for="email"><?= t('Email address') ?></label>
          <input id="email" type="email" name="email" required autocomplete="email" placeholder="name@company.com">
        </div>
        <button class="btn btn-p" type="submit" style="width:100%;justify-content:center;margin-top:4px"><?= t('Send reset link') ?></button>
      </form>
      <p class="authcard-foot"><a class="acc" href="/login"><?= t('Back to sign in') ?></a></p>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__.'/inc/foot.php';
