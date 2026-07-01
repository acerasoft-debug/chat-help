<?php
/**
 * ChatHelp — Schengen "Gerekli Belgeler Kontrol Listesi" — hangi belge ChatHelp'te üretilir,
 * hangisi resmi kaynaktan alınmalı, tek ekranda net gösterilir.
 * KULLANIM: chat-help.com/chat/apply-turkiye-checklist.php -> opcache-reset.php. SİL.
 * GEREKLİ: apply-turkiye-schengen.php önce çalıştırılmış olmalı (tr_schengen kategorisi).
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-turkiye-checklist BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_TR_SCHENGEN_CHECKLIST")!==false) exit("Zaten ekli.\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-trchecklist-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_TR_SCHENGEN_CHECKLIST — vize kontrol listesi: ChatHelp'te oluşturulabilenler vs resmi kaynaktan alınması gerekenler */
try{(function(){
  var CHECKLIST_KEY="tr_schengen_checklist";

  var CHECKLIST_DOC = {
    ic:"✅",
    n:"Gerekli Belgeler Kontrol Listesi (Schengen)",
    cat:"tr_schengen",
    tier:"einfach",
    isChecklist:true,
    q:[
      {k:"amac",l:"Seyahat amacı",r:true,opts:["Turizm","Aile/Arkadaş ziyareti","İş","Eğitim/Öğrenci","Konferans/Etkinlik"]},
      {k:"ulke",l:"Başvurulacak Schengen ülkesi",r:true}
    ],
    checklist:[
      {grp:"Kimlik & Seyahat Belgeleri", items:[
        {t:"Geçerli pasaport (en az 3 ay geçerlilik, 2 boş sayfa)", src:"resmi", note:"Nüfus Müdürlüğü / pasaport şubesi"},
        {t:"Biyometrik fotoğraf (son 6 ay, Schengen standardında)", src:"resmi", note:"Fotoğrafçı"},
        {t:"Nüfus cüzdanı fotokopisi", src:"resmi"},
        {t:"Önceki vize/pasaport sayfaları (varsa)", src:"resmi"}
      ]},
      {grp:"Resmi Başvuru Belgeleri", items:[
        {t:"Vize başvuru formu (konsolosluğun kendi formu)", src:"resmi", note:"Konsolosluk/VFS/iDATA web sitesinden"},
        {t:"Biyometrik randevu onayı", src:"resmi", note:"Randevu sistemi üzerinden alınır"},
        {t:"Seyahat sağlık sigortası poliçesi (min. 30.000 € teminat)", src:"resmi", note:"Sigorta şirketinden satın alınır"}
      ]},
      {grp:"ChatHelp ile Oluşturabileceğiniz Belgeler", items:[
        {t:"Seyahat Amacı Beyanı (Cover Letter)", src:"chathelp", doc:"tr_schengen_amac_beyani"},
        {t:"Davet Mektubu (varsa aile/arkadaş)", src:"chathelp", doc:"tr_schengen_davet_aile"},
        {t:"İş Daveti Mektubu (iş amaçlıysa)", src:"chathelp", doc:"tr_schengen_davet_is"},
        {t:"İşveren İzin/Görevlendirme Yazısı", src:"chathelp", doc:"tr_schengen_izin_yazisi"},
        {t:"Mali Durum / Sponsorluk Beyanı", src:"chathelp", doc:"tr_schengen_mali_beyan"},
        {t:"Konaklama Bilgi Formu", src:"chathelp", doc:"tr_schengen_konaklama_beyan"},
        {t:"Seyahat Planı (İtinerary)", src:"chathelp", doc:"tr_schengen_seyahat_plani"},
        {t:"Öğrenci İzin Belgesi Talebi (öğrenciyseniz)", src:"chathelp", doc:"tr_schengen_ogrenci_izin"},
        {t:"Emekli/Serbest Meslek Gelir Beyanı", src:"chathelp", doc:"tr_schengen_emekli_gelir"},
        {t:"Reşit Olmayan İçin Ebeveyn İzin Belgesi (çocuk varsa)", src:"chathelp", doc:"tr_schengen_cocuk_izin"}
      ]},
      {grp:"Mali/Kanıt Belgeleri (Resmi Kaynak)", items:[
        {t:"Son 3 aylık banka hesap dökümü", src:"resmi", note:"Bankanızdan / e-Devlet / internet bankacılığı"},
        {t:"Uçak bileti rezervasyon teyidi (henüz satın alınmamış olabilir)", src:"resmi", note:"Seyahat acentesi/havayolu"},
        {t:"Otel rezervasyon teyidi", src:"resmi", note:"Booking.com vb."},
        {t:"SGK/vergi kaydı veya işveren belgesi (gelir kanıtı)", src:"resmi", note:"SGK / işvereninizden"}
      ]}
    ]
  };

  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      if(!DOCS[CHECKLIST_KEY]) DOCS[CHECKLIST_KEY]=CHECKLIST_DOC;
      if(typeof DOC_TIER!=="undefined") DOC_TIER[CHECKLIST_KEY]="einfach";
      if(typeof CATS!=="undefined" && CATS.tr_schengen && CATS.tr_schengen.docs && CATS.tr_schengen.docs.indexOf(CHECKLIST_KEY)<0){
        CATS.tr_schengen.docs.unshift(CHECKLIST_KEY);
      }
      if(typeof CAT_DOCS!=="undefined" && CAT_DOCS.tr_schengen){
        var ex=false; for(var i=0;i<CAT_DOCS.tr_schengen.length;i++){ if(CAT_DOCS.tr_schengen[i].k===CHECKLIST_KEY){ex=true;break;} }
        if(!ex) CAT_DOCS.tr_schengen.unshift({k:CHECKLIST_KEY,ic:CHECKLIST_DOC.ic,t:CHECKLIST_DOC.n,tier:"einfach"});
      }
      return !!DOCS[CHECKLIST_KEY];
    }catch(e){ return false; }
  }

  /* Kontrol listesi ekranı: showQuestions'ın normal akışını kullanmak yerine,
     qS(k) çağrıldığında bu belge ise checklist görünümünü göster (mevcut akışı bozmaz, ek katman) */
  function renderChecklistView(doc){
    var html = '<div style="padding:4px 2px">';
    html += '<p style="font-size:13px;color:#c8c8e0;margin-bottom:14px">Aşağıdaki liste genel bir Schengen vizesi başvurusu içindir; konsolosluğa göre küçük farklar olabilir — güncel listeyi başvuracağınız ülkenin konsolosluk/VFS sayfasından teyit edin.</p>';
    doc.checklist.forEach(function(grp){
      html += '<div style="margin-bottom:16px"><div style="font-weight:800;color:#d4a84a;font-size:13px;margin-bottom:8px">'+grp.grp+'</div>';
      grp.items.forEach(function(it){
        var badge = it.src==="chathelp"
          ? '<span style="font-size:10px;font-weight:800;color:#1b1b1e;background:linear-gradient(135deg,#d4a84a,#f0c860);padding:2px 8px;border-radius:100px;white-space:nowrap">ChatHelp ile oluştur</span>'
          : '<span style="font-size:10px;font-weight:800;color:#cfcfdd;background:rgba(255,255,255,.08);padding:2px 8px;border-radius:100px;white-space:nowrap">Resmi kaynaktan alın</span>';
        html += '<div style="display:flex;align-items:flex-start;gap:10px;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.06)">';
        html += '<div style="flex:1"><div style="font-size:13px;color:#e7e7ef">'+it.t+'</div>';
        if(it.note) html += '<div style="font-size:11px;color:#8e8e9c;margin-top:2px">'+it.note+'</div>';
        html += '</div>'+badge;
        if(it.src==="chathelp" && it.doc){
          html += '<button style="margin-left:6px;background:rgba(212,168,74,.12);border:1px solid rgba(212,168,74,.4);color:#d4a84a;border-radius:8px;padding:5px 10px;font-size:11px;font-weight:700;cursor:pointer" onclick="(function(){try{if(typeof qS===\'function\')qS(\''+it.doc+'\');}catch(e){}})()">Oluştur</button>';
        }
        html += '</div>';
      });
      html += '</div>';
    });
    html += '</div>';
    return html;
  }

  function wrapQS(){
    try{
      if(typeof qS!=="function" || qS.__checklist) return;
      var _qs=qS;
      var w=function(k){
        try{
          if(typeof DOCS!=="undefined" && DOCS[k] && DOCS[k].isChecklist){
            if(typeof addMsg==="function"){
              var c=document.createElement("div");
              c.innerHTML=renderChecklistView(DOCS[k]);
              addMsg("ai",c);
              return;
            }
          }
        }catch(e){}
        return _qs.apply(this,arguments);
      };
      w.__checklist=1; qS=w; if(typeof window!=="undefined") window.qS=w;
    }catch(e){}
  }

  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } wrapQS(); if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src!==$start){ file_put_contents($file,$src); echo "Kontrol listesi eklendi (CH_TR_SCHENGEN_CHECKLIST).\n"; }
else echo "degisiklik yok\n";
echo "\nSONRA: opcache-reset.php. SIL: rm apply-turkiye-checklist.php\n";
echo "Test: TR -> Schengen Vizesi Belgeleri -> 'Gerekli Belgeler Kontrol Listesi' en ustte gorunmeli.\n";
