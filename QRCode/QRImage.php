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
	private $pixelPerPoint;
	private $outerFrame;
	private $saveandprint;
	
	function __construct($frame, $pixelPerPoint = 4, $outerFrame = 4,$saveandprint=FALSE)
	{
		$this->frame = $frame;
		$this->pixelPerPoint = $pixelPerPoint;
		$this->outerFrame = $outerFrame;
		$this->saveandprint = $saveandprint;
	}
	
	public function png($filename = false)
	{
		$image = $this->image();
		
		if ($filename === false) {
			Header("Content-type: image/png");
			ImagePng($image);
		} else {
			if($this->saveandprint){
				ImagePng($image, $filename);
				header("Content-type: image/png");
				ImagePng($image);
			}else{
				ImagePng($image, $filename);
			}
		}
		
		ImageDestroy($image);
	}

	/* $pixelPerPoint = 8 default */
	public function jpg($filename = false, $q = 85) 
	{
		$image = $this->image();
		
		if ($filename === false) {
			Header("Content-type: image/jpeg");
			ImageJpeg($image, null, $this->q);
		} else {
			ImageJpeg($image, $filename, $this->q);
		}
		
		ImageDestroy($image);
	}

	private function image()
	{
		$h = count($this->frame);
		$w = strlen($this->frame[0]);
		
		$imgW = $w + 2*$this->outerFrame;
		$imgH = $h + 2*$this->outerFrame;
		
		$base_image =ImageCreate($imgW, $imgH);
		
		$col[0] = ImageColorAllocate($base_image,255,255,255);
		$col[1] = ImageColorAllocate($base_image,0,0,0);

		imagefill($base_image, 0, 0, $col[0]);

		for($y=0; $y<$h; $y++) {
			for($x=0; $x<$w; $x++) {
				if ($this->frame[$y][$x] == '1') {
					ImageSetPixel($base_image,$x+$this->outerFrame,$y+$this->outerFrame,$col[1]);
				}
			}
		}
		
		$target_image =ImageCreate($imgW * $this->pixelPerPoint, $imgH * $this->pixelPerPoint);
		ImageCopyResized($target_image, $base_image, 0, 0, 0, 0, $imgW * $this->pixelPerPoint, $imgH * $this->pixelPerPoint, $imgW, $imgH);
		ImageDestroy($base_image);
		
		return $target_image;
	}
}