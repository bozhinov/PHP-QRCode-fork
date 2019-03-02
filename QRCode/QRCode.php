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
 * Last update - 02.2019
 *
 */

namespace QRCode;

use QRCode\QRException;
use QRCode\QRFrame;
use QRCode\QRTools;
use QRCode\QRInput;
use QRCode\QRInputItem;
use QRCode\QRMask;
use QRCode\QRrsItem;

// QRMask
define('QR_N1', 3);
define('QR_N2', 3);
define('QR_N3', 40);
define('QR_N4', 10);

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

define('QR_SPEC_VERSION_MAX', 40);
define('QR_SPEC_WIDTH_MAX', 177);
define('QR_CAP_WIDTH', 0);
define('QR_CAP_WORDS', 1);
define('QR_CAP_REMINDER', 2);
define('QR_CAP_EC', 3);

class QRcode {

	private $size;
	private $margin;
	private $level;

	function __construct(int $level = QR_ECLEVEL_L, int $size = 3, int $margin = 4)
	{
		$this->size = $size;
		$this->level = $level;
		$this->margin = $margin;

		if (!in_array($level,[0,1,2,3])){
			throw QRException::Std('unknown error correction level');
		}
	}

	private function createImage($frame, $filename, $type, $quality = 90)
	{
		$h = count($frame);
		$imgH = $h + 2 * $this->margin;
		$pixelPerPoint = min($this->size, $imgH);

		$base_image = imageCreate($imgH, $imgH);

		$white = imageColorAllocate($base_image,255,255,255);
		$black = imageColorAllocate($base_image,0,0,0);

		imagefill($base_image, 0, 0, $white);

		for($y=0; $y<$h; $y++) {
			for($x=0; $x<$h; $x++) {
				if ($frame[$y][$x]&1) {
					imageSetPixel($base_image,$x+$this->margin,$y+$this->margin,$black);
				}
			}
		}

		$target_image = imageCreate($imgH * $pixelPerPoint, $imgH * $pixelPerPoint);
		imageCopyResized($target_image, $base_image, 0, 0, 0, 0, $imgH * $pixelPerPoint, $imgH * $pixelPerPoint, $imgH, $imgH);
		imageDestroy($base_image);
		
		if ((php_sapi_name() == "cli") || ($filename != false)) {
			if ($type == "PNG"){
				imagePng($target_image, $filename);
			} elseif ($type == "JPG"){
				imageJpeg($target_image, $filename, $quality);
			}
		} else {
			if ($type == "PNG"){
				header("Content-type: image/png");
				imagePng($target_image);
			} elseif ($type == "JPG"){
				header("Content-type: image/jpeg");
				imageJpeg($target_image, null, $quality);
			}
		}

		imageDestroy($target_image);
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

		if (!in_array($this->level,[0,1,2,3])){
			throw QRException::Std('unknown error correction level');
		}
	}

	public function raw(string $text, int $hint = -1, bool $casesensitive = true)
	{
		if($text == '\0' || $text == '') {
			throw QRException::Std('empty string!');
		}

		if (!in_array($hint,[-1,0,1,2,3])){
			throw QRException::Std('unknown hint');
		}

		$dataStr = [];
		foreach(str_split($text)as $val){
			$dataStr[] = ord($val);
		}

		return (new QRInput($this->level))->encodeString($dataStr, $casesensitive, $hint);
	}

	public function jpg(string $text, $filename, int $quality = 90, int $hint = -1, bool $casesensitive = true)
	{
		$encoded = $this->raw($text, $hint, $casesensitive);

		$this->createImage($encoded, $filename, "JPG", $quality);
	}

	public function png(string $text, $filename, int $hint = -1, bool $casesensitive = true)
	{
		$encoded = $this->raw($text, $hint, $casesensitive);

		$this->createImage($encoded, $filename, "PNG");
	}
}

?>