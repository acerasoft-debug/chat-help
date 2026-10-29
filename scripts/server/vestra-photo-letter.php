<?php
/**
 * FOTOĞRAFLI MEKTUP — satıcı adına alıcıya gerçek ürün fotoğrafları (19 Eki 2026, operatör: "bu fotoları Les Garage
 * Paris adına gönderiyoruz diye bu müşteriye gönder, estetik bir şekilde").
 *
 * Depo ve Actions kütüğü herkese açık: alıcının adresi, adı, mektup metni ve fotoğraflar TEK bir şifreli pakette gelir
 * (KEY.IV.GÖVDE, scripts/envelope_seal.php biçimi; özel anahtar ~/.vestra_inbox_key.pem sunucudan çıkmaz). Bu betik
 * yalnız GENEL şablondur — kişiye ait hiçbir şey taşımaz.
 *
 *   php vestra-photo-letter.php check <paket>   paketi açar, fotoğrafları siteye koyar, adresleri yoklar, GÖNDERMEZ
 *   php vestra-photo-letter.php send  <paket>   aynısı + mektubu gönderir
 *   php vestra-photo-letter.php render <spec.json> <çıktı.html>   (yerel önizleme; şifresiz spec, fotoğraf yolu yerel)
 *   php vestra-photo-letter.php sheet <paket> <çıktı.jpg>   mektubun fotoğraflarının (sunucuda çevrilmiş hâlleriyle)
 *     etiketli kontak sayfası; hiçbir şey yayınlamaz, göndermez (katalog mektubunu göndermeden gözle görmek için)
 *
 * Paket gövdesi (JSON): to, from_name, reply_to, subject, kicker, title, subtitle, greeting, intro[], chips[],
 * sections[{title, swatch, sub, photos[{name, b64, caption}]}], tap_hint, closing[], sign[], button{label,url}, footer.
 * to='order:<ref>' adresi sipariş kaydından çözer; name_from='order:<ref>' hitaptaki {name}'i o siparişin hesabından çözer
 * (operatöre [COPY] kopyası için ŞART — 28 Eki 2026); attachments[{name *.pdf, b64}] PDF ekler (21 Eki 2026).
 * Katalog mektubu için (19 Eki 2026, "tüm renklerini ve lotunu gönder") bölümde isteğe bağlı: colors[{name, css}]
 * (renk çipleri), facts[[etiket, değer]] (lot / MOQ satırları), sizes[[beden, adet]] (karton dökümü), cols (2|3),
 * link{label,url}, price + price_unit (başlığın yanında fiyat); üst düzeyde summary[[ad, kod, fiyat]] + summary_title
 * (girişin altında fiyat özeti); videos[{name (.mp4), b64, poster, poster_b64, caption}] (20 Eki 2026, depo videosu:
 * e-posta istemcileri videoyu satır içinde oynatmaz — kapak görseli + oynat işareti videonun yayındaki adresine bağlanır).
 * Fotoğraf b64 yerine src taşıyabilir: sunucudaki katalog görseli (/uploads/…), burada JPEG'e çevrilir
 * (AVIF/WebP e-posta istemcilerinin çoğunda görünmüyor) ve 4:5 tuvale, köşe renginde boşlukla oturtulur.
 * Fotoğraflar uploads/msg/<rastgele 24 hane>/ altına yazılır (dizin listesi kapalı, adres tahmin edilemez); her biri
 * tam boy + 520 piksellik küçük kopya. Kütükte adres maskeli, metin basılmaz.
 */
ini_set('display_errors', 'stderr'); error_reporting(E_ALL);
[$self, $mode, $arg1] = array_pad($argv, 3, '');
$arg2 = $argv[3] ?? '';
$root = getenv('VESTRA_ROOT') ?: (getenv('HOME').'/public_html');
require_once $root.'/inc/products.php';
require_once $root.'/inc/notify.php';

function pl_open(string $env, string $keyFile): array {
    $dec = fn(string $s): string => (string)base64_decode(strtr($s, '!$%&#,;?@~', '0123456789'), true);
    $parts = explode('.', trim($env));
    if (count($parts) !== 3) throw new RuntimeException('paket bicimi bozuk');
    $priv = openssl_pkey_get_private((string)@file_get_contents($keyFile));
    if (!$priv) throw new RuntimeException('ozel anahtar okunamadi');
    $aes = '';
    if (!openssl_private_decrypt($dec($parts[0]), $aes, $priv, OPENSSL_PKCS1_OAEP_PADDING)) throw new RuntimeException('anahtar acilamadi');
    $json = openssl_decrypt($dec($parts[2]), 'aes-256-cbc', $aes, OPENSSL_RAW_DATA, $dec($parts[1]));
    $d = is_string($json) ? json_decode($json, true) : null;
    if (!is_array($d)) throw new RuntimeException('govde cozulemedi');
    return $d;
}

/** Ana blok (vestra_html_email'in 'html' seçeneği) + düz metin. $url(name, thumb?) fotoğrafın adresini verir. */
function pl_render(array $s, callable $url): array {
    $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $p = 'margin:0 0 16px;line-height:1.65;color:#3a3428;font-size:15px';
    $h = '<div style="font-size:11px;letter-spacing:.22em;text-transform:uppercase;color:#8a6d1f;font-weight:700;margin:2px 0 10px">'.$e($s['kicker'] ?? '').'</div>'
       .'<div style="font-size:27px;line-height:1.2;color:#14110c;margin:0 0 6px">'.$e($s['title'] ?? '').'</div>'
       .'<div style="font-size:13px;color:#8a8272;letter-spacing:.04em;margin:0 0 22px">'.$e($s['subtitle'] ?? '').'</div>'
       .'<p style="'.$p.'">'.$e($s['greeting'] ?? '').'</p>';
    foreach ((array)($s['intro'] ?? []) as $t) $h .= '<p style="'.$p.'">'.$e($t).'</p>';
    if (!empty($s['chips'])) {
        /* Satır içi blok: dar ekranda alt satıra geçer (tablo hücresi geçmiyor, telefonda taşıyordu). */
        $c = '';
        foreach ((array)$s['chips'] as $t) $c .= '<span style="display:inline-block;margin:0 2px 7px 0;border:1px solid #e1d5bd;background:#faf6ee;color:#5c5449;border-radius:999px;padding:5px 12px;font-size:12px;line-height:1.3;white-space:nowrap">'.$e($t).'</span> ';
        $h .= '<div style="margin:0 0 14px">'.$c.'</div>';
    }
    $plain = [(string)($s['greeting'] ?? ''), ...array_map('strval', (array)($s['intro'] ?? []))];
    foreach ((array)($s['videos'] ?? []) as $v) {
        /* Kapak tam genislik, videonun adresine bagli; altinda baglanti metni. */
        $h .= '<div style="margin:6px 0 18px">'
            .'<a href="'.$e($url($v['name'], false)).'" style="text-decoration:none">'
            .'<img src="'.$e($url($v['poster'], false)).'" width="520" alt="'.$e($v['caption'] ?? 'Video').'" style="display:block;width:100%;max-width:520px;height:auto;border:1px solid #e6e0d5;border-radius:12px"></a>'
            .'<div style="margin:8px 2px 0"><a href="'.$e($url($v['name'], false)).'" style="font-size:13px;color:#8a6d1f;font-weight:700;text-decoration:none">&#9654;&nbsp;'.$e($v['caption'] ?? 'Video').'</a></div></div>';
        $plain[] = (string)($v['caption'] ?? 'Video').': '.$url($v['name'], false);
    }
    if (!empty($s['summary'])) {
        /* Fiyat özeti: ürün adı + kod solda, fiyat sağda. */
        $r = '';
        foreach ((array)$s['summary'] as $i => $row) {
            [$nm, $code, $pr] = array_pad(array_values((array)$row), 3, '');
            $r .= '<tr><td style="padding:9px 0;'.($i ? 'border-top:1px solid #efe6d4;' : '').'font-size:14px;color:#14110c;line-height:1.35">'.$e($nm)
                .($code !== '' ? '<div style="font-size:11.5px;color:#8a8272;letter-spacing:.03em">'.$e($code).'</div>' : '').'</td>'
                .'<td align="right" valign="middle" style="padding:9px 0 9px 12px;'.($i ? 'border-top:1px solid #efe6d4;' : '').'font-size:17px;color:#8a6d1f;font-weight:700;white-space:nowrap">'.$e($pr).'</td></tr>';
            $plain[] = '  · '.$nm.($code !== '' ? ' ('.$code.')' : '').': '.$pr;
        }
        $h .= '<div style="background:#faf6ee;border:1px solid #efe6d4;border-radius:10px;padding:12px 16px 6px;margin:4px 0 18px">'
            .'<div style="font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#8a6d1f;font-weight:700;margin:0 0 4px">'.$e($s['summary_title'] ?? '').'</div>'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse">'.$r.'</table></div>';
        array_splice($plain, count($plain) - count((array)$s['summary']), 0, [(string)($s['summary_title'] ?? '')]);
    }
    $hintTop = ($s['tap_hint_pos'] ?? '') === 'top' && !empty($s['tap_hint']);
    if ($hintTop) $h .= '<div style="font-size:12px;color:#8a8272;font-style:italic;margin:0 0 4px">'.$e($s['tap_hint']).'</div>';
    foreach ((array)($s['sections'] ?? []) as $sec) {
        $sw = (string)($sec['swatch'] ?? (empty($sec['colors']) ? '#ccc' : ''));
        $h .= '<div style="border-top:1px solid #e6e0d5;margin:8px 0 0;padding:20px 0 4px">'
            .'<table role="presentation"'.(!empty($sec['price']) ? ' width="100%"' : '').' cellpadding="0" cellspacing="0" border="0"><tr>'
            .($sw !== '' || empty($sec['colors']) ? '<td valign="middle" style="padding-right:10px;line-height:0"><div style="width:16px;height:16px;border-radius:50%;background:'.$e($sw).';border:1px solid rgba(0,0,0,.15)"></div></td>' : '')
            .'<td valign="middle" style="font-size:17px;color:#14110c;font-weight:700;letter-spacing:.01em">'.$e($sec['title'] ?? '').'</td>'
            .(!empty($sec['price']) ? '<td align="right" valign="middle" style="padding-left:12px;white-space:nowrap"><span style="font-size:19px;color:#8a6d1f;font-weight:700">'.$e($sec['price']).'</span>'
                .(!empty($sec['price_unit']) ? '<span style="font-size:11.5px;color:#8a8272"> '.$e($sec['price_unit']).'</span>' : '').'</td>' : '')
            .'</tr></table>'
            .'<div style="font-size:12px;color:#8a8272;margin:6px 0 14px '.($sw !== '' || empty($sec['colors']) ? '26px' : '0').'">'.$e($sec['sub'] ?? '').'</div>';
        $plain[] = (string)($sec['title'] ?? '').' — '.(string)($sec['sub'] ?? '')
            .(!empty($sec['price']) ? "\n  ".$sec['price'].(!empty($sec['price_unit']) ? ' '.$sec['price_unit'] : '') : '');
        if (!empty($sec['colors'])) {
            /* Renk çipleri: nokta + ad, satır içi blok (dar ekranda alt satıra geçer). */
            $c = '';
            /* Outlook (Word motoru) boş span'a genişlik vermiyor: orada nokta yerine renkli ● basılır. Çipler arasındaki
               boşluk, satır içi blok kenar boşluğunu yok sayan istemcide adların birbirine yapışmasını önler. */
            foreach ((array)$sec['colors'] as $co) $c .= '<span style="display:inline-block;margin:0 2px 7px 0;border:1px solid #e6e0d5;background:#fff;border-radius:999px;padding:4px 11px 4px 6px;font-size:12px;line-height:1.3;color:#3a3428;white-space:nowrap">'
                .'<!--[if mso]><span style="color:'.$e($co['css'] ?? '#ccc').'">&#9679;</span>&nbsp;<![endif]--><!--[if !mso]><!--><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:'.$e($co['css'] ?? '#ccc').';border:1px solid rgba(0,0,0,.18);vertical-align:-2px;margin-right:6px"></span><!--<![endif]-->'.$e($co['name'] ?? '').'</span> ';
            $h .= '<div style="margin:0 0 10px">'.$c.'</div>';
            $plain[] = '  '.implode(' · ', array_map(fn($co) => (string)($co['name'] ?? ''), (array)$sec['colors']));
        }
        if (!empty($sec['facts']) || !empty($sec['sizes'])) {
            $f = '';
            foreach ((array)($sec['facts'] ?? []) as $row) {
                [$k, $v] = array_pad(array_values((array)$row), 2, '');
                $f .= '<tr><td valign="top" style="padding:5px 12px 5px 0;font-size:12px;color:#8a8272;white-space:nowrap">'.$e($k).'</td>'
                    .'<td valign="top" style="padding:5px 0;font-size:13.5px;color:#14110c;line-height:1.45">'.$e($v).'</td></tr>';
                $plain[] = '  '.$k.': '.$v;
            }
            $box = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse">'.$f.'</table>';
            if (!empty($sec['sizes'])) {
                /* Karton dökümü: üst satır beden, alt satır adet, sonda toplam. */
                $hd = ''; $qt = ''; $tot = 0;
                foreach ((array)$sec['sizes'] as $row) {
                    [$sz, $q] = array_pad(array_values((array)$row), 2, 0); $tot += (int)$q;
                    $hd .= '<td align="center" style="padding:6px 0;font-size:11px;letter-spacing:.06em;color:#8a8272;border-bottom:1px solid #e6e0d5">'.$e($sz).'</td>';
                    $qt .= '<td align="center" style="padding:7px 0;font-size:15px;color:#14110c;font-weight:700">'.$e($q).'</td>';
                }
                $hd .= '<td align="center" style="padding:6px 0;font-size:11px;letter-spacing:.06em;color:#8a6d1f;border-bottom:1px solid #e6e0d5;border-left:1px solid #e6e0d5">'.$e($sec['sizes_total_label'] ?? 'TOT').'</td>';
                $qt .= '<td align="center" style="padding:7px 0;font-size:15px;color:#8a6d1f;font-weight:700;border-left:1px solid #e6e0d5">'.$tot.'</td>';
                $box .= '<div style="font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#8a6d1f;font-weight:700;margin:12px 0 6px">'.$e($sec['sizes_title'] ?? '').'</div>'
                      .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;table-layout:fixed;background:#fff;border:1px solid #e6e0d5;border-radius:8px"><tr>'.$hd.'</tr><tr>'.$qt.'</tr></table>';
                $plain[] = '  '.(string)($sec['sizes_title'] ?? '').': '.implode(' · ', array_map(fn($r) => implode(' ', array_values((array)$r)), (array)$sec['sizes'])).' = '.$tot;
            }
            $h .= '<div style="background:#faf6ee;border:1px solid #efe6d4;border-radius:10px;padding:12px 14px;margin:0 0 16px">'.$box.'</div>';
        }
        $cols = (int)($sec['cols'] ?? 2) === 3 ? 3 : 2; $pct = $cols === 3 ? '33%' : '50%'; $tw = $cols === 3 ? 160 : 240;
        $rows = '';
        foreach (array_chunk((array)($sec['photos'] ?? []), $cols) as $pair) {
            $tds = '';
            foreach ($pair as $ph) {
                $tds .= '<td valign="top" width="'.$pct.'" style="padding:0 5px 14px">'
                      .'<a href="'.$e($url($ph['name'], false)).'" style="text-decoration:none">'
                      .'<img src="'.$e($url($ph['name'], true)).'" width="'.$tw.'" alt="'.$e($ph['caption'] ?? '').'" style="display:block;width:100%;max-width:'.$tw.'px;height:auto;border:1px solid #e6e0d5;border-radius:10px"></a>'
                      .'<div style="font-size:11.5px;line-height:1.45;color:#5c5449;margin:7px 2px 0">'.$e($ph['caption'] ?? '').'</div></td>';
            }
            for ($i = count($pair); $i < $cols; $i++) $tds .= '<td width="'.$pct.'" style="padding:0 5px 14px"></td>';
            $rows .= '<tr>'.$tds.'</tr>';
        }
        $h .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse">'.$rows.'</table>';
        if (!empty($sec['link']['url'])) {
            $h .= '<div style="margin:0 0 6px"><a href="'.$e($sec['link']['url']).'" style="font-size:13px;color:#8a6d1f;font-weight:700;text-decoration:none;letter-spacing:.02em">'.$e($sec['link']['label'] ?? '').' &rarr;</a></div>';
        }
        $h .= '</div>';
        foreach ((array)($sec['photos'] ?? []) as $ph) $plain[] = '  · '.(string)($ph['caption'] ?? '').': '.$url($ph['name'], false);
        if (!empty($sec['link']['url'])) $plain[] = '  '.(string)($sec['link']['label'] ?? '').': '.(string)$sec['link']['url'];
    }
    if (!empty($s['tap_hint']) && !$hintTop) $h .= '<div style="font-size:12px;color:#8a8272;font-style:italic;text-align:center;margin:0 0 20px">'.$e($s['tap_hint']).'</div>';
    $h .= '<div style="border-top:1px solid #e6e0d5;padding-top:20px"></div>';
    foreach ((array)($s['closing'] ?? []) as $t) { $h .= '<p style="'.$p.'">'.$e($t).'</p>'; $plain[] = (string)$t; }
    if (!empty($s['sign'])) {
        $lines = array_map('strval', (array)$s['sign']);
        $h .= '<p style="margin:0 0 6px;line-height:1.55;color:#3a3428;font-size:15px">'.$e($lines[0]).'</p>'
            .'<p style="margin:0 0 4px;line-height:1.4;color:#14110c;font-size:16px;font-weight:700">'.$e($lines[1] ?? '').'</p>'
            .(isset($lines[2]) ? '<p style="margin:0 0 4px;color:#8a8272;font-size:12px;letter-spacing:.06em">'.$e($lines[2]).'</p>' : '');
        $plain[] = implode("\n", $lines);
    }
    if (!empty($s['button']['url'])) $plain[] = (string)$s['button']['label'].': '.(string)$s['button']['url'];
    $txt = implode("\n\n", array_filter($plain, fn($x) => trim($x) !== ''));
    if (!empty($s['footer'])) $txt .= "\n\n—\n".(string)$s['footer'];
    return [$h, $txt];
}

/**
 * Sunucudaki bir katalog görselini e-postaya uygun JPEG'e çevirir: 900×1125 (4:5) tuval, görsel ortada ve oranı korunarak,
 * boşluk dört köşenin ortalama rengiyle (ürün fotoğrafının kendi zemini) doldurulur. Dönüş [jpeg baytları, yöntem];
 * okunamazsa ['', neden]. Yalnız /uploads/ altındaki görseller.
 */
function pl_catalog_jpeg(string $src, string $root): array {
    if (!preg_match('#^/uploads/[A-Za-z0-9/_.-]+\.(jpe?g|png|webp|avif)$#i', $src) || str_contains($src, '..')) return ['', 'yol reddedildi'];
    $file = $root.$src;
    if (!is_file($file)) return ['', 'dosya yok'];
    $sz = @getimagesize($file);
    if ($sz && $sz[0] * $sz[1] > 40000000) return ['', 'cok buyuk '.$sz[0].'x'.$sz[1]];
    $data = (string)file_get_contents($file); $how = 'gd';
    $im = @imagecreatefromstring($data);
    if ($im === false && function_exists('imagecreatefromavif')) { $im = @imagecreatefromavif($file); $how = 'gd-avif'; }
    if ($im === false && class_exists('Imagick')) {
        try { $m = new Imagick($file); $m->setImageFormat('png'); $im = @imagecreatefromstring($m->getImageBlob()); $how = 'imagick'; } catch (Throwable $t) { $im = false; }
    }
    if ($im === false) {
        $tbase = tempnam(sys_get_temp_dir(), 'pl'); $tmp = $tbase.'.png';
        foreach (['magick', 'convert'] as $bin) {
            @exec(escapeshellcmd($bin).' '.escapeshellarg($file.'[0]').' '.escapeshellarg($tmp).' 2>/dev/null', $o, $rc);
            if ($rc === 0 && is_file($tmp) && ($im = @imagecreatefromstring((string)file_get_contents($tmp))) !== false) { $how = $bin; break; }
        }
        @unlink($tmp); @unlink($tbase);
    }
    if ($im === false) return ['', 'cozulemedi'];
    $w = imagesx($im); $h = imagesy($im);
    $W = 900; $H = 1125;
    $sum = [0, 0, 0]; $lo = [255, 255, 255]; $hi = [0, 0, 0];
    foreach ([[0, 0], [$w - 1, 0], [0, $h - 1], [$w - 1, $h - 1]] as [$x, $y]) {
        $c = imagecolorsforindex($im, imagecolorat($im, $x, $y));
        $a = $c['alpha'] / 127; /* saydam köşe beyaz zemin sayılır */
        foreach (['red', 'green', 'blue'] as $i => $k) {
            $v = (int)round($c[$k] * (1 - $a) + 255 * $a);
            $sum[$i] += $v; $lo[$i] = min($lo[$i], $v); $hi[$i] = max($hi[$i], $v);
        }
    }
    /* Düz zemini kırp, ürünü tuvalde aynı boyda göster: geniş kenar boşluklu kaynak (ör. 1436 kare) yanındakilerden
       küçük görünüyordu. YALNIZ dört köşe aynı renkse (düz zemin): degrade ya da manken çekiminde kırpma bir kenarı
       keser ve kalan kenar dolgu renginden ayrışıp kutu gibi görünür (yerelde degrade örnekle görüldü). */
    $flat = max($hi[0] - $lo[0], $hi[1] - $lo[1], $hi[2] - $lo[2]) <= 10;
    /* Eşik %2: JPEG gürültüsü (±3 seviye ≈ %1) zemin sayılır, beyaz/açık mavi kumaş (zeminden ≥ %3) sayılmaz —
       %6'da beyaz ve azzurro kapüşonlunun kolları kırpılıyordu (19 Eki, kontak sayfasında görüldü). */
    if ($flat && ($cr = @imagecropauto($im, IMG_CROP_THRESHOLD, 2.0, imagecolorat($im, 0, 0))) !== false && imagesx($cr) > 50 && imagesy($cr) > 50) { $im = $cr; $w = imagesx($im); $h = imagesy($im); }
    $cv = imagecreatetruecolor($W, $H);
    imagefill($cv, 0, 0, imagecolorallocate($cv, intdiv($sum[0], 4), intdiv($sum[1], 4), intdiv($sum[2], 4)));
    /* Kenar payı yalnız düz zeminde (dolgu zeminle aynı renk); değilse görsel tuvali tam doldurur, kutu görünmez. */
    $r = $flat ? min($W * 0.86 / $w, $H * 0.9 / $h) : min($W / $w, $H / $h); $nw = (int)round($w * $r); $nh = (int)round($h * $r);
    imagealphablending($cv, true);
    imagecopyresampled($cv, $im, intdiv($W - $nw, 2), intdiv($H - $nh, 2), 0, 0, $nw, $nh, $w, $h);
    ob_start(); imageinterlace($cv, true); imagejpeg($cv, null, 82); $jpg = (string)ob_get_clean();
    return [$jpg, "$how {$w}x{$h}"];
}

$mask = static function (string $e): string { $at = strrpos($e, '@'); return $at === false ? '***' : substr($e, 0, 1).'***'.substr($e, $at); };

if ($mode === 'render') {
    $s = json_decode((string)file_get_contents($arg1), true);
    [$html, $txt] = pl_render($s, fn($n, $t) => 'file://'.dirname(realpath($arg1)).'/out/'.$n);
    $opts = ['html' => $html] + (!empty($s['button']['url']) ? ['button' => $s['button']] : []);
    file_put_contents($arg2, vestra_html_email($txt, '', $opts));
    file_put_contents($arg2.'.txt', $txt);
    echo "yazildi: $arg2\n"; exit(0);
}
if ($mode === 'sheet') {
    $s = pl_open((string)file_get_contents($arg1), getenv('HOME').'/.vestra_inbox_key.pem');
    $tw = 180; $th = 225; $cols = 5; $cap = 34; $tiles = [];
    foreach ($s['sections'] as $si => $sec) foreach ($sec['photos'] as $ph) {
        /* Sayfa herkese açık depoya işlenir: yalnız ilan KATALOG görselleri. Operatörün yüklediği (b64), bir mektupta
           yayınlanmış (uploads/msg) ya da yalnız üyelere açık (uploads/live) fotoğraf buradan geçmez. */
        $src = (string)($ph['src'] ?? '');
        if ($src === '' || str_starts_with($src, '/uploads/msg/') || str_starts_with($src, '/uploads/live/')) { fwrite(STDERR, "sheet yalniz katalog (src) fotograflari icin\n"); exit(1); }
        [$raw, $how] = pl_catalog_jpeg($src, $root);
        $im = $raw !== '' ? @imagecreatefromstring($raw) : false;
        if ($im === false) { fwrite(STDERR, "okunamadi: ".($ph['src'] ?? $ph['name'])." ($how)\n"); exit(1); }
        $tiles[] = [$im, ($si + 1).'. '.(string)($ph['caption'] ?? ''), (string)$ph['name']];
    }
    $rows = (int)ceil(count($tiles) / $cols);
    $sh = imagecreatetruecolor($cols * ($tw + 10) + 10, $rows * ($th + $cap + 10) + 10);
    imagefill($sh, 0, 0, imagecolorallocate($sh, 255, 255, 255));
    $ink = imagecolorallocate($sh, 40, 40, 40); $grey = imagecolorallocate($sh, 140, 140, 140);
    foreach ($tiles as $i => [$im, $c1, $c2]) {
        $x = 10 + ($i % $cols) * ($tw + 10); $y = 10 + intdiv($i, $cols) * ($th + $cap + 10);
        imagecopyresampled($sh, $im, $x, $y, 0, 0, $tw, $th, imagesx($im), imagesy($im));
        imagerectangle($sh, $x, $y, $x + $tw - 1, $y + $th - 1, $grey);
        /* GD'nin gömülü yazı tipi yalnız ASCII basar. */
        imagestring($sh, 3, $x, $y + $th + 3, iconv('UTF-8', 'ASCII//TRANSLIT', $c1) ?: '?', $ink);
        imagestring($sh, 2, $x, $y + $th + 18, $c2, $grey);
    }
    imagejpeg($sh, $arg2, 80);
    echo "kontak sayfasi: ".count($tiles)." fotograf, ".round(filesize($arg2) / 1024)." KB — hicbir sey yayinlanmadi, gonderilmedi\n";
    exit(0);
}
if (!in_array($mode, ['check', 'send'], true) || !is_readable($arg1)) { fwrite(STDERR, "kullanim: check|send|sheet <paket>\n"); exit(2); }

$s = pl_open((string)file_get_contents($arg1), getenv('HOME').'/.vestra_inbox_key.pem');
$to = trim((string)($s['to'] ?? ''));
/* to=order:<ref> (21 Eki 2026, Ash Vintage / VES-8D231E8D): adres pakete hic girmez, SIPARIS KAYDINDAN cozulur. */
if (preg_match('/^order:([A-Za-z0-9_-]+)$/', $to, $om)) {
    $to = '';
    foreach (vestra_read_csv('orders.csv') as $r) if (($r['ref'] ?? '') === $om[1]) { $to = trim((string)($r['email'] ?? '')); break; }
    if ($to === '') { fwrite(STDERR, "siparis bulunamadi ya da adresi yok: {$om[1]}\n"); exit(1); }
    echo "alici siparisten: {$om[1]}\n";
}
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { fwrite(STDERR, "alici adresi gecersiz\n"); exit(1); }
/* Hitaptaki {name}: alicinin ADI sunucudaki hesabindan (ilk kelime) -- ad pakete de girmez. Hesap ya da ad yoksa
   "Bonjour," gibi adsiz hitap kalir. */
/* OPERATOR KOPYASI (28 Eki 2026, Il Mercatista / VES-EC6D6B0D: "[COPY]" mektubu operatorun adresine gitti, hitap
   operatorun KENDI hesabindan "Dear Serdar" cozuldu -- operator: "benim adimi yazma"). name_from=order:<ref> hitaptaki
   adi o siparisin alicisinin hesabindan cozer (kopya musteriye gidenin birebir aynisi olur); konusu [COPY] ile
   baslayan bir paket name_from tasimiyorsa DURUR -- alicinin hesabindan ad cozulmez. */
$nameAddr = $to;
if (preg_match('/^order:([A-Za-z0-9_-]+)$/', trim((string)($s['name_from'] ?? '')), $nm2)) {
    $nameAddr = '';
    foreach (vestra_read_csv('orders.csv') as $r) if (($r['ref'] ?? '') === $nm2[1]) { $nameAddr = trim((string)($r['email'] ?? '')); break; }
    if ($nameAddr === '') { fwrite(STDERR, "name_from siparisi bulunamadi: {$nm2[1]}\n"); exit(1); }
    echo "hitap adi siparisten: {$nm2[1]}\n";
} elseif (str_starts_with(ltrim((string)($s['subject'] ?? '')), '[COPY]') && str_contains((string)($s['greeting'] ?? ''), '{name}')) {
    fwrite(STDERR, "[COPY] paketinde {name} var ama name_from=order:<ref> yok -- hitap operatorun adiyla cozulurdu\n"); exit(1);
}
if (str_contains((string)($s['greeting'] ?? ''), '{name}')) {
    require_once $root.'/inc/auth.php';
    $acc = auth_find($nameAddr); $nm = trim((string)($acc['name'] ?? ''));
    $first = $nm !== '' && preg_match('/^\p{L}[\p{L}\'-]{1,}$/u', explode(' ', $nm)[0]) ? explode(' ', $nm)[0] : '';
    $s['greeting'] = $first !== '' ? str_replace('{name}', mb_convert_case(mb_strtolower($first), MB_CASE_TITLE), $s['greeting']) : str_replace(' {name}', '', $s['greeting']);
    echo "hitap: ".($first !== '' ? 'hesaptaki ad' : 'adsiz')."\n";
}
$token = bin2hex(random_bytes(12));
$dir = $root.'/uploads/msg/'.$token; $base = 'https://vestrasales.com/uploads/msg/'.$token.'/';
if (!@mkdir($dir, 0755, true)) { fwrite(STDERR, "dizin acilamadi\n"); exit(1); }
@file_put_contents($root.'/uploads/msg/index.html', '');
@file_put_contents($dir.'/index.html', '');
$n = 0; $bytes = 0;
$cleanup = function () use ($dir): void { array_map('unlink', glob($dir.'/*') ?: []); @rmdir($dir); };
foreach ($s['sections'] as &$sec) {
    foreach ($sec['photos'] as &$ph) {
        $name = preg_replace('/[^a-z0-9._-]/', '', strtolower((string)$ph['name']));
        if (isset($ph['src'])) {
            [$raw, $how] = pl_catalog_jpeg((string)$ph['src'], $root);
            if ($raw === '') { fwrite(STDERR, "katalog gorseli okunamadi: ".$ph['src']." ($how)\n"); $cleanup(); exit(1); }
            echo "  ".$ph['src']." -> $name ($how)\n";
        } else $raw = base64_decode((string)($ph['b64'] ?? ''), true);
        if ($name === '' || !str_ends_with($name, '.jpg') || $raw === false || @imagecreatefromstring($raw) === false) { fwrite(STDERR, "fotograf bozuk: $name\n"); $cleanup(); exit(1); }
        file_put_contents($dir.'/'.$name, $raw); $bytes += strlen($raw);
        $im = imagecreatefromstring($raw); $w = imagesx($im); $hh = imagesy($im); $tw = 520; $th = (int)round($hh * $tw / $w);
        $t = imagecreatetruecolor($tw, $th); imagecopyresampled($t, $im, 0, 0, 0, 0, $tw, $th, $w, $hh);
        imagejpeg($t, $dir.'/t-'.$name, 82);
        $ph['name'] = $name; unset($ph['b64'], $ph['src']); $n++;
    }
    unset($ph);
}
unset($sec);
$nv = 0;
foreach ((array)($s['videos'] ?? []) as $vi => $v) {
    $vn = preg_replace('/[^a-z0-9._-]/', '', strtolower((string)($v['name'] ?? '')));
    $pn = preg_replace('/[^a-z0-9._-]/', '', strtolower((string)($v['poster'] ?? '')));
    $raw = base64_decode((string)($v['b64'] ?? ''), true);
    $praw = base64_decode((string)($v['poster_b64'] ?? ''), true);
    /* MP4 'ftyp' kutusuyla baslar (4. bayttan itibaren); kapak gecerli bir gorsel olmali. */
    if ($vn === '' || !str_ends_with($vn, '.mp4') || $raw === false || substr($raw, 4, 4) !== 'ftyp'
        || $pn === '' || !str_ends_with($pn, '.jpg') || $praw === false || @imagecreatefromstring($praw) === false) {
        fwrite(STDERR, "video ya da kapagi bozuk: $vn\n"); $cleanup(); exit(1);
    }
    file_put_contents($dir.'/'.$vn, $raw); file_put_contents($dir.'/'.$pn, $praw); $bytes += strlen($raw) + strlen($praw);
    $s['videos'][$vi]['name'] = $vn; $s['videos'][$vi]['poster'] = $pn; unset($s['videos'][$vi]['b64'], $s['videos'][$vi]['poster_b64']); $nv++;
}
echo "fotograf: $n".($nv ? " + video: $nv" : '')." (".round($bytes / 1048576, 2)." MB) + kucuk kopyalar -> uploads/msg/".substr($token, 0, 4)."…/\n";
$url = fn($nm, $thumb) => $base.($thumb ? 't-' : '').$nm;
/* Yayında mı? Mektup gitmeden önce her adres 200 dönmeli; dönmeyen varsa mektup gitmez. */
$bad = 0;
foreach ($s['sections'] as $sec) foreach ($sec['photos'] as $ph) foreach ([true, false] as $th) {
    $ch = curl_init($url($ph['name'], $th));
    curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $ct = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE); curl_close($ch);
    if ($code !== 200 || !str_starts_with($ct, 'image/')) { $bad++; echo "  YAYINDA DEGIL: ".($th ? 't-' : '').$ph['name']." ($code $ct)\n"; }
}
foreach ((array)($s['videos'] ?? []) as $v) foreach ([[$v['name'], 'video/'], [$v['poster'], 'image/']] as [$fn, $want]) {
    $ch = curl_init($url($fn, false));
    curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $ct = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE); curl_close($ch);
    if ($code !== 200 || !str_starts_with($ct, $want)) { $bad++; echo "  YAYINDA DEGIL: $fn ($code $ct)\n"; }
    else echo "  $fn: $code $ct\n";
}
echo $bad ? "adres yoklamasi: $bad sorun\n" : "adres yoklamasi: hepsi 200 (gorseller image/*, video video/*)\n";
/* Düğme canlı bir sayfaya gitmeli; açılmıyorsa düğme mektuptan çıkar (fotoğraflar mektubun asıl işi). */
if (!empty($s['button']['url'])) {
    $ch = curl_init((string)$s['button']['url']);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => false]);
    curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    echo "dugme sayfasi: HTTP $code".($code === 200 ? '' : ' — dugme mektuptan CIKARILDI')."\n";
    if ($code !== 200) unset($s['button']);
}
foreach ($s['sections'] as $i => $sec) if (!empty($sec['link']['url'])) {
    $ch = curl_init((string)$sec['link']['url']);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => false]);
    curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    /* Alici paneli (buyer?tab=…) girissiz istekte giris sayfasina yonlendirir: sitenin kendi sayfasinda 3xx
       beklenen cevap, baglanti dogru (alici giris yapinca acilir). */
    $okLink = $code === 200 || ($code >= 300 && $code < 400 && str_starts_with((string)$sec['link']['url'], 'https://vestrasales.com/'));
    echo "bolum baglantisi ".($i + 1).": HTTP $code".($okLink ? ($code === 200 ? '' : ' (giris sayfasina yonlendirme -- beklenen)') : ' — baglanti CIKARILDI')."\n";
    if (!$okLink) unset($s['sections'][$i]['link']);
}
[$html, $txt] = pl_render($s, $url);
$opts = ['html' => $html] + (!empty($s['button']['url']) ? ['button' => $s['button']] : []);
/* EKLER: yalniz PDF (b64, %PDF- ile baslar, <= 8 MB). Gecici dosya kosu sonunda silinir. */
$attTmp = [];
foreach ((array)($s['attachments'] ?? []) as $at) {
    $an = preg_replace('/[^A-Za-z0-9._-]/', '', (string)($at['name'] ?? ''));
    $ar = base64_decode((string)($at['b64'] ?? ''), true);
    if ($an === '' || !str_ends_with(strtolower($an), '.pdf') || $ar === false || strncmp($ar, '%PDF-', 5) !== 0 || strlen($ar) > 8 * 1048576) {
        fwrite(STDERR, "ek bozuk: $an\n"); $cleanup(); exit(1);
    }
    $tp = sys_get_temp_dir().'/pl-att-'.bin2hex(random_bytes(6)).'.pdf'; file_put_contents($tp, $ar); $attTmp[] = $tp;
    $opts['attachments'][] = ['name' => $an, 'path' => $tp];
    echo "ek: $an (".round(strlen($ar) / 1024)." KB)\n";
}
register_shutdown_function(static function () use ($attTmp) { foreach ($attTmp as $f) @unlink($f); });
echo "alici: ".$mask($to)." · gonderen adi: ".($s['from_name'] ?? '')." · konu: ".($s['subject'] ?? '')."\n";
if ($mode === 'check' || $bad) {
    /* Kuru koşuda yayımlanan fotoğraflar geri alınır — adres zaten kimseye gitmedi. */
    array_map('unlink', glob($dir.'/*') ?: []); @rmdir($dir);
    echo $bad ? "GONDERILMEDI (adres sorunu)\n" : "KURU KOSU — gonderilmedi, fotograflar geri alindi\n";
    exit($bad ? 1 : 0);
}
$ok = vestra_send_mail($to, (string)$s['subject'], $txt, (string)($s['reply_to'] ?? 'support@vestrasales.com'), (string)($s['from_name'] ?? 'VESTRA'), null, '', $opts);
echo $ok ? "GONDERILDI".(!empty($GLOBALS['vestra_api_last_message_id']) ? ' (saglayici kimligi var)' : '')."\n"
         : "GONDERILEMEDI — ".json_encode($GLOBALS['vestra_api_last_error'] ?? null)."\n";
exit($ok ? 0 : 1);
