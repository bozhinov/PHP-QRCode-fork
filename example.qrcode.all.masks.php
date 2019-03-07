<?php

require_once("bootstrap.php");

use QRCode\QRCode;
use QRCode\QRException;

define("QR_ALL_MASKS", true);

## Start timer
$mtime = explode(" ",microtime());
$starttime = $mtime[1] + $mtime[0];

$QRCode = new QRcode(2, 10, 4);
$QRCode->encode('https://github.com/bozhinov/PHP-QRCode-fork')->toFile("example.QRcode.all.masks.png");

## Stop timer
$mtime = explode(" ",microtime());
echo "Test took ".(($mtime[1] + $mtime[0]) - $starttime)." seconds";

?>