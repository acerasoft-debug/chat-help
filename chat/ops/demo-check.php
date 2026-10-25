<?php
/**
 * ChatHelp ops — demo-check (temporär, nur CLI): alle Live-Demos der Startseite gegen den echten concierge-Endpunkt (Türkisch + Deutsch),
 * je Demo HTTP-Code, Fehlercode, Quelle und die ersten Zeichen der Antwort. Schreibt nichts außer den üblichen Demo-Protokollen.
 */
if(PHP_SAPI!=='cli'){ http_response_code(404); exit; }
@set_time_limit(600);
$DEMOS=['agent-phone','agent-bestellung','agent-taxi','agent-hotel','agent-termine','agent-beauty','agent-aesthetic','agent-hair-tr','agent-dental-tr','agent-bank','agent-kanzlei','agent-steuer','agent-inkasso','immo-agent','agent-support','agent-backoffice','agent-marketing','agent-amazon','agent-behoerde','privat-behoerde','privat-bank','privat-reise','privat-assistent','privat-anruf'];
$MSG=['tr'=>'Merhaba, neler yapabilirsin? Kısaca.','de'=>'Hallo, was kannst du? Kurz bitte.'];
$TOOLQ=['agent-termine'=>['tr'=>'Yarın saat 10 için randevu var mı?'],'agent-bestellung'=>['tr'=>'Bir margherita sipariş etmek istiyorum, adres Friesenstraße 10, 50670 Köln.'],'agent-taxi'=>['tr'=>'Şimdi Köln Hauptbahnhof\'tan havalimanına taksi istiyorum.'],'agent-hotel'=>['tr'=>'Gelecek cuma iki gece için çift kişilik oda var mı?']];
echo "ChatHelp — demo-check (".date('c').")\n";
$bad=0; $n=0;
$post=function(array $body){ $ch=curl_init('https://chat-help.com/chat/concierge.php'); $t=microtime(true);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>90,CURLOPT_POSTFIELDS=>json_encode($body,JSON_UNESCAPED_UNICODE),CURLOPT_HTTPHEADER=>['Content-Type: application/json']]);
    $res=(string)curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch); return [$code,json_decode($res,true),round(microtime(true)-$t,1),$err,mb_substr($res,0,160)]; };
/* Modus „tools“: nur die Demos mit echten Werkzeugen, als kurze Gespräche (mehrere Nachrichten), mit Pausen gegen die Anfragegrenze des Hosters */
$mode=(string)($_GET['mode'] ?? 'tools');
$CONV=['agent-termine'=>['Yarın saat 10 için randevu var mı?','Saç kesimi olsun. Adım Demo, telefon 0170 0000000.','Evet, lütfen kaydet.'],
       'agent-bestellung'=>['Bir margherita sipariş etmek istiyorum.','Teslimat olsun, adres Friesenstraße 10, 50670 Köln.','Adım Demo, telefon 0170 0000000, nakit ödeyeceğim. Evet, siparişi ver.'],
       'agent-taxi'=>['Şimdi Köln Hauptbahnhof\'tan Köln/Bonn havalimanına taksi istiyorum.','Adım Demo, telefon 0170 0000000. Evet, onaylıyorum.'],
       'agent-hotel'=>['Gelecek cuma iki gece için çift kişilik oda var mı?','İki yetişkin. Fiyat ne kadar, kahvaltı dahil mi?'],
       'agent-hair-tr'=>['Hallo, was kostet eine Haartransplantation ungefähr?']];
/* Ohne Umweg über das Internet (die Gateway-Anfragegrenze des Hosters greift sonst bei der eigenen Server-IP): concierge.php im Unterprozess */
$post=function(array $body) use($post){ if(($_GET['net'] ?? '')==='1') return $post($body);
    $tmp=tempnam(sys_get_temp_dir(),'cin'); file_put_contents($tmp,json_encode($body,JSON_UNESCAPED_UNICODE)); $t=microtime(true);
    $cmd='php -d display_errors=0 -r '.escapeshellarg('$_SERVER["REQUEST_METHOD"]="POST"; $_SERVER["REMOTE_ADDR"]="127.0.0.1"; $GLOBALS["__in"]=file_get_contents("'.$tmp.'"); stream_wrapper_unregister("php"); stream_wrapper_register("php", "CHIn"); class CHIn { public $context; private $p=0; function stream_open($u,$m,$o,&$op){ return str_ends_with($u,"input"); } function stream_read($n){ $d=substr($GLOBALS["__in"],$this->p,$n); $this->p+=strlen($d); return $d; } function stream_eof(){ return $this->p>=strlen($GLOBALS["__in"]); } function stream_stat(){ return []; } } include "'.__DIR__.'/concierge.php";').' 2>&1';
    $out=(string)shell_exec($cmd); @unlink($tmp); $i=strpos($out,'{"'); $j=$i!==false ? json_decode(substr($out,$i),true) : null;
    return [is_array($j) ? 200 : 0,$j,round(microtime(true)-$t,1),is_array($j) ? '' : 'no_json',mb_substr(trim($out),0,300)]; };
if($mode==='log'){   /* Server-Protokolle: letzte KI-Fehler (nur Fehlertexte der KI-Anbieter, keine Nutzerdaten) */
    $cands=array_filter(array_unique([ini_get('error_log'),__DIR__.'/error_log',dirname(__DIR__).'/error_log',dirname(__DIR__,2).'/logs/error.log',dirname(__DIR__,2).'/logs/error_log',(getenv('HOME') ?: '').'/logs/error.log',(getenv('HOME') ?: '').'/logs/php_error.log']),fn($f)=>is_string($f) && $f!=='' && @is_readable($f));
    foreach($cands as $f){ echo "Log: $f (".round(filesize($f)/1024)." KB)\n"; $fh=fopen($f,'r'); fseek($fh,max(0,filesize($f)-2000000)); $L=[]; while(($ln=fgets($fh))!==false){ if(str_contains($ln,'CH th_ai') || str_contains($ln,'concierge') || str_contains($ln,'Fatal')) $L[]=mb_substr(trim($ln),0,260); } fclose($fh);
        foreach(array_slice($L,-25) as $ln) echo "  $ln\n"; }
    if(!$cands) echo "Kein lesbares Fehlerprotokoll gefunden.\n"; }
$cases=[]; if($mode==='all'){ foreach($DEMOS as $d){ $cases[]=[$d,'tr',$MSG['tr']]; } }
$only=(string)($_GET['demo'] ?? ''); if($only!=='') $CONV=array_intersect_key($CONV,[$only=>1]);
$rep=max(1,min(5,(int)($_GET['rep'] ?? 1)));
if($mode==='tools') for($r=0;$r<$rep;$r++) foreach($CONV as $d=>$turns){ $hist=[]; foreach($turns as $i=>$m){ $hist[]=['role'=>'user','content'=>$m];
        $n++; [$code,$j,$sec,$err,$raw]=$post(['agent'=>$d,'channel'=>'chat','messages'=>$hist,'lang'=>$d==='agent-hair-tr' ? 'de' : 'tr']);
        $ok=$code===200 && is_array($j) && empty($j['error']) && trim((string)($j['reply'] ?? ''))!=='';
        if(!$ok) $bad++;
        echo sprintf("%s %-18s #%d HTTP %d %4.1fs src=%s err=%s tools=%s | %s\n",$ok?'OK ':'XX ',$d,$i+1,$code,$sec,(string)($j['source'] ?? '-'),(string)($j['error'] ?? ($err ?: '-')),json_encode(array_map(fn($t)=>($t['name'] ?? '?').(($t['ok'] ?? true) ? '' : '!'),(array)($j['tools'] ?? []))),mb_substr(preg_replace('/\s+/',' ',(string)($j['reply'] ?? $raw)),0,140));
        if(!$ok) echo "     detail: ".mb_substr(json_encode($j['detail'] ?? null,JSON_UNESCAPED_UNICODE),0,1500)."\n";
        if($ok) $hist[]=['role'=>'assistant','content'=>(string)$j['reply']]; else break;
        sleep(1); } }
foreach($cases as [$d,$l,$m]){
    $n++; [$code,$j,$sec,$err,$raw]=$post(['agent'=>$d,'channel'=>$d==='agent-phone' ? 'phone' : 'chat','messages'=>[['role'=>'user','content'=>$m]],'lang'=>$l]);
    $ok=$code===200 && is_array($j) && empty($j['error']) && trim((string)($j['reply'] ?? ''))!=='';
    if(!$ok) $bad++;
    echo sprintf("%s %-18s %s HTTP %d %4.1fs src=%s err=%s tools=%s | %s\n",$ok?'OK ':'XX ',$d,$l,$code,$sec,(string)($j['source'] ?? '-'),(string)($j['error'] ?? ($err ?: '-')),json_encode(array_column((array)($j['tools'] ?? []),'name')),mb_substr(preg_replace('/\s+/',' ',(string)($j['reply'] ?? $raw)),0,110));
    usleep(300000);
}
echo "\nERGEBNIS: ".($n-$bad)." ok, $bad Fehler von $n\n";
