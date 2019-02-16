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

class QRFrame {

	private $width;
	private $frame;
	private $version;
	private $x;
	private $y;
	private $dir;
	private $bit;
	
	// those two came from the QRSpec class
	private $new_frame;
	private $tools;

	// Error correction code
	// Table of the error correction code (Reed-Solomon block)
	// See Table 12-16 (pp.30-36), JIS X0510:2004.
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

	/** Alignment pattern
	* Positions of alignment patterns.
	* This array includes only the second and the third position of the 
	* alignment patterns. Rest of them can be calculated from the distance 
	* between them.
	* See Table 1 in Appendix E (pp.71) of JIS X0510:2004.
	*/
	private $alignmentPattern = [
		[0, 0],
		[0, 0], [18, 0], [22, 0], [26, 0], [30, 0], // 1- 5
		[34, 0], [22, 38], [24, 42], [26, 46], [28, 50], // 6-10
		[30, 54], [32, 58], [34, 62], [26, 46], [26, 48], //11-15
		[26, 50], [30, 54], [30, 56], [30, 58], [34, 62], //16-20
		[28, 50], [26, 50], [30, 54], [28, 54], [32, 58], //21-25
		[30, 58], [34, 62], [26, 50], [30, 54], [26, 52], //26-30
		[30, 56], [34, 60], [30, 58], [34, 62], [30, 54], //31-35
		[24, 50], [28, 54], [32, 58], [26, 54], [30, 58] //35-40
	];
	
	// Version information pattern (BCH coded).
	// See Table 1 in Appendix D (pp.68) of JIS X0510:2004.
	// size: [QR_SPEC_VERSION_MAX - 6]
	private $versionPattern = [
		0x07c94, 0x085bc, 0x09a99, 0x0a4d3, 0x0bbf6, 0x0c762, 0x0d847, 0x0e60d,
		0x0f928, 0x10b78, 0x1145d, 0x12a17, 0x13532, 0x149a6, 0x15683, 0x168c9,
		0x177ec, 0x18ec4, 0x191e1, 0x1afab, 0x1b08e, 0x1cc1a, 0x1d33f, 0x1ed75,
		0x1f250, 0x209d5, 0x216f0, 0x228ba, 0x2379f, 0x24b0b, 0x2542e, 0x26a64,
		0x27541, 0x28c69
	];

	function __construct($version)
	{
		if($version < 1 || $version > QR_SPEC_VERSION_MAX){
			throw QRException::Std('Version invalid');
		}
		
		$this->tools = new QRTools();
		
		$this->width = $this->tools->getWidth($version);
		$this->frame = $this->createFrame($version);	
		$this->version = $version;

		$this->x = $this->width - 1;
		$this->y = $this->width - 1;
		$this->dir = -1;
		$this->bit = -1;
	}

	public function getFrame($dataCode, $level)
	{
		$spec = $this->getEccSpec($this->version, $level);
		
		$dataLength = ($spec[0] * $spec[1]) + ($spec[3] * $spec[4]);
		$eccLength = ($spec[0] + $spec[3]) * $spec[2];

		$raw = new QRrawcode($dataCode, $dataLength, $eccLength, $spec);
		
		// inteleaved data and ecc codes
		for($i=0; $i < ($dataLength + $eccLength); $i++) {
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
		$j = $this->getRemainder($this->version);
		for($i=0; $i<$j; $i++) {
			$addr = $this->next();
			$this->setFrameAt($addr, 0x02);
		}

		return $this->frame;
	}
	
	private function getRemainder($version)
	{
		return $this->tools->capacity[$version][QR_CAP_REMINDER];
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
	
	private function getVersionPattern($version)
	{
		if($version < 7 || $version > QR_SPEC_VERSION_MAX){
			return 0;
		}

		return $this->versionPattern[$version -7];
	}
	
	private function getECCLength($version, $level)
	{
		return $this->tools->capacity[$version][QR_CAP_EC][$level];
	}
	
	private function set_qrstr($x, $y, $repl, $replLen = false) 
	{
		if ($replLen !== false){
			$str1 = substr($repl,0,$replLen);
			$str2 = $replLen;
		} else {
			$str1 = $repl;
			$str2 = strlen($repl);
		}

		$this->new_frame[$y] = substr_replace($this->new_frame[$y], $str1, $x, $str2);
	}
	
	/** 
	 * Put an alignment marker.
	 * @param width
	 * @param ox,oy center coordinate of the pattern
	 */
	private function putAlignmentMarker($ox, $oy)
	{
		$finder = [
			"\xa1\xa1\xa1\xa1\xa1",
			"\xa1\xa0\xa0\xa0\xa1",
			"\xa1\xa0\xa1\xa0\xa1",
			"\xa1\xa0\xa0\xa0\xa1",
			"\xa1\xa1\xa1\xa1\xa1"
		];
		
		$yStart = $oy-2;
		$xStart = $ox-2;
		
		for($y=0; $y<5; $y++) {
			$this->set_qrstr($xStart, $yStart+$y, $finder[$y]);
		}
	}

	private function putAlignmentPattern($version, $width)
	{
		if($version < 2){
			return;
		}

		$d = $this->alignmentPattern[$version][1] - $this->alignmentPattern[$version][0];
		if($d < 0) {
			$w = 2;
		} else {
			$w = (int)(($width - $this->alignmentPattern[$version][0]) / $d + 2);
		}

		if($w * $w - 3 == 1) {
			$x = $this->alignmentPattern[$version][0];
			$y = $this->alignmentPattern[$version][0];
			$this->putAlignmentMarker($x, $y);
			return;
		}

		$cx = $this->alignmentPattern[$version][0];
		for($x=1; $x<$w - 1; $x++) {
			$this->putAlignmentMarker(6, $cx);
			$this->putAlignmentMarker($cx, 6);
			$cx += $d;
		}

		$cy = $this->alignmentPattern[$version][0];
		for($y=0; $y<$w-1; $y++) {
			$cx = $this->alignmentPattern[$version][0];
			for($x=0; $x<$w-1; $x++) {
				$this->putAlignmentMarker($cx, $cy);
				$cx += $d;
			}
			$cy += $d;
		}
	}
	
	/** 
	 * Put a finder pattern.
	 * @param width
	 * @param ox,oy upper-left coordinate of the pattern
	 */
	private function putFinderPattern($ox, $oy)
	{
		$finder = [
			"\xc1\xc1\xc1\xc1\xc1\xc1\xc1",
			"\xc1\xc0\xc0\xc0\xc0\xc0\xc1",
			"\xc1\xc0\xc1\xc1\xc1\xc0\xc1",
			"\xc1\xc0\xc1\xc1\xc1\xc0\xc1",
			"\xc1\xc0\xc1\xc1\xc1\xc0\xc1",
			"\xc1\xc0\xc0\xc0\xc0\xc0\xc1",
			"\xc1\xc1\xc1\xc1\xc1\xc1\xc1"
		];
		
		for($y=0; $y<7; $y++) {
			$this->set_qrstr($ox, $oy+$y, $finder[$y]);
		}
	}

	private function createFrame($version)
	{
		$width = $this->tools->capacity[$version][QR_CAP_WIDTH];
		$frameLine = str_repeat ("\0", $width);
		$this->new_frame = array_fill(0, $width, $frameLine);

		// Finder pattern
		$this->putFinderPattern(0, 0);
		$this->putFinderPattern($width - 7, 0);
		$this->putFinderPattern(0, $width - 7);
		
		// Separator
		$yOffset = $width - 7;
		
		for($y=0; $y<7; $y++) {
			$this->new_frame[$y][7] = "\xc0";
			$this->new_frame[$y][$width - 8] = "\xc0";
			$this->new_frame[$yOffset][7] = "\xc0";
			$yOffset++;
		}
		
		$setPattern = str_repeat("\xc0", 8);
		
		$this->set_qrstr(0, 7, $setPattern);
		$this->set_qrstr($width-8, 7, $setPattern);
		$this->set_qrstr(0, $width - 8, $setPattern);
	
		// Format info
		$setPattern = str_repeat("\x84", 9);
		$this->set_qrstr(0, 8, $setPattern);
		$this->set_qrstr($width - 8, 8, $setPattern, 8);
		
		$yOffset = $width - 8;

		for($y=0; $y<8; $y++,$yOffset++) {
			$this->new_frame[$y][8] = "\x84";
			$this->new_frame[$yOffset][8] = "\x84";
		}

		// Timing pattern  
		for($i=1; $i<$width-15; $i++) {
			$this->new_frame[6][7+$i] = chr(0x90 | ($i & 1));
			$this->new_frame[7+$i][6] = chr(0x90 | ($i & 1));
		}
		
		// Alignment pattern  
		$this->putAlignmentPattern($version, $width);
		
		// Version information 
		if($version >= 7) {
			$vinf = $this->getVersionPattern($version);

			$v = $vinf;
			
			for($x=0; $x<6; $x++) {
				for($y=0; $y<3; $y++) {
					$this->new_frame[($width - 11)+$y][$x] = chr(0x88 | ($v & 1));
					$v = $v >> 1;
				}
			}

			$v = $vinf;
			for($y=0; $y<6; $y++) {
				for($x=0; $x<3; $x++) {
					$this->new_frame[$y][$x+($width - 11)] = chr(0x88 | ($v & 1));
					$v = $v >> 1;
				}
			}
		}

		// and a little bit...  
		$this->new_frame[$width - 8][8] = "\x81";
		
		return $this->new_frame;
	}

	private function getEccSpec($version, $level)
	{
		$spec = [0,0,0,0,0];

		$b1   = $this->eccTable[$version][$level][0];
		$b2   = $this->eccTable[$version][$level][1];
		$data = $this->tools->getDataLength($version, $level);
		$ecc  = $this->getECCLength($version, $level);

		if($b2 == 0) {
			$spec[0] = $b1;
			$spec[1] = (int)($data / $b1);
			$spec[2] = (int)($ecc / $b1);
			$spec[3] = 0; 
			$spec[4] = 0;
		} else {
			$spec[0] = $b1;
			$spec[1] = (int)($data / ($b1 + $b2));
			$spec[2] = (int)($ecc  / ($b1 + $b2));
			$spec[3] = $b2;
			$spec[4] = $spec[1] + 1;
		}
		
		return $spec;
	}

}

?>