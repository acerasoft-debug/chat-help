<?php
/**
 * ChatHelp — TÜRKİYE HUKUKU MODÜLÜ (tek seferde sağlam: deferred yükleme, Fransa'daki "0 documents"
 * hatasından ders çıkarılarak — senkron enjeksiyon YERİNE doğrudan DOCS/CATS hazır olunca yükleyen yöntem)
 * ------------------------------------------------------------------------------------------------------
 *  • ~65 Türkçe belge, 6 kategori (Fesih/İptal, Konut&Kira, İş Hukuku, Tüketici&Uyuşmazlık, Sözleşmeler, Resmi Kurumlar)
 *  • CC_DATA'ya Türkiye eklenir (ülke seçicide görünür)
 *  • GENELLEŞTİRİLMİŞ kategori filtresi: showAllCats artık DE/FR/TR arasında doğru ayrım yapar
 *    (Fransa'nın eski ikili DE-FR filtresinin ÜSTÜNE güvenle sarılır, onu bozmaz)
 *  • Welcome grid: TR kartları eklenir + tüm .wcat'lerin görünürlüğü ülkeye göre ayarlanır
 *  • setCountry('TR') -> dil otomatik tr, IP tespiti zaten geo.php'de TR'yi destekliyor
 *
 * KULLANIM: chat-help.com/chat/apply-turkiye.php -> opcache-reset.php. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-turkiye BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_TR_MODULE")!==false) exit("Zaten ekli (CH_TR_MODULE).\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-tr-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_TR_MODULE — Türkiye hukuku modülü (deferred: DOCS/CATS/CAT_DOCS hazır olunca yüklenir) */
try{(function(){
  function Pt(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var TRC={
    tr_fesih:{n:"Fesih ve İptaller",ic:"📄"},
    tr_konut:{n:"Konut ve Kira",ic:"🏠"},
    tr_is:{n:"İş Hukuku",ic:"💼"},
    tr_tuketici:{n:"Tüketici ve Uyuşmazlık",ic:"🛒"},
    tr_sozlesme:{n:"Sözleşmeler",ic:"📝"},
    tr_resmi:{n:"Resmi Kurumlar",ic:"🏛️"}
  };
  var TR_ORDER=["tr_fesih","tr_konut","tr_is","tr_tuketici","tr_sozlesme","tr_resmi"];

  var T={
    /* Fesih ve İptaller */
    tr_fesih_abonelik:{ic:"📱",n:"Abonelik İptali (GSM/İnternet/TV)",cat:"tr_fesih",tier:"einfach",q:[Pt("firma","Firma: ad ve adres",true),Pt("musteri_no","Müşteri/abone numarası",true),Pt("tur","Abonelik türü",true,["GSM hattı","İnternet","TV/Kablo","Sabit hat"]),Pt("tarih","İptal edilecek tarih",false),Pt("gerekce","İptal gerekçesi (opsiyonel)",false),Pt("iletisim","Ad, soyad ve adresiniz",true)]},
    tr_fesih_sigorta:{ic:"🛡️",n:"Sigorta Poliçesi İptali",cat:"tr_fesih",tier:"einfach",q:[Pt("sirket","Sigorta şirketi: ad ve adres",true),Pt("police_no","Poliçe numarası",true),Pt("tur","Sigorta türü",true,["Kasko","Trafik","Konut/DASK","Sağlık","Hayat"]),Pt("tarih","İptal tarihi",false),Pt("iletisim","Ad, soyad ve adresiniz",true)]},
    tr_fesih_uyelik:{ic:"🏋️",n:"Üyelik İptali (Spor Salonu/Kulüp)",cat:"tr_fesih",tier:"einfach",q:[Pt("kurum","Kurum: ad ve adres",true),Pt("uye_no","Üyelik numarası",false),Pt("tarih","İptal edilecek tarih",true),Pt("gerekce","Gerekçe (opsiyonel)",false),Pt("iletisim","Ad, soyad ve adresiniz",true)]},
    tr_fesih_kira:{ic:"🏠",n:"Kira Sözleşmesi Feshi (Kiracı)",cat:"tr_fesih",tier:"einfach",q:[Pt("ev_sahibi","Ev sahibi/yönetici: ad ve adres",true),Pt("adres","Kiralanan taşınmazın adresi",true),Pt("tahliye_tarihi","Tahliye tarihi",true),Pt("gerekce","Fesih gerekçesi (opsiyonel)",false),Pt("iletisim","Ad, soyad ve iletişim bilgileriniz",true)]},
    tr_fesih_banka:{ic:"🏦",n:"Banka Hesabı Kapatma Talebi",cat:"tr_fesih",tier:"einfach",q:[Pt("banka","Banka: şube ve adres",true),Pt("hesap_no","Hesap/IBAN numarası",true),Pt("gerekce","Gerekçe (opsiyonel)",false),Pt("bakiye","Bakiye transferi için IBAN (opsiyonel)",false),Pt("iletisim","Ad, soyad ve adresiniz",true)]},
    tr_fesih_kredi_karti:{ic:"💳",n:"Kredi Kartı İptal Talebi",cat:"tr_fesih",tier:"einfach",q:[Pt("banka","Banka: ad ve adres",true),Pt("kart_no","Kart numarasının son 4 hanesi",false),Pt("gerekce","Gerekçe (opsiyonel)",false),Pt("iletisim","Ad, soyad ve adresiniz",true)]},

    /* Konut ve Kira */
    tr_kira_sozlesme:{ic:"🏠",n:"Kira Sözleşmesi (Konut)",cat:"tr_konut",tier:"standard",q:[Pt("rol","Rolünüz",true,["Kiraya Veren","Kiracı"]),Pt("diger_taraf","Diğer taraf: ad ve adres",true),Pt("adres","Kiralanan taşınmazın adresi",true),Pt("m2","Metrekare ve oda sayısı",false),Pt("baslangic","Kira başlangıç tarihi",true),Pt("sure","Kira süresi (ay/yıl)",false),Pt("kira_bedeli","Aylık kira bedeli (TL)",true),Pt("depozito","Depozito tutarı (TL)",false),Pt("aidatlar","Aidat/ortak gider (TL, varsa)",false)]},
    tr_kira_artis_itiraz:{ic:"📈",n:"Kira Artışına İtiraz",cat:"tr_konut",tier:"standard",q:[Pt("ev_sahibi","Ev sahibi: ad ve adres",true),Pt("adres","Taşınmaz adresi",true),Pt("eski_kira","Mevcut kira bedeli (TL)",true),Pt("talep_edilen","Talep edilen yeni kira (TL)",true),Pt("itiraz_gerekce","İtiraz gerekçeniz",true)]},
    tr_depozito_iade:{ic:"💶",n:"Depozito İade Talebi",cat:"tr_konut",tier:"standard",q:[Pt("ev_sahibi","Ev sahibi: ad ve adres",true),Pt("adres","Taşınmaz adresi",true),Pt("tahliye_tarihi","Tahliye tarihi",true),Pt("depozito_tutari","Depozito tutarı (TL)",true),Pt("iade_iban","İade için IBAN",false)]},
    tr_tahliye_taahhut:{ic:"📋",n:"Tahliye Taahhütnamesi",cat:"tr_konut",tier:"standard",q:[Pt("kiraci","Kiracı: ad ve adres",true),Pt("ev_sahibi","Ev sahibi: ad ve adres",true),Pt("adres","Taşınmaz adresi",true),Pt("tahliye_tarihi","Taahhüt edilen tahliye tarihi",true)]},
    tr_ayip_bildirim:{ic:"🔧",n:"Ayıp/Arıza Bildirimi (Kiralanan)",cat:"tr_konut",tier:"standard",q:[Pt("ev_sahibi","Ev sahibi/yönetici: ad ve adres",true),Pt("adres","Taşınmaz adresi",true),Pt("sorun","Ayıp/arızanın tanımı",true),Pt("tarih","Fark edildiği tarih",false),Pt("talep","Talebiniz",true,["Onarım","Kira indirimi","Her ikisi"])]},
    tr_ortak_gider_itiraz:{ic:"🧾",n:"Ortak Gider (Aidat) İtirazı",cat:"tr_konut",tier:"standard",q:[Pt("yonetim","Site/apartman yönetimi",true),Pt("adres","Taşınmaz adresi",true),Pt("donem","İtiraz edilen dönem",true),Pt("tutar","İtiraz edilen tutar (TL)",true),Pt("gerekce","İtiraz gerekçeniz",true)]},
    tr_komsuluk_sikayet:{ic:"🔊",n:"Komşuluk/Gürültü Şikayeti",cat:"tr_konut",tier:"standard",q:[Pt("komsu","Şikayet edilen komşu/daire",false),Pt("sorun","Şikayet konusu (gürültü, koku vb.)",true),Pt("sıklik","Sıklık/saatler",false),Pt("talep","Talebiniz",true)]},
    tr_su_basmasi:{ic:"💧",n:"Su Baskını/Hasar Bildirimi",cat:"tr_konut",tier:"standard",q:[Pt("ev_sahibi","Ev sahibi/sigorta: ad ve adres",true),Pt("adres","Taşınmaz adresi",true),Pt("tarih","Olay tarihi",true),Pt("hasar","Hasarın tanımı",true),Pt("talep","Talebiniz",true)]},

    /* İş Hukuku */
    tr_is_sozlesmesi:{ic:"📄",n:"İş Sözleşmesi (Belirsiz Süreli)",cat:"tr_is",tier:"komplex",q:[Pt("rol","Rolünüz",true,["İşveren","İşçi"]),Pt("diger_taraf","Diğer taraf: ad ve adres",true),Pt("pozisyon","Görev/pozisyon",true),Pt("baslangic","İşe başlama tarihi",true),Pt("calisma_yeri","Çalışma yeri",false),Pt("ucret","Brüt aylık ücret (TL)",true),Pt("calisma_suresi","Haftalık çalışma süresi",false),Pt("deneme_suresi","Deneme süresi",false,["Yok","1 ay","2 ay"])]},
    tr_istifa:{ic:"👋",n:"İstifa Dilekçesi",cat:"tr_is",tier:"einfach",q:[Pt("isveren","İşveren: ad ve adres",true),Pt("pozisyon","Pozisyonunuz",true),Pt("son_gun","Son çalışma günü",true),Pt("ihbar_suresi","İhbar süresine uyuluyor mu?",false,["Evet","Hayır, haklı nedenle"]),Pt("gerekce","Gerekçe (opsiyonel)",false)]},
    tr_is_fesih_bildirimi:{ic:"⚠️",n:"İş Akdi Fesih Bildirimi (İşveren)",cat:"tr_is",tier:"standard",q:[Pt("calisan","Çalışan: ad ve adres",true),Pt("pozisyon","Pozisyon",true),Pt("fesih_tarihi","Fesih tarihi",true),Pt("gerekce","Fesih gerekçesi",true,["Ekonomik nedenler","Performans","Davranış","Diğer"]),Pt("ihbar_tazminat","İhbar tazminatı ödenecek mi?",false,["Evet","Hayır"])]},
    tr_kidem_ihbar_talep:{ic:"💰",n:"Kıdem ve İhbar Tazminatı Talebi",cat:"tr_is",tier:"komplex",q:[Pt("isveren","İşveren: ad ve adres",true),Pt("pozisyon","Pozisyonunuz",true),Pt("baslangic","İşe başlama tarihi",true),Pt("ayrilma_tarihi","İşten ayrılma tarihi",true),Pt("ayrilma_nedeni","Ayrılma nedeni",true),Pt("son_ucret","Son brüt aylık ücret (TL)",true)]},
    tr_fazla_mesai:{ic:"⏰",n:"Fazla Mesai Ücreti Talebi",cat:"tr_is",tier:"standard",q:[Pt("isveren","İşveren: ad ve adres",true),Pt("pozisyon","Pozisyonunuz",true),Pt("donem","İlgili dönem",true),Pt("saat_tahmini","Tahmini fazla mesai saati",true),Pt("aciklama","Açıklama/kanıt (varsa)",false)]},
    tr_mobbing_sikayet:{ic:"🚨",n:"Mobbing/Psikolojik Taciz Şikayeti",cat:"tr_is",tier:"komplex",q:[Pt("muhatap","Şikayet edilecek makam (İK, işveren)",true),Pt("faille","İddia edilen kişi(ler)",false),Pt("olaylar","Olayların tanımı (tarih, kişi, içerik)",true),Pt("tanik","Tanıklar (varsa)",false),Pt("talep","Talebiniz",true)]},
    tr_ibraname_itiraz:{ic:"📜",n:"İbraname/İbra Belgesine İtiraz",cat:"tr_is",tier:"standard",q:[Pt("isveren","İşveren: ad ve adres",true),Pt("ibra_tarihi","İbranamenin tarihi",true),Pt("itiraz_gerekce","İtiraz gerekçeniz",true),Pt("talep","Talebiniz",true)]},
    tr_calisma_belgesi:{ic:"📄",n:"Çalışma/Deneyim Belgesi Talebi",cat:"tr_is",tier:"einfach",q:[Pt("isveren","İşveren: ad ve adres",true),Pt("pozisyon","Pozisyonunuz",true),Pt("calisma_tarihleri","Çalışma tarihleri (başlangıç-bitiş)",true)]},
    tr_ucretsiz_izin:{ic:"🏖️",n:"Ücretsiz İzin Talebi",cat:"tr_is",tier:"einfach",q:[Pt("isveren","İşveren/yönetici",true),Pt("tarih_araligi","İstenilen izin tarihleri",true),Pt("gerekce","Gerekçe",false)]},
    tr_hastalik_bildirim:{ic:"🏥",n:"İşe Devamsızlık/Hastalık Bildirimi",cat:"tr_is",tier:"einfach",q:[Pt("isveren","İşveren/yönetici",true),Pt("baslangic","Başlangıç tarihi",true),Pt("tahmini_sure","Tahmini süre",false)]},

    /* Tüketici ve Uyuşmazlık */
    tr_ayipli_mal:{ic:"🔍",n:"Ayıplı Mal/Hizmet Şikayeti",cat:"tr_tuketici",tier:"standard",q:[Pt("satici","Satıcı: ad ve adres",true),Pt("urun","Ürün/hizmet tanımı",true),Pt("satin_alma_tarihi","Satın alma tarihi",true),Pt("sorun","Ayıp/sorunun tanımı",true),Pt("talep","Talebiniz",true,["Onarım","Değişim","İade","Bedel indirimi"])]},
    tr_cayma_hakki:{ic:"🔄",n:"Cayma Hakkı Bildirimi",cat:"tr_tuketici",tier:"einfach",q:[Pt("satici","Satıcı: ad ve adres",true),Pt("siparis_no","Sipariş numarası",true),Pt("tarih","Sipariş/teslim tarihi",true),Pt("urunler","Cayılan ürünler",true),Pt("iade_iban","İade için IBAN (opsiyonel)",false)]},
    tr_tuketici_hakem:{ic:"⚖️",n:"Tüketici Hakem Heyeti Başvurusu",cat:"tr_tuketici",tier:"komplex",q:[Pt("satici","Şikayet edilen firma: ad ve adres",true),Pt("konu","Uyuşmazlık konusu",true),Pt("tutar","Parasal değer (TL)",true),Pt("olaylar","Olayların özeti",true),Pt("talep","Talebiniz",true),Pt("kanit","Elinizdeki belgeler (fatura, yazışma vb.)",false)]},
    tr_sozlesme_iptal_tuketici:{ic:"🚫",n:"Tüketici Sözleşmesi İptali",cat:"tr_tuketici",tier:"einfach",q:[Pt("firma","Firma: ad ve adres",true),Pt("sozlesme_no","Sözleşme numarası",true),Pt("iptal_gerekce","İptal gerekçesi",false),Pt("tarih","İptal tarihi",false)]},
    tr_haksiz_kesinti:{ic:"💳",n:"Haksız Kesinti/Ücret İtirazı",cat:"tr_tuketici",tier:"standard",q:[Pt("firma","Firma/banka: ad ve adres",true),Pt("kesinti_tutari","Kesinti tutarı (TL)",true),Pt("kesinti_tarihi","Kesinti tarihi",true),Pt("gerekce","İtiraz gerekçeniz",true)]},
    tr_ucak_iptal_tazminat:{ic:"✈️",n:"Uçuş İptal/Gecikme Tazminatı",cat:"tr_tuketici",tier:"standard",q:[Pt("havayolu","Havayolu şirketi",true),Pt("ucus_no","Uçuş numarası ve tarihi",true),Pt("sorun","Sorun",true,["İptal","Gecikme","Aşırı rezervasyon"]),Pt("sure","Gecikme süresi (varsa)",false),Pt("talep","Talebiniz",true)]},
    tr_abonelik_sozlesme_yenileme:{ic:"🔁",n:"Otomatik Yenilemeye İtiraz",cat:"tr_tuketici",tier:"einfach",q:[Pt("firma","Firma: ad ve adres",true),Pt("sozlesme_no","Sözleşme/abonelik numarası",true),Pt("konu","İtiraz konusu",true)]},
    tr_dolandiricilik_bildirim:{ic:"🎣",n:"Dolandırıcılık/Sahtecilik Bildirimi",cat:"tr_tuketici",tier:"standard",q:[Pt("konu","Dolandırıcılık türü",true),Pt("kim","Karşı taraf (site, kişi, firma)",false),Pt("olaylar","Olayların tanımı",true),Pt("zarar","Maddi zarar (TL, varsa)",false)]},

    /* Sözleşmeler */
    tr_satis_sozlesmesi:{ic:"🛒",n:"Satış Sözleşmesi (Özel Kişiler Arası)",cat:"tr_sozlesme",tier:"einfach",q:[Pt("rol","Rolünüz",true,["Satıcı","Alıcı"]),Pt("diger_taraf","Diğer taraf: ad ve adres",true),Pt("konu","Satış konusu (ürün tanımı)",true),Pt("bedel","Satış bedeli (TL)",true),Pt("teslim_tarihi","Teslim tarihi",false),Pt("garanti","Ayıba karşı garanti hariç mi?",false,["Evet","Hayır"])]},
    tr_borc_senedi:{ic:"💶",n:"Borç Senedi / Ödünç Sözleşmesi",cat:"tr_sozlesme",tier:"standard",q:[Pt("rol","Rolünüz",true,["Alacaklı","Borçlu"]),Pt("diger_taraf","Diğer taraf: ad ve adres",true),Pt("tutar","Borç tutarı (TL, rakam ve yazıyla)",true),Pt("verilis_tarihi","Ödünç veriliş tarihi",true),Pt("odeme_plani","Geri ödeme planı",true),Pt("faiz","Faiz uygulanacak mı?",false,["Evet","Hayır"])]},
    tr_vekaletname:{ic:"📝",n:"Vekaletname",cat:"tr_sozlesme",tier:"standard",q:[Pt("vekil","Vekil tayin edilen kişi: ad ve adres",true),Pt("konu","Vekaletin kapsamı/konusu",true),Pt("sure","Süre (opsiyonel)",false),Pt("vekaleti_veren","Vekalet verenin ad ve adresi",true)]},
    tr_kefaletname:{ic:"🤝",n:"Kefaletname",cat:"tr_sozlesme",tier:"komplex",q:[Pt("alacakli","Alacaklı: ad ve adres",true),Pt("asil_borclu","Asıl borçlu: ad ve adres",true),Pt("borc_tutari","Kefil olunan borç tutarı (TL)",true),Pt("kefalet_turu","Kefalet türü",false,["Adi kefalet","Müteselsil kefalet"]),Pt("kefil","Kefilin ad ve adresi",true)]},
    tr_hizmet_sozlesmesi:{ic:"🧰",n:"Hizmet Sözleşmesi",cat:"tr_sozlesme",tier:"standard",q:[Pt("hizmet_veren","Hizmet veren: ad ve adres",true),Pt("hizmet_alan","Hizmet alan: ad ve adres",true),Pt("hizmet_tanimi","Hizmetin tanımı",true),Pt("bedel","Hizmet bedeli (TL)",true),Pt("odeme_sekli","Ödeme şekli",false),Pt("sure","Sözleşme süresi",false)]},
    tr_gizlilik_sozlesmesi:{ic:"🔒",n:"Gizlilik Sözleşmesi (NDA)",cat:"tr_sozlesme",tier:"standard",q:[Pt("taraf2","Diğer taraf: ad ve adres",true),Pt("amac","İşbirliğinin amacı",true),Pt("gizli_bilgi","Gizli bilgi kapsamı",true),Pt("sure","Gizlilik süresi",false),Pt("ceza","Cezai şart uygulanacak mı?",false,["Evet","Hayır"])]},
    tr_hediye_sozlesmesi:{ic:"🎁",n:"Bağışlama (Hibe) Sözleşmesi",cat:"tr_sozlesme",tier:"standard",q:[Pt("bagislayan","Bağışlayan: ad ve adres",true),Pt("bagislanan","Bağışlanan: ad ve adres",true),Pt("konu","Bağışlanan şey/tutar",true),Pt("tarih","Bağışlama tarihi",false)]},
    tr_kira_ortagi_sozlesme:{ic:"🛏️",n:"Ev Arkadaşlığı (Kira Paylaşım) Sözleşmesi",cat:"tr_sozlesme",tier:"einfach",q:[Pt("ana_kiraci","Ana kiracı: ad ve adres",true),Pt("adres","Taşınmaz adresi",true),Pt("oda","Oda/alan tanımı",true),Pt("pay_tutari","Aylık pay tutarı (TL)",true),Pt("baslangic","Başlangıç tarihi",true)]},
    tr_esletirme_ortaklik:{ic:"🔗",n:"İş Ortaklığı (Adi Ortaklık) Sözleşmesi",cat:"tr_sozlesme",tier:"komplex",q:[Pt("ortaklar","Tüm ortaklar: ad ve adres",true),Pt("amac","Ortaklığın amacı",true),Pt("sermaye","Her ortağın sermaye katkısı",true),Pt("kar_zarar","Kâr/zarar paylaşımı",true),Pt("yonetim","Yönetim şekli",false)]},

    /* Resmi Kurumlar */
    tr_sgk_basvuru:{ic:"🏥",n:"SGK Başvuru/İtiraz Dilekçesi",cat:"tr_resmi",tier:"standard",q:[Pt("sgk_no","SGK sicil/TC kimlik numarası",false),Pt("konu","Başvuru/itiraz konusu",true,["Sigortalılık tespiti","Prim itirazı","Sağlık hizmeti","Emeklilik"]),Pt("aciklama","Açıklama",true)]},
    tr_vergi_itiraz:{ic:"🧾",n:"Vergi Dairesi İtiraz/Uzlaşma Talebi",cat:"tr_resmi",tier:"standard",q:[Pt("vergi_dairesi","Vergi dairesi adı",true),Pt("vergi_no","Vergi kimlik numarası",false),Pt("konu","İtiraz konusu (ceza, tarhiyat vb.)",true),Pt("tutar","Tutar (TL)",false),Pt("gerekce","Gerekçeniz",true)]},
    tr_vergi_taksit:{ic:"📅",n:"Vergi Borcu Taksitlendirme Talebi",cat:"tr_resmi",tier:"einfach",q:[Pt("vergi_dairesi","Vergi dairesi adı",true),Pt("borc_tutari","Borç tutarı (TL)",true),Pt("taksit_sayisi","Talep edilen taksit sayısı",false)]},
    tr_adres_degisikligi:{ic:"🏠",n:"Adres Değişikliği Bildirimi (Nüfus Müdürlüğü)",cat:"tr_resmi",tier:"einfach",q:[Pt("eski_adres","Eski adres",true),Pt("yeni_adres","Yeni adres",true),Pt("tarih","Taşınma tarihi",true)]},
    tr_iskur_basvuru:{ic:"💼",n:"İŞKUR Başvuru/İşsizlik Maaşı Talebi",cat:"tr_resmi",tier:"standard",q:[Pt("son_isveren","Son işveren: ad ve adres",true),Pt("isten_ayrilma_tarihi","İşten ayrılma tarihi",true),Pt("ayrilma_nedeni","Ayrılma nedeni",true),Pt("prim_gun","Tahmini prim gün sayısı (opsiyonel)",false)]},
    tr_iskur_itiraz:{ic:"⚖️",n:"İŞKUR Ret Kararına İtiraz",cat:"tr_resmi",tier:"standard",q:[Pt("basvuru_no","Başvuru numarası (varsa)",false),Pt("ret_tarihi","Ret kararı tarihi",true),Pt("itiraz_gerekce","İtiraz gerekçeniz",true)]},
    tr_trafik_ceza_itiraz:{ic:"🚗",n:"Trafik Cezasına İtiraz",cat:"tr_resmi",tier:"standard",q:[Pt("ceza_tutanak_no","Ceza tutanak numarası",true),Pt("tarih","Ceza tarihi",true),Pt("plaka","Araç plakası",false),Pt("itiraz_gerekce","İtiraz gerekçeniz",true)]},
    tr_belediye_sikayet:{ic:"🏛️",n:"Belediyeye Şikayet/Talep Dilekçesi",cat:"tr_resmi",tier:"einfach",q:[Pt("belediye","İlgili belediye",true),Pt("konu","Şikayet/talep konusu",true),Pt("adres","İlgili adres (varsa)",false),Pt("aciklama","Detaylı açıklama",true)]},
    tr_evlilik_kaydi:{ic:"💍",n:"Evlilik Kaydı/Değişikliği Başvurusu",cat:"tr_resmi",tier:"einfach",q:[Pt("esler","Eşlerin ad ve soyadları",true),Pt("evlilik_tarihi","Evlilik tarihi",true),Pt("nufus_muduru","İlgili Nüfus Müdürlüğü",false)]},
    tr_dogum_bildirimi:{ic:"👶",n:"Doğum Bildirimi/Nüfus Kaydı Başvurusu",cat:"tr_resmi",tier:"einfach",q:[Pt("cocuk_adi","Çocuğun adı",false),Pt("dogum_tarihi","Doğum tarihi",true),Pt("dogum_yeri","Doğum yeri",true),Pt("ebeveynler","Ebeveynlerin ad ve soyadları",true)]},
    tr_emeklilik_basvuru:{ic:"👴",n:"Emeklilik Başvurusu (SGK)",cat:"tr_resmi",tier:"standard",q:[Pt("sgk_no","SGK sicil/TC kimlik numarası",false),Pt("hizmet_yillari","Toplam hizmet yılı (tahmini)",false),Pt("basvuru_tarihi","Başvuru tarihi",false)]},
    tr_adli_sicil:{ic:"📁",n:"Adli Sicil Kaydı Talebi",cat:"tr_resmi",tier:"einfach",q:[Pt("ad_soyad","Ad, soyad ve doğum tarihi",true),Pt("amac","Talep amacı (opsiyonel)",false)]},
    tr_dilekce_genel:{ic:"✍️",n:"Genel Dilekçe (Kamu Kurumuna)",cat:"tr_resmi",tier:"einfach",q:[Pt("kurum","İlgili kurum/makam",true),Pt("konu","Dilekçe konusu",true),Pt("aciklama","Detaylı açıklama",true),Pt("talep","Talebiniz",true)]}
  };

  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      for(var k in T){ if(!DOCS[k]) DOCS[k]=T[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=T[k].tier||"standard"; }
      if(typeof CATS!=="undefined"&&CATS){
        for(var ck in TRC){ if(!CATS[ck]) CATS[ck]={n:TRC[ck].n,ic:TRC[ck].ic,docs:[],country:"TR"}; if(!CATS[ck].docs) CATS[ck].docs=[]; }
        for(var k2 in T){ var c2=T[k2].cat; if(CATS[c2]&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
        for(var gc in CATS){ if(!CATS[gc].country) CATS[gc].country="DE"; }
      }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in TRC) CAT_LABELS[cl]=TRC[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in TRC){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in T){ var c3=T[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:T[k3].ic,t:T[k3].n,tier:T[k3].tier||"standard"}); } }
      }
      if(typeof CC_DATA!=="undefined" && CC_DATA && !CC_DATA.TR){
        CC_DATA.TR = {n:"Türkiye", f:"🇹🇷", l:"tr", law:"TR"};
      }
      return (typeof DOCS!=="undefined" && DOCS.tr_dilekce_genel)?true:false;
    }catch(e){ return false; }
  }

  /* Genelleştirilmiş çok-ülkeli kategori filtresi: showAllCats'i (varsa Fransa sarmalayıcısının ÜSTÜNE) sarar */
  function installMultiCountryFilter(){
    try{
      if(typeof showAllCats!=="function" || showAllCats.__multiCountry) return;
      var _sac=showAllCats;
      var w=function(){
        try{
          if(typeof CATS==="undefined") return _sac.apply(this,arguments);
          var cur=(typeof CC!=="undefined"?CC:"DE");
          var hid={};
          for(var k in CATS){
            var catCountry=(CATS[k]&&CATS[k].country)||"DE";
            if(catCountry!==cur){ hid[k]=CATS[k]; delete CATS[k]; }
          }
          try{ return _sac.apply(this,arguments); } finally { for(var h in hid) CATS[h]=hid[h]; }
        }catch(e){ return _sac.apply(this,arguments); }
      };
      w.__multiCountry=1; showAllCats=w; if(typeof window!=="undefined") window.showAllCats=w;
    }catch(e){}
  }

  /* Welcome grid: TR kartları ekle + tüm .wcat'lerin görünürlüğünü ülkeye göre ayarla (genelleştirilmiş) */
  function cardCountryOf(el){
    if(el.getAttribute("data-cccountry")) return el.getAttribute("data-cccountry");
    if(el.getAttribute("data-frcat")) return "FR";
    if(el.getAttribute("data-trcat")) return "TR";
    return "DE";
  }
  function trGrid(){
    try{
      var grid=document.querySelector(".wcat-grid"); if(!grid) return;
      var cur=(typeof CC!=="undefined"?CC:"DE");
      if(cur==="TR"){
        TR_ORDER.forEach(function(ck){
          if(grid.querySelector('[data-trcat="'+ck+'"]')) return;
          var c=TRC[ck]; var el=document.createElement("div"); el.className="wcat"; el.setAttribute("data-trcat",ck); el.setAttribute("data-cccountry","TR");
          el.onclick=function(){ try{ if(typeof showCat==="function") showCat(ck); }catch(e){} };
          var n=(typeof CATS!=="undefined"&&CATS[ck]&&CATS[ck].docs)?CATS[ck].docs.length:0;
          el.innerHTML='<div class="wcat-ic">'+c.ic+'</div><div class="wcat-t">'+c.n+'</div><div class="wcat-s">'+n+' belge</div>';
          grid.appendChild(el);
        });
      }
      grid.querySelectorAll(".wcat").forEach(function(el){
        el.style.display=(cardCountryOf(el)===cur)?"":"none";
      });
    }catch(e){}
  }

  function installCountryHook(){
    try{
      if(typeof setCountry!=="function" || setCountry.__tr) return;
      var _sc=setCountry;
      var s=function(cc){
        var r; try{ r=_sc.apply(this,arguments); }catch(e){}
        try{ if(cc==="TR" && typeof dLang!=="undefined" && !localStorage.getItem("ch_lm")){ dLang="tr"; localStorage.setItem("ch_uilang","tr"); } }catch(e){}
        try{ trGrid(); }catch(e){}
        return r;
      };
      s.__tr=1; setCountry=s; if(typeof window!=="undefined") window.setCountry=s;
    }catch(e){}
  }

  var done=false, ticks=0;
  function run(){
    if(!done){ if(inject()) done=true; }
    installMultiCountryFilter(); installCountryHook();
    try{ trGrid(); }catch(e){}
    if(ticks++<40) setTimeout(run, 500);
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src!==$start){ file_put_contents($file,$src); echo "Turkiye modulu eklendi (CH_TR_MODULE) - ~65 belge, 6 kategori, genellestirilmis ulke filtresi.\n"; }
else echo "degisiklik yok\n";
echo "\nStripe: yeni belgeler tier'li -> odeme otomatik.\n";
echo "SONRA: opcache-reset.php. SIL: rm apply-turkiye.php\n";
echo "Test: TR Turkiye sec (veya Turk IP) -> kategoriler Turkce -> bir belge uret.\n";
