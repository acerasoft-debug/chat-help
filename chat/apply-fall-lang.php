<?php
/**
 * ChatHelp — apply-fall-lang (CH_FALL_LANG) — Fall/"Dava Ac" akisi dil kilidi
 *
 *  KANIT (dump-fall-lang / dump-fall-blocks / dump-fall-a1 / dump-siteaudit):
 *  fall-api.php'de 'lang' HIC kullanilmiyor; 5 nokta Almanca'ya kilitli:
 *   [1] fall_chat $sys: "Du bist ein erfahrener, einfühlsamer Rechtsberater..."
 *       — dil talimati yok -> Turkce/Ispanyolca yazana ALMANCA cevap veriyor.
 *   [2] Gecis cumlesi '➡️ Ich habe nun alle wichtigen Informationen...' sabit
 *       Almanca (index.php bunu string-match ETMIYOR — dump ile dogrulandi,
 *       yani cok dilli yapmak GUVENLI).
 *   [3] a1 analiz promptu: "Du bist deutscher Jurist" — ulke hukuku sabit DE.
 *   [4] Belge $sys: "Du bist ein erfahrener deutscher Rechtsanwalt" — sabit.
 *   [5] $usr sonu: "Erstelle jetzt das finale Dokument auf Deutsch." — ACIK
 *       Almanca kilidi (en kritik nokta).
 *
 *  COZUM: $body['country'] ZATEN geliyor (CH_FALL_SAVE kullaniyor) —
 *  ulkeye gore hukuk ipucu ($__lawF) + her cevap/belge icin "kullanicinin
 *  yazdigi dilde yanit ver" (otomatik algilama; chat akisindaki CH_AICHAT_LANG
 *  ile ayni strateji). 5 degisiklik de ya HEP ya HIC uygulanir.
 * KULLANIM: pull-updates.php?files=apply-fall-lang.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fall-lang BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/fall-api.php';
$src = @file_get_contents($file);
if ($src===false) exit("fall-api.php okunamadi\n");
if (strpos($src,'CH_FALL_LANG')!==false) exit("Zaten ekli (CH_FALL_LANG).\n");

$fail=[];

/* ── [1] fall_chat: dil-otomatik-algilama talimati ── */
$o1 = '    $sys = "Du bist ein erfahrener, einfühlsamer Rechtsberater. "';
$n1 = '    $sys = "Du bist ein erfahrener, einfühlsamer Rechtsberater. WICHTIG — SPRACHE: Antworte IMMER in derselben Sprache, in der der Nutzer schreibt (automatisch erkennen: Türkisch→Türkisch, Spanisch→Spanisch, Französisch→Französisch, Englisch→Englisch, Deutsch→Deutsch usw.) — antworte NIEMALS auf Deutsch, wenn der Nutzer in einer anderen Sprache schreibt. /*CH_FALL_LANG*/ "';
$c=0; $src=str_replace($o1,$n1,$src,$c);
echo ($c===1?"  ✓ [1] fall_chat: dil-otomatik-algilama eklendi\n":"  ✗ [1] fall_chat anchor ($c)\n"); if($c!==1)$fail[]=1;

/* ── [2] gecis cumlesi: kullanicinin dilinde ── */
$o2 = '         . "\'➡️ Ich habe nun alle wichtigen Informationen — Sie können auf »Fall lösen« klicken.\'"';
$n2 = '         . "\'➡️ Ich habe nun alle wichtigen Informationen — Sie können auf »Fall lösen« klicken.\' (diesen Abschlusssatz IMMER in der Sprache des Nutzers formulieren, nicht zwingend auf Deutsch)"';
$c=0; $src=str_replace($o2,$n2,$src,$c);
echo ($c===1?"  ✓ [2] gecis cumlesi cok dilli\n":"  ✗ [2] gecis anchor ($c)\n"); if($c!==1)$fail[]=2;

/* ── [3] ulke-hukuk hesabi + a1 analiz promptu ulke-farkinda ── */
$o3 = <<<'OLD'
    $analysis = [];

    $a1 = chAI1([
        ['role'=>'system','content'=>'Du bist deutscher Jurist. Analysiere sachlich: rechtlicher Rahmen, §-Angaben, Fristen, Erfolgsaussichten.'],
OLD;
$n3 = <<<'NEW'
    $__ccF = strtoupper(preg_replace('/[^A-Za-z]/','',(string)($body['country'] ?? 'DE'))) ?: 'DE'; /*CH_FALL_LANG*/
    $__lawF = ['DE'=>'deutschem Recht','AT'=>'österreichischem Recht (ABGB)','CH'=>'Schweizer Recht (OR, ZGB)','TR'=>'türkischem Recht (TBK, İş Kanunu, TMK, TKHK)','FR'=>'französischem Recht (Code civil, Code du travail)','ES'=>'spanischem Recht (Código Civil, ET, LAU)','IT'=>'italienischem Recht (Codice Civile)','NL'=>'niederländischem Recht (BW)','BE'=>'belgischem Recht','PL'=>'polnischem Recht (Kodeks cywilny)','SE'=>'schwedischem Recht','PT'=>'portugiesischem Recht (Código Civil)','GB'=>'englischem Recht (England & Wales)','US'=>'US-amerikanischem Recht (je nach Bundesstaat)'][$__ccF] ?? 'deutschem Recht';
    $analysis = [];

    $a1 = chAI1([
        ['role'=>'system','content'=>'Du bist Jurist und arbeitest nach '.$__lawF.'. Analysiere sachlich: rechtlicher Rahmen, §-Angaben, Fristen, Erfolgsaussichten.'],
NEW;
$c=0; $src=str_replace($o3,$n3,$src,$c);
echo ($c===1?"  ✓ [3] ulke-hukuk hesabi + a1 ulke-farkinda\n":"  ✗ [3] a1 anchor ($c)\n"); if($c!==1)$fail[]=3;

/* ── [4] belge $sys: ulke hukuku + kullanici dili ── */
$o4 = '    $sys = "Du bist ein erfahrener deutscher Rechtsanwalt. Erstelle ein vollständiges, sofort einsetzbares "';
$n4 = '    $sys = "Du bist ein erfahrener Rechtsanwalt und arbeitest nach {$__lawF}. WICHTIG — SPRACHE: Verfasse das GESAMTE Dokument in DERSELBEN Sprache, die der NUTZER im GESPRÄCH verwendet (automatisch erkennen — NICHT zwingend Deutsch); nur die Rechtsgrundlagen folgen {$__lawF}. /*CH_FALL_LANG*/ Erstelle ein vollständiges, sofort einsetzbares "';
$c=0; $src=str_replace($o4,$n4,$src,$c);
echo ($c===1?"  ✓ [4] belge promptu: ulke hukuku + kullanici dili\n":"  ✗ [4] belge-sys anchor ($c)\n"); if($c!==1)$fail[]=4;

/* ── [5] EN KRITIK: 'auf Deutsch' kilidi kaldirildi ── */
$o5 = 'Erstelle jetzt das finale Dokument auf Deutsch.";';
$n5 = 'Erstelle jetzt das finale Dokument in der Sprache, die der Nutzer im GESPRÄCH verwendet."; /*CH_FALL_LANG*/';
$c=0; $src=str_replace($o5,$n5,$src,$c);
echo ($c===1?"  ✓ [5] 'auf Deutsch' kilidi kaldirildi (belge artik kullanicinin dilinde)\n":"  ✗ [5] auf-Deutsch anchor ($c)\n"); if($c!==1)$fail[]=5;

if ($fail) { echo "\nHATA: adimlar eslesmedi: ".implode(', ',$fail)." — fall-api.php DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'flg').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — fall-api.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-falllang-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_FALL_LANG uygulandi. Dava Ac akisi artik:\n";
echo "   • Kullanici hangi dilde yazarsa O DILDE cevap verir (soru-cevap + belge)\n";
echo "   • Hukuk, secili ulkeye gore uygulanir (TR->TBK/IsK, ES->Codigo Civil vb.)\n";
echo "   • 'auf Deutsch' kilidi kaldirildi — Almanca yalnizca Almanca yazana/DE secene\n";
