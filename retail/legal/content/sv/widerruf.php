<?php
/**
 * Ångerrättsinformation + standardångerblankett — svenska (vägledande
 * översättning). Följer den officiella mallen i direktiv 2011/83/EU (bilaga I),
 * liksom det tyska originalet följer BGB. Variabler: legal/widerruf.php.
 */
?>
<div class="notice">
  <strong>Viktigt vid köp på marknadsplatsen:</strong> Den lagstadgade ångerrätten gäller endast vid
  avtal mellan en konsument och en <em>näringsidkare</em>. För varor som på produktsidan är märkta
  „<?= te('seller_private') ?>“ finns därför <strong>ingen</strong> ångerrätt. Du ser denna märkning
  innan du betalar och måste bekräfta den uttryckligen under beställningen.
</div>

<h2>Ångerrätt</h2>
<p>
  Du har rätt att frånträda detta avtal utan att ange något skäl inom <?= (int)$wd ?> dagar.
</p>
<p>
  Ångerfristen löper ut <?= (int)$wd ?> dagar efter den dag då du, eller någon tredje part som du
  anger, dock ej transportföretaget, tar varan i fysisk besittning.
</p>
<p>
  Vid ett avtal om flera varor som du beställt i en och samma beställning och som levereras separat
  löper ångerfristen ut <?= (int)$wd ?> dagar efter den dag då du, eller någon tredje part som du
  anger, dock ej transportföretaget, tar den sista varan i fysisk besittning.
</p>
<p>
  Vill du utöva ångerrätten ska du till oss
</p>
<div class="doc__box">
  <?= h($co) ?><br>
  <?= h($addrShown) ?><br>
  <?= $email !== '' ? 'E-post: <a href="mailto:' . h($email) . '">' . h($email) . '</a>' : 'E-post: [e-postadress]' ?>
</div>
<p>
  skicka ett klart och tydligt meddelande om ditt beslut att frånträda avtalet (t.ex. ett brev
  avsänt per post eller e-post). Du kan använda den bifogade standardångerblanketten, men du måste
  inte använda den.
</p>
<p>
  Om ångern avser en vara från en näringsidkande tredjepartssäljare räcker förklaringen till oss; vi
  är bemyndigade att ta emot den och vidarebefordrar den utan dröjsmål.
</p>
<p>
  För att du ska hinna utöva din ångerrätt i tid räcker det med att du sänder in ditt meddelande om
  att du tänker utöva ångerrätten innan ångerfristen gått ut.
</p>

<h2>Verkan av utövad ångerrätt</h2>
<p>
  Om du frånträder detta avtal kommer vi att betala tillbaka alla betalningar vi fått från dig,
  bland dem också leveranskostnader (men då räknas inte extra leveranskostnader till följd av att
  du valt något annat leveranssätt än den billigaste standardleverans vi erbjuder). Återbetalningen
  kommer att ske utan onödigt dröjsmål och i vilket fall som helst senast fjorton dagar från och
  med den dag då vi underrättades om ditt beslut att frånträda avtalet. Vi kommer att använda samma
  betalningsmedel för återbetalningen som du själv har använt för den inledande affärshändelsen, om
  du inte uttryckligen kommit överens med oss om något annat. I vilket fall som helst kommer
  återbetalningen inte att kosta dig något.
</p>
<p>
  Vi får vänta med återbetalningen tills vi fått tillbaka varan från dig eller tills du sänt in ett
  bevis på att du återsänt varan, beroende på vilket som inträffar först.
</p>
<p>
  Du ska återsända varan till oss eller till den säljare som anges på returetiketten, eller
  överlämna den, utan onödigt dröjsmål och i vart fall senast fjorton dagar efter den dag då du
  meddelat oss om ditt beslut att frånträda avtalet. Ångerfristen ska anses ha iakttagits om du
  skickar tillbaka varorna innan denna fjortondagarsperiod löpt ut.
</p>
<p>
  Vi står för kostnaderna för återsändandet av varan om returen sker från Tyskland. Vid returer
  från andra länder får du själv betala de direkta kostnaderna för återsändandet av varan.
</p>
<p>
  Du är ansvarig endast för varornas minskade värde till följd av annan hantering än vad som är
  nödvändigt för att fastställa varornas art, egenskaper och funktion. Att prova ett plagg är
  tillåtet; att bära, tvätta eller ta bort etiketterna går utöver detta.
</p>

<h2>Undantag från ångerrätten</h2>
<p>Ångerrätten gäller inte eller upphör vid följande avtal:</p>
<ul>
  <li>avtal med säljare som inte är näringsidkare (privata säljare);</li>
  <li>avtal om leverans av varor som tillverkats enligt konsumentens anvisningar eller som har en
      tydlig personlig prägel;</li>
  <li>avtal om leverans av förseglade varor som av hälsoskydds- eller hygienskäl inte lämpligen
      kan returneras och där förseglingen brutits efter leveransen (t.ex. örhängen, badkläder utan
      hygienförsegling);</li>
  <li>avtal där du handlar som näringsidkare (B2B).</li>
</ul>

<h2>Standardångerblankett</h2>
<div class="doc__box">
  <p><em>(Blanketten ska fyllas i och återsändas bara om du vill frånträda avtalet.)</em></p>
  <p>
    Till<br>
    <?= h($co) ?><br>
    <?= h($addrShown) ?><br>
    <?= $email !== '' ? h($email) : '[e-postadress]' ?>
  </p>
  <p>
    Jag/Vi (*) meddelar härmed att jag/vi (*) frånträder mitt/vårt (*) köpeavtal avseende följande
    varor (*):
  </p>
  <p>
    ______________________________________________<br>
    ______________________________________________
  </p>
  <p>
    Ordernummer: ____________________________<br>
    Beställdes den (*) / mottogs den (*): ____________________________<br>
    Konsumentens/konsumenternas namn: ____________________________<br>
    Konsumentens/konsumenternas adress: ____________________________<br>
    ____________________________
  </p>
  <p>
    Konsumentens/konsumenternas underskrift <em>(endast om blanketten meddelas på papper)</em>:
    ____________________________<br>
    Datum: ____________________________
  </p>
  <p><em>(*) Stryk det som inte är tillämpligt.</em></p>
</div>

<p class="doc__related">
  Utöver den lagstadgade ångerrätten ger vi en frivillig returrätt för egna varor — detaljer under
  <a href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a>.
</p>
