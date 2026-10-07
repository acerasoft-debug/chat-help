<?php
/**
 * Note legali (§ 5 DDG, § 18 MStV) — italiano (traduzione di cortesia). Variabili: legal/impressum.php.
 */
?>
<h2>Fornitore</h2>
<?php vr_company_block(); ?>

<h2>Gestore del marketplace</h2>
<p>
  <?= h((string)vr_config('brand')) ?> è un marketplace online gestito da
  <?= h((string)($c['legal_name'] ?? '')) ?>. Sulla piattaforma vengono offerti sia articoli propri
  del gestore sia articoli di terzi (rivenditori professionali e venditori privati). Chi è la
  controparte del contratto di vendita è indicato su ogni pagina prodotto e durante l'ordine, prima
  dell'invio.
</p>

<h2>Responsabile dei contenuti</h2>
<p>
  Responsabile ai sensi del § 18 comma 2 MStV è la persona con potere di rappresentanza indicata
  sopra, indirizzo come sopra.
</p>

<h2>Contatto per richieste dei consumatori</h2>
<p>
  Per richieste su ordini, resi e reclami scriva a
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[indirizzo e-mail]</em>' ?>.
  Di norma rispondiamo entro un giorno lavorativo. Per domande su un articolo di un venditore terzo
  La mettiamo in contatto con il venditore o inoltriamo la Sua richiesta.
</p>

<h2>Risoluzione delle controversie dei consumatori</h2>
<p>
  Non siamo obbligati né disposti a partecipare a procedure di risoluzione delle controversie dinanzi
  a un organismo di conciliazione per i consumatori. Ciò non esclude un accordo bonario con noi — La
  preghiamo di rivolgersi prima direttamente a noi. Ulteriori informazioni in
  <a href="<?= h(vr_url('legal/streitbeilegung.php')) ?>"><?= te('legal_disputes') ?></a>.
</p>

<h2>Fornitore di servizi di pagamento</h2>
<p>
  I pagamenti sono gestiti tramite Stripe (Stripe Payments Europe, Limited, 1 Grand Canal Street
  Lower, Grand Canal Dock, Dublino, Irlanda). I pagamenti ai venditori terzi avvengono tramite Stripe
  Connect. I dati delle carte sono trattati esclusivamente presso Stripe e non raggiungono i nostri
  sistemi.
</p>

<h2>Responsabilità per i contenuti</h2>
<p>
  In quanto fornitore di servizi siamo responsabili dei nostri contenuti su queste pagine secondo le
  leggi generali. Per le offerte di venditori terzi non siamo obbligati a sorvegliare le informazioni
  trasmesse o memorizzate né a ricercare circostanze che indichino un'attività illecita (artt. 6, 8
  Digital Services Act). Non appena veniamo a conoscenza di una violazione concreta, rimuoviamo
  senza indugio il contenuto interessato. Le segnalazioni vanno inviate a
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[indirizzo e-mail]</em>' ?>.
</p>
<p>
  Verifichiamo la plausibilità delle offerte di venditori terzi prima della pubblicazione e
  richiediamo prove di provenienza. Questa verifica volontaria non costituisce garanzia della
  legittimità o autenticità di ogni singola offerta e lascia impregiudicato il nostro privilegio di
  responsabilità quale fornitore di servizi di hosting.
</p>

<h2>Responsabilità per i link</h2>
<p>
  Il nostro sito contiene link a siti esterni di terzi sui cui contenuti non abbiamo alcuna
  influenza. Di tali contenuti è sempre responsabile il rispettivo fornitore. Al momento del
  collegamento non erano riconoscibili contenuti illeciti.
</p>

<h2>Diritto d'autore</h2>
<p>
  I contenuti e le opere creati dal gestore su queste pagine sono protetti dal diritto d'autore. Le
  immagini e le descrizioni dei prodotti dei venditori terzi sono fornite da questi ultimi, che ci
  garantiscono di disporre dei diritti necessari. Nomi di marchi e prodotti sono di proprietà dei
  rispettivi titolari. La loro menzione serve unicamente a descrivere la merce offerta e non
  costituisce alcun rapporto commerciale con i titolari dei marchi.
</p>

<h2>Nota sui diritti di marchio</h2>
<p>
  <?= h((string)vr_config('brand')) ?> non è un rivenditore autorizzato dei marchi citati, salvo
  espressa indicazione contraria. La merce offerta è originale e immessa per la prima volta sul
  mercato nello Spazio economico europeo; la rivendita è quindi lecita in base al principio
  dell'esaurimento del diritto di marchio (§ 24 MarkenG, art. 15 RMUE).
</p>
