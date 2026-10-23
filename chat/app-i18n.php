<?php
/**
 * ChatHelp Business-App — Oberflächensprache (13 Sprachen, Deutsch = Quelle) für chat/app.php und chat/app-download.php
 * ====================================================================================================================
 * Gleiches System wie das Studio (studio-i18n.php / studio-i18n.js), aber eigene Wörterbücher: chat/app-i18n/<lang>.json
 * (die Studio-Dateien bleiben unberührt). Fehlt ein Schlüssel: Englisch, dann Deutsch.
 *   „src.*“  = deutsche Servertexte (exakt) → Übersetzung     „srx.*“ = Muster mit {platzhalter}     „err.*“ = API-Fehlercodes
 *   apl_lang()            → [lang, quelle]   ?lang= (setzt Cookie ch_lang) → Cookie ch_lang → Accept-Language → 'de'  (= sti_lang)
 *   apl_head($l,$src)     → <script>-Block für den <head>: Konfiguration + Wörterbuch + Laufzeit (studio-i18n.js) INLINE –
 *                           die App-Hülle wird vom Service Worker offline gespeichert, deshalb keine zusätzlichen Anfragen.
 *   apl_t($l)             → PHP-Übersetzer fn(key, vars=[]) für serverseitig gerenderte Seiten (app-download.php)
 *   apl_files($l)         → Dateien, deren Stand die Seitenversion bestimmt (ch_page_version)
 */
require_once __DIR__.'/studio-i18n.php';   // STI_LANGS, sti_norm(), sti_lang() – nur Funktionen, kein Endpunkt beim Einbinden
const APL_DIR = __DIR__.'/app-i18n';

function apl_lang(): array { return sti_lang(); }
function apl_file(string $l): string { return APL_DIR.'/'.$l.'.json'; }
function apl_read(string $l): array { static $c=[]; if(!isset($c[$l])){ $j=json_decode((string)@file_get_contents(apl_file($l)),true); $c[$l]=is_array($j) ? $j : []; } return $c[$l]; }
function apl_files(string $l): array { return array_values(array_unique([apl_file('de'),apl_file('en'),apl_file(sti_norm($l) ?: 'de'),__DIR__.'/studio-i18n.js',__FILE__])); }
/** Zusammengeführtes Wörterbuch (Deutsch ← Englisch ← Sprache) im Format von window.CH_I18N {l,tag,d,src,srx}. */
function apl_dict(string $l): array {
    $l=sti_norm($l) ?: 'de'; $de=apl_read('de'); $d=$de;
    if($l!=='de'){ foreach(apl_read('en') as $k=>$v) if(is_string($v) && $v!=='') $d[$k]=$v; if($l!=='en') foreach(apl_read($l) as $k=>$v) if(is_string($v) && $v!=='') $d[$k]=$v; }
    $out=[]; $src=[]; $srx=[];
    foreach($d as $k=>$v){ if(!is_string($v) || str_starts_with($k,'_')) continue;
        if(str_starts_with($k,'src.')){ $o=$de[$k] ?? ''; if($l!=='de' && $o!=='' && $o!==$v) $src[$o]=$v; continue; }
        if(str_starts_with($k,'srx.')){ $o=$de[$k] ?? ''; if($l!=='de' && $o!=='') $srx[]=[$o,$v]; continue; }
        $out[$k]=$v; }
    return ['l'=>$l,'tag'=>STI_LANGS[$l][1],'d'=>$out,'src'=>(object)$src,'srx'=>$srx];
}
/** Inline-Kopf: window.CH_I18N_CFG + window.CH_I18N + Laufzeit. */
function apl_head(string $l, string $source): string {
    $l=sti_norm($l) ?: 'de';
    $cfg=['lang'=>$l,'source'=>$source,'langs'=>array_map(fn($k,$x)=>[$k,$x[0],$x[1]],array_keys(STI_LANGS),STI_LANGS)];
    $f=JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG;
    $rt=(string)@file_get_contents(__DIR__.'/studio-i18n.js');
    return '<script>window.CH_I18N_CFG='.json_encode($cfg,$f).';window.CH_I18N='.json_encode(apl_dict($l),$f).";</script>\n<script>".str_ireplace('</script','<\/script',$rt).'</script>';
}
/** Übersetzer für PHP-Seiten: Text in $l (Fallback Englisch → Deutsch → Schlüssel), {name}-Platzhalter. */
function apl_t(string $l): callable {
    $d=apl_dict($l)['d'];
    return function(string $k, array $v=[]) use($d): string { $s=$d[$k] ?? $k; foreach($v as $n=>$x) $s=str_replace('{'.$n.'}',(string)$x,$s); return $s; };
}
