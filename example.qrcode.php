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

$QRCode = new QRcode(QR_ECLEVEL_Q, 10, 4);
$QRCode->png('http://www.test.bg/фффф TEST TEST  TEST  TEST  TEST  TEST  TEST  TEST  TEST ', "example.QRcode.png");

$QRCode->config(["error_correction" => QR_ECLEVEL_H, "matrix_point_size" => 8, "margin" => 4]);
$QRCode->png('http://www.test.bg/фффф', "example2.QRcode.png");

$QRCode->config(["error_correction" => QR_ECLEVEL_M, "matrix_point_size" => 6, "margin" => 4]);
$QRCode->png('momchil@bojinov.info', "example3.QRcode.png");

$QRCode->config(["error_correction" => QR_ECLEVEL_H, "matrix_point_size" => 7, "margin" => 4]);
$QRCode->jpg('momchil@bojinov.info', "example4.QRcode.jpg");

$QRCode->config(["error_correction" => QR_ECLEVEL_Q, "matrix_point_size" => 10, "margin" => 4]);
$QRCode->png('http://www.test.bg/фффф TEST TEST  TEST  TEST  TEST  TEST  TEST  TEST ', "example5.QRcode.png", QR_MODE_8); // 70 chars

$QRCode->config(["error_correction" => QR_ECLEVEL_L, "matrix_point_size" => 7, "margin" => 4]);
$QRCode->jpg('00359888888888', "example6.QRcode.png", $quality = 90, $hint = QR_MODE_NUM);

## Stop timer
$mtime = explode(" ",microtime());
echo "Test took ".(($mtime[1] + $mtime[0]) - $starttime)." seconds";

?>