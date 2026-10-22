<?php
/**
 * ChatHelp — apply-tr-expand2 (CH_TR_EXPAND2) — Turkiye katalog genisletme 2
 *  +36 belge · +4 yeni kategori: 💸 Icra ve Borc · 🏥 SGK ve Saglik ·
 *  🚗 Arac ve Trafik · 💶 Banka ve Finans — hepsi Turk hukuku referansli:
 *  IIK m.62 (7 gun itiraz), TBK m.315/344, Is K. m.20/28/41, 6502 m.11/48,
 *  KVKK 6698 m.11, 4982 bilgi edinme, IYUK m.11, 5326 m.27 (15 gun),
 *  6183 m.48 tecil, TMK m.27... Mevcut tr_ kategorilerine de ekler.
 *  Ayni merge deseni (DOCS/CATS/CAT_LABELS/CAT_DOCS + retry boot) — anchor'siz.
 * KULLANIM: pull-updates.php?files=apply-tr-expand2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-tr-expand2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_TR_EXPAND2')!==false) exit("Zaten ekli (CH_TR_EXPAND2).\n");

$bundle = <<<'HTML'
<script id="ch-tr-expand2">
/* CH_TR_EXPAND2 — Turkiye katalog genisletme 2 (+36 belge, +4 kategori) */
try{(function(){
  function Pe(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}

  var NEWCATS={
    tr_icra:{n:"İcra ve Borç",ic:"💸"},
    tr_sgk:{n:"SGK ve Sağlık",ic:"🏥"},
    tr_arac:{n:"Araç ve Trafik",ic:"🚗"},
    tr_banka:{n:"Banka ve Finans",ic:"💶"}
  };

  var E={
    /* İcra ve Borç */
    tr_icra_itiraz:{ic:"⚖️",n:"İcra Takibine İtiraz (İİK m.62 — 7 gün!)",cat:"tr_icra",tier:"komplex",q:[Pe("icra_dairesi","İcra dairesi ve dosya numarası",true),Pe("teblig","Ödeme emrinin tebliğ tarihi (süre: 7 gün!)",true),Pe("alacakli","Alacaklı (ad/unvan)",true),Pe("itiraz_kapsam","İtiraz kapsamı",true,["Borcun tamamına","Borcun bir kısmına (tutar yazın)","Faize","İmzaya"]),Pe("gerekce","İtiraz gerekçeniz (kendi cümlelerinizle)",true),Pe("iletisim","Ad, soyad, T.C. kimlik no ve adresiniz",true)]},
    tr_ihtarname_alacak:{ic:"📮",n:"Alacak İhtarnamesi (temerrüt)",cat:"tr_icra",tier:"standard",q:[Pe("borclu","Borçlu: ad/unvan ve adres",true),Pe("alacak","Alacağın dayanağı ve tutarı",true),Pe("vade","Vade/ödeme tarihi",true),Pe("sure","Verilen ek süre (ör. 7 gün)",true),Pe("iletisim","Ad, soyad ve adresiniz",true)]},
    tr_taksit_teklifi:{ic:"🧾",n:"İcra Dosyasına Taksit Teklifi",cat:"tr_icra",tier:"einfach",q:[Pe("icra_dairesi","İcra dairesi ve dosya numarası",true),Pe("borc","Toplam borç tutarı",true),Pe("teklif","Taksit teklifi (aylık tutar, süre)",true),Pe("gerekce","Ödeme güçlüğü gerekçesi (kısa)",true),Pe("iletisim","Ad, soyad ve adresiniz",true)]},
    tr_maas_haczi:{ic:"💼",n:"Maaş Haczi Oranına İtiraz (İİK m.83)",cat:"tr_icra",tier:"standard",q:[Pe("icra_dairesi","İcra dairesi ve dosya numarası",true),Pe("maas","Net maaşınız",true),Pe("kesinti","Yapılan/istenen kesinti",true),Pe("gecim","Geçim durumu (bakmakla yükümlü olduklarınız)",true),Pe("iletisim","Ad, soyad ve adresiniz",true)]},

    /* Konut ve Kira (ek) */
    tr_kira_tespit_ihtar:{ic:"📈",n:"Kira Bedeli Tespiti Öncesi İhtar (TBK m.344)",cat:"tr_konut",tier:"standard",q:[Pe("muhatap","Muhatap (kiracı/kiraya veren): ad ve adres",true),Pe("tasinmaz","Taşınmazın adresi",true),Pe("mevcut_kira","Mevcut kira ve sözleşme başlangıcı",true),Pe("talep","Talep edilen kira / gerekçe (emsal, TÜFE)",true),Pe("iletisim","Ad, soyad ve adresiniz",true)]},
    tr_depozito_iade:{ic:"💰",n:"Depozito İadesi Talebi",cat:"tr_konut",tier:"einfach",q:[Pe("kiraya_veren","Kiraya veren: ad ve adres",true),Pe("tasinmaz","Tahliye edilen taşınmazın adresi",true),Pe("tahliye","Tahliye/teslim tarihi",true),Pe("depozito","Depozito tutarı",true),Pe("iban","İade için IBAN",true),Pe("iletisim","Ad, soyad ve yeni adresiniz",true)]},
    tr_kira_odenmedi_ihtar:{ic:"⏰",n:"Ödenmeyen Kira İçin İhtar (TBK m.315 — 30 gün)",cat:"tr_konut",tier:"standard",q:[Pe("kiraci","Kiracı: ad ve adres",true),Pe("tasinmaz","Taşınmazın adresi",true),Pe("borc","Ödenmeyen aylar ve toplam tutar",true),Pe("sure","Verilen süre (en az 30 gün)",true),Pe("iletisim","Kiraya veren: ad, soyad ve adres",true)]},
    tr_aidat_itiraz:{ic:"🏢",n:"Site/Apartman Aidat İtirazı (KMK)",cat:"tr_konut",tier:"einfach",q:[Pe("yonetim","Site/apartman yönetimi: ad ve adres",true),Pe("daire","Bağımsız bölüm (blok/daire)",true),Pe("itiraz","İtiraz edilen aidat/karar ve gerekçe",true),Pe("iletisim","Ad, soyad",true)]},

    /* İş ve Çalışma (ek) */
    tr_ise_iade:{ic:"🔄",n:"İşe İade — Arabuluculuk Başvurusu (İş K. m.20, 1 ay!)",cat:"tr_is",tier:"komplex",q:[Pe("isveren","İşveren: unvan ve adres",true),Pe("fesih","Fesih bildirimi tarihi (süre: 1 ay içinde arabulucu!)",true),Pe("kidem","İşe giriş tarihi / kıdem",true),Pe("gerekce","Feshin geçersizlik gerekçesi (kendi cümlelerinizle)",true),Pe("iletisim","Ad, soyad, T.C. kimlik no ve adresiniz",true)]},
    tr_kidem_ihbar:{ic:"💶",n:"Kıdem ve İhbar Tazminatı Talebi",cat:"tr_is",tier:"standard",q:[Pe("isveren","İşveren: unvan ve adres",true),Pe("calisma","Çalışma dönemi (giriş–çıkış) ve son brüt ücret",true),Pe("fesih_sekli","Fesih şekli",true,["İşveren feshetti","Haklı nedenle ben feshettim","Emeklilik","Askerlik/evlilik (kadın)"]),Pe("talep","Talep edilen kalemler",true,["Kıdem","Kıdem + ihbar","Kıdem + ihbar + yıllık izin","Tümü + fazla mesai"]),Pe("iletisim","Ad, soyad ve adresiniz",true)]},
    tr_fazla_mesai:{ic:"🕐",n:"Fazla Mesai Alacağı Talebi (İş K. m.41)",cat:"tr_is",tier:"standard",q:[Pe("isveren","İşveren: unvan ve adres",true),Pe("donem","Fazla çalışma dönemi ve haftalık saatler",true),Pe("ucret","Ücretiniz (fazla saat %50 zamlı)",true),Pe("sure","Ödeme için verilen süre",true),Pe("iletisim","Ad, soyad ve adresiniz",true)]},
    tr_calisma_belgesi:{ic:"📜",n:"Çalışma Belgesi Talebi (İş K. m.28)",cat:"tr_is",tier:"einfach",q:[Pe("isveren","İşveren: unvan ve adres",true),Pe("donem","Çalışma dönemi ve görev",true),Pe("iletisim","Ad, soyad ve adresiniz",true)]},

    /* Tüketici (ek) */
    tr_hakem_heyeti:{ic:"⚖️",n:"Tüketici Hakem Heyeti Başvurusu (6502)",cat:"tr_tuketici",tier:"standard",q:[Pe("satici","Satıcı/sağlayıcı: unvan ve adres",true),Pe("islem","İşlem (tarih, ürün/hizmet, tutar)",true),Pe("sorun","Sorun ve yaşananlar (kendi cümlelerinizle)",true),Pe("talep","Talebiniz",true,["Bedel iadesi","Değişim","Onarım","Bedel indirimi"]),Pe("iletisim","Ad, soyad, T.C. kimlik no ve adresiniz",true)]},
    tr_ayipli_mal:{ic:"📦",n:"Ayıplı Mal Bildirimi (6502 m.11 — seçimlik haklar)",cat:"tr_tuketici",tier:"standard",q:[Pe("satici","Satıcı: unvan ve adres",true),Pe("urun","Ürün, fiyat, satın alma tarihi/fiş no",true),Pe("ayip","Ayıbın tarifi",true),Pe("hak","Kullanılan seçimlik hak",true,["Ücretsiz onarım","Ayıpsız misliyle değişim","Bedel iadesi (sözleşmeden dönme)","Bedel indirimi"]),Pe("iletisim","Ad, soyad ve adresiniz",true)]},
    tr_cayma_mesafeli:{ic:"🛒",n:"Mesafeli Satışta Cayma (14 gün, 6502 m.48)",cat:"tr_tuketici",tier:"einfach",q:[Pe("satici","Satıcı: unvan ve adres/e-posta",true),Pe("siparis","Sipariş no / tarih",true),Pe("teslim","Teslim tarihi (süre: 14 gün)",true),Pe("iade","İade şekli",false,["Kargo ile","Satıcı alacak"]),Pe("iletisim","Ad, soyad ve adresiniz",true)]},
    tr_haksiz_ucret:{ic:"🧾",n:"Haksız Fatura/Ücret İadesi Talebi",cat:"tr_tuketici",tier:"einfach",q:[Pe("kurum","Kurum/firma: unvan ve adres",true),Pe("fatura","Fatura/işlem (dönem, tutar)",true),Pe("gerekce","Neden haksız (kendi cümlelerinizle)",true),Pe("iletisim","Ad, soyad ve abone/müşteri no",true)]},

    /* Resmî Kurumlar (ek) */
    tr_kvkk_basvuru:{ic:"🔐",n:"KVKK Veri Sahibi Başvurusu (6698 m.11 — 30 gün)",cat:"tr_resmi",tier:"standard",q:[Pe("veri_sorumlusu","Veri sorumlusu: unvan ve adres",true),Pe("talep","Talebiniz",true,["Verilerimin işlenip işlenmediğini öğrenme","Bilgi talep etme","Silinmesini/yok edilmesini isteme","Düzeltilmesini isteme","Aktarıldığı 3. kişileri öğrenme"]),Pe("aciklama","Açıklama (hangi veriler, hangi işlem)",true),Pe("iletisim","Ad, soyad, T.C. kimlik no ve adresiniz",true)]},
    tr_cimer:{ic:"🏛️",n:"CİMER Başvurusu",cat:"tr_resmi",tier:"einfach",q:[Pe("kurum","Şikâyet/talep edilen kurum",true),Pe("konu","Konu ve yaşananlar (kendi cümlelerinizle)",true),Pe("talep","Talebiniz",true),Pe("iletisim","Ad, soyad, T.C. kimlik no",true)]},
    tr_bilgi_edinme:{ic:"🗂️",n:"Bilgi Edinme Başvurusu (4982 — 15 iş günü)",cat:"tr_resmi",tier:"einfach",q:[Pe("kurum","Kurum: ad ve adres",true),Pe("bilgi","İstenen bilgi/belge (olabildiğince net)",true),Pe("teslim","Teslim şekli",false,["E-posta","Posta","Elden"]),Pe("iletisim","Ad, soyad, T.C. kimlik no ve adresiniz",true)]},
    tr_vergi_taksit:{ic:"🧾",n:"Vergi Borcu Tecil/Taksitlendirme (6183 m.48)",cat:"tr_resmi",tier:"standard",q:[Pe("dairesi","Vergi dairesi",true),Pe("borc","Borç türü ve tutarı",true),Pe("teklif","Taksit önerisi (ay sayısı)",true),Pe("gerekce","Çok zor durum gerekçesi (kısa)",true),Pe("iletisim","Ad, soyad, T.C./vergi kimlik no",true)]},
    tr_idari_itiraz:{ic:"⚖️",n:"İdari İşleme İtiraz (İYUK m.11 — 60 gün)",cat:"tr_resmi",tier:"komplex",q:[Pe("idare","İşlemi yapan idare: ad ve adres",true),Pe("islem","İşlem (tarih, sayı, konu)",true),Pe("teblig","Tebliğ/öğrenme tarihi (dava süresi: 60 gün)",true),Pe("gerekce","İşlemin hukuka aykırılık gerekçesi (kendi cümlelerinizle)",true),Pe("talep","Talep",true,["İşlemin geri alınması","Kaldırılması","Değiştirilmesi"]),Pe("iletisim","Ad, soyad, T.C. kimlik no ve adresiniz",true)]},

    /* Aile Hukuku (ek) */
    tr_veraset_talep:{ic:"📜",n:"Veraset İlamı (Mirasçılık Belgesi) Talebi",cat:"tr_aile",tier:"standard",q:[Pe("murisci","Vefat eden: ad, soyad, ölüm tarihi",true),Pe("yakinlik","Yakınlığınız",true,["Eş","Çocuk","Anne/baba","Kardeş","Torun"]),Pe("mirascilar","Bilinen diğer mirasçılar",false),Pe("makam","Başvuru yeri",false,["Noter","Sulh Hukuk Mahkemesi"]),Pe("iletisim","Ad, soyad, T.C. kimlik no ve adresiniz",true)]},
    tr_nafaka_ihtar:{ic:"👨‍👧",n:"Ödenmeyen Nafaka İhtarı",cat:"tr_aile",tier:"standard",q:[Pe("yukumlu","Nafaka yükümlüsü: ad ve adres",true),Pe("karar","Dayanak karar (mahkeme, tarih, sayı)",true),Pe("borc","Ödenmeyen aylar ve tutar",true),Pe("sure","Verilen süre (ör. 7 gün; sonrası icra)",true),Pe("iletisim","Ad, soyad ve adresiniz",true)]},
    tr_isim_duzeltme:{ic:"✏️",n:"Ad/Soyad Düzeltme Dava Dilekçesi (TMK m.27)",cat:"tr_aile",tier:"komplex",q:[Pe("mevcut","Mevcut ad/soyad",true),Pe("istenen","İstenen ad/soyad",true),Pe("gerekce","Haklı sebep (kendi cümlelerinizle)",true),Pe("iletisim","Ad, soyad, T.C. kimlik no ve adresiniz",true)]},

    /* SGK ve Sağlık */
    tr_sgk_itiraz:{ic:"🏥",n:"SGK Kararına İtiraz",cat:"tr_sgk",tier:"komplex",q:[Pe("karar","SGK karar/yazı (tarih, sayı)",true),Pe("konu","Konu",true,["Emeklilik","Rapor parası (iş göremezlik)","Prim/hizmet dökümü","Sağlık gideri","Diğer"]),Pe("gerekce","Kararın neden hatalı olduğu",true),Pe("iletisim","Ad, soyad, T.C. kimlik no",true)]},
    tr_emeklilik_talep:{ic:"🧓",n:"Emeklilik Tahsis Talebi Yazısı",cat:"tr_sgk",tier:"standard",q:[Pe("sicil","SGK sicil no / T.C. kimlik no",true),Pe("durum","Sigortalılık durumu (4A/4B/4C, başlangıç)",true),Pe("talep","Talep (yaşlılık aylığı tahsisi vb.)",true),Pe("iletisim","Ad, soyad ve adresiniz",true)]},
    tr_rapor_itiraz:{ic:"📋",n:"Sağlık Kurulu Raporuna İtiraz",cat:"tr_sgk",tier:"standard",q:[Pe("hastane","Raporu veren hastane ve rapor tarihi/sayısı",true),Pe("sonuc","Rapor sonucu ve itiraz nedeni",true),Pe("iletisim","Ad, soyad, T.C. kimlik no",true)]},
    tr_hastane_iade:{ic:"🩺",n:"Hastane Fazla Ücret İadesi Talebi",cat:"tr_sgk",tier:"einfach",q:[Pe("hastane","Hastane: ad ve adres",true),Pe("islem","İşlem (tarih, tedavi, ödenen tutar — fatura ekli)",true),Pe("gerekce","Neden fazla/haksız (SUT farkı, bilgilendirme yok vb.)",true),Pe("iban","İade için IBAN",true),Pe("iletisim","Ad, soyad, T.C. kimlik no",true)]},

    /* Araç ve Trafik */
    tr_arac_satis:{ic:"🚗",n:"Araç Satış Sözleşmesi (ön anlaşma)",cat:"tr_arac",tier:"standard",q:[Pe("rol","Sıfatınız",true,["Satıcı","Alıcı"]),Pe("taraf","Karşı taraf: ad, T.C. kimlik no, adres",true),Pe("arac","Marka, model, plaka, şasi no",true),Pe("km","Model yılı ve kilometre",true),Pe("bedel","Satış bedeli ve ödeme şekli",true),Pe("not","NOT: Resmî devir YALNIZCA noterde yapılır — bu belge ön anlaşma/kaparo içindir",false)]},
    tr_trafik_ceza_itiraz:{ic:"🚦",n:"Trafik Cezasına İtiraz (15 gün — Sulh Ceza)",cat:"tr_arac",tier:"komplex",q:[Pe("tutanak","Ceza tutanağı (tarih, sayı, tutar)",true),Pe("teblig","Tebliğ tarihi (süre: 15 gün!)",true),Pe("plaka","Plaka",true),Pe("gerekce","İtiraz gerekçesi (kendi cümlelerinizle)",true),Pe("iletisim","Ad, soyad, T.C. kimlik no ve adresiniz",true)]},
    tr_kasko_hasar:{ic:"💥",n:"Kasko/Trafik Sigortası Hasar Bildirimi",cat:"tr_arac",tier:"einfach",q:[Pe("sigorta","Sigorta şirketi ve poliçe no",true),Pe("olay","Olay (tarih, yer, oluş şekli)",true),Pe("hasar","Araçtaki hasar",true),Pe("karsi","Karşı taraf (ad, plaka, sigorta — varsa)",false),Pe("iletisim","Ad, soyad ve adresiniz",true)]},
    tr_deger_kaybi:{ic:"📉",n:"Araç Değer Kaybı Talebi (sigortaya başvuru)",cat:"tr_arac",tier:"standard",q:[Pe("sigorta","Karşı tarafın sigorta şirketi ve poliçe/hasar dosya no",true),Pe("kaza","Kaza (tarih, yer; kusur durumu)",true),Pe("arac","Aracınız (marka, model, yıl, km)",true),Pe("talep","Talep edilen değer kaybı (ekspertiz varsa tutar)",false),Pe("iban","Ödeme için IBAN",true),Pe("iletisim","Ad, soyad, T.C. kimlik no",true)]},

    /* Banka ve Finans */
    tr_hesap_kapatma:{ic:"🏦",n:"Banka Hesabı Kapatma Talebi",cat:"tr_banka",tier:"einfach",q:[Pe("banka","Banka ve şube",true),Pe("hesap","Kapatılacak hesap/IBAN",true),Pe("bakiye","Kalan bakiyenin aktarılacağı IBAN",true),Pe("iletisim","Ad, soyad, T.C. kimlik no",true)]},
    tr_kredi_erken:{ic:"💳",n:"Kredi Erken Kapama Talebi (6502 m.37 indirim)",cat:"tr_banka",tier:"standard",q:[Pe("banka","Banka: ad ve şube",true),Pe("kredi","Kredi sözleşme no / tarihi",true),Pe("kapama","Kapama",true,["Tamamı","Kısmi (tutar yazın)"]),Pe("indirim","Faiz/komisyon indirimli kapama dökümü istensin",false,["Evet","Hayır"]),Pe("iletisim","Ad, soyad, T.C. kimlik no",true)]},
    tr_haksiz_islem:{ic:"🧾",n:"Yetkisiz Kart İşlemi İtirazı",cat:"tr_banka",tier:"standard",q:[Pe("banka","Banka/kart kuruluşu",true),Pe("islem","İşlem (tarih, tutar, işyeri)",true),Pe("neden","Neden",true,["Hiç onaylamadım","Tutar hatalı","Mükerrer çekim","Dolandırıcılık/phishing"]),Pe("kart","Kart iptal edildi mi?",false,["Evet","Hayır"]),Pe("iletisim","Ad, soyad, T.C. kimlik no",true)]},
    tr_dosya_masrafi:{ic:"💶",n:"Haksız Dosya Masrafı/Ücret İadesi",cat:"tr_banka",tier:"einfach",q:[Pe("banka","Banka: ad ve şube",true),Pe("kredi","Kredi/işlem (tarih, sözleşme no)",true),Pe("masraf","İadesi istenen kalemler ve tutarlar",true),Pe("iban","İade için IBAN",true),Pe("iletisim","Ad, soyad, T.C. kimlik no",true)]}
  };

  function boot(){
    try{
      if(typeof DOCS==="undefined"||typeof CATS==="undefined") return false;
      for(var k in E){ if(!DOCS[k]) DOCS[k]=E[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=E[k].tier||"standard"; }
      for(var nc in NEWCATS){ if(!CATS[nc]) CATS[nc]={n:NEWCATS[nc].n,ic:NEWCATS[nc].ic,docs:[],country:"TR"}; if(!CATS[nc].docs) CATS[nc].docs=[]; }
      for(var k2 in E){ var c2=E[k2].cat; if(CATS[c2]&&CATS[c2].docs&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in NEWCATS) CAT_LABELS[cl]=NEWCATS[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in NEWCATS){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in E){ var c3=E[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:E[k3].ic,t:E[k3].n,tier:E[k3].tier||"standard"}); } }
      }
      return true;
    }catch(e){ return false; }
  }
  var tries=0;
  (function run(){ if(boot()) return; if(tries++<40) setTimeout(run,500); })();
})();}catch(e){}
</script>
HTML;

$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi.\n");
$src = substr($src,0,$pos).$bundle."\n".substr($src,$pos);
echo "  ✓ TR +36 belge, +4 kategori eklendi\n";

$tmp = tempnam(sys_get_temp_dir(),'te').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-trexp2-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
$nTR = preg_match_all('/\btr_[a-z0-9_]+:\{ic:/', $src, $m);
echo "\n✓ CH_TR_EXPAND2 uygulandi. Yeni toplam: TR=$nTR belge tanimi (+4 kategori).\n";
