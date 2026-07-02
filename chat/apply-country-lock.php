<?php
/**
 * ChatHelp — BAYRAK/HUKUK KARIŞIKLIĞI KÖK NEDEN DÜZELTMESİ
 * -----------------------------------------------------------------------------
 *  Teşhis (dump-design + dump-country-revert): İKİ ayrı IP-otomatik-tespit
 *  sistemi var — eski detectLoc() (backend action=detect_location) ve yeni
 *  CH_GEO_MULTI (client-side geo.php). detectLoc() SADECE 'ch_ldi' bayrağını
 *  kontrol ediyor, kullanıcının setCountry() ile yaptığı MANUEL seçimi hiç
 *  bilmiyor. Sonuç: kullanıcı 🇹🇷 seçer -> CC=TR olur -> ama az sonra
 *  (yavaş/asenkron) detectLoc()'un IP sorgusu döner -> CC'yi tekrar IP'nin
 *  gösterdiği ülkeye (çoğunlukla DE) EZER. "Bayrak DE'de takılı kalıyor"
 *  şikayetinin kök nedeni budur.
 *
 *  Düzeltme (CH_CC_LOCK): yeni bir 'ch_cc_manual' bayrağı ekler.
 *   1) setCountry(cc) çağrıldığında (kullanıcı ELLE seçtiğinde) bu bayrak set edilir.
 *   2) detectLoc() artık CC'yi SADECE bu bayrak yoksa değiştirir.
 *  Elle seçim her zaman IP tespitini kalıcı olarak ezer (sayfa yenilense bile).
 *
 * KULLANIM: pull-updates.php?files=apply-country-lock.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-country-lock BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_CC_LOCK")!==false) exit("Zaten ekli (CH_CC_LOCK).\n");
file_put_contents($file.".bak-cclock-".date("Ymd-His"),$src);
$rep=[];

/* ---- 1) setCountry: manuel secim bayragini set et ---- */
$o1="function setCountry(cc){CC=cc;const c=CC_DATA[cc]||CC_DATA.DE;if(!localStorage.getItem('ch_lm')){UL=c.l||'de';}savePref();applyUI();applyCC();addMsg('ai','✅ Land: '+c.f+' '+c.n);}";
$n1="function setCountry(cc){CC=cc;const c=CC_DATA[cc]||CC_DATA.DE;if(!localStorage.getItem('ch_lm')){UL=c.l||'de';}try{localStorage.setItem('ch_cc_manual','1');}catch(e){}/*CH_CC_LOCK*/savePref();applyUI();applyCC();addMsg('ai','✅ Land: '+c.f+' '+c.n);}";
$c=substr_count($src,$o1); if($c>0) $src=str_replace($o1,$n1,$src); $rep[]=["1 setCountry manuel bayrak",$c];

/* ---- 2) detectLoc: manuel secim varsa CC'yi ezme ---- */
$o2="if(d.country){CC=d.country;if(!localStorage.getItem('ch_lm'))UL=d.lang||'de';localStorage.setItem('ch_ldi','1');savePref();}";
$n2="if(d.country){if(!localStorage.getItem('ch_cc_manual'))CC=d.country;/*CH_CC_LOCK*/if(!localStorage.getItem('ch_lm'))UL=d.lang||'de';localStorage.setItem('ch_ldi','1');savePref();}";
$c=substr_count($src,$o2); if($c>0) $src=str_replace($o2,$n2,$src); $rep[]=["2 detectLoc manuel-seciме saygi",$c];

/* ---- 3) CH_GEO_MULTI: manuel secim varsa tetiklenmesin (varsa) ---- */
$o3='if(localStorage.getItem("ch_lm")) return;         // kullanıcı dili elle seçtiyse dokunma';
$n3='if(localStorage.getItem("ch_lm")) return;         // kullanıcı dili elle seçtiyse dokunma
      if(localStorage.getItem("ch_cc_manual")) return; /*CH_CC_LOCK*/ // ulkeyi elle sectiyse dokunma';
$c=substr_count($src,$o3); if($c>0) $src=str_replace($o3,$n3,$src); $rep[]=["3 CH_GEO_MULTI manuel-secime saygi (varsa)",$c];

$ok=0; foreach($rep as $r){ if($r[1]>0) $ok++; }
if($src!==$start){ file_put_contents($file,$src); }
echo "Rapor:\n";
foreach($rep as $r) echo ($r[1]>0?"  OK  ":"  ✗   ").$r[0]."  -> ".$r[1]."\n";
echo "\n".($ok>0?"index.php guncellendi ($ok/3 - madde 3 opsiyonel, CH_GEO_MULTI yoksa 0 normal). Yedek: index.php.bak-cclock-*\n":"HICBIR DEGISIKLIK YAPILAMADI — anchor'lar farkli, bana dosyanin ilgili satirlarini gonder.\n");
echo "SIL: rm apply-country-lock.php\n";
echo "Test: 🇹🇷 sec -> sayfayi yenile (F5) birkac kez -> bayrak/hukuk hep TR kalmali, DE'ye donmemeli.\n";
