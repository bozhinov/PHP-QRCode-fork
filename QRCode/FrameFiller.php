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

class FrameFiller {

	private $width;
	private $frame;
	private $version;
	private $x;
	private $y;
	private $dir;
	private $bit;

	private $QRspec;

	function __construct($version)
	{
		$this->QRspec = new QRspec();
		
		$this->width = $this->QRspec->getWidth($version);
		$this->frame = $this->QRspec->newFrame($version);
		$this->version = $version;

		$this->x = $this->width - 1;
		$this->y = $this->width - 1;
		$this->dir = -1;
		$this->bit = -1;
	}

	public function getFrame($dataCode, $level)
	{
		$spec = $this->QRspec->getEccSpec($this->version, $level);

		$raw = new QRrawcode($dataCode, $spec);
		
		// inteleaved data and ecc codes
		for($i=0; $i<$raw->dataLength + $raw->eccLength; $i++) {
			$code = $raw->getCode();
			$bit = 0x80;
			for($j=0; $j<8; $j++) {
				$addr = $this->next();
				$this->setFrameAt($addr, 0x02 | (($bit & $code) != 0));
				$bit = $bit >> 1;
			}
		}

		unset($raw);
		
		// remainder bits
		$j = $this->QRspec->getRemainder($this->version);
		for($i=0; $i<$j; $i++) {
			$addr = $this->next();
			$this->setFrameAt($addr, 0x02);
		}

		return $this->frame;
	}

	private function setFrameAt($at, $val)
	{
		$this->frame[$at['y']][$at['x']] = chr($val);
	}

	private function getFrameAt($at)  # UNSED
	{
		return ord($this->frame[$at['y']][$at['x']]);
	}

	private function next()
	{
		do {
		
			if($this->bit == -1) {
				$this->bit = 0;
				return ['x'=>$this->x, 'y'=>$this->y];
			}

			$x = $this->x;
			$y = $this->y;
			$w = $this->width;

			if($this->bit == 0) {
				$x--;
				$this->bit++;
			} else {
				$x++;
				$y += $this->dir;
				$this->bit--;
			}

			if($this->dir < 0) {
				if($y < 0) {
					$y = 0;
					$x -= 2;
					$this->dir = 1;
					if($x == 6) {
						$x--;
						$y = 9;
					}
				}
			} else {
				if($y == $w) {
					$y = $w - 1;
					$x -= 2;
					$this->dir = -1;
					if($x == 6) {
						$x--;
						$y -= 8;
					}
				}
			}
			if($x < 0 || $y < 0){
				return null;
			}

			$this->x = $x;
			$this->y = $y;

		} while(ord($this->frame[$y][$x]) & 0x80);

		return ['x'=>$x, 'y'=>$y];
	}

}

?>