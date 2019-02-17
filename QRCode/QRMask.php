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

class QRmask {

	private $runLength = [];
	private $width;
	private $level;
	private $frame;
	private $empty_grid;

	// See calcFormatInfo in tests/test_qrspec.c (orginal qrencode c lib)
	private $formatInfo = [
		[0x77c4, 0x72f3, 0x7daa, 0x789d, 0x662f, 0x6318, 0x6c41, 0x6976],
		[0x5412, 0x5125, 0x5e7c, 0x5b4b, 0x45f9, 0x40ce, 0x4f97, 0x4aa0],
		[0x355f, 0x3068, 0x3f31, 0x3a06, 0x24b4, 0x2183, 0x2eda, 0x2bed],
		[0x1689, 0x13be, 0x1ce7, 0x19d0, 0x0762, 0x0255, 0x0d0c, 0x083b]
	];
	
	function __construct(array $dataCode, int $width, int $level, int $version)
	{
		$this->runLength = array_fill(0, QR_SPEC_WIDTH_MAX + 1, 0);
		$this->width = $width;
		$this->level = $level;
		$this->frame = (new QRFrame($version, $this->level))->getFrame($dataCode);
		
		$this->empty_grid = array_fill(0, $width, array_fill(0, $width, 0));
	}
	
	private function getFormatInfo($mask, $level)
	{
		if($mask < 0 || $mask > 7){
			return 0;
		}

		if($level < 0 || $level > 3){
			return 0;
		}

		return $this->formatInfo[$level][$mask];
	}

	private function writeFormatInformation(&$frame, $mask)
	{
		$blacks = 0;
		$format = $this->getFormatInfo($mask, $this->level);

		for($i=0; $i<8; $i++) {
			if($format & 1) {
				$blacks += 2;
				$v = 0x85;
			} else {
				$v = 0x84;
			}
			
			$frame[8][$this->width - 1 - $i] = chr($v);
			if($i < 6) {
				$frame[$i][8] = chr($v);
			} else {
				$frame[$i + 1][8] = chr($v);
			}
			$format = $format >> 1;
		}
		
		for($i=0; $i<7; $i++) {
			if($format & 1) {
				$blacks += 2;
				$v = 0x85;
			} else {
				$v = 0x84;
			}
			
			$frame[$this->width - 7 + $i][8] = chr($v);
			if($i == 0) {
				$frame[8][7] = chr($v);
			} else {
				$frame[8][6 - $i] = chr($v);
			}
			
			$format = $format >> 1;
		}

		return $blacks;
	}

	private function maskByNum($x, $y, $num)
	{
		$ret = false;
		
		switch($num){
			case 0:
				$ret = ($x+$y)&1;
				break;
			case 1:
				$ret = ($y&1);
				break;
			case 2:
				$ret = ($x%3);
				break;
			case 3:
				$ret = ($x+$y)%3;
				break;
			case 4:
				$ret = (floor($y/2)+floor($x/3))&1;
				break;
			case 5:
				$ret = (($x*$y)&1)+($x*$y)%3;
				break;
			case 6:
				$ret = ((($x*$y)&1)+($x*$y)%3)&1;
				break;
			case 7:
				$ret = ((($x*$y)%3)+(($x+$y)&1))&1;
				break;
		}
		
		return $ret;
	}

	private function generateMaskNo($maskNo)
	{
		$bitMask = $this->empty_grid;
		
		for($y=0; $y<$this->width; $y++) {
			for($x=0; $x<$this->width; $x++) {
				if(ord($this->frame[$y][$x]) & 0x80) {
					$bitMask[$y][$x] = 0;
				} else {
					$maskFunc = $this->maskByNum($x, $y, $maskNo);
					$bitMask[$y][$x] = ($maskFunc == 0)?1:0;
				}
			}
		}
		
		return $bitMask;
	}

	private function makeMaskNo(&$masked, $maskNo)
	{
		$b = 0;

		$bitMask = $this->generateMaskNo($maskNo);

		$s = $this->frame;
		$masked = $this->frame;

		for($y=0; $y<$this->width; $y++) {
			for($x=0; $x<$this->width; $x++) {
				if($bitMask[$y][$x] == 1) {
					$masked[$y][$x] = chr(ord($s[$y][$x]) ^ floor($bitMask[$y][$x]));
				}
				$b += floor(ord($masked[$y][$x]) & 1);
			}
		}

		return $b;
	}

	private function makeMask($maskNo)
	{
		$masked = $this->empty_grid;
		$this->makeMaskNo($masked, $maskNo);
		$this->writeFormatInformation($masked, $maskNo);
   
		return $masked;
	}

	private function calcN1N3($length)
	{
		$demerit = 0;

		for($i=0; $i<$length; $i++) {
			
			if($this->runLength[$i] >= 5) {
				$demerit += (QR_N1 + ($this->runLength[$i] - 5));
			}
			if($i & 1) {
				if(($i >= 3) && ($i < ($length-2)) && ($this->runLength[$i] % 3 == 0)) {
					$fact = floor($this->runLength[$i] / 3);
					if(($this->runLength[$i-2] == $fact) &&
					   ($this->runLength[$i-1] == $fact) &&
					   ($this->runLength[$i+1] == $fact) &&
					   ($this->runLength[$i+2] == $fact)) {
						if(($this->runLength[$i-3] < 0) || ($this->runLength[$i-3] >= (4 * $fact))) {
							$demerit += QR_N3;
						} elseif((($i+3) >= $length) || ($this->runLength[$i+3] >= (4 * $fact))) {
							$demerit += QR_N3;
						}
					}
				}
			}
		}
		return $demerit;
	}

	private function evaluateSymbol($mask)
	{
		$head = 0;
		$demerit = 0;

		for($y=0; $y<$this->width; $y++) {
			$head = 0;
			$this->runLength[0] = 1;
			
			$frameY = $mask[$y];
			
			if ($y>0){
				$frameYM = $mask[$y-1];
			}
			
			for($x=0; $x<$this->width; $x++) {
				if(($x > 0) && ($y > 0)) {
					$b22 = ord($frameY[$x]) & ord($frameY[$x-1]) & ord($frameYM[$x]) & ord($frameYM[$x-1]);
					$w22 = ord($frameY[$x]) | ord($frameY[$x-1]) | ord($frameYM[$x]) | ord($frameYM[$x-1]);
					
					if(($b22 | ($w22 ^ 1))&1) {
						$demerit += QR_N2;
					}
				}
				if(($x == 0) && (ord($frameY[$x]) & 1)) {
					$this->runLength[0] = -1;
					$head = 1;
					$this->runLength[$head] = 1;
				} elseif($x > 0) {
					if((ord($frameY[$x]) ^ ord($frameY[$x-1])) & 1) {
						$head++;
						$this->runLength[$head] = 1;
					} else {
						$this->runLength[$head]++;
					}
				}
			}

			$demerit += $this->calcN1N3($head+1);
		}

		for($x=0; $x<$this->width; $x++) {
			$head = 0;
			$this->runLength[0] = 1;
			
			for($y=0; $y<$this->width; $y++) {
				if($y == 0 && (ord($mask[$y][$x]) & 1)) {
					$this->runLength[0] = -1;
					$head = 1;
					$this->runLength[$head] = 1;
				} elseif($y > 0) {
					if((ord($mask[$y][$x]) ^ ord($mask[$y-1][$x])) & 1) {
						$head++;
						$this->runLength[$head] = 1;
					} else {
						$this->runLength[$head]++;
					}
				}
			}
		
			$demerit += $this->calcN1N3($head+1);
		}

		return $demerit;
	}

	private function mask()
	{
		$minDemerit = PHP_INT_MAX;
		$bestMaskNum = 0;
		$checked_masks = [0,1,2,3,4,5,6,7];

		if (QR_FIND_FROM_RANDOM !== false) {
			
			$howManuOut = 8-(QR_FIND_FROM_RANDOM % 9);
			for ($i = 0; $i < $howManuOut; $i++) {
				$remPos = rand (0, count($checked_masks)-1);
				unset($checked_masks[$remPos]);
				$checked_masks = array_values($checked_masks);
			}
		}

		$bestMask = $this->frame;

		foreach($checked_masks as $i) {
			
			$mask = $this->empty_grid;

			$blacks  = $this->makeMaskNo($mask, $i);
			$blacks += $this->writeFormatInformation($mask, $i);
			$blacks  = floor(100 * $blacks / ($this->width * $this->width));
			$demerit = floor(floor(abs($blacks - 50) / 5) * QR_N4);
			$demerit += $this->evaluateSymbol($mask);
			
			if($demerit < $minDemerit) {
				$minDemerit = $demerit;
				$bestMask = $mask;
				$bestMaskNum = $i;
			}
		}

		return $bestMask;
	}

	public function get(int $mask)
	{
		# $mask is always -1
		if($mask < 0) {
		
			if (QR_FIND_BEST_MASK) {
				$masked = $this->mask();
			} else {
				$masked = $this->makeMask(QR_DEFAULT_MASK % 8);
			}
		} else {
			$masked = $this->makeMask($mask);
		}

		return $masked;
	}

}

?>