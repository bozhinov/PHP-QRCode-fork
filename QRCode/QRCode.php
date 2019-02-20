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

// QRinputItem
define('QR_STRUCTURE_HEADER_BITS', 20);

// Encoding modes
define('QR_MODE_NUL', -1);
define('QR_MODE_NUM', 0);
define('QR_MODE_AN', 1);
define('QR_MODE_8', 2);
define('QR_MODE_KANJI', 3);
define('QR_MODE_STRUCTURE', 4);

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
		$this->hint = $hint;
		$this->casesensitive = $casesensitive;

		if (!in_array($level,[0,1,2,3])){
			throw QRException::Std('unknown error correction level');
		}
		
		$this->level = $level;
	}

	private function createImage($frame, $filename, $type, $quality = 90)
	{
		$h = count($frame);
		$w = strlen($frame[0]);
		
		$imgW = $w + 2*$this->margin;
		$imgH = $h + 2*$this->margin;
		
		$base_image = imageCreate($imgW, $imgH);
		
		$white = imageColorAllocate($base_image,255,255,255);
		$black = imageColorAllocate($base_image,0,0,0);

		imagefill($base_image, 0, 0, $white);

		for($y=0; $y<$h; $y++) {
			for($x=0; $x<$w; $x++) {
				if ($frame[$y][$x] == '1') {
					imageSetPixel($base_image,$x+$this->margin,$y+$this->margin,$black);
				}
			}
		}

		$maxSize = count($frame)+2*$this->margin;
		$pixelPerPoint = min($this->size, $maxSize);
		$target_image = imageCreate($imgW * $pixelPerPoint, $imgH * $pixelPerPoint);
		imageCopyResized($target_image, $base_image, 0, 0, 0, 0, $imgW * $pixelPerPoint, $imgH * $pixelPerPoint, $imgW, $imgH);
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
	
	private function encodeString($text)
	{
		return (new QRsplit($this->casesensitive, $this->hint, 1, $this->level))->splitString($text);
	}
	
	private function encodeString8bit($text)
	{
		$input = new QRinput(1, $this->level);

		$input->append(QR_MODE_8, strlen($text), $text);

		return $input->encodeMask();
	}
	
	private function binarize($frame)
	{
		$len = count($frame);
		foreach ($frame as &$frameLine) {

			for($i=0; $i<$len; $i++) {
				$frameLine[$i] = (ord($frameLine[$i])&1)?'1':'0';
			}
		}

		return $frame;
	}
	
	public function raw(string $text)
	{
		if($text == '\0' || $text == '') {
			throw QRException::Std('empty string!');
		}
		
		if($this->hint == QR_MODE_8) { # around 70 chars
			$encoded = $this->encodeString8bit($text);
		} else {
			$encoded = $this->encodeString($text);
		}

		return $encoded;
	}

	public function jpg(string $text, $filename, int $quality = 90)
	{
		$encoded = $this->raw($text);

		$tab = $this->binarize($encoded);

		$this->createImage($tab, $filename, "JPG", $quality);
	}
	
	public function png(string $text, $filename)
	{
		$encoded = $this->raw($text);

		$tab = $this->binarize($encoded);

		$this->createImage($tab, $filename, "PNG");
	}
}

?>