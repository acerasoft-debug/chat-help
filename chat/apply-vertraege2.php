<?php
/**
 * ChatHelp — Verträge GENİŞLETME: +15 sözleşme (additive, bağımsız blok)
 * v1 (apply-vertraege) çalıştırılmış olsun olmasın çalışır.
 * KULLANIM: chat-help.com/chat/apply-vertraege2.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src   = file_get_contents($file);
$start = $src;
file_put_contents($file . '.bak-vtr2-' . date('Ymd-His'), $src);

if (strpos($src, 'ch-vertraege2') !== false) { exit("Zaten ekli (ch-vertraege2).\n"); }

$bundle = <<<'HTML'
<script id="ch-vertraege2">
(function(){
  'use strict';
  function P(k,l,r,opts){ var o={k:k,l:l,r:!!r}; if(opts)o.opts=opts; return o; }
  var V = {
    /* ── Gesellschaft & Wirtschaft ── */
    vtr_gbr:{ic:'🤝',n:'GbR-Gesellschaftsvertrag',cat:'vertraege',tier:'einfach',q:[
      P('name','Name der GbR',true),
      P('sitz','Sitz der Gesellschaft',true),
      P('zweck','Gesellschaftszweck',true),
      P('gesellschafter','Alle Gesellschafter (Namen & Anschriften)',true),
      P('einlagen','Einlagen je Gesellschafter',true),
      P('beteiligung','Gewinn-/Verlustverteilung',true),
      P('geschaeftsfuehrung','Geschäftsführung/Vertretung',false),
      P('beginn','Beginn (Datum)',true)
    ]},
    vtr_gmbh_satzung:{ic:'🏛️',n:'GmbH-Gesellschaftsvertrag (Satzung)',cat:'vertraege',tier:'einfach',q:[
      P('firma','Firmenname der GmbH',true),
      P('sitz','Sitz',true),
      P('zweck','Unternehmensgegenstand',true),
      P('stammkapital','Stammkapital (€, mind. 25.000)',true),
      P('gesellschafter','Gesellschafter & Geschäftsanteile',true),
      P('geschaeftsfuehrer','Geschäftsführer',true),
      P('geschaeftsjahr','Geschäftsjahr',false),
      P('hinweis_notar','Hinweis: GmbH-Satzung ist notariell zu beurkunden',false)
    ]},
    vtr_beratervertrag:{ic:'💼',n:'Beratervertrag',cat:'vertraege',tier:'einfach',q:[
      P('vertragspartner','Auftraggeber: Name & Anschrift',true),
      P('gegenstand','Beratungsgegenstand',true),
      P('beginn','Beginn (Datum)',true),
      P('umfang','Umfang (Stunden/Tage)',false),
      P('honorar','Honorar (€, Satz oder Pauschale)',true),
      P('zahlung','Zahlungsbedingungen',false),
      P('verschwiegenheit','Verschwiegenheit vereinbaren?',false,['Ja','Nein']),
      P('laufzeit','Laufzeit / Kündigungsfrist',false)
    ]},
    vtr_aufhebung_arbeit:{ic:'🤝',n:'Aufhebungsvertrag (Arbeit)',cat:'vertraege',tier:'einfach',q:[
      P('rolle','Ihre Rolle',true,['Arbeitgeber','Arbeitnehmer']),
      P('vertragspartner','Andere Partei: Name & Anschrift',true),
      P('position','Position des Arbeitnehmers',true),
      P('enddatum','Beendigung zum (Datum)',true),
      P('abfindung','Abfindung (€, optional)',false),
      P('resturlaub','Resturlaub / Überstunden',false),
      P('freistellung','Freistellung bis Vertragsende?',false,['Ja','Nein']),
      P('zeugnis','Qualifiziertes Zeugnis (gut/sehr gut)?',false,['Ja','Nein'])
    ]},
    vtr_lizenzvertrag:{ic:'📝',n:'Lizenzvertrag',cat:'vertraege',tier:'einfach',q:[
      P('rolle','Ihre Rolle',true,['Lizenzgeber','Lizenznehmer']),
      P('vertragspartner','Andere Partei: Name & Anschrift',true),
      P('gegenstand','Lizenzgegenstand (Software/Marke/Werk)',true),
      P('umfang','Umfang',true,['Einfache Lizenz','Exklusive Lizenz']),
      P('gebiet','Gebiet / Reichweite',false),
      P('dauer','Laufzeit',false),
      P('verguetung','Lizenzgebühr (€)',true),
      P('nutzung','Erlaubte Nutzungsarten',false)
    ]},
    vtr_kooperationsvertrag:{ic:'🔗',n:'Kooperationsvertrag',cat:'vertraege',tier:'einfach',q:[
      P('vertragspartner','Kooperationspartner: Name & Anschrift',true),
      P('ziel','Ziel der Kooperation',true),
      P('leistungen','Beiträge/Leistungen beider Seiten',true),
      P('beginn','Beginn (Datum)',true),
      P('dauer','Laufzeit',false),
      P('kosten','Kosten-/Erlösverteilung',false),
      P('verschwiegenheit','Vertraulichkeit?',false,['Ja','Nein'])
    ]},

    /* ── Miete & Immobilien ── */
    vtr_mietvertrag_wohnung:{ic:'🏠',n:'Mietvertrag Wohnung (vollständig)',cat:'vertraege',tier:'einfach',q:[
      P('rolle','Ihre Rolle',true,['Vermieter','Mieter']),
      P('vertragspartner','Andere Partei: Name & Anschrift',true),
      P('adresse','Adresse der Wohnung',true),
      P('groesse','Wohnfläche (m²) & Zimmerzahl',true),
      P('beginn','Mietbeginn (Datum)',true),
      P('befristung','Mietdauer',false,['Unbefristet','Befristet']),
      P('kaltmiete','Kaltmiete monatlich (€)',true),
      P('nebenkosten','Nebenkostenvorauszahlung (€)',false),
      P('kaution','Kaution (€, max. 3 Kaltmieten)',false),
      P('haustiere','Haustiere',false,['Erlaubt','Nach Absprache','Nicht erlaubt']),
      P('schoenheit','Schönheitsreparaturen durch Mieter?',false,['Ja','Nein'])
    ]},
    vtr_wg_vertrag:{ic:'🛏️',n:'WG-Mitbewohnervertrag',cat:'vertraege',tier:'einfach',q:[
      P('vertragspartner','Hauptmieter: Name & Anschrift',true),
      P('adresse','Adresse der WG',true),
      P('zimmer','Zimmer (Nr./Größe)',true),
      P('beginn','Beginn (Datum)',true),
      P('miete','Zimmermiete monatlich (€, warm)',true),
      P('kaution','Kaution (€)',false),
      P('gemeinschaft','Mitbenutzung (Küche/Bad/etc.)',false),
      P('regeln','Hausregeln (kurz)',false)
    ]},
    vtr_pacht:{ic:'🌾',n:'Pachtvertrag',cat:'vertraege',tier:'einfach',q:[
      P('rolle','Ihre Rolle',true,['Verpächter','Pächter']),
      P('vertragspartner','Andere Partei: Name & Anschrift',true),
      P('gegenstand','Pachtgegenstand (z.B. Grundstück, Lokal)',true),
      P('nutzung','Art der Nutzung',true),
      P('beginn','Pachtbeginn (Datum)',true),
      P('laufzeit','Laufzeit',false),
      P('pachtzins','Pachtzins (€)',true),
      P('instandhaltung','Instandhaltungspflichten',false)
    ]},
    vtr_mietaufhebung:{ic:'📄',n:'Mietaufhebungsvertrag',cat:'vertraege',tier:'einfach',q:[
      P('rolle','Ihre Rolle',true,['Vermieter','Mieter']),
      P('vertragspartner','Andere Partei: Name & Anschrift',true),
      P('adresse','Adresse der Wohnung',true),
      P('enddatum','Mietverhältnis endet am (Datum)',true),
      P('uebergabe','Wohnungsübergabe am (Datum)',false),
      P('kaution_rueck','Kautionsrückzahlung (Frist)',false),
      P('abgeltung','Sonstige Abgeltungen/Vereinbarungen',false)
    ]},
    vtr_leihvertrag:{ic:'📦',n:'Leihvertrag (unentgeltlich)',cat:'vertraege',tier:'einfach',q:[
      P('vertragspartner','Entleiher: Name & Anschrift',true),
      P('gegenstand','Leihgegenstand (Beschreibung)',true),
      P('beginn','Überlassung ab (Datum)',true),
      P('rueckgabe','Rückgabe bis (Datum)',true),
      P('zustand','Zustand bei Übergabe',false),
      P('bedingungen','Besondere Bedingungen',false)
    ]},

    /* ── Familie & Vorsorge ── */
    vtr_berliner_testament:{ic:'📜',n:'Berliner Testament (gemeinschaftlich)',cat:'vertraege',tier:'einfach',q:[
      P('ehepartner','Name des Ehe-/Lebenspartners',true),
      P('schlusserben','Schlusserben (z.B. Kinder, Namen)',true),
      P('pflichtteil','Pflichtteilsstrafklausel aufnehmen?',false,['Ja','Nein']),
      P('wiederheirat','Wiederverheiratungsklausel?',false,['Ja','Nein']),
      P('vermaechtnis','Besondere Vermächtnisse (optional)',false),
      P('hinweis','Hinweis: eigenhändig & von beiden unterschrieben',false)
    ]},
    vtr_betreuungsverfuegung:{ic:'🧭',n:'Betreuungsverfügung',cat:'vertraege',tier:'einfach',q:[
      P('betreuer','Gewünschte Betreuungsperson',true),
      P('ersatz','Ersatzperson (optional)',false),
      P('ausschluss','Auszuschließende Person (optional)',false),
      P('wuensche','Wünsche (Wohnort, Pflege, Behandlung)',false)
    ]},
    vtr_sorgerechtsverfuegung:{ic:'👶',n:'Sorgerechtsverfügung',cat:'vertraege',tier:'einfach',q:[
      P('kinder','Kinder (Namen & Geburtsdaten)',true),
      P('vormund','Gewünschter Vormund',true),
      P('ersatz','Ersatzvormund (optional)',false),
      P('ausschluss','Auszuschließende Person (optional)',false),
      P('wuensche','Wünsche zur Erziehung',false)
    ]},
    vtr_unterhaltsvereinbarung:{ic:'💶',n:'Unterhaltsvereinbarung',cat:'vertraege',tier:'einfach',q:[
      P('vertragspartner','Andere Partei: Name & Anschrift',true),
      P('betrifft','Unterhalt für',true,['Kind','Ehegatte/Trennung','Sonstige']),
      P('berechtigter','Name des/der Berechtigten',true),
      P('betrag','Monatlicher Unterhalt (€)',true),
      P('beginn','Zahlung ab (Datum)',true),
      P('anpassung','Dynamisierung (Anpassung)?',false,['Ja','Nein']),
      P('zahlweise','Zahlweise',false)
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
  if(!add()){ var n=0,t=setInterval(function(){ if(add()||++n>40) clearInterval(t); },150); }
})();
</script>
HTML;

$n = 0;
$src = preg_replace('/<\/body>/', $bundle . "\n</body>", $src, 1, $n);
$changed = ($src !== $start);
if ($changed) file_put_contents($file, $src);

echo "ChatHelp — Verträge +15 raporu\n==============================\n\n";
echo ($n > 0 ? "  ✓ Verträge2 katmanı eklendi  →  $n\n" : "  ✗ </body> bulunamadı! HABER VER.\n");
echo "\n" . ($changed ? "DURUM: index.php güncellendi. ✅\n" : "DURUM: Değişiklik yok.\n");
echo "\nToplam Verträge: v1 (19) + v2 (15) = 34 sözleşme.\n";
echo "SONRA: opcache-reset.php. SİL: rm apply-vertraege2.php\n";
