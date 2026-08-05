<?php
/**
 * Rückgabe (freiwilliges Rückgaberecht + praktische Abwicklung)
 * Bilinçli olarak Widerrufsbelehrung'dan AYRI sayfa: birinde yasal metin,
 * burada "nasıl yapılır". İkisi karışınca yasal metin okunmaz hale geliyor.
 */

declare(strict_types=1);

require_once __DIR__ . '/_doc.php';

vr_doc_start('legal_returns', '2026-08-01');

$days  = (int)vr_config('return_days', 30);
$wd    = (int)vr_config('withdrawal_days', 14);
$email = trim((string)(vr_config('company')['email'] ?? ''));
?>

<h2>Auf einen Blick</h2>
<table>
  <thead><tr><th>Verkäufer</th><th>Frist</th><th>Kosten der Rücksendung</th></tr></thead>
  <tbody>
    <tr><td><?= h((string)vr_config('brand')) ?>-Bestand</td>
        <td><?= (int)$days ?> Tage (gesetzlich <?= (int)$wd ?> + freiwillige Verlängerung)</td>
        <td>innerhalb der gesetzlichen Frist aus Deutschland kostenlos</td></tr>
    <tr><td>Händler</td>
        <td><?= (int)$wd ?> Tage gesetzlicher Widerruf, viele Händler gewähren mehr</td>
        <td>innerhalb der gesetzlichen Frist aus Deutschland kostenlos</td></tr>
    <tr><td>Privatverkäufer</td>
        <td>kein gesetzliches Widerrufsrecht</td>
        <td>Rücknahme nur bei Abweichung von der Beschreibung</td></tr>
  </tbody>
</table>

<h2>So senden Sie zurück</h2>
<ol>
  <li>Schreiben Sie an
      <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[E-Mail]</em>' ?>
      mit Ihrer Bestellnummer und den Artikeln, die zurückgehen sollen.</li>
  <li>Sie erhalten innerhalb eines Werktags ein Rücksendelabel und die Rücksendeadresse des
      jeweiligen Verkäufers.</li>
  <li>Packen Sie die Ware möglichst im Originalkarton, legen Sie den Lieferschein bei und geben Sie
      das Paket ab.</li>
  <li>Nach Eingang und Prüfung erstatten wir auf dasselbe Zahlungsmittel — spätestens 14 Tage nach
      Eingang Ihrer Widerrufserklärung, sobald die Ware bei uns ist oder Sie den Versand
      nachgewiesen haben.</li>
</ol>
<p>
  Eine Rücksendung ohne vorherige Ankündigung nehmen wir ebenfalls an; die Bearbeitung dauert dann
  aber länger, weil die Zuordnung manuell erfolgt.
</p>

<h2>Zustand der Rücksendung</h2>
<p>
  Anprobieren ist ausdrücklich in Ordnung — genau dafür ist das Rückgaberecht da. Bitte senden Sie
  Artikel ungetragen, ungewaschen, unparfümiert und mit vollständigen Originaletiketten zurück.
  Für einen Wertverlust durch einen darüber hinausgehenden Umgang können wir einen Ausgleich
  verlangen; wir rechnen das nachvollziehbar auf und melden uns vorher bei Ihnen.
</p>

<h2>Was nicht zurückgenommen werden kann</h2>
<ul>
  <li>Bademode und Ohrschmuck ohne intaktes Hygienesiegel;</li>
  <li>individuell angepasste oder auf Ihre Vorgaben gefertigte Artikel;</li>
  <li>Artikel von Privatverkäufern, sofern die Ware der Beschreibung entspricht.</li>
</ul>

<h2>Umtausch</h2>
<p>
  Ein direkter Umtausch ist nicht möglich, weil die meisten Artikel Einzelstücke oder einzelne
  Größensätze sind. Senden Sie die Ware zurück und bestellen Sie die passende Größe neu — sofern
  noch verfügbar. Bei Interesse an einer bestimmten Größe schreiben Sie uns; wir sagen, ob Nachschub
  erwartbar ist.
</p>

<h2>Artikel defekt oder nicht wie beschrieben</h2>
<p>
  Dann greift nicht die Rückgabe, sondern die Mängelhaftung — mit besseren Rechten für Sie. Melden
  Sie sich mit Fotos; die Rücksendung ist in diesem Fall immer kostenlos, auch bei Privatverkäufern
  und auch nach Ablauf der Rückgabefrist innerhalb der gesetzlichen Verjährung.
</p>

<h2>Keine Originalware</h2>
<p>
  Sollte sich ein Artikel als nicht original erweisen, erstatten wir den vollständigen Kaufpreis
  einschließlich Versandkosten und übernehmen die Rücksendung — unabhängig davon, welcher Verkäufer
  ihn angeboten hat. Der betroffene Verkäufer wird von der Plattform entfernt.
</p>

<h2>Premium Outlet</h2>
<p>
  Reduzierte Preise ändern nichts an Ihren Rechten: für Vault-Käufe gelten dieselben Fristen wie
  oben. Nur die Ausnahme für Privatverkäufer bleibt bestehen.
</p>

<p style="font-size:13px;color:var(--muted)">
  Der rechtliche Text mit Muster-Widerrufsformular:
  <a href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a>.
</p>

<?php vr_doc_end(); ?>
