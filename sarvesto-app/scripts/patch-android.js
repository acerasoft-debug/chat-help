#!/usr/bin/env node
/**
 * SARVESTO — Android yerel cila (tekrar çalıştırılabilir)
 * `npx cap add android` android/ klasörünü şablondan yeniden üretiyor; bu betik
 * ondan SONRA çalışır:
 *   1) MainActivity.java — donanım geri tuşu WebView geçmişinde gezinir; kökte
 *      çift basışla çıkılır (tek basışta uygulama aniden kapanmaz).
 *   2) app/build.gradle — release imzası android/key.properties'ten okunur.
 *      Anahtar depoda DEĞİL (depo herkese açık): iş akışı onu sunucudan alır
 *      ve key.properties'i derleme sırasında yazar. Dosya yoksa release
 *      imzasız kalır, derleme bozulmaz.
 *   3) Sürüm numarası: VERSION_CODE / VERSION_NAME ortam değişkenlerinden;
 *      her derleme bir öncekinin üzerine güncelleme olarak kurulabilsin.
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..', 'android');
const GRADLE = path.join(ROOT, 'app', 'build.gradle');

function findMainActivity(dir) {
  if (!fs.existsSync(dir)) return null;
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, e.name);
    if (e.isDirectory()) { const r = findMainActivity(p); if (r) return r; }
    else if (e.name === 'MainActivity.java') return p;
  }
  return null;
}

function patchMainActivity() {
  const file = findMainActivity(path.join(ROOT, 'app', 'src', 'main', 'java'));
  if (!file) { console.log('MainActivity.java yok — önce `npx cap add android`.'); return; }
  const src = fs.readFileSync(file, 'utf8');
  if (src.includes('SARVESTO_BACK_PATCH')) { console.log('MainActivity zaten yamalı.'); return; }
  const pkg = (src.match(/^package\s+([\w.]+);/m) || [])[1] || 'com.sarvesto.app';
  fs.writeFileSync(file, `package ${pkg};

/* SARVESTO_BACK_PATCH — geri tuşu WebView geçmişinde gezinir; kökte çift basışla çıkış. */
import android.webkit.WebView;
import android.widget.Toast;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    private long lastBack = 0;

    @Override
    public void onBackPressed() {
        WebView w = getBridge() != null ? getBridge().getWebView() : null;
        if (w != null && w.canGoBack()) { w.goBack(); return; }
        long now = System.currentTimeMillis();
        if (now - lastBack < 2000) { super.onBackPressed(); return; }
        lastBack = now;
        Toast.makeText(this, "Press back again to close", Toast.LENGTH_SHORT).show();
    }
}
`);
  console.log('✓ MainActivity: geri tuşu');
}

function patchGradle() {
  if (!fs.existsSync(GRADLE)) { console.log('build.gradle yok.'); return; }
  let s = fs.readFileSync(GRADLE, 'utf8');
  if (s.includes('SARVESTO_SIGNING_PATCH')) { console.log('build.gradle zaten yamalı.'); return; }

  // Sürüm: ortamdan (CI çalışma numarası), yoksa şablondaki değer kalır.
  // Atama biçimi şart: "versionCode (x).toInteger()" Groovy'de versionCode(x)
  // çağrısının DÖNÜŞÜ üzerinde toInteger() olarak okunuyor ("Value is null").
  s = s.replace(/versionCode\s+\d+/, 'versionCode = Integer.parseInt(System.getenv("VERSION_CODE") ?: "1")');
  s = s.replace(/versionName\s+"[^"]*"/, 'versionName = (System.getenv("VERSION_NAME") ?: "1.0")');

  const anchor = /(\n[ \t]*buildTypes[ \t]*\{)/;
  if (!anchor.test(s)) { console.log('✗ buildTypes bulunamadı — imza yaması atlandı.'); fs.writeFileSync(GRADLE, s); return; }
  s = s.replace(anchor, `
    /* SARVESTO_SIGNING_PATCH — android/key.properties varsa release imzalanır. */
    def svKeyFile = rootProject.file("key.properties")
    def svHasKey = svKeyFile.exists()
    def svKey = new Properties()
    if (svHasKey) { svKey.load(new FileInputStream(svKeyFile)) }
    signingConfigs {
        release {
            if (svHasKey) {
                storeFile file(svKey['storeFile'])
                storePassword svKey['storePassword']
                keyAlias svKey['keyAlias']
                keyPassword svKey['keyPassword']
            }
        }
    }
$1`);
  s = s.replace(/(release\s*\{[^}]*?minifyEnabled\s+\w+)/, `$1\n            if (svHasKey) { signingConfig signingConfigs.release }`);
  fs.writeFileSync(GRADLE, s);
  console.log('✓ build.gradle: sürüm + release imzası');
}

patchMainActivity();
patchGradle();
