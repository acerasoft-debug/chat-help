<?php
/**
 * Cookiepolitik — dansk (vejledende oversættelse). Intet banner, fordi ingen sporing.
 */
?>
<div class="doc__box">
  <strong>Hvorfor du ikke ser et cookiebanner her</strong>
  <p style="margin-top:8px">
    Et banner er kun nødvendigt, hvis der sættes cookies, der går ud over det teknisk nødvendige —
    analyse, reklame, sporing. Vi anvender intet af dette. For rent funktionelle cookies tillader
    § 25, stk. 2, nr. 2, TDDDG placering uden samtykke. Derfor: intet banner, ingen „Acceptér
    alle“-knap, ingen samtykketjeneste.
  </p>
</div>

<h2>Fuldstændig liste</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Navn</th><th>Type</th><th>Formål</th><th>Opbevaring</th></tr></thead>
  <tbody>
    <tr>
      <td><code><?= h((string)vr_config('session_name', 'maxsales_retail')) ?></code></td>
      <td>sessionscookie</td>
      <td>Holder din session sammen: taskens indhold, Vault-reservationer, sælgerlogin og
          sikkerhedstokenet mod formularforfalskning (CSRF). Indeholder kun et tilfældigt kendetegn,
          intet personligt indhold.</td>
      <td>indtil browsersessionen afsluttes</td>
    </tr>
    <tr>
      <td><code>vr_lang</code></td>
      <td>funktionel</td>
      <td>Husker det valgte sprog, så du ikke skal vælge det igen ved hvert klik. Indhold: en
          sprogkode som <code>da</code>.</td>
      <td>180 dage</td>
    </tr>
    <tr>
      <td><code>vr_member</code></td>
      <td>funktionel</td>
      <td>Sættes kun, når du har bekræftet Vault-medlemskabet pr. e-mail, og giver tidlig adgang til
          nye partier. Indeholder en udløbsdato, en afkortet hashværdi af din e-mailadresse og en
          signatur — ikke din adresse i klartekst.</td>
      <td>1 år</td>
    </tr>
    <tr>
      <td><code>vr_wish</code></td>
      <td>funktionel</td>
      <td>Din ønskeliste. Indhold: en liste af varenumre, intet andet. Meddeles ikke til serveren,
          medmindre du selv åbner ønskelistesiden.</td>
      <td>180 dage</td>
    </tr>
    <tr>
      <td><code>vr_seen</code></td>
      <td>funktionel</td>
      <td>De varer, du senest har set, så du kan finde dem igen. Ligeledes kun varenumre.</td>
      <td>30 dage</td>
    </tr>
  </tbody>
</table></div>

<h2>Cookies fra Stripe</h2>
<p>
  Ved betaling skifter du til en side hos Stripe. Stripe sætter dér egne cookies, som er nødvendige
  for betalingsafvikling og svindelforebyggelse. Det sker på Stripes domæne og er underlagt
  <a href="https://stripe.com/privacy" rel="noopener">Stripes privatlivspolitik</a>. På vores egne
  sider indlejres intet Stripe-script.
</p>

<h2>Ingen local storage, ingen fingeraftryk</h2>
<p>
  Vi anvender hverken <code>localStorage</code> eller <code>sessionStorage</code>, ingen pixels,
  ingen fingerprinting-teknikker og ingen genkendelse på tværs af enheder. Alle skrifttyper, stilarter,
  scripts og billeder ligger på vores egen server; ved sidevisning oprettes ingen forbindelse til
  tredjeparter.
</p>

<h2>Slet eller blokér cookies</h2>
<p>
  Du kan til enhver tid slette eller blokere cookies i dine browserindstillinger. Blokerer du
  sessionscookien, fungerer taske, kasse og sælgerlogin ikke længere — så mangler den tekniske tråd,
  der forbinder dine skridt. Sprog og Vault-adgang kan blokeres uden problemer; så spørger vi om
  sproget igen, og den tidlige adgang bortfalder.
</p>
<p class="doc__related">
  Udførligt om databehandlingen:
  <a href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>.
</p>
