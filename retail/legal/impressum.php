<?php
/**
 * Impressum — §5 DDG (eski TMG §5), §18 MStV
 * Bir US LLC olarak Almanya'ya yönelik satış yapıldığında da Impressum
 * zorunluluğu geçerli: belirleyici olan hizmetin Almanya'ya yöneltilmiş
 * olması, şirketin nerede kurulduğu değil.
 */

declare(strict_types=1);

require_once __DIR__ . '/_doc.php';

vr_doc_start('legal_imprint', '2026-08-01');
$c     = vr_config('company');
$email = trim((string)($c['email'] ?? ''));
?>

<h2>Anbieter</h2>
<?php vr_company_block(); ?>

<h2>Marktplatzbetreiber</h2>
<p>
  <?= h((string)vr_config('brand')) ?> ist ein Online-Marktplatz, betrieben von
  <?= h((string)($c['legal_name'] ?? '')) ?>. Über die Plattform werden sowohl eigene Waren des
  Betreibers als auch Waren Dritter (gewerbliche Händler und private Verkäufer) angeboten.
  Wer Vertragspartner des Kaufvertrags ist, wird auf jeder Produktseite und im Bestellvorgang
  vor Abgabe der Bestellung angezeigt.
</p>

<h2>Verantwortlich für den Inhalt</h2>
<p>
  Verantwortlich nach § 18 Abs. 2 MStV ist die oben genannte vertretungsberechtigte Person,
  Anschrift wie oben.
</p>

<h2>Kontakt für Verbraucheranfragen</h2>
<p>
  Anfragen zu Bestellungen, Rücksendungen und Beschwerden richten Sie bitte an
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[E-Mail-Adresse]</em>' ?>.
  Wir antworten in der Regel innerhalb eines Werktags. Für Fragen zu einem Artikel eines
  Drittverkäufers vermitteln wir den Kontakt zum jeweiligen Verkäufer oder leiten Ihre Anfrage weiter.
</p>

<h2>Verbraucherstreitbeilegung</h2>
<p>
  Wir sind nicht verpflichtet und nicht bereit, an Streitbeilegungsverfahren vor einer
  Verbraucherschlichtungsstelle teilzunehmen. Das schließt eine gütliche Einigung mit uns
  nicht aus — bitte wenden Sie sich zuerst direkt an uns. Weitere Hinweise unter
  <a href="<?= h(vr_url('legal/streitbeilegung.php')) ?>"><?= te('legal_disputes') ?></a>.
</p>

<h2>Zahlungsdienstleister</h2>
<p>
  Zahlungen werden über Stripe abgewickelt (Stripe Payments Europe, Limited, 1 Grand Canal Street
  Lower, Grand Canal Dock, Dublin, Irland). Auszahlungen an Drittverkäufer erfolgen über Stripe
  Connect. Kartendaten werden ausschließlich bei Stripe verarbeitet und erreichen unsere Systeme nicht.
</p>

<h2>Haftung für Inhalte</h2>
<p>
  Als Diensteanbieter sind wir für eigene Inhalte auf diesen Seiten nach den allgemeinen Gesetzen
  verantwortlich. Für Angebote von Drittverkäufern sind wir nicht verpflichtet, übermittelte oder
  gespeicherte fremde Informationen zu überwachen oder nach Umständen zu forschen, die auf eine
  rechtswidrige Tätigkeit hinweisen (Art. 6, 8 Digital Services Act). Sobald wir Kenntnis von einer
  konkreten Rechtsverletzung erlangen, entfernen wir den betreffenden Inhalt unverzüglich.
  Meldungen richten Sie an
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[E-Mail-Adresse]</em>' ?>.
</p>
<p>
  Wir prüfen Angebote von Drittverkäufern vor der Freischaltung auf Plausibilität und verlangen
  Nachweise zur Herkunft. Diese freiwillige Prüfung begründet keine Gewähr für die Rechtmäßigkeit
  oder Echtheit jedes einzelnen Angebots und lässt unsere Haftungsprivilegierung als
  Hostingdiensteanbieter unberührt.
</p>

<h2>Haftung für Links</h2>
<p>
  Unser Angebot enthält Links zu externen Websites Dritter, auf deren Inhalte wir keinen Einfluss
  haben. Für diese Inhalte ist stets der jeweilige Anbieter verantwortlich. Zum Zeitpunkt der
  Verlinkung waren keine rechtswidrigen Inhalte erkennbar.
</p>

<h2>Urheberrecht</h2>
<p>
  Die durch den Betreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen dem
  Urheberrecht. Produktbilder und -beschreibungen von Drittverkäufern werden von diesen
  bereitgestellt; diese sichern uns zu, über die erforderlichen Rechte zu verfügen. Marken- und
  Produktbezeichnungen sind Eigentum der jeweiligen Rechteinhaber. Ihre Nennung dient allein der
  Beschreibung der angebotenen Ware und begründet keine Handelsbeziehung zu den Markeninhabern.
</p>

<h2>Hinweis zu Markenrechten</h2>
<p>
  <?= h((string)vr_config('brand')) ?> ist kein autorisierter Vertragshändler der genannten Marken,
  sofern nicht ausdrücklich anders angegeben. Die angebotene Ware ist Originalware, die innerhalb
  des Europäischen Wirtschaftsraums erstmals in Verkehr gebracht wurde; der Weiterverkauf ist damit
  nach dem Grundsatz der Erschöpfung des Markenrechts (§ 24 MarkenG, Art. 15 UMV) zulässig.
</p>

<?php
vr_doc_end();
