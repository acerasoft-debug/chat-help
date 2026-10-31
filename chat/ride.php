<?php
/**
 * ChatHelp — Fahrtstatus für Taxi-Kunden (öffentlich per Fahrtcode): ride.php?c=CODE
 * Überschrift je Status, große Wartezeit bzw. Abholzeit, Zeitleiste (Eingegangen → Fahrer zugeteilt → Anfahrt → Taxi ist da → Fahrt läuft → Angekommen),
 * Fahrerkarte mit Fahrzeug und Kennzeichen, Route, Richtpreis, Zentrale anrufen, Storno (solange erlaubt, POST + Redirect).
 * Aktualisiert sich alle 20 s, solange die Fahrt läuft. Unbekannter Code → freundliche 404-Seite.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/ride-lib.php';
require_once __DIR__.'/i18n.php';
th_nocache(); th_bootstrap(); th_fresh();
$code=strtoupper((string)preg_replace('/[^A-Za-z0-9]/','',ride_str($_GET['c'] ?? '')));   // ride_str: ?c[]=… ergibt '' statt PHP-Warnung
$r=($code!=='' && strlen($code)<=8) ? ride_by_code($code) : null;   // Code = Zugang des Kunden; Konto ergibt sich aus der Fahrt
if($r && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['do'] ?? '')==='cancel'){
    $res=ride_cancel_customer((int)$r['account_id'],(string)$r['code']);
    $ql=ch_lang_norm(is_string($_GET['lang'] ?? null) ? $_GET['lang'] : '');
    header('Location: ?c='.rawurlencode((string)$r['code']).($ql!=='' ? '&lang='.$ql : '').'&_='.dechex(time()).(isset($res['error']) ? '&e=1' : '&x=1'),true,303); exit;
}
if($r && (int)$r['account_id']===0) $r=ride_demo_tick($r);   // Demo: Fahrt läuft automatisch ab
$s=$r ? ride_settings((int)$r['account_id']) : null; $p=$r ? ride_public($r,$s) : null;
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$tz=$s ? ride_tz($s) : new DateTimeZone('Europe/Berlin'); $now=new DateTimeImmutable('now',$tz);
if(!$p){
    http_response_code(404); header('Content-Type: text/html; charset=UTF-8');
}else header('Content-Type: text/html; charset=UTF-8');
$st=$p['status'] ?? ''; $asap=$p['asap'] ?? true; $live=$p && $p['active'];
[$defL,$langs]=ch_account_langs($r ? (int)$r['account_id'] : 0); $L=ch_page_lang($langs,$defL);   // ?lang= → Cookie → Accept-Language → CF-IPCountry → Standard des Betriebs
$t=fn($k,$v=[])=>t($k,$L,$v); $clock=fn($hm)=>t('clock',$L,['t'=>$hm]);
$first=$p ? (explode(' ',trim((string)$p['name']))[0] ?? '') : '';
$h1=$st==='new' ? $t($asap ? 'r.h.new_asap' : 'r.h.new_pre') : (in_array($st,['assigned','en_route','arrived','onboard','completed','cancelled','no_show'],true) ? $t('r.h.'.$st) : $t('r.h.def'));
$steps=[['new',$t('r.s.new'),$t($asap ? 'r.s.new_asap' : 'r.s.new_pre')],['assigned',$t('r.s.assigned'),$t('r.s.assigned_d')],
        ['en_route',$t('r.s.en_route'),$t('r.s.en_route_d')],['arrived',$t('r.s.arrived'),$t('r.s.arrived_d')],['onboard',$t('r.s.onboard'),$t('r.s.onboard_d')],['completed',$t('r.s.completed'),$t('r.s.completed_d')]];
/* Texte aus ride-lib (deutsch) in der Seitensprache neu bilden: Preis, Zahlung, Strecke, Zeitpunkt */
$priceText=''; $payText=''; $distText=''; $whenLong=''; $whenShort='';
if($p){
    $pc=$p['price_cents']; $pk=(string)$p['price_kind'];
    $priceText=$L==='de' ? (string)$p['price_text'] : ($pc===null || $pk==='' ? $t('r.meter') : ($pk==='fixed' ? $t('r.fixed',['p'=>ch_fmt_money((int)$pc,$L)]) : $t('r.approx',['p'=>ch_fmt_money((int)$pc,$L)])));
    $payText=$L==='de' ? (string)$p['payment_label'] : (isset(CH_I18N['de']['pay.'.$p['payment']]) ? $t('pay.'.$p['payment']) : (string)$p['payment_label']);
    $distText=$L==='de' || $p['distance_km']===null ? (string)$p['distance_text'] : ($p['duration_min']!==null ? $t('r.dist',['km'=>ch_fmt_num((float)$p['distance_km'],1,$L).' km','n'=>(int)$p['duration_min']]) : ch_fmt_num((float)$p['distance_km'],1,$L).' km');
    $pat=$p['pickup_at'] ? ride_dt((string)$r['pickup_at'],$tz) : null;
    $whenLong=$asap ? $t('r.now') : ($pat ? ch_fmt_when($pat,$now,$L) : '');
    $whenShort=$pat ? ch_fmt_date($pat,$L,'short').' '.$pat->format('H:i') : '';
}
$idx=-1; foreach($steps as $i=>$x) if($x[0]===$st) $idx=$i;
$plateLetter=$s ? (['Europe/Berlin'=>'D','Europe/Vienna'=>'A'][$s['tz']] ?? '') : '';
$telZ=$s && $s['phone']!=='' ? preg_replace('/[^0-9+]/','',$s['phone']) : '';
?><!DOCTYPE html><html lang="<?= $e(CH_LANGS[$L]['tag']) ?>"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $p ? $e($t('r.title',['code'=>$p['code']])).' · '.$e($p['company'] ?: $t('r.taxi')) : $e($t('r.nf')) ?></title><meta name="robots" content="noindex"><meta name="theme-color" content="#f6f7fb">
<link rel="stylesheet" href="/fonts/fonts.css">
<style>
:root{--ink:#0f1222;--ink2:#3b4055;--mute:#6b7083;--line:#e4e6ee;--gold:#b8862b;--goldl:#f6ecd3;--ok:#1a9e5a;--okl:#e4f5ea;--rose:#9f2448;--rosel:#fde7ee;--taxi:#f5c518}
*{box-sizing:border-box}html{overflow-x:hidden}body{margin:0;background:#f6f7fb;font:15px/1.55 Inter,system-ui,sans-serif;color:var(--ink);-webkit-font-smoothing:antialiased}
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
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;padding:12px 16px;border-radius:12px;border:1px solid #d6d9e4;background:#fff;color:var(--ink);font:600 14px Inter;text-decoration:none;cursor:pointer}
.btn.p{background:var(--ink);color:#fff;border-color:var(--ink)}.btn.d{color:var(--rose);border-color:#f7c6d5;background:var(--rosel)}.row{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
.cx{padding:16px;border-radius:16px;background:var(--rosel);color:var(--rose);font-weight:600;margin:16px 0}.okb{padding:16px;border-radius:16px;background:var(--okl);color:#15703f;font-weight:600;margin:16px 0}
.foot{text-align:center;color:var(--mute);font-size:12.5px;margin-top:16px}.foot a{color:var(--gold);text-decoration:none}
.pulse{display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--ok);margin:0 5px 1px 2px;animation:p 1.6s infinite}@keyframes p{0%{box-shadow:0 0 0 0 rgba(26,158,90,.5)}70%{box-shadow:0 0 0 8px rgba(26,158,90,0)}}
@media(max-width:420px){.c{padding:20px}.hero b{font-size:26px}.drv{flex-wrap:wrap}.plate{margin-left:62px}}
</style></head><body><?= ch_lang_switcher($L,$langs) ?><div class="w"><div class="c">
<?php if(!$p): ?>
  <div class="eb"><i>🚕</i><?= $e($t('r.eb')) ?></div><h1><?= $e($t('r.nf')) ?></h1>
  <?php if($code!==''): ?><p style="margin:0 0 6px;color:var(--ink2)"><?= $e($t('r.code')) ?> <b style="color:var(--ink)"><?= $e($code) ?></b></p><?php endif; ?>
  <p style="color:var(--mute)"><?= $e($t('r.nf_txt')) ?></p>
<?php else: ?>
  <div class="eb"><i>🚕</i><?= $e($p['company'] ?: $t('r.company')) ?></div>
  <h1><?= $e($h1) ?></h1>
  <div class="sub"><?= $first!=='' ? $e($t('r.hello',['name'=>$first])).' · ' : '' ?><?= $e($t('r.code')) ?> <b><?= $e($p['code']) ?></b> · <?= $e($t('ordered',['time'=>$clock($p['created'])])) ?><?= $live ? ' · <span class="pulse"></span>'.$e($t('live')) : '' ?></div>
  <?php if(isset($_GET['x']) && $st==='cancelled'): ?><div class="okb" role="status"><?= $e($t('r.cx_ok')) ?></div><?php endif; ?>
  <?php if(isset($_GET['e'])): ?><div class="cx" role="alert"><?= $e($t('r.cx_late')) ?><?= $s['phone']!=='' ? ' '.$e($t('r.callz',['phone'=>$s['phone']])) : '' ?></div><?php endif; ?>
  <?php if($st==='cancelled' && !isset($_GET['x'])): ?><div class="cx"><?= $e($t($p['cancel_by']==='Kunde' ? 'r.cx_you' : 'r.cx_was')) ?><?= $s['phone']!=='' ? ' '.$e($t('q',['phone'=>$s['phone']])) : '' ?></div><?php endif; ?>
  <?php if($st==='no_show'): ?><div class="cx"><?= $e($t('r.noshow')) ?><?= $s['phone']!=='' ? ' '.$e($t('r.noshow_call',['phone'=>$s['phone']])) : '' ?></div><?php endif; ?>

  <?php if(in_array($st,['new','assigned','en_route'],true)): ?>
    <?php if($asap): ?>
      <div class="hero"><div class="ic">⏱</div><div><small><?= $e($t('r.due')) ?></small><b><?= $e($p['due_in_min']!==null && $p['due_in_min']>0 ? $t('r.in',['n'=>$p['due_in_min']]) : $t('r.soon')) ?></b><small><?= $p['pickup_time']!=='' && ($p['due_in_min'] ?? 0)>0 ? $e($t('r.around',['time'=>$p['pickup_time']])).' · ' : '' ?><?= $e($t('r.ready')) ?></small></div></div>
    <?php else: ?>
      <?php $atDt=ride_dt((string)$r['pickup_at'],$tz); ?>
      <div class="hero"><div class="ic">📅</div><div><small><?= $e($t('r.pickup')) ?></small><b><?= $atDt ? $e(ch_fmt_date($atDt,$L,'short').' · '.$clock($atDt->format('H:i'))) : '' ?></b><small><?= $e(mb_strtoupper(mb_substr($whenLong,0,1)).mb_substr($whenLong,1)) ?> · <?= $e($t('r.punctual')) ?></small></div></div>
    <?php endif; ?>
  <?php elseif($st==='arrived'): ?>
    <div class="hero ok"><div class="ic">🚖</div><div><small><?= $e($t('r.waiting')) ?></small><b><?= $p['driver'] && $p['driver']['plate']!=='' ? $e($p['driver']['plate']) : $e($t('r.here')) ?></b><small><?= $p['driver'] ? $e($p['driver']['vehicle']) : $e($t('r.come')) ?></small></div></div>
  <?php endif; ?>

  <?php if($p['driver'] && !in_array($st,['cancelled','no_show'],true)): $dv=$p['driver']; ?>
    <div class="drv"><div class="av"><?= $e(mb_strtoupper(mb_substr($dv['name'],0,1))) ?></div><div><div class="nm"><?= $e($dv['name']) ?></div><div class="car"><?= $e($dv['vehicle'] ?: $t('r.taxi')) ?></div></div>
      <?php if($dv['plate']!==''): ?><div class="plate" title="<?= $e($t('r.plate')) ?>"><?php if($plateLetter!==''): ?><i><?= $e($plateLetter) ?></i><?php endif; ?><span><?= $e($dv['plate']) ?></span></div><?php endif; ?></div>
  <?php endif; ?>

  <div class="route">
    <div class="pt"><span class="dot"></span><div><small><?= $e($t('r.pickup')) ?><?= !$asap && $p['pickup_time']!=='' ? ' · '.$e($L==='de' ? $p['when'].' Uhr' : $whenShort) : '' ?></small><b><?= $e($p['pickup']) ?></b><?php if($p['pickup_geo']!=='' && $p['pickup_geo']!==$p['pickup']): ?><small><?= $e($p['pickup_geo']) ?></small><?php endif; ?></div></div>
    <div class="ln"></div>
    <div class="pt"><span class="dot d"></span><div><small><?= $e($t('r.dest')) ?></small><b><?= $p['destination']!=='' ? $e($p['destination']) : $e($t('r.tell')) ?></b><?php if($distText!==''): ?><small><?= $e($distText) ?></small><?php endif; ?></div></div>
  </div>

  <dl class="kv">
    <dt><?= $e($t('r.vehicle')) ?></dt><dd><?= $e(ch_tn((int)$p['passengers'],'person1','persons',$L)) ?> · <?= $e($p['vehicle_name']) ?></dd>
    <?php if($p['extras']!==''): ?><dt><?= $e($t('r.extras')) ?></dt><dd><?= $e($p['extras']) ?></dd><?php endif; ?>
    <?php if($p['luggage']!==''): ?><dt><?= $e($t('r.luggage')) ?></dt><dd><?= $e($p['luggage']) ?></dd><?php endif; ?>
    <dt><?= $e($t('r.price')) ?></dt><dd><b><?= $e($priceText) ?></b></dd>
    <dt><?= $e($t('r.payment')) ?></dt><dd><?= $e($payText) ?></dd>
    <?php if($p['note']!==''): ?><dt><?= $e($t('r.note')) ?></dt><dd><?= $e($p['note']) ?></dd><?php endif; ?>
  </dl>

  <?php if(!in_array($st,['cancelled','no_show'],true)): ?>
  <ul class="tl"><?php foreach($steps as $i=>[$k,$lbl,$txt]): $cls=$i<$idx ? 'done' : ($i===$idx ? ($st==='completed' ? 'done' : 'now') : 'todo'); ?>
    <li class="<?= $cls ?>"<?= $cls==='now' ? ' aria-current="step"' : '' ?>><i><?= $cls==='done' ? '✓' : ($i+1) ?></i><div><b><?= $e($lbl) ?></b><small><?= $e($txt) ?></small></div><?php if(isset($p['times'][$k]) && $cls!=='todo'): ?><span class="tm"><?= $e($clock($p['times'][$k])) ?></span><?php endif; ?></li>
  <?php endforeach; ?></ul>
  <?php endif; ?>

  <div class="row">
    <?php if($telZ!==''): ?><a class="btn p" href="tel:<?= $e($telZ) ?>"><?= $e($t('r.call')) ?></a><?php endif; ?>
    <?php if($p['maps_pickup'] && $live): ?><a class="btn" href="<?= $e($p['maps_pickup']) ?>" target="_blank" rel="noopener"><?= $e($t('r.map')) ?></a><?php endif; ?>
    <?php if($p['can_cancel']): ?><form method="post" action="?c=<?= rawurlencode($p['code']) ?>&amp;lang=<?= $e($L) ?>&amp;_=<?= dechex(time()) ?>" onsubmit="return confirm(<?= $e(json_encode($t('r.confirm',['code'=>$p['code']]),JSON_UNESCAPED_UNICODE)) ?>)"><input type="hidden" name="do" value="cancel"><button class="btn d" type="submit"><?= $e($t('r.cancel')) ?></button></form><?php endif; ?>
  </div>
<?php endif; ?>
</div><div class="foot"><?= $t('r.foot',['link'=>'<a href="https://chat-help.com/">ChatHelp</a>','time'=>$e($now->format('H:i'))]) ?><?= $live ? ' · '.$e($t('r.auto')) : '' ?></div></div>
<?php if($live): ?><script>setTimeout(function(){location.replace('?c=<?= rawurlencode($p['code']) ?>&lang=<?= $e($L) ?>&_='+Date.now().toString(36));},20000);</script><?php endif; ?>
</body></html>
