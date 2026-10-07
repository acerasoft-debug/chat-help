<?php
/**
 * Salgsbetingelser — dansk (vejledende oversættelse; den tyske tekst er
 * bindende). Variabler: legal/agb.php. Nummereringen følger originalen afsnit
 * for afsnit, så henvisninger („punkt 10.3“) passer.
 */
?>
<nav class="doc__toc">
  <a href="#s1">1. Anvendelsesområde og aftaleparter</a>
  <a href="#s2">2. Platformens rolle</a>
  <a href="#s3">3. Aftaleindgåelse</a>
  <a href="#s4">4. Priser og forsendelsesomkostninger</a>
  <a href="#s5">5. Betaling</a>
  <a href="#s6">6. Levering</a>
  <a href="#s7">7. Ejendomsforbehold</a>
  <a href="#s8">8. Fortrydelsesret og frivillig returret</a>
  <a href="#s9">9. Mangelsansvar</a>
  <a href="#s10">10. Køb hos private sælgere</a>
  <a href="#s11">11. Premium Outlet (Vault)</a>
  <a href="#s12">12. Ægthed og oprindelse</a>
  <a href="#s13">13. Ansvar</a>
  <a href="#s14">14. Rabatkoder</a>
  <a href="#s15">15. Databeskyttelse</a>
  <a href="#s16">16. Afsluttende bestemmelser</a>
</nav>

<h2 id="s1">1. Anvendelsesområde og aftaleparter</h2>
<p>
  1.1 Disse salgsbetingelser gælder for alle ordrer, der afgives via
  <?= h($brand) ?> (<a href="<?= h(vr_origin()) ?>"><?= h(vr_origin()) ?></a>).
  Platformen drives af <?= h($co) ?> (herefter „Operatøren“, „vi“).
</p>
<p>
  1.2 På platformen findes tre typer tilbud, hver især markeret på produktsiden:
</p>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Mærkning</th><th>Sælger</th><th>Din aftalepart i købet</th></tr></thead>
  <tbody>
    <tr><td><?= h($brand) ?>-lager</td><td>Operatøren selv</td><td><?= h($co) ?></td></tr>
    <tr><td>Forhandler</td><td>erhvervsdrivende tredjepartssælger</td><td>den pågældende forhandler</td></tr>
    <tr><td>Privat sælger</td><td>privatperson</td><td>den pågældende privatperson</td></tr>
  </tbody>
</table></div>
<p>
  1.3 Ved tilbud fra tredjepartssælgere indgås købsaftalen udelukkende mellem dig og den
  pågældende sælger. Operatøren bliver ikke part i købsaftalen. For brugen af selve platformen —
  betalingsafvikling, ordreoversigt, formidling — gælder disse betingelser mellem dig og Operatøren.
</p>
<p>
  1.4 En forbruger er enhver fysisk person, der indgår en retshandel til formål, som overvejende
  ligger uden for vedkommendes erhvervsmæssige virksomhed (§ 13 BGB, den tyske borgerlige lovbog).
  Afvigende betingelser fra kunden bliver ikke en del af aftalen, medmindre vi udtrykkeligt
  accepterer dem skriftligt.
</p>

<h2 id="s2">2. Platformens rolle</h2>
<p>
  2.1 Operatøren stiller den tekniske infrastruktur til rådighed, kontrollerer tilbud fra
  tredjepartssælgere for plausibilitet og oprindelsesdokumentation inden offentliggørelse, afvikler
  betalingen via betalingsudbyderen Stripe og videresender sælgerens andel til sælgeren efter fradrag
  af provision.
</p>
<p>
  2.2 Operatøren er af tredjepartssælgerne bemyndiget til at modtage køberens betalinger med
  frigørende virkning. Din betalingsforpligtelse over for sælgeren er opfyldt ved gennemført
  betaling via platformen.
</p>
<p>
  2.3 Erklæringer vedrørende købsaftalen — navnlig fortrydelse, reklamation og ophævelse — kan med
  gyldig virkning rettes til
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '[e-mail]' ?>.
  Vi videresender dem straks til den berørte sælger og hjælper med afviklingen.
</p>

<h2 id="s3">3. Aftaleindgåelse</h2>
<p>
  3.1 Præsentationen af varerne på platformen udgør ikke et juridisk bindende tilbud, men en
  opfordring til at bestille.
</p>
<p>
  3.2 Ved at klikke på bestillingsknappen („<?= te('checkout_go') ?>“ efterfulgt af betaling hos
  Stripe) afgiver du et bindende tilbud om køb af varerne i din taske. Forinden kan du kontrollere
  og rette dine indtastninger på kassesiden.
</p>
<p>
  3.3 Vi bekræfter modtagelsen af din ordre straks pr. e-mail. Denne modtagelsesbekræftelse udgør
  endnu ikke en accept. Købsaftalen indgås, når vi eller sælgeren erklærer accept eller afsender
  varen — senest med ordrebekræftelsen, såfremt denne udtrykkeligt erklærer accept.
</p>
<p>
  3.4 Kommer aftalen ikke i stand, f.eks. fordi varen ikke længere er tilgængelig efter
  bestillingen, informerer vi dig straks og refunderer allerede foretagne betalinger fuldt ud.
</p>
<p>
  3.5 Aftaleteksten gemmes og sendes til dig sammen med ordrebekræftelsen i tekstform (e-mail),
  inklusive disse betingelser og fortrydelsesvejledningen.
</p>

<h2 id="s4">4. Priser og forsendelsesomkostninger</h2>
<p>
  4.1 Alle angivne priser er slutpriser i euro og inkluderer den lovpligtige moms på aktuelt
  <?= h($vat) ?> %, såfremt den pågældende sælger er momspligtig. Ved private sælgere angives ingen
  moms (§ 19 UStG, den tyske momslov, eller salg fra ikke-erhvervsdrivende).
</p>
<p>
  4.2 Ud over varepriserne påløber forsendelsesomkostninger. Disse vises særskilt og med nøjagtigt
  beløb på kassesiden, inden ordren afgives. Detaljer under
  <a href="<?= h(vr_url('legal/versand.php')) ?>"><?= te('legal_shipping') ?></a>.
</p>
<p>
  4.3 Indeholder din ordre varer fra flere sælgere, beregnes forsendelsesomkostningerne kun én gang;
  forsendelserne kan ankomme hver for sig.
</p>
<p>
  4.4 Ved levering til lande uden for EU kan der desuden påløbe told, importmoms og
  ekspeditionsgebyrer, som bæres af modtageren.
</p>

<h2 id="s5">5. Betaling</h2>
<p>
  5.1 Betalingen sker via betalingsudbyderen Stripe (Stripe Payments Europe, Limited, Dublin,
  Irland). De tilgængelige betalingsmetoder vises under bestillingen; en oversigt findes under
  <a href="<?= h(vr_url('legal/zahlung.php')) ?>"><?= te('legal_payment') ?></a>.
</p>
<p>
  5.2 Købesummen forfalder ved aftaleindgåelsen. Ved betalingsmetoder med forsinket afvikling
  (f.eks. SEPA-direkte debitering, Klarna) afsender vi efter betalingsudbyderens frigivelse af
  betalingen.
</p>
<p>
  5.3 Betalingsdata, navnlig kortdata, behandles udelukkende af betalingsudbyderen. Operatøren
  modtager og gemmer ingen fuldstændige kortdata.
</p>
<p>
  5.4 Ved tilbageførsler, som du er ansvarlig for, er vi berettiget til at opkræve de omkostninger,
  der derved opstår, såfremt du culpøst har foranlediget tilbageførslen.
</p>

<h2 id="s6">6. Levering</h2>
<p>
  6.1 Levering sker til den af dig angivne leveringsadresse. Leveringstider og destinationer er
  angivet under <a href="<?= h(vr_url('legal/versand.php')) ?>"><?= te('legal_shipping') ?></a> og
  begynder ved frigivelse af betalingen.
</p>
<p>
  6.2 Varer fra tredjepartssælgere afsendes af den pågældende sælger. Du modtager en separat
  sporing for hver forsendelse.
</p>
<p>
  6.3 Kan varen undtagelsesvis ikke leveres, selvom den blev vist som tilgængelig på platformen,
  informerer vi dig straks og refunderer det betalte beløb fuldt ud. Der er ikke krav på
  efterlevering af en tilsvarende vare, da det ofte drejer sig om enkeltstykker.
</p>
<p>
  6.4 For forbrugere overgår risikoen for hændelig undergang og hændelig forringelse først ved
  overdragelsen af varen til dig, også selvom forsendelsen sker via en transportør (§ 475, stk. 2,
  BGB).
</p>

<h2 id="s7">7. Ejendomsforbehold</h2>
<p>
  Varen forbliver den pågældende sælgers ejendom, indtil den er fuldt betalt.
</p>

<h2 id="s8">8. Fortrydelsesret og frivillig returret</h2>
<p>
  8.1 Forbrugere har ved aftaler med erhvervsdrivende sælgere en lovbestemt fortrydelsesret på
  <?= (int)$wd ?> dage. Den fuldstændige vejledning med standardfortrydelsesformular findes under
  <a href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a>.
</p>
<p>
  8.2 Ud over den lovbestemte fortrydelsesret giver vi for egne varer en frivillig returret på
  <?= (int)$days ?> dage fra modtagelsen. Betingelse: varen er ubrugt, ubeskadiget og med alle
  originale mærker. Den frivillige returret begrænser ikke dine lovbestemte rettigheder.
  Omkostningerne ved returforsendelse inden for den frivillige forlængelse bæres af køberen;
  detaljer under <a href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a>.
</p>
<p>
  8.3 Ved køb hos private sælgere er der ingen lovbestemt fortrydelsesret (se punkt 10).
</p>

<h2 id="s9">9. Mangelsansvar</h2>
<p>
  9.1 Ved erhvervsdrivende sælgere gælder det lovbestemte mangelsansvar efter §§ 434 ff. BGB. For
  forbrugere er forældelsesfristen to år fra modtagelsen af varen.
</p>
<p>
  9.2 Ved brugte varer kan forældelsesfristen over for forbrugere være forkortet til et år, hvis
  dette udtrykkeligt og særskilt er aftalt inden aftaleindgåelsen. En sådan oplysning vises i givet
  fald på produktsiden.
</p>
<p>
  9.3 Brugsspor, der er nævnt i varebeskrivelsen, udgør ikke en mangel. Afvigelser i farvegengivelse
  på grund af skærmvisning er ikke en mangel.
</p>
<p>
  9.4 Transportskader bedes meddelt os inden 14 dage med fotos. Vi behandler disse sager uafhængigt
  af spørgsmålet om fortrydelsesret, også ved private sælgere.
</p>

<h2 id="s10">10. Køb hos private sælgere</h2>
<p>
  10.1 Tilbud fra privatpersoner er på produktsiden, i tasken og under bestillingen markeret som
  „<?= te('seller_private') ?>“. Inden du afslutter ordren, skal du udtrykkeligt bekræfte de
  særlige konsekvenser.
</p>
<p>
  10.2 Da sælgeren ikke er erhvervsdrivende, er der ingen lovbestemt fortrydelsesret. Det
  lovbestemte mangelsansvar kan gyldigt udelukkes eller begrænses af den private sælger; en sådan
  udelukkelse gælder ikke ved svig eller forsætligt urigtige oplysninger.
</p>
<p>
  10.3 Uanset dette gælder: Afviger den leverede vare væsentligt fra beskrivelsen, eller er den
  ikke ægte, refunderer vi den fulde købesum inklusive forsendelsesomkostninger. Vi tilbageholder
  eller kræver i disse tilfælde sælgerens andel tilbage.
</p>
<p>
  10.4 Private sælgere, der reelt sælger planmæssigt, gentagne gange og med gevinst for øje,
  handler erhvervsmæssigt. Konstaterer vi dette, omklassificerer eller spærrer vi kontoen; i så fald
  gælder rettighederne over for erhvervsdrivende for allerede indgåede aftaler.
</p>

<h2 id="s11">11. Premium Outlet (Vault)</h2>
<p>
  11.1 I området „Premium Outlet“ (Vault) udbydes hvert stykke som et enkelt parti med en
  åbningspris, en mindstepris og en offentliggjort nedsættelsesplan. Prisen falder i
  <?= (int)$steps ?> lige store trin, ét trin hver <?= (int)$hours ?>. time, ned til mindsteprisen.
</p>
<p>
  11.2 Planen fastlægges ved partiets åbning og ændres ikke derefter. Prisen kan ikke stige.
  Beregningen sker på serveren ud fra planen og er identisk for alle besøgende; der finder ingen
  personaliseret prisdannelse sted.
</p>
<p>
  11.3 Afgørende er den pris, der vises på tidspunktet for tilføjelse til tasken, og som
  reserveres til dig i bestillingsforløbets varighed (20 minutter). Efter reservationens udløb
  frigives partiet igen.
</p>
<p>
  11.4 Hvert parti findes kun én gang. Det afsluttes med det første gyldige køb. Der er ikke krav
  på at erhverve et parti på et senere, lavere trin.
</p>
<p>
  11.5 Ved prisnedsættelser angiver vi i henhold til § 11 PAngV (den tyske prisangivelsesforordning)
  den laveste pris i de seneste 30 dage. Da Vault-prisen udelukkende falder, er dette den pris, der
  gjaldt umiddelbart før det aktuelle trin. På første trin foreligger ingen prisnedsættelse; der
  annonceres ingen referencepris.
</p>
<p>
  11.6 Dine lovbestemte rettigheder, navnlig fortrydelsesret og mangelsansvar, gælder uændret i
  Vault. Punkt 10 finder fortsat anvendelse på private sælgere.
</p>
<p>
  11.7 Medlemmer med bekræftet e-mailtilmelding får adgang til nye partier før den almindelige
  frigivelse. Medlemskabet er gratis og kan til enhver tid tilbagekaldes; der er ikke krav på
  forhåndsadgang.
</p>
<p>
  11.8 En prisalarm og det at gemme et stykke på ønskelisten reserverer det <strong>ikke</strong> og
  giver ingen forkøbsret. Meddelelsen sendes én gang og uden garanti for levering eller tidspunkt;
  afgørende er alene tilgængeligheden i bestillingsøjeblikket.
</p>

<h2 id="s12">12. Ægthed og oprindelse</h2>
<p>
  12.1 Der udbydes udelukkende originalvarer. Sælgere er forpligtet til at opbevare købsbilag for
  deres varer og fremlægge dem for os på anmodning.
</p>
<p>
  12.2 Viser det sig efter købet, at en vare ikke er ægte, refunderer vi den fulde købesum plus
  forsendelsesomkostninger og afholder omkostningerne ved returforsendelsen. Kravet gælder uanset
  sælgertype.
</p>
<p>
  12.3 Krav efter punkt 12.2 forudsætter, at du stiller varen og din reklamation til rådighed for
  os inden 30 dage efter modtagelsen og giver os mulighed for at undersøge den.
</p>

<h2 id="s13">13. Ansvar</h2>
<p>
  13.1 Vi hæfter ubegrænset for forsæt og grov uagtsomhed, ved skade på liv, legeme og helbred,
  efter bestemmelserne i den tyske produktansvarslov samt i omfanget af en af os påtaget garanti.
</p>
<p>
  13.2 Ved simpel uagtsom tilsidesættelse af en væsentlig aftaleforpligtelse er ansvaret begrænset
  til den aftaletypiske, forudsigelige skade. I øvrigt er ansvaret udelukket.
</p>
<p>
  13.3 For tredjepartssælgeres misligholdelse af købsaftalen hæfter vi ikke; vores ansvar følger i
  så henseende reglerne om hostingtjenester (art. 6 Digital Services Act). Punkt 10.3 og 12.2
  berøres ikke heraf.
</p>
<p>
  13.4 Der gives ingen garanti for platformens uafbrudte tilgængelighed.
</p>

<h2 id="s14">14. Rabatkoder</h2>
<p>
  14.1 Kampagnekoder (koder, der ikke er købt, men udstedt som led i en kampagne) kan kun indløses i
  den angivne periode og kun én gang. Efter udløb er indløsning udelukket; der sker ingen
  forlængelse.
</p>
<p>
  14.2 Kodens værdi modregnes i vareværdien, ikke i forsendelsesomkostningerne. Kontant udbetaling,
  forrentning eller kreditering af restværdi er udelukket.
</p>
<p>
  14.3 Er en kode knyttet til en e-mailadresse eller udtrykkeligt markeret som velkomst- eller
  førsteordrekode, kan kun indehaveren af denne adresse indløse den og kun til den første betalte
  ordre. Overdragelse til tredjemand eller videresalg er udelukket.
</p>
<p>
  14.4 Flere koder kan ikke kombineres, medmindre andet fremgår af den pågældende kodes
  betingelser. En minimumsordreværdi gælder, hvis den er angivet ved koden; afgørende er
  vareværdien uden forsendelsesomkostninger.
</p>
<p>
  14.5 Fortryder du en ordre helt eller delvist, refunderer vi det faktisk betalte beløb. En indløst
  kampagnekode genopstår ikke; der er ikke krav på udstedelse af en ny kode.
</p>
<p>
  14.6 Ved begrundet mistanke om misbrug — navnlig flere oprettede konti til gentagen brug af
  førsteordrekoder — kan vi spærre enkelte koder.
</p>

<h2 id="s15">15. Databeskyttelse</h2>
<p>
  Oplysninger om behandlingen af dine personoplysninger findes i
  <a href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>.
  Til afvikling af din ordre videregiver vi de til forsendelse og fakturering nødvendige
  oplysninger til den pågældende sælger.
</p>

<h2 id="s16">16. Afsluttende bestemmelser</h2>
<p>
  16.1 Forbundsrepublikken Tysklands ret finder anvendelse. For forbrugere med bopæl i en anden
  stat berøres de ufravigelige forbrugerbeskyttelsesregler i deres opholdsstat ikke (art. 6, stk.
  2, Rom I-forordningen).
</p>
<p>
  16.2 Opfyldelsessted og værneting følger de lovbestemte regler. For forbrugere gælder de
  lovbestemte værneting.
</p>
<p>
  16.3 Skulle enkelte bestemmelser i disse betingelser være ugyldige, berøres gyldigheden af de
  øvrige bestemmelser ikke. I stedet for den ugyldige bestemmelse træder den lovbestemte regel.
</p>
<p>
  16.4 Vi forbeholder os ret til at ændre disse betingelser med virkning for fremtiden. For allerede
  indgåede aftaler gælder den version, der kunne hentes ved aftaleindgåelsen; den accepterede
  version gemmes sammen med din ordre (aktuel version: <?= h(VR_TERMS_VERSION) ?>).
</p>

<div class="doc__box">
  <strong>Bemærkning om denne oversættelse</strong>
  <p style="margin-top:8px">
    Denne danske tekst er en vejledende oversættelse af de tyske salgsbetingelser, som alene er
    juridisk bindende. Den gør det muligt at læse, hvad du accepterer; ved uoverensstemmelser har den
    tyske version forrang. Den udgør ikke juridisk rådgivning.
  </p>
</div>
