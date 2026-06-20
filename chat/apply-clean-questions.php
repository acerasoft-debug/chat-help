<?php
/**
 * ChatHelp — Teknik/hukuki soruları TÜM belgelerden temizle (kullanıcı bilemez, AI belirler)
 * "Rechtsgrundlage", "Anspruchsgrundlage", "§ ...", "rechtliche Begründung", "Paragraph",
 * "gesetzliche Grundlage", "Rechtsnorm" gibi alanları DOCS'tan çıkarır (çalışma anında, tüm belgeler).
 * AI bu §§/dayanakları belgede zaten kendisi üretir.
 * KULLANIM: chat-help.com/chat/apply-clean-questions.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src=file_get_contents($file); $start=$src;
file_put_contents($file . '.bak-cleanq-' . date('Ymd-His'), $src);

$anchor = "function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if (strpos($src,'CH_CLEANQ_INLINE')!==false){ exit("Zaten ekli.\n"); }
if (strpos($src,$anchor)===false){ exit("✗ anchor (getDocPrice) bulunamadı.\n"); }

$js = <<<'JS'

/* CH_CLEANQ_INLINE — kullanıcının bilemeyeceği teknik/hukuki soruları kaldır (AI belirler) */
try{(function(){
  if(typeof DOCS==='undefined') return;
  var BAD=[
    /rechtsgrundlage/i,
    /gesetzliche?\s*grundlage/i,
    /gesetzesgrundlage/i,
    /anspruchsgrundlage/i,
    /\brechtsnorm/i,
    /einschl[äa]gige[rs]?\s*(paragraph|vorschrift|norm|gesetz)/i,
    /rechtliche[rs]?\s*begr[üu]ndung/i,
    /juristische[rs]?\s*begr[üu]ndung/i,
    /\bparagraph(en)?\b/i,
    /§\s*\d/,
    /einschl[äa]gige\s*§/i,
    /rechtsvorschrift/i
  ];
  function bad(l){ l=(''+(l||'')); for(var i=0;i<BAD.length;i++){ if(BAD[i].test(l)) return true; } return false; }
  var removed=0;
  for(var k in DOCS){ var d=DOCS[k];
    if(d && d.q && d.q.filter){
      var before=d.q.length;
      d.q=d.q.filter(function(q){ return !bad(q && q.l); });
      removed += (before - d.q.length);
    }
  }
  try{ window._chCleanQ=removed; }catch(e){}
})();}catch(e){}
JS;

$src=str_replace($anchor, $anchor.$js, $src);
$changed=($src!==$start);
if($changed) file_put_contents($file,$src);
echo "ChatHelp — Teknik soru temizliği raporu\n=======================================\n\n";
echo ($changed?"  ✓ Inline temizleyici eklendi (tüm DOCS, çalışma anında)\n":"  ✗ değişiklik yok\n");
echo "\nKaldırılan kalıplar: Rechtsgrundlage, Anspruchsgrundlage, gesetzliche Grundlage,\n";
echo "  Rechtsnorm, einschlägige Vorschrift/Paragraph, rechtliche/juristische Begründung, '§ ...'.\n";
echo "Not: AI bu §§/dayanakları belgede zaten kendisi üretir.\n";
echo "\n".($changed?"DURUM: güncellendi. ✅\n":"\n");
echo "SONRA: opcache-reset.php. SİL: rm apply-clean-questions.php\n";
echo "Test: o tip soruları içeren bir dilekçe aç -> 'Rechtsgrundlage' vb. artık sorulmaz.\n";
echo "(Tarayıcı konsolunda: window._chCleanQ = kaç soru kaldırıldı)\n";
