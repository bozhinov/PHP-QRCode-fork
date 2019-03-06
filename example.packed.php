<?php

require_once("QRCode.php");

## Start timer
$mtime = explode(" ",microtime());
$starttime = $mtime[1] + $mtime[0];

$QRCode = new QRcode(QR_ECLEVEL_Q, 10, 4);
$QRCode->encode('https://github.com/bozhinov/PHP-QRCode-fork')->toFile("example.QRcode.packed.png");

## Stop timer
$mtime = explode(" ",microtime());
echo "Test took ".(($mtime[1] + $mtime[0]) - $starttime)." seconds";

?>