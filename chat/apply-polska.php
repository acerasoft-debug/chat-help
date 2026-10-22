<?php
/**
 * ChatHelp — POLONYA MODÜLÜ (~36 belge, 6 kategori, Lehçe) — CH_TR_MODULE ile aynı kanıtlanmış desen
 * -------------------------------------------------------------------------------------------------------
 *  • Kategoriler country:'PL' etiketli -> genelleştirilmiş ülke filtresi (CH_TR_MODULE) otomatik çalışır
 *  • Welcome grid kartları data-cccountry='PL' -> kart görünürlüğü otomatik
 *  • Dil: setCountry('PL') zaten UL='pl' yapıyor (CC_DATA.PL.l) — ek dil kodu gerekmez
 *  • Belge dili/hukuku: api.php'de lawSystems['PL'] + doc-lang PL->pl (apply-lawsys-all.php ile)
 *
 * KULLANIM: pull-updates.php?files=apply-polska.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-polska BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_PL_MODULE")!==false) exit("Zaten ekli (CH_PL_MODULE).\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-pl-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_PL_MODULE — Polonya hukuku modülü (deferred; DOCS/CATS hazır olunca yüklenir) */
try{(function(){
  function Pq(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var XC={
    pl_wypowiedzenia:{n:"Wypowiedzenia i rezygnacje",ic:"📄"},
    pl_mieszkanie:{n:"Mieszkanie i najem",ic:"🏠"},
    pl_praca:{n:"Praca i zatrudnienie",ic:"💼"},
    pl_konsument:{n:"Konsument i spory",ic:"🛒"},
    pl_umowy:{n:"Umowy",ic:"📝"},
    pl_urzedy:{n:"Urzędy i instytucje",ic:"🏛️"}
  };
  var X_ORDER=["pl_wypowiedzenia","pl_mieszkanie","pl_praca","pl_konsument","pl_umowy","pl_urzedy"];
  var CCODE="PL", DOCWORD="dokumentów";

  var D={
    /* Wypowiedzenia i rezygnacje */
    pl_wypowiedzenie_telefon:{ic:"📱",n:"Wypowiedzenie umowy o telefon / internet",cat:"pl_wypowiedzenia",tier:"einfach",q:[Pq("operator","Operator: nazwa i adres",true),Pq("nr_klienta","Nr klienta / nr umowy",true),Pq("lojalka","Czy umowa ma okres zobowiązania (lojalkę)?",false,["Nie","Tak","Nie wiem"]),Pq("data","Pożądana data rozwiązania umowy",false),Pq("dane","Pana/Pani imię, nazwisko i adres",true)]},
    pl_rezygnacja_silownia:{ic:"🏋️",n:"Rezygnacja z siłowni / klubu fitness",cat:"pl_wypowiedzenia",tier:"einfach",q:[Pq("klub","Klub: nazwa i adres",true),Pq("nr_czlonka","Nr członkowski",false),Pq("data","Data rezygnacji",true),Pq("powod","Powód (opcjonalnie)",false),Pq("dane","Pana/Pani imię i nazwisko",true)]},
    pl_wypowiedzenie_oc:{ic:"🛡️",n:"Wypowiedzenie ubezpieczenia OC",cat:"pl_wypowiedzenia",tier:"einfach",q:[Pq("ubezpieczyciel","Ubezpieczyciel: nazwa i adres",true),Pq("nr_polisy","Nr polisy",true),Pq("tryb","Tryb wypowiedzenia",true,["Koniec okresu ubezpieczenia","Podwójne OC","Polisa po zbywcy pojazdu"]),Pq("pojazd","Pojazd: marka i nr rejestracyjny",true),Pq("dane","Pana/Pani imię, nazwisko i PESEL",true)]},
    pl_zamkniecie_konta:{ic:"🏦",n:"Zamknięcie konta bankowego",cat:"pl_wypowiedzenia",tier:"einfach",q:[Pq("bank","Bank: nazwa i oddział",true),Pq("nr_konta","Numer konta (IBAN)",true),Pq("przelew","Nr konta do przelania salda (opcjonalnie)",false),Pq("dane","Pana/Pani imię, nazwisko i PESEL",true)]},
    pl_rezygnacja_subskrypcja:{ic:"📺",n:"Rezygnacja z subskrypcji cyfrowej",cat:"pl_wypowiedzenia",tier:"einfach",q:[Pq("platforma","Platforma (Netflix, Spotify…)",true),Pq("konto","E-mail konta",true),Pq("data","Data rezygnacji (opcjonalnie)",false),Pq("dane","Pana/Pani imię i nazwisko",true)]},
    pl_wypowiedzenie_media:{ic:"⚡",n:"Wypowiedzenie umowy na prąd / gaz",cat:"pl_wypowiedzenia",tier:"einfach",q:[Pq("dostawca","Dostawca: nazwa i adres",true),Pq("nr_umowy","Nr umowy / nr klienta",true),Pq("adres","Adres punktu poboru",true),Pq("data","Pożądana data rozwiązania",true),Pq("dane","Pana/Pani imię, nazwisko i adres",true)]},

    /* Mieszkanie i najem */
    pl_wypowiedzenie_najmu:{ic:"🏠",n:"Wypowiedzenie umowy najmu (najemca)",cat:"pl_mieszkanie",tier:"einfach",q:[Pq("wynajmujacy","Wynajmujący: imię/nazwa i adres",true),Pq("adres","Adres wynajmowanego mieszkania",true),Pq("data_konca","Data zakończenia najmu (okres wypowiedzenia z umowy)",true),Pq("dane","Pana/Pani imię, nazwisko i adres",true)]},
    pl_zwrot_kaucji:{ic:"💰",n:"Wezwanie do zwrotu kaucji",cat:"pl_mieszkanie",tier:"standard",q:[Pq("wynajmujacy","Wynajmujący: imię/nazwa i adres",true),Pq("adres","Adres mieszkania",true),Pq("data_zdania","Data zdania kluczy",true),Pq("kwota","Kwota kaucji (zł)",true),Pq("nr_konta","Nr konta do zwrotu",false),Pq("dane","Pana/Pani imię i nazwisko",true)]},
    pl_umowa_najmu:{ic:"📄",n:"Umowa najmu mieszkania",cat:"pl_mieszkanie",tier:"komplex",q:[Pq("rola","Jest Pan/Pani",true,["Wynajmującym","Najemcą"]),Pq("druga_strona","Druga strona: imię, nazwisko i PESEL",true),Pq("adres","Adres mieszkania",true),Pq("powierzchnia","Powierzchnia (m²) i liczba pokoi",false),Pq("poczatek","Data rozpoczęcia najmu",true),Pq("czynsz","Czynsz miesięczny (zł)",true),Pq("kaucja","Kaucja (zł)",false),Pq("oplaty","Opłaty dodatkowe (media, czynsz administracyjny…)",false)]},
    pl_usterki:{ic:"🔧",n:"Zgłoszenie usterek wynajmującemu",cat:"pl_mieszkanie",tier:"standard",q:[Pq("wynajmujacy","Wynajmujący: imię/nazwa i adres",true),Pq("adres","Adres mieszkania",true),Pq("problem","Opis usterki/awarii",true),Pq("pilne","Czy sprawa jest pilna?",false,["Tak","Nie"]),Pq("dane","Pana/Pani imię i nazwisko",true)]},
    pl_sprzeciw_czynsz:{ic:"📈",n:"Sprzeciw wobec podwyżki czynszu",cat:"pl_mieszkanie",tier:"standard",q:[Pq("wynajmujacy","Wynajmujący: imię/nazwa i adres",true),Pq("adres","Adres mieszkania",true),Pq("czynsz_obecny","Obecny czynsz (zł)",true),Pq("czynsz_nowy","Żądany nowy czynsz (zł)",true),Pq("argumenty","Pana/Pani argumenty przeciw podwyżce",true)]},
    pl_halas:{ic:"🔊",n:"Skarga na hałas (sąsiad/wspólnota)",cat:"pl_mieszkanie",tier:"standard",q:[Pq("adresat","Adresat (sąsiad/zarządca/spółdzielnia)",true),Pq("problem","Opis uciążliwości",true),Pq("terminy","Częstotliwość i pory",false),Pq("zadanie","Pana/Pani żądanie",true),Pq("dane","Pana/Pani imię, nazwisko i nr mieszkania",true)]},

    /* Praca i zatrudnienie */
    pl_wypowiedzenie_pracy:{ic:"👋",n:"Wypowiedzenie umowy o pracę (pracownik)",cat:"pl_praca",tier:"einfach",q:[Pq("pracodawca","Pracodawca: nazwa i adres",true),Pq("stanowisko","Pana/Pani stanowisko",true),Pq("ostatni_dzien","Ostatni dzień pracy (okres wypowiedzenia z Kodeksu pracy)",true),Pq("dane","Pana/Pani imię, nazwisko i adres",true)]},
    pl_zalegle_wynagrodzenie:{ic:"🧾",n:"Żądanie zapłaty zaległego wynagrodzenia",cat:"pl_praca",tier:"standard",q:[Pq("pracodawca","Pracodawca: nazwa i adres",true),Pq("stanowisko","Stanowisko i okres zatrudnienia",true),Pq("skladniki","Czego Pan/Pani żąda (pensja, nadgodziny, ekwiwalent za urlop)",true),Pq("kwota","Szacowana kwota (zł)",false),Pq("dane","Pana/Pani imię i nazwisko",true)]},
    pl_wniosek_urlop:{ic:"🏖️",n:"Wniosek o urlop",cat:"pl_praca",tier:"einfach",q:[Pq("pracodawca","Pracodawca/przełożony",true),Pq("rodzaj","Rodzaj urlopu",true,["Wypoczynkowy","Na żądanie","Bezpłatny","Okolicznościowy"]),Pq("od","Od (data)",true),Pq("do","Do (data)",true),Pq("dane","Pana/Pani imię, nazwisko i stanowisko",true)]},
    pl_praca_zdalna:{ic:"🏡",n:"Wniosek o pracę zdalną",cat:"pl_praca",tier:"einfach",q:[Pq("pracodawca","Pracodawca/przełożony",true),Pq("stanowisko","Pana/Pani stanowisko",true),Pq("wymiar","Pożądany wymiar (dni w tygodniu)",true),Pq("powod","Uzasadnienie (opcjonalnie)",false)]},
    pl_swiadectwo_pracy:{ic:"📊",n:"Świadectwo pracy — wniosek / sprostowanie",cat:"pl_praca",tier:"standard",q:[Pq("pracodawca","Pracodawca: nazwa i adres",true),Pq("cel","Cel pisma",true,["Wydanie świadectwa","Sprostowanie świadectwa"]),Pq("okres","Okres zatrudnienia i stanowisko",true),Pq("bledy","Co wymaga sprostowania (jeśli dotyczy)",false),Pq("dane","Pana/Pani imię, nazwisko i adres",true)]},
    pl_odwolanie_wypowiedzenia:{ic:"⚖️",n:"Odwołanie od wypowiedzenia (sąd pracy)",cat:"pl_praca",tier:"komplex",q:[Pq("pracodawca","Pozwany pracodawca: nazwa, NIP i adres",true),Pq("zatrudnienie","Stanowisko i okres zatrudnienia",true),Pq("wypowiedzenie","Data doręczenia wypowiedzenia (termin: 21 dni!) i podana przyczyna",true),Pq("zarzuty","Dlaczego wypowiedzenie jest wadliwe/nieuzasadnione",true),Pq("zadanie","Żądanie (przywrócenie do pracy / odszkodowanie zł)",true),Pq("dane","Pana/Pani imię, nazwisko, PESEL i adres",true)]},

    /* Konsument i spory */
    pl_odstapienie_14dni:{ic:"🔄",n:"Odstąpienie od umowy (14 dni)",cat:"pl_konsument",tier:"einfach",q:[Pq("sprzedawca","Sprzedawca: nazwa i adres",true),Pq("zamowienie","Nr zamówienia",true),Pq("data","Data zakupu/odbioru",true),Pq("produkty","Produkty, których dotyczy odstąpienie",true),Pq("nr_konta","Nr konta do zwrotu pieniędzy (opcjonalnie)",false)]},
    pl_reklamacja_towaru:{ic:"🔧",n:"Reklamacja towaru (niezgodność z umową)",cat:"pl_konsument",tier:"standard",q:[Pq("sprzedawca","Sprzedawca: nazwa i adres",true),Pq("produkt","Produkt (marka/model)",true),Pq("data_zakupu","Data zakupu",true),Pq("wada","Opis wady",true),Pq("zadanie","Pana/Pani żądanie",true,["Naprawa","Wymiana","Obniżenie ceny","Zwrot pieniędzy"])]},
    pl_reklamacja_lotu:{ic:"✈️",n:"Reklamacja lotu (rozp. UE 261/2004)",cat:"pl_konsument",tier:"standard",q:[Pq("linia","Linia lotnicza",true),Pq("lot","Nr lotu i data",true),Pq("problem","Problem",true,["Opóźnienie","Odwołanie","Overbooking","Bagaż"]),Pq("zadanie","Żądanie (odszkodowanie wg rozp. UE 261/2004…)",true),Pq("dane","Pana/Pani imię i nazwisko",true)]},
    pl_wezwanie_do_zaplaty:{ic:"📨",n:"Wezwanie do zapłaty",cat:"pl_konsument",tier:"standard",q:[Pq("dluznik","Dłużnik: imię/nazwa i adres",true),Pq("podstawa","Podstawa roszczenia (faktura, umowa, pożyczka…)",true),Pq("kwota","Kwota (zł)",true),Pq("termin","Termin zapłaty (np. 7 dni)",true),Pq("nr_konta","Nr konta do wpłaty",true),Pq("dane","Pana/Pani (wierzyciela) imię/nazwa i adres",true)]},
    pl_chargeback:{ic:"💳",n:"Chargeback / reklamacja bankowa",cat:"pl_konsument",tier:"standard",q:[Pq("bank","Bank: nazwa",true),Pq("transakcja","Transakcja (data, kwota zł, odbiorca)",true),Pq("powod","Powód reklamacji (brak towaru, oszustwo, podwójne obciążenie…)",true),Pq("kontakt","Czy kontaktował(a) się Pan/Pani ze sprzedawcą?",false,["Tak","Nie"]),Pq("dane","Pana/Pani imię, nazwisko i 4 ostatnie cyfry karty/konta",true)]},
    pl_skarga_uokik:{ic:"🛒",n:"Skarga do UOKiK / rzecznika konsumentów",cat:"pl_konsument",tier:"standard",q:[Pq("adresat","Adresat",true,["Powiatowy/miejski rzecznik konsumentów","UOKiK"]),Pq("przedsiebiorca","Przedsiębiorca: nazwa i adres",true),Pq("opis","Opis sprawy (co się stało, kiedy)",true),Pq("zadanie","Pana/Pani żądanie",true),Pq("dane","Pana/Pani imię, nazwisko i adres",true)]},

    /* Umowy */
    pl_umowa_pozyczki:{ic:"🤝",n:"Umowa pożyczki",cat:"pl_umowy",tier:"standard",q:[Pq("pozyczkodawca","Pożyczkodawca: imię, nazwisko i PESEL",true),Pq("pozyczkobiorca","Pożyczkobiorca: imię, nazwisko i PESEL",true),Pq("kwota","Kwota pożyczki (zł)",true),Pq("splata","Plan spłaty",true),Pq("odsetki","Odsetki (%, opcjonalnie)",false)]},
    pl_umowa_sprzedazy:{ic:"🛒",n:"Umowa sprzedaży między osobami prywatnymi",cat:"pl_umowy",tier:"einfach",q:[Pq("rola","Jest Pan/Pani",true,["Sprzedającym","Kupującym"]),Pq("druga_strona","Druga strona: imię, nazwisko i PESEL",true),Pq("przedmiot","Przedmiot sprzedaży",true),Pq("cena","Cena (zł)",true),Pq("wydanie","Data wydania rzeczy",false)]},
    pl_uznanie_dlugu:{ic:"💰",n:"Uznanie długu",cat:"pl_umowy",tier:"standard",q:[Pq("wierzyciel","Wierzyciel: imię/nazwa i adres",true),Pq("dluznik","Dłużnik: imię/nazwa i adres",true),Pq("kwota","Kwota (zł, cyframi i słownie)",true),Pq("splata","Termin/sposób spłaty",true),Pq("odsetki","Czy z odsetkami?",false,["Bez odsetek","Z odsetkami"])]},
    pl_pelnomocnictwo:{ic:"📝",n:"Pełnomocnictwo",cat:"pl_umowy",tier:"einfach",q:[Pq("pelnomocnik","Pełnomocnik: imię, nazwisko i PESEL",true),Pq("zakres","Do czego upoważnia pełnomocnictwo",true),Pq("okres","Okres obowiązywania (opcjonalnie)",false),Pq("dane","Pana/Pani (mocodawcy) imię, nazwisko i PESEL",true)]},
    pl_nda:{ic:"🔒",n:"Umowa o poufności (NDA)",cat:"pl_umowy",tier:"standard",q:[Pq("druga_strona","Druga strona: nazwa i adres",true),Pq("przedmiot","Przedmiot współpracy",true),Pq("zakres","Zakres informacji poufnych",true),Pq("okres","Okres obowiązywania (opcjonalnie)",false)]},
    pl_umowa_dzielo:{ic:"🧰",n:"Umowa o dzieło / zlecenie",cat:"pl_umowy",tier:"komplex",q:[Pq("rodzaj","Rodzaj umowy",true,["Umowa o dzieło","Umowa zlecenie"]),Pq("rola","Jest Pan/Pani",true,["Zamawiającym/Zleceniodawcą","Wykonawcą/Zleceniobiorcą"]),Pq("druga_strona","Druga strona: imię/nazwa i NIP/PESEL",true),Pq("przedmiot","Opis dzieła/zlecenia",true),Pq("wynagrodzenie","Wynagrodzenie i sposób zapłaty (zł)",true),Pq("termin","Termin wykonania",false),Pq("klauzule","Postanowienia dodatkowe (opcjonalnie)",false)]},

    /* Urzędy i instytucje */
    pl_odwolanie_mandat:{ic:"🚗",n:"Odwołanie od mandatu / wniosek o uchylenie",cat:"pl_urzedy",tier:"standard",q:[Pq("organ","Organ (policja/straż miejska) i sygnatura sprawy",true),Pq("data","Data zdarzenia i nałożenia mandatu",true),Pq("rejestracja","Nr rejestracyjny (jeśli dotyczy)",false),Pq("argumenty","Argumenty odwołania",true),Pq("dane","Pana/Pani imię, nazwisko i PESEL",true)]},
    pl_urzad_skarbowy:{ic:"🧾",n:"Pismo do urzędu skarbowego (czynny żal / raty)",cat:"pl_urzedy",tier:"komplex",q:[Pq("urzad","Urząd skarbowy (nazwa i adres)",true),Pq("rodzaj","Rodzaj pisma",true,["Czynny żal","Wniosek o rozłożenie na raty","Wniosek o umorzenie/odroczenie"]),Pq("sprawa","Czego dotyczy (podatek, okres, kwota zł)",true),Pq("uzasadnienie","Uzasadnienie / opis sytuacji",true),Pq("dane","Pana/Pani imię, nazwisko, NIP/PESEL i adres",true)]},
    pl_wniosek_zus:{ic:"🏥",n:"Wniosek do ZUS",cat:"pl_urzedy",tier:"standard",q:[Pq("oddzial","Oddział ZUS",true),Pq("przedmiot","Przedmiot (zasiłek, emerytura, zaświadczenie, raty składek…)",true),Pq("opis","Opis sprawy",true),Pq("zadanie","Pana/Pani żądanie",true),Pq("dane","Pana/Pani imię, nazwisko, PESEL i adres",true)]},
    pl_zameldowanie:{ic:"🏠",n:"Zameldowanie / przemeldowanie",cat:"pl_urzedy",tier:"einfach",q:[Pq("urzad","Urząd gminy/miasta",true),Pq("adres","Adres zameldowania",true),Pq("rodzaj","Rodzaj",true,["Zameldowanie na pobyt stały","Zameldowanie na pobyt czasowy","Wymeldowanie/przemeldowanie"]),Pq("dane","Pana/Pani imię, nazwisko i PESEL",true)]},
    pl_niekaralnosc:{ic:"📊",n:"Wniosek o zaświadczenie o niekaralności",cat:"pl_urzedy",tier:"einfach",q:[Pq("dane","Pana/Pani imię, nazwisko, PESEL i imiona rodziców",true),Pq("cel","Cel zaświadczenia (praca, przetarg…)",false)]},
    pl_skarga_gmina:{ic:"🏛️",n:"Skarga / wniosek do gminy",cat:"pl_urzedy",tier:"standard",q:[Pq("urzad","Urząd gminy/miasta i wydział",true),Pq("przedmiot","Przedmiot skargi/wniosku (droga, oświetlenie, hałas…)",true),Pq("opis","Opis sprawy",true),Pq("zadanie","Pana/Pani żądanie",true),Pq("dane","Pana/Pani imię, nazwisko i adres",true)]}
  };

  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      for(var k in D){ if(!DOCS[k]) DOCS[k]=D[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=D[k].tier||"standard"; }
      if(typeof CATS!=="undefined"&&CATS){
        for(var ck in XC){ if(!CATS[ck]) CATS[ck]={n:XC[ck].n,ic:XC[ck].ic,docs:[],country:CCODE}; if(!CATS[ck].docs) CATS[ck].docs=[]; }
        for(var k2 in D){ var c2=D[k2].cat; if(CATS[c2]&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in XC) CAT_LABELS[cl]=XC[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in XC){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in D){ var c3=D[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:D[k3].ic,t:D[k3].n,tier:D[k3].tier||"standard"}); } }
      }
      return (typeof DOCS!=="undefined" && DOCS.pl_wezwanie_do_zaplaty)?true:false;
    }catch(e){ return false; }
  }

  /* Ülke filtresi (CH_TR_MODULE kuruyor; yoksa aynı korumalı sarmalayıcı burada da kurulur) */
  function installMultiCountryFilter(){
    try{
      if(typeof showAllCats!=="function" || showAllCats.__multiCountry) return;
      var _sac=showAllCats;
      var w=function(){
        try{
          if(typeof CATS==="undefined") return _sac.apply(this,arguments);
          var cur=(typeof CC!=="undefined"?CC:"DE");
          var hid={};
          for(var k in CATS){
            var cc=(CATS[k]&&CATS[k].country)||"DE";
            if(cc!==cur){ hid[k]=CATS[k]; delete CATS[k]; }
          }
          try{ return _sac.apply(this,arguments); } finally { for(var h in hid) CATS[h]=hid[h]; }
        }catch(e){ return _sac.apply(this,arguments); }
      };
      w.__multiCountry=1; showAllCats=w; if(typeof window!=="undefined") window.showAllCats=w;
    }catch(e){}
  }

  /* Welcome grid: bu ülkenin kartları (data-cccountry) + görünürlük */
  function xGrid(){
    try{
      var grid=document.querySelector(".wcat-grid"); if(!grid) return;
      var cur=(typeof CC!=="undefined"?CC:"DE");
      if(cur===CCODE){
        X_ORDER.forEach(function(ck){
          if(grid.querySelector('[data-cccountry="'+CCODE+'"][data-ccat="'+ck+'"]')) return;
          var c=XC[ck]; var el=document.createElement("div"); el.className="wcat";
          el.setAttribute("data-cccountry",CCODE); el.setAttribute("data-ccat",ck);
          el.onclick=function(){ try{ if(typeof showCat==="function") showCat(ck); }catch(e){} };
          var n=(typeof CATS!=="undefined"&&CATS[ck]&&CATS[ck].docs)?CATS[ck].docs.length:0;
          el.innerHTML='<div class="wcat-ic">'+c.ic+'</div><div class="wcat-t">'+c.n+'</div><div class="wcat-s">'+n+' '+DOCWORD+'</div>';
          grid.appendChild(el);
        });
      }
      grid.querySelectorAll(".wcat").forEach(function(el){
        var cc=el.getAttribute("data-cccountry")||(el.getAttribute("data-frcat")?"FR":(el.getAttribute("data-trcat")?"TR":"DE"));
        el.style.display=(cc===cur)?"":"none";
      });
    }catch(e){}
  }

  function installCountryHook(){
    try{
      if(typeof setCountry!=="function" || setCountry["__m"+CCODE]) return;
      var _sc=setCountry;
      var s=function(cc){ var r; try{ r=_sc.apply(this,arguments); }catch(e){} try{ xGrid(); }catch(e){} return r; };
      s["__m"+CCODE]=1; setCountry=s; if(typeof window!=="undefined") window.setCountry=s;
    }catch(e){}
  }

  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } installMultiCountryFilter(); installCountryHook(); try{ xGrid(); }catch(e){} if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src!==$start){ file_put_contents($file,$src); echo "Polonya modulu eklendi (CH_PL_MODULE) - ~36 belge, 6 kategori, Lehce.\n"; }
else echo "degisiklik yok\n";
echo "\nNOT: Belge dili/hukuku icin apply-lawsys-all.php de calistirilmali (lawSystems['PL'] + doc-lang).\n";
echo "SONRA: opcache-reset (pull-updates otomatik yapar). SIL: rm apply-polska.php\n";
echo "Test: Ayarlar -> Polska sec -> kategoriler Lehce -> 'Wezwanie do zaplaty' uret.\n";
