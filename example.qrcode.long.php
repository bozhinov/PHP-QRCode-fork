<?php


include "phpqrcode.php";    

## Start timer
$mtime = explode(" ",microtime());
$starttime = $mtime[1] + $mtime[0];

QRcode::png('http://www.test.bg/12341234 TEST TEST  TEST  TEST  TEST  TEST  TEST  TEST  TEST   TEST   TEST   TESTTSTS', "example.QRcode.long.png", 2, 10, 4);  

## Stop timer
$mtime = explode(" ",microtime());
echo "Test took ".(($mtime[1] + $mtime[0]) - $starttime)." seconds";

?>