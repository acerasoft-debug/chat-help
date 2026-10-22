<?php
/** ChatHelp — apply-pl-expand-more (CH_PL_EXPAND_MORE) — PL katalog genisletme
 *  Workflow ile uretildi: +16 belge, 8 kategori. Deferred injection.
 *  KULLANIM: pull-updates.php?files=apply-pl-expand-more.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-pl-expand-more BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_PL_EXPAND_MORE')!==false) exit("Zaten ekli (CH_PL_EXPAND_MORE).\n");
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if (strpos($src,$anchor)===false) exit("HATA: anchor (getDocPrice) yok.\n");
$js = <<<'CHJS'

/* CH_PL_EXPAND_MORE — PL katalog genisletme (workflow, deferred) */
try{(function(){
  function Pe(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var NEWCATS={
    "pl_rodzina":{n:"Rodzina i alimenty",ic:"👨‍👩‍👧"},
    "pl_spadki":{n:"Spadki i testamenty",ic:"📜"},
    "pl_dlugi":{n:"Długi i windykacja",ic:"💸"},
    "pl_sady":{n:"Pozwy i pisma sądowe",ic:"⚖️"},
    "pl_mieszkanie":{n:"Mieszkanie i najem",ic:"🏠"},
    "pl_praca":{n:"Praca i zatrudnienie",ic:"💼"},
    "pl_konsument":{n:"Konsument i spory",ic:"🛒"},
    "pl_urzedy":{n:"Urzędy i instytucje",ic:"🏛️"}
  };
  var E={
    "plx_pozew_o_alimenty":{ic:"💶",n:"Pozew o alimenty",cat:"pl_rodzina",tier:"komplex",q:[Pe("pozwany","Pozwany (zobowiązany do alimentów): imię, nazwisko i adres",true),Pe("uprawniony","Na czyją rzecz alimenty: imię, nazwisko i data urodzenia dziecka/osoby",true),Pe("kwota","Żądana kwota alimentów miesięcznie (zł)",true),Pe("potrzeby","Uzasadnione potrzeby uprawnionego (koszty utrzymania, wyżywienie, leczenie, edukacja)",true),Pe("mozliwosci","Zarobkowe i majątkowe możliwości pozwanego (co Panu/Pani wiadomo)",false),Pe("wnioskodawca","Pana/Pani (przedstawiciela ustawowego) imię, nazwisko, PESEL i adres",true)]},
    "plx_zgoda_na_wyjazd_dziecka":{ic:"✈️",n:"Zgoda na wyjazd dziecka za granicę",cat:"pl_rodzina",tier:"standard",q:[Pe("dziecko","Dziecko: imię, nazwisko i data urodzenia",true),Pe("rodzic","Rodzic udzielający zgody: imię, nazwisko i numer dokumentu tożsamości",true),Pe("opiekun","Osoba, pod której opieką dziecko wyjeżdża (imię, nazwisko, pokrewieństwo)",false),Pe("kraj","Kraj lub kraje docelowe",true),Pe("termin","Termin wyjazdu (od–do)",true)]},
    "plx_pozew_o_rozwod":{ic:"💔",n:"Pozew o rozwód",cat:"pl_rodzina",tier:"komplex",q:[Pe("malzonek","Współmałżonek (pozwany): imię, nazwisko i adres",true),Pe("slub","Data i miejsce zawarcia małżeństwa",true),Pe("wina","Żądanie co do winy w rozkładzie pożycia",true,["Bez orzekania o winie","Z winy współmałżonka","Z winy obu stron"]),Pe("dzieci","Małoletnie dzieci (imiona i wiek) lub wpisz 'brak'",true),Pe("ustalenia","Wnioski dotyczące władzy rodzicielskiej, kontaktów i alimentów (jeśli są dzieci)",false),Pe("powod","Pana/Pani imię, nazwisko, PESEL i adres",true)]},
    "plx_odrzucenie_spadku":{ic:"🚫",n:"Oświadczenie o odrzuceniu spadku",cat:"pl_spadki",tier:"standard",q:[Pe("spadkodawca","Spadkodawca (zmarły): imię, nazwisko, data i miejsce śmierci",true),Pe("pokrewienstwo","Pana/Pani pokrewieństwo ze spadkodawcą",true),Pe("data_dowiedzenia","Data, w której dowiedział(a) się Pan/Pani o powołaniu do spadku (termin: 6 miesięcy!)",true),Pe("maloletni","Czy odrzucenie dotyczy również małoletniego dziecka?",false,["Nie","Tak"]),Pe("dane","Pana/Pani imię, nazwisko, PESEL i adres",true),Pe("nota","UWAGA: oświadczenie składa się przed notariuszem lub w sądzie rejonowym",false)]},
    "plx_wniosek_o_stwierdzenie_nabycia_spadku":{ic:"🧾",n:"Wniosek o stwierdzenie nabycia spadku",cat:"pl_spadki",tier:"komplex",q:[Pe("spadkodawca","Spadkodawca: imię, nazwisko, ostatni adres i data śmierci",true),Pe("testament","Czy spadkodawca pozostawił testament?",true,["Nie – dziedziczenie ustawowe","Tak – testament"]),Pe("spadkobiercy","Spadkobiercy (imiona, nazwiska, pokrewieństwo, adresy)",true),Pe("majatek","Główne składniki majątku spadkowego (nieruchomości, konta, pojazdy)",false),Pe("wnioskodawca","Pana/Pani imię, nazwisko, PESEL i adres",true)]},
    "plx_testament_wlasnoreczny":{ic:"✍️",n:"Testament własnoręczny (wzór)",cat:"pl_spadki",tier:"standard",q:[Pe("testator","Pana/Pani imię, nazwisko, PESEL i adres",true),Pe("spadkobiercy","Kogo Pan/Pani powołuje do spadku (imiona, nazwiska i udziały)",true),Pe("zapisy","Zapisy szczególne – konkretne rzeczy dla konkretnych osób (opcjonalnie)",false),Pe("wydziedziczenie","Czy kogoś Pan/Pani wydziedzicza (ze wskazaniem przyczyny)?",false),Pe("nota","UWAGA: testament własnoręczny musi być w całości napisany ręcznie, opatrzony datą i podpisany",false)]},
    "plx_sprzeciw_od_nakazu_zaplaty":{ic:"⚖️",n:"Sprzeciw od nakazu zapłaty",cat:"pl_dlugi",tier:"komplex",q:[Pe("sad","Sąd, który wydał nakaz, i sygnatura akt",true),Pe("data_doreczenia","Data doręczenia nakazu zapłaty (termin na sprzeciw: 14 dni!)",true),Pe("powod","Powód z nakazu (wierzyciel lub firma windykacyjna)",true),Pe("kwota","Kwota objęta nakazem (zł)",true),Pe("zarzuty","Zarzuty wobec roszczenia (spłacone, przedawnione, nienależne, zawyżone…)",true),Pe("dane","Pana/Pani imię, nazwisko, PESEL i adres",true)]},
    "plx_wniosek_o_rozlozenie_na_raty":{ic:"📆",n:"Wniosek o rozłożenie zadłużenia na raty",cat:"pl_dlugi",tier:"standard",q:[Pe("wierzyciel","Wierzyciel: nazwa i adres",true),Pe("podstawa","Podstawa zadłużenia (umowa, faktura, numer sprawy)",true),Pe("kwota","Łączna kwota zadłużenia (zł)",true),Pe("propozycja","Proponowana wysokość i liczba rat",true),Pe("sytuacja","Opis sytuacji finansowej uzasadniający prośbę",true),Pe("dane","Pana/Pani imię, nazwisko i adres",true)]},
    "plx_zarzut_przedawnienia":{ic:"⏳",n:"Zarzut przedawnienia roszczenia",cat:"pl_dlugi",tier:"standard",q:[Pe("wierzyciel","Wierzyciel lub windykator: nazwa i adres",true),Pe("roszczenie","Roszczenie, którego dotyczy zarzut (numer sprawy, rodzaj długu)",true),Pe("data_wymagalnosci","Kiedy roszczenie stało się wymagalne (data lub rok)",true),Pe("kwota","Kwota żądana przez wierzyciela (zł)",false),Pe("dane","Pana/Pani imię, nazwisko i adres",true)]},
    "plx_pozew_o_zaplate":{ic:"📨",n:"Pozew o zapłatę",cat:"pl_sady",tier:"komplex",q:[Pe("pozwany","Pozwany (dłużnik): imię/nazwa, adres oraz PESEL/NIP (jeśli znany)",true),Pe("podstawa","Podstawa roszczenia (umowa, faktura, pożyczka)",true),Pe("kwota","Kwota główna (zł) oraz odsetki, jeśli są żądane",true),Pe("termin","Termin zapłaty, który upłynął (data)",true),Pe("wezwanie","Czy wysłano wcześniej wezwanie do zapłaty?",false,["Tak","Nie"]),Pe("powod","Pana/Pani (powoda) imię/nazwa, adres i PESEL/NIP",true)]},
    "plx_wniosek_o_zwolnienie_od_kosztow_sadowych":{ic:"🆓",n:"Wniosek o zwolnienie od kosztów sądowych",cat:"pl_sady",tier:"standard",q:[Pe("sad","Sąd i sygnatura sprawy (jeśli została już nadana)",true),Pe("zakres","Zakres wniosku",true,["Całkowite zwolnienie od kosztów","Częściowe zwolnienie"]),Pe("dochody","Miesięczne dochody Pana/Pani gospodarstwa domowego (zł)",true),Pe("wydatki","Stałe wydatki i liczba osób na utrzymaniu",true),Pe("dane","Pana/Pani imię, nazwisko, PESEL i adres",true)]},
    "plx_pozew_o_eksmisje":{ic:"🚪",n:"Pozew o eksmisję najemcy",cat:"pl_mieszkanie",tier:"komplex",q:[Pe("pozwany","Osoba do eksmisji: imię, nazwisko i adres lokalu",true),Pe("lokal","Adres lokalu i Pana/Pani tytuł prawny (własność, zarząd)",true),Pe("podstawa","Podstawa eksmisji (zaległy czynsz, wygaśnięcie najmu, zakłócanie porządku)",true),Pe("wypowiedzenie","Czy umowa została wypowiedziana i czy wezwano do opuszczenia lokalu?",false,["Tak","Nie"]),Pe("osoby_chronione","Czy w lokalu mieszkają dzieci lub osoby chronione (prawo do lokalu socjalnego)?",false),Pe("powod","Pana/Pani (właściciela) imię, nazwisko i adres",true)]},
    "plx_protokol_zdawczo_odbiorczy":{ic:"📋",n:"Protokół zdawczo-odbiorczy mieszkania",cat:"pl_mieszkanie",tier:"standard",q:[Pe("strony","Wynajmujący i najemca: imiona i nazwiska",true),Pe("adres","Adres mieszkania",true),Pe("data","Data przekazania / zdania lokalu",true),Pe("liczniki","Stany liczników (prąd, gaz, woda)",true),Pe("stan","Stan techniczny i wyposażenie lokalu (opis, ewentualne usterki)",true),Pe("klucze","Liczba przekazanych kluczy i innych elementów (piloty, karty)",false)]},
    "plx_zawiadomienie_o_mobbingu":{ic:"🚨",n:"Zawiadomienie pracodawcy o mobbingu lub dyskryminacji",cat:"pl_praca",tier:"komplex",q:[Pe("pracodawca","Pracodawca: nazwa i adres",true),Pe("sprawca","Osoba dopuszczająca się mobbingu/dyskryminacji (imię, stanowisko)",true),Pe("opis","Opis zachowań: co się dzieje, kiedy i jak często",true),Pe("swiadkowie","Świadkowie lub dowody (e-maile, nagrania) – jeśli są",false),Pe("zadanie","Pana/Pani żądanie (zaprzestanie, przeciwdziałanie, odszkodowanie)",true),Pe("dane","Pana/Pani imię, nazwisko i stanowisko",true)]},
    "plx_zadanie_usuniecia_danych_rodo":{ic:"🔒",n:"Żądanie realizacji praw z RODO (dane osobowe)",cat:"pl_konsument",tier:"standard",q:[Pe("administrator","Administrator danych (firma/instytucja): nazwa i adres",true),Pe("prawo","Z którego prawa Pan/Pani korzysta",true,["Dostęp do danych","Usunięcie danych (prawo do bycia zapomnianym)","Sprostowanie danych","Sprzeciw wobec przetwarzania","Ograniczenie przetwarzania","Przeniesienie danych"]),Pe("relacja","Pana/Pani relacja z administratorem (klient, były pracownik, użytkownik…)",true),Pe("zakres","Jakich danych dotyczy żądanie (jeśli konkretne)",false),Pe("dane","Pana/Pani imię, nazwisko oraz adres lub e-mail do kontaktu",true)]},
    "plx_wniosek_o_dostep_do_informacji_publicznej":{ic:"📢",n:"Wniosek o dostęp do informacji publicznej",cat:"pl_urzedy",tier:"standard",q:[Pe("organ","Organ lub instytucja, do której kierowany jest wniosek",true),Pe("informacja","Jakiej informacji publicznej Pan/Pani żąda (dokładny opis)",true),Pe("forma","Preferowana forma udostępnienia",false,["Kopia elektroniczna (e-mail)","Kopia papierowa","Wgląd na miejscu"]),Pe("dane","Pana/Pani imię, nazwisko i dane kontaktowe",true)]}
  };
  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      if(typeof CATS==="undefined"||!CATS) return false;
      for(var k in E){ if(!DOCS[k]) DOCS[k]=E[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=E[k].tier||"standard"; }
      for(var nc in NEWCATS){ if(!CATS[nc]) CATS[nc]={n:NEWCATS[nc].n,ic:NEWCATS[nc].ic,docs:[],country:"PL"}; if(!CATS[nc].docs) CATS[nc].docs=[]; }
      for(var k2 in E){ var c2=E[k2].cat; if(CATS[c2]&&CATS[c2].docs&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in NEWCATS) CAT_LABELS[cl]=NEWCATS[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in NEWCATS){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in E){ var c3=E[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:E[k3].ic,t:E[k3].n,tier:E[k3].tier||"standard"}); } }
      }
      return !!(DOCS.plx_pozew_o_alimenty);
    }catch(e){ return false; }
  }
  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}

CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
$tmp = tempnam(sys_get_temp_dir(),'pl').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-plmore-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "  ✓ PL: +16 belge, 8 kategori eklendi\n\n✓ CH_PL_EXPAND_MORE uygulandi.\n";
