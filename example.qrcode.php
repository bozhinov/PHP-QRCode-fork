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

(new QRcode($errorCorrectionLevel, $matrixPointSize, $margin = 4))->png('http://www.test.bg/фффф', "example.QRcode.png");

?>