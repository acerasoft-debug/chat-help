<?php
/* apply-vrdbg (GECICI) — Verlauf son-3 neden gorunmuyor? EKRANDA gosterir:
   localStorage oturum dizileri, chSessKey, nav-verlauf/ch-verlauf-recent durumu.
   Kaldirmak: apply-vrdbg-off.php
   KULLANIM: pull2.php?key=...&files=apply-vrdbg.php */
header('Content-Type: text/plain; charset=UTF-8'); error_reporting(E_ERROR|E_PARSE);
echo "apply-vrdbg OK\n";
$f=__DIR__.'/index.php'; $s=@file_get_contents($f); if($s===false) exit("okunamadi\n");
if(strpos($s,'ch-vrdbg')!==false) exit("zaten ekli\n");
$b='<script id="ch-vrdbg">try{(function(){function box(){var d=document.getElementById("vrdbg");if(!d){d=document.createElement("div");d.id="vrdbg";d.style.cssText="position:fixed;left:6px;bottom:6px;right:6px;z-index:2147483647;background:#0d1a0d;color:#c0ffc0;border:1px solid #3a3;border-radius:10px;padding:10px;font:11px/1.5 monospace;max-height:55vh;overflow:auto;white-space:pre-wrap";d.onclick=function(){d.remove();};document.body.appendChild(d);}return d;}function scan(){var o=[];o.push("chSessKey="+(window.chSessKey||"(yok)"));var found=[];for(var i=0;i<localStorage.length;i++){var k=localStorage.key(i);var v=localStorage.getItem(k);if(v&&v.charAt(0)==="["){try{var a=JSON.parse(v);if(Array.isArray(a)&&a.length&&a[0]&&typeof a[0]==="object"){found.push(k+" (n="+a.length+") alanlar:"+Object.keys(a[0]).join(","));}}catch(e){}}}o.push("dizi-anahtarlari:\n  "+(found.join("\n  ")||"(hic array yok)"));o.push("nav-verlauf="+(document.getElementById("nav-verlauf")?"VAR":"YOK"));o.push("ch-verlauf-recent="+(document.getElementById("ch-verlauf-recent")?"VAR":"YOK"));try{var ss=JSON.parse(localStorage.getItem(window.chSessKey||"")||"[]");o.push("SK-oturum="+ss.length+" | "+ss.slice(0,4).map(function(s){return (s.title||"?");}).join(" || "));}catch(e){o.push("SK parse err");}box().textContent="VRDBG (dokun=kapat)\n"+o.join("\n");}setTimeout(scan,1500);setTimeout(scan,4500);})();}catch(e){}</script>';
$p=strripos($s,'</body>'); if($p===false) exit("</body> yok\n");
$s=substr($s,0,$p).$b."\n".substr($s,$p);
@file_put_contents($f,$s); if(function_exists('opcache_reset'))@opcache_reset();
echo (strpos((string)@file_get_contents($f),'ch-vrdbg')!==false)?"✓ VRDBG eklendi. Sayfayi yenile, altta yesil kutu cikar -> ekran goruntusu at. Kaldir: apply-vrdbg-off.php\n":"✗\n";
