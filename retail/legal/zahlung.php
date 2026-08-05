<?php
/**
 * Zahlungsarten
 * Hangi yöntemlerin gerçekten açık olduğu Stripe Dashboard'daki ayara bağlı;
 * bu sayfa yöntemleri ve akışı anlatıyor, hangi anahtar modunun etkin olduğunu
 * da (test/live) dürüstçe gösteriyor.
 */

declare(strict_types=1);

require_once __DIR__ . '/_doc.php';

vr_doc_start('legal_payment', '2026-08-01');

$mode = vr_stripe_settings()['mode'];
?>

<h2>Wie die Zahlung abläuft</h2>
<p>
  Sie legen Artikel in die Tasche, wählen an der Kasse das Lieferland und bestätigen AGB und
  Widerrufsbelehrung. Zum Bezahlen leiten wir Sie zu unserem Zahlungsdienstleister Stripe weiter.
  Dort geben Sie Ihre Zahlungsdaten ein und schließen die Zahlung ab; anschließend kommen Sie zur
  Bestellbestätigung zurück.
</p>
<p>
  <strong>Ihre Kartendaten erreichen unsere Server nie.</strong> Wir erhalten von Stripe lediglich die
  Information, ob die Zahlung erfolgreich war, den Betrag, die Zahlungsart in allgemeiner Form und
  die für Versand und Rechnung nötigen Angaben.
</p>

<h2>Verfügbare Zahlungsarten</h2>
<table>
  <thead><tr><th>Zahlungsart</th><th>Abbuchung</th><th>Hinweis</th></tr></thead>
  <tbody>
    <tr><td>Visa, Mastercard, American Express</td><td>sofort</td>
        <td>3-D-Secure-Bestätigung Ihrer Bank kann erforderlich sein (SCA)</td></tr>
    <tr><td>Apple Pay</td><td>sofort</td><td>auf Apple-Geräten in Safari</td></tr>
    <tr><td>Google Pay</td><td>sofort</td><td>in Chrome und auf Android</td></tr>
    <tr><td>Klarna</td><td>je nach gewählter Option</td>
        <td>Rechnung oder Ratenzahlung; Vertragspartner der Finanzierung ist Klarna</td></tr>
    <tr><td>SEPA-Lastschrift</td><td>1–3 Bankarbeitstage</td>
        <td>Versand nach Zahlungsfreigabe</td></tr>
  </tbody>
</table>
<p style="font-size:13px;color:var(--muted)">
  Welche Zahlungsarten tatsächlich angezeigt werden, hängt von Lieferland, Betrag und Gerät ab —
  Stripe blendet nur ein, was für Ihre Bestellung nutzbar ist. Zusätzliche Kosten entstehen Ihnen
  durch keine der angebotenen Zahlungsarten.
</p>

<?php if ($mode !== 'live'): ?>
  <div class="notice" style="border-left-color:var(--ember)">
    <strong>Diese Installation läuft derzeit nicht im Live-Modus.</strong>
    Es können keine echten Zahlungen ausgeführt werden
    (<?= $mode === 'test' ? 'Stripe-Testmodus aktiv' : 'keine Stripe-Schlüssel hinterlegt' ?>).
  </div>
<?php endif; ?>

<h2>Fälligkeit</h2>
<p>
  Der Kaufpreis ist mit Vertragsschluss fällig. Bei Zahlungsarten mit verzögerter Abwicklung
  reservieren wir Ihre Artikel und versenden nach Zahlungsfreigabe.
</p>

<h2>Währung und Steuer</h2>
<p>
  Alle Preise sind in Euro angegeben und enthalten die gesetzliche Umsatzsteuer von
  <?= h((int)vr_config('vat_rate_bps', 1900) / 100) ?> %, sofern der jeweilige Verkäufer
  umsatzsteuerpflichtig ist. Bei Artikeln von Privatverkäufern wird keine Umsatzsteuer ausgewiesen.
  Zahlt Ihre Bank in einer anderen Währung, kann Ihr Kreditinstitut ein Umrechnungsentgelt berechnen —
  darauf haben wir keinen Einfluss.
</p>

<h2>Rechnung</h2>
<p>
  Die Rechnung erhalten Sie mit der Ware oder per E-Mail. Bei Artikeln gewerblicher Drittverkäufer
  stellt der jeweilige Händler die Rechnung; bei eigener Ware
  <?= h((string)(vr_config('company')['legal_name'] ?? '')) ?>. Privatverkäufer stellen keine
  Rechnung mit Umsatzsteuerausweis.
</p>

<h2>Erstattungen</h2>
<p>
  Erstattungen erfolgen immer auf dasselbe Zahlungsmittel, mit dem Sie bezahlt haben. Bei Klarna
  läuft die Erstattung über Ihr Klarna-Konto, bei SEPA auf das belastete Konto. Die Bearbeitung
  starten wir nach Eingang und Prüfung der Rücksendung; bis zur Gutschrift bei Ihrer Bank können
  je nach Zahlungsart einige Werktage liegen.
</p>

<h2>Fehlgeschlagene Zahlung</h2>
<p>
  Wird eine Zahlung abgelehnt, kommt kein Vertrag zustande und es wird nichts abgebucht. Ihre Tasche
  bleibt erhalten, sodass Sie es erneut oder mit einer anderen Zahlungsart versuchen können. Häufigste
  Ursache ist eine nicht abgeschlossene 3-D-Secure-Bestätigung.
</p>

<h2>Zahlungssicherheit</h2>
<p>
  Die Verbindung ist durchgängig per TLS verschlüsselt. Stripe ist als Zahlungsdienstleister nach
  PCI-DSS Level 1 zertifiziert und in Europa als Zahlungsinstitut zugelassen (Stripe Payments Europe,
  Limited, Dublin). Zur Betrugsabwehr prüft Stripe Transaktionen automatisiert; wir speichern keine
  Kartennummern.
</p>

<p style="font-size:13px;color:var(--muted)">
  Details zur Datenverarbeitung: <a href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>.
</p>

<?php vr_doc_end(); ?>
