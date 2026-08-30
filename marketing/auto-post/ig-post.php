<?php
/**
 * ChatHelp — Instagram Reels yayını (Instagram Login akışı, graph.instagram.com).
 * Token IGAA... ile başlar. IG User ID token'dan otomatik çözülür (elle girmeye gerek yok).
 * Reel yayını 3 adım: container oluştur -> işlensin bekle -> yayınla.
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

/** Token'dan Instagram user id'yi çözer (config 'AUTO' ise). */
function ig_user_id($cfg, $tok) {
    if (!empty($cfg['ig_user_id']) && $cfg['ig_user_id'] !== 'AUTO') return $cfg['ig_user_id'];
    $j = ig_http('GET', $cfg['api_base'] . '/me', ['fields' => 'user_id', 'access_token' => $tok]);
    return $j['user_id'] ?? $j['id'] ?? null;
}

/**
 * Bir Reel yayınlar. $videoUrl PUBLIC (tarayıcıdan erişilebilir) bir .mp4 olmalı.
 * Başarılıysa yayınlanan medya ID'sini döndürür.
 */
function ig_publish_reel($videoUrl, $caption) {
    $cfg  = ig_cfg();
    $tok  = ig_token($cfg);
    $base = $cfg['api_base'];
    $uid  = ig_user_id($cfg, $tok);
    if (!$uid) throw new Exception('IG user id çözülemedi (token geçersiz olabilir)');

    // 1) Container oluştur
    $c = ig_http('POST', "$base/$uid/media", [
        'media_type'   => 'REELS',
        'video_url'    => $videoUrl,
        'caption'      => $caption,
        'access_token' => $tok,
    ]);
    $cid = $c['id'] ?? null;
    if (!$cid) throw new Exception('container ID alınamadı');
    ig_log($cfg, "container $cid  <- $videoUrl");

    // 2) Video işlenene kadar bekle
    $ok = false;
    for ($i = 0; $i < 30; $i++) {
        sleep(5);
        $s  = ig_http('GET', "$base/$cid", ['fields' => 'status_code', 'access_token' => $tok]);
        $sc = $s['status_code'] ?? '';
        if ($sc === 'FINISHED') { $ok = true; break; }
        if ($sc === 'ERROR')    throw new Exception('container işlenirken ERROR');
    }
    if (!$ok) throw new Exception('container zaman aşımı (video işlenmedi)');

    // 3) Yayınla
    $p = ig_http('POST', "$base/$uid/media_publish", [
        'creation_id'  => $cid,
        'access_token' => $tok,
    ]);
    $mid = $p['id'] ?? null;
    ig_log($cfg, "YAYINLANDI media $mid");
    return $mid;
}
