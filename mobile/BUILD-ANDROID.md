# ChatHelp — Android uygulaması (Google Play) kurulum & yayın rehberi

Bu klasör, mevcut `chat-help.com/chat` web uygulamasını **native Android** kabuğa saran Capacitor projesidir.
Uygulama açılışta native splash + status bar ile başlar, sonra canlı siteyi yükler (her güncelleme anında yansır),
internet yoksa şık bir offline ekranı gösterir.

- **Paket adı (appId):** `com.chathelp.app`  *(yayından sonra DEĞİŞMEZ — baştan doğru olsun)*
- **Uygulama adı:** `ChatHelp`
- **Ödeme:** uygulama ücretsiz; satın alma sitedeki Stripe akışında (mağaza komisyonu yok).

---

## 0) Gereksinimler (Mac'inde — tek seferlik)

1. **Node.js 18+** (zaten var: `node -v`)
2. **Android Studio** → https://developer.android.com/studio
   - İlk açılışta SDK + "Android SDK Command-line Tools" + bir emulator kur.
   - **JDK 17** Android Studio ile gelir (ayrı kurmana gerek yok).
3. **Google Play Developer hesabı** ($25 tek sefer) → https://play.google.com/console/signup

---

## 1) Projeyi hazırla + premium native yamalar

```bash
cd ~/chat-help/mobile
npm install
npm run setup:android
```

`npm run setup:android` üç şeyi sırayla yapar:
1. `cap add android` → `android/` native klasörünü oluşturur (bu klasör git'e girmez, normaldir).
2. `cap sync android` → eklentileri (App/Browser/SplashScreen/StatusBar) bağlar.
3. `scripts/patch-android.js` → **premium native polish** uygular (aşağıya bak). Güvenle tekrar tekrar çalıştırılabilir (idempotent) — `android/` klasörünü silip yeniden oluşturduğunda sadece `npm run setup:android`'i tekrar çalıştırman yeter.

### Otomatik uygulanan premium native özellikler
- **Donanım geri tuşu**: Android geri tuşu artık uygulamayı aniden kapatmıyor — önce WebView geçmişinde geri gider; en kökteyken çift basışla ("Nochmal drücken zum Beenden") çıkar. (`MainActivity.java`)
- **Otomatik release imzalama**: `android/key.properties` oluşturduğunda (adım 4), `./gradlew bundleRelease` otomatik imzalı çıktı üretir — `build.gradle`'ı elle düzenlemene gerek yok.

---

## 2) İkon & açılış ekranı

Premium **CHelp** ikon + splash zaten hazır (`mobile/assets/` — icon-only, adaptive foreground/background, splash). Sadece tüm Android boyutlarını üret:

```bash
npx capacitor-assets generate --android
```

Bu komut `assets/`'tan okuyup mdpi…xxxhdpi ikon, adaptive icon ve splash'ı `android/`'e yerleştirir.
Kendi logonu kullanmak istersen `assets/icon-only.png` (1024) ve `assets/splash.png` (2732) dosyalarını değiştirip komutu tekrar çalıştır.

---

## 3) Senkronize et

```bash
npx cap sync android
```

> `capacitor.config.json`, `package.json` veya ikonları her değiştirdiğinde bu komutu çalıştır. (Bu komut `MainActivity.java`'ya dokunmaz, geri tuşu yaması kalıcıdır.)

---

## 4) İmza anahtarı (keystore) oluştur — TEK SEFER, ÇOK ÖNEMLİ

Bu anahtarı **kaybetme/yedekle** — uygulamayı güncellemek için hep aynısı gerekir.

```bash
keytool -genkey -v -keystore ~/chathelp-release.jks -keyalg RSA -keysize 2048 -validity 10000 -alias chathelp
```

Sorulan parolayı not et. Sonra `android/key.properties` dosyası oluştur:

```properties
storeFile=/Users/SENIN_KULLANICI/chathelp-release.jks
storePassword=SENIN_STORE_PAROLAN
keyAlias=chathelp
keyPassword=SENIN_KEY_PAROLAN
```

Bu kadar — **`build.gradle`'ı elle düzenlemene gerek yok**, `npm run setup:android` (adım 1) zaten `key.properties` varsa release'i otomatik imzalayacak şekilde bağladı.

> `key.properties` ve `.jks` dosyalarını GİT'e koyma (zaten `.gitignore`'da).

---

## 5) Sürüm numarası

`android/app/build.gradle` → `defaultConfig`:

```gradle
        versionCode 1        // her yeni yüklemede +1 (tam sayı)
        versionName "1.0.0"  // kullanıcıya görünen
```

---

## 6) Yayın paketini (AAB) üret

```bash
cd ~/chat-help/mobile/android
./gradlew bundleRelease
```

Çıktı: `android/app/build/outputs/bundle/release/app-release.aab`
*(Bu .aab dosyasını Play Console'a yükleyeceksin.)*

> Test için cihazda denemek istersen Android Studio'da **Run** ▶ (USB'li telefon/emulator) yeterli.

---

## 7) Google Play Console'da yayınla

1. **Yeni uygulama oluştur** → ad: ChatHelp, dil, ücretsiz.
2. **App content** (zorunlu formlar):
   - **Gizlilik politikası:** `https://chat-help.com/datenschutz` (veya mevcut Datenschutz linkin)
   - **Data safety:** topladığın veriler (e-posta, kullanıcı içeriği) — DSGVO'ya uygun beyan.
   - **Content rating:** anketi doldur (büyük ihtimal "Everyone/3+").
   - **Target audience:** 18+ (hukuki içerik).
   - **Ads:** Reklam yok.
3. **Store listing:** kısa/uzun açıklama (DE/FR/EN), ikon (512×512), feature graphic (1024×500), **en az 2 telefon ekran görüntüsü**.
4. **Release → Testing → Internal testing** ile başla: `.aab` yükle, kendine test linki al, telefonda dene.
5. Sorun yoksa **Production**'a gönder → inceleme (genelde birkaç saat–birkaç gün).

---

## 8) Ödeme politikası notu (önemli)

Uygulama içinde dijital satışı **kendi web akışında** (Stripe) yapıyoruz; uygulama "araç + companion" olarak konumlanıyor.
Bu, ilk sürüm için en pratik ve düşük riskli yol. İleride Google "Play Billing" isterse, abonelikleri Play Billing'e
taşıyıp tek belge satışını web'de bırakan hibrit modele geçeriz.

---

## Sonraki adımlar (Faz 2 — premium native özellikler)

- ✅ ~~Donanım geri tuşu~~ — otomatik (adım 1)
- ✅ ~~Otomatik release imzalama~~ — otomatik (adım 1 + 4)
- 🔔 **Push bildirim** (belge hazır, hatırlatma) — `@capacitor/push-notifications` + FCM
- 📷 **Native kamera** (foto çekip belgeye) — `@capacitor/camera`
- 🔒 **Biyometrik kilit** (Face/parmak izi ile giriş)
- 🍎 **iOS sürümü** — `npx cap add ios` (Mac + Xcode + Apple Developer $99/yıl); Apple IAP kuralı ayrıca ele alınır.

Hepsini sırayla ben hazırlayabilirim — sadece söyle.
