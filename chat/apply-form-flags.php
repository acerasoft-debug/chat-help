<?php
/**
 * ChatHelp — apply-form-flags (CH_FORM_FLAGS) — uc bayrak / uc akis (DE):
 *  [1] schriftform_streng GENISLETME: needsWet listesine §550 (befristeter
 *      Mietvertrag) + §492 (Darlehensvertrag/Verbraucherdarlehen) eklenir.
 *      Bu belgelerde ekran imzasi/dogrudan gonderim yok — "indir + islak
 *      imzala" uyarisi (mevcut davranis).
 *  [2] frist_kritisch: kesin onay ekranina '📬 Einwurf-Einschreiben (+3,50 €)'
 *      secenegi. Widerruf/Widerspruch/Kündigung/Einspruch/Frist/Mahnung
 *      metinlerinde OTOMATIK isaretli + oneri notu. Secim send_letter'a
 *      registered:1 olarak gider (LetterXpress einwurf).
 *  [3] diger -> normal posta (degisiklik yok).
 *  3 count-guard'li str_replace (needsWet 3x, confirm-bar 1x, body 1x).
 * KULLANIM: pull2.php?key=...&files=apply-form-flags.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-form-flags BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_ADRES_IMZA4')===false) exit("HATA: once CH_ADRES_IMZA4 gerekli.\n");
if (strpos($src,'id="ai-reg"')!==false) exit("Zaten ekli (CH_FORM_FLAGS).\n");

/* ── [1] needsWet genisletme (IMZA2/3/4'te ayni satir 3x — hepsi genisler) ── */
$o1 = "  function needsWet(html){ var h=(html||'').toLowerCase(); return /k(ü|ue)ndigung[^<]{0,60}(arbeit|anstellung)|arbeitsvertrag|aufhebungsvertrag|b(ü|ue)rgschaft|testament|erbausschlagung/.test(h); }";
$n1 = "  function needsWet(html){ var h=(html||'').toLowerCase(); return /k(ü|ue)ndigung[^<]{0,60}(arbeit|anstellung)|arbeitsvertrag|aufhebungsvertrag|b(ü|ue)rgschaft|testament|erbausschlagung|befristeter mietvertrag|mietvertrag[^<]{0,40}befristet|darlehensvertrag|verbraucherdarlehen/.test(h); }";
$c1=substr_count($src,$o1);
if($c1<1||$c1>3) exit("HATA: [1] needsWet anchor $c1 kez (1-3 beklenir) — index DEGISTIRILMEDI.\n");
$src=str_replace($o1,$n1,$src);
echo "  ✓ [1] schriftform_streng: §550 + §492 eklendi ($c1 katman)\n";

/* ── [2a] T: reg/reghint anahtarlari (IMZA4 'back' satirinin ardina) ── */
$o2 = "      back:{de:'← Zurück',tr:'← Geri',en:'← Back'},";
$n2 = "      back:{de:'← Zurück',tr:'← Geri',en:'← Back'},
      reg:{de:'📬 Einwurf-Einschreiben (+3,50 €) — mit Zustellnachweis',tr:'📬 Taahhütlü gönderim (+3,50 €) — teslim kanıtlı',en:'📬 Registered mail (+3,50 €) — proof of delivery'},
      reghint:{de:'Fristkritisches Dokument — Einschreiben empfohlen.',tr:'Süreye bağlı belge — taahhütlü önerilir.',en:'Deadline-critical — registered recommended.'},";
$c2=substr_count($src,$o2);
if($c2!==1) exit("HATA: [2a] T-back anchor $c2 kez — index DEGISTIRILMEDI.\n");
$src=str_replace($o2,$n2,$src);
echo "  ✓ [2a] Einschreiben ceviri anahtarlari eklendi\n";

/* ── [2b] onay barina Einschreiben kutusu ── */
$o3 = "    bar.innerHTML='<div class=\"ai-warn\" style=\"width:100%;margin-bottom:6px\">'+T('confirmq').replace('{r}',esc(rec.split('\\n')[0]))+'</div><button id=\"ai-do\">'+T('confirmyes')+' — '+priceStr()+'</button><button id=\"ai-back\">'+T('back')+'</button>';";
$n3 = "    var frk=/widerruf|widerspruch|k(ü|ue)ndigung|einspruch|frist|mahnung/i.test(ORIG_BODY);
    bar.innerHTML='<div class=\"ai-warn\" style=\"width:100%;margin-bottom:6px\">'+T('confirmq').replace('{r}',esc(rec.split('\\n')[0]))+'</div>'
      +'<label style=\"display:flex;align-items:center;gap:7px;width:100%;margin:2px 0 8px;font-size:11.5px;color:#cfe0ff;cursor:pointer\"><input type=\"checkbox\" id=\"ai-reg\"'+(frk?' checked':'')+'> <span>'+T('reg')+(frk?'<br><span style=\"color:#ffcf7a\">'+T('reghint')+'</span>':'')+'</span></label>'
      +'<button id=\"ai-do\">'+T('confirmyes')+' — '+priceStr()+'</button><button id=\"ai-back\">'+T('back')+'</button>';";
$c3=substr_count($src,$o3);
if($c3!==1) exit("HATA: [2b] confirm-bar anchor $c3 kez — index DEGISTIRILMEDI.\n");
$src=str_replace($o3,$n3,$src);
echo "  ✓ [2b] Onay ekranina Einschreiben kutusu eklendi (fristli belgede otomatik)\n";

/* ── [2c] send govdesine registered ── */
$o4 = "    var body={text:txt,recipient:rec,sender:sender,sig_jpeg:sigJpeg()};";
$n4 = "    var body={text:txt,recipient:rec,sender:sender,sig_jpeg:sigJpeg(),registered:((document.getElementById('ai-reg')||{}).checked?1:0)};";
$c4=substr_count($src,$o4);
if($c4!==1) exit("HATA: [2c] send-body anchor $c4 kez — index DEGISTIRILMEDI.\n");
$src=str_replace($o4,$n4,$src);
echo "  ✓ [2c] registered:1 send_letter'a aktarilir\n";

$src .= "\n<!-- CH_FORM_FLAGS -->";

$tmp = tempnam(sys_get_temp_dir(),'ff').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-formflags-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'id="ai-reg"')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "\n  ✓ DOGRULAMA: CH_FORM_FLAGS diskte (".strlen($chk)." bayt)\n";
echo "\n✓ UC BAYRAK / UC AKIS (DE):\n";
echo "   1) schriftform_streng (\u{00a7}623/\u{00a7}766/\u{00a7}550/\u{00a7}492) -> indir + islak imza uyarisi\n";
echo "   2) frist_kritisch -> onayda '📬 Einwurf-Einschreiben (+3,50 €)' (otomatik onerili)\n";
echo "   3) diger -> normal posta\n";
echo "\nNOT: postversand.php'nin de guncellenmesi gerekli:\n";
echo "   pull2.php?key=...&files=apply-postversand.php,dump-lx-reg.php\n";
