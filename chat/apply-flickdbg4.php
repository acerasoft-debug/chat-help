<?php
/* apply-flickdbg4 (GECICI) — flickdbg3'un POST adresini MUTLAK (/chat/log-flick.php)
   yapar; bos-log sorununu cozer. KULLANIM: pull2.php?key=...&files=apply-flickdbg4.php */
header('Content-Type: text/plain; charset=UTF-8'); error_reporting(E_ERROR|E_PARSE);
echo "apply-flickdbg4 OK\n";
$f=__DIR__.'/index.php'; $s=@file_get_contents($f); if($s===false) exit("okunamadi\n");
$s=preg_replace('#<script id="ch-flickdbg3?">.*?</script>#s','',$s,1); /* eskiyi kaldir */
$b='<script id="ch-flickdbg4">try{(function(){'
.'var C={};function sig(n){try{if(!n||n.nodeType!==1)return "#text";var id=n.id?("#"+n.id):"";var cl=(n.className&&typeof n.className==="string")?("."+n.className.split(" ").filter(Boolean).slice(0,2).join(".")):"";return "<"+n.tagName.toLowerCase()+">"+id+cl;}catch(e){return "?";}}'
.'function add(k){C[k]=(C[k]||0)+1;}'
.'var mo=new MutationObserver(function(ms){ms.forEach(function(m){try{'
.'if(m.type==="attributes"){add("ATTR "+sig(m.target)+" ["+m.attributeName+"]");}'
.'else if(m.type==="childList"){Array.prototype.forEach.call(m.addedNodes,function(n){add("ADD "+sig(n)+" @"+sig(m.target));});Array.prototype.forEach.call(m.removedNodes,function(n){add("DEL "+sig(n)+" @"+sig(m.target));});}'
.'}catch(e){}});});'
.'try{mo.observe(document.documentElement,{childList:true,subtree:true,attributes:true});}catch(e){}'
.'setTimeout(function(){try{mo.disconnect();}catch(e){}'
.'var arr=Object.keys(C).map(function(k){return [k,C[k]];}).sort(function(a,b){return b[1]-a[1];}).slice(0,20);'
.'var txt=arr.map(function(x){return x[1]+"x  "+x[0];}).join("\\n");'
.'try{var xhr=new XMLHttpRequest();xhr.open("POST","/chat/log-flick.php",true);xhr.send("URL:"+location.href+"\\n\\n"+txt);}catch(e){}'
.'try{fetch("/chat/log-flick.php",{method:"POST",body:"URL2:"+location.href+"\\n\\n"+txt});}catch(e){}'
.'try{var d=document.createElement("div");d.style.cssText="position:fixed;left:6px;top:6px;right:6px;z-index:2147483647;background:#1a0d1a;color:#ffd0ff;border:2px solid #a4a;border-radius:10px;padding:10px;font:11px/1.4 monospace;max-height:60vh;overflow:auto;white-space:pre-wrap";d.onclick=function(){d.remove();};d.textContent="FLICKER (gonderildi ✓ dokun=kapat)\\n\\n"+txt;document.body.appendChild(d);}catch(e){}'
.'},6000);'
.'})();}catch(e){}</script>';
$p=strripos($s,'</body>'); if($p===false) exit("</body> yok\n");
$s=substr($s,0,$p).$b."\n".substr($s,$p);
@file_put_contents($f,$s); if(function_exists('opcache_reset'))@opcache_reset();
echo (strpos((string)@file_get_contents($f),'ch-flickdbg4')!==false)?"✓ eklendi (mutlak yol + XHR+fetch). Sayfayi SERT YENILE, 8sn bekle, sonra dump-flick-log.php.\n":"✗\n";
