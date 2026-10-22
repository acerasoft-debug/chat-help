<?php
/**
 * ChatHelp — apply-tr-expand4 (CH_TR_EXPAND4) — Turkiye dilekce katalogu
 *  genisletme: 3 YENI kategori + 12 dilekce (canli CH_TR_EXPAND_MORE
 *  kalibinin birebir klonu — inject() mantigi dump ile dogrulandi):
 *   🏛 İcra ve Borç Takibi: takibe itiraz (İİK 62), menfi tespit (İİK 72),
 *      taksitlendirme/odeme taahhudu (İİK 111), haczedilmezlik sikayeti (İİK 82)
 *   👨‍👩‍👧 Aile Hukuku: anlasmali bosanma (TMK 166/3), nafaka artirim,
 *      velayet degisikligi (TMK 183), tedbir nafakasi (TMK 169)
 *   📋 İdari Basvurular: bilgi edinme (4982), idari itiraz (İYUK 11),
 *      trafik cezasina itiraz, belediye imar/kacak yapi sikayeti
 *  Sadece EKLEMELI (</body> oncesi tek script). Mevcut kod DEGISMEZ.
 *  node --check ✓ + fonksiyonel harness ✓ (12 dilekce + 3 kategori merge).
 * KULLANIM: pull2.php?key=...&files=apply-tr-expand4.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-tr-expand4 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_TR_EXPAND4')!==false) exit("Zaten ekli (CH_TR_EXPAND4).\n");
if (strpos($src,'CH_TR_EXPAND_MORE')===false) exit("HATA: CH_TR_EXPAND_MORE yok — once temel TR katalogu gerekli.\n");

$block = <<<'HTMLBLOCK'
<script id="ch-tr-expand4-js">
/* CH_TR_EXPAND4 — TR katalog genisletme: icra + aile + idari (12 dilekce) */
try{(function(){
  function Pe(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var NEWCATS={
    "trx_icra":{n:"İcra ve Borç Takibi",ic:"🏛"},
    "trx_aile":{n:"Aile Hukuku",ic:"👨‍👩‍👧"},
    "trx_idari":{n:"İdari Başvurular ve Dilekçeler",ic:"📋"}
  };
  var E={
    "trx_icra_takibe_itiraz":{ic:"✋",n:"İcra Takibine İtiraz Dilekçesi (İİK m.62 — 7 gün)",cat:"trx_icra",tier:"standard",q:[Pe("itiraz_eden_ad","İtiraz eden borçlunun adı soyadı",true),Pe("itiraz_eden_tckn","İtiraz edenin TC kimlik numarası",true),Pe("icra_mudurlugu","İcra Müdürlüğü ve dosya numarası (örn. İstanbul 5. İcra Müd. 2026/1234 E.)",true),Pe("teblig_tarihi","Ödeme emrinin tebliğ tarihi (itiraz süresi tebliğden itibaren 7 gündür)",true),Pe("itiraz_kapsami","İtirazın kapsamı",true,["Borcun tamamına itiraz","Borcun bir kısmına itiraz","Faize itiraz","İmzaya itiraz (senet/imza bana ait değil)","Yetkiye itiraz"]),Pe("itiraz_gerekcesi","İtiraz gerekçeniz (borç neden yok/ödendi/hatalı?)",true),Pe("kismi_tutar","Kısmi itirazda kabul edilen tutar (varsa)",false)]},
    "trx_icra_menfi_tespit":{ic:"⚖️",n:"Menfi Tespit Davası Dilekçesi (İİK m.72)",cat:"trx_icra",tier:"komplex",q:[Pe("davaci_ad","Davacının (borçlu görünenin) adı soyadı",true),Pe("davaci_tckn","Davacının TC kimlik numarası",true),Pe("davali_ad","Davalının (alacaklı görünenin) adı soyadı/unvanı",true),Pe("icra_dosyasi","İlgili icra dosyası (müdürlük + esas no, takip başladıysa)",false),Pe("iddia_tutar","İddia edilen borç tutarı",true),Pe("borclu_olmama_gerekcesi","Borçlu olmadığınızın gerekçesi ve delilleriniz (ödeme dekontları, sözleşme, tanık vb.)",true),Pe("mahkeme","Görevli mahkeme (Asliye Hukuk / Asliye Ticaret — takibin yapıldığı veya davalının yerleşim yeri)",true)]},
    "trx_icra_taksitlendirme":{ic:"📆",n:"Borç Taksitlendirme / Ödeme Taahhüdü (İİK m.111)",cat:"trx_icra",tier:"einfach",q:[Pe("borclu_ad","Borçlunun adı soyadı",true),Pe("borclu_tckn","Borçlunun TC kimlik numarası",true),Pe("icra_dosyasi","İcra Müdürlüğü ve dosya numarası",true),Pe("toplam_borc","Toplam borç tutarı (faiz ve masraflar dahil)",true),Pe("taksit_plani","Önerilen taksit planı (İİK m.111: ilk taksit peşin, kalan en fazla 3 ayda 3 eşit taksit)",true),Pe("ilk_odeme_tarihi","İlk ödeme tarihi",true)]},
    "trx_icra_haczedilmezlik":{ic:"🛡️",n:"Haczedilmezlik Şikayeti (İİK m.82)",cat:"trx_icra",tier:"standard",q:[Pe("borclu_ad","Borçlunun adı soyadı",true),Pe("icra_dosyasi","İcra Müdürlüğü ve dosya numarası",true),Pe("haciz_tarihi","Haciz işleminin tarihi (şikayet süresi öğrenmeden itibaren 7 gündür)",true),Pe("haczedilen","Haczedilen mal/hak (örn. maaş, ev eşyası, emekli maaşı)",true),Pe("haczedilmezlik_nedeni","Haczedilmezlik nedeni",true,["Emekli maaşı (SGK, rızam yok)","Maaşın 1/4'ünden fazlası haczedilemez","Lüzumlu ev eşyası","Mesleği için zorunlu araç/gereç","Diğer (İİK m.82 kapsamı)"]),Pe("icra_mahkemesi","Başvurulacak İcra Hukuk Mahkemesi (yer)",true)]},
    "trx_aile_anlasmali_bosanma":{ic:"🤝",n:"Anlaşmalı Boşanma Protokolü + Dava Dilekçesi (TMK m.166/3)",cat:"trx_aile",tier:"komplex",q:[Pe("es1_ad","1. eşin adı soyadı ve TC kimlik numarası",true),Pe("es2_ad","2. eşin adı soyadı ve TC kimlik numarası",true),Pe("evlilik_tarihi","Evlilik tarihi (anlaşmalı boşanma için evlilik en az 1 yıl sürmüş olmalıdır)",true),Pe("cocuk_durumu","Müşterek çocuk var mı?",true,["Yok","Var (aşağıda velayet düzenlemesini yazın)"]),Pe("velayet_duzeni","Çocuk(lar) için velayet ve kişisel ilişki (görüşme) düzeni",false),Pe("nafaka_anlasmasi","Nafaka anlaşması (yoksulluk/iştirak nafakası tutarları veya karşılıklı feragat)",true),Pe("mal_paylasimi","Mal paylaşımı ve tazminat anlaşmasının özeti",true),Pe("mahkeme","Başvurulacak Aile Mahkemesi (taraflardan birinin yerleşim yeri)",true)]},
    "trx_aile_nafaka_artirim":{ic:"📈",n:"Nafaka Artırım Davası Dilekçesi (TMK m.176/331)",cat:"trx_aile",tier:"standard",q:[Pe("talep_eden_ad","Nafaka alacaklısının (talep edenin) adı soyadı",true),Pe("yukumlu_ad","Nafaka yükümlüsünün adı soyadı",true),Pe("mevcut_nafaka","Mevcut nafaka tutarı ve hangi mahkeme kararıyla/tarihle belirlendiği",true),Pe("talep_tutar","Talep edilen yeni aylık tutar",true),Pe("artis_gerekcesi","Artış gerekçesi",true,["Enflasyon / alım gücü kaybı","Çocuğun ihtiyaçlarının artması (okul, sağlık)","Yükümlünün gelirinin artması","Birden fazlası"]),Pe("gerekce_detay","Gerekçenin kısa açıklaması ve varsa belgeler",true),Pe("mahkeme","Başvurulacak Aile Mahkemesi",true)]},
    "trx_aile_velayet_degisiklik":{ic:"🧒",n:"Velayet Değişikliği Talebi (TMK m.183)",cat:"trx_aile",tier:"komplex",q:[Pe("talep_eden_ad","Talep eden ebeveynin adı soyadı",true),Pe("mevcut_veli_ad","Mevcut velayet sahibinin adı soyadı",true),Pe("cocuk_bilgi","Çocuk(lar)ın adı ve yaşı",true),Pe("mevcut_karar","Velayeti düzenleyen mevcut mahkeme kararı (mahkeme, tarih, esas no)",true),Pe("degisiklik_gerekcesi","Velayet değişikliğini gerektiren esaslı değişiklik (çocuğun üstün yararı yönünden somut olaylar)",true),Pe("cocuk_gorusu","Çocuğun görüşü/tercihi (yaşı ve olgunluğu elveriyorsa mahkemece dinlenir)",false),Pe("mahkeme","Başvurulacak Aile Mahkemesi",true)]},
    "trx_aile_tedbir_nafakasi":{ic:"🏠",n:"Tedbir Nafakası Talebi (TMK m.169)",cat:"trx_aile",tier:"standard",q:[Pe("talep_eden_ad","Talep edenin adı soyadı",true),Pe("es_ad","Eşin (yükümlünün) adı soyadı",true),Pe("durum","Durum",true,["Boşanma davası devam ediyor (dava dosya no aşağıda)","Ayrı yaşama hakkı doğdu, henüz dava yok"]),Pe("dava_dosyasi","Devam eden boşanma davasının mahkemesi ve esas numarası (varsa)",false),Pe("aylik_ihtiyac","Kendiniz ve çocuk(lar) için talep edilen aylık tutar",true),Pe("gerekce","İhtiyaç gerekçesi (gelir durumu, çocukların gideri, kira vb.)",true)]},
    "trx_idari_bilgi_edinme":{ic:"🔍",n:"Bilgi Edinme Başvurusu (4982 sayılı Kanun)",cat:"trx_idari",tier:"einfach",q:[Pe("basvuran_ad","Başvuranın adı soyadı",true),Pe("basvuran_tckn","TC kimlik numarası",true),Pe("kurum","Başvurulan kurum/idare",true),Pe("istenen_bilgi","İstenen bilgi veya belgenin açık tanımı",true),Pe("iletisim","Cevabın gönderileceği adres veya e-posta",true)]},
    "trx_idari_itiraz":{ic:"📝",n:"İdari İşleme İtiraz / Üst Makama Başvuru (İYUK m.11)",cat:"trx_idari",tier:"standard",q:[Pe("basvuran_ad","Başvuranın adı soyadı ve TC kimlik numarası",true),Pe("kurum","İşlemi yapan idare/kurum",true),Pe("islem_bilgi","İdari işlemin tarihi ve sayısı (tebliğ edilen yazı)",true),Pe("islem_ozeti","İşlemin özeti (ne karar verildi?)",true),Pe("itiraz_gerekcesi","İtiraz gerekçeleriniz (hukuka aykırılık, maddi hata, mağduriyet)",true),Pe("talep","Talebiniz",true,["İşlemin geri alınması","İşlemin değiştirilmesi","Yeni bir işlem yapılması"])]},
    "trx_idari_trafik_cezasi":{ic:"🚗",n:"Trafik Cezasına İtiraz Dilekçesi (Sulh Ceza — 15 gün)",cat:"trx_idari",tier:"standard",q:[Pe("itiraz_eden_ad","İtiraz edenin adı soyadı",true),Pe("itiraz_eden_tckn","TC kimlik numarası",true),Pe("tutanak_bilgi","Ceza tutanağının tarihi ve seri/sıra numarası",true),Pe("teblig_tarihi","Tutanağın tebliğ/öğrenme tarihi (itiraz süresi 15 gündür)",true),Pe("plaka","Araç plakası",true),Pe("ceza_tutari","Ceza tutarı",true),Pe("itiraz_gerekcesi","İtiraz gerekçesi",true,["Aracı o tarihte ben kullanmıyordum","Trafik işareti/levha yoktu veya görünmüyordu","Radar/ölçüm hatalı","Tutanakta maddi hata var","Diğer"]),Pe("gerekce_detay","Gerekçenin kısa açıklaması ve delilleriniz",true),Pe("hakimlik","Başvurulacak Sulh Ceza Hâkimliği (cezanın kesildiği yer)",true)]},
    "trx_idari_imar_sikayet":{ic:"🏗️",n:"Belediyeye İmar / Kaçak Yapı Şikayeti",cat:"trx_idari",tier:"einfach",q:[Pe("sikayet_eden_ad","Şikayet edenin adı soyadı",true),Pe("belediye","Başvurulan belediye",true),Pe("yapi_adresi","Şikayet konusu yapının açık adresi",true),Pe("sikayet_detay","Şikayet detayı (kaçak kat, ruhsatsız yapı, imara aykırılık vb.)",true),Pe("talep","Talebiniz (denetim, yıkım kararı, para cezası uygulanması vb.)",true)]}
  };
  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      if(typeof CATS==="undefined"||!CATS) return false;
      for(var k in E){ if(!DOCS[k]) DOCS[k]=E[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=E[k].tier||"standard"; }
      for(var nc in NEWCATS){ if(!CATS[nc]) CATS[nc]={n:NEWCATS[nc].n,ic:NEWCATS[nc].ic,docs:[],country:"TR"}; if(!CATS[nc].docs) CATS[nc].docs=[]; }
      for(var k2 in E){ var c2=E[k2].cat; if(CATS[c2]&&CATS[c2].docs&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in NEWCATS) CAT_LABELS[cl]=NEWCATS[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in NEWCATS){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in E){ var c3=E[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:E[k3].ic,t:E[k3].n,tier:E[k3].tier||"standard"}); } }
      }
      return !!(DOCS.trx_icra_takibe_itiraz);
    }catch(e){ return false; }
  }
  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'t4').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-trexp4-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_TR_EXPAND4')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_TR_EXPAND4 diskte (".strlen($chk)." bayt)\n";
echo "\n✓ TR katalog genisletme canli — 3 yeni kategori, 12 yeni dilekce:\n";
echo "   🏛 İcra ve Borç Takibi (4): takibe itiraz, menfi tespit, taksitlendirme, haczedilmezlik\n";
echo "   👨‍👩‍👧 Aile Hukuku (4): anlaşmalı boşanma, nafaka artırım, velayet, tedbir nafakası\n";
echo "   📋 İdari Başvurular (4): bilgi edinme, idari itiraz, trafik cezası, imar şikayeti\n";
echo "   (TR toplami 7 kategori: miras, kira, kat mülkiyeti, iş/tüketici + bu 3 yeni)\n";
