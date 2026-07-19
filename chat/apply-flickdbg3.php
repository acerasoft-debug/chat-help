<?php
/* apply-flickdbg3 (GECICI) — oynama kaynagini 6sn izler, sonucu log-flick.php'ye
   POST eder (ekrana da yazar). Ben dump-flick-log.php ile okurum.
   KULLANIM: pull2.php?key=...&files=apply-flickdbg3.php */
header('Content-Type: text/plain; charset=UTF-8'); error_reporting(E_ERROR|E_PARSE);
echo "apply-flickdbg3 OK\n";
$f=__DIR__.'/index.php'; $s=@file_get_contents($f); if($s===false) exit("okunamadi\n");
/* eski ch-flickdbg'yi kaldir */
$s=preg_replace('#<script id="ch-flickdbg">.*?</script>#s','',$s,1);
if(strpos($s,'ch-flickdbg3')!==false){ @file_put_contents($f,$s); exit("zaten ekli (eski temizlendi)\n"); }
$b='<script id="ch-flickdbg3">try{(function(){'
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
.'try{fetch("log-flick.php",{method:"POST",body:"UA:"+navigator.userAgent+"\\nURL:"+location.href+"\\n\\n"+txt});}catch(e){}'
.'try{var d=document.createElement("div");d.style.cssText="position:fixed;left:6px;top:6px;right:6px;z-index:2147483647;background:#1a0d1a;color:#ffd0ff;border:2px solid #a4a;border-radius:10px;padding:10px;font:11px/1.4 monospace;max-height:60vh;overflow:auto;white-space:pre-wrap";d.onclick=function(){d.remove();};d.textContent="FLICKER (gonderildi ✓ · dokun=kapat)\\n\\n"+txt;document.body.appendChild(d);}catch(e){}'
.'},6000);'
.'})();}catch(e){}</script>';
$p=strripos($s,'</body>'); if($p===false) exit("</body> yok\n");
$s=substr($s,0,$p).$b."\n".substr($s,$p);
@file_put_contents($f,$s); if(function_exists('opcache_reset'))@opcache_reset();
echo (strpos((string)@file_get_contents($f),'ch-flickdbg3')!==false)?"✓ eklendi. Sayfayi ac + 7sn bekle (sonuc otomatik gonderilir). Sonra bana dump-flick-log.php'yi calistir.\n":"✗\n";
