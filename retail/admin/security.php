<?php
/**
 * Sicherheit: Anmeldeversuche (aus admin-audit.log), aktive Sperren, IP-
 * Beschränkung des Panels, eigenes Admin-Passwort ändern, Sitzungsregeln.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/_nav.php';

$who = vr_admin_require();
$cfg = vr_admin_config();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'password') {
        $email = trim((string)($_POST['email'] ?? ''));
        $pw1 = (string)($_POST['pw1'] ?? ''); $pw2 = (string)($_POST['pw2'] ?? '');
        $cur = (string)($_POST['current'] ?? '');
        $err = '';
        if ($pw1 !== $pw2) $err = 'Die beiden neuen Passwörter stimmen nicht überein.';
        elseif ($cfg !== null && !password_verify($cur, $cfg['pw_hash']) && !(vr_vestra_admin_pass() !== '' && hash_equals(vr_vestra_admin_pass(), $cur))) $err = 'Aktuelles Passwort falsch.';
        if ($err === '') { [$ok, $msg] = vr_admin_set($email, $pw1); if (!$ok) $err = $msg; }
        if ($err !== '') { vr_flash($err, 'err'); vr_redirect('admin/security.php'); }
        vr_admin_audit('admin_password_set', ['email' => $email]);
        vr_session_start(); $_SESSION['vr_admin'] = strtolower($email);
        vr_flash('Zugang gespeichert. Ab jetzt gilt: ' . strtolower($email) . ' mit dem neuen Passwort.', 'ok');
        vr_redirect('admin/security.php');
    }
    if ($act === 'block') {
        $ip = trim((string)($_POST['ip'] ?? ''));
        if (filter_var($ip, FILTER_VALIDATE_IP) && $ip !== vr_client_ip()) { vr_ip_block($ip, (string)($_POST['note'] ?? 'Sicherheit'), $who); vr_admin_audit('ip_block', ['ip' => $ip]); vr_flash($ip . ' gesperrt.', 'ok'); }
        else vr_flash('Ungültige Adresse oder die eigene.', 'err');
        vr_redirect('admin/security.php');
    }
}

$audit = vr_log_tail('admin-audit.log', 5000);
$logins = array_values(array_filter($audit, fn($r) => in_array((string)($r['action'] ?? ''), ['login_ok', 'login_failed'], true)));
$failed = array_filter($logins, fn($r) => $r['action'] === 'login_failed');
$fail24 = count(array_filter($failed, fn($r) => (int)$r['at'] > time() - 86400));
$byIp = [];
foreach ($failed as $r) { $byIp[(string)$r['ip']] = ($byIp[(string)$r['ip']] ?? 0) + 1; }
arsort($byIp);
$blocked = vr_ip_blocklist();
$ips = trim((string)getenv('VR_ADMIN_IPS'));
$changes = array_values(array_filter($audit, fn($r) => !in_array((string)($r['action'] ?? ''), ['login_ok', 'login_failed'], true)));

vr_admin_head('Sicherheit', ['sub' => 'Anmeldungen, Sperren, Panel-Zugang. Nichts hier zeigt Schlüssel oder Passwörter.']);
?>
<?php vr_admin_stats([
  'Fehlversuche 24 h' => [$fail24, count($failed) . ' gesamt im Protokoll'],
  'Erfolgreiche Logins' => [count($logins) - count($failed), $logins ? 'zuletzt ' . vr_admin_ago((int)$logins[0]['at']) : ''],
  'Gesperrte IPs' => [count($blocked), 'Liste unter Besucher › Gesperrt'],
  'Panel-IP-Filter' => [$ips !== '' ? 'aktiv' : 'aus', $ips !== '' ? count(explode(',', $ips)) . ' Adresse(n)' : 'VR_ADMIN_IPS nicht gesetzt'],
]); ?>

<div class="agrid agrid--2">
  <?php vr_admin_card_open('Anmeldeversuche', 'letzte 60', 'acard--pad0'); ?>
    <div class="tablewrap"><table class="table">
      <thead><tr><th>Zeit</th><th>Ergebnis</th><th>Konto</th><th>IP</th><th></th></tr></thead>
      <tbody>
      <?php foreach (array_slice($logins, 0, 60) as $r): $ip = (string)($r['ip'] ?? ''); ?>
        <tr><td class="asmall"><?= h(date('d.m.Y H:i:s', (int)$r['at'])) ?></td>
          <td><?= $r['action'] === 'login_ok' ? vr_admin_pill('angemeldet', 'ok') : vr_admin_pill('fehlgeschlagen', 'bad') ?></td>
          <td class="asmall"><?= h((string)($r['action'] === 'login_ok' ? ($r['who'] ?? '') : ($r['ctx']['email'] ?? ''))) ?></td>
          <td><a class="amono asmall" href="<?= h(vr_url('admin/visitors.php', ['ip' => $ip])) ?>"><?= h($ip) ?></a><?= vr_ip_blocked($ip) ? ' ' . vr_admin_pill('gesperrt', 'bad') : '' ?></td>
          <td><?= $r['action'] === 'login_failed' && !vr_ip_blocked($ip) && $ip !== vr_client_ip() ? vr_admin_button('block', ['ip' => $ip, 'note' => 'Login-Fehlversuche'], 'Sperren', 'btn btn--ghost btn--sm btn--danger') : '' ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$logins): ?><tr><td colspan="5" class="asmall">Noch keine Anmeldungen protokolliert.</td></tr><?php endif; ?>
      </tbody></table></div>
  <?php vr_admin_card_close(); ?>

  <div class="agrid">
    <?php vr_admin_card_open('Fehlversuche je IP'); ?>
      <?php vr_admin_bars(array_slice($byIp, 0, 10, true), 10, fn($k) => vr_url('admin/visitors.php', ['ip' => $k])); ?>
      <form method="post" class="afilter" style="margin-top:14px"><?= vr_csrf_field() ?><input type="hidden" name="action" value="block">
        <input type="text" name="ip" placeholder="IP sperren …" required><input type="text" name="note" placeholder="Grund" value="Sicherheit"><button class="btn btn--ghost btn--sm btn--danger" type="submit"><span>Sperren</span></button></form>
    <?php vr_admin_card_close(); ?>

    <?php vr_admin_card_open('Panel-Zugang'); ?>
      <form method="post" class="aform"><?= vr_csrf_field() ?><input type="hidden" name="action" value="password">
        <?= vr_admin_field('Admin-E-Mail', '<input type="email" name="email" value="' . h($cfg['email'] ?? '') . '" required autocomplete="username">') ?>
        <?php if ($cfg !== null): ?><?= vr_admin_field('Aktuelles Passwort', '<input type="password" name="current" autocomplete="current-password" required>', 'Eigenes Admin-Passwort oder das Vestra-Passwort') ?><?php endif; ?>
        <div class="aform2">
          <?= vr_admin_field('Neues Passwort', '<input type="password" name="pw1" minlength="12" required autocomplete="new-password">', 'mindestens 12 Zeichen') ?>
          <?= vr_admin_field('Wiederholen', '<input type="password" name="pw2" minlength="12" required autocomplete="new-password">') ?>
        </div>
        <p><button class="btn btn--sm" type="submit"><span><?= $cfg === null ? 'Eigenen Zugang anlegen' : 'Passwort ändern' ?></span></button></p>
      </form>
      <dl class="adl" style="margin-top:14px">
        <dt>Angemeldet als</dt><dd><?= h($who) ?></dd>
        <dt>Eigener Admin</dt><dd><?= $cfg !== null ? h($cfg['email']) . ' <span class="asmall">(data/admin.json)</span>' : '<span class="asmall">nicht eingerichtet — Login läuft über das Vestra-Passwort</span>' ?></dd>
        <dt>Vestra-Passwort</dt><dd><?= vr_vestra_admin_pass() !== '' || vr_vestra_admin_hash() !== '' ? 'akzeptiert' : 'nicht gefunden' ?></dd>
        <dt>Sitzung</dt><dd>8 Stunden, dann automatische Abmeldung · Cookie nur HTTPS, SameSite</dd>
        <dt>Drosselung</dt><dd>8 Versuche je 10 Minuten und IP</dd>
        <dt>IP-Filter</dt><dd><?= $ips !== '' ? h($ips) : '<span class="asmall">Umgebungsvariable VR_ADMIN_IPS (kommagetrennt) setzen, um das Panel auf feste Adressen zu beschränken.</span>' ?></dd>
      </dl>
    <?php vr_admin_card_close(); ?>
  </div>
</div>

<?php vr_admin_card_open('Letzte Änderungen im Panel', '<a href="' . h(vr_url('admin/logs.php', ['f' => 'audit'])) . '">vollständiges Protokoll →</a>', 'acard--pad0'); ?>
  <div class="tablewrap"><table class="table">
    <thead><tr><th>Zeit</th><th>Wer</th><th>Aktion</th><th>Details</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach (array_slice($changes, 0, 40) as $r): ?>
      <tr><td class="asmall"><?= h(date('d.m.Y H:i', (int)$r['at'])) ?></td><td class="asmall"><?= h((string)($r['who'] ?? '')) ?></td><td><b><?= h((string)($r['action'] ?? '')) ?></b></td>
        <td class="amono asmall"><?= h(mb_strimwidth(json_encode($r['ctx'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 0, 120, '…')) ?></td>
        <td><a class="amono asmall" href="<?= h(vr_url('admin/visitors.php', ['ip' => (string)($r['ip'] ?? '')])) ?>"><?= h((string)($r['ip'] ?? '')) ?></a></td></tr>
    <?php endforeach; ?>
    <?php if (!$changes): ?><tr><td colspan="5" class="asmall">Noch keine Änderungen.</td></tr><?php endif; ?>
    </tbody></table></div>
<?php vr_admin_card_close(); ?>
<?php vr_admin_foot(); ?>
