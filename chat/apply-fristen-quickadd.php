<?php
/**
 * ChatHelp — apply-fristen-quickadd (CH_FRISTEN_QUICKADD)
 *  AI'nin urettigi belge kartina (.ch-doccard) "📅 Hatirlatma ekle"
 *  kisayolu ekler: kullanici tarih girer, dilekce basligiyla otomatik
 *  Fristen-Tracker'a eklenir (ayni "ch_fristen" localStorage biçimi,
 *  Fristen modulunun kendi periyodik tick'i UI'i otomatik gunceller —
 *  bu yama Fristen'in ic fonksiyonlarina dokunmaz, sadece ayni veri
 *  formatini paylasir).
 *  Ayrica belge kartina kisa bir "Einschreiben/taahhutlu posta" ipucu
 *  ekler.
 * KULLANIM: pull-updates.php?files=apply-fristen-quickadd.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fristen-quickadd BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_FRISTEN_QUICKADD') !== false) exit("Zaten ekli (CH_FRISTEN_QUICKADD).\n");
if (strpos($src, 'CH_AICHAT_DOCPDF') === false) exit("HATA: apply-aichat-docpdf.php (CH_AICHAT_DOCPDF) kurulu degil.\n");
file_put_contents($file . '.bak-fristenqa-' . date('Ymd-His'), $src);

$bundle = <<<'FQHTML'
<style id="ch-fristen-qa-css">
.ch-frist-row{margin-top:10px;padding-top:10px;border-top:1px dashed rgba(212,168,74,.25)}
.ch-frist-add{padding:8px 13px;border-radius:10px;border:1px solid rgba(212,168,74,.4);background:rgba(212,168,74,.08);
  color:var(--gold,#d4a84a);font-size:12px;font-weight:700;cursor:pointer;font-family:inherit}
.ch-frist-add:hover{background:rgba(212,168,74,.16)}
.ch-frist-form{display:none;gap:8px;margin-top:9px;align-items:center;flex-wrap:wrap}
.ch-frist-form.open{display:flex}
.ch-frist-form input[type="date"]{padding:9px 10px;border-radius:9px;border:1px solid var(--bdr,rgba(255,255,255,.14));
  background:var(--s2,#25252e);color:var(--t1,#fff);font-size:12.5px;font-family:inherit}
.ch-frist-form button{padding:9px 14px;border-radius:9px;border:none;background:linear-gradient(135deg,#d4a84a,#f0c860);
  color:#1a1400;font-size:12.5px;font-weight:800;cursor:pointer}
.ch-frist-ok{font-size:11.5px;color:#42df94;font-weight:700;margin-left:4px;opacity:0;transition:opacity .25s}
.ch-frist-ok.show{opacity:1}
</style>
<script id="ch-fristen-quickadd">
/* CH_FRISTEN_QUICKADD */
try{(function(){
  var TXT={de:{add:"Erinnerung hinzufügen",save:"Speichern",saved:"✓ Zu Fristen hinzugefügt",post:"Tipp: Wichtige Schreiben per Einschreiben/Einwurf-Einschreiben versenden — das ist im Streitfall Ihr Zustellnachweis."},
    tr:{add:"Hatırlatma ekle",save:"Kaydet",saved:"✓ Fristen'e eklendi",post:"İpucu: Önemli yazıları taahhütlü posta/iadeli taahhütlü ile gönderin — anlaşmazlıkta teslim kanıtınız olur."},
    en:{add:"Add reminder",save:"Save",saved:"✓ Added to deadlines",post:"Tip: send important letters by registered mail — it's your proof of delivery if disputed."}};
  function UIL(){ try{ return (typeof dLang!=="undefined"&&dLang)?dLang:(localStorage.getItem("ch_uilang")||"de"); }catch(e){ return "de"; } }
  function L(){ return TXT[UIL()]||TXT.en; }
  function esc(s){ return String(s==null?"":s).replace(/[&<>"]/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];}); }
  function LS(k,d){ try{ var v=JSON.parse(localStorage.getItem(k)); return v==null?d:v; }catch(e){ return d; } }
  function SV(k,v){ try{ localStorage.setItem(k,JSON.stringify(v)); }catch(e){} }

  function titleFor(card){
    var body=card.querySelector(".ch-doccard-body");
    var txt=body?body.textContent:"";
    var m=txt.match(/(?:Betreff|Konu|Subject)\s*:\s*(.+)/i);
    if(m && m[1]) return m[1].trim().slice(0,60);
    var h=card.querySelector(".ch-doccard-h");
    return (h?h.textContent.replace(/^[^A-Za-zÀ-ž]*/,"").trim():"Dokument").slice(0,60);
  }
  function decorate(card){
    if(card.querySelector(".ch-frist-row")) return;
    var l=L();
    var row=document.createElement("div"); row.className="ch-frist-row";
    row.innerHTML='<button type="button" class="ch-frist-add">📅 '+esc(l.add)+'</button>'
      +'<span class="ch-frist-ok"></span>'
      +'<div class="ch-frist-form"><input type="date" class="ch-frist-date"><button type="button" class="ch-frist-save">'+esc(l.save)+'</button></div>'
      +'<div style="font-size:10.5px;color:var(--t3,#a8a8d0);margin-top:8px;line-height:1.5">✉️ '+esc(l.post)+'</div>';
    card.appendChild(row);
    var addBtn=row.querySelector(".ch-frist-add"), form=row.querySelector(".ch-frist-form"), ok=row.querySelector(".ch-frist-ok");
    addBtn.addEventListener("click",function(){ form.classList.toggle("open"); });
    row.querySelector(".ch-frist-save").addEventListener("click",function(){
      var d=row.querySelector(".ch-frist-date").value; if(!d) return;
      var v=LS("ch_fristen",[]); v.push({t:titleFor(card),d:d,n:""}); SV("ch_fristen",v);
      form.classList.remove("open"); ok.textContent=l.saved; ok.classList.add("show");
      setTimeout(function(){ ok.classList.remove("show"); },2400);
    });
  }
  function tick(){ try{ document.querySelectorAll(".ch-doccard").forEach(decorate); }catch(e){} }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",function(){ tick(); setInterval(tick,700); });
  else { tick(); setInterval(tick,700); }
})();}catch(e){}
</script>
FQHTML;

$p = strrpos($src, '</body>');
if ($p === false) exit("HATA: </body> bulunamadi — yazilmadi.\n");
$src = substr($src, 0, $p) . $bundle . "\n" . substr($src, $p);

$tmp = tempnam(sys_get_temp_dir(), 'idx') . '.php';
file_put_contents($tmp, $src);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "LINT HATASI — index.php degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
file_put_contents($file, $src);
echo "✓ CH_FRISTEN_QUICKADD uygulandi. AI belge kartinda artik '📅 Hatirlatma ekle' + posta ipucu var.\n";
