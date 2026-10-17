<?php
/**
 * ChatHelp — Fahrtstatus für Taxi-Kunden (öffentlich per Fahrtcode): ride.php?c=CODE
 * Überschrift je Status, große Wartezeit bzw. Abholzeit, Zeitleiste (Eingegangen → Fahrer zugeteilt → Anfahrt → Taxi ist da → Fahrt läuft → Angekommen),
 * Fahrerkarte mit Fahrzeug und Kennzeichen, Route, Richtpreis, Zentrale anrufen, Storno (solange erlaubt, POST + Redirect).
 * Aktualisiert sich alle 20 s, solange die Fahrt läuft. Unbekannter Code → freundliche 404-Seite.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/ride-lib.php';
th_nocache(); th_bootstrap(); th_fresh();
$code=strtoupper((string)preg_replace('/[^A-Za-z0-9]/','',ride_str($_GET['c'] ?? '')));   // ride_str: ?c[]=… ergibt '' statt PHP-Warnung
$r=($code!=='' && strlen($code)<=8) ? ride_by_code($code) : null;   // Code = Zugang des Kunden; Konto ergibt sich aus der Fahrt
if($r && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['do'] ?? '')==='cancel'){
    $res=ride_cancel_customer((int)$r['account_id'],(string)$r['code']);
    header('Location: ?c='.rawurlencode((string)$r['code']).'&_='.dechex(time()).(isset($res['error']) ? '&e=1' : '&x=1'),true,303); exit;
}
if($r && (int)$r['account_id']===0) $r=ride_demo_tick($r);   // Demo: Fahrt läuft automatisch ab
$s=$r ? ride_settings((int)$r['account_id']) : null; $p=$r ? ride_public($r,$s) : null;
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$tz=$s ? ride_tz($s) : new DateTimeZone('Europe/Berlin'); $now=new DateTimeImmutable('now',$tz);
if(!$p){
    http_response_code(404); header('Content-Type: text/html; charset=UTF-8');
}else header('Content-Type: text/html; charset=UTF-8');
$st=$p['status'] ?? ''; $asap=$p['asap'] ?? true; $live=$p && $p['active'];
$first=$p ? (explode(' ',trim((string)$p['name']))[0] ?? '') : '';
$h1=['new'=>$asap?'Ihr Taxi ist bestellt':'Ihre Fahrt ist vorgemerkt','assigned'=>'Ihr Fahrer ist eingeteilt','en_route'=>'Ihr Taxi ist unterwegs','arrived'=>'Ihr Taxi ist da!',
     'onboard'=>'Gute Fahrt!','completed'=>'Danke für Ihre Fahrt','cancelled'=>'Fahrt storniert','no_show'=>'Fahrt nicht zustande gekommen'][$st] ?? 'Ihre Fahrt';
$steps=[['new','Bestellung eingegangen',$asap?'Wir suchen den nächsten freien Wagen.':'Ihre Fahrt ist fest vorgemerkt.'],['assigned','Fahrer zugeteilt','Ihr Fahrer ist eingeteilt.'],
        ['en_route','Anfahrt','Das Taxi ist auf dem Weg zu Ihnen.'],['arrived','Taxi ist da','Ihr Taxi wartet am Abholort.'],['onboard','Fahrt läuft','Lehnen Sie sich zurück.'],['completed','Angekommen','Danke, dass Sie mit uns gefahren sind.']];
$idx=-1; foreach($steps as $i=>$x) if($x[0]===$st) $idx=$i;
$plateLetter=$s ? (['Europe/Berlin'=>'D','Europe/Vienna'=>'A'][$s['tz']] ?? '') : '';
$telZ=$s && $s['phone']!=='' ? preg_replace('/[^0-9+]/','',$s['phone']) : '';
?><!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $p ? 'Fahrt '.$e($p['code']).' · '.$e($p['company'] ?: 'Taxi') : 'Fahrt nicht gefunden' ?></title><meta name="robots" content="noindex"><meta name="theme-color" content="#f6f7fb">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,600&display=swap">
<style>
:root{--ink:#0f1222;--ink2:#3b4055;--mute:#6b7083;--line:#e4e6ee;--gold:#b8862b;--goldl:#f6ecd3;--ok:#1a9e5a;--okl:#e4f5ea;--rose:#9f2448;--rosel:#fde7ee;--taxi:#f5c518}
*{box-sizing:border-box}body{margin:0;background:#f6f7fb;font:15px/1.55 Inter,system-ui,sans-serif;color:var(--ink);-webkit-font-smoothing:antialiased}
.w{max-width:600px;margin:26px auto 60px;padding:0 16px}.c{background:#fff;border:1px solid var(--line);border-radius:24px;padding:24px;box-shadow:0 10px 30px rgba(15,18,34,.07)}
.eb{font:700 12px Inter;letter-spacing:.12em;text-transform:uppercase;color:var(--gold);display:flex;align-items:center;gap:8px}.eb i{width:22px;height:22px;border-radius:7px;background:var(--taxi);display:grid;place-items:center;font-style:normal;font-size:12px}
h1{font:600 clamp(26px,6vw,34px)/1.15 Fraunces,Georgia,serif;margin:10px 0 6px}.sub{color:var(--mute);font-size:14px}.sub b{color:var(--ink);letter-spacing:.06em}
.hero{display:flex;align-items:center;gap:16px;margin:18px 0;padding:18px 20px;border-radius:20px;background:var(--goldl)}.hero .ic{font-size:30px;line-height:1}.hero small{display:block;color:var(--ink2);font-size:13.5px}.hero b{display:block;font:800 30px/1.15 Inter;letter-spacing:-.01em}
.hero.ok{background:var(--okl)}.hero.ok b{color:#0f6b3c}
.drv{display:flex;align-items:center;gap:14px;padding:16px;border:1px solid var(--line);border-radius:18px;margin:14px 0}.drv .av{flex:0 0 48px;height:48px;border-radius:50%;background:var(--ink);color:#fff;display:grid;place-items:center;font:800 19px Inter}
.drv .nm{font-weight:700;font-size:16px}.drv .car{color:var(--ink2);font-size:14px}
.plate{display:inline-flex;align-items:stretch;border:2px solid #111;border-radius:7px;background:#fff;overflow:hidden;margin-left:auto;flex:0 0 auto;box-shadow:0 2px 6px rgba(0,0,0,.08)}
.plate i{background:#1f4fbf;color:#fff;font:700 10px Inter;font-style:normal;display:flex;align-items:flex-end;justify-content:center;padding:0 5px 3px;min-width:16px}.plate span{font:700 18px/1 ui-monospace,Menlo,Consolas,monospace;padding:7px 10px;letter-spacing:.04em;white-space:nowrap}
.route{margin:14px 0;padding:16px;border-radius:18px;background:#f8f9fc;border:1px solid var(--line)}.route .pt{display:flex;gap:12px;align-items:flex-start}.route .pt+.pt{margin-top:12px}
.route .dot{flex:0 0 14px;height:14px;border-radius:50%;border:3px solid var(--ink);margin-top:4px;background:#fff}.route .dot.d{border-radius:3px;background:var(--ink)}.route small{display:block;color:var(--mute);font-size:12.5px}
.route .ln{margin:2px 0 2px 6px;border-left:2px dashed #c9ccd8;height:10px}
.kv{display:grid;grid-template-columns:auto 1fr;gap:8px 16px;margin:14px 0 0;font-size:14.5px}.kv dt{color:var(--mute)}.kv dd{margin:0}
.tl{list-style:none;margin:20px 0 6px;padding:0}.tl li{display:flex;gap:14px;position:relative;padding-bottom:16px}.tl li:last-child{padding-bottom:0}
.tl li::before{content:"";position:absolute;left:13px;top:28px;bottom:0;width:2px;background:var(--line)}.tl li:last-child::before{display:none}.tl li.done::before{background:var(--ok)}
.tl i{flex:0 0 28px;height:28px;border-radius:50%;display:grid;place-items:center;font-style:normal;font-size:13px;font-weight:800;background:#fff;border:2px solid var(--line);color:var(--mute);z-index:1}
.tl li.done i{background:var(--ok);border-color:var(--ok);color:#fff}.tl li.now i{background:var(--ink);border-color:var(--ink);color:#fff;box-shadow:0 0 0 6px rgba(15,18,34,.08)}
.tl b{display:block;font-size:15px}.tl small{color:var(--mute)}.tl li.todo b{color:var(--mute);font-weight:600}.tl .tm{margin-left:auto;color:var(--mute);font-size:13px;white-space:nowrap}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 16px;border-radius:12px;border:1px solid #d6d9e4;background:#fff;color:var(--ink);font:600 14px Inter;text-decoration:none;cursor:pointer}
.btn.p{background:var(--ink);color:#fff;border-color:var(--ink)}.btn.d{color:var(--rose);border-color:#f7c6d5;background:var(--rosel)}.row{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
.cx{padding:16px;border-radius:16px;background:var(--rosel);color:var(--rose);font-weight:600;margin:16px 0}.okb{padding:16px;border-radius:16px;background:var(--okl);color:#15703f;font-weight:600;margin:16px 0}
.foot{text-align:center;color:var(--mute);font-size:12.5px;margin-top:16px}.foot a{color:var(--gold);text-decoration:none}
.pulse{display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--ok);margin:0 5px 1px 2px;animation:p 1.6s infinite}@keyframes p{0%{box-shadow:0 0 0 0 rgba(26,158,90,.5)}70%{box-shadow:0 0 0 8px rgba(26,158,90,0)}}
@media(max-width:420px){.c{padding:20px}.hero b{font-size:26px}.drv{flex-wrap:wrap}.plate{margin-left:62px}}
</style></head><body><div class="w"><div class="c">
<?php if(!$p): ?>
  <div class="eb"><i>🚕</i>Fahrtstatus</div><h1>Fahrt nicht gefunden</h1>
  <p style="color:var(--mute)">Der Fahrtcode<?= $code!=='' ? ' <b style="color:var(--ink)">'.$e($code).'</b>' : '' ?> ist uns nicht bekannt oder die Fahrt liegt schon länger zurück. Bitte prüfen Sie den Code aus Ihrer Bestätigung – oder rufen Sie Ihre Taxi-Zentrale direkt an.</p>
<?php else: ?>
  <div class="eb"><i>🚕</i><?= $e($p['company'] ?: 'Ihre Taxi-Zentrale') ?></div>
  <h1><?= $e($h1) ?></h1>
  <div class="sub"><?= $first!=='' ? 'Hallo '.$e($first).' · ' : '' ?>Fahrtcode <b><?= $e($p['code']) ?></b> · bestellt <?= $e($p['created']) ?> Uhr<?= $live ? ' · <span class="pulse"></span>live' : '' ?></div>
  <?php if(isset($_GET['x']) && $st==='cancelled'): ?><div class="okb">Ihre Fahrt wurde storniert. Die Zentrale ist informiert – vielen Dank für die Absage.</div><?php endif; ?>
  <?php if(isset($_GET['e'])): ?><div class="cx">Stornieren ist nicht mehr möglich, weil das Taxi schon da ist oder die Fahrt läuft.<?= $s['phone']!=='' ? ' Bitte rufen Sie die Zentrale an: '.$e($s['phone']) : '' ?></div><?php endif; ?>
  <?php if($st==='cancelled' && !isset($_GET['x'])): ?><div class="cx">Diese Fahrt wurde storniert<?= $p['cancel_by']==='Kunde' ? ' (durch Sie)' : '' ?>.<?= $s['phone']!=='' ? ' Fragen? '.$e($s['phone']) : '' ?></div><?php endif; ?>
  <?php if($st==='no_show'): ?><div class="cx">Der Fahrer hat Sie am Abholort leider nicht angetroffen.<?= $s['phone']!=='' ? ' Bitte melden Sie sich bei der Zentrale: '.$e($s['phone']) : '' ?></div><?php endif; ?>

  <?php if(in_array($st,['new','assigned','en_route'],true)): ?>
    <?php if($asap): ?>
      <div class="hero"><div class="ic">⏱</div><div><small>Ihr Taxi ist voraussichtlich da</small><b><?= $e($p['due_in_min']!==null && $p['due_in_min']>0 ? 'in ca. '.$p['due_in_min'].' Min' : 'in Kürze') ?></b><small><?= $p['pickup_time']!=='' && ($p['due_in_min'] ?? 0)>0 ? 'gegen '.$e($p['pickup_time']).' Uhr · ' : '' ?>Bitte halten Sie sich am Abholort bereit.</small></div></div>
    <?php else: ?>
      <?php $atDt=ride_dt((string)$r['pickup_at'],$tz); ?>
      <div class="hero"><div class="ic">📅</div><div><small>Abholung</small><b><?= $atDt ? $e(ride_wd($atDt).', '.$atDt->format('d.m.').' · '.$atDt->format('H:i').' Uhr') : '' ?></b><small><?= $e(ucfirst($p['when_long'])) ?> · Bitte seien Sie pünktlich am Abholort.</small></div></div>
    <?php endif; ?>
  <?php elseif($st==='arrived'): ?>
    <div class="hero ok"><div class="ic">🚖</div><div><small>Ihr Taxi wartet am Abholort</small><b><?= $p['driver'] && $p['driver']['plate']!=='' ? $e($p['driver']['plate']) : 'Taxi ist da' ?></b><small><?= $p['driver'] ? $e($p['driver']['vehicle']) : 'Bitte kommen Sie zum Fahrzeug.' ?></small></div></div>
  <?php endif; ?>

  <?php if($p['driver'] && !in_array($st,['cancelled','no_show'],true)): $dv=$p['driver']; ?>
    <div class="drv"><div class="av"><?= $e(mb_strtoupper(mb_substr($dv['name'],0,1))) ?></div><div><div class="nm"><?= $e($dv['name']) ?></div><div class="car"><?= $e($dv['vehicle'] ?: 'Taxi') ?></div></div>
      <?php if($dv['plate']!==''): ?><div class="plate" title="Kennzeichen"><?php if($plateLetter!==''): ?><i><?= $e($plateLetter) ?></i><?php endif; ?><span><?= $e($dv['plate']) ?></span></div><?php endif; ?></div>
  <?php endif; ?>

  <div class="route">
    <div class="pt"><span class="dot"></span><div><small>Abholung<?= !$asap && $p['pickup_time']!=='' ? ' · '.$e($p['when']).' Uhr' : '' ?></small><b><?= $e($p['pickup']) ?></b><?php if($p['pickup_geo']!=='' && $p['pickup_geo']!==$p['pickup']): ?><small><?= $e($p['pickup_geo']) ?></small><?php endif; ?></div></div>
    <div class="ln"></div>
    <div class="pt"><span class="dot d"></span><div><small>Ziel</small><b><?= $p['destination']!=='' ? $e($p['destination']) : 'sagen Sie bitte dem Fahrer' ?></b><?php if($p['distance_text']!==''): ?><small><?= $e($p['distance_text']) ?></small><?php endif; ?></div></div>
  </div>

  <dl class="kv">
    <dt>Fahrzeug</dt><dd><?= (int)$p['passengers'] ?> <?= (int)$p['passengers']===1 ? 'Person' : 'Personen' ?> · <?= $e($p['vehicle_name']) ?></dd>
    <?php if($p['extras']!==''): ?><dt>Extras</dt><dd><?= $e($p['extras']) ?></dd><?php endif; ?>
    <?php if($p['luggage']!==''): ?><dt>Gepäck</dt><dd><?= $e($p['luggage']) ?></dd><?php endif; ?>
    <dt>Preis</dt><dd><b><?= $e($p['price_text']) ?></b></dd>
    <dt>Zahlung</dt><dd><?= $e($p['payment_label']) ?></dd>
    <?php if($p['note']!==''): ?><dt>Hinweis</dt><dd><?= $e($p['note']) ?></dd><?php endif; ?>
  </dl>

  <?php if(!in_array($st,['cancelled','no_show'],true)): ?>
  <ul class="tl"><?php foreach($steps as $i=>[$k,$lbl,$txt]): $cls=$i<$idx ? 'done' : ($i===$idx ? ($st==='completed' ? 'done' : 'now') : 'todo'); ?>
    <li class="<?= $cls ?>"><i><?= $cls==='done' ? '✓' : ($i+1) ?></i><div><b><?= $e($lbl) ?></b><small><?= $e($txt) ?></small></div><?php if(isset($p['times'][$k]) && $cls!=='todo'): ?><span class="tm"><?= $e($p['times'][$k]) ?> Uhr</span><?php endif; ?></li>
  <?php endforeach; ?></ul>
  <?php endif; ?>

  <div class="row">
    <?php if($telZ!==''): ?><a class="btn p" href="tel:<?= $e($telZ) ?>">📞 Zentrale anrufen</a><?php endif; ?>
    <?php if($p['maps_pickup'] && $live): ?><a class="btn" href="<?= $e($p['maps_pickup']) ?>" target="_blank" rel="noopener">🗺 Abholort auf der Karte</a><?php endif; ?>
    <?php if($p['can_cancel']): ?><form method="post" action="?c=<?= rawurlencode($p['code']) ?>&amp;_=<?= dechex(time()) ?>" onsubmit="return confirm('Fahrt <?= $e($p['code']) ?> wirklich stornieren?')"><input type="hidden" name="do" value="cancel"><button class="btn d" type="submit">Fahrt stornieren</button></form><?php endif; ?>
  </div>
<?php endif; ?>
</div><div class="foot">Taxi-Annahme mit <a href="https://chat-help.com/">ChatHelp</a> · Stand <?= $e($now->format('H:i')) ?> Uhr<?= $live ? ' · aktualisiert sich automatisch' : '' ?></div></div>
<?php if($live): ?><script>setTimeout(function(){location.replace('?c=<?= rawurlencode($p['code']) ?>&_='+Date.now().toString(36));},20000);</script><?php endif; ?>
</body></html>
