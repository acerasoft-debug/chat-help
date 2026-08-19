<?php
/* apply-updatebtn (CH_UPDATEBTN) — tek dokunusla TAZE yukleme: eski Service
   Worker'i unregister eder + tum cache'leri siler + reload. localStorage
   (gecmis/profiller) KORUNUR — veri silinmez. Sag-altta premium 'Aktualisieren'
   pili. Kaldirmak: apply-updatebtn-off.php
   KULLANIM: pull2.php?key=...&files=apply-updatebtn.php */
header('Content-Type: text/plain; charset=UTF-8'); error_reporting(E_ERROR|E_PARSE);
echo "apply-updatebtn OK\n";
$f=__DIR__.'/index.php'; $s=@file_get_contents($f); if($s===false) exit("okunamadi\n");
if(strpos($s,'CH_UPDATEBTN')!==false) exit("zaten ekli\n");
$b='<style id="ch-updatebtn-css">#ch-updbtn{position:fixed;right:12px;bottom:150px;z-index:2147483200;display:flex;align-items:center;gap:7px;background:linear-gradient(135deg,#e8c877,#d4a84a);color:#171a2b;border:none;border-radius:100px;padding:10px 15px;font:700 13px Inter,system-ui,sans-serif;box-shadow:0 6px 20px rgba(0,0,0,.4);cursor:pointer;-webkit-tap-highlight-color:transparent}#ch-updbtn:active{transform:translateY(1px)}</style>'
.'<script id="ch-updatebtn-js">try{(function(){'
.'var T={de:"Aktualisieren",tr:"Güncelle",en:"Update",fr:"Actualiser",es:"Actualizar",it:"Aggiorna"};'
.'function lg(){try{return (localStorage.getItem("ch_uilang")||"de").slice(0,2);}catch(e){return "de";}}'
.'function mk(){try{if(document.getElementById("ch-updbtn"))return;var b=document.createElement("button");b.id="ch-updbtn";b.innerHTML="🔄 <span>"+((T[lg()]||T.de))+"</span>";'
.'b.onclick=function(){b.querySelector("span").textContent="…";'
.'Promise.resolve().then(function(){return (window.caches?caches.keys().then(function(ks){return Promise.all(ks.map(function(k){return caches.delete(k);}));}):null);})'
.'.then(function(){return (navigator.serviceWorker?navigator.serviceWorker.getRegistrations().then(function(rs){return Promise.all(rs.map(function(r){return r.unregister();}));}):null);})'
.'.then(function(){try{location.reload(true);}catch(e){location.reload();}}).catch(function(){location.reload();});};'
.'document.body.appendChild(b);}catch(e){}}'
.'if(document.body)mk();else document.addEventListener("DOMContentLoaded",mk);'
.'})();}catch(e){}</script>';
$p=strripos($s,'</body>'); if($p===false) exit("</body> yok\n");
$s=substr($s,0,$p).$b."\n".substr($s,$p);
@file_put_contents($f,$s); if(function_exists('opcache_reset'))@opcache_reset();
echo (strpos((string)@file_get_contents($f),'CH_UPDATEBTN')!==false)?"✓ 'Aktualisieren' butonu eklendi (sag-alt). Bir kez bas -> taze yuklenir, veri korunur.\n":"✗\n";
