<?php
/** ChatHelp — Termin ansehen / absagen / in den Kalender (öffentlich per Buchungscode): appt.php?c=CODE[&ics=1] */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/appt-lib.php';
th_nocache(); if(!isset($_GET['ics'])) th_fresh();   // Statusseite immer frisch (Edge-Cache), ICS darf gecacht werden
$code=strtoupper(preg_replace('/[^A-Za-z0-9]/','',(string)($_GET['c'] ?? '')));
$b=$code!=='' ? appt_by_code($code) : null; $s=$b ? appt_settings((int)$b['account_id']) : null;
if($b && isset($_GET['ics'])){ header('Content-Type: text/calendar; charset=UTF-8'); header('Content-Disposition: attachment; filename="termin-'.$b['code'].'.ics"'); echo appt_ics($b,$s); exit; }
$msg='';
if($b && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['do'] ?? '')==='cancel'){ $r=appt_cancel($code); $b=appt_by_code($code); $msg=isset($r['error'])?'Absage nicht möglich.':'Ihr Termin wurde abgesagt.'; }
$pub=$b ? appt_public($b,$s) : null;
header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $pub ? 'Termin · '.htmlspecialchars($pub['company']) : 'Termin nicht gefunden' ?></title><meta name="robots" content="noindex">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,600&display=swap">
<style>body{margin:0;background:#f6f7fb;font:15px/1.55 Inter,system-ui,sans-serif;color:#0f1222;-webkit-font-smoothing:antialiased}.w{max-width:560px;margin:40px auto;padding:0 18px}.c{background:#fff;border:1px solid #e4e6ee;border-radius:24px;padding:30px;box-shadow:0 10px 30px rgba(15,18,34,.07)}h1{font:600 28px Fraunces,Georgia,serif;margin:6px 0 4px}.eb{font:700 12px Inter;letter-spacing:.1em;text-transform:uppercase;color:#b8862b}.box{margin:18px 0;padding:18px;border-radius:16px;background:#f6ecd3}.box b{font-size:18px}.m{color:#6b7083;font-size:13px}.btn{display:inline-flex;align-items:center;gap:8px;padding:12px 18px;border-radius:12px;border:1px solid #d6d9e4;background:#fff;color:#0f1222;font:600 14px Inter;text-decoration:none;cursor:pointer}.btn.p{background:#0f1222;color:#fff;border-color:#0f1222}.btn.d{color:#9f2448;border-color:#f7c6d5;background:#fde7ee}.row{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.st{display:inline-block;padding:6px 10px;border-radius:999px;font:700 11.5px Inter;letter-spacing:.06em;text-transform:uppercase}.ok{background:#e4f5ea;color:#1a9e5a}.no{background:#fde7ee;color:#9f2448}.msg{padding:10px 12px;border-radius:12px;background:#eceafe;color:#3730a3;font-size:14px;margin-bottom:12px}</style></head>
<body><div class="w"><div class="c">
<?php if(!$pub): ?><div class="eb">ChatHelp Termine</div><h1>Termin nicht gefunden</h1><p class="m">Der Buchungscode ist ungültig oder der Termin wurde gelöscht.</p>
<?php else: ?>
<?php if($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<div class="eb"><?= htmlspecialchars($pub['company']) ?></div><h1>Ihr Termin</h1><span class="st <?= $pub['status']==='booked'||$pub['status']==='done'?'ok':'no' ?>"><?= ['booked'=>'bestätigt','done'=>'wahrgenommen','cancelled'=>'abgesagt','noshow'=>'nicht erschienen'][$pub['status']] ?? $pub['status'] ?></span>
<div class="box"><b><?= htmlspecialchars($pub['service']) ?></b><br><?= htmlspecialchars($pub['human']) ?> – <?= htmlspecialchars($pub['end']) ?> Uhr<?php if($pub['address']): ?><br><?= htmlspecialchars($pub['address']) ?><?php endif; ?><br><span class="m">Buchungscode <?= htmlspecialchars($pub['code']) ?> · <?= htmlspecialchars($pub['name']) ?></span></div>
<div class="row"><?php if($pub['status']==='booked'): ?><a class="btn p" href="?c=<?= $pub['code'] ?>&ics=1">📅 In den Kalender</a><form method="post" onsubmit="return confirm('Termin wirklich absagen?')"><input type="hidden" name="do" value="cancel"><button class="btn d" type="submit">Termin absagen</button></form><?php endif; ?></div>
<p class="m" style="margin-top:18px">Fragen? Antworten Sie einfach auf die Bestätigungs-E-Mail oder rufen Sie an. Bereitgestellt von <a href="https://chat-help.com" style="color:#b8862b">ChatHelp</a>.</p>
<?php endif; ?>
</div></div></body></html>
