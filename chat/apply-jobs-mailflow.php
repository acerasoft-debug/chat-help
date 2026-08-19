<?php
/**
 * ChatHelp — apply-jobs-mailflow (CH_JOBS_MAIL)
 *  Anschreiben modalina 2 premium buton ekler:
 *   📮 "E-posta uygulamasinda ac"  -> mailto: kullanicinin KENDI adresinden gider
 *   📧 "E-postama gonder"          -> sunucu mektubu kullanicinin kayitli adresine yollar
 *      (CV'yi ekleyip firmaya kendi adresinden iletir — en profesyonel akis)
 *  Firma e-postasi ilandan geliyorsa mailto'ya otomatik konur; yoksa alici bos kalir (uydurma yok).
 * KULLANIM: pull-updates.php?files=apply-jobs-mailflow.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-jobs-mailflow BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_JOBS_MAIL') !== false) exit("Zaten ekli (CH_JOBS_MAIL).\n");
file_put_contents($file . '.bak-jobsmail-' . date('Ymd-His'), $src);

$rep = 0;

/* ── 1) foot butonlari: kopya butonunun yanina 2 buton ── */
$a1 = 'var foot=\'<button class="jb-mbtn" id="jb-copy">📋 \'+esc(l.copy)+\'</button>\';';
$n1 = <<<'JS'
var JM=(function(){var M={de:{op:"📮 In Mail-App öffnen",me:"📧 An meine E-Mail",sent:"Gesendet ✓",noem:"Bitte E-Mail im Profil hinterlegen",fail:"Senden fehlgeschlagen"},tr:{op:"📮 E-posta uygulamasında aç",me:"📧 E-postama gönder",sent:"Gönderildi ✓",noem:"Lütfen profile e-posta ekleyin",fail:"Gönderilemedi"},en:{op:"📮 Open in mail app",me:"📧 Send to my email",sent:"Sent ✓",noem:"Please add an email to your profile",fail:"Sending failed"},fr:{op:"📮 Ouvrir dans l'app mail",me:"📧 Envoyer à mon e-mail",sent:"Envoyé ✓",noem:"Ajoutez un e-mail à votre profil",fail:"Échec de l'envoi"},es:{op:"📮 Abrir en app de correo",me:"📧 Enviar a mi correo",sent:"Enviado ✓",noem:"Añada un correo a su perfil",fail:"No se pudo enviar"},it:{op:"📮 Apri nell'app mail",me:"📧 Invia alla mia e-mail",sent:"Inviato ✓",noem:"Aggiungi un'e-mail al profilo",fail:"Invio non riuscito"}};try{return M[UIL()]||M.de;}catch(e){return M.de;}})(); /* CH_JOBS_MAIL */
        var foot='<button class="jb-mbtn" id="jb-copy">📋 '+esc(l.copy)+'</button><button class="jb-mbtn" id="jb-mailto">'+esc(JM.op)+'</button><button class="jb-mbtn" id="jb-mailme">'+esc(JM.me)+'</button>';
JS;
$src2 = str_replace($a1, $n1, $src, $c); $rep += $c;
if ($c) { $src = $src2; echo "  ✓ [1] foot butonlari eklendi\n"; } else { echo "  ✗ [1] anchor bulunamadi (foot)\n"; }

/* ── 2) buton isleyicileri: copy handler'inin hemen arkasi ── */
$a2 = 'if(cp) cp.addEventListener("click",function(){ try{ navigator.clipboard.writeText(d.letter); cp.textContent="✓"; }catch(e){} });';
$n2 = $a2 . "\n" . <<<'JS'
        /* CH_JOBS_MAIL handlers */
        try{
          var mt=document.getElementById("jb-mailto");
          if(mt) mt.addEventListener("click",function(){
            var to=(job&&(job.email||job.contact_email))||"";
            var su=encodeURIComponent(((UIL()==="de")?"Bewerbung: ":"Application: ")+((job&&(job.title||job.company))||""));
            var bd=encodeURIComponent(String(d.letter).slice(0,1800));
            location.href="mailto:"+encodeURIComponent(to)+"?subject="+su+"&body="+bd;
          });
          var mm=document.getElementById("jb-mailme");
          if(mm) mm.addEventListener("click",async function(){
            var to=""; try{ to=(window.P&&P.f7)||""; }catch(e){}
            if(!to){ try{ to=JSON.parse(localStorage.getItem("ch_user")||"{}").email||""; }catch(e){} }
            if(!to){ alert(JM.noem); try{ gP("konto"); }catch(e){} return; }
            mm.disabled=true; var _t2=mm.textContent; mm.textContent="⏳";
            try{
              var subj=((job&&(job.title||job.company))?("Bewerbung — "+(job.title||job.company)):"Anschreiben")+" · ChatHelp";
              var body=String(d.letter)+"\n\n————————————\nNächster Schritt: Lebenslauf (CV) anhängen und diese E-Mail von Ihrer eigenen Adresse an den Arbeitgeber weiterleiten."+((job&&job.email)?("\nArbeitgeber-E-Mail: "+job.email):"");
              var rr=await fetch("send-doc.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({to:to,subject:subj,doc:body})});
              var dd=await rr.json();
              mm.textContent=(dd&&dd.success)?JM.sent:JM.fail;
            }catch(e){ mm.textContent=JM.fail; }
            setTimeout(function(){ mm.textContent=_t2; mm.disabled=false; },3500);
          });
        }catch(e){}
JS;
$src2 = str_replace($a2, $n2, $src, $c); $rep += $c;
if ($c) { $src = $src2; echo "  ✓ [2] mailto + mailme isleyicileri eklendi\n"; } else { echo "  ✗ [2] anchor bulunamadi (copy handler)\n"; }

if ($rep < 2) { echo "\nHATA: 2 ankrajdan en az biri eksik — dosya YAZILMADI (guvenlik).\n"; exit; }
file_put_contents($file, $src);
echo "\n✓ CH_JOBS_MAIL uygulandi.\n";
echo "Test: Is Bul -> Anschreiben erstellen -> modalda 3 buton: Kopyala / Mail-App / E-postama gonder.\n";
