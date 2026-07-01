<?php
/**
 * ChatHelp — Türkiye: Schengen Vizesi belge kategorisi (destekleyici mektup/beyan taslakları)
 * ---------------------------------------------------------------------------------------------
 *  NOT: Resmi Schengen başvuru formu AB'nin sabit formu olduğu için burada ÜRETİLMEZ.
 *  Burada üretilenler başvuruya EK olarak sunulan destekleyici belgeler (davet mektubu,
 *  mali beyan, izin yazısı vb.) — gerçek başvuru + randevu işlemi kullanıcı tarafından yapılır.
 *  Otomatik randevu botu YOKTUR (kullanım şartları + kara borsa riski nedeniyle bilerek eklenmedi).
 *
 * KULLANIM: chat-help.com/chat/apply-turkiye-schengen.php -> opcache-reset.php. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-turkiye-schengen BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_TR_SCHENGEN")!==false) exit("Zaten ekli (CH_TR_SCHENGEN).\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-trschengen-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_TR_SCHENGEN — Schengen vizesi destekleyici belgeler (deferred yükleme) */
try{(function(){
  function Ps(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var CAT_KEY="tr_schengen";
  var CAT_INFO={n:"Schengen Vizesi Belgeleri",ic:"🛂"};

  var S={
    tr_schengen_amac_beyani:{ic:"✍️",n:"Seyahat Amacı Beyanı (Cover Letter)",cat:CAT_KEY,tier:"standard",q:[Ps("ulke","Gidilecek Schengen ülkesi",true),Ps("amac","Seyahat amacı",true,["Turizm","Aile/Arkadaş ziyareti","İş","Konferans/Etkinlik","Eğitim"]),Ps("tarihler","Seyahat tarihleri (gidiş-dönüş)",true),Ps("guzergah","Ziyaret edilecek şehirler",false),Ps("ek_bilgi","Ek açıklama (opsiyonel)",false)]},
    tr_schengen_davet_aile:{ic:"👨‍👩‍👧",n:"Davet Mektubu (Aile/Arkadaş)",cat:CAT_KEY,tier:"standard",q:[Ps("davet_eden","Davet eden kişi: ad, adres, Schengen ülkesindeki statüsü",true),Ps("davetli","Davet edilen kişinin ad ve pasaport bilgileri",true),Ps("iliski","Davet edenle ilişkiniz",true),Ps("tarihler","Ziyaret tarihleri",true),Ps("konaklama","Konaklama yeri (davet edenin evi mi?)",false),Ps("masraf","Masrafları kim karşılayacak?",false,["Davet eden","Ziyaretçi","Paylaşımlı"])]},
    tr_schengen_davet_is:{ic:"💼",n:"İş Daveti Mektubu",cat:CAT_KEY,tier:"standard",q:[Ps("davet_eden_firma","Davet eden firma: ad ve adres (Schengen ülkesi)",true),Ps("davetli","Davet edilen kişi ve pozisyonu",true),Ps("amac","Ziyaretin amacı (toplantı, fuar, eğitim vb.)",true),Ps("tarihler","Ziyaret tarihleri",true),Ps("masraf","Masrafları kim karşılıyor?",false,["Davet eden firma","Kendi firması"])]},
    tr_schengen_izin_yazisi:{ic:"📄",n:"İşveren İzin/Görevlendirme Yazısı",cat:CAT_KEY,tier:"standard",q:[Ps("isveren","İşveren firma: ad ve adres",true),Ps("calisan","Çalışanın ad ve pozisyonu",true),Ps("tarihler","İzin/görevlendirme tarihleri",true),Ps("tur","Tür",true,["Yıllık izin onayı","İş seyahati görevlendirmesi"]),Ps("maas_bilgisi","Maaş bilgisi eklensin mi?",false,["Evet","Hayır"])]},
    tr_schengen_mali_beyan:{ic:"💶",n:"Mali Durum / Sponsorluk Beyanı",cat:CAT_KEY,tier:"standard",q:[Ps("basvuran","Başvuran: ad ve TC kimlik no",true),Ps("gelir_kaynagi","Gelir kaynağı",true,["Maaş","Kendi işi","Emekli maaşı","Sponsor (üçüncü kişi)"]),Ps("aylik_gelir","Aylık gelir (TL, opsiyonel)",false),Ps("sponsor","Sponsor varsa: ad ve ilişkisi",false),Ps("taahhut","Masrafları üstlenme taahhüdü metni istenir mi?",false,["Evet","Hayır"])]},
    tr_schengen_konaklama_beyan:{ic:"🏨",n:"Konaklama Bilgi Formu",cat:CAT_KEY,tier:"einfach",q:[Ps("tur","Konaklama türü",true,["Otel rezervasyonu","Ev sahibinde kalış","Kiralık konaklama"]),Ps("adres","Konaklama adresi",true),Ps("tarihler","Giriş-çıkış tarihleri",true),Ps("ev_sahibi","Ev sahibinin ad/iletişimi (varsa)",false)]},
    tr_schengen_seyahat_plani:{ic:"🗺️",n:"Seyahat Planı (İtinerary)",cat:CAT_KEY,tier:"einfach",q:[Ps("ulkeler","Ziyaret edilecek ülke(ler) ve şehirler",true),Ps("tarihler","Gidiş-dönüş tarihleri",true),Ps("gunluk_plan","Günlük plan (kısa özet, opsiyonel)",false),Ps("ulasim","Ulaşım şekli",false,["Uçak","Otobüs","Araç","Karma"])]},
    tr_schengen_ogrenci_izin:{ic:"🎓",n:"Öğrenci İzin Belgesi Talebi (Okuldan)",cat:CAT_KEY,tier:"einfach",q:[Ps("okul","Okul/üniversite adı",true),Ps("ogrenci","Öğrencinin ad ve numarası",true),Ps("tarihler","İzin tarihleri",true),Ps("amac","Seyahat amacı",false)]},
    tr_schengen_emekli_gelir:{ic:"👴",n:"Emekli/Serbest Meslek Gelir Beyanı",cat:CAT_KEY,tier:"einfach",q:[Ps("durum","Durum",true,["Emekli","Serbest meslek/Freelancer"]),Ps("gelir_kaynagi","Gelir kaynağının açıklaması",true),Ps("aylik_gelir","Aylık ortalama gelir (TL, opsiyonel)",false)]},
    tr_schengen_cocuk_izin:{ic:"👶",n:"Reşit Olmayan İçin Ebeveyn İzin Belgesi",cat:CAT_KEY,tier:"standard",q:[Ps("cocuk","Çocuğun ad ve doğum tarihi",true),Ps("seyahat_eden_ebeveyn","Çocukla seyahat edecek kişi",true),Ps("diger_ebeveyn","Diğer ebeveynin ad ve onayı",true),Ps("tarihler","Seyahat tarihleri",true)]}
  };

  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      for(var k in S){ if(!DOCS[k]) DOCS[k]=S[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=S[k].tier||"standard"; }
      if(typeof CATS!=="undefined"&&CATS){
        if(!CATS[CAT_KEY]) CATS[CAT_KEY]={n:CAT_INFO.n,ic:CAT_INFO.ic,docs:[],country:"TR"};
        if(!CATS[CAT_KEY].docs) CATS[CAT_KEY].docs=[];
        for(var k2 in S){ if(CATS[CAT_KEY].docs.indexOf(k2)<0) CATS[CAT_KEY].docs.push(k2); }
      }
      if(typeof CAT_LABELS!=="undefined"){ CAT_LABELS[CAT_KEY]=CAT_INFO.n; }
      if(typeof CAT_DOCS!=="undefined"){
        if(!CAT_DOCS[CAT_KEY]) CAT_DOCS[CAT_KEY]=[];
        for(var k3 in S){ var ex=false; for(var i=0;i<CAT_DOCS[CAT_KEY].length;i++){ if(CAT_DOCS[CAT_KEY][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[CAT_KEY].push({k:k3,ic:S[k3].ic,t:S[k3].n,tier:S[k3].tier||"standard"}); }
      }
      return (typeof DOCS!=="undefined" && DOCS.tr_schengen_amac_beyani)?true:false;
    }catch(e){ return false; }
  }

  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src!==$start){ file_put_contents($file,$src); echo "Schengen kategorisi eklendi (CH_TR_SCHENGEN) - 10 destekleyici belge.\n"; }
else echo "degisiklik yok\n";
echo "\nNOT: Resmi Schengen basvuru formu uretilmez (AB'nin sabit formu). Randevu botu yoktur (bilerek).\n";
echo "SONRA: opcache-reset.php. SIL: rm apply-turkiye-schengen.php\n";
echo "Test: TR sec -> Turkiye kategorileri arasinda 'Schengen Vizesi Belgeleri' gorunmeli.\n";
