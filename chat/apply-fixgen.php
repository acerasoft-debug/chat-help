<?php
/**
 * ChatHelp — apply-fixgen (CH_FIXGEN) — ACIL: kategori kartlarindaki
 *  "Dokument generieren" butonu calismiyordu. KOK NEDEN: fixpack [D] cift-
 *  Empfänger icin ch_empfaenger alanini display:none yapti ama 'required'
 *  birakti -> gizli+zorunlu+bos alan uretim dogrulamasini bloklayip sessizce
 *  return ettiriyordu. DUZELTME: ch_empfaenger GORUNUR kalir; gereksiz "Name
 *  des Unternehmens" gizlenir + Empfänger'den otomatik dolar + required kalkar.
 *  GENEL EMNIYET: gizli [required] alanlar uretimi asla bloklamaz.
 * KULLANIM: pull2.php?key=...&files=apply-fixgen.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fixgen BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_FIXPACK_J18')===false) exit("HATA: once CH_FIXPACK_J18 gerekli.\n");
if(strpos($src,'offsetParent===null) rs[k].removeAttribute')!==false) exit("Zaten ekli (CH_FIXGEN).\n");
$old=base64_decode("ICBmdW5jdGlvbiBkZWR1cGUoKXsKICAgIHRyeXsKICAgICAgdmFyIGVtcD1kb2N1bWVudC5xdWVyeVNlbGVjdG9yKCdbZGF0YS1rPSJjaF9lbXBmYWVuZ2VyIl0nKTsKICAgICAgaWYoIWVtcCkgcmV0dXJuOwogICAgICB2YXIgd3JhcD1lbXAuY2xvc2VzdD8gKGVtcC5jbG9zZXN0KCcuZnJtLXJvdycpfHxlbXAuY2xvc2VzdCgnLmZlLWZpZWxkJyl8fGVtcC5wYXJlbnROb2RlKSA6IGVtcC5wYXJlbnROb2RlOwogICAgICBpZighd3JhcHx8d3JhcC5fX2oxOCkgcmV0dXJuOwogICAgICB2YXIgbGFiZWxzPWRvY3VtZW50LnF1ZXJ5U2VsZWN0b3JBbGwoJ2xhYmVsLCAuZmUtbGFiZWwsIC5mcm0tbGFiZWwnKTsKICAgICAgZm9yKHZhciBpPTA7aTxsYWJlbHMubGVuZ3RoO2krKyl7CiAgICAgICAgdmFyIHQ9KGxhYmVsc1tpXS50ZXh0Q29udGVudHx8JycpLnRvTG93ZXJDYXNlKCk7CiAgICAgICAgaWYoL25hbWUgZGVzIHVudGVybmVobWVuc3xmaXJtZW5uYW1lfGFuYmlldGVyfHVudGVybmVobWVuXGIvLnRlc3QodCkgJiYgIS9lbXBmw6RuZ2VyLy50ZXN0KHQpKXsKICAgICAgICAgIHdyYXAuc3R5bGUuZGlzcGxheT0nbm9uZSc7IHdyYXAuX19qMTg9MTsgcmV0dXJuOwogICAgICAgIH0KICAgICAgfQogICAgfWNhdGNoKGUpe30KICB9"); $new=base64_decode("ICBmdW5jdGlvbiBkZWR1cGUoKXsKICAgIHRyeXsKICAgICAgdmFyIGVtcD1kb2N1bWVudC5xdWVyeVNlbGVjdG9yKCdbZGF0YS1rPSJjaF9lbXBmYWVuZ2VyIl0nKTsKICAgICAgaWYoZW1wKXsKICAgICAgICB2YXIgbGFiZWxzPWRvY3VtZW50LnF1ZXJ5U2VsZWN0b3JBbGwoJ2xhYmVsLCAuZmUtbGFiZWwsIC5mcm0tbGFiZWwnKTsKICAgICAgICBmb3IodmFyIGk9MDtpPGxhYmVscy5sZW5ndGg7aSsrKXsKICAgICAgICAgIHZhciB0PShsYWJlbHNbaV0udGV4dENvbnRlbnR8fCcnKS50b0xvd2VyQ2FzZSgpOwogICAgICAgICAgaWYoL25hbWUgZGVzIHVudGVybmVobWVuc3xmaXJtZW5uYW1lfGFuYmlldGVyfG5hbWUgZGVzIGFuYmlldGVycy8udGVzdCh0KSAmJiAhL2VtcGYvLnRlc3QodCkpewogICAgICAgICAgICB2YXIgbHc9bGFiZWxzW2ldLmNsb3Nlc3Q/IChsYWJlbHNbaV0uY2xvc2VzdCgnLmZpJyl8fGxhYmVsc1tpXS5jbG9zZXN0KCcuZnJtLXJvdycpfHxsYWJlbHNbaV0uY2xvc2VzdCgnLmZlLWZpZWxkJyl8fGxhYmVsc1tpXS5wYXJlbnROb2RlKSA6IGxhYmVsc1tpXS5wYXJlbnROb2RlOwogICAgICAgICAgICBpZihsdyAmJiAhbHcuX19qMTgpewogICAgICAgICAgICAgIHZhciBpbnA9bHcucXVlcnlTZWxlY3RvcignaW5wdXQsdGV4dGFyZWEsW2RhdGEta10nKTsKICAgICAgICAgICAgICBpZihpbnApeyBpZighU3RyaW5nKGlucC52YWx1ZXx8JycpLnRyaW0oKSl7IGlucC52YWx1ZT1TdHJpbmcoZW1wLnZhbHVlfHwnJykuc3BsaXQoJ1xuJylbMF0udHJpbSgpOyB9IGlucC5yZW1vdmVBdHRyaWJ1dGUoJ3JlcXVpcmVkJyk7IH0KICAgICAgICAgICAgICBsdy5zdHlsZS5kaXNwbGF5PSdub25lJzsgbHcuX19qMTg9MTsKICAgICAgICAgICAgfQogICAgICAgICAgfQogICAgICAgIH0KICAgICAgfQogICAgfWNhdGNoKGUpe30KICAgIHRyeXsgdmFyIHJzPWRvY3VtZW50LnF1ZXJ5U2VsZWN0b3JBbGwoJ1tyZXF1aXJlZF0nKTsgZm9yKHZhciBrPTA7azxycy5sZW5ndGg7aysrKXsgaWYocnNba10ub2Zmc2V0UGFyZW50PT09bnVsbCkgcnNba10ucmVtb3ZlQXR0cmlidXRlKCdyZXF1aXJlZCcpOyB9IH1jYXRjaChlKXt9CiAgfQ==");
$c=substr_count($src,$old);
if($c!==1) exit("HATA: dedupe anchor $c kez (1 beklenir) — index DEGISTIRILMEDI.\n");
$src=str_replace($old,$new,$src);
echo "  ✓ dedupe yeniden yazildi (ch_empfaenger korunur, gizli required kalkar)\n";
$tmp=tempnam(sys_get_temp_dir(),'fg').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-fixgen-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'offsetParent===null) rs[k].removeAttribute')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ DOGRULAMA: CH_FIXGEN diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Kategori 'Dokument generieren' butonu artik calisir (gizli-required blokaji cozuldu)\n";
echo "✓ Cift Empfänger: EMPFÄNGER kalir, 'Name des Unternehmens' gizlenip otomatik dolar\n";
