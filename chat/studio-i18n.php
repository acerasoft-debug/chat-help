<?php
/**
 * ChatHelp Studio — Oberflächensprache und Wörterbuch (13 Sprachen, Deutsch = Quelle)
 * ==================================================================================
 * Wörterbücher: chat/studio-i18n/<lang>.json  {schlüssel: text}. Platzhalter {name}. Fehlt ein Schlüssel: Englisch, dann Deutsch.
 *   „src.*“-Schlüssel übersetzen Texte, die der Server auf Deutsch liefert (Einrichtungsfragen, Profil-Optionen …): Deutsch = exakter Text.
 *   „srx.*“-Schlüssel sind Muster dafür: {name} im deutschen Text steht für einen beliebigen Teil, der in die Übersetzung übernommen wird.
 *
 *   sti_lang()            → [lang, quelle]   ?lang= (setzt Cookie) → Cookie ch_lang (auch von der Startseite) → Accept-Language → 'de'
 *                            (Profilsprache langs[0] wertet das Studio nach der Anmeldung aus, wenn nichts ausdrücklich gewählt ist.)
 *   sti_ver($lang)        → Versions-Hash für die Wörterbuch-URL (Dateistände de/en/<lang> + diese Datei)
 *   sti_head($lang,$src)  → <script>-Tags für den <head> des Studios (Wörterbuch + Laufzeit studio-i18n.js, beide versioniert)
 *   GET /chat/studio-i18n.php?l=fr&v=…  → JavaScript: window.CH_I18N={l,tag,src,d,src,srx}  (lange cachebar, URL ändert sich mit jedem Stand)
 */
const STI_LANGS = ['de'=>['Deutsch','de-DE'],'fr'=>['Français','fr-FR'],'tr'=>['Türkçe','tr-TR'],'az'=>['Azərbaycanca','az-AZ'],'es'=>['Español','es-ES'],
  'it'=>['Italiano','it-IT'],'en'=>['English','en-GB'],'ru'=>['Русский','ru-RU'],'nl'=>['Nederlands','nl-NL'],'sv'=>['Svenska','sv-SE'],'no'=>['Norsk','nb-NO'],
  'fi'=>['Suomi','fi-FI'],'ja'=>['日本語','ja-JP']];
const STI_DIR = __DIR__.'/studio-i18n';

function sti_norm($l): string {
    if(!is_string($l)) return '';
    $l=strtolower(trim(str_replace('_','-',$l))); if($l==='') return '';
    if(preg_match('/^(nb|nn|no)(-|$)/',$l)) return 'no';
    $p=substr($l,0,2); return isset(STI_LANGS[$p]) ? $p : '';
}
/** @return array{0:string,1:string} Sprache + Quelle (query|cookie|accept|default) */
function sti_lang(): array {
    $q=sti_norm($_GET['lang'] ?? '');
    if($q!==''){ if(!headers_sent() && PHP_SAPI!=='cli') header('Set-Cookie: ch_lang='.$q.'; Path=/; Max-Age=31536000; Secure; SameSite=Lax',false); $_COOKIE['ch_lang']=$q; return [$q,'query']; }
    $c=sti_norm($_COOKIE['ch_lang'] ?? ''); if($c!=='') return [$c,'cookie'];
    $al=(string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    if($al!==''){ $cand=[];
        foreach(explode(',',$al) as $i=>$part){ $bits=explode(';',trim($part)); $qv=1.0; foreach(array_slice($bits,1) as $b) if(preg_match('/^\s*q=([0-9.]+)/',$b,$m)) $qv=(float)$m[1]; $cand[]=[$qv,-$i,sti_norm($bits[0])]; }
        rsort($cand); foreach($cand as [$qv,,$l]) if($qv>0 && $l!=='') return [$l,'accept']; }
    return ['de','default'];
}
function sti_file(string $l): string { return STI_DIR.'/'.$l.'.json'; }
function sti_ver(string $l): string {
    $f=[__FILE__,sti_file('de'),sti_file('en'),sti_file($l),__DIR__.'/studio-i18n.js'];
    return substr(md5(implode('|',array_map(fn($x)=>$x.':'.(string)@filemtime($x),$f))),0,10);
}
function sti_read(string $l): array { $j=json_decode((string)@file_get_contents(sti_file($l)),true); return is_array($j) ? $j : []; }
/** Zusammengeführtes Wörterbuch: Deutsch ← Englisch ← Sprache; dazu die Rückübersetzung deutscher Servertexte. */
function sti_dict(string $l): array {
    $l=sti_norm($l) ?: 'de'; $de=sti_read('de'); $d=$de;
    if($l!=='de'){ foreach(sti_read('en') as $k=>$v) if(is_string($v) && $v!=='') $d[$k]=$v; if($l!=='en') foreach(sti_read($l) as $k=>$v) if(is_string($v) && $v!=='') $d[$k]=$v; }
    $out=[]; $src=[]; $srx=[];
    foreach($d as $k=>$v){ if(!is_string($v) || str_starts_with($k,'_')) continue;
        if(str_starts_with($k,'src.')){ $o=$de[$k] ?? ''; if($l!=='de' && $o!=='' && $o!==$v) $src[$o]=$v; continue; }
        if(str_starts_with($k,'srx.')){ $o=$de[$k] ?? ''; if($l!=='de' && $o!=='') $srx[]=[$o,$v]; continue; }
        $out[$k]=$v; }
    return ['l'=>$l,'tag'=>STI_LANGS[$l][1],'d'=>$out,'src'=>(object)$src,'srx'=>$srx];
}
/** Kopf des Studios: Wörterbuch (blockierend, damit Texte vor dem ersten Zeichnen stehen) + Laufzeit. */
function sti_head(string $l, string $source): string {
    $v=sti_ver($l); $rv=substr(md5((string)@filemtime(__DIR__.'/studio-i18n.js')),0,8);
    $cfg=['lang'=>$l,'source'=>$source,'langs'=>array_map(fn($k,$x)=>[$k,$x[0],$x[1]],array_keys(STI_LANGS),STI_LANGS)];
    return '<script>window.CH_I18N_CFG='.json_encode($cfg,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG).';</script>'
      .'<script src="/chat/studio-i18n.php?l='.$l.'&amp;v='.$v.'"></script><script src="/chat/studio-i18n.js?v='.$rv.'"></script>';
}

/* ---- Endpunkt: Wörterbuch als JavaScript ---- */
if(PHP_SAPI!=='cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? ''))===realpath(__FILE__)){
    $l=sti_norm($_GET['l'] ?? '') ?: 'de';
    header('Content-Type: application/javascript; charset=utf-8'); header('X-Robots-Tag: noindex');
    if(($_GET['v'] ?? '')===sti_ver($l)) header('Cache-Control: public, max-age=31536000, immutable');
    else header('Cache-Control: no-cache');
    echo 'window.CH_I18N=',json_encode(sti_dict($l),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG),';';
}
