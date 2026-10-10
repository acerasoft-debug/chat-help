#!/bin/bash
# SARVESTO — iOS yerel ayarlar (macOS'ta, `npx cap add ios` SONRASI; tekrar çalıştırılabilir)
#   • Ana ekranda görünen ad: SARVESTO
#   • iPhone yalnızca dikey (mağaza dikey tasarlandı); iPad dört yön
#   • Durum çubuğu: siyah şerit (duyuru şeridiyle tek parça), beyaz saat
#   • App Store şifreleme beyanı: standart HTTPS dışında şifreleme yok
#     (yoksa her yüklemede "Export Compliance" sorusu çıkar)
#   • Sürüm: MARKETING_VERSION / CURRENT_PROJECT_VERSION ortamdan
set -e
P="$(dirname "$0")/../ios/App/App/Info.plist"
[ -f "$P" ] || { echo "Info.plist yok — önce: npx cap add ios"; exit 1; }
B=/usr/libexec/PlistBuddy
set_kv() { $B -c "Set :$1 $3" "$P" 2>/dev/null || $B -c "Add :$1 $2 $3" "$P"; }

set_kv CFBundleDisplayName string "SARVESTO"
set_kv ITSAppUsesNonExemptEncryption bool false
set_kv UIStatusBarStyle string UIStatusBarStyleLightContent
set_kv UIRequiresFullScreen bool false

$B -c "Delete :UISupportedInterfaceOrientations" "$P" 2>/dev/null || true
$B -c "Add :UISupportedInterfaceOrientations array" "$P"
$B -c "Add :UISupportedInterfaceOrientations:0 string UIInterfaceOrientationPortrait" "$P"

$B -c "Delete :UISupportedInterfaceOrientations~ipad" "$P" 2>/dev/null || true
$B -c "Add :UISupportedInterfaceOrientations~ipad array" "$P"
i=0
for o in Portrait PortraitUpsideDown LandscapeLeft LandscapeRight; do
  $B -c "Add :UISupportedInterfaceOrientations~ipad:$i string UIInterfaceOrientation$o" "$P"; i=$((i+1))
done

echo "✓ Info.plist: ad, yönler, durum çubuğu, şifreleme beyanı"
