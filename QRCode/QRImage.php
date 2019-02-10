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

class QRimage {

	private $image;
	
	function __construct(array $frame, int $pixelPerPoint = 4, int $outerFrame = 4)
	{		
		$this->image = $this->createImage($frame, $pixelPerPoint, $outerFrame);
	}
	
	function __destruct(){
		if (is_resource($this->image)){
			imageDestroy($this->image);
		}
	}
	
	public function png($filename = false, bool $saveandprint = false)
	{		
		if ($filename === false) {
			
			header("Content-type: image/png");
			imagePng($this->image);
			
		} else {
			
			imagePng($this->image, $filename);
			
			if ($saveandprint) {
				imagePng($this->image, $filename);
				header("Content-type: image/png");
				imagePng($this->image);
			}
		}
	}

	/* $pixelPerPoint = 8 default */
	public function jpg($filename = false, int $q = 85, bool $saveandprint = false) 
	{		
		if ($filename === false) {
			
			header("Content-type: image/jpeg");
			imageJpeg($this->image, null, $q);
			
		} else {
			
			imageJpeg($this->image, $filename, $q);
			
			if($saveandprint){
				header("Content-type: image/jpeg");
				imageJpeg($this->image, null, $q);
			}
		}
	}

	private function createImage($frame, $pixelPerPoint, $outerFrame)
	{
		$h = count($frame);
		$w = strlen($frame[0]);
		
		$imgW = $w + 2*$outerFrame;
		$imgH = $h + 2*$outerFrame;
		
		$base_image = imageCreate($imgW, $imgH);
		
		$white = imageColorAllocate($base_image,255,255,255);
		$black = imageColorAllocate($base_image,0,0,0);

		imagefill($base_image, 0, 0, $white);

		for($y=0; $y<$h; $y++) {
			for($x=0; $x<$w; $x++) {
				if ($frame[$y][$x] == '1') {
					imageSetPixel($base_image,$x+$outerFrame,$y+$outerFrame,$black);
				}
			}
		}
		
		$target_image = imageCreate($imgW * $pixelPerPoint, $imgH * $pixelPerPoint);
		imageCopyResized($target_image, $base_image, 0, 0, 0, 0, $imgW * $pixelPerPoint, $imgH * $pixelPerPoint, $imgW, $imgH);
		imageDestroy($base_image);
		
		return $target_image;
	}
}