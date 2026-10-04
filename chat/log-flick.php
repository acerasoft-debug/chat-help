<?php
header('Content-Type: text/plain; charset=UTF-8'); error_reporting(E_ERROR|E_PARSE);
$d=@file_get_contents('php://input'); if($d===false)$d='';
@file_put_contents(__DIR__.'/flick-log.txt', "==== ".date('H:i:s')." ====\n".substr($d,0,4000)."\n", FILE_APPEND|LOCK_EX);
echo 'ok';
