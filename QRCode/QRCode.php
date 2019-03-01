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
use QRCode\QRSplit;

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

	private $casesensitive;
	private $size;
	private $margin;
	private $hint;
	private $level;

	function __construct(int $level = QR_ECLEVEL_L, int $size = 3, int $margin = 4, int $hint = QR_MODE_NUM, bool $casesensitive = true)
	{
		$this->size = $size;
		$this->margin = $margin;
		$this->casesensitive = $casesensitive;

		$valid = [0,1,2,3];
		if (!in_array($level,$valid)){
			throw QRException::Std('unknown error correction level');
		}

		if (!in_array($hint,$valid)){
			throw QRException::Std('unknown hint');
		}

		$this->level = $level;
		$this->hint = $hint;
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

	private function encodeString($dataStr)
	{
		return (new QRsplit($this->casesensitive, $this->hint, $this->level))->splitString($dataStr);
	}

	private function encodeString8bit($dataStr)
	{
		$input = new QRinput($this->level);

		$input->append(QR_MODE_8, count($dataStr), $dataStr);

		return $input->encodeMask();
	}

	public function raw(string $text)
	{
		if($text == '\0' || $text == '') {
			throw QRException::Std('empty string!');
		}

		$dataStr = [];
		foreach(str_split($text)as $val){
			$dataStr[] = ord($val);
		}

		if($this->hint == QR_MODE_8) {
			$encoded = $this->encodeString8bit($dataStr);
		} else {
			$encoded = $this->encodeString($dataStr);
		}

		return $encoded;
	}

	public function jpg(string $text, $filename, int $quality = 90)
	{
		$encoded = $this->raw($text);

		$this->createImage($encoded, $filename, "JPG", $quality);
	}

	public function png(string $text, $filename)
	{
		$encoded = $this->raw($text);

		$this->createImage($encoded, $filename, "PNG");
	}
}

?>