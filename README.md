
This is PHP 7+ only implementation of QR Code 2-D barcode generator.<br />

Based on:<br />
http://sourceforge.net/projects/phpqrcode/<br />
By Dominik Dzienia<br />

I was looking for a QR implementation for the pChart fork and came across this one.<br />
It was either refactor Dominik's or use Chillerlan's (https://github.com/chillerlan/php-qrcode)<br />
This fork is not a drop in replacement.<br />
My code is four times smaller and two times faster<br />
(much much faster than the Chillerlan's implementation)<br />

Usage:<br />

	Possible hints: "Numeric", "Alphanumeric", "Byte", "Kanji"

- Single line:<br />

(new QRCode([<br />
<pre><code>'level' => $error_correction_level,<br />
'size' => $max_module_size,<br />
'margin' => $white_frame_size<br />
</code></pre>
]))->encode('https://github.com/bozhinov/PHP-QRCode-fork')->toFile("example.QRcode.png");<br />

- Or:<br />
$QRCode = new QRCode(['level' => "Q", 'size' => 10, 'margin' => 4]);<br />
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
