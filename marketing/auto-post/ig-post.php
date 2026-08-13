<?php
/**
 * ChatHelp — Instagram Graph API ile Reels yayınlama.
 * Instagram, otomatik paylaşımı yalnızca resmi Graph API (veya onaylı iş ortağı)
 * üzerinden verir. Reel yayını 3 adımdır: container oluştur -> işlensin bekle -> yayınla.
 */

function ig_cfg() { return require __DIR__ . '/config.php'; }

function ig_token($cfg) {
    $t = @trim(@file_get_contents($cfg['token_file']));
    if (!$t) throw new Exception('token.txt yok veya boş');
    return $t;
}

function ig_log($cfg, $msg) {
    @file_put_contents($cfg['log_file'], '[' . date('c') . '] ' . $msg . "\n", FILE_APPEND);
}

function ig_http($method, $url, $params) {
    $ch = curl_init();
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    } else {
        curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($res === false) throw new Exception("curl hatası: $err");
    $j = json_decode($res, true);
    if ($code >= 400) throw new Exception("HTTP $code: $res");
    return $j;
}

/**
 * Bir Reel yayınlar. $videoUrl PUBLIC (tarayıcıdan erişilebilir) bir .mp4 olmalı.
 * Başarılıysa yayınlanan medya ID'sini döndürür.
 */
function ig_publish_reel($videoUrl, $caption) {
    $cfg  = ig_cfg();
    $tok  = ig_token($cfg);
    $v    = $cfg['graph_version'];
    $ig   = $cfg['ig_user_id'];
    $base = "https://graph.facebook.com/$v";

    // 1) Container oluştur
    $c = ig_http('POST', "$base/$ig/media", [
        'media_type'    => 'REELS',
        'video_url'     => $videoUrl,
        'caption'       => $caption,
        'share_to_feed' => 'true',
        'access_token'  => $tok,
    ]);
    $cid = $c['id'] ?? null;
    if (!$cid) throw new Exception('container ID alınamadı');
    ig_log($cfg, "container $cid  <- $videoUrl");

    // 2) Video işlenene kadar bekle (status_code = FINISHED)
    $ok = false;
    for ($i = 0; $i < 30; $i++) {
        sleep(5);
        $s = ig_http('GET', "$base/$cid", ['fields' => 'status_code', 'access_token' => $tok]);
        $sc = $s['status_code'] ?? '';
        if ($sc === 'FINISHED') { $ok = true; break; }
        if ($sc === 'ERROR')    throw new Exception('container işlenirken ERROR');
    }
    if (!$ok) throw new Exception('container zaman aşımı (video işlenmedi)');

    // 3) Yayınla
    $p = ig_http('POST', "$base/$ig/media_publish", [
        'creation_id'  => $cid,
        'access_token' => $tok,
    ]);
    $mid = $p['id'] ?? null;
    ig_log($cfg, "YAYINLANDI media $mid");
    return $mid;
}
