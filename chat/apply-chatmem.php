<?php
/**
 * ChatHelp — apply-chatmem (CH_CHATMEM) — sunucu tarafi kanonik konusma hafizasi
 *  SORUN: foto analizi ve uretilen dilekce konusma gecmisine girmiyor → PDF/foto
 *  sonrasi AI baglami unutuyor; farkli sohbet modulleri birbirini gormuyor.
 *  COZUM: $sid'e bagli data/mem_<sid>.json'da "ozel olaylar" (foto ozeti +
 *  uretilen dilekce) saklanir; doAiChat her turda bunu transkriptin basina
 *  enjekte eder → DeepSeek de Claude de tam baglami gorur. Sadece EKLEME.
 *  api.php'de:
 *   [1] router: analyze_photo + aichat'a $sid gecir
 *   [2] doPhoto/doAiChat imzalarina $sid ekle
 *   [3] doPhoto: analiz sonucunu hafizaya yaz
 *   [4] doGenerate: uretilen dilekceyi hafizaya yaz
 *   [5] doAiChat: hafizayi transkripte enjekte et + baglam limitini artir
 *   [6] ch_mem_* yardimci fonksiyonlari
 * KULLANIM: pull2.php?key=...&files=apply-chatmem.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-chatmem BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/api.php';
$src = @file_get_contents($file);
if ($src===false) exit("api.php okunamadi\n");
if (strpos($src,'CH_CHATMEM')!==false) exit("Zaten ekli (CH_CHATMEM).\n");

$steps = [];
function rep(&$src, $old, $new, $label, &$steps){
    $c = substr_count($src, $old);
    if ($c!==1) { $steps[] = "✗ $label — anchor $c kez (1 bekleniyordu)"; return false; }
    $src = str_replace($old, $new, $src);
    $steps[] = "✓ $label";
    return true;
}

$ok = true;

/* [1] router: $sid gecir */
$ok = rep($src, '=> doPhoto($body),', '=> doPhoto($body, $sid),', '[1a] router analyze_photo -> $sid', $steps) && $ok;
$ok = rep($src, '=> doAiChat($body),', '=> doAiChat($body, $sid),', '[1b] router aichat -> $sid', $steps) && $ok;

/* [2] imzalar */
$ok = rep($src, 'function doPhoto(array $b): void {', 'function doPhoto(array $b, string $sid=\'\'): void {', '[2a] doPhoto imza', $steps) && $ok;
$ok = rep($src, 'function doAiChat(array $b): void {', 'function doAiChat(array $b, string $sid=\'\'): void {', '[2b] doAiChat imza', $steps) && $ok;

/* [3] doPhoto: analizi hafizaya yaz */
$photoOld = "respond(['success'=>true,'result'=>\$result??[]]);";
$photoNew = "if (\$sid!=='' && function_exists('ch_mem_append') && is_array(\$result)) {\n"
          . "        \$__ev='Der Nutzer hat ein Dokument-Foto gesendet.';\n"
          . "        foreach(['erkannt'=>'Erkannt','behoerde'=>'Behörde/Absender','datum'=>'Datum','az'=>'Aktenzeichen','frist'=>'Frist','betrag'=>'Betrag'] as \$__k=>\$__lbl){ if(!empty(\$result[\$__k])) \$__ev.=' '.\$__lbl.': '.\$result[\$__k].'.'; }\n"
          . "        if(!empty(\$result['zusammenfassung'])) \$__ev.=' Zusammenfassung: '.\$result['zusammenfassung'];\n"
          . "        ch_mem_append(\$sid,'photo',\$__ev);\n"
          . "    }\n"
          . "    respond(['success'=>true,'result'=>\$result??[]]);";
$ok = rep($src, $photoOld, $photoNew, '[3] doPhoto -> hafiza', $steps) && $ok;

/* [4] doGenerate: uretilen dilekceyi hafizaya yaz */
$genOld = "respond(['success'=>true,'docId'=>\$docId,'document'=>\$final,'research'=>\$research]);";
$genNew = "if (\$sid!=='' && function_exists('ch_mem_append')) ch_mem_append(\$sid,'doc','Es wurde ein Dokument erstellt: '.\$docName.'. Auszug: '.mb_substr(strip_tags((string)\$final),0,600));\n"
        . "    respond(['success'=>true,'docId'=>\$docId,'document'=>\$final,'research'=>\$research]);";
$ok = rep($src, $genOld, $genNew, '[4] doGenerate -> hafiza', $steps) && $ok;

/* [5] doAiChat: hafizayi transkripte enjekte + limit artir */
$trOld = "\$transcript .= \"User: {\$safeMsg}\\nAssistant:\";";
$trNew = $trOld."\n"
       . "    if (\$sid!=='' && function_exists('ch_mem_read')) {\n"
       . "        \$__mem = ch_mem_read(\$sid); \$__ctx='';\n"
       . "        foreach(\$__mem as \$__m){ \$__t=(string)(\$__m['text']??''); if(\$repl) \$__t=strtr(\$__t,\$repl); if(\$__t!=='') \$__ctx.=\"Kontext (früher in diesem Gespräch): \".\$__t.\"\\n\"; }\n"
       . "        if(\$__ctx!=='') \$transcript = \$__ctx.\"\\n\".\$transcript;\n"
       . "    }";
$ok = rep($src, $trOld, $trNew, '[5a] doAiChat hafiza enjekte', $steps) && $ok;
$ok = rep($src, 'if ($n++ > 20) break; // baglami makul tut', 'if ($n++ > 30) break; // baglami makul tut', '[5b] baglam 20->30', $steps) && $ok;
$ok = rep($src, 'if (mb_strlen($content) > 800) $content = mb_substr($content, 0, 800);', 'if (mb_strlen($content) > 1500) $content = mb_substr($content, 0, 1500);', '[5c] mesaj 800->1500', $steps) && $ok;

foreach($steps as $s) echo "  $s\n";
if (!$ok) exit("\nHATA: en az bir adim eslesmedi — api.php DEGISTIRILMEDI.\n");

/* [6] yardimci fonksiyonlar — dosya sonuna */
$fn = <<<'PHPFN'

/* CH_CHATMEM — sunucu tarafi kanonik konusma hafizasi (foto/dilekce olaylari) */
function ch_mem_file(string $sid): string {
    $sid = preg_replace('/[^a-f0-9]/','',(string)$sid);
    return STORE_DIR.'mem_'.($sid!==''?$sid:'anon').'.json';
}
function ch_mem_append(string $sid, string $kind, string $text): void {
    $sid = preg_replace('/[^a-f0-9]/','',(string)$sid);
    if ($sid==='' || trim((string)$text)==='') return;
    $f = ch_mem_file($sid); $arr=[];
    if (is_file($f)) { $arr = json_decode((string)@file_get_contents($f), true); if(!is_array($arr)) $arr=[]; }
    $arr[] = ['kind'=>$kind,'text'=>mb_substr(trim((string)$text),0,900),'ts'=>time()];
    if (count($arr)>12) $arr=array_slice($arr,-12);
    @file_put_contents($f, json_encode($arr, JSON_UNESCAPED_UNICODE), LOCK_EX);
}
function ch_mem_read(string $sid): array {
    $f = ch_mem_file($sid);
    if (!is_file($f)) return [];
    if (time()-@filemtime($f) > TTL) { @unlink($f); return []; }
    $arr = json_decode((string)@file_get_contents($f), true);
    return is_array($arr)?$arr:[];
}
PHPFN;

$closeTag = strrpos($src, '?>');
if ($closeTag !== false && trim(substr($src,$closeTag+2))==='') {
    $src = substr($src,0,$closeTag).$fn."\n?>".substr($src,$closeTag+2);
} else {
    $src = rtrim($src)."\n".$fn."\n";
}
echo "  ✓ [6] ch_mem_* fonksiyonlari eklendi\n";

/* lint + yaz */
$tmp = tempnam(sys_get_temp_dir(),'cm').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — api.php DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
$bk=@file_put_contents($file.'.bak-chatmem-'.date('Ymd-His'), @file_get_contents($file));
if ($bk===false) echo "  ⚠ yedek yazilamadi — devam\n";
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_CHATMEM')===false || strpos($chk,'function ch_mem_append')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_CHATMEM + ch_mem_append() diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Sunucu hafizasi aktif. Foto analizi + uretilen dilekce artik konusma\n";
echo "   baglamina yaziliyor; her aichat turunda (DeepSeek/Claude) enjekte ediliyor.\n";
