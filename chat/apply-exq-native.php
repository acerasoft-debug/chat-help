<?php
/**
 * ChatHelp — apply-exq-native (CH_EXQ_NATIVE) — ornek sorular 12 dilde YERLI
 *
 *  SORUN: Ornek sorular Almanca EX dizisinden geliyor; gorunen ceviri,
 *  i18n-full katmaninin calismasina BAGIMLI. Katman gecikirse/atlarsa soru
 *  Almanca gorunur ve Almanca gider. Ayrica buildEx her 1000ms'de bolumu
 *  DOM'a YENIDEN EKLIYORDU (appendChild) — saniyelik childList gurultusu.
 *
 *  KESIN COZUM (ceviri katmanina SIFIR bagimlilik):
 *   [1] 7 soru + baslik + altyazi 12 dilde (de,en,tr,fr,es,it,nl,pl,sv,pt,ar,ru)
 *       YERLI metin olarak gomulur; buildEx dogrudan arayuz dilinde kurar.
 *   [2] Bolume data-noi18n verilir -> ceviri katmani hic dokunmaz (savas yok).
 *   [3] Dil degisince bolum otomatik yeniden kurulur (data-exl imzasi).
 *   [4] Saniyelik yeniden-ekleme durdurulur: zaten en alttaysa DOKUNMA.
 *   [5] Tiklama .qt span'inin canli metnini okumaya devam eder (CH_EXQ_LANG_FIX)
 *       -> ekranda gorunen YERLI metin, AI'ya birebir gider. Almanca imkansiz.
 * KULLANIM: pull-updates.php?files=apply-exq-native.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-exq-native BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_EXQ_NATIVE')!==false) exit("Zaten ekli (CH_EXQ_NATIVE).\n");
if (strpos($src,"chExAsk(this.querySelector(")===false) exit("HATA: once apply-exq-lang-fix gerekli (qt-span tiklama kodu yok).\n");

$fail=[];

/* [A1] EX dizisinin hemen ardina 12 dilli EXT + yardimcilar */
$o1 = <<<'OLD'
    {ic:'🛒',t:'Ich möchte einen Online-Kauf widerrufen'}
  ];
OLD;
$n1 = <<<'NEW'
    {ic:'🛒',t:'Ich möchte einen Online-Kauf widerrufen'}
  ];
  /*CH_EXQ_NATIVE — 12 dilde YERLI metin; ceviri katmanina bagimlilik SIFIR*/
  var EXT={
   de:{h:"💡 Beispiel-Fragen",s:"Tippen Sie auf eine Frage, um direkt zu starten:",q:["Ich möchte Widerspruch gegen einen Bescheid einlegen","Wie kündige ich meinen Mietvertrag richtig?","Ich habe einen Bußgeldbescheid erhalten – was kann ich tun?","Ich brauche einen Arbeitsvertrag","Meine Kaution wurde nicht zurückgezahlt","Ich möchte eine Mahnung / Zahlungsaufforderung schreiben","Ich möchte einen Online-Kauf widerrufen"]},
   en:{h:"💡 Example questions",s:"Tap a question to start right away:",q:["I want to appeal against an official decision","How do I terminate my rental contract correctly?","I received a fine notice – what can I do?","I need an employment contract","My deposit was not refunded","I want to write a payment reminder / demand letter","I want to cancel an online purchase"]},
   tr:{h:"💡 Örnek Sorular",s:"Hemen başlamak için bir soruya dokunun:",q:["Bir resmi karara itiraz etmek istiyorum","Kira sözleşmemi doğru şekilde nasıl feshederim?","Bir para cezası tebligatı aldım – ne yapabilirim?","Bir iş sözleşmesine ihtiyacım var","Depozitom iade edilmedi","Bir ihtar / ödeme talebi yazmak istiyorum","Bir internet alışverişini iptal etmek istiyorum"]},
   fr:{h:"💡 Exemples de questions",s:"Touchez une question pour commencer :",q:["Je souhaite contester une décision administrative","Comment résilier correctement mon bail ?","J'ai reçu un avis d'amende – que puis-je faire ?","J'ai besoin d'un contrat de travail","Ma caution n'a pas été remboursée","Je souhaite rédiger une mise en demeure / relance de paiement","Je souhaite annuler un achat en ligne"]},
   es:{h:"💡 Preguntas de ejemplo",s:"Toque una pregunta para empezar:",q:["Quiero presentar un recurso contra una resolución","¿Cómo rescindo correctamente mi contrato de alquiler?","He recibido una multa: ¿qué puedo hacer?","Necesito un contrato de trabajo","No me han devuelto la fianza","Quiero redactar un requerimiento de pago","Quiero cancelar una compra online"]},
   it:{h:"💡 Domande di esempio",s:"Tocca una domanda per iniziare:",q:["Voglio presentare ricorso contro un provvedimento","Come disdico correttamente il mio contratto di affitto?","Ho ricevuto una multa: cosa posso fare?","Ho bisogno di un contratto di lavoro","La mia cauzione non è stata restituita","Voglio scrivere un sollecito di pagamento","Voglio annullare un acquisto online"]},
   nl:{h:"💡 Voorbeeldvragen",s:"Tik op een vraag om direct te beginnen:",q:["Ik wil bezwaar maken tegen een besluit","Hoe zeg ik mijn huurcontract correct op?","Ik heb een boete ontvangen – wat kan ik doen?","Ik heb een arbeidsovereenkomst nodig","Mijn borg is niet terugbetaald","Ik wil een aanmaning / betalingsherinnering schrijven","Ik wil een online aankoop annuleren"]},
   pl:{h:"💡 Przykładowe pytania",s:"Dotknij pytania, aby zacząć:",q:["Chcę odwołać się od decyzji urzędowej","Jak prawidłowo wypowiedzieć umowę najmu?","Otrzymałem mandat – co mogę zrobić?","Potrzebuję umowy o pracę","Nie zwrócono mi kaucji","Chcę napisać wezwanie do zapłaty","Chcę anulować zakup online"]},
   sv:{h:"💡 Exempelfrågor",s:"Tryck på en fråga för att börja:",q:["Jag vill överklaga ett myndighetsbeslut","Hur säger jag upp mitt hyresavtal korrekt?","Jag har fått ett bötesföreläggande – vad kan jag göra?","Jag behöver ett anställningsavtal","Min deposition har inte betalats tillbaka","Jag vill skriva en betalningspåminnelse","Jag vill ångra ett onlineköp"]},
   pt:{h:"💡 Perguntas de exemplo",s:"Toque numa pergunta para começar:",q:["Quero recorrer de uma decisão administrativa","Como rescindo corretamente o meu contrato de arrendamento?","Recebi uma multa – o que posso fazer?","Preciso de um contrato de trabalho","A minha caução não foi devolvida","Quero escrever uma notificação de pagamento","Quero cancelar uma compra online"]},
   ar:{h:"💡 أسئلة نموذجية",s:"اضغط على سؤال للبدء مباشرة:",q:["أريد الاعتراض على قرار رسمي","كيف أنهي عقد الإيجار بشكل صحيح؟","استلمت إشعار غرامة – ماذا أفعل؟","أحتاج إلى عقد عمل","لم يُرَدّ إليّ مبلغ التأمين","أريد كتابة إنذار / مطالبة بالدفع","أريد إلغاء عملية شراء عبر الإنترنت"]},
   ru:{h:"💡 Примеры вопросов",s:"Нажмите на вопрос, чтобы начать:",q:["Я хочу обжаловать официальное решение","Как правильно расторгнуть договор аренды?","Я получил штраф – что мне делать?","Мне нужен трудовой договор","Мне не вернули залог","Я хочу написать требование об оплате","Я хочу отменить онлайн-покупку"]}
  };
  function exL(){ var L="de"; try{ L=localStorage.getItem("ch_uilang")||((typeof UL!=="undefined"&&UL)?UL:"de")||"de"; }catch(e){} return EXT[L]?L:"de"; }
  function exT(i){ var a=EXT[exL()]; return (a&&a.q&&a.q[i])||EX[i].t; }
  function exH(){ var a=EXT[exL()]; return (a&&a.h)||"💡 Beispiel-Fragen"; }
  function exS(){ var a=EXT[exL()]; return (a&&a.s)||""; }
NEW;
$c=0; $src=str_replace($o1,$n1,$src,$c);
echo ($c===1?"  ✓ [1] 12 dilli yerli soru metinleri gomuldu\n":"  ✗ [1] anchor ($c)\n"); if($c!==1)$fail[]=1;

/* [A5] saniyelik yeniden-ekleme durdur + dil degisince yeniden kur */
$o2 = <<<'OLD'
    if(ex){ host.appendChild(ex); return; } // her zaman en altta (Verträge'nin altında) kalsın
OLD;
$n2 = <<<'NEW'
    if(ex){ if(ex.getAttribute('data-exl')===exL()){ if(host.lastElementChild!==ex) host.appendChild(ex); return; } try{ ex.remove(); }catch(e){ return; } } /*CH_EXQ_NATIVE — dil degisince yeniden kur; zaten en alttaysa DOM'a DOKUNMA*/
NEW;
$c=0; $src=str_replace($o2,$n2,$src,$c);
echo ($c===1?"  ✓ [2] saniyelik DOM yeniden-ekleme durduruldu + dil-degisim yeniden kurulumu\n":"  ✗ [2] anchor ($c)\n"); if($c!==1)$fail[]=2;

/* [A4] baslik/altyazi yerli + bolume data-noi18n (ceviri katmani dokunmasin) */
$o3 = <<<'OLD'
    sec.innerHTML='<div class="ch-ex-h">💡 Beispiel-Fragen</div>'
      +'<div class="ch-ex-sub">Tippen Sie auf eine Frage, um direkt zu starten:</div>'
OLD;
$n3 = <<<'NEW'
    sec.setAttribute('data-noi18n','1'); sec.setAttribute('data-exl',exL()); /*CH_EXQ_NATIVE*/
    sec.innerHTML='<div class="ch-ex-h">'+exH()+'</div>'
      +'<div class="ch-ex-sub">'+exS()+'</div>'
NEW;
$c=0; $src=str_replace($o3,$n3,$src,$c);
echo ($c===1?"  ✓ [3] baslik/altyazi yerli dilde + bolum ceviri-katmanindan muaf\n":"  ✗ [3] anchor ($c)\n"); if($c!==1)$fail[]=3;

/* [A2] butonlar yerli metinle kurulur (gorunen = gonderilen) */
$o4 = <<<'OLD'
      + EX.map(function(e){ var t=e.t.replace(/"/g,'&quot;'); return '<button type="button" class="ch-ex-q" onclick="chExAsk(this.querySelector(\'.qt\').textContent)" data-t="'+t+'"><span class="qic">'+e.ic+'</span><span class="qt">'+e.t+'</span><span class="qar">→</span></button>'; }).join('')
OLD;
$n4 = <<<'NEW'
      + EX.map(function(e,i){ var q=exT(i); var t=q.replace(/"/g,'&quot;'); return '<button type="button" class="ch-ex-q" onclick="chExAsk(this.querySelector(\'.qt\').textContent)" data-t="'+t+'"><span class="qic">'+e.ic+'</span><span class="qt">'+q+'</span><span class="qar">→</span></button>'; }).join('') /*CH_EXQ_NATIVE*/
NEW;
$c=0; $src=str_replace($o4,$n4,$src,$c);
echo ($c===1?"  ✓ [4] butonlar dogrudan yerli dilde kuruluyor — Almanca hicbir asamada yok\n":"  ✗ [4] anchor ($c)\n"); if($c!==1)$fail[]=4;

if ($fail) { echo "\nHATA: eslesmeyen adimlar: ".implode(', ',$fail)." — index.php DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'xn').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-exqnative-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_EXQ_NATIVE uygulandi. Ornek sorular arayuz dilinde YERLI olarak gorunur\n";
echo "   ve AI'ya birebir o dilde gider. Dil degisince otomatik yenilenir.\n";
