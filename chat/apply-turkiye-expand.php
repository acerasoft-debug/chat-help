<?php
/**
 * ChatHelp — TÜRKİYE KATALOG GENİŞLETME (+44 belge, 1 yeni kategori: Aile Hukuku)
 * --------------------------------------------------------------------------------
 *  Mevcut 6 TR kategorisine yeni belgeler ekler + "Aile Hukuku" kategorisi açar
 *  (yeni kategori enjeksiyonu apply-turkiye-schengen.php ile aynı kanıtlanmış desen).
 *  Öne çıkanlar: KVKK başvurusu, CİMER, Bilgi Edinme, iş kazası bildirimi, işe iade,
 *  ikale, araç satış protokolü, garanti onarımı, anlaşmalı boşanma protokolü, nafaka.
 *  Tüm belgeler tier'lı -> Stripe ödeme otomatik. Belge dili/hukuku CC üzerinden
 *  otomatik (CH_TR_LAWSYS + CH_TR_DOCLANG zaten api.php'de).
 *
 * KULLANIM: chat-help.com/chat/apply-turkiye-expand.php -> opcache-reset.php. SİL.
 * GEREKLİ: apply-turkiye.php önce çalıştırılmış olmalı (CH_TR_MODULE, tr_* kategoriler).
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-turkiye-expand BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_TR_EXPAND")!==false) exit("Zaten ekli (CH_TR_EXPAND).\n");
if(strpos($src,"CH_TR_MODULE")===false) exit("CH_TR_MODULE yok — once apply-turkiye.php calistir.\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-trexpand-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_TR_EXPAND — Türkiye katalog genişletme (+44 belge, yeni kategori: Aile Hukuku; deferred yükleme) */
try{(function(){
  function Pe(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}

  var NEWCATS={
    tr_aile:{n:"Aile Hukuku",ic:"👨‍👩‍👧"}
  };

  var E={
    /* Fesih ve İptaller */
    tr_fesih_elektrik:{ic:"⚡",n:"Elektrik/Doğalgaz Aboneliği İptali",cat:"tr_fesih",tier:"einfach",q:[Pe("firma","Dağıtım/tedarik şirketi: ad ve adres",true),Pe("abone_no","Abone/tesisat numarası",true),Pe("adres","Abonelik adresi",true),Pe("tarih","İptal edilecek tarih",true),Pe("sayac","Son sayaç endeksi (opsiyonel)",false),Pe("iletisim","Ad, soyad ve adresiniz",true)]},
    tr_fesih_su:{ic:"💧",n:"Su Aboneliği İptali",cat:"tr_fesih",tier:"einfach",q:[Pe("idare","Su idaresi (İSKİ, ASKİ vb.)",true),Pe("abone_no","Abone numarası",true),Pe("adres","Abonelik adresi",true),Pe("tarih","İptal/taşınma tarihi",true),Pe("iletisim","Ad, soyad ve adresiniz",true)]},
    tr_fesih_dijital:{ic:"📺",n:"Dijital Platform Aboneliği İptali",cat:"tr_fesih",tier:"einfach",q:[Pe("platform","Platform (Netflix, Spotify, BluTV vb.)",true),Pe("hesap","Hesap e-postası/kullanıcı adı",true),Pe("tarih","İptal edilecek tarih",false),Pe("iade","Kullanılmayan dönem iadesi istensin mi?",false,["Evet","Hayır"]),Pe("iletisim","Ad ve soyadınız",true)]},
    tr_fesih_kurs:{ic:"🎓",n:"Kurs/Dershane Kaydı İptali",cat:"tr_fesih",tier:"einfach",q:[Pe("kurum","Kurum: ad ve adres",true),Pe("kayit_no","Kayıt/sözleşme numarası",false),Pe("gerekce","İptal gerekçesi",false),Pe("iade","Ödenen ücretin iadesi istensin mi?",false,["Evet","Hayır"]),Pe("iletisim","Ad, soyad ve adresiniz",true)]},
    tr_fesih_odeme_talimati:{ic:"🏦",n:"Otomatik Ödeme Talimatı İptali",cat:"tr_fesih",tier:"einfach",q:[Pe("banka","Banka: ad ve şube",true),Pe("hesap","Hesap/IBAN numarası",true),Pe("kurum","Talimatı verilen kurum (fatura vb.)",true),Pe("iletisim","Ad, soyad ve adresiniz",true)]},

    /* Konut ve Kira */
    tr_kira_makbuz:{ic:"🧾",n:"Kira Ödeme Makbuzu",cat:"tr_konut",tier:"einfach",q:[Pe("kiraci","Kiracı: ad ve soyad",true),Pe("adres","Kiralanan taşınmazın adresi",true),Pe("donem","İlgili ay/dönem",true),Pe("tutar","Kira tutarı (TL)",true),Pe("odeme_sekli","Ödeme şekli",false,["Banka havalesi","Elden","Diğer"])]},
    tr_tahliye_ihtar:{ic:"⚠️",n:"Tahliye İhtarnamesi (Ev Sahibi)",cat:"tr_konut",tier:"komplex",q:[Pe("kiraci","Kiracı: ad ve adres",true),Pe("adres","Taşınmaz adresi",true),Pe("gerekce","Tahliye gerekçesi",true,["Kira borcunun ödenmemesi","Sözleşme süresinin dolması","İhtiyaç nedeniyle","Tahliye taahhüdü","Diğer"]),Pe("sure","Verilen süre (gün)",false),Pe("iletisim","Ad, soyad ve adresiniz",true)]},
    tr_kira_alacak_ihtar:{ic:"💶",n:"Kira Alacağı İhtarnamesi",cat:"tr_konut",tier:"standard",q:[Pe("kiraci","Kiracı: ad ve adres",true),Pe("adres","Taşınmaz adresi",true),Pe("donem","Ödenmeyen dönem(ler)",true),Pe("tutar","Toplam alacak (TL)",true),Pe("sure","Ödeme için verilen süre (gün, TBK 315: en az 30)",false),Pe("iletisim","Ad, soyad ve adresiniz",true)]},
    tr_emlak_komisyon_itiraz:{ic:"🏢",n:"Emlakçı Komisyon İtirazı",cat:"tr_konut",tier:"standard",q:[Pe("emlakci","Emlak ofisi: ad ve adres",true),Pe("islem","İlgili işlem (kiralama/satış)",true),Pe("talep_edilen","Talep edilen komisyon (TL)",true),Pe("itiraz_gerekce","İtiraz gerekçeniz",true),Pe("iletisim","Ad, soyad ve adresiniz",true)]},
    tr_alt_kira_izin:{ic:"🔑",n:"Alt Kira / Devir İzni Talebi",cat:"tr_konut",tier:"standard",q:[Pe("ev_sahibi","Ev sahibi: ad ve adres",true),Pe("adres","Taşınmaz adresi",true),Pe("neden","Talep nedeni",true),Pe("devralan","Devralacak/alt kiracı (biliniyorsa)",false),Pe("iletisim","Ad, soyad ve adresiniz",true)]},

    /* İş Hukuku */
    tr_is_kazasi_bildirim:{ic:"🚑",n:"İş Kazası Bildirimi",cat:"tr_is",tier:"komplex",q:[Pe("muhatap","Muhatap (işveren/SGK)",true),Pe("kaza_tarihi","Kaza tarihi ve saati",true),Pe("kaza_yeri","Kaza yeri",true),Pe("olay","Olayın tanımı",true),Pe("yaralanma","Yaralanma/hasar durumu",true),Pe("tanik","Tanıklar (varsa)",false)]},
    tr_ihtarname_cevap:{ic:"📨",n:"İşveren İhtarnamesine Cevap/Savunma",cat:"tr_is",tier:"standard",q:[Pe("isveren","İşveren: ad ve adres",true),Pe("ihtar_tarihi","İhtarnamenin tarihi",true),Pe("iddia","İhtardaki iddia/suçlama",true),Pe("savunma","Savunmanız (olayların sizin açınızdan anlatımı)",true)]},
    tr_ise_iade_talep:{ic:"⚖️",n:"İşe İade Talebi (Arabuluculuk Öncesi)",cat:"tr_is",tier:"komplex",q:[Pe("isveren","İşveren: ad ve adres",true),Pe("pozisyon","Pozisyonunuz",true),Pe("fesih_tarihi","Fesih bildirim tarihi",true),Pe("fesih_gerekce","İşverenin fesih gerekçesi",true),Pe("itiraz","Feshin geçersizliğine ilişkin gerekçeniz",true)]},
    tr_referans_mektubu:{ic:"📄",n:"Referans Mektubu Talebi",cat:"tr_is",tier:"einfach",q:[Pe("isveren","İşveren/yönetici",true),Pe("pozisyon","Pozisyonunuz",true),Pe("calisma_tarihleri","Çalışma tarihleri",true),Pe("amac","Kullanım amacı (opsiyonel)",false)]},
    tr_yillik_izin:{ic:"🌴",n:"Yıllık İzin Talebi",cat:"tr_is",tier:"einfach",q:[Pe("isveren","İşveren/yönetici",true),Pe("baslangic","İzin başlangıcı",true),Pe("bitis","İzin bitişi",true),Pe("gun_sayisi","Gün sayısı (opsiyonel)",false)]},
    tr_dogum_izni:{ic:"👶",n:"Doğum/Ebeveyn İzni Talebi",cat:"tr_is",tier:"einfach",q:[Pe("isveren","İşveren/İK",true),Pe("tur","İzin türü",true,["Doğum izni (analık)","Babalık izni","Ücretsiz ebeveyn izni","Süt izni düzenlemesi"]),Pe("tarih","Tahmini doğum/başlangıç tarihi",true)]},
    tr_uzaktan_calisma:{ic:"🏡",n:"Uzaktan/Esnek Çalışma Talebi",cat:"tr_is",tier:"einfach",q:[Pe("isveren","İşveren/yönetici",true),Pe("pozisyon","Pozisyonunuz",true),Pe("duzen","İstenen düzen (gün/hafta)",true),Pe("gerekce","Gerekçe (opsiyonel)",false)]},
    tr_zam_talebi:{ic:"📈",n:"Maaş Zammı Talebi",cat:"tr_is",tier:"einfach",q:[Pe("isveren","İşveren/yönetici",true),Pe("pozisyon","Pozisyonunuz",true),Pe("sure","Bu pozisyondaki süreniz",false),Pe("gerekce","Gerekçeleriniz (başarılar, sorumluluklar)",true),Pe("beklenti","Beklenen zam (opsiyonel)",false)]},

    /* Tüketici ve Uyuşmazlık */
    tr_garanti_onarim:{ic:"🔧",n:"Garanti Kapsamında Onarım Talebi",cat:"tr_tuketici",tier:"standard",q:[Pe("satici","Satıcı/yetkili servis: ad ve adres",true),Pe("urun","Ürün (marka/model)",true),Pe("satin_alma_tarihi","Satın alma tarihi",true),Pe("ariza","Arızanın tanımı",true),Pe("talep","Talebiniz",true,["Ücretsiz onarım","Değişim","İade"])]},
    tr_kargo_hasar:{ic:"📦",n:"Kargo Hasarı Tazmin Talebi",cat:"tr_tuketici",tier:"standard",q:[Pe("kargo","Kargo firması: ad",true),Pe("takip_no","Gönderi takip numarası",true),Pe("tarih","Teslim tarihi",true),Pe("hasar","Hasarın tanımı",true),Pe("deger","Ürün değeri (TL)",true)]},
    tr_fatura_itiraz:{ic:"🧾",n:"Fatura İtirazı (Elektrik/Su/Doğalgaz/Telefon)",cat:"tr_tuketici",tier:"standard",q:[Pe("firma","Firma: ad ve adres",true),Pe("abone_no","Abone/müşteri numarası",true),Pe("fatura_donem","İtiraz edilen fatura dönemi",true),Pe("tutar","Fatura tutarı (TL)",true),Pe("gerekce","İtiraz gerekçeniz",true)]},
    tr_internet_sikayet:{ic:"📶",n:"İnternet Hız/Kesinti Şikayeti",cat:"tr_tuketici",tier:"standard",q:[Pe("operator","Operatör: ad",true),Pe("abone_no","Abone numarası",true),Pe("sorun","Sorunun tanımı (hız, kesinti, taahhüt)",true),Pe("sure","Sorunun süresi",false),Pe("talep","Talebiniz",true,["İndirim/iade","Cayma bedelsiz fesih","Sorunun giderilmesi"])]},
    tr_devre_tatil_cayma:{ic:"🏖️",n:"Devre Tatil / Devremülk Cayma Bildirimi",cat:"tr_tuketici",tier:"standard",q:[Pe("firma","Firma: ad ve adres",true),Pe("sozlesme_no","Sözleşme numarası",true),Pe("sozlesme_tarihi","Sözleşme tarihi",true),Pe("odenen","Ödenen tutar (TL, varsa)",false),Pe("iletisim","Ad, soyad ve adresiniz",true)]},
    tr_sigorta_hasar:{ic:"🛡️",n:"Sigorta Hasar Başvurusu",cat:"tr_tuketici",tier:"standard",q:[Pe("sirket","Sigorta şirketi: ad ve adres",true),Pe("police_no","Poliçe numarası",true),Pe("hasar_tarihi","Hasar tarihi",true),Pe("olay","Olayın ve hasarın tanımı",true),Pe("tutar","Tahmini hasar tutarı (TL, opsiyonel)",false)]},
    tr_arac_deger_kaybi:{ic:"🚗",n:"Araç Değer Kaybı Talebi",cat:"tr_tuketici",tier:"komplex",q:[Pe("muhatap","Muhatap (karşı taraf sigortası)",true),Pe("kaza_tarihi","Kaza tarihi",true),Pe("plaka","Aracınızın plakası",true),Pe("hasar","Hasarın/onarımın özeti",true),Pe("dosya_no","Hasar dosya numarası (varsa)",false)]},

    /* Sözleşmeler */
    tr_arac_satis:{ic:"🚗",n:"Araç Satış Sözleşmesi (Noter Öncesi Protokol)",cat:"tr_sozlesme",tier:"standard",q:[Pe("rol","Rolünüz",true,["Satıcı","Alıcı"]),Pe("diger_taraf","Diğer taraf: ad ve adres",true),Pe("arac","Araç (marka/model/yıl/plaka/şasi)",true),Pe("km","Kilometre",false),Pe("bedel","Satış bedeli (TL)",true),Pe("kapora","Kapora tutarı (TL, varsa)",false),Pe("devir_tarihi","Noter devir tarihi",false)]},
    tr_eser_sozlesmesi:{ic:"🔨",n:"Eser Sözleşmesi (Tadilat/Yapım İşi)",cat:"tr_sozlesme",tier:"komplex",q:[Pe("rol","Rolünüz",true,["İş sahibi","Yüklenici"]),Pe("diger_taraf","Diğer taraf: ad ve adres",true),Pe("is_tanimi","İşin tanımı ve kapsamı",true),Pe("bedel","İş bedeli (TL)",true),Pe("odeme_plani","Ödeme planı",false),Pe("teslim_suresi","Teslim süresi/tarihi",true),Pe("ceza","Gecikme cezası uygulanacak mı?",false,["Evet","Hayır"])]},
    tr_ikale:{ic:"🤝",n:"İkale (Karşılıklı Fesih) Sözleşmesi",cat:"tr_sozlesme",tier:"komplex",q:[Pe("isveren","İşveren: ad ve adres",true),Pe("calisan","Çalışan: ad ve adres",true),Pe("pozisyon","Pozisyon",true),Pe("fesih_tarihi","Fesih tarihi",true),Pe("odemeler","Kararlaştırılan ödemeler (kıdem, ek ödeme vb.)",true)]},
    tr_esya_devir:{ic:"📋",n:"Eşya Devir/Teslim Tutanağı",cat:"tr_sozlesme",tier:"einfach",q:[Pe("devreden","Devreden: ad",true),Pe("devralan","Devralan: ad",true),Pe("esyalar","Devredilen eşyaların listesi",true),Pe("tarih","Teslim tarihi",true),Pe("durum","Eşyaların durumu (opsiyonel)",false)]},
    tr_odunc_esya:{ic:"🔄",n:"Kullanım Ödüncü (Ariyet) Sözleşmesi",cat:"tr_sozlesme",tier:"einfach",q:[Pe("veren","Ödünç veren: ad ve adres",true),Pe("alan","Ödünç alan: ad ve adres",true),Pe("esya","Ödünç verilen şey",true),Pe("sure","Süre/iade tarihi",true)]},

    /* Resmi Kurumlar */
    tr_kvkk_basvuru:{ic:"🔒",n:"KVKK Kişisel Veri Bilgi/Silme Başvurusu",cat:"tr_resmi",tier:"standard",q:[Pe("kurum","Veri sorumlusu (şirket/kurum): ad ve adres",true),Pe("talep","Talebiniz",true,["Verilerimin bilgisini istiyorum","Verilerimin silinmesini istiyorum","Verilerimin düzeltilmesini istiyorum","İşlemeye itiraz ediyorum"]),Pe("iliski","Kurumla ilişkiniz (müşteri, eski çalışan vb.)",true),Pe("iletisim","Ad, soyad, TC ve adresiniz",true)]},
    tr_bilgi_edinme:{ic:"📋",n:"Bilgi Edinme Başvurusu (4982 sayılı Kanun)",cat:"tr_resmi",tier:"einfach",q:[Pe("kurum","İlgili kamu kurumu",true),Pe("istenen_bilgi","İstenen bilgi/belge",true),Pe("iletisim","Ad, soyad, TC ve adresiniz",true)]},
    tr_cimer:{ic:"🏛️",n:"CİMER Başvuru Metni",cat:"tr_resmi",tier:"einfach",q:[Pe("konu","Başvuru konusu",true),Pe("kurum","İlgili kurum (biliniyorsa)",false),Pe("aciklama","Detaylı açıklama",true),Pe("talep","Talebiniz",true)]},
    tr_isim_degisiklik:{ic:"✍️",n:"İsim/Soyisim Değişikliği Dilekçesi",cat:"tr_resmi",tier:"komplex",q:[Pe("mevcut","Mevcut ad-soyad",true),Pe("istenen","İstenen ad-soyad",true),Pe("gerekce","Haklı sebep/gerekçe",true),Pe("mahkeme","Başvurulacak Asliye Hukuk Mahkemesi (il/ilçe)",false)]},
    tr_askerlik_tecil:{ic:"🎖️",n:"Askerlik Tecil/Erteleme Dilekçesi",cat:"tr_resmi",tier:"einfach",q:[Pe("askerlik_subesi","Askerlik şubesi",true),Pe("gerekce","Erteleme gerekçesi",true,["Öğrencilik","Çalışma (bakaya değil)","Sağlık","Yurt dışı"]),Pe("belgeler","Elinizdeki belgeler",false),Pe("iletisim","Ad, soyad, TC",true)]},
    tr_burs_basvuru:{ic:"🎓",n:"Burs/Kredi Başvuru Dilekçesi (KYK vb.)",cat:"tr_resmi",tier:"einfach",q:[Pe("kurum","Başvurulan kurum",true),Pe("okul","Okul/bölüm",true),Pe("durum","Maddi/ailevi durum özeti",true),Pe("iletisim","Ad, soyad, TC",true)]},
    tr_tapu_dilekce:{ic:"🏠",n:"Tapu Müdürlüğü İşlem Dilekçesi",cat:"tr_resmi",tier:"standard",q:[Pe("mudurluk","İlgili Tapu Müdürlüğü",true),Pe("islem","İşlem türü",true,["Satış","İntikal (miras)","İpotek tesis/terkin","Düzeltme","Diğer"]),Pe("tasinmaz","Taşınmaz bilgisi (il/ilçe/ada/parsel)",true),Pe("aciklama","Açıklama",false)]},

    /* Aile Hukuku (yeni kategori) */
    tr_bosanma_protokol:{ic:"📜",n:"Anlaşmalı Boşanma Protokolü",cat:"tr_aile",tier:"komplex",q:[Pe("esler","Eşlerin ad ve soyadları",true),Pe("evlilik_tarihi","Evlilik tarihi",true),Pe("cocuklar","Müşterek çocuklar (ad/yaş, yoksa 'yok')",true),Pe("velayet","Velayet düzenlemesi (çocuk varsa)",false),Pe("nafaka","Nafaka düzenlemesi (tutar/tür, yoksa 'yok')",false),Pe("mal_paylasimi","Mal paylaşımı özeti",true)]},
    tr_nafaka_talep:{ic:"💶",n:"Nafaka Talebi/Artırım Dilekçesi",cat:"tr_aile",tier:"komplex",q:[Pe("tur","Talep türü",true,["Yoksulluk nafakası","İştirak nafakası (çocuk)","Nafaka artırımı","Tedbir nafakası"]),Pe("karsi_taraf","Karşı taraf: ad",true),Pe("mevcut","Mevcut nafaka (TL, varsa)",false),Pe("talep_tutar","Talep edilen tutar (TL)",true),Pe("gerekce","Gerekçe (ihtiyaç/gelir durumu)",true)]},
    tr_velayet_talep:{ic:"👨‍👩‍👧",n:"Velayet/Kişisel İlişki Düzenleme Talebi",cat:"tr_aile",tier:"komplex",q:[Pe("cocuk","Çocuğun adı ve yaşı",true),Pe("karsi_taraf","Diğer ebeveyn: ad",true),Pe("mevcut_durum","Mevcut velayet/görüşme durumu",true),Pe("talep","Talebiniz",true),Pe("gerekce","Gerekçe (çocuğun üstün yararı)",true)]},
    tr_mal_paylasim:{ic:"⚖️",n:"Mal Paylaşımı Protokolü",cat:"tr_aile",tier:"komplex",q:[Pe("taraflar","Tarafların ad ve soyadları",true),Pe("mallar","Paylaşıma konu mallar (ev, araç, birikim)",true),Pe("paylasim","Kararlaştırılan paylaşım",true),Pe("odeme","Denkleştirme ödemesi (varsa)",false)]},
    tr_cocuk_seyahat_muvafakat:{ic:"✈️",n:"Çocuk İçin Seyahat Muvafakatnamesi",cat:"tr_aile",tier:"standard",q:[Pe("cocuk","Çocuğun adı ve doğum tarihi",true),Pe("seyahat_eden","Çocukla seyahat edecek kişi",true),Pe("muvafakat_veren","Muvafakat veren ebeveyn: ad ve TC",true),Pe("guzergah","Gidilecek ülke/yer",true),Pe("tarihler","Seyahat tarihleri",true)]},
    tr_evlilik_sozlesmesi:{ic:"💍",n:"Mal Ayrılığı (Evlilik) Sözleşmesi Taslağı",cat:"tr_aile",tier:"komplex",q:[Pe("taraflar","Tarafların ad ve soyadları",true),Pe("rejim","Seçilen mal rejimi",true,["Mal ayrılığı","Paylaşmalı mal ayrılığı","Mal ortaklığı"]),Pe("ozel_hukumler","Özel hükümler (opsiyonel)",false),Pe("not","NOT: Geçerlilik için noter onayı gerekir",false)]},
    tr_soyadi_kullanim:{ic:"✍️",n:"Boşanma Sonrası Soyadı Kullanım Talebi",cat:"tr_aile",tier:"standard",q:[Pe("mevcut_soyadi","Kullanılmak istenen (evlilik) soyadı",true),Pe("gerekce","Gerekçe (mesleki, sosyal vb.)",true),Pe("eski_es","Eski eşin adı",true)]}
  };

  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      if(typeof CATS==="undefined"||!CATS.tr_fesih) return false; /* CH_TR_MODULE yüklenmiş olmalı */
      for(var k in E){ if(!DOCS[k]) DOCS[k]=E[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=E[k].tier||"standard"; }
      for(var nc in NEWCATS){ if(!CATS[nc]) CATS[nc]={n:NEWCATS[nc].n,ic:NEWCATS[nc].ic,docs:[],country:"TR"}; if(!CATS[nc].docs) CATS[nc].docs=[]; }
      for(var k2 in E){ var c2=E[k2].cat; if(CATS[c2]&&CATS[c2].docs&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in NEWCATS) CAT_LABELS[cl]=NEWCATS[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in NEWCATS){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in E){ var c3=E[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:E[k3].ic,t:E[k3].n,tier:E[k3].tier||"standard"}); } }
      }
      return (typeof DOCS!=="undefined" && DOCS.tr_kvkk_basvuru)?true:false;
    }catch(e){ return false; }
  }

  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src!==$start){ file_put_contents($file,$src); echo "Turkiye genisletme eklendi (CH_TR_EXPAND) - +44 belge, yeni kategori: Aile Hukuku.\n"; }
else echo "degisiklik yok\n";
echo "\nOzet:\n";
echo "  Fesih: elektrik/dogalgaz, su, dijital platform, kurs, odeme talimati (+5)\n";
echo "  Konut: kira makbuzu, tahliye ihtarnamesi, kira alacagi ihtari, emlak komisyonu, alt kira (+5)\n";
echo "  Is: is kazasi, ihtarnameye cevap, ise iade, referans, yillik izin, dogum izni, uzaktan calisma, zam (+8)\n";
echo "  Tuketici: garanti onarim, kargo hasari, fatura itirazi, internet sikayeti, devre tatil, sigorta hasar, deger kaybi (+7)\n";
echo "  Sozlesme: arac satis, eser sozlesmesi, ikale, esya devir, ariyet (+5)\n";
echo "  Resmi: KVKK, bilgi edinme, CIMER, isim degisikligi, askerlik tecil, burs, tapu (+7)\n";
echo "  Aile Hukuku (YENI): bosanma protokolu, nafaka, velayet, mal paylasimi, cocuk muvafakat, evlilik sozlesmesi, soyadi (+7)\n";
echo "\nStripe: tum belgeler tier'li -> odeme otomatik. Belge dili/hukuku CC=TR ile otomatik Turkce/Turk hukuku.\n";
echo "SONRA: opcache-reset.php. SIL: rm apply-turkiye-expand.php\n";
echo "Test: TR sec -> 'Aile Hukuku' kategorisi gorunmeli; Is Hukuku'nda 'Ise Iade Talebi' gorunmeli.\n";
