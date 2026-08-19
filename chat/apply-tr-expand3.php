<?php
/**
 * ChatHelp — apply-tr-expand3 (CH_TR_EXPAND3) — Türkiye katalog genişletme
 *  Yeni kategori "İdari İşlemler" (tr_idare) + tr_aile / tr_is / tr_konut /
 *  tr_tuketici / tr_banka kategorilerine toplam 22 yeni belge. Türk hukukuna
 *  göre (İYUK, TMK, İş Kanunu, TBK, TKHK, KVKK, VUK). Anchorsuz merge + retry
 *  boot (tr-expand2 ile ayni desen). Mevcut belge anahtarlariyla cakismaz.
 * KULLANIM: pull-updates.php?files=apply-tr-expand3.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-tr-expand3 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_TR_EXPAND3')!==false) exit("Zaten ekli (CH_TR_EXPAND3).\n");

$bundle = <<<'HTML'
<script id="ch-tr-expand3-js">
/* CH_TR_EXPAND3 — TR +22 belge, +1 kategori (İdari İşlemler), deferred merge */
try{(function(){
  function Pe(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var NEWCATS={ tr_idare:{n:"İdari İşlemler",ic:"🏛️"} };

  var E={
    /* İdari İşlemler (yeni kategori) */
    tr_idari_itiraz:{ic:"📋",n:"İdari İşleme İtiraz Dilekçesi",cat:"tr_idare",tier:"standard",q:[Pe("kurum","İşlemi yapan kurum/makam",true),Pe("islem","İtiraz edilen işlem (tarih, sayı/evrak no)",true),Pe("gerekce","İşlemin neden hatalı/hukuka aykırı olduğu",true),Pe("talep","Talebiniz (işlemin geri alınması/düzeltilmesi)",true),Pe("kisi","Ad, soyad, T.C. kimlik no ve adres",true)]},
    tr_idari_dava:{ic:"⚖️",n:"İdari Dava Dilekçesi (taslak)",cat:"tr_idare",tier:"komplex",q:[Pe("mahkeme","Görevli idare/vergi mahkemesi",true),Pe("davali","Davalı idare",true),Pe("islem","Dava konusu işlem (tarih, sayı, tebliğ tarihi)",true),Pe("hukuki","Hukuka aykırılık sebepleri",true),Pe("talep","Netice-i talep (iptal / yürütmeyi durdurma)",true),Pe("kisi","Davacı ad-soyad, T.C. kimlik no, adres",true)]},
    tr_vergi_itiraz:{ic:"🧾",n:"Vergi/Ceza İhbarnamesine İtiraz (uzlaşma/düzeltme)",cat:"tr_idare",tier:"komplex",q:[Pe("daire","Vergi dairesi",true),Pe("ihbarname","İhbarname tarihi ve numarası",true),Pe("tutar","Tarh edilen vergi/ceza tutarı (₺)",true),Pe("gerekce","İtiraz gerekçesi (maddi/hukuki hata)",true),Pe("yol","Talebiniz",true,["Düzeltme","Uzlaşma","Terkin"]),Pe("kisi","Ad-soyad/unvan, T.C./VKN",true)]},
    tr_trafik_itiraz:{ic:"🚗",n:"Trafik İdari Para Cezasına İtiraz (Sulh Ceza Hâkimliği)",cat:"tr_idare",tier:"standard",q:[Pe("makam","Cezayı kesen birim (Trafik/Emniyet/Jandarma)",true),Pe("tutanak","Ceza tutanağı tarihi ve seri/sıra no",true),Pe("plaka","Araç plakası",true),Pe("gerekce","İtiraz gerekçesi (tebliğ, yanlışlık, mücbir sebep…)",true),Pe("kisi","Ad, soyad, T.C. kimlik no, adres",true)]},
    tr_kvkk_basvuru:{ic:"🔒",n:"KVKK Kapsamında Veri Sorumlusuna Başvuru",cat:"tr_idare",tier:"standard",q:[Pe("veri_sorumlusu","Veri sorumlusu (şirket/kurum)",true),Pe("talep","Talebiniz",true,["Verilerime erişim","Silinmesi/yok edilmesi","Düzeltilmesi","Aktarıldığı 3. kişiler","İşlenmesine itiraz"]),Pe("iliski","İlgili kişi olarak ilişkiniz (müşteri, eski çalışan…)",true),Pe("kisi","Ad, soyad, T.C. kimlik no ve iletişim",true)]},
    tr_kamu_dilekce:{ic:"✉️",n:"Kamu Kurumuna Genel Dilekçe (Dilekçe Hakkı)",cat:"tr_idare",tier:"einfach",q:[Pe("kurum","Başvurulan kurum/makam",true),Pe("konu","Konu",true),Pe("talep","Talebiniz / açıklama",true),Pe("kisi","Ad, soyad, T.C. kimlik no ve adres",true)]},

    /* Aile */
    tr_anlasmali_bosanma:{ic:"📜",n:"Anlaşmalı Boşanma Protokolü (taslak)",cat:"tr_aile",tier:"komplex",q:[Pe("esler","Eşlerin ad-soyad ve T.C. kimlik no",true),Pe("evlilik","Evlilik tarihi",true),Pe("cocuk","Müşterek çocuk (ad/yaş, yoksa 'yok')",true),Pe("velayet","Velayet ve kişisel ilişki (varsa)",false),Pe("nafaka","Nafaka (iştirak/yoksulluk — ₺, yoksa 'yok')",false),Pe("mal","Mal paylaşımı / tazminat özeti",true),Pe("not","NOT: hâkim onayı ile geçerli olur",false)]},
    tr_nafaka_talebi:{ic:"💶",n:"Nafaka Talebi Dilekçesi",cat:"tr_aile",tier:"komplex",q:[Pe("yukumlu","Nafaka yükümlüsü: ad-soyad",true),Pe("tur","Nafaka türü",true,["İştirak (çocuk)","Yoksulluk","Tedbir","Yardım"]),Pe("gerekce","Talebin dayanağı (ihtiyaç, gelir durumu)",true),Pe("tutar","Talep edilen aylık tutar (₺)",true),Pe("kisi","Ad, soyad, T.C. kimlik no, adres",true)]},
    tr_nafaka_artirim:{ic:"📈",n:"Nafaka Artırım Talebi",cat:"tr_aile",tier:"standard",q:[Pe("karar","Nafakayı belirleyen karar (tarih, mahkeme, esas no)",true),Pe("mevcut","Mevcut aylık nafaka (₺)",true),Pe("gerekce","Artış gerekçesi (enflasyon, artan ihtiyaç)",true),Pe("talep","Talep edilen yeni tutar (₺)",true),Pe("kisi","Ad, soyad, T.C. kimlik no",true)]},
    tr_velayet_talebi:{ic:"👪",n:"Velayet / Kişisel İlişki Tesisi Talebi",cat:"tr_aile",tier:"komplex",q:[Pe("diger","Diğer taraf: ad-soyad",true),Pe("cocuk","Çocuk (ad/yaş)",true),Pe("durum","Mevcut durum ve talebin gerekçesi",true),Pe("talep","Talebiniz (velayet / kişisel ilişki düzeni)",true),Pe("kisi","Ad, soyad, T.C. kimlik no, adres",true)]},
    tr_cocuk_muvafakat:{ic:"✈️",n:"Çocuk için Yurt Dışı Muvafakatnamesi",cat:"tr_aile",tier:"standard",q:[Pe("cocuk","Çocuğun ad-soyad ve doğum tarihi",true),Pe("veli","Muvafakat veren veli: ad-soyad, T.C. kimlik no",true),Pe("refakat","Çocuğa refakat eden kişi (ad, yakınlık)",false),Pe("ulke","Gidilecek ülke",true),Pe("tarih","Seyahat tarihleri",true),Pe("not","NOT: noter onayı gerekebilir",false)]},

    /* İş & Çalışma */
    tr_kidem_ihbar:{ic:"💼",n:"Kıdem ve İhbar Tazminatı Talebi",cat:"tr_is",tier:"komplex",q:[Pe("isveren","İşveren/şirket adı ve adresi",true),Pe("giris_cikis","İşe giriş ve çıkış tarihleri",true),Pe("ucret","Son brüt ücret (₺)",true),Pe("fesih","Fesih şekli (istifa/haklı fesih/işten çıkarma)",true),Pe("kisi","Ad, soyad, T.C. kimlik no, adres",true)]},
    tr_yillik_izin_ucret:{ic:"🏖️",n:"Kullanılmayan Yıllık İzin Ücreti Talebi",cat:"tr_is",tier:"standard",q:[Pe("isveren","İşveren adı ve adresi",true),Pe("sure","Çalışma süresi (yıl)",true),Pe("izin","Kullanılmayan izin günü sayısı",true),Pe("ucret","Günlük brüt ücret (₺)",true),Pe("kisi","Ad, soyad, T.C. kimlik no",true)]},
    tr_istifa_bildirim:{ic:"📝",n:"İstifa / İş Sözleşmesi Fesih Bildirimi",cat:"tr_is",tier:"einfach",q:[Pe("isveren","İşveren/şirket",true),Pe("gorev","Göreviniz ve işe giriş tarihi",true),Pe("tarih","Son çalışma günü",true),Pe("sebep","Sebep (opsiyonel)",false),Pe("kisi","Ad, soyad, T.C. kimlik no",true)]},
    tr_mobbing_sikayet:{ic:"🚨",n:"İşyeri Psikolojik Taciz (Mobbing) Şikâyeti",cat:"tr_is",tier:"komplex",q:[Pe("isveren","İşveren/şirket",true),Pe("makam","Şikâyet mercii",true,["İşveren/İK","ALO 170","Çalışma ve İş Kurumu İl Müdürlüğü"]),Pe("olaylar","Yaşanan olaylar (tarih, kişi, davranış)",true),Pe("tanik","Tanık/delil (opsiyonel)",false),Pe("kisi","Ad, soyad, T.C. kimlik no ve görev",true)]},

    /* Konut & Kira */
    tr_kira_artis_itiraz:{ic:"🏠",n:"Fahiş Kira Artışına İtiraz",cat:"tr_konut",tier:"standard",q:[Pe("kiraya_veren","Kiraya veren: ad ve adres",true),Pe("adres","Kiralanan konutun adresi",true),Pe("mevcut","Mevcut kira (₺)",true),Pe("istenen","İstenen yeni kira (₺) ve artış oranı",true),Pe("kisi","Kiracı ad, soyad, T.C. kimlik no",true)]},
    tr_tahliye_ihtari:{ic:"⚠️",n:"Kira Bedeli Ödenmemesi İhtarı (kiraya veren)",cat:"tr_konut",tier:"standard",q:[Pe("kiraci","Kiracı: ad ve adres",true),Pe("adres","Kiralanan konutun adresi",true),Pe("aylar","Ödenmeyen aylar",true),Pe("tutar","Toplam borç (₺)",true),Pe("sure","Verilen ödeme süresi (gün)",false),Pe("kisi","Kiraya veren ad, soyad, T.C. kimlik no",true)]},
    tr_konut_onarim:{ic:"🔧",n:"Kiralanan Konutta Onarım/Ayıp Giderme Talebi",cat:"tr_konut",tier:"standard",q:[Pe("kiraya_veren","Kiraya veren: ad ve adres",true),Pe("adres","Konut adresi",true),Pe("ariza","Giderilmesi gereken arıza/ayıp (detay)",true),Pe("sure","Talep edilen makul süre",false),Pe("kisi","Kiracı ad, soyad, T.C. kimlik no",true)]},

    /* Tüketici */
    tr_garanti_talebi:{ic:"🛠️",n:"Garanti Kapsamında Onarım/Değişim Talebi",cat:"tr_tuketici",tier:"standard",q:[Pe("satici","Satıcı/üretici firma",true),Pe("urun","Ürün ve satın alma tarihi",true),Pe("ariza","Arıza/ayıp tanımı",true),Pe("talep","Talebiniz",true,["Ücretsiz onarım","Değişim","Bedel iadesi","İndirim"]),Pe("kisi","Ad, soyad, T.C. kimlik no ve iletişim",true)]},
    tr_abonelik_iptal:{ic:"📵",n:"Abonelik İptali ve İade Talebi",cat:"tr_tuketici",tier:"einfach",q:[Pe("firma","Firma (operatör/platform)",true),Pe("abone","Abone/müşteri no",true),Pe("talep","Talep (iptal + varsa iade)",true),Pe("iade","İade edilmesi gereken tutar (₺, varsa)",false),Pe("kisi","Ad, soyad, T.C. kimlik no",true)]},
    tr_kart_ucret_itiraz:{ic:"💳",n:"Kredi Kartı Haksız Ücret/İşlem İtirazı",cat:"tr_tuketici",tier:"standard",q:[Pe("banka","Banka",true),Pe("kart","Kart (son 4 hane)",true),Pe("islem","İtiraz edilen ücret/işlem (tarih, tutar ₺)",true),Pe("gerekce","Neden haksız olduğu",true),Pe("kisi","Ad, soyad, T.C. kimlik no",true)]},

    /* Banka & Finans */
    tr_kredi_yapilandirma:{ic:"🏦",n:"Kredi Yapılandırma / Ödeme Planı Talebi",cat:"tr_banka",tier:"standard",q:[Pe("banka","Banka",true),Pe("kredi","Kredi/kart hesap no ve tür",true),Pe("durum","Mevcut durum (gecikme, borç ₺)",true),Pe("talep","Talep edilen yapılandırma (vade, taksit)",true),Pe("kisi","Ad, soyad, T.C. kimlik no",true)]}
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
      return !!(DOCS.tr_idari_itiraz);
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
echo "  ✓ TR +22 belge, +1 kategori (İdari İşlemler) eklendi\n";

$tmp = tempnam(sys_get_temp_dir(),'t3').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-trexp3-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_TR_EXPAND3 uygulandi. TR kataloğu +22 belge, yeni 'İdari İşlemler' kategorisi.\n";
