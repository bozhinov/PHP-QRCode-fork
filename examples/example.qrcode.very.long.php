<?php

require_once("bootstrap.php");

use QRCode\QRCode;

define("QR_ALL_MASKS", true);

## Start timer
$mtime = explode(" ",microtime());
$starttime = $mtime[1] + $mtime[0];

$QRCode = new QRCode(['level' => "Q", 'size' => 10, 'margin' => 4]);
$QRCode->encode(str_repeat("A", 256))->toFile("temp/example.QRcode.very.long.png");

## Stop timer
$mtime = explode(" ",microtime());
echo "Test took ".(($mtime[1] + $mtime[0]) - $starttime)." seconds";
