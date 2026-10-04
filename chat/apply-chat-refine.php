<?php
/**
 * ChatHelp — apply-chat-refine (CH_CHAT_REFINE)
 *  Uc rotus:
 *   [A] Ana sayfa Verträge vitrini artik "Staatliche Formulare" bolumunun
 *       ÜSTÜNE yerlesir (en ustte normal soru-cevap sablonlari kalir).
 *   [B] AI davranisi: kullanici bir sey direkt sorunca, uygun bir STANDART
 *       sablon varsa cok soru sormak yerine o sablona yonlendirir; sablon
 *       yoksa/yetmezse (yada foto/sablon bilgisine sadik kalarak) derin
 *       soru-cevaba gecip [[DOC]] uretir.
 *   [C] Saglayici oranlari: Basic sadece DeepSeek · Pro 5 DeepSeek : 1
 *       Claude · Elite 4 DeepSeek sonra 1 Claude saglamasi · Free DeepSeek.
 *  api.php (prompt) + index.php (vitrin konumu + pickProvider) guncellenir.
 * KULLANIM: pull-updates.php?files=apply-chat-refine.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-chat-refine BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$idxFile = __DIR__ . '/index.php';
$apiFile = __DIR__ . '/api.php';
$idx = @file_get_contents($idxFile);
$api = @file_get_contents($apiFile);
if ($idx === false) exit("index.php okunamadi\n");
if ($api === false) exit("api.php okunamadi\n");
if (strpos($idx, 'CH_CHAT_REFINE') !== false) exit("Zaten ekli (CH_CHAT_REFINE).\n");
file_put_contents($idxFile . '.bak-chatrefine-' . date('Ymd-His'), $idx);
file_put_contents($apiFile . '.bak-chatrefineapi-' . date('Ymd-His'), $api);

$okIdx = 0; $okApi = 0;

/* ── [A] vitrin konumu: Staatliche basliginin ustune ── */
$oldHost = 'var host=document.querySelector(".wlc-quick")||document.querySelector(".wlc-quick-label"); if(!host||!host.parentNode) return;';
$newHost = 'var host=null; try{ var hh=document.querySelectorAll(".sec-hdr"); for(var i=0;i<hh.length;i++){ if((hh[i].textContent||"").indexOf("Staatliche")>-1){ host=hh[i]; break; } } }catch(e){} if(!host) host=document.querySelector(".wlc-quick")||document.querySelector(".wlc-quick-label"); if(!host||!host.parentNode) return;';
$c=0; $idx=str_replace($oldHost,$newHost,$idx,$c); if($c){ $okIdx++; echo "  ✓ [A] vitrin Staatliche ustune tasindi ($c)\n"; } else echo "  ✗ [A] vitrin anchor yok\n";

/* ── [C] pickProvider oranlari ── */
$oldPick = "  function pickProvider(turnIdx){\n"
  . "    var pl=plan();\n"
  . "    if(pl===\"elite\") return (turnIdx%3===2)?\"claude\":\"deepseek\";\n"
  . "    if(pl===\"pro\")   return (turnIdx%6===5)?\"claude\":\"deepseek\";\n"
  . "    if(pl===\"basic\") return (turnIdx%6===5)?\"claude\":\"deepseek\";\n"
  . "    return (turnIdx%6===5)?\"claude\":\"deepseek\"; /* free: ~5-6 mesajda bir Claude kontrolu */\n"
  . "  }";
$newPick = "  function pickProvider(turnIdx){\n"
  . "    var pl=plan();\n"
  . "    if(pl===\"elite\") return (turnIdx%5===4)?\"claude\":\"deepseek\"; /* 4 deepseek + 1 claude saglamasi */\n"
  . "    if(pl===\"pro\")   return (turnIdx%6===5)?\"claude\":\"deepseek\"; /* 5 deepseek : 1 claude */\n"
  . "    if(pl===\"basic\") return \"deepseek\"; /* sadece deepseek */\n"
  . "    return \"deepseek\"; /* free: sadece deepseek */\n"
  . "  }";
$c=0; $idx=str_replace($oldPick,$newPick,$idx,$c); if($c){ $okIdx++; echo "  ✓ [C] saglayici oranlari guncellendi ($c)\n"; } else echo "  ✗ [C] pickProvider anchor yok\n";

if ($okIdx < 2) { echo "\nHATA: index.php'de beklenen 2 degisiklikten $okIdx uygulandi — index.php DEGISTIRILMEDI.\n"; exit; }

/* ── [B] prompt: once sablon yonlendir, yoksa derin intake ── */
$oldClause = 'INTAKE FIRST: when the user describes a problem, do NOT jump to a template. Understand it through a few focused, friendly questions — who the letter must be addressed to (authority/company/other party), the concrete facts, the key dates, and the outcome they want. Ask only what is still missing, one or two questions at a time, never a long list. ';
$newClause = 'TEMPLATE FIRST: if the user\'s request clearly matches a standard document (a common letter, application, objection, cancellation, contract or CV), do NOT interrogate them — briefly note that a ready structured template exists (which is faster and more reliable for them) and end your reply on its own line with the matching tag: [[SUGGEST:doc]] for a letter/petition, [[SUGGEST:vst]] for a contract, [[SUGGEST:cv]] for a CV, [[SUGGEST:jobs]] for job search. ONLY when no standard template fits, the matter is custom or complex, the template alone is not enough, or the user wants to keep discussing, switch to INTAKE mode: staying faithful to any details already given (including from a template or an uploaded photo), understand it through a few focused, friendly questions — the recipient, the concrete facts, the key dates and the desired outcome — asking only what is still missing, one or two at a time, never a long list. When the user wants a PDF, prefer guiding them to the template; you may still discuss the matter in depth. ';
$c=0; $api=str_replace($oldClause,$newClause,$api,$c); if($c){ $okApi++; echo "  ✓ [B] prompt: sablon-once + intake-yedek ($c)\n"; } else echo "  ✗ [B] INTAKE FIRST anchor yok\n";

$idx = str_replace('<script id="ch-vertrag-home">', "<script id=\"ch-vertrag-home\">\n/* CH_CHAT_REFINE */", $idx, $mk);

/* lint + yaz */
foreach ([[$idxFile,$idx],[$apiFile,$api]] as $pair) {
    $tmp = tempnam(sys_get_temp_dir(), 'chr') . '.php';
    file_put_contents($tmp, $pair[1]);
    $lo=[]; $rc=0; exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc); @unlink($tmp);
    if ($rc !== 0) { echo "\nLINT HATASI (".basename($pair[0]).") — HICBIR dosya degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
}
file_put_contents($idxFile, $idx);
file_put_contents($apiFile, $api);
echo "\n✓ CH_CHAT_REFINE uygulandi (index:$okIdx, api:$okApi).\n";
echo "Vitrin Staatliche ustunde; AI once sablona yonlendirir, gerekirse derin Q&A; Basic=DeepSeek, Pro=5:1, Elite=4:1 Claude.\n";
