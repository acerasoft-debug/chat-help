<?php
/**
 * ChatHelp — Dilekçe sorularına fotoğraf yükleme
 * -----------------------------------------------
 *  #1 showQuestions: her soru bloğunun altına küçük foto ekleme alanı
 *  #2 genDoc: toplanan fotoğrafları base64 olarak API'ye gönder
 *  #3 api.php: foto içeriğini Claude vision API ile oku ve dilekçeye ekle
 *  #4 Stil: foto alanı soruyla aynı boyutta, şık görünüm
 *
 * KULLANIM: chat-help.com/chat/apply-photo-upload.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$ifile = __DIR__ . '/index.php';
$afile = __DIR__ . '/api.php';
if (!file_exists($ifile)) { exit("HATA: index.php yok.\n"); }
if (!file_exists($afile)) { exit("HATA: api.php yok.\n"); }

$idx  = file_get_contents($ifile);
$api  = file_get_contents($afile);
$si   = $idx; $sa = $api;
file_put_contents($ifile . '.bak-photo-' . date('Ymd-His'), $idx);
file_put_contents($afile . '.bak-photo-' . date('Ymd-His'), $api);
$report = [];

function rp(&$src, $label, $old, $new) {
    global $report;
    $n = substr_count($src, $old);
    if ($n > 0) $src = str_replace($old, $new, $src);
    $report[] = [$label, $n];
}

/* ── #1 Soru bloğuna fotoğraf alanı ekle (showQuestions içinde) ── */
// Her inp oluştuktan sonra opsiyonel foto input'u ekle
// Tip: sorudan sonra gelen </div> ya da inp oluşturma bloğu
rp($idx, '#1 Soru-foto CSS',
    '</style>',
    '.ch-q-photo{margin-top:6px;display:flex;align-items:center;gap:8px}'
    . '.ch-q-photo label{display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;color:var(--t4);padding:6px 10px;border:1px dashed var(--bdr);border-radius:8px;background:var(--s2);transition:.15s}'
    . '.ch-q-photo label:hover{border-color:var(--gold);color:var(--gold)}'
    . '.ch-q-photo input[type=file]{display:none}'
    . '.ch-q-photo .ph-name{max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px;color:var(--t3)}'
    . '.ch-q-photo .ph-rm{cursor:pointer;color:var(--t4);font-size:14px;line-height:1}'
    . '</style>');

// showQuestions içinde inp oluşturulduktan sonra foto alanı ekle
rp($idx, '#1b Soru-foto alanı (inp sonrası)',
    "div.innerHTML='<label>'+q.l+(q.r?' *':'')+'</label>'+inp;",
    "div.innerHTML='<label>'+q.l+(q.r?' *':'')+'</label>'+inp"
    . "+'<div class=\"ch-q-photo\"><label><input type=\"file\" accept=\"image/*\" data-qk=\"'+q.k+'\"'
    + ' onchange=\"chQPhotoChange(this)\">📎 <span>Foto hinzufügen</span></label><span class=\"ph-name\"></span>'
    + '<span class=\"ph-rm\" onclick=\"chQPhotoRm(this)\" style=\"display:none\">✕</span></div>';");

/* ── #2 Foto işleme JavaScript fonksiyonları (body sonuna) ── */
$photoScript = <<<'JS'
<script>
window._chPhotos={};
window.chQPhotoChange=function(inp){
  var qk=inp.dataset.qk,file=inp.files[0];
  if(!file)return;
  if(file.size>5*1024*1024){alert('Bild zu groß (max. 5 MB).');inp.value='';return;}
  var reader=new FileReader();
  reader.onload=function(e){
    var b64=e.target.result.split(',')[1];
    window._chPhotos[qk]={data:b64,type:file.type,name:file.name};
    var wrap=inp.closest('.ch-q-photo');
    if(wrap){wrap.querySelector('.ph-name').textContent=file.name;wrap.querySelector('.ph-rm').style.display='';}
  };reader.readAsDataURL(file);
};
window.chQPhotoRm=function(btn){
  var wrap=btn.closest('.ch-q-photo'),inp=wrap?wrap.querySelector('input[type=file]'):null;
  if(!inp)return;
  var qk=inp.dataset.qk;
  delete window._chPhotos[qk];
  inp.value='';wrap.querySelector('.ph-name').textContent='';btn.style.display='none';
};
</script>
JS;
rp($idx, '#2 Foto JS fonksiyonları', '</body>', $photoScript . "\n</body>");

/* ── #3 genDoc: _chPhotos'u API'ye gönder ── */
// body:JSON.stringify({action:'generate',...}) → photos ekle
rp($idx, '#3a genDoc photos (generate)',
    "body:JSON.stringify({action:'generate',",
    "body:JSON.stringify({action:'generate',photos:Object.values(window._chPhotos||{}),");
rp($idx, '#3b genDoc photos (document)',
    "body:JSON.stringify({action:'document',",
    "body:JSON.stringify({action:'document',photos:Object.values(window._chPhotos||{}),");
// Her iki isim de denensin (api.php'ye göre değişiyor)
rp($idx, '#3c genDoc photos (generate_doc)',
    "body:JSON.stringify({action:'generate_doc',",
    "body:JSON.stringify({action:'generate_doc',photos:Object.values(window._chPhotos||{}),");

/* ── #4 api.php: gelen fotoğrafları Claude vision'a ilet ── */
// Mevcut Claude çağrısına foto parametresi ekle
// doGenerate veya doDocument fonksiyonu içindeki claude() çağrısını genişlet
$photoApiCode = <<<'PHP'

    // Fotoğraflar varsa Claude vision kullan
    $photos = array_filter($body['photos'] ?? [], fn($p) =>
        isset($p['data']) && strlen($p['data']) > 100 && strlen($p['data']) < 5000000
    );
    if ($photos) {
        $photoNote = "\n\nHINWEIS: Der Nutzer hat " . count($photos) . " Bild(er) beigefügt "
            . "(z.B. Bescheid, Rechnung, Schreiben). Analysiere den Bildinhalt vollständig "
            . "und nutze alle sichtbaren Informationen für das Dokument.";
    } else { $photoNote = ''; }
PHP;

// Claude çağrısından önce photo işleme kodunu ekle
rp($api, '#4a api.php foto ön işleme',
    "    \$document = claude(",
    $photoApiCode . "\n    \$document = claude(");

// claude() fonksiyonuna fotoğraf desteği ekle (yeni sürüm)
$claudePhotoFn = <<<'PHP'
function claudeVision(string $system, string $userText, array $photos = [], int $maxTok = 3000): string {
    if ($photos) {
        $content = [['type'=>'text','text'=>$userText]];
        foreach ($photos as $ph) {
            $mt = $ph['type'] ?? 'image/jpeg';
            if (!in_array($mt, ['image/jpeg','image/png','image/gif','image/webp'])) continue;
            $content[] = ['type'=>'image','source'=>['type'=>'base64','media_type'=>$mt,'data'=>$ph['data']]];
        }
        $msgs = [['role'=>'user','content'=>$content]];
    } else {
        $msgs = [['role'=>'user','content'=>$userText]];
    }
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>120, CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-api-key: '.CLAUDE_KEY,'anthropic-version: 2023-06-01'],
        CURLOPT_POSTFIELDS=>json_encode(['model'=>'claude-sonnet-4-6','max_tokens'=>$maxTok,'system'=>$system,'messages'=>$msgs]),
    ]);
    $res=curl_exec($ch); curl_close($ch);
    $d=json_decode($res,true);
    return $d['content'][0]['text']??'';
}
PHP;
rp($api, '#4b claudeVision() fonksiyonu',
    "function claude(string \$system",
    $claudePhotoFn . "\nfunction claude(string \$system");

// claude() çağrısını claudeVision() ile değiştir
rp($api, '#4c claude() -> claudeVision() çağrısı',
    "\$document = claude(\$sys, \$usr",
    "\$document = claudeVision(\$sys, \$usr\$photoNote, array_values(\$photos)");

// Üretilen metin artık $photoNote içeriyor — claude çağrısının ikinci argümanını güncelle
// Alternatif: sadece $photoNote'u $usr'a ekle, claudeVision'ı da claude yerine çağır
rp($api, '#4d photoNote usr birleştirme',
    "\$document = claudeVision(\$sys, \$usr\$photoNote, array_values(\$photos)",
    "\$document = claudeVision(\$sys, \$usr . \$photoNote, array_values(\$photos)");

$iChanged = ($idx !== $si);
$aChanged = ($api !== $sa);
if ($iChanged) file_put_contents($ifile, $idx);
if ($aChanged) file_put_contents($afile, $api);

echo "ChatHelp — Fotoğraf Yükleme Raporu\n=====================================\n\n";
foreach ($report as [$l,$n]) echo ($n > 0 ? "  ✓ " : "  ✗ ") . $l . "  →  $n\n";
echo "\n";
echo "index.php: " . ($iChanged ? "güncellendi ✅" : "değişiklik yok") . "\n";
echo "api.php:   " . ($aChanged ? "güncellendi ✅" : "değişiklik yok") . "\n";
echo "\n✗ çıkanlar için dump-chat2.php çalıştır ve gerçek metni gönder.\n";
echo "SONRA: opcache-reset.php. SİL: rm apply-photo-upload.php\n";
echo "\nTest: Bir dilekçe aç, soru altında '📎 Foto hinzufügen' görün, fotoğraf seç, oluştur.\n";
