<?php
/** ChatHelp — Fahrerseite (mobil): driver.php?t=<Fahrer-Token> · zugewiesene Fahrten, Navigation, Anruf, „Zugestellt“ */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/order-lib.php';
th_nocache(); th_bootstrap(); th_fresh();
$t=preg_replace('/[^a-f0-9]/','',(string)($_GET['t'] ?? '')); $drv=ord_driver_by_token($t);
if(!$drv){ header('Content-Type: text/plain; charset=UTF-8'); http_response_code(404); exit("Fahrer-Link ungültig.\n"); }
$acc=(int)$drv['account_id']; $s=ord_settings($acc);
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['done'])){ $id=(int)$_POST['done']; $st=th_db()->prepare("SELECT id FROM orders WHERE id=? AND account_id=? AND driver_id=?"); $st->execute([$id,$acc,$drv['id']]); if($st->fetchColumn()) ord_set_status($acc,$id,'delivered',null,'driver'); header('Location: ?t='.$t.'&_='.dechex(time())); exit; }
$st=th_db()->prepare("SELECT * FROM orders WHERE account_id=? AND driver_id=? AND status IN ('ready','out_for_delivery') ORDER BY eta_at"); $st->execute([$acc,$drv['id']]); $orders=array_map(fn($o)=>ord_public($o,$s),$st->fetchAll(PDO::FETCH_ASSOC));
/* „heute“ = Ortstag des Betriebs (nicht UTC/CURDATE): seit lokaler Mitternacht vergangene Sekunden von NOW() der DB abziehen – unabhängig von der DB-Zeitzone */
$now=new DateTimeImmutable('now',ord_tz($s)); $sinceMidnight=$now->getTimestamp()-$now->setTime(0,0)->getTimestamp();
$st=th_db()->prepare("SELECT COUNT(*) FROM orders WHERE account_id=? AND driver_id=? AND status='delivered' AND COALESCE(updated_at,created_at) >= (NOW() - INTERVAL ? SECOND)"); $st->execute([$acc,$drv['id'],$sinceMidnight]); $doneToday=(int)$st->fetchColumn();
header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Fahrten · <?= htmlspecialchars($drv['name']) ?></title><meta name="robots" content="noindex">
<script>setTimeout(function(){location.replace('?t=<?= $t ?>&_='+Date.now().toString(36));},30000);</script>
<style>body{margin:0;background:#0f1222;color:#fff;font:16px/1.5 Inter,system-ui,sans-serif}.w{max-width:560px;margin:0 auto;padding:16px}.hd{display:flex;justify-content:space-between;align-items:center;padding:8px 0 14px}.hd b{font-size:18px}.hd span{color:#9aa0c0;font-size:13px}
.o{background:#171b33;border:1px solid #2a2f4a;border-radius:18px;padding:16px;margin-bottom:12px}.o .top{display:flex;justify-content:space-between;align-items:center}.o .code{font:800 20px Inter}.o .eta{color:#e3c27a;font-weight:700}.o .adr{font-size:17px;font-weight:600;margin:10px 0 4px}.o .meta{color:#c6c9d9;font-size:14px}.o .items{margin:8px 0;color:#fff;font-size:14.5px}.o .tot{font-weight:800;font-size:18px;margin-top:6px}
.btns{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:12px}.b{display:flex;align-items:center;justify-content:center;gap:8px;padding:13px;border-radius:12px;font-weight:700;text-decoration:none;border:0;cursor:pointer;font-size:15px}.nav{background:#4f46e5;color:#fff}.call{background:#2a2f4a;color:#fff}.done{background:#1a9e5a;color:#fff;grid-column:1/-1}
.empty{text-align:center;color:#9aa0c0;padding:40px 10px}.st{display:inline-block;padding:4px 9px;border-radius:999px;font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;background:#2a2f4a}.st.out{background:#e3c27a;color:#1a1407}</style></head>
<body><div class="w"><div class="hd"><div><b>🚗 <?= htmlspecialchars($drv['name']) ?></b><br><span><?= htmlspecialchars($s['name'] ?: 'Restaurant') ?> · heute zugestellt: <?= $doneToday ?></span></div><span>aktualisiert <?= $now->format('H:i') ?></span></div>
<?php if(!$orders): ?><div class="empty">Keine offenen Fahrten.<br>Die Seite aktualisiert sich automatisch.</div><?php endif; ?>
<?php foreach($orders as $o): ?><div class="o"><div class="top"><span class="code"><?= $o['code'] ?></span><span class="st <?= $o['status']==='out_for_delivery'?'out':'' ?>"><?= $o['status']==='out_for_delivery'?'unterwegs':'bereit' ?></span><span class="eta">⏱ <?= htmlspecialchars(($o['eta_day'] ? $o['eta_day'].' ' : '').$o['eta']) ?></span></div>
<div class="adr">📍 <?= htmlspecialchars($o['address']) ?></div><div class="meta"><?= htmlspecialchars($o['name']) ?> · <?= htmlspecialchars($o['phone']) ?><?= $o['zone']?' · '.htmlspecialchars($o['zone']):'' ?><?= $o['distance_km']!==null?' · '.$o['distance_km'].' km':'' ?></div>
<div class="items"><?= htmlspecialchars($o['items_text']) ?></div><?php if($o['note']): ?><div class="meta">📝 <?= htmlspecialchars($o['note']) ?></div><?php endif; ?><div class="tot"><?= $o['total'] ?> · <?= htmlspecialchars($o['payment']) ?><?= $o['payment']==='bar'?' (kassieren)':' (bezahlt)' ?></div>
<div class="btns"><a class="b nav" href="<?= htmlspecialchars($o['maps']) ?>" target="_blank">🗺 Navigation</a><a class="b call" href="tel:<?= htmlspecialchars($o['phone']) ?>">📞 Anrufen</a><form method="post" style="display:contents"><input type="hidden" name="done" value="<?= $o['id'] ?>"><button class="b done" type="submit">✅ Zugestellt</button></form></div></div><?php endforeach; ?>
</div></body></html>
