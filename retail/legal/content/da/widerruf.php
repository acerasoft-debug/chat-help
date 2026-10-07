<?php
/**
 * Fortrydelsesvejledning + standardfortrydelsesformular — dansk (vejledende
 * oversættelse). Følger den officielle model i direktiv 2011/83/EU (bilag I),
 * ligesom den tyske original følger BGB. Variabler: legal/widerruf.php.
 */
?>
<div class="notice">
  <strong>Vigtigt ved køb på markedspladsen:</strong> Den lovbestemte fortrydelsesret gælder kun ved
  aftaler mellem en forbruger og en <em>erhvervsdrivende</em>. For varer, der på produktsiden er
  markeret som „<?= te('seller_private') ?>“, er der derfor <strong>ingen</strong> fortrydelsesret.
  Du ser denne mærkning inden betaling og skal bekræfte den udtrykkeligt under bestillingen.
</div>

<h2>Fortrydelsesret</h2>
<p>
  Du har ret til at træde tilbage fra denne aftale uden begrundelse inden for <?= (int)$wd ?> dage.
</p>
<p>
  Fortrydelsesfristen udløber <?= (int)$wd ?> dage efter den dag, hvor du eller en af dig angiven
  tredjemand, dog ikke transportøren, får varerne i fysisk besiddelse.
</p>
<p>
  Ved en aftale om flere varer, som du har bestilt i én ordre, og som leveres enkeltvis, udløber
  fortrydelsesfristen <?= (int)$wd ?> dage efter den dag, hvor du eller en af dig angiven
  tredjemand, dog ikke transportøren, får den sidste vare i fysisk besiddelse.
</p>
<p>
  For at udøve fortrydelsesretten skal du meddele os
</p>
<div class="doc__box">
  <?= h($co) ?><br>
  <?= h($addrShown) ?><br>
  <?= $email !== '' ? 'E-mail: <a href="mailto:' . h($email) . '">' . h($email) . '</a>' : 'E-mail: [e-mailadresse]' ?>
</div>
<p>
  din beslutning om at fortryde denne aftale i en utvetydig erklæring (f.eks. ved postbesørget brev
  eller e-mail). Du kan benytte den vedhæftede standardfortrydelsesformular, men det er ikke
  obligatorisk.
</p>
<p>
  Vedrører fortrydelsen en vare fra en erhvervsdrivende tredjepartssælger, er erklæringen over for
  os tilstrækkelig; vi er bemyndiget til at modtage den og videresender den straks.
</p>
<p>
  Fortrydelsesfristen er overholdt, hvis du sender din meddelelse om udøvelse af fortrydelsesretten,
  inden fortrydelsesfristen er udløbet.
</p>

<h2>Følger af fortrydelse</h2>
<p>
  Hvis du udøver din fortrydelsesret i denne aftale, refunderer vi alle betalinger modtaget fra dig,
  herunder leveringsomkostninger (dog ikke ekstra omkostninger som følge af dit eget valg af en
  anden leveringsform end den billigste form for standardlevering, som vi tilbyder), uden unødig
  forsinkelse og under alle omstændigheder senest fjorten dage fra den dato, hvor vi har modtaget
  meddelelse om din beslutning om at fortryde denne aftale. Vi gennemfører en sådan
  tilbagebetaling med samme betalingsmiddel, som du benyttede ved den oprindelige transaktion,
  medmindre du udtrykkeligt har indvilget i noget andet. Under alle omstændigheder pålægges du
  ingen former for gebyrer som følge af tilbagebetalingen.
</p>
<p>
  Vi kan tilbageholde tilbagebetalingen, indtil vi har modtaget varerne retur, eller du har fremlagt
  dokumentation for at have returneret varerne, alt efter hvad der er tidligst.
</p>
<p>
  Du returnerer varerne eller afleverer dem til os eller til den sælger, der er angivet på
  returlabelen, uden unødig forsinkelse og senest fjorten dage fra den dato, hvor du har informeret
  os om udøvelsen af aftalens fortrydelsesret. Fristen er overholdt, hvis du returnerer varerne
  inden udløbet af de fjorten dage.
</p>
<p>
  Vi afholder omkostningerne ved returnering af varerne, hvis returneringen sker fra Tyskland. Ved
  returnering fra andre lande skal du afholde de direkte udgifter i forbindelse med
  tilbageleveringen af varerne.
</p>
<p>
  Du hæfter kun for eventuel forringelse af varernes værdi, som skyldes anden håndtering, end hvad
  der er nødvendigt for at fastslå varernes art, egenskaber og den måde, de fungerer på. Det er
  tilladt at prøve et stykke tøj; at bære, vaske eller fjerne mærkerne går ud over dette.
</p>

<h2>Undtagelser fra fortrydelsesretten</h2>
<p>Fortrydelsesretten gælder ikke eller bortfalder ved følgende aftaler:</p>
<ul>
  <li>aftaler med sælgere, der ikke er erhvervsdrivende (private sælgere);</li>
  <li>aftaler om levering af varer, som er fremstillet efter forbrugerens specifikationer eller har
      fået et tydeligt personligt præg;</li>
  <li>aftaler om levering af forseglede varer, som af sundhedsbeskyttelses- eller hygiejnemæssige
      årsager ikke er egnede til at blive returneret, og hvor forseglingen er blevet brudt efter
      leveringen (f.eks. øresmykker, badetøj uden hygiejneforsegling);</li>
  <li>aftaler, hvor du handler som erhvervsdrivende (B2B).</li>
</ul>

<h2>Standardfortrydelsesformular</h2>
<div class="doc__box">
  <p><em>(Denne formular udfyldes og returneres kun, hvis fortrydelsesretten gøres gældende.)</em></p>
  <p>
    Til<br>
    <?= h($co) ?><br>
    <?= h($addrShown) ?><br>
    <?= $email !== '' ? h($email) : '[e-mailadresse]' ?>
  </p>
  <p>
    Jeg/vi (*) meddeler herved, at jeg/vi (*) ønsker at gøre fortrydelsesretten gældende i
    forbindelse med min/vores (*) købsaftale om følgende varer (*):
  </p>
  <p>
    ______________________________________________<br>
    ______________________________________________
  </p>
  <p>
    Ordrenummer: ____________________________<br>
    Bestilt den (*) / modtaget den (*): ____________________________<br>
    Forbrugerens navn (forbrugernes navne): ____________________________<br>
    Forbrugerens adresse (forbrugernes adresse): ____________________________<br>
    ____________________________
  </p>
  <p>
    Forbrugerens underskrift (forbrugernes underskrifter) <em>(kun hvis formularens indhold
    meddeles på papir)</em>: ____________________________<br>
    Dato: ____________________________
  </p>
  <p><em>(*) Det ikke relevante udstreges.</em></p>
</div>

<p class="doc__related">
  Ud over den lovbestemte fortrydelsesret giver vi en frivillig returret for egne varer — detaljer
  under <a href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a>.
</p>
