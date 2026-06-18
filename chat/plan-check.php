<?php
/**
 * ChatHelp — Plan satın alma → kullanım zinciri denetimi & onarımı
 * ----------------------------------------------------------------
 * "Paket alındıktan sonra kullanılabildiğinden emin ol" için.
 *
 * Yapar:
 *  1) user_plans tablosunu okur, son kayıtları ve plan/status değerlerini gösterir
 *  2) Kod 'basic'/'pro'/'elite' (küçük harf) bekliyor — webhook ne yazmış kontrol eder
 *  3) ?fix=1 ile: tüm plan adlarını normalize eder (Pro->pro), boş status'leri 'active' yapar
 *  4) stripe-webhook.php'nin plan yazımını dökerek teşhis eder
 *
 * KULLANIM:
 *   Teşhis:  chat-help.com/chat/plan-check.php
 *   Onarım:  chat-help.com/chat/plan-check.php?fix=1
 *   SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
require_once __DIR__ . '/db.php';
if (file_exists(__DIR__ . '/config.php')) require_once __DIR__ . '/config.php';

$fix = isset($_GET['fix']);

echo "ChatHelp — Plan zinciri denetimi\n================================\n";
echo "Mod: " . ($fix ? "ONARIM (fix=1)" : "sadece teşhis") . "\n\n";

try {
    $pdo = db();
} catch (Exception $e) {
    exit("HATA: DB bağlanamadı: " . $e->getMessage() . "\n");
}

/* ── 1) user_plans var mı, yapısı ne? ── */
echo "[1] user_plans tablosu\n";
try {
    $cols = $pdo->query("SHOW COLUMNS FROM user_plans")->fetchAll(PDO::FETCH_ASSOC);
    echo "  Sütunlar: ";
    echo implode(', ', array_map(fn($c) => $c['Field'] . '(' . $c['Type'] . ')', $cols)) . "\n";
} catch (Exception $e) {
    echo "  ✗ user_plans tablosu YOK veya okunamadı: " . $e->getMessage() . "\n";
    echo "  → Webhook plan yazamıyorsa kullanıcı paketi alsa da kullanamaz!\n";
}

/* ── 2) Son 20 plan kaydı ── */
echo "\n[2] Son plan kayıtları (en yeni 20)\n";
try {
    $rows = $pdo->query("SELECT * FROM user_plans ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        echo "  (hiç kayıt yok — henüz kimse plan almamış ya da webhook yazmıyor)\n";
    } else {
        foreach ($rows as $r) {
            $plan = $r['plan'] ?? '?';
            $stat = $r['status'] ?? '(yok)';
            $uid  = $r['user_id'] ?? '?';
            $norm = ch_norm($plan);
            $flag = ($norm === strtolower(trim($plan))) ? '' : "  ⚠️ NORMALIZE GEREK -> '$norm'";
            echo "  #{$r['id']} uid=$uid plan='$plan' status='$stat'$flag\n";
        }
    }
} catch (Exception $e) {
    echo "  ✗ okunamadı: " . $e->getMessage() . "\n";
}

/* ── 3) Plan adı uyuşmazlığı analizi ── */
echo "\n[3] Kod ne bekliyor?\n";
echo "  fall-api.php ve api.php küçük harf bekler: 'basic' / 'pro' / 'elite'\n";
echo "  status 'active' (veya boş) olmalı.\n";

try {
    $distinct = $pdo->query("SELECT DISTINCT plan FROM user_plans")->fetchAll(PDO::FETCH_COLUMN);
    echo "\n  Tablodaki farklı plan değerleri:\n";
    $needFix = 0;
    foreach ($distinct as $p) {
        $n = ch_norm($p);
        $ok = in_array($n, ['basic','pro','elite'], true);
        if ($n !== strtolower(trim($p))) $needFix++;
        echo "    '$p'  ->  '$n'  " . ($ok ? "✓" : "✗ (tanınmıyor!)") . "\n";
    }
    if ($needFix > 0) echo "\n  ⚠️ $needFix değer normalize edilmeli. ?fix=1 ile düzelt.\n";
    else echo "\n  ✓ Tüm plan adları zaten uygun.\n";
} catch (Exception $e) {
    echo "  ✗ " . $e->getMessage() . "\n";
}

/* ── 4) ONARIM ── */
if ($fix) {
    echo "\n[4] ONARIM uygulanıyor...\n";
    try {
        $all = $pdo->query("SELECT id, plan, status FROM user_plans")->fetchAll(PDO::FETCH_ASSOC);
        $upd = $pdo->prepare("UPDATE user_plans SET plan=?, status=? WHERE id=?");
        $cnt = 0;
        foreach ($all as $r) {
            $newPlan = ch_norm($r['plan'] ?? '');
            $newStat = strtolower(trim($r['status'] ?? '')) ?: 'active';
            if (in_array($newStat, ['paid','complete','completed','aktiv','trialing'], true)) $newStat = 'active';
            if ($newPlan !== ($r['plan'] ?? '') || $newStat !== ($r['status'] ?? '')) {
                $upd->execute([$newPlan, $newStat, $r['id']]);
                $cnt++;
            }
        }
        echo "  ✓ $cnt kayıt normalize edildi.\n";
    } catch (Exception $e) {
        echo "  ✗ onarım hatası: " . $e->getMessage() . "\n";
    }
} else {
    echo "\n[4] Onarım atlandı. Gerekiyorsa: plan-check.php?fix=1\n";
}

/* ── 5) stripe-webhook.php plan yazımı ── */
echo "\n[5] stripe-webhook.php plan yazma kodu\n";
$wh = @file_get_contents(__DIR__ . '/stripe-webhook.php');
if ($wh === false) {
    echo "  (stripe-webhook.php bulunamadı)\n";
} else {
    foreach (['user_plans','INSERT','plan','status','price_'] as $kw) {
        $p = strpos($wh, $kw);
        if ($p !== false) {
            echo "\n  ▼ '$kw' çevresi:\n";
            foreach (explode("\n", substr($wh, max(0,$p-40), 240)) as $ln) echo "    $ln\n";
        }
    }
}

/* normalize helper */
function ch_norm($raw) {
    $p = strtolower(trim((string)$raw));
    if ($p === '') return 'free';
    if (strpos($p,'elite')!==false || strpos($p,'premium')!==false) return 'elite';
    if (strpos($p,'pro')!==false) return 'pro';
    if (strpos($p,'basic')!==false || strpos($p,'basis')!==false || strpos($p,'start')!==false) return 'basic';
    return $p;
}

echo "\n================================\n";
echo "BİTTİ. Çıktıyı bana gönder.  SİL: rm plan-check.php\n";
