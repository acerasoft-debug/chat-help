<?php
/**
 * VESTRA — belge yukleme ARAYUZU: form, hata bandi, e-posta yolu ve tarayici
 * tarafinda fotograf kucultme. Alici ve satici paneli AYNI formu basar; iki
 * kopya (buyer.php / seller.php) bir kez ayristi -- accept listesi ve sinir
 * metni -- ve ayrisan taraf yanlisti.
 *
 * NEDEN VAR (2 Eyl 2026): yeni kaydolan bir alici belgesini iki gun
 * yukleyemedi ve dosyayi e-postayla gonderdi. Yol her hatada ayni cumleyi
 * basiyordu ("Upload failed... max 10 MB") ve post_max_size asimini hic
 * gormuyordu: PHP govdeyi tumden atar, $_POST bos kalir, handler calismaz,
 * sayfa sessizce yeniden yuklenir. Kullanicinin gordugu: "hicbir sey olmuyor".
 *
 * Kurallar (inc/auth.php): auth_doc_file_check / auth_doc_max_bytes /
 * auth_doc_error_text. Burasi yalnizca HTML/JS.
 */
require_once __DIR__.'/auth.php';

/* POST geldi ama $_POST ve $_FILES bos: post_max_size asildi. Tek iz
   CONTENT_LENGTH. Paneller bunu handler'lardan ONCE sorar ve sebebiyle
   yonlendirir; aksi halde bu istek hicbir dala girmez. */
function vestra_doc_post_overflow(): bool {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return false;
    if (!empty($_POST) || !empty($_FILES)) return false;
    $len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($len <= 0) return false;
    $max = auth_ini_bytes((string)ini_get('post_max_size'));
    return $max > 0 && $len > $max;
}

function vestra_doc_support_email(): string {
    return (string)(function_exists('vestra_cfg') ? vestra_cfg('mail_from', 'support@vestrasales.com') : 'support@vestrasales.com');
}

function vestra_doc_mailto(): string {
    return 'mailto:'.rawurlencode(vestra_doc_support_email()).'?subject='.rawurlencode('Document for my VESTRA account');
}

/* Hata bandi. Her metin bir CIKIS YOLU soyler (kucult / PDF / e-posta);
   "basarisiz" tek basina kullaniciyi ayni dosyayi ayni sekilde bir daha
   denemeye gonderir. */
function vestra_doc_upload_err_banner(string $code): string {
    $tr   = fn(string $s) => function_exists('t') ? t($s) : $s;
    $mail = vestra_doc_support_email();
    return '<div class="banner" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.3);color:var(--bad)">⚠ '
         . htmlspecialchars($tr(auth_doc_error_text($code)))
         . ' <a href="'.htmlspecialchars(vestra_doc_mailto()).'" style="color:inherit;text-decoration:underline">'.htmlspecialchars($mail).'</a></div>';
}

/* "E-postayla da gonderebilirsiniz" (operator karari, 2 Eyl 2026: evrak
   e-postayla girilebilir; operator paneldeki "Attach" ile hesaba ekler). */
function vestra_doc_email_hint(): string {
    $tr   = fn(string $s) => function_exists('t') ? t($s) : $s;
    $mail = vestra_doc_support_email();
    return '<div class="hint" style="margin-top:14px;padding-top:12px;border-top:1px solid var(--brd)">📧 '
         . htmlspecialchars($tr('Prefer e-mail? Send the document to')).' '
         . '<a href="'.htmlspecialchars(vestra_doc_mailto()).'" style="color:var(--acc)">'.htmlspecialchars($mail).'</a> '
         . htmlspecialchars($tr('from the address you registered with, and we attach it to your account for you.')).'</div>';
}

/* Yukleme formu. MAX_FILE_SIZE PHP'nin kendi kontrolu (asim -> FORM_SIZE ->
   'size' kodu); data-shrink tarayici tarafinda buyuk fotografi kucultur. */
function vestra_doc_upload_form(string $action, string $reqId, string $label): string {
    $max    = auth_doc_max_bytes();
    $accept = '.'.implode(',.', auth_doc_allowed_ext()).',image/*,application/pdf';
    return '<form method="post" action="'.htmlspecialchars($action).'" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">'
         . '<input type="hidden" name="_action" value="upload_doc">'
         . '<input type="hidden" name="MAX_FILE_SIZE" value="'.$max.'">'
         . '<input type="hidden" name="req_id" value="'.htmlspecialchars($reqId).'">'
         . '<input type="file" name="doc_file" accept="'.htmlspecialchars($accept).'" required data-shrink="1" data-max="'.$max.'" style="font-size:12px;max-width:220px">'
         . '<span class="hint shrinkhint" style="font-size:11px"></span>'
         . '<button class="btn btn-p btn-sm" type="submit">'.htmlspecialchars($label).'</button>'
         . '</form>';
}

/* Fotografi TARAYICIDA kucult. Telefon fotografi 5-12 MB; sunucu siniri
   bundan kucuk olabilir ve kullanici bunu bilemez. Buyuk resim secilince
   canvas ile en fazla 2000 px kenar, JPEG (kalite dusurerek hedefe iner).
   Kucuk dosya ya da cozulemeyen bicim (Safari disinda HEIC) oldugu gibi
   kalir -- sunucu o zaman SEBEBIYLE reddeder. Sayfada bir kez basilir. */
function vestra_doc_upload_js(): string {
    static $done = false;
    if ($done) return '';
    $done = true;
    return <<<'JS'
<script>
document.addEventListener('change', async function(e){
  var inp = e.target;
  if(!inp || inp.type !== 'file' || !inp.hasAttribute('data-shrink')) return;
  var f = inp.files && inp.files[0]; if(!f) return;
  var hint = inp.parentNode ? inp.parentNode.querySelector('.shrinkhint') : null;
  var max = parseInt(inp.getAttribute('data-max') || '0', 10) || 0;
  var isImg = /^image\//.test(f.type) || /\.(heic|heif|jpe?g|png|webp)$/i.test(f.name);
  if(!isImg) return;
  var target = Math.min(max || 1e12, 1.5*1024*1024);
  if(f.size <= target) return;
  if(hint) hint.textContent = '…';
  try{
    var bmp = await createImageBitmap(f);
    var s = Math.min(1, 2000 / Math.max(bmp.width, bmp.height));
    var c = document.createElement('canvas');
    c.width = Math.max(1, Math.round(bmp.width * s)); c.height = Math.max(1, Math.round(bmp.height * s));
    c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
    var q = 0.85, blob = null;
    for(var i = 0; i < 4; i++){
      blob = await new Promise(function(r){ c.toBlob(r, 'image/jpeg', q); });
      if(!blob || blob.size <= target) break;
      q -= 0.15;
    }
    if(!blob){ if(hint) hint.textContent = ''; return; }
    var nf = new File([blob], f.name.replace(/\.[^.]+$/, '') + '.jpg', {type:'image/jpeg'});
    var dt = new DataTransfer(); dt.items.add(nf); inp.files = dt.files;
    if(hint) hint.textContent = '✓ ' + Math.round(nf.size/1024) + ' KB';
  }catch(err){ if(hint) hint.textContent = ''; }
});
</script>
JS;
}
