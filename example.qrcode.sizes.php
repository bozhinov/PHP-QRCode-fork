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

/* Reference

ERROR_CORRECTION_LEVEL - Reed–Solomon error correction
# https://en.wikipedia.org/wiki/QR_code

MAX_POINT_SIZE - max module size
# https://www.qrcode.com/en/howto/cell.html

MARGIN - the thickness of the white frame

Sizing table:

QR_ECLEVEL_L, 1, 1 = 27 px
QR_ECLEVEL_L, 2, 1 = 54 px
QR_ECLEVEL_L, 3, 1 = 81 px
QR_ECLEVEL_L, 4, 1 = 108 px
QR_ECLEVEL_L, 5, 1 = 135 px
QR_ECLEVEL_L, 6, 1 = 162 px
QR_ECLEVEL_L, 7, 1 = 189 px
QR_ECLEVEL_L, 8, 1 = 216 px
QR_ECLEVEL_L, 9, 1 = 243 px
QR_ECLEVEL_L, 10,1 = 270 px

QR_ECLEVEL_M, 1, 1 = 31 px
QR_ECLEVEL_M, 2, 1 = 62 px
QR_ECLEVEL_M, 3, 1 = 93 px
QR_ECLEVEL_M, 4, 1 = 124 px
QR_ECLEVEL_M, 5, 1 = 155 px
QR_ECLEVEL_M, 6, 1 = 186 px
QR_ECLEVEL_M, 7, 1 = 217 px
QR_ECLEVEL_M, 8, 1 = 248 px
QR_ECLEVEL_M, 9, 1 = 279 px
QR_ECLEVEL_M, 10,1 = 310 px

QR_ECLEVEL_Q, 1, 1 = 31 px
QR_ECLEVEL_Q, 2, 1 = 62 px
QR_ECLEVEL_Q, 3, 1 = 93 px
QR_ECLEVEL_Q, 4, 1 = 124 px
QR_ECLEVEL_Q, 5, 1 = 155 px
QR_ECLEVEL_Q, 6, 1 = 186 px
QR_ECLEVEL_Q, 7, 1 = 217 px
QR_ECLEVEL_Q, 8, 1 = 248 px
QR_ECLEVEL_Q, 9, 1 = 279 px
QR_ECLEVEL_Q, 10,1 = 310 px

QR_ECLEVEL_H, 1, 1 = 35 px
QR_ECLEVEL_H, 2, 1 = 70 px
QR_ECLEVEL_H, 3, 1 = 105 px
QR_ECLEVEL_H, 4, 1 = 140 px
QR_ECLEVEL_H, 5, 1 = 175 px
QR_ECLEVEL_H, 6, 1 = 210 px
QR_ECLEVEL_H, 7, 1 = 245 px
QR_ECLEVEL_H, 8, 1 = 280 px
QR_ECLEVEL_H, 9, 1 = 315 px
QR_ECLEVEL_H, 10,1 = 350 px

*/

$string = 'http://www.test.bg/фффф';

$QRCode = new QRcode(QR_ECLEVEL_H, 1, 1);
$Q = QR_ECLEVEL_H;
$QRCode->encode($string)->toFile("exampleS01.QRcode.png");

$QRCode->config(["error_correction" => $Q, "matrix_point_size" => 2, "margin" => 1]);
$QRCode->encode($string)->toFile("exampleS02.QRcode.png");

$QRCode->config(["error_correction" => $Q, "matrix_point_size" => 3, "margin" => 1]);
$QRCode->encode($string)->toFile("exampleS03.QRcode.png");

$QRCode->config(["error_correction" => $Q, "matrix_point_size" => 4, "margin" => 1]);
$QRCode->encode($string)->toFile("exampleS04.QRcode.png");

$QRCode->config(["error_correction" => $Q, "matrix_point_size" => 5, "margin" => 1]);
$QRCode->encode($string)->toFile("exampleS05.QRcode.png");

$QRCode->config(["error_correction" => $Q, "matrix_point_size" => 6, "margin" => 1]);
$QRCode->encode($string)->toFile("exampleS06.QRcode.png");

$QRCode->config(["error_correction" => $Q, "matrix_point_size" => 7, "margin" => 1]);
$QRCode->encode($string)->toFile("exampleS07.QRcode.png");

$QRCode->config(["error_correction" => $Q, "matrix_point_size" => 8, "margin" => 1]);
$QRCode->encode($string)->toFile("exampleS08.QRcode.png");

$QRCode->config(["error_correction" => $Q, "matrix_point_size" => 9, "margin" => 1]);
$QRCode->encode($string)->toFile("exampleS09.QRcode.png");

$QRCode->config(["error_correction" => $Q, "matrix_point_size" => 10, "margin" => 1]);
$QRCode->encode($string)->toFile("exampleS10.QRcode.png");

## Stop timer
$mtime = explode(" ",microtime());
echo "Test took ".(($mtime[1] + $mtime[0]) - $starttime)." seconds";

?>