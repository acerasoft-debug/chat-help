<?php
/**
 * ChatHelp — Anahtarları BİR KEZ göster (sonra kendini siler)
 * Sadece sunucudaki kendi config'ini okur. Çalıştırınca dosya silinir.
 * KULLANIM: chat-help.com/chat/show-keys.php?confirm=yes
 * ⚠️ Gördükten sonra TÜM anahtarları ROTATE ET (sohbette göründüler).
 */
header('Content-Type: text/plain; charset=UTF-8');
if (($_GET['confirm'] ?? '') !== 'yes') {
    exit("Güvenlik: anahtarları görmek için URL'ye  ?confirm=yes  ekle.\n");
}
require_once __DIR__ . '/db.php';
if (file_exists(__DIR__ . '/config.php')) require_once __DIR__ . '/config.php';

function p($label, $const){ echo str_pad($label, 18) . ': ' . (defined($const) ? constant($const) : '(tanımlı değil)') . "\n"; }

echo "ChatHelp — Anahtarlar (BU DOSYA KENDİNİ SİLECEK)\n";
echo "================================================\n\n";

echo "[AI API]\n";
p('DeepSeek',   'DEEPSEEK_KEY');
p('Claude',     'CLAUDE_KEY');
foreach (['ANTHROPIC_KEY','ANTHROPIC_API_KEY','OPENAI_KEY'] as $c) if (defined($c)) p($c, $c);

echo "\n[E-Posta / SMTP]\n";
p('SMTP_HOST','SMTP_HOST'); p('SMTP_PORT','SMTP_PORT');
p('SMTP_USER','SMTP_USER'); p('SMTP_PASS','SMTP_PASS');
p('SMTP_FROM','SMTP_FROM');

echo "\n[JWT / DB]\n";
p('JWT_SECRET','JWT_SECRET');
foreach (['DB_HOST','DB_NAME','DB_USER','DB_PASS','DB_PASSWORD'] as $c) if (defined($c)) p($c, $c);

echo "\n[Stripe]\n";
foreach (['STRIPE_SK','STRIPE_PK','STRIPE_SECRET','STRIPE_WEBHOOK_SECRET'] as $c) if (defined($c)) p($c, $c);
foreach (['stripe-checkout.php','stripe-webhook.php'] as $f){
    $t=@file_get_contents(__DIR__.'/'.$f);
    if($t!==false){
        if(preg_match_all("/(sk_live_[A-Za-z0-9]+|sk_test_[A-Za-z0-9]+|whsec_[A-Za-z0-9]+|pk_live_[A-Za-z0-9]+)/",$t,$m)){
            foreach(array_unique($m[0]) as $k) echo str_pad($f,20).': '.$k."\n";
        }
    }
}

echo "\n[Google OAuth]\n";
$idx=@file_get_contents(__DIR__.'/index.php');
if($idx!==false && preg_match('/([0-9]+-[a-z0-9]+\.apps\.googleusercontent\.com)/',$idx,$m)) echo "Client-ID         : ".$m[1]."\n";

echo "\n[Shopify]\n";
echo "  Shopify token (shpat_...) bu sunucuda DEĞİL — kendi terminal komutunda/Shopify Admin'de.\n";

/* kendini sil */
@unlink(__FILE__);
echo "\n================================================\n";
echo "✅ Bu dosya KENDİNİ SİLDİ.\n";
echo "🚨 ŞİMDİ HEPSİNİ ROTATE ET: Claude, DeepSeek, Stripe, SMTP şifresi, SFTP şifresi.\n";
