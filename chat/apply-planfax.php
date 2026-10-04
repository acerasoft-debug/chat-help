<?php
/**
 * ChatHelp — apply-planfax (CH_PLANFAX) — FAZ 1 (guvenli icerik):
 *  [A] Tum plan gosterimlerine Gratis-Fax satiri: Basic 5 / Pro 25 / Elite 100 /Ay.
 *      3 format: Konto (['brain',..]) + kompakt planList (feats:'...') + premium sayfa (feats:[...]).
 *  [B] Hero altina "So funktioniert's / Nasil calisir" 3-adim aciklayici (olustur->imzala->gonder),
 *      cok dilli, idempotent, additive.
 *  Sadece gosterim/metin — JS mantigina/odemeye DOKUNMAZ. php -l + marker dogrulama.
 * KULLANIM: pull2.php?key=...&files=apply-planfax.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-planfax BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_PLANFAX')!==false) exit("Zaten ekli (CH_PLANFAX).\n");

/* ── [A] plan feats -> Gratis-Fax satiri (9 gosterim, cosmetic) ── */
$rep=[
  /* Format A: Konto (['brain',..]]) */
  "['brain','5 Fallanalysen / Monat']]"   => "['brain','5 Fallanalysen / Monat'],['check','5 Gratis-Fax / Monat']]",
  "['brain','20 Fallanalysen / Monat']]"  => "['brain','20 Fallanalysen / Monat'],['check','25 Gratis-Fax / Monat']]",
  "['brain','100 Fallanalysen / Monat']]" => "['brain','100 Fallanalysen / Monat'],['check','100 Gratis-Fax / Monat']]",
  /* Format C: premium plan sayfasi (string array) */
  "'KI-Recherche','5 Fallanalysen / Monat']" => "'KI-Recherche','5 Fallanalysen / Monat','5 Gratis-Fax / Monat']",
  "'Strategie-Analyse','20 Fallanalysen / Monat']" => "'Strategie-Analyse','20 Fallanalysen / Monat','25 Gratis-Fax / Monat']",
  "'100 Fallanalysen / Monat','Anwalt-DeepSearch']" => "'100 Fallanalysen / Monat','100 Gratis-Fax / Monat','Anwalt-DeepSearch']",
  /* Format B: kompakt planList (feats:'...') */
  "feats:'40 Dok. · PDF · KI'" => "feats:'40 Dok. · PDF · KI · 5 Gratis-Fax'",
  "feats:'200 Dok. · Unbegr. Formulare · 20 Fallanalysen'" => "feats:'200 Dok. · Unbegr. Formulare · 20 Fallanalysen · 25 Gratis-Fax'",
  "feats:'1000 Dok. · Unbegr. Formulare · 100 Fallanalysen'" => "feats:'1000 Dok. · Unbegr. Formulare · 100 Fallanalysen · 100 Gratis-Fax'",
];
$applied=0;
foreach($rep as $o=>$n){
  $c=substr_count($src,$o);
  if($c>0){ $src=str_replace($o,$n,$src); echo "  ✓ [A] ($c) ".substr($o,0,46)."...\n"; $applied+=$c; }
  else echo "  = [A] eslesmedi: ".substr($o,0,46)."...\n";
}
echo "  -> toplam $applied fax-satiri eklendi\n\n";

/* ── [B] hero altina "nasil calisir" 3-adim aciklayici ── */
$block = <<<'HTMLBLOCK'
<style id="ch-planfax-css">
#ch-howto{max-width:560px;margin:14px auto 4px;display:flex;gap:9px;flex-wrap:wrap;justify-content:center}
#ch-howto .hw{flex:1 1 160px;min-width:150px;background:var(--s1,rgba(255,255,255,.04));border:1px solid var(--bdr,rgba(255,255,255,.1));border-radius:14px;padding:12px 13px;text-align:left}
#ch-howto .hw-i{font-size:20px;margin-bottom:5px}
#ch-howto .hw-t{font-size:13px;font-weight:800;color:var(--t1,#fff);margin-bottom:3px}
#ch-howto .hw-s{font-size:11.5px;color:var(--t3,#a8a8d0);line-height:1.5}
#ch-howto .hw-3{border-color:rgba(212,168,74,.4)}
#ch-howto .hw-3 .hw-t{color:var(--gold,#d4a84a)}
</style>
<script id="ch-planfax-js">
/* CH_PLANFAX — hero alti nasil-calisir (olustur->imzala->gonder) */
try{(function(){
  function UIL(){ try{ return (localStorage.getItem("ch_uilang")||"de").slice(0,2);}catch(e){return "de";} }
  var T={
   de:[["📝","Erstellen","Anliegen beschreiben — die KI erstellt ein rechtssicheres Schreiben."],["✍️","Unterschreiben","Direkt am Handy unterschreiben."],["📤","Versenden","Als PDF oder direkt per Post, Einschreiben oder Fax — inkl. Gratis-Fax je nach Paket."]],
   tr:[["📝","Oluştur","Talebini anlat — yapay zeka hukuka uygun bir yazı hazırlar."],["✍️","İmzala","Doğrudan telefonda imzala."],["📤","Gönder","PDF olarak ya da doğrudan Post/Einschreiben/Faks ile — paketine göre ücretsiz faks dahil."]],
   en:[["📝","Create","Describe your case — the AI drafts a legally sound letter."],["✍️","Sign","Sign right on your phone."],["📤","Send","As PDF or directly by mail, registered post or fax — free faxes included per plan."]],
   fr:[["📝","Créer","Décrivez votre demande — l'IA rédige un courrier juridique."],["✍️","Signer","Signez directement sur le téléphone."],["📤","Envoyer","En PDF ou par courrier, recommandé ou fax — fax gratuits selon le forfait."]],
   es:[["📝","Crear","Describe tu caso — la IA redacta un escrito legal."],["✍️","Firmar","Firma directamente en el móvil."],["📤","Enviar","En PDF o por correo, certificado o fax — faxes gratis según el plan."]],
   it:[["📝","Crea","Descrivi la tua richiesta — l'IA redige una lettera legale."],["✍️","Firma","Firma direttamente sul telefono."],["📤","Invia","In PDF o via posta, raccomandata o fax — fax gratis in base al piano."]]
  };
  function render(){
    try{
      var host=document.getElementById("wlc-p"); if(!host) return;
      var ex=document.getElementById("ch-howto");
      var arr=T[UIL()]||T.de;
      var html='';
      for(var i=0;i<arr.length;i++){ var s=arr[i];
        html+='<div class="hw'+(i===2?' hw-3':'')+'"><div class="hw-i">'+s[0]+'</div><div class="hw-t">'+s[1]+'</div><div class="hw-s">'+s[2]+'</div></div>';
      }
      if(!ex){ ex=document.createElement("div"); ex.id="ch-howto"; if(host.parentNode) host.parentNode.insertBefore(ex, host.nextSibling); }
      if(ex.getAttribute("data-l")!==UIL()){ ex.innerHTML=html; ex.setAttribute("data-l",UIL()); }
    }catch(e){}
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",render); else render();
  try{ setInterval(render, 1500); }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;
$pos=strripos($src,'</body>');
if($pos===false) exit("</body> yok\n");
$src=substr($src,0,$pos).$block."\n".substr($src,$pos);
echo "  ✓ [B] hero-alti nasil-calisir 3-adim (6 dil) eklendi\n";

$tmp=tempnam(sys_get_temp_dir(),'pf').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-planfax-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_PLANFAX')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_PLANFAX diskte (".strlen($chk)." B)\n";
echo "  Test: Plan sayfasi/Konto -> her pakette 'X Gratis-Fax / Monat'; Ana sayfa hero alti 3-adim.\n";
