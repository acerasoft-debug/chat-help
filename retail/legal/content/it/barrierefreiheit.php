<?php
/**
 * Dichiarazione di accessibilità (BFSG) — italiano (traduzione di cortesia).
 * Variabili: legal/barrierefreiheit.php.
 */
?>
<h2>Il nostro obiettivo</h2>
<p>
  Vogliamo che questo negozio sia utilizzabile da tutti — con la tastiera, con uno screen reader,
  con caratteri ingranditi, con animazioni ridotte. La base sono i requisiti della legge tedesca sul
  rafforzamento dell'accessibilità (BFSG) e la norma EN 301 549, che rinvia alle WCAG 2.1 livello AA.
</p>

<h2>Stato di attuazione</h2>
<p>
  A nostro giudizio questo sito è <strong>in larga misura conforme</strong> alle WCAG 2.1 livello AA.
  La valutazione si basa su una verifica interna, non su un audit esterno.
</p>

<h3>Cosa è stato realizzato</h3>
<ul>
  <li><strong>Utilizzabile senza JavaScript:</strong> navigazione, filtri, borsa, cassa e area
      venditore funzionano completamente con semplici moduli HTML. JavaScript aggiunge solo comodità
      (conto alla rovescia, selettore quantità, dissolvenze).</li>
  <li><strong>Uso da tastiera:</strong> tutti gli elementi interattivi sono raggiungibili, con
      indicatore di focus visibile; un link «Vai al contenuto» è in cima a ogni pagina.</li>
  <li><strong>Contrasti:</strong> ogni nodo di testo delle pagine principali è stato misurato
      automaticamente (colore effettivamente reso su sfondo effettivamente reso, trasparenze
      incluse). Tutti raggiungono almeno 4,5:1, i caratteri grandi almeno 3:1. L'area scura del Vault
      usa propri valori di grigio e i colori d'accento hanno una variante testo più scura su sfondo
      chiaro.</li>
  <li><strong>Struttura:</strong> un H1 per pagina, livelli di intestazione logici, landmark
      (<code>header</code>, <code>nav</code>, <code>main</code>, <code>footer</code>), campi modulo
      etichettati, messaggi di errore collegati.</li>
  <li><strong>Immagini:</strong> le immagini prodotto hanno testi alternativi composti da marca e
      denominazione; le grafiche puramente decorative sono nascoste alle tecnologie assistive.</li>
  <li><strong>Movimento:</strong> con l'impostazione di sistema «Riduci movimento» attiva, scorrimento
      orizzontale, dissolvenze e transizioni vengono disattivati.</li>
  <li><strong>Zoom e schermi piccoli:</strong> il layout resta utilizzabile fino al 400 % di zoom
      senza scorrimento orizzontale del contenuto.</li>
  <li><strong>Lingua:</strong> la lingua della pagina è dichiarata nell'HTML e commutabile tramite il
      selettore (dieci lingue).</li>
  <li><strong>Galleria immagini:</strong> ogni immagine prodotto è un normale link al file immagine.
      Senza JavaScript si apre direttamente; con JavaScript in una lightbox che si chiude con Esc e
      si sfoglia con le frecce.</li>
  <li><strong>Suggerimenti di ricerca:</strong> utilizzabili con le frecce e Invio, chiudibili con
      Esc. Il campo di ricerca funziona anche senza suggerimenti come modulo ordinario.</li>
  <li><strong>Preferiti:</strong> realizzati come modulo; lo stato è in <code>aria-pressed</code> e
      viene salvato correttamente anche senza JavaScript.</li>
</ul>

<h3>Limitazioni note</h3>
<ul>
  <li><strong>Immagini prodotto di venditori terzi:</strong> i testi alternativi sono generati
      automaticamente da marca e denominazione. Non descrivono il soggetto nel dettaglio — per
      domande su un articolo lo descriviamo volentieri via e-mail.</li>
  <li><strong>Pagina di pagamento:</strong> il pagamento avviene presso Stripe. Dell'accessibilità
      di quelle pagine è responsabile Stripe; non ci risultano carenze di conformità ma non le
      abbiamo verificate noi stessi.</li>
  <li><strong>Conto alla rovescia del Vault:</strong> il tempo residuo si aggiorna ogni secondo. Il
      prezzo determinante è indicato come testo accanto e cambia solo dopo il caricamento della
      pagina, così l'uso dello screen reader non è disturbato da modifiche in tempo reale.</li>
  <li><strong>Documenti PDF:</strong> fatture ed etichette di reso sono in parte generate da venditori
      e corrieri e potrebbero non essere taggate. Su richiesta forniamo i contenuti in forma
      accessibile.</li>
</ul>

<h2>Feedback e contatto</h2>
<p>
  Se incontra una barriera, scriva a <?= $mail ?> con oggetto «Accessibilità» indicando, se
  possibile, la pagina e la Sua tecnologia assistiva. Rispondiamo entro un giorno lavorativo e
  indichiamo una data per la correzione. Se Le serve un'informazione in altra forma — caratteri più
  grandi, testo semplice, lettura al telefono — basta dirlo; la forniamo gratuitamente.
</p>

<h2>Procedura di esecuzione</h2>
<p>
  Se la nostra risposta non è di aiuto, può rivolgersi all'autorità di vigilanza del mercato dei
  Länder per l'accessibilità di prodotti e servizi (MDBD), organismo competente ai sensi del BFSG
  per i reclami sull'accessibilità dei servizi:
  <a href="https://www.marktueberwachungsstelle.de" rel="noopener">marktueberwachungsstelle.de</a>.
</p>

<h2>Redazione di questa dichiarazione</h2>
<p>
  Questa dichiarazione è stata redatta il <?= h(vr_date(strtotime('2026-08-01'))) ?> sulla base di
  un'autovalutazione interna: navigazione da tastiera, misurazione automatizzata del contrasto di
  tutti i nodi di testo in un browser reale, test con JavaScript disattivato, verifica della
  struttura delle intestazioni e delle etichette dei moduli. Non è stato effettuato alcun audit
  esterno. Aggiorniamo la dichiarazione quando il negozio cambia in modo sostanziale.
</p>
