<?php

require_once("bootstrap.php");

use QRCode\QRCode;

## Start timer
$mtime = explode(" ",microtime());
$starttime = $mtime[1] + $mtime[0];

$QRCode = new QRCode(['level' => "Q", 'size' => 10, 'margin' => 4]);
$QRCode->encode('http://www.test.bg/  TEST  TEST')->toFile("temp/example.QRcode.svg");

## Stop timer
$mtime = explode(" ",microtime());
echo "Test took ".(($mtime[1] + $mtime[0]) - $starttime)." seconds";
