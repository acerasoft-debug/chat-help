<?php
/**
 * ChatHelp — apply-emsal3 (CH_EMSAL3) — Emsal aramada CIFT ASAMALI dogrulama:
 *  Halusinasyon riskini dusurmek icin (dilekce tarafindaki DeepSeek->Claude
 *  denetim mimarisinin aynisi):
 *   1. ONCE DeepSeek aday emsal kararlari uretir (taslak).
 *   2. SONRA Claude taslagi DENETLER: gercekten var olmayan/uydurma veya
 *      dosya no/tarihi dogrulanamayan kararlari ELER; yalniz guvenilir,
 *      dogru hukuk sistemine ait kararlari birakir; hicbiri guvenilir
 *      degilse acikca soyler.
 *  Seffaf fetch-katmani: CH_EMSAL2'nin arama cagrisini (isaret: "EMSAL
 *  KARARLARI listele") yakalar, iki asamayi calistirir, DENETLENMIS sonucu
 *  cagirana dondurur. DeepSeek yoksa/hata verirse dogrudan Claude'a duser
 *  (tek asama). Arayuz/akis degismez. node ✓ + harness ✓.
 * KULLANIM: pull2.php?key=...&files=apply-emsal3.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-emsal3 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_EMSAL3')!==false) exit("Zaten ekli (CH_EMSAL3).\n");
if (strpos($src,'CH_EMSAL2')===false) exit("HATA: once CH_EMSAL2 gerekli.\n");

$block = <<<'HTMLBLOCK'
<script id="ch-emsal3-js">
/* CH_EMSAL3 — Emsal arama: once DeepSeek taslak, sonra Claude denetim (seffaf) */
try{(function(){
  var _f=window.fetch;
  window.fetch=function(url,opt){
    try{
      var u=''+url;
      if(u.indexOf('action=aichat')!==-1 && opt && typeof opt.body==='string'
         && opt.body.indexOf('EMSAL KARARLARI listele')!==-1
         && opt.body.indexOf('__emsalStage')===-1){
        var b=JSON.parse(opt.body);
        var cc=b.country||'DE';
        var lang=b.lang||'de';
        /* 1) DeepSeek taslak (ayni istek, provider degisik) — _f ile cagir (re-intercept yok) */
        var d1=Object.assign({},b,{provider:'deepseek'});
        return _f.call(window,url,Object.assign({},opt,{body:JSON.stringify(d1)}))
          .then(function(r){ return r.json(); })
          .then(function(j1){
            var draft=(j1&&j1.reply)?String(j1.reply):'';
            if(!draft){
              /* DeepSeek yok/bos -> tek asama Claude (orijinal istek) */
              return _f.call(window,url,opt).then(function(r){ return r.json(); });
            }
            /* 2) Claude denetim */
            var vmsg='Sen kıdemli, son derece titiz bir hukukçusun. Aşağıda başka bir yapay zekânın '+cc+' hukukunda ürettiği EMSAL KARAR taslağı var. GÖREV — taslağı DENETLE:\n'
              +'1) Gerçekten var olduğundan EMİN olmadığın, uydurma görünen ya da mahkeme/tarih/dosya-esas numarası doğrulanamayan kararları ÇIKAR (silme konusunda cömert ol; şüpheliyse çıkar).\n'
              +'2) Yalnızca '+cc+' hukukuna ait, gerçek, tanınmış ve yayımlanmış kararları bırak. Başka ülke hukukunu karıştırma.\n'
              +'3) Hiçbiri güvenilir değilse bunu AÇIKÇA yaz ve hangi resmî veritabanında (ör. '+cc+' için resmî içtihat bankası) hangi anahtar kelimelerle aranacağını öner. Kesinlikle karar UYDURMA.\n'
              +'4) Kalan her karar için: Mahkeme — Tarih — Dosya/Esas No — 2-3 cümle özet — bu konuya somut etkisi — güven düzeyi (kesin/yüksek/doğrulanmalı).\n'
              +'5) Sonda 2 cümle genel durum. Markdown yıldızı KULLANMA.\n'
              +'__emsalStage=verify\n\nDENETLENECEK TASLAK:\n'+draft;
            var d2={message:vmsg,history:[],provider:'claude',lang:lang,country:cc};
            return _f.call(window,url,Object.assign({},opt,{body:JSON.stringify(d2)}))
              .then(function(r){ return r.json(); });
          })
          .then(function(finalJ){
            return { json:function(){ return Promise.resolve(finalJ); } };
          })
          .catch(function(){
            /* herhangi bir hata -> orijinal tek asama */
            return _f.call(window,url,opt);
          });
      }
    }catch(e){}
    return _f.apply(window,arguments);
  };
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'e3').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-emsal3-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_EMSAL3')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_EMSAL3 diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Emsal arama artik CIFT ASAMALI:\n";
echo "   1) DeepSeek aday kararlari uretir (taslak)\n";
echo "   2) Claude denetler; uydurma/dogrulanamayan kararlari ELER,\n";
echo "      yalniz dogru hukuk sistemine ait guvenilir kararlari birakir\n";
echo "   DeepSeek yoksa dogrudan Claude'a duser (tek asama). Arayuz degismez.\n";
