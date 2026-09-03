<?php
/**
 * VESTRA — File-based account auth (no database required).
 * Accounts stored in data/accounts.json (server-side, not web-accessible).
 */

/* Defined before the session bootstrap below, because auth_remember_restore()
   (called during that bootstrap) reads the accounts file. */
define('VESTRA_ACCOUNTS', __DIR__.'/../data/accounts.json');

/* IP block list — enforced here because auth.php is the one include EVERY page
   loads first, so a ban covers the whole site without each page opting in. */
require_once __DIR__.'/security.php';
vestra_ip_guard();

/* Session-cookie hardening — must run before any session_start() (auth.php is
 * required before sessions start everywhere). HttpOnly blocks JS access,
 * SameSite=Lax blocks cross-site POSTs riding the session, Secure on HTTPS. */
/* Every page that knows who you are must also refuse to be cached — the two are
   one decision, which is why it lives here in the auth bootstrap and not in each
   page. head.php already sent these, but index.php and eight checkout/export
   endpoints never include head.php, and the homepage proved what that costs: a
   browser or edge cache holding the signed-OUT rendering kept showing "Register
   as Seller" to a customer who had just signed in — again on every visit.
   Duplicated later by head.php harmlessly: header() replaces by default. */
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('CDN-Cache-Control: no-store');
    header('Cloudflare-CDN-Cache-Control: no-store');
    header('Vary: Cookie, Accept-Language');
}

$_vlife = 90 * 86400; // keep users signed in ~90 days, even after the browser is closed
// Private session store: on shared hosting the global /tmp GC would otherwise
// expire our sessions within minutes. Keeping them under data/ (web-blocked)
// means our own gc_maxlifetime governs their lifetime.
$_vsess = __DIR__.'/../data/sessions';
if (!is_dir($_vsess)) @mkdir($_vsess, 0700, true);
$_vsess_ok = is_dir($_vsess) && is_writable($_vsess);

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @ini_set('session.gc_maxlifetime', (string)$_vlife);
    if ($_vsess_ok) @ini_set('session.save_path', $_vsess);
    session_set_cookie_params([
        'lifetime' => $_vlife, 'path' => '/',
        'secure'   => !empty($_SERVER['HTTPS']) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
    auth_remember_restore(); // re-establish a persistent login if the session itself has lapsed
}
/* Self-heal: a page that called session_start() before requiring this file gets
   PHP's defaults -- the shared /tmp store and a cookie that dies with the browser.
   That is not a cosmetic difference. Login writes to data/sessions; a page reading
   /tmp carries the same session id but looks in a different drawer, finds nothing,
   and shows a signed-in customer the registration buttons. The homepage did exactly
   that, on every visit, for as long as the two calls have been in that order.
   Fixing the callers is the real fix and has been done -- this is the guard that
   stops it coming back silently. Whatever the page already put in the session is
   carried across, so healing never costs data. */
elseif (session_status() === PHP_SESSION_ACTIVE && $_vsess_ok && !headers_sent()
        && rtrim((string)ini_get('session.save_path'), '/') !== rtrim($_vsess, '/')) {
    $_vcarry = $_SESSION ?? [];
    session_write_close();
    @ini_set('session.gc_maxlifetime', (string)$_vlife);
    @ini_set('session.save_path', $_vsess);
    session_set_cookie_params([
        'lifetime' => $_vlife, 'path' => '/',
        'secure'   => !empty($_SERVER['HTTPS']) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
    foreach ($_vcarry as $_k => $_v) { if (!isset($_SESSION[$_k])) $_SESSION[$_k] = $_v; }
    unset($_vcarry, $_k, $_v);
    auth_remember_restore();
    error_log('[VESTRA auth] oturum yanlis depoda baslatilmisti, duzeltildi: '
              .($_SERVER['SCRIPT_NAME'] ?? '?').' — o sayfa inc/auth.php\'yi session_start()\'tan ONCE yuklemeli');
}

/* Ziyaret sayaci. Oturum bootstrap'inin ARDINDAN cagriliyor: benzersiz ziyaretci
   tespiti $_SESSION'a bakiyor, once cagrilsaydi her istek "yeni ziyaretci"
   sayilirdi. auth.php'de duruyor cunku ip guard ile ayni gerekce -- her sayfa bu
   dosyayi ilk is yukluyor, yani hicbir sayfanin ayrica katilmasi gerekmiyor. */
if (session_status() === PHP_SESSION_ACTIVE) vestra_track_visit();

function auth_accounts(): array {
    if (!is_file(VESTRA_ACCOUNTS)) return [];
    return json_decode(file_get_contents(VESTRA_ACCOUNTS), true) ?: [];
}

function auth_save_accounts(array $list): void {
    $dir = dirname(VESTRA_ACCOUNTS);
    if(!is_dir($dir)){
        @mkdir($dir, 0755, true);
        @file_put_contents($dir.'/.htaccess', "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order deny,allow\n  Deny from all\n</IfModule>\n");
    }
    file_put_contents(VESTRA_ACCOUNTS, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function auth_find(string $email): ?array {
    foreach (auth_accounts() as $a)
        if (strtolower($a['email'] ?? '') === strtolower(trim($email))) return $a;
    return null;
}

function auth_user(): ?array {
    if (empty($_SESSION['uid'])) return null;
    foreach (auth_accounts() as $a)
        if (($a['id'] ?? '') === $_SESSION['uid']) return $a;
    return null;
}

/* Freischaltung: is this account approved enough to see photos, seller identities and
   catalog exports? The single source of truth — head.php's $APPROVED and every gated
   endpoint use this.
   Two ways in, and BOTH are the owner's decision: status 'active' or kyb_status
   'approved', which the admin KYB action sets together.

   TICARI BELGE ARTIK KAPI DEGIL (operator karari, 31 Agu 2026): "Gewerbe
   anmeldung istenmesi uyari olarak dursun ve direkt yukleyebilsinler." Belge
   hala kayitta isteniyor ve panelde UYARI olarak duruyor, ama erisimi
   engellemiyor -- kapi tek basina operatorun onayi. Onceki surumde
   auth_trade_unlocked() sarti buradaydi; belgesini gonderemeyen gercek
   musteriler onay verilmis olmasina ragmen fiyat goremiyordu.

   Uploading a document used to be enough on its own. It should not be: uploading is
   the applicant's action, not ours, and anyone who attached a file — any file, to any
   of the four requests — unlocked trade prices, seller identities and the full export
   before a human had opened it. Submitting a document is now what it says it is, a
   submission; access waits for the approval. */
function auth_user_approved(?array $u): bool {
    if ($u === null) return false;
    $base = (($u['status'] ?? '') === 'active') || (($u['kyb_status'] ?? '') === 'approved');
    return $base;
}

function auth_set(array $acc): void {
    $_SESSION['uid']    = $acc['id'];
    $_SESSION['member'] = true;
    $_SESSION['utype']  = $acc['type'];
    auth_remember_set($acc['id'] ?? ''); // persistent "remember me" — stays signed in across visits
}

function auth_logout(): void {
    auth_remember_clear($_SESSION['uid'] ?? '');
    unset($_SESSION['uid'], $_SESSION['member'], $_SESSION['utype']);
}

/* ── Persistent login ("remember me") ────────────────────────────────────────
   A random token is stored (hashed) on the account and mirrored in a long-lived
   cookie. When the PHP session has lapsed but the cookie is still valid, the
   login is transparently restored. The raw token is never stored server-side. */
function auth_remember_set(string $uid): void {
    if ($uid === '') return;
    $token = bin2hex(random_bytes(32));
    $exp   = time() + 90 * 86400;
    auth_update($uid, ['remember' => ['hash' => hash('sha256', $token), 'exp' => $exp]]);
    if (!headers_sent()) {
        $secure = !empty($_SERVER['HTTPS']) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        setcookie('vestra_rmb', $uid.':'.$token, [
            'expires' => $exp, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax',
        ]);
        $_COOKIE['vestra_rmb'] = $uid.':'.$token;
    }
}
function auth_remember_restore(): void {
    if (!empty($_SESSION['uid'])) return;
    $c = $_COOKIE['vestra_rmb'] ?? '';
    if ($c === '' || strpos($c, ':') === false) return;
    [$uid, $token] = explode(':', $c, 2);
    if ($uid === '' || $token === '') return;
    foreach (auth_accounts() as $a) {
        if (($a['id'] ?? '') !== $uid) continue;
        $r = $a['remember'] ?? null;
        if (!is_array($r) || empty($r['hash']) || empty($r['exp']) || time() > (int)$r['exp']) return;
        if (!hash_equals((string)$r['hash'], hash('sha256', $token))) return;
        // never auto-restore a suspended account -- except a docs-suspended seller, who may sign in to upload
        if (($a['status'] ?? '') === 'suspended' && !auth_suspended_for_docs($a)) return;
        $_SESSION['uid']    = $a['id'];
        $_SESSION['member'] = true;
        $_SESSION['utype']  = $a['type'] ?? '';
        return;
    }
}
function auth_remember_clear(string $uid): void {
    if ($uid !== '') auth_update($uid, ['remember' => null]);
    if (!headers_sent()) setcookie('vestra_rmb', '', ['expires' => time() - 3600, 'path' => '/']);
    unset($_COOKIE['vestra_rmb']);
}

/* Resend the verification email for a pending_email account and record the
 * attempt (verify_sent_at/verify_sent_ok) so admins can see whether it went
 * out. Shared by register.php's "already registered" path, login.php's
 * one-click resend, and the admin panel's Resend button. Returns false
 * silently for unknown/already-verified emails — callers must not use the
 * return value to reveal account existence. */
function auth_resend_verify(string $email): bool {
    $acc = auth_find($email);
    if (!$acc || ($acc['status'] ?? '') !== 'pending_email' || empty($acc['email_token'])) return false;
    require_once __DIR__.'/notify.php';
    $lang = substr($acc['lang'] ?? ($_COOKIE['vlang'] ?? 'en'), 0, 2);
    [$subj, $body, $vOpts] = vestra_verify_text($lang, $acc['name'] ?: ($acc['company'] ?: 'there'), $acc['email_token']);
    $sent = vestra_send_mail($acc['email'], $subj, $body, '', '', null, '', $vOpts);
    auth_update($acc['id'], ['verify_sent_at' => date('c'), 'verify_sent_ok' => $sent]);
    return true;
}

/* Record the timestamp of a successful login (shown in the admin Users tab). */
/**
 * Stamp the account with when — and from WHERE — it was last used.
 *
 * The rolling security log answers "what happened recently"; this answers "where
 * does this customer normally sign in from", which is the question in front of
 * the operator while approving KYB. Accounts that registered before any of this
 * existed have no reg_* fields at all and would stay blank forever; because this
 * runs on every login, the table fills itself in as people come back.
 */
function auth_touch_login(string $id): void {
    $ip = vestra_client_ip();
    $i  = vestra_ip_intel($ip);
    auth_update($id, [
        'last_login'   => date('c'),
        'last_ip'      => $ip,
        'last_cc'      => $i['cc'],
        'last_country' => $i['country'],
        'last_city'    => $i['city'],
        'last_isp'     => $i['isp'],
        'last_vpn'     => !empty($i['proxy']) || !empty($i['hosting']),
    ]);
}

function auth_register(array $d): array|string {
    require_once __DIR__.'/notify.php';
    $existing = auth_find($d['email'] ?? '');
    if ($existing) {
        // Only resend a verification link when verification is actually required.
        // With it off, a stuck pending_email account can just sign in, so fall
        // through to a plain "email taken" instead of a dead-end resend.
        if (($existing['status'] ?? '') === 'pending_email' && !empty($existing['email_token'])
            && vestra_cfg('require_email_verify', false)) {
            auth_resend_verify($existing['email']);
            return 'email_pending_verify';
        }
        return 'email_taken';
    }
    if (strlen($d['password'] ?? '') < 8) return 'password_short';
    if (($d['password'] ?? '') !== ($d['password2'] ?? '')) return 'password_mismatch';
    // Operator decision, 3 Sep 2026: no Turkish sellers/buyers, VPN or not —
    // see vestra_country_declares_turkey() in security.php for why this
    // checks the DECLARED country rather than the connecting IP.
    if (vestra_country_declares_turkey((string)($d['country'] ?? ''))) return 'country_not_served';

    $promo_code = strtoupper(trim($d['promo_code'] ?? ''));
    $promo_data = null;
    if ($promo_code !== '') {
        require_once __DIR__.'/promos.php';
        $pv = promo_validate($promo_code);
        if (is_string($pv)) return 'promo_'.$pv;
        $promo_data = $pv;
    }

    // Email-verification gate. Default OFF: on hosts where outgoing mail can't be
    // delivered (blocked SMTP ports + strict domain DMARC), waiting on a click-to-
    // verify email would lock every new account out forever. Access is still gated
    // downstream by admin KYB approval (status must reach 'active'). Set
    // 'require_email_verify' => true in inc/config.php once real email delivery works.
    require_once __DIR__.'/notify.php';
    $requireVerify = (bool) vestra_cfg('require_email_verify', false);

    $list = auth_accounts();
    $type = in_array($d['type'] ?? '', ['seller', 'buyer']) ? $d['type'] : 'buyer';
    $acc  = [
        'id'             => bin2hex(random_bytes(8)),
        'email'          => strtolower(trim($d['email'])),
        'hash'           => password_hash($d['password'], PASSWORD_DEFAULT),
        'type'           => $type,
        'status'         => $requireVerify ? 'pending_email' : 'pending',
        'email_verified' => !$requireVerify,
        'email_token'    => bin2hex(random_bytes(16)),
        'name'           => trim($d['name']        ?? ''),
        'company'       => trim($d['company']     ?? ''),
        'vat_id'        => trim($d['vat_id']      ?? ''),
        'reg_number'    => trim($d['reg_number']  ?? ''),
        'country'       => trim($d['country']     ?? ''),
        'address'       => trim($d['address']     ?? ''),
        'phone'         => trim($d['phone']       ?? ''),
        'website'       => trim($d['website']     ?? ''),
        'lang'          => substr($_COOKIE['vlang'] ?? 'en', 0, 2),
        'kyb_status'    => $promo_data ? 'approved' : 'pending',
        'membership_status' => 'none',
        'promo_code'    => $promo_code,
        'promo_benefit' => $promo_data['benefit'] ?? '',
        'promo_expiry'  => $promo_data['expiry']  ?? '',
        'created'       => date('c'),
        /* Gewerbe/trade-licence zorunlulugu SADECE bu bayragi tasiyan hesaplara
           uygulanir. Kayit sirasinda trade_licence belgesi ZATEN isteniyordu ve notu
           "An account cannot be activated without it" diyordu -- ama hicbir yerde
           uygulanmiyordu: kayit olan herkes belge yuklemeden fiyati ve fotograflari
           goruyordu. Kural yaziliydi, kapi yoktu.

           Bayrak, "bundan sonrakiler" ayrimini acikca tasiyor: mevcut hesaplarda bu
           alan yok, dolayisiyla onlar kilitlenmiyor. Ayrimi doc_requests'in varligina
           baglamak yanlis olurdu -- eski hesaplarda da o kayitlar var, hepsi bir gecede
           kilitlenirdi. */
        'trade_doc_required' => true,
        'doc_requests'  => [],
    ];
    /* Belge adlari kayit formunda SECILEN ulkeye gore: Gewerbeschein Almanya'nin
       belgesidir, Irlandali bir butik icin o kelime hicbir sey anlatmaz. Ulke
       bilinmiyorsa notr ifade tek basina kalir -- her yerde dogru olan odur. */
    $docCc    = vestra_cc_of_country((string)($acc['country'] ?? ''));
    $tradeLoc = auth_trade_doc_local_name($docCc);
    /* "An account cannot be activated without it" ARTIK DOGRU DEGIL: kapiyi
       operator onayi aciyor (auth_prices_unlocked -> auth_user_approved),
       belge uyari olarak duruyor. Cumleyi oldugu gibi birakmak, her yeni
       kayda soylediginin tersini yapan bir platform gostermek olurdu. */
    $tradeTxt = 'Please upload your trade licence / business registration'
              . ($tradeLoc !== '' ? ' ('.$tradeLoc.')' : '')
              . '. We keep it on file for compliance; you can add it at any time.';
    // Auto document requests on registration
    $ts = date('c');
    if($type === 'seller'){
        $acc['doc_requests'] = [
            ['id'=>bin2hex(random_bytes(4)),'type'=>'trade_licence','note'=>$tradeTxt,'status'=>'requested','requested_at'=>$ts],
            /* company_reg BILEREK YOK. Ticari kayit belgesiyle buyuk olcude AYNI SEYI
               kanitliyor, ve kucuk isletmelerin cogunda ayrica MEVCUT DEGIL: Almanya'da
               sahis sirketinin Gewerbeschein'i vardir ama Handelsregister kaydi yoktur.
               Var olmayan bir belgeyi sart kosmak, hedefledigimiz butik profilini tam
               olarak kapida tutuyordu. Odeme alabilmek icin satici zaten Stripe
               Connect'in kendi KYB'sinden geciyor -- yasal dogrulama orada yapiliyor,
               buradaki dosya bir on eleme.
               vat_cert de BILEREK YOK. Vergi kimligi kayit formunda ZATEN NUMARA olarak
               aliniyor (vat_id) ve alan kendini ulkeye gore adlandiriyor: AB'de KDV
               numarasi, ABD'de EIN, Isvicre'de UID, Turkiye'de VKN -- bkz.
               vestra_tax_id_hint(). Numara dogrulanabilir bir veri; sertifika ise
               ayni bilginin taranmis hali ve bir cok ulkede boyle bir belge zaten
               ayrica basilmiyor. Ustelik ABD'de "VAT sertifikasi" diye bir sey YOK,
               yani ABD'li her saticiya bulamayacagi bir belge soruluyordu.
               auth_letter de ARTIK YOK (operator karari, 31 Agu 2026). Sahis
               isletmesinde ve tek ortakli sirkette boyle bir belge zaten yok;
               notu "sole director iseniz atlayabilirsiniz" diyordu, yani listede
               atlanmasi soylenen bir satir duruyordu. Atlanabilen bir istek,
               istek degil: sadece listeyi uzatiyor ve gercekten gereken iki
               belgenin yanindaki aciliyeti seyreltiyordu.
               SATICIDAN ISTENEN: ticari kayit + kimlik. Digerleri
               auth_doc_types()'ta duruyor, operator supheli bir dosyada
               panelden tek tek yine isteyebilir. */
            ['id'=>bin2hex(random_bytes(4)),'type'=>'id_document', 'note'=>'Please upload a government-issued ID: passport, national ID card, or driving licence.','status'=>'requested','requested_at'=>$ts],
        ];
    } elseif($type === 'buyer'){
        /* ALICIDAN TEK BELGE: ticari kayit. Onceden company_reg ve vat_cert de
           acilirdi, ama ikisi de HICBIR kapiyi acmiyordu -- auth_trade_unlocked()
           ve auth_prices_unlocked()'in ikisi de yalnizca trade_licence'a bakiyor.
           Yani alici, hicbir seyi degistirmeyen iki "Upload erforderlich" satiri
           goruyordu; katalogu gormek icin gerekli sanip ucunu birden toplamaya
           calisiyor, toplayamayinca birakiyordu. Saticida ucu de duruyor: orada
           para tasiniyor ve KYB gercekten gerekiyor. */
        $acc['doc_requests'] = [
            ['id'=>bin2hex(random_bytes(4)),'type'=>'trade_licence','note'=>$tradeTxt,'status'=>'requested','requested_at'=>$ts],
        ];
    }
    /* Where did this registration come from? Stamped ON the account, not only in
       the rolling security log: the log ages out, but "which country signed this
       account up, and was it behind a VPN" stays a fair question for as long as
       the account exists — it is what the operator checks before approving KYB. */
    $regIp    = vestra_client_ip();
    $regIntel = vestra_ip_intel($regIp);
    $acc['reg_ip']      = $regIp;
    $acc['reg_cc']      = $regIntel['cc'];
    $acc['reg_country'] = $regIntel['country'];
    $acc['reg_city']    = $regIntel['city'];
    $acc['reg_isp']     = $regIntel['isp'];
    $acc['reg_vpn']     = !empty($regIntel['proxy']) || !empty($regIntel['hosting']);

    $list[] = $acc;
    auth_save_accounts($list);
    if ($promo_data) { promo_use($promo_code); }
    vestra_sec_log('register', $acc['email'], $acc['id']);

    // Notify admin of new registration
    require_once __DIR__.'/notify.php';
    $roleLabel = $type === 'seller' ? 'Seller' : 'Buyer';
    vestra_notify(
        "🆕 New {$roleLabel} registered: ".($acc['name']?:'—').' — '.($acc['company']?:'—'),
        "New {$roleLabel} account on VESTRA:\n\n".
        "Name:    ".($acc['name']    ?: '—')."\n".
        "Email:   ".$acc['email']."\n".
        "Company: ".($acc['company'] ?: '—')."\n".
        "Country: ".($acc['country'] ?: '—')."\n".
        "VAT ID:  ".($acc['vat_id']  ?: '—')."\n".
        "Phone:   ".($acc['phone']   ?: '—')."\n".
        "Promo:   ".($promo_code     ?: 'none')."\n".
        "KYB:     ".($acc['kyb_status'])."\n".
        "Created: ".$acc['created']."\n\n".
        "Admin: https://vestrasales.com/admin?tab=users",
        $acc['email']
    );
    // Send email verification link only when verification is required. When it's
    // off, the account is already usable (email_verified=true) so a link would be
    // pointless — skip it to avoid a dead "check your inbox" wait. Either way the
    // new user gets SOME email: a verify link, or (when verification is off) the
    // welcome/next-steps email instead — otherwise they'd get nothing at all.
    $lang = $acc['lang'] ?? 'en';
    if ($requireVerify) {
        [$subj, $body, $vOpts] = vestra_verify_text($lang, $acc['name'] ?: $acc['company'], $acc['email_token']);
        $sent = vestra_send_mail($acc['email'], $subj, $body, '', '', null, '', $vOpts);
        $acc['verify_sent_at'] = date('c');
        $acc['verify_sent_ok'] = $sent;
        auth_update($acc['id'], ['verify_sent_at' => $acc['verify_sent_at'], 'verify_sent_ok' => $sent]);
    } else {
        [$subj, $body, $aOpts] = vestra_ack_text($lang, $acc['name'] ?: $acc['company'], $type);
        $sent = vestra_send_mail($acc['email'], $subj, $body, '', '', null, '', $aOpts);
        auth_update($acc['id'], ['ack_sent_at' => date('c'), 'ack_sent_ok' => $sent]);
    }
    return $acc;
}

function auth_login(string $email, string $password): array|string {
    $acc = auth_find($email);
    if (!$acc || !password_verify($password, $acc['hash'] ?? '')) return 'invalid';
    if (($acc['status'] ?? '') === 'pending_email') {
        require_once __DIR__.'/notify.php';
        // With email verification required, keep blocking until they confirm.
        if (vestra_cfg('require_email_verify', false)) return 'unverified';
        // Verification disabled → don't strand accounts that were created (or got
        // stuck) under the old flow: treat them as verified and let them in.
        auth_update($acc['id'], ['status' => 'pending', 'email_verified' => true, 'email_token' => '']);
        $acc['status'] = 'pending'; $acc['email_verified'] = true;
    }
    /* BELGE askisi giris engeli DEGIL: belge yuzunden askiya alinan satici
       panele girip tam da o belgeyi yukleyebilmeli (seller.php onu KYC
       sekmesine kilitler). Operatorun elle askiya aldigi hesap ise girmez. */
    if (($acc['status'] ?? '') === 'suspended' && !auth_suspended_for_docs($acc)) return 'suspended';
    return $acc;
}

/* Askinin sebebi belge mi? cron_seller_docs.php suspend_reason='docs' yazar;
   operatorun Suspend dugmesi 'operator'. Sebebi olmayan eski aski = operator. */
function auth_suspended_for_docs(?array $acc): bool {
    return $acc !== null && (($acc['status'] ?? '') === 'suspended') && (($acc['suspend_reason'] ?? '') === 'docs');
}

/* Set a new password (the only sanctioned way to touch 'hash' — auth_update locks it). */
function auth_set_password(string $id, string $newPassword): bool {
    if (strlen($newPassword) < 8) return false;
    $list = auth_accounts(); $ok = false;
    foreach ($list as &$a) {
        if (($a['id'] ?? '') === $id) {
            $a['hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
            unset($a['reset_token'], $a['reset_expires']);
            $ok = true; break;
        }
    }
    if ($ok) auth_save_accounts($list);
    return $ok;
}

/* ── Password reset tokens ─────────────────────────────────────────────────── */
function auth_reset_begin(string $email): ?array {
    $acc = auth_find($email);
    if (!$acc) return null;
    $token = bin2hex(random_bytes(24));
    auth_update($acc['id'], ['reset_token'=>$token, 'reset_expires'=>date('c', time()+3600)]);
    $acc['reset_token'] = $token;
    return $acc;
}
function auth_reset_find(string $token): ?array {
    if ($token === '') return null;
    foreach (auth_accounts() as $a) {
        if (!empty($a['reset_token']) && hash_equals($a['reset_token'], $token)) {
            if (strtotime($a['reset_expires'] ?? '') < time()) return null; // expired
            return $a;
        }
    }
    return null;
}

/* ── Login throttling (per email+IP, file-based) ───────────────────────────────
 * auth_throttled($key)  → true when ≥5 failures in the last 15 min (still blocked)
 * auth_throttle_hit / auth_throttle_clear on failure / success. */
define('VESTRA_THROTTLE', __DIR__.'/../data/login_throttle.json');
function auth_throttle_load(): array {
    if (!is_file(VESTRA_THROTTLE)) return [];
    return json_decode((string)@file_get_contents(VESTRA_THROTTLE), true) ?: [];
}
function auth_throttle_save(array $d): void {
    $now = time();
    $d = array_filter($d, fn($e) => ($e['first'] ?? 0) > $now - 3600); // prune stale entries
    @file_put_contents(VESTRA_THROTTLE, json_encode($d), LOCK_EX);
}
function auth_throttled(string $key, int $max = 5, int $window = 900): bool {
    $e = auth_throttle_load()[$key] ?? null;
    return $e && ($e['n'] ?? 0) >= $max && ($e['first'] ?? 0) > time() - $window;
}
function auth_throttle_hit(string $key): void {
    $d = auth_throttle_load();
    $e = $d[$key] ?? ['n'=>0, 'first'=>time()];
    if (($e['first'] ?? 0) < time() - 900) $e = ['n'=>0, 'first'=>time()]; // window rolled over
    $e['n']++;
    $d[$key] = $e;
    auth_throttle_save($d);
}
function auth_throttle_clear(string $key): void {
    $d = auth_throttle_load();
    if (isset($d[$key])) { unset($d[$key]); auth_throttle_save($d); }
}

function auth_update(string $id, array $fields): void {
    $list   = auth_accounts();
    $locked = ['id', 'hash', 'email', 'created'];
    foreach ($list as &$a) {
        if ($a['id'] === $id) {
            foreach ($fields as $k => $v)
                if (!in_array($k, $locked)) $a[$k] = $v;
            break;
        }
    }
    auth_save_accounts($list);
}

// ── Document management ────────────────────────────────────────────────────
define('VESTRA_DOCS_DIR', __DIR__.'/../data/docs');

function auth_docs_dir(string $uid): string {
    $base = VESTRA_DOCS_DIR;
    if(!is_dir($base)) @mkdir($base, 0755, true);
    $htaccess = $base.'/.htaccess';
    if(!is_file($htaccess)) @file_put_contents($htaccess, "Deny from all\n");
    $dir = $base.'/'.$uid;
    if(!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

/**
 * What the trade licence is actually CALLED in a given country.
 *
 * "Gewerbeschein" was printed to everyone, everywhere. It is a German word for a
 * German document: an Irish boutique reading it learns nothing, and a marketplace
 * that asks for paperwork by the wrong name looks like it does not know which
 * country it is talking to. So the local name appears only where it IS the local
 * name, and everywhere else the neutral wording stands alone.
 *
 * Returns '' for countries with no entry — the caller then says just "trade
 * licence / business registration", which is true everywhere.
 */
function auth_trade_doc_local_name(string $cc): string {
    static $map = [
        'DE' => 'Gewerbeschein',
        'AT' => 'Gewerbeschein',
        'CH' => 'Handelsregisterauszug',
        'NL' => 'KvK-uittreksel',
        'BE' => 'KBO-uittreksel',
        'FR' => 'extrait Kbis',
        'IT' => 'visura camerale',
        'ES' => 'alta censal',
        'PT' => 'certidão permanente',
        'GB' => 'Certificate of Incorporation',
        'IE' => 'CRO registration',
        'PL' => 'wypis z CEIDG / KRS',
        'CZ' => 'živnostenský list',
        'GR' => 'βεβαίωση έναρξης εργασιών',
        'SE' => 'registreringsbevis',
        'DK' => 'CVR-registreringsbevis',
        'NO' => 'firmaattest',
        'FI' => 'kaupparekisteriote',
        'TR' => 'faaliyet belgesi',
        'AE' => 'trade licence',
        'AU' => 'ABN registration',
        'KR' => '사업자등록증',
        'JP' => '履歴事項全部証明書',
    ];
    return $map[strtoupper(trim($cc))] ?? '';
}

/**
 * "trade licence / business registration", plus the local name in brackets when
 * this visitor's country has one. $cc comes from vestra_visitor_cc().
 */
function auth_trade_doc_phrase(string $cc): string {
    $local = auth_trade_doc_local_name($cc);
    if ($local === '') return t('trade licence / business registration');
    /* Arayuz zaten o ulkenin dilindeyse yerel ad TEK BASINA yeter: Almanca okuyan
       bir Alman icin "Gewerbeanmeldung / Handelsregistereintrag (Gewerbeschein)"
       ayni seyi iki kez soylemek olur. Baska bir dilde okuyana ise parantez
       gerekiyor -- aradigi belgeyi kendi ulkesindeki adiyla taniyabilsin diye. */
    static $lang = ['DE'=>'de','AT'=>'de','CH'=>'de','FR'=>'fr','BE'=>'fr','IT'=>'it','ES'=>'es'];
    $cc = strtoupper(trim($cc));
    if (isset($lang[$cc]) && function_exists('vlang') && vlang() === $lang[$cc]) return $local;
    return t('trade licence / business registration').' ('.$local.')';
}

/* Platformun bir hesaptan GERCEKTEN istedigi belgeler. Tek kaynak: kayitta
 * acilan istekler, panelde gosterilen liste ve saticiya giden mektup ayni
 * yerden okusun. Ayri ayri yazildiklarinda kaciniz -- 20 Agustos'ta kaydolan
 * bir satici hesabinda BES istek duruyor (company_reg, vat_cert, auth_letter
 * dahil) cunku o gunku liste farkliydi; mektup "iki belge" derken panel bes
 * satir gosterirse musteri hangisine inanacagini bilmez.
 * auth_doc_types() bundan GENIS kalir: orada duran tipleri operator supheli
 * bir dosyada panelden tek tek yine isteyebilir. Buradaki liste ZORUNLU
 * olanlar; oradaki liste ISTENEBILIR olanlar. */
function auth_required_doc_types(string $accType): array {
    return $accType === 'seller'
        ? ['trade_licence', 'id_document']   // ticari kayit + kimlik
        : ['trade_licence'];
}

function auth_doc_types(string $cc = ''): array {
    $local = auth_trade_doc_local_name($cc);
    return [
        'trade_licence'=> 'Trade Licence'.($local !== '' ? ' / '.$local : ''),
        'company_reg'  => 'Company Registration',
        'vat_cert'     => 'VAT / Tax Certificate',
        'id_document'  => 'Government ID (Passport / National ID)',
        'auth_letter'  => 'Authorization Letter (if not director)',
        'other'        => 'Other document',
    ];
}

/* auth_trade_unlocked() BURADAYDI ve 31 Agustos 2026'dan beri hicbir kapiyi
   acmiyordu (KURAL 2: kapiyi operator onayi acar). Olu kod, okuyani "belge
   onaylaninca acilir" diye yaniltiyordu; kaldirildi. Tek kapi: auth_prices_unlocked(). */

/* FIYAT kapisi. ONAY bekler -- yukleme yetmez.
   Eskiden belgenin YUKLENMIS olmasi fiyati aciyordu; gerekcesi, onayin elle
   yapilmasi ve gun alabilmesiydi. Operator bunu tersine cevirdi: fiyat da
   Prufung'un arkasinda. Yani "herhangi bir PDF yukleyen fiyat listesini gorur"
   yolu kapandi; toptan fiyat, belgesi gercekten okunmus hesaplara gosteriliyor.

   Bedeli bilerek kabul ediliyor: belgesini gonderen alici onaya kadar katalogda
   fiyat goremiyor. Bunu tolere edilebilir kilan sey bekleme suresi -- talepler
   panele dusuyor ve elle onaylaniyor. Bekleyen alici "yukleyin" degil "inceleniyor"
   gormeli; sayfalar bunu auth_trade_doc_status() ile ayirt ediyor.

   Bayragi olmayan hesap (zorunluluk oncesi kayit) etkilenmez. */
function auth_prices_unlocked(?array $acc): bool {
    if (!$acc) return false;                       // giris yapmamis: zaten kapali
    return auth_user_approved($acc);   // belge UYARI; kapiyi operator onayi acar
}

/* Ticari belgenin nerede oldugu: '' (hic talep yok) | 'requested' (yuklenmedi)
   | 'uploaded' (yuklendi, onay bekliyor) | 'approved' | 'rejected'.
   Kapinin ACIK/KAPALI cevabi auth_prices_unlocked()'ta; bu fonksiyon KAPALI'nin
   sebebini soyluyor ki sayfa dogru cumleyi yazsin. Fiyat onaya baglandiktan sonra
   bu ayrim sart oldu: belgesini dun yuklemis bir aliciya "belgenizi yukleyin"
   demek, onu yaptigi isi tekrar yapmaya gonderir. */
function auth_trade_doc_status(?array $acc): string {
    if (!$acc) return '';
    $best = '';
    foreach ((array)($acc['doc_requests'] ?? []) as $r) {
        if (($r['type'] ?? '') !== 'trade_licence') continue;
        $st = (string)($r['status'] ?? 'requested');
        if ($st === 'approved') return 'approved';        // en guclu durum, aramayi bitir
        if ($st === 'uploaded') { $best = 'uploaded'; continue; }
        if ($best === '') $best = $st;                    // requested / rejected
    }
    return $best;
}

/* Fiyat neden kapali? Kapiyi cizen sayfalar dogru cumleyi secebilsin diye:
   'guest' = giris yok, 'approval' = giris var ama operator hesabi henuz acmadi.
   Eski deger 'doc' idi ve alti sayfa "belgenizi yukleyin, yukleyince fiyatlar
   acilir" yaziyordu -- KURAL 2'den (31 Agu 2026) beri YANLIS bir cumle: belge
   kapiyi acmaz, onay acar. Belgesini yuklemis ve onay bekleyen alici, panelin
   kendisinden yanlis bir talimat okuyordu. */
function auth_price_gate_reason(?array $acc): string {
    if (!$acc) return 'guest';
    return auth_prices_unlocked($acc) ? '' : 'approval';
}

function auth_request_doc(string $uid, string $type, string $note=''): void {
    $list = auth_accounts();
    foreach($list as &$a) {
        if($a['id']!==$uid) continue;
        if(!isset($a['doc_requests'])) $a['doc_requests']=[];
        foreach($a['doc_requests'] as &$r) {
            if($r['type']===$type && in_array($r['status'],['requested','uploaded'],true)){
                $r['note']=$note; $r['requested_at']=date('c');
                auth_save_accounts($list); return;
            }
        }
        /* by='operator': bunu KAYIT otomatigi degil, bir insan istedi.
           auth_prune_stale_doc_requests() bu damgaya bakip elle acilmis bir
           istegi asla silmiyor -- supheli bir dosyada operatorun ayrica
           istedigi belge, "artik zorunlu degil" diye temizlenirse sessizce
           kaybolurdu. */
        $a['doc_requests'][]=['id'=>bin2hex(random_bytes(4)),'type'=>$type,'note'=>$note,'status'=>'requested','requested_at'=>date('c'),'by'=>'operator'];
        break;
    }
    auth_save_accounts($list);
}

/* Kayit otomatiginin ESKI listesinden kalan, artik istenmeyen belge
 * isteklerini temizler. 20 Agustos'ta kaydolan bir saticida bes istek
 * duruyor (company_reg, vat_cert, auth_letter dahil); liste o gunden beri
 * ticari kayit + kimlige indi. Mektup iki belge isterken panelin bes satir
 * gostermesi musteriyi bizim beklemedigimiz kagitlarin pesine dusuruyor.
 *
 * ASLA silinmeyenler:
 *  - yuklenmis / onaylanmis / reddedilmis olanlar (kanit ve gecmis),
 *  - dosyasi olan her kayit (durumu ne olursa olsun),
 *  - by='operator' damgalilar (bir insan bilerek istedi),
 *  - halen zorunlu tipler.
 * $apply=false iken hicbir sey yazilmaz; ne olacagini dondurur. */
function auth_prune_stale_doc_requests(?string $uid = null, bool $apply = false): array {
    $list = auth_accounts();
    $report = ['accounts' => 0, 'removed' => 0, 'detail' => []];
    $changed = false;
    foreach ($list as &$a) {
        if ($uid !== null && ($a['id'] ?? '') !== $uid) continue;
        $reqs = (array)($a['doc_requests'] ?? []);
        if (!$reqs) continue;
        $need = auth_required_doc_types((string)($a['type'] ?? 'buyer'));
        $keep = []; $gone = [];
        foreach ($reqs as $r) {
            $ty = (string)($r['type'] ?? '');
            $st = strtolower((string)($r['status'] ?? 'requested'));
            $stale = $st === 'requested'
                  && empty($r['file'])
                  && ($r['by'] ?? '') !== 'operator'
                  && !in_array($ty, $need, true);
            if ($stale) { $gone[] = $ty; } else { $keep[] = $r; }
        }
        if (!$gone) continue;
        $report['accounts']++;
        $report['removed'] += count($gone);
        $report['detail'][] = ['id' => (string)($a['id'] ?? ''), 'type' => (string)($a['type'] ?? ''), 'removed' => $gone];
        if ($apply) { $a['doc_requests'] = array_values($keep); $changed = true; }
    }
    unset($a);
    if ($apply && $changed) auth_save_accounts($list);
    return $report;
}

/* ── Belge dosyasi: KONTROL + SAKLAMA ─────────────────────────────────────────
   2 Eylul 2026: yeni kaydolan bir alici belgesini iki gundur yukleyemedigini
   yazdi ve dosyayi e-postayla gonderdi. Yukleme yolu HER hatada ayni cumleyi
   basiyordu ("Upload failed... max 10 MB") -- sebep hicbir yerde kayitli
   degildi -- ve post_max_size asildiginda PHP $_POST'u bos birakir: handler
   hic calismaz, sayfa sessizce yeniden yuklenir, kullanici "hicbir sey
   olmuyor" gorur. Artik:
     (a) her ret bir SEBEP KODU tasir ve error_log'a yazilir (diag-messages
         upload_probe okur),
     (b) sinir sunucunun kendi ini degerinden okunur -- sunucu 2 MB'de
         kesiyorsa forma "10 MB'a kadar" yazmak yalan,
     (c) ayni kontrol operatorun e-postayla gelen dosyayi eklerken de calisir
         (auth_attach_doc_for), iki yol ayrisamaz.
   HEIC/HEIF kabul ediliyor: iPhone'un varsayilan fotograf bicimi. Tarayici
   onizleyemez ama dosya durur ve operator acar -- "hic yukleyememek"ten iyi. */
function auth_doc_allowed_ext(): array {
    return ['pdf','jpg','jpeg','png','webp','heic','heif'];
}

function auth_ini_bytes(string $v): int {
    $v = trim($v); if ($v === '' || $v === '-1') return 0;
    $n = (float)$v; $u = strtolower(substr($v, -1));
    if ($u === 'g') $n *= 1024*1024*1024; elseif ($u === 'm') $n *= 1024*1024; elseif ($u === 'k') $n *= 1024;
    return (int)$n;
}

/* Kabul edilecek en buyuk dosya: uygulama tavani (10 MB) ile sunucunun kendi
   sinirlarinin KUCUGU. Form (MAX_FILE_SIZE) ve hata metni bu sayiyi soyler. */
function auth_doc_max_bytes(): int {
    $m    = 10*1024*1024;
    $up   = auth_ini_bytes((string)ini_get('upload_max_filesize'));
    $post = auth_ini_bytes((string)ini_get('post_max_size'));
    if ($up > 0)   $m = min($m, $up);
    if ($post > 0) $m = min($m, max(0, $post - 64*1024));   // multipart basliklari icin pay
    return max($m, 256*1024);
}

/* '' = gecerli; aksi halde sebep kodu: size | type | partial | nofile | empty | server */
function auth_doc_file_check(?array $file, ?int $cap = null): string {
    if (!$file || !array_key_exists('error', $file)) return 'nofile';
    $err = (int)$file['error'];
    if ($err !== UPLOAD_ERR_OK) {
        return match($err) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'size',
            UPLOAD_ERR_PARTIAL                        => 'partial',
            UPLOAD_ERR_NO_FILE                        => 'nofile',
            default                                   => 'server',   // NO_TMP_DIR, CANT_WRITE, EXTENSION
        };
    }
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0) return 'empty';
    if ($size > ($cap ?? auth_doc_max_bytes())) return 'size';
    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, auth_doc_allowed_ext(), true)) return 'type';
    return '';
}

/* Sebep kodunun insan icin karsiligi (Ingilizce; paneller t() ile cevirir).
   Her metin bir CIKIS YOLU soyluyor -- "basarisiz" tek basina kullaniciyi
   ayni dosyayi ayni sekilde bir daha denemeye gonderir. */
function auth_doc_error_text(string $code): string {
    $mb = max(1, (int)floor(auth_doc_max_bytes() / (1024*1024)));
    return match($code) {
        'size'    => sprintf('The file is too large — the limit is %d MB. Photos are shrunk automatically when you pick them; if it is still too big, export the document as a PDF or a smaller JPG, or e-mail it to us.', $mb),
        'type'    => 'This file type is not accepted. Please upload a PDF, JPG, PNG, WebP or HEIC file — or e-mail it to us.',
        'partial' => 'The upload was interrupted before the whole file arrived. Please try again.',
        'empty', 'nofile' => 'No file was received. Please choose a file and try again.',
        'post'    => sprintf('The upload was larger than the server accepts (%d MB), so nothing was saved. Please choose a smaller file, or e-mail it to us.', $mb),
        default   => 'The file could not be stored on our side. Please try again in a moment, or e-mail it to us and we will attach it for you.',
    };
}

/* Dosyayi hesabin belge dizinine tasir. [ok, error, file]. Basarisizlik
   error_log'a NEDENIYLE yazilir -- bir sonraki "yukleyemiyorum" mektubunda
   kutuk okunur, tahmin edilmez. Adres/isim yazilmaz, yalnizca uid'in basi. */
function auth_store_doc_file(string $uid, string $reqId, array $file): array {
    $code = auth_doc_file_check($file);
    if ($code !== '') {
        error_log('[VESTRA doc] rejected uid='.substr($uid,0,8).' req='.$reqId.' code='.$code
                 .' err='.(int)($file['error'] ?? -1).' size='.(int)($file['size'] ?? 0)
                 .' ext='.strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION)));
        return ['ok'=>false,'error'=>$code,'file'=>''];
    }
    $ext   = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    $dir   = auth_docs_dir($uid);
    $fname = $reqId.'_'.bin2hex(random_bytes(4)).'.'.$ext;
    if (!is_dir($dir) || !is_writable($dir) || !@move_uploaded_file((string)$file['tmp_name'], $dir.'/'.$fname)) {
        error_log('[VESTRA doc] store FAILED uid='.substr($uid,0,8).' dir='.$dir
                 .' dir_ok='.(is_dir($dir) ? 'yes' : 'NO').' writable='.(is_writable($dir) ? 'yes' : 'NO')
                 .' tmp='.(is_uploaded_file((string)($file['tmp_name'] ?? '')) ? 'ok' : 'NOT-UPLOADED'));
        return ['ok'=>false,'error'=>'server','file'=>''];
    }
    return ['ok'=>true,'error'=>'','file'=>$fname];
}

/* Kullanicinin kendi yuklemesi: istek kaydini bulur, dosyayi saklar, durumu
   'uploaded' yapar. [ok, error, file]; 'noreq' = istek yok (eski/yanlis id). */
function auth_upload_doc_ex(string $uid, string $req_id, ?array $file, string $by = 'user'): array {
    if (!$file) return ['ok'=>false,'error'=>'nofile','file'=>''];
    $list = auth_accounts(); $found = false;
    foreach ($list as $a) {
        if (($a['id'] ?? '') !== $uid) continue;
        foreach ((array)($a['doc_requests'] ?? []) as $r) { if (($r['id'] ?? '') === $req_id) { $found = true; break; } }
        break;
    }
    if (!$found) { error_log('[VESTRA doc] no such request uid='.substr($uid,0,8).' req='.$req_id); return ['ok'=>false,'error'=>'noreq','file'=>'']; }
    $st = auth_store_doc_file($uid, $req_id, $file);
    if (!$st['ok']) return $st;
    foreach ($list as &$a) {
        if (($a['id'] ?? '') !== $uid) continue;
        foreach ($a['doc_requests'] as &$r) {
            if (($r['id'] ?? '') === $req_id) { $r['status']='uploaded'; $r['file']=$st['file']; $r['uploaded_at']=date('c'); $r['uploaded_by']=$by; break; }
        }
        unset($r);
        break;
    }
    unset($a);
    auth_save_accounts($list);
    return $st;
}
function auth_upload_doc(string $uid, string $req_id, array $file): bool { return auth_upload_doc_ex($uid, $req_id, $file)['ok']; }

/* OPERATOR, e-postayla gelen belgeyi hesaba ekler (operator karari, 2 Eyl 2026:
   "evrak email ile girilsin"). Ayni tipte ACIK bir istek (requested/rejected)
   varsa ona baglanir; yoksa by=operator ile yeni istek acilir -- prune bunu
   asla silmez. Durum 'uploaded': EKLEMEK ONAYLAMAK DEGIL, operator dosyayi
   panelde gorup ayrica onaylar. Ayni dosya kontrolu kullanicinin yuklemesiyle
   BIREBIR (auth_store_doc_file); iki yol farkli kural uygulayamaz. */
function auth_attach_doc_for(string $uid, string $type, ?array $file, string $note = ''): array {
    $type = preg_replace('/[^a-z_]/', '', strtolower($type));
    if ($type === '' || !isset(auth_doc_types()[$type])) return ['ok'=>false,'error'=>'doctype','file'=>'','req_id'=>''];
    if (!$file) return ['ok'=>false,'error'=>'nofile','file'=>'','req_id'=>''];
    $list = auth_accounts(); $idx = null; $reqId = '';
    foreach ($list as $i => $a) {
        if (($a['id'] ?? '') !== $uid) continue;
        $idx = $i;
        foreach ((array)($a['doc_requests'] ?? []) as $r) {
            if (($r['type'] ?? '') === $type && in_array((string)($r['status'] ?? 'requested'), ['requested','rejected'], true)) { $reqId = (string)($r['id'] ?? ''); break; }
        }
        break;
    }
    if ($idx === null) return ['ok'=>false,'error'=>'noacc','file'=>'','req_id'=>''];
    if ($reqId === '') $reqId = bin2hex(random_bytes(4));
    $st = auth_store_doc_file($uid, $reqId, $file);
    if (!$st['ok']) return $st + ['req_id'=>$reqId];
    $a = &$list[$idx];
    if (!isset($a['doc_requests']) || !is_array($a['doc_requests'])) $a['doc_requests'] = [];
    $done = false;
    foreach ($a['doc_requests'] as &$r) {
        if (($r['id'] ?? '') !== $reqId) continue;
        $r['status']='uploaded'; $r['file']=$st['file']; $r['uploaded_at']=date('c'); $r['uploaded_by']='operator';
        if ($note !== '') $r['admin_note'] = $note;
        $done = true; break;
    }
    unset($r);
    if (!$done) {
        $a['doc_requests'][] = ['id'=>$reqId,'type'=>$type,'note'=>'Received by e-mail and attached by VESTRA.',
            'status'=>'uploaded','requested_at'=>date('c'),'uploaded_at'=>date('c'),'by'=>'operator','uploaded_by'=>'operator',
            'file'=>$st['file']] + ($note !== '' ? ['admin_note'=>$note] : []);
    }
    unset($a);
    auth_save_accounts($list);
    return $st + ['req_id'=>$reqId];
}

function auth_review_doc(string $uid, string $req_id, string $status, string $note=''): void {
    $list=auth_accounts();
    foreach($list as &$a){
        if($a['id']!==$uid) continue;
        foreach($a['doc_requests'] as &$r){
            if($r['id']===$req_id){ $r['status']=$status; if($note) $r['admin_note']=$note; $r['reviewed_at']=date('c'); break; }
        }
        break;
    }
    auth_save_accounts($list);
}

function auth_doc_file_path(string $uid, string $filename): string {
    return VESTRA_DOCS_DIR.'/'.$uid.'/'.$filename;
}

/* Hesabin HALA borclu oldugu zorunlu belgeler. Tek dogruluk kaynagi
   auth_required_doc_types() (KURAL 2): satici trade_licence + id_document,
   alici trade_licence. 'uploaded' VERILMIS sayilir -- operator onayini
   bekliyor; kullaniciya "yukle" demek yaptigi isi tekrar yaptirmak olurdu.
   Bu liste iki cron'da ve satici panelinde okunuyor; ayri ayri yazildiklarinda
   ayrisirlar (KURAL 2 tam da bu yuzden tek kaynaga baglandi). */
function auth_missing_doc_types(array $a): array {
    $need = auth_required_doc_types((string)($a['type'] ?? 'buyer'));
    $have = [];
    foreach ((array)($a['doc_requests'] ?? []) as $r) {
        $t = (string)($r['type'] ?? ''); $s = (string)($r['status'] ?? 'requested');
        if ($t !== '' && in_array($s, ['uploaded','approved'], true)) $have[$t] = true;
    }
    return array_values(array_filter($need, fn($t) => !isset($have[$t])));
}

// ── Satici belge suresi (operator karari, 2 Eyl 2026) ───────────────────────
/* "Satici bolumunde ilk urun eklensin, sonrasinda belgeleri eklemesi icin
   sure verilsin -- 3 gun gibi; yuklemezse suspend olsun."
   Saat, hesaptaki doc_grace_start damgasiyla baslar: ILK ILAN kaydedildiginde
   (seller-add.php) ya da bu kural yururluge girdiginde zaten ilani olan
   saticiyi cron ilk gordugunde. Ilan tarihinden GERIYE DONUK hesaplanmaz:
   46 gun once ilan vermis bir saticiyi ilk cron kosusunda uyarmadan askiya
   almak kural degil tuzak olurdu. Ilani olmayan saticiya saat islemez --
   platformda koruyacak bir sey yok.
   Karar SAF fonksiyonda (auth_seller_doc_grace): cron, satici paneli ve admin
   ayni cevabi okur; test de onu kosar. */
if (!defined('VESTRA_SELLER_DOC_GRACE_DAYS')) define('VESTRA_SELLER_DOC_GRACE_DAYS', 3);

/* MUAFIYET. Operator karari, 2 Eyl 2026: "GARAGE LE PARIS'i muaf tut" -- ana
   ortak satici (56 ilan, faturalar onun adina kesiliyor); kural yururluge
   girdiginde ilk sabah saati baslayacak iki saticidan biriydi. Kodda VARSAYILAN
   liste (hemen ve kesin etkili olsun diye), uzerine hesap bayragi
   doc_grace_exempt: panelden acilir/kapatilir ve kodun varsayilanini EZER --
   yani GARAGE'in suresi bir gun panelden yeniden acilabilir. Muaf satici:
   saat yok, mektup yok, aski yok; belgeleri yine istenir ve panelde acik durur. */
function auth_doc_grace_exempt_uids(): array {
    return ['7ab30f26afedd840'];   // GARAGE LE PARIS
}
function auth_doc_grace_exempt(array $acc): bool {
    if (array_key_exists('doc_grace_exempt', $acc) && $acc['doc_grace_exempt'] !== null && $acc['doc_grace_exempt'] !== '') {
        return (bool)$acc['doc_grace_exempt'];
    }
    return in_array((string)($acc['id'] ?? ''), auth_doc_grace_exempt_uids(), true);
}

function auth_seller_doc_grace(array $acc, array $listings, ?int $now = null): array {
    $now = $now ?? time();
    $out = ['phase'=>'clear', 'missing'=>[], 'start'=>null, 'deadline'=>null, 'days_left'=>null,
            'has_listing'=>count($listings) > 0, 'reason'=>(string)($acc['suspend_reason'] ?? ''),
            'exempt'=>false];
    if (($acc['type'] ?? '') !== 'seller') return $out;
    $out['missing'] = auth_missing_doc_types($acc);
    if (!$out['missing']) return $out;                                    // belgeler tamam
    if (auth_doc_grace_exempt($acc)) { $out['phase'] = 'exempt'; $out['exempt'] = true; return $out; }
    if (($acc['status'] ?? '') === 'suspended') { $out['phase'] = 'suspended'; return $out; }
    if (!$listings) { $out['phase'] = 'none'; return $out; }              // ilan yok: saat yok
    $start = strtotime((string)($acc['doc_grace_start'] ?? '')) ?: null;
    if (!$start) { $out['phase'] = 'unstamped'; return $out; }            // ilani var, saat henuz baslamadi
    $deadline = $start + VESTRA_SELLER_DOC_GRACE_DAYS * 86400;
    $left = $deadline - $now;
    $out['start'] = $start; $out['deadline'] = $deadline;
    $out['days_left'] = (int)ceil($left / 86400);
    $out['phase'] = $left <= 0 ? 'expired' : ($left <= 86400 ? 'due_soon' : 'running');
    return $out;
}

/* Saati baslat (damga yoksa) ya da $force ile yeniden baslat. Yeniden
   baslatma bildirim damgalarini da siler: askidan cikarilan saticiya taze 3
   gun ve taze bir uyari mektubu -- eski damga dursaydi ertesi sabah cron
   ayni hesabi yeniden askiya alirdi. */
function auth_seller_doc_grace_start(string $uid, bool $force = false): void {
    $acc = null;
    foreach (auth_accounts() as $a) { if (($a['id'] ?? '') === $uid) { $acc = $a; break; } }
    if (!$acc || ($acc['type'] ?? '') !== 'seller') return;
    if (!$force && !empty($acc['doc_grace_start'])) return;
    auth_update($uid, ['doc_grace_start'=>date('c'), 'doc_grace_notice_at'=>'', 'doc_grace_reminder_at'=>'', 'doc_grace_suspended_at'=>'']);
}
