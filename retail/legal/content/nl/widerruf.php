<?php
/**
 * Herroepingsinformatie + modelformulier — Nederlands (vertaling ter informatie).
 * Volgt het officiële model van Richtlijn 2011/83/EU (bijlage I), zoals het Duitse
 * origineel het BGB volgt. Variabelen: legal/widerruf.php.
 */
?>
<div class="notice">
  <strong>Belangrijk bij aankopen op de marktplaats:</strong> het wettelijke herroepingsrecht
  bestaat alleen bij overeenkomsten tussen een consument en een <em>ondernemer</em>. Voor artikelen
  die op de productpagina als „<?= te('seller_private') ?>” zijn aangeduid, bestaat daarom
  <strong>geen</strong> herroepingsrecht. U ziet deze aanduiding vóór het betalen en moet haar
  tijdens het bestelproces uitdrukkelijk bevestigen.
</div>

<h2>Herroepingsrecht</h2>
<p>
  U heeft het recht om binnen een termijn van <?= (int)$wd ?> dagen zonder opgave van redenen de
  overeenkomst te herroepen.
</p>
<p>
  De herroepingstermijn verstrijkt <?= (int)$wd ?> dagen na de dag waarop u of een door u aangewezen
  derde, die niet de vervoerder is, het goed fysiek in bezit krijgt.
</p>
<p>
  Bij een overeenkomst over meerdere goederen die u in één bestelling hebt besteld en die
  afzonderlijk worden geleverd, verstrijkt de herroepingstermijn <?= (int)$wd ?> dagen na de dag
  waarop u of een door u aangewezen derde, die niet de vervoerder is, het laatste goed fysiek in
  bezit krijgt.
</p>
<p>
  Om het herroepingsrecht uit te oefenen, moet u ons
</p>
<div class="doc__box">
  <?= h($co) ?><br>
  <?= h($addrShown) ?><br>
  <?= $email !== '' ? 'E-mail: <a href="mailto:' . h($email) . '">' . h($email) . '</a>' : 'E-mail: [e-mailadres]' ?>
</div>
<p>
  via een ondubbelzinnige verklaring (bijvoorbeeld schriftelijk per post of e-mail) op de hoogte
  stellen van uw beslissing de overeenkomst te herroepen. U kunt hiervoor gebruikmaken van het
  bijgevoegde modelformulier voor herroeping, maar bent hiertoe niet verplicht.
</p>
<p>
  Betreft de herroeping een artikel van een zakelijke derde verkoper, dan volstaat de verklaring
  aan ons; wij zijn gemachtigd deze in ontvangst te nemen en sturen haar onverwijld door.
</p>
<p>
  Om de herroepingstermijn na te leven volstaat het om uw mededeling betreffende uw uitoefening van
  het herroepingsrecht te verzenden voordat de herroepingstermijn is verstreken.
</p>

<h2>Gevolgen van de herroeping</h2>
<p>
  Als u de overeenkomst herroept, ontvangt u alle betalingen die u tot op dat moment heeft gedaan,
  inclusief leveringskosten (met uitzondering van eventuele extra kosten ten gevolge van uw keuze
  voor een andere wijze van levering dan de door ons geboden goedkoopste standaardlevering)
  onverwijld en in ieder geval niet later dan veertien dagen nadat wij op de hoogte zijn gesteld van
  uw beslissing de overeenkomst te herroepen, van ons terug. Wij betalen u terug met hetzelfde
  betaalmiddel als waarmee u de oorspronkelijke transactie heeft verricht, tenzij u uitdrukkelijk
  anderszins heeft ingestemd; in ieder geval zullen u voor zulke terugbetaling geen kosten in
  rekening worden gebracht.
</p>
<p>
  Wij mogen wachten met terugbetaling tot wij de goederen hebben teruggekregen, of u heeft
  aangetoond dat u de goederen heeft teruggezonden, al naar gelang welk tijdstip eerst valt.
</p>
<p>
  U dient de goederen onverwijld, doch in ieder geval niet later dan veertien dagen na de dag waarop
  u het besluit de overeenkomst te herroepen aan ons heeft medegedeeld, aan ons of aan de op het
  retourlabel genoemde verkoper terug te zenden of te overhandigen. U bent op tijd als u de goederen
  terugstuurt voordat de termijn van veertien dagen is verstreken.
</p>
<p>
  Wij dragen de kosten van het terugzenden van de goederen als de retourzending vanuit Duitsland
  plaatsvindt. Bij retourzendingen uit andere landen draagt u de directe kosten van het terugzenden.
</p>
<p>
  U bent alleen aansprakelijk voor de waardevermindering van de goederen die het gevolg is van het
  gebruik van de goederen, dat verder gaat dan nodig is om de aard, de kenmerken en de werking van
  de goederen vast te stellen. Een kledingstuk passen is toegestaan; dragen, wassen of de labels
  verwijderen gaat daar overheen.
</p>

<h2>Uitsluiting van het herroepingsrecht</h2>
<p>Het herroepingsrecht bestaat niet of vervalt bij de volgende overeenkomsten:</p>
<ul>
  <li>overeenkomsten met verkopers die geen ondernemer zijn (particuliere verkopers);</li>
  <li>overeenkomsten tot levering van goederen die volgens specificaties van de consument zijn
      vervaardigd of duidelijk voor een specifieke persoon bestemd zijn;</li>
  <li>overeenkomsten tot levering van verzegelde goederen die om redenen van gezondheidsbescherming
      of hygiëne niet geschikt zijn om te worden teruggezonden en waarvan de verzegeling na de
      levering is verbroken (bijv. oorsieraden, badmode zonder hygiënezegel);</li>
  <li>overeenkomsten waarbij u als ondernemer handelt (B2B).</li>
</ul>

<h2>Modelformulier voor herroeping</h2>
<div class="doc__box">
  <p><em>(Dit formulier alleen invullen en terugzenden als u de overeenkomst wilt herroepen.)</em></p>
  <p>
    Aan<br>
    <?= h($co) ?><br>
    <?= h($addrShown) ?><br>
    <?= $email !== '' ? h($email) : '[e-mailadres]' ?>
  </p>
  <p>
    Ik/Wij (*) deel/delen (*) u hierbij mede dat ik/wij (*) onze overeenkomst betreffende de
    verkoop van de volgende goederen (*) herroep/herroepen (*):
  </p>
  <p>
    ______________________________________________<br>
    ______________________________________________
  </p>
  <p>
    Bestelnummer: ____________________________<br>
    Besteld op (*) / ontvangen op (*): ____________________________<br>
    Naam consument(en): ____________________________<br>
    Adres consument(en): ____________________________<br>
    ____________________________
  </p>
  <p>
    Handtekening van consument(en) <em>(alleen wanneer dit formulier op papier wordt
    ingediend)</em>: ____________________________<br>
    Datum: ____________________________
  </p>
  <p><em>(*) Doorhalen wat niet van toepassing is.</em></p>
</div>

<p class="doc__related">
  Bovenop het wettelijke herroepingsrecht verlenen wij voor eigen artikelen een vrijwillig
  retourrecht — details onder
  <a href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a>.
</p>
