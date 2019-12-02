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

namespace QRCode;

use QRCode\QRException;
use QRCode\QRFrame;
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
	private $h;
	private $margin;
	private $level;
	private $target_image;
	private $encoded = [];

	function __construct(array $config = [])
	{
		$this->level  = (isset($config['level']))  ? $config['level']  : 0;
		$this->size   = (isset($config['size']))   ? $config['size']   : 3;
		$this->margin = (isset($config['margin'])) ? $config['margin'] : 4;

		if (!in_array($this->level,[0,1,2,3])){
			throw QRException::Std('unknown error correction level');
		}
	}

	function __destruct()
	{
		if (is_resource($this->target_image)){
			imagedestroy($this->target_image);
		}
	}

	private function createImage($img_resource = NULL, $startX = NULL, $startY = NULL)
	{
		$h = count($this->encoded);
		$imgH = $h + 2 * $this->margin;

		$base_image = imagecreate($imgH, $imgH);

		$white = imagecolorallocate($base_image,255,255,255);
		$black = imagecolorallocate($base_image,0,0,0);

		imagefill($base_image, 0, 0, $white);

		for($y=0; $y<$h; $y++) {
			for($x=0; $x<$h; $x++) {
				if ($this->encoded[$y][$x]&1) {
					imagesetpixel($base_image,$x+$this->margin,$y+$this->margin,$black);
				}
			}
		}

		$pixelPerPoint = min($this->size, $imgH);
		$target_h = $imgH * $pixelPerPoint;
		$this->h = $target_h;
		if (is_null($img_resource)){
			$this->target_image = imagecreate($target_h, $target_h);
			imagecopyresized($this->target_image, $base_image, 0, 0, 0, 0, $target_h, $target_h, $imgH, $imgH);
		} else {
			imagecopyresized($img_resource, $base_image, $startX, $startY, 0, 0, $target_h, $target_h, $imgH, $imgH);
		}

		imagedestroy($base_image);
	}

	private function toPNG($filename)
	{
		if(is_null($filename)) {
			header("Content-type: image/png");
		}
		imagepng($this->target_image, $filename);
	}

	private function toJPG($filename, $quality)
	{
		if(is_null($filename)) {
			header("Content-type: image/jpeg");
		}
		imagejpeg($this->target_image, $filename, $quality);
	}

	private function toSVG($filename)
	{
		$content = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
<svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="'.$this->h.'px" height="'.$this->h.'px" viewBox="0 0 '.$this->h.' '.$this->h.'" enable-background="new 0 0 '.$this->h.' '.$this->h.'" xml:space="preserve">
<image id="image0" width="'.$this->h.'" height="'.$this->h.'" x="0" y="0" href="data:image/png;base64,'.$this->toBase64().'" />
</svg>';

		if(is_null($filename)) {
			header("Content-type: image/svg+xml");
			return $content;
		} else {
			file_put_contents($filename, $content);
		}
	}

	public function config(array $config)
	{
		$this->__construct($config);
		$this->encoded = [];
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

	public function toBase64()
	{
		ob_start();
		imagePng($this->target_image);
		$imagedata = ob_get_contents();
		ob_end_clean();

		return base64_encode($imagedata);
	}

	public function toASCII()
	{
		$h = count($this->encoded);
		$ascii = "";

		for($y=0; $y<$h; $y++) {
			for($x=0; $x<$h; $x++) {
				if ($this->encoded[$y][$x]&1) {
					$ascii .= chr(219).chr(219);
				} else {
					$ascii .= "  ";
				}
			}
			$ascii .= "\r\n";
		}

		return $ascii;
	}

	public function toArray()
	{
		return $this->encoded;
	}

	public function fromArray(array $encoded)
	{
		$this->encoded = $encoded;
	}

	public function forPChart(\pChart\pDraw $MyPicture, $X = 0, $Y = 0)
	{
		$this->createImage($MyPicture->gettheImage(), $X, $Y);
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

?>