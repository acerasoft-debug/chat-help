# ChatHelp Android — WebView kurulumu (Pazartesi derlemesi için)

Bu dosya, App'te **yazdırma / PDF indirme / mikrofon / foto yükleme / harici bağlantı**
sorunlarını kökten çözen APK-tarafı ayarlarını içerir. Web tarafından açılamayan
şeylerdir; yalnızca uygulama kodunda etkinleştirilebilir.

## Neden gerekli

App, sunucudaki `chat/index.php`'nin **aynısını** yüklüyor (2026-07-25 gecesi
`stamp` beacon'ı ile kanıtlandı). Kod farkı yok. Fark, WebView'in varsayılan
olarak kapalı gelen yetenekleri:

| Yetenek | Tarayıcı | WebView (varsayılan) |
|---|---|---|
| `window.print()` | ✅ | ❌ sessizce hiçbir şey yapmaz |
| Dosya indirme (`Content-Disposition: attachment`) | ✅ | ❌ DownloadListener yoksa yok sayılır |
| `alert` / `confirm` / `prompt` | ✅ | ❌ `onJsAlert` yoksa görünmez |
| `window.open('_blank')` | ✅ | ❌ multiple windows kapalıysa `null` döner |
| Mikrofon / kamera | ✅ | ❌ `onPermissionRequest` yoksa reddedilir |
| Dosya seçici (`<input type=file>`) | ✅ | ❌ `onShowFileChooser` yoksa açılmaz |

Bu tablonun **her satırı** bu projede fiilen soruna yol açtı.

---

## 1) Play Console sürüm kuralı

Sürüm kodu **asla geriye gidemez**. Kullanıcılarda `5 (1.4)` varsa `4` de `5` de
yayınlanamaz — sıradaki **6** olmalı.

```gradle
// app/build.gradle
android {
    defaultConfig {
        versionCode 6
        versionName "1.5"
    }
}
```

İmza anahtarı öncekiyle **aynı** olmalı (Play App Signing açıksa Play hallediyor).

---

## 2) WebView kurulumu (Kotlin)

```kotlin
private var filePathCallback: ValueCallback<Array<Uri>>? = null
private val REQ_FILE = 1001

private fun setupWebView(webView: WebView) {
    val ws = webView.settings
    ws.javaScriptEnabled = true
    ws.domStorageEnabled = true
    ws.javaScriptCanOpenWindowsAutomatically = true
    ws.setSupportMultipleWindows(true)
    ws.allowFileAccess = true
    ws.mediaPlaybackRequiresUserGesture = false   // mikrofon için

    // ── 1) İNDİRME: "PDF inmiyor" bununla biter ──
    webView.setDownloadListener { url, userAgent, contentDisposition, mimeType, _ ->
        try {
            val req = DownloadManager.Request(Uri.parse(url))
            req.setMimeType(mimeType)
            req.addRequestHeader("User-Agent", userAgent)
            CookieManager.getInstance().getCookie(url)?.let {
                req.addRequestHeader("Cookie", it)
            }
            val name = URLUtil.guessFileName(url, contentDisposition, mimeType)
            req.setNotificationVisibility(
                DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED)
            req.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, name)
            (getSystemService(DOWNLOAD_SERVICE) as DownloadManager).enqueue(req)
            Toast.makeText(this, "Download: $name", Toast.LENGTH_SHORT).show()
        } catch (e: Exception) {
            Toast.makeText(this, "Download-Fehler", Toast.LENGTH_SHORT).show()
        }
    }

    webView.webChromeClient = object : WebChromeClient() {

        // ── 2) JS DIALOG: alert/confirm/prompt görünür olur ──
        override fun onJsAlert(v: WebView?, url: String?, msg: String?, r: JsResult?): Boolean {
            AlertDialog.Builder(this@MainActivity)
                .setMessage(msg)
                .setPositiveButton("OK") { _, _ -> r?.confirm() }
                .setCancelable(false).show()
            return true
        }
        override fun onJsConfirm(v: WebView?, url: String?, msg: String?, r: JsResult?): Boolean {
            AlertDialog.Builder(this@MainActivity)
                .setMessage(msg)
                .setPositiveButton("OK") { _, _ -> r?.confirm() }
                .setNegativeButton("Abbrechen") { _, _ -> r?.cancel() }
                .setCancelable(false).show()
            return true
        }

        // ── 3) DOSYA SEÇİCİ: foto/belge yükleme ──
        override fun onShowFileChooser(
            v: WebView?, cb: ValueCallback<Array<Uri>>?,
            params: FileChooserParams?
        ): Boolean {
            filePathCallback?.onReceiveValue(null)
            filePathCallback = cb
            return try {
                startActivityForResult(params!!.createIntent(), REQ_FILE); true
            } catch (e: Exception) {
                filePathCallback = null; false
            }
        }

        // ── 4) MİKROFON / KAMERA izni ──
        override fun onPermissionRequest(request: PermissionRequest) {
            runOnUiThread { request.grant(request.resources) }
        }
    }

    webView.webViewClient = object : WebViewClient() {
        // ── 5) HARİCİ BAĞLANTI: window.open, mailto:, tel: dışarı açılsın ──
        override fun shouldOverrideUrlLoading(v: WebView, req: WebResourceRequest): Boolean {
            val url = req.url.toString()
            return if (url.startsWith("https://chat-help.com")) {
                false                                  // site içi → WebView'de kalsın
            } else {
                try { startActivity(Intent(Intent.ACTION_VIEW, req.url)) } catch (_: Exception) {}
                true                                   // dışarısı → sistem tarayıcısı
            }
        }
    }
}

// dosya seçici sonucu
override fun onActivityResult(rc: Int, res: Int, data: Intent?) {
    super.onActivityResult(rc, res, data)
    if (rc == REQ_FILE) {
        filePathCallback?.onReceiveValue(
            WebChromeClient.FileChooserParams.parseResult(res, data))
        filePathCallback = null
    }
}
```

## 3) YAZDIRMA — menüye "Drucken"

```kotlin
private fun printPage(webView: WebView) {
    val adapter = webView.createPrintDocumentAdapter("ChatHelp-Dokument")
    (getSystemService(PRINT_SERVICE) as PrintManager)
        .print("ChatHelp", adapter, PrintAttributes.Builder().build())
}
```

## 4) Manifest

```xml
<uses-permission android:name="android.permission.INTERNET"/>
<uses-permission android:name="android.permission.RECORD_AUDIO"/>
<uses-permission android:name="android.permission.CAMERA"/>
<!-- Android 9 ve altı için indirme klasörü -->
<uses-permission android:name="android.permission.WRITE_EXTERNAL_STORAGE"
                 android:maxSdkVersion="28"/>
```

---

## Sarmalayıcı servis kullanılıyorsa (Median / GoNative / WebToApp)

Kod yazmaya gerek yok — panelde şu anahtarları aç:

- **Downloads / File Downloads**
- **File Uploads**
- **JavaScript Dialogs (alert/confirm)**
- **Multiple Windows (window.open)**
- **Microphone & Camera permissions**
- **Print support**

Aynı altı yetenek.

---

## Bu yapıldıktan sonra

- "Kostenlos selbst ausdrucken" **doğrudan yazdırır**
- PDF **iner**
- Mikrofon ve foto yükleme çalışır
- Web ile App arasında davranış farkı kalmaz

Web tarafındaki geçici çözümler (belgeyi ekranda gösterme `CH_APPVIEW3`,
📧 PDF-e-posta `CH_MAILBTN`, `pv.php` tarayıcı görünümü) **yedek olarak kalabilir**
— zararı yok, APK desteği gelince kullanıcı zaten doğrudan yolu kullanır.

## İlgili web-tarafı işaretler (bozmayın)

`CH_ISWV_GLOBAL` · `CH_APPVIEW3` · `CH_APPVIEW2C` (4 çağrı) · `CH_MAILBTN`
Kontrol: `chk-applock.php` · Geri alma: `apply-applock-restore.php`
Ayrıntı: `chat/FIXES.md`
