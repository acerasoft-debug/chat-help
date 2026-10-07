<?php
/**
 * Impressum (§ 5 DDG, § 18 MStV) — svenska (vägledande översättning). Variabler: legal/impressum.php.
 */
?>
<h2>Leverantör</h2>
<?php vr_company_block(); ?>

<h2>Marknadsplatsens operatör</h2>
<p>
  <?= h((string)vr_config('brand')) ?> är en onlinemarknadsplats som drivs av
  <?= h((string)($c['legal_name'] ?? '')) ?>. Via plattformen erbjuds både operatörens egna varor och
  varor från tredje part (näringsidkande återförsäljare och privata säljare). Vem som är avtalspart
  i köpet visas på varje produktsida och under beställningen innan ordern läggs.
</p>

<h2>Ansvarig för innehållet</h2>
<p>
  Ansvarig enligt § 18 st. 2 MStV är den ovan nämnda firmatecknaren, adress som ovan.
</p>

<h2>Kontakt för konsumentfrågor</h2>
<p>
  Frågor om beställningar, returer och klagomål skickas till
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-postadress]</em>' ?>.
  Vi svarar i regel inom en arbetsdag. Vid frågor om en artikel från en tredjepartssäljare
  förmedlar vi kontakten till respektive säljare eller vidarebefordrar din förfrågan.
</p>

<h2>Konsumenttvistlösning</h2>
<p>
  Vi är varken skyldiga eller villiga att delta i tvistlösningsförfaranden inför en nämnd för
  konsumenttvister. Det utesluter inte en uppgörelse i godo med oss — vänd dig först direkt till oss.
  Mer information under
  <a href="<?= h(vr_url('legal/streitbeilegung.php')) ?>"><?= te('legal_disputes') ?></a>.
</p>

<h2>Betaltjänstleverantör</h2>
<p>
  Betalningar hanteras via Stripe (Stripe Payments Europe, Limited, 1 Grand Canal Street Lower,
  Grand Canal Dock, Dublin, Irland). Utbetalningar till tredjepartssäljare sker via Stripe Connect.
  Kortuppgifter behandlas uteslutande hos Stripe och når inte våra system.
</p>

<h2>Ansvar för innehåll</h2>
<p>
  Som tjänsteleverantör ansvarar vi för eget innehåll på dessa sidor enligt allmän lag. För
  erbjudanden från tredjepartssäljare är vi inte skyldiga att övervaka överförd eller lagrad
  information från tredje part eller att efterforska omständigheter som tyder på olaglig verksamhet
  (art. 6, 8 Digital Services Act). Så snart vi får kännedom om en konkret rättskränkning tar vi
  bort innehållet utan dröjsmål. Anmälningar skickas till
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-postadress]</em>' ?>.
</p>
<p>
  Vi granskar erbjudanden från tredjepartssäljare avseende rimlighet före publicering och kräver
  ursprungsbevis. Denna frivilliga granskning utgör ingen garanti för lagligheten eller äktheten hos
  varje enskilt erbjudande och påverkar inte vårt ansvarsprivilegium som värdtjänstleverantör.
</p>

<h2>Ansvar för länkar</h2>
<p>
  Vårt utbud innehåller länkar till externa webbplatser från tredje part vars innehåll vi inte har
  något inflytande över. För detta innehåll ansvarar alltid respektive leverantör. Vid tidpunkten för
  länkningen kunde inget olagligt innehåll upptäckas.
</p>

<h2>Upphovsrätt</h2>
<p>
  Innehåll och verk som operatören skapat på dessa sidor omfattas av upphovsrätt. Produktbilder och
  produktbeskrivningar från tredjepartssäljare tillhandahålls av dem; de försäkrar oss att de
  förfogar över nödvändiga rättigheter. Varumärkes- och produktnamn tillhör respektive
  rättighetshavare. Att de nämns tjänar enbart till att beskriva de erbjudna varorna och grundar
  ingen affärsrelation med varumärkesinnehavarna.
</p>

<h2>Information om varumärkesrätt</h2>
<p>
  <?= h((string)vr_config('brand')) ?> är inte auktoriserad återförsäljare för de nämnda märkena om
  inte annat uttryckligen anges. De erbjudna varorna är originalvaror som första gången släppts på
  marknaden inom Europeiska ekonomiska samarbetsområdet; vidareförsäljning är därmed tillåten enligt
  principen om konsumtion av varumärkesrätten (§ 24 MarkenG, art. 15 EUTMR).
</p>
