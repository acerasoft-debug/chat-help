<?php
/**
 * AGB — Allgemeine Geschäftsbedingungen (Kaufverträge)
 * ----------------------------------------------------
 * Pazaryeri modeli burada net kurulmalı: sözleşme kimin arasında kuruluyor,
 * platformun rolü ne, privat satıcıda hangi hükümler düşüyor. Vault (Premium
 * Outlet) kendine ait bir bölüm alıyor çünkü fiyat mekanizması standart bir
 * "sabit fiyatlı teklif" değil.
 */

declare(strict_types=1);

require_once __DIR__ . '/_doc.php';

vr_doc_start('legal_terms', '2026-08-01');

$brand = (string)vr_config('brand');
$c     = vr_config('company');
$co    = trim((string)($c['legal_name'] ?? ''));
$email = trim((string)($c['email'] ?? ''));
$wd    = (int)vr_config('withdrawal_days', 14);
$days  = (int)vr_config('return_days', 30);
$vat   = (int)vr_config('vat_rate_bps', 1900) / 100;
$steps = (int)vr_config('vault_steps', 6);
$hours = (int)vr_config('vault_step_hours', 24);
?>

<nav class="doc__toc">
  <a href="#s1">1. Geltungsbereich und Vertragspartner</a>
  <a href="#s2">2. Rolle der Plattform</a>
  <a href="#s3">3. Vertragsschluss</a>
  <a href="#s4">4. Preise und Versandkosten</a>
  <a href="#s5">5. Zahlung</a>
  <a href="#s6">6. Lieferung</a>
  <a href="#s7">7. Eigentumsvorbehalt</a>
  <a href="#s8">8. Widerruf und freiwillige Rückgabe</a>
  <a href="#s9">9. Mängelhaftung</a>
  <a href="#s10">10. Käufe von Privatverkäufern</a>
  <a href="#s11">11. Premium Outlet (Vault)</a>
  <a href="#s12">12. Echtheit und Herkunft</a>
  <a href="#s13">13. Haftung</a>
  <a href="#s14">14. Datenschutz</a>
  <a href="#s15">15. Schlussbestimmungen</a>
</nav>

<h2 id="s1">1. Geltungsbereich und Vertragspartner</h2>
<p>
  1.1 Diese Allgemeinen Geschäftsbedingungen gelten für alle Bestellungen, die über
  <?= h($brand) ?> (<a href="<?= h(vr_origin()) ?>"><?= h(vr_origin()) ?></a>) aufgegeben werden.
  Betreiber der Plattform ist <?= h($co) ?> (nachfolgend „Betreiber“, „wir“).
</p>
<p>
  1.2 Auf der Plattform werden drei Arten von Angeboten geführt, jeweils gekennzeichnet auf der
  Produktseite:
</p>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Kennzeichnung</th><th>Verkäufer</th><th>Vertragspartner des Kaufvertrags</th></tr></thead>
  <tbody>
    <tr><td><?= h($brand) ?>-Bestand</td><td>der Betreiber selbst</td><td><?= h($co) ?></td></tr>
    <tr><td>Händler</td><td>gewerblicher Drittverkäufer</td><td>der jeweilige Händler</td></tr>
    <tr><td>Privatverkäufer</td><td>Privatperson</td><td>die jeweilige Privatperson</td></tr>
  </tbody>
</table></div>
<p>
  1.3 Bei Angeboten von Drittverkäufern kommt der Kaufvertrag ausschließlich zwischen Ihnen und dem
  jeweiligen Verkäufer zustande. Der Betreiber wird nicht Vertragspartei des Kaufvertrags. Für die
  Nutzung der Plattform selbst — Zahlungsabwicklung, Bestellübersicht, Vermittlung — gelten diese AGB
  zwischen Ihnen und dem Betreiber.
</p>
<p>
  1.4 Verbraucher ist jede natürliche Person, die ein Rechtsgeschäft zu Zwecken abschließt, die
  überwiegend weder ihrer gewerblichen noch ihrer selbständigen beruflichen Tätigkeit zugerechnet
  werden können (§ 13 BGB). Abweichende Bedingungen des Kunden werden nicht Vertragsinhalt, es sei
  denn, wir stimmen ihrer Geltung ausdrücklich in Textform zu.
</p>

<h2 id="s2">2. Rolle der Plattform</h2>
<p>
  2.1 Der Betreiber stellt die technische Infrastruktur bereit, prüft Angebote von Drittverkäufern
  vor der Freischaltung auf Plausibilität und Herkunftsnachweise, wickelt die Zahlung über den
  Zahlungsdienstleister Stripe ab und leitet den Verkäuferanteil nach Abzug der Provision an den
  Verkäufer weiter.
</p>
<p>
  2.2 Der Betreiber ist von den Drittverkäufern bevollmächtigt, Zahlungen des Käufers mit
  schuldbefreiender Wirkung entgegenzunehmen. Ihre Zahlungsverpflichtung gegenüber dem Verkäufer ist
  mit erfolgreicher Zahlung über die Plattform erfüllt.
</p>
<p>
  2.3 Erklärungen zum Kaufvertrag — insbesondere Widerruf, Mängelanzeige und Rücktritt — können Sie
  wirksam an <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '[E-Mail]' ?>
  richten. Wir leiten sie unverzüglich an den betroffenen Verkäufer weiter und unterstützen bei der
  Abwicklung.
</p>

<h2 id="s3">3. Vertragsschluss</h2>
<p>
  3.1 Die Darstellung der Waren auf der Plattform stellt kein rechtlich bindendes Angebot dar,
  sondern eine Aufforderung zur Bestellung.
</p>
<p>
  3.2 Mit dem Anklicken des Bestellbuttons („<?= te('checkout_go') ?>“ und anschließender Zahlung bei
  Stripe) geben Sie ein verbindliches Angebot zum Kauf der im Warenkorb enthaltenen Waren ab. Zuvor
  können Sie Ihre Eingaben auf der Kassenseite prüfen und korrigieren.
</p>
<p>
  3.3 Wir bestätigen den Eingang Ihrer Bestellung unverzüglich per E-Mail. Diese
  Eingangsbestätigung stellt noch keine Annahme dar. Der Kaufvertrag kommt zustande, wenn wir bzw.
  der Verkäufer die Annahme erklären oder die Ware versenden — spätestens jedoch mit der
  Bestellbestätigung, sofern diese die Annahme ausdrücklich erklärt.
</p>
<p>
  3.4 Kommt der Vertrag nicht zustande, etwa weil die Ware nach Bestellung nicht mehr verfügbar ist,
  informieren wir Sie unverzüglich und erstatten bereits geleistete Zahlungen vollständig.
</p>
<p>
  3.5 Der Vertragstext wird gespeichert und Ihnen mit der Bestellbestätigung in Textform
  (E-Mail) einschließlich dieser AGB und der Widerrufsbelehrung übermittelt.
</p>

<h2 id="s4">4. Preise und Versandkosten</h2>
<p>
  4.1 Alle angegebenen Preise sind Endpreise in Euro und enthalten die gesetzliche Umsatzsteuer von
  derzeit <?= h($vat) ?> %, sofern der jeweilige Verkäufer umsatzsteuerpflichtig ist. Bei
  Privatverkäufern wird keine Umsatzsteuer ausgewiesen (§ 19 UStG bzw. Verkauf durch
  Nichtunternehmer).
</p>
<p>
  4.2 Zusätzlich zu den Warenpreisen fallen Versandkosten an. Diese werden auf der Kassenseite
  vor Abgabe der Bestellung gesondert und betragsgenau angezeigt. Einzelheiten unter
  <a href="<?= h(vr_url('legal/versand.php')) ?>"><?= te('legal_shipping') ?></a>.
</p>
<p>
  4.3 Enthält Ihre Bestellung Waren mehrerer Verkäufer, werden die Versandkosten nur einmal
  berechnet; die Sendungen können getrennt bei Ihnen eintreffen.
</p>
<p>
  4.4 Bei Lieferungen in Länder außerhalb der EU können zusätzlich Zölle, Einfuhrumsatzsteuer und
  Bearbeitungsgebühren anfallen, die vom Empfänger zu tragen sind.
</p>

<h2 id="s5">5. Zahlung</h2>
<p>
  5.1 Die Zahlung erfolgt über den Zahlungsdienstleister Stripe (Stripe Payments Europe, Limited,
  Dublin, Irland). Verfügbare Zahlungsarten werden im Bestellvorgang angezeigt; eine Übersicht
  finden Sie unter <a href="<?= h(vr_url('legal/zahlung.php')) ?>"><?= te('legal_payment') ?></a>.
</p>
<p>
  5.2 Der Kaufpreis ist mit Vertragsschluss fällig. Bei Zahlungsarten mit verzögerter Abwicklung
  (z. B. SEPA-Lastschrift, Klarna) versenden wir nach Zahlungsfreigabe durch den Zahlungsdienstleister.
</p>
<p>
  5.3 Zahlungsdaten, insbesondere Kartendaten, werden ausschließlich vom Zahlungsdienstleister
  verarbeitet. Der Betreiber erhält und speichert keine vollständigen Kartendaten.
</p>
<p>
  5.4 Bei Rückbuchungen, die Sie zu vertreten haben, sind wir berechtigt, die uns dadurch
  entstehenden Kosten zu berechnen, sofern Sie die Rückbuchung schuldhaft veranlasst haben.
</p>

<h2 id="s6">6. Lieferung</h2>
<p>
  6.1 Die Lieferung erfolgt an die von Ihnen angegebene Lieferadresse. Lieferzeiten und Zielgebiete
  sind unter <a href="<?= h(vr_url('legal/versand.php')) ?>"><?= te('legal_shipping') ?></a> angegeben
  und beginnen mit Zahlungsfreigabe.
</p>
<p>
  6.2 Bei Waren von Drittverkäufern versendet der jeweilige Verkäufer. Sie erhalten je Sendung eine
  eigene Sendungsverfolgung.
</p>
<p>
  6.3 Ist die Ware ausnahmsweise nicht lieferbar, obwohl sie auf der Plattform als verfügbar
  angezeigt wurde, informieren wir Sie unverzüglich und erstatten den bezahlten Betrag vollständig.
  Ein Anspruch auf Nachlieferung eines vergleichbaren Artikels besteht nicht, da es sich vielfach um
  Einzelstücke handelt.
</p>
<p>
  6.4 Das Risiko des zufälligen Untergangs und der zufälligen Verschlechterung geht bei
  Verbrauchern erst mit Übergabe der Ware an Sie über, auch wenn der Versand durch einen
  Transportdienstleister erfolgt (§ 475 Abs. 2 BGB).
</p>

<h2 id="s7">7. Eigentumsvorbehalt</h2>
<p>
  Die Ware bleibt bis zur vollständigen Bezahlung Eigentum des jeweiligen Verkäufers.
</p>

<h2 id="s8">8. Widerruf und freiwillige Rückgabe</h2>
<p>
  8.1 Verbrauchern steht bei Verträgen mit gewerblichen Verkäufern ein gesetzliches Widerrufsrecht
  von <?= (int)$wd ?> Tagen zu. Die vollständige Belehrung samt Muster-Widerrufsformular finden Sie
  unter <a href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a>.
</p>
<p>
  8.2 Über das gesetzliche Widerrufsrecht hinaus gewähren wir für eigene Ware ein freiwilliges
  Rückgaberecht von <?= (int)$days ?> Tagen ab Erhalt. Voraussetzung: die Ware ist ungetragen,
  unbeschädigt und mit vollständigen Originaletiketten. Das freiwillige Rückgaberecht beschränkt
  Ihre gesetzlichen Rechte nicht. Kosten der Rücksendung im Rahmen der freiwilligen Verlängerung
  trägt der Käufer; Einzelheiten unter
  <a href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a>.
</p>
<p>
  8.3 Bei Käufen von Privatverkäufern besteht kein gesetzliches Widerrufsrecht (siehe Ziffer 10).
</p>

<h2 id="s9">9. Mängelhaftung</h2>
<p>
  9.1 Bei gewerblichen Verkäufern gilt die gesetzliche Mängelhaftung nach §§ 434 ff. BGB. Für
  Verbraucher beträgt die Verjährungsfrist zwei Jahre ab Erhalt der Ware.
</p>
<p>
  9.2 Bei gebrauchter Ware kann die Verjährungsfrist gegenüber Verbrauchern auf ein Jahr verkürzt
  sein, wenn dies vor Vertragsschluss ausdrücklich und gesondert vereinbart wurde. Ein solcher
  Hinweis wird gegebenenfalls auf der Produktseite angezeigt.
</p>
<p>
  9.3 Gebrauchsspuren, die in der Artikelbeschreibung genannt sind, stellen keinen Mangel dar.
  Abweichungen in Farbdarstellung durch Bildschirmwiedergabe sind kein Mangel.
</p>
<p>
  9.4 Transportschäden melden Sie uns bitte innerhalb von 14 Tagen mit Fotos. Wir wickeln diese
  Fälle unabhängig von der Frage des Widerrufsrechts ab, auch bei Privatverkäufern.
</p>

<h2 id="s10">10. Käufe von Privatverkäufern</h2>
<p>
  10.1 Angebote von Privatpersonen sind auf der Produktseite, im Warenkorb und im Bestellvorgang als
  „<?= te('seller_private') ?>“ gekennzeichnet. Vor Abschluss der Bestellung müssen Sie die
  besonderen Folgen ausdrücklich bestätigen.
</p>
<p>
  10.2 Da der Verkäufer kein Unternehmer ist, besteht kein gesetzliches Widerrufsrecht. Die
  gesetzliche Mängelhaftung kann vom Privatverkäufer wirksam ausgeschlossen oder beschränkt werden;
  ein solcher Ausschluss gilt nicht für Arglist oder vorsätzlich falsche Angaben.
</p>
<p>
  10.3 Unabhängig davon gilt: Weicht die gelieferte Ware wesentlich von der Beschreibung ab oder
  handelt es sich nicht um Originalware, erstatten wir den vollständigen Kaufpreis einschließlich
  Versandkosten. Wir behalten den Verkäuferanteil in diesen Fällen zurück oder fordern ihn zurück.
</p>
<p>
  10.4 Privatverkäufer, die tatsächlich planmäßig, wiederholt und mit Gewinnabsicht verkaufen,
  handeln gewerblich. Stellen wir dies fest, stufen wir das Konto um oder sperren es; in diesem Fall
  gelten für bereits geschlossene Verträge die Rechte gegenüber Unternehmern.
</p>

<h2 id="s11">11. Premium Outlet (Vault)</h2>
<p>
  11.1 Im Bereich „Premium Outlet“ (Vault) wird jedes Stück als einzelnes Los mit einem
  Eröffnungspreis, einem Mindestpreis und einem veröffentlichten Senkungsplan angeboten. Der Preis
  sinkt in <?= (int)$steps ?> gleichmäßigen Stufen, je eine Stufe alle <?= (int)$hours ?> Stunden, bis
  zum Mindestpreis.
</p>
<p>
  11.2 Der Plan wird bei Öffnung des Loses festgeschrieben und danach nicht geändert. Der Preis kann
  nicht steigen. Die Berechnung erfolgt serverseitig aus dem Plan und ist für alle Besucher
  identisch; eine personalisierte Preisbildung findet nicht statt.
</p>
<p>
  11.3 Maßgeblich ist der Preis, der zum Zeitpunkt des Hinzufügens zum Warenkorb angezeigt und für
  die Dauer des Bestellvorgangs (20 Minuten) für Sie reserviert wird. Nach Ablauf der Reservierung
  wird das Los wieder freigegeben.
</p>
<p>
  11.4 Jedes Los existiert nur einmal. Es endet mit dem ersten wirksamen Kauf. Ein Anspruch darauf,
  ein Los zu einer späteren, niedrigeren Stufe zu erwerben, besteht nicht.
</p>
<p>
  11.5 Bei Preissenkungen weisen wir gemäß § 11 PAngV den niedrigsten Preis der letzten 30 Tage aus.
  Da der Vault-Preis ausschließlich fällt, ist dies der unmittelbar vor der aktuellen Stufe geltende
  Preis. In der ersten Stufe liegt keine Preissenkung vor; dort wird kein Referenzpreis beworben.
</p>
<p>
  11.6 Ihre gesetzlichen Rechte, insbesondere Widerruf und Mängelhaftung, gelten im Vault
  unverändert. Ziffer 10 bleibt für Privatverkäufer anwendbar.
</p>
<p>
  11.7 Mitglieder mit bestätigter E-Mail-Anmeldung erhalten Zugang zu neuen Losen vor der
  allgemeinen Freischaltung. Die Mitgliedschaft ist kostenlos und jederzeit widerrufbar; ein
  Anspruch auf Vorabzugang besteht nicht.
</p>
<p>
  11.8 Ein gesetzter Preisalarm und das Merken eines Stückes reservieren dieses <strong>nicht</strong>
  und begründen kein Vorkaufsrecht. Die Benachrichtigung erfolgt einmalig und ohne Gewähr für
  Zustellung oder Zeitpunkt; maßgeblich bleibt allein die Verfügbarkeit im Moment der Bestellung.
</p>

<h2 id="s12">12. Echtheit und Herkunft</h2>
<p>
  12.1 Es wird ausschließlich Originalware angeboten. Verkäufer sind verpflichtet, die
  Einkaufsbelege ihrer Ware vorzuhalten und uns auf Anfrage vorzulegen.
</p>
<p>
  12.2 Stellt sich nach dem Kauf heraus, dass ein Artikel keine Originalware ist, erstatten wir den
  vollständigen Kaufpreis zuzüglich Versandkosten und übernehmen die Kosten der Rücksendung. Der
  Anspruch besteht unabhängig von der Art des Verkäufers.
</p>
<p>
  12.3 Ansprüche nach Ziffer 12.2 setzen voraus, dass Sie uns die Ware und Ihre Beanstandung
  innerhalb von 30 Tagen nach Erhalt zugänglich machen und uns eine Prüfung ermöglichen.
</p>

<h2 id="s13">13. Haftung</h2>
<p>
  13.1 Wir haften unbeschränkt für Vorsatz und grobe Fahrlässigkeit, bei Verletzung von Leben,
  Körper und Gesundheit, nach den Vorschriften des Produkthaftungsgesetzes sowie im Umfang einer von
  uns übernommenen Garantie.
</p>
<p>
  13.2 Bei leicht fahrlässiger Verletzung einer wesentlichen Vertragspflicht ist die Haftung auf den
  vertragstypischen, vorhersehbaren Schaden begrenzt. Im Übrigen ist die Haftung ausgeschlossen.
</p>
<p>
  13.3 Für Pflichtverletzungen von Drittverkäufern aus dem Kaufvertrag haften wir nicht; unsere
  Verantwortlichkeit richtet sich insoweit nach den Vorschriften über Hostingdienste
  (Art. 6 Digital Services Act). Ziffern 10.3 und 12.2 bleiben davon unberührt.
</p>
<p>
  13.4 Für die ununterbrochene Verfügbarkeit der Plattform wird keine Gewähr übernommen.
</p>

<h2 id="s14">14. Datenschutz</h2>
<p>
  Informationen zur Verarbeitung Ihrer personenbezogenen Daten finden Sie in der
  <a href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>.
  Zur Abwicklung Ihrer Bestellung geben wir die für Versand und Rechnungsstellung erforderlichen
  Daten an den jeweiligen Verkäufer weiter.
</p>

<h2 id="s15">15. Schlussbestimmungen</h2>
<p>
  15.1 Es gilt das Recht der Bundesrepublik Deutschland. Bei Verbrauchern mit Wohnsitz in einem
  anderen Staat bleiben die zwingenden Verbraucherschutzvorschriften ihres Aufenthaltsstaates
  unberührt (Art. 6 Abs. 2 Rom-I-VO).
</p>
<p>
  15.2 Erfüllungsort und Gerichtsstand richten sich nach den gesetzlichen Vorschriften. Für
  Verbraucher gelten die gesetzlichen Gerichtsstände.
</p>
<p>
  15.3 Sollten einzelne Bestimmungen dieser AGB unwirksam sein, bleibt die Wirksamkeit der übrigen
  Bestimmungen unberührt. An die Stelle der unwirksamen Bestimmung tritt die gesetzliche Regelung.
</p>
<p>
  15.4 Wir behalten uns vor, diese AGB mit Wirkung für die Zukunft zu ändern. Für bereits
  geschlossene Verträge gilt die Fassung, die bei Vertragsschluss abrufbar war; die jeweils
  akzeptierte Fassung wird zu Ihrer Bestellung gespeichert (aktuelle Fassung:
  <?= h(VR_TERMS_VERSION) ?>).
</p>

<div class="doc__box">
  <strong>Hinweis zur Verwendung dieses Dokuments</strong>
  <p style="margin-top:8px">
    Dieser Text ist als vollständige, betriebsbereite Grundlage für den beschriebenen
    Marktplatzbetrieb geschrieben und deckt die üblichen Pflichtangaben ab. Er ist keine
    Rechtsberatung. Vor dem Livegang sollten die Angaben zu Unternehmen, Steuerpflicht
    (insbesondere Umsatzsteuer bei Lieferungen aus einem Nicht-EU-Unternehmen nach Deutschland) und
    die Ausgestaltung der Verkäuferbeziehung anwaltlich geprüft werden.
  </p>
</div>

<?php vr_doc_end(); ?>
