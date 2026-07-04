# ChatHelp — AÇIK HATALAR ve YAPILACAKLAR (sonraki oturum için tam liste)

Tarih: 2026-07-04. Bu liste, canlı testlerde tespit edilen TÜM açık hataları kök
neden hipotezleri ve somut düzeltme talimatlarıyla içerir. Sıra = öncelik.

## Çalışma düzeni (ZORUNLU kurallar)
- Deploy kanalı: `chat/pull-updates.php?files=<dosya>&run=1` (GitHub branch:
  `claude/diagnostics-catalog-work-5gmxqw`'dan çeker). index.php/api.php repoda YOK —
  sadece sunucuda; her değişiklik `apply-*.php` yama scriptiyle yapılır (marker kontrolü
  + .bak yedek + php -l lint deseni mevcut dosyalardan kopyalanmalı).
- ⚠️ EDGE CACHE KURALI: Sitenin edge/CDN'i bir path'i cache'ledikten sonra query string'i
  bile YOK SAYIYOR (kanıt: dump-gen-debug.php güncellendi, ?_nc= eklendi, yine eski çıktı).
  Bir dump/apply dosyasının içeriği DEĞİŞTİYSE, YENİ DOSYA ADIYLA (…2.php, …3.php) koş.
  pull-updates'in kendi iç çağrılarına cache-buster eklendi (CH_PULLCACHE_FIX) ama path
  bazlı cache key sorunu için tek garanti = yeni dosya adı.
- Sunucudaki dosyaların güncel olduğu KANITLI: dump-verify-all.php → 23/23 marker OK
  (disk içeriği doğrudan okundu). Yani "sunucuya gitti mi?" = EVET. Ancak KULLANICIYA
  teslim (tarayıcıya inen HTML) edge cache yüzünden eski olabilir — kalıcı çözüm için
  GoDaddy destek talebi hâlâ bekliyor (aşağıda #7).

## #1 — KRİTİK: Belge üretimi bozuk — KÖK NEDEN KANITLANDI (dump-gen-debug3, 2026-07-04 09:01)
KESİN KANIT: Backend/AI hattı ÇALIŞIYOR (research=1999, draft=1172, final=1108 bayt;
CH_DOCQUAL doğru — bracket yerine temiz ____ boşluklar üretilmiş). SORUN FRONTEND:
api.php'ye ulaşan answers = SADECE {"datum":"4.7.2026"} — datum'u da genDoc kendisi
ekliyor (boş objeye). Yani genDoc BOŞ cevaplarla çağrılmış; formdaki hiçbir alan
(kundennummer, anbieter, ch_empfaenger, neden…) gönderilmemiş. AI jenerik mektup yazmış.
Ek kanıt: profil de neredeyse boş (VORNAME=Acerasoft NACHNAME=LLC, adres/tel yok) —
gönderen bloğu boş kalıyor; "isim Antragsgegner oldu" karışıklığı da boş answers +
boş profil ile AI'nın rol tahmininden geliyor (bkz #2).
Yığılan "Bitte warten" = genDoc'un DEFALARCA boş ans ile çağrılması.
ARAŞTIRILACAK: genDoc'u boş ans ile TEKRAR TEKRAR çağıran yol hangisi?
Adaylar (sırayla):
a) location.reload() sonrası restoreConv() eski form baloncuğunu geri çiziyor ama
   buton onclick closure'ları (box/ans referansı) KAYIP → butona basınca ans boş
   VEYA buton hiçbir şey yapmıyor, kullanıcı sohbet yolunu deniyor.
b) Sohbet-akışı yolu (composer'dan yazınca) genDoc(dk,{}) çağırıyor olabilir —
   state.ans toplama zinciri kopuk.
c) window._pendingDoc replay (paywall→login) eski/boş ans ile tekrar oynatıyor.
GÜVENLİ SAVUNMA DÜZELTMESİ (yol hangisi olursa olsun işe yarar, apply ile index.php'ye):
genDoc sarmalayıcısında (veya yeni bir wrap'te): gönderilecek ans'ta ≤1 anahtar varsa
DOM'daki SON formdan topla → document.querySelectorAll('#msgs [data-k]') üzerinden
{k:value} birleştir (dolu değerler öncelikli), ch_empfaenger dahil. Ek olarak: ans yine
boşsa üretime GİRME — kullanıcıya "form alanlarını doldurun" uyarısı göster (jenerik
belge üretmekten iyidir). Ve genDoc başında mevcut TÜM bekleme mesajlarını sil,
yenisini 1 kez ekle (kullanıcı talebi: tek mesaj + arayüz dilinde, ar/ru dahil).
Profil eksikliği için: resmi dilekçe üretiminden önce profil (ad+adres) boşsa kibar
uyarı/yönlendirme (Konto → Profil).
(Önceki hipotez bölümü aşağıda — artık ikincil:)
Belirti (ekran görüntüsü): "Bitte warten, Ihr Dokument wird erstellt …" mesajı ÜST ÜSTE
onlarca kez yığılıyor, kırmızı "Abbrechen" butonu görünüyor, belge gelmiyor.
Bağlantılı belirtiler (muhtemelen AYNI kök neden):
- Formda verilen cevaplar (kundennummer, kündigung nedeni, karşı taraf…) belgede YOK
- Yüklenen fotoğraflar belgede kullanılmıyor
- Anträge sayfası dolmuyor (belge kaydedilmiyor → history boş)
- Kullanıcının adı belgede ANTRAGSGEGNER (karşı taraf) olarak yazılmış — rol karışıyor
- jbdata/gen-debug-in.json HİÇ oluşmadı → doGenerate answersText satırına ULAŞMIYOR
  (yani istek ya api.php'ye hiç varmıyor ya da daha önce fatal error veriyor)
İLK ADIM (kod yazmadan): `pull-updates.php?files=dump-gen-debug3.php&run=1` çıktısını al
(dosya pushlandı, taze path). Ayrıca YENİ bir dump yaz (yeni adla!) şunları toplasın:
- api.php'ye `?action=generate` POST'unun gerçekten ulaşıp ulaşmadığı (en tepeye, match()
  öncesine geçici bir append-log: zaman + action + body uzunluğu → jbdata/req-log.txt)
- PHP error_log konumu ve son satırları (fatal error kanıtı)
- doGenerate'te CH_GENDEBUG2 bloğunun TAM konumu (belki $answers tanımından ÖNCE bir
  return/exit path'i var; ya da CH_DOCQUAL edit'lerinden biri prompt string'ini bozdu —
  api.php 38969 bayt, dump ile CH_DOCQUAL çevresini ham göster)
Hipotezler (sırayla test et):
a) api.php'de runtime fatal (ör. CH_DOCQUAL/CH_GENDEBUG2 sonrası undefined değişken
   değil — null coalescing var — ama string interpolasyonu bozulmuş olabilir) → 500 →
   frontend genDoc catch'e düşüyor; birden çok bekleme mesajı = kullanıcı tekrar tekrar
   deniyor VEYA followup/pendingDoc yeniden tetikliyor.
b) Edge cache POST'u bozuyor olabilir mi? (normalde POST cache'lenmez ama bu edge
   zaten kural dışı davranıyor) — curl ile POST atıp ham cevabı gör.
c) CH_LAUNCH_UI wrapGenDoc: finally bloğu sadece TEK #ch-wait-msg siliyor; paralel
   çağrıda öncekiler kalıyor. Düzeltme: id yerine class + hepsini sil; ve genDoc
   çalışırken ikinci çağrıyı engelle (busy kilidi zaten var mı kontrol et).
   KULLANICI TALEBİ (net): "Bitte warten" mesajı (1) SADECE BİR KEZ görünecek —
   yenisi eklenmeden önce mevcut TÜM bekleme mesajları silinecek (class ile
   querySelectorAll + hepsini remove; genDoc başında da temizle), ve (2) TÜM
   dillerde çevrilecek — dil seçimi ÜLKE belge dili (DOCLANG[CC]) değil ARAYÜZ
   dili (ch_uilang) olacak; WAIT sözlüğüne eksik diller (ar, ru) eklenecek.
KABUL KRİTERİ: Kündigung Internetvertrag + tüm alanlar dolu + foto → TEK bekleme
mesajı → belge geliyor → belgede: kullanıcı GÖNDEREN, ch_empfaenger MUHATAP, tüm form
cevapları gerçek değer olarak metinde, [PLACEHOLDER] yok → Anträge listesinde görünüyor.

## #1b — FOTO AKIŞI: yüklenen foto dilekçede KULLANILMALI (kullanıcı talebi, zorunlu)
Backend hazır ve kanıtlı ÇALIŞIYOR: doGenerate, $b['photos'] doluysa callClaudeVision
ile okuyup "AUS HOCHGELADENEN DOKUMENTEN:" olarak answersText'e ekliyor (dump'ta kod
görüldü). Debug'da photo_count=0 → foto FRONTEND'den hiç gitmemiş. Zincir:
- genDoc gönderiyor: photos: Object.values(window._chPhotos||{})
- attach kutusu (apply-attach-plus) dosyaları window._chDocFiles'a koyuyor ve
  syncDocPhotos() çağırıyor — _chDocFiles → _chPhotos senkronunun GERÇEKTEN çalıştığı
  ve formatın backend beklentisiyle eşleştiği DOĞRULANMALI (backend filtresi:
  isset($p['data']) && strlen 100..5MB — yani {data:<base64>, mime:...} bekliyor).
YAPILACAK: (1) dump ile syncDocPhotos gövdesini çıkar, _chPhotos'a ne yazdığını gör;
(2) #1'deki savunma sarmalayıcısına foto fallback'i de ekle: _chPhotos boş ama
window._chDocFiles doluysa oradan doldur; (3) uçtan uca test: foto yükle → üret →
debug'da photo_count>0 + vision_in_answers=EVET + belge foto verisini kullanıyor.

## #2 — KRİTİK: Gönderen/Muhatap rol karışması
Kullanıcının adı "Antragsgegner" (karşı taraf) olarak yazılmış. api.php draft
prompt'unda SENDER bloğu placeholder'larla, muhatap ise CASE DETAILS içindeki
ch_empfaenger'den geliyor. Düzeltme (apply ile api.php'ye):
- Draft prompt'a açık satır ekle: "SENDER = the applicant ([VORNAME] [NACHNAME]) —
  never list the sender as Antragsgegner/defendant/recipient. The field
  'ch_empfaenger' in CASE DETAILS is ALWAYS the recipient/counterparty."
- Test: adı belli bir profil + ch_empfaenger="Telekom GmbH, X Str. 1" → belgede
  gönderen=kullanıcı, muhatap=Telekom.

## #3 — İş Bul & Başvur paneli işlevsiz
Belirtiler: arama sonuç bulmuyor; meslek sorulmuyor; CV üretmiyor; e-posta yok;
1. kutuya tarayıcı otomatik doldurması ADRES yazıyor (etiket yok, sadece placeholder).
Düzeltmeler (apply-jobsearch-ui2.php — YENİ index.php yaması, CH_JOBS_UI2):
a) Inputlara GÖRÜNÜR label + `autocomplete="off"` + `name` çakışmayan adlar (jbkw1 gibi)
   → tarayıcı adres doldurmasın.
b) Pozisyon kutusu BOŞSA kullanıcı profilinden/CV metninden meslek TAHMİN etme yerine
   panel açılışında kısa bir soru akışı: "Mesleğiniz/alanınız nedir?" (arayüz dilinde),
   cevabı localStorage ch_job_role'a yaz, kutuya önceden doldur.
c) 0 sonuç dönerse NEDENLİ mesaj göster ("AI önerisi boş döndü — alanı/bölgeyi
   sadeleştirin"); backend jobsearch cevabına 'error' alanını UI'da göster.
d) Backend chJobFindAI: callClaude null dönerse (API anahtarı/quota) bunu
   {'error':'ai_unavailable'} olarak döndür; UI bunu açıkça söylesin.
e) CV üretimi: "CV oluştur" butonu ekle → mevcut tr_basvuru/bew_ CV belgeleri akışına
   yönlendir (DOCS'ta CV dokümanı aç: showCat/openDoc çağrısı) — yeni backend gerekmez.
f) E-posta: otomatik GÖNDERME YOK (spam riski, kullanıcıyla önceden anlaşıldı);
   bunun yerine mektup modalına "E-posta taslağı" butonu: mailto:?subject=Bewerbung …
   &body=<mektup> (encodeURIComponent, 1800 karakteri aşarsa sadece kopyala düğmesi).
KABUL: "Berlin + yazılım" → 5-12 gerçek firma kartı; boşsa nedeni yazıyor; karttan
mektup + mailto taslağı; panel meslek soruyor/hatırlıyor; adres otomatik dolmuyor.

## #3b — BEWERBUNG UCTAN UCA AKISI (kullanıcı talebi — tam spec)
Hedef: "İş başvurusunda bulunma" belgeleri profesyonel bir akış olsun.
1) OTOMATİK DOLDURMA: Bewerbung/CV formlarındaki kişisel alanlar profilden
   otomatik dolsun. ÖNCE dump gerekli: tr_basvuru/bew_* dokümanlarının q key'leri
   (ör. "kisisel","ad_soyad","iletisim"?) — sonra render'daki _pf haritasına bu
   key'ler eklenir (f1+f2 ad, f6 tel, f7 email, f4 şehir). Ayrıca hedef işveren:
   İş panelinden gelindiyse (seçilen ilan) firma adı+yeri forma otomatik yazılsın
   (window._chJobCtx = seçilen ilan; form açılırken firma alanına inject).
2) ANSCHREIBEN → E-POSTA: ⚠️ TEKNİK SINIR: mailto: EK DOSYA EKLEYEMEZ (tarayıcı
   standardı; hiçbir sitede yapılamaz). İki meşru yol:
   a) mailto ile konu+gövde (Anschreiben metni) hazır açılır; CV/Lebenslauf PDF
      indirilir, kullanıcı manuel ekler (1 tık fark), VEYA
   b) sitenin MEVCUT send-doc.php altyapısıyla (PROTECT listesinde var — e-posta
      gönderebiliyor) backend'den ekli e-posta gönderimi: kullanıcının KENDİ
      adresine Anschreiben+CV PDF ekli mail atılır → oradan iletir. Önce
      dump-senddoc ile imza/parametreleri çıkar, sonra 'sendBewerbung' action.
   Öneri: (a) hemen, (b) ikinci adım.
3) EKSİK KONTROL + YÖNLENDİRME: Anschreiben üretilmeden önce kontrol listesi:
   profil tam mı, CV/Lebenslauf var mı (Anträge geçmişinde bew_cv/lebenslauf tipli
   belge VAR MI diye action=history'den bak), vesikalık foto yüklü mü. Eksikler
   arayüz dilinde TEK mesajla listelenir; ilgili bölüme yönlendirilir (CV yoksa
   CV formunu aç: showCat/openDoc ile bew_/tr_basvuru kategorisi).
4) FOTOĞRAFLI ESTETİK CV ŞABLONU: kullanıcıdan vesikalık foto al (mevcut attach
   altyapısı), CV/Lebenslauf çıktısına yerleştir. Uygulama: CV HTML şablonu
   (print/PDF için A4, foto sağ üst, temiz tipografi — form-engine'deki staatliche
   HTML form deseni örnek alınabilir); doc çıktısı yerine bu şablona render eden
   özel bir viewer (sadece bew_cv/lebenslauf tipleri için). Foto base64 olarak
   localStorage ch_cv_photo'da tutulur (sunucuya gerek yok).
5) İŞE ÖZGÜ SORULAR: Bewerbung q listesine ekle: "Bu alanda kaç yıl deneyim?",
   "Öne çıkan 2-3 başarı/yetkinlik", "Neden bu firma?" (opsiyonel) — cevaplar
   Anschreiben metnine hazır cümleler olarak işlensin (backend'e ek gerek yok,
   CASE DETAILS zaten kullanılıyor).
KABUL: İş panelinden ilan seç → Anschreiben formu firma+kişisel bilgiler dolu
açılır → deneyim soruları sorulur → metin hazır → "E-posta ile gönder" mailto
(konu+gövde dolu) + CV PDF indirme; CV yoksa önce CV akışına yönlendirir; CV
şablonu fotoğraflı ve estetik.

## #3c — IS ARAMA: E-POSTA + 50 SONUC + PLAN BAZLI GUNLUK KOTA (kullanıcı talebi)
1) FIRMA E-POSTASI: ⚠️ AI'ya serbest e-posta yazdirmak YASAK KALIR (uydurma adres
   riski — basvuru boşa gider, spam/hukuk riski). Iki asamali cozum:
   a) chJobFindAI prompt'una ek: "Include an application e-mail ONLY if you are
      highly confident it is the company's real, public careers address (e.g.
      published careers@/jobs@); otherwise empty string. NEVER guess." → UI'da
      e-posta varsa kartta goster + 'Basvuruyu e-postayla hazirla' butonu.
   b) Opsiyonel guclendirme: Hunter.io benzeri yasal e-posta bulma API'si
      (kullanici ucretsiz anahtar alir; jbdata/hunter.key deseni, Jooble gibi).
2) KULLANICININ KENDI E-POSTASINA GONDERIM: 'Paketi e-postama gonder' butonu →
   backend send-doc.php altyapisiyla (once dump: imza/parametre) kullanicinin
   KAYITLI adresine Anschreiben metni (+ CV PDF, #3b sablonu hazirsa ekli)
   gonderilir; kullanici kendi kutusundan firmaya iletir/gonderir. (Otomatik
   firmaya gonderim YOK — onceki karar: spam/ToS riski.)
3) 50 SONUCA KADAR: 'Daha fazla goster' butonu → ayni aramada devam:
   - Jooble yolunda: sayfalama (page param) ile 25+25.
   - AI yolunda: ikinci cagri "list 25 MORE, different from: <onceki firmalar>"
     (dedup: company adi normalize edilip Set'te tutulur). Kart limiti 50.
4) GUNLUK PLAN KOTASI (istemci+backend):
   - Basic: gunde 3 arama (~50 firma/arama, gunluk ~150 kart tavani yerine
     kullanici ifadesi esas: 3 arama = yeterli)  → sayac localStorage
     'ch_jobq_YYYY-MM-DD' {searches:n, companies:n}.
   - Pro: gunde 100 firma karti.  - Elite: gunde 200 firma karti.
   - Free: panel gorunur ama arama kilitli → plan sec CTA (paywall).
   - Kota dolunca arayuz dilinde net mesaj + Konto/plan linki. Backend'e de
     basit koruma: jobsearch action gunluk IP/sid sayaci (jbdata/jobq-<gun>.json)
     — istemci atlatilirsa da sinir calisir.
KABUL: Basic hesapta 4. arama engellenir; 'Daha fazla' ile 50 karta ulasilir;
e-posta SADECE gercek/resmi oldugunda gorunur; 'e-postama gonder' kullanicinin
kayitli adresine paket yollar.

## #4 — Anträge dolmuyor
Büyük olasılıkla #1'in sonucu (belge kaydedilmiyor). #1 çözülünce yeniden test et.
Hâlâ boşsa: doHistory sid eşleşmesini dump'la (STORE_DIR glob '{$sid}_*' vs kayıtta
kullanılan sid; giriş yapan kullanıcının sid'i istekten nasıl geliyor).

## #5 — Debug kalıntılarını temizle (launch öncesi)
CH_GENDEBUG + CH_GENDEBUG2 blokları api.php'den çıkarılacak (apply-remove-gendebug.php:
iki bloğu marker'larıyla bulup sil, php -l, .bak). jbdata/gen-debug*.json sil.
dump-* dosyalarını sunucudan sil (rm chat/dump-*.php) — pull-updates kalabilir.

## #6 — Kündigung kalite regresyon testi
CH_DOCQUAL uygulanmış ama kullanıcı testinde hâlâ: cevaplar yok (bkz #1), uzunluk?
#1 çözüldükten SONRA aynı Kündigung senaryosunu yeniden üret ve şunları doğrula:
kısa/öz, 1-2 kanun maddesi, uydurma emsal yok, tüm cevaplar metinde. Sorun sürerse
CH_DOCQUAL edit'lerinin canlı api.php'de TAM metnini dump'la karşılaştır (edit'ler
str_replace ile girdi; önceki yamalarla satır kaymış olabilir).

## #7 — Edge cache kalıcı çözümü (kullanıcı aksiyonu)
Origin header'ları doğru (CH_NOCACHE + CH_STRONG_NOCACHE) ama edge override ediyor
(kanıt: cache-control: public, max-age=2678400; cf-cache-status: HIT; query-string
yok sayma). Kod tarafında yapılacak bir şey KALMADI. Kullanıcı GoDaddy desteğe hazır
metni göndermeli (önceki mesajlarda İngilizce hazır): /chat/* için cache bypass kuralı
veya "respect origin Cache-Control". Bu yapılmadan kullanıcılar eski sürüm görebilir.

## #8 — Google Play imzalı AAB (kullanıcı aksiyonu + CI hazır)
CI'da build-release job'u hazır (.github/workflows/android-build.yml). Kullanıcı:
keytool ile keystore üret → base64 → 4 GitHub Secret (ANDROID_KEYSTORE_BASE64,
ANDROID_KEYSTORE_PASSWORD, ANDROID_KEY_ALIAS, ANDROID_KEY_PASSWORD) → Actions'tan
workflow'u çalıştır → Artifact: CHelp-release.aab → Play Console'a yükle.
Rehber: mobile/BUILD-ANDROID.md §4b. Play form cevapları: mobile/PLAY-COMPLIANCE.md.
NOT: Debug APK Play'e YÜKLENEMEZ; internal testing bile imzalı AAB ister.

## #8b — PLAN BAZLI PROFİL SİSTEMİ (kullanıcı talebi, tasarım hazır — implement edilecek)
Temel katman TAMAM: CH_PROFILE_REQ (üretim öncesi profil zorunlu) + CH_ONBOARD
(girişte Konto'ya yönlendirme + DSGVO notu) + CH_ANON_FINAL (ad/adres/iletişim
AI'ya HİÇ gitmiyor — not metni artık hukuken doğru). KALAN:
- FREE/BASIC: TEK profil (mevcut davranış) — tüm dilekçeler hep bu isme. ✅ zaten böyle.
- PRO: en fazla 5 profil. localStorage 'ch_profiles' = [{f1..f7},...]; Konto'ya
  profil yöneticisi (listele/ekle/sil, 5 sınırı, plan!=='pro'&&'elite' ise gizli);
  belge formunun üstüne profil seçici (select, data-noi18n) — seçilen profil
  genDoc çağrısında window.P yerine geçer (P'yi kalıcı DEĞİŞTİRME; sadece o
  üretim için profile param'ı override: genDoc sarmalayıcısında
  body.profile=secilen). Backend değişikliği GEREKMEZ ($profile zaten body'den).
- ELITE: belgedeki gönderen adı SERBEST değiştirilebilir — form üstüne opsiyonel
  "Absender-Name (frei)" alanı (data-k ch_sender_name); api.php'de küçük ek:
  if(!empty($answers['ch_sender_name'])) → isim parçala, $ph VORNAME/NACHNAME
  override (CH_SENDER_OVERRIDE marker'ıyla, $ph tanımından hemen sonra).
- Plan tespiti: P.plan||ch_user.plan ('free','basic','pro','elite').
- Test: pro'da 5. profilden sonra ekleme engellenir; seçilen profille üretilen
  belgede o profilin adı; elite'te serbest ad belgeye yazılır; free'de hiçbir
  yeni UI görünmez.

## #9 — Bekleyen küçük işler
- Task #9 (eski liste): ülke resmi formları faz 2 (Staatliche mantığı diğer ülkelere,
  form içi 1,99€ Stripe) — başlanmadı.
- Jooble anahtarı gelirse: set-jooble-key.php yaz (jbdata/jooble.key'e kaydeder,
  web'den erişim .htaccess ile kapalı) → jobsearch otomatik gerçek ilanlara geçer.
- apply-jobsearch-ui'de setInterval(refreshLabels,1500) etiketleri her 1.5 sn yeniliyor
  — input değerlerine dokunmuyor ama gereksiz; dil değişim event'ine bağlanabilir.

## Test protokolü (her düzeltmeden sonra)
1) İlgili apply-*.php'yi fixture ile YEREL test et (php -l + node --check + davranış).
2) pull-updates ile uygula; çıktıda "✓" doğrula.
3) İçerik değişen dump'ları YENİ adla koş (edge cache kuralı).
4) Kullanıcıdan gizli pencere + ?t= ile ekran görüntüsü iste.
