<?php
/**
 * Yönetim panelinin iskeleti
 * ==========================
 * Operatör, 14 Eki 2026: "Vestra gibi bir admin kur: IP adresleri,
 * istatistik, kullanıcılar, siparişler, her ayrıntı, konforlu."
 *
 * Panel vitrinden ayrı bir kabuk: sol kenarda bölümlü menü, üstte arama ve
 * canlı ziyaretçi sayısı, içerikte kartlar ve tablolar. Vitrinin başlığı,
 * altlığı, uygulama çubuğu burada yok. Metinler ALMANCA (CLI araçları ve
 * hukuk metinleri gibi; operatörün dili).
 *
 * Her sayfa:  vr_admin_head('Başlık', ['sub' => '…']);  …  vr_admin_foot();
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/stats.php';

// Panel Almanca: tarih/para biçimi de (vr_date) Almanca olsun, ziyaretçinin
// ülkesine göre seçilen dil paneli etkilemesin.
$GLOBALS['vr_lang'] = 'de';

/** Menü: bölüm ⇒ [dosya ⇒ [etiket, ikon]]. Rozetler vr_admin_badges() ile. */
function vr_admin_menu(): array
{
    return [
        'Überblick' => [
            'index.php'     => ['Dashboard', 'home'],
            'stats.php'     => ['Statistik', 'chart'],
            'visitors.php'  => ['Besucher & IPs', 'globe'],
        ],
        'Verkauf' => [
            'orders.php'    => ['Bestellungen', 'bag'],
            'customers.php' => ['Kunden', 'users'],
            'messages.php'  => ['Nachrichten', 'mail'],
            'vouchers.php'  => ['Gutscheine', 'tag'],
        ],
        'Katalog' => [
            'products.php'  => ['Artikel', 'box'],
            'deal.php'      => ['48h-Aktion', 'bolt'],
            'vault.php'     => ['Vault', 'clock'],
            'listings.php'  => ['Angebote', 'list'],
            'sellers.php'   => ['Verkäufer', 'store'],
        ],
        'Marketing' => [
            'members.php'   => ['Newsletter', 'news'],
            'push.php'      => ['Push', 'bell'],
        ],
        'System' => [
            'logs.php'      => ['Protokoll', 'log'],
            'security.php'  => ['Sicherheit', 'shield'],
            'settings.php'  => ['Einstellungen', 'cog'],
            'system.php'    => ['Systemstatus', 'pulse'],
        ],
    ];
}

/** Menü rozetleri: bekleyen iş sayıları (sayfa adı ⇒ sayı). */
function vr_admin_badges(): array
{
    static $b = null;
    if ($b !== null) return $b;
    $b = [];
    require_once __DIR__ . '/../inc/listings.php';
    $open = 0;
    foreach (vr_orders() as $o) if ((string)($o['status'] ?? '') === 'paid' && empty($o['shipped_at'])) $open++;
    if ($open) $b['orders.php'] = $open;
    $rev = 0;
    foreach (vr_listings_all() as $r) if ((string)($r['status'] ?? 'review') === 'review') $rev++;
    if ($rev) $b['listings.php'] = $rev;
    $unread = 0;
    foreach ((array)vr_store_read('retail-messages.json', []) as $m) if (is_array($m) && empty($m['done'])) $unread++;
    foreach ((array)vr_store_read('retail-private-requests.json', []) as $m) if (is_array($m) && empty($m['done'])) $unread++;
    if ($unread) $b['messages.php'] = $unread;
    return $b;
}

/** Panel ikonları (çizgi, 24 kutu). Vitrinin vr_icon'una dokunmuyoruz. */
function vr_aicon(string $n, int $s = 18): string
{
    static $p = [
        'home'  => '<path d="M4 10.5L12 4l8 6.5"/><path d="M6 9v11h12V9"/>',
        'chart' => '<path d="M4 20h16"/><path d="M7 16V9"/><path d="M12 16V5"/><path d="M17 16v-6"/>',
        'globe' => '<circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17"/><path d="M12 3.5c3 3 3 14 0 17"/><path d="M12 3.5c-3 3-3 14 0 17"/>',
        'bag'   => '<path d="M6 8h12l-1 12H7L6 8z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
        'users' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-5 6-5s6 1.7 6 5"/><circle cx="17" cy="9" r="2.4"/><path d="M16 14.5c2.8.2 5 1.8 5 4.5"/>',
        'mail'  => '<rect x="3" y="5.5" width="18" height="13" rx="1.5"/><path d="M3.5 7l8.5 6 8.5-6"/>',
        'tag'   => '<path d="M4 12.5L12.5 4H20v7.5L11.5 20 4 12.5z"/><circle cx="16" cy="8" r="1.3"/>',
        'box'   => '<path d="M4 8l8-4 8 4v8l-8 4-8-4z"/><path d="M4 8l8 4 8-4"/><path d="M12 12v8"/>',
        'bolt'  => '<path d="M13 3L5 13h6l-1 8 8-10h-6l1-8z"/>',
        'clock' => '<circle cx="12" cy="12" r="8"/><path d="M12 8v4.4l3 1.8"/>',
        'list'  => '<path d="M8 6h12M8 12h12M8 18h12"/><circle cx="4.5" cy="6" r="1"/><circle cx="4.5" cy="12" r="1"/><circle cx="4.5" cy="18" r="1"/>',
        'store' => '<path d="M4 9l1.5-5h13L20 9"/><path d="M4 9v11h16V9"/><path d="M4 9c0 1.5 1.3 2.5 2.7 2.5S9.3 10.5 9.3 9c0 1.5 1.2 2.5 2.7 2.5s2.7-1 2.7-2.5c0 1.5 1.2 2.5 2.6 2.5S20 10.5 20 9"/><path d="M10 20v-6h4v6"/>',
        'news'  => '<rect x="4" y="5" width="16" height="14" rx="1.5"/><path d="M8 9h8M8 12.5h8M8 16h5"/>',
        'bell'  => '<path d="M6.5 16.5V11a5.5 5.5 0 0 1 11 0v5.5l1.5 1.5H5z"/><path d="M10 20.5a2.2 2.2 0 0 0 4 0"/>',
        'log'   => '<path d="M7 3h7l4 4v14H7z"/><path d="M14 3v4h4"/><path d="M10 12h6M10 16h6"/>',
        'shield'=> '<path d="M12 3l7 3v6c0 4-3 7.4-7 9-4-1.6-7-5-7-9V6l7-3z"/><path d="M9 12l2.2 2.2L15.5 10"/>',
        'cog'   => '<circle cx="12" cy="12" r="3"/><path d="M12 3v2.5M12 18.5V21M3 12h2.5M18.5 12H21M5.6 5.6l1.8 1.8M16.6 16.6l1.8 1.8M5.6 18.4l1.8-1.8M16.6 7.4l1.8-1.8"/>',
        'pulse' => '<path d="M3 12h4l2.5-6 4 12 2.5-6h5"/>',
        'search'=> '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
        'menu'  => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'out'   => '<path d="M10 4H5v16h5"/><path d="M14 8l4 4-4 4"/><path d="M18 12H9"/>',
        'arrow' => '<path d="M5 12h13"/><path d="M13 6l6 6-6 6"/>',
        'check' => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        'x'     => '<path d="M6 6l12 12M18 6L6 18"/>',
        'eye'   => '<path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"/><circle cx="12" cy="12" r="2.8"/>',
        'down'  => '<path d="M12 4v13"/><path d="M6 11l6 6 6-6"/><path d="M5 20h14"/>',
        'ban'   => '<circle cx="12" cy="12" r="8.5"/><path d="M6 6l12 12"/>',
        'pin'   => '<path d="M12 21s-6-5.3-6-10a6 6 0 0 1 12 0c0 4.7-6 10-6 10z"/><circle cx="12" cy="11" r="2.2"/>',
        'img'   => '<rect x="3.5" y="5" width="17" height="14" rx="1.5"/><circle cx="9" cy="10" r="1.6"/><path d="M4 18l5-5 3 3 3-4 5 6"/>',
        'truck' => '<path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.7"/><circle cx="17" cy="18" r="1.7"/>',
        'euro'  => '<path d="M18 6.5A7 7 0 0 0 7.3 9H5v1.5h1.7a7 7 0 0 0 0 3H5V15h2.3A7 7 0 0 0 18 17.5"/><path d="M6 10.5h9M6 13.5h8"/>',
    ];
    return '<svg class="ai" width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$n] ?? '') . '</svg>';
}

/**
 * Sayfa başı. $o: sub (alt başlık), wide (geniş içerik), actions (HTML, sağ
 * üstte düğmeler), back (geri bağlantısı [url, etiket]).
 */
function vr_admin_head(string $title, array $o = []): void
{
    $who  = vr_admin_require();
    $cur  = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $live = count(vr_stats_live(300));
    $bad  = vr_admin_badges();
    header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= h($title) ?> · Verwaltung · <?= h((string)vr_config('brand')) ?></title>
<meta name="robots" content="noindex,nofollow">
<link rel="icon" href="<?= h(vr_url('assets/mark.php')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= h(vr_url('assets/css/maxsales.css', ['v' => vr_asset_v()])) ?>">
<link rel="stylesheet" href="<?= h(vr_url('assets/css/admin.css', ['v' => vr_asset_v()])) ?>">
</head>
<body class="adm">
<a class="skip" href="#amain">Zum Inhalt</a>
<div class="adm__shell">
  <aside class="adm__side" id="adm-side">
    <a class="adm__brand" href="<?= h(vr_url('admin/index.php')) ?>">
      <span class="adm__mark"><?= h(mb_substr((string)vr_config('brand'), 0, 1)) ?></span>
      <span><b><?= h((string)vr_config('brand')) ?></b><small>Verwaltung</small></span>
    </a>
    <nav class="adm__nav" aria-label="Verwaltung">
      <?php foreach (vr_admin_menu() as $sec => $items): ?>
        <p class="adm__sec"><?= h($sec) ?></p>
        <?php foreach ($items as $file => [$label, $ico]): ?>
          <a class="adm__a<?= $cur === $file || (isset($o['nav']) && $o['nav'] === $file) ? ' is-on' : '' ?>" href="<?= h(vr_url('admin/' . $file)) ?>">
            <?= vr_aicon($ico) ?><span><?= h($label) ?></span>
            <?php if (!empty($bad[$file])): ?><em class="adm__badge"><?= (int)$bad[$file] ?></em><?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <div class="adm__sidefoot">
      <a class="adm__a" href="<?= h(vr_url('/')) ?>" target="_blank" rel="noopener"><?= vr_aicon('eye') ?><span>Shop öffnen</span></a>
      <a class="adm__a" href="<?= h(vr_url('admin/logout.php')) ?>"><?= vr_aicon('out') ?><span>Abmelden</span></a>
      <p class="adm__who"><?= h($who) ?></p>
    </div>
  </aside>
  <div class="adm__main">
    <header class="adm__top">
      <button class="adm__burger" type="button" data-adm-menu aria-label="Menü" aria-controls="adm-side"><?= vr_aicon('menu', 22) ?></button>
      <form class="adm__search" method="get" action="<?= h(vr_url('admin/search.php')) ?>" role="search">
        <?= vr_aicon('search', 17) ?>
        <input type="search" name="q" placeholder="Bestellnummer, E-Mail, Artikel, IP …" value="<?= h((string)($_GET['q'] ?? '')) ?>" autocomplete="off">
        <kbd>/</kbd>
      </form>
      <a class="adm__live" href="<?= h(vr_url('admin/visitors.php', ['f' => 'live'])) ?>" title="Besucher in den letzten 5 Minuten">
        <i></i><b data-adm-live><?= $live ?></b><span>online</span>
      </a>
      <span class="adm__clock" data-adm-clock><?= h(date('H:i')) ?></span>
    </header>
    <main class="adm__content<?= !empty($o['wide']) ? ' adm__content--wide' : '' ?>" id="amain">
      <?php if (!empty($o['back'])): ?>
        <p class="adm__back"><a href="<?= h((string)$o['back'][0]) ?>">← <?= h((string)$o['back'][1]) ?></a></p>
      <?php endif; ?>
      <div class="adm__head">
        <div>
          <h1 class="adm__h1"><?= h($title) ?></h1>
          <?php if (!empty($o['sub'])): ?><p class="adm__sub"><?= h((string)$o['sub']) ?></p><?php endif; ?>
        </div>
        <?php if (!empty($o['actions'])): ?><div class="adm__actions"><?= $o['actions'] ?></div><?php endif; ?>
      </div>
      <?php foreach (vr_flash() as $f): ?>
        <div class="flash flash--<?= h((string)$f['type']) ?> adm__flash"><?= h((string)$f['msg']) ?></div>
      <?php endforeach; ?>
<?php
}

function vr_admin_foot(): void
{
?>
    </main>
  </div>
</div>
<script src="<?= h(vr_url('assets/js/admin.js', ['v' => vr_asset_v()])) ?>" defer></script>
</body>
</html>
<?php
}

// ------------------------------------------------------------ bileşenler
/** Sayı kutuları: [etiket => değer] ya da [etiket => [değer, altyazı, eğilim]] */
function vr_admin_stats(array $rows, int $cols = 0): void
{
    echo '<div class="akpi"' . ($cols ? ' style="--n:' . (int)$cols . '"' : '') . '>';
    foreach ($rows as $label => $v) {
        $val = is_array($v) ? (string)$v[0] : (string)$v;
        $sub = is_array($v) ? (string)($v[1] ?? '') : '';
        $tr  = is_array($v) ? ($v[2] ?? null) : null;
        echo '<div class="akpi__i"><span class="akpi__l">' . h((string)$label) . '</span><b class="akpi__v">' . h($val) . '</b>';
        if ($sub !== '' || $tr !== null) {
            echo '<small class="akpi__s">';
            if ($tr !== null) {
                $cls = $tr > 0 ? 'up' : ($tr < 0 ? 'down' : 'flat');
                echo '<i class="akpi__tr akpi__tr--' . $cls . '">' . ($tr > 0 ? '+' : '') . h((string)$tr) . ' %</i> ';
            }
            echo h($sub) . '</small>';
        }
        echo '</div>';
    }
    echo '</div>';
}

/** Kart. */
function vr_admin_card_open(string $title = '', string $right = '', string $cls = ''): void
{
    echo '<section class="acard' . ($cls !== '' ? ' ' . h($cls) : '') . '">';
    if ($title !== '' || $right !== '') echo '<header class="acard__h"><h2>' . h($title) . '</h2>' . ($right !== '' ? '<div class="acard__r">' . $right . '</div>' : '') . '</header>';
    echo '<div class="acard__b">';
}
function vr_admin_card_close(): void { echo '</div></section>'; }

/** Sekme çubuğu: [anahtar => etiket], seçili, temel URL parametreleri. */
function vr_admin_tabs(array $tabs, string $cur, string $file, string $param = 'f', array $keep = []): void
{
    echo '<nav class="atabs">';
    foreach ($tabs as $k => $label) {
        $cnt = null;
        if (is_array($label)) { [$label, $cnt] = $label; }
        echo '<a class="' . ($cur === (string)$k ? 'is-on' : '') . '" href="' . h(vr_url('admin/' . $file, $keep + [$param => $k])) . '">' . h((string)$label) . ($cnt !== null ? ' <i>' . (int)$cnt . '</i>' : '') . '</a>';
    }
    echo '</nav>';
}

/** Durum rozeti. $tone: ok | warn | bad | muted | ink */
function vr_admin_pill(string $text, string $tone = 'muted'): string
{
    return '<span class="apill apill--' . h($tone) . '">' . h($text) . '</span>';
}

/** Ülke bayrağı (emoji) + kod. */
function vr_admin_flag(string $cc): string
{
    $cc = strtoupper($cc);
    if (!preg_match('/^[A-Z]{2}$/', $cc)) return '<span class="aflag aflag--none">?</span>';
    $f = mb_chr(0x1F1E6 + ord($cc[0]) - 65) . mb_chr(0x1F1E6 + ord($cc[1]) - 65);
    return '<span class="aflag" title="' . h(vr_country_name($cc)) . '">' . $f . ' ' . h($cc) . '</span>';
}

/** Göreli zaman ("vor 3 Min."). */
function vr_admin_ago(int $ts): string
{
    if ($ts <= 0) return '—';
    $d = time() - $ts;
    if ($d < -60) {                                     // gelecek: "in 3 Std."
        $r = -$d;
        if ($r < 3600) return 'in ' . intdiv($r, 60) . ' Min.';
        if ($r < 86400) return 'in ' . intdiv($r, 3600) . ' Std.';
        return 'in ' . intdiv($r, 86400) . ' Tg.';
    }
    if ($d < 60) return 'gerade eben';
    if ($d < 3600) return 'vor ' . intdiv($d, 60) . ' Min.';
    if ($d < 86400) return 'vor ' . intdiv($d, 3600) . ' Std.';
    if ($d < 86400 * 14) return 'vor ' . intdiv($d, 86400) . ' Tg.';
    return date('d.m.Y', $ts);
}

/** Yatay çubuk listesi: [etiket => sayı]; $href(etiket) bağlantı üretir. */
function vr_admin_bars(array $rows, int $max = 10, ?callable $href = null, ?callable $label = null): void
{
    $rows = array_slice($rows, 0, $max, true);
    $top  = max(1, (int)max($rows ?: [1]));
    $tot  = max(1, array_sum($rows));
    echo '<ul class="abars">';
    foreach ($rows as $k => $n) {
        $k = (string)$k;
        $txt = $label ? $label($k) : $k;
        $open = $href ? '<a href="' . h($href($k)) . '">' : '<span>';
        $close = $href ? '</a>' : '</span>';
        echo '<li><div class="abars__t">' . $open . $txt . $close . '<b>' . number_format((int)$n, 0, ',', '.') . ' <small>' . round($n * 100 / $tot) . ' %</small></b></div>'
           . '<div class="abars__bar"><i style="width:' . round($n * 100 / $top, 1) . '%"></i></div></li>';
    }
    if (!$rows) echo '<li class="abars__empty">Noch keine Daten.</li>';
    echo '</ul>';
}

/**
 * Çizgi/alan grafiği (satır içi SVG, kütüphane yok). $series: [ad => [gün => sayı]].
 * $labels: x etiketleri (günler). $money: değerler kuruş ise.
 */
function vr_admin_chart(array $series, array $labels, array $o = []): void
{
    $W = 720; $H = (int)($o['h'] ?? 200); $pl = 44; $pr = 10; $pt = 12; $pb = 26;
    $money = !empty($o['money']);
    $all = [];
    foreach ($series as $s) foreach ($s as $v) $all[] = $v;
    $max = max(1, (int)max($all ?: [1]));
    // güzel üst sınır
    $pow = 10 ** max(0, (int)floor(log10($max)));
    $nice = (int)(ceil($max / $pow) * $pow);
    if ($nice / $pow <= 2 && $pow >= 10) $nice = (int)(ceil($max / ($pow / 5)) * ($pow / 5));
    $n = max(1, count($labels));
    $x = fn(int $i) => $pl + ($n === 1 ? ($W - $pl - $pr) / 2 : $i * ($W - $pl - $pr) / ($n - 1));
    $y = fn(float $v) => $pt + ($H - $pt - $pb) * (1 - $v / $nice);
    $fmt = fn(int $v) => $money ? number_format($v / 100, 0, ',', '.') . ' €' : number_format($v, 0, ',', '.');
    $colors = ['#0b0a09', '#c9a96e', '#8f897f', '#3f7d52'];
    echo '<figure class="achart"><svg viewBox="0 0 ' . $W . ' ' . $H . '" preserveAspectRatio="none" role="img" aria-label="' . h(implode(', ', array_keys($series))) . '">';
    for ($g = 0; $g <= 4; $g++) {
        $v = $nice * $g / 4; $yy = $y($v);
        echo '<line x1="' . $pl . '" y1="' . $yy . '" x2="' . ($W - $pr) . '" y2="' . $yy . '" class="achart__grid"/>';
        echo '<text x="' . ($pl - 6) . '" y="' . ($yy + 4) . '" class="achart__y" text-anchor="end">' . h($fmt((int)$v)) . '</text>';
    }
    $step = max(1, (int)ceil($n / 8));
    foreach ($labels as $i => $l) {
        if ($i % $step !== 0 && $i !== $n - 1) continue;
        echo '<text x="' . $x($i) . '" y="' . ($H - 8) . '" class="achart__x" text-anchor="middle">' . h((string)$l) . '</text>';
    }
    $si = 0;
    foreach ($series as $name => $s) {
        $vals = array_values($s);
        $pts = [];
        foreach ($vals as $i => $v) $pts[] = round($x($i), 1) . ',' . round($y((float)$v), 1);
        $c = $colors[$si % count($colors)];
        if ($si === 0) {
            echo '<polygon points="' . $pl . ',' . ($H - $pb) . ' ' . implode(' ', $pts) . ' ' . $x($n - 1) . ',' . ($H - $pb) . '" fill="' . $c . '" opacity=".06"/>';
        }
        echo '<polyline points="' . implode(' ', $pts) . '" fill="none" stroke="' . $c . '" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>';
        foreach ($vals as $i => $v) {
            echo '<circle cx="' . round($x($i), 1) . '" cy="' . round($y((float)$v), 1) . '" r="3.2" fill="' . $c . '" class="achart__dot"><title>' . h((string)($labels[$i] ?? '') . ' · ' . $name . ': ' . $fmt((int)$v)) . '</title></circle>';
        }
        $si++;
    }
    echo '</svg>';
    if (count($series) > 1) {
        echo '<figcaption class="achart__legend">';
        $si = 0;
        foreach (array_keys($series) as $name) { echo '<span><i style="background:' . $colors[$si % 4] . '"></i>' . h((string)$name) . '</span>'; $si++; }
        echo '</figcaption>';
    }
    echo '</figure>';
}

/** 24 saatlik dağılım çubukları. */
function vr_admin_hours(array $h): void
{
    $max = max(1, (int)max($h ?: [1]));
    echo '<div class="ahours">';
    foreach ($h as $i => $n) {
        echo '<i style="height:' . round($n * 100 / $max) . '%" title="' . sprintf('%02d:00 · %d', $i, $n) . '"></i>';
    }
    echo '</div><div class="ahours__x"><span>0</span><span>6</span><span>12</span><span>18</span><span>23 Uhr</span></div>';
}

/** Sayfalama: toplam, sayfa, sayfa boyu → [dilimlenmiş, html] */
function vr_admin_paginate(array $rows, int $per = 50): array
{
    $page = max(1, (int)($_GET['page'] ?? 1));
    $tot  = count($rows);
    $pages = max(1, (int)ceil($tot / $per));
    $page  = min($page, $pages);
    $slice = array_slice($rows, ($page - 1) * $per, $per, true);
    $html  = '';
    if ($pages > 1) {
        $html .= '<nav class="apag"><span>' . number_format($tot, 0, ',', '.') . ' Einträge · Seite ' . $page . ' / ' . $pages . '</span>';
        $q = $_GET;
        for ($p = max(1, $page - 3); $p <= min($pages, $page + 3); $p++) {
            $q['page'] = $p;
            $html .= '<a class="' . ($p === $page ? 'is-on' : '') . '" href="?' . h(http_build_query($q)) . '">' . $p . '</a>';
        }
        $html .= '</nav>';
    }
    return [$slice, $html];
}

/** CSV indir ve bitir. */
function vr_admin_csv(string $name, array $head, array $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $head, ';');
    foreach ($rows as $r) fputcsv($out, $r, ';');
    fclose($out);
    exit;
}

/** Form kutusu: etiket + alan (HTML). */
function vr_admin_field(string $label, string $inner, string $hint = ''): string
{
    return '<div class="field"><label>' . h($label) . '</label>' . $inner . ($hint !== '' ? '<small>' . h($hint) . '</small>' : '') . '</div>';
}

/** Küçük POST düğmesi (tek eylem). */
function vr_admin_button(string $action, array $hidden, string $label, string $cls = 'btn btn--ghost btn--sm', string $confirm = ''): string
{
    $h = '<form method="post" class="ainline"' . ($confirm !== '' ? ' data-confirm="' . h($confirm) . '"' : '') . '>' . vr_csrf_field() . '<input type="hidden" name="action" value="' . h($action) . '">';
    foreach ($hidden as $k => $v) $h .= '<input type="hidden" name="' . h($k) . '" value="' . h((string)$v) . '">';
    return $h . '<button class="' . h($cls) . '" type="submit"><span>' . h($label) . '</span></button></form>';
}

/**
 * Siparişi veren müşterinin kaydı (varsa) — e-posta ile.
 */
function vr_admin_customer_by_mail(string $email): ?array
{
    require_once __DIR__ . '/../inc/customers.php';
    return $email !== '' ? vr_customer_by_email($email) : null;
}

/** Ham ürün satırı (kaynak dosyalardan, salt okunur) — gizlenmiş ürünler için. */
function vr_admin_raw_product(string $id): ?array
{
    static $raw = null;
    if ($raw === null) {
        $raw = [];
        foreach (['listings.json', 'retail-imported.json', 'retail-listings.json'] as $file) {
            foreach ((array)vr_store_read($file, []) as $row) {
                if (is_array($row) && ($row['id'] ?? '') !== '' && !isset($raw[(string)$row['id']])) $raw[(string)$row['id']] = $row;
            }
        }
    }
    return $raw[$id] ?? null;
}

/** E-posta doğrulama uyarısı: panelde yok, uyumluluk için boş. */
function vr_verify_notice(array $c): void {}

/** Sipariş durumu rozeti (durum + kargo). */
function vr_admin_order_pill(array $o): string
{
    $st = (string)($o['status'] ?? '');
    if ($st === 'paid' && !empty($o['shipped_at'])) return vr_admin_pill('versendet', 'ink');
    if ($st === 'paid') return vr_admin_pill('bezahlt · offen', 'ok');
    if ($st === 'cancelled') return vr_admin_pill('storniert', 'bad');
    if ($st === 'failed') return vr_admin_pill('fehlgeschlagen', 'bad');
    if ($st === 'refunded') return vr_admin_pill('erstattet', 'warn');
    return vr_admin_pill('unbezahlt', 'muted');
}

/** Siparişin ödeme yolu: 'crypto' (OxaPay) ya da 'card' (Stripe; alan yoksa eski siparişler). */
function vr_admin_paymethod(array $o): string
{
    return (string)($o['payment_method'] ?? '') === 'crypto' ? 'crypto' : 'card';
}

/**
 * Ödeme yolu rozeti. Kriptoda ödenen coin varsa yanında (ör. "Krypto · USDT").
 * paid_crypto biçimi: "<miktar> <coin> <ağ>" (OxaPay txs[0]).
 */
function vr_admin_paym(array $o): string
{
    if (vr_admin_paymethod($o) === 'crypto') {
        $coin = preg_split('/\s+/', trim((string)($o['oxapay']['paid_crypto'] ?? '')))[1] ?? '';
        return '<span class="apm apm--crypto" title="Krypto · OxaPay">Krypto' . ($coin !== '' ? ' · ' . h($coin) : '') . '</span>';
    }
    return '<span class="apm apm--card" title="Karte · Stripe">Karte</span>';
}

/**
 * Dünya haritası — satır içi SVG, kütüphane ve dış servis yok. Ülke dolgusu
 * sayıya göre (log ölçek) kumdan altına koyulaşır; üzerine gelince ad + sayı,
 * tıklayınca $href(ISO-2). Natural Earth 110m'de görünmeyen küçük ülkeler
 * (Malta, Monaco, Singapur …) nokta olarak çizilir. $counts: [ISO-2 => sayı].
 */
function vr_admin_worldmap(array $counts, ?callable $href = null, string $unit = 'Besucher'): void
{
    static $m = null;
    if ($m === null) $m = require __DIR__ . '/../inc/worldmap-data.php';

    $c = [];
    foreach ($counts as $k => $n) {
        $k = strtoupper((string)$k);
        if (preg_match('/^[A-Z]{2}$/', $k) && (int)$n > 0) $c[$k] = (int)$n;
    }
    $max = $c ? max($c) : 0;
    // Üç duraklı renk: kum → altın → bronz. Log ölçek: bir ülke ezici çoğunlukta
    // olsa da ikinci, üçüncü ülke açık gri kalmasın.
    $stops = [[0xe2, 0xcd, 0x9f], [0xc9, 0xa9, 0x6e], [0x6e, 0x53, 0x26]];
    $fill = static function (int $n) use ($max, $stops): string {
        $t = $max > 1 ? log(1 + $n) / log(1 + $max) : 1.0;
        [$a, $b, $u] = $t < .5 ? [$stops[0], $stops[1], $t * 2] : [$stops[1], $stops[2], ($t - .5) * 2];
        $mix = fn($i) => (int)round($a[$i] + ($b[$i] - $a[$i]) * $u);
        return sprintf('#%02x%02x%02x', $mix(0), $mix(1), $mix(2));
    };
    $num = static fn(int $n): string => number_format($n, 0, ',', '.');

    echo '<figure class="amap"><svg viewBox="0 0 ' . (int)$m['w'] . ' ' . (int)$m['h'] . '" role="img" aria-label="Weltkarte: ' . h($unit) . ' nach Land">';
    foreach ($m['paths'] as $cc => $d) {
        $n = $c[$cc] ?? 0;
        $title = '<title>' . h(vr_country_name($cc)) . ($n ? ' · ' . $num($n) . ' ' . $unit : '') . '</title>';
        $path = '<path d="' . $d . '" fill="' . ($n ? $fill($n) : '#eeece7') . '">' . $title . '</path>';
        echo ($n && $href) ? '<a href="' . h($href($cc)) . '">' . $path . '</a>' : $path;
    }
    foreach ($m['dots'] as $cc => [$x, $y]) {
        $n = $c[$cc] ?? 0;
        if (!$n) continue;                                  // boş küçük ülke çizilmez
        $dot = '<circle class="amap__dot" cx="' . $x . '" cy="' . $y . '" r="4.5" fill="' . $fill($n) . '"><title>' . h(vr_country_name($cc)) . ' · ' . $num($n) . ' ' . $unit . '</title></circle>';
        echo $href ? '<a href="' . h($href($cc)) . '">' . $dot . '</a>' : $dot;
    }
    echo '</svg>';
    if ($max > 0) {
        echo '<figcaption class="amap__legend"><span>1</span><i class="amap__scale"></i><span>' . $num($max) . ' ' . h($unit) . '</span>'
           . '<span class="amap__n">' . count($c) . ' ' . (count($c) === 1 ? 'Land' : 'Länder') . '</span></figcaption>';
    } else {
        echo '<figcaption class="amap__legend"><span>Noch keine Daten.</span></figcaption>';
    }
    echo '</figure>';
}

/**
 * Canlı ziyaretçi tablosunun satırları (sayfa ve 10 sn'lik JSON akışı aynı
 * çıktıyı kullanır). $live: vr_stats_live() sonucu.
 */
function vr_admin_live_rows(array $live): string
{
    require_once __DIR__ . '/../inc/customers.php';
    $out = '';
    foreach ($live as $v) {
        $c = ($v['u'] ?? '') !== '' ? vr_customer((string)$v['u']) : null;
        $det = ($v['q']['id'] ?? '') !== '' ? ' · ' . $v['q']['id'] : (($v['q']['brand'] ?? '') !== '' ? ' · ' . $v['q']['brand'] : (($v['q']['q'] ?? '') !== '' ? ' · „' . $v['q']['q'] . '“' : ''));
        $fresh = time() - (int)$v['last'] < 60;
        $out .= '<tr' . ($fresh ? ' class="is-fresh"' : '') . '><td class="anowrap">' . ($fresh ? '<i class="alive-dot" title="in der letzten Minute aktiv"></i> ' : '')
              . '<a class="amono" href="' . h(vr_url('admin/visitors.php', ['ip' => $v['ip']])) . '">' . h((string)$v['ip']) . '</a></td>'
              . '<td class="anowrap">' . vr_admin_flag((string)$v['cc']) . '</td><td><span class="aellip" title="' . h($v['p'] . $det) . '">' . h($v['p'] . $det) . '</span></td><td class="anum">' . (int)$v['n'] . '</td>'
              . '<td>' . h(vr_admin_ago((int)$v['first'])) . '</td><td>' . h(vr_admin_ago((int)$v['last'])) . '</td><td class="asmall">' . h($v['d'] . ' · ' . $v['b']) . '</td>'
              . '<td>' . ($c ? '<a href="' . h(vr_url('admin/customer.php', ['id' => $c['id']])) . '">' . h((string)$c['email']) . '</a>' : '—') . '</td></tr>';
    }
    if (!$live) $out = '<tr><td colspan="8" class="asmall">Gerade ist niemand im Shop.</td></tr>';
    return $out;
}

/**
 * Canlı görünümün yan kartı: kişi sayısı, şu an bakılan sayfalar, cihazlar.
 * Sayfa ve 10 sn'lik akış aynı çıktıyı kullanır.
 */
function vr_admin_live_side(array $live): string
{
    $pages = []; $dev = [];
    foreach ($live as $v) {
        $p = (string)($v['p'] ?? '');
        $pages[$p] = ($pages[$p] ?? 0) + 1;
        $d = ['phone' => 'Telefon', 'tablet' => 'Tablet', 'desktop' => 'Desktop'][(string)($v['d'] ?? '')] ?? (string)($v['d'] ?? '');
        $dev[$d] = ($dev[$d] ?? 0) + 1;
    }
    arsort($pages); arsort($dev);
    ob_start();
    echo '<p class="abig">' . count($live) . '</p><p class="asmall" style="margin:0 0 18px">Besucher in den letzten 5 Minuten — ohne Bots und ohne Verwaltung. Grüner Punkt: in der letzten Minute aktiv.</p>';
    if ($live) {
        echo '<h3 class="asub">Gerade angesehen</h3>';
        vr_admin_bars($pages, 5, null, fn($k) => h($k));
        echo '<h3 class="asub">Geräte</h3>';
        vr_admin_bars($dev, 3, null, fn($k) => h($k));
    }
    return (string)ob_get_clean();
}

/** Canlı ziyaretçilerin ülke sayıları (harita için). */
function vr_admin_live_cc(array $live): array
{
    $cc = [];
    foreach ($live as $v) { $k = strtoupper((string)($v['cc'] ?? '')); if ($k !== '') $cc[$k] = ($cc[$k] ?? 0) + 1; }
    return $cc;
}
