<?php
/**
 * ChatHelp — apply-contract-studio (CH_VST)
 *  "Verträge" bolumu — cok sayfali premium sozlesme sihirbazi (AI'siz, CV Studio mantigi):
 *   • Katalog: Mietvertrag (4,99€ · 4 sayfa) / Arbeitsvertrag (4,99€ · 3 sayfa) / Kfz-Kaufvertrag (3,99€ · 2 sayfa)
 *   • Sihirbaz: "Seite 1/4" gostergesi — sayfa kaydedilince sonrakine gecilir (localStorage kalici)
 *   • Premium A4 hukuki sablon: §§ tipografisi, altin cizgiler, sayfa altligi "Seite X von Y"
 *   • Canli onizleme + cok sayfali PDF baski penceresi
 *   • Kapı: plansiz -> 3,99/4,99€ (Stripe ID bekliyor); paketli aylik kota basic 3 / pro 10 / elite 50
 * KULLANIM: pull-updates.php?files=apply-contract-studio.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-contract-studio BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_VST') !== false) exit("Zaten ekli (CH_VST).\n");
file_put_contents($file . '.bak-vst-' . date('Ymd-His'), $src);

$bundle = <<<'VSTHTML'
<script id="ch-vertrag-studio">
/* CH_VST — Vertrag Studio: cok sayfali premium sozlesmeler (Miet/Arbeits/Kfz), sihirbaz + PDF */
try{(function(){
  function UIL(){ try{ return (window.chUIL?window.chUIL():(localStorage.getItem("ch_uilang")||"de")); }catch(e){ return "de"; } }
  function esc(s){ return String(s==null?"":s).replace(/[&<>"]/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];}); }
  function V(s,ph){ return s ? '<b class="vv">'+esc(s)+'</b>' : '<span class="vb">'+(ph||"____________")+'</span>'; }

  var T={
   de:{ttl:"Verträge",sub:"Premium-Vertragsvorlagen — Sie füllen aus, Seite für Seite. Rechtsgültig unterschreiben Sie auf Papier.",pg:"Seite",of:"von",next:"Speichern & weiter →",prev:"← Zurück",make:"📄 PDF erstellen (alle Seiten)",hint:"Im Druckdialog «Als PDF speichern» wählen.",free:"Diesen Monat frei",gT:"Premium-Vertrag",gP:"Einzelpreis {p} — oder mit Paket monatlich enthalten (Basic 3 · Pro 10 · Elite 50).",gPlans:"Pakete ansehen",gBuy:"{p} kaufen (in Kürze)",pages:"Seiten",note:"Vorlage nach deutschem Recht. Kein Ersatz für Rechtsberatung.",done:"✓ Gespeichert"},
   en:{ttl:"Contracts",sub:"Premium contract templates — fill in page by page. Sign on paper to make it binding.",pg:"Page",of:"of",next:"Save & continue →",prev:"← Back",make:"📄 Create PDF (all pages)",hint:"Choose “Save as PDF” in the print dialog.",free:"Free this month",gT:"Premium contract",gP:"Single price {p} — or included monthly with a plan (Basic 3 · Pro 10 · Elite 50).",gPlans:"See plans",gBuy:"Buy {p} (soon)",pages:"pages",note:"Template under German law. Not a substitute for legal advice.",done:"✓ Saved"},
   tr:{ttl:"Sözleşmeler",sub:"Premium sözleşme şablonları — sayfa sayfa doldur. Bağlayıcılık için kâğıt üzerinde imzala.",pg:"Sayfa",of:"/",next:"Kaydet & devam →",prev:"← Geri",make:"📄 PDF oluştur (tüm sayfalar)",hint:"Yazdırma penceresinde «PDF olarak kaydet» seç.",free:"Bu ay ücretsiz",gT:"Premium sözleşme",gP:"Tekil fiyat {p} — veya paketle aylık dahil (Basic 3 · Pro 10 · Elite 50).",gPlans:"Paketleri gör",gBuy:"{p} satın al (yakında)",pages:"sayfa",note:"Alman hukukuna göre şablon. Hukuki danışmanlık yerine geçmez.",done:"✓ Kaydedildi"},
   fr:{ttl:"Contrats",sub:"Modèles de contrats premium — remplissez page par page. Signez sur papier.",pg:"Page",of:"sur",next:"Enregistrer & continuer →",prev:"← Retour",make:"📄 Créer le PDF (toutes les pages)",hint:"Choisissez « Enregistrer en PDF ».",free:"Gratuit ce mois-ci",gT:"Contrat premium",gP:"Prix unitaire {p} — ou inclus avec un forfait (Basic 3 · Pro 10 · Elite 50).",gPlans:"Voir les forfaits",gBuy:"Acheter {p} (bientôt)",pages:"pages",note:"Modèle de droit allemand. Ne remplace pas un conseil juridique.",done:"✓ Enregistré"},
   es:{ttl:"Contratos",sub:"Plantillas premium — rellene página a página. Firme en papel.",pg:"Página",of:"de",next:"Guardar y continuar →",prev:"← Atrás",make:"📄 Crear PDF (todas las páginas)",hint:"Elija «Guardar como PDF».",free:"Gratis este mes",gT:"Contrato premium",gP:"Precio único {p} — o incluido con un plan (Basic 3 · Pro 10 · Elite 50).",gPlans:"Ver planes",gBuy:"Comprar {p} (pronto)",pages:"páginas",note:"Plantilla de derecho alemán. No sustituye asesoría legal.",done:"✓ Guardado"},
   it:{ttl:"Contratti",sub:"Modelli premium — compila pagina per pagina. Firma su carta.",pg:"Pagina",of:"di",next:"Salva e continua →",prev:"← Indietro",make:"📄 Crea PDF (tutte le pagine)",hint:"Scegli «Salva come PDF».",free:"Gratis questo mese",gT:"Contratto premium",gP:"Prezzo singolo {p} — o incluso con un piano (Basic 3 · Pro 10 · Elite 50).",gPlans:"Vedi piani",gBuy:"Acquista {p} (presto)",pages:"pagine",note:"Modello di diritto tedesco. Non sostituisce la consulenza legale.",done:"✓ Salvato"},
   nl:{ttl:"Contracten",sub:"Premium contractsjablonen — vul pagina voor pagina in. Onderteken op papier.",pg:"Pagina",of:"van",next:"Opslaan & verder →",prev:"← Terug",make:"📄 PDF maken (alle pagina's)",hint:"Kies «Opslaan als PDF».",free:"Gratis deze maand",gT:"Premium contract",gP:"Losse prijs {p} — of inbegrepen met pakket (Basic 3 · Pro 10 · Elite 50).",gPlans:"Bekijk pakketten",gBuy:"Koop {p} (binnenkort)",pages:"pagina's",note:"Sjabloon naar Duits recht. Geen vervanging voor juridisch advies.",done:"✓ Opgeslagen"},
   pl:{ttl:"Umowy",sub:"Szablony premium — wypełniaj strona po stronie. Podpisz na papierze.",pg:"Strona",of:"z",next:"Zapisz i dalej →",prev:"← Wstecz",make:"📄 Utwórz PDF (wszystkie strony)",hint:"Wybierz «Zapisz jako PDF».",free:"Bezpłatnie w tym miesiącu",gT:"Umowa premium",gP:"Cena {p} — lub w pakiecie miesięcznie (Basic 3 · Pro 10 · Elite 50).",gPlans:"Zobacz pakiety",gBuy:"Kup {p} (wkrótce)",pages:"stron",note:"Szablon wg prawa niemieckiego. Nie zastępuje porady prawnej.",done:"✓ Zapisano"},
   sv:{ttl:"Avtal",sub:"Premiummallar — fyll i sida för sida. Underteckna på papper.",pg:"Sida",of:"av",next:"Spara & fortsätt →",prev:"← Tillbaka",make:"📄 Skapa PDF (alla sidor)",hint:"Välj «Spara som PDF».",free:"Gratis denna månad",gT:"Premiumavtal",gP:"Styckpris {p} — eller ingår med paket (Basic 3 · Pro 10 · Elite 50).",gPlans:"Se paket",gBuy:"Köp {p} (snart)",pages:"sidor",note:"Mall enligt tysk rätt. Ersätter inte juridisk rådgivning.",done:"✓ Sparat"},
   pt:{ttl:"Contratos",sub:"Modelos premium — preencha página a página. Assine em papel.",pg:"Página",of:"de",next:"Guardar & continuar →",prev:"← Voltar",make:"📄 Criar PDF (todas as páginas)",hint:"Escolha «Guardar como PDF».",free:"Grátis este mês",gT:"Contrato premium",gP:"Preço único {p} — ou incluído com plano (Basic 3 · Pro 10 · Elite 50).",gPlans:"Ver planos",gBuy:"Comprar {p} (em breve)",pages:"páginas",note:"Modelo de direito alemão. Não substitui aconselhamento jurídico.",done:"✓ Guardado"}
  };
  function L(){ return T[UIL()]||T.en; }

  /* ══ SOZLESME TANIMLARI ══ */
  var CT={
    miet:{ic:"🏠",n:"Mietvertrag",sn:"Wohnraum",price:"4,99 €",pgs:4,
      steps:[
        {t:"§1–2 · Parteien & Mietobjekt",f:[
          ["vm_name","Vermieter — Name / Firma"],["vm_addr","Vermieter — Anschrift"],
          ["mt_name","Mieter — Name"],["mt_addr","Mieter — bisherige Anschrift"],
          ["obj_addr","Mietobjekt — Straße, Nr., PLZ, Ort"],["obj_lage","Lage (Etage, links/rechts)"],
          ["obj_zi","Zimmerzahl"],["obj_qm","Wohnfläche (m²)"],["obj_mit","Mitvermietet (Keller, Stellplatz …)"]]},
        {t:"§3–5 · Mietzeit, Miete & Kaution",f:[
          ["beginn","Mietbeginn (Datum)"],["befristung","Befristung (leer = unbefristet)"],
          ["kalt","Kaltmiete (€ / Monat)"],["nk","Nebenkosten-Vorauszahlung (€)"],["heiz","Heizkosten-Vorauszahlung (€)"],
          ["zahltag","Zahlbar bis (Tag des Monats)","3"],["iban","Konto des Vermieters (IBAN)"],["kaution","Kaution (€)"]]},
        {t:"§6–9 · Pflichten & Nutzung",f:[
          ["schoen","Schönheitsreparaturen trägt","Mieter"],["tiere","Tierhaltung","nur mit Zustimmung des Vermieters"],
          ["unterv","Untervermietung","nur mit schriftlicher Zustimmung"],["hausord","Hausordnung","ist Vertragsbestandteil"]]},
        {t:"§10 · Sonstiges & Unterschriften",f:[
          ["zusatz","Zusatzvereinbarungen (optional)"],["ort","Ort"],["datum","Datum"]]}
      ]},
    arbeit:{ic:"💼",n:"Arbeitsvertrag",sn:"unbefristet/befristet",price:"4,99 €",pgs:3,
      steps:[
        {t:"§1–3 · Parteien, Tätigkeit & Probezeit",f:[
          ["ag_name","Arbeitgeber — Firma"],["ag_addr","Arbeitgeber — Anschrift"],
          ["an_name","Arbeitnehmer — Name"],["an_addr","Arbeitnehmer — Anschrift"],
          ["beginn","Beschäftigungsbeginn"],["taetig","Tätigkeit / Position"],["ort","Arbeitsort"],
          ["befristung","Befristung (leer = unbefristet)"],["probe","Probezeit (Monate)","6"]]},
        {t:"§4–7 · Arbeitszeit, Vergütung, Urlaub",f:[
          ["stunden","Wochenarbeitszeit (Std.)","40"],["gehalt","Bruttogehalt (€ / Monat)"],
          ["zahlung","Auszahlung zum","Monatsende"],["urlaub","Urlaubstage / Jahr","28"],
          ["sonder","Sonderzahlungen (optional)"]]},
        {t:"§8–10 · Schlussbestimmungen",f:[
          ["kuend","Kündigungsfrist nach Probezeit","4 Wochen zum 15. oder Monatsende"],
          ["zusatz","Nebenabreden (optional)"],["vort","Ort"],["vdatum","Datum"]]}
      ]},
    kfz:{ic:"🚗",n:"Kfz-Kaufvertrag",sn:"privat",price:"3,99 €",pgs:2,
      steps:[
        {t:"1–2 · Parteien & Fahrzeug",f:[
          ["vk_name","Verkäufer — Name"],["vk_addr","Verkäufer — Anschrift"],
          ["kf_name","Käufer — Name"],["kf_addr","Käufer — Anschrift"],
          ["marke","Marke / Modell"],["fin","Fahrgestellnummer (FIN)"],
          ["ez","Erstzulassung"],["km","Kilometerstand"],["tuev","HU/AU gültig bis"]]},
        {t:"3–5 · Kaufpreis & Übergabe",f:[
          ["preis","Kaufpreis (€)"],["zahlart","Zahlungsart","bar bei Übergabe"],
          ["uebergabe","Übergabe am (Datum)"],["maengel","Bekannte Mängel (optional)"],
          ["kort","Ort"],["kdatum","Datum"]]}
      ]}
  };

  /* ══ SAYFA URETICILERI (premium A4 hukuki sablon) ══ */
  function head(title,sub){
    return '<div class="vhd"><div class="vhd-band"></div><div class="vhd-in"><div class="vhd-t">'+title+'</div><div class="vhd-s">'+sub+'</div></div></div>';
  }
  function par(no,t,body){ return '<div class="vpar"><div class="vpn">'+no+'</div><div class="vpb"><div class="vpt">'+t+'</div><div class="vptx">'+body+'</div></div></div>'; }
  function sig2(d,a,b,ort,dat){
    return '<div class="vsig"><div class="vsig-l">'+esc(ort||"")+', '+esc(dat||"")+'</div><div class="vsig-r">'
      +'<div class="vsl"><div class="vline"></div>'+a+'</div><div class="vsl"><div class="vline"></div>'+b+'</div></div></div>';
  }
  function foot(doc,i,n){ var l=L(); return '<div class="vfoot"><span>'+doc+'</span><span>'+l.pg+' '+i+' '+l.of+' '+n+'</span></div>'; }

  function buildPages(key,d){
    var P=[];
    if(key==="miet"){
      P.push(head("MIETVERTRAG","für Wohnraum")
        +par("§ 1","Mietparteien","Zwischen "+V(d.vm_name)+", "+V(d.vm_addr)+" — nachfolgend <i>Vermieter</i> — und "+V(d.mt_name)+", "+V(d.mt_addr)+" — nachfolgend <i>Mieter</i> — wird folgender Mietvertrag geschlossen.")
        +par("§ 2","Mietobjekt","Vermietet wird die Wohnung in "+V(d.obj_addr)+", Lage: "+V(d.obj_lage,"—")+", bestehend aus "+V(d.obj_zi,"__")+" Zimmern mit einer Wohnfläche von ca. "+V(d.obj_qm,"__")+" m². Mitvermietet: "+V(d.obj_mit,"—")+". Die Wohnung wird zu Wohnzwecken überlassen."));
      P.push(par("§ 3","Mietzeit","Das Mietverhältnis beginnt am "+V(d.beginn)+" und läuft "+(d.befristung?("befristet bis "+V(d.befristung)):"auf unbestimmte Zeit")+". Für die Kündigung gelten die gesetzlichen Fristen (§ 573c BGB).")
        +par("§ 4","Miete & Betriebskosten","Die monatliche Kaltmiete beträgt "+V(d.kalt,"____")+" €. Zuzüglich sind Vorauszahlungen zu leisten: Betriebskosten "+V(d.nk,"____")+" € und Heizkosten "+V(d.heiz,"____")+" €. Die Gesamtmiete ist monatlich im Voraus, spätestens am "+V(d.zahltag,"3")+". Werktag, auf das Konto "+V(d.iban)+" zu zahlen. Über die Vorauszahlungen wird jährlich abgerechnet (§ 556 BGB).")
        +par("§ 5","Kaution","Der Mieter leistet eine Mietsicherheit in Höhe von "+V(d.kaution,"____")+" € (max. drei Kaltmieten, § 551 BGB). Die Kaution ist getrennt vom Vermögen des Vermieters verzinslich anzulegen; Zahlung in drei Monatsraten ist zulässig."));
      P.push(par("§ 6","Schönheitsreparaturen","Die Schönheitsreparaturen während der Mietzeit trägt "+V(d.schoen,"der Mieter")+", soweit sie durch vertragsgemäßen Gebrauch erforderlich werden. Starre Fristenpläne gelten nicht.")
        +par("§ 7","Tierhaltung","Kleintiere sind erlaubt. Im Übrigen gilt: "+V(d.tiere,"Tierhaltung nur mit Zustimmung des Vermieters")+". Die Zustimmung darf nur aus wichtigem Grund verweigert werden.")
        +par("§ 8","Untervermietung","Eine Gebrauchsüberlassung an Dritte ist "+V(d.unterv,"nur mit schriftlicher Zustimmung des Vermieters")+" zulässig (§ 540 BGB). Der Anspruch auf Erlaubnis nach § 553 BGB bleibt unberührt.")
        +par("§ 9","Hausordnung","Die beigefügte Hausordnung "+V(d.hausord,"ist Vertragsbestandteil")+". Der Mieter verpflichtet sich zur Rücksichtnahme gegenüber den übrigen Hausbewohnern."));
      P.push(par("§ 10","Sonstige Vereinbarungen",(d.zusatz?esc(d.zusatz):'<span class="vb">— keine —</span>')+"<br><br>Änderungen und Ergänzungen dieses Vertrages bedürfen der Textform. Sollte eine Bestimmung unwirksam sein, bleibt der Vertrag im Übrigen wirksam.")
        +'<div class="vnote">Es gelten ergänzend die Vorschriften des BGB (§§ 535 ff.).</div>'
        +sig2(d,"Vermieter","Mieter",d.ort,d.datum));
    } else if(key==="arbeit"){
      P.push(head("ARBEITSVERTRAG","")
        +par("§ 1","Vertragsparteien","Zwischen "+V(d.ag_name)+", "+V(d.ag_addr)+" — <i>Arbeitgeber</i> — und "+V(d.an_name)+", "+V(d.an_addr)+" — <i>Arbeitnehmer</i> — wird folgender Arbeitsvertrag geschlossen.")
        +par("§ 2","Beginn & Tätigkeit","Das Arbeitsverhältnis beginnt am "+V(d.beginn)+(d.befristung?(" und ist befristet bis "+V(d.befristung)+" (TzBfG)"):" und wird auf unbestimmte Zeit geschlossen")+". Der Arbeitnehmer wird als "+V(d.taetig)+" eingestellt. Arbeitsort ist "+V(d.ort)+". Der Arbeitgeber kann eine andere zumutbare, gleichwertige Tätigkeit zuweisen.")
        +par("§ 3","Probezeit","Die ersten "+V(d.probe,"6")+" Monate gelten als Probezeit. Während der Probezeit kann das Arbeitsverhältnis beiderseits mit einer Frist von zwei Wochen gekündigt werden (§ 622 Abs. 3 BGB)."));
      P.push(par("§ 4","Arbeitszeit","Die regelmäßige Arbeitszeit beträgt "+V(d.stunden,"40")+" Stunden pro Woche. Beginn und Ende richten sich nach den betrieblichen Regelungen.")
        +par("§ 5","Vergütung","Der Arbeitnehmer erhält ein Bruttomonatsgehalt von "+V(d.gehalt,"____")+" €, zahlbar zum "+V(d.zahlung,"Monatsende")+" auf ein vom Arbeitnehmer benanntes Konto. "+(d.sonder?("Sonderzahlungen: "+V(d.sonder)+"."):"") )
        +par("§ 6","Urlaub","Der Urlaubsanspruch beträgt "+V(d.urlaub,"28")+" Arbeitstage pro Kalenderjahr (mindestens der gesetzliche Anspruch nach BUrlG). Die Lage des Urlaubs ist abzustimmen.")
        +par("§ 7","Arbeitsverhinderung","Der Arbeitnehmer zeigt eine Arbeitsunfähigkeit unverzüglich an und legt spätestens am dritten Kalendertag eine ärztliche Bescheinigung vor. Entgeltfortzahlung richtet sich nach dem EFZG."));
      P.push(par("§ 8","Verschwiegenheit","Der Arbeitnehmer bewahrt über alle Geschäfts- und Betriebsgeheimnisse Stillschweigen — auch nach Beendigung des Arbeitsverhältnisses.")
        +par("§ 9","Kündigung","Nach der Probezeit gilt eine Kündigungsfrist von "+V(d.kuend,"4 Wochen zum 15. oder zum Monatsende")+"; gesetzliche Verlängerungen (§ 622 BGB) bleiben unberührt. Die Kündigung bedarf der Schriftform.")
        +par("§ 10","Schlussbestimmungen",(d.zusatz?("Nebenabreden: "+esc(d.zusatz)+". "):"Nebenabreden bestehen nicht. ")+"Änderungen bedürfen der Schriftform. Die Unwirksamkeit einzelner Bestimmungen berührt den Vertrag im Übrigen nicht.")
        +'<div class="vnote">Nachweisgesetz (NachwG): Der Arbeitgeber händigt die wesentlichen Vertragsbedingungen schriftlich aus.</div>'
        +sig2(d,"Arbeitgeber","Arbeitnehmer",d.vort,d.vdatum));
    } else {
      P.push(head("KFZ-KAUFVERTRAG","zwischen Privatpersonen")
        +par("1.","Vertragsparteien","Verkäufer: "+V(d.vk_name)+", "+V(d.vk_addr)+"<br>Käufer: "+V(d.kf_name)+", "+V(d.kf_addr))
        +par("2.","Fahrzeug","Marke/Modell: "+V(d.marke)+" · FIN: "+V(d.fin)+"<br>Erstzulassung: "+V(d.ez)+" · Kilometerstand: "+V(d.km,"______")+" km · HU/AU: "+V(d.tuev,"—")));
      P.push(par("3.","Kaufpreis","Der Kaufpreis beträgt "+V(d.preis,"______")+" €. Zahlung: "+V(d.zahlart,"bar bei Übergabe")+". Das Fahrzeug bleibt bis zur vollständigen Zahlung Eigentum des Verkäufers.")
        +par("4.","Gewährleistung","Das Fahrzeug wird — soweit gesetzlich zulässig — <b>unter Ausschluss jeglicher Sachmängelhaftung</b> verkauft (Privatverkauf). Der Ausschluss gilt nicht für Vorsatz, Arglist oder Schäden aus der Verletzung von Leben, Körper oder Gesundheit. Bekannte Mängel: "+(d.maengel?esc(d.maengel):'<span class="vb">— keine —</span>'))
        +par("5.","Übergabe","Die Übergabe des Fahrzeugs mit Schlüsseln und Papieren (ZB I & II) erfolgt am "+V(d.uebergabe)+". Der Käufer meldet das Fahrzeug unverzüglich um.")
        +sig2(d,"Verkäufer","Käufer",d.kort,d.kdatum));
    }
    var n=P.length, doc=CT[key].n;
    return P.map(function(inner,i){ return '<div class="vsA4">'+inner+foot(doc,i+1,n)+'</div>'; });
  }

  /* ══ CSS ══ */
  var VCSS=[
  ".vsA4{width:794px;height:1123px;position:relative;overflow:hidden;background:#fff;color:#1e1e26;font-family:'Inter','Segoe UI',Arial,sans-serif;padding:56px 62px 70px}",
  ".vsA4 *{margin:0;padding:0;box-sizing:border-box}",
  ".vsA4 .vhd{margin-bottom:26px}",
  ".vsA4 .vhd-band{height:6px;background:linear-gradient(90deg,#0a1a3f,#0d2452 55%,#d8b25a);border-radius:100px}",
  ".vsA4 .vhd-in{padding-top:18px}",
  ".vsA4 .vhd-t{font-size:26px;font-weight:800;letter-spacing:3px;color:#0a1a3f}",
  ".vsA4 .vhd-s{font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#8a6a1f;font-weight:700;margin-top:4px}",
  ".vsA4 .vpar{display:flex;gap:16px;margin-bottom:18px}",
  ".vsA4 .vpn{width:44px;flex-shrink:0;font-size:13px;font-weight:800;color:#8a6a1f;padding-top:1px}",
  ".vsA4 .vpt{font-size:12.5px;font-weight:800;letter-spacing:.8px;text-transform:uppercase;color:#0a1a3f;margin-bottom:5px}",
  ".vsA4 .vptx{font-size:11.8px;line-height:1.72;color:#33333d;text-align:justify}",
  ".vsA4 .vv{color:#0a1a3f;font-weight:700;border-bottom:1px solid rgba(216,178,90,.65)}",
  ".vsA4 .vb{color:#b9b9c2;letter-spacing:1px}",
  ".vsA4 .vnote{font-size:10px;color:#8a8a92;border-left:3px solid #d8b25a;padding:6px 10px;background:#faf7ef;margin:6px 0 0 60px}",
  ".vsA4 .vsig{margin-top:44px;padding-left:60px}",
  ".vsA4 .vsig-l{font-size:12px;color:#33333d;margin-bottom:38px}",
  ".vsA4 .vsig-r{display:flex;gap:40px}",
  ".vsA4 .vsl{flex:1;font-size:11px;color:#55555d;text-align:center}",
  ".vsA4 .vline{border-top:1.5px solid #22222a;margin-bottom:6px}",
  ".vsA4 .vfoot{position:absolute;bottom:26px;left:62px;right:62px;display:flex;justify-content:space-between;font-size:9.5px;color:#98989f;border-top:1px solid #ececf0;padding-top:8px}"
  ].join("\n");

  function addCss(){
    if(document.getElementById("ch-vst-css")) return;
    var st=document.createElement("style"); st.id="ch-vst-css";
    st.textContent=VCSS+"\n"+[
      "#pnl-vst{position:fixed;inset:0;z-index:520;background:var(--bg,#17171d);display:none;flex-direction:column;overflow:hidden}",
      "#pnl-vst.on{display:flex}",
      ".vst-topbar{display:flex;align-items:center;gap:10px;padding:14px 16px;border-bottom:1px solid var(--bdr,rgba(255,255,255,.1));background:var(--s1,#1d1d24);flex-shrink:0}",
      ".vst-back{width:34px;height:34px;border-radius:10px;border:1px solid var(--bdr,rgba(255,255,255,.12));background:rgba(255,255,255,.05);color:var(--t2,#e0e0ff);font-size:20px;cursor:pointer}",
      ".vst-ttl{font-size:16px;font-weight:800;color:var(--t1,#fff)}",
      ".vst-badge{margin-left:auto;font-size:11px;font-weight:700;color:var(--gold,#d4a84a);border:1px solid rgba(212,168,74,.35);border-radius:100px;padding:5px 12px}",
      ".vst-scroll{flex:1;overflow-y:auto;padding:16px}",
      ".vst-wrap{max-width:1060px;margin:0 auto}",
      ".vst-sub{font-size:13px;color:var(--t3,#a8a8d0);margin-bottom:14px}",
      ".vst-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:14px}",
      ".vst-card{border:1px solid rgba(212,168,74,.18);border-radius:16px;background:var(--s2,#25252e);cursor:pointer;padding:18px;transition:transform .14s,box-shadow .2s,border-color .2s}",
      ".vst-card:hover{transform:translateY(-2px);border-color:var(--gold,#d4a84a);box-shadow:0 14px 34px rgba(0,0,0,.35)}",
      ".vst-ic{width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,rgba(212,168,74,.18),rgba(212,168,74,.05));border:1px solid rgba(212,168,74,.3);display:flex;align-items:center;justify-content:center;font-size:26px;margin-bottom:12px}",
      ".vst-n{font-size:15px;font-weight:800;color:var(--t1,#fff)}",
      ".vst-sn{font-size:11.5px;color:var(--t3,#a8a8d0);margin:2px 0 10px}",
      ".vst-meta{display:flex;justify-content:space-between;align-items:center}",
      ".vst-pr{font-size:13px;font-weight:800;color:var(--gold,#d4a84a)}",
      ".vst-pgc{font-size:11px;color:var(--t3,#a8a8d0)}",
      ".vst-steps{display:flex;align-items:center;gap:6px;margin:4px 0 14px}",
      ".vst-dot{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11.5px;font-weight:800;border:1.5px solid var(--bdr,rgba(255,255,255,.15));color:var(--t3,#a8a8d0);background:var(--s2,#25252e)}",
      ".vst-dot.on{border-color:var(--gold,#d4a84a);color:#1a1400;background:linear-gradient(135deg,#d4a84a,#f0c860)}",
      ".vst-dot.ok{border-color:rgba(66,223,148,.6);color:#42df94}",
      ".vst-dash{flex:1;height:1.5px;background:var(--bdr,rgba(255,255,255,.12))}",
      ".vst-pgttl{font-size:14px;font-weight:800;color:var(--t1,#fff);margin-bottom:10px}",
      ".vst-ed{display:flex;gap:18px;align-items:flex-start}",
      ".vst-form{flex:1;min-width:300px;max-width:440px}",
      ".vst-prev{flex:1;position:sticky;top:0}",
      ".vst-prevbox{border:1px solid var(--bdr,rgba(255,255,255,.12));border-radius:12px;overflow:hidden;background:#3a3a44;padding:10px;display:flex;justify-content:center}",
      ".vst-previn{overflow:hidden;position:relative}",
      "@media(max-width:900px){.vst-ed{flex-direction:column}.vst-prev{position:static;width:100%}}",
      ".vst-in{width:100%;padding:11px 12px;margin-bottom:8px;background:var(--s2,#25252e);border:1px solid var(--bdr,rgba(255,255,255,.12));border-radius:10px;color:var(--t1,#fff);font-size:13.5px;font-family:inherit}",
      ".vst-in:focus{outline:none;border-color:rgba(212,168,74,.5)}",
      ".vst-fl{font-size:10.5px;font-weight:700;color:var(--t3,#a8a8d0);margin:0 0 4px 2px;letter-spacing:.3px}",
      ".vst-nav{display:flex;gap:9px;margin-top:12px}",
      ".vst-prevb{padding:12px 16px;border-radius:11px;border:1px solid var(--bdr,rgba(255,255,255,.14));background:rgba(255,255,255,.05);color:var(--t2,#e0e0ff);font-size:13.5px;font-weight:700;cursor:pointer}",
      ".vst-nextb{flex:1;padding:12px;border-radius:11px;border:none;background:linear-gradient(135deg,#d4a84a,#f0c860);color:#1a1400;font-size:14px;font-weight:800;cursor:pointer}",
      ".vst-note{font-size:11px;color:var(--t3,#a8a8d0);margin-top:10px;line-height:1.5}",
      ".vst-saved{font-size:12px;color:#42df94;font-weight:700;margin-top:8px;text-align:center;opacity:0;transition:opacity .3s}",
      ".vst-saved.show{opacity:1}"
    ].join("\n");
    document.head.appendChild(st);
  }

  /* ══ durum ══ */
  var CUR=null, STEP=0;
  function dkey(k){ return "ch_vst_"+k; }
  function getD(k){ try{ return JSON.parse(localStorage.getItem(dkey(k))||"{}"); }catch(e){ return {}; } }
  function setD(k,d){ try{ localStorage.setItem(dkey(k), JSON.stringify(d)); }catch(e){} }

  function panel(){
    var p=document.getElementById("pnl-vst");
    if(!p){ p=document.createElement("div"); p.className="pnl"; p.id="pnl-vst"; document.body.appendChild(p); }
    return p;
  }
  function topbar(t){
    var l=L();
    return '<div class="vst-topbar"><button class="vst-back" id="vst-back">‹</button><div class="vst-ttl">'+esc(t)+'</div><div class="vst-badge">3,99–4,99 € · Basic 3 · Pro 10 · Elite 50</div></div>';
  }
  function renderCat(){
    CUR=null; var l=L(), p=panel();
    p.innerHTML=topbar(l.ttl)
     +'<div class="vst-scroll"><div class="vst-wrap">'
     +'<div class="vst-sub">'+esc(l.sub)+'</div>'
     +'<div class="vst-grid">'+Object.keys(CT).map(function(k){var c=CT[k];
        return '<div class="vst-card" data-k="'+k+'"><div class="vst-ic">'+c.ic+'</div><div class="vst-n">'+esc(c.n)+'</div><div class="vst-sn">'+esc(c.sn)+'</div>'
          +'<div class="vst-meta"><span class="vst-pr">'+esc(c.price)+'</span><span class="vst-pgc">'+c.pgs+' '+esc(l.pages)+'</span></div></div>';
      }).join("")+'</div>'
     +'<div class="vst-note">'+esc(l.note)+'</div>'
     +'</div></div>';
    document.getElementById("vst-back").addEventListener("click",function(){ try{ gP("chat"); }catch(e){} });
    p.querySelectorAll(".vst-card").forEach(function(c){
      c.addEventListener("click",function(){ CUR=c.getAttribute("data-k"); STEP=0; renderStep(); });
    });
  }
  var _vt=null;
  function schedPrev(){ clearTimeout(_vt); _vt=setTimeout(updPrev,240); }
  function updPrev(){
    try{
      var box=document.getElementById("vst-previn"); if(!box||!CUR) return;
      var pages=buildPages(CUR,getD(CUR));
      var show=Math.min(STEP,pages.length-1);
      box.innerHTML=pages[show];
      var w=box.parentNode.clientWidth;
      var sc=Math.min(1,w/794);
      var a4=box.querySelector(".vsA4");
      a4.style.transform="scale("+sc+")"; a4.style.transformOrigin="top left";
      box.style.width=(794*sc)+"px"; box.style.height=(1123*sc)+"px";
    }catch(e){}
  }
  function renderStep(){
    var l=L(), p=panel(), c=CT[CUR], d=getD(CUR), st=c.steps[STEP];
    var dots=c.steps.map(function(s,i){
      var cls=i===STEP?"on":(i<STEP?"ok":"");
      return '<div class="vst-dot '+cls+'">'+(i<STEP?"✓":(i+1))+'</div>'+(i<c.steps.length-1?'<div class="vst-dash"></div>':'');
    }).join("");
    p.innerHTML=topbar(c.n+" · "+l.pg+" "+(STEP+1)+" "+l.of+" "+c.steps.length)
     +'<div class="vst-scroll"><div class="vst-wrap">'
     +'<div class="vst-steps">'+dots+'</div>'
     +'<div class="vst-ed"><div class="vst-form">'
     +'<div class="vst-pgttl">'+esc(st.t)+'</div>'
     +st.f.map(function(f){
        return '<div class="vst-fl">'+esc(f[1])+'</div><input class="vst-in" data-f="'+f[0]+'" value="'+esc(d[f[0]]||"")+'" placeholder="'+esc(f[2]||"")+'">';
      }).join("")
     +'<div class="vst-nav">'+(STEP>0?'<button class="vst-prevb" id="vst-pv">'+esc(l.prev)+'</button>':'')
     +'<button class="vst-nextb" id="vst-nx">'+(STEP<c.steps.length-1?esc(l.next):esc(l.make))+'</button></div>'
     +'<div class="vst-saved" id="vst-sv">'+esc(l.done)+'</div>'
     +'<div class="vst-note">'+esc(l.note)+' · '+esc(l.hint)+'</div>'
     +'</div>'
     +'<div class="vst-prev"><div class="vst-prevbox"><div class="vst-previn" id="vst-previn"></div></div></div>'
     +'</div></div></div>';
    document.getElementById("vst-back").addEventListener("click",renderCat);
    p.querySelectorAll("input[data-f]").forEach(function(el){
      el.addEventListener("input",function(){ var dd=getD(CUR); dd[el.getAttribute("data-f")]=el.value; setD(CUR,dd); schedPrev(); });
    });
    var pv=document.getElementById("vst-pv");
    if(pv) pv.addEventListener("click",function(){ STEP--; renderStep(); });
    document.getElementById("vst-nx").addEventListener("click",function(){
      if(STEP<c.steps.length-1){
        STEP++;
        var sv=document.getElementById("vst-sv"); if(sv){ sv.classList.add("show"); }
        setTimeout(renderStep,120);
      } else { makePdf(); }
    });
    updPrev(); setTimeout(updPrev,300);
  }

  /* ══ kota + kapı + PDF ══ */
  var QUOTA={basic:3,pro:10,elite:50};
  function plan(){ var pl=""; try{ pl=(window.P&&P.plan)||JSON.parse(localStorage.getItem("ch_user")||"{}").plan||""; }catch(e){} return pl; }
  function monthKey(){ var dt=new Date(); return "ch_vst_used_"+dt.getFullYear()+"_"+(dt.getMonth()+1); }
  function used(){ try{ return +(localStorage.getItem(monthKey())||0); }catch(e){ return 0; } }
  function bumpUsed(){ try{ localStorage.setItem(monthKey(), String(used()+1)); }catch(e){} }
  function gate(){
    var l=L(), c=CT[CUR];
    var m=document.createElement("div"); m.className="jb-modal";
    m.innerHTML='<div class="jb-sheet" style="max-width:430px"><div class="jb-sheet-h"><b>'+esc(l.gT)+' — '+esc(c.n)+'</b></div>'
     +'<div class="jb-sheet-b" style="white-space:normal">'+esc(l.gP.replace("{p}",c.price))+'</div>'
     +'<div class="jb-sheet-f"><button class="jb-mbtn" disabled style="opacity:.55">'+esc(l.gBuy.replace("{p}",c.price))+'</button>'
     +'<button class="jb-mbtn p" id="vst-gp">'+esc(l.gPlans)+'</button></div></div>';
    m.addEventListener("click",function(e){ if(e.target===m) m.remove(); });
    document.body.appendChild(m);
    document.getElementById("vst-gp").addEventListener("click",function(){ m.remove(); try{ gP("konto"); }catch(e){} });
  }
  function makePdf(){
    var pl=plan(), q=QUOTA[pl]||0;
    if(!q){ gate(); return; }
    if(used()>=q){ gate(); return; }
    var pages=buildPages(CUR,getD(CUR));
    var html='<!doctype html><html><head><meta charset="utf-8"><title>'+esc(CT[CUR].n)+'</title>'
     +'<style>@page{size:A4;margin:0}html,body{margin:0;padding:0}'+VCSS+'\n.vsA4{page-break-after:always}.vsA4:last-child{page-break-after:auto}</style></head><body>'
     +pages.join("")
     +'<script>window.onload=function(){setTimeout(function(){window.print();},350);};<\/script></body></html>';
    var w=window.open("","_blank");
    if(!w){ alert("Popup?"); return; }
    w.document.open(); w.document.write(html); w.document.close();
    bumpUsed();
  }

  /* ══ nav ══ */
  function addNav(){
    if(document.getElementById("nav-vst")) return;
    var l=L();
    var nav=document.createElement("div"); nav.className="sn sb-photo-btn"; nav.id="nav-vst";
    nav.setAttribute("onclick","gP('vst')");
    nav.style.cssText="cursor:pointer";
    nav.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="var(--gold,#d4a84a)" stroke-width="1.8" stroke-linecap="round" style="width:20px;height:20px;flex-shrink:0"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><path d="M8 13h8M8 17h5"/></svg>'
      +'<div><div class="sb-photo-t" id="nav-vst-t">'+esc(l.ttl)+'</div></div>';
    var ref=document.getElementById("nav-cv")||document.getElementById("nav-jobs")||document.querySelector(".sb-photo-btn");
    if(ref&&ref.parentNode){ ref.parentNode.insertBefore(nav, ref.nextSibling); }
    else { var sb=document.getElementById("sb"); if(sb) sb.appendChild(nav); }
  }

  function boot(){ addCss(); addNav(); if(!document.getElementById("pnl-vst")){ panel(); renderCat(); } }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",boot); else boot();
  setInterval(function(){
    try{
      addNav();
      var t=document.getElementById("nav-vst-t"); if(t&&t.textContent!==L().ttl) t.textContent=L().ttl;
    }catch(e){}
  },1500);
})();}catch(e){}
</script>
VSTHTML;

$n = 0;
$src = preg_replace('/<\/body>/', $bundle . "\n</body>", $src, 1, $n);
if (!$n) exit("HATA: </body> bulunamadi — yazilmadi.\n");

$tmp = tempnam(sys_get_temp_dir(), 'idx') . '.php';
file_put_contents($tmp, $src);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "LINT HATASI — index.php degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
file_put_contents($file, $src);
echo "✓ CH_VST uygulandi.\n";
echo "Test: sol menude 'Verträge' -> 3 sozlesme karti -> sec -> Seite 1/N sihirbazi -> doldur, Kaydet&devam -> son sayfada PDF.\n";
echo "Kapı: plansiz -> fiyat modali; paketli aylik kota (basic 3 / pro 10 / elite 50).\n";
