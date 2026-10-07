<?php
/**
 * Metodi di pagamento — italiano (traduzione di cortesia). $mode: legal/zahlung.php.
 */
?>
<h2>Come funziona il pagamento</h2>
<p>
  Aggiunge gli articoli alla borsa, sceglie il Paese di consegna in cassa e conferma le condizioni
  generali e l'informativa sul recesso. Per pagare La reindirizziamo al nostro fornitore di servizi
  di pagamento Stripe. Lì inserisce i dati di pagamento e completa il pagamento; poi torna alla
  conferma d'ordine.
</p>
<p>
  <strong>I dati della Sua carta non raggiungono mai i nostri server.</strong> Da Stripe riceviamo
  soltanto l'esito del pagamento, l'importo, il metodo di pagamento in forma generica e i dati
  necessari per spedizione e fattura.
</p>

<h2>Metodi di pagamento disponibili</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Metodo di pagamento</th><th>Addebito</th><th>Nota</th></tr></thead>
  <tbody>
    <tr><td>Visa, Mastercard, American Express</td><td>immediato</td>
        <td>può essere richiesta la conferma 3-D Secure della Sua banca (SCA)</td></tr>
    <tr><td>Apple Pay</td><td>immediato</td><td>su dispositivi Apple in Safari</td></tr>
    <tr><td>Google Pay</td><td>immediato</td><td>in Chrome e su Android</td></tr>
    <tr><td>Klarna</td><td>a seconda dell'opzione scelta</td>
        <td>fattura o rate; la controparte del finanziamento è Klarna</td></tr>
    <tr><td>Addebito diretto SEPA</td><td>1–3 giorni lavorativi bancari</td>
        <td>spedizione dopo l'approvazione del pagamento</td></tr>
  </tbody>
</table></div>
<p class="doc__related">
  Quali metodi di pagamento vengono effettivamente mostrati dipende da Paese di consegna, importo e
  dispositivo — Stripe mostra solo ciò che è utilizzabile per il Suo ordine. Nessuno dei metodi
  offerti comporta costi aggiuntivi per Lei.
</p>

<?php if ($mode !== 'live'): ?>
  <div class="notice notice--demo">
    <strong>Questa installazione non è attualmente in modalità live.</strong>
    Non è possibile effettuare pagamenti reali
    (<?= $mode === 'test' ? 'modalità test Stripe attiva' : 'nessuna chiave Stripe configurata' ?>).
  </div>
<?php endif; ?>

<h2>Esigibilità</h2>
<p>
  Il prezzo d'acquisto è esigibile alla conclusione del contratto. Per i metodi di pagamento con
  regolamento differito riserviamo gli articoli e spediamo dopo l'approvazione del pagamento.
</p>

<h2>Valuta e imposte</h2>
<p>
  Tutti i prezzi sono indicati in euro e includono l'IVA di legge del
  <?= h((int)vr_config('vat_rate_bps', 1900) / 100) ?> %, qualora il rispettivo venditore sia
  soggetto a IVA. Per gli articoli di venditori privati non viene esposta IVA. Se la Sua banca regola
  in un'altra valuta, può applicare una commissione di conversione — su cui non abbiamo alcuna
  influenza.
</p>

<h2>Fattura</h2>
<p>
  Riceve la fattura con la merce o via e-mail. Per gli articoli di venditori terzi professionali la
  fattura è emessa dal rispettivo rivenditore; per la nostra merce da
  <?= h((string)(vr_config('company')['legal_name'] ?? '')) ?>. I venditori privati non emettono
  fatture con IVA esposta.
</p>

<h2>Rimborsi</h2>
<p>
  I rimborsi avvengono sempre sullo stesso mezzo di pagamento utilizzato. Con Klarna il rimborso
  passa dal Suo account Klarna, con SEPA sul conto addebitato. Avviamo l'elaborazione dopo il
  ricevimento e il controllo del reso; a seconda del metodo di pagamento possono trascorrere alcuni
  giorni lavorativi prima dell'accredito presso la Sua banca.
</p>

<h2>Pagamento non riuscito</h2>
<p>
  Se un pagamento viene rifiutato, non si conclude alcun contratto e nulla viene addebitato. La
  borsa resta salvata, così può riprovare o usare un altro metodo di pagamento. La causa più
  frequente è una conferma 3-D Secure non completata.
</p>

<h2>Sicurezza del pagamento</h2>
<p>
  La connessione è cifrata end-to-end (TLS). Stripe è certificato come fornitore di servizi di
  pagamento PCI DSS livello 1 e autorizzato in Europa come istituto di pagamento (Stripe Payments
  Europe, Limited, Dublino). Per la prevenzione delle frodi Stripe verifica le transazioni in modo
  automatizzato; non conserviamo numeri di carta.
</p>
<p class="doc__related">
  Dettagli sul trattamento dei dati: <a href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>.
</p>
