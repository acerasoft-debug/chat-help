<?php
/**
 * ChatHelp — Buchungsübersicht für Hotelgäste (öffentlich per Buchungscode): stay.php?c=CODE[&ics=1]
 * Status (Anfrage eingegangen · Bestätigt · Eingecheckt · Storniert …), Anreise/Abreise mit Wochentag und Zeiten, Zimmer, Gäste,
 * Preisaufschlüsselung, Adresse mit Route, Telefon der Rezeption, Hausinfos, kostenfreie Stornierung innerhalb der Frist (POST + Redirect)
 * und Kalender-Datei (&ics=1, ganztägig). Seite immer frisch (th_nocache + th_fresh), ICS ausgenommen.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/hotel-lib.php';
th_nocache(); th_bootstrap(); hotel_bootstrap(); if(!isset($_GET['ics'])) th_fresh();
$code=strtoupper(preg_replace('/[^A-Za-z0-9]/','',is_string($_GET['c'] ?? null) ? $_GET['c'] : ''));
$b=$code!=='' ? hotel_by_code($code) : null; $s=$b ? hotel_settings((int)$b['account_id']) : null;
if(isset($_GET['ics'])){
    if(!$b){ http_response_code(404); header('Content-Type: text/plain; charset=UTF-8'); exit("Buchung nicht gefunden.\n"); }
    header('Content-Type: text/calendar; charset=UTF-8'); header('Content-Disposition: attachment; filename="aufenthalt-'.$b['code'].'.ics"'); echo hotel_ics($b,$s); exit;
}
if($b && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['do'] ?? '')==='cancel'){
    $r=hotel_guest_cancel($b,'guest');
    header('Location: ?c='.rawurlencode($b['code']).'&_='.dechex(time()).'&x='.(isset($r['error']) ? '0' : '1'),true,303); exit;
}
if(!$b) http_response_code(404);
$p=$b ? hotel_public($b,$s) : null; $e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); $eur=fn($c)=>number_format(((int)$c)/100,2,',','.').' €';
$first=$p ? (preg_split('/\s+/u',trim(preg_replace('/^(herr|frau|dr\.?|familie|fam\.?)\s+/iu','',(string)$p['name'])))[0] ?? '') : '';
$st=$p['status'] ?? '';
$head=['requested'=>'Ihre Anfrage ist eingegangen','confirmed'=>'Wir freuen uns auf Sie'.($first!=='' ? ', '.$first : '').'!','checked_in'=>'Willkommen im Haus'.($first!=='' ? ', '.$first : '').'!',
       'checked_out'=>'Danke für Ihren Besuch!','cancelled'=>'Buchung storniert','declined'=>'Anfrage leider nicht möglich','no_show'=>'Aufenthalt nicht angetreten'][$st] ?? 'Ihre Buchung';
$pill=['requested'=>'ind','confirmed'=>'ok','checked_in'=>'ok','checked_out'=>'neu','cancelled'=>'ros','declined'=>'ros','no_show'=>'ros'][$st] ?? 'neu';
$steps=[['requested','Anfrage'],['confirmed','Bestätigt'],['checked_in','Eingecheckt'],['checked_out','Abgereist']];
$idx=array_search($st,array_column($steps,0),true);
$live=$st==='requested'; $active=in_array($st,['requested','confirmed','checked_in'],true);
$mails=$b ? !hotel_silent((int)$b['account_id'],(string)$b['channel']) : false;   // Live-Demo: es werden keine E-Mails versendet
$monS=['Jan.','Feb.','März','Apr.','Mai','Juni','Juli','Aug.','Sept.','Okt.','Nov.','Dez.'];
$dayParts=function(string $ymd) use($monS): array { $d=hotel_day($ymd); return [HOTEL_WD[(int)$d->format('w')],(int)$d->format('j').'. '.$monS[(int)$d->format('n')-1],$d->format('Y')]; };
$tz=$s ? hotel_tz($s) : new DateTimeZone('Europe/Berlin');
header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $p ? 'Ihr Aufenthalt · '.$e($s['name'] ?: 'Hotel') : 'Buchung nicht gefunden' ?></title><meta name="robots" content="noindex">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,600&display=swap">
<style>
:root{--ink:#0f1222;--ink2:#3b4055;--mute:#6b7083;--line:#e4e6ee;--gold:#b8862b;--goldl:#f6ecd3;--ok:#1a9e5a;--okl:#e4f5ea;--rose:#9f2448;--rosel:#fde7ee;--ind:#4f46e5;--indl:#eceafe;--bg:#f6f7fb}
*{box-sizing:border-box}body{margin:0;background:var(--bg);font:15px/1.55 Inter,system-ui,sans-serif;color:var(--ink);-webkit-font-smoothing:antialiased}
a{color:var(--gold)}.w{max-width:640px;margin:22px auto 60px;padding:0 16px}
.hero{position:relative;overflow:hidden;background:linear-gradient(140deg,#161c3b 0%,#0f1222 58%,#2b2240 100%);color:#fff;border-radius:26px 26px 0 0;padding:26px 24px 24px}
.hero::after{content:"";position:absolute;right:-70px;top:-80px;width:260px;height:260px;border-radius:50%;background:radial-gradient(circle,rgba(227,194,122,.32),transparent 68%);pointer-events:none}
.eb{font:700 12px Inter;letter-spacing:.14em;text-transform:uppercase;color:#e3c27a}
h1{font:600 clamp(25px,6vw,34px)/1.15 Fraunces,Georgia,serif;margin:10px 0 10px;letter-spacing:-.01em}
.sub{color:#c6c9d9;font-size:14px}.sub b{color:#fff;letter-spacing:.06em}
.pill{display:inline-flex;align-items:center;gap:7px;padding:6px 12px;border-radius:999px;font:700 11.5px Inter;letter-spacing:.08em;text-transform:uppercase;margin-top:14px}
.pill::before{content:"";width:7px;height:7px;border-radius:50%;background:currentColor}
.pill.ok{background:rgba(26,158,90,.18);color:#6fe0a4}.pill.ind{background:rgba(129,120,255,.2);color:#c4bfff}.pill.ros{background:rgba(224,72,111,.2);color:#ffb3c6}.pill.neu{background:rgba(255,255,255,.12);color:#e6e8f5}
.pill.ind::before{animation:pu 1.6s infinite}@keyframes pu{50%{opacity:.35}}
.c{background:#fff;border:1px solid var(--line);border-top:0;border-radius:0 0 26px 26px;padding:22px 24px 24px;box-shadow:0 10px 30px rgba(15,18,34,.07)}
.steps{display:flex;gap:6px;margin:2px 0 20px}.steps div{flex:1}.steps i{display:block;height:5px;border-radius:9px;background:var(--line)}.steps div.on i{background:var(--gold)}.steps span{display:block;font-size:11.5px;color:var(--mute);margin-top:6px;font-weight:600}.steps div.on span{color:var(--ink)}
.dates{display:grid;grid-template-columns:1fr auto 1fr;gap:10px;align-items:center;padding:16px 18px;border-radius:18px;background:var(--goldl)}
.dates .d span{display:block;font:700 11px Inter;letter-spacing:.1em;text-transform:uppercase;color:#8a6420}.dates .d b{display:block;font:600 22px/1.2 Fraunces,Georgia,serif;margin:4px 0 2px}.dates .d small{color:var(--ink2);font-size:13px}
.dates .d.r{text-align:right}.dates .n{text-align:center;color:#8a6420;font-weight:700;font-size:13px;white-space:nowrap}.dates .n i{display:block;font-style:normal;font-size:20px;line-height:1;color:var(--gold)}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:14px}.cell{border:1px solid var(--line);border-radius:14px;padding:11px 13px}.cell span{display:block;color:var(--mute);font-size:12px;font-weight:600}.cell b{font-size:15px}
.cell.room{grid-column:1/-1}.roomno{display:inline-block;margin-left:8px;padding:2px 9px;border-radius:999px;background:var(--ink);color:#fff;font-size:12.5px;font-weight:700}
h2{font:600 18px Fraunces,Georgia,serif;margin:22px 0 8px}
.pr .r{display:flex;justify-content:space-between;gap:12px;padding:8px 0;border-bottom:1px solid var(--line);font-size:14.5px}.pr .r small{color:var(--mute)}.pr .t{font-weight:800;font-size:16.5px;border-bottom:0;padding-top:12px}
.box{padding:14px 16px;border-radius:16px;font-size:14px;margin-top:16px}.box.info{background:var(--indl);color:#3730a3}.box.ok{background:var(--okl);color:#15703f}.box.err{background:var(--rosel);color:var(--rose)}.box.neu{background:#f1f2f7;color:var(--ink2)}
.pol{white-space:pre-line;color:var(--ink2);font-size:14px;background:#fafbfd;border:1px solid var(--line);border-radius:14px;padding:12px 14px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 16px;border-radius:12px;border:1px solid #d6d9e4;background:#fff;color:var(--ink);font:600 14px Inter;text-decoration:none;cursor:pointer}
.btn.p{background:var(--ink);color:#fff;border-color:var(--ink)}.btn.d{color:var(--rose);border-color:#f7c6d5;background:var(--rosel)}.row{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}.row form{margin:0}
.addr{display:flex;gap:12px;align-items:flex-start;color:var(--ink2);font-size:14px}.addr b{color:var(--ink)}
.foot{text-align:center;color:var(--mute);font-size:12.5px;margin-top:16px}.foot a{color:var(--gold);text-decoration:none}
@media(max-width:520px){.hero,.c{padding-left:18px;padding-right:18px}.row>.btn,.row>form{flex:1 1 100%}.row form .btn{width:100%}.dates{grid-template-columns:1fr 1fr}.dates .n{grid-column:1/-1;grid-row:2;text-align:left}.dates .n i{display:inline;margin-left:6px}.dates .d b{font-size:19px}.grid{grid-template-columns:1fr}}
</style></head><body><div class="w">
<?php if(!$p): ?>
  <div class="hero"><div class="eb">Ihr Aufenthalt</div><h1>Buchung nicht gefunden</h1><div class="sub">Der Buchungscode ist ungültig oder die Buchung wurde entfernt.</div></div>
  <div class="c"><p style="margin:0;color:var(--ink2)">Bitte prüfen Sie den Link aus Ihrer Bestätigungs-E-Mail oder wenden Sie sich direkt an Ihr Hotel. Den Buchungscode finden Sie in der Bestätigung (8 Zeichen, z. B. <b>K7M2QX4P</b>).</p></div>
<?php else: [$ciW,$ciD,$ciY]=$dayParts($p['checkin']); [$coW,$coD,$coY]=$dayParts($p['checkout']); $pr=$p['price']; ?>
  <div class="hero">
    <div class="eb"><?= $e($s['name'] ?: 'Ihr Hotel') ?></div>
    <h1><?= $e($head) ?></h1>
    <div class="sub">Buchungscode <b><?= $e($p['code']) ?></b> · <?= $e($p['name']) ?></div>
    <span class="pill <?= $pill ?>"><?= $e($p['status_label']) ?></span>
  </div>
  <div class="c">
    <?php if(isset($_GET['x']) && $_GET['x']==='1' && $st==='cancelled'): ?><div class="box ok" style="margin:0 0 16px">Ihre Buchung wurde kostenfrei storniert.<?= $p['email'] && $mails ? ' Eine Bestätigung ist per E-Mail unterwegs.' : '' ?></div><?php endif; ?>
    <?php if(isset($_GET['x']) && $_GET['x']==='0'): ?><div class="box err" style="margin:0 0 16px">Eine kostenfreie Stornierung ist nicht mehr möglich. Bitte wenden Sie sich direkt an die Rezeption<?= $s['phone'] ? ' ('.$e($s['phone']).')' : '' ?>.</div><?php endif; ?>
    <?php if($idx!==false): ?><div class="steps"><?php foreach($steps as $i=>[$k,$lbl]): ?><div class="<?= $i<=$idx ? 'on' : '' ?>"><i></i><span><?= $e($lbl) ?></span></div><?php endforeach; ?></div><?php endif; ?>
    <div class="dates">
      <div class="d"><span>Anreise</span><b><?= $e($ciW.', '.$ciD) ?></b><small><?= $e($ciY) ?> · ab <?= $e($p['checkin_from']) ?> Uhr</small></div>
      <div class="n"><?= (int)$p['nights'] ?> <?= $p['nights']===1 ? 'Nacht' : 'Nächte' ?><i>→</i></div>
      <div class="d r"><span>Abreise</span><b><?= $e($coW.', '.$coD) ?></b><small><?= $e($coY) ?> · bis <?= $e($p['checkout_until']) ?> Uhr</small></div>
    </div>
    <div class="grid">
      <div class="cell room"><span>Zimmer</span><b><?= $e($p['room']) ?></b><?= $p['room_no']!=='' && in_array($st,['checked_in','checked_out'],true) ? '<span class="roomno">Zimmer '.$e($p['room_no']).'</span>' : '' ?></div>
      <div class="cell"><span>Gäste</span><b><?= (int)$p['guests'] ?> <?= $p['guests']===1 ? 'Person' : 'Personen' ?></b></div>
      <div class="cell"><span>Frühstück</span><b><?= $e(ucfirst($p['breakfast_text'])) ?></b></div>
    </div>
    <?php if($st==='requested'): ?><div class="box info">Die Rezeption prüft Ihre Anfrage und bestätigt sie in Kürze<?= $p['email'] && $mails ? ' per E-Mail an <b>'.$e($p['email']).'</b>' : '' ?>. Das Zimmer ist für Sie vorgemerkt – diese Seite aktualisiert sich automatisch.</div>
    <?php elseif($st==='confirmed'): ?><div class="box ok">Ihre Buchung ist bestätigt. Check-in ab <?= $e($p['checkin_from']) ?> Uhr – wir freuen uns auf Sie!</div>
    <?php elseif($st==='checked_in'): ?><div class="box ok">Schön, dass Sie da sind! Wünsche während Ihres Aufenthalts (Handtücher, Weckruf, Taxi …) nimmt die Rezeption jederzeit entgegen – auch per Chat oder Telefon. Check-out bis <?= $e($p['checkout_until']) ?> Uhr.</div>
    <?php elseif($st==='declined'): ?><div class="box err">Leider können wir Ihre Anfrage für diesen Zeitraum nicht bestätigen. Gern finden wir einen anderen Termin für Sie<?= $s['phone'] ? ' – rufen Sie uns an: '.$e($s['phone']) : '' ?>.</div>
    <?php elseif($st==='cancelled' && !isset($_GET['x'])): ?><div class="box neu">Diese Buchung wurde storniert.</div>
    <?php elseif($st==='checked_out'): ?><div class="box neu">Vielen Dank für Ihren Aufenthalt – wir freuen uns auf ein Wiedersehen.</div><?php endif; ?>

    <h2>Preis</h2>
    <div class="pr">
      <?php if(is_array($pr) && !empty($pr['nights'])): $lines=$pr['nights'];
        if(count($lines)<=7){ foreach($lines as $n): ?><div class="r"><span><?= $e($n['label'] ?? '') ?><?= !empty($n['weekend']) ? ' <small>· Wochenende</small>' : '' ?></span><span><?= $eur($n['cents'] ?? 0) ?></span></div><?php endforeach; }
        else { $grp=[]; foreach($lines as $n){ $k=(string)(int)($n['cents'] ?? 0); $grp[$k]=($grp[$k] ?? 0)+1; } foreach($grp as $c=>$cnt): ?><div class="r"><span><?= (int)$cnt ?> <?= $cnt===1 ? 'Nacht' : 'Nächte' ?> × <?= $eur($c) ?></span><span><?= $eur((int)$c*$cnt) ?></span></div><?php endforeach; } ?>
        <?php if(!empty($pr['breakfast_cents'])): ?><div class="r"><span>Frühstück <small>· <?= (int)$p['guests'] ?> Pers. × <?= (int)$p['nights'] ?> <?= $p['nights']===1 ? 'Nacht' : 'Nächte' ?></small></span><span><?= $eur($pr['breakfast_cents']) ?></span></div>
        <?php elseif(($pr['breakfast'] ?? '')==='inklusive'): ?><div class="r"><span>Frühstück</span><span>inklusive</span></div><?php endif; ?>
        <?php if(!empty($pr['city_tax_cents'])): ?><div class="r"><span>City-Tax <small>· <?= $e(number_format((float)($pr['city_tax_pct'] ?? 0),2,',','.')) ?> %</small></span><span><?= $eur($pr['city_tax_cents']) ?></span></div><?php endif; ?>
      <?php endif; ?>
      <div class="r t"><span>Gesamt</span><span><?= $e($p['total']) ?></span></div>
    </div>

    <?php if(in_array($st,['requested','confirmed'],true)): ?>
      <h2>Stornierung</h2>
      <?php if($p['can_cancel']): ?><p style="margin:0;color:var(--ink2);font-size:14px">Kostenfrei stornierbar bis <b><?= $e($p['cancel_until_human']) ?></b>.</p>
      <?php else: ?><p style="margin:0;color:var(--ink2);font-size:14px">Die Frist für die kostenfreie Stornierung ist am <?= $e($p['cancel_until_human']) ?> abgelaufen. Für Änderungen wenden Sie sich bitte direkt an die Rezeption.</p><?php endif; ?>
    <?php endif; ?>

    <?php if(trim($s['policies'])!==''): ?><h2>Gut zu wissen</h2><div class="pol"><?= $e($s['policies']) ?></div><?php endif; ?>

    <?php if($s['address'] || $s['phone']): ?><h2>So finden Sie uns</h2>
      <div class="addr"><div style="font-size:20px">📍</div><div><b><?= $e($s['name']) ?></b><?= $s['address'] ? '<br>'.$e($s['address']) : '' ?><?= $s['phone'] ? '<br>Rezeption: <a href="tel:'.$e(preg_replace('/[^0-9+]/','',$s['phone'])).'">'.$e($s['phone']).'</a>' : '' ?></div></div>
    <?php endif; ?>
    <div class="row">
      <?php if($active): ?><a class="btn p" href="?c=<?= $e(rawurlencode($p['code'])) ?>&amp;ics=1">📅 In den Kalender</a><?php endif; ?>
      <?php if($s['phone']): ?><a class="btn" href="tel:<?= $e(preg_replace('/[^0-9+]/','',$s['phone'])) ?>">📞 Rezeption anrufen</a><?php endif; ?>
      <?php if($s['address']): ?><a class="btn" href="https://www.google.com/maps/dir/?api=1&amp;destination=<?= $e(rawurlencode($s['address'])) ?>" target="_blank" rel="noopener">🗺 Route planen</a><?php endif; ?>
      <?php if($p['can_cancel']): ?><form method="post" action="?c=<?= $e(rawurlencode($p['code'])) ?>&amp;_=<?= dechex(time()) ?>" onsubmit="return confirm('Buchung <?= $e($p['code']) ?> wirklich kostenfrei stornieren?')"><input type="hidden" name="do" value="cancel"><button class="btn d" type="submit">Kostenfrei stornieren</button></form><?php endif; ?>
    </div>
  </div>
<?php endif; ?>
<div class="foot">Digitale Rezeption mit <a href="https://chat-help.com/">ChatHelp</a> · Stand <?= (new DateTimeImmutable('now',$tz))->format('H:i') ?> Uhr</div>
</div>
<?php if($live): ?><script>setTimeout(function(){location.replace('?c=<?= $e(rawurlencode($p['code'])) ?>&_='+Date.now().toString(36));},60000);</script><?php endif; ?>
</body></html>
