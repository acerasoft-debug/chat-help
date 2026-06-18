<?php
/**
 * ChatHelp — "Önce anla, sonra üret" dilekçe intake endpoint'i
 * ------------------------------------------------------------
 * Sorun: sabit sorularla zayıf belgeler üretiliyor, geri dönüş yok.
 * Çözüm: kullanıcı durumunu anlatır -> sistem duruma göre 1-2 (gerekirse
 * daha fazla) soru sorarak ihtiyacı ÖĞRENİR -> sonra güçlü belgeyi üretir.
 *
 * action=intake_chat   : adaptif anlama sohbeti (DeepSeek). Yeterince anlayınca
 *                        cevabın başına gizli "[READY]" koyar -> ön yüz "Dokument
 *                        erstellen" butonunu açar.
 * action=intake_solve  : anlaşılan duruma göre nihai belge (DeepSeek yapı + Claude metin),
 *                        gerçek profil + tarih + ton ile. 3. taraflara gerçek veri gitmez.
 */
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/db.php';
if (file_exists(__DIR__ . '/config.php')) require_once __DIR__ . '/config.php';

if (!defined('DEEPSEEK_KEY') || !defined('CLAUDE_KEY')) {
    http_response_code(500);
    echo json_encode(['error' => 'Konfigurationsfehler. Bitte wenden Sie sich an den Support.']);
    exit;
}

/* Plan normalize — paket alınınca KESİN tanınsın (Pro/PRO/pro_plan -> pro) */
function ch_norm_plan(string $raw): string {
    $p = strtolower(trim($raw));
    if ($p === '') return 'free';
    if (strpos($p,'elite')!==false || strpos($p,'premium')!==false) return 'elite';
    if (strpos($p,'pro')!==false) return 'pro';
    if (strpos($p,'basic')!==false || strpos($p,'basis')!==false || strpos($p,'start')!==false) return 'basic';
    return $p;
}

function intake_user(): array {
    $auth  = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $tok   = trim(str_replace('Bearer', '', $auth));
    $parts = explode('.', $tok);
    if (count($parts) !== 3) { http_response_code(401); echo json_encode(['error'=>'Bitte zuerst anmelden.']); exit; }
    [$h,$p,$s] = $parts;
    $exp = rtrim(strtr(base64_encode(hash_hmac('sha256', "$h.$p", JWT_SECRET, true)), '+/', '-_'), '=');
    if (!hash_equals($exp, $s)) { http_response_code(401); echo json_encode(['error'=>'Sitzung ungültig.']); exit; }
    $d = json_decode(base64_decode(strtr($p, '-_', '+/')), true);
    if (!$d || ($d['exp'] ?? 0) < time()) { http_response_code(401); echo json_encode(['error'=>'Sitzung abgelaufen.']); exit; }
    $plan = 'free';
    try {
        $pdo = db();
        $st  = $pdo->prepare('SELECT plan, COALESCE(status,"active") st FROM user_plans WHERE user_id=? ORDER BY id DESC LIMIT 1');
        $st->execute([$d['sub']]);
        if ($r = $st->fetch()) {
            $active = in_array(strtolower(trim($r['st'] ?? 'active')), ['active','aktiv','paid','complete','completed','trialing',''], true);
            $plan   = $active ? ch_norm_plan($r['plan'] ?? '') : 'free';
        }
    } catch (Exception $e) {}
    return ['id'=>$d['sub'], 'email'=>$d['email'] ?? '', 'plan'=>$plan];
}

function ch_ds(array $messages, float $temp = 0.4, int $max = 2000): string {
    $ch = curl_init('https://api.deepseek.com/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>90, CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.DEEPSEEK_KEY],
        CURLOPT_POSTFIELDS=>json_encode(['model'=>'deepseek-chat','messages'=>$messages,'temperature'=>$temp,'max_tokens'=>$max]),
    ]);
    $res = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    if ($res===false){ error_log('intake ds: '.$err); return ''; }
    $d = json_decode($res,true);
    if (isset($d['error'])) error_log('intake ds api: '.json_encode($d['error']));
    return $d['choices'][0]['message']['content'] ?? '';
}

function ch_claude(string $sys, string $usr, int $max = 4000): string {
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>120, CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-api-key: '.CLAUDE_KEY,'anthropic-version: 2023-06-01'],
        CURLOPT_POSTFIELDS=>json_encode(['model'=>'claude-sonnet-4-6','max_tokens'=>$max,'system'=>$sys,'messages'=>[['role'=>'user','content'=>$usr]]]),
    ]);
    $res = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    if ($res===false){ error_log('intake claude: '.$err); return ''; }
    $d = json_decode($res,true);
    if (isset($d['error'])) error_log('intake claude api: '.json_encode($d['error']));
    return $d['content'][0]['text'] ?? '';
}

$user   = intake_user();
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? 'intake_chat';
$msgs   = $body['messages'] ?? [];

$convo = '';
foreach ($msgs as $m) {
    $role = ($m['role'] ?? 'user') === 'user' ? 'NUTZER' : 'BERATER';
    $convo .= "$role: " . trim($m['content'] ?? '') . "\n";
}

/* ── Adaptif anlama sohbeti ── */
if ($action === 'intake_chat') {
    $sys = "Du bist ein erfahrener deutscher Rechtsberater. Deine EINZIGE Aufgabe in diesem Schritt: "
         . "GENAU verstehen, was der Nutzer für sein Dokument/seinen Antrag braucht — BEVOR etwas erstellt wird. "
         . "Stelle gezielte, einfache Rückfragen, EINE bis ZWEI pro Nachricht. "
         . "Bei einfachen Anliegen reichen 1–2 Fragen; bei komplexen Themen (z.B. Aufenthalt, Einspruch, "
         . "Widerspruch, Kündigung, Steuer, Sozialleistungen) frage so lange weiter, bis du WIRKLICH alles "
         . "Wesentliche kennst: Ziel des Nutzers, beteiligte Stelle/Person, relevante Daten/Fristen, "
         . "Vorgeschichte, gewünschtes Ergebnis. "
         . "Bleib immer beim Thema des Nutzers, weiche NICHT ab, schlage nichts Unpassendes vor. "
         . "Wenn du genug verstanden hast, beginne deine Antwort mit dem Marker [READY] in der ersten Zeile, "
         . "danach eine kurze Bestätigung in 1–2 Sätzen, was du erstellen wirst. "
         . "Solange du noch Fragen hast, NIEMALS [READY] schreiben.";
    $reply = ch_ds(array_merge(
        [['role'=>'system','content'=>$sys]],
        array_map(fn($m)=>['role'=>($m['role']==='user'?'user':'assistant'),'content'=>$m['content']], $msgs)
    ), 0.5);
    if ($reply === '') $reply = 'Der KI-Dienst ist momentan nicht erreichbar. Bitte erneut versuchen.';
    $ready = (strpos($reply, '[READY]') !== false);
    $reply = trim(str_replace('[READY]', '', $reply));
    echo json_encode(['reply'=>$reply, 'ready'=>$ready]);
    exit;
}

/* ── Anlaşılan duruma göre güçlü belge ── */
if ($action === 'intake_solve') {
    // 1) Yapı + hukuki çerçeve (anlama derinleştirme)
    $struct = ch_ds([
        ['role'=>'system','content'=>'Du bist deutscher Jurist. Fasse den Bedarf des Nutzers präzise zusammen und bestimme: passender Dokumententyp, einschlägige §§, Fristen, die stärksten Argumente. Kompakt und strukturiert.'],
        ['role'=>'user','content'=>"GESPRÄCH:\n$convo"],
    ], 0.3, 1500);

    // 2) Gerçek profil + tarih + ton (sadece sunucuda birleşir)
    $prof  = $body['profile'] ?? [];
    $datum = trim($body['datum'] ?? date('d.m.Y'));
    $toneMap = [
        'formell'  => 'höflich-formell, professioneller Briefstil',
        'sachlich' => 'sachlich-nüchtern, klar, ohne Floskeln',
        'bestimmt' => 'bestimmt und fordernd, aber respektvoll',
    ];
    $toneStr = $toneMap[trim($body['tone'] ?? 'formell')] ?? $toneMap['formell'];
    $absName = trim(($prof['f1'] ?? '') . ' ' . ($prof['f2'] ?? ''));
    $absAdr  = trim(($prof['f3'] ?? '') . (($prof['f4'] ?? '') ? ', ' . $prof['f4'] : ''));
    $profBlock = "ABSENDERDATEN (exakt verwenden, KEINE Platzhalter):\n"
        . "Name: "    . ($absName ?: '[Vorname Nachname]') . "\n"
        . "Adresse: " . ($absAdr  ?: '[Straße, PLZ Ort]')  . "\n"
        . "E-Mail: "  . ($prof['f7'] ?? '') . "\n"
        . "Telefon: " . ($prof['f6'] ?? '') . "\n"
        . "Datum: "   . $datum . "\n\n";

    $sys = "Du bist ein erfahrener deutscher Rechtsanwalt. Erstelle ein vollständiges, sofort einsetzbares "
         . "deutsches Dokument, das GENAU auf das Anliegen des Nutzers zugeschnitten ist. "
         . "Korrekte Briefform: Absender (echte Daten), Empfänger, Ort + Datum, Betreff, Anrede, "
         . "ausführliche Begründung mit §-Verweisen, klare Forderung + Frist, Grußformel. "
         . "Schreibstil: $toneStr. "
         . "Reiner Fließtext — kein Markdown, keine Sternchen, keine Emojis. "
         . "Echte Absenderdaten und Datum verwenden. Kein Versandvermerk (z.B. 'Per Einschreiben'). "
         . "Nur das fertige Dokument ausgeben.";
    $usr = $profBlock . "ANLIEGEN (Gespräch):\n$convo\n\nJURISTISCHE EINORDNUNG:\n$struct\n\nErstelle jetzt das finale Dokument.";
    $doc = ch_claude($sys, $usr, 4000);

    echo json_encode([
        'success'  => (bool)$doc,
        'document' => $doc ?: 'Das Dokument konnte nicht erstellt werden. Bitte erneut versuchen.',
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unbekannte Aktion']);
