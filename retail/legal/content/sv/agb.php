<?php
/**
 * Köpvillkor — svenska (vägledande översättning; den tyska texten är bindande).
 * Variabler: legal/agb.php. Numreringen följer originalet stycke för stycke så
 * att hänvisningar („punkt 10.3“) stämmer.
 */
?>
<nav class="doc__toc">
  <a href="#s1">1. Tillämpningsområde och avtalsparter</a>
  <a href="#s2">2. Plattformens roll</a>
  <a href="#s3">3. Avtalets ingående</a>
  <a href="#s4">4. Priser och fraktkostnader</a>
  <a href="#s5">5. Betalning</a>
  <a href="#s6">6. Leverans</a>
  <a href="#s7">7. Äganderättsförbehåll</a>
  <a href="#s8">8. Ångerrätt och frivillig returrätt</a>
  <a href="#s9">9. Felansvar</a>
  <a href="#s10">10. Köp från privata säljare</a>
  <a href="#s11">11. Premium Outlet (Vault)</a>
  <a href="#s12">12. Äkthet och ursprung</a>
  <a href="#s13">13. Ansvar</a>
  <a href="#s14">14. Rabattkoder</a>
  <a href="#s15">15. Dataskydd</a>
  <a href="#s16">16. Slutbestämmelser</a>
</nav>

<h2 id="s1">1. Tillämpningsområde och avtalsparter</h2>
<p>
  1.1 Dessa köpvillkor gäller för alla beställningar som görs via
  <?= h($brand) ?> (<a href="<?= h(vr_origin()) ?>"><?= h(vr_origin()) ?></a>).
  Plattformen drivs av <?= h($co) ?> (nedan „Operatören“, „vi“).
</p>
<p>
  1.2 På plattformen förekommer tre typer av erbjudanden, var och en märkt på produktsidan:
</p>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Märkning</th><th>Säljare</th><th>Din avtalspart i köpet</th></tr></thead>
  <tbody>
    <tr><td><?= h($brand) ?>-lager</td><td>Operatören själv</td><td><?= h($co) ?></td></tr>
    <tr><td>Återförsäljare</td><td>näringsidkande tredjepartssäljare</td><td>respektive återförsäljare</td></tr>
    <tr><td>Privat säljare</td><td>privatperson</td><td>respektive privatperson</td></tr>
  </tbody>
</table></div>
<p>
  1.3 Vid erbjudanden från tredjepartssäljare ingås köpeavtalet uteslutande mellan dig och
  respektive säljare. Operatören blir inte part i köpeavtalet. För användningen av själva
  plattformen — betalningshantering, orderöversikt, förmedling — gäller dessa villkor mellan dig
  och Operatören.
</p>
<p>
  1.4 Konsument är varje fysisk person som ingår en rättshandling för ändamål som huvudsakligen
  faller utanför näringsverksamhet (§ 13 BGB, tyska civillagen). Avvikande villkor från kunden blir
  inte del av avtalet om vi inte uttryckligen godkänner dem skriftligen.
</p>

<h2 id="s2">2. Plattformens roll</h2>
<p>
  2.1 Operatören tillhandahåller den tekniska infrastrukturen, granskar erbjudanden från
  tredjepartssäljare avseende rimlighet och ursprungsbevis före publicering, hanterar betalningen
  via betaltjänstleverantören Stripe och vidarebefordrar säljarens andel till säljaren efter avdrag
  för provision.
</p>
<p>
  2.2 Operatören är av tredjepartssäljarna bemyndigad att ta emot köparens betalningar med
  befriande verkan. Din betalningsskyldighet gentemot säljaren är fullgjord när betalningen via
  plattformen har genomförts.
</p>
<p>
  2.3 Förklaringar rörande köpeavtalet — särskilt ånger, reklamation och hävning — kan med giltig
  verkan riktas till
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '[e-post]' ?>.
  Vi vidarebefordrar dem utan dröjsmål till berörd säljare och hjälper till med hanteringen.
</p>

<h2 id="s3">3. Avtalets ingående</h2>
<p>
  3.1 Presentationen av varorna på plattformen utgör inget rättsligt bindande anbud utan en
  uppmaning att beställa.
</p>
<p>
  3.2 Genom att klicka på beställningsknappen („<?= te('checkout_go') ?>“ följt av betalning hos
  Stripe) lämnar du ett bindande anbud om köp av varorna i din väska. Dessförinnan kan du granska
  och rätta dina uppgifter på kassasidan.
</p>
<p>
  3.3 Vi bekräftar mottagandet av din beställning omedelbart per e-post. Denna mottagningsbekräftelse
  utgör ännu inte en accept. Köpeavtalet ingås när vi eller säljaren förklarar accept eller skickar
  varan — senast med orderbekräftelsen, om denna uttryckligen förklarar accept.
</p>
<p>
  3.4 Om avtalet inte kommer till stånd, t.ex. för att varan inte längre finns tillgänglig efter
  beställningen, informerar vi dig utan dröjsmål och återbetalar redan gjorda betalningar fullt ut.
</p>
<p>
  3.5 Avtalstexten sparas och skickas till dig med orderbekräftelsen i textform (e-post),
  inklusive dessa villkor och ångerrättsinformationen.
</p>

<h2 id="s4">4. Priser och fraktkostnader</h2>
<p>
  4.1 Alla angivna priser är slutpriser i euro och inkluderar lagstadgad moms om för närvarande
  <?= h($vat) ?> %, såvida respektive säljare är momspliktig. För privata säljare anges ingen moms
  (§ 19 UStG, tyska momslagen, eller försäljning av icke-näringsidkare).
</p>
<p>
  4.2 Utöver varupriserna tillkommer fraktkostnader. Dessa visas separat och med exakt belopp på
  kassasidan innan beställningen läggs. Detaljer under
  <a href="<?= h(vr_url('legal/versand.php')) ?>"><?= te('legal_shipping') ?></a>.
</p>
<p>
  4.3 Om din beställning innehåller varor från flera säljare debiteras fraktkostnaden endast en
  gång; försändelserna kan anlända separat.
</p>
<p>
  4.4 Vid leverans till länder utanför EU kan dessutom tull, importmoms och hanteringsavgifter
  tillkomma, vilka bärs av mottagaren.
</p>

<h2 id="s5">5. Betalning</h2>
<p>
  5.1 Betalningen sker via betaltjänstleverantören Stripe (Stripe Payments Europe, Limited, Dublin,
  Irland). Tillgängliga betalsätt visas under beställningen; en översikt finns under
  <a href="<?= h(vr_url('legal/zahlung.php')) ?>"><?= te('legal_payment') ?></a>.
</p>
<p>
  5.2 Köpeskillingen förfaller till betalning vid avtalets ingående. Vid betalsätt med fördröjd
  avräkning (t.ex. SEPA-autogiro, Klarna) skickar vi efter att betaltjänstleverantören frigjort
  betalningen.
</p>
<p>
  5.3 Betalningsuppgifter, särskilt kortuppgifter, behandlas uteslutande av
  betaltjänstleverantören. Operatören tar inte emot och lagrar inga fullständiga kortuppgifter.
</p>
<p>
  5.4 Vid återdebiteringar som du ansvarar för har vi rätt att debitera de kostnader som uppstår,
  om du vållat återdebiteringen.
</p>

<h2 id="s6">6. Leverans</h2>
<p>
  6.1 Leverans sker till den leveransadress du angett. Leveranstider och destinationer anges under
  <a href="<?= h(vr_url('legal/versand.php')) ?>"><?= te('legal_shipping') ?></a> och börjar löpa när
  betalningen frigjorts.
</p>
<p>
  6.2 Varor från tredjepartssäljare skickas av respektive säljare. Du får separat spårning för varje
  försändelse.
</p>
<p>
  6.3 Om varan undantagsvis inte kan levereras trots att den visades som tillgänglig på
  plattformen, informerar vi dig utan dröjsmål och återbetalar det betalda beloppet fullt ut. Rätt
  till efterleverans av en jämförbar vara föreligger inte, eftersom det ofta rör sig om unika
  exemplar.
</p>
<p>
  6.4 För konsumenter övergår risken för varans förstörelse eller försämring först när varan
  överlämnas till dig, även om försändelsen sker via en transportör (§ 475 st. 2 BGB).
</p>

<h2 id="s7">7. Äganderättsförbehåll</h2>
<p>
  Varan förblir respektive säljares egendom tills den är fullt betald.
</p>

<h2 id="s8">8. Ångerrätt och frivillig returrätt</h2>
<p>
  8.1 Konsumenter har vid avtal med näringsidkande säljare en lagstadgad ångerrätt om
  <?= (int)$wd ?> dagar. Den fullständiga informationen med standardångerblankett finns under
  <a href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a>.
</p>
<p>
  8.2 Utöver den lagstadgade ångerrätten ger vi för egna varor en frivillig returrätt om
  <?= (int)$days ?> dagar från mottagandet. Villkor: varan är oanvänd, oskadad och har alla
  originaletiketter kvar. Den frivilliga returrätten begränsar inte dina lagstadgade rättigheter.
  Kostnaden för returfrakt inom den frivilliga förlängningen bärs av köparen; detaljer under
  <a href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a>.
</p>
<p>
  8.3 Vid köp från privata säljare finns ingen lagstadgad ångerrätt (se punkt 10).
</p>

<h2 id="s9">9. Felansvar</h2>
<p>
  9.1 För näringsidkande säljare gäller det lagstadgade felansvaret enligt §§ 434 ff. BGB. För
  konsumenter är preskriptionstiden två år från mottagandet av varan.
</p>
<p>
  9.2 För begagnade varor kan preskriptionstiden gentemot konsumenter förkortas till ett år om detta
  uttryckligen och separat avtalats före avtalets ingående. En sådan upplysning visas i
  förekommande fall på produktsidan.
</p>
<p>
  9.3 Bruksspår som anges i varubeskrivningen utgör inte fel. Avvikelser i färgåtergivning på grund
  av skärmvisning är inte fel.
</p>
<p>
  9.4 Transportskador ber vi dig anmäla inom 14 dagar med foton. Vi hanterar dessa ärenden
  oberoende av frågan om ångerrätt, även hos privata säljare.
</p>

<h2 id="s10">10. Köp från privata säljare</h2>
<p>
  10.1 Erbjudanden från privatpersoner är på produktsidan, i väskan och under beställningen märkta
  som „<?= te('seller_private') ?>“. Innan du slutför beställningen måste du uttryckligen bekräfta
  de särskilda följderna.
</p>
<p>
  10.2 Eftersom säljaren inte är näringsidkare finns ingen lagstadgad ångerrätt. Det lagstadgade
  felansvaret kan giltigt uteslutas eller begränsas av den private säljaren; en sådan uteslutning
  gäller inte vid svek eller uppsåtligt oriktiga uppgifter.
</p>
<p>
  10.3 Oavsett detta gäller: Om den levererade varan väsentligt avviker från beskrivningen eller
  inte är äkta, återbetalar vi hela köpeskillingen inklusive fraktkostnader. Vi håller i dessa fall
  inne säljarens andel eller kräver den tillbaka.
</p>
<p>
  10.4 Privata säljare som i själva verket säljer planmässigt, upprepat och i vinstsyfte handlar
  som näringsidkare. Om vi konstaterar detta omklassificerar eller spärrar vi kontot; i så fall
  gäller rättigheterna gentemot näringsidkare för redan ingångna avtal.
</p>

<h2 id="s11">11. Premium Outlet (Vault)</h2>
<p>
  11.1 I området „Premium Outlet“ (Vault) erbjuds varje plagg som ett enskilt parti med ett
  öppningspris, ett lägstapris och en publicerad sänkningsplan. Priset sjunker i
  <?= (int)$steps ?> lika stora steg, ett steg var <?= (int)$hours ?>:e timme, ned till lägstapriset.
</p>
<p>
  11.2 Planen fastställs när partiet öppnas och ändras inte därefter. Priset kan inte stiga.
  Beräkningen sker på servern utifrån planen och är identisk för alla besökare; ingen
  personaliserad prissättning förekommer.
</p>
<p>
  11.3 Avgörande är det pris som visas när partiet läggs i väskan och som reserveras för dig under
  beställningsförloppet (20 minuter). När reservationen löper ut frigörs partiet igen.
</p>
<p>
  11.4 Varje parti finns bara en gång. Det avslutas med det första giltiga köpet. Rätt att förvärva
  ett parti på ett senare, lägre steg föreligger inte.
</p>
<p>
  11.5 Vid prissänkningar anger vi enligt § 11 PAngV (tyska prisinformationsförordningen) det
  lägsta priset under de senaste 30 dagarna. Eftersom Vault-priset enbart sjunker är detta det pris
  som gällde omedelbart före det aktuella steget. I första steget föreligger ingen prissänkning;
  där annonseras inget referenspris.
</p>
<p>
  11.6 Dina lagstadgade rättigheter, särskilt ångerrätt och felansvar, gäller oförändrat i Vault.
  Punkt 10 gäller fortsatt för privata säljare.
</p>
<p>
  11.7 Medlemmar med bekräftad e-postregistrering får tillgång till nya partier före det allmänna
  släppet. Medlemskapet är kostnadsfritt och kan när som helst återkallas; rätt till förhandstillgång
  föreligger inte.
</p>
<p>
  11.8 En prisbevakning och att spara ett plagg på önskelistan reserverar det <strong>inte</strong>
  och ger ingen förköpsrätt. Meddelandet skickas en gång och utan garanti för leverans eller
  tidpunkt; avgörande är enbart tillgängligheten i beställningsögonblicket.
</p>

<h2 id="s12">12. Äkthet och ursprung</h2>
<p>
  12.1 Endast originalvaror erbjuds. Säljare är skyldiga att bevara inköpskvitton för sina varor
  och på begäran visa upp dem för oss.
</p>
<p>
  12.2 Om det efter köpet visar sig att en vara inte är äkta återbetalar vi hela köpeskillingen
  plus fraktkostnader och står för returfrakten. Rätten gäller oavsett typ av säljare.
</p>
<p>
  12.3 Anspråk enligt punkt 12.2 förutsätter att du ger oss tillgång till varan och din reklamation
  inom 30 dagar efter mottagandet och låter oss undersöka den.
</p>

<h2 id="s13">13. Ansvar</h2>
<p>
  13.1 Vi ansvarar obegränsat vid uppsåt och grov vårdslöshet, vid skada på liv, kropp och hälsa,
  enligt bestämmelserna i den tyska produktansvarslagen samt i den omfattning vi lämnat garanti.
</p>
<p>
  13.2 Vid ringa vårdslöst åsidosättande av en väsentlig avtalsförpliktelse är ansvaret begränsat
  till den förutsebara, för avtalet typiska skadan. I övrigt är ansvaret uteslutet.
</p>
<p>
  13.3 För tredjepartssäljares avtalsbrott ansvarar vi inte; vårt ansvar följer i detta avseende
  reglerna om värdtjänster (art. 6 Digital Services Act). Punkterna 10.3 och 12.2 berörs inte.
</p>
<p>
  13.4 Ingen garanti lämnas för plattformens oavbrutna tillgänglighet.
</p>

<h2 id="s14">14. Rabattkoder</h2>
<p>
  14.1 Kampanjkoder (koder som inte köpts utan utfärdats inom ramen för en kampanj) kan endast lösas
  in under angiven period och endast en gång. Efter utgången är inlösen utesluten; ingen
  förlängning sker.
</p>
<p>
  14.2 Kodens värde avräknas mot varuvärdet, inte mot fraktkostnader. Kontantutbetalning, ränta
  eller tillgodohavande för restvärde är uteslutet.
</p>
<p>
  14.3 Om en kod är knuten till en e-postadress eller uttryckligen märkt som välkomst- eller
  förstaköpskod kan endast innehavaren av den adressen lösa in den, och endast för den första
  betalda beställningen. Överlåtelse till tredje part eller vidareförsäljning är utesluten.
</p>
<p>
  14.4 Flera koder kan inte kombineras om inte annat anges i villkoren för respektive kod. Ett
  lägsta ordervärde gäller om det anges vid koden; avgörande är varuvärdet exklusive
  fraktkostnader.
</p>
<p>
  14.5 Ångrar du en beställning helt eller delvis återbetalar vi det faktiskt betalda beloppet. En
  inlöst kampanjkod återuppstår inte; rätt till utfärdande av en ny kod föreligger inte.
</p>
<p>
  14.6 Vid skälig misstanke om missbruk — särskilt flera skapade konton för upprepad användning av
  förstaköpskoder — kan vi spärra enskilda koder.
</p>

<h2 id="s15">15. Dataskydd</h2>
<p>
  Information om behandlingen av dina personuppgifter finns i
  <a href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>.
  För att hantera din beställning lämnar vi de uppgifter som krävs för frakt och fakturering till
  respektive säljare.
</p>

<h2 id="s16">16. Slutbestämmelser</h2>
<p>
  16.1 Förbundsrepubliken Tysklands lag gäller. För konsumenter med hemvist i en annan stat berörs
  inte de tvingande konsumentskyddsreglerna i deras vistelsestat (art. 6.2 Rom I-förordningen).
</p>
<p>
  16.2 Uppfyllelseort och behörig domstol följer lagens bestämmelser. För konsumenter gäller de
  lagstadgade forumreglerna.
</p>
<p>
  16.3 Skulle enskilda bestämmelser i dessa villkor vara ogiltiga påverkas inte giltigheten av
  övriga bestämmelser. I den ogiltiga bestämmelsens ställe träder lagens regel.
</p>
<p>
  16.4 Vi förbehåller oss rätten att ändra dessa villkor med verkan för framtiden. För redan
  ingångna avtal gäller den version som var tillgänglig vid avtalets ingående; den godkända
  versionen sparas med din beställning (aktuell version: <?= h(VR_TERMS_VERSION) ?>).
</p>

<div class="doc__box">
  <strong>Anmärkning om denna översättning</strong>
  <p style="margin-top:8px">
    Denna svenska text är en vägledande översättning av de tyska köpvillkoren, som ensamma är
    juridiskt bindande. Den låter dig läsa vad du godkänner; vid avvikelser gäller den tyska
    versionen. Den utgör inte juridisk rådgivning.
  </p>
</div>
