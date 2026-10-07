<?php
/**
 * Spedizione e consegna — italiano (traduzione di cortesia).
 * $ship e $countries da legal/versand.php; le etichette sono qui.
 */
$zones = [
    'de'    => 'Germania',
    'eu'    => 'Unione europea',
    'ch'    => 'Svizzera, Liechtenstein, Norvegia, Regno Unito',
    'world' => 'Altre destinazioni',
];
?>
<h2>Spese di spedizione e tempi di consegna</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead>
    <tr><th>Destinazione</th><th>Spedizione</th><th>Gratuita da</th><th>Tempi</th></tr>
  </thead>
  <tbody>
  <?php foreach ($zones as $key => $label):
      $row = (array)($ship[$key] ?? []); ?>
    <tr>
      <td><?= h($label) ?></td>
      <td><?= h(vr_money((int)($row['cents'] ?? 0))) ?></td>
      <td><?= (int)($row['free_over'] ?? 0) > 0 ? h(vr_money((int)$row['free_over'])) : '—' ?></td>
      <td><?= h((string)($row['days'] ?? '')) ?> giorni lavorativi</td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<p>
  Tutti gli importi sono prezzi finali IVA inclusa. Le spese di spedizione applicabili al Suo ordine
  sono mostrate nella borsa non appena ha scelto il Paese di consegna e sono indicate di nuovo con
  importo esatto durante l'ordine, prima dell'invio.
</p>

<h3>Paesi UE serviti</h3>
<p class="doc__list">
  <?= h(implode(' · ', vr_country_options($countries['eu']))) ?>
</p>

<h3>Altre destinazioni</h3>
<p class="doc__list">
  <?= h(implode(' · ', vr_country_options($countries['world']))) ?>
</p>
<p>
  Se il Suo Paese non è elencato, ci scriva — molto si può risolvere caso per caso.
</p>

<h2>Inizio della spedizione</h2>
<p>
  I tempi decorrono dall'approvazione del pagamento. Con carta, Apple Pay e Google Pay è di norma
  immediata; con addebito SEPA e Klarna possono aggiungersi da uno a tre giorni lavorativi bancari.
  Gli ordini approvati entro le 13:00 partono di norma lo stesso giorno lavorativo.
</p>

<h2>Più venditori, più pacchi</h2>
<p>
  <?= h((string)vr_config('brand')) ?> è un marketplace. Se l'ordine contiene articoli di venditori
  diversi, ogni venditore spedisce separatamente. Riceverà quindi più pacchi e più link di
  tracciamento — ma paga una sola volta e le spese di spedizione sono addebitate una sola volta.
</p>

<h2>Tracciamento</h2>
<p>
  Ogni spedizione è assicurata e inviata con numero di tracciamento. Riceve il link via e-mail non
  appena il pacco è stato consegnato al corriere. Può inoltre consultare lo stato attuale in
  qualsiasi momento in <a href="<?= h(vr_url('order.php')) ?>"><?= te('order_status') ?></a> con
  numero d'ordine e indirizzo e-mail.
</p>

<h2>Punti di ritiro e indirizzo di consegna diverso</h2>
<p>
  In Germania consegniamo alle Packstation DHL; indichi la Packstation con il Suo numero postale
  come indirizzo di consegna. Per le spedizioni internazionali è necessario un indirizzo stradale.
</p>

<h2>Dogana e oneri all'importazione</h2>
<p>
  All'interno dell'UE non si applicano dazi né oneri all'importazione. Per consegne in Svizzera,
  Norvegia, Regno Unito o fuori dall'Europa possono applicarsi IVA all'importazione, dazi doganali e
  spese di gestione del vettore. Sono a carico del destinatario e non fanno parte del prezzo pagato
  a noi.
</p>

<h2>Spedizioni non recapitate</h2>
<p>
  Se un pacco viene restituito al venditore come non recapitabile, concordiamo con Lei una nuova
  spedizione. Nuove spese di spedizione si applicano se il mancato recapito è dovuto a un indirizzo
  di consegna incompleto o errato.
</p>

<h2>Danni da trasporto</h2>
<p>
  Se un pacco arriva visibilmente danneggiato, lo accetti pure, documenti il danno con foto e ci
  contatti entro 14 giorni. Gestiamo questi casi indipendentemente dal diritto di recesso — anche
  per i venditori privati. I Suoi diritti di legge non ne sono limitati.
</p>

<p class="doc__related">
  Vedi anche <a href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a> e
  <a href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a>.
</p>
