<?php
/**
 * Condizioni per i venditori — italiano (traduzione di cortesia; fa fede il testo tedesco).
 * Le aliquote di commissione provengono dalla configurazione tramite legal/verkaeufer.php,
 * così che il testo contrattuale e l'aliquota fatturata non possano mai divergere.
 */
?>
<nav class="doc__toc">
  <a href="#v1">1. Oggetto</a>
  <a href="#v2">2. Account venditore e registrazione</a>
  <a href="#v3">3. Professionale o privato</a>
  <a href="#v4">4. Inserzioni e verifica</a>
  <a href="#v5">5. Merce vietata</a>
  <a href="#v6">6. Commissione</a>
  <a href="#v7">7. Pagamento e liquidazione</a>
  <a href="#v8">8. Spedizione e resi</a>
  <a href="#v9">9. Premium Outlet</a>
  <a href="#v10">10. Obblighi ai sensi del Digital Services Act</a>
  <a href="#v11">11. Diritti sui contenuti</a>
  <a href="#v12">12. Sospensione e recesso</a>
  <a href="#v13">13. Responsabilità e manleva</a>
  <a href="#v14">14. Disposizioni finali</a>
</nav>

<h2 id="v1">1. Oggetto</h2>
<p>
  1.1 Le presenti condizioni regolano il rapporto tra <?= h($co) ?>, quale gestore del marketplace
  <?= h($brand) ?>, e le persone che offrono merce tramite la piattaforma («Venditori»).
</p>
<p>
  1.2 Il Gestore mette a disposizione lo spazio di vendita, l'elaborazione dei pagamenti tramite Stripe
  e la gestione degli ordini. Il contratto di compravendita sulla merce offerta si conclude
  esclusivamente tra il Venditore e l'acquirente; il Gestore non ne diviene parte.
</p>
<p>
  1.3 Il Gestore è autorizzato a ricevere i pagamenti degli acquirenti in nome del Venditore con
  effetto liberatorio e a ricevere e inoltrare per conto del Venditore le dichiarazioni degli
  acquirenti relative al contratto di compravendita (in particolare recesso e denunce di vizi).
</p>

<h2 id="v2">2. Account venditore e registrazione</h2>
<p>
  2.1 La registrazione avviene online. I dati devono essere veritieri, completi e aggiornati. Le
  modifiche — in particolare di ragione sociale, indirizzo, codice fiscale/partita IVA o tipo di
  venditore — devono essere aggiornate senza indugio.
</p>
<p>
  2.2 Le credenziali di accesso devono essere mantenute segrete. Il Venditore risponde delle azioni
  compiute tramite il proprio account nella misura della propria colpa.
</p>
<p>
  2.3 Prima dell'attivazione della prima inserzione deve essere completata la verifica presso Stripe
  (Stripe Connect). Senza verifica completata non è possibile alcuna liquidazione; le inserzioni
  restano in tal caso offline.
</p>

<h2 id="v3">3. Professionale o privato</h2>
<p>
  3.1 Al momento della registrazione il Venditore dichiara se vende come operatore professionale
  («Rivenditore») o come privato («Venditore privato»). Questa informazione è mostrata agli acquirenti
  nella pagina prodotto e durante il pagamento e determina quali diritti dei consumatori si applicano.
</p>
<p>
  3.2 Chi vende in modo sistematico, ripetuto e con intento di lucro agisce a titolo professionale —
  indipendentemente dalla propria autovalutazione. La corretta classificazione e tutti gli obblighi
  fiscali, commerciali e di diritto d'impresa sono a carico del Venditore.
</p>
<p>
  3.3 Il Gestore è autorizzato, previo avviso, a riclassificare un account come «Rivenditore» o a
  sospenderlo se l'attività di vendita effettiva ha natura professionale. Rilevano in particolare il
  numero di inserzioni, il fatturato e la regolarità.
</p>
<p>
  3.4 I Rivenditori sono tenuti a riconoscere ai consumatori il diritto di recesso legale, a emettere
  fatture regolari e ad adempiere alla garanzia legale di conformità.
</p>

<h2 id="v4">4. Inserzioni e verifica</h2>
<p>
  4.1 Le inserzioni devono essere esatte, complete e aggiornate: marca, nome del modello, taglia,
  condizioni, prezzo IVA inclusa e almeno una fotografia propria, non ritoccata, della merce
  effettivamente disponibile.
</p>
<p>
  4.2 Per la merce usata devono essere descritti i segni d'uso. Informazioni mancanti o abbellite sono
  a carico del Venditore.
</p>
<p>
  4.3 Ogni inserzione nuova o modificata viene verificata prima dell'attivazione. La verifica riguarda
  plausibilità, qualità delle immagini e prezzo; il Gestore può richiedere prove di provenienza
  (documenti d'acquisto). Non sussiste alcun diritto all'attivazione.
</p>
<p>
  4.4 Il Venditore conserva i documenti d'acquisto almeno per la durata dell'inserzione più due anni e
  li presenta su richiesta entro cinque giorni lavorativi.
</p>
<p>
  4.5 Il Venditore garantisce la disponibilità della merce offerta. Ripetute mancate consegne dopo una
  vendita autorizzano il Gestore a sospendere l'account.
</p>

<h2 id="v5">5. Merce vietata</h2>
<p>Non possono essere offerti, in particolare:</p>
<ul>
  <li>contraffazioni, repliche, «dupe» e merce con marcature rimosse o alterate;</li>
  <li>merce senza provenienza tracciabile o di origine illecita;</li>
  <li>merce immessa per la prima volta sul mercato al di fuori del SEE, salvo consenso del titolare del
      marchio alla rivendita nel SEE;</li>
  <li>campioni non destinati alla vendita («not for resale»), merce riservata al personale soggetta a
      divieto di rivendita;</li>
  <li>merce che viola le norme sulla sicurezza dei prodotti, sull'etichettatura o sull'etichettatura
      tessile;</li>
  <li>pellicce e pelli esotiche prive della documentazione richiesta (CITES).</li>
</ul>
<p>
  Le violazioni comportano la rimozione immediata dell'inserzione e, di regola, la chiusura
  dell'account venditore.
</p>

<h2 id="v6">6. Commissione</h2>
<p>6.1 Su ogni vendita intermediata dalla piattaforma viene applicata una commissione:</p>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Tipo di venditore</th><th>Commissione</th><th>Premium Outlet (Vault)</th></tr></thead>
  <tbody>
    <tr><td>Rivenditore</td><td><?= h($feeB) ?> % + <?= h($fixed) ?> per articolo venduto</td><td><?= h($feeV) ?> %</td></tr>
    <tr><td>Venditore privato</td><td><?= h($feeP) ?> % + <?= h($fixed) ?> per articolo venduto</td><td><?= h($feeV) ?> %</td></tr>
  </tbody>
</table></div>
<p>
  6.2 La base di calcolo è il prezzo di vendita lordo degli articoli del Venditore. Le spese di
  spedizione non rientrano nella base di calcolo e restano al Gestore, che sostiene le spese di
  spedizione verso il corriere.
</p>
<p>
  6.3 Non sono previsti costi di inserzione, di pubblicazione o canoni mensili.
</p>
<p>
  6.4 La commissione viene trattenuta automaticamente al pagamento da parte dell'acquirente. In caso
  di annullamento completo (recesso, risoluzione, mancata consegna) la commissione viene rimborsata,
  ad eccezione dell'importo fisso di cui al punto 6.1 qualora l'annullamento sia imputabile al
  Venditore.
</p>
<p>
  6.5 Le modifiche della commissione sono comunicate via e-mail con almeno 30 giorni di anticipo. Se
  il Venditore non si oppone prima della loro entrata in vigore, la nuova commissione si intende
  accettata; resta fermo il diritto di recesso di cui al punto 12.
</p>

<h2 id="v7">7. Pagamento e liquidazione</h2>
<p>
  7.1 L'elaborazione dei pagamenti avviene tramite Stripe. A tal fine il Venditore stipula un proprio
  contratto con Stripe (Stripe Connected Account Agreement) e ne accetta le condizioni.
</p>
<p>
  7.2 A seconda della composizione dell'ordine, il pagamento viene effettuato direttamente sul conto
  del Venditore (pagamento con inoltro) oppure viene prima incassato sul conto della piattaforma e poi
  trasferito al Venditore con bonifico separato. In entrambi i casi il Venditore riceve il prezzo di
  vendita lordo dei propri articoli al netto della commissione.
</p>
<p>
  7.3 La cadenza dei versamenti sul conto bancario segue le regole di Stripe. Il Gestore non detiene
  fondi dei clienti e non deve alcun interesse.
</p>
<p>
  7.4 Se la verifica Stripe non è ancora completata, la quota del Venditore resta sul conto della
  piattaforma fino al completamento. Se la verifica non viene completata entro 180 giorni, il Gestore
  può annullare gli ordini interessati e rimborsare gli acquirenti.
</p>
<p>
  7.5 Il Gestore può trattenere o compensare le liquidazioni nella misura in cui sussistano crediti
  fondati nei confronti del Venditore — in particolare per rimborsi agli acquirenti, chargeback o
  violazioni del punto 5. La trattenuta è motivata e limitata all'importo dei crediti.
</p>

<h2 id="v8">8. Spedizione e resi</h2>
<p>
  8.1 Il Venditore spedisce entro due giorni lavorativi dal ricevimento del pagamento, con
  assicurazione e tracciamento, e inserisce senza indugio i dati di spedizione.
</p>
<p>
  8.2 Il Venditore accetta i resi all'indirizzo da lui indicato. I Rivenditori rimborsano i recessi
  entro il termine; in mancanza, il Gestore è autorizzato a rimborsare l'acquirente e a compensare
  l'importo con le liquidazioni del Venditore.
</p>
<p>
  8.3 Nei confronti dei venditori privati non sussiste alcun diritto di recesso legale. Tuttavia, se
  la merce si discosta sostanzialmente dalla descrizione o non è autentica, il Venditore è tenuto a
  ritirarla e a rimborsare.
</p>

<h2 id="v9">9. Premium Outlet</h2>
<p>
  9.1 Il Venditore può mettere a disposizione merce per il Vault. Prezzo di apertura, prezzo minimo e
  schema dei gradini vengono concordati prima dell'apertura e non vengono più modificati in seguito.
</p>
<p>
  9.2 Il Venditore riconosce che la vendita può concludersi a qualsiasi prezzo compreso tra il prezzo
  di apertura e il prezzo minimo e che, una volta aperto il lotto, il ritiro della partecipazione non
  è possibile finché il lotto è in corso.
</p>
<p>
  9.3 Il prezzo minimo non viene mai superato al ribasso. Se un lotto non viene venduto entro la
  scadenza, viene chiuso e può essere nuovamente offerto nel modo ordinario.
</p>

<h2 id="v10">10. Obblighi ai sensi del Digital Services Act</h2>
<p>
  10.1 I Venditori professionali forniscono le informazioni richieste dall'art. 30 DSA: nome,
  indirizzo, numero di telefono, indirizzo e-mail, numero di iscrizione al registro delle imprese o
  identificativo analogo e, se disponibile, partita IVA. Il Gestore verifica tali informazioni con
  mezzi ragionevoli e può mettere offline l'inserzione fino a chiarimento.
</p>
<p>
  10.2 Il Venditore garantisce che le proprie inserzioni rispettano le norme sulla sicurezza dei
  prodotti e sull'etichettatura e di possedere le autorizzazioni necessarie.
</p>
<p>
  10.3 Le segnalazioni di contenuti illegali sono trattate ai sensi dell'art. 16 DSA. I Venditori
  interessati sono informati delle rimozioni con motivazione e possono opporsi
  (<a href="<?= h(vr_url('legal/streitbeilegung.php')) ?>"><?= te('legal_disputes') ?></a>).
</p>

<h2 id="v11">11. Diritti sui contenuti</h2>
<p>
  11.1 Il Venditore concede al Gestore un diritto semplice, territorialmente illimitato e gratuito di
  utilizzare, modificare (ritaglio, correzione del colore, ridimensionamento) e riprodurre i testi e le
  immagini pubblicati, ai fini della gestione e della promozione della piattaforma.
</p>
<p>
  11.2 Il diritto d'uso permane oltre la fine dell'inserzione nella misura in cui serve a documentare
  le vendite concluse.
</p>
<p>
  11.3 Il Venditore garantisce di detenere tutti i diritti necessari sui contenuti pubblicati e di non
  violare diritti di terzi.
</p>

<h2 id="v12">12. Sospensione e recesso</h2>
<p>
  12.1 Ciascuna parte può recedere dal rapporto di vendita in qualsiasi momento, in forma testuale,
  con preavviso di 14 giorni. Gli ordini in corso devono comunque essere portati a termine.
</p>
<p>
  12.2 Il Gestore può rimuovere immediatamente le inserzioni e sospendere l'account in caso di
  violazione del punto 5, informazioni false sullo status di venditore, ripetute mancate consegne o
  fondato sospetto di contraffazione. La sospensione è motivata.
</p>
<p>
  12.3 Dopo il recesso, le liquidazioni dovute vengono disposte alla scadenza dei termini di reso e di
  chargeback, al più tardi 90 giorni dopo l'ultimo ordine.
</p>

<h2 id="v13">13. Responsabilità e manleva</h2>
<p>
  13.1 Il Gestore risponde illimitatamente per dolo e colpa grave, per lesioni alla vita, al corpo o
  alla salute e qualora sia stata prestata una garanzia. In caso di violazione per colpa lieve di
  obblighi contrattuali essenziali, la responsabilità è limitata al danno prevedibile e tipico del
  contratto; per il resto è esclusa.
</p>
<p>
  13.2 Il Venditore manleva il Gestore dalle pretese di terzi fondate su una violazione delle presenti
  condizioni — in particolare in materia di marchi, diritto d'autore o concorrenza e per violazioni
  della tutela dei consumatori. La manleva comprende i costi ragionevoli di difesa legale.
</p>
<p>
  13.3 Non viene prestata alcuna garanzia di fatturato o di successo. Visibilità, posizionamento e
  ordinamento delle inserzioni sono stabiliti dal Gestore.
</p>

<h2 id="v14">14. Disposizioni finali</h2>
<p>
  14.1 Si applica il diritto tedesco. Se il Venditore è un operatore professionale, il foro
  competente è quello della sede del Gestore, nei limiti consentiti dalla legge.
</p>
<p>
  14.2 Le modifiche delle presenti condizioni sono comunicate via e-mail con almeno 30 giorni di
  anticipo. Versione in vigore: <?= h(VR_TERMS_VERSION) ?>.
</p>
<p>
  14.3 Contatto per tutte le questioni relative ai venditori:
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-mail]</em>' ?>.
</p>

<div class="doc__box">
  <strong>Nota su questa traduzione</strong>
  <p style="margin-top:8px">
    Questo testo italiano è una traduzione di cortesia delle Condizioni per i venditori tedesche, le
    uniche giuridicamente vincolanti e che accettate al momento della registrazione. In caso di
    divergenze prevale la versione tedesca. Non costituisce consulenza legale.
  </p>
</div>
