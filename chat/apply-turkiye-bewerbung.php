<?php
/**
 * ChatHelp — TÜRKİYE: İŞ BAŞVURUSU ve CV KATEGORİSİ (12 belge, çok dilli)
 * -------------------------------------------------------------------------
 *  Almanca Bewerbung modülünün (CH_BEW_INLINE) Türkiye karşılığı — üstüne
 *  ÇOK DİLLİ üretim: CV ve ön yazılar Türkçe / Almanca / İngilizce olarak
 *  ayrı belgeler halinde (Almanya'ya veya yurt dışına başvuran TR kullanıcısı
 *  için özgeçmiş hedef ülkenin dilinde üretilir).
 *  GEREKLİ: apply-turkiye.php (CH_TR_MODULE) + apply-turkiye-bewerbung-backend.php
 *  (belge dilini anahtara göre kilitleyen arka uç talimatı).
 *
 * KULLANIM: pull-updates.php?files=apply-turkiye-bewerbung.php,apply-turkiye-bewerbung-backend.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-turkiye-bewerbung BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_TR_BEW")!==false) exit("Zaten ekli (CH_TR_BEW).\n");
if(strpos($src,"CH_TR_MODULE")===false) exit("CH_TR_MODULE yok — once apply-turkiye.php calistir.\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-trbew-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_TR_BEW — Türkiye: İş Başvurusu ve CV (çok dilli; deferred yükleme) */
try{(function(){
  function Pb(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var CAT_KEY="tr_basvuru";
  var CAT_INFO={n:"İş Başvurusu ve CV",ic:"📋"};

  /* Ortak CV soruları */
  function cvQ(){ return [
    Pb("pozisyon","Hedeflenen pozisyon / meslek",true),
    Pb("kisisel","Ad soyad, telefon, e-posta, şehir",true),
    Pb("deneyim","İş deneyimi (firma, dönem, görevler — her satıra bir tane)",true),
    Pb("egitim","Eğitim (okul, bölüm, yıl)",true),
    Pb("beceriler","Beceriler / uzmanlıklar",false),
    Pb("diller","Diller + seviye (örn. İngilizce B2)",false),
    Pb("sertifikalar","Sertifikalar (opsiyonel)",false),
    Pb("hedef","Hedef ülke/şirket hakkında not (opsiyonel)",false)
  ]; }
  function mektupQ(){ return [
    Pb("firma","Firma adı ve adresi",true),
    Pb("pozisyon","Başvurulan pozisyon",true),
    Pb("kaynak","İlanı nerede gördünüz? (opsiyonel)",false),
    Pb("guclu_yonler","En güçlü yönleriniz / yetkinlikleriniz",true),
    Pb("motivasyon","Neden bu firma / bu pozisyon?",true),
    Pb("baslama","Mümkün başlama tarihi (opsiyonel)",false),
    Pb("ilan","İlan metni (opsiyonel — birebir uyumlu mektup için yapıştırın)",false)
  ]; }

  var B={
    trbew_cv_tr:{ic:"📋",n:"Özgeçmiş (CV) — Türkçe",cat:CAT_KEY,tier:"standard",q:cvQ()},
    trbew_cv_de:{ic:"🇩🇪",n:"CV — Almanca (Lebenslauf)",cat:CAT_KEY,tier:"standard",q:cvQ()},
    trbew_cv_en:{ic:"🇬🇧",n:"CV — İngilizce (Resume)",cat:CAT_KEY,tier:"standard",q:cvQ()},
    trbew_onyazi_tr:{ic:"✉️",n:"Ön Yazı — Türkçe",cat:CAT_KEY,tier:"standard",q:mektupQ()},
    trbew_onyazi_de:{ic:"✉️",n:"Başvuru Mektubu — Almanca (Anschreiben)",cat:CAT_KEY,tier:"standard",q:mektupQ()},
    trbew_onyazi_en:{ic:"✉️",n:"Cover Letter — İngilizce",cat:CAT_KEY,tier:"standard",q:mektupQ()},
    trbew_motivasyon:{ic:"🎯",n:"Motivasyon Mektubu (okul/burs/vize)",cat:CAT_KEY,tier:"standard",q:[Pb("amac","Ne için? (üniversite, burs, vize, program)",true),Pb("kurum","Kurum / program adı",true),Pb("dil","Mektup dili",true,["Türkçe","İngilizce","Almanca"]),Pb("motivasyon","Motivasyonunuz",true),Pb("nitelikler","İlgili deneyim/nitelikleriniz",true),Pb("hedefler","Gelecek hedefleriniz (opsiyonel)",false)]},
    trbew_initiativ:{ic:"📨",n:"Spontane Başvuru — Almanca (Initiativbewerbung)",cat:CAT_KEY,tier:"standard",q:[Pb("firma","Hedef firma ve adresi",true),Pb("alan","İstenen alan / görev",true),Pb("guclu_yonler","Güçlü yönleriniz",true),Pb("motivasyon","Neden bu firma?",true),Pb("baslama","Mümkün başlama tarihi (opsiyonel)",false)]},
    trbew_linkedin:{ic:"💼",n:"LinkedIn Profil Özeti",cat:CAT_KEY,tier:"einfach",q:[Pb("dil","Özet dili",true,["Türkçe","İngilizce"]),Pb("pozisyon","Mevcut/hedef pozisyon",true),Pb("deneyim_ozet","Deneyim özeti (kaç yıl, hangi alanlar)",true),Pb("basarilar","Öne çıkan başarılar (opsiyonel)",false)]},
    trbew_referans:{ic:"📄",n:"Referans Mektubu Taslağı",cat:CAT_KEY,tier:"einfach",q:[Pb("dil","Mektup dili",true,["Türkçe","İngilizce","Almanca"]),Pb("referans_veren","Referans veren: ad, unvan, firma",true),Pb("aday","Aday: ad ve pozisyonu",true),Pb("iliski","Çalışma ilişkisi (ne kadar süre, hangi rol)",true),Pb("guclu_yonler","Vurgulanacak güçlü yönler",true)]},
    trbew_staj:{ic:"🎓",n:"Staj Başvuru Mektubu",cat:CAT_KEY,tier:"einfach",q:[Pb("dil","Mektup dili",true,["Türkçe","İngilizce","Almanca"]),Pb("firma","Firma ve adresi",true),Pb("alan","Staj alanı",true),Pb("donem","Staj dönemi (tarih aralığı)",true),Pb("okul","Okul/bölüm ve sınıf",true),Pb("motivasyon","Motivasyonunuz",true)]},
    trbew_takip:{ic:"📧",n:"Başvuru Takip / Teşekkür E-postası",cat:CAT_KEY,tier:"einfach",q:[Pb("dil","E-posta dili",true,["Türkçe","İngilizce","Almanca"]),Pb("firma","Firma",true),Pb("pozisyon","Başvurulan pozisyon",true),Pb("durum","Durum",true,["Başvuru sonrası takip","Mülakat sonrası teşekkür"]),Pb("tarih","Başvuru/mülakat tarihi (opsiyonel)",false)]}
  };

  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      if(typeof CATS==="undefined"||!CATS.tr_fesih) return false; /* CH_TR_MODULE hazır olmalı */
      for(var k in B){ if(!DOCS[k]) DOCS[k]=B[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=B[k].tier||"standard"; }
      if(!CATS[CAT_KEY]) CATS[CAT_KEY]={n:CAT_INFO.n,ic:CAT_INFO.ic,docs:[],country:"TR"};
      if(!CATS[CAT_KEY].docs) CATS[CAT_KEY].docs=[];
      for(var k2 in B){ if(CATS[CAT_KEY].docs.indexOf(k2)<0) CATS[CAT_KEY].docs.push(k2); }
      if(typeof CAT_LABELS!=="undefined"){ CAT_LABELS[CAT_KEY]=CAT_INFO.n; }
      if(typeof CAT_DOCS!=="undefined"){
        if(!CAT_DOCS[CAT_KEY]) CAT_DOCS[CAT_KEY]=[];
        for(var k3 in B){ var ex=false; for(var i=0;i<CAT_DOCS[CAT_KEY].length;i++){ if(CAT_DOCS[CAT_KEY][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[CAT_KEY].push({k:k3,ic:B[k3].ic,t:B[k3].n,tier:B[k3].tier||"standard"}); }
      }
      return (typeof DOCS!=="undefined" && DOCS.trbew_cv_de)?true:false;
    }catch(e){ return false; }
  }

  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src!==$start){ file_put_contents($file,$src); echo "TR Is Basvurusu ve CV kategorisi eklendi (CH_TR_BEW) - 12 belge, cok dilli (TR/DE/EN).\n"; }
else echo "degisiklik yok\n";
echo "\nGEREKLI: apply-turkiye-bewerbung-backend.php de calistirilmali (belge dili kilidi + CV formati).\n";
echo "SIL: rm apply-turkiye-bewerbung.php\n";
echo "Test: TR sec -> 'Is Basvurusu ve CV' -> 'CV — Almanca' uret -> belge TAMAMEN Almanca cikmali.\n";
