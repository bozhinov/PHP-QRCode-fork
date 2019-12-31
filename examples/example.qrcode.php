<?php

require_once("bootstrap.php");

use QRCode\QRCode;

## Start timer
$mtime = explode(" ",microtime());
$starttime = $mtime[1] + $mtime[0];

# Force building of all masks instead of trying random ones
# Slower execution but guarantees you get the least amount of square possible
define("QR_ALL_MASKS", true);

/* Usage

Single line:
(new QRCode($level = "Q", $size = 10, $margin = 4))->encode('http://www.test.bg/')->toFile("example.QRcode.png");

Or:
$QRCode = new QRCode("Q", 10, 4);
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
# Levels
# https://www.qrcode.com/en/about/error_correction.html
# Level L = ~7%
# Level M = ~15%
# Level Q = ~25%
# Level H = ~30%

# Encoding hints
# https://www.thonky.com/qr-code-tutorial/data-encoding
# Encoding Mode		Maximum number of characters a 40-L code can contain
# Numeric		7089 characters
# Alphanumeric		4296 characters
# Byte			2953 characters
# Kanji			1817 characters

$QRCode = new QRCode(['level' => "Q", 'size' => 10, 'margin' => 4]);
$QRCode->encode('http://www.test.bg/фффф TEST TEST  TEST  TEST  TEST  TEST  TEST  TEST  TEST ')->toFile("temp/example.QRcode.png");

$QRCode->config(["error_correction" => "H", "matrix_point_size" => 8, "margin" => 4]);
$QRCode->encode('http://www.test.bg/фффф')->toFile("temp/example2.QRcode.png");

$QRCode->config(["error_correction" => "M", "matrix_point_size" => 6, "margin" => 4]);
$QRCode->encode('momchil@bojinov.info')->toFile("temp/example3.QRcode.png");

$QRCode->config(["error_correction" => "H", "matrix_point_size" => 7, "margin" => 4]);
$QRCode->encode('momchil@bojinov.info')->toFile("temp/example4.QRcode.jpg");

$QRCode->config(["error_correction" => "Q", "matrix_point_size" => 10, "margin" => 4]);
$QRCode->encode('http://www.test.bg/фффф TEST TEST  TEST  TEST  TEST  TEST  TEST  TEST ', "Byte")->toFile("temp/example5.QRcode.png"); // 70 chars

$QRCode->config(["error_correction" => "L", "matrix_point_size" => 7, "margin" => 4]);
$QRCode->encode('00359888888888', $hint = "Numeric")->toFile("temp/example6.QRcode.jpg", $quality = 90);

## Stop timer
$mtime = explode(" ",microtime());
echo "Test took ".(($mtime[1] + $mtime[0]) - $starttime)." seconds";
