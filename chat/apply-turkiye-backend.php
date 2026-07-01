<?php
/**
 * ChatHelp — Türkiye hukuk sistemi + belge dili (backend) — apply-france-backend.php ile aynı desen
 * -----------------------------------------------------------------------------------------------
 *  1) lawSystems['TR'] ekler (mevcut 'FR' satırının hemen ardına, bilinen kesin metinle anchor)
 *  2) CH_FR_DOCLANG'daki dil eşleme tablosuna 'TR'=>'tr' ekler (Türk belgeleri Türkçe üretilsin)
 *  Güvenli: değişiklik php -l ile denetlenir, temiz değilse api.php'ye YAZILMAZ.
 *
 * KULLANIM: chat-help.com/chat/apply-turkiye-backend.php -> opcache-reset.php. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-turkiye-backend BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file=__DIR__."/api.php";
if(!file_exists($file)) exit("api.php yok\n");
$src=file_get_contents($file); $start=$src;

if(strpos($src,"CH_TR_LAWSYS")!==false && strpos($src,"CH_TR_DOCLANG")!==false){
  exit("Zaten ekli (CH_TR_LAWSYS + CH_TR_DOCLANG). Değişiklik yok.\n");
}

$changed=0;

/* 1) lawSystems['TR'] — bilinen kesin 'FR' satırının hemen ardına ekle */
$frAnchor = "'FR' => \"Expert avocat français. Appliquer le droit français: Code civil, Code du travail, Code pénal. Citer les articles.\",";
if(strpos($src,"CH_TR_LAWSYS")===false){
  if(strpos($src,$frAnchor)!==false){
    $trLine = "\n        'TR' => \"Türk hukuku uzmanı avukat. Türk hukukunu uygula: Türk Borçlar Kanunu, İş Kanunu, Tüketici Kanunu, Türk Medeni Kanunu, Türk Ceza Kanunu. İlgili madde numaralarını belirt. /*CH_TR_LAWSYS*/\",";
    $src = str_replace($frAnchor, $frAnchor.$trLine, $src);
    $changed++;
    echo "✓ lawSystems['TR'] eklendi.\n";
  } else {
    echo "✗ lawSystems FR anchor bulunamadı (metin farklı olabilir) — lawSystems['TR'] EKLENEMEDİ.\n";
  }
} else { echo "• lawSystems['TR'] zaten ekli.\n"; }

/* 2) CH_FR_DOCLANG dil eşleme tablosu — bilinen kesin metne 'TR'=>'tr' ekle */
$docLangAnchor = "\$__ll=['DE'=>'de','AT'=>'de','CH'=>'de','FR'=>'fr','BE'=>'fr','LU'=>'fr','IT'=>'it','ES'=>'es','NL'=>'nl']";
if(strpos($src,"CH_TR_DOCLANG")===false){
  if(strpos($src,$docLangAnchor)!==false){
    $newDocLang = "\$__ll=['DE'=>'de','AT'=>'de','CH'=>'de','FR'=>'fr','BE'=>'fr','LU'=>'fr','IT'=>'it','ES'=>'es','NL'=>'nl','TR'=>'tr']; /*CH_TR_DOCLANG*/";
    $src = str_replace($docLangAnchor, $newDocLang, $src);
    $changed++;
    echo "✓ Belge dili eşlemesine TR->tr eklendi (Türk belgeleri Türkçe üretilecek).\n";
  } else {
    echo "✗ CH_FR_DOCLANG anchor bulunamadı — belge dili eşlemesi EKLENEMEDİ (Fransa yaması hiç uygulanmamış olabilir).\n";
  }
} else { echo "• Belge dili eşlemesi zaten ekli.\n"; }

/* 3) __lln (dil adı tablosu, native açıklama başlığı için) — varsa TR ekle, yoksa atla (kritik değil) */
$llnAnchor = "\$__lln=['de'=>'DEUTSCH','fr'=>'FRANZÖSISCH','it'=>'ITALIENISCH','es'=>'SPANISCH','nl'=>'NIEDERLÄNDISCH']";
if(strpos($src,$llnAnchor)!==false && strpos($src,"'tr'=>'TÜRKISCH'")===false){
  $newLln = "\$__lln=['de'=>'DEUTSCH','fr'=>'FRANZÖSISCH','it'=>'ITALIENISCH','es'=>'SPANISCH','nl'=>'NIEDERLÄNDISCH','tr'=>'TÜRKISCH'];";
  $src = str_replace($llnAnchor."??'DEUTSCH'", $newLln."[\$__ll]??'DEUTSCH'", $src);
  // yukarıdaki satır orijinal ifadeyi tam eşleştirmiyorsa dokunmadan bırak (güvenli, kritik değil)
}

if($changed===0){
  echo "\nHiçbir değişiklik uygulanamadı (anchor'lar bulunamadı). api.php dokunulmadan bırakıldı.\n";
  exit;
}

/* Güvenlik: geçici dosyada php -l denetimi */
$tmp = $file . ".tmp-tr";
file_put_contents($tmp, $src);
$out = []; $code = 0;
exec("php -l " . escapeshellarg($tmp) . " 2>&1", $out, $code);
@unlink($tmp);

if ($code !== 0) {
  echo "\n✗ LINT HATASI — api.php DEĞİŞTİRİLMEDİ (güvenli):\n" . implode("\n", $out) . "\n";
  exit;
}

$bak = $file . ".bak-tr-" . date("Ymd-His");
file_put_contents($bak, $start);
file_put_contents($file, $src);

echo "\nLINT: api.php sözdizimi temiz ✓\n";
echo "Yedek: " . basename($bak) . "\n";
echo "\nSONRA: opcache-reset.php. SİL: rm apply-turkiye-backend.php\n";
echo "Test: TR sec -> bir belge uret -> hukuk Turk hukuku + metin Turkce olmali.\n";
