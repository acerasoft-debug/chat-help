<?php
/**
 * ChatHelp — Toplu güncelleme v2
 * ------------------------------
 *  #1 PDF temizliği: "Per Einschreiben/Rückschein" satırları silinir,
 *     bozuk karakterler (—, "", '', …, emoji) düzeltilir
 *  #2 Fall artık Basic'ten itibaren (kotalar sunucuda: 5/20/100) + etiketler
 *  #3 Fall listesi premium kart görünümü
 *  #4 Plan metinleri: Fallanalysen 5→20 (Pro), 50→100 (Elite), Basic'e 5 eklendi
 *  #5 showProModal (eski 9,99 penceresi) -> Konto  (sağlam yöntem)
 *  #6 TIER_CONFIG -> Formular Basis 1,00€ / Plus 1,99€  (sağlam yöntem)
 *  #7 Überarbeiten düzeltildi + kota: Basic 3 / Pro 10 / Elite sınırsız
 *  #8 Meine Anträge + Fall premium estetik (CSS)
 *
 * KULLANIM: chat-help.com/chat/apply-fall-v2.php -> sonra opcache-reset.php
 * İŞ BİTİNCE SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src = file_get_contents($file);
$start = $src;
file_put_contents($file . '.bak-v2-' . date('Ymd-His'), $src);
$report = [];
function rep(&$src, $label, $old, $new) {
    global $report;
    $n = substr_count($src, $old);
    if ($n > 0) $src = str_replace($old, $new, $src);
    $report[] = [$label, $n];
}

/* ── #1 PDF sanitizer (makePDF'e kanca) ── */
if (strpos($src, 'function chSanitizePDF') === false) {
    $san = <<<'JS'
function chSanitizePDF(t){
  return (t||'')
    .split('\n').filter(function(l){return !/Einschreiben|Rückschein/i.test(l);}).join('\n')
    .replace(/[—–]/g,'-')
    .replace(/[“”„«»]/g,'"')
    .replace(/[‘’‚]/g,"'")
    .replace(/…/g,'...')
    .replace(/ /g,' ')
    .replace(/[•●■▪]/g,'-')
    .replace(/\*\*/g,'')
    .replace(/[\u{1F000}-\u{1FFFF}\u{2600}-\u{27BF}\u{2B00}-\u{2BFF}️‍←-⇿]/gu,'')
    .replace(/[ \t]+\n/g,'\n')
    .replace(/\n{3,}/g,'\n\n').trim();
}
JS;
    rep($src, '#1 PDF sanitizer + makePDF kancasi',
        'function makePDF(doc,name,cc){',
        $san . "\nfunction makePDF(doc,name,cc){doc=chSanitizePDF(doc);");
} else { $report[] = ['#1 PDF sanitizer (zaten ekli)', 0]; }

/* ── #2 Fall: Basic'ten itibaren + etiketler ── */
rep($src, '#2a Fall plan kontrolu (basic dahil)',
    "if(plan()!=='pro'&&plan()!=='elite'){ p.innerHTML=upsell('Die KI-Fallanalyse ist nur für Pro & Elite verfügbar.', false); return; }",
    "if(['basic','pro','elite'].indexOf(plan())===-1){ p.innerHTML=upsell('Die KI-Fallanalyse erfordert einen aktiven Plan — Basic: 5, Pro: 20, Elite: 100 Fälle/Monat.', false); return; }");
rep($src, '#2b Kenar menu etiketi',
    '<div class="sb-photo-s">KI-Tiefenanalyse · Pro/Elite</div>',
    '<div class="sb-photo-s">KI-Tiefenanalyse · ab Basic</div>');

/* ── #3 Fall listesi premium kart ── */
rep($src, '#3 Fall kart premium stil',
    'background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:14px 16px;margin-bottom:10px;cursor:pointer;display:flex;align-items:center;gap:12px',
    'background:linear-gradient(135deg,rgba(212,168,74,.08),rgba(255,255,255,.02));border:1px solid rgba(212,168,74,.20);border-radius:16px;padding:15px 16px;margin-bottom:10px;cursor:pointer;display:flex;align-items:center;gap:12px;box-shadow:0 4px 14px rgba(0,0,0,.25)');

/* ── #4 Fallanalysen sayıları (önce 50→100, sonra 5→20) ── */
rep($src, '#4a Elite 50 -> 100 Fallanalysen', '50 Fallanalysen', '100 Fallanalysen');
rep($src, '#4b Pro 5 -> 20 Fallanalysen', '5 Fallanalysen', '20 Fallanalysen');
rep($src, '#4c Basic karti (Konto) 5 Fallanalysen eklendi',
    "feats:[['check','20 Dokumente / Monat'],['check','20 Staatl. Formulare / Mo'],['check','PDF-Download'],['check','KI-Recherche']],
     miss:['Ton-Anpassung','Strategie-Analyse','Fallanalyse']},",
    "feats:[['check','20 Dokumente / Monat'],['check','20 Staatl. Formulare / Mo'],['check','PDF-Download'],['check','KI-Recherche'],['brain','5 Fallanalysen / Monat']],
     miss:['Ton-Anpassung','Strategie-Analyse']},");

/* ── #5 showProModal -> Konto (gövdeden bağımsız, sağlam) ── */
rep($src, '#5 showProModal -> Konto',
    'function showProModal(){',
    "function showProModal(){ gP('konto'); return;");

/* ── #6 TIER_CONFIG -> Formular Basis/Plus (parça parça, sağlam) ── */
rep($src, '#6a TIER einfach -> Basis 1,00',
    "price_id:'price_1Tf71jAJdvZPpak76GX3MZID',price:'1,99€',label:'Einfach'",
    "price_id:'price_1TgyGdAJdvZPpak764pQThUw',price:'1,00€',label:'Formular Basis'");
rep($src, '#6b TIER standard -> Basis 1,00',
    "price_id:'price_1Tf75LAJdvZPpak75eZIpoj6',price:'2,99€',label:'Standard'",
    "price_id:'price_1TgyGdAJdvZPpak764pQThUw',price:'1,00€',label:'Formular Basis'");
rep($src, '#6c TIER komplex -> Plus 1,99',
    "price_id:'price_1Tf7H6AJdvZPpak7QSdWq61K',price:'3,99€',label:'Komplex'",
    "price_id:'price_1TgyKuAJdvZPpak7i10q1jhJ',price:'1,99€',label:'Formular Plus'");

/* ── #7 Überarbeiten: çalışır hale getir + kota ── */
rep($src, '#7a Uberarbeiten butonu -> chRevise',
    'c3.onclick=()=>showQuestions(dk);',
    'c3.onclick=()=>chRevise(dk,doc);');
rep($src, '#7b genDoc son cevaplari sakla',
    'async function genDoc(dk,ans){',
    "async function genDoc(dk,ans){window._lastGenAns={dk:dk,ans:Object.assign({},ans)};");
if (strpos($src, 'window.chRevise') === false) {
    $rev = <<<'JS'
<script>
window.chRevise=function(dk,prevDoc){
  var planLvl=(window.P&&P.plan)||(JSON.parse(localStorage.getItem('ch_user')||'{}').plan)||'free';
  var limits={basic:3,pro:10,elite:99999};
  if(!(planLvl in limits)){ if(typeof showPaywall==='function')showPaywall(dk,{}); return; }
  var key='rev_'+new Date().getFullYear()+'_'+(new Date().getMonth()+1);
  var used=parseInt(localStorage.getItem(key)||'0');
  if(used>=limits[planLvl]){
    addMsg('ai','⚠️ Überarbeitungs-Limit erreicht ('+used+'/'+(limits[planLvl]===99999?'∞':limits[planLvl])+' diesen Monat). Upgrade für mehr Überarbeitungen.');
    return;
  }
  var wunsch=prompt('Was soll geändert oder verbessert werden?');
  if(!wunsch||!wunsch.trim())return;
  localStorage.setItem(key,(used+1).toString());
  var base=(window._lastGenAns&&window._lastGenAns.dk===dk)?Object.assign({},window._lastGenAns.ans):{};
  base['aenderungswunsch']='WICHTIG — Überarbeitung der vorherigen Fassung. Gewünschte Änderung: '+wunsch.trim();
  base['vorherige_fassung']=(prevDoc||'').slice(0,1800);
  addMsg('user','🔄 Überarbeiten: '+wunsch.trim());
  genDoc(dk,base);
};
</script>
JS;
    rep($src, '#7c chRevise enjekte', '</body>', $rev . "\n</body>");
} else { $report[] = ['#7c chRevise (zaten ekli)', 0]; }

/* ── #8 Premium estetik (Anträge + Fall) ── */
if (strpos($src, 'id="chp-premium"') === false) {
    $css = '<style id="chp-premium">'
        . '.acard{background:linear-gradient(180deg,rgba(255,255,255,.05),rgba(255,255,255,.015))!important;border:1px solid rgba(212,168,74,.15)!important;border-radius:18px!important;overflow:hidden}'
        . '.acard:hover{border-color:rgba(212,168,74,.45)!important;box-shadow:0 10px 30px rgba(0,0,0,.4)!important;transform:translateY(-2px)}'
        . '.acard-ic{background:linear-gradient(135deg,rgba(212,168,74,.20),rgba(212,168,74,.05))!important;border:1px solid rgba(212,168,74,.35)!important;border-radius:12px!important}'
        . '.acard-name{letter-spacing:-.2px;font-size:14.5px!important}'
        . '.astat{background:linear-gradient(180deg,rgba(255,255,255,.055),rgba(255,255,255,.02))!important;border:1px solid rgba(255,255,255,.10)!important;border-radius:16px!important}'
        . '.astat-n{font-size:24px!important;letter-spacing:-.5px}'
        . '.ab{border-radius:11px!important;font-weight:600!important}'
        . '.aprev{border:1px solid rgba(255,255,255,.06);border-radius:12px!important}'
        . '#pnl-fall .pnl-topbar{background:linear-gradient(180deg,#0e0e1e,#0a0a16)!important;border-bottom:1px solid rgba(212,168,74,.15)!important}'
        . '#fall-body{background:radial-gradient(900px 360px at 50% -8%,rgba(212,168,74,.06),transparent)}'
        . '#fall-body button{transition:transform .12s ease,filter .12s ease}'
        . '#fall-body button:active{transform:scale(.97)}'
        . "</style>\n</head>";
    $n8 = 0;
    $src = preg_replace('/<\/head>/', $css, $src, 1, $n8);
    $report[] = ['#8 Premium estetik CSS', $n8];
} else { $report[] = ['#8 Premium CSS (zaten ekli)', 0]; }

$changed = ($src !== $start);
if ($changed) file_put_contents($file, $src);

echo "ChatHelp — Toplu güncelleme v2 raporu\n=====================================\n\n";
foreach ($report as [$l,$n]) echo ($n>0?"  ✓ ":"  ✗ ").$l."  →  $n\n";
echo "\n".($changed?"DURUM: index.php güncellendi. ✅\n":"DURUM: Değişiklik yok.\n");
echo "\nÖNEMLİ: Yeni fall-api.php'yi de yüklemeyi unutma (kotalar + API anahtarı düzeltmesi orada).\n";
echo "SONRA: opcache-reset.php aç.\nSil: rm apply-fall-v2.php dump-prices.php\n";
echo "\nNot: '✗ 0' satır varsa kalıp bulunamadı — bana hangi satır olduğunu yaz.\n";
