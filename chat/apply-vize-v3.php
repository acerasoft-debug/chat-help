<?php
/**
 * ChatHelp — apply-vize-v3 (CH_VIZE_V3) — VIZE ASISTANI = ANA KONSEPT.
 *  V2'nin ustune tam kapsamli V3 (V2 kodda kalir, uykuya gecer — tum giris
 *  noktalari window.chVizeAsistan uzerinden V3'u acar):
 *  [1] ULKELER genisledi: 23 hedef — Schengen (DE FR IT ES **PT** NL BE AT CH
 *      GR SE PL CZ HU DK NO) + ABD, Ingiltere, Kanada, Avustralya, BAE,
 *      Japonya + Schengen-genel. Belge dili ulkeye gore.
 *  [2] AMACLAR genisledi: Turizm, Aile/Arkadas, Is, Ogrenci, CALISMA vizesi,
 *      TEDAVI, ETKINLIK/Fuar/Spor, Transit — her amacin KENDI sorulari ve
 *      KENDI belge seti var.
 *  [3] KISISEL DURUM sorulari: calisma durumu (calisan/is sahibi/ogrenci/
 *      emekli/calismiyor) + masraflari kim karsiliyor -> belge listesi ve
 *      evrak listesi otomatik buna gore kurulur (11 belge tipi).
 *  [4] VIZE DOSYALARIM (arsiv): her islem otomatik kaydedilir (ch_vz_arsiv).
 *      Ayri bolumde: islemler listelenir, her islem ACILIR, KALDIGI YERDEN
 *      DEVAM (uberarbeiten), belgeler TEK TEK veya TUMU BIRDEN indirilir,
 *      silinebilir.
 *  [5] BELGE DUZENLEME: her uretilen belge panelde ✏️ duzenlenebilir (metin
 *      elle + 🤖 AI ile talimatla revize), 💾 kaydedilir, 📄 PDF indirilir,
 *      📋 kopyalanir.
 *  [6] KISA SABLON KALDIRILDI: eski 'Vize Formlarini Doldurun' (#vsf-entry)
 *      karti gizlenir; TR vize panelinin en ustune Asistana baglanan tek
 *      buyuk kart konur -> her sey Asistan uzerinden, kafa karisikligi yok.
 *  Tek eklemeli </body> blogu; mevcut koda dokunmaz. node ✓ + harness ✓.
 * KULLANIM: pull2.php?key=...&files=apply-vize-v3.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-vize-v3 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_VIZE_V3')!==false) exit("Zaten ekli (CH_VIZE_V3).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-vize-v3-css">
#vz3-ov{position:fixed;inset:0;z-index:2147483160;background:linear-gradient(180deg,#0b0b16,#07070e);display:none;flex-direction:column;font-family:'Inter',system-ui,sans-serif;color:#f2f2fb}
#vz3-ov.on{display:flex}
#vz3-ov .top{display:flex;align-items:center;gap:12px;padding:13px 16px;border-bottom:1px solid rgba(255,255,255,.08);background:#0d0d1c}
#vz3-ov .top h2{font-size:16px;font-weight:800;flex:1}
#vz3-ov .top h2 em{font-style:normal;color:#e8c874}
#vz3-ov .x{background:#1a1a30;border:1px solid rgba(255,255,255,.12);color:#c8c8e0;border-radius:9px;padding:8px 12px;font-size:13px;cursor:pointer;font-family:inherit}
#vz3-bd{flex:1;overflow-y:auto;padding:18px 16px 70px;max-width:760px;width:100%;margin:0 auto;box-sizing:border-box}
.vz3-steps{display:flex;gap:6px;margin-bottom:18px}
.vz3-steps .s{flex:1;height:4px;border-radius:3px;background:rgba(255,255,255,.1)}
.vz3-steps .s.on{background:linear-gradient(90deg,#d4a84a,#f0c860)}
.vz3-h{font-size:19px;font-weight:800;margin-bottom:4px}
.vz3-sub{font-size:12.5px;color:#9a9ab0;margin-bottom:16px;line-height:1.5}
.vz3-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(128px,1fr));gap:10px}
.vz3-card{background:#12122a;border:1.5px solid rgba(255,255,255,.09);border-radius:14px;padding:14px 10px;text-align:center;cursor:pointer;transition:transform .12s,border-color .15s}
.vz3-card:hover{transform:translateY(-2px);border-color:rgba(212,168,74,.5)}
.vz3-card .ic{font-size:26px;display:block;margin-bottom:6px}
.vz3-card .nm{font-size:12.5px;font-weight:700}
.vz3-card .sb{font-size:10px;color:#8e8e9c;margin-top:2px;display:block}
.vz3-chips{display:flex;flex-wrap:wrap;gap:10px}
.vz3-chip{background:#12122a;border:1.5px solid rgba(255,255,255,.09);border-radius:12px;padding:12px 16px;font-size:13.5px;font-weight:700;cursor:pointer}
.vz3-chip:hover{border-color:rgba(212,168,74,.5)}
.vz3-field{margin-bottom:14px}
.vz3-field label{display:block;font-size:10.5px;font-weight:800;color:#8e8e9c;text-transform:uppercase;letter-spacing:.6px;margin-bottom:5px}
.vz3-in{width:100%;background:#12122a;border:1.5px solid rgba(255,255,255,.12);border-radius:11px;padding:12px;color:#fff;font-size:14.5px;font-family:inherit;outline:none;box-sizing:border-box;min-height:48px}
.vz3-in:focus{border-color:rgba(212,168,74,.6)}
select.vz3-in{cursor:pointer}
#vz3-ov input.vz3-in[type="date"]{color-scheme:dark;-webkit-appearance:none;appearance:none;cursor:pointer}
#vz3-ov input.vz3-in[type="date"]::-webkit-calendar-picker-indicator{filter:invert(1);opacity:.95;cursor:pointer}
.vz3-2col{display:flex;gap:12px}.vz3-2col>div{flex:1}
.vz3-nav{display:flex;gap:10px;margin-top:22px}
.vz3-btn{flex:1;background:linear-gradient(135deg,#d4a84a,#ecc060);color:#07070e;border:none;border-radius:12px;padding:14px;font-size:14.5px;font-weight:800;cursor:pointer;font-family:inherit}
.vz3-btn.ghost{background:#1a1a30;color:#c8c8e0;border:1px solid rgba(255,255,255,.12);flex:0 0 auto;padding:14px 20px}
.vz3-flow{display:flex;gap:6px;flex-wrap:wrap;margin:0 0 16px;padding:9px;background:rgba(212,168,74,.07);border:1px solid rgba(212,168,74,.22);border-radius:13px}
.vz3-flow .st{flex:1;min-width:88px;display:flex;align-items:center;gap:6px;padding:7px 9px;border-radius:9px;cursor:pointer;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08)}
.vz3-flow .st:hover{border-color:rgba(212,168,74,.45)}
.vz3-flow .n{width:20px;height:20px;border-radius:50%;background:linear-gradient(135deg,#d4a84a,#ecc060);color:#191000;font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;flex:0 0 auto}
.vz3-flow .l{font-size:10.6px;font-weight:700;line-height:1.2}
.vz3-sec{font-size:15px;font-weight:800;color:#e8c874;margin:22px 0 8px;display:flex;align-items:center;gap:8px}
.vz3-box{background:#12122a;border:1px solid rgba(255,255,255,.1);border-radius:13px;padding:13px;margin-bottom:10px}
.vz3-doc{background:#12122a;border:1px solid rgba(212,168,74,.25);border-radius:13px;padding:13px;margin-bottom:10px}
.vz3-doc .hd{display:flex;align-items:center;gap:10px;cursor:pointer}
.vz3-doc .hd .t{flex:1}
.vz3-doc .hd .n{font-size:14px;font-weight:700}
.vz3-doc .hd .d{font-size:11.5px;color:#8e8e9c;margin-top:2px}
.vz3-doc .hd .okb{font-size:10px;font-weight:800;color:#42df94;border:1px solid rgba(66,223,148,.4);padding:2px 8px;border-radius:100px;white-space:nowrap}
.vz3-doc .bd{display:none;margin-top:10px;border-top:1px solid rgba(255,255,255,.07);padding-top:10px}
.vz3-doc.open .bd{display:block}
.vz3-doc .go{width:100%;background:linear-gradient(135deg,#d4a84a,#ecc060);color:#07070e;border:none;border-radius:10px;padding:12px;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit;margin-top:6px}
.vz3-doc .row{display:flex;gap:8px;margin-top:8px}
.vz3-doc .row button{flex:1;background:#1a1a30;border:1px solid rgba(255,255,255,.14);color:#d8d8ea;border-radius:9px;padding:9px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit}
.vz3-cl-grp{margin-bottom:14px}
.vz3-cl-grp .gt{font-weight:800;color:#d4a84a;font-size:13px;margin-bottom:6px}
.vz3-cl-it{display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.06)}
.vz3-cl-it .tx{flex:1;font-size:13px;color:#e7e7ef}
.vz3-cl-it .nt{font-size:11px;color:#8e8e9c;margin-top:2px}
.vz3-badge{font-size:10px;font-weight:800;padding:2px 8px;border-radius:100px;white-space:nowrap}
.vz3-badge.ch{color:#1b1b1e;background:linear-gradient(135deg,#d4a84a,#f0c860)}
.vz3-badge.of{color:#cfcfdd;background:rgba(255,255,255,.08)}
.vz3-rdv{display:flex;align-items:center;gap:12px;background:linear-gradient(135deg,rgba(212,168,74,.16),rgba(212,168,74,.06));border:1.5px solid rgba(212,168,74,.4);border-radius:13px;padding:13px 15px;cursor:pointer;text-decoration:none;color:inherit}
.vz3-rdv .a{font-size:13.5px;font-weight:800;color:#f0d488;display:block}
.vz3-rdv .b{font-size:11px;color:#b8b8cc}
.vz3-note{font-size:11px;color:#8e8e9c;margin-top:8px;line-height:1.55}
.vz3-arcbtn{display:flex;align-items:center;gap:10px;background:#12122a;border:1.5px solid rgba(212,168,74,.35);border-radius:13px;padding:13px 15px;cursor:pointer;margin-bottom:16px}
.vz3-arcbtn .a{font-size:13.5px;font-weight:800;flex:1}
.vz3-arcbtn .c{font-size:11px;font-weight:800;background:linear-gradient(135deg,#d4a84a,#f0c860);color:#191000;border-radius:100px;padding:3px 10px}
.vz3-proc{background:#12122a;border:1px solid rgba(255,255,255,.12);border-radius:13px;padding:13px;margin-bottom:12px}
.vz3-proc .ph{display:flex;align-items:center;gap:10px;cursor:pointer}
.vz3-proc .ph .t{flex:1}
.vz3-proc .ph .n{font-size:14px;font-weight:800}
.vz3-proc .ph .d{font-size:11px;color:#8e8e9c;margin-top:2px}
.vz3-proc .pb{display:none;margin-top:10px;border-top:1px solid rgba(255,255,255,.07);padding-top:10px}
.vz3-proc.open .pb{display:block}
.vz3-proc .pd{display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid rgba(255,255,255,.06)}
.vz3-proc .pd .dn{flex:1;font-size:12.5px;font-weight:600}
.vz3-proc .pd button{background:#1a1a30;border:1px solid rgba(255,255,255,.14);color:#d8d8ea;border-radius:8px;padding:6px 10px;font-size:11.5px;font-weight:700;cursor:pointer;font-family:inherit}
.vz3-proc .pr{display:flex;gap:8px;margin-top:10px;flex-wrap:wrap}
.vz3-proc .pr button{flex:1;min-width:110px;background:#1a1a30;border:1px solid rgba(255,255,255,.14);color:#d8d8ea;border-radius:9px;padding:9px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit}
.vz3-proc .pr button.gold{background:linear-gradient(135deg,#d4a84a,#ecc060);color:#07070e;border:none}
.vz3-proc .pr button.red{color:#ff9c9c;border-color:rgba(255,120,120,.35)}
#vz3-dw{position:fixed;top:0;right:0;bottom:0;width:min(520px,96vw);z-index:2147483310;transform:translateX(105%);transition:transform .28s;background:linear-gradient(180deg,#12122a,#0b0b19);border-left:1px solid rgba(212,168,74,.4);box-shadow:-18px 0 60px rgba(0,0,0,.55);display:flex;flex-direction:column;color:#f2f2fb;font-family:'Inter',system-ui,sans-serif}
#vz3-dw.on{transform:translateX(0)}
#vz3-dw .hd{padding:15px 16px 11px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;gap:10px;align-items:flex-start}
#vz3-dw .hd .t{flex:1;font-size:14px;font-weight:800;line-height:1.35}
#vz3-dw .hd .x{width:32px;height:32px;border-radius:9px;border:1px solid rgba(255,255,255,.14);background:transparent;color:#c8c8e8;cursor:pointer}
#vz3-db{flex:1;overflow-y:auto;padding:15px 16px;font-size:13.5px;line-height:1.7;white-space:pre-wrap}
#vz3-ded{flex:1;display:none;flex-direction:column;padding:12px 14px;gap:9px}
#vz3-dw.edit #vz3-db{display:none}
#vz3-dw.edit #vz3-ded{display:flex}
#vz3-dta{flex:1;background:#0d0d1e;border:1.5px solid rgba(212,168,74,.35);border-radius:11px;color:#f2f2fb;font-size:13px;line-height:1.6;padding:12px;font-family:inherit;outline:none;resize:none;min-height:200px}
#vz3-dai{display:flex;gap:8px}
#vz3-dai input{flex:1;background:#0d0d1e;border:1.5px solid rgba(255,255,255,.14);border-radius:10px;color:#fff;padding:10px;font-size:12.5px;font-family:inherit;outline:none}
#vz3-dai button{background:#1a1a30;border:1px solid rgba(212,168,74,.4);color:#e8c874;border-radius:10px;padding:10px 13px;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit;white-space:nowrap}
#vz3-dw .ft{padding:11px 14px;border-top:1px solid rgba(255,255,255,.08);display:flex;gap:8px;flex-wrap:wrap}
#vz3-dw .ft button{flex:1;min-width:96px;padding:12px 8px;border-radius:11px;border:none;cursor:pointer;font-family:inherit;font-size:12.5px;font-weight:800}
#vz3-pdf{background:linear-gradient(135deg,#d4a84a,#ecc060);color:#191000}
#vz3-copy,#vz3-edit,#vz3-save{background:transparent;border:1.5px solid rgba(255,255,255,.18)!important;color:#c8c8e8}
#vz3-save{border-color:rgba(66,223,148,.5)!important;color:#7fe8b0}
#vsf-entry{display:none!important}
</style>
<script id="ch-vize-v3-js">
/* CH_VIZE_V3 — Vize Asistani ana konsept: 23 ulke, 8 amac, 11 belge, arsiv+duzenleme+indirme */
try{(function(){
  var API=function(){ return (typeof window.API!=='undefined'&&window.API)?window.API:'api.php'; };
  var UIL=function(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } };
  function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
  function J(k,d){ try{ return JSON.parse(localStorage.getItem(k)||'null')||d; }catch(e){ return d; } }
  function W(k,v){ try{ localStorage.setItem(k,JSON.stringify(v)); }catch(e){} }
  function clean(s){ return String(s||'').replace(/```[a-z]*\n?|```/g,'').replace(/\[\[\/?DOC\]\]/g,'').replace(/\*\*/g,'').trim(); }
  function today(){ var d=new Date(); return ('0'+d.getDate()).slice(-2)+'.'+('0'+(d.getMonth()+1)).slice(-2)+'.'+d.getFullYear(); }
  function toast(s){ try{ var t=document.createElement('div'); t.setAttribute('style','position:fixed;left:50%;bottom:92px;transform:translateX(-50%);z-index:2147483400;background:rgba(15,22,52,.97);color:#f0e6c8;border:1px solid rgba(212,168,74,.5);border-radius:12px;padding:10px 16px;font-size:13px;font-weight:700;max-width:88vw;text-align:center'); t.textContent=s; document.body.appendChild(t); setTimeout(function(){ try{ t.remove(); }catch(e){} },3200); }catch(e){} }

  var DEST=[
    {k:'schengen',n:'Schengen (genel)',ic:'🇪🇺',ll:'en',cc:'DE',sb:'DE·FR·IT·ES·PT…',sch:1},
    {k:'de',n:'Almanya',ic:'🇩🇪',ll:'de',cc:'DE',sch:1},
    {k:'fr',n:'Fransa',ic:'🇫🇷',ll:'fr',cc:'FR',sch:1},
    {k:'it',n:'İtalya',ic:'🇮🇹',ll:'it',cc:'IT',sch:1},
    {k:'es',n:'İspanya',ic:'🇪🇸',ll:'es',cc:'ES',sch:1},
    {k:'pt',n:'Portekiz',ic:'🇵🇹',ll:'pt',cc:'PT',sch:1},
    {k:'nl',n:'Hollanda',ic:'🇳🇱',ll:'en',cc:'NL',sch:1},
    {k:'be',n:'Belçika',ic:'🇧🇪',ll:'fr',cc:'BE',sch:1},
    {k:'at',n:'Avusturya',ic:'🇦🇹',ll:'de',cc:'AT',sch:1},
    {k:'chx',n:'İsviçre',ic:'🇨🇭',ll:'de',cc:'CH',sch:1},
    {k:'gr',n:'Yunanistan',ic:'🇬🇷',ll:'en',cc:'GR',sch:1},
    {k:'se',n:'İsveç',ic:'🇸🇪',ll:'en',cc:'SE',sch:1},
    {k:'dk',n:'Danimarka',ic:'🇩🇰',ll:'en',cc:'DK',sch:1},
    {k:'no',n:'Norveç',ic:'🇳🇴',ll:'en',cc:'NO',sch:1},
    {k:'pl',n:'Polonya',ic:'🇵🇱',ll:'en',cc:'PL',sch:1},
    {k:'cz',n:'Çekya',ic:'🇨🇿',ll:'en',cc:'CZ',sch:1},
    {k:'hu',n:'Macaristan',ic:'🇭🇺',ll:'en',cc:'HU',sch:1},
    {k:'usa',n:'ABD',ic:'🇺🇸',ll:'en',cc:'US'},
    {k:'uk',n:'İngiltere',ic:'🇬🇧',ll:'en',cc:'GB'},
    {k:'ca',n:'Kanada',ic:'🇨🇦',ll:'en',cc:'CA'},
    {k:'au',n:'Avustralya',ic:'🇦🇺',ll:'en',cc:'AU'},
    {k:'ae',n:'BAE (Dubai)',ic:'🇦🇪',ll:'en',cc:'AE'},
    {k:'jp',n:'Japonya',ic:'🇯🇵',ll:'en',cc:'JP'}
  ];
  var TYPE=[
    {k:'tourism',n:'Turizm',ic:'🏖️'},
    {k:'family',n:'Aile / Arkadaş ziyareti',ic:'👪'},
    {k:'business',n:'İş görüşmesi / Ticari',ic:'💼'},
    {k:'study',n:'Öğrenci / Eğitim / Dil kursu',ic:'🎓'},
    {k:'work',n:'Çalışma vizesi',ic:'🛠️'},
    {k:'medical',n:'Tedavi / Sağlık',ic:'🏥'},
    {k:'event',n:'Etkinlik / Fuar / Spor / Kültür',ic:'🎫'},
    {k:'transit',n:'Transit',ic:'✈️'}
  ];
  var LN={de:'Deutsch',en:'English',fr:'Français',it:'Italiano',es:'Español',nl:'Nederlands',pt:'Português'};
  var EMP=[['calisan','Maaşlı çalışanım'],['issahibi','Kendi işim var / serbest'],['ogrenci','Öğrenciyim'],['emekli','Emekliyim'],['calismiyor','Şu an çalışmıyorum']];
  var PAYER=[['kendim','Kendim karşılıyorum'],['sponsor','Ailem / sponsorum karşılıyor'],['davet','Davet eden karşılıyor'],['firma','Firmam / kurum karşılıyor']];
  var GEN={
    cover:{n:'Niyet / Başvuru Mektubu',d:'Seyahat amacı, tarihler, finansman, dönüş niyeti'},
    itinerary:{n:'Günlük Seyahat Planı (Itinerary)',d:'Gün gün tarih, şehir, aktivite'},
    invitation:{n:'Davet Mektubu (destekleyici)',d:'Davet eden kişinin imzalayacağı mektup'},
    sponsor:{n:'Sponsor / Masraf Karşılama Beyanı',d:'Masrafları karşılayan kişinin imzalayacağı beyan'},
    employer:{n:'İşveren İzin / Görevlendirme Yazısı',d:'İşverenin imzalayacağı yazı'},
    selfemployed:{n:'İş Sahibi / Serbest Meslek Beyanı',d:'Kendi işinizin sahibiyseniz kendiniz imzalarsınız'},
    student:{n:'Öğrenci Durum + Niyet Mektubu',d:'Okul/üniversite kaydı ve dönüş niyeti'},
    retiree:{n:'Emekli Gelir & Bağ Beyanı',d:'Emekli maaşı ve ülkeye bağların beyanı'},
    work:{n:'Çalışma Vizesi Motivasyon Mektubu',d:'İş sözleşmesi ve pozisyona uygun başvuru yazısı'},
    medical:{n:'Tedavi Amaçlı Başvuru Mektubu',d:'Tedavi, hastane randevusu ve finansman açıklaması'},
    event:{n:'Etkinlik Katılım Mektubu',d:'Fuar / spor / kültür etkinliğine katılım açıklaması'}
  };
  var PF=[['ad','Ad Soyad'],['dogum','Doğum tarihi'],['pasaport','Pasaport numarası'],['meslek','Meslek / Unvan'],['isveren','Çalıştığınız yer (varsa)'],['adres','Adres (şehir, ülke)']];
  var DF={
    cover:[['konaklama','Konaklama (otel adı veya kalacağınız kişi)',0],['ek','Vurgulamak istedikleriniz (opsiyonel)',1]],
    itinerary:[['plan','Gün gün plan: şehirler, geceleme, aktiviteler',1],['konak','Konaklama yer(ler)i',0]],
    invitation:[['davet_ad','Davet eden kişinin adı soyadı',0],['davet_adres','Davet edenin adresi (gidilecek ülkede)',0],['davet_dogum','Davet edenin doğum tarihi (opsiyonel)',0],['yakinlik','Yakınlık dereceniz',0]],
    sponsor:[['sp_ad','Sponsorun adı soyadı',0],['sp_iliski','Sponsorla ilişkiniz (anne/baba/eş…)',0],['sp_gelir','Sponsorun geliri / mesleği',0],['sp_neden','Neden sponsor karşılıyor? (kısa)',0]],
    employer:[['firma','İşveren / firma adı',0],['pozisyon','Pozisyonunuz',0],['giris','İşe başlama tarihi (ay/yıl)',0],['maas','Aylık maaş (opsiyonel)',0]],
    selfemployed:[['firma','Firma / işletme adı',0],['sicil','Vergi no / oda sicil (opsiyonel)',0],['faaliyet','Faaliyet konusu',0],['gelir','Gelir (opsiyonel)',0]],
    student:[['okul','Okul / üniversite adı',0],['bolum','Bölüm / sınıf',0],['donem','Kayıt dönemi (ör. 2025-2026)',0]],
    retiree:[['kurum','Emekli maaşını ödeyen kurum (SGK vb.)',0],['gelir','Aylık emekli maaşı',0],['baglar','Ülkenizdeki bağlar (aile, mülk…)',0]],
    work:[['is_firma','İşe alacak firma (hedef ülkede)',0],['is_pozisyon','Pozisyon / görev',0],['sozlesme','Sözleşme durumu (imzalı / teklif)',0],['baslama','İşe başlama tarihi',0]],
    medical:[['hastane','Hastane / klinik adı (hedef ülkede)',0],['tedavi','Tedavinin türü / teşhis',0],['randevu','Hastane randevu / kabul yazısı var mı?',0],['refakat','Refakatçi gelecek mi? (kim)',0]],
    event:[['etkinlik','Etkinliğin adı',0],['etk_tarih','Etkinlik tarihi ve yeri',0],['kurum','Davet eden / düzenleyen kurum (varsa)',0],['rol','Katılım şekliniz (ziyaretçi / katılımcı / sporcu…)',0]]
  };
  var PORTALS={
    de:{n:'iData — Almanya vizesi',u:'https://www.idata.com.tr'},
    fr:{n:'France-Visas (resmi)',u:'https://france-visas.gouv.fr'},
    it:{n:'iData — İtalya vizesi',u:'https://www.idata.com.tr'},
    es:{n:'BLS Spain Visa',u:'https://www.blsspainvisa.com'},
    pt:{n:'VFS Global — Portekiz',u:'https://www.vfsglobal.com'},
    nl:{n:'VFS Global — Hollanda',u:'https://www.vfsglobal.com'},
    be:{n:'VFS Global — Belçika',u:'https://www.vfsglobal.com'},
    at:{n:'VFS Global — Avusturya',u:'https://www.vfsglobal.com'},
    chx:{n:'TLScontact — İsviçre',u:'https://www.tlscontact.com'},
    gr:{n:'Yunanistan Konsolosluğu / VFS',u:'https://www.vfsglobal.com'},
    se:{n:'VFS Global — İsveç',u:'https://www.vfsglobal.com'},
    dk:{n:'VFS Global — Danimarka',u:'https://www.vfsglobal.com'},
    no:{n:'VFS Global — Norveç',u:'https://www.vfsglobal.com'},
    pl:{n:'VFS Global — Polonya',u:'https://www.vfsglobal.com'},
    cz:{n:'VFS Global — Çekya',u:'https://www.vfsglobal.com'},
    hu:{n:'VFS Global — Macaristan',u:'https://www.vfsglobal.com'},
    usa:{n:'U.S. Travel Docs (resmi)',u:'https://www.ustraveldocs.com/tr/'},
    uk:{n:'gov.uk — UK Visa (resmi)',u:'https://www.gov.uk/apply-uk-visa'},
    ca:{n:'IRCC — Kanada (resmi)',u:'https://www.canada.ca/en/services/immigration-citizenship.html'},
    au:{n:'ImmiAccount (resmi)',u:'https://immi.homeaffairs.gov.au'},
    ae:{n:'BAE e-vize / havayolu vize servisi',u:'https://www.emirates.com/tr/turkish/before-you-fly/visa-passport-information/uae-visa-information/'},
    jp:{n:'Japonya Büyükelçiliği (resmi)',u:'https://www.tr.emb-japan.go.jp'},
    schengen:{n:'Resmi Schengen randevu portalı (VFS)',u:'https://www.vfsglobal.com'}
  };

  var S={dest:null,type:null,from:'',to:'',city:'',emp:'',payer:''};
  var PREP={}; var CURPROC=null; var STEP=1;
  function dObj(){ for(var i=0;i<DEST.length;i++) if(DEST[i].k===S.dest) return DEST[i]; return null; }
  function tObj(){ for(var i=0;i<TYPE.length;i++) if(TYPE[i].k===S.type) return TYPE[i]; return null; }

  /* ── belge seti: amac + calisma durumu + odeme ── */
  function docsFor(){
    var t=S.type, out=[];
    if(t==='tourism') out=['cover','itinerary'];
    else if(t==='family') out=['cover','invitation','itinerary'];
    else if(t==='business') out=['cover','itinerary'];
    else if(t==='study') out=['cover','student'];
    else if(t==='work') out=['work'];
    else if(t==='medical') out=['medical'];
    else if(t==='event') out=['cover','event'];
    else out=['cover'];
    if(t!=='work'){
      if(S.emp==='calisan') out.push('employer');
      else if(S.emp==='issahibi') out.push('selfemployed');
      else if(S.emp==='ogrenci'){ if(out.indexOf('student')===-1) out.push('student'); }
      else if(S.emp==='emekli') out.push('retiree');
    }
    if(S.payer==='sponsor'||S.payer==='davet'||(S.emp==='calismiyor'&&S.payer!=='firma')){ if(out.indexOf('sponsor')===-1) out.push('sponsor'); }
    var seen={},fin=[];
    out.forEach(function(g){ if(!seen[g]&&GEN[g]){ seen[g]=1; fin.push(g); } });
    return fin;
  }

  /* ── evrak listesi ── */
  function checklist(){
    var d=dObj(), ty=S.type, G=[];
    if(!d) return G;
    var idGrp={g:'Kimlik & Seyahat',it:[
      {t:'Geçerli pasaport (seyahat sonrası en az '+(d.sch?'3':'6')+' ay geçerli, 2 boş sayfa)',s:'of',nt:'Varsa eski pasaportları da ekleyin'},
      {t:'Biyometrik vesikalık (35×45 mm, son 6 ay)',s:'of'},
      {t:'Kimlik / nüfus cüzdanı fotokopisi',s:'of'}]};
    var finGrp={g:'Mali Belgeler',it:[
      {t:'Son 3 aylık banka hesap dökümü (kaşeli/imzalı)',s:'of'},
      {t:(S.emp==='emekli'?'Emekli maaş dökümü':'Maaş bordroları (son 3 ay) veya gelir belgesi'),s:'of'}]};
    if(S.payer==='sponsor'||S.payer==='davet') finGrp.it.push({t:'Sponsorun banka dökümü + kimlik',s:'of'},{t:'Sponsor / masraf karşılama beyanı',s:'ch',g:'sponsor'});
    var empGrp={g:'İş / Durum Belgesi',it:[]};
    if(S.emp==='calisan') empGrp.it.push({t:'SGK hizmet dökümü',s:'of'},{t:'İşveren izin / görevlendirme yazısı',s:'ch',g:'employer'});
    else if(S.emp==='issahibi') empGrp.it.push({t:'Ticaret sicil gazetesi / vergi levhası',s:'of'},{t:'Serbest meslek beyanı',s:'ch',g:'selfemployed'});
    else if(S.emp==='ogrenci') empGrp.it.push({t:'Öğrenci belgesi (okuldan)',s:'of'},{t:'Öğrenci durum + niyet mektubu',s:'ch',g:'student'});
    else if(S.emp==='emekli') empGrp.it.push({t:'Emekli kimliği / maaş yazısı',s:'of'},{t:'Emekli gelir & bağ beyanı',s:'ch',g:'retiree'});
    else if(S.emp==='calismiyor') empGrp.it.push({t:'Varsa mülk tapusu, kira geliri vb. bağ belgeleri',s:'of'});
    if(d.sch){
      G.push(idGrp);
      G.push({g:'Başvuru & Randevu',it:[
        {t:'Schengen vize başvuru formu (eksiksiz, imzalı)',s:'of',nt:'Konsolosluk / VFS / iDATA sitesinden'},
        {t:'Randevu onayı',s:'of'},
        {t:'Niyet / başvuru mektubu',s:'ch',g:'cover'}]});
      G.push(finGrp);
      G.push({g:'Seyahat Planı',it:[
        {t:'Gidiş-dönüş uçak rezervasyonu (bilet almadan)',s:'of',nt:'Onaylı rezervasyon yeterli'},
        {t:'Konaklama (otel rezervasyonu / davet)',s:'of'},
        {t:'Seyahat sağlık sigortası — min. 30.000 €, tüm Schengen',s:'of',nt:'ZORUNLU'},
        {t:'Günlük seyahat planı (itinerary)',s:'ch',g:'itinerary'}]});
      if(empGrp.it.length) G.push(empGrp);
    } else if(d.k==='usa'){
      G.push({g:'ABD (B1/B2)',it:[
        {t:'DS-160 onay sayfası (barkodlu)',s:'of',nt:'ceac.state.gov'},
        {t:'MRV vize harcı ödeme dekontu',s:'of'},
        {t:'İki randevu: OFC (parmak izi) + konsolosluk mülakatı',s:'of'},
        {t:'Vesikalık (5×5 cm, beyaz fon)',s:'of'}]});
      G.push(idGrp); G.push(finGrp); if(empGrp.it.length) G.push(empGrp);
      G.push({g:'Destekleyici',it:[{t:'Türkiye’ye bağ (aile, iş, mülk) — niyet mektubu',s:'ch',g:'cover',nt:'Dönüş niyetini güçlendirir'}]});
    } else {
      var portal=(d.k==='uk'?'gov.uk':d.k==='ca'?'IRCC':d.k==='au'?'ImmiAccount':d.k==='jp'?'Büyükelçilik / VFS':'e-vize / VFS');
      G.push({g:d.n+' başvurusu',it:[
        {t:'Online başvuru ('+portal+') + ücret ödemesi',s:'of'},
        {t:'Biyometri / evrak teslim randevusu (gerekliyse)',s:'of'},
        {t:'Niyet mektubu (purpose of travel)',s:'ch',g:'cover'}]});
      G.push(idGrp); G.push(finGrp); if(empGrp.it.length) G.push(empGrp);
    }
    if(ty==='family') G.push({g:'Aile / Arkadaş Ziyareti',it:[
      {t:(d.k==='de'||d.k==='at'?'Resmi davet / Verpflichtungserklärung':'Resmi davet belgesi (davet eden alır)'),s:'of'},
      {t:'Destekleyici davet mektubu',s:'ch',g:'invitation'},
      {t:'Davet edenin kimlik / oturum + gelir belgesi',s:'of'}]});
    else if(ty==='business') G.push({g:'İş Seyahati',it:[
      {t:'Karşı firma davet mektubu',s:'of'},
      {t:'Firmanızın görevlendirme yazısı',s:'ch',g:'employer'},
      {t:'Ticaret sicil gazetesi / vergi levhası (firma sahibiyseniz)',s:'of'}]});
    else if(ty==='study') G.push({g:'Eğitim',it:[
      {t:'Kabul / kayıt belgesi (okul / üniversite / kurs)',s:'of'},
      {t:'Öğrenim ücreti ödeme / burs belgesi',s:'of'},
      {t:'Öğrenci durum + niyet mektubu',s:'ch',g:'student'}]});
    else if(ty==='work') G.push({g:'Çalışma Vizesi',it:[
      {t:'İmzalı iş sözleşmesi / bağlayıcı iş teklifi',s:'of'},
      {t:'Diploma + mesleki yeterlilik belgeleri (gerekirse tercümeli)',s:'of'},
      {t:'Çalışma vizesi motivasyon mektubu',s:'ch',g:'work'},
      {t:'Hedef ülkenin çalışma izni ön onayı (varsa)',s:'of',nt:'Ör. Almanya: ZAV ön onayı / Mavi Kart koşulları'}]});
    else if(ty==='medical') G.push({g:'Tedavi',it:[
      {t:'Hastane / klinik kabul-randevu yazısı (hedef ülke)',s:'of'},
      {t:'Türkiye’den sevk / epikriz raporu (tercümeli)',s:'of'},
      {t:'Tedavi masrafı ödeme kanıtı / depozito',s:'of'},
      {t:'Tedavi amaçlı başvuru mektubu',s:'ch',g:'medical'}]});
    else if(ty==='event') G.push({g:'Etkinlik',it:[
      {t:'Etkinlik davetiyesi / bilet / akreditasyon',s:'of'},
      {t:'Etkinlik katılım mektubu',s:'ch',g:'event'}]});
    else if(ty==='transit') G.push({g:'Transit',it:[
      {t:'Devam uçuş bileti + var ise 3. ülke vizesi',s:'of'}]});
    return G;
  }

  /* ── profil & cevaplar ── */
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
    var em=EMP.filter(function(e){return e[0]===S.emp;})[0]; if(em) L.push('Çalışma durumu: '+em[1]);
    var py=PAYER.filter(function(e){return e[0]===S.payer;})[0]; if(py) L.push('Masraflar: '+py[1]);
    fs.forEach(function(f){ if(a[f[0]]) L.push(f[1]+': '+a[f[0]]); });
    return L.length?('\nEk bilgiler (belgeye uygun yerlere işle):\n- '+L.join('\n- ')):'';
  }
  function prompt(g){
    var d=dObj(), t=tObj(), ln=LN[d.ll]||'English';
    var base='Vize: '+d.n+' ('+(t?t.n:'')+'), seyahat tarihleri '+(S.from||'?')+' – '+(S.to||'?')+(S.city?(', şehir(ler): '+S.city):'')+'. Başvuran: '+who()+'.';
    var tail=extra(g)
      +'\nHeutiges Datum: '+today()+' — dieses Datum als Briefdatum verwenden.'
      +'\nKöşeli parantezli yer tutucu ASLA bırakma; bilinmeyen alan varsa cümleyi bilgisiz kur. KEIN Markdown, keine Sternchen — nur reiner Brieftext.';
    var P={
      cover:'Erstelle ein formelles, überzeugendes Motivations-/Begleitschreiben (cover letter) für einen Visumantrag. '+base+' Erwähne den Reisezweck, die genauen Daten, die Finanzierung, die Reiseversicherung und die feste Rückkehrabsicht (Bindungen zum Heimatland). Adressiere es an das zuständige Konsulat. Schreibe den vollständigen Brief ausschließlich auf '+ln+', mit Datum und Unterschriftszeile. Gib NUR den Brieftext aus.',
      invitation:'Erstelle ein formelles Einladungsschreiben, das die EINLADENDE Person im Zielland unterschreibt, zur Unterstützung dieses Visumantrags. '+base+' Die einladende Person bestätigt Beziehung, Unterkunft und ggf. Kostenübernahme sowie die fristgerechte Rückkehr des Gastes. Schreibe ausschließlich auf '+ln+'. Gib NUR den Brieftext mit Unterschriftszeile für den Gastgeber aus.',
      sponsor:'Erstelle eine formelle Kostenübernahme-/Sponsorerklärung, die der SPONSOR unterschreibt: bestätigt Beziehung zum Antragsteller, eigene Einkünfte und die vollständige Übernahme der Reise- und Aufenthaltskosten für den genannten Zeitraum. '+base+' Schreibe ausschließlich auf '+ln+'. Gib NUR den Erklärungstext mit Unterschriftszeile des Sponsors aus.',
      employer:'Erstelle ein formelles Arbeitgeber-Schreiben, das der ARBEITGEBER unterschreibt: bestätigt Anstellung, Position, Gehalt, den genehmigten Urlaub für den Reisezeitraum und die Rückkehr an den Arbeitsplatz. '+base+' Schreibe ausschließlich auf '+ln+'. Gib NUR den Brieftext aus, mit Firmenkopf-Zeile und Unterschriftszeile.',
      selfemployed:'Erstelle eine formelle Selbstständigen-Erklärung (Eigenerklärung des Geschäftsinhabers) für diesen Visumantrag: bestätigt die selbstständige Tätigkeit, die Firma, die Fortführung des Geschäfts während der Reise und die Rückkehr. '+base+' Schreibe ausschließlich auf '+ln+'. Gib NUR den Brieftext mit Unterschriftszeile aus.',
      itinerary:'Erstelle einen übersichtlichen Tages-Reiseplan (itinerary) für diesen Visumantrag. '+base+' Liste für jeden Tag Datum, Ort/Stadt und geplante Aktivität. Schreibe ausschließlich auf '+ln+'. Gib NUR den Reiseplan aus.',
      student:'Erstelle ein formelles Schreiben eines STUDENTEN für einen Visumantrag: bestätigt die aktuelle Einschreibung (Schule/Universität, Fachrichtung, Studienjahr), den Zweck der Reise und die feste Absicht, zum Studium zurückzukehren. '+base+' Schreibe ausschließlich auf '+ln+'. Gib NUR den Brieftext mit Unterschriftszeile aus.',
      retiree:'Erstelle ein formelles Schreiben eines RENTNERS für einen Visumantrag: bestätigt den Rentenstatus, die monatliche Rente (auszahlende Stelle), die finanzielle Selbstversorgung während der Reise und die starken Bindungen an das Heimatland (Familie, Eigentum). '+base+' Schreibe ausschließlich auf '+ln+'. Gib NUR den Brieftext mit Unterschriftszeile aus.',
      work:'Erstelle ein formelles Motivationsschreiben für einen ARBEITSVISUM-Antrag (nationales Visum). '+base+' Erwähne den Arbeitgeber im Zielland, die Position, den Vertragsstatus, das geplante Startdatum, die Qualifikationen des Antragstellers und die Einhaltung der Visabestimmungen. Schreibe ausschließlich auf '+ln+', mit Datum und Unterschriftszeile. Gib NUR den Brieftext aus.',
      medical:'Erstelle ein formelles Begleitschreiben für einen Visumantrag zur MEDIZINISCHEN BEHANDLUNG. '+base+' Erwähne die Diagnose/Behandlung, die Klinik im Zielland, den Termin-/Aufnahmestatus, die Finanzierung der Behandlung und der Reise, ggf. die Begleitperson, und die Rückkehr nach Abschluss der Behandlung. Schreibe ausschließlich auf '+ln+'. Gib NUR den Brieftext mit Unterschriftszeile aus.',
      event:'Erstelle ein formelles Begleitschreiben für einen Visumantrag zur Teilnahme an einer VERANSTALTUNG (Messe/Sport/Kultur). '+base+' Erwähne die Veranstaltung, Datum und Ort, die Rolle des Antragstellers, die Finanzierung und die Rückkehrabsicht. Schreibe ausschließlich auf '+ln+'. Gib NUR den Brieftext mit Unterschriftszeile aus.'
    };
    return (P[g]||base)+tail;
  }

  /* ── ARSIV (Vize Dosyalarim) ── */
  function arc(){ var a=J('ch_vz_arsiv',[]); return Array.isArray(a)?a:[]; }
  function arcW(a){ W('ch_vz_arsiv',a); }
  function procOf(id){ var a=arc(); for(var i=0;i<a.length;i++) if(a[i].id===id) return a[i]; return null; }
  function procTitle(p){
    var d=null,t=null,i;
    for(i=0;i<DEST.length;i++) if(DEST[i].k===p.dest) d=DEST[i];
    for(i=0;i<TYPE.length;i++) if(TYPE[i].k===p.type) t=TYPE[i];
    return (d?d.ic+' '+d.n:'?')+' · '+(t?t.n:'?')+(p.from?(' · '+p.from+' → '+p.to):'');
  }
  function ensureProc(){
    if(CURPROC){ var ex=procOf(CURPROC); if(ex) return ex; }
    var a=arc();
    var p={id:'vz'+new Date().getTime(),dest:S.dest,type:S.type,from:S.from,to:S.to,city:S.city,emp:S.emp,payer:S.payer,created:today(),docs:{}};
    a.push(p); arcW(a); CURPROC=p.id; return p;
  }
  function saveDocToProc(g,txt){
    var p=ensureProc(), a=arc();
    for(var i=0;i<a.length;i++) if(a[i].id===p.id){
      a[i].dest=S.dest;a[i].type=S.type;a[i].from=S.from;a[i].to=S.to;a[i].city=S.city;a[i].emp=S.emp;a[i].payer=S.payer;
      a[i].docs[g]={n:(GEN[g]&&GEN[g].n)||g,txt:txt,cc:(dObj()?dObj().cc:'DE'),ts:today()};
      arcW(a); return;
    }
  }

  /* ── sag panel: goruntule + duzenle + AI revize + kaydet + PDF ── */
  var DW={procId:null,g:null,title:'',txt:'',cc:'DE'};
  function drawer(){
    var d=document.getElementById('vz3-dw');
    if(d) return d;
    d=document.createElement('div'); d.id='vz3-dw';
    d.innerHTML='<div class="hd"><div class="t" id="vz3-dt"></div><button class="x" id="vz3-dx">✕</button></div>'
      +'<div id="vz3-db"></div>'
      +'<div id="vz3-ded"><textarea id="vz3-dta"></textarea><div id="vz3-dai"><input id="vz3-dai-in" placeholder="AI talimatı: ör. daha resmi yap, şu cümleyi ekle…"><button id="vz3-dai-go">🤖 AI Düzelt</button></div></div>'
      +'<div class="ft"><button id="vz3-edit">✏️ Düzenle</button><button id="vz3-save" style="display:none">💾 Kaydet</button><button id="vz3-copy">📋 Kopyala</button><button id="vz3-pdf">📄 PDF indir</button></div>';
    document.body.appendChild(d);
    document.getElementById('vz3-dx').onclick=function(){ d.classList.remove('on'); d.classList.remove('edit'); };
    document.getElementById('vz3-edit').onclick=function(){
      d.classList.add('edit');
      document.getElementById('vz3-dta').value=DW.txt;
      document.getElementById('vz3-edit').style.display='none';
      document.getElementById('vz3-save').style.display='';
    };
    document.getElementById('vz3-save').onclick=function(){
      DW.txt=String(document.getElementById('vz3-dta').value||'');
      document.getElementById('vz3-db').textContent=DW.txt;
      d.classList.remove('edit');
      document.getElementById('vz3-edit').style.display='';
      document.getElementById('vz3-save').style.display='none';
      if(DW.procId&&DW.g){
        var a=arc();
        for(var i=0;i<a.length;i++) if(a[i].id===DW.procId&&a[i].docs[DW.g]){ a[i].docs[DW.g].txt=DW.txt; a[i].docs[DW.g].ts=today(); arcW(a); }
        if(DW.procId===CURPROC) PREP[DW.g]=(GEN[DW.g]&&GEN[DW.g].n)||DW.g;
      }
      toast('💾 Belge kaydedildi');
      if(STEP==='arc') draw();
    };
    document.getElementById('vz3-dai-go').onclick=function(){
      var ins=String(document.getElementById('vz3-dai-in').value||'').trim();
      if(!ins){ toast('Önce talimat yazın (ör. daha resmi yap)'); return; }
      var btn=this, old=btn.textContent; btn.textContent='⏳'; btn.disabled=true;
      var cur=String(document.getElementById('vz3-dta').value||DW.txt);
      fetch(API()+'?action=aichat',{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({message:'Aşağıdaki resmi belgeyi şu talimata göre revize et: "'+ins+'". Belgenin dilini ve resmi yapısını koru. KEIN Markdown, keine Sternchen. Gib NUR den vollständigen überarbeiteten Text aus.\n\n───\n'+cur,history:[],provider:'claude',lang:UIL(),country:DW.cc})})
       .then(function(r){ return r.json(); })
       .then(function(j){
          btn.textContent=old; btn.disabled=false;
          var t=(j&&j.reply)?clean(j.reply):'';
          if(!t){ toast('⚠ '+((j&&j.error)||'Revize alınamadı, tekrar deneyin')); return; }
          document.getElementById('vz3-dta').value=t;
          document.getElementById('vz3-dai-in').value='';
          toast('🤖 Revize hazır — kontrol edip 💾 Kaydet\'e basın');
       })
       .catch(function(){ btn.textContent=old; btn.disabled=false; toast('Bağlantı hatası'); });
    };
    document.getElementById('vz3-copy').onclick=function(){
      try{ if(navigator.clipboard&&navigator.clipboard.writeText) navigator.clipboard.writeText(DW.txt);
        var b=document.getElementById('vz3-copy'); if(b){ b.textContent='✓ Kopyalandı'; setTimeout(function(){ try{ b.textContent='📋 Kopyala'; }catch(e){} },1500); } }catch(e){}
    };
    document.getElementById('vz3-pdf').onclick=function(){
      try{ if(typeof window.makePDF==='function') window.makePDF(DW.txt,DW.title,DW.cc); else alert(DW.txt); }catch(e){ alert(DW.txt); }
    };
    return d;
  }
  function showDoc(title,txt,cc,procId,g){
    DW={procId:procId||null,g:g||null,title:title,txt:txt,cc:cc||'DE'};
    var d=drawer();
    d.classList.remove('edit');
    var eb=document.getElementById('vz3-edit'),sb=document.getElementById('vz3-save');
    if(eb) eb.style.display=''; if(sb) sb.style.display='none';
    var te=document.getElementById('vz3-dt'); if(te) te.textContent='📄 '+title;
    var be=document.getElementById('vz3-db'); if(be) be.textContent=txt;
    d.classList.add('on');
  }

  /* ── uretim ── */
  function saveForms(g){
    var p={}; PF.forEach(function(f){ var el=document.getElementById('vz3-p-'+f[0]); if(el) p[f[0]]=String(el.value||'').trim(); });
    if(Object.keys(p).length) W('ch_vz_profil',Object.assign(prof(),p));
    var fs=DF[g]||[], a=J('ch_vz_docans',{}), cur=a[g]||{};
    fs.forEach(function(f){ var el=document.getElementById('vz3-d-'+g+'-'+f[0]); if(el) cur[f[0]]=String(el.value||'').trim(); });
    a[g]=cur; W('ch_vz_docans',a);
  }
  window.__v3Gen=function(g,btn){
    var d=dObj(); if(!d) return;
    saveForms(g);
    var old=btn.textContent; btn.textContent='⏳ Hazırlanıyor…'; btn.disabled=true;
    fetch(API()+'?action=aichat',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({message:prompt(g),history:[],provider:'claude',lang:UIL(),country:d.cc})})
     .then(function(r){ return r.json(); })
     .then(function(j){
        btn.textContent=old; btn.disabled=false;
        var txt=(j&&j.reply)?clean(j.reply):'';
        if(!txt){ alert('Belge üretilemedi: '+((j&&j.error)||'lütfen tekrar deneyin.')); return; }
        PREP[g]=(GEN[g]&&GEN[g].n)||g;
        saveDocToProc(g,txt);
        var ok=document.getElementById('vz3-ok-'+g); if(ok) ok.style.display='';
        var rw=document.getElementById('vz3-row-'+g); if(rw) rw.style.display='';
        showDoc(PREP[g],txt,d.cc,CURPROC,g);
     })
     .catch(function(){ btn.textContent=old; btn.disabled=false; alert('Bağlantı hatası. Lütfen tekrar deneyin.'); });
  };
  window.__v3View=function(g){
    var p=CURPROC?procOf(CURPROC):null;
    if(p&&p.docs[g]) showDoc(p.docs[g].n,p.docs[g].txt,p.docs[g].cc,p.id,g);
    else toast('Önce belgeyi hazırlayın');
  };

  /* ── pruefung ── */
  window.__v3Pruef=function(btn){
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

  /* ── ARSIV eylemleri ── */
  window.__v3Arc=function(){ STEP='arc'; draw(); };
  window.__v3ArcTgl=function(id){ var el=document.getElementById('vz3-proc-'+id); if(el) el.classList.toggle('open'); };
  window.__v3ArcView=function(id,g){ var p=procOf(id); if(p&&p.docs[g]) showDoc(p.docs[g].n,p.docs[g].txt,p.docs[g].cc,id,g); };
  window.__v3ArcPdf=function(id,g){ var p=procOf(id); if(p&&p.docs[g]){ try{ if(typeof window.makePDF==='function') window.makePDF(p.docs[g].txt,p.docs[g].n,p.docs[g].cc); else alert(p.docs[g].txt); }catch(e){} } };
  window.__v3ArcDlAll=function(id){
    var p=procOf(id); if(!p) return;
    var ks=Object.keys(p.docs); if(!ks.length){ toast('Bu işlemde kayıtlı belge yok'); return; }
    var parts=[],cc='DE';
    ks.forEach(function(g){ var dd=p.docs[g]; cc=dd.cc||cc;
      parts.push('═══════════════════════════════\n'+dd.n+'\n═══════════════════════════════\n\n'+dd.txt); });
    var all=parts.join('\n\n\n');
    try{ if(typeof window.makePDF==='function') window.makePDF(all,'Vize Dosyası — '+procTitle(p),cc); else alert(all); }catch(e){ alert(all); }
  };
  window.__v3ArcResume=function(id){
    var p=procOf(id); if(!p) return;
    S={dest:p.dest,type:p.type,from:p.from||'',to:p.to||'',city:p.city||'',emp:p.emp||'',payer:p.payer||''};
    CURPROC=p.id; PREP={};
    Object.keys(p.docs).forEach(function(g){ PREP[g]=p.docs[g].n; });
    STEP=4; draw();
    toast('🔁 İşlem yüklendi — kaldığınız yerden devam edin');
  };
  window.__v3ArcDel=function(id){
    if(!confirm('Bu vize işlemi ve içindeki belgeler silinsin mi?')) return;
    arcW(arc().filter(function(p){ return p.id!==id; }));
    if(CURPROC===id) CURPROC=null;
    draw(); toast('🗑 Silindi');
  };
  window.__v3Finish=function(){
    if(CURPROC||Object.keys(PREP).length){ ensureProc(); toast('💾 İşleminiz "Vize Dosyalarım"a kaydedildi'); }
    STEP='arc'; draw();
  };

  /* ── cizim ── */
  function ov(){ var o=document.getElementById('vz3-ov'); if(!o){ o=document.createElement('div'); o.id='vz3-ov'; document.body.appendChild(o); } return o; }
  function bar(n){ var h='<div class="vz3-steps">'; for(var i=1;i<=4;i++) h+='<div class="s'+(i<=n?' on':'')+'"></div>'; return h+'</div>'; }
  function fieldRow(id,label,val,ta){
    return '<div class="vz3-field"><label>'+esc(label)+'</label>'+(ta?('<textarea class="vz3-in" id="'+id+'" rows="3">'+esc(val)+'</textarea>'):('<input class="vz3-in" id="'+id+'" value="'+esc(val)+'">'))+'</div>';
  }
  function selRow(id,label,opts,val){
    var h='<div class="vz3-field"><label>'+esc(label)+'</label><select class="vz3-in" id="'+id+'"><option value="">— Seçin —</option>';
    opts.forEach(function(o){ h+='<option value="'+o[0]+'"'+(val===o[0]?' selected':'')+'>'+esc(o[1])+'</option>'; });
    return h+'</select></div>';
  }
  function draw(){
    var o=ov(); o.classList.add('on');
    var h='<div class="top"><h2>🛂 Vize <em>Asistanı</em></h2><button class="x" onclick="window.__v3Close()">✕ Kapat</button></div><div id="vz3-bd">';
    if(STEP==='arc'){
      var A=arc().slice().reverse();
      h+='<div class="vz3-h">📁 Vize Dosyalarım</div><div class="vz3-sub">Tamamlanan ve devam eden vize işlemleriniz. Her işlemi açıp belgeleri görüntüleyebilir, düzenleyebilir (überarbeiten), tek tek veya tümünü birden indirebilirsiniz.</div>';
      if(!A.length) h+='<div class="vz3-box" style="text-align:center;color:#9a9ab0;padding:26px">Henüz kayıtlı işlem yok.<br>Yeni bir vize işlemi başlatın — hazırladığınız her belge otomatik buraya kaydedilir.</div>';
      A.forEach(function(p){
        var ks=Object.keys(p.docs);
        h+='<div class="vz3-proc" id="vz3-proc-'+p.id+'"><div class="ph" onclick="window.__v3ArcTgl(\''+p.id+'\')"><div class="t"><div class="n">'+esc(procTitle(p))+'</div><div class="d">Oluşturma: '+esc(p.created||'')+' · '+ks.length+' belge</div></div><span style="color:#8e8e9c">▾</span></div><div class="pb">';
        if(!ks.length) h+='<div class="vz3-sub" style="margin:4px 0 8px">Bu işlemde henüz belge hazırlanmadı — "Devam et" ile hazırlayın.</div>';
        ks.forEach(function(g){
          h+='<div class="pd"><div class="dn">📄 '+esc(p.docs[g].n)+'</div>'
            +'<button onclick="window.__v3ArcView(\''+p.id+'\',\''+g+'\')">👁 Aç / ✏️</button>'
            +'<button onclick="window.__v3ArcPdf(\''+p.id+'\',\''+g+'\')">📄 PDF</button></div>';
        });
        h+='<div class="pr"><button class="gold" onclick="window.__v3ArcResume(\''+p.id+'\')">🔁 Devam et / Überarbeiten</button>'
          +'<button onclick="window.__v3ArcDlAll(\''+p.id+'\')">📦 Tümünü indir</button>'
          +'<button class="red" onclick="window.__v3ArcDel(\''+p.id+'\')">🗑 Sil</button></div>';
        h+='</div></div>';
      });
      h+='<div class="vz3-nav"><button class="vz3-btn" onclick="window.__v3New()">➕ Yeni vize işlemi başlat</button></div>';
    } else if(STEP===1){
      var cnt=arc().length;
      h+='<div class="vz3-h">Nereye seyahat edeceksiniz?</div><div class="vz3-sub">Hedef ülkeyi seçin — sorular, belgeler ve evrak listesi tamamen ona göre hazırlanır.</div>';
      if(cnt) h+='<div class="vz3-arcbtn" onclick="window.__v3Arc()"><span style="font-size:22px">📁</span><span class="a">Vize Dosyalarım — kayıtlı işlemleriniz</span><span class="c">'+cnt+'</span></div>';
      h+='<div class="vz3-grid">';
      DEST.forEach(function(d){ h+='<div class="vz3-card" onclick="window.__v3Pick(\''+d.k+'\')"><span class="ic">'+d.ic+'</span><span class="nm">'+esc(d.n)+'</span>'+(d.sb?'<span class="sb">'+esc(d.sb)+'</span>':'')+'</div>'; });
      h+='</div>';
    } else if(STEP===2){
      h+='<div class="vz3-h">Seyahat amacınız?</div><div class="vz3-sub">'+esc(dObj().ic+' '+dObj().n)+' için vize tipi — sorular ve belgeler buna göre değişir.</div><div class="vz3-chips">';
      TYPE.forEach(function(t){ h+='<div class="vz3-chip" onclick="window.__v3Type(\''+t.k+'\')">'+t.ic+' '+esc(t.n)+'</div>'; });
      h+='</div><div class="vz3-nav"><button class="vz3-btn ghost" onclick="window.__v3Back()">←</button></div>';
    } else if(STEP===3){
      h+='<div class="vz3-h">Seyahat ve durum bilgileri</div><div class="vz3-sub">Tarihleri takvimden seçin; durum soruları belge listenizi belirler.</div>';
      h+='<div class="vz3-2col"><div class="vz3-field"><label>Gidiş</label><input type="date" class="vz3-in" id="vz3-from" value="'+esc(S.from)+'"></div><div class="vz3-field"><label>Dönüş</label><input type="date" class="vz3-in" id="vz3-to" value="'+esc(S.to)+'"></div></div>';
      h+='<div class="vz3-field"><label>Şehir(ler) (opsiyonel)</label><input type="text" class="vz3-in" id="vz3-city" placeholder="ör. Lizbon, Porto" value="'+esc(S.city)+'"></div>';
      h+=selRow('vz3-emp','Çalışma durumunuz',EMP,S.emp);
      h+=selRow('vz3-payer','Masrafları kim karşılıyor?',PAYER,S.payer);
      h+='<div class="vz3-nav"><button class="vz3-btn ghost" onclick="window.__v3Back()">←</button><button class="vz3-btn" onclick="window.__v3Go()">Belgelerimi hazırla →</button></div>';
    } else {
      var d=dObj(), gl=docsFor(), p=prof(), pt=PORTALS[d.k]||PORTALS.schengen;
      h+='<div class="vz3-h">'+esc(d.ic+' '+d.n)+' · '+esc(tObj().n)+'</div><div class="vz3-sub">'+esc((S.from||'?')+' → '+(S.to||'?'))+(S.city?(' · '+esc(S.city)):'')+'</div>';
      h+='<div class="vz3-flow">'
        +'<div class="st" onclick="window.__v3Jump(\'info\')"><span class="n">1</span><span class="l">Bilgilerim</span></div>'
        +'<div class="st" onclick="window.__v3Jump(\'docs\')"><span class="n">2</span><span class="l">Belgeler</span></div>'
        +'<div class="st" onclick="window.__v3Jump(\'cl\')"><span class="n">3</span><span class="l">Evraklar</span></div>'
        +'<div class="st" onclick="window.__v3Jump(\'pruef\')"><span class="n">4</span><span class="l">Prüfung</span></div>'
        +'<div class="st" onclick="window.__v3Jump(\'rdv\')"><span class="n">5</span><span class="l">Randevu</span></div>'
        +'<div class="st" onclick="window.__v3Arc()"><span class="n">📁</span><span class="l">Dosyalarım</span></div></div>';
      h+='<div class="vz3-sec" id="vz3-sec-info">🪪 Bilgilerim <span style="font-size:10px;color:#8e8e9c;font-weight:600">(bir kez girin — tüm belgelerde kullanılır)</span></div><div class="vz3-box">';
      PF.forEach(function(f){ h+=fieldRow('vz3-p-'+f[0],f[1],p[f[0]]||'',0); });
      h+='</div>';
      h+='<div class="vz3-sec" id="vz3-sec-docs">📄 Belgeler <span style="font-size:10px;color:#8e8e9c;font-weight:600">('+esc(LN[d.ll]||'English')+' dilinde, A4 · hazırlananlar otomatik kaydedilir)</span></div>';
      if(!gl.length) h+='<div class="vz3-sub">Bu seçim için otomatik belge yok; evrak listesini takip edin.</div>';
      gl.forEach(function(g){
        var m=GEN[g]||{n:g,d:''}, a=docans(g), fs=DF[g]||[];
        h+='<div class="vz3-doc" id="vz3-doc-'+g+'"><div class="hd" onclick="window.__v3Tgl(\''+g+'\')"><div class="t"><div class="n">'+esc(m.n)+'</div><div class="d">'+esc(m.d)+'</div></div>'
          +'<span class="okb" id="vz3-ok-'+g+'" style="display:'+(PREP[g]?'':'none')+'">✓ hazırlandı</span><span style="color:#8e8e9c">▾</span></div><div class="bd">';
        fs.forEach(function(f){ h+=fieldRow('vz3-d-'+g+'-'+f[0],f[1],a[f[0]]||'',f[2]); });
        h+='<button class="go" onclick="window.__v3Gen(\''+g+'\',this)">📄 Belgeyi Hazırla</button>'
          +'<div class="row" id="vz3-row-'+g+'" style="display:'+(PREP[g]?'':'none')+'"><button onclick="window.__v3View(\''+g+'\')">👁 Aç / ✏️ Düzenle</button><button onclick="window.__v3ArcPdf(String(window.__v3Cur||\'\'),\''+g+'\')">📄 PDF</button></div>'
          +'</div></div>';
      });
      h+='<div class="vz3-sec" id="vz3-sec-cl">✅ Toplamanız Gereken Evraklar</div><div class="vz3-sub" style="margin-top:-2px">Liste durumunuza göre kurulur; konsolosluğun / VFS sayfasının güncel listesini de teyit edin.</div>';
      checklist().forEach(function(grp){
        h+='<div class="vz3-cl-grp"><div class="gt">'+esc(grp.g)+'</div>';
        grp.it.forEach(function(it){
          h+='<div class="vz3-cl-it"><div class="tx">'+esc(it.t)+(it.nt?'<div class="nt">'+esc(it.nt)+'</div>':'')+'</div>'
            +'<span class="vz3-badge '+(it.s==='ch'?'ch">ChatHelp':'of">Resmi kaynak')+'</span></div>';
        });
        h+='</div>';
      });
      h+='<div class="vz3-sec" id="vz3-sec-pruef">🧪 Prüfung — Son Kontrol</div>'
        +'<div class="vz3-sub" style="margin-top:-2px">Belgeleriniz ve evrak listeniz denetlenir; eksikler ve hazırlık yüzdesi raporlanır.</div>'
        +'<button class="vz3-btn" style="width:100%" onclick="window.__v3Pruef(this)">🧪 Son Kontrolü Başlat</button>';
      h+='<div class="vz3-sec" id="vz3-sec-rdv">📅 Randevu</div>'
        +'<a class="vz3-rdv" href="'+esc(pt.u)+'" target="_blank" rel="noopener noreferrer"><span style="font-size:24px">📅</span><span style="flex:1"><span class="a">'+esc(pt.n)+'</span><span class="b">Resmi randevu portalını açar ↗</span></span></a>'
        +'<div class="vz3-note">1️⃣ Hesap oluştur · 2️⃣ Formu doldur · 3️⃣ Ücreti öde · 4️⃣ Randevu saatini seç · 5️⃣ Belgelerinle randevuya git<br>⚠️ ChatHelp randevu almaz; yalnızca resmî sayfaya yönlendirir.</div>';
      h+='<div class="vz3-nav"><button class="vz3-btn ghost" onclick="window.__v3Back()">←</button><button class="vz3-btn" onclick="window.__v3Finish()">✅ Bitir & Kaydet</button></div>';
    }
    h+='</div>';
    o.innerHTML=h;
    try{ window.__v3Cur=CURPROC; }catch(e){}
    if(STEP===3){
      ['vz3-from','vz3-to'].forEach(function(id){
        var el=document.getElementById(id); if(!el) return;
        try{ var dd=new Date(); var mm=('0'+(dd.getMonth()+1)).slice(-2), da=('0'+dd.getDate()).slice(-2); el.setAttribute('min',dd.getFullYear()+'-'+mm+'-'+da); }catch(e){}
        el.addEventListener('click',function(){ try{ if(typeof el.showPicker==='function') el.showPicker(); }catch(e){} });
        el.addEventListener('focus',function(){ try{ if(typeof el.showPicker==='function') el.showPicker(); }catch(e){} });
        el.onchange=function(){ if(id==='vz3-from')S.from=el.value; else S.to=el.value; };
      });
      var c=document.getElementById('vz3-city'); if(c) c.oninput=function(){ S.city=c.value; };
      var e1=document.getElementById('vz3-emp'); if(e1) e1.onchange=function(){ S.emp=e1.value; };
      var e2=document.getElementById('vz3-payer'); if(e2) e2.onchange=function(){ S.payer=e2.value; };
    }
  }
  window.__v3Close=function(){ var o=document.getElementById('vz3-ov'); if(o) o.classList.remove('on'); var d=document.getElementById('vz3-dw'); if(d){ d.classList.remove('on'); d.classList.remove('edit'); } };
  window.__v3New=function(){ S={dest:null,type:null,from:'',to:'',city:'',emp:'',payer:''}; PREP={}; CURPROC=null; STEP=1; draw(); };
  window.__v3Pick=function(k){ S.dest=k; STEP=2; draw(); };
  window.__v3Type=function(k){ S.type=k; STEP=3; draw(); };
  window.__v3Back=function(){ if(STEP==='arc'){ STEP=S.dest?4:1; draw(); return; } if(STEP>1){ STEP--; draw(); } };
  window.__v3Go=function(){
    var f=document.getElementById('vz3-from'),t=document.getElementById('vz3-to'),c=document.getElementById('vz3-city'),e1=document.getElementById('vz3-emp'),e2=document.getElementById('vz3-payer');
    if(f)S.from=f.value; if(t)S.to=t.value; if(c)S.city=c.value; if(e1)S.emp=e1.value; if(e2)S.payer=e2.value;
    if(!S.from||!S.to){ alert('Lütfen gidiş ve dönüş tarihini seçin.'); return; }
    if(!S.emp){ alert('Lütfen çalışma durumunuzu seçin — belge listeniz buna göre kurulur.'); return; }
    if(!S.payer) S.payer='kendim';
    STEP=4; draw();
  };
  window.__v3Tgl=function(g){ var el=document.getElementById('vz3-doc-'+g); if(el) el.classList.toggle('open'); };
  window.__v3Jump=function(sec){ var el=document.getElementById('vz3-sec-'+sec); if(el&&el.scrollIntoView) el.scrollIntoView({behavior:'smooth',block:'start'}); };

  /* ── kisa sablon karti -> Asistan; TR vize paneline buyuk giris ── */
  var kn=0;(function killLoop(){
    try{
      var p=document.getElementById('pnl-trvisa');
      if(p){
        var wrap=p.querySelector('.trv-wrap');
        if(wrap && !wrap.querySelector('#vz3-entry')){
          var e=document.createElement('div'); e.id='vz3-entry';
          e.style.cssText='border:1.5px solid #d4a84a;border-radius:13px;padding:15px;margin-bottom:14px;cursor:pointer;background:linear-gradient(135deg,rgba(212,168,74,.16),rgba(212,168,74,.04))';
          e.innerHTML='<div style="font-size:14.5px;font-weight:800;color:#fff">🛂 Vize Asistanı — tüm vize işlemleri tek yerde</div>'
            +'<div style="font-size:11.5px;color:#99a;margin-top:4px">Ülke + amaç seçin → sorulara cevap verin → konsolosluk düzeyinde belgeler hazırlansın → düzenleyin, tek tek veya tümünü indirin. İşlemleriniz "Vize Dosyalarım"da saklanır.</div>';
          e.addEventListener('click',function(){ try{ window.chVizeAsistan(); }catch(err){} });
          wrap.insertBefore(e,wrap.firstChild);
        }
      }
    }catch(e){}
    if(kn++<240) setTimeout(killLoop,600);
  })();

  /* ── kurulum: tum giris noktalari V3'u acar (V2 uykuya gecer) ── */
  function install(){
    window.chVizeAsistan=function(destK){
      S={dest:destK||null,type:null,from:'',to:'',city:'',emp:'',payer:''}; PREP={}; CURPROC=null;
      STEP=destK?2:1; draw();
    };
    window.chVizeAsistan.__v2=1; /* V2 guard'ini sustur */
    window.chVizeAsistan.__v3=1;
  }
  install();
  var n=0;(function guard(){ try{ if(!(window.chVizeAsistan&&window.chVizeAsistan.__v3)) install(); }catch(e){} if(n++<300) setTimeout(guard,400); })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'v3').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-vizev3-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_VIZE_V3')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_VIZE_V3 diskte (".strlen($chk)." bayt)\n";
echo "\n✓ VIZE ASISTANI V3 CANLI — ANA KONSEPT:\n";
echo "   • 23 ulke (PT Portekiz, BE, AT, CH, SE, DK, NO, PL, CZ, HU, AE, JP yeni)\n";
echo "   • 8 amac (Calisma, Tedavi, Etkinlik yeni) — her amacin kendi sorulari\n";
echo "   • Calisma durumu + masraf sorusu -> 11 belge tipinden otomatik set\n";
echo "   • VIZE DOSYALARIM: her islem otomatik kaydedilir; ac, DEVAM ET,\n";
echo "     belgeleri TEK TEK veya TUMUNU BIRDEN indir, sil\n";
echo "   • Her belge panelde ✏️ elle + 🤖 AI talimatiyla duzenlenir, 💾 kaydedilir\n";
echo "   • Kisa sablonlu 'Vize Formlari' karti kaldirildi -> her sey Asistanda\n";
