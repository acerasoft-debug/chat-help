<?php
/**
 * ChatHelp — ZORUNLU MUHATAP (KARSI TARAF) ALANI (dilekce formlarina)
 * ------------------------------------------------------------------------
 *  Kullanici: dilekcede hem yazanin hem KARSI TARAFIN tum bilgileri olmali;
 *  bunun icin muhatap ad/adres formda ZORUNLU sorulmali (AI tahminine birakma).
 *
 *  Cozum (CH_RECIPIENT): ana dilekce render'inde, butonun eklendigi noktada
 *  (`body.appendChild(btn);box.appendChild(body);`) forma en uste ZORUNLU bir
 *  "Muhatap: ad/kurum + tam adres" textarea'si enjekte edilir. Submit handler
 *  zaten [data-k]'leri toplayip [required]'lari dogruladigi icin alan otomatik
 *  hem toplanir (ans.ch_empfaenger) hem zorunlu olur. CASE DETAILS'e girer ->
 *  AI gercek muhatap bilgisini yazar (docquality-fix ile).
 *
 *  Sadece MUHATABI OLAN belgelere eklenir: CV/Bewerbung, iki-taraf sozlesme
 *  (vtr_/ehevertrag/mietvertrag...), vize/checklist, vasiyet/vekalet HARIC.
 *
 * KULLANIM: pull-updates.php?files=apply-recipient-field.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-recipient-field BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_RECIPIENT")!==false) exit("Zaten ekli (CH_RECIPIENT).\n");

$rep=[];

/* --- 1) yardimci fonksiyonlar (getDocPrice anchor) --- */
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,$anchor)===false){ echo "✗ getDocPrice anchor yok\n"; exit; }
$helpers=<<<'CHJS'

/* CH_RECIPIENT — zorunlu muhatap alani yardimcilari */
try{
window.chUIL=function(){ try{ var s=localStorage.getItem("ch_uilang"); if(s) return s; }catch(e){} try{ if(typeof UL!=="undefined"&&UL) return UL; }catch(e){} return "de"; };
window.chNeedRecipient=function(dk,doc){
  try{
    dk=(dk||"").toLowerCase();
    var cat=((doc&&doc.cat)||"").toLowerCase();
    var s=dk+" "+cat;
    /* muhatabi olmayan / iki-taraf sozlesme / CV / vize / vasiyet -> HAYIR */
    if(/(^|_)vtr_|(^|_)bew_|bewerbung|lebenslauf|anschreiben|motivation|basvuru|cv_|_cv|visa|vize|schengen|checklist|testament|patientenverf|vollmacht|vekalet|ehevertrag|mietvertrag|arbeitsvertrag|kaufvertrag|schenkung|nda|geheimhaltung|vertrag_/.test(s)) return false;
    return true;
  }catch(e){ return true; }
};
window.chRecipLabel=function(){
  var M={de:"Empfänger: Name/Behörde und vollständige Anschrift",tr:"Muhatap: Ad/Kurum ve tam adres",en:"Recipient: name/authority and full address",fr:"Destinataire : nom/administration et adresse complète",es:"Destinatario: nombre/organismo y dirección completa",it:"Destinatario: nome/ente e indirizzo completo",nl:"Ontvanger: naam/instantie en volledig adres",pl:"Odbiorca: nazwa/urząd i pełny adres",sv:"Mottagare: namn/myndighet och fullständig adress",pt:"Destinatário: nome/entidade e morada completa"};
  return M[window.chUIL()]||M.en;
};
window.chRecipPh=function(){
  var M={de:"z. B. Telekom Deutschland GmbH, Musterstr. 1, 12345 Stadt",tr:"örn. Vodafone A.Ş., Örnek Cad. 1, 34000 İstanbul",en:"e.g. British Telecom, 1 Example St, London",fr:"ex. Orange SA, 1 rue Exemple, 75000 Paris",es:"p. ej. Movistar, C/ Ejemplo 1, 28000 Madrid",it:"es. TIM, Via Esempio 1, 00100 Roma",nl:"bijv. KPN, Voorbeeldstraat 1, 1000 AA Stad",pl:"np. Orange, ul. Przykładowa 1, 00-001",sv:"t.ex. Telia, Exempelgatan 1, 100 00",pt:"ex. MEO, Rua Exemplo 1, 1000 Lisboa"};
  return M[window.chUIL()]||M.en;
};
}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$helpers,$src);
$rep[]=["yardimci fonksiyonlar",1];

/* --- 2) render'a enjeksiyon --- */
$a2="body.appendChild(btn);box.appendChild(body);";
$c2=substr_count($src,$a2);
if($c2!==1){ echo "✗ render anchor $c2 kez (beklenen 1) — DEGISIKLIK YOK.\n"; exit; }
$inject='/*CH_RECIPIENT*/try{if(typeof window.chNeedRecipient==="function"&&window.chNeedRecipient(dk,doc)&&!body.querySelector(\'[data-k="ch_empfaenger"]\')){var _rw=document.createElement("div");_rw.className="fi";_rw.innerHTML=\'<label>\'+window.chRecipLabel()+\' <span style="color:var(--rd)">*</span></label><textarea data-k="ch_empfaenger" rows="3" required placeholder="\'+window.chRecipPh()+\'"></textarea>\';var _ff=body.querySelector(".fi");if(_ff)body.insertBefore(_rw,_ff);else body.appendChild(_rw);}}catch(e){} ';
$src=str_replace($a2, $inject.$a2, $src);
$rep[]=["render muhatap enjeksiyonu",1];

/* --- lint + kaydet --- */
if($src===$start){ echo "Degisiklik yok!\n"; exit; }
$tmp=$file.".tmp-recip"; file_put_contents($tmp,$src);
$out=[]; $code=0; exec("php -l ".escapeshellarg($tmp)." 2>&1",$out,$code); @unlink($tmp);
if($code!==0){ echo "✗ LINT HATASI — DEGISTIRILMEDI:\n".implode("\n",$out)."\n"; exit; }
file_put_contents($file.".bak-recip-".date("Ymd-His"),$start);
file_put_contents($file,$src);

echo "✓ Zorunlu muhatap alani eklendi (CH_RECIPIENT):\n";
foreach($rep as $r) echo "  OK  ".$r[0]."\n";
echo "  Dilekce/itiraz/fesih/basvuru formlarinda en ustte zorunlu 'Muhatap: ad+adres'.\n";
echo "  CV/sozlesme/vize/vasiyet formlarinda YOK.\n";
echo "SIL: rm apply-recipient-field.php\n";
echo "Test: Kundigung Internetvertrag -> ustte 'Muhatap: Ad/Kurum ve tam adres *' zorunlu alan;\n";
echo "      doldurmadan uretilemez; doldurunca belgede karsi tarafin tam bilgisi yazili.\n";
echo "Test2: Bewerbung/CV veya vize checklist -> muhatap alani GORUNMEMELI.\n";
