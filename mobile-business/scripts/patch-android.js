#!/usr/bin/env node
/**
 * ChatHelp Business – Android anpassen (idempotent, läuft nach `cap add/sync android`)
 * ===================================================================================
 *  1. MainActivity: Benachrichtigungskanal „chathelp_alerts“ (Wichtigkeit HOCH, Ton, Vibration, Licht in Gold) beim Start anlegen –
 *     FCM-Meldungen im Hintergrund landen so sofort im richtigen Kanal (push-lib.php sendet channel_id=chathelp_alerts).
 *  2. AndroidManifest: POST_NOTIFICATIONS (Android 13+), VIBRATE, FCM-Standards (Symbol, Farbe, Kanal), kein Klartext-HTTP.
 *  3. res/drawable/ic_stat_chathelp.xml: weißes Benachrichtigungs-Symbol (Emblem) + Farbe chathelp_gold.
 *  4. app/build.gradle: Release-Signatur aus android/key.properties (nur wenn vorhanden), versionCode/versionName aus
 *     CH_VERSION_CODE / CH_VERSION_NAME (CI: Laufnummer).
 * google-services.json (Firebase) legt die CI bzw. der Entwickler nach android/app/ – Capacitors build.gradle bindet es dann automatisch ein.
 */
'use strict';
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..', 'android');
if (!fs.existsSync(ROOT)) { console.log('ℹ android/ fehlt – zuerst `npm run setup:android`.'); process.exit(0); }
const APP = path.join(ROOT, 'app');
const MAIN = path.join(APP, 'src', 'main');
const done = [];
const read = (f) => fs.readFileSync(f, 'utf8');
const write = (f, s) => { fs.mkdirSync(path.dirname(f), { recursive: true }); fs.writeFileSync(f, s); };

function findMainActivity(dir) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, e.name);
    if (e.isDirectory()) { const r = findMainActivity(p); if (r) return r; } else if (e.name === 'MainActivity.java') return p;
  }
  return null;
}

/* ---------- 1. MainActivity ---------- */
const ma = findMainActivity(path.join(MAIN, 'java'));
if (ma) {
  const src = read(ma);
  if (!src.includes('CHB_PATCH_MAIN')) {
    const pkg = (/^package\s+([\w.]+);/m.exec(src) || [])[1] || 'com.chathelp.business';
    write(ma, `package ${pkg};

/* CHB_PATCH_MAIN – ChatHelp Business: Benachrichtigungskanal beim Start anlegen (patch-android.js) */
import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.graphics.Color;
import android.media.AudioAttributes;
import android.media.RingtoneManager;
import android.os.Build;
import android.os.Bundle;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    public static final String CHANNEL_ID = "chathelp_alerts";

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        createNotificationChannel();
    }

    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return;
        NotificationManager nm = getSystemService(NotificationManager.class);
        if (nm == null || nm.getNotificationChannel(CHANNEL_ID) != null) return;
        NotificationChannel ch = new NotificationChannel(CHANNEL_ID, "Aufträge & Hinweise", NotificationManager.IMPORTANCE_HIGH);
        ch.setDescription("Neue Bestellungen, Fahrten, Termine, Rückrufe und wichtige Hinweise");
        ch.enableVibration(true);
        ch.setVibrationPattern(new long[] { 0, 260, 120, 260, 120, 520 });
        ch.enableLights(true);
        ch.setLightColor(Color.parseColor("#B8862B"));
        ch.setLockscreenVisibility(Notification.VISIBILITY_PUBLIC);
        ch.setShowBadge(true);
        ch.setSound(RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION),
            new AudioAttributes.Builder().setUsage(AudioAttributes.USAGE_NOTIFICATION).setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION).build());
        nm.createNotificationChannel(ch);
    }
}
`);
    done.push('MainActivity: Kanal chathelp_alerts');
  }
} else console.warn('⚠ MainActivity.java nicht gefunden');

/* ---------- 2. AndroidManifest ---------- */
const mf = path.join(MAIN, 'AndroidManifest.xml');
if (fs.existsSync(mf)) {
  let s = read(mf); const before = s;
  for (const perm of ['android.permission.POST_NOTIFICATIONS', 'android.permission.VIBRATE', 'android.permission.WAKE_LOCK']) {
    if (!s.includes('"' + perm + '"')) s = s.replace(/<\/manifest>\s*$/, '    <uses-permission android:name="' + perm + '" />\n</manifest>\n');
  }
  if (!s.includes('CHB_FCM_DEFAULTS')) {
    s = s.replace(/(<application\b[^>]*>)/, `$1
        <!-- CHB_FCM_DEFAULTS: Symbol, Farbe und Kanal für Meldungen, die FCM im Hintergrund selbst anzeigt -->
        <meta-data android:name="com.google.firebase.messaging.default_notification_icon" android:resource="@drawable/ic_stat_chathelp" />
        <meta-data android:name="com.google.firebase.messaging.default_notification_color" android:resource="@color/chathelp_gold" />
        <meta-data android:name="com.google.firebase.messaging.default_notification_channel_id" android:value="chathelp_alerts" />`);
  }
  if (!/usesCleartextTraffic/.test(s)) s = s.replace(/<application\b/, '<application\n        android:usesCleartextTraffic="false"');
  if (s !== before) { write(mf, s); done.push('AndroidManifest: Berechtigungen + FCM-Standards'); }
}

/* ---------- 3. Ressourcen ---------- */
const icon = path.join(MAIN, 'res', 'drawable', 'ic_stat_chathelp.xml');
if (!fs.existsSync(icon)) {
  write(icon, `<?xml version="1.0" encoding="utf-8"?>
<!-- ChatHelp-Emblem als Benachrichtigungs-Symbol (Android verlangt einfarbig weiß auf transparent) -->
<vector xmlns:android="http://schemas.android.com/apk/res/android"
    android:width="24dp" android:height="24dp" android:viewportWidth="24" android:viewportHeight="24">
    <path android:fillColor="#00000000" android:strokeColor="#FFFFFFFF" android:strokeWidth="3"
        android:strokeLineCap="round" android:pathData="M18.5,7.5A8,8 0,1 0,19 15" />
    <path android:fillColor="#FFFFFFFF" android:pathData="M12,9.6a2.4,2.4 0,1 1,0 4.8a2.4,2.4 0,1 1,0 -4.8z" />
</vector>
`);
  done.push('drawable/ic_stat_chathelp.xml');
}
const colors = path.join(MAIN, 'res', 'values', 'chathelp_colors.xml');
if (!fs.existsSync(colors)) {
  write(colors, `<?xml version="1.0" encoding="utf-8"?>
<resources>
    <color name="chathelp_gold">#B8862B</color>
    <color name="chathelp_ink">#0F1222</color>
</resources>
`);
  done.push('values/chathelp_colors.xml');
}

/* ---------- 4. app/build.gradle ---------- */
const bg = path.join(APP, 'build.gradle');
if (fs.existsSync(bg)) {
  let s = read(bg); const before = s;
  if (!s.includes('CHB_SIGNING')) {
    s = s.replace(/(apply plugin: 'com\.android\.application'\s*\n)/, `$1
// CHB_SIGNING – Release-Signatur nur, wenn android/key.properties existiert (CI schreibt sie aus GitHub-Secrets)
def chKeyPropsFile = rootProject.file('key.properties')
def chKeyProps = new Properties()
if (chKeyPropsFile.exists()) { chKeyPropsFile.withInputStream { chKeyProps.load(it) } }
def chVersionCode = (System.getenv('CH_VERSION_CODE') ?: '1') as Integer
def chVersionName = System.getenv('CH_VERSION_NAME') ?: '1.1.0'
`);
    s = s.replace(/versionCode\s+\d+/, 'versionCode chVersionCode');
    s = s.replace(/versionName\s+"[^"]*"/, 'versionName chVersionName');
    s = s.replace(/(\n\s*buildTypes\s*\{)/, `
    signingConfigs {
        release {
            if (chKeyPropsFile.exists()) {
                storeFile file(chKeyProps['storeFile'])
                storePassword chKeyProps['storePassword']
                keyAlias chKeyProps['keyAlias']
                keyPassword chKeyProps['keyPassword']
            }
        }
    }$1`);
    s = s.replace(/(release\s*\{\s*\n)(\s*)(minifyEnabled)/, `$1$2if (chKeyPropsFile.exists()) { signingConfig signingConfigs.release }\n$2$3`);
  }
  if (s !== before) { write(bg, s); done.push('app/build.gradle: Signatur + Versionsnummern'); }
  const chk = read(bg);
  ['CHB_SIGNING', 'versionCode chVersionCode', 'versionName chVersionName', 'signingConfig signingConfigs.release'].forEach((m) => { if (!chk.includes(m)) { console.error('✗ build.gradle-Anpassung fehlt: ' + m); process.exitCode = 1; } });
}

/* google-services.json-Hinweis */
if (!fs.existsSync(path.join(APP, 'google-services.json'))) console.log('ℹ android/app/google-services.json fehlt → App läuft, native Push-Benachrichtigungen aber erst mit Firebase-Konfiguration (siehe README).');
console.log(done.length ? '✓ Android angepasst: ' + done.join(' · ') : '✓ Android war bereits angepasst');
