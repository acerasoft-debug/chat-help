<?php
/**
 * Returer — svenska (vägledande översättning). Variabler: legal/rueckgabe.php.
 */
?>
<h2>I korthet</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Säljare</th><th>Frist</th><th>Kostnad för returfrakt</th></tr></thead>
  <tbody>
    <tr><td><?= h((string)vr_config('brand')) ?>-lager</td>
        <td><?= (int)$days ?> dagar (lagstadgade <?= (int)$wd ?> + frivillig förlängning)</td>
        <td>kostnadsfritt från Tyskland inom den lagstadgade fristen</td></tr>
    <tr><td>Återförsäljare</td>
        <td><?= (int)$wd ?> dagars lagstadgad ångerrätt; många återförsäljare ger mer</td>
        <td>kostnadsfritt från Tyskland inom den lagstadgade fristen</td></tr>
    <tr><td>Privat säljare</td>
        <td>ingen lagstadgad ångerrätt</td>
        <td>återtagande endast vid avvikelse från beskrivningen</td></tr>
  </tbody>
</table></div>

<h2>Så här returnerar du</h2>
<ol>
  <li>Skriv till
      <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-post]</em>' ?>
      med ditt ordernummer och de varor som ska returneras.</li>
  <li>Inom en arbetsdag får du en returetikett och respektive säljares returadress.</li>
  <li>Packa varan helst i originalkartongen, lägg med följesedeln och lämna in paketet.</li>
  <li>Efter mottagande och kontroll återbetalar vi till samma betalningsmedel — senast 14 dagar
      efter att din ångerförklaring kommit in, så snart varan är hos oss eller du styrkt
      avsändandet.</li>
</ol>
<p>
  Vi tar även emot returer utan föregående avisering; handläggningen tar då längre tid eftersom
  matchningen sker manuellt.
</p>

<h2>Returvarans skick</h2>
<p>
  Att prova är uttryckligen i sin ordning — det är precis det returrätten är till för. Skicka varor
  tillbaka oanvända, otvättade, oparfymerade och med alla originaletiketter kvar. För en
  värdeminskning genom hantering utöver detta kan vi begära ersättning; vi räknar ut den
  transparent och hör av oss till dig först.
</p>

<h2>Vad som inte kan returneras</h2>
<ul>
  <li>badkläder och örhängen utan intakt hygienförsegling;</li>
  <li>individuellt anpassade eller enligt dina anvisningar tillverkade varor;</li>
  <li>varor från privata säljare, såvida varan motsvarar beskrivningen.</li>
</ul>

<h2>Byte</h2>
<p>
  Direkt byte är inte möjligt eftersom de flesta varor är unika exemplar eller enstaka storlekar.
  Returnera varan och beställ rätt storlek på nytt — om den fortfarande finns. Är du intresserad av
  en viss storlek, skriv till oss; vi berättar om påfyllning kan väntas.
</p>

<h2>Varan är defekt eller inte som beskriven</h2>
<p>
  Då gäller inte returrätten utan felansvaret — med bättre rättigheter för dig. Hör av dig med
  foton; returen är i så fall alltid kostnadsfri, även hos privata säljare och även efter
  returfristens utgång inom den lagstadgade preskriptionstiden.
</p>

<h2>Inte äkta vara</h2>
<p>
  Skulle en vara visa sig inte vara äkta återbetalar vi hela köpeskillingen inklusive
  fraktkostnader och står för returen — oavsett vilken säljare som erbjöd den. Den berörda säljaren
  tas bort från plattformen.
</p>

<h2>Premium Outlet</h2>
<p>
  Sänkta priser ändrar inget i dina rättigheter: för Vault-köp gäller samma frister som ovan. Endast
  undantaget för privata säljare kvarstår.
</p>

<p class="doc__related">
  Den juridiska texten med standardångerblankett:
  <a href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a>.
</p>
