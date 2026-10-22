<?php
/**
 * ChatHelp — apply-aichat-intake (CH_AICHAT_INTAKE)
 *  AI sohbetini "kisa cevap + arac oner" modundan gercek bir HUKUK ALIM
 *  (intake) modeline yukseltir:
 *   • Kullanici serbest yazinca hemen sablona atlamaz — once soru-cevap ile
 *     ne istedigini, muhatabi, olaylari, tarihleri, amaci ogrenir.
 *   • Ayni sohbet baglamini korur (tek konu, tek dilekce olarak ilerler).
 *   • Yeterli bilgi toplaninca TAM ve resmi dilekceyi [[DOC]]…[[/DOC]]
 *     arasinda uretir; ilgili ulke kanununun maddelerini (karmasik islemde
 *     emsal karar) belirtir; kisa ve profesyonel tutar. Eksik bilgi varsa
 *     uretmeden once sorar.
 *   • Gizlilik korunur: gercek ad/adres AI'ya gitmez (mevcut placeholder
 *     mekanizmasi aynen), dilekcedeki placeholder'lar istemci tarafinda
 *     gercek degerlerle degistirilir (frontend yamasinda).
 *  Bu yama api.php'deki CH_AICHAT_BE sistem-promptunu ve token limitini
 *  cerrahi olarak gunceller; callDeepSeek()/callClaude() imzalarina dokunmaz.
 * KULLANIM: pull-updates.php?files=apply-aichat-intake.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-aichat-intake BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/api.php';
$src  = @file_get_contents($file);
if ($src === false) exit("api.php okunamadi\n");
if (strpos($src, 'CH_AICHAT_INTAKE') !== false) exit("Zaten ekli (CH_AICHAT_INTAKE).\n");
if (strpos($src, 'CH_AICHAT_BE') === false && strpos($src, "ChatHelp's friendly, concise legal assistant") === false)
    exit("HATA: AI sohbet backend (CH_AICHAT_BE) bulunamadi — once apply-aichat-backend.php calistir.\n");
file_put_contents($file . '.bak-aiintake-' . date('Ymd-His'), $src);

/* ── 1) sistem promptunu (eski kisa hali) yeni intake promptuyla degistir ── */
$newSys = <<<'PHPX'
$country = strtoupper(preg_replace('/[^A-Za-z]/', '', (string)($b['country'] ?? 'DE')));
    $__lawh = [
      'DE'=>'German law (BGB, ZPO, TzBfG, StGB and related codes)',
      'AT'=>'Austrian law (ABGB and related codes)',
      'CH'=>'Swiss law (OR, ZGB)',
      'TR'=>'Turkish law (Turkish Code of Obligations, Labour Law, Consumer Law TKHK, Civil Code, Penal Code)',
      'FR'=>'French law (Code civil, Code du travail, Code de la consommation)',
      'BE'=>'Belgian law',
      'ES'=>'Spanish law (Codigo Civil, Estatuto de los Trabajadores, Ley de Consumidores)',
      'IT'=>'Italian law (Codice Civile)',
      'NL'=>'Dutch law (Burgerlijk Wetboek)',
      'PL'=>'Polish law (Kodeks cywilny)',
      'SE'=>'Swedish law',
      'PT'=>'Portuguese law (Codigo Civil)',
      'GB'=>'English law (England & Wales)',
      'US'=>'U.S. state law (note it varies by state)'
    ];
    $lawHint = $__lawh[$country] ?? $__lawh['DE'];
    $sys = "You are ChatHelp's experienced legal assistant working under {$lawHint}, replying in language code '{$lang}'. /*CH_AICHAT_INTAKE*/ "
         . "PRIVACY: never ask for the user's real name or address, and never echo the [VORNAME]/[NACHNAME]/[ADRESSE]/[ORT]/[TELEFON]/[EMAIL] placeholders back in chat — use them silently inside any document as if you already know the details. "
         . "INTAKE FIRST: when the user describes a problem, do NOT jump to a template. Understand it through a few focused, friendly questions — who the letter must be addressed to (authority/company/other party), the concrete facts, the key dates, and the outcome they want. Ask only what is still missing, one or two questions at a time, never a long list. "
         . "STAY IN CONTEXT: everything in this conversation concerns ONE matter and builds toward ONE document; remember every answer already given and never lose the thread, including anything attached or uploaded earlier. "
         . "GENERATE ONLY WHEN READY: once you know the recipient, the facts and the goal, write the COMPLETE formal letter/petition and wrap it EXACTLY between a line containing only [[DOC]] and a line containing only [[/DOC]]. Structure it properly: sender block (use the placeholders), recipient block, place and date, a subject/Betreff line, salutation, a CONCISE body that states the facts and cites the relevant {$lawHint} article/paragraph numbers, and — only for genuinely complex disputes — one fitting precedent; then a clear demand, a closing and a signature line. Keep it tight and professional, no padding. If key information is still missing, ask for it instead of generating. "
         . "AFTER GENERATING the user may ask for changes — update the document inside a new [[DOC]]…[[/DOC]] block in the same context. "
         . "If a specialized tool clearly fits better than a letter, you may instead end your reply on its own line with exactly one of: [[SUGGEST:cv]] (CV/resume), [[SUGGEST:vst]] (rental/employment/car-sale contract), [[SUGGEST:jobs]] (job search).";
PHPX;

$patSys = '/\$sys = "You are ChatHelp\'s friendly, concise legal assistant[\s\S]*?Omit the tag if none fits yet\.";/';
$c1 = 0;
$src = preg_replace_callback($patSys, function ($m) use ($newSys) { $c1 = 1; return $newSys; }, $src, 1, $c1);
if (!$c1) exit("HATA: eski sistem-prompt bloku bulunamadi — degistirilmedi.\n");
echo "  ✓ [1] intake sistem-promptu + ulke-hukuk ipucu kuruldu\n";

/* ── 2) token limitini yukselt (tam dilekce sigsin) ── */
$c2a = 0; $src = str_replace('callClaude($sys, $transcript, 500)',      'callClaude($sys, $transcript, 1600)',      $src, $c2a);
$c2b = 0; $src = str_replace('callDeepSeekChat($sys, $transcript, 500)', 'callDeepSeekChat($sys, $transcript, 1600)', $src, $c2b);
echo "  ✓ [2] token limiti 500 -> 1600 (claude:$c2a, deepseek:$c2b)\n";

$tmp = tempnam(sys_get_temp_dir(), 'api') . '.php';
file_put_contents($tmp, $src);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "\nLINT HATASI — api.php degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
file_put_contents($file, $src);
echo "\n✓ CH_AICHAT_INTAKE uygulandi. AI artik soru-cevap ile bilgi toplayip [[DOC]]…[[/DOC]] icinde tam dilekce uretiyor.\n";
