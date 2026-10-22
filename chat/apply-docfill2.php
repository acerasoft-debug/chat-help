<?php
/**
 * ChatHelp — apply-docfill (CH_DOCFILL) — dilekce icerik duzeltmeleri:
 *  [1] Yanlis imza adi: belgede/HTML'de "Acerasoft" -> kullanicinin gercek adi
 *      (IHR NAME / profil). [2] EMPFÄNGER AI ile tamamlaninca dilekce metnine de
 *      YAZILIR (tarih-Betreff arasi alici alanina). [3] Temizlenen metin PDF+
 *      gonderime akar (bodyEdited artik ham metne gore olcer).
 *  5 count-guard'li degisim (IMZA5). Lint + opcache reset.
 * KULLANIM: pull2.php?key=...&files=apply-docfill.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-docfill BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_DOCFILL')!==false) exit("Zaten ekli (CH_DOCFILL).\n");
if(strpos($src,'CH_ADRES_IMZA5')===false) exit("HATA: once CH_ADRES_IMZA5 gerekli.\n");
$REP=json_decode('[["ICBmdW5jdGlvbiBib2R5RWRpdGVkKCl7IHZhciBiPWRvY3VtZW50LmdldEVsZW1lbnRCeUlkKCdhaS1ib2R5Jyk7IHJldHVybiAhIShiICYmIFN0cmluZyhiLnZhbHVlfHwnJykudHJpbSgpICYmIFN0cmluZyhiLnZhbHVlfHwnJykudHJpbSgpIT09T1JJR19CT0RZLnRyaW0oKSk7IH0=", "ICBmdW5jdGlvbiBib2R5RWRpdGVkKCl7IHZhciBiPWRvY3VtZW50LmdldEVsZW1lbnRCeUlkKCdhaS1ib2R5Jyk7IHZhciBiYXNlPSgoQ1RYJiZDVFgucmF3KXx8T1JJR19CT0RZfHwnJykudHJpbSgpOyByZXR1cm4gISEoYiAmJiBTdHJpbmcoYi52YWx1ZXx8JycpLnRyaW0oKSAmJiBTdHJpbmcoYi52YWx1ZXx8JycpLnRyaW0oKSE9PWJhc2UpOyB9"], ["ICB2YXIgT1JJR19CT0RZPScnOw==", "ICB2YXIgT1JJR19CT0RZPScnOwogIC8qIENIX0RPQ0ZJTEwg4oCUIGltemEgYWRpICsgYWxpY2kgYmVsZ2V5ZSBkb2xkdXIgKi8KICBmdW5jdGlvbiBjaFNpZ25lck5hbWUoKXsgdHJ5eyB2YXIgZjE9KGRvY3VtZW50LmdldEVsZW1lbnRCeUlkKCdhaS1mMScpfHx7fSkudmFsdWU7IGlmKGYxJiYoJycrZjEpLnRyaW0oKSkgcmV0dXJuICgnJytmMSkudHJpbSgpOyB2YXIgUD1wcm9mKCk7IHJldHVybiAoKFAuZjF8fCcnKSsnICcrKFAuZjJ8fCcnKSkudHJpbSgpOyB9Y2F0Y2goZSl7IHJldHVybiAnJzsgfSB9CiAgZnVuY3Rpb24gY2hEb2NDbGVhbih0ZXh0KXsgdHJ5eyB0ZXh0PVN0cmluZyh0ZXh0fHwnJyk7IHZhciBubT1jaFNpZ25lck5hbWUoKTsgaWYobm0peyB0ZXh0PXRleHQucmVwbGFjZSgvQWNlcmFzb2Z0KFxzK0xMQyk/L2dpLG5tKTsgfSByZXR1cm4gdGV4dDsgfWNhdGNoKGUpeyByZXR1cm4gU3RyaW5nKHRleHR8fCcnKTsgfSB9CiAgZnVuY3Rpb24gZmlsbFJlY2lwKHRleHQscmVjKXsgdHJ5eyByZWM9U3RyaW5nKHJlY3x8JycpLnRyaW0oKTsgaWYoIXJlYykgcmV0dXJuIHRleHQ7IHZhciBsaW5lcz1TdHJpbmcodGV4dHx8JycpLnNwbGl0KCdcbicpOyB2YXIgZGk9LTEsYmk9LTE7IGZvcih2YXIgaT0wO2k8bGluZXMubGVuZ3RoO2krKyl7IGlmKGRpPDAgJiYgLyxccypkZW5ccytcZHsxLDJ9XC5cZHsxLDJ9XC5cZHsyLDR9Ly50ZXN0KGxpbmVzW2ldKSkgZGk9aTsgaWYoYmk8MCAmJiAvXlxzKihCZXRyZWZmfEJldHJpZmZ0KVxzKjovaS50ZXN0KGxpbmVzW2ldKSkgYmk9aTsgfSB2YXIgcmVjRmlyc3Q9cmVjLnNwbGl0KCdcbicpWzBdLnRyaW0oKTsgaWYoZGk+PTAgJiYgYmk+ZGkpeyB2YXIgYXJlYT1saW5lcy5zbGljZShkaSsxLGJpKS5qb2luKCdcbicpOyBpZihhcmVhLmluZGV4T2YocmVjRmlyc3QpPT09LTEgfHwgL197Myx9fFwuezQsfXzigKYvLnRlc3QoYXJlYSkpeyByZXR1cm4gbGluZXMuc2xpY2UoMCxkaSsxKS5qb2luKCdcbicpKydcblxuJytyZWMrJ1xuXG4nK2xpbmVzLnNsaWNlKGJpKS5qb2luKCdcbicpOyB9IHJldHVybiB0ZXh0OyB9IGlmKC9fezQsfS8udGVzdCh0ZXh0KSl7IHZhciBhZHI9cmVjLnNwbGl0KCdcbicpLnNsaWNlKDEpLmpvaW4oJywgJyl8fHJlYzsgcmV0dXJuIHRleHQucmVwbGFjZSgvX3s0LH0vLCBhZHIpOyB9IHJldHVybiB0ZXh0OyB9Y2F0Y2goZSl7IHJldHVybiBTdHJpbmcodGV4dHx8JycpOyB9IH0KICBmdW5jdGlvbiBmaWxsUmVjaXBJbkJvZHkocmVjKXsgdHJ5eyB2YXIgYj1kb2N1bWVudC5nZXRFbGVtZW50QnlJZCgnYWktYm9keScpOyBpZighYikgcmV0dXJuOyB2YXIgdD1maWxsUmVjaXAoYi52YWx1ZSwgcmVjKTsgaWYodCE9PWIudmFsdWUpIGIudmFsdWU9dDsgfWNhdGNoKGUpe30gfQ=="], ["ICAgIE9SSUdfQk9EWT1odG1sVGV4dChDVFguaHRtbCk7", "ICAgIENUWC5yYXc9aHRtbFRleHQoQ1RYLmh0bWwpOyBPUklHX0JPRFk9Y2hEb2NDbGVhbihDVFgucmF3KTs="], ["aWYodCYmdC5sZW5ndGg8MjQwICYmIGVsICYmICFlbC52YWx1ZS50cmltKCkpeyBlbC52YWx1ZT10OyB2YXIgd2w9ZG9jdW1lbnQuZ2V0RWxlbWVudEJ5SWQoJ2FpLXJlY3dhcm4nKTsgaWYod2wpIHdsLnN0eWxlLmRpc3BsYXk9KC9cYlxkezV9XGIvLnRlc3QodCk/J25vbmUnOicnKTsgfQ==", "aWYodCYmdC5sZW5ndGg8MjQwICYmIGVsICYmICFlbC52YWx1ZS50cmltKCkpeyBlbC52YWx1ZT10OyB0cnl7IGZpbGxSZWNpcEluQm9keSh0KTsgfWNhdGNoKGUpe30gdmFyIHdsPWRvY3VtZW50LmdldEVsZW1lbnRCeUlkKCdhaS1yZWN3YXJuJyk7IGlmKHdsKSB3bC5zdHlsZS5kaXNwbGF5PSgvXGJcZHs1fVxiLy50ZXN0KHQpPydub25lJzonJyk7IH0="], ["ICAgIHZhciBodG1sPXNhdmVQcm9mQWRkcihDVFguaHRtbCk7CiAgICBodG1sPWFwcGx5RWRpdGVkKGh0bWwpOw==", "ICAgIHZhciBodG1sPXNhdmVQcm9mQWRkcihDVFguaHRtbCk7CiAgICB0cnl7IHZhciBfbm09Y2hTaWduZXJOYW1lKCk7IGlmKF9ubSkgaHRtbD1odG1sLnJlcGxhY2UoL0FjZXJhc29mdChccytMTEMpPy9naSxfbm0pOyB9Y2F0Y2goZSl7fQogICAgaHRtbD1hcHBseUVkaXRlZChodG1sKTs="]]', true);
$i=0;
foreach($REP as $pr){ $i++; $o=base64_decode($pr[0]); $n=base64_decode($pr[1]);
  $c=substr_count($src,$o);
  if($c<1 || $c>2){ exit("HATA: degisim #$i anchor $c kez (1-2 beklenir) — index DEGISTIRILMEDI.\n"); }
  $src=str_replace($o,$n,$src); echo "  ✓ degisim #$i ($c yer)\n";
}
$tmp=tempnam(sys_get_temp_dir(),'df').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-docfill-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_DOCFILL')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ DOGRULAMA: CH_DOCFILL diskte (".strlen($chk)." bayt)\n";
echo "\n✓ [1] Belgedeki yanlis isim (Acerasoft) -> gercek imza adiyla degisir\n";
echo "✓ [2] EMPFÄNGER dilekce metnine yazilir (AI tamamlayinca)\n";
echo "✓ [3] Temizlenen metin PDF + gonderime akar\n";
