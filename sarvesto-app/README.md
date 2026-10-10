# SARVESTO — mobil ve masaüstü uygulama

Mağazanın kendisi (sarvesto.com), tam ekran ve kendi ikonuyla. Kurulum yolları
`sarvesto.com/app` sayfasında; sayfa ziyaretçinin cihazını tanıyıp onun yolunu öne alır.

| Cihaz | Yol | Durum |
|---|---|---|
| Android | APK doğrudan indirme (`/uploads/app/SARVESTO.apk`) | hazır |
| Android | Google Play | AAB her derlemede hazır; Play hesabı gerekir |
| iPhone / iPad | Safari → Paylaş → "Ana Ekrana Ekle" | hazır |
| iPhone / iPad | App Store / TestFlight | derleme hazır; Apple hesabı gerekir |
| Windows / Mac | Chrome/Edge "Yükle", Mac Safari "Dock'a ekle" | hazır |

## Android — `.github/workflows/sarvesto-android.yml`
İmzalı APK sitede yayınlanır; `SARVESTO.aab` (Google Play paketi) çalışmanın
Artifacts bölümündedir. İmza anahtarı depoda değil, sunucuda `~/.sarvesto-signing/`
(web dışı, 0600); her sürüm aynı anahtarla imzalanır, telefonda güncelleme olarak kurulur.

**Google Play'e otomatik yükleme:**
1. Play Console hesabı (tek seferlik 25 $), "SARVESTO" uygulaması, paket `com.sarvesto.app`.
2. İlk AAB'yi bir kez elle yükleyin (Google'ın kuralı).
3. Play Console → Kurulum → API erişimi → hizmet hesabı ("Sürüm yöneticisi").
4. Depo → Settings → Secrets → Actions: `PLAY_SERVICE_ACCOUNT_JSON` = hesabın JSON anahtarı.
Sonraki her derleme Dahili test kanalına gider.

## iPhone / iPad — `.github/workflows/sarvesto-ios.yml`
GitHub'ın Mac sunucusunda Xcode ile derlenir. Her çalışmada Simulator paketi ve
imzasız `.ipa` çıkar (Artifacts).

**App Store / TestFlight'a otomatik yükleme:**
1. Apple Developer Program (yıllık 99 $), acerasoft LLC adına.
2. App Store Connect → Uygulamalar → "+" → SARVESTO, paket kimliği `com.sarvesto.app`.
3. App Store Connect → Users and Access → Integrations → App Store Connect API →
   anahtar oluşturun (rol: **Admin**), `.p8` dosyasını indirin.
4. Depo → Settings → Secrets → Actions:
   `APPLE_TEAM_ID`, `ASC_KEY_ID`, `ASC_ISSUER_ID`, `ASC_KEY_P8` (.p8 dosyasının içeriği).
Sonraki her derleme imzalanır ve TestFlight'a yüklenir; App Store incelemesine
App Store Connect'ten gönderilir.

Yayına girince bağlantılar `retail/inc/config.php` → `app_store_url`,
`play_store_url`; `/app` sayfası resmi mağaza düğmelerini kendiliğinden gösterir.

## Dosyalar
- `capacitor.config.json` — adres, izinli alan adları (Stripe ödeme dahil), iOS güvenli alan
- `www/index.html` — bağlantı yokken gösterilen sayfa
- `assets/` — ikon (serif "S." monogramı) ve açılış ekranları (açık/koyu)
- `scripts/patch-android.js` — geri tuşu, sürüm, imza, beyaz sistem çubukları
- `scripts/patch-ios.sh` — ad, yönler, durum çubuğu, şifreleme beyanı
