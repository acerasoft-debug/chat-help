# ChatHelp Business – native App (Android & iOS)

Capacitor‑8‑Hülle um die Business‑App **https://chat-help.com/chat/app.php**. Die App lädt immer die Live‑Seite
(jede Änderung an `chat/app.php` ist sofort in der App), bringt aber alles mit, was eine Store‑App ausmacht:
native Push‑Benachrichtigungen (FCM/APNs) mit eigenem Kanal „Aufträge & Hinweise“, Haptik, Splash, Statusleiste
in Hell/Dunkel, Zurück‑Taste (Android) bzw. Wischgeste (iOS) und eine Offline‑Seite (`www/index.html`).

| | |
|---|---|
| App‑ID / Bundle‑ID | `com.chathelp.business` (nach der ersten Veröffentlichung **nicht mehr ändern**) |
| Name | ChatHelp Business (Symbol: „ChatHelp“) |
| Start‑URL | `https://chat-help.com/chat/app.php?src=native` (`CH_APP_URL` überschreibt, z. B. Staging) |
| Push | Server `chat/push-lib.php` → FCM HTTP v1 → Android direkt, iOS über APNs |

## Aufbau

```
mobile-business/
  capacitor.config.json   server.url, Splash, SystemBars (Safe‑Area), Push‑Optionen
  www/index.html          Start‑/Offline‑Seite (errorPath) – leitet zur App weiter, sobald Netz da ist
  assets/src/*.svg        Vorlagen für App‑Symbol, Adaptive Icon, Splash hell/dunkel
  scripts/
    build-www.js          setzt die App‑URL (CH_APP_URL) in www + Config, --check für CI
    setup-platform.js     cap add → patch → cap sync → Symbole → patch (idempotent)
    assets.js             SVG → PNG (sharp) → capacitor-assets (alle Größen)
    patch-android.js      Kanal chathelp_alerts, POST_NOTIFICATIONS, FCM‑Standards, Signatur, Versionsnummern
    patch-ios.js          FirebaseMessaging, FCM‑Token, Wischgeste, Info.plist, Entitlements, Signatur
  ci/ExportOptions.plist.tpl
```

`android/` und `ios/` werden **nicht** eingecheckt – sie entstehen lokal und in CI immer frisch per `npm run setup:*`.

## Lokal bauen

Voraussetzungen: Node 20+, für Android JDK 21 + Android Studio (SDK 36), für iOS ein Mac mit Xcode 16+ und CocoaPods.

```bash
cd mobile-business
npm ci
# Android
cp ~/secrets/google-services.json android-google-services.json   # optional, siehe Firebase
npm run setup:android
cp android-google-services.json android/app/google-services.json  # nach dem ersten setup
npm run build:apk                     # → android/app/build/outputs/apk/debug/app-debug.apk
npx cap open android                  # oder in Android Studio starten
# iOS (Mac)
npm run setup:ios
cp ~/secrets/GoogleService-Info.plist ios/App/App/ && npm run patch:ios
npx cap open ios                      # Signing & Capabilities: Team wählen, „Push Notifications“ ist über App.entitlements aktiv
```

Staging testen: `CH_APP_URL=https://staging.chat-help.com/chat/app.php?src=native npm run www && npx cap sync`.

## Native Push einrichten (einmalig)

1. **Firebase‑Projekt** (console.firebase.google.com) anlegen.
2. **Android‑App** `com.chathelp.business` hinzufügen → `google-services.json` herunterladen
   (CI‑Secret `ANDROID_GOOGLE_SERVICES_JSON`, Inhalt als Base64 oder Klartext).
3. **iOS‑App** `com.chathelp.business` hinzufügen → `GoogleService-Info.plist` (CI‑Secret `IOS_GOOGLE_SERVICE_INFO_PLIST`).
   Im Apple‑Developer‑Konto einen **APNs‑Schlüssel (.p8)** erzeugen und in Firebase → Projekteinstellungen → Cloud Messaging → Apple‑App hochladen.
   Die App‑ID braucht die Capability **Push Notifications** (Provisioning‑Profil danach neu erzeugen).
4. **Server**: Firebase → Projekteinstellungen → Dienstkonten → „Neuen privaten Schlüssel generieren“ und den JSON‑Text in
   `chat/db.php` als `FCM_SERVICE_ACCOUNT_JSON` hinterlegen (oder `FCM_SERVICE_ACCOUNT_FILE` = Pfad außerhalb des Webroots).
   `GET /chat/tokens-api.php/v1/push/config` meldet danach `"fcm_ready": true`.
5. In der App: **Meldungen → Benachrichtigungen aktivieren** → `app-bridge.js` holt das FCM‑Token und meldet das Gerät über
   `/v1/push/subscribe {type:'fcm'}` an. „Test senden“ prüft alle Geräte.

Ablauf einer Meldung: `ch_push()` → FCM mit `channel_id=chathelp_alerts`, `tag`, `badge`, `data.url` →
Tippen öffnet die App, `pushNotificationActionPerformed` → `app.php` springt in den Bereich (z. B. Bestellungen) und öffnet den Eintrag
(`tag` = `order-…`, `ride-…`, `appt-…`, `hotelreq-…`, `hotel-…`, `cb-…`, `call-…`) und markiert ihn im Postfach als gelesen.
Im Vordergrund zeigt die App einen eigenen Hinweis + lokale Benachrichtigung (Android) und lädt den Feed neu.

## CI (GitHub Actions)

Beide Workflows laufen nur manuell (**Actions → Run workflow**):

* `.github/workflows/android-business.yml` – Debug‑APK immer; signiertes Release‑AAB + APK, wenn die Keystore‑Secrets existieren.
* `.github/workflows/ios-business.yml` – baut nur, wenn die Apple‑Secrets existieren (sonst Hinweis und Ende); optional Upload zu TestFlight.

| Secret | Wofür |
|---|---|
| `ANDROID_KEYSTORE_BASE64` | Upload‑Keystore (`base64 -w0 upload.jks`) |
| `ANDROID_KEYSTORE_PASSWORD`, `ANDROID_KEY_ALIAS`, `ANDROID_KEY_PASSWORD` | Zugang zum Keystore |
| `ANDROID_GOOGLE_SERVICES_JSON` | Firebase‑Konfiguration Android (Base64 oder Klartext) |
| `IOS_CERT_P12_BASE64`, `IOS_CERT_PASSWORD` | Apple‑Distribution‑Zertifikat (.p12) |
| `IOS_PROVISION_PROFILE_BASE64` | App‑Store‑Provisioning‑Profil für `com.chathelp.business` (mit Push) |
| `IOS_TEAM_ID` | Apple Team‑ID (10 Zeichen) |
| `IOS_GOOGLE_SERVICE_INFO_PLIST` | Firebase‑Konfiguration iOS (Base64 oder Klartext) |
| `APPSTORE_API_KEY_ID`, `APPSTORE_API_ISSUER_ID`, `APPSTORE_API_KEY_P8_BASE64` | optional: automatischer TestFlight‑Upload |

Keystore einmalig erzeugen (gut aufbewahren – ohne ihn keine Updates im Play Store):
`keytool -genkeypair -v -keystore upload.jks -alias chathelp -keyalg RSA -keysize 4096 -validity 10000`

## Veröffentlichen

**Google Play**: AAB aus dem Workflow in der Play Console hochladen (zuerst „Interner Test“). Datensicherheit: E‑Mail (Login),
Geräte‑Token für Benachrichtigungen; keine Werbung, kein Tracking. Danach den Play‑Link in `chat/app-links.json` eintragen.

**App Store**: Build über TestFlight verteilen, im App‑Store‑Connect‑Eintrag einen Test‑Login für das Review hinterlegen
(Demo‑Konto mit Bestellungen). Native Funktionen für Richtlinie 4.2: Push‑Benachrichtigungen, Haptik, Offline‑Start, Wischgesten.
Danach TestFlight‑ bzw. App‑Store‑Link in `chat/app-links.json` eintragen.

`chat/app-links.json` (optional, alle Felder https):
```json
{ "play": "https://play.google.com/store/apps/details?id=com.chathelp.business",
  "apk": "", "appstore": "https://apps.apple.com/app/id…", "testflight": "https://testflight.apple.com/join/…" }
```
Die Seite `chat/app-download.php` zeigt die Links automatisch statt „Bald im App Store / bei Google Play“.

## Fehlersuche

* **Keine Meldungen auf Android**: Einstellungen → Apps → ChatHelp Business → Benachrichtigungen → „Aufträge & Hinweise“ an;
  Akku‑Optimierung für die App ausschalten (Samsung/Xiaomi). `fcm_ready` am Server prüfen.
* **iOS „registration_error“**: Push‑Capability im Profil fehlt oder APNs‑Schlüssel nicht in Firebase hochgeladen.
* **Weiße Seite / Offline‑Seite**: Server nicht erreichbar oder `allowNavigation` passt nicht zu `CH_APP_URL` (`npm run check`).
