<?php

require_once("bootstrap.php");

use QRCode\QRCode;

define("QR_ALL_MASKS", true);

## Start timer
$mtime = explode(" ",microtime());
$starttime = $mtime[1] + $mtime[0];

$QRCode = new QRcode(['level' => QR_ECLEVEL_Q, 'size' => 10, 'margin' => 4]);
$QRCode->encode('http://www.test.bg/12341234 TEST TEST  TEST  TEST  TEST  TEST  TEST  TEST  TEST   TEST   TEST   TESTTSTSTEST  TEST  TEST   TEST   TEST   TESTTSTSTEST  TEST  TEST   TEST   TEST   TESTTSTSTEST  TEST  TEST   TEST   TEST   TESTTSTSTEST  TEST  TEST   TEST   TE')->toFile("temp/example.QRcode.very.long.png");

## Stop timer
$mtime = explode(" ",microtime());
echo "Test took ".(($mtime[1] + $mtime[0]) - $starttime)." seconds";
