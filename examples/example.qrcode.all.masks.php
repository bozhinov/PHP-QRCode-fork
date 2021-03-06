<?php

require_once("bootstrap.php");

use QRCode\QRCode;

## Start timer
$mtime = explode(" ",microtime());
$starttime = $mtime[1] + $mtime[0];

$QRCode = new QRCode(['level' => "Q", 'size' => 10, 'margin' => 4]);
$QRCode->encode('https://github.com/bozhinov/PHP-QRCode-fork')->toFile("temp/example.QRcode.all.masks.png");

## Stop timer
$mtime = explode(" ",microtime());
echo "Test took ".(($mtime[1] + $mtime[0]) - $starttime)." seconds";
