<?php
/**
 * ChatHelp — Konto plan bölümü: free plan kaldır + estetik paket karşılaştırması
 * -----------------------------------------------------------------------------
 * renderKontoPlanSelector fonksiyonunu yeniden yazar:
 *   • "free" plan kavramını kaldırır (badge/isim "Kein Plan")
 *   • Her 3 paketi (Basic/Pro/Elite) TAM özellik listesiyle gösterir (karşılaştırma)
 *   • Eksik özellikler ✗ ile, dahil olanlar ✓ ile
 *   • Pro'ya "BELIEBTESTE" rozeti, aktif plan işaretli, şık satın-al butonları
 *
 * KULLANIM: html/chat/ içine yükle -> chat-help.com/chat/apply-konto-plans.php aç
 * İŞ BİTİNCE SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');

$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php bulunamadı.\n"); }

$src   = file_get_contents($file);
$start = $src;
$bak = $file . '.bak-kontoplans-' . date('Ymd-His');
file_put_contents($bak, $src);

$new = <<<'NEWFUNC'
function renderKontoPlanSelector(){
  const sel = document.getElementById('k-plan-selector');
  if(!sel) return;
  const userPlan = P.plan || JSON.parse(localStorage.getItem('ch_user')||'{}').plan || '';
  const hasPlan = userPlan==='basic'||userPlan==='pro'||userPlan==='elite';
  const nm=document.getElementById('k-plan-name'); if(nm) nm.textContent = hasPlan ? (userPlan.charAt(0).toUpperCase()+userPlan.slice(1)) : 'Kein Plan';
  const bd=document.getElementById('konto-plan-badge'); if(bd){ bd.textContent = hasPlan ? userPlan.toUpperCase() : 'KEIN PLAN'; }
  const plans = [
    {key:'basic', ic:'🥉', name:'Basic', price:'13,99', color:'#d4a84a', pop:false,
     feats:['20 Dokumente / Monat','20 Staatliche Formulare','PDF-Download','KI-Recherche'],
     miss:['Ton-Anpassung','Strategie-Analyse','Fallanalysen']},
    {key:'pro', ic:'🥈', name:'Pro', price:'29,99', color:'#6094ff', pop:true,
     feats:['50 Dokumente / Monat','Unbegrenzte Staatliche Formulare','PDF-Download','KI-Recherche','Ton-Anpassung','Strategie-Analyse','5 Fallanalysen / Monat'],
     miss:[]},
    {key:'elite', ic:'👑', name:'Elite', price:'99,99', color:'#c090ff', pop:false,
     feats:['300 Dokumente / Monat','Unbegrenzte Staatliche Formulare','PDF-Download','KI-Recherche','Ton-Anpassung','Strategie-Analyse','50 Fallanalysen / Monat'],
     miss:[]},
  ];
  let html = '';
  if(!hasPlan){
    html += `<div style="text-align:center;margin-bottom:16px"><div style="font-size:15px;font-weight:800;color:#fff;margin-bottom:4px">Wählen Sie Ihren Plan</div><div style="font-size:12px;color:#808090">Jederzeit kündbar · Sichere Zahlung via Stripe</div></div>`;
  }
  plans.forEach(p=>{
    const isCurrent = userPlan === p.key;
    const feats = p.feats.map(f=>`<div style="display:flex;align-items:flex-start;gap:7px;font-size:12.5px;color:#d0d0e8;padding:3px 0"><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="${p.color}" stroke-width="3" stroke-linecap="round" style="flex-shrink:0;margin-top:2px"><polyline points="20,6 9,17 4,12"/></svg>${f}</div>`).join('');
    const miss = p.miss.map(f=>`<div style="display:flex;align-items:flex-start;gap:7px;font-size:12.5px;color:#54546a;padding:3px 0"><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="#54546a" stroke-width="2.5" stroke-linecap="round" style="flex-shrink:0;margin-top:2px"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>${f}</div>`).join('');
    html += `<div style="position:relative;padding:16px;margin-bottom:12px;background:${isCurrent?`linear-gradient(135deg,${p.color}1f,rgba(255,255,255,.02))`:'rgba(255,255,255,.025)'};border:1.5px solid ${isCurrent?p.color:(p.pop?'rgba(96,148,255,.45)':'rgba(255,255,255,.08)')};border-radius:16px">${p.pop&&!isCurrent?`<div style="position:absolute;top:-10px;left:50%;transform:translateX(-50%);background:#6094ff;color:#fff;font-size:9px;font-weight:800;padding:3px 12px;border-radius:100px;letter-spacing:.5px;white-space:nowrap">BELIEBTESTE</div>`:''}<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px"><div style="display:flex;align-items:center;gap:10px"><span style="font-size:24px">${p.ic}</span><div><div style="font-size:16px;font-weight:800;color:#fff">${p.name}</div><div style="font-size:13px;color:${p.color};font-weight:700">${p.price}€<span style="font-size:11px;color:#808090;font-weight:400">/Monat</span></div></div></div>${isCurrent?`<span style="font-size:10px;background:${p.color};color:#0a0a14;padding:4px 12px;border-radius:100px;font-weight:800">✓ AKTIV</span>`:''}</div><div style="margin-bottom:14px">${feats}${miss}</div>${isCurrent?`<div style="text-align:center;font-size:11px;color:${p.color};font-weight:600;padding:9px;background:rgba(255,255,255,.03);border-radius:10px">Ihr aktueller Plan</div>`:`<button onclick="stripeCheckout('${p.key}')" style="width:100%;padding:12px;background:linear-gradient(135deg,${p.color},${p.color}cc);border:none;border-radius:12px;color:#0a0a14;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit;-webkit-tap-highlight-color:transparent">${hasPlan?'Wechseln zu '+p.name:p.name+' wählen'} →</button>`}</div>`;
  });
  sel.innerHTML = html;
}
NEWFUNC;

$pattern = '/function renderKontoPlanSelector\(\)\s*\{.*?sel\.innerHTML = html;\s*\}/s';
$count = 0;
$src = preg_replace_callback($pattern, function($m) use ($new){ return $new; }, $src, 1, $count);

$changed = ($src !== $start);
if ($changed && $count > 0) { file_put_contents($file, $src); }

echo "ChatHelp — Konto plan karşılaştırması raporu\n";
echo "============================================\n";
echo "Yedek: " . basename($bak) . "\n\n";
echo ($count > 0 ? "  ✓ renderKontoPlanSelector yeniden yazıldı (free kaldırıldı + karşılaştırma)\n"
                 : "  ✗ Fonksiyon bulunamadı (kalıp eşleşmedi) — bana haber ver\n");
echo "\n" . (($changed && $count>0) ? "DURUM: Güncellendi. ✅\n" : "DURUM: Değişiklik yok.\n");
echo "\nTest: Konto bölümünü aç → 3 paket tam özellik listesiyle, ✓/✗ karşılaştırmalı görünmeli.\n";
echo "Sil:  rm apply-konto-plans.php\n";
