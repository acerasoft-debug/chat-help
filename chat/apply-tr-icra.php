<?php
/**
 * ChatHelp — apply-tr-icra (CH_TR_ICRA) — Icra'ya OZEL kategori derinlestirme
 *  💸 "Icra ve Borc" kategorisi 4 -> 18 belge: BORCLU ve ALACAKLI tarafi
 *  tam kapsam, hepsi IIK'nin yerlesik hukumleriyle ve SURE uyarilariyla:
 *   Borclu: kambiyo itirazi (m.168-169, 5 GUN!), gecikmis itiraz (m.65),
 *   memur islemi sikayeti (m.16, 7 gun), haczedilmezlik (m.82), emekli
 *   maasi (5510 m.93), meskeniyet (m.82/12), istihkak (m.96), icranin
 *   geri birakilmasi (m.71), tahliye emrine itiraz (m.269), dosya kapama.
 *   Alacakli: takip talebi, haciz talebi, itirazin kaldirilmasi (m.68),
 *   dosya yenileme.
 *  Ayni merge deseni — anchor'siz append.
 * KULLANIM: pull-updates.php?files=apply-tr-icra.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-tr-icra BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_TR_ICRA')!==false) exit("Zaten ekli (CH_TR_ICRA).\n");

$bundle = <<<'HTML'
<script id="ch-tr-icra">
/* CH_TR_ICRA — Icra ve Borc kategorisi derinlestirme (+14 IIK belgesi) */
try{(function(){
  function Pe(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}

  var NEWCATS={ tr_icra:{n:"İcra ve Borç",ic:"💸"} };

  var E={
    /* ── BORÇLU TARAFI ── */
    tr_icra_kambiyo_itiraz:{ic:"🧾",n:"Kambiyo Takibinde Borca/İmzaya İtiraz (İİK m.168 — 5 GÜN!)",cat:"tr_icra",tier:"komplex",q:[Pe("mahkeme","İcra Hukuk Mahkemesi (icra dairesinin bulunduğu yer)",true),Pe("dosya","İcra dairesi ve dosya numarası",true),Pe("teblig","Ödeme emrinin tebliğ tarihi (SÜRE: 5 GÜN — icra mahkemesine!)",true),Pe("senet","Takibe konu senet/çek/poliçe bilgisi",true),Pe("itiraz_turu","İtiraz türü",true,["Borca itiraz (ödedim/borç yok)","İmzaya itiraz (imza bana ait değil)","Her ikisi"]),Pe("gerekce","Gerekçeniz (kendi cümlelerinizle)",true),Pe("iletisim","Ad, soyad, T.C. kimlik no ve adresiniz",true)]},
    tr_icra_gecikmis_itiraz:{ic:"⏳",n:"Gecikmiş İtiraz (İİK m.65)",cat:"tr_icra",tier:"komplex",q:[Pe("mahkeme","İcra Hukuk Mahkemesi",true),Pe("dosya","İcra dairesi ve dosya numarası",true),Pe("engel","İtirazı süresinde yapamama nedeni (hastalık, yolculuk vb. — belgeli)",true),Pe("engel_bitis","Engelin kalktığı tarih (kalkmasından itibaren 3 gün içinde!)",true),Pe("itiraz","Borca ilişkin itirazınız",true),Pe("iletisim","Ad, soyad, T.C. kimlik no ve adresiniz",true)]},
    tr_icra_sikayet:{ic:"⚖️",n:"İcra Memuru İşlemini Şikâyet (İİK m.16 — 7 gün)",cat:"tr_icra",tier:"komplex",q:[Pe("mahkeme","İcra Hukuk Mahkemesi",true),Pe("dosya","İcra dairesi ve dosya numarası",true),Pe("islem","Şikâyet edilen işlem ve tarihi (öğrenmeden itibaren 7 gün)",true),Pe("gerekce","İşlemin neden hukuka/usule aykırı olduğu",true),Pe("talep","Talep",true,["İşlemin iptali","İşlemin düzeltilmesi"]),Pe("iletisim","Ad, soyad, T.C. kimlik no ve adresiniz",true)]},
    tr_icra_haczedilmezlik:{ic:"🛋️",n:"Haczedilmezlik Şikâyeti (İİK m.82 — 7 gün)",cat:"tr_icra",tier:"komplex",q:[Pe("mahkeme","İcra Hukuk Mahkemesi",true),Pe("dosya","İcra dairesi ve dosya numarası",true),Pe("haciz","Haciz tarihi ve haczedilen eşya/mal",true),Pe("kapsam","Haczedilmezlik nedeni",true,["Lüzumlu ev eşyası","Mesleğin icrası için gerekli eşya/alet","Ekonomik faaliyet için zorunlu hayvan/araç","Diğer (açıklayın)"]),Pe("aciklama","Açıklama (eşyanın niteliği, aileniz için gerekliliği)",true),Pe("iletisim","Ad, soyad, T.C. kimlik no ve adresiniz",true)]},
    tr_icra_emekli_maas:{ic:"🧓",n:"Emekli Maaşı Haczine İtiraz (5510 m.93)",cat:"tr_icra",tier:"standard",q:[Pe("mahkeme","İcra Hukuk Mahkemesi / icra dairesi",true),Pe("dosya","İcra dairesi ve dosya numarası",true),Pe("maas","Emekli aylığınız (SGK) ve haciz yazısı tarihi",true),Pe("riza","Hacze muvafakatiniz var mı?",true,["Hayır — muvafakat vermedim (haczedilemez)","Takipten önce verilmiş (geçersizliğini ileri süreceğim)"]),Pe("iletisim","Ad, soyad, T.C. kimlik no ve adresiniz",true)]},
    tr_icra_meskeniyet:{ic:"🏠",n:"Meskeniyet İddiası (İİK m.82/12 — 7 gün)",cat:"tr_icra",tier:"komplex",q:[Pe("mahkeme","İcra Hukuk Mahkemesi",true),Pe("dosya","İcra dairesi ve dosya numarası",true),Pe("tasinmaz","Haczedilen konut (adres, tapu bilgisi)",true),Pe("haciz_teblig","Haczi öğrenme tarihi (SÜRE: 7 gün)",true),Pe("durum","Konutun 'haline münasip' olduğuna dair açıklama (aile, tek konut)",true),Pe("iletisim","Ad, soyad, T.C. kimlik no ve adresiniz",true)]},
    tr_icra_istihkak:{ic:"📦",n:"İstihkak İddiası — Mal Üçüncü Kişinin (İİK m.96)",cat:"tr_icra",tier:"komplex",q:[Pe("dosya","İcra dairesi ve dosya numarası",true),Pe("haciz","Haciz tarihi ve haczedilen mal",true),Pe("sifat","Sıfatınız",true,["Malın sahibi üçüncü kişiyim","Borçluyum — mal üçüncü kişiye ait"]),Pe("dayanak","Mülkiyet dayanağı (fatura, devir, tapu vb.)",true),Pe("iletisim","Ad, soyad, T.C. kimlik no ve adresiniz",true)]},
    tr_icra_geri_birakma:{ic:"🛑",n:"İcranın Geri Bırakılması Talebi (İİK m.71)",cat:"tr_icra",tier:"komplex",q:[Pe("mahkeme","İcra Hukuk Mahkemesi",true),Pe("dosya","İcra dairesi ve dosya numarası",true),Pe("neden","Neden",true,["Borç takipten sonra ödendi (itfa)","Alacaklı süre verdi (imhal)","Zamanaşımı"]),Pe("belge","Dayanak belge (dekont, ibraname vb.)",true),Pe("iletisim","Ad, soyad, T.C. kimlik no ve adresiniz",true)]},
    tr_icra_tahliye_itiraz:{ic:"🚪",n:"Tahliye Emrine İtiraz (İİK m.269 — 7 gün)",cat:"tr_icra",tier:"komplex",q:[Pe("dosya","İcra dairesi ve dosya numarası",true),Pe("teblig","Tahliye/ödeme emrinin tebliğ tarihi (itiraz: 7 gün)",true),Pe("tasinmaz","Kiralanan taşınmazın adresi",true),Pe("itiraz","İtiraz nedeni",true,["Kira borcum yok/ödedim","Kira sözleşmesine itiraz","Talep edilen tutar hatalı"]),Pe("aciklama","Açıklama (ödeme dekontları vb.)",true),Pe("iletisim","Ad, soyad, T.C. kimlik no ve adresiniz",true)]},
    tr_icra_dosya_kapama:{ic:"✅",n:"Haricen Tahsil / Dosya Kapama Beyanı",cat:"tr_icra",tier:"einfach",q:[Pe("dosya","İcra dairesi ve dosya numarası",true),Pe("sifat","Sıfatınız",true,["Alacaklı — borç haricen tahsil edildi","Borçlu — ödeme yapıldı, kapatma talep ediyorum"]),Pe("odeme","Ödeme bilgisi (tarih, tutar, şekil)",true),Pe("iletisim","Ad, soyad, T.C. kimlik no ve adresiniz",true)]},
    /* ── ALACAKLI TARAFI ── */
    tr_icra_takip_talebi:{ic:"📄",n:"İcra Takip Talebi (ilamsız — alacaklı)",cat:"tr_icra",tier:"standard",q:[Pe("daire","Yetkili icra dairesi (borçlunun yerleşim yeri)",true),Pe("borclu","Borçlu: ad/unvan, T.C./vergi no, adres",true),Pe("alacak","Alacak tutarı ve dayanağı (fatura, sözleşme, kira vb.)",true),Pe("faiz","Faiz talebi",false,["Yasal faiz","Ticari (avans) faiz","Faiz istemiyorum"]),Pe("iletisim","Alacaklı: ad/unvan, T.C./vergi no, adres, IBAN",true)]},
    tr_icra_haciz_talebi:{ic:"🔨",n:"Haciz Talebi (takip kesinleşince — alacaklı)",cat:"tr_icra",tier:"einfach",q:[Pe("dosya","İcra dairesi ve dosya numarası",true),Pe("kesinlesme","Takibin kesinleştiği bilgisi (itiraz yok / kaldırıldı)",true),Pe("hedef","Haciz istenen mal/haklar",true,["Banka hesapları","Maaş","Araç","Taşınmaz","Menkul (ev/işyeri haczi)","Tümü"]),Pe("iletisim","Alacaklı/vekili: ad ve adres",true)]},
    tr_icra_itirazin_kaldirilmasi:{ic:"⚡",n:"İtirazın Kaldırılması Talebi (İİK m.68 — 6 ay)",cat:"tr_icra",tier:"komplex",q:[Pe("mahkeme","İcra Hukuk Mahkemesi",true),Pe("dosya","İcra dairesi ve dosya numarası",true),Pe("itiraz_teblig","İtirazın tebliğ tarihi (başvuru süresi: 6 ay)",true),Pe("belge","Elinizdeki belge (İİK m.68 kapsamında: imzası ikrar edilmiş senet, resmî belge vb.)",true),Pe("talep_tutar","Takip konusu tutar",true),Pe("iletisim","Alacaklı: ad/unvan ve adres",true)]},
    tr_icra_yenileme:{ic:"🔄",n:"Dosya Yenileme Talebi (işlemden kaldırılan dosya)",cat:"tr_icra",tier:"einfach",q:[Pe("dosya","İcra dairesi ve dosya numarası",true),Pe("durum","Dosyanın işlemden kalkma nedeni/tarihi",true),Pe("talep","Yenileme sonrası istenen işlem (haciz vb.)",false),Pe("iletisim","Alacaklı/vekili: ad ve adres",true)]}
  };

  function boot(){
    try{
      if(typeof DOCS==="undefined"||typeof CATS==="undefined") return false;
      for(var k in E){ if(!DOCS[k]) DOCS[k]=E[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=E[k].tier||"standard"; }
      for(var nc in NEWCATS){ if(!CATS[nc]) CATS[nc]={n:NEWCATS[nc].n,ic:NEWCATS[nc].ic,docs:[],country:"TR"}; if(!CATS[nc].docs) CATS[nc].docs=[]; }
      for(var k2 in E){ var c2=E[k2].cat; if(CATS[c2]&&CATS[c2].docs&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in NEWCATS) if(!CAT_LABELS[cl]) CAT_LABELS[cl]=NEWCATS[cl].n; }
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
echo "  ✓ Icra kategorisine +14 IIK belgesi eklendi (borclu + alacakli tarafi)\n";

$tmp = tempnam(sys_get_temp_dir(),'ti').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-tricra-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
$nIC = preg_match_all('/\btr_icra_[a-z0-9_]+:\{ic:/', $src, $m);
echo "\n✓ CH_TR_ICRA uygulandi. Icra kategorisi toplam ~".($nIC>0?$nIC:18)." belge (mevcut 4 + yeni 14).\n";
