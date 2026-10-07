<?php
/**
 * Retourneren — Nederlands (vertaling ter informatie). Variabelen: legal/rueckgabe.php.
 */
?>
<h2>In één oogopslag</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Verkoper</th><th>Termijn</th><th>Kosten retourzending</th></tr></thead>
  <tbody>
    <tr><td><?= h((string)vr_config('brand')) ?>-voorraad</td>
        <td><?= (int)$days ?> dagen (wettelijk <?= (int)$wd ?> + vrijwillige verlenging)</td>
        <td>binnen de wettelijke termijn gratis vanuit Duitsland</td></tr>
    <tr><td>Handelaar</td>
        <td><?= (int)$wd ?> dagen wettelijke herroeping; veel handelaren geven meer</td>
        <td>binnen de wettelijke termijn gratis vanuit Duitsland</td></tr>
    <tr><td>Particuliere verkoper</td>
        <td>geen wettelijk herroepingsrecht</td>
        <td>terugname alleen bij afwijking van de beschrijving</td></tr>
  </tbody>
</table></div>

<h2>Zo stuurt u terug</h2>
<ol>
  <li>Schrijf naar
      <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-mail]</em>' ?>
      met uw bestelnummer en de artikelen die terug moeten.</li>
  <li>U ontvangt binnen één werkdag een retourlabel en het retouradres van de betreffende
      verkoper.</li>
  <li>Verpak het artikel bij voorkeur in de originele doos, voeg de pakbon bij en geef het pakket
      af.</li>
  <li>Na ontvangst en controle betalen wij terug op hetzelfde betaalmiddel — uiterlijk 14 dagen na
      ontvangst van uw herroepingsverklaring, zodra het artikel bij ons is of u de verzending hebt
      aangetoond.</li>
</ol>
<p>
  Een retourzending zonder voorafgaande aankondiging nemen wij ook aan; de verwerking duurt dan
  langer, omdat de toewijzing handmatig gebeurt.
</p>

<h2>Staat van de retourzending</h2>
<p>
  Passen mag uitdrukkelijk — daar is het retourrecht precies voor. Stuur artikelen ongedragen,
  ongewassen, ongeparfumeerd en met alle originele labels terug. Voor een waardevermindering door
  gebruik dat verder gaat, kunnen wij een vergoeding vragen; wij berekenen die transparant en nemen
  vooraf contact met u op.
</p>

<h2>Wat niet kan worden teruggenomen</h2>
<ul>
  <li>badmode en oorsieraden zonder intact hygiënezegel;</li>
  <li>individueel aangepaste of op uw aanwijzingen gemaakte artikelen;</li>
  <li>artikelen van particuliere verkopers, voor zover ze overeenkomen met de beschrijving.</li>
</ul>

<h2>Ruilen</h2>
<p>
  Direct ruilen is niet mogelijk, omdat de meeste artikelen unieke stukken of losse maten zijn.
  Stuur het artikel terug en bestel de juiste maat opnieuw — als die nog beschikbaar is. Heeft u
  interesse in een bepaalde maat, schrijf ons dan; wij laten weten of aanvulling te verwachten is.
</p>

<h2>Artikel defect of niet zoals beschreven</h2>
<p>
  Dan geldt niet het retourrecht maar de wettelijke garantie — met betere rechten voor u. Neem
  contact op met foto's; de retourzending is in dat geval altijd gratis, ook bij particuliere
  verkopers en ook na afloop van de retourtermijn binnen de wettelijke verjaring.
</p>

<h2>Geen origineel</h2>
<p>
  Mocht een artikel niet origineel blijken, dan betalen wij de volledige koopprijs inclusief
  verzendkosten terug en nemen wij de retourzending voor onze rekening — ongeacht welke verkoper het
  heeft aangeboden. De betrokken verkoper wordt van het platform verwijderd.
</p>

<h2>Premium Outlet</h2>
<p>
  Verlaagde prijzen veranderen niets aan uw rechten: voor Vault-aankopen gelden dezelfde termijnen
  als hierboven. Alleen de uitzondering voor particuliere verkopers blijft bestaan.
</p>

<p class="doc__related">
  De juridische tekst met het modelformulier voor herroeping:
  <a href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a>.
</p>
