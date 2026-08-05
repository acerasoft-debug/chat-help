<?php
/**
 * Verkäuferbedingungen
 * --------------------
 * Satıcı ile platform arasındaki ilişki. Komisyon, ödeme akışı, yasak ürünler,
 * privat/gewerblich ayrımı, DSA yükümlülükleri, fesih.
 * Ücret oranları YAPILANDIRMADAN okunuyor: sözleşme metni ile faturalanan oran
 * asla ayrışmasın.
 */

declare(strict_types=1);

require_once __DIR__ . '/_doc.php';

vr_doc_start('legal_seller_terms', '2026-08-01');

$brand = (string)vr_config('brand');
$c     = vr_config('company');
$co    = trim((string)($c['legal_name'] ?? ''));
$email = trim((string)($c['email'] ?? ''));
$feeB  = (int)vr_config('fee_bps_business', 1200) / 100;
$feeP  = (int)vr_config('fee_bps_private', 900) / 100;
$feeV  = (int)vr_config('fee_bps_outlet', 1500) / 100;
$fixed = vr_money((int)vr_config('fee_fixed_cents', 35));
?>

<nav class="doc__toc">
  <a href="#v1">1. Gegenstand</a>
  <a href="#v2">2. Verkäuferkonto und Anmeldung</a>
  <a href="#v3">3. Gewerblich oder privat</a>
  <a href="#v4">4. Angebote und Prüfung</a>
  <a href="#v5">5. Verbotene Ware</a>
  <a href="#v6">6. Provision</a>
  <a href="#v7">7. Zahlung und Auszahlung</a>
  <a href="#v8">8. Versand und Retouren</a>
  <a href="#v9">9. Premium Outlet</a>
  <a href="#v10">10. Pflichten aus dem Digital Services Act</a>
  <a href="#v11">11. Rechte an Inhalten</a>
  <a href="#v12">12. Sperrung und Kündigung</a>
  <a href="#v13">13. Haftung und Freistellung</a>
  <a href="#v14">14. Schlussbestimmungen</a>
</nav>

<h2 id="v1">1. Gegenstand</h2>
<p>
  1.1 Diese Bedingungen regeln das Verhältnis zwischen <?= h($co) ?> als Betreiber des Marktplatzes
  <?= h($brand) ?> und Personen, die über die Plattform Waren anbieten („Verkäufer“).
</p>
<p>
  1.2 Der Betreiber stellt die Verkaufsfläche, die Zahlungsabwicklung über Stripe und die
  Bestellverwaltung zur Verfügung. Der Kaufvertrag über die angebotene Ware kommt ausschließlich
  zwischen dem Verkäufer und dem Käufer zustande; der Betreiber wird nicht Vertragspartei.
</p>
<p>
  1.3 Der Betreiber ist berechtigt, Zahlungen der Käufer im Namen des Verkäufers mit
  schuldbefreiender Wirkung entgegenzunehmen und Erklärungen der Käufer zum Kaufvertrag
  (insbesondere Widerruf und Mängelanzeigen) für den Verkäufer anzunehmen und weiterzuleiten.
</p>

<h2 id="v2">2. Verkäuferkonto und Anmeldung</h2>
<p>
  2.1 Die Anmeldung erfolgt online. Angaben müssen wahr, vollständig und aktuell sein. Änderungen —
  insbesondere von Firmenname, Anschrift, Steuernummer oder Verkäufertyp — sind unverzüglich
  nachzutragen.
</p>
<p>
  2.2 Zugangsdaten sind geheim zu halten. Der Verkäufer haftet für Handlungen, die über sein Konto
  vorgenommen werden, soweit ihn ein Verschulden trifft.
</p>
<p>
  2.3 Vor der ersten Freischaltung eines Angebots muss die Verifizierung bei Stripe (Stripe Connect)
  abgeschlossen sein. Ohne abgeschlossene Verifizierung kann keine Auszahlung erfolgen; Angebote
  bleiben in diesem Fall offline.
</p>

<h2 id="v3">3. Gewerblich oder privat</h2>
<p>
  3.1 Bei der Anmeldung ist anzugeben, ob als Unternehmer („Händler“) oder als Privatperson
  („Privatverkäufer“) verkauft wird. Diese Angabe wird Käufern auf der Produktseite und im
  Bestellvorgang angezeigt und bestimmt, welche Verbraucherrechte gelten.
</p>
<p>
  3.2 Wer planmäßig, wiederholt und mit Gewinnabsicht verkauft, handelt gewerblich — unabhängig von
  der Selbsteinschätzung. Die zutreffende Einordnung sowie sämtliche steuer-, gewerbe- und
  handelsrechtlichen Pflichten liegen in der Verantwortung des Verkäufers.
</p>
<p>
  3.3 Der Betreiber ist berechtigt, ein Konto nach vorheriger Ankündigung auf „Händler“ umzustellen
  oder zu sperren, wenn die tatsächliche Verkaufstätigkeit gewerblichen Charakter hat. Maßstab sind
  insbesondere Zahl der Angebote, Umsatzhöhe und Regelmäßigkeit.
</p>
<p>
  3.4 Händler sind verpflichtet, Verbrauchern das gesetzliche Widerrufsrecht zu gewähren, ordentliche
  Rechnungen auszustellen und die gesetzliche Mängelhaftung zu erfüllen.
</p>

<h2 id="v4">4. Angebote und Prüfung</h2>
<p>
  4.1 Angebote müssen zutreffend, vollständig und aktuell sein: Marke, Modellbezeichnung, Größe,
  Zustand, Preis inklusive Umsatzsteuer sowie mindestens ein eigenes, unbearbeitetes Bild der
  tatsächlich vorhandenen Ware.
</p>
<p>
  4.2 Bei vorbesitzter Ware sind Gebrauchsspuren zu beschreiben. Fehlende oder beschönigende Angaben
  gehen zulasten des Verkäufers.
</p>
<p>
  4.3 Jedes neue oder geänderte Angebot wird vor Freischaltung geprüft. Die Prüfung umfasst
  Plausibilität, Bildqualität und Preis; der Betreiber kann Herkunftsnachweise (Einkaufsbelege)
  verlangen. Ein Anspruch auf Freischaltung besteht nicht.
</p>
<p>
  4.4 Der Verkäufer hält Einkaufsbelege mindestens für die Dauer des Angebots zuzüglich zwei Jahre
  vor und legt sie auf Anfrage innerhalb von fünf Werktagen vor.
</p>
<p>
  4.5 Der Verkäufer stellt sicher, dass angebotene Ware verfügbar ist. Wiederholte Nichtlieferung
  nach Verkauf berechtigt den Betreiber zur Sperrung.
</p>

<h2 id="v5">5. Verbotene Ware</h2>
<p>Nicht angeboten werden dürfen insbesondere:</p>
<ul>
  <li>Fälschungen, Replikate, „Dupes“ sowie Ware mit entfernten oder veränderten Kennzeichen;</li>
  <li>Ware ohne nachvollziehbare Herkunft oder aus rechtswidriger Quelle;</li>
  <li>Ware, die außerhalb des EWR erstmals in Verkehr gebracht wurde, sofern der Markeninhaber dem
      Weiterverkauf im EWR nicht zugestimmt hat;</li>
  <li>Muster ohne Verkaufsfreigabe („not for resale“), Mitarbeiterware mit Weiterverkaufsverbot;</li>
  <li>Ware, die gegen Produktsicherheits-, Kennzeichnungs- oder Textilkennzeichnungsvorschriften
      verstößt;</li>
  <li>Pelz und Exotenleder ohne die erforderlichen Nachweise (CITES).</li>
</ul>
<p>
  Verstöße führen zur sofortigen Entfernung des Angebots und regelmäßig zur Kündigung des
  Verkäuferkontos.
</p>

<h2 id="v6">6. Provision</h2>
<p>6.1 Für jeden über die Plattform vermittelten Verkauf fällt eine Provision an:</p>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Verkäufertyp</th><th>Provision</th><th>Premium Outlet (Vault)</th></tr></thead>
  <tbody>
    <tr><td>Händler</td><td><?= h($feeB) ?> % + <?= h($fixed) ?> je verkauftem Artikel</td><td><?= h($feeV) ?> %</td></tr>
    <tr><td>Privatverkäufer</td><td><?= h($feeP) ?> % + <?= h($fixed) ?> je verkauftem Artikel</td><td><?= h($feeV) ?> %</td></tr>
  </tbody>
</table></div>
<p>
  6.2 Bemessungsgrundlage ist der Bruttoverkaufspreis der Artikel des Verkäufers. Versandkosten sind
  nicht Teil der Bemessungsgrundlage und verbleiben beim Betreiber, der die Versandkosten gegenüber
  dem Transportdienstleister trägt.
</p>
<p>
  6.3 Es fallen keine Einstell-, Listungs- oder Monatsgebühren an.
</p>
<p>
  6.4 Die Provision wird bei Zahlung des Käufers automatisch einbehalten. Bei vollständiger
  Rückabwicklung (Widerruf, Rücktritt, Nichtlieferung) wird die Provision erstattet, mit Ausnahme
  des Festbetrags nach Ziffer 6.1, wenn die Rückabwicklung vom Verkäufer verursacht wurde.
</p>
<p>
  6.5 Änderungen der Provision werden mindestens 30 Tage vorher per E-Mail angekündigt. Widerspricht
  der Verkäufer nicht bis zum Wirksamwerden, gilt die neue Provision als vereinbart; das Recht zur
  Kündigung nach Ziffer 12 bleibt unberührt.
</p>

<h2 id="v7">7. Zahlung und Auszahlung</h2>
<p>
  7.1 Die Zahlungsabwicklung erfolgt über Stripe. Der Verkäufer schließt hierfür eine eigene
  Vereinbarung mit Stripe (Stripe Connected Account Agreement) und akzeptiert dessen Bedingungen.
</p>
<p>
  7.2 Je nach Zusammensetzung der Bestellung wird entweder direkt an das Konto des Verkäufers
  ausgezahlt (Zahlung mit Weiterleitung) oder der Betrag zunächst auf dem Plattformkonto vereinnahmt
  und anschließend als gesonderte Überweisung an den Verkäufer transferiert. In beiden Fällen erhält
  der Verkäufer den Bruttoverkaufspreis seiner Artikel abzüglich Provision.
</p>
<p>
  7.3 Der Auszahlungsrhythmus auf das Bankkonto richtet sich nach den Regeln von Stripe. Der
  Betreiber verwahrt keine Kundengelder und schuldet keine Verzinsung.
</p>
<p>
  7.4 Ist die Stripe-Verifizierung noch nicht abgeschlossen, verbleibt der Verkäuferanteil bis zum
  Abschluss der Verifizierung auf dem Plattformkonto. Wird die Verifizierung nicht innerhalb von
  180 Tagen abgeschlossen, kann der Betreiber die betroffenen Bestellungen stornieren und den Käufern
  erstatten.
</p>
<p>
  7.5 Der Betreiber darf Auszahlungen zurückhalten oder verrechnen, soweit begründete Ansprüche gegen
  den Verkäufer bestehen — insbesondere aus Rückerstattungen an Käufer, Rückbuchungen
  (Chargebacks) oder Verstößen gegen Ziffer 5. Die Zurückhaltung wird begründet und ist auf die Höhe
  der Ansprüche begrenzt.
</p>

<h2 id="v8">8. Versand und Retouren</h2>
<p>
  8.1 Der Verkäufer versendet innerhalb von zwei Werktagen nach Zahlungseingang, versichert und mit
  Sendungsverfolgung, und trägt die Sendungsdaten unverzüglich nach.
</p>
<p>
  8.2 Retouren nimmt der Verkäufer an die von ihm angegebene Adresse zurück. Händler erstatten
  Widerrufe fristgerecht; erfolgt keine Erstattung, ist der Betreiber berechtigt, dem Käufer zu
  erstatten und den Betrag mit Auszahlungen des Verkäufers zu verrechnen.
</p>
<p>
  8.3 Bei Privatverkäufern besteht kein gesetzliches Widerrufsrecht. Weicht die Ware jedoch
  wesentlich von der Beschreibung ab oder ist sie keine Originalware, ist der Verkäufer zur Rücknahme
  und Erstattung verpflichtet.
</p>

<h2 id="v9">9. Premium Outlet (Vault)</h2>
<p>
  9.1 Für den Vault kann der Verkäufer Ware zur Verfügung stellen. Eröffnungspreis, Mindestpreis und
  Stufenplan werden vor Öffnung vereinbart und danach nicht mehr geändert.
</p>
<p>
  9.2 Der Verkäufer erkennt an, dass der Verkauf zu jedem Preis zwischen Eröffnungs- und
  Mindestpreis zustande kommen kann, und dass ein Widerruf der Teilnahme nach Öffnung des Loses
  nicht möglich ist, solange das Los läuft.
</p>
<p>
  9.3 Der Mindestpreis wird nie unterschritten. Wird ein Los bis zum Ende nicht verkauft, wird es
  geschlossen und kann erneut regulär angeboten werden.
</p>

<h2 id="v10">10. Pflichten aus dem Digital Services Act</h2>
<p>
  10.1 Gewerbliche Verkäufer stellen die nach Art. 30 DSA erforderlichen Angaben bereit: Name,
  Anschrift, Telefonnummer, E-Mail-Adresse, Handelsregister- bzw. vergleichbare Kennung und
  Umsatzsteuer-Identifikationsnummer, soweit vorhanden. Der Betreiber prüft diese Angaben mit
  angemessenen Mitteln und darf das Angebot bis zur Klärung offline nehmen.
</p>
<p>
  10.2 Der Verkäufer sichert zu, dass seine Angebote den Produktsicherheits- und
  Kennzeichnungsvorschriften entsprechen und dass er über die erforderlichen Erlaubnisse verfügt.
</p>
<p>
  10.3 Meldungen über rechtswidrige Inhalte werden nach Art. 16 DSA bearbeitet. Betroffene Verkäufer
  werden über Entfernungen begründet informiert und können widersprechen
  (<a href="<?= h(vr_url('legal/streitbeilegung.php')) ?>"><?= te('legal_disputes') ?></a>).
</p>

<h2 id="v11">11. Rechte an Inhalten</h2>
<p>
  11.1 Der Verkäufer räumt dem Betreiber ein einfaches, räumlich unbeschränktes, unentgeltliches
  Recht ein, die eingestellten Texte und Bilder zum Betrieb und zur Bewerbung der Plattform zu
  nutzen, zu bearbeiten (Zuschnitt, Farbanpassung, Größenanpassung) und zu vervielfältigen.
</p>
<p>
  11.2 Das Nutzungsrecht besteht über das Ende des Angebots hinaus, soweit es der Dokumentation
  abgeschlossener Verkäufe dient.
</p>
<p>
  11.3 Der Verkäufer sichert zu, über alle erforderlichen Rechte an den eingestellten Inhalten zu
  verfügen und keine Rechte Dritter zu verletzen.
</p>

<h2 id="v12">12. Sperrung und Kündigung</h2>
<p>
  12.1 Beide Seiten können das Verkäuferverhältnis jederzeit mit einer Frist von 14 Tagen in
  Textform kündigen. Laufende Bestellungen sind noch vollständig abzuwickeln.
</p>
<p>
  12.2 Der Betreiber kann Angebote sofort entfernen und das Konto sperren bei Verstoß gegen
  Ziffer 5, bei falschen Angaben zum Verkäuferstatus, bei wiederholter Nichtlieferung oder bei
  begründetem Verdacht auf Fälschungen. Die Sperrung wird begründet.
</p>
<p>
  12.3 Nach Beendigung werden fällige Auszahlungen nach Ablauf der Retouren- und
  Rückbuchungsfristen, spätestens 90 Tage nach der letzten Bestellung, angewiesen.
</p>

<h2 id="v13">13. Haftung und Freistellung</h2>
<p>
  13.1 Der Betreiber haftet unbeschränkt für Vorsatz und grobe Fahrlässigkeit, bei Verletzung von
  Leben, Körper und Gesundheit sowie bei Übernahme einer Garantie. Bei leicht fahrlässiger
  Verletzung wesentlicher Vertragspflichten ist die Haftung auf den vertragstypischen,
  vorhersehbaren Schaden begrenzt; im Übrigen ist sie ausgeschlossen.
</p>
<p>
  13.2 Der Verkäufer stellt den Betreiber von Ansprüchen Dritter frei, die auf einer Verletzung
  dieser Bedingungen beruhen — insbesondere aus Marken-, Urheber- oder Wettbewerbsrecht sowie aus
  Verbraucherschutzverstößen. Die Freistellung umfasst angemessene Kosten der Rechtsverteidigung.
</p>
<p>
  13.3 Eine Umsatz- oder Erfolgsgarantie wird nicht übernommen. Sichtbarkeit, Platzierung und
  Sortierung der Angebote bestimmt der Betreiber.
</p>

<h2 id="v14">14. Schlussbestimmungen</h2>
<p>
  14.1 Es gilt deutsches Recht. Ist der Verkäufer Unternehmer, ist Gerichtsstand der Sitz des
  Betreibers, soweit gesetzlich zulässig.
</p>
<p>
  14.2 Änderungen dieser Bedingungen werden mindestens 30 Tage vorher per E-Mail mitgeteilt.
  Aktuelle Fassung: <?= h(VR_TERMS_VERSION) ?>.
</p>
<p>
  14.3 Kontakt für alle Verkäuferangelegenheiten:
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[E-Mail]</em>' ?>.
</p>

<div class="doc__box">
  <strong>Hinweis</strong>
  <p style="margin-top:8px">
    Diese Bedingungen sind betriebsbereit formuliert, aber keine Rechtsberatung. Insbesondere die
    Regelungen zu Provision, Verrechnung und Zurückbehaltung sollten vor dem Livegang anwaltlich
    geprüft werden, ebenso die Frage der Umsatzsteuerpflicht der Vermittlungsleistung.
  </p>
</div>

<?php vr_doc_end(); ?>
