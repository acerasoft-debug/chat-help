<?php
/**
 * Returnering — dansk (vejledende oversættelse). Variabler: legal/rueckgabe.php.
 */
?>
<h2>Kort fortalt</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Sælger</th><th>Frist</th><th>Omkostninger ved returforsendelse</th></tr></thead>
  <tbody>
    <tr><td><?= h((string)vr_config('brand')) ?>-lager</td>
        <td><?= (int)$days ?> dage (lovbestemt <?= (int)$wd ?> + frivillig forlængelse)</td>
        <td>gratis fra Tyskland inden for den lovbestemte frist</td></tr>
    <tr><td>Forhandler</td>
        <td><?= (int)$wd ?> dages lovbestemt fortrydelsesret; mange forhandlere giver mere</td>
        <td>gratis fra Tyskland inden for den lovbestemte frist</td></tr>
    <tr><td>Privat sælger</td>
        <td>ingen lovbestemt fortrydelsesret</td>
        <td>tilbagetagelse kun ved afvigelse fra beskrivelsen</td></tr>
  </tbody>
</table></div>

<h2>Sådan returnerer du</h2>
<ol>
  <li>Skriv til
      <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-mail]</em>' ?>
      med dit ordrenummer og de varer, der skal retur.</li>
  <li>Inden for én arbejdsdag modtager du en returlabel og den pågældende sælgers returadresse.</li>
  <li>Pak varen helst i originalæsken, læg følgesedlen ved og aflever pakken.</li>
  <li>Efter modtagelse og kontrol refunderer vi til samme betalingsmiddel — senest 14 dage efter
      modtagelsen af din fortrydelseserklæring, så snart varen er hos os, eller du har dokumenteret
      afsendelsen.</li>
</ol>
<p>
  Vi modtager også returforsendelser uden forudgående besked; behandlingen tager så længere tid,
  fordi tilordningen sker manuelt.
</p>

<h2>Returvarens stand</h2>
<p>
  Det er udtrykkeligt i orden at prøve — det er netop det, returretten er til. Send venligst varer
  retur ubrugte, uvaskede, uparfumerede og med alle originale mærker. For et værditab som følge af
  håndtering ud over dette kan vi kræve kompensation; vi gør det op gennemskueligt og kontakter dig
  forinden.
</p>

<h2>Hvad der ikke kan tages retur</h2>
<ul>
  <li>badetøj og øresmykker uden intakt hygiejneforsegling;</li>
  <li>individuelt tilpassede eller efter dine anvisninger fremstillede varer;</li>
  <li>varer fra private sælgere, såfremt varen svarer til beskrivelsen.</li>
</ul>

<h2>Ombytning</h2>
<p>
  Direkte ombytning er ikke mulig, fordi de fleste varer er enkeltstykker eller enkelte størrelser.
  Send varen retur og bestil den rigtige størrelse på ny — hvis den stadig er tilgængelig. Er du
  interesseret i en bestemt størrelse, så skriv til os; vi fortæller, om der kan forventes
  genopfyldning.
</p>

<h2>Varen er defekt eller ikke som beskrevet</h2>
<p>
  Så gælder ikke returretten, men mangelsansvaret — med bedre rettigheder for dig. Kontakt os med
  fotos; returforsendelsen er i så fald altid gratis, også ved private sælgere og også efter
  returfristens udløb inden for den lovbestemte forældelse.
</p>

<h2>Ikke originalvare</h2>
<p>
  Skulle en vare vise sig ikke at være ægte, refunderer vi den fulde købesum inklusive
  forsendelsesomkostninger og afholder returforsendelsen — uanset hvilken sælger der har udbudt
  den. Den pågældende sælger fjernes fra platformen.
</p>

<h2>Premium Outlet</h2>
<p>
  Nedsatte priser ændrer intet ved dine rettigheder: For Vault-køb gælder samme frister som
  ovenfor. Kun undtagelsen for private sælgere består.
</p>

<p class="doc__related">
  Den juridiske tekst med standardfortrydelsesformular:
  <a href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a>.
</p>
