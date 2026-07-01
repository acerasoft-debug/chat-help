# ChatHelp — iOS uygulaması (App Store) kurulum & yayın rehberi

Android ile aynı Capacitor projesi; sadece platform ekleniyor. **Simulator build'i CI'da
imza gerekmeden doğrulanıyor** (`.github/workflows/ios-build.yml`) — yani proje sağlam,
gerçek cihaz/App Store için tek eksik **senin Apple Developer hesabın**.

- **Bundle ID:** `com.chathelp.app` (Android ile aynı — App Store'da bu ID senin adına kayıtlı olmalı)
- **Ödeme:** Android'de olduğu gibi uygulama ücretsiz, satın alma web'de (Stripe). *Apple, dijital
  içerik/hizmet satışında genelde In-App Purchase (IAP) ister — bkz. "Ödeme politikası" altta.*

---

## 0) Gereksinimler (SENİN elinde olması gerekenler)

1. **Mac + Xcode** (App Store Connect'e yükleme ve gerçek cihaz testi için şart — Apple, Xcode dışında imzalı build üretmeye izin vermiyor).
2. **Apple Developer Program hesabı** — $99/yıl → https://developer.apple.com/programs/enroll/
   *(Bu adım atlanamaz; App Store'da yayın için Apple'ın kendi kimlik doğrulaması/ödemesi gerekiyor, benim yapabileceğim bir şey değil.)*
3. **CocoaPods** (`sudo gem install cocoapods` — Xcode Command Line Tools kuruluysa genelde zaten var).

---

## 1) Projeyi hazırla (Mac'inde)

```bash
cd ~/chat-help/mobile
npm install
npm run setup:ios
```

`android/` gibi `ios/` klasörü de git'e girmez (`.gitignore`'da) — her seferinde `cap add ios` ile yeniden üretilir.

---

## 2) İkon & açılış ekranı

```bash
npx capacitor-assets generate --ios
```

Aynı `mobile/assets/` kaynaklarından (CHelp ikon + splash) iOS boyutlarını üretir.

---

## 3) Xcode'da aç, imzala, test et

```bash
npm run open:ios
```

Xcode açılınca:
1. Sol üstte proje adına tıkla → **Signing & Capabilities** → **Team**'i kendi Apple Developer hesabınla değiştir.
2. Üstte bir simulator seç → **▶ Run** → CHelp simulator'de açılır.
3. Gerçek iPhone'da denemek istersen kabloyla bağla, cihazı seç, **Run**.

---

## 4) App Store Connect'te uygulama oluştur

1. https://appstoreconnect.apple.com → **My Apps → +**
2. Bundle ID: `com.chathelp.app` (Xcode'da otomatik kayıtlı olur; farklıysa Apple Developer portalından elle ekle).
3. Ad: **ChatHelp**, kategori: hukuk/verimlilik, birincil dil: Almanca (veya Fransızca, pazarına göre).
4. **Gizlilik politikası URL:** `https://chat-help.com/datenschutz`
5. **Age rating:** hukuki/finansal içerik nedeniyle genelde 17+ işaretlenir (Apple'ın anketini doldur).
6. **App Privacy (Nutrition Label):** topladığın verileri beyan et (e-posta, kullanıcı içeriği, ödeme — DSGVO'ya uygun).

---

## 5) Archive + yükle

Xcode'da: **Product → Archive** (gerçek cihaz/"Any iOS Device" seçiliyken; simulator seçiliyse Archive kapalıdır).
Archive bitince açılan pencerede **Distribute App → App Store Connect → Upload**.

Yükleme sonrası App Store Connect'te **TestFlight**'a düşer — önce kendi/ekip testinle dene, sonra **Production**'a gönder.
Apple incelemesi genelde 1-3 gün sürer (Google Play'den biraz daha yavaş/katı olabilir).

---

## 6) Ödeme politikası notu — Apple, Google'dan FARKLI davranabilir

Android'de "uygulama ücretsiz, ödeme web'de Stripe" yaklaşımı sorunsuzdu. Apple'ın App Store Review
Guideline 3.1.1'i, **dijital içerik/hizmeti UYGULAMA İÇİNDEN** satıyorsan In-App Purchase (IAP) zorunlu
kılabilir. ChatHelp bir "hukuki belge oluşturma hizmeti" sattığı için Apple bunu dijital hizmet sayıp
IAP isteyebilir (kesin değil — inceleme ekibine bağlı, "reader app" istisnası da mümkün: uygulama içinde
satış YAPMAYIP sadece dışarıdan satın alınan hizmeti göstermek).

**Öneri:** İlk yüklemede web ödeme akışıyla dene (Android'deki gibi); Apple reddederse iki seçenek çıkar:
1. **Reader app modeli**: uygulamada satın alma linki/butonu göstermeyip, kullanıcı zaten web'den satın almışsa
   giriş yapıp kullanabilsin (satış butonunu app içinden gizle).
2. **IAP entegrasyonu**: `@capacitor/purchases` veya `RevenueCat` gibi bir katmanla Apple'ın IAP'ına bağlan
   (Apple bu satışlardan %15-30 komisyon alır).

Bu kararı Apple'ın gerçek ret/onay geri bildirimini görünce netleştirmek en doğrusu — şimdiden IAP kodu
yazmak (henüz onaylanıp onaylanmayacağını bilmeden) gereksiz karmaşıklık olur.

---

## Durum özeti

| Adım | Durum |
|---|---|
| Capacitor iOS projesi | ✅ CI'da simulator build ile doğrulandı (imza gerekmeden derleniyor) |
| CHelp ikon + splash (iOS boyutları) | ✅ otomatik üretilir |
| Xcode imzalama + gerçek cihaz testi | ⏳ senin Mac + Apple Developer hesabınla |
| App Store Connect kaydı + yayın | ⏳ senin Apple Developer hesabınla ($99/yıl) |
| Ödeme modeli (web vs IAP) | ⏳ Apple'ın ilk inceleme geri bildirimine göre netleşir |
