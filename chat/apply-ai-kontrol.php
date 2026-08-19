<?php
/**
 * ChatHelp — apply-ai-kontrol (CH_AI_KONTROL) — girisleri AI + canli kontrol:
 *  [1] VIZE (uyarisiz, otomatik): uretim promptuna SESSIZ DUZELTME talimati —
 *      adres/posta kodu/tarih bicimi/buyuk-kucuk harf hatalari belgeye
 *      islenirken otomatik duzeltilir; tarihler hedef ulkenin resmi bicimine
 *      cevrilir; eksik/supheli alan uydurulmaz, genel ifade kullanilir.
 *  [2] VIZE KOKPITI (canli ipucu): Bilgilerim alanlarinin altinda aninda
 *      kontrol — dogum tarihi bicimi, pasaport no uzunlugu, adreste sehir/
 *      ulke, ad-soyad iki kelime. Sari ipucu; duzeltince kaybolur.
 *  [3] CHAT DILEKCELERI (gorunur uyari): uretilen belge kartinda doldurulmamis
 *      yer tutucu ([VORNAME], [ADRESSE]...) kalirsa kartin altinda sari uyari:
 *      hangi alanlarin eksik oldugu + Konto>Profil onerisi.
 *  1 count-guard'li str_replace + 1 eklemeli blok. node ✓.
 * KULLANIM: pull2.php?key=...&files=apply-ai-kontrol.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-ai-kontrol BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_AI_KONTROL')!==false) exit("Zaten ekli (CH_AI_KONTROL).\n");
if (strpos($src,'CH_VIZE_V4')===false) exit("HATA: once CH_VIZE_V4 gerekli.\n");

/* ── [1] vize promptuna sessiz duzeltme ── */
$o1 = <<<'ANC'
      +'\nKullanıcının verdiği bilgiler Türkçe veya başka bir dilde olabilir: bunları belgenin diline ÇEVİREREK işle; kişi/kurum adları dışında belge dilinden başka dilde TEK KELİME kalmasın. Das gesamte Dokument muss zu 100% auf '+ln+' verfasst sein.'
ANC;
$n1 = <<<'ANC'
      +'\nKullanıcının verdiği bilgiler Türkçe veya başka bir dilde olabilir: bunları belgenin diline ÇEVİREREK işle; kişi/kurum adları dışında belge dilinden başka dilde TEK KELİME kalmasın. Das gesamte Dokument muss zu 100% auf '+ln+' verfasst sein.'
      +'\nKullanıcı bilgilerindeki bariz yazım/biçim hatalarını (adres, posta kodu, tarih biçimi, büyük-küçük harf) belgeye işlerken SESSİZCE düzelt; tarihleri hedef ülkenin resmî biçimine çevir; şüpheli veya eksik alanı ASLA uydurma, genel ifade kullan.'
ANC;
$c1=substr_count($src,$o1);
if($c1!==1) exit("HATA: [1] anchor $c1 kez — index DEGISTIRILMEDI.\n");
$src=str_replace($o1,$n1,$src);
echo "  ✓ [1] Vize: sessiz otomatik duzeltme talimati eklendi\n";

/* ── [2]+[3] canli kontrol blogu ── */
$block = <<<'HTMLBLOCK'
<style id="ch-ai-kontrol-css">
.aik-hint{font-size:10.5px;color:#ffcf7a;margin:-8px 0 12px;line-height:1.4}
.aik-warn{background:rgba(255,190,60,.1);border:1px solid rgba(255,190,60,.4);border-radius:10px;padding:9px 12px;margin-top:8px;font-size:11.5px;color:#ffd9a0;line-height:1.5}
</style>
<script id="ch-ai-kontrol-js">
/* CH_AI_KONTROL — vize alan ipuclari + chat dilekce yer-tutucu uyarisi */
try{(function(){
  function UIL(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  function T(k){
    var L={
      dogum:{tr:'Tarih biçimi belirsiz görünüyor (ör. 12.03.1988 yazın).',de:'Datumsformat unklar (z.B. 12.03.1988).',en:'Date format unclear (e.g. 12.03.1988).'},
      pas:{tr:'Pasaport numarası kısa/eksik görünüyor.',de:'Passnummer wirkt zu kurz.',en:'Passport number looks too short.'},
      adres:{tr:'Adreste şehir/ülke eksik görünüyor (ör. İstanbul, Türkiye).',de:'Ort/Land scheint zu fehlen (z.B. Istanbul, Türkei).',en:'City/country seems missing (e.g. Istanbul, Türkiye).'},
      ad:{tr:'Ad SOYAD birlikte yazın.',de:'Vor- und Nachname zusammen angeben.',en:'Enter first and last name.'},
      ph:{tr:'⚠ Belgede doldurulmamış alanlar var: ',de:'⚠ Im Dokument sind Platzhalter offen: ',en:'⚠ The document has unfilled fields: '},
      ph2:{tr:' — Konto → Profil bölümünden bilgilerinizi tamamlayın, belge otomatik dolar.',de:' — Ergänzen Sie Ihr Profil unter Konto; die Felder werden automatisch ersetzt.',en:' — Complete your profile under Konto; fields are filled automatically.'}
    };
    var l=UIL(); return (L[k]&&(L[k][l]||L[k].en))||'';
  }
  function hint(afterEl,id,msg){
    var h=document.getElementById(id);
    if(!msg){ if(h) h.remove(); return; }
    if(!h){ h=document.createElement('div'); h.className='aik-hint'; h.id=id;
      if(afterEl&&afterEl.parentNode) afterEl.parentNode.insertBefore(h,afterEl.nextSibling); else return; }
    if(h.textContent!=='💡 '+msg) h.textContent='💡 '+msg;
  }
  /* [2] vize Bilgilerim canli kontrol */
  function vizeCheck(){
    try{
      var ov=document.getElementById('vz3-ov'); if(!ov||!ov.classList.contains('on')) return;
      var f;
      f=document.getElementById('vz3-p-dogum');
      if(f){ var v=f.value.trim();
        hint(f.parentNode,'aik-dogum',(v && !/\d{1,2}[./-]\d{1,2}[./-]\d{2,4}/.test(v) && !/\d{4}-\d{2}-\d{2}/.test(v))?T('dogum'):''); }
      f=document.getElementById('vz3-p-pasaport');
      if(f){ var v2=f.value.trim();
        hint(f.parentNode,'aik-pas',(v2 && v2.replace(/[^a-z0-9]/gi,'').length<6)?T('pas'):''); }
      f=document.getElementById('vz3-p-adres');
      if(f){ var v3=f.value.trim();
        hint(f.parentNode,'aik-adres',(v3 && (v3.length<6 || v3.split(/[ ,]+/).length<2))?T('adres'):''); }
      f=document.getElementById('vz3-p-ad');
      if(f){ var v4=f.value.trim();
        hint(f.parentNode,'aik-ad',(v4 && v4.split(/\s+/).length<2)?T('ad'):''); }
    }catch(e){}
  }
  /* [3] chat belge kartlari: acik yer tutucu uyarisi */
  function cardCheck(){
    try{
      var cards=document.querySelectorAll('.ch-doccard');
      for(var i=0;i<cards.length;i++){
        var c=cards[i]; if(c.__aik) continue;
        var body=c.querySelector('.ch-doccard-body'); if(!body) continue;
        var tx=body.textContent||''; if(!tx || tx.length<40) continue;
        c.__aik=1;
        var m=tx.match(/\[[A-ZÄÖÜŞİĞÇ_]{3,}\]/g);
        if(m&&m.length){
          var uniq=[]; m.forEach(function(x){ if(uniq.indexOf(x)===-1) uniq.push(x); });
          var w=document.createElement('div'); w.className='aik-warn';
          w.textContent=T('ph')+uniq.slice(0,6).join(', ')+T('ph2');
          c.appendChild(w);
        }
      }
    }catch(e){}
  }
  setInterval(function(){ vizeCheck(); cardCheck(); },900);
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$src = substr($src,0,$pos).$block."\n".substr($src,$pos);
echo "  ✓ [2][3] canli kontrol blogu eklendi\n";

$tmp = tempnam(sys_get_temp_dir(),'ak').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-aikontrol-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_AI_KONTROL')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "\n  ✓ DOGRULAMA: CH_AI_KONTROL diskte (".strlen($chk)." bayt)\n";
echo "\n✓ [1] Vize belgeleri: adres/tarih/bicim hatalari uretimde SESSIZCE duzeltilir\n";
echo "✓ [2] Vize kokpiti: alan altlarinda canli 💡 ipuclari (dogum/pasaport/adres/ad)\n";
echo "✓ [3] Chat dilekceleri: doldurulmamis yer tutucu kalirsa kartta ⚠ uyari +\n";
echo "     Konto>Profil onerisi\n";
