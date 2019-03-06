If you are looking for a proper implemention take a look at this one:<br />
https://github.com/chillerlan/php-qrcode

This is PHP implementation of QR Code 2-D barcode generator.<br />
It is pure-php LGPL-licensed implementation based on C libqrencode by Kentaro Fukuchi.<br />

Based on:<br />
http://sourceforge.net/projects/phpqrcode/<br />
By Dominik Dzienia<br />

I was looking for a QR implementation for the pChart fork and came across this one.

My code is 30% (~33kb) the size of the original one and was built for speed.<br />
This is not a drop in replacement.<br />
490x490px image with all 8 masks takes ~19 miliseconds on my machine<br />
That is 10x faster than the Chillerlan's implementation<br />
It is also 5ms slower than the original implementation by Dominik which only uses 2 masks by default
