<?php
/**
 * ChatHelp — apply-visa-req (CH_VISA_REQ) — TR vize modulu: konsolosluk gereksinimleri
 *  Her destinasyon icin VIZE TURUNE GORE gerekli belgeler + TARIH/GECERLILIK
 *  kurallari (pasaport suresi, hesap dokumu donemi, foto yasi, sigorta teminati)
 *  konsoloslarin istedigi bicimde kontrol listesi olarak sunulur. Mevcut
 *  isChecklist gorunumu kullanilir; ChatHelp ile uretilebilen belgeler forma
 *  baglanir (doc:). ABD/Kanada/Ingiltere/Avustralya kategorilerine gereksinim
 *  listesi eklenir; Schengen'e vize-turune-gore ek belgeler rehberi eklenir.
 * KULLANIM: pull2.php?key=...&files=apply-visa-req.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-visa-req BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_VISA_REQ')!==false) exit("Zaten ekli (CH_VISA_REQ).\n");
if (strpos($src,'CH_TR_VISA_MODULE')===false) exit("HATA: TR vize modulu yok.\n");

$fail=[];
function ins(&$src,$anchor,$insertAfter,$label,&$fail,$id){
    $c=substr_count($src,$anchor);
    if($c!==1){ echo "  ✗ [$id] anchor ($c): $label\n"; $fail[]=$id; return; }
    $src=str_replace($anchor,$anchor.$insertAfter,$src);
    echo "  ✓ [$id] $label\n";
}

/* ── ABD: B1/B2 + F1 gereksinimleri ── */
$usa = <<<'JS'

      {k:"tr_usa_checklist",ic:"✅",n:"ABD Vizesi — Gerekli Belgeler ve Kurallar (B1/B2 · F1)",tier:"einfach",isChecklist:true,/*CH_VISA_REQ*/
       checklist:[
        {grp:"B1/B2 (Turizm/İş) — Zorunlu Belgeler",items:[
          {t:"DS-160 onay sayfası (barkodlu)",src:"resmi",note:"ceac.state.gov — online doldurulur"},
          {t:"MRV vize harcı ödeme dekontu (185 USD)",src:"resmi",note:"Ödeme sonrası randevu alınabilir"},
          {t:"Randevu onay sayfası",src:"resmi",note:"usvisascheduling.com"},
          {t:"Pasaport — kalış süresinden SONRA en az 6 ay geçerli",src:"resmi",note:"6 ay kuralı; en az 1 boş sayfa"},
          {t:"Fotoğraf 5x5 cm, SON 6 AY içinde çekilmiş, beyaz fon, gözlüksüz",src:"resmi"}
        ]},
        {grp:"B1/B2 — Destekleyici (görüşmede istenebilir)",items:[
          {t:"Son 3 aylık banka hesap dökümü (bankadan kaşeli veya e-imzalı)",src:"resmi"},
          {t:"İşveren yazısı — SON 1 AY içinde alınmış, maaş ve izin belirtilmeli",src:"chathelp",doc:"tr_usa_isveren_izin"},
          {t:"Türkiye'ye bağlılık kanıtları (tapu, iş, aile)",src:"chathelp",doc:"tr_usa_baglilik"},
          {t:"Davet mektubu (aile/arkadaş ziyaretiyse)",src:"chathelp",doc:"tr_usa_davet_aile"},
          {t:"Seyahat planı",src:"chathelp",doc:"tr_usa_seyahat_plani"},
          {t:"SGK hizmet dökümü / vergi levhası (gelir kanıtı)",src:"resmi",note:"e-Devlet"}
        ]},
        {grp:"F1 (Öğrenci) — Ek Zorunlu Belgeler",items:[
          {t:"I-20 formu (okuldan, imzalı)",src:"resmi",note:"Kabul alınan okul gönderir"},
          {t:"SEVIS I-901 ücreti dekontu (350 USD)",src:"resmi",note:"fmjfee.com — görüşmeden en az 3 gün önce ödenmeli"},
          {t:"Kabul mektubu ve mali yeterlilik kanıtı (1. yıl masraflarını karşılayacak bakiye)",src:"resmi"},
          {t:"Eğitim amacı beyanı",src:"chathelp",doc:"tr_usa_amac_beyani"}
        ]},
        {grp:"Tarih Kuralları — Özet",items:[
          {t:"Pasaport: kalış + 6 ay geçerlilik",src:"resmi"},
          {t:"Fotoğraf: en fazla 6 aylık",src:"resmi"},
          {t:"Banka dökümü: son 3 ay, güncel bakiyeli",src:"resmi"},
          {t:"İşveren yazısı: son 30 gün içinde tarihli",src:"resmi"},
          {t:"Randevuya DS-160'taki pasaportla gidilmeli (yenilendiyse DS-160 güncellenir)",src:"resmi"}
        ]}
       ]},
JS;

/* ── Ingiltere: Standard Visitor + Student ── */
$uk = <<<'JS'

      {k:"tr_uk_checklist",ic:"✅",n:"İngiltere Vizesi — Gerekli Belgeler ve Kurallar (Visitor · Student)",tier:"einfach",isChecklist:true,/*CH_VISA_REQ*/
       checklist:[
        {grp:"Standard Visitor — Zorunlu Belgeler",items:[
          {t:"Online başvuru formu (gov.uk) + ödeme onayı (~115 GBP / 6 ay)",src:"resmi",note:"gov.uk/standard-visitor-visa"},
          {t:"Biyometri randevusu onayı (VFS Global)",src:"resmi"},
          {t:"Pasaport — planlanan kalışın TAMAMI boyunca geçerli + 1 boş sayfa",src:"resmi"},
          {t:"Son 6 AYLIK banka hesap dökümü (İngiltere 6 ay ister!)",src:"resmi",note:"Bankadan kaşeli veya e-imzalı"},
          {t:"Gelir kanıtı: maaş bordroları (son 3-6 ay) veya vergi kaydı",src:"resmi"}
        ]},
        {grp:"Visitor — Destekleyici Belgeler",items:[
          {t:"İşveren yazısı — son 1 ay içinde, izin tarihleri ve dönüş güvencesiyle",src:"chathelp",doc:"tr_uk_isveren_izin"},
          {t:"Seyahat amacı beyanı (cover letter)",src:"chathelp",doc:"tr_uk_amac_beyani"},
          {t:"Davet/sponsor mektubu (varsa) + davet edenin İngiltere'deki statü belgesi",src:"chathelp",doc:"tr_uk_davet"},
          {t:"Konaklama ve uçuş rezervasyonları (kesin bilet şart değil)",src:"resmi"},
          {t:"Türkiye'ye bağlılık kanıtları (iş, tapu, aile)",src:"resmi"}
        ]},
        {grp:"Student — Ek Zorunlu Belgeler",items:[
          {t:"CAS (Confirmation of Acceptance for Studies) — başvurudan en fazla 6 ay önce alınmış",src:"resmi",note:"Okul verir"},
          {t:"Mali yeterlilik: gerekli tutar hesapta KESİNTİSİZ 28 GÜN beklemiş olmalı; döküm başvuru tarihinden en fazla 31 gün önce alınmış",src:"resmi",note:"28 gün kuralı kritik"},
          {t:"İngilizce yeterlilik (SELT/IELTS UKVI vb.)",src:"resmi"},
          {t:"18 yaş altı için ebeveyn muvafakati",src:"chathelp",doc:"tr_uk_cocuk_izin"}
        ]},
        {grp:"Tarih Kuralları — Özet",items:[
          {t:"Banka dökümü: Visitor 6 ay, Student 28-gün bakiye + 31-gün tazelik",src:"resmi"},
          {t:"CAS: 6 aydan eski olamaz",src:"resmi"},
          {t:"İşveren yazısı: son 30 gün",src:"resmi"},
          {t:"Başvuru seyahatten en erken 3 ay önce yapılabilir",src:"resmi"}
        ]}
       ]},
JS;

/* ── Kanada: Visitor + Study ── */
$ca = <<<'JS'

      {k:"tr_canada_checklist",ic:"✅",n:"Kanada Vizesi — Gerekli Belgeler ve Kurallar (Visitor · Study)",tier:"einfach",isChecklist:true,/*CH_VISA_REQ*/
       checklist:[
        {grp:"Visitor Visa (TRV) — Zorunlu Belgeler",items:[
          {t:"IMM 5257 başvuru formu (IRCC hesabıyla online)",src:"resmi",note:"canada.ca — IRCC secure account"},
          {t:"Başvuru ücreti 100 CAD + biyometri 85 CAD",src:"resmi"},
          {t:"Biyometri randevusu (VFS Global) — davet mektubu (BIL) geldikten sonra 30 GÜN içinde",src:"resmi"},
          {t:"Pasaport — planlanan kalış boyunca geçerli",src:"resmi"},
          {t:"Fotoğraf 35x45 mm, son 6 ay",src:"resmi"}
        ]},
        {grp:"Visitor — Destekleyici Belgeler",items:[
          {t:"Son 4 AYLIK banka hesap dökümü (Kanada 4 ay ister)",src:"resmi"},
          {t:"Seyahat amacı beyanı",src:"chathelp",doc:"tr_canada_amac_beyani"},
          {t:"Davet mektubu + davet edenin statü/gelir belgeleri",src:"chathelp",doc:"tr_canada_davet"},
          {t:"İşveren izin yazısı (son 1 ay)",src:"chathelp",doc:"tr_canada_isveren_izin"},
          {t:"Türkiye'ye bağlılık kanıtları",src:"resmi"}
        ]},
        {grp:"Study Permit — Ek Zorunlu Belgeler",items:[
          {t:"Kabul mektubu (LOA) — DLI listesindeki okuldan",src:"resmi"},
          {t:"PAL/TAL (il onay mektubu) — çoğu program için zorunlu",src:"resmi"},
          {t:"Mali yeterlilik: 1 yıl okul ücreti + 20.635 CAD geçim (2024 kuralı)",src:"resmi"},
          {t:"Niyet mektubu (Study Plan)",src:"chathelp",doc:"tr_canada_amac_beyani"}
        ]},
        {grp:"Tarih Kuralları — Özet",items:[
          {t:"Biyometri: BIL mektubundan sonra 30 gün içinde",src:"resmi"},
          {t:"Banka dökümü: son 4 ay",src:"resmi"},
          {t:"Fotoğraf: son 6 ay",src:"resmi"},
          {t:"Pasaport: kalış süresi boyunca geçerli (uzatma planlıyorsanız daha uzun)",src:"resmi"}
        ]}
       ]},
JS;

/* ── Avustralya: Visitor 600 + Student 500 ── */
$au = <<<'JS'

      {k:"tr_australia_checklist",ic:"✅",n:"Avustralya Vizesi — Gerekli Belgeler ve Kurallar (600 · 500)",tier:"einfach",isChecklist:true,/*CH_VISA_REQ*/
       checklist:[
        {grp:"Visitor (Subclass 600) — Zorunlu Belgeler",items:[
          {t:"ImmiAccount üzerinden online başvuru + ücret (~195 AUD)",src:"resmi",note:"immi.homeaffairs.gov.au"},
          {t:"Pasaport — planlanan kalış + 6 ay geçerli",src:"resmi"},
          {t:"Son 3 aylık banka hesap dökümü",src:"resmi"},
          {t:"Gelir kanıtı (bordro, SGK dökümü, vergi levhası)",src:"resmi",note:"e-Devlet"}
        ]},
        {grp:"Visitor — Destekleyici Belgeler",items:[
          {t:"Seyahat amacı beyanı",src:"chathelp",doc:"tr_australia_amac"},
          {t:"Davet mektubu (varsa) + davet edenin statüsü",src:"chathelp",doc:"tr_australia_davet"},
          {t:"İşveren izin yazısı (son 1 ay)",src:"chathelp",doc:"tr_australia_isveren"},
          {t:"Seyahat sağlık sigortası (tavsiye; 70+ yaş için zorunlu olabilir)",src:"resmi"}
        ]},
        {grp:"Student (Subclass 500) — Ek Zorunlu Belgeler",items:[
          {t:"CoE (Confirmation of Enrolment)",src:"resmi",note:"Okul verir"},
          {t:"GS (Genuine Student) beyanı",src:"chathelp",doc:"tr_australia_amac"},
          {t:"OSHC sağlık sigortası — tüm eğitim süresini kapsamalı",src:"resmi"},
          {t:"Mali yeterlilik: yıllık ~29.710 AUD geçim + okul ücreti",src:"resmi"},
          {t:"İngilizce yeterlilik (IELTS vb.)",src:"resmi"}
        ]},
        {grp:"Tarih Kuralları — Özet",items:[
          {t:"Pasaport: kalış + 6 ay",src:"resmi"},
          {t:"Banka dökümü: son 3 ay",src:"resmi"},
          {t:"OSHC: eğitim süresinin tamamı",src:"resmi"},
          {t:"Karar süreci online takip edilir; biyometri gerekirse VFS randevusu 14 gün içinde",src:"resmi"}
        ]}
       ]},
JS;

/* ── Schengen: vize turune gore ek belgeler rehberi ── */
$schx = <<<'JS'
{k:"tr_schengen_tur_rehberi",ic:"🧭",n:"Vize Türüne Göre Ek Belgeler (Turizm · Ziyaret · İş · Öğrenci)",tier:"einfach",isChecklist:true,/*CH_VISA_REQ*/
       checklist:[
        {grp:"Turizm (C tipi)",items:[
          {t:"Otel + uçak rezervasyonları (tüm kalışı kapsamalı)",src:"resmi"},
          {t:"Seyahat planı (itinerary)",src:"chathelp",doc:"tr_schengen_seyahat_plani"},
          {t:"Günlük ~50-120 € yeterli bakiye (ülkeye göre değişir)",src:"resmi"}
        ]},
        {grp:"Aile/Arkadaş Ziyareti",items:[
          {t:"Davet mektubu (resmi taahhütname: DE 'Verpflichtungserklärung', FR 'attestation d'accueil')",src:"resmi",note:"Davet eden kendi belediyesinden alır"},
          {t:"Gayri resmi davet yazısı (destekleyici)",src:"chathelp",doc:"tr_schengen_davet_aile"},
          {t:"Davet edenin kimlik/oturum belgesi fotokopisi",src:"resmi"}
        ]},
        {grp:"İş (C tipi)",items:[
          {t:"Karşı firmadan davet yazısı (tarih ve amaç belirtilmiş)",src:"chathelp",doc:"tr_schengen_davet_is"},
          {t:"İşveren görevlendirme yazısı (son 1 ay)",src:"chathelp",doc:"tr_schengen_izin_yazisi"},
          {t:"Firma faaliyet belgesi + imza sirküleri",src:"resmi",note:"Ticaret odası"}
        ]},
        {grp:"Öğrenci/Kısa Kurs",items:[
          {t:"Kurs/okul kabul yazısı ve ödeme dekontu",src:"resmi"},
          {t:"Öğrenci belgesi (e-Devlet) + okul izin yazısı",src:"chathelp",doc:"tr_schengen_ogrenci_izin"},
          {t:"18 yaş altı: noter onaylı ebeveyn muvafakatnamesi",src:"chathelp",doc:"tr_schengen_cocuk_izin"}
        ]},
        {grp:"Tarih Kuralları — Özet (tüm türler)",items:[
          {t:"Pasaport: dönüşten sonra 3 ay geçerli, son 10 yılda basılmış, 2 boş sayfa",src:"resmi"},
          {t:"Fotoğraf: son 6 ay, biyometrik",src:"resmi"},
          {t:"Banka dökümü: son 3 ay",src:"resmi"},
          {t:"Sigorta: min. 30.000 € teminat, tüm Schengen + tüm kalış",src:"resmi"},
          {t:"Başvuru: seyahatten en erken 6 ay, en geç 15 gün önce",src:"resmi"}
        ]}
       ]},

JS;

ins($src,'usa:{ic:"🇺🇸",n:"ABD Vizesi",docs:[',$usa,'ABD gereksinim listesi (B1/B2 + F1)',$fail,'1');
ins($src,'uk:{ic:"🇬🇧",n:"İngiltere Vizesi",docs:[',$uk,'İngiltere gereksinim listesi (Visitor + Student)',$fail,'2');
ins($src,'canada:{ic:"🇨🇦",n:"Kanada Vizesi",docs:[',$ca,'Kanada gereksinim listesi (Visitor + Study)',$fail,'3');
ins($src,'australia:{ic:"🇦🇺",n:"Avustralya Vizesi",docs:[',$au,'Avustralya gereksinim listesi (600 + 500)',$fail,'4');

$a5='{k:"tr_schengen_amac_beyani",ic:"✍️"';
$c=substr_count($src,$a5);
if($c!==1){ echo "  ✗ [5] schengen anchor ($c)\n"; $fail[]=5; }
else { $src=str_replace($a5,$schx.$a5,$src); echo "  ✓ [5] Schengen vize-turune-gore rehber eklendi\n"; }

if ($fail) { echo "\nHATA: eslesmeyen adimlar: ".implode(', ',$fail)." — index DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'vr').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
$bk=@file_put_contents($file.'.bak-visareq-'.date('Ymd-His'), @file_get_contents($file));
if ($bk===false) echo "  ⚠ yedek yazilamadi — devam\n";
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI: ".var_export($w,true)."\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_VISA_REQ')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_VISA_REQ diskte (".strlen($chk)." bayt)\n";
echo "\n✓ CH_VISA_REQ uygulandi: 5 destinasyonda vize-turune-gore konsolosluk\n";
echo "   gereksinimleri + tarih kurallari kullaniciya sunuluyor.\n";
