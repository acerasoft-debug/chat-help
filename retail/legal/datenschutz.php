<?php
/**
 * Datenschutzerklärung (DSGVO)
 * ----------------------------
 * Bu metin sitenin GERÇEKTE yaptığı işleme göre yazıldı — kopyala-yapıştır bir
 * şablon değil. Bu yüzden burada olmayan şeyler de açıkça yazılı: analitik yok,
 * pazarlama pikseli yok, dış CDN yok, Google Fonts yok. Site bu nedenle rıza
 * bandı gerektirmiyor (TDDDG §25 Abs. 2).
 */

declare(strict_types=1);

require_once __DIR__ . '/_doc.php';

vr_doc_start('legal_privacy', '2026-08-01');

$c     = vr_config('company');
$co    = trim((string)($c['legal_name'] ?? ''));
$email = trim((string)($c['email'] ?? ''));
$mail  = vr_mail_settings();
?>

<h2>1. Verantwortlicher</h2>
<?php vr_company_block(); ?>
<p>
  Ein Datenschutzbeauftragter ist nicht bestellt, da die gesetzlichen Voraussetzungen
  (Art. 37 DSGVO, § 38 BDSG) nicht vorliegen. Für Datenschutzanfragen wenden Sie sich an
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[E-Mail]</em>' ?>.
</p>

<h2>2. Was wir <em>nicht</em> tun</h2>
<p>
  Wir setzen kein Webanalyse-Werkzeug ein (kein Google Analytics, kein Matomo), keine
  Werbe- oder Tracking-Pixel, keine Social-Media-Plugins und kein Profiling. Es findet keine
  automatisierte Entscheidungsfindung im Sinne des Art. 22 DSGVO statt. Auch die Preisbildung im
  Premium Outlet ist nicht personalisiert: Preise ergeben sich aus einem festen, veröffentlichten
  Plan und sind für alle Besucher identisch.
</p>
<p>
  Alle Schriftarten, Stylesheets, Skripte und Bilder werden von unserem eigenen Server geladen.
  Insbesondere kommen keine Google Fonts zum Einsatz — dadurch wird Ihre IP-Adresse beim Seitenaufruf
  an keinen Dritten übermittelt.
</p>

<h2>3. Aufruf der Website (Server-Logfiles)</h2>
<p>
  Beim Aufruf verarbeitet unser Hoster technisch notwendige Daten: IP-Adresse, Datum und Uhrzeit,
  abgerufene Ressource, Referrer, User-Agent und übertragene Datenmenge. Diese Daten sind für die
  Auslieferung der Seite und die Abwehr von Angriffen erforderlich.
</p>
<ul>
  <li><strong>Zweck:</strong> Bereitstellung, Stabilität, IT-Sicherheit</li>
  <li><strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse)</li>
  <li><strong>Speicherdauer:</strong> in der Regel 7–30 Tage, danach automatische Löschung</li>
</ul>

<h2>4. Cookies und lokale Speicherung</h2>
<p>
  Wir verwenden ausschließlich technisch notwendige Cookies. Eine Einwilligung ist dafür nach
  § 25 Abs. 2 Nr. 2 TDDDG nicht erforderlich — deshalb sehen Sie bei uns kein Cookie-Banner.
</p>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Name</th><th>Zweck</th><th>Dauer</th></tr></thead>
  <tbody>
    <tr><td><code><?= h((string)vr_config('session_name', 'maxsales_retail')) ?></code></td>
        <td>Sitzung: Warenkorb, Verkäufer-Login, CSRF-Schutz</td><td>Sitzungsende</td></tr>
    <tr><td><code>vr_lang</code></td><td>gewählte Sprache</td><td>180 Tage</td></tr>
    <tr><td><code>vr_member</code></td>
        <td>Vault-Erstzugang nach bestätigter Newsletter-Anmeldung (signierter Wert, keine
            Klartext-E-Mail)</td><td>1 Jahr</td></tr>
    <tr><td><code>vr_wish</code></td><td>Merkliste — nur Artikelkennungen</td><td>180 Tage</td></tr>
    <tr><td><code>vr_seen</code></td><td>zuletzt angesehene Artikel — nur Artikelkennungen</td><td>30 Tage</td></tr>
  </tbody>
</table></div>
<p>
  Weitere Hinweise: <a href="<?= h(vr_url('legal/cookies.php')) ?>"><?= te('legal_cookies') ?></a>.
</p>

<h2>5. Merkliste und zuletzt angesehene Artikel</h2>
<p>
  Merkliste und „Zuletzt angesehen“ speichern wir <strong>ausschließlich in einem Cookie auf Ihrem
  Gerät</strong>. Der Cookie enthält nur Artikelkennungen (z. B. <code>blm-ah0eg000</code>) — keinen
  Namen, keine E-Mail-Adresse, keine Kennung, die Sie identifizierbar macht. Auf unseren Servern
  entsteht dazu kein Profil und keine Zuordnung zu Ihrer Person.
</p>
<ul>
  <li><strong>Zweck:</strong> die von Ihnen ausdrücklich gewünschte Funktion</li>
  <li><strong>Rechtsgrundlage:</strong> § 25 Abs. 2 Nr. 2 TDDDG (technisch erforderlich für den
      vom Nutzer angeforderten Dienst); soweit personenbezogen, Art. 6 Abs. 1 lit. f DSGVO</li>
  <li><strong>Speicherdauer:</strong> Merkliste 180 Tage, zuletzt angesehen 30 Tage — oder bis Sie
      die Cookies löschen</li>
</ul>

<h2>6. Kontaktformular</h2>
<p>
  Nutzen Sie das Kontaktformular, verarbeiten wir Ihre E-Mail-Adresse, optional Name und
  Bestellnummer sowie den Inhalt Ihrer Nachricht. Eine Kopie wird auf unserem Server gespeichert,
  damit keine Anfrage verloren geht, wenn der E-Mail-Versand scheitert.
</p>
<ul>
  <li><strong>Zweck:</strong> Beantwortung Ihrer Anfrage</li>
  <li><strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. b DSGVO bei Bestellbezug, sonst
      Art. 6 Abs. 1 lit. f DSGVO</li>
  <li><strong>Speicherdauer:</strong> bis zur abschließenden Bearbeitung, danach längstens sechs
      Monate; bei Bestellbezug gelten die handelsrechtlichen Fristen</li>
</ul>
<p>
  Zur Spamabwehr setzen wir ein unsichtbares Formularfeld und eine Zeitmessung ein. Es wird
  <em>kein</em> externer Captcha-Dienst eingebunden — dadurch werden keine Daten an Dritte
  übertragen.
</p>

<h2>7. Preisalarm im Vault</h2>
<p>
  Wenn Sie für ein Los einen Preisalarm setzen, speichern wir Ihre E-Mail-Adresse, das Los, Ihren
  Wunschpreis, den Zeitpunkt und einen gesalzenen Hashwert Ihrer IP-Adresse als Nachweis.
</p>
<ul>
  <li><strong>Zweck:</strong> die eine von Ihnen angeforderte Benachrichtigung</li>
  <li><strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. a DSGVO (Einwilligung)</li>
  <li><strong>Speicherdauer:</strong> bis zum Versand der Benachrichtigung, längstens 90 Tage.
      Danach wird der Datensatz vollständig gelöscht.</li>
</ul>
<p>
  Es wird <strong>genau eine</strong> E-Mail versendet; danach ist der Alarm verbraucht. Erinnerungen
  oder Werbung folgen nicht. Jeder Alarm lässt sich über den Link in der E-Mail sofort löschen.
</p>

<h2>8. Bestellung und Vertragsabwicklung</h2>
<p>
  Für eine Bestellung verarbeiten wir: Name, Liefer- und Rechnungsadresse, E-Mail-Adresse,
  bestellte Artikel, Preise, Zahlungsstatus, Bestellnummer sowie einen Nachweis Ihrer Zustimmung zu
  AGB und Widerrufsbelehrung (Zeitpunkt, Fassung und ein gesalzener Hashwert Ihrer IP-Adresse — die
  IP selbst wird dabei nicht gespeichert).
</p>
<ul>
  <li><strong>Zweck:</strong> Vertragserfüllung, Versand, Rechnungsstellung, Rückabwicklung</li>
  <li><strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. b DSGVO; für Aufbewahrung
      Art. 6 Abs. 1 lit. c DSGVO</li>
  <li><strong>Speicherdauer:</strong> Bestell- und Rechnungsdaten unterliegen handels- und
      steuerrechtlichen Aufbewahrungsfristen (§ 147 AO, § 257 HGB) und werden entsprechend
      aufbewahrt, danach gelöscht.</li>
</ul>
<p>
  <strong>Weitergabe an Verkäufer:</strong> Bei Artikeln von Drittverkäufern übermitteln wir dem
  jeweiligen Verkäufer die für Versand und Rechnungsstellung erforderlichen Daten (Name,
  Lieferadresse, bestellte Artikel, Bestellnummer). Der Verkäufer ist für diese Daten eigener
  Verantwortlicher. Nicht übermittelt werden Zahlungsdaten und Angaben zu Artikeln anderer Verkäufer.
</p>

<h2>9. Zahlungsabwicklung (Stripe)</h2>
<p>
  Zahlungen werden über Stripe Payments Europe, Limited, 1 Grand Canal Street Lower, Grand Canal
  Dock, Dublin, Irland abgewickelt. Ihre Zahlungsdaten geben Sie direkt bei Stripe ein; wir erhalten
  von Stripe lediglich Statusinformationen (bezahlt/offen/fehlgeschlagen), Betrag, Zahlungsart in
  allgemeiner Form, Name, E-Mail-Adresse und Lieferadresse.
</p>
<ul>
  <li><strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. b DSGVO (Vertragserfüllung)</li>
  <li><strong>Drittlandübermittlung:</strong> Stripe kann Daten an Stripe, Inc. in den USA
      übermitteln. Grundlage sind Standardvertragsklauseln der EU-Kommission sowie die
      Zertifizierung nach dem EU-US Data Privacy Framework.</li>
</ul>
<p>
  Für Auszahlungen an Verkäufer nutzen wir Stripe Connect. Verkäufer schließen dafür eine eigene
  Vereinbarung mit Stripe; die dort erhobenen Identitätsnachweise (KYC/Geldwäscheprävention)
  verarbeitet Stripe als eigener Verantwortlicher. Wir erhalten lediglich die Statusmerkmale
  <code>charges_enabled</code>, <code>payouts_enabled</code> und <code>details_submitted</code>.
</p>
<p>Datenschutzhinweise von Stripe: <a href="https://stripe.com/privacy" rel="noopener">stripe.com/privacy</a></p>

<h2>10. E-Mail-Versand</h2>
<p>
  Transaktions-E-Mails (Bestellbestätigung, Versandmitteilung, Verkäuferbenachrichtigung) und
  Newsletter versenden wir über
  <?= h($mail['provider'] === 'brevo' ? 'Brevo (Sendinblue GmbH / Brevo SAS, Paris, Frankreich)' : 'unseren Mailserver') ?>.
  Übermittelt werden E-Mail-Adresse, Name und Inhalt der Nachricht.
</p>
<ul>
  <li><strong>Rechtsgrundlage:</strong> Transaktionsmails Art. 6 Abs. 1 lit. b DSGVO;
      Newsletter Art. 6 Abs. 1 lit. a DSGVO (Einwilligung)</li>
  <li><strong>Auftragsverarbeitung:</strong> Es besteht ein Vertrag nach Art. 28 DSGVO.</li>
</ul>

<h2>11. Newsletter / Vault-Mitgliedschaft</h2>
<p>
  Die Anmeldung erfolgt im Double-Opt-in-Verfahren: nach Eingabe der Adresse erhalten Sie eine
  Bestätigungs-E-Mail; erst mit Klick auf den Link wird die Anmeldung wirksam. Zum Nachweis
  speichern wir Zeitpunkt der Anmeldung, Zeitpunkt der Bestätigung und einen gesalzenen Hashwert der
  IP-Adresse.
</p>
<p>
  Nicht bestätigte Anmeldungen löschen wir automatisch nach 7 Tagen. Sie können Ihre Einwilligung
  jederzeit widerrufen — über den Abmeldelink in jeder E-Mail oder per Nachricht an uns. Der Widerruf
  berührt nicht die Rechtmäßigkeit der bis dahin erfolgten Verarbeitung.
</p>

<h2>12. Verkäuferkonten</h2>
<p>
  Für ein Verkäuferkonto verarbeiten wir: Name, ggf. Firmenname, E-Mail-Adresse, Land, ggf.
  USt-IdNr., Verkäufertyp (gewerblich/privat), Passwort (nur als kryptografischer Hash, niemals im
  Klartext), Angebote sowie Umsatz- und Auszahlungsdaten.
</p>
<ul>
  <li><strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. b DSGVO; für die Prüfung der Angebote und
      die Nachvollziehbarkeit von Händlerangaben zusätzlich Art. 6 Abs. 1 lit. c DSGVO in Verbindung
      mit Art. 30 Digital Services Act.</li>
  <li><strong>Veröffentlichung:</strong> Bei gewerblichen Verkäufern zeigen wir Name/Firma und Land
      auf der Produktseite an; das ist gesetzlich vorgeschrieben. Bei privaten Verkäufern wird
      lediglich der Status „<?= te('seller_private') ?>“ angezeigt, kein vollständiger Name.</li>
</ul>

<h2>13. Sicherheitsmaßnahmen und Protokolle</h2>
<p>
  Wir führen technische Protokolle über fehlgeschlagene Anmeldeversuche, Zahlungsfehler und
  Webhook-Ereignisse. Sie enthalten Zeitpunkt, Ereignisart und technische Kennungen; E-Mail-Adressen
  werden dabei gekürzt. Zweck ist Missbrauchs- und Betrugsabwehr (Art. 6 Abs. 1 lit. f DSGVO),
  Speicherdauer maximal 90 Tage.
</p>
<p>
  Die Übertragung erfolgt verschlüsselt (TLS). Passwörter werden mit einem modernen
  Einweg-Hashverfahren gespeichert.
</p>

<h2>14. Ihre Rechte</h2>
<p>Sie haben jederzeit das Recht auf:</p>
<ul>
  <li>Auskunft über die zu Ihrer Person gespeicherten Daten (Art. 15 DSGVO)</li>
  <li>Berichtigung unrichtiger Daten (Art. 16 DSGVO)</li>
  <li>Löschung (Art. 17 DSGVO), soweit keine Aufbewahrungspflicht entgegensteht</li>
  <li>Einschränkung der Verarbeitung (Art. 18 DSGVO)</li>
  <li>Datenübertragbarkeit (Art. 20 DSGVO)</li>
  <li>Widerspruch gegen Verarbeitungen auf Grundlage berechtigter Interessen (Art. 21 DSGVO)</li>
  <li>Widerruf erteilter Einwilligungen mit Wirkung für die Zukunft (Art. 7 Abs. 3 DSGVO)</li>
</ul>
<p>
  Zur Ausübung genügt eine Nachricht an
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[E-Mail]</em>' ?>.
  Außerdem haben Sie das Recht, sich bei einer Datenschutz-Aufsichtsbehörde zu beschweren, etwa bei
  der Behörde Ihres gewöhnlichen Aufenthaltsorts.
</p>

<h2>15. Änderungen</h2>
<p>
  Wir passen diese Erklärung an, wenn sich die tatsächliche Verarbeitung ändert — etwa bei Einsatz
  eines neuen Dienstleisters. Es gilt die jeweils auf dieser Seite veröffentlichte Fassung.
</p>

<?php vr_doc_end(); ?>
