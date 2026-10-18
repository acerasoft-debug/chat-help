#!/usr/bin/env node
/**
 * ChatHelp Business – iOS anpassen (idempotent, läuft nach `cap add ios`)
 * =======================================================================
 *  1. Podfile: FirebaseMessaging – der Server (push-lib.php) sendet über FCM HTTP v1 und braucht deshalb ein FCM-Token statt des APNs-Tokens.
 *  2. AppDelegate.swift: Firebase starten (Werte aus Info.plist → ChatHelpFirebase), APNs-Token an Firebase geben und das FCM-Token an
 *     Capacitor melden (Event „registration“ → app-bridge.js → /v1/push/subscribe {type:'fcm'}).
 *  3. SceneDelegate.swift: Wischgeste „zurück“ im WebView (z. B. aus dem Studio zurück in die App).
 *  4. Info.plist: Hintergrundmodus remote-notification, FirebaseAppDelegateProxyEnabled=NO, keine Export-Verschlüsselung, arm64,
 *     Firebase-Werte aus GoogleService-Info.plist (Pfad: CH_GOOGLE_SERVICE_INFO oder ios/App/App/GoogleService-Info.plist).
 *  5. App.entitlements (aps-environment = CH_APS_ENV, Standard production) + Signatur/Versionsnummern im Xcode-Projekt:
 *     IOS_TEAM_ID, IOS_PROFILE_NAME (manuelle Signatur, nur Ziel „App“), CH_VERSION_NAME, CH_VERSION_CODE.
 */
'use strict';
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..', 'ios', 'App');
if (!fs.existsSync(ROOT)) { console.log('ℹ ios/ fehlt – zuerst `npm run setup:ios`.'); process.exit(0); }
const APPDIR = path.join(ROOT, 'App');
const done = [];
const read = (f) => fs.readFileSync(f, 'utf8');
const write = (f, s) => fs.writeFileSync(f, s);
let plist = null; try { plist = require('plist'); } catch (e) { /* kommt mit @capacitor/cli */ }

/* ---------- 1. Podfile ---------- */
const pod = path.join(ROOT, 'Podfile');
if (fs.existsSync(pod)) {
  let s = read(pod);
  if (!s.includes("pod 'FirebaseMessaging'")) {
    s = s.replace(/(target 'App' do\s*\n\s*capacitor_pods\s*\n)/, "$1  # CHB: FCM-Token für native Push-Benachrichtigungen (push-lib.php → FCM HTTP v1)\n  pod 'FirebaseMessaging'\n");
    if (!s.includes("pod 'FirebaseMessaging'")) { console.error('✗ Podfile: target App nicht gefunden'); process.exitCode = 1; }
    else { write(pod, s); done.push('Podfile: FirebaseMessaging'); }
  }
}

/* ---------- 2. AppDelegate.swift ---------- */
const ad = path.join(APPDIR, 'AppDelegate.swift');
if (fs.existsSync(ad)) {
  let s = read(ad);
  if (!s.includes('CHB_PUSH')) {
    s = s.replace(/import Capacitor\n/, `import Capacitor
#if canImport(FirebaseCore)
import FirebaseCore
import FirebaseMessaging
#endif
`);
    s = s.replace(/(didFinishLaunchingWithOptions[^{]*\{\s*\n)/, `$1        // CHB_PUSH – Firebase für FCM-Token starten (ohne Konfiguration läuft die App normal weiter)
        ChatHelpPush.configure()
`);
    s = s.replace(/\n\}\s*$/, `

    // CHB_PUSH – APNs-Token → Firebase → FCM-Token an Capacitor (PushNotifications „registration“)
    func application(_ application: UIApplication, didRegisterForRemoteNotificationsWithDeviceToken deviceToken: Data) {
        ChatHelpPush.didRegister(deviceToken)
    }

    func application(_ application: UIApplication, didFailToRegisterForRemoteNotificationsWithError error: Error) {
        NotificationCenter.default.post(name: .capacitorDidFailToRegisterForRemoteNotifications, object: error)
    }
}

/// ChatHelp Business: Firebase-Anbindung für native Push-Benachrichtigungen (patch-ios.js).
enum ChatHelpPush {
    static var ready = false

    static func configure() {
        #if canImport(FirebaseCore)
        if FirebaseApp.app() != nil { ready = true; return }
        if let d = Bundle.main.object(forInfoDictionaryKey: "ChatHelpFirebase") as? [String: String],
           let appId = d["GOOGLE_APP_ID"], let sender = d["GCM_SENDER_ID"], !appId.isEmpty {
            let o = FirebaseOptions(googleAppID: appId, gcmSenderID: sender)
            o.apiKey = d["API_KEY"]
            o.projectID = d["PROJECT_ID"]
            o.storageBucket = d["STORAGE_BUCKET"]
            o.bundleID = d["BUNDLE_ID"] ?? (Bundle.main.bundleIdentifier ?? "")
            FirebaseApp.configure(options: o)
            ready = true
        } else if Bundle.main.path(forResource: "GoogleService-Info", ofType: "plist") != nil {
            FirebaseApp.configure()
            ready = true
        }
        #endif
    }

    static func didRegister(_ deviceToken: Data) {
        #if canImport(FirebaseCore)
        if ready {
            Messaging.messaging().apnsToken = deviceToken
            Messaging.messaging().token { token, error in
                if let token = token, !token.isEmpty {
                    NotificationCenter.default.post(name: .capacitorDidRegisterForRemoteNotifications, object: token)
                } else {
                    NotificationCenter.default.post(name: .capacitorDidFailToRegisterForRemoteNotifications,
                        object: error ?? NSError(domain: "ChatHelp", code: 1, userInfo: [NSLocalizedDescriptionKey: "Kein FCM-Token erhalten"]))
                }
            }
            return
        }
        #endif
        NotificationCenter.default.post(name: .capacitorDidFailToRegisterForRemoteNotifications,
            object: NSError(domain: "ChatHelp", code: 2, userInfo: [NSLocalizedDescriptionKey: "Firebase ist in dieser App-Version nicht eingerichtet (GoogleService-Info.plist fehlt)."]))
    }
}
`);
    if (!s.includes('ChatHelpPush.configure()') || !s.includes('didRegisterForRemoteNotificationsWithDeviceToken')) { console.error('✗ AppDelegate.swift: Anpassung fehlgeschlagen'); process.exitCode = 1; }
    else { write(ad, s); done.push('AppDelegate: Firebase + FCM-Token'); }
  }
}

/* ---------- 3. SceneDelegate.swift: Wischgeste zurück ---------- */
const sd = path.join(APPDIR, 'SceneDelegate.swift');
if (fs.existsSync(sd)) {
  let s = read(sd);
  if (!s.includes('CHB_SWIPE_BACK') && s.includes('CAPBridgeViewController()')) {
    s = s.replace('CAPBridgeViewController()', 'ChatHelpBridgeViewController()') + `
/* CHB_SWIPE_BACK – Wischgeste „zurück“ im WebView (z. B. aus dem Studio zurück in die App; iOS hat keine Zurück-Taste) */
class ChatHelpBridgeViewController: CAPBridgeViewController {
    override func capacitorDidLoad() {
        super.capacitorDidLoad()
        webView?.allowsBackForwardNavigationGestures = true
    }
}
`;
    write(sd, s); done.push('SceneDelegate: Wischgeste zurück');
  }
}

/* ---------- 4. Info.plist ---------- */
const ip = path.join(APPDIR, 'Info.plist');
if (fs.existsSync(ip) && plist) {
  const d = plist.parse(read(ip)); const before = JSON.stringify(d);
  const modes = new Set(d.UIBackgroundModes || []); modes.add('remote-notification'); d.UIBackgroundModes = [...modes];
  d.FirebaseAppDelegateProxyEnabled = false;
  d.ITSAppUsesNonExemptEncryption = false;
  d.UIRequiredDeviceCapabilities = ['arm64'];
  d.CFBundleDisplayName = 'ChatHelp';
  d.CFBundleDevelopmentRegion = 'de';
  d.UISupportedInterfaceOrientations = ['UIInterfaceOrientationPortrait'];
  const gsi = process.env.CH_GOOGLE_SERVICE_INFO || path.join(APPDIR, 'GoogleService-Info.plist');
  if (fs.existsSync(gsi)) {
    const g = plist.parse(read(gsi)); const fb = {};
    ['GOOGLE_APP_ID', 'GCM_SENDER_ID', 'API_KEY', 'PROJECT_ID', 'STORAGE_BUCKET', 'BUNDLE_ID'].forEach((k) => { if (typeof g[k] === 'string') fb[k] = g[k]; });
    if (fb.GOOGLE_APP_ID && fb.GCM_SENDER_ID) d.ChatHelpFirebase = fb; else console.warn('⚠ GoogleService-Info.plist ohne GOOGLE_APP_ID/GCM_SENDER_ID');
    if (fb.BUNDLE_ID && fb.BUNDLE_ID !== 'com.chathelp.business') console.warn('⚠ GoogleService-Info.plist gehört zu ' + fb.BUNDLE_ID + ' statt com.chathelp.business');
  } else console.log('ℹ GoogleService-Info.plist fehlt → App läuft, native Push-Benachrichtigungen erst mit Firebase-Konfiguration (siehe README).');
  if (JSON.stringify(d) !== before) { write(ip, plist.build(d)); done.push('Info.plist: Push-Hintergrundmodus, Firebase, arm64'); }
}

/* ---------- 5. Entitlements + Xcode-Projekt (Signatur, Version) ---------- */
const ent = path.join(APPDIR, 'App.entitlements');
const aps = process.env.CH_APS_ENV === 'development' ? 'development' : 'production';
const entXml = `<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
	<key>aps-environment</key>
	<string>${aps}</string>
</dict>
</plist>
`;
if (!fs.existsSync(ent) || read(ent) !== entXml) { write(ent, entXml); done.push('App.entitlements (aps-environment=' + aps + ')'); }

const pbx = path.join(ROOT, 'App.xcodeproj', 'project.pbxproj');
if (fs.existsSync(pbx)) {
  let s = read(pbx); const before = s;
  const team = process.env.IOS_TEAM_ID || '', prof = process.env.IOS_PROFILE_NAME || '';
  const ver = process.env.CH_VERSION_NAME || '', build = process.env.CH_VERSION_CODE || '';
  /* nur die Build-Konfigurationen des Ziels „App“ (erkennbar an PRODUCT_BUNDLE_IDENTIFIER) */
  s = s.replace(/(buildSettings = \{)([^}]*?PRODUCT_BUNDLE_IDENTIFIER = [^;]+;[^}]*?)(\};)/g, (all, a, body, c) => {
    const set = (k, v) => { const re = new RegExp('\\n(\\s*)' + k.replace(/[[\]*]/g, '\\$&') + ' = [^;]*;'); if (re.test(body)) body = body.replace(re, '\n$1' + k + ' = ' + v + ';'); else body = body.replace(/(\n(\s*)PRODUCT_BUNDLE_IDENTIFIER = )/, '\n$2' + k + ' = ' + v + ';$1'); };
    set('CODE_SIGN_ENTITLEMENTS', 'App/App.entitlements');
    if (team) set('DEVELOPMENT_TEAM', team);
    if (team && prof) { set('CODE_SIGN_STYLE', 'Manual'); set('PROVISIONING_PROFILE_SPECIFIER', '"' + prof.replace(/"/g, '') + '"'); set('"CODE_SIGN_IDENTITY[sdk=iphoneos*]"', '"Apple Distribution"'); }
    if (/^\d+(\.\d+){0,2}$/.test(ver)) set('MARKETING_VERSION', ver);
    if (/^\d+$/.test(build)) set('CURRENT_PROJECT_VERSION', build);
    set('TARGETED_DEVICE_FAMILY', '"1,2"');
    return a + body + c;
  });
  if (s !== before) { write(pbx, s); done.push('Xcode-Projekt: Entitlements' + (team ? ', Team ' + team : '') + (team && prof ? ', Profil „' + prof + '“' : '') + (ver ? ', Version ' + ver : '') + (build ? ' (' + build + ')' : '')); }
  if (!read(pbx).includes('CODE_SIGN_ENTITLEMENTS = App/App.entitlements')) { console.error('✗ project.pbxproj: CODE_SIGN_ENTITLEMENTS nicht gesetzt'); process.exitCode = 1; }
}
console.log(done.length ? '✓ iOS angepasst: ' + done.join(' · ') : '✓ iOS war bereits angepasst');
