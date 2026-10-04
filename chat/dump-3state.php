<?php
/**
 * ChatHelp — dump-3state — 3 sikayetin canli durumu (SADECE OKUR):
 *  [A] Verlauf altinda son sohbetler (CH_VERLAUF_RECENT3) var mi + #nav-verlauf?
 *  [B] Apple App butonu Google Play altinda mi (appstore/iph marker + play link sayisi)?
 *  [C] Meine Themen panelinin id/class yapisi (premium yapabilmem icin).
 * KULLANIM: pull2.php?key=...&files=dump-3state.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false) exit("index.php okunamadi\n");
echo "== dump-3state (index.php ".strlen($src)." B) ==\n\n";

function has($src,$s){ return strpos($src,$s)!==false ? "VAR" : "yok"; }
function cnt($src,$s){ return substr_count($src,$s); }

echo "[A] VERLAUF SON SOHBETLER\n";
echo "  CH_VERLAUF_RECENT3 marker: ".has($src,'CH_VERLAUF_RECENT3')."\n";
echo "  ch-verlauf-recent-js blok: ".has($src,'ch-verlauf-recent-js')."\n";
echo "  id=nav-verlauf: ".has($src,'nav-verlauf')." (adet: ".cnt($src,'nav-verlauf').")\n";
echo "  ch_chat_sessions gecis: ".cnt($src,'ch_chat_sessions')."\n";
echo "  chSessKey gecis: ".cnt($src,'chSessKey')."\n";
foreach(['ch-verlauf-recent-css','ch-verlauf-recent-css2'] as $c) echo "  $c: ".has($src,$c)."\n";

echo "\n[B] APPLE APP / GOOGLE PLAY\n";
foreach(['CH_APPSTORE_IOS','CH_APPSTORE_IOS2','CH_QR_IOS','ch-iph-btn','ch-qr-btn','ch-iph-clone'] as $m) echo "  $m: ".has($src,$m)."\n";
echo "  play.google.com gecis: ".cnt($src,'play.google.com')."\n";
echo "  'Google Play' metni: ".cnt($src,'Google Play')."\n";
echo "  apps.apple.com gecis: ".cnt($src,'apps.apple.com')."\n";
echo "  'App Store'/'iPhone' metni: ".cnt($src,'App Store')."/".cnt($src,'iPhone')."\n";

echo "\n[C] MEINE THEMEN PANEL\n";
// olasi id/class'lari yakala
if(preg_match_all('/id=["\']([a-z0-9-]*(?:themen|thema|topic)[a-z0-9-]*)["\']/i',$src,$m)) echo "  id'ler: ".implode(', ',array_unique($m[1]))."\n"; else echo "  themen id bulunamadi\n";
if(preg_match_all('/class=["\']([^"\']*(?:thm-|themen|topic)[^"\']*)["\']/i',$src,$m2)) echo "  class ornekleri: ".implode(' | ',array_slice(array_unique($m2[1]),0,12))."\n"; else echo "  themen class bulunamadi\n";
echo "  'Meine Themen'/'Themen' metni: ".cnt($src,'Meine Themen')."/".cnt($src,'Themen')."\n";
// panel scroll host
if(preg_match('/id=["\'](pnl-themen[a-z0-9-]*)["\']/i',$src,$mp)) echo "  panel id: ".$mp[1]."\n";

echo "\n== stabilize markerlari (regresyon suphesi) ==\n";
foreach(['CH_STABILIZE','CH_STABILIZE2','CH_FLICKER_KILL','CH_FLICKDBG_REMOVE'] as $m) echo "  $m: ".has($src,$m)."\n";
echo "\n== BITTI ==\n";
