<?php
/**
 * Sælgerbetingelser — dansk (vejledende oversættelse; den tyske tekst er bindende).
 * Provisionssatserne kommer fra konfigurationen via legal/verkaeufer.php, så aftaleteksten
 * og den fakturerede sats aldrig kan afvige fra hinanden.
 */
?>
<nav class="doc__toc">
  <a href="#v1">1. Genstand</a>
  <a href="#v2">2. Sælgerkonto og tilmelding</a>
  <a href="#v3">3. Erhvervsdrivende eller privat</a>
  <a href="#v4">4. Tilbud og kontrol</a>
  <a href="#v5">5. Forbudte varer</a>
  <a href="#v6">6. Provision</a>
  <a href="#v7">7. Betaling og udbetaling</a>
  <a href="#v8">8. Forsendelse og returneringer</a>
  <a href="#v9">9. Premium Outlet</a>
  <a href="#v10">10. Forpligtelser efter Digital Services Act</a>
  <a href="#v11">11. Rettigheder til indhold</a>
  <a href="#v12">12. Spærring og opsigelse</a>
  <a href="#v13">13. Ansvar og skadesløsholdelse</a>
  <a href="#v14">14. Afsluttende bestemmelser</a>
</nav>

<h2 id="v1">1. Genstand</h2>
<p>
  1.1 Disse betingelser regulerer forholdet mellem <?= h($co) ?> som operatør af markedspladsen
  <?= h($brand) ?> og personer, der udbyder varer via platformen („Sælgere“).
</p>
<p>
  1.2 Operatøren stiller salgsfladen, betalingsafviklingen via Stripe og ordreadministrationen til
  rådighed. Købsaftalen om de udbudte varer indgås udelukkende mellem Sælgeren og køberen;
  Operatøren bliver ikke part i aftalen.
</p>
<p>
  1.3 Operatøren er berettiget til at modtage købernes betalinger i Sælgerens navn med frigørende
  virkning og til på Sælgerens vegne at modtage og videresende købernes erklæringer vedrørende
  købsaftalen (navnlig fortrydelse og reklamationer over mangler).
</p>

<h2 id="v2">2. Sælgerkonto og tilmelding</h2>
<p>
  2.1 Tilmeldingen sker online. Oplysningerne skal være sande, fuldstændige og aktuelle. Ændringer —
  navnlig af firmanavn, adresse, skattenummer eller sælgertype — skal straks opdateres.
</p>
<p>
  2.2 Adgangsoplysninger skal holdes hemmelige. Sælgeren hæfter for handlinger, der foretages via
  dennes konto, i det omfang Sælgeren har handlet culpøst.
</p>
<p>
  2.3 Inden det første tilbud aktiveres, skal verifikationen hos Stripe (Stripe Connect) være
  gennemført. Uden gennemført verifikation kan der ikke ske udbetaling; tilbud forbliver i så fald
  offline.
</p>

<h2 id="v3">3. Erhvervsdrivende eller privat</h2>
<p>
  3.1 Ved tilmeldingen skal det angives, om der sælges som erhvervsdrivende („Forhandler“) eller som
  privatperson („Privat sælger“). Denne oplysning vises for køberne på produktsiden og under
  bestillingen og afgør, hvilke forbrugerrettigheder der gælder.
</p>
<p>
  3.2 Den, der sælger planmæssigt, gentagne gange og med gevinst for øje, handler erhvervsmæssigt —
  uanset egen vurdering. Den korrekte klassificering samt alle skatte-, nærings- og
  handelsretlige forpligtelser er Sælgerens ansvar.
</p>
<p>
  3.3 Operatøren er berettiget til efter forudgående varsel at omklassificere en konto til
  „Forhandler“ eller at spærre den, hvis den faktiske salgsaktivitet har erhvervsmæssig karakter.
  Afgørende er navnlig antallet af tilbud, omsætningens størrelse og regelmæssigheden.
</p>
<p>
  3.4 Forhandlere er forpligtet til at give forbrugerne den lovbestemte fortrydelsesret, at udstede
  behørige fakturaer og at opfylde det lovbestemte mangelsansvar.
</p>

<h2 id="v4">4. Tilbud og kontrol</h2>
<p>
  4.1 Tilbud skal være korrekte, fuldstændige og aktuelle: mærke, modelbetegnelse, størrelse, stand,
  pris inklusive moms samt mindst ét eget, uredigeret billede af den vare, der faktisk er på lager.
</p>
<p>
  4.2 Ved brugte varer skal brugsspor beskrives. Manglende eller forskønnende oplysninger er for
  Sælgerens regning.
</p>
<p>
  4.3 Hvert nyt eller ændret tilbud kontrolleres inden aktivering. Kontrollen omfatter plausibilitet,
  billedkvalitet og pris; Operatøren kan kræve oprindelsesdokumentation (købsbilag). Der er ikke
  krav på aktivering.
</p>
<p>
  4.4 Sælgeren opbevarer købsbilag i mindst tilbuddets varighed plus to år og fremlægger dem på
  anmodning inden for fem arbejdsdage.
</p>
<p>
  4.5 Sælgeren sikrer, at udbudte varer er tilgængelige. Gentagen manglende levering efter salg
  berettiger Operatøren til at spærre kontoen.
</p>

<h2 id="v5">5. Forbudte varer</h2>
<p>Følgende må navnlig ikke udbydes:</p>
<ul>
  <li>forfalskninger, replikaer, „dupes“ samt varer med fjernede eller ændrede mærkninger;</li>
  <li>varer uden sporbar oprindelse eller fra ulovlig kilde;</li>
  <li>varer, der første gang er bragt i omsætning uden for EØS, medmindre mærkeindehaveren har
      givet samtykke til videresalg inden for EØS;</li>
  <li>prøver uden salgsfrigivelse („not for resale“), medarbejdervarer med videresalgsforbud;</li>
  <li>varer, der strider mod regler om produktsikkerhed, mærkning eller tekstilmærkning;</li>
  <li>pels og eksotisk læder uden den nødvendige dokumentation (CITES).</li>
</ul>
<p>
  Overtrædelser medfører øjeblikkelig fjernelse af tilbuddet og som hovedregel opsigelse af
  sælgerkontoen.
</p>

<h2 id="v6">6. Provision</h2>
<p>6.1 For hvert salg, der formidles via platformen, opkræves en provision:</p>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Sælgertype</th><th>Provision</th><th>Premium Outlet (Vault)</th></tr></thead>
  <tbody>
    <tr><td>Forhandler</td><td><?= h($feeB) ?> % + <?= h($fixed) ?> pr. solgt vare</td><td><?= h($feeV) ?> %</td></tr>
    <tr><td>Privat sælger</td><td><?= h($feeP) ?> % + <?= h($fixed) ?> pr. solgt vare</td><td><?= h($feeV) ?> %</td></tr>
  </tbody>
</table></div>
<p>
  6.2 Beregningsgrundlaget er bruttosalgsprisen for Sælgerens varer. Forsendelsesomkostninger indgår
  ikke i beregningsgrundlaget og tilfalder Operatøren, som bærer forsendelsesomkostningerne over for
  transportøren.
</p>
<p>
  6.3 Der opkræves ingen oprettelses-, annonce- eller månedsgebyrer.
</p>
<p>
  6.4 Provisionen tilbageholdes automatisk ved køberens betaling. Ved fuldstændig tilbageførsel
  (fortrydelse, ophævelse, manglende levering) refunderes provisionen, bortset fra det faste beløb
  efter punkt 6.1, hvis tilbageførslen er forårsaget af Sælgeren.
</p>
<p>
  6.5 Ændringer af provisionen varsles pr. e-mail mindst 30 dage i forvejen. Gør Sælgeren ikke
  indsigelse inden ikrafttrædelsen, anses den nye provision for aftalt; retten til opsigelse efter
  punkt 12 berøres ikke.
</p>

<h2 id="v7">7. Betaling og udbetaling</h2>
<p>
  7.1 Betalingsafviklingen sker via Stripe. Sælgeren indgår til dette formål sin egen aftale med
  Stripe (Stripe Connected Account Agreement) og accepterer dennes betingelser.
</p>
<p>
  7.2 Afhængigt af ordrens sammensætning udbetales der enten direkte til Sælgerens konto (betaling
  med videreførsel), eller beløbet modtages først på platformens konto og overføres derefter til
  Sælgeren som en særskilt overførsel. I begge tilfælde modtager Sælgeren bruttosalgsprisen for sine
  varer med fradrag af provision.
</p>
<p>
  7.3 Udbetalingsrytmen til bankkontoen følger Stripes regler. Operatøren opbevarer ingen
  kundemidler og skylder ingen forrentning.
</p>
<p>
  7.4 Er Stripe-verifikationen endnu ikke gennemført, forbliver Sælgerens andel på platformens konto,
  indtil verifikationen er gennemført. Gennemføres verifikationen ikke inden for 180 dage, kan
  Operatøren annullere de berørte ordrer og refundere køberne.
</p>
<p>
  7.5 Operatøren må tilbageholde eller modregne udbetalinger, i det omfang der består begrundede
  krav mod Sælgeren — navnlig som følge af refusioner til købere, tilbageførsler (chargebacks) eller
  overtrædelser af punkt 5. Tilbageholdelsen begrundes og er begrænset til kravenes størrelse.
</p>

<h2 id="v8">8. Forsendelse og returneringer</h2>
<p>
  8.1 Sælgeren afsender inden for to arbejdsdage efter modtagelse af betalingen, forsikret og med
  sporing, og registrerer straks forsendelsesoplysningerne.
</p>
<p>
  8.2 Sælgeren modtager returneringer på den adresse, som denne har angivet. Forhandlere refunderer
  fortrydelser rettidigt; sker der ingen refusion, er Operatøren berettiget til at refundere køberen
  og modregne beløbet i Sælgerens udbetalinger.
</p>
<p>
  8.3 Ved private sælgere er der ingen lovbestemt fortrydelsesret. Afviger varen imidlertid
  væsentligt fra beskrivelsen, eller er den ikke ægte, er Sælgeren forpligtet til at tage den tilbage
  og refundere.
</p>

<h2 id="v9">9. Premium Outlet</h2>
<p>
  9.1 Sælgeren kan stille varer til rådighed for Vault. Åbningspris, mindstepris og nedsættelsesplan
  aftales inden åbningen og ændres ikke derefter.
</p>
<p>
  9.2 Sælgeren anerkender, at salget kan komme i stand til enhver pris mellem åbnings- og
  mindsteprisen, og at tilbagekaldelse af deltagelsen ikke er mulig efter partiets åbning, så længe
  partiet løber.
</p>
<p>
  9.3 Mindsteprisen underskrides aldrig. Sælges et parti ikke inden udløbet, lukkes det og kan på ny
  udbydes på almindelig vis.
</p>

<h2 id="v10">10. Forpligtelser efter Digital Services Act</h2>
<p>
  10.1 Erhvervsdrivende Sælgere stiller de oplysninger til rådighed, der kræves efter art. 30 DSA:
  navn, adresse, telefonnummer, e-mailadresse, handelsregister- eller tilsvarende identifikation og
  momsregistreringsnummer, såfremt det findes. Operatøren kontrollerer disse oplysninger med rimelige
  midler og kan tage tilbuddet offline, indtil forholdet er afklaret.
</p>
<p>
  10.2 Sælgeren indestår for, at dennes tilbud overholder reglerne om produktsikkerhed og mærkning,
  og at Sælgeren råder over de nødvendige tilladelser.
</p>
<p>
  10.3 Anmeldelser af ulovligt indhold behandles efter art. 16 DSA. Berørte Sælgere informeres med
  begrundelse om fjernelser og kan gøre indsigelse
  (<a href="<?= h(vr_url('legal/streitbeilegung.php')) ?>"><?= te('legal_disputes') ?></a>).
</p>

<h2 id="v11">11. Rettigheder til indhold</h2>
<p>
  11.1 Sælgeren indrømmer Operatøren en simpel, geografisk ubegrænset, vederlagsfri ret til at
  anvende, bearbejde (beskæring, farvejustering, størrelsestilpasning) og mangfoldiggøre de
  indsendte tekster og billeder til drift og markedsføring af platformen.
</p>
<p>
  11.2 Brugsretten består ud over tilbuddets ophør, i det omfang den tjener til dokumentation af
  gennemførte salg.
</p>
<p>
  11.3 Sælgeren indestår for at råde over alle nødvendige rettigheder til det indsendte indhold og
  ikke at krænke tredjemands rettigheder.
</p>

<h2 id="v12">12. Spærring og opsigelse</h2>
<p>
  12.1 Begge parter kan til enhver tid opsige sælgerforholdet i tekstform med et varsel på 14 dage.
  Igangværende ordrer skal fortsat afvikles fuldt ud.
</p>
<p>
  12.2 Operatøren kan straks fjerne tilbud og spærre kontoen ved overtrædelse af punkt 5, ved
  urigtige oplysninger om sælgerstatus, ved gentagen manglende levering eller ved begrundet mistanke
  om forfalskninger. Spærringen begrundes.
</p>
<p>
  12.3 Efter ophør anvises forfaldne udbetalinger efter udløbet af retur- og tilbageførselsfristerne,
  senest 90 dage efter den sidste ordre.
</p>

<h2 id="v13">13. Ansvar og skadesløsholdelse</h2>
<p>
  13.1 Operatøren hæfter ubegrænset for forsæt og grov uagtsomhed, ved skade på liv, legeme og
  helbred samt ved påtagelse af en garanti. Ved simpel uagtsom tilsidesættelse af væsentlige
  aftaleforpligtelser er ansvaret begrænset til den aftaletypiske, forudsigelige skade; i øvrigt er
  det udelukket.
</p>
<p>
  13.2 Sælgeren holder Operatøren skadesløs for krav fra tredjemand, der beror på en overtrædelse af
  disse betingelser — navnlig efter varemærke-, ophavs- eller konkurrenceretten samt ved
  overtrædelser af forbrugerbeskyttelsesregler. Skadesløsholdelsen omfatter rimelige omkostninger til
  retslig forsvar.
</p>
<p>
  13.3 Der gives ingen garanti for omsætning eller succes. Tilbuddenes synlighed, placering og
  sortering fastlægges af Operatøren.
</p>

<h2 id="v14">14. Afsluttende bestemmelser</h2>
<p>
  14.1 Tysk ret finder anvendelse. Er Sælgeren erhvervsdrivende, er værnetinget Operatørens
  hjemsted, i det omfang loven tillader det.
</p>
<p>
  14.2 Ændringer af disse betingelser meddeles pr. e-mail mindst 30 dage i forvejen. Aktuel
  version: <?= h(VR_TERMS_VERSION) ?>.
</p>
<p>
  14.3 Kontakt i alle sælgeranliggender:
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-mail]</em>' ?>.
</p>

<div class="doc__box">
  <strong>Bemærkning om denne oversættelse</strong>
  <p style="margin-top:8px">
    Denne danske tekst er en vejledende oversættelse af de tyske sælgerbetingelser, som alene er
    juridisk bindende, og som du accepterer ved tilmeldingen. Ved uoverensstemmelser har den tyske
    version forrang. Den udgør ikke juridisk rådgivning.
  </p>
</div>
