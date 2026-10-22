<?php
/**
 * ChatHelp — apply-aichat-docpdf (CH_AICHAT_DOCPDF)
 *  AI sohbet frontend'ini intake-modeliyle tamamlar:
 *   • Serbest yazilan her mesaj artik dogrudan AI soru-cevaba gider
 *     (eski "algila -> hemen sablon ac" siçramasi kaldirilir).
 *   • AI yaniti [[DOC]]…[[/DOC]] iceriyorsa: metin sohbette ham gorunmez;
 *     onun yerine sik bir "belge karti" + '📄 PDF olarak kaydet' butonu cikar.
 *   • PDF butonu, placeholder'lari ([VORNAME] vb.) istemci tarafinda gercek
 *     profil degerleriyle degistirir (gizlilik korunur: gercek veri hic
 *     AI'ya gitmedi) ve mevcut chPrintDoc() ile A4 yazdirir.
 *   • Sohbet geçmişine tam dilekce metni kaydedilir -> baglam korunur.
 *   • aichat istegine country:CC eklenir -> AI dogru ulke hukukunu kullanir.
 * KULLANIM: pull-updates.php?files=apply-aichat-docpdf.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-aichat-docpdf BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_AICHAT_DOCPDF') !== false) exit("Zaten ekli (CH_AICHAT_DOCPDF).\n");
if (strpos($src, 'CH_AICHAT_FE') === false) exit("HATA: AI sohbet frontend (CH_AICHAT_FE) yok — once apply-aichat-frontend.php calistir.\n");
file_put_contents($file . '.bak-aidocpdf-' . date('Ymd-His'), $src);

$changes = 0;

/* ── 1) fetch govdesine country:CC ekle ── */
$oldFetch = 'lang:(typeof dLang!=="undefined"?dLang:"de"),nudge:freeNudge})';
$newFetch = 'lang:(typeof dLang!=="undefined"?dLang:"de"),country:(typeof CC!=="undefined"?CC:"DE"),nudge:freeNudge})';
$c=0; $src=str_replace($oldFetch,$newFetch,$src,$c); if($c){ $changes++; echo "  ✓ [1] aichat istegine country eklendi ($c)\n"; } else echo "  ✗ [1] fetch anchor yok\n";

/* ── 2) reply-isleme blogunu [[DOC]] destekli hale getir ── */
$oldReply = 'if(d&&d.reply){'."\n"
          .'        var reply=d.reply, suggest=null;'."\n"
          .'        var m=reply.match(/\[\[SUGGEST:(doc|cv|vst|jobs)\]\]\s*$/);'."\n"
          .'        if(m){ suggest=m[1]; reply=reply.replace(/\[\[SUGGEST:(doc|cv|vst|jobs)\]\]\s*$/,"").trim(); }'."\n"
          .'        addMsg("ai", reply);'."\n"
          .'        if(suggest) showSuggestChip(suggest);';
$newReply = 'if(d&&d.reply){'."\n"
          .'        var fullReply=d.reply, reply=d.reply, suggest=null;'."\n"
          .'        var m=reply.match(/\[\[SUGGEST:(doc|cv|vst|jobs)\]\]\s*$/);'."\n"
          .'        if(m){ suggest=m[1]; reply=reply.replace(/\[\[SUGGEST:(doc|cv|vst|jobs)\]\]\s*$/,"").trim(); }'."\n"
          .'        var _doc=(window.__chDocPdf&&window.__chDocPdf.extract)?window.__chDocPdf.extract(reply):null;'."\n"
          .'        if(_doc){ reply=_doc.before; }'."\n"
          .'        if(reply) addMsg("ai", reply);'."\n"
          .'        if(_doc&&window.__chDocPdf&&window.__chDocPdf.card) window.__chDocPdf.card(_doc.doc);'."\n"
          .'        if(suggest) showSuggestChip(suggest);';
$c=0; $src=str_replace($oldReply,$newReply,$src,$c); if($c){ $changes++; echo "  ✓ [2] [[DOC]] belge-karti isleme eklendi ($c)\n"; } else echo "  ✗ [2] reply blogu anchor yok\n";

/* ── 3) gecmise TAM yaniti (dilekce dahil) kaydet -> baglam korunur ── */
$oldHist = 'hist=hist.concat([{role:"user",content:userText},{role:"ai",content:reply}]);';
$newHist = 'hist=hist.concat([{role:"user",content:userText},{role:"ai",content:(typeof fullReply!=="undefined"?fullReply:reply)}]);';
$c=0; $src=str_replace($oldHist,$newHist,$src,$c); if($c){ $changes++; echo "  ✓ [3] gecmise tam dilekce metni kaydediliyor ($c)\n"; } else echo "  ✗ [3] history anchor yok\n";

/* ── 4) doSend: serbest metni her zaman AI intake'e yonlendir (sablon sicramasi kalkar) ── */
$oldSend = 'try{'."\n"
         .'          const r=await fetch(API+\'?action=detect\',{method:\'POST\',headers:{\'Content-Type\':\'application/json\'},body:JSON.stringify({text:tx,lang:dLang,country:CC})});'."\n"
         .'          const d=await r.json();'."\n"
         .'          if(d.doc&&d.doc!==\'other\'&&DOCS[d.doc]){if(d.reply)addMsg(\'ai\',d.reply);CD=d.doc;setTimeout(()=>showQuestions(d.doc),300);}'."\n"
         .'          else{ await chAiChatTurn(tx); }'."\n"
         .'        }catch(e){ await chAiChatTurn(tx); }';
$newSend = 'try{ await chAiChatTurn(tx); }catch(e){}';
$c=0; $src=str_replace($oldSend,$newSend,$src,$c); if($c){ $changes++; echo "  ✓ [4] serbest metin artik Q&A intake'e gidiyor ($c)\n"; } else echo "  ✗ [4] doSend anchor yok\n";

if ($changes < 4) { echo "\nHATA: beklenen 4 degisiklikten sadece $changes uygulandi — index.php DEGISTIRILMEDI.\n"; exit; }

/* ── 5) yardimci script (window.__chDocPdf) + CSS ── */
$bundle = <<<'DPHTML'
<style id="ch-aichat-docpdf-css">
.ch-doccard{border:1px solid rgba(212,168,74,.4);border-radius:14px;background:linear-gradient(135deg,rgba(212,168,74,.08),rgba(212,168,74,.02));padding:14px 16px;margin-top:6px;max-width:560px}
.ch-doccard-h{display:flex;align-items:center;gap:8px;font-size:12px;font-weight:800;letter-spacing:.4px;text-transform:uppercase;color:var(--gold,#d4a84a);margin-bottom:10px}
.ch-doccard-body{font-size:12.5px;line-height:1.6;color:var(--t1,#fff);white-space:pre-wrap;max-height:280px;overflow:auto;border:1px solid var(--bdr,rgba(255,255,255,.1));border-radius:10px;padding:12px;background:rgba(0,0,0,.15)}
.ch-doccard-btn{margin-top:11px;width:100%;padding:12px;border-radius:11px;border:none;background:linear-gradient(135deg,#d4a84a,#f0c860);color:#1a1400;font-size:13.5px;font-weight:800;cursor:pointer;font-family:inherit;box-shadow:0 8px 20px rgba(212,168,74,.28)}
.ch-doccard-btn:hover{transform:translateY(-1px)}
.ch-doccard-note{font-size:10.5px;color:var(--t3,#a8a8d0);margin-top:8px;line-height:1.45}
</style>
<script id="ch-aichat-docpdf">
/* CH_AICHAT_DOCPDF — [[DOC]] belge karti + placeholder geri-yukleme + A4 PDF */
try{(function(){
  function esc(s){ return String(s==null?"":s).replace(/[&<>"]/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];}); }
  var BTN={de:"📄 Als PDF speichern",tr:"📄 PDF olarak kaydet",en:"📄 Save as PDF",fr:"📄 Enregistrer en PDF",es:"📄 Guardar como PDF",it:"📄 Salva come PDF",nl:"📄 Opslaan als PDF",pl:"📄 Zapisz jako PDF",sv:"📄 Spara som PDF",pt:"📄 Guardar como PDF"};
  var HDR={de:"Ihr Dokument",tr:"Belgeniz",en:"Your document",fr:"Votre document",es:"Su documento",it:"Il tuo documento",nl:"Uw document",pl:"Twój dokument",sv:"Ditt dokument",pt:"O seu documento"};
  var NOTE={de:"Vorschau mit Ihren echten Daten — Ihr Name/Ihre Adresse wurden lokal eingesetzt und nie an die KI gesendet.",tr:"Gerçek bilgilerinizle önizleme — ad/adresiniz yalnızca cihazınızda eklendi, yapay zekâya hiç gönderilmedi.",en:"Preview with your real data — your name/address were inserted locally and never sent to the AI."};
  function uil(){ try{ return (typeof dLang!=="undefined"&&dLang)?dLang:(localStorage.getItem("ch_uilang")||"de"); }catch(e){ return "de"; } }
  function tr(map){ return map[uil()]||map.en||map.de; }

  function restore(s){
    try{
      var P2=window.P||{};
      var map={"[VORNAME]":P2.f1,"[NACHNAME]":P2.f2,"[ADRESSE]":P2.f3,"[ORT]":P2.f4,"[TELEFON]":P2.f5,"[EMAIL]":P2.f7};
      Object.keys(map).forEach(function(k){ if(map[k]) s=s.split(k).join(map[k]); });
      var full=[(P2.f1||""),(P2.f2||"")].join(" ").trim();
      if(full) s=s.split("[NAME]").join(full);
    }catch(e){}
    return s;
  }
  function extract(text){
    if(!text) return null;
    var re=/\[\[DOC\]\]([\s\S]*?)\[\[\/DOC\]\]/;
    var m=text.match(re);
    if(!m) return null;
    return { before:text.slice(0,m.index).trim(), doc:(m[1]||"").trim() };
  }
  function printDoc(docText){
    var real=restore(docText);
    var html='<!doctype html><html><head><meta charset="utf-8"><title>ChatHelp</title>'
      +'<style>@page{size:A4;margin:0}html,body{margin:0;padding:0}'
      +'.a4{width:794px;min-height:1123px;box-sizing:border-box;padding:80px 84px;background:#fff;color:#1e1e26;'
      +'font-family:\'Inter\',\'Segoe UI\',Arial,sans-serif;font-size:12.5px;line-height:1.75;white-space:pre-wrap}'
      +'</style></head><body><div class="a4">'+esc(real)+'</div></body></html>';
    try{ if(window.chPrintDoc){ window.chPrintDoc(html); return; } }catch(e){}
    try{ var w=window.open("","_blank"); w.document.open(); w.document.write(html+'<scr'+'ipt>window.onload=function(){setTimeout(function(){window.print();},350);}<\/scr'+'ipt>'); w.document.close(); }catch(e){}
  }
  function card(docText){
    try{
      var mi=document.getElementById("mi"); if(!mi) return;
      var real=restore(docText);
      var d=document.createElement("div"); d.className="msg ai";
      d.innerHTML='<div class="av">CH</div><div class="mbody"><div class="bbl" style="background:transparent;padding:0">'
        +'<div class="ch-doccard"><div class="ch-doccard-h">📄 '+esc(tr(HDR))+'</div>'
        +'<div class="ch-doccard-body">'+esc(real)+'</div>'
        +'<button type="button" class="ch-doccard-btn">'+esc(tr(BTN))+'</button>'
        +'<div class="ch-doccard-note">'+esc(tr(NOTE))+'</div></div></div></div>';
      mi.appendChild(d);
      var b=d.querySelector(".ch-doccard-btn");
      if(b) b.addEventListener("click",function(){ printDoc(docText); });
      try{ mi.scrollTop=mi.scrollHeight; }catch(e){}
    }catch(e){}
  }
  window.__chDocPdf={ extract:extract, restore:restore, card:card, print:printDoc };
})();}catch(e){}
</script>
DPHTML;

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
echo "\n✓ CH_AICHAT_DOCPDF uygulandi ($changes/4 degisiklik + yardimci script).\n";
echo "Test: chat'e serbest yaz -> AI soru sorar -> yeterli bilgi olunca belge karti + PDF butonu; buton gercek adinla A4 yazdirir.\n";
