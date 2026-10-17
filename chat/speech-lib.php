<?php
/**
 * ChatHelp — Sprachausgabe der Live-Demos (Startseite) + Server-Stimmen
 * =====================================================================
 * Reine Textfunktionen (keine DB):
 *   ch_speech_sentences($text,$lang,$du)  Agenten-Antwort → sprechbare Sätze (max. 12, je ca. 220 Zeichen):
 *        Markdown/Emojis/Links raus, Listen → Sätze, Preise, Uhrzeiten, Daten, Codes, Telefonnummern, Abkürzungen ausgeschrieben.
 *   ch_speech_payload($demo,$text)        → [['text'=>…,'t'=>Token],…]  (für concierge.php)
 * Token (HMAC, 2 h gültig): nur Sätze, die der Demo-Agent selbst erzeugt hat, dürfen über tts.php vertont werden.
 *   t = "<exp>.<base64url(hash_hmac('sha256', demo|exp|text, ch_tts_key(), true))>"
 * Server-Stimme — erste verfügbare (TTS_PROVIDER erzwingt eine, 'off' schaltet ab):
 *   openai      OPENAI_KEY | OPENAI_API_KEY          Modell TTS_OPENAI_MODEL (Standard gpt-4o-mini-tts, mit Sprechstil-Anweisung)
 *   elevenlabs  ELEVENLABS_KEY | ELEVENLABS_API_KEY  Stimmen ELEVENLABS_VOICE_F / ELEVENLABS_VOICE_M
 *   google      GOOGLE_TTS_KEY                       Chirp 3 HD
 *   azure       AZURE_SPEECH_KEY + AZURE_SPEECH_REGION
 * Datei-Cache: sys_get_temp_dir()/ch_tts/<sha256>.mp3 (Ordner 0700); Dateien älter als 14 Tage werden gelegentlich gelöscht.
 * Tests: TTS_TEST_BASE lenkt alle Anbieter-Aufrufe auf einen lokalen Mock – wird NUR beachtet, wenn es auf 127.0.0.1/localhost zeigt.
 */

const CH_TTS_TTL = 7200;                                   // Token-Laufzeit (2 h)
const CH_TTS_PROVIDERS = ['openai','elevenlabs','google','azure'];
const CH_TTS_FALLBACK_SECRET = 'chathelp-tts-fallback-v1';  // nur wenn gar kein Schlüssel in db.php steht
const CH_SPEECH_MAX = 12;
const CH_SPEECH_LEN = 220;
const CH_SPEECH_MONTHS = [1=>'Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
const CH_SPEECH_DAYS = ['Mo'=>'Montag','Di'=>'Dienstag','Mi'=>'Mittwoch','Do'=>'Donnerstag','Fr'=>'Freitag','Sa'=>'Samstag','So'=>'Sonntag'];
/* Großbuchstaben+Ziffern, die KEIN Code sind (werden nicht buchstabiert) */
const CH_SPEECH_KEEP = ['COVID-19','USB-C','MP3','MP4','H2O','CO2','B2B','B2C','4K','5G','ID3','ID4','ID7','A4','A3','A6','Q3','Q5','Q7','Q8','IP65','IP67','IP68'];
/* Nach einer Ordnungszahl („2. Obergeschoss“) wird nicht in Sätze getrennt */
const CH_SPEECH_ORD_NOUNS = 'Obergeschoss|Untergeschoss|Erdgeschoss|Dachgeschoss|Stock|Etage|Stufe|Mahnstufe|Mahnung|Rate|Raten|Klasse|Woche|Kalenderwoche|Quartal|Tag|Platz|Reihe|Mal|Halbjahr|Monat|Jahr|Termin|Versuch|Hälfte|Zimmer|Liga';
const CH_SPEECH_LINK = ['de'=>['Den Link finden Sie im Chat.','Den Link findest du im Chat.'],'en'=>["You'll find the link in the chat.","You'll find the link in the chat."],
  'tr'=>['Bağlantıyı sohbette bulabilirsiniz.','Bağlantıyı sohbette bulabilirsin.'],'fr'=>['Vous trouverez le lien dans le chat.','Tu trouveras le lien dans le chat.'],
  'es'=>['Encontrará el enlace en el chat.','Encontrarás el enlace en el chat.'],'ru'=>['Ссылку вы найдёте в чате.','Ссылку ты найдёшь в чате.']];
/* Abkürzungen: [Muster, Ersatz, kann am Satzende stehen] – Reihenfolge zählt (kWh vor kW, USt-IdNr vor USt) */
const CH_SPEECH_ABBR = [
  ['/\bz\.\s?B\./u','zum Beispiel',false], ['/\bZ\.\s?B\./u','Zum Beispiel',false], ['/\bd\.\s?h\./u','das heißt',false], ['/\bu\.\s?a\./u','unter anderem',false],
  ['/\bi\.\s?d\.\s?R\./u','in der Regel',false], ['/\bu\.\s?U\./u','unter Umständen',false], ['/\bo\.\s?ä\./u','oder Ähnliches',true], ['/\bi\.\s?V\.\s?m\./u','in Verbindung mit',false],
  ['/\bu\.\s?v\.\s?m\./u','und vieles mehr',true], ['/\bz\.\s?Zt\./u','zurzeit',false], ['/\bvgl\./u','vergleiche',false],
  ['/\bca\./u','circa',false], ['/\bCa\./u','Circa',false], ['/\binkl\./iu','inklusive',false], ['/\bzzgl\./iu','zuzüglich',false], ['/\bexkl\./iu','exklusive',false],
  ['/\bggf\./iu','gegebenenfalls',false], ['/\bbzw\./iu','beziehungsweise',false], ['/\bevtl\./iu','eventuell',false], ['/\bmind\./iu','mindestens',false],
  ['/\bmax\./iu','maximal',false], ['/\bbzgl\./iu','bezüglich',false], ['/\bgem\./u','gemäß',false], ['/\blt\./u','laut',false], ['/\bsog\./u','sogenannte',false], ['/\bzzt\./u','zurzeit',false],
  ['/\bNr\./u','Nummer',false], ['/\bTel\./u','Telefon',false], ['/\bStr\./u','Straße',true], ['/\bDr\./u','Doktor',false], ['/\bProf\./u','Professor',false],
  ['/\bHr\./u','Herr',false], ['/\bFa\./u','Firma',false], ['/\bAbs\./u','Absatz',false], ['/\bArt\./u','Artikel',false], ['/\bBj\./u','Baujahr',false], ['/\bHbf\b\.?/u','Hauptbahnhof',true],
  ['/\bUSt-IdNr\b\.?/u','Umsatzsteuer-Identifikationsnummer',true], ['/\bUSt-VA\b/u','Umsatzsteuer-Voranmeldung',false], ['/\bUSt\b\.?/u','Umsatzsteuer',true], ['/\bMwSt\b\.?/u','Mehrwertsteuer',true],
  ['/\bPLZ\b/u','Postleitzahl',false], ['/\busw\./u','und so weiter',true], ['/\betc\./u','et cetera',true], ['/\bMio\b\.?/u','Millionen',true], ['/\bMrd\b\.?/u','Milliarden',true], ['/\bTsd\b\.?/u','tausend',true],
  ['/\bOG\b/u','Obergeschoss',false], ['/\bEG\b/u','Erdgeschoss',false], ['/\bDG\b/u','Dachgeschoss',false], ['/\bUG\b/u','Untergeschoss',false], ['/\bTG\b/u','Tiefgarage',false], ['/\bWEG\b/u','W E G',false],
  ['/\bkWh\b/u','Kilowattstunden',false], ['/\bkWp\b/u','Kilowatt-Peak',false], ['/\bkW\b/u','Kilowatt',false],
  ['/(?<=\d)\s?km\/h\b/u',' Kilometer pro Stunde',false], ['/(?<=\d)\s?km\b/u',' Kilometer',false], ['/\s?m²/u',' Quadratmeter',false], ['/(?<=\d)\s?qm\b/u',' Quadratmeter',false],
  ['/(?<=\d)\s?kg\b/u',' Kilo',false], ['/\s?°C\b/u',' Grad',false], ['/(?<=\d)\s?°/u',' Grad',false], ['/§§\s?/u','Paragrafen ',false], ['/§\s?/u','Paragraf ',false],
];

/* ---------- kleine Helfer ---------- */
function ch_speech_R(string $re, string $rep, string $s): string { $r=preg_replace($re,$rep,$s); return is_string($r) ? $r : $s; }
function ch_speech_RC(string $re, callable $fn, string $s): string { $r=preg_replace_callback($re,$fn,$s); return is_string($r) ? $r : $s; }
/** preg_replace_callback mit Zugriff auf den Text NACH dem Treffer (für „Abkürzung am Satzende“). $fn($m, $after) */
function ch_speech_rx(string $s, string $re, callable $fn): string {
    $r=preg_replace_callback($re, function($m) use($s,$fn){
        $after=substr($s,$m[0][1]+strlen($m[0][0])); $mm=[];
        foreach($m as $k=>$v) $mm[$k]=(is_array($v) && $v[1]>=0) ? $v[0] : '';
        return $fn($mm,$after);
    }, $s, -1, $cnt, PREG_OFFSET_CAPTURE);
    return is_string($r) ? $r : $s;
}
function ch_speech_final(string $after): bool { return preg_match('/^(?:\s*$|\s+[\p{Lu}\x{E100}-\x{F8FF}])/u',$after)===1; }
/** Geschütztes Stück (wird von späteren Regeln nicht mehr verändert) → ein Platzhalterzeichen. */
function ch_speech_p(array &$P, string $s): string { $P[]=$s; return mb_chr(0xE100+count($P)-1,'UTF-8'); }
function ch_speech_restore(string $s, array $P): string {
    for($i=0; $i<3 && preg_match('/[\x{E100}-\x{F8FF}]/u',$s); $i++)
        $s=ch_speech_RC('/[\x{E100}-\x{F8FF}]/u', fn($m)=>$P[mb_ord($m[0],'UTF-8')-0xE100] ?? '', $s);
    return $s;
}
/** Zeichen einzeln: "TK4729" → "T K 4 7 2 9" */
function ch_speech_spell(string $s): string { $c=preg_replace('/[^\p{L}\p{N}]/u','',$s); return implode(' ', mb_str_split((string)$c,1,'UTF-8')); }
function ch_speech_pairs(string $digits): string { return implode(', ', str_split($digits,2)); }
function ch_speech_time(int $h, int $m): string { return $h.' Uhr'.($m>0 ? ' '.$m : ''); }

/* ====================================================================== */
/** Agenten-Antwort → sprechbare Sätze. $du: Kunde wird geduzt (für den Link-Hinweis). */
function ch_speech_sentences(string $text, string $lang='de', bool $du=false): array {
    $lang=strtolower(substr((string)preg_replace('/[^a-zA-Z]/','',$lang),0,2)) ?: 'de';
    if(!mb_check_encoding($text,'UTF-8')) $text=mb_scrub($text,'UTF-8');
    if(strlen($text)>20000) $text=substr($text,0,20000);
    $P=[];
    $s=ch_speech_blocks($text,$lang,$du,$P);
    $s=ch_speech_symbols($s);
    $s=($lang==='de') ? ch_speech_de($s,$P) : ch_speech_generic($s,$lang,$P);
    $s=ch_speech_punct($s);
    return ch_speech_split($s,$P);
}

/** Zeilen/Markdown/Links/E-Mail-Adressen → ein Fließtext mit Satzzeichen. */
function ch_speech_blocks(string $text, string $lang, bool $du, array &$P): string {
    $s=str_replace(["\r\n","\r"],"\n",$text);
    $s=str_replace(['[WEITERLEITEN]','[ENDE]'],' ',$s);
    if(preg_match('/<\/?[a-z][a-z0-9]*[^>]*>/i',$s)){ $s=ch_speech_R('/<br\s*\/?>|<\/(?:p|li|div|h\d|tr)>/i',"\n",$s); $s=strip_tags($s); }
    $s=html_entity_decode($s,ENT_QUOTES|ENT_HTML5,'UTF-8');
    $s=ch_speech_R('/[\x{200B}\x{200C}\x{2060}\x{FEFF}\x{00AD}]/u','',$s);
    $s=ch_speech_R('/[\x{00A0}\x{2007}\x{2009}\x{202F}\t]/u',' ',$s);
    $s=ch_speech_R('/```.*?(?:```|$)/su',"\n",$s);
    $s=ch_speech_R('/!\[[^\]]*\]\([^)]*\)/u','',$s);                          // Bilder
    $s=ch_speech_R('/\[([^\]\n]+)\]\(\s*[^)\s]+[^)]*\)/u',"$1\u{E000}",$s);     // [Text](URL) → Text + Link-Marke
    /* E-Mail-Adressen buchstabierbar: service@nordlicht-outdoor.de → "service at nordlicht Bindestrich outdoor Punkt D E" */
    $s=ch_speech_RC('/(?<![\w.+-])([A-Za-z0-9][A-Za-z0-9._%+-]*)@([A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?)*\.[A-Za-z]{2,})(?![\w-])/u',
        function($m) use(&$P,$lang){ return ch_speech_p($P, ch_speech_email($m[1],$m[2],$lang)); }, $s);
    /* URLs → Link-Marke (wird später zu „Den Link finden Sie im Chat.“) */
    $url=function($m){ $u=$m[0]; $tail=''; while($u!=='' && strpbrk(substr($u,-1),".,;:!?)]»\"'")!==false){ $tail=substr($u,-1).$tail; $u=substr($u,0,-1); } return "\u{E000}".$tail; };
    $s=ch_speech_RC('~\bhttps?://[^\s<>"\[\]]+~iu',$url,$s);
    $s=ch_speech_RC('~(?<![\w.@-])www\.[^\s<>"\[\]]+~iu',$url,$s);
    $s=ch_speech_RC('~(?<![\w.@/-])[a-z0-9][a-z0-9-]*(?:\.[a-z0-9-]+)*\.(?:de|com|at|ch|eu|net|org|io|info|shop|app|online)/[^\s<>"\[\]]*~iu',$url,$s);
    /* reine Domain ohne Pfad: sprechen ("chat-help Punkt com") */
    $s=ch_speech_RC('~(?<![\w.@/-])([a-z0-9][a-z0-9-]*(?:\.[a-z0-9-]+)*)\.(de|com|at|ch|eu|net|org|io|info|shop|app|online)\b(?!\.[a-z0-9]|[\w/-])~iu',
        function($m) use(&$P,$lang){ return ch_speech_p($P, ch_speech_domain($m[1],$m[2],$lang,false)); }, $s);

    $months=implode('|',CH_SPEECH_MONTHS); $out=[]; $linkDone=false;
    foreach(explode("\n",$s) as $ln){
        $ln=trim($ln); if($ln==='') continue;
        if(preg_match('/^[\s\-*_=~]{3,}$/u',$ln)) continue;                                   // Trennlinie
        if(preg_match('/^\|?\s*:?-{2,}:?\s*(?:\|\s*:?-{2,}:?\s*)*\|?$/u',$ln)) continue;       // Tabellen-Trenner
        if(substr_count($ln,'|')>=2){ $cells=array_values(array_filter(array_map('trim',explode('|',trim($ln,'| '))),fn($c)=>$c!=='')); $ln=implode(', ',$cells); }
        $ln=ch_speech_R('/^#{1,6}\s*/u','',$ln);
        $ln=ch_speech_R('/^(?:>\s*)+/u','',$ln);
        $ln=ch_speech_R('/^(?:[-*+•▪◦‣–—✓✔☐☑]\s+|\d{1,2}[.)]\s+(?!(?:'.$months.')\b)|[a-z]\)\s+)/u','',$ln);
        $ln=ch_speech_R('/^\[[ xX]\]\s*/u','',$ln);
        $ln=ch_speech_R('/`([^`]*)`/u','$1',$ln);
        $ln=ch_speech_R('/(\*\*|__)(.+?)\1/u','$2',$ln);
        $ln=ch_speech_R('/(?<![\p{L}\p{N}*_])[*_](?=\S)(.+?)(?<=\S)[*_](?![\p{L}\p{N}*_])/u','$1',$ln);
        $ln=ch_speech_R('/~~(.+?)~~/u','$1',$ln);
        $ln=str_replace(['**','__','~~'],'',$ln);
        if(str_contains($ln,"\u{E000}")){
            $ln=ch_speech_R('/\s*(?:\b(?:unter|auf|über|via|hier|bei|at|on|here|sur|ici)\b\s*)?[:→]?\s*\x{E000}/iu','',$ln);
            $ln=trim(rtrim($ln," :,;–-"));
            if(!$linkDone){ $ln=($ln!=='' ? ch_speech_term($ln).' ' : '').ch_speech_linkphrase($lang,$du); $linkDone=true; }
        }
        if($ln==='') continue;
        $out[]=$ln;
    }
    $txt='';
    foreach($out as $i=>$ln){ $txt.=($i ? ' ' : '').(preg_match('/[:,;]$/u',$ln) ? $ln : ch_speech_term($ln)); }
    return $txt;
}
/** Zeile ohne Satzzeichen am Ende → Punkt anhängen (Listenpunkte werden zu Sätzen). */
function ch_speech_term(string $ln): string { return preg_match('/[.!?…:;,]$/u',$ln) ? $ln : $ln.'.'; }
function ch_speech_linkphrase(string $lang, bool $du): string { $p=CH_SPEECH_LINK[$lang] ?? CH_SPEECH_LINK['de']; return $p[$du ? 1 : 0]; }
function ch_speech_tld(string $tld, string $lang): string {
    $t=strtolower($tld);
    return in_array($t,['de','at','ch','eu','io'],true) ? strtoupper(implode(' ',str_split($t))) : $t;
}
function ch_speech_domain(string $host, string $tld, string $lang, bool $dictate): string {
    $dot=$lang==='de' ? ' Punkt ' : ($lang==='en' ? ' dot ' : ' . ');
    $labels=explode('.',$host);
    if($dictate && $lang==='de') $labels=array_map(fn($l)=>str_replace('-',' Bindestrich ',$l),$labels);
    elseif($dictate && $lang==='en') $labels=array_map(fn($l)=>str_replace('-',' dash ',$l),$labels);
    return implode($dot,$labels).$dot.ch_speech_tld($tld,$lang);
}
function ch_speech_email(string $local, string $domain, string $lang): string {
    $dot=$lang==='de' ? ' Punkt ' : ($lang==='en' ? ' dot ' : ' . ');
    $l=str_replace(['.','-','_'],[$dot,$lang==='de'?' Bindestrich ':' dash ',$lang==='de'?' Unterstrich ':' underscore '],$local);
    $p=strrpos($domain,'.'); $host=substr($domain,0,$p); $tld=substr($domain,$p+1);
    return trim($l).' at '.ch_speech_domain($host,$tld,$lang,true);
}

/** Emojis, Pfeile, Aufzählungszeichen. */
function ch_speech_symbols(string $s): string {
    $s=ch_speech_R('/\s*[\x{2190}-\x{21FF}\x{27F0}-\x{27FF}\x{2900}-\x{297F}\x{2B05}-\x{2B07}\x{2794}-\x{27BF}]\s*/u',', ',$s);   // → ⇒ ➜
    $s=ch_speech_R('/(?<!\S)[:;]-?[)(DPp](?!\S)/u','',$s);                                                                      // :) ;-)
    $s=ch_speech_R('/\s*[•·▪◦‣|]\s*/u',', ',$s);
    $s=ch_speech_R('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2300}-\x{23FF}\x{2B00}-\x{2BFF}\x{25A0}-\x{25FF}\x{FE00}-\x{FE0F}\x{200D}\x{20E3}\x{E0020}-\x{E007F}\x{3030}\x{303D}\x{3297}\x{3299}\x{00A9}\x{00AE}\x{2122}]/u',' ',$s);
    return $s;
}

/* ---------- sprachneutrale Schutzregeln (IBAN, Telefon, Codes) ---------- */
function ch_speech_iban(string $s, array &$P): string {
    return ch_speech_RC('/\b[A-Z]{2}\d{2}(?:\s?[A-Z0-9]{4}){2,7}(?:\s?[A-Z0-9]{1,3})?\b/u', function($m) use(&$P){
        $an=preg_replace('/\s+/','',$m[0]);
        return ch_speech_p($P, implode(', ', array_map('ch_speech_spell', str_split($an,4))));
    }, $s);
}
/** Telefonnummern: Vorwahl einzeln, Rufnummer in Zweiergruppen – "0221 2000000" → "0 2 2 1, 20, 00, 00, 0" */
function ch_speech_phone(string $s, array &$P, string $lang='de'): string {
    $plus=$lang==='de' ? 'plus' : 'plus';
    $s=ch_speech_RC('/(?<![\p{L}\p{N}\x{E100}-\x{F8FF}.,:\/+-])(\+\d{1,3}(?:\s?\(0\))?|0\d{1,5})((?:[ \/-]\d{2,8}){1,4})(?![\p{N}])/u', function($m) use(&$P,$plus){
        $groups=preg_split('/[ \/-]+/',trim($m[2],' /-')) ?: [];
        $isPlus=$m[1][0]==='+'; $prefix=preg_replace('/\D/','',str_replace('(0)','',$m[1]));
        $keep=[]; $digits=strlen($prefix); $rest=$groups; $cut=null;
        foreach($groups as $i=>$g){
            if($digits+strlen($g)>15) { $cut=$i; break; }
            $keep[]=$g; $digits+=strlen($g);
            $subscriber=$isPlus ? count($keep)>=2 : true;
            if($subscriber && strlen($g)>=5){ $cut=$i+1; break; }
        }
        if($digits<7 || !$keep) return $m[0];
        $tailTxt='';
        if($cut!==null && $cut<count($groups)){
            /* nicht verbrauchte Gruppen unverändert zurückgeben (z. B. "0221 2000000 24 Stunden") */
            $rx='/^'.preg_quote($m[1],'/'); foreach($keep as $g) $rx.='[ \/-]+'.preg_quote($g,'/'); $rx.='/u';
            if(preg_match($rx,$m[0],$mm)) $tailTxt=substr($m[0],strlen($mm[0]));
        }
        if($isPlus){ $area=array_shift($keep); $head=$plus.' '.ch_speech_spell($prefix).', '.ch_speech_spell($area); }
        else $head=ch_speech_spell($prefix);
        $sub=implode('',$keep);
        return ch_speech_p($P, $head.($sub!=='' ? ', '.ch_speech_pairs($sub) : '')).$tailTxt;
    }, $s);
    /* ohne Trennzeichen: 02212000000 */
    $s=ch_speech_RC('/(?<![\p{L}\p{N}\x{E100}-\x{F8FF}.,:\/+-])0\d{6,14}(?![\p{N}])/u', function($m) use(&$P){
        $n=$m[0]; $a=preg_match('/^0(?:30|40|69|89)/',$n) ? 3 : 4;
        return ch_speech_p($P, ch_speech_spell(substr($n,0,$a)).', '.ch_speech_pairs(substr($n,$a)));
    }, $s);
    return $s;
}
/** Buchungs-/Bestell-/Aktencodes aus Großbuchstaben UND Ziffern (≥ 4 Zeichen) einzeln sprechen: "LX7Q3M" → "L X 7 Q 3 M" */
function ch_speech_codes(string $s, array &$P): string {
    return ch_speech_RC('/(?<![\p{L}\p{N}\x{E100}-\x{F8FF}])(?=[A-Z0-9-]*[A-Z])(?=[A-Z0-9-]*\d)[A-Z0-9]+(?:-[A-Z0-9]+)*(?![\p{L}\p{N}])/u', function($m) use(&$P){
        $raw=$m[0]; $an=str_replace('-','',$raw);
        if(strlen($an)<4 || in_array($raw,CH_SPEECH_KEEP,true)) return $raw;
        return ch_speech_p($P, ch_speech_spell($an));
    }, $s);
}

/* ---------- Deutsch ---------- */
function ch_speech_de(string $s, array &$P): string {
    $D=CH_SPEECH_DAYS; $wd='(Mo|Di|Mi|Do|Fr|Sa|So)'; $months=implode('|',CH_SPEECH_MONTHS);
    /* feste Wendungen */
    $s=strtr($s,['24/7'=>'rund um die Uhr','m/w/d'=>'männlich, weiblich, divers','HU/AU'=>'HU und AU','und/oder'=>'oder']);
    $s=ch_speech_R('/kWh\s?\/\s?\(?\s?m²\s?a\s?\)?/u','Kilowattstunden pro Quadratmeter und Jahr',$s);
    /* Zeiteinheiten mit Zahl (Einzahl/Mehrzahl) */
    $s=ch_speech_rx($s,'/(?<![\p{L}\p{N}])(\d+(?:,\d+)?|eine|einer|ein)\s?(Std|Min|min|Sek|h)\b(\.)?(?![\p{L}])/u', function($m,$a){
        $u=['Std'=>['Stunde','Stunden'],'h'=>['Stunde','Stunden'],'Min'=>['Minute','Minuten'],'min'=>['Minute','Minuten'],'Sek'=>['Sekunde','Sekunden']][$m[2]];
        $one=in_array($m[1],['1','ein','eine','einer'],true);
        return $m[1].' '.$u[$one?0:1].($m[3]==='.' && ch_speech_final($a) ? '.' : '');
    });
    $s=ch_speech_rx($s,'/\b(Std|Min|Sek)\b(\.)?(?![\p{L}])/u', fn($m,$a)=>['Std'=>'Stunden','Min'=>'Minuten','Sek'=>'Sekunden'][$m[1]].($m[2]==='.' && ch_speech_final($a) ? '.' : ''));
    /* Abkürzungen */
    foreach(CH_SPEECH_ABBR as [$re,$rep,$fin])
        $s=ch_speech_rx($s,$re, fn($m,$a)=>$rep.($fin && str_ends_with($m[0],'.') && ch_speech_final($a) ? '.' : ''));
    $s=ch_speech_R('/(?<=\p{Ll})str\.(?=\s+\d)/u','straße',$s);                         // Hauptstr. 12
    /* Wochentage: "Mo–Fr" → "Montag bis Freitag", "Di + Do" → "Dienstag und Donnerstag" */
    $s=ch_speech_rx($s,'/\b'.$wd.'\.?\s*(?:–|-|bis)\s*'.$wd.'\b(\.)?/u', fn($m,$a)=>$D[$m[1]].' bis '.$D[$m[2]].($m[3]==='.' && ch_speech_final($a) ? '.' : ''));
    $s=ch_speech_rx($s,'/\b'.$wd.'\.?\s*(?:\+|&|und|\/)\s*'.$wd.'\b(\.)?/u', fn($m,$a)=>$D[$m[1]].' und '.$D[$m[2]].($m[3]==='.' && ch_speech_final($a) ? '.' : ''));
    $s=ch_speech_R('/\bFr\.\s+(?=\p{Lu}\p{Ll}+)(?!(?:'.$months.'|Montag|Dienstag|Mittwoch|Donnerstag|Freitag|Samstag|Sonntag)\b)/u','Frau ',$s);
    $s=ch_speech_rx($s,'/(?<![\p{L}-])(Mo|Di|Mi|Do|Fr|Sa)\b(\.)?(?![\p{L}-])/u', fn($m,$a)=>$D[$m[1]].($m[2]==='.' && ch_speech_final($a) ? '.' : ''));
    $s=ch_speech_R('/\bSo\b\.?(?=\s*(?:\d|ab\b|bis\b|von\b|geschlossen|Ruhetag|und Feiertag|,\s*\d))/u','Sonntag',$s);
    /* IBAN, Telefonnummern */
    $s=ch_speech_iban($s,$P);
    $s=ch_speech_phone($s,$P,'de');
    /* Datum: 20.11. → "20. November", 02.07.2026 → "2. Juli 2026", 2026-11-20 → "20. November 2026" */
    $s=ch_speech_RC('/\b((?:19|20)\d{2})-(\d{2})-(\d{2})\b/u', function($m) use(&$P){
        $d=(int)$m[3]; $mo=(int)$m[2]; if($d<1||$d>31||$mo<1||$mo>12) return $m[0];
        return ch_speech_p($P, $d.'. '.CH_SPEECH_MONTHS[$mo].' '.$m[1]);
    }, $s);
    $s=ch_speech_RC('/(?<![\p{N}.,])(\d{1,2})\.(\d{1,2})\.((?:19|20)\d{2}|\d{2}(?![\p{N}.]))?(?![\p{N}])/u', function($m) use(&$P){
        $d=(int)$m[1]; $mo=(int)$m[2]; if($d<1||$d>31||$mo<1||$mo>12) return $m[0];
        $y=$m[3] ?? ''; if(strlen($y)===2) $y='20'.$y;
        return ch_speech_p($P, $d.'. '.CH_SPEECH_MONTHS[$mo].($y!=='' ? ' '.$y : ''));
    }, $s);
    /* Uhrzeiten: 8:00–18:00 Uhr → "8 bis 18 Uhr", 15:30 → "15 Uhr 30", 15.30 Uhr → "15 Uhr 30", 15:00 → "15 Uhr" */
    $range=function($m) use(&$P){
        $h1=(int)$m[1]; $m1=(int)$m[2]; $h2=(int)$m[3]; $m2=(int)$m[4];
        if($h1>24||$h2>24) return $m[0];
        return ch_speech_p($P, ($m1===0 && $m2===0) ? $h1.' bis '.$h2.' Uhr' : ch_speech_time($h1,$m1).' bis '.ch_speech_time($h2,$m2));
    };
    $s=ch_speech_RC('/(?<![\p{N}:.,])(\d{1,2}):([0-5]\d)\s*(?:–|—|-|bis)\s*(\d{1,2}):([0-5]\d)(?:\s?Uhr\b)?(?![\p{N}:])/u',$range,$s);
    $s=ch_speech_RC('/(?<![\p{N}:.,])(\d{1,2})\.([0-5]\d)\s*(?:–|—|-|bis)\s*(\d{1,2})\.([0-5]\d)\s?Uhr\b/u',$range,$s);
    $one=function($m) use(&$P){ return (int)$m[1]>24 ? $m[0] : ch_speech_p($P, ch_speech_time((int)$m[1],(int)$m[2])); };
    $s=ch_speech_RC('/(?<![\p{N}:.,])(\d{1,2}):([0-5]\d)(?:\s?Uhr\b)?(?![\p{N}:])/u',$one,$s);
    $s=ch_speech_RC('/(?<![\p{N}:.,])(\d{1,2})\.([0-5]\d)\s?Uhr\b/u',$one,$s);
    /* Nummern mit Bindestrich: "2026-0142" → "2026 Strich 0 1 4 2", Jahresspanne "2025-2026" → "2025 bis 2026" */
    $s=ch_speech_RC('/(?<![\p{L}\p{N}\x{E100}-\x{F8FF}.,-])(\d{3,4})-(\d{2,6})(?![\p{N}-])/u', function($m) use(&$P){
        $y1=preg_match('/^(?:19|20)\d\d$/',$m[1]); $y2=preg_match('/^(?:19|20)\d\d$/',$m[2]);
        if($y1 && $y2) return $m[1].' bis '.$m[2];
        if($y1 && strlen($m[2])===2 && (int)$m[2]>(int)substr($m[1],2)) return $m[1].' bis '.substr($m[1],0,2).$m[2];
        if($y1 || $m[2][0]==='0') return ch_speech_p($P, $m[1].' Strich '.ch_speech_spell($m[2]));
        return $m[0];
    }, $s);
    /* Codes (TK4729, LX7Q3M, CF-2026-0912) */
    $s=ch_speech_codes($s,$P);
    /* Postleitzahl vor Ortsnamen: ", 50667 Köln" → "5 0 6 6 7 Köln" */
    $s=ch_speech_RC('/(?<=,\s|\bin\s|\bPostleitzahl\s|^)(\d{5})(?=\s+\p{Lu}\p{Ll})(?!\s+(?:Euro|Kilometer|Meter|Stück|Tokens?|Kunden|Menschen|Mal|Personen|Einwohner|Anlagen|Mitglieder|Quadratmeter|Kilowattstunden|Minuten|Stunden|Tage|Jahre|Franken)\b)/u',
        function($m) use(&$P){ return ch_speech_p($P, ch_speech_spell($m[1])); }, $s);
    /* Geld: "12,90 €" → "12 Euro 90", "1.840 €" → "1840 Euro", "0,50 €" → "50 Cent" */
    $money=function(string $int, string $cents, string $cur): string {
        $int=str_replace('.','',$int); $c=(in_array($cents,['','-','--','–'],true)) ? '' : (strlen($cents)===1 ? $cents.'0' : $cents);
        if($c==='00') $c=''; $c=ltrim($c,'0');
        [$big,$small]=$cur==='CHF' ? ['Franken','Rappen'] : ['Euro','Cent'];
        if($int==='0' && $c!=='') return $c.' '.$small;
        return $int.' '.$big.($c!=='' ? ' '.$c : '');
    };
    $s=ch_speech_RC('/(€|EUR|CHF)\s?(\d{1,3}(?:\.\d{3})+|\d+)(?:,(\d{1,2}|-{1,2}|–))?(?![\p{N}])/u', fn($m)=>$money($m[2],$m[3]??'',$m[1]==='CHF'?'CHF':'EUR'), $s);
    $s=ch_speech_RC('/(?<![\p{N}.,])(\d{1,3}(?:\.\d{3})+|\d+)(?:,(\d{1,2}|-{1,2}|–))?\s?(€|EUR\b|Euro\b|CHF\b|Franken\b)/u', fn($m)=>$money($m[1],$m[2]??'',in_array($m[3],['CHF','Franken'],true)?'CHF':'EUR'), $s);
    $s=ch_speech_R('/\s?€/u',' Euro',$s); $s=ch_speech_R('/\bEUR\b/u','Euro',$s);
    /* Tausenderpunkte: 650.000 → 650000 */
    $s=ch_speech_RC('/(?<![\p{N}.,])\d{1,3}(?:\.\d{3})+(?![\p{N}]|\.\d)/u', fn($m)=>str_replace('.','',$m[0]), $s);
    /* Prozent, Minus, mal, Nummer, circa */
    $s=ch_speech_R('/(\d)\s?%/u','$1 Prozent',$s); $s=ch_speech_R('/\s?%/u',' Prozent',$s);
    $s=ch_speech_R('/[−]\s?(?=\d)/u','minus ',$s);
    $s=ch_speech_R('/(?<![\p{L}\p{N}\x{E100}-\x{F8FF}])-(?=\d)/u','minus ',$s);
    $s=ch_speech_R('/±\s?/u','plus minus ',$s);
    $s=ch_speech_R('/(?<![\p{L}\p{N}])(\d+)\s?[x×](?=\s|$)/u','$1 mal',$s);
    $s=ch_speech_R('/#\s?(?=\d)/u','Nummer ',$s);
    $s=ch_speech_R('/(?<!\S)~\s?(?=\d)/u','circa ',$s);
    $s=ch_speech_R('/[Ø⌀]\s?(?=\d)/u','durchschnittlich ',$s);
    /* Spannen: "2–4 Werktage" → "2 bis 4 Werktage" */
    $s=ch_speech_R('/(?<=\d)\s*[–—]\s*(?=\d)/u',' bis ',$s);
    $s=ch_speech_R('/(?<=\d)\s?-\s?(?=\d)/u',' bis ',$s);
    /* Zeichen */
    $s=ch_speech_R('/\s*&\s*/u',' und ',$s);
    $s=ch_speech_R('/\s+\+\s+/u',' und ',$s);
    $s=ch_speech_R('/\+\s?(?=\d)/u','plus ',$s);
    $s=ch_speech_R('/\s+=\s+/u',' gleich ',$s);
    $s=ch_speech_R('/\s*\/\s*(?=(?:Monat|Tag|Nacht|Stunde|Person|Jahr|Woche|Stück|Kilometer|Minute|Saison|Termin|Fahrt|Rate|Liter|Gast|Zimmer|Quadratmeter|Kilowatt-Peak|Kilowattstunde|Kilowattstunden|Monate|Tage|Nächte|Personen|Stunden)\b)/u',' pro ',$s);
    return ch_speech_tail($s,' oder ');
}

/* ---------- andere Sprachen (Hotel-Gäste, …): nur sprachneutrale Regeln ---------- */
function ch_speech_generic(string $s, string $lang, array &$P): string {
    $W=['en'=>['euros','percent','and','or','around the clock'],'tr'=>['Euro','yüzde','ve','veya','7/24'],'fr'=>['euros','pour cent','et','ou','24 heures sur 24'],
        'es'=>['euros','por ciento','y','o','las 24 horas'],'ru'=>['евро','процентов','и','или','круглосуточно']][$lang] ?? ['Euro','%','&','/','24/7'];
    $s=str_replace('24/7',$W[4],$s);
    $s=ch_speech_iban($s,$P);
    $s=ch_speech_phone($s,$P,$lang);
    $s=ch_speech_codes($s,$P);
    $s=ch_speech_R('/(?:€|EUR)\s?(\d+(?:[.,]\d{1,2})?)/u','$1 '.$W[0],$s);
    $s=ch_speech_R('/(\d+(?:[.,]\d{1,2})?)\s?(?:€|EUR\b)/u','$1 '.$W[0],$s);
    $s=ch_speech_R('/\s?€/u',' '.$W[0],$s);
    if($lang==='tr') $s=ch_speech_R('/%\s?(\d+(?:[.,]\d+)?)/u',$W[1].' $1',$s);
    $s=ch_speech_R('/(\d)\s?%/u','$1 '.$W[1],$s);
    $s=ch_speech_R('/\s*&\s*/u',' '.$W[2].' ',$s);
    $s=ch_speech_R('/(?<=\d)\s*[–—]\s*(?=\d)/u',' – ',$s);
    return ch_speech_tail($s,' '.$W[3].' ');
}

/** Restliche Zeichen: Schrägstrich, Klammern, Anführungszeichen, Gedankenstriche, Reste. */
function ch_speech_tail(string $s, string $or): string {
    $s=ch_speech_R('/(?<=[\x{E100}-\x{F8FF}])\s*\/\s*(?=[\x{E100}-\x{F8FF}])/u',', ',$s);   // 15. Februar / 15. Mai
    $s=ch_speech_R('/(?<=\p{L})\s*\/\s*(?=\p{L})/u',$or,$s);                              // Kauf/Miete → Kauf oder Miete
    $s=str_replace('/',' ',$s);
    $s=ch_speech_R('/\s*[(\[{]\s*/u',', ',$s);
    $s=ch_speech_R('/\s*[)\]}]\s*/u',', ',$s);
    $s=ch_speech_R('/[„“”"«»‚‘›‹]/u','',$s);
    $s=str_replace('’',"'",$s);
    $s=ch_speech_R('/\s+[–—-]+\s+/u',', ',$s);
    $s=ch_speech_R('/[–—]/u',', ',$s);
    $s=ch_speech_R('/(?<=\s)-+(?=\s)|^-+\s/u',' ',$s);
    $s=ch_speech_R('/@/u',' at ',$s);
    $s=ch_speech_R('/[*_^~\\\\<>]+/u',' ',$s);
    return $s;
}

/** Satzzeichen aufräumen. */
function ch_speech_punct(string $s): string {
    $s=ch_speech_R('/\.{3,}/u','…',$s);
    $s=ch_speech_R('/[!?]*\?[!?]*/u','?',$s);
    $s=ch_speech_R('/!{2,}/u','!',$s);
    $s=ch_speech_R('/\s+/u',' ',$s);
    $s=ch_speech_R('/\s+([,.!?;:…])/u','$1',$s);
    $s=ch_speech_R('/([,;:])(?:\s*[,;:])+/u','$1',$s);
    $s=ch_speech_R('/[,;:]+\s*([.!?…])/u','$1',$s);
    $s=ch_speech_R('/([.!?…])\s*[,;:]+/u','$1',$s);
    $s=ch_speech_R('/([.!?])(?:\s*\.)+/u','$1',$s);
    $s=ch_speech_R('/…\s*\./u','…',$s);
    $s=ch_speech_R('/([,;])(?=[\p{L}\x{E100}-\x{F8FF}])/u','$1 ',$s);
    $s=ch_speech_R('/^[\s,;:.!?…]+/u','',$s);
    return trim($s);
}

/** In Sätze teilen (Ordnungszahlen/Daten nicht trennen), lange Sätze an Kommas teilen, Mini-Sätze zusammenfassen, max. 12. */
function ch_speech_split(string $s, array $P): array {
    $s=trim($s); if($s==='') return [];
    $sent=[]; $start=0;
    if(preg_match_all('/[.!?…]+(?=\s|$)/u',$s,$mm,PREG_OFFSET_CAPTURE)){
        foreach($mm[0] as [$p,$off]){
            $end=$off+strlen($p); $before=substr($s,$start,$off-$start); $after=substr($s,$end);
            if($p==='.' && preg_match('/(?:^|\s)\d{1,4}$/',$before) && preg_match('/^\s+(?:'.CH_SPEECH_ORD_NOUNS.')\b/u',$after)) continue;
            if(trim($after)!=='' && !preg_match('/^\s+[\p{Lu}\p{N}\x{E100}-\x{F8FF}„"»«(]/u',$after)) continue;
            $sent[]=trim(substr($s,$start,$end-$start)); $start=$end;
        }
    }
    $rest=trim(substr($s,$start)); if($rest!=='') $sent[]=$rest;
    $L=fn(string $x): int => mb_strlen(ch_speech_restore($x,$P));
    $out=[];
    foreach($sent as $x) foreach(ch_speech_chunk($x,$L) as $c){
        $c=ch_speech_tidy(ch_speech_restore($c,$P));
        if($c!=='' && preg_match('/[\p{L}\p{N}]/u',$c)) $out[]=$c;
    }
    /* sehr kurze Sätze (außer dem ersten – der soll sofort klingen) mit dem Nachbarn zusammenfassen */
    $m=[];
    foreach($out as $x){
        $n=count($m);
        if($n>=2 && (mb_strlen($m[$n-1])<30 || mb_strlen($x)<20) && mb_strlen($m[$n-1].' '.$x)<=CH_SPEECH_LEN){ $m[$n-1].=' '.$x; continue; }
        $m[]=$x;
    }
    return array_slice($m,0,CH_SPEECH_MAX);
}
function ch_speech_chunk(string $x, callable $L, int $max=CH_SPEECH_LEN): array {
    if($L($x)<=$max) return [$x];
    $parts=preg_split('/(?<=[,;:])\s+/u',$x) ?: [$x]; $out=[]; $cur='';
    foreach($parts as $p){
        if($cur===''){ $cur=$p; continue; }
        if($L($cur.' '.$p)<=$max) $cur.=' '.$p; else { $out[]=$cur; $cur=$p; }
    }
    if($cur!=='') $out[]=$cur;
    $fin=[];
    foreach($out as $o){
        while($o!=='' && $L($o)>$max){
            $words=preg_split('/\s+/u',$o) ?: [$o]; $a='';
            while($words && $L(trim($a.' '.$words[0]))<=$max) $a=trim($a.' '.array_shift($words));
            if($a==='') $a=(string)array_shift($words);
            $fin[]=$a; $o=implode(' ',$words);
        }
        if($o!=='') $fin[]=$o;
    }
    return $fin;
}
function ch_speech_tidy(string $s): string {
    $s=ch_speech_R('/\s+/u',' ',$s);
    $s=ch_speech_R('/\s+([,.!?;:…])/u','$1',$s);
    $s=ch_speech_R('/^[\s,;:.\-–]+/u','',$s);
    return trim($s);
}

/** Grobe Spracherkennung der Agenten-Antwort (Gast schreibt Englisch → Antwort Englisch). */
function ch_speech_guess_lang(string $t): string {
    if(preg_match('/\p{Cyrillic}{3,}/u',$t)) return 'ru';
    $l=' '.mb_strtolower((string)preg_replace('/[^\p{L}\s]+/u',' ',$t)).' '; $l=(string)preg_replace('/\s+/u',' ',$l);
    $sc=function(array $w) use($l): int { $n=0; foreach($w as $x) $n+=substr_count($l,' '.$x.' '); return $n; };
    $de=$sc(['der','die','das','und','ist','sie','ich','nicht','mit','ein','eine','für','wir','gerne','gern','bitte','ihr','ihre','sind','auf','zu','den','dem','haben','kann','können','du','dich','dir','wie','noch','oder','uhr']);
    $sc2=['en'=>$sc(['the','and','is','you','your','we','are','for','with','to','of','can','please','will','would','this','that','have','our','it','be','room']),
          'tr'=>$sc(['ve','bir','bu','için','ile','mı','mi','değil','evet','lütfen','size','sizin','çok','var','yok','olarak','teşekkürler']) + (preg_match('/[ğışİ]/u',$t) ? 3 : 0),
          'fr'=>$sc(['le','la','les','et','est','vous','nous','pour','avec','une','des','du','pas','merci','votre','bonjour','chambre']),
          'es'=>$sc(['el','los','las','y','usted','para','con','una','por','gracias','su','hola','qué','está','habitación']) + (preg_match('/[¿¡ñ]/u',$t) ? 3 : 0)];
    $best='de'; $max=$de;
    foreach($sc2 as $k=>$v) if($v>$max+1){ $best=$k; $max=$v; }
    return $best;
}

/* ====================================================================== */
/* Token */
function ch_tts_const(string ...$names): string {
    foreach($names as $n) if(defined($n)){ $v=constant($n); if(is_string($v) && trim($v)!=='') return trim($v); }
    return '';
}
function ch_tts_key(): string {
    static $k=null; if($k!==null) return $k;
    $base=ch_tts_const('CH_SECRET_KEY','CLAUDE_KEY');
    if($base==='') $base=ch_tts_const('DEEPSEEK_KEY','OPENAI_KEY','OPENAI_API_KEY','ELEVENLABS_KEY','ELEVENLABS_API_KEY','GOOGLE_TTS_KEY','AZURE_SPEECH_KEY');
    if($base==='') $base=CH_TTS_FALLBACK_SECRET;
    return $k=hash('sha256','ch-tts|'.$base);
}
function ch_tts_b64u(string $b): string { return rtrim(strtr(base64_encode($b),'+/','-_'),'='); }
function ch_tts_sig(string $demo, int $exp, string $text): string { return ch_tts_b64u(hash_hmac('sha256',$demo.'|'.$exp.'|'.$text,ch_tts_key(),true)); }
function ch_tts_sign(string $demo, string $text): string { $exp=time()+CH_TTS_TTL; return $exp.'.'.ch_tts_sig($demo,$exp,$text); }
function ch_tts_verify(string $demo, string $text, string $t): bool {
    if(!preg_match('/^(\d{9,12})\.([A-Za-z0-9_-]{43})$/',$t,$m)) return false;
    $exp=(int)$m[1]; $now=time();
    if($exp<$now || $exp>$now+CH_TTS_TTL+300) return false;
    return hash_equals(ch_tts_sig($demo,$exp,$text),$m[2]);
}
/** Antwort → [['text'=>Satz,'t'=>Token],…] für die Startseite (Sprache der Antwort wird erkannt; du/Sie aus CH_DEMO). */
function ch_speech_payload(string $demo, string $text): array {
    $du=defined('CH_DEMO') && !empty(CH_DEMO[$demo]['du']);
    $o=[]; foreach(ch_speech_sentences($text,ch_speech_guess_lang($text),$du) as $s) $o[]=['text'=>$s,'t'=>ch_tts_sign($demo,$s)];
    return $o;
}

/* ====================================================================== */
/* Server-Stimme */
function ch_tts_creds(string $provider): ?array {
    switch($provider){
        case 'openai':     $k=ch_tts_const('OPENAI_KEY','OPENAI_API_KEY');         return $k!=='' ? ['key'=>$k] : null;
        case 'elevenlabs': $k=ch_tts_const('ELEVENLABS_KEY','ELEVENLABS_API_KEY'); return $k!=='' ? ['key'=>$k] : null;
        case 'google':     $k=ch_tts_const('GOOGLE_TTS_KEY');                      return $k!=='' ? ['key'=>$k] : null;
        case 'azure':      $k=ch_tts_const('AZURE_SPEECH_KEY'); $r=strtolower(ch_tts_const('AZURE_SPEECH_REGION'));
                           return ($k!=='' && preg_match('/^[a-z0-9-]{2,40}$/',$r)) ? ['key'=>$k,'region'=>$r] : null;
    }
    return null;
}
/** Erster verfügbarer Anbieter (oder TTS_PROVIDER); null = keine Server-Stimme → Browser-Stimme. */
function ch_tts_provider(): ?string {
    if(defined('TTS_PROVIDER')){
        $f=strtolower(trim((string)TTS_PROVIDER));
        if($f!=='' && $f!=='auto'){
            if(in_array($f,CH_TTS_PROVIDERS,true) && ch_tts_creds($f)) return $f;
            static $warned=false; if(!$warned && !in_array($f,['off','none','browser'],true)){ $warned=true; error_log('CH tts: TTS_PROVIDER='.$f.' ohne gültigen Schlüssel – Server-Stimme aus'); }
            return null;
        }
    }
    foreach(CH_TTS_PROVIDERS as $p) if(ch_tts_creds($p)) return $p;
    return null;
}
/** Basis-URL; TTS_TEST_BASE nur für lokale Mocks (127.0.0.1 / localhost). */
function ch_tts_base(string $default): string {
    if(defined('TTS_TEST_BASE') && is_string(TTS_TEST_BASE)){
        $u=parse_url(TTS_TEST_BASE); $h=strtolower(trim((string)($u['host'] ?? ''),'[]'));
        if(in_array($u['scheme'] ?? '',['http','https'],true) && in_array($h,['127.0.0.1','localhost','::1'],true)) return rtrim(TTS_TEST_BASE,'/');
    }
    return $default;
}
/** Stimme des Demos für einen Anbieter auflösen (Modell, Stimmname, Tempo). */
function ch_tts_resolve(string $provider, array $voice): array {
    $m=($voice['gender'] ?? 'f')==='m';
    $rate=max(0.85,min(1.15,(float)($voice['rate'] ?? 1.0)));
    switch($provider){
        case 'openai':
            $model=ch_tts_const('TTS_OPENAI_MODEL') ?: 'gpt-4o-mini-tts';
            $ok=['alloy','ash','ballad','coral','echo','fable','nova','onyx','sage','shimmer','verse'];
            $v=in_array($voice['openai'] ?? '',$ok,true) ? $voice['openai'] : ($m ? 'ash' : 'coral');
            $legacy=str_starts_with($model,'tts-1');
            if($legacy && in_array($v,['ballad','verse'],true)) $v=$m ? 'onyx' : 'nova';
            return ['model'=>$model,'voice'=>$v,'instructions'=>$legacy ? '' : trim((string)($voice['instructions'] ?? ''))];
        case 'elevenlabs':
            $id=(string)($voice['elevenlabs'] ?? '');
            if(!preg_match('/^[A-Za-z0-9]{10,40}$/',$id)) $id=$m ? (ch_tts_const('ELEVENLABS_VOICE_M') ?: 'pNInz6obpgDQGcFmaJgB') : (ch_tts_const('ELEVENLABS_VOICE_F') ?: 'EXAVITQu4vr4xnSDxMaL');
            if(!preg_match('/^[A-Za-z0-9]{10,40}$/',$id)) $id=$m ? 'pNInz6obpgDQGcFmaJgB' : 'EXAVITQu4vr4xnSDxMaL';
            return ['model'=>'eleven_multilingual_v2','voice'=>$id];
        case 'google':
            $n=(string)($voice['google'] ?? ''); if(!preg_match('/^de-DE-[A-Za-z0-9-]{2,40}$/',$n)) $n=$m ? 'de-DE-Chirp3-HD-Charon' : 'de-DE-Chirp3-HD-Aoede';
            return ['voice'=>$n,'rate'=>round($rate,2)];
        case 'azure':
            $n=(string)($voice['azure'] ?? ''); if(!preg_match('/^[a-z]{2}-[A-Z]{2}-[A-Za-z]{2,60}Neural$/',$n)) $n=$m ? 'de-DE-FlorianMultilingualNeural' : 'de-DE-SeraphinaMultilingualNeural';
            return ['voice'=>$n,'rate'=>round($rate,2)];
    }
    return [];
}
/** HTTP-Anfrage für den Anbieter bauen: ['url','headers','body','mode'=>'binary'|'google_b64']. */
function ch_tts_request(string $provider, string $text, array $voice, array $cred): ?array {
    $r=ch_tts_resolve($provider,$voice); $J=JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES;
    switch($provider){
        case 'openai':
            $body=['model'=>$r['model'],'voice'=>$r['voice'],'input'=>$text];
            if($r['instructions']!=='') $body['instructions']=$r['instructions'];
            $body['response_format']='mp3';
            return ['url'=>ch_tts_base('https://api.openai.com').'/v1/audio/speech','mode'=>'binary','body'=>json_encode($body,$J),
                    'headers'=>['Authorization: Bearer '.$cred['key'],'Content-Type: application/json','Accept: audio/mpeg']];
        case 'elevenlabs':
            $body=['text'=>$text,'model_id'=>$r['model'],'voice_settings'=>['stability'=>0.45,'similarity_boost'=>0.8,'style'=>0.25,'use_speaker_boost'=>true]];
            return ['url'=>ch_tts_base('https://api.elevenlabs.io').'/v1/text-to-speech/'.rawurlencode($r['voice']).'?output_format=mp3_44100_128','mode'=>'binary','body'=>json_encode($body,$J),
                    'headers'=>['xi-api-key: '.$cred['key'],'Content-Type: application/json','Accept: audio/mpeg']];
        case 'google':
            $body=['input'=>['text'=>$text],'voice'=>['languageCode'=>'de-DE','name'=>$r['voice']],'audioConfig'=>['audioEncoding'=>'MP3','speakingRate'=>$r['rate']]];
            return ['url'=>ch_tts_base('https://texttospeech.googleapis.com').'/v1/text:synthesize?key='.rawurlencode($cred['key']),'mode'=>'google_b64','body'=>json_encode($body,$J),
                    'headers'=>['Content-Type: application/json; charset=utf-8']];
        case 'azure':
            $pct=(int)round(($r['rate']-1)*100); $rate=($pct>=0 ? '+' : '').$pct.'%';
            $x=fn(string $v): string => htmlspecialchars($v,ENT_XML1|ENT_QUOTES,'UTF-8');
            $ssml="<speak version='1.0' xmlns='http://www.w3.org/2001/10/synthesis' xml:lang='de-DE'><voice name='".$x($r['voice'])."'><prosody rate='".$x($rate)."'>".$x($text)."</prosody></voice></speak>";
            return ['url'=>ch_tts_base('https://'.$cred['region'].'.tts.speech.microsoft.com').'/cognitiveservices/v1','mode'=>'binary','body'=>$ssml,
                    'headers'=>['Ocp-Apim-Subscription-Key: '.$cred['key'],'Content-Type: application/ssml+xml','X-Microsoft-OutputFormat: audio-24khz-48kbitrate-mono-mp3','User-Agent: ChatHelp-TTS']];
    }
    return null;
}
function ch_tts_cache_dir(): ?string {
    $d=rtrim(sys_get_temp_dir(),'/\\').'/ch_tts';
    if(!is_dir($d)) @mkdir($d,0700,true);
    return (is_dir($d) && is_writable($d)) ? $d : null;
}
/** Cache-Datei für Text + Stimme beim aktuellen Anbieter (null = kein Anbieter / kein Cache-Ordner). */
function ch_tts_cache_path(string $text, array $voice, ?string $provider=null): ?string {
    $provider=$provider ?? ch_tts_provider(); if(!$provider) return null;
    $d=ch_tts_cache_dir(); if(!$d) return null;
    $v=$voice; ksort($v); $v['_resolved']=ch_tts_resolve($provider,$voice);
    return $d.'/'.hash('sha256',$provider.'|'.json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'|'.$text).'.mp3';
}
function ch_tts_is_mp3(string $b): bool {
    if(strlen($b)<64) return false;
    if(substr($b,0,3)==='ID3') return true;
    return ord($b[0])===0xFF && (ord($b[1]) & 0xE0)===0xE0;
}
function ch_tts_prune(?string $dir=null, int $days=14): int {
    $dir=$dir ?? ch_tts_cache_dir(); if(!$dir) return 0;
    $n=0; $lim=time()-$days*86400;
    foreach(glob($dir.'/*.mp3') ?: [] as $f) if(@filemtime($f)<$lim && @unlink($f)) $n++;
    foreach(glob($dir.'/*.tmp*') ?: [] as $f) if(@filemtime($f)<time()-3600) @unlink($f);
    return $n;
}
/** Text → MP3-Bytes (Cache zuerst). null bei jedem Fehler (dann spricht der Browser). */
function ch_tts_synth(string $text, array $voice): ?string {
    $text=trim($text); if($text==='' || mb_strlen($text)>600) return null;
    $prov=ch_tts_provider(); if(!$prov) return null;
    $cred=ch_tts_creds($prov); if(!$cred) return null;
    $file=ch_tts_cache_path($text,$voice,$prov);
    if($file && is_file($file) && filesize($file)>0){
        $b=@file_get_contents($file);
        if(is_string($b) && ch_tts_is_mp3($b)){ if(@filemtime($file)<time()-86400) @touch($file); return $b; }
    }
    $req=ch_tts_request($prov,$text,$voice,$cred); if(!$req) return null;
    $ch=curl_init($req['url']);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$req['body'],CURLOPT_HTTPHEADER=>$req['headers'],
        CURLOPT_TIMEOUT=>12,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_NOPROXY=>'127.0.0.1,localhost,::1']);
    $raw=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    $raw=is_string($raw) ? $raw : '';
    $redact=fn(string $m): string => str_replace([$cred['key'],rawurlencode($cred['key'])],'***',$m);
    if($code!==200){
        $msg=$raw!=='' && !ch_tts_is_mp3($raw) ? (string)preg_replace('/\s+/',' ',mb_substr(mb_scrub($raw,'UTF-8'),0,200)) : $err;
        error_log('CH tts '.$prov.' http '.($code ?: 'curl').' '.$redact($msg)); return null;
    }
    $audio=$req['mode']==='google_b64' ? base64_decode((string)(json_decode($raw,true)['audioContent'] ?? ''),true) : $raw;
    if(!is_string($audio) || !ch_tts_is_mp3($audio)){ error_log('CH tts '.$prov.' keine MP3-Antwort ('.strlen($raw).' Bytes)'); return null; }
    if($file){
        $tmp=$file.'.tmp'.bin2hex(random_bytes(4));
        if(@file_put_contents($tmp,$audio)!==false){ @chmod($tmp,0600); if(!@rename($tmp,$file)) @unlink($tmp); }
    }
    if(random_int(1,200)===1) ch_tts_prune();
    return $audio;
}
