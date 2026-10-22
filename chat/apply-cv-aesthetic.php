<?php
/**
 * ChatHelp — apply-cv-aesthetic (CH_CV_AESTHETIC)
 *  CV / LinkedIn / is-mektubu (career) uretim promptlarini daha ESTETIK ve
 *  AYRINTILI yapar; ayrica dogruluk: kullanicinin vermedigi deneyim/nitelik
 *  UYDURULMAZ. api.php career sysLaw dallari guncellenir.
 * KULLANIM: pull-updates.php?files=apply-cv-aesthetic.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-cv-aesthetic BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file=__DIR__.'/api.php';
$src=@file_get_contents($file);
if($src===false) exit("api.php okunamadi\n");
if(strpos($src,'CH_CV_AESTHETIC')!==false) exit("Zaten ekli (CH_CV_AESTHETIC).\n");

$n=0;

/* ── 1) CV (_cv) — estetik + ayrintili + dogruluk ── */
$o=' Modern, geriye dönük kronolojik, net bölümlenmiş bir CV oluştur (Kişisel Bilgiler, İş Deneyimi, Eğitim, Beceriler, Diller, Sertifikalar). Hedef ülkenin CV geleneklerine uy (Almanya: tabellarischer Lebenslauf; İngilizce: özlü resume/CV).';
$nw=' Modern, geriye dönük kronolojik ve GÖRSEL olarak temiz, tutarlı bir CV oluştur. Bölümler: kısa ve etkili bir PROFESYONEL ÖZET (2-3 cümle, hedeflenen pozisyona göre) en üstte; İş Deneyimi (her rol için firma, dönem ve 2-4 madde halinde SOMUT başarılar — mümkünse ölçülebilir sonuçlarla, güçlü eylem fiilleriyle); Eğitim; Beceriler (kategorize: teknik/dil/kişisel); Diller (seviye ile); Sertifikalar. Tutarlı tarih biçimi, hizalı ve tarayıcı-dostu (ATS) yapı, abartısız profesyonel dil. Hedef ülkenin geleneklerine uy (Almanya: tabellarischer Lebenslauf, foto/kişisel bilgiler üstte; İngilizce: özlü resume). ÖNEMLİ: Yalnızca kullanıcının verdiği bilgileri kullan — deneyim, nitelik, unvan veya tarih UYDURMA; eksik bölümü boş bırak, doldurmak için bilgi isteyebilirsin.';
$c=0; $src=str_replace($o,$nw,$src,$c); $n+=$c; echo ($c?"  ✓ [1] CV estetik+ayrintili+dogruluk\n":"  ✗ [1] CV anchor yok\n");

/* ── 2) LinkedIn ozeti — biraz daha zengin ── */
$o=' Etkileyici, birinci ağızdan yazılmış, anahtar kelime açısından zengin bir LinkedIn özeti (about) yaz — 3-5 kısa paragraf.';
$nw=' Etkileyici, birinci ağızdan, anahtar kelime açısından zengin bir LinkedIn özeti (about) yaz — 3-5 kısa paragraf: kim olduğun ve uzmanlık alanın, öne çıkan başarıların (somut, mümkünse ölçülebilir), değer önerin ve ne aradığın; sıcak ama profesyonel bir ton, sonda hafif bir eyleme çağrı. Kullanıcının vermediği başarı/nitelik uydurma.';
$c=0; $src=str_replace($o,$nw,$src,$c); echo ($c?"  ✓ [2] LinkedIn zengin\n":"  ✗ [2] LinkedIn anchor yok (atlandi)\n");

/* ── 3) is-mektubu (else dali) — estetik + Eintrittstermin ── */
$o=' Doğru iş mektubu formatında yaz: gönderen, alıcı, tarih, konu, hitap, ikna edici gövde metni (güçlü yönler + pozisyon bağlantısı), kapanış. İlan metni verildiyse birebir ona göre uyarla.';
$nw=' Doğru ve estetik iş mektubu formatında yaz: gönderen, alıcı, tarih, konu, hitap; iyi yapılandırılmış paragraflarda ikna edici gövde (güçlü bir giriş + pozisyonla bağ, motivasyon, somut örneklerle güçlü yönler, şirkete katkı), görüşme isteğiyle profesyonel kapanış; varsa mümkün işe başlama tarihini (Eintrittstermin) ve yalnızca belirtilmişse maaş beklentisini ekle. Akıcı, özgüvenli ama abartısız (~250-350 kelime). İlan metni verildiyse birebir ona göre uyarla. Kullanıcının vermediği nitelik/deneyim uydurma.';
$c=0; $src=str_replace($o,$nw,$src,$c); $n+=$c; echo ($c?"  ✓ [3] is-mektubu estetik+Eintrittstermin\n":"  ✗ [3] is-mektubu anchor yok\n");

if($n<2){ echo "\nHATA: kritik degisikliklerden yalnizca $n uygulandi — api.php DEGISTIRILMEDI.\n"; exit; }

$src=str_replace('<?php','<?php /*CH_CV_AESTHETIC*/',$src,$mk);
$tmp=tempnam(sys_get_temp_dir(),'cva').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "\nLINT HATASI — api.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-cva-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_CV_AESTHETIC uygulandi ($n kritik). CV/LinkedIn/is-mektubu daha estetik, ayrintili ve dogru (uydurma yok).\n";
