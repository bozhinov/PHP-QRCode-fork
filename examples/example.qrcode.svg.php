<?php

require_once("bootstrap.php");

use QRCode\QRCode;
use QRCode\QRException;

#QR_ECLEVEL_L = 0
#QR_ECLEVEL_M = 1
#QR_ECLEVEL_Q = 2
#QR_ECLEVEL_H = 3

#QR_MODE_NUM = 0
#QR_MODE_AN = 1
#QR_MODE_8 = 2
#QR_MODE_KANJI = 3

## Start timer
$mtime = explode(" ",microtime());
$starttime = $mtime[1] + $mtime[0];

$QRCode = new QRcode(['level' => QR_ECLEVEL_Q, 'size' => 10, 'margin' => 4]);
$QRCode->encode('http://www.test.bg/  TEST  TEST')->toFile("temp/example.QRcode.svg");

## Stop timer
$mtime = explode(" ",microtime());
echo "Test took ".(($mtime[1] + $mtime[0]) - $starttime)." seconds";

?>