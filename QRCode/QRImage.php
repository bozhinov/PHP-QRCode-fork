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
	
	private $frame;
	private $image;
	private $pixelPerPoint;
	private $outerFrame;
	
	function __construct(array $frame, int $pixelPerPoint = 4, int $outerFrame = 4)
	{
		$this->frame = $frame;
		$this->pixelPerPoint = $pixelPerPoint;
		$this->outerFrame = $outerFrame;
		
		$this->image = $this->createImage();
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

	private function createImage()
	{
		
		print_r($this->frame);
		$h = count($this->frame);
		$w = strlen($this->frame[0]);
		
		$imgW = $w + 2*$this->outerFrame;
		$imgH = $h + 2*$this->outerFrame;
		
		$base_image = imageCreate($imgW, $imgH);
		
		$white = imageColorAllocate($base_image,255,255,255);
		$black = imageColorAllocate($base_image,0,0,0);

		imagefill($base_image, 0, 0, $white);

		for($y=0; $y<$h; $y++) {
			for($x=0; $x<$w; $x++) {
				if ($this->frame[$y][$x] == '1') {
					imageSetPixel($base_image,$x+$this->outerFrame,$y+$this->outerFrame,$black);
				}
			}
		}
		
		$target_image = imageCreate($imgW * $this->pixelPerPoint, $imgH * $this->pixelPerPoint);
		imageCopyResized($target_image, $base_image, 0, 0, 0, 0, $imgW * $this->pixelPerPoint, $imgH * $this->pixelPerPoint, $imgW, $imgH);
		imageDestroy($base_image);
		
		return $target_image;
	}
}