<?php
/** ChatHelp — apply-se-expand-more (CH_SE_EXPAND_MORE) — SE katalog genisletme
 *  Workflow ile uretildi: +16 belge, 4 kategori. Deferred injection.
 *  KULLANIM: pull-updates.php?files=apply-se-expand-more.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-se-expand-more BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_SE_EXPAND_MORE')!==false) exit("Zaten ekli (CH_SE_EXPAND_MORE).\n");
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if (strpos($src,$anchor)===false) exit("HATA: anchor (getDocPrice) yok.\n");
$js = <<<'CHJS'

/* CH_SE_EXPAND_MORE — SE katalog genisletme (workflow, deferred) */
try{(function(){
  function Pe(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var NEWCATS={
    "se_familj":{n:"Familj",ic:"👪"},
    "se_boende":{n:"Boende & Hyra",ic:"🏠"},
    "se_arbete":{n:"Arbete & Anställning",ic:"💼"},
    "se_konsument":{n:"Konsument, skuld & myndighet",ic:"🛒"}
  };
  var E={
    "sex_aktenskapsforord":{ic:"💍",n:"Äktenskapsförord",cat:"se_familj",tier:"komplex",q:[Pe("make1","Make/maka 1: fullständigt namn och personnummer",true),Pe("make2","Make/maka 2: fullständigt namn och personnummer",true),Pe("tidpunkt","När ingås förordet?",true,["Före vigsel","Under äktenskapet"]),Pe("omfattning","Vad ska göras till enskild egendom?",true,["All egendom","Viss angiven egendom"]),Pe("egendom","Ange vilken egendom som ska vara enskild (om viss egendom)",false),Pe("notering","OBS: äktenskapsförordet måste registreras hos Skatteverket för att gälla",false)]},
    "sex_samboavtal":{ic:"🏡",n:"Samboavtal",cat:"se_familj",tier:"standard",q:[Pe("sambo1","Sambo 1: fullständigt namn och personnummer",true),Pe("sambo2","Sambo 2: fullständigt namn och personnummer",true),Pe("bostad","Gemensam bostad (adress), om sådan finns",false),Pe("reglering","Vad vill ni avtala om?",true,["Att sambolagens bodelningsregler inte ska gälla alls","Att viss egendom ska undantas från bodelning","Annan fördelning"]),Pe("egendom","Vilken egendom ska undantas (bostad, bohag, annat)?",false),Pe("datum","Datum då avtalet ska gälla från",false)]},
    "sex_testamente":{ic:"📜",n:"Testamente",cat:"se_familj",tier:"komplex",q:[Pe("testator","Testator: fullständigt namn och personnummer",true),Pe("civilstand","Ditt civilstånd",true,["Ogift","Gift","Sambo","Skild","Änka/änkling"]),Pe("arvingar","Vem eller vilka ska ärva och vad ska var och en få?",true),Pe("enskild","Ska arvet vara mottagarens enskilda egendom?",false,["Ja","Nej"]),Pe("sarskilda","Särskilda önskemål (testamentsexekutor, legat, villkor)",false),Pe("notering","OBS: testamentet måste undertecknas och bevittnas av två samtidigt närvarande vittnen",false)]},
    "sex_framtidsfullmakt":{ic:"🖊️",n:"Framtidsfullmakt",cat:"se_familj",tier:"standard",q:[Pe("givare","Fullmaktsgivare: fullständigt namn och personnummer",true),Pe("havare","Fullmaktshavare: fullständigt namn och personnummer",true),Pe("omfattning","Vad ska fullmakten omfatta?",true,["Ekonomiska angelägenheter","Personliga angelägenheter","Både ekonomiska och personliga angelägenheter"]),Pe("granskare","Eventuell granskare (namn) – kan lämnas tomt",false),Pe("ersattare","Eventuell ersättare om fullmaktshavaren inte kan",false),Pe("notering","OBS: fullmakten måste undertecknas och bevittnas av två personer",false)]},
    "sex_uppsagning_hyresavtal":{ic:"📤",n:"Uppsägning av hyresavtal (hyresgäst)",cat:"se_boende",tier:"einfach",q:[Pe("hyresvard","Hyresvärd: namn",true),Pe("hyresgast","Ditt namn",true),Pe("adress","Lägenhetens adress och lägenhetsnummer",true),Pe("sistadag","Sista hyresdag (med hänsyn till uppsägningstiden, normalt tre månader till månadsskifte)",true),Pe("kontakt","Din kontaktuppgift (adress/telefon för bekräftelse)",false),Pe("notering","OBS: säg upp skriftligt och spara bevis på att uppsägningen skickats",false)]},
    "sex_andrahandsuthyrning":{ic:"🔑",n:"Avtal om andrahandsuthyrning",cat:"se_boende",tier:"standard",q:[Pe("uthyrare","Förstahandshyresgäst (uthyrare): namn och personnummer",true),Pe("hyresgast","Andrahandshyresgäst: namn och personnummer",true),Pe("adress","Bostadens adress och lägenhetsnummer",true),Pe("period","Hyresperiod (från och med – till och med)",true),Pe("hyra","Månadshyra i kronor, inklusive eventuella tillägg",true),Pe("mobler","Hyrs bostaden ut möblerad eller omöblerad?",false,["Möblerad","Omöblerad"])]},
    "sex_begaran_reparation":{ic:"🔧",n:"Begäran om reparation till hyresvärd",cat:"se_boende",tier:"einfach",q:[Pe("hyresvard","Hyresvärd: namn",true),Pe("adress","Din adress och lägenhetsnummer",true),Pe("fel","Beskriv felet eller bristen och när det upptäcktes",true),Pe("paverkan","Hur påverkar felet ditt boende?",false),Pe("krav","Vad begär du?",true,["Reparation/åtgärd","Åtgärd och nedsättning av hyra","Åtgärd och skadestånd"]),Pe("frist","Skälig tidsfrist du ger för åtgärd",false)]},
    "sex_atervand_deposition":{ic:"💰",n:"Krav på återbetalning av deposition",cat:"se_boende",tier:"einfach",q:[Pe("hyresvard","Hyresvärd: namn",true),Pe("adress","Bostadens adress",true),Pe("belopp","Depositionsbelopp som ska återbetalas (kr)",true),Pe("utflytt","Datum då du flyttade ut och lämnade tillbaka nycklarna",true),Pe("konto","Ditt kontonummer för återbetalning",false),Pe("frist","Tidsfrist du ger för återbetalning",false)]},
    "sex_egen_uppsagning":{ic:"📝",n:"Egen uppsägning (anställning)",cat:"se_arbete",tier:"einfach",q:[Pe("arbetsgivare","Arbetsgivare: företagsnamn",true),Pe("namn","Ditt namn och personnummer",true),Pe("befattning","Din befattning/tjänst",true),Pe("sistadag","Sista anställningsdag (med hänsyn till uppsägningstiden)",true),Pe("halsning","Eventuell avslutande hälsning (frivilligt)",false)]},
    "sex_bestridande_uppsagning":{ic:"⚖️",n:"Bestridande av uppsägning (ogiltigförklaring)",cat:"se_arbete",tier:"komplex",q:[Pe("arbetsgivare","Arbetsgivare: företagsnamn och organisationsnummer",true),Pe("namn","Ditt namn och personnummer",true),Pe("uppsagningsdatum","Datum då du fick uppsägningen",true),Pe("grund","Vilken grund angav arbetsgivaren?",true,["Arbetsbrist","Personliga skäl","Ingen grund angavs"]),Pe("skal","Varför anser du att uppsägningen är felaktig?",true),Pe("yrkande","Vad yrkar du?",true,["Ogiltigförklaring av uppsägningen","Skadestånd","Både ogiltigförklaring och skadestånd"])]},
    "sex_krav_innestaende_lon":{ic:"💸",n:"Krav på innestående lön och semesterersättning",cat:"se_arbete",tier:"standard",q:[Pe("arbetsgivare","Arbetsgivare: företagsnamn och organisationsnummer",true),Pe("namn","Ditt namn och personnummer",true),Pe("period","Vilken period avser den obetalda lönen?",true),Pe("belopp","Belopp du kräver (kr) – lön och/eller semesterersättning",true),Pe("forfallodag","Förfallodag/lönedag som passerats",false),Pe("frist","Tidsfrist du ger för betalning",false)]},
    "sex_begaran_arbetsgivarintyg":{ic:"📄",n:"Begäran om arbetsgivarintyg",cat:"se_arbete",tier:"einfach",q:[Pe("arbetsgivare","Arbetsgivare: företagsnamn",true),Pe("namn","Ditt namn och personnummer",true),Pe("anstallningstid","Din anställningsperiod (från–till)",true),Pe("syfte","Syfte (t.ex. ansökan om a-kassa)",false),Pe("leverans","Hur vill du få intyget?",false,["Digitalt via arbetsgivarintyg.nu","Post","E-post"])]},
    "sex_reklamation_vara":{ic:"🛒",n:"Reklamation av felaktig vara",cat:"se_konsument",tier:"standard",q:[Pe("saljare","Säljare/butik: namn",true),Pe("vara","Vara och inköpsdatum",true),Pe("fel","Beskriv felet och när det upptäcktes",true),Pe("krav","Vad kräver du?",true,["Reparation","Omleverans/ny vara","Prisavdrag","Hävning och återbetalning"]),Pe("kopbevis","Har du kvitto eller orderbekräftelse?",false,["Ja","Nej"]),Pe("kontakt","Dina kontaktuppgifter",false)]},
    "sex_bestridande_faktura":{ic:"🚫",n:"Bestridande av faktura och inkassokrav",cat:"se_konsument",tier:"einfach",q:[Pe("motpart","Företag/borgenär som skickat fakturan",true),Pe("faktura","Fakturanummer och belopp",true),Pe("namn","Ditt namn och person-/kundnummer",true),Pe("grund","Varför bestrider du fakturan?",true,["Har inte beställt/ingått avtal","Varan eller tjänsten är inte levererad","Felaktigt belopp","Redan betald","Annat"]),Pe("forklaring","Förklara närmare varför fakturan bestrids",true),Pe("notering","OBS: en bestriden faktura får inte drivas in som ostridig hos Kronofogden",false)]},
    "sex_gdpr_registerutdrag":{ic:"🔐",n:"Begäran om registerutdrag (GDPR)",cat:"se_konsument",tier:"einfach",q:[Pe("mottagare","Företag/organisation (personuppgiftsansvarig)",true),Pe("namn","Ditt namn",true),Pe("identifiering","Uppgifter för att identifiera dig (t.ex. kundnummer, e-post)",true),Pe("omfattning","Vad vill du ha ut?",false,["Alla mina personuppgifter","Specifika uppgifter (ange nedan)"]),Pe("specifikt","Om specifika uppgifter – ange vilka",false),Pe("leverans","Hur vill du få utdraget?",false,["Digitalt/e-post","Post"])]},
    "sex_overklagande_beslut":{ic:"🏛️",n:"Överklagande av myndighetsbeslut",cat:"se_konsument",tier:"komplex",q:[Pe("myndighet","Myndighet som fattat beslutet",true),Pe("beslut","Beslutets diarienummer/beteckning och datum",true),Pe("namn","Ditt namn och personnummer",true),Pe("delgivning","Datum då du fick del av beslutet (avgör tre veckors överklagandetid)",true),Pe("skal","Varför anser du att beslutet är felaktigt?",true),Pe("yrkande","Hur vill du att beslutet ändras?",true)]}
  };
  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      if(typeof CATS==="undefined"||!CATS) return false;
      for(var k in E){ if(!DOCS[k]) DOCS[k]=E[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=E[k].tier||"standard"; }
      for(var nc in NEWCATS){ if(!CATS[nc]) CATS[nc]={n:NEWCATS[nc].n,ic:NEWCATS[nc].ic,docs:[],country:"SE"}; if(!CATS[nc].docs) CATS[nc].docs=[]; }
      for(var k2 in E){ var c2=E[k2].cat; if(CATS[c2]&&CATS[c2].docs&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in NEWCATS) CAT_LABELS[cl]=NEWCATS[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in NEWCATS){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in E){ var c3=E[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:E[k3].ic,t:E[k3].n,tier:E[k3].tier||"standard"}); } }
      }
      return !!(DOCS.sex_aktenskapsforord);
    }catch(e){ return false; }
  }
  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}

CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
$tmp = tempnam(sys_get_temp_dir(),'se').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-semore-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "  ✓ SE: +16 belge, 4 kategori eklendi\n\n✓ CH_SE_EXPAND_MORE uygulandi.\n";
