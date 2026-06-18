<?php
/**
 * ChatHelp — Foto v2 (gerçek koda göre)
 *  #1 genDoc body'sine photos ekle (gerçek anchor: {docType:dk,docName:doc.n,)
 *  #2 Her soru alanının (data-k) altına foto ekleme kontrolü — ÇALIŞMA ANINDA,
 *     showQuestions sarmalanır, her formda _chPhotos sıfırlanır. (Güvenilir, additive.)
 * KULLANIM: chat-help.com/chat/apply-photo2.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src   = file_get_contents($file);
$start = $src;
file_put_contents($file . '.bak-photo2-' . date('Ymd-His'), $src);
$rep = [];
function rp(&$src, $label, $old, $new) {
    global $rep;
    $n = substr_count($src, $old);
    if ($n > 0) $src = str_replace($old, $new, $src);
    $rep[] = [$label, $n];
}

/* ── #1 genDoc -> photos gönder ── */
rp($src, '#1 genDoc body photos',
    'JSON.stringify({docType:dk,docName:doc.n,',
    'JSON.stringify({docType:dk,docName:doc.n,photos:Object.values(window._chPhotos||{}),');

/* ── #2 Çalışma anında foto UI katmanı ── */
if (strpos($src, 'ch-photo2-layer') === false) {
    $layer = <<<'HTML'
<style id="ch-photo2-css">
.ch-qp{margin:6px 0 2px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.ch-qp label{display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;color:var(--t4,#8e8e9c);padding:6px 11px;border:1px dashed var(--bdr,rgba(255,255,255,.16));border-radius:9px;background:var(--s2,#29292e);transition:.15s}
.ch-qp label:hover{border-color:var(--gold,#d4a84a);color:var(--gold,#d4a84a)}
.ch-qp input[type=file]{display:none}
.ch-qp .nm{font-size:11px;color:var(--t3,#b4b4c0);max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ch-qp .rm{cursor:pointer;color:#c0392b;font-size:13px;font-weight:700;display:none}
.ch-qp.has .rm{display:inline}
</style>
<script id="ch-photo2-layer">
(function(){
  'use strict';
  window._chPhotos=window._chPhotos||{};
  window.chQP=function(inp,key){
    var f=inp.files&&inp.files[0]; if(!f) return;
    if(f.size>5*1024*1024){ alert('Bild zu groß (max. 5 MB).'); inp.value=''; return; }
    var fr=new FileReader();
    fr.onload=function(e){
      window._chPhotos[key]={data:(''+e.target.result).split(',')[1],type:f.type,name:f.name};
      var box=inp.closest('.ch-qp'); if(box){ box.classList.add('has'); box.querySelector('.nm').textContent=f.name; }
    };
    fr.readAsDataURL(f);
  };
  window.chQPrm=function(key,el){
    delete window._chPhotos[key];
    var box=el.closest('.ch-qp'); if(box){ box.classList.remove('has'); box.querySelector('.nm').textContent=''; var i=box.querySelector('input[type=file]'); if(i)i.value=''; }
  };
  function decorate(){
    var fields=document.querySelectorAll('[data-k]');
    fields.forEach(function(el){
      if(el.tagName==='OPTION') return;
      var key=el.getAttribute('data-k'); if(!key) return;
      // aynı alan için zaten eklendiyse atla
      var host=el.closest('.fi')||el.parentNode; if(!host) return;
      if(host.querySelector('.ch-qp[data-for="'+cssEsc(key)+'"]')) return;
      var qp=document.createElement('div'); qp.className='ch-qp'; qp.setAttribute('data-for',key);
      qp.innerHTML='<label><input type="file" accept="image/*"> 📎 Foto/Beleg hinzufügen</label>'
        +'<span class="nm"></span><span class="rm">✕ entfernen</span>';
      qp.querySelector('input').addEventListener('change',function(){ window.chQP(this,key); });
      qp.querySelector('.rm').addEventListener('click',function(){ window.chQPrm(key,this); });
      host.appendChild(qp);
    });
  }
  function cssEsc(s){ return (''+s).replace(/["\\]/g,'\\$&'); }

  // showQuestions sarmala: her yeni formda fotoları sıfırla + dekore et
  function wrap(){
    if(typeof window.showQuestions==='function' && !window._sqWrapped){
      var _sq=window.showQuestions;
      window.showQuestions=function(){
        window._chPhotos={};
        var r=_sq.apply(this,arguments);
        setTimeout(decorate,200); setTimeout(decorate,600); setTimeout(decorate,1200);
        return r;
      };
      window._sqWrapped=true;
    }
  }
  function boot(){ wrap(); setInterval(function(){ wrap(); decorate(); },1500); }
  if(document.body) boot(); else document.addEventListener('DOMContentLoaded',boot);
})();
</script>
HTML;
    $n2 = 0;
    $src = preg_replace('/<\/body>/', $layer . "\n</body>", $src, 1, $n2);
    $rep[] = ['#2 Foto UI katmanı (data-k tarama)', $n2];
} else { $rep[] = ['#2 (zaten ekli)', 0]; }

$changed = ($src !== $start);
if ($changed) file_put_contents($file, $src);
echo "ChatHelp — Foto v2 raporu\n=========================\n\n";
foreach ($rep as [$l,$n]) echo ($n>0?"  ✓ ":"  ✗ ").$l."  →  $n\n";
echo "\n" . ($changed ? "DURUM: index.php güncellendi. ✅\n" : "DURUM: Değişiklik yok.\n");
echo "\nNOT: Backend'in fotoları kullanması için dump4 sonrası bir patch daha gelecek.\n";
echo "SONRA: opcache-reset.php. SİL: rm apply-photo2.php\n";
echo "Test: dilekçe aç -> her sorunun altında '📎 Foto/Beleg hinzufügen' görünür.\n";
