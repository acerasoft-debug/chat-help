<?php
/**
 * ChatHelp — apply-konto-law (CH_KONTO_LAW)
 *  Konto > Profil'de mevcut #k-cc (bayrak+ulke) satirinin hemen altina,
 *  o an gecerli HUKUK SISTEMINI gosteren yeni bir #k-law satiri ekler
 *  (orn. "🇩🇪 Deutschland" altinda "Deutsches Recht (BGB, TzBfG, StGB)").
 *  Boylece kullanici hangi hukuk sistemine tabi oldugunu her zaman Konto'da
 *  gorur — ulke degisince (CC degisince) otomatik guncellenir.
 * KULLANIM: pull-updates.php?files=apply-konto-law.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-konto-law BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_KONTO_LAW') !== false) exit("Zaten ekli (CH_KONTO_LAW).\n");
file_put_contents($file . '.bak-kontolaw-' . date('Ymd-His'), $src);

$bundle = <<<'KLHTML'
<script id="ch-konto-law">
/* CH_KONTO_LAW — Konto > Profil: mevcut hukuk sistemini goster (#k-cc altinda #k-law) */
try{(function(){
  var LAWNAMES={
    DE:"Deutsches Recht (BGB, TzBfG, StGB)",
    TR:"Türk Hukuku (TBK, İş Kanunu, TCK)",
    FR:"Droit français (Code civil, Code du travail)",
    ES:"Derecho español (Código Civil, Estatuto de los Trabajadores)",
    CH:"Schweizer Recht (OR, ZGB)",
    GB:"English Law (England & Wales)",
    US:"U.S. State Law (varies by state)",
    IT:"Diritto italiano (Codice Civile)",
    NL:"Nederlands recht (Burgerlijk Wetboek)",
    BE:"Droit belge / Belgisch recht",
    AT:"Österreichisches Recht (ABGB)",
    PL:"Prawo polskie (Kodeks cywilny)",
    SE:"Svensk rätt",
    PT:"Direito português (Código Civil)"
  };
  var LBL={de:"Geltendes Recht",tr:"Geçerli hukuk",en:"Applicable law",fr:"Droit applicable",es:"Derecho aplicable",it:"Diritto applicabile",nl:"Toepasselijk recht",pl:"Obowiązujące prawo",sv:"Tillämplig rätt",pt:"Direito aplicável"};
  function UIL(){ try{ return (window.chUIL?window.chUIL():(localStorage.getItem("ch_uilang")||"de")); }catch(e){ return "de"; } }
  function ensure(){
    try{
      var ccEl=document.getElementById("k-cc");
      if(!ccEl||!ccEl.parentNode) return;
      var row=document.getElementById("k-law-row");
      if(!row){
        row=document.createElement("div"); row.id="k-law-row";
        row.style.cssText="margin-top:6px";
        row.innerHTML='<div style="font-size:10.5px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;color:var(--t3,#a8a8d0);margin-bottom:2px" id="k-law-lbl"></div>'
          +'<div id="k-law" style="font-size:13px;color:var(--t1,#fff);font-weight:700"></div>';
        ccEl.parentNode.insertBefore(row, ccEl.nextSibling);
      }
      var lbl=document.getElementById("k-law-lbl"), val=document.getElementById("k-law");
      var lt=LBL[UIL()]||LBL.en;
      if(lbl && lbl.textContent!==lt) lbl.textContent=lt;
      var cc="DE"; try{ cc=(typeof CC!=="undefined"&&CC)?CC:"DE"; }catch(e){}
      var lv=LAWNAMES[cc]||cc;
      if(val && val.textContent!==lv) val.textContent=lv;
    }catch(e){}
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",function(){ ensure(); setInterval(ensure,1000); });
  else { ensure(); setInterval(ensure,1000); }
})();}catch(e){}
</script>
KLHTML;

$p = strrpos($src, '</body>');
if ($p === false) exit("HATA: </body> bulunamadi — yazilmadi.\n");
$src = substr($src, 0, $p) . $bundle . "\n" . substr($src, $p);

$tmp = tempnam(sys_get_temp_dir(), 'idx') . '.php';
file_put_contents($tmp, $src);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "LINT HATASI — index.php degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
file_put_contents($file, $src);
echo "✓ CH_KONTO_LAW uygulandi. Konto > Profil'de ülke satırının altında 'Geçerli hukuk' satırı görünecek.\n";
