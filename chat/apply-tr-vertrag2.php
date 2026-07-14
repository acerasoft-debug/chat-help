<?php
/**
 * ChatHelp — apply-tr-vertrag2 (CH_TR_VERTRAG2) — TR Adim 2: duz-kart sozlesmeleri
 *  kaldir + kategoriyi module yonlendir.
 *   (A) Module 3 ek sozlesme (ev kira paylasim, ikale, teslim tutanagi) -> __TRK2 augment
 *   (B) 'tr_sozlesme' kategorisi tiklaninca Vertrag modulunu ac (showCat/showCatDocs wrap)
 *   (C) tr_konut'tan SADECE "Kira Sözleşmesi (Konut)" belgesini kaldir (dilekceler kalir)
 *  EKLEMELI script (</body> oncesi). apply-tr-vertrag SONRASI calismali (__TRK2 augment).
 *  Gomulu JS node --check ile dogrulandi.
 * KULLANIM: pull2.php?key=...&files=apply-tr-vertrag2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-tr-vertrag2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_TR_VERTRAG')===false) exit("HATA: once apply-tr-vertrag uygulanmali (CH_TR_VERTRAG yok).\n");
if (strpos($src,'CH_TR_VERTRAG2')!==false) exit("Zaten ekli (CH_TR_VERTRAG2).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-tr-vertrag2">
/* CH_TR_VERTRAG2 — TR duz-kart sozlesmeleri module tasi (kategori yonlendir + belge kaldir) */
try{(function(){
  var UC=[["uc_t1","Ek Madde 1 — Başlık",""],["uc_b1","Ek Madde 1 — Metin","","ta"],["uc_t2","Ek Madde 2 — Başlık (opsiyonel)",""],["uc_b2","Ek Madde 2 — Metin (opsiyonel)","","ta"]];
  function ucStep(){ return {t:"Kendi Maddeleriniz (opsiyonel)",f:UC}; }

  /* ── (A) module 3 ek sozlesme (apply-tr-vertrag __TRK2'yi augment) ── */
  window.__TRK2 = window.__TRK2 || {};
  if(!window.__TRK2["vtr_tr_ev_kira_paylasim"]) window.__TRK2["vtr_tr_ev_kira_paylasim"]={ic:"🏘️",n:"Ev Arkadaşlığı (Kira Paylaşım) Sözleşmesi",sn:"Ortak konutta kira ve gider paylaşımı",price:"3,99 €",pgs:3,steps:[
    {t:"Taraflar ve Konut",f:[["ana_kiraci","Ana kiracı: ad soyad, T.C., adres","","ta"],["ev_arkadasi","Ev arkadaşı: ad soyad, T.C.","","ta"],["konut","Ortak konut (tam adres, oda sayısı)","","ta"]]},
    {t:"Paylaşım",f:[["oda","Ev arkadaşına tahsis edilen oda / alan"],["kira_pay","Aylık kira payı (TL)"],["gider_pay","Ortak giderlerin paylaşımı","aidat, elektrik, su, doğalgaz ve internet eşit olarak paylaşılır"],["depozito","Depozito payı (TL)"],["odeme","Ödeme günü ve şekli","her ayın 5'i, ana kiracıya"]]},
    {t:"Kurallar ve İmza",f:[["kurallar","Ortak yaşam kuralları","Taraflar ortak alanların düzenli ve temiz kullanımı ile makul saatlerde sessizlik konusunda anlaşmıştır.","ta"],["fesih","Ayrılma / bildirim","Taraflar en az 15 gün önceden yazılı bildirimle ayrılabilir; ana kira sözleşmesi ana kiracı adına devam eder."],["ort","Yer (şehir)"],["datum","Tarih"]]},
    ucStep()]};
  if(!window.__TRK2["vtr_tr_ikale"]) window.__TRK2["vtr_tr_ikale"]={ic:"🤝",n:"İkale (Karşılıklı Fesih) Sözleşmesi",sn:"İş sözleşmesinin tarafların anlaşmasıyla sona erdirilmesi",price:"4,99 €",pgs:4,steps:[
    {t:"Taraflar",f:[["isveren","İşveren (unvan, adres)","","ta"],["isci","İşçi (ad soyad, T.C., adres)","","ta"],["pozisyon","Pozisyon / görev"],["ise_giris","İşe giriş tarihi"]]},
    {t:"Fesih ve Ödemeler",f:[["fesih_tarihi","İş ilişkisinin sona erme tarihi"],["gerekce","İkale gerekçesi","Taraflar iş sözleşmesini karşılıklı anlaşarak (ikale) sona erdirmektedir."],["odemeler","Kararlaştırılan ödemeler (kıdem, ihbar, izin, ek ödeme)","","ta"],["ek_menfaat","Ek menfaat (makul yarar)","İşçiye yasal hakları dışında ______ tutarında ek ödeme yapılacaktır."]]},
    {t:"İbra ve İmza",f:[["ibra","Karşılıklı ibra","Taraflar, ödemelerin eksiksiz yapılması koşuluyla birbirlerini karşılıklı ibra ederler (İş K. ve TBK m.420 çerçevesinde işçi lehine değerlendirme saklıdır).","ta"],["belgeler","Teslim edilecek belgeler","Çalışma belgesi ve varsa diğer evrak işçiye teslim edilir."],["ort","Yer (şehir)"],["datum","Tarih"]]},
    ucStep()]};
  if(!window.__TRK2["vtr_tr_teslim_tutanagi"]) window.__TRK2["vtr_tr_teslim_tutanagi"]={ic:"📋",n:"Eşya Teslim / Devir Tutanağı",sn:"Menkul eşya ve demirbaşların teslim kaydı",price:"3,99 €",pgs:3,steps:[
    {t:"Taraflar",f:[["devreden","Teslim eden / devreden: ad soyad, adres","","ta"],["devralan","Teslim alan / devralan: ad soyad, adres","","ta"],["iliski","Teslimin dayanağı (kira, satış, emanet vb.)","","ta"]]},
    {t:"Eşya Listesi ve Durum",f:[["esyalar","Teslim edilen eşya/demirbaş listesi (adet, marka, seri no)","","ta"],["durum","Eşyaların durumu","Eşyalar sağlam ve çalışır durumda teslim edilmiştir.","ta"],["sayaclar","Sayaç değerleri (varsa: elektrik, su, doğalgaz)","","ta"]]},
    {t:"Beyan ve İmza",f:[["beyan","Beyan","Taraflar yukarıdaki eşyaların eksiksiz teslim/tesellüm edildiğini kabul ve beyan eder."],["ort","Yer (şehir)"],["datum","Tarih"]]},
    ucStep()]};

  /* ── (B) 'tr_sozlesme' kategorisi -> Vertrag modulu ── */
  function openVst(){ try{ if(typeof gP==="function"){ gP("vst"); return true; } }catch(e){} return false; }
  function isContractCat(arg){
    try{ var key=(typeof arg==="string")?arg:((arg&&arg.cat)||""); return key==="tr_sozlesme"; }catch(e){ return false; }
  }
  function wrapNav(){
    ["showCat","showCatDocs"].forEach(function(fn){
      try{
        if(typeof window[fn]!=="function" || window[fn].__trv2) return;
        var _o=window[fn];
        var w=function(arg){
          try{ if(isContractCat(arg) && openVst()) return; }catch(e){}
          return _o.apply(this,arguments);
        };
        w.__trv2=1; try{ w.toString=function(){return _o.toString();}; }catch(_){}
        window[fn]=w;
      }catch(e){}
    });
  }

  /* ── (C) tr_konut'tan "Kira Sözleşmesi (Konut)" belgesini kaldir ── */
  function cleanKonut(){
    try{
      if(typeof CAT_DOCS==="undefined" || !CAT_DOCS || !CAT_DOCS.tr_konut) return;
      CAT_DOCS.tr_konut = CAT_DOCS.tr_konut.filter(function(d){
        var t=(d&&d.t)||""; return !/Kira Sözleşmesi \(Konut\)/.test(t);
      });
    }catch(e){}
  }

  var n=0;(function loop(){ wrapNav(); cleanKonut(); if(n++<80) setTimeout(loop,600); })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'t2').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-trvertrag2-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_TR_VERTRAG2')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_TR_VERTRAG2 diskte (".strlen($chk)." bayt)\n";
echo "\n✓ TR duz-kart sozlesmeler module tasindi: kategori -> modul, Kira Sözleşmesi kaldirildi,\n";
echo "  module'e 3 ek sozlesme (ev paylasim, ikale, teslim tutanagi). Toplam 18 TR sozlesmesi.\n";
