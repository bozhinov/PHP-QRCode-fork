If you are looking for a proper implemention take a look at this one:<br />
https://github.com/chillerlan/php-qrcode

This is PHP implementation of QR Code 2-D barcode generator.<br />
It is pure-php LGPL-licensed implementation based on C libqrencode by Kentaro Fukuchi.<br />

Based on:<br />
http://sourceforge.net/projects/phpqrcode/<br />
By Dominik Dzienia<br />

I was looking for a QR implementation for the pChart fork and came across this one.

My code four times smaller and two times faster<br />
(much much faster than the Chillerlan's implementation)<br />
This is not a drop in replacement.<br />

Usage:<br />

Possible hints: QR_MODE_NUM, QR_MODE_AN, QR_MODE_8, QR_MODE_KANJI<br /><br />

- Single line:<br />
(new QRcode(QR_ECLEVEL_Q, $max_module_size, $white_frame_size))->encode('https://github.com/bozhinov/PHP-QRCode-fork')->toFile("example.QRcode.png");<br />

- Or:<br />
$QRCode = new QRcode(QR_ECLEVEL_Q, 10, 4);<br />
$QRCode->encode('https://github.com/bozhinov/PHP-QRCode-fork', $hint);<br />

- Dump the matrix:<br />
echo json_encode($QRCode->toArray());<br />

- Load a matrix:<br />
$QRCode->fromArray($matix);<br />

- Dump base64 encoded PNG:<br />
$QRCode->toBase64();<br />

- Create ASCII:<br />
$QRCode->toASCII();<br />

- Output to file:<br />
$QRCode->toFile("example.QRcode.png");<br />
$QRCode->toFile("example.QRcode.svg");<br />
$QRCode->toFile("example.QRcode.jpg");<br />

- Add HTTP headers:<br />
$QRCode->forWeb("PNG");<br />
$QRCode->forWeb("SVG");<br />
$QRCode->forWeb("JPG", $quality = 90);<br />
