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
use QRCode\QRMask;
use QRCode\QRrsItem;

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
	private $margin;
	private $level;
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

	private function createImage($filename, $type, $quality = 90)
	{
		$h = count($this->encoded);
		$imgH = $h + 2 * $this->margin;
		$pixelPerPoint = min($this->size, $imgH);

		$base_image = imageCreate($imgH, $imgH);

		$white = imageColorAllocate($base_image,255,255,255);
		$black = imageColorAllocate($base_image,0,0,0);

		imagefill($base_image, 0, 0, $white);

		for($y=0; $y<$h; $y++) {
			for($x=0; $x<$h; $x++) {
				if ($this->encoded[$y][$x]&1) {
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

		$dataStr = [];
		foreach(str_split($text)as $val){
			$dataStr[] = ord($val);
		}

		$this->encoded = (new QRInput($this->level))->encodeString($dataStr, $hint);

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

	public function toFile(string $filename, int $quality = 90)
	{
		$ext = strtoupper(substr($filename, -3));
		if (($ext == "JPG") || ($ext == "PNG")) {
			$this->createImage($filename, $ext, $quality);
		} else {
			throw QRException::Std('file extension unsupported!');
		}
	}

	public function forWeb(string $ext, int $quality = 90)
	{
		$ext = strtoupper($ext);
		if (($ext == "JPG") || ($ext == "PNG")) {
			$this->createImage(false, $ext, $quality);
		} else {
			throw QRException::Std('file type unsupported!');
		}
	}
}

?>