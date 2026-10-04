<?php
/**
 * ChatHelp — apply-vize-selfemployed (CH_VIZE_SELFEMP) — Vize Asistani "Is /
 *  Durum Belgesi" bolumu SADECE calisan senaryosunu kapsiyordu (isverenin
 *  imzalayacagi izin yazisi). Is sahibi / serbest meslek basvuranlar icin
 *  FARKLI belgeler gerekir: ticaret sicil gazetesi + vergi levhasi + faaliyet
 *  belgesi + KENDI imzalayacagi is sahibi beyani (isveren degil, kendisi
 *  imzalar). 5 kucuk, count-guard'li str_replace — CH_VIZE_ASISTAN'in orijinal
 *  (degismemis) metnine dayanir:
 *   [1] GENMETA'ya 'selfemployed' belge tipi eklenir
 *   [2] Schengen 'business' amac dalina is-sahibi secenegi eklenir
 *   [3] Schengen genel (else) 'Is / Durum Belgesi' dalina calisan+is-sahibi ayrimi
 *   [4] ABD destekleyici bolumune calisan+is-sahibi ayrimi (uretilebilir)
 *   [5] prompt()'a 'selfemployed' (is sahibi kendi beyani) sablonu eklenir
 * KULLANIM: pull2.php?key=...&files=apply-vize-selfemployed.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-vize-selfemployed BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_VIZE_SELFEMP')!==false) exit("Zaten ekli (CH_VIZE_SELFEMP).\n");
if (strpos($src,'chVizeAsistan')===false) exit("HATA: Vize Asistani yok — once apply-vize-asistan deploy edilmeli.\n");

$fail=[];

/* ── [1] GENMETA: selfemployed belge tipi ── */
$o1 = <<<'ANC'
    employer:{n:'İşveren Görevlendirme / İzin Yazısı',d:'İşverenin imzalayacağı yazı'},
ANC;
$n1 = <<<'ANC'
    employer:{n:'İşveren Görevlendirme / İzin Yazısı',d:'İşverenin imzalayacağı yazı'},
    selfemployed:{n:'İş Sahibi / Serbest Meslek Beyanı',d:'Kendi işinizin sahibiyseniz kendiniz imzalarsınız'},
ANC;
$c=substr_count($src,$o1);
if($c!==1){ echo "  ✗ [1] GENMETA anchor ($c)\n"; $fail[]='1'; } else { $src=str_replace($o1,$n1,$src); echo "  ✓ [1] GENMETA: selfemployed belge tipi eklendi\n"; }

/* ── [2] Schengen 'business' amac dali: is-sahibi secenegi ── */
$o2 = <<<'ANC'
      else if(ty==='business') G.push({g:'İş Seyahati',it:[
        {t:'Karşı firma davet mektubu',s:'of'},
        {t:'Kendi firmanızın görevlendirme yazısı',s:'ch',g:'employer'},
        {t:'Ticaret sicil gazetesi / vergi levhası',s:'of'}
      ]});
ANC;
$n2 = <<<'ANC'
      else if(ty==='business') G.push({g:'İş Seyahati',it:[
        {t:'Karşı firma davet mektubu',s:'of'},
        {t:'Çalışan/temsilci iseniz: kendi firmanızın görevlendirme yazısı',s:'ch',g:'employer'},
        {t:'İş sahibiyseniz: kendi beyanınız (kendiniz imzalarsınız)',s:'ch',g:'selfemployed'},
        {t:'Ticaret sicil gazetesi / vergi levhası / faaliyet belgesi',s:'of',nt:'Ticaret odası / vergi dairesinden'}
      ]});
ANC;
$c=substr_count($src,$o2);
if($c!==1){ echo "  ✗ [2] business dali anchor ($c)\n"; $fail[]='2'; } else { $src=str_replace($o2,$n2,$src); echo "  ✓ [2] İş Seyahati: iş sahibi seçeneği eklendi\n"; }

/* ── [3] Schengen genel (else) 'İş / Durum Belgesi' dali ── */
$o3 = <<<'ANC'
      else G.push({g:'İş / Durum Belgesi',it:[
        {t:'Çalışan: SGK hizmet dökümü + işveren izin yazısı',s:'of'},
        {t:'İşveren görevlendirme / izin yazısı',s:'ch',g:'employer'}
      ]});
ANC;
$n3 = <<<'ANC'
      else G.push({g:'İş / Durum Belgesi',it:[
        {t:'Çalışan iseniz: SGK hizmet dökümü',s:'of'},
        {t:'Çalışan iseniz: işveren görevlendirme / izin yazısı',s:'ch',g:'employer'},
        {t:'İş sahibiyseniz: ticaret sicil gazetesi + vergi levhası + faaliyet belgesi',s:'of',nt:'Ticaret odası / vergi dairesinden'},
        {t:'İş sahibiyseniz: kendi beyanınız (kendiniz imzalarsınız)',s:'ch',g:'selfemployed'},
        {t:'Emekli/öğrenci/diğer: durumu gösteren belge (emekli maaşı dökümü, öğrenci belgesi vb.)',s:'of'}
      ]});
ANC;
$c=substr_count($src,$o3);
if($c!==1){ echo "  ✗ [3] genel İş/Durum dali anchor ($c)\n"; $fail[]='3'; } else { $src=str_replace($o3,$n3,$src); echo "  ✓ [3] İş / Durum Belgesi: çalışan + iş sahibi ayrımı eklendi\n"; }

/* ── [4] ABD destekleyici bolumu: calisan+is-sahibi ayrimi (uretilebilir) ── */
$o4 = <<<'ANC'
        {t:'İşveren yazısı / SGK / ticaret sicil',s:'of'},
ANC;
$n4 = <<<'ANC'
        {t:'Çalışan iseniz: işveren yazısı + SGK hizmet dökümü',s:'ch',g:'employer'},
        {t:'İş sahibiyseniz: ticaret sicil gazetesi + vergi levhası',s:'ch',g:'selfemployed'},
ANC;
$c=substr_count($src,$o4);
if($c!==1){ echo "  ✗ [4] ABD destekleyici anchor ($c)\n"; $fail[]='4'; } else { $src=str_replace($o4,$n4,$src); echo "  ✓ [4] ABD (B1/B2): çalışan + iş sahibi ayrımı eklendi (üretilebilir)\n"; }

/* ── [5] prompt(): 'selfemployed' — is sahibi kendi beyani ── */
$o5 = <<<'ANC'
    if(gen==='employer') return 'Erstelle ein formelles Arbeitgeber-Schreiben, das der ARBEITGEBER unterschreibt: bestätigt Anstellung, Position, Gehalt, den genehmigten Urlaub für den Reisezeitraum und die Rückkehr an den Arbeitsplatz. '+base+' Schreibe ausschließlich auf '+ln+'. Gib NUR den Brieftext aus, mit Platzhalter für Firmenkopf und Unterschriftszeile.';
ANC;
$n5 = <<<'ANC'
    if(gen==='employer') return 'Erstelle ein formelles Arbeitgeber-Schreiben, das der ARBEITGEBER unterschreibt: bestätigt Anstellung, Position, Gehalt, den genehmigten Urlaub für den Reisezeitraum und die Rückkehr an den Arbeitsplatz. '+base+' Schreibe ausschließlich auf '+ln+'. Gib NUR den Brieftext aus, mit Platzhalter für Firmenkopf und Unterschriftszeile.';
    if(gen==='selfemployed') return 'Erstelle ein formelles Schreiben, das der ANTRAGSTELLER SELBST als Betriebsinhaber/Selbstständiger unterschreibt (er ist sein eigener Arbeitgeber). '+base+' Das Schreiben bestätigt: die Art des Geschäfts bzw. der selbstständigen Tätigkeit, dass der Antragsteller während der Reise abwesend sein kann (ggf. wer den Betrieb währenddessen vertritt), und die feste Rückkehrabsicht (Bindungen zum Heimatland: Betrieb, Familie, Eigentum). Schreibe ausschließlich auf '+ln+', mit Datum und Unterschriftszeile für den Antragsteller als Betriebsinhaber. Gib NUR den Brieftext aus, ohne Erklärungen.';
ANC;
$c=substr_count($src,$o5);
if($c!==1){ echo "  ✗ [5] prompt() employer anchor ($c)\n"; $fail[]='5'; } else { $src=str_replace($o5,$n5,$src); echo "  ✓ [5] prompt(): iş sahibi kendi beyanı şablonu eklendi\n"; }

if ($fail) { echo "\nHATA: eslesmeyen adimlar: ".implode(', ',$fail)." — index DEGISTIRILMEDI.\n"; exit; }

/* marker */
$src .= "\n<!-- CH_VIZE_SELFEMP -->\n";

$tmp = tempnam(sys_get_temp_dir(),'vs').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-vizeselfemp-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_VIZE_SELFEMP')===false || strpos($chk,'selfemployed')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_VIZE_SELFEMP + selfemployed diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Vize Asistanı artık iş sahibi/serbest meslek başvuranlar için de doğru belgeleri gösterir + üretir.\n";
