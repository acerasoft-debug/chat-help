<?php
/**
 * ChatHelp — apply-i18n-truce (CH_I18N_TRUCE) — DE-disi dillerdeki oynamanin KOK COZUMU
 *
 *  KANIT ZINCIRI (dump-i18nwar + transkript kod cikarimi):
 *   • Moduller (CV/VST/TR-Visa/ModPack/ModHub/Jobs...) 1-1.5 sn'de bir
 *     "basligim L().ttl degilse GERI YAZ" dongusu calistiriyor.
 *   • t.textContent=... atamasi ESKI metin dugumunu YOK EDIP YENISINI yaratir.
 *   • i18n-full'un DONE/ORIG haritalari dugum-bazli (WeakMap) oldugundan yeni
 *     dugumu hic taniyamaz -> translate.php'ye sorup TEKRAR cevirir -> modul
 *     1.5 sn sonra yine geri yazar -> SONSUZ metin ping-pongu (yalniz DE-disi
 *     dillerde gorunur; DE'de iki taraf da ayni Almancayi yazar) + sunucuya
 *     bitmeyen ceviri istekleri.
 *
 *  COZUM (tek bogaz noktasi — i18n-full'e SAHIPLIK ATESKESI):
 *   Bir ogeye yazdigimiz cevirinin YERINE baska bir katman farkli metin
 *   koymussa, o ogenin sahibi odur: oge kalici data-noi18n isaretlenir
 *   (skipEl bunu zaten tanir) ve i18n-full oraya bir daha DOKUNMAZ.
 *   Savas her oge icin en fazla BIR tur surer, sonra sonsuza dek biter.
 *   Moduller kendi cok-dilli basliklarini yazar (zaten dogru dildeler),
 *   i18n-full sahipsiz her seyi cevirmeye devam eder.
 *
 *  3 cerrahi degisiklik (ayni desen index'te 1-2 kopya olabilir — i18n-full
 *  ve kardes katman; HER kopyaya uygulanir):
 *   [1] WeakMap tanimlarina _ELL/_ELV (oge->yazdigimiz dil/deger) eklenir
 *   [2] ceviri yazim noktasi (DONE.set(n,lang)) oge hafizasini gunceller
 *   [3] alim dongusu: oge hafizasiyla celisen yeni dugum -> data-noi18n + atla
 * KULLANIM: pull-updates.php?files=apply-i18n-truce.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-i18n-truce BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_I18N_TRUCE')!==false) exit("Zaten ekli (CH_I18N_TRUCE).\n");
if (strpos($src,'__chI18nFull')===false) exit("HATA: i18n-full katmani bulunamadi.\n");

$fail=[]; $log=[];

/* [1] oge-hafizasi haritalari */
$o1 = 'var ORIG=new WeakMap(), DONE=new WeakMap(), KEY=new WeakMap();';
$n1 = 'var ORIG=new WeakMap(), DONE=new WeakMap(), KEY=new WeakMap(); var _ELL=new WeakMap(), _ELV=new WeakMap(); /*CH_I18N_TRUCE*/';
$c1=substr_count($src,$o1);
if($c1===1){ $src=str_replace($o1,$n1,$src); $log[]="  ✓ [1] oge-hafizasi haritalari eklendi"; }
else { $log[]="  ✗ [1] anchor ($c1, beklenen 1)"; $fail[]=1; }

/* [2] yazim noktasi: yazdigimiz deger oge hafizasina */
$o2 = 'DONE.set(n,lang);';
$n2 = 'DONE.set(n,lang); try{ var _pe=n.parentElement||n.parentNode; if(_pe&&_pe.nodeType===1){ _ELL.set(_pe,lang); _ELV.set(_pe,n.nodeValue); } }catch(e){} /*CH_I18N_TRUCE*/';
$c2=substr_count($src,$o2);
if($c2===1){ $src=str_replace($o2,$n2,$src); $log[]="  ✓ [2] yazim noktasi oge hafizasini guncelliyor"; }
else { $log[]="  ✗ [2] anchor ($c2, beklenen 1)"; $fail[]=2; }

/* [3] alim dongusu: sahiplik celiskisi -> kalici ateskes */
$o3 = '      if(DONE.get(n)===lang) return;';
$n3 = '      try{ var _pe0=n.parentElement||n.parentNode; if(_pe0&&_pe0.nodeType===1&&_ELL.get(_pe0)===lang&&_ELV.get(_pe0)!==n.nodeValue){ if(_pe0.setAttribute) _pe0.setAttribute("data-noi18n","1"); return; } }catch(e){} /*CH_I18N_TRUCE*/'."\n".'      if(DONE.get(n)===lang) return;';
$c3=substr_count($src,$o3);
if($c3===1){ $src=str_replace($o3,$n3,$src); $log[]="  ✓ [3] sahiplik ateskesi devrede"; }
else { $log[]="  ✗ [3] anchor ($c3, beklenen 1)"; $fail[]=3; }

echo implode("\n",$log)."\n";
if ($fail) { echo "\nHATA: eslesmeyen adimlar: ".implode(', ',$fail)." — index.php DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'tr').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-i18ntruce-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_I18N_TRUCE uygulandi:\n";
echo "   • DE-disi dillerdeki menu/kart metin ping-pongu KOKUNDEN bitti (sahiplik ateskesi)\n";
echo "   • translate.php'ye giden sonsuz ceviri istekleri de kesildi (sunucu yuku azalir)\n";
echo "   • moduller kendi cok-dilli basliklarini korur; i18n-full sahipsiz alanlari cevirir\n";
