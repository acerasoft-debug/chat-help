<?php
/**
 * ChatHelp — ABD/Kanada/İngiltere/Avustralya vizesi destekleyici belgeleri (Türkiye altında)
 * NOT: İsviçre ve Norveç zaten Schengen alanına dahil (apply-turkiye-schengen.php ile karşılanıyor),
 *      bu yüzden onlar için ayrı kategori açılmadı.
 * KULLANIM: chat-help.com/chat/apply-turkiye-visa-countries.php -> opcache-reset.php. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-turkiye-visa-countries BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_TR_VISA_COUNTRIES")!==false) exit("Zaten ekli.\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-trvisa-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_TR_VISA_COUNTRIES — ABD/Kanada/İngiltere/Avustralya vizesi destekleyici belgeler (deferred yükleme) */
try{(function(){
  function Pv(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}

  var VC={
    tr_visa_usa:{n:"ABD Vizesi Belgeleri",ic:"🇺🇸"},
    tr_visa_canada:{n:"Kanada Vizesi Belgeleri",ic:"🇨🇦"},
    tr_visa_uk:{n:"İngiltere Vizesi Belgeleri",ic:"🇬🇧"},
    tr_visa_australia:{n:"Avustralya Vizesi Belgeleri",ic:"🇦🇺"}
  };
  var VC_ORDER=["tr_visa_usa","tr_visa_canada","tr_visa_uk","tr_visa_australia"];

  var V={
    /* ABD */
    tr_usa_amac_beyani:{ic:"✍️",n:"Seyahat Amacı Beyanı (DS-160 Desteği)",cat:"tr_visa_usa",tier:"standard",q:[Pv("amac","Seyahat amacı",true,["Turizm","Aile/Arkadaş ziyareti","İş","Konferans","Eğitim"]),Pv("tarihler","Seyahat tarihleri",true),Pv("guzergah","Ziyaret edilecek şehirler",false)]},
    tr_usa_davet_aile:{ic:"👨‍👩‍👧",n:"Davet Mektubu (Aile/Arkadaş)",cat:"tr_visa_usa",tier:"standard",q:[Pv("davet_eden","Davet eden: ad, adres, statüsü (vatandaş/oturum)",true),Pv("davetli","Davet edilen kişinin bilgileri",true),Pv("iliski","İlişkiniz",true),Pv("tarihler","Ziyaret tarihleri",true)]},
    tr_usa_is_davet:{ic:"💼",n:"İş Daveti / Konferans Daveti",cat:"tr_visa_usa",tier:"standard",q:[Pv("davet_eden_firma","Davet eden kurum: ad ve adres",true),Pv("amac","Etkinlik/toplantı amacı",true),Pv("tarihler","Tarihler",true)]},
    tr_usa_isveren_izin:{ic:"📄",n:"İşveren İzin Yazısı",cat:"tr_visa_usa",tier:"standard",q:[Pv("isveren","İşveren: ad ve adres",true),Pv("pozisyon","Pozisyonunuz",true),Pv("tarihler","İzin tarihleri",true),Pv("maas","Maaş bilgisi eklensin mi?",false,["Evet","Hayır"])]},
    tr_usa_mali_beyan:{ic:"💶",n:"Mali Durum Beyanı",cat:"tr_visa_usa",tier:"standard",q:[Pv("gelir_kaynagi","Gelir kaynağı",true,["Maaş","Kendi işi","Emekli maaşı"]),Pv("aylik_gelir","Aylık gelir (TL, opsiyonel)",false)]},
    tr_usa_baglilik:{ic:"🏠",n:"Türkiye'ye Bağlılık Beyanı (Ties to Home Country)",cat:"tr_visa_usa",tier:"komplex",q:[Pv("is_durumu","İş durumu (işveren, pozisyon, süre)",true),Pv("mulk","Mülk/varlık (varsa)",false),Pv("aile_baglari","Türkiye'deki aile bağları",true),Pv("donus_niyeti","Dönüş planınız/niyetiniz",true)]},
    tr_usa_seyahat_plani:{ic:"🗺️",n:"Seyahat Planı",cat:"tr_visa_usa",tier:"einfach",q:[Pv("tarihler","Gidiş-dönüş tarihleri",true),Pv("sehirler","Ziyaret edilecek şehirler",true),Pv("konaklama","Konaklama bilgisi",false)]},

    /* Kanada */
    tr_canada_amac_beyani:{ic:"✍️",n:"Seyahat Amacı Beyanı",cat:"tr_visa_canada",tier:"standard",q:[Pv("amac","Seyahat amacı",true,["Turizm","Aile/Arkadaş ziyareti","İş","Eğitim"]),Pv("tarihler","Seyahat tarihleri",true)]},
    tr_canada_davet:{ic:"👨‍👩‍👧",n:"Davet Mektubu",cat:"tr_visa_canada",tier:"standard",q:[Pv("davet_eden","Davet eden: ad, adres, statüsü",true),Pv("davetli","Davet edilen kişi",true),Pv("iliski","İlişkiniz",true),Pv("tarihler","Ziyaret tarihleri",true)]},
    tr_canada_isveren_izin:{ic:"📄",n:"İşveren İzin Yazısı",cat:"tr_visa_canada",tier:"standard",q:[Pv("isveren","İşveren: ad ve adres",true),Pv("pozisyon","Pozisyonunuz",true),Pv("tarihler","İzin tarihleri",true)]},
    tr_canada_mali_beyan:{ic:"💶",n:"Mali Durum Beyanı",cat:"tr_visa_canada",tier:"standard",q:[Pv("gelir_kaynagi","Gelir kaynağı",true),Pv("aylik_gelir","Aylık gelir (TL, opsiyonel)",false)]},
    tr_canada_baglilik:{ic:"🏠",n:"Türkiye'ye Bağlılık Beyanı",cat:"tr_visa_canada",tier:"komplex",q:[Pv("is_durumu","İş durumu",true),Pv("aile_baglari","Aile bağları",true),Pv("donus_niyeti","Dönüş niyetiniz",true)]},
    tr_canada_konaklama:{ic:"🏨",n:"Konaklama Bilgi Formu",cat:"tr_visa_canada",tier:"einfach",q:[Pv("tur","Konaklama türü",true,["Otel","Ev sahibinde kalış"]),Pv("adres","Adres",true),Pv("tarihler","Tarihler",true)]},
    tr_canada_ogrenim_amac:{ic:"🎓",n:"Öğrenim Amacı Beyanı (Study Permit)",cat:"tr_visa_canada",tier:"komplex",q:[Pv("okul","Kabul aldığınız okul",true),Pv("program","Program/bölüm",true),Pv("sure","Eğitim süresi",false),Pv("donus_plani","Mezuniyet sonrası planınız",false)]},

    /* İngiltere */
    tr_uk_amac_beyani:{ic:"✍️",n:"Seyahat Amacı Beyanı",cat:"tr_visa_uk",tier:"standard",q:[Pv("amac","Seyahat amacı",true,["Turizm","Aile/Arkadaş ziyareti","İş","Eğitim"]),Pv("tarihler","Seyahat tarihleri",true)]},
    tr_uk_davet:{ic:"👨‍👩‍👧",n:"Davet Mektubu (Aile/Arkadaş)",cat:"tr_visa_uk",tier:"standard",q:[Pv("davet_eden","Davet eden: ad, adres, statüsü",true),Pv("davetli","Davet edilen kişi",true),Pv("iliski","İlişkiniz",true),Pv("tarihler","Ziyaret tarihleri",true)]},
    tr_uk_isveren_izin:{ic:"📄",n:"İşveren İzin Yazısı",cat:"tr_visa_uk",tier:"standard",q:[Pv("isveren","İşveren: ad ve adres",true),Pv("pozisyon","Pozisyonunuz",true),Pv("tarihler","İzin tarihleri",true)]},
    tr_uk_mali_beyan:{ic:"💶",n:"Mali Durum Beyanı",cat:"tr_visa_uk",tier:"standard",q:[Pv("gelir_kaynagi","Gelir kaynağı",true),Pv("aylik_gelir","Aylık gelir (TL, opsiyonel)",false)]},
    tr_uk_konaklama:{ic:"🏨",n:"Konaklama Bilgi Formu",cat:"tr_visa_uk",tier:"einfach",q:[Pv("tur","Konaklama türü",true,["Otel","Ev sahibinde kalış"]),Pv("adres","Adres",true),Pv("tarihler","Tarihler",true)]},
    tr_uk_seyahat_plani:{ic:"🗺️",n:"Seyahat Planı",cat:"tr_visa_uk",tier:"einfach",q:[Pv("tarihler","Gidiş-dönüş tarihleri",true),Pv("sehirler","Ziyaret edilecek şehirler",true)]},
    tr_uk_baglilik:{ic:"🏠",n:"Türkiye'ye Bağlılık Beyanı",cat:"tr_visa_uk",tier:"komplex",q:[Pv("is_durumu","İş durumu",true),Pv("aile_baglari","Aile bağları",true),Pv("donus_niyeti","Dönüş niyetiniz",true)]},

    /* Avustralya */
    tr_au_amac_beyani:{ic:"✍️",n:"Seyahat Amacı Beyanı",cat:"tr_visa_australia",tier:"standard",q:[Pv("amac","Seyahat amacı",true,["Turizm","Aile/Arkadaş ziyareti","İş","Eğitim"]),Pv("tarihler","Seyahat tarihleri",true)]},
    tr_au_davet:{ic:"👨‍👩‍👧",n:"Davet Mektubu",cat:"tr_visa_australia",tier:"standard",q:[Pv("davet_eden","Davet eden: ad, adres, statüsü",true),Pv("davetli","Davet edilen kişi",true),Pv("iliski","İlişkiniz",true),Pv("tarihler","Ziyaret tarihleri",true)]},
    tr_au_isveren_izin:{ic:"📄",n:"İşveren İzin Yazısı",cat:"tr_visa_australia",tier:"standard",q:[Pv("isveren","İşveren: ad ve adres",true),Pv("pozisyon","Pozisyonunuz",true),Pv("tarihler","İzin tarihleri",true)]},
    tr_au_mali_beyan:{ic:"💶",n:"Mali Durum Beyanı",cat:"tr_visa_australia",tier:"standard",q:[Pv("gelir_kaynagi","Gelir kaynağı",true),Pv("aylik_gelir","Aylık gelir (TL, opsiyonel)",false)]},
    tr_au_seyahat_plani:{ic:"🗺️",n:"Seyahat Planı",cat:"tr_visa_australia",tier:"einfach",q:[Pv("tarihler","Gidiş-dönüş tarihleri",true),Pv("sehirler","Ziyaret edilecek şehirler",true)]},
    tr_au_baglilik:{ic:"🏠",n:"Türkiye'ye Bağlılık Beyanı",cat:"tr_visa_australia",tier:"komplex",q:[Pv("is_durumu","İş durumu",true),Pv("aile_baglari","Aile bağları",true),Pv("donus_niyeti","Dönüş niyetiniz",true)]}
  };

  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      for(var k in V){ if(!DOCS[k]) DOCS[k]=V[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=V[k].tier||"standard"; }
      if(typeof CATS!=="undefined"&&CATS){
        for(var ck in VC){ if(!CATS[ck]) CATS[ck]={n:VC[ck].n,ic:VC[ck].ic,docs:[],country:"TR"}; if(!CATS[ck].docs) CATS[ck].docs=[]; }
        for(var k2 in V){ var c2=V[k2].cat; if(CATS[c2]&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in VC) CAT_LABELS[cl]=VC[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in VC){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in V){ var c3=V[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:V[k3].ic,t:V[k3].n,tier:V[k3].tier||"standard"}); } }
      }
      return (typeof DOCS!=="undefined" && DOCS.tr_usa_amac_beyani)?true:false;
    }catch(e){ return false; }
  }

  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src!==$start){ file_put_contents($file,$src); echo "Vize ulkeleri eklendi (CH_TR_VISA_COUNTRIES) - ABD/Kanada/Ingiltere/Avustralya, ~28 belge.\n"; }
else echo "degisiklik yok\n";
echo "\nNOT: Isvicre/Norvec zaten Schengen kategorisiyle karsilaniyor (ayri kategori acilmadi).\n";
echo "SONRA: opcache-reset.php. SIL: rm apply-turkiye-visa-countries.php\n";
echo "Test: TR sec -> ABD/Kanada/Ingiltere/Avustralya Vizesi Belgeleri kategorileri gorunmeli.\n";
