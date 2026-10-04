<?php
/**
 * ChatHelp — apply-tr-visa-module (CH_TR_VISA_MODULE)
 *  Turkiye vize destek belgelerini (Schengen/ABD/Kanada/Ingiltere/Avustralya,
 *  38 belge) soru-cevap sohbet akisindan cikarip, CV Studio/Vertrag Studio
 *  tarzinda gercek bir MODUL haline getirir:
 *   kategori karti -> belge listesi -> tek sayfalik form -> "Olustur"
 *  "Olustur"a basinca form verisi toplanir, chat paneline donulur ve
 *  MEVCUT, zaten calisan window.genDoc(dk, ans) fonksiyonu cagrilir —
 *  boylece mevcut hukuk-prompt/plan-kapisi/uretim altyapisi aynen
 *  kullanilir, hicbir sey yeniden yazilmaz.
 *  Turkiye'ye ozgu: CC (secili hukuk ulkesi) "TR" degilse modul sol
 *  menuden ve ana sayfadan tamamen gizlenir (diger ulkelerde anlamsiz).
 *  Checklist belgesi (tr_schengen_checklist) form yerine gruplu kontrol
 *  listesi olarak gosterilir (orijinal renderChecklistView mantigi).
 * KULLANIM: pull-updates.php?files=apply-tr-visa-module.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-tr-visa-module BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_TR_VISA_MODULE') !== false) exit("Zaten ekli (CH_TR_VISA_MODULE).\n");
file_put_contents($file . '.bak-trvisamod-' . date('Ymd-His'), $src);

$bundle = <<<'TRVHTML'
<script id="ch-tr-visa-module">
/* CH_TR_VISA_MODULE — Turkiye vize destek belgeleri: modul (form) arayuzu */
try{(function(){
  function UIL(){ try{ return (window.chUIL?window.chUIL():(localStorage.getItem("ch_uilang")||"de")); }catch(e){ return "de"; } }
  function esc(s){ return String(s==null?"":s).replace(/[&<>"]/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];}); }
  function q(k,l,r,o){ var x={k:k,l:l,r:!!r}; if(o) x.opts=o; return x; }

  var T={
    de:{ttl:"Türkei-Visum",sub:"Unterstützende Dokumente für Ihren Visumantrag — pro Dokument ein kurzes Formular statt Frage für Frage.",cats:"Kategorien",docs:"Dokumente",back:"← Zurück",make:"📄 Dokument erstellen",note:"Unterstützt Ihren Antrag — ersetzt nicht das offizielle Antragsformular der Botschaft/des Konsulats.",req:"Pflichtfeld"},
    tr:{ttl:"Türkiye Vize Modülü",sub:"Vize başvurunuz için destekleyici belgeler — soru soru sohbet yerine, belge başına tek bir form.",cats:"Kategoriler",docs:"Belgeler",back:"← Geri",make:"📄 Belgeyi oluştur",note:"Başvurunuzu destekler — büyükelçilik/konsoloslığın resmi başvuru formunun yerini tutmaz.",req:"Zorunlu alan"},
    en:{ttl:"Turkey Visa",sub:"Supporting documents for your visa application — one short form per document instead of chat Q&A.",cats:"Categories",docs:"Documents",back:"← Back",make:"📄 Create document",note:"Supports your application — does not replace the embassy/consulate's official application form.",req:"Required"}
  };
  function L(){ return T[UIL()]||T.en; }

  var CATS_TR={
    schengen:{ic:"🛂",n:"Schengen Vizesi",docs:[
      {k:"tr_schengen_checklist",ic:"✅",n:"Gerekli Belgeler Kontrol Listesi",tier:"einfach",isChecklist:true,
       checklist:[
        {grp:"Kimlik & Seyahat Belgeleri",items:[
          {t:"Geçerli pasaport (en az 3 ay geçerlilik, 2 boş sayfa)",src:"resmi",note:"Nüfus Müdürlüğü / pasaport şubesi"},
          {t:"Biyometrik fotoğraf (son 6 ay, Schengen standardında)",src:"resmi",note:"Fotoğrafçı"},
          {t:"Nüfus cüzdanı fotokopisi",src:"resmi"},
          {t:"Önceki vize/pasaport sayfaları (varsa)",src:"resmi"}
        ]},
        {grp:"Resmi Başvuru Belgeleri",items:[
          {t:"Vize başvuru formu (konsolosluğun kendi formu)",src:"resmi",note:"Konsolosluk/VFS/iDATA web sitesinden"},
          {t:"Biyometrik randevu onayı",src:"resmi",note:"Randevu sistemi üzerinden alınır"},
          {t:"Seyahat sağlık sigortası poliçesi (min. 30.000 € teminat)",src:"resmi",note:"Sigorta şirketinden satın alınır"}
        ]},
        {grp:"Bu Modülle Oluşturabileceğiniz Belgeler",items:[
          {t:"Seyahat Amacı Beyanı (Cover Letter)",src:"chathelp",doc:"tr_schengen_amac_beyani"},
          {t:"Davet Mektubu (varsa aile/arkadaş)",src:"chathelp",doc:"tr_schengen_davet_aile"},
          {t:"İş Daveti Mektubu (iş amaçlıysa)",src:"chathelp",doc:"tr_schengen_davet_is"},
          {t:"İşveren İzin/Görevlendirme Yazısı",src:"chathelp",doc:"tr_schengen_izin_yazisi"},
          {t:"Mali Durum / Sponsorluk Beyanı",src:"chathelp",doc:"tr_schengen_mali_beyan"},
          {t:"Konaklama Bilgi Formu",src:"chathelp",doc:"tr_schengen_konaklama_beyan"},
          {t:"Seyahat Planı (İtinerary)",src:"chathelp",doc:"tr_schengen_seyahat_plani"},
          {t:"Öğrenci İzin Belgesi Talebi (öğrenciyseniz)",src:"chathelp",doc:"tr_schengen_ogrenci_izin"},
          {t:"Emekli/Serbest Meslek Gelir Beyanı",src:"chathelp",doc:"tr_schengen_emekli_gelir"},
          {t:"Reşit Olmayan İçin Ebeveyn İzin Belgesi (çocuk varsa)",src:"chathelp",doc:"tr_schengen_cocuk_izin"}
        ]},
        {grp:"Mali/Kanıt Belgeleri (Resmi Kaynak)",items:[
          {t:"Son 3 aylık banka hesap dökümü",src:"resmi",note:"Bankanızdan / e-Devlet / internet bankacılığı"},
          {t:"Uçak bileti rezervasyon teyidi",src:"resmi",note:"Seyahat acentesi/havayolu"},
          {t:"Otel rezervasyon teyidi",src:"resmi",note:"Booking.com vb."},
          {t:"SGK/vergi kaydı veya işveren belgesi (gelir kanıtı)",src:"resmi",note:"SGK / işvereninizden"}
        ]}
       ]},
      {k:"tr_schengen_amac_beyani",ic:"✍️",n:"Seyahat Amacı Beyanı (Cover Letter)",tier:"standard",qs:[
        q("amac","Seyahat amacı",true,["Turizm","Aile/Arkadaş ziyareti","İş","Konferans/Etkinlik","Eğitim"]),
        q("tarihler","Seyahat tarihleri (gidiş-dönüş)",true),
        q("guzergah","Ziyaret edilecek şehirler",false),
        q("ek_bilgi","Ek açıklama (opsiyonel)",false)]},
      {k:"tr_schengen_davet_aile",ic:"👨‍👩‍👧",n:"Davet Mektubu (Aile/Arkadaş)",tier:"standard",qs:[
        q("davet_eden","Davet eden kişi: ad, adres, Schengen ülkesindeki statüsü",true),
        q("davetli","Davet edilen kişinin ad ve pasaport bilgileri",true),
        q("iliski","Davet edenle ilişkiniz",true),
        q("tarihler","Ziyaret tarihleri",true),
        q("konaklama","Konaklama yeri (davet edenin evi mi?)",false),
        q("masraf","Masrafları kim karşılayacak?",false,["Davet eden","Ziyaretçi","Paylaşımlı"])]},
      {k:"tr_schengen_davet_is",ic:"💼",n:"İş Daveti Mektubu",tier:"standard",qs:[
        q("davet_eden_firma","Davet eden firma: ad ve adres (Schengen ülkesi)",true),
        q("davetli","Davet edilen kişi ve pozisyonu",true),
        q("amac","Ziyaretin amacı (toplantı, fuar, eğitim vb.)",true),
        q("tarihler","Ziyaret tarihleri",true),
        q("masraf","Masrafları kim karşılıyor?",false,["Davet eden firma","Kendi firması"])]},
      {k:"tr_schengen_izin_yazisi",ic:"📄",n:"İşveren İzin/Görevlendirme Yazısı",tier:"standard",qs:[
        q("isveren","İşveren firma: ad ve adres",true),
        q("calisan","Çalışanın ad ve pozisyonu",true),
        q("tarihler","İzin/görevlendirme tarihleri",true),
        q("tur","Tür",true,["Yıllık izin onayı","İş seyahati görevlendirmesi"]),
        q("maas_bilgisi","Maaş bilgisi eklensin mi?",false,["Evet","Hayır"])]},
      {k:"tr_schengen_mali_beyan",ic:"💶",n:"Mali Durum / Sponsorluk Beyanı",tier:"standard",qs:[
        q("basvuran","Başvuran: ad ve TC kimlik no",true),
        q("gelir_kaynagi","Gelir kaynağı",true,["Maaş","Kendi işi","Emekli maaşı","Sponsor (üçüncü kişi)"]),
        q("aylik_gelir","Aylık gelir (TL, opsiyonel)",false),
        q("sponsor","Sponsor varsa: ad ve ilişkisi",false),
        q("taahhut","Masrafları üstlenme taahhüdü metni istenir mi?",false,["Evet","Hayır"])]},
      {k:"tr_schengen_konaklama_beyan",ic:"🏨",n:"Konaklama Bilgi Formu",tier:"einfach",qs:[
        q("tur","Konaklama türü",true,["Otel rezervasyonu","Ev sahibinde kalış","Kiralık konaklama"]),
        q("adres","Konaklama adresi",true),
        q("tarihler","Giriş-çıkış tarihleri",true),
        q("ev_sahibi","Ev sahibinin ad/iletişimi (varsa)",false)]},
      {k:"tr_schengen_seyahat_plani",ic:"🗺️",n:"Seyahat Planı (İtinerary)",tier:"einfach",qs:[
        q("ulkeler","Ziyaret edilecek ülke(ler) ve şehirler",true),
        q("tarihler","Gidiş-dönüş tarihleri",true),
        q("gunluk_plan","Günlük plan (kısa özet, opsiyonel)",false),
        q("ulasim","Ulaşım şekli",false,["Uçak","Otobüs","Araç","Karma"])]},
      {k:"tr_schengen_ogrenci_izin",ic:"🎓",n:"Öğrenci İzin Belgesi Talebi",tier:"einfach",qs:[
        q("okul","Okul/üniversite adı",true),
        q("ogrenci","Öğrencinin ad ve numarası",true),
        q("tarihler","İzin tarihleri",true),
        q("amac","Seyahat amacı",false)]},
      {k:"tr_schengen_emekli_gelir",ic:"👴",n:"Emekli/Serbest Meslek Gelir Beyanı",tier:"einfach",qs:[
        q("durum","Durum",true,["Emekli","Serbest meslek/Freelancer"]),
        q("gelir_kaynagi","Gelir kaynağının açıklaması",true),
        q("aylik_gelir","Aylık ortalama gelir (TL, opsiyonel)",false)]},
      {k:"tr_schengen_cocuk_izin",ic:"👶",n:"Reşit Olmayan İçin Ebeveyn İzin Belgesi",tier:"standard",qs:[
        q("cocuk","Çocuğun ad ve doğum tarihi",true),
        q("seyahat_eden_ebeveyn","Çocukla seyahat edecek kişi",true),
        q("diger_ebeveyn","Diğer ebeveynin ad ve onayı",true),
        q("tarihler","Seyahat tarihleri",true)]}
    ]},
    usa:{ic:"🇺🇸",n:"ABD Vizesi",docs:[
      {k:"tr_usa_amac_beyani",ic:"✍️",n:"Seyahat Amacı Beyanı (DS-160 Desteği)",tier:"standard",qs:[
        q("amac","Seyahat amacı",true,["Turizm","Aile/Arkadaş ziyareti","İş","Konferans","Eğitim"]),
        q("tarihler","Seyahat tarihleri",true),
        q("guzergah","Ziyaret edilecek şehirler",false)]},
      {k:"tr_usa_davet_aile",ic:"👨‍👩‍👧",n:"Davet Mektubu (Aile/Arkadaş)",tier:"standard",qs:[
        q("davet_eden","Davet eden: ad, adres, statüsü (vatandaş/oturum)",true),
        q("davetli","Davet edilen kişinin bilgileri",true),
        q("iliski","İlişkiniz",true),
        q("tarihler","Ziyaret tarihleri",true)]},
      {k:"tr_usa_is_davet",ic:"💼",n:"İş Daveti / Konferans Daveti",tier:"standard",qs:[
        q("davet_eden_firma","Davet eden kurum: ad ve adres",true),
        q("amac","Etkinlik/toplantı amacı",true),
        q("tarihler","Tarihler",true)]},
      {k:"tr_usa_isveren_izin",ic:"📄",n:"İşveren İzin Yazısı",tier:"standard",qs:[
        q("isveren","İşveren: ad ve adres",true),
        q("pozisyon","Pozisyonunuz",true),
        q("tarihler","İzin tarihleri",true),
        q("maas","Maaş bilgisi eklensin mi?",false,["Evet","Hayır"])]},
      {k:"tr_usa_mali_beyan",ic:"💶",n:"Mali Durum Beyanı",tier:"standard",qs:[
        q("gelir_kaynagi","Gelir kaynağı",true,["Maaş","Kendi işi","Emekli maaşı"]),
        q("aylik_gelir","Aylık gelir (TL, opsiyonel)",false)]},
      {k:"tr_usa_baglilik",ic:"🏠",n:"Türkiye'ye Bağlılık Beyanı",tier:"komplex",qs:[
        q("is_durumu","İş durumu (işveren, pozisyon, süre)",true),
        q("mulk","Mülk/varlık (varsa)",false),
        q("aile_baglari","Türkiye'deki aile bağları",true),
        q("donus_niyeti","Dönüş planınız/niyetiniz",true)]},
      {k:"tr_usa_seyahat_plani",ic:"🗺️",n:"Seyahat Planı",tier:"einfach",qs:[
        q("tarihler","Gidiş-dönüş tarihleri",true),
        q("sehirler","Ziyaret edilecek şehirler",true),
        q("konaklama","Konaklama bilgisi",false)]}
    ]},
    canada:{ic:"🇨🇦",n:"Kanada Vizesi",docs:[
      {k:"tr_canada_amac_beyani",ic:"✍️",n:"Seyahat Amacı Beyanı",tier:"standard",qs:[
        q("amac","Seyahat amacı",true,["Turizm","Aile/Arkadaş ziyareti","İş","Eğitim"]),
        q("tarihler","Seyahat tarihleri",true)]},
      {k:"tr_canada_davet",ic:"👨‍👩‍👧",n:"Davet Mektubu",tier:"standard",qs:[
        q("davet_eden","Davet eden: ad, adres, statüsü",true),
        q("davetli","Davet edilen kişi",true),
        q("iliski","İlişkiniz",true),
        q("tarihler","Ziyaret tarihleri",true)]},
      {k:"tr_canada_isveren_izin",ic:"📄",n:"İşveren İzin Yazısı",tier:"standard",qs:[
        q("isveren","İşveren: ad ve adres",true),
        q("pozisyon","Pozisyonunuz",true),
        q("tarihler","İzin tarihleri",true)]},
      {k:"tr_canada_mali_beyan",ic:"💶",n:"Mali Durum Beyanı",tier:"standard",qs:[
        q("gelir_kaynagi","Gelir kaynağı",true),
        q("aylik_gelir","Aylık gelir (TL, opsiyonel)",false)]},
      {k:"tr_canada_baglilik",ic:"🏠",n:"Türkiye'ye Bağlılık Beyanı",tier:"komplex",qs:[
        q("is_durumu","İş durumu",true),
        q("aile_baglari","Aile bağları",true),
        q("donus_niyeti","Dönüş niyetiniz",true)]},
      {k:"tr_canada_konaklama",ic:"🏨",n:"Konaklama Bilgi Formu",tier:"einfach",qs:[
        q("tur","Konaklama türü",true,["Otel","Ev sahibinde kalış"]),
        q("adres","Adres",true),
        q("tarihler","Tarihler",true)]},
      {k:"tr_canada_ogrenim_amac",ic:"🎓",n:"Öğrenim Amacı Beyanı (Study Permit)",tier:"komplex",qs:[
        q("okul","Kabul aldığınız okul",true),
        q("program","Program/bölüm",true),
        q("sure","Eğitim süresi",false),
        q("donus_plani","Mezuniyet sonrası planınız",false)]}
    ]},
    uk:{ic:"🇬🇧",n:"İngiltere Vizesi",docs:[
      {k:"tr_uk_amac_beyani",ic:"✍️",n:"Seyahat Amacı Beyanı",tier:"standard",qs:[
        q("amac","Seyahat amacı",true,["Turizm","Aile/Arkadaş ziyareti","İş","Eğitim"]),
        q("tarihler","Seyahat tarihleri",true)]},
      {k:"tr_uk_davet",ic:"👨‍👩‍👧",n:"Davet Mektubu (Aile/Arkadaş)",tier:"standard",qs:[
        q("davet_eden","Davet eden: ad, adres, statüsü",true),
        q("davetli","Davet edilen kişi",true),
        q("iliski","İlişkiniz",true),
        q("tarihler","Ziyaret tarihleri",true)]},
      {k:"tr_uk_isveren_izin",ic:"📄",n:"İşveren İzin Yazısı",tier:"standard",qs:[
        q("isveren","İşveren: ad ve adres",true),
        q("pozisyon","Pozisyonunuz",true),
        q("tarihler","İzin tarihleri",true)]},
      {k:"tr_uk_mali_beyan",ic:"💶",n:"Mali Durum Beyanı",tier:"standard",qs:[
        q("gelir_kaynagi","Gelir kaynağı",true),
        q("aylik_gelir","Aylık gelir (TL, opsiyonel)",false)]},
      {k:"tr_uk_konaklama",ic:"🏨",n:"Konaklama Bilgi Formu",tier:"einfach",qs:[
        q("tur","Konaklama türü",true,["Otel","Ev sahibinde kalış"]),
        q("adres","Adres",true),
        q("tarihler","Tarihler",true)]},
      {k:"tr_uk_seyahat_plani",ic:"🗺️",n:"Seyahat Planı",tier:"einfach",qs:[
        q("tarihler","Gidiş-dönüş tarihleri",true),
        q("sehirler","Ziyaret edilecek şehirler",true)]},
      {k:"tr_uk_baglilik",ic:"🏠",n:"Türkiye'ye Bağlılık Beyanı",tier:"komplex",qs:[
        q("is_durumu","İş durumu",true),
        q("aile_baglari","Aile bağları",true),
        q("donus_niyeti","Dönüş niyetiniz",true)]}
    ]},
    australia:{ic:"🇦🇺",n:"Avustralya Vizesi",docs:[
      {k:"tr_au_amac_beyani",ic:"✍️",n:"Seyahat Amacı Beyanı",tier:"standard",qs:[
        q("amac","Seyahat amacı",true,["Turizm","Aile/Arkadaş ziyareti","İş","Eğitim"]),
        q("tarihler","Seyahat tarihleri",true)]},
      {k:"tr_au_davet",ic:"👨‍👩‍👧",n:"Davet Mektubu",tier:"standard",qs:[
        q("davet_eden","Davet eden: ad, adres, statüsü",true),
        q("davetli","Davet edilen kişi",true),
        q("iliski","İlişkiniz",true),
        q("tarihler","Ziyaret tarihleri",true)]},
      {k:"tr_au_isveren_izin",ic:"📄",n:"İşveren İzin Yazısı",tier:"standard",qs:[
        q("isveren","İşveren: ad ve adres",true),
        q("pozisyon","Pozisyonunuz",true),
        q("tarihler","İzin tarihleri",true)]},
      {k:"tr_au_mali_beyan",ic:"💶",n:"Mali Durum Beyanı",tier:"standard",qs:[
        q("gelir_kaynagi","Gelir kaynağı",true),
        q("aylik_gelir","Aylık gelir (TL, opsiyonel)",false)]},
      {k:"tr_au_seyahat_plani",ic:"🗺️",n:"Seyahat Planı",tier:"einfach",qs:[
        q("tarihler","Gidiş-dönüş tarihleri",true),
        q("sehirler","Ziyaret edilecek şehirler",true)]},
      {k:"tr_au_baglilik",ic:"🏠",n:"Türkiye'ye Bağlılık Beyanı",tier:"komplex",qs:[
        q("is_durumu","İş durumu",true),
        q("aile_baglari","Aile bağları",true),
        q("donus_niyeti","Dönüş niyetiniz",true)]}
    ]}
  };
  var CAT_ORDER=["schengen","usa","canada","uk","australia"];

  function addCss(){
    if(document.getElementById("ch-trvisa-css")) return;
    var st=document.createElement("style"); st.id="ch-trvisa-css";
    st.textContent=[
      "#pnl-trvisa{position:fixed;inset:0;z-index:520;background:var(--bg,#17171d);display:none;flex-direction:column;overflow:hidden}",
      "#pnl-trvisa.on{display:flex}",
      ".trv-topbar{display:flex;align-items:center;gap:10px;padding:14px 16px;border-bottom:1px solid var(--bdr,rgba(255,255,255,.1));background:var(--s1,#1d1d24);flex-shrink:0}",
      ".trv-back{width:38px;height:38px;border-radius:12px;border:1px solid rgba(212,168,74,.45);background:rgba(212,168,74,.09);color:var(--gold,#d4a84a);font-size:22px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center}",
      ".trv-back:hover{background:linear-gradient(135deg,#d4a84a,#f0c860);color:#1a1400}",
      ".trv-ttl{font-size:17px;font-weight:800}",
      ".trv-scroll{flex:1;overflow-y:auto;padding:16px}",
      ".trv-wrap{max-width:900px;margin:0 auto}",
      ".trv-sub{font-size:13.5px;color:var(--t3,#a8a8d0);margin-bottom:16px;line-height:1.6}",
      ".trv-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px}",
      ".trv-card{border:1px solid rgba(212,168,74,.2);border-radius:16px;background:var(--s2,#25252e);cursor:pointer;padding:18px;box-shadow:0 6px 20px rgba(0,0,0,.2);transition:transform .16s,box-shadow .2s,border-color .2s}",
      ".trv-card:hover{transform:translateY(-2px);border-color:rgba(212,168,74,.7);box-shadow:0 16px 38px rgba(0,0,0,.36)}",
      ".trv-ic{width:48px;height:48px;border-radius:13px;background:linear-gradient(135deg,rgba(212,168,74,.22),rgba(212,168,74,.05));border:1px solid rgba(212,168,74,.35);display:flex;align-items:center;justify-content:center;font-size:24px;margin-bottom:10px}",
      ".trv-n{font-size:14.5px;font-weight:800;color:var(--t1,#fff)}",
      ".trv-cnt{font-size:11.5px;color:var(--t3,#a8a8d0);margin-top:3px}",
      ".trv-doclist{display:flex;flex-direction:column;gap:9px}",
      ".trv-docrow{display:flex;align-items:center;gap:12px;padding:13px 15px;border:1px solid rgba(212,168,74,.18);border-radius:13px;background:var(--s2,#25252e);cursor:pointer;transition:border-color .18s,transform .14s}",
      ".trv-docrow:hover{border-color:rgba(212,168,74,.6);transform:translateX(2px)}",
      ".trv-docrow .trv-ic{width:38px;height:38px;font-size:19px;margin:0;flex-shrink:0}",
      ".trv-docn{flex:1;font-size:13.5px;font-weight:700;color:var(--t1,#fff)}",
      ".trv-tier{font-size:10px;font-weight:800;letter-spacing:.4px;text-transform:uppercase;color:var(--gold,#d4a84a);border:1px solid rgba(212,168,74,.35);border-radius:100px;padding:3px 9px}",
      ".trv-fl{font-size:10.5px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;color:var(--gold,#d4a84a);opacity:.85;margin:0 0 4px 2px}",
      ".trv-in,.trv-sel{width:100%;padding:11px 12px;margin-bottom:12px;background:var(--s2,#25252e);border:1px solid var(--bdr,rgba(255,255,255,.12));border-radius:10px;color:var(--t1,#fff);font-size:13.5px;font-family:inherit}",
      ".trv-in:focus,.trv-sel:focus{outline:none;border-color:rgba(212,168,74,.6)}",
      ".trv-req{color:#e05050;margin-left:3px}",
      ".trv-mkbtn{width:100%;padding:13px;border-radius:12px;border:none;background:linear-gradient(135deg,#d4a84a,#f0c860);color:#1a1400;font-size:14.5px;font-weight:800;cursor:pointer;box-shadow:0 8px 22px rgba(212,168,74,.3);margin-top:6px}",
      ".trv-mkbtn:hover{transform:translateY(-1px)}",
      ".trv-note{font-size:11.5px;color:var(--t3,#a8a8d0);margin-top:12px;line-height:1.55}",
      ".trv-cl-grp{margin-bottom:18px}",
      ".trv-cl-h{font-size:12px;font-weight:800;letter-spacing:.4px;text-transform:uppercase;color:var(--gold,#d4a84a);margin-bottom:9px}",
      ".trv-cl-item{display:flex;align-items:center;gap:10px;padding:10px 13px;border:1px solid var(--bdr,rgba(255,255,255,.1));border-radius:11px;background:var(--s2,#25252e);margin-bottom:7px}",
      ".trv-cl-t{flex:1;font-size:12.5px;color:var(--t2,#e0e0ff)}",
      ".trv-cl-note{font-size:10.5px;color:var(--t3,#a8a8d0);display:block;margin-top:2px}",
      ".trv-cl-badge{font-size:9.5px;font-weight:800;letter-spacing:.3px;text-transform:uppercase;border-radius:100px;padding:3px 9px;flex-shrink:0}",
      ".trv-cl-badge.official{color:#e0a03c;border:1px solid rgba(224,160,60,.4)}",
      ".trv-cl-badge.chathelp{color:#42df94;border:1px solid rgba(66,223,148,.4);cursor:pointer}"
    ].join("\n");
    document.head.appendChild(st);
  }

  var CUR_CAT=null;
  function panel(){
    var p=document.getElementById("pnl-trvisa");
    if(!p){ p=document.createElement("div"); p.className="pnl"; p.id="pnl-trvisa"; document.body.appendChild(p); }
    return p;
  }
  function topbar(t){ var l=L(); return '<div class="trv-topbar"><button class="trv-back" id="trv-back">‹</button><div class="trv-ttl">'+esc(t)+'</div></div>'; }

  function renderCats(){
    CUR_CAT=null; var l=L(), p=panel();
    p.innerHTML=topbar(l.ttl)
     +'<div class="trv-scroll"><div class="trv-wrap">'
     +'<div class="trv-sub">'+esc(l.sub)+'</div>'
     +'<div class="trv-grid">'+CAT_ORDER.map(function(ck){ var c=CATS_TR[ck];
        return '<div class="trv-card" data-c="'+ck+'"><div class="trv-ic">'+c.ic+'</div><div class="trv-n">'+esc(c.n)+'</div>'
          +'<div class="trv-cnt">'+c.docs.length+' '+esc(l.docs)+'</div></div>';
      }).join("")+'</div></div></div>';
    document.getElementById("trv-back").addEventListener("click",function(){ try{ gP("chat"); }catch(e){} });
    p.querySelectorAll(".trv-card").forEach(function(c){
      c.addEventListener("click",function(){ renderDocs(c.getAttribute("data-c")); });
    });
  }

  function renderDocs(catKey){
    CUR_CAT=catKey; var l=L(), p=panel(), cat=CATS_TR[catKey];
    p.innerHTML=topbar(cat.n)
     +'<div class="trv-scroll"><div class="trv-wrap">'
     +'<div class="trv-doclist">'+cat.docs.map(function(d){
        return '<div class="trv-docrow" data-d="'+d.k+'"><div class="trv-ic">'+d.ic+'</div><div class="trv-docn">'+esc(d.n)+'</div>'
          +(d.isChecklist?'':'<div class="trv-tier">'+esc(d.tier)+'</div>')+'</div>';
      }).join("")+'</div>'
     +'</div></div>';
    document.getElementById("trv-back").addEventListener("click",renderCats);
    p.querySelectorAll(".trv-docrow").forEach(function(row){
      row.addEventListener("click",function(){
        var dk=row.getAttribute("data-d"), doc=cat.docs.filter(function(x){return x.k===dk;})[0];
        if(doc&&doc.isChecklist) renderChecklist(doc); else renderForm(doc);
      });
    });
  }

  function renderChecklist(doc){
    var l=L(), p=panel();
    p.innerHTML=topbar(doc.n)
     +'<div class="trv-scroll"><div class="trv-wrap">'
     +doc.checklist.map(function(g){
        return '<div class="trv-cl-grp"><div class="trv-cl-h">'+esc(g.grp)+'</div>'
         +g.items.map(function(it){
            var badge=it.src==="chathelp"
              ?'<span class="trv-cl-badge chathelp" data-go="'+esc(it.doc||"")+'">'+esc(l.ttl.indexOf("Türkiye")>-1?"Oluştur →":"Create →")+'</span>'
              :'<span class="trv-cl-badge official">'+(l===T.tr?"Resmî kaynak":"Official source")+'</span>';
            return '<div class="trv-cl-item"><div class="trv-cl-t">'+esc(it.t)+(it.note?'<span class="trv-cl-note">'+esc(it.note)+'</span>':'')+'</div>'+badge+'</div>';
         }).join("")+'</div>';
      }).join("")
     +'</div></div>';
    document.getElementById("trv-back").addEventListener("click",function(){ renderDocs(CUR_CAT); });
    p.querySelectorAll("[data-go]").forEach(function(b){
      var dk=b.getAttribute("data-go"); if(!dk) return;
      b.addEventListener("click",function(){
        var cat=CATS_TR[CUR_CAT], doc=cat.docs.filter(function(x){return x.k===dk;})[0];
        if(doc) renderForm(doc);
      });
    });
  }

  function renderForm(doc){
    var l=L(), p=panel();
    p.innerHTML=topbar(doc.n)
     +'<div class="trv-scroll"><div class="trv-wrap" style="max-width:560px">'
     +doc.qs.map(function(f){
        var field;
        if(f.opts){
          field='<select class="trv-sel" data-k="'+esc(f.k)+'"><option value="">—</option>'+f.opts.map(function(o){return '<option value="'+esc(o)+'">'+esc(o)+'</option>';}).join("")+'</select>';
        } else {
          field='<input class="trv-in" data-k="'+esc(f.k)+'" type="text">';
        }
        return '<div class="trv-fl">'+esc(f.l)+(f.r?'<span class="trv-req">*</span>':'')+'</div>'+field;
      }).join("")
     +'<button class="trv-mkbtn" id="trv-make">'+esc(l.make)+'</button>'
     +'<div class="trv-note">'+esc(l.note)+'</div>'
     +'</div></div>';
    document.getElementById("trv-back").addEventListener("click",function(){ renderDocs(CUR_CAT); });
    document.getElementById("trv-make").addEventListener("click",function(){
      var missing=null;
      var ans={};
      p.querySelectorAll("[data-k]").forEach(function(el){ ans[el.getAttribute("data-k")]=el.value||""; });
      for(var i=0;i<doc.qs.length;i++){
        var f=doc.qs[i];
        if(f.r && !String(ans[f.k]||"").trim()){ missing=f; break; }
      }
      if(missing){
        var el=p.querySelector('[data-k="'+missing.k+'"]');
        if(el){ el.style.borderColor="#e05050"; el.focus(); }
        return;
      }
      try{ gP("chat"); }catch(e){}
      setTimeout(function(){
        try{ if(typeof window.genDoc==="function") window.genDoc(doc.k, ans); }catch(e){}
      },200);
    });
  }

  /* ══ nav + Turkiye-only gorunurluk ══ */
  function ccIsTR(){ try{ return (typeof CC!=="undefined"&&CC)==="TR"; }catch(e){ return false; } }
  function addNav(){
    var ex=document.getElementById("nav-trvisa");
    if(!ccIsTR()){ if(ex) ex.style.display="none"; return; }
    if(ex){ ex.style.display=""; return; }
    var l=L();
    var ref=document.getElementById("nav-hub")||document.querySelector(".sb-photo-btn");
    if(!ref||!ref.parentNode) return;
    var nav=document.createElement("div"); nav.className="sn sb-photo-btn"; nav.id="nav-trvisa";
    nav.setAttribute("onclick","gP('trvisa')"); nav.style.cssText="cursor:pointer";
    nav.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="var(--gold,#d4a84a)" stroke-width="1.8" stroke-linecap="round" style="width:20px;height:20px;flex-shrink:0"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 9h20M7 15h4"/></svg>'
      +'<div><div class="sb-photo-t" id="nav-trvisa-t">'+esc(l.ttl)+'</div></div>';
    ref.parentNode.insertBefore(nav, ref.nextSibling);
  }
  function addHomeCard(){
    var ex=document.getElementById("ch-trvisa-card");
    if(!ccIsTR()){ if(ex) ex.style.display="none"; return; }
    if(ex){ ex.style.display=""; return; }
    var anchor=document.querySelector(".wlc-quick")||document.querySelector(".wlc-quick-label");
    if(!anchor) return;
    var l=L();
    var c=document.createElement("div"); c.id="ch-trvisa-card";
    c.style.cssText="display:flex;align-items:center;gap:12px;margin:12px 0;padding:13px 15px;border:1px solid rgba(212,168,74,.3);border-radius:14px;cursor:pointer;background:linear-gradient(135deg,rgba(212,168,74,.08),rgba(212,168,74,.02))";
    c.innerHTML='<div style="font-size:24px">🛂</div><div style="flex:1"><div style="font-size:13.5px;font-weight:800;color:#fff">'+esc(l.ttl)+'</div>'
      +'<div style="font-size:11.5px;color:var(--t3,#a8a8d0);margin-top:2px">'+esc(l.sub)+'</div></div><div style="font-size:16px;color:var(--gold,#d4a84a)">›</div>';
    c.addEventListener("click",function(){ try{ gP("trvisa"); }catch(e){} });
    anchor.parentNode.insertBefore(c, anchor);
  }

  function boot(){ addCss(); addNav(); addHomeCard(); if(!document.getElementById("pnl-trvisa")){ panel(); renderCats(); } }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",boot); else boot();
  setInterval(function(){
    try{
      addNav(); addHomeCard();
      var t=document.getElementById("nav-trvisa-t"); if(t&&t.textContent!==L().ttl) t.textContent=L().ttl;
    }catch(e){}
  },1000);
})();}catch(e){}
</script>
TRVHTML;

$p = strrpos($src, '</body>');
if ($p === false) exit("HATA: </body> bulunamadi — yazilmadi.\n");
$src = substr($src, 0, $p) . $bundle . "\n" . substr($src, $p);

$tmp = tempnam(sys_get_temp_dir(), 'idx') . '.php';
file_put_contents($tmp, $src);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "LINT HATASI — index.php degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
file_put_contents($file, $src);
echo "✓ CH_TR_VISA_MODULE uygulandi.\n";
echo "Icerik: 5 kategori (Schengen/ABD/Kanada/Ingiltere/Avustralya), 38 belge — kategori karti -> belge listesi -> tek sayfalik form -> Olustur.\n";
echo "Sadece CC==\"TR\" iken sol menu + ana sayfa kartinda gorunur. Olustur -> mevcut genDoc(dk,ans) fonksiyonunu cagirir (ayni hukuk-prompt/plan-kapisi/uretim), chat paneline donup sonucu gosterir.\n";
