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

	private $runLength;
	private $width;
	private $level;
	private $frame;
	private $masked;

	// See calcFormatInfo in tests/test_qrspec.c (orginal qrencode c lib)
	private $formatInfo = [
		[0x77c4, 0x72f3, 0x7daa, 0x789d, 0x662f, 0x6318, 0x6c41, 0x6976],
		[0x5412, 0x5125, 0x5e7c, 0x5b4b, 0x45f9, 0x40ce, 0x4f97, 0x4aa0],
		[0x355f, 0x3068, 0x3f31, 0x3a06, 0x24b4, 0x2183, 0x2eda, 0x2bed],
		[0x1689, 0x13be, 0x1ce7, 0x19d0, 0x0762, 0x0255, 0x0d0c, 0x083b]
	];

	function __construct(array $dataCode, int $dataLength, int $width, int $level, int $version)
	{
		$this->runLength = array_fill(0, QR_SPEC_WIDTH_MAX + 1, 0);
		$this->width = $width;
		$this->level = $level;
		$this->frame = (new QRFrame($version, $width, $this->level))->getFrame($dataCode, $dataLength);
	}

	private function writeFormatInformation($maskNo)
	{
		$format = $this->formatInfo[$this->level][$maskNo];

		$blacks = 0;

		for($i=0; $i<8; $i++) {
			if($format & 1) {
				$blacks += 2;
				$v = 133;
			} else {
				$v = 132;
			}

			$this->masked[8][$this->width - 1 - $i] = $v;
			if($i < 6) {
				$this->masked[$i][8] = $v;
			} else {
				$this->masked[$i + 1][8] = $v;
			}
			$format >>= 1;
		}

		for($i=0; $i<7; $i++) {
			if($format & 1) {
				$blacks += 2;
				$v = 133;
			} else {
				$v = 132;
			}

			$this->masked[$this->width - 7 + $i][8] = $v;
			if($i == 0) {
				$this->masked[8][7] = $v;
			} else {
				$this->masked[8][6 - $i] = $v;
			}

			$format >>= 1;
		}

		return $blacks;
	}

	private function makeMaskNo($maskNo)
	{
		$blacks = 0;

		for($y=0; $y<$this->width; $y++) {
			for($x=0; $x<$this->width; $x++) {
				if((($this->masked[$y][$x]) & 128) == false) { # 0x80

					switch($maskNo){
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
							$ret = ((int)($y/2)+(int)($x/3))&1;
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

					if ($ret == 0){
						$this->masked[$y][$x]++;
					}
				}
				$blacks += ($this->masked[$y][$x] & 1);
			}
		}

		$blacks += $this->writeFormatInformation($maskNo);

		return (int)(100 * $blacks / ($this->width * $this->width));
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
					$fact = (int)($this->runLength[$i] / 3);
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

	private function evaluateSymbol()
	{
		$demerit = 0;

		for($y=0; $y<$this->width; $y++) {
			$head = 0;
			$this->runLength[0] = 1;

			$frameY = $this->masked[$y];

			if ($y > 0){
				$frameYM = $this->masked[$y-1];
			}

			if($frameY[0] & 1) {
				$this->runLength[0] = -1;
				$head = 1;
				$this->runLength[$head] = 1;
			}

			for($x=1; $x<$this->width; $x++) {
				if($y > 0) {
					$b22 = $frameY[$x] & $frameY[$x-1] & $frameYM[$x] & $frameYM[$x-1];
					$w22 = $frameY[$x] | $frameY[$x-1] | $frameYM[$x] | $frameYM[$x-1];
					if(($b22 | ($w22 ^ 1))&1) {
						$demerit += QR_N2;
					}
				}

				if(($frameY[$x] ^ $frameY[$x-1]) & 1) {
					$head++;
					$this->runLength[$head] = 1;
				} else {
					$this->runLength[$head]++;
				}
			}

			$demerit += $this->calcN1N3($head+1);
		}

		for($x=0; $x<$this->width; $x++) {
			$head = 0;
			$this->runLength[0] = 1;

			if(($this->masked[0][$x]) & 1) {
				$this->runLength[0] = -1;
				$head = 1;
				$this->runLength[$head] = 1;
			}

			for($y=0; $y<$this->width; $y++) {
				if ($y > 0) {
					if(($this->masked[$y][$x] ^ $this->masked[$y-1][$x]) & 1) {
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

	public function get()
	{
		$minDemerit = PHP_INT_MAX;

		foreach([0,1,2,3,4,5,6,7] as $i) {

			$this->masked = $this->frame;

			$blacks = $this->makeMaskNo($i);

			$demerit = (int)(abs($blacks - 50) / 5) * QR_N4 + $this->evaluateSymbol();

			if($demerit < $minDemerit) {
				$minDemerit = $demerit;
				$bestMask = $this->masked;
			}
		}

		return $bestMask;
	}

}

?>