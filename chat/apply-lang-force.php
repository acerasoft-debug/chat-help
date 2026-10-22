<?php
/**
 * ChatHelp — apply-lang-force (CH_LANG_FORCE) — DIL: rica degil, EMIR
 *
 *  SORUN: "hangi dilde yazilirsa o dilde cevap ver" talimati prompt'ta VAR
 *  ama model (ozellikle DeepSeek) Almanca agirlikli sistem promptu yuzunden
 *  yine Almanca cevap verebiliyor — yumusak talimat YETMIYOR.
 *
 *  COZUM: Sunucu tarafinda DETERMINISTIK dil algilama (Arap/Kiril alfabesi,
 *  Turkce ozel harfler, dile ozgu kelimeler). Algilanan dil, sistem promptun
 *  SONUNA (recency etkisi) hem Almanca hem O DILDE yazilmis KESIN bir emir
 *  olarak eklenir ("Yanitinin TAMAMINI yalnizca Turkce yaz." gibi).
 *  Uygulanan yerler:
 *   [api.php doAiChat]  — ana sohbet (algilanamazsa istekteki lang'e duser)
 *   [fall-api fall_chat] — Dava Ac soru-cevap (son kullanici mesajindan)
 *   [fall-api fall_solve]— nihai belge (tum kullanici mesajlarindan)
 * KULLANIM: pull-updates.php?files=apply-lang-force.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-lang-force BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$helper = <<<'PHPH'

/* CH_LANG_FORCE — deterministik dil algilama + kesin dil emri */
function ch_sniff_lang(string $t): ?string {
    if (preg_match('/\p{Arabic}/u', $t)) return 'ar';
    if (preg_match('/\p{Cyrillic}/u', $t)) return 'ru';
    if (preg_match('/[ğışİıŞĞ]|(\b(bir|ve|için|istiyorum|nasıl|değil|merhaba|sözleşme|dilekçe|fesih|kira|işten)\b)/ui', $t)) return 'tr';
    if (preg_match('/[ąęłżźćńś]|\b(chcę|umowa|jak|nie|proszę)\b/ui', $t)) return 'pl';
    if (preg_match('/[ãõ]|\b(eu quero|obrigado|você)\b/ui', $t)) return 'pt';
    if (preg_match('/[¿¡ñ]|\b(quiero|cómo|hola|contrato|cancelar|por favor|usted)\b/ui', $t)) return 'es';
    if (preg_match('/\b(je veux|bonjour|résilier|contrat|s\'il vous|monsieur|madame)\b/ui', $t)) return 'fr';
    if (preg_match('/\b(voglio|come posso|ciao|contratto|disdire|per favore)\b/ui', $t)) return 'it';
    if (preg_match('/\b(ik wil|hoe kan|opzeggen|alstublieft|graag|huur)\b/ui', $t)) return 'nl';
    if (preg_match('/\b(jag vill|hur kan|avtal|säga upp)\b/ui', $t)) return 'sv';
    if (preg_match('/\b(i want|how can|please|cancel|contract|my landlord)\b/i', $t)) return 'en';
    if (preg_match('/[äöüß]|\b(ich|nicht|möchte|bitte|kündigen)\b/ui', $t)) return 'de';
    return null;
}
function ch_lang_order(?string $code): string {
    $M = [
      'tr'=>['Türkisch','Yanıtının TAMAMINI yalnızca Türkçe yaz.'],
      'en'=>['Englisch','Write your ENTIRE reply in English only.'],
      'es'=>['Spanisch','Escribe TODA tu respuesta únicamente en español.'],
      'fr'=>['Französisch','Rédige TOUTE ta réponse uniquement en français.'],
      'it'=>['Italienisch','Scrivi TUTTA la risposta solo in italiano.'],
      'nl'=>['Niederländisch','Schrijf je HELE antwoord uitsluitend in het Nederlands.'],
      'pl'=>['Polnisch','Całą odpowiedź napisz wyłącznie po polsku.'],
      'sv'=>['Schwedisch','Skriv HELA ditt svar enbart på svenska.'],
      'pt'=>['Portugiesisch','Escreve TODA a resposta apenas em português.'],
      'ru'=>['Russisch','Пиши ВЕСЬ ответ только на русском языке.'],
      'ar'=>['Arabisch','اكتب إجابتك كاملة باللغة العربية فقط.'],
    ];
    if (!$code || !isset($M[$code])) return '';
    return " ABSOLUTE SPRACHREGEL (hat Vorrang vor ALLEM anderen): Der Nutzer schreibt auf "
         . $M[$code][0] . ". Deine GESAMTE Antwort MUSS auf " . $M[$code][0]
         . " sein — KEIN einziger deutscher Satz. " . $M[$code][1];
}
PHPH;

/* ══ api.php (ana sohbet) ══ */
$fA = __DIR__.'/api.php';
$a = @file_get_contents($fA);
if ($a===false) exit("api.php okunamadi\n");
$failA=[];
if (strpos($a,'CH_LANG_FORCE')!==false) { echo "api.php: zaten ekli, atlandi\n"; }
else {
    $o = 'function doAiChat(array $b): void {';
    $c=0; $a=str_replace($o, $helper."\n".$o, $a, $c);
    echo ($c===1?"  ✓ [A1] api.php: algilama yardimcisi eklendi\n":"  ✗ [A1] doAiChat anchor ($c)\n"); if($c!==1)$failA[]='A1';

    $o2 = "    \$reply = (\$provider === 'claude')\n"
        . "        ? callClaude(\$sys, \$transcript, 1600)\n"
        . "        : callDeepSeekChat(\$sys, \$transcript, 1600);";
    $n2 = "    \$__sn = ch_sniff_lang(\$message) ?: (in_array(\$lang,['tr','ru','ar','en','fr','es','it','nl','pl','sv','pt'],true)?\$lang:null); /*CH_LANG_FORCE*/\n"
        . "    \$sys .= ch_lang_order(\$__sn);\n"
        . $o2;
    $c=0; $a=str_replace($o2,$n2,$a,$c);
    echo ($c===1?"  ✓ [A2] api.php: kesin dil emri sistem promptuna eklendi\n":"  ✗ [A2] reply anchor ($c)\n"); if($c!==1)$failA[]='A2';
}

/* ══ fall-api.php (Dava Ac) ══ */
$fF = __DIR__.'/fall-api.php';
$f = @file_get_contents($fF);
if ($f===false) exit("fall-api.php okunamadi\n");
$failF=[];
if (strpos($f,'CH_LANG_FORCE')!==false) { echo "fall-api.php: zaten ekli, atlandi\n"; }
else {
    $o = "const FALL_QUOTA = ['basic' => 5, 'pro' => 20, 'elite' => 100];";
    $c=0; $f=str_replace($o, $o."\n".$helper, $f, $c);
    echo ($c===1?"  ✓ [F1] fall-api.php: algilama yardimcisi eklendi\n":"  ✗ [F1] FALL_QUOTA anchor ($c)\n"); if($c!==1)$failF[]='F1';

    /* fall_chat: son kullanici mesajindan algila */
    $o2 = "    \$reply = chAI1(array_merge(";
    $n2 = "    \$__lastU=''; foreach (array_reverse(\$msgs) as \$__m) { if ((\$__m['role'] ?? '')==='user') { \$__lastU=(string)(\$__m['content'] ?? ''); break; } } /*CH_LANG_FORCE*/\n"
        . "    \$sys .= ch_lang_order(ch_sniff_lang(\$__lastU));\n"
        . $o2;
    $c=0; $f=str_replace($o2,$n2,$f,$c);
    echo ($c===1?"  ✓ [F2] fall_chat: kesin dil emri\n":"  ✗ [F2] chAI1 anchor ($c)\n"); if($c!==1)$failF[]='F2';

    /* fall_solve: tum kullanici mesajlarindan algila -> belge dili */
    $o3 = '    $document = chAI2($sys, $usr, 4000, array_values($photos));';
    $n3 = "    \$__uTxt=''; foreach (\$msgs as \$__m) { if ((\$__m['role'] ?? '')==='user') \$__uTxt .= ' '.(string)(\$__m['content'] ?? ''); } /*CH_LANG_FORCE*/\n"
        . "    \$__ord = ch_lang_order(ch_sniff_lang(\$__uTxt));\n"
        . "    if (\$__ord !== '') { \$sys .= \$__ord; \$usr .= \"\\n\\n\".\$__ord; }\n"
        . $o3;
    $c=0; $f=str_replace($o3,$n3,$f,$c);
    echo ($c===1?"  ✓ [F3] fall_solve: belge dili kesin emirle\n":"  ✗ [F3] chAI2 anchor ($c)\n"); if($c!==1)$failF[]='F3';
}

if ($failA || $failF) { echo "\nHATA: eslesmeyen adimlar: ".implode(',',array_merge($failA,$failF))." — HICBIR dosya degistirilmedi.\n"; exit; }

/* lint + yaz (iki dosya birden) */
foreach ([[$fA,$a],[$fF,$f]] as $pair) {
    $tmp = tempnam(sys_get_temp_dir(),'lf').'.php';
    file_put_contents($tmp,$pair[1]);
    $lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
    if ($rc!==0) { echo "\nLINT HATASI (".basename($pair[0]).") — HICBIR dosya degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
}
file_put_contents($fA.'.bak-langforce-'.date('Ymd-His'), @file_get_contents($fA));
file_put_contents($fF.'.bak-langforce2-'.date('Ymd-His'), @file_get_contents($fF));
file_put_contents($fA,$a);
file_put_contents($fF,$f);
echo "\n✓ CH_LANG_FORCE uygulandi. Dil artik sunucuda ALGILANIP kesin EMIR olarak veriliyor:\n";
echo "   Turkce yazana Turkce, Ispanyolca yazana Ispanyolca — sohbet + Dava Ac + belge.\n";
