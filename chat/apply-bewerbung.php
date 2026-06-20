<?php
/**
 * ChatHelp — "Bewerbung" kategorisi (iş + Wohnung başvuruları) — inline, scope-bağımsız
 * getDocPrice'tan sonra ana scope'a enjekte -> DOCS/CATS/DOC_TIER kesin erişim.
 * KULLANIM: chat-help.com/chat/apply-bewerbung.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src=file_get_contents($file); $start=$src;
file_put_contents($file . '.bak-bew-' . date('Ymd-His'), $src);

$anchor = "function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if (strpos($src,'CH_BEW_INLINE')!==false){ exit("Zaten ekli (CH_BEW_INLINE).\n"); }
if (strpos($src,$anchor)===false){ exit("✗ anchor (getDocPrice) bulunamadı.\n"); }

$js = <<<'JS'

/* CH_BEW_INLINE — Bewerbung (aynı scope) */
try{(function(){
  function P(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var V={
    bew_lebenslauf:{ic:'📋',n:'Lebenslauf',cat:'bewerbung',tier:'standard',q:[P('position','Angestrebte Position / Berufsziel',true),P('berufserfahrung','Berufserfahrung (Firma, Zeitraum, Aufgaben — je Zeile)',true),P('ausbildung','Ausbildung / Schulbildung (Abschluss, Ort, Jahr)',true),P('kenntnisse','Fachkenntnisse / Fähigkeiten',false),P('sprachen','Sprachen + Niveau',false),P('edv','EDV-/Software-Kenntnisse',false),P('interessen','Interessen / Ehrenamt (optional)',false)]},
    bew_anschreiben:{ic:'✉️',n:'Bewerbungsschreiben (Anschreiben)',cat:'bewerbung',tier:'standard',q:[P('firma','Firma & Anschrift',true),P('ansprechpartner','Ansprechpartner (falls bekannt)',false),P('position','Stelle, auf die Sie sich bewerben',true),P('quelle','Wo gefunden? (z.B. Stellenanzeige vom …)',false),P('staerken','Ihre wichtigsten Stärken/Qualifikationen',true),P('motivation','Warum diese Firma / diese Stelle?',true),P('eintritt','Möglicher Eintrittstermin',false),P('gehalt','Gehaltsvorstellung (optional)',false),P('stellenanzeige','Stellenanzeige-Text einfügen (optional, für passgenaues Anschreiben)',false)]},
    bew_motivation:{ic:'🎯',n:'Motivationsschreiben',cat:'bewerbung',tier:'einfach',q:[P('ziel','Wofür? (Studienplatz, Stipendium, Job …)',true),P('institution','Institution / Programm / Firma',true),P('motivation','Ihre Motivation',true),P('qualifikationen','Relevante Qualifikationen / Erfahrungen',true),P('ziele','Ihre Ziele',false)]},
    bew_initiativ:{ic:'📨',n:'Initiativbewerbung',cat:'bewerbung',tier:'einfach',q:[P('firma','Wunsch-Firma & Anschrift',true),P('bereich','Wunschbereich / Tätigkeit',true),P('staerken','Ihre Stärken',true),P('motivation','Warum diese Firma?',true),P('eintritt','Möglicher Eintrittstermin',false)]},
    bew_praktikum:{ic:'🎓',n:'Praktikumsbewerbung',cat:'bewerbung',tier:'einfach',q:[P('firma','Firma & Anschrift',true),P('art','Art',true,['Pflichtpraktikum','Freiwilliges Praktikum']),P('bereich','Gewünschter Bereich',true),P('zeitraum','Zeitraum (von – bis)',true),P('motivation','Motivation',true),P('ausbildung_stand','Aktueller Ausbildungsstand (Schule/Studium)',false)]},
    bew_ausbildung:{ic:'🔧',n:'Ausbildungsbewerbung',cat:'bewerbung',tier:'einfach',q:[P('firma','Ausbildungsbetrieb & Anschrift',true),P('beruf','Ausbildungsberuf',true),P('schulabschluss','Schulabschluss',true),P('beginn','Gewünschter Ausbildungsbeginn',true),P('staerken','Ihre Stärken / Interessen',true),P('motivation','Warum dieser Beruf / Betrieb?',true)]},
    bew_wohnung:{ic:'🏠',n:'Wohnungsbewerbung (Anschreiben)',cat:'bewerbung',tier:'einfach',q:[P('objekt','Wohnung (Adresse / Inserat)',true),P('vermieter','Vermieter / Makler',false),P('haushalt','Wer zieht ein? (Personen, Alter)',true),P('beruf_einkommen','Beruf & monatliches Nettoeinkommen',true),P('einzug','Gewünschtes Einzugsdatum',false),P('haustiere','Haustiere?',false,['Keine','Ja']),P('raucher','Raucher?',false,['Nein','Ja']),P('motivation','Warum sind Sie der ideale Mieter?',true)]},
    bew_selbstauskunft:{ic:'📝',n:'Mieterselbstauskunft',cat:'bewerbung',tier:'einfach',q:[P('objekt','Wohnung (Adresse)',true),P('personen','Mieter (Namen & Geburtsdaten)',true),P('beruf','Beruf & Arbeitgeber',true),P('einkommen','Monatliches Nettoeinkommen (€)',true),P('beschaeftigt_seit','Beschäftigt seit',false),P('anzahl','Anzahl einziehender Personen',true),P('schufa','SCHUFA-Auskunft vorhanden?',false,['Ja','Nein']),P('insolvenz','Laufende Insolvenz / eidesstattl. Versicherung?',false,['Nein','Ja']),P('haustiere','Haustiere?',false,['Keine','Ja']),P('einzug','Gewünschtes Einzugsdatum',false)]}
  };
  for(var k in V){ if(typeof DOCS!=='undefined' && !DOCS[k]) DOCS[k]=V[k]; }
  if(typeof CATS!=='undefined'){ if(!CATS.bewerbung) CATS.bewerbung={n:'Bewerbung',ic:'📄',docs:[]}; for(var k in V){ if(CATS.bewerbung.docs.indexOf(k)<0) CATS.bewerbung.docs.push(k); } }
  if(typeof DOC_TIER!=='undefined'){ for(var k in V){ DOC_TIER[k]=V[k].tier||'einfach'; } }
})();}catch(e){}
JS;

$src=str_replace($anchor, $anchor.$js, $src);
$changed=($src!==$start);
if($changed) file_put_contents($file,$src);
echo "ChatHelp — Bewerbung kategorisi raporu\n======================================\n\n";
echo ($changed?"  ✓ Bewerbung inline eklendi (8 belge)\n":"  ✗ değişiklik yok\n");
echo "\n".($changed?"DURUM: güncellendi. ✅\n":"\n");
echo "Fiyat: Lebenslauf/Anschreiben 3,99€ · diğerleri 1,99€ (plan'da bedava).\n";
echo "GEREKLİ: apply-bewerbung-backend.php de çalıştır (CV/Anschreiben formatı).\n";
echo "SONRA: opcache-reset.php. SİL: rm apply-bewerbung.php\n";
echo "Test: Kategoriler -> 📄 Bewerbung -> Lebenslauf/Anschreiben/Wohnungsbewerbung.\n";
