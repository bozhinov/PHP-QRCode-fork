<?php

require_once("bootstrap.php");

use QRCode\QRCode;
use QRCode\QRException;

define("QR_ALL_MASKS", true);

## Start timer
$mtime = explode(" ",microtime());
$starttime = $mtime[1] + $mtime[0];

$QRCode = new QRcode(['level' => QR_ECLEVEL_Q, 'size' => 10, 'margin' => 4]);
$QRCode->encode('https://github.com/bozhinov/PHP-QRCode-fork')->toFile("temp/example.QRcode.all.masks.png");

## Stop timer
$mtime = explode(" ",microtime());
echo "Test took ".(($mtime[1] + $mtime[0]) - $starttime)." seconds";

?>