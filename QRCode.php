<?php
/*
 * PHP QR Code
 *
 * Based on libqrencode C library distributed under LGPL 2.1
 * Copyright (C) 2006, 2007, 2008, 2009 Kentaro Fukuchi <fukuchi@megaui.net>
 *
 * PHP QR Code is distributed under LGPL 3
 * Copyright (C) 2010 Dominik Dzienia <deltalab at poczta dot fm>
 *
 * Code modifications by Momchil Bozhinov <momchil at bojinov dot info>
 * Last update - 03.2019
 *
 */

// Encoding modes
define('QR_MODE_NUM', 0);
define('QR_MODE_AN', 1);
define('QR_MODE_8', 2);
define('QR_MODE_KANJI', 3);

// Levels of error correction.
define('QR_ECLEVEL_L', 0);
define('QR_ECLEVEL_M', 1);
define('QR_ECLEVEL_Q', 2);
define('QR_ECLEVEL_H', 3);

class QRcode {

	private $size;
	private $h;
	private $margin;
	private $level;
	private $target_image;
	private $encoded = [];

	function __construct(int $level = 0, int $size = 3, int $margin = 4)
	{
		$this->size = $size;
		$this->level = $level;
		$this->margin = $margin;

		if (!in_array($level,[0,1,2,3])){
			throw QRException::Std('unknown error correction level');
		}
	}

	function __destruct()
	{
		if (is_resource($this->target_image)){
			imageDestroy($this->target_image);
		}
	}

	private function createImage()
	{
		$h = count($this->encoded);
		$imgH = $h + 2 * $this->margin;

		$base_image = imageCreate($imgH, $imgH);

		$white = imageColorAllocate($base_image,255,255,255);
		$black = imageColorAllocate($base_image,0,0,0);

		imageFill($base_image, 0, 0, $white);

		for($y=0; $y<$h; $y++) {
			for($x=0; $x<$h; $x++) {
				if ($this->encoded[$y][$x]&1) {
					imageSetPixel($base_image,$x+$this->margin,$y+$this->margin,$black);
				}
			}
		}

		$pixelPerPoint = min($this->size, $imgH);
		$target_h = $imgH * $pixelPerPoint;
		$this->h = $target_h;

		$this->target_image = imageCreate($target_h, $target_h);
		imageCopyResized($this->target_image, $base_image, 0, 0, 0, 0, $target_h, $target_h, $imgH, $imgH);
		imageDestroy($base_image);
	}

	private function toPNG($filename)
	{
		if(is_null($filename)) {
			header("Content-type: image/png");
		}
		imagePng($this->target_image, $filename);
	}

	private function toJPG($filename, $quality)
	{
		if(is_null($filename)) {
			header("Content-type: image/jpeg");
		}
		imageJpeg($this->target_image, $filename, $quality);
	}

	private function toSVG($filename)
	{
		ob_start();
		imagePng($this->target_image);
		$imagedata = ob_get_contents();
		ob_end_clean();

		$content = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
<svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="'.$this->h.'px" height="'.$this->h.'px" viewBox="0 0 '.$this->h.' '.$this->h.'" enable-background="new 0 0 '.$this->h.' '.$this->h.'" xml:space="preserve">
<image id="image0" width="'.$this->h.'" height="'.$this->h.'" x="0" y="0" href="data:image/png;base64,'.base64_encode($imagedata).'" />
</svg>';

		if(is_null($filename)) {
			header("Content-type: image/svg+xml");
			echo $content;
		} else {
			file_put_contents($filename, $content);
		}
	}

	public function config(array $opts)
	{
		if (isset($opts["error_correction"])){
			$this->level = $opts["error_correction"];
		}

		if (isset($opts["matrix_point_size"])){
			$this->size = $opts["matrix_point_size"];
		}

		if (isset($opts["margin"])){
			$this->margin = $opts["margin"];
		}

		$this->encoded = [];

		if (!in_array($this->level,[0,1,2,3])){
			throw QRException::Std('unknown error correction level');
		}
	}

	public function encode(string $text, int $hint = -1)
	{
		if($text == '\0' || $text == '') {
			throw QRException::Std('empty string!');
		}

		if (!in_array($hint,[-1,0,1,2,3])){
			throw QRException::Std('unknown hint');
		}

		$this->encoded = (new QRInput($this->level))->encodeString($text, $hint);

		return $this;
	}

	public function toArray()
	{
		return $this->encoded;
	}

	public function fromArray(array $encoded)
	{
		$this->encoded = $encoded;
	}

	public function toFile(string $filename, int $quality = 90, bool $forWeb = false)
	{
		$ext = strtoupper(substr($filename, -3));
		($forWeb) AND $filename = null;

		$this->createImage();

		switch($ext)
		{
			case "PNG":
				$this->toPNG($filename);
				break;
			case "JPG":
				$this->toJPG($filename, $quality);
				break;
			case "SVG":
				$this->toSVG($filename);
				break;
			default:
				throw QRException::Std('file extension unsupported!');
		}
	}

	public function forWeb(string $ext, int $quality = 90)
	{
		$this->toFile($ext, $quality, true);
	}

}

class QRException extends Exception
{
	public static function Std($text)
	{
		return new static(sprintf('QRCode: %s', $text));
	}
}

class QRInput {

	private $dataStr;
	private $dataStrLen;
	private $hint;
	private $pos;
	private $level;
	private $bstream = [];
	private $streams = [];
	private $maxLenlengths = [];
	private $lengthTableBits = [
		[10, 12, 14],
		[ 9, 11, 13],
		[ 8, 16, 16],
		[ 8, 10, 12]
	];

	private $anTable = [
		-1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
		-1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
		36, -1, -1, -1, 37, 38, -1, -1, -1, -1, 39, 40, -1, 41, 42, 43,
		 0,  1,  2,  3,  4,  5,  6,  7,  8,  9, 44, -1, -1, -1, -1, -1,
		-1, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24,
		25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35
	];
	
	private $capacity = [
		[0, [0, 0, 0, 0]],
		[26, [7, 10, 13, 17]], // 1
		[44, [10, 16, 22, 28]],
		[70, [15, 26, 36, 44]],
		[100, [20, 36, 52, 64]],
		[134, [26, 48, 72, 88]], // 5
		[172, [36, 64, 96, 112]],
		[196, [40, 72, 108, 130]],
		[242, [48, 88, 132, 156]],
		[292, [60, 110, 160, 192]],
		[346, [72, 130, 192, 224]], //10
		[404, [80, 150, 224, 264]],
		[466, [96, 176, 260, 308]],
		[532, [104, 198, 288, 352]],
		[581, [120, 216, 320, 384]],
		[655, [132, 240, 360, 432]], //15
		[733, [144, 280, 408, 480]],
		[815, [168, 308, 448, 532]],
		[901, [180, 338, 504, 588]],
		[991, [196, 364, 546, 650]],
		[1085, [224, 416, 600, 700]], //20
		[1156, [224, 442, 644, 750]],
		[1258, [252, 476, 690, 816]],
		[1364, [270, 504, 750, 900]],
		[1474, [300, 560, 810, 960]],
		[1588, [312, 588, 870, 1050]], //25
		[1706, [336, 644, 952, 1110]],
		[1828, [360, 700, 1020, 1200]],
		[1921, [390, 728, 1050, 1260]],
		[2051, [420, 784, 1140, 1350]],
		[2185, [450, 812, 1200, 1440]], //30
		[2323, [480, 868, 1290, 1530]],
		[2465, [510, 924, 1350, 1620]],
		[2611, [540, 980, 1440, 1710]],
		[2761, [570, 1036, 1530, 1800]],
		[2876, [570, 1064, 1590, 1890]], //35
		[3034, [600, 1120, 1680, 1980]],
		[3196, [630, 1204, 1770, 2100]],
		[3362, [660, 1260, 1860, 2220]],
		[3532, [720, 1316, 1950, 2310]],
		[3706, [750, 1372, 2040, 2430]] //40
	];

	function __construct(int $level)
	{
		$this->level = $level;
	}
	
	private function getDataLength($version)
	{
		$ecc = $this->capacity[$version][1][$this->level];
		return [$this->capacity[$version][0] - $ecc, $ecc];
	}

	private function lookAnTable($c)
	{
		return (($c > 90) ? -1 : $this->anTable[$c]);
	}

	private function lengthIndicator($mode, $version)
	{
		if ($version <= 9) {
			$l = 0;
		} else if ($version <= 26) {
			$l = 1;
		} else {
			$l = 2;
		}

		return $this->lengthTableBits[$mode][$l];
	}

	private function encodeModeNum($size, $data)
	{
		$words = (int)($size / 3);

		$this->bstream[] = [4, 1];
		$this->bstream[] = [$this->maxLenlengths[QR_MODE_NUM], $size];

		for($i=0; $i<$words; $i++) {
			$val  = ($data[$i*3] - 48) * 100;
			$val += ($data[$i*3+1] - 48) * 10;
			$val += ($data[$i*3+2] - 48);
			$this->bstream[] = [10, $val];
		}

		if($size - $words * 3 == 1) {
			$val = $data[$words*3] - 48;
			$this->bstream[] = [4, $val];
		} elseif($size - $words * 3 == 2) {
			$val  = ($data[$words*3] - 48) * 10;
			$val += ($data[$words*3+1] - 48);
			$this->bstream[] = [7, $val];
		}
	}

	private function encodeModeAn($size, $data)
	{
		$words = (int)($size / 2);

		$this->bstream[] = [4, 2];
		$this->bstream[] = [$this->maxLenlengths[QR_MODE_AN], $size];

		for($i=0; $i<$words; $i++) {
			$val = ($this->lookAnTable($data[$i*2]) * 45) + $this->lookAnTable($data[$i*2+1]);
			$this->bstream[] = [11, $val];
		}

		if($size & 1) {
			$val = $this->lookAnTable($data[$words * 2]);
			$this->bstream[] = [6, $val];
		}
	}

	private function encodeMode8($size, $data)
	{
		$this->bstream[] = [4, 4];
		$this->bstream[] = [$this->maxLenlengths[QR_MODE_8], $size];

		for($i=0; $i<$size; $i++) {
			$this->bstream[] = [8, $data[$i]];
		}
	}

	private function encodeModeKanji($size, $data)
	{
		$this->bstream[] = [4, 8];
		$this->bstream[] = [$this->maxLenlengths[QR_MODE_KANJI], (int)($size / 2)];

		for($i=0; $i<$size; $i+=2) {
			$val = ($data[$i] << 8) | $data[$i+1];
			if($val <= 40956) {
				$val -= 33088;
			} else {
				$val -= 49472;
			}

			$val = ($val & 255) + (($val >> 8) * 192);

			$this->bstream[] = [13, $val];
		}
	}
 
	private function estimateVersion($version)
	{
		$bits = 0;
		foreach($this->streams as $stream) {
			list($mode, $size, ) = $stream;
			switch($mode) {
				case QR_MODE_NUM:
					$bits += (int)($size / 3) * 10;
					switch($size % 3) {
						case 1:
							$bits += 4;
							break;
						case 2:
							$bits += 7;
							break;
					}
					break;
				case QR_MODE_AN:
					$bits += (int)($size / 2) * 11;
					if($size & 1) {
						$bits += 6;
					}
					break;
				case QR_MODE_8:
					$bits += ($size * 8);
					break;
				case QR_MODE_KANJI:
					$bits += (int)($size / 2) * 13;
					break;
			}

			$l = $this->lengthIndicator($mode, $version);
			$this->maxLenlengths[$mode] = $l;
            $m = 1 << $l;
            $num = (int)(($size + $m - 1) / $m);
            $bits += $num * (4 + $l);
		}

		return $this->getMinimumVersion($bits);
	}

	private function encodeStreams()
	{
		$version = 1;
        do {
			$prev = $version;
			$package = $this->estimateVersion($version);
			$version = $package[0];
		} while ($version > $prev);

		foreach($this->streams as $stream) {

			list($mode, $size, $data) = $stream;

			switch($mode) {
				case QR_MODE_NUM:
					$this->encodeModeNum($size, $data);
					break;
				case QR_MODE_AN:
					$this->encodeModeAn($size, $data);
					break;
				case QR_MODE_8:
					$this->encodeMode8($size, $data);
					break;
				case QR_MODE_KANJI:
					$this->encodeModeKanji($size, $data);
					break;
			}
		}

		$bits = array_pop($package);
		$maxwords = $package[1];

		$bits += 4;
		$words = floor(($bits + 7) / 8);

		$this->bstream[] = [$words * 8 - $bits + 4, 0];

		$padlen = $maxwords - $words;

		if($padlen > 0) {
			for($i=0; $i<$padlen; $i+=2) {
				$this->bstream[] = [8, 236];
				$this->bstream[] = [8, 17];
			}
		}
		$package[] = $this->toByte();

		return $package;
	}

	private function getMinimumVersion($bits)
	{
		$size = (int)(($bits + 7) / 8);
		for($i=1; $i<= 40; $i++) { # QR_SPEC_VERSION_MAX = 40
			list($dataLength, $ecc) = $this->getDataLength($i);
			if($dataLength >= $size){
				$width = $i * 4 + 17;
				return [$i, $dataLength, $ecc, $width, $this->level, $bits];
			}
		}
	}

	private function toByte()
	{
		$dataStr = "";

		foreach($this->bstream as $d){

			list($bits, $num) = $d;

			$bin = decbin($num);
			$diff = $bits - strlen($bin);

			if ($diff != 0){
				$bin = str_repeat("0", $diff).$bin;
			}

			$dataStr .= $bin;
		}

		$data = [];

		foreach(str_split($dataStr, 8) as $val){
			$data[] = bindec($val);
		}

		return $data;
	}

	private function is_digit()
	{
		if ($this->pos >= $this->dataStrLen){
			return false;
		}
		return ($this->dataStr[$this->pos] >= 48 && $this->dataStr[$this->pos] <= 57);
	}

	private function is_alnum()
	{
		if ($this->pos >= $this->dataStrLen){
			return false;
		}
		return ($this->lookAnTable($this->dataStr[$this->pos]) >= 0);
	}

	private function is_kanji()
	{
		if ($this->pos+1 < $this->dataStrLen) {
			$word = ($this->dataStr[$this->pos]) << 8 | $this->dataStr[$this->pos+1];
			if(($word >= 33088 && $word <= 40956) || ($word >= 57408 && $word <= 60351)) {
				return true;
			}
		}
		return false;
	}

	private function identifyMode()
	{
		switch (true){
			case $this->is_digit():
				$mode = QR_MODE_NUM;
				break;
			case $this->is_alnum():
				$mode = QR_MODE_AN;
				break;
			case ($this->hint == QR_MODE_KANJI):
				$mode = ($this->is_kanji()) ? QR_MODE_KANJI : QR_MODE_8;
				break;
			default:
				$mode = QR_MODE_8;
		}

		return $mode;
	}

	private function eatNum()
	{
		$this->pos++;

		while($this->is_digit()) {
			$this->pos++;
		}
	}

	private function eatAn()
	{
		$this->pos++;

		while($this->is_alnum()) {
			$this->pos++;
		}
	}

	private function eatKanji()
	{
		$this->pos += 2;

		while($this->is_kanji()) {
			$this->pos += 2;
		}
	}

	private function eat8()
	{
		$this->pos++;

		while($this->pos < $this->dataStrLen) {

			switch($this->identifyMode()){
				case QR_MODE_KANJI:
					break 2;
				case QR_MODE_NUM:
					$old_pos = $this->pos;
					$this->eatNum();
					if(($this->pos - $old_pos) > 3) {
						$this->pos = $old_pos;
						break 2;
					}
					break;
				case QR_MODE_AN:
					$old_pos = $this->pos;
					$this->eatAn();
					if(($this->pos - $old_pos) > 5) {
						$this->pos = $old_pos;
						break 2;
					}
					break;
				default:
					$this->pos++;
			}
		}
	}

	public function encodeString($text, $hint)
	{
		$this->dataStr = [];
		foreach(str_split($text)as $val){
			$this->dataStr[] = ord($val);
		}

		$this->dataStrLen = count($this->dataStr);

		if (($hint != QR_MODE_KANJI) && ($hint != -1)) {

			$this->streams[] = [$hint, $this->dataStrLen, $this->dataStr];

		} else {

			$this->hint = $hint;
			$this->pos = 0;

			while ($this->dataStrLen > $this->pos)
			{
				$prev = $this->pos;
				$mode = $this->identifyMode();

				switch ($mode) {
					case QR_MODE_NUM:
						$this->eatNum();
						break;
					case QR_MODE_AN:
						$this->eatAn();
						break;
					case QR_MODE_KANJI:
						$this->eatKanji();
						break;
					default:
						$mode = QR_MODE_8;
						$this->eat8();
				}

				$size = $this->pos - $prev;
				$this->streams[] = [$mode, $size, array_slice($this->dataStr, $prev, $size)];
			}
		}

		$package = $this->encodeStreams();
		return (new QRmask($package))->get();
	}
}

class QRmask {

	private $runLength;
	private $width;
	private $level;
	private $frame;
	private $masked;

	// See calcFormatInfo in tests/test_qrspec.c (orginal qrencode c lib)
	private $formatInfo = [
		[0x77c4, 0x72f3, 0x7daa, 0x789d, 0x662f, 0x6318, 0x6c41, 0x6976],
		[0x5412, 0x5125, 0x5e7c, 0x5b4b, 0x45f9, 0x40ce, 0x4f97, 0x4aa0],
		[0x355f, 0x3068, 0x3f31, 0x3a06, 0x24b4, 0x2183, 0x2eda, 0x2bed],
		[0x1689, 0x13be, 0x1ce7, 0x19d0, 0x0762, 0x0255, 0x0d0c, 0x083b]
	];

	function __construct(array $package)
	{
		$this->width = $package[3];
		$this->level = $package[4];
		# QR_SPEC_WIDTH_MAX = 177
		# Allocate only as much as we need
		$this->runLength = array_fill(0, $this->width + 1, 0);
		$this->frame = (new QRFrame())->getFrame($package);
	}

	private function writeFormatInformation($maskNo)
	{
		$format = $this->formatInfo[$this->level][$maskNo];

		$blacks = 0;

		for($i=0; $i<8; $i++) {
			if($format & 1) {
				$blacks += 2;
				$v = 133;
			} else {
				$v = 132;
			}

			$this->masked[8][$this->width - 1 - $i] = $v;
			if($i < 6) {
				$this->masked[$i][8] = $v;
			} else {
				$this->masked[$i + 1][8] = $v;
			}
			$format >>= 1;
		}

		for($i=0; $i<7; $i++) {
			if($format & 1) {
				$blacks += 2;
				$v = 133;
			} else {
				$v = 132;
			}

			$this->masked[$this->width - 7 + $i][8] = $v;
			if($i == 0) {
				$this->masked[8][7] = $v;
			} else {
				$this->masked[8][6 - $i] = $v;
			}

			$format >>= 1;
		}

		return $blacks;
	}

	private function makeMaskNo($maskNo)
	{
		$blacks = 0;

		for($y=0; $y<$this->width; $y++) {
			for($x=0; $x<$this->width; $x++) {
				if((($this->masked[$y][$x]) & 128) == false) { # 0x80

					switch($maskNo){
						case 0:
							$ret = ($x+$y)&1;
							break;
						case 1:
							$ret = ($y&1);
							break;
						case 2:
							$ret = ($x%3);
							break;
						case 3:
							$ret = ($x+$y)%3;
							break;
						case 4:
							$ret = ((int)($y/2)+(int)($x/3))&1;
							break;
						case 5:
							$ret = (($x*$y)&1)+($x*$y)%3;
							break;
						case 6:
							$ret = ((($x*$y)&1)+($x*$y)%3)&1;
							break;
						case 7:
							$ret = ((($x*$y)%3)+(($x+$y)&1))&1;
							break;
					}

					if ($ret == 0){
						$this->masked[$y][$x]++;
					}
				}
				$blacks += ($this->masked[$y][$x] & 1);
			}
		}

		$blacks += $this->writeFormatInformation($maskNo);

		return (int)(100 * $blacks / ($this->width * $this->width));
	}

	// ~500 calls per image
	private function calcN1N3($length)
	{
		$demerit = 0;

		for($i=0; $i<$length; $i++) {
			if($this->runLength[$i] >= 5) {
				$demerit += ($this->runLength[$i] - 2);
			}
		}

		for($i=3; $i<($length-2); $i+=2) {
			if($this->runLength[$i] % 3 == 0) {
				$fact = $this->runLength[$i] / 3;
				if(($this->runLength[$i-2] == $fact) &&
				   ($this->runLength[$i-1] == $fact) &&
				   ($this->runLength[$i+1] == $fact) &&
				   ($this->runLength[$i+2] == $fact)
				) {
					if(
						($this->runLength[$i-3] < 0) ||
						($this->runLength[$i-3] >= (4 * $fact)) ||
						(($i+3) >= $length) ||
						($this->runLength[$i+3] >= (4 * $fact))
					) {
						$demerit += 40;
					}
				}
			}
		}

		return $demerit;
	}

	private function evaluateSymbol()
	{
		$demerit = 0;

		for($y=0; $y<$this->width; $y++) {
			$head = 0;
			$this->runLength[0] = 1;

			$frameY = $this->masked[$y];

			if ($y > 0){
				$frameYM = $this->masked[$y-1];
			}

			if($frameY[0] & 1) {
				$this->runLength[0] = -1;
				$head = 1;
				$this->runLength[$head] = 1;
			}

			for($x=1; $x<$this->width; $x++) {
				if($y > 0) {
					$b22 = $frameY[$x] & $frameY[$x-1] & $frameYM[$x] & $frameYM[$x-1];
					$w22 = $frameY[$x] | $frameY[$x-1] | $frameYM[$x] | $frameYM[$x-1];
					if(($b22 | ($w22 ^ 1))&1) {
						$demerit += 3;
					}
				}

				if(($frameY[$x] ^ $frameY[$x-1]) & 1) {
					$head++;
					$this->runLength[$head] = 1;
				} else {
					$this->runLength[$head]++;
				}
			}

			$demerit += $this->calcN1N3($head+1);
		}

		for($x=0; $x<$this->width; $x++) {
			$head = 0;
			$this->runLength[0] = 1;

			if(($this->masked[0][$x]) & 1) {
				$this->runLength[0] = -1;
				$head = 1;
				$this->runLength[$head] = 1;
			}

			for($y=1; $y<$this->width; $y++) {
				if(($this->masked[$y][$x] ^ $this->masked[$y-1][$x]) & 1) {
					$head++;
					$this->runLength[$head] = 1;
				} else {
					$this->runLength[$head]++;
				}
			}

			$demerit += $this->calcN1N3($head+1);
		}

		return $demerit;
	}

	public function get()
	{
		$minDemerit = PHP_INT_MAX;

		$masks = [0,1,2,3,4,5,6,7];
		if (!defined("QR_ALL_MASKS")) {
			$masks = array_rand($masks, 2);
		}

		foreach($masks as $i) {

			$this->masked = $this->frame;

			$blacks = $this->makeMaskNo($i);

			$demerit = (int)(abs($blacks - 50)) * 2 + $this->evaluateSymbol();

			if($demerit < $minDemerit) {
				$minDemerit = $demerit;
				$bestMask = $this->masked;
			}
		}

		return $bestMask;
	}

}

class QRFrame {

	private $width;
	private $frame;
	private $version;
	private $x;
	private $y;
	private $dir;
	private $bit;
	private $new_frame;

	private $eccTable = [
		[[0, 0], [0, 0], [0, 0], [0, 0]],
		[[1, 0], [1, 0], [1, 0], [1, 0]], // 1
		[[1, 0], [1, 0], [1, 0], [1, 0]],
		[[1, 0], [1, 0], [2, 0], [2, 0]],
		[[1, 0], [2, 0], [2, 0], [4, 0]],
		[[1, 0], [2, 0], [2, 2], [2, 2]], // 5
		[[2, 0], [4, 0], [4, 0], [4, 0]],
		[[2, 0], [4, 0], [2, 4], [4, 1]],
		[[2, 0], [2, 2], [4, 2], [4, 2]],
		[[2, 0], [3, 2], [4, 4], [4, 4]],
		[[2, 2], [4, 1], [6, 2], [6, 2]], //10
		[[4, 0], [1, 4], [4, 4], [3, 8]],
		[[2, 2], [6, 2], [4, 6], [7, 4]],
		[[4, 0], [8, 1], [8, 4], [12, 4]],
		[[3, 1], [4, 5], [11, 5], [11, 5]],
		[[5, 1], [5, 5], [5, 7], [11, 7]], //15
		[[5, 1], [7, 3], [15, 2], [3, 13]],
		[[1, 5], [10, 1], [1, 15], [2, 17]],
		[[5, 1], [9, 4], [17, 1], [2, 19]],
		[[3, 4], [3, 11], [17, 4], [9, 16]],
		[[3, 5], [3, 13], [15, 5], [15, 10]], //20
		[[4, 4], [17, 0], [17, 6], [19, 6]],
		[[2, 7], [17, 0], [7, 16], [34, 0]],
		[[4, 5], [4, 14], [11, 14], [16, 14]],
		[[6, 4], [6, 14], [11, 16], [30, 2]],
		[[8, 4], [8, 13], [7, 22], [22, 13]], //25
		[[10, 2], [19, 4], [28, 6], [33, 4]],
		[[8, 4], [22, 3], [8, 26], [12, 28]],
		[[3, 10], [3, 23], [4, 31], [11, 31]],
		[[7, 7], [21, 7], [1, 37], [19, 26]],
		[[5, 10], [19, 10], [15, 25], [23, 25]], //30
		[[13, 3], [2, 29], [42, 1], [23, 28]],
		[[17, 0], [10, 23], [10, 35], [19, 35]],
		[[17, 1], [14, 21], [29, 19], [11, 46]],
		[[13, 6], [14, 23], [44, 7], [59, 1]],
		[[12, 7], [12, 26], [39, 14], [22, 41]], //35
		[[6, 14], [6, 34], [46, 10], [2, 64]],
		[[17, 4], [29, 14], [49, 10], [24, 46]],
		[[4, 18], [13, 32], [48, 14], [42, 32]],
		[[20, 4], [40, 7], [43, 22], [10, 67]],
		[[19, 6], [18, 31], [34, 34], [20, 61]] //40
	];

	private $alignmentPattern = [
		[0, 0],
		[0, 0], [18, 0], [22, 0], [26, 0], [30, 0],
		[34, 0], [22, 38], [24, 42], [26, 46], [28, 50],
		[30, 54], [32, 58], [34, 62], [26, 46], [26, 48],
		[26, 50], [30, 54], [30, 56], [30, 58], [34, 62],
		[28, 50], [26, 50], [30, 54], [28, 54], [32, 58],
		[30, 58], [34, 62], [26, 50], [30, 54], [26, 52],
		[30, 56], [34, 60], [30, 58], [34, 62], [30, 54],
		[24, 50], [28, 54], [32, 58], [26, 54], [30, 58]
	];

	private $versionPattern = [
		0x07c94, 0x085bc, 0x09a99, 0x0a4d3, 0x0bbf6, 0x0c762, 0x0d847, 0x0e60d,
		0x0f928, 0x10b78, 0x1145d, 0x12a17, 0x13532, 0x149a6, 0x15683, 0x168c9,
		0x177ec, 0x18ec4, 0x191e1, 0x1afab, 0x1b08e, 0x1cc1a, 0x1d33f, 0x1ed75,
		0x1f250, 0x209d5, 0x216f0, 0x228ba, 0x2379f, 0x24b0b, 0x2542e, 0x26a64,
		0x27541, 0x28c69
	];
	
	private $remainder_bits = [
		0,
		0,7,7,7,7,7,
		0,0,0,0,0,0,0,
		3,3,3,3,3,3,3,
		4,4,4,4,4,4,4,
		3,3,3,3,3,3,3,
		0,0,0,0,0,0
	];

	public function getFrame($package)
	{
		list($this->version, $dataLength, $ecc, $this->width, $level, $dataCode) = $package;

		$this->frame = $this->createFrame();

		$this->x = $this->width - 1;
		$this->y = $this->width - 1;
		$this->dir = -1;
		$this->bit = -1;

		list($b1,$b2) = $this->eccTable[$this->version][$level];

		$pad = floor($dataLength / ($b1 + $b2));
		$nroots = floor($ecc / ($b1 + $b2));
		$spec4 = ($b2 == 0) ? 0 : (floor($dataLength / ($b1 + $b2)) + 1);

		$dataLength = ($b1 * $pad) + ($b2 * $spec4);
		$eccLength = ($b1 + $b2) * $nroots;

		$ReedSolomon = new QRrsItem($dataCode, $dataLength, $b1, $pad, $nroots, $b2);

		// inteleaved data and ecc codes
		for($i=0; $i < ($dataLength + $eccLength); $i++) {
			$code = $ReedSolomon->getCode();
			$bit = 128;
			for($j=0; $j<8; $j++) {
				$this->setNext(2 | (($bit & $code) != 0));
				$bit = $bit >> 1;
			}
		}

		// remainder bits
		$j = $this->remainder_bits[$this->version];
		for($i=0; $i<$j; $i++) {
			$this->setNext(2);
		}

		return $this->frame;
	}

	private function setNext($val)
	{
		do {
			if($this->bit == -1) {
				$this->bit = 0;
				$this->frame[$this->y][$this->x] = $val;
				return;
			}

			if($this->bit == 0) {
				$this->x--;
				$this->bit++;
			} else {
				$this->x++;
				$this->y += $this->dir;
				$this->bit--;
			}

			if($this->dir < 0) {
				if($this->y < 0) {
					$this->y = 0;
					$this->x -= 2;
					$this->dir = 1;
					if($this->x == 6) {
						$this->x--;
						$this->y = 9;
					}
				}
			} else {
				if($this->y == $this->width) {
					$this->y--;
					$this->x -= 2;
					$this->dir = -1;
					if($this->x == 6) {
						$this->x--;
						$this->y -= 8;
					}
				}
			}

			if($this->x < 0 || $this->y < 0){
				throw QRException::Std('Invalid dimentions');
			}

		} while($this->frame[$this->y][$this->x] != 0);

		$this->frame[$this->y][$this->x] = $val;
	}

	private function putAlignmentMarker($ox, $oy)
	{
		$finder = [
			[161, 161, 161, 161, 161],
			[161, 160, 160, 160, 161],
			[161, 160, 161, 160, 161],
			[161, 160, 160, 160, 161],
			[161, 161, 161, 161, 161]
		];

		$yStart = $oy-2;
		$xStart = $ox-2;

		for($y=0; $y<5; $y++) {
			array_splice($this->new_frame[$yStart+$y], $xStart, 5, $finder[$y]);
		}
	}

	private function putAlignmentPattern()
	{
		if($this->version < 2){
			return;
		}

		list($v0, $v1) = $this->alignmentPattern[$this->version];

		$d = $v1 - $v0;
		if($d < 0) {
			$w = 2;
		} else {
			$w = floor(($this->width - $v0) / $d + 2);
		}

		if($w * $w - 3 == 1) {
			$this->putAlignmentMarker($v0, $v0);
			return;
		}

		$cx = $v0;
		for($x=1; $x<$w - 1; $x++) {
			$this->putAlignmentMarker(6, $cx);
			$this->putAlignmentMarker($cx, 6);
			$cx += $d;
		}

		$cy = $v0;
		for($y=0; $y<$w-1; $y++) {
			$cx = $v0;
			for($x=0; $x<$w-1; $x++) {
				$this->putAlignmentMarker($cx, $cy);
				$cx += $d;
			}
			$cy += $d;
		}
	}

	private function putFinderPattern($ox, $oy)
	{
		$finder = [
			[193, 193, 193, 193, 193, 193, 193],
			[193, 192, 192, 192, 192, 192, 193],
			[193, 192, 193, 193, 193, 192, 193],
			[193, 192, 193, 193, 193, 192, 193],
			[193, 192, 193, 193, 193, 192, 193],
			[193, 192, 192, 192, 192, 192, 193],
			[193, 193, 193, 193, 193, 193, 193]
		];
		
		for($y=0; $y<7; $y++) {
			array_splice($this->new_frame[$oy+$y], $ox, 7, $finder[$y]);
		}
	}

	private function createFrame()
	{
		$this->new_frame = array_fill(0, $this->width, array_fill(0, $this->width, 0));

		// Finder pattern
		$this->putFinderPattern(0, 0);
		$this->putFinderPattern($this->width - 7, 0);
		$this->putFinderPattern(0, $this->width - 7);

		// Separator
		$yOffset = $this->width - 7;

		for($y=0; $y<7; $y++) {
			$this->new_frame[$y][7] = 192;
			$this->new_frame[$y][$this->width - 8] = 192;
			$this->new_frame[$yOffset][7] = 192;
			$yOffset++;
		}

		$setPattern = [192,192,192,192,192,192,192,192];
		array_splice($this->new_frame[7], 0, 8, $setPattern);
		array_splice($this->new_frame[7], $this->width - 8, 8, $setPattern);
		array_splice($this->new_frame[$this->width - 8], 0, 8, $setPattern);

		// Format info
		$setPattern = [132,132,132,132,132,132,132,132,132];
		array_splice($this->new_frame[8], 0, 9, $setPattern);
		array_splice($this->new_frame[8], $this->width - 8, 8, array_slice($setPattern, 0, 8));

		$yOffset = $this->width - 8;

		for($y=0; $y<8; $y++,$yOffset++) {
			$this->new_frame[$y][8] = 132;
			$this->new_frame[$yOffset][8] = 132;
		}

		// Timing pattern
		for($i=1; $i<$this->width-15; $i++) {
			$val = (144 | ($i & 1));
			$this->new_frame[6][7+$i] = $val;
			$this->new_frame[7+$i][6] = $val;
		}

		// Alignment pattern
		$this->putAlignmentPattern();

		// Version information
		if($this->version >= 7) {

			$v = $this->versionPattern[$this->version -7];

			for($x=0; $x<6; $x++) {
				for($y=0; $y<3; $y++) {
					$val = (136 | ($v & 1));
					$yc = ($this->width - 11)+$y;
					$this->new_frame[$yc][$x] = $val;
					$this->new_frame[$x][$yc] = $val;
					$v = $v >> 1;
				}
			}
		}

		// and a little bit...
		$this->new_frame[$this->width - 8][8] = 129;

		return $this->new_frame;
	}

}

class QRrsItem {

	private $alpha_to;	// log lookup table 
	private $index_of;	// Antilog lookup table 
	private $genpoly;	// Generator polynomial 
	private $nroots;	// Number of generator roots = number of parity symbols 
	private $pad;		// Padding bytes in shortened block 
	private $parity;

	// RawCode
	private $blocks;
	private $count;
	private $b1;
	private $rsblocks = [];
	private $dataLength;

	function __construct(array $dataCode, int $dataLength, int $b1, int $pad, int $nroots, int $b2)
	{
		$this->count = 0;

		$this->b1 = $b1;
		$this->pad = $pad;
		$this->nroots = $nroots;
		$this->dataLength = $dataLength;
		$this->blocks = $this->b1 + $b2;

		// Check parameter ranges
		if($this->nroots >= 256){
			throw QRException::Std("Can't have more roots than symbol values!");
		}

		if($this->pad < 1){
			throw QRException::Std('Too much padding');
		}

		// Common code for intializing a Reed-Solomon control block (char or int symbols)
		// Copyright 2004 Phil Karn, KA9Q
		// May be used under the terms of the GNU Lesser General Public License (LGPL)
		$this->parity = array_fill(0, $this->nroots, 0);
		$this->genpoly = $this->parity;
		array_unshift($this->genpoly,1);
		$this->alpha_to = array_fill(0, 256, 0);
		$this->index_of = $this->alpha_to;

		// Generate Galois field lookup tables
		$this->index_of[0] = 255; // log(zero) = -inf
		$sr = 1;

		for($i = 0; $i < 255; $i++) {
			$this->index_of[$sr] = $i;
			$this->alpha_to[$i] = $sr;
			$sr <<= 1;
			if($sr & 256) {
				$sr ^= 285; # gfpoly
			}
			$sr &= 255;
		}

		if($sr != 1){
			throw QRException::Std('field generator polynomial is not primitive!');
		}

		/* Form RS code generator polynomial from its roots */
		for ($i = 0; $i < $this->nroots; $i++) {

			$this->genpoly[$i+1] = 1;

			// Multiply rs->genpoly[] by  @**(root + x)
			for ($j = $i; $j > 0; $j--) {
				if ($this->genpoly[$j] != 0) {
					$this->genpoly[$j] = $this->genpoly[$j-1] ^ $this->alpha_to[($this->index_of[$this->genpoly[$j]] + $i) % 255];
				} else {
					$this->genpoly[$j] = $this->genpoly[$j-1];
				}
			}
			// rs->genpoly[0] can never be zero
			$this->genpoly[0] = $this->alpha_to[($this->index_of[$this->genpoly[0]] + $i) % 255];
		}

		// convert rs->genpoly[] to index form for quicker encoding
		for ($i = 0; $i <= $this->nroots; $i++){
			$this->genpoly[$i] = $this->index_of[$this->genpoly[$i]];
		}

		for($i = 0; $i < $this->b1; $i++) { # rsBlockNum1
			$data = array_slice($dataCode, $this->pad * $i);
			$this->rsblocks[$i] = [$data, $this->encode_rs_char($data, $this->pad)];
		}

		if($b2 != 0) {
			for($i = 0; $i < $b2; $i++) {
				$inc = $this->pad + (($i == 0) ? 0 : 1);
				$data = array_slice($data, $inc);
				$this->rsblocks[$this->b1 + $i] = [$data, $this->encode_rs_char($data, $this->pad + 1)];
			}
		}
	}

	private function encode_rs_char($data, $pad)
	{
		$parity = $this->parity;

		for($i=0; $i< $pad; $i++) {
			$feedback = $this->index_of[$data[$i] ^ $parity[0]];
			if($feedback != 255) {
				for($j=1; $j < $this->nroots; $j++) {
					$parity[$j] ^= $this->alpha_to[($feedback + $this->genpoly[$this->nroots-$j]) % 255];
				}
				$parity[] = $this->alpha_to[($feedback + $this->genpoly[0]) % 255];
			} else {
				$parity[] = 0;
			}
			array_shift($parity);
		}

		return $parity;
	}

	public function getCode()
	{
		if($this->count < $this->dataLength) {
			$blockNo = $this->count % $this->blocks;
			$col = $this->count / $this->blocks;
			if($col >= $this->pad) { # was $this->rsblocks[0]->dataLength
				$blockNo += $this->b1;
			}
			$ret = $this->rsblocks[$blockNo][0][$col];
		} else {
			$blockNo = ($this->count - $this->dataLength) % $this->blocks;
			$col = ($this->count - $this->dataLength) / $this->blocks;
			$ret = $this->rsblocks[$blockNo][1][$col];
		}
		$this->count++;

		return $ret;
	}
}

?>