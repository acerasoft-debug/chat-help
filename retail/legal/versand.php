<?php
/**
 * Versand & Lieferung
 * Tutarlar ve süreler yapılandırmadan okunuyor — kasada gösterilen bedelle bu
 * sayfadaki bedel hiçbir zaman ayrışmasın (PAngV §6'nın ruhu da bu).
 */

declare(strict_types=1);

require_once __DIR__ . '/_doc.php';

vr_doc_start('legal_shipping', '2026-08-01');

$ship = (array)vr_config('shipping');
$zones = [
    'de'    => ['Deutschland', (array)vr_config('shipping_countries_de', [])],
    'eu'    => ['Europäische Union', (array)vr_config('shipping_countries_eu', [])],
    'ch'    => ['Schweiz, Liechtenstein, Norwegen, Vereinigtes Königreich', (array)vr_config('shipping_countries_ch', [])],
    'world' => ['Weitere Zielgebiete', []],
];
?>

<h2>Versandkosten und Laufzeiten</h2>
<table>
  <thead>
    <tr><th>Zielgebiet</th><th>Versandkosten</th><th>Versandfrei ab</th><th>Laufzeit</th></tr>
  </thead>
  <tbody>
  <?php foreach ($zones as $key => [$label, $countries]):
      $row = (array)($ship[$key] ?? []); ?>
    <tr>
      <td><?= h($label) ?></td>
      <td><?= h(vr_money((int)($row['cents'] ?? 0))) ?></td>
      <td><?= (int)($row['free_over'] ?? 0) > 0 ? h(vr_money((int)$row['free_over'])) : '—' ?></td>
      <td><?= h((string)($row['days'] ?? '')) ?> Werktage</td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<p>
  Alle Beträge sind Endpreise inklusive Umsatzsteuer. Die für Ihre Bestellung geltenden
  Versandkosten werden im Warenkorb angezeigt, sobald Sie das Lieferland gewählt haben, und im
  Bestellvorgang vor Abgabe der Bestellung noch einmal betragsgenau ausgewiesen.
</p>

<h3>Belieferte EU-Länder</h3>
<p style="font-size:13px;color:var(--muted)">
  <?= h(implode(' · ', $zones['eu'][1])) ?>
</p>
<h3>Weitere Zielgebiete</h3>
<p style="font-size:13px;color:var(--muted)">
  <?= h(implode(' · ', array_values(array_diff(vr_shipping_countries(),
      $zones['de'][1], $zones['eu'][1], $zones['ch'][1])))) ?>
</p>
<p>
  Ist Ihr Land nicht aufgeführt, schreiben Sie uns — vieles lässt sich einzeln lösen.
</p>

<h2>Versandbeginn</h2>
<p>
  Die Laufzeit beginnt mit der Zahlungsfreigabe. Bei Kartenzahlung, Apple Pay und Google Pay ist das
  in der Regel sofort; bei SEPA-Lastschrift und Klarna können ein bis drei Bankarbeitstage
  hinzukommen. Bestellungen, die bis 13:00 Uhr freigegeben sind, gehen in der Regel am selben
  Werktag raus.
</p>

<h2>Mehrere Verkäufer, mehrere Pakete</h2>
<p>
  <?= h((string)vr_config('brand')) ?> ist ein Marktplatz. Enthält Ihre Bestellung Artikel
  verschiedener Verkäufer, versendet jeder Verkäufer separat. Sie erhalten dann mehrere Pakete und
  mehrere Sendungslinks — bezahlen aber nur einmal, und Versandkosten fallen nur einmal an.
</p>

<h2>Sendungsverfolgung</h2>
<p>
  Jede Sendung ist versichert und wird mit Sendungsnummer versandt. Den Link erhalten Sie per E-Mail,
  sobald das Paket übergeben wurde. Den aktuellen Stand können Sie außerdem jederzeit über
  <a href="<?= h(vr_url('order.php')) ?>"><?= te('order_status') ?></a> mit Bestellnummer und
  E-Mail-Adresse abrufen.
</p>

<h2>Packstation und abweichende Lieferadresse</h2>
<p>
  Innerhalb Deutschlands liefern wir an DHL-Packstationen; geben Sie die Packstation samt Postnummer
  als Lieferadresse an. Für internationale Sendungen ist eine Straßenanschrift erforderlich.
</p>

<h2>Zoll und Einfuhrabgaben</h2>
<p>
  Innerhalb der EU fallen keine Zölle oder Einfuhrabgaben an. Bei Lieferungen in die Schweiz, nach
  Norwegen, ins Vereinigte Königreich oder außerhalb Europas können Einfuhrumsatzsteuer, Zoll und
  Bearbeitungsgebühren des Transportdienstleisters anfallen. Diese trägt der Empfänger und sie sind
  nicht Teil des bei uns gezahlten Preises.
</p>

<h2>Nicht zugestellte Sendungen</h2>
<p>
  Wird ein Paket als unzustellbar an den Verkäufer zurückgesandt, klären wir mit Ihnen den erneuten
  Versand. Erneute Versandkosten fallen an, wenn die Unzustellbarkeit auf einer unvollständigen oder
  unrichtigen Lieferadresse beruht.
</p>

<h2>Transportschäden</h2>
<p>
  Kommt ein Paket erkennbar beschädigt an, nehmen Sie es gerne an, dokumentieren Sie den Schaden mit
  Fotos und melden Sie sich innerhalb von 14 Tagen bei uns. Wir regeln solche Fälle unabhängig vom
  Widerrufsrecht — auch bei Privatverkäufern. Ihre gesetzlichen Rechte werden dadurch nicht
  eingeschränkt.
</p>

<p style="font-size:13px;color:var(--muted)">
  Siehe auch <a href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a> und
  <a href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a>.
</p>

<?php vr_doc_end(); ?>
