<?php
/**
 * ChatHelp — Stimmenkatalog (eine Quelle für Browser-Stimmen, Server-Stimmen und Telefon)
 * ========================================================================================
 * Je Sprache (13) und Geschlecht mindestens sechs Stimmen, beste zuerst. Jeder Eintrag:
 *   id        stabil, z. B. de-f-lea (Sprache-Geschlecht-Persona) · premium: de-f-az-katja, tr-m-oa-onyx, fr-f-gg-aoede, de-m-el-max
 *   label     Vorname der Persona (DE: Lea, Sophie, Mia, Hanna · Max, Jonas, Thomas, Paul = die bisherigen Voreinstellungen zuerst)
 *   desc      2–4 Klang-Stichworte als Schlüssel (warm, klar, ruhig …) – Oberfläche übersetzt sie (studio-i18n-voice: vc.d.<wort>)
 *   tier      real    = echter, eigener Sprecher (Piper-Modell, läuft im Browser, keine Schlüssel)
 *             variant = Klangfarben-Variante eines echten Sprechers (Abtastraten-Trick, ehrlich als „Variante“ gezeigt)
 *             premium = Cloud-Stimme (OpenAI/Azure/Google/ElevenLabs), nur wenn der Betreiber den Schlüssel hinterlegt hat
 *   source    {type:'piper', key, sid, shift, length} | {type:'cloud', provider, voice}
 *   speaker   Anzeigename des Piper-Sprechers („Kerstin“, „MLS 589“) – für die ehrliche Zeile „Open-Source-Stimme …“
 *   mb        Download-Größe des Modells (einmalig)  ·  preset = nächste Voreinstellung (f_warm … m_tief) für alte Pfade
 * Lizenzen: nur Modelle, deren Lizenz kommerzielle Nutzung erlaubt (VC_MODELS, geprüft über MODEL_CARD auf dem Runner); nie NC.
 * Telefon: vc_twilio(id, locale) → gültige Twilio-Stimme (nur Namen aus PH_VOICES/PH_CHIRP_POOL in phone-webhook.php: Chirp3-HD je Geschlecht).
 * Browser: vc_browser_json() → chat/voice/catalog.json (php chat/voice-catalog.php export); voice.js: CHVoice.pickId(id), CHVoice.list(lang, g).
 * Studio-REST (voice-api.php): GET /v1/voices?lang=xx · GET|POST /v1/voices/agent {plan, voice_id} · POST /v1/voices/sample {id}.
 */
if(is_file(__DIR__.'/speech-lib.php')) require_once __DIR__.'/speech-lib.php';   // Anbieter-Schlüssel (ch_tts_creds), Voreinstellungen, Server-Stimme

const VC_LANGS = ['de','fr','tr','az','es','it','en','ru','nl','sv','no','fi','ja'];
const VC_TIERS = ['real','variant','premium'];
const VC_PRESETS = ['f'=>['f_warm','f_klar','f_frisch','f_sanft'],'m'=>['m_freundlich','m_ruhig','m_souveraen','m_tief']];
/* Piper-Modelle, die der Katalog nutzt: Lizenz (nur kommerziell nutzbare), Größe in MB (Anzeige), Sprecherzahl. voice-fetch.php ?models=catalog lädt genau diese. */
const VC_MODELS = [
  'de_DE-kerstin-low'=>['lic'=>'CC0','mb'=>63,'spk'=>1], 'de_DE-ramona-low'=>['lic'=>'M-AILABS','mb'=>63,'spk'=>1], 'de_DE-eva_k-x_low'=>['lic'=>'M-AILABS','mb'=>21,'spk'=>1],
  'de_DE-karlsson-low'=>['lic'=>'M-AILABS','mb'=>63,'spk'=>1], 'de_DE-thorsten-medium'=>['lic'=>'CC0','mb'=>63,'spk'=>1], 'de_DE-mls-medium'=>['lic'=>'CC-BY','mb'=>77,'spk'=>236],
  'de_DE-thorsten_emotional-medium'=>['lic'=>'CC0','mb'=>77,'spk'=>8],
  'fr_FR-siwis-medium'=>['lic'=>'CC-BY','mb'=>63,'spk'=>1], 'fr_FR-upmc-medium'=>['lic'=>'CC-BY-SA','mb'=>77,'spk'=>2], 'fr_FR-mls-medium'=>['lic'=>'CC-BY','mb'=>77,'spk'=>125],
  'fr_FR-gilles-low'=>['lic'=>'CC0','mb'=>63,'spk'=>1], 'fr_FR-mls_1840-low'=>['lic'=>'CC-BY','mb'=>63,'spk'=>1],
  'es_ES-sharvard-medium'=>['lic'=>'CC-BY 3.0','mb'=>77,'spk'=>2], 'es_ES-davefx-medium'=>['lic'=>'CC0','mb'=>63,'spk'=>1], 'es_ES-carlfm-x_low'=>['lic'=>'Public Domain','mb'=>28,'spk'=>1],
  'es_MX-ald-medium'=>['lic'=>'Unlicense','mb'=>63,'spk'=>1], 'es_MX-claude-high'=>['lic'=>'Apache-2.0','mb'=>63,'spk'=>1], 'es_AR-daniela-high'=>['lic'=>'CC-BY-SA','mb'=>114,'spk'=>1],
  'tr_TR-fettah-medium'=>['lic'=>'CC0','mb'=>63,'spk'=>1], 'tr_TR-fahrettin-medium'=>['lic'=>'CC0','mb'=>63,'spk'=>1],
  'it_IT-serena-medium'=>['lic'=>'CC-BY','mb'=>63,'spk'=>1], 'it_IT-paola-medium'=>['lic'=>'M-AILABS','mb'=>63,'spk'=>1], 'it_IT-riccardo-x_low'=>['lic'=>'M-AILABS','mb'=>28,'spk'=>1],
  'en_US-kristin-medium'=>['lic'=>'Public Domain','mb'=>64,'spk'=>1], 'en_GB-alba-medium'=>['lic'=>'CC-BY','mb'=>63,'spk'=>1], 'en_GB-cori-medium'=>['lic'=>'CC-BY','mb'=>64,'spk'=>1],
  'en_US-kathleen-low'=>['lic'=>'CC0','mb'=>63,'spk'=>1], 'en_GB-southern_english_female-low'=>['lic'=>'CC-BY','mb'=>63,'spk'=>1], 'en_GB-vctk-medium'=>['lic'=>'CC-BY','mb'=>77,'spk'=>109],
  'en_US-bryce-medium'=>['lic'=>'Public Domain','mb'=>64,'spk'=>1], 'en_US-john-medium'=>['lic'=>'Public Domain','mb'=>64,'spk'=>1], 'en_US-joe-medium'=>['lic'=>'CC0','mb'=>63,'spk'=>1],
  'en_US-norman-medium'=>['lic'=>'Public Domain','mb'=>64,'spk'=>1], 'en_GB-northern_english_male-medium'=>['lic'=>'CC-BY','mb'=>63,'spk'=>1],
  'nl_BE-nathalie-medium'=>['lic'=>'CC0','mb'=>63,'spk'=>1], 'nl_BE-rdh-medium'=>['lic'=>'CC0','mb'=>63,'spk'=>1], 'nl_NL-mls-medium'=>['lic'=>'CC-BY','mb'=>77,'spk'=>52],
  'nl_NL-mls_5809-low'=>['lic'=>'CC-BY','mb'=>63,'spk'=>1], 'nl_NL-mls_7432-low'=>['lic'=>'CC-BY','mb'=>63,'spk'=>1], 'nl_NL-pim-medium'=>['lic'=>'CC0','mb'=>63,'spk'=>1], 'nl_NL-ronnie-medium'=>['lic'=>'CC0','mb'=>63,'spk'=>1],
  'ru_RU-denis-medium'=>['lic'=>'CC0','mb'=>63,'spk'=>1], 'ru_RU-dmitri-medium'=>['lic'=>'CC0','mb'=>63,'spk'=>1],
  'sv_SE-nst-medium'=>['lic'=>'CC0','mb'=>63,'spk'=>1], 'no_NO-talesyntese-medium'=>['lic'=>'CC0','mb'=>63,'spk'=>1], 'fi_FI-harri-medium'=>['lic'=>'CC0','mb'=>63,'spk'=>1],
];
const VC_LIC_OK = ['CC0','CC-BY','CC-BY 3.0','CC-BY-SA','Apache-2.0','MIT','Public Domain','Unlicense','M-AILABS'];
/* Telefon (Twilio): Google-Chirp3-HD-Namen je Geschlecht – identisch mit PH_CHIRP_POOL in phone-webhook.php (Test prüft das) – und die Locales, in denen Twilio sie führt. */
const VC_CHIRP = ['f'=>['Aoede','Kore','Leda','Zephyr'],'m'=>['Charon','Fenrir','Orus','Puck']];
const VC_CHIRP_LOCALES = ['de-DE','fr-FR','tr-TR','es-ES','it-IT','en-US','en-GB','nl-NL','ru-RU','pl-PL'];
/* Cloud-Stimmen: OpenAI (mehrsprachig, Geschlecht wie allgemein wahrgenommen), Azure Neural je Sprache, Google Chirp3-HD je Locale, ElevenLabs über die Voreinstellungen. */
const VC_OPENAI = ['f'=>[['coral',['warm','herzlich']],['nova',['frisch','lebendig']],['sage',['klar','ruhig']],['shimmer',['sanft','hell']],['alloy',['neutral','klar']]],
                   'm'=>[['ash',['freundlich','locker']],['ballad',['ruhig','warm']],['echo',['souveraen','klar']],['onyx',['tief','gelassen']],['verse',['lebendig','klar']],['fable',['erzaehlend','weich']]]];
const VC_AZURE = [
  'de'=>['f'=>[['de-DE-KatjaNeural',['klar','freundlich']],['de-DE-SeraphinaMultilingualNeural',['warm','natuerlich']],['de-DE-AmalaNeural',['frisch','hell']],['de-DE-LouisaNeural',['sanft','ruhig']]],
         'm'=>[['de-DE-ConradNeural',['freundlich','klar']],['de-DE-FlorianMultilingualNeural',['warm','natuerlich']],['de-DE-KillianNeural',['souveraen','deutlich']],['de-DE-ChristophNeural',['tief','ruhig']]]],
  'fr'=>['f'=>[['fr-FR-DeniseNeural',['klar','freundlich']],['fr-FR-VivienneMultilingualNeural',['warm','natuerlich']],['fr-FR-EloiseNeural',['jung','hell']]],
         'm'=>[['fr-FR-HenriNeural',['freundlich','klar']],['fr-FR-RemyMultilingualNeural',['warm','natuerlich']],['fr-FR-AlainNeural',['ruhig','reif']]]],
  'tr'=>['f'=>[['tr-TR-EmelNeural',['klar','freundlich']]],'m'=>[['tr-TR-AhmetNeural',['freundlich','klar']]]],
  'az'=>['f'=>[['az-AZ-BanuNeural',['klar','freundlich']]],'m'=>[['az-AZ-BabekNeural',['freundlich','klar']]]],
  'es'=>['f'=>[['es-ES-ElviraNeural',['klar','freundlich']],['es-ES-XimenaMultilingualNeural',['warm','natuerlich']],['es-ES-AbrilNeural',['frisch','hell']]],
         'm'=>[['es-ES-AlvaroNeural',['freundlich','klar']],['es-ES-ArnauNeural',['ruhig','warm']],['es-ES-DarioNeural',['souveraen','deutlich']]]],
  'it'=>['f'=>[['it-IT-ElsaNeural',['klar','freundlich']],['it-IT-IsabellaNeural',['warm','herzlich']],['it-IT-FiammaNeural',['frisch','hell']]],
         'm'=>[['it-IT-DiegoNeural',['freundlich','klar']],['it-IT-GiuseppeMultilingualNeural',['warm','natuerlich']],['it-IT-BenignoNeural',['ruhig','reif']]]],
  'en'=>['f'=>[['en-US-AriaNeural',['klar','lebendig']],['en-US-JennyNeural',['warm','freundlich']],['en-US-AvaMultilingualNeural',['natuerlich','warm']],['en-GB-SoniaNeural',['klar','britisch']]],
         'm'=>[['en-US-GuyNeural',['freundlich','klar']],['en-US-DavisNeural',['ruhig','warm']],['en-US-AndrewMultilingualNeural',['natuerlich','warm']],['en-GB-RyanNeural',['souveraen','britisch']]]],
  'ru'=>['f'=>[['ru-RU-SvetlanaNeural',['klar','freundlich']],['ru-RU-DariyaNeural',['warm','ruhig']]],'m'=>[['ru-RU-DmitryNeural',['freundlich','klar']]]],
  'nl'=>['f'=>[['nl-NL-FennaNeural',['klar','freundlich']],['nl-NL-ColetteNeural',['warm','ruhig']]],'m'=>[['nl-NL-MaartenNeural',['freundlich','klar']]]],
  'sv'=>['f'=>[['sv-SE-SofieNeural',['klar','freundlich']],['sv-SE-HilleviNeural',['warm','ruhig']]],'m'=>[['sv-SE-MattiasNeural',['freundlich','klar']]]],
  'no'=>['f'=>[['nb-NO-PernilleNeural',['klar','freundlich']],['nb-NO-IselinNeural',['warm','ruhig']]],'m'=>[['nb-NO-FinnNeural',['freundlich','klar']]]],
  'fi'=>['f'=>[['fi-FI-NooraNeural',['klar','freundlich']],['fi-FI-SelmaNeural',['warm','ruhig']]],'m'=>[['fi-FI-HarriNeural',['freundlich','klar']]]],
  'ja'=>['f'=>[['ja-JP-NanamiNeural',['klar','freundlich']],['ja-JP-AoiNeural',['frisch','hell']],['ja-JP-MayuNeural',['warm','ruhig']],['ja-JP-ShioriNeural',['sanft','weich']]],
         'm'=>[['ja-JP-KeitaNeural',['freundlich','klar']],['ja-JP-DaichiNeural',['ruhig','warm']],['ja-JP-NaokiNeural',['souveraen','deutlich']]]],
];
const VC_GOOGLE = ['f'=>[['Aoede',['warm','herzlich']],['Kore',['klar','professionell']],['Leda',['frisch','lebendig']],['Zephyr',['sanft','ruhig']]],
                   'm'=>[['Charon',['ruhig','warm']],['Fenrir',['freundlich','locker']],['Orus',['souveraen','klar']],['Puck',['tief','gelassen']]]];
const VC_ELEVEN_LANGS = ['de','fr','tr','es','it','en','ru','nl','sv','fi','ja','no','az'];   // Norwegisch/Aserbaidschanisch über Eleven v3/v4 (ch_tts_resolve wählt das Modell)
/* Vornamen je Sprache: erst die vier Persona-Namen der Voreinstellungen (DE: Lea, Sophie, Mia, Hanna / Max, Jonas, Thomas, Paul), dann weitere natürliche Namen. */
const VC_NAMES = [
  'de'=>['f'=>['Lea','Sophie','Mia','Hanna','Emma','Clara','Nora','Marie','Lena','Julia'],'m'=>['Max','Jonas','Thomas','Paul','Felix','Leon','David','Julian','Lukas','Tim']],
  'fr'=>['f'=>['Camille','Chloé','Léa','Manon','Inès','Louise','Jade','Emma','Zoé','Alice'],'m'=>['Louis','Hugo','Lucas','Gabriel','Arthur','Jules','Nathan','Raphaël','Théo','Adam']],
  'tr'=>['f'=>['Elif','Zeynep','Ayşe','Defne','Ece','Selin','Merve','Deniz'],'m'=>['Mehmet','Emre','Can','Burak','Kerem','Ali','Murat','Cem']],
  'az'=>['f'=>['Aysel','Leyla','Nigar','Günel','Sevinc','Aynur','Lalə','Nərmin'],'m'=>['Murad','Elçin','Rəşad','Orxan','Tural','Kamran','Elvin','Samir']],
  'es'=>['f'=>['Lucía','Sofía','Valentina','Martina','Paula','Carmen','Julia','Elena'],'m'=>['Mateo','Diego','Alejandro','Carlos','Pablo','Daniel','Javier','Adrián']],
  'it'=>['f'=>['Serena','Paola','Giulia','Chiara','Sara','Alice','Martina','Elena'],'m'=>['Riccardo','Marco','Luca','Andrea','Matteo','Lorenzo','Francesco','Davide']],
  'en'=>['f'=>['Kristin','Alba','Cori','Kathleen','Emily','Amelia','Isla','Chloe','Grace','Olivia'],'m'=>['Bryce','John','Joe','Norman','Oliver','Harry','Callum','Ethan','James','Liam']],
  'ru'=>['f'=>['Anna','Maria','Elena','Olga','Daria','Irina','Natalia','Ekaterina'],'m'=>['Denis','Dmitri','Ivan','Nikita','Sergei','Maxim','Andrei','Pavel']],
  'nl'=>['f'=>['Nathalie','Sanne','Lotte','Fleur','Eva','Anne','Julia','Emma'],'m'=>['Daan','Sem','Bram','Lars','Pim','Ronnie','Thijs','Luuk']],
  'sv'=>['f'=>['Elsa','Alice','Maja','Ebba','Wilma','Astrid','Ella','Freja'],'m'=>['Erik','Johan','Anders','Oskar','Karl','Lars','Nils','Axel']],
  'no'=>['f'=>['Nora','Emma','Ingrid','Sofie','Maja','Ida','Thea','Linnea'],'m'=>['Magnus','Henrik','Lars','Jonas','Olav','Erik','Even','Sander']],
  'fi'=>['f'=>['Aino','Emma','Sofia','Helmi','Aada','Venla','Ella','Siiri'],'m'=>['Juhani','Mikael','Onni','Eetu','Leo','Elias','Oliver','Väinö']],
  'ja'=>['f'=>['Nanami','Aoi','Mayu','Shiori','Yui','Hina','Sakura','Mio'],'m'=>['Keita','Daichi','Naoki','Haruto','Sota','Ren','Yuto','Riku']],
];

/* ---------- Bausteine ---------- */
function vc_slug(string $s): string { $t=@iconv('UTF-8','ASCII//TRANSLIT',str_replace(['ş','ğ','ı','ç','ö','ü','ə','ş','Ş','Ğ','İ','Ç','Ö','Ü','Ə','ä','ß','é','è','ï','ô','â'],['s','g','i','c','o','u','e','s','S','G','I','C','O','U','E','ae','ss','e','e','i','o','a'],$s)); $t=strtolower(preg_replace('/[^a-z0-9]+/i','',(string)$t)); return $t!=='' ? $t : substr(md5($s),0,6); }
/** Piper-Eintrag: echter Sprecher (shift 1) oder Klangfarben-Variante (shift ≠ 1: >1 heller, <1 voller; Tempo über length ausgeglichen). */
function vc_piper(string $lang, string $g, string $name, array $desc, string $key, $sid, string $speaker, float $length=1.0, float $shift=1.0): array {
    $m=VC_MODELS[$key] ?? ['mb'=>63];
    return ['id'=>$lang.'-'.$g.'-'.vc_slug($name),'label'=>$name,'desc'=>$desc,'tier'=>$shift==1.0 ? 'real' : 'variant','gender'=>$g,'lang'=>$lang,
        'source'=>['type'=>'piper','key'=>$key,'sid'=>$sid,'shift'=>$shift,'length'=>$length],'speaker'=>$speaker,'mb'=>(int)$m['mb']];
}
function vc_cloud(string $lang, string $g, string $name, array $desc, string $provider, string $voice, string $idTail): array {
    return ['id'=>$lang.'-'.$g.'-'.$idTail,'label'=>$name,'desc'=>$desc,'tier'=>'premium','gender'=>$g,'lang'=>$lang,'source'=>['type'=>'cloud','provider'=>$provider,'voice'=>$voice],'speaker'=>$voice,'mb'=>0];
}
/** Nächste Variante eines echten Sprechers: Name aus der Namensliste, Klangfarbe aus der Reihe (f heller zuerst, m voller zuerst). */
function vc_variants(string $lang, string $g, array $real, int $want, array $names, int $from): array {
    $shifts=$g==='f' ? [1.07,0.93,1.12,0.96,1.04,0.9] : [1.06,0.95,0.88,1.1,0.92,1.04];
    $descs=$g==='f' ? [['hell','frisch'],['weich','warm'],['jung','lebendig'],['ruhig','klar'],['leicht','freundlich'],['tief','ruhig']] : [['hell','freundlich'],['voll','ruhig'],['tief','gelassen'],['jung','lebendig'],['sonor','warm'],['leicht','klar']];
    $out=[]; $n=count($real); if(!$n) return $out;
    for($i=0; count($out)<$want && $i<count($shifts); $i++){
        $r=$real[$i % $n]; $s=$r['source']; $nm=$names[$from+count($out)] ?? ($r['label'].' '.(count($out)+2));
        $out[]=vc_piper($lang,$g,$nm,$descs[$i],$s['key'],$s['sid'],$r['speaker'],$s['length']*($shifts[$i]>1 ? 0.98 : 1.03),$shifts[$i]);
    }
    return $out;
}
function vc_cloud_list(string $lang, string $g, array $names, int $from): array {
    $B=['openai'=>[],'azure'=>[],'google'=>[],'elevenlabs'=>[]]; $tag=function_exists('ch_lang_tag') ? ch_lang_tag($lang) : 'de-DE'; $gl=$lang==='az' ? 'tr-TR' : $tag;
    foreach(VC_OPENAI[$g] as [$v,$d]) $B['openai'][]=vc_cloud($lang,$g,ucfirst($v),$d,'openai',$v,'oa-'.$v);
    foreach(VC_AZURE[$lang][$g] ?? [] as [$v,$d]){ $short=preg_replace('/^[a-z]{2}-[A-Z]{2}-|(Multilingual)?Neural$/','',$v); $B['azure'][]=vc_cloud($lang,$g,$short,$d,'azure',$v,'az-'.strtolower($short)); }
    foreach(VC_GOOGLE[$g] as [$v,$d]) $B['google'][]=vc_cloud($lang,$g,$v,$d,'google',$gl.'-Chirp3-HD-'.$v,'gg-'.strtolower($v));
    /* Muttersprachliche Profi-Sprecher aus der ElevenLabs Voice Library (voice-scout, Tabelle tts_native) – vor den englischen Standardstimmen */
    if(function_exists('ch_tts_native_all')) foreach(ch_tts_native_all()['elevenlabs'][$lang][$g] ?? [] as $i=>$n){
        $first=trim((string)preg_split('/\s+[-–—|,·(]\s*|\s*[,(]/u',$n['label'])[0]);
        $label=preg_match('/^\p{L}{2,20}$/u',$first) ? $first : ($names[$from+$i] ?? 'Voice '.($i+1));
        $B['elevenlabs'][]=vc_cloud($lang,$g,$label,['natuerlich','klar'],'elevenlabs',$n['voice'],'eln-'.strtolower(substr((string)preg_replace('/[^A-Za-z0-9]/','',$n['voice']),0,12)));
    }
    /* ElevenLabs-Standardstimmen, die ops/voice-scout (mode=premade) im Konto geprüft hat – für jede Sprache wählbar (Sprache wird beim Vorlesen festgehalten) */
    if(function_exists('ch_tts_native_all')){ $pre=function_exists('ch_tts_voices') ? array_column(ch_tts_voices(),'elevenlabs') : [];
        foreach(ch_tts_native_all()['elevenlabs']['*'][$g] ?? [] as $n){ if(in_array($n['voice'],$pre,true)) continue; $m=json_decode($n['meta'] ?? '',true) ?: [];
            $B['elevenlabs'][]=vc_cloud($lang,$g,$n['label'] ?: 'Voice',is_array($m['desc'] ?? null) ? $m['desc'] : ['natuerlich'],'elevenlabs',$n['voice'],'elp-'.strtolower(substr((string)preg_replace('/[^A-Za-z0-9]/','',$n['voice']),0,12))); } }
    if(in_array($lang,VC_ELEVEN_LANGS,true) && function_exists('ch_tts_voices')){
        $ED=['f_warm'=>['warm','herzlich'],'f_klar'=>['klar','professionell'],'f_frisch'=>['frisch','lebendig'],'f_sanft'=>['sanft','geduldig'],'m_ruhig'=>['ruhig','warm'],'m_freundlich'=>['freundlich','locker'],'m_souveraen'=>['souveraen','deutlich'],'m_tief'=>['tief','gelassen']];
        foreach(ch_tts_voices() as $pid=>$p){ if($p['gender']!==$g) continue; $persona=str_contains($p['label'],' · ') ? trim(explode(' · ',$p['label'],2)[0]) : ucfirst(substr($pid,2));
            $B['elevenlabs'][]=vc_cloud($lang,$g,$persona,$ED[$pid] ?? ['warm'],'elevenlabs',$p['elevenlabs'],'el-'.substr($pid,2)); }
    }
    /* Reihenfolge = beste muttersprachliche Aussprache der Sprache zuerst (ch_tts_rank) */
    $out=[]; foreach(function_exists('ch_tts_rank') ? ch_tts_rank($lang) : array_keys($B) as $p) $out=array_merge($out,$B[$p] ?? []);
    return $out;
}
/** Voreinstellung je Eintrag: Reihenfolge innerhalb des Geschlechts (0 → f_warm/m_freundlich, 1 → f_klar/m_ruhig, …), premium nach Klang. */
function vc_preset_for(array $e, int $i): string {
    $P=VC_PRESETS[$e['gender']];
    if($e['tier']==='premium'){ $d=$e['desc'];
        if(array_intersect($d,['warm','herzlich','freundlich','locker','natuerlich'])) return $P[0];
        if(array_intersect($d,['klar','souveraen','professionell','deutlich','neutral'])) return $e['gender']==='f' ? $P[1] : $P[2];
        if(array_intersect($d,['frisch','lebendig','hell','jung'])) return $e['gender']==='f' ? $P[2] : $P[0];
        return $e['gender']==='f' ? $P[3] : ($e['gender']==='m' && array_intersect($d,['tief','sonor','gelassen']) ? $P[3] : $P[1]);
    }
    return $P[$i % 4];
}

/* ---------- Katalog ---------- */
/** lang → ['f'=>[…],'m'=>[…]] – echte Sprecher, dann Varianten (nur wenn weniger als 6 echte), dann Cloud-Stimmen. */
function vc_catalog(): array {
    static $C=null; if($C!==null) return $C;
    $N=VC_NAMES; $P=function(string $l,string $g,int $i,array $d,string $key,$sid,string $sp,float $len=1.0) use($N){ return vc_piper($l,$g,$N[$l][$g][$i],$d,$key,$sid,$sp,$len); };
    $R=[];
    /* Deutsch: Kerstin, Ramona, Eva, MLS-Sprecherinnen · Thorsten, Karlsson, MLS-Sprecher (MLS = echte Menschen, Mehrsprecher-Modell, ein Download) */
    $R['de']['f']=[$P('de','f',0,['warm','herzlich'],'de_DE-kerstin-low',null,'Kerstin'),$P('de','f',1,['klar','professionell'],'de_DE-ramona-low',null,'Ramona'),
        $P('de','f',2,['frisch','lebendig'],'de_DE-eva_k-x_low',null,'Eva',0.94),$P('de','f',3,['sanft','geduldig'],'de_DE-mls-medium','589','MLS 589',1.04),
        $P('de','f',4,['warm','natuerlich'],'de_DE-mls-medium','3990','MLS 3990'),$P('de','f',5,['klar','ruhig'],'de_DE-mls-medium','4174','MLS 4174'),
        $P('de','f',6,['hell','freundlich'],'de_DE-mls-medium','1649','MLS 1649'),$P('de','f',7,['weich','ruhig'],'de_DE-mls-medium','10179','MLS 10179',1.02)];
    $R['de']['m']=[$P('de','m',0,['freundlich','locker'],'de_DE-thorsten-medium',null,'Thorsten',0.98),$P('de','m',1,['ruhig','warm'],'de_DE-karlsson-low',null,'Karlsson',1.06),
        $P('de','m',2,['souveraen','klar'],'de_DE-mls-medium','5675','MLS 5675'),$P('de','m',3,['tief','gelassen'],'de_DE-mls-medium','11480','MLS 11480',1.04),
        $P('de','m',4,['freundlich','natuerlich'],'de_DE-mls-medium','5055','MLS 5055'),$P('de','m',5,['klar','deutlich'],'de_DE-mls-medium','252','MLS 252'),
        $P('de','m',6,['warm','ruhig'],'de_DE-mls-medium','3494','MLS 3494',1.02),$P('de','m',7,['hell','lebendig'],'de_DE-mls-medium','143','MLS 143')];
    /* Französisch: Siwis, UPMC Jessica/Pierre, Gilles, MLS */
    $R['fr']['f']=[$P('fr','f',0,['warm','klar'],'fr_FR-siwis-medium',null,'Siwis'),$P('fr','f',1,['klar','professionell'],'fr_FR-upmc-medium','jessica','Jessica'),
        $P('fr','f',2,['frisch','lebendig'],'fr_FR-mls-medium','12899','MLS 12899',0.95),$P('fr','f',3,['sanft','ruhig'],'fr_FR-mls-medium','13634','MLS 13634',1.02),
        $P('fr','f',4,['warm','natuerlich'],'fr_FR-mls-medium','12709','MLS 12709'),$P('fr','f',5,['hell','freundlich'],'fr_FR-mls-medium','3060','MLS 3060'),
        $P('fr','f',6,['klar','ruhig'],'fr_FR-mls-medium','11772','MLS 11772'),$P('fr','f',7,['weich','warm'],'fr_FR-mls-medium','2506','MLS 2506')];
    $R['fr']['m']=[$P('fr','m',0,['freundlich','klar'],'fr_FR-upmc-medium','pierre','Pierre'),$P('fr','m',1,['ruhig','warm'],'fr_FR-mls-medium','5525','MLS 5525',1.04),
        $P('fr','m',2,['souveraen','deutlich'],'fr_FR-mls-medium','3182','MLS 3182'),$P('fr','m',3,['tief','gelassen'],'fr_FR-mls-medium','66','MLS 66'),
        $P('fr','m',4,['warm','natuerlich'],'fr_FR-gilles-low',null,'Gilles'),$P('fr','m',5,['klar','ruhig'],'fr_FR-mls_1840-low',null,'MLS 1840'),
        $P('fr','m',6,['freundlich','lebendig'],'fr_FR-mls-medium','5764','MLS 5764'),$P('fr','m',7,['sonor','ruhig'],'fr_FR-mls-medium','12501','MLS 12501')];
    /* Türkisch: nur zwei freie Stimmen (CC0) – Fettah, Fahrettin; Rest Klangfarben-Varianten. Aserbaidschanisch: dieselben Modelle mit eigener Aussprache (espeak az). */
    foreach(['tr','az'] as $l){
        $R[$l]['f']=[$P($l,'f',0,['warm','herzlich'],'tr_TR-fettah-medium',null,'Fettah')];
        $R[$l]['m']=[$P($l,'m',0,['freundlich','klar'],'tr_TR-fahrettin-medium',null,'Fahrettin')];
    }
    /* Spanisch: Sharvard F/M, Claude (MX), Daniela (AR), Davefx, Ald (MX), Carlfm */
    $R['es']['f']=[$P('es','f',0,['warm','klar'],'es_ES-sharvard-medium','F','Sharvard F'),$P('es','f',1,['klar','professionell'],'es_MX-claude-high',null,'Claude (MX)'),
        $P('es','f',2,['frisch','lebendig'],'es_AR-daniela-high',null,'Daniela (AR)',0.96)];
    $R['es']['m']=[$P('es','m',0,['freundlich','klar'],'es_ES-davefx-medium',null,'Davefx'),$P('es','m',1,['ruhig','warm'],'es_ES-sharvard-medium','M','Sharvard M',1.06),
        $P('es','m',2,['souveraen','deutlich'],'es_MX-ald-medium',null,'Ald (MX)'),$P('es','m',3,['tief','gelassen'],'es_ES-carlfm-x_low',null,'Carlfm',1.04)];
    /* Italienisch: Serena, Paola · Riccardo */
    $R['it']['f']=[$P('it','f',0,['warm','klar'],'it_IT-serena-medium',null,'Serena'),$P('it','f',1,['klar','ruhig'],'it_IT-paola-medium',null,'Paola')];
    $R['it']['m']=[$P('it','m',0,['freundlich','klar'],'it_IT-riccardo-x_low',null,'Riccardo')];
    /* Englisch: viele freie Einzelstimmen + VCTK-Sprecher (109 echte Menschen, ein Download) */
    $R['en']['f']=[$P('en','f',0,['warm','herzlich'],'en_US-kristin-medium',null,'Kristin'),$P('en','f',1,['klar','professionell'],'en_GB-alba-medium',null,'Alba'),
        $P('en','f',2,['frisch','lebendig'],'en_GB-cori-medium',null,'Cori',0.96),$P('en','f',3,['sanft','ruhig'],'en_US-kathleen-low',null,'Kathleen',1.04),
        $P('en','f',4,['weich','britisch'],'en_GB-southern_english_female-low',null,'Southern English',1.06),$P('en','f',5,['klar','britisch'],'en_GB-vctk-medium','p239','VCTK p239'),
        $P('en','f',6,['warm','natuerlich'],'en_GB-vctk-medium','p236','VCTK p236'),$P('en','f',7,['hell','freundlich'],'en_GB-vctk-medium','p250','VCTK p250')];
    $R['en']['m']=[$P('en','m',0,['freundlich','locker'],'en_US-bryce-medium',null,'Bryce'),$P('en','m',1,['ruhig','warm'],'en_US-john-medium',null,'John'),
        $P('en','m',2,['souveraen','klar'],'en_GB-northern_english_male-medium',null,'Northern English'),$P('en','m',3,['tief','gelassen'],'en_US-joe-medium',null,'Joe',1.04),
        $P('en','m',4,['klar','deutlich'],'en_US-norman-medium',null,'Norman'),$P('en','m',5,['warm','britisch'],'en_GB-vctk-medium','p259','VCTK p259'),
        $P('en','m',6,['hell','lebendig'],'en_GB-vctk-medium','p247','VCTK p247'),$P('en','m',7,['sonor','ruhig'],'en_GB-vctk-medium','p263','VCTK p263')];
    /* Russisch: Denis, Dmitri (CC0) – keine frei lizenzierte Frauenstimme (Irina: Lizenz unklar, Ruslan: NC) */
    $R['ru']['f']=[];
    $R['ru']['m']=[$P('ru','m',0,['freundlich','klar'],'ru_RU-denis-medium',null,'Denis'),$P('ru','m',1,['ruhig','warm'],'ru_RU-dmitri-medium',null,'Dmitri',1.02)];
    /* Niederländisch: Nathalie, MLS · Rdh, Pim, Ronnie, MLS */
    $R['nl']['f']=[$P('nl','f',0,['warm','herzlich'],'nl_BE-nathalie-medium',null,'Nathalie'),$P('nl','f',1,['klar','professionell'],'nl_NL-mls_5809-low',null,'MLS 5809'),
        $P('nl','f',2,['frisch','lebendig'],'nl_NL-mls_7432-low',null,'MLS 7432',0.96),$P('nl','f',3,['sanft','ruhig'],'nl_NL-mls-medium','1724','MLS 1724',1.04)];
    $R['nl']['m']=[$P('nl','m',0,['freundlich','klar'],'nl_BE-rdh-medium',null,'Rdh'),$P('nl','m',1,['ruhig','warm'],'nl_NL-mls-medium','2450','MLS 2450',1.04),
        $P('nl','m',2,['souveraen','deutlich'],'nl_NL-mls-medium','4429','MLS 4429'),$P('nl','m',3,['tief','gelassen'],'nl_NL-mls-medium','11290','MLS 11290',1.04),
        $P('nl','m',4,['warm','natuerlich'],'nl_NL-pim-medium',null,'Pim'),$P('nl','m',5,['klar','lebendig'],'nl_NL-ronnie-medium',null,'Ronnie')];
    /* Schwedisch, Norwegisch, Finnisch: je eine freie Männerstimme (CC0), keine freie Frauenstimme → Frauen nur Cloud (+ Browserstimme) */
    $R['sv']['f']=[]; $R['sv']['m']=[$P('sv','m',0,['freundlich','klar'],'sv_SE-nst-medium',null,'NST')];
    $R['no']['f']=[]; $R['no']['m']=[$P('no','m',0,['freundlich','klar'],'no_NO-talesyntese-medium',null,'Talesyntese')];
    $R['fi']['f']=[]; $R['fi']['m']=[$P('fi','m',0,['freundlich','klar'],'fi_FI-harri-medium',null,'Harri')];
    /* Japanisch: keine frei lizenzierte Piper-Stimme (hi_fi_captain ist NC) → nur Cloud, sonst Browserstimme */
    $R['ja']['f']=[]; $R['ja']['m']=[];

    $C=[];
    foreach(VC_LANGS as $l) foreach(['f','m'] as $g){
        $real=$R[$l][$g] ?? []; $list=$real;
        if($real && count($real)<6) $list=array_merge($list,vc_variants($l,$g,$real,6-count($real),$N[$l][$g],count($real)));
        $list=array_merge($list,vc_cloud_list($l,$g,$N[$l][$g],count($list)));
        foreach($list as $i=>&$e){ $e['preset']=vc_preset_for($e,$i); if($l==='az' && $e['source']['type']==='piper') $e['via']='az'; } unset($e);
        $C[$l][$g]=$list;
    }
    return $C;
}
function vc_entry(?string $id): ?array {
    static $idx=null;
    if($idx===null){ $idx=[]; foreach(vc_catalog() as $l=>$G) foreach($G as $g=>$list) foreach($list as $e) $idx[$e['id']]=$e; }
    return $id!==null && isset($idx[$id]) ? $idx[$id] : null;
}
/** Welche Cloud-Anbieter der Betreiber eingerichtet hat (Schlüssel in db.php) – Flag je Anbieter. */
function vc_providers(): array {
    $o=[]; foreach(['openai','elevenlabs','google','azure'] as $p) $o[$p]=function_exists('ch_tts_creds') ? ch_tts_creds($p)!==null : false;
    /* TTS_PROVIDER erzwingt einen Anbieter (oder 'off'): dann zählt nur dieser */
    if(function_exists('ch_tts_provider')){ $act=ch_tts_provider(); if(defined('TTS_PROVIDER') && TTS_PROVIDER!=='' && TTS_PROVIDER!=='auto') foreach($o as $p=>$v) $o[$p]=$v && $p===$act; }
    return $o;
}
/** Steht die Stimme in dieser Installation zur Verfügung? Piper immer (läuft im Browser), Cloud nur mit Schlüssel des Anbieters. */
function vc_available(array $e): bool { if($e['source']['type']!=='cloud') return true; return !empty(vc_providers()[$e['source']['provider']]); }
function vc_public(array $e): array {
    $s=$e['source'];
    return ['id'=>$e['id'],'label'=>$e['label'],'desc'=>$e['desc'],'tier'=>$e['tier'],'gender'=>$e['gender'],'lang'=>$e['lang'],'preset'=>$e['preset'],'mb'=>$e['mb'],'speaker'=>$e['speaker'],
        'source'=>$s['type']==='piper' ? ['type'=>'piper','key'=>$s['key'],'sid'=>$s['sid'],'shift'=>$s['shift'],'length'=>$s['length']] : ['type'=>'cloud','provider'=>$s['provider']],
        'available'=>vc_available($e)]+(isset($e['via']) ? ['via'=>$e['via']] : []);
}
/** Liste fürs Studio: lang → f/m, je Eintrag mit available; $onlyAvailable blendet Cloud-Stimmen ohne Schlüssel aus. */
function vc_list(string $lang, ?string $gender=null, bool $onlyAvailable=false): array {
    $l=vc_lang($lang); $C=vc_catalog()[$l]; $o=[];
    foreach(['f','m'] as $g){ if($gender!==null && $gender!==$g) continue; $o[$g]=[];
        foreach($C[$g] as $e){ $p=vc_public($e); if($onlyAvailable && !$p['available']) continue; $o[$g][]=$p; } }
    return $o;
}
/** Echte Stimme (Cloud, Server-Vertonung) – ElevenLabs zuerst, dann übrige Cloud-Anbieter; Piper/Varianten (im Browser) zuletzt. */
function vc_real_rank(array $e): int { $s=$e['source'] ?? []; return ($s['type'] ?? '')!=='cloud' ? 2 : (($s['provider'] ?? '')==='elevenlabs' ? 0 : 1); }
/** Verfügbare Stimmen einer Sprache + eines Geschlechts, echte zuerst; gibt es echte, nur diese (Piper bleibt Ersatz ohne Server-Stimme). */
function vc_real_list(string $lang, string $g): array {
    $L=array_values(array_filter(vc_list($lang,$g)[$g] ?? [],fn($v)=>$v['available']));
    usort($L,fn($a,$b)=>vc_real_rank($a)<=>vc_real_rank($b));
    $real=array_values(array_filter($L,fn($v)=>vc_real_rank($v)<2));
    return $real ?: $L;
}
function vc_lang(string $l): string { $l=function_exists('ch_lang_norm') ? ch_lang_norm($l) : strtolower(substr($l,0,2)); return in_array($l,VC_LANGS,true) ? $l : 'de'; }
/** Standardstimme: erste verfügbare Stimme der Voreinstellung (sonst erste verfügbare, sonst erste überhaupt). '' nur, wenn die Sprache leer wäre (nie). */
function vc_default(string $lang, string $gender, string $preset=''): string {
    $g=$gender==='m' ? 'm' : 'f'; $list=vc_catalog()[vc_lang($lang)][$g];
    foreach($list as $e) if($preset!=='' && $e['preset']===$preset && vc_available($e)) return $e['id'];
    foreach($list as $e) if(vc_available($e)) return $e['id'];
    return $list ? $list[0]['id'] : '';
}
/** Gültige Stimme für Sprache + Geschlecht: $id, wenn sie passt, sonst Standard (gleiche Voreinstellung wie bisher). */
function vc_fit(?string $id, string $lang, string $gender, string $preset=''): string {
    $e=vc_entry($id); $l=vc_lang($lang); $g=$gender==='m' ? 'm' : 'f';
    if($e && $e['lang']===$l && $e['gender']===$g) return $e['id'];
    /* passt nicht (Sprache/Geschlecht gewechselt): gewünschte Voreinstellung des Zielgeschlechts, sonst die der alten Stimme (nur wenn sie zum Zielgeschlecht gehört) */
    $pp=in_array($preset,VC_PRESETS[$g],true) ? $preset : ($e && in_array($e['preset'],VC_PRESETS[$g],true) ? $e['preset'] : '');
    return vc_default($l,$g,$pp);
}
/** Cloud-Stimmname der Katalogstimme für einen Anbieter (null = Piper-Stimme oder anderer Anbieter → Voreinstellung gilt). */
function vc_cloud_voice(array $e, string $provider): ?string { $s=$e['source']; return $s['type']==='cloud' && $s['provider']===$provider ? $s['voice'] : null; }
/** Server-Stimme (ch_tts_synth) an eine Katalogstimme anpassen: Voreinstellung + Anbieter-Stimmname der gewählten Cloud-Stimme. */
function vc_apply_tts(array $voice, ?string $id): array {
    $e=vc_entry($id); if(!$e) return $voice;
    $voice['preset']=$e['preset']; $voice['gender']=$e['gender']; $voice['catalog_id']=$e['id'];
    if($e['source']['type']==='cloud') $voice[$e['source']['provider']]=$e['source']['voice'];
    return $voice;
}
/**
 * Telefon (Twilio): Katalogstimme → Twilio-Stimmname für die Locale des Anrufs. Google-Chirp3-HD-Namen gelten in allen Sprachen der Tabelle;
 * Azure/OpenAI/ElevenLabs/Piper gibt es bei Twilio nicht → nächste Chirp3-HD-Stimme desselben Geschlechts (fest je Stimme). null = kein Chirp in dieser Locale
 * (sv/nb/fi/ja) → phone-webhook nimmt wie bisher die Polly-Stimme je Geschlecht.
 */
function vc_twilio(?string $id, string $locale): ?string {
    $e=vc_entry($id); if(!$e) return null;
    $loc=$locale==='az-AZ' ? 'tr-TR' : $locale; if(!in_array($loc,VC_CHIRP_LOCALES,true)) return null;
    $g=$e['gender']; $pool=VC_CHIRP[$g]; $s=$e['source'];
    if($s['type']==='cloud' && $s['provider']==='google' && preg_match('/-Chirp3-HD-([A-Za-z]+)$/',$s['voice'],$m) && in_array($m[1],$pool,true)) $n=$m[1];
    else $n=$pool[crc32($e['id']) % count($pool)];
    return 'Google.'.$loc.'-Chirp3-HD-'.$n;
}
/** Geschlecht + Sprache einer Katalogstimme als phone_voice-Wert („tr-TR“, „de-DE-m“) – hält agent_configs.phone_voice konsistent. */
function vc_phone_voice(array $e): string { $tag=function_exists('ch_lang_tag') ? ch_lang_tag($e['lang']) : 'de-DE'; return $tag.($e['gender']==='m' ? '-m' : ''); }

/* ---------- Browser-Export (chat/voice/catalog.json) ---------- */
/** Nur, was voice.js braucht: Piper-Einträge je Sprache/Geschlecht (Cloud-Stimmen spricht der Server). */
function vc_browser_json(): array {
    $o=['v'=>1,'langs'=>[]];
    foreach(vc_catalog() as $l=>$G) foreach($G as $g=>$list) foreach($list as $e){ if($e['source']['type']!=='piper') continue; $s=$e['source'];
        $o['langs'][$l][$g][]=['id'=>$e['id'],'label'=>$e['label'],'tier'=>$e['tier'],'key'=>$s['key'],'sid'=>$s['sid'],'shift'=>$s['shift'],'length'=>$s['length'],'speaker'=>$e['speaker'],'mb'=>$e['mb'],'preset'=>$e['preset']]+(isset($e['via']) ? ['via'=>$e['via']] : []); }
    return $o;
}
/** Alle Piper-Modelle des Katalogs (für ops/voice-fetch.php ?models=catalog). */
function vc_models(): array { $k=[]; foreach(vc_catalog() as $G) foreach($G as $list) foreach($list as $e) if($e['source']['type']==='piper') $k[$e['source']['key']]=true; return array_keys($k); }

/* ---------- Agenten (agent_configs.voice_id) ---------- */
function vc_agent_bootstrap(): void {
    static $done=false; if($done || !function_exists('th_db')) return; $done=true;
    try{ $p=th_db(); $cols=$p->query("SHOW COLUMNS FROM agent_configs")->fetchAll(PDO::FETCH_COLUMN); if(!in_array('voice_id',$cols,true)) $p->exec("ALTER TABLE agent_configs ADD COLUMN voice_id VARCHAR(40) DEFAULT ''"); }catch(Throwable $e){ error_log('CH voice: agent_configs.voice_id '.$e->getMessage()); }
}
/** Gespeicherte Stimme eines Agenten (leer = Vorrat je Konto wie bisher, PH_CHIRP_POOL). */
function vc_agent_get(int $acc, string $plan): array {
    vc_agent_bootstrap(); $cfg=function_exists('th_agent_cfg') ? th_agent_cfg($acc,$plan) : [];
    $id=(string)($cfg['voice_id'] ?? ''); $e=vc_entry($id);
    $lang=vc_lang((string)($cfg['lang'] ?? 'de')); $pv=(string)($cfg['phone_voice'] ?? 'de-DE'); $g=preg_match('/-(m|gm)$/',$pv) ? 'm' : 'f';
    if(preg_match('/^([a-z]{2})-[A-Z]{2}/',$pv,$m) && in_array($m[1]==='nb' ? 'no' : $m[1],VC_LANGS,true)) $lang=$m[1]==='nb' ? 'no' : $m[1];   // Telefonsprache hat Vorrang (so klingt der Agent am Telefon)
    return ['plan'=>$plan,'voice_id'=>$e ? $e['id'] : '','voice'=>$e ? vc_public($e) : null,'lang'=>$lang,'gender'=>$e ? $e['gender'] : $g,'phone_voice'=>$pv];
}
/** Stimme eines Agenten speichern ('' = zurück zum Vorrat). Hält phone_voice (Sprache + Geschlecht) passend. Rückgabe wie vc_agent_get oder ['error'=>…]. */
function vc_agent_set(int $acc, string $plan, string $id): array {
    vc_agent_bootstrap();
    if(!function_exists('th_agent_cfg_save') || !function_exists('ch_plan') || !ch_plan($plan)) return ['error'=>'plan_required','message'=>'Unbekannter Agent.'];
    $e=$id!=='' ? vc_entry($id) : null; if($id!=='' && !$e) return ['error'=>'voice_unknown','message'=>'Diese Stimme gibt es nicht.'];
    if($e && !vc_available($e)) return ['error'=>'voice_unavailable','message'=>'Diese Premium-Stimme braucht einen Sprachdienst-Schlüssel des Betreibers.'];
    $cur=th_agent_cfg($acc,$plan); $active=function_exists('th_plan_active') ? th_plan_active($acc,$plan) : false;
    if(empty($cur['id'])) th_agent_cfg_save($acc,$plan,[],$active);   // Zeile anlegen (ohne Kanäle zu schalten)
    $pv=$e ? vc_phone_voice($e) : (string)$cur['phone_voice'];
    th_db()->prepare("UPDATE agent_configs SET voice_id=?, phone_voice=?, updated_at=NOW() WHERE account_id=? AND plan=?")->execute([$e ? $e['id'] : '',$pv,$acc,$plan]);
    return vc_agent_get($acc,$plan);
}
/** Hörprobe-Text je Sprache (nur Server-gebaute Texte werden vertont). */
function vc_sample_text(string $lang, string $name): string {
    $T=['de'=>'Guten Tag, hier ist {n}. Schön, dass Sie anrufen – wie kann ich Ihnen helfen?','en'=>"Hello, this is {n}. Thanks for calling – how can I help you today?",
        'fr'=>'Bonjour, ici {n}. Merci de votre appel – que puis-je faire pour vous ?','tr'=>'Merhaba, ben {n}. Aradığınız için teşekkürler – size nasıl yardımcı olabilirim?',
        'az'=>'Salam, mən {n}. Zəng etdiyiniz üçün təşəkkür edirəm – sizə necə kömək edə bilərəm?','es'=>'Hola, soy {n}. Gracias por llamar, ¿en qué puedo ayudarle?',
        'it'=>'Buongiorno, sono {n}. Grazie per la chiamata – come posso aiutarla?','ru'=>'Здравствуйте, это {n}. Спасибо за звонок – чем могу помочь?',
        'nl'=>'Goedendag, u spreekt met {n}. Fijn dat u belt – waarmee kan ik u helpen?','sv'=>'Hej, det här är {n}. Tack för att du ringer – hur kan jag hjälpa dig?',
        'no'=>'Hei, dette er {n}. Takk for at du ringer – hva kan jeg hjelpe deg med?','fi'=>'Hei, täällä {n}. Kiitos soitostasi – miten voin auttaa?','ja'=>'こんにちは、{n}でございます。お電話ありがとうございます。ご用件をお伺いいたします。'];
    return str_replace('{n}',$name,$T[vc_lang($lang)] ?? $T['de']);
}

/* ---------- Studio-REST (von voice-api.php aufgerufen; Vertrag wie die Modul-APIs: [code, body] oder null) ---------- */
function vc_api(string $route, string $method, int $aid, array $body, array $account): ?array {
    if($route!=='voices' && !str_starts_with($route,'voices/')) return null;
    if($aid<=0) return [403,['error'=>'forbidden']];
    $q=$_GET ?? [];
    switch(true){
        case $route==='voices' && $method==='GET':
            $lang=vc_lang((string)($body['lang'] ?? $q['lang'] ?? 'de')); $g=in_array($q['gender'] ?? '',['f','m'],true) ? $q['gender'] : null;
            return [200,['lang'=>$lang,'voices'=>vc_list($lang,$g,!empty($q['all']) ? false : true),'providers'=>vc_providers(),'tiers'=>VC_TIERS,'default'=>['f'=>vc_default($lang,'f'),'m'=>vc_default($lang,'m')]]];
        case $route==='voices/agent' && $method==='GET':
            $plan=preg_replace('/[^a-z0-9-]/','',strtolower((string)($q['plan'] ?? ''))); if($plan==='' || !function_exists('ch_plan') || !ch_plan($plan)) return [400,['error'=>'plan_required']];
            $a=vc_agent_get($aid,$plan); return [200,$a+['voices'=>vc_list($a['lang'],null,true),'providers'=>vc_providers()]];
        case $route==='voices/agent' && $method==='POST':
            $plan=preg_replace('/[^a-z0-9-]/','',strtolower((string)($body['plan'] ?? $q['plan'] ?? ''))); if($plan==='') return [400,['error'=>'plan_required']];
            if(!function_exists('th_rate') || !th_rate('vc:agent:'.$aid,120,3600)) return [429,['error'=>'rate_limited']];
            $id=is_string($body['voice_id'] ?? null) ? preg_replace('/[^a-z0-9_-]/','',substr($body['voice_id'],0,40)) : '';
            $r=vc_agent_set($aid,$plan,$id); if(isset($r['error'])) return [$r['error']==='plan_required' ? 400 : 422,$r];
            return [200,['ok'=>true]+$r];
        case $route==='voices/sample' && $method==='POST':
            $e=vc_entry(is_string($body['id'] ?? null) ? $body['id'] : null); if(!$e) return [404,['error'=>'voice_unknown','message'=>'Diese Stimme gibt es nicht.']];
            if($e['source']['type']!=='cloud') return [400,['error'=>'browser_voice','message'=>'Diese Stimme läuft im Browser (Piper) – die Hörprobe erzeugt der Browser.']];
            if(!vc_available($e) || !function_exists('ch_tts_synth')) return [501,['error'=>'tts_unavailable','message'=>'Für diese Premium-Stimme fehlt der Sprachdienst-Schlüssel.']];
            if(function_exists('th_balance') && th_balance($aid)<=0 && !(function_exists('assistant_active_plans') && assistant_active_plans($aid))) return [402,['error'=>'insufficient_tokens','message'=>'Hörproben von Premium-Stimmen brauchen Guthaben oder einen aktiven Agenten.']];
            if(function_exists('th_rate') && !th_rate('vc:sample:'.$aid,120,86400)) return [429,['error'=>'rate_limited','message'=>'Tageslimit für Hörproben erreicht.']];
            $name=preg_replace('/[^\p{L}\p{M} \-]/u','',mb_substr((string)($body['name'] ?? $e['label']),0,30)) ?: $e['label'];
            $pre=function_exists('ch_tts_preset') ? (ch_tts_preset($e['preset']) ?? []) : [];
            $voice=vc_apply_tts(['gender'=>$e['gender'],'preset'=>$e['preset'],'lang'=>ch_lang_tag($e['lang']),'rate'=>(float)($pre['rate'] ?? 1.0),'pitch'=>(float)($pre['pitch'] ?? 1.0),
                'instructions'=>(string)($pre['instructions'] ?? ''),'openai'=>$pre['openai'] ?? '','google'=>$pre['google'] ?? '','azure'=>$pre['azure'] ?? '','elevenlabs'=>$pre['elevenlabs'] ?? '','eleven'=>$pre['eleven'] ?? null],$e['id']);
            $voice['lang_auto']=false; $voice['channel']='studio';
            $mp3=null; try{ $mp3=ch_tts_synth(vc_sample_text($e['lang'],$name),$voice); }catch(Throwable $x){ error_log('CH voice sample: '.$x->getMessage()); }
            if(!is_string($mp3) || $mp3==='') return [502,['error'=>'tts_failed','message'=>'Die Hörprobe konnte gerade nicht erzeugt werden.']];
            if(PHP_SAPI==='cli') return [200,['audio'=>true,'content_type'=>'audio/mpeg','bytes'=>strlen($mp3)]];
            if(function_exists('th_nocache')) th_nocache(); http_response_code(200); header('Content-Type: audio/mpeg'); header('Content-Length: '.strlen($mp3)); echo $mp3; exit;
    }
    return [404,['error'=>'not_found','routes'=>['GET /v1/voices?lang=xx','GET /v1/voices/agent?plan=…','POST /v1/voices/agent {plan, voice_id}','POST /v1/voices/sample {id}']]];
}

/* ---------- Kommandozeile: php chat/voice-catalog.php export | models | table ---------- */
if(PHP_SAPI==='cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? ''))===realpath(__FILE__)){
    require_once __DIR__.'/i18n.php';
    $cmd=$argv[1] ?? 'table';
    if($cmd==='export'){ $f=__DIR__.'/voice/catalog.json'; file_put_contents($f,json_encode(vc_browser_json(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); echo "geschrieben: $f (".filesize($f)." Bytes)\n"; exit; }
    if($cmd==='models'){ echo implode(',',vc_models()),"\n"; exit; }
    printf("%-5s %-3s %4s %4s %4s\n",'lang','g','real','var','prem');
    foreach(vc_catalog() as $l=>$G) foreach($G as $g=>$list){ $n=['real'=>0,'variant'=>0,'premium'=>0]; foreach($list as $e) $n[$e['tier']]++; printf("%-5s %-3s %4d %4d %4d\n",$l,$g,$n['real'],$n['variant'],$n['premium']); }
}
