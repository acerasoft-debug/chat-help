<?php
/* dump-lx-jobs — LetterXpress is listesi + bakiye (salt-okunur). -0,96 € nereden
   geldi gosterir. Kimlik config.php'den; apikey EKRANA yazilmaz.
   KULLANIM: pull2.php?key=...&files=dump-lx-jobs.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
@include_once __DIR__.'/config.php';
echo "=== LetterXpress is listesi ===\n\n";
if(!defined('LETTERXPRESS_USER')||!defined('LETTERXPRESS_APIKEY')){ exit("config yok\n"); }
$mode=defined('LETTERXPRESS_MODE')?constant('LETTERXPRESS_MODE'):'test';
$auth=['username'=>constant('LETTERXPRESS_USER'),'apikey'=>constant('LETTERXPRESS_APIKEY'),'mode'=>($mode==='live'?'live':'test')];
function lx($path,$payload){
  $ch=curl_init('https://api.letterxpress.de/v1/'.$path);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_TIMEOUT=>40]);
  $r=curl_exec($ch); $c=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); return [$c,$r];
}
echo "mode = $mode | user = ".constant('LETTERXPRESS_USER')."\n\n";

list($bc,$br)=lx('getBalance',['auth'=>$auth]);
$bj=json_decode((string)$br,true);
echo "BAKIYE: ".(is_array($bj)&&isset($bj['balance'])?json_encode($bj['balance']):substr((string)$br,0,200))."\n\n";

foreach(['getJobs','getJobList'] as $ep){
  list($c,$r)=lx($ep,['auth'=>$auth]);
  if($c===200){ echo "== $ep ==\n";
    $j=json_decode((string)$r,true);
    if(is_array($j)){
      $jobs=$j['jobs']??($j['data']??$j);
      if(is_array($jobs)){
        $n=0;
        foreach($jobs as $k=>$job){
          if(!is_array($job)) continue; $n++;
          echo "  #".($job['id']??$k)." status=".($job['status']??'?')." price=".($job['price']??$job['cost']??'?')." pages=".($job['pages']??'?')." ".($job['datetime']??$job['created']??'')."\n";
        }
        echo "  toplam is: $n\n";
      } else echo "  ".substr((string)$r,0,400)."\n";
    } else echo "  ".substr((string)$r,0,400)."\n";
    echo "\n"; break;
  }
}
echo "NOT: status 'hold' = test isi, fiziksel GONDERILMEDI. Kredisiz hesapta fiyati\n";
echo "     bakiyeyi eksiye dusurmus olabilir. Silmek istersen deleteJob ile temizlenir.\n";
