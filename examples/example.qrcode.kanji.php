<?php

require_once("bootstrap.php");

use QRCode\QRCode;

## Start timer
$mtime = explode(" ",microtime());
$starttime = $mtime[1] + $mtime[0];

if (function_exists("mb_internal_encoding")){
	mb_internal_encoding('SJIS');
} else {
	die("mb_string ext is required");
}

$QRCode = new QRCode(['level' => "Q", 'size' => 10, 'margin' => 4]);
$QRCode->encode('“ú–{‚Ì•Ûˆç‰€', "Kanji")->toFile("temp/example.QRcode.kanji.png");

## Stop timer
$mtime = explode(" ",microtime());
echo "Test took ".(($mtime[1] + $mtime[0]) - $starttime)." seconds";
