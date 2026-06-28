<?php
/**
 * VESTRA — File-based account auth (no database required).
 * Accounts stored in data/accounts.json (server-side, not web-accessible).
 */

define('VESTRA_ACCOUNTS', __DIR__.'/../data/accounts.json');

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

function auth_set(array $acc): void {
    $_SESSION['uid']    = $acc['id'];
    $_SESSION['member'] = true;
    $_SESSION['utype']  = $acc['type'];
}

function auth_logout(): void {
    unset($_SESSION['uid'], $_SESSION['member'], $_SESSION['utype']);
}

function auth_register(array $d): array|string {
    if (auth_find($d['email'] ?? '')) return 'email_taken';
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

    $list = auth_accounts();
    $type = in_array($d['type'] ?? '', ['seller', 'buyer']) ? $d['type'] : 'buyer';
    $acc  = [
        'id'            => bin2hex(random_bytes(8)),
        'email'         => strtolower(trim($d['email'])),
        'hash'          => password_hash($d['password'], PASSWORD_DEFAULT),
        'type'          => $type,
        'status'        => 'active',
        'name'          => trim($d['name']        ?? ''),
        'company'       => trim($d['company']     ?? ''),
        'vat_id'        => trim($d['vat_id']      ?? ''),
        'reg_number'    => trim($d['reg_number']  ?? ''),
        'country'       => trim($d['country']     ?? ''),
        'address'       => trim($d['address']     ?? ''),
        'phone'         => trim($d['phone']       ?? ''),
        'website'       => trim($d['website']     ?? ''),
        'kyb_status'    => $promo_data ? 'approved' : 'pending',
        'promo_code'    => $promo_code,
        'promo_benefit' => $promo_data['benefit'] ?? '',
        'promo_expiry'  => $promo_data['expiry']  ?? '',
        'created'       => date('c'),
        'doc_requests'  => [],
    ];
    // Auto document requests on registration
    $ts = date('c');
    if($type === 'seller'){
        $acc['doc_requests'] = [
            ['id'=>bin2hex(random_bytes(4)),'type'=>'company_reg', 'note'=>'Please upload your company registration certificate (Handelsregister / KvK / equivalent).','status'=>'requested','requested_at'=>$ts],
            ['id'=>bin2hex(random_bytes(4)),'type'=>'vat_cert',    'note'=>'Please upload your VAT or tax registration certificate (Umsatzsteuer-ID confirmation or equivalent).','status'=>'requested','requested_at'=>$ts],
            ['id'=>bin2hex(random_bytes(4)),'type'=>'id_document', 'note'=>'Please upload a government-issued ID: passport, national ID card, or driving licence.','status'=>'requested','requested_at'=>$ts],
            ['id'=>bin2hex(random_bytes(4)),'type'=>'auth_letter', 'note'=>'If you are not the sole director/owner of the company, upload a signed authorization letter. You may skip this if you are the sole director.','status'=>'requested','requested_at'=>$ts],
        ];
    } elseif($type === 'buyer'){
        $acc['doc_requests'] = [
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
    // Auto-acknowledge the new user
    if(vestra_cfg('confirm_user', true)){
        $lang = substr($_COOKIE['vlang'] ?? 'en', 0, 2);
        [$subj, $body] = vestra_ack_text($lang, $acc['name'] ?: $acc['company'], $type);
        vestra_send_mail($acc['email'], $subj, $body);
    }
    return $acc;
}

function auth_login(string $email, string $password): array|false {
    $acc = auth_find($email);
    if (!$acc) return false;
    if (!password_verify($password, $acc['hash'] ?? '')) return false;
    return $acc;
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
        'company_reg'  => 'Company Registration',
        'vat_cert'     => 'VAT / Tax Certificate',
        'id_document'  => 'Government ID (Passport / National ID)',
        'auth_letter'  => 'Authorization Letter (if not director)',
        'other'        => 'Other document',
    ];
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
