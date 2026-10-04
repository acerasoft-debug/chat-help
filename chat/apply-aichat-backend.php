<?php
/**
 * ChatHelp — apply-aichat-backend (CH_AICHAT_BE)
 *  api.php'ye 'aichat' action'i ekler:
 *   • callDeepSeekChat(system,user,max) — mevcut callDeepSeek()'e DOKUNMADAN,
 *     ozel sistem promptuyla konusma icin YENI, ayri fonksiyon.
 *   • doAiChat($body) — {message, history[], provider, profile, lang, nudge}
 *     alir; PROFIL VERISI (ad/soyad/adres/telefon/email) DeepSeek/Claude'a
 *     GITMEDEN ONCE placeholder'a cevrilir (belge uretimindeki anonimlestirme
 *     ile ayni prensip). Yaniti + kullanilan provider'i dondurur.
 * KULLANIM: pull-updates.php?files=apply-aichat-backend.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-aichat-backend BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/api.php';
$src  = file_get_contents($file);
if ($src === false) exit("api.php okunamadi\n");
if (strpos($src, 'CH_AICHAT_BE') !== false) exit("Zaten ekli (CH_AICHAT_BE).\n");
file_put_contents($file . '.bak-aichatbe-' . date('Ymd-His'), $src);

/* ── [1] dispatch tablosuna 'aichat' ekle ── */
$dispAnchor = "'coverletter'     => doCoverLetter(\$body),";
$dispNew    = $dispAnchor . "\n    'aichat'          => doAiChat(\$body),";
$src2 = str_replace($dispAnchor, $dispNew, $src, $c1);
if ($c1) { $src = $src2; echo "  ✓ [1] dispatch tablosuna 'aichat' eklendi ($c1)\n"; }
else { echo "  ✗ [1] dispatch anchor bulunamadi — durduruluyor.\n"; exit; }

/* ── [2] callDeepSeek() sonrasina yeni fonksiyonlari ekle ── */
$fnAnchor = "function callDeepSeek(string \$prompt, int \$max=500): ?string {
    \$payload = ['model'=>'deepseek-chat','max_tokens'=>\$max,
                'messages'=>[['role'=>'system','content'=>'Legal research assistant. Concise, factual, current information only.'],
                             ['role'=>'user','content'=>\$prompt]]];
    \$ch = curl_init('https://api.deepseek.com/v1/chat/completions');
    curl_setopt_array(\$ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>json_encode(\$payload),
        CURLOPT_HTTPHEADER=>['Authorization: Bearer '.DEEPSEEK_KEY,'content-type: application/json'],
        CURLOPT_TIMEOUT=>30]);
    \$res = curl_exec(\$ch); curl_close(\$ch);
    \$data = json_decode(\$res, true);
    return \$data['choices'][0]['message']['content'] ?? null;
}";

$newFns = <<<'PHPFN'


/* CH_AICHAT_BE — genel sohbet icin ozel-sistem-promptlu DeepSeek cagrisi (callDeepSeek() ayri kalir, dokunulmadi) */
function callDeepSeekChat(string $system, string $user, int $max = 700): ?string {
    $payload = ['model'=>'deepseek-chat','max_tokens'=>$max,
                'messages'=>[['role'=>'system','content'=>$system],['role'=>'user','content'=>$user]]];
    $ch = curl_init('https://api.deepseek.com/v1/chat/completions');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>json_encode($payload),
        CURLOPT_HTTPHEADER=>['Authorization: Bearer '.DEEPSEEK_KEY,'content-type: application/json'],
        CURLOPT_TIMEOUT=>30]);
    $res = curl_exec($ch); curl_close($ch);
    $data = json_decode($res, true);
    return $data['choices'][0]['message']['content'] ?? null;
}

/* CH_AICHAT_BE — genel sohbet action'i. Profil verisi (ad/adres/tel/email)
   yukari akis (DeepSeek/Claude) GORMEDEN ONCE placeholder'a cevrilir. */
function doAiChat(array $b): void {
    $message  = trim((string)($b['message'] ?? ''));
    $history  = is_array($b['history'] ?? null) ? $b['history'] : [];
    $provider = ($b['provider'] ?? 'deepseek') === 'claude' ? 'claude' : 'deepseek';
    $profile  = is_array($b['profile'] ?? null) ? $b['profile'] : [];
    $lang     = $b['lang'] ?? 'de';
    $nudge    = !empty($b['nudge']);

    if ($message === '') { respond(['error' => 'empty message']); return; }
    if (mb_strlen($message) > 4000) $message = mb_substr($message, 0, 4000);

    /* ── anonimlestirme: gercek ad/adres/tel/email hicbir zaman AI'ya gitmez ── */
    $repl = [];
    if (!empty($profile['f1'])) $repl[$profile['f1']] = '[VORNAME]';
    if (!empty($profile['f2'])) $repl[$profile['f2']] = '[NACHNAME]';
    if (!empty($profile['f3'])) $repl[$profile['f3']] = '[ADRESSE]';
    if (!empty($profile['f4'])) $repl[$profile['f4']] = '[ORT]';
    if (!empty($profile['f5'])) $repl[$profile['f5']] = '[TELEFON]';
    if (!empty($profile['f7'])) $repl[$profile['f7']] = '[EMAIL]';
    $safeMsg = $repl ? strtr($message, $repl) : $message;

    $transcript = '';
    $n = 0;
    foreach ($history as $h) {
        if ($n++ > 20) break; // baglami makul tut
        $role = ($h['role'] ?? 'user') === 'ai' ? 'Assistant' : 'User';
        $content = (string)($h['content'] ?? '');
        if ($repl) $content = strtr($content, $repl);
        if (mb_strlen($content) > 800) $content = mb_substr($content, 0, 800);
        $transcript .= "{$role}: {$content}\n";
    }
    $transcript .= "User: {$safeMsg}\nAssistant:";

    $sys = "You are ChatHelp's friendly, concise legal assistant, replying in language code '{$lang}'. "
         . "Never ask for the user's real name or address, and never repeat any [VORNAME]/[NACHNAME]/[ADRESSE]/[ORT]/[TELEFON]/[EMAIL] placeholders literally — just continue naturally as if you already know them privately. "
         . "Keep replies short (2-5 sentences) and warm. "
         . "If the user's need clearly matches one of ChatHelp's tools, end your reply on its own line with exactly one tag from this list (nothing else on that line): [[SUGGEST:doc]] for a legal letter/document, [[SUGGEST:cv]] for CV/resume, [[SUGGEST:vst]] for a contract (rental/employment/car sale), [[SUGGEST:jobs]] for job search. Omit the tag if none fits yet.";
    if ($nudge) {
        $sys .= " The conversation has continued for a while — gently suggest creating a document or choosing a plan now, still friendly, not pushy.";
    }

    $reply = ($provider === 'claude')
        ? callClaude($sys, $transcript, 500)
        : callDeepSeekChat($sys, $transcript, 500);

    if (!$reply) { respond(['error' => 'AI request failed']); return; }
    respond(['reply' => trim($reply), 'provider' => $provider]);
}
PHPFN;

$src2 = str_replace($fnAnchor, $fnAnchor . $newFns, $src, $c2);
if ($c2) { $src = $src2; echo "  ✓ [2] callDeepSeekChat() + doAiChat() eklendi ($c2)\n"; }
else { echo "  ✗ [2] callDeepSeek anchor bulunamadi — durduruluyor.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(), 'api') . '.php';
file_put_contents($tmp, $src);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "\nLINT HATASI — api.php degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
file_put_contents($file, $src);
echo "\n✓ CH_AICHAT_BE uygulandi.\n";
echo "Test: POST api.php?action=aichat {message:'Merhaba'} -> {reply, provider} donmeli.\n";
