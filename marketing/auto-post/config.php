<?php
/**
 * ChatHelp — Instagram oto-paylaşım ayarları.
 *
 * GÜVENLİK:
 *  - Access token'ı ve app secret'ı ASLA repoya yazma.
 *    token.txt ve app_secret.txt dosyalarında tut (chmod 600), .gitignore'da hariç.
 *  - Bu klasörü mümkünse web kökünün DIŞINA koy (ör. /home/KULLANICI/auto-post/),
 *    böylece token.txt tarayıcıdan erişilemez. Sadece reel MP4'leri public olmalı.
 */
return [
    'graph_version' => 'v21.0',

    // Instagram Login akışı (token IGAA... ile başlar) -> graph.instagram.com
    'api_base'      => 'https://graph.instagram.com/v21.0',

    // IG User ID: 'AUTO' bırak -> koddan token üzerinden otomatik çözülür (elle girmeye gerek yok)
    'ig_user_id'    => getenv('IG_USER_ID') ?: 'AUTO',

    // Meta App bilgileri (token yenileme için)
    'app_id'        => getenv('FB_APP_ID') ?: '2503799493460693',
    'app_secret'    => getenv('FB_APP_SECRET') ?: '',   // boşsa app_secret.txt'ten okunur

    // Sırlar dosyada (repoda değil)
    'token_file'    => __DIR__ . '/token.txt',        // uzun ömürlü access token (tek satır)
    'secret_file'   => __DIR__ . '/app_secret.txt',   // app secret (tek satır)

    // İçerik ve durum
    'content_file'  => __DIR__ . '/content.json',
    'state_file'    => __DIR__ . '/state.json',        // her dil için en son atılan index
    'log_file'      => __DIR__ . '/post.log',

    // Aynı gün aynı dile ikinci kez atmayı engelle (idempotent cron)
    'guard_same_day' => true,
];
