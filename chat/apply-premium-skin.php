<?php
/**
 * ChatHelp — PREMIUM GÖRÜNÜM (antrasit korunur, dilekçe kutuları altın uyumlu) + ayar netliği
 * ---------------------------------------------------------------------------------------------
 *  dump-design çıktısındaki BİREBİR dizgilere dayalı:
 *   1) showCatDocs (belge listesi alt-sayfası): düz gri kutular -> altın vurgulu premium
 *      kartlar (ikon rozeti, altın kenarlık, altın ok) — antrasit zemin aynen kalır
 *   2) CSS katmanı: .wcat / .ccard kategori kartları + .co ülke kutuları + .lang-opt
 *      dil butonlarına altın uyumlu premium doku (JS ile <style> enjekte, sınıf tanımlarına dokunmaz)
 *   3) Ayarlar: "Interface-Sprache" bölümüne tek satır açıklama — menü dili ile
 *      hukuk/belge ülkesinin ayrımı net (bayrak = hukuk ülkesi)
 *
 * KULLANIM: pull-updates.php?files=apply-premium-skin.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-premium-skin BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_PREMIUM_SKIN")!==false) exit("Zaten ekli (CH_PREMIUM_SKIN).\n");
file_put_contents($file.".bak-skin-".date("Ymd-His"),$src);
$rep=[];

/* ---- 1a) Belge listesi alt-sayfası: zemin (altın çerçeve + derin gölge) ---- */
$o1='background:#0d0d1f;border-radius:22px 22px 0 0;width:100%;max-width:500px';
$n1='background:linear-gradient(180deg,#12122a,#0b0b18);border:1px solid rgba(212,168,74,.18);border-bottom:none;box-shadow:0 -8px 40px rgba(0,0,0,.5);border-radius:22px 22px 0 0;width:100%;max-width:500px';
$c=substr_count($src,$o1); if($c>0) $src=str_replace($o1,$n1,$src); $rep[]=["1a alt-sayfa zemini",$c];

/* ---- 1b) Belge satırları: altın vurgulu kart ---- */
$o2='margin-bottom:8px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:14px';
$n2='margin-bottom:8px;background:linear-gradient(135deg,rgba(255,255,255,.05),rgba(212,168,74,.045));border:1px solid rgba(212,168,74,.16);border-radius:14px';
$c=substr_count($src,$o2); if($c>0) $src=str_replace($o2,$n2,$src); $rep[]=["1b belge satirlari",$c];

/* ---- 1c) İkonlar: altın rozet kutusu ---- */
$o3='<span style="font-size:22px;flex-shrink:0;width:32px;text-align:center">${d.ic}</span>';
$n3='<span style="font-size:19px;flex-shrink:0;width:38px;height:38px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,rgba(212,168,74,.14),rgba(212,168,74,.05));border:1px solid rgba(212,168,74,.22);border-radius:11px">${d.ic}</span>';
$c=substr_count($src,$o3); if($c>0) $src=str_replace($o3,$n3,$src); $rep[]=["1c ikon rozetleri",$c];

/* ---- 1d) Ok işareti: gri -> altın ---- */
$o4='stroke="#505078" stroke-width="2.5" stroke-linecap="round"><polyline points="9,18 15,12 9,6"/>';
$n4='stroke="#c9a44f" stroke-width="2.5" stroke-linecap="round"><polyline points="9,18 15,12 9,6"/>';
$c=substr_count($src,$o4); if($c>0) $src=str_replace($o4,$n4,$src); $rep[]=["1d altin ok",$c];

/* ---- 3) Ayarlar: Interface-Sprache aciklamasi ---- */
$o5='<div class="ksec-body"><div class="lang-grid" id="lg">';
$n5='<div class="ksec-body"><div style="font-size:11px;color:var(--t4);margin-bottom:9px;line-height:1.5">&#127760; Nur Men&#252;sprache &#8212; &#9878;&#65039; Recht &amp; Dokumentsprache richten sich nach dem <b style="color:var(--gold)">Land</b> (Abschnitt unten; Flagge oben = Rechtsland).</div><div class="lang-grid" id="lg">';
$c=substr_count($src,$o5); if($c>0) $src=str_replace($o5,$n5,$src); $rep[]=["3 ayar aciklamasi (dil vs hukuk)",$c];

/* ---- 2) CSS katmani (JS enjekte, anchor: getDocPrice) ---- */
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,$anchor)!==false){
$js=<<<'CHJS'

/* CH_PREMIUM_SKIN — kategori kartları/ülke kutuları/dil butonları için altın uyumlu doku */
try{(function(){
  function addSkin(){
    if(document.getElementById("ch-premium-skin")) return;
    var st=document.createElement("style"); st.id="ch-premium-skin";
    st.textContent=[
      ".wcat{background:linear-gradient(135deg,var(--s2,#131325),var(--s1,#0d0d1c))!important;border:1px solid rgba(212,168,74,.14)!important;box-shadow:0 4px 16px rgba(0,0,0,.25);transition:border-color .18s,transform .18s,box-shadow .18s}",
      ".wcat:hover{border-color:rgba(212,168,74,.42)!important;transform:translateY(-1px);box-shadow:0 8px 24px rgba(0,0,0,.35),0 0 0 1px rgba(212,168,74,.1)}",
      ".wcat-ic{display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;background:linear-gradient(135deg,rgba(212,168,74,.14),rgba(212,168,74,.04));border:1px solid rgba(212,168,74,.22);border-radius:12px;font-size:20px!important;margin-bottom:8px}",
      ".ccard{background:linear-gradient(135deg,var(--s2,#131325),var(--s1,#0d0d1c))!important;border:1px solid rgba(212,168,74,.14)!important;transition:border-color .18s,transform .18s}",
      ".ccard:hover{border-color:rgba(212,168,74,.42)!important;transform:translateY(-1px)}",
      ".ccard-ic{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;background:linear-gradient(135deg,rgba(212,168,74,.14),rgba(212,168,74,.04));border:1px solid rgba(212,168,74,.22);border-radius:11px;font-size:19px!important;margin-bottom:6px}",
      ".co{transition:border-color .15s,background .15s}",
      ".co:hover{border-color:rgba(212,168,74,.45)!important;background:rgba(212,168,74,.06)!important}",
      ".lang-opt{transition:border-color .15s,background .15s}",
      ".lang-opt:hover{border-color:rgba(212,168,74,.4)!important}",
      ".astat,.ant-item{border:1px solid rgba(212,168,74,.12)!important}"
    ].join("\n");
    document.head.appendChild(st);
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",addSkin); else addSkin();
})();}catch(e){}
CHJS;
  $src=str_replace($anchor,$anchor.$js,$src);
  $rep[]=["2 CSS premium katmani",1];
} else { $rep[]=["2 CSS katmani (anchor yok!)",0]; }

$ok=0; foreach($rep as $r){ if($r[1]>0) $ok++; }
if($src!==$start){ file_put_contents($file,$src); }
echo "Rapor:\n";
foreach($rep as $r) echo ($r[1]>0?"  OK  ":"  ✗   ").$r[0]."  -> ".$r[1]."\n";
echo "\n".($src!==$start?"index.php guncellendi ($ok/6). Yedek: index.php.bak-skin-*\n":"Degisiklik yok!\n");
echo "SIL: rm apply-premium-skin.php\n";
echo "Test: gizli pencere -> kategori ac -> belge listesi altin cerceveli premium kartlar;\n";
echo "      Ayarlar -> Interface-Sprache altinda aciklama satiri; kategori kartlarinda altin ikon rozetleri.\n";
