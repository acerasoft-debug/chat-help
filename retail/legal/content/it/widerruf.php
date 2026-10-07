<?php
/**
 * Informativa sul recesso + modulo tipo — italiano (traduzione di cortesia).
 * Segue il modello ufficiale della direttiva 2011/83/UE (allegato I), come
 * l'originale tedesco segue il BGB. Variabili: legal/widerruf.php.
 */
?>
<div class="notice">
  <strong>Importante per gli acquisti sul marketplace:</strong> il diritto legale di recesso sussiste
  solo per i contratti tra un consumatore e un <em>professionista</em>. Per gli articoli
  contrassegnati come «<?= te('seller_private') ?>» sulla pagina del prodotto non sussiste quindi
  <strong>alcun</strong> diritto di recesso. Vede questa indicazione prima di pagare e deve
  confermarla espressamente durante l'ordine.
</div>

<h2>Diritto di recesso</h2>
<p>
  Lei ha il diritto di recedere dal presente contratto, senza indicarne le ragioni, entro
  <?= (int)$wd ?> giorni.
</p>
<p>
  Il periodo di recesso scade dopo <?= (int)$wd ?> giorni dal giorno in cui Lei o un terzo, diverso
  dal vettore e da Lei designato, acquisisce il possesso fisico dei beni.
</p>
<p>
  Nel caso di un contratto relativo a beni multipli ordinati con un solo ordine e consegnati
  separatamente, il periodo di recesso scade dopo <?= (int)$wd ?> giorni dal giorno in cui Lei o un
  terzo, diverso dal vettore e da Lei designato, acquisisce il possesso fisico dell'ultimo bene.
</p>
<p>
  Per esercitare il diritto di recesso, Lei è tenuto a informare
</p>
<div class="doc__box">
  <?= h($co) ?><br>
  <?= h($addrShown) ?><br>
  <?= $email !== '' ? 'E-mail: <a href="mailto:' . h($email) . '">' . h($email) . '</a>' : 'E-mail: [indirizzo e-mail]' ?>
</div>
<p>
  della Sua decisione di recedere dal presente contratto tramite una dichiarazione esplicita (ad
  esempio lettera inviata per posta o posta elettronica). A tal fine può utilizzare il modulo tipo
  di recesso allegato, ma non è obbligatorio.
</p>
<p>
  Se il recesso riguarda un articolo di un venditore terzo professionale, è sufficiente la
  dichiarazione a noi; siamo autorizzati a riceverla e la inoltriamo senza indugio.
</p>
<p>
  Per rispettare il termine di recesso, è sufficiente che Lei invii la comunicazione relativa
  all'esercizio del diritto di recesso prima della scadenza del periodo di recesso.
</p>

<h2>Effetti del recesso</h2>
<p>
  Se Lei recede dal presente contratto, Le saranno rimborsati tutti i pagamenti che ha effettuato a
  nostro favore, compresi i costi di consegna (ad eccezione dei costi supplementari derivanti dalla
  Sua eventuale scelta di un tipo di consegna diverso dal tipo meno costoso di consegna standard da
  noi offerto), senza indebito ritardo e in ogni caso non oltre quattordici giorni dal giorno in cui
  siamo informati della Sua decisione di recedere dal presente contratto. Detti rimborsi saranno
  effettuati utilizzando lo stesso mezzo di pagamento da Lei usato per la transazione iniziale,
  salvo che Lei non abbia espressamente convenuto altrimenti; in ogni caso, non dovrà sostenere
  alcun costo quale conseguenza di tale rimborso.
</p>
<p>
  Il rimborso può essere sospeso fino al ricevimento dei beni oppure fino all'avvenuta dimostrazione
  da parte Sua di aver rispedito i beni, se precedente.
</p>
<p>
  È pregato di rispedire i beni o di consegnarli a noi o al venditore indicato sull'etichetta di
  reso, senza indebiti ritardi e in ogni caso entro quattordici giorni dal giorno in cui ci ha
  comunicato il Suo recesso dal presente contratto. Il termine è rispettato se Lei rispedisce i beni
  prima della scadenza del periodo di quattordici giorni.
</p>
<p>
  I costi della restituzione dei beni sono a nostro carico se il reso avviene dalla Germania. Per i
  resi da altri Paesi, i costi diretti della restituzione sono a Suo carico.
</p>
<p>
  Lei è responsabile solo della diminuzione del valore dei beni risultante da una manipolazione del
  bene diversa da quella necessaria per stabilire la natura, le caratteristiche e il funzionamento
  dei beni. Provare un capo è consentito; indossarlo, lavarlo o rimuovere le etichette va oltre.
</p>

<h2>Esclusione del diritto di recesso</h2>
<p>Il diritto di recesso non sussiste o si estingue per i seguenti contratti:</p>
<ul>
  <li>contratti con venditori che non sono professionisti (venditori privati);</li>
  <li>contratti di fornitura di beni confezionati su misura o chiaramente personalizzati;</li>
  <li>contratti di fornitura di beni sigillati che non si prestano a essere restituiti per motivi
      igienici o connessi alla protezione della salute e che sono stati aperti dopo la consegna (ad
      es. orecchini, costumi da bagno senza sigillo igienico);</li>
  <li>contratti in cui Lei agisce come professionista (B2B).</li>
</ul>

<h2>Modulo di recesso tipo</h2>
<div class="doc__box">
  <p><em>(Compilare e restituire il presente modulo solo se si desidera recedere dal
  contratto.)</em></p>
  <p>
    Destinatario<br>
    <?= h($co) ?><br>
    <?= h($addrShown) ?><br>
    <?= $email !== '' ? h($email) : '[indirizzo e-mail]' ?>
  </p>
  <p>
    Con la presente io/noi (*) notifichiamo il recesso dal mio/nostro (*) contratto di vendita dei
    seguenti beni (*):
  </p>
  <p>
    ______________________________________________<br>
    ______________________________________________
  </p>
  <p>
    Numero d'ordine: ____________________________<br>
    Ordinato il (*) / ricevuto il (*): ____________________________<br>
    Nome del/dei consumatore/i: ____________________________<br>
    Indirizzo del/dei consumatore/i: ____________________________<br>
    ____________________________
  </p>
  <p>
    Firma del/dei consumatore/i <em>(solo se il presente modulo è notificato in versione
    cartacea)</em>: ____________________________<br>
    Data: ____________________________
  </p>
  <p><em>(*) Cancellare la dicitura inutile.</em></p>
</div>

<p class="doc__related">
  Oltre al diritto legale di recesso concediamo un diritto di reso volontario per la merce di nostra
  proprietà — dettagli in
  <a href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a>.
</p>
