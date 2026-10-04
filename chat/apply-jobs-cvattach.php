<?php
/**
 * ChatHelp — apply-jobs-cvattach (CH_JOBS_CVATTACH)
 *  Is basvurusu e-postasina (mailme akisi) kayitli Lebenslauf/CV verisini
 *  (CV Studio -> ch_cv_data) GUZEL BICIMLENDIRILMIS ikinci bir bolum olarak ekler.
 *  Gercek dosya eki degil (mevcut mail altyapisi multipart desteklemiyor) —
 *  ayni e-postanin icinde net basliklandirilmis "📎 Lebenslauf" blogu.
 *  CV yoksa eski davranis (sadece not) aynen kalir.
 * KULLANIM: pull-updates.php?files=apply-jobs-cvattach.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-jobs-cvattach BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_JOBS_CVATTACH') !== false) exit("Zaten ekli (CH_JOBS_CVATTACH).\n");

$old = 'var subj=((job&&(job.title||job.company))?("Bewerbung — "+(job.title||job.company)):"Anschreiben")+" · ChatHelp";
              var body=String(d.letter)+"\n\n————————————\nNächster Schritt: Lebenslauf (CV) anhängen und diese E-Mail von Ihrer eigenen Adresse an den Arbeitgeber weiterleiten."+((job&&job.email)?("\nArbeitgeber-E-Mail: "+job.email):"");';

$new = 'var subj=((job&&(job.title||job.company))?("Bewerbung — "+(job.title||job.company)):"Anschreiben")+" · ChatHelp"; /* CH_JOBS_CVATTACH */
              var body=String(d.letter);
              try{
                var cvd=JSON.parse(localStorage.getItem("ch_cv_data")||"{}");
                if(cvd&&(cvd.name||(cvd.exp&&cvd.exp.some(function(x){return x.r||x.f;})))){
                  var L2="\n\n════════════════════\n📎 LEBENSLAUF — "+(cvd.name||"—")+"\n════════════════════\n";
                  if(cvd.role) L2+=cvd.role+"\n";
                  var kv=[cvd.addr,cvd.phone,cvd.email].filter(Boolean).join(" · "); if(kv) L2+=kv+"\n";
                  if(cvd.prof) L2+="\n"+cvd.prof+"\n";
                  var xp=(cvd.exp||[]).filter(function(x){return x.r||x.f;});
                  if(xp.length){ L2+="\nBerufserfahrung:\n"; xp.forEach(function(x){ L2+="• "+[x.d,x.r,x.f].filter(Boolean).join(" — ")+(x.t?(" — "+x.t):"")+"\n"; }); }
                  var ed=(cvd.edu||[]).filter(function(x){return x.r||x.f;});
                  if(ed.length){ L2+="\nAusbildung:\n"; ed.forEach(function(x){ L2+="• "+[x.d,x.r,x.f].filter(Boolean).join(" — ")+"\n"; }); }
                  var sk=(cvd.skills||[]).filter(function(x){return x[0];});
                  if(sk.length) L2+="\nFähigkeiten: "+sk.map(function(x){return x[0];}).join(", ")+"\n";
                  var lg=(cvd.langs||[]).filter(function(x){return x[0];});
                  if(lg.length) L2+="Sprachen: "+lg.map(function(x){return x[0]+(x[1]?(" ("+x[1]+")"):"");}).join(", ")+"\n";
                  body+=L2;
                } else {
                  body+="\n\n————————————\nHinweis: Noch kein Lebenslauf hinterlegt — unter \\"Lebenslauf & CV\\" erstellen, dann wird er hier automatisch mitgesendet.";
                }
              }catch(e){}
              body+=((job&&job.email)?("\n\nArbeitgeber-E-Mail: "+job.email):"");';

$c = 0;
$src2 = str_replace($old, $new, $src, $c);
if (!$c) { echo "HATA: beklenen blok bulunamadi (belki zaten farklilasti) — dosya degistirilmedi.\n"; exit; }
$src = $src2;
echo "  ✓ mailme govdesi CV ekleme mantigiyla genisletildi ($c nokta)\n";

$tmp = tempnam(sys_get_temp_dir(), 'idx') . '.php';
file_put_contents($tmp, $src);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }

file_put_contents($file . '.bak-jobscvattach-' . date('Ymd-His'), file_get_contents($file));
file_put_contents($file, $src);
echo "\n✓ CH_JOBS_CVATTACH uygulandi.\n";
echo "Test: Is Bul -> Anschreiben erstellen -> 'E-postama gonder' -> kayitli CV varsa e-postada 📎 LEBENSLAUF bolumu gorunmeli.\n";
