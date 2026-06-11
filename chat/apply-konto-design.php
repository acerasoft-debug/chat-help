<?php
/**
 * ChatHelp — Konto'ya estetik tarife kartları (kullanıcının tasarımı)
 * ------------------------------------------------------------------
 * renderKontoPlanSelector'ı, gönderilen 3-kart tarife tasarımıyla değiştirir:
 *   • Basic / Pro / Elite — Tabler ikonları, "Beliebteste Wahl" rozeti
 *   • Aktif plan işaretli, butonlar stripeCheckout'a bağlı
 *   • Mobilde tek sütun, masaüstünde 3 sütun
 *   • Free plan yok
 * Ayrıca Tabler ikon CSS'i + kart stillerini (chp- önekli) head'e ekler.
 *
 * KULLANIM: html/chat/ -> chat-help.com/chat/apply-konto-design.php
 * İŞ BİTİNCE SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');

$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php bulunamadı.\n"); }

$src   = file_get_contents($file);
$start = $src;
$bak = $file . '.bak-kontodesign-' . date('Ymd-His');
file_put_contents($bak, $src);

$report = [];

/* ── 1) Yeni renderKontoPlanSelector ── */
$new = <<<'NEWFUNC'
function renderKontoPlanSelector(){
  const sel=document.getElementById('k-plan-selector');
  if(!sel) return;
  const userPlan=P.plan||JSON.parse(localStorage.getItem('ch_user')||'{}').plan||'';
  const hasPlan=userPlan==='basic'||userPlan==='pro'||userPlan==='elite';
  const nm=document.getElementById('k-plan-name'); if(nm) nm.textContent=hasPlan?(userPlan.charAt(0).toUpperCase()+userPlan.slice(1)):'Kein Plan';
  const bd=document.getElementById('konto-plan-badge'); if(bd) bd.textContent=hasPlan?userPlan.toUpperCase():'KEIN PLAN';
  const C={check:'ti-check',inf:'ti-infinity',brain:'ti-brain'};
  const plans=[
    {key:'basic',name:'Basic',price:'13,99',color:'#BA7517',bg:'rgba(186,117,23,.1)',icon:'ti-medal',btnTxt:'#633806',btnBg:'rgba(186,117,23,.08)',btnBd:'rgba(186,117,23,.35)',pop:false,
     feats:[['check','20 Dokumente / Monat'],['check','20 Staatl. Formulare / Mo'],['check','PDF-Download'],['check','KI-Recherche']],
     miss:['Ton-Anpassung','Strategie-Analyse','Fallanalyse']},
    {key:'pro',name:'Pro',price:'29,99',color:'#185FA5',bg:'rgba(24,95,165,.1)',icon:'ti-trophy',pop:true,
     feats:[['check','50 Dokumente / Monat'],['inf','Unbegr. Staatl. Formulare'],['check','PDF-Download'],['check','KI-Recherche'],['check','Ton-Anpassung'],['check','Strategie-Analyse'],['brain','5 Fallanalysen / Monat']],
     miss:[]},
    {key:'elite',name:'Elite',price:'99,99',color:'#534AB7',bg:'rgba(83,74,183,.1)',icon:'ti-crown',btnTxt:'#3C3489',btnBg:'rgba(83,74,183,.08)',btnBd:'rgba(83,74,183,.35)',pop:false,
     feats:[['check','300 Dokumente / Monat'],['inf','Unbegr. Staatl. Formulare'],['check','PDF-Download'],['check','KI-Recherche'],['check','Ton-Anpassung'],['check','Strategie-Analyse'],['brain','50 Fallanalysen / Monat']],
     miss:[]},
  ];
  let html='<div class="chp-grid">';
  plans.forEach(p=>{
    const isCur=userPlan===p.key;
    const feats=p.feats.map(f=>`<div class="chp-feat"><i class="ti ${C[f[0]]}" style="color:#3B6D11"></i>${f[1]}</div>`).join('');
    const miss=p.miss.map(m=>`<div class="chp-feat chp-off"><i class="ti ti-minus"></i>${m}</div>`).join('');
    let btn;
    if(isCur){ btn=`<button class="chp-btn" style="background:#e9f7ef;border:0.5px solid #9bd5b3;color:#1f7a44;cursor:default" disabled><i class="ti ti-check" style="vertical-align:-1px"></i> Aktiv</button>`; }
    else if(p.pop){ btn=`<button class="chp-btn" onclick="stripeCheckout('${p.key}')" style="background:#185FA5;border:0.5px solid #185FA5;color:#fff">${hasPlan?'Wechseln':'Jetzt starten'}</button>`; }
    else { btn=`<button class="chp-btn" onclick="stripeCheckout('${p.key}')" style="background:${p.btnBg};border:0.5px solid ${p.btnBd};color:${p.btnTxt}">${hasPlan?'Wechseln':'Jetzt starten'}</button>`; }
    html+=`<div class="chp-card${p.pop?' chp-card-pro':''}${isCur?' chp-card-active':''}">${p.pop?'<div class="chp-badge">Beliebteste Wahl</div>':''}<div class="chp-ic" style="background:${p.bg}"><i class="ti ${p.icon}" style="font-size:20px;color:${p.color}"></i></div><div class="chp-tier" style="color:${p.color}">${p.name}</div><div class="chp-price">${p.price}€</div><div class="chp-period">pro Monat · jederzeit kündbar</div><div class="chp-sep"></div>${feats}${miss}<div class="chp-btnwrap">${btn}</div></div>`;
  });
  html+='</div><div class="chp-footer"><i class="ti ti-lock" style="font-size:13px;vertical-align:-1px;margin-right:4px"></i>Sichere Zahlung via Stripe · Alle Preise inkl. MwSt. · Keine versteckten Kosten</div>';
  sel.innerHTML=html;
}
NEWFUNC;

$pattern = '/function renderKontoPlanSelector\(\)\s*\{.*?sel\.innerHTML\s*=\s*html;\s*\}/s';
$c1 = 0;
$src = preg_replace_callback($pattern, function($m) use ($new){ return $new; }, $src, 1, $c1);
$report[] = ['renderKontoPlanSelector yeni tasarimla degistirildi', $c1];

/* ── 2) Tabler ikon CSS + kart stilleri (chp-) head'e ── */
$head = '';
if (strpos($src, 'tabler-icons') === false) {
    $head .= '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">';
}
$head .= '<style id="chp-style">'
    . '.chp-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:16px}'
    . '.chp-card{background:#fff;border:0.5px solid #e0dfd8;border-radius:16px;padding:20px 18px;display:flex;flex-direction:column;position:relative}'
    . '.chp-card-pro{border:2px solid #185FA5;box-shadow:0 0 0 4px rgba(24,95,165,.06)}'
    . '.chp-card-active{box-shadow:0 0 0 3px rgba(31,122,68,.20)}'
    . '.chp-badge{position:absolute;top:-13px;left:50%;transform:translateX(-50%);white-space:nowrap;font-size:11px;font-weight:600;padding:3px 12px;border-radius:100px;background:#e6f1fb;color:#185FA5;border:0.5px solid #b5d4f4}'
    . '.chp-ic{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:14px}'
    . '.chp-tier{font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;margin-bottom:4px}'
    . '.chp-price{font-size:30px;font-weight:600;line-height:1;margin-bottom:2px;color:#1a1a1a}'
    . '.chp-period{font-size:12px;color:#888;margin-bottom:18px}'
    . '.chp-sep{height:0.5px;background:#e0dfd8;margin-bottom:14px}'
    . '.chp-feat{display:flex;align-items:center;gap:8px;font-size:13px;color:#404040;padding:4px 0}'
    . '.chp-feat i{font-size:14px;flex-shrink:0}'
    . '.chp-off{color:#aaa}'
    . '.chp-btnwrap{margin-top:auto;padding-top:16px}'
    . '.chp-btn{width:100%;padding:11px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;transition:opacity .15s}'
    . '.chp-btn:hover{opacity:.85}'
    . '.chp-footer{text-align:center;font-size:12px;color:#888;margin-top:4px}'
    . '@media(max-width:760px){.chp-grid{grid-template-columns:1fr}}'
    . "</style>\n</head>";

$c2 = 0;
if (strpos($src, 'id="chp-style"') === false) {
    $src = preg_replace('/<\/head>/', $head, $src, 1, $c2);
}
$report[] = ['Tabler ikon CSS + kart stilleri eklendi', $c2];

/* ── Yaz ── */
$changed = ($src !== $start);
if ($changed) { file_put_contents($file, $src); }

echo "ChatHelp — Konto tarife tasarımı raporu\n";
echo "========================================\n";
echo "Yedek: " . basename($bak) . "\n\n";
foreach ($report as [$l,$n]) { echo ($n>0?"  ✓ ":"  ✗ ").$l."  →  $n\n"; }
echo "\n" . ($changed ? "DURUM: Güncellendi. ✅\n" : "DURUM: Değişiklik yok (zaten uygulanmış olabilir).\n");
echo "\nTest: Konto → 3 estetik tarife kartı (Basic/Pro/Elite), Pro'da 'Beliebteste Wahl' rozeti.\n";
echo "Sil:  rm apply-konto-design.php\n";
echo "\nNot: '✗ 0' görürsen kalıp bulunamadı — bana haber ver.\n";
