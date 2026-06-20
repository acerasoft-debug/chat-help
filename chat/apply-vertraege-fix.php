<?php
/**
 * ChatHelp — Verträge KESİN FIX (inline, scope-bağımsız) + kademeli fiyat + textarea büyüme
 * -----------------------------------------------------------------------------------------
 *  • Sözleşmeleri ana script'in İÇİNE (getDocPrice'tan sonra, aynı scope) enjekte eder
 *    -> DOCS/CATS/DOC_TIER'a kesin erişir (IIFE olsa bile).
 *  • Fiyat kademeleri: einfach 1,99€ / standard 3,99€ / komplex 4,99€ (mevcut price ID'ler).
 *  • Soru textarea'ları yazdıkça kendiliğinden büyür (estetik).
 *
 * KULLANIM: chat-help.com/chat/apply-vertraege-fix.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src   = file_get_contents($file);
$start = $src;
file_put_contents($file . '.bak-vtrfix-' . date('Ymd-His'), $src);
$rep = [];

/* ── 1) Sözleşmeleri ana scope'a inline enjekte et ── */
$anchor = "function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if (strpos($src, 'CH_VTR_INLINE') !== false) {
    $rep[] = ['#1 inline (zaten ekli)', 0];
} elseif (strpos($src, $anchor) === false) {
    $rep[] = ['#1 anchor (getDocPrice) BULUNAMADI', 0];
} else {
    $js = <<<'JS'

/* CH_VTR_INLINE — Verträge (aynı scope) */
try{(function(){
  function P(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var V={
    vtr_arbeitsvertrag:{ic:'📄',n:'Arbeitsvertrag',cat:'vertraege',tier:'standard',q:[P('rolle','Ihre Rolle',true,['Arbeitgeber','Arbeitnehmer']),P('vertragspartner','Andere Partei: Name & Anschrift',true),P('position','Stellenbezeichnung / Tätigkeit',true),P('beginn','Arbeitsbeginn (Datum)',true),P('befristung','Vertragsart',true,['Unbefristet','Befristet']),P('befristet_bis','Falls befristet: bis (Datum)',false),P('stunden','Wochenarbeitszeit (Stunden)',true),P('gehalt','Bruttogehalt monatlich (€)',true),P('urlaub','Urlaubstage pro Jahr',false),P('probezeit','Probezeit',false,['Keine','1 Monat','3 Monate','6 Monate'])]},
    vtr_minijob:{ic:'🧾',n:'Minijob-Arbeitsvertrag',cat:'vertraege',tier:'einfach',q:[P('rolle','Ihre Rolle',true,['Arbeitgeber','Arbeitnehmer']),P('vertragspartner','Andere Partei: Name & Anschrift',true),P('taetigkeit','Tätigkeit',true),P('beginn','Beginn (Datum)',true),P('stunden','Stunden pro Woche/Monat',true),P('verguetung','Vergütung (€, max. 538 €/Monat)',true),P('urlaub','Urlaubsanspruch (Tage)',false)]},
    vtr_freelancer:{ic:'💻',n:'Freier-Mitarbeiter-Vertrag',cat:'vertraege',tier:'standard',q:[P('vertragspartner','Auftraggeber: Name & Anschrift',true),P('leistung','Leistung / Projektbeschreibung',true),P('beginn','Beginn (Datum)',true),P('dauer','Laufzeit / Projektende',false),P('verguetung','Honorar (€, Satz oder Pauschal)',true),P('zahlung','Zahlungsweise',false,['Nach Rechnung','Monatlich','Nach Meilenstein']),P('rechte','Nutzungsrechte übertragen?',false,['Ja','Nein'])]},
    vtr_praktikum:{ic:'🎓',n:'Praktikumsvertrag',cat:'vertraege',tier:'einfach',q:[P('vertragspartner','Unternehmen: Name & Anschrift',true),P('art','Art des Praktikums',true,['Freiwillig','Pflichtpraktikum']),P('bereich','Einsatzbereich',true),P('beginn','Beginn (Datum)',true),P('ende','Ende (Datum)',true),P('stunden','Wochenstunden',false),P('verguetung','Vergütung (€/Monat, optional)',false)]},
    vtr_nda:{ic:'🔒',n:'Geheimhaltungsvereinbarung (NDA)',cat:'vertraege',tier:'standard',q:[P('vertragspartner','Andere Partei: Name & Anschrift',true),P('zweck','Zweck der Zusammenarbeit',true),P('richtung','Geheimhaltung',true,['Einseitig','Gegenseitig']),P('gegenstand','Vertrauliche Informationen (kurz)',true),P('dauer','Geheimhaltungsdauer (z.B. 3 Jahre)',false),P('vertragsstrafe','Vertragsstrafe?',false,['Ja','Nein'])]},
    vtr_dienstleistung:{ic:'🤝',n:'Dienstleistungsvertrag',cat:'vertraege',tier:'standard',q:[P('vertragspartner','Auftraggeber: Name & Anschrift',true),P('leistung','Beschreibung der Dienstleistung',true),P('beginn','Beginn (Datum)',true),P('verguetung','Vergütung (€)',true),P('zahlung','Zahlungsbedingungen',false),P('kuendigung','Kündigungsfrist',false)]},
    vtr_werkvertrag:{ic:'🛠️',n:'Werkvertrag',cat:'vertraege',tier:'standard',q:[P('vertragspartner','Auftraggeber: Name & Anschrift',true),P('werk','Zu erstellendes Werk',true),P('beginn','Beginn (Datum)',false),P('fertig','Fertigstellung bis (Datum)',true),P('verguetung','Werklohn (€)',true),P('abnahme','Abnahmebedingungen',false),P('gewaehr','Gewährleistung (Monate)',false)]},
    vtr_beratervertrag:{ic:'💼',n:'Beratervertrag',cat:'vertraege',tier:'standard',q:[P('vertragspartner','Auftraggeber: Name & Anschrift',true),P('gegenstand','Beratungsgegenstand',true),P('beginn','Beginn (Datum)',true),P('umfang','Umfang (Stunden/Tage)',false),P('honorar','Honorar (€)',true),P('zahlung','Zahlungsbedingungen',false),P('verschwiegenheit','Verschwiegenheit?',false,['Ja','Nein']),P('laufzeit','Laufzeit / Kündigung',false)]},
    vtr_aufhebung_arbeit:{ic:'🤝',n:'Aufhebungsvertrag (Arbeit)',cat:'vertraege',tier:'standard',q:[P('rolle','Ihre Rolle',true,['Arbeitgeber','Arbeitnehmer']),P('vertragspartner','Andere Partei: Name & Anschrift',true),P('position','Position',true),P('enddatum','Beendigung zum (Datum)',true),P('abfindung','Abfindung (€, optional)',false),P('resturlaub','Resturlaub / Überstunden',false),P('freistellung','Freistellung?',false,['Ja','Nein']),P('zeugnis','Qualifiziertes Zeugnis?',false,['Ja','Nein'])]},
    vtr_untermietvertrag:{ic:'🏠',n:'Untermietvertrag',cat:'vertraege',tier:'einfach',q:[P('vertragspartner','Untermieter: Name & Anschrift',true),P('objekt','Untervermietetes Zimmer/Objekt',true),P('adresse','Adresse',true),P('beginn','Mietbeginn (Datum)',true),P('befristung','Befristet?',false,['Unbefristet','Befristet']),P('miete','Miete monatlich (€, warm)',true),P('kaution','Kaution (€)',false),P('moebliert','Möbliert?',false,['Ja','Teilweise','Nein'])]},
    vtr_gewerbemiete:{ic:'🏢',n:'Gewerbemietvertrag',cat:'vertraege',tier:'standard',q:[P('rolle','Ihre Rolle',true,['Vermieter','Mieter']),P('vertragspartner','Andere Partei: Name & Anschrift',true),P('objekt','Gewerbeobjekt & Adresse',true),P('flaeche','Fläche (m²)',false),P('nutzung','Art der Nutzung',true),P('beginn','Mietbeginn (Datum)',true),P('miete','Nettokaltmiete (€)',true),P('nebenkosten','Nebenkosten (€)',false),P('laufzeit','Laufzeit / Kündigung',false)]},
    vtr_stellplatz:{ic:'🅿️',n:'Stellplatz-/Garagenmietvertrag',cat:'vertraege',tier:'einfach',q:[P('vertragspartner','Mieter: Name & Anschrift',true),P('objekt','Stellplatz/Garage (Nr. & Adresse)',true),P('beginn','Mietbeginn (Datum)',true),P('miete','Miete monatlich (€)',true),P('kaution','Kaution (€)',false),P('kuendigung','Kündigungsfrist',false)]},
    vtr_uebergabe:{ic:'📋',n:'Wohnungsübergabeprotokoll',cat:'vertraege',tier:'einfach',q:[P('vertragspartner','Andere Partei (Mieter/Vermieter)',true),P('adresse','Adresse der Wohnung',true),P('anlass','Anlass',true,['Einzug','Auszug']),P('datum','Übergabedatum (Datum)',true),P('zaehler','Zählerstände',false),P('schluessel','Anzahl Schlüssel',false),P('maengel','Mängel (kurz)',false)]},
    vtr_mietvertrag_wohnung:{ic:'🏠',n:'Mietvertrag Wohnung',cat:'vertraege',tier:'standard',q:[P('rolle','Ihre Rolle',true,['Vermieter','Mieter']),P('vertragspartner','Andere Partei: Name & Anschrift',true),P('adresse','Adresse der Wohnung',true),P('groesse','Wohnfläche (m²) & Zimmer',true),P('beginn','Mietbeginn (Datum)',true),P('befristung','Mietdauer',false,['Unbefristet','Befristet']),P('kaltmiete','Kaltmiete (€)',true),P('nebenkosten','Nebenkosten (€)',false),P('kaution','Kaution (€, max. 3 KM)',false),P('haustiere','Haustiere',false,['Erlaubt','Nach Absprache','Nicht erlaubt']),P('schoenheit','Schönheitsreparaturen Mieter?',false,['Ja','Nein'])]},
    vtr_wg_vertrag:{ic:'🛏️',n:'WG-Mitbewohnervertrag',cat:'vertraege',tier:'einfach',q:[P('vertragspartner','Hauptmieter: Name & Anschrift',true),P('adresse','Adresse der WG',true),P('zimmer','Zimmer (Nr./Größe)',true),P('beginn','Beginn (Datum)',true),P('miete','Zimmermiete (€, warm)',true),P('kaution','Kaution (€)',false),P('gemeinschaft','Mitbenutzung',false),P('regeln','Hausregeln (kurz)',false)]},
    vtr_pacht:{ic:'🌾',n:'Pachtvertrag',cat:'vertraege',tier:'standard',q:[P('rolle','Ihre Rolle',true,['Verpächter','Pächter']),P('vertragspartner','Andere Partei: Name & Anschrift',true),P('gegenstand','Pachtgegenstand',true),P('nutzung','Art der Nutzung',true),P('beginn','Pachtbeginn (Datum)',true),P('laufzeit','Laufzeit',false),P('pachtzins','Pachtzins (€)',true),P('instandhaltung','Instandhaltung',false)]},
    vtr_mietaufhebung:{ic:'📄',n:'Mietaufhebungsvertrag',cat:'vertraege',tier:'einfach',q:[P('rolle','Ihre Rolle',true,['Vermieter','Mieter']),P('vertragspartner','Andere Partei: Name & Anschrift',true),P('adresse','Adresse der Wohnung',true),P('enddatum','Endet am (Datum)',true),P('uebergabe','Übergabe am (Datum)',false),P('kaution_rueck','Kautionsrückzahlung (Frist)',false),P('abgeltung','Sonstige Vereinbarungen',false)]},
    vtr_leihvertrag:{ic:'📦',n:'Leihvertrag (unentgeltlich)',cat:'vertraege',tier:'einfach',q:[P('vertragspartner','Entleiher: Name & Anschrift',true),P('gegenstand','Leihgegenstand',true),P('beginn','Überlassung ab (Datum)',true),P('rueckgabe','Rückgabe bis (Datum)',true),P('zustand','Zustand bei Übergabe',false),P('bedingungen','Besondere Bedingungen',false)]},
    vtr_kaufvertrag_privat:{ic:'🛒',n:'Kaufvertrag (privat)',cat:'vertraege',tier:'einfach',q:[P('rolle','Ihre Rolle',true,['Verkäufer','Käufer']),P('vertragspartner','Andere Partei: Name & Anschrift',true),P('gegenstand','Kaufgegenstand (Beschreibung)',true),P('zustand','Zustand',false,['Neu','Gebraucht']),P('preis','Kaufpreis (€)',true),P('zahlung','Zahlungsweise',false,['Bar','Überweisung']),P('uebergabe','Übergabedatum (Datum)',false),P('gewaehr','Gewährleistung ausschließen?',false,['Ja','Nein'])]},
    vtr_tierkauf:{ic:'🐾',n:'Tierkaufvertrag',cat:'vertraege',tier:'einfach',q:[P('rolle','Ihre Rolle',true,['Verkäufer','Käufer']),P('vertragspartner','Andere Partei: Name & Anschrift',true),P('tier','Tierart, Rasse, Name, Geburtsdatum',true),P('kennzeichen','Chip-/Zuchtnummer',false),P('preis','Kaufpreis (€)',true),P('gesundheit','Gesundheit / Impfungen',false),P('uebergabe','Übergabedatum (Datum)',false)]},
    vtr_darlehen:{ic:'💶',n:'Darlehensvertrag (privat)',cat:'vertraege',tier:'standard',q:[P('rolle','Ihre Rolle',true,['Darlehensgeber','Darlehensnehmer']),P('vertragspartner','Andere Partei: Name & Anschrift',true),P('betrag','Darlehenssumme (€)',true),P('zweck','Zweck',false),P('zinsen','Zinssatz (% p.a., 0=zinslos)',false),P('auszahlung','Auszahlung (Datum)',true),P('rueckzahlung','Rückzahlung (Raten/Termin)',true)]},
    vtr_schuldanerkenntnis:{ic:'✍️',n:'Schuldanerkenntnis',cat:'vertraege',tier:'einfach',q:[P('vertragspartner','Gläubiger: Name & Anschrift',true),P('betrag','Schuldsumme (€)',true),P('grund','Grund der Schuld',true),P('rueckzahlung','Rückzahlung (Raten/Termin)',true),P('zinsen','Verzugszinsen?',false,['Ja','Nein'])]},
    vtr_quittung:{ic:'🧾',n:'Quittung',cat:'vertraege',tier:'einfach',q:[P('vertragspartner','Zahlender: Name',true),P('betrag','Betrag (€)',true),P('zweck','Verwendungszweck',true),P('datum','Datum (Datum)',true),P('zahlart','Zahlungsart',false,['Bar','Überweisung'])]},
    vtr_schenkung:{ic:'🎁',n:'Schenkungsvertrag',cat:'vertraege',tier:'standard',q:[P('rolle','Ihre Rolle',true,['Schenker','Beschenkter']),P('vertragspartner','Andere Partei: Name & Anschrift',true),P('gegenstand','Geschenk (Beschreibung)',true),P('wert','Wert (€)',false),P('uebergabe','Wirksamkeit (Datum)',false),P('auflagen','Auflagen/Rückforderung?',false),P('hinweis_notar','Bei Immobilien: notarielle Beurkundung nötig',false)]},
    vtr_unterhaltsvereinbarung:{ic:'💶',n:'Unterhaltsvereinbarung',cat:'vertraege',tier:'standard',q:[P('vertragspartner','Andere Partei: Name & Anschrift',true),P('betrifft','Unterhalt für',true,['Kind','Ehegatte/Trennung','Sonstige']),P('berechtigter','Name des/der Berechtigten',true),P('betrag','Monatlicher Unterhalt (€)',true),P('beginn','Zahlung ab (Datum)',true),P('anpassung','Dynamisierung?',false,['Ja','Nein']),P('zahlweise','Zahlweise',false)]},
    vtr_lizenzvertrag:{ic:'📝',n:'Lizenzvertrag',cat:'vertraege',tier:'standard',q:[P('rolle','Ihre Rolle',true,['Lizenzgeber','Lizenznehmer']),P('vertragspartner','Andere Partei: Name & Anschrift',true),P('gegenstand','Lizenzgegenstand',true),P('umfang','Umfang',true,['Einfache Lizenz','Exklusive Lizenz']),P('gebiet','Gebiet',false),P('dauer','Laufzeit',false),P('verguetung','Lizenzgebühr (€)',true),P('nutzung','Erlaubte Nutzungsarten',false)]},
    vtr_kooperationsvertrag:{ic:'🔗',n:'Kooperationsvertrag',cat:'vertraege',tier:'standard',q:[P('vertragspartner','Kooperationspartner: Name & Anschrift',true),P('ziel','Ziel der Kooperation',true),P('leistungen','Beiträge beider Seiten',true),P('beginn','Beginn (Datum)',true),P('dauer','Laufzeit',false),P('kosten','Kostenverteilung',false),P('verschwiegenheit','Vertraulichkeit?',false,['Ja','Nein'])]},
    vtr_berliner_testament:{ic:'📜',n:'Berliner Testament',cat:'vertraege',tier:'standard',q:[P('ehepartner','Name des Ehe-/Lebenspartners',true),P('schlusserben','Schlusserben (z.B. Kinder)',true),P('pflichtteil','Pflichtteilsstrafklausel?',false,['Ja','Nein']),P('wiederheirat','Wiederverheiratungsklausel?',false,['Ja','Nein']),P('vermaechtnis','Vermächtnisse (optional)',false),P('hinweis','Hinweis: eigenhändig & beide unterschreiben',false)]},
    vtr_patientenverfuegung:{ic:'🏥',n:'Patientenverfügung',cat:'vertraege',tier:'einfach',q:[P('situation','Für welche Situationen',true),P('lebenserhaltung','Lebenserhaltende Maßnahmen',true,['Ablehnen','Im Zweifel durchführen']),P('schmerz','Schmerzbehandlung?',false,['Ja','Nein']),P('kuenstlich','Künstliche Ernährung/Beatmung',false,['Ablehnen','Erwünscht']),P('vertreter','Vertrauensperson (optional)',false),P('organspende','Organspende',false,['Ja','Nein','Keine Angabe'])]},
    vtr_betreuungsverfuegung:{ic:'🧭',n:'Betreuungsverfügung',cat:'vertraege',tier:'einfach',q:[P('betreuer','Gewünschte Betreuungsperson',true),P('ersatz','Ersatzperson (optional)',false),P('ausschluss','Auszuschließende Person (optional)',false),P('wuensche','Wünsche (Wohnort, Pflege)',false)]},
    vtr_sorgerechtsverfuegung:{ic:'👶',n:'Sorgerechtsverfügung',cat:'vertraege',tier:'einfach',q:[P('kinder','Kinder (Namen & Geburtsdaten)',true),P('vormund','Gewünschter Vormund',true),P('ersatz','Ersatzvormund (optional)',false),P('ausschluss','Auszuschließende Person (optional)',false),P('wuensche','Wünsche zur Erziehung',false)]},
    vtr_gbr:{ic:'🤝',n:'GbR-Gesellschaftsvertrag',cat:'vertraege',tier:'komplex',q:[P('name','Name der GbR',true),P('sitz','Sitz',true),P('zweck','Gesellschaftszweck',true),P('gesellschafter','Alle Gesellschafter (Namen & Anschriften)',true),P('einlagen','Einlagen je Gesellschafter',true),P('beteiligung','Gewinn-/Verlustverteilung',true),P('geschaeftsfuehrung','Geschäftsführung',false),P('beginn','Beginn (Datum)',true)]},
    vtr_gmbh_satzung:{ic:'🏛️',n:'GmbH-Gesellschaftsvertrag (Satzung)',cat:'vertraege',tier:'komplex',q:[P('firma','Firmenname der GmbH',true),P('sitz','Sitz',true),P('zweck','Unternehmensgegenstand',true),P('stammkapital','Stammkapital (€, mind. 25.000)',true),P('gesellschafter','Gesellschafter & Geschäftsanteile',true),P('geschaeftsfuehrer','Geschäftsführer',true),P('geschaeftsjahr','Geschäftsjahr',false),P('hinweis_notar','Hinweis: notariell zu beurkunden',false)]},
    vtr_ehevertrag:{ic:'💍',n:'Ehevertrag',cat:'vertraege',tier:'komplex',q:[P('partner','Name des Ehepartners',true),P('status','Zeitpunkt',true,['Vor der Ehe','Während der Ehe']),P('gueterstand','Güterstand',true,['Gütertrennung','Modifizierte Zugewinngemeinschaft','Gütergemeinschaft']),P('unterhalt','Unterhaltsregelungen (kurz)',false),P('versorgung','Versorgungsausgleich',false,['Beibehalten','Ausschließen']),P('vermoegen','Besondere Vermögenswerte (kurz)',false),P('hinweis_notar','Hinweis: notariell zu beurkunden',false)]}
  };
  for(var k in V){ if(typeof DOCS!=='undefined' && !DOCS[k]) DOCS[k]=V[k]; }
  if(typeof CATS!=='undefined'){ if(!CATS.vertraege) CATS.vertraege={n:'Verträge',ic:'📜',docs:[]}; for(var k in V){ if(CATS.vertraege.docs.indexOf(k)<0) CATS.vertraege.docs.push(k); } }
  if(typeof DOC_TIER!=='undefined'){ for(var k in V){ DOC_TIER[k]=V[k].tier||'standard'; } }
})();}catch(e){}
JS;
    $src = str_replace($anchor, $anchor . $js, $src);
    $rep[] = ['#1 Verträge inline (aynı scope)', 1];
}

/* ── 2) Soru textarea'ları auto-grow (estetik) ── */
if (strpos($src, 'ch-ta-grow') === false) {
    $grow = <<<'HTML'
<style id="ch-ta-grow-css">.fi textarea{overflow:hidden;resize:none;min-height:44px;transition:height .08s}</style>
<script id="ch-ta-grow">
(function(){
  function grow(t){ try{ t.style.height='auto'; t.style.height=Math.min(t.scrollHeight,320)+'px'; }catch(e){} }
  document.addEventListener('input',function(e){ var t=e.target; if(t&&t.tagName==='TEXTAREA'&&t.closest&&t.closest('.fi')) grow(t); },true);
  setInterval(function(){ document.querySelectorAll('.fi textarea').forEach(function(t){ if(!t._g){t._g=1; grow(t);} }); },800);
})();
</script>
HTML;
    $n2 = 0;
    $src = preg_replace('/<\/body>/', $grow . "\n</body>", $src, 1, $n2);
    $rep[] = ['#2 textarea auto-grow', $n2];
} else { $rep[] = ['#2 textarea (zaten ekli)', 0]; }

$changed = ($src !== $start);
if ($changed) file_put_contents($file, $src);
echo "ChatHelp — Verträge KESİN FIX raporu\n====================================\n\n";
foreach ($rep as [$l,$n]) echo ($n>0?"  ✓ ":"  ✗ ").$l."  →  $n\n";
echo "\n" . ($changed ? "DURUM: index.php güncellendi. ✅\n" : "DURUM: Değişiklik yok.\n");
echo "\nFiyat: einfach 1,99€ / standard 3,99€ / komplex 4,99€ (mevcut price ID'ler).\n";
echo "SONRA: opcache-reset.php. SİL: rm apply-vertraege-fix.php\n";
echo "Test: Kategoriler -> 📜 Verträge görünür; sözleşme seç; free kullanıcıda paywall doğru fiyat.\n";
