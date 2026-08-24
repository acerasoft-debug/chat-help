<?php
/**
 * VESTRA — File-based account auth (no database required).
 * Accounts stored in data/accounts.json (server-side, not web-accessible).
 */

/* Defined before the session bootstrap below, because auth_remember_restore()
   (called during that bootstrap) reads the accounts file. */
define('VESTRA_ACCOUNTS', __DIR__.'/../data/accounts.json');

/* Session-cookie hardening — must run before any session_start() (auth.php is
 * required before sessions start everywhere). HttpOnly blocks JS access,
 * SameSite=Lax blocks cross-site POSTs riding the session, Secure on HTTPS. */
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

   Uploading a document used to be enough on its own. It should not be: uploading is
   the applicant's action, not ours, and anyone who attached a file — any file, to any
   of the four requests — unlocked trade prices, seller identities and the full export
   before a human had opened it. Submitting a document is now what it says it is, a
   submission; access waits for the approval. */
function auth_user_approved(?array $u): bool {
    if ($u === null) return false;
    $base = (($u['status'] ?? '') === 'active') || (($u['kyb_status'] ?? '') === 'approved');
    if (!$base) return false;
    /* Gewerbe/trade-licence sarti. Kayit akisi bu belgeyi zaten istiyordu ve notunda
       "An account cannot be activated without it" yaziyordu -- ama hicbir kod bunu
       kontrol etmiyordu. Sart artik onayin parcasi: operator hesabi elle 'active'
       yapsa bile, trade_licence ONAYLANMADAN katalog acilmaz.
       Bayragi olmayan (zorunluluk oncesi) hesaplar etkilenmez. */
    return auth_trade_unlocked($u);
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
        if (($a['status'] ?? '') === 'suspended') return; // never auto-restore a suspended account
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
function auth_touch_login(string $id): void {
    auth_update($id, ['last_login' => date('c')]);
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
    // Auto document requests on registration
    $ts = date('c');
    if($type === 'seller'){
        $acc['doc_requests'] = [
            ['id'=>bin2hex(random_bytes(4)),'type'=>'trade_licence','note'=>'Please upload your trade licence / business registration (Gewerbeschein or your country\'s equivalent). An account cannot be activated without it.','status'=>'requested','requested_at'=>$ts],
            ['id'=>bin2hex(random_bytes(4)),'type'=>'company_reg', 'note'=>'Please upload your company registration certificate (Handelsregister / KvK / equivalent).','status'=>'requested','requested_at'=>$ts],
            ['id'=>bin2hex(random_bytes(4)),'type'=>'vat_cert',    'note'=>'Please upload your VAT or tax registration certificate (Umsatzsteuer-ID confirmation or equivalent).','status'=>'requested','requested_at'=>$ts],
            ['id'=>bin2hex(random_bytes(4)),'type'=>'id_document', 'note'=>'Please upload a government-issued ID: passport, national ID card, or driving licence.','status'=>'requested','requested_at'=>$ts],
            ['id'=>bin2hex(random_bytes(4)),'type'=>'auth_letter', 'note'=>'If you are not the sole director/owner of the company, upload a signed authorization letter. You may skip this if you are the sole director.','status'=>'requested','requested_at'=>$ts],
        ];
    } elseif($type === 'buyer'){
        $acc['doc_requests'] = [
            ['id'=>bin2hex(random_bytes(4)),'type'=>'trade_licence','note'=>'Please upload your trade licence / business registration (Gewerbeschein or your country\'s equivalent). An account cannot be activated without it.','status'=>'requested','requested_at'=>$ts],
            ['id'=>bin2hex(random_bytes(4)),'type'=>'company_reg', 'note'=>'Please upload your company registration certificate to complete buyer verification.','status'=>'requested','requested_at'=>$ts],
            ['id'=>bin2hex(random_bytes(4)),'type'=>'vat_cert',    'note'=>'Please upload your VAT or tax registration certificate.','status'=>'requested','requested_at'=>$ts],
        ];
    }
    $list[] = $acc;
    auth_save_accounts($list);
    if ($promo_data) { promo_use($promo_code); }

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
    if (($acc['status'] ?? '') === 'suspended')     return 'suspended';
    return $acc;
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

function auth_doc_types(): array {
    return [
        'trade_licence'=> 'Trade Licence / Gewerbeschein',
        'company_reg'  => 'Company Registration',
        'vat_cert'     => 'VAT / Tax Certificate',
        'id_document'  => 'Government ID (Passport / National ID)',
        'auth_letter'  => 'Authorization Letter (if not director)',
        'other'        => 'Other document',
    ];
}

/* Ticari katalog (fiyat, fotograf, line-sheet) acik mi?
   - Bayragi olmayan hesap = zorunluluk oncesi kayit -> her zaman acik.
   - Bayragi olan hesap = trade_licence belgesi ONAYLANANA kadar kapali.
   Sadece 'approved' aciyor: 'uploaded' yeterli degil, yoksa herhangi bir PDF
   yukleyen kapiyi kendi kendine acardi. */
function auth_trade_unlocked(?array $acc): bool {
    if (!$acc) return false;
    if (empty($acc['trade_doc_required'])) return true;
    foreach ((array)($acc['doc_requests'] ?? []) as $r) {
        if (($r['type'] ?? '') === 'trade_licence' && ($r['status'] ?? '') === 'approved') return true;
    }
    return false;
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
        $a['doc_requests'][]=['id'=>bin2hex(random_bytes(4)),'type'=>$type,'note'=>$note,'status'=>'requested','requested_at'=>date('c')];
        break;
    }
    auth_save_accounts($list);
}

function auth_upload_doc(string $uid, string $req_id, array $file): bool {
    if($file['error']!==UPLOAD_ERR_OK||$file['size']>10*1024*1024) return false;
    $ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
    if(!in_array($ext,['pdf','jpg','jpeg','png','webp'],true)) return false;
    $dir=auth_docs_dir($uid);
    $fname=$req_id.'_'.bin2hex(random_bytes(4)).'.'.$ext;
    if(!@move_uploaded_file($file['tmp_name'],$dir.'/'.$fname)) return false;
    $list=auth_accounts();
    foreach($list as &$a){
        if($a['id']!==$uid) continue;
        foreach($a['doc_requests'] as &$r){
            if($r['id']===$req_id){ $r['status']='uploaded'; $r['file']=$fname; $r['uploaded_at']=date('c'); break; }
        }
        break;
    }
    auth_save_accounts($list);
    return true;
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
