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
 * Safe to re-run: every edit is guarded by a marker check.
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..', 'android');
const MAIN_ACTIVITY = findMainActivity(ROOT);
const BUILD_GRADLE = path.join(ROOT, 'app', 'build.gradle');

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
    console.log('MainActivity.java zaten yamalı (geri tuşu).');
    return;
  }
  const pkgMatch = src.match(/^package\s+([\w.]+);/m);
  const pkg = pkgMatch ? pkgMatch[1] : 'com.chathelp.app';
  const out = `package ${pkg};

/* CHELP_BACK_BUTTON_PATCH — donanım geri tuşu: WebView geçmişinde gezinir,
   kökte çift basışla çıkış (premium Android davranışı). */
import android.webkit.WebView;
import android.widget.Toast;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    private long lastBackPressAt = 0;

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
  console.log('✓ MainActivity.java yamalandı (geri tuşu + çift-tık çıkış).');
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

  fs.writeFileSync(BUILD_GRADLE, src);
  console.log('✓ build.gradle yamalandı (key.properties varsa release otomatik imzalanır).');
}

patchMainActivity();
patchBuildGradle();
console.log('\nBitti. Bu script her zaman güvenle tekrar çalıştırılabilir (idempotent).');
