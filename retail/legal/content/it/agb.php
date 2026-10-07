<?php
/**
 * Condizioni generali di vendita — italiano (traduzione di cortesia; fa fede il
 * testo tedesco). Variabili: legal/agb.php. La numerazione segue l'originale
 * paragrafo per paragrafo, così i rinvii («clausola 10.3») restano esatti.
 */
?>
<nav class="doc__toc">
  <a href="#s1">1. Ambito di applicazione e parti contraenti</a>
  <a href="#s2">2. Ruolo della piattaforma</a>
  <a href="#s3">3. Conclusione del contratto</a>
  <a href="#s4">4. Prezzi e spese di spedizione</a>
  <a href="#s5">5. Pagamento</a>
  <a href="#s6">6. Consegna</a>
  <a href="#s7">7. Riserva di proprietà</a>
  <a href="#s8">8. Recesso e reso volontario</a>
  <a href="#s9">9. Garanzia legale di conformità</a>
  <a href="#s10">10. Acquisti da venditori privati</a>
  <a href="#s11">11. Premium Outlet (Vault)</a>
  <a href="#s12">12. Autenticità e provenienza</a>
  <a href="#s13">13. Responsabilità</a>
  <a href="#s14">14. Buoni sconto</a>
  <a href="#s15">15. Protezione dei dati</a>
  <a href="#s16">16. Disposizioni finali</a>
</nav>

<h2 id="s1">1. Ambito di applicazione e parti contraenti</h2>
<p>
  1.1 Le presenti condizioni generali di vendita si applicano a tutti gli ordini effettuati tramite
  <?= h($brand) ?> (<a href="<?= h(vr_origin()) ?>"><?= h(vr_origin()) ?></a>).
  Il gestore della piattaforma è <?= h($co) ?> (di seguito «Gestore», «noi»).
</p>
<p>
  1.2 Sulla piattaforma sono presenti tre tipi di offerte, ciascuna contrassegnata sulla pagina del
  prodotto:
</p>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Indicazione</th><th>Venditore</th><th>Controparte del contratto di vendita</th></tr></thead>
  <tbody>
    <tr><td>Stock <?= h($brand) ?></td><td>il Gestore stesso</td><td><?= h($co) ?></td></tr>
    <tr><td>Rivenditore</td><td>venditore terzo professionale</td><td>il rispettivo rivenditore</td></tr>
    <tr><td>Venditore privato</td><td>persona privata</td><td>la rispettiva persona privata</td></tr>
  </tbody>
</table></div>
<p>
  1.3 Per le offerte di venditori terzi il contratto di vendita si conclude esclusivamente tra Lei e
  il rispettivo venditore. Il Gestore non diventa parte del contratto di vendita. Per l'utilizzo
  della piattaforma in sé — gestione del pagamento, riepilogo ordini, intermediazione — le presenti
  condizioni valgono tra Lei e il Gestore.
</p>
<p>
  1.4 È consumatore ogni persona fisica che conclude un negozio giuridico per scopi prevalentemente
  estranei alla propria attività commerciale o professionale (§ 13 BGB, codice civile tedesco).
  Condizioni divergenti del cliente non diventano parte del contratto, salvo nostra espressa
  accettazione in forma testuale.
</p>

<h2 id="s2">2. Ruolo della piattaforma</h2>
<p>
  2.1 Il Gestore mette a disposizione l'infrastruttura tecnica, verifica plausibilità e prove di
  provenienza delle offerte di venditori terzi prima della pubblicazione, gestisce il pagamento
  tramite il fornitore di servizi di pagamento Stripe e trasferisce al venditore la sua quota al
  netto della commissione.
</p>
<p>
  2.2 Il Gestore è autorizzato dai venditori terzi a ricevere i pagamenti dell'acquirente con
  effetto liberatorio. Il Suo obbligo di pagamento verso il venditore è adempiuto con il buon fine
  del pagamento tramite la piattaforma.
</p>
<p>
  2.3 Le dichiarazioni relative al contratto di vendita — in particolare recesso, denuncia di vizi e
  risoluzione — possono essere validamente indirizzate a
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '[e-mail]' ?>.
  Le inoltriamo senza indugio al venditore interessato e assistiamo nella gestione.
</p>

<h2 id="s3">3. Conclusione del contratto</h2>
<p>
  3.1 La presentazione dei prodotti sulla piattaforma non costituisce un'offerta giuridicamente
  vincolante, bensì un invito a ordinare.
</p>
<p>
  3.2 Cliccando sul pulsante d'ordine («<?= te('checkout_go') ?>» e successivo pagamento presso
  Stripe) Lei presenta un'offerta vincolante di acquisto degli articoli nella Sua borsa. Prima può
  verificare e correggere i dati inseriti nella pagina di cassa.
</p>
<p>
  3.3 Confermiamo senza indugio la ricezione dell'ordine via e-mail. Tale conferma di ricezione non
  costituisce ancora accettazione. Il contratto di vendita si conclude quando noi o il venditore
  dichiariamo l'accettazione o spediamo la merce — al più tardi con la conferma d'ordine, se questa
  dichiara espressamente l'accettazione.
</p>
<p>
  3.4 Se il contratto non si perfeziona, ad esempio perché l'articolo non è più disponibile dopo
  l'ordine, La informiamo senza indugio e rimborsiamo integralmente i pagamenti già effettuati.
</p>
<p>
  3.5 Il testo del contratto viene memorizzato e Le viene trasmesso con la conferma d'ordine in
  forma testuale (e-mail), incluse le presenti condizioni e l'informativa sul recesso.
</p>

<h2 id="s4">4. Prezzi e spese di spedizione</h2>
<p>
  4.1 Tutti i prezzi indicati sono prezzi finali in euro e includono l'IVA di legge, attualmente
  pari al <?= h($vat) ?> %, qualora il rispettivo venditore sia soggetto a IVA. Per i venditori
  privati non viene esposta IVA (§ 19 UStG, legge tedesca sull'IVA, ovvero vendita da parte di non
  imprenditore).
</p>
<p>
  4.2 Ai prezzi degli articoli si aggiungono le spese di spedizione. Queste sono indicate
  separatamente e con importo esatto nella pagina di cassa prima dell'invio dell'ordine. Dettagli in
  <a href="<?= h(vr_url('legal/versand.php')) ?>"><?= te('legal_shipping') ?></a>.
</p>
<p>
  4.3 Se l'ordine contiene articoli di più venditori, le spese di spedizione vengono addebitate una
  sola volta; le spedizioni possono arrivare separatamente.
</p>
<p>
  4.4 Per consegne in Paesi extra-UE possono applicarsi inoltre dazi doganali, IVA all'importazione e
  spese di gestione, a carico del destinatario.
</p>

<h2 id="s5">5. Pagamento</h2>
<p>
  5.1 Il pagamento avviene tramite il fornitore di servizi di pagamento Stripe (Stripe Payments
  Europe, Limited, Dublino, Irlanda). I metodi di pagamento disponibili sono mostrati durante
  l'ordine; una panoramica è disponibile in
  <a href="<?= h(vr_url('legal/zahlung.php')) ?>"><?= te('legal_payment') ?></a>.
</p>
<p>
  5.2 Il prezzo d'acquisto è esigibile alla conclusione del contratto. Per i metodi di pagamento
  con regolamento differito (ad es. addebito diretto SEPA, Klarna) spediamo dopo l'approvazione del
  pagamento da parte del fornitore di servizi di pagamento.
</p>
<p>
  5.3 I dati di pagamento, in particolare i dati della carta, sono trattati esclusivamente dal
  fornitore di servizi di pagamento. Il Gestore non riceve né conserva dati completi della carta.
</p>
<p>
  5.4 In caso di storni a Lei imputabili, siamo legittimati ad addebitare i costi che ne derivano,
  qualora Lei abbia causato lo storno con colpa.
</p>

<h2 id="s6">6. Consegna</h2>
<p>
  6.1 La consegna avviene all'indirizzo di consegna da Lei indicato. Tempi e aree di consegna sono
  indicati in <a href="<?= h(vr_url('legal/versand.php')) ?>"><?= te('legal_shipping') ?></a> e
  decorrono dall'approvazione del pagamento.
</p>
<p>
  6.2 Gli articoli di venditori terzi sono spediti dal rispettivo venditore. Riceverà un
  tracciamento separato per ogni spedizione.
</p>
<p>
  6.3 Se in via eccezionale un articolo non è consegnabile sebbene fosse indicato come disponibile
  sulla piattaforma, La informiamo senza indugio e rimborsiamo integralmente l'importo pagato. Non
  sussiste alcun diritto alla consegna successiva di un articolo analogo, trattandosi spesso di pezzi
  unici.
</p>
<p>
  6.4 Per i consumatori il rischio di perimento e deterioramento fortuiti passa solo con la consegna
  della merce, anche se la spedizione è affidata a un vettore (§ 475 comma 2 BGB).
</p>

<h2 id="s7">7. Riserva di proprietà</h2>
<p>
  La merce resta di proprietà del rispettivo venditore fino al pagamento integrale.
</p>

<h2 id="s8">8. Recesso e reso volontario</h2>
<p>
  8.1 Ai consumatori spetta, per i contratti con venditori professionali, un diritto legale di
  recesso di <?= (int)$wd ?> giorni. L'informativa completa con il modulo di recesso tipo è
  disponibile in <a href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a>.
</p>
<p>
  8.2 Oltre al diritto legale di recesso, per la merce di nostra proprietà concediamo un diritto di
  reso volontario di <?= (int)$days ?> giorni dal ricevimento. Condizione: l'articolo è non
  indossato, integro e con tutte le etichette originali. Il diritto di reso volontario non limita i
  Suoi diritti di legge. I costi del reso nell'ambito dell'estensione volontaria sono a carico
  dell'acquirente; dettagli in
  <a href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a>.
</p>
<p>
  8.3 Per gli acquisti da venditori privati non sussiste alcun diritto legale di recesso (v. clausola
  10).
</p>

<h2 id="s9">9. Garanzia legale di conformità</h2>
<p>
  9.1 Per i venditori professionali si applica la garanzia legale ai sensi dei §§ 434 e seguenti
  BGB. Per i consumatori il termine di prescrizione è di due anni dal ricevimento della merce.
</p>
<p>
  9.2 Per la merce usata il termine di prescrizione verso i consumatori può essere ridotto a un anno
  se ciò è stato concordato espressamente e separatamente prima della conclusione del contratto.
  Un'eventuale indicazione in tal senso è riportata sulla pagina del prodotto.
</p>
<p>
  9.3 I segni d'uso indicati nella descrizione dell'articolo non costituiscono un difetto. Le
  differenze nella resa dei colori dovute allo schermo non costituiscono un difetto.
</p>
<p>
  9.4 La preghiamo di segnalarci i danni da trasporto entro 14 giorni con foto. Gestiamo questi casi
  indipendentemente dalla questione del diritto di recesso, anche per i venditori privati.
</p>

<h2 id="s10">10. Acquisti da venditori privati</h2>
<p>
  10.1 Le offerte di persone private sono contrassegnate come «<?= te('seller_private') ?>» sulla
  pagina del prodotto, nella borsa e durante l'ordine. Prima di completare l'ordine deve confermare
  espressamente le particolari conseguenze.
</p>
<p>
  10.2 Poiché il venditore non è un imprenditore, non sussiste alcun diritto legale di recesso. La
  garanzia legale può essere validamente esclusa o limitata dal venditore privato; tale esclusione
  non vale in caso di dolo o dichiarazioni intenzionalmente false.
</p>
<p>
  10.3 Indipendentemente da ciò: se la merce consegnata si discosta sostanzialmente dalla
  descrizione o non è originale, rimborsiamo l'intero prezzo d'acquisto, spese di spedizione
  incluse. In questi casi tratteniamo o richiediamo la restituzione della quota del venditore.
</p>
<p>
  10.4 I venditori privati che di fatto vendono in modo pianificato, ripetuto e a scopo di lucro
  agiscono professionalmente. Se lo accertiamo, riclassifichiamo o sospendiamo l'account; in tal
  caso ai contratti già conclusi si applicano i diritti verso gli imprenditori.
</p>

<h2 id="s11">11. Premium Outlet (Vault)</h2>
<p>
  11.1 Nell'area «Premium Outlet» (Vault) ogni pezzo è offerto come lotto singolo con un prezzo di
  apertura, un prezzo minimo e un piano di riduzione pubblicato. Il prezzo scende in
  <?= (int)$steps ?> scatti uguali, uno ogni <?= (int)$hours ?> ore, fino al prezzo minimo.
</p>
<p>
  11.2 Il piano viene fissato all'apertura del lotto e non viene più modificato. Il prezzo non può
  salire. Il calcolo avviene lato server in base al piano ed è identico per tutti i visitatori; non
  esiste una formazione del prezzo personalizzata.
</p>
<p>
  11.3 Fa fede il prezzo mostrato al momento dell'aggiunta alla borsa, che Le viene riservato per la
  durata del processo d'ordine (20 minuti). Allo scadere della prenotazione il lotto viene
  nuovamente liberato.
</p>
<p>
  11.4 Ogni lotto esiste una sola volta. Termina con il primo acquisto efficace. Non sussiste alcun
  diritto ad acquistare un lotto a uno scatto successivo e inferiore.
</p>
<p>
  11.5 In caso di riduzioni di prezzo indichiamo, ai sensi del § 11 PAngV (regolamento tedesco
  sull'indicazione dei prezzi), il prezzo più basso degli ultimi 30 giorni. Poiché il prezzo Vault
  può solo scendere, si tratta del prezzo in vigore immediatamente prima dello scatto attuale. Nel
  primo scatto non vi è riduzione di prezzo e non viene pubblicizzato alcun prezzo di riferimento.
</p>
<p>
  11.6 I Suoi diritti di legge, in particolare recesso e garanzia legale, valgono invariati nel
  Vault. La clausola 10 resta applicabile ai venditori privati.
</p>
<p>
  11.7 I membri con iscrizione e-mail confermata accedono ai nuovi lotti prima dell'apertura
  generale. L'iscrizione è gratuita e revocabile in qualsiasi momento; non sussiste alcun diritto
  all'accesso anticipato.
</p>
<p>
  11.8 Un avviso di prezzo e il salvataggio di un pezzo tra i preferiti <strong>non</strong> lo
  riservano e non costituiscono alcun diritto di prelazione. La notifica viene inviata una sola
  volta e senza garanzia di recapito o tempistica; è determinante solo la disponibilità al momento
  dell'ordine.
</p>

<h2 id="s12">12. Autenticità e provenienza</h2>
<p>
  12.1 Viene offerta esclusivamente merce originale. I venditori sono tenuti a conservare le
  ricevute d'acquisto della loro merce e a esibircele su richiesta.
</p>
<p>
  12.2 Se dopo l'acquisto risulta che un articolo non è originale, rimborsiamo l'intero prezzo
  d'acquisto più le spese di spedizione e ci facciamo carico dei costi del reso. Il diritto sussiste
  indipendentemente dal tipo di venditore.
</p>
<p>
  12.3 I diritti di cui alla clausola 12.2 presuppongono che Lei ci renda accessibili la merce e la
  Sua contestazione entro 30 giorni dal ricevimento e ci consenta una verifica.
</p>

<h2 id="s13">13. Responsabilità</h2>
<p>
  13.1 Rispondiamo senza limitazioni per dolo e colpa grave, per lesioni alla vita, al corpo e alla
  salute, ai sensi della legge tedesca sulla responsabilità per danno da prodotti e nei limiti di
  una garanzia da noi assunta.
</p>
<p>
  13.2 In caso di violazione per colpa lieve di un obbligo contrattuale essenziale, la
  responsabilità è limitata al danno prevedibile e tipico del contratto. Per il resto la
  responsabilità è esclusa.
</p>
<p>
  13.3 Non rispondiamo delle violazioni del contratto di vendita da parte di venditori terzi; al
  riguardo la nostra responsabilità è disciplinata dalle norme sui servizi di hosting (art. 6
  Digital Services Act). Le clausole 10.3 e 12.2 restano impregiudicate.
</p>
<p>
  13.4 Non si garantisce la disponibilità ininterrotta della piattaforma.
</p>

<h2 id="s14">14. Buoni sconto</h2>
<p>
  14.1 I buoni promozionali (buoni non acquistati ma emessi nell'ambito di un'iniziativa
  pubblicitaria) sono utilizzabili solo nel periodo indicato e una sola volta. Dopo la scadenza
  l'utilizzo è escluso; non è prevista alcuna proroga.
</p>
<p>
  14.2 Il valore del buono viene detratto dal valore della merce, non dalle spese di spedizione. È
  escluso il pagamento in contanti, la corresponsione di interessi o l'accredito di eventuali
  residui.
</p>
<p>
  14.3 Se un buono è legato a un indirizzo e-mail o è espressamente contrassegnato come buono di
  benvenuto o per il primo ordine, può utilizzarlo solo il titolare di tale indirizzo e solo per il
  primo ordine pagato. Sono esclusi la cessione a terzi e la rivendita.
</p>
<p>
  14.4 Più buoni non sono cumulabili, salvo diversa indicazione nelle condizioni del rispettivo
  buono. Un valore minimo d'ordine si applica se indicato con il buono; fa fede il valore della
  merce al netto delle spese di spedizione.
</p>
<p>
  14.5 Se recede da un ordine in tutto o in parte, rimborsiamo l'importo effettivamente pagato. Un
  buono promozionale utilizzato non viene riattivato; non sussiste alcun diritto all'emissione di un
  nuovo buono.
</p>
<p>
  14.6 In caso di fondato sospetto di utilizzo abusivo — in particolare più account creati per
  l'uso ripetuto di buoni per il primo ordine — possiamo bloccare singoli buoni.
</p>

<h2 id="s15">15. Protezione dei dati</h2>
<p>
  Le informazioni sul trattamento dei Suoi dati personali sono disponibili nell'
  <a href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>.
  Per l'evasione dell'ordine trasmettiamo al rispettivo venditore i dati necessari per spedizione e
  fatturazione.
</p>

<h2 id="s16">16. Disposizioni finali</h2>
<p>
  16.1 Si applica il diritto della Repubblica Federale di Germania. Per i consumatori residenti in un
  altro Stato restano salve le norme imperative a tutela dei consumatori dello Stato di residenza
  (art. 6 comma 2 regolamento Roma I).
</p>
<p>
  16.2 Luogo di adempimento e foro competente sono determinati dalla legge. Per i consumatori
  valgono i fori di legge.
</p>
<p>
  16.3 Qualora singole disposizioni delle presenti condizioni fossero inefficaci, resta ferma
  l'efficacia delle restanti disposizioni. Alla disposizione inefficace subentra la norma di legge.
</p>
<p>
  16.4 Ci riserviamo di modificare le presenti condizioni con effetto per il futuro. Per i contratti
  già conclusi vale la versione consultabile al momento della conclusione; la versione accettata
  viene memorizzata con il Suo ordine (versione attuale: <?= h(VR_TERMS_VERSION) ?>).
</p>

<div class="doc__box">
  <strong>Nota su questa traduzione</strong>
  <p style="margin-top:8px">
    Questo testo italiano è una traduzione di cortesia delle condizioni generali di vendita tedesche,
    le sole giuridicamente vincolanti. Serve a farLe leggere ciò che accetta; in caso di divergenze
    prevale la versione tedesca. Non costituisce consulenza legale.
  </p>
</div>
