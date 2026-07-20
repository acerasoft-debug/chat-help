#!/usr/bin/env node
/**
 * ChatHelp — Android native premium polish (idempotent)
 * Runs AFTER `npx cap add android` (which regenerates android/ from a template).
 * Patches:
 *   1) MainActivity.java — hardware back button navigates WebView history;
 *      double-press-to-exit at the root instead of an abrupt close.
 *   2) app/build.gradle — wires an optional release signingConfig that reads
 *      key.properties if present (see BUILD-ANDROID.md step 4). No key.properties
 *      yet -> release build stays unsigned, nothing breaks.
 *   3) variables.gradle — targetSdk/compileSdk 35 (Google Play zorunluluğu:
 *      Ağustos 2025'ten beri yeni uygulama ve güncellemeler API 35 hedeflemeli;
 *      Capacitor 6 şablonu 34 üretir -> Play yüklemesi reddedilir).
 *   4) styles.xml — Android 15 zorunlu edge-to-edge'den çıkış (windowOptOut...):
 *      targetSdk 35'te sistem çubukları WebView üzerine binerdi; opt-out ile
 *      mevcut StatusBar (overlaysWebView:false) davranışı aynen korunur.
 *   5) gradle.properties — AGP'nin "compileSdk 35 test edilmedi" uyarısını sustur.
 * Safe to re-run: every edit is guarded by a marker check.
 */
const fs = require('fs');
const path = require('path');

/* CHELP_VERSION — Play her yuklemede daha yuksek versionCode ister.
   Yeni AAB cikarmadan once bu iki degeri artir. */
const VERSION_CODE = 3;
const VERSION_NAME = '1.2';

const ROOT = path.join(__dirname, '..', 'android');
const MAIN_ACTIVITY = findMainActivity(ROOT);
const BUILD_GRADLE = path.join(ROOT, 'app', 'build.gradle');
const VARIABLES_GRADLE = path.join(ROOT, 'variables.gradle');
const STYLES_XML = path.join(ROOT, 'app', 'src', 'main', 'res', 'values', 'styles.xml');
const GRADLE_PROPERTIES = path.join(ROOT, 'gradle.properties');

function findMainActivity(root) {
  const base = path.join(root, 'app', 'src', 'main', 'java');
  if (!fs.existsSync(base)) return null;
  const stack = [base];
  while (stack.length) {
    const dir = stack.pop();
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
      const p = path.join(dir, entry.name);
      if (entry.isDirectory()) stack.push(p);
      else if (entry.name === 'MainActivity.java') return p;
    }
  }
  return null;
}

function patchMainActivity() {
  if (!MAIN_ACTIVITY || !fs.existsSync(MAIN_ACTIVITY)) {
    console.log('MainActivity.java bulunamadı — atlandı (önce `npx cap add android` çalıştır).');
    return;
  }
  const src = fs.readFileSync(MAIN_ACTIVITY, 'utf8');
  if (src.includes('CHELP_BACK_BUTTON_PATCH')) {
    console.log('MainActivity.java zaten yamalı (geri tuşu + biyometrik).');
    return;
  }
  const pkgMatch = src.match(/^package\s+([\w.]+);/m);
  const pkg = pkgMatch ? pkgMatch[1] : 'com.acerasoft.chathelp';
  const out = `package ${pkg};

/* CHELP_BACK_BUTTON_PATCH — donanım geri tuşu: WebView geçmişinde gezinir,
   kökte çift basışla çıkış (premium Android davranışı). */
import android.os.Bundle;
import android.webkit.WebView;
import android.widget.Toast;
import androidx.biometric.BiometricManager;
import androidx.biometric.BiometricPrompt;
import androidx.core.content.ContextCompat;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    /* CHELP_BIOMETRIC_PATCH — Face/Fingerprint kilidi (AndroidX Biometric, stabil API).
       Varsayılan KAPALI: burada true yapıp yeniden derleyerek cihazında test edebilirsin.
       "Fail-open" tasarım: hata/iptal/biyometri-yok durumlarında HER ZAMAN içerik açılır —
       kullanıcı asla kilitli kalmaz. */
    private static final boolean BIOMETRIC_LOCK_ENABLED = false;
    private boolean unlocked = false;
    private long lastBackPressAt = 0;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
    }

    @Override
    public void onResume() {
        super.onResume();
        if (BIOMETRIC_LOCK_ENABLED && !unlocked) {
            showBiometricPrompt();
        }
    }

    private void showBiometricPrompt() {
        try {
            BiometricManager bm = BiometricManager.from(this);
            int can = bm.canAuthenticate(BiometricManager.Authenticators.BIOMETRIC_WEAK);
            if (can != BiometricManager.BIOMETRIC_SUCCESS) {
                unlocked = true; // biyometri yok/kurulu değil -> kilitlemeden aç
                return;
            }
            BiometricPrompt prompt = new BiometricPrompt(this, ContextCompat.getMainExecutor(this),
                new BiometricPrompt.AuthenticationCallback() {
                    @Override
                    public void onAuthenticationSucceeded(BiometricPrompt.AuthenticationResult result) {
                        unlocked = true;
                    }
                    @Override
                    public void onAuthenticationError(int errorCode, CharSequence errString) {
                        unlocked = true; // hata/iptal -> güvenli taraf: içerik yine de açılır
                    }
                });
            BiometricPrompt.PromptInfo info = new BiometricPrompt.PromptInfo.Builder()
                .setTitle("ChatHelp")
                .setSubtitle("Entsperren, um fortzufahren")
                .setNegativeButtonText("Überspringen")
                .build();
            prompt.authenticate(info);
        } catch (Exception e) {
            unlocked = true; // beklenmeyen hata -> güvenli taraf: içerik açılır
        }
    }

    @Override
    public void onBackPressed() {
        WebView webView = (getBridge() != null) ? getBridge().getWebView() : null;
        if (webView != null && webView.canGoBack()) {
            webView.goBack();
            return;
        }
        long now = System.currentTimeMillis();
        if (now - lastBackPressAt < 2000) {
            super.onBackPressed();
        } else {
            lastBackPressAt = now;
            Toast.makeText(this, "Nochmal drücken zum Beenden", Toast.LENGTH_SHORT).show();
        }
    }
}
`;
  fs.writeFileSync(MAIN_ACTIVITY, out);
  console.log('✓ MainActivity.java yamalandı (geri tuşu + çift-tık çıkış + biyometrik kilit altyapısı [kapalı]).');
}

function patchBuildGradle() {
  if (!fs.existsSync(BUILD_GRADLE)) {
    console.log('app/build.gradle bulunamadı — atlandı.');
    return;
  }
  let src = fs.readFileSync(BUILD_GRADLE, 'utf8');
  if (src.includes('CHELP_SIGNING_PATCH')) {
    console.log('build.gradle zaten yamalı (signing).');
    return;
  }

  // 1) signingConfigs bloğunu "buildTypes {" öncesine ekle (esnek boşluk eşleşmesi)
  const buildTypesAnchor = /(\n[ \t]*buildTypes[ \t]*\{)/;
  if (!buildTypesAnchor.test(src)) {
    console.log('✗ build.gradle: "buildTypes {" bulunamadı — signing yaması atlandı (build bozulmasın diye).');
    return;
  }
  const signingBlock = `
    /* CHELP_SIGNING_PATCH — key.properties varsa release imzalanır; yoksa unsigned kalır (bozulmaz). */
    def chelpKeyPropsFile = rootProject.file("key.properties")
    def chelpHasSigning = chelpKeyPropsFile.exists()
    def chelpKeyProps = new Properties()
    if (chelpHasSigning) {
        chelpKeyProps.load(new FileInputStream(chelpKeyPropsFile))
    }
    signingConfigs {
        release {
            if (chelpHasSigning) {
                storeFile file(chelpKeyProps['storeFile'])
                storePassword chelpKeyProps['storePassword']
                keyAlias chelpKeyProps['keyAlias']
                keyPassword chelpKeyProps['keyPassword']
            }
        }
    }
`;
  src = src.replace(buildTypesAnchor, signingBlock + '$1');

  // 2) release { ... } içine signingConfig satırını ekle (proguardFiles satırından sonra)
  const proguardAnchor = /(proguardFiles[^\n]*\n)/;
  if (!proguardAnchor.test(src)) {
    console.log('✗ build.gradle: proguardFiles satırı bulunamadı — signingConfig atanamadı (signingConfigs bloğu yine de eklendi).');
  } else {
    src = src.replace(proguardAnchor, `$1            if (chelpHasSigning) {\n                signingConfig signingConfigs.release\n            }\n`);
  }

  // 3) androidx.biometric bağımlılığı (biyometrik kilit altyapısı için — MainActivity import'ları buna ihtiyaç duyuyor)
  const depsAnchor = /(implementation project\(':capacitor-android'\)\n)/;
  if (depsAnchor.test(src)) {
    src = src.replace(depsAnchor, `$1    implementation "androidx.biometric:biometric:1.1.0" /* CHELP_BIOMETRIC_DEP */\n`);
  } else {
    console.log('✗ build.gradle: dependencies anchor bulunamadı — biometric bağımlılığı eklenemedi.');
  }

  // 4) Sabit, paylaşılan debug imzası (mobile/ci/debug-shared.keystore) — HER build (CI dahil) aynı
  //    imzayı kullansın diye. Bunsuz her CI çalışması rastgele bir debug anahtarı üretir ve telefonda
  //    "üzerine kur" (güncelle) işlemi "App not installed" hatasıyla başarısız olur.
  //    Gradle'ın "android {}" bloğu dosyada birden fazla kez tanımlanabilir (birleşir) -> ayrı, güvenli ek.
  const debugSigningAppend = `

/* CHELP_DEBUG_SIGNING_PATCH — tüm debug build'ler sabit paylaşılan anahtarla imzalanır (güncelleme uyumu) */
android {
    signingConfigs {
        debug {
            def chelpDebugKs = rootProject.file("../ci/debug-shared.keystore")
            if (chelpDebugKs.exists()) {
                storeFile chelpDebugKs
                storePassword "android"
                keyAlias "androiddebugkey"
                keyPassword "android"
            }
        }
    }
    buildTypes {
        debug {
            signingConfig signingConfigs.debug
        }
    }
}
`;
  src = src + debugSigningAppend;

  fs.writeFileSync(BUILD_GRADLE, src);
  console.log('✓ build.gradle yamalandı (key.properties varsa release otomatik imzalanır; androidx.biometric eklendi; debug build\'ler sabit paylaşılan anahtarla imzalanır).');
}

function patchVariablesGradle() {
  if (!fs.existsSync(VARIABLES_GRADLE)) {
    console.log('variables.gradle bulunamadı — atlandı (önce `npx cap add android` çalıştır).');
    return;
  }
  let src = fs.readFileSync(VARIABLES_GRADLE, 'utf8');
  if (src.includes('CHELP_TARGET_SDK_PATCH')) {
    console.log('variables.gradle zaten yamalı (targetSdk 35).');
    return;
  }
  const before = src;
  src = src.replace(/compileSdkVersion\s*=\s*3[0-4]\b/, 'compileSdkVersion = 35');
  src = src.replace(/targetSdkVersion\s*=\s*3[0-4]\b/, 'targetSdkVersion = 35');
  if (src === before) {
    console.log('variables.gradle: compileSdk/targetSdk zaten 35+ görünüyor — dokunulmadı.');
    return;
  }
  src = '/* CHELP_TARGET_SDK_PATCH — Google Play zorunluluğu: yeni uygulama/güncellemeler API 35 (Android 15) hedeflemeli (Ağu 2025+). */\n' + src;
  fs.writeFileSync(VARIABLES_GRADLE, src);
  console.log('✓ variables.gradle yamalandı (compileSdk/targetSdk 34 -> 35, Google Play zorunluluğu).');
}

function patchStylesXml() {
  if (!fs.existsSync(STYLES_XML)) {
    console.log('styles.xml bulunamadı — atlandı.');
    return;
  }
  let src = fs.readFileSync(STYLES_XML, 'utf8');
  if (src.includes('CHELP_EDGE_PATCH')) {
    console.log('styles.xml zaten yamalı (edge-to-edge opt-out).');
    return;
  }
  // targetSdk 35'te Android 15 edge-to-edge'i zorlar; WebView kabuk için opt-out
  // en güvenli yol (StatusBar overlaysWebView:false davranışı korunur).
  // Hem ana temaya hem launch temasına eklenir; API<35 cihazlar özniteliği yok sayar.
  const item = '\n        <item name="android:windowOptOutEdgeToEdgeEnforcement">true</item><!-- CHELP_EDGE_PATCH -->';
  const before = src;
  src = src.replace(/(<style name="AppTheme\.NoActionBar"[^>]*>)/, '$1' + item);
  src = src.replace(/(<style name="AppTheme\.NoActionBarLaunch"[^>]*>)/, '$1' + item);
  if (src === before) {
    console.log('✗ styles.xml: AppTheme.NoActionBar bulunamadı — edge-to-edge yaması atlandı.');
    return;
  }
  fs.writeFileSync(STYLES_XML, src);
  console.log('✓ styles.xml yamalandı (Android 15 edge-to-edge opt-out — durum çubuğu düzeni korunur).');
}

function patchGradleProperties() {
  if (!fs.existsSync(GRADLE_PROPERTIES)) {
    console.log('gradle.properties bulunamadı — atlandı.');
    return;
  }
  let src = fs.readFileSync(GRADLE_PROPERTIES, 'utf8');
  if (src.includes('CHELP_SUPPRESS_SDK_WARN')) {
    console.log('gradle.properties zaten yamalı (compileSdk 35 uyarısı susturuldu).');
    return;
  }
  src += '\n# CHELP_SUPPRESS_SDK_WARN — Capacitor 6 şablonundaki AGP, compileSdk 35 ile resmi test edilmedi uyarısı verir; build sorunsuz.\nandroid.suppressUnsupportedCompileSdk=35\n';
  fs.writeFileSync(GRADLE_PROPERTIES, src);
  console.log('✓ gradle.properties yamalandı (suppressUnsupportedCompileSdk=35).');
}

function patchVersion() {
  if (!fs.existsSync(BUILD_GRADLE)) {
    console.log('app/build.gradle bulunamadı — sürüm yaması atlandı.');
    return;
  }
  let src = fs.readFileSync(BUILD_GRADLE, 'utf8');
  const before = src;
  src = src.replace(/versionCode\s+\d+/, 'versionCode ' + VERSION_CODE);
  src = src.replace(/versionName\s+"[^"]*"/, 'versionName "' + VERSION_NAME + '"');
  if (src !== before) {
    fs.writeFileSync(BUILD_GRADLE, src);
    console.log(`✓ sürüm yamalandı: versionCode ${VERSION_CODE} · versionName ${VERSION_NAME}`);
  } else {
    console.log('✗ build.gradle: versionCode/versionName bulunamadı.');
  }
}

patchMainActivity();
patchBuildGradle();
patchVersion();
patchVariablesGradle();
patchStylesXml();
patchGradleProperties();
console.log('\nBitti. Bu script her zaman güvenle tekrar çalıştırılabilir (idempotent).');
