<?php
/** Yönetici girişi. Hız sınırı, isteğe bağlı IP kısıtı ve sabit hata metni. */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/admin.php';

if (vr_current_admin() !== null) vr_redirect('admin/index.php');

$err = '';
$unconfigured = vr_admin_unconfigured();
$ownAdmin = vr_admin_config() !== null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !$unconfigured) {
    vr_csrf_check();
    [$ok, $key] = vr_admin_login((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''));
    if ($ok) vr_redirect('admin/index.php');
    $err = match ($key) {
        'admin_ip_blocked' => 'Zugriff von dieser IP-Adresse ist nicht freigegeben.',
        'login_throttled'  => 'Zu viele Versuche. Bitte kurz warten.',
        default            => 'E-Mail oder Passwort ist falsch.',
    };
}
header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Verwaltung · <?= h((string)vr_config('brand')) ?></title>
<meta name="robots" content="noindex,nofollow">
<link rel="icon" href="<?= h(vr_url('assets/mark.php')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= h(vr_url('assets/css/maxsales.css', ['v' => vr_asset_v()])) ?>">
<link rel="stylesheet" href="<?= h(vr_url('assets/css/admin.css', ['v' => vr_asset_v()])) ?>">
<style>
.alogin{min-height:100vh;display:grid;place-items:center;padding:24px;background:#0b0a09;position:relative;overflow:hidden}
.alogin .acc__aura{position:absolute;inset:-40%;pointer-events:none;background:radial-gradient(30% 50% at 18% 50%,rgba(201,169,110,.22),transparent 70%),radial-gradient(28% 46% at 82% 30%,rgba(150,120,80,.14),transparent 70%)}
.alogin__box{position:relative;width:100%;max-width:400px;background:#fff;border-radius:12px;padding:34px 32px 28px;display:grid;gap:18px}
.alogin__mark{display:grid;place-items:center;width:52px;height:52px;border-radius:50%;background:#0b0a09;color:#fff;font-family:var(--serif);font-size:26px;box-shadow:0 0 0 1px rgba(201,169,110,.7),0 0 0 5px #fff,0 0 0 6px rgba(201,169,110,.3)}
.alogin h1{margin:0;font-family:var(--serif);font-weight:400;font-size:28px}
.alogin p{margin:0;color:var(--muted);font-size:13.5px;line-height:1.6}
</style>
</head>
<body class="adm">
<div class="alogin"><span class="acc__aura"></span>
  <div class="alogin__box">
    <span class="alogin__mark"><?= h(mb_substr((string)vr_config('brand'), 0, 1)) ?></span>
    <div><h1>Verwaltung</h1><p><?= h((string)vr_config('brand')) ?> · nur für den Betreiber</p></div>
    <?php if ($unconfigured): ?>
      <div class="notice" style="border-left-color:var(--ember)">
        <strong>Noch kein Administrator eingerichtet.</strong>
        Auf dem Server einmal <code>php tools/admin-user.php set &lt;E-Mail&gt; &lt;Passwort&gt;</code> ausführen
        oder <code>VR_ADMIN_USER</code> / <code>VR_ADMIN_PW_HASH</code> setzen.
      </div>
    <?php else: ?>
      <?php if ($err !== ''): ?><div class="flash flash--err"><?= h($err) ?></div><?php endif; ?>
      <form class="form" method="post" action="<?= h(vr_url('admin/login.php')) ?>">
        <?= vr_csrf_field() ?>
        <?php if ($ownAdmin): ?>
          <div class="field"><label for="email">E-Mail</label>
            <input id="email" name="email" type="email" autocomplete="username"></div>
        <?php endif; ?>
        <div class="field"><label for="password">Passwort</label>
          <input id="password" name="password" type="password" required autofocus autocomplete="current-password"></div>
        <button class="btn btn--block" type="submit"><span>Anmelden</span></button>
      </form>
      <p><?= $ownAdmin ? 'Eigener Zugang oder das Vestra-Adminpasswort.' : 'Es gilt dasselbe Passwort wie im Vestra-Adminbereich.' ?></p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
