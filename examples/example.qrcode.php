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

define("QR_ALL_MASKS", true);

/* Usage

Single line:
(new QRcode(QR_ECLEVEL_Q, 10, 4))->encode('http://www.test.bg/')->toFile("example.QRcode.png");

Or:
$QRCode = new QRcode(QR_ECLEVEL_Q, 10, 4);
$QRCode->encode('http://www.test.bg/', $hint);

Dump the matrix:
echo json_encode($QRCode->toArray());

Dump base64 encoded PNG:
$QRCode->toBase64();

Create ASCII:
$QRCode->toASCII();

Load a matrix:
$QRCode->fromArray($matix);

$QRCode->toFile("example.QRcode.png");
$QRCode->toFile("example.QRcode.svg");
$QRCode->toFile("example.QRcode.jpg");

Add HTTP headers:
$QRCode->forWeb("PNG");
$QRCode->forWeb("SVG");
$QRCode->forWeb("JPG", $quality = 90);

*/

$QRCode = new QRcode(['level' => QR_ECLEVEL_Q, 'size' => 10, 'margin' => 4]);
$QRCode->encode('http://www.test.bg/фффф TEST TEST  TEST  TEST  TEST  TEST  TEST  TEST  TEST ')->toFile("temp/example.QRcode.png");

$QRCode->config(["error_correction" => QR_ECLEVEL_H, "matrix_point_size" => 8, "margin" => 4]);
$QRCode->encode('http://www.test.bg/фффф')->toFile("temp/example2.QRcode.png");

$QRCode->config(["error_correction" => QR_ECLEVEL_M, "matrix_point_size" => 6, "margin" => 4]);
$QRCode->encode('momchil@bojinov.info')->toFile("temp/example3.QRcode.png");

$QRCode->config(["error_correction" => QR_ECLEVEL_H, "matrix_point_size" => 7, "margin" => 4]);
$QRCode->encode('momchil@bojinov.info')->toFile("temp/example4.QRcode.jpg");

$QRCode->config(["error_correction" => QR_ECLEVEL_Q, "matrix_point_size" => 10, "margin" => 4]);
$QRCode->encode('http://www.test.bg/фффф TEST TEST  TEST  TEST  TEST  TEST  TEST  TEST ', QR_MODE_8)->toFile("temp/example5.QRcode.png"); // 70 chars

$QRCode->config(["error_correction" => QR_ECLEVEL_L, "matrix_point_size" => 7, "margin" => 4]);
$QRCode->encode('00359888888888', $hint = QR_MODE_NUM)->toFile("temp/example6.QRcode.jpg", $quality = 90);

## Stop timer
$mtime = explode(" ",microtime());
echo "Test took ".(($mtime[1] + $mtime[0]) - $starttime)." seconds";

?>