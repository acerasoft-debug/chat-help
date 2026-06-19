<?php
/**
 * ChatHelp — "Verträge" kategorisi + ~19 özgün sözleşme (additive)
 * ----------------------------------------------------------------
 * Mevcut DOCS/CATS literal'lerine DOKUNMAZ. </body> öncesine bir script ekler;
 * sayfa yüklenince DOCS'a sözleşmeleri, CATS.vertraege'ye listeyi merge eder.
 * Üretim mevcut hattan geçer (doGenerate -> Claude), yani gerçekten çalışır.
 * İçerik tamamen özgün (smartlaw'dan KOPYA DEĞİL; sadece tür isimleri).
 *
 * KULLANIM: chat-help.com/chat/apply-vertraege.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src   = file_get_contents($file);
$start = $src;
file_put_contents($file . '.bak-vtr-' . date('Ymd-His'), $src);

if (strpos($src, 'ch-vertraege') !== false) { exit("Zaten ekli (ch-vertraege).\n"); }

$bundle = <<<'HTML'
<script id="ch-vertraege">
(function(){
  'use strict';
  function P(k,l,r,opts){ var o={k:k,l:l,r:!!r}; if(opts)o.opts=opts; return o; }
  // ortak: karşı taraf
  var PARTNER = P('vertragspartner','Name & Anschrift der anderen Vertragspartei', true);

  var V = {
    /* ── Arbeit & Wirtschaft ── */
    vtr_arbeitsvertrag:{ic:'📄',n:'Arbeitsvertrag',cat:'vertraege',tier:'einfach',q:[
      P('rolle','Ihre Rolle',true,['Arbeitgeber','Arbeitnehmer']),
      P('vertragspartner','Name & Anschrift der anderen Partei',true),
      P('position','Stellenbezeichnung / Tätigkeit',true),
      P('beginn','Arbeitsbeginn (Datum)',true),
      P('befristung','Vertragsart',true,['Unbefristet','Befristet']),
      P('befristet_bis','Falls befristet: bis (Datum)',false),
      P('stunden','Wochenarbeitszeit (Stunden)',true),
      P('gehalt','Bruttogehalt monatlich (€)',true),
      P('urlaub','Urlaubstage pro Jahr',false),
      P('probezeit','Probezeit',false,['Keine','1 Monat','3 Monate','6 Monate'])
    ]},
    vtr_minijob:{ic:'🧾',n:'Minijob-Arbeitsvertrag',cat:'vertraege',tier:'einfach',q:[
      P('rolle','Ihre Rolle',true,['Arbeitgeber','Arbeitnehmer']),
      P('vertragspartner','Name & Anschrift der anderen Partei',true),
      P('taetigkeit','Tätigkeit',true),
      P('beginn','Beginn (Datum)',true),
      P('stunden','Stunden pro Woche/Monat',true),
      P('verguetung','Vergütung (€, max. 538 €/Monat)',true),
      P('urlaub','Urlaubsanspruch (Tage)',false)
    ]},
    vtr_freelancer:{ic:'💻',n:'Freier-Mitarbeiter-Vertrag',cat:'vertraege',tier:'einfach',q:[
      P('vertragspartner','Auftraggeber: Name & Anschrift',true),
      P('leistung','Leistung / Projektbeschreibung',true),
      P('beginn','Beginn (Datum)',true),
      P('dauer','Laufzeit / Projektende',false),
      P('verguetung','Honorar (€, Stunden-/Tagessatz oder Pauschal)',true),
      P('zahlung','Zahlungsweise',false,['Nach Rechnung','Monatlich','Nach Meilenstein']),
      P('rechte','Nutzungsrechte am Ergebnis übertragen?',false,['Ja','Nein'])
    ]},
    vtr_praktikum:{ic:'🎓',n:'Praktikumsvertrag',cat:'vertraege',tier:'einfach',q:[
      P('vertragspartner','Unternehmen: Name & Anschrift',true),
      P('art','Art des Praktikums',true,['Freiwillig','Pflichtpraktikum']),
      P('bereich','Einsatzbereich / Abteilung',true),
      P('beginn','Beginn (Datum)',true),
      P('ende','Ende (Datum)',true),
      P('stunden','Wochenstunden',false),
      P('verguetung','Vergütung (€/Monat, falls vorhanden)',false)
    ]},
    vtr_nda:{ic:'🔒',n:'Geheimhaltungsvereinbarung (NDA)',cat:'vertraege',tier:'einfach',q:[
      P('vertragspartner','Andere Partei: Name & Anschrift',true),
      P('zweck','Zweck / Anlass der Zusammenarbeit',true),
      P('richtung','Geheimhaltung',true,['Einseitig','Gegenseitig']),
      P('gegenstand','Vertrauliche Informationen (kurz)',true),
      P('dauer','Geheimhaltungsdauer (z.B. 3 Jahre)',false),
      P('vertragsstrafe','Vertragsstrafe vereinbaren?',false,['Ja','Nein'])
    ]},
    vtr_dienstleistung:{ic:'🤝',n:'Dienstleistungsvertrag',cat:'vertraege',tier:'einfach',q:[
      P('vertragspartner','Auftraggeber: Name & Anschrift',true),
      P('leistung','Beschreibung der Dienstleistung',true),
      P('beginn','Beginn (Datum)',true),
      P('verguetung','Vergütung (€)',true),
      P('zahlung','Zahlungsbedingungen',false),
      P('kuendigung','Kündigungsfrist',false)
    ]},
    vtr_werkvertrag:{ic:'🛠️',n:'Werkvertrag',cat:'vertraege',tier:'einfach',q:[
      P('vertragspartner','Auftraggeber: Name & Anschrift',true),
      P('werk','Zu erstellendes Werk / Gewerk',true),
      P('beginn','Beginn (Datum)',false),
      P('fertig','Fertigstellung bis (Datum)',true),
      P('verguetung','Werklohn (€)',true),
      P('abnahme','Abnahmebedingungen',false),
      P('gewaehrleistung','Gewährleistung (Monate)',false)
    ]},

    /* ── Miete & Immobilien ── */
    vtr_untermietvertrag:{ic:'🏠',n:'Untermietvertrag',cat:'vertraege',tier:'einfach',q:[
      P('vertragspartner','Untermieter: Name & Anschrift',true),
      P('objekt','Untervermietetes Objekt / Zimmer',true),
      P('adresse','Adresse der Wohnung',true),
      P('beginn','Mietbeginn (Datum)',true),
      P('befristung','Befristet?',false,['Unbefristet','Befristet bis']),
      P('miete','Miete monatlich (€, warm)',true),
      P('kaution','Kaution (€)',false),
      P('moebliert','Möbliert?',false,['Ja','Teilweise','Nein'])
    ]},
    vtr_gewerbemiete:{ic:'🏢',n:'Gewerbemietvertrag',cat:'vertraege',tier:'einfach',q:[
      P('rolle','Ihre Rolle',true,['Vermieter','Mieter']),
      P('vertragspartner','Andere Partei: Name & Anschrift',true),
      P('objekt','Gewerbeobjekt & Adresse',true),
      P('flaeche','Fläche (m²)',false),
      P('nutzung','Art der gewerblichen Nutzung',true),
      P('beginn','Mietbeginn (Datum)',true),
      P('miete','Nettokaltmiete monatlich (€)',true),
      P('nebenkosten','Nebenkosten (€)',false),
      P('laufzeit','Laufzeit / Kündigungsfrist',false)
    ]},
    vtr_stellplatz:{ic:'🅿️',n:'Stellplatz-/Garagenmietvertrag',cat:'vertraege',tier:'einfach',q:[
      P('vertragspartner','Mieter: Name & Anschrift',true),
      P('objekt','Stellplatz/Garage (Nr. & Adresse)',true),
      P('beginn','Mietbeginn (Datum)',true),
      P('miete','Miete monatlich (€)',true),
      P('kaution','Kaution (€)',false),
      P('kuendigung','Kündigungsfrist',false)
    ]},
    vtr_uebergabe:{ic:'📋',n:'Wohnungsübergabeprotokoll',cat:'vertraege',tier:'einfach',q:[
      P('vertragspartner','Andere Partei (Mieter/Vermieter)',true),
      P('adresse','Adresse der Wohnung',true),
      P('anlass','Anlass',true,['Einzug','Auszug']),
      P('datum','Übergabedatum (Datum)',true),
      P('zaehler','Zählerstände (Strom/Gas/Wasser)',false),
      P('schluessel','Anzahl übergebener Schlüssel',false),
      P('maengel','Festgestellte Mängel (kurz)',false)
    ]},

    /* ── Kauf & Verkauf ── */
    vtr_kaufvertrag_privat:{ic:'🛒',n:'Kaufvertrag (privat)',cat:'vertraege',tier:'einfach',q:[
      P('rolle','Ihre Rolle',true,['Verkäufer','Käufer']),
      P('vertragspartner','Andere Partei: Name & Anschrift',true),
      P('gegenstand','Kaufgegenstand (Beschreibung)',true),
      P('zustand','Zustand',false,['Neu','Gebraucht']),
      P('preis','Kaufpreis (€)',true),
      P('zahlung','Zahlungsweise',false,['Bar','Überweisung']),
      P('uebergabe','Übergabedatum (Datum)',false),
      P('gewaehr','Gewährleistung ausschließen? (privat üblich)',false,['Ja','Nein'])
    ]},
    vtr_tierkauf:{ic:'🐾',n:'Tierkaufvertrag',cat:'vertraege',tier:'einfach',q:[
      P('rolle','Ihre Rolle',true,['Verkäufer','Käufer']),
      P('vertragspartner','Andere Partei: Name & Anschrift',true),
      P('tier','Tierart, Rasse, Name, Geburtsdatum',true),
      P('kennzeichen','Chip-/Zuchtnummer',false),
      P('preis','Kaufpreis (€)',true),
      P('gesundheit','Gesundheitszustand / Impfungen',false),
      P('uebergabe','Übergabedatum (Datum)',false)
    ]},

    /* ── Geld & Finanzen ── */
    vtr_darlehen:{ic:'💶',n:'Darlehensvertrag (privat)',cat:'vertraege',tier:'einfach',q:[
      P('rolle','Ihre Rolle',true,['Darlehensgeber','Darlehensnehmer']),
      P('vertragspartner','Andere Partei: Name & Anschrift',true),
      P('betrag','Darlehenssumme (€)',true),
      P('zweck','Zweck des Darlehens',false),
      P('zinsen','Zinssatz (% p.a., 0 = zinslos)',false),
      P('auszahlung','Auszahlungsdatum (Datum)',true),
      P('rueckzahlung','Rückzahlung (Raten/auf einmal, Termin)',true)
    ]},
    vtr_schuldanerkenntnis:{ic:'✍️',n:'Schuldanerkenntnis',cat:'vertraege',tier:'einfach',q:[
      P('vertragspartner','Gläubiger: Name & Anschrift',true),
      P('betrag','Anerkannte Schuldsumme (€)',true),
      P('grund','Grund der Schuld',true),
      P('rueckzahlung','Rückzahlungsvereinbarung (Raten/Termin)',true),
      P('zinsen','Verzugszinsen vereinbart?',false,['Ja','Nein'])
    ]},
    vtr_quittung:{ic:'🧾',n:'Quittung',cat:'vertraege',tier:'einfach',q:[
      P('vertragspartner','Zahlender: Name',true),
      P('betrag','Betrag (€)',true),
      P('zweck','Wofür (Verwendungszweck)',true),
      P('datum','Datum (Datum)',true),
      P('zahlart','Zahlungsart',false,['Bar','Überweisung'])
    ]},

    /* ── Familie & Vorsorge ── */
    vtr_ehevertrag:{ic:'💍',n:'Ehevertrag',cat:'vertraege',tier:'einfach',q:[
      P('partner','Name des Ehepartners',true),
      P('status','Zeitpunkt',true,['Vor der Ehe','Während der Ehe']),
      P('gueterstand','Gewünschter Güterstand',true,['Gütertrennung','Modifizierte Zugewinngemeinschaft','Gütergemeinschaft']),
      P('unterhalt','Regelungen zum Unterhalt (kurz)',false),
      P('versorgung','Versorgungsausgleich',false,['Beibehalten','Ausschließen']),
      P('vermoegen','Besondere Vermögenswerte (kurz)',false),
      P('hinweis_notar','Hinweis: Ehevertrag ist notariell zu beurkunden',false)
    ]},
    vtr_schenkung:{ic:'🎁',n:'Schenkungsvertrag',cat:'vertraege',tier:'einfach',q:[
      P('rolle','Ihre Rolle',true,['Schenker','Beschenkter']),
      P('vertragspartner','Andere Partei: Name & Anschrift',true),
      P('gegenstand','Geschenk (Geld/Gegenstand, Beschreibung)',true),
      P('wert','Wert (€)',false),
      P('uebergabe','Übergabe-/Wirksamkeitsdatum (Datum)',false),
      P('auflagen','Auflagen oder Rückforderungsrecht?',false),
      P('hinweis_notar','Bei Immobilien: notarielle Beurkundung nötig',false)
    ]},
    vtr_patientenverfuegung:{ic:'🏥',n:'Patientenverfügung',cat:'vertraege',tier:'einfach',q:[
      P('situation','Für welche Situationen (z.B. unheilbare Krankheit, Endstadium)',true),
      P('lebenserhaltung','Lebenserhaltende Maßnahmen',true,['Ablehnen','Im Zweifel durchführen']),
      P('schmerz','Schmerz-/Palliativbehandlung gewünscht?',false,['Ja','Nein']),
      P('kuenstlich','Künstliche Ernährung/Beatmung',false,['Ablehnen','Erwünscht']),
      P('vertreter','Vertrauensperson (Name, optional)',false),
      P('organspende','Organspende',false,['Ja','Nein','Keine Angabe'])
    ]}
  };

  function add(){
    try{
      if(typeof DOCS==='undefined' || typeof CATS==='undefined') return false;
      var keys=Object.keys(V);
      keys.forEach(function(k){ if(!DOCS[k]) DOCS[k]=V[k]; });
      if(!CATS.vertraege) CATS.vertraege={n:'Verträge',ic:'📜',docs:[]};
      if(!CATS.vertraege.n) CATS.vertraege.n='Verträge';
      if(!CATS.vertraege.ic) CATS.vertraege.ic='📜';
      var ex=CATS.vertraege.docs||[];
      keys.forEach(function(k){ if(ex.indexOf(k)<0) ex.push(k); });
      CATS.vertraege.docs=ex;
      return true;
    }catch(e){ return false; }
  }
  // DOCS/CATS bu noktada tanımlı olmalı; değilse kısa süre dene
  if(!add()){ var n=0,t=setInterval(function(){ if(add()||++n>40) clearInterval(t); },150); }
})();
</script>
HTML;

$n = 0;
$src = preg_replace('/<\/body>/', $bundle . "\n</body>", $src, 1, $n);
$changed = ($src !== $start);
if ($changed) file_put_contents($file, $src);

echo "ChatHelp — Verträge kategorisi raporu\n=====================================\n\n";
echo ($n > 0 ? "  ✓ Verträge katmanı eklendi  →  $n\n" : "  ✗ </body> bulunamadı! HABER VER.\n");
echo "\n" . ($changed ? "DURUM: index.php güncellendi. ✅\n" : "DURUM: Değişiklik yok.\n");
echo "\nEklenen sözleşmeler: 19 (Arbeit, Miete, Kauf, Finanzen, Familie).\n";
echo "SONRA: opcache-reset.php. SİL: rm apply-vertraege.php\n";
echo "Test: Kategoriler -> 📜 Verträge -> bir sözleşme seç -> sorular -> erstellen.\n";
