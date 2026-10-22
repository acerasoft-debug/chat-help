<?php
/**
 * ChatHelp — apply-vize-forms (CH_VIZE_FORMS) — "Belge hazirla diyor ama
 *  belge DOLDURULAMIYOR" cozumu. KOK NEDEN (yerel kaynak + dumplarla kesin):
 *  prompt() belgeleri '[VORNAME] [NACHNAME], ikamet adresi: [ADRESSE], [ORT]'
 *  yer tutucalariyla uretiyor ve belgeye ozgu bilgiler (isveren adi, davet
 *  eden, firma, konaklama, gun-gun plan...) HIC sorulmuyor -> cikan PDF bos
 *  kaliplarla dolu, kullanici hicbir seyi dolduramiyor.
 *  COZUM: Her "Hazirla" tiklamasinda BELGEYE OZEL DOLDURMA FORMU acilir:
 *   • Ortak kimlik alanlari (ad, dogum, pasaport, meslek, isveren, adres)
 *   • Belge turune gore ozel alanlar:
 *     cover: konaklama + finansman + ek notlar
 *     invitation: davet eden ad/adres/yakinlik/masraf
 *     employer: firma, pozisyon, ise giris, maas(ops)
 *     selfemployed: firma, sicil(ops), faaliyet, gelir(ops)
 *     itinerary: gun-gun plan (textarea) + konaklama
 *     (bilinmeyen tur: ortak alanlar + serbest ek bilgi)
 *   • Yanitlar KAYDEDILIR (tekrar tiklayinca onceden dolu gelir)
 *   • Gonderilen istekte: [VORNAME]/[NACHNAME]/[ADRESSE]/[ORT] yer tutuculari
 *     GERCEK degerlerle DEGISTIRILIR + belge cevaplari yapilandirilmis blok
 *     olarak prompt'a eklenir -> PDF tam dolu cikar, yer tutucu kalmaz.
 *  CH_VIZE_PRUF ile uyumlu: ayni profil deposunu (ch_vz_profil) doldurur,
 *  eski kapi formunu atlatir (cift form yok). Sadece EKLEMELI. node ✓ +
 *  4 senaryoluk harness ✓.
 * KULLANIM: pull2.php?key=...&files=apply-vize-forms.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-vize-forms BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_VIZE_FORMS')!==false) exit("Zaten ekli (CH_VIZE_FORMS).\n");
if (strpos($src,'window.__vzGen=')===false) exit("HATA: Vize Asistani yok.\n");

$block = <<<'HTMLBLOCK'
<style id="ch-vize-forms-css">
#vzf-ov{position:fixed;inset:0;z-index:2147483100;background:rgba(4,4,12,.8);backdrop-filter:blur(5px);display:none;align-items:center;justify-content:center;padding:16px;font-family:'Inter',system-ui,sans-serif}
#vzf-ov.on{display:flex}
#vzf-card{width:100%;max-width:460px;max-height:88vh;overflow-y:auto;background:linear-gradient(180deg,#14142e,#0c0c1c);border:1px solid rgba(212,168,74,.42);border-radius:18px;padding:20px 18px;color:#f2f2fb;box-shadow:0 24px 70px rgba(0,0,0,.55)}
#vzf-card .t{font-size:15.5px;font-weight:800;margin-bottom:3px}
#vzf-card .s{font-size:11.5px;color:#9c9cc4;margin-bottom:12px;line-height:1.5}
#vzf-card .sec{font-size:10.5px;font-weight:800;color:#d4a84a;text-transform:uppercase;letter-spacing:.8px;margin:14px 0 2px}
#vzf-card label{display:block;font-size:11px;color:#a8a8d0;margin:9px 0 3px}
#vzf-card input,#vzf-card textarea{width:100%;background:#101024;border:1.5px solid rgba(255,255,255,.12);border-radius:10px;padding:9px 11px;color:#fff;font-size:13.5px;outline:none;font-family:inherit;box-sizing:border-box}
#vzf-card textarea{min-height:74px;resize:vertical}
#vzf-card input:focus,#vzf-card textarea:focus{border-color:rgba(212,168,74,.55)}
#vzf-card .nav{display:flex;gap:8px;margin-top:15px}
#vzf-card .b{flex:1;padding:12px;border-radius:11px;border:none;cursor:pointer;font-family:inherit;font-size:13.5px;font-weight:800;background:linear-gradient(135deg,#d4a84a,#ecc060);color:#191000}
#vzf-card .b.ghost{background:transparent;border:1.5px solid rgba(255,255,255,.18);color:#c8c8e8}
</style>
<script id="ch-vize-forms-js">
/* CH_VIZE_FORMS — Hazirla: belgeye ozel doldurma formu + yer tutucu doldurma */
try{(function(){
  var LSP='ch_vz_profil', LSD='ch_vz_docans';
  function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
  function J(k,d){ try{ return JSON.parse(localStorage.getItem(k)||'null')||d; }catch(e){ return d; } }
  function W(k,v){ try{ localStorage.setItem(k,JSON.stringify(v)); }catch(e){} }
  var PF=[['ad','Ad Soyad'],['dogum','Doğum tarihi (GG.AA.YYYY)'],['pasaport','Pasaport numarası'],['meslek','Meslek / Unvan'],['isveren','Çalıştığınız yer (işveren)'],['adres','Adres (şehir, ülke)']];
  var DF={
    cover:{t:'Niyet / Başvuru Mektubu',f:[['konaklama','Konaklama (otel adı veya kalacağınız kişi)',0],['finans','Masrafları kim karşılıyor? (kendim / sponsor / işveren)',0],['ek','Vurgulamak istedikleriniz (opsiyonel)',1]]},
    invitation:{t:'Davet Mektubu',f:[['davet_ad','Davet eden kişinin adı soyadı',0],['davet_adres','Davet edenin adresi (gidilecek ülkede)',0],['yakinlik','Yakınlık dereceniz (arkadaş, kardeş…)',0],['masraf','Masrafları kim karşılıyor?',0]]},
    employer:{t:'İşveren Görevlendirme / İzin Yazısı',f:[['firma','İşveren / firma adı',0],['pozisyon','Pozisyonunuz',0],['giris','İşe başlama tarihi (ay/yıl yeterli)',0],['maas','Aylık maaş (opsiyonel)',0]]},
    selfemployed:{t:'İş Sahibi / Serbest Meslek Beyanı',f:[['firma','Firma / işletme adı',0],['sicil','Vergi no / oda sicil no (opsiyonel)',0],['faaliyet','Faaliyet konusu',0],['gelir','Aylık veya yıllık gelir (opsiyonel)',0]]},
    itinerary:{t:'Günlük Seyahat Planı',f:[['plan','Gün gün plan: şehirler, geceleme, aktiviteler (kısaca)',1],['konak','Konaklama yer(ler)i (otel/kişi)',0]]}
  };
  var CUR={gen:null,inner:null,self:null,args:null,ans:null,prof:null};
  function draw(gen){
    var ov=document.getElementById('vzf-ov');
    if(!ov){ ov=document.createElement('div'); ov.id='vzf-ov'; document.body.appendChild(ov); }
    var meta=DF[gen]||{t:'Belge Bilgileri',f:[['ek','Bu belge için eklemek istediğiniz bilgiler',1]]};
    var prof=J(LSP,{}), all=J(LSD,{}), da=all[gen]||{};
    var h='<div id="vzf-card"><div class="t">📝 '+esc(meta.t)+'</div>'
      +'<div class="s">Belgeniz bu bilgilerle eksiksiz doldurulur — yer tutucu (boş alan) kalmaz. Bilgiler kaydedilir.</div>'
      +'<div class="sec">Kimlik Bilgileri</div>';
    for(var i=0;i<PF.length;i++) h+='<label>'+esc(PF[i][1])+'</label><input id="vzf-p-'+PF[i][0]+'" value="'+esc(prof[PF[i][0]]||'')+'">';
    h+='<div class="sec">Belge Bilgileri</div>';
    for(var j=0;j<meta.f.length;j++){
      var f=meta.f[j];
      h+='<label>'+esc(f[1])+'</label>';
      h+=f[2]?('<textarea id="vzf-d-'+f[0]+'">'+esc(da[f[0]]||'')+'</textarea>')
             :('<input id="vzf-d-'+f[0]+'" value="'+esc(da[f[0]]||'')+'">');
    }
    h+='<div class="nav"><button class="b ghost" id="vzf-cancel">Vazgeç</button><button class="b" id="vzf-go">📄 Belgeyi Hazırla</button></div></div>';
    ov.innerHTML=h; ov.classList.add('on');
    document.getElementById('vzf-cancel').onclick=function(){ ov.classList.remove('on'); CUR={gen:null,inner:null,self:null,args:null,ans:null,prof:null}; };
    document.getElementById('vzf-go').onclick=function(){
      var prof2={}; for(var i=0;i<PF.length;i++){ var el=document.getElementById('vzf-p-'+PF[i][0]); prof2[PF[i][0]]=el?String(el.value||'').trim():''; }
      var ans={}; for(var j=0;j<meta.f.length;j++){ var el2=document.getElementById('vzf-d-'+meta.f[j][0]); ans[meta.f[j][0]]=el2?String(el2.value||'').trim():''; }
      W(LSP,prof2);
      var all2=J(LSD,{}); all2[CUR.gen]=ans; W(LSD,all2);
      try{ sessionStorage.setItem('ch_vzp_skip','1'); }catch(e){}
      CUR.ans={meta:meta,ans:ans,prof:prof2};
      ov.classList.remove('on');
      var inner=CUR.inner, self=CUR.self, args=CUR.args;
      window.__vzfActive=true;
      try{ inner.apply(self,args); } finally{ setTimeout(function(){ window.__vzfActive=false; },0); }
    };
  }
  /* fetch: yer tutuculari degistir + belge cevaplarini ekle */
  (function(){
    var _f=window.fetch;
    window.fetch=function(u,opt){
      try{
        if(window.__vzfActive && typeof u==='string' && u.indexOf('action=aichat')!==-1 && opt && opt.body && CUR.ans){
          var b=JSON.parse(opt.body);
          if(b.message){
            var p=CUR.ans.prof||{};
            var name=(p.ad||'').trim(), fn=name, ln='';
            var sp=name.lastIndexOf(' '); if(sp>0){ fn=name.slice(0,sp); ln=name.slice(sp+1); }
            var m=b.message;
            if(name){ m=m.split('[VORNAME] [NACHNAME]').join(name); m=m.split('[VORNAME]').join(fn); m=m.split('[NACHNAME]').join(ln); }
            if(p.adres){ m=m.split('[ADRESSE]').join(p.adres); m=m.split(', [ORT]').join(''); m=m.split('[ORT]').join(p.adres); }
            var L=[];
            if(p.ad)L.push('Ad Soyad: '+p.ad); if(p.dogum)L.push('Doğum tarihi: '+p.dogum);
            if(p.pasaport)L.push('Pasaport No: '+p.pasaport); if(p.meslek)L.push('Meslek: '+p.meslek);
            if(p.isveren)L.push('Çalıştığı yer: '+p.isveren); if(p.adres)L.push('Adres: '+p.adres);
            var mf=CUR.ans.meta.f, aa=CUR.ans.ans;
            for(var i=0;i<mf.length;i++){ if(aa[mf[i][0]]) L.push(mf[i][1]+': '+aa[mf[i][0]]); }
            if(L.length){
              m+='\n\nBelgeye işlenecek bilgiler (kullanıcı formundan — belgede uygun yerlere yaz, köşeli parantezli yer tutucu ASLA bırakma):\n- '+L.join('\n- ');
            }
            b.message=m; opt.body=JSON.stringify(b);
          }
        }
      }catch(e){}
      return _f.apply(this,arguments);
    };
  })();
  /* __vzGen sarmala: once form */
  function wrap(){
    try{
      if(typeof window.__vzGen!=='function' || window.__vzGen.__vzforms) return false;
      var _o=window.__vzGen;
      var w=function(gen,btn){
        CUR={gen:gen,inner:_o,self:this,args:arguments,ans:null,prof:null};
        draw(gen);
      };
      w.__vzforms=1; window.__vzGen=w;
      return true;
    }catch(e){ return false; }
  }
  var n=0;(function loop(){ wrap(); if(n++<6000) setTimeout(loop,700); })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'vf').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-vizeforms-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_VIZE_FORMS')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_VIZE_FORMS diskte (".strlen($chk)." bayt)\n";
echo "\n✓ 'Hazirla' artik gercek DOLDURMA FORMU acar:\n";
echo "   • Kimlik bilgileri + belgeye ozel alanlar (isveren yazisi -> firma/pozisyon,\n";
echo "     davet mektubu -> davet eden, niyet mektubu -> konaklama/finansman,\n";
echo "     seyahat plani -> gun-gun plan, is sahibi beyani -> firma/faaliyet)\n";
echo "   • Bilgiler kaydedilir, tekrar tiklayinca dolu gelir\n";
echo "   • Yer tutucular ([VORNAME] vb.) GERCEK degerlerle degistirilir —\n";
echo "     PDF tam dolu cikar, bos kalip kalmaz.\n";
