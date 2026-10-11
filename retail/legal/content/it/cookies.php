<?php
/**
 * Informativa sui cookie — italiano (traduzione di cortesia). Nessun banner perché nessun tracciamento.
 */
?>
<div class="doc__box">
  <strong>Perché qui non vede un banner dei cookie</strong>
  <p style="margin-top:8px">
    Un banner è necessario solo se vengono impostati cookie che vanno oltre il tecnicamente
    necessario — analisi, pubblicità, tracciamento. Non ne utilizziamo alcuno. Per i cookie
    puramente funzionali il § 25 comma 2 n. 2 TDDDG consente l'impostazione senza consenso.
    Quindi: nessun banner, nessun pulsante «Accetta tutto», nessun servizio di consenso.
  </p>
</div>

<h2>Elenco completo</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Nome</th><th>Tipo</th><th>Finalità</th><th>Conservazione</th></tr></thead>
  <tbody>
    <tr>
      <td><code><?= h((string)vr_config('session_name', 'maxsales_retail')) ?></code></td>
      <td>cookie di sessione</td>
      <td>Tiene insieme la Sua sessione: contenuto della borsa, prenotazioni nel Vault, login
          venditore e token di sicurezza contro la falsificazione dei moduli (CSRF). Contiene solo un
          identificativo casuale, nessun contenuto personale.</td>
      <td>fino alla fine della sessione del browser</td>
    </tr>
    <tr>
      <td><code>vr_lang</code></td>
      <td>funzionale</td>
      <td>Ricorda la lingua scelta, così non deve riselezionarla a ogni clic. Contenuto: un codice
          lingua come <code>it</code>.</td>
      <td>180 giorni</td>
    </tr>
    <tr>
      <td><code>vr_cur</code></td>
      <td>funzionale</td>
      <td>Ricorda la valuta di visualizzazione scelta per il prezzo indicativo accanto al prezzo in euro. Contenuto: un codice valuta come <code>USD</code>.</td>
      <td>180 giorni</td>
    </tr>
    <tr>
      <td><code>vr_member</code></td>
      <td>funzionale</td>
      <td>Impostato solo dopo la conferma via e-mail dell'iscrizione al Vault; sblocca l'accesso
          anticipato ai nuovi lotti. Contiene una data di scadenza, un hash troncato del Suo
          indirizzo e-mail e una firma — non il Suo indirizzo in chiaro.</td>
      <td>1 anno</td>
    </tr>
    <tr>
      <td><code>vr_wish</code></td>
      <td>funzionale</td>
      <td>I Suoi preferiti. Contenuto: un elenco di identificativi di articoli, nient'altro. Non
          viene comunicato al server se non quando apre Lei stesso la pagina dei preferiti.</td>
      <td>180 giorni</td>
    </tr>
    <tr>
      <td><code>vr_seen</code></td>
      <td>funzionale</td>
      <td>Gli articoli che ha visto di recente, per ritrovarli. Anche qui solo identificativi di
          articoli.</td>
      <td>30 giorni</td>
    </tr>
  </tbody>
</table></div>

<h2>Cookie di Stripe</h2>
<p>
  Al momento del pagamento passa a una pagina di Stripe. Lì Stripe imposta propri cookie, necessari
  per la gestione del pagamento e la prevenzione delle frodi. Ciò avviene sul dominio di Stripe ed è
  soggetto all'<a href="https://stripe.com/privacy" rel="noopener">informativa privacy di Stripe</a>.
  Sulle nostre pagine non è integrato alcuno script Stripe.
</p>

<h2>Local storage solo per due promemoria, nessun fingerprint</h2>
<p>
  Nel <code>localStorage</code> del browser conserviamo al massimo due voci senza alcun
  identificativo, ciascuna con solo un orario: che hai chiuso l’avviso dell’app in fondo allo
  schermo (<code>sv-appbar</code>, non più mostrato per 30 giorni) e che hai rimandato la domanda
  sulle notifiche con «Non ora» (<code>sv-push-later</code>, 14 giorni). Entrambe sono necessarie
  per la funzione che hai scelto (§ 25, c. 2, n. 2 TDDDG). L’app installata conserva inoltre file di
  stile e script e una pagina offline nella cache del browser. Non usiamo
  <code>sessionStorage</code>, pixel, tecniche di fingerprinting né riconoscimento tra dispositivi.
  Tutti i caratteri, gli stili, gli script e le immagini sono sul nostro server; al caricamento della
  pagina non viene stabilita alcuna connessione a terzi.
</p>

<h2>Cancellare o bloccare i cookie</h2>
<p>
  Può cancellare o bloccare i cookie in qualsiasi momento nelle impostazioni del browser. Se blocca il
  cookie di sessione, borsa, cassa e login venditore smettono di funzionare — manca il filo tecnico
  che collega i Suoi passaggi. Lingua e accesso al Vault possono essere bloccati senza problemi;
  allora chiediamo di nuovo la lingua e l'accesso anticipato non è disponibile.
</p>
<p class="doc__related">
  In dettaglio sul trattamento dei dati:
  <a href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>.
</p>
