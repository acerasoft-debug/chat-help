<?php
/**
 * ChatHelp — apply-photo-lang (CH_PHOTO_LANG) — foto analizi: kullanici dilinde
 *  aciklama + ne yapilmali + dilekce onerisi (hem ana sayfa hem chat).
 *  KANIT (dump-photoflow2/critfix): doPhoto sabit Ingilizce/kisa "zusammenfassung"
 *  istiyor ve frontend 'lang' gondermiyor. Model-fix ile foto artik calisiyor;
 *  bu yama ciktisini zenginlestirir:
 *   [BE] doPhoto: 'lang' alir, zusammenfassung'u O DILDE ister (3-4 cumle: bu
 *        belge nedir, alici icin ne anlama gelir, SOMUT olarak ne yapmali —
 *        hangi itiraz/dilekce, hangi sure). doc tespiti korunur -> kart dogru
 *        dilekce butonunu gosterir.
 *   [FE] iki analyze_photo cagrisina (ana sayfa + chat) 'lang' eklenir.
 *  Kart zaten zusammenfassung'u + docType dilekce butonunu gosteriyor (UXFIX3).
 * KULLANIM: pull2.php?key=...&files=apply-photo-lang.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-photo-lang BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$idxFile=__DIR__.'/index.php';
$apiFile=__DIR__.'/api.php';
$idx=@file_get_contents($idxFile);
$api=@file_get_contents($apiFile);
if($idx===false) exit("index.php okunamadi\n");
if($api===false) exit("api.php okunamadi\n");
if(strpos($api,'CH_PHOTO_LANG')!==false) exit("Zaten ekli (CH_PHOTO_LANG).\n");

$fail=[];

/* ── [BE1] doPhoto basina $__pl (dil) ekle ── */
$o1 = "    \$b64  = \$b['b64']  ?? '';\n    \$mime = \$b['mime'] ?? 'image/jpeg';\n    if (!\$b64) { respond(['error'=>'No image']); return; }";
$n1 = "    \$b64  = \$b['b64']  ?? '';\n    \$mime = \$b['mime'] ?? 'image/jpeg';\n    \$__pl = preg_replace('/[^a-z]/','',strtolower((string)(\$b['lang'] ?? 'de'))); if(\$__pl==='') \$__pl='de'; /*CH_PHOTO_LANG*/\n    if (!\$b64) { respond(['error'=>'No image']); return; }";
$c=substr_count($api,$o1);
if($c!==1){ echo "  ✗ [BE1] doPhoto bas anchor ($c)\n"; $fail[]='BE1'; }
else { $api=str_replace($o1,$n1,$api,$c); echo "  ✓ [BE1] doPhoto 'lang' parametresini aliyor\n"; }

/* ── [BE2] prompt: zusammenfassung kullanici dilinde + somut oneri ── */
$o2 = '[\'type\'=>\'text\',\'text\'=>\'Analyze this document: {"erkannt":"","doc":"widerspruch|strafanzeige|kuendigung_mieter|mietvertrag|bussgeldbescheid|einspruch_steuer|mahnschreiben|other","behoerde":"","datum":"","az":"","frist":"","betrag":"","land":"","zusammenfassung":"2 sentences what this is and what to do"}\']';
$n2 = '[\'type\'=>\'text\',\'text\'=>\'Analyze this document. Write the "erkannt" and "zusammenfassung" values in language code "\'.$__pl.\'" (the user interface language); all other fields as raw data. "zusammenfassung" = 3-4 clear sentences in \'.$__pl.\': what this document is, what it means for the recipient, and CONCRETELY what to do next (which objection/petition to file, any deadline to watch). Return JSON only, no markdown: {"erkannt":"short title in \'.$__pl.\'","doc":"widerspruch|strafanzeige|kuendigung_mieter|mietvertrag|bussgeldbescheid|einspruch_steuer|mahnschreiben|other","behoerde":"","datum":"","az":"","frist":"","betrag":"","land":"","zusammenfassung":"..."}\']';
$c=substr_count($api,$o2);
if($c!==1){ echo "  ✗ [BE2] doPhoto prompt anchor ($c)\n"; $fail[]='BE2'; }
else { $api=str_replace($o2,$n2,$api,$c); echo "  ✓ [BE2] zusammenfassung kullanici dilinde + somut ne-yapmali\n"; }

/* ── [FE1] ana sayfa analyze_photo: lang ekle ── */
$o3 = 'body:JSON.stringify({b64,mime:file.type})';
$n3 = 'body:JSON.stringify({b64,mime:file.type,lang:(localStorage.getItem("ch_uilang")||(typeof dLang!=="undefined"?dLang:"de"))})';
$c=substr_count($idx,$o3);
if($c!==1){ echo "  ✗ [FE1] ana sayfa foto cagrisi anchor ($c)\n"; $fail[]='FE1'; }
else { $idx=str_replace($o3,$n3,$idx,$c); echo "  ✓ [FE1] ana sayfa foto cagrisina 'lang' eklendi\n"; }

/* ── [FE2] chat analyze() analyze_photo: lang ekle ── */
$o4 = 'body:JSON.stringify({b64:b64,mime:file.type})';
$n4 = 'body:JSON.stringify({b64:b64,mime:file.type,lang:(localStorage.getItem("ch_uilang")||(typeof dLang!=="undefined"?dLang:"de"))})';
$c=substr_count($idx,$o4);
if($c!==1){ echo "  ✗ [FE2] chat foto cagrisi anchor ($c)\n"; $fail[]='FE2'; }
else { $idx=str_replace($o4,$n4,$idx,$c); echo "  ✓ [FE2] chat foto cagrisina 'lang' eklendi\n"; }

if($fail){ echo "\nHATA: eslesmeyen: ".implode(', ',$fail)." — HICBIR dosya DEGISTIRILMEDI.\n"; exit; }

/* iz birak */
$api=preg_replace('/^<\?php/', "<?php\n/* CH_PHOTO_LANG */", $api, 1);

foreach([[$idxFile,$idx],[$apiFile,$api]] as $pair){
    $tmp=tempnam(sys_get_temp_dir(),'pl').'.php';
    file_put_contents($tmp,$pair[1]);
    $lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
    if($rc!==0){ echo "\nLINT HATASI (".basename($pair[0]).") — HICBIR dosya degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
}
$bk1=@file_put_contents($idxFile.'.bak-photolang-'.date('Ymd-His'), @file_get_contents($idxFile));
$bk2=@file_put_contents($apiFile.'.bak-photolangapi-'.date('Ymd-His'), @file_get_contents($apiFile));
$w1=@file_put_contents($idxFile,$idx);
$w2=@file_put_contents($apiFile,$api);
if($w1===false||$w1<strlen($idx)||$w2===false||$w2<strlen($api)){ echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($apiFile);
if(strpos($chk,'CH_PHOTO_LANG')===false){ echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_PHOTO_LANG diskte (index ".strlen($idx).", api ".strlen($api).")\n";
echo "\n✓ CH_PHOTO_LANG uygulandi. Foto analizi artik kullanici dilinde aciklama +\n";
echo "   ne yapilmali + uygun dilekce onerisi gosterir (ana sayfa + chat).\n";
