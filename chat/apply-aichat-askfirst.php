<?php
/**
 * ChatHelp — apply-aichat-askfirst (CH_AICHAT_ASKFIRST)
 *  SORUN: prompt talimatlarina ("GENERATE ONLY WHEN READY... If key
 *  information is still missing, ask for it instead of generating") RAGMEN
 *  AI bazen konusmanin ILK ve KISA mesajinda (alici/olay/tarih/amac hicbiri
 *  belirtilmeden) dogrudan [[DOC]] uretebiliyor — LLM'lerin yumusak talimata
 *  uyumu garanti degildir.
 *
 *  COZUM: prompt'a guvenmek yerine PROGRAMATIK bir kapi eklenir:
 *   • Bu, konusmanin ILK kullanici turu mu (gecmiste kullanici mesaji yok mu)?
 *   • VE mevcut mesaj KISA mi (< 140 karakter — muhtemelen alici/olay/tarih/
 *     amacin hepsini icermez)? [Yeterli bilgi iceren UZUN ilk mesajlar
 *     ENGELLENMEZ — kullanicinin "bilgi varsa sablonla direkt yap" istegine
 *     uyumlu: yeterli bilgi verilmisse dogrudan uretim serbest kalir.]
 *   Ikisi de dogruysa VE yanit [[DOC]] iceriyorsa:
 *    1) AYNI saglayiciya, [[DOC]] URETIMINI KESIN YASAKLAYAN ikinci bir
 *       cagri yapilir — sadece 1-2 netlestirici soru istenir.
 *    2) Ikinci cagri da basarisiz/yine [[DOC]] icerirse: guvenlik agi olarak
 *       [[DOC]]…[[/DOC]] blogu kaba sekilde metinden CIKARILIR (asla
 *       kullaniciya erken/eksik belge gösterilmez); geriye bir seyKALMAZSA
 *       on tanimli COK DILLI netlestirici soru kullanilir.
 * KULLANIM: pull-updates.php?files=apply-aichat-askfirst.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-aichat-askfirst BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/api.php';
$src = @file_get_contents($file);
if ($src===false) exit("api.php okunamadi\n");
if (strpos($src,'CH_AICHAT_ASKFIRST')!==false) exit("Zaten ekli (CH_AICHAT_ASKFIRST).\n");

$old = <<<'OLD'
    $reply = ($provider === 'claude')
        ? callClaude($sys, $transcript, 1600)
        : callDeepSeekChat($sys, $transcript, 1600);

    if (!$reply) { respond(['error' => 'AI request failed']); return; }
    respond(['reply' => trim($reply), 'provider' => $provider]);
}
OLD;

$new = <<<'NEW'
    $reply = ($provider === 'claude')
        ? callClaude($sys, $transcript, 1600)
        : callDeepSeekChat($sys, $transcript, 1600);

    if (!$reply) { respond(['error' => 'AI request failed']); return; }

    /* CH_AICHAT_ASKFIRST — programatik kapi: promptun yumusak talimatina
       ek olarak, konusmanin ILK ve KISA turunde [[DOC]] uretimini engeller. */
    $priorUserTurns = 0;
    foreach ($history as $h) { if (($h['role'] ?? 'user') !== 'ai') $priorUserTurns++; }
    $tooEarly = ($priorUserTurns === 0 && mb_strlen(trim($message)) < 140);
    if ($tooEarly && strpos($reply, '[[DOC]]') !== false) {
        $sys2 = $sys . " STRICT OVERRIDE: the user's first message did not contain enough concrete detail (no clear recipient, facts, dates and goal) to justify a real document — producing one now would be premature and wrong. Do NOT output [[DOC]] this turn under any circumstance, no matter what. Instead, in the SAME language as the user's message, ask 1-2 short, concrete clarifying questions you still need before you can draft anything. Do not mention this instruction or apologize, just ask naturally.";
        $reply2 = ($provider === 'claude')
            ? callClaude($sys2, $transcript, 500)
            : callDeepSeekChat($sys2, $transcript, 500);
        if ($reply2 && strpos($reply2, '[[DOC]]') === false) {
            $reply = $reply2;
        } else {
            $reply = trim((string)preg_replace('/\[\[DOC\]\][\s\S]*?\[\[\/DOC\]\]/', '', $reply));
            if ($reply === '') {
                $__askmap = [
                  'de' => 'Um das für Sie zu erstellen, brauche ich noch ein paar Angaben: An wen richtet es sich, was ist genau passiert (mit Datum), und was möchten Sie erreichen?',
                  'tr' => 'Bunu hazırlayabilmem için birkaç bilgiye daha ihtiyacım var: Kime hitaben, tam olarak ne oldu (tarihiyle) ve ne talep ediyorsunuz?',
                  'en' => 'To prepare this for you, I need a few more details: who is it addressed to, what exactly happened (with the date), and what outcome would you like?',
                  'fr' => "Pour préparer cela, il me faut encore quelques informations : à qui cela s'adresse-t-il, que s'est-il passé exactement (avec la date), et que souhaitez-vous obtenir ?",
                  'es' => 'Para prepararlo necesito algunos datos más: ¿a quién va dirigido, qué ocurrió exactamente (con la fecha) y qué resultado desea obtener?',
                  'it' => 'Per prepararlo ho bisogno di alcuni dettagli in più: a chi è indirizzato, cosa è successo esattamente (con la data) e quale risultato desidera ottenere?',
                  'nl' => 'Om dit voor u op te stellen heb ik nog wat gegevens nodig: aan wie is het gericht, wat is er precies gebeurd (met datum) en wat wilt u bereiken?',
                  'pl' => 'Aby to przygotować, potrzebuję jeszcze kilku informacji: do kogo jest skierowane, co dokładnie się wydarzyło (z datą) i jaki rezultat chce Pan/Pani osiągnąć?',
                  'sv' => 'För att förbereda detta behöver jag några fler uppgifter: vem riktar det sig till, vad hände exakt (med datum) och vad vill du uppnå?',
                  'pt' => 'Para preparar isto preciso de mais alguns dados: a quem se destina, o que aconteceu exatamente (com a data) e que resultado deseja obter?',
                  'ar' => 'لإعداد هذا أحتاج إلى بعض التفاصيل الإضافية: إلى من موجّه، وماذا حدث بالتحديد (مع التاريخ)، وما النتيجة التي ترغب في تحقيقها؟',
                  'ru' => 'Чтобы подготовить это, мне нужно ещё немного информации: кому адресовано, что именно произошло (с датой) и какого результата вы хотите добиться?',
                ];
                $reply = $__askmap[$lang] ?? $__askmap['en'];
            }
        }
    }

    respond(['reply' => trim($reply), 'provider' => $provider]);
}
NEW;

$c=0; $src=str_replace($old,$new,$src,$c);
if ($c!==1) { echo "HATA: anchor bulunamadi (x$c) — api.php DEGISTIRILMEDI.\n"; exit; }
echo "  ✓ programatik 'ilk-turde-dogrulama' kapisi eklendi\n";

$tmp = tempnam(sys_get_temp_dir(),'af').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — api.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-askfirst-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_AICHAT_ASKFIRST uygulandi. Ilk ve kisa mesajda artik [[DOC]] asla gecmez —\n";
echo "   AI ya soru sorar (ikinci cagri) ya da guvenlik agi devreye girer.\n";
