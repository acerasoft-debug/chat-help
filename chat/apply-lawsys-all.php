<?php
/**
 * ChatHelp — TÜM ÜLKELER İÇİN ARKA UÇ HUKUK SİSTEMİ + BELGE DİLİ (api.php)
 * --------------------------------------------------------------------------
 *  1) lawSystems: ES/CH/GB/US/IT/NL/BE/AT/PL/SE/PT (+ eksikse TR) uzman tanımları
 *     — FR satırının hemen ardına, ülke başına tekil kontrolle (çift ekleme yok)
 *  2) $__ll belge dili eşlemesi: tüm ülkeler (TR/GB/US/PL/SE/PT dahil) tek seferde
 *     — mevcut dizi literal'i regex ile komple değiştirilir (TR'li/TR'siz her durumda çalışır)
 *  3) $__lln dil adı tablosu: en/pl/sv/pt/tr eklenir
 *  Güvenli: php -l temiz değilse api.php'ye YAZILMAZ.
 *
 * KULLANIM: pull-updates.php?files=apply-lawsys-all.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-lawsys-all BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file=__DIR__."/api.php";
if(!file_exists($file)) exit("api.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_ALL_LAWSYS")!==false) exit("Zaten ekli (CH_ALL_LAWSYS).\n");

$changed=0;

/* ---- 1) lawSystems — FR satırına dayalı blok ekleme, ülke başına tekil kontrol ---- */
$frAnchor = "'FR' => \"Expert avocat français. Appliquer le droit français: Code civil, Code du travail, Code pénal. Citer les articles.\",";
$LAW = [
  'TR' => "Türk hukuku uzmanı avukat. Türk hukukunu uygula: Türk Borçlar Kanunu, İş Kanunu, Tüketici Kanunu, Türk Medeni Kanunu, Türk Ceza Kanunu. İlgili madde numaralarını belirt.",
  'ES' => "Experto abogado español. Aplicar el derecho español: Código Civil, Estatuto de los Trabajadores, LAU, Ley de Consumidores. Citar los artículos.",
  'CH' => "Erfahrener Schweizer Jurist. Schweizer Recht anwenden: OR, ZGB, ArG, SchKG, KVG. Artikel zitieren.",
  'GB' => "Expert solicitor for England & Wales. Apply the law of England and Wales: Consumer Rights Act 2015, Housing Act 1988, Employment Rights Act 1996. Cite statutes.",
  'US' => "Experienced US attorney. Apply US law with state-specific awareness (the user's state is provided): landlord-tenant law, FDCPA, FCRA, at-will employment. Cite statutes and add state-specific caveats.",
  'IT' => "Esperto avvocato italiano. Applicare il diritto italiano: Codice Civile, Codice del Consumo, Statuto dei Lavoratori. Citare gli articoli.",
  'NL' => "Ervaren Nederlandse jurist. Nederlands recht toepassen: Burgerlijk Wetboek, huurrecht, arbeidsrecht, consumentenrecht. Artikelen citeren.",
  'BE' => "Expert juriste belge. Appliquer le droit belge: Code civil belge, bail 3-6-9, droit du travail belge, protection du consommateur. Citer les articles.",
  'AT' => "Erfahrener österreichischer Jurist. Österreichisches Recht anwenden: ABGB, MRG, KSchG, FAGG, AngG. Paragrafen zitieren.",
  'PL' => "Doświadczony polski prawnik. Stosować prawo polskie: Kodeks cywilny, Kodeks pracy, prawa konsumenta. Cytować artykuły.",
  'SE' => "Erfaren svensk jurist. Tillämpa svensk rätt: Jordabalken, LAS, Konsumentköplagen, Avtalslagen. Ange lagrum.",
  'PT' => "Advogado português experiente. Aplicar o direito português: Código Civil, Código do Trabalho, NRAU, direitos do consumidor. Citar os artigos.",
];
if(strpos($src,$frAnchor)===false){
  echo "✗ lawSystems FR anchor bulunamadi — lawSystems EKLENEMEDI.\n";
} else {
  $block="";
  $added=[]; $skipped=[];
  foreach($LAW as $cc=>$txt){
    if(strpos($src,"'$cc' => \"")!==false && preg_match("~'".$cc."' => \"[^\"]{20,}~",$src)){ $skipped[]=$cc; continue; }
    $block .= "\n        '$cc' => \"".$txt."\",";
    $added[]=$cc;
  }
  if($block!==""){
    $block .= " /*CH_ALL_LAWSYS*/";
    $src=str_replace($frAnchor,$frAnchor.$block,$src);
    $changed++;
    echo "✓ lawSystems eklendi: ".implode(", ",$added).($skipped?("  (zaten vardi: ".implode(", ",$skipped).")"):"")."\n";
  } else {
    echo "• lawSystems: tum ulkeler zaten tanimli.\n";
  }
}

/* ---- 2) $__ll belge dili eslemesi — dizi literal'i komple degistir ---- */
$newLL = "\$__ll=['DE'=>'de','AT'=>'de','CH'=>'de','FR'=>'fr','BE'=>'fr','LU'=>'fr','IT'=>'it','ES'=>'es','NL'=>'nl','TR'=>'tr','GB'=>'en','US'=>'en','PL'=>'pl','SE'=>'sv','PT'=>'pt']/*CH_ALL_DOCLANG*/";
if(strpos($src,"CH_ALL_DOCLANG")!==false){
  echo "• Belge dili eslemesi zaten guncel.\n";
} elseif(preg_match('~\$__ll=\[[^\]]*\]~',$src)){
  $src=preg_replace('~\$__ll=\[[^\]]*\]~',$newLL,$src,1);
  $changed++;
  echo "✓ Belge dili eslemesi genisletildi (TR/GB/US/PL/SE/PT dahil 15 ulke).\n";
} else {
  echo "✗ \$__ll dizisi bulunamadi (apply-france-backend hic uygulanmamis olabilir) — belge dili eslemesi EKLENEMEDI.\n";
}

/* ---- 3) $__lln dil adi tablosu ---- */
$newLLN = "\$__lln=['de'=>'DEUTSCH','fr'=>'FRANZÖSISCH','it'=>'ITALIENISCH','es'=>'SPANISCH','nl'=>'NIEDERLÄNDISCH','tr'=>'TÜRKISCH','en'=>'ENGLISCH','pl'=>'POLNISCH','sv'=>'SCHWEDISCH','pt'=>'PORTUGIESISCH']/*CH_ALL_LLN*/";
if(strpos($src,"CH_ALL_LLN")!==false){
  echo "• Dil adi tablosu zaten guncel.\n";
} elseif(preg_match('~\$__lln=\[[^\]]*\]~',$src)){
  $src=preg_replace('~\$__lln=\[[^\]]*\]~',$newLLN,$src,1);
  $changed++;
  echo "✓ Dil adi tablosu genisletildi (tr/en/pl/sv/pt).\n";
} else {
  echo "• \$__lln tablosu yok — atlandi (kritik degil).\n";
}

if($changed===0) exit("\nHicbir degisiklik uygulanmadi. api.php dokunulmadi.\n");

/* Guvenlik: gecici dosyada php -l */
$tmp=$file.".tmp-all"; file_put_contents($tmp,$src);
$out=[]; $code=0; exec("php -l ".escapeshellarg($tmp)." 2>&1",$out,$code); @unlink($tmp);
if($code!==0){ echo "\n✗ LINT HATASI — api.php DEGISTIRILMEDI (guvenli):\n".implode("\n",$out)."\n"; exit; }

file_put_contents($file.".bak-lawall-".date("Ymd-His"),$start);
file_put_contents($file,$src);
echo "\nLINT temiz ✓ — api.php guncellendi. Yedek: api.php.bak-lawall-*\n";
echo "SIL: rm apply-lawsys-all.php\n";
echo "Test: ES/GB/US/IT/NL/BE/AT/PL/SE/PT/CH sec -> belge uret -> o ulkenin hukuku + dilinde olmali.\n";
