<?php
/**
 * ChatHelp — apply-tr-vertrag (CH_TR_VERTRAG) — TR sozlesme setini TAMAMLA.
 *  Turk Borclar Kanunu'na gore eksik sozlesmeleri Vertrag modulune ekler:
 *   eser(istisna), vekalet, danismanlik/hizmet, ariyet(kullanim oduncu),
 *   karz(odunc para), kefalet, gizlilik(NDA), bagislama, adi ortaklik.
 *  Yontem: window.__TRK2 tanimla + syncTR merge/delete satirlarini hook'la
 *  (byte-exact, count-guarded). Modulun ulke-takas mekanizmasi TR'de ekler,
 *  TR disinda kaldirir. Mevcut 6 TR sozlesmesi + 9 yeni = 15.
 *  NOT: duz-kart TR sozlesmelerinin kaldirilmasi AYRI apply'da yapilacak.
 * KULLANIM: pull2.php?key=...&files=apply-tr-vertrag.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-tr-vertrag BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_TR_VERTRAG')!==false) exit("Zaten ekli (CH_TR_VERTRAG).\n");

/* ── ADIM 1: syncTR merge/delete satirlarini __TRK2 icin hook'la ── */
$mOld = 'for(var t in TRK){ CT[t]=TRK[t]; }';
$mNew = 'for(var t in TRK){ CT[t]=TRK[t]; } if(window.__TRK2){for(var _tt in window.__TRK2){CT[_tt]=window.__TRK2[_tt];}} /*CH_TR_VERTRAG*/';
$dOld = 'for(var t2 in TRK){ delete CT[t2]; }';
$dNew = 'for(var t2 in TRK){ delete CT[t2]; } if(window.__TRK2){for(var _td in window.__TRK2){delete CT[_td];}} /*CH_TR_VERTRAG*/';

$cm = substr_count($src,$mOld);
$cd = substr_count($src,$dOld);
if ($cm!==1) exit("HATA: merge anchor $cm kez (1 bekleniyordu). index DEGISTIRILMEDI.\n");
if ($cd!==1) exit("HATA: delete anchor $cd kez (1 bekleniyordu). index DEGISTIRILMEDI.\n");
$src = str_replace($mOld,$mNew,$src);
$src = str_replace($dOld,$dNew,$src);
echo "  ✓ ADIM 1: syncTR merge+delete hook'landi (__TRK2)\n";

/* ── ADIM 2: __TRK2 sozlesme seti (TBK) — </body> oncesi ── */
$block = <<<'HTMLBLOCK'
<script id="ch-tr-vertrag">
/* CH_TR_VERTRAG — Turk Borclar Kanunu sozlesmeleri (Vertrag modulune, syncTR hook ile) */
try{(function(){
  var UC=[["uc_t1","Ek Madde 1 — Başlık",""],["uc_b1","Ek Madde 1 — Metin","","ta"],["uc_t2","Ek Madde 2 — Başlık (opsiyonel)",""],["uc_b2","Ek Madde 2 — Metin (opsiyonel)","","ta"]];
  function ucStep(){ return {t:"Kendi Maddeleriniz (opsiyonel)",f:UC}; }
  var K={};

  K["vtr_tr_eser"]={ic:"🔧",n:"Eser (İstisna) Sözleşmesi",sn:"TBK m.470 vd. — yüklenici bir eser meydana getirmeyi üstlenir",price:"4,99 €",pgs:4,steps:[
    {t:"Taraflar",f:[["yuklenici","Yüklenici (ad soyad/unvan, kimlik/VKN, adres)","","ta"],["is_sahibi","İş sahibi (ad soyad/unvan, kimlik/VKN, adres)","","ta"]]},
    {t:"Eser ve Bedel",f:[["eser","Meydana getirilecek eser (tanım, kapsam, teknik özellikler)","","ta"],["malzeme","Malzeme kim sağlayacak","Malzeme yüklenici tarafından sağlanır; bedeli eser ücretine dahildir."],["bedel","Eser bedeli (TL)"],["odeme","Ödeme planı","%30 avans, %40 ara hakediş, %30 teslimde"],["sure","Teslim tarihi / süresi"]]},
    {t:"Ayıp ve Teslim",f:[["ayip","Ayıba karşı sorumluluk","Yüklenici eseri ayıpsız ve sözleşmeye uygun teslim etmekle yükümlüdür (TBK m.474 vd.). İş sahibi teslimden sonra uygun sürede eseri gözden geçirip ayıpları bildirir."],["gecikme","Gecikme","Yüklenicinin kusuruyla gecikmede iş sahibinin TBK'daki seçimlik hakları saklıdır."],["ort","Yer (şehir)"],["datum","Tarih"]]},
    ucStep()]};

  K["vtr_tr_vekalet"]={ic:"📜",n:"Vekâlet Sözleşmesi",sn:"TBK m.502 vd. — vekilin iş görmeyi üstlenmesi",price:"4,99 €",pgs:4,steps:[
    {t:"Taraflar",f:[["muvekkil","Vekâlet veren (müvekkil): ad soyad, T.C. Kimlik No, adres","","ta"],["vekil","Vekil: ad soyad/unvan, kimlik/VKN, adres","","ta"]]},
    {t:"Kapsam ve Ücret",f:[["konu","Vekâletin konusu ve kapsamı (yapılacak işler)","","ta"],["yetki","Özel yetkiler","Vekile işin gereği olağan yetkiler verilmiştir. TBK m.504 uyarınca sulh, ibra, tahkim, feragat gibi özel yetki gerektiren işlemler ayrıca belirtilmedikçe kapsam dışındadır."],["ucret","Vekâlet ücreti (TL)","kararlaştırılmamışsa TBK m.502/son uyarınca teamüle göre"],["masraf","Masraflar","İşin görülmesi için yapılan zorunlu masraflar müvekkil tarafından karşılanır (TBK m.510)."]]},
    {t:"Yükümlülükler ve İmza",f:[["ozen","Özen ve sadakat","Vekil işi müvekkilin yararına ve talimatına uygun, özenle yürütür ve hesap verir (TBK m.506-508)."],["sona","Sona erme","Taraflar sözleşmeyi her zaman sona erdirebilir (TBK m.512); uygun olmayan zamanda sona erdiren doğan zarardan sorumludur."],["ort","Yer (şehir)"],["datum","Tarih"]]},
    ucStep()]};

  K["vtr_tr_danismanlik"]={ic:"🤝",n:"Danışmanlık / Serbest Hizmet Sözleşmesi",sn:"Bağımsız hizmet sunumu (TBK genel hükümler)",price:"4,99 €",pgs:4,steps:[
    {t:"Taraflar",f:[["veren","Hizmet veren (danışman): ad soyad/unvan, VKN, adres","","ta"],["alan","Hizmet alan: ad soyad/unvan, VKN, adres","","ta"]]},
    {t:"Hizmet ve Bedel",f:[["hizmet","Hizmetin konusu ve kapsamı","","ta"],["sure","Süre / dönem","______ (belirli / belirsiz)"],["bedel","Hizmet bedeli (TL) ve KDV durumu"],["odeme","Ödeme koşulu","aylık, fatura tarihini izleyen 7 gün içinde banka havalesiyle"],["baglilik","Bağımsızlık","Hizmet veren bağımsız çalışır; taraflar arasında iş (hizmet akdi) ilişkisi bulunmaz."]]},
    {t:"Gizlilik ve İmza",f:[["gizlilik","Gizlilik","Taraflar, hizmet kapsamında öğrendikleri ticari ve kişisel bilgileri gizli tutar; bu yükümlülük sözleşme sonrasında da devam eder."],["fesih","Fesih","Taraflar ______ gün önceden yazılı bildirimle sözleşmeyi feshedebilir."],["ort","Yer (şehir)"],["datum","Tarih"]]},
    ucStep()]};

  K["vtr_tr_ariyet"]={ic:"🔑",n:"Kullanım Ödüncü (Ariyet) Sözleşmesi",sn:"TBK m.379 vd. — bir malın ücretsiz kullanıma bırakılması",price:"3,99 €",pgs:3,steps:[
    {t:"Taraflar ve Mal",f:[["veren","Ödünç veren: ad soyad, adres","","ta"],["alan","Ödünç alan: ad soyad, adres","","ta"],["mal","Ödünç verilen mal (tanım, adet, durum)","","ta"]]},
    {t:"Kullanım ve İade",f:[["amac","Kullanım amacı","Mal yalnızca amacına uygun ve özenle kullanılır (TBK m.380)."],["sure","Kullanım süresi / iade zamanı"],["giderler","Giderler","Olağan kullanım giderleri ödünç alana aittir; olağanüstü giderler ödünç verence karşılanır."],["iade","İade","Süre sonunda mal, teslim alındığı durumda iade edilir; olağan yıpranma dışındaki hasardan ödünç alan sorumludur."],["ort","Yer (şehir)"],["datum","Tarih"]]},
    ucStep()]};

  K["vtr_tr_karz"]={ic:"💰",n:"Ödünç Para (Karz) Sözleşmesi",sn:"TBK m.386 vd. — tüketim ödüncü",price:"4,99 €",pgs:4,steps:[
    {t:"Taraflar",f:[["veren","Ödünç veren: ad soyad, T.C. Kimlik No, adres","","ta"],["alan","Ödünç alan: ad soyad, T.C. Kimlik No, adres","","ta"]]},
    {t:"Tutar ve Faiz",f:[["tutar","Ödünç verilen tutar (TL, rakam ve yazı ile)","","ta"],["teslim","Teslim şekli ve tarihi","banka havalesi ile, sözleşme tarihinde"],["faiz","Faiz","Faizsizdir. / Yıllık ______ %% faiz uygulanır (kararlaştırılmışsa)."],["vade","Geri ödeme vadesi / taksit planı","","ta"]]},
    {t:"Geri Ödeme ve İmza",f:[["temerrut","Temerrüt","Vadesinde ödenmeyen tutara TBK ve mevzuata göre temerrüt faizi işletilir."],["ifa_yeri","İfa yeri","Ödeme, ödünç verenin bildireceği banka hesabına yapılır."],["ort","Yer (şehir)"],["datum","Tarih"]]},
    ucStep()]};

  K["vtr_tr_kefalet"]={ic:"✍️",n:"Kefalet Sözleşmesi",sn:"TBK m.581 vd. — geçerlilik için azami tutar ve tarih el yazısıyla yazılmalıdır (m.583)",price:"4,99 €",pgs:4,steps:[
    {t:"Taraflar",f:[["alacakli","Alacaklı: ad soyad/unvan, adres","","ta"],["kefil","Kefil: ad soyad, T.C. Kimlik No, adres","","ta"],["borclu","Asıl borçlu: ad soyad/unvan, adres","","ta"]]},
    {t:"Borç ve Kefalet",f:[["borc","Kefalete konu asıl borç (kaynağı ve niteliği)","","ta"],["azami_tutar","Kefilin sorumlu olduğu AZAMİ tutar (TL)","— bu tutar imzada el yazısıyla da yazılmalıdır (TBK m.583)"],["tur","Kefalet türü","Adi kefalet / Müteselsil kefalet (belirtiniz)"],["tarih_elyazisi","Kefalet tarihi","— imzada el yazısıyla da yazılmalıdır (TBK m.583)"]]},
    {t:"Uyarı ve İmza",f:[["uyari","Geçerlilik uyarısı","Kefalet sözleşmesi yazılı olmalı; kefilin sorumlu olduğu azami tutar, kefalet tarihi ve (müteselsil ise) bu husus kefilin EL YAZISIYLA belirtilmelidir. Aksi halde kefalet geçersizdir.","ta"],["es_rizasi","Eş rızası","Evli kefil için diğer eşin yazılı rızası gerekir (TBK m.584)."],["ort","Yer (şehir)"],["datum","Tarih"]]},
    ucStep()]};

  K["vtr_tr_gizlilik"]={ic:"🔒",n:"Gizlilik Sözleşmesi (NDA)",sn:"Karşılıklı gizlilik ve sır saklama",price:"4,99 €",pgs:4,steps:[
    {t:"Taraflar",f:[["taraf1","Taraf 1: ad soyad/unvan, VKN, adres","","ta"],["taraf2","Taraf 2: ad soyad/unvan, VKN, adres","","ta"]]},
    {t:"Kapsam",f:[["amac","İşbirliği / paylaşım amacı","","ta"],["gizli","Gizli bilgi tanımı","Taraflarca sözlü, yazılı veya elektronik olarak paylaşılan; ticari, teknik, finansal ve kişisel her türlü bilgi gizli kabul edilir."],["istisna","Kapsam dışı","Kamuya mal olmuş, karşı taraftan önce bilinen veya yasal zorunlulukla açıklanan bilgiler kapsam dışıdır."]]},
    {t:"Yükümlülük ve Süre",f:[["yukumluluk","Gizlilik yükümlülüğü","Taraflar gizli bilgiyi yalnızca amaç için kullanır, üçüncü kişilere açıklamaz ve korur."],["sure","Gizliliğin süresi","Sözleşme sona erse dahi ______ yıl devam eder."],["ceza","Cezai şart (opsiyonel)","İhlal halinde ______ TL cezai şart ödenir; fazlaya ilişkin haklar saklıdır."],["ort","Yer (şehir)"],["datum","Tarih"]]},
    ucStep()]};

  K["vtr_tr_bagislama"]={ic:"🎁",n:"Bağışlama Sözleşmesi",sn:"TBK m.285 vd. — karşılıksız kazandırma",price:"3,99 €",pgs:3,steps:[
    {t:"Taraflar ve Konu",f:[["bagislayan","Bağışlayan: ad soyad, T.C. Kimlik No, adres","","ta"],["bagislanan","Bağışlanan: ad soyad, T.C. Kimlik No, adres","","ta"],["konu","Bağışlanan mal / hak (tanım, değer)","","ta"]]},
    {t:"Koşullar",f:[["kosul","Koşul / yükleme (varsa)","Bağışlama koşulsuzdur."],["teslim","Teslim","Bağışlanan mal bu sözleşmeyle birlikte teslim edilmiştir."],["donme","Dönme hakkı","Bağışlayanın TBK m.295 vd.'daki dönme hakları saklıdır."],["ort","Yer (şehir)"],["datum","Tarih"]]},
    ucStep()]};

  K["vtr_tr_ortaklik"]={ic:"👥",n:"Adi Ortaklık Sözleşmesi",sn:"TBK m.620 vd. — ortak amaç için emek/sermaye birliği",price:"4,99 €",pgs:4,steps:[
    {t:"Ortaklar",f:[["ortak1","Ortak 1: ad soyad/unvan, kimlik/VKN, adres","","ta"],["ortak2","Ortak 2: ad soyad/unvan, kimlik/VKN, adres","","ta"],["amac","Ortaklığın amacı ve faaliyet konusu","","ta"]]},
    {t:"Sermaye ve Paylaşım",f:[["katilim","Ortakların katılım payları (para, mal, emek)","","ta"],["kar_zarar","Kâr ve zararın paylaşımı","Aksi kararlaştırılmadıkça kâr ve zarar eşit paylaşılır (TBK m.623)."],["yonetim","Yönetim","Ortaklık ortaklarca birlikte yönetilir; olağan işler için her ortak yetkilidir."],["hesap","Hesap ve denetim","Kayıtlar düzenli tutulur; her ortak bilgi ve hesap isteyebilir."]]},
    {t:"Sona Erme ve İmza",f:[["cikma","Çıkma / fesih","Ortaklık TBK m.639 vd. hükümlerine göre sona erer; çıkan ortağın payı tasfiye edilir."],["rekabet","Rekabet yasağı (opsiyonel)","Ortaklar, ortaklık faaliyeti ile rekabet eden işlemlerden kaçınır."],["ort","Yer (şehir)"],["datum","Tarih"]]},
    ucStep()]};

  window.__TRK2 = K;
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'tv').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-trvertrag-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_TR_VERTRAG')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_TR_VERTRAG diskte (".strlen($chk)." bayt)\n";
echo "\n✓ TR sozlesme seti tamamlandi: 9 yeni TBK sozlesmesi module eklendi (toplam 15).\n";
echo "  Sonraki adim: duz-kart TR sozlesmelerini kaldirma (ayri apply).\n";
