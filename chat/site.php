<?php
/**
 * ChatHelp — Öffentliche Website aus dem Baukasten: chat/site.php?s=<slug>[&lang=xx][&page=impressum|datenschutz]
 *   &preview=<token>   Entwurf ansehen (Studio-Vorschau; immer frisch, noindex, Platzhalter markiert)
 *   &demo=<vorlage>    Beispiel-Website je Vorlage (Startseite/Studio-Vorschaubilder; &thumb=1 ohne Skripte)
 *   &sitemap=xml       Sitemap der veröffentlichten Website
 *   POST &a=lead       Kontaktformular → site_leads (Rate-Limit, Honigtopf, signierter Zeitstempel) → 303 zurück mit &f=ok|err|rate|off
 * Caching: veröffentlichte Seiten ohne ?v= werden immer frisch ausgeliefert (kanonische URL); interne Links tragen ?v=<Version> und sind öffentlich
 * cachebar (neue Veröffentlichung = neue Version; veraltete Version → 302 auf die aktuelle). Entwürfe/Vorschau: no-store + noindex.
 * Eigene Domain: zeigt der Host (CNAME/Weiterleitung) auf diese Installation, wird die Website mit custom_domain = Host ausgeliefert.
 */
require_once __DIR__.'/site-render.php';
site_bootstrap();

function sp_sec(): void {
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https://chat-help.com; connect-src 'self'; frame-ancestors 'self'; form-action 'self' https://chat-help.com; base-uri 'none'; object-src 'none'");
    header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: strict-origin-when-cross-origin');
}
function sp_html(string $html, int $code=200): void { http_response_code($code); header('Content-Type: text/html; charset=UTF-8'); sp_sec(); echo $html; exit; }
function sp_public_cache(int $max, int $edge): void { header('Cache-Control: public, max-age='.$max.', s-maxage='.$edge.', stale-while-revalidate=600'); header('Vary: Accept-Encoding'); }
function sp_q(array $q): string { return '/chat/site.php?'.http_build_query(array_filter($q,fn($v)=>$v!==null && $v!=='')); }

$host=strtolower(preg_replace('/:\d+$/','',(string)($_SERVER['HTTP_HOST'] ?? '')) ?? '');
$isMain=$host==='' || (bool)preg_match('/(^|\.)chat-help\.com$|^localhost$|^127\.|^\[?::1\]?$/',$host);
$L=site_lang($_GET['lang'] ?? '');
$page=in_array($_GET['page'] ?? '',['impressum','datenschutz'],true) ? (string)$_GET['page'] : 'home';

/* ---- Beispiel-Websites ---- */
if(isset($_GET['demo'])){
    $tpl=preg_replace('/[^a-z]/','',(string)$_GET['demo']); if(!isset(SITE_TEMPLATES[$tpl])) $tpl='gastro';
    $S=site_demo($tpl,$L ?: 'de'); header('X-Robots-Tag: noindex');
    $v=substr(md5((string)@filemtime(__DIR__.'/site-render.php').'|'.(string)@filemtime(__DIR__.'/site-lib.php')),0,8);
    if(($_GET['v'] ?? '')===$v) sp_public_cache(3600,86400); else th_nocache();
    sp_html(site_render($S,$S['lang_default'],['mode'=>isset($_GET['thumb']) ? 'thumb' : 'demo','page'=>$page,'mods'=>['appt'=>false,'order'=>false,'hotel'=>false,'ride'=>false,'callback'=>false,'book_url'=>'','callback_url'=>'']]));
}

$slug=is_string($_GET['s'] ?? null) ? strtolower(trim($_GET['s'])) : '';
$S=$slug!=='' ? site_by_slug($slug) : (!$isMain ? site_by_domain($host) : null);
/* Hauptdomain ohne Website-Kennung (direkter Aufruf, Crawler) → Baukasten-Bereich der Startseite statt 404 */
if($isMain && $slug==='' && !isset($_GET['a']) && !isset($_GET['preview']) && ($_SERVER['REQUEST_METHOD'] ?? 'GET')==='GET'){ th_nocache(); header('Location: /#website',true,302); exit; }

/* ---- Kontaktformular ---- */
if(($_GET['a'] ?? '')==='lead'){
    if($_SERVER['REQUEST_METHOD']!=='POST' || !$S){ th_nocache(); sp_html(site_notfound($L ?: 'de'),404); }
    $res=site_lead_submit($S,$_POST,th_client_ip());
    $f=isset($res['ok']) ? 'ok' : substr((string)$res['error'],2);
    $lang=site_lang($_POST['lang'] ?? '') ?: $S['lang_default'];
    th_nocache();
    if(($_GET['r'] ?? '')==='export' && $S['status']!=='published') header('Location: '.sp_q(['s'=>$S['slug'],'thanks'=>'1','f'=>$f,'lang'=>$lang!==$S['lang_default'] ? $lang : null]),true,303);
    else header('Location: '.sp_q(['s'=>$S['slug'],'lang'=>$lang!==$S['lang_default'] ? $lang : null,'f'=>$f]).'#contact',true,303);
    exit;
}
/* Danke-Seite für exportierte (nicht bei ChatHelp veröffentlichte) Websites */
if(isset($_GET['thanks']) && $S){
    th_nocache(); header('X-Robots-Tag: noindex'); $l=$L ?: $S['lang_default']; $f=in_array($_GET['f'] ?? '',['ok','err','rate','off'],true) ? (string)$_GET['f'] : 'ok';
    $msg=sr_t($l,$f==='ok' ? 'f_ok' : 'f_'.$f); $back=$S['custom_domain']!=='' ? 'https://'.$S['custom_domain'].'/' : '';
    sp_html('<!DOCTYPE html><html lang="'.sr_e(SITE_LANG_TAGS[$l]).'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.sr_e((string)($S['data']['biz']['name'] ?? 'ChatHelp')).'</title><meta name="robots" content="noindex"><style>'
      .sr_vars($S['theme']).sr_css().'</style></head><body><main class="nf"><div><h1>'.sr_e((string)($S['data']['biz']['name'] ?? '')).'</h1><p class="msg '.($f==='ok' ? 'ok' : 'err').'" role="status">'.sr_e($msg).'</p>'
      .($back!=='' ? '<p style="margin-top:22px"><a class="btn p" href="'.sr_e($back).'">'.sr_e(sr_t($l,'back')).'</a></p>' : '').'</div></main></body></html>');
}
/* ---- Vorschau (Entwurf) ---- */
$tok=is_string($_GET['preview'] ?? null) ? (string)$_GET['preview'] : '';
if($S && $tok!==''){
    th_nocache(); header('X-Robots-Tag: noindex, nofollow');
    if(!hash_equals(site_preview_token($S),$tok)) sp_html(site_notfound($L ?: $S['lang_default']),404);
    $wid=site_widget($S['account_id'],$S['custom_domain']);
    sp_html(site_render($S,$L ?: $S['lang_default'],['mode'=>'preview','page'=>$page,'token'=>$tok,'widget_key'=>$wid['key']]));
}
/* ---- Öffentlich ---- */
$V=$S ? site_published_view($S) : null;
if(!$V){ th_nocache(); header('X-Robots-Tag: noindex'); sp_html(site_notfound($L ?: 'de'),404); }
if(($_GET['sitemap'] ?? '')==='xml'){ th_nocache(); header('Content-Type: application/xml; charset=UTF-8'); echo site_sitemap($V); exit; }
if($L!=='' && !in_array($L,$V['langs'],true)){ th_nocache(); header('Location: '.sp_q(['s'=>$V['slug'],'page'=>$page!=='home' ? $page : null]),true,302); exit; }
$ver=site_pub_version($S);
$flash=in_array($_GET['f'] ?? '',['ok','err','rate','off'],true) ? 'f_'.$_GET['f'] : '';
if(isset($_GET['v']) && $flash===''){
    if((string)$_GET['v']!==$ver){ th_nocache(); $q=$_GET; $q['v']=$ver; header('Location: /chat/site.php?'.http_build_query($q),true,302); exit; }
    sp_public_cache(600,86400);
} else th_nocache();
$wid=!empty($V['data']['widget']) ? site_widget($V['account_id'],$V['custom_domain']) : ['key'=>null];
sp_html(site_render($V,$L ?: $V['lang_default'],['mode'=>'public','page'=>$page,'ver'=>$ver,'widget_key'=>$wid['key'],'flash'=>$flash,'host'=>$isMain ? '' : $host]));
