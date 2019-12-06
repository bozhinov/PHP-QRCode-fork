<?php

require_once("bootstrap.php");

use QRCode\QRCode;

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

if (function_exists("mb_internal_encoding")){
	mb_internal_encoding('SJIS');
} else {
	die("mb_string ext is required");
}

$QRCode = new QRcode(['level' => QR_ECLEVEL_Q, 'size' => 10, 'margin' => 4]);
$QRCode->encode('“ú–{‚Ì•Ûˆç‰€', QR_MODE_KANJI)->toFile("temp/example.QRcode.kanji.png");

## Stop timer
$mtime = explode(" ",microtime());
echo "Test took ".(($mtime[1] + $mtime[0]) - $starttime)." seconds";
