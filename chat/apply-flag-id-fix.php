<?php
/**
 * ChatHelp — SOL MENÜ BAYRAĞI GÜNCELLENMİYOR: ID UYUŞMAZLIĞI KÖK NEDEN DÜZELTMESİ
 * ------------------------------------------------------------------------------------
 *  Teşhis: applyCC() bayrağı 'sb-cflag' ID'sinden güncellemeye çalışıyor, ama
 *  gerçek HTML'deki span ID'si 'sb-flag' (<span id="sb-flag">🇩🇪</span>).
 *  if(el('sb-cflag')) kontrolü hep FALSE dönüyor -> bayrak HİÇ güncellenmiyor,
 *  ilk yüklemedeki 🇩🇪'de kalıyor. Hemen altındaki 'sb-cname' ID'si doğru
 *  olduğu için ülke ismi ("United States" vb.) doğru güncelleniyor — kullanıcının
 *  gördüğü "isim değişiyor ama bayrak Almanya'da kalıyor" tam olarak bu yüzden.
 *
 *  Ek düzeltme: 'tb-flag' de yanlış hedefleniyor — bu bir DIV (id="tb-flag"),
 *  içinde iki ayrı span var (tb-flag-emoji, tb-country-name). textContent
 *  ataması DIV'in tüm içeriğini (her iki span'i) siler, sadece emoji kalır.
 *  Doğrudan span'ler hedeflenmeli.
 *
 * KULLANIM: pull-updates.php?files=apply-flag-id-fix.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-flag-id-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_FLAG_ID_FIX")!==false) exit("Zaten ekli (CH_FLAG_ID_FIX).\n");
file_put_contents($file.".bak-flagfix-".date("Ymd-His"),$src);
$rep=[];

/* ---- 1) sb-cflag -> sb-flag (gercek HTML ID'si) ---- */
$o1="if(el('sb-cflag')) el('sb-cflag').textContent=c.f;";
$n1="if(el('sb-flag')) el('sb-flag').textContent=c.f; /*CH_FLAG_ID_FIX*/";
$c=substr_count($src,$o1); if($c>0) $src=str_replace($o1,$n1,$src); $rep[]=["1 sb-cflag -> sb-flag (sol menu bayragi)",$c];

/* ---- 2) tb-flag (DIV) -> tb-flag-emoji + tb-country-name (dogru span'ler) ---- */
$o2="if(el('tb-flag')) el('tb-flag').textContent=c.f;";
$n2="if(el('tb-flag-emoji')) el('tb-flag-emoji').textContent=c.f; if(el('tb-country-name')) el('tb-country-name').textContent=CC;";
$c=substr_count($src,$o2); if($c>0) $src=str_replace($o2,$n2,$src); $rep[]=["2 tb-flag DIV -> tb-flag-emoji + tb-country-name span'leri",$c];

$ok=0; foreach($rep as $r){ if($r[1]>0) $ok++; }
if($src!==$start){ file_put_contents($file,$src); }
echo "Rapor:\n";
foreach($rep as $r) echo ($r[1]>0?"  OK  ":"  ✗   ").$r[0]."  -> ".$r[1]."\n";
echo "\n".($ok>0?"index.php guncellendi ($ok/2). Yedek: index.php.bak-flagfix-*\n":"HICBIR DEGISIKLIK YAPILAMADI — anchor'lar farkli, dosyanin applyCC() fonksiyonunun TAM govdesini bana gonder.\n");
echo "SIL: rm apply-flag-id-fix.php\n";
echo "Test: sol menuden bir ulke sec (orn United States) -> bayrak DA degismeli (artik ulke ismiyle birlikte).\n";
