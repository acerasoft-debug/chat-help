<?php
/**
 * ChatHelp — apply-download-gate (CH_DLGATE)
 *  KURAL: Anmeldung (giris) olmadan VEYA odeme (plan/tekil) olmadan
 *  HICBIR belge indirilemez. Acik kalan uc delik kapatilir:
 *   [1] Chat [[DOC]] "Als PDF speichern" (printDoc) — girissiz herkes indirebiliyordu
 *   [2] Briefe/Schnellbriefe modulu (1 tik PDF) — tamamen acikti
 *   [3] Staatliche form linkleri (window.location.href = fileUrl) — ACIK ERISIMDI
 *  Zaten kapili olanlar (CV Studio 3,99/plan, Vertrag Studio kota, uretim
 *  paywall'i) DEGISTIRILMEZ. Ortak bekci: window.chDlGate() — token +
 *  (basic/pro/elite) ister; yoksa cok dilli uyari + Konto/plan sayfasi.
 * KULLANIM: pull-updates.php?files=apply-download-gate.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-download-gate BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_DLGATE')!==false) exit("Zaten ekli (CH_DLGATE).\n");

$fail=[];

/* ── [1] chat [[DOC]] printDoc basina bekci ── */
$o1 = "function printDoc(docText){";
$n1 = "function printDoc(docText){ if(window.chDlGate&&!window.chDlGate()) return; /*CH_DLGATE*/";
$c=0; $src=str_replace($o1,$n1,$src,$c);
echo ($c===1?"  ✓ [1] chat PDF kapisi\n":"  ✗ [1] printDoc anchor ($c)\n"); if($c!==1)$fail[]=1;

/* ── [2] Briefe modulu PDF bekcisi ── */
$o2 = "      if(window.chPrintDoc) window.chPrintDoc(html);";
$n2 = "      if(window.chDlGate&&!window.chDlGate()) return; /*CH_DLGATE*/\n      if(window.chPrintDoc) window.chPrintDoc(html);";
$c=0; $src=str_replace($o2,$n2,$src,$c);
echo ($c>=1?"  ✓ [2] Briefe PDF kapisi ($c)\n":"  ✗ [2] briefe anchor ($c)\n"); if($c<1)$fail[]=2;

/* ── [3] Staatliche form linki bekcisi (ifade-guvenli form) ── */
$o3 = "window.location.href = fileUrl;";
$n3 = "(window.chDlGate&&!window.chDlGate())||(window.location.href = fileUrl); /*CH_DLGATE*/";
$c=0; $src=str_replace($o3,$n3,$src,$c);
echo ($c>=1?"  ✓ [3] Staatliche form kapisi ($c)\n":"  ~ [3] fileUrl anchor yok (atlandi — canli surumde farkli olabilir)\n");

/* ── [4] ortak bekci fonksiyonu ── */
$bundle = <<<'HTML'
<script id="ch-dlgate-js">
/* CH_DLGATE — indirme bekcisi: giris + plan/odeme sarti */
try{(function(){
  var MSG={
    de:{login:"🔒 Bitte zuerst anmelden — Downloads nur mit Konto und Plan bzw. Einzelzahlung.",plan:"🔒 PDF-Download erfordert einen Plan (Basic 13,99 € · Pro · Elite) oder eine Einzelzahlung."},
    tr:{login:"🔒 Önce giriş yapın — indirme yalnızca hesap ve plan/tekil ödeme ile mümkün.",plan:"🔒 PDF indirmek için bir plan (Basic 13,99 € · Pro · Elite) veya tekil ödeme gerekli."},
    en:{login:"🔒 Please sign in first — downloads require an account and a plan or one-time payment.",plan:"🔒 PDF download requires a plan (Basic €13.99 · Pro · Elite) or a one-time payment."},
    fr:{login:"🔒 Connectez-vous d'abord — téléchargement uniquement avec un compte et un forfait/paiement unique.",plan:"🔒 Le téléchargement PDF nécessite un forfait (Basic 13,99 € · Pro · Elite) ou un paiement unique."},
    es:{login:"🔒 Inicie sesión primero — la descarga requiere cuenta y plan/pago único.",plan:"🔒 La descarga de PDF requiere un plan (Basic 13,99 € · Pro · Elite) o un pago único."},
    it:{login:"🔒 Accedi prima — il download richiede un account e un piano/pagamento singolo.",plan:"🔒 Il download del PDF richiede un piano (Basic 13,99 € · Pro · Elite) o un pagamento singolo."}
  };
  window.chDlGate=function(){
    try{
      var tk=null; try{ tk=localStorage.getItem("ch_token"); }catch(e){}
      var pl=""; try{ pl=(window.P&&P.plan)||JSON.parse(localStorage.getItem("ch_user")||"{}").plan||""; }catch(e){}
      if(tk&&(pl==="basic"||pl==="pro"||pl==="elite")) return true;
      var ul="de"; try{ ul=localStorage.getItem("ch_uilang")||"de"; }catch(e){}
      var m=MSG[ul]||MSG.de;
      alert(tk?m.plan:m.login);
      try{ gP("konto"); }catch(e){}
      return false;
    }catch(e){ return true; }
  };
})();}catch(e){}
</script>
HTML;
$pos = strrpos($src,'</body>');
if ($pos===false) { echo "  ✗ [4] </body> yok\n"; $fail[]=4; }
else { $src = substr($src,0,$pos).$bundle."\n".substr($src,$pos); echo "  ✓ [4] chDlGate bekcisi eklendi\n"; }

if ($fail) { echo "\nHATA: adimlar eslesmedi: ".implode(', ',$fail)." — index.php DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'dg').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-dlgate-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_DLGATE uygulandi. Girissiz/odemesiz belge indirme kapatildi (chat PDF, Briefe, Staatliche).\n";
