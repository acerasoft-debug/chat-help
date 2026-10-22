<?php
/**
 * ChatHelp — apply-visa-dossier (CH_VISA_DOSSIER)
 *  🗂️ VIZE DOSYASI — hedef ULKE bazli tam belge seti + durum takibi + toplu cikti.
 *   • Her ulke icin gerekli belgeler ayri ayri listelenir.
 *   • DOLDURULACAK belgeler (Vize Studyosu formlari) durumla gosterilir:
 *     dolduruldu ise "Düzenlendi ✓", degilse "Düzenlenmedi". (Otomatik —
 *     ch_vsf_* localStorage'a bakar.)
 *   • TEMIN EDILECEK belgeler (pasaport, foto, sigorta...) elle isaretlenir.
 *   • Ilerleme cubugu (X/Y hazir).
 *   • "📄 Doldurulanları tek PDF" (birlesik) + her belge icin tek tek indirme.
 *     PDF'ler chDlGate arkasinda (giris + plan).
 *  1) Vize Studyosu'nun icini window.chVSF ile disa acar.
 *  2) TR vize paneline "Vize Dosyası" giris karti + dosya sayfasi ekler.
 * KULLANIM: pull-updates.php?files=apply-visa-dossier.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-visa-dossier BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_VISA_DOSSIER')!==false) exit("Zaten ekli (CH_VISA_DOSSIER).\n");
if (strpos($src,'window.chOpenVSF=open;')===false) exit("HATA: Vize Studyosu (CH_VISA_STUDIO) bulunamadi — once apply-visa-studio.php.\n");

$fail=[];

/* ── [1] Vize Studyosu icini disa ac ── */
$o1 = "  window.chOpenVSF=open;";
$n1 = "  window.chOpenVSF=open;\n"
    . "  window.chVSF={ /*CH_VISA_DOSSIER*/\n"
    . "    open:open,\n"
    . "    VF:VF, getD:getD, buildPages:buildPages, cssId:\"ch-vsf-css\", makePdfHtml:function(pages){ var css=document.getElementById(\"ch-vsf-css\"); return '<!doctype html><html><head><meta charset=\"utf-8\"><title>ChatHelp</title><style>@page{size:A4;margin:0}html,body{margin:0;padding:0}'+(css?css.textContent:\"\")+'\\n.vfA4{page-break-after:always}.vfA4:last-child{page-break-after:auto}</style></head><body>'+pages.join(\"\")+'<scr'+'ipt>window.onload=function(){setTimeout(function(){window.print();},350);};<\\/scr'+'ipt></body></html>'; },\n"
    . "    openForm:function(k){ try{ if(!VF[k])return; panel().classList.add(\"on\"); CUR=k; STEP=0; prefill(); renderStep(); }catch(e){} },\n"
    . "    isDone:function(k){ try{ var d=getD(k)||{}, n=0; for(var x in d){ if((\"\"+d[x]).trim()) n++; } return n>=2; }catch(e){ return false; } } };";
$c=0; $src=str_replace($o1,$n1,$src,$c);
echo ($c===1?"  ✓ [1] Vize Studyosu ici disa acildi (window.chVSF)\n":"  ✗ [1] chOpenVSF anchor ($c)\n"); if($c!==1)$fail[]=1;

/* ── [2] Dosya sayfasi + giris karti ── */
$bundle = <<<'HTML'
<style id="ch-vdos-css">
/* CH_VISA_DOSSIER */
#pnl-vdos{position:fixed;inset:0;z-index:545;background:var(--bg,#17171d);display:none;flex-direction:column;overflow:hidden}
#pnl-vdos.on{display:flex}
.vd-top{display:flex;align-items:center;gap:10px;padding:12px 16px;border-bottom:1px solid rgba(212,168,74,.18);background:var(--s1,#1d1d26)}
.vd-back{background:none;border:1px solid rgba(212,168,74,.3);color:#d4a84a;border-radius:9px;width:34px;height:34px;font-size:18px;cursor:pointer}
.vd-ttl{font-size:14px;font-weight:700;color:#fff}
.vd-scroll{flex:1;overflow-y:auto;padding:16px}
.vd-wrap{max-width:820px;margin:0 auto}
.vd-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;margin-top:12px}
.vd-cc{border:1px solid rgba(212,168,74,.22);border-radius:12px;padding:12px;cursor:pointer;background:rgba(212,168,74,.04);text-align:center;transition:border-color .15s}
.vd-cc:hover{border-color:#d4a84a}
.vd-flag{font-size:26px}.vd-cn{font-size:12.5px;font-weight:700;color:#fff;margin-top:5px}.vd-cs{font-size:10px;color:var(--t3,#99a);margin-top:2px}
.vd-prog{height:9px;border-radius:100px;background:rgba(255,255,255,.08);overflow:hidden;margin:10px 0}
.vd-prog>i{display:block;height:100%;background:linear-gradient(90deg,#d4a84a,#f0c860);transition:width .3s}
.vd-sec{font-size:12px;font-weight:800;color:#d4a84a;letter-spacing:.4px;margin:18px 0 8px;text-transform:uppercase}
.vd-row{display:flex;align-items:center;gap:11px;padding:11px 13px;border:1px solid rgba(212,168,74,.16);border-radius:11px;margin-bottom:8px;background:rgba(255,255,255,.02)}
.vd-row.done{border-color:rgba(70,180,110,.5);background:rgba(70,180,110,.06)}
.vd-chk{width:22px;height:22px;border-radius:6px;border:2px solid rgba(212,168,74,.45);flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:13px;color:#46b46e;cursor:pointer}
.vd-chk.on{background:rgba(70,180,110,.15);border-color:#46b46e}
.vd-info{flex:1;min-width:0}
.vd-n{font-size:13px;font-weight:600;color:#fff}
.vd-d{font-size:10.5px;color:var(--t3,#99a);margin-top:2px}
.vd-st{font-size:10px;font-weight:700;padding:3px 9px;border-radius:100px;white-space:nowrap}
.vd-st.ok{background:rgba(70,180,110,.16);color:#7fd6a0}
.vd-st.no{background:rgba(212,168,74,.12);color:var(--t3,#b9a06a)}
.vd-btn{border:1px solid rgba(212,168,74,.4);background:none;color:#d4a84a;border-radius:8px;padding:6px 11px;font-size:11.5px;cursor:pointer;font-family:inherit;white-space:nowrap}
.vd-btn.p{background:linear-gradient(135deg,#d4a84a,#f0c860);border:none;color:#141414;font-weight:700}
.vd-actions{display:flex;gap:9px;flex-wrap:wrap;margin-top:16px}
.vd-note{font-size:10.5px;color:var(--t3,#99a);margin-top:12px;line-height:1.5}
.vd-entry{border:1px solid #d4a84a;border-radius:13px;padding:14px;margin-bottom:14px;cursor:pointer;background:linear-gradient(135deg,rgba(212,168,74,.12),rgba(212,168,74,.03))}
</style>
<script id="ch-vdos-js">
/* CH_VISA_DOSSIER — hedef ulke bazli vize dosyasi */
try{(function(){
  function esc(s){ return String(s==null?"":s).replace(/[&<>"]/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];}); }

  /* doldurulabilir belgeler (Vize Studyosu formlari) */
  var FILL_SCH=[
    {k:"schengen",n:"Schengen Başvuru Formu",req:true},
    {k:"cover",n:"Niyet Dilekçesi (Cover Letter)",req:true},
    {k:"itinerary",n:"Seyahat Planı (Itinerary)",req:true},
    {k:"sponsor",n:"Sponsor / Masraf Taahhüt Beyanı",req:false},
    {k:"invitation",n:"Davet Mektubu (varsa)",req:false}
  ];
  var FILL_NOSCH=[
    {k:"cover",n:"Niyet Dilekçesi (Cover Letter)",req:true},
    {k:"itinerary",n:"Seyahat Planı (Itinerary)",req:true},
    {k:"sponsor",n:"Sponsor / Masraf Taahhüt Beyanı",req:false},
    {k:"invitation",n:"Davet Mektubu (varsa)",req:false}
  ];
  /* temin edilecek belgeler */
  function OB(k,t,d){ return {k:k,t:t,d:d}; }
  var OB_SCH=[
    OB("pasaport","Geçerli pasaport","Min. 3 ay geçerli + 2 boş sayfa"),
    OB("foto","Biyometrik fotoğraf (2 adet)","Son 6 ay, Schengen standardı 35×45 mm"),
    OB("sigorta","Seyahat sağlık sigortası","Min. 30.000 € teminat, tüm Schengen"),
    OB("randevu","Biyometrik randevu onayı","Konsolosluk/VFS/iDATA/TLS randevusu"),
    OB("ucus","Gidiş-dönüş uçak rezervasyonu","Kesin bilet almadan rezervasyon yeterli"),
    OB("konaklama","Otel / konaklama rezervasyonu","Tüm süreyi kapsamalı"),
    OB("banka","Banka hesap dökümü","Son 3 ay, yeterli bakiye"),
    OB("isyeri","İşyeri belgesi + SGK dökümü","Çalışan: izin yazısı + SGK hizmet dökümü · Şirket sahibi: imza sirküleri, vergi levhası"),
    OB("harc","Vize harcı dekontu","Konsolosluk/aracı harcı")
  ];
  var OB_GB=[
    OB("pasaport","Geçerli pasaport","Seyahat boyunca geçerli + boş sayfa"),
    OB("foto","Dijital biyometrik fotoğraf","UK standardı (online yüklenir)"),
    OB("banka","Banka hesap dökümü","Son 6 ay"),
    OB("isyeri","İşyeri belgesi + SGK dökümü","Dönüş güvencesi için"),
    OB("konaklama","Konaklama / davet bilgisi",""),
    OB("ucus","Uçuş rezervasyonu",""),
    OB("harc","Vize ücreti ödemesi","Online ödenir")
  ];
  var OB_US=[
    OB("pasaport","Geçerli pasaport","Min. 6 ay geçerli"),
    OB("foto","Vesikalık fotoğraf (5×5 cm)","ABD standardı, beyaz fon"),
    OB("randevu","Görüşme randevusu","Konsolosluk mülakat randevusu"),
    OB("banka","Banka hesap dökümü / gelir belgesi","Ekonomik durum kanıtı"),
    OB("isyeri","İşyeri belgesi + SGK dökümü","Türkiye'ye bağ / dönüş güvencesi"),
    OB("harc","Vize ücreti (MRV) dekontu","")
  ];

  var DEST={
    DE:{f:"🇩🇪",n:"Almanya",cons:"Almanya Konsolosluğu · iDATA",sch:true,extra:["Kısa süreli (Typ C) için: Verpflichtungserklärung gerekiyorsa davet eden kişi Almanya'daki Ausländerbehörde'den alır."]},
    FR:{f:"🇫🇷",n:"Fransa",cons:"Fransa Konsolosluğu · TLScontact",sch:true,extra:["Başvuru France-Visas portalından doldurulup TLScontact randevusuyla tamamlanır."]},
    IT:{f:"🇮🇹",n:"İtalya",cons:"İtalya Konsolosluğu · iDATA",sch:true,extra:[]},
    ES:{f:"🇪🇸",n:"İspanya",cons:"İspanya Konsolosluğu · BLS",sch:true,extra:[]},
    NL:{f:"🇳🇱",n:"Hollanda",cons:"Hollanda Konsolosluğu · VFS Global",sch:true,extra:[]},
    AT:{f:"🇦🇹",n:"Avusturya",cons:"Avusturya Konsolosluğu · VFS",sch:true,extra:[]},
    BE:{f:"🇧🇪",n:"Belçika",cons:"Belçika Konsolosluğu · TLScontact",sch:true,extra:[]},
    GR:{f:"🇬🇷",n:"Yunanistan",cons:"Yunanistan Konsolosluğu",sch:true,extra:[]},
    CH:{f:"🇨🇭",n:"İsviçre",cons:"İsviçre Konsolosluğu · TLScontact",sch:true,extra:["İsviçre Schengen üyesidir; aynı Schengen formu geçerlidir."]},
    PT:{f:"🇵🇹",n:"Portekiz",cons:"Portekiz Konsolosluğu · VFS",sch:true,extra:[]},
    GB:{f:"🇬🇧",n:"İngiltere",cons:"UK · VFS Global",sch:false,ob:OB_GB,extra:["Ana başvuru formu ONLINE doldurulur (gov.uk / VFS). Buradaki niyet dilekçesi ve seyahat planı dosyanızı güçlendirir."]},
    US:{f:"🇺🇸",n:"ABD",cons:"ABD Konsolosluğu",sch:false,ob:OB_US,extra:["Ana başvuru formu DS-160 ONLINE doldurulur (ceac.state.gov). Buradaki niyet dilekçesi ve seyahat planı mülakat dosyanızı destekler."]}
  };

  function uil(){ try{ return localStorage.getItem("ch_uilang")||"tr"; }catch(e){ return "tr"; } }
  function obKey(cc,k){ return "ch_vdos_"+cc+"_"+k; }
  function obDone(cc,k){ try{ return localStorage.getItem(obKey(cc,k))==="1"; }catch(e){ return false; } }
  function obToggle(cc,k){ try{ localStorage.setItem(obKey(cc,k), obDone(cc,k)?"0":"1"); }catch(e){} }
  function fillList(cc){ return DEST[cc].sch?FILL_SCH:FILL_NOSCH; }
  function obList(cc){ return DEST[cc].ob||OB_SCH; }
  function vsf(){ return window.chVSF||null; }

  function panel(){
    var p=document.getElementById("pnl-vdos");
    if(!p){ p=document.createElement("div"); p.id="pnl-vdos"; document.body.appendChild(p); }
    return p;
  }
  function top(t){ return '<div class="vd-top"><button class="vd-back" id="vd-back">‹</button><div class="vd-ttl">'+esc(t)+'</div></div>'; }
  function open(){ panel().classList.add("on"); renderCat(); }
  function close(){ panel().classList.remove("on"); }
  window.chOpenVDos=open;

  function renderCat(){
    var p=panel();
    p.innerHTML=top("🗂️ Vize Dosyası — Hedef Ülke Seçin")
     +'<div class="vd-scroll"><div class="vd-wrap">'
     +'<div class="vd-note" style="font-size:12px">Gideceğiniz ülkeyi seçin. Gereken tüm belgeler listelenir; doldurdukça "Düzenlendi ✓" olur, sonunda hepsini tek PDF olarak alabilirsiniz.</div>'
     +'<div class="vd-grid">'+Object.keys(DEST).map(function(cc){var d=DEST[cc];
        return '<div class="vd-cc" data-cc="'+cc+'"><div class="vd-flag">'+d.f+'</div><div class="vd-cn">'+esc(d.n)+'</div><div class="vd-cs">'+(d.sch?"Schengen":"Ulusal vize")+'</div></div>';
      }).join("")+'</div></div></div>';
    document.getElementById("vd-back").addEventListener("click",function(){ close(); });
    p.querySelectorAll(".vd-cc").forEach(function(c){
      c.addEventListener("click",function(){ renderCountry(c.getAttribute("data-cc")); });
    });
  }

  function renderCountry(cc){
    var d=DEST[cc], p=panel(), V=vsf();
    var fl=fillList(cc), ob=obList(cc);
    var fillDone=0; fl.forEach(function(f){ if(V&&V.isDone(f.k)) fillDone++; });
    var obDoneN=0; ob.forEach(function(o){ if(obDone(cc,o.k)) obDoneN++; });
    var tot=fl.length+ob.length, done=fillDone+obDoneN;
    var pct=Math.round(done/tot*100);

    var h=top(d.f+" "+d.n+" — Vize Dosyası")
     +'<div class="vd-scroll"><div class="vd-wrap">'
     +'<div style="font-size:12px;color:var(--t3,#99a)">'+esc(d.cons)+'</div>'
     +'<div class="vd-prog"><i style="width:'+pct+'%"></i></div>'
     +'<div style="font-size:12.5px;color:#fff;font-weight:600">'+done+' / '+tot+' hazır <span style="color:var(--t3,#99a);font-weight:400">('+pct+'%)</span></div>'
     +'<div class="vd-sec">✍️ Doldurulacak Belgeler</div>';
    fl.forEach(function(f){
      var ok=V&&V.isDone(f.k);
      h+='<div class="vd-row'+(ok?' done':'')+'">'
        +'<div class="vd-info"><div class="vd-n">'+esc(f.n)+(f.req?'':' <span style="color:var(--t3,#99a);font-weight:400">· opsiyonel</span>')+'</div>'
        +'<div class="vd-d">'+(ok?'Bu belge dolduruldu.':'Henüz doldurulmadı — "Düzenle" ile açın.')+'</div></div>'
        +'<span class="vd-st '+(ok?'ok':'no')+'">'+(ok?'Düzenlendi ✓':'Düzenlenmedi')+'</span>'
        +'<button class="vd-btn'+(ok?'':' p')+'" data-open="'+f.k+'">'+(ok?'Düzenle':'Doldur')+'</button>'
        +(ok?'<button class="vd-btn" data-one="'+f.k+'">İndir</button>':'')
        +'</div>';
    });
    h+='<div class="vd-sec">📎 Temin Edilecek Belgeler <span style="font-weight:400;color:var(--t3,#99a);text-transform:none">(hazır olanları işaretleyin)</span></div>';
    ob.forEach(function(o){
      var ok=obDone(cc,o.k);
      h+='<div class="vd-row'+(ok?' done':'')+'" data-obrow="'+o.k+'" style="cursor:pointer">'
        +'<div class="vd-chk'+(ok?' on':'')+'">'+(ok?'✓':'')+'</div>'
        +'<div class="vd-info"><div class="vd-n">'+esc(o.t)+'</div>'+(o.d?'<div class="vd-d">'+esc(o.d)+'</div>':'')+'</div>'
        +'<span class="vd-st '+(ok?'ok':'no')+'">'+(ok?'Hazır ✓':'Eksik')+'</span>'
        +'</div>';
    });
    if(d.extra&&d.extra.length){ h+='<div class="vd-note">ℹ️ '+d.extra.map(esc).join('<br>ℹ️ ')+'</div>'; }
    h+='<div class="vd-actions">'
      +'<button class="vd-btn p" id="vd-allpdf">📄 Doldurulanları tek PDF indir</button>'
      +'</div>'
      +'<div class="vd-note">Makamların bastığı resmî belgeler (ör. Verpflichtungserklärung) burada üretilmez — ilgili makamdan alınır. PDF indirmek için giriş + plan gerekir.</div>'
      +'</div></div>';
    p.innerHTML=h;

    document.getElementById("vd-back").addEventListener("click",renderCat);
    p.querySelectorAll("[data-open]").forEach(function(b){
      b.addEventListener("click",function(){ var V2=vsf(); if(V2){ V2.openForm(b.getAttribute("data-open")); } });
    });
    p.querySelectorAll("[data-one]").forEach(function(b){
      b.addEventListener("click",function(){ onePdf(b.getAttribute("data-one")); });
    });
    p.querySelectorAll("[data-obrow]").forEach(function(row){
      row.addEventListener("click",function(){ obToggle(cc,row.getAttribute("data-obrow")); renderCountry(cc); });
    });
    document.getElementById("vd-allpdf").addEventListener("click",function(){ allPdf(cc); });
  }

  function output(html){
    if(window.chDlGate&&!window.chDlGate()) return false;
    try{ if(window.chPrintDoc){ window.chPrintDoc(html); return true; } }catch(e){}
    var w=window.open("","_blank"); if(!w){ alert("Popup engellendi"); return false; }
    w.document.open(); w.document.write(html); w.document.close(); return true;
  }
  function onePdf(k){
    var V=vsf(); if(!V) return;
    try{ var pages=V.buildPages(k); if(!pages||!pages.length){ alert("Bu belge boş."); return; } output(V.makePdfHtml(pages)); }catch(e){}
  }
  function allPdf(cc){
    var V=vsf(); if(!V) return;
    var pages=[];
    fillList(cc).forEach(function(f){ if(V.isDone(f.k)){ try{ pages=pages.concat(V.buildPages(f.k)); }catch(e){} } });
    if(!pages.length){ alert("Önce en az bir belge doldurun."); return; }
    output(V.makePdfHtml(pages));
  }

  /* TR vize paneline giris karti */
  function inject(){
    try{
      var p=document.getElementById("pnl-trvisa"); if(!p) return;
      var wrap=p.querySelector(".trv-wrap"); if(!wrap) return;
      if(wrap.querySelector("#vdos-entry")) return;
      var e=document.createElement("div"); e.id="vdos-entry"; e.className="vd-entry";
      e.innerHTML='<div style="font-size:14px;font-weight:800;color:#fff">🗂️ Vize Dosyası (Ülkeye Göre)</div>'
        +'<div style="font-size:11.5px;color:var(--t3,#99a);margin-top:4px">Hedef ülke seçin → gereken tüm belgeler listelenir → doldurdukça "Düzenlendi ✓" olur → hepsini tek PDF olarak alın.</div>';
      e.addEventListener("click",open);
      wrap.insertBefore(e, wrap.firstChild);
    }catch(e){}
  }
  setInterval(inject, 900);
})();}catch(e){}
</script>
HTML;
$pos = strrpos($src,'</body>');
if ($pos===false) { echo "  ✗ [2] </body> yok\n"; $fail[]=2; }
else { $src = substr($src,0,$pos).$bundle."\n".substr($src,$pos); echo "  ✓ [2] Vize Dosyasi sayfasi + giris karti eklendi\n"; }

if ($fail) { echo "\nHATA: adimlar eslesmedi: ".implode(', ',$fail)." — index.php DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'vd').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-vdossier-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_VISA_DOSSIER uygulandi. TR vize panelinde 'Vize Dosyasi' — 12 ulke, durum takibi, birlesik/tekil PDF.\n";
