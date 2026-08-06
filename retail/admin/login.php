<?php
/** Yönetici girişi. Hız sınırı, isteğe bağlı IP kısıtı ve sabit hata metni. */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/admin.php';

if (vr_current_admin() !== null) vr_redirect('admin/index.php');

$err = '';
$unconfigured = vr_admin_unconfigured();

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

vr_layout_start(['title' => 'Verwaltung', 'robots' => 'noindex,nofollow']);
?>
<section class="sec">
  <div class="wrap wrap--narrow">
    <h1 class="sechead__t">Verwaltung</h1>

    <?php if ($unconfigured): ?>
      <div class="notice" style="border-left-color:var(--ember);margin-top:22px">
        <strong>Noch kein Administrator eingerichtet.</strong>
        Das Panel bleibt geschlossen, bis auf dem Server einmal
        <code>php tools/admin-user.php set &lt;E-Mail&gt; &lt;Passwort&gt;</code> gelaufen ist.
        Alternativ die Umgebungsvariablen <code>VR_ADMIN_USER</code> und
        <code>VR_ADMIN_PW_HASH</code> setzen.
      </div>
    <?php else: ?>
      <p class="sechead__s" style="margin:12px 0 28px">Nur für den Betreiber.</p>

      <?php if ($err !== ''): ?>
        <div class="flash flash--err" style="margin-bottom:16px"><?= h($err) ?></div>
      <?php endif; ?>

      <form class="form" method="post" action="<?= h(vr_url('admin/login.php')) ?>">
        <?= vr_csrf_field() ?>
        <div class="field">
          <label for="email">E-Mail</label>
          <input id="email" name="email" type="email" required autocomplete="username">
        </div>
        <div class="field">
          <label for="password">Passwort</label>
          <input id="password" name="password" type="password" required autocomplete="current-password">
        </div>
        <div class="field">
          <button class="btn btn--brass" type="submit"><span>Anmelden</span></button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php vr_layout_end(); ?>
