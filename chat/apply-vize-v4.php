<?php
/**
 * ChatHelp — apply-vize-v4 (CH_VIZE_V4) — Vize Asistani premium sekillendirme:
 *  [A] DIL SAFLIGI: formlar Turkce doldurulsa bile belge %100 hedef dilde —
 *      prompta kesin ceviri talimati eklendi (kisi/kurum adlari haric belge
 *      dilinden baska dilde tek kelime kalamaz).
 *  [B] Japonya listeden cikarildi (TR vatandasina vize gerekmiyor).
 *  [C] ✕ ile kapatinca da islem OTOMATIK kaydedilir (Vize Dosyalarim) —
 *      "Bitir & Kaydet"e basilmasa bile hicbir islem kaybolmaz.
 *  [D] PREMIUM cila: ust barda ★ PREMIUM rozeti + "X/Y belge ✓" canli
 *      ilerleme gostergesi; kart/dugme parlatmalari (altin vurgular).
 *  NOT: Basvuru turune gore belge/soru setleri ve amaca ozel evrak listesi
 *  V3'te zaten aktif (turizm/is/ogrenci/calisma/tedavi/etkinlik...).
 *  3 count-guard'li degisiklik + 1 eklemeli blok. node ✓.
 * KULLANIM: pull2.php?key=...&files=apply-vize-v4.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-vize-v4 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_VIZE_V4')!==false) exit("Zaten ekli (CH_VIZE_V4).\n");
if (strpos($src,'CH_VIZE_V3')===false) exit("HATA: once CH_VIZE_V3 gerekli.\n");

$fail=[];
function rep(&$src,$o,$n,$tag,&$fail){
  $c=substr_count($src,$o);
  if($c!==1){ echo "  ✗ [$tag] anchor $c kez (1 olmali)\n"; $fail[]=$tag; return; }
  $src=str_replace($o,$n,$src);
  echo "  ✓ [$tag] uygulandi\n";
}

/* ── [A] dil safligi ── */
$oA = <<<'ANC'
      +'\nHeutiges Datum: '+today()+' — dieses Datum als Briefdatum verwenden.'
      +'\nKöşeli parantezli yer tutucu ASLA bırakma; bilinmeyen alan varsa cümleyi bilgisiz kur. KEIN Markdown, keine Sternchen — nur reiner Brieftext.';
ANC;
$nA = <<<'ANC'
      +'\nHeutiges Datum: '+today()+' — dieses Datum als Briefdatum verwenden.'
      +'\nKullanıcının verdiği bilgiler Türkçe veya başka bir dilde olabilir: bunları belgenin diline ÇEVİREREK işle; kişi/kurum adları dışında belge dilinden başka dilde TEK KELİME kalmasın. Das gesamte Dokument muss zu 100% auf '+ln+' verfasst sein.'
      +'\nKöşeli parantezli yer tutucu ASLA bırakma; bilinmeyen alan varsa cümleyi bilgisiz kur. KEIN Markdown, keine Sternchen — nur reiner Brieftext.';
ANC;
rep($src,$oA,$nA,'A-dil',$fail);

/* ── [B] Japonya cikar ── */
$oB1 = <<<'ANC'
    {k:'ae',n:'BAE (Dubai)',ic:'🇦🇪',ll:'en',cc:'AE'},
    {k:'jp',n:'Japonya',ic:'🇯🇵',ll:'en',cc:'JP'}
ANC;
$nB1 = <<<'ANC'
    {k:'ae',n:'BAE (Dubai)',ic:'🇦🇪',ll:'en',cc:'AE'}
ANC;
rep($src,$oB1,$nB1,'B1-japonya',$fail);
$oB2 = "    jp:{n:'Japonya Büyükelçiliği (resmi)',u:'https://www.tr.emb-japan.go.jp'},\n";
$cB2=substr_count($src,$oB2);
if($cB2===1){ $src=str_replace($oB2,'',$src); echo "  ✓ [B2-portal] uygulandi\n"; }
else echo "  ⚠ [B2-portal] anchor $cB2 (atlandi — zararsiz)\n";

/* ── [C] kapatinca otomatik kayit ── */
rep($src,
  "window.__v3Close=function(){ var o=document.getElementById('vz3-ov');",
  "window.__v3Close=function(){ try{ if(CURPROC||Object.keys(PREP).length){ ensureProc(); } }catch(e){} var o=document.getElementById('vz3-ov');",
  'C-kayit',$fail);

if ($fail) { echo "\nHATA: eslesmeyen: ".implode(', ',$fail)." — index DEGISTIRILMEDI.\n"; exit; }

/* ── [D] premium cila blogu ── */
$block = <<<'HTMLBLOCK'
<style id="ch-vize-v4-css">
/* CH_VIZE_V4 — premium cila */
#vz3-ov .top{background:linear-gradient(180deg,#101024,#0d0d1c);border-bottom:1px solid rgba(212,168,74,.35)}
#vz3-premchip{font-size:9.5px;font-weight:800;letter-spacing:.8px;background:linear-gradient(135deg,#d4a84a,#f0c860);color:#191000;border-radius:100px;padding:3px 9px;white-space:nowrap}
#vz3-prog{font-size:10.5px;font-weight:800;color:#42df94;border:1px solid rgba(66,223,148,.4);border-radius:100px;padding:3px 9px;white-space:nowrap}
#vz3-ov .vz3-doc{box-shadow:0 4px 14px rgba(0,0,0,.25);transition:box-shadow .2s,border-color .2s}
#vz3-ov .vz3-doc:hover{border-color:rgba(212,168,74,.55);box-shadow:0 8px 22px rgba(212,168,74,.12)}
#vz3-ov .vz3-btn:not(.ghost){box-shadow:0 6px 18px rgba(212,168,74,.28)}
#vz3-dw{box-shadow:-18px 0 70px rgba(0,0,0,.65)}
#vz3-ov .vz3-card:hover .ic{transform:scale(1.12);transition:transform .15s}
</style>
<script id="ch-vize-v4-js">
/* CH_VIZE_V4 — premium rozet + canli ilerleme (X/Y belge ✓) */
try{(function(){
  setInterval(function(){
    try{
      var ov=document.getElementById('vz3-ov'); if(!ov) return;
      var top=ov.querySelector('.top'); if(!top) return;
      if(!document.getElementById('vz3-premchip')){
        var ch=document.createElement('span'); ch.id='vz3-premchip'; ch.textContent='★ PREMIUM';
        var x=top.querySelector('.x');
        if(x) top.insertBefore(ch,x); else top.appendChild(ch);
      }
      var docs=ov.querySelectorAll('.vz3-doc');
      var pr=document.getElementById('vz3-prog');
      if(docs.length){
        var ok=0;
        for(var i=0;i<docs.length;i++){
          var b=docs[i].querySelector('[id^="vz3-ok-"]');
          if(b && b.style.display!=='none') ok++;
        }
        if(!pr){ pr=document.createElement('span'); pr.id='vz3-prog';
          var chp=document.getElementById('vz3-premchip');
          if(chp&&chp.parentNode) chp.parentNode.insertBefore(pr,chp); else top.appendChild(pr); }
        pr.textContent=ok+'/'+docs.length+' ✓';
        pr.style.display='';
      } else if(pr){ pr.style.display='none'; }
    }catch(e){}
  },900);
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$src = substr($src,0,$pos).$block."\n".substr($src,$pos);
echo "  ✓ [D-premium] blok eklendi\n";

$tmp = tempnam(sys_get_temp_dir(),'v4').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-vizev4-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_VIZE_V4')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "\n  ✓ DOGRULAMA: CH_VIZE_V4 diskte (".strlen($chk)." bayt)\n";
echo "\n✓ [A] Formlar Turkce doldurulsa bile belge %100 hedef dilde uretilir\n";
echo "✓ [B] Japonya listeden cikarildi (TR vatandasina vize yok)\n";
echo "✓ [C] ✕ ile kapatinca da islem otomatik Vize Dosyalarim'a kaydedilir\n";
echo "✓ [D] Premium: ★ rozet + canli 'X/Y belge ✓' ilerlemesi + altin cilalar\n";
echo "  (Basvuru turune gore belge/soru setleri V3'te zaten aktif.)\n";
