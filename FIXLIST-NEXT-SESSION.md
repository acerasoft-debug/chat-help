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

## #1 — KRİTİK: Belge üretimi bozuk (sonsuz "Bitte warten" döngüsü)
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
