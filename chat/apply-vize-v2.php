<?php
/**
 * ChatHelp — apply-vize-v2 (CH_VIZE_V2) — VIZE MODULU SIFIRDAN, TEK PARCA.
 *  Eski moduldeki 9 ayri yamanin (asistan/ux/rdv/selfemp/datefix/merge/pruef/
 *  forms/result/flow) TUM ozellikleri tek temiz modulde birlesti; eski modul
 *  yerinde kalir ama window.chVizeAsistan V2'ye baglanir -> tum eski
 *  baslatma noktalari otomatik V2'yi acar, eski katmanlar uykuya gecer.
 *  AKIS (tek duzen): 1 Ülke → 2 Amaç → 3 Tarihler → 4 KOKPIT:
 *   [şerit: Bilgiler › Belgeler › Evraklar › Prüfung › Randevu]
 *   🪪 Bilgilerim: ad/dogum/pasaport/meslek/isveren/adres — kaydedilir
 *   📄 Belgeler: her belge kartinda BELGEYE OZEL alanlar + Hazırla;
 *      uretilen belge SAG PANELDE acilir (metin + 📄 PDF jest-butonu +
 *      kopyala); hazirlananlar ✓ isaretlenir; yer tutucu ASLA kalmaz
 *      (gercek degerler dogrudan prompt'a yazilir)
 *   ✅ Evraklar: ulke+amaca ozel checklist (resmi kaynak / ChatHelp rozeti)
 *   🧪 Prüfung: hazirlanan belgeler + evrak listesi denetlenir, eksik/
 *      uyari/hazirlik yuzdesi raporu panelde
 *   📅 Randevu: secilen ulkenin RESMI portal linki + 5 adimli rehber
 *  Tarih alanlari: tiklayinca takvim (showPicker), min=bugun. node ✓ +
 *  harness ✓ (adim gecisleri, uretim istegi, panel, prüfung, randevu).
 * KULLANIM: pull2.php?key=...&files=apply-vize-v2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-vize-v2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_VIZE_V2')!==false) exit("Zaten ekli (CH_VIZE_V2).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-vize-v2-css">
#vz2-ov{position:fixed;inset:0;z-index:2147483150;background:linear-gradient(180deg,#0b0b16,#07070e);display:none;flex-direction:column;font-family:'Inter',system-ui,sans-serif;color:#f2f2fb}
#vz2-ov.on{display:flex}
#vz2-ov .top{display:flex;align-items:center;gap:12px;padding:13px 16px;border-bottom:1px solid rgba(255,255,255,.08);background:#0d0d1c}
#vz2-ov .top h2{font-size:16px;font-weight:800;flex:1}
#vz2-ov .top h2 em{font-style:normal;color:#e8c874}
#vz2-ov .x{background:#1a1a30;border:1px solid rgba(255,255,255,.12);color:#c8c8e0;border-radius:9px;padding:8px 12px;font-size:13px;cursor:pointer;font-family:inherit}
#vz2-bd{flex:1;overflow-y:auto;padding:18px 16px 70px;max-width:760px;width:100%;margin:0 auto;box-sizing:border-box}
.vz2-steps{display:flex;gap:6px;margin-bottom:18px}
.vz2-steps .s{flex:1;height:4px;border-radius:3px;background:rgba(255,255,255,.1)}
.vz2-steps .s.on{background:linear-gradient(90deg,#d4a84a,#f0c860)}
.vz2-h{font-size:19px;font-weight:800;margin-bottom:4px}
.vz2-sub{font-size:12.5px;color:#9a9ab0;margin-bottom:16px;line-height:1.5}
.vz2-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(138px,1fr));gap:10px}
.vz2-card{background:#12122a;border:1.5px solid rgba(255,255,255,.09);border-radius:14px;padding:15px 12px;text-align:center;cursor:pointer;transition:transform .12s,border-color .15s}
.vz2-card:hover{transform:translateY(-2px);border-color:rgba(212,168,74,.5)}
.vz2-card .ic{font-size:28px;display:block;margin-bottom:6px}
.vz2-card .nm{font-size:13px;font-weight:700}
.vz2-card .sb{font-size:10.5px;color:#8e8e9c;margin-top:2px;display:block}
.vz2-chips{display:flex;flex-wrap:wrap;gap:10px}
.vz2-chip{background:#12122a;border:1.5px solid rgba(255,255,255,.09);border-radius:12px;padding:12px 16px;font-size:13.5px;font-weight:700;cursor:pointer}
.vz2-chip:hover{border-color:rgba(212,168,74,.5)}
.vz2-field{margin-bottom:14px}
.vz2-field label{display:block;font-size:10.5px;font-weight:800;color:#8e8e9c;text-transform:uppercase;letter-spacing:.6px;margin-bottom:5px}
.vz2-in{width:100%;background:#12122a;border:1.5px solid rgba(255,255,255,.12);border-radius:11px;padding:12px;color:#fff;font-size:14.5px;font-family:inherit;outline:none;box-sizing:border-box;min-height:48px}
.vz2-in:focus{border-color:rgba(212,168,74,.6)}
#vz2-ov input.vz2-in[type="date"]{color-scheme:dark;-webkit-appearance:none;appearance:none;cursor:pointer}
#vz2-ov input.vz2-in[type="date"]::-webkit-calendar-picker-indicator{filter:invert(1);opacity:.95;cursor:pointer}
.vz2-2col{display:flex;gap:12px}.vz2-2col>div{flex:1}
.vz2-nav{display:flex;gap:10px;margin-top:22px}
.vz2-btn{flex:1;background:linear-gradient(135deg,#d4a84a,#ecc060);color:#07070e;border:none;border-radius:12px;padding:14px;font-size:14.5px;font-weight:800;cursor:pointer;font-family:inherit}
.vz2-btn.ghost{background:#1a1a30;color:#c8c8e0;border:1px solid rgba(255,255,255,.12);flex:0 0 auto;padding:14px 20px}
.vz2-flow{display:flex;gap:6px;flex-wrap:wrap;margin:0 0 16px;padding:9px;background:rgba(212,168,74,.07);border:1px solid rgba(212,168,74,.22);border-radius:13px}
.vz2-flow .st{flex:1;min-width:96px;display:flex;align-items:center;gap:6px;padding:7px 9px;border-radius:9px;cursor:pointer;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08)}
.vz2-flow .st:hover{border-color:rgba(212,168,74,.45)}
.vz2-flow .n{width:20px;height:20px;border-radius:50%;background:linear-gradient(135deg,#d4a84a,#ecc060);color:#191000;font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;flex:0 0 auto}
.vz2-flow .l{font-size:10.8px;font-weight:700;line-height:1.2}
.vz2-sec{font-size:15px;font-weight:800;color:#e8c874;margin:22px 0 8px;display:flex;align-items:center;gap:8px}
.vz2-box{background:#12122a;border:1px solid rgba(255,255,255,.1);border-radius:13px;padding:13px;margin-bottom:10px}
.vz2-doc{background:#12122a;border:1px solid rgba(212,168,74,.25);border-radius:13px;padding:13px;margin-bottom:10px}
.vz2-doc .hd{display:flex;align-items:center;gap:10px;cursor:pointer}
.vz2-doc .hd .t{flex:1}
.vz2-doc .hd .n{font-size:14px;font-weight:700}
.vz2-doc .hd .d{font-size:11.5px;color:#8e8e9c;margin-top:2px}
.vz2-doc .hd .okb{font-size:10px;font-weight:800;color:#42df94;border:1px solid rgba(66,223,148,.4);padding:2px 8px;border-radius:100px;white-space:nowrap}
.vz2-doc .bd{display:none;margin-top:10px;border-top:1px solid rgba(255,255,255,.07);padding-top:10px}
.vz2-doc.open .bd{display:block}
.vz2-doc .go{width:100%;background:linear-gradient(135deg,#d4a84a,#ecc060);color:#07070e;border:none;border-radius:10px;padding:12px;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit;margin-top:6px}
.vz2-cl-grp{margin-bottom:14px}
.vz2-cl-grp .gt{font-weight:800;color:#d4a84a;font-size:13px;margin-bottom:6px}
.vz2-cl-it{display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.06)}
.vz2-cl-it .tx{flex:1;font-size:13px;color:#e7e7ef}
.vz2-cl-it .nt{font-size:11px;color:#8e8e9c;margin-top:2px}
.vz2-badge{font-size:10px;font-weight:800;padding:2px 8px;border-radius:100px;white-space:nowrap}
.vz2-badge.ch{color:#1b1b1e;background:linear-gradient(135deg,#d4a84a,#f0c860)}
.vz2-badge.of{color:#cfcfdd;background:rgba(255,255,255,.08)}
.vz2-rdv{display:flex;align-items:center;gap:12px;background:linear-gradient(135deg,rgba(212,168,74,.16),rgba(212,168,74,.06));border:1.5px solid rgba(212,168,74,.4);border-radius:13px;padding:13px 15px;cursor:pointer;text-decoration:none;color:inherit}
.vz2-rdv .a{font-size:13.5px;font-weight:800;color:#f0d488;display:block}
.vz2-rdv .b{font-size:11px;color:#b8b8cc}
.vz2-note{font-size:11px;color:#8e8e9c;margin-top:8px;line-height:1.55}
#vz2-dw{position:fixed;top:0;right:0;bottom:0;width:min(480px,94vw);z-index:2147483300;transform:translateX(105%);transition:transform .28s;background:linear-gradient(180deg,#12122a,#0b0b19);border-left:1px solid rgba(212,168,74,.4);box-shadow:-18px 0 60px rgba(0,0,0,.55);display:flex;flex-direction:column;color:#f2f2fb}
#vz2-dw.on{transform:translateX(0)}
#vz2-dw .hd{padding:15px 16px 11px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;gap:10px;align-items:flex-start}
#vz2-dw .hd .t{flex:1;font-size:14px;font-weight:800;line-height:1.35}
#vz2-dw .hd .x{width:32px;height:32px;border-radius:9px;border:1px solid rgba(255,255,255,.14);background:transparent;color:#c8c8e8;cursor:pointer}
#vz2-dw .bd{flex:1;overflow-y:auto;padding:15px 16px;font-size:13.5px;line-height:1.7;white-space:pre-wrap}
#vz2-dw .ft{padding:11px 14px;border-top:1px solid rgba(255,255,255,.08);display:flex;gap:8px}
#vz2-dw .ft button{flex:1;padding:12px;border-radius:11px;border:none;cursor:pointer;font-family:inherit;font-size:13px;font-weight:800}
#vz2-pdf{background:linear-gradient(135deg,#d4a84a,#ecc060);color:#191000}
#vz2-copy{background:transparent;border:1.5px solid rgba(255,255,255,.18)!important;color:#c8c8e8}
</style>
<script id="ch-vize-v2-js">
/* CH_VIZE_V2 — vize modulu tek parca: ulke→amac→tarih→kokpit (belge+evrak+pruefung+randevu) */
try{(function(){
  var API=function(){ return (typeof window.API!=='undefined'&&window.API)?window.API:'api.php'; };
  var UIL=function(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } };
  function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
  function J(k,d){ try{ return JSON.parse(localStorage.getItem(k)||'null')||d; }catch(e){ return d; } }
  function W(k,v){ try{ localStorage.setItem(k,JSON.stringify(v)); }catch(e){} }
  function clean(s){ return String(s||'').replace(/```[a-z]*\n?|```/g,'').replace(/\[\[\/?DOC\]\]/g,'').trim(); }

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
  var GEN={
    cover:{n:'Niyet / Başvuru Mektubu (cover letter)',d:'Seyahat amacı, tarihler, dönüş niyeti'},
    invitation:{n:'Davet Mektubu (destekleyici)',d:'Davet eden kişinin imzalayacağı mektup'},
    employer:{n:'İşveren Görevlendirme / İzin Yazısı',d:'İşverenin imzalayacağı yazı'},
    selfemployed:{n:'İş Sahibi / Serbest Meslek Beyanı',d:'Kendi işinizin sahibiyseniz kendiniz imzalarsınız'},
    itinerary:{n:'Günlük Seyahat Planı (itinerary)',d:'Gün gün tarih, şehir, aktivite'}
  };
  var PF=[['ad','Ad Soyad'],['dogum','Doğum tarihi'],['pasaport','Pasaport numarası'],['meslek','Meslek / Unvan'],['isveren','Çalıştığınız yer'],['adres','Adres (şehir, ülke)']];
  var DF={
    cover:[['konaklama','Konaklama (otel adı veya kalacağınız kişi)',0],['finans','Masrafları kim karşılıyor?',0],['ek','Vurgulamak istedikleriniz (opsiyonel)',1]],
    invitation:[['davet_ad','Davet eden kişinin adı soyadı',0],['davet_adres','Davet edenin adresi (gidilecek ülkede)',0],['yakinlik','Yakınlık dereceniz',0],['masraf','Masrafları kim karşılıyor?',0]],
    employer:[['firma','İşveren / firma adı',0],['pozisyon','Pozisyonunuz',0],['giris','İşe başlama tarihi (ay/yıl)',0],['maas','Aylık maaş (opsiyonel)',0]],
    selfemployed:[['firma','Firma / işletme adı',0],['sicil','Vergi no / oda sicil (opsiyonel)',0],['faaliyet','Faaliyet konusu',0],['gelir','Gelir (opsiyonel)',0]],
    itinerary:[['plan','Gün gün plan: şehirler, geceleme, aktiviteler',1],['konak','Konaklama yer(ler)i',0]]
  };
  var PORTALS={
    de:{n:'iData — Almanya vizesi',u:'https://www.idata.com.tr'},
    fr:{n:'France-Visas (resmi)',u:'https://france-visas.gouv.fr'},
    it:{n:'iData — İtalya vizesi',u:'https://www.idata.com.tr'},
    es:{n:'BLS Spain Visa',u:'https://www.blsspainvisa.com'},
    nl:{n:'VFS Global — Hollanda',u:'https://www.vfsglobal.com'},
    gr:{n:'Yunanistan Konsolosluğu / VFS',u:'https://www.vfsglobal.com'},
    usa:{n:'U.S. Travel Docs (resmi)',u:'https://www.ustraveldocs.com/tr/'},
    uk:{n:'gov.uk — UK Visa (resmi)',u:'https://www.gov.uk/apply-uk-visa'},
    ca:{n:'IRCC — Kanada (resmi)',u:'https://www.canada.ca/en/services/immigration-citizenship.html'},
    au:{n:'ImmiAccount (resmi)',u:'https://immi.homeaffairs.gov.au'},
    schengen:{n:'Resmi Schengen randevu portalı (VFS)',u:'https://www.vfsglobal.com'}
  };

  var S={dest:null,type:null,from:'',to:'',city:''};
  var PREP={};
  function dObj(){ for(var i=0;i<DEST.length;i++) if(DEST[i].k===S.dest) return DEST[i]; return null; }
  function tObj(){ for(var i=0;i<TYPE.length;i++) if(TYPE[i].k===S.type) return TYPE[i]; return null; }

  function checklist(){
    var d=dObj(), ty=S.type, G=[];
    if(!d) return G;
    if(d.sch){
      G.push({g:'Kimlik & Seyahat',it:[
        {t:'Geçerli pasaport (seyahat sonrası en az 3 ay geçerli, 2 boş sayfa)',s:'of',nt:'Varsa eski pasaportları da ekleyin'},
        {t:'Biyometrik vesikalık (2 adet, 35×45 mm, son 6 ay)',s:'of',nt:'Fotoğrafçıya "Schengen/biyometrik" deyin'},
        {t:'Kimlik / nüfus cüzdanı fotokopisi',s:'of'}]});
      G.push({g:'Başvuru & Randevu',it:[
        {t:'Schengen vize başvuru formu (eksiksiz, imzalı)',s:'of',nt:'Konsolosluk / VFS Global / iDATA sitesinden'},
        {t:'Randevu onayı (VFS / iDATA / konsolosluk)',s:'of'},
        {t:'Niyet / başvuru mektubu (cover letter)',s:'ch',g:'cover',nt:'Seyahat amacı ve dönüş niyetini açıklar'}]});
      G.push({g:'Mali Belgeler',it:[
        {t:'Son 3 aylık banka hesap dökümü (kaşeli/imzalı)',s:'of'},
        {t:'Maaş bordroları (son 3 ay) veya gelir belgesi',s:'of'},
        {t:'Seyahat sağlık sigortası — min. 30.000 € teminat, tüm Schengen',s:'of',nt:'ZORUNLU; sigorta acentesinden'}]});
      G.push({g:'Seyahat Planı',it:[
        {t:'Gidiş-dönüş uçak rezervasyonu (bilet almadan)',s:'of',nt:'Onaylı rezervasyon yeterli'},
        {t:'Konaklama (otel rezervasyonu / davet)',s:'of'},
        {t:'Günlük seyahat planı (itinerary)',s:'ch',g:'itinerary'}]});
      if(ty==='family') G.push({g:'Aile / Arkadaş Ziyareti',it:[
        {t:'Resmi davet / Verpflichtungserklärung',s:'of',nt:'Davet eden, kendi ülkesindeki makamdan alır'},
        {t:'Destekleyici davet mektubu',s:'ch',g:'invitation'},
        {t:'Davet edenin kimlik/ikamet + gelir belgesi',s:'of'}]});
      else if(ty==='business') G.push({g:'İş Seyahati',it:[
        {t:'Karşı firma davet mektubu',s:'of'},
        {t:'Kendi firmanızın görevlendirme yazısı',s:'ch',g:'employer'},
        {t:'İş sahibiyseniz: serbest meslek beyanı',s:'ch',g:'selfemployed'},
        {t:'Ticaret sicil gazetesi / vergi levhası',s:'of'}]});
      else if(ty==='study') G.push({g:'Eğitim',it:[
        {t:'Kabul / kayıt belgesi (okul / üniversite)',s:'of'},
        {t:'Öğrenci belgesi',s:'of'}]});
      else G.push({g:'İş / Durum Belgesi',it:[
        {t:'Çalışan: SGK hizmet dökümü + işveren izin yazısı',s:'of'},
        {t:'İşveren görevlendirme / izin yazısı',s:'ch',g:'employer'},
        {t:'İş sahibiyseniz: serbest meslek beyanı',s:'ch',g:'selfemployed'}]});
    } else if(d.k==='usa'){
      G.push({g:'ABD (B1/B2)',it:[
        {t:'DS-160 onay sayfası (barkodlu)',s:'of',nt:'ceac.state.gov'},
        {t:'MRV vize harcı ödeme dekontu',s:'of'},
        {t:'İki randevu: OFC (parmak izi) + konsolosluk mülakatı',s:'of'},
        {t:'Vesikalık (5×5 cm, beyaz fon)',s:'of'},
        {t:'Geçerli pasaport + eski pasaportlar',s:'of'}]});
      G.push({g:'Destekleyici',it:[
        {t:'Son 3-6 ay banka dökümü / mali durum',s:'of'},
        {t:'İşveren yazısı / SGK / ticaret sicil',s:'of'},
        {t:'İş sahibiyseniz: serbest meslek beyanı',s:'ch',g:'selfemployed'},
        {t:'Türkiye’ye bağ (aile, iş, mülk) — niyet mektubu',s:'ch',g:'cover',nt:'Dönüş niyetini güçlendirir'}]});
      if(ty==='family') G.push({g:'Ziyaret',it:[
        {t:'ABD’deki davet edenin mektubu + statü (I-797 / yeşil kart / pasaport)',s:'of'},
        {t:'Destekleyici davet mektubu',s:'ch',g:'invitation'}]});
    } else {
      var portal=(d.k==='uk'?'gov.uk':d.k==='ca'?'IRCC':'ImmiAccount');
      G.push({g:d.n+' başvurusu',it:[
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
        {t:'Destekleyici davet mektubu',s:'ch',g:'invitation'}]});
    }
    return G;
  }
  function genList(){
    var seen={},out=[];
    checklist().forEach(function(g){ g.it.forEach(function(it){ if(it.s==='ch'&&it.g&&!seen[it.g]){ seen[it.g]=1; out.push(it.g); } }); });
    return out;
  }

  /* ── prompt: gercek degerler dogrudan yazilir, yer tutucu YOK ── */
  function prof(){ return J('ch_vz_profil',{}); }
  function docans(g){ var a=J('ch_vz_docans',{}); return a[g]||{}; }
  function who(){
    var p=prof(), L=[];
    if(p.ad)L.push(p.ad); if(p.dogum)L.push('doğum: '+p.dogum); if(p.pasaport)L.push('pasaport: '+p.pasaport);
    if(p.meslek)L.push('meslek: '+p.meslek); if(p.adres)L.push('ikamet: '+p.adres);
    return L.length?L.join(', '):'(başvuran bilgisi verilmedi)';
  }
  function extra(g){
    var p=prof(), a=docans(g), L=[], fs=DF[g]||[];
    if(p.isveren)L.push('Çalıştığı yer: '+p.isveren);
    fs.forEach(function(f){ if(a[f[0]]) L.push(f[1]+': '+a[f[0]]); });
    return L.length?('\nEk bilgiler (belgeye uygun yerlere işle):\n- '+L.join('\n- ')):'';
  }
  function prompt(g){
    var d=dObj(), t=tObj(), ln=LN[d.ll]||'English';
    var base='Vize: '+d.n+' ('+(t?t.n:'')+'), seyahat tarihleri '+(S.from||'?')+' – '+(S.to||'?')+(S.city?(', şehir(ler): '+S.city):'')+'. Başvuran: '+who()+'.';
    var tail=extra(g)+'\nKöşeli parantezli yer tutucu ASLA bırakma; bilinmeyen alan varsa cümleyi bilgisiz kur.';
    if(g==='cover') return 'Erstelle ein formelles, überzeugendes Motivations-/Begleitschreiben (cover letter) für einen Visumantrag. '+base+' Erwähne den Reisezweck, die genauen Daten, die Finanzierung, die Reiseversicherung und die feste Rückkehrabsicht (Bindungen zum Heimatland). Adressiere es an das zuständige Konsulat. Schreibe den vollständigen Brief ausschließlich auf '+ln+', mit Datum und Unterschriftszeile. Gib NUR den Brieftext aus.'+tail;
    if(g==='invitation') return 'Erstelle ein formelles Einladungsschreiben, das die EINLADENDE Person im Zielland unterschreibt, zur Unterstützung dieses Visumantrags. '+base+' Die einladende Person bestätigt Beziehung, Unterkunft und ggf. Kostenübernahme sowie die fristgerechte Rückkehr des Gastes. Schreibe ausschließlich auf '+ln+'. Gib NUR den Brieftext mit Unterschriftszeile für den Gastgeber aus.'+tail;
    if(g==='employer') return 'Erstelle ein formelles Arbeitgeber-Schreiben, das der ARBEITGEBER unterschreibt: bestätigt Anstellung, Position, Gehalt, den genehmigten Urlaub für den Reisezeitraum und die Rückkehr an den Arbeitsplatz. '+base+' Schreibe ausschließlich auf '+ln+'. Gib NUR den Brieftext aus, mit Firmenkopf-Zeile und Unterschriftszeile.'+tail;
    if(g==='selfemployed') return 'Erstelle eine formelle Selbstständigen-Erklärung (Eigenerklärung des Geschäftsinhabers) für diesen Visumantrag: bestätigt die selbstständige Tätigkeit, die Firma, die Fortführung des Geschäfts während der Reise und die Rückkehr. '+base+' Schreibe ausschließlich auf '+ln+'. Gib NUR den Brieftext mit Unterschriftszeile aus.'+tail;
    if(g==='itinerary') return 'Erstelle einen übersichtlichen Tages-Reiseplan (itinerary) für diesen Visumantrag. '+base+' Liste für jeden Tag Datum, Ort/Stadt und geplante Aktivität. Schreibe ausschließlich auf '+ln+'. Gib NUR den Reiseplan aus.'+tail;
    return base+tail;
  }

  /* ── sag panel ── */
  var LASTDOC={t:'',txt:'',cc:'DE'};
  function drawer(){
    var d=document.getElementById('vz2-dw');
    if(d) return d;
    d=document.createElement('div'); d.id='vz2-dw';
    d.innerHTML='<div class="hd"><div class="t" id="vz2-dt"></div><button class="x" id="vz2-dx">✕</button></div><div class="bd" id="vz2-db"></div>'
      +'<div class="ft"><button id="vz2-copy">📋 Kopyala</button><button id="vz2-pdf">📄 PDF olarak aç</button></div>';
    document.body.appendChild(d);
    document.getElementById('vz2-dx').onclick=function(){ d.classList.remove('on'); };
    document.getElementById('vz2-copy').onclick=function(){
      try{ if(navigator.clipboard&&navigator.clipboard.writeText) navigator.clipboard.writeText(LASTDOC.txt);
        var b=document.getElementById('vz2-copy'); if(b){ b.textContent='✓ Kopyalandı'; setTimeout(function(){ try{ b.textContent='📋 Kopyala'; }catch(e){} },1500); } }catch(e){}
    };
    document.getElementById('vz2-pdf').onclick=function(){
      try{ if(typeof window.makePDF==='function') window.makePDF(LASTDOC.txt,LASTDOC.t,LASTDOC.cc); else alert(LASTDOC.txt); }catch(e){ alert(LASTDOC.txt); }
    };
    return d;
  }
  function showDoc(title,txt,cc){
    LASTDOC={t:title,txt:txt,cc:cc||'DE'};
    var d=drawer();
    var te=document.getElementById('vz2-dt'); if(te) te.textContent='📄 '+title;
    var be=document.getElementById('vz2-db'); if(be) be.textContent=txt;
    d.classList.add('on');
  }

  /* ── uretim ── */
  function saveForms(g){
    var p={}; PF.forEach(function(f){ var el=document.getElementById('vz2-p-'+f[0]); if(el) p[f[0]]=String(el.value||'').trim(); });
    if(Object.keys(p).length) W('ch_vz_profil',Object.assign(prof(),p));
    var fs=DF[g]||[], a=J('ch_vz_docans',{}), cur=a[g]||{};
    fs.forEach(function(f){ var el=document.getElementById('vz2-d-'+g+'-'+f[0]); if(el) cur[f[0]]=String(el.value||'').trim(); });
    a[g]=cur; W('ch_vz_docans',a);
  }
  window.__v2Gen=function(g,btn){
    var d=dObj(); if(!d) return;
    saveForms(g);
    var old=btn.textContent; btn.textContent='⏳ Hazırlanıyor…'; btn.disabled=true;
    fetch(API()+'?action=aichat',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({message:prompt(g),history:[],provider:'claude',lang:UIL(),country:d.cc})})
     .then(function(r){ return r.json(); })
     .then(function(j){
        btn.textContent=old; btn.disabled=false;
        var txt=(j&&j.reply)?clean(j.reply):'';
        if(!txt){ alert('Belge üretilemedi, lütfen tekrar deneyin.'); return; }
        PREP[g]=(GEN[g]&&GEN[g].n)||g;
        var ok=document.getElementById('vz2-ok-'+g); if(ok) ok.style.display='';
        showDoc(PREP[g],txt,d.cc);
     })
     .catch(function(){ btn.textContent=old; btn.disabled=false; alert('Bağlantı hatası. Lütfen tekrar deneyin.'); });
  };

  /* ── pruefung ── */
  window.__v2Pruef=function(btn){
    var docs=[]; for(var k in PREP) docs.push(PREP[k]);
    var cl=[]; checklist().forEach(function(g){ g.it.forEach(function(it){ cl.push(g.g+' — '+it.t+' ['+(it.s==='ch'?'ChatHelp':'resmi')+']'); }); });
    var d=dObj();
    var msg='Bir vize başvurusu için SON KONTROL (Prüfung) yap. Türkçe, kısa, madde madde.\n\nHedef: '+(d?d.n:'?')+' ('+(tObj()?tObj().n:'')+'), '+(S.from||'?')+' – '+(S.to||'?')
      +'\n\nChatHelp ile hazırlanan belgeler:\n'+(docs.length?('- '+docs.join('\n- ')):'(henüz belge hazırlanmadı)')
      +'\n\nEvrak listesi:\n- '+cl.join('\n- ')
      +'\n\nGörev: 1) Hazırlanmamış ChatHelp belgelerini söyle. 2) Resmi evraklar için kısa hatırlatma. 3) Bu başvuruda sık yapılan 3 hata. 4) Sonda "Hazırlık durumu: %.." satırı. 15 satırı geçme.';
    var old=btn.textContent; btn.textContent='⏳ Kontrol ediliyor…'; btn.disabled=true;
    fetch(API()+'?action=aichat',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({message:msg,history:[],provider:'claude',lang:'tr',country:(d?d.cc:'DE')})})
     .then(function(r){ return r.json(); })
     .then(function(j){ btn.textContent=old; btn.disabled=false; showDoc('🧪 Prüfung — Son Kontrol',(j&&j.reply)?clean(j.reply):'Kontrol alınamadı, tekrar deneyin.',(d?d.cc:'DE')); })
     .catch(function(){ btn.textContent=old; btn.disabled=false; showDoc('🧪 Prüfung','Bağlantı hatası — tekrar deneyin.','DE'); });
  };

  /* ── cizim ── */
  var STEP=1;
  function ov(){ var o=document.getElementById('vz2-ov'); if(!o){ o=document.createElement('div'); o.id='vz2-ov'; document.body.appendChild(o); } return o; }
  function bar(n){ var h='<div class="vz2-steps">'; for(var i=1;i<=4;i++) h+='<div class="s'+(i<=n?' on':'')+'"></div>'; return h+'</div>'; }
  function fieldRow(id,label,val,ta){
    return '<div class="vz2-field"><label>'+esc(label)+'</label>'+(ta?('<textarea class="vz2-in" id="'+id+'" rows="3">'+esc(val)+'</textarea>'):('<input class="vz2-in" id="'+id+'" value="'+esc(val)+'">'))+'</div>';
  }
  function draw(){
    var o=ov(); o.classList.add('on');
    var h='<div class="top"><h2>🛂 Vize <em>Asistanı</em> <span style="font-size:9px;color:#55557a">v2</span></h2><button class="x" onclick="window.__v2Close()">✕ Kapat</button></div><div id="vz2-bd">'+bar(STEP);
    if(STEP===1){
      h+='<div class="vz2-h">Nereye seyahat edeceksiniz?</div><div class="vz2-sub">Hedef ülkeyi seçin — belgeler ve evrak listesi ona göre hazırlanır.</div><div class="vz2-grid">';
      DEST.forEach(function(d){ h+='<div class="vz2-card" onclick="window.__v2Pick(\''+d.k+'\')"><span class="ic">'+d.ic+'</span><span class="nm">'+esc(d.n)+'</span>'+(d.sb?'<span class="sb">'+esc(d.sb)+'</span>':'')+'</div>'; });
      h+='</div>';
    } else if(STEP===2){
      h+='<div class="vz2-h">Seyahat amacınız?</div><div class="vz2-sub">'+esc(dObj().ic+' '+dObj().n)+' için vize tipi.</div><div class="vz2-chips">';
      TYPE.forEach(function(t){ h+='<div class="vz2-chip" onclick="window.__v2Type(\''+t.k+'\')">'+t.ic+' '+esc(t.n)+'</div>'; });
      h+='</div><div class="vz2-nav"><button class="vz2-btn ghost" onclick="window.__v2Back()">←</button></div>';
    } else if(STEP===3){
      h+='<div class="vz2-h">Seyahat tarihleri</div><div class="vz2-sub">Takvimden gidiş ve dönüş tarihini seçin.</div>';
      h+='<div class="vz2-2col"><div class="vz2-field"><label>Gidiş</label><input type="date" class="vz2-in" id="vz2-from" value="'+esc(S.from)+'"></div><div class="vz2-field"><label>Dönüş</label><input type="date" class="vz2-in" id="vz2-to" value="'+esc(S.to)+'"></div></div>';
      h+='<div class="vz2-field"><label>Şehir(ler) (opsiyonel)</label><input type="text" class="vz2-in" id="vz2-city" placeholder="ör. Berlin, München" value="'+esc(S.city)+'"></div>';
      h+='<div class="vz2-nav"><button class="vz2-btn ghost" onclick="window.__v2Back()">←</button><button class="vz2-btn" onclick="window.__v2Go()">Belgelerimi hazırla →</button></div>';
    } else {
      var d=dObj(), gl=genList(), p=prof(), pt=PORTALS[d.k]||PORTALS.schengen;
      h+='<div class="vz2-h">'+esc(d.ic+' '+d.n)+' · '+esc(tObj().n)+'</div><div class="vz2-sub">'+esc((S.from||'?')+' → '+(S.to||'?'))+(S.city?(' · '+esc(S.city)):'')+'</div>';
      h+='<div class="vz2-flow">'
        +'<div class="st" onclick="window.__v2Jump(\'info\')"><span class="n">1</span><span class="l">Bilgilerim</span></div>'
        +'<div class="st" onclick="window.__v2Jump(\'docs\')"><span class="n">2</span><span class="l">Belgeler</span></div>'
        +'<div class="st" onclick="window.__v2Jump(\'cl\')"><span class="n">3</span><span class="l">Evraklar</span></div>'
        +'<div class="st" onclick="window.__v2Jump(\'pruef\')"><span class="n">4</span><span class="l">Prüfung</span></div>'
        +'<div class="st" onclick="window.__v2Jump(\'rdv\')"><span class="n">5</span><span class="l">Randevu</span></div></div>';
      h+='<div class="vz2-sec" id="vz2-sec-info">🪪 Bilgilerim <span style="font-size:10px;color:#8e8e9c;font-weight:600">(bir kez girin — tüm belgelerde kullanılır)</span></div><div class="vz2-box">';
      PF.forEach(function(f){ h+=fieldRow('vz2-p-'+f[0],f[1],p[f[0]]||'',0); });
      h+='</div>';
      h+='<div class="vz2-sec" id="vz2-sec-docs">📄 Belgeler <span style="font-size:10px;color:#8e8e9c;font-weight:600">('+esc(LN[d.ll]||'English')+' dilinde, A4)</span></div>';
      if(!gl.length) h+='<div class="vz2-sub">Bu seçim için otomatik belge yok; evrak listesini takip edin.</div>';
      gl.forEach(function(g){
        var m=GEN[g]||{n:g,d:''}, a=docans(g), fs=DF[g]||[];
        h+='<div class="vz2-doc" id="vz2-doc-'+g+'"><div class="hd" onclick="window.__v2Tgl(\''+g+'\')"><div class="t"><div class="n">'+esc(m.n)+'</div><div class="d">'+esc(m.d)+'</div></div>'
          +'<span class="okb" id="vz2-ok-'+g+'" style="display:'+(PREP[g]?'':'none')+'">✓ hazırlandı</span><span style="color:#8e8e9c">▾</span></div><div class="bd">';
        fs.forEach(function(f){ h+=fieldRow('vz2-d-'+g+'-'+f[0],f[1],a[f[0]]||'',f[2]); });
        h+='<button class="go" onclick="window.__v2Gen(\''+g+'\',this)">📄 Belgeyi Hazırla</button></div></div>';
      });
      h+='<div class="vz2-sec" id="vz2-sec-cl">✅ Toplamanız Gereken Evraklar</div><div class="vz2-sub" style="margin-top:-2px">Liste geneldir; konsolosluğun / VFS sayfasının güncel listesini de teyit edin.</div>';
      checklist().forEach(function(grp){
        h+='<div class="vz2-cl-grp"><div class="gt">'+esc(grp.g)+'</div>';
        grp.it.forEach(function(it){
          h+='<div class="vz2-cl-it"><div class="tx">'+esc(it.t)+(it.nt?'<div class="nt">'+esc(it.nt)+'</div>':'')+'</div>'
            +'<span class="vz2-badge '+(it.s==='ch'?'ch">ChatHelp':'of">Resmi kaynak')+'</span></div>';
        });
        h+='</div>';
      });
      h+='<div class="vz2-sec" id="vz2-sec-pruef">🧪 Prüfung — Son Kontrol</div>'
        +'<div class="vz2-sub" style="margin-top:-2px">Belgeleriniz ve evrak listeniz denetlenir; eksikler ve hazırlık yüzdesi raporlanır.</div>'
        +'<button class="vz2-btn" style="width:100%" onclick="window.__v2Pruef(this)">🧪 Son Kontrolü Başlat</button>';
      h+='<div class="vz2-sec" id="vz2-sec-rdv">📅 Randevu</div>'
        +'<a class="vz2-rdv" href="'+esc(pt.u)+'" target="_blank" rel="noopener noreferrer"><span style="font-size:24px">📅</span><span style="flex:1"><span class="a">'+esc(pt.n)+'</span><span class="b">Resmi randevu portalını açar ↗</span></span></a>'
        +'<div class="vz2-note">1️⃣ Hesap oluştur · 2️⃣ Formu doldur · 3️⃣ Ücreti öde · 4️⃣ Randevu saatini seç · 5️⃣ Belgelerinle randevuya git<br>⚠️ ChatHelp randevu almaz; yalnızca resmî sayfaya yönlendirir.</div>';
      h+='<div class="vz2-nav"><button class="vz2-btn ghost" onclick="window.__v2Back()">←</button><button class="vz2-btn" onclick="window.__v2Close()">Bitti</button></div>';
    }
    h+='</div>';
    o.innerHTML=h;
    if(STEP===3){
      ['vz2-from','vz2-to'].forEach(function(id){
        var el=document.getElementById(id); if(!el) return;
        try{ var dd=new Date(); var mm=('0'+(dd.getMonth()+1)).slice(-2), da=('0'+dd.getDate()).slice(-2); el.setAttribute('min',dd.getFullYear()+'-'+mm+'-'+da); }catch(e){}
        el.addEventListener('click',function(){ try{ if(typeof el.showPicker==='function') el.showPicker(); }catch(e){} });
        el.addEventListener('focus',function(){ try{ if(typeof el.showPicker==='function') el.showPicker(); }catch(e){} });
        el.onchange=function(){ if(id==='vz2-from')S.from=el.value; else S.to=el.value; };
      });
      var c=document.getElementById('vz2-city'); if(c) c.oninput=function(){ S.city=c.value; };
    }
  }
  window.__v2Close=function(){ var o=document.getElementById('vz2-ov'); if(o) o.classList.remove('on'); var d=document.getElementById('vz2-dw'); if(d) d.classList.remove('on'); };
  window.__v2Pick=function(k){ S.dest=k; STEP=2; draw(); };
  window.__v2Type=function(k){ S.type=k; STEP=3; draw(); };
  window.__v2Back=function(){ if(STEP>1){ STEP--; draw(); } };
  window.__v2Go=function(){
    var f=document.getElementById('vz2-from'),t=document.getElementById('vz2-to'),c=document.getElementById('vz2-city');
    if(f)S.from=f.value; if(t)S.to=t.value; if(c)S.city=c.value;
    if(!S.from||!S.to){ alert('Lütfen gidiş ve dönüş tarihini seçin.'); return; }
    STEP=4; draw();
  };
  window.__v2Tgl=function(g){ var el=document.getElementById('vz2-doc-'+g); if(el) el.classList.toggle('open'); };
  window.__v2Jump=function(sec){ var el=document.getElementById('vz2-sec-'+sec); if(el&&el.scrollIntoView) el.scrollIntoView({behavior:'smooth',block:'start'}); };

  /* eski tum baslatma noktalari V2'yi acar */
  function install(){
    window.chVizeAsistan=function(destK){
      S={dest:destK||null,type:null,from:'',to:'',city:''}; PREP={};
      STEP=destK?2:1; draw();
    };
    window.chVizeAsistan.__v2=1;
  }
  install();
  var n=0;(function guard(){ try{ if(!(window.chVizeAsistan&&window.chVizeAsistan.__v2)) install(); }catch(e){} if(n++<300) setTimeout(guard,400); })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'v2').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-vizev2-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_VIZE_V2')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_VIZE_V2 diskte (".strlen($chk)." bayt)\n";
echo "\n✓ VIZE MODULU V2 CANLI — tek parca, eksiksiz:\n";
echo "   1 Ülke → 2 Amaç → 3 Tarihler (takvimli) → 4 Kokpit:\n";
echo "   [şerit: Bilgiler › Belgeler › Evraklar › Prüfung › Randevu]\n";
echo "   • Bilgilerim: bir kez gir, kaydedilir, tum belgelerde kullanilir\n";
echo "   • Belgeler: belgeye ozel alanlar + Hazirla -> SAG PANELDE metin +\n";
echo "     PDF butonu (garantili acilir) + kopyala; hazirlanan ✓ isaretlenir\n";
echo "   • Evraklar: ulke+amaca ozel liste (resmi/ChatHelp rozetli)\n";
echo "   • Prüfung: eksik/uyari/hazirlik yuzdesi raporu\n";
echo "   • Randevu: resmi portal + 5 adim rehber\n";
echo "   Eski vize ekrani devre disi — tum girisler artik V2'yi acar.\n";
