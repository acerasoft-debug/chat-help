<?php
/**
 * Informativa sulla privacy (GDPR) — italiano (traduzione di cortesia; fa fede il
 * testo tedesco). Scritta su ciò che il sito fa realmente. Variabili: legal/datenschutz.php.
 */
?>
<h2>1. Titolare del trattamento</h2>
<?php vr_company_block(); ?>
<p>
  Non è stato nominato un responsabile della protezione dei dati, non sussistendo i presupposti di
  legge (art. 37 GDPR, § 38 BDSG). Per richieste in materia di protezione dei dati rivolgersi a
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-mail]</em>' ?>.
</p>

<h2>2. Cosa <em>non</em> facciamo</h2>
<p>
  Non utilizziamo strumenti di analisi web (né Google Analytics né Matomo), né pixel pubblicitari o di
  tracciamento, né plug-in di social media, né profilazione. Non viene effettuato alcun processo
  decisionale automatizzato ai sensi dell'art. 22 GDPR. Anche la formazione dei prezzi nel Premium
  Outlet non è personalizzata: i prezzi derivano da un piano fisso e pubblicato e sono identici per
  tutti i visitatori.
</p>
<p>
  Tutti i caratteri, i fogli di stile, gli script e le immagini sono caricati dal nostro server. In
  particolare non vengono utilizzati Google Fonts — il Suo indirizzo IP non viene quindi trasmesso
  a terzi all'apertura della pagina.
</p>

<h2>3. Visita del sito (file di log del server)</h2>
<p>
  All'apertura del sito il nostro hosting provider tratta dati tecnicamente necessari: indirizzo IP,
  data e ora, risorsa richiesta, referrer, user agent e volume di dati trasferiti. Questi dati sono
  necessari per fornire la pagina e respingere gli attacchi.
</p>
<ul>
  <li><strong>Finalità:</strong> erogazione, stabilità, sicurezza informatica</li>
  <li><strong>Base giuridica:</strong> art. 6 par. 1 lett. f GDPR (legittimo interesse)</li>
  <li><strong>Conservazione:</strong> di norma 7–30 giorni, poi cancellazione automatica</li>
</ul>

<h2>4. Cookie e archiviazione locale</h2>
<p>
  Utilizziamo esclusivamente cookie tecnicamente necessari. Per questi non è richiesto il consenso
  ai sensi del § 25 comma 2 n. 2 TDDDG — per questo da noi non vede alcun banner dei cookie.
</p>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Nome</th><th>Finalità</th><th>Durata</th></tr></thead>
  <tbody>
    <tr><td><code><?= h((string)vr_config('session_name', 'maxsales_retail')) ?></code></td>
        <td>sessione: borsa, login venditore, protezione CSRF</td><td>fine sessione</td></tr>
    <tr><td><code>vr_lang</code></td><td>lingua scelta</td><td>180 giorni</td></tr>
    <tr><td><code>vr_member</code></td>
        <td>accesso anticipato al Vault dopo iscrizione confermata alla newsletter (valore firmato,
            nessuna e-mail in chiaro)</td><td>1 anno</td></tr>
    <tr><td><code>vr_wish</code></td><td>preferiti — solo identificativi degli articoli</td><td>180 giorni</td></tr>
    <tr><td><code>vr_seen</code></td><td>articoli visti di recente — solo identificativi degli articoli</td><td>30 giorni</td></tr>
  </tbody>
</table></div>
<p>
  Ulteriori informazioni: <a href="<?= h(vr_url('legal/cookies.php')) ?>"><?= te('legal_cookies') ?></a>.
</p>

<h2>5. Preferiti e articoli visti di recente</h2>
<p>
  Preferiti e «Visti di recente» sono salvati <strong>esclusivamente in un cookie sul Suo
  dispositivo</strong>. Il cookie contiene solo identificativi di articoli (es.
  <code>blm-ah0eg000</code>) — nessun nome, nessun indirizzo e-mail, nessun identificativo che La
  renda riconoscibile. Sui nostri server non si crea alcun profilo né alcun collegamento alla Sua
  persona.
</p>
<ul>
  <li><strong>Finalità:</strong> la funzione da Lei espressamente richiesta</li>
  <li><strong>Base giuridica:</strong> § 25 comma 2 n. 2 TDDDG (tecnicamente necessario per il
      servizio richiesto dall'utente); ove personale, art. 6 par. 1 lett. f GDPR</li>
  <li><strong>Conservazione:</strong> preferiti 180 giorni, visti di recente 30 giorni — o fino alla
      cancellazione dei cookie</li>
</ul>

<h2>6. Modulo di contatto</h2>
<p>
  Se utilizza il modulo di contatto, trattiamo il Suo indirizzo e-mail, facoltativamente nome e
  numero d'ordine, nonché il contenuto del messaggio. Una copia viene salvata sul nostro server
  affinché nessuna richiesta vada persa in caso di mancato invio dell'e-mail.
</p>
<ul>
  <li><strong>Finalità:</strong> risposta alla Sua richiesta</li>
  <li><strong>Base giuridica:</strong> art. 6 par. 1 lett. b GDPR se riferita a un ordine, altrimenti
      art. 6 par. 1 lett. f GDPR</li>
  <li><strong>Conservazione:</strong> fino alla chiusura della pratica, poi al massimo sei mesi; in
      caso di riferimento a un ordine valgono i termini di conservazione commerciali</li>
</ul>
<p>
  Contro lo spam utilizziamo un campo invisibile del modulo e una misurazione del tempo.
  <em>Nessun</em> servizio captcha esterno è integrato — nessun dato viene quindi trasmesso a terzi.
</p>

<h2>7. Avviso di prezzo nel Vault</h2>
<p>
  Se imposta un avviso di prezzo per un lotto, salviamo il Suo indirizzo e-mail, il lotto, il prezzo
  desiderato, il momento e un hash salato del Suo indirizzo IP come prova.
</p>
<ul>
  <li><strong>Finalità:</strong> l'unica notifica da Lei richiesta</li>
  <li><strong>Base giuridica:</strong> art. 6 par. 1 lett. a GDPR (consenso)</li>
  <li><strong>Conservazione:</strong> fino all'invio della notifica, al massimo 90 giorni. Poi il
      record viene cancellato completamente.</li>
</ul>
<p>
  Viene inviata <strong>esattamente una</strong> e-mail; poi l'avviso è esaurito. Non seguono
  promemoria né pubblicità. Ogni avviso può essere cancellato subito tramite il link nell'e-mail.
</p>

<h2>8. Ordine ed esecuzione del contratto</h2>
<p>
  Per un ordine trattiamo: nome, indirizzo di consegna e di fatturazione, indirizzo e-mail, articoli
  ordinati, prezzi, stato del pagamento, numero d'ordine e una prova del Suo consenso alle condizioni
  generali e all'informativa sul recesso (momento, versione e hash salato del Suo indirizzo IP — l'IP
  stesso non viene salvato).
</p>
<ul>
  <li><strong>Finalità:</strong> adempimento del contratto, spedizione, fatturazione, rimborso</li>
  <li><strong>Base giuridica:</strong> art. 6 par. 1 lett. b GDPR; per la conservazione art. 6 par. 1
      lett. c GDPR</li>
  <li><strong>Conservazione:</strong> i dati di ordine e fattura sono soggetti ai termini di
      conservazione commerciali e fiscali (§ 147 AO, § 257 HGB) e vengono conservati di conseguenza,
      poi cancellati.</li>
</ul>
<p>
  <strong>Comunicazione ai venditori:</strong> per articoli di venditori terzi trasmettiamo al
  rispettivo venditore i dati necessari per spedizione e fatturazione (nome, indirizzo di consegna,
  articoli ordinati, numero d'ordine). Il venditore è titolare autonomo per tali dati. Non vengono
  trasmessi dati di pagamento né informazioni su articoli di altri venditori.
</p>

<h2>9. Gestione dei pagamenti (Stripe)</h2>
<p>
  I pagamenti sono gestiti da Stripe Payments Europe, Limited, 1 Grand Canal Street Lower, Grand
  Canal Dock, Dublino, Irlanda. I dati di pagamento vengono inseriti direttamente presso Stripe; da
  Stripe riceviamo solo informazioni di stato (pagato/aperto/fallito), importo, metodo di pagamento
  in forma generica, nome, indirizzo e-mail e indirizzo di consegna.
</p>
<ul>
  <li><strong>Base giuridica:</strong> art. 6 par. 1 lett. b GDPR (adempimento del contratto)</li>
  <li><strong>Trasferimento verso paesi terzi:</strong> Stripe può trasferire dati a Stripe, Inc.
      negli USA, sulla base delle clausole contrattuali standard della Commissione UE e della
      certificazione ai sensi dell'EU-US Data Privacy Framework.</li>
</ul>
<p>
  Per i pagamenti ai venditori utilizziamo Stripe Connect. I venditori stipulano a tal fine un
  proprio accordo con Stripe; le prove d'identità ivi raccolte (KYC/antiriciclaggio) sono trattate
  da Stripe come titolare autonomo. Riceviamo solo gli indicatori di stato <code>charges_enabled</code>,
  <code>payouts_enabled</code> e <code>details_submitted</code>.
</p>
<p>Informativa privacy di Stripe: <a href="https://stripe.com/privacy" rel="noopener">stripe.com/privacy</a></p>

<h2>10. Invio di e-mail</h2>
<p>
  Le e-mail transazionali (conferma d'ordine, avviso di spedizione, notifica al venditore) e la
  newsletter vengono inviate tramite
  <?= h($mail['provider'] === 'brevo' ? 'Brevo (Sendinblue GmbH / Brevo SAS, Parigi, Francia)' : 'il nostro server di posta') ?>.
  Vengono trasmessi indirizzo e-mail, nome e contenuto del messaggio.
</p>
<ul>
  <li><strong>Base giuridica:</strong> e-mail transazionali art. 6 par. 1 lett. b GDPR; newsletter
      art. 6 par. 1 lett. a GDPR (consenso)</li>
  <li><strong>Responsabile del trattamento:</strong> è in essere un contratto ai sensi dell'art. 28
      GDPR.</li>
</ul>

<h2>11. Newsletter / iscrizione al Vault</h2>
<p>
  L'iscrizione avviene con procedura double opt-in: dopo l'inserimento dell'indirizzo riceve
  un'e-mail di conferma; l'iscrizione diventa efficace solo con il clic sul link. Come prova
  salviamo il momento dell'iscrizione, il momento della conferma e un hash salato dell'indirizzo IP.
</p>
<p>
  Le iscrizioni non confermate vengono cancellate automaticamente dopo 7 giorni. Può revocare il
  consenso in qualsiasi momento — tramite il link di disiscrizione in ogni e-mail o scrivendoci. La
  revoca non pregiudica la liceità del trattamento effettuato fino a quel momento.
</p>

<h2>12. Account venditore</h2>
<p>
  Per un account venditore trattiamo: nome, eventuale ragione sociale, indirizzo e-mail, Paese,
  eventuale partita IVA, tipo di venditore (professionale/privato), password (solo come hash
  crittografico, mai in chiaro), offerte nonché dati di fatturato e di pagamento.
</p>
<ul>
  <li><strong>Base giuridica:</strong> art. 6 par. 1 lett. b GDPR; per la verifica delle offerte e la
      tracciabilità delle informazioni dei professionisti anche art. 6 par. 1 lett. c GDPR in
      combinato disposto con l'art. 30 Digital Services Act.</li>
  <li><strong>Pubblicazione:</strong> per i venditori professionali mostriamo nome/ragione sociale e
      Paese sulla pagina del prodotto; è previsto dalla legge. Per i venditori privati viene mostrato
      solo lo stato «<?= te('seller_private') ?>», non il nome completo.</li>
</ul>

<h2>13. Misure di sicurezza e log</h2>
<p>
  Teniamo log tecnici dei tentativi di accesso falliti, degli errori di pagamento e degli eventi
  webhook. Contengono momento, tipo di evento e identificativi tecnici; gli indirizzi e-mail sono
  troncati. La finalità è la prevenzione di abusi e frodi (art. 6 par. 1 lett. f GDPR), conservazione
  massima 90 giorni.
</p>
<p>
  La trasmissione è cifrata (TLS). Le password sono salvate con una moderna procedura di hash
  unidirezionale.
</p>

<h2>14. I Suoi diritti</h2>
<p>Ha in qualsiasi momento il diritto di:</p>
<ul>
  <li>accesso ai dati che La riguardano (art. 15 GDPR)</li>
  <li>rettifica dei dati inesatti (art. 16 GDPR)</li>
  <li>cancellazione (art. 17 GDPR), salvo obblighi di conservazione</li>
  <li>limitazione del trattamento (art. 18 GDPR)</li>
  <li>portabilità dei dati (art. 20 GDPR)</li>
  <li>opposizione ai trattamenti basati sul legittimo interesse (art. 21 GDPR)</li>
  <li>revoca dei consensi prestati, con effetto per il futuro (art. 7 par. 3 GDPR)</li>
</ul>
<p>
  È sufficiente un messaggio a
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-mail]</em>' ?>.
  Ha inoltre il diritto di proporre reclamo a un'autorità di controllo per la protezione dei dati,
  ad esempio quella del Suo luogo di residenza abituale.
</p>

<h2>15. Modifiche</h2>
<p>
  Adeguiamo questa informativa quando cambia il trattamento effettivo — ad esempio in caso di
  ricorso a un nuovo fornitore. Fa fede la versione pubblicata su questa pagina.
</p>
