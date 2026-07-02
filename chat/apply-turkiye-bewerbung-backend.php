<?php
/**
 * ChatHelp — TR başvuru belgeleri arka ucu: trbew_ için kariyer danışmanı modu + DİL KİLİDİ
 * -------------------------------------------------------------------------------------------
 *  trbew_cv_de -> belge tamamen Almanca; trbew_cv_en -> İngilizce; diğerleri Türkçe
 *  (soru olarak dil seçilen belgelerde kullanıcının seçtiği dile uyar).
 *  Almanca Bewerbung arka ucuyla (ch_bew_doc) çakışmaz: 'trbew_' != 'bew_' öneki.
 *  Güvenli: php -l temiz değilse api.php'ye yazılmaz.
 *
 * KULLANIM: pull-updates.php?files=apply-turkiye-bewerbung-backend.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-turkiye-bewerbung-backend BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/api.php";
if(!file_exists($file)) exit("api.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"ch_trbew_doc")!==false) exit("Zaten ekli (ch_trbew_doc).\n");

$anchor = '$sysLaw = $lawSystems[$country] ?? $lawSystems[\'default\'];';
if(strpos($src,$anchor)===false) exit("anchor (sysLaw) bulunamadi\n");

$inject = $anchor . "\n"
  . "    if (strpos(\$docType, 'trbew_') === 0) { /*ch_trbew_doc*/\n"
  . "        \$__bl = 'TÜRKÇE';\n"
  . "        if (substr(\$docType, -3) === '_de') \$__bl = 'ALMANCA (Deutsch)';\n"
  . "        elseif (substr(\$docType, -3) === '_en') \$__bl = 'İNGİLİZCE (English)';\n"
  . "        \$sysLaw = 'Sen birinci sınıf bir uluslararası kariyer ve CV danışmanısın. İşe alımcıyı ikna eden, güncel standartlarda profesyonel başvuru belgeleri hazırlarsın. Yasal madde/paragraf KULLANMA; sadece temiz, doğrudan kullanılabilir metin üret. BELGE DİLİ: ' . \$__bl . '. Belgeyi TAMAMEN bu dilde yaz; kullanıcının Türkçe girdilerini gerekiyorsa bu dile profesyonelce çevir. Sorular arasında ayrıca bir dil tercihi belirtilmişse ONA uy.';\n"
  . "        if (strpos(\$docType, '_cv') !== false) \$sysLaw .= ' Modern, geriye dönük kronolojik, net bölümlenmiş bir CV oluştur (Kişisel Bilgiler, İş Deneyimi, Eğitim, Beceriler, Diller, Sertifikalar). Hedef ülkenin CV geleneklerine uy (Almanya: tabellarischer Lebenslauf; İngilizce: özlü resume/CV).';\n"
  . "        elseif (strpos(\$docType, 'linkedin') !== false) \$sysLaw .= ' Etkileyici, birinci ağızdan yazılmış, anahtar kelime açısından zengin bir LinkedIn özeti (about) yaz — 3-5 kısa paragraf.';\n"
  . "        else \$sysLaw .= ' Doğru iş mektubu formatında yaz: gönderen, alıcı, tarih, konu, hitap, ikna edici gövde metni (güçlü yönler + pozisyon bağlantısı), kapanış. İlan metni verildiyse birebir ona göre uyarla.';\n"
  . "    }";

$src=str_replace($anchor,$inject,$src);
if($src===$start) exit("degisiklik uygulanamadi\n");

/* php -l guvenligi */
$tmp=$file.".tmp-trbew"; file_put_contents($tmp,$src);
$out=[]; $code=0; exec("php -l ".escapeshellarg($tmp)." 2>&1",$out,$code); @unlink($tmp);
if($code!==0){ echo "✗ LINT HATASI — api.php DEGISTIRILMEDI:\n".implode("\n",$out)."\n"; exit; }

file_put_contents($file.".bak-trbew-".date("Ymd-His"),$start);
file_put_contents($file,$src);
echo "✓ trbew_ karriyer modu + dil kilidi eklendi (ch_trbew_doc).\n";
echo "  trbew_*_de -> tamamen Almanca | trbew_*_en -> Ingilizce | digerleri Turkce/secilen dil\n";
echo "SIL: rm apply-turkiye-bewerbung-backend.php\n";
echo "Test: TR -> 'CV — Almanca' uret -> tamamen Almanca tabellarischer Lebenslauf gelmeli.\n";
