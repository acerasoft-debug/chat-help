<?php
/**
 * ChatHelp — apply-lang-force2 (CH_LANG_FORCE2) — DIL GARANTISI v2
 *
 *  KANIT (kullanici ekran goruntusu): TR arayuzlu kullaniciya sohbet yine
 *  ALMANCA cevap verdi. Sebep: v1 once mesaj METNINDEN algiliyordu — mesaj
 *  Almanca icerik tasiyorsa (foto/Bescheid metni, Almanca konu) 'de'
 *  algilanip zorlamasiz kaliyordu; model de Almanca'ya kayiyordu.
 *
 *  YENI POLITIKA (kesin):
 *   [1] ARAYUZ DILI HER ZAMAN KAZANIR: kullanicinin sectigi UI dili (lang
 *       parametresi) desteklenen bir dilse hedef dil ODUR — mesaj icinde
 *       Almanca metin olsa bile. Metin algilama yalnizca yedege duser.
 *   [2] DOGRULA-VE-YENIDEN-YAZ: AI cevabi dondukten sonra sunucu, cevabin
 *       dilini DETERMINISTIK dedektorle kontrol eder. Hedef dil de-degilken
 *       cevap Almanca ciktiysa, "cevabin YANLIS dilde, tamamen yeniden yaz"
 *       emriyle OTOMATIK ikinci cagri yapilir. Yani Almanca cevap artik
 *       kullaniciya ULASAMAZ (sohbet + Dava Ac soru-cevap + Dava Ac belgesi).
 *   [3] Dava Ac istekleri artik frontend'den UI dilini de tasir (fetch
 *       sarmalayici — cagri noktalarindan bagimsiz, index.php'ye eklenir).
 * KULLANIM: pull-updates.php?files=apply-lang-force2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-lang-force2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$SUP = "['tr','ru','ar','en','fr','es','it','nl','pl','sv','pt']";
$fail=[];

/* ══ api.php (ana sohbet) ══ */
$fA = __DIR__.'/api.php';
$a = @file_get_contents($fA);
if ($a===false) exit("api.php okunamadi\n");
if (strpos($a,'CH_LANG_FORCE2')!==false) { echo "api.php: zaten ekli, atlandi\n"; }
else {
    /* [A1] UI dili oncelikli hale getir */
    $o1 = "    \$__sn = ch_sniff_lang(\$message) ?: (in_array(\$lang,['tr','ru','ar','en','fr','es','it','nl','pl','sv','pt'],true)?\$lang:null); /*CH_LANG_FORCE*/";
    $n1 = "    \$__sn = (in_array(\$lang,{$SUP},true)?\$lang:null) ?: ch_sniff_lang(\$message); /*CH_LANG_FORCE2 — UI dili HER ZAMAN oncelikli*/";
    $c=0; $a=str_replace($o1,$n1,$a,$c);
    echo ($c===1?"  ✓ [A1] sohbet: UI dili oncelikli\n":"  ✗ [A1] anchor ($c)\n"); if($c!==1)$fail[]='A1';

    /* [A2] dogrula-ve-yeniden-yaz */
    $o2 = "    if (!\$reply) { respond(['error' => 'AI request failed']); return; }";
    $n2 = "    if (!\$reply) { respond(['error' => 'AI request failed']); return; }\n"
        . "    if (\$__sn && \$__sn !== 'de' && ch_sniff_lang(mb_substr((string)\$reply, 0, 500)) === 'de') { /*CH_LANG_FORCE2 — Almanca cikti: zorunlu yeniden yazim*/\n"
        . "        \$sysR = \$sys . \" WARNUNG: Deine letzte Antwort war fälschlicherweise auf Deutsch — das ist ein schwerer FEHLER. Schreibe die komplette Antwort jetzt NEU, ausschließlich in der Zielsprache.\" . ch_lang_order(\$__sn);\n"
        . "        \$replyR = (\$provider === 'claude') ? callClaude(\$sysR, \$transcript, 1600) : callDeepSeekChat(\$sysR, \$transcript, 1600);\n"
        . "        if (\$replyR && ch_sniff_lang(mb_substr(\$replyR, 0, 500)) !== 'de') \$reply = \$replyR;\n"
        . "    }";
    $c=0; $a=str_replace($o2,$n2,$a,$c);
    echo ($c===1?"  ✓ [A2] sohbet: dogrula-ve-yeniden-yaz\n":"  ✗ [A2] anchor ($c)\n"); if($c!==1)$fail[]='A2';
}

/* ══ fall-api.php ══ */
$fF = __DIR__.'/fall-api.php';
$f = @file_get_contents($fF);
if ($f===false) exit("fall-api.php okunamadi\n");
if (strpos($f,'CH_LANG_FORCE2')!==false) { echo "fall-api.php: zaten ekli, atlandi\n"; }
else {
    /* [F1] fall_chat: UI dili oncelikli */
    $o3 = "    \$__lastU=''; foreach (array_reverse(\$msgs) as \$__m) { if ((\$__m['role'] ?? '')==='user') { \$__lastU=(string)(\$__m['content'] ?? ''); break; } } /*CH_LANG_FORCE*/\n"
        . "    \$sys .= ch_lang_order(ch_sniff_lang(\$__lastU));";
    $n3 = "    \$__lastU=''; foreach (array_reverse(\$msgs) as \$__m) { if ((\$__m['role'] ?? '')==='user') { \$__lastU=(string)(\$__m['content'] ?? ''); break; } } /*CH_LANG_FORCE*/\n"
        . "    \$__uiL = (string)(\$body['lang'] ?? ''); /*CH_LANG_FORCE2*/\n"
        . "    \$__fsn = (in_array(\$__uiL,{$SUP},true)?\$__uiL:null) ?: ch_sniff_lang(\$__lastU);\n"
        . "    \$sys .= ch_lang_order(\$__fsn);";
    $c=0; $f=str_replace($o3,$n3,$f,$c);
    echo ($c===1?"  ✓ [F1] fall_chat: UI dili oncelikli\n":"  ✗ [F1] anchor ($c)\n"); if($c!==1)$fail[]='F1';

    /* [F2] fall_chat: dogrula-ve-yeniden-yaz */
    $o4 = "    ), 0.5);\n    echo json_encode(['reply'";
    $n4 = "    ), 0.5);\n"
        . "    if (isset(\$__fsn) && \$__fsn && \$__fsn !== 'de' && \$reply && ch_sniff_lang(mb_substr(\$reply, 0, 500)) === 'de') { /*CH_LANG_FORCE2*/\n"
        . "        \$__sysR = \$sys . \" WARNUNG: Deine letzte Antwort war fälschlicherweise auf Deutsch — FEHLER. Schreibe alles NEU, ausschließlich in der Zielsprache.\" . ch_lang_order(\$__fsn);\n"
        . "        \$__rR = chAI1(array_merge([['role'=>'system','content'=>\$__sysR]], array_map(fn(\$m)=>['role'=>(\$m['role']==='user'?'user':'assistant'),'content'=>\$m['content']], \$msgs)), 0.5);\n"
        . "        if (\$__rR && ch_sniff_lang(mb_substr(\$__rR, 0, 500)) !== 'de') \$reply = \$__rR;\n"
        . "    }\n"
        . "    echo json_encode(['reply'";
    $c=0; $f=str_replace($o4,$n4,$f,$c);
    echo ($c===1?"  ✓ [F2] fall_chat: dogrula-ve-yeniden-yaz\n":"  ✗ [F2] anchor ($c)\n"); if($c!==1)$fail[]='F2';

    /* [F3] fall_solve: UI dili oncelikli */
    $o5 = "    \$__uTxt=''; foreach (\$msgs as \$__m) { if ((\$__m['role'] ?? '')==='user') \$__uTxt .= ' '.(string)(\$__m['content'] ?? ''); } /*CH_LANG_FORCE*/\n"
        . "    \$__ord = ch_lang_order(ch_sniff_lang(\$__uTxt));\n"
        . "    if (\$__ord !== '') { \$sys .= \$__ord; \$usr .= \"\\n\\n\".\$__ord; }";
    $n5 = "    \$__uTxt=''; foreach (\$msgs as \$__m) { if ((\$__m['role'] ?? '')==='user') \$__uTxt .= ' '.(string)(\$__m['content'] ?? ''); } /*CH_LANG_FORCE*/\n"
        . "    \$__uiL2 = (string)(\$body['lang'] ?? ''); /*CH_LANG_FORCE2*/\n"
        . "    \$__fsn2 = (in_array(\$__uiL2,{$SUP},true)?\$__uiL2:null) ?: ch_sniff_lang(\$__uTxt);\n"
        . "    \$__ord = ch_lang_order(\$__fsn2);\n"
        . "    if (\$__ord !== '') { \$sys .= \$__ord; \$usr .= \"\\n\\n\".\$__ord; }";
    $c=0; $f=str_replace($o5,$n5,$f,$c);
    echo ($c===1?"  ✓ [F3] fall_solve: UI dili oncelikli\n":"  ✗ [F3] anchor ($c)\n"); if($c!==1)$fail[]='F3';

    /* [F4] fall_solve: belge dogrula-ve-yeniden-yaz */
    $o6 = '    $document = chAI2($sys, $usr, 4000, array_values($photos));';
    $n6 = "    \$document = chAI2(\$sys, \$usr, 4000, array_values(\$photos));\n"
        . "    if (isset(\$__fsn2) && \$__fsn2 && \$__fsn2 !== 'de' && \$document && ch_sniff_lang(mb_substr(\$document, 0, 600)) === 'de') { /*CH_LANG_FORCE2*/\n"
        . "        \$__sysD = \$sys . \" WARNUNG: Dein letztes Dokument war fälschlicherweise auf Deutsch — FEHLER. Erstelle das GESAMTE Dokument jetzt NEU, ausschließlich in der Zielsprache.\" . ch_lang_order(\$__fsn2);\n"
        . "        \$__dR = chAI2(\$__sysD, \$usr, 4000, array_values(\$photos));\n"
        . "        if (\$__dR && ch_sniff_lang(mb_substr(\$__dR, 0, 600)) !== 'de') \$document = \$__dR;\n"
        . "    }";
    $c=0; $f=str_replace($o6,$n6,$f,$c);
    echo ($c===1?"  ✓ [F4] fall_solve: belge dogrula-ve-yeniden-yaz\n":"  ✗ [F4] anchor ($c)\n"); if($c!==1)$fail[]='F4';
}

/* ══ index.php: Dava Ac isteklerine UI dilini ekle (fetch sarmalayici) ══ */
$fI = __DIR__.'/index.php';
$i = @file_get_contents($fI);
if ($i===false) exit("index.php okunamadi\n");
if (strpos($i,'CH_LANG_FORCE2')!==false) { echo "index.php: zaten ekli, atlandi\n"; }
else {
    $bundle = "<script id=\"ch-fall-lang-js\">\n"
        . "/* CH_LANG_FORCE2 — Dava Ac isteklerine UI dilini otomatik ekle */\n"
        . "try{(function(){\n"
        . "  var of=window.fetch.bind(window);\n"
        . "  window.fetch=function(input,init){\n"
        . "    try{\n"
        . "      var url=(typeof input==='string')?input:((input&&input.url)||'');\n"
        . "      if(/fall-api\\.php/.test(url)&&init&&typeof init.body==='string'){\n"
        . "        var b=JSON.parse(init.body);\n"
        . "        if(b&&!b.lang){ b.lang=(localStorage.getItem('ch_uilang')||'de'); init.body=JSON.stringify(b); }\n"
        . "      }\n"
        . "    }catch(e){}\n"
        . "    return of(input,init);\n"
        . "  };\n"
        . "})();}catch(e){}\n"
        . "</script>";
    $pos = strrpos($i,'</body>');
    if ($pos===false) { echo "  ✗ [I1] </body> yok\n"; $fail[]='I1'; }
    else { $i = substr($i,0,$pos).$bundle."\n".substr($i,$pos); echo "  ✓ [I1] Dava Ac istekleri artik UI dilini tasiyor\n"; }
}

if ($fail) { echo "\nHATA: eslesmeyen adimlar: ".implode(',',$fail)." — HICBIR dosya degistirilmedi.\n"; exit; }

foreach ([[$fA,$a],[$fF,$f],[$fI,$i]] as $pair) {
    $tmp = tempnam(sys_get_temp_dir(),'l2').'.php';
    file_put_contents($tmp,$pair[1]);
    $lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
    if ($rc!==0) { echo "\nLINT HATASI (".basename($pair[0]).") — HICBIR dosya degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
}
file_put_contents($fA.'.bak-lf2-'.date('Ymd-His'), @file_get_contents($fA));
file_put_contents($fF.'.bak-lf2b-'.date('Ymd-His'), @file_get_contents($fF));
file_put_contents($fI.'.bak-lf2c-'.date('Ymd-His'), @file_get_contents($fI));
file_put_contents($fA,$a); file_put_contents($fF,$f); file_put_contents($fI,$i);
echo "\n✓ CH_LANG_FORCE2 uygulandi:\n";
echo "   • UI dilin HER ZAMAN kazanir (Almanca icerik yapistirsan bile cevap senin dilinde)\n";
echo "   • Cevap yine Almanca cikarsa sunucu OTOMATIK yeniden yazdirir — Almanca cevap kullaniciya ULASAMAZ\n";
