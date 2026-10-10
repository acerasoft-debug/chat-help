# SARVESTO — mobil uygulama

Mağazanın kendisi (sarvesto.com), tam ekran ve kendi ikonuyla.

## Android
`.github/workflows/sarvesto-android.yml` → imzalı release APK →
`sarvesto.com/uploads/app/SARVESTO.apk`. Uygulama sayfası (`/app`) dosyayı görünce
"Android için indir" düğmesini gösterir. İmza anahtarı depoda değil; sunucuda
`~/.sarvesto-signing/` (web dışı, 0600). Her derleme aynı anahtarla imzalanır,
telefonda güncelleme olarak kurulur. Sürüm numarası iş akışının çalışma numarası.

Google Play'e koymak için: Play Console hesabı (tek seferlik 25 $), aynı iş
akışında `bundleRelease` ile .aab; anahtar aynı kalır (Play App Signing'de
"upload key" olarak).

## iPhone / iPad
Apple, App Store dışından uygulama kurulmasına izin vermiyor. İki yol:
1. **Ana ekran uygulaması (hazır):** Safari → Paylaş → "Ana Ekrana Ekle".
   Manifest, ikon, tam ekran ve çevrimdışı sayfa sitede (`retail/manifest.php`,
   `retail/sw.js`, `retail/offline.html`). Uygulama sayfası adımları gösterir.
2. **App Store:** Apple Developer hesabı (yıllık 99 $) gerekir. Bu proje
   `npm run setup:ios` ile Xcode projesine dönüşür; imzalama ve inceleme
   işletmecinin hesabıyla yapılır.

## Dosyalar
- `capacitor.config.json` — adres, izinli alan adları (Stripe ödeme dahil)
- `www/index.html` — bağlantı yokken gösterilen sayfa
- `assets/` — ikon ve açılış ekranı kaynakları (serif "S." monogramı)
- `scripts/patch-android.js` — geri tuşu, sürüm, release imzası
