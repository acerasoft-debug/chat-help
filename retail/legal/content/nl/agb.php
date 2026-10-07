<?php
/**
 * Algemene verkoopvoorwaarden — Nederlands (vertaling ter informatie; de Duitse
 * tekst is bindend). Variabelen: legal/agb.php. De nummering volgt het origineel
 * alinea voor alinea, zodat verwijzingen („artikel 10.3”) kloppen.
 */
?>
<nav class="doc__toc">
  <a href="#s1">1. Toepassingsgebied en contractpartijen</a>
  <a href="#s2">2. Rol van het platform</a>
  <a href="#s3">3. Totstandkoming van de overeenkomst</a>
  <a href="#s4">4. Prijzen en verzendkosten</a>
  <a href="#s5">5. Betaling</a>
  <a href="#s6">6. Levering</a>
  <a href="#s7">7. Eigendomsvoorbehoud</a>
  <a href="#s8">8. Herroeping en vrijwillig retourrecht</a>
  <a href="#s9">9. Wettelijke garantie</a>
  <a href="#s10">10. Aankopen bij particuliere verkopers</a>
  <a href="#s11">11. Premium Outlet (Vault)</a>
  <a href="#s12">12. Echtheid en herkomst</a>
  <a href="#s13">13. Aansprakelijkheid</a>
  <a href="#s14">14. Kortingsbonnen</a>
  <a href="#s15">15. Gegevensbescherming</a>
  <a href="#s16">16. Slotbepalingen</a>
</nav>

<h2 id="s1">1. Toepassingsgebied en contractpartijen</h2>
<p>
  1.1 Deze algemene verkoopvoorwaarden gelden voor alle bestellingen die via
  <?= h($brand) ?> (<a href="<?= h(vr_origin()) ?>"><?= h(vr_origin()) ?></a>) worden geplaatst.
  Exploitant van het platform is <?= h($co) ?> (hierna „Exploitant”, „wij”).
</p>
<p>
  1.2 Op het platform worden drie soorten aanbiedingen gevoerd, telkens aangeduid op de
  productpagina:
</p>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Aanduiding</th><th>Verkoper</th><th>Uw wederpartij bij de koop</th></tr></thead>
  <tbody>
    <tr><td><?= h($brand) ?>-voorraad</td><td>de Exploitant zelf</td><td><?= h($co) ?></td></tr>
    <tr><td>Handelaar</td><td>zakelijke derde verkoper</td><td>de betreffende handelaar</td></tr>
    <tr><td>Particuliere verkoper</td><td>privépersoon</td><td>de betreffende privépersoon</td></tr>
  </tbody>
</table></div>
<p>
  1.3 Bij aanbiedingen van derde verkopers komt de koopovereenkomst uitsluitend tot stand tussen u
  en de betreffende verkoper. De Exploitant wordt geen partij bij de koopovereenkomst. Voor het
  gebruik van het platform zelf — betalingsafwikkeling, bestellingsoverzicht, bemiddeling — gelden
  deze voorwaarden tussen u en de Exploitant.
</p>
<p>
  1.4 Consument is iedere natuurlijke persoon die een rechtshandeling verricht voor doeleinden die
  overwegend buiten zijn bedrijfs- of beroepsactiviteit vallen (§ 13 BGB, Duits Burgerlijk Wetboek).
  Afwijkende voorwaarden van de klant maken geen deel uit van de overeenkomst, tenzij wij daar
  uitdrukkelijk schriftelijk mee instemmen.
</p>

<h2 id="s2">2. Rol van het platform</h2>
<p>
  2.1 De Exploitant stelt de technische infrastructuur ter beschikking, controleert aanbiedingen
  van derde verkopers vóór publicatie op plausibiliteit en herkomstbewijzen, wikkelt de betaling af
  via de betaaldienstverlener Stripe en maakt het verkopersaandeel na aftrek van de commissie over
  aan de verkoper.
</p>
<p>
  2.2 De Exploitant is door de derde verkopers gemachtigd om betalingen van de koper met bevrijdende
  werking in ontvangst te nemen. Uw betalingsverplichting jegens de verkoper is voldaan zodra de
  betaling via het platform is geslaagd.
</p>
<p>
  2.3 Verklaringen over de koopovereenkomst — met name herroeping, melding van gebreken en
  ontbinding — kunt u rechtsgeldig richten aan
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '[e-mail]' ?>.
  Wij sturen ze onverwijld door naar de betrokken verkoper en ondersteunen bij de afwikkeling.
</p>

<h2 id="s3">3. Totstandkoming van de overeenkomst</h2>
<p>
  3.1 De presentatie van de artikelen op het platform is geen juridisch bindend aanbod, maar een
  uitnodiging tot bestellen.
</p>
<p>
  3.2 Door op de bestelknop te klikken („<?= te('checkout_go') ?>” gevolgd door betaling bij
  Stripe) doet u een bindend aanbod tot aankoop van de artikelen in uw tas. Vooraf kunt u uw
  gegevens op de afrekenpagina controleren en corrigeren.
</p>
<p>
  3.3 Wij bevestigen de ontvangst van uw bestelling onverwijld per e-mail. Deze
  ontvangstbevestiging is nog geen aanvaarding. De koopovereenkomst komt tot stand wanneer wij of de
  verkoper de aanvaarding verklaren of de artikelen verzenden — uiterlijk met de
  bestelbevestiging, voor zover die de aanvaarding uitdrukkelijk verklaart.
</p>
<p>
  3.4 Komt de overeenkomst niet tot stand, bijvoorbeeld omdat het artikel na de bestelling niet meer
  beschikbaar is, dan informeren wij u onverwijld en betalen wij reeds gedane betalingen volledig
  terug.
</p>
<p>
  3.5 De tekst van de overeenkomst wordt opgeslagen en u met de bestelbevestiging in tekstvorm
  (e-mail) toegezonden, inclusief deze voorwaarden en de herroepingsinformatie.
</p>

<h2 id="s4">4. Prijzen en verzendkosten</h2>
<p>
  4.1 Alle vermelde prijzen zijn eindprijzen in euro, inclusief de wettelijke btw van momenteel
  <?= h($vat) ?> %, voor zover de betreffende verkoper btw-plichtig is. Bij particuliere verkopers
  wordt geen btw vermeld (§ 19 UStG, Duitse btw-wet, of verkoop door een niet-ondernemer).
</p>
<p>
  4.2 Naast de artikelprijzen worden verzendkosten in rekening gebracht. Deze worden op de
  afrekenpagina vóór het plaatsen van de bestelling afzonderlijk en op de cent nauwkeurig getoond.
  Details onder <a href="<?= h(vr_url('legal/versand.php')) ?>"><?= te('legal_shipping') ?></a>.
</p>
<p>
  4.3 Bevat uw bestelling artikelen van meerdere verkopers, dan worden de verzendkosten slechts
  eenmaal berekend; de pakketten kunnen afzonderlijk bij u aankomen.
</p>
<p>
  4.4 Bij leveringen naar landen buiten de EU kunnen bovendien invoerrechten, invoer-btw en
  afhandelingskosten verschuldigd zijn, die voor rekening van de ontvanger komen.
</p>

<h2 id="s5">5. Betaling</h2>
<p>
  5.1 De betaling verloopt via de betaaldienstverlener Stripe (Stripe Payments Europe, Limited,
  Dublin, Ierland). De beschikbare betaalmethoden worden tijdens het bestelproces getoond; een
  overzicht vindt u onder <a href="<?= h(vr_url('legal/zahlung.php')) ?>"><?= te('legal_payment') ?></a>.
</p>
<p>
  5.2 De koopprijs is opeisbaar bij het sluiten van de overeenkomst. Bij betaalmethoden met
  vertraagde afwikkeling (bijv. SEPA-incasso, Klarna) verzenden wij na vrijgave van de betaling door
  de betaaldienstverlener.
</p>
<p>
  5.3 Betaalgegevens, met name kaartgegevens, worden uitsluitend door de betaaldienstverlener
  verwerkt. De Exploitant ontvangt en bewaart geen volledige kaartgegevens.
</p>
<p>
  5.4 Bij terugboekingen die aan u toe te rekenen zijn, mogen wij de daardoor ontstane kosten in
  rekening brengen, voor zover u de terugboeking verwijtbaar hebt veroorzaakt.
</p>

<h2 id="s6">6. Levering</h2>
<p>
  6.1 De levering vindt plaats op het door u opgegeven leveringsadres. Levertijden en
  bestemmingsgebieden staan onder
  <a href="<?= h(vr_url('legal/versand.php')) ?>"><?= te('legal_shipping') ?></a> en gaan in bij
  vrijgave van de betaling.
</p>
<p>
  6.2 Artikelen van derde verkopers worden door de betreffende verkoper verzonden. U ontvangt per
  zending een eigen track &amp; trace.
</p>
<p>
  6.3 Is een artikel bij uitzondering niet leverbaar hoewel het op het platform als beschikbaar
  werd getoond, dan informeren wij u onverwijld en betalen wij het betaalde bedrag volledig terug.
  Er bestaat geen recht op nalevering van een vergelijkbaar artikel, aangezien het vaak om unieke
  stukken gaat.
</p>
<p>
  6.4 Bij consumenten gaat het risico van toevallig verlies en toevallige verslechtering pas over
  bij overhandiging van de artikelen aan u, ook als de verzending door een vervoerder plaatsvindt
  (§ 475 lid 2 BGB).
</p>

<h2 id="s7">7. Eigendomsvoorbehoud</h2>
<p>
  De artikelen blijven tot volledige betaling eigendom van de betreffende verkoper.
</p>

<h2 id="s8">8. Herroeping en vrijwillig retourrecht</h2>
<p>
  8.1 Consumenten hebben bij overeenkomsten met zakelijke verkopers een wettelijk herroepingsrecht
  van <?= (int)$wd ?> dagen. De volledige informatie met het modelformulier voor herroeping vindt u
  onder <a href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a>.
</p>
<p>
  8.2 Bovenop het wettelijke herroepingsrecht verlenen wij voor eigen artikelen een vrijwillig
  retourrecht van <?= (int)$days ?> dagen na ontvangst. Voorwaarde: het artikel is ongedragen,
  onbeschadigd en voorzien van alle originele labels. Het vrijwillige retourrecht beperkt uw
  wettelijke rechten niet. De kosten van de retourzending binnen de vrijwillige verlenging draagt
  de koper; details onder
  <a href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a>.
</p>
<p>
  8.3 Bij aankopen bij particuliere verkopers bestaat geen wettelijk herroepingsrecht (zie
  artikel 10).
</p>

<h2 id="s9">9. Wettelijke garantie</h2>
<p>
  9.1 Bij zakelijke verkopers geldt de wettelijke garantie volgens §§ 434 e.v. BGB. Voor
  consumenten bedraagt de verjaringstermijn twee jaar vanaf ontvangst van de artikelen.
</p>
<p>
  9.2 Bij gebruikte artikelen kan de verjaringstermijn jegens consumenten tot één jaar zijn verkort,
  indien dit vóór het sluiten van de overeenkomst uitdrukkelijk en afzonderlijk is overeengekomen.
  Een dergelijke vermelding staat in dat geval op de productpagina.
</p>
<p>
  9.3 Gebruikssporen die in de artikelbeschrijving zijn genoemd, vormen geen gebrek. Afwijkingen in
  kleurweergave door het beeldscherm zijn geen gebrek.
</p>
<p>
  9.4 Meld transportschade ons binnen 14 dagen met foto's. Wij handelen deze gevallen af
  onafhankelijk van de vraag naar het herroepingsrecht, ook bij particuliere verkopers.
</p>

<h2 id="s10">10. Aankopen bij particuliere verkopers</h2>
<p>
  10.1 Aanbiedingen van privépersonen zijn op de productpagina, in de tas en tijdens het
  bestelproces aangeduid als „<?= te('seller_private') ?>”. Vóór het afronden van de bestelling
  moet u de bijzondere gevolgen uitdrukkelijk bevestigen.
</p>
<p>
  10.2 Omdat de verkoper geen ondernemer is, bestaat er geen wettelijk herroepingsrecht. De
  wettelijke garantie kan door de particuliere verkoper rechtsgeldig worden uitgesloten of beperkt;
  een dergelijke uitsluiting geldt niet bij bedrog of opzettelijk onjuiste opgaven.
</p>
<p>
  10.3 Los daarvan geldt: wijkt het geleverde artikel wezenlijk af van de beschrijving of is het
  geen origineel, dan betalen wij de volledige koopprijs inclusief verzendkosten terug. In die
  gevallen houden wij het verkopersaandeel in of vorderen wij het terug.
</p>
<p>
  10.4 Particuliere verkopers die in werkelijkheid planmatig, herhaaldelijk en met winstoogmerk
  verkopen, handelen bedrijfsmatig. Stellen wij dit vast, dan herclassificeren of blokkeren wij het
  account; voor reeds gesloten overeenkomsten gelden dan de rechten jegens ondernemers.
</p>

<h2 id="s11">11. Premium Outlet (Vault)</h2>
<p>
  11.1 In de „Premium Outlet” (Vault) wordt elk stuk als afzonderlijk kavel aangeboden met een
  openingsprijs, een minimumprijs en een gepubliceerd verlagingsschema. De prijs daalt in
  <?= (int)$steps ?> gelijke stappen, één stap per <?= (int)$hours ?> uur, tot aan de minimumprijs.
</p>
<p>
  11.2 Het schema wordt bij opening van het kavel vastgelegd en daarna niet meer gewijzigd. De
  prijs kan niet stijgen. De berekening gebeurt serverzijdig op basis van het schema en is voor alle
  bezoekers identiek; er vindt geen gepersonaliseerde prijsvorming plaats.
</p>
<p>
  11.3 Bepalend is de prijs die wordt getoond op het moment dat u het kavel in uw tas legt; die
  wordt voor de duur van het bestelproces (20 minuten) voor u gereserveerd. Na afloop van de
  reservering wordt het kavel weer vrijgegeven.
</p>
<p>
  11.4 Elk kavel bestaat slechts eenmaal. Het eindigt met de eerste geldige aankoop. Er bestaat geen
  recht om een kavel in een latere, lagere stap te verwerven.
</p>
<p>
  11.5 Bij prijsverlagingen vermelden wij overeenkomstig § 11 PAngV (Duitse prijsaanduidingsverordening)
  de laagste prijs van de afgelopen 30 dagen. Omdat de Vault-prijs uitsluitend daalt, is dat de
  prijs die onmiddellijk vóór de huidige stap gold. In de eerste stap is er geen prijsverlaging; daar
  wordt geen referentieprijs geadverteerd.
</p>
<p>
  11.6 Uw wettelijke rechten, met name herroeping en wettelijke garantie, gelden onverkort in de
  Vault. Artikel 10 blijft van toepassing op particuliere verkopers.
</p>
<p>
  11.7 Leden met een bevestigde e-mailaanmelding krijgen toegang tot nieuwe kavels vóór de algemene
  vrijgave. Het lidmaatschap is gratis en te allen tijde herroepbaar; er bestaat geen recht op
  vroegtijdige toegang.
</p>
<p>
  11.8 Een ingestelde prijsalert en het bewaren van een stuk op de verlanglijst reserveren dit
  <strong>niet</strong> en geven geen voorkeursrecht. De melding wordt eenmalig verzonden, zonder
  garantie op bezorging of tijdstip; alleen de beschikbaarheid op het moment van bestellen is
  bepalend.
</p>

<h2 id="s12">12. Echtheid en herkomst</h2>
<p>
  12.1 Er worden uitsluitend originele artikelen aangeboden. Verkopers zijn verplicht de
  aankoopbewijzen van hun artikelen te bewaren en op verzoek aan ons te overleggen.
</p>
<p>
  12.2 Blijkt na de aankoop dat een artikel niet origineel is, dan betalen wij de volledige
  koopprijs plus verzendkosten terug en dragen wij de kosten van de retourzending. Dit recht bestaat
  ongeacht het type verkoper.
</p>
<p>
  12.3 Aanspraken op grond van artikel 12.2 vereisen dat u ons het artikel en uw klacht binnen 30
  dagen na ontvangst ter beschikking stelt en ons in staat stelt het te onderzoeken.
</p>

<h2 id="s13">13. Aansprakelijkheid</h2>
<p>
  13.1 Wij zijn onbeperkt aansprakelijk bij opzet en grove nalatigheid, bij schade aan leven,
  lichaam en gezondheid, volgens de bepalingen van de Duitse wet op de productaansprakelijkheid en
  in de omvang van een door ons gegeven garantie.
</p>
<p>
  13.2 Bij licht nalatige schending van een wezenlijke contractuele verplichting is de
  aansprakelijkheid beperkt tot de voorzienbare, voor de overeenkomst typische schade. Overigens is
  aansprakelijkheid uitgesloten.
</p>
<p>
  13.3 Voor tekortkomingen van derde verkopers uit de koopovereenkomst zijn wij niet aansprakelijk;
  onze verantwoordelijkheid wordt in zoverre beheerst door de regels voor hostingdiensten (art. 6
  Digital Services Act). De artikelen 10.3 en 12.2 blijven onverminderd van kracht.
</p>
<p>
  13.4 Voor de ononderbroken beschikbaarheid van het platform wordt geen garantie gegeven.
</p>

<h2 id="s14">14. Kortingsbonnen</h2>
<p>
  14.1 Actiebonnen (bonnen die niet zijn gekocht maar in het kader van een actie zijn uitgegeven)
  zijn alleen in de aangegeven periode en slechts eenmaal inwisselbaar. Na afloop van de
  geldigheid is inwisselen uitgesloten; er vindt geen verlenging plaats.
</p>
<p>
  14.2 De waarde van de bon wordt verrekend met de artikelwaarde, niet met de verzendkosten.
  Uitbetaling in contanten, rente of creditering van een restwaarde is uitgesloten.
</p>
<p>
  14.3 Is een bon gekoppeld aan een e-mailadres of uitdrukkelijk aangeduid als welkomst- of
  eerstebestellingsbon, dan kan alleen de houder van dat adres hem inwisselen, en alleen voor de
  eerste betaalde bestelling. Overdracht aan derden of doorverkoop is uitgesloten.
</p>
<p>
  14.4 Meerdere bonnen zijn niet met elkaar te combineren, tenzij de voorwaarden van de betreffende
  bon anders bepalen. Een minimumbestelwaarde geldt als die bij de bon is vermeld; bepalend is de
  artikelwaarde exclusief verzendkosten.
</p>
<p>
  14.5 Herroept u een bestelling geheel of gedeeltelijk, dan betalen wij het daadwerkelijk betaalde
  bedrag terug. Een ingewisselde actiebon herleeft daarbij niet; er bestaat geen recht op uitgifte
  van een nieuwe bon.
</p>
<p>
  14.6 Bij een gegrond vermoeden van misbruik — met name meerdere aangemaakte accounts om herhaald
  eerstebestellingsbonnen te gebruiken — kunnen wij afzonderlijke bonnen blokkeren.
</p>

<h2 id="s15">15. Gegevensbescherming</h2>
<p>
  Informatie over de verwerking van uw persoonsgegevens vindt u in de
  <a href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>.
  Voor de afwikkeling van uw bestelling geven wij de voor verzending en facturering noodzakelijke
  gegevens door aan de betreffende verkoper.
</p>

<h2 id="s16">16. Slotbepalingen</h2>
<p>
  16.1 Het recht van de Bondsrepubliek Duitsland is van toepassing. Voor consumenten met
  woonplaats in een andere staat blijven de dwingende consumentenbeschermingsregels van hun
  verblijfsstaat onverlet (art. 6 lid 2 Rome I-verordening).
</p>
<p>
  16.2 Plaats van nakoming en bevoegde rechter worden bepaald door de wettelijke voorschriften. Voor
  consumenten gelden de wettelijk bevoegde rechters.
</p>
<p>
  16.3 Mochten afzonderlijke bepalingen van deze voorwaarden ongeldig zijn, dan blijft de geldigheid
  van de overige bepalingen onverlet. In de plaats van de ongeldige bepaling treedt de wettelijke
  regeling.
</p>
<p>
  16.4 Wij behouden ons het recht voor deze voorwaarden met werking voor de toekomst te wijzigen.
  Voor reeds gesloten overeenkomsten geldt de versie die bij het sluiten raadpleegbaar was; de
  aanvaarde versie wordt bij uw bestelling opgeslagen (huidige versie: <?= h(VR_TERMS_VERSION) ?>).
</p>

<div class="doc__box">
  <strong>Opmerking bij deze vertaling</strong>
  <p style="margin-top:8px">
    Deze Nederlandse tekst is een vertaling ter informatie van de Duitse algemene
    verkoopvoorwaarden, die als enige juridisch bindend zijn. Hij laat u lezen waarmee u instemt; bij
    verschillen prevaleert de Duitse versie. Dit is geen juridisch advies.
  </p>
</div>
