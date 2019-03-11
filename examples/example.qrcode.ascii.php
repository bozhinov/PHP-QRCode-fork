<?php

require_once("QRCode.php");

$QRCode = new QRcode(QR_ECLEVEL_Q, 10, 4);
$text = $QRCode->encode('http://www.test.bg/BLA BLA BLA BLA BLA BLA')->toASCII();

file_put_contents("ascii.qrcode.txt", $text);

echo "Now run 'type ascii.qrcode.txt' in the CMD";

/*

Recognition works even with inverted colors (while on black)
but you could change the CMD colors to match - color 0F or color 03

Resize the CMD window - mode con: cols=100 lines=40

For PowerShell you need to do this:
$output.replace("U", [char]0x2588)

*/

?>