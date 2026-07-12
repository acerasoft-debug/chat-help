<?php
/**
 * ChatHelp — apply-vst-tr (CH_VST_TR) — F2-2 PILOT: Turk hukukuna gore sozlesmeler
 *  Vertrag Studio'ya 5 TR sozlesmesi ekler (TMK/TBK/Is K. referansli, Turkce):
 *   1) Anlasmali Bosanma Protokolu (TMK m.166/3)  2) Konut Kira Sozlesmesi (TBK 299 vd.)
 *   3) Belirsiz Sureli Is Sozlesmesi (4857)       4) Arac/Tasinir Satis Sozlesmesi (TBK 207 vd.)
 *   5) Ibra ve Feragat Sozlesmesi
 *  • Her sozlesmede "Kendi Maddeleriniz (opsiyonel)" adimi (uc_t1/uc_b1...)
 *  • ULKE FILTRESI: hukuk=TR iken SADECE TR sozlesmeleri listelenir; diger
 *    hukuklarda TR sozlesmeleri gizlenir (Alman seti aynen kalir).
 *  • TR'de modul GORUNUR: country-gate'in nav-vst gizlemesi TR icin acilir.
 *  Mevcut motor kullanilir: NCX + ncxBuild + buildPages sarmalayicisi (vst-more).
 * KULLANIM: pull2.php?key=...&files=apply-vst-tr.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-vst-tr BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_VST_TR')!==false) exit("Zaten ekli (CH_VST_TR).\n");
$anchor='function boot(){ addCss(); addNav();';
$ac = substr_count($src,$anchor);
if ($ac!==1) exit("HATA: Vertrag Studio boot anchor $ac kez (beklenen 1).\n");
if (strpos($src,'ncxBuild')===false) exit("HATA: vst-more (ncxBuild) yok — once o gerekli.\n");

$js = <<<'CHJS'

  /* ══ CH_VST_TR — Turk hukukuna gore sozlesmeler + ulke filtresi ══ */
  setTimeout(function(){ try{
    var TRK={};
    TRK["vtr_tr_bosanma"]={ic:"⚖️",n:"Anlaşmalı Boşanma Protokolü",sn:"TMK m.166/3 uyarınca boşanma ve sonuçları hakkında protokol",price:"4,99 €",pgs:5,steps:[
      {t:"Taraflar",f:[["es1_ad","Eş 1 — Ad Soyad"],["es1_tc","Eş 1 — T.C. Kimlik No"],["es1_adres","Eş 1 — Adres"],["es2_ad","Eş 2 — Ad Soyad"],["es2_tc","Eş 2 — T.C. Kimlik No"],["es2_adres","Eş 2 — Adres"]]},
      {t:"Evlilik Bilgileri",f:[["evlilik_tarihi","Evlilik tarihi"],["evlilik_yeri","Evliliğin yapıldığı yer (nüfusa kayıtlı yer)"],["cocuklar","Ortak çocuklar (ad, doğum tarihi; yoksa 'yok')","yok","ta"]]},
      {t:"Velayet ve Nafaka",f:[["velayet","Velayet düzenlemesi","Ortak çocuk bulunmadığından velayet düzenlemesine yer verilmemiştir.","ta"],["kisisel_iliski","Kişisel ilişki (görüş) düzenlemesi","Her ayın 1. ve 3. hafta sonu cumartesi 10:00 – pazar 18:00 arası; dini bayramların 2. günü 09:00–18:00; yaz tatilinde 1–31 Temmuz arası.","ta"],["istirak_nafakasi","İştirak nafakası","Talep edilmemektedir."],["yoksulluk_nafakasi","Yoksulluk nafakası","Taraflar birbirlerinden yoksulluk nafakası talep etmediklerini kabul ve beyan ederler."]]},
      {t:"Mali Sonuçlar",f:[["maddi_tazminat","Maddi tazminat","Taraflar birbirlerinden maddi tazminat talep etmemektedir."],["manevi_tazminat","Manevi tazminat","Taraflar birbirlerinden manevi tazminat talep etmemektedir."],["mal_paylasimi","Mal rejimi ve paylaşım","Taraflar, evlilik birliği içinde edinilen tüm menkul ve gayrimenkul malların paylaşımı konusunda aralarında anlaşmış olup birbirlerinden mal rejiminin tasfiyesine ilişkin başkaca alacak, katkı ve katılma alacağı talepleri bulunmadığını beyan ederler.","ta"],["ortak_konut","Ortak konutun durumu","Ortak konutta bulunan eşyalar taraflarca paylaşılmış olup konut üzerinde ihtilaf yoktur.","ta"],["yargilama_gideri","Yargılama giderleri","Her bir taraf kendi yargılama giderini ve vekâlet ücretini üstlenecektir."]]},
      {t:"İmza",f:[["ort","Yer (şehir)"],["datum","Tarih"]]},
      {t:"Kendi Maddeleriniz (opsiyonel)",f:[["uc_t1","Ek Madde 1 — Başlık","örn. Özel düzenleme"],["uc_b1","Ek Madde 1 — Metin","","ta"],["uc_t2","Ek Madde 2 — Başlık (opsiyonel)",""],["uc_b2","Ek Madde 2 — Metin (opsiyonel)","","ta"]]}]};
    TRK["vtr_tr_kira"]={ic:"🏠",n:"Konut Kira Sözleşmesi",sn:"6098 sayılı TBK m.299 vd. uyarınca konut kirası",price:"4,99 €",pgs:5,steps:[
      {t:"Taraflar",f:[["kiraya_veren","Kiraya veren (ad soyad / unvan, T.C./VKN)","","ta"],["kv_adres","Kiraya verenin adresi"],["kiraci","Kiracı (ad soyad, T.C. Kimlik No)","","ta"],["k_adres","Kiracının tebligat adresi"]]},
      {t:"Kiralanan ve Süre",f:[["tasinmaz","Kiralanan taşınmaz (tam adres, ada/parsel veya bağımsız bölüm no)","","ta"],["kullanim","Kullanım amacı","yalnızca konut olarak"],["baslangic","Kira başlangıç tarihi"],["sure","Kira süresi","1 (bir) yıl; süre sonunda TBK m.347 uyarınca aynı koşullarla birer yıl uzar"]]},
      {t:"Bedel ve Depozito",f:[["kira_bedeli","Aylık kira bedeli (TL)"],["odeme","Ödeme şekli ve günü","her ayın en geç 5. günü, kiraya verenin bildireceği banka hesabına"],["artis","Yıllık artış oranı","TÜFE on iki aylık ortalamalara göre değişim oranını geçmemek üzere (TBK m.344)"],["depozito","Güvence bedeli (depozito)","üç aylık kira bedelini aşmamak üzere (TBK m.342)"],["yan_giderler","Yan giderler (aidat, sayaçlar)","Elektrik, su, doğalgaz ve site aidatı kiracıya aittir."]]},
      {t:"Kullanım ve Teslim",f:[["demirbas","Demirbaş ve teslim durumu","Kiralanan, demirbaşlarıyla birlikte sağlam ve kullanılabilir durumda teslim edilmiştir.","ta"],["devir_yasagi","Devir ve alt kira","Kiraya verenin yazılı rızası olmadıkça kiralanan devredilemez ve alt kiraya verilemez (TBK m.322)."],["tadilat","Tadilat","Kiracı, kiraya verenin yazılı onayı olmadan esaslı değişiklik yapamaz."],["ort","Yer (şehir)"],["datum","Tarih"]]},
      {t:"Kendi Maddeleriniz (opsiyonel)",f:[["uc_t1","Ek Madde 1 — Başlık","örn. Özel şart"],["uc_b1","Ek Madde 1 — Metin","","ta"],["uc_t2","Ek Madde 2 — Başlık (opsiyonel)",""],["uc_b2","Ek Madde 2 — Metin (opsiyonel)","","ta"]]}]};
    TRK["vtr_tr_is"]={ic:"💼",n:"Belirsiz Süreli İş Sözleşmesi",sn:"4857 sayılı İş Kanunu'na tabi iş sözleşmesi",price:"4,99 €",pgs:5,steps:[
      {t:"Taraflar",f:[["isveren","İşveren (unvan, adres, SGK işyeri sicil no)","","ta"],["isci","İşçi (ad soyad, T.C. Kimlik No, adres)","","ta"]]},
      {t:"İş ve Ücret",f:[["gorev","Görev / pozisyon"],["isyeri","İşyeri (çalışma yeri)"],["baslama","İşe başlama tarihi"],["ucret","Aylık brüt ücret (TL)"],["odeme_gunu","Ücretin ödenme günü","her ayın son iş günü, işçinin banka hesabına"],["deneme","Deneme süresi","iki ay (İş K. m.15)"]]},
      {t:"Çalışma Koşulları",f:[["calisma_suresi","Haftalık çalışma süresi","45 saat (İş K. m.63); fazla çalışma mevzuata göre ücretlendirilir"],["izin","Yıllık ücretli izin","İş Kanunu m.53 vd. hükümlerine göre"],["gizlilik","Sır saklama","İşçi, işverene ait ticari sır ve gizli bilgileri iş ilişkisi sırasında ve sonrasında saklamakla yükümlüdür.","ta"],["fesih","Fesih ve ihbar","Fesihte İş Kanunu m.17 ihbar süreleri ve ilgili mevzuat hükümleri uygulanır."]]},
      {t:"İmza",f:[["ort","Yer (şehir)"],["datum","Tarih"]]},
      {t:"Kendi Maddeleriniz (opsiyonel)",f:[["uc_t1","Ek Madde 1 — Başlık","örn. Prim düzenlemesi"],["uc_b1","Ek Madde 1 — Metin","","ta"],["uc_t2","Ek Madde 2 — Başlık (opsiyonel)",""],["uc_b2","Ek Madde 2 — Metin (opsiyonel)","","ta"]]}]};
    TRK["vtr_tr_arac_satis"]={ic:"🚗",n:"Araç / Taşınır Satış Sözleşmesi",sn:"TBK m.207 vd. uyarınca satış ve devir",price:"4,99 €",pgs:4,steps:[
      {t:"Taraflar",f:[["satici","Satıcı (ad soyad, T.C. Kimlik No, adres)","","ta"],["alici","Alıcı (ad soyad, T.C. Kimlik No, adres)","","ta"]]},
      {t:"Satışa Konu Mal",f:[["mal","Satılan (araçsa: marka, model, yıl, plaka, şasi no; diğer taşınırsa tanımı)","","ta"],["km_durum","Mevcut durum / km","","ta"],["ayip_beyani","Bilinen kusurlar / hasar kaydı","Satıcı tarafından bildirilen kusur bulunmamaktadır.","ta"]]},
      {t:"Bedel ve Teslim",f:[["bedel","Satış bedeli (TL)"],["odeme_sekli","Ödeme şekli","peşin, banka havalesi ile"],["teslim","Teslim tarihi ve yeri","bedelin ödenmesiyle aynı gün, satıcının adresinde"],["devir","Resmî devir","Araç devri, noterde yapılacak resmî satış işlemi ile tamamlanır; masraflar aksi kararlaştırılmadıkça alıcıya aittir."],["ort","Yer (şehir)"],["datum","Tarih"]]},
      {t:"Kendi Maddeleriniz (opsiyonel)",f:[["uc_t1","Ek Madde 1 — Başlık","örn. Ek aksesuar listesi"],["uc_b1","Ek Madde 1 — Metin","","ta"],["uc_t2","Ek Madde 2 — Başlık (opsiyonel)",""],["uc_b2","Ek Madde 2 — Metin (opsiyonel)","","ta"]]}]};
    TRK["vtr_tr_ibraname"]={ic:"🖋️",n:"İbra ve Feragat Sözleşmesi",sn:"Karşılıklı ibra (iş ilişkilerinde TBK m.420 şartlarına dikkat)",price:"4,99 €",pgs:4,steps:[
      {t:"Taraflar",f:[["taraf1","İbra eden (ad soyad/unvan, kimlik/VKN, adres)","","ta"],["taraf2","İbra edilen (ad soyad/unvan, kimlik/VKN, adres)","","ta"]]},
      {t:"İlişki ve Kapsam",f:[["iliski","İbraya konu hukuki ilişki (ör. iş sözleşmesi, kira, alım-satım)","","ta"],["kapsam","İbra kapsamı","Taraflar, yukarıda belirtilen hukuki ilişkiden doğmuş ve doğacak tüm alacak, tazminat ve sair taleplerinden karşılıklı olarak feragat ettiklerini beyan ederler.","ta"],["odeme","Bu sözleşme kapsamında yapılan ödeme (varsa; tutar ve tarih)","yoktur"]]},
      {t:"Son Hükümler ve İmza",f:[["saklama","Saklı tutulan haklar (varsa)","yoktur"],["ort","Yer (şehir)"],["datum","Tarih"]]},
      {t:"Kendi Maddeleriniz (opsiyonel)",f:[["uc_t1","Ek Madde 1 — Başlık",""],["uc_b1","Ek Madde 1 — Metin","","ta"],["uc_t2","Ek Madde 2 — Başlık (opsiyonel)",""],["uc_b2","Ek Madde 2 — Metin (opsiyonel)","","ta"]]}]};

    function ekMadde(d){ var s=""; if(d.uc_t1&&d.uc_b1){ s+=d.uc_t1+": "+d.uc_b1; } if(d.uc_t2&&d.uc_b2){ s+=(s?" — ":"")+d.uc_t2+": "+d.uc_b2; } return s||"Taraflarca eklenen başkaca özel hüküm yoktur."; }
    if(typeof NCX!=="undefined" && NCX){
      NCX["vtr_tr_bosanma"]={n:"ANLAŞMALI BOŞANMA PROTOKOLÜ",sn:"4721 sayılı TMK m.166/3 uyarınca",A:"Eş 1",B:"Eş 2",cl:[
        {no:"Madde 1",t:"Taraflar ve Konu",b:function(d){return "İşbu protokol, "+V(d.es1_ad)+" (T.C. "+V(d.es1_tc)+", "+V(d.es1_adres)+") ile "+V(d.es2_ad)+" (T.C. "+V(d.es2_tc)+", "+V(d.es2_adres)+") arasında, "+V(d.evlilik_tarihi)+" tarihinde "+V(d.evlilik_yeri,"ilgili evlendirme dairesinde")+" akdedilen evliliğin 4721 sayılı Türk Medenî Kanunu'nun 166/3. maddesi uyarınca anlaşmalı olarak sona erdirilmesi ve boşanmanın malî sonuçları ile çocukların durumunun düzenlenmesi amacıyla akdedilmiştir.";}},
        {no:"Madde 2",t:"Boşanma Hususunda Anlaşma",b:function(d){return "Taraflar, evlilik birliğinin temelinden sarsıldığını, ortak hayatın çekilmez hâle geldiğini ve boşanma ile boşanmanın tüm sonuçları hakkında özgür iradeleriyle anlaştıklarını kabul ve beyan ederler. Taraflar mahkemece duruşmada bizzat dinlenmeleri gerektiğini bildiklerini beyan ederler. Ortak çocuklar: "+V(d.cocuklar,"yok")+".";}},
        {no:"Madde 3",t:"Velayet ve Kişisel İlişki",b:function(d){return V(d.velayet)+" Kişisel ilişki düzenlemesi: "+V(d.kisisel_iliski)+"";}},
        {no:"Madde 4",t:"Nafaka",b:function(d){return "İştirak nafakası: "+V(d.istirak_nafakasi)+" Yoksulluk nafakası: "+V(d.yoksulluk_nafakasi)+"";}},
        {no:"Madde 5",t:"Tazminat ve Mal Rejimi",b:function(d){return "Maddi tazminat: "+V(d.maddi_tazminat)+" Manevi tazminat: "+V(d.manevi_tazminat)+" Mal rejimi ve paylaşım: "+V(d.mal_paylasimi)+" Ortak konut ve eşyalar: "+V(d.ortak_konut)+"";}},
        {no:"Madde 6",t:"Yargılama Giderleri",b:function(d){return V(d.yargilama_gideri)+" İşbu protokol, tarafların açacağı/açtığı anlaşmalı boşanma davasında hükme esas alınmak üzere mahkemeye sunulacaktır.";}},
        {no:"Madde 7",t:"Taraflarca Eklenen Özel Hükümler",b:ekMadde}]};
      NCX["vtr_tr_kira"]={n:"KONUT KİRA SÖZLEŞMESİ",sn:"6098 sayılı TBK m.299 vd.",A:"Kiraya Veren",B:"Kiracı",cl:[
        {no:"Madde 1",t:"Taraflar ve Kiralanan",b:function(d){return "Kiraya veren "+V(d.kiraya_veren)+" ("+V(d.kv_adres)+") ile kiracı "+V(d.kiraci)+" ("+V(d.k_adres)+") arasında, "+V(d.tasinmaz)+" adresindeki taşınmazın "+V(d.kullanim,"yalnızca konut olarak")+" kullanılmak üzere kiralanması konusunda işbu sözleşme akdedilmiştir.";}},
        {no:"Madde 2",t:"Süre",b:function(d){return "Kira ilişkisi "+V(d.baslangic)+" tarihinde başlar. Kira süresi: "+V(d.sure)+".";}},
        {no:"Madde 3",t:"Kira Bedeli ve Ödeme",b:function(d){return "Aylık kira bedeli "+V(d.kira_bedeli)+" TL olup "+V(d.odeme)+" ödenir. Yıllık artış: "+V(d.artis)+". Güvence bedeli: "+V(d.depozito)+". Yan giderler: "+V(d.yan_giderler)+"";}},
        {no:"Madde 4",t:"Teslim ve Kullanım",b:function(d){return V(d.demirbas)+" "+V(d.devir_yasagi)+" "+V(d.tadilat)+"";}},
        {no:"Madde 5",t:"Genel Hükümler",b:function(d){return "Bu sözleşmede hüküm bulunmayan hâllerde 6098 sayılı Türk Borçlar Kanunu'nun konut ve çatılı işyeri kiralarına ilişkin hükümleri uygulanır. Tebligatlar, tarafların yukarıda yazılı adreslerine yapılır; adres değişikliği yazılı olarak bildirilmedikçe eski adrese yapılan tebligat geçerlidir.";}},
        {no:"Madde 6",t:"Taraflarca Eklenen Özel Hükümler",b:ekMadde}]};
      NCX["vtr_tr_is"]={n:"BELİRSİZ SÜRELİ İŞ SÖZLEŞMESİ",sn:"4857 sayılı İş Kanunu",A:"İşveren",B:"İşçi",cl:[
        {no:"Madde 1",t:"Taraflar",b:function(d){return "İşveren "+V(d.isveren)+" ile işçi "+V(d.isci)+" arasında aşağıdaki koşullarla belirsiz süreli iş sözleşmesi akdedilmiştir.";}},
        {no:"Madde 2",t:"İşin Tanımı ve Yeri",b:function(d){return "İşçi, "+V(d.isyeri)+" işyerinde "+V(d.gorev)+" pozisyonunda çalışacaktır. İşe başlama tarihi: "+V(d.baslama)+". Deneme süresi: "+V(d.deneme)+".";}},
        {no:"Madde 3",t:"Ücret",b:function(d){return "Aylık brüt ücret "+V(d.ucret)+" TL olup "+V(d.odeme_gunu)+" ödenir. Fazla çalışma ve genel tatil ücretleri 4857 sayılı Kanun hükümlerine göre hesaplanır.";}},
        {no:"Madde 4",t:"Çalışma Süresi ve İzin",b:function(d){return "Haftalık çalışma süresi: "+V(d.calisma_suresi)+". Yıllık ücretli izin: "+V(d.izin)+".";}},
        {no:"Madde 5",t:"Sır Saklama",b:function(d){return V(d.gizlilik)+"";}},
        {no:"Madde 6",t:"Fesih",b:function(d){return V(d.fesih)+" İşbu sözleşmede düzenlenmeyen hususlarda 4857 sayılı İş Kanunu ve ilgili mevzuat uygulanır.";}},
        {no:"Madde 7",t:"Taraflarca Eklenen Özel Hükümler",b:ekMadde}]};
      NCX["vtr_tr_arac_satis"]={n:"ARAÇ / TAŞINIR SATIŞ SÖZLEŞMESİ",sn:"6098 sayılı TBK m.207 vd.",A:"Satıcı",B:"Alıcı",cl:[
        {no:"Madde 1",t:"Taraflar ve Satışa Konu Mal",b:function(d){return "Satıcı "+V(d.satici)+" ile alıcı "+V(d.alici)+" arasında, aşağıda tanımlanan malın satışı konusunda anlaşılmıştır: "+V(d.mal)+". Mevcut durum: "+V(d.km_durum,"beyan edilmiştir")+".";}},
        {no:"Madde 2",t:"Bedel ve Ödeme",b:function(d){return "Satış bedeli "+V(d.bedel)+" TL olup "+V(d.odeme_sekli)+" ödenecektir.";}},
        {no:"Madde 3",t:"Teslim ve Devir",b:function(d){return "Teslim: "+V(d.teslim)+". "+V(d.devir)+"";}},
        {no:"Madde 4",t:"Ayıp ve Sorumluluk",b:function(d){return "Satıcının bildirdiği kusurlar: "+V(d.ayip_beyani)+" Alıcı, malı görüp inceleyerek satın aldığını kabul eder; satıcının ayıba karşı tekeffül sorumluluğu hakkında TBK m.219 vd. hükümleri saklıdır.";}},
        {no:"Madde 5",t:"Taraflarca Eklenen Özel Hükümler",b:ekMadde}]};
      NCX["vtr_tr_ibraname"]={n:"İBRA VE FERAGAT SÖZLEŞMESİ",sn:"Karşılıklı ibra beyanı",A:"İbra Eden",B:"İbra Edilen",cl:[
        {no:"Madde 1",t:"Taraflar ve İlişki",b:function(d){return V(d.taraf1)+" ile "+V(d.taraf2)+" arasındaki "+V(d.iliski)+" ilişkisi kapsamında işbu ibra ve feragat sözleşmesi düzenlenmiştir.";}},
        {no:"Madde 2",t:"İbra Kapsamı",b:function(d){return V(d.kapsam)+" Bu sözleşme kapsamında yapılan ödeme: "+V(d.odeme,"yoktur")+".";}},
        {no:"Madde 3",t:"Saklı Haklar ve Geçerlilik",b:function(d){return "Saklı tutulan haklar: "+V(d.saklama,"yoktur")+". İş ilişkisinden doğan alacaklara ilişkin ibranamelerde 6098 sayılı TBK m.420'de öngörülen şekil ve süre şartlarının geçerlilik koşulu olduğunu taraflar bilmektedir.";}},
        {no:"Madde 4",t:"Taraflarca Eklenen Özel Hükümler",b:ekMadde}]};
    }

    /* ── ulke filtresi: TR'de sadece TR seti; digerlerinde TR seti gizli ── */
    var deBak=null, lastMode=null;
    function ccNow(){ try{ return (typeof CC!=="undefined"&&CC)?String(CC):"DE"; }catch(e){ return "DE"; } }
    function syncTR(){
      var tr=(ccNow()==="TR"), mode=tr?"TR":"DE";
      if(mode===lastMode) return;
      lastMode=mode;
      if(tr){
        if(!deBak){ deBak={}; for(var k in CT){ if(!TRK[k]){ deBak[k]=CT[k]; delete CT[k]; } } }
        for(var t in TRK){ CT[t]=TRK[t]; }
      } else {
        for(var t2 in TRK){ delete CT[t2]; }
        if(deBak){ for(var d2 in deBak){ CT[d2]=deBak[d2]; } deBak=null; }
      }
      try{ renderCat(); }catch(e){}
    }
    syncTR();
    setInterval(syncTR, 700);
  }catch(e){} }, 0);
CHJS;

$src = str_replace($anchor, $anchor.$js, $src);
echo "  ✓ [1] 5 TR sozlesmesi + ulke filtresi boot'a enjekte edildi\n";

/* TR'de modul gorunurlugu: country-gate'i TR icin ac */
$css = "\n<style id=\"ch-vst-tr-css\">\n"
     . "/* CH_VST_TR — Vertrag Studio TR hukukunda GORUNUR */\n"
     . "body.ch-cc-nonde[data-chcc=\"TR\"] #nav-vst{display:flex !important}\n"
     . "body.ch-cc-nonde[data-chcc=\"TR\"] #ch-pill-vst{display:inline-flex !important}\n"
     . "body.ch-cc-nonde[data-chcc=\"TR\"] .vst-card:has([data-tg=\"vst\"]){display:block !important}\n"
     . "</style>";
$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$src = substr($src,0,$pos).$css."\n".substr($src,$pos);
echo "  ✓ [2] TR'de modul gorunurlugu acildi (nav + pill + kart)\n";

$tmp = tempnam(sys_get_temp_dir(),'vt').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-vsttr-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_VST_TR uygulandi. Hukuk=TR iken Vertrag Studio'da 5 Turk sozlesmesi\n";
echo "   (Anlasmali Bosanma Protokolu dahil) listelenir; diger hukuklarda gizlidir.\n";
echo "   Her sozlesmede 'Kendi Maddeleriniz' adimi vardir.\n";
