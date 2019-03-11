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
<br />
- Single line:<br />
(new QRcode(QR_ECLEVEL_Q, 10, 4))->encode('http://www.test.bg/')->toFile("example.QRcode.png");<br />
<br />
- Or:<br />
$QRCode = new QRcode(QR_ECLEVEL_Q, 10, 4);<br />
$QRCode->encode('http://www.test.bg/', $hint);<br />
<br />
- Dump the matrix:<br />
echo json_encode($QRCode->toArray());<br />
<br />
- Dump base64 encoded PNG:<br />
$QRCode->toBase64();<br />
<br />
- Create ASCII:<br />
$QRCode->toASCII();<br />
<br />
- Load a matrix:<br />
$QRCode->fromArray($matix);<br />
<br />
- Output to file:<br />
$QRCode->toFile("example.QRcode.png");<br />
$QRCode->toFile("example.QRcode.svg");<br />
$QRCode->toFile("example.QRcode.jpg");<br />
<br />
- Add HTTP headers:<br />
$QRCode->forWeb("PNG");<br />
$QRCode->forWeb("SVG");<br />
$QRCode->forWeb("JPG", $quality = 90);<br />
