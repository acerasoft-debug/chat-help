<?php
/**
 * Cookie-Richtlinie
 * Bant yok, çünkü izleme yok. Bu sayfa tam olarak neyin saklandığını sayıyor —
 * "sitemiz deneyiminizi iyileştirmek için çerez kullanır" tipi boş metin değil.
 */

declare(strict_types=1);

require_once __DIR__ . '/_doc.php';

vr_doc_start('legal_cookies', '2026-08-01');
?>

<div class="doc__box">
  <strong>Warum Sie hier kein Cookie-Banner sehen</strong>
  <p style="margin-top:8px">
    Ein Banner ist nur nötig, wenn Cookies gesetzt werden, die über das technisch Notwendige
    hinausgehen — Analyse, Werbung, Tracking. Wir setzen nichts davon ein. Für rein funktionale
    Cookies erlaubt § 25 Abs. 2 Nr. 2 TDDDG das Setzen ohne Einwilligung. Deshalb: kein Banner,
    keine „Alle akzeptieren“-Schaltfläche, kein Consent-Dienst.
  </p>
</div>

<h2>Vollständige Liste</h2>
<table>
  <thead><tr><th>Name</th><th>Typ</th><th>Zweck</th><th>Speicherdauer</th></tr></thead>
  <tbody>
    <tr>
      <td><code><?= h((string)vr_config('session_name', 'maxsales_retail')) ?></code></td>
      <td>Sitzungscookie</td>
      <td>Hält Ihre Sitzung zusammen: Inhalt der Tasche, Vault-Reservierungen, Verkäufer-Login und
          das Sicherheitstoken gegen Formularfälschung (CSRF). Enthält nur eine zufällige Kennung,
          keine personenbezogenen Inhalte.</td>
      <td>bis Ende der Browsersitzung</td>
    </tr>
    <tr>
      <td><code>vr_lang</code></td>
      <td>funktional</td>
      <td>Merkt die gewählte Sprache, damit Sie sie nicht bei jedem Klick neu wählen müssen.
          Inhalt: ein Sprachkürzel wie <code>de</code>.</td>
      <td>180 Tage</td>
    </tr>
    <tr>
      <td><code>vr_member</code></td>
      <td>funktional</td>
      <td>Wird nur gesetzt, wenn Sie die Vault-Mitgliedschaft per E-Mail bestätigt haben, und
          schaltet den Erstzugang zu neuen Losen frei. Enthält ein Ablaufdatum, einen gekürzten
          Hashwert Ihrer E-Mail-Adresse und eine Signatur — nicht Ihre Adresse im Klartext.</td>
      <td>1 Jahr</td>
    </tr>
  </tbody>
</table>

<h2>Cookies von Stripe</h2>
<p>
  Beim Bezahlen wechseln Sie auf eine Seite von Stripe. Stripe setzt dort eigene Cookies, die für
  Zahlungsabwicklung und Betrugsprävention erforderlich sind. Das geschieht auf der Domain von
  Stripe und unterliegt der
  <a href="https://stripe.com/privacy" rel="noopener">Datenschutzerklärung von Stripe</a>.
  Auf unseren eigenen Seiten wird kein Stripe-Skript eingebunden.
</p>

<h2>Kein Local Storage, keine Fingerprints</h2>
<p>
  Wir verwenden weder <code>localStorage</code> noch <code>sessionStorage</code>, keine
  Pixel, keine Fingerprinting-Techniken und keine geräteübergreifende Wiedererkennung. Sämtliche
  Schriftarten, Stile, Skripte und Bilder liegen auf unserem eigenen Server; beim Seitenaufruf wird
  keine Verbindung zu Dritten aufgebaut.
</p>

<h2>Cookies löschen oder blockieren</h2>
<p>
  Sie können Cookies jederzeit in Ihren Browsereinstellungen löschen oder blockieren. Blockieren Sie
  das Sitzungscookie, funktionieren Tasche, Kasse und Verkäufer-Login nicht mehr — dann fehlt der
  technische Faden, der Ihre Schritte verbindet. Sprache und Vault-Zugang lassen sich problemlos
  blockieren; dann fragen wir die Sprache erneut und der Erstzugang entfällt.
</p>

<p style="font-size:13px;color:var(--muted)">
  Ausführlich zur Datenverarbeitung:
  <a href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>.
</p>

<?php vr_doc_end(); ?>
