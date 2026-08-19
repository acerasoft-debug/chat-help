<?php
/**
 * ChatHelp — apply-chat-save (CH_CHAT_SAVE)
 *  Chat'te uretilen [[DOC]] dilekceleri ARTIK Meine Antraege'ye kaydedilir:
 *   [1] api.php: yeni 'savedoc' action (doSaveDoc) — doGenerate ile ayni
 *       depolama bicimi (STORE_DIR/sid_id.json, 24h TTL, Behalten uyumlu).
 *       Kaydedilen metin PLACEHOLDER'li surumdur (gercek ad/adres sunucuya
 *       yazilmaz — mevcut gizlilik modeliyle uyumlu).
 *   [2] index.php: [[DOC]] karti olusunca belge otomatik kaydedilir (tekrar
 *       kaydetme korumali); Betreff satirindan isim cikarilir.
 *  + TESHIS (sadece okur): fall-api solve kuyrugu — Fall belgeleri icin
 *    ayni kaydi bir sonraki adimda eklemek uzere.
 * KULLANIM: pull-updates.php?files=apply-chat-save.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-chat-save BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$apiF = __DIR__.'/api.php';
$idxF = __DIR__.'/index.php';
$api = @file_get_contents($apiF);
$idx = @file_get_contents($idxF);
if ($api===false) exit("api.php okunamadi\n");
if ($idx===false) exit("index.php okunamadi\n");
if (strpos($api,'CH_CHAT_SAVE')!==false || strpos($idx,'CH_CHAT_SAVE')!==false) exit("Zaten ekli (CH_CHAT_SAVE).\n");

/* ── TESHIS: fall-api solve kuyrugu ── */
$fall = @file_get_contents(__DIR__.'/fall-api.php');
if ($fall!==false) {
    $p = strpos($fall,'fall_solve');
    if ($p===false) $p = strrpos($fall,'json_encode');
    echo "###### TESHIS: fall-api kuyruk (son json_encode civari) ######\n";
    $q = strrpos($fall,'echo json_encode');
    if ($q!==false) echo substr($fall,max(0,$q-500),900)."\n---\n\n";
}

$fail=[];

/* ── [1a] dispatch: savedoc ── */
$o1 = "    'keepdoc'         => doKeep(\$body, \$sid),";
$n1 = "    'keepdoc'         => doKeep(\$body, \$sid),\n    'savedoc'         => doSaveDoc(\$body, \$sid), /*CH_CHAT_SAVE*/";
$c=0; $api=str_replace($o1,$n1,$api,$c);
echo ($c===1?"  ✓ [1a] dispatch savedoc\n":"  ✗ [1a] dispatch anchor ($c)\n"); if($c!==1)$fail[]='1a';

/* ── [1b] doSaveDoc fonksiyonu (doKeep'in onune) ── */
$o2 = "function doKeep(array \$b, string \$sid): void {";
$n2 = "function doSaveDoc(array \$b, string \$sid): void { /*CH_CHAT_SAVE*/\n"
    . "    \$doc = trim((string)(\$b['document'] ?? ''));\n"
    . "    if (\$doc === '' || mb_strlen(\$doc) > 60000) { respond(['error'=>'invalid']); return; }\n"
    . "    \$docId  = bin2hex(random_bytes(8));\n"
    . "    \$stored = ['id'=>\$docId,'sid'=>\$sid,\n"
    . "               'docType'=>preg_replace('/[^a-z0-9_]/','', strtolower((string)(\$b['docType'] ?? 'chat_doc'))) ?: 'chat_doc',\n"
    . "               'docName'=>mb_substr(htmlspecialchars(trim((string)(\$b['docName'] ?? 'KI-Dokument'))),0,80) ?: 'KI-Dokument',\n"
    . "               'document'=>\$doc,'research'=>'',\n"
    . "               'country'=>strtoupper(preg_replace('/[^A-Za-z]/','',(string)(\$b['country'] ?? 'DE'))) ?: 'DE',\n"
    . "               'created'=>time(),'keep'=>false,\n"
    . "               'preview'=>mb_substr(strip_tags(\$doc),0,200)];\n"
    . "    file_put_contents(STORE_DIR.\$sid.'_'.\$docId.'.json', json_encode(\$stored, JSON_UNESCAPED_UNICODE));\n"
    . "    respond(['success'=>true,'docId'=>\$docId]);\n"
    . "}\n\n"
    . "function doKeep(array \$b, string \$sid): void {";
$c=0; $api=str_replace($o2,$n2,$api,$c);
echo ($c===1?"  ✓ [1b] doSaveDoc eklendi\n":"  ✗ [1b] doKeep anchor ($c)\n"); if($c!==1)$fail[]='1b';

/* ── [2] [[DOC]] karti: otomatik kaydet ── */
$o3 = "      mi.appendChild(d);\n"
    . "      var b=d.querySelector(\".ch-doccard-btn\");";
$n3 = "      mi.appendChild(d);\n"
    . "      /*CH_CHAT_SAVE — chat dilekcesini Meine Antraege'ye kaydet (placeholder'li, tekrarsiz)*/\n"
    . "      try{\n"
    . "        window._chDocSaved=window._chDocSaved||{};\n"
    . "        var _kk=docText.length+\"_\"+docText.slice(0,40);\n"
    . "        if(!window._chDocSaved[_kk]){\n"
    . "          window._chDocSaved[_kk]=1;\n"
    . "          var _nm=\"KI-Dokument\"; try{ var _mB=docText.match(/Betreff:?\\s*(.+)/); if(_mB) _nm=_mB[1].trim().slice(0,70); }catch(e){}\n"
    . "          fetch((typeof API!==\"undefined\"?API:\"api.php\")+\"?action=savedoc\",{method:\"POST\",headers:{\"Content-Type\":\"application/json\"},body:JSON.stringify({document:docText,docName:_nm,docType:\"chat_doc\",country:(typeof CC!==\"undefined\"?CC:\"DE\")})}).then(function(){ try{ if(typeof loadRecentDocs===\"function\") loadRecentDocs(); }catch(e){} }).catch(function(){});\n"
    . "        }\n"
    . "      }catch(e){}\n"
    . "      var b=d.querySelector(\".ch-doccard-btn\");";
$c=0; $idx=str_replace($o3,$n3,$idx,$c);
echo ($c===1?"  ✓ [2] [[DOC]] karti otomatik kayit\n":"  ✗ [2] card anchor ($c)\n"); if($c!==1)$fail[]=2;

if ($fail) { echo "\nHATA: adimlar eslesmedi: ".implode(', ',$fail)." — HICBIR dosya degistirilmedi.\n"; exit; }

foreach ([[$apiF,$api],[$idxF,$idx]] as $pair) {
    $tmp = tempnam(sys_get_temp_dir(),'cs').'.php';
    file_put_contents($tmp,$pair[1]);
    $lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
    if ($rc!==0) { echo "\nLINT HATASI (".basename($pair[0]).") — HICBIR dosya degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
}
file_put_contents($apiF.'.bak-chatsave-'.date('Ymd-His'), @file_get_contents($apiF));
file_put_contents($idxF.'.bak-chatsave-'.date('Ymd-His'), @file_get_contents($idxF));
file_put_contents($apiF,$api);
file_put_contents($idxF,$idx);
echo "\n✓ CH_CHAT_SAVE uygulandi. Chat'te uretilen dilekceler artik Meine Antraege'de gorunur (Behalten ile kalici yapilabilir).\n";
