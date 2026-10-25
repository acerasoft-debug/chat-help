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
$cases=[]; foreach($DEMOS as $d){ $cases[]=[$d,'tr',$MSG['tr']]; } foreach(['agent-phone','agent-bestellung','agent-termine','agent-hair-tr'] as $d) $cases[]=[$d,'de',$MSG['de']]; foreach($TOOLQ as $d=>$q) $cases[]=[$d,'tr',$q['tr']];
foreach($cases as [$d,$l,$m]){
    $n++; [$code,$j,$sec,$err,$raw]=$post(['agent'=>$d,'channel'=>$d==='agent-phone' ? 'phone' : 'chat','messages'=>[['role'=>'user','content'=>$m]],'lang'=>$l]);
    $ok=$code===200 && is_array($j) && empty($j['error']) && trim((string)($j['reply'] ?? ''))!=='';
    if(!$ok) $bad++;
    echo sprintf("%s %-18s %s HTTP %d %4.1fs src=%s err=%s tools=%s | %s\n",$ok?'OK ':'XX ',$d,$l,$code,$sec,(string)($j['source'] ?? '-'),(string)($j['error'] ?? ($err ?: '-')),json_encode(array_column((array)($j['tools'] ?? []),'name')),mb_substr(preg_replace('/\s+/',' ',(string)($j['reply'] ?? $raw)),0,110));
    usleep(300000);
}
echo "\nERGEBNIS: ".($n-$bad)." ok, $bad Fehler von $n\n";
