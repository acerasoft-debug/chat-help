<?php
/**
 * ChatHelp — apply-chatdoc-fix (CH_CHATDOC_FIX) — canli test gorusmesi kanitli
 *  sohbet-ici [[DOC]] uretimi duzeltmesi (5 madde, index.php + api.php):
 *  [1] PDF calismiyordu: printDoc() 'window.chDlGate()' (basarisiz -> cirkin
 *      alert() + Konto'ya atma, belgeyi kaybettirir) VE gereksiz 'window.jspdf'
 *      sartina (makePDF buna hic ihtiyac duymuyor) bagliydi. Ikisi de kaldirildi.
 *  [2] Odeme kapisi artik Doc-Dev v3 ile AYNI, kanitlanmis model: paketli
 *      kullanici -> dogrudan ucretsiz PDF. Paketsiz -> belge BULANIK/secilemez,
 *      "🔓 Freischalten & PDF · X,XX €" -> stripeCheckout(null,"devdoc",tier).
 *  [3] Belge dili artik HUKUK DILINE gore (konusma dili degil) — sadece konu
 *      baska bir ulkeyle (ornegin vize/seyahat) ilgiliyse AI o dilde EK bir
 *      surum isteyip istemedigini SORAR, sormadan uretmez.
 *  [4] Isim sorma onlenir: belgede kullanicinin KENDI adi/adresi gerekiyorsa
 *      placeholder'lar sessizce kullanilir — "adiniz nedir" diye SORULMAZ;
 *      sadece ucuncu sahislarin (cocuk, diger ebeveyn, karsi taraf) adi sorulur.
 *  [5] Halusinasyon onlenir: yeniden-yazimda ONCEDEN verilen isim/tarih/adres
 *      asla degistirilmez/uydurulmaz — eksikse tekrar sorulur.
 *  EK: CH_LANG_FORCE2'nin "yanlislikla Almanca -> zorla yeniden yaz" denetimi,
 *      [[DOC]] iceren yanitlarda ARTIK TETIKLENMEZ (hukuk-dilindeki belgeyi
 *      "hata" sanip Turkce'ye zorla cevirmesini onler).
 * KULLANIM: pull2.php?key=...&files=apply-chatdoc-fix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-chatdoc-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$idxFile = __DIR__.'/index.php';
$apiFile = __DIR__.'/api.php';
$idx = @file_get_contents($idxFile);
$api = @file_get_contents($apiFile);
if ($idx===false) exit("index.php okunamadi\n");
if ($api===false) exit("api.php okunamadi\n");
if (strpos($idx,'CH_CHATDOC_FIX')!==false) exit("Zaten ekli (CH_CHATDOC_FIX).\n");

$fail=[];

/* ══════════════════ index.php duzenlemeleri ══════════════════ */

/* ── [1]+[2] card(): plan kontrolu + bulanik kilit + odeme butonu ── */
$o1 = <<<'EOT'
      var b=d.querySelector(".ch-doccard-btn");
      if(b) b.addEventListener("click",function(){ printDoc(docText); });
      try{ mi.scrollTop=mi.scrollHeight; }catch(e){}
    }catch(e){}
  }
  window.__chDocPdf={ extract:extract, restore:restore, card:card, print:printDoc };
EOT;
$n1 = <<<'EOT'
      var b=d.querySelector(".ch-doccard-btn");
      if(b) b.addEventListener("click",function(){ /*CH_CHATDOC_FIX*/
        if(_hasPlan){ printDoc(docText); }
        else { try{ window.stripeCheckout(null,"devdoc",_tier[0]); }catch(e){} }
      });
      try{ mi.scrollTop=mi.scrollHeight; }catch(e){}
    }catch(e){}
  }
  window.__chDocPdf={ extract:extract, restore:restore, card:card, print:printDoc };
EOT;
$c=substr_count($idx,$o1);
if($c!==1){ echo "  ✗ [1] buton-wiring anchor ($c)\n"; $fail[]=1; }
else { $idx=str_replace($o1,$n1,$idx,$c); echo "  ✓ [1] PDF butonu: paketli->dogrudan, paketsiz->tek-belge odeme\n"; }

$o2 = <<<'EOT'
    var mi=document.getElementById("mi"); if(!mi) return;
      var real=restore(docText);
      var d=document.createElement("div"); d.className="msg ai";
      d.innerHTML='<div class="av">CH</div><div class="mbody"><div class="bbl" style="background:transparent;padding:0">'
        +'<div class="ch-doccard"><div class="ch-doccard-h">📄 '+esc(tr(HDR))+'</div>'
        +'<div class="ch-doccard-body">'+esc(real)+'</div>'
        +'<button type="button" class="ch-doccard-btn">'+esc(tr(BTN))+'</button>'
        +'<div class="ch-doccard-note">'+esc(tr(NOTE))+'</div></div></div></div>';
      mi.appendChild(d);
EOT;
$n2 = <<<'EOT'
    var mi=document.getElementById("mi"); if(!mi) return;
      var real=restore(docText);
      var _tier=real.length<=2500?["einfach","1,99"]:(real.length<=6000?["standard","3,99"]:["komplex","4,99"]); /*CH_CHATDOC_FIX*/
      var _hasPlan=false;
      try{ if(typeof window.chPlan==="function"){ var _p1=window.chPlan(); if(_p1&&_p1!=="free") _hasPlan=true; } }catch(e){}
      try{ if(!_hasPlan && typeof plan==="string" && plan && plan!=="free") _hasPlan=true; }catch(e){}
      try{ if(!_hasPlan && typeof plan==="function"){ var _p2=plan(); if(_p2&&_p2!=="free") _hasPlan=true; } }catch(e){}
      try{ if(!_hasPlan && window.P && window.P.plan && window.P.plan!=="free") _hasPlan=true; }catch(e){}
      try{ if(!_hasPlan){ var _u=JSON.parse(localStorage.getItem("ch_user")||"{}"); if(_u.plan&&_u.plan!=="free") _hasPlan=true; } }catch(e){}
      var d=document.createElement("div"); d.className="msg ai";
      var _btnLbl = _hasPlan ? esc(tr(BTN)) : ("🔓 "+esc(tr(BTN))+" · "+_tier[1]+" €");
      d.innerHTML='<div class="av">CH</div><div class="mbody"><div class="bbl" style="background:transparent;padding:0">'
        +'<div class="ch-doccard"><div class="ch-doccard-h">📄 '+esc(tr(HDR))+'</div>'
        +'<div class="ch-doccard-body'+(_hasPlan?'':' ch-doccard-locked')+'">'+esc(real)+'</div>'
        +'<button type="button" class="ch-doccard-btn">'+_btnLbl+'</button>'
        +'<div class="ch-doccard-note">'+esc(tr(NOTE))+'</div></div></div></div>';
      mi.appendChild(d);
EOT;
$c=substr_count($idx,$o2);
if($c!==1){ echo "  ✗ [2] card-html anchor ($c)\n"; $fail[]=2; }
else { $idx=str_replace($o2,$n2,$idx,$c); echo "  ✓ [2] belge karti: paket yoksa bulanik onizleme + fiyatli buton\n"; }

/* ── [1b] printDoc(): chDlGate + jspdf sartlari kaldirilir ── */
$o3 = <<<'EOT'
  function printDoc(docText){ if(window.chDlGate&&!window.chDlGate()) return; /*CH_DLGATE*/ /*CH_AICHAT_PDF_POLISH*/
    var real=restore(docText);
    try{ if(window.jspdf&&typeof makePDF==="function"){ makePDF(real,"ChatHelp-Dokument",(typeof CC!=="undefined"?CC:"DE")); return; } }catch(e){} /*CH_FIX5 PDF*/
EOT;
$n3 = <<<'EOT'
  function printDoc(docText){ /*CH_CHATDOC_FIX — plan kontrolu artik card() seviyesinde; jsPDF sarti kaldirildi (makePDF gerektirmiyor)*/
    var real=restore(docText);
    try{ if(typeof makePDF==="function"){ makePDF(real,"ChatHelp-Dokument",(typeof CC!=="undefined"?CC:"DE")); return; } }catch(e){}
EOT;
$c=substr_count($idx,$o3);
if($c!==1){ echo "  ✗ [1b] printDoc anchor ($c)\n"; $fail[]='1b'; }
else { $idx=str_replace($o3,$n3,$idx,$c); echo "  ✓ [1b] printDoc: chDlGate/jspdf sarti kaldirildi, makePDF dogrudan cagriliyor\n"; }

/* ══════════════════ api.php duzenlemeleri ══════════════════ */

/* ── [3a] hukuk-dili adi haritasi eklenir ── */
$o4 = "    \$lawHint = \$__lawh[\$country] ?? \$__lawh['DE'];\n";
$n4 = <<<'EOT'
    $lawHint = $__lawh[$country] ?? $__lawh['DE'];
    $__lawlang = ['DE'=>'German','AT'=>'German','CH'=>'German','TR'=>'Turkish','FR'=>'French','BE'=>'French','ES'=>'Spanish','IT'=>'Italian','NL'=>'Dutch','PL'=>'Polish','SE'=>'Swedish','PT'=>'Portuguese','GB'=>'English','US'=>'English']; /*CH_CHATDOC_FIX*/
    $lawLangName = $__lawlang[$country] ?? 'German';
EOT;
$c=substr_count($api,$o4);
if($c!==1){ echo "  ✗ [3a] lawHint anchor ($c)\n"; $fail[]='3a'; }
else { $api=str_replace($o4,$n4,$api,$c); echo "  ✓ [3a] hukuk-dili adi haritasi eklendi\n"; }

/* ── [4] PRIVACY: kendi adini asla sorma ── */
$o5 = <<<'EOT'
         . "PRIVACY: never ask for the user's real name or address, and never echo the [VORNAME]/[NACHNAME]/[ADRESSE]/[ORT]/[TELEFON]/[EMAIL] placeholders back in chat — use them silently inside any document as if you already know the details. "
EOT;
$n5 = <<<'EOT'
         . "PRIVACY: never ask for the user's real name or address, and never echo the [VORNAME]/[NACHNAME]/[ADRESSE]/[ORT]/[TELEFON]/[EMAIL] placeholders back in chat — use them silently inside any document as if you already know the details. Whenever the document needs the CHATTING USER's own name/address as sender or signer, use the placeholders automatically — never ask 'what is your name' under any circumstance; only ask for names of genuinely OTHER people part of the matter (a child, another parent, a counterparty, a recipient). /*CH_CHATDOC_FIX*/ "
EOT;
$c=substr_count($api,$o5);
if($c!==1){ echo "  ✗ [4] PRIVACY anchor ($c)\n"; $fail[]=4; }
else { $api=str_replace($o5,$n5,$api,$c); echo "  ✓ [4] kendi adini/adresini sorma talimati guclendirildi\n"; }

/* ── [3b]+[5] GENERATE ONLY WHEN READY: hukuk dili + halusinasyon onleme ── */
$o6 = <<<'EOT'
         . "GENERATE ONLY WHEN READY: once you know the recipient, the facts and the goal, write the COMPLETE formal letter/petition IN THE SAME LANGUAGE the user is using in the conversation, and wrap it EXACTLY between a line containing only [[DOC]] and a line containing only [[/DOC]]. Structure it properly: sender block (use the placeholders), recipient block, place and date, a subject/Betreff line, salutation, a CONCISE body that states ONLY the facts the user themselves provided /*CH_AICHAT_NOHALL*/ — invent no extra facts, no legal arguments and no demands the user did not state; cite the applicable well-established paragraphs/§§ you are certain are real and correctly apply (when unsure of the exact number, state the point in plain words without inventing one; never invent case-law); then exactly the demand the user actually asked for, a closing and a signature line. Keep it tight and professional, no padding. If key information is still missing, ask for it instead of generating. "
EOT;
$n6 = <<<'EOT'
         . "GENERATE ONLY WHEN READY: once you know the recipient, the facts and the goal, write the COMPLETE formal letter/petition in {$lawLangName} — the official document language under {$lawHint} — regardless of which language this conversation has been conducted in; NEVER silently write the document itself in the conversation language instead. EXCEPTION: if the matter clearly concerns a DIFFERENT country (travel, visa, consulate or similar cross-border matters), ask the user whether they ALSO want a second edition in that country's language, and only add a separate additional [[DOC]]...[[/DOC]] block in that language if they confirm — never produce it unasked. Wrap the {$lawLangName} document EXACTLY between a line containing only [[DOC]] and a line containing only [[/DOC]]. Structure it properly: sender block (use the placeholders), recipient block, place and date, a subject/Betreff line, salutation, a CONCISE body that states ONLY the facts the user themselves provided /*CH_AICHAT_NOHALL*/ — invent no extra facts, no legal arguments and no demands the user did not state; NEVER substitute or invent a different name, date, address or fact than what was already established earlier in this same conversation — reuse them EXACTLY as given, and if a needed detail was genuinely never provided, ask for it rather than guessing a plausible-looking replacement; cite the applicable well-established paragraphs/§§ you are certain are real and correctly apply (when unsure of the exact number, state the point in plain words without inventing one; never invent case-law); then exactly the demand the user actually asked for, a closing and a signature line. Keep it tight and professional, no padding. If key information is still missing, ask for it instead of generating. "
EOT;
$c=substr_count($api,$o6);
if($c!==1){ echo "  ✗ [3b/5] GENERATE anchor ($c)\n"; $fail[]='3b'; }
else { $api=str_replace($o6,$n6,$api,$c); echo "  ✓ [3b] belge hukuk dilinde uretiliyor (sorulmadan baska dile gecmiyor)\n"; echo "  ✓ [5] yeniden-yazimda isim/tarih/adres uydurmasi engellendi\n"; }

/* ── EK: CH_LANG_FORCE2 [[DOC]] icerigini "hatali Almanca" sanip cevirmesin ── */
$o7 = "if (\$__sn && \$__sn !== 'de' && ch_sniff_lang(mb_substr((string)\$reply, 0, 500)) === 'de') { /*CH_LANG_FORCE2 — Almanca cikti: zorunlu yeniden yazim*/";
$n7 = "if (\$__sn && \$__sn !== 'de' && strpos(\$reply, '[[DOC]]') === false && ch_sniff_lang(mb_substr((string)\$reply, 0, 500)) === 'de') { /*CH_LANG_FORCE2 — Almanca cikti: zorunlu yeniden yazim (CH_CHATDOC_FIX: [[DOC]] iceren yanitlarda tetiklenmez)*/";
$c=substr_count($api,$o7);
if($c!==1){ echo "  ✗ [EK] CH_LANG_FORCE2 anchor ($c)\n"; $fail[]='ek'; }
else { $api=str_replace($o7,$n7,$api,$c); echo "  ✓ [EK] dil-zorlama denetimi artik [[DOC]] iceren yanitlara dokunmuyor\n"; }

if ($fail) { echo "\nHATA: eslesmeyen adimlar: ".implode(', ',$fail)." — HICBIR dosya DEGISTIRILMEDI.\n"; exit; }

/* ── CSS: bulanik onizleme ── */
$css = "\n<style id=\"ch-chatdoc-fix-css\">\n/* CH_CHATDOC_FIX */\n.ch-doccard-body.ch-doccard-locked{filter:blur(6px);user-select:none;-webkit-user-select:none;pointer-events:none;max-height:280px;overflow:hidden}\n</style>";
$pos = strrpos($idx,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$idx = substr($idx,0,$pos).$css."\n".substr($idx,$pos);
echo "  ✓ bulanik onizleme CSS'i eklendi\n";

foreach ([[$idxFile,$idx],[$apiFile,$api]] as $pair) {
    $tmp = tempnam(sys_get_temp_dir(),'cd').'.php';
    file_put_contents($tmp,$pair[1]);
    $lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
    if ($rc!==0) { echo "\nLINT HATASI (".basename($pair[0]).") — HICBIR dosya degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
}
$bk1=@file_put_contents($idxFile.'.bak-chatdocfix-'.date('Ymd-His'), @file_get_contents($idxFile));
$bk2=@file_put_contents($apiFile.'.bak-chatdocfixapi-'.date('Ymd-His'), @file_get_contents($apiFile));
if ($bk1===false || $bk2===false) echo "  ⚠ yedek(ler) yazilamadi — devam\n";
$w1=@file_put_contents($idxFile,$idx);
$w2=@file_put_contents($apiFile,$api);
if ($w1===false || $w1<strlen($idx) || $w2===false || $w2<strlen($api)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk1=(string)@file_get_contents($idxFile);
$chk2=(string)@file_get_contents($apiFile);
if (strpos($chk1,'CH_CHATDOC_FIX')===false) { echo "\n✗ DOGRULAMA BASARISIZ (index.php).\n"; exit; }
echo "  ✓ DOGRULAMA: CH_CHATDOC_FIX diskte — index.php ".strlen($chk1)." bayt, api.php ".strlen($chk2)." bayt\n";
echo "\n✓ CH_CHATDOC_FIX uygulandi (5 madde + dil-zorlama duzeltmesi).\n";
