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

- Single line:
(new QRcode(QR_ECLEVEL_Q, 10, 4))->encode('http://www.test.bg/')->toFile("example.QRcode.png");<br />

- Or:
$QRCode = new QRcode(QR_ECLEVEL_Q, 10, 4);<br />
$QRCode->encode('http://www.test.bg/', $hint);<br />

- Dump the matrix:
echo json_encode($QRCode->toArray());<br />

- Dump base64 encoded PNG:
$QRCode->toBase64();<br />

- Create ASCII:
$QRCode->toASCII();<br />

- Load a matrix:
$QRCode->fromArray($matix);<br />

- Output to file:
$QRCode->toFile("example.QRcode.png");<br />
$QRCode->toFile("example.QRcode.svg");<br />
$QRCode->toFile("example.QRcode.jpg");<br />

- Add HTTP headers:
$QRCode->forWeb("PNG");<br />
$QRCode->forWeb("SVG");<br />
$QRCode->forWeb("JPG", $quality = 90);<br />
