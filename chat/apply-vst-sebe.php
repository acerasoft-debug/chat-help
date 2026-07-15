<?php
/**
 * ChatHelp — apply-vst-sebe (CH_VST_SEBE) — Vertrag Studio: SE + BE ulke setleri.
 *  (Eski apply-vst-x2.php taslagindan CH kismi CIKARILDI — CH zaten bu
 *  oturumda apply-atch-vertrag.php + apply-atch-vertrag2.php ile 9 sozlesmeye
 *  ulasti; ayni anchor zincirindeki __XSET_ADD merge-hook, vst2'nin CH
 *  girisini calisma-zamaninda sessizce ezerdi — gereksiz olurdu.)
 *  CH_VST_X'in (apply-vst-x.php) ayni XSETS/syncX mekanizmasina SE, BE
 *  eklenir — GB girisinin hemen ONUNE kardes anahtar olarak enjekte edilir.
 *  Her ulkeye 2 sozlesme: ayrilik/bosanma tipi anlasma + konut kira sozlesmesi,
 *  hepsinde "kendi maddeleriniz" adimi.
 *   SE: Bodelningsavtal (ÄktB 9-13 kap.) + Hyresavtal (Jordabalken 12 kap.)
 *   BE: Convention de divorce par consentement mutuel (loi 27.04.2007) +
 *       Bail de résidence principale (regionale wetgeving: Wallonie/Bxl/Vlaanderen)
 * KULLANIM: pull2.php?key=...&files=apply-vst-sebe.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-vst-sebe BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_VST_SEBE')!==false) exit("Zaten ekli (CH_VST_SEBE).\n");
if (strpos($src,'CH_VST_X')===false) exit("HATA: once CH_VST_X (apply-vst-x.php) gerekli.\n");

$fail=[];

/* ── [A] CT (form) girisleri: GB'nin hemen onune kardes anahtar ── */
$ctNew = <<<'JS'
SE:{
        "vtr_se_bodelning":{ic:"⚖️",n:"Bodelningsavtal",sn:"Äktenskapsbalken 9–13 kap. — avtal om bodelning vid äktenskapsskillnad",price:"4,99 €",pgs:5,steps:[
          {t:"Makar",f:[["m1","Make/maka 1 — fullständigt namn"],["m2","Make/maka 2 — fullständigt namn"],["vigseldatum","Datum för vigsel"],["ansokan_datum","Datum för ansökan om äktenskapsskillnad"]]},
          {t:"Barn",f:[["barn","Gemensamma barn (namn, födelsedatum; annars 'inga')","inga gemensamma barn","ta"],["betanketid","Betänketid krävs (gemensamma barn under 16 eller endast en part ansöker)?",true,["Ja, sex månaders betänketid","Nej, ej tillämpligt"]]]},
          {t:"Egendom",f:[["bostad","Bostad — vem övertar och på vilka villkor","","ta"],["losore","Lösöre och bohag — fördelning","","ta"],["konton","Bankkonton och sparande — fördelning","","ta"],["skulder","Skulder — vem svarar för vad","","ta"]]},
          {t:"Underskrift",f:[["ort","Ort"],["datum","Datum"]]},
          ucStep("Egna villkor (valfritt)","Tilläggsvillkor — rubrik","Tilläggsvillkor — text")]},
        "vtr_se_hyresavtal":{ic:"🏠",n:"Hyresavtal (bostad)",sn:"Jordabalken 12 kap. (hyreslagen)",price:"4,99 €",pgs:4,steps:[
          {t:"Parter",f:[["hyresvard","Hyresvärd (namn, adress)","","ta"],["hyresgast","Hyresgäst (namn)","","ta"],["lagenhet","Lägenhetens adress och lägenhetsnummer"]]},
          {t:"Villkor",f:[["tilltrade","Tillträdesdatum"],["hyrestid","Hyrestid","tillsvidare (obestämd tid)"],["hyra","Hyra per månad (kr)"],["uppsagning","Uppsägningstid","tre kalendermånader (12 kap. 4 § JB)"]]},
          {t:"Underskrift",f:[["ort","Ort"],["datum","Datum"]]},
          ucStep("Egna villkor (valfritt)","Tilläggsvillkor — rubrik","Tilläggsvillkor — text")]}
      },
      BE:{
        "vtr_be_divorce":{ic:"⚖️",n:"Convention de divorce par consentement mutuel",sn:"loi du 27 avril 2007 réformant le divorce — Code civil belge",price:"4,99 €",pgs:5,steps:[
          {t:"Époux",f:[["e1","Époux/Épouse 1 — nom complet"],["e2","Époux/Épouse 2 — nom complet"],["date_mariage","Date du mariage"]]},
          {t:"Enfants",f:[["enfants","Enfants (nom, date de naissance; sinon 'aucun')","aucun enfant mineur","ta"],["hebergement","Hébergement et autorité parentale","hébergement égalitaire; autorité parentale conjointe","ta"],["pension_enfants","Contribution alimentaire pour les enfants (€/mois)","sans objet"]]},
          {t:"Entre époux",f:[["pension_epoux","Pension alimentaire entre époux","les époux y renoncent réciproquement"],["logement","Logement familial"]]},
          {t:"Régime matrimonial",f:[["regime","Régime matrimonial","régime légal"],["liquidation","Liquidation et partage","opérés amiablement; plus aucune créance entre les parties","ta"]]},
          ucStep("Stipulations particulières (optionnel)","Clause — titre","Clause — texte")]},
        "vtr_be_bail":{ic:"🏠",n:"Bail de résidence principale",sn:"Législation régionale (Wallonie/Bruxelles/Flandre)",price:"4,99 €",pgs:4,steps:[
          {t:"Parties et logement",f:[["region","Région",true,["Wallonie","Bruxelles-Capitale","Flandre"]],["bailleur","Bailleur (nom, adresse)","","ta"],["preneur","Preneur (nom)","","ta"],["logement_adr","Adresse du logement"]]},
          {t:"Conditions",f:[["debut","Date de prise d'effet"],["duree","Durée","neuf ans (bail de courte durée possible)"],["loyer","Loyer mensuel (€)"],["charges","Charges","provisions avec décompte annuel"],["garantie","Garantie locative","maximum deux ou trois mois de loyer, sur compte bloqué"]]},
          {t:"Signature",f:[["ort","Fait à"],["datum","Le"]]},
          ucStep("Stipulations particulières (optionnel)","Clause — titre","Clause — texte")]}
      },
JS;
$anchA = '      GB:{';
$c=substr_count($src,$anchA);
if ($c!==1) { echo "  ✗ [A] XSETS anchor ($c)\n"; $fail[]='A'; }
else { $src=str_replace($anchA,$ctNew.$anchA,$src); echo "  ✓ [A] SE/BE form girisleri XSETS'e eklendi\n"; }

/* ── [B] NCX (belge metni) girisleri: GB'nin hemen onune ── */
$ncxNew = <<<'JS'
NCX["vtr_se_bodelning"]={n:"BODELNINGSAVTAL",sn:"Äktenskapsbalken 9–13 kap.",A:"Make/Maka 1",B:"Make/Maka 2",cl:[
        {no:"§ 1",t:"Parter och grund",b:function(d){return V(d.m1)+" och "+V(d.m2)+", gifta "+V(d.vigseldatum)+", ansökte om äktenskapsskillnad "+V(d.ansokan_datum)+" och ingår genom detta avtal bodelning enligt Äktenskapsbalken.";}},
        {no:"§ 2",t:"Barn och betänketid",b:function(d){return "Gemensamma barn: "+V(d.barn,"inga")+". "+(d.betanketid?String(d.betanketid):"Betänketid: ej tillämpligt")+".";}},
        {no:"§ 3",t:"Egendom",b:function(d){return "Bostad: "+V(d.bostad)+" Lösöre och bohag: "+V(d.losore)+" Bankkonton: "+V(d.konton)+" Skulder: "+V(d.skulder)+"";}},
        {no:"§ 4",t:"Slutgiltig reglering",b:function(){return "Parterna förklarar att bodelningen är slutgiltig och att inga ytterligare krav föreligger mellan dem med anledning av äktenskapet.";}},
        {no:"§ 5",t:"Egna villkor",b:function(d){return ekX(d,"Inga ytterligare särskilda villkor föreligger.");}}]};
      NCX["vtr_se_hyresavtal"]={n:"HYRESAVTAL",sn:"Jordabalken 12 kap.",A:"Hyresvärd",B:"Hyresgäst",cl:[
        {no:"§ 1",t:"Parter och lägenhet",b:function(d){return "Hyresvärd "+V(d.hyresvard)+" och hyresgäst "+V(d.hyresgast)+" avtalar om uthyrning av lägenheten på "+V(d.lagenhet)+".";}},
        {no:"§ 2",t:"Hyrestid och tillträde",b:function(d){return "Tillträde "+V(d.tilltrade)+". Hyrestid: "+V(d.hyrestid)+". Uppsägningstid: "+V(d.uppsagning)+".";}},
        {no:"§ 3",t:"Hyra",b:function(d){return "Hyra: "+V(d.hyra)+" kr per månad, betalas i förskott senast sista vardagen före varje kalendermånads början.";}},
        {no:"§ 4",t:"Egna villkor",b:function(d){return ekX(d,"Inga ytterligare särskilda villkor föreligger.");}}]};
      NCX["vtr_be_divorce"]={n:"CONVENTION DE DIVORCE PAR CONSENTEMENT MUTUEL",sn:"loi du 27 avril 2007 — Code civil belge",A:"Époux 1",B:"Époux 2",cl:[
        {no:"Article 1",t:"Parties",b:function(d){return V(d.e1)+" et "+V(d.e2)+", mariés le "+V(d.date_mariage)+", conviennent de divorcer par consentement mutuel conformément à la loi du 27 avril 2007.";}},
        {no:"Article 2",t:"Enfants",b:function(d){return "Enfants: "+V(d.enfants,"aucun enfant mineur")+". "+V(d.hebergement)+" Contribution alimentaire: "+V(d.pension_enfants)+".";}},
        {no:"Article 3",t:"Relations entre époux",b:function(d){return "Pension alimentaire entre époux: "+V(d.pension_epoux)+" Logement familial: "+V(d.logement)+"";}},
        {no:"Article 4",t:"Régime matrimonial",b:function(d){return "Régime: "+V(d.regime)+". Liquidation: "+V(d.liquidation)+"";}},
        {no:"Article 5",t:"Stipulations particulières",b:function(d){return ekX(d,"Aucune autre stipulation particulière.");}}]};
      NCX["vtr_be_bail"]={n:"BAIL DE RÉSIDENCE PRINCIPALE",sn:"Législation régionale belge",A:"Bailleur",B:"Preneur",cl:[
        {no:"Article 1",t:"Parties et logement",b:function(d){return "Bailleur "+V(d.bailleur)+" et preneur "+V(d.preneur)+" concluent, sous le régime applicable en "+V(d.region,"Région non précisée")+", un bail relatif au logement sis "+V(d.logement_adr)+", affecté à la résidence principale du preneur.";}},
        {no:"Article 2",t:"Durée",b:function(d){return "Le bail prend cours le "+V(d.debut)+" pour une durée de "+V(d.duree)+".";}},
        {no:"Article 3",t:"Loyer et garantie",b:function(d){return "Loyer mensuel: "+V(d.loyer)+" €. Charges: "+V(d.charges)+". Garantie locative: "+V(d.garantie)+".";}},
        {no:"Article 4",t:"Stipulations particulières",b:function(d){return ekX(d,"Aucune autre stipulation particulière.");}}]};
      NCX["vtr_gb_separation"]={
JS;
$anchB = 'NCX["vtr_gb_separation"]={';
$c=substr_count($src,$anchB);
if ($c!==1) { echo "  ✗ [B] NCX anchor ($c)\n"; $fail[]='B'; }
else { $src=str_replace($anchB,$ncxNew,$src); echo "  ✓ [B] SE/BE belge metinleri (NCX) eklendi\n"; }

/* ── [C] gorunurluk CSS: SE/BE'de Vertrag Studio acilsin ── */
if (!$fail) {
    $css = "\n<style id=\"ch-vst-sebe-css\">\n/* CH_VST_SEBE — SE/BE hukukunda Vertrag Studio gorunur */\n";
    foreach (['SE','BE'] as $cc) {
        $css .= "body.ch-cc-nonde[data-chcc=\"$cc\"] #nav-vst{display:flex !important}\n"
              . "body.ch-cc-nonde[data-chcc=\"$cc\"] #ch-pill-vst{display:inline-flex !important}\n"
              . "body.ch-cc-nonde[data-chcc=\"$cc\"] .vst-card:has([data-tg=\"vst\"]){display:block !important}\n";
    }
    $css .= "</style>";
    $pos = strrpos($src,'</body>');
    if ($pos===false) exit("HATA: </body> yok.\n");
    $src = substr($src,0,$pos).$css."\n".substr($src,$pos);
    echo "  ✓ [C] SE/BE'de modul gorunurlugu acildi\n";
}

if ($fail) { echo "\nHATA: eslesmeyen adimlar: ".implode(', ',$fail)." — index DEGISTIRILMEDI.\n"; exit; }

$src .= "\n<!-- CH_VST_SEBE -->\n";

$tmp = tempnam(sys_get_temp_dir(),'sb').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
$bk=@file_put_contents($file.'.bak-vstsebe-'.date('Ymd-His'), @file_get_contents($file));
if ($bk===false) echo "  ⚠ yedek yazilamadi — devam\n";
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI: ".var_export($w,true)."\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_VST_SEBE')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_VST_SEBE diskte (".strlen($chk)." bayt)\n";
echo "\n✓ CH_VST_SEBE uygulandi: SE'de 2 (bodelning + hyresavtal), BE'de 2 (divorce + bail),\n";
echo "   hepsinde 'kendi maddeleriniz' adimiyla.\n";
