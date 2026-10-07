<?php
/**
 * Kolofon (§ 5 DDG, § 18 MStV) — dansk (vejledende oversættelse). Variabler: legal/impressum.php.
 */
?>
<h2>Udbyder</h2>
<?php vr_company_block(); ?>

<h2>Markedspladsens operatør</h2>
<p>
  <?= h((string)vr_config('brand')) ?> er en online markedsplads, der drives af
  <?= h((string)($c['legal_name'] ?? '')) ?>. Via platformen udbydes både operatørens egne varer og
  varer fra tredjeparter (erhvervsdrivende forhandlere og private sælgere). Hvem der er aftalepart i
  købet, vises på hver produktside og under bestillingen, inden ordren afgives.
</p>

<h2>Ansvarlig for indholdet</h2>
<p>
  Ansvarlig efter § 18, stk. 2, MStV er den ovenfor nævnte tegningsberettigede person, adresse som
  ovenfor.
</p>

<h2>Kontakt for forbrugerhenvendelser</h2>
<p>
  Henvendelser om ordrer, returneringer og klager bedes rettet til
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-mailadresse]</em>' ?>.
  Vi svarer som regel inden for én arbejdsdag. Ved spørgsmål om en vare fra en tredjepartssælger
  formidler vi kontakten til den pågældende sælger eller videresender din henvendelse.
</p>

<h2>Forbrugertvistbilæggelse</h2>
<p>
  Vi er hverken forpligtet eller villige til at deltage i tvistbilæggelsesprocedurer ved et
  forbrugerklagenævn. Det udelukker ikke en mindelig løsning med os — henvend dig først direkte til
  os. Yderligere oplysninger under
  <a href="<?= h(vr_url('legal/streitbeilegung.php')) ?>"><?= te('legal_disputes') ?></a>.
</p>

<h2>Betalingsudbyder</h2>
<p>
  Betalinger afvikles via Stripe (Stripe Payments Europe, Limited, 1 Grand Canal Street Lower, Grand
  Canal Dock, Dublin, Irland). Udbetalinger til tredjepartssælgere sker via Stripe Connect. Kortdata
  behandles udelukkende hos Stripe og når ikke vores systemer.
</p>

<h2>Ansvar for indhold</h2>
<p>
  Som tjenesteudbyder er vi ansvarlige for eget indhold på disse sider efter de almindelige love. For
  tilbud fra tredjepartssælgere er vi ikke forpligtet til at overvåge overført eller lagret
  fremmed information eller at undersøge omstændigheder, der tyder på ulovlig aktivitet (art. 6 og
  8 Digital Services Act). Så snart vi får kendskab til en konkret retskrænkelse, fjerner vi det
  pågældende indhold straks. Anmeldelser sendes til
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-mailadresse]</em>' ?>.
</p>
<p>
  Vi kontrollerer tilbud fra tredjepartssælgere for plausibilitet inden offentliggørelse og kræver
  dokumentation for oprindelse. Denne frivillige kontrol udgør ingen garanti for lovligheden eller
  ægtheden af hvert enkelt tilbud og berører ikke vores ansvarsprivilegium som hostingudbyder.
</p>

<h2>Ansvar for links</h2>
<p>
  Vores tilbud indeholder links til eksterne websites fra tredjeparter, hvis indhold vi ikke har
  indflydelse på. For dette indhold er den pågældende udbyder altid ansvarlig. På tidspunktet for
  linkningen var intet ulovligt indhold synligt.
</p>

<h2>Ophavsret</h2>
<p>
  Det indhold og de værker, operatøren har skabt på disse sider, er beskyttet af ophavsretten.
  Produktbilleder og -beskrivelser fra tredjepartssælgere leveres af disse; de garanterer over for
  os, at de råder over de nødvendige rettigheder. Mærke- og produktbetegnelser tilhører de respektive
  rettighedshavere. Deres nævnelse tjener alene til beskrivelse af de udbudte varer og etablerer
  ingen handelsrelation til mærkeindehaverne.
</p>

<h2>Bemærkning om varemærkerettigheder</h2>
<p>
  <?= h((string)vr_config('brand')) ?> er ikke autoriseret forhandler af de nævnte mærker, medmindre
  andet udtrykkeligt er angivet. De udbudte varer er originalvarer, der første gang er bragt i
  omsætning inden for Det Europæiske Økonomiske Samarbejdsområde; videresalg er dermed tilladt
  efter princippet om konsumption af varemærkeretten (§ 24 MarkenG, art. 15 EUTMR).
</p>
