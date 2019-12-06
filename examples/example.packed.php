<?php

require_once("QRCode.php");

## Start timer
$mtime = explode(" ",microtime());
$starttime = $mtime[1] + $mtime[0];

$QRCode = new QRcode(['level' => QR_ECLEVEL_Q]);
$QRCode->encode('https://github.com/bozhinov/PHP-QRCode-fork')->toFile("temp/example.QRcode.packed.png");

## Stop timer
$mtime = explode(" ",microtime());
echo "Test took ".(($mtime[1] + $mtime[0]) - $starttime)." seconds";
