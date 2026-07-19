<?php
/* apply-diag2 (GECICI) — YUKLENEN sayfada guncellemeler var mi? EKRANDA gosterir:
   CH_ marker'lari, .snd-row premium mi, nav-verlauf/recent, oturum sayisi.
   Kaldirmak: apply-diag2-off.php
   KULLANIM: pull2.php?key=...&files=apply-diag2.php */
header('Content-Type: text/plain; charset=UTF-8'); error_reporting(E_ERROR|E_PARSE);
echo "apply-diag2 OK\n";
$f=__DIR__.'/index.php'; $s=@file_get_contents($f); if($s===false) exit("okunamadi\n");
if(strpos($s,'ch-diag2')!==false) exit("zaten ekli\n");
$b='<script id="ch-diag2">try{(function(){'
.'function box(){var d=document.getElementById("d2");if(!d){d=document.createElement("div");d.id="d2";d.style.cssText="position:fixed;left:6px;top:6px;right:6px;z-index:2147483647;background:#0a0f2a;color:#bfe0ff;border:2px solid #4a90d9;border-radius:10px;padding:10px;font:11px/1.55 monospace;max-height:60vh;overflow:auto;white-space:pre-wrap";d.onclick=function(){d.remove();};document.body.appendChild(d);}return d;}'
.'function has(m){try{return document.documentElement.innerHTML.indexOf(m)!==-1;}catch(e){return false;}}'
.'function run(){var o=[];'
.'o.push("=== YUKLENEN SAYFA (dokun=kapat) ===");'
.'["CH_SND_PREMIUM","CH_VERLAUF_RECENT3","CH_APPSTORE_IOS","CH_KONTO_REALUSER","CH_HIDE_BILLING","CH_UPDATEBTN","CH_SW_V5"].forEach(function(m){o.push((has(m)?"✓":"✗ EKSIK")+" "+m);});'
.'try{var r=document.querySelector(".snd-row");if(r){var bg=getComputedStyle(r).backgroundImage;o.push("snd-row bg: "+(bg&&bg.indexOf("gradient")!==-1?"PREMIUM(gradient)":bg));}else o.push("snd-row: sayfada yok (Sendungen acilmadi)");}catch(e){}'
.'o.push("nav-verlauf: "+(document.getElementById("nav-verlauf")?"VAR":"YOK"));'
.'o.push("ch-verlauf-recent: "+(document.getElementById("ch-verlauf-recent")?"VAR":"YOK"));'
.'try{var n=0;for(var i=0;i<localStorage.length;i++){var k=localStorage.key(i);var v=localStorage.getItem(k);if(v&&v.charAt(0)==="["){try{var a=JSON.parse(v);if(Array.isArray(a)&&a[0]&&a[0].messages){n=a.length;}}catch(e){}}}o.push("kayitli konusma: "+n);}catch(e){}'
.'o.push("SW: "+((navigator.serviceWorker&&navigator.serviceWorker.controller)?"kontrol VAR":"kontrol YOK"));'
.'box().textContent=o.join("\\n");}'
.'setTimeout(run,1500);setTimeout(run,4000);'
.'})();}catch(e){}</script>';
$p=strripos($s,'</body>'); if($p===false) exit("</body> yok\n");
$s=substr($s,0,$p).$b."\n".substr($s,$p);
@file_put_contents($f,$s); if(function_exists('opcache_reset'))@opcache_reset();
echo (strpos((string)@file_get_contents($f),'ch-diag2')!==false)?"✓ Teshis kutusu eklendi (ust-mavi). Sayfayi ac -> ekran goruntusu at.\n":"✗\n";
