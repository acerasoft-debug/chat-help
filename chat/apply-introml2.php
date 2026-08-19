<?php
/**
 * ChatHelp — apply-introml2 (CH_INTRO_ML2) — intro'yu 12 dile cikarir + Arapca RTL.
 *   Diller: de,en,tr,fr,es,it,nl,pl,pt,sv,ar,ru. Arayuz dili (ch_uilang) hangisiyse o cikar.
 *   [1] Onceki CH_INTRO_ML'yi devre disi birakir (bir 'return;').
 *   [2] 12 dilli yeni intro'yu </body>'den once enjekte eder.
 *   Ayni anahtar (ch_intro_ml_v1) -> daha once gorenler tekrar gormez; yenilere dogru dilde cikar.
 *   Additive + fail-open + idempotent + php -l lint + .bak + opcache + dogrulama.
 * KULLANIM: /chat/pull2.php?key=...&files=apply-introml2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-introml2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_INTRO_ML2')!==false) exit("Zaten ekli (CH_INTRO_ML2).\n");

/* ── [1] onceki CH_INTRO_ML'yi devre disi birak ── */
$oldA="var KEY='ch_intro_ml_v1';";
$oldN="var KEY='ch_intro_ml_v1';return;/*CH_INTRO_ML2 — onceki 6-dil intro kapatildi*/";
$co=substr_count($src,$oldA);
if($co===1){ $src=str_replace($oldA,$oldN,$src); echo "  ✓ onceki CH_INTRO_ML devre disi (1)\n"; }
elseif($co===0){ echo "  · onceki CH_INTRO_ML bulunamadi — sadece yeni enjekte edilecek\n"; }
else { exit("  ✗ onceki intro anchor $co kez — guvenlik icin DEGISTIRILMEDI\n"); }

/* ── [2] 12 dilli yeni intro ── */
$block=<<<'BLOCK'
<script>/*CH_INTRO_ML2 — 12 dilli giris intro'su (bir kez, fail-open, RTL destekli)*/(function(){try{
  var KEY='ch_intro_ml_v1';
  try{ if(localStorage.getItem(KEY)) return; }catch(e){ return; }
  function uil(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2).toLowerCase(); }catch(e){ return 'de'; } }
  var L={
    de:{eb:'So entsteht Ihr Schreiben',ha:'Recherchiert. Geprüft. ',hb:'Zugestellt.',
      sub:'Kein Textbaustein. Kein Zufall. ChatHelp recherchiert, prüft, formuliert und versendet — vollautomatisch und rechtssicher.',
      s1n:'Recherche',s1t:'Geht in die Datenbank',s1d:'Durchsucht geprüfte Rechtsquellen &amp; Behördendaten und holt die korrekten, aktuellen Informationen.',
      s2n:'Verifizierung',s2t:'Und prüft jede Angabe',s2d:'Fristen, Paragraphen und Zuständigkeiten werden gegengeprüft — erst wenn alles stimmt, geht es weiter.',
      s3n:'Dokument',s3t:'Ein makelloses Schreiben',s3d:'Rechtssicher nach DIN 5008 — präzise formuliert, premium gesetzt, unterschriftsreif.',
      s4n:'Versand',s4t:'Direkt aus dem System',s4d:'Per Fax, Brief oder Einschreiben — ohne Drucker, ohne Warteschlange, ohne Weg zur Post.',
      c1:'Fax',c2:'Brief',c3:'Einschreiben',cta:'Los geht’s',skip:'Überspringen'},
    en:{eb:'How your letter is created',ha:'Researched. Verified. ',hb:'Delivered.',
      sub:'No boilerplate. No guesswork. ChatHelp researches, verifies, drafts and sends — fully automatic and legally sound.',
      s1n:'Research',s1t:'Goes into the database',s1d:'Searches verified legal sources &amp; official data and retrieves the correct, up-to-date information.',
      s2n:'Verification',s2t:'And checks every detail',s2d:'Deadlines, statutes and responsibilities are cross-checked — only when everything fits does it proceed.',
      s3n:'Document',s3t:'A flawless letter',s3d:'Legally sound per DIN 5008 — precisely worded, premium typeset, ready to sign.',
      s4n:'Delivery',s4t:'Straight from the system',s4d:'By fax, letter or registered mail — no printer, no queue, no trip to the post office.',
      c1:'Fax',c2:'Letter',c3:'Registered',cta:'Let’s go',skip:'Skip'},
    tr:{eb:'Yazınız nasıl hazırlanıyor',ha:'Araştırıldı. Doğrulandı. ',hb:'Gönderildi.',
      sub:'Hazır kalıp yok. Tahmin yok. ChatHelp araştırır, doğrular, yazar ve gönderir — tamamen otomatik ve hukuka uygun.',
      s1n:'Araştırma',s1t:'Veri bankasına girer',s1d:'Doğrulanmış hukuk kaynaklarını ve resmi verileri tarar, doğru ve güncel bilgiyi getirir.',
      s2n:'Doğrulama',s2t:'Ve her bilgiyi kontrol eder',s2d:'Süreler, kanun maddeleri ve yetkili makamlar çapraz kontrol edilir — her şey doğruysa devam eder.',
      s3n:'Belge',s3t:'Kusursuz bir dilekçe',s3d:'DIN 5008’e uygun, hukuken sağlam — özenle yazılmış, premium dizilmiş, imzaya hazır.',
      s4n:'Gönderim',s4t:'Doğrudan sistemden',s4d:'Faks, mektup veya iadeli taahhütlü — yazıcı yok, sıra yok, postaneye gitmek yok.',
      c1:'Faks',c2:'Mektup',c3:'İadeli Taahhütlü',cta:'Başlayalım',skip:'Geç'},
    fr:{eb:'Comment votre courrier est créé',ha:'Recherché. Vérifié. ',hb:'Envoyé.',
      sub:'Aucun modèle tout fait. Aucun hasard. ChatHelp recherche, vérifie, rédige et envoie — entièrement automatique et juridiquement fiable.',
      s1n:'Recherche',s1t:'Accède à la base de données',s1d:'Parcourt des sources juridiques vérifiées &amp; des données officielles pour obtenir les informations correctes et à jour.',
      s2n:'Vérification',s2t:'Et vérifie chaque donnée',s2d:'Délais, articles de loi et compétences sont recoupés — on ne continue que si tout concorde.',
      s3n:'Document',s3t:'Un courrier impeccable',s3d:'Juridiquement fiable selon DIN 5008 — formulé avec précision, mise en page premium, prêt à signer.',
      s4n:'Envoi',s4t:'Directement depuis le système',s4d:'Par fax, courrier ou recommandé — sans imprimante, sans file d’attente, sans aller à la poste.',
      c1:'Fax',c2:'Courrier',c3:'Recommandé',cta:'C’est parti',skip:'Passer'},
    es:{eb:'Cómo se crea su escrito',ha:'Investigado. Verificado. ',hb:'Enviado.',
      sub:'Sin plantillas. Sin azar. ChatHelp investiga, verifica, redacta y envía — totalmente automático y jurídicamente seguro.',
      s1n:'Investigación',s1t:'Entra en la base de datos',s1d:'Busca en fuentes jurídicas verificadas &amp; datos oficiales para obtener la información correcta y actual.',
      s2n:'Verificación',s2t:'Y comprueba cada dato',s2d:'Plazos, artículos y competencias se contrastan — solo continúa cuando todo encaja.',
      s3n:'Documento',s3t:'Un escrito impecable',s3d:'Jurídicamente seguro según DIN 5008 — redactado con precisión, maquetado premium, listo para firmar.',
      s4n:'Envío',s4t:'Directo desde el sistema',s4d:'Por fax, carta o certificado — sin impresora, sin colas, sin ir a correos.',
      c1:'Fax',c2:'Carta',c3:'Certificado',cta:'Vamos',skip:'Omitir'},
    it:{eb:'Come nasce la tua lettera',ha:'Ricercato. Verificato. ',hb:'Inviato.',
      sub:'Nessun modello. Nessun caso. ChatHelp ricerca, verifica, redige e invia — completamente automatico e giuridicamente sicuro.',
      s1n:'Ricerca',s1t:'Entra nella banca dati',s1d:'Consulta fonti giuridiche verificate &amp; dati ufficiali per ottenere le informazioni corrette e aggiornate.',
      s2n:'Verifica',s2t:'E controlla ogni dato',s2d:'Scadenze, articoli e competenze vengono verificati — si prosegue solo se tutto corrisponde.',
      s3n:'Documento',s3t:'Una lettera impeccabile',s3d:'Giuridicamente sicura secondo DIN 5008 — formulata con precisione, impaginata premium, pronta da firmare.',
      s4n:'Invio',s4t:'Direttamente dal sistema',s4d:'Via fax, lettera o raccomandata — senza stampante, senza code, senza andare alla posta.',
      c1:'Fax',c2:'Lettera',c3:'Raccomandata',cta:'Iniziamo',skip:'Salta'},
    nl:{eb:'Hoe uw brief tot stand komt',ha:'Onderzocht. Gecontroleerd. ',hb:'Verzonden.',
      sub:'Geen standaardtekst. Geen toeval. ChatHelp onderzoekt, controleert, stelt op en verzendt — volledig automatisch en juridisch correct.',
      s1n:'Onderzoek',s1t:'Gaat de databank in',s1d:'Doorzoekt geverifieerde juridische bronnen &amp; officiële gegevens en haalt de juiste, actuele informatie op.',
      s2n:'Verificatie',s2t:'En controleert elk gegeven',s2d:'Termijnen, wetsartikelen en bevoegdheden worden gecontroleerd — pas als alles klopt, gaat het verder.',
      s3n:'Document',s3t:'Een onberispelijke brief',s3d:'Juridisch correct volgens DIN 5008 — nauwkeurig geformuleerd, premium opgemaakt, klaar om te ondertekenen.',
      s4n:'Verzending',s4t:'Rechtstreeks vanuit het systeem',s4d:'Per fax, brief of aangetekend — zonder printer, zonder wachtrij, zonder gang naar het postkantoor.',
      c1:'Fax',c2:'Brief',c3:'Aangetekend',cta:'Aan de slag',skip:'Overslaan'},
    pl:{eb:'Jak powstaje Twoje pismo',ha:'Zbadane. Zweryfikowane. ',hb:'Wysłane.',
      sub:'Żadnych szablonów. Żadnego przypadku. ChatHelp bada, weryfikuje, redaguje i wysyła — w pełni automatycznie i zgodnie z prawem.',
      s1n:'Badanie',s1t:'Sięga do bazy danych',s1d:'Przeszukuje zweryfikowane źródła prawne &amp; dane urzędowe i pobiera poprawne, aktualne informacje.',
      s2n:'Weryfikacja',s2t:'I sprawdza każdy szczegół',s2d:'Terminy, przepisy i właściwości są weryfikowane — dalej dopiero, gdy wszystko się zgadza.',
      s3n:'Dokument',s3t:'Nienaganne pismo',s3d:'Zgodne z prawem wg DIN 5008 — precyzyjnie sformułowane, profesjonalnie złożone, gotowe do podpisu.',
      s4n:'Wysyłka',s4t:'Bezpośrednio z systemu',s4d:'Faksem, listem lub listem poleconym — bez drukarki, bez kolejki, bez wizyty na poczcie.',
      c1:'Faks',c2:'List',c3:'Polecony',cta:'Zaczynamy',skip:'Pomiń'},
    pt:{eb:'Como a sua carta é criada',ha:'Pesquisado. Verificado. ',hb:'Enviado.',
      sub:'Sem modelos. Sem acaso. O ChatHelp pesquisa, verifica, redige e envia — totalmente automático e juridicamente seguro.',
      s1n:'Pesquisa',s1t:'Acede à base de dados',s1d:'Pesquisa fontes jurídicas verificadas &amp; dados oficiais e obtém as informações corretas e atuais.',
      s2n:'Verificação',s2t:'E verifica cada dado',s2d:'Prazos, artigos e competências são cruzados — só avança quando tudo está correto.',
      s3n:'Documento',s3t:'Uma carta impecável',s3d:'Juridicamente segura segundo a DIN 5008 — redigida com precisão, composição premium, pronta a assinar.',
      s4n:'Envio',s4t:'Diretamente do sistema',s4d:'Por fax, carta ou registado — sem impressora, sem filas, sem ir aos correios.',
      c1:'Fax',c2:'Carta',c3:'Registado',cta:'Vamos',skip:'Ignorar'},
    sv:{eb:'Så skapas ditt brev',ha:'Undersökt. Verifierat. ',hb:'Skickat.',
      sub:'Inga mallar. Ingen slump. ChatHelp undersöker, verifierar, formulerar och skickar — helt automatiskt och juridiskt säkert.',
      s1n:'Efterforskning',s1t:'Går in i databasen',s1d:'Söker i verifierade rättskällor &amp; myndighetsdata och hämtar korrekt, aktuell information.',
      s2n:'Verifiering',s2t:'Och kontrollerar varje uppgift',s2d:'Frister, paragrafer och behörigheter dubbelkollas — det fortsätter först när allt stämmer.',
      s3n:'Dokument',s3t:'Ett felfritt brev',s3d:'Juridiskt säkert enligt DIN 5008 — precist formulerat, premiumsatt, klart att signera.',
      s4n:'Utskick',s4t:'Direkt från systemet',s4d:'Via fax, brev eller rekommenderat — utan skrivare, utan kö, utan att gå till posten.',
      c1:'Fax',c2:'Brev',c3:'Rekommenderat',cta:'Sätt igång',skip:'Hoppa över'},
    ar:{eb:'كيف يتم إنشاء رسالتك',ha:'بحثٌ. تحقُّقٌ. ',hb:'إرسال.',
      sub:'لا قوالب جاهزة. لا صدفة. تبحث ChatHelp وتتحقّق وتصيغ وترسل — تلقائيًا بالكامل وبأمان قانوني.',
      s1n:'البحث',s1t:'يدخل إلى قاعدة البيانات',s1d:'يبحث في مصادر قانونية موثّقة &amp; بيانات رسمية ويجلب المعلومات الصحيحة والحديثة.',
      s2n:'التحقّق',s2t:'ويتحقّق من كل معلومة',s2d:'يتم التحقّق من المواعيد والمواد القانونية والاختصاصات — ولا يُتابَع إلا عندما يكون كل شيء صحيحًا.',
      s3n:'المستند',s3t:'رسالة لا تشوبها شائبة',s3d:'آمنة قانونيًا وفق DIN 5008 — صياغة دقيقة، تنسيق فاخر، جاهزة للتوقيع.',
      s4n:'الإرسال',s4t:'مباشرة من النظام',s4d:'عبر الفاكس أو البريد أو البريد المسجّل — دون طابعة، دون طابور، دون الذهاب إلى مكتب البريد.',
      c1:'فاكس',c2:'خطاب',c3:'بريد مسجّل',cta:'لنبدأ',skip:'تخطّي'},
    ru:{eb:'Как создаётся ваше письмо',ha:'Изучено. Проверено. ',hb:'Отправлено.',
      sub:'Никаких шаблонов. Никакой случайности. ChatHelp изучает, проверяет, составляет и отправляет — полностью автоматически и юридически надёжно.',
      s1n:'Исследование',s1t:'Обращается к базе данных',s1d:'Ищет в проверенных правовых источниках &amp; официальных данных и получает верную, актуальную информацию.',
      s2n:'Проверка',s2t:'И проверяет каждый пункт',s2d:'Сроки, статьи закона и компетенции перепроверяются — далее только когда всё совпадает.',
      s3n:'Документ',s3t:'Безупречное письмо',s3d:'Юридически надёжно по DIN 5008 — точная формулировка, премиум-вёрстка, готово к подписи.',
      s4n:'Отправка',s4t:'Прямо из системы',s4d:'Факсом, письмом или заказным — без принтера, без очереди, без похода на почту.',
      c1:'Факс',c2:'Письмо',c3:'Заказное',cta:'Поехали',skip:'Пропустить'}
  };
  var t=L[uil()]||L.de;
  var isRTL=(uil()==='ar');
  function build(){
    if(document.getElementById('chiov2')) return;
    var reduce=false; try{reduce=window.matchMedia('(prefers-reduced-motion:reduce)').matches;}catch(e){}
    var css=""
    +"#chiov2{position:fixed;inset:0;z-index:2147483000;display:flex;align-items:center;justify-content:center;padding:22px;overflow:auto;-webkit-overflow-scrolling:touch;opacity:1;transition:opacity .44s ease;background:radial-gradient(120% 90% at 50% -8%,rgba(24,52,96,.55),transparent 60%),rgba(6,13,26,.9);backdrop-filter:blur(9px);-webkit-backdrop-filter:blur(9px);font-family:system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif}"
    +"#chiov2 *{box-sizing:border-box}"
    +".chi2-p{width:100%;max-width:540px;margin:auto;background:linear-gradient(168deg,#122A4B,#081326);border:1px solid rgba(214,175,106,.3);border-radius:24px;padding:34px 30px 30px;box-shadow:0 40px 90px -40px rgba(0,0,0,.85),0 0 0 1px rgba(255,255,255,.03) inset;color:#EAF0F8;text-align:center}"
    +".chi2-seal{width:60px;height:60px;margin:0 auto 16px;display:block}"
    +".chi2-eb{font-size:.68rem;letter-spacing:.3em;text-transform:uppercase;color:#D6AF6A;font-weight:600;display:inline-flex;align-items:center;gap:.7em}"
    +".chi2-eb:before,.chi2-eb:after{content:'';width:22px;height:1px;background:#D6AF6A;opacity:.55}"
    +".chi2-h{font-family:ui-serif,'Iowan Old Style',Georgia,serif;font-weight:600;font-size:clamp(1.5rem,1.1rem+2vw,2rem);line-height:1.12;letter-spacing:-.01em;margin:.55em 0 .2em;text-wrap:balance}"
    +".chi2-h em{font-style:italic;color:#E4C688}"
    +".chi2-sub{color:#A9BAD2;font-size:.96rem;max-width:42ch;margin:0 auto 22px;line-height:1.55}"
    +".chi2-steps{text-align:left;position:relative;margin:0 0 20px;padding-left:4px}"
    +".chi2-steps:before{content:'';position:absolute;left:20px;top:12px;bottom:22px;width:2px;background:linear-gradient(#D6AF6A,rgba(214,175,106,.12));border-radius:2px;opacity:.5}"
    +".chi2-step{position:relative;display:grid;grid-template-columns:38px 1fr;gap:14px;align-items:start;padding:0 0 16px;opacity:0;transform:translateY(10px);transition:opacity .5s cubic-bezier(.2,.7,.2,1),transform .5s cubic-bezier(.2,.7,.2,1)}"
    +".chi2-step.in{opacity:1;transform:none}"
    +".chi2-nd{width:38px;height:38px;border-radius:50%;background:#0C1C34;border:1.5px solid rgba(214,175,106,.7);display:grid;place-items:center;z-index:1;box-shadow:0 0 0 4px rgba(214,175,106,.1)}"
    +".chi2-nd svg{width:18px;height:18px;stroke:#E4C688;fill:none;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}"
    +".chi2-num{font-family:ui-serif,Georgia,serif;font-size:.66rem;letter-spacing:.16em;color:#D6AF6A;font-weight:600;display:block;margin-bottom:.2em}"
    +".chi2-st{font-family:ui-serif,'Iowan Old Style',Georgia,serif;color:#fff;font-weight:600;font-size:1.02rem;margin:0 0 .1em;line-height:1.2}"
    +".chi2-sd{color:#9FB2CC;font-size:.86rem;margin:0;line-height:1.5}"
    +".chi2-chips{display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin:0 0 24px}"
    +".chi2-chip{display:inline-flex;align-items:center;gap:6px;font-size:.8rem;color:#EAF0F8;background:rgba(255,255,255,.05);border:1px solid rgba(214,175,106,.28);border-radius:999px;padding:7px 13px}"
    +".chi2-chip svg{width:14px;height:14px;stroke:#E4C688;fill:none;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}"
    +".chi2-cta{display:block;width:100%;border:0;cursor:pointer;font:inherit;font-weight:700;font-size:1.02rem;color:#0B1E38;background:linear-gradient(180deg,#E4C688,#C6A15B);border-radius:999px;padding:15px 22px;letter-spacing:.01em;box-shadow:0 12px 26px -12px rgba(214,175,106,.7);transition:transform .2s,box-shadow .2s}"
    +".chi2-cta:hover{transform:translateY(-2px);box-shadow:0 16px 30px -12px rgba(214,175,106,.85)}"
    +".chi2-skip{background:none;border:0;cursor:pointer;font:inherit;color:#8AA0BE;font-size:.85rem;margin-top:14px;text-decoration:underline;text-underline-offset:3px}"
    +".chi2-skip:hover{color:#C9D6E8}"
    +".chi2-p[dir=rtl]{text-align:center}"
    +".chi2-p[dir=rtl] .chi2-steps{padding-left:0;padding-right:4px;direction:rtl}"
    +".chi2-p[dir=rtl] .chi2-steps:before{left:auto;right:20px}"
    +"@media (max-width:460px){.chi2-p{padding:28px 20px 24px}}";
    var st=document.createElement('style'); st.id='chiov2-css'; st.textContent=css; (document.head||document.documentElement).appendChild(st);
    var IC1="<svg viewBox='0 0 24 24'><ellipse cx='12' cy='5' rx='8' ry='3'/><path d='M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5'/><path d='M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6'/></svg>";
    var IC2="<svg viewBox='0 0 24 24'><path d='M12 2l7 3v6c0 4.5-3 8-7 10-4-2-7-5.5-7-10V5z'/><path d='M9 12l2 2 4-4.5'/></svg>";
    var IC3="<svg viewBox='0 0 24 24'><path d='M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z'/><path d='M14 3v5h5'/><path d='M9 13h6M9 16.5h4'/></svg>";
    var IC4="<svg viewBox='0 0 24 24'><path d='M22 3L11 14'/><path d='M22 3l-7 19-4-8-8-4z'/></svg>";
    function step(ic,num,word,tt,dd){ return "<div class='chi2-step'><div class='chi2-nd'>"+ic+"</div><div><span class='chi2-num'>"+num+" · "+word+"</span><p class='chi2-st'>"+tt+"</p><p class='chi2-sd'>"+dd+"</p></div></div>"; }
    var html=""
    +"<div class='chi2-p' role='dialog' dir='"+(isRTL?'rtl':'ltr')+"' aria-label='ChatHelp'>"
    +"<svg class='chi2-seal' viewBox='0 0 100 100' fill='none' aria-hidden='true'><circle cx='50' cy='50' r='44' stroke='#D6AF6A' stroke-width='1.5' opacity='.55'/><circle cx='50' cy='50' r='35' stroke='#D6AF6A' stroke-width='1' stroke-dasharray='2 4' opacity='.5'/><path d='M35 51l10 10 20-22' stroke='#E4C688' stroke-width='3.6' stroke-linecap='round' stroke-linejoin='round'/></svg>"
    +"<span class='chi2-eb'>"+t.eb+"</span>"
    +"<h2 class='chi2-h'>"+t.ha+"<em>"+t.hb+"</em></h2>"
    +"<p class='chi2-sub'>"+t.sub+"</p>"
    +"<div class='chi2-steps'>"
    +step(IC1,'01',t.s1n,t.s1t,t.s1d)
    +step(IC2,'02',t.s2n,t.s2t,t.s2d)
    +step(IC3,'03',t.s3n,t.s3t,t.s3d)
    +step(IC4,'04',t.s4n,t.s4t,t.s4d)
    +"</div>"
    +"<div class='chi2-chips'>"
    +"<span class='chi2-chip'><svg viewBox='0 0 24 24'><path d='M6 9V3h12v6'/><rect x='3' y='9' width='18' height='8' rx='2'/><path d='M6 17h12v4H6z'/></svg>"+t.c1+"</span>"
    +"<span class='chi2-chip'><svg viewBox='0 0 24 24'><rect x='3' y='5' width='18' height='14' rx='2'/><path d='M3 7l9 6 9-6'/></svg>"+t.c2+"</span>"
    +"<span class='chi2-chip'><svg viewBox='0 0 24 24'><rect x='3' y='5' width='18' height='14' rx='2'/><path d='M3 7l9 6 9-6'/><path d='M8.5 16.5l1.6 1.6 3.4-3.4'/></svg>"+t.c3+"</span>"
    +"</div>"
    +"<button class='chi2-cta' data-close type='button'>"+t.cta+" →</button>"
    +"<button class='chi2-skip' data-close type='button'>"+t.skip+"</button>"
    +"</div>";
    var ov=document.createElement('div'); ov.id='chiov2'; ov.innerHTML=html;
    document.body.appendChild(ov);
    function done(){ try{localStorage.setItem(KEY,'1');}catch(e){} ov.style.opacity='0'; setTimeout(function(){ try{ov.remove();}catch(_){} },440); }
    ov.addEventListener('click',function(e){ if(e.target===ov || (e.target.closest && e.target.closest('[data-close]'))) done(); });
    document.addEventListener('keydown',function ek(e){ if(e.key==='Escape'){ done(); document.removeEventListener('keydown',ek); } });
    var steps=ov.querySelectorAll('.chi2-step');
    for(var i=0;i<steps.length;i++){
      if(reduce){ steps[i].classList.add('in'); }
      else { (function(el,d){ setTimeout(function(){ el.classList.add('in'); },160+d*150); })(steps[i],i); }
    }
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',build);
  else build();
}catch(e){ try{ var o=document.getElementById('chiov2'); if(o)o.remove(); }catch(_){} }})();</script>
BLOCK;

$anchor='</body>';
$pos=strrpos($src,$anchor);
if($pos===false){ echo "  ✗ </body> capasi bulunamadi — DEGISTIRILMEDI.\n"; exit; }
$new=substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp=tempnam(sys_get_temp_dir(),'im2').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "  ✗ LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }

@file_put_contents($file.'.bak-introml2-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("  ✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_INTRO_ML2')===false) exit("  ✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_INTRO_ML2 enjekte edildi (".strlen($chk)." B)\n";
echo "  · Intro artik 12 dilde: de/en/tr/fr/es/it/nl/pl/pt/sv/ar/ru (ar = RTL).\n";
echo "  · Arayuz dili hangisiyse intro o dilde cikar (ch_uilang).\n";
