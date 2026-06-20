<?php
/**
 * ChatHelp — Ana sayfa grid'ine Verträge + Bewerbung kartı ekle + GÜNCELLEME KONTROLÜ
 *  • Eski grid stilinde (.wcat) Verträge & Bewerbung kartları (showCat ile açılır)
 *  • Bugünkü tüm güncellemelerin index.php'de olup olmadığını raporlar
 * KULLANIM: chat-help.com/chat/apply-home-cats.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src=file_get_contents($file); $start=$src;
file_put_contents($file . '.bak-homecats-' . date('Ymd-His'), $src);

echo "ChatHelp — Güncelleme kontrolü + grid kartları\n=============================================\n\n";

/* ── A) Bugünkü güncellemeler index.php'de mi? ── */
$markers=[
  'CH_VTR_INLINE'   =>'Verträge (34 sözleşme)',
  'CH_BEW_INLINE'   =>'Bewerbung (Lebenslauf vs.)',
  'CH_CLEANQ_INLINE'=>'Teknik soru temizliği',
  'ch-i18n-layer'   =>'8 dil + çeviri',
  'ch-lang-mobile'  =>'Mobil dil + bilgi kartı',
  'ch-foto-gate'    =>'Foto-gate + Beispiel-Fragen',
  'ch-attach-v2'    =>'Foto/dosya ekleme',
  'ch-einzelkauf'   =>'Tek ödeme teslim',
  'ch-doc-email'    =>'Dilekçe e-posta',
  'ch-mega-layer'   =>'AI adı gizle/durdur/ton',
  'ch-foto-gate'    =>'Foto paywall',
];
echo "[Güncellemeler index.php'de mi?]\n";
foreach($markers as $m=>$d){ echo (strpos($src,$m)!==false?"  ✓ ":"  ✗ EKSİK ").$d."  ($m)\n"; }

/* ── B) Grid'e Verträge + Bewerbung kartı ekle (additive) ── */
if (strpos($src,'ch-home-cats')!==false){
  echo "\n[Grid kartları] Zaten ekli.\n";
} else {
$bundle = <<<'HTML'
<style id="ch-home-cats-css">
.wcat[data-chcat] .wcat-ic{font-size:18px;align-items:center;justify-content:center;background:rgba(212,168,74,.12)}
</style>
<script id="ch-home-cats">
(function(){
  var CARDS=[
    {k:'vertraege', ic:'📜', n:'Verträge', s:'34 rechtssichere Verträge'},
    {k:'bewerbung', ic:'📄', n:'Bewerbung', s:'Lebenslauf, Anschreiben & mehr'}
  ];
  function add(){
    var grid=document.querySelector('.wcat-grid'); if(!grid) return;
    CARDS.forEach(function(c){
      if(grid.querySelector('[data-chcat="'+c.k+'"]')) return;
      var el=document.createElement('div'); el.className='wcat'; el.setAttribute('data-chcat',c.k);
      el.onclick=function(){ try{ if(typeof showCat==='function') showCat(c.k); }catch(e){} };
      el.innerHTML='<div class="wcat-ic">'+c.ic+'</div><div class="wcat-t">'+c.n+'</div><div class="wcat-s">'+c.s+'</div>';
      grid.appendChild(el);
    });
  }
  function boot(){ add(); setInterval(add, 1000); }
  if(document.body) boot(); else document.addEventListener('DOMContentLoaded',boot);
})();
</script>
HTML;
  $n=0; $src=preg_replace('/<\/body>/', $bundle."\n</body>", $src, 1, $n);
  echo "\n[Grid kartları] ".($n>0?"✓ eklendi (Verträge + Bewerbung)":"✗ </body> yok")."\n";
}

$changed=($src!==$start);
if($changed) file_put_contents($file,$src);
echo "\n".($changed?"DURUM: güncellendi. ✅":"DURUM: değişiklik yok.")."\n";
echo "\n⚠️ EKSİK (✗) varsa söyle — o güncellemeyi tekrar uygularım.\n";
echo "⚠️ Hepsi ✓ ama görünmüyorsa = TARAYICI ÖNBELLEĞİ -> gizli sekme / hard-refresh.\n";
echo "SONRA: opcache-reset.php. SİL: rm apply-home-cats.php\n";
