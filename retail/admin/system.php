<?php
/**
 * Systemzustand. Zeigt, was ohne SSH nicht sichtbar ist: ob Stripe und der
 * Mailversand konfiguriert sind, ob die Datenverzeichnisse beschreibbar sind
 * und wo die Dateien liegen.
 *
 * GEHEIMNISSE WERDEN NIE ANGEZEIGT — nur „gesetzt" oder „fehlt". Ein Panel,
 * das Schlüssel im Klartext zeigt, macht jede übernommene Sitzung zum
 * Schlüsseldiebstahl.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/../inc/uploads.php';
require_once __DIR__ . '/_nav.php';

vr_admin_require();

$co = (array)vr_config('company');
$missingCo = [];
foreach (['legal_name' => 'Firmenname', 'street' => 'Straße', 'city' => 'Ort',
          'email' => 'E-Mail', 'phone' => 'Telefon'] as $k => $label) {
    if (trim((string)($co[$k] ?? '')) === '') $missingCo[] = $label;
}

$dataDir = vr_data_dir();
$checks = [
    'Stripe Secret Key'   => vr_stripe_ready(),
    'Stripe Webhook-Key'  => trim((string)(vr_stripe_settings()['webhook_secret'] ?? '')) !== '',
    'Stripe Connect'      => (bool)vr_config('connect_enabled', true),
    'Mailversand (API)'   => (bool)(vr_mail_settings()['enabled'] ?? false),
    'Datenverzeichnis beschreibbar' => is_writable($dataDir),
    'Uploads beschreibbar'          => is_writable(vr_doc_root() . '/uploads'),
    'Impressum vollständig'         => vr_company_complete(),
    'Echter Katalog (kein Demo)'    => !vr_catalog_is_demo(),
];

vr_admin_head('System');
?>
<div class="tablewrap" tabindex="0">
  <table class="table">
    <thead><tr><th>Prüfung</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($checks as $label => $ok): ?>
      <tr>
        <td><?= h($label) ?></td>
        <td style="color:<?= $ok ? 'var(--muted)' : 'var(--ember-text)' ?>">
          <?= $ok ? 'in Ordnung' : 'fehlt' ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($missingCo): ?>
  <div class="notice" style="border-left-color:var(--ember);margin-top:20px">
    <strong>Impressum unvollständig.</strong>
    Es fehlen: <?= h(implode(', ', $missingCo)) ?>.
    Diese Felder stehen in <code>inc/config.php</code> unter <code>company</code> und sind
    nach §5 DDG Pflicht. Solange sie leer sind, zeigt das Impressum sichtbare Platzhalter —
    absichtlich, damit keine erfundenen Angaben online stehen.
  </div>
<?php endif; ?>

<h2 class="sechead__t" style="font-size:20px;margin:34px 0 12px">Ablage</h2>
<div class="dl">
  <dt>Datenverzeichnis</dt><dd><code><?= h($dataDir) ?></code></dd>
  <dt>Uploads</dt><dd><code><?= h(vr_doc_root() . '/uploads') ?></code></dd>
  <dt>Basis-URL</dt><dd><code><?= h(vr_base_url() === '' ? '/' : vr_base_url()) ?></code></dd>
  <dt>Sprachen</dt><dd><?= h(strtoupper(implode(' · ', (array)vr_config('languages', [])))) ?></dd>
  <dt>Aufschlag</dt><dd>×<?= h((string)vr_config('retail_multiplier')) ?></dd>
  <dt>Land laut Vorschaltebene</dt>
  <dd><?php require_once __DIR__ . '/../inc/geo.php';
      $cc = vr_geo_country(); echo $cc === '' ? 'nicht gemeldet' : h($cc); ?></dd>
</div>

<h2 class="sechead__t" style="font-size:20px;margin:34px 0 12px">Werkzeuge</h2>
<p style="font-size:13.5px;line-height:1.9">
  Diese Aufgaben laufen bewusst nur über die Kommandozeile:<br>
  <code>php tools/selftest.php</code> — vollständige Prüfung<br>
  <code>php tools/test-flow.php</code> — Ablauftests<br>
  <code>php tools/test-account.php</code> — Kontotests<br>
  <code>php tools/import-live.php --from=~/public_html --images</code> — Katalogimport<br>
  <code>php tools/vault-alerts.php</code> — Preisalarme versenden<br>
  <code>php tools/admin-user.php set &lt;E-Mail&gt; &lt;Passwort&gt;</code> — Zugang ändern
</p>
<?php vr_admin_foot(); ?>
