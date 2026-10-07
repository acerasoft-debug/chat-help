<?php
/**
 * Resi — italiano (traduzione di cortesia). Variabili: legal/rueckgabe.php.
 */
?>
<h2>In sintesi</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Venditore</th><th>Termine</th><th>Costi del reso</th></tr></thead>
  <tbody>
    <tr><td>Stock <?= h((string)vr_config('brand')) ?></td>
        <td><?= (int)$days ?> giorni (<?= (int)$wd ?> di legge + estensione volontaria)</td>
        <td>gratuito dalla Germania entro il termine di legge</td></tr>
    <tr><td>Rivenditore</td>
        <td><?= (int)$wd ?> giorni di recesso legale; molti rivenditori concedono di più</td>
        <td>gratuito dalla Germania entro il termine di legge</td></tr>
    <tr><td>Venditore privato</td>
        <td>nessun diritto legale di recesso</td>
        <td>ritiro solo in caso di difformità dalla descrizione</td></tr>
  </tbody>
</table></div>

<h2>Come effettuare il reso</h2>
<ol>
  <li>Scriva a
      <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-mail]</em>' ?>
      indicando il numero d'ordine e gli articoli da restituire.</li>
  <li>Entro un giorno lavorativo riceve un'etichetta di reso e l'indirizzo di restituzione del
      rispettivo venditore.</li>
  <li>Imballi la merce, possibilmente nella scatola originale, alleghi la bolla di consegna e
      consegni il pacco.</li>
  <li>Dopo il ricevimento e il controllo rimborsiamo sullo stesso mezzo di pagamento — entro 14
      giorni dal ricevimento della Sua dichiarazione di recesso, non appena la merce è da noi o Lei
      ha dimostrato la spedizione.</li>
</ol>
<p>
  Accettiamo anche resi senza preavviso; l'elaborazione richiede però più tempo, perché
  l'abbinamento avviene manualmente.
</p>

<h2>Condizioni del reso</h2>
<p>
  Provare è espressamente consentito — il diritto di reso serve proprio a questo. La preghiamo di
  restituire gli articoli non indossati, non lavati, non profumati e con tutte le etichette
  originali. Per una perdita di valore dovuta a un uso che va oltre, possiamo chiedere un
  indennizzo; lo calcoliamo in modo trasparente e La contattiamo prima.
</p>

<h2>Cosa non può essere restituito</h2>
<ul>
  <li>costumi da bagno e orecchini senza sigillo igienico integro;</li>
  <li>articoli adattati individualmente o realizzati su Sue indicazioni;</li>
  <li>articoli di venditori privati, se la merce corrisponde alla descrizione.</li>
</ul>

<h2>Cambio</h2>
<p>
  Un cambio diretto non è possibile, poiché la maggior parte degli articoli sono pezzi unici o
  taglie singole. Restituisca l'articolo e ordini nuovamente la taglia giusta — se ancora
  disponibile. Se Le interessa una taglia precisa ci scriva; Le diremo se è previsto un
  riassortimento.
</p>

<h2>Articolo difettoso o non conforme alla descrizione</h2>
<p>
  In questo caso non si applica il reso, ma la garanzia legale — con diritti più ampi per Lei. Ci
  contatti con delle foto; il reso è in tal caso sempre gratuito, anche per i venditori privati e
  anche dopo la scadenza del termine di reso, entro la prescrizione di legge.
</p>

<h2>Merce non originale</h2>
<p>
  Qualora un articolo risultasse non originale, rimborsiamo l'intero prezzo d'acquisto, spese di
  spedizione incluse, e ci facciamo carico del reso — indipendentemente dal venditore che lo ha
  offerto. Il venditore interessato viene rimosso dalla piattaforma.
</p>

<h2>Premium Outlet</h2>
<p>
  I prezzi ridotti non cambiano i Suoi diritti: per gli acquisti nel Vault valgono gli stessi
  termini di cui sopra. Resta solo l'eccezione per i venditori privati.
</p>

<p class="doc__related">
  Il testo legale con il modulo di recesso tipo:
  <a href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a>.
</p>
