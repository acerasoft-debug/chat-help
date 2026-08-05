<?php
/**
 * Barrierefreiheitserklärung (BFSG)
 * ---------------------------------
 * Barrierefreiheitsstärkungsgesetz 28.06.2025'ten beri B2C e-ticareti kapsıyor.
 * Bu sayfa şablon değil: sitede GERÇEKTEN yapılmış olan şeyleri (klavye
 * erişimi, kontrast, JS'siz çalışma) ve bilinen eksikleri sayıyor.
 */

declare(strict_types=1);

require_once __DIR__ . '/_doc.php';

vr_doc_start('legal_accessibility', '2026-08-01');

$email = trim((string)(vr_config('company')['email'] ?? ''));
$mail  = $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[E-Mail]</em>';
?>

<h2>Unser Anspruch</h2>
<p>
  Wir möchten, dass dieser Shop für alle nutzbar ist — mit Tastatur, mit Screenreader, mit
  vergrößerter Schrift, mit reduzierter Bewegung. Grundlage sind die Anforderungen des
  Barrierefreiheitsstärkungsgesetzes (BFSG) und die Norm EN 301 549, die auf WCAG 2.1 Level AA
  verweist.
</p>

<h2>Stand der Umsetzung</h2>
<p>
  Diese Website ist nach unserer Einschätzung <strong>weitgehend konform</strong> mit
  WCAG 2.1 Level AA. Die Einschätzung beruht auf einer internen Prüfung, nicht auf einem
  externen Audit.
</p>

<h3>Was umgesetzt ist</h3>
<ul>
  <li><strong>Ohne JavaScript nutzbar:</strong> Navigation, Filter, Tasche, Kasse und
      Verkäuferbereich funktionieren vollständig mit reinen HTML-Formularen. JavaScript liefert nur
      Komfort (Countdown, Mengenschalter, sanfte Einblendungen).</li>
  <li><strong>Tastaturbedienung:</strong> alle interaktiven Elemente sind erreichbar, mit sichtbarem
      Fokusring; ein „Zum Inhalt springen“-Link steht am Seitenanfang.</li>
  <li><strong>Kontraste:</strong> Text erfüllt mindestens 4,5:1 gegenüber dem Hintergrund; im dunklen
      Vault-Bereich wurden Preis- und Hinweistexte entsprechend aufgehellt.</li>
  <li><strong>Struktur:</strong> eine H1 pro Seite, logische Überschriftenebenen, Landmarken
      (<code>header</code>, <code>nav</code>, <code>main</code>, <code>footer</code>), beschriftete
      Formularfelder, verknüpfte Fehlermeldungen.</li>
  <li><strong>Bilder:</strong> Produktbilder tragen Alternativtexte aus Marke und Artikelbezeichnung;
      rein dekorative Grafiken sind für Hilfsmittel ausgeblendet.</li>
  <li><strong>Bewegung:</strong> bei aktivierter Systemeinstellung „Bewegung reduzieren“ werden
      Laufband, Einblendungen und Übergänge abgeschaltet.</li>
  <li><strong>Zoom und kleine Bildschirme:</strong> das Layout bleibt bis 400 % Zoom nutzbar, ohne
      horizontales Scrollen des Seiteninhalts.</li>
  <li><strong>Sprache:</strong> die Seitensprache ist im HTML ausgezeichnet und über die
      Sprachauswahl umschaltbar.</li>
</ul>

<h3>Bekannte Einschränkungen</h3>
<ul>
  <li><strong>Produktbilder von Drittverkäufern:</strong> Alternativtexte werden automatisch aus
      Marke und Bezeichnung gebildet. Sie beschreiben das Motiv nicht im Detail — bei
      Rückfragen zu einem Artikel beschreiben wir ihn Ihnen gerne per E-Mail.</li>
  <li><strong>Zahlungsseite:</strong> der Bezahlvorgang läuft bei Stripe. Für die Barrierefreiheit
      dieser Seiten ist Stripe verantwortlich; uns liegen keine Konformitätsdefizite vor, wir haben
      sie aber nicht selbst geprüft.</li>
  <li><strong>Vault-Countdown:</strong> die verbleibende Zeit aktualisiert sich sekündlich. Der
      maßgebliche Preis steht als Text daneben und ändert sich nur nach einem Seitenaufruf, sodass
      Screenreader-Nutzung nicht durch Live-Änderungen gestört wird.</li>
  <li><strong>PDF-Belege:</strong> Rechnungen und Rücksendelabels werden teils von Verkäufern und
      Transportdienstleistern erzeugt und sind möglicherweise nicht getaggt. Auf Anfrage stellen wir
      die Inhalte in barrierefreier Form bereit.</li>
</ul>

<h2>Feedback und Kontakt</h2>
<p>
  Stoßen Sie auf eine Barriere, schreiben Sie an <?= $mail ?> mit dem Betreff „Barrierefreiheit“ und
  nennen Sie möglichst die Seite und Ihr Hilfsmittel. Wir antworten innerhalb eines Werktags und
  nennen einen Termin für die Behebung. Benötigen Sie eine Information in anderer Form — größere
  Schrift, Klartext, Vorlesen am Telefon — sagen Sie es einfach; wir stellen sie kostenfrei bereit.
</p>

<h2>Durchsetzungsverfahren</h2>
<p>
  Hilft unsere Antwort nicht weiter, können Sie sich an die Marktüberwachungsstelle der Länder für
  die Barrierefreiheit von Produkten und Dienstleistungen (MDBD) wenden. Diese ist die nach dem BFSG
  zuständige Stelle für Beschwerden über die Barrierefreiheit von Dienstleistungen:
  <a href="https://www.marktueberwachungsstelle.de" rel="noopener">marktueberwachungsstelle.de</a>.
</p>

<h2>Erstellung dieser Erklärung</h2>
<p>
  Diese Erklärung wurde am <?= h(vr_date(strtotime('2026-08-01'))) ?> auf Grundlage einer internen
  Selbstbewertung erstellt: Prüfung mit Tastaturnavigation, Kontrastmessung der eingesetzten
  Farbwerte, Test mit deaktiviertem JavaScript sowie Prüfung der Seitenstruktur. Wir aktualisieren
  sie, wenn sich der Shop wesentlich ändert.
</p>

<?php vr_doc_end(); ?>
