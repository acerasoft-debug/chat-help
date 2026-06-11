<?php
/**
 * chat-help.com (kök) -> chat-help.com/chat/ yönlendirmesi
 * --------------------------------------------------------
 * PHP ile, "asla cache'leme" başlıklarıyla. Böylece tüm cihazlar/tarayıcılar
 * her seferinde taze yönlendirme alır (statik index.html'in aksine cache'lenmez).
 * nginx index.php'yi index.html'den önce çalıştırdığı için bu öncelikli olur.
 */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Location: /chat/', true, 302);   // 302 = geçici (301 kalıcı cache'lenir, istemiyoruz)
exit;
