<?php
/**
 * ChatHelp — Bestellstatus für Kunden (öffentlich per Bestellcode): order.php?c=CODE
 * Status-Zeitleiste (Eingegangen → Bestätigt → In Zubereitung → Fertig → Unterwegs → Zugestellt), voraussichtliche Zeit,
 * Positionen, Summe, Kontakt zum Restaurant. Lädt sich alle 30 s neu, solange die Bestellung läuft. Storno, solange „neu“.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/order-lib.php';
th_nocache(); th_bootstrap(); th_fresh();
$code=strtoupper(preg_replace('/[^A-Za-z0-9]/','',(string)($_GET['c'] ?? '')));
$o=$code!=='' ? ord_by_code($code) : null;
if($o && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['do'] ?? '')==='cancel' && $o['status']==='new'){
    ord_set_status((int)$o['account_id'],(int)$o['id'],'cancelled');
    header('Location: ?c='.$o['code'].'&_='.dechex(time()).'&x=1',true,303); exit;
}
$s=$o ? ord_settings((int)$o['account_id']) : null; $p=$o ? ord_public($o,$s) : null;
$first=$p ? (explode(' ',trim((string)$p['name']))[0] ?: '') : '';
$steps=$p && $p['type']==='pickup'
  ? [['new','Eingegangen','Die Bestellung ist beim Restaurant angekommen.'],['confirmed','Bestätigt','Das Restaurant hat angenommen.'],['preparing','In Zubereitung','Wird gerade frisch zubereitet.'],['ready','Abholbereit','Kann abgeholt werden.'],['delivered','Abgeholt','Guten Appetit!']]
  : [['new','Eingegangen','Die Bestellung ist beim Restaurant angekommen.'],['confirmed','Bestätigt','Das Restaurant hat angenommen.'],['preparing','In Zubereitung','Wird gerade frisch zubereitet.'],['ready','Fertig','Wartet auf den Fahrer.'],['out_for_delivery','Unterwegs','Der Fahrer ist auf dem Weg zu dir.'],['delivered','Zugestellt','Guten Appetit!']];
$idx=-1; if($p){ foreach($steps as $i=>$st) if($st[0]===$p['status']) $idx=$i; }
$live=$p && !in_array($p['status'],['delivered','cancelled'],true);
header('Content-Type: text/html; charset=UTF-8');
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
?><!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $p ? 'Bestellung '.$e($p['code']).' · '.$e($p['restaurant'] ?: 'Status') : 'Bestellung nicht gefunden' ?></title><meta name="robots" content="noindex">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,600&display=swap">
<style>
:root{--ink:#0f1222;--ink2:#3b4055;--mute:#6b7083;--line:#e4e6ee;--gold:#b8862b;--goldl:#f6ecd3;--ok:#1a9e5a;--okl:#e4f5ea;--rose:#9f2448;--rosel:#fde7ee}
*{box-sizing:border-box}body{margin:0;background:#f6f7fb;font:15px/1.55 Inter,system-ui,sans-serif;color:var(--ink);-webkit-font-smoothing:antialiased}
.w{max-width:600px;margin:26px auto 60px;padding:0 16px}.c{background:#fff;border:1px solid var(--line);border-radius:24px;padding:24px;box-shadow:0 10px 30px rgba(15,18,34,.07)}
.eb{font:700 12px Inter;letter-spacing:.12em;text-transform:uppercase;color:var(--gold)}h1{font:600 clamp(26px,6vw,34px)/1.15 Fraunces,Georgia,serif;margin:8px 0 6px}
.eta{display:flex;align-items:center;gap:14px;margin:18px 0;padding:16px 18px;border-radius:18px;background:var(--goldl)}.eta b{font:800 30px Inter}.eta span{color:var(--ink2);font-size:14px}
.tl{list-style:none;margin:18px 0 6px;padding:0}.tl li{display:flex;gap:14px;position:relative;padding-bottom:16px}.tl li:last-child{padding-bottom:0}
.tl li::before{content:"";position:absolute;left:13px;top:28px;bottom:0;width:2px;background:var(--line)}.tl li:last-child::before{display:none}.tl li.done::before{background:var(--ok)}
.tl i{flex:0 0 28px;height:28px;border-radius:50%;display:grid;place-items:center;font-style:normal;font-size:13px;font-weight:800;background:#fff;border:2px solid var(--line);color:var(--mute);z-index:1}
.tl li.done i{background:var(--ok);border-color:var(--ok);color:#fff}.tl li.now i{background:var(--ink);border-color:var(--ink);color:#fff;box-shadow:0 0 0 6px rgba(15,18,34,.08)}
.tl b{display:block;font-size:15px}.tl small{color:var(--mute)}.tl li.todo b{color:var(--mute);font-weight:600}
.items{margin-top:16px;border-top:1px solid var(--line)}.items .r{display:flex;justify-content:space-between;gap:12px;padding:9px 0;border-bottom:1px solid var(--line);font-size:14.5px}.items .r small{display:block;color:var(--mute)}.items .t{font-weight:800;font-size:16px;border-bottom:0}
.meta{color:var(--ink2);font-size:14px;margin-top:12px}.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 16px;border-radius:12px;border:1px solid #d6d9e4;background:#fff;color:var(--ink);font:600 14px Inter;text-decoration:none;cursor:pointer}
.btn.p{background:var(--ink);color:#fff;border-color:var(--ink)}.btn.d{color:var(--rose);border-color:#f7c6d5;background:var(--rosel)}.row{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
.cx{padding:16px;border-radius:16px;background:var(--rosel);color:var(--rose);font-weight:600;margin:16px 0}.foot{text-align:center;color:var(--mute);font-size:12.5px;margin-top:16px}.foot a{color:var(--gold)}
.pulse{display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--ok);margin-right:6px;animation:p 1.6s infinite}@keyframes p{70%{box-shadow:0 0 0 8px rgba(26,158,90,0)}0%{box-shadow:0 0 0 0 rgba(26,158,90,.5)}}
</style></head><body><div class="w"><div class="c">
<?php if(!$p): ?>
  <div class="eb">Bestellstatus</div><h1>Bestellung nicht gefunden</h1><p style="color:var(--mute)">Der Bestellcode ist ungültig oder die Bestellung ist älter als ein paar Tage. Bitte wende dich direkt an das Restaurant.</p>
<?php else: ?>
  <div class="eb"><?= $e($p['restaurant'] ?: 'Bestellung') ?></div>
  <h1><?= $p['status']==='cancelled' ? 'Bestellung storniert' : ($p['status']==='delivered' ? ($p['type']==='pickup'?'Abgeholt – guten Appetit!':'Zugestellt – guten Appetit!') : 'Danke'.($first?', '.$e($first):'').'! Deine Bestellung läuft.') ?></h1>
  <div style="color:var(--mute);font-size:14px">Bestellcode <b style="color:var(--ink)"><?= $e($p['code']) ?></b> · <?= $p['type']==='delivery'?'Lieferung':'Abholung' ?> · bestellt <?= $e($p['created']) ?> Uhr<?= $live ? ' · <span class="pulse"></span>live' : '' ?></div>
  <?php if(isset($_GET['x']) && $p['status']==='cancelled'): ?><div class="cx">Deine Bestellung wurde storniert. Das Restaurant ist informiert.</div><?php endif; ?>
  <?php if($p['status']==='cancelled'): ?>
    <?php if(!isset($_GET['x'])): ?><div class="cx">Diese Bestellung wurde storniert.<?= $s['phone'] ? ' Fragen? '.$e($s['phone']) : '' ?></div><?php endif; ?>
  <?php else: ?>
    <?php if($live && $p['eta']): ?><div class="eta"><div style="font-size:28px"><?= $p['type']==='delivery'?'🚗':'🛍️' ?></div><div><span><?= $p['type']==='delivery'?'Voraussichtliche Lieferung':'Abholbereit ab' ?></span><br><b><?= $e($p['eta']) ?> Uhr</b></div></div><?php endif; ?>
    <ul class="tl"><?php foreach($steps as $i=>[$k,$lbl,$txt]): $cls=$i<$idx?'done':($i===$idx?($p['status']==='delivered'?'done':'now'):'todo'); ?>
      <li class="<?= $cls ?>"><i><?= $cls==='done'?'✓':($i+1) ?></i><div><b><?= $e($lbl) ?><?= $k==='out_for_delivery' && $p['driver'] && $i===$idx ? ' · mit '.$e($p['driver']['name']) : '' ?></b><small><?= $e($txt) ?></small></div></li>
    <?php endforeach; ?></ul>
  <?php endif; ?>
  <div class="items">
    <?php foreach($p['items'] as $it): ?><div class="r"><span><?= (int)$it['qty'] ?>× <?= $e($it['name']) ?><?php if(!empty($it['options'])): ?><small><?= $e(implode(', ',$it['options'])) ?></small><?php endif; ?></span><span><?= number_format($it['total_cents']/100,2,',','.') ?> €</span></div><?php endforeach; ?>
    <?php if($p['fee_cents']): ?><div class="r"><span>Liefergebühr</span><span><?= number_format($p['fee_cents']/100,2,',','.') ?> €</span></div><?php endif; ?>
    <div class="r t"><span>Gesamt · <?= $e($p['payment']) ?></span><span><?= $e($p['total']) ?></span></div>
  </div>
  <?php if($p['type']==='delivery' && $p['address']): ?><div class="meta">📍 <?= $e($p['address']) ?></div><?php elseif($s['address']): ?><div class="meta">📍 Abholung: <?= $e($s['address']) ?></div><?php endif; ?>
  <?php if($p['note']): ?><div class="meta">📝 <?= $e($p['note']) ?></div><?php endif; ?>
  <div class="row">
    <?php if($s['phone']): ?><a class="btn p" href="tel:<?= $e(preg_replace('/[^0-9+]/','',$s['phone'])) ?>">📞 Restaurant anrufen</a><?php endif; ?>
    <?php if($p['type']==='pickup' && $s['address']): ?><a class="btn" href="https://www.google.com/maps/dir/?api=1&destination=<?= rawurlencode($s['address']) ?>" target="_blank" rel="noopener">🗺 Route zum Restaurant</a><?php endif; ?>
    <?php if($p['status']==='new'): ?><form method="post" onsubmit="return confirm('Bestellung wirklich stornieren?')"><input type="hidden" name="do" value="cancel"><button class="btn d" type="submit">Stornieren</button></form><?php endif; ?>
  </div>
<?php endif; ?>
</div><div class="foot">Bestellannahme mit <a href="https://chat-help.com/#bestellen">ChatHelp</a> · Status aktualisiert <?= (new DateTimeImmutable('now',$s ? ord_tz($s) : new DateTimeZone('Europe/Berlin')))->format('H:i') ?> Uhr</div></div>
<?php if($live): ?><script>setTimeout(function(){location.replace('?c=<?= $e($p['code']) ?>&_='+Date.now().toString(36));},30000);</script><?php endif; ?>
</body></html>
