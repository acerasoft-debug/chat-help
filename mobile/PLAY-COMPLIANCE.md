# ChatHelp — Google Play Uyum Denetimi ve Kontrol Listesi

Son denetim: 2026-07 (bu depo durumuna göre). Teknik maddeler otomatik yamayla
çözüldü; form/politika maddeleri Play Console'da senin dolduracağın alanlar —
aşağıda hazır cevaplarıyla.

---

## 1) Teknik gereksinimler — DURUM

| Gereksinim | Play kuralı | Durum |
|---|---|---|
| **Target API 35** (Android 15) | Ağu 2025'ten beri yeni uygulama + güncellemeler için zorunlu | ✅ **Otomatik** — `patch-android.js` `variables.gradle`'ı 34→35 yamalar (`npm run setup:android`) |
| Android 15 edge-to-edge | targetSdk 35'te sistem çubukları içeriğin üzerine biner | ✅ **Otomatik** — `styles.xml`'e opt-out eklenir, mevcut durum çubuğu düzeni korunur |
| **16 KB bellek sayfası desteği** | Kas 2025'ten beri Android 15+ hedefleyen yeni uygulama/güncellemeler için zorunlu | ✅ Uygulamada **hiç native .so kütüphanesi yok** (saf WebView kabuk + Java eklentiler) — otomatik uyumlu |
| AAB formatı + Play App Signing | Yeni uygulamalar için zorunlu | ✅ `./gradlew bundleRelease` → `.aab` (BUILD-ANDROID.md adım 6) |
| Cleartext / karışık içerik yok | Güvenlik | ✅ `allowMixedContent:false`, `androidScheme:https`, tüm trafik HTTPS |
| Minimal izinler | Gereksiz izin = inceleme riski | ✅ `INTERNET` + `RECORD_AUDIO`/`MODIFY_AUDIO_SETTINGS` (App içi sesli giriş / yerel Spracherkennung); kamera native `<input capture>` üzerinden (izin gerektirmez), konum/kişi/SMS yok |
| **Mikrofon izni beyanı** | `RECORD_AUDIO` isteyen uygulama Play'de kullanım amacını beyan etmeli | ⚠️ **Console'da sen beyan et** — amaç: "Sesli giriş (Spracherkennung) — kullanıcı mikrofon tuşuna basınca konuşmasını metne çevirmek". Ses **kaydedilmez/saklanmaz/gönderilmez**; Android'in yerel tanıma servisi (RecognizerIntent) anında metne çevirir. Data safety'de "Audio → toplanmıyor" işaretle (yalnız cihazda işlenir). |
| Hedef kitle | Hukuki içerik | 18+ olarak beyan et (aşağıda) |

> `npm run setup:android` çalıştırdığında beş yama da otomatik uygulanır ve
> CI'daki "Verify Google Play compliance patches" adımı bunları her build'de doğrular.

---

## 2) Play Console formları — HAZIR CEVAPLAR

### Data safety (Veri güvenliği)
Uygulama WebView üzerinden chat-help.com'u yüklediği için sitede toplanan veriler
"uygulama içinde toplanan veri" sayılır. Beyan et:

| Veri | Toplanıyor mu? | Amaç | Paylaşım |
|---|---|---|---|
| E-posta adresi | Evet (hesap) | Hesap yönetimi | Paylaşılmıyor |
| Ad/soyad | Evet (belge içeriği için kullanıcı girer) | Uygulama işlevi | Paylaşılmıyor |
| Kullanıcı içeriği (hukuki metin/soru, yüklenen foto) | Evet | Uygulama işlevi (belge üretimi) | AI işleme için işlemciye iletilir* |
| Ödeme bilgisi | **Hayır** — kart verisi Stripe'ta kalır, uygulama/site kart verisi görmez | — | — |
| Konum, kişiler, SMS, cihaz kimliği | Hayır | — | — |

- "Veriler aktarımda şifreleniyor" → **Evet** (HTTPS).
- "Kullanıcı veri silme talep edebilir" → **Evet** de — ama bunun için sitede
  çalışan bir silme yolu şart (aşağıdaki §3).
- *AI işleme: "Data shared with service providers for app functionality" kapsamında.

### Diğer formlar
- **Privacy policy:** `https://chat-help.com/datenschutz` (canlı ve erişilebilir olmalı — yayından önce tarayıcıda kontrol et).
- **Content rating (IARC anketi):** şiddet/kumar/cinsellik yok → muhtemelen "Everyone"; yine de **Target audience: 18+** seç (hukuki hizmet, sözleşme imzalama ehliyeti).
- **Ads:** "No ads".
- **App access:** hesapsız da gezinilebiliyorsa "All functionality available without special access" de; girişli test istenirse Play'e bir test hesabı ver (review için hazırla).
- **Login credentials for review:** İncelemeci belge üretimini test etmek isteyebilir — geçerli bir test hesabı + (gerekirse) test planı tanımla.

---

## 3) ⚠️ KRİTİK AÇIK MADDE: Hesap silme zorunluluğu

Google Play **Kullanıcı Verileri politikası**: hesap oluşturmaya izin veren her
uygulama şunları sağlamak ZORUNDA (yoksa güncellemeler reddedilir):

1. **Uygulama içinden** hesap silme yolu (WebView'da sitedeki "Konto löschen" yeterli), ve
2. Data safety formuna girilecek **web silme URL'si** (kullanıcı uygulamayı
   silmiş olsa da hesabını silebilmeli).

**Durum: sitede "Konto löschen" özelliği var mı bilinmiyor.** Yoksa bu, Play
yayını öncesi sitede yapılması gereken bir iş (auth.php/konto paneline silme
endpoint'i — her zamanki `apply-*.php` desenimizle eklenir; önce ilgili dump
çıktısı gerekir). Varsa: Data safety formuna URL'sini yaz, mesele kapanır.

---

## 4) Ödeme politikası — mevcut duruş ve risk

**Mevcut:** Uygulama ücretsiz; dijital belge/abonelik satışı WebView içindeki
sitede **Stripe** ile yapılıyor (BUILD-ANDROID.md §8'deki "companion" konumlanması).

**Risk (dürüst değerlendirme):** Google'ın Ödemeler politikası, uygulama içinde
tüketilen dijital içerik satışında **Play Billing** ister. WebView'da Stripe
checkout açılması incelemede "in-app purchase of digital goods" sayılabilir →
ret veya sonradan uyarı ihtimali gerçek.

**Seçenekler (kolaydan zora):**
1. **Olduğu gibi dene** (Internal testing → Production): İlk sürüm için pratik.
   Ret gelirse gerekçe yazılı gelir, plan B'ye geçilir. (Mevcut plan bu.)
2. **AB/ABD dış ödeme programları:** EEA "alternative billing / external offers"
   programı ve ABD'de (Epic kararı sonrası) dış ödeme bağlantılarına izin var —
   ama ikisi de Play Console'da **programa kayıt + beyan** gerektirir, otomatik değil.
3. **Hibrit:** abonelikleri Play Billing'e taşı (native plugin işi, ~birkaç gün),
   tek belge satışı webde kalsın.

Öneri: 1 ile başla; retten sonra 2 (AB+ABD pazarıysa) veya 3.

---

## 5) Push bildirimleri (şimdilik pasif)

`@capacitor/push-notifications` kurulu ama **Firebase projesi yok** → push fiilen
kapalı; Data safety'de beyan gerekmez. Aktive edilirse (BUILD-ANDROID.md'deki
Firebase adımı): Android 13+ için `POST_NOTIFICATIONS` çalışma zamanı izni
istenmeli + Data safety'ye "Device or other IDs (FCM token)" beyanı eklenmeli.
O gün geldiğinde ikisini de ben eklerim.

---

## 6) Yayın öncesi son kontrol (özet sıra)

1. `npm run setup:android` (yamalar + targetSdk 35 otomatik) → CI yeşil mi bak.
2. `https://chat-help.com/datenschutz` canlı mı kontrol et.
3. **Hesap silme** (§3) — sitede var mı doğrula, yoksa önce onu ekleyelim.
4. Keystore oluştur (BUILD-ANDROID.md §4) — YEDEKLE.
5. `./gradlew bundleRelease` → `.aab` → Play Console **Internal testing**.
6. Formları §2'deki cevaplarla doldur.
7. Telefonda test → sorun yoksa Production.
