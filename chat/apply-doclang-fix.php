<?php
/**
 * ChatHelp — BELGE DİLİ/HUKUK MANTIĞI KIRIK KOD DÜZELTMESİ (KÖK NEDEN)
 * ---------------------------------------------------------------------------------
 *  Teşhis (dump-dogenerate): üç ayrı yamanın (Fransa -> Türkiye -> tüm-ülkeler)
 *  üst üste binmesi $__ll atamasını BOZMUŞ. apply-turkiye-backend.php'nin anchor'ı
 *  sadece dizi KISMINI arayıp değiştirdiği için `[$country]??'de';` indeksleme
 *  kısmı KOPUK, kullanılmayan bir ifade olarak kalmış:
 *
 *    $__ll=[...diziler...]/*CH_ALL_DOCLANG*​/; /*CH_TR_DOCLANG*​/[$country]??'de'; ...
 *
 *  Sonuç: $__ll artık bir STRING ('de'/'tr'/...) değil, TÜM DİZİ. Bu yüzden:
 *   1) $__lln[$__ll] geçersiz (array'i index olarak kullanma) -> PHP her zaman
 *      '??DEUTSCH' yedeğine düşüyor -> $__lln HEP "DEUTSCH".
 *   2) $ulang!==$__ll (string vs array) tip farkından HER ZAMAN true -> nativeNote
 *      HER SEFERİNDE "resmi belge tamamen ALMANCA kalsın" talimatını AI'ya ekliyor
 *      — ülke ne olursa olsun. Kullanıcının gördüğü "the official document is
 *      created in German" mesajının kök nedeni budur.
 *
 *  ÖNEMLİ: $sysLaw = $lawSystems[$country] (hukuk sistemi seçimi) BOZULMAMIŞ,
 *  doğru çalışıyor — sorun sadece bu native-note/belge-dili ek katmanında.
 *
 *  Düzeltme: $__ll ve $__lln atamalarını doğru parantezli PHP ifadesine çevirir.
 *
 * KULLANIM: pull-updates.php?files=apply-doclang-fix.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-doclang-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/api.php";
if(!file_exists($file)) exit("api.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_DOCLANG_FIX")!==false) exit("Zaten ekli (CH_DOCLANG_FIX).\n");

$old = "\$__ll=['DE'=>'de','AT'=>'de','CH'=>'de','FR'=>'fr','BE'=>'fr','LU'=>'fr','IT'=>'it','ES'=>'es','NL'=>'nl','TR'=>'tr','GB'=>'en','US'=>'en','PL'=>'pl','SE'=>'sv','PT'=>'pt']/*CH_ALL_DOCLANG*/; /*CH_TR_DOCLANG*/[\$country]??'de'; /*CH_FR_DOCLANG*/ \$__lln=['de'=>'DEUTSCH','fr'=>'FRANZÖSISCH','it'=>'ITALIENISCH','es'=>'SPANISCH','nl'=>'NIEDERLÄNDISCH','tr'=>'TÜRKISCH','en'=>'ENGLISCH','pl'=>'POLNISCH','sv'=>'SCHWEDISCH','pt'=>'PORTUGIESISCH']/*CH_ALL_LLN*/[\$__ll]??'DEUTSCH';";

$new = "\$__ll=(['DE'=>'de','AT'=>'de','CH'=>'de','FR'=>'fr','BE'=>'fr','LU'=>'fr','IT'=>'it','ES'=>'es','NL'=>'nl','TR'=>'tr','GB'=>'en','US'=>'en','PL'=>'pl','SE'=>'sv','PT'=>'pt'][\$country]??'de'); /*CH_DOCLANG_FIX*/ \$__lln=(['de'=>'DEUTSCH','fr'=>'FRANZÖSISCH','it'=>'ITALIENISCH','es'=>'SPANISCH','nl'=>'NIEDERLÄNDISCH','tr'=>'TÜRKISCH','en'=>'ENGLISCH','pl'=>'POLNISCH','sv'=>'SCHWEDISCH','pt'=>'PORTUGIESISCH'][\$__ll]??'DEUTSCH');";

if(strpos($src,$old)===false){
  echo "✗ Beklenen bozuk kod anchor'i bulunamadi — api.php farkli olabilir. HICBIR SEY DEGISTIRILMEDI.\n";
  echo "Dosyanin doGenerate icindeki '\$__ll=' satirinin TAMAMINI bana gonder.\n";
  exit;
}

file_put_contents($file.".bak-doclangfix-".date("Ymd-His"),$src);
$src=str_replace($old,$new,$src);

/* Güvenlik: geçici dosyada php -l denetimi */
$tmp=$file.".tmp-dlf"; file_put_contents($tmp,$src);
$out=[]; $code=0; exec("php -l ".escapeshellarg($tmp)." 2>&1",$out,$code); @unlink($tmp);
if($code!==0){ echo "✗ LINT HATASI — api.php DEGISTIRILMEDI (guvenli):\n".implode("\n",$out)."\n"; exit; }

file_put_contents($file,$src);
echo "✓ \$__ll / \$__lln atamalari duzeltildi (CH_DOCLANG_FIX).\n";
echo "  Artik: \$__ll = secilen ULKENIN dili (orn TR->'tr', US->'en'), dizi degil.\n";
echo "  \$__lln = o dilin BUYUK harfli adi (orn TURKISCH), her zaman DEUTSCH degil.\n";
echo "  nativeNote artik SADECE gercekten farkli oldugunda tetiklenir (dogru mantik).\n";
echo "\nLINT temiz ✓. Yedek: api.php.bak-doclangfix-*\n";
echo "SIL: rm apply-doclang-fix.php\n";
echo "Test: TR sec -> bir belge uret -> belge TAMAMEN Turkce olmali (Almanca + aciklama DEGIL).\n";
echo "      US/GB sec -> Ingilizce; ES sec -> Ispanyolca; hepsi kendi dilinde TAM belge olmali.\n";
