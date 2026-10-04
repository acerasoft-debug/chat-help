<?php
/**
 * ChatHelp — apply-analyse — sunucuya premium analiz panosu (analyse.php) yazar.
 *   URL (deploy sonrasi): /chat/analyse.php  (ADMIN_PASS ile korumali; admin.php ile ayni sifre)
 *   Gercek PDO sorgulari: kullanici/kayit/plan/ciro(MRR)/belge/huni. Her sorgu try/catch korumali.
 *   Idempotent degil (dosyayi yeniden yazar); onceki analyse.php varsa .bak alinir.
 * KULLANIM: /chat/pull2.php?key=...&files=apply-analyse.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-analyse BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$content = <<<'ANALYSE'
<?php
/* ChatHelp — analyse.php — Admin Analiz Panosu (ADMIN_PASS korumali). Salt-okunur raporlar. */
session_start();
error_reporting(E_ERROR | E_PARSE);
if (is_file(__DIR__.'/config.php')) include_once __DIR__.'/config.php';
if (!defined('ADMIN_PASS')) define('ADMIN_PASS', (defined('ADMIN_PW') && ADMIN_PW !== '') ? ADMIN_PW : 'chathelp2026');

/* ── auth ── */
if (isset($_GET['logout'])) { $_SESSION['admin']=false; header('Location: analyse.php'); exit; }
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['password'])) {
  if (hash_equals((string)ADMIN_PASS, (string)$_POST['password'])) { $_SESSION['admin']=true; header('Location: analyse.php'); exit; }
  $err=1;
}
$isAdmin = !empty($_SESSION['admin']);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function eur($n){ return number_format((float)$n, 2, ',', '.').' €'; }
function q($cb){ try{ return $cb(); }catch(\Throwable $e){ return null; } }

if (!$isAdmin) {
  ?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Analyse · ChatHelp</title>
  <style>
    *{box-sizing:border-box}body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:radial-gradient(120% 90% at 50% -10%,rgba(24,52,96,.5),transparent 60%),#070d1a;font-family:system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;color:#EAF0F8}
    .card{width:100%;max-width:360px;background:linear-gradient(168deg,#122A4B,#081326);border:1px solid rgba(214,175,106,.3);border-radius:20px;padding:30px 26px;box-shadow:0 30px 80px -40px rgba(0,0,0,.85);text-align:center}
    .lg{font-size:20px;font-weight:800;letter-spacing:.02em}.lg b{color:#E4C688}
    p{color:#9FB2CC;font-size:13px;margin:6px 0 20px}
    input{width:100%;padding:13px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.15);background:rgba(255,255,255,.05);color:#fff;font-size:15px;margin-bottom:12px}
    button{width:100%;padding:13px;border:0;border-radius:999px;background:linear-gradient(180deg,#E4C688,#C6A15B);color:#0B1E38;font-weight:800;font-size:15px;cursor:pointer}
    .err{color:#ff8f8f;font-size:12.5px;margin-bottom:10px}
  </style></head><body>
    <form class="card" method="post" autocomplete="off">
      <div class="lg">Chat<b>Help</b> · Analyse</div>
      <p>Admin-Bereich · bitte anmelden</p>
      <?php if(!empty($err)) echo '<div class="err">Falsches Passwort.</div>'; ?>
      <input type="password" name="password" placeholder="Admin-Passwort" autofocus>
      <button type="submit">Anmelden</button>
    </form>
  </body></html><?php
  exit;
}

/* ── veriler ── */
$pdo = null;
if (is_file(__DIR__.'/db.php')) { include_once __DIR__.'/db.php'; if (function_exists('db')) { try{ $pdo=db(); }catch(\Throwable $e){ $pdo=null; } } }

$totalUsers = $pdo ? q(fn()=>(int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn()) : null;
$verified   = $pdo ? q(fn()=>(int)$pdo->query("SELECT COUNT(*) FROM users WHERE email_verified=1")->fetchColumn()) : null;
$withStripe = $pdo ? q(fn()=>(int)$pdo->query("SELECT COUNT(*) FROM users WHERE stripe_customer_id IS NOT NULL AND stripe_customer_id<>''")->fetchColumn()) : null;
$totalDocs  = $pdo ? q(fn()=>(int)$pdo->query("SELECT COUNT(*) FROM user_documents")->fetchColumn()) : null;

/* plan dagilimi (user_plans; kullanici basina tek) */
$planCounts = ['free'=>0,'basic'=>0,'pro'=>0,'elite'=>0];
$planRows = $pdo ? q(fn()=>$pdo->query("SELECT plan, COUNT(DISTINCT user_id) c FROM user_plans WHERE plan IS NOT NULL GROUP BY plan")->fetchAll(PDO::FETCH_KEY_PAIR)) : null;
$paidTotal = 0;
if (is_array($planRows)) { foreach($planRows as $pl=>$c){ $pl=strtolower(trim($pl)); if(isset($planCounts[$pl])) $planCounts[$pl]=(int)$c; if($pl!=='free') $paidTotal+=(int)$c; } }
if ($totalUsers!==null) $planCounts['free'] = max(0, $totalUsers - $paidTotal);

/* MRR (aylik: basic 13,99 / pro 29,99 / elite 99,99) */
$PRICE=['basic'=>13.99,'pro'=>29.99,'elite'=>99.99];
$mrr = $planCounts['basic']*$PRICE['basic'] + $planCounts['pro']*$PRICE['pro'] + $planCounts['elite']*$PRICE['elite'];

/* son 30 gun kayitlar */
$reg=[]; $regOk=false;
if ($pdo) { $r=q(fn()=>$pdo->query("SELECT DATE(created_at) d, COUNT(*) c FROM users WHERE created_at>=DATE_SUB(CURDATE(),INTERVAL 29 DAY) GROUP BY DATE(created_at)")->fetchAll(PDO::FETCH_KEY_PAIR)); if(is_array($r)){ $reg=$r; $regOk=true; } }
$days=[]; $maxc=1; $new7=0; $new30=0;
for($i=29;$i>=0;$i--){ $d=date('Y-m-d', time()-$i*86400); $c=isset($reg[$d])?(int)$reg[$d]:0; $days[]=['d'=>$d,'c'=>$c]; if($c>$maxc)$maxc=$c; $new30+=$c; if($i<7)$new7+=$c; }

$verRate = ($totalUsers) ? round(100*$verified/$totalUsers) : 0;
$payRate = ($totalUsers) ? round(100*$paidTotal/$totalUsers,1) : 0;
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Analyse · ChatHelp</title>
<style>
  *{box-sizing:border-box}body{margin:0;background:radial-gradient(120% 60% at 50% -10%,rgba(24,52,96,.4),transparent 55%),#070d1a;font-family:system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;color:#EAF0F8;padding:20px 16px 60px}
  .wrap{max-width:1080px;margin:0 auto}
  .top{display:flex;align-items:center;justify-content:space-between;margin:6px 2px 22px;flex-wrap:wrap;gap:10px}
  .brand{font-size:20px;font-weight:800}.brand b{color:#E4C688}
  .top .sub{color:#8AA0BE;font-size:12.5px}
  .logout{color:#9FB2CC;font-size:12.5px;text-decoration:none;border:1px solid rgba(255,255,255,.14);padding:7px 14px;border-radius:999px}
  .grid{display:grid;gap:14px}
  .kpis{grid-template-columns:repeat(auto-fit,minmax(160px,1fr))}
  .card{background:linear-gradient(168deg,#122A4B,#0a1526);border:1px solid rgba(214,175,106,.2);border-radius:18px;padding:18px 18px}
  .kpi .l{color:#8FA6C2;font-size:12px;letter-spacing:.04em;text-transform:uppercase}
  .kpi .v{font-size:30px;font-weight:800;margin-top:6px;color:#fff;line-height:1}
  .kpi .v small{font-size:14px;color:#E4C688;font-weight:700}
  .kpi .s{color:#7f93af;font-size:11.5px;margin-top:6px}
  .sec{margin-top:22px}
  .sec h3{font-size:13px;letter-spacing:.06em;text-transform:uppercase;color:#D6AF6A;margin:0 0 12px 2px;font-weight:700}
  .bars{display:flex;align-items:flex-end;gap:3px;height:150px;padding:6px 0}
  .bar{flex:1;background:linear-gradient(180deg,#E4C688,rgba(214,175,106,.25));border-radius:3px 3px 0 0;min-height:2px;position:relative}
  .bar:hover::after{content:attr(data-t);position:absolute;bottom:100%;left:50%;transform:translateX(-50%);background:#0b1526;border:1px solid rgba(214,175,106,.4);color:#EAF0F8;font-size:11px;padding:3px 7px;border-radius:6px;white-space:nowrap;margin-bottom:4px}
  .axis{display:flex;justify-content:space-between;color:#61748f;font-size:10.5px;margin-top:4px}
  .plan{display:flex;align-items:center;gap:12px;margin-bottom:12px}
  .plan .n{width:64px;font-size:13px;color:#C7D4E8;font-weight:600}
  .plan .track{flex:1;background:rgba(255,255,255,.06);border-radius:999px;height:20px;overflow:hidden}
  .plan .fill{height:100%;border-radius:999px;background:linear-gradient(90deg,#C6A15B,#E4C688)}
  .plan .c{width:70px;text-align:right;font-size:13px;color:#EAF0F8;font-weight:700}
  .funnel{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;text-align:center}
  .fn{background:linear-gradient(168deg,#122A4B,#0a1526);border:1px solid rgba(214,175,106,.2);border-radius:16px;padding:16px 10px}
  .fn .v{font-size:26px;font-weight:800;color:#fff}.fn .l{color:#8FA6C2;font-size:12px;margin-top:4px}.fn .p{color:#E4C688;font-size:12px;margin-top:4px;font-weight:700}
  .warn{background:rgba(224,80,80,.08);border:1px solid rgba(224,80,80,.3);color:#ffb3b3;font-size:12.5px;border-radius:12px;padding:10px 14px;margin-bottom:16px}
  .muted{color:#61748f}
</style></head><body><div class="wrap">
  <div class="top">
    <div><div class="brand">Chat<b>Help</b> · Analyse</div><div class="sub">Live-Kennzahlen · <?=h(date('d.m.Y H:i'))?></div></div>
    <a class="logout" href="?logout=1">Abmelden</a>
  </div>

  <?php if(!$pdo) echo '<div class="warn">⚠ Datenbank nicht erreichbar — Kennzahlen können nicht geladen werden.</div>'; ?>

  <div class="grid kpis">
    <div class="card kpi"><div class="l">Nutzer gesamt</div><div class="v"><?=$totalUsers===null?'—':number_format($totalUsers,0,',','.')?></div><div class="s"><?=$verified===null?'':($verRate.'% verifiziert')?></div></div>
    <div class="card kpi"><div class="l">Neu (7 Tage)</div><div class="v"><?=$regOk?('+'.$new7):'—'?></div><div class="s"><?=$regOk?('30 Tage: +'.$new30):'created_at n/v'?></div></div>
    <div class="card kpi"><div class="l">Zahlende Kunden</div><div class="v"><?=$paidTotal?><small> / <?=$totalUsers===null?'—':$totalUsers?></small></div><div class="s"><?=$payRate?>% Conversion</div></div>
    <div class="card kpi"><div class="l">MRR (geschätzt)</div><div class="v"><?=eur($mrr)?></div><div class="s">wiederkehrend / Monat</div></div>
    <div class="card kpi"><div class="l">Dokumente</div><div class="v"><?=$totalDocs===null?'—':number_format($totalDocs,0,',','.')?></div><div class="s">gesamt erstellt</div></div>
    <div class="card kpi"><div class="l">Stripe-Kunden</div><div class="v"><?=$withStripe===null?'—':$withStripe?></div><div class="s">mit Kundenkonto</div></div>
  </div>

  <div class="sec">
    <h3>Neuregistrierungen · letzte 30 Tage</h3>
    <div class="card">
      <?php if($regOk){ ?>
        <div class="bars">
          <?php foreach($days as $x){ $hgt=max(2,round(100*$x['c']/$maxc)); echo '<div class="bar" style="height:'.$hgt.'%" data-t="'.h(date('d.m',strtotime($x['d']))).': '.$x['c'].'"></div>'; } ?>
        </div>
        <div class="axis"><span><?=h(date('d.m',strtotime($days[0]['d'])))?></span><span><?=h(date('d.m',strtotime($days[15]['d'])))?></span><span><?=h(date('d.m',strtotime($days[29]['d'])))?></span></div>
      <?php } else { echo '<div class="muted">Spalte <code>created_at</code> nicht verfügbar — Zeitverlauf kann nicht dargestellt werden.</div>'; } ?>
    </div>
  </div>

  <div class="sec">
    <h3>Plan-Verteilung</h3>
    <div class="card">
      <?php $pmax=max(1,$planCounts['free'],$planCounts['basic'],$planCounts['pro'],$planCounts['elite']);
      foreach(['free'=>'Free','basic'=>'Basic','pro'=>'Pro','elite'=>'Elite'] as $k=>$lbl){ $c=$planCounts[$k]; $w=round(100*$c/$pmax); ?>
        <div class="plan"><div class="n"><?=$lbl?></div><div class="track"><div class="fill" style="width:<?=$w?>%"></div></div><div class="c"><?=number_format($c,0,',','.')?></div></div>
      <?php } ?>
    </div>
  </div>

  <div class="sec">
    <h3>Conversion-Funnel</h3>
    <div class="funnel">
      <div class="fn"><div class="v"><?=$totalUsers===null?'—':number_format($totalUsers,0,',','.')?></div><div class="l">Registriert</div><div class="p">100%</div></div>
      <div class="fn"><div class="v"><?=$verified===null?'—':number_format($verified,0,',','.')?></div><div class="l">Verifiziert</div><div class="p"><?=$verRate?>%</div></div>
      <div class="fn"><div class="v"><?=number_format($paidTotal,0,',','.')?></div><div class="l">Zahlend</div><div class="p"><?=$payRate?>%</div></div>
    </div>
  </div>

  <div class="sec"><div class="muted" style="font-size:11.5px;text-align:center">MRR = Basic 13,99 € · Pro 29,99 € · Elite 99,99 € × aktive Abos. Nur-Lese-Auswertung.</div></div>
</div></body></html>
ANALYSE;

/* ── lint (gecici) ── */
$tmp=tempnam(sys_get_temp_dir(),'an').'.php'; file_put_contents($tmp,$content);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "  ✗ LINT HATASI — analyse.php YAZILMADI:\n  ".implode("\n  ",$lo)."\n"; exit; }
echo "  ✓ analyse.php lint temiz\n";

$target=__DIR__.'/analyse.php';
if(is_file($target)) @copy($target,$target.'.bak-'.date('Ymd-His'));
$w=@file_put_contents($target,$content);
if($w===false||$w<strlen($content)) exit("  ✗ analyse.php YAZILAMADI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
echo "\n  ✓ analyse.php yazildi (".strlen($content)." B)\n";
echo "  · URL: /chat/analyse.php  (Admin-Passwort ile giris — admin.php ile ayni)\n";
echo "  · Nutzer/Neuregistrierungen/Plan-Verteilung/MRR/Dokumente/Funnel — gercek PDO sorgulari.\n";
echo "  · created_at yoksa zaman-grafigi zarifce 'n/v' gosterir; digerleri calisir.\n";
