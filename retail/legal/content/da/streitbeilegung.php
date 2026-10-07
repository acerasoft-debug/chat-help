<?php
/**
 * Tvistbilæggelse + DSA-kontaktpunkt + anmeldelsesprocedure (art. 16 DSA) — dansk.
 * Variabler: legal/streitbeilegung.php.
 */
?>
<h2>Først den direkte vej</h2>
<p>
  De fleste problemer kan løses i én e-mail. Skriv til <?= $mail ?> med dit ordrenummer og en kort
  beskrivelse. Vi svarer som regel inden for én arbejdsdag og vender senest efter syv dage tilbage
  med en afgørelse eller en status.
</p>

<h2>Klager over sælgere</h2>
<p>
  Ved problemer med en tredjepartssælger — vare ikke modtaget, stand afvigende, refusion udeblevet —
  formidler vi og kan tilbageholde sælgerens andel, indtil sagen er afklaret. Findes der ingen
  løsning, refunderer vi selv i de tilfælde, der er nævnt i salgsbetingelsernes
  <a href="<?= h(vr_url('legal/agb.php')) ?>#s10">punkt 10.3</a> og
  <a href="<?= h(vr_url('legal/agb.php')) ?>#s12">12.2</a>.
</p>

<h2>Anmeldelse af ulovligt indhold (art. 16 DSA)</h2>
<p>
  Enhver kan anmelde tilbud til os, som vedkommende anser for ulovlige — f.eks. forfalskninger,
  varemærkekrænkelser eller forbudte produkter. Angiv venligst:
</p>
<ul>
  <li>tilbuddets præcise adresse (URL),</li>
  <li>en begrundelse for, hvorfor indholdet skulle være ulovligt,</li>
  <li>dit navn og en e-mailadresse (undtagen ved anmeldelser af forbrydelser mod personer),</li>
  <li>en erklæring om, at dine oplysninger efter bedste overbevisning er korrekte og fuldstændige.</li>
</ul>
<p>
  Anmeldelser sendes til <?= $mail ?> med emnet „DSA-anmeldelse“. Vi bekræfter modtagelsen straks,
  træffer afgørelse hurtigt og omhyggeligt og meddeler dig afgørelsen med begrundelse.
  Rettighedshavere, der gentagne gange sender os korrekte anmeldelser, behandler vi med forrang
  (art. 22 DSA).
</p>

<h2>Hvis vi fjerner et tilbud</h2>
<p>
  Berørte sælgere informeres begrundet om fjernelse, spærring eller nedsat synlighed (art. 17 DSA)
  og kan gøre indsigelse mod afgørelsen pr. e-mail inden for 14 dage. Indsigelsen vurderes af en
  person, der ikke var involveret i den oprindelige afgørelse. Afgørelsen om indsigelsen meddeles
  begrundet.
</p>
<p>
  Ved åbenbart ubegrundede anmeldelser eller gentagne ulovlige tilbud suspenderer vi behandlingen
  efter rimelig forudgående advarsel hhv. spærrer kontoen (art. 23 DSA).
</p>

<h2>Udenretlig tvistbilæggelse for forbrugere</h2>
<p>
  Vi er hverken forpligtet eller villige til at deltage i tvistbilæggelsesprocedurer ved et
  forbrugerklagenævn efter den tyske lov om forbrugertvistbilæggelse (VSBG). Din mulighed for at gå
  rettens vej berøres ikke heraf; heller ikke vores bestræbelse på først at afklare enhver sag
  direkte.
</p>
<p>
  Bemærk: Europa-Kommissionens tidligere platform til onlinetvistbilæggelse (OTB-platformen) blev
  nedlagt den 20. juli 2025. Et link dertil er derfor bortfaldet. Ved grænseoverskridende sager kan
  du henvende dig til Det Europæiske Forbrugercenter (<a href="https://www.evz.de" rel="noopener">evz.de</a>).
</p>

<h2>Værneting og lovvalg</h2>
<p>
  Tysk ret finder anvendelse. For forbrugere gælder de lovbestemte værneting; ufravigelige
  forbrugerbeskyttelsesregler i opholdsstaten berøres ikke (art. 6, stk. 2, Rom I-forordningen).
</p>

<h2>Kontaktpunkt for myndigheder</h2>
<p>
  For henvendelser fra myndigheder og domstole efter art. 11 DSA kan du nå os på <?= $mail ?>.
  Sagsbehandlingssprog er tysk og engelsk.
</p>
