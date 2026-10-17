<?php
/**
 * ChatHelp — Fahrer-App (Handy, ohne Login): taxi-driver.php?t=<Fahrer-Token>
 * Status Frei/Pause, aktuelle Fahrt mit großen Knöpfen in Reihenfolge („Anfahrt starten“ → „Bin da“ → „Kunde an Bord“ → „Fahrt beendet“,
 * dazu „Kunde nicht da“), Navigation zum Abholort bzw. danach zum Ziel, Kunde anrufen, Preis, nächste Vorbestellungen.
 * Aktionen per POST + Redirect (PRG), Seite lädt sich alle 15 s neu. Fahrer sehen nur eigene Fahrten ihres Kontos.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/ride-lib.php';
th_nocache(); th_bootstrap(); th_fresh();
$t=preg_replace('/[^a-f0-9]/','',(string)($_GET['t'] ?? '')); $drv=ride_driver_by_token($t);
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
if(!$drv){ http_response_code(404); header('Content-Type: text/html; charset=UTF-8'); ?><!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Fahrer-Link ungültig</title><meta name="robots" content="noindex">
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0f1222;color:#fff;font:16px/1.55 Inter,system-ui,sans-serif;padding:16px}.b{max-width:420px;background:#171b33;border:1px solid #2a2f4a;border-radius:22px;padding:26px}h1{font-size:21px;margin:0 0 8px}p{color:#9aa0c0;margin:0}</style></head>
<body><div class="b"><h1>🚕 Fahrer-Link ungültig</h1><p>Dieser Link ist nicht (mehr) aktiv. Bitte lass dir von deiner Zentrale den aktuellen persönlichen Link schicken.</p></div></body></html>
<?php exit; }
$acc=(int)$drv['account_id']; $did=(int)$drv['id']; $s=ride_settings($acc); $tz=ride_tz($s); $now=ride_now($s);
$self='?t='.$t;
if($_SERVER['REQUEST_METHOD']==='POST'){
    $do=(string)($_POST['do'] ?? ''); $m='ok';
    if($do==='me'){ $st=(string)($_POST['s'] ?? ''); $m=in_array($st,['frei','pause'],true) && !isset(ride_driver_set_status($acc,$did,$st)['error']) ? 'me' : 'err'; }
    elseif($do==='status'){
        $id=(int)($_POST['id'] ?? 0); $st=(string)($_POST['s'] ?? '');
        $q=th_db()->prepare("SELECT id FROM rides WHERE id=? AND account_id=? AND driver_id=?"); $q->execute([$id,$acc,$did]);
        if($q->fetchColumn() && in_array($st,['en_route','arrived','onboard','completed','no_show'],true)){ $res=ride_set_status($acc,$id,$st,null,'Fahrer'); $m=isset($res['error']) ? 'err' : $st; } else $m='err';
    }
    header('Location: '.$self.'&_='.dechex(time()).'&m='.rawurlencode($m),true,303); exit;
}
$drv=ride_driver($acc,$did) ?? $drv;
$q=th_db()->prepare("SELECT * FROM rides WHERE account_id=? AND driver_id=? AND status IN ('assigned','en_route','arrived','onboard') AND pickup_at >= ? ORDER BY FIELD(status,'onboard','arrived','en_route','assigned'), asap DESC, pickup_at, id");
$q->execute([$acc,$did,$now->modify('-12 hours')->format('Y-m-d H:i:s')]); $drvList=[$drv];
$rides=array_map(fn($r)=>ride_public($r,$s,$drvList),$q->fetchAll(PDO::FETCH_ASSOC));
$cur=null; $next=[];
foreach($rides as $r){ if($cur===null && ($r['status']!=='assigned' || $r['asap'] || ($r['due_in_min']!==null && $r['due_in_min']<=60))) $cur=$r; else $next[]=$r; }
$q=th_db()->prepare("SELECT COUNT(*) FROM rides WHERE account_id=? AND driver_id=? AND status='completed' AND pickup_at BETWEEN ? AND ?"); $q->execute([$acc,$did,$now->format('Y-m-d 00:00:00'),$now->format('Y-m-d 23:59:59')]); $done=(int)$q->fetchColumn();
$flash=['me'=>'Status gespeichert.','en_route'=>'Anfahrt gestartet – gute Fahrt!','arrived'=>'Gemeldet: Du bist da. Der Fahrgast wird informiert.','onboard'=>'Fahrgast an Bord.','completed'=>'Fahrt beendet ✓ Danke!','no_show'=>'Als „nicht angetroffen“ gemeldet.','err'=>'Das hat nicht geklappt – bitte noch einmal versuchen oder die Zentrale anrufen.'][(string)($_GET['m'] ?? '')] ?? '';
$telZ=$s['phone']!=='' ? preg_replace('/[^0-9+]/','',$s['phone']) : '';
$step=['assigned'=>['en_route','▶ Anfahrt starten','go'],'en_route'=>['arrived','📍 Bin da','go'],'arrived'=>['onboard','👤 Kunde an Bord','ok'],'onboard'=>['completed','🏁 Fahrt beendet','ok']];
header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>Fahrten · <?= $e($drv['name']) ?></title><meta name="robots" content="noindex">
<meta name="theme-color" content="#0f1222"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="Taxi">
<script>setTimeout(function(){if(!document.querySelector('form.busy'))location.replace('<?= $e($self) ?>&_='+Date.now().toString(36));},15000);</script>
<style>
:root{--bg:#0f1222;--sur:#171b33;--sur2:#1d2240;--line:#2a2f4a;--mute:#9aa0c0;--gold:#e3c27a;--taxi:#f5c518;--ok:#1a9e5a;--rose:#e0486f;--indigo:#4f46e5}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:#fff;font:16px/1.5 Inter,system-ui,sans-serif;-webkit-font-smoothing:antialiased}button{font:inherit}
.w{max-width:560px;margin:0 auto;padding:14px 16px calc(28px + env(safe-area-inset-bottom))}
.hd{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:6px 0 14px}.hd b{font-size:19px}.hd small{display:block;color:var(--mute);font-size:13px}.hd .tm{color:var(--mute);font-size:13px;text-align:right}
.sw{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px}.sw button{padding:14px;border-radius:14px;border:1px solid var(--line);background:var(--sur);color:var(--mute);font-weight:800;font-size:15px;cursor:pointer}
.sw button.on.frei{background:var(--ok);border-color:var(--ok);color:#fff}.sw button.on.pause{background:#4a4f6e;border-color:#4a4f6e;color:#fff}.busyl{grid-column:1/-1;text-align:center;padding:10px;border-radius:14px;background:rgba(224,72,111,.15);color:#ff9bb3;font-weight:700;font-size:14px}
.fl{padding:12px 14px;border-radius:14px;background:rgba(26,158,90,.16);color:#8ee6b5;font-weight:600;margin-bottom:12px;font-size:14.5px}.fl.err{background:rgba(224,72,111,.16);color:#ff9bb3}
.job{background:var(--sur);border:1px solid var(--line);border-radius:22px;padding:18px;margin-bottom:14px}.job.hot{border-color:var(--taxi);box-shadow:0 0 0 2px rgba(245,197,24,.18)}
.job .top{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.job .code{font:800 24px Inter;letter-spacing:.05em}.bd{font:800 11.5px Inter;letter-spacing:.08em;text-transform:uppercase;padding:5px 10px;border-radius:999px;background:var(--line)}.bd.asap{background:var(--taxi);color:#111}.bd.st{background:var(--sur2);color:var(--gold);border:1px solid var(--line)}
.when{margin-left:auto;color:var(--gold);font-weight:800;font-size:16px}
.pt{display:flex;gap:10px;margin-top:14px}.pt .i{font-size:20px;line-height:1.2}.pt b{display:block;font-size:19px;line-height:1.3}.pt small{display:block;color:var(--mute);font-size:13px}.pt .warn{color:#ff9bb3}
.meta{color:#c6c9d9;font-size:14.5px;margin-top:12px}.note{margin-top:10px;padding:10px 12px;border-radius:12px;background:#2a2340;color:#f3d9ff;font-size:14.5px}
.price{display:flex;justify-content:space-between;gap:10px;margin-top:12px;padding-top:12px;border-top:1px solid var(--line);font-weight:800;font-size:17px}.price span:first-child{color:var(--mute);font-weight:600;font-size:15px}
.btns{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:14px}.b{display:flex;align-items:center;justify-content:center;gap:8px;padding:15px 12px;border-radius:14px;font-weight:800;text-decoration:none;border:0;cursor:pointer;font-size:16px;color:#fff;width:100%}
.b.nav{background:var(--indigo)}.b.call{background:var(--line)}.b.go{background:var(--taxi);color:#111;grid-column:1/-1;font-size:19px;padding:20px}.b.ok{background:var(--ok);grid-column:1/-1;font-size:19px;padding:20px}.b.x{grid-column:1/-1;background:transparent;border:1px solid var(--line);color:var(--mute);font-weight:700}
form{margin:0}form.full{grid-column:1/-1}
h3{font:700 12px Inter;letter-spacing:.12em;text-transform:uppercase;color:var(--mute);margin:20px 0 8px}
.nx{display:flex;gap:12px;align-items:flex-start;padding:12px 14px;border:1px solid var(--line);border-radius:16px;margin-bottom:8px;background:rgba(23,27,51,.6)}.nx .tm{font:800 15px Inter;color:var(--gold);min-width:88px}.nx small{display:block;color:var(--mute);font-size:13px}
.empty{text-align:center;color:var(--mute);padding:34px 14px;border:1px dashed var(--line);border-radius:20px}.empty b{display:block;color:#fff;font-size:18px;margin-bottom:4px}
.foot{display:flex;justify-content:space-between;gap:10px;color:var(--mute);font-size:12.5px;margin-top:18px}.foot a{color:var(--gold);text-decoration:none}
</style></head>
<body><div class="w">
<div class="hd"><div><b>🚖 <?= $e($drv['name']) ?></b><small><?= $e($drv['vehicle'] ?: ($s['name'] ?: 'Taxi')) ?><?= $drv['plate']!=='' ? ' · <span style="white-space:nowrap">'.$e($drv['plate']).'</span>' : '' ?></small></div><div class="tm"><?= $e($s['name'] ?: 'Zentrale') ?><br>heute erledigt: <b style="color:#fff"><?= $done ?></b> · <?= $e($now->format('H:i')) ?></div></div>
<?php if($flash!==''): ?><div class="fl <?= ($_GET['m'] ?? '')==='err' ? 'err' : '' ?>"><?= $e($flash) ?></div><?php endif; ?>
<div class="sw">
  <?php foreach(['frei'=>'🟢 Frei','pause'=>'⏸ Pause'] as $k=>$lbl): ?><form method="post" action="<?= $e($self) ?>" onsubmit="this.classList.add('busy')"><input type="hidden" name="do" value="me"><input type="hidden" name="s" value="<?= $k ?>"><button type="submit" class="<?= $k ?><?= $drv['status']===$k ? ' on' : '' ?>" style="width:100%"><?= $lbl ?></button></form><?php endforeach; ?>
  <?php if($drv['status']==='besetzt'): ?><div class="busyl">● Besetzt – du bist auf einer Fahrt</div><?php endif; ?>
</div>
<?php if(!$cur): ?>
  <div class="empty"><b>Keine aktuelle Fahrt</b><?= $drv['status']==='pause' ? 'Du bist auf Pause. Tippe „Frei“, wenn du wieder fährst.' : 'Neue Fahrten von der Zentrale erscheinen hier automatisch (und per WhatsApp/SMS).' ?></div>
<?php else: $r=$cur; $nx=$step[$r['status']] ?? null; $toDest=$r['status']==='onboard'; $nav=$toDest ? ($r['maps_dest'] ?: null) : $r['maps_pickup']; ?>
  <div class="job <?= in_array($r['status'],['assigned','en_route'],true) ? 'hot' : '' ?>">
    <div class="top"><span class="code"><?= $e($r['code']) ?></span><?= $r['asap'] ? '<span class="bd asap">Sofort</span>' : '' ?><span class="bd st"><?= $e($r['status_label']) ?></span><span class="when"><?= $r['asap'] ? $e($r['eta_text'] ?: 'jetzt') : $e($r['when']) ?></span></div>
    <div class="pt"><span class="i">📍</span><div><small>Abholung</small><b><?= $e($r['pickup']) ?></b><?php if(!$r['pickup_checked']): ?><small class="warn">⚠ Adresse ungeprüft – im Zweifel Fahrgast anrufen</small><?php elseif($r['pickup_geo']!=='' && $r['pickup_geo']!==$r['pickup']): ?><small><?= $e($r['pickup_geo']) ?></small><?php endif; ?></div></div>
    <div class="pt"><span class="i">🏁</span><div><small>Ziel</small><b><?= $r['destination']!=='' ? $e($r['destination']) : 'sagt der Fahrgast' ?></b><?php if($r['distance_text']!==''): ?><small><?= $e($r['distance_text']) ?></small><?php endif; ?></div></div>
    <div class="meta">👤 <?= $e($r['name']) ?> · 👥 <?= (int)$r['passengers'] ?> · <?= $e($r['vehicle_name']) ?><?= $r['extras']!=='' ? ' · '.$e($r['extras']) : '' ?><?= $r['luggage']!=='' ? ' · 🧳 '.$e($r['luggage']) : '' ?></div>
    <?php if($r['note']!==''): ?><div class="note">📝 <?= $e($r['note']) ?></div><?php endif; ?>
    <div class="price"><span><?= $e($r['payment_label']) ?><?= $r['payment']==='bar' ? ' · kassieren' : '' ?></span><span><?= $e($r['price_kind']==='fixed' ? ride_eur($r['price_cents']).' Festpreis' : ($r['price_cents']!==null ? 'ca. '.ride_eur($r['price_cents']).' (Taxameter)' : 'Taxameter')) ?></span></div>
    <div class="btns">
      <?php if($nav): ?><a class="b nav" href="<?= $e($nav) ?>" target="_blank" rel="noopener">🗺 <?= $toDest ? 'Zum Ziel' : 'Zum Abholort' ?></a><?php else: ?><span class="b call" style="opacity:.5">🗺 kein Ziel</span><?php endif; ?>
      <?php if($r['phone']!==''): ?><a class="b call" href="tel:<?= $e(preg_replace('/[^0-9+]/','',$r['phone'])) ?>">📞 Fahrgast</a><?php elseif($telZ!==''): ?><a class="b call" href="tel:<?= $e($telZ) ?>">📞 Zentrale</a><?php endif; ?>
      <?php if($nx): ?><form method="post" action="<?= $e($self) ?>" class="full" onsubmit="this.classList.add('busy');this.querySelector('button').disabled=true"><input type="hidden" name="do" value="status"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="s" value="<?= $nx[0] ?>"><button class="b <?= $nx[2] ?>" type="submit"><?= $e($nx[1]) ?></button></form><?php endif; ?>
      <?php if(in_array($r['status'],['en_route','arrived'],true)): ?><form method="post" action="<?= $e($self) ?>" class="full" onsubmit="if(!confirm('Fahrgast wirklich nicht angetroffen?'))return false;this.classList.add('busy')"><input type="hidden" name="do" value="status"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="s" value="no_show"><button class="b x" type="submit">Kunde nicht da</button></form><?php endif; ?>
    </div>
  </div>
<?php endif; ?>
<?php if($next): ?><h3>Nächste Fahrten</h3>
  <?php foreach($next as $r): ?><div class="nx"><span class="tm"><?= $e($r['asap'] ? 'sofort' : $r['when']) ?></span><div><b><?= $e($r['code']) ?></b> · <?= $e($r['pickup']) ?><small><?= $r['destination']!=='' ? '→ '.$e($r['destination']).' · ' : '' ?><?= (int)$r['passengers'] ?> Pers. · <?= $e($r['vehicle_name']) ?><?= $r['extras']!=='' ? ' · '.$e($r['extras']) : '' ?> · <?= $e($r['name']) ?></small></div></div><?php endforeach; ?>
<?php endif; ?>
<div class="foot"><span>Aktualisiert sich alle 15 s</span><?php if($telZ!==''): ?><a href="tel:<?= $e($telZ) ?>">📞 Zentrale <?= $e($s['phone']) ?></a><?php endif; ?></div>
<div class="foot" style="margin-top:6px"><span>Tipp: Seite zum Startbildschirm hinzufügen – dann startet sie wie eine App.</span></div>
</div></body></html>
