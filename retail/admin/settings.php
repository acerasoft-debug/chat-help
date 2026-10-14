<?php
/**
 * Einstellungen: der ungefährliche Teil von data/retail-settings.json.
 * Firma/Impressum, Kontakt-Mails, Gutschein, Rückgabe, Hausrabatte, Versand.
 *
 * Schlüssel (Stripe, Brevo) stehen NICHT hier — die liegen in eigenen
 * 0600-Dateien und werden nur unter Systemstatus als „gesetzt/fehlt" gezeigt.
 * Die Datei wird beim Deployment nicht angefasst; was hier gespeichert wird,
 * überlagert inc/config.php dauerhaft.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/_nav.php';

vr_admin_require();

$coKeys = ['legal_name' => 'Firma (Rechtsform im Namen)', 'form' => 'Rechtsform', 'represented_by' => 'Vertreten durch', 'street' => 'Straße', 'zip' => 'PLZ', 'city' => 'Ort', 'state' => 'Bundesstaat / Region', 'country' => 'Land',
           'reg_authority' => 'Registerbehörde', 'reg_number' => 'Registernummer', 'vat_id' => 'USt-IdNr.', 'tax_id' => 'Steuer-ID', 'email' => 'E-Mail (Kontakt, Nachrichten)', 'phone' => 'Telefon', 'email_legal' => 'E-Mail Recht', 'email_privacy' => 'E-Mail Datenschutz', 'eu_rep' => 'EU-Vertreter (Art. 27 DSGVO)'];
$shipZones = ['de' => 'Deutschland', 'eu' => 'EU', 'ch' => 'Schweiz / UK / Norwegen', 'world' => 'Übrige Welt'];
$tiers = ['luxury' => 'Luxus', 'designer' => 'Designer', 'casual' => 'Casual'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    $sec = (string)($_POST['section'] ?? '');
    $patch = [];
    if ($sec === 'company') {
        $co = [];
        foreach ($coKeys as $k => $_) $co[$k] = mb_substr(trim((string)($_POST['co'][$k] ?? '')), 0, 200);
        if ($co['email'] !== '' && !filter_var($co['email'], FILTER_VALIDATE_EMAIL)) { vr_flash('Kontakt-E-Mail ungültig.', 'err'); vr_redirect('admin/settings.php'); }
        $patch['company'] = $co;
    } elseif ($sec === 'shop') {
        $patch['brand'] = mb_substr(trim((string)($_POST['brand'] ?? '')), 0, 40) ?: vr_config('brand');
        $patch['mail_bcc_ops'] = mb_substr(trim((string)($_POST['mail_bcc_ops'] ?? '')), 0, 160);
        $patch['welcome_discount_bps'] = max(0, min(5000, (int)round((float)str_replace(',', '.', (string)($_POST['welcome'] ?? '0')) * 100)));
        $patch['welcome_valid_days'] = max(1, min(365, (int)($_POST['welcome_days'] ?? 90)));
        $patch['return_days'] = max(14, min(365, (int)($_POST['return_days'] ?? 30)));
        $t = [];
        foreach ($tiers as $k => $_) $t[$k] = max(0, min(8000, (int)round((float)str_replace(',', '.', (string)($_POST['tier'][$k] ?? '0')) * 100)));
        $patch['official_discount_tiers'] = $t;
        $patch['official_discount_bps'] = max(0, min(8000, (int)round((float)str_replace(',', '.', (string)($_POST['tier_default'] ?? '20')) * 100)));
    } elseif ($sec === 'shipping') {
        $sh = [];
        foreach ($shipZones as $z => $_) {
            $sh[$z] = ['cents' => max(0, (int)round((float)str_replace(',', '.', (string)($_POST['ship'][$z]['cents'] ?? '0')) * 100)),
                       'free_over' => max(0, (int)round((float)str_replace(',', '.', (string)($_POST['ship'][$z]['free'] ?? '0')) * 100)),
                       'days' => mb_substr(trim((string)($_POST['ship'][$z]['days'] ?? '')), 0, 12)];
        }
        $patch['shipping'] = $sh;
    }
    if ($patch) {
        vr_store_mutate('retail-settings.json', function ($all) use ($patch) {
            if (!is_array($all)) $all = [];
            foreach ($patch as $k => $v) $all[$k] = $v;
            return $all;
        });
        vr_admin_audit('settings_' . $sec, ['keys' => array_keys($patch)]);
        vr_flash('Gespeichert. Gilt sofort für alle Seiten.', 'ok');
    }
    vr_redirect('admin/settings.php');
}

$co = (array)vr_config('company');
$ship = (array)vr_config('shipping');
$tierVals = (array)vr_config('official_discount_tiers', []);
$file = vr_data_dir() . '/retail-settings.json';
$over = is_readable($file) ? (json_decode((string)file_get_contents($file), true) ?: []) : [];
$pct = fn(int $bps) => rtrim(rtrim(number_format($bps / 100, 2, ',', ''), '0'), ',');
$eur = fn(int $c) => number_format($c / 100, 2, ',', '');

vr_admin_head('Einstellungen', ['sub' => 'Überlagert inc/config.php dauerhaft (data/retail-settings.json, ' . (is_array($over) ? count($over) : 0) . ' Schlüssel gesetzt). Zahlungs- und Mail-Schlüssel: siehe Systemstatus.']);
?>
<?php vr_admin_card_open('Shop & Verkauf'); ?>
  <form method="post" class="aform"><?= vr_csrf_field() ?><input type="hidden" name="section" value="shop">
    <div class="asetgrid">
      <?= vr_admin_field('Markenname', '<input type="text" name="brand" value="' . h((string)vr_config('brand')) . '" maxlength="40">') ?>
      <?= vr_admin_field('Bestellkopie an (BCC)', '<input type="email" name="mail_bcc_ops" value="' . h((string)vr_config('mail_bcc_ops', '')) . '">', 'leer = keine Kopie') ?>
      <?= vr_admin_field('Willkommensrabatt (%)', '<input type="text" inputmode="decimal" name="welcome" value="' . h($pct((int)vr_config('welcome_discount_bps', 0))) . '">', '0 = aus; nach E-Mail-Bestätigung') ?>
      <?= vr_admin_field('Gültig (Tage)', '<input type="number" name="welcome_days" min="1" max="365" value="' . (int)vr_config('welcome_valid_days', 90) . '">') ?>
      <?= vr_admin_field('Rückgabefrist (Tage)', '<input type="number" name="return_days" min="14" max="365" value="' . (int)vr_config('return_days', 30) . '">', 'gesetzlich 14, freiwillig mehr') ?>
    </div>
    <h3 class="asub">Hausrabatt auf die geprüfte UVP</h3>
    <div class="asetgrid">
      <?php foreach ($tiers as $k => $l): ?><?= vr_admin_field($l . ' (%)', '<input type="text" inputmode="decimal" name="tier[' . $k . ']" value="' . h($pct((int)($tierVals[$k] ?? 0))) . '">') ?><?php endforeach; ?>
      <?= vr_admin_field('Ohne Stufe (%)', '<input type="text" inputmode="decimal" name="tier_default" value="' . h($pct((int)vr_config('official_discount_bps', 2000))) . '">') ?>
    </div>
    <p class="anote">Welche Häuser zu welcher Stufe gehören, steht in inc/config.php (brand_tier). Vault liegt automatisch 5 Punkte darunter. Ein manueller Artikelpreis überstimmt den Hausrabatt.</p>
    <p><button class="btn btn--sm" type="submit"><span>Speichern</span></button></p>
  </form>
<?php vr_admin_card_close(); ?>

<?php vr_admin_card_open('Versand', 'brutto, je Zone'); ?>
  <form method="post" class="aform"><?= vr_csrf_field() ?><input type="hidden" name="section" value="shipping">
    <table class="table"><thead><tr><th>Zone</th><th>Kosten (€)</th><th>Frei ab (€)</th><th>Laufzeit (Tage)</th></tr></thead><tbody>
    <?php foreach ($shipZones as $z => $l): $s = (array)($ship[$z] ?? []); ?>
      <tr><td><?= h($l) ?></td>
        <td><input type="text" inputmode="decimal" name="ship[<?= $z ?>][cents]" value="<?= h($eur((int)($s['cents'] ?? 0))) ?>" style="width:100px"></td>
        <td><input type="text" inputmode="decimal" name="ship[<?= $z ?>][free]" value="<?= h($eur((int)($s['free_over'] ?? 0))) ?>" style="width:100px"></td>
        <td><input type="text" name="ship[<?= $z ?>][days]" value="<?= h((string)($s['days'] ?? '')) ?>" style="width:90px" placeholder="1–3"></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <p class="anote">Länderzuordnung: DE · EU-Staaten · CH, LI, NO, GB · Rest (inc/config.php shipping_countries_*). Frei ab 0 = nie kostenlos.</p>
    <p><button class="btn btn--sm" type="submit"><span>Versand speichern</span></button></p>
  </form>
<?php vr_admin_card_close(); ?>

<?php vr_admin_card_open('Firma & Impressum', vr_company_complete() ? vr_admin_pill('vollständig', 'ok') : vr_admin_pill('unvollständig', 'warn')); ?>
  <form method="post" class="aform"><?= vr_csrf_field() ?><input type="hidden" name="section" value="company">
    <div class="asetgrid">
      <?php foreach ($coKeys as $k => $l): ?><?= vr_admin_field($l, '<input type="text" name="co[' . $k . ']" value="' . h((string)($co[$k] ?? '')) . '" maxlength="200">') ?><?php endforeach; ?>
    </div>
    <p class="anote">Pflichtangaben nach §5 DDG. Leere Felder erscheinen im Impressum als sichtbare Platzhalter, nie als erfundene Angaben. Die Kontakt-E-Mail empfängt Kontaktformular und Private-Clients-Anfragen.</p>
    <p><button class="btn btn--sm" type="submit"><span>Firma speichern</span></button></p>
  </form>
<?php vr_admin_card_close(); ?>

<?php vr_admin_card_open('Nur per Datei / Umgebung'); ?>
  <dl class="adl">
    <dt>Stripe, Brevo</dt><dd>data/stripe_settings.json, data/email_settings.json oder Umgebungsvariablen · Status unter <a href="<?= h(vr_url('admin/system.php')) ?>">Systemstatus</a></dd>
    <dt>Panel-IP-Filter</dt><dd>VR_ADMIN_IPS · siehe <a href="<?= h(vr_url('admin/security.php')) ?>">Sicherheit</a></dd>
    <dt>Preisregeln, Häuser, Kategorien</dt><dd>inc/config.php (price_rules, brand_tier, cat_factor) und data/official-prices.json, kommen mit jedem Deployment</dd>
    <dt>Statistik</dt><dd>Rohdaten <?= VR_STATS_KEEP_DAYS ?> Tage, danach nur Tagessummen · data/stats/</dd>
  </dl>
<?php vr_admin_card_close(); ?>
<?php vr_admin_foot(); ?>
