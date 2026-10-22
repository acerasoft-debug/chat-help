<?php
/**
 * ChatHelp — apply-jobs-connect (CH_JOBS_CONNECT)
 *  Job finden <-> CV/Lebenslauf baglantisi + firma e-postasi:
 *   [1] Anschreiben modalinda yeni satir:
 *       • 🏢 Arbeitgeber-E-Mail alani (ilan e-postasi varsa onceden dolu,
 *         yoksa kullanici yazar; son girilen hatirlanir)
 *       • CV durumu cipi:
 *         ✓ CV Studio'dan Lebenslauf hazir (otomatik eklenir)
 *         ✓ Yuklenmis PDF Lebenslauf (indir butonuyla — e-postana ekle)
 *         ⚠ CV yok -> "CV Studio'da olustur" butonu VEYA yerinde PDF yukle
 *   [2] "In Mail-App offnen" artik girilen firma adresine acilir.
 *   [3] "An meine E-Mail" govdesindeki Arbeitgeber-E-Mail satiri artik
 *       girilen adresi kullanir (ilan adresi yedek).
 *  PDF yukleme localStorage "ch_cv_pdf" {n,s,d} (max ~2.5MB) — gercek
 *  eklenti (attachment) SMTP altyapisinda olmadigi icin durustce
 *  "indir + kendi e-postana ekle" akisi sunulur.
 *  GEREKLI: apply-jobsearch-ui + apply-jobs-mailflow + apply-jobs-cvattach.
 * KULLANIM: pull-updates.php?files=apply-jobs-connect.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-jobs-connect BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_JOBS_CONNECT') !== false) exit("Zaten ekli (CH_JOBS_CONNECT).\n");
if (strpos($src, 'CH_JOBS_CVATTACH') === false) exit("HATA: apply-jobs-cvattach.php kurulu degil.\n");
file_put_contents($file . '.bak-jobsconnect-' . date('Ymd-His'), $src);

$changes = 0;

/* ── [2] mailto: firma alanini oncelikli oku ── */
$oldTo = 'var to=(job&&(job.email||job.contact_email))||"";';
$newTo = 'var to=""; try{ to=((document.getElementById("jb-comp-email")||{}).value||"").trim(); }catch(e){} if(!to) to=(job&&(job.email||job.contact_email))||""; try{ if(to) localStorage.setItem("ch_last_company_email",to); }catch(e){}';
$c=0; $src=str_replace($oldTo,$newTo,$src,$c); if($c){ $changes++; echo "  ✓ [2] mailto firma alanini kullaniyor ($c)\n"; } else echo "  ✗ [2] mailto anchor yok\n";

/* ── [3] mailme govdesi: firma satiri girilen adresi kullansin ── */
$oldBody = 'body+=((job&&job.email)?("\n\nArbeitgeber-E-Mail: "+job.email):"");';
$newBody = 'var __ce=""; try{ __ce=((document.getElementById("jb-comp-email")||{}).value||"").trim(); if(__ce) localStorage.setItem("ch_last_company_email",__ce); }catch(e){} var __fe=__ce||((job&&job.email)||""); body+=(__fe?("\n\nArbeitgeber-E-Mail: "+__fe):"");';
$c=0; $src=str_replace($oldBody,$newBody,$src,$c); if($c){ $changes++; echo "  ✓ [3] mailme govdesi firma alanini kullaniyor ($c)\n"; } else echo "  ✗ [3] mailme body anchor yok\n";

if ($changes < 2) { echo "\nHATA: 2 anchor'dan $changes eslesti — index.php DEGISTIRILMEDI.\n"; exit; }

/* ── [1] modal dekoratoru + CSS ── */
$bundle = <<<'JCHTML'
<style id="ch-jobs-connect-css">
.jb-sheet-f{flex-wrap:wrap}
#jb-conn-row{flex:1 1 100%;display:flex;flex-direction:column;gap:8px;margin-bottom:4px}
#jb-comp-email{width:100%;padding:10px 12px;border-radius:10px;border:1px solid var(--bdr,rgba(255,255,255,.14));background:var(--s2,#25252e);color:var(--t1,#fff);font-size:13px;font-family:inherit}
#jb-comp-email:focus{outline:none;border-color:rgba(212,168,74,.55)}
#jb-cv-status{display:flex;align-items:center;gap:9px;flex-wrap:wrap;font-size:12px;line-height:1.5}
#jb-cv-status.ok{color:#42df94}
#jb-cv-status.warn{color:#e0a03c}
#jb-cv-status .jcbtn{padding:7px 12px;border-radius:9px;border:1px solid rgba(212,168,74,.4);background:rgba(212,168,74,.08);color:var(--gold,#d4a84a);font-size:11.5px;font-weight:700;cursor:pointer;font-family:inherit}
#jb-cv-status .jcbtn:hover{background:rgba(212,168,74,.16)}
#jb-cv-status a.jcbtn{text-decoration:none}
</style>
<script id="ch-jobs-connect">
/* CH_JOBS_CONNECT — Anschreiben modal dekoratoru */
try{(function(){
  function esc(s){ return String(s==null?"":s).replace(/[&<>"]/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];}); }
  function UIL(){ try{ return (window.chUIL?window.chUIL():(localStorage.getItem("ch_uilang")||"de")); }catch(e){ return "de"; } }
  var T={
    de:{ph:"🏢 Arbeitgeber-E-Mail (z. B. jobs@firma.de)",cvOk:"✓ Lebenslauf aus CV Studio wird automatisch beigefügt",pdfOk:"✓ Hochgeladener Lebenslauf:",dl:"⬇ Herunterladen",rm:"✕",none:"⚠ Noch kein Lebenslauf —",make:"In CV Studio erstellen",up:"PDF hochladen",note:"PDF bitte beim Versand selbst anhängen (Download-Button).",big:"Datei zu groß (max. 2,5 MB)"},
    tr:{ph:"🏢 Firma e-postası (örn. jobs@firma.de)",cvOk:"✓ CV Studio'daki özgeçmişin otomatik eklenecek",pdfOk:"✓ Yüklenen özgeçmiş:",dl:"⬇ İndir",rm:"✕",none:"⚠ Henüz özgeçmiş yok —",make:"CV Studio'da oluştur",up:"PDF yükle",note:"PDF'i gönderirken e-postanıza kendiniz ekleyin (indir butonu).",big:"Dosya çok büyük (en fazla 2,5 MB)"},
    en:{ph:"🏢 Employer email (e.g. jobs@company.com)",cvOk:"✓ Your CV from CV Studio will be included automatically",pdfOk:"✓ Uploaded CV:",dl:"⬇ Download",rm:"✕",none:"⚠ No CV yet —",make:"Create in CV Studio",up:"Upload PDF",note:"Attach the PDF yourself when sending (download button).",big:"File too large (max 2.5 MB)"}
  };
  function L(){ return T[UIL()]||T.en; }
  function hasCvStudio(){
    try{ var d=JSON.parse(localStorage.getItem("ch_cv_data")||"{}");
      return !!(d&&(d.name||(d.exp&&d.exp.some(function(x){return x.r||x.f;})))); }catch(e){ return false; }
  }
  function getPdf(){ try{ return JSON.parse(localStorage.getItem("ch_cv_pdf")||"null"); }catch(e){ return null; } }

  function renderStatus(row){
    var l=L(), st=row.querySelector("#jb-cv-status");
    var pdf=getPdf();
    if(hasCvStudio()){
      st.className="ok"; st.id="jb-cv-status";
      st.innerHTML=esc(l.cvOk)+(pdf?(' · '+esc(l.pdfOk)+' '+esc(pdf.n)+' <a class="jcbtn" download="'+esc(pdf.n)+'" href="'+pdf.d+'">'+esc(l.dl)+'</a>'):'');
      return;
    }
    if(pdf){
      st.className="ok"; st.id="jb-cv-status";
      st.innerHTML=esc(l.pdfOk)+' '+esc(pdf.n)
        +' <a class="jcbtn" download="'+esc(pdf.n)+'" href="'+pdf.d+'">'+esc(l.dl)+'</a>'
        +' <button type="button" class="jcbtn" id="jb-pdf-rm">'+esc(l.rm)+'</button>'
        +' <span style="opacity:.75">'+esc(l.note)+'</span>';
      var rm=st.querySelector("#jb-pdf-rm");
      if(rm) rm.addEventListener("click",function(){ try{ localStorage.removeItem("ch_cv_pdf"); }catch(e){} renderStatus(row); });
      return;
    }
    st.className="warn"; st.id="jb-cv-status";
    st.innerHTML=esc(l.none)
      +' <button type="button" class="jcbtn" id="jb-cv-make">📇 '+esc(l.make)+'</button>'
      +' <label class="jcbtn" style="display:inline-block">📎 '+esc(l.up)+'<input type="file" id="jb-cv-up" accept="application/pdf" style="display:none"></label>';
    var mk=st.querySelector("#jb-cv-make");
    if(mk) mk.addEventListener("click",function(){
      try{ var m=row.closest(".jb-modal"); if(m) m.remove(); }catch(e){}
      try{ gP("cv"); }catch(e){}
    });
    var up=st.querySelector("#jb-cv-up");
    if(up) up.addEventListener("change",function(){
      var f=up.files&&up.files[0]; if(!f) return;
      if(f.size>2621440){ alert(L().big); return; }
      var rd=new FileReader();
      rd.onload=function(){
        try{ localStorage.setItem("ch_cv_pdf", JSON.stringify({n:f.name,s:f.size,d:String(rd.result)})); }catch(e){ alert(L().big); return; }
        renderStatus(row);
      };
      rd.readAsDataURL(f);
    });
  }

  function decorate(){
    try{
      var mm=document.getElementById("jb-mailme");
      if(!mm) return;
      var f=mm.parentNode; if(!f||f.querySelector("#jb-conn-row")) return;
      var l=L();
      var row=document.createElement("div"); row.id="jb-conn-row";
      var last=""; try{ last=localStorage.getItem("ch_last_company_email")||""; }catch(e){}
      row.innerHTML='<input id="jb-comp-email" type="email" placeholder="'+esc(l.ph)+'" value="'+esc(last)+'">'
        +'<div id="jb-cv-status"></div>';
      f.insertBefore(row, f.firstChild);
      renderStatus(row);
    }catch(e){}
  }
  setInterval(decorate,500);
})();}catch(e){}
</script>
JCHTML;

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
echo "\n✓ CH_JOBS_CONNECT uygulandi ($changes/2 + dekorator).\n";
echo "Test: Job finden -> Anschreiben erstellen -> modalda firma e-posta alani + CV durum cipi.\n";
