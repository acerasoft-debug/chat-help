<?php
/**
 * ChatHelp — apply-vize-asistan (CH_VIZE_ASISTAN) — premium rehberli Vize Asistani
 *  Serbest metin yerine: ULKE + VIZE TIPI + TARIH secici (takvim). Sonuc: (a)
 *  kurumsal belge(ler) — niyet/davet/isveren mektubu, hedef ulke dilinde, makePDF
 *  ile A4; (b) toplanacak evrak CHECKLIST'i (ulke+tipe ozel, "burada uret" /
 *  "resmi kaynaktan al"). Schengen bloku + ABD/UK/Kanada/Avustralya.
 *  EKLEMELI: </body> oncesi tek modul; window.chVizeAsistan() + launcher.
 * KULLANIM: pull2.php?key=...&files=apply-vize-asistan.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-vize-asistan BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_VIZE_ASISTAN')!==false) exit("Zaten ekli (CH_VIZE_ASISTAN).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-vize-css">
#vz-ov{position:fixed;inset:0;z-index:2147483000;background:linear-gradient(180deg,#0b0b16,#07070e);display:none;flex-direction:column;font-family:'Inter',system-ui,sans-serif;color:#f2f2fb}
#vz-ov.on{display:flex}
#vz-ov .vz-top{display:flex;align-items:center;gap:12px;padding:14px 16px;border-bottom:1px solid rgba(255,255,255,.08);background:#0d0d1c;position:sticky;top:0}
#vz-ov .vz-top h2{font-size:16px;font-weight:800;flex:1}
#vz-ov .vz-top h2 em{font-style:normal;color:#e8c874}
.vz-x{background:#1a1a30;border:1px solid rgba(255,255,255,.12);color:#c8c8e0;border-radius:9px;padding:8px 12px;font-size:13px;cursor:pointer}
#vz-ov .vz-body{flex:1;overflow-y:auto;padding:20px 16px 60px;max-width:760px;width:100%;margin:0 auto}
.vz-steps{display:flex;gap:6px;margin-bottom:20px}
.vz-steps .s{flex:1;height:4px;border-radius:3px;background:rgba(255,255,255,.1)}
.vz-steps .s.on{background:linear-gradient(90deg,#d4a84a,#f0c860)}
.vz-h{font-size:20px;font-weight:800;margin-bottom:4px}
.vz-sub{font-size:13px;color:#9a9ab0;margin-bottom:18px}
.vz-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px}
.vz-card{background:#12122a;border:1.5px solid rgba(255,255,255,.09);border-radius:14px;padding:16px 12px;text-align:center;cursor:pointer;transition:transform .12s,border-color .15s}
.vz-card:hover{transform:translateY(-2px);border-color:rgba(212,168,74,.5)}
.vz-card.sel{border-color:#e8c874;background:rgba(212,168,74,.1)}
.vz-card .ic{font-size:30px;display:block;margin-bottom:6px}
.vz-card .nm{font-size:13px;font-weight:700}
.vz-card .sb{font-size:10.5px;color:#8e8e9c;margin-top:2px}
.vz-chips{display:flex;flex-wrap:wrap;gap:10px}
.vz-chip{background:#12122a;border:1.5px solid rgba(255,255,255,.09);border-radius:12px;padding:12px 16px;font-size:13.5px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:8px}
.vz-chip:hover{border-color:rgba(212,168,74,.5)}
.vz-chip.sel{border-color:#e8c874;background:rgba(212,168,74,.1)}
.vz-field{margin-bottom:16px}
.vz-field label{display:block;font-size:11px;font-weight:800;color:#8e8e9c;text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px}
.vz-in{width:100%;background:#12122a;border:1.5px solid rgba(255,255,255,.12);border-radius:11px;padding:13px;color:#fff;font-size:15px;font-family:inherit;outline:none}
.vz-in:focus{border-color:rgba(212,168,74,.6)}
.vz-2col{display:flex;gap:12px}.vz-2col>div{flex:1}
.vz-nav{display:flex;gap:10px;margin-top:24px}
.vz-btn{flex:1;background:linear-gradient(135deg,#d4a84a,#ecc060);color:#07070e;border:none;border-radius:12px;padding:15px;font-size:15px;font-weight:800;cursor:pointer;font-family:inherit}
.vz-btn.ghost{background:#1a1a30;color:#c8c8e0;border:1px solid rgba(255,255,255,.12);flex:0 0 auto;padding:15px 20px}
.vz-btn:disabled{opacity:.5}
.vz-sec-t{font-size:15px;font-weight:800;color:#e8c874;margin:22px 0 10px;display:flex;align-items:center;gap:8px}
.vz-gen{background:#12122a;border:1px solid rgba(212,168,74,.25);border-radius:12px;padding:14px;margin-bottom:10px;display:flex;align-items:center;gap:12px}
.vz-gen .t{flex:1}.vz-gen .t .n{font-size:14px;font-weight:700}.vz-gen .t .d{font-size:11.5px;color:#8e8e9c;margin-top:2px}
.vz-gen button{background:linear-gradient(135deg,#d4a84a,#ecc060);color:#07070e;border:none;border-radius:9px;padding:10px 14px;font-size:12.5px;font-weight:800;cursor:pointer;white-space:nowrap;font-family:inherit}
.vz-cl-grp{margin-bottom:16px}
.vz-cl-grp .gt{font-weight:800;color:#d4a84a;font-size:13px;margin-bottom:8px}
.vz-cl-it{display:flex;align-items:flex-start;gap:10px;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.06)}
.vz-cl-it .tx{flex:1;font-size:13px;color:#e7e7ef}
.vz-cl-it .nt{font-size:11px;color:#8e8e9c;margin-top:2px}
.vz-badge{font-size:10px;font-weight:800;padding:2px 8px;border-radius:100px;white-space:nowrap}
.vz-badge.ch{color:#1b1b1e;background:linear-gradient(135deg,#d4a84a,#f0c860)}
.vz-badge.of{color:#cfcfdd;background:rgba(255,255,255,.08)}
.vz-mini{margin-left:6px;background:rgba(212,168,74,.14);border:1px solid rgba(212,168,74,.4);color:#e8c874;border-radius:8px;padding:6px 9px;font-size:11px;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap}
.vz-launch{display:flex;align-items:center;gap:12px;background:linear-gradient(135deg,rgba(212,168,74,.16),rgba(212,168,74,.06));border:1.5px solid rgba(212,168,74,.4);border-radius:14px;padding:14px 16px;margin:0 0 14px;cursor:pointer}
.vz-launch .li{font-size:26px}.vz-launch .lt{flex:1}.vz-launch .lt .a{font-size:14px;font-weight:800;color:#f0d488}.vz-launch .lt .b{font-size:11.5px;color:#b8b8cc;margin-top:2px}
.vz-launch .lg{font-size:20px;color:#e8c874}
</style>
<script id="ch-vize-js">
try{(function(){
  var API=function(){ return (typeof window.API!=='undefined'&&window.API)?window.API:'api.php'; };
  var UIL=function(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } };
  var DEST=[
    {k:'schengen',n:'Schengen (genel)',ic:'🇪🇺',ll:'en',cc:'DE',sb:'DE·FR·IT·ES·NL…',sch:1},
    {k:'de',n:'Almanya',ic:'🇩🇪',ll:'de',cc:'DE',sch:1},
    {k:'fr',n:'Fransa',ic:'🇫🇷',ll:'fr',cc:'FR',sch:1},
    {k:'it',n:'İtalya',ic:'🇮🇹',ll:'it',cc:'IT',sch:1},
    {k:'es',n:'İspanya',ic:'🇪🇸',ll:'es',cc:'ES',sch:1},
    {k:'nl',n:'Hollanda',ic:'🇳🇱',ll:'en',cc:'NL',sch:1},
    {k:'gr',n:'Yunanistan',ic:'🇬🇷',ll:'en',cc:'GR',sch:1},
    {k:'usa',n:'ABD',ic:'🇺🇸',ll:'en',cc:'US'},
    {k:'uk',n:'İngiltere',ic:'🇬🇧',ll:'en',cc:'GB'},
    {k:'ca',n:'Kanada',ic:'🇨🇦',ll:'en',cc:'CA'},
    {k:'au',n:'Avustralya',ic:'🇦🇺',ll:'en',cc:'AU'}
  ];
  var TYPE=[
    {k:'tourism',n:'Turizm',ic:'🏖️'},
    {k:'family',n:'Aile / Arkadaş ziyareti',ic:'👪'},
    {k:'business',n:'İş',ic:'💼'},
    {k:'study',n:'Öğrenci / Eğitim',ic:'🎓'},
    {k:'transit',n:'Transit',ic:'✈️'}
  ];
  var LN={de:'Deutsch',en:'English',fr:'Français',it:'Italiano',es:'Español',nl:'Nederlands'};
  var GENMETA={
    cover:{n:'Niyet / Başvuru Mektubu (cover letter)',d:'Seyahat amacı, tarihler, dönüş niyeti'},
    invitation:{n:'Davet Mektubu (destekleyici)',d:'Davet eden kişinin imzalayacağı mektup'},
    employer:{n:'İşveren Görevlendirme / İzin Yazısı',d:'İşverenin imzalayacağı yazı'},
    itinerary:{n:'Günlük Seyahat Planı (itinerary)',d:'Gün gün tarih, şehir, aktivite'}
  };
  var VZ={dest:null,type:null,from:'',to:'',city:''};

  function dObj(){ for(var i=0;i<DEST.length;i++) if(DEST[i].k===VZ.dest) return DEST[i]; return null; }
  function tObj(){ for(var i=0;i<TYPE.length;i++) if(TYPE[i].k===VZ.type) return TYPE[i]; return null; }
  function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
  function strip(s){ return String(s||'').replace(/```[a-z]*|```/g,'').replace(/\[\[DOC\]\][\s\S]*?\[\[\/DOC\]\]/g,'').replace(/\[\[\/?DOC\]\]/g,'').trim(); }
  function fill(t){ try{ var P=window.P||{}; var m={'[VORNAME]':P.f1||'','[NACHNAME]':P.f2||'','[ADRESSE]':P.f3||'','[ORT]':P.f4||'','[TELEFON]':P.f5||'','[EMAIL]':P.f7||''}; Object.keys(m).forEach(function(k){ if(m[k]) t=t.split(k).join(m[k]); }); }catch(e){} return t; }

  function checklist(){
    var d=dObj(), ty=VZ.type, G=[];
    if(d&&d.sch){
      G.push({g:'Kimlik & Seyahat',it:[
        {t:'Geçerli pasaport (seyahat sonrası en az 3 ay geçerli, 2 boş sayfa)',s:'of',nt:'Varsa eski pasaportları da ekleyin'},
        {t:'Biyometrik vesikalık (2 adet, 35×45 mm, son 6 ay)',s:'of',nt:'Fotoğrafçıya "Schengen/biyometrik" deyin'},
        {t:'Kimlik / nüfus cüzdanı fotokopisi',s:'of'}
      ]});
      G.push({g:'Başvuru & Randevu',it:[
        {t:'Schengen vize başvuru formu (eksiksiz, imzalı)',s:'of',nt:'Konsolosluk / VFS Global / iDATA sitesinden'},
        {t:'Randevu onayı (VFS / iDATA / konsolosluk)',s:'of'},
        {t:'Niyet / başvuru mektubu (cover letter)',s:'ch',g:'cover',nt:'Seyahat amacı ve dönüş niyetini açıklar'}
      ]});
      G.push({g:'Mali Belgeler',it:[
        {t:'Son 3 aylık banka hesap dökümü (kaşeli/imzalı)',s:'of'},
        {t:'Maaş bordroları (son 3 ay) veya gelir belgesi',s:'of'},
        {t:'Seyahat sağlık sigortası — min. 30.000 € teminat, tüm Schengen',s:'of',nt:'ZORUNLU; sigorta acentesinden'}
      ]});
      G.push({g:'Seyahat Planı',it:[
        {t:'Gidiş-dönüş uçak rezervasyonu (bilet almadan)',s:'of',nt:'Onaylı rezervasyon yeterli'},
        {t:'Konaklama (otel rezervasyonu / davet)',s:'of'},
        {t:'Günlük seyahat planı (itinerary)',s:'ch',g:'itinerary'}
      ]});
      if(ty==='family') G.push({g:'Aile / Arkadaş Ziyareti',it:[
        {t:'Resmi davet / Verpflichtungserklärung',s:'of',nt:'Davet eden, kendi ülkesindeki makamdan alır'},
        {t:'Destekleyici davet mektubu',s:'ch',g:'invitation'},
        {t:'Davet edenin kimlik/ikamet + gelir belgesi',s:'of'}
      ]});
      else if(ty==='business') G.push({g:'İş Seyahati',it:[
        {t:'Karşı firma davet mektubu',s:'of'},
        {t:'Kendi firmanızın görevlendirme yazısı',s:'ch',g:'employer'},
        {t:'Ticaret sicil gazetesi / vergi levhası',s:'of'}
      ]});
      else if(ty==='study') G.push({g:'Eğitim',it:[
        {t:'Kabul / kayıt belgesi (okul / üniversite)',s:'of'},
        {t:'Öğrenci belgesi',s:'of'}
      ]});
      else G.push({g:'İş / Durum Belgesi',it:[
        {t:'Çalışan: SGK hizmet dökümü + işveren izin yazısı',s:'of'},
        {t:'İşveren görevlendirme / izin yazısı',s:'ch',g:'employer'}
      ]});
    } else if(d&&d.k==='usa'){
      G.push({g:'ABD (B1/B2)',it:[
        {t:'DS-160 onay sayfası (barkodlu)',s:'of',nt:'ceac.state.gov'},
        {t:'MRV vize harcı ödeme dekontu',s:'of'},
        {t:'İki randevu: OFC (parmak izi) + konsolosluk mülakatı',s:'of'},
        {t:'Vesikalık (5×5 cm, beyaz fon)',s:'of'},
        {t:'Geçerli pasaport + eski pasaportlar',s:'of'}
      ]});
      G.push({g:'Destekleyici',it:[
        {t:'Son 3-6 ay banka dökümü / mali durum',s:'of'},
        {t:'İşveren yazısı / SGK / ticaret sicil',s:'of'},
        {t:'Türkiye’ye bağ (aile, iş, mülk) — niyet mektubu',s:'ch',g:'cover',nt:'Dönüş niyetini güçlendirir'}
      ]});
      if(ty==='family') G.push({g:'Ziyaret',it:[
        {t:'ABD’deki davet edenin mektubu + statü (I-797 / yeşil kart / pasaport)',s:'of'},
        {t:'Destekleyici davet mektubu',s:'ch',g:'invitation'}
      ]});
    } else if(d){
      var nm=d.n, portal=(d.k==='uk'?'gov.uk':d.k==='ca'?'IRCC':'ImmiAccount');
      G.push({g:nm+' başvurusu',it:[
        {t:'Online başvuru ('+portal+') + ücret ödemesi',s:'of'},
        (d.k!=='au'?{t:'Biyometrik randevu (VFS / TLS)',s:'of'}:{t:'Dijital biyometrik vesikalık',s:'of'}),
        {t:'Geçerli pasaport + vesikalık',s:'of'},
        {t:'Son '+(d.k==='uk'?'6':'3-6')+' ay banka dökümü / mali durum',s:'of'},
        {t:'İşveren / okul belgesi + SGK hizmet dökümü',s:'of'},
        (d.k==='au'?{t:'Seyahat sağlık sigortası (önerilir)',s:'of'}:null),
        {t:'Niyet mektubu (purpose of travel)',s:'ch',g:'cover'}
      ].filter(Boolean)});
      if(ty==='family') G.push({g:'Ziyaret',it:[
        {t:'Davet edenin mektubu + statü / konaklama belgesi',s:'of'},
        {t:'Destekleyici davet mektubu',s:'ch',g:'invitation'}
      ]});
    }
    return G;
  }
  function genList(){
    var seen={}, out=[], G=checklist();
    G.forEach(function(grp){ grp.it.forEach(function(it){ if(it.s==='ch'&&it.g&&!seen[it.g]){ seen[it.g]=1; out.push(it.g); } }); });
    return out;
  }

  function ov(){ var o=document.getElementById('vz-ov'); if(!o){ o=document.createElement('div'); o.id='vz-ov'; document.body.appendChild(o); } return o; }
  function close(){ var o=document.getElementById('vz-ov'); if(o) o.classList.remove('on'); }
  function top(t){ return '<div class="vz-top"><h2>🛂 Vize <em>Asistanı</em></h2><button class="vz-x" onclick="window.__vzClose()">✕ Kapat</button></div>'; }
  function stepsBar(n){ var h='<div class="vz-steps">'; for(var i=1;i<=4;i++) h+='<div class="s'+(i<=n?' on':'')+'"></div>'; return h+'</div>'; }

  function render(){
    var o=ov(); o.classList.add('on');
    var step = !VZ.dest?1 : !VZ.type?2 : 3;
  }
  var STEP=1;
  function draw(){
    var o=ov(); o.classList.add('on'); var h=top();
    h+='<div class="vz-body">'+stepsBar(STEP);
    if(STEP===1){
      h+='<div class="vz-h">Nereye seyahat edeceksiniz?</div><div class="vz-sub">Hedef ülkeyi seçin — gerekli belgeler ve doğru başvuru ona göre hazırlanır.</div><div class="vz-grid">';
      DEST.forEach(function(d){ h+='<div class="vz-card'+(VZ.dest===d.k?' sel':'')+'" onclick="window.__vzPick(\''+d.k+'\')"><span class="ic">'+d.ic+'</span><span class="nm">'+esc(d.n)+'</span>'+(d.sb?'<span class="sb">'+esc(d.sb)+'</span>':'')+'</div>'; });
      h+='</div>';
    } else if(STEP===2){
      h+='<div class="vz-h">Vize tipi nedir?</div><div class="vz-sub">'+esc(dObj().ic+' '+dObj().n)+' için seyahat amacınız.</div><div class="vz-chips">';
      TYPE.forEach(function(t){ h+='<div class="vz-chip'+(VZ.type===t.k?' sel':'')+'" onclick="window.__vzType(\''+t.k+'\')">'+t.ic+' '+esc(t.n)+'</div>'; });
      h+='</div><div class="vz-nav"><button class="vz-btn ghost" onclick="window.__vzBack()">←</button></div>';
    } else if(STEP===3){
      h+='<div class="vz-h">Seyahat tarihleri</div><div class="vz-sub">Takvimden gidiş ve dönüş tarihini seçin.</div>';
      h+='<div class="vz-2col"><div class="vz-field"><label>Gidiş</label><input type="date" class="vz-in" id="vz-from" value="'+esc(VZ.from)+'"></div><div class="vz-field"><label>Dönüş</label><input type="date" class="vz-in" id="vz-to" value="'+esc(VZ.to)+'"></div></div>';
      h+='<div class="vz-field"><label>Şehir(ler) (opsiyonel)</label><input type="text" class="vz-in" id="vz-city" placeholder="ör. Berlin, München" value="'+esc(VZ.city)+'"></div>';
      h+='<div class="vz-nav"><button class="vz-btn ghost" onclick="window.__vzBack()">←</button><button class="vz-btn" onclick="window.__vzResult()">Belgelerimi hazırla →</button></div>';
    } else if(STEP===4){
      var d=dObj(), gl=genList();
      h+='<div class="vz-h">'+esc(d.ic+' '+d.n)+' · '+esc(tObj().n)+'</div><div class="vz-sub">'+esc((VZ.from||'?')+' → '+(VZ.to||'?'))+(VZ.city?(' · '+esc(VZ.city)):'')+'</div>';
      h+='<div class="vz-sec-t">📄 Hazırlanabilecek Belgeler</div>';
      h+='<div class="vz-sub" style="margin-top:-4px">Aşağıdaki belgeler '+esc(LN[d.ll]||'İngilizce')+' dilinde, kurumsal A4 formatta hazırlanır.</div>';
      if(!gl.length) h+='<div class="vz-sub">Bu seçim için otomatik mektup yok; aşağıdaki listeyi takip edin.</div>';
      gl.forEach(function(g){ var m=GENMETA[g]||{n:g,d:''}; h+='<div class="vz-gen"><div class="t"><div class="n">'+esc(m.n)+'</div><div class="d">'+esc(m.d)+'</div></div><button onclick="window.__vzGen(\''+g+'\',this)">Hazırla</button></div>'; });
      h+='<div class="vz-sec-t">✅ Toplamanız Gereken Evraklar</div>';
      h+='<div class="vz-sub" style="margin-top:-4px">Liste geneldir; başvuracağınız konsolosluğun / VFS sayfasının güncel listesini de teyit edin.</div>';
      checklist().forEach(function(grp){
        h+='<div class="vz-cl-grp"><div class="gt">'+esc(grp.g)+'</div>';
        grp.it.forEach(function(it){
          h+='<div class="vz-cl-it"><div class="tx">'+esc(it.t)+(it.nt?'<div class="nt">'+esc(it.nt)+'</div>':'')+'</div>';
          h+='<span class="vz-badge '+(it.s==='ch'?'ch">ChatHelp ile oluştur':'of">Resmi kaynaktan alın')+'</span>';
          if(it.s==='ch'&&it.g) h+='<button class="vz-mini" onclick="window.__vzGen(\''+it.g+'\',this)">Hazırla</button>';
          h+='</div>';
        });
        h+='</div>';
      });
      h+='<div class="vz-nav"><button class="vz-btn ghost" onclick="window.__vzBack()">←</button><button class="vz-btn" onclick="window.__vzClose()">Bitti</button></div>';
    }
    h+='</div>';
    o.innerHTML=h;
    if(STEP===3){ var f=document.getElementById('vz-from'),t=document.getElementById('vz-to'),c=document.getElementById('vz-city');
      if(f) f.onchange=function(){VZ.from=f.value;}; if(t) t.onchange=function(){VZ.to=t.value;}; if(c) c.oninput=function(){VZ.city=c.value;}; }
  }

  window.__vzClose=function(){ close(); };
  window.__vzPick=function(k){ VZ.dest=k; STEP=2; draw(); };
  window.__vzType=function(k){ VZ.type=k; STEP=3; draw(); };
  window.__vzBack=function(){ if(STEP>1) STEP--; draw(); };
  window.__vzResult=function(){
    var f=document.getElementById('vz-from'),t=document.getElementById('vz-to'),c=document.getElementById('vz-city');
    if(f)VZ.from=f.value; if(t)VZ.to=t.value; if(c)VZ.city=c.value;
    if(!VZ.from||!VZ.to){ alert('Lütfen gidiş ve dönüş tarihini seçin.'); return; }
    STEP=4; draw();
  };
  function prompt(gen){
    var d=dObj(), t=tObj(), ln=LN[d.ll]||'English';
    var who='[VORNAME] [NACHNAME], ikamet adresi: [ADRESSE], [ORT]';
    var base='Vize: '+d.n+' ('+(t?t.n:'')+'), seyahat tarihleri '+(VZ.from||'?')+' – '+(VZ.to||'?')+(VZ.city?(', şehir(ler): '+VZ.city):'')+'. Başvuran: '+who+'.';
    if(gen==='cover') return 'Erstelle ein formelles, überzeugendes Motivations-/Begleitschreiben (cover letter) für einen Visumantrag. '+base+' Erwähne den Reisezweck, die genauen Daten, die Finanzierung, die Reiseversicherung und die feste Rückkehrabsicht (Bindungen zum Heimatland: Familie, Arbeit, Eigentum). Adressiere es an das zuständige Konsulat. Schreibe den vollständigen Brief ausschließlich auf '+ln+', mit Datum und Unterschriftszeile. Gib NUR den Brieftext aus, ohne Erklärungen.';
    if(gen==='invitation') return 'Erstelle ein formelles Einladungsschreiben (Davet Mektubu), das die EINLADENDE Person im Zielland unterschreibt, zur Unterstützung dieses Visumantrags. '+base+' Die einladende Person bestätigt Beziehung, Unterkunft und ggf. Kostenübernahme sowie die fristgerechte Rückkehr des Gastes. Schreibe ausschließlich auf '+ln+'. Gib NUR den Brieftext mit Unterschriftszeile für den Gastgeber aus.';
    if(gen==='employer') return 'Erstelle ein formelles Arbeitgeber-Schreiben, das der ARBEITGEBER unterschreibt: bestätigt Anstellung, Position, Gehalt, den genehmigten Urlaub für den Reisezeitraum und die Rückkehr an den Arbeitsplatz. '+base+' Schreibe ausschließlich auf '+ln+'. Gib NUR den Brieftext aus, mit Platzhalter für Firmenkopf und Unterschriftszeile.';
    if(gen==='itinerary') return 'Erstelle einen übersichtlichen Tages-Reiseplan (itinerary) für diesen Visumantrag. '+base+' Liste für jeden Tag Datum, Ort/Stadt und geplante Aktivität. Schreibe ausschließlich auf '+ln+'. Gib NUR den Reiseplan aus.';
    return base;
  }
  window.__vzGen=function(gen,btn){
    var d=dObj(); if(!d) return;
    var old=btn.textContent; btn.textContent='⏳ Hazırlanıyor…'; btn.disabled=true;
    fetch(API()+'?action=aichat',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({message:prompt(gen),history:[],provider:'claude',lang:UIL(),country:d.cc})})
     .then(function(r){ return r.json(); })
     .then(function(j){
        btn.textContent=old; btn.disabled=false;
        var txt=(j&&j.reply)?fill(strip(j.reply)):'';
        if(!txt){ alert('Belge üretilemedi, lütfen tekrar deneyin.'); return; }
        var name=(GENMETA[gen]&&GENMETA[gen].n)?GENMETA[gen].n:'Vize Belgesi';
        if(typeof window.makePDF==='function'){ try{ window.makePDF(txt,name,d.cc); }catch(e){ alert(txt); } }
        else alert(txt);
     })
     .catch(function(){ btn.textContent=old; btn.disabled=false; alert('Bağlantı hatası. İnternetinizi kontrol edip tekrar deneyin.'); });
  };
  window.chVizeAsistan=function(destK){ VZ={dest:destK||null,type:null,from:'',to:'',city:''}; STEP=destK?2:1; draw(); };

  /* ── launcher: sidebar tepesi + Schengen checklist ustu ── */
  function launchHtml(){ return '<div class="vz-launch" onclick="window.chVizeAsistan()"><span class="li">🛂</span><span class="lt"><span class="a">Vize Asistanı</span><span class="b">Ülke ve tarih seçin — belgenizi hazırlayın, evrak listenizi görün</span></span><span class="lg">›</span></div>'; }
  function injectLaunchers(){
    try{
      /* Schengen checklist ustu */
      if(!document.getElementById('vz-launch-cl')){
        var ps=document.querySelectorAll('p'), tgt=null;
        for(var i=0;i<ps.length;i++){ if((ps[i].textContent||'').indexOf('Aşağıdaki liste genel bir Schengen')===0){ tgt=ps[i]; break; } }
        if(tgt&&tgt.parentNode){ var w=document.createElement('div'); w.id='vz-launch-cl'; w.innerHTML=launchHtml(); tgt.parentNode.insertBefore(w,tgt); }
      }
    }catch(e){}
  }
  var n=0; (function loop(){ injectLaunchers(); if(n++<400) setTimeout(loop,900); })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'vz').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }

$bk=@file_put_contents($file.'.bak-vize-'.date('Ymd-His'), $src);
if ($bk===false) echo "  ⚠ yedek yazilamadi — devam\n";
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_VIZE_ASISTAN')===false || strpos($chk,'chVizeAsistan')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_VIZE_ASISTAN diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Vize Asistani hazir. Ac: window.chVizeAsistan()  (veya Schengen checklist ustundeki launcher).\n";
echo "   Akis: ulke → vize tipi → tarih(takvim) → kurumsal belge + evrak checklist.\n";
