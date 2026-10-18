<?php
/**
 * ChatHelp — Bestellstatus für Kunden (öffentlich per Bestellcode): order.php?c=CODE
 * Status-Zeitleiste (Eingegangen → Bestätigt → In Zubereitung → Fertig → Unterwegs → Zugestellt), voraussichtliche Zeit,
 * Positionen, Summe, Kontakt zum Restaurant. Lädt sich alle 30 s neu, solange die Bestellung läuft. Storno, solange „neu“.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/order-lib.php';
require_once __DIR__.'/i18n.php';
th_nocache(); th_bootstrap(); th_fresh();
$code=strtoupper(preg_replace('/[^A-Za-z0-9]/','',(string)($_GET['c'] ?? '')));
$o=$code!=='' ? ord_by_code($code) : null;
if($o && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['do'] ?? '')==='cancel'){
    if($o['status']==='new') ord_set_status((int)$o['account_id'],(int)$o['id'],'cancelled',null,'customer');   // atomar: kein Storno, falls das Restaurant inzwischen weiter ist
    $ql=ch_lang_norm(is_string($_GET['lang'] ?? null) ? $_GET['lang'] : '');
    header('Location: ?c='.$o['code'].($ql!=='' ? '&lang='.$ql : '').'&_='.dechex(time()).'&x=1',true,303); exit;
}
$s=$o ? ord_settings((int)$o['account_id']) : null; $p=$o ? ord_public($o,$s) : null;
[$defL,$langs]=ch_account_langs($o ? (int)$o['account_id'] : 0); $L=ch_page_lang($langs,$defL);   // ?lang= → Cookie → Accept-Language → CF-IPCountry → Standard des Betriebs
$t=fn($k,$v=[])=>t($k,$L,$v); $money=fn($c)=>ch_fmt_money((int)$c,$L); $clock=fn($hm)=>t('clock',$L,['t'=>$hm]);
$first=$p ? (explode(' ',trim((string)$p['name']))[0] ?: '') : '';
$steps=$p && $p['type']==='pickup'
  ? [['new',$t('o.new'),$t('o.new_d')],['confirmed',$t('o.confirmed'),$t('o.confirmed_d')],['preparing',$t('o.preparing'),$t('o.preparing_d')],['ready',$t('o.ready_p'),$t('o.ready_p_d')],['delivered',$t('o.picked'),$t('o.enjoy')]]
  : [['new',$t('o.new'),$t('o.new_d')],['confirmed',$t('o.confirmed'),$t('o.confirmed_d')],['preparing',$t('o.preparing'),$t('o.preparing_d')],['ready',$t('o.ready'),$t('o.ready_d')],['out_for_delivery',$t('o.out'),$t('o.out_d')],['delivered',$t('o.delivered'),$t('o.enjoy')]];
$pay=$p ? (string)$p['payment'] : ''; $payL=$pay!=='' && isset(CH_I18N['de']['pay.'.strtolower($pay)]) ? $t('pay.'.strtolower($pay)) : $pay;
$idx=-1; if($p){ foreach($steps as $i=>$st) if($st[0]===$p['status']) $idx=$i; }
$live=$p && !in_array($p['status'],['delivered','cancelled'],true);
header('Content-Type: text/html; charset=UTF-8');
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
?><!DOCTYPE html><html lang="<?= $e(CH_LANGS[$L]['tag']) ?>"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $p ? $e($t('o.title',['code'=>$p['code']])).' · '.$e($p['restaurant'] ?: $t('o.eb')) : $e($t('o.nf')) ?></title><meta name="robots" content="noindex">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,600&display=swap">
<style>
:root{--ink:#0f1222;--ink2:#3b4055;--mute:#6b7083;--line:#e4e6ee;--gold:#b8862b;--goldl:#f6ecd3;--ok:#1a9e5a;--okl:#e4f5ea;--rose:#9f2448;--rosel:#fde7ee}
*{box-sizing:border-box}html{overflow-x:hidden}body{margin:0;background:#f6f7fb;font:15px/1.55 Inter,system-ui,sans-serif;color:var(--ink);-webkit-font-smoothing:antialiased}
.w{max-width:600px;margin:26px auto 60px;padding:0 16px}.c{background:#fff;border:1px solid var(--line);border-radius:24px;padding:24px;box-shadow:0 10px 30px rgba(15,18,34,.07)}
.eb{font:700 12px Inter;letter-spacing:.12em;text-transform:uppercase;color:var(--gold)}h1{font:600 clamp(26px,6vw,34px)/1.15 Fraunces,Georgia,serif;margin:8px 0 6px}
.eta{display:flex;align-items:center;gap:14px;margin:18px 0;padding:16px 18px;border-radius:18px;background:var(--goldl)}.eta b{font:800 30px Inter}.eta span{color:var(--ink2);font-size:14px}
.tl{list-style:none;margin:18px 0 6px;padding:0}.tl li{display:flex;gap:14px;position:relative;padding-bottom:16px}.tl li:last-child{padding-bottom:0}
.tl li::before{content:"";position:absolute;left:13px;top:28px;bottom:0;width:2px;background:var(--line)}.tl li:last-child::before{display:none}.tl li.done::before{background:var(--ok)}
.tl i{flex:0 0 28px;height:28px;border-radius:50%;display:grid;place-items:center;font-style:normal;font-size:13px;font-weight:800;background:#fff;border:2px solid var(--line);color:var(--mute);z-index:1}
.tl li.done i{background:var(--ok);border-color:var(--ok);color:#fff}.tl li.now i{background:var(--ink);border-color:var(--ink);color:#fff;box-shadow:0 0 0 6px rgba(15,18,34,.08)}
.tl b{display:block;font-size:15px}.tl small{color:var(--mute)}.tl li.todo b{color:var(--mute);font-weight:600}
.items{margin-top:16px;border-top:1px solid var(--line)}.items .r{display:flex;justify-content:space-between;gap:12px;padding:9px 0;border-bottom:1px solid var(--line);font-size:14.5px}.items .r small{display:block;color:var(--mute)}.items .t{font-weight:800;font-size:16px;border-bottom:0}
.meta{color:var(--ink2);font-size:14px;margin-top:12px}.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;padding:12px 16px;border-radius:12px;border:1px solid #d6d9e4;background:#fff;color:var(--ink);font:600 14px Inter;text-decoration:none;cursor:pointer}
.btn.p{background:var(--ink);color:#fff;border-color:var(--ink)}.btn.d{color:var(--rose);border-color:#f7c6d5;background:var(--rosel)}.row{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
.cx{padding:16px;border-radius:16px;background:var(--rosel);color:var(--rose);font-weight:600;margin:16px 0}.foot{text-align:center;color:var(--mute);font-size:12.5px;margin-top:16px}.foot a{color:var(--gold)}
.pulse{display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--ok);margin-right:6px;animation:p 1.6s infinite}@keyframes p{70%{box-shadow:0 0 0 8px rgba(26,158,90,0)}0%{box-shadow:0 0 0 0 rgba(26,158,90,.5)}}
</style></head><body><?= ch_lang_switcher($L,$langs) ?><div class="w"><div class="c">
<?php if(!$p): ?>
  <div class="eb"><?= $e($t('o.eb')) ?></div><h1><?= $e($t('o.nf')) ?></h1><p style="color:var(--mute)"><?= $e($t('o.nf_txt')) ?></p>
<?php else: ?>
  <div class="eb"><?= $e($p['restaurant'] ?: $t('o.order')) ?></div>
  <h1><?= $p['status']==='cancelled' ? $e($t('o.h_cancel')) : ($p['status']==='delivered' ? $e($t($p['type']==='pickup' ? 'o.h_picked' : 'o.h_deliv')) : $e($first!=='' ? $t('o.h_run',['name'=>$first]) : $t('o.h_run0'))) ?></h1>
  <div style="color:var(--mute);font-size:14px"><?= $e($t('o.code')) ?> <b style="color:var(--ink)"><?= $e($p['code']) ?></b> · <?= $e($t($p['type']==='delivery' ? 'o.delivery' : 'o.pickup')) ?> · <?= $e($t('ordered',['time'=>$clock($p['created'])])) ?><?= $live ? ' · <span class="pulse"></span>'.$e($t('live')) : '' ?></div>
  <?php if(isset($_GET['x']) && $p['status']==='cancelled'): ?><div class="cx" role="status"><?= $e($t('o.cx_done')) ?></div><?php elseif(isset($_GET['x'])): ?><div class="cx" role="alert"><?= $e($t('o.cx_late')) ?><?= $s['phone'] ? ' '.$e($t('o.callus',['phone'=>$s['phone']])) : '' ?></div><?php endif; ?>
  <?php if($p['status']==='cancelled'): ?>
    <?php if(!isset($_GET['x'])): ?><div class="cx"><?= $e($t('o.cx_was')) ?><?= $s['phone'] ? ' '.$e($t('q',['phone'=>$s['phone']])) : '' ?></div><?php endif; ?>
  <?php else: ?>
    <?php if($live && $p['eta']): ?><div class="eta"><div style="font-size:28px"><?= $p['type']==='delivery'?'🚗':'🛍️' ?></div><div><span><?= $e($t($p['type']==='delivery' ? 'o.eta_d' : 'o.eta_p')) ?></span><br><b><?= $e(($p['eta_day'] ? ch_de_phrase((string)$p['eta_day'],$L).' ' : '').$clock((string)$p['eta'])) ?></b></div></div><?php endif; ?>
    <ul class="tl"><?php foreach($steps as $i=>[$k,$lbl,$txt]): $cls=$i<$idx?'done':($i===$idx?($p['status']==='delivered'?'done':'now'):'todo'); ?>
      <li class="<?= $cls ?>"<?= $cls==='now' ? ' aria-current="step"' : '' ?>><i><?= $cls==='done'?'✓':($i+1) ?></i><div><b><?= $e($lbl) ?><?= $k==='out_for_delivery' && $p['driver'] && $i===$idx ? ' · '.$e($t('o.with',['name'=>$p['driver']['name']])) : '' ?></b><small><?= $e($txt) ?></small></div></li>
    <?php endforeach; ?></ul>
  <?php endif; ?>
  <div class="items">
    <?php foreach($p['items'] as $it): ?><div class="r"><span><?= (int)$it['qty'] ?>× <?= $e($it['name']) ?><?php if(!empty($it['options'])): ?><small><?= $e(implode(', ',$it['options'])) ?></small><?php endif; ?></span><span><?= $e($money($it['total_cents'])) ?></span></div><?php endforeach; ?>
    <?php if(!empty($p['discount_cents'])): ?><div class="r"><span><?= $e($t('o.disc')) ?></span><span><?= $e($money(-abs((int)$p['discount_cents']))) ?></span></div><?php endif; ?>
    <?php if($p['fee_cents']): ?><div class="r"><span><?= $e($t('o.fee')) ?></span><span><?= $e($money($p['fee_cents'])) ?></span></div><?php endif; ?>
    <div class="r t"><span><?= $e($t('total')) ?> · <?= $e($payL) ?></span><span><?= $e($money($p['total_cents'])) ?></span></div>
  </div>
  <?php if($p['type']==='delivery' && $p['address']): ?><div class="meta">📍 <?= $e($p['address']) ?></div><?php elseif($s['address']): ?><div class="meta">📍 <?= $e($t('o.pickup_at',['addr'=>$s['address']])) ?></div><?php endif; ?>
  <?php if($p['note']): ?><div class="meta">📝 <?= $e($p['note']) ?></div><?php endif; ?>
  <div class="row">
    <?php if($s['phone']): ?><a class="btn p" href="tel:<?= $e(preg_replace('/[^0-9+]/','',$s['phone'])) ?>"><?= $e($t('o.call')) ?></a><?php endif; ?>
    <?php if($p['type']==='pickup' && $s['address']): ?><a class="btn" href="https://www.google.com/maps/dir/?api=1&destination=<?= rawurlencode($s['address']) ?>" target="_blank" rel="noopener"><?= $e($t('o.route')) ?></a><?php endif; ?>
    <?php if($p['status']==='new'): ?><form method="post" onsubmit="return confirm(<?= $e(json_encode($t('o.confirm'),JSON_UNESCAPED_UNICODE)) ?>)"><input type="hidden" name="do" value="cancel"><button class="btn d" type="submit"><?= $e($t('o.cancel')) ?></button></form><?php endif; ?>
  </div>
<?php endif; ?>
</div><div class="foot"><?= $t('o.foot',['link'=>'<a href="https://chat-help.com/#bestellen">ChatHelp</a>','time'=>$e($clock((new DateTimeImmutable('now',$s ? ord_tz($s) : new DateTimeZone('Europe/Berlin')))->format('H:i')))]) ?></div></div>
<?php if($live): ?><script>setTimeout(function(){location.replace('?c=<?= $e($p['code']) ?>&lang=<?= $e($L) ?>&_='+Date.now().toString(36));},30000);</script><?php endif; ?>
</body></html>
