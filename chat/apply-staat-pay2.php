<?php
/**
 * ChatHelp — Staatliche paywall: Basic + Pro + Elite (form-engine.js)
 * Mevcut tek "Basic-Abo" butonunu Basic/Pro/Elite üçlüsüne çevirir.
 * KULLANIM: chat-help.com/chat/apply-staat-pay2.php -> opcache-reset.php (Ctrl+Shift+R). SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/form-engine.js';
if (!file_exists($file)) { exit("HATA: form-engine.js yok.\n"); }
$src=file_get_contents($file); $start=$src;
file_put_contents($file . '.bak-staatpay2-' . date('Ymd-His'), $src);
$rep=[];
function rp(&$src,$label,$old,$new){ global $rep; $n=substr_count($src,$old); if($n>0)$src=str_replace($old,$new,$src); $rep[]=[$label,$n]; }

if (strpos($src, 'cps-elite') !== false) { exit("Zaten ekli (cps-elite).\n"); }

/* #1 Tek Basic butonu -> Basic + Pro + Elite */
$oldBtn = '        <button type="button" class="cps-abo"
          style="display:flex;align-items:center;gap:12px;padding:13px;background:rgba(255,255,255,.04);border:1px solid #23233f;border-radius:13px;color:#fff;cursor:pointer">
          <span style="font-size:19px">⭐</span>
          <span style="flex:1;text-align:left"><span style="display:block;font-size:13.5px;font-weight:700">Basic-Abo</span><span style="font-size:11px;color:#9090a8">Unbegrenzt Formulare</span></span>
          <span style="font-size:13px;font-weight:800;color:#6094ff">13,99 €/Mo</span>
        </button>';
$newBtn = '        <button type="button" class="cps-basic" style="display:flex;align-items:center;gap:11px;padding:12px;margin-bottom:8px;background:rgba(212,168,74,.07);border:1px solid rgba(212,168,74,.3);border-radius:12px;color:#fff;cursor:pointer">
          <span style="font-size:18px">🥉</span><span style="flex:1;text-align:left"><span style="display:block;font-size:13px;font-weight:700">Basic</span><span style="font-size:10.5px;color:#9090a8">Unbegr. Formulare · 20 Dokumente</span></span><span style="font-size:13px;font-weight:800;color:#d4a84a">13,99 €/Mo</span>
        </button>
        <button type="button" class="cps-pro" style="display:flex;align-items:center;gap:11px;padding:12px;margin-bottom:8px;background:rgba(96,148,255,.08);border:1px solid rgba(96,148,255,.32);border-radius:12px;color:#fff;cursor:pointer">
          <span style="font-size:18px">🥈</span><span style="flex:1;text-align:left"><span style="display:block;font-size:13px;font-weight:700">Pro</span><span style="font-size:10.5px;color:#9090a8">50 Dokumente · 20 Fallanalysen</span></span><span style="font-size:13px;font-weight:800;color:#6094ff">29,99 €/Mo</span>
        </button>
        <button type="button" class="cps-elite" style="display:flex;align-items:center;gap:11px;padding:12px;background:rgba(200,160,255,.08);border:1px solid rgba(200,160,255,.32);border-radius:12px;color:#fff;cursor:pointer">
          <span style="font-size:18px">👑</span><span style="flex:1;text-align:left"><span style="display:block;font-size:13px;font-weight:700">Elite</span><span style="font-size:10.5px;color:#9090a8">Unbegrenzt · Priorität · alle Funktionen</span></span><span style="font-size:13px;font-weight:800;color:#c89aff">99,99 €/Mo</span>
        </button>';
rp($src, '#1 Basic/Pro/Elite butonları', $oldBtn, $newBtn);

/* #2 Handler: basic -> basic+pro+elite */
$oldH = "    overlay.querySelector('.cps-abo').addEventListener('click', function(){ cpsCheckout('subscription','basic'); });";
$newH = "    overlay.querySelector('.cps-basic').addEventListener('click', function(){ cpsCheckout('subscription','basic'); });
    overlay.querySelector('.cps-pro').addEventListener('click', function(){ cpsCheckout('subscription','pro'); });
    overlay.querySelector('.cps-elite').addEventListener('click', function(){ cpsCheckout('subscription','elite'); });";
rp($src, '#2 Basic/Pro/Elite handler', $oldH, $newH);

$changed=($src!==$start);
if($changed) file_put_contents($file,$src);
echo "ChatHelp — Staatliche Basic/Pro/Elite raporu\n============================================\n\n";
foreach($rep as [$l,$n]) echo ($n>0?"  ✓ ":"  ✗ ").$l."  →  $n\n";
echo "\n".($changed?"DURUM: form-engine.js güncellendi. ✅\n":"DURUM: Değişiklik yok.\n");
echo "GEREKLİ: önce apply-staat-pay.php çalıştırılmış olmalı.\n";
echo "SONRA: opcache-reset.php + tarayıcıda Ctrl+Shift+R. SİL: rm apply-staat-pay2.php\n";
