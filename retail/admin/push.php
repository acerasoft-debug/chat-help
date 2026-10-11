<?php
/** Push-Benachrichtigungen: Abonnenten sehen, Nachricht senden, Verlauf. */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/../inc/push.php';
require_once __DIR__ . '/_nav.php';

vr_admin_require();

$msg = ''; $err = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    $title = trim((string)($_POST['title'] ?? ''));
    $body  = trim((string)($_POST['body'] ?? ''));
    $url   = trim((string)($_POST['url'] ?? '/'));
    $lang  = (string)($_POST['lang'] ?? '');
    // Nur Ziele auf der eigenen Seite: eine Benachrichtigung soll nie nach
    // außen führen (Phishing-Schutz, auch gegen Tippfehler).
    if ($url === '' || !preg_match('#^/[^/\\\\]#', $url) && $url !== '/') $url = '/';
    if (!in_array($lang, array_merge([''], (array)vr_config('languages', [])), true)) $lang = '';
    if ($title === '' || $body === '') {
        $err = 'Titel und Text sind Pflicht.';
    } elseif (($_POST['confirm'] ?? '') !== '1') {
        $err = 'Bitte das Senden bestätigen (Häkchen).';
    } else {
        $r = vr_push_broadcast($title, $body, $url, $lang);
        $msg = sprintf('Gesendet an %d von %d Geräten · %d abgemeldete entfernt · %d Fehler.',
                       $r['sent'], $r['total'], $r['removed'], $r['failed']);
    }
}

$subs = vr_push_subs();
$byLang = [];
foreach ($subs as $s) $byLang[$s['lang'] ?? '?'] = ($byLang[$s['lang'] ?? '?'] ?? 0) + 1;
arsort($byLang);
$log = (array)vr_store_read('push-log.json', []);

vr_admin_head('Benachrichtigungen');
?>
<?php if ($msg !== ''): ?><div class="flash flash--ok" style="margin-bottom:18px"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err !== ''): ?><div class="flash flash--err" style="margin-bottom:18px"><?= h($err) ?></div><?php endif; ?>

<?php
$stats = ['Geräte gesamt' => count($subs)];
foreach (array_slice($byLang, 0, 4, true) as $l => $n) $stats['Sprache ' . strtoupper((string)$l)] = $n;
vr_admin_stats($stats);
?>

<p style="font-size:12.5px;color:var(--muted);margin:18px 0;max-width:70ch">
  Empfänger sind Geräte, auf denen jemand in der App oder auf der Website
  „Benachrichtigungen aktivieren“ gewählt hat (iPhone ab iOS 16.4 mit der App auf dem
  Home-Bildschirm, Android, Windows, Mac). Kurz und selten senden — jede Nachricht,
  die nicht interessiert, kostet Abonnenten. Ziel nur Seiten dieser Website.
</p>

<h2 class="sechead__t" style="font-size:20px;margin:30px 0 12px">Neue Nachricht</h2>
<form class="form" method="post">
  <?= vr_csrf_field() ?>
  <div class="field"><label for="title">Titel (max. 80 Zeichen)</label>
    <input id="title" name="title" type="text" maxlength="80" required placeholder="Vault: neue Preisstufe"></div>
  <div class="field"><label for="body">Text (max. 240 Zeichen)</label>
    <input id="body" name="body" type="text" maxlength="240" required placeholder="13 Lose sind gerade günstiger geworden — nur solange sie da sind."></div>
  <div class="field"><label for="url">Ziel beim Antippen</label>
    <select id="url" name="url" style="padding:9px 12px;border:1px solid var(--bone-3);background:var(--bone);font:inherit;font-size:13.5px">
      <option value="/outlet.php">Premium Outlet (Vault)</option>
      <option value="/shop.php?sort=new">Neuheiten</option>
      <option value="/">Startseite</option>
      <option value="/brands.php">Marken</option>
      <option value="/journal.php">Journal</option>
    </select></div>
  <div class="field"><label for="lang">Empfänger</label>
    <select id="lang" name="lang" style="padding:9px 12px;border:1px solid var(--bone-3);background:var(--bone);font:inherit;font-size:13.5px">
      <option value="">Alle Sprachen (<?= count($subs) ?>)</option>
      <?php foreach ((array)vr_config('languages', []) as $l): ?>
        <option value="<?= h($l) ?>">Nur <?= h(strtoupper($l)) ?> (<?= (int)($byLang[$l] ?? 0) ?>)</option>
      <?php endforeach; ?>
    </select></div>
  <label class="consent"><input type="checkbox" name="confirm" value="1">
    <span>Ich möchte diese Nachricht jetzt an die gewählten Geräte senden.</span></label>
  <div class="field"><button class="btn btn--brass" type="submit"><span>Senden</span></button></div>
</form>

<h2 class="sechead__t" style="font-size:20px;margin:34px 0 12px">Verlauf</h2>
<?php if (!$log): ?>
  <p style="color:var(--muted);font-size:14px">Noch nichts gesendet.</p>
<?php else: ?>
  <div class="tablewrap" tabindex="0">
    <table class="table">
      <thead><tr><th>Zeit</th><th>Titel</th><th>Ziel</th><th>Sprache</th><th>Zugestellt</th><th>Entfernt</th><th>Fehler</th></tr></thead>
      <tbody>
      <?php foreach ($log as $e): ?>
        <tr>
          <td><?= h(vr_date((int)strtotime((string)$e['at']))) ?></td>
          <td><?= h((string)$e['title']) ?></td>
          <td style="font-size:12.5px"><?= h((string)$e['url']) ?></td>
          <td><?= h(($e['lang'] ?? '') === '' ? 'alle' : strtoupper((string)$e['lang'])) ?></td>
          <td><?= (int)$e['sent'] ?> / <?= (int)$e['total'] ?></td>
          <td><?= (int)$e['removed'] ?></td>
          <td><?= (int)$e['failed'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php vr_admin_foot(); ?>
