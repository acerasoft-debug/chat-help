<?php
/**
 * ChatHelp — apply-vst-x (CH_VST_X) — sozlesme cogaltma: bosanma cesitleri + ulke setleri
 *  [A] TR bosanma cesitleri (mevcut TR setine eklenir, TR'de gorunur):
 *      • Çocuklu Kapsamlı Boşanma Protokolü (ayrıntılı velayet/nafaka planı)
 *      • Mal Rejimi Tasfiye Sözleşmesi (TMK m.202 vd.)
 *  [B] DE bosanma cesitleri (Alman temel setine eklenir):
 *      • Scheidungsfolgenvereinbarung (§ 1408 BGB — notarielle Beurkundung)
 *      • Trennungsvereinbarung (§ 1567 BGB)
 *  [C] Ulke sozlesme setleri (hukuk ulkesine gore otomatik gecis, TR mantigi):
 *      ES: Convenio Regulador (art. 90 CC) + Arrendamiento de Vivienda (LAU)
 *      FR: Convention de divorce (art. 229-1 C.civ.) + Bail d'habitation (loi 89-462)
 *      IT: Separazione consensuale (L.162/2014) + Locazione abitativa (L.431/1998)
 *      GB: Separation Agreement (E&W) + Assured Shorthold Tenancy (HA 1988)
 *      Her sozlesmede "kendi maddeleriniz" adimi vardir.
 * KULLANIM: pull2.php?key=...&files=apply-vst-x.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-vst-x BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_VST_X')!==false) exit("Zaten ekli (CH_VST_X).\n");
if (strpos($src,'CH_VST_TR')===false) exit("HATA: once TR seti (vst-tr3) gerekli.\n");
if (strpos($src,'ncxBuild')===false) exit("HATA: vst-more (ncxBuild) yok.\n");

$fail=[];

/* ── [A] TR bosanma cesitleri: vst-tr blogu icine (TRK/NCX literal genisletme) ── */
$aTR = <<<'JS'
TRK["vtr_tr_bosanma_cocuklu"]={ic:"👨‍👩‍👧",n:"Çocuklu Kapsamlı Boşanma Protokolü",sn:"TMK m.166/3 — ayrıntılı velayet, kişisel ilişki ve nafaka planı",price:"4,99 €",pgs:6,steps:[
      {t:"Taraflar",f:[["es1_ad","Eş 1 — Ad Soyad"],["es1_tc","Eş 1 — T.C. Kimlik No"],["es2_ad","Eş 2 — Ad Soyad"],["es2_tc","Eş 2 — T.C. Kimlik No"],["evlilik_tarihi","Evlilik tarihi"]]},
      {t:"Çocuklar ve Velayet",f:[["cocuklar","Çocuklar (ad, doğum tarihi)","","ta"],["velayet_kim","Velayet kimde olacak?"],["okul_donemi","Okul dönemi kişisel ilişki planı","Her ayın 1. ve 3. hafta sonu cumartesi 10:00 – pazar 18:00","ta"],["tatil_plani","Yaz/sömestr tatili planı","Yaz tatilinde 1–31 Temmuz; sömestr tatilinin ilk haftası","ta"],["bayram_plani","Dini bayram planı","Bayramların 2. günü 09:00–18:00"]]},
      {t:"Nafaka ve Giderler",f:[["istirak_nafakasi","Aylık iştirak nafakası (çocuk başına, TL)"],["artis_endeksi","Yıllık artış","TÜFE on iki aylık ortalama oranında"],["egitim_gider","Eğitim/sağlık giderleri paylaşımı","Okul ve sağlık giderleri taraflarca yarı yarıya karşılanır."],["yoksulluk_nafakasi","Yoksulluk nafakası","Taraflar birbirlerinden yoksulluk nafakası talep etmemektedir."]]},
      {t:"Mali Sonuçlar",f:[["mal_paylasimi","Mal paylaşımı özeti","Taraflar mal paylaşımı konusunda anlaşmış olup karşılıklı talepleri yoktur.","ta"],["tazminat","Maddi/manevi tazminat","Taraflar karşılıklı olarak tazminat talep etmemektedir."]]},
      {t:"İmza",f:[["ort","Yer (şehir)"],["datum","Tarih"]]},
      {t:"Kendi Maddeleriniz (opsiyonel)",f:[["uc_t1","Ek Madde 1 — Başlık",""],["uc_b1","Ek Madde 1 — Metin","","ta"],["uc_t2","Ek Madde 2 — Başlık",""],["uc_b2","Ek Madde 2 — Metin","","ta"]]}]};
    TRK["vtr_tr_malrejimi"]={ic:"🏛️",n:"Mal Rejimi Tasfiye Sözleşmesi",sn:"TMK m.202 vd. — edinilmiş mallara katılma rejiminin tasfiyesi",price:"4,99 €",pgs:5,steps:[
      {t:"Taraflar",f:[["es1_ad","Eş 1 — Ad Soyad"],["es1_tc","Eş 1 — T.C. Kimlik No"],["es2_ad","Eş 2 — Ad Soyad"],["es2_tc","Eş 2 — T.C. Kimlik No"],["evlilik_tarihi","Evlilik tarihi"],["rejim_sonu","Rejimin sona erdiği tarih (boşanma davası tarihi)"]]},
      {t:"Mallar",f:[["tasinmazlar","Taşınmazlar ve devri (ada/parsel, kime kalacağı)","","ta"],["tasitlar","Taşıtlar ve devri","","ta"],["hesaplar","Banka hesapları/birikimler paylaşımı","","ta"],["kisisel_mallar","Kişisel mallar","Her eşin kişisel malları kendisinde kalır (TMK m.220)."]]},
      {t:"Katılma Alacağı",f:[["katilma_odeme","Katılma alacağı ödemesi (varsa tutar ve vade)","Taraflar karşılıklı katılma alacağı taleplerinden feragat etmiştir."],["denklestirme","Denkleştirme (TMK m.230, varsa)","yoktur"]]},
      {t:"İmza",f:[["ort","Yer (şehir)"],["datum","Tarih"]]},
      {t:"Kendi Maddeleriniz (opsiyonel)",f:[["uc_t1","Ek Madde 1 — Başlık",""],["uc_b1","Ek Madde 1 — Metin","","ta"],["uc_t2","Ek Madde 2 — Başlık",""],["uc_b2","Ek Madde 2 — Metin","","ta"]]}]};

JS;
$anchA = '    function ekMadde(d){ var s="";';
$c=substr_count($src,$anchA);
if($c!==1){ echo "  ✗ [A] TRK anchor ($c)\n"; $fail[]='A'; }
else { $src=str_replace($anchA,$aTR.$anchA,$src); echo "  ✓ [A1] TR: +2 bosanma cesidi (form)\n"; }

$aNCX = <<<'JS'
NCX["vtr_tr_bosanma_cocuklu"]={n:"ÇOCUKLU KAPSAMLI BOŞANMA PROTOKOLÜ",sn:"4721 sayılı TMK m.166/3 uyarınca",A:"Eş 1",B:"Eş 2",cl:[
        {no:"Madde 1",t:"Taraflar ve Boşanma İradesi",b:function(d){return V(d.es1_ad)+" (T.C. "+V(d.es1_tc)+") ile "+V(d.es2_ad)+" (T.C. "+V(d.es2_tc)+"), "+V(d.evlilik_tarihi)+" tarihli evliliklerini TMK m.166/3 uyarınca anlaşmalı olarak sona erdirme iradelerini beyan ederler.";}},
        {no:"Madde 2",t:"Velayet",b:function(d){return "Ortak çocuklar: "+V(d.cocuklar)+". Velayet "+V(d.velayet_kim)+" tarafına verilecektir.";}},
        {no:"Madde 3",t:"Kişisel İlişki Planı",b:function(d){return "Okul döneminde: "+V(d.okul_donemi)+". Tatillerde: "+V(d.tatil_plani)+". Bayramlarda: "+V(d.bayram_plani)+". Plan çocuğun üstün yararı gözetilerek uygulanır.";}},
        {no:"Madde 4",t:"İştirak Nafakası ve Giderler",b:function(d){return "Velayeti almayan taraf çocuk başına aylık "+V(d.istirak_nafakasi)+" TL iştirak nafakası öder; nafaka her yıl "+V(d.artis_endeksi,"TÜFE oranında")+" artırılır. "+V(d.egitim_gider)+" Yoksulluk nafakası: "+V(d.yoksulluk_nafakasi)+"";}},
        {no:"Madde 5",t:"Mali Sonuçlar",b:function(d){return V(d.mal_paylasimi)+" "+V(d.tazminat)+"";}},
        {no:"Madde 6",t:"Yargılama",b:function(d){return "İşbu protokol anlaşmalı boşanma davasında hükme esas alınmak üzere mahkemeye sunulur; taraflar duruşmada bizzat dinleneceklerini bilirler.";}},
        {no:"Madde 7",t:"Taraflarca Eklenen Özel Hükümler",b:ekMadde}]};
      NCX["vtr_tr_malrejimi"]={n:"MAL REJİMİ TASFİYE SÖZLEŞMESİ",sn:"4721 sayılı TMK m.202 vd.",A:"Eş 1",B:"Eş 2",cl:[
        {no:"Madde 1",t:"Taraflar ve Rejim",b:function(d){return V(d.es1_ad)+" (T.C. "+V(d.es1_tc)+") ile "+V(d.es2_ad)+" (T.C. "+V(d.es2_tc)+") arasında "+V(d.evlilik_tarihi)+" tarihinde başlayan edinilmiş mallara katılma rejimi, "+V(d.rejim_sonu)+" itibarıyla sona ermiş olup işbu sözleşme rejimin tasfiyesini düzenler.";}},
        {no:"Madde 2",t:"Taşınmaz ve Taşıtlar",b:function(d){return "Taşınmazlar: "+V(d.tasinmazlar,"yoktur")+" Taşıtlar: "+V(d.tasitlar,"yoktur")+"";}},
        {no:"Madde 3",t:"Hesaplar ve Kişisel Mallar",b:function(d){return "Hesap ve birikimler: "+V(d.hesaplar,"taraflarca paylaşılmıştır")+". "+V(d.kisisel_mallar)+"";}},
        {no:"Madde 4",t:"Katılma Alacağı ve Denkleştirme",b:function(d){return "Katılma alacağı: "+V(d.katilma_odeme)+" Denkleştirme: "+V(d.denklestirme,"yoktur")+". Taraflar bu sözleşme dışında birbirlerinden mal rejiminden kaynaklanan başkaca hak ve alacak talep etmeyeceklerini kabul ederler.";}},
        {no:"Madde 5",t:"Taraflarca Eklenen Özel Hükümler",b:ekMadde}]};

JS;
$anchB = '      NCX["vtr_tr_kira"]={';
$c=substr_count($src,$anchB);
if($c!==1){ echo "  ✗ [A2] NCX anchor ($c)\n"; $fail[]='A2'; }
else { $src=str_replace($anchB,$aNCX.$anchB,$src); echo "  ✓ [A2] TR: +2 bosanma cesidi (metin)\n"; }

/* ── [B]+[C] DE cesitleri + ulke setleri: vst closure'ina prepend ── */
$js = <<<'CHJS'

  /* ══ CH_VST_X — bosanma cesitleri (DE) + ulke sozlesme setleri (ES/FR/IT/GB) ══ */
  setTimeout(function(){ try{
    function ekX(d,none){ var s=""; if(d.uc_t1&&d.uc_b1){ s+=d.uc_t1+": "+d.uc_b1; } if(d.uc_t2&&d.uc_b2){ s+=(s?" — ":"")+d.uc_t2+": "+d.uc_b2; } return s||none; }
    function ucStep(t,l1,l2){ return {t:t,f:[["uc_t1",l1,""],["uc_b1",l2,"","ta"],["uc_t2",l1+" 2",""],["uc_b2",l2+" 2","","ta"]]}; }

    /* [B] DE: Scheidungsfolgenvereinbarung + Trennungsvereinbarung (temel sete) */
    if(typeof CT!=="undefined"){
      CT["nc_sfv"]={ic:"⚖️",n:"Scheidungsfolgenvereinbarung",sn:"Regelt Unterhalt, Vermögen, Hausrat und Wohnung — notarielle Beurkundung erforderlich (§ 1408 BGB)",price:"4,99 €",pgs:5,steps:[
        {t:"Ehegatten",f:[["p1_name","Ehegatte 1 — Name"],["p1_addr","Ehegatte 1 — Anschrift"],["p2_name","Ehegatte 2 — Name"],["p2_addr","Ehegatte 2 — Anschrift"],["ehe_datum","Tag der Eheschließung"],["trennung_datum","Getrenntlebend seit"]]},
        {t:"Unterhalt",f:[["tr_unterhalt","Trennungsunterhalt","Auf Trennungsunterhalt wird — soweit gesetzlich zulässig — wechselseitig verzichtet."],["nach_unterhalt","Nachehelicher Unterhalt","Die Ehegatten verzichten wechselseitig auf nachehelichen Unterhalt, auch für den Fall der Not."],["kindesunterhalt","Kindesunterhalt (falls Kinder)","nach Düsseldorfer Tabelle"]]},
        {t:"Vermögen und Wohnung",f:[["zugewinn","Zugewinnausgleich","Der Zugewinnausgleich ist einvernehmlich durchgeführt; wechselseitige Ansprüche bestehen nicht mehr."],["hausrat","Hausratsverteilung","Der Hausrat ist einvernehmlich verteilt."],["wohnung","Ehewohnung","Die Ehewohnung verbleibt bei"],["versorgung","Versorgungsausgleich","Der Versorgungsausgleich richtet sich nach den gesetzlichen Vorschriften."]]},
        {t:"Unterschrift",f:[["ort","Ort"],["datum","Datum"]]},
        ucStep("Eigene Klauseln (optional)","Zusatzklausel — Titel","Zusatzklausel — Text")]};
      CT["nc_trennung"]={ic:"🏠",n:"Trennungsvereinbarung",sn:"Regelt Getrenntleben, Wohnung, Hausrat und Unterhalt (§ 1567 BGB)",price:"3,99 €",pgs:4,steps:[
        {t:"Ehegatten",f:[["p1_name","Ehegatte 1 — Name"],["p2_name","Ehegatte 2 — Name"],["trennung_datum","Getrenntlebend seit"]]},
        {t:"Regelungen",f:[["wohnung","Nutzung der Ehewohnung","Die Ehewohnung nutzt künftig allein"],["hausrat","Hausratsaufteilung","Der Hausrat wird einvernehmlich aufgeteilt; jeder behält die persönlichen Gegenstände."],["unterhalt","Trennungsunterhalt","Trennungsunterhalt wird nicht geltend gemacht, solange beide erwerbstätig sind."],["kinder","Umgang mit gemeinsamen Kindern (falls vorhanden)","","ta"],["konten","Konten und laufende Verträge","Gemeinsame Konten werden aufgelöst; laufende Verträge übernimmt der jeweilige Nutzer."]]},
        {t:"Unterschrift",f:[["ort","Ort"],["datum","Datum"]]},
        ucStep("Eigene Klauseln (optional)","Zusatzklausel — Titel","Zusatzklausel — Text")]};
    }
    if(typeof NCX!=="undefined"&&NCX){
      var ekDE=function(d){ return ekX(d,"Weitere individuelle Vereinbarungen bestehen nicht."); };
      NCX["nc_sfv"]={n:"SCHEIDUNGSFOLGENVEREINBARUNG",sn:"§ 1408 BGB — bedarf der notariellen Beurkundung",A:"Ehegatte 1",B:"Ehegatte 2",cl:[
        {no:"§ 1",t:"Parteien und Trennung",b:function(d){return "Die Ehegatten "+V(d.p1_name)+" ("+V(d.p1_addr)+") und "+V(d.p2_name)+" ("+V(d.p2_addr)+"), verheiratet seit "+V(d.ehe_datum)+", leben seit "+V(d.trennung_datum)+" getrennt und beabsichtigen die einvernehmliche Scheidung.";}},
        {no:"§ 2",t:"Unterhalt",b:function(d){return "Trennungsunterhalt: "+V(d.tr_unterhalt)+" Nachehelicher Unterhalt: "+V(d.nach_unterhalt)+" Kindesunterhalt: "+V(d.kindesunterhalt,"entfällt — keine gemeinsamen Kinder")+".";}},
        {no:"§ 3",t:"Vermögen, Hausrat, Wohnung",b:function(d){return "Zugewinn: "+V(d.zugewinn)+" Hausrat: "+V(d.hausrat)+" Ehewohnung: "+V(d.wohnung)+". Versorgungsausgleich: "+V(d.versorgung)+"";}},
        {no:"§ 4",t:"Form und Wirksamkeit",b:function(){return "Den Parteien ist bekannt, dass Vereinbarungen über Zugewinnausgleich, Versorgungsausgleich und nachehelichen Unterhalt gemäß §§ 1408, 1410 BGB, § 7 VersAusglG der notariellen Beurkundung bedürfen. Diese Vereinbarung dient als abgestimmte Grundlage für die Beurkundung.";}},
        {no:"§ 5",t:"Individuelle Vereinbarungen",b:ekDE}]};
      NCX["nc_trennung"]={n:"TRENNUNGSVEREINBARUNG",sn:"§ 1567 BGB — Regelung des Getrenntlebens",A:"Ehegatte 1",B:"Ehegatte 2",cl:[
        {no:"§ 1",t:"Getrenntleben",b:function(d){return "Die Ehegatten "+V(d.p1_name)+" und "+V(d.p2_name)+" leben seit dem "+V(d.trennung_datum)+" im Sinne des § 1567 BGB getrennt.";}},
        {no:"§ 2",t:"Wohnung und Hausrat",b:function(d){return V(d.wohnung)+". "+V(d.hausrat)+"";}},
        {no:"§ 3",t:"Unterhalt und Kinder",b:function(d){return V(d.unterhalt)+" Umgang: "+V(d.kinder,"entfällt — keine gemeinsamen Kinder")+".";}},
        {no:"§ 4",t:"Konten und Verträge",b:function(d){return V(d.konten)+"";}},
        {no:"§ 5",t:"Individuelle Vereinbarungen",b:ekDE}]};

      /* [C] ulke NCX metinleri */
      var ekES=function(d){ return ekX(d,"No existen otras estipulaciones particulares."); };
      NCX["vtr_es_divorcio"]={n:"CONVENIO REGULADOR DE DIVORCIO DE MUTUO ACUERDO",sn:"arts. 81, 86 y 90 del Código Civil",A:"Cónyuge 1",B:"Cónyuge 2",cl:[
        {no:"Cláusula 1",t:"Partes y objeto",b:function(d){return "D./Dña. "+V(d.c1)+" (DNI "+V(d.c1_dni)+") y D./Dña. "+V(d.c2)+" (DNI "+V(d.c2_dni)+"), casados el "+V(d.fecha_matrimonio)+", acuerdan divorciarse de mutuo acuerdo y suscriben el presente convenio regulador conforme al art. 90 CC.";}},
        {no:"Cláusula 2",t:"Hijos, guarda y visitas",b:function(d){return "Hijos: "+V(d.hijos,"no hay hijos menores")+". Guarda y custodia: "+V(d.custodia,"no procede")+". Régimen de visitas: "+V(d.visitas,"no procede")+".";}},
        {no:"Cláusula 3",t:"Pensiones",b:function(d){return "Pensión de alimentos: "+V(d.alimentos,"no procede")+". Pensión compensatoria (art. 97 CC): "+V(d.compensatoria,"ambos cónyuges renuncian expresamente")+".";}},
        {no:"Cláusula 4",t:"Vivienda y liquidación",b:function(d){return "Uso de la vivienda familiar (art. 96 CC): "+V(d.vivienda)+". Liquidación de la sociedad de gananciales: "+V(d.gananciales,"practicada de común acuerdo, sin reclamaciones pendientes")+".";}},
        {no:"Cláusula 5",t:"Aprobación judicial",b:function(){return "El presente convenio se someterá a aprobación judicial o, en su caso, se otorgará ante notario conforme al art. 82 CC cuando no existan hijos menores.";}},
        {no:"Cláusula 6",t:"Estipulaciones particulares",b:ekES}]};
      NCX["vtr_es_alquiler"]={n:"CONTRATO DE ARRENDAMIENTO DE VIVIENDA",sn:"Ley 29/1994, de Arrendamientos Urbanos (LAU)",A:"Arrendador",B:"Arrendatario",cl:[
        {no:"Cláusula 1",t:"Partes y vivienda",b:function(d){return "Arrendador: "+V(d.arrendador)+". Arrendatario: "+V(d.arrendatario)+". Vivienda: "+V(d.vivienda_dir)+", destinada a vivienda habitual.";}},
        {no:"Cláusula 2",t:"Duración",b:function(d){return "El contrato comienza el "+V(d.inicio)+" con duración de "+V(d.duracion,"un año, prorrogable hasta cinco años conforme al art. 9 LAU")+".";}},
        {no:"Cláusula 3",t:"Renta y fianza",b:function(d){return "Renta mensual: "+V(d.renta)+" €, pagadera en los primeros siete días de cada mes. Actualización: "+V(d.actualizacion,"según el índice legal aplicable")+". Fianza: "+V(d.fianza,"una mensualidad (art. 36 LAU)")+".";}},
        {no:"Cláusula 4",t:"Gastos y conservación",b:function(d){return "Suministros a cargo del arrendatario. Gastos de comunidad: "+V(d.comunidad,"a cargo del arrendador")+". Las reparaciones necesarias por el uso ordinario corresponden conforme a los arts. 21 y 22 LAU.";}},
        {no:"Cláusula 5",t:"Estipulaciones particulares",b:ekES}]};

      var ekFR=function(d){ return ekX(d,"Aucune autre stipulation particulière."); };
      NCX["vtr_fr_divorce"]={n:"CONVENTION DE DIVORCE PAR CONSENTEMENT MUTUEL",sn:"art. 229-1 du Code civil — acte sous signature privée contresigné par avocats, déposé chez notaire",A:"Époux 1",B:"Époux 2",cl:[
        {no:"Article 1",t:"Parties",b:function(d){return V(d.e1)+" et "+V(d.e2)+", mariés le "+V(d.date_mariage)+" à "+V(d.lieu_mariage)+", conviennent de divorcer par consentement mutuel conformément à l'article 229-1 du Code civil, chacun assisté de son avocat ("+V(d.avocats,"à désigner")+").";}},
        {no:"Article 2",t:"Enfants",b:function(d){return "Enfants: "+V(d.enfants,"aucun enfant mineur")+". Résidence: "+V(d.residence_enfants,"sans objet")+". Droit de visite et d'hébergement: "+V(d.visites,"sans objet")+". Pension alimentaire: "+V(d.pension,"sans objet")+".";}},
        {no:"Article 3",t:"Prestation compensatoire",b:function(d){return "Prestation compensatoire (art. 270 C.civ.): "+V(d.prestation,"les époux y renoncent réciproquement")+".";}},
        {no:"Article 4",t:"Liquidation du régime matrimonial",b:function(d){return "Régime: "+V(d.regime,"communauté légale")+". Liquidation: "+V(d.liquidation,"opérée amiablement; aucune créance entre époux ne subsiste")+". Logement familial: "+V(d.logement)+".";}},
        {no:"Article 5",t:"Délai de réflexion et dépôt",b:function(){return "Le projet de convention ne peut être signé avant l'expiration d'un délai de réflexion de quinze jours à compter de sa réception (art. 229-4 C.civ.). La convention sera déposée au rang des minutes d'un notaire, ce qui lui confère date certaine et force exécutoire.";}},
        {no:"Article 6",t:"Stipulations particulières",b:ekFR}]};
      NCX["vtr_fr_bail"]={n:"CONTRAT DE BAIL D'HABITATION",sn:"loi n° 89-462 du 6 juillet 1989 — résidence principale",A:"Bailleur",B:"Locataire",cl:[
        {no:"Article 1",t:"Parties et logement",b:function(d){return "Bailleur: "+V(d.bailleur)+". Locataire: "+V(d.locataire)+". Logement: "+V(d.logement_adr)+", loué à usage de résidence principale.";}},
        {no:"Article 2",t:"Durée",b:function(d){return "Le bail prend effet le "+V(d.debut)+" pour une durée de "+V(d.duree,"trois ans (bailleur personne physique), renouvelable")+".";}},
        {no:"Article 3",t:"Loyer et dépôt de garantie",b:function(d){return "Loyer mensuel: "+V(d.loyer)+" € hors charges; provisions sur charges: "+V(d.charges,"selon régularisation annuelle")+". Dépôt de garantie: "+V(d.depot,"un mois de loyer hors charges (art. 22)")+".";}},
        {no:"Article 4",t:"Obligations",b:function(d){return "Le locataire use paisiblement du logement, l'assure et paie le loyer; le bailleur délivre un logement décent et effectue les réparations autres que locatives. État des lieux: "+V(d.etat_lieux,"établi contradictoirement à la remise des clés")+".";}},
        {no:"Article 5",t:"Stipulations particulières",b:ekFR}]};

      var ekIT=function(d){ return ekX(d,"Non sussistono ulteriori pattuizioni particolari."); };
      NCX["vtr_it_separazione"]={n:"ACCORDO DI SEPARAZIONE CONSENSUALE",sn:"art. 158 c.c. — anche tramite negoziazione assistita (L. 162/2014)",A:"Coniuge 1",B:"Coniuge 2",cl:[
        {no:"Art. 1",t:"Parti e oggetto",b:function(d){return V(d.c1)+" e "+V(d.c2)+", coniugati il "+V(d.data_matrimonio)+", convengono di separarsi consensualmente alle condizioni che seguono.";}},
        {no:"Art. 2",t:"Figli e affidamento",b:function(d){return "Figli: "+V(d.figli,"nessun figlio minore")+". Affidamento: "+V(d.affidamento,"condiviso ai sensi dell'art. 337-ter c.c.")+". Collocamento e frequentazione: "+V(d.frequentazione,"come da accordi tra le parti")+".";}},
        {no:"Art. 3",t:"Mantenimento",b:function(d){return "Assegno di mantenimento per i figli: "+V(d.mantenimento_figli,"non dovuto")+". Mantenimento del coniuge: "+V(d.mantenimento_coniuge,"le parti vi rinunciano reciprocamente")+".";}},
        {no:"Art. 4",t:"Casa coniugale e beni",b:function(d){return "Casa coniugale: "+V(d.casa)+". Divisione dei beni: "+V(d.beni,"già operata di comune accordo; nulla a pretendere reciprocamente")+".";}},
        {no:"Art. 5",t:"Omologazione",b:function(){return "L'accordo sarà sottoposto ad omologazione del Tribunale ovvero concluso mediante convenzione di negoziazione assistita da avvocati ai sensi della L. 162/2014.";}},
        {no:"Art. 6",t:"Pattuizioni particolari",b:ekIT}]};
      NCX["vtr_it_locazione"]={n:"CONTRATTO DI LOCAZIONE ABITATIVA",sn:"L. 431/1998 — canone libero 4+4",A:"Locatore",B:"Conduttore",cl:[
        {no:"Art. 1",t:"Parti e immobile",b:function(d){return "Locatore: "+V(d.locatore)+". Conduttore: "+V(d.conduttore)+". Immobile: "+V(d.immobile)+", ad uso abitativo primario.";}},
        {no:"Art. 2",t:"Durata",b:function(d){return "Durata di anni quattro dal "+V(d.inizio)+", rinnovabile per ulteriori quattro anni salvo disdetta nei casi di legge (art. 2, L. 431/1998).";}},
        {no:"Art. 3",t:"Canone e deposito",b:function(d){return "Canone mensile: "+V(d.canone)+" €. Aggiornamento: "+V(d.aggiornamento,"ISTAT nella misura consentita")+". Deposito cauzionale: "+V(d.deposito,"non superiore a tre mensilità (art. 11, L. 392/1978)")+".";}},
        {no:"Art. 4",t:"Oneri e obblighi",b:function(d){return "Oneri accessori a carico del conduttore secondo legge. Manutenzione ordinaria al conduttore, straordinaria al locatore. Registrazione del contratto a cura del locatore con spese ripartite al 50%.";}},
        {no:"Art. 5",t:"Pattuizioni particolari",b:ekIT}]};

      var ekGB=function(d){ return ekX(d,"No further special terms are agreed."); };
      NCX["vtr_gb_separation"]={n:"SEPARATION AGREEMENT",sn:"England & Wales — executed as a deed; a consent order is required for a binding financial settlement",A:"Party 1",B:"Party 2",cl:[
        {no:"Clause 1",t:"Parties and separation",b:function(d){return V(d.p1)+" and "+V(d.p2)+", married on "+V(d.marriage_date)+", have lived separately since "+V(d.separation_date)+" and record the terms of their separation in this deed.";}},
        {no:"Clause 2",t:"Children",b:function(d){return "Children: "+V(d.children,"none under 18")+". Living arrangements: "+V(d.child_arrangements,"not applicable")+". Child maintenance: "+V(d.child_maintenance,"in accordance with CMS assessment")+".";}},
        {no:"Clause 3",t:"Financial arrangements",b:function(d){return "Family home: "+V(d.home)+". Division of assets and debts: "+V(d.assets)+". Spousal maintenance: "+V(d.spousal,"each party forgoes any claim, so far as the law allows")+".";}},
        {no:"Clause 4",t:"Legal effect",b:function(){return "The parties acknowledge that only a financial consent order approved by the court finally determines financial claims between spouses; each party has had the opportunity to take independent legal advice and has given full and frank financial disclosure.";}},
        {no:"Clause 5",t:"Special terms",b:ekGB}]};
      NCX["vtr_gb_ast"]={n:"ASSURED SHORTHOLD TENANCY AGREEMENT",sn:"Housing Act 1988 — England",A:"Landlord",B:"Tenant",cl:[
        {no:"Clause 1",t:"Parties and property",b:function(d){return "Landlord: "+V(d.landlord)+". Tenant: "+V(d.tenant)+". Property: "+V(d.property)+", let as a single private residence.";}},
        {no:"Clause 2",t:"Term and rent",b:function(d){return "Fixed term of "+V(d.term,"12 months")+" from "+V(d.start)+". Rent: "+V(d.rent)+" per calendar month, payable in advance.";}},
        {no:"Clause 3",t:"Deposit",b:function(d){return "Deposit: "+V(d.deposit,"not exceeding five weeks' rent (Tenant Fees Act 2019)")+", to be protected in a government-approved tenancy deposit scheme within 30 days, with the prescribed information served on the tenant.";}},
        {no:"Clause 4",t:"Obligations",b:function(d){return "The tenant shall pay the rent, use the property in a tenant-like manner and allow access on 24 hours' written notice. The landlord shall keep the structure and installations in repair (s.11 Landlord and Tenant Act 1985) and comply with gas, electrical and smoke alarm safety duties.";}},
        {no:"Clause 5",t:"Special terms",b:ekGB}]};
    }

    /* [C] ulke formlari + CC'ye gore gecis */
    var XSETS={
      ES:{
        "vtr_es_divorcio":{ic:"⚖️",n:"Convenio Regulador de Divorcio",sn:"Divorcio de mutuo acuerdo — arts. 81, 86 y 90 del Código Civil",price:"4,99 €",pgs:5,steps:[
          {t:"Cónyuges",f:[["c1","Cónyuge 1 — nombre completo"],["c1_dni","Cónyuge 1 — DNI/NIE"],["c2","Cónyuge 2 — nombre completo"],["c2_dni","Cónyuge 2 — DNI/NIE"],["fecha_matrimonio","Fecha del matrimonio"]]},
          {t:"Hijos",f:[["hijos","Hijos (nombre y fecha de nacimiento; si no hay: 'no hay')","no hay hijos menores","ta"],["custodia","Guarda y custodia","no procede"],["visitas","Régimen de visitas","no procede","ta"],["alimentos","Pensión de alimentos (€/mes)","no procede"]]},
          {t:"Economía",f:[["compensatoria","Pensión compensatoria","ambos cónyuges renuncian expresamente"],["vivienda","Uso de la vivienda familiar"],["gananciales","Liquidación de gananciales","practicada de común acuerdo, sin reclamaciones","ta"]]},
          {t:"Firma",f:[["ort","Lugar"],["datum","Fecha"]]},
          ucStep("Estipulaciones propias (opcional)","Cláusula — título","Cláusula — texto")]},
        "vtr_es_alquiler":{ic:"🏠",n:"Contrato de Arrendamiento de Vivienda",sn:"Ley 29/1994 (LAU) — vivienda habitual",price:"4,99 €",pgs:4,steps:[
          {t:"Partes",f:[["arrendador","Arrendador (nombre, DNI, domicilio)","","ta"],["arrendatario","Arrendatario (nombre, DNI)","","ta"],["vivienda_dir","Dirección de la vivienda"]]},
          {t:"Condiciones",f:[["inicio","Fecha de inicio"],["duracion","Duración","un año, prorrogable hasta cinco (art. 9 LAU)"],["renta","Renta mensual (€)"],["actualizacion","Actualización anual","según el índice legal aplicable"],["fianza","Fianza","una mensualidad (art. 36 LAU)"],["comunidad","Gastos de comunidad","a cargo del arrendador"]]},
          {t:"Firma",f:[["ort","Lugar"],["datum","Fecha"]]},
          ucStep("Estipulaciones propias (opcional)","Cláusula — título","Cláusula — texto")]}
      },
      FR:{
        "vtr_fr_divorce":{ic:"⚖️",n:"Convention de divorce par consentement mutuel",sn:"art. 229-1 C.civ. — contresignée par avocats, déposée chez notaire",price:"4,99 €",pgs:5,steps:[
          {t:"Époux",f:[["e1","Époux/Épouse 1 — nom complet"],["e2","Époux/Épouse 2 — nom complet"],["date_mariage","Date du mariage"],["lieu_mariage","Lieu du mariage"],["avocats","Avocats des parties (noms)","à désigner"]]},
          {t:"Enfants",f:[["enfants","Enfants (nom, date de naissance; sinon 'aucun')","aucun enfant mineur","ta"],["residence_enfants","Résidence des enfants","sans objet"],["visites","Droit de visite et d'hébergement","sans objet","ta"],["pension","Pension alimentaire (€/mois)","sans objet"]]},
          {t:"Économie",f:[["prestation","Prestation compensatoire","les époux y renoncent réciproquement"],["regime","Régime matrimonial","communauté légale"],["liquidation","Liquidation du régime","opérée amiablement, aucune créance ne subsiste","ta"],["logement","Logement familial"]]},
          {t:"Signature",f:[["ort","Fait à"],["datum","Le"]]},
          ucStep("Stipulations propres (optionnel)","Clause — titre","Clause — texte")]},
        "vtr_fr_bail":{ic:"🏠",n:"Contrat de bail d'habitation",sn:"loi n° 89-462 — résidence principale, 3 ans",price:"4,99 €",pgs:4,steps:[
          {t:"Parties",f:[["bailleur","Bailleur (nom, domicile)","","ta"],["locataire","Locataire (nom)","","ta"],["logement_adr","Adresse du logement"]]},
          {t:"Conditions",f:[["debut","Date de prise d'effet"],["duree","Durée","trois ans, renouvelable"],["loyer","Loyer mensuel hors charges (€)"],["charges","Provisions sur charges (€)","selon régularisation annuelle"],["depot","Dépôt de garantie","un mois de loyer hors charges"],["etat_lieux","État des lieux","établi contradictoirement à la remise des clés"]]},
          {t:"Signature",f:[["ort","Fait à"],["datum","Le"]]},
          ucStep("Stipulations propres (optionnel)","Clause — titre","Clause — texte")]}
      },
      IT:{
        "vtr_it_separazione":{ic:"⚖️",n:"Accordo di separazione consensuale",sn:"art. 158 c.c. / negoziazione assistita (L. 162/2014)",price:"4,99 €",pgs:5,steps:[
          {t:"Coniugi",f:[["c1","Coniuge 1 — nome completo"],["c2","Coniuge 2 — nome completo"],["data_matrimonio","Data del matrimonio"]]},
          {t:"Figli",f:[["figli","Figli (nome, data di nascita; altrimenti 'nessuno')","nessun figlio minore","ta"],["affidamento","Affidamento","condiviso (art. 337-ter c.c.)"],["frequentazione","Collocamento e frequentazione","come da accordi tra le parti","ta"],["mantenimento_figli","Assegno per i figli (€/mese)","non dovuto"]]},
          {t:"Economia",f:[["mantenimento_coniuge","Mantenimento del coniuge","le parti vi rinunciano reciprocamente"],["casa","Casa coniugale"],["beni","Divisione dei beni","già operata di comune accordo","ta"]]},
          {t:"Firma",f:[["ort","Luogo"],["datum","Data"]]},
          ucStep("Pattuizioni proprie (facoltativo)","Clausola — titolo","Clausola — testo")]},
        "vtr_it_locazione":{ic:"🏠",n:"Contratto di locazione abitativa",sn:"L. 431/1998 — canone libero 4+4",price:"4,99 €",pgs:4,steps:[
          {t:"Parti",f:[["locatore","Locatore (nome, CF, domicilio)","","ta"],["conduttore","Conduttore (nome, CF)","","ta"],["immobile","Indirizzo dell'immobile"]]},
          {t:"Condizioni",f:[["inizio","Data di inizio"],["canone","Canone mensile (€)"],["aggiornamento","Aggiornamento","ISTAT nella misura consentita"],["deposito","Deposito cauzionale","non superiore a tre mensilità"]]},
          {t:"Firma",f:[["ort","Luogo"],["datum","Data"]]},
          ucStep("Pattuizioni proprie (facoltativo)","Clausola — titolo","Clausola — testo")]}
      },
      GB:{
        "vtr_gb_separation":{ic:"⚖️",n:"Separation Agreement",sn:"England & Wales — deed; consent order needed for binding financial settlement",price:"4,99 €",pgs:5,steps:[
          {t:"Parties",f:[["p1","Party 1 — full name"],["p2","Party 2 — full name"],["marriage_date","Date of marriage"],["separation_date","Separated since"]]},
          {t:"Children",f:[["children","Children (name, date of birth; otherwise 'none')","none under 18","ta"],["child_arrangements","Living arrangements","not applicable","ta"],["child_maintenance","Child maintenance","in accordance with CMS assessment"]]},
          {t:"Finances",f:[["home","Family home"],["assets","Division of assets and debts","","ta"],["spousal","Spousal maintenance","each party forgoes any claim, so far as the law allows"]]},
          {t:"Signature",f:[["ort","Signed at"],["datum","Date"]]},
          ucStep("Special terms (optional)","Term — title","Term — text")]},
        "vtr_gb_ast":{ic:"🏠",n:"Assured Shorthold Tenancy Agreement",sn:"Housing Act 1988 — England",price:"4,99 €",pgs:4,steps:[
          {t:"Parties",f:[["landlord","Landlord (name, address)","","ta"],["tenant","Tenant (name)","","ta"],["property","Property address"]]},
          {t:"Terms",f:[["start","Start date"],["term","Fixed term","12 months"],["rent","Rent per calendar month (£)"],["deposit","Deposit","five weeks' rent, protected in a TDP scheme"]]},
          {t:"Signature",f:[["ort","Signed at"],["datum","Date"]]},
          ucStep("Special terms (optional)","Term — title","Term — text")]}
      }
    };
    var xBak=null,xMode=null;
    function ccX(){ try{ return (typeof CC!=="undefined"&&CC)?String(CC):"DE"; }catch(e){ return "DE"; } }
    function syncX(){
      var cc=ccX(); var mode=XSETS[cc]?cc:"OFF";
      if(mode===xMode) return;
      if(xMode&&xMode!=="OFF"){ for(var o in XSETS[xMode]){ delete CT[o]; } if(xBak){ for(var b in xBak){ CT[b]=xBak[b]; } xBak=null; } }
      xMode=mode;
      if(mode!=="OFF"){
        if(!xBak){ xBak={}; for(var k in CT){ if(k.indexOf("vtr_tr_")!==0 && !XSETS[mode][k]){ xBak[k]=CT[k]; delete CT[k]; } } }
        for(var t in XSETS[mode]){ CT[t]=XSETS[mode][t]; }
      }
      try{ renderCat(); }catch(e){}
    }
    syncX(); setInterval(syncX, 700);
  }catch(e){} }, 0);
CHJS;

$anchor='function boot(){ addCss(); addNav(); try{window.__vstOpen';
$ac = substr_count($src,$anchor);
if ($ac!==1) { echo "  ✗ [B/C] boot anchor ($ac)\n"; $fail[]='B'; }
else { $src=str_replace($anchor,$js."\n  ".$anchor,$src); echo "  ✓ [B] DE: +2 bosanma cesidi\n  ✓ [C] ES/FR/IT/GB: 8 ulke sozlesmesi + otomatik gecis\n"; }

/* ulke gorunurluk CSS (TR'dekiyle ayni mantik) */
$css = "\n<style id=\"ch-vst-x-css\">\n/* CH_VST_X — ES/FR/IT/GB hukukunda Vertrag Studio gorunur */\n";
foreach (['ES','FR','IT','GB'] as $cc) {
    $css .= "body.ch-cc-nonde[data-chcc=\"$cc\"] #nav-vst{display:flex !important}\n"
          . "body.ch-cc-nonde[data-chcc=\"$cc\"] #ch-pill-vst{display:inline-flex !important}\n"
          . "body.ch-cc-nonde[data-chcc=\"$cc\"] .vst-card:has([data-tg=\"vst\"]){display:block !important}\n";
}
$css .= "</style>";
$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$src = substr($src,0,$pos).$css."\n".substr($src,$pos);
echo "  ✓ [C2] 4 ulkede modul gorunurlugu acildi\n";

if ($fail) { echo "\nHATA: eslesmeyen adimlar: ".implode(', ',$fail)." — index DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'vx').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
$bk=@file_put_contents($file.'.bak-vstx-'.date('Ymd-His'), @file_get_contents($file));
if ($bk===false) echo "  ⚠ yedek yazilamadi — devam\n";
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI: ".var_export($w,true)."\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_VST_X')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_VST_X diskte (".strlen($chk)." bayt)\n";
echo "\n✓ CH_VST_X uygulandi: TR+2, DE+2 bosanma cesidi; ES/FR/IT/GB'ye 2'ser sozlesme.\n";
