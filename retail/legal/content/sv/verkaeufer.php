<?php
/**
 * Säljarvillkor — svenska (vägledande översättning; den tyska texten är bindande).
 * Provisionssatserna kommer från konfigurationen via legal/verkaeufer.php så att
 * avtalstexten och den fakturerade satsen aldrig kan glida isär.
 */
?>
<nav class="doc__toc">
  <a href="#v1">1. Föremål</a>
  <a href="#v2">2. Säljarkonto och registrering</a>
  <a href="#v3">3. Näringsidkare eller privatperson</a>
  <a href="#v4">4. Erbjudanden och granskning</a>
  <a href="#v5">5. Förbjudna varor</a>
  <a href="#v6">6. Provision</a>
  <a href="#v7">7. Betalning och utbetalning</a>
  <a href="#v8">8. Frakt och returer</a>
  <a href="#v9">9. Premium Outlet</a>
  <a href="#v10">10. Skyldigheter enligt Digital Services Act</a>
  <a href="#v11">11. Rättigheter till innehåll</a>
  <a href="#v12">12. Spärrning och uppsägning</a>
  <a href="#v13">13. Ansvar och skadeslöshet</a>
  <a href="#v14">14. Slutbestämmelser</a>
</nav>

<h2 id="v1">1. Föremål</h2>
<p>
  1.1 Dessa villkor reglerar förhållandet mellan <?= h($co) ?> som operatör av marknadsplatsen
  <?= h($brand) ?> och personer som erbjuder varor via plattformen (”Säljare”).
</p>
<p>
  1.2 Operatören tillhandahåller försäljningsytan, betalningshanteringen via Stripe och
  orderhanteringen. Köpeavtalet om den erbjudna varan ingås uteslutande mellan Säljaren och
  köparen; Operatören blir inte avtalspart.
</p>
<p>
  1.3 Operatören har rätt att med befriande verkan ta emot köparnas betalningar i Säljarens namn
  samt att för Säljarens räkning ta emot och vidarebefordra köparnas förklaringar rörande
  köpeavtalet (särskilt ånger och reklamationer).
</p>

<h2 id="v2">2. Säljarkonto och registrering</h2>
<p>
  2.1 Registreringen sker online. Uppgifterna ska vara sanningsenliga, fullständiga och aktuella.
  Ändringar — särskilt av företagsnamn, adress, skattenummer eller säljartyp — ska uppdateras utan
  dröjsmål.
</p>
<p>
  2.2 Inloggningsuppgifter ska hållas hemliga. Säljaren ansvarar för åtgärder som vidtas via dennes
  konto i den mån Säljaren är vållande.
</p>
<p>
  2.3 Innan det första erbjudandet aktiveras måste verifieringen hos Stripe (Stripe Connect) vara
  slutförd. Utan slutförd verifiering kan ingen utbetalning ske; erbjudandena förblir i så fall
  offline.
</p>

<h2 id="v3">3. Näringsidkare eller privatperson</h2>
<p>
  3.1 Vid registreringen ska anges om försäljningen sker som näringsidkare (”Återförsäljare”) eller
  som privatperson (”Privat säljare”). Denna uppgift visas för köparna på produktsidan och under
  beställningen och avgör vilka konsumenträttigheter som gäller.
</p>
<p>
  3.2 Den som säljer planmässigt, upprepat och i vinstsyfte handlar som näringsidkare — oavsett
  egen bedömning. Den korrekta klassificeringen samt samtliga skatte-, närings- och
  handelsrättsliga skyldigheter är Säljarens ansvar.
</p>
<p>
  3.3 Operatören har rätt att efter föregående meddelande omklassificera ett konto till
  ”Återförsäljare” eller spärra det om den faktiska försäljningsverksamheten har näringskaraktär.
  Måttstock är särskilt antalet erbjudanden, omsättningens storlek och regelbundenheten.
</p>
<p>
  3.4 Återförsäljare är skyldiga att ge konsumenter den lagstadgade ångerrätten, att utfärda korrekta
  fakturor och att fullgöra det lagstadgade felansvaret.
</p>

<h2 id="v4">4. Erbjudanden och granskning</h2>
<p>
  4.1 Erbjudanden ska vara korrekta, fullständiga och aktuella: märke, modellbeteckning, storlek,
  skick, pris inklusive moms samt minst en egen, oredigerad bild av den vara som faktiskt finns.
</p>
<p>
  4.2 För begagnade varor ska bruksspår beskrivas. Saknade eller förskönande uppgifter går ut över
  Säljaren.
</p>
<p>
  4.3 Varje nytt eller ändrat erbjudande granskas före aktivering. Granskningen omfattar rimlighet,
  bildkvalitet och pris; Operatören kan begära ursprungsbevis (inköpskvitton). Rätt till aktivering
  föreligger inte.
</p>
<p>
  4.4 Säljaren bevarar inköpskvitton minst under erbjudandets löptid plus två år och visar upp dem på
  begäran inom fem arbetsdagar.
</p>
<p>
  4.5 Säljaren säkerställer att erbjudna varor finns tillgängliga. Upprepad utebliven leverans efter
  försäljning ger Operatören rätt att spärra kontot.
</p>

<h2 id="v5">5. Förbjudna varor</h2>
<p>Följande får i synnerhet inte erbjudas:</p>
<ul>
  <li>förfalskningar, repliker, ”dupes” samt varor med avlägsnade eller ändrade märkningar;</li>
  <li>varor utan spårbart ursprung eller från olaglig källa;</li>
  <li>varor som för första gången släppts ut på marknaden utanför EES, om varumärkesinnehavaren inte
      har samtyckt till vidareförsäljning inom EES;</li>
  <li>prover utan säljtillstånd (”not for resale”), personalvaror med vidareförsäljningsförbud;</li>
  <li>varor som strider mot bestämmelser om produktsäkerhet, märkning eller textilmärkning;</li>
  <li>päls och exotiskt läder utan erforderliga intyg (CITES).</li>
</ul>
<p>
  Överträdelser leder till att erbjudandet omedelbart tas bort och i regel till att säljarkontot
  sägs upp.
</p>

<h2 id="v6">6. Provision</h2>
<p>6.1 För varje försäljning som förmedlas via plattformen utgår en provision:</p>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Säljartyp</th><th>Provision</th><th>Premium Outlet (Vault)</th></tr></thead>
  <tbody>
    <tr><td>Återförsäljare</td><td><?= h($feeB) ?> % + <?= h($fixed) ?> per sålt plagg</td><td><?= h($feeV) ?> %</td></tr>
    <tr><td>Privat säljare</td><td><?= h($feeP) ?> % + <?= h($fixed) ?> per sålt plagg</td><td><?= h($feeV) ?> %</td></tr>
  </tbody>
</table></div>
<p>
  6.2 Beräkningsunderlag är bruttoförsäljningspriset för Säljarens varor. Fraktkostnader ingår inte
  i beräkningsunderlaget och tillfaller Operatören, som bär fraktkostnaderna gentemot
  transportören.
</p>
<p>
  6.3 Inga inläggnings-, listnings- eller månadsavgifter tas ut.
</p>
<p>
  6.4 Provisionen dras automatiskt vid köparens betalning. Vid fullständig återgång (ånger, hävning,
  utebliven leverans) återbetalas provisionen, med undantag för det fasta beloppet enligt punkt 6.1
  om återgången orsakats av Säljaren.
</p>
<p>
  6.5 Ändringar av provisionen meddelas per e-post minst 30 dagar i förväg. Om Säljaren inte
  invänder innan ändringen träder i kraft anses den nya provisionen avtalad; rätten till uppsägning
  enligt punkt 12 påverkas inte.
</p>

<h2 id="v7">7. Betalning och utbetalning</h2>
<p>
  7.1 Betalningshanteringen sker via Stripe. Säljaren ingår för detta ändamål ett eget avtal med
  Stripe (Stripe Connected Account Agreement) och godtar dess villkor.
</p>
<p>
  7.2 Beroende på beställningens sammansättning sker utbetalningen antingen direkt till Säljarens
  konto (betalning med vidarebefordran), eller så tas beloppet först emot på plattformskontot och
  överförs därefter till Säljaren som en separat överföring. I båda fallen erhåller Säljaren
  bruttoförsäljningspriset för sina varor med avdrag för provision.
</p>
<p>
  7.3 Utbetalningsrytmen till bankkontot följer Stripes regler. Operatören förvarar inga kundmedel
  och är inte skyldig att betala ränta.
</p>
<p>
  7.4 Om Stripe-verifieringen ännu inte är slutförd ligger Säljarens andel kvar på plattformskontot
  tills verifieringen har slutförts. Om verifieringen inte slutförs inom 180 dagar kan Operatören
  annullera de berörda beställningarna och återbetala köparna.
</p>
<p>
  7.5 Operatören får hålla inne eller kvitta utbetalningar i den mån det finns grundade anspråk mot
  Säljaren — särskilt till följd av återbetalningar till köpare, återdebiteringar (chargebacks) eller
  överträdelser av punkt 5. Innehållandet motiveras och begränsas till anspråkens belopp.
</p>

<h2 id="v8">8. Frakt och returer</h2>
<p>
  8.1 Säljaren skickar varan inom två arbetsdagar efter att betalningen inkommit, försäkrad och med
  spårning, och registrerar försändelseuppgifterna utan dröjsmål.
</p>
<p>
  8.2 Säljaren tar emot returer på den adress som Säljaren angett. Återförsäljare återbetalar vid
  ånger inom fristen; sker ingen återbetalning har Operatören rätt att återbetala köparen och kvitta
  beloppet mot Säljarens utbetalningar.
</p>
<p>
  8.3 För privata säljare finns ingen lagstadgad ångerrätt. Om varan emellertid väsentligt avviker
  från beskrivningen eller inte är äkta, är Säljaren skyldig att ta tillbaka varan och återbetala.
</p>

<h2 id="v9">9. Premium Outlet</h2>
<p>
  9.1 Säljaren kan ställa varor till förfogande för Vault. Öppningspris, lägstapris och
  sänkningsplan avtalas före öppningen och ändras inte därefter.
</p>
<p>
  9.2 Säljaren godtar att försäljningen kan komma till stånd till varje pris mellan öppningspriset
  och lägstapriset, och att deltagandet inte kan återkallas efter att partiet öppnats så länge
  partiet pågår.
</p>
<p>
  9.3 Lägstapriset underskrids aldrig. Om ett parti inte har sålts när det löper ut stängs det och
  kan på nytt erbjudas på vanligt sätt.
</p>

<h2 id="v10">10. Skyldigheter enligt Digital Services Act</h2>
<p>
  10.1 Näringsidkande säljare tillhandahåller de uppgifter som krävs enligt art. 30 DSA: namn,
  adress, telefonnummer, e-postadress, handelsregisternummer eller jämförbar identifierare samt
  momsregistreringsnummer i förekommande fall. Operatören kontrollerar dessa uppgifter med rimliga
  medel och får ta erbjudandet offline tills saken klarlagts.
</p>
<p>
  10.2 Säljaren försäkrar att dennes erbjudanden uppfyller bestämmelserna om produktsäkerhet och
  märkning och att Säljaren innehar erforderliga tillstånd.
</p>
<p>
  10.3 Anmälningar om olagligt innehåll handläggs enligt art. 16 DSA. Berörda säljare informeras med
  motivering om borttagningar och kan invända
  (<a href="<?= h(vr_url('legal/streitbeilegung.php')) ?>"><?= te('legal_disputes') ?></a>).
</p>

<h2 id="v11">11. Rättigheter till innehåll</h2>
<p>
  11.1 Säljaren upplåter till Operatören en enkel, geografiskt obegränsad och kostnadsfri rätt att
  använda, bearbeta (beskärning, färgjustering, storleksanpassning) och mångfaldiga de publicerade
  texterna och bilderna för plattformens drift och marknadsföring.
</p>
<p>
  11.2 Nyttjanderätten består efter erbjudandets slut i den mån den tjänar dokumentationen av
  genomförda försäljningar.
</p>
<p>
  11.3 Säljaren försäkrar att denne innehar alla erforderliga rättigheter till det publicerade
  innehållet och inte kränker någon tredje parts rättigheter.
</p>

<h2 id="v12">12. Spärrning och uppsägning</h2>
<p>
  12.1 Båda parter kan när som helst säga upp säljarförhållandet i textform med 14 dagars
  uppsägningstid. Pågående beställningar ska fortfarande fullgöras i sin helhet.
</p>
<p>
  12.2 Operatören kan omedelbart ta bort erbjudanden och spärra kontot vid överträdelse av punkt 5,
  vid oriktiga uppgifter om säljarstatus, vid upprepad utebliven leverans eller vid grundad misstanke
  om förfalskningar. Spärrningen motiveras.
</p>
<p>
  12.3 Efter avtalets upphörande anvisas förfallna utbetalningar efter utgången av retur- och
  återdebiteringsfristerna, senast 90 dagar efter den sista beställningen.
</p>

<h2 id="v13">13. Ansvar och skadeslöshet</h2>
<p>
  13.1 Operatören ansvarar obegränsat vid uppsåt och grov vårdslöshet, vid skada på liv, kropp och
  hälsa samt i den omfattning garanti lämnats. Vid ringa vårdslöst åsidosättande av väsentliga
  avtalsförpliktelser är ansvaret begränsat till den förutsebara, för avtalet typiska skadan; i
  övrigt är ansvaret uteslutet.
</p>
<p>
  13.2 Säljaren håller Operatören skadeslös från tredje parts anspråk som grundar sig på en
  överträdelse av dessa villkor — särskilt enligt varumärkes-, upphovs- eller konkurrensrätt samt
  till följd av överträdelser av konsumentskyddsregler. Skadeslöshetsåtagandet omfattar skäliga
  kostnader för rättsligt försvar.
</p>
<p>
  13.3 Ingen garanti för omsättning eller framgång lämnas. Erbjudandenas synlighet, placering och
  sortering bestäms av Operatören.
</p>

<h2 id="v14">14. Slutbestämmelser</h2>
<p>
  14.1 Tysk rätt gäller. Är Säljaren näringsidkare är behörig domstol den vid Operatörens säte, i
  den mån lagen tillåter.
</p>
<p>
  14.2 Ändringar av dessa villkor meddelas per e-post minst 30 dagar i förväg. Aktuell version:
  <?= h(VR_TERMS_VERSION) ?>.
</p>
<p>
  14.3 Kontakt för alla säljarärenden:
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-post]</em>' ?>.
</p>

<div class="doc__box">
  <strong>Anmärkning om denna översättning</strong>
  <p style="margin-top:8px">
    Denna svenska text är en vägledande översättning av de tyska säljarvillkoren, som ensamma är
    juridiskt bindande och som du godkänner vid registreringen. Vid avvikelser gäller den tyska
    versionen. Den utgör inte juridisk rådgivning.
  </p>
</div>
