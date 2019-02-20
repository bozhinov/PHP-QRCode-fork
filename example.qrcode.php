<?php

require_once("bootstrap.php");

use QRCode\QRCode;
use QRCode\QRException;

#QR_ECLEVEL_L = 0
#QR_ECLEVEL_M = 1
#QR_ECLEVEL_Q = 2
#QR_ECLEVEL_H = 3

$errorCorrectionLevel = 2;
$matrixPointSize = 10;

## Start timer
$mtime = explode(" ",microtime());
$starttime = $mtime[1] + $mtime[0];

(new QRcode($errorCorrectionLevel, $matrixPointSize, $margin = 4))->png('http://www.test.bg/фффф TEST TEST  TEST  TEST  TEST  TEST  TEST  TEST  TEST ', "example.QRcode.png");

(new QRcode(3, 8, 4))->png('http://www.test.bg/фффф', "example2.QRcode.png");

(new QRcode(1, 6, 4))->png('momchil@bojinov.info', "example3.QRcode.png");

(new QRcode(3, 7, 4))->jpg('momchil@bojinov.info', "example4.QRcode.jpg");

## Stop timer
$mtime = explode(" ",microtime());
echo "Op took ".(($mtime[1] + $mtime[0]) - $starttime)." seconds";

?>