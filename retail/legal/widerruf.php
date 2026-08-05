<?php
/**
 * Widerrufsbelehrung + Muster-Widerrufsformular
 * ---------------------------------------------
 * Metin, BGB Anlage 1/2'deki resmi örneğe sadık kalıyor — bu metinlerde
 * "yaratıcı" olmak doğrudan hukuki risk demek. Pazaryerine özgü tek fark:
 * cayma hakkının yalnızca GEWERBLICH satıcıya karşı geçerli olduğunun ayrıca
 * anlatılması.
 */

declare(strict_types=1);

require_once __DIR__ . '/_doc.php';

vr_doc_start('legal_withdrawal', '2026-08-01');

$c     = vr_config('company');
$co    = trim((string)($c['legal_name'] ?? ''));
$email = trim((string)($c['email'] ?? ''));
$wd    = (int)vr_config('withdrawal_days', 14);
$addr  = trim(implode(', ', array_filter([
    trim((string)($c['street'] ?? '')),
    trim(trim((string)($c['zip'] ?? '')) . ' ' . trim((string)($c['city'] ?? ''))),
    trim((string)($c['country'] ?? '')),
])));
$addrShown = $addr !== '' ? $addr : '[Anschrift]';
?>

<div class="notice">
  <strong>Wichtig bei Marktplatz-Käufen:</strong> Das gesetzliche Widerrufsrecht besteht nur bei
  Verträgen zwischen einem Verbraucher und einem <em>Unternehmer</em>. Für Artikel, die auf der
  Produktseite als „<?= te('seller_private') ?>“ gekennzeichnet sind, besteht daher
  <strong>kein</strong> Widerrufsrecht. Diese Kennzeichnung sehen Sie vor dem Bezahlen, und Sie
  müssen sie im Bestellvorgang ausdrücklich bestätigen.
</div>

<h2>Widerrufsrecht</h2>
<p>
  Sie haben das Recht, binnen <?= (int)$wd ?> Tagen ohne Angabe von Gründen diesen Vertrag zu
  widerrufen.
</p>
<p>
  Die Widerrufsfrist beträgt <?= (int)$wd ?> Tage ab dem Tag, an dem Sie oder ein von Ihnen benannter
  Dritter, der nicht der Beförderer ist, die Waren in Besitz genommen haben bzw. hat.
</p>
<p>
  Im Falle eines Vertrags über mehrere Waren, die Sie im Rahmen einer einheitlichen Bestellung
  bestellt haben und die getrennt geliefert werden, beträgt die Widerrufsfrist <?= (int)$wd ?> Tage ab
  dem Tag, an dem Sie oder ein von Ihnen benannter Dritter, der nicht der Beförderer ist, die letzte
  Ware in Besitz genommen haben bzw. hat.
</p>
<p>
  Um Ihr Widerrufsrecht auszuüben, müssen Sie uns
</p>
<div class="doc__box">
  <?= h($co) ?><br>
  <?= h($addrShown) ?><br>
  <?= $email !== '' ? 'E-Mail: <a href="mailto:' . h($email) . '">' . h($email) . '</a>' : 'E-Mail: [E-Mail-Adresse]' ?>
</div>
<p>
  mittels einer eindeutigen Erklärung (z. B. ein mit der Post versandter Brief oder eine E-Mail) über
  Ihren Entschluss, diesen Vertrag zu widerrufen, informieren. Sie können dafür das beigefügte
  Muster-Widerrufsformular verwenden, das jedoch nicht vorgeschrieben ist.
</p>
<p>
  Betrifft der Widerruf einen Artikel eines gewerblichen Drittverkäufers, genügt die Erklärung
  gegenüber uns; wir sind zur Entgegennahme bevollmächtigt und leiten sie unverzüglich weiter.
</p>
<p>
  Zur Wahrung der Widerrufsfrist reicht es aus, dass Sie die Mitteilung über die Ausübung des
  Widerrufsrechts vor Ablauf der Widerrufsfrist absenden.
</p>

<h2>Folgen des Widerrufs</h2>
<p>
  Wenn Sie diesen Vertrag widerrufen, haben wir Ihnen alle Zahlungen, die wir von Ihnen erhalten
  haben, einschließlich der Lieferkosten (mit Ausnahme der zusätzlichen Kosten, die sich daraus
  ergeben, dass Sie eine andere Art der Lieferung als die von uns angebotene, günstigste
  Standardlieferung gewählt haben), unverzüglich und spätestens binnen vierzehn Tagen ab dem Tag
  zurückzuzahlen, an dem die Mitteilung über Ihren Widerruf dieses Vertrags bei uns eingegangen ist.
  Für diese Rückzahlung verwenden wir dasselbe Zahlungsmittel, das Sie bei der ursprünglichen
  Transaktion eingesetzt haben, es sei denn, mit Ihnen wurde ausdrücklich etwas anderes vereinbart;
  in keinem Fall werden Ihnen wegen dieser Rückzahlung Entgelte berechnet.
</p>
<p>
  Wir können die Rückzahlung verweigern, bis wir die Waren wieder zurückerhalten haben oder bis Sie
  den Nachweis erbracht haben, dass Sie die Waren zurückgesandt haben, je nachdem, welches der
  früherer Zeitpunkt ist.
</p>
<p>
  Sie haben die Waren unverzüglich und in jedem Fall spätestens binnen vierzehn Tagen ab dem Tag, an
  dem Sie uns über den Widerruf dieses Vertrags unterrichten, an uns oder an den auf dem
  Rücksendelabel genannten Verkäufer zurückzusenden oder zu übergeben. Die Frist ist gewahrt, wenn
  Sie die Waren vor Ablauf der Frist von vierzehn Tagen absenden.
</p>
<p>
  Wir tragen die Kosten der Rücksendung der Waren, wenn die Rücksendung aus Deutschland erfolgt.
  Bei Rücksendungen aus anderen Ländern tragen Sie die unmittelbaren Kosten der Rücksendung.
</p>
<p>
  Sie müssen für einen etwaigen Wertverlust der Waren nur aufkommen, wenn dieser Wertverlust auf
  einen zur Prüfung der Beschaffenheit, Eigenschaften und Funktionsweise der Waren nicht notwendigen
  Umgang mit ihnen zurückzuführen ist. Das Anprobieren eines Kleidungsstücks ist zulässig; das
  Tragen, Waschen oder Entfernen der Etiketten geht darüber hinaus.
</p>

<h2>Ausschluss des Widerrufsrechts</h2>
<p>Das Widerrufsrecht besteht nicht bzw. erlischt bei folgenden Verträgen:</p>
<ul>
  <li>Verträge mit Verkäufern, die keine Unternehmer sind (Privatverkäufer);</li>
  <li>Verträge zur Lieferung von Waren, die nach Kundenspezifikation angefertigt oder eindeutig auf
      persönliche Bedürfnisse zugeschnitten sind;</li>
  <li>Verträge zur Lieferung versiegelter Waren, die aus Gründen des Gesundheitsschutzes oder der
      Hygiene nicht zur Rückgabe geeignet sind, wenn die Versiegelung nach der Lieferung entfernt
      wurde (z. B. Ohrschmuck, Bademode ohne Hygienesiegel);</li>
  <li>Verträge, bei denen Sie als Unternehmer handeln (B2B).</li>
</ul>

<h2>Muster-Widerrufsformular</h2>
<div class="doc__box">
  <p><em>(Wenn Sie den Vertrag widerrufen wollen, dann füllen Sie bitte dieses Formular aus und senden
  Sie es zurück.)</em></p>
  <p>
    An<br>
    <?= h($co) ?><br>
    <?= h($addrShown) ?><br>
    <?= $email !== '' ? h($email) : '[E-Mail-Adresse]' ?>
  </p>
  <p>
    Hiermit widerrufe(n) ich/wir (*) den von mir/uns (*) abgeschlossenen Vertrag über den Kauf der
    folgenden Waren (*):
  </p>
  <p>
    ______________________________________________<br>
    ______________________________________________
  </p>
  <p>
    Bestellnummer: ____________________________<br>
    Bestellt am (*) / erhalten am (*): ____________________________<br>
    Name des/der Verbraucher(s): ____________________________<br>
    Anschrift des/der Verbraucher(s): ____________________________<br>
    ____________________________
  </p>
  <p>
    Unterschrift des/der Verbraucher(s) <em>(nur bei Mitteilung auf Papier)</em>: ____________________________<br>
    Datum: ____________________________
  </p>
  <p><em>(*) Unzutreffendes streichen.</em></p>
</div>

<p style="font-size:13px;color:var(--muted)">
  Über das gesetzliche Widerrufsrecht hinaus gewähren wir für eigene Ware ein freiwilliges
  Rückgaberecht — Einzelheiten unter
  <a href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a>.
</p>

<?php vr_doc_end(); ?>
