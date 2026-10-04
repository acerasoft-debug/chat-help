<?php
/* apply-flickdbg (GECICI) — "oynama"nin kaynagini bulur: 6 sn boyunca DOM
   degisimlerini (ekle/cikar/attribute) izler, EN COK degisen ogeleri EKRANDA
   listeler. Kaldir: apply-flickdbg-off.php
   KULLANIM: pull2.php?key=...&files=apply-flickdbg.php */
header('Content-Type: text/plain; charset=UTF-8'); error_reporting(E_ERROR|E_PARSE);
echo "apply-flickdbg OK\n";
$f=__DIR__.'/index.php'; $s=@file_get_contents($f); if($s===false) exit("okunamadi\n");
if(strpos($s,'ch-flickdbg')!==false) exit("zaten ekli\n");
$b='<script id="ch-flickdbg">try{(function(){'
.'var C={};function sig(n){try{if(n.nodeType!==1)return "#text";var id=n.id?("#"+n.id):"";var cl=(n.className&&typeof n.className==="string")?("."+n.className.split(" ").filter(Boolean).slice(0,2).join(".")):"";return "<"+n.tagName.toLowerCase()+">"+id+cl;}catch(e){return "?";}}'
.'function add(k){C[k]=(C[k]||0)+1;}'
.'var mo=new MutationObserver(function(ms){ms.forEach(function(m){try{'
.'if(m.type==="attributes"){add("ATTR "+sig(m.target)+" ["+m.attributeName+"]");}'
.'else if(m.type==="childList"){Array.prototype.forEach.call(m.addedNodes,function(n){add("ADD "+sig(n)+" @"+sig(m.target));});Array.prototype.forEach.call(m.removedNodes,function(n){add("DEL "+sig(n)+" @"+sig(m.target));});}'
.'}catch(e){}});});'
.'try{mo.observe(document.documentElement,{childList:true,subtree:true,attributes:true});}catch(e){}'
.'setTimeout(function(){try{mo.disconnect();}catch(e){}'
.'var arr=Object.keys(C).map(function(k){return [k,C[k]];}).sort(function(a,b){return b[1]-a[1];}).slice(0,18);'
.'var d=document.createElement("div");d.id="fdbgbox";d.style.cssText="position:fixed;left:6px;top:6px;right:6px;z-index:2147483647;background:#1a0d1a;color:#ffd0ff;border:2px solid #a4a;border-radius:10px;padding:10px;font:11px/1.5 monospace;max-height:70vh;overflow:auto;white-space:pre-wrap";d.onclick=function(){d.remove();};'
.'d.textContent="FLICKER KAYNAGI — 6sn (dokun=kapat)\\nEn cok degisen ogeler:\\n\\n"+arr.map(function(x){return x[1]+"x  "+x[0];}).join("\\n");'
.'document.body.appendChild(d);},6000);'
.'})();}catch(e){}</script>';
$p=strripos($s,'</body>'); if($p===false) exit("</body> yok\n");
$s=substr($s,0,$p).$b."\n".substr($s,$p);
@file_put_contents($f,$s); if(function_exists('opcache_reset'))@opcache_reset();
echo (strpos((string)@file_get_contents($f),'ch-flickdbg')!==false)?"✓ eklendi. Sayfayi ac, 6sn bekle -> mor kutu cikar -> ekran goruntusu at. Kaldir: apply-flickdbg-off.php\n":"✗\n";
