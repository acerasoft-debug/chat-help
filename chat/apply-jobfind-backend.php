<?php
/**
 * ChatHelp — IS BULMA BACKEND (api.php): jobsearch + coverletter — ANAHTARSIZ CALISIR
 * ------------------------------------------------------------------------
 *  doJobSearch:
 *    - Jooble anahtari VARSA (chat/jbdata/jooble.key): gercek is ilanlari (Jooble API).
 *    - Anahtar YOKSA: yapay zeka, o bolge+sektordeki GERCEK firmalari kendi
 *      bilgisinden onerir (uydurma firma/web/e-posta YASAK; "oneri, dogrula" etiketi).
 *  doCoverLetter: secilen firma/ilana + kullanici profiline gore ozel basvuru mektubu.
 *
 * KULLANIM: pull-updates.php?files=apply-jobfind-backend.php&run=1. SİL.
 *  (Anahtar opsiyonel — ileride set-jooble-key.php ile eklenince gercek ilanlara gecer.)
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-jobfind-backend BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/api.php";
if(!file_exists($file)) exit("api.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_JOBFIND")!==false) exit("Zaten ekli (CH_JOBFIND).\n");

/* 1) dispatch */
$a1="    default           => respond(['error' => 'Unknown action']),";
if(substr_count($src,$a1)!==1) exit("✗ dispatch anchor bulunamadi/coklu — DEGISIKLIK YOK.\n");
$src=str_replace($a1,"    'jobsearch'       => doJobSearch(\$body),\n    'coverletter'     => doCoverLetter(\$body),\n".$a1,$src);

/* 2) fonksiyonlar */
$a2="function respond(array \$data): void {";
if(substr_count($src,$a2)!==1) exit("✗ respond anchor bulunamadi/coklu — DEGISIKLIK YOK.\n");

$fns=<<<'PHP'
/* ── CH_JOBFIND — IS BULMA (anahtarli: Jooble / anahtarsiz: AI firma onerisi) + BASVURU MEKTUBU ── */
function chJoobleKey(): string {
    $f = __DIR__.'/jbdata/jooble.key';
    return is_file($f) ? trim((string)file_get_contents($f)) : '';
}
function doJobSearch(array $b): void {
    $kw = trim((string)($b['keywords'] ?? ''));
    if ($kw === '') { respond(['error' => 'no_keywords', 'jobs' => []]); return; }
    $key = chJoobleKey();
    if ($key !== '') { chJoobleSearch($b, $key); return; }
    chJobFindAI($b);
}
function chJoobleSearch(array $b, string $key): void {
    $kw  = trim((string)($b['keywords'] ?? ''));
    $loc = trim((string)($b['location'] ?? ''));
    $payload = json_encode(['keywords' => $kw, 'location' => $loc], JSON_UNESCAPED_UNICODE);
    $ch = curl_init('https://jooble.org/api/'.rawurlencode($key));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload, CURLOPT_TIMEOUT => 20,
    ]);
    $res = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($res === false || $code >= 400) { chJobFindAI($b); return; }
    $d = json_decode((string)$res, true);
    $jobs = [];
    foreach (($d['jobs'] ?? []) as $j) {
        $jobs[] = [
            'title'    => (string)($j['title'] ?? ''),
            'company'  => (string)($j['company'] ?? ''),
            'location' => (string)($j['location'] ?? ''),
            'snippet'  => mb_substr(trim(strip_tags((string)($j['snippet'] ?? ''))), 0, 260),
            'salary'   => (string)($j['salary'] ?? ''),
            'link'     => (string)($j['link'] ?? ''),
            'source'   => 'jooble',
        ];
        if (count($jobs) >= 25) break;
    }
    respond(['jobs' => $jobs, 'total' => (int)($d['totalCount'] ?? count($jobs)), 'source' => 'jooble']);
}
function chJobFindAI(array $b): void {
    $kw   = trim((string)($b['keywords'] ?? ''));
    $loc  = trim((string)($b['location'] ?? ''));
    $lang = strtolower((string)($b['lang'] ?? 'en'));
    $LNAMES = ['de'=>'German','en'=>'English','tr'=>'Turkish','fr'=>'French','es'=>'Spanish','it'=>'Italian','nl'=>'Dutch','pl'=>'Polish','sv'=>'Swedish','pt'=>'Portuguese','ru'=>'Russian','ar'=>'Arabic'];
    $ln = $LNAMES[$lang] ?? 'English';
    $sys = "You list REAL, currently-operating employers that a job seeker could apply to. Output ONLY a valid JSON array, no prose, no markdown fences. NEVER invent companies, websites or e-mail addresses. Include a website only if you are confident it is the company's real primary domain; otherwise use an empty string. Never output an e-mail address. Prefer well-known, verifiable employers actually present in the requested location and field.";
    $usr = "Job seeker field/role: {$kw}\nLocation / region: {$loc}\nList up to 12 real employers there to apply to in this field. JSON array; each item exactly: {\"company\":\"\",\"location\":\"city or region\",\"field\":\"what they do\",\"website\":\"real domain or empty string\",\"why\":\"one short reason it fits\"}. Write 'field' and 'why' in {$ln}.";
    $r = callClaude($sys, $usr, 1500);
    $jobs = [];
    if ($r) {
        $r = trim(preg_replace('/^```[a-z]*|```$/m', '', $r));
        $arr = json_decode($r, true);
        if (is_array($arr)) {
            foreach ($arr as $it) {
                if (!is_array($it)) continue;
                $web = trim((string)($it['website'] ?? ''));
                if ($web !== '' && !preg_match('#^https?://#i', $web)) $web = 'https://'.$web;
                $company = trim((string)($it['company'] ?? ''));
                if ($company === '') continue;
                $jobs[] = [
                    'title'    => $company,
                    'company'  => $company,
                    'location' => trim((string)($it['location'] ?? $loc)),
                    'snippet'  => mb_substr(trim((string)($it['field'] ?? '').(($it['why'] ?? '') ? ' — '.$it['why'] : '')), 0, 260),
                    'salary'   => '',
                    'link'     => $web,
                    'source'   => 'ai',
                ];
                if (count($jobs) >= 12) break;
            }
        }
    }
    respond(['jobs' => $jobs, 'total' => count($jobs), 'source' => 'ai']);
}
function doCoverLetter(array $b): void {
    $job     = is_array($b['job'] ?? null) ? $b['job'] : [];
    $profile = is_array($b['profile'] ?? null) ? $b['profile'] : [];
    $lang    = strtolower((string)($b['lang'] ?? 'en'));
    $cvText  = trim((string)($b['cv'] ?? ''));
    $title   = (string)($job['title'] ?? '');
    $company = (string)($job['company'] ?? '');
    $loc     = (string)($job['location'] ?? '');
    $snip    = (string)($job['snippet'] ?? '');
    if ($title === '' && $company === '') { respond(['error' => 'no_job']); return; }
    $LNAMES = ['de'=>'German','en'=>'English','tr'=>'Turkish','fr'=>'French','es'=>'Spanish','it'=>'Italian','nl'=>'Dutch','pl'=>'Polish','sv'=>'Swedish','pt'=>'Portuguese','ru'=>'Russian','ar'=>'Arabic'];
    $ln = $LNAMES[$lang] ?? 'English';
    $applicant = trim(((string)($profile['f1'] ?? '')).' '.((string)($profile['f2'] ?? '')));
    $addr = trim(((string)($profile['f3'] ?? '')).' '.((string)($profile['f4'] ?? '')));
    $sys = "You are a professional career coach who writes concise, tailored job-application cover letters. Write ONLY the letter, entirely in {$ln}. One page, specific to this role, result-oriented, no cliches or filler. Proper business-letter layout: applicant block, date, company, salutation, three short paragraphs, closing, signature. Use only the natural alphabet of {$ln}; no emoji or exotic characters. Never invent qualifications the applicant did not state.";
    $usr = "Write a cover letter for this position.\n\nROLE/TITLE: {$title}\nCOMPANY: {$company}\nLOCATION: {$loc}\nCONTEXT: {$snip}\n\nAPPLICANT: {$applicant}\nEMAIL: ".((string)($profile['f7'] ?? ''))."\nPHONE: ".((string)($profile['f6'] ?? ''))."\nADDRESS: {$addr}\n\nAPPLICANT CV / BACKGROUND (use ONLY these real facts, do not invent):\n".($cvText !== '' ? $cvText : '(No CV text given. Write a strong general letter based on the role, and where a personal detail is required leave a short blank ____ for the applicant to fill.)');
    $letter = callClaude($sys, $usr, 1200);
    if (!$letter) { respond(['error' => 'gen_failed']); return; }
    respond(['letter' => $letter]);
}

PHP;
$src=str_replace($a2, $fns.$a2, $src);

if($src===$start) exit("degisiklik yok!\n");
$tmp=$file.".tmp-jf"; file_put_contents($tmp,$src);
$out=[]; $code=0; exec("php -l ".escapeshellarg($tmp)." 2>&1",$out,$code); @unlink($tmp);
if($code!==0){ echo "✗ LINT HATASI — api.php DEGISTIRILMEDI:\n".implode("\n",$out)."\n"; exit; }
file_put_contents($file.".bak-jobfind-".date("Ymd-His"),$start);
file_put_contents($file,$src);

$dir=__DIR__."/jbdata"; if(!is_dir($dir)) @mkdir($dir,0700,true);
$ht=$dir."/.htaccess"; if(!is_file($ht)) @file_put_contents($ht,"Require all denied\nDeny from all\n");

echo "✓ api.php yamalandi (CH_JOBFIND): jobsearch + coverletter action'lari.\n";
echo "  ANAHTARSIZ calisir: AI, gercek firmalari onerir (uydurma yok, 'dogrula' etiketli).\n";
echo "  Ileride set-jooble-key.php ile Jooble anahtari eklenirse otomatik GERCEK ILANLARA gecer.\n";
echo "SIL: rm apply-jobfind-backend.php\n";
