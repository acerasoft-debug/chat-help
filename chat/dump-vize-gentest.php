<?php
/**
 * ChatHelp — dump-vize-gentest (UCTAN UCA TEST) — 5 vize belgesinin TAMAMINI
 *  sunucu uzerinden gercek uretimle dener (cover/invitation/employer/
 *  selfemployed/itinerary). Her biri icin: HTTP kodu, yanit var mi, uzunluk,
 *  yer tutucu kaldi mi, ilk satirlar. Hangi belge neden "evrak vermiyor"
 *  KESIN gorunur. (5 gercek AI cagrisi yapar; veri YAZMAZ.)
 * KULLANIM: pull2.php?key=...&files=dump-vize-gentest.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
@set_time_limit(300);
echo "=== dump-vize-gentest — 5 belge uctan uca ===\n\n";

$host = $_SERVER['HTTP_HOST'] ?? 'chat-help.com';
$dir  = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/chat/x.php'), '/');
$base = 'https://'.$host.$dir.'/';

function post($url,$payload){
  $c=curl_init($url);
  curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>120,CURLOPT_CONNECTTIMEOUT=>15,
    CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload),
    CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_USERAGENT=>'ChatHelp-gentest/1.0']);
  $b=curl_exec($c); $code=(int)curl_getinfo($c,CURLINFO_HTTP_CODE); $err=curl_error($c); curl_close($c);
  return [$code,$b,$err];
}

$who='Mehmet Yılmaz, doğum: 12.03.1988, pasaport: U12345678, meslek: İnşaat Mühendisi, ikamet: İstanbul, Türkiye';
$base_tr='Vize: Almanya (Turizm), seyahat tarihleri 2026-08-10 – 2026-08-24, şehir(ler): Berlin, München. Başvuran: '.$who.'.';
$tail="\nKöşeli parantezli yer tutucu ASLA bırakma; bilinmeyen alan varsa cümleyi bilgisiz kur.";
$P=[
 'cover'=>'Erstelle ein formelles, überzeugendes Motivations-/Begleitschreiben (cover letter) für einen Visumantrag. '.$base_tr.' Erwähne den Reisezweck, die genauen Daten, die Finanzierung, die Reiseversicherung und die feste Rückkehrabsicht (Bindungen zum Heimatland). Adressiere es an das zuständige Konsulat. Schreibe den vollständigen Brief ausschließlich auf Deutsch, mit Datum und Unterschriftszeile. Gib NUR den Brieftext aus.'."\nEk bilgiler (belgeye uygun yerlere işle):\n- Konaklama: Hotel Berlin Mitte\n- Masrafları kim karşılıyor?: Kendim".$tail,
 'invitation'=>'Erstelle ein formelles Einladungsschreiben, das die EINLADENDE Person im Zielland unterschreibt, zur Unterstützung dieses Visumantrags. '.$base_tr.' Die einladende Person bestätigt Beziehung, Unterkunft und ggf. Kostenübernahme sowie die fristgerechte Rückkehr des Gastes. Schreibe ausschließlich auf Deutsch. Gib NUR den Brieftext mit Unterschriftszeile für den Gastgeber aus.'."\nEk bilgiler:\n- Davet eden: Ali Kaya\n- Adres: Hauptstr. 5, Berlin\n- Yakınlık: kardeş".$tail,
 'employer'=>'Erstelle ein formelles Arbeitgeber-Schreiben, das der ARBEITGEBER unterschreibt: bestätigt Anstellung, Position, Gehalt, den genehmigten Urlaub für den Reisezeitraum und die Rückkehr an den Arbeitsplatz. '.$base_tr.' Schreibe ausschließlich auf Deutsch. Gib NUR den Brieftext aus, mit Firmenkopf-Zeile und Unterschriftszeile.'."\nEk bilgiler:\n- Firma: Yılmaz İnşaat A.Ş.\n- Pozisyon: Şantiye Şefi\n- İşe giriş: 03/2019".$tail,
 'selfemployed'=>'Erstelle eine formelle Selbstständigen-Erklärung (Eigenerklärung des Geschäftsinhabers) für diesen Visumantrag: bestätigt die selbstständige Tätigkeit, die Firma, die Fortführung des Geschäfts während der Reise und die Rückkehr. '.$base_tr.' Schreibe ausschließlich auf Deutsch. Gib NUR den Brieftext mit Unterschriftszeile aus.'."\nEk bilgiler:\n- Firma: Yılmaz Danışmanlık\n- Faaliyet: mühendislik danışmanlığı".$tail,
 'itinerary'=>'Erstelle einen übersichtlichen Tages-Reiseplan (itinerary) für diesen Visumantrag. '.$base_tr.' Liste für jeden Tag Datum, Ort/Stadt und geplante Aktivität. Schreibe ausschließlich auf Deutsch. Gib NUR den Reiseplan aus.'."\nEk bilgiler:\n- Plan: 4 gece Berlin (müzeler, şehir turu), 3 gece München\n- Konaklama: Hotel Berlin Mitte / Hotel München City".$tail
];

$fails=0;
foreach($P as $g=>$msg){
  echo "───── [$g] ─────\n";
  list($code,$body,$err)=post($base.'api.php?action=aichat',
    ['message'=>$msg,'history'=>[],'provider'=>'claude','lang'=>'tr','country'=>'DE']);
  if($body===false||$body===null||$body===''){ echo "  ✗ HTTP $code — govde yok".($err?" ($err)":"")."\n\n"; $fails++; continue; }
  $j=json_decode($body,true);
  if(!is_array($j)){ echo "  ✗ HTTP $code — JSON degil: ".substr($body,0,200)."\n\n"; $fails++; continue; }
  if(!empty($j['error'])){ echo "  ✗ HTTP $code — API HATASI: ".$j['error']."\n\n"; $fails++; continue; }
  $r=(string)($j['reply']??'');
  $r2=trim(preg_replace('/```[a-z]*\n?|```/','',$r));
  $len=mb_strlen($r2);
  $ph=preg_match('/\[[A-ZÄÖÜ_ ]{3,}\]/u',$r2);
  echo "  HTTP $code — yanit ".($r?'VAR':'YOK')." — ".$len." karakter — yer tutucu: ".($ph?'⚠ VAR':'yok ✓')."\n";
  if($len<200){ echo "  ⚠ COK KISA (belge sayilmaz)\n"; $fails++; }
  echo "  Ilk 350 karakter:\n  ".preg_replace('/\s+/',' ',mb_substr($r2,0,350))."\n\n";
}
echo $fails? "SONUC: $fails belge SORUNLU — yukaridaki hatalara gore duzeltilecek.\n"
           : "SONUC: 5/5 belge uretimi CALISIYOR ✓ (sorun frontend'te degilse kota/oturumla ilgili olabilir)\n";
