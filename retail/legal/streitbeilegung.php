<?php
/**
 * Streitbeilegung + DSA iletişim noktası + bildirim usulü (Art. 16 DSA)
 * Pazaryeri işletmecisinin bildirim/şikayet mekanizmasını YAYINLAMASI gerekiyor;
 * bu sayfa onu somut bir akış olarak veriyor.
 */

declare(strict_types=1);

require_once __DIR__ . '/_doc.php';

vr_doc_start('legal_disputes', '2026-08-01');

$email = trim((string)(vr_config('company')['email'] ?? ''));
$mail  = $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[E-Mail]</em>';
?>

<h2>Zuerst der direkte Weg</h2>
<p>
  Die meisten Probleme lassen sich in einer E-Mail lösen. Schreiben Sie an <?= $mail ?> mit Ihrer
  Bestellnummer und einer kurzen Beschreibung. Wir antworten in der Regel innerhalb eines Werktags
  und melden uns spätestens nach sieben Tagen mit einer Entscheidung oder einem Zwischenstand.
</p>

<h2>Beschwerden über Verkäufer</h2>
<p>
  Bei Problemen mit einem Drittverkäufer — Ware nicht angekommen, Zustand abweichend, Erstattung
  ausgeblieben — vermitteln wir und können den Verkäuferanteil zurückhalten, bis die Sache geklärt
  ist. Kommt es zu keiner Lösung, erstatten wir in den in den
  <a href="<?= h(vr_url('legal/agb.php')) ?>#s10">AGB Ziffer 10.3</a> und
  <a href="<?= h(vr_url('legal/agb.php')) ?>#s12">12.2</a> genannten Fällen selbst.
</p>

<h2>Meldung rechtswidriger Inhalte (Art. 16 DSA)</h2>
<p>
  Jede Person kann uns Angebote melden, die sie für rechtswidrig hält — etwa Fälschungen,
  Markenrechtsverletzungen oder unzulässige Produkte. Bitte geben Sie an:
</p>
<ul>
  <li>die genaue Adresse (URL) des Angebots,</li>
  <li>eine Begründung, warum der Inhalt rechtswidrig sein soll,</li>
  <li>Ihren Namen und eine E-Mail-Adresse (außer bei Meldungen zu Straftaten gegen Personen),</li>
  <li>eine Erklärung, dass Ihre Angaben nach bestem Wissen richtig und vollständig sind.</li>
</ul>
<p>
  Meldungen richten Sie an <?= $mail ?> mit dem Betreff „DSA-Meldung“. Wir bestätigen den Eingang
  unverzüglich, entscheiden zügig und sorgfältig und teilen Ihnen die Entscheidung samt Begründung
  mit. Rechteinhaber, die uns wiederholt zutreffende Meldungen zusenden, behandeln wir vorrangig
  (Art. 22 DSA).
</p>

<h2>Wenn wir ein Angebot entfernen</h2>
<p>
  Betroffene Verkäufer werden über eine Entfernung, Sperrung oder Herabsetzung der Sichtbarkeit
  begründet informiert (Art. 17 DSA) und können der Entscheidung innerhalb von 14 Tagen per E-Mail
  widersprechen. Der Widerspruch wird von einer Person geprüft, die an der ursprünglichen
  Entscheidung nicht beteiligt war. Die Entscheidung über den Widerspruch teilen wir begründet mit.
</p>
<p>
  Bei offensichtlich unbegründeten Meldungen oder wiederholt rechtswidrigen Angeboten setzen wir die
  Bearbeitung nach angemessener Vorwarnung aus bzw. sperren das Konto (Art. 23 DSA).
</p>

<h2>Außergerichtliche Streitbeilegung für Verbraucher</h2>
<p>
  Wir sind nicht verpflichtet und nicht bereit, an Streitbeilegungsverfahren vor einer
  Verbraucherschlichtungsstelle nach dem Verbraucherstreitbeilegungsgesetz (VSBG) teilzunehmen.
  Ihre Möglichkeit, gerichtlich vorzugehen, bleibt davon unberührt; ebenso unser Bemühen, jeden
  Fall zuerst direkt zu klären.
</p>
<p>
  Hinweis: Die frühere Online-Streitbeilegungsplattform (OS-Plattform) der Europäischen Kommission
  wurde zum 20. Juli 2025 eingestellt. Ein Link darauf ist deshalb entfallen. Sie können sich bei
  grenzüberschreitenden Fällen an das Europäische Verbraucherzentrum wenden
  (<a href="https://www.evz.de" rel="noopener">evz.de</a>).
</p>

<h2>Gerichtsstand und anwendbares Recht</h2>
<p>
  Es gilt deutsches Recht. Für Verbraucher gelten die gesetzlichen Gerichtsstände; zwingende
  Verbraucherschutzvorschriften des Aufenthaltsstaates bleiben unberührt
  (Art. 6 Abs. 2 Rom-I-VO).
</p>

<h2>Behördliche Kontaktstelle</h2>
<p>
  Für Anfragen von Behörden und Gerichten nach Art. 11 DSA erreichen Sie uns unter <?= $mail ?>.
  Verfahrenssprachen sind Deutsch und Englisch.
</p>

<?php vr_doc_end(); ?>
